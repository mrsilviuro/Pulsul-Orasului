<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — pagina unui eveniment.
 *
 * Adresa: event.php?slug=<slugul-evenimentului>. Slugul, nu id-ul: se poate
 * citi la telefon, spune despre ce e vorba și nu dă în vileag câte evenimente
 * are site-ul.
 */

require_once __DIR__ . '/inc/evenimente.php';
require_once __DIR__ . '/inc/afisare-eveniment.php';

$slug = trim((string) ($_GET['slug'] ?? ''));

/**
 * Fără cont nu se intră deloc — de oriunde ar veni linkul.
 *
 * Verificarea e ÎNAINTEA căutării în bază, dinadins: așa, cine nu e conectat
 * nu poate afla nici măcar dacă un slug duce undeva. Și tot de aici se pleacă
 * spre login cu adresa de acum în buzunar, ca după conectare omul să ajungă
 * fix la evenimentul pe care voia să-l vadă, nu pe prima pagină.
 */
$membru = membruCurent();

if ($membru === null) {
    cereIntrare('/event.php' . ($slug !== '' ? '?slug=' . urlencode($slug) : ''));
}

$eveniment = evenimentDupaSlug($slug);

/**
 * Un slug care nu duce nicăieri și un eveniment pe care n-ai voie să-l vezi
 * sfârșesc la fel: pe prima pagină.
 *
 * Același răspuns pentru amândouă, dinadins. Dacă „nu există" ar arăta altfel
 * decât „nu ai voie", oricine ar putea afla, ghicind sluguri, ce evenimente
 * așteaptă la moderare.
 */
$eStaff = esteStaff($membru);

if ($eveniment === null || !poateVedeaEvenimentul($eveniment, (int) $membru['id'], $eStaff)) {
    header('Location: index.php');
    exit;
}

$eOrganizatorul = (int) $eveniment['membru_id'] === (int) $membru['id'];
$eAprobat       = $eveniment['stare_moderare'] === 'aprobat';
$eAnulat        = $eveniment['stare_moderare'] === 'anulat';

/* --------------------------- ce se afișează --------------------------- */

// Coperta, orele, numele organizatorului — toate se pregătesc în
// evenimentDinBaza(), din inc/afisare-eveniment.php. Aici rămâne doar ce ține
// de pagină: titlul din bara browserului și dacă se lasă indexată.
$titlu     = $eveniment['titlu'] . ' — PulsulOrasului.Ro';
$descriere = inceputDeText((string) $eveniment['descriere'], 155);

// Cât timp nu e aprobat, n-are ce căuta în motoarele de căutare.
$noindex   = !$eAprobat;

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
      <span><?= h($eveniment['categorie']) ?></span>
      <span aria-hidden="true">/</span>
      <span class="crumbs__current"><?= h(inceputDeText($eveniment['titlu'], 60)) ?></span>
    </nav>

    <article class="post">

      <?php
        /**
         * Tot ce se vede din datele evenimentului — antet, copertă, caseta cu
         * detalii, descrierea — se desenează în inc/afisare-eveniment.php.
         *
         * Acolo, și nu aici, fiindcă aceeași bucată desenează și
         * previzualizarea din formular. Dacă ar fi scrisă în două locuri,
         * previzualizarea ar rămâne în urmă la prima schimbare, și tocmai ea
         * trebuie să arate exact ca pagina adevărată.
         *
         * Ce ține de pagina asta se dă din afară: banda cu starea anunțului
         * (o vede doar organizatorul, ceilalți nu deschid pagina deloc) și
         * butonul „Editează".
         */
        $banda = null;

        if ($eAnulat) {
            /**
             * Anulat: pagina se deschide doar pentru staff, deci banda e
             * pentru ei. Motivul merge alături — e textul organizatorului, cel
             * care va pleca și spre oamenii înscriși, deci trebuie citit
             * întocmai, nu rezumat de noi.
             */
            $banda = [
                'fel'   => 'anulat',
                'text'  => 'Anulat de organizator. Anunțul nu se mai vede pe site; pagina asta o deschide doar staff-ul.',
                'motiv' => (string) ($eveniment['motiv_anulare'] ?? ''),
            ];
        } elseif (!$eAprobat) {
            $banda = $eveniment['stare_moderare'] === 'respins'
                ? ['fel' => 'respins',   'text' => 'Anunțul nu a trecut de verificare. Îl vezi doar tu.']
                : ['fel' => 'asteptare', 'text' => 'În așteptare de aprobare. Îl vezi doar tu, până îl citim.'];
        }

        // Butonul „Editează" dispare la anulare: nu mai e nimic de corectat, iar
        // evenimentDeEditat() oricum nu l-ar mai deschide.
        afiseazaEveniment(evenimentDinBaza($eveniment), $banda, ($eOrganizatorul && !$eAnulat) ? function () use ($eveniment) {
            ?>
            <!-- Doar pentru cel care l-a scris. Slugul spune formularului ce
                 eveniment să încarce; acolo se verifică din nou al cui e. -->
            <a class="btn btn--ghost btn--sm post__editeaza"
               href="<?= h(urlEditareEveniment((string) $eveniment['slug'])) ?>">
              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 20h4l10-10a2.4 2.4 0 0 0-3.4-3.4L4.6 16.6z"/>
                <path d="m14.2 7.4 2.4 2.4"/>
              </svg>
              <span>Editează</span>
            </a>
            <?php
        } : null);
      ?>

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
          <a class="card__media" href="event.php">
            <img src="assets/img/posts/post-5.svg" alt="" width="1280" height="720" loading="lazy" decoding="async">
            <span class="card__tag">Sport</span>
          </a>
          <div class="card__body">
            <h3 class="card__title"><a href="event.php">Prima pistă de ciclism care leagă cele două maluri</a></h3>
            <div class="card__meta">
              <time datetime="2026-07-30">30 iul 2026</time>
              <span class="dot" aria-hidden="true"></span>
              <span>4 min citire</span>
            </div>
          </div>
        </article>

        <article class="card">
          <a class="card__media" href="event.php">
            <img src="assets/img/posts/post-2.svg" alt="" width="1280" height="720" loading="lazy" decoding="async">
            <span class="card__tag">Cultură</span>
          </a>
          <div class="card__body">
            <h3 class="card__title"><a href="event.php">Trei zile de festival în parcul central, cu intrare liberă</a></h3>
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
