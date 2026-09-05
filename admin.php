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
 */

require_once __DIR__ . '/inc/admin.php';
require_once __DIR__ . '/inc/posta.php';   // pentru rândul de stare a poștei
require_once __DIR__ . '/inc/coada.php';   // …și pentru cifrele cozii

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

/**
 * ȘTERGEREA MESAJELOR RĂMASE PE DRUMURI, tot cu un formular adevărat spre
 * pagina asta, din aceleași motive ca lacătul de mai sus: e o unealtă a casei,
 * nu o faptă pe un rând dintr-o listă, iar panoul de poștă e tocmai locul în
 * care ajungi când ceva nu merge — inclusiv JavaScript-ul.
 *
 * NU E O PIERDERE. Un rând de aici e un plic pe care serverul l-a refuzat de
 * trei ori; ce scria în el s-a întâmplat oricum (contul e suspendat, anunțul e
 * anulat), doar vestea n-a ajuns. Ștergerea îl scoate din ochi, atât — de
 * aceea nici nu întreabă nimic înainte.
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

    $_SESSION['vorba_picate'] = $vorbaPicate;
    header('Location: /admin.php#posta');
    exit;
}

if (!empty($_SESSION['vorba_picate'])) {
    $vorbaPicate = (string) $_SESSION['vorba_picate'];
    unset($_SESSION['vorba_picate']);
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

    <!-- ========================= STAREA POȘTEI =========================
      DE CE E O LINIE PE ECRAN, ȘI NU DOAR UN RÂND ÎN LOG. Căderea de pe SMTP
      pe mail() e tăcută dinadins — un site care nu mai poate confirma un cont
      fiindcă lipsește un dosar ar fi mai rău. Dar tăcută ȘI nevăzută înseamnă
      că cineva pune datele în config, vede că mesajele pleacă, și crede ani de
      zile că merg pe drumul cel bun. Aici scrie negru pe alb pe care drum merg,
      fără SSH și fără să caute prin loguri.
    ============================================================== -->
    <?php
      $drumulPostei  = drumulPostei();
      $piedicaPostei = $drumulPostei === 'smtp' ? '' : deCeNuMergeSmtp();
    ?>
    <section class="posta<?= $drumulPostei === 'mail' ? ' posta--rau' : '' ?>"
             id="posta" aria-labelledby="posta-titlu">
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
        Coada: câte așteaptă acum și câte au rămas pe drumuri. A doua cifră ar
        trebui să fie mereu zero — când nu e, ceva e stricat, iar motivul stă
        în `coada_emailuri.eroare`. E singurul loc din care se vede asta fără
        SSH și fără phpMyAdmin.
      -->
      <p class="posta__vorba posta__vorba--mic">
        <?php $laRand = cateAsteaptaInCoada(); ?>
        <?php if ($laRand === 0): ?>
        Nu așteaptă niciun mesaj la rând.
        <?php else: ?>
        <strong><?= $laRand ?></strong>
        <?= $laRand === 1 ? 'mesaj așteaptă' : 'mesaje așteaptă' ?> la rând;
        cronul duce <?= (int) coadaPeRulare() ?> la fiecare pornire.
        <?php endif; ?>
        În ultimul ceas au plecat <strong><?= catePlecateInUltimulCeas() ?></strong>.
      </p>

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
        RÂNDURILE, LA VEDERE. Cifra singură nu spune nimic de făcut: ca s-o
        vezi de ce, trebuia deschis phpMyAdmin. Aici scrie cui n-a ajuns, ce
        i se scria și ce a răspuns serverul — de obicei „No Such User Here",
        adică o adresă care nu există.

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
                <form method="post" action="/admin.php#posta">
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

        <form method="post" action="/admin.php#posta">
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
