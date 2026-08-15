<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — ce s-a mai spus în cameră.
 *
 * Întrebarea pe care o pune browserul din când în când, cât e chatul deschis.
 * Doar CITEȘTE, deci primește GET și n-are token CSRF: tokenul apără de fapte
 * făcute în numele cuiva fără voia lui, iar aici nu se face nimic.
 *
 * Cont cere, totuși — spre deosebire de api/lista-evenimente.php, care e
 * singurul cu adevărat public. Chatul se citește doar de cine e înăuntru.
 *
 * Întoarce mesajele GATA DESENATE, din aceleași funcții care scriu pagina la
 * încărcare (inc/chat.php). JS-ul le lipește la coada listei și atât.
 *
 * Parametri:
 *   camera     — numele din adresă; ce nu duce nicăieri înseamnă „General"
 *   dupa       — cel mai mare id pe care îl are deja pe ecran
 *   sters_dupa — clipa până la care știe ce s-a șters (`moment` din răspunsul
 *                dinainte, adică ceasul SERVERULUI, nu al lui)
 */

require_once __DIR__ . '/../inc/chat.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    raspunsJson(['ok' => false, 'mesaj' => 'Metodă nepermisă.'], 405);
}

$membru = membruCurent();

if ($membru === null) {
    raspunsJson(['ok' => false, 'mesaj' => 'Trebuie să fii conectat.'], 401);
}

opresteDacaTrebuieParolaNoua(true);

$membruId = (int) $membru['id'];
$eStaff   = esteStaff($membru);

$camera = cameraCeruta($_GET['camera'] ?? null, $membruId, $eStaff);

/**
 * Clipa la care s-a terminat de socotit răspunsul ăsta.
 *
 * Se citește ÎNAINTE de întrebări, nu după: dacă cineva șterge un mesaj chiar
 * în timpul lor, mai bine îl trimitem încă o dată la întrebarea următoare decât
 * să-l sărim de tot. O ștergere spusă de două ori nu strică nimic — JS-ul caută
 * un mesaj care nu mai e în pagină și trece mai departe.
 */
$moment = acum();

$dupa = max(0, (int) ($_GET['dupa'] ?? 0));

/**
 * Prima întrebare a unei file proaspete („dupa=0") nu trebuie să care toată
 * camera înapoi: pagina și-a tipărit deja mesajele. Se ajunge aici doar dacă
 * JS-ul a pierdut șirul, și atunci ultimele CHAT_MESAJE_DEODATA sunt exact ce-i
 * trebuie ca să se așeze la loc.
 */
$mesaje = $dupa > 0
    ? mesajeDupa($camera['cheie'], $dupa)
    : mesajeleCamerei($camera['cheie']);

$sterseDupa = trim((string) ($_GET['sters_dupa'] ?? ''));

// Ce vine din adresă nu ajunge în bază așa cum a venit: numai o clipă scrisă
// exact în formatul coloanei trece mai departe.
if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/', $sterseDupa) !== 1) {
    $sterseDupa = '';
}

$sterse = mesajeSterseDupa($camera['cheie'], str_replace('T', ' ', $sterseDupa));

/** Cel mai mare id din teanc — cursorul cu care se întoarce browserul. */
$ultimId = $dupa;

foreach ($mesaje as $m) {
    $ultimId = max($ultimId, (int) $m['id']);
}

raspunsJson([
    'ok'      => true,
    'html'    => randeazaMesajeChat($mesaje, [
        'membru_id' => $membruId,
        'e_staff'   => $eStaff,
    ]),
    'cate'    => count($mesaje),
    'ultim_id' => $ultimId,
    'sterse'  => $sterse,
    'moment'  => $moment,

    // Din ce cameră vin. Browserul o compară cu a lui: dacă omul a schimbat
    // camera exact cât zbura întrebarea, răspunsul ăsta nu mai e al lui.
    'camera'  => $camera['cheie'],
]);
