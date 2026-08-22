<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — administrare: oamenii.
 *
 * Căutare după nume, prenume, e-mail sau telefon — toate patru deodată, fiindcă
 * omul de casă are în mână UN lucru și nu vrea să aleagă întâi după ce caută.
 *
 * Ce se poate face în dreptul cuiva:
 *   – starea contului (neconfirmat / activ / suspendat)
 *   – limita de evenimente active
 *   – ștergerea pozei de profil, când cineva a încărcat ce n-ar fi trebuit
 *
 * LA SUSPENDARE ȘI LA ȘTERGEREA POZEI PLEACĂ UN E-MAIL, cu un motiv care poate
 * să lipsească — atunci mesajul spune limpede că nu s-a dat niciunul. Fără el,
 * omul ar fi intrat într-o zi pe profil și ar fi găsit inițiala în locul
 * chipului lui, fără nicio lămurire.
 *
 * ADRESA DE E-MAIL NU SE ARATĂ ÎN TABEL. Nu e nevoie de ea aici: căutarea o
 * primește oricum, iar dacă tot trebuie scris cuiva, se scrie de pe pagina lui.
 * Un tabel de cincizeci de rânduri cu adrese e, în plus, tocmai ce n-ar trebui
 * lăsat deschis pe un ecran. Rămâne telefonul, care se cere rar și e de folos
 * când chiar se cere.
 *
 * Ce NU se poate, și de ce:
 *   – „șters" nu e o stare care se pune de aici. Ștergerea unui cont înseamnă
 *     ANONIMIZARE (inc/stergere.php): se golește omul din rând, rămân
 *     evenimentele și participările lui. Un `UPDATE stare='sters'` ar fi lăsat
 *     numele, adresa și poza în bază sub o stare care spune că nu mai sunt.
 *   – pe un om de casă nu se umblă din tabelul ăsta. Nu e o pază — cine e staff
 *     poate oricum orice — ci o ferire de apăsarea greșită: doi oameni de casă
 *     care se suspendă unul pe altul dintr-o listă de două sute de rânduri ar
 *     rămâne amândoi pe dinafară.
 *   – steagul de staff se dă și se ia tot din phpMyAdmin, dinadins.
 *
 * Așezați DUPĂ ULTIMA LOGARE, cel mult ADMIN_USERI (50): un cont făcut acum doi
 * ani, dar folosit ieri, e mai interesant decât unul deschis alaltăieri și lăsat
 * baltă. Cine caută pe cineva anume are căutarea de deasupra.
 *
 * Formularul de căutare e unul ADEVĂRAT, cu GET: fără JS merge la fel, iar
 * căutarea rămâne în adresă, deci se poate da mai departe și se poate reîncărca.
 */

require_once __DIR__ . '/inc/admin.php';

cerePazaDeStaff('/admin-useri.php');

$cauta  = trim((string) ($_GET['q'] ?? ''));
$oameni = cautaMembri($cauta);

/** Cele trei stări care se pot pune de aici, cu vorba lor. */
$stari = [
    'neconfirmat' => 'Neconfirmat',
    'activ'       => 'Activ',
    'suspendat'   => 'Suspendat',
];

$titlu   = 'Useri — Admin';
$pagina  = 'admin';
$noindex = true;

require __DIR__ . '/inc/antet.php';
?>
<main id="main">
  <div class="wrap">
    <?= randeazaMeniulAdmin('useri') ?>

    <header class="page-head">
      <h1>Useri</h1>
      <p class="page-head__sub">
        Caută după nume, prenume, e-mail sau telefon — toate deodată. Numărul se
        găsește și scris cu „+40" sau cu spații. Lista arată ultimii
        <?= ADMIN_USERI ?> care au trecut pe site, cei mai proaspeți întâi.
      </p>
    </header>

    <form class="admin-cauta" method="get" action="/admin-useri.php" role="search">
      <label class="sr-only" for="cauta-om">Caută un om</label>
      <input type="search" id="cauta-om" name="q" value="<?= h($cauta) ?>"
             placeholder="Nume, e-mail sau telefon…" autocomplete="off">
      <button class="btn btn--primary" type="submit">Caută</button>
      <?php if ($cauta !== ''): ?>
      <a class="btn btn--text" href="/admin-useri.php">Arată-i pe toți</a>
      <?php endif; ?>
    </form>

    <section class="admin-sect" data-admin data-csrf="<?= h(tokenCsrf()) ?>">
      <h2>
        <?= $cauta === '' ? 'Toți' : 'Găsiți' ?>
        <span class="admin-sect__cate"><?= count($oameni) ?></span>
      </h2>

      <?php if ($oameni === []): ?>
      <p class="admin-gol">
        <?= $cauta === '' ? 'Niciun cont.' : 'Nimeni nu se potrivește cu „' . h($cauta) . '".' ?>
      </p>
      <?php else: ?>
      <?php if (count($oameni) >= ADMIN_USERI): ?>
      <p class="admin-sect__vorba">
        Se arată <?= ADMIN_USERI ?>, cei care au trecut cel mai de curând pe
        site. Caută ceva anume ca să ajungi la cine te interesează.
      </p>
      <?php endif; ?>

      <div class="admin-scroll">
        <table class="admin-tabel admin-tabel--useri">
          <thead>
            <tr>
              <th scope="col">Omul</th>
              <th scope="col">Telefon</th>
              <th scope="col">Înscris</th>
              <th scope="col">Ultima logare</th>
              <th scope="col">Stare</th>
              <th scope="col">Limită</th>
              <th scope="col"><span class="sr-only">Acțiuni</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($oameni as $o):
              $eStaffOmul = (int) $o['este_staff'] !== 0;
              $arePoza    = estePozaValida($o['poza'] ?? null);
            ?>
            <tr data-rand data-id="<?= (int) $o['id'] ?>">
              <td>
                <div class="admin-om">
                  <span class="admin-om__chip" aria-hidden="true">
                    <img src="<?= h(urlPoza($o['poza'] ?? null, true)) ?>" alt=""
                         width="32" height="32" loading="lazy">
                  </span>
                  <div>
                    <?= omulCuLegatura($o['nume'], $o['prenume'], $o['permalink'], $o['stare']) ?>
                    <?php if ($eStaffOmul): ?>
                    <span class="admin-eticheta admin-eticheta--casa">casă</span>
                    <?php endif; ?>
                    <?php if ($o['cerere_stergere'] !== null): ?>
                    <!-- Ștergerea are răgaz de 30 de zile, iar simpla intrare
                         în cont o anulează. Se scrie aici ca să nu pară, peste
                         o lună, că omul a dispărut din senin. -->
                    <span class="admin-eticheta admin-eticheta--rea">a cerut ștergerea</span>
                    <?php endif; ?>
                    <span class="admin-tabel__mic">
                      <?= (int) $o['cate_evenimente'] ?> evenimente
                    </span>
                  </div>
                </div>
              </td>

              <td>
                <!-- Doar telefonul, și numai dacă îl are — e opțional în
                     setări. Fără el, o liniuță: acolo, gol înseamnă „nu ni l-a
                     dat", și trebuie să se vadă. -->
                <?php if (($o['telefon'] ?? '') !== ''): ?>
                <a href="tel:<?= h((string) $o['telefon']) ?>"><?= h((string) $o['telefon']) ?></a>
                <?php else: ?>
                <span class="admin-tabel__gol">—</span>
                <?php endif; ?>
              </td>

              <td><?= h(clipaScurta($o['creat_la'])) ?></td>
              <td><?= h(clipaScurta($o['autentificat_la'])) ?></td>

              <td>
                <?php if ($eStaffOmul): ?>
                <!-- Fără listă de ales pentru un om de casă: nu se desenează un
                     câmp stins, nu se desenează nimic. Un câmp care nu face
                     nimic e o întrebare fără răspuns. -->
                <span class="admin-tabel__mic" title="Starea unui om de casă se schimbă din phpMyAdmin.">
                  <?= h($stari[$o['stare']] ?? (string) $o['stare']) ?>
                </span>
                <?php else: ?>
                <!--
                  `data-motiv` cere o vorbă NUMAI pentru starea care doare —
                  suspendarea. La „activ" sau „neconfirmat" nu pleacă niciun
                  e-mail, deci n-are cui să-i folosească un motiv, iar o
                  întrebare pusă degeaba e o întrebare pe care omul învață s-o
                  închidă fără să citească.
                -->
                <select class="admin-select" data-fapta="schimba-membru"
                        data-camp="stare" data-id="<?= (int) $o['id'] ?>"
                        data-motiv-pentru="suspendat"
                        data-motiv="De ce se suspendă contul? Motivul îi pleacă pe e-mail."
                        aria-label="Starea contului">
                  <?php foreach ($stari as $val => $vorba): ?>
                  <option value="<?= h($val) ?>" <?= $o['stare'] === $val ? 'selected' : '' ?>>
                    <?= h($vorba) ?>
                  </option>
                  <?php endforeach; ?>
                  <?php if (!isset($stari[$o['stare']])): ?>
                  <!-- Contul anonimizat („sters") nu se mai schimbă de aici;
                       se arată doar, ca lista să nu mintă. -->
                  <option value="<?= h((string) $o['stare']) ?>" selected disabled>
                    <?= h((string) $o['stare']) ?>
                  </option>
                  <?php endif; ?>
                </select>
                <?php endif; ?>
              </td>

              <td>
                <!--
                  GOL ÎNSEAMNĂ „REGULA OBIȘNUITĂ", nu zero.

                  În bază, coloana e NULL pentru aproape toată lumea, iar
                  limitaEvenimente() citește NULL ca EVENIMENTE_ACTIVE_IMPLICIT.
                  Un `(int)` peste NULL ar fi scris „0" în căsuță — adică „omul
                  ăsta nu mai publică nimic" — pentru toți cei care n-au fost
                  atinși niciodată. Iar la prima salvare, minciuna ar fi devenit
                  adevăr.

                  De aceea căsuța rămâne goală, cu numărul obișnuit scris ca
                  îndemn, și se poate goli la loc: golită, coloana se face
                  NULL. Zero rămâne o valoare adevărată și folositoare — „nu
                  mai publică o vreme" — doar că trebuie scrisă anume.

                  Se scrie la ieșirea din câmp sau la Enter, nu la fiecare
                  tastă: altfel „12" ar fi plecat întâi ca „1".
                -->
                <input class="admin-numar" type="number" min="0" max="255"
                       value="<?= $o['limita_evenimente_active'] === null
                                  ? '' : (int) $o['limita_evenimente_active'] ?>"
                       placeholder="<?= EVENIMENTE_ACTIVE_IMPLICIT ?>"
                       title="Gol = regula obișnuită (<?= EVENIMENTE_ACTIVE_IMPLICIT ?>). Zero = nu mai publică."
                       data-fapta="schimba-membru" data-camp="limita"
                       data-id="<?= (int) $o['id'] ?>"
                       aria-label="Limita de evenimente active">
              </td>

              <td class="admin-tabel__unelte">
                <?php if ($arePoza): ?>
                <button class="btn btn--rau btn--xs" type="button"
                        data-fapta="sterge-poza" data-id="<?= (int) $o['id'] ?>"
                        data-motiv="De ce se șterge poza? Motivul îi pleacă pe e-mail."
                        data-intreb="Ștergi poza de profil? Omul primește un e-mail și o poate încărca la loc.">
                  Șterge poza
                </button>
                <?php else: ?>
                <span class="admin-tabel__gol">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
