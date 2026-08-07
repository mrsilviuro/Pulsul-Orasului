<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — cererea de ștergere a contului.
 *
 * Aici NU se șterge nimic și nu se schimbă nimic în cont. Se scrie un token și
 * pleacă un e-mail. Ștergerea pornește abia la apăsarea linkului, în
 * stergere.php.
 *
 * Două încuietori, pentru cine are parolă: parola dovedește că e omul din fața
 * calculatorului, e-mailul dovedește că are și cutia poștală. Un calculator
 * lăsat deschis nu e de ajuns ca să pierzi contul.
 */

require_once __DIR__ . '/../inc/stergere.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    raspunsJson(['ok' => false, 'mesaj' => 'Metodă nepermisă.'], 405);
}

$tip = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($tip, 'application/json') !== false) {
    $date = json_decode(file_get_contents('php://input') ?: '', true);
    $date = is_array($date) ? $date : [];
} else {
    $date = $_POST;
}

if (!tokenCsrfValid(is_string($date['csrf'] ?? null) ? $date['csrf'] : '')) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Sesiunea a expirat. Reîncarcă pagina și încearcă din nou.',
    ], 419);
}

$membru = membruCurent();

if ($membru === null) {
    raspunsJson(['ok' => false, 'mesaj' => 'Trebuie să fii conectat.'], 401);
}

opresteDacaTrebuieParolaNoua(true);

/* --------------------------- Parola, dacă are ------------------------- */

$q = db()->prepare('SELECT parola_hash FROM membri WHERE id = ? LIMIT 1');
$q->execute([(int) $membru['id']]);
$hash = $q->fetchColumn();

$areParola = is_string($hash) && $hash !== '';

if ($areParola) {
    $parola = is_string($date['parola'] ?? null) ? $date['parola'] : '';

    // Aceeași limită ca la intrare: nimeni nu ne pune să calculăm hash-uri
    // pentru șiruri uriașe.
    if (strlen($parola) > 4096) {
        raspunsJson(['ok' => false, 'erori' => ['parola' => 'Parola e prea lungă.']], 422);
    }

    if ($parola === '') {
        raspunsJson(['ok' => false, 'erori' => ['parola' => 'Scrie-ți parola.']], 422);
    }

    if (!password_verify($parola, (string) $hash)) {
        raspunsJson(['ok' => false, 'erori' => ['parola' => 'Parola nu e corectă.']], 422);
    }
}

/* ------------------------------ E-mailul ------------------------------ */

$plecat = cereStergereaContului($membru);

if (!$plecat) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Nu am putut trimite e-mailul. Încearcă din nou peste câteva minute.',
    ], 500);
}

raspunsJson([
    'ok'    => true,
    'mesaj' => 'Ți-am trimis un e-mail pe ' . $membru['email'] . '. '
             . 'Apasă linkul din el ca să confirmi.',
]);
