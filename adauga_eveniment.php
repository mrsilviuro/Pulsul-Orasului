<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — publicarea și editarea unui eveniment.
 *
 * Fără parametri: formular gol, pentru un eveniment nou. Se ajunge aici din
 * butonul „+ Eveniment nou" de pe prima pagină.
 *
 * Cu „?slug=…": același formular, dar precompletat cu evenimentul acela, de
 * schimbat. Se ajunge din butonul „Editează" de pe event.php.
 *
 * Cu „?remake=…": tot precompletat din evenimentul acela, dar ca unul NOU.
 * Se ajunge din butonul „Remake", care apare pe pagina unui eveniment
 * încheiat sau anulat. Se copiază tot ce a scris omul o dată; „Când o să aibă
 * loc?" rămâne gol, fiindcă data e singurul lucru care chiar se schimbă.
 *
 * Un singur formular pentru toate trei, dinadins: două formulare aproape la
 * fel s-ar despărți la prima corectură, iar regulile de verificare ar începe
 * să difere între „nou" și „schimbat" — exact acolo unde n-au voie.
 */

require_once __DIR__ . '/inc/evenimente.php';
// Pentru randeazaZonaAnulare(): aceeași zonă se desenează și pe event.php.
require_once __DIR__ . '/inc/afisare-eveniment.php';
// Pentru codul de abțibild al unei vânători „FindMe", la editare.
require_once __DIR__ . '/inc/coduri-qr.php';

$slug   = trim((string) ($_GET['slug'] ?? ''));
$deRefacut = trim((string) ($_GET['remake'] ?? ''));
$membru = membruCurent();

/**
 * Fără cont nu se intră deloc.
 *
 * Nu ascundem formularul, ci trimitem omul de pe pagină: dacă l-am lăsa să
 * vadă câmpurile și să le completeze degeaba, ar afla că n-are cont abia la
 * apăsarea butonului, cu tot ce scrisese pierdut.
 */
if ($membru === null) {
    $coada = '';

    if ($slug !== '') {
        $coada = '?slug=' . urlencode($slug);
    } elseif ($deRefacut !== '') {
        $coada = '?remake=' . urlencode($deRefacut);
    }

    cereIntrare('/adauga_eveniment.php' . $coada);
}

$membruId  = (int) $membru['id'];

/**
 * Omul de casă publică direct: anunțul lui nu mai are pe cine să aștepte.
 *
 * De steagul ăsta atârnă patru lucruri pe pagina asta — ce categorii i se
 * arată, ce scrie pe buton, ce scrie în panoul de după, și dacă se desenează
 * bifa „nu-l arăta pe profil". Toate patru sunt purtare frumoasă; regula o
 * ține api/eveniment.php, care întreabă din nou baza la fiecare cerere.
 */
$eStaff = esteStaff($membru);

/**
 * Lista din care alege omul. Categoriile ținute pentru casă („FindMe") intră
 * în ea numai pentru staff — iar cine nu e staff nici nu poate publica în
 * ele: api/eveniment.php cere aceeași listă la verificare.
 */
$categorii = categoriiEvenimente($eStaff);

/**
 * Ce se editează, dacă se editează ceva.
 *
 * Un slug care nu duce nicăieri și unul al altcuiva sfârșesc la fel: pe prima
 * pagină. Ca la event.php, același răspuns pentru amândouă — altfel, ghicind
 * sluguri, s-ar putea afla ce evenimente există.
 */
$ev = null;

if ($slug !== '') {
    $ev = evenimentDeEditat($slug, $membruId);

    if ($ev === null) {
        header('Location: index.php');
        exit;
    }

    /**
     * Început înseamnă închis pentru corecturi.
     *
     * Nu pe prima pagină, ci pe pagina lui: acolo mai are ce apăsa —
     * „Anulează evenimentul", încă o oră, și „Încheie evenimentul". Butonul
     * „Editează" nici nu se mai desenează acolo, deci aici se ajunge doar cu
     * o adresă veche în mână sau cu o filă lăsată deschisă.
     */
    if (!poateFiEditat($ev)) {
        header('Location: ' . urlEveniment((string) $ev['slug']));
        exit;
    }
}

$eEditare = $ev !== null;

/**
 * Din ce eveniment se face unul nou, dacă se face.
 *
 * Se cere numai când NU se editează: cele două n-au ce căuta împreună, iar o
 * adresă cu amândouă („?slug=…&remake=…") ar fi însemnat, în cel mai bun caz,
 * că omul a lipit greșit. Câștigă editarea, fiindcă acolo se schimbă un rând
 * care există deja.
 *
 * Verificarea („e al meu? s-a încheiat sau s-a anulat?") stă într-un singur
 * loc, evenimentDeRefacut(), fiindcă o cer trei fișiere: pagina asta,
 * api/eveniment.php (care copiază coperta) și api/previzualizare.php.
 */
$refacut = ($eEditare || $deRefacut === '')
    ? null
    : evenimentDeRefacut($deRefacut, $membruId);

if (!$eEditare && $deRefacut !== '' && $refacut === null) {
    header('Location: index.php');
    exit;
}

$eRemake = $refacut !== null;

/**
 * Rândul din care se completează formularul.
 *
 * La editare e chiar evenimentul care se schimbă; la refacere, cel din care se
 * copiază. „Când o să aibă loc?" se golește pe loc, aici, nu în fiecare câmp
 * în parte: data e singurul lucru care chiar se schimbă la un remake, iar o
 * dată veche lăsată în căsuță ar fi trecut de verificarea celor două ceasuri
 * doar dacă omul o observa.
 */
$sursa = $ev;

if ($eRemake) {
    $sursa = $refacut;
    $sursa['data_eveniment'] = null;
    $sursa['ora_inceput']    = null;
    $sursa['ora_sfarsit']    = null;
}

/**
 * Codul de abțibild, la editarea unei vânători.
 *
 * Nu e o coloană din `evenimente` — legătura stă în `coduri_qr` — deci se aduce
 * aici și se pune în $sursa, ca să-l poată citi $val() ca pe orice alt câmp.
 *
 * LA REFACERE NU SE COPIAZĂ, dinadins, deși „Remake" copiază tot restul: un
 * abțibild s-a găsit o dată și s-a dezlipit. Vânătoarea nouă cere un cod nou,
 * lipit în altă parte — altfel ar fi fost aceeași ascunzătoare, cu aceeași
 * hârtie, pe care primul câștigător o știe deja.
 */
if ($eEditare && esteJocQr($sursa)) {
    $codulLui = codQrAlEvenimentului((int) $sursa['id']);

    if ($codulLui !== null) {
        $sursa['cod_qr'] = $codulLui['cod'];
    }
}

/**
 * Bifa de ținut deoparte: la editare urmează ce e în bază, la un formular gol
 * pornește STINSĂ. Alegerea obișnuită e ca anunțul să se vadă pe profilul
 * celui care l-a pus — cealaltă e pentru ce se publică în numele orașului.
 */
$ascunsPeProfil = $sursa !== null && (int) ($sursa['ascuns_pe_profil'] ?? 0) === 1;

/**
 * Limita de evenimente active se cere doar la unul nou.
 *
 * La editare, limita s-ar aplica chiar evenimentului care se editează: omul cu
 * un singur eveniment activ ar fi oprit tocmai de el, deci n-ar mai putea
 * corecta niciodată nimic.
 */
$voie = $eEditare
    ? ['poate' => true, 'mesaj' => '', 'active' => []]
    : poatePublicaEveniment($membruId, $eStaff);

/* ------------------- valorile cu care pleacă formularul ---------------- */

/**
 * Ce scrie într-un câmp: ce era în bază la editare sau la refacere, nimic la
 * un formular gol. Vine din $sursa, nu din $ev — vezi mai sus de ce.
 */
$val = static function (string $camp, string $implicit = '') use ($sursa): string {
    return $sursa !== null && ($sursa[$camp] ?? null) !== null
        ? (string) $sursa[$camp]
        : $implicit;
};

$copertaAcum = $sursa !== null ? urlCoperta($sursa['coperta'] ?? null) : '';

/**
 * Bifele: la editare urmează ce e în bază, la un formular gol pornesc bifate.
 *
 * „Nu se știe până când ține" bifată din start e adevărul pentru cele mai
 * multe anunțuri: ora de început se știe mereu, cea de sfârșit aproape
 * niciodată. Cine o știe scoate bifa și scrie ora — o mișcare, în loc de una
 * pe care ar fi trebuit s-o facă toți ceilalți.
 */
$faraOraSfarsit = $sursa === null || ($sursa['ora_sfarsit'] ?? null) === null;

/**
 * „Gratuit" înseamnă aici același lucru ca la afișare (costScris): și NULL, și
 * zero. În bază rămân două lucruri diferite — NULL e „n-a cerut bani", 0 e „a
 * scris el zero" — dar pe pagina evenimentului amândouă scriu „Gratuit", deci
 * formularul n-are voie să spună altceva. Altfel omul ar deschide editarea unui
 * eveniment gratuit și ar găsi bifa scoasă și un preț de 0 lei.
 */
$eGratuit  = $sursa === null || ($sursa['cost'] ?? null) === null || (float) $sursa['cost'] <= 0;
$faraMinim = $sursa === null || ($sursa['participanti_min'] ?? null) === null;
$faraMaxim = $sursa === null || ($sursa['participanti_max'] ?? null) === null;

$titlu = 'Publică un eveniment — PulsulOrasului.Ro';

if ($eEditare) {
    $titlu = 'Schimbă evenimentul — PulsulOrasului.Ro';
} elseif ($eRemake) {
    $titlu = 'Încă unul la fel — PulsulOrasului.Ro';
}
$descriere = 'Spune orașului ce pui la cale.';
$noindex   = true;
$pagina    = '';

// Token-ul se cere înaintea antetului: după ce pagina începe să se tipărească,
// sesiunea nu mai poate fi pornită.
$csrf = tokenCsrf();

require __DIR__ . '/inc/antet.php';
?>


<main id="main">
  <div class="wrap wrap--ingust">

    <nav class="crumbs" aria-label="Navigare">
      <a href="index.php">Acasă</a>
      <span aria-hidden="true">/</span>
      <?php if ($eEditare): ?>
      <a href="<?= h(urlEveniment((string) $ev['slug'])) ?>"><?= h(inceputDeText((string) $ev['titlu'], 40)) ?></a>
      <span aria-hidden="true">/</span>
      <span class="crumbs__current">Editează</span>
      <?php elseif ($eRemake): ?>
      <a href="<?= h(urlEveniment((string) $refacut['slug'])) ?>"><?= h(inceputDeText((string) $refacut['titlu'], 40)) ?></a>
      <span aria-hidden="true">/</span>
      <span class="crumbs__current">Încă unul la fel</span>
      <?php else: ?>
      <span class="crumbs__current">Eveniment nou</span>
      <?php endif; ?>
    </nav>

    <?php if ($eEditare): ?>
    <h1 class="setari__titlu">Editează activitatea</h1>
    <p class="setari__lead">
      Orice modificare trebuie verificată și aprobată de un moderator. Până atunci, activitatea este invizibilă publicului larg.
    </p>
    <?php elseif ($eRemake): ?>
    <h1 class="setari__titlu">Încă unul la fel</h1>
    <p class="setari__lead">
      Am adus tot ce scrisese „<?= h(inceputDeText((string) $refacut['titlu'], 60)) ?>".
      Spune doar când are loc de data asta — și schimbă ce vrei, dacă s-a
      schimbat ceva. Anunțul cel vechi rămâne la locul lui, neatins.
    </p>
    <?php else: ?>
    <h1 class="setari__titlu">Publică un eveniment</h1>
    <p class="setari__lead">
      Completează ce știi acum. Anunțul intră la verificare și apare pe site
      după ce îl citim.
    </p>
    <?php endif; ?>

    <?php if (!$voie['poate']): ?>
    <!-- ================== ARE DEJA UNUL ACTIV ====================== -->
    <section class="card-set">
      <h2 class="card-set__titlu">
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.4 2"/>
        </svg>
        Ai deja un eveniment în desfășurare
      </h2>
      <p class="card-set__lead"><?= h($voie['mesaj']) ?></p>

      <ul class="lista-simpla">
        <?php foreach ($voie['active'] as $activ): ?>
        <li>
          <strong><?= h($activ['titlu']) ?></strong>
          <span class="lista-simpla__data">
            <?= h(date('d.m.Y', strtotime((string) $activ['data_eveniment']))) ?>
          </span>
        </li>
        <?php endforeach; ?>
      </ul>

      <p class="card-set__lead">
        Un eveniment este considerat încheiat în mod automat următoarea zi ce urmează zilei în care a avut loc.
        Până atunci, tot ce ai de făcut e să te ocupi de el.
      </p>

      <!--
        „Înapoi" înseamnă exact înapoi: pe pagina de unde s-a apăsat
        „+ Eveniment nou", de obicei profilul. Saltul îl face main.js cu
        history.back(); „href" rămâne prima pagină, pentru cine ajunge aici
        direct, cu un link, fără nimic în urmă.
      -->
      <a class="btn btn--ghost" id="ev-inapoi" href="index.php">Înapoi</a>
    </section>

    <?php else: ?>
    <!-- ======================== FORMULARUL ========================== -->
    <div id="ev-block">
      <form class="form form--eveniment" id="eveniment-form" novalidate enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <?php if ($eEditare): ?>
        <!-- Slugul spune punctului de intrare care eveniment se schimbă. Nu e
             o dovadă: acolo se verifică din nou al cui e. -->
        <input type="hidden" name="slug" value="<?= h((string) $ev['slug']) ?>">
        <?php elseif ($eRemake): ?>
        <!-- Din care se face unul nou. E nevoie de el la salvare pentru UN
             SINGUR lucru: coperta. Restul câmpurilor au venit deja completate
             în pagină și pleacă de acolo, ca la orice eveniment nou; poza însă
             stă pe disc, iar formularul n-are cum s-o trimită înapoi fără s-o
             ceară omului din nou. Nici ăsta nu e o dovadă: api/eveniment.php
             verifică iar al cui e și dacă chiar s-a încheiat. -->
        <input type="hidden" name="remake" value="<?= h((string) $refacut['slug']) ?>">
        <?php endif; ?>

        <!-- --------------------- Ce și unde --------------------- -->
        <section class="card-set">
          <h2 class="card-set__titlu">Despre ce e vorba</h2>

          <div class="field">
            <label for="ev-titlu">Titlul evenimentului / activității <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="ev-titlu" name="titlu" maxlength="<?= TITLU_EVENIMENT_MAX ?>"
                   value="<?= h($val('titlu')) ?>"
                   placeholder="Ex: Monopoly la terasă!" required
                   aria-describedby="err-ev-titlu">
            <p class="field__error" id="err-ev-titlu" hidden></p>
          </div>

          <div class="field">
            <label for="ev-categorie">Categorie <span class="req" aria-hidden="true">*</span></label>
            <select id="ev-categorie" name="categorie_id" required aria-describedby="err-ev-categorie">
              <option value="" <?= $eEditare ? '' : 'selected' ?> disabled>Alege…</option>
              <?php foreach ($categorii as $c): ?>
              <!-- `data-joc-qr` spune JS-ului care categorie cere un cod de
                   abțibild. Steagul vine din bază (`categorii.joc_qr`), nu din
                   numele categoriei — vezi sql/025-coduri-qr.sql. -->
              <option value="<?= (int) $c['id'] ?>"
                      <?= (int) ($c['joc_qr'] ?? 0) === 1 ? 'data-joc-qr="1"' : '' ?>
                      <?= $val('categorie_id') === (string) $c['id'] ? 'selected' : '' ?>><?= h($c['nume']) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="field__error" id="err-ev-categorie" hidden></p>
          </div>

          <!--
            CODUL DE PE ABȚIBILD — numai la categoriile de joc („FindMe").

            Stă ascuns până când se alege o astfel de categorie, și se arată
            atunci; JS-ul face doar atât (vezi „codul de abțibild" din main.js).
            Fără JS rămâne vizibil tot timpul, ceea ce e supărător, dar nu
            stricăcios: la orice altă categorie serverul nici nu-l citește.

            `required` NU se scrie în HTML, ci îl pune JS-ul odată cu arătarea
            câmpului. Un `required` pe un câmp ascuns oprește trimiterea
            formularului cu o bulă pe care browserul n-o poate arăta, fiindcă
            n-are unde s-o pună — iar omul rămâne cu un buton care nu face
            nimic și fără nicio vorbă de ce.

            Regula adevărată e oricum pe server: verificaEveniment() cere codul
            când categoria e un joc, iar api/eveniment.php verifică apoi că el
            chiar există și e liber.
          -->
          <div class="field field--qr" id="camp-cod-qr" data-camp-qr hidden>
            <label for="ev-cod-qr">Codul de pe abțibild <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="ev-cod-qr" name="cod_qr"
                   maxlength="<?= COD_QR_LUNGIME ?>" size="<?= COD_QR_LUNGIME ?>"
                   value="<?= h($val('cod_qr')) ?>"
                   placeholder="K3M7P" autocomplete="off"
                   autocapitalize="characters" spellcheck="false"
                   aria-describedby="err-ev-cod-qr ajutor-cod-qr">
            <p class="field__hint" id="ajutor-cod-qr">
              Cele <?= COD_QR_LUNGIME ?> semne de pe abțibildul pe care l-ai lipit
              deja prin oraș. Îl iei de pe <a href="coduri.php">pagina codurilor</a>.
              Vânătoarea începe în clipa în care publici anunțul.
            </p>
            <p class="field__error" id="err-ev-cod-qr" hidden></p>
          </div>

          <div class="field">
            <label for="ev-oras">Oraș <span class="req" aria-hidden="true">*</span></label>
            <!--
              Lista vine din inc/config.php (cheia „orase"), prin
              oraseDisponibile() — nu e scrisă aici. Un oraș nou înseamnă un
              rând în config, iar formularul și verificarea de pe server se
              schimbă amândouă odată, fiindcă citesc din același loc.

              Prima opțiune e goală și `disabled`, ca la categorie: nimic nu e
              ales dinainte, nici măcar când e un singur oraș în listă. Omul
              trebuie să spună el unde are loc, ca să nu publice din greșeală
              în alt oraș în ziua în care lista are mai multe.
            -->
            <select id="ev-oras" name="oras" required aria-describedby="err-ev-oras">
              <option value="" <?= $val('oras') === '' ? 'selected' : '' ?> disabled>Selectează orașul</option>
              <?php foreach (oraseDisponibile() as $oras): ?>
              <option value="<?= h($oras) ?>"
                      <?= $val('oras') === $oras ? 'selected' : '' ?>><?= h($oras) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="field__error" id="err-ev-oras" hidden></p>
          </div>

          <div class="field">
            <label for="ev-locatie">Unde are loc <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="ev-locatie" name="locatie" maxlength="<?= LOCATIE_MAX ?>"
                   value="<?= h($val('locatie')) ?>"
                   placeholder="Ex: Pub-ul de pe centru!" required
                   aria-describedby="err-ev-locatie">
            <p class="field__error" id="err-ev-locatie" hidden></p>
          </div>
        </section>

        <!-- ---------------------- Coperta ----------------------- -->
        <section class="card-set">
          <h2 class="card-set__titlu">Poza de copertă <span class="field__optional">(opțional)</span></h2>
          <p class="card-set__lead">
            Te rugăm să alegi o poză care să aiba legătură cu activitatea sau evenimentul pe care urmează să-l publici, de minim
            <?= COPERTA_SURSA_MIN_LATIME ?>×<?= COPERTA_SURSA_MIN_INALTIME ?> pixeli. Acest lucru este opțional. Dacă nu vei încărca nici o poza, vom folosi noi una conform categoriei selectate.
          </p>

          <?php if ($copertaAcum !== ''): ?>
          <!--
            Poza de acum, la editare. Cât timp nu alege alta, asta rămâne:
            un formular trimis fără fișier înseamnă „n-am umblat la poză", nu
            „șterge-o". Când alege alta, JS ascunde blocul ăsta și arată cadrul
            de așezare — ca să nu stea două poze una peste alta.
          -->
          <div class="coperta-acum" id="ev-coperta-acum">
            <img src="<?= h($copertaAcum) ?>" alt="Coperta de acum a evenimentului"
                 width="1600" height="900" decoding="async">
            <p class="coperta-acum__text">
              <?= $eRemake
                    ? 'Poza de la anunțul dinainte. O păstrăm, dacă nu alegi alta.'
                    : 'Aceasta este imaginea de copertă actuală. O poți păstra sau poți alege alta.' ?>
            </p>
          </div>
          <?php endif; ?>

          <div class="field">
            <!-- Aceleași clase ca la poza de profil (poza.php), deci același CSS. -->
            <label class="poza-drop" id="ev-drop" for="ev-coperta">
              <span class="poza-drop__ico" aria-hidden="true">
                <svg class="ico" viewBox="0 0 24 24">
                  <rect x="3" y="5" width="18" height="14" rx="2.5"/>
                  <circle cx="8.5" cy="10" r="1.6"/><path d="m4 17 5-4.5 4 3.5 3-2.5 4 3.5"/>
                </svg>
              </span>
              <span class="poza-drop__titlu">Alege o poză sau trage fișierul aici</span>
              <span class="poza-drop__hint" id="ev-coperta-nume">JPG, PNG sau WEBP, cel puțin <?= COPERTA_SURSA_MIN_LATIME ?>×<?= COPERTA_SURSA_MIN_INALTIME ?> px</span>
            </label>
            <input type="file" id="ev-coperta" name="coperta" accept="image/jpeg,image/png,image/webp" hidden>
            <p class="field__error" id="err-ev-coperta" hidden></p>
          </div>

          <!--
            Cadrul de așezare. Aceleași clase ca la poza de profil, doar rama e
            lată în loc de pătrată (.crop--lat) și n-are cerc peste ea.

            Ce se trimite la server e tot fișierul original plus trei numere —
            colțul din stânga-sus și lățimea decupajului. Poza tăiată de aici
            n-ar fi de încredere: cine vrea poate schimba orice pleacă din pagină.
          -->
          <div class="crop crop--lat" id="ev-crop" hidden>
            <div class="crop__stage" id="ev-crop-stage">
              <img class="crop__img" id="ev-crop-img" alt="Coperta aleasă, de așezat în cadru">
            </div>

            <div class="crop__zoom" id="ev-crop-bara">
              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                <rect x="3" y="5" width="18" height="14" rx="2.5"/>
                <circle cx="8.5" cy="10" r="1.6"/><path d="m4 17 5-4.5 4 3.5 3-2.5 4 3.5"/>
              </svg>
              <label class="sr-only" for="ev-crop-zoom">Mărimea pozei</label>
              <input type="range" id="ev-crop-zoom" min="1" max="4" step="0.01" value="1">
              <svg class="ico ico--mare" viewBox="0 0 24 24" aria-hidden="true">
                <rect x="3" y="5" width="18" height="14" rx="2.5"/>
                <circle cx="8.5" cy="10" r="1.6"/><path d="m4 17 5-4.5 4 3.5 3-2.5 4 3.5"/>
              </svg>
            </div>

            <p class="crop__ajutor" id="ev-crop-ajutor">
              Trage de poză ca să o miști. Din bară o mărești sau o micșorezi.
            </p>

            <div class="crop__actiuni">
              <button class="btn btn--ghost btn--sm" type="button" id="ev-coperta-renunt">Șterge poza</button>
            </div>
          </div>
        </section>

        <!-- ----------------------- Când ------------------------- -->
        <!--
          LA O VÂNĂTOARE „FINDME", FORMULARUL SE SUBȚIAZĂ.

          Trei atribute fac toată treaba, iar JS-ul le ascultă pe toate trei
          dintr-un singur loc (vezi „FINDME — CÂMPUL CU CODUL" din main.js):

            data-fara-joc  — pleacă de tot cât e aleasă o categorie de joc, și
                             se întoarce dacă omul se răzgândește. Câmpurile
                             dinăuntru se și STING (`disabled`), ca un cost
                             scris înainte de răzgândire să nu plece pe furiș
                             odată cu anunțul;
            data-vorba-joc — altă vorbă pentru același câmp. „Ora de început"
                             la un concert e ora la care se începe; la o
                             vânătoare e ora la care se TERMINĂ căutarea, iar
                             cine citește „început" scrie exact pe dos;
            data-vorba     — bucata de text care se schimbă, ca steluța de
                             „obligatoriu" de lângă ea să rămână pe loc.

          DE CE, ȘI NU O NOTĂ SCRISĂ UNDEVA: la o vânătoare n-are cine să vină,
          nu se ține nimeni de listă și nu costă nimic. Fiecare câmp rămas pe
          ecran e o întrebare fără răspuns pusă unui om de casă nou — iar el o
          va completa, fiindcă e acolo.
        -->
        <section class="card-set">
          <h2 class="card-set__titlu" data-vorba-joc="Data și ora limită">Când o să aibă loc?</h2>

          <!--
            Data se scrie ZZ-LL-AAAA, cum se scrie o dată în România. De ce nu
            e `type="date"` și cum se deschide totuși calendarul — scrie în
            inc/camp-data.php. Același câmp îl folosește și data nașterii, la
            înregistrare.

            „min" e ziua evenimentului dacă ea a trecut deja: altfel browserul
            ar arăta ca greșită o dată pe care omul n-a atins-o. Verificarea
            adevărată e oricum pe server.
          -->
          <?php
          $dataId      = 'ev-data';
          $dataNume    = 'data_eveniment';
          $dataText    = 'Data';
          $dataStea    = true;
          $dataValoare = $val('data_eveniment') ?: '';
          $dataMin     = min(date('Y-m-d'), $val('data_eveniment', date('Y-m-d')));
          $dataMax     = date('Y-m-d', strtotime('+' . ANI_INAINTE_MAX . ' years'));
          require __DIR__ . '/inc/camp-data.php';
          ?>

          <div class="field-row">
            <div class="field">
              <label for="ev-ora-inceput" data-vorba-joc="Ora"><span data-vorba>Ora de început</span> <span class="req" aria-hidden="true">*</span></label>
              <!--
                Câmp de text, nu `type="time"`.

                Ceasul browserului se scrie cu AM/PM sau fără după limba în
                care e pus browserul, nu după limba paginii: `lang="ro"` pe
                input n-are niciun efect, am încercat. Un om cu Chrome în
                engleză ar fi văzut „07:30 PM" pe un site românesc. Aici
                scriem noi ora, deci e mereu de 24 de ore.

                Serverul cere oricum exact HH:MM (vezi verificaEveniment),
                iar `pattern` spune același lucru și browserului.
              -->
              <input type="text" id="ev-ora-inceput" name="ora_inceput" required
                     class="camp-ora" inputmode="numeric" autocomplete="off"
                     maxlength="5" placeholder="19:30"
                     pattern="([01][0-9]|2[0-3]):[0-5][0-9]"
                     value="<?= h(oraScurta($val('ora_inceput') ?: null)) ?>"
                     aria-describedby="err-ev-ora-inceput">
              <p class="field__error" id="err-ev-ora-inceput" hidden></p>
            </div>

            <!-- La o vânătoare nu există „până la": ora de mai sus E capătul. -->
            <div class="field" data-fara-joc>
              <label for="ev-ora-sfarsit">Ora de sfârșit</label>
              <input type="text" id="ev-ora-sfarsit" name="ora_sfarsit"
                     class="camp-ora" inputmode="numeric" autocomplete="off"
                     maxlength="5" placeholder="22:00"
                     pattern="([01][0-9]|2[0-3]):[0-5][0-9]"
                     value="<?= h(oraScurta($val('ora_sfarsit') ?: null)) ?>"
                     aria-describedby="err-ev-ora-sfarsit">
              <!-- Bifa stă sub câmpul pe care îl stinge, nu sub tot rândul:
                   altfel nu se vede din prima la care dintre ore se referă. -->
              <label class="check check--mic">
                <input type="checkbox" id="ev-fara-sfarsit" name="fara_ora_sfarsit" value="1"
                       <?= $faraOraSfarsit ? 'checked' : '' ?>>
                <span>Fără oră de sfârșit!</span>
              </label>
              <p class="field__error" id="err-ev-ora-sfarsit" hidden></p>
            </div>
          </div>
        </section>

        <!-- -------------------- Cine și cât --------------------- -->
        <!-- Tot chenarul pleacă la o vânătoare: acolo nu se înscrie nimeni, nu
             se ține nicio listă și nu costă nimic. Vezi lămurirea de la „Când". -->
        <section class="card-set" data-fara-joc>
          <h2 class="card-set__titlu">Cine poate veni și cât costă?</h2>

          <div class="field">
            <label class="check">
              <input type="checkbox" id="ev-gratuit" name="gratuit" value="1"
                     <?= $eGratuit ? 'checked' : '' ?>>
              <span>Intrarea e gratuită</span>
            </label>
          </div>

          <div class="field" id="ev-cost-camp" <?= $eGratuit ? 'hidden' : '' ?>>
            <label for="ev-cost">Cât costă, de persoană (lei)</label>
            <!-- „25.00" din bază se arată „25", iar „45.50" se arată „45.5":
                 zecimalele apar doar când există. Trecerea prin float face
                 asta singură — tăiatul zerourilor din coadă cu rtrim() ar fi
                 mers pentru „25.00", dar ar fi făcut din „500" un „5".
                 Virgula sau punctul, cum îi vine omului, le primește oricum
                 verificarea de pe server. -->
            <input type="text" id="ev-cost" name="cost" inputmode="decimal"
                   value="<?= h($eGratuit ? '' : (string) (float) $val('cost')) ?>"
                   placeholder="25" aria-describedby="err-ev-cost">
            <p class="field__error" id="err-ev-cost" hidden></p>
          </div>

          <div class="field-row">
            <div class="field">
              <label for="ev-varsta">Vârstă minimă</label>
              <?php /* Din $sursa, ca toate celelalte câmpuri: la editare e
                        evenimentul care se schimbă, la refacere cel din care
                        se copiază. Un număr care nu e printre cele trei de mai
                        jos (pus de mână, din phpMyAdmin) cade pe
                        „Nespecificată" — lista rămâne cea a formularului. */
                    $varstaAcum = $sursa !== null && ($sursa['varsta_minima'] ?? null) !== null
                    ? (string) (int) $sursa['varsta_minima'] : 'nespecificat'; ?>
              <select id="ev-varsta" name="varsta_minima" aria-describedby="err-ev-varsta">
                <?php foreach ([
                    'nespecificat' => 'Nespecificată',
                    '13' => '13+', '16' => '16+', '18' => '18+',
                ] as $valoare => $eticheta): ?>
                <option value="<?= h((string) $valoare) ?>"
                        <?= (string) $valoare === $varstaAcum ? 'selected' : '' ?>><?= h($eticheta) ?></option>
                <?php endforeach; ?>
              </select>
              <p class="field__error" id="err-ev-varsta" hidden></p>
            </div>

            <div class="field">
              <label for="ev-gen">Pentru cine e</label>
              <?php $genAcum = $val('gen_participanti', 'nespecificat'); ?>
              <select id="ev-gen" name="gen_participanti" aria-describedby="err-ev-gen">
                <?php foreach ([
                    'nespecificat' => 'Oricine poate veni',
                    'barbati'      => 'Doar bărbați',
                    'femei'        => 'Doar femei',
                ] as $valoare => $eticheta): ?>
                <option value="<?= h($valoare) ?>"
                        <?= $valoare === $genAcum ? 'selected' : '' ?>><?= h($eticheta) ?></option>
                <?php endforeach; ?>
              </select>
              <p class="field__error" id="err-ev-gen" hidden></p>
            </div>
          </div>

          <div class="field-row">
            <div class="field">
              <label for="ev-min">Participanți minim</label>
              <p class="field__hint-sus">Sub câți oameni evenimentul nu poate începe.</p>
              <input type="text" id="ev-min" name="participanti_min" inputmode="numeric"
                     value="<?= h($faraMinim ? '' : $val('participanti_min')) ?>"
                     placeholder="10" aria-describedby="err-ev-min">
              <label class="check check--mic">
                <input type="checkbox" id="ev-fara-min" name="fara_participanti_min" value="1"
                       <?= $faraMinim ? 'checked' : '' ?>>
                <span>Nespecificat</span>
              </label>
              <p class="field__error" id="err-ev-min" hidden></p>
            </div>

            <div class="field">
              <label for="ev-max">Participanți maxim</label>
              <p class="field__hint-sus">Câți încap, dacă există o limită.</p>
              <input type="text" id="ev-max" name="participanti_max" inputmode="numeric"
                     value="<?= h($faraMaxim ? '' : $val('participanti_max')) ?>"
                     placeholder="50" aria-describedby="err-ev-max">
              <label class="check check--mic">
                <input type="checkbox" id="ev-fara-max" name="fara_participanti_max" value="1"
                       <?= $faraMaxim ? 'checked' : '' ?>>
                <span>Nespecificat</span>
              </label>
              <p class="field__error" id="err-ev-max" hidden></p>
            </div>
          </div>
        </section>

        <!-- --------------------- Descrierea --------------------- -->
        <section class="card-set">
          <h2 class="card-set__titlu">Detalii</h2>
          <p class="card-set__lead">
            Scrie ca și cum ai povesti unui prieten: ce se întâmplă, de ce merită
            venit, ce să-și ia cu el. Cel puțin <?= DESCRIERE_MIN ?> de caractere.
          </p>

          <div class="field">
            <label for="ev-descriere">Descrierea evenimentului <span class="req" aria-hidden="true">*</span></label>
            <!--
              Limitele pleacă spre JS ca date, nu scrise a doua oară acolo.
              `maxlength` lipsește dinadins: el numără în unități UTF-16, deci
              ar fi tăiat un text cu emoji cu mult înainte de limita ținută de
              server. Oprirea o face main.js, numărând caractere.
            -->
            <textarea id="ev-descriere" name="descriere" rows="10"
                      data-min="<?= DESCRIERE_MIN ?>" data-max="<?= DESCRIERE_MAX ?>"
                      placeholder="Salutare! Am primit cadou un set nou de Monopoly dar momentan nu am cu cine să mă joc. Dacă sunt interesați ..."
                      required aria-describedby="err-ev-descriere ev-numar"><?= h($val('descriere')) ?></textarea>
            <!-- „din minim 300", nu „din 300": 300 e pragul de la care se
                 poate trimite, nu o cotă de umplut. Scris fără „minim", un
                 contor care arăta „633 din 300" părea o greșeală. -->
            <p class="field__hint contor-caractere" id="ev-numar" role="status">0 din minim <?= DESCRIERE_MIN ?> caractere</p>
            <p class="field__error" id="err-ev-descriere" hidden></p>
          </div>
        </section>

        <?php if ($eStaff): ?>
        <!--
          Numai pentru oamenii de casă, și numai fiindcă numai ei au ce pune
          în numele orașului. Bifa nu se desenează pentru ceilalți — nu stinsă,
          ci deloc: o casetă pe care n-o poți apăsa e o întrebare pusă degeaba.
          Cererea lor nici n-ar fi ascultată, oricât ar scrie în ea (vezi
          ascundePeProfil din inc/evenimente.php).

          Stă între „Detalii" și butoane, dinadins: e ultimul lucru de hotărât
          înainte de apăsare, nu unul dintre câmpurile anunțului.
        -->
        <div class="field ev-ascunde">
          <label class="check">
            <input type="checkbox" id="ev-ascuns" name="ascuns_pe_profil" value="1"
                   <?= $ascunsPeProfil ? 'checked' : '' ?>>
            <span>Nu arăta evenimentul pe profilul meu, la „Ieșiri organizate".</span>
          </label>
          <p class="field__hint">
            Anunțul rămâne întreg pe site — pe prima pagină, în căutare și pe
            pagina lui. Doar de pe profilul tău lipsește.
          </p>
        </div>
        <?php endif; ?>

        <!--
          Previzualizarea trece prin aceleași verificări ca trimiterea; dacă
          ceva nu e în regulă, erorile apar aici, pe formular, și fila nouă nu
          se mai deschide. De aceea e un buton obișnuit, nu un formular cu
          target="_blank": acela ar deschide fila înainte să știe dacă are ce
          arăta. Vezi secțiunea 11 din main.js, la „previzualizarea".
        -->
        <div class="ev-butoane">
          <button class="btn btn--ghost btn--block" type="button" id="ev-previzualizeaza">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <span>Previzualizează</span>
          </button>
          <!-- Pentru staff nu mai e o cerere, e o publicare: anunțul apare pe
               site în clipa apăsării. Vezi starePentruPublicare(). -->
          <button class="btn btn--primary btn--block" type="submit"><?=
            $eStaff ? 'Publică evenimentul' : 'Trimite spre aprobare'
          ?></button>
        </div>

        <!-- Portița pentru browserele care nu lasă o filă să se deschidă
             dintr-un răspuns venit mai târziu. Stă ascunsă până e nevoie —
             ascuns e paragraful întreg, nu doar linkul din el: un <p> gol tot
             ocupă un rând și împingea linia de despărțire de mai jos. -->
        <p class="ev-previz-link" id="ev-previz-rand" hidden>
          <a id="ev-previz-link" href="#" target="_blank" rel="noopener">Deschide previzualizarea</a>
        </p>

        <?php if ($eEditare && poateFiAnulat($ev)): ?>
        <!--
          Anularea evenimentului. Stă despărțită de restul formularului și în
          roșu stins, ca zona de ștergere a contului din setări: e singura
          apăsare de pe pagina asta care nu se poate lua înapoi.

          HTML-ul vine din randeazaZonaAnulare() (inc/afisare-eveniment.php),
          fiindcă aceeași zonă stă și pe pagina evenimentului, sub caseta de
          interes. Un singur loc care o desenează, două pagini care o cer.

          `poateFiAnulat($ev)` e, de fapt, mereu adevărat pe pagina asta: ca să
          se deschidă, evenimentul trebuie să nu fi început (poateFiEditat),
          iar ce n-a început se poate anula. Întrebarea rămâne scrisă fiindcă
          zona ASTA despre anulare e, și nu vrem să atârne de un lucru
          adevărat din altă parte — cele două reguli se pot despărți mâine.

          Cât ține ceasul de anulare după început se vede acum doar pe pagina
          evenimentului: acolo se apasă butonul, tot acolo se citește și
          numărul celor care au spus că vin.
        -->
        <?= randeazaZonaAnulare($ev, $csrf) ?>
        <?php endif; ?>
      </form>
    </div>

    <!-- ==================== MESAJUL DE DUPĂ ======================== -->
    <div class="card-set" id="ev-done" hidden>
      <div class="done done--ok" tabindex="-1">
        <span class="done__ico" aria-hidden="true">
          <svg class="ico" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
          </svg>
        </span>
        <h2 class="done__title"><?=
          $eStaff ? 'Evenimentul a fost publicat' : 'Evenimentul tău a fost trimis spre aprobare'
        ?></h2>
        <!-- Fără termen promis: nu există încă nimeni care să apese „aprobă",
             iar un „în aceeași zi" scris aici ar fi o vorbă goală. -->
        <p class="done__text"><?=
          $eStaff ? 'Se vede de acum pe site, fără să mai treacă pe la nimeni.'
                  : 'Îl citim și, dacă e totul în regulă, apare pe site.'
        ?></p>
        <div class="done__actions">
          <!--
            Cel mai firesc lucru după trimitere e să te uiți cum a ieșit, nu
            să te întorci pe prima pagină. La editare știm adresa de pe acum;
            la un eveniment nou o aflăm din răspunsul serverului, fiindcă
            slugul se naște abia la salvare — de-aia „href" pleacă gol și îl
            umple main.js. Fără el, butonul nu se arată deloc.
          -->
          <a class="btn btn--primary" id="ev-done-link"
             href="<?= $eEditare ? h(urlEveniment((string) $ev['slug'])) : '' ?>"
             <?= $eEditare ? '' : 'hidden' ?>>Vezi pagina evenimentului</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
