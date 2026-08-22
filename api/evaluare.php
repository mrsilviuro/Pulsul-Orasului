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

    // A doua oară n-are ce adăuga: în pagină butonul nici nu se mai desenează.
    if (esteNeprezentat($evenimentId, $tintaId)) {
        raspunsJson([
            'ok'    => false,
            'mesaj' => 'E deja însemnat ca neprezentat.',
        ], 409);
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

/**
 * Cine n-a venit nu se mai notează de nimeni.
 *
 * N-are ce judeca nimeni la un om care n-a fost acolo. Iar regula asta îl
 * privește mai ales pe organizator: fără ea, cel care a pus însemnarea ar
 * putea alege peste o săptămână cinci stele și ar șterge cu ele exact ce a
 * scris — poate după o vorbă bună de la cineva.
 *
 * În pagină, rândul lui nu mai are nici stele, nici „Lasă și câteva cuvinte".
 * Aici e regula: cererea poate veni de oriunde.
 */
if (esteNeprezentat($evenimentId, $tintaId)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'E însemnat ca neprezentat, nu mai poate fi notat.',
    ], 409);
}

$stele = stelePrimite($date['stele'] ?? 0);

if ($stele === 0) {
    raspunsJson(['ok' => false, 'mesaj' => 'Alege câte stele îi dai, de la 1 la 5.'], 422);
}

/**
 * Textul, doar când vine dintr-un formular de părere.
 *
 * De pe stelele din dreptul cuiva se dă o notă dintr-o apăsare, fără vorbe —
 * și `null` înseamnă acolo „nu atinge ce scria înainte", nu „șterge". Cine a
 * scris un text și apoi schimbă stelele nu are de unde să știe că altfel și-ar
 * șterge vorbele. Vezi salveazaEvaluare().
 *
 * La `scrie`, dimpotrivă: acolo omul a apăsat „Trimite" pe o casetă pe care o
 * vede, iar goală înseamnă „îmi retrag vorbele". De aceea pleacă mai jos cu
 * $eScriere.
 */
$text = null;

if ($fapta === 'scrie') {
    $rezultat = verificaTextEvaluare($date['text'] ?? '');

    if ($rezultat['eroare'] !== '') {
        raspunsJson(['ok' => false, 'erori' => ['text' => $rezultat['eroare']]], 422);
    }

    // Gol înseamnă „doar stele": caseta nu e obligatorie.
    $text = $rezultat['text'] !== '' ? $rezultat['text'] : null;
}

salveazaEvaluare($evenimentId, $tintaId, $membruId, $stele, $text, false, $fapta === 'scrie');

/**
 * VESTEA CĂ I S-A SCRIS CEVA — O SINGURĂ DATĂ.
 *
 * Numai la o părere SCRISĂ. Stelele rămân anonime și tăcute — vezi
 * omDeInstiintatLaFeedback(), unde stă regula întreagă, și
 * sql/027-instiintari-feedback.sql, unde stă bifa.
 *
 * ȘI NUMAI LA CEA DINTÂI. Vestea pleca, o vreme, la fiecare text schimbat: în
 * viață, omul își îndreaptă vorbele — scrie în grabă, vede o greșeală, se
 * răzgândește asupra unui cuvânt — iar zece îndreptări însemnau zece e-mailuri
 * despre ACEEAȘI părere. Ștampila (`evaluari.instiintat_la`, sql/028) se pune
 * o dată și nu se mai șterge, nici când omul își retrage vorbele: altfel
 * „scrie – șterge – scrie" ar fi devenit felul de a trimite oricâte mesaje.
 *
 * ORDINEA CONTEAZĂ. Se ștampilează ÎNTÂI, și abia dacă ștampila a prins se
 * trimite. Invers, două file deschise deodată ar fi trimis amândouă înainte ca
 * vreuna să apuce să însemne ceva. Iar dacă poșta pică după ștampilă, se pierde
 * un mesaj — mai bine decât un potop, și părerea rămâne scrisă oricum.
 *
 * Pleacă DUPĂ scriere: un e-mail care spune „ți-a scris cineva" pentru o
 * părere care n-a intrat în bază e mai rău decât nimic.
 */
if ($fapta === 'scrie' && $text !== null) {
    $celNotat = omDeInstiintatLaFeedback($tintaId, $membruId);

    if ($celNotat !== null
        && insemneazaVesteaTrimisa($evenimentId, $tintaId, $membruId)) {

        require_once __DIR__ . '/../inc/email.php';

        emailFeedbackNou(
            (string) $celNotat['email'],
            (string) $celNotat['prenume'],
            numeAfisat((string) $membru['nume'], (string) $membru['prenume']),
            (string) $eveniment['titlu'],
            $text,
            urlIntreg(urlProfil((string) ($celNotat['permalink'] ?? '')))
        );
    }
}

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
