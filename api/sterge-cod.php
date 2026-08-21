<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — ștergerea unui abțibild „FindMe".
 *
 * O poate face doar staff-ul, de pe coduri.php, cu „×"-ul din capătul rândului.
 *
 * Numai codurile care N-AU FOST GĂSITE. Unul găsit e istoria cuiva: de el
 * atârnă cifra de pe profilul câștigătorului. Regula stă într-un singur loc,
 * poateFiStersCodul() din inc/coduri-qr.php, iar fapta o face stergeCodulQr(),
 * care o cere încă o dată chiar în WHERE.
 */

require_once __DIR__ . '/../inc/coduri-qr.php';

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

/**
 * Cine cere se întreabă din nou aici, din bază — nu ne bazăm pe faptul că
 * pagina cu butonul s-a deschis. Cererea asta poate veni de oriunde.
 */
if (!esteStaff($membru)) {
    raspunsJson(['ok' => false, 'mesaj' => 'Nu ai voie.'], 403);
}

$cod = codQrDupaCod(is_string($date['cod'] ?? null) ? $date['cod'] : '');

if ($cod === null) {
    raspunsJson(['ok' => false, 'mesaj' => 'Codul ăsta nu mai există.'], 404);
}

/**
 * 409, nu 403: codul există și omul are dreptul să umble la listă — doar că
 * ăsta anume nu se mai poate șterge. Un 403 l-ar fi trimis să creadă că nu e
 * de-al casei.
 */
if (!poateFiStersCodul($cod)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Abțibildul ăsta a fost găsit, așa că rămâne în listă: '
                 . 'de el atârnă cifra de pe profilul câștigătorului.',
    ], 409);
}

if (!stergeCodulQr((int) $cod['id'])) {
    // Între apăsare și clipa asta a apucat cineva să-l scaneze și să câștige.
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Cineva tocmai a găsit abțibildul ăsta. Reîncarcă pagina.',
    ], 409);
}

raspunsJson(['ok' => true, 'mesaj' => 'Codul a fost șters.']);
