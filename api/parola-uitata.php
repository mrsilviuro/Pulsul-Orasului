<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — cererea unei parole temporare.
 *
 * Primește o adresă de e-mail și, dacă are cont activ, trimite acolo o parolă
 * de șase caractere, valabilă o oră și bună o singură dată.
 *
 * Răspunsul e ÎNTOTDEAUNA același, indiferent dacă adresa există sau nu.
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

/* --------------------------- Adresa cerută ---------------------------- */

$email = mb_strtolower(
    curataSpatii(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) ($date['email'] ?? '')) ?? ''),
    'UTF-8'
);

if ($email === '' || mb_strlen($email, 'UTF-8') > EMAIL_MAX
    || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    raspunsJson(['ok' => false, 'erori' => ['email' => 'Adresa de e-mail nu pare validă.']], 422);
}

/**
 * Același răspuns pentru orice adresă.
 *
 * Dacă am spune „adresa asta nu are cont", butonul ăsta ar deveni o unealtă de
 * aflat cine e înscris pe site: se încearcă pe rând o listă de adrese și se
 * citește răspunsul. Cine chiar deține adresa află tot ce trebuie din cutia
 * poștală.
 */
$raspunsNeutru = [
    'ok'    => true,
    'mesaj' => 'Dacă adresa are un cont, ți-am trimis acolo o parolă temporară. '
             . 'Verifică și în „Spam", uneori ajunge acolo.',
];

/* -------------------- Prea multe cereri de la același IP --------------- */

/**
 * Limita pe IP se pune ÎNAINTE de a căuta adresa în bază.
 *
 * Altfel, cineva care încearcă o mie de adrese străine nu ar fi oprit de
 * nimic: pentru adresele inexistente nu se scrie nimic nicăieri, deci nu ar
 * exista ce să numărăm.
 */
$ip = ipBinar();

if ($ip !== null) {
    $q = db()->prepare(
        'SELECT COUNT(*) FROM incercari_autentificare
          WHERE ip = ? AND reusita = 0 AND email LIKE ? AND creat_la > ?'
    );
    $q->execute([$ip, 'recuperare:%', acumMinus(60)]);

    if ((int) $q->fetchColumn() >= 10) {
        raspunsJson([
            'ok'    => false,
            'mesaj' => 'S-au cerut prea multe parole de pe conexiunea asta. Încearcă peste o oră.',
        ], 429);
    }

    // Cererea se scrie chiar dacă adresa nu există: tocmai ăsta e rostul.
    $q = db()->prepare(
        'INSERT INTO incercari_autentificare (email, ip, reusita, creat_la) VALUES (?, ?, 0, ?)'
    );
    $q->execute([mb_substr('recuperare:' . $email, 0, 190), $ip, acum()]);
}

/* -------------------------- Căutarea contului -------------------------- */

$q = db()->prepare(
    'SELECT id, email, prenume, stare, parola_temporara_ceruta_la
       FROM membri
      WHERE email = ?
      LIMIT 1'
);
$q->execute([$email]);
$membru = $q->fetch();

// Adresa nu există sau contul nu e activ: nu spunem nimic în plus. Un cont
// neconfirmat nu primește parolă temporară — întâi își confirmă adresa.
if (!$membru || $membru['stare'] !== 'activ') {
    raspunsJson($raspunsNeutru);
}

/* ------------------- Nu mai des de o dată la 10 minute ----------------- */

if (!empty($membru['parola_temporara_ceruta_la'])) {
    $urmatoarea = (new DateTimeImmutable((string) $membru['parola_temporara_ceruta_la']))
        ->modify('+' . MINUTE_INTRE_CERERI_PAROLA . ' minutes');

    $ramas = $urmatoarea->getTimestamp() - time();

    if ($ramas > 0) {
        raspunsJson([
            'ok'      => false,
            'secunde' => $ramas,
            'mesaj'   => 'Ți-am trimis deja o parolă de curând. Mai încearcă peste '
                       . durataInCuvinte($ramas) . '.',
        ], 429);
    }
}

/* ---------------------- Parola nouă și trimiterea ---------------------- */

$parola = parolaTemporaraNoua();

// În bază intră doar hash-ul, ca și la parola adevărată. Vezi explicația din
// sql/004-parola-uitata.sql.
$hash   = password_hash($parola, PASSWORD_DEFAULT);
$expira = (new DateTimeImmutable('+' . MINUTE_PAROLA_TEMPORARA . ' minutes'))->format('Y-m-d H:i:s');

if ($hash === false) {
    raspunsJson(['ok' => false, 'mesaj' => 'Nu am putut pregăti parola. Încearcă din nou.'], 500);
}

// Contorul de greșeli pornește de la zero: e o parolă nouă.
$u = db()->prepare(
    'UPDATE membri
        SET parola_temporara_hash = ?, parola_temporara_expira = ?,
            parola_temporara_ceruta_la = ?, parola_temporara_incercari = 0
      WHERE id = ?'
);
$u->execute([$hash, $expira, acum(), (int) $membru['id']]);

$trimis = emailParolaTemporara(
    (string) $membru['email'],
    (string) $membru['prenume'],
    $parola,
    MINUTE_PAROLA_TEMPORARA
);

if (!$trimis) {
    error_log('PulsulOrasului: parola temporară nu a putut fi trimisă.');
}

$raspuns = $raspunsNeutru;

// Doar în dezvoltare, unde nu există server de e-mail: parola se întoarce în
// răspuns, ca fluxul să poată fi dus până la capăt din XAMPP.
if (!empty($config['dezvoltare'])) {
    $raspuns['parola_dezvoltare'] = $parola;
}

raspunsJson($raspuns);
