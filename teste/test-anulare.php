<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — vestea că un eveniment s-a anulat.
 *
 * Cere BAZA DE DATE. Partea de API cere și SERVERUL, dar se sare singură dacă
 * nu i se dă o adresă.
 *
 * Cum se rulează:
 *     php teste/test-anulare.php                        (fără API)
 *     php teste/test-anulare.php http://127.0.0.1:8099  (cu tot)
 *
 * Își face singur oamenii și evenimentul de care are nevoie, cu nume care nu se
 * pot încurca cu ale nimănui, și le șterge la sfârșit — și dacă pică ceva la
 * mijloc, prin curata() de la coadă.
 *
 * NU TRIMITE E-MAILURI ADEVĂRATE: cu `dezvoltare => true` în inc/config.php,
 * inc/email.php scrie mesajele în private/emailuri-trimise.log în loc să le
 * pună pe drum. De acolo se citesc aici.
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

const SEMN  = 'test-anulare-';
const PAROLA = 'ParolaDeProba#2026';

function curata(): void
{
    db()->prepare('DELETE FROM evenimente WHERE slug LIKE ?')->execute(['tst-anul-%']);

    /**
     * Se șterge ȘI după permalink, nu doar după adresă.
     *
     * Unul dintre oamenii de probă își pierde adresa în timpul probei — e cel
     * căruia i se șterge contul, iar acolo adresa se golește. După el nu mai
     * rămânea nicio urmă de căutat, așa că rândul supraviețuia curățeniei și a
     * doua rulare se lovea de permalinkul lui, deja luat.
     */
    db()->prepare('DELETE FROM membri WHERE email LIKE ?')->execute([SEMN . '%']);
    db()->prepare('DELETE FROM membri WHERE permalink LIKE ?')->execute(['tstanul-%']);
}

curata();
register_shutdown_function('curata');

function faMembru(string $cheie, string $prenume, string $sex = 'M'): int
{
    db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, sex, data_nasterii,
                             parola_hash, stare, este_staff, creat_la, confirmat_la)
         VALUES (?,?,?,?,?,\'1990-01-01\',?,\'activ\',0,?,?)'
    )->execute([
        substr('tstanul-' . $cheie, 0, 16), 'Popa', $prenume,
        SEMN . $cheie . '@invalid.local', $sex,
        password_hash(PAROLA, PASSWORD_DEFAULT), acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

$org  = faMembru('org',  'Ioana', 'F');
$vine = faMembru('vine', 'Radu');
$uita = faMembru('uita', 'Elena', 'F');   // doar interesat
$alt  = faMembru('alt',  'Mihai');        // nu e pe nicio listă
$sters = faMembru('sters', 'Fantoma');    // cont șters între timp

/* Un eveniment care urmează, al lui Ioana. */
db()->prepare(
    'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, descriere, oras,
                             locatie, data_eveniment, ora_inceput, stare_moderare,
                             creat_la, actualizat_la)
     VALUES (?, (SELECT MIN(id) FROM categorii), ?, ?, ?, ?, "Centru", ?, "18:00:00",
             "aprobat", ?, ?)'
)->execute([
    $org, 'Alergare de seară', 'tst-anul-ev', str_repeat('Text de probă. ', 20),
    oraseDisponibile()[0] ?? 'Roman', date('Y-m-d', strtotime('+6 days')), acum(), acum(),
]);

$evenimentId = (int) db()->lastInsertId();

/* Organizatorul e trecut singur pe lista de participanți, ca la publicare. */
faOrganizatorulParticipant($evenimentId, $org);

salveazaInteres($evenimentId, $vine,  'participant');
salveazaInteres($evenimentId, $uita,  'interesat');
salveazaInteres($evenimentId, $sters, 'participant');

/* Contul ăsta se șterge după ce s-a înscris: nu mai trebuie să afle nimic. */
db()->prepare('UPDATE membri SET stare = ?, email = ? WHERE id = ?')
    ->execute(['sters', '', $sters]);

/* ===================== 1. CINE AFLĂ ================================= */

sectiune('cine află');

$oameni = oameniiDeInstiintatLaAnulare($evenimentId, $org);
$ids    = array_map('intval', array_column($oameni, 'id'));

verifica('cel care confirmase e pe listă', true, in_array($vine, $ids, true));
verifica('și cel doar interesat',          true, in_array($uita, $ids, true));
verifica('organizatorul NU e pe listă',    false, in_array($org, $ids, true));
verifica('nici cine n-avea treabă cu el',  false, in_array($alt, $ids, true));
verifica('nici contul șters',              false, in_array($sters, $ids, true));
verifica('deci doi oameni cu totul',       2,     count($oameni));

/* Starea vine cu ei, ca mesajul să poată fi scris după om. */
$stari = [];
foreach ($oameni as $om) { $stari[(int) $om['id']] = (string) $om['stare']; }

verifica('se știe cine confirmase', 'participant', $stari[$vine] ?? '');
verifica('și cine doar se uita',    'interesat',   $stari[$uita] ?? '');

/* Fără organizator dat deoparte, el ar fi primit vestea de la el însuși. */
$cuTot = oameniiDeInstiintatLaAnulare($evenimentId);
verifica('fără excludere, ar fi trei', 3, count($cuTot));

/* ===================== 2. CUM ARATĂ MESAJUL ========================= */

sectiune('cum arată mesajul');

/**
 * Se citește din private/emailuri-trimise.log, unde ajung mesajele cât
 * `dezvoltare` e pornit în config.php. Dacă nu e, partea asta n-are ce citi.
 */
global $config;

$logEmail = __DIR__ . '/../private/emailuri-trimise.log';

if (empty($config['dezvoltare'])) {
    echo "  (`dezvoltare` e oprit în config.php — partea asta s-a sărit)\n";
} else {
    $inainte = is_file($logEmail) ? (int) filesize($logEmail) : 0;

    $plecat = emailAnulareEveniment(
        SEMN . 'vine@invalid.local',
        'Radu',
        'Alergare de seară',
        'Sâmbătă, 22 august 2026',
        'S-a stricat vremea și nu avem unde ne adăposti.',
        'https://exemplu.test/index.php',
        true
    );

    verifica('mesajul pleacă', true, $plecat);

    $nou = file_get_contents($logEmail);
    $nou = substr((string) $nou, $inainte);

    verifica('are titlul evenimentului în subiect', true,
        str_contains($nou, 'Alergare de seară" a fost anulat'));
    verifica('spune cui i se scrie',   true, str_contains($nou, 'Bună, Radu!'));
    verifica('spune că nu mai are loc', true, str_contains($nou, 'nu mai are loc'));
    verifica('spune că era pe listă',   true, str_contains($nou, 'lista de participanți'));
    verifica('spune ziua',              true, str_contains($nou, '22 august 2026'));
    verifica('și motivul întreg',       true, str_contains($nou, 'S-a stricat vremea'));

    /**
     * Butonul NU duce spre eveniment: din clipa anulării pagina lui se deschide
     * doar pentru staff, deci ar fi fost o ușă închisă la capătul unui mesaj
     * care deja strica planul omului.
     */
    verifica('butonul duce în oraș, nu la eveniment', true,
        str_contains($nou, 'exemplu.test/index.php'));
    verifica('și nu spre pagina evenimentului', false, str_contains($nou, 'event.php'));

    /* Cel doar interesat primește altă primă propoziție. */
    $inainte = (int) filesize($logEmail);

    emailAnulareEveniment(
        SEMN . 'uita@invalid.local', 'Elena', 'Alergare de seară',
        'Sâmbătă, 22 august 2026', 'Motiv oarecare de probă.',
        'https://exemplu.test/index.php', false
    );

    $nou = substr((string) file_get_contents($logEmail), $inainte);

    verifica('cui era doar interesat i se spune altfel', true,
        str_contains($nou, 'te arătaseși interesat'));
    verifica('și nu i se spune că era pe listă', false,
        str_contains($nou, 'lista de participanți'));

    $inainte = (int) filesize($logEmail);

    emailAnulareEveniment(
        SEMN . 'vine@invalid.local', 'Radu', 'Test <b>gras</b>',
        '', 'Motiv cu <script>alert(1)</script> în el.',
        'https://exemplu.test/index.php', true
    );

    $nou = substr((string) file_get_contents($logEmail), $inainte);

    verifica('fără dată, nu scrie „Era programat pentru ."', false,
        str_contains($nou, 'Era programat pentru .'));
}

/**
 * Motivul e scris de om, deci trece prin aceeași ieșire ca orice text: scăpat
 * în varianta HTML. Nimeni nu poate strecura etichete în e-mailul altcuiva
 * prin caseta de motiv.
 *
 * Se cercetează ȘABLONUL, nu logul din private/: acolo se scrie varianta de
 * text simplu, unde un „<" e doar un semn — n-are ce strica și n-are de ce să
 * fie scăpat. Partea care contează e cealaltă, iar ea se cere de-a dreptul.
 */
sectiune('motivul nu poate strecura etichete');

$sablon = sablonEmail('Test', [
    'salut'     => 'Bună, Radu!',
    'paragrafe' => ['Motiv cu <script>alert(1)</script> în el.'],
]);

verifica('în HTML, eticheta nu rămâne etichetă', false,
    str_contains($sablon['html'], '<script>alert(1)</script>'));
verifica('ci text scăpat',                      true,
    str_contains($sablon['html'], '&lt;script&gt;'));
verifica('în textul simplu rămâne cum a fost scris', true,
    str_contains($sablon['text'], '<script>alert(1)</script>'));

/* ===================== 3. PRIN SERVER =============================== */

if ($BAZA === '') {
    echo "\n(partea de API s-a sărit — dă o adresă ca s-o rulezi:"
       . " php teste/test-anulare.php http://127.0.0.1:8099)\n";
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

    /* Intrăm în cont ca organizator. */
    $pagina = cere('/login.php');
    $cookie = $pagina['cookie'];
    preg_match('/name="csrf" value="([^"]+)"/', $pagina['corp'], $m);

    $r = cere('/api/autentificare.php', [
        'csrf'   => $m[1] ?? '',
        'email'  => SEMN . 'org@invalid.local',
        'parola' => PAROLA,
    ], $cookie);

    $corp = json_decode($r['corp'], true) ?: [];

    if (($corp['ok'] ?? false) !== true) {
        echo "  (intrarea în cont n-a mers — restul s-a sărit)\n";
    } else {
        $cookie = $r['cookie'];

        /**
         * Tokenul se ia de pe pagina de unde se apasă „Anulează" — formularul
         * de editare al evenimentului.
         *
         * Se caută în amândouă formele în care apare pe site: `name="csrf"`
         * într-un câmp ascuns (formularele obișnuite) și `data-csrf` pe un
         * element (acolo unde îl citește JS-ul, ca pe profil). Tokenul e al
         * sesiunii, deci e același oriunde ar fi scris — dar prima oară l-am
         * căutat doar într-o formă, pe o pagină care o folosea pe cealaltă, și
         * proba se lovea de un 419 care n-avea nicio legătură cu ce verifica.
         */
        $pagEditare = cere('/adauga_eveniment.php?slug=tst-anul-ev', null, $cookie);

        $token = '';

        if (preg_match('/name="csrf" value="([^"]+)"/', $pagEditare['corp'], $m) === 1
            || preg_match('/data-csrf="([^"]+)"/', $pagEditare['corp'], $m) === 1) {
            $token = $m[1];
        }

        verifica('am găsit tokenul de pe pagina de editare', true, $token !== '');

        $inainte = is_file($logEmail) ? (int) filesize($logEmail) : 0;

        $r = cere('/api/anuleaza-eveniment.php', [
            'csrf'  => $token,
            'slug'  => 'tst-anul-ev',
            'motiv' => 'Nu am mai găsit sala, îmi pare rău de tot.',
        ], $cookie);

        $corp = json_decode($r['corp'], true) ?: [];

        verifica('anularea reușește', true, $corp['ok'] ?? false);
        verifica('și spune câți au aflat', 2, $corp['instiintati'] ?? -1);

        $stare = db()->prepare('SELECT stare_moderare, motiv_anulare FROM evenimente WHERE id = ?');
        $stare->execute([$evenimentId]);
        $rand = $stare->fetch();

        verifica('evenimentul e anulat în bază', 'anulat', $rand['stare_moderare'] ?? '');
        verifica('cu motivul scris de organizator',
            'Nu am mai găsit sala, îmi pare rău de tot.', $rand['motiv_anulare'] ?? '');

        if (!empty($config['dezvoltare'])) {
            $nou = substr((string) file_get_contents($logEmail), $inainte);

            verifica('i-a plecat mesaj celui care confirmase', true,
                str_contains($nou, SEMN . 'vine@invalid.local'));
            verifica('și celui doar interesat', true,
                str_contains($nou, SEMN . 'uita@invalid.local'));
            verifica('organizatorului, nu', false,
                str_contains($nou, SEMN . 'org@invalid.local'));
            verifica('nici celui care n-avea treabă', false,
                str_contains($nou, SEMN . 'alt@invalid.local'));
            verifica('motivul a ajuns în mesaj', true,
                str_contains($nou, 'Nu am mai găsit sala'));
        }

        /* A doua anulare nu mai are ce anula — deci nici ce trimite. */
        $inainte = is_file($logEmail) ? (int) filesize($logEmail) : 0;

        $r = cere('/api/anuleaza-eveniment.php', [
            'csrf'  => $token,
            'slug'  => 'tst-anul-ev',
            'motiv' => 'Încă o dată, ca să vedem ce se întâmplă.',
        ], $cookie);

        verifica('a doua anulare e refuzată', 404, $r['cod']);

        if (!empty($config['dezvoltare'])) {
            $nou = substr((string) file_get_contents($logEmail), $inainte);
            verifica('și nu mai pleacă niciun mesaj', false,
                str_contains($nou, SEMN . 'vine@invalid.local'));
        }

        /* Motivul prea scurt nu trece — și atunci nu se anulează nimic. */
        db()->prepare('UPDATE evenimente SET stare_moderare = ? WHERE id = ?')
            ->execute(['aprobat', $evenimentId]);

        $inainte = is_file($logEmail) ? (int) filesize($logEmail) : 0;

        $r = cere('/api/anuleaza-eveniment.php', [
            'csrf'  => $token,
            'slug'  => 'tst-anul-ev',
            'motiv' => 'scurt',
        ], $cookie);

        verifica('motivul prea scurt e refuzat', 422, $r['cod']);

        $stare->execute([$evenimentId]);
        verifica('evenimentul rămâne neatins', 'aprobat',
            ($stare->fetch()['stare_moderare'] ?? ''));

        if (!empty($config['dezvoltare'])) {
            $nou = substr((string) file_get_contents($logEmail), $inainte);
            verifica('și nu pleacă nicio veste degeaba', false,
                str_contains($nou, SEMN . 'vine@invalid.local'));
        }
    }
}

/* ============================= GATA ================================== */

echo "\n" . str_repeat('=', 60) . "\n";
echo "TOTAL: $treceri trecute, $picaturi picate\n";

exit($picaturi > 0 ? 1 : 0);
