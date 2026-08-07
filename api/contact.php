<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — primirea mesajelor din formularul de contact.
 *
 * Mesajul se scrie în baza de date ȘI pleacă pe e-mail. Un e-mail se poate
 * pierde în „Spam", iar un rând în bază nu sună când ajunge — fiecare acoperă
 * slăbiciunea celuilalt.
 *
 * Vizitatorii fără cont au voie să scrie: e o pagină de contact, nu una de
 * membri. De aceea are nevoie de apărare împotriva roboților.
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/validare.php';
require_once __DIR__ . '/../inc/email.php';

/** Câte mesaje poate trimite un vizitator fără cont, într-o oră, de la același IP. */
const MESAJE_PE_ORA_PE_IP = 5;

/** Câte minute între două mesaje ale aceluiași membru. */
const MINUTE_INTRE_MESAJE = 5;

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

/* =========================== 1. HONEYPOT ============================== */

/**
 * Câmpul pe care niciun om nu-l vede și niciun om nu-l completează.
 *
 * Roboții completează tot ce găsesc în formular. Dacă a venit ceva aici, n-a
 * fost o persoană.
 *
 * Îi răspundem cu „ok", nu cu o eroare: dacă i-am spune că l-am prins, cine
 * scrie robotul ar afla din prima încercare că există capcana și ar ocoli-o
 * mâine. Așa, robotul pleacă mulțumit, iar mesajul nu ajunge nicăieri.
 */
$capcana = is_string($date['website'] ?? null) ? trim($date['website']) : '';

if ($capcana !== '') {
    scrieInLog('spam-contact.log',
        'capcană completată de la ' . ($_SERVER['REMOTE_ADDR'] ?? '?')
        . ' — a scris „' . mb_substr($capcana, 0, 60) . '"'
        . ($membru !== null ? ' (membrul #' . (int) $membru['id'] . ')' : ''));

    raspunsJson(['ok' => true, 'mesaj' => 'Mesajul a fost trimis.']);
}

/* ======================== 2. CÂT DE DES SCRIE ========================= */

/**
 * Limitarea numără chiar rândurile din mesaje_contact, nu ține o socoteală
 * separată.
 *
 * E același tipar ca la înregistrare, unde numărul de conturi noi de la un IP
 * se numără direct în „membri". Nu apare un al doilea sistem de ținut minte,
 * iar limita nu poate rămâne niciodată nepotrivită cu realitatea: dacă rândul
 * există, a fost numărat.
 *
 * Nu atingem incercari_autentificare, deși are deja o limită pe IP: acolo se
 * numără greșeli de parolă și se ajunge la blocarea intrării în cont. Un om
 * care scrie de multe ori pe pagina de contact n-are de ce să rămână pe
 * dinafara contului.
 */
if ($membru !== null) {
    // Membrul e cunoscut, deci limita poate fi mai largă: o dată la câteva
    // minute, cât să nu poată ține butonul apăsat.
    $q = db()->prepare(
        'SELECT COUNT(*) FROM mesaje_contact WHERE membru_id = ? AND creat_la > ?'
    );
    $q->execute([(int) $membru['id'], acumMinus(MINUTE_INTRE_MESAJE)]);

    if ((int) $q->fetchColumn() > 0) {
        raspunsJson([
            'ok'    => false,
            'mesaj' => 'Ai trimis un mesaj chiar acum. Mai așteaptă câteva minute '
                     . 'înainte de următorul.',
        ], 429);
    }
} else {
    $ip = ipBinar();

    if ($ip !== null) {
        $q = db()->prepare(
            'SELECT COUNT(*) FROM mesaje_contact WHERE ip = ? AND creat_la > ?'
        );
        $q->execute([$ip, acumMinus(60)]);

        if ((int) $q->fetchColumn() >= MESAJE_PE_ORA_PE_IP) {
            raspunsJson([
                'ok'    => false,
                'mesaj' => 'Ai trimis prea multe mesaje recent. Te rugăm încearcă mai târziu.',
            ], 429);
        }
    }
}

/* ========================== 3. VERIFICĂRILE =========================== */

/**
 * Pentru un membru conectat, numele, adresa și telefonul vin din cont, nu din
 * formular.
 *
 * În pagină câmpurile sunt blocate, dar asta e doar comoditate: cine trimite
 * cererea de-a dreptul poate scrie în ele orice. Dacă le-am crede, oricine
 * și-ar putea semna mesajul cu numele altui membru.
 */
$dinCont = [];

if ($membru !== null) {
    $q = db()->prepare('SELECT nume, prenume, email, telefon FROM membri WHERE id = ? LIMIT 1');
    $q->execute([(int) $membru['id']]);
    $dinCont = $q->fetch() ?: [];
}

$rezultat = verificaContact($date, $dinCont);

if ($rezultat['erori'] !== []) {
    raspunsJson(['ok' => false, 'erori' => $rezultat['erori']], 422);
}

$curat = $rezultat['curat'];

/* ========================== 4. SALVAREA =============================== */

$u = db()->prepare(
    'INSERT INTO mesaje_contact
        (membru_id, nume, prenume, email, telefon, mesaj, ip, creat_la)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

$u->execute([
    $membru === null ? null : (int) $membru['id'],
    $curat['nume'],
    $curat['prenume'],
    $curat['email'],
    $curat['telefon'],
    $curat['mesaj'],
    ipBinar(),
    acum(),   // ceasul e al PHP-ului, niciodată NOW()
]);

/**
 * Membrul care n-avea telefon în cont și tocmai l-a scris aici îl păstrează.
 *
 * E același număr, la aceeași persoană — n-are rost să-l ceară a doua oară.
 */
if ($membru !== null && (string) ($dinCont['telefon'] ?? '') === '') {
    db()->prepare('UPDATE membri SET telefon = ? WHERE id = ?')
        ->execute([$curat['telefon'], (int) $membru['id']]);
}

/* ========================== 5. E-MAILUL =============================== */

// Dacă e-mailul nu pleacă, mesajul e deja în bază: omul nu trebuie pus să
// scrie din nou pentru o piedică ce nu e a lui.
emailMesajDeContact($curat, $membru !== null ? (int) $membru['id'] : null);

raspunsJson([
    'ok'    => true,
    'mesaj' => 'Mesajul a fost trimis. Îți mulțumim — revenim cu un răspuns în cel mai scurt timp.',
]);
