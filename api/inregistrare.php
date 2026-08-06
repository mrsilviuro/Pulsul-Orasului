<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — crearea unui cont nou.
 *
 * Primește datele formularului de înregistrare prin POST și răspunde cu JSON:
 *
 *   succes  → {"ok": true,  "mesaj": "..."}
 *   eșec    → {"ok": false, "erori": {"email": "mesaj", ...}}
 *
 * Pagina nu se reîncarcă: login.php trimite datele din JavaScript și
 * înlocuiește formularul cu mesajul de confirmare.
 */

require_once __DIR__ . '/../inc/bootstrap.php';

/* ------------------------- 1. Doar prin POST -------------------------- */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    raspunsJson(['ok' => false, 'mesaj' => 'Metodă nepermisă.'], 405);
}

/* --------------------- 2. Datele, oricum ar veni ---------------------- */

$tip = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($tip, 'application/json') !== false) {
    $brut  = file_get_contents('php://input') ?: '';
    $date  = json_decode($brut, true);
    $date  = is_array($date) ? $date : [];
} else {
    $date = $_POST;
}

/* --------------------------- 3. Token CSRF ---------------------------- */

$csrf = isset($date['csrf']) && is_string($date['csrf']) ? $date['csrf'] : '';

if (!tokenCsrfValid($csrf)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Sesiunea a expirat. Reîncarcă pagina și încearcă din nou.',
    ], 419);
}

/* ------------------ 4. Verificarea datelor introduse ------------------ */

$rezultat = verificaInregistrare($date);

if ($rezultat['erori'] !== []) {
    raspunsJson(['ok' => false, 'erori' => $rezultat['erori']], 422);
}

$curat = $rezultat['curat'];

/* ------------- 5. Prea multe conturi de la aceeași adresă ------------- */

$ip = ipBinar();

if ($ip !== null) {
    $limita = (int) ($config['inregistrari_pe_ora'] ?? 5);

    $q = db()->prepare(
        'SELECT COUNT(*) FROM membri
          WHERE ip_inregistrare = ?
            AND creat_la > ?'
    );
    $q->execute([$ip, acumMinus(60)]);

    if ((int) $q->fetchColumn() >= $limita) {
        raspunsJson([
            'ok'    => false,
            'mesaj' => 'S-au creat prea multe conturi de pe această conexiune. Încearcă peste o oră.',
        ], 429);
    }
}

/* ---------------------- 6. E-mailul e deja folosit? ------------------- */

$q = db()->prepare('SELECT 1 FROM membri WHERE email = ? LIMIT 1');
$q->execute([$curat['email']]);

if ($q->fetchColumn()) {
    raspunsJson([
        'ok'    => false,
        'erori' => ['email' => 'Există deja un cont cu această adresă de e-mail.'],
    ], 409);
}

/* ---------------------------- 7. Salvarea ----------------------------- */

// Token-ul de confirmare: în e-mail pleacă valoarea în clar, în baza de date
// se păstrează doar hash-ul ei. Dacă baza ajunge pe mâini străine, linkurile
// nu pot fi refolosite.
$token     = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$oreValid  = (int) ($config['ore_valabilitate_token'] ?? 48);

// Momentul expirării se calculează în PHP, nu prin „INTERVAL ? HOUR": MySQL
// nu acceptă un parametru pregătit în locul numărului dintr-un INTERVAL.
$tokenExpira = (new DateTimeImmutable("+{$oreValid} hours"))->format('Y-m-d H:i:s');

$parolaHash = password_hash($curat['parola'], PASSWORD_DEFAULT);

if ($parolaHash === false) {
    raspunsJson(['ok' => false, 'mesaj' => 'Nu am putut crea contul. Încearcă din nou.'], 500);
}

$sql = 'INSERT INTO membri
          (permalink, nume, prenume, email, parola_hash, data_nasterii, sex,
           stare, token_confirmare, token_expira, ip_inregistrare, creat_la)
        VALUES
          (?, ?, ?, ?, ?, ?, ?, \'neconfirmat\', ?, ?, ?, ?)';

$permalink = '';
$reusit    = false;

// Permalinkul e aleatoriu, deci o coliziune e practic imposibilă — dar dacă
// totuși apare, mai încercăm de câteva ori în loc să dăm eroare.
for ($incercare = 1; $incercare <= 5 && !$reusit; $incercare++) {
    $permalink = permalinkNou();

    try {
        $q = db()->prepare($sql);
        $q->execute([
            $permalink,
            $curat['nume'],
            $curat['prenume'],
            $curat['email'],
            $parolaHash,
            $curat['data_nasterii'],
            $curat['sex'],
            $tokenHash,
            $tokenExpira,
            $ip,
            acum(),
        ]);
        $reusit = true;

    } catch (PDOException $e) {
        // 23000 = o valoare unică se repetă. Poate fi permalinkul (mai
        // încercăm) sau e-mailul (înseamnă că altcineva s-a înregistrat cu
        // aceeași adresă între verificarea de mai sus și rândul acesta).
        if ($e->getCode() !== '23000') {
            throw $e;
        }

        // Ca să aflăm care valoare s-a repetat, întrebăm baza — mai sigur
        // decât să căutăm numele indexului în textul erorii, care diferă de
        // la un motor de bază de date la altul.
        $verificare = db()->prepare('SELECT 1 FROM membri WHERE email = ? LIMIT 1');
        $verificare->execute([$curat['email']]);

        if ($verificare->fetchColumn()) {
            raspunsJson([
                'ok'    => false,
                'erori' => ['email' => 'Există deja un cont cu această adresă de e-mail.'],
            ], 409);
        }
        // altfel s-a repetat permalinkul: mai încercăm cu altul
    }
}

if (!$reusit) {
    raspunsJson(['ok' => false, 'mesaj' => 'Nu am putut crea contul. Încearcă din nou.'], 500);
}

/* ------------------------- 8. E-mailul de confirmare ------------------ */

$linkConfirmare = rtrim((string) $config['url_site'], '/')
                . '/confirma.php?token=' . $token;

// TODO: trimiterea propriu-zisă a e-mailului.
//
// În XAMPP nu există server de mail, așa că deocamdată scriem linkul într-un
// fișier. Două precauții, pentru că fișierul conține token-uri valabile —
// cine le citește poate activa contul altcuiva:
//   1. stă în private/, unde .htaccess refuză accesul prin web;
//   2. se scrie DOAR în modul dezvoltare, deci pe site-ul public nici nu apare.
if (!empty($config['dezvoltare'])) {
    @file_put_contents(
        __DIR__ . '/../private/emailuri-trimise.log',
        sprintf(
            "[%s] către: %s\nsubiect: Confirmă-ți contul pe PulsulOrasului.Ro\nlink: %s\n\n",
            date('Y-m-d H:i:s'),
            $curat['email'],
            $linkConfirmare
        ),
        FILE_APPEND
    );
}

/* ---------------------------- 9. Răspunsul ---------------------------- */

$raspuns = [
    'ok'    => true,
    'email' => $curat['email'],
    'mesaj' => 'Contul a fost creat. Ți-am trimis un e-mail de confirmare.',
];

// Doar în dezvoltare: linkul e trimis înapoi, ca să poți încheia
// înregistrarea fără server de e-mail.
if (!empty($config['dezvoltare'])) {
    $raspuns['link_confirmare'] = $linkConfirmare;
}

raspunsJson($raspuns, 201);
