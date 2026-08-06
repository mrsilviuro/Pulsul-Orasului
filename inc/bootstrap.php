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
    http_response_code(500);
    exit(
        "Lipsește fișierul inc/config.php.\n\n" .
        "Copiază inc/config.example.php cu numele inc/config.php " .
        "și pune acolo datele tale de acces la baza de date."
    );
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
        $d['host'], $d['port'], $d['nume']
    );

    $pdo = new PDO($dsn, $d['user'], $d['parola'], [
        // Orice problemă devine excepție, ca să nu treacă neobservată.
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Interogări pregătite adevărate, trimise ca atare serverului.
        // Fără asta, PDO le compune singur în PHP, iar protecția împotriva
        // injecțiilor depinde de setarea corectă a codificării.
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

/* ------------------------------- SESIUNE ------------------------------ */

function pornesteSesiunea(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
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

/** Adresa IP a vizitatorului, în formă binară, pentru coloana VARBINARY(16). */
function ipBinar(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $binar = @inet_pton($ip);
    return $binar === false ? null : $binar;
}
