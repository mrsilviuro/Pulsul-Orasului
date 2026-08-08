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
if ($eveniment === null || !poateVedeaEvenimentul($eveniment, (int) $membru['id'])) {
    header('Location: index.php');
    exit;
}

$eOrganizatorul = (int) $eveniment['membru_id'] === (int) $membru['id'];
$eAprobat       = $eveniment['stare_moderare'] === 'aprobat';

/* --------------------------- ce se afișează --------------------------- */

$coperta = urlCoperta($eveniment['coperta'] ?? null);

// Imaginea implicită a categoriei, când va exista. Coloana e deja în bază,
// fișierele se urcă de mână — vezi roadmap-ul din CLAUDE.md.
if ($coperta === '' && !empty($eveniment['imagine_default'])) {
    $coperta = 'assets/img/categorii/' . $eveniment['imagine_default'];
}

$oraInceput = oraScurta($eveniment['ora_inceput']);
$oraSfarsit = oraScurta($eveniment['ora_sfarsit'] ?? null);

$organizator = numeAfisat($eveniment['org_nume'], $eveniment['org_prenume']);

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

      <!-- ======================= ANTETUL EVENIMENTULUI ===================== -->
      <header class="post__head">
        <span class="post__cat"><?= h($eveniment['categorie']) ?></span>
        <h1 class="post__title"><?= h($eveniment['titlu']) ?></h1>

        <?php if (!$eAprobat): ?>
        <!--
          Aici ajunge doar organizatorul: pentru oricine altcineva, un eveniment
          neaprobat nu se deschide deloc. Îi spunem pe față unde stă anunțul,
          cu aceeași etichetă galbenă de pe cartonașele din profil.
        -->
        <p class="stare-anunt stare-anunt--<?= $eveniment['stare_moderare'] === 'respins' ? 'respins' : 'asteptare' ?>">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.4 2"/>
          </svg>
          <?php if ($eveniment['stare_moderare'] === 'respins'): ?>
          <span>Anunțul nu a trecut de verificare. Îl vezi doar tu.</span>
          <?php else: ?>
          <span>În așteptare de aprobare. Îl vezi doar tu, până îl citim.</span>
          <?php endif; ?>
        </p>
        <?php endif; ?>

        <div class="post__meta">
          <!-- Poza organizatorului; cine n-are, arată silueta implicită. -->
          <img class="post__avatar" src="<?= h(urlPoza($eveniment['org_poza'] ?? null, true)) ?>"
               alt="" width="96" height="96">
          <div class="post__by">
            <a class="post__author" href="profil.php?m=<?= h(urlencode((string) $eveniment['org_permalink'])) ?>"><?= h($organizator) ?></a>
            <div class="post__sub">
              <span>Organizator</span>
              <span class="dot" aria-hidden="true"></span>
              <time datetime="<?= h((string) $eveniment['creat_la']) ?>">publicat <?= h(dataScurta($eveniment['creat_la'])) ?></time>
            </div>
          </div>

          <?php if ($eOrganizatorul): ?>
          <!-- Doar pentru cel care l-a scris. Formularul învață să și editeze
               separat; deocamdată doar linkul. -->
          <a class="btn btn--ghost btn--sm post__editeaza" href="adauga_eveniment.php">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M4 20h4l10-10a2.4 2.4 0 0 0-3.4-3.4L4.6 16.6z"/>
              <path d="m14.2 7.4 2.4 2.4"/>
            </svg>
            <span>Editează</span>
          </a>
          <?php endif; ?>
        </div>
      </header>

      <?php if ($coperta !== ''): ?>
      <!-- ======================= COPERTA 16:9 ============================= -->
      <!-- Fără figcaption: n-avem de unde ști ce e în poză, iar o legendă
           inventată e mai rea decât niciuna. -->
      <figure class="post__figure">
        <img src="<?= h($coperta) ?>" alt=""
             width="1600" height="900" fetchpriority="high" decoding="async">
      </figure>
      <?php endif; ?>

      <!-- ==================== DETALIILE EVENIMENTULUI =====================
        Ce lipsește nu se arată gol. Un rând „Vârstă minimă: —" nu spune
        nimic, dar ocupă locul unuia care ar fi spus.
      ================================================================== -->
      <section class="event-box" aria-label="Detaliile evenimentului">
        <div class="event-box__item">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <rect x="3.5" y="5" width="17" height="16" rx="3"/><path d="M8 3v4M16 3v4M3.5 10h17"/>
          </svg>
          <div><span>Data</span><strong><?= h(dataLunga($eveniment['data_eveniment'])) ?></strong></div>
        </div>

        <div class="event-box__item">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.4 2"/>
          </svg>
          <div><span>Ora</span><strong><?php
            // Ora de început e mereu știută; sfârșitul poate lipsi. Când
            // lipsește, nu se spune nimic despre el: „19:00", atât. O mențiune
            // de genul „nedeterminat" ocupă un rând ca să nu spună nimic.
            echo h($oraSfarsit !== '' ? $oraInceput . ' — ' . $oraSfarsit : $oraInceput);
          ?></strong></div>
        </div>

        <div class="event-box__item">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>
          </svg>
          <div><span>Locul</span><strong><?= h($eveniment['locatie']) ?></strong></div>
        </div>

        <div class="event-box__item">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <rect x="2.5" y="6" width="19" height="12" rx="3"/><circle cx="12" cy="12" r="2.6"/>
          </svg>
          <div><span>Acces</span><strong><?= h(costScris($eveniment['cost'])) ?></strong></div>
        </div>

        <?php if ($eveniment['varsta_minima'] !== null): ?>
        <div class="event-box__item">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="8.2" r="3.6"/><path d="M5 20c0-3.9 3.1-6.4 7-6.4s7 2.5 7 6.4"/>
          </svg>
          <div><span>Vârstă minimă</span><strong><?= (int) $eveniment['varsta_minima'] ?> ani</strong></div>
        </div>
        <?php endif; ?>

        <?php if ($eveniment['gen_participanti'] !== 'nespecificat'): ?>
        <div class="event-box__item">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="9" cy="8.5" r="3.4"/><path d="M3 20c0-3.4 2.7-5.7 6-5.7s6 2.3 6 5.7"/>
            <path d="M17 8.5h4M19 6.5v4"/>
          </svg>
          <div><span>Pentru cine</span><strong><?=
            $eveniment['gen_participanti'] === 'barbati' ? 'Doar bărbați' : 'Doar femei'
          ?></strong></div>
        </div>
        <?php endif; ?>

        <?php
          // Cele două numere stau într-un singur rând: „minim 10" și „cel mult
          // 50" sunt aceeași informație, câți oameni încap.
          $participanti = [];
          if ($eveniment['participanti_min'] !== null) {
              $participanti[] = 'minimum ' . (int) $eveniment['participanti_min'];
          }
          if ($eveniment['participanti_max'] !== null) {
              $participanti[] = 'cel mult ' . (int) $eveniment['participanti_max'];
          }
        ?>
        <?php if ($participanti !== []): ?>
        <div class="event-box__item">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="9" cy="8.5" r="3.4"/><path d="M3 20c0-3.4 2.7-5.7 6-5.7s6 2.3 6 5.7"/>
            <path d="M16.5 5.6a3.4 3.4 0 0 1 0 5.8"/><path d="M18 14.6c2 .8 3 2.6 3 5.4"/>
          </svg>
          <div><span>Participanți</span><strong><?= h(implode(', ', $participanti)) ?></strong></div>
        </div>
        <?php endif; ?>
      </section>

      <!-- ======================== CORPUL ARTICOLULUI ====================== -->
      <!-- ======================== DESCRIEREA ============================
        Textul e în bază exact cum l-a scris omul, neescapat. Escaparea se
        face aici, la randare, cu h() — invers ar fi însemnat „&amp;amp;" la
        a doua editare și un text pe care nu-l mai poți căuta.

        Se escapează ÎNTÂI, se pun etichetele DUPĂ: altfel <p> și <br> ar fi
        escapate și ele, iar omul ar vedea codul în loc de paragrafe.
      ================================================================== -->
      <div class="post__body">
        <?php
          $paragrafe = preg_split('/\n{2,}/', (string) $eveniment['descriere']) ?: [];

          foreach ($paragrafe as $paragraf) {
              $paragraf = trim($paragraf);

              if ($paragraf === '') {
                  continue;
              }

              // Rândurile simple dinăuntrul unui paragraf rămân rânduri.
              echo '<p>', nl2br(h($paragraf), false), '</p>', "\n";
          }
        ?>
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
