<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — piuneza: anunțul care stă în capul primei pagini.
 *
 * Primește slugul și ce să se întâmple (`fixat`: true/false), pune sau ia
 * `evenimente.fixat_la`, răspunde cu starea nouă.
 *
 * NUMAI OMUL CASEI, oricine ar fi scris anunțul. E singurul punct de intrare de
 * pe pagina evenimentului care nu întreabă „e al tău?", ci „ești de-al casei?"
 * — încheierea și anularea sunt ale organizatorului, moderarea și asta sunt ale
 * staff-ului.
 *
 * Se cere o singură apăsare, fără confirmare: nu se pierde nimic și se ia
 * înapoi cu aceeași apăsare. O întrebare pusă pentru ceva care se dezface
 * dintr-o apăsare e o întrebare pe care omul învață s-o închidă fără să
 * citească.
 */

require_once __DIR__ . '/../inc/evenimente.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    raspunsJson(['ok' => false, 'mesaj' => 'Metodă nepermisă.'], 405);
}

// Din pagină vine JSON, dar primim și un formular obișnuit — aceeași
// îngăduință ca la celelalte puncte de intrare.
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
 * Paza, înaintea oricărei alte întrebări.
 *
 * Cine nu e de-al casei primește 403 și atât: nu află nici măcar dacă anunțul
 * despre care întreabă există. Butonul nici nu se scrie în pagină pentru el
 * (vezi event.php), dar cererea asta poate veni de oriunde.
 */
if (!esteStaff($membru)) {
    raspunsJson(['ok' => false, 'mesaj' => 'Doar echipa poate fixa un anunț.'], 403);
}

$slug      = trim((string) ($date['slug'] ?? ''));
$eveniment = evenimentDupaSlug($slug);

if ($eveniment === null) {
    raspunsJson(['ok' => false, 'mesaj' => 'Evenimentul nu mai există.'], 404);
}

/**
 * Se fixează doar ce se vede pe site.
 *
 * Un anunț care încă așteaptă moderarea, sau unul respins, nu e pe prima
 * pagină deloc — deci n-are unde să stea primul. Vezi poateFiFixat().
 */
if (!poateFiFixat($eveniment)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Anunțul ăsta nu e pe site, deci n-are unde să stea primul.',
    ], 409);
}

/**
 * Ce se cere: „pune" sau „ia".
 *
 * Se citește ce vrea omul, NU se comută ce e acum. Cu o comutare, două file
 * deschise deodată ar fi apăsat una după alta și ar fi lăsat piuneza exact cum
 * era — iar omul ar fi văzut butonul schimbându-se de două ori degeaba.
 */
$fixat = !empty($date['fixat']) && $date['fixat'] !== 'false';

fixeazaEveniment($eveniment, $fixat);

raspunsJson([
    'ok'    => true,
    'fixat' => $fixat,
    'mesaj' => $fixat
        ? 'Gata, anunțul stă acum primul pe prima pagină.'
        : 'Am luat piuneza. Anunțul se așază la rândul lui.',
]);
