<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/imagini.php';
require_once __DIR__ . '/inc/evenimente.php';

/**
 * Al cui e profilul.
 *
 * Fără „?m=", al celui conectat. Cu „?m=<permalink>", al membrului cerut —
 * adresa publică a cuiva, cea de zece caractere. Când vor exista adrese
 * frumoase de forma /membru/<permalink>, tot aici se va ajunge.
 *
 * Un permalink care nu duce nicăieri (cont șters, greșeală de tastare) nu e o
 * eroare de arătat: pagina trimite omul înapoi pe prima pagină.
 */
$eu     = membruCurent();
$cerut  = trim((string) ($_GET['m'] ?? ''));
$membruProfil = $eu;

if ($cerut !== '') {
    $membruProfil = membruDupaPermalink($cerut);

    if ($membruProfil === null) {
        header('Location: index.php');
        exit;
    }
}

$eProfilulMeu = $eu !== null && $membruProfil !== null
    && (int) $eu['id'] === (int) $membruProfil['id'];

// Datele scurte din antet vin din bază atunci când avem pe cine arăta.
// Restul paginii — evaluările — e încă exemplu de așezare.
$p = $membruProfil;

$numeProfil = $p ? numeAfisat($p['nume'], $p['prenume']) : 'P. Ionuț';
$pozaProfil = $p['poza'] ?? null;
$varsta     = $p ? varstaDin($p['data_nasterii'] ?? null) : 34;
$localitate = $p ? ($p['localitate'] ?? null) : 'Brașov';
$sex        = $p['sex'] ?? 'M';
$membruDin  = $p ? lunaSiAnul($p['creat_la'] ?? null) : 'mai 2024';

/**
 * Doar primul prenume, pentru „Ana nu organizează momentan nimic".
 *
 * Cine are două prenume („Ana Maria") e strigat pe primul, ca între oameni.
 */
$prenumeScurt = $p ? explode(' ', trim((string) $p['prenume']))[0] : 'Ionuț';

/* ---------------------- Evenimentele organizate ----------------------- */

// Cine nu are cont (și nici nu cere un profil anume) vede pagina-exemplu:
// n-avem al cui profil să citim, deci nici evenimente.
$evenimenteProfil = $p ? evenimenteDePeProfil((int) $p['id'], $eProfilulMeu) : [];

/**
 * Cifra de pe cartonașul „Evenimente organizate".
 *
 * Nu e lungimea listei de mai jos: acolo se văd doar cele care urmează (plus,
 * pentru omul însuși, cele în așteptare), aici se numără tot ce a organizat
 * vreodată și a fost aprobat. Un organizator cu douăzeci de evenimente în
 * urmă și niciunul în față are „20" scris sus și lista goală dedesubt — și e
 * corect așa.
 */
$cateOrganizate = $p ? cateEvenimenteOrganizate((int) $p['id']) : 12;

/** Câte se văd din prima. Restul intră în pagină, dar ascunse. */
const EVENIMENTE_VIZIBILE = 4;

$titlu     = $numeProfil . ' — Profil membru — PulsulOrasului.Ro';
$descriere = 'Profilul membrului ' . $numeProfil . ' pe PulsulOrasului.Ro: evenimente organizate, participări și evaluări primite.';

require __DIR__ . '/inc/antet.php';
?>


<main id="main">
  <div class="wrap">

    <nav class="crumbs" aria-label="Navigare">
      <a href="index.php">Acasă</a>
      <span aria-hidden="true">/</span>
      <a href="#">Membri</a>
      <span aria-hidden="true">/</span>
      <span class="crumbs__current"><?= h($numeProfil) ?></span>
    </nav>

    <!-- ========================= ANTETUL PROFILULUI =====================
      Numele vine deja prescurtat de la server: inițiala numelui de familie,
      punct, prenumele întreg („Popescu Ionuț" → „P. Ionuț"). Numele complet
      nu se trimite deloc în pagină, ca să nu poată fi citit din sursă.
    ================================================================== -->
    <header class="profile">
      <div class="profile__id">

        <!--
          Poza, cu creionul de schimbare peste colțul de jos.
          Creionul se tipărește doar pe profilul propriu: nu e ascuns din CSS,
          ci pur și simplu nu ajunge în pagină pentru ceilalți.
        -->
        <div class="profile__poza">
          <img class="profile__avatar" id="profil-avatar"
               src="<?= h(urlPoza($pozaProfil)) ?>" alt="" width="96" height="96">

          <?php if ($eProfilulMeu): ?>
          <a class="profile__poza-edit" href="poza.php"
             title="Schimbă poza de profil" aria-label="Schimbă poza de profil">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M4 20h4l10-10a2.4 2.4 0 0 0-3.4-3.4L4.6 16.6z"/>
              <path d="m14.2 7.4 2.4 2.4"/>
            </svg>
          </a>
          <?php endif; ?>
        </div>

        <div class="profile__head">
          <h1 class="profile__name"><?= h($numeProfil) ?></h1>

          <ul class="facts">
            <?php if ($varsta !== null): ?>
            <li class="fact">
              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                <rect x="3.5" y="5" width="17" height="16" rx="3"/><path d="M8 3v4M16 3v4M3.5 10h17"/>
              </svg>
              <span><?= h(aniInCuvinte($varsta)) ?></span>
            </li>
            <?php endif; ?>

            <?php if (!empty($localitate)): ?>
            <li class="fact">
              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>
              </svg>
              <span><?= h($localitate) ?></span>
            </li>
            <?php endif; ?>

            <!--
              Sexul: doar simbolul, desenat de noi.
              Masculin → simbolul Marte, feminin → simbolul Venus.
            -->
            <?php if ($sex === 'M'): ?>
            <li class="fact fact--m" title="Masculin">
              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="10" cy="14.2" r="5.8"/>
                <path d="m14.3 10.3 5.4-5.4"/>
                <path d="M14.6 4.6h5.3v5.3"/>
              </svg>
              <span class="sr-only">Masculin</span>
            </li>
            <?php elseif ($sex === 'F'): ?>
            <li class="fact fact--f" title="Feminin">
              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="9.2" r="5.8"/>
                <path d="M12 15v6"/>
                <path d="M9 18.2h6"/>
              </svg>
              <span class="sr-only">Feminin</span>
            </li>
            <?php endif; ?>

            <?php if ($membruDin !== ''): ?>
            <li class="fact fact--muted">
              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.4 2"/>
              </svg>
              <span>Membru din <?= h($membruDin) ?></span>
            </li>
            <?php endif; ?>
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
        <span class="stat__value"><?= (int) $cateOrganizate ?></span>
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

    <!-- ===================== EVENIMENTE ORGANIZATE ======================
      Cartonașele sunt aceleași ca pe prima pagină (.card, .card__media,
      .card__body…), ca să nu existe două feluri de a arăta un eveniment.

      Două lucruri lipsesc, fiindcă nu există încă: pagina publică a unui
      eveniment, deci titlul și poza nu duc nicăieri (sunt <div>, nu <a>), și
      imaginile implicite de categorie, deci evenimentele fără copertă rămân
      cu dreptunghiul gol al cartonașului.
    ================================================================== -->
    <section class="evenimente-profil" aria-labelledby="evenimente-title">

      <div class="section-head">
        <div>
          <!-- Pe profilul propriu vorbim cu omul, nu despre el. -->
          <p class="eyebrow"><span class="pulse-dot" aria-hidden="true"></span>
            <?= $eProfilulMeu ? 'Ce pui la cale' : 'Ce pune la cale' ?></p>
          <h2 class="section-title" id="evenimente-title">Evenimente organizate</h2>
        </div>
      </div>

      <?php if ($evenimenteProfil === []): ?>
      <!-- Niciunul. Al meu → o invitație; al altcuiva → o constatare. -->
      <div class="fara-nimic">
        <?php if ($eProfilulMeu): ?>
        <p class="fara-nimic__text">Nu organizezi nimic, nu vrei să încerci?</p>
        <a class="btn btn--primary btn--sm" href="<?= $logat
              ? 'adauga_eveniment.php'
              : 'login.php?redirect=' . h(urlencode('/adauga_eveniment.php')) ?>">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 5v14"/><path d="M5 12h14"/>
          </svg>
          <span>Eveniment nou</span>
        </a>
        <?php else: ?>
        <p class="fara-nimic__text"><?= h($prenumeScurt) ?> nu organizează momentan nimic.</p>
        <?php endif; ?>
      </div>

      <?php else: ?>
      <div class="grid" id="evenimente-lista">
        <?php foreach ($evenimenteProfil as $i => $ev): ?>
        <?php
          $inAsteptare = ($ev['stare_moderare'] ?? '') === 'in_asteptare';

          // Peste primele patru, cartonașele intră în pagină ascunse. Le arată
          // butonul „Vezi mai mult…", fără să mai ceară nimic de la server.
          $ascuns = $i >= EVENIMENTE_VIZIBILE;

          $clase = 'card';
          if ($inAsteptare) { $clase .= ' card--in-asteptare'; }
          if ($ascuns)      { $clase .= ' ascuns'; }

          $coperta = urlCoperta($ev['coperta'] ?? null);

          // Imaginea implicită a categoriei, când va exista. Coloana e deja în
          // bază, fișierele se urcă de mână — vezi roadmap-ul din CLAUDE.md.
          if ($coperta === '' && !empty($ev['imagine_default'])) {
              $coperta = 'assets/img/categorii/' . $ev['imagine_default'];
          }

          // De acum pagina evenimentului există, deci cartonașul duce undeva.
          $adresa = h(urlEveniment((string) $ev['slug']));
        ?>
        <article class="<?= $clase ?>">
          <a class="card__media" href="<?= $adresa ?>">
            <?php if ($coperta !== ''): ?>
            <img src="<?= h($coperta) ?>" alt="" width="1600" height="900" loading="lazy" decoding="async">
            <?php endif; ?>
            <span class="card__tag"><?= h($ev['categorie']) ?></span>
            <?php if ($inAsteptare): ?>
            <span class="card__stare">În așteptare de aprobare</span>
            <?php endif; ?>
          </a>
          <div class="card__body">
            <h3 class="card__title"><a href="<?= $adresa ?>"><?= h($ev['titlu']) ?></a></h3>
            <p class="card__excerpt"><?= h(inceputDeText((string) $ev['descriere'])) ?></p>
            <div class="card__meta">
              <time datetime="<?= h((string) $ev['data_eveniment']) ?>"><?= h(dataScurta($ev['data_eveniment'])) ?></time>
              <span class="dot" aria-hidden="true"></span>
              <span><?= h(inceputDeText((string) $ev['locatie'], 48)) ?></span>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>

      <?php if (count($evenimenteProfil) > EVENIMENTE_VIZIBILE): ?>
      <!-- Numărul se scrie aici, nu din JS: altfel butonul ar apărea o clipă
           fără el și s-ar corecta singur sub ochii omului. -->
      <div class="vezi-mai-mult">
        <button class="btn btn--ghost" type="button" id="evenimente-mai-mult">
          Vezi mai mult… (<?= count($evenimenteProfil) - EVENIMENTE_VIZIBILE ?>)
        </button>
      </div>
      <?php endif; ?>
      <?php endif; ?>

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
        <img class="comment-form__avatar" src="<?= h(urlPoza($eu['poza'] ?? null, true)) ?>" alt="" width="96" height="96">

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
