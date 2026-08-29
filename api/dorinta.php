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

$membruId = (int) $membru['id'];
$rezultat = puneODorinta($membruId, $date, esteStaff($membru));

/**
 * ZONA DE SUB TABLĂ SE ÎNTOARCE GATA DESENATĂ.
 *
 * Vorba despre dorințele omului („Ai două în lucru. Mai poți pune una.") și
 * butonul „Dorințele mele (2)" se schimbă amândouă la fiecare dorință scrisă
 * — dar pagina nu se reîncarcă, deci rămâneau cele de dinainte până la un F5.
 *
 * Se trimit de aici, desenate de aceeași funcție ca la încărcarea paginii. Nu
 * se construiesc în JS: ar fi fost al doilea loc care știe cum arată, iar cele
 * două s-ar fi despărțit la prima corectură. Aceeași înțelegere ca la
 * cartonașele de pe prima pagină.
 *
 * Se cere DUPĂ scriere, ca să numere și dorința tocmai pusă.
 */
$voie = poatePuneODorinta($membruId);

raspunsJson([
    'ok'    => $rezultat['ok'],
    'mesaj' => $rezultat['mesaj'],
    'erori' => $rezultat['erori'],
    'zona'  => randeazaZonaDorinte(true, $voie['stare'], $voie['dorinte']),
    // De el atârnă butonul „Pune-ți o dorință" din fereastra de bun venit: la
    // a treia dorință n-are ce căuta pe ecran, fiindcă serverul l-ar refuza.
    'poate' => $voie['stare'] === 'poate',
], $rezultat['cod']);
