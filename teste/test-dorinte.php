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
verifica('cine are una aprobată și proaspătă, nu',
    'e_pe_tabla', poatePuneODorinta($ana)['stare']);
verifica('cine are una necitită, așteaptă',
    'asteapta', poatePuneODorinta($radu)['stare']);
verifica('cui i-a fost respinsă, poate din nou',
    'poate', poatePuneODorinta($dan)['stare']);
verifica('cui i-a ieșit de pe tablă, poate din nou',
    'poate', poatePuneODorinta($vechi)['stare']);

$iese = dorintaIeseDePeTabla(dorintaMembrului($ana) ?? []);
verifica('se știe și când iese a lui', true, $iese !== null && $iese > time());
verifica('adică peste vreo șapte zile', true,
    $iese !== null && abs($iese - (time() + ZILE_PE_TABLA * 24 * 3600 - 3600)) < 120);

/* ==================================================================== */
sectiune('scrierea unei dorințe');

$scrie = faMembru('scrie', 'Popa', 'Sorin');

$r = puneODorinta($scrie, ['oras' => $orasBun, 'dorinta' => 'TSTDOR o seară de jocuri']);
verifica('o dorință bună se scrie', true, $r['ok']);
verifica('și i se spune omului ce urmează', MESAJ_DORINTA_TRIMISA, $r['mesaj']);

$aScris = dorintaMembrului($scrie);
verifica('intră în așteptare, nu direct pe tablă',
    'in_asteptare', $aScris['stare_moderare'] ?? null);
// `??` ar fi înghițit tocmai NULL-ul căutat: null ?? 'x' dă 'x'.
verifica('și fără ștampilă de publicare', true,
    array_key_exists('publicat_la', $aScris) && $aScris['publicat_la'] === null);

/**
 * Regula „o singură dorință" se ține LA SCRIERE, nu în butonul de pe ecran.
 * Două file deschise deodată ar fi trimis amândouă.
 */
$r = puneODorinta($scrie, ['oras' => $orasBun, 'dorinta' => 'TSTDOR și încă una']);
verifica('a doua nu trece', false, $r['ok']);
verifica('cu 409, nu cu 422', 409, $r['cod']);

$q = db()->prepare('SELECT COUNT(*) FROM dorinte WHERE membru_id = ?');
$q->execute([$scrie]);
verifica('și chiar n-a intrat în bază', 1, (int) $q->fetchColumn());

/* Nici cel care are una pe tablă. */
$r = puneODorinta($ana, ['oras' => $orasBun, 'dorinta' => 'TSTDOR încă o dorință']);
verifica('nici cine are una pe tablă nu poate', false, $r['ok']);
verifica('și i se spune până când', true, str_contains($r['mesaj'], 'pe tablă'));

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

verifica('cu una necitită, nu e niciun buton', '', butonulDorintei(true, 'asteapta'));
verifica('nici cu una pe tablă',               '', butonulDorintei(true, 'e_pe_tabla'));

/**
 * VORBA DE SUB TABLĂ, care a rămas acolo. Nu mai are buton în ea niciodată:
 * ori spune ceva despre dorința omului, ori nu spune nimic.
 */
verifica('nelogat, sub tablă nu scrie nimic', '', randeazaZonaDorinte(false, ''));
verifica('nici cui poate pune una',           '', randeazaZonaDorinte(true, 'poate'));

$z = randeazaZonaDorinte(true, 'asteapta');
verifica('cu una necitită, o vorbă despre ea',
    true, str_contains($z, 'așteaptă să fie citită'));
verifica('și niciun buton', false, str_contains($z, '<a class="btn'));

$z = randeazaZonaDorinte(true, 'e_pe_tabla', dorintaMembrului($ana));
verifica('cu una pe tablă, scrie până când stă', true, str_contains($z, 'până'));
verifica('și niciun buton', false, str_contains($z, '<a class="btn'));

/**
 * Data are ziua săptămânii și n-are anul: „Joi, 27 august".
 *
 * Ziua săptămânii e ce caută omul întâi („mai am până joi"), iar anul, la
 * șapte zile depărtare, nu spune nimic — e limpede că e cel de-acum.
 */
$ieseLa = dorintaIeseDePeTabla(dorintaMembrului($ana) ?? []);
$ziua   = ['duminică','luni','marți','miercuri','joi','vineri','sâmbătă'][(int) date('w', (int) $ieseLa)];

verifica('scrie ziua săptămânii', true,
    str_contains(mb_strtolower($z, 'UTF-8'), $ziua));
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

    $ia = static function (string $cale) use ($BAZA): string {
        $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
        return (string) @file_get_contents($BAZA . $cale, false, $ctx);
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

    $capulListei = '';
    if (preg_match('~<div class="section-head">(.*?)</div>\s*</div>~s', $goala, $m)) {
        $capulListei = $m[1];
    }
    verifica('și în capul listei nu s-a mutat niciun buton',
        false, str_contains($capulListei, 'class="btn'));

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
}

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
