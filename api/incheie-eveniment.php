<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — încheierea unui eveniment, înainte de vreme.
 *
 * Primește slugul, pune starea „incheiat", răspunde cu unde să meargă omul mai
 * departe. Nu se șterge nimic și nu se cere niciun motiv: evenimentul a avut
 * loc, pagina lui rămâne publică, se stinge doar ce se poate face pe ea.
 *
 * Confirmarea s-a dat deja în pagină, în două trepte; aici se verifică doar
 * dacă are voie.
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

$membruId  = (int) $membru['id'];
$slug      = trim((string) ($date['slug'] ?? ''));
$eveniment = evenimentDupaSlug($slug);

/**
 * Încheie doar organizatorul.
 *
 * Nu ne bazăm pe faptul că butonul s-a văzut în pagină — cererea asta poate
 * veni de oriunde, cu orice slug în ea. Și, ca peste tot, „nu există" și „nu e
 * al tău" primesc același răspuns.
 */
if ($eveniment === null || (int) $eveniment['membru_id'] !== $membruId) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Evenimentul nu mai există sau nu e al tău.',
    ], 404);
}

/**
 * Se încheie doar ce e publicat și încă în față.
 *
 * Un anunț care așteaptă moderarea n-a început, deci n-are ce încheia. Unul
 * anulat s-a terminat altfel. Iar unul deja încheiat — fie prin apăsare, fie
 * pentru că i-a trecut ziua — n-are de ce să fie încheiat a doua oară; în
 * pagină butonul nici nu se mai vede, dar asta e purtare frumoasă, nu regulă.
 */
if (!evenimentPublicat($eveniment)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Evenimentul nu e publicat, deci n-are ce încheia.',
    ], 409);
}

if (evenimentIncheiat($eveniment)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Evenimentul s-a încheiat deja.',
    ], 409);
}

/**
 * Și doar DUPĂ ce a început — ziua și ora.
 *
 * Ce nu s-a petrecut încă nu se poate încheia: ar fi un eveniment care apare pe
 * site ca și cum ar fi avut loc, deși nimeni n-a fost nicăieri. Ce vrea
 * organizatorul atunci se cheamă anulare, are butonul lui în formularul de
 * editare și cere un motiv — fiindcă oamenii înscriși trebuie înștiințați,
 * ceea ce la o încheiere n-are rost.
 *
 * În pagină butonul nici nu se vede înainte de oră, dar asta e purtare
 * frumoasă, nu regulă: cererea poate veni de oriunde, iar o filă lăsată
 * deschisă peste noapte arată butoane vechi.
 */
if (!evenimentAInceput($eveniment)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Evenimentul n-a început încă. Îl poți doar anula, din formularul de editare.',
    ], 409);
}

incheieEveniment($eveniment);

// Mesajul îl citește inc/subsol.php pe pagina următoare și îl arată o singură
// dată, ca la anulare.
pornesteSesiunea();
$_SESSION['mesaj_bun'] = 'Am încheiat evenimentul.';

/**
 * Înapoi pe aceeași pagină, nu pe profil.
 *
 * La anulare omul e trimis în altă parte, fiindcă evenimentul nu se mai vede.
 * Aici se vede în continuare — iar reîncărcarea e chiar lucrul care îi arată
 * banda de „s-a încheiat" și butoanele stinse.
 */
raspunsJson([
    'ok'       => true,
    'redirect' => urlEveniment((string) $eveniment['slug']),
    'mesaj'    => 'Am încheiat evenimentul.',
]);
