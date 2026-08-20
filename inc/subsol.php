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
        <div class="socials">
          <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8.5V7a1.5 1.5 0 0 1 1.5-1.5H17V3h-2.2A3.8 3.8 0 0 0 11 6.8v1.7H9V11h2v10h3V11h2.2l.4-2.5H14Z" fill="currentColor" stroke="none"/></svg></a>
          <a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r="1.1" fill="currentColor" stroke="none"/></svg></a>
          <a href="#" aria-label="YouTube"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="m10.5 9.5 5 2.5-5 2.5z"/></svg></a>
        </div>
      </div>

      <nav class="footer__col" aria-label="Secțiuni">
        <h3>Secțiuni</h3>
        <ul>
          <li><a href="#">Sport</a></li>
          <li><a href="#">Cultură</a></li>
          <li><a href="#">Comunitate</a></li>
          <li><a href="#">Gastro</a></li>
        </ul>
      </nav>

      <nav class="footer__col" aria-label="Site">
        <h3>Site</h3>
        <ul>
          <li><a href="index.php">Acasă</a></li>
          <li><a href="despre.php">Despre</a></li>
          <li><a href="contact.php">Contact</a></li>
          <?php if ($logat): ?>
          <li><a href="profil.php">Profilul tău</a></li>
          <?php else: ?>
          <li><a href="login.php#inregistrare">Alătură-te și tu</a></li>
          <?php endif; ?>
        </ul>
      </nav>

      <div class="footer__col footer__news">
        <h3>Newsletter</h3>
        <p>Un e-mail pe săptămână, cu ce merită văzut în oraș.</p>
        <form class="news-form" action="#" method="post">
          <label class="sr-only" for="news-email">Adresa ta de e-mail</label>
          <input id="news-email" type="email" name="email" placeholder="adresa@email.ro" required>
          <button class="btn btn--primary btn--sm" type="submit">Mă abonez</button>
        </form>
      </div>

    </div>

    <div class="footer__bottom">
      <p>© <span id="year"><?= date('Y') ?></span> PulsulOrasului.Ro — Toate drepturile rezervate.</p>
      <ul class="footer__legal">
        <li><a href="#">Termeni</a></li>
        <li><a href="#">Confidențialitate</a></li>
        <li><a href="#">Cookies</a></li>
      </ul>
    </div>
  </div>
</footer>

<div class="toast" id="toast" role="status" aria-live="polite"
     data-mesaj="<?= h($mesajTrecator) ?>"></div>

<script src="assets/js/main.js?v=73"></script>
</body>
</html>
