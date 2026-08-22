<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — pornirea aplicației.
 *
 * Se include la începutul oricărei pagini sau al oricărui punct de intrare
 * din api/. Citește setările, deschide legătura cu baza de date și pune la
 * dispoziție câteva funcții mici, folosite peste tot.
 */

require_once __DIR__ . '/validare.php';

/* ----------------------------- SETĂRILE ------------------------------- */

$caleConfig = __DIR__ . '/config.php';

if (!is_file($caleConfig)) {
    /**
     * Cea mai des întâlnită problemă imediat după mutarea pe o găzduire nouă.
     *
     * config.php e trecut în .gitignore, tocmai ca parolele să nu ajungă pe
     * GitHub — dar asta înseamnă și că nu se copiază odată cu restul codului.
     * Se face de mână pe server.
     *
     * Răspunsul pleacă în JSON când cererea vine din formulare, ca mesajul să
     * ajungă în pagină. Altfel utilizatorul vedea „verifică conexiunea", iar
     * adevărata explicație rămânea ascunsă.
     */
    $mesaj = 'Lipsește fișierul inc/config.php de pe server. '
           . 'Copiază inc/config.example.php cu numele inc/config.php '
           . 'și pune acolo datele de acces la baza de date.';

    http_response_code(500);

    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['ok' => false, 'mesaj' => $mesaj], JSON_UNESCAPED_UNICODE));
    }

    header('Content-Type: text/plain; charset=utf-8');
    exit($mesaj);
}

/** @var array $config */
$config = require $caleConfig;

/* ------------------------------ TIMPUL -------------------------------- */

/**
 * Un singur ceas în toată aplicația: cel al PHP-ului.
 *
 * Motivul, învățat pe pielea noastră: dacă unele momente se scriu cu NOW()
 * (ceasul serverului de baze de date) și se compară apoi cu time() (ceasul
 * PHP), iar cele două servere au fusuri orare diferite, toate socotelile de
 * genul „mai ai 10 minute" ies greșite exact cu diferența dintre fusuri.
 *
 * De aceea nicio interogare din aplicație nu mai folosește NOW(): momentele
 * se calculează aici și se trimit ca parametri obișnuiți.
 */
date_default_timezone_set($config['fus_orar'] ?? 'Europe/Bucharest');

/**
 * Orașele în care se pot pune evenimente, din inc/config.php.
 *
 * Un singur loc de unde le ia și formularul, și verificarea de pe server:
 * altfel s-ar putea alege în pagină un oraș pe care serverul îl refuză, sau
 * invers. Se curăță aici de valorile goale, ca o virgulă în plus în config să
 * nu ajungă o opțiune fără nume în listă.
 *
 * Lista goală nu e o greșeală de oprit: înseamnă doar că nu se poate publica
 * nimic până nu se scrie un oraș în config, iar formularul spune asta.
 */
function oraseDisponibile(): array
{
    global $config;

    $orase = $config['orase'] ?? [];

    if (!is_array($orase)) {
        return [];
    }

    $curate = [];

    foreach ($orase as $oras) {
        if (is_string($oras) && trim($oras) !== '') {
            $curate[] = trim($oras);
        }
    }

    return array_values(array_unique($curate));
}

/**
 * Adresa site-ului, fără bară la coadă: „https://pulsulorasului.ro".
 *
 * Se cere ori de câte ori o cale trebuie să devină adresă întreagă — linkul
 * de distribuire, poza din cartonașul de pe WhatsApp, linkurile din e-mailuri.
 * Într-un singur loc, ca să nu existe cinci feluri de a tăia bara de la coadă.
 */
function urlSite(): string
{
    global $config;

    return rtrim((string) ($config['url_site'] ?? ''), '/');
}

/**
 * O cale de-a noastră („assets/img/…"), făcută adresă întreagă.
 *
 * Facebook și WhatsApp nu se uită la pagină din browserul omului: o cer ele,
 * de pe alt server, iar o cale relativă n-are acolo față de ce să se
 * socotească. Calea goală rămâne goală — cine cheamă funcția hotărăște ce face
 * cu asta.
 */
function urlIntreg(string $cale): string
{
    if ($cale === '') {
        return '';
    }

    // Ce e deja adresă întreagă se lasă în pace.
    if (preg_match('#^https?://#i', $cale) === 1) {
        return $cale;
    }

    return urlSite() . '/' . ltrim($cale, '/');
}

/** Momentul de acum, în formatul cu care lucrează coloanele DATETIME. */
function acum(): string
{
    return date('Y-m-d H:i:s');
}

/** Un moment din trecut: acumMinus(10) = „acum 10 minute". */
function acumMinus(int $minute): string
{
    return date('Y-m-d H:i:s', time() - $minute * 60);
}

/**
 * Un rând într-un fișier din private/, cu data în față.
 *
 * Dosarul private/ e închis din .htaccess, deci nimic de aici nu se vede din
 * web. Se folosește la e-mailurile trimise, la conturile anonimizate și la
 * încercările de spam — toate aveau, până acum, propria copie a acelorași
 * cinci rânduri.
 *
 * Nu aruncă niciodată: un log care nu se poate scrie nu trebuie să oprească
 * treaba pentru care s-a scris logul.
 */
function scrieInLog(string $fisier, string $rand): void
{
    $dosar = __DIR__ . '/../private';

    if (!is_dir($dosar) && !@mkdir($dosar, 0755, true) && !is_dir($dosar)) {
        return;
    }

    // Numele fișierului vine din cod, nu de la vizitator, dar tăiem oricum
    // orice ar putea ieși din dosar.
    $fisier = basename($fisier);

    @file_put_contents(
        $dosar . '/' . $fisier,
        '[' . date('Y-m-d H:i:s') . '] ' . $rand . "\n",
        FILE_APPEND
    );
}

/* --------------------------- AFIȘAREA ERORILOR ------------------------ */

// În dezvoltare vrem să vedem tot. În producție, utilizatorul nu trebuie să
// afle niciodată numele tabelelor sau calea fișierelor de pe server.
if (!empty($config['dezvoltare'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

/* ------------------------- BAZA DE DATE (PDO) ------------------------- */

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $config;
    $d = $config['db'];

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $d['host'] ?? 'localhost',
        (int) ($d['port'] ?? 3306),
        $d['nume'] ?? ''
    );

    try {
        $pdo = deschidePdo($dsn, $d);
    } catch (PDOException $e) {
        /**
         * Mesajul original ajunge doar în logul serverului. Vizitatorului nu i
         * se spune nici numele bazei, nici al utilizatorului, nici dacă parola
         * a fost greșită — toate astea ajută pe cineva care încearcă să intre.
         */
        error_log('PulsulOrasului: nu s-a putut deschide baza de date — ' . $e->getMessage());

        throw new RuntimeException(
            'Nu am putut deschide baza de date. Verifică datele din inc/config.php.',
            0,
            $e
        );
    }

    return $pdo;
}

function deschidePdo(string $dsn, array $d): PDO
{
    return new PDO($dsn, $d['user'] ?? '', $d['parola'] ?? '', [
        // Orice problemă devine excepție, ca să nu treacă neobservată.
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Interogări pregătite adevărate, trimise ca atare serverului.
        // Fără asta, PDO le compune singur în PHP, iar protecția împotriva
        // injecțiilor depinde de setarea corectă a codificării.
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

/* ---------------------- CÂND CEVA CHIAR SE STRICĂ --------------------- */

/**
 * Ultima plasă de siguranță: o eroare neprinsă nu mai lasă pagina goală.
 *
 * Fără ea, o problemă de bază de date întoarce un răspuns gol cu codul 500.
 * Pagina cere JSON, primește nimic, și îi spune omului „verifică conexiunea"
 * — cel mai nepotrivit sfat cu putință, fiindcă internetul lui e în regulă.
 *
 * Acum răspunsul e tot timpul JSON pentru cererile către api/, ca partea din
 * browser să aibă ce citi și ce arăta.
 */
set_exception_handler(function (Throwable $e): void {
    global $config;

    error_log('PulsulOrasului: ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');

    if (headers_sent()) {
        return;
    }

    // În dezvoltare se vede tot; pe site-ul public, doar atât cât ajută.
    $detaliu = !empty($config['dezvoltare'])
        ? $e->getMessage()
        : 'A apărut o problemă pe server. Detaliile sunt în logul de erori.';

    $esteApi = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/');

    if ($esteApi) {
        raspunsJson(['ok' => false, 'mesaj' => $detaliu], 500);
    }

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Eroare</title>'
       . '<p style="font:16px/1.6 system-ui,sans-serif;max-width:40em;margin:3em auto;padding:0 1em">'
       . h($detaliu) . '</p>';
});

/* ------------------------------- SESIUNE ------------------------------ */

function pornesteSesiunea(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // O sesiune nu mai poate fi pornită după ce pagina a început să se
    // tipărească — cookie-ul se trimite printre antetele HTTP, iar acelea au
    // plecat deja. Fără verificarea asta, PHP ar umple pagina cu avertismente.
    if (headers_sent()) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,                     // JavaScript nu vede cookie-ul
        'samesite' => 'Lax',                    // nu pleacă la cereri de pe alt site
        'secure'   => !empty($_SERVER['HTTPS']), // pe https, doar pe https
    ]);
    session_start();
}

/* ------------------------ TOKEN ÎMPOTRIVA CSRF ------------------------ */

/**
 * Fără el, un alt site ar putea trimite formulare în numele vizitatorului.
 * Token-ul stă în sesiune și e verificat la fiecare trimitere.
 */
function tokenCsrf(): string
{
    pornesteSesiunea();

    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function tokenCsrfValid(?string $primit): bool
{
    pornesteSesiunea();

    if (empty($_SESSION['csrf']) || !is_string($primit) || $primit === '') {
        return false;
    }

    // hash_equals compară în timp constant, ca durata răspunsului să nu
    // spună nimic despre cât din token a fost ghicit.
    return hash_equals($_SESSION['csrf'], $primit);
}

/* -------------------------------- DIVERSE ----------------------------- */

/** Trimite un răspuns JSON și oprește execuția. */
function raspunsJson(array $date, int $cod = 200): void
{
    http_response_code($cod);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($date, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Scapă un text înainte de a-l tipări în HTML. */
function h(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Traduce o setare de PHP scrisă pe scurt („8M", „2G") în octeți.
 *
 * Întoarce 0 pentru „fără limită" (-1) și pentru valorile pe care nu le
 * înțelege, ca cel care o folosește să poată trata cazul.
 */
function octetiDinSetare(string $valoare): int
{
    $valoare = trim($valoare);

    if ($valoare === '' || (int) $valoare === -1) {
        return 0;
    }

    $unitate = strtolower(substr($valoare, -1));
    $numar   = (int) $valoare;

    if ($unitate === 'g') return $numar * 1024 * 1024 * 1024;
    if ($unitate === 'm') return $numar * 1024 * 1024;
    if ($unitate === 'k') return $numar * 1024;

    return $numar;
}

/* ------------------------- ANTETE DE SIGURANȚĂ ------------------------ */

/**
 * Cifra de o singură folosință cu care se însemnează scripturile noastre.
 *
 * Politica de conținut (mai jos) spune browserului: „rulează doar scripturile
 * aduse de pe site-ul ăsta și, dintre cele scrise chiar în pagină, doar pe
 * cele care poartă cifra asta". Cifra se face din nou la fiecare cerere, deci
 * cine ar reuși vreodată să strecoare un `<script>` în pagină n-are de unde
 * s-o ghicească, iar scriptul lui rămâne o bucată de text.
 *
 * Se face o singură dată pe cerere și se ține minte: antetul și eticheta din
 * pagină trebuie să spună aceeași cifră, altfel nu rulează nici scriptul
 * nostru.
 */
function nonceCsp(): string
{
    static $nonce = null;

    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }

    return $nonce;
}

/**
 * Antetele de siguranță ale unei PAGINI (nu ale unui răspuns JSON).
 *
 * Stau într-un singur loc fiindcă le cer două pagini care nu se ating:
 * `inc/antet.php`, adică tot site-ul, și `constructie.php`, singura pagină
 * care nu trece prin antetul comun (vezi lămurirea de acolo). Scrise de două
 * ori, a doua copie ar fi rămas în urmă la prima schimbare — și tocmai afișul
 * de pe ușă, singurul care se vede cu site-ul închis, ar fi fost cel rămas
 * fără pază.
 *
 * DESPRE `style-src 'unsafe-inline'`: da, e o portiță, și e deschisă anume.
 * Site-ul are trei locuri cu `style="…"` scris în pagină (bara de note de pe
 * profil, care are lățimea în procente, și pagina de eroare din bootstrap), iar
 * o cifră de unică folosință nu funcționează pe atributele `style`, doar pe
 * etichetele `<style>`. Ce se poate face cu un stil strecurat e mult mai puțin
 * decât cu un script: nu citește sesiunea, nu trimite nimic nicăieri —
 * `connect-src 'self'` și `form-action 'self'` îi taie și drumul de întoarcere.
 */
function antetedeSiguranta(): void
{
    // Pagina nu poate fi încărcată într-un cadru pe alt site (clickjacking).
    header('X-Frame-Options: DENY');
    // Browserul nu ghicește tipul fișierelor, ci îl respectă pe cel trimis.
    header('X-Content-Type-Options: nosniff');
    // Către alte site-uri nu se trimite calea completă, doar domeniul.
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Fără acces la cameră, microfon sau localizare.
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    // De unde are voie browserul să aducă fiecare fel de lucru. Tot ce nu e
    // scris aici e oprit: e o listă de îngăduințe, nu una de opreliști.
    header('Content-Security-Policy: '
        . "default-src 'self'; "
        // Scripturile: ale noastre, plus cel scris în pagină care poartă cifra.
        . "script-src 'self' 'nonce-" . nonceCsp() . "'; "
        // Stilurile: ale noastre, fontul de la Google și atributele `style=`.
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        // Fișierele de font vin de pe celălalt domeniu al Google.
        . "font-src 'self' https://fonts.gstatic.com; "
        // Pozele: ale noastre, plus cele desenate în cod (`data:`).
        . "img-src 'self' data:; "
        // `fetch` din main.js merge doar spre api/-ul nostru.
        . "connect-src 'self'; "
        // Un formular strecurat în pagină n-are unde trimite ce a scris omul.
        . "form-action 'self'; "
        // Nimeni nu poate muta rădăcina adreselor relative pe alt server.
        . "base-uri 'self'; "
        // Aceeași vorbă ca X-Frame-Options, pentru browserele noi.
        . "frame-ancestors 'none'; "
        // Fără cadre, fără Flash, fără applet-uri: site-ul n-are niciunul.
        . "frame-src 'none'; "
        . "object-src 'none'"
    );
}

/** Adresa IP a vizitatorului, în formă binară, pentru coloana VARBINARY(16). */
function ipBinar(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $binar = @inet_pton($ip);
    return $binar === false ? null : $binar;
}
