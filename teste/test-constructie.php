<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — site-ul închis pentru lucrări.
 *
 * Cere BAZA DE DATE. Partea de API și de pagini cere și SERVERUL, dar se sare
 * singură dacă nu i se dă o adresă.
 *
 * Cum se rulează:
 *     php teste/test-constructie.php                        (fără server)
 *     php teste/test-constructie.php http://127.0.0.1:8099  (cu tot)
 *
 * ATENȚIE: partea cu serverul PORNEȘTE ȘI OPREȘTE `in_constructie` din
 * inc/config.php, fiindcă altfel n-ar avea ce verifica. Îl pune la loc cum
 * l-a găsit, și dacă pică ceva la mijloc — vezi curata() de la coadă.
 */

/* --------------------------- Doar din consolă -------------------------- */

/**
 * Probele nu se rulează din browser. `teste/.htaccess` le închide dosarul, dar
 * el se citește doar pe Apache cu AllowOverride pornit — verificarea asta ține
 * oriunde. Aceeași pereche de încuietori ca la cron/.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Se rulează doar din linia de comandă.\n");
}
require_once __DIR__ . '/../inc/constructie.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/google.php';   // pentru googleEsteConfigurat()

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

const SEMN = 'test-constr-';

/** Fișierul de setări, ca să putem porni și opri lacătul în timpul probei. */
$CALE_CONFIG = __DIR__ . '/../inc/config.php';
$CONFIG_INITIAL = file_get_contents($CALE_CONFIG);

function curata(): void
{
    global $CALE_CONFIG, $CONFIG_INITIAL;

    db()->prepare('DELETE FROM abonati_newsletter WHERE email LIKE ?')->execute([SEMN . '%']);
    db()->prepare('DELETE FROM membri WHERE email LIKE ?')->execute([SEMN . '%']);

    // Setările se pun ÎNAPOI cum au fost, orice s-ar fi întâmplat.
    if (is_string($CONFIG_INITIAL) && $CONFIG_INITIAL !== '') {
        file_put_contents($CALE_CONFIG, $CONFIG_INITIAL);
    }
}

curata();
register_shutdown_function('curata');

/** Pornește sau oprește lacătul în inc/config.php, pentru serverul de probă. */
/**
 * Scrie `in_constructie` în fișierul de setări.
 *
 * Serverul de probă citește setările la fiecare cerere, deci schimbarea se
 * simte imediat. Nu există alt fel de a proba un lacăt care atârnă de config.
 */
function lacat(bool $pornit): void
{
    global $CALE_CONFIG, $BAZA;

    $s = file_get_contents($CALE_CONFIG);
    $nou = preg_replace(
        "/'in_constructie'\s*=>\s*(?:true|false)\s*,/",
        "'in_constructie' => " . ($pornit ? 'true' : 'false') . ",",
        (string) $s,
        1
    );

    file_put_contents($CALE_CONFIG, $nou);
    clearstatcache();

    if ($BAZA === '') {
        return;
    }

    /**
     * Se AȘTEAPTĂ până când serverul chiar vede schimbarea.
     *
     * PHP ține fișierele compilate în OPcache și se uită dacă s-au schimbat pe
     * disc doar din două în două secunde (`opcache.revalidate_freq`). Deci,
     * imediat după scriere, serverul poate răspunde încă după setările vechi —
     * iar proba pica pe un lacăt care era pus, dar nu apucase să se audă.
     *
     * Nu e un `sleep(3)` pe ghicite: se întreabă un API ieftin până spune ce
     * trebuie. Când merge, trece imediat mai departe.
     */
    $asteptat = $pornit ? 503 : 200;

    for ($i = 0; $i < 50; $i++) {          // cel mult ~5 secunde
        $ctx = stream_context_create(['http' => ['ignore_errors' => true]]);
        @file_get_contents($BAZA . '/api/lista-evenimente.php', false, $ctx);

        foreach ($http_response_header ?? [] as $rand) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $rand, $m) === 1
                && (int) $m[1] === $asteptat) {
                return;
            }
        }

        usleep(100000);                    // 100 ms
    }

    echo "  (ATENȚIE: serverul n-a văzut schimbarea setărilor)\n";
}

/* ===================== 1. ADRESA DE E-MAIL ========================== */

sectiune('adresa de e-mail');

verifica('adresa goală nu trece', 'Avem nevoie și de adresa ta de e-mail.', verificaEmail('')['eroare']);
verifica('nici doar spații',      'Avem nevoie și de adresa ta de e-mail.', verificaEmail('   ')['eroare']);
verifica('nici altceva decât text', 'Avem nevoie și de adresa ta de e-mail.', verificaEmail(null)['eroare']);
verifica('una fără @',            'Adresa nu pare completă. Mai aruncă un ochi pe ea.', verificaEmail('ion')['eroare']);
verifica('una prea lungă', 'Adresa asta e neobișnuit de lungă. Mai aruncă-i un ochi.',
    verificaEmail(str_repeat('a', EMAIL_MAX) . '@x.ro')['eroare']);

verifica('una bună trece',   '',                verificaEmail('Ion@Email.ro')['eroare']);
verifica('și ajunge cu litere mici', 'ion@email.ro', verificaEmail('Ion@Email.ro')['email']);
verifica('cu spațiile tăiate',       'ion@email.ro', verificaEmail('  ion@email.ro  ')['email']);

/* ===================== 2. ÎNSCRIEREA LA VEȘTI ======================= */

sectiune('înscrierea la vești');

$adresa = SEMN . 'unu@invalid.local';

$r = inscrieLaVesti($adresa);
verifica('adresa bună intră pe listă', true, $r['ok']);
verifica('cu răspunsul cuvenit',       200,  $r['cod']);

$cate = db()->prepare('SELECT COUNT(*) FROM abonati_newsletter WHERE email = ?');
$cate->execute([$adresa]);
verifica('și chiar e în bază', 1, (int) $cate->fetchColumn());

// A doua oară: același răspuns, tot un singur rând.
$r = inscrieLaVesti($adresa);
verifica('a doua înscriere nu e o eroare', true, $r['ok']);
verifica('și spune exact același lucru',
    'Gata! Îți dăm de veste imediat ce deschidem.', $r['mesaj']);

$cate->execute([$adresa]);
verifica('dar rândul rămâne unul singur', 1, (int) $cate->fetchColumn());

// Litere mari = aceeași adresă, deci tot un rând.
inscrieLaVesti(mb_strtoupper($adresa, 'UTF-8'));
$cate->execute([$adresa]);
verifica('scrisă cu majuscule, tot ea', 1, (int) $cate->fetchColumn());

$r = inscrieLaVesti('nu-e-adresa');
verifica('o adresă stricată e refuzată', false, $r['ok']);
verifica('cu 422',                       422,   $r['cod']);

/* Limita pe IP se numără în tabelul propriu al funcției. */
$ip = ipBinar();

if ($ip !== null) {
    db()->prepare('DELETE FROM abonati_newsletter WHERE email LIKE ?')->execute([SEMN . '%']);

    for ($i = 0; $i < ABONARI_PE_ORA_PE_IP; $i++) {
        inscrieLaVesti(SEMN . 'lim' . $i . '@invalid.local');
    }

    $r = inscrieLaVesti(SEMN . 'peste@invalid.local');
    verifica('peste limita pe oră nu mai trece', false, $r['ok']);
    verifica('cu 429',                           429,   $r['cod']);
} else {
    echo "  (fără IP în CLI — limita pe oră s-a sărit)\n";
}

/* ===================== 3. UȘILE DESCHISE ============================ */

sectiune('ușile');

$usi = usileDeschiseInConstructie();

foreach (['constructie.php', 'login.php', 'iesire.php',
          'api/autentificare.php', 'api/newsletter.php',
          // A doua ușă de intrare: cine și-a făcut contul cu Google n-are
          // parolă la noi, deci fără ea n-ar avea pe unde intra deloc.
          'google.php',
          // Afișul cere o adresă de e-mail; documentele care spun ce facem
          // cu ea n-au voie să stea în spatele lacătului.
          'termeni.php', 'confidentialitate.php', 'cookies.php'] as $usa) {
    verifica('„' . $usa . '" e deschisă', true, in_array($usa, $usi, true));
}

foreach (['index.php', 'event.php', 'contact.php', 'profil.php',
          'api/inregistrare.php', 'api/lista-evenimente.php',
          // Drumul lui Google e deschis până unde se INTRĂ; de unde încolo s-ar
          // face un cont NOU, rămâne închis.
          'finalizare.php', 'api/finalizare-google.php'] as $inchisa) {
    verifica('„' . $inchisa . '" NU e deschisă', false, in_array($inchisa, $usi, true));
}

// Fiecare ușă din listă trebuie să existe pe disc. O cale scrisă greșit ar fi
// însemnat o ușă zidită, descoperită abia în ziua în care se pune lacătul.
foreach ($usi as $usa) {
    verifica('„' . $usa . '" chiar există', true,
        is_file(__DIR__ . '/../' . $usa));
}

/* Din linia de comandă nu se pune niciun lacăt: cronurile nu sunt vizitatori. */
verifica('din CLI nu se pune lacăt', false, cerereDinBrowser());

/* ================= 3a. COMUTATORUL DIN ADMIN ======================== */

sectiune('comutatorul de șantier');

/**
 * DOUĂ LACĂTE, iar cel din setări e mai tare.
 *
 * Comutatorul din admin scrie un fișier gol în private/; linia din config
 * rămâne cheia de rezervă, cea care merge chiar dacă discul e doar de citit.
 * Cât e ea pusă, comutatorul n-are ce comuta — și pagina spune asta pe față,
 * fiindcă un buton care nu face nimic e mai rău decât unul care lipsește.
 *
 * Proba pornește de la site DESCHIS din config: curata() pune fișierul de
 * setări la loc oricum, iar lacătul de fișier îl stingem noi aici.
 */
puneLacatul(false);

verifica('la început, deschis',       false, siteInConstructie());
verifica('și nu din setări',          false, lacatulEDinSetari());

verifica('comutatorul închide',       true,  puneLacatul(true));
verifica('și site-ul chiar e închis', true,  siteInConstructie());
verifica('dar nu din setări',         false, lacatulEDinSetari());
verifica('semnul e pe disc',          true,  is_file(FISIER_CONSTRUCTIE));

/* A doua apăsare pe „închide" nu e o eroare: era deja închis. */
verifica('închis de două ori, tot bine', true, puneLacatul(true));

verifica('comutatorul deschide',      true,  puneLacatul(false));
verifica('și site-ul e deschis',      false, siteInConstructie());
verifica('semnul a plecat de pe disc', false, is_file(FISIER_CONSTRUCTIE));

/* Și deschis de două ori, la fel: nu se plânge că nu era nimic de șters. */
verifica('deschis de două ori, tot bine', true, puneLacatul(false));

/* ================= 3b. PERMISUL DE DISPOZITIV ======================= */

sectiune('permisul de dispozitiv');

/**
 * CE PĂZEȘTE, mai presus de orice: că permisul NU SE POATE SCRIE DE MÂNĂ.
 *
 * E singurul lucru de pe site care deschide o ușă închisă fără să întrebe cine
 * ești. Dacă s-ar putea ticlui un cookie care trece, lacătul n-ar mai fi lacăt
 * — iar asta nu s-ar vedea nicăieri: site-ul ar arăta exact la fel.
 */
$maine = time() + 86400;
$bun   = permisSantier($maine);

/* Fără cookie, dispozitivul nu e cunoscut. */
unset($_COOKIE[COOKIE_SANTIER]);
verifica('fără permis, necunoscut', false, dispozitivCunoscut());

$_COOKIE[COOKIE_SANTIER] = $bun;
verifica('cu permisul bun, cunoscut', true, dispozitivCunoscut());

/* SEMNĂTURA. Un permis cu ceasul bun, dar semnat aiurea, nu trece. */
$_COOKIE[COOKIE_SANTIER] = $maine . '.' . str_repeat('a', 64);
verifica('semnătură inventată, respinsă', false, dispozitivCunoscut());

/* Nici unul fără semnătură deloc. */
$_COOKIE[COOKIE_SANTIER] = (string) $maine;
verifica('fără semnătură, respins', false, dispozitivCunoscut());

/**
 * CEASUL. Semnătura e a clipei de expirare, deci nu se poate lua un permis
 * bun și împinge data mai încolo: semnătura n-ar mai fi a ei.
 */
$_COOKIE[COOKIE_SANTIER] = permisSantier(time() - 60);
verifica('permis expirat, respins', false, dispozitivCunoscut());

[$candExpira, $semnatura] = explode('.', $bun, 2);
$_COOKIE[COOKIE_SANTIER] = ((int) $candExpira + 999999) . '.' . $semnatura;
verifica('data împinsă, semnătura nu ține', false, dispozitivCunoscut());

/* Și gunoiul curat, care nici măcar nu seamănă. */
foreach (['', '.', 'abc', 'nuecifra.abc', '123'] as $gunoi) {
    $_COOKIE[COOKIE_SANTIER] = $gunoi;
    verifica('gunoi respins: „' . $gunoi . '"', false, dispozitivCunoscut());
}

/**
 * CINE TRECE DE LACĂT: staff-ul SAU dispozitivul cunoscut.
 *
 * Aceeași funcție o cer TREI locuri — lacătul de pe pagini și cele două uși de
 * intrare în cont (api/autentificare.php, google.php). Scrisă de mână în
 * vreuna, ieșea o casă în care puteai deschide paginile de pe dispozitivul
 * tău, dar nu te puteai conecta cu un cont de probă ca să le vezi.
 */
unset($_COOKIE[COOKIE_SANTIER]);
$deProba  = ['id' => 1, 'este_staff' => 0];
$alCasei  = ['id' => 1, 'este_staff' => 1];

verifica('fără nimic, nu trece',        false, poateIntraInConstructie($deProba));
verifica('omul de casă trece',          true,  poateIntraInConstructie($alCasei));

$_COOKIE[COOKIE_SANTIER] = $bun;
verifica('și un cont de probă, de pe dispozitivul cunoscut', true,
    poateIntraInConstructie($deProba));

unset($_COOKIE[COOKIE_SANTIER]);

/* ===================== 3b. CINE E OM DE CASĂ ======================== */

sectiune('cine e om de casă');

/**
 * Staff înseamnă ORICE valoare în afară de 0 — nu neapărat 1.
 *
 * Coloana se pune de mână, din phpMyAdmin. Cine scrie acolo un 2 pentru
 * „administrator" se așteaptă să fie tot om de casă, nu să cadă înapoi printre
 * vizitatori fără să afle de ce.
 */
verifica('0 nu e staff',        false, esteStaff(['id' => 1, 'este_staff' => 0]));
verifica('„0" ca text, la fel', false, esteStaff(['id' => 1, 'este_staff' => '0']));
verifica('1 e staff',           true,  esteStaff(['id' => 1, 'este_staff' => 1]));
verifica('2 e tot staff',       true,  esteStaff(['id' => 1, 'este_staff' => 2]));
verifica('și „1" ca text',      true,  esteStaff(['id' => 1, 'este_staff' => '1']));

/**
 * Rândul fără coloană nu se mai ghicește: se citește din bază, după id.
 *
 * Aici era buba care închidea ușa omului de casă. Interogarea de la intrare nu
 * cerea `este_staff`, iar `?? 0` lua lipsa drept „nu e staff" și tăcea: parolă
 * bună, cont bun, dar tratat ca un vizitator oarecare.
 */
$idStaff = (int) db()->query(
    'SELECT id FROM membri WHERE este_staff <> 0 ORDER BY id LIMIT 1'
)->fetchColumn();

$idOm = (int) db()->query(
    'SELECT id FROM membri WHERE este_staff = 0 ORDER BY id LIMIT 1'
)->fetchColumn();

if ($idStaff > 0 && $idOm > 0) {
    verifica('rândul fără coloană se lămurește din bază (staff)',
        true, esteStaff(['id' => $idStaff]));
    verifica('și pentru cine nu e staff',
        false, esteStaff(['id' => $idOm]));
} else {
    echo "  (n-am găsit în bază și staff, și ne-staff — s-a sărit)\n";
}

verifica('fără id și fără coloană, nu e staff', false, esteStaff(['nume' => 'X']));
verifica('rândul gol nu e staff',               false, esteStaff([]));

/* ===================== 4. PRIN SERVER =============================== */

if ($BAZA === '') {
    echo "\n(partea cu serverul s-a sărit — dă o adresă ca s-o rulezi:"
       . " php teste/test-constructie.php http://127.0.0.1:8099)\n";
} else {
    sectiune('prin server');

    /** O cerere care ține minte cookie-urile și NU urmează redirecționările. */
    function cere(string $cale, ?array $trup = null, string $cookie = '', bool $urmeaza = false): array
    {
        global $BAZA;

        $ctx = [
            'http' => [
                'method'          => $trup === null ? 'GET' : 'POST',
                'header'          => "Content-Type: application/json\r\n"
                                   . ($cookie !== '' ? "Cookie: $cookie\r\n" : ''),
                'content'         => $trup === null ? '' : json_encode($trup),
                'ignore_errors'   => true,
                'follow_location' => $urmeaza ? 1 : 0,
                'max_redirects'   => $urmeaza ? 5 : 1,
            ],
        ];

        $raspuns = @file_get_contents($BAZA . $cale, false, stream_context_create($ctx));
        $cod = 0; $unde = ''; $cookieNou = $cookie;
        $antete = $http_response_header ?? [];

        foreach ($antete as $rand) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $rand, $m) === 1)      { $cod = (int) $m[1]; }
            if (preg_match('/^Location:\s*(.+)$/i', $rand, $m) === 1)  { $unde = trim($m[1]); }
            if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $rand, $m) === 1) { $cookieNou = $m[1]; }
        }

        return ['cod' => $cod, 'corp' => (string) $raspuns, 'unde' => $unde,
                'cookie' => $cookieNou, 'antete' => $antete];
    }

    /* --- doi oameni: unul obișnuit, unul de casă --- */

    $PAROLA = 'ParolaDeProba#2026';

    $faMembru = function (string $cheie, bool $staff) use ($PAROLA): void {
        db()->prepare(
            'INSERT INTO membri (permalink, nume, prenume, email, sex, data_nasterii,
                                 parola_hash, stare, este_staff, creat_la, confirmat_la)
             VALUES (?,?,?,?,\'M\',\'1990-01-01\',?,\'activ\',?,?,?)'
        )->execute([
            substr('tstc-' . $cheie, 0, 16), 'Popa', 'Dan',
            SEMN . $cheie . '@invalid.local',
            password_hash($PAROLA, PASSWORD_DEFAULT),
            $staff ? 1 : 0, acum(), acum(),
        ]);
    };

    $faMembru('om',  false);
    $faMembru('sef', true);

    /* --- cu lacătul PUS --- */

    lacat(true);

    $r = cere('/index.php');
    verifica('prima pagină trimite la afiș', 302, $r['cod']);
    verifica('și anume acolo', true, str_contains($r['unde'], 'constructie.php'), );

    foreach (['/eveniment/x', '/contact.php', '/despre.php', '/profil.php'] as $cale) {
        $r = cere($cale);
        verifica('„' . $cale . '" e închisă', 302, $r['cod']);
    }

    // API-urile primesc JSON, nu o redirecționare.
    $r = cere('/api/lista-evenimente.php');
    verifica('API-ul public răspunde 503', 503, $r['cod']);
    verifica('și spune de ce', true,
        str_contains($r['corp'], 'în lucru'), mb_substr($r['corp'], 0, 60));

    // Gaura de altădată: singurul API care nu trecea prin auth.php.
    $r = cere('/api/inregistrare.php', ['ceva' => 1]);
    verifica('nici conturi noi nu se fac', 503, $r['cod']);

    // Ușile deschise.
    $r = cere('/constructie.php');
    verifica('afișul se deschide',        503, $r['cod']);
    verifica('și scrie ce trebuie', true, str_contains($r['corp'], 'Se pregătește ceva tare'));
    verifica('cu formularul de vești', true, str_contains($r['corp'], 'data-newsletter'));

    /* ---------------- ANTETELE DE SIGURANȚĂ, PE AFIȘ ---------------- */

    /**
     * Afișul e SINGURA pagină care nu trece prin inc/antet.php, deci singura
     * care putea rămâne fără antete de siguranță fără să bage nimeni de seamă.
     * Și e tocmai pagina care se vede cât e site-ul închis, adică singura pe
     * care o vede lumea în perioada în care e cel mai probabil să se lucreze la
     * cod.
     */
    $antetul = static function (array $antete, string $nume): string {
        foreach ($antete as $rand) {
            if (preg_match('/^' . preg_quote($nume, '/') . ':\s*(.+)$/i', $rand, $m) === 1) {
                return trim($m[1]);
            }
        }
        return '';
    };

    $cspAfis = $antetul($r['antete'], 'Content-Security-Policy');

    verifica('afișul are politică de conținut', true, $cspAfis !== '', $cspAfis);
    verifica('care pornește de la „doar de la noi"', true,
        str_contains($cspAfis, "default-src 'self'"));
    verifica('fără scripturi străine',  true, str_contains($cspAfis, "script-src 'self'"));
    verifica('și fără cadre',           true, str_contains($cspAfis, "frame-ancestors 'none'"));

    /**
     * POZELE AU VOIE ȘI DIN `blob:`, iar rândul ăsta e aici fiindcă lipsa lui a
     * rupt deja site-ul în tăcere.
     *
     * Poza de profil (poza.php) și coperta de eveniment (adauga_eveniment.php)
     * îi arată omului ce a ales, ca s-o potrivească în ramă înainte de a o
     * trimite. Felul în care i-o arată e URL.createObjectURL() →
     * `<img src="blob:…">`. Cu `img-src 'self' data:` — fără `blob:` — browserul
     * refuza poza, `onerror` se aprindea, iar omul primea „Nu am putut deschide
     * fișierul" și „Fișierul nu pare o poză" la ORICE poză, oricât de bună.
     * Nimic nu ajungea pe server: se rupea în browser, înainte de trimitere.
     *
     * NU E O PORTIȚĂ CĂTRE AFARĂ: o adresă „blob:" nu se naște decât din
     * scriptul nostru, pe fișierul pe care l-a ales omul însuși.
     */
    verifica('pozele alese de om (blob:) au voie', true,
        preg_match('/img-src[^;]*\bblob:/', $cspAfis) === 1, $cspAfis);
    verifica('și cele desenate în cod (data:)',   true,
        preg_match('/img-src[^;]*\bdata:/', $cspAfis) === 1);
    verifica('dar nu de pe orice server',         false,
        preg_match('/img-src[^;]*\*/', $cspAfis) === 1);
    verifica('cu X-Frame-Options',   'DENY', $antetul($r['antete'], 'X-Frame-Options'));
    verifica('și cu nosniff',     'nosniff', $antetul($r['antete'], 'X-Content-Type-Options'));

    /**
     * Scriptul care pune tema, singurul scris în pagină de pe tot site-ul,
     * poartă o cifră de unică folosință — iar cifra din antet și cea din pagină
     * trebuie să fie ACEEAȘI. Dacă s-ar despărți vreodată, scriptul n-ar mai
     * rula și site-ul ar clipi alb la fiecare încărcare pe temă întunecată.
     *
     * Se cere de pe pagina de intrare, care e deschisă și în lacăt.
     */
    $rl = cere('/login.php');
    $cspLogin = $antetul($rl['antete'], 'Content-Security-Policy');

    preg_match("/'nonce-([^']+)'/", $cspLogin, $mn);
    $cifraDinAntet = $mn[1] ?? '';

    preg_match('/<script nonce="([^"]+)"/', $rl['corp'], $mp);
    $cifraDinPagina = $mp[1] ?? '';

    verifica('antetul poartă o cifră',      true, $cifraDinAntet !== '');
    verifica('și scriptul temei o poartă pe aceeași',
        $cifraDinAntet, $cifraDinPagina);

    /**
     * Cifra e de UNICĂ folosință: la a doua cerere trebuie să fie alta. Dacă ar
     * fi mereu aceeași, cine ar reuși vreodată să strecoare un `<script>` în
     * pagină ar putea s-o scrie în el, și politica n-ar mai apăra nimic.
     */
    $cspAlDoilea = $antetul(cere('/login.php')['antete'], 'Content-Security-Policy');
    preg_match("/'nonce-([^']+)'/", $cspAlDoilea, $mn2);

    verifica('iar la a doua cerere e alta', false, ($mn2[1] ?? '') === $cifraDinAntet);

    $r = cere('/login.php');
    verifica('intrarea în cont e deschisă', 200, $r['cod']);
    verifica('dar fără înregistrare', false, str_contains($r['corp'], 'id="panel-register"'));
    verifica('spune că se lucrează', true, str_contains($r['corp'], 'Site-ul e în lucru'));

    /**
     * Formularul trebuie să trimită prin POST, nu prin GET.
     *
     * Fără `method="post"`, o cădere a JS-ului îl lasă pe browser să-l trimită
     * cum știe el — adică prin GET, cu PAROLA ÎN BARA DE ADRESE, de unde ajunge
     * în istoric, în logurile serverului și în Referer. S-a și întâmplat, când
     * pagina a rămas fără taburi și blocul de JS n-a mai pornit.
     */
    verifica('formularul de intrare trimite prin POST', true,
        (bool) preg_match('/<form[^>]*id="login-form"[^>]*method="post"/i', $r['corp']));

    /**
     * Butonul de Google rămâne pe pagină și cât e site-ul în lucru: omul de
     * casă care și-a făcut contul prin el n-are parolă la noi, deci fără
     * butonul ăsta n-ar avea pe unde intra deloc.
     *
     * Se arată doar dacă Google e configurat în config.php — dacă nu e, nu se
     * arată nicăieri pe site, și nu asta se verifică aici.
     */
    if (googleEsteConfigurat()) {
        verifica('butonul de Google e la locul lui', true,
            str_contains($r['corp'], 'google.php'));
    } else {
        echo "  (Google nu e configurat în config.php — butonul s-a sărit)\n";
    }

    // Ușa lui e deschisă în lacăt: nu răspunde cu redirecționarea spre afiș.
    $r = cere('/google.php');
    verifica('google.php nu e trimis la afiș', true,
        !str_contains($r['unde'], 'constructie.php'), $r['cod'] . ' → ' . $r['unde']);

    // Dar cele care FAC un cont nou rămân închise.
    $r = cere('/finalizare.php');
    verifica('finalizarea unui cont nou e închisă', 302, $r['cod']);
    verifica('și duce la afiș', true, str_contains($r['unde'], 'constructie.php'));

    $r = cere('/api/finalizare-google.php', ['ceva' => 1]);
    verifica('și API-ul ei, la fel', 503, $r['cod']);

    /* --- intrarea în cont, cu lacătul pus --- */

    $pagina = cere('/login.php');
    $cookie = $pagina['cookie'];
    preg_match('/name="csrf" value="([^"]+)"/', $pagina['corp'], $m);
    $token = $m[1] ?? '';

    // Cine NU e staff: parola bună, dar ușa rămâne închisă.
    $r = cere('/api/autentificare.php', [
        'csrf'   => $token,
        'email'  => SEMN . 'om@invalid.local',
        'parola' => $PAROLA,
    ], $cookie);

    $corp = json_decode($r['corp'], true) ?: [];

    verifica('cine nu e staff nu intră',    503,   $r['cod']);
    verifica('și i se spune de ce',         'in_constructie', $corp['stare'] ?? '');
    verifica('e trimis înapoi la afiș',     '/constructie.php', $corp['redirect'] ?? '');
    verifica('iar sesiune nu se face',      false, $corp['ok'] ?? true);

    // Și chiar nu s-a făcut: cu același cookie, prima pagină tot îl respinge.
    $r = cere('/index.php', null, $r['cookie']);
    verifica('deci prima pagină tot nu-l lasă', 302, $r['cod']);

    /**
     * DAR DE PE UN DISPOZITIV CUNOSCUT, ACELAȘI CONT INTRĂ — și ăsta e tot
     * rostul permisului.
     *
     * Fără el, omul de casă putea deschide paginile de pe dispozitivul lui,
     * dar nu se putea CONECTA cu un cont de probă ca să vadă site-ul cu ochii
     * unui om obișnuit: ușa de la intrarea în cont întreba doar `esteStaff()`.
     * Un site închis pe care nu-l poți încerca decât ca administrator nu e
     * închis pentru lucrări, e închis pentru lucru.
     *
     * Permisul se face AICI, în probă, cu aceeași funcție pe care o folosește
     * și serverul: cheia iese din inc/config.php, deci amândoi ajung la aceeași
     * semnătură. Dacă asta ar înceta să fie adevărat, proba pică — și e bine.
     */
    $pagina  = cere('/login.php');
    $cookie  = $pagina['cookie'];
    preg_match('/name="csrf" value="([^"]+)"/', $pagina['corp'], $m);
    $token   = $m[1] ?? '';
    $permis  = COOKIE_SANTIER . '=' . permisSantier(time() + 86400);

    $r = cere('/api/autentificare.php', [
        'csrf'   => $token,
        'email'  => SEMN . 'om@invalid.local',
        'parola' => $PAROLA,
    ], $cookie . '; ' . $permis);

    $corp = json_decode($r['corp'], true) ?: [];

    verifica('de pe dispozitiv cunoscut, intră și cine nu e staff', true, $corp['ok'] ?? false);

    /* Și chiar vede site-ul, nu doar formularul de intrare. */
    $r = cere('/index.php', null, $r['cookie'] . '; ' . $permis);
    verifica('și prima pagină i se deschide', 200, $r['cod']);

    /**
     * DAR UN PERMIS TICLUIT NU DESCHIDE NIMIC. Aici e miezul: dacă s-ar putea
     * scrie un cookie de mână, lacătul n-ar mai fi lacăt, iar asta nu s-ar
     * vedea din afară — site-ul ar arăta exact la fel.
     */
    $r = cere('/index.php', null,
        COOKIE_SANTIER . '=' . (time() + 86400) . '.' . str_repeat('a', 64));
    verifica('un permis inventat nu deschide nimic', 302, $r['cod']);

    // Omul de casă intră, și de la el încolo site-ul e deschis.
    $pagina = cere('/login.php');
    $cookie = $pagina['cookie'];
    preg_match('/name="csrf" value="([^"]+)"/', $pagina['corp'], $m);
    $token = $m[1] ?? '';

    $r = cere('/api/autentificare.php', [
        'csrf'   => $token,
        'email'  => SEMN . 'sef@invalid.local',
        'parola' => $PAROLA,
    ], $cookie);

    $corp = json_decode($r['corp'], true) ?: [];
    verifica('omul de casă intră', true, $corp['ok'] ?? false);

    $cookieSef = $r['cookie'];

    $r = cere('/index.php', null, $cookieSef);
    verifica('și vede prima pagină', 200, $r['cod']);

    $r = cere('/api/lista-evenimente.php', null, $cookieSef);
    verifica('și API-urile îi răspund', 200, $r['cod']);

    $r = cere('/constructie.php', null, $cookieSef);
    verifica('iar afișul îl trimite înăuntru', 302, $r['cod']);

    /* --- înscrierea la vești, prin API --- */

    $pagina = cere('/constructie.php');
    preg_match('/name="csrf" value="([^"]+)"/', $pagina['corp'], $m);
    $tokenNl = $m[1] ?? '';
    $cookieNl = $pagina['cookie'];

    $r = cere('/api/newsletter.php', [
        'csrf'  => $tokenNl,
        'email' => SEMN . 'prin-api@invalid.local',
    ], $cookieNl);

    $corp = json_decode($r['corp'], true) ?: [];
    verifica('adresa se lasă și fără cont', true, $corp['ok'] ?? false);

    $q = db()->prepare('SELECT COUNT(*) FROM abonati_newsletter WHERE email = ?');
    $q->execute([SEMN . 'prin-api@invalid.local']);
    verifica('și ajunge în bază', 1, (int) $q->fetchColumn());

    // Fără token nu trece.
    $r = cere('/api/newsletter.php', ['email' => SEMN . 'fara-token@invalid.local'], $cookieNl);
    verifica('fără token nu se scrie nimic', 419, $r['cod']);

    // Capcana pentru roboți: „ok", dar nimic în bază.
    $r = cere('/api/newsletter.php', [
        'csrf'    => $tokenNl,
        'email'   => SEMN . 'robot@invalid.local',
        'website' => 'http://spam.example',
    ], $cookieNl);

    $corp = json_decode($r['corp'], true) ?: [];
    verifica('robotul pleacă mulțumit', true, $corp['ok'] ?? false);

    $q->execute([SEMN . 'robot@invalid.local']);
    verifica('dar adresa lui nu intră', 0, (int) $q->fetchColumn());

    /* --- cu lacătul LUAT --- */

    lacat(false);

    $r = cere('/index.php');
    verifica('fără lacăt, prima pagină se deschide', 200, $r['cod']);

    $r = cere('/api/lista-evenimente.php');
    verifica('și API-ul public la fel', 200, $r['cod']);

    $r = cere('/constructie.php');
    verifica('iar afișul nu mai are ce spune', 302, $r['cod']);
    verifica('și trimite pe prima pagină', true, str_contains($r['unde'], 'index.php'));

    $r = cere('/login.php');
    verifica('înregistrarea se vede iar', true, str_contains($r['corp'], 'id="panel-register"'));

    // Cine nu e staff intră ca de obicei.
    $pagina = cere('/login.php');
    $cookie = $pagina['cookie'];
    preg_match('/name="csrf" value="([^"]+)"/', $pagina['corp'], $m);

    $r = cere('/api/autentificare.php', [
        'csrf'   => $m[1] ?? '',
        'email'  => SEMN . 'om@invalid.local',
        'parola' => $PAROLA,
    ], $cookie);

    $corp = json_decode($r['corp'], true) ?: [];
    verifica('și oricine intră iar în cont', true, $corp['ok'] ?? false);
}

/* ============================= GATA ================================== */

echo "\n" . str_repeat('=', 60) . "\n";
echo "TOTAL: $treceri trecute, $picaturi picate\n";

exit($picaturi > 0 ? 1 : 0);
