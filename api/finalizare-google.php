<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — creează contul, după înregistrarea cu Google.
 *
 * Datele venite de la Google stau în sesiune, puse acolo de google.php.
 * Formularul aduce doar ce lipsea: numele (de verificat), data nașterii, sexul.
 *
 * Adresa de e-mail NU se ia din formular. Vine numai din sesiune, adică din
 * ce ne-a spus Google — altfel oricine ar putea trece prin Google cu contul
 * lui și ar cere apoi cont pe adresa altcuiva.
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

if (esteLogat()) {
    raspunsJson(['ok' => true, 'redirect' => 'index.php', 'mesaj' => 'Ești deja conectat.']);
}

/* --------------------- Ce ne-a spus Google despre om ------------------- */

pornesteSesiunea();
$nou = $_SESSION['google_nou'] ?? null;

if (!is_array($nou) || empty($nou['sub']) || empty($nou['email'])
    || (time() - (int) ($nou['la'] ?? 0)) > 900) {
    raspunsJson([
        'ok'       => false,
        'mesaj'    => 'A trecut prea mult timp. Ia-o de la capăt cu Google.',
        'redirect' => 'login.php',
    ], 419);
}

/* ------------------------- Verificarea datelor ------------------------- */

/**
 * Se folosesc aceleași reguli ca la înregistrarea obișnuită.
 *
 * verificaInregistrare() cere și parolă, pe care aici nu o avem: contul se
 * deschide cu Google. Îi dăm una inventată doar ca să treacă de verificare, și
 * NU o salvăm nicăieri — coloana rămâne NULL.
 */
$parolaFictiva = bin2hex(random_bytes(16));

$rezultat = verificaInregistrare([
    'nume'              => $date['nume'] ?? '',
    'prenume'           => $date['prenume'] ?? '',
    'email'             => $nou['email'],
    'data_nasterii'     => $date['data_nasterii'] ?? '',
    'sex'               => $date['sex'] ?? '',
    'parola'            => $parolaFictiva,
    'parola_confirmare' => $parolaFictiva,
    'termeni'           => $date['termeni'] ?? '',
]);

if ($rezultat['erori'] !== []) {
    // Erorile de parolă n-ar avea ce căuta în pagină: câmpul nici nu există.
    unset($rezultat['erori']['parola'], $rezultat['erori']['parola_confirmare']);

    if ($rezultat['erori'] !== []) {
        raspunsJson(['ok' => false, 'erori' => $rezultat['erori']], 422);
    }
}

$curat = $rezultat['curat'];

/* ---------------------- Între timp a apărut contul? -------------------- */

// Se poate întâmpla dacă omul a deschis două file și a terminat în amândouă.
$q = db()->prepare('SELECT id, prenume, stare FROM membri WHERE google_id = ? OR email = ? LIMIT 1');
$q->execute([$nou['sub'], $nou['email']]);

if ($existent = $q->fetch()) {
    unset($_SESSION['google_nou']);

    if ($existent['stare'] !== 'activ') {
        raspunsJson(['ok' => false, 'mesaj' => 'Contul există deja, dar nu e activ.'], 409);
    }

    autentifica($existent);
    raspunsJson(['ok' => true, 'redirect' => 'index.php', 'mesaj' => 'Contul exista deja. Te-am conectat.']);
}

/* ------------------------------ Salvarea ------------------------------ */

/**
 * Contul e activ din prima, fără e-mail de confirmare.
 *
 * Confirmarea prin e-mail există ca să dovedească faptul că omul chiar are
 * cutia poștală aia. Google tocmai a dovedit-o, cu „email_verified". Ar fi o
 * piedică fără rost să-i mai cerem încă o dată același lucru.
 */
$sql = 'INSERT INTO membri
          (permalink, nume, prenume, email, google_id, parola_hash, data_nasterii, sex,
           stare, confirmat_la, ip_inregistrare, creat_la)
        VALUES
          (?, ?, ?, ?, ?, NULL, ?, ?, \'activ\', ?, ?, ?)';

$ip        = ipBinar();
$permalink = '';
$reusit    = false;

for ($incercare = 1; $incercare <= 5 && !$reusit; $incercare++) {
    $permalink = permalinkNou();

    try {
        $q = db()->prepare($sql);
        $q->execute([
            $permalink,
            $curat['nume'],
            $curat['prenume'],
            $nou['email'],
            $nou['sub'],
            $curat['data_nasterii'],
            $curat['sex'],
            acum(),
            $ip,
            acum(),
        ]);
        $reusit = true;

    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') {
            throw $e;
        }

        // S-a repetat e-mailul sau google_id? Atunci contul există deja.
        $verificare = db()->prepare('SELECT 1 FROM membri WHERE email = ? OR google_id = ? LIMIT 1');
        $verificare->execute([$nou['email'], $nou['sub']]);

        if ($verificare->fetchColumn()) {
            raspunsJson(['ok' => false, 'mesaj' => 'Contul există deja. Încearcă să intri cu Google.'], 409);
        }
        // altfel s-a repetat permalinkul: mai încercăm cu altul
    }
}

if (!$reusit) {
    raspunsJson(['ok' => false, 'mesaj' => 'Nu am putut crea contul. Încearcă din nou.'], 500);
}

/* ------------------------------ Intrarea ------------------------------ */

$q = db()->prepare('SELECT id, prenume, email FROM membri WHERE google_id = ? LIMIT 1');
$q->execute([$nou['sub']]);
$membru = $q->fetch();

$inapoiLa = (string) ($nou['inapoi_la'] ?? '');
unset($_SESSION['google_nou']);

autentifica($membru);

// Un „bine ai venit", fără link de confirmat: nu mai e nimic de confirmat.
emailBunVenit((string) $membru['email'], (string) $membru['prenume']);

raspunsJson([
    'ok'       => true,
    'redirect' => $inapoiLa !== '' ? $inapoiLa : 'index.php',
    'mesaj'    => 'Bine ai venit, ' . $membru['prenume'] . '!',
], 201);
