<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — scoaterea cuiva de pe lista de participanți.
 *
 * O poate face organizatorul evenimentului sau staff-ul. Cere un motiv, care
 * pleacă întreg în e-mailul primit de omul scos, și poate închide ușa: cine e
 * scos cu bifa pusă nu se mai poate înscrie la evenimentul ăsta.
 *
 * Lista se întoarce GATA DESENATĂ, din aceleași funcții care o scriu la
 * încărcarea paginii (inc/interese.php) — ca la comentarii, și din aceleași
 * motive: un singur loc care desenează, și niciun text venit de la om lipit în
 * pagină fără trecerea prin h().
 */

require_once __DIR__ . '/../inc/interese.php';
require_once __DIR__ . '/../inc/email.php';

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
$eStaff   = esteStaff($membru);

/* ========================= 1. Care eveniment ========================= */

$slug      = trim((string) ($date['slug'] ?? ''));
$eveniment = evenimentDupaSlug($slug);

if ($eveniment === null
    || !poateVedeaEvenimentul($eveniment, $membruId, $eStaff)) {
    raspunsJson(['ok' => false, 'mesaj' => 'Evenimentul nu mai există.'], 404);
}

$evenimentId    = (int) $eveniment['id'];
$organizatorId  = (int) $eveniment['membru_id'];
$eOrganizatorul = $membruId === $organizatorId;

/* ===================== 2. Are voie să facă asta? ===================== */

/**
 * Lista e a organizatorului. Staff-ul umblă la ea ca și cum ar fi a lui —
 * aceeași regulă ca la comentarii.
 *
 * În pagină, butoanele nici nu se desenează pentru altcineva. Aici e regula:
 * cererea poate veni de oriunde, nu doar de pe butoanele noastre.
 */
if (!$eOrganizatorul && !$eStaff) {
    raspunsJson(['ok' => false, 'mesaj' => 'Nu poți umbla la lista asta.'], 403);
}

/**
 * În ce calitate a făcut-o, scris ACUM, nu socotit mai târziu.
 *
 * Cine e și staff, și organizator, apare ca „organizator": e evenimentul lui,
 * iar asta spune mai mult celui care primește e-mailul.
 */
$rol = $eOrganizatorul ? 'organizator' : 'staff';

/**
 * La un eveniment neaprobat nu există listă de făcut curat.
 *
 * Pagina lui se deschide doar pentru organizator sau pentru staff, iar
 * api/interes.php oricum nu lasă pe nimeni să se înscrie acolo.
 */
if (!evenimentPublicat($eveniment)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Evenimentul nu e publicat, deci nu are listă.',
    ], 409);
}

/**
 * După ce a început, lista nu se mai atinge.
 *
 * Nici de organizator, nici de staff. De aici încolo lista e istoria
 * evenimentului: cine a fost trecut pe ea a fost, iar dacă n-a venit, unealta
 * potrivită e „Nu s-a prezentat" — care lasă urmă pe profilul omului — nu
 * ștergerea lui din poveste.
 */
if (evenimentAInceput($eveniment)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Evenimentul a început, lista nu se mai schimbă.',
    ], 409);
}

/* ========================= 3. Pe cine scoate ======================== */

$tintaId = (int) ($date['membru'] ?? 0);

if ($tintaId <= 0) {
    raspunsJson(['ok' => false, 'mesaj' => 'Nu știu pe cine să scot.'], 422);
}

/**
 * Organizatorul nu se scoate de pe lista lui.
 *
 * Nici de el însuși — n-ar rămâne nimeni care să răspundă de eveniment — nici
 * de staff, care are altă unealtă pentru un eveniment care nu-i place: îl
 * poate anula cu totul, iar atunci sunt înștiințați toți, nu unul singur.
 */
if ($tintaId === $organizatorId) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Organizatorul nu poate fi scos de pe lista evenimentului lui.',
    ], 409);
}

if (interesulMeu($evenimentId, $tintaId) !== 'participant') {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Omul ăsta nu mai e pe lista de participanți.',
    ], 409);
}

/* =========================== 4. Motivul ============================= */

$rezultat = verificaMotivExcludere($date['motiv'] ?? '');

if ($rezultat['eroare'] !== '') {
    raspunsJson(['ok' => false, 'erori' => ['motiv' => $rezultat['eroare']]], 422);
}

$interzis = !empty($date['interzis']);

/* ====================== 5. Se face, în ordine ======================= */

/**
 * Adresa se citește ÎNAINTE de scoatere.
 *
 * După, rândul din `interese_evenimente` nu mai e — și, mai ales, dacă am
 * lăsat citirea pe după și omul și-ar fi șters contul între timp, am fi rămas
 * cu scoaterea făcută și fără cui să-i spunem.
 */
$omul = omulDeInstiintat($tintaId);

excludeParticipant($evenimentId, $tintaId, $membruId, $rol, $rezultat['text'], $interzis);

/**
 * E-mailul pleacă DUPĂ scoatere, nu înainte.
 *
 * Invers, un e-mail trimis și o scriere picată i-ar fi spus omului o
 * neadevărat. Așa, dacă e-mailul nu pleacă, scoaterea rămâne făcută — iar asta
 * se poate îndrepta, spre deosebire de un mesaj trimis degeaba.
 *
 * Nu oprim răspunsul dacă nu s-a putut trimite: fapta e făcută, iar cel care a
 * apăsat trebuie să vadă lista nouă, nu o eroare despre serverul de e-mail.
 */
$instiintat = false;

if ($omul !== null) {
    $instiintat = emailExcludereParticipant(
        (string) $omul['email'],
        (string) $omul['prenume'],
        (string) $omul['sex'],
        (string) $eveniment['titlu'],
        urlIntreg(urlEveniment((string) $eveniment['slug'])),
        $rol,
        $rezultat['text'],
        $interzis
    );

    if (!$instiintat) {
        error_log('PulsulOrasului: nu am putut trimite e-mailul de excludere '
                . 'pentru membrul #' . $tintaId . ' la evenimentul #' . $evenimentId);
    }
}

/* ========================== 6. Ce se întoarce ======================= */

/**
 * Numerele și listele se citesc din bază DUPĂ schimbare, nu se socotesc în JS
 * pornind de la ce era pe ecran: între încărcarea paginii și apăsare pot intra
 * sau ieși alții, iar un număr scăzut cu unu în browser ar fi rămas greșit
 * până la următoarea reîncărcare.
 */
$numar = numaraInterese($evenimentId);

/**
 * Vorba de pe ecran se face după om, ca peste tot pe site.
 *
 * „L-am scos de pe listă" despre o femeie e o scăpare care se vede din prima,
 * mai ales de cea despre care e vorba. Sexul îl avem deja: a fost citit ca să
 * i se poată scrie cum trebuie și în e-mail (vezi emailExcludereParticipant).
 *
 * Când contul e șters între timp și $omul e null, rămâne o vorbă fără gen —
 * nu se ghicește.
 */
$eF = ($omul['sex'] ?? '') === 'F';

if ($omul === null) {
    $mesaj = $instiintat
        ? 'Am scos omul de pe listă și l-am înștiințat pe e-mail.'
        : 'Am scos omul de pe listă.';
} elseif ($eF) {
    $mesaj = $instiintat
        ? 'Am scos-o de pe listă și am înștiințat-o pe e-mail.'
        : 'Am scos-o de pe listă.';
} else {
    $mesaj = $instiintat
        ? 'L-am scos de pe listă și l-am înștiințat pe e-mail.'
        : 'L-am scos de pe listă.';
}

raspunsJson([
    'ok'          => true,
    'membru'      => $tintaId,
    'numar'       => $numar,
    // Amândouă listele din taburi, în aceeași formă ca la api/interes.php, ca
    // main.js să le aplice cu o singură funcție. Cu butoane de scoatere: cine
    // a apăsat aici e organizatorul sau staff-ul, care le are.
    // Aici ajunge numai organizatorul sau staff-ul (se verifică mai sus), deci
    // tot ei sunt și cei care văd numerele — dar se întreabă din nou, cu
    // aceeași funcție ca pagina: un steag scris de mână s-ar fi despărțit de
    // regulă la prima schimbare.
    'panouri'     => raspunsulPanourilor($eveniment, true, null,
                                         poateVedeaTelefoanele($eveniment, $membru)),
    // Chipurile de sub butoane se schimbă și ele: omul scos nu mai are ce
    // căuta acolo, iar „încă 84 de persoane" a scăzut cu unu.
    'chipuri'     => randeazaChipuri($evenimentId),
    'instiintat'  => $instiintat,
    'mesaj'       => $mesaj,
]);
