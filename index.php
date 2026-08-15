<?php
declare(strict_types=1);

$titlu     = 'PulsulOrasului.Ro — Evenimente locale, sport și comunitate';
$descriere = 'Pulsul Orașului — evenimente locale, sportive, culturale și tot ce mișcă în oraș. Postat de comunitate, pentru comunitate.';
$pagina    = 'acasa';

require_once __DIR__ . '/inc/evenimente.php';

/**
 * Filtrele vin din adresă, nu din sesiune.
 *
 * Așa o pagină filtrată se poate da mai departe pe WhatsApp și se deschide la
 * fel la celălalt capăt, iar butonul „înapoi" al browserului face ce trebuie.
 * JS-ul rescrie adresa la fiecare filtrare (history.replaceState), tocmai ca
 * cele două să nu se despartă.
 *
 * Amândouă trec prin sitele lor: un oraș care nu e în config.php sau o
 * categorie care nu e în bază înseamnă „toate", nu o eroare. O adresă veche
 * trebuie să arate prima pagină, nu un ecran roșu.
 */
$orasAles      = orasulCerut($_GET['oras'] ?? null);
$categorieAleasa = categoriaCeruta($_GET['categorie'] ?? null);

/**
 * Primul teanc se scrie de PHP, nu se cere din JS după încărcare.
 *
 * Fără el, cine intră pe site ar vedea o pagină goală până pleacă și se
 * întoarce o a doua cerere — iar Google ar indexa exact golul acela. Restul
 * teancurilor vin prin api/lista-evenimente.php, la apăsare.
 */
$primaTura = evenimenteDePePrima($orasAles, $categorieAleasa);

require __DIR__ . '/inc/antet.php';
?>


<!-- ============================= SLIDESHOW ============================== -->
<section class="hero" aria-label="Recomandările redacției">
  <div class="wrap">
    <!--
      Ca să adaugi un slide nou: copiază un bloc <a class="slide"> și schimbă
      href + src. Punctele de navigare se generează automat din JS.
    -->
    <div class="slideshow" id="slideshow" data-interval="5000" aria-roledescription="carusel">
      <div class="slideshow__track" id="slideshow-track">

        <a class="slide" href="event.php">
          <img src="assets/img/slides/slide-1.svg" alt="Evenimentele săptămânii în oraș" width="1600" height="900" fetchpriority="high" decoding="async">
        </a>

        <a class="slide" href="event.php">
          <img src="assets/img/slides/slide-2.svg" alt="Competiții sportive locale" width="1600" height="900" decoding="async">
        </a>

        <a class="slide" href="event.php">
          <img src="assets/img/slides/slide-3.svg" alt="Comunitatea în acțiune" width="1600" height="900" decoding="async">
        </a>

      </div>

      <button class="slideshow__arrow slideshow__arrow--prev" type="button" data-dir="-1" aria-label="Slide-ul anterior">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 5-7 7 7 7"/></svg>
      </button>
      <button class="slideshow__arrow slideshow__arrow--next" type="button" data-dir="1" aria-label="Slide-ul următor">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
      </button>

      <div class="slideshow__dots" id="slideshow-dots" role="tablist" aria-label="Alege slide-ul"></div>
    </div>
  </div>
</section>

<!-- =============================== CONȚINUT ============================= -->
<main id="main">
  <div class="wrap">

    <!-- Titlu secțiune -->
    <div class="section-head">
      <div>
        <p class="eyebrow"><span class="pulse-dot" aria-hidden="true"></span> Live din oraș</p>
        <h1 class="section-title">Ce se întâmplă acum</h1>
      </div>
      <!--
        Butonul se vede și fără cont: cine nu e înscris trebuie să afle că
        poate publica, nu să descopere după ce se înregistrează.

        Cine nu e conectat ajunge la pagina de intrare, dar cu drumul de
        întoarcere în adresă — după autentificare pică direct pe formular, nu
        pe prima pagină, de unde ar trebui să caute butonul din nou.
      -->
      <a class="btn btn--primary btn--sm" href="<?= $logat
            ? 'adauga_eveniment.php'
            : 'login.php?redirect=' . h(urlencode('/adauga_eveniment.php')) ?>">
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M12 5v14"/><path d="M5 12h14"/>
        </svg>
        <span>Eveniment nou</span>
      </a>
    </div>

    <!-- ============================ FILTRELE ============================
      Un formular adevărat, cu `method="get"`, nu doar butoane pe care ascultă
      JS-ul. Fără JavaScript, oricare dintre ele duce tot unde trebuie: se
      încarcă pagina din nou, cu filtrele în adresă. Cu JavaScript, aceleași
      filtre aduc doar lista, fără să clatine pagina — vezi „PRIMA PAGINĂ" din
      main.js.

      De aceea categoriile sunt LEGĂTURI, nu butoane: o legătură merge și fără
      JS, se deschide în filă nouă cu clic pe mijloc și se poate da mai
      departe. Butoanele de dinainte nu făceau nimic.
    ============================================================== -->
    <form class="filtre" id="filtre" method="get" action="index.php"
          data-filtre aria-label="Filtrează evenimentele">

      <!-- ORAȘUL. `.field select` e caseta de selectare a site-ului, cu
           săgeata desenată inline — aceeași ca în formularul de eveniment. -->
      <div class="filtre__oras field">
        <label class="sr-only" for="filtru-oras">Orașul</label>
        <select id="filtru-oras" name="oras" data-filtru-oras>
          <option value="">Toate orașele</option>
          <?php foreach (oraseDisponibile() as $oras): ?>
          <option value="<?= h($oras) ?>" <?= $orasAles === $oras ? 'selected' : '' ?>>
            <?= h($oras) ?>
          </option>
          <?php endforeach; ?>
        </select>

        <!-- Numai pentru cine n-are JS: cu el, schimbarea din listă filtrează
             singură, iar butonul ăsta n-are ce face. JS îl ia din pagină. -->
        <noscript>
          <button class="btn btn--primary btn--sm" type="submit">Arată</button>
        </noscript>
      </div>

      <!-- CATEGORIILE, din tabelul `categorii`. Ordinea vine de acolo
           (`ordine`), nu de aici. -->
      <div class="chips" aria-label="Filtrează după categorie">
        <a class="chip<?= $categorieAleasa === '' ? ' is-active' : '' ?>"
           href="<?= h(adresaFiltrata($orasAles, '')) ?>"
           data-filtru-categorie=""
           <?= $categorieAleasa === '' ? 'aria-current="true"' : '' ?>>Toate</a>

        <?php foreach (categoriiEvenimente() as $categorie): ?>
        <a class="chip<?= $categorieAleasa === $categorie['slug'] ? ' is-active' : '' ?>"
           href="<?= h(adresaFiltrata($orasAles, (string) $categorie['slug'])) ?>"
           data-filtru-categorie="<?= h((string) $categorie['slug']) ?>"
           <?= $categorieAleasa === $categorie['slug'] ? 'aria-current="true"' : '' ?>><?= h((string) $categorie['nume']) ?></a>
        <?php endforeach; ?>
      </div>
    </form>

    <!-- ============================ EVENIMENTELE ========================
      Primul teanc e scris de PHP; următoarele vin prin fetch și se lipesc la
      coadă. Amândouă trec prin randeazaListaEvenimente(), deci arată la fel.

      `aria-live="polite"` ca cititorul de ecran să spună că au apărut altele,
      fără să întrerupă ce citea.
    ============================================================== -->
    <div class="grid" id="lista-evenimente" data-lista-evenimente aria-live="polite">
      <?= randeazaListaEvenimente($primaTura['evenimente']) ?>
    </div>

    <!-- Când nu e nimic de arătat. Se scrie și când filtrele nu potrivesc
         nimic, iar JS îl aprinde și-l stinge fără să reîncarce pagina. -->
    <p class="grid-gol" data-lista-goala <?= $primaTura['evenimente'] !== [] ? 'hidden' : '' ?>>
      Niciun eveniment pe potriva filtrelor. Încearcă alt oraș sau altă categorie.
    </p>

    <!--
      „Vezi mai mult", fără numărul celor rămase: pe prima pagină nu se știe
      câte mai sunt fără să le numeri pe toate, iar numărul acela n-ar spune
      nimic folositor. Se știe doar dacă MAI E ceva — atât cere butonul ca să
      hotărască dacă rămâne pe ecran.
    -->
    <div class="load-more" data-mai-multe <?= $primaTura['mai_sunt'] ? '' : 'hidden' ?>>
      <button class="btn btn--ghost" type="button" data-mai-multe-buton>Vezi mai mult</button>
    </div>

    <!-- CTA: contribuie -->
    <section class="cta">
      <div class="cta__glow" aria-hidden="true"></div>
      <div class="cta__content">
        <p class="eyebrow eyebrow--light">Scrie și tu</p>
        <h2>Ai un eveniment în oraș? Publică-l aici.</h2>
        <p class="cta__text">Pulsul Orașului e scris de oameni din oraș. Creează-ți cont și publică evenimentul tău în câteva minute — gratuit.</p>
        <div class="cta__actions">
          <a class="btn btn--primary" href="login.php#inregistrare">Alătură-te și tu</a>
          <a class="btn btn--outline" href="despre.php">Cum funcționează</a>
        </div>
      </div>
    </section>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
