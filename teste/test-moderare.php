<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — aprobarea și respingerea anunțurilor, de către staff.
 *
 * Cere BAZA DE DATE. Partea de pagini și de API cere și SERVERUL, dar se sare
 * singură dacă nu i se dă o adresă.
 *
 * Cum se rulează:
 *     php teste/test-moderare.php                        (fără API)
 *     php teste/test-moderare.php http://127.0.0.1:8099  (cu tot)
 *
 * Își face singur oamenii și evenimentele de care are nevoie, cu nume care nu
 * se pot încurca cu ale nimănui, și le șterge la sfârșit — și dacă pică ceva la
 * mijloc, prin curata() de la coadă.
 */

require_once __DIR__ . '/../inc/evenimente.php';
require_once __DIR__ . '/../inc/interese.php';
require_once __DIR__ . '/../inc/email.php';

$BAZA = $argv[1] ?? '';

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

const SEMN   = 'test-moderare-';
const PAROLA = 'ParolaDeProba#2026';

function curata(): void
{
    db()->prepare('DELETE FROM evenimente WHERE slug LIKE ?')->execute(['tst-mod-%']);

    /**
     * Și după organizator, nu doar după slug.
     *
     * Anunțurile puse prin salveazaEveniment() își iau slugul din titlu, cu o
     * coadă întâmplătoare — deci nu încep cu „tst-mod-". Fără rândul ăsta ar fi
     * rămas în bază, iar ștergerea oamenilor de probă s-ar fi lovit de cheia
     * străină care leagă evenimentul de cel care l-a pus.
     */
    db()->prepare('DELETE e FROM evenimente e JOIN membri m ON m.id = e.membru_id
                    WHERE m.permalink LIKE ?')->execute(['tstmod-%']);

    db()->prepare('DELETE FROM membri WHERE email LIKE ?')->execute([SEMN . '%']);
    db()->prepare('DELETE FROM membri WHERE permalink LIKE ?')->execute(['tstmod-%']);
}

curata();
register_shutdown_function('curata');

function faMembru(string $cheie, bool $staff): int
{
    db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, sex, data_nasterii,
                             parola_hash, stare, este_staff, creat_la, confirmat_la)
         VALUES (?,?,?,?,\'M\',\'1990-01-01\',?,\'activ\',?,?,?)'
    )->execute([
        substr('tstmod-' . $cheie, 0, 16), 'Popa', 'Dan',
        SEMN . $cheie . '@invalid.local',
        password_hash(PAROLA, PASSWORD_DEFAULT),
        $staff ? 1 : 0, acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

$sef = faMembru('sef', true);
$org = faMembru('org', false);

function faEveniment(string $slug, int $organizator, string $stare): array
{
    db()->prepare(
        'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, descriere, oras,
                                 locatie, data_eveniment, ora_inceput, stare_moderare,
                                 creat_la, actualizat_la)
         VALUES (?, (SELECT MIN(id) FROM categorii), ?, ?, ?, ?, "Centru", ?, "18:00:00",
                 ?, ?, ?)'
    )->execute([
        $organizator, 'Proba ' . $slug, $slug, str_repeat('Text de probă. ', 20),
        oraseDisponibile()[0] ?? 'Roman', date('Y-m-d', strtotime('+8 days')),
        $stare, acum(), acum(),
    ]);

    return evenimentDupaSlug($slug);
}

/* ===================== 1. CE SE POATE MODERA ======================== */

sectiune('ce se poate modera');

foreach (['in_asteptare', 'aprobat', 'respins'] as $stare) {
    verifica('din „' . $stare . '" se poate', true,
        poateFiModerat(['stare_moderare' => $stare]));
}

foreach (['anulat', 'incheiat'] as $stare) {
    verifica('din „' . $stare . '" NU se poate', false,
        poateFiModerat(['stare_moderare' => $stare]));
}

verifica('nici dintr-o stare pe care n-o știm', false,
    poateFiModerat(['stare_moderare' => 'altceva']));
verifica('nici dintr-un rând fără stare', false, poateFiModerat([]));

verifica('se pot pune doar două stări', ['aprobat', 'respins'], STARI_DE_MODERAT);

/* ===================== 2. SCRIEREA ================================== */

sectiune('scrierea');

$ev = faEveniment('tst-mod-1', $org, 'in_asteptare');

moderezaEveniment($ev, 'aprobat');
verifica('aprobarea se scrie', 'aprobat',
    (string) evenimentDupaSlug('tst-mod-1')['stare_moderare']);

$ev = evenimentDupaSlug('tst-mod-1');
moderezaEveniment($ev, 'respins');
verifica('și răzgândirea, la fel', 'respins',
    (string) evenimentDupaSlug('tst-mod-1')['stare_moderare']);

/* Motivul anulării nu se atinge — nu e treaba moderării. */
db()->prepare('UPDATE evenimente SET motiv_anulare = ? WHERE slug = ?')
    ->execute(['un motiv oarecare', 'tst-mod-1']);
moderezaEveniment(evenimentDupaSlug('tst-mod-1'), 'aprobat');
verifica('motivul anulării rămâne neatins', 'un motiv oarecare',
    (string) evenimentDupaSlug('tst-mod-1')['motiv_anulare']);

/* ===================== 2b. MOTIVUL RESPINGERII ====================== */

sectiune('motivul respingerii');

verifica('lipsa lui nu e o eroare',    '', verificaMotivRespingere('')['eroare']);
verifica('nici a unui text de spații', '', verificaMotivRespingere('   ')['eroare']);
verifica('și rămâne gol',              '', verificaMotivRespingere('   ')['text']);
verifica('nici altceva decât text',    '', verificaMotivRespingere(null)['eroare']);

verifica('unul scurt trece', 'nu', verificaMotivRespingere('nu')['text']);
verifica('spațiile de la capete se taie', 'prea vag',
    verificaMotivRespingere('  prea vag  ')['text']);

$prea = str_repeat('ă', MOTIV_RESPINGERE_MAX + 1);
verifica('unul prea lung nu trece', true,
    str_starts_with(verificaMotivRespingere($prea)['eroare'], 'Motivul e prea lung'));
verifica('exact la limită trece', MOTIV_RESPINGERE_MAX,
    mb_strlen(verificaMotivRespingere(str_repeat('ă', MOTIV_RESPINGERE_MAX))['text'], 'UTF-8'));

/* ===================== 2c. CUM ARATĂ E-MAILUL ======================= */

sectiune('e-mailul către organizator');

global $config;

$logEmail = __DIR__ . '/../private/emailuri-trimise.log';

if (empty($config['dezvoltare'])) {
    echo "  (`dezvoltare` e oprit în config.php — partea asta s-a sărit)\n";
} else {
    $citeste = function (callable $ce) use ($logEmail): string {
        $inainte = is_file($logEmail) ? (int) filesize($logEmail) : 0;
        $ce();
        return substr((string) file_get_contents($logEmail), $inainte);
    };

    /**
     * Varianta de text simplu a e-mailului rupe rândurile pe la 70 de semne,
     * deci o propoziție lungă e tăiată în două de un rând nou. Pentru a o căuta
     * întreagă, spațiile albe se strâng într-unul singur.
     */
    $faraRupturi = static function (string $text): string {
        return (string) preg_replace('/\s+/u', ' ', $text);
    };

    /* --- aprobat --- */
    $nou = $citeste(function () {
        emailModerareAnunt(SEMN . 'org@invalid.local', 'Dan', 'Fotbal în parc',
            'https://exemplu.test/event.php?slug=x', 'aprobat');
    });

    verifica('la aprobare, subiectul o spune', true,
        str_contains($nou, 'Fotbal în parc” a fost aprobat'));
    verifica('și că se vede pe site', true, str_contains($nou, 'vizibil pe site'));
    verifica('fără vorbe despre motive', false, str_contains($nou, 'motiv'));

    /* --- respins CU motiv --- */
    $nou = $citeste(function () {
        emailModerareAnunt(SEMN . 'org@invalid.local', 'Dan', 'Fotbal în parc',
            'https://exemplu.test/event.php?slug=x', 'respins', 'Lipsește adresa exactă.');
    });

    verifica('la respingere, subiectul o spune', true,
        str_contains($faraRupturi($nou), 'nu a fost aprobat pentru publicare'));
    verifica('motivul scris ajunge întreg', true,
        str_contains($nou, 'Lipsește adresa exactă.'));
    verifica('și se spune al cui e', true, str_contains($nou, 'Motivul, așa cum a fost scris'));
    verifica('fără vorba pentru lipsa lui', false,
        str_contains($nou, 'Nu a fost adăugată o explicație'));
    verifica('cu îndemnul de a-l îndrepta', true,
        str_contains($faraRupturi($nou), 'edita și retrimite'));

    /* --- respins FĂRĂ motiv --- */
    $nou = $citeste(function () {
        emailModerareAnunt(SEMN . 'org@invalid.local', 'Dan', 'Fotbal în parc',
            'https://exemplu.test/event.php?slug=x', 'respins', '');
    });

    verifica('fără motiv, se spune pe față', true,
        str_contains($faraRupturi($nou), 'Nu a fost adăugată o explicație detaliată'));
    verifica('și nu se pretinde că ar fi unul', false,
        str_contains($nou, 'Uite ce s-a scris'));

    /* --- editare necesară: nici „da", nici „nu" --- */
    $nou = $citeste(function () {
        emailModerareAnunt(SEMN . 'org@invalid.local', 'Dan', 'Fotbal în parc',
            'https://exemplu.test/event.php?slug=x', 'editare', 'Lipsește ora de început.');
    });

    verifica('la editare, subiectul cere schimbări', true,
        str_contains($nou, 'are nevoie de câteva ajustări'));
    verifica('și spune că NU e respins', true,
        str_contains($faraRupturi($nou), 'aproape de publicare'));
    verifica('cu ce trebuie schimbat', true, str_contains($nou, 'Lipsește ora de început.'));
    verifica('și cu îndemnul de a-l trimite din nou', true,
        str_contains($faraRupturi($nou), 'retrimite-l'));
    verifica('fără vorba „nu a fost aprobat"', false,
        str_contains($nou, 'nu a fost aprobat'));

    /* Și fără motiv, tot aceeași vorbă pentru lipsa lui. */
    $nou = $citeste(function () {
        emailModerareAnunt(SEMN . 'org@invalid.local', 'Dan', 'Fotbal în parc',
            'https://exemplu.test/event.php?slug=x', 'editare', '');
    });
    verifica('la editare fără motiv, se spune la fel', true,
        str_contains($faraRupturi($nou), 'Nu s-a menționat un motiv anume'));

    /* Motivul e text de la om: scăpat în HTML, ca orice paragraf. */
    $sablon = sablonEmail('Test', [
        'paragrafe' => ['Motiv cu <script>alert(1)</script> în el.'],
    ]);
    verifica('motivul nu poate strecura etichete', false,
        str_contains($sablon['html'], '<script>alert(1)</script>'));
}

/* ============ 2d. GOLIREA DATELOR LA RESPINGERE ===================== */

sectiune('golirea datelor');

require_once __DIR__ . '/../inc/comentarii.php';

$ala  = faMembru('ala', false);
$evGol = faEveniment('tst-mod-gol', $org, 'aprobat');
$idGol = (int) $evGol['id'];

/* Se strânge de toate în jurul lui. */
faOrganizatorulParticipant($idGol, $org);
salveazaInteres($idGol, $ala, 'participant');

$idCom = salveazaComentariu($idGol, $ala, 'Un comentariu de probă.');
comutaApreciere($idCom, $org);

db()->prepare('INSERT INTO evaluari (eveniment_id, evaluat_id, evaluator_id, stele, creat_la, actualizat_la)
               VALUES (?,?,?,?,?,?)')->execute([$idGol, $ala, $org, 5, acum(), acum()]);

db()->prepare('INSERT INTO excluderi_evenimente (eveniment_id, membru_id, exclus_de_id, rol, motiv, interzis, creat_la)
               VALUES (?,?,?,\'organizator\',?,1,?)')
    ->execute([$idGol, $ala, $org, 'un motiv de probă', acum()]);

$cate = function (string $tabel, int $id): int {
    $q = db()->prepare('SELECT COUNT(*) FROM ' . $tabel . ' WHERE eveniment_id = ?');
    $q->execute([$id]);
    return (int) $q->fetchColumn();
};

$cateAprecieri = function (int $comentariuId): int {
    $q = db()->prepare('SELECT COUNT(*) FROM comentarii_aprecieri WHERE comentariu_id = ?');
    $q->execute([$comentariuId]);
    return (int) $q->fetchColumn();
};

verifica('avem interese',   2, $cate('interese_evenimente', $idGol));
verifica('avem comentarii', 1, $cate('comentarii', $idGol));
verifica('avem aprecieri',  1, $cateAprecieri($idCom));
verifica('avem evaluări',   1, $cate('evaluari', $idGol));
verifica('avem excluderi',  1, $cate('excluderi_evenimente', $idGol));

$sters = golesteDateleEvenimentului($idGol);

verifica('interesele s-au dus',   0, $cate('interese_evenimente', $idGol));
verifica('comentariile s-au dus', 0, $cate('comentarii', $idGol));
verifica('și aprecierile, în cascadă', 0, $cateAprecieri($idCom));
verifica('evaluările s-au dus',   0, $cate('evaluari', $idGol));
verifica('excluderile s-au dus',  0, $cate('excluderi_evenimente', $idGol));

verifica('se spune câte au plecat', 2, $sters['interese'] ?? -1);
verifica('și câte comentarii',      1, $sters['comentarii'] ?? -1);

/* Rândul evenimentului RĂMÂNE: e al organizatorului, ca să-l poată îndrepta. */
verifica('dar anunțul rămâne în bază', true, evenimentDupaSlug('tst-mod-gol') !== null);

/* Pe un eveniment fără nimic în jur, golirea nu e o eroare. */
$sters = golesteDateleEvenimentului($idGol);
verifica('a doua golire nu strică nimic', 0, $sters['interese'] ?? -1);

/* ============ 3. STAFF-UL PUBLICĂ DIRECT, ȘI POATE ȚINE DEOPARTE ===== */

sectiune('publicarea de către staff');

/* --- cele două întrebări, singure --- */

verifica('un om obișnuit trimite spre aprobare', 'in_asteptare', starePentruPublicare(false));
verifica('omul de casă publică de-a dreptul',   'aprobat',      starePentruPublicare(true));

verifica('bifa de la staff se ascultă', true,
    ascundePeProfil(['ascuns_pe_profil' => '1'], true));
verifica('nebifată înseamnă nu', false, ascundePeProfil([], true));
verifica('un „0" trimis de mână, la fel', false,
    ascundePeProfil(['ascuns_pe_profil' => '0'], true));

/**
 * Cel mai important rând din secțiunea asta: bifa nu există pentru cine nu e
 * staff, oricât ar scrie în cererea trimisă. Caseta nici nu se desenează în
 * formularul lui, deci un „1" venit de acolo e scris de mână.
 */
verifica('dar de la oricine altcineva, NU', false,
    ascundePeProfil(['ascuns_pe_profil' => '1'], false));

/* --- ce ajunge în bază --- */

$campuri = static function (string $titlu): array {
    return [
        'categorie_id'     => (int) db()->query('SELECT MIN(id) FROM categorii')->fetchColumn(),
        'titlu'            => $titlu,
        'data_eveniment'   => date('Y-m-d', strtotime('+9 days')),
        'ora_inceput'      => '18:00:00',
        'ora_sfarsit'      => null,
        'oras'             => oraseDisponibile()[0] ?? 'Roman',
        'locatie'          => 'Centru',
        'cost'             => null,
        'varsta_minima'    => null,
        'participanti_min' => null,
        'participanti_max' => null,
        'descriere'        => str_repeat('Text de probă. ', 25),
        'gen_participanti' => 'nespecificat',
    ];
};

// Limita de evenimente active nu ne stă în drum: aici se verifică starea și
// bifa, nu numărătoarea, iar salveazaEveniment() n-o întreabă oricum.
$slugSef = salveazaEveniment($sef, $campuri('Anunțul orașului'), null, true, true);
$slugOm  = salveazaEveniment($org, $campuri('Ieșirea unui om'),  null, false, false);

$alSefului = evenimentDupaSlug($slugSef);
$alOmului  = evenimentDupaSlug($slugOm);

verifica('anunțul staff-ului intră aprobat',    'aprobat',      $alSefului['stare_moderare']);
verifica('al unui om obișnuit, în așteptare',   'in_asteptare', $alOmului['stare_moderare']);
verifica('și e însemnat ca ținut deoparte',      1, (int) $alSefului['ascuns_pe_profil']);
verifica('celălalt, nu',                         0, (int) $alOmului['ascuns_pe_profil']);

/* --- organizatorul, pe lista de participanți --- */

verifica('la un anunț obișnuit, organizatorul se trece singur', true,
    organizatorulVineSingur(false));
verifica('la unul ținut deoparte, NU',           false, organizatorulVineSingur(true));

/**
 * Nu e o vorbă frumoasă, se vede în bază: la anunțul orașului nu s-a scris
 * niciun rând în `interese_evenimente`, deci omul de casă nu apare printre
 * chipurile de sub „Cine vine" și nu umflă numărul cu unu.
 */
verifica('și chiar nu e pe listă', null,
    interesulMeu((int) $alSefului['id'], $sef));
verifica('dar la anunțul obișnuit e', 'participant',
    interesulMeu((int) $alOmului['id'], $org));

// Se poate înscrie oricând singur, ca oricine altcineva — bifa nu-i închide ușa.
salveazaInteres((int) $alSefului['id'], $sef, 'participant');
verifica('se poate înscrie și el, dacă chiar se duce', 'participant',
    interesulMeu((int) $alSefului['id'], $sef));

// Îl scoatem la loc, ca socotelile de mai jos să rămână cele dinainte.
db()->prepare('DELETE FROM interese_evenimente WHERE eveniment_id = ? AND membru_id = ?')
    ->execute([(int) $alSefului['id'], $sef]);

/* --- limita de evenimente active --- */

/**
 * Amândoi au deja anunțuri active din rândurile de mai sus, iar limita se pune
 * pe 1 ca să fie limpede că e atinsă. Omul obișnuit se oprește în ea; omul de
 * casă trece — el publică tocmai zece anunțuri ale orașului, iar o limită
 * gândită împotriva celui care ar umple prima pagină n-are ce căuta în calea
 * lui.
 */
db()->prepare('UPDATE membri SET limita_evenimente_active = 1 WHERE permalink LIKE ?')
    ->execute(['tstmod-%']);

$voieOm  = poatePublicaEveniment($org, false);
$voieSef = poatePublicaEveniment($sef, true);

verifica('omul obișnuit se lovește de limită', false, $voieOm['poate']);
verifica('cu un mesaj care o spune',            true,
    str_contains($voieOm['mesaj'], 'eveniment activ'));
verifica('omul de casă trece peste ea',         true,  $voieSef['poate']);
verifica('și fără niciun mesaj de oprire',      '',    $voieSef['mesaj']);

/**
 * Lista celor active se citește și pentru el: e ce arată pagina sub formular
 * („ai deja pe astea"), și n-are de ce să dispară doar fiindcă nu-l mai oprește.
 */
verifica('dar tot își vede anunțurile active', true, $voieSef['active'] !== []);

// Fără steagul de staff, aceeași limită îl oprește și pe el — trece OMUL, nu
// contul: regula se citește la fiecare cerere, din baza de date.
verifica('același cont, fără steag, e oprit', false,
    poatePublicaEveniment($sef, false)['poate']);

/* --- ce se vede pe profil --- */

$slugurile = static function (array $lista): array {
    return array_map(static fn (array $e): string => (string) $e['slug'], $lista);
};

// Un al doilea anunț al omului de casă, de data asta la vedere: fără el n-am
// ști dacă lipsa celuilalt vine din bifă sau din faptul că e al staff-ului.
$slugLaVedere = salveazaEveniment($sef, $campuri('Ieșirea mea, a omului'), null, true, false);

$peProfil = $slugurile(evenimenteDePeProfil($sef, true));

verifica('ce e ținut deoparte lipsește de pe profil', false, in_array($slugSef, $peProfil, true));
verifica('dar celălalt e acolo',                      true,  in_array($slugLaVedere, $peProfil, true));

/**
 * Și pentru EL. Dacă i s-ar arăta doar lui, ar crede de fiecare dată că bifa
 * n-a mers — iar profilul lui n-ar arăta la fel pentru el și pentru lume.
 */
verifica('lipsește și când își vede propriul profil', false,
    in_array($slugSef, $slugurile(evenimenteDePeProfil($sef, false)), true));

verifica('nici cifra de deasupra nu-l numără', 1, cateEvenimenteOrganizate($sef));

/* --- istoricul: al lui nu, al celorlalți da --- */

require_once __DIR__ . '/../inc/evaluari.php';

$vizitator = faMembru('viz', false);

// Amândouă anunțurile se mută în trecut și se încheie, ca să intre în istoric.
foreach ([$slugSef, $slugLaVedere] as $s) {
    $id = (int) evenimentDupaSlug($s)['id'];
    db()->prepare('UPDATE evenimente SET data_eveniment = ?, stare_moderare = \'incheiat\'
                    WHERE id = ?')->execute([date('Y-m-d', strtotime('-3 days')), $id]);
    salveazaInteres($id, $vizitator, 'participant');
}

$istoricSef = $slugurile(istoricEvenimente($sef));
$istoricViz = $slugurile(istoricEvenimente($vizitator));

/**
 * CÂND NU S-A DUS, NU APARE — și asta e situația obișnuită la un anunț al
 * orașului: omul de casă îl scrie pe site, atât. Nu e trecut singur pe lista
 * de participanți (organizatorulVineSingur), deci n-are cum să intre în
 * istoric. Rândul din `interese_evenimente` a fost scos mai sus, dinadins.
 */
verifica('nedus, nu-i apare în istoric',        false, in_array($slugSef, $istoricSef, true));
verifica('cel la vedere rămâne în istoricul lui', true, in_array($slugLaVedere, $istoricSef, true));

/**
 * Pentru cine a FOST acolo, seara aceea a existat. Bifa spune „nu e o ieșire
 * de-a MEA", nu „ștergeți evenimentul din viețile tuturor".
 */
verifica('dar în al participantului e la locul lui', true,
    in_array($slugSef, $istoricViz, true));

verifica('și cifra lui îl numără', 2, laCateEvenimenteAFost($vizitator));
verifica('a celui care nu s-a dus, nu',  1, laCateEvenimenteAFost($sef));

/* --- DAR DACĂ SE DUCE, INTRĂ CA ORICARE ALTUL --- */

/**
 * De obicei anunțurile astea sunt ale primăriei sau ale altcuiva, iar omul de
 * casă doar le scrie pe site. Uneori însă se duce și el — și atunci apasă
 * „Particip", ca oricare altul.
 *
 * Din clipa aceea, seara aceea face parte din ce i s-a întâmplat: intră în
 * istoric și se numără la „Prezent la activități". LIPSEA DE ACOLO, și ieșea o
 * pagină care se contrazicea singură — pe lista de participanți a
 * evenimentului scria numele lui, iar în istoricul lui seara aceea nu existase
 * niciodată.
 *
 * CE RĂMÂNE DIN BIFĂ: anunțul nu urcă la „Ieșiri organizate" și nu i se scrie
 * „Organizator" pe cartonaș. N-a pus el la cale nimic; a fost acolo.
 */
salveazaInteres((int) evenimentDupaSlug($slugSef)['id'], $sef, 'participant');

$istoricSef = istoricEvenimente($sef);
$dupaSlug   = [];

foreach ($istoricSef as $e) { $dupaSlug[(string) $e['slug']] = $e; }

verifica('dus la anunțul orașului, îi apare în istoric', true,
    isset($dupaSlug[$slugSef]));
verifica('dar FĂRĂ însemnul „Organizator"', 0,
    (int) ($dupaSlug[$slugSef]['e_organizator'] ?? -1));
verifica('pe al lui, în schimb, scrie', 1,
    (int) ($dupaSlug[$slugLaVedere]['e_organizator'] ?? -1));

/* Și cifra de deasupra se mișcă odată cu lista: două cartonașe, doi. */
verifica('cifra numără acum două', 2, laCateEvenimenteAFost($sef));
verifica('adică fix câte cartonașe sunt', count($istoricSef),
    laCateEvenimenteAFost($sef));

/**
 * IAR „IEȘIRI ORGANIZATE" NU SE CLINTEȘTE. Acolo e rostul bifei, și acolo
 * rămâne: anunțul orașului n-are ce căuta printre ieșirile puse la cale de el,
 * oricâte ori s-ar duce la ele.
 */
verifica('„Ieșiri organizate" rămâne cum era', 1, cateEvenimenteOrganizate($sef));
verifica('și lista de acolo, la fel', false,
    in_array($slugSef, $slugurile(evenimenteDePeProfil($sef, true)), true));

/* Cartonașul desenat nu poartă insigna — proba se uită la ce ajunge pe ecran. */
$htmlIstoric = randeazaIstoric([$dupaSlug[$slugSef]]);
verifica('nici pe cartonașul desenat nu scrie', false,
    str_contains($htmlIstoric, 'Organizator'));

/* ===================== 4. PRIN SERVER =============================== */

if ($BAZA === '') {
    echo "\n(partea de API s-a sărit — dă o adresă ca s-o rulezi:"
       . " php teste/test-moderare.php http://127.0.0.1:8099)\n";
} else {
    sectiune('prin server');

    function cere(string $cale, ?array $trup = null, string $cookie = ''): array
    {
        global $BAZA;

        $ctx = [
            'http' => [
                'method'        => $trup === null ? 'GET' : 'POST',
                'header'        => "Content-Type: application/json\r\n"
                                 . ($cookie !== '' ? "Cookie: $cookie\r\n" : ''),
                'content'       => $trup === null ? '' : json_encode($trup),
                'ignore_errors' => true,
            ],
        ];

        $raspuns = @file_get_contents($BAZA . $cale, false, stream_context_create($ctx));
        $cod = 0; $cookieNou = $cookie;

        foreach ($http_response_header ?? [] as $rand) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $rand, $m) === 1) { $cod = (int) $m[1]; }
            if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $rand, $m) === 1) { $cookieNou = $m[1]; }
        }

        return ['cod' => $cod, 'corp' => (string) $raspuns, 'cookie' => $cookieNou];
    }

    /** Intră în cont și întoarce cookie-ul plus tokenul de pe pagina cerută. */
    function intra(string $email, string $pagina): array
    {
        $p = cere('/login.php');
        $cookie = $p['cookie'];
        preg_match('/name="csrf" value="([^"]+)"/', $p['corp'], $m);

        $r = cere('/api/autentificare.php', [
            'csrf' => $m[1] ?? '', 'email' => $email, 'parola' => PAROLA,
        ], $cookie);

        $corp = json_decode($r['corp'], true) ?: [];

        if (($corp['ok'] ?? false) !== true) {
            return ['cookie' => '', 'token' => '', 'corp' => ''];
        }

        $cookie = $r['cookie'];
        $pag = cere($pagina, null, $cookie);

        $token = '';
        if (preg_match('/data-csrf="([^"]+)"/', $pag['corp'], $m) === 1
            || preg_match('/name="csrf" value="([^"]+)"/', $pag['corp'], $m) === 1) {
            $token = $m[1];
        }

        return ['cookie' => $cookie, 'token' => $token, 'corp' => $pag['corp']];
    }

    /* Un anunț proaspăt, în așteptare. */
    faEveniment('tst-mod-2', $org, 'in_asteptare');
    $adresa = '/eveniment/tst-mod-2';

    /* --- ce vede fiecare în PAGINĂ --- */

    $caOrg = intra(SEMN . 'org@invalid.local', $adresa);

    if ($caOrg['cookie'] === '') {
        echo "  (intrarea în cont n-a mers — restul s-a sărit)\n";
    } else {
        verifica('organizatorul își vede anunțul', true,
            str_contains($caOrg['corp'], 'Proba tst-mod-2'));
        verifica('dar NU vede blocul de moderare', false,
            str_contains($caOrg['corp'], 'data-moderare'));
        verifica('nici butonul de aprobat', false,
            str_contains($caOrg['corp'], 'data-modereaza="aprobat"'));
        verifica('nici pe cel de respins', false,
            str_contains($caOrg['corp'], 'data-modereaza="respins"'));

        $caSef = intra(SEMN . 'sef@invalid.local', $adresa);

        verifica('omul de casă vede blocul', true,
            str_contains($caSef['corp'], 'data-moderare'));
        verifica('cu butonul de aprobat', true,
            str_contains($caSef['corp'], 'data-modereaza="aprobat"'));
        verifica('și cu cel de respins', true,
            str_contains($caSef['corp'], 'data-modereaza="respins"'));

        /* --- cine NU e staff nu poate, nici dacă cere de-a dreptul --- */

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caOrg['token'], 'slug' => 'tst-mod-2', 'stare' => 'aprobat',
        ], $caOrg['cookie']);

        verifica('cererea de la cine nu e staff e refuzată', 403, $r['cod']);
        verifica('și anunțul rămâne neatins', 'in_asteptare',
            (string) evenimentDupaSlug('tst-mod-2')['stare_moderare']);

        /* Nici fără cont. */
        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => 'orice', 'slug' => 'tst-mod-2', 'stare' => 'aprobat',
        ]);
        verifica('fără cont, nici atât', true, in_array($r['cod'], [401, 419], true), (string) $r['cod']);

        /* --- omul de casă poate --- */

        $inainteDeAprobare = is_file($logEmail) ? (int) filesize($logEmail) : 0;

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-2', 'stare' => 'aprobat',
        ], $caSef['cookie']);

        $corp = json_decode($r['corp'], true) ?: [];

        verifica('staff-ul aprobă',            true,      $corp['ok'] ?? false);
        verifica('și starea chiar s-a scris',  'aprobat',
            (string) evenimentDupaSlug('tst-mod-2')['stare_moderare']);
        verifica('cu întoarcerea pe pagină',   true,
            str_contains((string) ($corp['redirect'] ?? ''), 'tst-mod-2'));

        /* A doua oară, aceeași stare: nu e o eroare adevărată, dar se spune. */
        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-2', 'stare' => 'aprobat',
        ], $caSef['cookie']);
        verifica('aprobat de două ori nu se poate', 409, $r['cod']);

        verifica('și organizatorul a fost înștiințat', true,
            $corp['instiintat'] ?? false);

        if (!empty($config['dezvoltare'])) {
            $nou = substr((string) file_get_contents($logEmail), $inainteDeAprobare);
            verifica('i-a plecat mesajul de aprobare', true,
                str_contains($nou, SEMN . 'org@invalid.local'));
            verifica('cu vestea bună', true, str_contains($nou, 'a fost aprobat'));
        }

        /* ==================== PIUNEZA, TOT A CASEI ==================== */

        /**
         * `tst-mod-2` e acum aprobat, deci se poate fixa. Piuneza e a doua
         * unealtă de pe pagina evenimentului care nu întreabă „e al tău?", ci
         * „ești de-al casei?" — la fel ca moderarea de mai sus, și se păzește
         * exact la fel.
         */
        $r = cere('/api/fixeaza-eveniment.php', [
            'csrf' => $caOrg['token'], 'slug' => 'tst-mod-2', 'fixat' => '1',
        ], $caOrg['cookie']);

        verifica('organizatorul nu-și poate fixa anunțul', 403, $r['cod']);
        verifica('și piuneza nu s-a pus', null,
            evenimentDupaSlug('tst-mod-2')['fixat_la']);

        $r = cere('/api/fixeaza-eveniment.php', [
            'csrf' => 'orice', 'slug' => 'tst-mod-2', 'fixat' => '1',
        ]);
        verifica('fără cont, nici atât', true, in_array($r['cod'], [401, 419], true),
            (string) $r['cod']);

        /* Omul de casă poate. */
        $r = cere('/api/fixeaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-2', 'fixat' => '1',
        ], $caSef['cookie']);

        $corpFix = json_decode($r['corp'], true) ?: [];
        verifica('staff-ul fixează', true, $corpFix['ok'] ?? false);
        verifica('și răspunde cu starea nouă', true, $corpFix['fixat'] ?? false);
        verifica('ștampila chiar s-a scris', true,
            evenimentDupaSlug('tst-mod-2')['fixat_la'] !== null);

        /* Și o ia înapoi, cu aceeași cerere. */
        $r = cere('/api/fixeaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-2', 'fixat' => '',
        ], $caSef['cookie']);

        verifica('și o ia înapoi', false, (json_decode($r['corp'], true) ?: [])['fixat'] ?? true);
        verifica('ștampila s-a șters', null, evenimentDupaSlug('tst-mod-2')['fixat_la']);

        /**
         * Un anunț care încă așteaptă nu se fixează: n-are unde să stea primul,
         * fiindcă nu e pe prima pagină deloc. Se face unul anume pentru proba
         * asta — celelalte au trecut deja prin moderare mai sus, iar un test
         * care se bizuie pe starea lăsată de altul se strică la prima
         * rearanjare.
         */
        faEveniment('tst-mod-fix-astept', $org, 'in_asteptare');

        $r = cere('/api/fixeaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-fix-astept', 'fixat' => '1',
        ], $caSef['cookie']);
        verifica('unul în așteptare nu se fixează', 409, $r['cod']);
        verifica('și rămâne nefixat', null,
            evenimentDupaSlug('tst-mod-fix-astept')['fixat_la']);

        /* Respingerea unui anunț aprobat, cu motiv: răzgândirea merge. */
        $inainte = is_file($logEmail) ? (int) filesize($logEmail) : 0;

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf'  => $caSef['token'], 'slug' => 'tst-mod-2', 'stare' => 'respins',
            'motiv' => 'Lipsește ora de început.',
        ], $caSef['cookie']);
        verifica('răzgândirea merge', true, (json_decode($r['corp'], true) ?: [])['ok'] ?? false);
        verifica('și se scrie',       'respins',
            (string) evenimentDupaSlug('tst-mod-2')['stare_moderare']);

        if (!empty($config['dezvoltare'])) {
            $nou = substr((string) file_get_contents($logEmail), $inainte);
            verifica('motivul ajunge în e-mail', true,
                str_contains($nou, 'Lipsește ora de început.'));
        }

        /* Un motiv prea lung nu trece, și atunci nu se schimbă nimic. */
        db()->prepare('UPDATE evenimente SET stare_moderare = ? WHERE slug = ?')
            ->execute(['in_asteptare', 'tst-mod-2']);

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf'  => $caSef['token'], 'slug' => 'tst-mod-2', 'stare' => 'respins',
            'motiv' => str_repeat('a', MOTIV_RESPINGERE_MAX + 1),
        ], $caSef['cookie']);
        verifica('un motiv prea lung e refuzat', 422, $r['cod']);
        verifica('și anunțul rămâne cum era', 'in_asteptare',
            (string) evenimentDupaSlug('tst-mod-2')['stare_moderare']);

        /* Fără motiv, respingerea merge oricum — e opțional. */
        $inainte = is_file($logEmail) ? (int) filesize($logEmail) : 0;

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-2', 'stare' => 'respins',
        ], $caSef['cookie']);
        verifica('fără motiv, respingerea merge', true,
            (json_decode($r['corp'], true) ?: [])['ok'] ?? false);

        if (!empty($config['dezvoltare'])) {
            $nou = (string) preg_replace('/\s+/u', ' ',
                substr((string) file_get_contents($logEmail), $inainte));
            verifica('iar e-mailul spune că n-a fost niciunul', true,
                str_contains($nou, 'Nu a fost adăugată o explicație'));
        }

        /* --- „editare necesară": rămâne în așteptare --- */

        db()->prepare('UPDATE evenimente SET stare_moderare = ? WHERE slug = ?')
            ->execute(['in_asteptare', 'tst-mod-2']);

        $inainte = is_file($logEmail) ? (int) filesize($logEmail) : 0;

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf'    => $caSef['token'], 'slug' => 'tst-mod-2', 'stare' => 'respins',
            'motiv'   => 'Lipsește ora de început.',
            'editare' => true,
        ], $caSef['cookie']);

        $corp = json_decode($r['corp'], true) ?: [];

        verifica('cu bifa pusă, cererea reușește', true, $corp['ok'] ?? false);
        verifica('și răspunsul o spune',           true, $corp['editare'] ?? false);
        verifica('iar anunțul RĂMÂNE în așteptare', 'in_asteptare',
            (string) evenimentDupaSlug('tst-mod-2')['stare_moderare']);

        if (!empty($config['dezvoltare'])) {
            $nou = substr((string) file_get_contents($logEmail), $inainte);
            verifica('organizatorul primește vestea', true,
                str_contains($nou, 'are nevoie de câteva ajustări'));
            verifica('cu ce are de îndreptat', true,
                str_contains($nou, 'Lipsește ora de început.'));
        }

        /* Se poate cere îndreptarea de mai multe ori — nu e o stare nouă. */
        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-2', 'stare' => 'respins',
            'editare' => true,
        ], $caSef['cookie']);
        verifica('și se poate cere din nou', true,
            (json_decode($r['corp'], true) ?: [])['ok'] ?? false);

        /* --- respingerea adevărată golește tot --- */

        $evPlin = faEveniment('tst-mod-plin', $org, 'aprobat');
        $idPlin = (int) $evPlin['id'];

        faOrganizatorulParticipant($idPlin, $org);
        salveazaInteres($idPlin, $ala, 'participant');
        $idCom2 = salveazaComentariu($idPlin, $ala, 'Alt comentariu de probă.');

        verifica('anunțul plin are interese',   2, $cate('interese_evenimente', $idPlin));
        verifica('și comentarii',               1, $cate('comentarii', $idPlin));

        /* Întâi cu bifa PUSĂ: nu se șterge nimic. */
        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-plin', 'stare' => 'respins',
            'editare' => true,
        ], $caSef['cookie']);

        verifica('cu bifa pusă, nu se golește nimic', 2, $cate('interese_evenimente', $idPlin));
        verifica('nici comentariile',                 1, $cate('comentarii', $idPlin));

        /* Apoi cu bifa SCOASĂ: se duce tot. */
        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-plin', 'stare' => 'respins',
            'editare' => false,
        ], $caSef['cookie']);

        $corp = json_decode($r['corp'], true) ?: [];

        verifica('respingerea adevărată reușește', true, $corp['ok'] ?? false);
        verifica('anunțul e respins', 'respins',
            (string) evenimentDupaSlug('tst-mod-plin')['stare_moderare']);
        verifica('interesele s-au golit',   0, $cate('interese_evenimente', $idPlin));
        verifica('comentariile s-au golit', 0, $cate('comentarii', $idPlin));
        verifica('și răspunsul spune câte', 2, $corp['sters']['interese'] ?? -1);
        verifica('dar anunțul rămâne în bază', true,
            evenimentDupaSlug('tst-mod-plin') !== null);

        if (!empty($config['dezvoltare'])) {
            $nou = substr((string) file_get_contents($logEmail), $inainte);
            verifica('iar organizatorului nu i se spune că s-a șters ceva', false,
                str_contains($nou, 'ters'));
        }

        /* Aprobat după o respingere: organizatorul se pune la loc pe listă. */
        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-plin', 'stare' => 'aprobat',
        ], $caSef['cookie']);

        verifica('aprobarea de după merge', true,
            (json_decode($r['corp'], true) ?: [])['ok'] ?? false);
        verifica('și organizatorul e iar pe lista lui', 1,
            $cate('interese_evenimente', $idPlin));

        /* --- stări pe care nu le știm --- */

        // Se ia starea de ACUM, nu se presupune: secțiunile de mai sus au
        // umblat la ea, iar ce se verifică aici e că o stare necunoscută nu
        // schimbă nimic — oricare ar fi ea.
        $stareInainte = (string) evenimentDupaSlug('tst-mod-2')['stare_moderare'];

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-2', 'stare' => 'incheiat',
        ], $caSef['cookie']);
        verifica('„incheiat" nu se pune de aici', 422, $r['cod']);

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-2', 'stare' => 'anulat',
        ], $caSef['cookie']);
        verifica('nici „anulat"', 422, $r['cod']);

        verifica('iar anunțul a rămas cum era', $stareInainte,
            (string) evenimentDupaSlug('tst-mod-2')['stare_moderare']);

        /* --- anulat și încheiat nu se mai moderează --- */

        faEveniment('tst-mod-anulat', $org, 'anulat');
        faEveniment('tst-mod-incheiat', $org, 'incheiat');

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-anulat', 'stare' => 'aprobat',
        ], $caSef['cookie']);
        verifica('un anunț anulat nu se aprobă', 409, $r['cod']);
        verifica('și rămâne anulat', 'anulat',
            (string) evenimentDupaSlug('tst-mod-anulat')['stare_moderare']);

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-incheiat', 'stare' => 'respins',
        ], $caSef['cookie']);
        verifica('nici unul încheiat nu se respinge', 409, $r['cod']);

        /* Și nici blocul nu se mai arată la ele. */
        $pag = cere('/eveniment/tst-mod-anulat', null, $caSef['cookie']);
        verifica('la anulat, blocul nu se mai scrie', false,
            str_contains($pag['corp'], 'data-moderare'));

        $pag = cere('/eveniment/tst-mod-incheiat', null, $caSef['cookie']);
        verifica('nici la încheiat', false, str_contains($pag['corp'], 'data-moderare'));

        /* --- butonul stării de acum lipsește --- */

        // Un anunț al lui, respins, pus anume pentru verificarea asta: cele de
        // mai sus au trecut prin prea multe stări ca să se mai poată presupune
        // ceva despre ele.
        faEveniment('tst-mod-resp', $org, 'respins');

        $pag = cere('/eveniment/tst-mod-resp', null, $caSef['cookie']);
        verifica('la un anunț respins nu se mai oferă „Respinge"', false,
            str_contains($pag['corp'], 'data-modereaza="respins"'));
        verifica('dar „Aprobă" se oferă', true,
            str_contains($pag['corp'], 'data-modereaza="aprobat"'));

        // Iar la unul în așteptare se oferă amândouă.
        faEveniment('tst-mod-astept', $org, 'in_asteptare');
        $pag = cere('/eveniment/tst-mod-astept', null, $caSef['cookie']);
        verifica('la unul în așteptare, amândouă', true,
            str_contains($pag['corp'], 'data-modereaza="respins"')
            && str_contains($pag['corp'], 'data-modereaza="aprobat"'));
        verifica('cu bifa pusă din start', true,
            str_contains($pag['corp'], 'checked data-moderare-editare'));

        /* --- un slug care nu duce nicăieri --- */

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'nu-exista-nicaieri', 'stare' => 'aprobat',
        ], $caSef['cookie']);
        verifica('un slug inventat e 404', 404, $r['cod']);

        /* --- doar POST --- */

        $r = cere('/api/modereaza-eveniment.php', null, $caSef['cookie']);
        verifica('nu primește GET', 405, $r['cod']);

        /* ============ FORMULARUL DE PUBLICARE, PENTRU FIECARE ========= */

        /**
         * api/eveniment.php primește un formular, nu JSON: e singurul care
         * poate purta și un fișier. Fără copertă, „urlencoded" umple $_POST
         * la fel de bine ca „multipart", deci nu ne trebuie un plic întreg.
         */
        $cereFormular = static function (array $campuri, string $cookie) use ($BAZA): array {
            $ctx = ['http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/x-www-form-urlencoded\r\n"
                                 . ($cookie !== '' ? "Cookie: $cookie\r\n" : ''),
                'content'       => http_build_query($campuri),
                'ignore_errors' => true,
            ]];

            $raspuns = @file_get_contents($BAZA . '/api/eveniment.php', false,
                stream_context_create($ctx));

            $cod = 0;
            foreach ($http_response_header ?? [] as $rand) {
                if (preg_match('#^HTTP/\S+ (\d+)#', $rand, $m) === 1) { $cod = (int) $m[1]; }
            }

            return ['cod' => $cod, 'corp' => json_decode((string) $raspuns, true) ?: []];
        };

        /**
         * Limitele, înainte de a cere paginile.
         *
         * Omului obișnuit i se ridică: are deja anunțuri din secțiunile de mai
         * sus, iar cine a atins limita nu primește formularul deloc — primește
         * panoul care-i spune să aștepte, fără buton și fără token. Aici se
         * verifică ce scrie pe buton, nu numărătoarea.
         *
         * Omului de casă i se LASĂ 1, tocmai ca să se vadă că trece peste ea:
         * are deja mai multe active, deci fără portița pentru staff n-ar mai
         * primi nici pagina, nici dreptul de a publica.
         */
        db()->prepare('UPDATE membri SET limita_evenimente_active = 20 WHERE permalink = ?')
            ->execute(['tstmod-org']);
        db()->prepare('UPDATE membri SET limita_evenimente_active = 1 WHERE permalink = ?')
            ->execute(['tstmod-sef']);

        // Un anunț activ al lui, ca limita de 1 să fie chiar atinsă: cele din
        // secțiunile de mai sus au fost mutate în trecut și încheiate, pentru
        // proba cu istoricul, deci nu se mai numără printre cele active.
        faEveniment('tst-mod-activ', $sef, 'aprobat');

        verifica('omul de casă și-a atins limita', true,
            count(evenimenteActive($sef)) >= limitaEvenimente($sef));

        /* --- ce scrie pe buton, și dacă se vede bifa --- */

        $formOrg = intra(SEMN . 'org@invalid.local', '/adauga_eveniment.php');
        $formSef = intra(SEMN . 'sef@invalid.local', '/adauga_eveniment.php');

        verifica('omului obișnuit îi scrie „Trimite spre aprobare"', true,
            str_contains($formOrg['corp'], 'Trimite spre aprobare'));
        verifica('și nu „Publică evenimentul"', false,
            str_contains($formOrg['corp'], 'Publică evenimentul'));
        verifica('nici bifa de ținut deoparte n-o vede', false,
            str_contains($formOrg['corp'], 'name="ascuns_pe_profil"'));

        verifica('omului de casă îi scrie „Publică evenimentul"', true,
            str_contains($formSef['corp'], 'Publică evenimentul'));
        verifica('și nu „Trimite spre aprobare"', false,
            str_contains($formSef['corp'], 'Trimite spre aprobare'));
        verifica('lui i se desenează și bifa', true,
            str_contains($formSef['corp'], 'name="ascuns_pe_profil"'));
        verifica('nebifată din start', false,
            str_contains($formSef['corp'], 'name="ascuns_pe_profil" value="1"' . "\n"
                       . '                   checked'));

        /* --- ce ajunge în bază prin API --- */

        /**
         * Slugul din adresa întoarsă de API: „/eveniment/…".
         *
         * A fost „event.php?slug=…" până la adresele frumoase; se ia din CALE,
         * nu din interogare. urlEveniment() scrie cu rawurlencode, deci se
         * citește înapoi cu rawurldecode.
         */
        $slugDinUrl = static function ($url): string {
            $cale = (string) parse_url((string) $url, PHP_URL_PATH);
            return rawurldecode(basename($cale));
        };

        $anunt = static function (string $titlu, array $peste = []): array {
            return array_merge([
                'titlu'            => $titlu,
                'categorie_id'     => (string) db()->query('SELECT MIN(id) FROM categorii')->fetchColumn(),
                'oras'             => oraseDisponibile()[0] ?? 'Roman',
                'locatie'          => 'Centru vechi',
                // Data se scrie ca în formular, zi-lună-an.
                'data_eveniment'   => date('d-m-Y', strtotime('+11 days')),
                'ora_inceput'      => '18:00',
                // Bifele care spun „nu se știe"; fără ele, câmpurile golite
                // sunt luate drept răspunsuri lipsă, nu drept nespecificate.
                'fara_ora_sfarsit'      => '1',
                'gratuit'               => '1',
                'fara_participanti_min' => '1',
                'fara_participanti_max' => '1',
                'gen_participanti' => 'nespecificat',
                'descriere'        => str_repeat('Text de probă pentru anunț. ', 15),
            ], $peste);
        };

        $r = $cereFormular($anunt('Anunț pus de omul de casă', [
            'csrf'             => $formSef['token'],
            'ascuns_pe_profil' => '1',
        ]), $formSef['cookie']);

        verifica('staff-ul publică, deși e peste limită', 200, $r['cod']);
        verifica('iar mesajul nu mai vorbește de aprobare', 'Gata, evenimentul e publicat!',
            (string) ($r['corp']['mesaj'] ?? ''));

        $pus = evenimentDupaSlug($slugDinUrl($r['corp']['url'] ?? ''));

        verifica('anunțul lui e aprobat pe loc', 'aprobat',
            (string) ($pus['stare_moderare'] ?? ''));
        verifica('și ținut deoparte de profil', 1, (int) ($pus['ascuns_pe_profil'] ?? 0));

        /**
         * Și nu s-a trecut singur pe lista de participanți: la anunțul orașului
         * el nu e cel care iese, e cel care a scris anunțul.
         */
        verifica('fără să se treacă pe lista de participanți', null,
            interesulMeu((int) $pus['id'], $sef));
        verifica('deci nimeni pe listă', 0,
            numaraInterese((int) $pus['id'])['participant']);

        /* --- același lucru cerut de cine nu e staff --- */

        $r = $cereFormular($anunt('Anunț pus de un om obișnuit', [
            'csrf'             => $formOrg['token'],
            'ascuns_pe_profil' => '1',
        ]), $formOrg['cookie']);

        verifica('și el poate publica', 200, $r['cod']);
        verifica('dar mesajul lui vorbește de aprobare',
            'Gata! Anunțul tău merge spre aprobare — îți dăm de veste imediat ce l-am citit.',
            (string) ($r['corp']['mesaj'] ?? ''));

        $pus = evenimentDupaSlug($slugDinUrl($r['corp']['url'] ?? ''));

        verifica('anunțul lui rămâne în așteptare', 'in_asteptare',
            (string) ($pus['stare_moderare'] ?? ''));

        // Pe el, în schimb, îl trece pe listă ca până acum: bifa n-a avut niciun
        // cuvânt, deci anunțul e o ieșire de-a lui ca oricare alta.
        verifica('iar el e trecut pe lista lui de participanți', 'participant',
            interesulMeu((int) $pus['id'], $org));

        /**
         * Bifa trimisă de mână, de cine n-o vede în pagină, nu schimbă nimic.
         * Asta e regula; caseta din formular e doar purtare frumoasă.
         */
        verifica('iar bifa trimisă de mână e ignorată', 0,
            (int) ($pus['ascuns_pe_profil'] ?? 1));
    }
}

/* ============================= GATA ================================== */

echo "\n" . str_repeat('=', 60) . "\n";
echo "TOTAL: $treceri trecute, $picaturi picate\n";

exit($picaturi > 0 ? 1 : 0);
