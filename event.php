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
$eAprobat       = $eveniment['stare_moderare'] === 'aprobat';
$eAnulat        = $eveniment['stare_moderare'] === 'anulat';

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

      <?php if ($eAprobat): ?>
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
               <?= $eLogat ? 'data-csrf="' . h(tokenCsrf()) . '"' : '' ?>>
        <div class="rsvp__head">
          <h2 id="rsvp-title">Mergi la acest eveniment?</h2>
          <p>Spune-le și celorlalți — apari în lista de mai jos.</p>
        </div>

        <div class="rsvp__actions">
          <button class="rsvp__btn rsvp__btn--interested" type="button"
                  id="btn-interested" data-rsvp="interesat"
                  aria-pressed="<?= $stareaMea === 'interesat' ? 'true' : 'false' ?>">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path d="m12 3.8 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 10l5.9-.9L12 3.8Z"/>
            </svg>
            <span class="rsvp__label">Mă interesează</span>
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
                  <?= (!$maiSuntLocuri && $stareaMea !== 'participant') ? 'disabled' : '' ?>>
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
            </svg>
            <span class="rsvp__label">Voi participa</span>
            <span class="rsvp__count" data-count-for="participant"><?= (int) $numarInterese['participant'] ?></span>
          </button>
        </div>

        <?php if (!$maiSuntLocuri && $stareaMea !== 'participant'): ?>
        <p class="rsvp__plin">Nu mai sunt locuri disponibile la acest eveniment.</p>
        <?php endif; ?>

        <!-- ------------------- confirmarea participării -------------------
          „Mă interesează" e o însemnare; „voi participa" e o hotărâre care
          dă datele omului mai departe. De aceea treapta asta există, și de
          aceea spune pe față ce se întâmplă înainte, nu după.

          Se deschide din JS și se verifică din nou pe server: fără
          `confirmat`, api/interes.php nu scrie nimic.
        -->
        <?php if ($eLogat): ?>
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
          <?= randeazaOameniInteresati($evenimentId) ?>
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
            <span class="tab__count">7</span>
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
        <div class="panel" id="panel-going" role="tabpanel" aria-labelledby="tab-going" tabindex="0" hidden>
          <p class="panel__intro">
            <strong><span data-count-for="participant"><?= (int) $numarInterese['participant'] ?></span> persoane</strong> au confirmat că vor participa.
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
