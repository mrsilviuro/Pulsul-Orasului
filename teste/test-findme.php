<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — „FindMe": abțibildele cu coduri QR.
 *
 * Cere BAZA DE DATE. Partea de HTTP cere și SERVERUL, dar se sare singură dacă
 * nu i se dă o adresă.
 *
 * Cum se rulează:
 *     php teste/test-findme.php                        (fără HTTP)
 *     php teste/test-findme.php http://127.0.0.1:8099  (cu tot)
 *
 * Își face singur oamenii, categoria de joc, evenimentele și codurile, cu semne
 * care nu se pot încurca cu ale nimănui, și le șterge la sfârșit — și dacă pică
 * ceva la mijloc, prin curata() de la coadă.
 */

require_once __DIR__ . '/../inc/coduri-qr.php';
// Pentru cateCoduriQrGasite(), ușa prin care cere profilul cifra.
require_once __DIR__ . '/../inc/evaluari.php';

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

const SEMN   = 'test-findme-';
const PAROLA = 'ParolaDeProba#2026';
const SLUG_CAT = 'tst-findme-joc';

function curata(): void
{
    /**
     * Ordinea contează: codurile atârnă de evenimente ȘI de membri (cheile
     * `fk_qr_eveniment`, `fk_qr_gasit_de`, `fk_qr_creat_de`), iar ultimele două
     * sunt RESTRICT — adică nu lasă rândul din `membri` să plece până nu pleacă
     * ele. De aceea codurile se șterg primele, oricât ar părea de firesc
     * invers.
     */
    db()->prepare(
        'DELETE q FROM coduri_qr q JOIN membri m ON m.id = q.creat_de
          WHERE m.permalink LIKE ?'
    )->execute(['tstfm-%']);

    db()->prepare(
        'DELETE q FROM coduri_qr q JOIN membri m ON m.id = q.gasit_de
          WHERE m.permalink LIKE ?'
    )->execute(['tstfm-%']);

    db()->prepare('DELETE FROM evenimente WHERE slug LIKE ?')->execute(['tstfm-%']);
    db()->prepare('DELETE FROM membri WHERE permalink LIKE ?')->execute(['tstfm-%']);
    db()->prepare('DELETE FROM categorii WHERE slug = ?')->execute([SLUG_CAT]);
}

curata();
register_shutdown_function('curata');

function faMembru(string $cheie, string $prenume, bool $staff = false): int
{
    db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, sex, data_nasterii,
                             parola_hash, stare, este_staff, creat_la, confirmat_la)
         VALUES (?,?,?,?,"M","1990-01-01",?,"activ",?,?,?)'
    )->execute([
        substr('tstfm-' . $cheie, 0, 16), 'Ionescu', $prenume,
        SEMN . $cheie . '@invalid.local',
        password_hash(PAROLA, PASSWORD_DEFAULT), $staff ? 1 : 0, acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

/** Un eveniment de-a dreptul în bază, cu termenul cerut. */
function faEveniment(int $gazda, int $categorie, string $slug, string $cand,
                     string $stare = 'aprobat'): int
{
    $clipa = strtotime($cand);

    db()->prepare(
        'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, oras, locatie,
                                 descriere, data_eveniment, ora_inceput, stare_moderare,
                                 creat_la, actualizat_la)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $gazda, $categorie, 'Vânătoare de probă', $slug, oraseDisponibile()[0] ?? 'Roman',
        'Undeva prin oraș', str_repeat('Caută abțibildul prin oraș. ', 10),
        date('Y-m-d', $clipa), date('H:i:s', $clipa), $stare, acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

/* ==================================================================== */
sectiune('cum arată un cod');

verifica('cinci semne bune trec',        'K3M7P', curataCodQr('K3M7P'));
verifica('literele mici se ridică',     'K3M7P', curataCodQr('k3m7p'));
verifica('spațiile din jur se taie',    'K3M7P', curataCodQr("  K3M7P \n"));
verifica('patru semne, nu',                  '', curataCodQr('K3M7'));
verifica('șase semne, nu',                   '', curataCodQr('K3M7PQ'));
verifica('gol, nu',                          '', curataCodQr(''));
verifica('null, nu',                         '', curataCodQr(null));

/**
 * Semnele care se confundă lipsesc din alfabet DINADINS: pe abțibild, un „0"
 * citit ca „O" ar trimite omul la un cod care nu există.
 */
verifica('„O" nu e în alfabet',              '', curataCodQr('KOM7P'));
verifica('„0" nu e nici el',                 '', curataCodQr('K0M7P'));
verifica('„I" nu e',                         '', curataCodQr('KIM7P'));
verifica('„1" nu e',                         '', curataCodQr('K1M7P'));

verifica('semne străine, nu',                '', curataCodQr('K3M-P'));
verifica('diacriticele, nu',                 '', curataCodQr('K3MĂP'));
verifica('spațiu la mijloc, nu',             '', curataCodQr('K3 M7'));

// Nimic din adresă n-are voie să ajungă la bază așa cum a venit.
verifica('gunoi din adresă, nu',             '', curataCodQr('<script>'));
verifica('și nici o ghilimea',               '', curataCodQr("K3M'P"));

// Un cod făcut de noi trece mereu prin propria noastră verificare.
$totBun = true;
for ($i = 0; $i < 200; $i++) {
    if (curataCodQr(parolaTemporaraNoua(COD_QR_LUNGIME)) === '') { $totBun = false; break; }
}
verifica('orice cod făcut de noi e bun', true, $totBun);

/* ==================================================================== */
sectiune('categoria de joc');

$idCat = (int) (static function (): int {
    db()->prepare(
        'INSERT INTO categorii (nume, slug, ordine, doar_staff, joc_qr) VALUES (?,?,?,1,1)'
    )->execute(['Vânătoare de probă', SLUG_CAT, 97]);

    return (int) db()->lastInsertId();
})();

// Lista se ține minte în memorie de la o chemare la alta; categoria nouă
// trebuie citită din bază, nu din ce s-a adus mai devreme în test.
$slugCat = static fn(array $c): array =>
    array_map(static fn(array $x): string => (string) $x['slug'], $c);

verifica('e printre categoriile staff-ului', true,
    in_array(SLUG_CAT, $slugCat(categoriiEvenimente(true)), true));
verifica('id-ul ei e printre cele de joc', true, in_array($idCat, idCategoriiJocQr(), true));

/**
 * Cele două steaguri sunt DIFERITE și nu se confundă: `doar_staff` spune cine
 * poate publica, `joc_qr` spune ce fel de eveniment iese. O categorie de joc
 * deschisă tuturor ar trebui să rămână joc.
 */
db()->prepare('UPDATE categorii SET doar_staff = 0 WHERE slug = ?')->execute([SLUG_CAT]);
verifica('rămâne joc și fără „doar_staff"', true, in_array($idCat, idCategoriiJocQr(), true));
db()->prepare('UPDATE categorii SET doar_staff = 1 WHERE slug = ?')->execute([SLUG_CAT]);

$gazda = faMembru('gazda', 'Silviu', true);
$omul  = faMembru('omul',  'Andrei');
$altul = faMembru('altul', 'Maria');

$evJoc = faEveniment($gazda, $idCat, 'tstfm-joc', '+2 days');

/**
 * Steagul călătorește cu rândul evenimentului, ca esteJocQr() să poată fi
 * întrebat oriunde a ajuns rândul — fără încă o interogare.
 */
$randJoc = evenimentDupaSlug('tstfm-joc');
verifica('steagul vine cu rândul evenimentului', true, esteJocQr($randJoc));

$evObisnuit = faEveniment($gazda, 1, 'tstfm-obisnuit', '+2 days');
verifica('un eveniment obișnuit nu e joc', false, esteJocQr(evenimentDupaSlug('tstfm-obisnuit')));
verifica('și nici null nu e',               false, esteJocQr(null));

/* ==================================================================== */
sectiune('formularul cere codul');

$campuri = static fn(int $categorie, array $peste = []): array => $peste + [
    'titlu'            => 'Vânătoare prin centrul vechi',
    'categorie_id'     => (string) $categorie,
    'oras'             => oraseDisponibile()[0] ?? 'Roman',
    'locatie'          => 'Undeva prin oraș',
    'data_eveniment'   => date('d-m-Y', strtotime('+10 days')),
    'ora_inceput'      => '19:00',
    'fara_ora_sfarsit' => '1',
    'gratuit'          => '1',
    'varsta_minima'    => 'nespecificat',
    'gen_participanti' => 'nespecificat',
    'fara_participanti_min' => '1',
    'fara_participanti_max' => '1',
    'descriere'        => str_repeat('Povestea vânătorii de probă. ', 12),
];

$orase   = oraseDisponibile();
$idBune  = idCategoriiValide(true);
$jocuri  = idCategoriiJocQr();

$r = verificaEveniment($campuri($idCat), $idBune, $orase, null, null, $jocuri);
verifica('la o categorie de joc, codul e cerut', 'Scrie codul de pe abțibild.',
    $r['erori']['cod_qr'] ?? '');

$r = verificaEveniment($campuri($idCat, ['cod_qr' => 'K3M']), $idBune, $orase, null, null, $jocuri);
verifica('un cod strâmb e respins', true, isset($r['erori']['cod_qr']));

$r = verificaEveniment($campuri($idCat, ['cod_qr' => 'k3m7p']), $idBune, $orase, null, null, $jocuri);
verifica('un cod bun trece',            [], $r['erori']);
verifica('și intră cu majuscule', 'K3M7P', $r['curat']['cod_qr'] ?? '');

/**
 * La orice altă categorie, câmpul nici nu se citește: un cod rămas completat
 * din clipa în care omul s-a răzgândit nu e o eroare, dar nici nu se salvează.
 */
$r = verificaEveniment($campuri(1, ['cod_qr' => 'K3M7P']), $idBune, $orase, null, null, $jocuri);
verifica('la altă categorie nu se cere', [], $r['erori']);
verifica('și nici nu se salvează',    false, isset($r['curat']['cod_qr']));

// Fără lista de jocuri (cum o cheamă testele vechi), nu se cere nimic —
// altfel adăugarea unui argument nou ar fi stricat toate chemările de dinainte.
$r = verificaEveniment($campuri($idCat), $idBune, $orase);
verifica('fără lista de jocuri, nu se cere', [], $r['erori']);

/* ==================================================================== */
sectiune('formularul se subțiază');

/**
 * LA O VÂNĂTOARE, TREI ÎNTREBĂRI NU SE MAI PUN: ora de sfârșit, costul și
 * numărul de participanți. Nu e o îngăduință, e o potrivire cu adevărul —
 * acolo nu există „până la", nu se vinde nimic și nu se înscrie nimeni, iar
 * formularul nici nu mai desenează câmpurile (vezi `data-fara-joc` din
 * adauga_eveniment.php).
 *
 * Fără scutirile astea, anunțul ar fi fost oprit cu trei erori care arată spre
 * bife pe care omul nu le mai vede — cea mai rea formă de refuz.
 *
 * Aici se trimite un formular CIUNTIT, cum vine el de la un câmp stins: fără
 * `fara_ora_sfarsit`, fără `gratuit`, fără bifele de participanți.
 */
$ciuntit = static fn(int $categorie): array => [
    'titlu'          => 'Vânătoare prin centrul vechi',
    'categorie_id'   => (string) $categorie,
    'cod_qr'         => 'K3M7P',
    'oras'           => oraseDisponibile()[0] ?? 'Roman',
    'locatie'        => 'Undeva prin oraș',
    'data_eveniment' => date('d-m-Y', strtotime('+10 days')),
    'ora_inceput'    => '19:00',
    'gen_participanti' => 'nespecificat',
    'descriere'      => str_repeat('Povestea vânătorii de probă. ', 12),
];

$r = verificaEveniment($ciuntit($idCat), $idBune, $orase, null, null, $jocuri);
verifica('un formular fără cele trei câmpuri trece', [], $r['erori']);

// `??` n-ar merge aici: null e chiar răspunsul căutat, iar el l-ar citi ca
// „lipsește". Se întreabă de CHEIE, care trebuie să fie acolo și goală.
$goalaSiScrisa = static fn(string $cheie): bool =>
    array_key_exists($cheie, $r['curat']) && $r['curat'][$cheie] === null;

verifica('ora de sfârșit iese goală', true, $goalaSiScrisa('ora_sfarsit'));
verifica('costul, la fel',            true, $goalaSiScrisa('cost'));
verifica('și numărul minim',          true, $goalaSiScrisa('participanti_min'));
verifica('și cel maxim',              true, $goalaSiScrisa('participanti_max'));

/**
 * LA ORICE ALTĂ CATEGORIE, ACELEAȘI TREI SE CER MAI DEPARTE. Scutirea ține de
 * ce fel de eveniment e, nu de ce lipsește din cerere: altfel oricine ar fi
 * scăpat de reguli trimițând un formular ciuntit, iar noi am fi numit asta
 * „a ales să nu completeze".
 */
$r = verificaEveniment($ciuntit(1), $idBune, $orase, null, null, $jocuri);
verifica('la o categorie obișnuită, ora de sfârșit se cere', true,
    isset($r['erori']['ora_sfarsit']));
verifica('și costul',        true, isset($r['erori']['cost']));
verifica('și participanții', true, isset($r['erori']['participanti_min']));

/* ==================================================================== */
sectiune('facerea codurilor');

$cod1 = faCodQrNou($gazda);
$cod2 = faCodQrNou($gazda);

verifica('codul nou are 5 semne bune', $cod1, curataCodQr($cod1));
verifica('două coduri nu ies la fel',  true,  $cod1 !== $cod2);

$rand = codQrDupaCod($cod1);
verifica('se găsește după cod',           true, $rand !== null);
verifica('scris cu litere mici, tot el',  $cod1, (string) codQrDupaCod(mb_strtolower($cod1))['cod']);
verifica('un cod inventat nu se găsește', null, codQrDupaCod('ZZZZZ'));
verifica('gunoi în loc de cod, nici el',  null, codQrDupaCod('<script>'));

verifica('pornește nefolosit', 'nefolosit', stareaCoduluiQr($rand));
verifica('fără eveniment',           null, $rand['eveniment_id']);
verifica('fără câștigător',          null, $rand['gasit_de']);

/* ==================================================================== */
sectiune('legarea de eveniment');

verifica('un cod care nu există', 'necunoscut', legaCodulDeEveniment('ZZZZZ', $evJoc));
verifica('codul liber se leagă',       'gata', legaCodulDeEveniment($cod1, $evJoc));

$rand = codQrDupaCod($cod1);
verifica('acum e în joc', 'in_joc', stareaCoduluiQr($rand));
verifica('și știe de care eveniment', $evJoc, (int) $rand['eveniment_id']);
verifica('evenimentul își știe codul', $cod1, (string) codQrAlEvenimentului($evJoc)['cod']);

/**
 * Al doilea eveniment nu poate lua codul primului. Nu e o purtare frumoasă, e
 * cheia `uq_eveniment` plus `eveniment_id IS NULL` din WHERE: două publicări
 * venite în aceeași clipă ar fi trecut amândouă de orice verificare de
 * dinainte.
 */
$evAlDoilea = faEveniment($gazda, $idCat, 'tstfm-al-doilea', '+4 days');
verifica('alt eveniment nu i-l poate lua', 'ocupat', legaCodulDeEveniment($cod1, $evAlDoilea));
verifica('codul a rămas la primul', $evJoc, (int) codQrDupaCod($cod1)['eveniment_id']);

// A doua salvare a aceleiași editări nu e o greșeală.
verifica('același eveniment, iar: e în regulă', 'gata', legaCodulDeEveniment($cod1, $evJoc));

/* ---- verificarea de dinainte, cea care dă vorba din formular ---- */
verifica('de ce nu: cod necunoscut', 'necunoscut', deCeNuSePoateLega('ZZZZZ', null));
verifica('de ce nu: ocupat',             'ocupat', deCeNuSePoateLega($cod1, $evAlDoilea));
verifica('de ce nu: la un eveniment nou','ocupat', deCeNuSePoateLega($cod1, null));
verifica('la evenimentul lui, se poate',       '', deCeNuSePoateLega($cod1, $evJoc));
verifica('un cod liber, se poate',             '', deCeNuSePoateLega($cod2, null));

/* ---- dezlegarea, la editare ---- */
dezleagaCodurileEvenimentului($evJoc);
verifica('dezlegat, se întoarce la liber', 'nefolosit', stareaCoduluiQr(codQrDupaCod($cod1)));
verifica('și evenimentul rămâne fără cod',        null, codQrAlEvenimentului($evJoc));
verifica('acum îl poate lua altcineva',         'gata', legaCodulDeEveniment($cod1, $evAlDoilea));

// Înapoi la primul, pentru ce urmează.
dezleagaCodurileEvenimentului($evAlDoilea);
legaCodulDeEveniment($cod1, $evJoc);

/* ==================================================================== */
sectiune('de ce nu se poate revendica');

$membruOm = ['id' => $omul];

$liber = codQrDupaCod($cod2);
verifica('un cod fără eveniment', 'nepornit', deCeNuSePoateRevendica($liber, $membruOm));

$inJoc = codQrDupaCod($cod1);
verifica('nelogat, i se cere contul', 'nelogat', deCeNuSePoateRevendica($inJoc, null));
verifica('logat și la timp, se poate',       '', deCeNuSePoateRevendica($inJoc, $membruOm));

/* Un anunț care nu se vede pe site nu e o vânătoare. */
foreach (['in_asteptare', 'respins', 'anulat'] as $stare) {
    db()->prepare('UPDATE evenimente SET stare_moderare = ? WHERE id = ?')
        ->execute([$stare, $evJoc]);

    verifica('starea „' . $stare . '" → nepublic', 'nepublic',
        deCeNuSePoateRevendica(codQrDupaCod($cod1), $membruOm));
}

db()->prepare('UPDATE evenimente SET stare_moderare = "aprobat" WHERE id = ?')->execute([$evJoc]);

/**
 * Termenul. „Când o să aibă loc?" înseamnă, la o vânătoare, clipa în care se
 * ÎNCHIDE — ceasul e al PHP-ului, ca peste tot pe site.
 */
$evTrecut = faEveniment($gazda, $idCat, 'tstfm-trecut', '-3 hours');
$cod3     = faCodQrNou($gazda);
legaCodulDeEveniment($cod3, $evTrecut);

verifica('după termen, prea târziu', 'tarziu',
    deCeNuSePoateRevendica(codQrDupaCod($cod3), $membruOm));

// Chiar și cu un minut înainte, se mai poate.
$evLaLimita = faEveniment($gazda, $idCat, 'tstfm-limita', '+30 minutes');
$cod4       = faCodQrNou($gazda);
legaCodulDeEveniment($cod4, $evLaLimita);
verifica('cu o jumătate de oră înainte, se poate', '',
    deCeNuSePoateRevendica(codQrDupaCod($cod4), $membruOm));

/* ==================================================================== */
sectiune('câștigul');

$inJoc = codQrDupaCod($cod1);
verifica('primul care scanează câștigă', true, revendicaCodul($inJoc, $membruOm));

$dupa = codQrDupaCod($cod1);
verifica('codul e găsit',          'gasit', stareaCoduluiQr($dupa));
verifica('și știe de cine',         $omul,  (int) $dupa['gasit_de']);
verifica('cu ceasul pus',            true,  $dupa['gasit_la'] !== null);

/**
 * A DOUA SCHIMBARE, cea care merge împreună cu prima: evenimentul se încheie.
 * Dacă ar fi picat singură, ar fi rămas un anunț cu numărătoarea inversă
 * mergând peste un abțibild deja găsit.
 */
verifica('evenimentul s-a încheiat', 'incheiat',
    (string) evenimentDupaSlug('tstfm-joc')['stare_moderare']);

/**
 * Al doilea om nu mai poate câștiga. Nu prin verificarea de dinainte, ci prin
 * `gasit_de IS NULL` din WHERE: doi oameni care scanează în aceeași secundă
 * trec amândoi de orice întrebare pusă înainte.
 */
verifica('al doilea nu mai câștigă', false, revendicaCodul($inJoc, ['id' => $altul]));
verifica('câștigătorul a rămas primul', $omul, (int) codQrDupaCod($cod1)['gasit_de']);
verifica('de ce nu: luat', 'luat', deCeNuSePoateRevendica(codQrDupaCod($cod1), ['id' => $altul]));

// Un cod fără eveniment nu se poate câștiga nici prin scriere directă.
verifica('un cod nelegat nu se revendică', false, revendicaCodul(codQrDupaCod($cod2), $membruOm));

/* Un abțibild găsit nu se mai leagă de nimic și nu se mai dezleagă. */
verifica('găsit, nu se mai leagă de altul', 'gasit', legaCodulDeEveniment($cod1, $evAlDoilea));
dezleagaCodurileEvenimentului($evJoc);
verifica('și nici nu se dezleagă', $evJoc, (int) codQrDupaCod($cod1)['eveniment_id']);

/* ---- cifra de pe profil ---- */
verifica('câștigătorul are un cod găsit', 1, cateCoduriQrGasiteDe($omul));
verifica('ceilalți, niciunul',            0, cateCoduriQrGasiteDe($altul));
verifica('la fel și prin ușa din evaluari.php', 1, cateCoduriQrGasite($omul));

/* ==================================================================== */
sectiune('cum arată caseta');

/* În toi: numărătoare inversă, fără câștigător. */
$caseta = randeazaCasetaFindMe(evenimentDupaSlug('tstfm-limita'), codQrDupaCod($cod4));
verifica('în toi: e caseta de căutare', true, str_contains($caseta, 'findme--cautare'));
verifica('cu ceas de numărat',          true, str_contains($caseta, 'data-findme-timer='));
verifica('și cu clipa scrisă în litere', true, str_contains($caseta, '<time datetime='));
verifica('fără câștigător',            false, str_contains($caseta, 'findme__castigator'));

/**
 * CODUL NU SE SCRIE NICIODATĂ ÎN PAGINĂ. Dacă ar apărea acolo, cine deschide
 * anunțul ar putea câștiga fără să se ridice de pe scaun — adică exact opusul
 * jocului.
 */
verifica('codul nu ajunge în pagină', false, str_contains($caseta, $cod4));

/* Găsit: câștigătorul, cu poză și legătură spre profil. */
$caseta = randeazaCasetaFindMe(evenimentDupaSlug('tstfm-joc'), codQrDupaCod($cod1));
verifica('găsit: e caseta de câștig',  true, str_contains($caseta, 'findme--gasit'));
verifica('cu numele câștigătorului',   true, str_contains($caseta, 'Andrei'));
verifica('cu legătură spre profil',    true, str_contains($caseta, 'profil.php?m='));
verifica('fără ceas',                 false, str_contains($caseta, 'data-findme-timer='));
verifica('nici aici codul nu apare',  false, str_contains($caseta, $cod1));

/* Termen trecut, negăsit. */
$caseta = randeazaCasetaFindMe(evenimentDupaSlug('tstfm-trecut'), codQrDupaCod($cod3));
verifica('trecut: „nu l-a găsit nimeni"', true, str_contains($caseta, 'findme--trecut'));
verifica('fără ceas',                    false, str_contains($caseta, 'data-findme-timer='));

/* Fără cod legat, pagina nu se strică. */
$caseta = randeazaCasetaFindMe(evenimentDupaSlug('tstfm-limita'), null);
verifica('fără cod, tot se desenează ceva', true, str_contains($caseta, 'findme'));

/**
 * Contul anonimizat: câștigul rămâne scris în bază — de-aia cheia e RESTRICT —
 * dar pe ecran nu se mai poate arăta spre nimeni.
 */
db()->prepare('UPDATE membri SET stare = "sters" WHERE id = ?')->execute([$omul]);
$caseta = randeazaCasetaFindMe(evenimentDupaSlug('tstfm-joc'), codQrDupaCod($cod1));
verifica('cont anonimizat: fără legătură', false, str_contains($caseta, 'profil.php?m='));
verifica('dar tot spune că s-a găsit',      true, str_contains($caseta, 'findme--gasit'));
verifica('și nici numele nu se mai scrie', false, str_contains($caseta, 'Andrei'));

/**
 * ȘI FĂRĂ CHIP. Poza se lua mai departe din rând, oricât ar fi scris „Cineva"
 * deasupra ei. De obicei nu se vedea, fiindcă anonimizarea o șterge și de pe
 * disc, și din bază — dar atunci regula atârna de curățenia altcuiva.
 *
 * De aceea proba pune ANUME o poză înapoi pe rândul golit: fără ea, ar fi
 * trecut și codul de dinainte, care nu întreba nimic.
 */
db()->prepare('UPDATE membri SET poza = ? WHERE id = ?')->execute([str_repeat('b', 32), $omul]);
$caseta = randeazaCasetaFindMe(evenimentDupaSlug('tstfm-joc'), codQrDupaCod($cod1));
verifica('cont anonimizat: nici chipul',   false, str_contains($caseta, str_repeat('b', 32)));
verifica('ci silueta implicită',            true, str_contains($caseta, POZA_IMPLICITA));

db()->prepare('UPDATE membri SET poza = NULL, stare = "activ" WHERE id = ?')->execute([$omul]);

/* ==================================================================== */
sectiune('cifrele de pe cartonaș');

/**
 * LA O VÂNĂTOARE, PARTICIPANȚII NU SE SCRIU. Acolo nu se înscrie nimeni:
 * caseta de interes nici nu există pe pagina anunțului, deci lista e goală prin
 * însăși alcătuirea jocului. Un „0" lângă un omuleț ar fi spus „nu se duce
 * nimeni" despre singurul fel de eveniment la care nimeni n-are unde să se
 * ducă.
 *
 * Rândurile de mai jos sunt cum vin din bază (CIFRE_CARTONAS le aduce pe
 * amândouă, oricare ar fi evenimentul): hotărârea e a funcției de desenat.
 */
$cartonasObisnuit = [
    'cati_participanti' => 3,
    'cate_comentarii'   => 4,
    'participanti_max'  => 12,
    'categorie_joc_qr'  => 0,
];

$cifre = cifreleCartonasului($cartonasObisnuit);
verifica('la unul obișnuit, se scriu participanții', true, str_contains($cifre, '3 / 12'));
verifica('și comentariile',                          true, str_contains($cifre, 'comentarii'));
verifica('două cifre în total',                         2, substr_count($cifre, 'card__cifra'));

$cifre = cifreleCartonasului(['categorie_joc_qr' => 1] + $cartonasObisnuit);
verifica('la o vânătoare, participanții lipsesc', false, str_contains($cifre, '3 / 12'));
verifica('și omulețul odată cu ei',               false, str_contains($cifre, 'persoane participă'));
verifica('rămân comentariile',                     true, str_contains($cifre, 'comentarii'));
verifica('o singură cifră',                           1, substr_count($cifre, 'card__cifra'));

/* Fără limită, cifra e singură — dar tot nu se scrie la o vânătoare. */
$faraLimita = ['cati_participanti' => 7, 'cate_comentarii' => 0, 'categorie_joc_qr' => 0];
verifica('fără limită, doar cifra', true,
    str_contains(cifreleCartonasului($faraLimita), '>7</span>'));
verifica('la vânătoare, nici atât', 1,
    substr_count(cifreleCartonasului(['categorie_joc_qr' => 1] + $faraLimita), 'card__cifra'));

/**
 * Steagul călătorește cu rândul evenimentului, deci o listă adevărată îl are.
 * Dacă vreo cerere ar uita `c.joc_qr AS categorie_joc_qr`, cartonașul ar fi
 * arătat participanții la o vânătoare — de-aia se probează pe rândul venit din
 * bază, nu doar pe unul scris de mână.
 */
$dinBaza = evenimentDupaSlug('tstfm-joc');
verifica('rândul din bază poartă steagul', 1, (int) $dinBaza['categorie_joc_qr']);

/* ==================================================================== */
sectiune('lista staff-ului');

$lista = toateCodurileQr();
$aleNoastre = array_values(array_filter($lista,
    static fn(array $c): bool => in_array((string) $c['cod'], [$cod1, $cod2, $cod3, $cod4], true)));

verifica('toate patru sunt în listă', 4, count($aleNoastre));
verifica('cel mai nou e primul', true,
    (int) $lista[0]['id'] >= (int) $lista[count($lista) - 1]['id']);

/**
 * Lista se taie la CODURI_QR_PASTRATE. Dincolo de ele sunt abțibilde de acum
 * trei luni, demult dezlipite — un șir care curge o jumătate de metru în jos
 * nu e o listă, e un morman.
 */
$cateErau = count(toateCodurileQr(1000));

for ($i = 0; $i < CODURI_QR_PASTRATE + 5 - $cateErau; $i++) {
    faCodQrNou($gazda);
}

verifica('lista se taie la cincizeci', CODURI_QR_PASTRATE, count(toateCodurileQr()));
verifica('și se poate cere mai puțin',            3, count(toateCodurileQr(3)));

/* ==================================================================== */
sectiune('ștergerea unui cod');

$deSters = faCodQrNou($gazda);
$randul  = codQrDupaCod($deSters);

verifica('un cod liber se poate șterge', true, poateFiStersCodul($randul));
verifica('și chiar pleacă',              true, stergeCodulQr((int) $randul['id']));
verifica('după care nu mai există',      null, codQrDupaCod($deSters));
verifica('a doua oară nu mai are ce șterge', false, stergeCodulQr((int) $randul['id']));

/* Unul aflat în joc se poate șterge: vânătoarea rămâne fără abțibild, ceea ce
   e supărător, dar e treaba organizatorului — poate lega altul din „Editează". */
$inJocDeSters = faCodQrNou($gazda);
$evDeSters    = faEveniment($gazda, $idCat, 'tstfm-de-sters', '+6 days');
legaCodulDeEveniment($inJocDeSters, $evDeSters);

$randul = codQrDupaCod($inJocDeSters);
verifica('unul în joc, la fel', 'in_joc', stareaCoduluiQr($randul));
verifica('se poate șterge',        true, poateFiStersCodul($randul));
verifica('și pleacă',              true, stergeCodulQr((int) $randul['id']));
verifica('evenimentul rămâne',     true, evenimentDupaSlug('tstfm-de-sters') !== null);
verifica('doar fără abțibild',     null, codQrAlEvenimentului($evDeSters));

/**
 * UNUL GĂSIT, NU. Rândul acela nu mai e o unealtă, e istoria cuiva: de el
 * atârnă cifra „Coduri QR găsite" de pe profilul câștigătorului. Un om de casă
 * care face curat prin listă n-are de unde să știe că apăsând un „×" scade cu
 * unu ceva de pe pagina altcuiva.
 */
$gasitul = codQrDupaCod($cod1);
verifica('unul găsit nu se poate șterge', false, poateFiStersCodul($gasitul));
verifica('și nici prin funcția de scriere', false, stergeCodulQr((int) $gasitul['id']));
verifica('rândul e tot acolo', $cod1, (string) codQrDupaCod($cod1)['cod']);
verifica('și cifra de pe profil e neatinsă', 1, cateCoduriQrGasiteDe($omul));

/* ==================================================================== */
sectiune('bâjbâiala după un cod bun');

/**
 * Numărătoarea se face pe adresa IP, iar din linia de comandă nu există una:
 * PHP-CLI n-are REMOTE_ADDR. O punem noi, ca funcțiile să aibă după ce număra —
 * și o luăm de acolo la sfârșit, ca restul testelor să nu moștenească nimic.
 */
$ipDeProba = '203.0.113.77';   // gama rezervată probelor (RFC 5737)
$ipVechi   = $_SERVER['REMOTE_ADDR'] ?? null;
$_SERVER['REMOTE_ADDR'] = $ipDeProba;

$binar = ipBinar();
db()->prepare('DELETE FROM incercari_qr WHERE ip = ?')->execute([$binar]);

verifica('la început, nimeni n-a bâjbâit', false, preaMulteIncercariQr());

// Cu o scanare greșită mai puțin decât limita, ușa e tot deschisă.
for ($i = 1; $i < QR_INCERCARI_PE_CEAS; $i++) {
    insemneazaIncercareaQr();
}
verifica('cu una sub limită, tot se poate', false, preaMulteIncercariQr());

insemneazaIncercareaQr();
verifica('a treizecea închide ușa', true, preaMulteIncercariQr());

/**
 * Fereastra e de un ceas: ce s-a încercat acum două ceasuri nu mai spune nimic.
 * Mutăm rândurile în urmă și numărătoarea trebuie să pornească de la zero.
 */
db()->prepare('UPDATE incercari_qr SET creat_la = ? WHERE ip = ?')
    ->execute([acumMinus(QR_MINUTE_FEREASTRA * 2), $binar]);
verifica('după ce trece ceasul, se poate din nou', false, preaMulteIncercariQr());

/** Alt om, altă adresă: limita unuia nu-l încuie pe vecin. */
$_SERVER['REMOTE_ADDR'] = $ipDeProba;
db()->prepare('DELETE FROM incercari_qr WHERE ip = ?')->execute([$binar]);
for ($i = 0; $i < QR_INCERCARI_PE_CEAS; $i++) { insemneazaIncercareaQr(); }
verifica('adresa care a bâjbâit e oprită', true, preaMulteIncercariQr());

$_SERVER['REMOTE_ADDR'] = '203.0.113.78';
$binarVecin = ipBinar();
db()->prepare('DELETE FROM incercari_qr WHERE ip = ?')->execute([$binarVecin]);
verifica('vecinul de la altă adresă trece', false, preaMulteIncercariQr());

/**
 * Fără adresă deloc (linia de comandă, un server ciudat) nu se oprește nimeni:
 * n-avem după ce număra, iar a opri toată lumea ar fi mai rău decât a nu opri
 * pe nimeni.
 */
unset($_SERVER['REMOTE_ADDR']);
verifica('fără adresă, nu se oprește nimic', false, preaMulteIncercariQr());
$candNuENimic = (int) db()->query('SELECT COUNT(*) FROM incercari_qr')->fetchColumn();
insemneazaIncercareaQr();
verifica('și nici nu se scrie ceva în tabel', $candNuENimic,
    (int) db()->query('SELECT COUNT(*) FROM incercari_qr')->fetchColumn());

db()->prepare('DELETE FROM incercari_qr WHERE ip IN (?, ?)')->execute([$binar, $binarVecin]);

if ($ipVechi === null) { unset($_SERVER['REMOTE_ADDR']); }
else                   { $_SERVER['REMOTE_ADDR'] = $ipVechi; }

/* ==================================================================== */
sectiune('vânătoarea se închide la termen');

/**
 * O vânătoare nu ține o zi, ține până la o CLIPĂ ANUME — cea din „Când o să
 * aibă loc?", care la ea înseamnă ora în care se închide, nu ora la care se
 * strânge lumea. Un eveniment obișnuit se încheie singur când trece ziua, iar
 * asta se socotește la citire; o vânătoare al cărei termen a trecut la 18:00
 * rămânea „aprobat" până la miezul nopții, cu numărătoarea inversă la zero,
 * printre cele care urmează pe prima pagină și cu buton de încheiere.
 *
 * Acum se scrie în rând, ca la celălalt capăt al jocului: cine găsește
 * abțibildul încheie evenimentul (revendicaCodul), iar timpul scurs îl încheie
 * la fel.
 */
$stareaLui = static fn(int $id): string => (string) db()->query(
    'SELECT stare_moderare FROM evenimente WHERE id = ' . $id)->fetchColumn();

/**
 * ÎNTÂI SE FACE CURAT. Secțiunile de mai sus au lăsat în urmă vânători cu
 * termenul trecut, iar proba de aici numără câte închide o chemare — ar fi
 * numărat și pe ale lor. Se închid acum, ca de aici încolo să rămână numai ce
 * face secțiunea asta.
 */
incheieVanatorileTrecute();

/* Termenul a trecut acum două ceasuri. */
$evGata = faEveniment($gazda, $idCat, 'tstfm-gata', '-2 hours');

/* Termenul e peste două ceasuri — vânătoarea e în toi. */
$evInToi = faEveniment($gazda, $idCat, 'tstfm-intoi', '+2 hours');

/* Un eveniment OBIȘNUIT, început acum două ceasuri: nu-l atinge nimeni. */
$evObisnuitInceput = faEveniment($gazda, 1, 'tstfm-obisnuit-inceput', '-2 hours');

verifica('până se cheamă, toate trei sunt „aprobat"',
    ['aprobat', 'aprobat', 'aprobat'],
    [$stareaLui($evGata), $stareaLui($evInToi), $stareaLui($evObisnuitInceput)]);

$cate = incheieVanatorileTrecute();

verifica('vânătoarea cu termenul trecut s-a încheiat', 'incheiat', $stareaLui($evGata));
verifica('cea în toi rămâne cum era',                  'aprobat',  $stareaLui($evInToi));

/**
 * EVENIMENTUL OBIȘNUIT NU SE ATINGE, deși a început acum două ceasuri. El ține
 * ziua întreagă: la 20:00 e încă în toi, iar a-l încheia pentru că a trecut ora
 * de început ar însemna să-l stingi tocmai când se petrece. Steagul care le
 * desparte e `categorii.joc_qr`, niciodată numele sau slugul categoriei.
 */
verifica('cel obișnuit rămâne neatins', 'aprobat', $stareaLui($evObisnuitInceput));

/**
 * A DOUA CHEMARE NU MAI GĂSEȘTE NIMIC. Hotărârea e în `WHERE`
 * (`stare_moderare = 'aprobat'`), nu într-un `SELECT` de dinainte: două cereri
 * venite în aceeași clipă nu se calcă, iar prima pagină o cheamă la fiecare
 * încărcare.
 */
verifica('a găsit una singură', 1, $cate);
verifica('a doua oară, niciuna', 0, incheieVanatorileTrecute());

/**
 * UN ANUNȚ ANULAT NU SE ATINGE, oricât i-ar fi trecut termenul: a încetat deja,
 * altfel. Tot `WHERE`-ul îl ține deoparte.
 */
$evAnulat = faEveniment($gazda, $idCat, 'tstfm-gata-anulat', '-2 hours', 'anulat');
incheieVanatorileTrecute();
verifica('anulatul rămâne anulat', 'anulat', $stareaLui($evAnulat));

/* Nici cel care așteaptă moderarea: n-a fost niciodată o vânătoare pornită. */
$evAsteapta = faEveniment($gazda, $idCat, 'tstfm-gata-astept', '-2 hours', 'in_asteptare');
incheieVanatorileTrecute();
verifica('nici cel în așteptare', 'in_asteptare', $stareaLui($evAsteapta));

/**
 * ȚINTIT, cu id: pagina unui eveniment o închide pe a ei, fără să le mai
 * cerceteze pe toate. Aceeași regulă, același `WHERE` — doar o condiție în plus.
 */
$evUnu = faEveniment($gazda, $idCat, 'tstfm-tintit-unu', '-2 hours');
$evDoi = faEveniment($gazda, $idCat, 'tstfm-tintit-doi', '-2 hours');

verifica('chemată cu un id, închide una', 1, incheieVanatorileTrecute($evUnu));
verifica('și chiar pe aceea',      'incheiat', $stareaLui($evUnu));
verifica('cealaltă rămâne cum era', 'aprobat', $stareaLui($evDoi));

/**
 * ȘI ATUNCI PAGINA O VEDE ÎNCHEIATĂ. Proba de până aici se uită în bază; asta
 * întreabă ce ar spune site-ul despre rândul citit după aceea — fiindcă tot ce
 * se desenează pe pagina unui eveniment atârnă de întrebările astea două.
 */
$randInchis = evenimentDupaSlug('tstfm-tintit-unu');

verifica('și se citește ca încheiat',        true, evenimentIncheiat($randInchis));
verifica('deci fără buton de încheiere',    false, poateFiIncheiat($randInchis));
verifica('dar cu „Remake", ca orice sfârșit', true, poateFiRefacut($randInchis));

/* ==================================================================== */
if ($BAZA === '') {
    echo "\n(sar peste HTTP: dă adresa serverului ca argument, "
       . "ex. php teste/test-findme.php http://127.0.0.1:8099)\n";
} else {
    sectiune('pagina de scanare');

    /**
     * Proba de mai jos scanează dinadins coduri care nu există, iar fiecare
     * dintre ele se numără la frâna împotriva ghicitului. Rulată de câteva ori
     * la rând, proba și-ar fi pus singură lacătul — și ar fi picat pe ceva ce
     * funcționa. De aceea se pornește de la zero, pentru adresa de pe care vin
     * cererile.
     */
    $ipulProbei = @inet_pton('127.0.0.1');
    $uitaScanarile = static function () use ($ipulProbei): void {
        db()->prepare('DELETE FROM incercari_qr WHERE ip = ?')->execute([$ipulProbei]);
    };
    $uitaScanarile();

    /** Ce răspunde findme.php pentru o adresă, fără cont. */
    $ia = static function (string $adresa) use ($BAZA): string {
        $corp = @file_get_contents($BAZA . $adresa, false, stream_context_create([
            'http' => ['ignore_errors' => true, 'timeout' => 10],
        ]));

        return (string) $corp;
    };

    $pagina = $ia('/findme.php?qr=' . urlencode($cod2));
    verifica('cod fără eveniment → „n-a început"', true,
        str_contains($pagina, 'fm-card--nepornit'));

    $pagina = $ia('/findme.php?qr=ZZZZZ');
    verifica('cod inventat → necunoscut', true, str_contains($pagina, 'fm-card--necunoscut'));

    $pagina = $ia('/findme.php?qr=' . urlencode('<script>alert(1)</script>'));
    verifica('gunoi în adresă → necunoscut', true, str_contains($pagina, 'fm-card--necunoscut'));
    verifica('și nu se scrie în pagină ca atare', false,
        str_contains($pagina, '<script>alert(1)</script>'));

    $pagina = $ia('/findme.php');
    verifica('fără niciun cod → necunoscut', true, str_contains($pagina, 'fm-card--necunoscut'));

    $pagina = $ia('/findme.php?qr=' . urlencode($cod1));
    verifica('cod deja găsit → „al doilea"', true, str_contains($pagina, 'fm-card--luat'));

    /**
     * NELOGAT, la un cod bun: i se cere contul, dar NIMIC nu se scrie în bază.
     * Altfel primul robot care trece pe-acolo ar încheia vânătoarea.
     */
    $pagina = $ia('/findme.php?qr=' . urlencode($cod4));
    verifica('nelogat → i se cere contul', true, str_contains($pagina, 'fm-card--nelogat'));
    verifica('și nimeni n-a câștigat pe furiș', null, codQrDupaCod($cod4)['gasit_de']);
    verifica('evenimentul lui e tot deschis', 'aprobat',
        (string) evenimentDupaSlug('tstfm-limita')['stare_moderare']);

    /* ------------------ frâna, văzută din pagină ------------------ */

    /**
     * Se umple numărătoarea de-a dreptul în bază, nu cu treizeci de cereri:
     * proba ar fi durat un minut ca să arate același lucru.
     */
    $umple = db()->prepare('INSERT INTO incercari_qr (ip, creat_la) VALUES (?, ?)');
    for ($i = 0; $i < QR_INCERCARI_PE_CEAS; $i++) { $umple->execute([$ipulProbei, acum()]); }

    $pagina = $ia('/findme.php?qr=ZZZZZ');
    verifica('după prea multe încercări, pagina se oprește', true,
        str_contains($pagina, 'Hai să ne oprim puțin'));

    /**
     * ȘI LA UN COD BUN. Altfel, „codul ăsta trece, ăsta nu" ar fi fost chiar
     * răspunsul pe care îl caută cine ghicește — frâna ar fi devenit unealta
     * lui.
     */
    $pagina = $ia('/findme.php?qr=' . urlencode($cod4));
    verifica('nici măcar un cod bun nu mai spune nimic', true,
        str_contains($pagina, 'Hai să ne oprim puțin'));
    verifica('și abțibildul rămâne negăsit', null, codQrDupaCod($cod4)['gasit_de']);
    verifica('fără rugămintea de dezlipit', false, str_contains($pagina, 'fm-dezlipeste'));

    // Cu numărătoarea uitată, totul merge iar.
    $uitaScanarile();
    $pagina = $ia('/findme.php?qr=' . urlencode($cod4));
    verifica('după ce se uită, codul bun se vede iar', true,
        str_contains($pagina, 'fm-card--nelogat'));

    $uitaScanarile();

    sectiune('pagina codurilor');

    $pagina = $ia('/coduri.php');
    verifica('nelogat, nu vede lista', false, str_contains($pagina, 'tabel-coduri'));
    verifica('și niciun cod nu se scurge', false, str_contains($pagina, $cod2));

    // POST fără token: nu se face niciun cod.
    $inainte = count(toateCodurileQr(500));
    @file_get_contents($BAZA . '/coduri.php', false, stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => 'csrf=gresit',
            'ignore_errors' => true, 'timeout' => 10,
        ],
    ]));
    verifica('POST fără CSRF nu face niciun cod', $inainte, count(toateCodurileQr(500)));

    sectiune('ștergerea prin API');

    /** POST cu JSON către api/sterge-cod.php, fără cont. */
    $sterge = static function (array $date) use ($BAZA): array {
        $raw = @file_get_contents($BAZA . '/api/sterge-cod.php', false, stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => json_encode($date),
                'ignore_errors' => true, 'timeout' => 10,
            ],
        ]));

        $cod = 0;

        foreach ($http_response_header ?? [] as $rand) {
            if (preg_match('~^HTTP/\S+\s+(\d+)~', $rand, $m)) { $cod = (int) $m[1]; }
        }

        return ['cod' => $cod, 'corp' => json_decode((string) $raw, true)];
    };

    $codDeProba = faCodQrNou($gazda);

    $r = $sterge(['cod' => $codDeProba]);
    verifica('fără token CSRF, 419', 419, $r['cod']);
    verifica('și codul e tot acolo', $codDeProba, (string) codQrDupaCod($codDeProba)['cod']);

    // Nelogat, cu un token oarecare: se oprește tot înainte de faptă.
    $r = $sterge(['csrf' => 'oarecare', 'cod' => $codDeProba]);
    verifica('nelogat, nu șterge nimic', true, in_array($r['cod'], [401, 419], true));
    verifica('codul, iar, e tot acolo', $codDeProba, (string) codQrDupaCod($codDeProba)['cod']);

    stergeCodulQr((int) codQrDupaCod($codDeProba)['id']);

    sectiune('pagina evenimentului');

    $pagina = $ia('/eveniment/tstfm-limita');
    verifica('la o vânătoare, fără caseta de interes', false, str_contains($pagina, 'id="rsvp"'));
    verifica('în locul ei, caseta vânătorii',           true, str_contains($pagina, 'findme--cautare'));
    verifica('fără tabul „Participă"',                 false, str_contains($pagina, 'id="tab-going"'));
    verifica('fără tabul „Interesați"',                false, str_contains($pagina, 'id="tab-interested"'));
    verifica('fără panourile lor',                     false, str_contains($pagina, 'id="panel-going"'));
    verifica('dar cu comentarii',                       true, str_contains($pagina, 'id="panel-comments"'));
    verifica('și codul nu apare nicăieri',             false, str_contains($pagina, $cod4));

    // La un eveniment obișnuit, totul e la locul lui.
    $pagina = $ia('/eveniment/tstfm-obisnuit');
    verifica('la unul obișnuit, caseta de interes e acolo', true, str_contains($pagina, 'id="rsvp"'));
    verifica('și taburile la fel',                          true, str_contains($pagina, 'id="tab-going"'));
    verifica('fără nicio casetă de vânătoare',             false, str_contains($pagina, 'class="findme'));
}

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
