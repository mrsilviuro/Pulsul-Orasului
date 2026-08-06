<?php
declare(strict_types=1);

$titlu     = 'P. Ionuț — Profil membru — PulsulOrasului.Ro';
$descriere = 'Profilul membrului P. Ionuț pe PulsulOrasului.Ro: evenimente organizate, participări și evaluări primite.';

require __DIR__ . '/inc/antet.php';
?>


<main id="main">
  <div class="wrap">

    <nav class="crumbs" aria-label="Navigare">
      <a href="index.php">Acasă</a>
      <span aria-hidden="true">/</span>
      <a href="#">Membri</a>
      <span aria-hidden="true">/</span>
      <span class="crumbs__current">P. Ionuț</span>
    </nav>

    <!-- ========================= ANTETUL PROFILULUI =====================
      Numele vine deja prescurtat de la server: inițiala numelui de familie,
      punct, prenumele întreg („Popescu Ionuț" → „P. Ionuț"). Numele complet
      nu se trimite deloc în pagină, ca să nu poată fi citit din sursă.
    ================================================================== -->
    <header class="profile">
      <div class="profile__id">
        <img class="profile__avatar" src="assets/img/avatars/andrei.svg" alt="" width="96" height="96">

        <div class="profile__head">
          <h1 class="profile__name">P. Ionuț</h1>

          <ul class="facts">
            <li class="fact">
              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                <rect x="3.5" y="5" width="17" height="16" rx="3"/><path d="M8 3v4M16 3v4M3.5 10h17"/>
              </svg>
              <span>34 de ani</span>
            </li>

            <li class="fact">
              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>
              </svg>
              <span>Brașov</span>
            </li>

            <!--
              Sexul: doar simbolul, desenat de noi.
              Masculin  → clasa fact--m  și simbolul Marte
              Feminin   → clasa fact--f  și simbolul Venus
              Nespecificat → se scoate tot elementul <li>
            -->
            <li class="fact fact--m" title="Masculin">
              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="10" cy="14.2" r="5.8"/>
                <path d="m14.3 10.3 5.4-5.4"/>
                <path d="M14.6 4.6h5.3v5.3"/>
              </svg>
              <span class="sr-only">Masculin</span>
            </li>

            <li class="fact fact--muted">
              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.4 2"/>
              </svg>
              <span>Membru din mai 2024</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- ============================ RATING ============================
        data-stars = media primită (0 → stele goale și „Fără rating").
        data-stars-count = câte evaluări stau la baza mediei.
        Stelele se desenează din JS, ca să nu repetăm zece SVG-uri de
        fiecare dată — vezi buildStars() din main.js.
      ============================================================== -->
      <div class="rating">
        <div class="rating__score">
          <span class="rating__value" data-stars-value>4,6</span>
          <span class="rating__max">/ 5</span>
        </div>
        <div class="rating__stars" data-stars="4.6" data-stars-count="23"></div>
        <p class="rating__meta" data-stars-label></p>
      </div>
    </header>

    <!-- ========================== ACTIVITATE ============================ -->
    <section class="stats" aria-label="Activitatea membrului">
      <div class="stat">
        <span class="stat__ico stat__ico--brand" aria-hidden="true">
          <svg class="ico" viewBox="0 0 24 24">
            <rect x="3.5" y="5" width="17" height="16" rx="3"/><path d="M8 3v4M16 3v4M3.5 10h17"/>
            <path d="M8.5 14.5h3M8.5 17.5h7"/>
          </svg>
        </span>
        <span class="stat__value">12</span>
        <span class="stat__label">Evenimente organizate</span>
      </div>

      <div class="stat">
        <span class="stat__ico stat__ico--ok" aria-hidden="true">
          <svg class="ico" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
          </svg>
        </span>
        <span class="stat__value">47</span>
        <span class="stat__label">Prezent la evenimente</span>
      </div>

      <div class="stat">
        <span class="stat__ico stat__ico--warn" aria-hidden="true">
          <svg class="ico" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>
          </svg>
        </span>
        <span class="stat__value">3</span>
        <span class="stat__label">A confirmat, dar nu a venit</span>
      </div>
    </section>

    <!-- =========================== FEEDBACK ============================= -->
    <section class="feedback" aria-labelledby="feedback-title">

      <div class="section-head">
        <div>
          <p class="eyebrow"><span class="pulse-dot" aria-hidden="true"></span> Ce spun ceilalți</p>
          <h2 class="section-title" id="feedback-title">Evaluări</h2>
        </div>
      </div>

      <!-- Rezumatul notelor -->
      <div class="rating-summary">
        <div class="rating-summary__score">
          <span class="rating-summary__value">4,6</span>
          <div class="rating__stars" data-stars="4.6"></div>
          <span class="rating-summary__count">23 de evaluări</span>
        </div>

        <!--
          Distribuția pe stele. data-percent se calculează pe server:
          (număr de note de N stele / total note) × 100.
        -->
        <ul class="rating-bars">
          <li><span>5</span><div class="rating-bar"><i style="width:70%"></i></div><span>16</span></li>
          <li><span>4</span><div class="rating-bar"><i style="width:22%"></i></div><span>5</span></li>
          <li><span>3</span><div class="rating-bar"><i style="width:4%"></i></div><span>1</span></li>
          <li><span>2</span><div class="rating-bar"><i style="width:4%"></i></div><span>1</span></li>
          <li><span>1</span><div class="rating-bar"><i style="width:0%"></i></div><span>0</span></li>
        </ul>
      </div>

      <!-- Formular: notă + comentariu -->
      <form class="review-form" id="review-form" novalidate>
        <img class="comment-form__avatar" src="assets/img/avatars/cristi.svg" alt="" width="96" height="96">

        <div class="comment-form__main">
          <div class="review-form__stars">
            <span class="review-form__ask">Ce notă îi dai?</span>
            <!-- Selectorul de stele se generează din JS, în [data-stars-input]. -->
            <div class="stars-input" data-stars-input id="review-stars"></div>
            <span class="review-form__chosen" id="review-chosen">Nicio notă aleasă</span>
          </div>

          <label class="sr-only" for="review-text">Comentariul tău</label>
          <textarea id="review-text" rows="3" placeholder="Spune pe scurt cum a fost…"></textarea>

          <div class="comment-form__actions">
            <p class="comment-form__hint">Evaluează doar oameni cu care ai fost la un eveniment.</p>
            <button class="btn btn--primary btn--sm" type="submit">Trimite evaluarea</button>
          </div>
        </div>
      </form>

      <!-- Lista de evaluări primite -->
      <ul class="comments">

        <li class="comment">
          <article class="comment__body">
            <img class="comment__avatar" src="assets/img/avatars/ioana.svg" alt="" width="96" height="96" loading="lazy">
            <div class="comment__main">
              <div class="comment__head">
                <a class="comment__author" href="profil.php">R. Ioana</a>
                <span class="badge">Organizator</span>
                <span class="dot" aria-hidden="true"></span>
                <time datetime="2026-07-28">28 iulie 2026</time>
              </div>
              <div class="rating__stars rating__stars--sm" data-stars="5"></div>
              <p>
                A organizat cursa de la lac impecabil. A stat până la ultimul participant și
                a ajutat la strâns. Recomand cu încredere.
              </p>
              <div class="comment__tools">
                <button class="comment__tool" type="button" data-like aria-pressed="false">
                  <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M7 20V9.5l4.2-6a1.6 1.6 0 0 1 2.9 1.2L13.3 9H19a2 2 0 0 1 2 2.4l-1.4 6.4A2.6 2.6 0 0 1 17 20Z"/>
                    <path d="M7 9.8H4.2A1.2 1.2 0 0 0 3 11v7.8c0 .7.5 1.2 1.2 1.2H7"/>
                  </svg>
                  <span data-like-count>7</span>
                </button>
              </div>
            </div>
          </article>
        </li>

        <li class="comment">
          <article class="comment__body">
            <img class="comment__avatar" src="assets/img/avatars/mihai.svg" alt="" width="96" height="96" loading="lazy">
            <div class="comment__main">
              <div class="comment__head">
                <a class="comment__author" href="profil.php">C. Mihai</a>
                <span class="dot" aria-hidden="true"></span>
                <time datetime="2026-07-15">15 iulie 2026</time>
              </div>
              <div class="rating__stars rating__stars--sm" data-stars="4"></div>
              <p>
                Om serios, ne-am văzut la două ture cu bicicleta. Singurul minus: a anunțat
                traseul cam târziu, cu o zi înainte.
              </p>
              <div class="comment__tools">
                <button class="comment__tool" type="button" data-like aria-pressed="false">
                  <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M7 20V9.5l4.2-6a1.6 1.6 0 0 1 2.9 1.2L13.3 9H19a2 2 0 0 1 2 2.4l-1.4 6.4A2.6 2.6 0 0 1 17 20Z"/>
                    <path d="M7 9.8H4.2A1.2 1.2 0 0 0 3 11v7.8c0 .7.5 1.2 1.2 1.2H7"/>
                  </svg>
                  <span data-like-count>3</span>
                </button>
              </div>
            </div>
          </article>
        </li>

        <li class="comment">
          <article class="comment__body">
            <img class="comment__avatar" src="assets/img/avatars/elena.svg" alt="" width="96" height="96" loading="lazy">
            <div class="comment__main">
              <div class="comment__head">
                <span class="comment__author comment__author--system">Sistem</span>
                <span class="badge badge--author">Automat</span>
                <span class="dot" aria-hidden="true"></span>
                <time datetime="2026-07-01">1 iulie 2026</time>
              </div>
              <div class="rating__stars rating__stars--sm" data-stars="5"></div>
              <p>
                Notă acordată automat: zece evenimente organizate fără nicio anulare.
              </p>
            </div>
          </article>
        </li>

      </ul>

      <div class="load-more">
        <button class="btn btn--ghost" type="button">Vezi toate cele 23 de evaluări</button>
      </div>
    </section>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
