<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — ultimul pas la înregistrarea cu Google.
 *
 * Google ne spune numele și adresa, dar nu și data nașterii sau sexul — iar
 * pe acelea le arătăm pe pagina de profil. Aici i le cerem, o singură dată.
 *
 * Contul se creează abia la trimiterea formularului, în api/finalizare-google.php.
 * Până atunci nu există nimic în baza de date: dacă omul închide fila acum, nu
 * rămâne în urmă un cont pe jumătate.
 */

require_once __DIR__ . '/inc/auth.php';

if (esteLogat()) {
    header('Location: index.php');
    exit;
}

pornesteSesiunea();
$nou = $_SESSION['google_nou'] ?? null;

/**
 * Fără datele din sesiune, pagina n-are ce arăta.
 *
 * Se întâmplă dacă cineva o deschide direct, sau dacă a stat prea mult și
 * sesiunea s-a închis. Îl trimitem să ia drumul de la capăt.
 */
if (!is_array($nou) || empty($nou['sub']) || empty($nou['email'])) {
    $_SESSION['google_necaz'] = 'A trecut prea mult timp. Încearcă din nou cu Google.';
    header('Location: login.php');
    exit;
}

// Un sfert de oră e destul pentru completarea a două câmpuri.
if ((time() - (int) ($nou['la'] ?? 0)) > 900) {
    unset($_SESSION['google_nou']);
    $_SESSION['google_necaz'] = 'A trecut prea mult timp. Încearcă din nou cu Google.';
    header('Location: login.php');
    exit;
}

$titlu     = 'Încă un pas — PulsulOrasului.Ro';
$descriere = 'Completează ultimele două date și contul e gata.';
$pagina    = 'cont';
$noindex   = true;

$csrf = tokenCsrf();

require __DIR__ . '/inc/antet.php';
?>


<main id="main">
  <div class="wrap">
    <div class="auth">
      <div class="auth__card">
        <div class="auth-panel is-active">

          <div id="final-block">

            <p class="auth__notice auth__notice--ok">
              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
              </svg>
              <span>Google ne-a confirmat adresa <strong><?= h($nou['email']) ?></strong>.</span>
            </p>

            <h1 class="auth__title">Încă un pas</h1>
            <p class="auth__lead">
              Verifică numele și mai completează două date. Apar pe profilul tău,
              lângă evenimentele la care participi.
            </p>

            <form class="form" id="final-form" novalidate>

              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

              <div class="field-row">
                <div class="field">
                  <label for="fn-lastname">Nume</label>
                  <input type="text" id="fn-lastname" name="nume" autocomplete="family-name"
                         value="<?= h($nou['nume']) ?>" placeholder="Popescu"
                         required aria-describedby="err-fn-lastname">
                  <p class="field__error" id="err-fn-lastname" hidden></p>
                </div>

                <div class="field">
                  <label for="fn-firstname">Prenume</label>
                  <input type="text" id="fn-firstname" name="prenume" autocomplete="given-name"
                         value="<?= h($nou['prenume']) ?>" placeholder="Ionuț"
                         required aria-describedby="err-fn-firstname">
                  <p class="field__error" id="err-fn-firstname" hidden></p>
                </div>
              </div>

              <!-- Aceleași clase ca la înregistrare, deci același CSS:
                   iconița de calendar și săgeata de la „Sex" sunt desenate de
                   noi, din stilurile câmpurilor. -->
              <div class="field-row">
                <div class="field">
                  <label for="fn-birthdate">Data nașterii</label>
                  <input type="date" id="fn-birthdate" name="data_nasterii" autocomplete="bday"
                         required aria-describedby="err-fn-birthdate">
                  <p class="field__error" id="err-fn-birthdate" hidden></p>
                </div>

                <div class="field">
                  <label for="fn-gender">Sex</label>
                  <select id="fn-gender" name="sex" required aria-describedby="err-fn-gender">
                    <option value="" selected disabled>Alege…</option>
                    <option value="F">Feminin</option>
                    <option value="M">Masculin</option>
                  </select>
                  <p class="field__error" id="err-fn-gender" hidden></p>
                </div>
              </div>

              <!-- Bifa stă într-un „.field", ca la înregistrare: de el se
                   agață mesajul de eroare (vezi setError din main.js). -->
              <div class="field">
                <label class="check">
                  <input type="checkbox" id="fn-terms" name="termeni"
                         aria-describedby="err-fn-terms">
                  <span>Sunt de acord cu <a href="#">Termenii</a> și cu
                        <a href="#">Politica de confidențialitate</a>.</span>
                </label>
                <p class="field__error" id="err-fn-terms" hidden></p>
              </div>

              <button class="btn btn--primary btn--block" type="submit">Gata, creează contul</button>

              <p class="auth__switch">
                Te-ai răzgândit?
                <a class="link-btn" href="login.php">Înapoi la pagina de cont</a>
              </p>
            </form>
          </div><!-- /#final-block -->

        </div>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
