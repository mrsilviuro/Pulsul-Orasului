<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — schimbarea parolei din cont.
 *
 * Se folosește în două situații:
 *   - după intrarea cu parola temporară (atunci parola veche nu se cere,
 *     fiindcă tocmai aia e uitată);
 *   - oricând, din cont, caz în care se cere și parola de acum.
 */

require_once __DIR__ . '/../inc/auth.php';
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

/**
 * Aici NU se cheamă opresteDacaTrebuieParolaNoua(): asta e chiar pagina care
 * scoate omul din starea aia.
 */
$dupaTemporara = trebuieParolaNoua();

/* ------------------------------ Parolele ------------------------------ */

$veche = is_string($date['parola_veche'] ?? null) ? $date['parola_veche'] : '';
$noua  = is_string($date['parola'] ?? null) ? $date['parola'] : '';
$noua2 = is_string($date['parola_confirmare'] ?? null) ? $date['parola_confirmare'] : '';

$erori = [];

/**
 * Parola de acum se cere doar când omul o știe.
 *
 * Când a intrat cu cea temporară, cererea n-ar avea sens — a ajuns aici tocmai
 * pentru că nu și-o amintește. Locul ei e luat de faptul că a putut citi
 * e-mailul trimis pe adresa contului.
 */
$q = db()->prepare('SELECT parola_hash FROM membri WHERE id = ? LIMIT 1');
$q->execute([(int) $membru['id']]);
$hashVechi = $q->fetchColumn();

/**
 * Cine a deschis contul cu Google n-are parolă la noi — parola_hash e NULL.
 *
 * Lui nu avem ce parolă veche să-i cerem, deci își pune prima parolă direct.
 * Nu e o portiță: ca să ajungă aici, a trecut deja prin Google și e conectat.
 * Cheia contului lui e contul de Google, iar acela e verificat la fiecare
 * intrare.
 */
$areParola = is_string($hashVechi) && $hashVechi !== '';

if (!$dupaTemporara && $areParola) {
    if ($veche === '') {
        $erori['parola_veche'] = 'Scrie parola de acum.';
    } elseif (!password_verify($veche, (string) $hashVechi)) {
        $erori['parola_veche'] = 'Parola de acum nu e corectă.';
    }
}

if ($noua === '') {
    $erori['parola'] = 'Alege o parolă.';
} elseif (mb_strlen($noua, 'UTF-8') < PAROLA_MIN) {
    $erori['parola'] = 'Parola trebuie să aibă cel puțin ' . PAROLA_MIN . ' caractere.';
} elseif (strlen($noua) > PAROLA_MAX) {
    // bcrypt taie tot ce trece de 72 de octeți, deci o parolă mai lungă ar
    // crea impresia falsă că ultimele caractere contează.
    $erori['parola'] = 'Parola e prea lungă (cel mult ' . PAROLA_MAX . ' de caractere).';
}

if ($noua !== '' && $noua2 !== $noua) {
    $erori['parola_confirmare'] = 'Cele două parole nu coincid.';
}

// Parola nouă nu poate fi cea veche: altfel „schimbarea" n-ar schimba nimic.
if ($erori === [] && $veche !== '' && $noua === $veche) {
    $erori['parola'] = 'Alege o parolă diferită de cea de acum.';
}

if ($erori !== []) {
    raspunsJson(['ok' => false, 'erori' => $erori], 422);
}

/* ------------------------------ Salvarea ------------------------------ */

$hash = password_hash($noua, PASSWORD_DEFAULT);

if ($hash === false) {
    raspunsJson(['ok' => false, 'mesaj' => 'Nu am putut salva parola. Încearcă din nou.'], 500);
}

// Odată cu parola nouă dispare și cea temporară, dacă mai era vreuna în
// așteptare: un e-mail vechi nu mai trebuie să deschidă contul.
$u = db()->prepare(
    'UPDATE membri
        SET parola_hash = ?, parola_schimbata_la = ?,
            parola_temporara_hash = NULL, parola_temporara_expira = NULL,
            parola_temporara_incercari = 0
      WHERE id = ?'
);
$u->execute([$hash, acum(), (int) $membru['id']]);

/**
 * Parolă nouă = toate dispozitivele ținute minte sunt date afară.
 *
 * Cine își schimbă parola o face de multe ori tocmai fiindcă bănuiește că
 * altcineva i-a intrat în cont. Dacă am lăsa amintirile vechi în picioare,
 * intrusul ar rămâne conectat treizeci de zile, fără să aibă nevoie de parola
 * cea nouă.
 *
 * Dispozitivul de pe care se schimbă parola e ținut minte din nou, curat, ca
 * omul să nu se trezească dat afară de propria lui grijă.
 */
$tineaMinte = !empty($_SESSION['tine_minte']);
uitaToateAle((int) $membru['id']);

if ($tineaMinte) {
    tineMinteAcest((int) $membru['id']);
}

gataCuParolaTemporara();

/**
 * Identificator nou de sesiune după schimbarea parolei.
 *
 * E obiceiul bun: dacă cineva reușise cumva să pună mâna pe sesiunea de
 * dinainte, din clipa asta nu-i mai folosește la nimic.
 */
if (!headers_sent()) {
    session_regenerate_id(true);
}

// Înștiințarea pleacă oricum, chiar dacă omul și-a schimbat singur parola:
// e felul în care afli că altcineva ți-a luat contul.
emailParolaSchimbata((string) $membru['email'], (string) $membru['prenume']);

raspunsJson([
    'ok'       => true,
    'redirect' => 'index.php',
    'mesaj'    => $areParola ? 'Parola a fost schimbată.' : 'Parola a fost salvată.',
]);
