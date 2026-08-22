<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — abțibildele „FindMe". Pagina omului de casă.
 *
 * De aici se face un cod nou, se ia adresa care intră în codul QR de pe
 * abțibild, și se vede în ce stare e fiecare: netipărit, în joc, sau găsit.
 *
 * PRIMA PAGINĂ DE ADMINISTRARE DE PE SITE. Restul lucrurilor de casă se fac din
 * phpMyAdmin — aprobarea unei dorințe, steagul de staff, ridicarea unei
 * interdicții. Asta n-avea cum: un cod de cinci semne ales de mână s-ar fi lovit
 * într-o zi de altul, iar cheia unică ar fi respins abțibildul DUPĂ ce era deja
 * tipărit și lipit pe stâlp.
 *
 * ȘI FĂRĂ JS. Facerea unui cod e un formular obișnuit, cu Post/Redirect/Get:
 * pagina se face în stradă, de pe telefon, adesea pe o rețea proastă.
 */

require_once __DIR__ . '/inc/coduri-qr.php';
// Pentru paza de staff și rândul de legături al zonei de administrare.
require_once __DIR__ . '/inc/admin.php';

// Paza, ca la orice pagină de administrare — vezi cerePazaDeStaff().
$membru = cerePazaDeStaff('/coduri.php');

$membruId = (int) $membru['id'];

/* ========================== Un cod nou ============================== */

/**
 * Post/Redirect/Get: după ce s-a scris codul, pagina se cere din nou printr-un
 * GET. Fără el, un „reîncarcă" apăsat din obișnuință ar fi făcut încă un cod.
 *
 * Codul proaspăt se trece prin adresă (`?nou=…`) ca să poată fi scos în față —
 * e singurul lucru pentru care omul a intrat pe pagină. Nu e o taină: pagina se
 * deschide numai de staff, iar codul se vede oricum în listă, dedesubt.
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!tokenCsrfValid(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
        header('Location: /coduri.php?e=sesiune');
        exit;
    }

    $codNou = faCodQrNou($membruId);

    header('Location: /coduri.php' . ($codNou !== '' ? '?nou=' . urlencode($codNou) : '?e=nereusit'));
    exit;
}

$codNou  = curataCodQr($_GET['nou'] ?? null);
$eroarea = (string) ($_GET['e'] ?? '');
$coduri  = toateCodurileQr();

/**
 * Adresa care intră în codul QR. Întreagă, cu domeniu — abțibildul se scanează
 * de pe stradă, unde nu există „pagina de dinainte".
 */
$adresaCodului = static fn(string $cod): string => urlIntreg('/findme.php?qr=' . urlencode($cod));

$titlu   = 'Abțibilde FindMe — PulsulOrasului.Ro';
$pagina  = 'admin';
$noindex = true;

require __DIR__ . '/inc/antet.php';
?>
<main id="main">
  <div class="wrap">
    <?= randeazaMeniulAdmin('coduri') ?>

    <header class="page-head">
      <h1>Abțibilde FindMe</h1>
      <p class="page-head__sub">
        Fă un cod, tipărește abțibildul, lipește-l undeva prin oraș — și abia
        pe urmă publică evenimentul cu codul ăsta în el. Vânătoarea începe în
        clipa publicării.
      </p>
    </header>

    <?php if ($eroarea === 'sesiune'): ?>
    <p class="coduri__rau" role="alert">Sesiunea a expirat. Încearcă din nou.</p>
    <?php elseif ($eroarea === 'nereusit'): ?>
    <p class="coduri__rau" role="alert">N-a ieșit un cod nou. Mai încearcă o dată.</p>
    <?php endif; ?>

    <?php if ($codNou !== ''): ?>
    <!-- Codul proaspăt, scos în față: e singurul lucru pentru care omul a
         intrat pe pagină. Adresa e scrisă întreagă, ca să poată fi copiată
         într-o unealtă de făcut coduri QR. -->
    <section class="cod-nou">
      <p class="cod-nou__eticheta">Codul tău nou</p>
      <p class="cod-nou__cod"><?= h($codNou) ?></p>
      <p class="cod-nou__adresa">
        Pune în codul QR adresa asta:
        <code><?= h($adresaCodului($codNou)) ?></code>
      </p>
      <p class="cod-nou__vorba">
        Abțibildul poate sta pe stâlp de pe acum. Cine îl scanează înainte de
        publicare află doar că vânătoarea n-a început încă.
      </p>
    </section>
    <?php endif; ?>

    <form class="cod-form" method="post" action="/coduri.php">
      <input type="hidden" name="csrf" value="<?= h(tokenCsrf()) ?>">
      <button class="btn btn--primary" type="submit">Fă un cod nou</button>
    </form>

    <!-- ============================ LISTA ============================= -->
    <!--
      `data-coduri` și `data-deodata` sunt ce caută main.js: arată primele zece
      rânduri, ascunde restul și aprinde „Vezi mai mult". Numărul vine din
      CODURI_QR_DEODATA, ca să fie scris într-un singur loc.

      TOATE cele cincizeci intră în pagină, ascunse — nu se aduc pe rând de la
      server. Sunt cincizeci de rânduri de text, cât o pagină de carte: o
      cerere în plus pentru ele ar fi mai scumpă decât ele însele. Fără JS se
      văd toate, ceea ce e chiar purtarea de dinainte.
    -->
    <section class="coduri" aria-labelledby="coduri-title"
             data-coduri data-deodata="<?= CODURI_QR_DEODATA ?>"
             data-csrf="<?= h(tokenCsrf()) ?>">
      <h2 id="coduri-title">Toate abțibildele</h2>

      <?php if ($coduri === []): ?>
      <p class="coduri__gol">Niciun cod încă. Apasă butonul de mai sus.</p>
      <?php else: ?>
      <!-- Tabelul se derulează în cutia lui pe ecran îngust, ca să nu împingă
           pagina în lateral — vezi `.coduri__scroll` din style.css. -->
      <div class="coduri__scroll">
        <table class="tabel-coduri">
          <thead>
            <tr>
              <th scope="col">Cod</th>
              <th scope="col">Stare</th>
              <th scope="col">Eveniment</th>
              <th scope="col">Adresa pentru QR</th>
              <th scope="col"><span class="sr-only">Șterge</span></th>
            </tr>
          </thead>
          <tbody data-lista-coduri>
            <?php foreach ($coduri as $c):
              $stare = stareaCoduluiQr($c);

              // Trei stări, trei vorbe. Într-un tablou, ca să se vadă dintr-o
              // privire ce poate scrie în coloană.
              $vorba = [
                  'nefolosit' => 'Netipărit / liber',
                  'in_joc'    => 'În joc',
                  'gasit'     => 'Găsit',
              ][$stare];
            ?>
            <tr class="cod-rand cod-rand--<?= h($stare) ?>" data-cod="<?= h((string) $c['cod']) ?>">
              <td><code class="cod-rand__cod"><?= h((string) $c['cod']) ?></code></td>

              <td>
                <span class="cod-stare cod-stare--<?= h($stare) ?>"><?= h($vorba) ?></span>

                <?php if ($stare === 'gasit'):
                  $castigator = numeAfisat((string) $c['gasit_nume'], (string) $c['gasit_prenume']);
                ?>
                <span class="cod-rand__mic">
                  de <?= h($castigator !== '' ? $castigator : 'cineva') ?>
                </span>
                <?php endif; ?>
              </td>

              <td>
                <?php if ($c['ev_slug'] !== null): ?>
                <a href="<?= h(urlEveniment((string) $c['ev_slug'])) ?>"><?= h((string) $c['ev_titlu']) ?></a>
                <?php else: ?>
                <span class="cod-rand__mic">—</span>
                <?php endif; ?>
              </td>

              <td><code class="cod-rand__adresa"><?= h($adresaCodului((string) $c['cod'])) ?></code></td>

              <!--
                „×"-ul. Numai la codurile care n-au fost găsite — unul găsit e
                istoria cuiva, iar de el atârnă cifra de pe profilul
                câștigătorului (poateFiStersCodul).

                Pentru celelalte nu se desenează un buton stins, ci nu se
                desenează nimic: un buton care nu face nimic e o întrebare fără
                răspuns. `title` pe rând spune de ce, pentru cine se oprește cu
                mouse-ul acolo.
              -->
              <td class="cod-rand__unelte">
                <?php if (poateFiStersCodul($c)): ?>
                <button class="cod-sterge" type="button" data-sterge-cod
                        title="Șterge codul <?= h((string) $c['cod']) ?>"
                        aria-label="Șterge codul <?= h((string) $c['cod']) ?>">
                  <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m6.5 6.5 11 11M17.5 6.5l-11 11"/>
                  </svg>
                </button>
                <?php else: ?>
                <span class="cod-rand__mic" title="Un abțibild găsit rămâne în listă: de el atârnă cifra de pe profilul câștigătorului.">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Se aprinde din JS, cu numărul celor rămase ascunse. Fără JS stă
           ascuns: toate rândurile sunt deja în pagină. -->
      <div class="load-more" data-mai-multe-coduri hidden>
        <button class="btn btn--ghost" type="button" data-mai-multe-coduri-buton>Vezi mai mult</button>
      </div>

      <p class="coduri__rau" data-coduri-rau role="alert" hidden></p>
      <?php endif; ?>
    </section>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
