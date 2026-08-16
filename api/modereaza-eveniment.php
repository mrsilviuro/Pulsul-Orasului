<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — aprobarea sau respingerea unui anunț, de către staff.
 *
 * Primește slugul și starea cerută („aprobat" sau „respins"), o pune, răspunde
 * cu unde să meargă omul mai departe. Nu se șterge nimic și nu se cere niciun
 * motiv: hotărârea se vede în starea anunțului, iar organizatorul o citește pe
 * pagina lui.
 *
 * BUTOANELE DIN PAGINĂ NU SUNT O DOVADĂ. Ele nici măcar nu se scriu în HTML
 * pentru cine nu e staff — dar asta e purtare frumoasă, nu pază. Cererea de
 * față poate veni de oriunde, cu orice slug și orice stare în ea, așa că
 * fiecare condiție se pune din nou aici.
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
 * NUMAI STAFF. E singura poartă care contează aici.
 *
 * esteStaff() citește din bază la fiecare cerere, nu dintr-un semn pus în
 * sesiune: un drept luat înapoi trebuie să dispară imediat, nu la următoarea
 * conectare. Iar „staff" înseamnă orice valoare în afară de 0 în
 * `membri.este_staff` — vezi inc/auth.php.
 *
 * 403, nu 404: aici nu e nimic de ascuns. Pagina evenimentului se vede oricum
 * de oricine, deci un „nu există" ar fi o minciună fără niciun câștig.
 */
if (!esteStaff($membru)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Doar echipa poate aproba sau respinge anunțuri.',
    ], 403);
}

/* ---------------------------- ce se cere ------------------------------ */

$stare = (string) ($date['stare'] ?? '');

if (!in_array($stare, STARI_DE_MODERAT, true)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Nu știu starea asta.',
    ], 422);
}

$slug      = trim((string) ($date['slug'] ?? ''));
$eveniment = evenimentDupaSlug($slug);

if ($eveniment === null) {
    raspunsJson(['ok' => false, 'mesaj' => 'Evenimentul nu mai există.'], 404);
}

/**
 * Anulat sau încheiat: nu mai e nimic de hotărât.
 *
 * Anulat e hotărârea organizatorului, luată în fața oamenilor înscriși, care au
 * primit deja un e-mail cu motivul; o aprobare peste ea ar readuce pe site un
 * eveniment despre care toată lumea a aflat că nu mai are loc. Încheiat
 * înseamnă că seara aceea a trecut.
 *
 * În pagină butoanele nici nu se văd atunci — dar, ca peste tot, asta e
 * purtare frumoasă, nu regulă: o filă lăsată deschisă peste noapte arată
 * butoane vechi.
 */
if (!poateFiModerat($eveniment)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Anunțul e ' . ($eveniment['stare_moderare'] === 'anulat' ? 'anulat' : 'încheiat')
                 . ', deci nu mai e nimic de hotărât.',
    ], 409);
}

/**
 * Starea pe care o are deja nu se pune a doua oară.
 *
 * Nu e o eroare adevărată — nu s-a stricat nimic — dar un „gata, l-am aprobat"
 * pentru un anunț aprobat de altcineva acum trei minute i-ar spune omului de
 * casă că el a făcut ceva ce n-a făcut. Se întâmplă des la doi moderatori pe
 * aceeași listă.
 */
if ((string) $eveniment['stare_moderare'] === $stare) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Anunțul e deja ' . ($stare === 'aprobat' ? 'aprobat' : 'respins') . '.',
    ], 409);
}

moderezaEveniment($eveniment, $stare);

$vorba = $stare === 'aprobat'
    ? 'Anunțul a fost aprobat și se vede pe site.'
    : 'Anunțul a fost respins. Îl vede doar organizatorul.';

// Mesajul îl citește inc/subsol.php pe pagina următoare și îl arată o singură
// dată, ca la încheiere și la anulare.
pornesteSesiunea();
$_SESSION['mesaj_bun'] = $vorba;

/**
 * Înapoi pe aceeași pagină.
 *
 * Reîncărcarea e chiar lucrul care arată ce s-a schimbat: banda de stare de sus
 * și butoanele, care de acum spun altceva. Un anunț respins rămâne de văzut
 * pentru staff, deci nimeni nu e trimis într-o ușă închisă.
 */
raspunsJson([
    'ok'       => true,
    'stare'    => $stare,
    'redirect' => urlEveniment((string) $eveniment['slug']),
    'mesaj'    => $vorba,
]);
