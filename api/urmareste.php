<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — apasă „Urmărește", sau îl apasă a doua oară.
 *
 * UN SINGUR PUNCT PENTRU AMÂNDOUĂ FAPTELE, fiindcă e același buton: prima
 * apăsare începe urmărirea, a doua o oprește. Două adrese deosebite ar fi cerut
 * ca pagina să știe dinainte în ce stare e — iar ea o știe doar de la ultima
 * încărcare, care poate fi de acum zece minute și de pe altă filă.
 *
 * Cine hotărăște ce se întâmplă e comutaUrmarirea() din inc/urmariri.php, prin
 * cheia unică din bază. Aici se face doar paza: token bun, cont, și un om pe
 * care chiar se poate apăsa.
 */

require_once __DIR__ . '/../inc/urmariri.php';

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

$eu = membruCurent();

if ($eu === null) {
    raspunsJson(['ok' => false, 'mesaj' => 'Intră în cont ca să urmărești pe cineva.'], 401);
}

opresteDacaTrebuieParolaNoua(true);

$idOm = (int) ($date['membru'] ?? 0);

if ($idOm <= 0) {
    raspunsJson(['ok' => false, 'mesaj' => 'Nu știu pe cine să urmăresc.'], 422);
}

/**
 * Omul se caută în bază, nu se ia pe cuvânt din cerere: de aici atârnă și dacă
 * mai există, și dacă rândul lui mai are un om în el.
 */
$q = db()->prepare('SELECT id, stare FROM membri WHERE id = ? LIMIT 1');
$q->execute([$idOm]);
$omul = $q->fetch();

if ($omul === false) {
    raspunsJson(['ok' => false, 'mesaj' => 'Omul ăsta nu mai există.'], 404);
}

/**
 * Pe tine însuți nu te poți urmări, iar un cont golit n-o să mai pună
 * niciodată nimic. Aceeași întrebare pe care o pune și butonul când se
 * desenează — scrisă o dată, în inc/urmariri.php.
 */
if (!poateFiUrmarit($eu, $omul)) {
    raspunsJson(['ok' => false, 'mesaj' => 'Pe omul ăsta nu-l poți urmări.'], 409);
}

$dupa = comutaUrmarirea((int) $eu['id'], $idOm);

raspunsJson([
    'ok'        => true,
    'urmareste' => $dupa['urmareste'],
    'cati'      => $dupa['cati'],
    'mesaj'     => $dupa['urmareste']
        ? 'Gata! Îți dăm de veste când pune ceva nou.'
        : 'Nu-l mai urmărești. Poți începe din nou oricând.',
]);
