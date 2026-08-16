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
        str_contains($nou, 'Fotbal în parc" a fost aprobat'));
    verifica('și că se vede pe site', true, str_contains($nou, 'se vede'));
    verifica('fără vorbe despre motive', false, str_contains($nou, 'motiv'));

    /* --- respins CU motiv --- */
    $nou = $citeste(function () {
        emailModerareAnunt(SEMN . 'org@invalid.local', 'Dan', 'Fotbal în parc',
            'https://exemplu.test/event.php?slug=x', 'respins', 'Lipsește adresa exactă.');
    });

    verifica('la respingere, subiectul o spune', true,
        str_contains($nou, 'Fotbal în parc" nu a fost aprobat'));
    verifica('motivul scris ajunge întreg', true,
        str_contains($nou, 'Lipsește adresa exactă.'));
    verifica('și se spune al cui e', true, str_contains($nou, 'Motivul, așa cum a fost scris'));
    verifica('fără vorba pentru lipsa lui', false,
        str_contains($nou, 'Nu s-a specificat nici un motiv'));
    verifica('cu îndemnul de a-l îndrepta', true, str_contains($nou, 'îndrepți'));

    /* --- respins FĂRĂ motiv --- */
    $nou = $citeste(function () {
        emailModerareAnunt(SEMN . 'org@invalid.local', 'Dan', 'Fotbal în parc',
            'https://exemplu.test/event.php?slug=x', 'respins', '');
    });

    verifica('fără motiv, se spune pe față', true,
        str_contains($faraRupturi($nou),
            'Nu s-a specificat nici un motiv. Pentru orice nelămurire, '
            . 'te rugăm să ne contactezi.'));
    verifica('și nu se pretinde că ar fi unul', false,
        str_contains($nou, 'Motivul, așa cum a fost scris'));

    /* --- editare necesară: nici „da", nici „nu" --- */
    $nou = $citeste(function () {
        emailModerareAnunt(SEMN . 'org@invalid.local', 'Dan', 'Fotbal în parc',
            'https://exemplu.test/event.php?slug=x', 'editare', 'Lipsește ora de început.');
    });

    verifica('la editare, subiectul cere schimbări', true,
        str_contains($nou, 'mai are nevoie de câteva schimbări'));
    verifica('și spune că NU e respins', true,
        str_contains($faraRupturi($nou), 'Nu l-am respins'));
    verifica('cu ce trebuie schimbat', true, str_contains($nou, 'Lipsește ora de început.'));
    verifica('și cu îndemnul de a-l trimite din nou', true,
        str_contains($faraRupturi($nou), 'trimite-l din nou'));
    verifica('fără vorba „nu a fost aprobat"', false,
        str_contains($nou, 'nu a fost aprobat'));

    /* Și fără motiv, tot aceeași vorbă pentru lipsa lui. */
    $nou = $citeste(function () {
        emailModerareAnunt(SEMN . 'org@invalid.local', 'Dan', 'Fotbal în parc',
            'https://exemplu.test/event.php?slug=x', 'editare', '');
    });
    verifica('la editare fără motiv, se spune la fel', true,
        str_contains($faraRupturi($nou), 'Nu s-a specificat nici un motiv.'));

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

/* ===================== 3. PRIN SERVER =============================== */

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
    $adresa = '/event.php?slug=tst-mod-2';

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
            $nou = substr((string) file_get_contents($logEmail), $inainte);
            verifica('iar e-mailul spune că n-a fost niciunul', true,
                str_contains($nou, 'Nu s-a specificat nici un motiv'));
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
                str_contains($nou, 'mai are nevoie de câteva schimbări'));
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
        $pag = cere('/event.php?slug=tst-mod-anulat', null, $caSef['cookie']);
        verifica('la anulat, blocul nu se mai scrie', false,
            str_contains($pag['corp'], 'data-moderare'));

        $pag = cere('/event.php?slug=tst-mod-incheiat', null, $caSef['cookie']);
        verifica('nici la încheiat', false, str_contains($pag['corp'], 'data-moderare'));

        /* --- butonul stării de acum lipsește --- */

        // Un anunț al lui, respins, pus anume pentru verificarea asta: cele de
        // mai sus au trecut prin prea multe stări ca să se mai poată presupune
        // ceva despre ele.
        faEveniment('tst-mod-resp', $org, 'respins');

        $pag = cere('/event.php?slug=tst-mod-resp', null, $caSef['cookie']);
        verifica('la un anunț respins nu se mai oferă „Respinge"', false,
            str_contains($pag['corp'], 'data-modereaza="respins"'));
        verifica('dar „Aprobă" se oferă', true,
            str_contains($pag['corp'], 'data-modereaza="aprobat"'));

        // Iar la unul în așteptare se oferă amândouă.
        faEveniment('tst-mod-astept', $org, 'in_asteptare');
        $pag = cere('/event.php?slug=tst-mod-astept', null, $caSef['cookie']);
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
    }
}

/* ============================= GATA ================================== */

echo "\n" . str_repeat('=', 60) . "\n";
echo "TOTAL: $treceri trecute, $picaturi picate\n";

exit($picaturi > 0 ? 1 : 0);
