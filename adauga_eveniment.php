<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — publicarea unui eveniment.
 *
 * Se ajunge aici din butonul „+ Eveniment nou" de pe prima pagină.
 */

require_once __DIR__ . '/inc/evenimente.php';

$membru = membruCurent();

/**
 * Fără cont nu se intră deloc.
 *
 * Nu ascundem formularul, ci trimitem omul de pe pagină: dacă l-am lăsa să
 * vadă câmpurile și să le completeze degeaba, ar afla că n-are cont abia la
 * apăsarea butonului, cu tot ce scrisese pierdut.
 */
if ($membru === null) {
    cereIntrare('/adauga_eveniment.php');
}

$membruId   = (int) $membru['id'];
$categorii  = categoriiEvenimente();
$voie       = poatePublicaEveniment($membruId);

$titlu     = 'Publică un eveniment — PulsulOrasului.Ro';
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
      <span class="crumbs__current">Eveniment nou</span>
    </nav>

    <h1 class="setari__titlu">Publică un eveniment</h1>
    <p class="setari__lead">
      Completează ce știi acum. Anunțul intră la verificare și apare pe site
      după ce îl citim.
    </p>

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
        Un eveniment se încheie singur a doua zi după data la care are loc.
        Până atunci, tot ce ai de făcut e să te ocupi de el.
      </p>

      <a class="btn btn--ghost" href="index.php">Înapoi pe prima pagină</a>
    </section>

    <?php else: ?>
    <!-- ======================== FORMULARUL ========================== -->
    <div id="ev-block">
      <form class="form form--eveniment" id="eveniment-form" novalidate enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

        <!-- --------------------- Ce și unde --------------------- -->
        <section class="card-set">
          <h2 class="card-set__titlu">Despre ce e vorba</h2>

          <div class="field">
            <label for="ev-titlu">Titlul evenimentului <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="ev-titlu" name="titlu" maxlength="<?= TITLU_EVENIMENT_MAX ?>"
                   placeholder="Cursa de seară prin centrul vechi" required
                   aria-describedby="err-ev-titlu">
            <p class="field__error" id="err-ev-titlu" hidden></p>
          </div>

          <div class="field">
            <label for="ev-categorie">Categorie <span class="req" aria-hidden="true">*</span></label>
            <select id="ev-categorie" name="categorie_id" required aria-describedby="err-ev-categorie">
              <option value="" selected disabled>Alege…</option>
              <?php foreach ($categorii as $c): ?>
              <option value="<?= (int) $c['id'] ?>"><?= h($c['nume']) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="field__error" id="err-ev-categorie" hidden></p>
          </div>

          <div class="field">
            <label for="ev-locatie">Unde are loc <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="ev-locatie" name="locatie" maxlength="<?= LOCATIE_MAX ?>"
                   placeholder="Piața Sfatului, lângă fântână" required
                   aria-describedby="err-ev-locatie">
            <p class="field__error" id="err-ev-locatie" hidden></p>
          </div>
        </section>

        <!-- ---------------------- Coperta ----------------------- -->
        <section class="card-set">
          <h2 class="card-set__titlu">Poza de copertă <span class="field__optional">(opțional)</span></h2>
          <p class="card-set__lead">
            E poza mare de sus, de la anunț. Cel puțin
            <?= COPERTA_SURSA_MIN_LATIME ?>×<?= COPERTA_SURSA_MIN_INALTIME ?> pixeli. După ce
            o alegi, o poți muta și mări în cadru — ce vezi acolo e exact ce se
            salvează. Dacă nu pui niciuna, folosim imaginea categoriei.
          </p>

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
              <button class="btn btn--ghost btn--sm" type="button" id="ev-coperta-renunt">Scoate poza</button>
            </div>
          </div>
        </section>

        <!-- ----------------------- Când ------------------------- -->
        <section class="card-set">
          <h2 class="card-set__titlu">Când</h2>

          <div class="field">
            <label for="ev-data">Data <span class="req" aria-hidden="true">*</span></label>
            <input type="date" id="ev-data" name="data_eveniment"
                   min="<?= h(date('Y-m-d')) ?>"
                   max="<?= h(date('Y-m-d', strtotime('+' . ANI_INAINTE_MAX . ' years'))) ?>"
                   required aria-describedby="err-ev-data">
            <p class="field__error" id="err-ev-data" hidden></p>
          </div>

          <div class="field-row">
            <div class="field">
              <label for="ev-ora-inceput">Ora de început <span class="req" aria-hidden="true">*</span></label>
              <input type="time" id="ev-ora-inceput" name="ora_inceput" required
                     aria-describedby="err-ev-ora-inceput">
              <p class="field__error" id="err-ev-ora-inceput" hidden></p>
            </div>

            <div class="field">
              <label for="ev-ora-sfarsit">Ora de sfârșit</label>
              <input type="time" id="ev-ora-sfarsit" name="ora_sfarsit"
                     aria-describedby="err-ev-ora-sfarsit">
              <!-- Bifa stă sub câmpul pe care îl stinge, nu sub tot rândul:
                   altfel nu se vede din prima la care dintre ore se referă. -->
              <label class="check check--mic">
                <input type="checkbox" id="ev-fara-sfarsit" name="fara_ora_sfarsit" value="1">
                <span>Nu se știe până când ține</span>
              </label>
              <p class="field__error" id="err-ev-ora-sfarsit" hidden></p>
            </div>
          </div>
        </section>

        <!-- -------------------- Cine și cât --------------------- -->
        <section class="card-set">
          <h2 class="card-set__titlu">Cine poate veni și cât costă</h2>

          <div class="field">
            <label class="check">
              <input type="checkbox" id="ev-gratuit" name="gratuit" value="1" checked>
              <span>Intrarea e gratuită</span>
            </label>
          </div>

          <div class="field" id="ev-cost-camp" hidden>
            <label for="ev-cost">Cât costă, de persoană (lei)</label>
            <input type="text" id="ev-cost" name="cost" inputmode="decimal"
                   placeholder="25" aria-describedby="err-ev-cost">
            <p class="field__error" id="err-ev-cost" hidden></p>
          </div>

          <div class="field-row">
            <div class="field">
              <label for="ev-varsta">Vârstă minimă</label>
              <select id="ev-varsta" name="varsta_minima" aria-describedby="err-ev-varsta">
                <option value="nespecificat" selected>Nespecificată</option>
                <option value="13">13+</option>
                <option value="16">16+</option>
                <option value="18">18+</option>
              </select>
              <p class="field__error" id="err-ev-varsta" hidden></p>
            </div>

            <div class="field">
              <label for="ev-gen">Pentru cine e</label>
              <select id="ev-gen" name="gen_participanti" aria-describedby="err-ev-gen">
                <option value="nespecificat" selected>Oricine poate veni</option>
                <option value="barbati">Doar bărbați</option>
                <option value="femei">Doar femei</option>
              </select>
              <p class="field__error" id="err-ev-gen" hidden></p>
            </div>
          </div>

          <div class="field-row">
            <div class="field">
              <label for="ev-min">Participanți minim</label>
              <p class="field__hint-sus">Sub câți oameni evenimentul nu poate începe.</p>
              <input type="text" id="ev-min" name="participanti_min" inputmode="numeric"
                     placeholder="10" aria-describedby="err-ev-min">
              <label class="check check--mic">
                <input type="checkbox" id="ev-fara-min" name="fara_participanti_min" value="1" checked>
                <span>Nespecificat</span>
              </label>
              <p class="field__error" id="err-ev-min" hidden></p>
            </div>

            <div class="field">
              <label for="ev-max">Participanți maxim</label>
              <p class="field__hint-sus">Câți încap, dacă există o limită.</p>
              <input type="text" id="ev-max" name="participanti_max" inputmode="numeric"
                     placeholder="50" aria-describedby="err-ev-max">
              <label class="check check--mic">
                <input type="checkbox" id="ev-fara-max" name="fara_participanti_max" value="1" checked>
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
            <textarea id="ev-descriere" name="descriere" rows="10"
                      maxlength="<?= DESCRIERE_MAX ?>"
                      placeholder="Pornim din fața primăriei la ora 19:00…"
                      required aria-describedby="err-ev-descriere ev-numar"></textarea>
            <p class="field__hint" id="ev-numar" role="status">0 din <?= DESCRIERE_MIN ?> de caractere</p>
            <p class="field__error" id="err-ev-descriere" hidden></p>
          </div>
        </section>

        <button class="btn btn--primary btn--block" type="submit">Trimite spre aprobare</button>
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
        <h2 class="done__title">Evenimentul tău a fost trimis spre aprobare</h2>
        <!-- Fără termen promis: nu există încă nimeni care să apese „aprobă",
             iar un „în aceeași zi" scris aici ar fi o vorbă goală. -->
        <p class="done__text">
          Îl citim și, dacă e totul în regulă, apare pe site.
        </p>
        <div class="done__actions">
          <a class="btn btn--primary" href="index.php">Mergi pe prima pagină</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
