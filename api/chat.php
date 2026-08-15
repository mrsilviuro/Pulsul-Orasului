<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — ce se FACE în chat: se scrie un mesaj, se șterge unul.
 *
 * Cititul e în api/chat-mesaje.php, care primește GET și n-are token. Aici se
 * schimbă starea, deci se cere POST și CSRF, ca peste tot pe site.
 *
 * Mesajul se întoarce mereu GATA DESENAT, din aceleași funcții care scriu
 * pagina la încărcare (inc/chat.php).
 */

require_once __DIR__ . '/../inc/chat.php';

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

/**
 * Fără cont nu se scrie și nu se șterge nimic.
 *
 * La chat nu se ajunge fără cont — pagina însăși cheamă cereIntrare(). Aici se
 * ajunge doar dacă sesiunea a expirat între încărcarea paginii și apăsare.
 * Codul 401 e semnul după care main.js îl trimite la intrare, cu întoarcere fix
 * în camera în care era.
 */
$membru = membruCurent();

if ($membru === null) {
    raspunsJson(['ok' => false, 'mesaj' => 'Trebuie să fii conectat.'], 401);
}

opresteDacaTrebuieParolaNoua(true);

$membruId = (int) $membru['id'];
$eStaff   = esteStaff($membru);

/**
 * Camera se socotește din nou aici, cu aceeași funcție ca la deschiderea
 * paginii — nu se ia pe încredere de la browser.
 *
 * Fără asta, o cerere scrisă de mână ar fi putut scrie în camera unui eveniment
 * ascuns, la care omul n-are acces: cheia camerei ar fi venit din JS, iar
 * serverul ar fi lipit-o în INSERT fără să se uite la ea. Așa, ce trece prin
 * cameraCeruta() e ori o cameră la care are voie, ori „General".
 */
$camera = cameraCeruta($date['camera'] ?? null, $membruId, $eStaff);

$fapta = (string) ($date['fapta'] ?? '');

switch ($fapta) {
    case 'trimite':
        trimiteMesajul($date, $camera, $membruId, $eStaff);
        break;

    case 'sterge':
        stergeMesajul($date, $camera, $membruId, $eStaff);
        break;

    default:
        raspunsJson(['ok' => false, 'mesaj' => 'Nu știu ce să fac.'], 400);
}

/* ========================== 1. SCRIEREA ============================== */

function trimiteMesajul(array $date, array $camera, int $membruId, bool $eStaff): void
{
    /**
     * Verificarea e aici, pe server, nu în JS. Cea din browser e confort: taie
     * drumul până la server pentru un mesaj gol. Singura care contează e asta —
     * regula 4 din CLAUDE.md.
     */
    $verificat = verificaMesajChat($date['mesaj'] ?? null);

    if ($verificat['eroare'] !== '') {
        raspunsJson(['ok' => false, 'mesaj' => $verificat['eroare']], 422);
    }

    /**
     * Limita se pune ÎNAINTE de scriere, altfel n-ar mai avea ce opri.
     *
     * Codul 429 („prea multe cereri") e cel după care main.js știe să nu arate
     * o eroare roșie, ci să aștepte și să lase mesajul în casetă: omul n-a
     * greșit cu nimic, doar a scris repede.
     */
    $asteptare = asteptareChat($membruId);

    if ($asteptare > 0) {
        raspunsJson([
            'ok'        => false,
            'mesaj'     => 'Mai încet — mai așteaptă ' . $asteptare
                         . ($asteptare === 1 ? ' secundă.' : ' secunde.'),
            'asteptare' => $asteptare,
        ], 429);
    }

    $id = salveazaMesajChat($camera['cheie'], $membruId, $verificat['text']);

    $mesaj = mesajChatDupaId($id);

    if ($mesaj === null) {
        raspunsJson(['ok' => false, 'mesaj' => 'Mesajul nu s-a putut scrie.'], 500);
    }

    raspunsJson([
        'ok'   => true,
        'id'   => $id,
        'html' => randeazaMesajChat($mesaj, [
            'membru_id' => $membruId,
            'e_staff'   => $eStaff,
        ]),
    ]);
}

/* ========================== 2. ȘTERGEREA ============================= */

function stergeMesajul(array $date, array $camera, int $membruId, bool $eStaff): void
{
    $id = (int) ($date['id'] ?? 0);

    $mesaj = $id > 0 ? mesajChatDupaId($id) : null;

    /**
     * Mesajul trebuie să fie DIN CAMERA ÎN CARE STĂ OMUL, iar camera aceea a
     * trecut deja prin cameraCeruta() — adică e una la care are voie.
     *
     * Fără perechea asta, un om de casă ar fi putut șterge, cu id-uri luate la
     * rând dintr-o consolă, mesaje din camere pe care nu le-a deschis niciodată.
     * Are dreptul să șteargă, dar acolo unde e și citește, nu pe nevăzute.
     *
     * „Nu există" și „nu e în camera asta" primesc același răspuns, ca peste tot
     * pe site: din chatul general nu trebuie să se poată afla, numărând id-uri,
     * ce s-a scris în camera unui eveniment la care n-ai acces.
     */
    if ($mesaj === null || (string) $mesaj['camera'] !== $camera['cheie']) {
        raspunsJson(['ok' => false, 'mesaj' => 'Mesajul nu mai există.'], 404);
    }

    if (!poateStergeMesajChat($mesaj, $membruId, $eStaff)) {
        raspunsJson(['ok' => false, 'mesaj' => 'Nu ai voie să ștergi mesajul ăsta.'], 403);
    }

    /**
     * Mesajul șters de altcineva între timp — de pe a doua filă a aceluiași om
     * de casă — nu e o eroare de arătat. S-a întâmplat ce trebuia să se
     * întâmple, iar pagina scoate bula la fel.
     */
    stergeMesajChat($id, $membruId);

    raspunsJson(['ok' => true, 'id' => $id]);
}
