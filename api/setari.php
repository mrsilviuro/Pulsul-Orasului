<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — salvarea setărilor mărunte: telefonul și newsletterul.
 *
 * Un singur punct pentru amândouă, cu „sectiune" spunând care formular a
 * trimis. Sunt două câmpuri, nu două povești: două fișiere ar fi însemnat de
 * două ori aceleași verificări de la început (metodă, CSRF, cine e conectat).
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/validare.php';

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

opresteDacaTrebuieParolaNoua(true);

$sectiune = is_string($date['sectiune'] ?? null) ? $date['sectiune'] : '';

/* ----------------------------- Telefonul ------------------------------ */

if ($sectiune === 'telefon') {
    /**
     * Câmpul poate lipsi cu totul — și atunci înseamnă „gol", ceea ce e o
     * alegere bună: omul își șterge numărul.
     *
     * Dar dacă vine și NU e text — un număr, o listă, un obiect — nu e „gol",
     * e altceva decât ne-am înțeles. Îl refuzăm în loc să-l luăm drept gol:
     * altfel un client care trimite 722334455 ca număr, nu ca text, ar șterge
     * numărul din cont fără ca nimeni să ceară asta.
     */
    if (array_key_exists('telefon', $date) && !is_string($date['telefon'])) {
        raspunsJson([
            'ok'    => false,
            'erori' => ['telefon' => 'Scrie numărul ca text, de forma 0722334455.'],
        ], 422);
    }

    $primit = (string) ($date['telefon'] ?? '');

    // Un câmp de telefon nu are ce căuta lung. Tăiem înainte de verificare,
    // ca expresiile regulate să nu primească niciodată un șir uriaș.
    if (strlen($primit) > 40) {
        raspunsJson(['ok' => false, 'erori' => ['telefon' => 'Numărul e prea lung.']], 422);
    }

    $rezultat = verificaTelefon($primit);

    if (!$rezultat['ok']) {
        raspunsJson(['ok' => false, 'erori' => ['telefon' => $rezultat['eroare']]], 422);
    }

    // Gol înseamnă „nu vreau să-l dau", deci NULL, nu șir gol: în bază e o
    // deosebire care contează la căutări.
    $valoare = $rezultat['curat'] === '' ? null : $rezultat['curat'];

    db()->prepare('UPDATE membri SET telefon = ? WHERE id = ?')
        ->execute([$valoare, (int) $membru['id']]);

    raspunsJson([
        'ok'      => true,
        'telefon' => (string) $valoare,
        'mesaj'   => $valoare === null ? 'Numărul a fost șters.' : 'Numărul a fost salvat.',
    ]);
}

/* ---------------------------- Newsletterul ---------------------------- */

if ($sectiune === 'newsletter') {
    /**
     * Orice altceva decât un „da" limpede înseamnă nu.
     *
     * O bifă netrimisă de browser nu ajunge în date deloc, deci absența ei e
     * chiar răspunsul „nu vreau".
     */
    $vrea = !empty($date['newsletter']) && $date['newsletter'] !== 'false';

    db()->prepare('UPDATE membri SET newsletter = ? WHERE id = ?')
        ->execute([$vrea ? 1 : 0, (int) $membru['id']]);

    raspunsJson([
        'ok'         => true,
        'newsletter' => $vrea,
        'mesaj'      => $vrea
            ? 'Gata, îți scriem când apar evenimente noi.'
            : 'Nu-ți mai trimitem e-mailuri cu evenimente.',
    ]);
}

raspunsJson(['ok' => false, 'mesaj' => 'Cerere neînțeleasă.'], 400);
