<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — intrarea în cont.
 *
 * Răspunsuri posibile:
 *   {"ok":true, "redirect":"..."}                       intrare reușită
 *   {"ok":false,"erori":{...}}                          date greșite
 *   {"ok":false,"stare":"neconfirmat","email":"..."}    contul nu e activat
 *   {"ok":false,"stare":"blocat","secunde":600}         prea multe greșeli
 */

require_once __DIR__ . '/../inc/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    raspunsJson(['ok' => false, 'mesaj' => 'Metodă nepermisă.'], 405);
}

/* ------------------------- Datele primite ----------------------------- */

$tip = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($tip, 'application/json') !== false) {
    $date = json_decode(file_get_contents('php://input') ?: '', true);
    $date = is_array($date) ? $date : [];
} else {
    $date = $_POST;
}

$csrf = isset($date['csrf']) && is_string($date['csrf']) ? $date['csrf'] : '';

if (!tokenCsrfValid($csrf)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Sesiunea a expirat. Reîncarcă pagina și încearcă din nou.',
    ], 419);
}

/* --------------------- Curățarea și verificarea ----------------------- */

// Aceleași reguli ca la înregistrare: tăiem caracterele de control, spațiile
// de la capete, aducem adresa la litere mici.
$email  = mb_strtolower(curataSpatii(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) ($date['email'] ?? '')) ?? ''), 'UTF-8');
$parola = is_string($date['parola'] ?? null) ? $date['parola'] : '';

$erori = [];

if ($email === '') {
    $erori['email'] = 'Scrie adresa de e-mail.';
} elseif (mb_strlen($email, 'UTF-8') > EMAIL_MAX || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erori['email'] = 'Adresa de e-mail nu pare validă.';
}

if ($parola === '') {
    $erori['parola'] = 'Scrie parola.';
} elseif (strlen($parola) > 4096) {
    // Nimeni nu are o parolă atât de lungă. Limita oprește cererile trimise
    // doar ca să încarce serverul cu calcule de hash.
    $erori['parola'] = 'Parola nu este validă.';
}

if ($erori !== []) {
    raspunsJson(['ok' => false, 'erori' => $erori], 422);
}

/* ------------------------ Formularul e blocat? ------------------------ */

$blocat = secundeBlocare($email);

if ($blocat > 0) {
    raspunsJson([
        'ok'      => false,
        'stare'   => 'blocat',
        'secunde' => $blocat,
        'mesaj'   => 'Prea multe încercări greșite. Mai încearcă peste ' . durataInCuvinte($blocat) . '.',
    ], 429);
}

/* -------------------------- Căutarea contului ------------------------- */

$q = db()->prepare(
    'SELECT id, permalink, nume, prenume, email, parola_hash, stare
       FROM membri
      WHERE email = ?
      LIMIT 1'
);
$q->execute([$email]);
$membru = $q->fetch();

/**
 * Verificarea parolei se face ÎNTOTDEAUNA, chiar dacă adresa nu există.
 *
 * Altfel, un răspuns instantaneu ar însemna „adresa nu există", iar unul
 * întârziat „adresa există, dar parola e greșită" — adică o metodă simplă
 * de a afla cine are cont pe site.
 */
$hashFals = '$2y$12$usesomesillystringfoeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';
$parolaCorecta = password_verify($parola, $membru['parola_hash'] ?? $hashFals);

if (!$membru || !$parolaCorecta) {
    scrieIncercare($email, false);

    $ramase = incercariRamase($email);
    $raspuns = [
        'ok'    => false,
        'erori' => ['parola' => 'E-mail sau parolă greșite.'],
    ];

    // După ultima greșeală permisă, spunem direct că formularul se închide.
    if ($ramase <= 0) {
        $secunde = secundeBlocare($email);
        $raspuns = [
            'ok'      => false,
            'stare'   => 'blocat',
            'secunde' => $secunde > 0 ? $secunde : MINUTE_BLOCARE * 60,
            'mesaj'   => 'Prea multe încercări greșite. Mai încearcă peste '
                       . durataInCuvinte($secunde > 0 ? $secunde : MINUTE_BLOCARE * 60) . '.',
        ];
        raspunsJson($raspuns, 429);
    }

    $raspuns['incercari_ramase'] = $ramase;
    raspunsJson($raspuns, 401);
}

/* ----------------------- Contul nu e încă activat --------------------- */

if ($membru['stare'] === 'neconfirmat') {
    // Nu se numără ca încercare greșită: parola a fost corectă.
    raspunsJson([
        'ok'    => false,
        'stare' => 'neconfirmat',
        'email' => $membru['email'],
        'mesaj' => 'Contul tău nu e încă activat. Verifică e-mailul de confirmare.',
    ], 403);
}

if ($membru['stare'] !== 'activ') {
    scrieIncercare($email, false);
    raspunsJson([
        'ok'    => false,
        'stare' => 'suspendat',
        'mesaj' => 'Contul este suspendat. Scrie-ne dacă vrei lămuriri.',
    ], 403);
}

/* ---------------------------- Totul e bun ----------------------------- */

// Dacă între timp a apărut un algoritm de hash mai bun, sau am schimbat
// „costul", refacem hash-ul acum, cât timp avem parola în clar.
if (password_needs_rehash($membru['parola_hash'], PASSWORD_DEFAULT)) {
    $nou = password_hash($parola, PASSWORD_DEFAULT);
    if ($nou !== false) {
        $u = db()->prepare('UPDATE membri SET parola_hash = ? WHERE id = ?');
        $u->execute([$nou, $membru['id']]);
    }
}

$u = db()->prepare('UPDATE membri SET autentificat_la = NOW() WHERE id = ?');
$u->execute([$membru['id']]);

scrieIncercare($email, true);

$tineMinte = !empty($date['tine_minte']);
autentifica($membru, $tineMinte);

/**
 * Unde trimitem utilizatorul după intrare.
 *
 * Se acceptă doar căi din interiorul site-ului. O valoare de forma
 * „https://alt-site.ro" sau „//alt-site.ro" e ignorată, ca parametrul să nu
 * poată fi folosit pentru a duce oamenii în altă parte.
 */
$redirect = 'index.php';
$cerut    = is_string($date['redirect'] ?? null) ? $date['redirect'] : '';

if ($cerut !== '' && $cerut[0] === '/' && ($cerut[1] ?? '') !== '/') {
    $redirect = $cerut;
}

raspunsJson([
    'ok'       => true,
    'redirect' => $redirect,
    'mesaj'    => 'Bine ai revenit, ' . $membru['prenume'] . '!',
]);
