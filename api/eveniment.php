<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — publicarea unui eveniment.
 *
 * Spre deosebire de celelalte puncte de intrare, aici datele vin ca
 * „multipart/form-data", nu ca JSON: e singurul fel în care poate urca și un
 * fișier. Restul verificărilor sunt aceleași.
 */

require_once __DIR__ . '/../inc/evenimente.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    raspunsJson(['ok' => false, 'mesaj' => 'Metodă nepermisă.'], 405);
}

/**
 * Un formular mai mare decât post_max_size ajunge aici gol: PHP aruncă totul
 * și nu spune nimic. Fără rândurile astea, omul ar primi „completează
 * câmpurile", deși le completase pe toate — și n-ar înțelege niciodată de ce.
 */
$primite   = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
$limitaPhp = octetiDinSetare((string) ini_get('post_max_size'));

if ($_POST === [] && $_FILES === [] && $primite > 0 && $limitaPhp > 0 && $primite > $limitaPhp) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Coperta e prea mare pentru server. Încarcă una mai mică.',
    ], 413);
}

if (!tokenCsrfValid(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
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

$membruId = (int) $membru['id'];

/* ==================== 1. Are voie să publice acum? ==================== */

/**
 * Se verifică ÎNAINTE de orice altceva.
 *
 * Altfel am procesa coperta — partea cea mai scumpă — pentru un eveniment pe
 * care oricum nu-l primim.
 */
$voie = poatePublicaEveniment($membruId);

if (!$voie['poate']) {
    raspunsJson(['ok' => false, 'mesaj' => $voie['mesaj']], 409);
}

/* ========================= 2. Câmpurile ============================== */

$rezultat = verificaEveniment($_POST, idCategoriiValide());

if ($rezultat['erori'] !== []) {
    raspunsJson(['ok' => false, 'erori' => $rezultat['erori']], 422);
}

$curat = $rezultat['curat'];

/* =========================== 3. Coperta ============================== */

/**
 * Coperta e opțională: fără ea, în bază intră NULL, iar la afișare se ia
 * imaginea implicită a categoriei.
 *
 * Se procesează DUPĂ verificarea câmpurilor: dacă titlul lipsește, n-are rost
 * să scriem un fișier pe disc pe care apoi să-l ștergem.
 */
$coperta = null;
$fisier  = $_FILES['coperta'] ?? null;

if (is_array($fisier) && (int) ($fisier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    /**
     * Cadrul ales din pagină. Nu-l credem pe cuvânt — potrivesteDecupajCoperta()
     * îl aduce la ceva care încape în poză. Trimitem fișierul întreg și trei
     * numere, nu poza gata tăiată de JavaScript: altfel am salva ce vrea cel de
     * la tastatură, nu ce am cerut noi.
     */
    $decupaj = null;

    // is_numeric înainte de cast, ca la poza de profil: „l[]=1" ar fi un tablou,
    // iar un tablou aruncat într-un (float) e o ceartă de pomană.
    if (isset($_POST['l']) && is_numeric($_POST['l'])) {
        $decupaj = [
            'x' => is_numeric($_POST['x'] ?? null) ? (float) $_POST['x'] : 0,
            'y' => is_numeric($_POST['y'] ?? null) ? (float) $_POST['y'] : 0,
            'l' => (float) $_POST['l'],
        ];
    }

    $poza = procesezaCoperta($fisier, $decupaj);

    if (!$poza['ok']) {
        raspunsJson(['ok' => false, 'erori' => ['coperta' => $poza['mesaj']]], 422);
    }

    $coperta = $poza['nume'];
}

/* =========================== 4. Salvarea ============================= */

try {
    salveazaEveniment($membruId, $curat, $coperta);
} catch (PDOException $e) {
    // Fișierul de pe disc n-are de ce să rămână dacă rândul n-a intrat.
    stergeCopertaDeFisier($coperta);
    throw $e;
}

raspunsJson([
    'ok'    => true,
    'mesaj' => 'Evenimentul tău a fost trimis spre aprobare.',
]);
