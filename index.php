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


<!-- =========================== PRIMA FEREASTRĂ ==========================
  Cât toată fereastra browserului, imediat sub bara de meniu, și lată cât ea:
  singurul loc de pe site care NU stă în `.wrap`. De aceea nici nu are un
  `<div class="wrap">` înăuntru — doar textul din mijloc e strunit la o lățime
  citibilă (`.hero__continut`), fundalul merge dintr-o margine în alta.

  Fundalul e un `<div>` gol, nu un `<img>`: se schimbă cu tema, iar tema de pe
  site se pune de mână (data-theme), nu se citește din sistem. Un `<picture>`
  cu `prefers-color-scheme` ar fi ascultat de sistem și ar fi rămas cu poza de
  zi pe cine apasă luna. Vezi „PRIMA FEREASTRĂ" din style.css.

  Săgeata de jos e o legătură adevărată spre `#main`, nu un buton de JS: merge
  și cu JavaScript-ul stins, iar derularea lină vine din `scroll-behavior`.
-->
<section class="hero" aria-labelledby="hero-titlu">
  <div class="hero__fundal" aria-hidden="true"></div>
  <div class="hero__voal" aria-hidden="true"></div>

  <div class="hero__continut">
    <p class="hero__salut">Bun venit pe</p>
    <h1 class="hero__nume" id="hero-titlu">PulsulOrasului.Ro</h1>
    <p class="hero__vorba">
      Fiecare ieșire contează. Tu vii cu ideea, noi venim cu energia. Implică-te!
    </p>

    <!--
      Butonul se vede și fără cont: cine nu e înscris trebuie să afle că poate
      publica, nu să descopere după ce se înregistrează.

      Cine nu e conectat ajunge la pagina de intrare, dar cu drumul de
      întoarcere în adresă — după autentificare pică direct pe formular, nu pe
      prima pagină, de unde ar trebui să caute butonul din nou.
    -->
    <a class="btn btn--primary hero__cta" href="<?= $logat
          ? 'adauga_eveniment.php'
          : 'login.php?redirect=' . h(urlencode('/adauga_eveniment.php')) ?>">
      <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M12 5v14"/><path d="M5 12h14"/>
      </svg>
      <span>Propune o ieșire</span>
    </a>
  </div>

  <a class="hero__jos" href="#main">
    <span>Vezi ieșirile</span>
    <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M12 5v14"/><path d="m19 12-7 7-7-7"/>
    </svg>
  </a>
</section>

<!-- =============================== CONȚINUT ============================= -->
<main id="main">
  <div class="wrap">

    <!--
      Titlu secțiune. `h2`, nu `h1`: numele site-ului din prima fereastră e
      titlul paginii, iar pe o pagină nu stau două titluri de rang întâi.
      Butonul „Propune o ieșire" s-a mutat și el sus, lângă nume.
    -->
    <div class="section-head">
      <div>
        <h2 class="section-title">Ce facem zilele astea?</h2>
      </div>
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

      <!-- CATEGORIILE, din tabelul `categorii` — dar numai cele în care chiar
           s-a pus ceva. O categorie goală e un buton care duce la un ecran
           gol: ocupă loc, se apasă o dată și pe urmă nu mai are cine să se
           încreadă în rândul ăsta. Ordinea vine din tabel (`ordine`).

           În formularul de publicat un eveniment apar TOATE, tocmai ca ele să
           se poată umple — acolo se cheamă categoriiEvenimente(). -->
      <div class="chips" aria-label="Filtrează după categorie">
        <a class="chip<?= $categorieAleasa === '' ? ' is-active' : '' ?>"
           href="<?= h(adresaFiltrata($orasAles, '')) ?>"
           data-filtru-categorie=""
           <?= $categorieAleasa === '' ? 'aria-current="true"' : '' ?>>Toate</a>

        <?php foreach (categoriiCuEvenimente() as $categorie): ?>
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
        <p class="eyebrow eyebrow--light">REDESCHIDEM ORAȘUL ÎMPREUNĂ</p>
        <h2>Vrei să ieși afară dar n-ai cu cine?</h2>
        <p class="cta__text">De la jocuri de weekend până la drumeții sau ieșiri cu motoarele. Adaugă activitatea ta pe site și cunoaște oameni faini din oraș.</p>
        <div class="cta__actions">
          <a class="btn btn--primary" href="adauga_eveniment.php">Propune o ieșire</a>
          <a class="btn btn--outline" href="despre.php">Despre Pulsul Orașului</a>
        </div>
      </div>
    </section>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
