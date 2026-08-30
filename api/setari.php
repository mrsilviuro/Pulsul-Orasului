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
        'mesaj'   => $valoare === null ? 'Am șters numărul.' : 'Am salvat numărul.',
    ]);
}

/* ------------------------ Ce e-mailuri vrea omul ---------------------- */

if ($sectiune === 'newsletter') {
    /**
     * Orice altceva decât un „da" limpede înseamnă nu.
     *
     * O bifă netrimisă de browser nu ajunge în date deloc, deci absența ei e
     * chiar răspunsul „nu vreau".
     */
    $daLimpede = static fn (string $cheie): bool =>
        !empty($date[$cheie]) && $date[$cheie] !== 'false';

    $vrea      = $daLimpede('newsletter');
    $vreaComen = $daLimpede('email_comentarii');
    $vreaFdb   = $daLimpede('email_feedback');

    /**
     * Toate trei într-o singură scriere, fiindcă vin dintr-un singur formular:
     * trei UPDATE-uri ar fi putut lăsa una salvată și celelalte nu, dacă pica
     * ceva la mijloc — iar omul ar fi văzut „salvat" pentru o hotărâre făcută
     * pe jumătate.
     */
    db()->prepare('UPDATE membri SET newsletter = ?, email_comentarii = ?, email_feedback = ?
                    WHERE id = ?')
        ->execute([$vrea ? 1 : 0, $vreaComen ? 1 : 0, $vreaFdb ? 1 : 0, (int) $membru['id']]);

    /**
     * Mesajul spune ce s-a ales, nu doar „gata".
     *
     * Cu mai multe bife, un singur „Salvat" n-ar mai spune nimic: omul care a
     * stins una și le-a lăsat pe celelalte n-ar ști dacă s-a înțeles care.
     *
     * SE ÎNȘIRĂ CE E PORNIT, nu se scrie o frază pentru fiecare împerechere:
     * cu trei bife ar fi fost opt fraze, iar la a patra bifă șaisprezece. Așa,
     * o bifă nouă înseamnă un rând nou în tabloul de mai jos.
     */
    $aprinse = [];

    if ($vrea)      { $aprinse[] = 'când apar evenimente noi'; }
    if ($vreaComen) { $aprinse[] = 'când cineva îți comentează sau îți răspunde'; }
    if ($vreaFdb)   { $aprinse[] = 'când cineva îți lasă un feedback scris'; }

    if ($aprinse === []) {
        $mesaj = 'Gata, nu-ți mai trimitem niciun e-mail de felul ăsta.';
    } elseif (count($aprinse) === 1) {
        $mesaj = 'Îți scriem doar ' . $aprinse[0] . '.';
    } else {
        // „a, b și c" — virgulă între primele, „și" înaintea ultimului.
        $ultimul = array_pop($aprinse);
        $mesaj   = 'Îți scriem ' . implode(', ', $aprinse) . ' și ' . $ultimul . '.';
    }

    raspunsJson([
        'ok'               => true,
        'newsletter'       => $vrea,
        'email_comentarii' => $vreaComen,
        'email_feedback'   => $vreaFdb,
        'mesaj'            => $mesaj,
    ]);
}

raspunsJson(['ok' => false, 'mesaj' => 'Cerere neînțeleasă.'], 400);
