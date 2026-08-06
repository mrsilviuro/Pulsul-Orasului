<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — confirmarea adresei de e-mail.
 *
 * Se ajunge aici din linkul trimis prin e-mail. Cât timp lucrezi în XAMPP,
 * fără server de mail, linkul e scris în emailuri-trimise.log și afișat
 * direct în pagină după înregistrare.
 */

require_once __DIR__ . '/inc/auth.php';

$token = isset($_GET['token']) && is_string($_GET['token']) ? trim($_GET['token']) : '';

$titlu  = 'Link invalid';
$mesaj  = 'Linkul de confirmare nu este valid. Verifică dacă l-ai copiat întreg.';
$reusit = false;

// Token-ul din link e în clar; în baza de date stă doar hash-ul lui.
if (preg_match('/^[a-f0-9]{64}$/', $token)) {

    $q = db()->prepare(
        'SELECT id, stare, token_expira
           FROM membri
          WHERE token_confirmare = ?
          LIMIT 1'
    );
    $q->execute([hash('sha256', $token)]);
    $membru = $q->fetch();

    if (!$membru) {
        $titlu = 'Link invalid';
        $mesaj = 'Linkul nu mai este valabil. Poate a fost deja folosit.';

    } elseif ($membru['stare'] === 'activ') {
        $titlu  = 'Contul e deja confirmat';
        $mesaj  = 'Poți intra în cont oricând.';
        $reusit = true;

    } elseif (new DateTimeImmutable((string) $membru['token_expira']) < new DateTimeImmutable()) {
        $titlu = 'Link expirat';
        $mesaj = 'Linkul de confirmare a expirat. Înregistrează-te din nou sau cere altul.';

    } else {
        $u = db()->prepare(
            'UPDATE membri
                SET stare = \'activ\',
                    confirmat_la = NOW(),
                    token_confirmare = NULL,
                    token_expira = NULL
              WHERE id = ?'
        );
        $u->execute([$membru['id']]);

        $titlu  = 'Contul tău e gata';
        $mesaj  = 'Adresa de e-mail a fost confirmată. Acum te poți autentifica.';
        $reusit = true;
    }
}

$titlu = $titlu . ' — PulsulOrasului.Ro';
$descriere = 'Confirmarea adresei de e-mail pe PulsulOrasului.Ro.';
$noindex   = true;

require __DIR__ . '/inc/antet.php';
?>


<main id="main">
  <div class="wrap">
    <div class="auth">
      <div class="auth__card">
        <div class="auth-panel">
          <div class="done <?= $reusit ? 'done--ok' : 'done--fail' ?>">
            <span class="done__ico" aria-hidden="true">
              <?php if ($reusit): ?>
                <svg class="ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/></svg>
              <?php else: ?>
                <svg class="ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5"/><path d="M12 16.4v.1"/></svg>
              <?php endif; ?>
            </span>

            <h1 class="done__title"><?= h($titlu) ?></h1>
            <p class="done__text"><?= h($mesaj) ?></p>

            <div class="done__actions">
              <?php if ($reusit): ?>
                <a class="btn btn--primary" href="login.php">Intră în cont</a>
              <?php else: ?>
                <a class="btn btn--primary" href="login.php#inregistrare">Înregistrează-te</a>
              <?php endif; ?>
              <a class="btn btn--ghost" href="index.php">Mergi la prima pagină</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
