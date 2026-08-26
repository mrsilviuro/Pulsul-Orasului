<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — omul își ia o dorință înapoi.
 *
 * Tot ce face: verifică cine cere, apoi cheamă stergeDorintaOmului() din
 * inc/dorinte.php. Acolo stă și regula — „a lui, și încă în viață" — scrisă în
 * `WHERE`, nu într-un SELECT de dinainte.
 *
 * FRATELE FĂRĂ JAVASCRIPT al aceleiași fapte e în index.php: „×"-ul de sub
 * tablă e un formular adevărat, cu `method="post"`, iar cine n-are JS îl
 * trimite acolo. Amândouă cheamă aceeași funcție — scrise de două ori, s-ar fi
 * despărțit la prima schimbare (aceeași înțelegere ca la puneODorinta).
 *
 * NU SE CERE NICIO CONFIRMARE, nici aici, nici în pagină. O dorință e un rând
 * de o sută de caractere, iar omul care apasă „×" în dreptul propriei fraze
 * știe ce face. Ce se pierde e o frază pe care o poate scrie din nou — și,
 * ștergând-o, își face loc pentru alta.
 */

require_once __DIR__ . '/../inc/dorinte.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    raspunsJson(['ok' => false, 'mesaj' => 'Metodă nepermisă.'], 405);
}

/**
 * Din pagină vine JSON, dar primim și un formular obișnuit — aceeași
 * îngăduință ca la celelalte API-uri.
 */
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

opresteDacaTrebuieParolaNoua(true);

$id = (int) ($date['id'] ?? 0);

/**
 * „Nu există" și „nu e a ta" primesc ACELAȘI răspuns, ca peste tot pe site.
 * Altfel, numerele încercate pe rând ar fi spus cine ce a scris — iar o
 * dorință netrecută încă pe la moderare n-a văzut-o nimeni în afară de autor.
 *
 * Al doilea „×" pe același rând ajunge tot aici: ștergerea nu prinde a doua
 * oară (`sters_la IS NULL` din WHERE), iar asta e purtarea bună — nu se mută
 * ștampila, nu se strică nimic.
 */
if (!stergeDorintaOmului((int) $membru['id'], $id)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Dorința nu mai există sau nu e a ta.',
    ], 404);
}

raspunsJson(['ok' => true, 'mesaj' => 'Dorința a fost ștearsă.']);
