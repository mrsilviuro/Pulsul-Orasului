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
 * Un singur formular pentru amândouă, dinadins: două formulare aproape la fel
 * s-ar despărți la prima corectură, iar regulile de verificare ar începe să
 * difere între „nou" și „schimbat" — exact acolo unde n-au voie.
 */

require_once __DIR__ . '/inc/evenimente.php';

$slug   = trim((string) ($_GET['slug'] ?? ''));
$membru = membruCurent();

/**
 * Fără cont nu se intră deloc.
 *
 * Nu ascundem formularul, ci trimitem omul de pe pagină: dacă l-am lăsa să
 * vadă câmpurile și să le completeze degeaba, ar afla că n-are cont abia la
 * apăsarea butonului, cu tot ce scrisese pierdut.
 */
if ($membru === null) {
    cereIntrare('/adauga_eveniment.php' . ($slug !== '' ? '?slug=' . urlencode($slug) : ''));
}

$membruId  = (int) $membru['id'];
$categorii = categoriiEvenimente();

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
}

$eEditare = $ev !== null;

/**
 * Limita de evenimente active se cere doar la unul nou.
 *
 * La editare, limita s-ar aplica chiar evenimentului care se editează: omul cu
 * un singur eveniment activ ar fi oprit tocmai de el, deci n-ar mai putea
 * corecta niciodată nimic.
 */
$voie = $eEditare
    ? ['poate' => true, 'mesaj' => '', 'active' => []]
    : poatePublicaEveniment($membruId);

/* ------------------- valorile cu care pleacă formularul ---------------- */

/** Ce scrie într-un câmp: ce era în bază la editare, nimic la unul nou. */
$val = static function (string $camp, string $implicit = '') use ($ev): string {
    return $ev !== null && $ev[$camp] !== null ? (string) $ev[$camp] : $implicit;
};

$copertaAcum = $eEditare ? urlCoperta($ev['coperta'] ?? null) : '';

/**
 * Bifele: la editare urmează ce e în bază, la un formular gol pornesc bifate.
 *
 * „Nu se știe până când ține" bifată din start e adevărul pentru cele mai
 * multe anunțuri: ora de început se știe mereu, cea de sfârșit aproape
 * niciodată. Cine o știe scoate bifa și scrie ora — o mișcare, în loc de una
 * pe care ar fi trebuit s-o facă toți ceilalți.
 */
$faraOraSfarsit = !$eEditare || ($ev['ora_sfarsit'] ?? null) === null;

/**
 * „Gratuit" înseamnă aici același lucru ca la afișare (costScris): și NULL, și
 * zero. În bază rămân două lucruri diferite — NULL e „n-a cerut bani", 0 e „a
 * scris el zero" — dar pe pagina evenimentului amândouă scriu „Gratuit", deci
 * formularul n-are voie să spună altceva. Altfel omul ar deschide editarea unui
 * eveniment gratuit și ar găsi bifa scoasă și un preț de 0 lei.
 */
$eGratuit  = !$eEditare || ($ev['cost'] ?? null) === null || (float) $ev['cost'] <= 0;
$faraMinim = !$eEditare || ($ev['participanti_min'] ?? null) === null;
$faraMaxim = !$eEditare || ($ev['participanti_max'] ?? null) === null;

$titlu     = $eEditare
    ? 'Schimbă evenimentul — PulsulOrasului.Ro'
    : 'Publică un eveniment — PulsulOrasului.Ro';
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
      <span class="crumbs__current">Schimbă</span>
      <?php else: ?>
      <span class="crumbs__current">Eveniment nou</span>
      <?php endif; ?>
    </nav>

    <?php if ($eEditare): ?>
    <h1 class="setari__titlu">Schimbă evenimentul</h1>
    <p class="setari__lead">
      Orice schimbare trece din nou pe la noi: până îl citim, anunțul nu se mai
      vede pe site. E singurul fel în care verificarea înseamnă ceva.
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
        Un eveniment se încheie singur a doua zi după data la care are loc.
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
        <?php endif; ?>

        <!-- --------------------- Ce și unde --------------------- -->
        <section class="card-set">
          <h2 class="card-set__titlu">Despre ce e vorba</h2>

          <div class="field">
            <label for="ev-titlu">Titlul evenimentului <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="ev-titlu" name="titlu" maxlength="<?= TITLU_EVENIMENT_MAX ?>"
                   value="<?= h($val('titlu')) ?>"
                   placeholder="Cursa de seară prin centrul vechi" required
                   aria-describedby="err-ev-titlu">
            <p class="field__error" id="err-ev-titlu" hidden></p>
          </div>

          <div class="field">
            <label for="ev-categorie">Categorie <span class="req" aria-hidden="true">*</span></label>
            <select id="ev-categorie" name="categorie_id" required aria-describedby="err-ev-categorie">
              <option value="" <?= $eEditare ? '' : 'selected' ?> disabled>Alege…</option>
              <?php foreach ($categorii as $c): ?>
              <option value="<?= (int) $c['id'] ?>"
                      <?= $val('categorie_id') === (string) $c['id'] ? 'selected' : '' ?>><?= h($c['nume']) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="field__error" id="err-ev-categorie" hidden></p>
          </div>

          <div class="field">
            <label for="ev-locatie">Unde are loc <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="ev-locatie" name="locatie" maxlength="<?= LOCATIE_MAX ?>"
                   value="<?= h($val('locatie')) ?>"
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
              Asta e poza de acum. Dacă nu alegi alta, rămâne ea.
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
              <button class="btn btn--ghost btn--sm" type="button" id="ev-coperta-renunt">Scoate poza</button>
            </div>
          </div>
        </section>

        <!-- ----------------------- Când ------------------------- -->
        <section class="card-set">
          <h2 class="card-set__titlu">Când</h2>

          <div class="field">
            <label for="ev-data">Data <span class="req" aria-hidden="true">*</span></label>
            <!--
              Data se scrie ZZ-LL-AAAA, cum se scrie o dată în România.

              Câmpul vizibil e de text, ca la ore și din același motiv:
              `type="date"` se desenează după limba browserului, nu a paginii,
              deci cine are Chrome în engleză vedea „mm/dd/yyyy" pe un site
              românesc. Cratimele le pune main.js, cifră cu cifră.

              Calendarul nu s-a pierdut: lângă câmp stă un `type="date"`
              adevărat, ascuns, pe care butonul îl deschide cu showPicker().
              El poartă „min" și „max" (în formatul lui, AAAA-LL-ZZ), iar ce se
              alege acolo se scrie înapoi în câmpul vizibil, pe românește. Pe
              telefon rămâne astfel roata nativă de dată.

              „min" e ziua evenimentului dacă ea a trecut deja: altfel
              browserul ar arăta ca greșită o dată pe care omul n-a atins-o.
              Verificarea adevărată e oricum pe server.

              Câmpul ascuns NU are „name": nimic din el nu pleacă spre server,
              ca să nu existe două date în aceeași cerere.
            -->
            <div class="camp-data">
              <input type="text" id="ev-data" name="data_eveniment" required
                     class="camp-data__text" inputmode="numeric" autocomplete="off"
                     maxlength="10" placeholder="25-12-2026"
                     pattern="(0[1-9]|[12][0-9]|3[01])-(0[1-9]|1[0-2])-[0-9]{4}"
                     value="<?= h(dataPentruFormular($val('data_eveniment') ?: null)) ?>"
                     aria-describedby="err-ev-data">

              <button type="button" class="camp-data__buton" id="ev-data-calendar"
                      aria-label="Alege data din calendar">
                <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                  <rect x="3.5" y="5" width="17" height="16" rx="3"/>
                  <path d="M8 3v4M16 3v4M3.5 10h17"/>
                </svg>
              </button>

              <input type="date" id="ev-data-nativ" class="camp-data__nativ"
                     tabindex="-1" aria-hidden="true"
                     min="<?= h(min(date('Y-m-d'), $val('data_eveniment', date('Y-m-d')))) ?>"
                     max="<?= h(date('Y-m-d', strtotime('+' . ANI_INAINTE_MAX . ' years'))) ?>">
            </div>
            <p class="field__error" id="err-ev-data" hidden></p>
          </div>

          <div class="field-row">
            <div class="field">
              <label for="ev-ora-inceput">Ora de început <span class="req" aria-hidden="true">*</span></label>
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

            <div class="field">
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
              <?php $varstaAcum = $eEditare && $ev['varsta_minima'] !== null
                    ? (string) (int) $ev['varsta_minima'] : 'nespecificat'; ?>
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
                      placeholder="Pornim din fața primăriei la ora 19:00…"
                      required aria-describedby="err-ev-descriere ev-numar"><?= h($val('descriere')) ?></textarea>
            <p class="field__hint" id="ev-numar" role="status">0 din <?= DESCRIERE_MIN ?> de caractere</p>
            <p class="field__error" id="err-ev-descriere" hidden></p>
          </div>
        </section>

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
          <button class="btn btn--primary btn--block" type="submit">Trimite spre aprobare</button>
        </div>

        <!-- Portița pentru browserele care nu lasă o filă să se deschidă
             dintr-un răspuns venit mai târziu. Stă ascunsă până e nevoie —
             ascuns e paragraful întreg, nu doar linkul din el: un <p> gol tot
             ocupă un rând și împingea linia de despărțire de mai jos. -->
        <p class="ev-previz-link" id="ev-previz-rand" hidden>
          <a id="ev-previz-link" href="#" target="_blank" rel="noopener">Deschide previzualizarea</a>
        </p>

        <?php if ($eEditare): ?>
        <!--
          Anularea evenimentului. Stă despărțită de restul formularului și în
          roșu stins, ca zona de ștergere a contului din setări: e singura
          apăsare de pe pagina asta care nu se poate lua înapoi.

          Confirmarea e desenată de noi, în pagină, nu cu window.confirm(): o
          fereastră a browserului arată altfel pe Windows, pe Android și pe
          iPhone, iar noi vrem aceeași interfață peste tot. Același tipar ca la
          ștergerea contului — butonul își schimbă locul cu întrebarea.
        -->
        <div class="zona-anulare" id="ev-anulare">
          <button class="btn btn--rau btn--block" type="button" id="ev-anuleaza">
            Anulează evenimentul
          </button>

          <div class="stergere-confirm" id="ev-anulare-sigur" hidden>
            <p class="card-set__lead">
              <strong>Sigur anulezi „<?= h(inceputDeText((string) $ev['titlu'], 60)) ?>"?</strong>
            </p>
            <p class="card-set__lead">
              Anunțul iese de pe site și nu mai poate fi adus înapoi de tine.
              Oamenii care și-au arătat interesul sau au spus că vin vor fi
              înștiințați prin e-mail că nu mai are loc — și vor citi exact ce
              scrii mai jos.
            </p>

            <!--
              Motivul e obligatoriu, și nu de formă: e chiar textul care pleacă
              spre oamenii care își făcuseră planuri. Se verifică pe server, ca
              tot restul; contorul de dedesubt numără la fel ca el.
            -->
            <div class="field">
              <label for="ev-motiv">De ce anulezi? <span class="req" aria-hidden="true">*</span></label>
              <!-- Fără „name" și fără „required": caseta stă înăuntrul
                   formularului de eveniment, iar cu ele ar pleca odată cu
                   trimiterea spre aprobare și ar bloca-o cât e goală. JS o
                   citește după id și o trimite singur, la anulare. -->
              <textarea id="ev-motiv" rows="3"
                        data-min="<?= MOTIV_ANULARE_MIN ?>" data-max="<?= MOTIV_ANULARE_MAX ?>"
                        placeholder="S-a stricat vremea și nu avem unde ne adăposti."
                        aria-describedby="err-ev-motiv ev-motiv-numar"></textarea>
              <p class="field__hint" id="ev-motiv-numar" role="status">0 din <?= MOTIV_ANULARE_MIN ?> de caractere</p>
              <p class="field__error" id="err-ev-motiv" hidden></p>
            </div>

            <div class="stergere-confirm__actiuni">
              <button class="btn btn--rau" type="button" id="ev-anulare-da">Da, anulează</button>
              <button class="btn btn--ghost" type="button" id="ev-anulare-nu">Renunță</button>
            </div>
          </div>
        </div>
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
        <h2 class="done__title">Evenimentul tău a fost trimis spre aprobare</h2>
        <!-- Fără termen promis: nu există încă nimeni care să apese „aprobă",
             iar un „în aceeași zi" scris aici ar fi o vorbă goală. -->
        <p class="done__text">
          Îl citim și, dacă e totul în regulă, apare pe site.
        </p>
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
