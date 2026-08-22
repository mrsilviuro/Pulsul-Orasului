<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — „mi-am uitat parola".
 *
 * Se cere adresa de e-mail, iar dacă are cont activ pleacă acolo o parolă
 * temporară de șase caractere, bună o oră și o singură dată.
 */

require_once __DIR__ . '/inc/auth.php';

// Cine e deja conectat nu are ce căuta aici: își schimbă parola din cont.
if (esteLogat()) {
    header('Location: /parola-noua.php');
    exit;
}

$titlu     = 'Ți-ai uitat parola — PulsulOrasului.Ro';
$descriere = 'Îți trimitem pe e-mail o parolă temporară, ca să poți intra în cont.';
$pagina    = 'cont';
$noindex   = true;

// Token-ul se cere înaintea antetului: după ce pagina începe să se tipărească,
// sesiunea nu mai poate fi pornită.
$csrf = tokenCsrf();

require __DIR__ . '/inc/antet.php';
?>


<main id="main">
  <div class="wrap">
    <div class="auth">
      <div class="auth__card">
        <div class="auth-panel is-active">

          <div id="uitata-block">

            <h1 class="auth__title">Ți-ai uitat parola</h1>
            <p class="auth__lead">
              Scrie adresa cu care te-ai înscris. Îți trimitem acolo o parolă
              temporară, cu care intri în cont și îți alegi una nouă.
            </p>

            <form class="form" id="uitata-form" novalidate>

              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

              <div class="field">
                <label for="uit-email">Adresa de e-mail</label>
                <input type="email" id="uit-email" name="email" autocomplete="email"
                       placeholder="adresa@email.ro" required aria-describedby="err-uit-email">
                <p class="field__error" id="err-uit-email" hidden></p>
              </div>

              <button class="btn btn--primary btn--block" type="submit">
                Trimite-mi o parolă temporară
              </button>

              <p class="auth__switch">
                Ți-ai adus aminte?
                <a class="link-btn" href="/login.php">Înapoi la autentificare</a>
              </p>
            </form>
          </div><!-- /#uitata-block -->

          <!-- ==================== MESAJUL DE DUPĂ ==================== -->
          <div class="done" id="uitata-done" hidden>
            <span class="done__ico" aria-hidden="true">
              <svg class="ico" viewBox="0 0 24 24">
                <rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.8 7 8.2 5.6L20.2 7"/>
              </svg>
            </span>

            <h2 class="done__title">Verifică-ți e-mailul</h2>
            <p class="done__text">
              Dacă adresa <strong id="uitata-email">scrisă de tine</strong> are cont la noi,
              ți-am trimis acolo o parolă temporară.
            </p>
            <p class="done__hint">
              E valabilă <?= MINUTE_PAROLA_TEMPORARA ?> de minute și merge o singură dată.
              Nu găsești mesajul? Uită-te și în „Spam" sau „Promoții".
            </p>

            <p class="done__dev" id="uitata-dev" hidden>
              <span>Mod dezvoltare — parola temporară:</span>
              <strong id="uitata-parola">------</strong>
            </p>

            <div class="done__actions">
              <a class="btn btn--primary" href="/login.php">Intră în cont</a>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
