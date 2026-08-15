<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — autentificare și înregistrare.
 *
 * Ambele formulare sunt trimise din JavaScript către api/, ca pagina să nu se
 * reîncarce.
 */

require_once __DIR__ . '/inc/auth.php';

/**
 * Pagina nu are ce căuta în fața cuiva deja conectat, așa că îl trimitem
 * acasă. Deconectarea se face doar din iesire.php, apăsând pe „Deloghează-te".
 *
 * De ce nu deconectăm aici: un semn de carte, o intrare veche din istoric sau
 * o preîncărcare făcută de browser ar da omul afară fără ca el să fi cerut-o.
 */
if (esteLogat()) {
    header('Location: index.php');
    exit;
}

$tocmaiIesit = isset($_GET['iesit']);

/**
 * Cât timp site-ul e în lucru, pagina asta e singura ușă — dar e mai îngustă.
 *
 * Se scot din ea cele două drumuri care n-ar duce nicăieri: înregistrarea
 * (api/inregistrare.php e închis) și intrarea cu Google (google.php la fel,
 * fiindcă drumul prin el se poate termina cu un cont NOU, iar cât e închis nu
 * se fac conturi noi).
 *
 * Le scoatem din pagină, nu le ascundem cu CSS: un formular care se vede și
 * nu merge e mai supărător decât unul care lipsește. Cine e de-al casei intră
 * cu e-mail și parolă, iar cine nu e n-are ce face aici nici într-un fel, nici
 * în altul — vezi api/autentificare.php.
 */
$inConstructie = siteInConstructie();

/**
 * Unde vrea omul să ajungă după ce intră.
 *
 * Se ia din adresă și se dă mai departe butonului de Google, care pleacă de pe
 * server, nu din JavaScript. Se acceptă doar căi de pe site-ul nostru — regula
 * întreagă e în caleInterna(), din inc/validare.php.
 */
$inapoiLa = caleInterna($_GET['redirect'] ?? null);

// Necazul lăsat de google.php, dacă întoarcerea de la Google n-a mers.
pornesteSesiunea();
$necazGoogle = (string) ($_SESSION['google_necaz'] ?? '');
unset($_SESSION['google_necaz']);

$titlu     = 'Cont — PulsulOrasului.Ro';
$descriere = 'Intră în cont sau creează-ți unul, ca să publici evenimente și să participi la discuții.';
$pagina    = 'cont';
$noindex   = true;

// Token-ul se cere înaintea antetului: odată ce a început tipărirea paginii,
// sesiunea nu mai poate fi pornită, iar PHP ar da avertismente.
$csrf = tokenCsrf();

require __DIR__ . '/inc/antet.php';
?>


<main id="main">
  <div class="wrap">
    <div class="auth">

      <?php if ($necazGoogle !== ''): ?>
      <p class="auth__notice auth__notice--rau">
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5"/><path d="M12 16.4v.1"/>
        </svg>
        <span><?= h($necazGoogle) ?></span>
      </p>
      <?php endif; ?>

      <?php if ($tocmaiIesit): ?>
      <p class="auth__notice auth__notice--ok">
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
        </svg>
        <span>Ai ieșit din cont.</span>
      </p>
      <?php endif; ?>

      <!-- mesaj afișat când ai fost trimis aici de pe altă pagină -->
      <p class="auth__notice" id="auth-notice" hidden>
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="12" r="9"/><path d="M12 11v5.5"/><path d="M12 7.6v.1"/>
        </svg>
        <span>Intră în cont ca să continui.</span>
      </p>

      <div class="auth__card">

        <!-- ====================== TABURI DE SELECȚIE ====================
          Cât e site-ul în lucru rămâne doar autentificarea: conturile noi nu
          se pot face, deci un tab „Înregistrare" ar fi fost un drum înfundat.
        ============================================================== -->
        <?php if (!$inConstructie): ?>
        <div class="auth-tabs" role="tablist" data-tabs id="auth-tabs" aria-label="Autentificare sau înregistrare">
          <button class="auth-tab is-active" type="button" role="tab" id="tab-login"
                  aria-controls="panel-login" aria-selected="true" tabindex="0">
            Autentificare
          </button>
          <button class="auth-tab" type="button" role="tab" id="tab-register"
                  aria-controls="panel-register" aria-selected="false" tabindex="-1">
            Înregistrare
          </button>
        </div>
        <?php endif; ?>

        <!-- ======================== AUTENTIFICARE ====================== -->
        <div class="auth-panel is-active" id="panel-login" role="tabpanel" aria-labelledby="tab-login" tabindex="0">

          <div id="login-block">

          <?php if ($inConstructie): ?>
          <h1 class="auth__title">Site-ul e în lucru</h1>
          <p class="auth__lead">
            Deocamdată intră doar echipa. Dacă ai ajuns aici din greșeală,
            <a href="constructie.php">lasă-ne adresa</a> și îți dăm de veste
            când deschidem.
          </p>
          <?php else: ?>
          <h1 class="auth__title">Bine ai revenit</h1>
          <p class="auth__lead">Intră în cont ca să publici evenimente și să participi la discuții.</p>

          <?php
            $textButon       = 'Continuă cu Google';
            $textDespartitor = 'sau cu e-mail';
            $redirectDupa    = $inapoiLa;
            require __DIR__ . '/inc/buton-google.php';
          ?>
          <?php endif; ?>

          <form class="form" id="login-form" novalidate>

            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

            <div class="field">
              <label for="lg-email">Adresa de e-mail</label>
              <input type="email" id="lg-email" name="email" autocomplete="email"
                     placeholder="adresa@email.ro" required aria-describedby="err-lg-email">
              <p class="field__error" id="err-lg-email" hidden></p>
            </div>

            <div class="field">
              <div class="field__head">
                <label for="lg-password">Parola</label>
                <a class="field__link" href="parola-uitata.php">Ai uitat parola?</a>
              </div>
              <div class="input-pass">
                <input type="password" id="lg-password" name="parola" autocomplete="current-password"
                       placeholder="Parola ta" required aria-describedby="err-lg-password">
                <button class="input-pass__toggle" type="button" data-toggle-pass="lg-password"
                        aria-label="Arată parola" aria-pressed="false">
                  <svg class="ico ico--eye" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M2.5 12S6 5.8 12 5.8 21.5 12 21.5 12 18 18.2 12 18.2 2.5 12 2.5 12Z"/>
                    <circle cx="12" cy="12" r="3.2"/>
                  </svg>
                  <svg class="ico ico--eye-off" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9.9 5.9A9.6 9.6 0 0 1 12 5.8c6 0 9.5 6.2 9.5 6.2a17 17 0 0 1-3.5 4.2M6 7.8A17 17 0 0 0 2.5 12S6 18.2 12 18.2c1.4 0 2.6-.3 3.7-.8"/>
                    <path d="m4 4 16 16"/>
                  </svg>
                </button>
              </div>
              <p class="field__error" id="err-lg-password" hidden></p>
            </div>

            <label class="check">
              <input type="checkbox" id="lg-remember" name="tine_minte" checked>
              <span>Ține-mă minte pe acest dispozitiv</span>
            </label>

            <button class="btn btn--primary btn--block" type="submit">Intră în cont</button>

            <?php if (!$inConstructie): ?>
            <p class="auth__switch">
              Nu ai cont încă?
              <button class="link-btn" type="button" data-go-tab="tab-register">Creează unul</button>
            </p>
            <?php endif; ?>
          </form>
          </div><!-- /#login-block -->

          <!-- ================= CONTUL NU E ÎNCĂ ACTIVAT =================
            Apare când parola e corectă, dar adresa nu a fost confirmată.
          ======================================================== -->
          <div class="done" id="login-neconfirmat" hidden>
            <span class="done__ico" aria-hidden="true">
              <svg class="ico" viewBox="0 0 24 24">
                <rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.8 7 8.2 5.6L20.2 7"/>
              </svg>
            </span>

            <h2 class="done__title">Contul nu e încă activat</h2>
            <p class="done__text">
              Ți-am trimis un e-mail de confirmare la
              <strong id="neconfirmat-email">adresa ta</strong>.
              Deschide-l și apasă pe link ca să îți activezi contul.
            </p>
            <p class="done__hint">Nu găsești mesajul? Uită-te și în „Spam" sau „Promoții".</p>

            <p class="done__dev" id="neconfirmat-dev" hidden>
              <span>Mod dezvoltare — linkul de confirmare:</span>
              <a href="#" id="neconfirmat-link">link</a>
            </p>

            <div class="done__actions">
              <button class="btn btn--primary" type="button" id="btn-retrimite">
                Trimite din nou e-mailul
              </button>
              <button class="btn btn--ghost" type="button" id="btn-inapoi-login">
                Înapoi la autentificare
              </button>
            </div>
          </div>

          <!-- ===================== FORMULAR BLOCAT =====================
            Apare după prea multe încercări greșite.
          ======================================================== -->
          <div class="done done--fail" id="login-blocat" hidden>
            <span class="done__ico" aria-hidden="true">
              <svg class="ico" viewBox="0 0 24 24">
                <rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/>
                <path d="M8 10.5V7.8a4 4 0 0 1 8 0v2.7"/>
              </svg>
            </span>

            <h2 class="done__title">Prea multe încercări</h2>
            <p class="done__text">
              Din motive de siguranță, formularul e închis temporar.
              Mai poți încerca peste <strong id="blocat-timp">10 minute</strong>.
            </p>
            <p class="done__hint">Ți-ai uitat parola? O poți schimba, ca să nu mai aștepți.</p>

            <div class="done__actions">
              <a class="btn btn--primary" href="parola-uitata.php">Mi-am uitat parola</a>
            </div>
          </div>
        </div>

        <!-- ========================= ÎNREGISTRARE ======================
          Lipsește cu totul cât e site-ul în lucru: api/inregistrare.php e
          închis, deci formularul n-ar avea unde să trimită. Scos din pagină,
          nu ascuns — unul care se vede și nu merge e mai supărător decât unul
          care nu e.
        ============================================================== -->
        <?php if (!$inConstructie): ?>
        <div class="auth-panel" id="panel-register" role="tabpanel" aria-labelledby="tab-register" tabindex="0" hidden>

          <div id="register-block">

          <h2 class="auth__title">Creează-ți cont</h2>
          <p class="auth__lead">Durează un minut. După aceea poți publica evenimente în oraș.</p>

          <?php
            $textButon       = 'Înregistrează-te cu Google';
            $textDespartitor = 'sau completează datele';
            $redirectDupa    = $inapoiLa;
            require __DIR__ . '/inc/buton-google.php';
          ?>

          <form class="form" id="register-form" novalidate>

            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

            <div class="field-row">
              <div class="field">
                <label for="rg-lastname">Nume</label>
                <input type="text" id="rg-lastname" name="nume" autocomplete="family-name"
                       placeholder="Popescu" required aria-describedby="err-rg-lastname">
                <p class="field__error" id="err-rg-lastname" hidden></p>
              </div>

              <div class="field">
                <label for="rg-firstname">Prenume</label>
                <input type="text" id="rg-firstname" name="prenume" autocomplete="given-name"
                       placeholder="Ion" required aria-describedby="err-rg-firstname">
                <p class="field__error" id="err-rg-firstname" hidden></p>
              </div>
            </div>

            <div class="field">
              <label for="rg-email">Adresa de e-mail</label>
              <input type="email" id="rg-email" name="email" autocomplete="email"
                     placeholder="adresa@email.ro" required aria-describedby="err-rg-email">
              <p class="field__error" id="err-rg-email" hidden></p>
            </div>

            <div class="field-row">
              <!-- Același câmp de dată ca la eveniment: text scris ZZ-LL-AAAA,
                   cu calendarul la un buton distanță. Lămurirea stă o singură
                   dată, în inc/camp-data.php. Calendarul se deschide între
                   marginile în care o dată de naștere are sens. -->
              <?php
              $dataId      = 'rg-birthdate';
              $dataNume    = 'data_nasterii';
              $dataText    = 'Data nașterii';
              $dataExemplu = '25-12-1990';
              $dataAuto    = 'bday';
              $dataMin     = date('Y-m-d', strtotime('-' . VARSTA_MAX . ' years'));
              $dataMax     = date('Y-m-d', strtotime('-' . VARSTA_MIN . ' years'));
              require __DIR__ . '/inc/camp-data.php';
              ?>

              <div class="field">
                <label for="rg-gender">Sex</label>
                <select id="rg-gender" name="sex" required aria-describedby="err-rg-gender">
                  <option value="" selected disabled>Alege…</option>
                  <option value="F">Feminin</option>
                  <option value="M">Masculin</option>
                </select>
                <p class="field__error" id="err-rg-gender" hidden></p>
              </div>
            </div>

            <div class="field">
              <label for="rg-password">Parola</label>
              <div class="input-pass">
                <input type="password" id="rg-password" name="parola" autocomplete="new-password"
                       placeholder="Minimum 8 caractere" required aria-describedby="err-rg-password pass-hint">
                <button class="input-pass__toggle" type="button" data-toggle-pass="rg-password"
                        aria-label="Arată parola" aria-pressed="false">
                  <svg class="ico ico--eye" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M2.5 12S6 5.8 12 5.8 21.5 12 21.5 12 18 18.2 12 18.2 2.5 12 2.5 12Z"/>
                    <circle cx="12" cy="12" r="3.2"/>
                  </svg>
                  <svg class="ico ico--eye-off" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9.9 5.9A9.6 9.6 0 0 1 12 5.8c6 0 9.5 6.2 9.5 6.2a17 17 0 0 1-3.5 4.2M6 7.8A17 17 0 0 0 2.5 12S6 18.2 12 18.2c1.4 0 2.6-.3 3.7-.8"/>
                    <path d="m4 4 16 16"/>
                  </svg>
                </button>
              </div>

              <!-- indicator de putere, actualizat din JS -->
              <div class="pass-meter" id="pass-meter" hidden>
                <div class="pass-meter__bars" aria-hidden="true">
                  <span></span><span></span><span></span><span></span>
                </div>
                <span class="pass-meter__label" id="pass-hint" role="status"></span>
              </div>

              <p class="field__error" id="err-rg-password" hidden></p>
            </div>

            <div class="field">
              <label for="rg-password2">Confirmă parola</label>
              <div class="input-pass">
                <input type="password" id="rg-password2" name="parola_confirmare" autocomplete="new-password"
                       placeholder="Scrie parola din nou" required aria-describedby="err-rg-password2">
                <button class="input-pass__toggle" type="button" data-toggle-pass="rg-password2"
                        aria-label="Arată parola" aria-pressed="false">
                  <svg class="ico ico--eye" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M2.5 12S6 5.8 12 5.8 21.5 12 21.5 12 18 18.2 12 18.2 2.5 12 2.5 12Z"/>
                    <circle cx="12" cy="12" r="3.2"/>
                  </svg>
                  <svg class="ico ico--eye-off" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9.9 5.9A9.6 9.6 0 0 1 12 5.8c6 0 9.5 6.2 9.5 6.2a17 17 0 0 1-3.5 4.2M6 7.8A17 17 0 0 0 2.5 12S6 18.2 12 18.2c1.4 0 2.6-.3 3.7-.8"/>
                    <path d="m4 4 16 16"/>
                  </svg>
                </button>
              </div>
              <p class="field__error" id="err-rg-password2" hidden></p>
            </div>

            <div class="field">
              <label class="check">
                <input type="checkbox" id="rg-terms" name="termeni" aria-describedby="err-rg-terms">
                <span>Sunt de acord cu <a href="#">Termenii</a> și cu <a href="#">Politica de confidențialitate</a>.</span>
              </label>
              <p class="field__error" id="err-rg-terms" hidden></p>
            </div>

            <button class="btn btn--primary btn--block" type="submit">Creează contul</button>

            <p class="auth__switch">
              Ai deja cont?
              <button class="link-btn" type="button" data-go-tab="tab-login">Autentifică-te</button>
            </p>
          </form>
          </div><!-- /#register-block -->

          <!-- =================== DUPĂ ÎNREGISTRARE ====================
            Apare în locul formularului, fără reîncărcarea paginii.
          ======================================================== -->
          <div class="done" id="register-done" hidden>
            <span class="done__ico" aria-hidden="true">
              <svg class="ico" viewBox="0 0 24 24">
                <rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.8 7 8.2 5.6L20.2 7"/>
              </svg>
            </span>

            <h2 class="done__title">Verifică-ți e-mailul</h2>
            <p class="done__text">
              Ți-am trimis un mesaj de confirmare la
              <strong id="register-done-email">adresa ta</strong>.
              Deschide-l și apasă pe link ca să îți activezi contul.
            </p>
            <p class="done__hint">
              Nu găsești mesajul? Uită-te și în „Spam" sau „Promoții".
            </p>

            <!-- Doar în dezvoltare: linkul apare aici, fiindcă în XAMPP nu
                 există server de e-mail. -->
            <p class="done__dev" id="register-done-dev" hidden>
              <span>Mod dezvoltare — linkul de confirmare:</span>
              <a href="#" id="register-done-link">link</a>
            </p>

            <div class="done__actions">
              <a class="btn btn--ghost" href="index.php">Mergi la prima pagină</a>
            </div>
          </div>
        </div>
        <?php endif; ?>

      </div>

      <?php /* contact.php e închis cât ține lucrarea, deci n-are rost trimis
               nimeni acolo — vezi inc/constructie.php. */ ?>
      <?php if (!$inConstructie): ?>
      <p class="auth__foot">
        Ai nevoie de ajutor? <a href="contact.php">Scrie-ne</a>.
      </p>
      <?php endif; ?>

    </div>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
