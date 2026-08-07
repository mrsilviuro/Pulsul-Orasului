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
              <?php
                $campId   = 'pn-veche';
                $campNume = 'parola_veche';
                $campText = 'Parola de acum';
                $campAjutor = 'Parola ta de acum';
                require __DIR__ . '/inc/camp-parola.php';
              ?>
              <?php endif; ?>

              <?php
                $campId    = 'pn-noua';
                $campNume  = 'parola';
                $campText  = 'Parola nouă';
                $campAjutor= 'Cel puțin 8 caractere';
                $campAuto  = 'new-password';
                $campMetru = true;
                require __DIR__ . '/inc/camp-parola.php';
              ?>

              <?php
                $campId    = 'pn-noua2';
                $campNume  = 'parola_confirmare';
                $campText  = 'Repetă parola nouă';
                $campAjutor= 'Aceeași parolă';
                $campAuto  = 'new-password';
                require __DIR__ . '/inc/camp-parola.php';
              ?>

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
