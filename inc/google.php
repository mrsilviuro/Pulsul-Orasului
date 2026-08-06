<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — intrarea cu Google.
 *
 * ---------------------------------------------------------------------------
 *  CUM MERGE, ÎN CUVINTE SIMPLE
 * ---------------------------------------------------------------------------
 *
 *  1. Omul apasă „Continuă cu Google" și e trimis pe google.com, cu o cerere
 *     de forma „spune-mi cine e ăsta, dacă e de acord".
 *  2. Se autentifică acolo, la ei. Noi nu-i vedem niciodată parola — ăsta e
 *     tot rostul.
 *  3. Google îl trimite înapoi la noi cu un COD scurt, prin adresa din bara
 *     browserului.
 *  4. Serverul nostru sună la Google, de la server la server, și schimbă codul
 *     pe datele omului. Pasul ăsta se face cu „secretul" nostru, pe care doar
 *     serverul îl știe.
 *
 *  Se numește „authorization code flow". Există și o variantă mai scurtă, cu
 *  butonul desenat de Google și un script încărcat de la ei — nu o folosim:
 *  ne-ar impune aspectul lor și ne-ar aduce cod străin în pagină, iar de
 *  amândouă am stat departe peste tot în proiect.
 *
 * ---------------------------------------------------------------------------
 *  DE CE ATÂTEA VERIFICĂRI
 * ---------------------------------------------------------------------------
 *
 *  Pasul 3 se întâmplă prin bara de adrese, adică prin mâinile vizitatorului.
 *  Acolo poate ajunge orice. De aceea:
 *
 *  - „state" e un șir aleatoriu pe care îl ținem în sesiune și îl cerem înapoi
 *    la întoarcere. Fără el, cineva ți-ar putea trimite un link care te
 *    conectează în contul LUI, fără să-ți dai seama — și tot ce faci apoi
 *    (poze, comentarii) ajunge acolo. Se cheamă CSRF pe autentificare.
 *
 *  - PKCE: trimitem la Google amprenta unui secret de unică folosință, iar la
 *    schimbul codului trimitem secretul întreg. Dacă cineva reușește să fure
 *    codul din bara de adrese, nu-i folosește la nimic fără secretul care a
 *    rămas la noi în sesiune.
 *
 *  - „state" se folosește o singură dată: se șterge din sesiune imediat ce a
 *    fost verificat, ca același link să nu poată fi refolosit.
 */

require_once __DIR__ . '/bootstrap.php';

/** Adresele Google, ca să nu fie scrise prin cod. */
const GOOGLE_AUTORIZARE = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_TOKEN      = 'https://oauth2.googleapis.com/token';

/** Cine are voie să semneze token-urile. */
const GOOGLE_EMITENTI = ['https://accounts.google.com', 'accounts.google.com'];

/* ========================= E CONFIGURAT? ============================== */

/**
 * Avem datele de la Google?
 *
 * Cât timp nu le avem, butoanele nici nu se tipăresc în pagină. Așa site-ul
 * merge normal înainte de a-ți face contul de Google, iar nimeni nu apasă un
 * buton care oricum ar da eroare.
 */
function googleEsteConfigurat(): bool
{
    global $config;

    return trim((string) ($config['google_client_id'] ?? '')) !== ''
        && trim((string) ($config['google_client_secret'] ?? '')) !== '';
}

/** Adresa la care Google trimite omul înapoi. Trebuie scrisă identic la ei. */
function googleAdresaDeIntoarcere(): string
{
    global $config;
    return rtrim((string) ($config['url_site'] ?? ''), '/') . '/google.php';
}

/* ======================== PLECAREA SPRE GOOGLE ========================= */

/**
 * Construiește adresa spre Google și pregătește sesiunea.
 *
 * $inapoiLa — unde vrem să ajungă omul după ce intră în cont.
 */
function googleAdresaDePlecare(string $inapoiLa = ''): string
{
    global $config;

    pornesteSesiunea();

    // Șirul care leagă plecarea de întoarcere.
    $state = bin2hex(random_bytes(32));

    /**
     * PKCE: un secret de unică folosință.
     *
     * Spre Google pleacă doar amprenta lui (SHA-256, scrisă în base64url).
     * Secretul întreg rămâne în sesiune și e trimis abia la schimbul codului,
     * de la server la server.
     */
    $verificator = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $amprenta    = rtrim(strtr(base64_encode(hash('sha256', $verificator, true)), '+/', '-_'), '=');

    $_SESSION['google_state']       = $state;
    $_SESSION['google_verificator'] = $verificator;
    $_SESSION['google_inapoi_la']   = $inapoiLa;
    $_SESSION['google_pornit_la']   = time();

    $parametri = [
        'client_id'             => (string) $config['google_client_id'],
        'redirect_uri'          => googleAdresaDeIntoarcere(),
        'response_type'         => 'code',

        // Cerem strictul necesar: cine e și ce adresă are. Nimic altceva.
        // Cu cât ceri mai puțin, cu atât mai puțini oameni se răzgândesc în
        // fața ecranului de acceptare.
        'scope'                 => 'openid email profile',

        'state'                 => $state,
        'code_challenge'        => $amprenta,
        'code_challenge_method' => 'S256',

        // Ecranul de alegere a contului apare de fiecare dată. Fără asta,
        // cine are mai multe conturi de Google intră mereu cu primul și nu
        // înțelege de ce.
        'prompt'                => 'select_account',
    ];

    $adresa = (string) ($config['google_url_autorizare'] ?? GOOGLE_AUTORIZARE);

    return $adresa . '?' . http_build_query($parametri);
}

/* ======================== ÎNTOARCEREA DE LA GOOGLE ===================== */

/**
 * Verifică „state"-ul primit înapoi.
 *
 * Întoarce un mesaj de eroare, sau '' dacă totul e în regulă. Indiferent de
 * rezultat, datele din sesiune se șterg: sunt bune o singură dată.
 */
function googleVerificaState(string $primit): string
{
    pornesteSesiunea();

    $asteptat = (string) ($_SESSION['google_state'] ?? '');
    $pornit   = (int) ($_SESSION['google_pornit_la'] ?? 0);

    // Se șterg din prima, orice ar urma. Un link folosit o dată nu mai merge.
    unset($_SESSION['google_state'], $_SESSION['google_pornit_la']);

    if ($asteptat === '' || $primit === '') {
        return 'Cererea nu mai e valabilă. Încearcă din nou de pe pagina de cont.';
    }

    if (!hash_equals($asteptat, $primit)) {
        return 'Cererea nu vine de unde trebuie. Încearcă din nou de pe pagina de cont.';
    }

    // Un drum dus-întors durează un minut, nu o oră.
    if ($pornit > 0 && (time() - $pornit) > 900) {
        return 'A trecut prea mult timp. Încearcă din nou.';
    }

    return '';
}

/**
 * Schimbă codul primit pe datele omului.
 *
 * Întoarce ['ok' => true, 'om' => [...]] sau ['ok' => false, 'mesaj' => ...].
 */
function googleSchimbaCodul(string $cod): array
{
    global $config;

    pornesteSesiunea();
    $verificator = (string) ($_SESSION['google_verificator'] ?? '');
    unset($_SESSION['google_verificator']);

    if ($cod === '' || $verificator === '') {
        return ['ok' => false, 'mesaj' => 'Cererea nu mai e valabilă. Încearcă din nou.'];
    }

    if (!function_exists('curl_init')) {
        error_log('PulsulOrasului: lipsește extensia curl, intrarea cu Google nu poate funcționa.');
        return ['ok' => false, 'mesaj' => 'Serverul nu poate vorbi cu Google. Am fost înștiințați.'];
    }

    $raspuns = googleCerere((string) ($config['google_url_token'] ?? GOOGLE_TOKEN), [
        'code'          => $cod,
        'client_id'     => (string) $config['google_client_id'],
        'client_secret' => (string) $config['google_client_secret'],
        'redirect_uri'  => googleAdresaDeIntoarcere(),
        'grant_type'    => 'authorization_code',
        'code_verifier' => $verificator,
    ]);

    if (!$raspuns['ok']) {
        return $raspuns;
    }

    $date = $raspuns['date'];

    if (empty($date['id_token']) || !is_string($date['id_token'])) {
        error_log('PulsulOrasului: Google nu a trimis id_token.');
        return ['ok' => false, 'mesaj' => 'Google nu ne-a trimis datele așteptate. Încearcă din nou.'];
    }

    return googleCitesteToken($date['id_token']);
}

/** Cererea propriu-zisă către Google, de la server la server. */
function googleCerere(string $adresa, array $campuri): array
{
    $ch = curl_init($adresa);

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($campuri),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,

        // Certificatul se verifică. Fără asta, cine stă pe traseu ar putea
        // răspunde în locul lui Google.
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,

        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);

    $text = curl_exec($ch);
    $cod  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $eroareCurl = curl_error($ch);
    curl_close($ch);

    if ($text === false) {
        error_log('PulsulOrasului: cererea către Google a dat greș — ' . $eroareCurl);
        return ['ok' => false, 'mesaj' => 'Nu am putut lua legătura cu Google. Încearcă din nou.'];
    }

    $date = json_decode((string) $text, true);

    if (!is_array($date)) {
        error_log('PulsulOrasului: răspuns neînțeles de la Google (HTTP ' . $cod . ').');
        return ['ok' => false, 'mesaj' => 'Google a răspuns ceva ce nu am înțeles. Încearcă din nou.'];
    }

    if ($cod < 200 || $cod >= 300) {
        // Mesajul lor ajunge în log, nu în pagină: poate conține detalii
        // despre setările noastre.
        error_log('PulsulOrasului: Google a refuzat schimbul (HTTP ' . $cod . ') — '
                . ($date['error'] ?? '?') . ': ' . ($date['error_description'] ?? ''));
        return ['ok' => false, 'mesaj' => 'Google a refuzat cererea. Încearcă din nou.'];
    }

    return ['ok' => true, 'date' => $date];
}

/**
 * Citește datele din „id_token" și le verifică.
 *
 * Token-ul e un JWT: trei bucăți despărțite de puncte, dintre care a doua
 * conține datele, scrise în base64url.
 *
 * NU îi verificăm semnătura, și e în regulă așa: token-ul nu vine prin
 * browser, ci direct de la Google, printr-o legătură HTTPS pe care tocmai am
 * verificat-o (CURLOPT_SSL_VERIFYPEER). Însuși Google scrie în documentație că
 * pentru fluxul ăsta verificarea semnăturii nu mai e necesară. Verificăm în
 * schimb ce scrie înăuntru — pentru cine, de la cine, până când.
 */
function googleCitesteToken(string $token): array
{
    global $config;

    $bucati = explode('.', $token);

    if (count($bucati) !== 3) {
        return ['ok' => false, 'mesaj' => 'Datele primite de la Google nu arată cum trebuie.'];
    }

    $brut = base64_decode(strtr($bucati[1], '-_', '+/') . str_repeat('=', (4 - strlen($bucati[1]) % 4) % 4), true);
    $date = $brut === false ? null : json_decode($brut, true);

    if (!is_array($date)) {
        return ['ok' => false, 'mesaj' => 'Datele primite de la Google nu au putut fi citite.'];
    }

    // Pentru cine a fost făcut token-ul? Trebuie să fim chiar noi.
    if (($date['aud'] ?? '') !== (string) $config['google_client_id']) {
        error_log('PulsulOrasului: id_token pentru alt client_id.');
        return ['ok' => false, 'mesaj' => 'Datele primite nu sunt pentru site-ul ăsta.'];
    }

    // Cine l-a emis?
    $emitenti = $config['google_emitenti'] ?? GOOGLE_EMITENTI;
    if (!in_array((string) ($date['iss'] ?? ''), $emitenti, true)) {
        error_log('PulsulOrasului: id_token de la un emitent necunoscut.');
        return ['ok' => false, 'mesaj' => 'Datele primite nu vin de la Google.'];
    }

    // Până când e valabil? Lăsăm un minut joc, pentru ceasuri ușor nepotrivite.
    if (isset($date['exp']) && (int) $date['exp'] + 60 < time()) {
        return ['ok' => false, 'mesaj' => 'Datele primite de la Google au expirat. Încearcă din nou.'];
    }

    $sub   = (string) ($date['sub'] ?? '');
    $email = mb_strtolower(trim((string) ($date['email'] ?? '')), 'UTF-8');

    if ($sub === '' || $email === '') {
        return ['ok' => false, 'mesaj' => 'Google nu ne-a spus cine ești. Încearcă din nou.'];
    }

    /**
     * Adresa trebuie să fie una pe care Google a verificat-o el.
     *
     * Contează mai mult decât pare: legăm contul de Google la un cont existent
     * cu aceeași adresă. Dacă adresa n-ar fi verificată, cineva și-ar putea
     * pune în contul lui de Google adresa altcuiva și ar intra în contul ăluia.
     */
    if (empty($date['email_verified'])) {
        return [
            'ok'    => false,
            'mesaj' => 'Google nu a confirmat adresa asta de e-mail, așa că nu o putem folosi. '
                     . 'Fă-ți cont cu e-mail și parolă.',
        ];
    }

    return ['ok' => true, 'om' => [
        'sub'     => $sub,
        'email'   => $email,
        'nume'    => trim((string) ($date['family_name'] ?? '')),
        'prenume' => trim((string) ($date['given_name'] ?? '')),
        'intreg'  => trim((string) ($date['name'] ?? '')),
    ]];
}
