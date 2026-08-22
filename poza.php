<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — schimbarea pozei de profil.
 *
 * Se ajunge aici din creionul de sub poza de pe pagina de profil.
 */

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/imagini.php';

$membru = membruCurent();

// Pagina e a contului propriu, deci fără cont nu are ce arăta.
if ($membru === null) {
    cereIntrare('/poza.php');
}

$titlu     = 'Poza de profil — PulsulOrasului.Ro';
$descriere = 'Alege-ți poza de profil.';
$noindex   = true;

// Token-ul se cere înaintea antetului: după ce pagina începe să se tipărească,
// sesiunea nu mai poate fi pornită.
$csrf = tokenCsrf();

$arePoza = estePozaValida($membru['poza'] ?? null);

require __DIR__ . '/inc/antet.php';
?>


<main id="main">
  <div class="wrap">

    <nav class="crumbs" aria-label="Navigare">
      <a href="/index.php">Acasă</a>
      <span aria-hidden="true">/</span>
      <a href="/profil.php">Profilul tău</a>
      <span aria-hidden="true">/</span>
      <span class="crumbs__current">Poza de profil</span>
    </nav>

    <div class="poza-card">

      <h1 class="poza-card__titlu">Poza ta de profil</h1>
      <p class="poza-card__lead">
        Alege o poză, potrivește-o în cerc și salveaz-o. Se va vedea lângă numele
        tău, la comentarii și la evenimentele la care participi.
      </p>

      <!-- ====================== POZA DE ACUM ========================= -->
      <div class="poza-acum" id="poza-acum">
        <img class="poza-acum__img" id="poza-acum-img"
             src="<?= h(urlPoza($membru['poza'] ?? null)) ?>"
             alt="Poza ta de profil de acum" width="64" height="64">
        <div class="poza-acum__text">
          <strong id="poza-acum-titlu"><?= $arePoza ? 'Poza de acum' : 'Nu ai încă nicio poză' ?></strong>
          <span id="poza-acum-sub"><?= $arePoza
              ? 'Poți să o înlocuiești sau să o ștergi.'
              : 'Până alegi una, se vede silueta asta.' ?></span>
        </div>
        <!--
          Ștergerea cere o confirmare, dar nu una desenată de browser: o
          fereastră window.confirm() arată altfel pe Windows, pe Android și pe
          iPhone, iar noi vrem aceeași interfață peste tot. Așa că butonul își
          schimbă locul cu întrebarea, chiar aici în rând.
        -->
        <button class="btn btn--ghost btn--sm" type="button" id="poza-sterge"
                <?= $arePoza ? '' : 'hidden' ?>>Șterge</button>

        <span class="poza-sigur" id="poza-sigur" hidden>
          <span class="poza-sigur__text">Sigur?</span>
          <button class="btn btn--rau btn--sm" type="button" id="poza-sterge-da">Da, șterge</button>
          <button class="btn btn--ghost btn--sm" type="button" id="poza-sterge-nu">Nu</button>
        </span>
      </div>

      <!-- ====================== ALEGEREA FIȘIERULUI =================== -->
      <label class="poza-drop" id="poza-drop" for="poza-fisier">
        <span class="poza-drop__ico" aria-hidden="true">
          <svg class="ico" viewBox="0 0 24 24">
            <path d="M12 16.5V4.5"/><path d="m7.5 9 4.5-4.5L16.5 9"/>
            <path d="M4 15v3.5A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V15"/>
          </svg>
        </span>
        <span class="poza-drop__titlu">Alege o poză sau trage fișierul aici</span>
        <span class="poza-drop__hint">JPG, PNG sau WEBP, cel mai mult <?= (int) (POZA_OCTETI_MAX / 1024 / 1024) ?> MB</span>
        <!--
          accept e doar o ușurare pentru cel care alege fișierul: îi arată din
          prima doar pozele. Nu e o verificare — serverul se uită oricum la
          conținutul fișierului, nu la ce scrie aici.
        -->
        <input type="file" id="poza-fisier" name="poza"
               accept="image/jpeg,image/png,image/webp">
      </label>

      <!-- ========================= DECUPAREA ========================== -->
      <div class="crop" id="crop" hidden>
        <div class="crop__stage" id="crop-stage">
          <img class="crop__img" id="crop-img" alt="Poza aleasă, de potrivit în cerc">
          <div class="crop__mask" aria-hidden="true"></div>
        </div>

        <div class="crop__zoom">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="8.5" r="3.6"/><path d="M5 20c0-4 3.1-6.6 7-6.6s7 2.6 7 6.6"/>
          </svg>
          <label class="sr-only" for="crop-zoom">Mărimea pozei</label>
          <input type="range" id="crop-zoom" min="1" max="4" step="0.01" value="1">
          <svg class="ico ico--mare" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="8.5" r="3.6"/><path d="M5 20c0-4 3.1-6.6 7-6.6s7 2.6 7 6.6"/>
          </svg>
        </div>

        <p class="crop__ajutor">Trage de poză ca să o miști. Din bară o mărești sau o micșorezi.</p>
      </div>

      <!-- ========================== MESAJE ============================ -->
      <p class="poza-mesaj" id="poza-mesaj" role="status" aria-live="polite" hidden>
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true" id="poza-mesaj-ico">
          <circle cx="12" cy="12" r="9"/><path d="M12 11v5.5"/><path d="M12 7.6v.1"/>
        </svg>
        <span id="poza-mesaj-text"></span>
      </p>

      <!-- ========================= BUTOANELE ========================== -->
      <div class="poza-actiuni">
        <a class="btn btn--ghost" href="/profil.php">Înapoi la profil</a>
        <button class="btn btn--primary" type="button" id="poza-salveaza" disabled>
          Salvează poza
        </button>
      </div>

      <ul class="poza-reguli">
        <li>
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/></svg>
          <span>Poza se micșorează automat, ca să se încarce repede pentru toată lumea.</span>
        </li>
        <li>
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/></svg>
          <span>Datele ascunse în fișier — inclusiv locul unde a fost făcută poza — sunt șterse la salvare.</span>
        </li>
        <li>
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/></svg>
          <span>Alege o poză în care se vede clar cine ești. Fără poze cu alți oameni fără voia lor.</span>
        </li>
      </ul>

      <input type="hidden" id="poza-csrf" value="<?= h($csrf) ?>">

    </div>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
