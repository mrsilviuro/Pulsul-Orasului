<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — pregătirea unei previzualizări.
 *
 * Primește datele din formular, le trece prin ACELEAȘI verificări ca la
 * trimiterea adevărată, și — dacă trec — le pune deoparte în sesiune sub o
 * cheie întâmplătoare. Răspunde cu cheia; pagina previzualizare.php o cere
 * apoi și desenează.
 *
 * De ce în doi pași, și nu un formular cu target="_blank":
 *
 *   Un formular obișnuit ar deschide fila nouă ÎNAINTE să se știe dacă datele
 *   sunt bune, iar erorile ar ajunge acolo, nu pe formular. Or, tocmai asta
 *   trebuie evitat: omul rămâne pe formular și vede ce are de corectat, iar
 *   fila se deschide numai când chiar e ce arăta.
 *
 * Nu se scrie nimic în tabelul evenimente. Nu se cere nici limita de
 * evenimente active: previzualizarea nu creează niciun eveniment.
 */

require_once __DIR__ . '/../inc/evenimente.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    raspunsJson(['ok' => false, 'mesaj' => 'Metodă nepermisă.'], 405);
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

/* ===================== 1. Aceleași verificări ========================= */

/**
 * verificaEveniment(), fix funcția de la salvare.
 *
 * Nu o copie „mai îngăduitoare": dacă previzualizarea ar trece peste ceva ce
 * salvarea refuză, omul ar vedea o pagină frumoasă și apoi un teanc de erori.
 */
$rezultat = verificaEveniment($_POST, idCategoriiValide());

if ($rezultat['erori'] !== []) {
    raspunsJson(['ok' => false, 'erori' => $rezultat['erori']], 422);
}

$curat = $rezultat['curat'];

/* ================== 2. Ce se vede în jurul datelor =================== */

/**
 * Categoria: numele ei, nu id-ul. Se ia din lista deja citită, nu cu încă o
 * interogare.
 */
$categorie = '';

foreach (categoriiEvenimente() as $c) {
    if ((int) $c['id'] === (int) $curat['categorie_id']) {
        $categorie = (string) $c['nume'];
        break;
    }
}

/**
 * Coperta — care dintre ele.
 *
 * Fișierul nou ales în formular NU se trimite aici: pagina de previzualizare
 * îl ia din browser, unde e deja. De aceea serverul nu poate vedea singur dacă
 * omul a ales unul; i-o spune formularul, prin „coperta_noua".
 *
 * Ordinea e limpede și n-are voie să se inverseze:
 *
 *   1. poza nouă din formular — indiferent dacă se scrie unul nou sau se
 *      schimbă unul existent. Ea e cea pe care omul vrea s-o vadă.
 *   2. la editare, dacă n-a ales alta, poza salvată pe eveniment.
 *   3. altfel, nimic.
 *
 * A doua peste prima a fost chiar bug-ul: la editare, cine alegea altă poză
 * o vedea tot pe cea veche.
 */
$copertaFel = '';   // 'browser' | 'bd' | ''
$coperta    = '';

if (($_POST['coperta_noua'] ?? '') !== '') {
    $copertaFel = 'browser';
} else {
    $slugCerut = trim((string) ($_POST['slug'] ?? ''));

    if ($slugCerut !== '') {
        $deEditat = evenimentDeEditat($slugCerut, $membruId);

        if ($deEditat !== null) {
            $coperta = urlCoperta($deEditat['coperta'] ?? null);
        }
    }

    if ($coperta !== '') {
        $copertaFel = 'bd';
    }
}

/* ==================== 3. Puse deoparte în sesiune ==================== */

pornesteSesiunea();

$cheie = bin2hex(random_bytes(16));

$_SESSION['previzualizari'][$cheie] = [
    'creat' => time(),
    'date'  => [
        'titlu'            => $curat['titlu'],
        'categorie'        => $categorie,
        'locatie'          => $curat['locatie'],
        'descriere'        => $curat['descriere'],
        'data_eveniment'   => $curat['data_eveniment'],
        'ora_inceput'      => $curat['ora_inceput'],
        'ora_sfarsit'      => $curat['ora_sfarsit'],
        'cost'             => $curat['cost'],
        'varsta_minima'    => $curat['varsta_minima'],
        'participanti_min' => $curat['participanti_min'],
        'participanti_max' => $curat['participanti_max'],
        'gen_participanti' => $curat['gen_participanti'],
        'coperta_url'      => $coperta,
        'coperta_fel'      => $copertaFel,
        'organizator'      => numeAfisat($membru['nume'], $membru['prenume']),
        'organizator_url'  => 'profil.php?m=' . urlencode((string) $membru['permalink']),
        'organizator_poza' => $membru['poza'] ?? null,
        // Fără dată de publicare: încă nu e publicat nimic.
        'creat_la'         => null,
    ],
];

/**
 * Sesiunea nu e un dulap fără fund.
 *
 * Se păstrează ultimele câteva previzualizări și numai cele din ultimul sfert
 * de oră: cine apasă butonul de zece ori la rând n-are de ce să care după el
 * zece descrieri de opt mii de caractere.
 */
const PREVIZUALIZARI_PASTRATE = 3;
const PREVIZUALIZARE_MINUTE   = 15;

$_SESSION['previzualizari'] = array_filter(
    $_SESSION['previzualizari'],
    static fn(array $p): bool => (time() - (int) $p['creat']) < PREVIZUALIZARE_MINUTE * 60
);

if (count($_SESSION['previzualizari']) > PREVIZUALIZARI_PASTRATE) {
    $_SESSION['previzualizari'] = array_slice(
        $_SESSION['previzualizari'], -PREVIZUALIZARI_PASTRATE, null, true
    );
}

raspunsJson(['ok' => true, 'cheie' => $cheie]);
