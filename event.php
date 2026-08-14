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
require_once __DIR__ . '/inc/interese.php';
require_once __DIR__ . '/inc/comentarii.php';

$slug = trim((string) ($_GET['slug'] ?? ''));

/**
 * Un eveniment publicat se vede de oricine, fără cont.
 *
 * A fost o vreme închisă și pagina asta, ca profilurile. Dar un anunț public
 * are altă treabă decât un profil: e făcut ca să fie dat mai departe, pus pe
 * Facebook, trimis pe WhatsApp. O ușă la intrare l-ar fi oprit tocmai pe cel
 * căruia i s-a trimis linkul, și l-ar fi ținut și în afara căutărilor Google.
 *
 * Restricționată rămâne INTERACȚIUNEA, nu privitul: butoanele de mai jos duc
 * spre login la apăsare, iar api/interes.php cere cont oricum.
 *
 * $membru poate fi null de aici încolo. Tot ce urmează îl citește cu grijă,
 * iar $membruId e 0 pentru cine nu e conectat — un id peste care nu nimerește
 * niciun rând din bază.
 */
$membru   = membruCurent();
$membruId = (int) ($membru['id'] ?? 0);

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

if ($eveniment === null || !poateVedeaEvenimentul($eveniment, $membruId, $eStaff)) {
    header('Location: index.php');
    exit;
}

$eOrganizatorul = $membruId > 0 && (int) $eveniment['membru_id'] === $membruId;
/**
 * „Publicat" înseamnă aprobat SAU încheiat: două stări, o singură purtare față
 * de lume. Un eveniment încheiat nu se ascunde — a avut loc, iar pagina lui
 * rămâne de citit și de trimis mai departe.
 */
$ePublicat      = evenimentPublicat($eveniment);
$eAnulat        = $eveniment['stare_moderare'] === 'anulat';

/**
 * Ziua lui a trecut.
 *
 * Nu-l ascunde și nu-l închide: un eveniment de acum două luni rămâne o
 * pagină bună de citit și de trimis mai departe. Se schimbă doar ce se poate
 * face pe ea — nimeni nu se mai poate înscrie la ceva ce s-a terminat.
 *
 * Aceeași regulă ca la limita de un eveniment activ, prin aceeași funcție.
 */
$eIncheiat = evenimentIncheiat($eveniment);

/**
 * Poate organizatorul să-l încheie chiar acum?
 *
 * Doar după ce a început — ziua ȘI ora. Un eveniment care nu s-a petrecut încă
 * nu se „încheie": ce vrea omul atunci se cheamă anulare, are butonul lui în
 * formularul de editare și cere un motiv, fiindcă oamenii înscriși trebuie
 * înștiințați. Încheierea nu spune nimănui nimic, tocmai fiindcă evenimentul
 * a avut loc.
 */
$poateIncheia = $eOrganizatorul && !$eIncheiat && evenimentAInceput($eveniment);

/* ------------------------ „Mergi la acest eveniment?" ------------------ */

/**
 * Cine s-a adunat în jurul evenimentului, și unde stă omul care se uită.
 *
 * Se citește o dată, aici, și se folosește în toată secțiunea de mai jos.
 * Numerele astea sunt o veste, nu o rezervare: între încărcarea paginii și
 * apăsare pot intra alții, de aceea locurile se numără din nou în
 * api/interes.php, în clipa apăsării.
 */
$evenimentId  = (int) $eveniment['id'];
$numarInterese = numaraInterese($evenimentId);
$stareaMea     = interesulMeu($evenimentId, $membruId);
$maiSuntLocuri = maiSuntLocuri($eveniment, $numarInterese['participant']);

/**
 * Numărul de telefon i se cere doar cui nu l-a dat încă — și niciodată
 * organizatorului: al lui e, n-are cui să și-l dea.
 */
$eLogat         = $membru !== null;
$imiCereTelefon = $eLogat && !$eOrganizatorul && telefonulMembrului($membruId) === '';

/**
 * Cine poate da pe cineva jos de pe lista de participanți.
 *
 * Organizatorul, fiindcă locurile sunt ale lui, și staff-ul, ca la comentarii.
 * Nu și la un eveniment neaprobat sau încheiat: acolo n-are ce curăța nimeni —
 * la primul nu s-a înscris nimeni, iar listele celui de-al doilea sunt istorie,
 * nu o socoteală deschisă.
 *
 * Aici se hotărăște doar dacă se DESENEAZĂ butoanele. Regula adevărată e în
 * api/exclude-participant.php, care întreabă din nou tot ce se întreabă aici.
 */
$poateScoateParticipanti = ($eOrganizatorul || $eStaff) && $ePublicat && !$eIncheiat;

/* ------------------------------ Discuția ------------------------------ */

/**
 * Comentariile, toate deodată.
 *
 * Toate, până la ultimul: ascunsul e treaba lui main.js, care lasă la vedere
 * primele COMENTARII_DEODATA și le arată pe celelalte la apăsarea butonului,
 * fără să mai întrebe serverul. Aici discuția e scurtă — zeci de rânduri, nu
 * mii — iar în schimbul câtorva kiloocteți în plus se câștigă un buton care
 * răspunde pe loc, o pagină care se poate căuta cu Ctrl+F întreagă și
 * comentarii pe care le vede și Google.
 *
 * Se scrie sub un eveniment publicat, fie el și încheiat. Listele de
 * participanți se închid odată cu evenimentul, discuția nu: acolo se închide o
 * socoteală, aici oamenii spun cum a fost — și asta se întâmplă mai ales după.
 */
$randuriComentarii = comentariileEvenimentului($evenimentId, $membruId);
$fireComentarii    = grupeazaComentarii($randuriComentarii);
$cateComentarii    = 0;

foreach ($randuriComentarii as $randComentariu) {
    // Cele golite nu se numără: pe ecran sunt o piatră de mormânt, nu o vorbă.
    $cateComentarii += (int) $randComentariu['sters'] === 1 ? 0 : 1;
}

/**
 * „poate_scrie" nu întreabă dacă omul e conectat, ci dacă locul e deschis.
 *
 * Butonul „Răspunde" se vede și de vizitator, ca și cel de apreciere: apăsat,
 * îl duce la intrare, cu întoarcere fix aici. Ascuns, i-ar fi arătat o
 * discuție la care pare că n-are cum să ia parte.
 */
$contextComentarii = [
    'organizator_id' => (int) $eveniment['membru_id'],
    'membru_id'      => $membruId,
    'e_staff'        => $eStaff,
    'poate_scrie'    => $ePublicat,
    'nume'           => numeleComentatorilor($randuriComentarii),
];

/* --------------------------- ce se afișează --------------------------- */

// Coperta, orele, numele organizatorului — toate se pregătesc în
// evenimentDinBaza(), din inc/afisare-eveniment.php. Aici rămâne doar ce ține
// de pagină: titlul din bara browserului și dacă se lasă indexată.
$titlu     = $eveniment['titlu'] . ' — PulsulOrasului.Ro';
$descriere = inceputDeText((string) $eveniment['descriere'], 155);

// Cât timp nu e aprobat, n-are ce căuta în motoarele de căutare.
$noindex   = !$ePublicat;

/* --------------------- Cartonașul de pe WhatsApp ---------------------- */

/**
 * Ce se vede când cineva pune linkul evenimentului într-o conversație.
 *
 * Titlul și descrierea le aveam deja. Lipsea poza: fără `og:image`, WhatsApp
 * arată doar două rânduri de text, iar un anunț fără coperta lui nu spune
 * mare lucru.
 *
 * Adresa pozei trebuie să fie ÎNTREAGĂ. WhatsApp și Facebook nu se uită la
 * pagină din browserul omului: o cer ele, de pe alt server, iar o cale de
 * forma „assets/img/…" n-are acolo față de ce să se socotească.
 */
$ogTitlu = (string) $eveniment['titlu'];
$ogUrl   = urlIntreg(urlEveniment((string) $eveniment['slug']));
$ogTip   = 'article';

// Fără etichete: în bază intră text curat, dar dacă cineva a scris „<b>" cu
// mâna lui, n-are ce căuta în cartonaș.
$ogDescriere = inceputDeText(strip_tags((string) $eveniment['descriere']), 180);

/**
 * Coperta lui, iar dacă n-are, imaginea categoriei.
 *
 * Se verifică pe disc, nu doar în bază: coloana `categorii.imagine_default`
 * există de mult, fișierele nu s-au urcat încă (vezi roadmapul din CLAUDE.md).
 * O adresă care duce la 404 e mai rea decât niciuna — WhatsApp ar încerca s-o
 * ia, n-ar găsi-o, și ar arăta un cartonaș ciuntit în loc de unul curat.
 */
$ogImagine = urlCoperta($eveniment['coperta'] ?? null);

if ($ogImagine === '' && !empty($eveniment['imagine_default'])) {
    $caleCategorie = 'assets/img/categorii/' . $eveniment['imagine_default'];

    if (is_file(__DIR__ . '/' . $caleCategorie)) {
        $ogImagine = $caleCategorie;
    }
}

$ogImagine = urlIntreg($ogImagine);

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
        } elseif (!$ePublicat) {
            $banda = $eveniment['stare_moderare'] === 'respins'
                ? ['fel' => 'respins',   'text' => 'Anunțul nu a trecut de verificare. Îl vezi doar tu.']
                : ['fel' => 'asteptare', 'text' => 'În așteptare de aprobare. Îl vezi doar tu, până îl citim.'];
        } elseif ($eIncheiat) {
            /**
             * Trecut, nu greșit. De aceea banda e cenușie, nu galbenă și nici
             * roșie: culorile alea sunt pentru ce n-a mers bine, iar aici n-a
             * greșit nimeni — doar a trecut ziua.
             */
            $banda = [
                'fel'  => 'incheiat',
                'text' => 'Acest eveniment s-a încheiat.',
            ];
        }

        // Butonul „Editează" dispare la anulare și la încheiere: nu mai e
        // nimic de corectat, iar evenimentDeEditat() oricum nu l-ar mai
        // deschide.
        $poateEdita = $eOrganizatorul && !$eAnulat && !$eIncheiat;

        /**
         * Butoanele organizatorului, sus, lângă numele lui.
         *
         * „Editează" și „Încheie evenimentul" sunt amândouă ale lui și se
         * exclud rareori: cel care poate încheia (a început) poate de obicei
         * și edita. Stau împreună, unde omul se uită întâi, nu una sus și una
         * pe la mijlocul paginii, printre iconițele de distribuire — alea sunt
         * pentru oricine.
         */
        afiseazaEveniment(evenimentDinBaza($eveniment), $banda,
          ($poateEdita || $poateIncheia)
            ? function () use ($eveniment, $poateEdita, $poateIncheia) {
            ?>
            <div class="post__actiuni">
              <?php if ($poateEdita): ?>
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
              <?php endif; ?>

              <?php if ($poateIncheia): ?>
              <!--
                „Încheie evenimentul" — doar pentru cel care l-a pus la cale,
                doar cât mai e ceva de încheiat, și doar DUPĂ ce a început. Un
                eveniment se termină oricum singur a doua zi după data lui;
                butonul ăsta e pentru când se termină mai devreme: s-au ocupat
                locurile, s-a stricat vremea la jumătate, s-a strâns lumea și
                gata.
              -->
              <button class="btn btn--ghost btn--sm post__incheie" type="button" id="ev-incheie"
                      data-slug="<?= h((string) $eveniment['slug']) ?>"
                      data-csrf="<?= h(tokenCsrf()) ?>"
                      aria-expanded="false" aria-controls="ev-incheie-sigur">
                <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                  <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
                </svg>
                <span>Încheie evenimentul</span>
              </button>

              <!--
                Confirmarea, desenată de noi, ca la anulare: o fereastră a
                browserului arată altfel pe Windows, pe Android și pe iPhone.
                Se deschide chiar sub buton, ca întrebarea să fie lângă mâna
                care a apăsat.

                Nu e roșie — încheierea nu e o pierdere, e un lucru firesc la
                capătul unui eveniment. Dar tot se cere o apăsare în plus: nu
                se poate lua înapoi, iar butonul stă lângă altele pe care
                oricine le apasă din curiozitate.
              -->
              <div class="incheiere-confirm" id="ev-incheie-sigur" hidden>
                <p class="incheiere-confirm__titlu">
                  <strong>Sigur vrei să încheii evenimentul?</strong>
                </p>
                <p class="incheiere-confirm__text">
                  Anunțul rămâne pe site și se poate citi mai departe, dar
                  nimeni nu se mai poate înscrie, iar tu vei putea publica un
                  eveniment nou. Nu se mai poate lua înapoi.
                </p>

                <div class="incheiere-confirm__actiuni">
                  <button class="btn btn--primary btn--sm" type="button" id="ev-incheie-da">Da, încheie</button>
                  <button class="btn btn--ghost btn--sm" type="button" id="ev-incheie-nu">Renunță</button>
                </div>
              </div>
              <?php endif; ?>
            </div>
            <?php
        } : null);
      ?>

      <?php if ($ePublicat): ?>
      <!-- ========================== DISTRIBUIRE ===========================
        Trei iconițe, între detalii și „Mergi la acest eveniment?". Aceleași
        desene ca cele scoase odinioară de lângă organizator — acolo erau
        lipite de numele cuiva, aici sunt la locul lor: după ce omul a citit
        despre ce e vorba și înainte să hotărască dacă vine.

        Numai la un eveniment publicat: n-are rost să dai mai departe un anunț
        pe care nu-l poate deschide nimeni.

        Adresa se scrie ÎNTREAGĂ, cu url_site din config. Facebook și WhatsApp
        primesc un link, nu o cale — „event.php?slug=…" singur n-ar duce
        nicăieri de pe telefonul altcuiva.
      ================================================================== -->
      <?php
        $adresaEveniment = rtrim((string) ($config['url_site'] ?? ''), '/')
                         . '/' . urlEveniment((string) $eveniment['slug']);

        // Textul care pleacă pe WhatsApp și în clipboard. Scurt dinadins:
        // pe WhatsApp intră în căsuța de scris, iar omul îl termină cum vrea.
        $textDistribuire = 'Uite ce eveniment am găsit: ' . $eveniment['titlu'];
      ?>
      <div class="post__share" role="group" aria-label="Distribuie evenimentul">
        <a class="icon-btn" target="_blank" rel="noopener noreferrer"
           href="https://www.facebook.com/sharer/sharer.php?u=<?= h(urlencode($adresaEveniment)) ?>"
           aria-label="Distribuie pe Facebook" title="Distribuie pe Facebook">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8.5V7a1.5 1.5 0 0 1 1.5-1.5H17V3h-2.2A3.8 3.8 0 0 0 11 6.8v1.7H9V11h2v10h3V11h2.2l.4-2.5H14Z" fill="currentColor" stroke="none"/></svg>
        </a>

        <a class="icon-btn" target="_blank" rel="noopener noreferrer"
           href="https://wa.me/?text=<?= h(urlencode($textDistribuire . ' ' . $adresaEveniment)) ?>"
           aria-label="Trimite pe WhatsApp" title="Trimite pe WhatsApp">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-11.9 7L4 20l1.6-4A8 8 0 1 1 20 11.5Z"/><path d="M9 9.5c.4 2 1.6 3.2 3.6 3.6l.9-1.2 1.7.8-.4 1.4c-2.9.3-5.8-2.6-5.5-5.5l1.4-.4.8 1.7z" fill="currentColor" stroke="none"/></svg>
        </a>

        <!--
          Copierea o face JS. Textul stă într-un atribut, nu se lipește în JS
          din bucăți: aici e escapat de h(), iar un titlu cu ghilimele sau cu
          „&" nu poate strica nimic.
        -->
        <button class="icon-btn" type="button" id="copiaza-link"
                data-copiaza="<?= h($textDistribuire . ' ' . $adresaEveniment) ?>"
                aria-label="Copiază linkul" title="Copiază linkul">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3A4 4 0 0 0 13 5.3l-1.4 1.4"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3A4 4 0 0 0 11 18.7l1.4-1.4"/></svg>
        </button>
      </div>

      <!-- =========================== PARTICIPARE ==========================
        Numai la un eveniment publicat. Cât e în așteptare, respins sau
        anulat, pagina se deschide doar pentru organizator sau pentru staff —
        și n-are rost o listă de participanți la ceva ce nu se vede pe site.

        `aria-pressed` spune în ce stare e omul chiar acum; JS îl schimbă după
        fiecare apăsare, iar la reîncărcare vine de la server. Retragerea n-are
        buton al ei: apăsarea pe starea în care ești deja o stinge.
      ================================================================== -->
      <!--
        Tokenul CSRF se scrie DOAR pentru cine e conectat: un vizitator n-are
        ce face cu el, fiindcă butoanele îl duc la login înainte de orice
        cerere. (Sesiunea tot se deschide — membruCurent() o cere pe fiecare
        pagină, ca peste tot pe site — dar un token în plus în HTML, pentru
        cineva care nu-l poate folosi, n-are de ce să existe.)
      -->
      <section class="rsvp" id="rsvp" aria-labelledby="rsvp-title"
               data-slug="<?= h((string) $eveniment['slug']) ?>"
               <?= $eIncheiat ? 'data-incheiat="1"' : '' ?>
               <?= $eLogat ? 'data-csrf="' . h(tokenCsrf()) . '"' : '' ?>>
        <div class="rsvp__head">
          <!-- La un eveniment trecut întrebarea se pune la trecut: „Mergi?"
               deasupra unei liste de oameni care au fost deja sună a invitație
               la ceva ce s-a terminat. -->
          <h2 id="rsvp-title"><?= $eIncheiat ? 'Cine a fost la acest eveniment?' : 'Mergi la acest eveniment?' ?></h2>
          <?php if ($eIncheiat): ?>
          <p>A trecut. Mai jos e cine a fost pe listă.</p>
          <?php else: ?>
          <p>Spune-le și celorlalți — apari în lista de mai jos.</p>
          <?php endif; ?>
        </div>

        <div class="rsvp__actions">
          <!-- La un eveniment încheiat, amândouă butoanele se sting: numerele
               rămân de citit, dar nu se mai intră și nu se mai iese de pe
               listă. Oprirea adevărată e în api/interes.php. -->
          <button class="rsvp__btn rsvp__btn--interested" type="button"
                  id="btn-interested" data-rsvp="interesat"
                  aria-pressed="<?= $stareaMea === 'interesat' ? 'true' : 'false' ?>"
                  <?= $eIncheiat ? 'disabled' : '' ?>>
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path d="m12 3.8 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 10l5.9-.9L12 3.8Z"/>
            </svg>
            <!-- La un eveniment trecut butoanele nu mai cer nimic, ci spun ce
                 numără: „Mă interesează 12" sub un anunț de acum trei luni
                 sună a invitație la ceva ce nu se mai poate. -->
            <span class="rsvp__label"><?= $eIncheiat ? 'Cine a fost interesat' : 'Mă interesează' ?></span>
            <span class="rsvp__count" data-count-for="interesat"><?= (int) $numarInterese['interesat'] ?></span>
          </button>

          <!--
            Butonul de participare se stinge când s-au ocupat toate locurile —
            dar numai pentru cine nu e deja înăuntru: cel care e pe listă
            trebuie să se poată retrage oricând. Oprirea adevărată e pe server,
            unde locurile se numără din nou în clipa apăsării.
          -->
          <button class="rsvp__btn rsvp__btn--going" type="button"
                  id="btn-going" data-rsvp="participant"
                  aria-pressed="<?= $stareaMea === 'participant' ? 'true' : 'false' ?>"
                  <?= ($eIncheiat || (!$maiSuntLocuri && $stareaMea !== 'participant')) ? 'disabled' : '' ?>>
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
            </svg>
            <span class="rsvp__label"><?= $eIncheiat ? 'Cine a participat' : 'Voi participa' ?></span>
            <span class="rsvp__count" data-count-for="participant"><?= (int) $numarInterese['participant'] ?></span>
          </button>
        </div>

        <?php if (!$eIncheiat && !$maiSuntLocuri && $stareaMea !== 'participant'): ?>
        <p class="rsvp__plin">Nu mai sunt locuri disponibile la acest eveniment.</p>
        <?php endif; ?>

        <!-- ------------------- confirmarea participării -------------------
          „Mă interesează" e o însemnare; „voi participa" e o hotărâre care
          dă datele omului mai departe. De aceea treapta asta există, și de
          aceea spune pe față ce se întâmplă înainte, nu după.

          Se deschide din JS și se verifică din nou pe server: fără
          `confirmat`, api/interes.php nu scrie nimic.
        -->
        <?php if ($eLogat && !$eIncheiat): ?>
        <div class="rsvp__confirm" id="rsvp-confirm" hidden>
          <p class="rsvp__confirm-titlu"><strong>Confirmi participarea?</strong></p>

          <p class="rsvp__confirm-text">
            Numele tău complet și numărul de telefon vor fi văzute de
            organizator, care te poate suna sau scrie pe WhatsApp ca să
            reconfirme înainte de eveniment.
          </p>

          <p class="rsvp__confirm-text">
            Confirmând, spui că ai citit și ești de acord cu
            <a href="#">Termenii și condițiile</a> platformei.
          </p>

          <?php if ($imiCereTelefon): ?>
          <!--
            Numărul se cere o singură dată: la confirmare se salvează în cont,
            ca la setări, cu aceeași verificare. A doua oară nu se mai întreabă.
          -->
          <div class="field">
            <label for="rsvp-telefon">Numărul tău de telefon <span class="req" aria-hidden="true">*</span></label>
            <input type="tel" id="rsvp-telefon" name="telefon" inputmode="tel"
                   autocomplete="tel" maxlength="20" placeholder="0722334455"
                   aria-describedby="err-rsvp-telefon rsvp-telefon-hint">
            <p class="field__hint" id="rsvp-telefon-hint">
              Se salvează în contul tău, ca să nu-l mai scrii data viitoare.
              Îl poți schimba oricând din <a href="setari.php">setări</a>.
            </p>
            <p class="field__error" id="err-rsvp-telefon" hidden></p>
          </div>
          <?php endif; ?>

          <div class="rsvp__confirm-actiuni">
            <button class="btn btn--primary" type="button" id="rsvp-confirm-da">Da, particip</button>
            <button class="btn btn--ghost" type="button" id="rsvp-confirm-nu">Renunță</button>
          </div>
        </div>
        <?php endif; ?>

        <!--
          Chipurile și vorba de dedesubt se desenează într-un singur loc,
          randeazaOameniInteresati() din inc/interese.php — de acolo vin și la
          încărcarea paginii, și după fiecare apăsare, prin api/interes.php.
          Scrise în două locuri, ar fi început să difere de la prima corectură.
        -->
        <div class="rsvp__people" id="rsvp-people">
          <?= randeazaOameniInteresati($evenimentId, $eIncheiat) ?>
        </div>
      </section>
      <?php endif; ?>

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
            <span class="tab__count" data-count-for="comentarii"><?= $cateComentarii ?></span>
          </button>

          <button class="tab" type="button" role="tab" id="tab-interested"
                  aria-controls="panel-interested" aria-selected="false" tabindex="-1">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path d="m12 3.8 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 10l5.9-.9L12 3.8Z"/>
            </svg>
            <span>Interesați</span>
            <span class="tab__count" data-count-for="interesat"><?= (int) $numarInterese['interesat'] ?></span>
          </button>

          <button class="tab" type="button" role="tab" id="tab-going"
                  aria-controls="panel-going" aria-selected="false" tabindex="-1">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
            </svg>
            <span>Participă</span>
            <span class="tab__count" data-count-for="participant"><?= (int) $numarInterese['participant'] ?></span>
          </button>
        </div>

        <!-- ------------------------ PANOU: COMENTARII --------------------- -->
        <!--
          Tokenul CSRF se scrie DOAR pentru cine e conectat, ca la secțiunea de
          participare: un vizitator n-are ce face cu el, fiindcă vede o
          invitație la intrare în locul casetei de scris.

          `data-deodata` vine din COMENTARII_DEODATA (inc/comentarii.php), ca
          numărul să fie scris într-un singur loc și schimbat într-unul singur.
        -->
        <div class="panel is-active" id="panel-comments" role="tabpanel"
             aria-labelledby="tab-comments" tabindex="0"
             data-comentarii
             data-slug="<?= h((string) $eveniment['slug']) ?>"
             data-deodata="<?= COMENTARII_DEODATA ?>"
             <?= $eLogat ? 'data-csrf="' . h(tokenCsrf()) . '"' : '' ?>>

          <?php if ($eLogat && $ePublicat): ?>
          <!--
            Formularul de comentariu nou. Numai pentru cine e conectat: mai jos
            e varianta pentru vizitatori, care nu are unde să scrie, ci unde să
            intre în cont.
          -->
          <form class="comment-form" data-comment-form>
            <img class="comment-form__avatar" src="<?= h(urlPoza($membru['poza'] ?? null, true)) ?>"
                 alt="" width="96" height="96">
            <div class="comment-form__main">
              <label class="sr-only" for="new-comment">Scrie un comentariu</label>
              <textarea id="new-comment" name="text" rows="3" maxlength="<?= COMENTARIU_MAX ?>"
                        placeholder="Scrie un comentariu…"
                        aria-describedby="err-comentariu"></textarea>
              <p class="field__error" id="err-comentariu" hidden></p>
              <div class="comment-form__actions">
                <p class="comment-form__hint">Fii civilizat. Comentariile jignitoare se șterg.</p>
                <button class="btn btn--primary btn--sm" type="submit">Publică</button>
              </div>
            </div>
          </form>
          <?php elseif ($ePublicat): ?>
          <!--
            Vizitatorul nu primește o casetă în care să scrie degeaba: ar fi
            aflat abia la apăsarea butonului că trebuie cont, cu textul scris
            deja. Primește direct ușa, cu întoarcere fix aici.
          -->
          <p class="comment-form__intra">
            <a class="btn btn--primary btn--sm"
               href="login.php?redirect=<?= h(urlencode('/' . urlEveniment((string) $eveniment['slug']))) ?>">Intră în cont</a>
            <span>ca să lași un comentariu.</span>
          </p>
          <?php endif; ?>

          <!--
            Lista de comentarii. Fiecare `<li class="comment">` are înăuntru
            `<article class="comment__body">` și, dacă e principal cu discuție
            sub el, `<ul class="comment__replies">` — ca frate al articolului,
            nu în el. Așa main.js poate înlocui un comentariu editat sau golit
            fără să șteargă odată cu el răspunsurile de dedesubt.

            Toate intră în pagină; ascunsul îl face main.js — vezi
            randeazaComentarii() din inc/comentarii.php.
          -->
          <ul class="comments" data-lista-comentarii>
            <?= randeazaComentarii($fireComentarii, $contextComentarii) ?>
          </ul>

          <!--
            Nicio vorbă încă. Se scrie mereu în pagină, nu doar când lista e
            goală: dacă omul își șterge singurul comentariu, main.js are ce să
            aprindă la loc, fără să lipească text din cod.
          -->
          <p class="comments__gol" data-comentarii-gol <?= $fireComentarii === [] ? '' : 'hidden' ?>>
            <?= $ePublicat ? 'Niciun comentariu încă. Fii primul care spune ceva.' : 'Discuția se deschide după ce evenimentul e publicat.' ?>
          </p>

          <!--
            Butonul se aprinde din JS, cu numărul celor rămase ascunse. Cât e
            fără JS, e ascuns — toate comentariile sunt deja în pagină, deci
            n-ar avea ce să mai aducă.
          -->
          <div class="load-more" data-mai-multe hidden>
            <button class="btn btn--ghost" type="button" data-mai-multe-buton>Vezi mai multe comentarii</button>
          </div>
        </div>

        <!-- ------------------------ PANOU: INTERESAȚI --------------------- -->
        <div class="panel" id="panel-interested" role="tabpanel" aria-labelledby="tab-interested" tabindex="0" hidden>
          <p class="panel__intro">
            <strong><span data-count-for="interesat"><?= (int) $numarInterese['interesat'] ?></span> persoane</strong> sunt interesate de acest eveniment.
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
        <!--
          Toți participanții intră în pagină; ascunsul îl face main.js, care
          lasă la vedere primii PARTICIPANTI_DEODATA și îi arată pe ceilalți la
          apăsarea butonului, fără să mai întrebe serverul. Aceeași alegere ca
          la comentarii, din aceleași motive.

          Tokenul CSRF se scrie doar pentru cine poate scoate pe cineva de pe
          listă — organizatorul și staff-ul. Pentru restul n-ar avea ce face.
        -->
        <div class="panel" id="panel-going" role="tabpanel" aria-labelledby="tab-going" tabindex="0" hidden
             data-participanti
             data-slug="<?= h((string) $eveniment['slug']) ?>"
             data-deodata="<?= PARTICIPANTI_DEODATA ?>"
             <?= $poateScoateParticipanti ? 'data-csrf="' . h(tokenCsrf()) . '"' : '' ?>>

          <p class="panel__intro">
            <?php if ($numarInterese['participant'] === 0): ?>
            <?= $eIncheiat ? 'Nu a confirmat nimeni participarea.' : 'Nimeni nu a confirmat încă participarea. Poți fi primul.' ?>
            <?php else: ?>
            <strong><span data-count-for="participant"><?= (int) $numarInterese['participant'] ?></span>
              <span data-cuvant-persoane><?= $numarInterese['participant'] === 1 ? 'persoană' : 'persoane' ?></span></strong>
            <?= $eIncheiat ? 'au confirmat participarea.' : 'au confirmat că vor participa.' ?>
            <?php endif; ?>
          </p>

          <!--
            Fiecare `<li class="person">` are `data-participant` cu id-ul
            omului: după el îl găsește main.js când serverul confirmă scoaterea.
            Lista se desenează într-un singur loc, randeazaParticipanti() din
            inc/interese.php — de acolo vine și la încărcarea paginii, și după
            fiecare scoatere, prin api/exclude-participant.php.
          -->
          <ul class="people" data-lista-participanti>
            <?= randeazaParticipanti($evenimentId, (int) $eveniment['membru_id'], $poateScoateParticipanti) ?>
          </ul>

          <!--
            Butonul se aprinde din JS, cu numărul celor rămași ascunși. Cât e
            fără JS, e ascuns — toți sunt deja în pagină, deci n-ar avea ce să
            mai aducă.
          -->
          <div class="load-more" data-mai-multi hidden>
            <button class="btn btn--ghost" type="button" data-mai-multi-buton>Vezi mai mult</button>
          </div>

          <?php if ($poateScoateParticipanti): ?>
          <!--
            Caseta de confirmare a scoaterii. Una singură, mutată de JS sub
            omul pe care s-a apăsat: câte una pentru fiecare rând ar fi însemnat
            zeci de formulare ascunse în pagină, fiecare cu textarea și bifa lui.

            Motivul e obligatoriu (MOTIV_EXCLUDERE_MIN caractere) fiindcă pleacă
            întreg în e-mailul primit de omul scos — el are dreptul să știe de ce.
          -->
          <template id="sablon-scoatere">
            <!--
              Un `<li>`, nu un `<form>` pus de-a dreptul în listă: copiii unui
              `<ul>` sunt `<li>`-uri, iar lista e o grilă pe două coloane — așa
              caseta poate să le cuprindă pe amândouă, sub omul pe care s-a
              apăsat.
            -->
            <li class="scoate-rand" data-scoate-form>
            <form class="scoate-form">
              <p class="scoate-form__titlu">Scoți de pe listă pe <strong data-scoate-nume></strong>?</p>

              <label class="sr-only" for="scoate-motiv">De ce îl scoți</label>
              <textarea id="scoate-motiv" rows="3" maxlength="<?= MOTIV_EXCLUDERE_MAX ?>"
                        placeholder="De ce îl scoți de pe listă? (cel puțin <?= MOTIV_EXCLUDERE_MIN ?> caractere)"
                        aria-describedby="scoate-motiv-hint err-scoate"></textarea>
              <p class="field__hint" id="scoate-motiv-hint">
                Textul ăsta pleacă întreg în e-mailul pe care îl primește.
              </p>
              <p class="field__error" id="err-scoate" hidden></p>

              <!--
                Bifa care închide ușa. `.check` e componenta de bifă a
                site-ului, aceeași ca la termeni sau la newsletter: fără ea,
                pătratul ar fi rămas nedesenat — resetul global stinge decorul
                nativ al oricărui `<input>` (vezi „Fără decor nativ pe
                controale" din style.css).
              -->
              <label class="check scoate-form__bifa">
                <input type="checkbox" data-scoate-interzis>
                <span>Nu se mai poate înscrie la acest eveniment</span>
              </label>

              <div class="scoate-form__actiuni">
                <button class="btn btn--primary btn--xs" type="submit">Scoate de pe listă</button>
                <button class="btn btn--text" type="button" data-scoate-renunta>Renunță</button>
              </div>
            </form>
            </li>
          </template>
          <?php endif; ?>
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
