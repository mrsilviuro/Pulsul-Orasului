<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — schimbarea pozei de profil.
 *
 * Primește un formular multipart cu:
 *   csrf     — token-ul din sesiune
 *   actiune  — 'salveaza' sau 'sterge'
 *   poza     — fișierul (doar la 'salveaza')
 *   x, y, l  — decupajul ales de utilizator, în pixelii pozei originale
 *
 * Răspunde cu JSON:
 *   {"ok":true, "poza":"assets/…jpg", "poza_mica":"assets/…-mic.jpg"}
 *   {"ok":false,"mesaj":"…"}
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/imagini.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    raspunsJson(['ok' => false, 'mesaj' => 'Metodă nepermisă.'], 405);
}

/**
 * Cazul special al trimiterii prea mari.
 *
 * Când corpul cererii depășește post_max_size, PHP îl aruncă înainte să
 * ajungă la noi: $_POST și $_FILES rămân goale, iar token-ul CSRF „lipsește"
 * din senin. Fără rândurile astea, omul ar primi „sesiunea a expirat" pentru
 * o poză prea mare — cel mai derutant mesaj cu putință.
 */
$primite = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
$limitaPhp = octetiDinSetare((string) ini_get('post_max_size'));

// Condiția cere și ca trimiterea să fi trecut chiar de limita serverului.
// Fără partea asta, orice cerere care nu e un formular obișnuit — o probă
// trimisă cu JSON, de pildă — ar primi „fișierul e prea mare", ceea ce ar
// trimite căutarea în direcția greșită.
if ($_POST === [] && $_FILES === [] && $primite > 0
    && $limitaPhp > 0 && $primite > $limitaPhp) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Fișierul e prea mare. Alege o poză de cel mult '
                 . (int) (min(POZA_OCTETI_MAX, $limitaPhp) / 1024 / 1024) . ' MB.',
    ], 413);
}

if (!tokenCsrfValid(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Sesiunea a expirat. Reîncarcă pagina și încearcă din nou.',
    ], 419);
}

/* --------------------------- Cine cere schimbarea --------------------- */

// Poza se schimbă doar pentru contul propriu. Nu există niciun parametru prin
// care să se spună „al cui" profil se modifică: e mereu al celui conectat.
$membru = membruCurent();

if ($membru === null) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Intră în cont ca să-ți poți schimba poza.',
    ], 401);
}

// Cine a intrat cu parola temporară nu face nimic altceva până nu-și pune una
// nouă. Paginile obișnuite sunt oprite din inc/antet.php; api/ nu trece pe
// acolo, deci verificarea se cheamă aici.
opresteDacaTrebuieParolaNoua(true);

$actiune = is_string($_POST['actiune'] ?? null) ? $_POST['actiune'] : 'salveaza';

/* ------------------------- Pauza dintre schimbări --------------------- */

// Se aplică la fel și la ștergere: altfel, un ciclu „șterge – încarcă" ar
// ocoli pauza. Nu e o barieră de nepătruns, ci o frână împotriva trimiterilor
// în rafală, care costă timp de procesor la fiecare redimensionare.
if (!empty($membru['poza_actualizata_la'])) {
    $trecut = time() - strtotime((string) $membru['poza_actualizata_la']);

    if ($trecut >= 0 && $trecut < POZA_SECUNDE_PAUZA) {
        raspunsJson([
            'ok'      => false,
            'secunde' => POZA_SECUNDE_PAUZA - $trecut,
            'mesaj'   => 'Ai schimbat poza chiar acum. Mai încearcă peste câteva secunde.',
        ], 429);
    }
}

/* ============================== ȘTERGEREA ============================== */

if ($actiune === 'sterge') {
    if (empty($membru['poza'])) {
        raspunsJson(['ok' => false, 'mesaj' => 'Nu ai nicio poză de șters.'], 422);
    }

    $u = db()->prepare('UPDATE membri SET poza = NULL, poza_actualizata_la = ? WHERE id = ?');
    $u->execute([acum(), (int) $membru['id']]);

    stergePozaDeFisier($membru['poza']);

    raspunsJson([
        'ok'        => true,
        'poza'      => urlPoza(null),
        'poza_mica' => urlPoza(null, true),
        'mesaj'     => 'Am șters poza de profil.',
    ]);
}

if ($actiune !== 'salveaza') {
    raspunsJson(['ok' => false, 'mesaj' => 'Acțiune necunoscută.'], 422);
}

/* ============================== SALVAREA =============================== */

if (!isset($_FILES['poza']) || !is_array($_FILES['poza'])) {
    raspunsJson(['ok' => false, 'mesaj' => 'Nu ai ales nicio poză.'], 422);
}

// Un formular trimis cu name="poza[]" ar face din fiecare câmp un tablou, iar
// codul de mai jos ar primi altceva decât se așteaptă. Nu-l lăsăm să treacă.
if (is_array($_FILES['poza']['tmp_name'] ?? null)) {
    raspunsJson(['ok' => false, 'mesaj' => 'Trimite o singură poză.'], 422);
}

$decupaj = null;

if (isset($_POST['l']) && is_numeric($_POST['l'])) {
    $decupaj = [
        'x' => is_numeric($_POST['x'] ?? null) ? (float) $_POST['x'] : 0,
        'y' => is_numeric($_POST['y'] ?? null) ? (float) $_POST['y'] : 0,
        'l' => (float) $_POST['l'],
    ];
}

$rezultat = procesezaPozaProfil($_FILES['poza'], $decupaj);

if (!$rezultat['ok']) {
    raspunsJson(['ok' => false, 'mesaj' => $rezultat['mesaj']], 422);
}

$vechea = $membru['poza'] ?? null;

try {
    $u = db()->prepare('UPDATE membri SET poza = ?, poza_actualizata_la = ? WHERE id = ?');
    $u->execute([$rezultat['nume'], acum(), (int) $membru['id']]);
} catch (PDOException $e) {
    // Rândul n-a fost scris, deci fișierele proaspăt create n-ar mai fi ale
    // nimănui. Le ștergem, ca să nu rămână gunoi pe disc.
    stergePozaDeFisier($rezultat['nume']);
    throw $e;
}

// Abia acum, când noua poză e sigur în bază, o aruncăm pe cea veche. În ordine
// inversă, o eroare la scriere ar lăsa omul fără nicio poză.
stergePozaDeFisier($vechea);

raspunsJson([
    'ok'        => true,
    'poza'      => urlPoza($rezultat['nume']),
    'poza_mica' => urlPoza($rezultat['nume'], true),
    'mesaj'     => 'Am schimbat poza de profil.',
]);
