<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — cum ar arăta evenimentul, înainte să-l trimiți.
 *
 * Se deschide într-o filă nouă, din butonul „Previzualizează" al formularului.
 * Datele nu vin din adresă și nici din bază: au fost puse deoparte în sesiune
 * de api/previzualizare.php, care le-a trecut întâi prin verificările
 * obișnuite. Aici se primește doar cheia sub care stau.
 *
 * Nu se salvează nimic, nici acum, nici mai devreme. Pagina asta e o oglindă.
 */

require_once __DIR__ . '/inc/evenimente.php';
require_once __DIR__ . '/inc/afisare-eveniment.php';

$cheie  = trim((string) ($_GET['p'] ?? ''));
$membru = membruCurent();

if ($membru === null) {
    cereIntrare('/adauga_eveniment.php');
}

pornesteSesiunea();

/**
 * Cheia e legată de sesiune, deci nu poate fi dată mai departe: pentru
 * altcineva, aceeași adresă nu duce nicăieri. Dacă nu se potrivește — filă
 * veche, sesiune reîncepută — omul e trimis înapoi la formular, nu lăsat pe o
 * pagină goală.
 */
$pastrat = $_SESSION['previzualizari'][$cheie] ?? null;

if (!is_array($pastrat) || !is_array($pastrat['date'] ?? null)) {
    header('Location: adauga_eveniment.php');
    exit;
}

$date = $pastrat['date'];

/**
 * Poza aleasă în formular n-a ajuns pe server, e doar în browser. Îi lăsăm
 * aici un loc gol, pe care JS îl umple din ce a pus fila-mamă deoparte.
 * Dacă nu găsește nimic, blocul se dă la o parte singur.
 */
if (($date['coperta_url'] ?? '') === '') {
    $date['coperta_url'] = 'assets/img/avatars/implicit.svg';
    $date['coperta_din_browser'] = true;
}

$titlu     = 'Previzualizare: ' . $date['titlu'] . ' — PulsulOrasului.Ro';
$descriere = 'Așa va arăta evenimentul după ce îl trimiți.';
$noindex   = true;
$pagina    = '';

require __DIR__ . '/inc/antet.php';
?>

<main id="main" data-previzualizare="<?= h($cheie) ?>">
  <div class="wrap">

    <nav class="crumbs" aria-label="Navigare">
      <a href="index.php">Acasă</a>
      <span aria-hidden="true">/</span>
      <span><?= h((string) $date['categorie']) ?></span>
      <span aria-hidden="true">/</span>
      <span class="crumbs__current"><?= h(inceputDeText((string) $date['titlu'], 60)) ?></span>
    </nav>

    <article class="post">
      <?php
        /**
         * Aceeași funcție care desenează pagina adevărată a evenimentului —
         * vezi inc/afisare-eveniment.php. De aceea previzualizarea nu poate
         * arăta altfel decât ce se va publica: nu e o copie a paginii, e
         * chiar pagina, cu alte date.
         */
        afiseazaEveniment($date, [
            'fel'  => 'previzualizare',
            'text' => 'Previzualizare — nu e publicat nimic. Închide fila și trimite formularul.',
        ]);
      ?>
    </article>

    <!--
      „Mergi la acest eveniment?" și comentariile lipsesc dinadins: sunt încă
      șablon, cu numere inventate, și n-ar spune nimic despre evenimentul ăsta.
    -->
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
