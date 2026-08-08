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
 *   titlu, categorie, locatie, descriere          — text
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
          <div><span>Locul</span><strong><?= h((string) ($e['locatie'] ?? '')) ?></strong></div>
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

    // Imaginea implicită a categoriei, când va exista. Coloana e deja în bază,
    // fișierele se urcă de mână — vezi roadmap-ul din CLAUDE.md.
    if ($coperta === '' && !empty($rand['imagine_default'])) {
        $coperta = 'assets/img/categorii/' . $rand['imagine_default'];
    }

    return [
        'titlu'            => $rand['titlu'] ?? '',
        'categorie'        => $rand['categorie'] ?? '',
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
        'organizator'      => numeAfisat((string) ($rand['org_nume'] ?? ''), (string) ($rand['org_prenume'] ?? '')),
        'organizator_url'  => !empty($rand['org_permalink'])
            ? 'profil.php?m=' . urlencode((string) $rand['org_permalink'])
            : '',
        'organizator_poza' => $rand['org_poza'] ?? null,
        'creat_la'         => $rand['creat_la'] ?? null,
    ];
}
