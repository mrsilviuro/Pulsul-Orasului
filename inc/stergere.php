<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — ștergerea contului, cu răgaz de treizeci de zile.
 *
 * Trei momente, despărțite intenționat:
 *
 *   1. Omul cere ștergerea din setări. Se verifică parola (dacă are una) și
 *      pleacă un e-mail. Până aici NU s-a schimbat nimic în cont.
 *   2. Omul apasă linkul din e-mail. Abia acum se scrie cerere_stergere și e
 *      dat afară din cont. Datele rămân întregi.
 *   3. După treizeci de zile fără nicio intrare, cronul anonimizează rândul.
 *
 * De ce nu ștergem rândul de tot: de el atârnă evenimentele organizate și
 * participările. Un DELETE ar lăsa găuri în istoricul altor oameni. Așa,
 * rândul rămâne, dar omul din spatele lui dispare.
 *
 * Anularea nu are buton: e destul ca omul să intre în cont. Vezi autentifica()
 * din inc/auth.php.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/imagini.php';

/** Cât ține răgazul, din clipa confirmării. */
const ZILE_RAGAZ_STERGERE = 30;

/** Cât e bun linkul din e-mail. */
const ORE_TOKEN_STERGERE = 2;

/* ===================== 1. CEREREA, DIN SETĂRI ======================== */

/**
 * Scrie un token nou și trimite e-mailul de confirmare.
 *
 * Întoarce true dacă mesajul a plecat.
 */
function cereStergereaContului(array $membru): bool
{
    global $config;

    $token = bin2hex(random_bytes(32));
    $expira = time() + ORE_TOKEN_STERGERE * 3600;

    $u = db()->prepare(
        'UPDATE membri
            SET token_stergere = ?, token_stergere_expira = ?
          WHERE id = ?'
    );
    $u->execute([
        hash('sha256', $token),
        date('Y-m-d H:i:s', $expira),
        (int) $membru['id'],
    ]);

    $site = rtrim((string) ($config['url_site'] ?? ''), '/');
    $link = $site . '/stergere.php?token=' . $token;

    return emailStergereCont(
        (string) $membru['email'],
        (string) $membru['prenume'],
        $link,
        ORE_TOKEN_STERGERE,
        ZILE_RAGAZ_STERGERE
    );
}

/* =================== 2. CONFIRMAREA, DIN E-MAIL ====================== */

/**
 * Pornește răgazul, dacă token-ul e bun.
 *
 * Întoarce rândul membrului la reușită, sau null.
 */
function confirmaStergereaContului(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $q = db()->prepare(
        'SELECT id, prenume, email, stare, token_stergere_expira
           FROM membri
          WHERE token_stergere = ?
          LIMIT 1'
    );
    $q->execute([hash('sha256', $token)]);
    $membru = $q->fetch();

    if (!$membru || $membru['stare'] !== 'activ') {
        return null;
    }

    // Ceasul e al PHP-ului, nu al bazei.
    if (strtotime((string) $membru['token_stergere_expira']) <= time()) {
        return null;
    }

    $u = db()->prepare(
        'UPDATE membri
            SET cerere_stergere = ?, token_stergere = NULL, token_stergere_expira = NULL
          WHERE id = ?'
    );
    $u->execute([acum(), (int) $membru['id']]);

    /**
     * Toate dispozitivele ținute minte sunt uitate.
     *
     * Altfel un telefon rămas conectat ar deschide site-ul singur peste două
     * zile, ar trece prin autentifica() și ar anula ștergerea fără ca omul să
     * fi cerut asta. Anularea trebuie să fie o faptă, nu un accident.
     */
    uitaToateAle((int) $membru['id']);

    return $membru;
}

/** Data la care se va șterge, scrisă pe românește. */
function candSeSterge(string $cerereLa): string
{
    $moment = strtotime($cerereLa) + ZILE_RAGAZ_STERGERE * 24 * 3600;

    $luni = ['ianuarie','februarie','martie','aprilie','mai','iunie',
             'iulie','august','septembrie','octombrie','noiembrie','decembrie'];

    return date('j', $moment) . ' ' . $luni[(int) date('n', $moment) - 1] . ' ' . date('Y', $moment);
}

/* ================ 3. ANONIMIZAREA, DUPĂ RĂGAZ ======================== */

/**
 * Șterge omul, păstrează rândul.
 *
 * Tot ce l-ar putea identifica dispare sau e înlocuit cu ceva fără sens.
 * Adresa de e-mail primește o valoare unică și imposibil de tastat, ca să nu
 * încurce indexul unic și să nu poată fi folosită vreodată la intrare.
 */
function anonimizeazaMembru(int $id): bool
{
    $q = db()->prepare('SELECT poza FROM membri WHERE id = ? LIMIT 1');
    $q->execute([$id]);
    $vechi = $q->fetch();

    if (!$vechi) {
        return false;
    }

    // Fișierele de pe disc nu sunt „date din bază": se șterg de mână.
    if (!empty($vechi['poza'])) {
        stergePozaDeFisier((string) $vechi['poza']);
    }

    $u = db()->prepare(
        'UPDATE membri
            SET nume = ?, prenume = ?,
                email = ?, telefon = NULL,
                parola_hash = NULL, google_id = NULL,
                parola_temporara_hash = NULL, parola_temporara_expira = NULL,
                parola_temporara_ceruta_la = NULL, parola_temporara_incercari = 0,
                poza = NULL, poza_actualizata_la = NULL,
                localitate = NULL,
                token_confirmare = NULL, token_expira = NULL,
                token_stergere = NULL, token_stergere_expira = NULL,
                ip_inregistrare = NULL,
                newsletter = 0,
                stare = ?, anonimizat_la = ?
          WHERE id = ?'
    );

    $reusit = $u->execute([
        'Șters',
        'Utilizator',
        // Unic, ca indexul să nu se supere, și pe un domeniu care nu există.
        'sters-' . bin2hex(random_bytes(8)) . '@invalid.local',
        'sters',
        acum(),
        $id,
    ]);

    // Fără dispozitive ținute minte: contul nu mai are cine să intre în el.
    uitaToateAle($id);

    return $reusit;
}

/**
 * Toate conturile cărora li s-a împlinit răgazul.
 *
 * Întoarce o listă cu ce s-a făcut, pentru log.
 */
function anonimizeazaConturileExpirate(?int $acumSecunde = null): array
{
    $acumSecunde ??= time();
    $limita = date('Y-m-d H:i:s', $acumSecunde - ZILE_RAGAZ_STERGERE * 24 * 3600);

    $q = db()->prepare(
        'SELECT id, email, cerere_stergere
           FROM membri
          WHERE cerere_stergere IS NOT NULL
            AND cerere_stergere <= ?
            AND stare <> \'sters\'
          ORDER BY cerere_stergere'
    );
    $q->execute([$limita]);

    $facute = [];

    foreach ($q->fetchAll() as $membru) {
        $id = (int) $membru['id'];

        $facute[] = [
            'id'      => $id,
            'cerut'   => (string) $membru['cerere_stergere'],
            'reusit'  => anonimizeazaMembru($id),
        ];
    }

    return $facute;
}

/**
 * Urma scrisă a fiecărei anonimizări.
 *
 * Adresa NU se scrie în log: ar însemna să păstrăm exact lucrul pe care omul
 * ne-a cerut să-l ștergem. Rămâne doar id-ul, cât să putem răspunde dacă
 * cineva întreabă mai târziu ce s-a întâmplat cu contul lui.
 */
function scrieInLogulStergerii(string $rand): void
{
    $dosar = dirname(__DIR__) . '/private';

    if (!is_dir($dosar) && !@mkdir($dosar, 0755, true) && !is_dir($dosar)) {
        return;
    }

    @file_put_contents(
        $dosar . '/conturi-anonimizate.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $rand . "\n",
        FILE_APPEND
    );
}
