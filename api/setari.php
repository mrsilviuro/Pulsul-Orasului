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

    /**
     * Amândouă într-o singură scriere, fiindcă vin dintr-un singur formular:
     * două UPDATE-uri ar fi putut lăsa una salvată și cealaltă nu, dacă pica
     * ceva la mijloc — iar omul ar fi văzut „salvat" pentru o hotărâre făcută
     * pe jumătate.
     */
    db()->prepare('UPDATE membri SET newsletter = ?, email_comentarii = ? WHERE id = ?')
        ->execute([$vrea ? 1 : 0, $vreaComen ? 1 : 0, (int) $membru['id']]);

    /**
     * Mesajul spune ce s-a ales, nu doar „gata".
     *
     * Cu două bife, un singur „Salvat" n-ar mai fi spus nimic: omul care a
     * stins una și a lăsat-o pe cealaltă n-ar fi știut dacă s-a înțeles care.
     */
    if ($vrea && $vreaComen) {
        $mesaj = 'Gata, primești și evenimentele noi, și răspunsurile la comentarii.';
    } elseif ($vrea) {
        $mesaj = 'Îți scriem când apar evenimente noi, dar nu și la comentarii.';
    } elseif ($vreaComen) {
        $mesaj = 'Îți scriem doar când cineva îți comentează sau îți răspunde.';
    } else {
        $mesaj = 'Gata, nu-ți mai trimitem niciun e-mail de felul ăsta.';
    }

    raspunsJson([
        'ok'               => true,
        'newsletter'       => $vrea,
        'email_comentarii' => $vreaComen,
        'mesaj'            => $mesaj,
    ]);
}

raspunsJson(['ok' => false, 'mesaj' => 'Cerere neînțeleasă.'], 400);
