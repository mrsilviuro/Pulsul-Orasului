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
    // `este_staff` e aici pentru lacătul de mai jos: cât timp site-ul e în
    // lucru, numai oamenii de casă trec de intrare. Fără coloană, esteStaff()
    // ar fi citit un câmp lipsă, l-ar fi luat drept 0 și i-ar fi închis ușa
    // tocmai celui care trebuia s-o poată deschide.
    'SELECT id, permalink, nume, prenume, email, parola_hash, stare, este_staff,
            parola_temporara_hash, parola_temporara_expira, parola_temporara_incercari
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

/**
 * Dacă parola obișnuită nu e bună, poate fi cea temporară, cerută prin
 * „mi-am uitat parola". Se încearcă și pentru adresele care nu există, tot
 * din motivul de mai sus: durata răspunsului nu trebuie să difere.
 */
$cuParolaTemporara = false;

if (!$parolaCorecta) {
    $cuParolaTemporara = incearcaParolaTemporara($membru ?: null, $parola);
    $parolaCorecta = $cuParolaTemporara;
}

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

/* ------------------- Site-ul e închis pentru lucrări ------------------- */

/**
 * Parola a fost bună, dar ușa e închisă pentru cine nu e de-al casei.
 *
 * Verificarea stă AICI, înainte de autentifica(), tocmai ca sesiunea să nu se
 * facă deloc. Dacă am fi lăsat-o pe urmă — sau, mai rău, doar pe lacătul de la
 * intrarea în pagini — omul ar fi rămas conectat în spatele unui site închis:
 * cu cont, cu cookie, cu tot, dar fără nicio pagină la care să ajungă. Așa nu
 * se întâmplă nimic: e ca și cum n-ar fi apăsat.
 *
 * Se citește din rândul deja adus din bază (`este_staff`), prin aceeași
 * esteStaff() ca peste tot — nu dintr-o comparație scrisă din nou aici.
 *
 * Nu se numără ca încercare greșită: parola a fost corectă, iar omul n-a
 * făcut nimic rău. Ar fi fost cel mai nedrept fel de blocare — trei intrări
 * bune și contul încuiat zece minute, pentru că site-ul e în lucru.
 *
 * `redirect` întoarce pagina la afișul de pe ușă, ca omul să vadă unde a
 * ajuns, nu un formular care refuză fără să spună unde să se ducă.
 */
if (siteInConstructie() && !esteStaff($membru)) {
    raspunsJson([
        'ok'       => false,
        'stare'    => 'in_constructie',
        'redirect' => '/constructie.php',
        'mesaj'    => 'Site-ul e în lucru. Îți dăm de veste imediat ce deschidem.',
    ], 503);
}

/* ---------------------------- Totul e bun ----------------------------- */

// Refacerea hash-ului are sens doar când s-a intrat cu parola adevărată: cu
// cea temporară am scrie peste parola bună un hash al unei parole de o oră.
if (!$cuParolaTemporara && password_needs_rehash($membru['parola_hash'], PASSWORD_DEFAULT)) {
    $nou = password_hash($parola, PASSWORD_DEFAULT);
    if ($nou !== false) {
        $u = db()->prepare('UPDATE membri SET parola_hash = ? WHERE id = ?');
        $u->execute([$nou, $membru['id']]);
    }
}

$u = db()->prepare('UPDATE membri SET autentificat_la = ? WHERE id = ?');
$u->execute([acum(), $membru['id']]);

scrieIncercare($email, true);

$tineMinte = !empty($date['tine_minte']);

// „Ține-mă minte" nu are ce căuta la o intrare cu parolă temporară: sesiunea
// aia trebuie să dureze cât îi ia omului să-și pună o parolă nouă, nu o lună.
autentifica($membru, $tineMinte && !$cuParolaTemporara, $cuParolaTemporara);

/**
 * Unde trimitem utilizatorul după intrare.
 *
 * Se acceptă doar căi din interiorul site-ului. O valoare de forma
 * „https://alt-site.ro" sau „//alt-site.ro" e ignorată, ca parametrul să nu
 * poată fi folosit pentru a duce oamenii în altă parte. Regula stă întreagă în
 * caleInterna() — și aici, și la login.php, și la google.php.
 */
$cerut    = caleInterna($date['redirect'] ?? null);
$redirect = $cerut !== '' ? $cerut : '/index.php';

// Cine a intrat cu parola temporară merge direct la schimbarea ei, oriunde ar
// fi vrut să ajungă.
if ($cuParolaTemporara) {
    raspunsJson([
        'ok'       => true,
        'redirect' => '/parola-noua.php',
        'temporara' => true,
        'mesaj'    => 'Ai intrat cu parola temporară. Alege-ți acum una nouă.',
    ]);
}

raspunsJson([
    'ok'       => true,
    'redirect' => $redirect,
    'mesaj'    => 'Bine ai revenit, ' . $membru['prenume'] . '!',
]);
