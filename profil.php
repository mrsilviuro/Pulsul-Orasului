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
        header('Location: /index.php');
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
 * Omul de casă vede un „×" în dreptul fiecărei păreri scrise.
 *
 * E singurul fel în care o vorbă nedreaptă poate pleca de pe profilul cuiva:
 * notele nu se retrag, nu se raportează, iar mai devreme nu exista nicio
 * pagină de moderare a lor. Ștergerea propriu-zisă se face de la lista
 * întreagă (admin-evaluari.php) SAU de aici, de la locul faptei — de obicei
 * așa afli de ea: intri pe profilul cuiva și vezi ce scrie.
 *
 * Se întreabă din bază la fiecare cerere (esteStaff citește `este_staff` din
 * rândul adus de membruCurent), nu din sesiune: un drept luat înapoi trebuie
 * să dispară pe loc.
 */
$eStaffulCasei = $eu !== null && esteStaff($eu);

/**
 * Istoricul: pe unde a fost omul.
 *
 * Tabul de lângă evaluări. Se citește tot, dintr-o cerere, și intră tot în
 * pagină — ascunsul îl face main.js. Nu se amestecă cu $evenimenteProfil de
 * mai sus: acela e ce PUNE LA CALE, adică ce urmează; ăsta e ce a fost.
 */
$istoricProfil = $p ? istoricEvenimente((int) $p['id']) : [];

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

/**
 * Celelalte două cifre: la câte evenimente a fost, și de câte ori n-a ajuns.
 *
 * Se exclud una pe alta, dinadins — vezi laCateEvenimenteAFost(). Un eveniment
 * la care omul a confirmat, dar organizatorul a însemnat că n-a venit, iese
 * din prima și intră în a doua. Altfel cele două s-ar bate cap în cap, iar
 * cine le citește n-ar ști care e adevărul.
 */
$cateParticipari = $p ? laCateEvenimenteAFost((int) $p['id'])     : 0;
$cateAbsente     = $p ? laCateEvenimenteNuAVenit((int) $p['id'])  : 0;

/**
 * A patra cifră: codurile QR găsite prin oraș, la jocul „FindMe".
 *
 * Deocamdată e mereu zero — jocul se scrie mai târziu, iar tabelul scanărilor
 * nu există încă. Cartonașul stă totuși de pe acum lângă celelalte trei:
 * adăugat mai târziu, ar fi rearanjat un rând pe care oamenii se obișnuiseră
 * să-l citească. Vezi cateCoduriQrGasite() din inc/evaluari.php.
 */
$cateCoduriQr = $p ? cateCoduriQrGasite((int) $p['id']) : 0;

/** Câte se văd din prima. Restul intră în pagină, dar ascunse. */
const EVENIMENTE_VIZIBILE = 4;

$titlu     = $numeProfil . ' — Profil membru — PulsulOrasului.Ro';
$descriere = 'Profilul membrului ' . $numeProfil . ' pe PulsulOrasului.Ro';

require __DIR__ . '/inc/antet.php';
?>


<main id="main">
  <div class="wrap">

    <nav class="crumbs" aria-label="Navigare">
      <a href="/index.php">Acasă</a>
      <span aria-hidden="true">/</span>
     <span aria-hidden="true">Membri</span>
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
          <?php if (estePozaValida($pozaProfil)): ?>
          <!--
            Cu poză, cercul se poate apăsa: se deschide mărită, cât încape pe
            ecran. Fișierul de pe server e 512×512 (POZA_LATURA), iar aici se
            vede la 86 px — deci e ce arăta, nu o mărire pe degeaba.

            Un `<button>`, nu un `<img>` cu ascultător pus pe el: se ajunge la
            el cu tabul, se apasă cu Enter și cititorul de ecran spune ce face.
            Creionul rămâne frate cu el, nu copil — un link în interiorul unui
            buton n-ar fi HTML valid.
          -->
          <button class="profile__poza-lupa" type="button"
                  data-mareste="<?= h(urlPoza($pozaProfil)) ?>"
                  aria-label="Vezi poza mai mare">
            <img class="profile__avatar" id="profil-avatar"
                 src="<?= h(urlPoza($pozaProfil)) ?>" alt="" width="96" height="96">
          </button>
          <?php else: ?>
          <!-- Fără poză, n-are ce se mări: chipul implicit e un desen, nu un om. -->
          <img class="profile__avatar" id="profil-avatar"
               src="<?= h(urlPoza($pozaProfil)) ?>" alt="" width="96" height="96">
          <?php endif; ?>

          <?php if ($eProfilulMeu): ?>
          <a class="profile__poza-edit" href="/poza.php"
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
        <span class="stat__label">Ieșiri organizate</span>
      </div>

      <div class="stat">
        <span class="stat__ico stat__ico--ok" aria-hidden="true">
          <svg class="ico" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
          </svg>
        </span>
        <span class="stat__value"><?= (int) $cateParticipari ?></span>
        <span class="stat__label">Prezent la activități</span>
      </div>

      <div class="stat">
        <span class="stat__ico stat__ico--warn" aria-hidden="true">
          <svg class="ico" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>
          </svg>
        </span>
        <span class="stat__value"><?= (int) $cateAbsente ?></span>
        <span class="stat__label">A confirmat, dar nu a venit</span>
      </div>

      <!--
        „FindMe": coduri QR ascunse prin oraș, pe care oamenii le caută și le
        scanează. Jocul se scrie mai târziu; cifra e deocamdată mereu zero,
        dintr-un singur loc (cateCoduriQrGasite).
      -->
      <div class="stat">
        <span class="stat__ico stat__ico--qr" aria-hidden="true">
          <svg class="ico" viewBox="0 0 24 24">
            <rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/>
            <rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/>
            <rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/>
            <path d="M13.5 13.5h3v3h-3zM20.5 13.5h-1M20.5 17.5v3h-3M16.5 20.5h-1"/>
          </svg>
        </span>
        <span class="stat__value"><?= (int) $cateCoduriQr ?></span>
        <span class="stat__label">Coduri QR găsite</span>
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
          <h2 class="section-title" id="evenimente-title">Ieșiri organizate</h2>
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
        <a class="btn btn--primary btn--sm" href="/adauga_eveniment.php">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 5v14"/><path d="M5 12h14"/>
          </svg>
          <span>Propune o ieșire</span>
        </a>
        <?php endif; ?>
      </div>

      <?php if ($evenimenteProfil === []): ?>
      <!-- Niciunul. Al meu → o invitație; al altcuiva → o constatare. -->
      <div class="fara-nimic">
        <?php if ($eProfilulMeu): ?>
        <p class="fara-nimic__text">Nu organizezi nimic, nu vrei să încerci?</p>
        <a class="btn btn--primary btn--sm" href="<?= $logat
              ? '/adauga_eveniment.php'
              : '/login.php?redirect=' . h(urlencode('/adauga_eveniment.php')) ?>">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 5v14"/><path d="M5 12h14"/>
          </svg>
          <span>Propune o ieșire</span>
        </a>
        <?php else: ?>
        <p class="fara-nimic__text"><?= h($prenumeScurt) ?> nu organizează momentan nimic.</p>
        <?php endif; ?>
      </div>

      <?php else: ?>
      <!--
        Peste primele patru, cartonașele intră în pagină ascunse. Le arată
        butonul „Vezi mai mult…", fără să mai ceară nimic de la server.

        Cum arată un cartonaș se scrie într-un singur loc,
        randeazaCartonasEveniment() din inc/evenimente.php — tabul „Istoric"
        de mai jos desenează cu aceeași funcție.
      -->
      <div class="grid" id="evenimente-lista">
        <?php foreach ($evenimenteProfil as $i => $ev): ?>
        <?= randeazaCartonasEveniment($ev, '', $i >= EVENIMENTE_VIZIBILE) ?>
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

    <!-- ================= TABURI: EVALUĂRI ȘI ISTORIC ====================
      Aceeași componentă ca pe pagina evenimentului: `[data-tabs]` din
      main.js, cu `role="tab"` și `aria-controls`. Nimic nou de scris — nici
      măcar deschiderea din adresă (#panel-istoric), care vine cu ea.

      Două lucruri care se citesc unul lângă altul: ce spun ceilalți despre
      om, și pe unde a fost. Unul sub altul ar fi făcut o pagină lungă în
      care al doilea s-ar fi văzut doar dacă mai ai răbdare să dai jos.

      NU se amestecă cu „Evenimente organizate" de mai sus: acolo se vede ce
      pune la cale, adică ce URMEAZĂ. Aici e ce a fost.
    ============================================================== -->
    <section class="tabs-section" aria-labelledby="tabs-profil-title">
      <h2 class="sr-only" id="tabs-profil-title">Evaluări și istoric</h2>

      <div class="tabs" role="tablist" data-tabs aria-label="Evaluări și istoric">
        <button class="tab is-active" type="button" role="tab" id="tab-evaluari"
                aria-controls="panel-evaluari" aria-selected="true" tabindex="0">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <path d="m12 3.8 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 10l5.9-.9L12 3.8Z"/>
          </svg>
          <span>Evaluări</span>
          <span class="tab__count"><?= (int) $rezumatProfil['cate'] ?></span>
        </button>

        <button class="tab" type="button" role="tab" id="tab-istoric"
                aria-controls="panel-istoric" aria-selected="false" tabindex="-1">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/><path d="M12 7.2V12l3 1.8"/>
          </svg>
          <span>Istoric</span>
          <span class="tab__count"><?= count($istoricProfil) ?></span>
        </button>
      </div>

    <!-- =========================== FEEDBACK =============================
      Evaluările primite. STELELE SINGURE sunt anonime și nici nu se arată;
      doar părerile scrise ajung în listă, iar acelea vin semnate.

      Toate intră în pagină; ascunsul îl face main.js, ca la comentarii și la
      listele din taburile evenimentului.
    ============================================================== -->
    <div class="panel is-active" id="panel-evaluari" role="tabpanel"
         aria-labelledby="tab-evaluari" tabindex="0"
         data-descopera
         data-deodata="<?= EVALUARI_DEODATA ?>">

      <!-- Media, câte sunt, barele pe stele — toate din randeazaRezumatEvaluari() -->
      <div class="rating-summary" data-rezumat-evaluari>
        <?= randeazaRezumatEvaluari($rezumatProfil) ?>
      </div>

      <?php if ($eProfilulMeu): ?>
      <!--
        Rândul care spune de unde vin cifrele de deasupra — deci stă lipit de
        ele, nu la capătul de jos al secțiunii.

        Odată cu barele, ca înainte — numai că barele se arată acum
        întotdeauna, așa că și el. La un profil fără nicio notă e chiar mai
        bine venit: lămurește niște bare goale, adică spune de unde ar veni
        stelele dacă ar veni. Rândul care spunea asta înainte, în locul
        casetei, a plecat odată cu ea.

        Numai pe profilul propriu, ca înainte: e o lămurire pentru cel care își
        vede notele și se întreabă de la cine sunt. Pe profilul altcuiva ar fi
        un rând despre treburile lui, scris pentru un privitor care n-are ce
        face cu el.
      -->
      <p class="feedback__nota">
        Calificativele sunt complet anonime și vin din partea persoanelor alături de care ai participat la evenimente.
      </p>
      <?php endif; ?>

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
      <?php endif; ?>

      <!--
        Lista de evaluări primite. Doar cele SCRISE: stelele date dintr-o
        apăsare intră în medie și în barele de sus, dar n-au ce citi aici.
        Cine s-a așezat să scrie ceva își pune și numele.

        `data-admin` și `data-csrf` se pun NUMAI pentru staff, și numai atunci
        se desenează și „×"-urile. Sunt aceleași atribute de care se leagă
        blocul de administrare din main.js — singura pagină din afara zonei de
        admin care le are, fiindcă aici e singurul loc unde o părere nedreaptă
        se vede la locul ei, pe profilul omului despre care e scrisă.
      -->
      <ul class="comments" data-lista-evaluari data-descopera-lista
          <?php if ($eStaffulCasei): ?>data-admin data-csrf="<?= h(tokenCsrf()) ?>"<?php endif; ?>>
        <?= randeazaEvaluari($evaluariProfil, $eStaffulCasei) ?>
      </ul>

      <?php if ($evaluariProfil === []): ?>
      <p class="feedback__gol">
        <?= h($prenumeScurt) ?> nu a primit niciun feedback scris.
      </p>
      <?php endif; ?>

      <div class="load-more" data-descopera-mai-mult hidden>
        <button class="btn btn--ghost" type="button" data-descopera-buton>Vezi mai mult</button>
      </div>
    </div>

    <!-- ============================ ISTORIC =============================
      Pe unde a fost omul: tot ce se vede pe site și are numele lui pe lista
      de participanți — și ce urmează, și ce s-a încheiat, și ale lui, și ale
      altora. Cartonașele sunt aceleași ca pe prima pagină, prin aceeași
      funcție (randeazaCartonasEveniment din inc/evenimente.php).

      Toate intră în pagină; ascunsul îl face main.js, câte ISTORIC_DEODATA,
      prin aceeași componentă ca lista de evaluări de deasupra.
    ============================================================== -->
    <div class="panel" id="panel-istoric" role="tabpanel"
         aria-labelledby="tab-istoric" tabindex="0" hidden
         data-descopera
         data-deodata="<?= ISTORIC_DEODATA ?>">

      <?php if ($istoricProfil === []): ?>
      <!-- Niciunul. Se spune pe mijloc, ca „Niciun comentariu încă" din tabul
           de alături — un rând singur, aliniat la stânga, ar arăta a text
           uitat acolo. -->
      <p class="panel__intro panel__intro--gol">
        <?= h($prenumeScurt) ?> nu a mai participat la niciun eveniment.
      </p>
      <?php else: ?>
      <div class="grid" data-descopera-lista>
        <?= randeazaIstoric($istoricProfil) ?>
      </div>

      <div class="load-more" data-descopera-mai-mult hidden>
        <button class="btn btn--ghost" type="button" data-descopera-buton>Vezi mai mult</button>
      </div>
      <?php endif; ?>
    </div>
    </section>
  </div>

  <?php if (estePozaValida($pozaProfil)): ?>
  <!-- ============================ POZA MĂRITĂ ==========================
    Caseta în care se deschide poza de profil, la apăsare pe cerc.

    Șablon, ca la casetele de confirmare de pe pagina evenimentului: HTML-ul
    se scrie tot în PHP, iar JS-ul doar îl clonează când are nevoie. Se
    tipărește numai când există o poză — fără ea, n-are cine să-l ceară.

    `alt` rămâne gol dinadins: e chipul omului al cărui nume scrie mare
    deasupra, nu o poză care spune ceva în plus. Un cititor de ecran ar
    repeta numele degeaba.
  ============================================================== -->
  <template id="sablon-lupa">
    <div class="lupa" role="dialog" aria-modal="true" aria-label="Poza de profil">
      <button class="lupa__inchide" type="button" aria-label="Închide">
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M6 6l12 12M18 6 6 18"/>
        </svg>
      </button>
      <img class="lupa__poza" src="" alt="" width="512" height="512">
    </div>
  </template>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
