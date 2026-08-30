<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — confirmarea ștergerii contului.
 *
 * Se ajunge aici din linkul trimis prin e-mail. Abia acum pornește răgazul.
 *
 * Ce NU se întâmplă aici: nu se șterge și nu se schimbă nicio dată a omului.
 * Numele, poza, tot ce a scris rămân neatinse cele treizeci de zile, tocmai ca
 * o intrare în cont să aducă totul înapoi exact cum era.
 */

require_once __DIR__ . '/inc/stergere.php';

$token = isset($_GET['token']) && is_string($_GET['token']) ? trim($_GET['token']) : '';

$membru = confirmaStergereaContului($token);

if ($membru === null) {
    $titlu  = 'Link invalid';
    $mesaj  = 'Linkul nu mai e bun — se poate să fi fost deja folosit, sau i-a trecut vremea. '
            . 'Poți cere altul din pagina de setări.';
    $reusit = false;
} else {
    $cand = candSeSterge(acum());

    emailStergereConfirmata(
        (string) $membru['email'],
        (string) $membru['prenume'],
        $cand,
        ZILE_RAGAZ_STERGERE
    );

    /**
     * Ieșirea din cont e parte din confirmare.
     *
     * Ar fi ciudat să rămână conectat un cont pe care tocmai l-a dat la
     * ștergere — și, mai rău, prima pagină deschisă ar trece prin autentifica()
     * și ar anula chiar cererea de acum două secunde.
     */
    deconecteaza();

    $titlu  = 'Contul tău va fi șters pe ' . $cand;
    $mesaj  = 'Până atunci nu se schimbă nimic. Dacă te răzgândești, e destul să intri '
            . 'din nou în cont: intrarea singură oprește ștergerea.';
    $reusit = true;
}

$titluPagina = $reusit ? 'Ștergerea contului — PulsulOrasului.Ro' : 'Link invalid — PulsulOrasului.Ro';

$titluVechi = $titlu;
$titlu      = $titluPagina;
$descriere  = 'Ștergerea contului de pe PulsulOrasului.Ro.';
$noindex    = true;
$pagina     = '';

require __DIR__ . '/inc/antet.php';
?>


<main id="main">
  <div class="wrap">
    <div class="auth">
      <div class="auth__card">
        <div class="auth-panel is-active">

          <div class="done <?= $reusit ? 'done--ok' : 'done--fail' ?>" tabindex="-1">
            <span class="done__ico" aria-hidden="true">
              <?php if ($reusit): ?>
              <svg class="ico" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.4 2"/>
              </svg>
              <?php else: ?>
              <svg class="ico" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>
              </svg>
              <?php endif; ?>
            </span>

            <h1 class="done__title"><?= h($titluVechi) ?></h1>
            <p class="done__text"><?= h($mesaj) ?></p>

            <?php if ($reusit): ?>
            <p class="done__hint">
              Ți-am trimis și un e-mail cu data scrisă, ca să nu fie nevoie să o ții minte.
            </p>
            <?php endif; ?>

            <div class="done__actions">
              <?php if ($reusit): ?>
              <a class="btn btn--primary" href="/login.php">M-am răzgândit, intru în cont</a>
              <a class="btn btn--ghost" href="/index.php">Mergi pe prima pagină</a>
              <?php else: ?>
              <a class="btn btn--primary" href="/setari.php">Mergi la setări</a>
              <a class="btn btn--ghost" href="/index.php">Mergi pe prima pagină</a>
              <?php endif; ?>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
