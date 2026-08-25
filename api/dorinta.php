<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — o dorință nouă pe tablă.
 *
 * Tot ce face: verifică cine cere, apoi cheamă puneODorinta() din
 * inc/dorinte.php. Verificarea textului, regula celor trei dorințe și
 * scrierea stau acolo — aceeași funcție pe care o cheamă și index.php când
 * pagina n-are JavaScript. Scrise în amândouă, s-ar fi despărțit la prima
 * schimbare.
 */

require_once __DIR__ . '/../inc/dorinte.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    raspunsJson(['ok' => false, 'mesaj' => 'Metodă nepermisă.'], 405);
}

/**
 * Din pagină vine JSON, dar primim și un formular obișnuit — aceeași
 * îngăduință ca la celelalte API-uri.
 */
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

$rezultat = puneODorinta((int) $membru['id'], $date);

raspunsJson([
    'ok'    => $rezultat['ok'],
    'mesaj' => $rezultat['mesaj'],
    'erori' => $rezultat['erori'],
], $rezultat['cod']);
