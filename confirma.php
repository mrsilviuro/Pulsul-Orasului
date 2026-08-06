<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — confirmarea adresei de e-mail.
 *
 * Se ajunge aici din linkul trimis prin e-mail. Cât timp lucrezi în XAMPP,
 * fără server de mail, linkul e scris în emailuri-trimise.log și afișat
 * direct în pagină după înregistrare.
 */

require_once __DIR__ . '/inc/bootstrap.php';

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
?>
<!DOCTYPE html>
<html lang="ro" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($titlu) ?> — PulsulOrasului.Ro</title>
<meta name="robots" content="noindex">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0d1015" media="(prefers-color-scheme: dark)">

<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=9">

<script>
(function () {
  try {
    var saved = localStorage.getItem('po-theme');
    var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);
  } catch (e) {}
})();
</script>
</head>

<body>
<a class="skip-link" href="#main">Sari la conținut</a>

<header class="site-header">
  <div class="nav">
    <a class="logo" href="index.html" aria-label="PulsulOrasului.Ro — Acasă">
      <span class="logo__mark" aria-hidden="true">
        <svg viewBox="0 0 32 32" fill="none">
          <path d="M2 16h6.2l2.6-7.4a1 1 0 0 1 1.9.05l4.1 14.2a1 1 0 0 0 1.9.04L21.4 16H30"
                stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
      <span class="logo__text">
        Pulsul<span class="logo__accent">Orasului</span><span class="logo__tld">.Ro</span>
      </span>
    </a>

    <nav class="nav__menu" id="nav-menu" aria-label="Meniu principal">
      <ul>
        <li><a class="nav__link" href="index.html">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.5V20h13V9.5"/><path d="M10 20v-5.5h4V20"/></svg>
          <span>Acasă</span></a></li>
        <li><a class="nav__link" href="despre.html">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5.5"/><path d="M12 7.6v.1"/></svg>
          <span>Despre</span></a></li>
        <li><a class="nav__link" href="login.php#inregistrare">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9.5" cy="8.5" r="3.5"/><path d="M3 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M18.5 8v6"/><path d="M21.5 11h-6"/></svg>
          <span>Alătură-te și tu</span></a></li>
        <li><a class="nav__link" href="contact.html">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.8 7 8.2 5.6L20.2 7"/></svg>
          <span>Contact</span></a></li>
      </ul>
    </nav>

    <div class="nav__actions">
      <button class="theme-toggle" id="theme-toggle" type="button" aria-label="Schimbă tema">
        <svg class="ico ico--sun" viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="12" r="4.2"/>
          <path d="M12 2.5v2.2M12 19.3v2.2M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6"/>
        </svg>
        <svg class="ico ico--moon" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M20.5 14.3A8.6 8.6 0 0 1 9.7 3.5a8.6 8.6 0 1 0 10.8 10.8Z"/>
        </svg>
      </button>
      <button class="nav__burger" id="nav-burger" type="button"
              aria-label="Deschide meniul" aria-expanded="false" aria-controls="nav-menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

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
              <a class="btn btn--ghost" href="index.html">Mergi la prima pagină</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<footer class="site-footer">
  <div class="wrap">
    <div class="footer__bottom" style="border-top:0;padding-top:0">
      <p>© <span id="year">2026</span> PulsulOrasului.Ro — Toate drepturile rezervate.</p>
      <ul class="footer__legal">
        <li><a href="#">Termeni</a></li>
        <li><a href="#">Confidențialitate</a></li>
        <li><a href="#">Cookies</a></li>
      </ul>
    </div>
  </div>
</footer>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="assets/js/main.js?v=9"></script>
</body>
</html>
