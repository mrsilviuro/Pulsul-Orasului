<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — capătul unui abțibild „FindMe".
 *
 * Aici ajunge cine a scanat codul QR de pe un abțibild lipit prin oraș:
 * pulsulorasului.ro/findme.php?qr=K3M7P
 *
 * PAGINA ASTA FACE O FAPTĂ, NU DOAR ARATĂ CEVA: dacă abțibildul e bun, e încă
 * nefolosit, iar omul e conectat, chiar aici se scrie câștigătorul și se
 * încheie evenimentul. De aceea are grijile unei pagini care schimbă starea.
 *
 * DE CE E TOTUȘI UN GET, fără token CSRF și fără buton de apăsat.
 *
 *   Un scaner de coduri QR deschide o adresă. Nu poate trimite un POST, nu
 *   poate purta un token. Dacă am fi cerut o apăsare („Revendică abțibildul"),
 *   am fi pus un drum în plus exact în clipa în care omul stă în stradă, cu
 *   telefonul în mână, lângă un stâlp.
 *
 *   Iar ce s-ar putea face cu o cerere pusă la cale de altcineva — să te
 *   păcălească cineva să deschizi findme.php?qr=… — e să CÂȘTIGI un abțibild.
 *   Nu se pierde nimic, nu se șterge nimic, nu se dă nimic altcuiva. E singurul
 *   loc de pe site unde asta e adevărat, și tocmai de aceea e singurul care are
 *   voie să lucreze pe GET.
 *
 * Ce NU face pagina: nu spune niciodată unde e lipit abțibildul, nici măcar
 * orașul, până nu e găsit. Cine ghicește un cod n-are ce afla din ea.
 */

require_once __DIR__ . '/inc/coduri-qr.php';

$codCerut = is_string($_GET['qr'] ?? null) ? $_GET['qr'] : '';
$membru   = membruCurent();

/**
 * Ce s-a întâmplat, într-un cuvânt. De el atârnă tot ce se vede mai jos.
 *
 *   'necunoscut' — nu există abțibild cu codul ăsta (sau adresa e stricată)
 *   'prea_des'   — s-a bâjbâit prea mult de la adresa asta
 *   'castigat'   — CHIAR ACUM a câștigat omul care se uită
 *   'nepornit' | 'nepublic' | 'tarziu' | 'luat' | 'nelogat' — vezi
 *   deCeNuSePoateRevendica()
 */
$ce  = 'necunoscut';
$cod = null;

/**
 * ÎNAINTE DE ORICE: s-a bâjbâit prea mult de la adresa asta?
 *
 * Un cod are 33 de milioane de combinații, dar nimeni n-are nevoie să le
 * încerce pe toate — e de ajuns să nimerească unul dintre cele câteva active.
 * Fără limita asta, un program ar fi câștigat toate vânătoarele din oraș de pe
 * canapea, ceea ce ar goli jocul de tot rostul lui.
 *
 * Se răspunde ÎNAINTE de a atinge baza cu codul cerut: cine bâjbâie nu află
 * nici măcar dacă a nimerit sau nu — iar asta e chiar ce încearcă să afle.
 */
if (preaMulteIncercariQr()) {
    $ce = 'prea_des';
} else {
    $cod = codQrDupaCod($codCerut);

    if ($cod === null) {
        /**
         * N-a nimerit nimic. SE ȚINE MINTE, ca bâjbâiala să se poată număra —
         * numai cele greșite: cine scanează un abțibild adevărat a fost acolo,
         * s-a uitat, a găsit.
         */
        insemneazaIncercareaQr();
    } else {
        $ce = deCeNuSePoateRevendica($cod, $membru);

        if ($ce === '') {
            /**
             * E rândul lui. revendicaCodul() hotărăște în `WHERE` cine a fost
             * primul: dacă altcineva a apucat înaintea lui — fie și cu o
             * secundă — întoarce false, iar rândul se citește din nou ca să se
             * vadă cine.
             */
            if (revendicaCodul($cod, $membru)) {
                $ce = 'castigat';
            } else {
                $ce = 'luat';
            }

            $cod = codQrDupaCod($codCerut);
        }
    }
}

/* ========================= Ce scrie pe ecran ========================== */

/**
 * Trei texte pentru fiecare situație: eticheta mică de deasupra, titlul mare,
 * și vorba de dedesubt. Într-un singur tablou, ca să se citească dintr-o
 * privire ce vede omul în fiecare caz — și ca pagina de mai jos să nu fie un
 * teanc de `if`-uri.
 */
$vorbe = [
    'castigat' => [
        'fel'      => 'castigat',
        'eticheta' => 'L-ai găsit!',
        'titlu'    => 'Felicitări, abțibildul e al tău',
        'vorba'    => 'Ai fost primul care a scanat codul ăsta. Vânătoarea s-a '
                    . 'încheiat, iar numele tău e acum pe pagina evenimentului.',
    ],
    'luat' => [
        'fel'      => 'luat',
        'eticheta' => 'Ai ajuns al doilea',
        'titlu'    => 'Abțibildul a fost deja găsit',
        'vorba'    => 'Cineva a scanat codul ăsta înaintea ta și a încheiat '
                    . 'vânătoarea. Mai sunt și altele ascunse prin oraș.',
    ],
    'tarziu' => [
        'fel'      => 'tarziu',
        'eticheta' => 'Prea târziu',
        'titlu'    => 'Vânătoarea s-a încheiat',
        'vorba'    => 'A trecut termenul și nu l-a găsit nimeni la timp — nici '
                    . 'măcar tu, deși erai pe drumul bun.',
    ],
    'nepornit' => [
        'fel'      => 'nepornit',
        'eticheta' => 'Ai găsit ceva înainte de vreme',
        'titlu'    => 'Vânătoarea asta n-a început încă',
        'vorba'    => 'Abțibildul e lipit, dar evenimentul lui nu e încă publicat. '
                    . 'Lasă-l unde e și mai treci pe-aici — codul o să însemne '
                    . 'ceva în curând.',
    ],
    'nepublic' => [
        'fel'      => 'nepornit',
        'eticheta' => 'Ai găsit ceva înainte de vreme',
        'titlu'    => 'Vânătoarea asta n-a început încă',
        'vorba'    => 'Abțibildul e lipit, dar evenimentul lui nu se vede pe site '
                    . 'chiar acum. Lasă-l unde e și mai treci pe-aici.',
    ],
    'nelogat' => [
        'fel'      => 'nelogat',
        'eticheta' => 'Aproape!',
        'titlu'    => 'L-ai găsit — intră în cont ca să-l revendici',
        'vorba'    => 'Ca să scriem cine a câștigat ne trebuie un cont. Intră sau '
                    . 'fă-ți unul acum: te aducem înapoi aici, iar abțibildul se '
                    . 'scrie pe numele tău.',
    ],
    'necunoscut' => [
        'fel'      => 'necunoscut',
        'eticheta' => 'Hm',
        'titlu'    => 'Codul ăsta nu ne spune nimic',
        'vorba'    => 'Ori s-a citit greșit, ori abțibildul nu e de la noi. '
                    . 'Încearcă să scanezi din nou, mai de aproape.',
    ],
    /**
     * S-a bâjbâit prea mult de la adresa asta. Vorba e blândă dinadins: cel
     * mai probabil e un om care a tastat de zece ori greșit, nu un program.
     * Iar dacă totuși e un program, nu-i spunem nimic din ce caută — nici
     * măcar dacă ultimul cod încercat exista.
     */
    'prea_des' => [
        'fel'      => 'necunoscut',
        'eticheta' => 'Prea multe încercări',
        'titlu'    => 'Hai să ne oprim puțin',
        'vorba'    => 'S-au încercat prea multe coduri de aici într-un timp scurt. '
                    . 'Mai așteaptă un ceas și încearcă din nou — sau scanează '
                    . 'abțibildul cu camera, în loc să scrii codul de mână.',
    ],
];

$v = $vorbe[$ce] ?? $vorbe['necunoscut'];

/**
 * Spre eveniment se poate trimite doar când chiar are rost: la un cod fără
 * eveniment n-ai unde trimite pe nimeni, iar la unul cu anunțul încă nepublicat
 * legătura ar fi dus la o pagină pe care omul n-are voie s-o vadă.
 */
$catreEveniment = '';

if ($cod !== null
    && $cod['ev_slug'] !== null
    && in_array((string) $cod['stare_moderare'], ['aprobat', 'incheiat'], true)) {
    $catreEveniment = urlEveniment((string) $cod['ev_slug']);
}

$titlu   = $v['titlu'] . ' — FindMe';
$pagina  = '';

/**
 * Pagina nu se indexează. Adresa ei poartă un cod care înseamnă un câștig: un
 * robot care ar trece pe-aici ar deschide-o exact ca un om, iar dacă ar fi și
 * conectat (nu e, dar) ar revendica abțibildul. În plus, o adresă de-asta
 * ajunsă în rezultatele căutării ar face jocul de prisos.
 */
$noindex = true;

require __DIR__ . '/inc/antet.php';
?>
<main id="main" class="findme-pagina">
  <div class="wrap wrap--ingust">

    <section class="fm-card fm-card--<?= h($v['fel']) ?>">

      <!-- Semnul de sus: bifă la câștig, lupă la restul. Desenat, nu emoji:
           emoji-urile arată altfel pe fiecare telefon, iar ăsta e primul lucru
           pe care îl vede omul. -->
      <span class="fm-card__semn" aria-hidden="true">
        <?php if ($ce === 'castigat'): ?>
        <svg class="ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/></svg>
        <?php else: ?>
        <svg class="ico" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/></svg>
        <?php endif; ?>
      </span>

      <p class="fm-card__eticheta"><?= h($v['eticheta']) ?></p>
      <h1 class="fm-card__titlu"><?= h($v['titlu']) ?></h1>
      <p class="fm-card__vorba"><?= h($v['vorba']) ?></p>

      <?php if ($ce === 'nelogat'): ?>
      <!--
        Înapoi exact aici, cu tot cu cod. Calea trece prin caleInterna() în
        cereIntrare/login.php, deci nu poate duce în altă parte.

        ATENȚIE la ce nu se promite: cât timp omul e la login, altcineva poate
        scana același abțibild și poate câștiga. Nu se ține deoparte pentru el —
        vezi antetul lui deCeNuSePoateRevendica(). De aceea butonul zice
        „Intră în cont", nu „Revendică".
      -->
      <?php $inapoi = '/findme.php?qr=' . urlencode(curataCodQr($codCerut)); ?>
      <div class="fm-card__butoane">
        <!-- Un singur buton: pagina de login are pe ea și „Creează unul", deci
             al doilea ar fi dus în același loc. -->
        <a class="btn btn--primary" href="/login.php?redirect=<?= h(urlencode($inapoi)) ?>">Intră în cont</a>
      </div>
      <?php elseif ($catreEveniment !== ''): ?>
      <div class="fm-card__butoane">
        <a class="btn btn--primary" href="<?= h($catreEveniment) ?>">Vezi evenimentul</a>
      </div>
      <?php if ($cod !== null && $cod['ev_titlu'] !== null): ?>
      <p class="fm-card__eveniment"><?= h((string) $cod['ev_titlu']) ?></p>
      <?php endif; ?>
      <?php else: ?>
      <div class="fm-card__butoane">
        <a class="btn btn--ghost" href="/index.php">Vezi ce se mai întâmplă în oraș</a>
      </div>
      <?php endif; ?>
    </section>

    <?php if (!in_array($ce, ['necunoscut', 'nepornit', 'nepublic', 'prea_des'], true)): ?>
    <!--
      Rugămintea de pe abțibild, spusă și aici.

      Se scrie ORICUI a ajuns la un abțibild al cărui rost s-a consumat: și
      câștigătorului, și celui care a ajuns al doilea, și celui venit după
      termen. Toți trei stau, în clipa aia, în fața aceleiași bucăți de hârtie
      care nu mai folosește nimănui.

      NU se scrie celui care a picat peste un abțibild înainte de vreme: acela
      trebuie să rămână pe stâlp. Și nici celui oprit de frână („prea_des"):
      el n-a găsit nimic, nu stă în fața niciunei hârtii, iar „dezlipește-l" nu
      i-ar spune decât că undeva există unul.
    -->
    <section class="fm-dezlipeste">
      <span class="fm-dezlipeste__semn" aria-hidden="true">
        <svg class="ico" viewBox="0 0 24 24">
          <path d="M5 4.5h9l5 5v10a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-14a1 1 0 0 1 1-1Z"/>
          <path d="M14 4.5v5h5"/>
        </svg>
      </span>
      <div>
        <p class="fm-dezlipeste__text">
          Acum că l-ai găsit și nu mai poate fi folosit,
          <strong>te rog frumos să dezlipești abțibildul.</strong>
        </p>
        <p class="fm-dezlipeste__multumesc">Mulțumesc! ❤️</p>
      </div>
    </section>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
