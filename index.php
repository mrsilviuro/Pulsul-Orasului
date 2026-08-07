<?php
declare(strict_types=1);

$titlu     = 'PulsulOrasului.Ro — Evenimente locale, sport și comunitate';
$descriere = 'Pulsul Orașului — evenimente locale, sportive, culturale și tot ce mișcă în oraș. Postat de comunitate, pentru comunitate.';
$pagina    = 'acasa';

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

        <a class="slide" href="articol.php">
          <img src="assets/img/slides/slide-1.svg" alt="Evenimentele săptămânii în oraș" width="1600" height="900" fetchpriority="high" decoding="async">
        </a>

        <a class="slide" href="articol.php">
          <img src="assets/img/slides/slide-2.svg" alt="Competiții sportive locale" width="1600" height="900" decoding="async">
        </a>

        <a class="slide" href="articol.php">
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

    <!-- Filtre categorii -->
    <div class="chips" role="tablist" aria-label="Filtrează după categorie">
      <button class="chip is-active" type="button" role="tab" aria-selected="true">Toate</button>
      <button class="chip" type="button" role="tab" aria-selected="false">Sport</button>
      <button class="chip" type="button" role="tab" aria-selected="false">Cultură</button>
      <button class="chip" type="button" role="tab" aria-selected="false">Comunitate</button>
      <button class="chip" type="button" role="tab" aria-selected="false">Gastro</button>
    </div>

    <!-- Grilă articole -->
    <div class="grid">

      <article class="card">
        <a class="card__media" href="articol.php">
          <img src="assets/img/posts/post-2.svg" alt="" width="1280" height="720" loading="lazy" decoding="async">
          <span class="card__tag">Cultură</span>
        </a>
        <div class="card__body">
          <h2 class="card__title"><a href="articol.php">Trei zile de festival în parcul central, cu intrare liberă</a></h2>
          <p class="card__excerpt">Peste 20 de trupe, food trucks și o zonă dedicată celor mici. Programul complet, pe zile.</p>
          <div class="card__meta">
            <span class="avatar" aria-hidden="true">IR</span>
            <span class="card__author">Ioana R.</span>
            <span class="dot" aria-hidden="true"></span>
            <time datetime="2026-08-03">3 aug 2026</time>
          </div>
        </div>
      </article>

      <article class="card">
        <a class="card__media" href="articol.php">
          <img src="assets/img/posts/post-3.svg" alt="" width="1280" height="720" loading="lazy" decoding="async">
          <span class="card__tag">Comunitate</span>
        </a>
        <div class="card__body">
          <h2 class="card__title"><a href="articol.php">Piața de weekend își schimbă locul. Unde o găsești de sâmbătă</a></h2>
          <p class="card__excerpt">Producătorii locali se mută temporar, pe perioada lucrărilor din zona centrală.</p>
          <div class="card__meta">
            <span class="avatar" aria-hidden="true">VS</span>
            <span class="card__author">Vlad S.</span>
            <span class="dot" aria-hidden="true"></span>
            <time datetime="2026-08-02">2 aug 2026</time>
          </div>
        </div>
      </article>

      <article class="card">
        <a class="card__media" href="articol.php">
          <img src="assets/img/posts/post-4.svg" alt="" width="1280" height="720" loading="lazy" decoding="async">
          <span class="card__tag">Muzică</span>
        </a>
        <div class="card__body">
          <h2 class="card__title"><a href="articol.php">Concert în aer liber pe faleză, sâmbătă seara</a></h2>
          <p class="card__excerpt">Acces gratuit, dar locurile pe scaune se rezervă online. Cum ajungi cu transportul public.</p>
          <div class="card__meta">
            <span class="avatar" aria-hidden="true">DP</span>
            <span class="card__author">Diana P.</span>
            <span class="dot" aria-hidden="true"></span>
            <time datetime="2026-08-01">1 aug 2026</time>
          </div>
        </div>
      </article>

      <article class="card">
        <a class="card__media" href="articol.php">
          <img src="assets/img/posts/post-5.svg" alt="" width="1280" height="720" loading="lazy" decoding="async">
          <span class="card__tag">Sport</span>
        </a>
        <div class="card__body">
          <h2 class="card__title"><a href="articol.php">Prima pistă de ciclism care leagă cele două maluri</a></h2>
          <p class="card__excerpt">7 kilometri, complet separați de trafic. Am mers pe ea și îți spunem cum arată.</p>
          <div class="card__meta">
            <span class="avatar" aria-hidden="true">MC</span>
            <span class="card__author">Mihai C.</span>
            <span class="dot" aria-hidden="true"></span>
            <time datetime="2026-07-30">30 iul 2026</time>
          </div>
        </div>
      </article>

      <article class="card">
        <a class="card__media" href="articol.php">
          <img src="assets/img/posts/post-6.svg" alt="" width="1280" height="720" loading="lazy" decoding="async">
          <span class="card__tag">Gastro</span>
        </a>
        <div class="card__body">
          <h2 class="card__title"><a href="articol.php">Târgul de street food, ediția de vară: 30 de standuri</a></h2>
          <p class="card__excerpt">De la langoși la ramen. Ce merită încercat și cât costă, realist.</p>
          <div class="card__meta">
            <span class="avatar" aria-hidden="true">RG</span>
            <span class="card__author">Raluca G.</span>
            <span class="dot" aria-hidden="true"></span>
            <time datetime="2026-07-28">28 iul 2026</time>
          </div>
        </div>
      </article>

    </div>

    <div class="load-more">
      <button class="btn btn--ghost" type="button">Încarcă mai multe articole</button>
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
