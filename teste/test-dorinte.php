<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — tabla cu dorințe.
 *
 * Cere BAZA DE DATE. Partea de HTTP cere și SERVERUL, dar se sare singură dacă
 * nu i se dă o adresă.
 *
 * Cum se rulează:
 *     php teste/test-dorinte.php                        (fără HTTP)
 *     php teste/test-dorinte.php http://127.0.0.1:8099  (cu tot)
 *
 * Își face singur oamenii și dorințele de care are nevoie, cu nume care nu se
 * pot încurca cu ale nimănui, și le șterge la sfârșit — și dacă pică ceva la
 * mijloc, prin curata() de la coadă.
 */

require_once __DIR__ . '/../inc/dorinte.php';

$BAZA = rtrim($argv[1] ?? '', '/');

$treceri = 0; $picaturi = 0;

function verifica(string $ce, $asteptat, $primit): void
{
    global $treceri, $picaturi;
    $ok = $asteptat === $primit;
    $ok ? $treceri++ : $picaturi++;
    printf("%-58s %s%s\n", $ce, $ok ? 'OK' : 'PICAT',
        $ok ? '' : "  (aștept " . var_export($asteptat, true) . ", am primit " . var_export($primit, true) . ")");
}

function sectiune(string $nume): void
{
    echo "\n" . str_repeat('-', 60) . "\n  " . mb_strtoupper($nume, 'UTF-8') . "\n"
       . str_repeat('-', 60) . "\n";
}

const SEMN   = 'test-dorinte-';
const PAROLA = 'ParolaDeProba#2026';

function curata(): void
{
    // Întâi dorințele: de ele atârnă rândurile din `membri`.
    db()->prepare(
        'DELETE d FROM dorinte d JOIN membri m ON m.id = d.membru_id
          WHERE m.permalink LIKE ?'
    )->execute(['tstdor-%']);

    db()->prepare('DELETE FROM membri WHERE permalink LIKE ?')->execute(['tstdor-%']);
}

curata();
register_shutdown_function('curata');

function faMembru(string $cheie, string $nume, string $prenume, string $stare = 'activ'): int
{
    db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, sex, data_nasterii,
                             parola_hash, stare, este_staff, creat_la, confirmat_la)
         VALUES (?,?,?,?,"M","1990-01-01",?,?,0,?,?)'
    )->execute([
        substr('tstdor-' . $cheie, 0, 16), $nume, $prenume,
        SEMN . $cheie . '@invalid.local',
        password_hash(PAROLA, PASSWORD_DEFAULT), $stare, acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

/**
 * O dorință scrisă de-a dreptul în bază, cu starea și vechimea cerute.
 *
 * `$publicatAcumMinuteInUrma` = null înseamnă „nepublicată" (publicat_la NULL).
 */
function faDorinta(int $membruId, string $text, string $stare = 'aprobat',
                   ?int $publicatAcumMinuteInUrma = 60): int
{
    db()->prepare(
        'INSERT INTO dorinte (membru_id, oras, dorinta, stare_moderare, creat_la, publicat_la)
              VALUES (?,?,?,?,?,?)'
    )->execute([
        $membruId, oraseDisponibile()[0] ?? 'Roman', $text, $stare,
        acumMinus(60 * 24 * 9),
        $publicatAcumMinuteInUrma === null ? null : acumMinus($publicatAcumMinuteInUrma),
    ]);

    return (int) db()->lastInsertId();
}

/** Doar dorințele făcute de proba asta, dintr-o listă venită de pe tablă. */
function aleNoastre(array $tabla): array {
    $ale = [];
    foreach ($tabla as $d) {
        if (str_starts_with((string) $d['dorinta'], 'TSTDOR')) { $ale[] = $d; }
    }
    return $ale;
}

$orasBun = oraseDisponibile()[0] ?? 'Roman';

/* ==================================================================== */
sectiune('ce se primește de la formular');

$r = verificaDorinta(['oras' => $orasBun, 'dorinta' => 'un turneu de șah în parc'],
                     oraseDisponibile());
verifica('o dorință bună trece', [], $r['erori']);
verifica('cu textul curat', 'un turneu de șah în parc', $r['curat']['dorinta']);
verifica('și cu orașul ales', $orasBun, $r['curat']['oras']);

$r = verificaDorinta(['oras' => '', 'dorinta' => 'un turneu de șah în parc'],
                     oraseDisponibile());
verifica('fără oraș, nu trece', true, isset($r['erori']['oras']));
verifica('și textul nu se ia în seamă', false, isset($r['curat']['dorinta']) && $r['erori'] === []);

$r = verificaDorinta(['oras' => 'Vaslui', 'dorinta' => 'un turneu de șah în parc'],
                     oraseDisponibile());
verifica('un oraș din afara listei, nu trece', true, isset($r['erori']['oras']));

$r = verificaDorinta(['oras' => $orasBun, 'dorinta' => ''], oraseDisponibile());
verifica('fără text, nu trece', true, isset($r['erori']['dorinta']));

$r = verificaDorinta(['oras' => $orasBun, 'dorinta' => 'scurt'], oraseDisponibile());
verifica('sub minim, nu trece', true, isset($r['erori']['dorinta']));

/**
 * Caracterele se numără cu mb_strlen, nu cu strlen. Textul de mai jos are fix
 * 100 de CARACTERE, dar mai mulți octeți, fiindcă „ă" ocupă doi. Numărat pe
 * octeți, ar fi fost respins pe nedrept — exact regula din CLAUDE.md.
 */
$fixLaLimita = str_repeat('ă', DORINTA_MAX);
verifica('proba are fix 100 de caractere', DORINTA_MAX, mb_strlen($fixLaLimita, 'UTF-8'));
verifica('dar mai mulți octeți', true, strlen($fixLaLimita) > DORINTA_MAX);

$r = verificaDorinta(['oras' => $orasBun, 'dorinta' => $fixLaLimita], oraseDisponibile());
verifica('fix la limită, trece', [], $r['erori']);

$r = verificaDorinta(['oras' => $orasBun, 'dorinta' => $fixLaLimita . 'ă'], oraseDisponibile());
verifica('un caracter peste, nu trece', true, isset($r['erori']['dorinta']));

/* Un singur rând: Enter-ul nu face paragrafe pe tablă. */
$r = verificaDorinta(['oras' => $orasBun, 'dorinta' => "un turneu\n\n\nde   șah"],
                     oraseDisponibile());
verifica('rândurile noi se strâng în spații', 'un turneu de șah', $r['curat']['dorinta']);

$r = verificaDorinta(['oras' => $orasBun, 'dorinta' => "  un turneu de \x00șah  "],
                     oraseDisponibile());
verifica('caracterele de control se scot', 'un turneu de șah', $r['curat']['dorinta']);

$r = verificaDorinta(['oras' => $orasBun, 'dorinta' => 'un meci de şah şi ţintar'],
                     oraseDisponibile());
verifica('sedila devine virgulă', 'un meci de șah și țintar', $r['curat']['dorinta']);

/* ==================================================================== */
sectiune('ce ajunge pe tablă');

$ana   = faMembru('ana',   'Popescu', 'Ana');
$radu  = faMembru('radu',  'Ionescu', 'Radu');
$dan   = faMembru('dan',   'Marin',   'Dan');
$plecat = faMembru('plecat', 'Fantoma', 'Ioan', 'suspendat');

$dAna = faDorinta($ana,  'TSTDOR aprobată și proaspătă');
faDorinta($radu, 'TSTDOR încă necitită', 'in_asteptare', null);
faDorinta($dan,  'TSTDOR respinsă', 'respins', null);
faDorinta($plecat, 'TSTDOR a unuia plecat');

$tabla = aleNoastre(dorinteDePeTabla());
$texte = array_column($tabla, 'dorinta');

verifica('cea aprobată e pe tablă', true, in_array('TSTDOR aprobată și proaspătă', $texte, true));
verifica('cea necitită NU e', false, in_array('TSTDOR încă necitită', $texte, true));
verifica('cea respinsă NU e', false, in_array('TSTDOR respinsă', $texte, true));
verifica('a unuia plecat NU e', false, in_array('TSTDOR a unuia plecat', $texte, true));

/* Vechimea se numără de la publicare, nu de la scriere. */
$vechi = faMembru('vechi', 'Radu', 'Emil');
faDorinta($vechi, 'TSTDOR ieșită de pe tablă', 'aprobat', ZILE_PE_TABLA * 24 * 60 + 10);

$texte = array_column(aleNoastre(dorinteDePeTabla()), 'dorinta');
verifica('trecută de a șaptea zi, iese de pe tablă',
    false, in_array('TSTDOR ieșită de pe tablă', $texte, true));

$aproape = faMembru('aproape', 'Radu', 'Vlad');
faDorinta($aproape, 'TSTDOR chiar la margine', 'aprobat', ZILE_PE_TABLA * 24 * 60 - 10);

$texte = array_column(aleNoastre(dorinteDePeTabla()), 'dorinta');
verifica('cu zece minute înainte, tot pe tablă e',
    true, in_array('TSTDOR chiar la margine', $texte, true));

/* Numele vine gata desfăcut, ca să nu-l caute pagina din nou. */
$peTabla = null;
foreach (aleNoastre(dorinteDePeTabla()) as $d) {
    if ((string) $d['dorinta'] === 'TSTDOR aprobată și proaspătă') { $peTabla = $d; }
}
verifica('dorința aduce numele omului', 'Popescu', $peTabla['nume'] ?? null);
verifica('și prenumele', 'Ana', $peTabla['prenume'] ?? null);
verifica('numele scris pe tablă e cel scurt', 'P. Ana',
    numeAfisat((string) ($peTabla['nume'] ?? ''), (string) ($peTabla['prenume'] ?? '')));

/* Cel mult zece, oricâte ar fi. */
for ($i = 0; $i < 14; $i++) {
    $om = faMembru('mult' . $i, 'Popa', 'Om' . $i);
    faDorinta($om, 'TSTDOR multă ' . $i);
}
verifica('pe tablă încap cel mult zece', true, count(dorinteDePeTabla()) <= DORINTE_PE_TABLA);
verifica('și chiar sunt zece, când există', DORINTE_PE_TABLA, count(dorinteDePeTabla()));

/**
 * Se aleg la întâmplare. Zece trageri din peste zece dorințe care nu dau
 * niciodată două liste diferite ar însemna că ORDER BY RAND() nu-și face
 * treaba — iar a unsprezecea n-ar fi citită niciodată de nimeni.
 */
$liste = [];
for ($i = 0; $i < 12; $i++) {
    $liste[] = implode('|', array_column(dorinteDePeTabla(), 'id'));
}
verifica('nu e mereu aceeași zecime', true, count(array_unique($liste)) > 1);

/* ==================================================================== */
sectiune('ștampila de publicare');

$nestampilat = faMembru('nest', 'Popa', 'Vasile');
$idNest = faDorinta($nestampilat, 'TSTDOR aprobată de mână', 'aprobat', null);

$q = db()->prepare('SELECT publicat_la FROM dorinte WHERE id = ?');
$q->execute([$idNest]);
verifica('aprobată din phpMyAdmin, n-are ștampilă', null, $q->fetchColumn());

dorinteDePeTabla();          // trecerea pe la tablă o pune

$q->execute([$idNest]);
$stampila = $q->fetchColumn();
verifica('după o trecere pe la tablă, are', true, is_string($stampila) && $stampila !== '');
verifica('și e pusă cu ceasul PHP, nu al bazei',
    true, abs(time() - (int) strtotime((string) $stampila)) < 60);

dorinteDePeTabla();
$q->execute([$idNest]);
verifica('a doua oară nu se mai pune', $stampila, $q->fetchColumn());

/* ==================================================================== */
sectiune('cine mai poate pune una');

$nou = faMembru('nou', 'Popa', 'Nicu');
verifica('cine n-a scris nimic, poate', 'poate', poatePuneODorinta($nou)['stare']);
verifica('și n-are niciuna în lucru',   0,       poatePuneODorinta($nou)['cate']);

/**
 * TREI DEODATĂ, nu una. Cine are UNA în lucru mai poate pune — asta s-a
 * schimbat: până acum, una singură îl oprea de tot.
 */
verifica('cine are una aprobată și proaspătă, mai poate',
    'poate', poatePuneODorinta($ana)['stare']);
verifica('dar ea îi ține un loc', 1, poatePuneODorinta($ana)['cate']);
verifica('cine are una necitită, mai poate',
    'poate', poatePuneODorinta($radu)['stare']);
verifica('și ea ține un loc',    1, poatePuneODorinta($radu)['cate']);

/* Cele care NU țin niciun loc: respinsa și cea ieșită de pe tablă. */
verifica('cui i-a fost respinsă, poate din nou',
    'poate', poatePuneODorinta($dan)['stare']);
verifica('și n-are niciun loc luat', 0, poatePuneODorinta($dan)['cate']);
verifica('cui i-a ieșit de pe tablă, poate din nou',
    'poate', poatePuneODorinta($vechi)['stare']);
verifica('și nici el', 0, poatePuneODorinta($vechi)['cate']);

/**
 * A TREIA UMPLE LOCURILE. Se pun încă două peste cea pe care o are deja Ana:
 * una pe tablă și una necitită — amândouă felurile țin un loc.
 */
faDorinta($ana, 'TSTDOR a doua a Anei', 'aprobat', 30);
verifica('cu două în lucru, tot mai poate', 'poate', poatePuneODorinta($ana)['stare']);
verifica('și se numără două',                2,      poatePuneODorinta($ana)['cate']);

$aTreiaAnei = faDorinta($ana, 'TSTDOR a treia a Anei', 'in_asteptare', null);
verifica('cu trei, nu mai poate', 'prea_multe', poatePuneODorinta($ana)['stare']);
verifica('și se numără trei',      3,           poatePuneODorinta($ana)['cate']);
verifica('adică fix cât scrie în DORINTE_DEODATA', DORINTE_DEODATA,
    poatePuneODorinta($ana)['cate']);

/* Cea mai nouă stă prima în listă: aceea e la care se gândește omul. */
verifica('cea mai nouă e prima în listă', $aTreiaAnei,
    (int) poatePuneODorinta($ana)['dorinte'][0]['id']);

$iese = dorintaIeseDePeTabla(poatePuneODorinta($ana)['dorinte'][2]);
verifica('se știe și când iese cea dintâi', true, $iese !== null && $iese > time());
verifica('adică peste vreo șapte zile', true,
    $iese !== null && abs($iese - (time() + ZILE_PE_TABLA * 24 * 3600 - 3600)) < 120);

/* ==================================================================== */
sectiune('ștergerea unei dorințe');

/**
 * ȘTERGEREA E MOALE: rândul rămâne în bază, cu `sters_la` scris. Rândurile din
 * `dorinte` nu se șterg niciodată — mai târziu vrem să putem spune câte
 * dorințe și-au pus oamenii de-a lungul timpului, iar o ștergere adevărată ar
 * fi luat din numărătoarea aceea tocmai dorințele la care cineva chiar s-a
 * gândit. Pentru cel care apasă, înseamnă însă tot ce trebuie: dispare de pe
 * tablă, iese din tabelul lui, și face loc alteia.
 */
$cateRanduri = static function (int $cine): int {
    $q = db()->prepare('SELECT COUNT(*) FROM dorinte WHERE membru_id = ?');
    $q->execute([$cine]);
    return (int) $q->fetchColumn();
};

$randuriAnei = $cateRanduri($ana);

verifica('cu trei în lucru, nu mai poate pune', 'prea_multe',
    poatePuneODorinta($ana)['stare']);

verifica('ștergerea prinde', true, stergeDorintaOmului($ana, $aTreiaAnei));
verifica('și face loc',      'poate', poatePuneODorinta($ana)['stare']);
verifica('mai are două în lucru', 2,  poatePuneODorinta($ana)['cate']);

/* Rândul e tot acolo — doar cu ștampila pusă. */
verifica('rândul NU se șterge din bază', $randuriAnei, $cateRanduri($ana));

$q = db()->prepare('SELECT sters_la FROM dorinte WHERE id = ?');
$q->execute([$aTreiaAnei]);
$stersLa = $q->fetchColumn();
verifica('are ștampila de ștergere', true, is_string($stersLa) && $stersLa !== '');
verifica('pusă cu ceasul PHP, nu al bazei', true,
    abs(time() - (int) strtotime((string) $stersLa)) < 60);

/**
 * A DOUA APĂSARE NU MAI PRINDE. Hotărârea e în `WHERE` (`sters_la IS NULL`),
 * nu într-un SELECT de dinainte: două apăsări în aceeași clipă, sau o filă
 * lăsată deschisă, nu mută ștampila.
 */
verifica('a doua oară, nu', false, stergeDorintaOmului($ana, $aTreiaAnei));
$q->execute([$aTreiaAnei]);
verifica('și ștampila nu se mută', $stersLa, $q->fetchColumn());

/**
 * A ALTUIA, NU. Cererea poate veni de oriunde, cu orice id în ea — regula e
 * tot în `WHERE` (`membru_id = ?`), nu în butonul de pe ecran.
 */
$dorintaLuiRadu = (int) poatePuneODorinta($radu)['dorinte'][0]['id'];
verifica('dorința altuia nu se șterge', false, stergeDorintaOmului($ana, $dorintaLuiRadu));
verifica('și a lui e neatinsă',         1,     poatePuneODorinta($radu)['cate']);

/* Numere care nu duc nicăieri se poartă la fel — același răspuns. */
verifica('un id care nu există, nu', false, stergeDorintaOmului($ana, 999999));
verifica('zero, nu',                 false, stergeDorintaOmului($ana, 0));
verifica('fără membru, nu',          false, stergeDorintaOmului(0, $aTreiaAnei));

/* ȘI DISPARE DE PE TABLĂ — altfel ștergerea n-ar fi însemnat nimic. */
$peTabla = faDorinta($ana, 'TSTDOR de pe tablă, până o șterg', 'aprobat', 5);

/**
 * TABLA IA ZECE LA ÎNTÂMPLARE din câte sunt (ORDER BY RAND), deci o singură
 * trecere nu dovedește nimic: pe o bază cu douăzeci de dorințe proaspete, a
 * noastră lipsește din jumătate din trageri, iar proba ar fi picat când și
 * când — fără nicio legătură cu ce verifică. Se trag mai multe și se
 * întreabă dacă a apărut MĂCAR O DATĂ.
 *
 * Numărul e ales pe partea sigură: la douăzeci de candidate, șansa să lipsească
 * din patruzeci de trageri e sub o miime de miliardime. Iar la mai puțin de
 * zece candidate, o găsește din prima.
 */
$TRAGERI = 40;

$apareInTabla = static function (string $text) use ($TRAGERI): bool {
    for ($i = 0; $i < $TRAGERI; $i++) {
        foreach (dorinteDePeTabla() as $d) {
            if ((string) $d['dorinta'] === $text) { return true; }
        }
    }
    return false;
};

verifica('până s-o șteargă, e pe tablă', true,
    $apareInTabla('TSTDOR de pe tablă, până o șterg'));

stergeDorintaOmului($ana, $peTabla);

/* Iar de aici încolo NU mai apare, în nicio tragere. */
verifica('după ștergere, nu mai e', false,
    $apareInTabla('TSTDOR de pe tablă, până o șterg'));

/* ==================================================================== */
sectiune('scrierea unei dorințe');

$scrie = faMembru('scrie', 'Popa', 'Sorin');

$r = puneODorinta($scrie, ['oras' => $orasBun, 'dorinta' => 'TSTDOR o seară de jocuri']);
verifica('o dorință bună se scrie', true, $r['ok']);
verifica('și i se spune omului ce urmează', MESAJ_DORINTA_TRIMISA, $r['mesaj']);

$aScris = poatePuneODorinta($scrie)['dorinte'][0] ?? [];
verifica('intră în așteptare, nu direct pe tablă',
    'in_asteptare', $aScris['stare_moderare'] ?? null);
// `??` ar fi înghițit tocmai NULL-ul căutat: null ?? 'x' dă 'x'.
verifica('și fără ștampilă de publicare', true,
    array_key_exists('publicat_la', $aScris) && $aScris['publicat_la'] === null);

/**
 * Regula celor trei se ține LA SCRIERE, nu în butonul de pe ecran.
 *
 * Iar întrebarea și scrierea stau sub același lacăt, pe rândul omului: două
 * SESIUNI deosebite ale aceluiași om — laptopul și telefonul — trimiteau
 * amândouă în aceeași clipă, întrebau amândouă înainte ca vreuna să scrie, și
 * intrau amândouă. (Două file ale ACELUIAȘI browser nu erau de ajuns ca să se
 * vadă: PHP ține un lacăt pe fișierul sesiunii, deci se așteptau oricum una pe
 * alta.)
 */
$r = puneODorinta($scrie, ['oras' => $orasBun, 'dorinta' => 'TSTDOR și încă una']);
verifica('a doua trece',  true, $r['ok']);
$r = puneODorinta($scrie, ['oras' => $orasBun, 'dorinta' => 'TSTDOR și a treia']);
verifica('și a treia la fel', true, $r['ok']);

$r = puneODorinta($scrie, ['oras' => $orasBun, 'dorinta' => 'TSTDOR dar a patra, nu']);
verifica('a patra nu trece', false, $r['ok']);
verifica('cu 409, nu cu 422', 409, $r['cod']);
verifica('și i se spune de unde le poate șterge', true,
    str_contains($r['mesaj'], 'Dorințele mele'));

/**
 * Și tranzacția s-a închis în urma ei. Un refuz care ar fi ieșit din funcție
 * lăsând lacătul pus ar fi ținut rândul omului încuiat până la sfârșitul
 * cererii — iar la o cerere lungă, toate celelalte scrieri pe el ar fi
 * așteptat degeaba.
 */
verifica('și nu rămâne nicio tranzacție deschisă', false, db()->inTransaction());

$q = db()->prepare('SELECT COUNT(*) FROM dorinte WHERE membru_id = ?');
$q->execute([$scrie]);
verifica('și chiar n-a intrat în bază', DORINTE_DEODATA, (int) $q->fetchColumn());

/**
 * DAR DUPĂ CE ȘTERGE UNA, POATE DIN NOU. Asta face ștergerea folositoare: fără
 * ea, „trei" ar fi fost tot o ușă închisă, doar de trei ori mai încolo.
 */
$deSters = (int) poatePuneODorinta($scrie)['dorinte'][0]['id'];
stergeDorintaOmului($scrie, $deSters);

$r = puneODorinta($scrie, ['oras' => $orasBun, 'dorinta' => 'TSTDOR una în locul ei']);
verifica('după o ștergere, se face loc', true, $r['ok']);

/* Verificarea textului se face înaintea regulii de o dorință. */
$r = puneODorinta($nou, ['oras' => '', 'dorinta' => 'x']);
verifica('un formular greșit se întoarce cu 422', 422, $r['cod']);
verifica('cu erorile pe câmpuri', true, isset($r['erori']['oras'], $r['erori']['dorinta']));

$q->execute([$nou]);
verifica('și nu scrie nimic', 0, (int) $q->fetchColumn());

/* ==================================================================== */
sectiune('cum arată');

verifica('fără dorințe, tabla nu se desenează deloc', '', randeazaTablaDorinte([]));

$html = randeazaTablaDorinte([
    ['id' => 1, 'oras' => 'Roman', 'dorinta' => 'un turneu de <șah>',
     'publicat_la' => acum(), 'nume' => 'Popescu', 'prenume' => 'Ana'],
    ['id' => 2, 'oras' => 'Roman', 'dorinta' => 'o alergare',
     'publicat_la' => acum(), 'nume' => 'Ionescu', 'prenume' => 'Radu'],
]);

verifica('numele omului e scris scurt', true, str_contains($html, 'P. Ana'));
verifica('și dorința lui', true, str_contains($html, 'și-ar dori'));
verifica('textul se escapează la randare', true, str_contains($html, '&lt;șah&gt;'));
verifica('neescapat nu apare', false, str_contains($html, '<șah>'));
verifica('prima e cea care se vede', 1, substr_count($html, 'dorinta is-activa'));
verifica('celelalte sunt ascunse', 1, substr_count($html, 'data-dorinta aria-hidden="true"'));
verifica('sunt puncte cât dorințe', 2, substr_count($html, 'data-dorinta-punct'));
verifica('și orașul e scris', true, str_contains($html, 'Roman'));

$unaSingura = randeazaTablaDorinte([
    ['id' => 1, 'oras' => 'Roman', 'dorinta' => 'una singură',
     'publicat_la' => acum(), 'nume' => 'Popescu', 'prenume' => 'Ana'],
]);
verifica('la o singură dorință nu se pun puncte',
    false, str_contains($unaSingura, 'tabla__puncte'));

/**
 * BUTONUL, care stă în fereastra de bun venit.
 *
 * Se vede doar cât timp omul chiar mai poate pune o dorință: cu una în lucru,
 * ar fi dus la un formular pe care serverul îl refuză oricum.
 */
$b = butonulDorintei(false, '');
verifica('nelogat, butonul duce la intrare', true, str_contains($b, 'login.php?redirect='));
verifica('și cu drumul de întoarcere în adresă',
    true, str_contains($b, urlencode('/index.php#dorinta-formular')));

$b = butonulDorintei(true, 'poate');
verifica('logat, butonul deschide formularul',
    true, str_contains($b, 'href="#dorinta-formular"'));
verifica('și stă lângă „Propune o ieșire"', true, str_contains($b, 'hero__cta'));

/* Butonul dispare DOAR când le are pe toate trei. Cu una sau două, rămâne. */
verifica('cu una în lucru, butonul rămâne', true,
    str_contains(butonulDorintei(true, 'poate'), 'href="#dorinta-formular"'));
verifica('cu toate trei, nu mai e niciun buton', '',
    butonulDorintei(true, 'prea_multe'));

/**
 * VORBA DE SUB TABLĂ ȘI „DORINȚELE MELE".
 *
 * Vorba spune câte are și câte mai încap; sub ea stă butonul care deschide
 * tabelul cu ele, cu „×" în dreptul fiecăreia.
 */
verifica('nelogat, sub tablă nu scrie nimic', '', randeazaZonaDorinte(false, ''));
verifica('nici cui n-are niciuna',            '', randeazaZonaDorinte(true, 'poate', []));

$aleMele = poatePuneODorinta($ana);
$z = randeazaZonaDorinte(true, $aleMele['stare'], $aleMele['dorinte']);

verifica('scrie câte are în lucru', true, str_contains($z, 'dorințe în lucru'));
verifica('și câte mai încap',       true, str_contains($z, 'Mai poți pune'));
verifica('și niciun buton de pus una', false, str_contains($z, '<a class="btn'));

/**
 * TABELUL. Un `<details>`, nu un panou deschis de JS: se deschide singur, în
 * orice browser, fără o linie de JavaScript.
 */
verifica('e un <details>',            true, str_contains($z, '<details class="dorintele"'));
verifica('cu numărul pe buton',       true, str_contains($z, 'Dorințele mele (2)'));
verifica('și cu un rând de fiecare',  2,    substr_count($z, 'dorintele__rand'));

/**
 * FIECARE „×" E UN FORMULAR ADEVĂRAT, cu token. Fără JavaScript, apăsarea
 * ajunge în index.php și șterge; cu el, main.js îi ia locul.
 */
verifica('fiecare rând are formularul lui', 2, substr_count($z, 'method="post"'));
verifica('și fiecare are token',            2, substr_count($z, 'name="csrf"'));
verifica('cu id-ul dorinței în el',      true, str_contains($z, 'name="sterge_dorinta"'));
verifica('și cu „×"-ul lui',                2, substr_count($z, 'dorintele__x'));

/* Textul dorinței se escapează, ca peste tot. */
$zRau = randeazaZonaDorinte(true, 'poate', [
    ['id' => 5, 'oras' => 'Roman', 'dorinta' => 'un <turneu> "de" șah',
     'stare_moderare' => 'in_asteptare', 'publicat_la' => null],
]);
verifica('textul se escapează la randare', true, str_contains($zRau, '&lt;turneu&gt;'));
verifica('neescapat nu apare',            false, str_contains($zRau, '<turneu>'));
verifica('nici în eticheta butonului',    false, str_contains($zRau, 'dorința „un <'));

/* Starea se scrie pentru FIECARE dorință în parte, nu una pentru om. */
verifica('cea necitită spune că așteaptă', true,
    str_contains($zRau, 'Așteaptă să fie citită'));

/**
 * Data are ziua săptămânii și n-are anul: „joi, 27 august".
 *
 * Ziua săptămânii e ce caută omul întâi („mai am până joi"), iar anul, la
 * șapte zile depărtare, nu spune nimic — e limpede că e cel de-acum.
 */
$ceaVeche = null;

foreach ($aleMele['dorinte'] as $d) {
    if ((string) $d['stare_moderare'] === 'aprobat') { $ceaVeche = $d; break; }
}

$ieseLa = dorintaIeseDePeTabla($ceaVeche ?? []);
$ziua   = ['duminică','luni','marți','miercuri','joi','vineri','sâmbătă'][(int) date('w', (int) $ieseLa)];

verifica('scrie „pe tablă până" și ziua săptămânii', true,
    str_contains(mb_strtolower($z, 'UTF-8'), 'pe tablă până ' . $ziua));
verifica('și ziua din lună cu luna', true,
    str_contains($z, date('j', (int) $ieseLa) . ' '
                   . numeleLunilor()[(int) date('n', (int) $ieseLa)]));
verifica('dar nu și anul', false, str_contains($z, date('Y', (int) $ieseLa)));

/* ==================================================================== */
if ($BAZA === '') {
    echo "\n(sar peste HTTP: dă adresa serverului ca argument, ex. "
       . "php teste/test-dorinte.php http://127.0.0.1:8099)\n";
} else {
    sectiune('prin http');

    $ia = static function (string $cale, string $cookie = '') use ($BAZA): string {
        $ctx = stream_context_create(['http' => [
            'ignore_errors' => true,
            'timeout'       => 10,
            'header'        => $cookie === '' ? '' : "Cookie: $cookie\r\n",
        ]]);
        return (string) @file_get_contents($BAZA . $cale, false, $ctx);
    };

    /**
     * Intră în cont și întoarce cookie-ul. Panoul de mulțumire stă în cutia
     * formularului, iar aceea nu se desenează pentru un vizitator fără cont —
     * deci fără o sesiune n-avem ce proba.
     */
    $intra = static function (string $email) use ($BAZA): string {
        $ceruta = static function (string $cale, ?array $trup, string $cookie) use ($BAZA): array {
            $ctx = stream_context_create(['http' => [
                'method'        => $trup === null ? 'GET' : 'POST',
                'header'        => "Content-Type: application/json\r\n"
                                 . ($cookie === '' ? '' : "Cookie: $cookie\r\n"),
                'content'       => $trup === null ? '' : json_encode($trup),
                'ignore_errors' => true,
                'timeout'       => 10,
            ]]);

            $corp = (string) @file_get_contents($BAZA . $cale, false, $ctx);
            $nou  = $cookie;

            foreach ($http_response_header ?? [] as $rand) {
                if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $rand, $m) === 1) { $nou = $m[1]; }
            }

            return ['corp' => $corp, 'cookie' => $nou];
        };

        $pag = $ceruta('/login.php', null, '');
        preg_match('/name="csrf" value="([^"]+)"/', $pag['corp'], $m);

        $r = $ceruta('/api/autentificare.php',
            ['csrf' => $m[1] ?? '', 'email' => $email, 'parola' => PAROLA], $pag['cookie']);

        return (json_decode($r['corp'], true)['ok'] ?? false) === true ? $r['cookie'] : '';
    };

    $pagina = $ia('/index.php');
    verifica('prima pagină se deschide', true, $pagina !== '');
    verifica('tabla e pe ea', true, str_contains($pagina, 'data-tabla'));
    verifica('cu eticheta ei', true, str_contains($pagina, 'Tabla cu dorințe'));
    verifica('vizitatorul fără cont vede butonul',
        true, str_contains($pagina, 'Pune-ți o dorință'));
    verifica('dar nu și formularul',
        false, str_contains($pagina, 'id="dorinta-formular"'));

    /**
     * PANOUL DE MULȚUMIRE ARE UN „×".
     *
     * Rămânea pe ecran până la următoarea navigare: omul citea vestea, o
     * înțelegea, și n-avea ce apăsa ca s-o dea la o parte. „×"-ul e o
     * LEGĂTURĂ către /index.php, ca să meargă și fără JavaScript — acolo
     * panoul e desenat fiindcă adresa poartă `?dorinta=trimisa`, iar o
     * încărcare curată îl face să dispară.
     *
     * Se cere ca un om conectat: panoul stă în cutia formularului, iar aceea
     * nu se desenează pentru un vizitator fără cont.
     */
    $cookieOm = $intra(SEMN . 'nou@invalid.local');
    verifica('proba a putut intra în cont', true, $cookieOm !== '');

    $cuCont = $ia('/index.php?dorinta=trimisa', $cookieOm);

    verifica('panoul de mulțumire are un „×"', true,
        str_contains($cuCont, 'data-dorinta-gata-x'));
    verifica('și e o legătură adevărată', true,
        str_contains($cuCont, '<a class="dorinta-gata__x" href="/index.php"'));

    /**
     * ȘI CÂND N-ARE CE ARĂTA.
     *
     * Tabla trebuie să dispară cu totul, iar butonul să urce în capul listei,
     * unde stătea „Propune o ieșire". Ca s-o vedem goală, împingem pentru o
     * clipă tot ce e publicat dincolo de a șaptea zi, și punem la loc.
     *
     * Punerea la loc se înscrie ÎNAINTE de împingere: dacă pică ceva la
     * mijloc, dorințele adevărate se întorc oricum pe tablă.
     */
    $peTablaAcum = db()->query(
        'SELECT id, publicat_la FROM dorinte
          WHERE stare_moderare = \'aprobat\' AND publicat_la IS NOT NULL'
    )->fetchAll();

    register_shutdown_function(static function () use ($peTablaAcum) {
        $u = db()->prepare('UPDATE dorinte SET publicat_la = ? WHERE id = ?');
        foreach ($peTablaAcum as $d) { $u->execute([$d['publicat_la'], $d['id']]); }
    });

    $vechime = acumMinus((ZILE_PE_TABLA + 1) * 24 * 60);
    $u = db()->prepare('UPDATE dorinte SET publicat_la = ? WHERE id = ?');
    foreach ($peTablaAcum as $d) { $u->execute([$vechime, $d['id']]); }

    verifica('cu tabla golită, chiar e goală', 0, count(dorinteDePeTabla()));

    $goala = $ia('/index.php');
    verifica('fără dorințe, tabla nu se desenează',
        false, str_contains($goala, 'data-tabla'));

    /**
     * Butonul rămâne oricum la locul lui, în fereastra de bun venit: el nu
     * atârnă de tablă, ci de ce mai poate face omul.
     */
    verifica('dar butonul din fereastră rămâne',
        true, str_contains($goala, 'hero__cta--dorinta'));

    /**
     * ȘI NIMIC NU SE MUTĂ ÎN CAPUL LISTEI.
     *
     * Vorba despre dorințele omului și butonul „Dorințele mele" ajungeau acolo
     * tocmai când tabla lipsea — pe același rând cu „Ce facem zilele astea?",
     * lipite de linia de bază a titlului. Acum secțiunea tablei se desenează
     * și fără tablă, numai pentru ele.
     */
    $capulListei = '';
    if (preg_match('~<div class="section-head">(.*?)</div>\s*</div>~s', $goala, $m)) {
        $capulListei = $m[1];
    }
    verifica('capul listei s-a găsit',            true,  $capulListei !== '');
    verifica('și n-are decât titlul în el',       true,  str_contains($capulListei, 'section-title'));
    verifica('fără vorba despre dorințe',         false, str_contains($capulListei, 'tabla__stare'));
    verifica('fără „Dorințele mele"',             false, str_contains($capulListei, 'dorintele__buton'));
    verifica('fără niciun buton',                 false, str_contains($capulListei, 'class="btn'));

    /**
     * ȘI PENTRU CINE CHIAR ARE DORINȚE — proba de mai sus se uită la pagina
     * unui vizitator fără cont, care n-are ce vedea acolo oricum.
     *
     * Radu e cel potrivit: dorința lui AȘTEAPTĂ să fie citită, iar golirea
     * tablei de mai sus a împins în trecut doar dorințele PUBLICATE. A lui
     * rămâne în lucru, deci vorba și butonul trebuie desenate — dar în
     * secțiunea tablei, nu lângă titlu.
     */
    $cookieAna = $intra(SEMN . 'radu@invalid.local');
    $goalaAna  = $ia('/index.php', $cookieAna);

    verifica('Radu a putut intra în cont', true, $cookieAna !== '');
    verifica('tabla lipsește și pentru el', false, str_contains($goalaAna, 'data-tabla'));
    verifica('dar „Dorințele mele" se vede', true,
        str_contains($goalaAna, 'dorintele__buton'));

    $capulEi = '';
    if (preg_match('~<div class="section-head">(.*?)</div>\s*</div>~s', $goalaAna, $m)) {
        $capulEi = $m[1];
    }
    verifica('și NU în capul listei', false, str_contains($capulEi, 'dorintele__buton'));
    verifica('ci în secțiunea tablei', true,
        preg_match('~<section class="tabla[^"]*"[^>]*>.*?dorintele__buton.*?</section>~s',
            $goalaAna) === 1);

    /* La loc, ca restul probei să vadă tabla plină. */
    $u = db()->prepare('UPDATE dorinte SET publicat_la = ? WHERE id = ?');
    foreach ($peTablaAcum as $d) { $u->execute([$d['publicat_la'], $d['id']]); }
    verifica('și s-a pus totul la loc', count($peTablaAcum) > 0, count(dorinteDePeTabla()) > 0);

    /* API-ul: aceleași porți ca peste tot. */
    $cheama = static function (array $date, string $metoda = 'POST') use ($BAZA): array {
        $ctx = stream_context_create(['http' => [
            'method'         => $metoda,
            'header'         => "Content-Type: application/json\r\n",
            'content'        => json_encode($date),
            'ignore_errors'  => true,
            'timeout'        => 10,
        ]]);
        $raw  = @file_get_contents($BAZA . '/api/dorinta.php', false, $ctx);
        $cod  = 0;
        foreach ($http_response_header ?? [] as $rand) {
            if (preg_match('~^HTTP/\S+\s+(\d+)~', $rand, $m)) { $cod = (int) $m[1]; }
        }
        return ['cod' => $cod, 'corp' => json_decode((string) $raw, true)];
    };

    $r = $cheama([], 'GET');
    verifica('GET nu e primit', 405, $r['cod']);

    $r = $cheama(['oras' => $orasBun, 'dorinta' => 'ceva de probă']);
    verifica('fără token CSRF, 419', 419, $r['cod']);

    verifica('și nimic nu s-a scris pe furiș', 0, (int) (static function () {
        $q = db()->prepare('SELECT COUNT(*) FROM dorinte WHERE dorinta = ?');
        $q->execute(['ceva de probă']);
        return $q->fetchColumn();
    })());

    /**
     * ȘTERGEREA: aceleași porți. Ea schimbă starea, deci trece prin aceleași
     * trei uși ca orice altă faptă de pe site — metodă, token, cont.
     */
    $cheamaStergerea = static function (array $date, string $metoda = 'POST') use ($BAZA): array {
        $ctx = stream_context_create(['http' => [
            'method'         => $metoda,
            'header'         => "Content-Type: application/json\r\n",
            'content'        => json_encode($date),
            'ignore_errors'  => true,
            'timeout'        => 10,
        ]]);
        $raw = @file_get_contents($BAZA . '/api/sterge-dorinta.php', false, $ctx);
        $cod = 0;
        foreach ($http_response_header ?? [] as $rand) {
            if (preg_match('~^HTTP/\S+\s+(\d+)~', $rand, $m)) { $cod = (int) $m[1]; }
        }
        return ['cod' => $cod, 'corp' => json_decode((string) $raw, true)];
    };

    /* O dorință adevărată, ca să se vadă că nu se atinge nimeni de ea. */
    $tinta = faDorinta($radu, 'TSTDOR ținta ștergerii prin http', 'aprobat', 10);

    $r = $cheamaStergerea([], 'GET');
    verifica('GET nu șterge nimic', 405, $r['cod']);

    $r = $cheamaStergerea(['id' => $tinta]);
    verifica('fără token CSRF, 419', 419, $r['cod']);

    $q = db()->prepare('SELECT sters_la FROM dorinte WHERE id = ?');
    $q->execute([$tinta]);
    // `??` ar fi înghițit tocmai NULL-ul căutat.
    verifica('și dorința e neatinsă', null, $q->fetchColumn() ?: null);
}

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
