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
     * Butonul duce în oraș, nu la evenimentul anulat.
     *
     * Pagina lui se deschide de oricine acum, cu banda și motivul la vedere —
     * dar motivul e deja în mesajul ăsta, iar omul care tocmai a aflat că i s-a
     * stricat seara are nevoie de altceva de făcut, nu de încă o citire a
     * aceleiași vești.
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

/* ================ 3. DUPĂ CE A ÎNCEPUT, NU SE MAI ANULEAZĂ =========== */

sectiune('ceasul închide anularea');

/**
 * Anularea se poate până la O ORĂ DUPĂ ora de început
 * (MINUTE_ANULARE_DUPA_INCEPUT). În primul sfert de oră de la ora scrisă în
 * anunț se vede, de fapt, dacă ieșirea are loc: plouă, au venit doi din
 * doisprezece, s-a închis terasa. După ceasul acela, o veste de „nu mai are
 * loc" nu mai ajută pe nimeni — butonul dispare, iar API-ul răspunde 409.
 *
 * Regula o ține poateFiAnulat(), aceeași funcție pentru amândouă paginile cu
 * buton și pentru server. Aici se verifică funcția; partea de API e mai jos.
 */
$candva = static function (string $data, string $ora): array {
    return [
        'id'             => 0,
        'stare_moderare' => 'aprobat',
        'data_eveniment' => $data,
        'ora_inceput'    => $ora,
    ];
};

verifica('unul de peste o săptămână se poate anula', true,
    poateFiAnulat($candva(date('Y-m-d', strtotime('+7 days')), '18:00:00')));

verifica('și unul de azi, dar de peste un ceas', true,
    poateFiAnulat($candva(date('Y-m-d'), date('H:i:s', time() + 3600))));

/* --- fereastra de o oră, de o parte și de alta a ei --- */

verifica('cel care tocmai a început SE POATE ÎNCĂ', true,
    poateFiAnulat($candva(date('Y-m-d'), date('H:i:s', time() - 60))));

verifica('și la 59 de minute după, tot se poate', true,
    poateFiAnulat($candva(date('Y-m-d'), date('H:i:s', time() - 59 * 60))));

verifica('la 61 de minute, nu mai merge', false,
    poateFiAnulat($candva(date('Y-m-d'), date('H:i:s', time() - 61 * 60))));

verifica('nici cel de ieri', false,
    poateFiAnulat($candva(date('Y-m-d', strtotime('-1 day')), '18:00:00')));

verifica('fereastra e de o oră', 60, MINUTE_ANULARE_DUPA_INCEPUT);

/**
 * Fără oră scrisă, ziua începe la miezul nopții — ca în evenimentAInceput().
 * Se cere IERI, nu azi: la ora la care rulează proba în primele șaizeci de
 * minute ale zilei, „azi la 00:00" ar fi încă în fereastră, iar verificarea ar
 * pica o dată pe zi, la miezul nopții.
 */
verifica('fără oră, ziua de ieri e demult începută', false,
    poateFiAnulat($candva(date('Y-m-d', strtotime('-1 day')), '')));

$anulatDeja = $candva(date('Y-m-d', strtotime('+7 days')), '18:00:00');
$anulatDeja['stare_moderare'] = 'anulat';
verifica('ce e anulat nu se mai anulează o dată', false, poateFiAnulat($anulatDeja));

$incheiatDeja = $candva(date('Y-m-d', strtotime('+7 days')), '18:00:00');
$incheiatDeja['stare_moderare'] = 'incheiat';
verifica('nici ce e încheiat de mână, deși ziua e în viitor', false,
    poateFiAnulat($incheiatDeja));

/* ================= 3b. CEASUL ÎNCHIDE ȘI EDITAREA =================== */

sectiune('ceasul închide editarea');

/**
 * Editarea se închide MAI DEVREME decât anularea: în clipa începerii, nu la o
 * oră după. Ce era de îndreptat se îndrepta înainte — după ora de start
 * oamenii sunt deja pe drum, iar o schimbare de loc le-ar ajunge sub ochi prea
 * târziu. Butonul de anulare rămâne, tocmai fiindcă atunci se vede dacă
 * ieșirea are loc.
 *
 * Cele două funcții TREBUIE să se despartă aici: poateFiEditat() se stinge la
 * minutul zero, poateFiAnulat() abia peste o oră.
 */
verifica('unul de peste o săptămână se editează', true,
    poateFiEditat($candva(date('Y-m-d', strtotime('+7 days')), '18:00:00')));

verifica('și unul de azi, dar de peste un ceas', true,
    poateFiEditat($candva(date('Y-m-d'), date('H:i:s', time() + 3600))));

$tocmaiAInceput = $candva(date('Y-m-d'), date('H:i:s', time() - 60));

verifica('cel care tocmai a început NU se mai editează', false,
    poateFiEditat($tocmaiAInceput));
verifica('dar se poate încă anula', true, poateFiAnulat($tocmaiAInceput));

verifica('nici cel de ieri nu se editează', false,
    poateFiEditat($candva(date('Y-m-d', strtotime('-1 day')), '18:00:00')));

verifica('ce e anulat nu se editează', false, poateFiEditat($anulatDeja));
verifica('nici ce e încheiat',         false, poateFiEditat($incheiatDeja));

/* ===================== 4. PRIN SERVER =============================== */

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

        // Anteturile brute rămân la îndemână: `file_get_contents` urmează
        // singur redirecționările, deci `cod` e al paginii de la capăt, iar
        // „unde m-a trimis" se citește doar de aici.
        return ['cod' => $cod, 'corp' => (string) $raspuns, 'cookie' => $cookieNou,
                'anteturi' => $http_response_header ?? []];
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

        /* ---------- butonul, pe amândouă paginile ---------- */

        /**
         * Aceeași zonă de anulare, scrisă dintr-un singur loc
         * (randeazaZonaAnulare), stă pe formularul de editare ȘI pe pagina
         * evenimentului, sub caseta de interes. `data-anulare` e cum o găsește
         * main.js — și cum o găsim și noi aici.
         */
        verifica('zona de anulare e pe formularul de editare', true,
            str_contains($pagEditare['corp'], 'data-anulare'));

        $pagEveniment = cere('/event.php?slug=tst-anul-ev', null, $cookie);
        verifica('și pe pagina evenimentului', true,
            str_contains($pagEveniment['corp'], 'data-anulare'));
        verifica('cu slugul lui pe ea', true,
            str_contains($pagEveniment['corp'], 'data-slug="tst-anul-ev"'));

        /**
         * DOAR ORGANIZATORULUI. Pentru oricine altcineva blocul nici nu se
         * scrie în pagină — nu se desenează stins, nu se ascunde cu CSS.
         */
        $altCookie = '';
        $p = cere('/login.php');
        preg_match('/name="csrf" value="([^"]+)"/', $p['corp'], $m);
        $rAlt = cere('/api/autentificare.php', [
            'csrf' => $m[1] ?? '', 'email' => SEMN . 'vine@invalid.local', 'parola' => PAROLA,
        ], $p['cookie']);
        $altCookie = $rAlt['cookie'];

        verifica('un alt membru nu vede zona de anulare', false,
            str_contains(cere('/event.php?slug=tst-anul-ev', null, $altCookie)['corp'],
                'data-anulare'));
        verifica('nici vizitatorul fără cont', false,
            str_contains(cere('/event.php?slug=tst-anul-ev')['corp'], 'data-anulare'));

        /**
         * Cât ține ceasul. La două ore după ora de început, butonul dispare de
         * pe amândouă paginile — aceeași funcție hotărăște în amândouă locurile.
         */
        db()->prepare('UPDATE evenimente SET data_eveniment = ?, ora_inceput = ? WHERE id = ?')
            ->execute([date('Y-m-d'), date('H:i:s', time() - 2 * 3600), $evenimentId]);

        verifica('trecut ceasul, butonul dispare de pe eveniment', false,
            str_contains(cere('/event.php?slug=tst-anul-ev', null, $cookie)['corp'],
                'data-anulare'));

        /**
         * Formularul de editare nici nu se mai deschide.
         *
         * De când un eveniment început nu se mai poate schimba (poateFiEditat),
         * pagina de editare trimite înapoi la pagina evenimentului — acolo e
         * tot ce mai are de făcut organizatorul: „Anulează" cât ține ceasul, și
         * „Încheie evenimentul". Nu pe prima pagină: n-a greșit adresa, doar a
         * trecut ora.
         */
        $dupaCeas = cere('/adauga_eveniment.php?slug=tst-anul-ev', null, $cookie);
        verifica('formularul de editare nu se mai deschide', false,
            str_contains($dupaCeas['corp'], 'id="eveniment-form"'));
        verifica('și trimite la pagina evenimentului', true,
            str_contains(strtolower(implode("\n", $dupaCeas['anteturi'])),
                'location: event.php?slug=tst-anul-ev'));

        /* În fereastră, la douăzeci de minute după început, e la locul lui. */
        db()->prepare('UPDATE evenimente SET ora_inceput = ? WHERE id = ?')
            ->execute([date('H:i:s', time() - 20 * 60), $evenimentId]);

        verifica('la douăzeci de minute după început, butonul e acolo', true,
            str_contains(cere('/event.php?slug=tst-anul-ev', null, $cookie)['corp'],
                'data-anulare'));

        /* Înapoi în viitor, pentru restul probei. */
        db()->prepare('UPDATE evenimente SET data_eveniment = ?, ora_inceput = ? WHERE id = ?')
            ->execute([date('Y-m-d', strtotime('+6 days')), '18:00:00', $evenimentId]);

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

        /**
         * Ceasul, prin server. Evenimentul se mută cu DOUĂ ore în urmă — deci
         * dincolo de fereastra de o oră — iar cererea trebuie respinsă chiar
         * dacă tot ce ține de om e în regulă: e al lui, e conectat, token-ul e
         * bun, motivul e lung.
         *
         * Două ore, nu exact una: la fix o oră suntem pe muchia regulii, iar o
         * probă care stă pe muchie pică o dată la câteva rulări, când secunda
         * se schimbă între scriere și cerere.
         */
        db()->prepare('UPDATE evenimente SET stare_moderare = ?, data_eveniment = ?,
                              ora_inceput = ? WHERE id = ?')
            ->execute(['aprobat', date('Y-m-d'), date('H:i:s', time() - 2 * 3600), $evenimentId]);

        $inainte = is_file($logEmail) ? (int) filesize($logEmail) : 0;

        $r = cere('/api/anuleaza-eveniment.php', [
            'csrf'  => $token,
            'slug'  => 'tst-anul-ev',
            'motiv' => 'S-a stricat vremea și nu mai avem unde să ne adăpostim.',
        ], $cookie);

        verifica('evenimentul început nu se mai anulează', 409, $r['cod']);

        $stare->execute([$evenimentId]);
        verifica('și rămâne aprobat', 'aprobat', ($stare->fetch()['stare_moderare'] ?? ''));

        if (!empty($config['dezvoltare'])) {
            $nou = substr((string) file_get_contents($logEmail), $inainte);
            verifica('fără nicio veste plecată degeaba', false,
                str_contains($nou, SEMN . 'vine@invalid.local'));
        }

        /* Motivul prea scurt nu trece — și atunci nu se anulează nimic. */
        db()->prepare('UPDATE evenimente SET stare_moderare = ?, data_eveniment = ?,
                              ora_inceput = ? WHERE id = ?')
            ->execute(['aprobat', date('Y-m-d', strtotime('+6 days')), '18:00:00', $evenimentId]);

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
