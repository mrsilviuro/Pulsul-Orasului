<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/imagini.php';
require_once __DIR__ . '/inc/evenimente.php';
require_once __DIR__ . '/inc/evaluari.php';

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

/**
 * Profilurile sunt pentru cei din casă — de oriunde ar veni linkul.
 *
 * Un profil spune vârsta, orașul, chipul și ce pune omul la cale. Nu e ceva
 * ce se lasă la vedere pe internet, unde poate fi cules de oricine. Cine nu e
 * conectat pleacă spre login cu adresa asta în buzunar, ca după conectare să
 * ajungă fix pe profilul pe care voia să-l vadă.
 *
 * Aceeași mișcare ca la event.php, cu aceeași funcție: verificarea e ÎNAINTEA
 * căutării în bază, deci un permalink nu se poate încerca din afară nici măcar
 * ca să se afle dacă duce undeva. Linkurile spre profiluri rămân peste tot
 * cum erau — se schimbă doar cine poate deschide pagina.
 */
if ($eu === null) {
    cereIntrare('/profil.php' . ($cerut !== '' ? '?m=' . urlencode($cerut) : ''));
}

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

/* --------------------------- Evaluările ------------------------------- */

/**
 * Notele primite: media, distribuția, și lista întreagă.
 *
 * Erau până acum scrise de mână în pagină — 4,6 din 23, cu o distribuție
 * inventată și un formular care nu trimitea nimic nicăieri. Acum vin din bază.
 */
$rezumatProfil  = $p ? rezumatEvaluari((int) $p['id']) : ['medie' => 0.0, 'cate' => 0, 'distributie' => []];
$evaluariProfil = $p ? evaluarilePrimite((int) $p['id']) : [];

/**
 * Poate omul care se uită să dea o notă aici, chiar acum?
 *
 * Numai dacă a venit de pe pagina unui eveniment încheiat la care au fost
 * amândoi — adică numai cu „?ev=<slug>" în adresă. Nota nu se dă de pe un
 * profil găsit la întâmplare: greutatea ei vine tocmai din seara petrecută
 * împreună.
 *
 * `?stele=` e nota apăsată acolo, pe pagina evenimentului. NU se salvează din
 * ea nimic — o adresă venită din afară n-are voie să scrie în bază — ci doar se
 * aprind stelele în casetă. Ce s-a salvat cu adevărat se citește din
 * evaluareaMea(), iar aceea are ultimul cuvânt.
 */
$slugEvaluare  = trim((string) ($_GET['ev'] ?? ''));
$titluEvaluare = '';
$potEvalua     = false;
$stelePuse     = 0;
$textPus       = '';

if ($p !== null && !$eProfilulMeu && $slugEvaluare !== '') {
    $evenimentEvaluare = evenimentDupaSlug($slugEvaluare);

    if ($evenimentEvaluare !== null
        && motivBlocajEvaluare($evenimentEvaluare, (int) $eu['id'], (int) $p['id']) === '') {

        $potEvalua     = true;
        $titluEvaluare = (string) $evenimentEvaluare['titlu'];

        $deja = evaluareaMea((int) $evenimentEvaluare['id'], (int) $eu['id'], (int) $p['id']);

        if ($deja !== null) {
            $stelePuse = (int) $deja['stele'];
            $textPus   = (string) ($deja['text'] ?? '');
        }

        // Stelele apăsate pe pagina evenimentului, dacă n-a apucat să se
        // salveze nimic încă. Se trec prin aceeași verificare ca oriunde.
        if ($stelePuse === 0) {
            $stelePuse = stelePrimite($_GET['stele'] ?? 0);
        }
    }
}

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
          <span class="rating__value" data-stars-value>—</span>
          <span class="rating__max">/ 5</span>
        </div>
        <div class="rating__stars" data-stars="<?= h((string) $rezumatProfil['medie']) ?>"
             data-stars-count="<?= (int) $rezumatProfil['cate'] ?>"></div>
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

        <?php if ($eProfilulMeu && $evenimenteProfil !== []): ?>
        <!--
          Pornirea unui eveniment nou stă la îndemână și când omul are deja
          unele: până acum se vedea doar în locul gol, adică exact la cine
          n-avea niciunul.

          Doar pe profilul propriu, și doar când lista are ceva în ea — când
          e goală, invitația de mai jos are deja butonul ei, iar două butoane
          unul sub altul ar spune același lucru de două ori.

          Cine are deja un eveniment în desfășurare ajunge pe pagina care i-o
          spune; de acolo, „Înapoi" îl aduce fix aici.
        -->
        <a class="btn btn--primary btn--sm" href="adauga_eveniment.php">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 5v14"/><path d="M5 12h14"/>
          </svg>
          <span>Eveniment nou</span>
        </a>
        <?php endif; ?>
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

    <!-- =========================== FEEDBACK =============================
      Evaluările primite. ANONIME: se vede nota și textul, niciodată cine
      le-a scris. Altfel nimeni n-ar mai da patru stele cuiva pe care îl
      reîntâlnește sâmbăta viitoare — iar o notă care se semnează e o notă
      frumoasă, adică una care nu spune nimic.

      Toate intră în pagină; ascunsul îl face main.js, ca la comentarii și la
      listele din taburile evenimentului.
    ============================================================== -->
    <section class="feedback" aria-labelledby="feedback-title"
             data-evaluari
             data-deodata="<?= EVALUARI_DEODATA ?>">

      <div class="section-head">
        <div>
          <p class="eyebrow"><span class="pulse-dot" aria-hidden="true"></span> Ce spun ceilalți</p>
          <h2 class="section-title" id="feedback-title">Evaluări</h2>
        </div>
      </div>

      <!-- Media, câte sunt, barele pe stele — toate din randeazaRezumatEvaluari() -->
      <div class="rating-summary" data-rezumat-evaluari>
        <?= randeazaRezumatEvaluari($rezumatProfil) ?>
      </div>

      <?php if ($potEvalua): ?>
      <!--
        Formularul apare DOAR când cineva vine aici de pe pagina unui eveniment
        încheiat la care au fost amândoi — adică cu „?ev=<slug>" în adresă.

        Fără evenimentul acela, nota n-ar avea de ce să se lege: notele nu se
        dau de pe un profil găsit la întâmplare, ci după o seară petrecută
        împreună. De aceea nici nu se desenează caseta — un formular care se
        vede și refuză la apăsare e mai rău decât unul care lipsește.
      -->
      <form class="review-form" id="review-form" novalidate
            data-evaluare-form
            data-slug="<?= h($slugEvaluare) ?>"
            data-membru="<?= (int) $p['id'] ?>"
            data-csrf="<?= h(tokenCsrf()) ?>">
        <img class="comment-form__avatar" src="<?= h(urlPoza($eu['poza'] ?? null, true)) ?>" alt="" width="96" height="96">

        <div class="comment-form__main">
          <p class="review-form__unde">
            După <a href="<?= h(urlEveniment($slugEvaluare)) ?>"><?= h($titluEvaluare) ?></a>
          </p>

          <div class="review-form__stars">
            <span class="review-form__ask">Ce notă îi dai?</span>
            <!-- Selectorul de stele se generează din JS, în [data-stars-input].
                 `data-chosen` e nota deja dată — de pe pagina evenimentului sau
                 de aici, data trecută — ca omul să nu creadă că a pierdut-o. -->
            <div class="stars-input" data-stars-input id="review-stars"
                 data-chosen="<?= $stelePuse > 0 ? (int) $stelePuse : '' ?>"></div>
            <span class="review-form__chosen" id="review-chosen">
              <?= $stelePuse > 0 ? (int) $stelePuse . ' din 5' : 'Nicio notă aleasă' ?>
            </span>
          </div>

          <label class="sr-only" for="review-text">Comentariul tău</label>
          <textarea id="review-text" rows="3" maxlength="<?= EVALUARE_TEXT_MAX ?>"
                    placeholder="Spune pe scurt cum a fost…"><?= h($textPus) ?></textarea>
          <p class="field__error" id="err-evaluare" hidden></p>

          <div class="comment-form__actions">
            <p class="comment-form__hint">Nota e anonimă. Textul e opțional.</p>
            <button class="btn btn--primary btn--sm" type="submit">Trimite evaluarea</button>
          </div>
        </div>
      </form>
      <?php elseif ($eProfilulMeu): ?>
      <p class="feedback__nota">
        Notele vin de la oamenii cu care ai fost la evenimente. Nu se vede cine
        le-a dat.
      </p>
      <?php endif; ?>

      <!--
        Lista de evaluări primite. Doar cele SCRISE: stelele date dintr-o
        apăsare intră în medie și în barele de sus, dar n-au ce citi aici.
        Cine s-a așezat să scrie ceva își pune și numele.
      -->
      <ul class="comments" data-lista-evaluari>
        <?= randeazaEvaluari($evaluariProfil) ?>
      </ul>

      <?php if ($evaluariProfil === []): ?>
      <p class="feedback__gol">
        <?= h($prenumeScurt) ?> nu a primit niciun feedback scris.
      </p>
      <?php endif; ?>

      <div class="load-more" data-mai-multe-evaluari hidden>
        <button class="btn btn--ghost" type="button" data-mai-multe-evaluari-buton>Vezi mai mult</button>
      </div>
    </section>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
