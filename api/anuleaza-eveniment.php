<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — anularea unui eveniment.
 *
 * Primește slugul și motivul, trece evenimentul în starea „anulat", răspunde
 * cu unde să meargă omul mai departe. Nu se șterge nimic: rândul rămâne pentru
 * staff și pentru e-mailurile care vor pleca spre cei înscriși.
 *
 * Confirmarea s-a dat deja în pagină, în două trepte; aici se verifică dacă
 * are voie și dacă motivul e scris cum trebuie.
 */

require_once __DIR__ . '/../inc/evenimente.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    raspunsJson(['ok' => false, 'mesaj' => 'Metodă nepermisă.'], 405);
}

/**
 * Din pagină vine JSON, dar primim și un formular obișnuit — aceeași
 * îngăduință ca la api/autentificare.php. Cine trimite altfel decât se aștepta
 * nu primește o eroare de necitit, ci trece prin aceleași verificări.
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

/**
 * Aceeași regulă ca la editare, aceeași funcție: anulează doar organizatorul.
 *
 * Nu ne bazăm pe faptul că butonul s-a văzut în pagină — cererea asta poate
 * veni de oriunde, cu orice slug în ea.
 */
$slug = trim((string) ($date['slug'] ?? ''));
$eveniment = evenimentDeEditat($slug, (int) $membru['id']);

if ($eveniment === null) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Evenimentul nu mai există sau nu e al tău.',
    ], 404);
}

/**
 * Motivul e obligatoriu, verificat pe server ca orice altceva.
 *
 * Nu e o formalitate: textul ăsta va pleca prin e-mail spre toți cei care
 * voiau să vină (vezi TODO-ul din anuleazaEveniment). Un „ok" scris în grabă
 * n-ar spune nimic nimănui, de-aia are și o lungime minimă.
 */
$motiv = verificaMotivAnulare($date['motiv'] ?? null);

if ($motiv['eroare'] !== '') {
    raspunsJson([
        'ok'    => false,
        'erori' => ['motiv' => $motiv['eroare']],
    ], 422);
}

anuleazaEveniment($eveniment, $motiv['text']);

// Mesajul îl citește inc/subsol.php pe pagina următoare și îl arată o
// singură dată, ca la intrarea cu Google.
pornesteSesiunea();
$_SESSION['mesaj_bun'] = 'Evenimentul a fost anulat.';

raspunsJson([
    'ok'       => true,
    'redirect' => 'profil.php',
    'mesaj'    => 'Evenimentul a fost anulat.',
]);
