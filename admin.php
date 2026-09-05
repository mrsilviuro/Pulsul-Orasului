<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — zona de administrare, pagina de intrare.
 *
 * Un cartonaș pentru fiecare unealtă a casei. Fiecare arată câte lucruri
 * așteaptă acolo, ca omul să vadă dintr-o privire unde are treabă — altfel ar fi
 * trebuit să deschidă toate paginile ca să afle că mai toate sunt goale.
 *
 * Lista secțiunilor NU se scrie aici: vine din sectiuniAdmin() (inc/admin.php),
 * de unde o ia și rândul de legături de sus. O secțiune nouă e un rând acolo,
 * și apare singură în amândouă locurile.
 *
 * SUB CARTONAȘE STĂ UN SINGUR LUCRU: comutatorul de șantier. A stat acolo și
 * starea poștei, cât era din două rânduri; de când are și coada, și tabelul
 * mesajelor rămase pe drumuri, și-a luat pagina ei (admin-posta.php). Regula
 * de care ține locul: aici se pune ce se APASĂ, nu ce se citește — un panou al
 * cărui rost e să se vadă dintr-o privire nu suportă bucăți care se citesc.
 */

require_once __DIR__ . '/inc/admin.php';

$membru = cerePazaDeStaff('/admin.php');

/**
 * COMUTATORUL DE ȘANTIER, dus cu un `<form method="post">` adevărat spre pagina
 * asta — nu prin api/admin.php, ca celelalte fapte.
 *
 * DE CE ALTFEL DECÂT RESTUL. Faptele din admin sunt lucruri făcute pe rânduri
 * dintr-o listă: șterge comentariul ăsta, suspendă contul ăstuia. Ele trec prin
 * api/admin.php ca paza să fie scrisă o dată. Aici nu e un rând, e chiar
 * întrerupătorul de la ușa casei — iar un întrerupător care are nevoie de
 * JavaScript ca să MAI DESCHIDĂ site-ul e cel mai prost fel de întrerupător cu
 * putință: dacă tocmai JS-ul e ce s-a stricat, rămâi cu site-ul închis.
 *
 * Paza nu se scrie de două ori: cerePazaDeStaff() de mai sus e chiar ea, prima
 * linie a paginii, dinaintea oricărei fapte.
 */
$vorbaSantier = '';
$santierMers  = true;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['santier'])) {
    if (!tokenCsrfValid((string) ($_POST['csrf'] ?? ''))) {
        $vorbaSantier = 'Sesiunea a expirat. Reîncarcă pagina și încearcă din nou.';
        $santierMers  = false;
    } else {
        $vreauInchis = $_POST['santier'] === 'inchide';

        if (!puneLacatul($vreauInchis)) {
            $vorbaSantier = 'N-am putut scrie în private/. Uită-te la drepturile dosarului.';
            $santierMers  = false;
        } else {
            $vorbaSantier = $vreauInchis
                ? 'Am închis site-ul. De acum, oricine intră vede afișul de șantier.'
                : 'Am deschis site-ul. Se poate intra din nou de oriunde.';
        }
    }

    /**
     * Se răspunde cu o REDIRECȚIONARE, nu cu pagina de-a dreptul: altfel un
     * „reîncarcă" ar fi trimis din nou aceeași apăsare, iar omul ar fi comutat
     * lacătul fără să vrea. Vorba se duce mai departe prin sesiune.
     */
    $_SESSION['vorba_santier'] = ['text' => $vorbaSantier, 'mers' => $santierMers];
    header('Location: /admin.php#santier');
    exit;
}

if (!empty($_SESSION['vorba_santier'])) {
    $vorbaSantier = (string) $_SESSION['vorba_santier']['text'];
    $santierMers  = (bool)   $_SESSION['vorba_santier']['mers'];
    unset($_SESSION['vorba_santier']);
}

$eInchis   = siteInConstructie();
$dinSetari = lacatulEDinSetari();
$cifre     = cifreleAdmin();

$titlu   = 'Admin — PulsulOrasului.Ro';
$pagina  = 'admin';
$noindex = true;

require __DIR__ . '/inc/antet.php';
?>
<main id="main">
  <div class="wrap">

    <header class="page-head">
      <h1>Zona de administrare</h1>
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

    <!-- ======================== ȘANTIERUL ==============================
      Întrerupătorul de la ușa casei. Pus sub cartonașe fiindcă nu e o
      secțiune ca ele — alea sunt liste de lucru, asta e o singură apăsare
      care schimbă ce vede tot orașul.
    ============================================================== -->
    <section class="santier<?= $eInchis ? ' santier--inchis' : '' ?>" id="santier"
             aria-labelledby="santier-titlu">
      <div class="santier__spus">
        <h2 class="santier__titlu" id="santier-titlu">
          <?= $eInchis ? 'Site-ul e închis pentru lucrări' : 'Site-ul e deschis' ?>
        </h2>
        <p class="santier__vorba">
          <?php if ($eInchis): ?>
          Oricine intră vede afișul de șantier. Trec doar oamenii de casă și
          dispozitivele pe care au intrat ei cândva.
          <?php else: ?>
          Se poate intra de oriunde. Închis, rămân deschise doar intrarea în
          cont și documentele.
          <?php endif; ?>
        </p>
      </div>

      <?php if ($dinSetari): ?>
      <!--
        Lacătul din inc/config.php e mai tare decât comutatorul, dinadins: e
        drumul care merge oricând, chiar dacă discul e doar de citit. Dar
        atunci butonul n-ar face nimic, iar un buton care nu face nimic e mai
        rău decât unul care lipsește — se spune pe față de ce.
      -->
      <p class="santier__blocat">
        Lacătul e pus din <code>inc/config.php</code>
        (<code>'in_constructie' =&gt; true</code>) și de acolo se și scoate.
        Cât stă acolo, comutatorul ăsta n-are ce comuta.
      </p>
      <?php else: ?>
      <form class="santier__form" method="post" action="/admin.php#santier">
        <input type="hidden" name="csrf" value="<?= h(tokenCsrf()) ?>">
        <button class="btn <?= $eInchis ? 'btn--primary' : 'btn--rau' ?>"
                type="submit" name="santier"
                value="<?= $eInchis ? 'deschide' : 'inchide' ?>">
          <?= $eInchis ? 'Deschide site-ul' : 'Închide site-ul' ?>
        </button>
      </form>
      <?php endif; ?>

      <?php if ($vorbaSantier !== ''): ?>
      <p class="santier__raspuns<?= $santierMers ? '' : ' santier__raspuns--rau' ?>">
        <?= h($vorbaSantier) ?>
      </p>
      <?php endif; ?>

      <!--
        Permisul de dispozitiv. Se scrie singur, la orice cerere a unui om de
        casă (vezi tineMinteDispozitivul din inc/constructie.php), tocmai ca
        să fie deja acolo când se pune lacătul.
      -->
      <div class="santier__permis">
        <?php if (dispozitivCunoscut()): ?>
        <p>Dispozitivul ăsta e ținut minte, deci trece de lacăt și cu un cont
          care nu e de-al casei — bun pentru probe. Ține <?= ZILE_SANTIER ?> de
          zile de la ultima intrare a unui om al casei.</p>
        <?php else: ?>
        <p>Dispozitivul ăsta nu e încă ținut minte. Reîncarcă pagina și va fi:
          permisul se scrie singur la prima cerere a unui om al casei.</p>
        <?php endif; ?>
      </div>
    </section>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
