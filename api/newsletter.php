<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — „dă-mi de veste când deschideți".
 *
 * Singurul lucru pe care îl poate face un vizitator cât timp site-ul e în
 * lucru: își lasă adresa pe pagina de așteptare (constructie.php).
 *
 * Rămâne deschis și după deschiderea site-ului — nu e legat de lacăt, doar
 * folosit mai ales de el.
 */

require_once __DIR__ . '/../inc/auth.php';

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
        'mesaj' => 'Reîncarcă pagina și încearcă din nou.',
    ], 419);
}

/**
 * Capcana pentru roboți: un câmp pe care omul nu-l vede și nu-l completează
 * niciodată, fiindcă e ascuns din CSS. Completat înseamnă că n-a fost om.
 *
 * Se răspunde cu „gata", nu cu o eroare: un robot căruia îi spui că l-ai prins
 * încearcă altfel, unul care crede că a reușit pleacă mai departe. Același
 * tipar ca la formularul de contact.
 */
$capcana = is_string($date['website'] ?? null) ? trim($date['website']) : '';

if ($capcana !== '') {
    scrieInLog('spam-newsletter.log',
        'capcană completată de la ' . ($_SERVER['REMOTE_ADDR'] ?? '?')
        . ' — a scris „' . mb_substr($capcana, 0, 60) . '"');

    raspunsJson(['ok' => true, 'mesaj' => 'Gata! Îți dăm de veste imediat ce deschidem.']);
}

/* ------------------------- pe listă cu ea ----------------------------- */

/**
 * Verificarea adresei, limita pe IP și scrierea stau în inscrieLaVesti(), din
 * inc/constructie.php — aceeași funcție pe care o cheamă și constructie.php
 * când pagina n-are JavaScript. Scrise în amândouă, s-ar fi despărțit la prima
 * schimbare.
 */
$rezultat = inscrieLaVesti($date['email'] ?? null);

raspunsJson(
    ['ok' => $rezultat['ok'], 'mesaj' => $rezultat['mesaj']],
    $rezultat['cod']
);
