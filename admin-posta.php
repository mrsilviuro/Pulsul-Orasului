<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — administrare: poșta.
 *
 * PE CE DRUM PLEACĂ MESAJELE, ce e la rând și ce n-a plecat. Trei întrebări
 * care se pun toate în aceeași zi — aia în care cineva spune „n-am primit
 * mailul" — și niciuna în restul anului.
 *
 * DE CE E O PAGINĂ, ȘI NU UN COLȚ AL PANOULUI. A stat o vreme jos, pe
 * admin.php, sub comutatorul de șantier, fiindcă atât era: două rânduri despre
 * SMTP. De când are și coada, și tabelul mesajelor rămase pe drumuri, era cea
 * mai lungă bucată de pe o pagină al cărei rost e să se citească dintr-o
 * privire — și, pe deasupra, singura care nu-i cere omului nimic. Panoul
 * spune ce AȘTEAPTĂ o hotărâre; aici nu așteaptă nimic, aici doar se vede cum
 * merge o mașină.
 *
 * DE CE E O LINIE PE ECRAN, ȘI NU DOAR UN RÂND ÎN LOG. Căderea de pe SMTP pe
 * mail() e tăcută dinadins — un site care nu mai poate confirma un cont fiindcă
 * lipsește un dosar ar fi mai rău. Dar tăcută ȘI nevăzută înseamnă că cineva
 * pune datele în config, vede că mesajele pleacă, și crede ani de zile că merg
 * pe drumul cel bun. Aici scrie negru pe alb pe care drum merg, fără SSH și
 * fără să caute prin loguri.
 *
 * ȘTERGEREA MESAJELOR RĂMASE PE DRUMURI se face cu un `<form method="post">`
 * adevărat spre pagina asta, NU prin api/admin.php ca faptele de pe listele
 * celorlalte pagini. Aceeași socoteală ca la comutatorul de șantier: pagina de
 * poștă e tocmai locul în care ajungi când ceva nu merge, iar „ceva" poate fi
 * chiar JavaScript-ul.
 */

require_once __DIR__ . '/inc/admin.php';
require_once __DIR__ . '/inc/posta.php';   // drumul pe care pleacă mesajele
require_once __DIR__ . '/inc/coada.php';   // …și cifrele cozii

cerePazaDeStaff('/admin-posta.php');

/**
 * NU E O PIERDERE. Un rând șters de aici e un plic pe care serverul l-a refuzat
 * de toate cele trei ori; ce scria în el s-a întâmplat oricum (contul e
 * suspendat, anunțul e anulat), doar vestea n-a ajuns. Ștergerea îl scoate din
 * ochi, atât — de aceea nici nu întreabă nimic înainte.
 */
$vorbaPicate = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['picat'])) {
    if (!tokenCsrfValid((string) ($_POST['csrf'] ?? ''))) {
        $vorbaPicate = 'Sesiunea a expirat. Reîncarcă pagina și încearcă din nou.';
    } elseif ($_POST['picat'] === 'toate') {
        $cate = stergeToateCelePicate();
        $vorbaPicate = $cate === 1
            ? 'Am șters mesajul rămas pe drumuri.'
            : 'Am șters ' . $cate . ' mesaje rămase pe drumuri.';
    } else {
        $vorbaPicate = stergeDinCoada((int) $_POST['picat'])
            ? 'Am șters mesajul.'
            : 'Nu l-am găsit — poate l-a șters altcineva între timp.';
    }

    /**
     * Se răspunde cu o REDIRECȚIONARE, ca la comutatorul de șantier: altfel un
     * „reîncarcă" ar trimite din nou aceeași apăsare. Vorba trece prin sesiune.
     */
    $_SESSION['vorba_picate'] = $vorbaPicate;
    header('Location: /admin-posta.php');
    exit;
}

if (!empty($_SESSION['vorba_picate'])) {
    $vorbaPicate = (string) $_SESSION['vorba_picate'];
    unset($_SESSION['vorba_picate']);
}

$drumulPostei  = drumulPostei();
$piedicaPostei = $drumulPostei === 'smtp' ? '' : deCeNuMergeSmtp();

$titlu   = 'Poșta — Admin';
$pagina  = 'admin';
$noindex = true;

require __DIR__ . '/inc/antet.php';
?>
<main id="main">
<div class="wrap">
<?= randeazaMeniulAdmin('posta') ?>

<!--
  FĂRĂ ÎNVELIȘUL `.admin-sect` al celorlalte pagini, dinadins: aici nu e o
  listă de lucru, e o singură casetă. Iar `.admin-sect h2` e mai tare decât
  `.posta__titlu` și i-ar fi rescris mărimea — caseta ar fi arătat altfel aici
  decât arăta pe panou, fără ca cineva să fi cerut asta.
-->
<section class="posta<?= $drumulPostei === 'mail' ? ' posta--rau' : '' ?>"
         aria-labelledby="posta-titlu">
    <h2 class="posta__titlu" id="posta-titlu">
      <?php if ($drumulPostei === 'smtp'): ?>
      Mesajele pleacă prin serverul de poștă
      <?php elseif ($drumulPostei === 'fisier'): ?>
      Mesajele nu pleacă nicăieri
      <?php else: ?>
      Mesajele pleacă prin <code>mail()</code>
      <?php endif; ?>
    </h2>

    <?php if ($drumulPostei === 'smtp'): ?>
    <p class="posta__vorba">
      Conectat la <code><?= h((string) setarileSmtp()['gazda']) ?></code>, ca
      <code><?= h((string) setarileSmtp()['user']) ?></code>. Așa primesc
      semnătura DKIM a găzduirii — drumul pe care e cel mai puțin probabil să
      ajungă în „Spam".
    </p>

    <?php if (!adreseleSePotrivesc()): ?>
    <!-- Nu e o piedică — mesajele pleacă — dar e taman strâmbătatea din care
         se nasc mesajele puse deoparte de DMARC, și e greu de bănuit dacă
         nu-ți spune cineva. -->
    <p class="posta__vorba posta__vorba--atentie">
      Dar te conectezi ca <code><?= h((string) setarileSmtp()['user']) ?></code>
      și scrii de pe <code><?= h((string) ($config['email_expeditor'] ?? '')) ?></code>.
      Verificarea DMARC le vrea aceleași — pune în <code>smtp_user</code>
      chiar adresa din <code>email_expeditor</code>.
    </p>
    <?php endif; ?>

    <?php elseif ($drumulPostei === 'fisier'): ?>
    <p class="posta__vorba">
      E pornit modul de dezvoltare, iar mesajele se scriu în
      <code>private/emailuri-trimise.log</code>. Pe site-ul adevărat,
      <code>dezvoltare</code> trebuie să fie <code>false</code>.
    </p>

    <?php else: ?>
    <p class="posta__vorba"><?= h($piedicaPostei) ?></p>
    <p class="posta__vorba">
      Pleacă mai departe, dar nesemnate cu DKIM, deci o parte bună vor ajunge
      în „Spam".
    </p>
    <?php endif; ?>

    <!--
      Coada: câte așteaptă acum și câte au plecat în ultimul ceas. Cifra a
      doua e cea care spune dacă te apropii de plafonul găzduirii.
    -->
    <p class="posta__vorba posta__vorba--mic">
      <?php $laRand = cateAsteaptaInCoada(); ?>
      <?php if ($laRand === 0): ?>
      Nu așteaptă niciun mesaj la rând.
      <?php else: ?>
      <strong><?= $laRand ?></strong>
      <?= $laRand === 1 ? 'mesaj așteaptă' : 'mesaje așteaptă' ?> la rând.
      <?php endif; ?>
      În ultimul ceas au plecat <strong><?= catePlecateInUltimulCeas() ?></strong>.
    </p>

    <!--
      CÂT DUCE CRONUL, ȘI DE UNDE VINE CIFRA.
      Scria doar cifra, iar când ea nu se potrivea cu ce era scris în config
      n-avea cum să se lămurească nimeni fără SSH: sunt trei căi spre ea (cheia
      nouă, cea veche, valoarea din lipsă), și cel mai des vinovatul e un
      `inc/config.php` mai vechi decât `config.example.php`, rămas cu
      `email_pe_minut`. Un panou de diagnostic n-are voie să pună ghicitori.
    -->
    <?php $cheia = deUndeVineCoadaPeRulare(); $ramanLocuri = locuriRamasePeMinut(); ?>
    <p class="posta__vorba posta__vorba--mic">
      Cronul duce <strong><?= (int) coadaPeRulare() ?></strong> la fiecare
      pornire<?php if ($cheia !== ''): ?>, din <code><?= h($cheia) ?></code> în
      <code>inc/config.php</code><?php else: ?> — cifra din lipsă, fiindcă în
      <code>inc/config.php</code> nu e scrisă nici
      <code>emailuri_pe_rulare</code>, nici <code>email_pe_minut</code><?php endif; ?>.
      Găzduirea duce <strong><?= plafonPeMinut() ?></strong> pe minut.
    </p>

    <?php if ($ramanLocuri > 0): ?>
    <p class="posta__vorba posta__vorba--mic">
      Rămân <strong><?= $ramanLocuri ?></strong>
      <?= $ramanLocuri === 1 ? 'loc' : 'locuri' ?> într-un minut pentru ce
      pleacă pe loc: confirmarea de cont și parola temporară.
    </p>
    <?php else: ?>
    <!--
      AICI E SCĂPAREA CARE NU SE VEDE ALTFEL NICĂIERI. Cronul duce fix cât
      plafonul (sau peste), deci cine își face cont exact în minutul acela e al
      unsprezecelea, iar serverul îl refuză. Nu se pierde nimic — mesajul intră
      în coadă cu prioritate și pleacă în minutul următor —, dar omul stă cu
      ochii pe cutia poștală, iar întârzierea e chiar acolo unde doare.
    -->
    <p class="posta__vorba posta__vorba--atentie">
      Nu mai rămâne niciun loc într-un minut pentru mesajele care pleacă pe loc.
      Cine își face cont fix în minutul în care cronul își duce teancul primește
      confirmarea abia la rularea următoare. Scrie
      <code>'emailuri_pe_rulare' =&gt; <?= max(1, plafonPeMinut() - 2) ?></code>
      în <code>inc/config.php</code>, ca să rămână două locuri de rezervă.
    </p>
    <?php endif; ?>

    <?php if ($vorbaPicate !== ''): ?>
    <p class="posta__vorba posta__raspuns"><?= h($vorbaPicate) ?></p>
    <?php endif; ?>

    <?php if (($ramase = catePicateInCoada()) > 0): ?>
    <p class="posta__vorba posta__vorba--atentie">
      <strong><?= $ramase ?></strong>
      <?= $ramase === 1 ? 'mesaj n-a plecat' : 'mesaje n-au plecat' ?> nici după
      <?= COADA_INCERCARI_MAX ?> încercări. Motivul e scris în dreptul
      fiecăruia.
    </p>

    <!--
      RÂNDURILE, LA VEDERE. Cifra singură nu spune nimic de făcut: ca să vezi
      de ce, trebuia deschis phpMyAdmin. Aici scrie cui n-a ajuns, ce i se
      scria și ce a răspuns serverul — de cele mai multe ori că adresa aceea
      nu există.

      Vorba serverului e scrisă de ALTCINEVA, deci trece prin h() ca orice
      text străin. E singurul loc de pe site unde se vede vreodată.
    -->
    <div class="posta__picate">
      <table class="posta__tabel">
        <thead>
          <tr>
            <th scope="col">Către</th>
            <th scope="col">Mesajul</th>
            <th scope="col">Ce a spus serverul</th>
            <th scope="col"><span class="sr-only">Șterge</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (emailurilePicate() as $picat): ?>
          <tr>
            <td><code><?= h((string) $picat['catre']) ?></code></td>
            <td>
              <?= h((string) $picat['subiect']) ?>
              <span class="posta__cand"><?= h(dataScrisaMic((string) $picat['creat_la'])) ?></span>
            </td>
            <td class="posta__eroare"><?= h((string) ($picat['eroare'] ?? '')) ?></td>
            <td>
              <form method="post" action="/admin-posta.php">
                <input type="hidden" name="csrf" value="<?= h(tokenCsrf()) ?>">
                <button class="posta__sterge" type="submit"
                        name="picat" value="<?= (int) $picat['id'] ?>"
                        title="Șterge mesajul"
                        aria-label="Șterge mesajul către <?= h((string) $picat['catre']) ?>">×</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($ramase > COADA_PICATE_ARATATE): ?>
      <p class="posta__vorba posta__vorba--mic">
        Se văd cele mai noi <?= COADA_PICATE_ARATATE ?>, din <?= $ramase ?>.
      </p>
      <?php endif; ?>

      <form method="post" action="/admin-posta.php">
        <input type="hidden" name="csrf" value="<?= h(tokenCsrf()) ?>">
        <button class="btn btn--rau btn--xs" type="submit" name="picat" value="toate">
          Șterge-le pe toate
        </button>
      </form>
    </div>
  <?php endif; ?>
</section>
</div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
