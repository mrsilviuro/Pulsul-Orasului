<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — anularea unui eveniment.
 *
 * Primește slugul și motivul, trece evenimentul în starea „anulat", dă de
 * veste tuturor celor de pe listă și răspunde cu unde să meargă omul mai
 * departe. Nu se șterge nimic: rândul rămâne pentru staff, iar curățenia lui
 * e o treabă de mai târziu.
 *
 * Confirmarea s-a dat deja în pagină, în două trepte; aici se verifică dacă
 * are voie și dacă motivul e scris cum trebuie.
 */

require_once __DIR__ . '/../inc/evenimente.php';
require_once __DIR__ . '/../inc/interese.php';
require_once __DIR__ . '/../inc/email.php';

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

/**
 * Oamenii se citesc ÎNAINTE de anulare, nu după.
 *
 * Nu fiindcă anularea ar șterge rândurile din `interese_evenimente` — nu le
 * șterge — ci fiindcă asta e ordinea care rămâne adevărată și în ziua în care
 * se va face curățenia de care vorbește anuleazaEveniment(). Cine citește
 * lista înainte n-are cum să rămână cu mâna goală.
 *
 * Organizatorul nu e printre ei: el tocmai a apăsat butonul, iar la publicare
 * s-a trecut singur pe lista de participanți.
 */
$evenimentId  = (int) $eveniment['id'];
$deInstiintat = oameniiDeInstiintatLaAnulare($evenimentId, (int) $membru['id']);

anuleazaEveniment($eveniment, $motiv['text']);

/**
 * Vestea pleacă DUPĂ ce anularea e scrisă în bază, nu înainte.
 *
 * Aceeași ordine ca la scoaterea cuiva de pe listă (api/exclude-participant.php):
 * un e-mail trimis peste o scriere care apoi pică ar spune oamenilor un
 * neadevăr — „nu mai are loc", pentru un eveniment care e tot acolo. Invers,
 * dacă scrierea reușește și e-mailul nu pleacă, anularea rămâne făcută, iar
 * asta se poate îndrepta.
 *
 * Nu oprim răspunsul dacă vreun mesaj nu pleacă: fapta e făcută, iar
 * organizatorul trebuie să vadă că evenimentul e anulat, nu o eroare despre
 * serverul de e-mail. Ce n-a mers ajunge în log.
 */
$adresaSite  = urlIntreg('index.php');
$candAvutLoc = dataLunga((string) ($eveniment['data_eveniment'] ?? ''));

$trimise = 0;
$picate  = 0;

foreach ($deInstiintat as $om) {
    $plecat = emailAnulareEveniment(
        (string) $om['email'],
        (string) $om['prenume'],
        (string) $eveniment['titlu'],
        $candAvutLoc,
        $motiv['text'],
        $adresaSite,
        (string) $om['stare'] === 'participant'
    );

    $plecat ? $trimise++ : $picate++;
}

if ($picate > 0) {
    error_log('PulsulOrasului: ' . $picate . ' din ' . count($deInstiintat)
            . ' e-mailuri de anulare n-au plecat pentru evenimentul #' . $evenimentId);
}

// Mesajul îl citește inc/subsol.php pe pagina următoare și îl arată o
// singură dată, ca la intrarea cu Google.
pornesteSesiunea();
$_SESSION['mesaj_bun'] = 'Evenimentul a fost anulat.';

raspunsJson([
    'ok'       => true,
    'redirect' => 'profil.php',
    'mesaj'    => 'Evenimentul a fost anulat.',

    // Câți au aflat — pagina nu-l arată deocamdată, dar proba îl citește, iar
    // organizatorul are dreptul să știe că vestea chiar a plecat.
    'instiintati' => $trimise,
]);
