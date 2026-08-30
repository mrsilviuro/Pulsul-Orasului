<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — cum arată un eveniment pe ecran.
 *
 * Un singur loc care știe să deseneze antetul, coperta, caseta cu detalii și
 * descrierea. Îl folosesc două pagini:
 *
 *   event.php           — cu datele din bază, pentru evenimentul publicat
 *   previzualizare.php  — cu datele din formular, înainte de a fi salvate
 *
 * De aceea funcția nu atinge baza și nu știe nimic despre moderare sau despre
 * cine e conectat: primește un tablou cu ce e de arătat și atât. Ce diferă
 * între cele două pagini — banda de sus, butonul de editare — se dă din afară.
 *
 * Dacă vrei să schimbi cum arată pagina unui eveniment, aici e locul: orice
 * schimbare de aici se vede pe amândouă deodată, deci previzualizarea nu poate
 * rămâne în urma paginii adevărate.
 */

require_once __DIR__ . '/validare.php';

/**
 * Cheile pe care le citește afiseazaEveniment():
 *
 *   titlu, categorie, oras, locatie, descriere    — text
 *   data_eveniment                                 — 'AAAA-LL-ZZ'
 *   ora_inceput, ora_sfarsit                       — 'HH:MM[:SS]' sau null
 *   cost                                           — number|string|null
 *   varsta_minima, participanti_min, participanti_max — int|null
 *   gen_participanti                               — 'barbati'|'femei'|'nespecificat'
 *   coperta_url                                    — adresa pozei, sau ''
 *   organizator                                    — numele afișat
 *   organizator_url                                — link spre profil, sau ''
 *   organizator_poza                               — numele pozei de profil, sau null
 *   creat_la                                       — data publicării, sau null
 *
 * Rândul din bază nu are chiar formele astea (are `coperta`, `org_nume`…), de
 * aceea există evenimentDinBaza(), mai jos.
 */
function afiseazaEveniment(array $e, ?array $banda = null, ?callable $actiuni = null): void
{
    $oraInceput = oraScurta($e['ora_inceput'] ?? null);
    $oraSfarsit = oraScurta($e['ora_sfarsit'] ?? null);
    $coperta    = (string) ($e['coperta_url'] ?? '');
    ?>
      <!-- ======================= ANTETUL EVENIMENTULUI ===================== -->
      <header class="post__head">
        <span class="post__cat"><?= h((string) ($e['categorie'] ?? '')) ?></span>
        <h1 class="post__title"><?= h((string) ($e['titlu'] ?? '')) ?></h1>

        <?php if ($banda !== null): ?>
        <p class="stare-anunt stare-anunt--<?= h((string) $banda['fel']) ?>">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.4 2"/>
          </svg>
          <span><?= h((string) $banda['text']) ?></span>
        </p>

        <?php if (($banda['motiv'] ?? '') !== ''): ?>
        <!--
          Motivul anulării, scris de organizator. Stă sub bandă, nu în ea:
          banda e o etichetă de o frază, iar asta e o explicație care poate
          avea și trei rânduri. Rândurile lui se păstrează prin nl2br, iar
          escaparea se face ÎNAINTE, ca la descriere — altfel <br> ar fi
          escapat și el și s-ar vedea codul.
        -->
        <blockquote class="motiv-anulare">
          <p class="motiv-anulare__eticheta">Motivul organizatorului</p>
          <p><?= nl2br(h((string) $banda['motiv'])) ?></p>
        </blockquote>
        <?php endif; ?>
        <?php endif; ?>

        <div class="post__meta">
          <!-- Poza organizatorului; cine n-are, arată silueta implicită. -->
          <img class="post__avatar" src="<?= h(urlPoza($e['organizator_poza'] ?? null, true)) ?>"
               alt="" width="96" height="96">
          <div class="post__by">
            <?php if (($e['organizator_url'] ?? '') !== ''): ?>
            <a class="post__author" href="<?= h((string) $e['organizator_url']) ?>"><?= h((string) $e['organizator']) ?></a>
            <?php else: ?>
            <span class="post__author"><?= h((string) $e['organizator']) ?></span>
            <?php endif; ?>
            <div class="post__sub">
              <span>Organizator</span>
              <?php if (!empty($e['creat_la'])): ?>
              <span class="dot" aria-hidden="true"></span>
              <time datetime="<?= h((string) $e['creat_la']) ?>">publicat <?= h(dataScurta((string) $e['creat_la'])) ?></time>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($actiuni !== null) { $actiuni(); } ?>
        </div>
      </header>

      <?php if ($coperta !== ''): ?>
      <!-- ======================= COPERTA 16:9 ============================= -->
      <!-- Fără figcaption: n-avem de unde ști ce e în poză, iar o legendă
           inventată e mai rea decât niciuna. -->
      <figure class="post__figure">
        <img src="<?= h($coperta) ?>" alt=""
             width="1600" height="900" fetchpriority="high" decoding="async"
             <?= !empty($e['coperta_din_browser']) ? 'id="prev-coperta"' : '' ?>>
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
          <div><span>Data</span><strong><?= h(dataLunga($e['data_eveniment'] ?? null)) ?></strong></div>
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
          <div><span>Locul</span><strong><?php
            /**
             * Orașul înaintea locului, despărțite printr-un punct ridicat:
             * „Roman · Piața Roman-Vodă". Un rând al lui, cu eticheta „Oraș",
             * ar fi spus același cuvânt la fiecare eveniment din caseta asta,
             * cât timp orașul e unul singur — iar când vor fi mai multe, tot
             * lângă adresă e locul lui, fiindcă asta e: prima ei jumătate.
             *
             * Evenimentele de dinaintea coloanei n-au oraș; atunci se scrie
             * doar locul, fără punct rătăcit la început.
             */
            $orasul = trim((string) ($e['oras'] ?? ''));
            $locul  = (string) ($e['locatie'] ?? '');

            echo h($orasul !== '' ? $orasul . ' · ' . $locul : $locul);
          ?></strong></div>
        </div>

        <div class="event-box__item">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <rect x="2.5" y="6" width="19" height="12" rx="3"/><circle cx="12" cy="12" r="2.6"/>
          </svg>
          <div><span>Acces</span><strong><?= h(costScris($e['cost'] ?? null)) ?></strong></div>
        </div>

        <?php if (($e['varsta_minima'] ?? null) !== null): ?>
        <div class="event-box__item">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="8.2" r="3.6"/><path d="M5 20c0-3.9 3.1-6.4 7-6.4s7 2.5 7 6.4"/>
          </svg>
          <div><span>Vârstă minimă</span><strong><?= (int) $e['varsta_minima'] ?> ani</strong></div>
        </div>
        <?php endif; ?>

        <?php if (($e['gen_participanti'] ?? 'nespecificat') !== 'nespecificat'): ?>
        <div class="event-box__item">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="9" cy="8.5" r="3.4"/><path d="M3 20c0-3.4 2.7-5.7 6-5.7s6 2.3 6 5.7"/>
            <path d="M17 8.5h4M19 6.5v4"/>
          </svg>
          <div><span>Pentru cine</span><strong><?=
            $e['gen_participanti'] === 'barbati' ? 'Doar bărbați' : 'Doar femei'
          ?></strong></div>
        </div>
        <?php endif; ?>

        <?php
          // Cele două numere stau într-un singur rând: „minim 10" și „cel mult
          // 50" sunt aceeași informație, câți oameni încap.
          $participanti = [];
          if (($e['participanti_min'] ?? null) !== null) {
              $participanti[] = 'minimum ' . (int) $e['participanti_min'];
          }
          if (($e['participanti_max'] ?? null) !== null) {
              $participanti[] = 'cel mult ' . (int) $e['participanti_max'];
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

      <!-- ========================= DESCRIEREA ============================
        Textul e păstrat exact cum l-a scris omul, neescapat. Escaparea se
        face aici, la randare, cu h() — invers ar fi însemnat „&amp;amp;" la
        a doua editare și un text pe care nu-l mai poți căuta.

        Se escapează ÎNTÂI, se pun etichetele DUPĂ: altfel <p> și <br> ar fi
        escapate și ele, iar omul ar vedea codul în loc de paragrafe.
      ================================================================== -->
      <div class="post__body">
        <?php
          $paragrafe = preg_split('/\n{2,}/', (string) ($e['descriere'] ?? '')) ?: [];

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
    <?php
}

/**
 * Rândul din bază, adus la forma pe care o citește afiseazaEveniment().
 *
 * Traducerea stă aici, nu în event.php, ca numele coloanelor să nu se
 * împrăștie prin pagini: dacă mâine se schimbă vreuna, se schimbă într-un loc.
 */
function evenimentDinBaza(array $rand): array
{
    $coperta = urlCoperta($rand['coperta'] ?? null);

    // Imaginea implicită a categoriei, dacă anunțul n-are copertă a lui.
    // urlImagineCategorie() se uită și pe disc: fișierele se urcă de mână, iar
    // unele lipsesc încă (vezi roadmap-ul din CLAUDE.md).
    if ($coperta === '') {
        $coperta = urlImagineCategorie($rand['imagine_default'] ?? null);
    }

    $orgSters = esteContSters($rand['org_stare'] ?? null);

    return [
        'titlu'            => $rand['titlu'] ?? '',
        'categorie'        => $rand['categorie'] ?? '',
        'oras'             => $rand['oras'] ?? '',
        'locatie'          => $rand['locatie'] ?? '',
        'descriere'        => $rand['descriere'] ?? '',
        'data_eveniment'   => $rand['data_eveniment'] ?? null,
        'ora_inceput'      => $rand['ora_inceput'] ?? null,
        'ora_sfarsit'      => $rand['ora_sfarsit'] ?? null,
        'cost'             => $rand['cost'] ?? null,
        'varsta_minima'    => $rand['varsta_minima'] ?? null,
        'participanti_min' => $rand['participanti_min'] ?? null,
        'participanti_max' => $rand['participanti_max'] ?? null,
        'gen_participanti' => $rand['gen_participanti'] ?? 'nespecificat',
        'coperta_url'      => $coperta,

        /**
         * ORGANIZATORUL CARE ȘI-A ȘTERS CONTUL nu mai are nici nume, nici chip,
         * nici profil la care să ducă legătura.
         *
         * Rândul lui a rămas în `membri` — de el atârnă anunțul ăsta și
         * participările altora — dar omul din el s-a golit. Fără întrebarea de
         * aici, antetul scria mai departe „Ș. Utilizator", adică o prescurtare
         * care arată ca un nume adevărat, și trimitea la un profil gol.
         *
         * Poza se stinge ȘI EA, deși anonimizarea o șterge oricum de pe disc:
         * regula n-are voie să atârne de curățenia altcuiva. Un rând golit de
         * mână din phpMyAdmin — se mai întâmplă — ar fi rămas altfel cu chipul
         * pe el.
         */
        'organizator'      => $orgSters
            ? NUME_CONT_STERS
            : numeAfisat((string) ($rand['org_nume'] ?? ''), (string) ($rand['org_prenume'] ?? '')),
        'organizator_url'  => (!$orgSters && !empty($rand['org_permalink']))
            ? urlProfil((string) $rand['org_permalink'])
            : '',
        'organizator_poza' => $orgSters ? null : ($rand['org_poza'] ?? null),
        'creat_la'         => $rand['creat_la'] ?? null,
    ];
}

/**
 * Zona de anulare a unui eveniment: butonul roșu și întrebarea de sub el.
 *
 * DOUĂ PAGINI, un singur HTML. O cer formularul de editare
 * (adauga_eveniment.php, unde a stat dintotdeauna) și pagina evenimentului
 * (event.php, sub caseta de interes) — iar organizatorul e același om, cu
 * aceeași faptă de făcut. Scrisă de două ori, s-ar fi despărțit la prima
 * corectură, și tocmai aici: textul de avertizare e singurul lucru pe care
 * omul îl citește înainte de o apăsare care nu se ia înapoi.
 *
 * Nu întreabă NIMIC despre cine e omul și dacă are voie. Cele două întrebări —
 * „e al lui?" și poateFiAnulat() — se pun în pagină, înainte de a chema
 * funcția asta, fiindcă tot acolo se știe cine se uită. Iar regula adevărată e
 * oricum în api/anuleaza-eveniment.php, care o pune din nou la fiecare cerere.
 *
 * `data-anulare` pe zonă e cum o găsește main.js: acolo stau și slugul, și
 * tokenul, deci JS-ul nu mai are nevoie de un formular în jur. (Înainte le
 * citea din câmpurile ascunse ale formularului de eveniment — ceea ce mergea
 * pe o singură pagină, exact cea de pe care plecăm acum.)
 */
function randeazaZonaAnulare(array $ev, string $csrf): string
{
    $titlu = h(inceputDeText((string) ($ev['titlu'] ?? ''), 60));

    return '<div class="zona-anulare" id="ev-anulare" data-anulare'
         . ' data-slug="' . h((string) ($ev['slug'] ?? '')) . '"'
         . ' data-csrf="' . h($csrf) . '">'

         . '<button class="btn btn--rau btn--block" type="button" id="ev-anuleaza">'
         . 'Anulează evenimentul</button>'

         . '<div class="stergere-confirm" id="ev-anulare-sigur" hidden>'
         . '<p class="card-set__lead"><strong>Sigur anulezi „' . $titlu . '"?</strong></p>'
         . '<p class="card-set__lead">'
         . 'Anunțul rămâne pe site, dar însemnat ca anulat, și nu mai poate fi '
         . 'adus înapoi de tine. Oamenii care și-au arătat interesul sau au spus '
         . 'că vin vor fi înștiințați prin e-mail că nu mai are loc — și vor citi '
         . 'exact ce scrii mai jos.</p>'

         /**
          * Motivul e obligatoriu, și nu de formă: e chiar textul care pleacă
          * spre oamenii care își făcuseră planuri, și care rămâne apoi scris
          * pe pagina evenimentului, sub bandă.
          *
          * Fără `name` și fără `required`: pe pagina de editare caseta stă
          * înăuntrul formularului de eveniment, iar cu ele ar pleca odată cu
          * trimiterea spre aprobare și ar bloca-o cât e goală. JS o citește
          * după id și o trimite singur.
          */
         . '<div class="field">'
         . '<label for="ev-motiv">De ce anulezi? <span class="req" aria-hidden="true">*</span></label>'
         . '<textarea id="ev-motiv" rows="3"'
         . ' data-min="' . MOTIV_ANULARE_MIN . '" data-max="' . MOTIV_ANULARE_MAX . '"'
         . ' placeholder="S-a stricat vremea și nu avem unde ne adăposti."'
         . ' aria-describedby="err-ev-motiv ev-motiv-numar"></textarea>'
         . '<p class="field__hint contor-caractere" id="ev-motiv-numar" role="status">'
         . '0 din minim ' . MOTIV_ANULARE_MIN . ' caractere</p>'
         . '<p class="field__error" id="err-ev-motiv" hidden></p>'
         . '</div>'

         . '<div class="stergere-confirm__actiuni">'
         . '<button class="btn btn--rau" type="button" id="ev-anulare-da">Da, anulează</button>'
         . '<button class="btn btn--ghost" type="button" id="ev-anulare-nu">Renunță</button>'
         . '</div>'
         . '</div></div>';
}
