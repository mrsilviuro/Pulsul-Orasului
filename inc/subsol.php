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
        <a class="logo logo--footer" href="/index.php">
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
          <li><a href="/index.php">Prima pagină</a></li>
          <li><a href="/despre.php">Despre noi</a></li>
          <li><a href="/contact.php">Contactează-ne</a></li>
          <?php if ($logat): ?>
          <li><a href="/profil.php">Profilul tău</a></li>
          <?php else: ?>
          <li><a href="/login.php">Alătură-te și tu</a></li>
          <?php endif; ?>
        </ul>
      </nav>

      <!--
        Aici a stat o casetă de înscriere la newsletter. A plecat: vestea
        despre ce se întâmplă în oraș pleacă doar către cine are cont, și se
        cere din setări (bifa „Vreau să primesc e-mail cu evenimente noi"), nu
        dintr-o căsuță de subsol în care putea scrie oricine orice adresă.

        Caseta de pe pagina de așteptare (constructie.php) rămâne unde e: acolo
        e singurul lucru pe care îl poate face cineva cât ține lucrarea, iar
        adresele acelea sunt ale unor oameni care n-au cum să-și facă un cont.
      -->
    </div>

    <div class="footer__bottom">
      <p>Copyright © <span id="year"><?= date('Y') ?></span> PulsulOrasului.Ro — Toate drepturile rezervate.</p>
    </div>
  </div>
</footer>

<!--
  MESAJUL SCURT DE JOS („Link copiat!", „Nota ta a fost trimisă.").

  `role="status" aria-live="polite"` stă pe cutie, iar textul se schimbă în
  `<span>`-ul dinăuntru: cititoarele de ecran anunță ce s-a adăugat. Butonul e
  scris o dată, la început, deci nu se anunță de fiecare dată cu el.

  „×"-ul e acolo fiindcă mesajul stă acum CINCI secunde, nu două și jumătate.
  Mai mult timp de citit înseamnă și mai mult timp în care stă în drum — cine
  l-a citit trebuie să-l poată da la o parte.
-->
<div class="toast" id="toast" role="status" aria-live="polite"
     data-mesaj="<?= h($mesajTrecator) ?>">
  <span class="toast__text" data-toast-text></span>
  <button class="toast__x" type="button" data-toast-x aria-label="Închide mesajul">
    <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M18 6 6 18M6 6l12 12"/>
    </svg>
  </button>
</div>

<script src="/assets/js/main.js?v=85"></script>
</body>
</html>
