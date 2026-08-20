<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — subsolul comun al tuturor paginilor.
 */

$logat = isset($logat) ? $logat : esteLogat();

/**
 * Mesajul lăsat în sesiune de o pagină care a făcut o redirecționare.
 *
 * Se citește o singură dată și se șterge imediat: dacă ar rămâne, ar reapărea
 * la fiecare reîncărcare a paginii. E felul în care „Bine ai revenit!" ajunge
 * pe ecran după intrarea cu Google, care trece prin trei adrese diferite.
 */
$mesajTrecator = '';

if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['mesaj_bun'])) {
    $mesajTrecator = (string) $_SESSION['mesaj_bun'];
    unset($_SESSION['mesaj_bun']);
}
?>
<!-- =============================== FOOTER =============================== -->
<footer class="site-footer">
  <div class="wrap">
    <div class="footer__top">

      <div class="footer__brand">
        <a class="logo logo--footer" href="index.php">
          <span class="logo__mark" aria-hidden="true">
            <svg viewBox="0 0 32 32" fill="none">
              <path d="M2 16h6.2l2.6-7.4a1 1 0 0 1 1.9.05l4.1 14.2a1 1 0 0 0 1.9.04L21.4 16H30"
                    stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <span class="logo__text">Pulsul<span class="logo__accent">Orasului</span><span class="logo__tld">.Ro</span></span>
        </a>
        <p class="footer__tagline">Redescoperă plăcerea de a ieși din casă. Locul unde comunitatea își dă întâlnire.</p>
      </div>

      <nav class="footer__col" aria-label="Secțiuni">
        <h3>Secțiuni</h3>
        <ul>
          <li><a href="#">Termeni și Condiții</a></li>
          <li><a href="#">Confidențialitate</a></li>
          <li><a href="#">Cookies</a></li>
        </ul>
      </nav>

      <nav class="footer__col" aria-label="Site">
        <h3>Site</h3>
        <ul>
          <li><a href="index.php">Prima pagină</a></li>
          <li><a href="despre.php">Despre noi</a></li>
          <li><a href="contact.php">Contactează-ne</a></li>
          <?php if ($logat): ?>
          <li><a href="profil.php">Profilul tău</a></li>
          <?php else: ?>
          <li><a href="login.php">Alătură-te și tu</a></li>
          <?php endif; ?>
        </ul>
      </nav>

      <div class="footer__col footer__news">
        <h3>Newsletter</h3>
        <p>Fii la curent cu ultimele noutăți. Introdu adresa ta de email în campul de mai jos:</p>
        <form class="news-form" action="#" method="post">
          <label class="sr-only" for="news-email">Adresa ta de e-mail</label>
          <input id="news-email" type="email" name="email" placeholder="adresa@email.ro" required>
          <button class="btn btn--primary btn--sm" type="submit">Mă abonez</button>
        </form>
      </div>

    </div>

    <div class="footer__bottom">
      <p>Copyright © <span id="year"><?= date('Y') ?></span> PulsulOrasului.Ro — Toate drepturile rezervate.</p>
    </div>
  </div>
</footer>

<div class="toast" id="toast" role="status" aria-live="polite"
     data-mesaj="<?= h($mesajTrecator) ?>"></div>

<script src="assets/js/main.js?v=73"></script>
</body>
</html>
