<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — schimbarea parolei.
 *
 * Se ajunge aici în două feluri: fie imediat după intrarea cu parola
 * temporară (și atunci e obligatoriu de trecut pe aici), fie din cont, când
 * omul vrea pur și simplu altă parolă.
 */

require_once __DIR__ . '/inc/auth.php';

$membru = membruCurent();

if ($membru === null) {
    header('Location: login.php?redirect=' . urlencode('/parola-noua.php'));
    exit;
}

// Dacă a intrat cu parola temporară, nu i se cere și parola veche: tocmai aia
// e cea uitată.
$dupaTemporara = trebuieParolaNoua();

$titlu     = 'Schimbă-ți parola — PulsulOrasului.Ro';
$descriere = 'Alege o parolă nouă pentru contul tău.';
$noindex   = true;

$csrf = tokenCsrf();

require __DIR__ . '/inc/antet.php';
?>


<main id="main">
  <div class="wrap">
    <div class="auth">
      <div class="auth__card">
        <div class="auth-panel is-active">

          <div id="parola-block">

            <?php if ($dupaTemporara): ?>
            <p class="auth__notice auth__notice--ok">
              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
              </svg>
              <span>Ai intrat cu parola temporară.</span>
            </p>
            <?php endif; ?>

            <h1 class="auth__title"><?= $dupaTemporara ? 'Alege-ți o parolă nouă' : 'Schimbă-ți parola' ?></h1>
            <p class="auth__lead">
              <?php if ($dupaTemporara): ?>
                Parola temporară s-a consumat. Pune una pe care o ții minte —
                până atunci nu poți face altceva în cont.
              <?php else: ?>
                Scrie parola de acum, apoi pe cea nouă, de două ori.
              <?php endif; ?>
            </p>

            <form class="form" id="parola-form" novalidate
                  data-dupa-temporara="<?= $dupaTemporara ? 'true' : 'false' ?>">

              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

              <?php if (!$dupaTemporara): ?>
              <div class="field">
                <label for="pn-veche">Parola de acum</label>
                <div class="input-pass">
                  <input type="password" id="pn-veche" name="parola_veche"
                         autocomplete="current-password" placeholder="Parola ta de acum"
                         required aria-describedby="err-pn-veche">
                  <button class="input-pass__toggle" type="button" data-toggle-pass="pn-veche"
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
                <p class="field__error" id="err-pn-veche" hidden></p>
              </div>
              <?php endif; ?>

              <div class="field">
                <label for="pn-noua">Parola nouă</label>
                <div class="input-pass">
                  <input type="password" id="pn-noua" name="parola" autocomplete="new-password"
                         placeholder="Cel puțin 8 caractere" required aria-describedby="err-pn-noua">
                  <button class="input-pass__toggle" type="button" data-toggle-pass="pn-noua"
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
                <!-- Aceeași structură ca la înregistrare, deci același CSS și
                     același cod din main.js. -->
                <div class="pass-meter" id="pn-meter" hidden>
                  <div class="pass-meter__bars" aria-hidden="true">
                    <span></span><span></span><span></span><span></span>
                  </div>
                  <span class="pass-meter__label" id="pn-hint" role="status"></span>
                </div>
                <p class="field__error" id="err-pn-noua" hidden></p>
              </div>

              <div class="field">
                <label for="pn-noua2">Repetă parola nouă</label>
                <div class="input-pass">
                  <input type="password" id="pn-noua2" name="parola_confirmare"
                         autocomplete="new-password" placeholder="Aceeași parolă"
                         required aria-describedby="err-pn-noua2">
                  <button class="input-pass__toggle" type="button" data-toggle-pass="pn-noua2"
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
                <p class="field__error" id="err-pn-noua2" hidden></p>
              </div>

              <button class="btn btn--primary btn--block" type="submit">Salvează parola</button>

              <?php if ($dupaTemporara): ?>
              <p class="auth__switch">
                Nu ai cerut tu asta?
                <a class="link-btn" href="iesire.php?token=<?= h(urlencode(tokenCsrf())) ?>">Ieși din cont</a>
              </p>
              <?php endif; ?>
            </form>
          </div><!-- /#parola-block -->

          <!-- ==================== MESAJUL DE DUPĂ ==================== -->
          <div class="done" id="parola-done" hidden>
            <span class="done__ico" aria-hidden="true">
              <svg class="ico" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
              </svg>
            </span>

            <h2 class="done__title">Parola a fost schimbată</h2>
            <p class="done__text">
              De acum intri în cont cu parola nouă. Ți-am trimis și un e-mail de
              înștiințare — dacă nu tu ai făcut schimbarea, scrie-ne imediat.
            </p>

            <div class="done__actions">
              <a class="btn btn--primary" href="index.php">Mergi pe prima pagină</a>
              <a class="btn btn--ghost" href="profil.php">Vezi-ți profilul</a>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
