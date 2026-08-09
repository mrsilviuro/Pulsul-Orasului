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

/* ============== 1. Eveniment nou, sau unul care se schimbă? ============ */

/**
 * Slugul spune care e care: dacă vine unul, se editează evenimentul acela.
 *
 * Cine are voie hotărăște evenimentDeEditat(), aceeași funcție de care se
 * folosește și pagina cu formularul. Nu ne bazăm pe faptul că formularul s-a
 * deschis: cererea asta poate veni de oriunde, cu orice slug în ea.
 */
$slugCerut = trim((string) ($_POST['slug'] ?? ''));
$deEditat  = null;

if ($slugCerut !== '') {
    $deEditat = evenimentDeEditat($slugCerut, $membruId);

    if ($deEditat === null) {
        raspunsJson([
            'ok'    => false,
            'mesaj' => 'Evenimentul nu mai există sau nu e al tău.',
        ], 404);
    }
}

/* ==================== 2. Are voie să publice acum? ==================== */

/**
 * Numai la eveniment nou.
 *
 * La editare, limita s-ar aplica evenimentului care se editează: omul cu un
 * singur eveniment activ ar fi oprit tocmai de el, deci n-ar mai putea corecta
 * niciodată nimic.
 *
 * Se verifică ÎNAINTE de orice altceva. Altfel am procesa coperta — partea cea
 * mai scumpă — pentru un eveniment pe care oricum nu-l primim.
 */
if ($deEditat === null) {
    $voie = poatePublicaEveniment($membruId);

    if (!$voie['poate']) {
        raspunsJson(['ok' => false, 'mesaj' => $voie['mesaj']], 409);
    }
}

/* ========================= 3. Câmpurile ============================== */

$rezultat = verificaEveniment($_POST, idCategoriiValide());

if ($rezultat['erori'] !== []) {
    raspunsJson(['ok' => false, 'erori' => $rezultat['erori']], 422);
}

$curat = $rezultat['curat'];

/* =========================== 4. Coperta ============================== */

/**
 * Coperta e opțională: fără ea, în bază intră NULL, iar la afișare se ia
 * imaginea implicită a categoriei.
 *
 * La editare, un formular trimis fără fișier nu înseamnă „șterge poza",
 * înseamnă „n-am umblat la ea" — de aceea $coperta rămâne null și
 * actualizeazaEveniment() nu atinge coloana.
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

/* =========================== 5. Salvarea ============================= */

try {
    if ($deEditat !== null) {
        actualizeazaEveniment((int) $deEditat['id'], $curat, $coperta);
        $slug = (string) $deEditat['slug'];

        // Poza veche se șterge abia după ce rândul s-a schimbat cu bine.
        // Invers, o eroare la scriere ar lăsa evenimentul arătând spre un
        // fișier care nu mai e.
        if ($coperta !== null) {
            stergeCopertaDeFisier($deEditat['coperta'] ?? null);
        }
    } else {
        $slug = salveazaEveniment($membruId, $curat, $coperta);
    }
} catch (PDOException $e) {
    // Fișierul nou n-are de ce să rămână dacă rândul n-a intrat.
    stergeCopertaDeFisier($coperta);
    throw $e;
}

// Adresa pleacă înapoi ca panoul de „gata" să aibă unde trimite omul: la un
// eveniment nou, pagina abia acum s-a născut, deci formularul n-avea de unde
// să știe slugul când s-a tipărit.
raspunsJson([
    'ok'    => true,
    'mesaj' => 'Evenimentul tău a fost trimis spre aprobare.',
    'url'   => $slug !== '' ? urlEveniment($slug) : '',
]);
