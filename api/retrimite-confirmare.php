<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — retrimiterea e-mailului de confirmare.
 *
 * Se cere din pagina de autentificare, atunci când cineva încearcă să intre
 * într-un cont încă neactivat. Cel mult o dată la 10 minute.
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
        'mesaj' => 'Sesiunea a expirat. Reîncarcă pagina și încearcă din nou.',
    ], 419);
}

$email = mb_strtolower(curataSpatii((string) ($date['email'] ?? '')), 'UTF-8');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    raspunsJson(['ok' => false, 'mesaj' => 'Adresa de e-mail nu pare validă.'], 422);
}

/**
 * Răspunsul e același indiferent dacă adresa există sau nu.
 *
 * Altfel, oricine ar putea folosi butonul ăsta ca să afle ce adrese au cont
 * pe site. Cine chiar deține adresa vede mesajul în căsuța de e-mail.
 */
$raspunsNeutru = [
    'ok'    => true,
    'mesaj' => 'Dacă adresa are un cont neconfirmat, am trimis un e-mail nou.',
];

$q = db()->prepare(
    'SELECT id, email, stare, token_trimis_la
       FROM membri
      WHERE email = ?
      LIMIT 1'
);
$q->execute([$email]);
$membru = $q->fetch();

// Adresa nu există, sau contul e deja activ: nu spunem nimic în plus.
if (!$membru || $membru['stare'] !== 'neconfirmat') {
    raspunsJson($raspunsNeutru);
}

/* --------------------- Nu mai des de o dată la 10 minute -------------- */

if (!empty($membru['token_trimis_la'])) {
    $urmatoarea = (new DateTimeImmutable((string) $membru['token_trimis_la']))
        ->modify('+' . MINUTE_INTRE_RETRIMITERI . ' minutes');

    $ramas = $urmatoarea->getTimestamp() - time();

    if ($ramas > 0) {
        raspunsJson([
            'ok'      => false,
            'secunde' => $ramas,
            'mesaj'   => 'Am trimis deja un e-mail de curând. Mai încearcă peste '
                       . durataInCuvinte($ramas) . '.',
        ], 429);
    }
}

/* -------------------------- Token nou și trimitere -------------------- */

$token     = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$oreValid  = (int) ($config['ore_valabilitate_token'] ?? 48);
$expira    = (new DateTimeImmutable("+{$oreValid} hours"))->format('Y-m-d H:i:s');

// Token-ul vechi este înlocuit, deci un link mai vechi nu mai funcționează.
$u = db()->prepare(
    'UPDATE membri
        SET token_confirmare = ?, token_expira = ?, token_trimis_la = ?
      WHERE id = ?'
);
$u->execute([$tokenHash, $expira, acum(), $membru['id']]);

$linkConfirmare = rtrim((string) $config['url_site'], '/')
                . '/confirma.php?token=' . $token;

// TODO: trimiterea propriu-zisă a e-mailului.
// Vezi explicația din api/inregistrare.php: fișierul conține token-uri
// valabile, deci se scrie doar în dezvoltare și doar în private/.
if (!empty($config['dezvoltare'])) {
    @file_put_contents(
        __DIR__ . '/../private/emailuri-trimise.log',
        sprintf(
            "[%s] către: %s\nsubiect: Confirmă-ți contul (retrimitere)\nlink: %s\n\n",
            date('Y-m-d H:i:s'),
            $membru['email'],
            $linkConfirmare
        ),
        FILE_APPEND
    );
}

$raspuns = $raspunsNeutru;

if (!empty($config['dezvoltare'])) {
    $raspuns['link_confirmare'] = $linkConfirmare;
}

raspunsJson($raspuns);
