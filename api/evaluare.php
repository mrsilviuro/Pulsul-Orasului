<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — notele dintre participanți.
 *
 * Trei fapte, toate legate de un eveniment încheiat:
 *
 *   noteaza  — stelele apăsate în dreptul cuiva, pe pagina evenimentului
 *   scrie    — stelele plus vorbele, din formularul de pe profilul lui
 *   absent   — „Nu s-a prezentat", pus de organizator: o stea și un text scris
 *              de noi, nu părerea nimănui
 *
 * Notele sunt ANONIME. Aici se scrie cine a dat-o, ca să nu poată nota de zece
 * ori, dar nimic din ce iese de aici nu spune cine. Vezi inc/evaluari.php.
 */

require_once __DIR__ . '/../inc/evaluari.php';

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

$membruId = (int) $membru['id'];

/* ========================= 1. Care eveniment ========================= */

$slug      = trim((string) ($date['slug'] ?? ''));
$eveniment = evenimentDupaSlug($slug);

if ($eveniment === null
    || !poateVedeaEvenimentul($eveniment, $membruId, esteStaff($membru))) {
    raspunsJson(['ok' => false, 'mesaj' => 'Evenimentul nu mai există.'], 404);
}

$evenimentId = (int) $eveniment['id'];

/* ========================== 2. Pe cine ============================== */

$tintaId = (int) ($date['membru'] ?? 0);

if ($tintaId <= 0) {
    raspunsJson(['ok' => false, 'mesaj' => 'Nu știu pe cine să notez.'], 422);
}

$fapta = (string) ($date['fapta'] ?? '');

/* ====================== 3. „Nu s-a prezentat" ======================= */

/**
 * Însemnarea organizatorului. Se rupe de celelalte două de la început, fiindcă
 * are alte reguli: n-o dă un participant oarecare, ci omul care a ținut
 * evenimentul, iar textul nu e al lui — e scris de noi.
 */
if ($fapta === 'absent') {
    if ((int) $eveniment['membru_id'] !== $membruId) {
        raspunsJson([
            'ok'    => false,
            'mesaj' => 'Doar organizatorul poate spune cine nu s-a prezentat.',
        ], 403);
    }

    /**
     * Nici organizatorul nu poate însemna pe cineva înainte să se termine.
     *
     * Aceeași regulă ca la note, prin aceeași funcție: în timpul evenimentului
     * omul poate încă să apară, iar o însemnare pusă la mijloc ar rămâne acolo
     * pe nedrept.
     */
    $blocaj = motivBlocajEvaluare($eveniment, $membruId, $tintaId);

    if ($blocaj !== '') {
        raspunsJson(['ok' => false, 'mesaj' => $blocaj], 403);
    }

    salveazaEvaluare(
        $evenimentId,
        $tintaId,
        $membruId,
        EVALUARE_ABSENT_STELE,
        EVALUARE_ABSENT_TEXT,
        true
    );

    raspunsJson([
        'ok'      => true,
        'membru'  => $tintaId,
        'stele'   => EVALUARE_ABSENT_STELE,
        'automat' => true,
        'mesaj'   => 'Am însemnat că nu s-a prezentat. A primit o stea și o notă pe profil.',
    ]);
}

/* ========================= 4. Nota unui om ========================== */

if ($fapta !== 'noteaza' && $fapta !== 'scrie') {
    raspunsJson(['ok' => false, 'mesaj' => 'Nu știu ce să fac cu asta.'], 422);
}

/**
 * Aceeași funcție care desenează stelele stinse în pagină.
 *
 * În pagină e o purtare frumoasă; aici e regula, fiindcă cererea poate veni de
 * oriunde — dintr-o filă deschisă înainte de încheierea evenimentului, sau
 * de-a dreptul cu curl.
 */
$blocaj = motivBlocajEvaluare($eveniment, $membruId, $tintaId);

if ($blocaj !== '') {
    raspunsJson(['ok' => false, 'mesaj' => $blocaj], 403);
}

$stele = stelePrimite($date['stele'] ?? 0);

if ($stele === 0) {
    raspunsJson(['ok' => false, 'mesaj' => 'Alege câte stele îi dai, de la 1 la 5.'], 422);
}

/**
 * Textul, doar când vine din formularul de pe profil.
 *
 * De pe pagina evenimentului se dau stele dintr-o apăsare, fără vorbe — și
 * `null` înseamnă acolo „nu atinge ce scria înainte", nu „șterge". Cine a
 * scris un text pe profil și apoi schimbă stelele de pe eveniment nu are de
 * unde să știe că altfel și-ar șterge vorbele. Vezi salveazaEvaluare().
 */
$text = null;

if ($fapta === 'scrie') {
    $rezultat = verificaTextEvaluare($date['text'] ?? '');

    if ($rezultat['eroare'] !== '') {
        raspunsJson(['ok' => false, 'erori' => ['text' => $rezultat['eroare']]], 422);
    }

    // Gol înseamnă „doar stele" și pe profil: caseta nu e obligatorie.
    $text = $rezultat['text'] !== '' ? $rezultat['text'] : null;
}

salveazaEvaluare($evenimentId, $tintaId, $membruId, $stele, $text);

/* ========================== 5. Ce se întoarce ======================= */

/**
 * Rezumatul și lista se citesc din bază DUPĂ scriere, nu se socotesc în JS:
 * media e o împărțire peste toate notele omului, nu ceva ce se poate ajusta cu
 * un plus în browser.
 *
 * Se trimit doar la `scrie`, adică atunci când cererea vine de pe profil și e
 * ceva de redesenat acolo. De pe pagina evenimentului n-are cine să le
 * folosească.
 */
$raspuns = [
    'ok'     => true,
    'membru' => $tintaId,
    'stele'  => $stele,
    'mesaj'  => $fapta === 'scrie' ? 'Evaluarea ta a fost trimisă.' : 'Nota ta a fost trimisă.',
];

if ($fapta === 'scrie') {
    $raspuns['rezumat']  = randeazaRezumatEvaluari(rezumatEvaluari($tintaId));
    $raspuns['evaluari'] = randeazaEvaluari(evaluarilePrimite($tintaId));
}

raspunsJson($raspuns);
