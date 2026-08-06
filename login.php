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

        <!-- ====================== TABURI DE SELECȚIE ==================== -->
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

        <!-- ======================== AUTENTIFICARE ====================== -->
        <div class="auth-panel is-active" id="panel-login" role="tabpanel" aria-labelledby="tab-login" tabindex="0">

          <div id="login-block">

          <h1 class="auth__title">Bine ai revenit</h1>
          <p class="auth__lead">Intră în cont ca să publici evenimente și să participi la discuții.</p>

          <!--
            Buton Google: când implementezi OAuth, pui aici href-ul către
            /auth/google (sau apelezi SDK-ul Google Identity din JS).
          -->
          <button class="btn-google" type="button" data-google="login">
            <svg class="btn-google__ico" viewBox="0 0 48 48" aria-hidden="true">
              <path fill="#4285F4" d="M45.1 24.5c0-1.6-.1-3.2-.4-4.7H24v8.9h11.8c-.5 2.7-2 5-4.4 6.6v5.5h7.1c4.2-3.8 6.6-9.5 6.6-16.3z"/>
              <path fill="#34A853" d="M24 46c6 0 11-2 14.6-5.3l-7.1-5.5c-2 1.3-4.5 2.1-7.5 2.1-5.8 0-10.7-3.9-12.4-9.1H4.3v5.7C7.9 41.1 15.4 46 24 46z"/>
              <path fill="#FBBC05" d="M11.6 28.2c-.5-1.3-.7-2.7-.7-4.2s.3-2.9.7-4.2v-5.7H4.3A22 22 0 0 0 2 24c0 3.6.9 6.9 2.3 9.9l7.3-5.7z"/>
              <path fill="#EA4335" d="M24 10.7c3.3 0 6.2 1.1 8.5 3.3l6.3-6.3C35 4.1 30 2 24 2 15.4 2 7.9 6.9 4.3 14.1l7.3 5.7c1.7-5.2 6.6-9.1 12.4-9.1z"/>
            </svg>
            <span>Continuă cu Google</span>
          </button>

          <div class="auth__divider"><span>sau cu e-mail</span></div>

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
                <a class="field__link" href="#">Ai uitat parola?</a>
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

            <p class="auth__switch">
              Nu ai cont încă?
              <button class="link-btn" type="button" data-go-tab="tab-register">Creează unul</button>
            </p>
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
              <a class="btn btn--primary" href="#">Mi-am uitat parola</a>
            </div>
          </div>
        </div>

        <!-- ========================= ÎNREGISTRARE ====================== -->
        <div class="auth-panel" id="panel-register" role="tabpanel" aria-labelledby="tab-register" tabindex="0" hidden>

          <div id="register-block">

          <h2 class="auth__title">Creează-ți cont</h2>
          <p class="auth__lead">Durează un minut. După aceea poți publica evenimente în oraș.</p>

          <button class="btn-google" type="button" data-google="register">
            <svg class="btn-google__ico" viewBox="0 0 48 48" aria-hidden="true">
              <path fill="#4285F4" d="M45.1 24.5c0-1.6-.1-3.2-.4-4.7H24v8.9h11.8c-.5 2.7-2 5-4.4 6.6v5.5h7.1c4.2-3.8 6.6-9.5 6.6-16.3z"/>
              <path fill="#34A853" d="M24 46c6 0 11-2 14.6-5.3l-7.1-5.5c-2 1.3-4.5 2.1-7.5 2.1-5.8 0-10.7-3.9-12.4-9.1H4.3v5.7C7.9 41.1 15.4 46 24 46z"/>
              <path fill="#FBBC05" d="M11.6 28.2c-.5-1.3-.7-2.7-.7-4.2s.3-2.9.7-4.2v-5.7H4.3A22 22 0 0 0 2 24c0 3.6.9 6.9 2.3 9.9l7.3-5.7z"/>
              <path fill="#EA4335" d="M24 10.7c3.3 0 6.2 1.1 8.5 3.3l6.3-6.3C35 4.1 30 2 24 2 15.4 2 7.9 6.9 4.3 14.1l7.3 5.7c1.7-5.2 6.6-9.1 12.4-9.1z"/>
            </svg>
            <span>Înregistrează-te cu Google</span>
          </button>

          <div class="auth__divider"><span>sau completează datele</span></div>

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
              <div class="field">
                <label for="rg-birthdate">Data nașterii</label>
                <input type="date" id="rg-birthdate" name="data_nasterii" autocomplete="bday"
                       required aria-describedby="err-rg-birthdate">
                <p class="field__error" id="err-rg-birthdate" hidden></p>
              </div>

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

      </div>

      <p class="auth__foot">
        Ai nevoie de ajutor? <a href="contact.php">Scrie-ne</a>.
      </p>

    </div>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
