<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — zona de administrare, pagina de intrare.
 *
 * Șase cartonașe, câte unul pentru fiecare unealtă a casei. Fiecare arată câte
 * lucruri așteaptă acolo, ca omul să vadă dintr-o privire unde are treabă —
 * altfel ar fi trebuit să deschidă șase pagini ca să afle că cinci sunt goale.
 *
 * Lista secțiunilor NU se scrie aici: vine din sectiuniAdmin() (inc/admin.php),
 * de unde o ia și rândul de legături de sus. O secțiune nouă e un rând acolo,
 * și apare singură în amândouă locurile.
 */

require_once __DIR__ . '/inc/admin.php';

$membru = cerePazaDeStaff('/admin.php');
$cifre  = cifreleAdmin();

$titlu   = 'Admin — PulsulOrasului.Ro';
$pagina  = 'admin';
$noindex = true;

require __DIR__ . '/inc/antet.php';
?>
<main id="main">
  <div class="wrap">

    <header class="page-head">
      <h1>Zona de administrare</h1>
      <p class="page-head__sub">
        Bună, <?= h($membru['prenume']) ?>. Uneltele casei, toate într-un loc.
        Cifrele arată câte lucruri așteaptă ceva de la tine.
      </p>
    </header>

    <section class="admin-grila" aria-label="Secțiuni">
      <?php foreach (sectiuniAdmin() as $s):
        $cate = $s['cifra'] !== null ? (int) ($cifre[$s['cifra']] ?? 0) : 0;
      ?>
      <!--
        Cartonașul se aprinde numai când e ceva de făcut. Unul care ar sta
        aprins mereu n-ar mai însemna nimic — exact ca un bec de avarie care
        arde de trei luni.
      -->
      <a class="admin-cart<?= $cate > 0 ? ' admin-cart--treaba' : '' ?>"
         href="<?= h($s['href']) ?>">
        <span class="admin-cart__ico" aria-hidden="true">
          <svg class="ico" viewBox="0 0 24 24"><?= $s['ico'] ?></svg>
        </span>

        <span class="admin-cart__spus">
          <span class="admin-cart__titlu"><?= h($s['titlu']) ?></span>
          <span class="admin-cart__vorba"><?= h($s['vorba']) ?></span>
        </span>

        <span class="admin-cart__cifra">
          <?php if ($cate > 0): ?>
          <strong><?= $cate ?></strong>
          <span><?= h($s['unitate']) ?></span>
          <?php else: ?>
          <span class="admin-cart__linistit">nimic de făcut</span>
          <?php endif; ?>
        </span>
      </a>
      <?php endforeach; ?>
    </section>

    <!--
      Ce NU se face de aici, ca să nu caute nimeni degeaba. Lista asta e scurtă
      dinadins: când unul dintre rânduri capătă o pagină, rândul lui pleacă de
      aici. Cât timp mai e scris, e adevărat.
    -->
    <section class="admin-nota">
      <h2>Ce se face tot din phpMyAdmin</h2>
      <ul>
        <li>Steagul de om al casei (<code>membri.este_staff</code>) — nu se dă
            din nicio pagină, dinadins.</li>
        <li>Ridicarea unei interdicții de reînscriere
            (<code>excluderi_evenimente.interzis</code>).</li>
        <li>Ștergerea unei note date pe nedrept, sau a unui „Nu s-a prezentat"
            pus din greșeală.</li>
        <li>Ștergerea unui abțibild deja găsit — de el atârnă cifra de pe
            profilul câștigătorului.</li>
      </ul>
    </section>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
