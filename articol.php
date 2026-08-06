<?php
declare(strict_types=1);

$titlu     = 'Maratonul orașului revine: peste 4.000 de alergători la start — PulsulOrasului.Ro';
$descriere = 'Traseul trece anul acesta prin centrul vechi. Tot ce trebuie să știi despre restricții, puncte de hidratare și program.';

require __DIR__ . '/inc/antet.php';
?>


<!-- bara de progres a citirii -->
<div class="read-progress" id="read-progress" aria-hidden="true"><span></span></div>

<main id="main">
  <div class="wrap">

    <!-- Firimituri -->
    <nav class="crumbs" aria-label="Navigare">
      <a href="index.php">Acasă</a>
      <span aria-hidden="true">/</span>
      <a href="#">Sport</a>
      <span aria-hidden="true">/</span>
      <span class="crumbs__current">Maratonul orașului</span>
    </nav>

    <article class="post">

      <!-- ======================= ANTETUL ARTICOLULUI ======================= -->
      <header class="post__head">
        <span class="post__cat">Sport</span>
        <h1 class="post__title">Maratonul orașului revine: peste 4.000 de alergători la start</h1>
        <p class="post__lead">
          Traseul trece anul acesta prin centrul vechi, iar înscrierile pentru cursa de 10 km
          s-au închis în mai puțin de 48 de ore. Tot ce trebuie să știi despre restricții de
          circulație, puncte de hidratare și programul pe ore.
        </p>

        <div class="post__meta">
          <img class="post__avatar" src="assets/img/avatars/andrei.svg" alt="" width="96" height="96">
          <div class="post__by">
            <a class="post__author" href="profil.php">Andrei Munteanu</a>
            <div class="post__sub">
              <time datetime="2026-08-04">4 august 2026</time>
              <span class="dot" aria-hidden="true"></span>
              <span>5 min citire</span>
            </div>
          </div>

          <div class="post__share">
            <button class="icon-btn" type="button" aria-label="Distribuie pe Facebook">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8.5V7a1.5 1.5 0 0 1 1.5-1.5H17V3h-2.2A3.8 3.8 0 0 0 11 6.8v1.7H9V11h2v10h3V11h2.2l.4-2.5H14Z" fill="currentColor" stroke="none"/></svg>
            </button>
            <button class="icon-btn" type="button" aria-label="Distribuie pe WhatsApp">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-11.9 7L4 20l1.6-4A8 8 0 1 1 20 11.5Z"/><path d="M9 9.5c.4 2 1.6 3.2 3.6 3.6l.9-1.2 1.7.8-.4 1.4c-2.9.3-5.8-2.6-5.5-5.5l1.4-.4.8 1.7z" fill="currentColor" stroke="none"/></svg>
            </button>
            <button class="icon-btn" type="button" aria-label="Copiază linkul" id="copy-link">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3A4 4 0 0 0 13 5.3l-1.4 1.4"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3A4 4 0 0 0 11 18.7l1.4-1.4"/></svg>
            </button>
          </div>
        </div>
      </header>

      <!-- ======================= THUMBNAIL 16:9 =========================== -->
      <figure class="post__figure">
        <img src="assets/img/posts/post-1.svg" alt="Alergători pe traseul din centrul orașului"
             width="1280" height="720" fetchpriority="high" decoding="async">
        <figcaption>Startul se dă din Piața Centrală, la ora 08:00. Foto: arhiva PulsulOrasului.Ro</figcaption>
      </figure>

      <!-- ==================== DETALIILE EVENIMENTULUI ===================== -->
      <section class="event-box" aria-label="Detaliile evenimentului">
        <div class="event-box__item">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <rect x="3.5" y="5" width="17" height="16" rx="3"/><path d="M8 3v4M16 3v4M3.5 10h17"/>
          </svg>
          <div><span>Data</span><strong>Duminică, 16 august 2026</strong></div>
        </div>
        <div class="event-box__item">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.4 2"/>
          </svg>
          <div><span>Ora</span><strong>08:00 — 14:00</strong></div>
        </div>
        <div class="event-box__item">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>
          </svg>
          <div><span>Locul</span><strong>Piața Centrală</strong></div>
        </div>
        <div class="event-box__item">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <rect x="2.5" y="6" width="19" height="12" rx="3"/><circle cx="12" cy="12" r="2.6"/>
          </svg>
          <div><span>Acces</span><strong>Gratuit pentru public</strong></div>
        </div>
      </section>

      <!-- ======================== CORPUL ARTICOLULUI ====================== -->
      <div class="post__body">
        <p>
          După doi ani în care traseul a ocolit zona pietonală, organizatorii au anunțat că
          ediția din această vară revine în inima orașului. Este cea mai mare ediție de până
          acum: <strong>peste 4.000 de participanți</strong> înscriși la cele trei curse, dintre
          care aproape jumătate la proba de 10 kilometri.
        </p>

        <h2>Traseul și restricțiile de circulație</h2>
        <p>
          Startul se dă din Piața Centrală și urmează bulevardul principal până la parcul de pe
          malul râului, de unde alergătorii revin pe strada veche. Circulația va fi închisă
          între orele 07:00 și 15:00 pe următoarele artere:
        </p>
        <ul>
          <li>Bulevardul Principal, între Piața Centrală și podul de fier</li>
          <li>Strada Veche, pe toată lungimea ei</li>
          <li>Aleea Parcului și accesul dinspre faleză</li>
        </ul>
        <p>
          Transportul public va fi deviat pe rutele ocolitoare, iar liniile 3 și 14 vor circula
          la interval dublu. Riverani vor putea ieși din zonă pe la punctele semnalizate.
        </p>

        <blockquote>
          <p>
            Ne-am dorit de mult să aducem cursa înapoi în centru. Atmosfera de pe strada veche,
            cu oamenii la ferestre, nu se compară cu nimic.
          </p>
          <cite>— Organizatorii cursei</cite>
        </blockquote>

        <h2>Puncte de hidratare și asistență medicală</h2>
        <p>
          Sunt prevăzute șase puncte de hidratare, la fiecare cinci kilometri, plus două corturi
          medicale — unul la start și unul la kilometrul 21. Voluntarii vor purta veste
          portocalii și pot fi recunoscuți ușor pe tot traseul.
        </p>

        <h3>Programul pe ore</h3>
        <ol>
          <li><strong>07:00</strong> — deschiderea zonei de start, ridicarea kiturilor</li>
          <li><strong>08:00</strong> — startul cursei de maraton</li>
          <li><strong>09:30</strong> — startul cursei de 10 km</li>
          <li><strong>11:00</strong> — cursa copiilor, 1 km</li>
          <li><strong>13:00</strong> — festivitatea de premiere</li>
        </ol>

        <p>
          Dacă vii cu mașina, cele mai apropiate parcări rămase deschise sunt cea de la
          complexul sportiv și cea subterană din spatele primăriei. Recomandarea organizatorilor
          este însă transportul public sau bicicleta — vor fi rasteluri suplimentare lângă start.
        </p>

        <!-- etichete -->
        <div class="post__tags">
          <a class="tag" href="#">#maraton</a>
          <a class="tag" href="#">#sport</a>
          <a class="tag" href="#">#evenimente</a>
          <a class="tag" href="#">#centruvechi</a>
        </div>
      </div>

      <!-- =========================== PARTICIPARE ========================== -->
      <!--
        Butoanele au data-count = numărul din baza de date. Cât timp
        body[], click-ul trimite spre login.php.
      -->
      <section class="rsvp" id="rsvp" aria-labelledby="rsvp-title">
        <div class="rsvp__head">
          <h2 id="rsvp-title">Mergi la acest eveniment?</h2>
          <p>Spune-le și celorlalți — apari în lista de mai jos.</p>
        </div>

        <div class="rsvp__actions">
          <button class="rsvp__btn rsvp__btn--interested" type="button"
                  id="btn-interested" data-rsvp="interested" data-count="128" aria-pressed="false">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path d="m12 3.8 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 10l5.9-.9L12 3.8Z"/>
            </svg>
            <span class="rsvp__label">Mă interesează</span>
            <span class="rsvp__count" data-count-for="interested">128</span>
          </button>

          <button class="rsvp__btn rsvp__btn--going" type="button"
                  id="btn-going" data-rsvp="going" data-count="86" aria-pressed="false">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
            </svg>
            <span class="rsvp__label">Voi participa</span>
            <span class="rsvp__count" data-count-for="going">86</span>
          </button>
        </div>

        <div class="rsvp__people">
          <div class="facepile" aria-hidden="true">
            <img src="assets/img/avatars/ioana.svg" alt="" width="96" height="96">
            <img src="assets/img/avatars/vlad.svg" alt="" width="96" height="96">
            <img src="assets/img/avatars/diana.svg" alt="" width="96" height="96">
            <img src="assets/img/avatars/mihai.svg" alt="" width="96" height="96">
            <img src="assets/img/avatars/raluca.svg" alt="" width="96" height="96">
          </div>
          <p class="rsvp__note">
            <strong>Ioana</strong>, <strong>Vlad</strong> și încă 84 de persoane vor participa.
          </p>
        </div>
      </section>

      <!-- ====================== TABURI: DISCUȚII ========================== -->
      <section class="tabs-section" aria-labelledby="tabs-title">
        <h2 class="sr-only" id="tabs-title">Discuții și participanți</h2>

        <div class="tabs" role="tablist" data-tabs aria-label="Comentarii și participanți">
          <button class="tab is-active" type="button" role="tab" id="tab-comments"
                  aria-controls="panel-comments" aria-selected="true" tabindex="0">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M20.5 12.5c0 4-3.8 7-8.5 7-1 0-2-.1-2.9-.4L4 21l1.3-3.4A7.4 7.4 0 0 1 3.5 12.5c0-4 3.8-7 8.5-7s8.5 3 8.5 7Z"/>
            </svg>
            <span>Comentarii</span>
            <span class="tab__count">7</span>
          </button>

          <button class="tab" type="button" role="tab" id="tab-interested"
                  aria-controls="panel-interested" aria-selected="false" tabindex="-1">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path d="m12 3.8 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 10l5.9-.9L12 3.8Z"/>
            </svg>
            <span>Interesați</span>
            <span class="tab__count" data-count-for="interested">128</span>
          </button>

          <button class="tab" type="button" role="tab" id="tab-going"
                  aria-controls="panel-going" aria-selected="false" tabindex="-1">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
            </svg>
            <span>Participă</span>
            <span class="tab__count" data-count-for="going">86</span>
          </button>
        </div>

        <!-- ------------------------ PANOU: COMENTARII --------------------- -->
        <div class="panel is-active" id="panel-comments" role="tabpanel" aria-labelledby="tab-comments" tabindex="0">

          <!-- formular comentariu nou -->
          <form class="comment-form" data-comment-form>
            <img class="comment-form__avatar" src="assets/img/avatars/cristi.svg" alt="" width="96" height="96">
            <div class="comment-form__main">
              <label class="sr-only" for="new-comment">Scrie un comentariu</label>
              <textarea id="new-comment" rows="3" placeholder="Scrie un comentariu…"></textarea>
              <div class="comment-form__actions">
                <p class="comment-form__hint">Fii civilizat. Comentariile jignitoare se șterg.</p>
                <button class="btn btn--primary btn--sm" type="submit">Publică</button>
              </div>
            </div>
          </form>

          <!-- lista de comentarii; sub-comentariile stau în .comment__replies -->
          <ul class="comments">

            <li class="comment">
              <article class="comment__body">
                <img class="comment__avatar" src="assets/img/avatars/ioana.svg" alt="" width="96" height="96" loading="lazy">
                <div class="comment__main">
                  <div class="comment__head">
                    <a class="comment__author" href="profil.php">Ioana Rusu</a>
                    <span class="badge">Organizator</span>
                    <span class="dot" aria-hidden="true"></span>
                    <time datetime="2026-08-04T10:12">acum 6 ore</time>
                  </div>
                  <p>
                    Foarte bună decizia de a reveni în centrul vechi. Anul trecut traseul de pe
                    faleză era frumos, dar prea puțin public. Sper să fie și anul ăsta muzică la
                    kilometrul 15!
                  </p>
                  <div class="comment__tools">
                    <button class="comment__tool" type="button" data-like aria-pressed="false">
                      <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M7 20V9.5l4.2-6a1.6 1.6 0 0 1 2.9 1.2L13.3 9H19a2 2 0 0 1 2 2.4l-1.4 6.4A2.6 2.6 0 0 1 17 20Z"/>
                        <path d="M7 9.8H4.2A1.2 1.2 0 0 0 3 11v7.8c0 .7.5 1.2 1.2 1.2H7"/>
                      </svg>
                      <span data-like-count>12</span>
                    </button>
                    <button class="comment__tool" type="button" data-reply>Răspunde</button>
                  </div>

                  <ul class="comment__replies">
                    <li class="comment">
                      <article class="comment__body">
                        <img class="comment__avatar" src="assets/img/avatars/andrei.svg" alt="" width="96" height="96" loading="lazy">
                        <div class="comment__main">
                          <div class="comment__head">
                            <a class="comment__author" href="profil.php">Andrei Munteanu</a>
                            <span class="badge badge--author">Autor</span>
                            <span class="dot" aria-hidden="true"></span>
                            <time datetime="2026-08-04T11:03">acum 5 ore</time>
                          </div>
                          <p>Confirmat, la kilometrul 15 va fi din nou scena cu fanfara. Am întrebat organizatorii ieri.</p>
                          <div class="comment__tools">
                            <button class="comment__tool" type="button" data-like aria-pressed="false">
                              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M7 20V9.5l4.2-6a1.6 1.6 0 0 1 2.9 1.2L13.3 9H19a2 2 0 0 1 2 2.4l-1.4 6.4A2.6 2.6 0 0 1 17 20Z"/>
                                <path d="M7 9.8H4.2A1.2 1.2 0 0 0 3 11v7.8c0 .7.5 1.2 1.2 1.2H7"/>
                              </svg>
                              <span data-like-count>8</span>
                            </button>
                            <button class="comment__tool" type="button" data-reply>Răspunde</button>
                          </div>
                        </div>
                      </article>
                    </li>

                    <li class="comment">
                      <article class="comment__body">
                        <img class="comment__avatar" src="assets/img/avatars/elena.svg" alt="" width="96" height="96" loading="lazy">
                        <div class="comment__main">
                          <div class="comment__head">
                            <a class="comment__author" href="profil.php">Elena Neagu</a>
                            <span class="dot" aria-hidden="true"></span>
                            <time datetime="2026-08-04T12:40">acum 3 ore</time>
                          </div>
                          <p>Super, atunci venim și noi să încurajăm. Se poate ajunge cu căruciorul până la scenă?</p>
                          <div class="comment__tools">
                            <button class="comment__tool" type="button" data-like aria-pressed="false">
                              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M7 20V9.5l4.2-6a1.6 1.6 0 0 1 2.9 1.2L13.3 9H19a2 2 0 0 1 2 2.4l-1.4 6.4A2.6 2.6 0 0 1 17 20Z"/>
                                <path d="M7 9.8H4.2A1.2 1.2 0 0 0 3 11v7.8c0 .7.5 1.2 1.2 1.2H7"/>
                              </svg>
                              <span data-like-count>3</span>
                            </button>
                            <button class="comment__tool" type="button" data-reply>Răspunde</button>
                          </div>
                        </div>
                      </article>
                    </li>
                  </ul>
                </div>
              </article>
            </li>

            <li class="comment">
              <article class="comment__body">
                <img class="comment__avatar" src="assets/img/avatars/mihai.svg" alt="" width="96" height="96" loading="lazy">
                <div class="comment__main">
                  <div class="comment__head">
                    <a class="comment__author" href="profil.php">Mihai Constantin</a>
                    <span class="dot" aria-hidden="true"></span>
                    <time datetime="2026-08-04T09:20">acum 7 ore</time>
                  </div>
                  <p>
                    Cineva știe dacă se mai pot face înscrieri la cursa de 21 km? La 10 km s-au
                    închis, dar pe site apare încă butonul activ la semimaraton.
                  </p>
                  <div class="comment__tools">
                    <button class="comment__tool" type="button" data-like aria-pressed="false">
                      <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M7 20V9.5l4.2-6a1.6 1.6 0 0 1 2.9 1.2L13.3 9H19a2 2 0 0 1 2 2.4l-1.4 6.4A2.6 2.6 0 0 1 17 20Z"/>
                        <path d="M7 9.8H4.2A1.2 1.2 0 0 0 3 11v7.8c0 .7.5 1.2 1.2 1.2H7"/>
                      </svg>
                      <span data-like-count>5</span>
                    </button>
                    <button class="comment__tool" type="button" data-reply>Răspunde</button>
                  </div>

                  <ul class="comment__replies">
                    <li class="comment">
                      <article class="comment__body">
                        <img class="comment__avatar" src="assets/img/avatars/raluca.svg" alt="" width="96" height="96" loading="lazy">
                        <div class="comment__main">
                          <div class="comment__head">
                            <a class="comment__author" href="profil.php">Raluca Grigore</a>
                            <span class="dot" aria-hidden="true"></span>
                            <time datetime="2026-08-04T09:55">acum 6 ore</time>
                          </div>
                          <p>Da, mai sunt locuri. Am prins unul aseară, dar cred că se închid în weekend.</p>
                          <div class="comment__tools">
                            <button class="comment__tool" type="button" data-like aria-pressed="false">
                              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M7 20V9.5l4.2-6a1.6 1.6 0 0 1 2.9 1.2L13.3 9H19a2 2 0 0 1 2 2.4l-1.4 6.4A2.6 2.6 0 0 1 17 20Z"/>
                                <path d="M7 9.8H4.2A1.2 1.2 0 0 0 3 11v7.8c0 .7.5 1.2 1.2 1.2H7"/>
                              </svg>
                              <span data-like-count>4</span>
                            </button>
                            <button class="comment__tool" type="button" data-reply>Răspunde</button>
                          </div>
                        </div>
                      </article>
                    </li>
                  </ul>
                </div>
              </article>
            </li>

            <li class="comment">
              <article class="comment__body">
                <img class="comment__avatar" src="assets/img/avatars/vlad.svg" alt="" width="96" height="96" loading="lazy">
                <div class="comment__main">
                  <div class="comment__head">
                    <a class="comment__author" href="profil.php">Vlad Solomon</a>
                    <span class="dot" aria-hidden="true"></span>
                    <time datetime="2026-08-03T18:05">ieri</time>
                  </div>
                  <p>
                    Ar fi utilă o hartă a traseului direct în articol. Pentru cei care stau pe
                    Strada Veche, contează mult să știe pe unde pot ieși cu mașina dimineața.
                  </p>
                  <div class="comment__tools">
                    <button class="comment__tool" type="button" data-like aria-pressed="false">
                      <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M7 20V9.5l4.2-6a1.6 1.6 0 0 1 2.9 1.2L13.3 9H19a2 2 0 0 1 2 2.4l-1.4 6.4A2.6 2.6 0 0 1 17 20Z"/>
                        <path d="M7 9.8H4.2A1.2 1.2 0 0 0 3 11v7.8c0 .7.5 1.2 1.2 1.2H7"/>
                      </svg>
                      <span data-like-count>9</span>
                    </button>
                    <button class="comment__tool" type="button" data-reply>Răspunde</button>
                  </div>
                </div>
              </article>
            </li>

          </ul>

          <div class="load-more">
            <button class="btn btn--ghost" type="button">Vezi mai multe comentarii</button>
          </div>
        </div>

        <!-- ------------------------ PANOU: INTERESAȚI --------------------- -->
        <div class="panel" id="panel-interested" role="tabpanel" aria-labelledby="tab-interested" tabindex="0" hidden>
          <p class="panel__intro">
            <strong><span data-count-for="interested">128</span> persoane</strong> sunt interesate de acest eveniment.
          </p>

          <ul class="people">
            <li class="person">
              <img class="person__avatar" src="assets/img/avatars/diana.svg" alt="" width="96" height="96" loading="lazy">
              <div class="person__info">
                <a class="person__name" href="profil.php">Diana Popa</a>
                <span class="person__meta">Interesată acum 2 ore</span>
              </div>
            </li>
            <li class="person">
              <img class="person__avatar" src="assets/img/avatars/george.svg" alt="" width="96" height="96" loading="lazy">
              <div class="person__info">
                <a class="person__name" href="profil.php">George Tudose</a>
                <span class="person__meta">Interesat acum 4 ore</span>
              </div>
            </li>
            <li class="person">
              <img class="person__avatar" src="assets/img/avatars/simona.svg" alt="" width="96" height="96" loading="lazy">
              <div class="person__info">
                <a class="person__name" href="profil.php">Simona Dobre</a>
                <span class="person__meta">Interesată ieri</span>
              </div>
            </li>
            <li class="person">
              <img class="person__avatar" src="assets/img/avatars/tudor.svg" alt="" width="96" height="96" loading="lazy">
              <div class="person__info">
                <a class="person__name" href="profil.php">Tudor Anghel</a>
                <span class="person__meta">Interesat ieri</span>
              </div>
            </li>
            <li class="person">
              <img class="person__avatar" src="assets/img/avatars/bianca.svg" alt="" width="96" height="96" loading="lazy">
              <div class="person__info">
                <a class="person__name" href="profil.php">Bianca Filip</a>
                <span class="person__meta">Interesată acum 2 zile</span>
              </div>
            </li>
            <li class="person">
              <img class="person__avatar" src="assets/img/avatars/cristi.svg" alt="" width="96" height="96" loading="lazy">
              <div class="person__info">
                <a class="person__name" href="profil.php">Cristi Barbu</a>
                <span class="person__meta">Interesat acum 2 zile</span>
              </div>
            </li>
          </ul>

          <div class="load-more">
            <button class="btn btn--ghost" type="button">Vezi toate cele 128 de persoane</button>
          </div>
        </div>

        <!-- ------------------------ PANOU: PARTICIPĂ ---------------------- -->
        <div class="panel" id="panel-going" role="tabpanel" aria-labelledby="tab-going" tabindex="0" hidden>
          <p class="panel__intro">
            <strong><span data-count-for="going">86</span> persoane</strong> au confirmat că vor participa.
          </p>

          <ul class="people">
            <li class="person">
              <img class="person__avatar" src="assets/img/avatars/ioana.svg" alt="" width="96" height="96" loading="lazy">
              <div class="person__info">
                <a class="person__name" href="profil.php">Ioana Rusu</a>
                <span class="person__meta">Aleargă la 10 km</span>
              </div>
              <span class="person__badge">Organizator</span>
            </li>
            <li class="person">
              <img class="person__avatar" src="assets/img/avatars/vlad.svg" alt="" width="96" height="96" loading="lazy">
              <div class="person__info">
                <a class="person__name" href="profil.php">Vlad Solomon</a>
                <span class="person__meta">Confirmat acum 3 ore</span>
              </div>
            </li>
            <li class="person">
              <img class="person__avatar" src="assets/img/avatars/mihai.svg" alt="" width="96" height="96" loading="lazy">
              <div class="person__info">
                <a class="person__name" href="profil.php">Mihai Constantin</a>
                <span class="person__meta">Confirmat acum 5 ore</span>
              </div>
            </li>
            <li class="person">
              <img class="person__avatar" src="assets/img/avatars/raluca.svg" alt="" width="96" height="96" loading="lazy">
              <div class="person__info">
                <a class="person__name" href="profil.php">Raluca Grigore</a>
                <span class="person__meta">Confirmat ieri</span>
              </div>
            </li>
            <li class="person">
              <img class="person__avatar" src="assets/img/avatars/elena.svg" alt="" width="96" height="96" loading="lazy">
              <div class="person__info">
                <a class="person__name" href="profil.php">Elena Neagu</a>
                <span class="person__meta">Confirmat ieri</span>
              </div>
            </li>
            <li class="person">
              <img class="person__avatar" src="assets/img/avatars/andrei.svg" alt="" width="96" height="96" loading="lazy">
              <div class="person__info">
                <a class="person__name" href="profil.php">Andrei Munteanu</a>
                <span class="person__meta">Confirmat acum 2 zile</span>
              </div>
              <span class="person__badge">Autor</span>
            </li>
          </ul>

          <div class="load-more">
            <button class="btn btn--ghost" type="button">Vezi toate cele 86 de persoane</button>
          </div>
        </div>

      </section>
    </article>

    <!-- ========================= ARTICOLE SIMILARE ======================== -->
    <section class="related" aria-labelledby="related-title">
      <div class="section-head">
        <h2 class="section-title" id="related-title">Ar putea să te intereseze</h2>
        <a class="link-more" href="index.php">Toate articolele
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h15"/><path d="m13 6 6 6-6 6"/></svg>
        </a>
      </div>

      <div class="grid">
        <article class="card">
          <a class="card__media" href="articol.php">
            <img src="assets/img/posts/post-5.svg" alt="" width="1280" height="720" loading="lazy" decoding="async">
            <span class="card__tag">Sport</span>
          </a>
          <div class="card__body">
            <h3 class="card__title"><a href="articol.php">Prima pistă de ciclism care leagă cele două maluri</a></h3>
            <div class="card__meta">
              <time datetime="2026-07-30">30 iul 2026</time>
              <span class="dot" aria-hidden="true"></span>
              <span>4 min citire</span>
            </div>
          </div>
        </article>

        <article class="card">
          <a class="card__media" href="articol.php">
            <img src="assets/img/posts/post-2.svg" alt="" width="1280" height="720" loading="lazy" decoding="async">
            <span class="card__tag">Cultură</span>
          </a>
          <div class="card__body">
            <h3 class="card__title"><a href="articol.php">Trei zile de festival în parcul central, cu intrare liberă</a></h3>
            <div class="card__meta">
              <time datetime="2026-08-03">3 aug 2026</time>
              <span class="dot" aria-hidden="true"></span>
              <span>3 min citire</span>
            </div>
          </div>
        </article>
      </div>
    </section>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
