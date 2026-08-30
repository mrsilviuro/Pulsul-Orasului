<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — administrare: tabla cu dorințe.
 *
 * AICI SE APROBĂ DORINȚELE. Până acum se făcea de mână, din phpMyAdmin — era
 * scris chiar așa în CLAUDE.md, la „ce e neterminat". O dorință scrisă de om nu
 * se vedea nicăieri până nu intra cineva în bază și îi schimba starea, ceea ce
 * însemna că tabla se umplea numai cât își aducea cineva aminte.
 *
 * `publicat_la` NU se pune la aprobare, dinadins: îl scrie
 * stampileazaCeleAprobate() la prima încărcare a primei pagini, tot cu ceasul
 * PHP. Așa, o dorință aprobată de aici și una aprobată din phpMyAdmin se poartă
 * la fel, iar cele șapte zile de pe tablă se numără dintr-un singur loc.
 *
 * SE ARATĂ TOATE, oricâte ar fi — singura listă de administrare fără tăietură.
 * Rândurile din `dorinte` nu se șterg niciodată, tocmai ca mai târziu să se
 * poată spune câte dorințe și-au pus oamenii; tabelul ăsta e singurul loc unde
 * se vede tot ce s-a scris vreodată, iar o limită ar fi tăiat chiar istoria
 * pentru care se păstrează rândurile.
 *
 * Ștergerea e ADEVĂRATĂ, și tocmai de aceea e singura de pe site: butonul de
 * aici e pentru ce n-are ce căuta în numărătoarea de mai sus — o înjurătură, un
 * test, o adresă strecurată în text.
 */

require_once __DIR__ . '/inc/admin.php';
require_once __DIR__ . '/inc/dorinte.php';

cerePazaDeStaff('/admin-dorinte.php');

$dorinte = toateDorintele();

/** În câte zile iese de pe tablă — sau '' dacă nu e pe ea. */
$panaCand = static function (array $d): string {
    if ($d['stare_moderare'] !== 'aprobat' || $d['publicat_la'] === null) {
        return '';
    }

    // Ia rândul întreg, nu data: aceeași funcție de care atârnă și vorba de
    // sub tablă, ca cele două să nu socotească vreodată altfel.
    $iese = dorintaIeseDePeTabla($d);

    return $iese === null ? '' : dataLunga(date('Y-m-d', $iese), false);
};

/** Cum se citește starea în coloana „Stare" — o vorbă despre unde e dorința. */
$vorbeStare = [
    'in_asteptare' => 'așteaptă',
    'aprobat'      => 'pe tablă',
    'respins'      => 'respinsă',
];

/**
 * Ce se poate alege din listă — FAPTELE care se pot face de aici.
 *
 * „Așteptare" e și starea în care intră orice dorință nouă, și drumul înapoi
 * pentru una hotărâtă din greșeală.
 */
$hotarari = [
    'in_asteptare' => 'Așteptare',
    'aprobat'      => 'Aprobă',
    'respins'      => 'Respinge',
];

/**
 * ACELEAȘI trei, dar scrise ca STĂRI — pentru rândul în care dorința se află
 * deja.
 *
 * O listă strânsă arată un singur rând: cel ales. Cu vorbele de sus, o dorință
 * aprobată scria „Aprobă" pe ea — adică o poruncă, un lucru rămas de făcut,
 * tocmai despre ceva ce se făcuse. Deschisă, lista se citește acum firesc: „e
 * Aprobat; pot Respinge".
 *
 * „Așteptare" e la fel în amândouă, fiindcă e un nume, nu o faptă — de aceea
 * lipsește de aici și se ia din tabloul de sus.
 */
$hotarariAcum = array_merge($hotarari, [
    'aprobat' => 'Aprobat',
    'respins' => 'Respins',
]);

$titlu   = 'Dorințe — Admin';
$pagina  = 'admin';
$noindex = true;

require __DIR__ . '/inc/antet.php';
?>
<main id="main">
  <div class="wrap">
    <?= randeazaMeniulAdmin('dorinte') ?>

    <header class="page-head">
      <h1>Tabla cu dorințe</h1>
      <p class="page-head__sub">
        Ce așteaptă o hotărâre stă în capul listei, apoi restul de la cele mai
        noi la cele mai vechi. O dorință aprobată apare pe prima pagină și stă
        acolo <?= ZILE_PE_TABLA ?> zile. Omul află pe e-mail în amândouă
        cazurile; la respingere poți scrie un motiv, sau îl poți lăsa gol.
      </p>
    </header>

    <section class="admin-sect" data-admin data-csrf="<?= h(tokenCsrf()) ?>">
      <?php if ($dorinte === []): ?>
      <p class="admin-gol">Nicio dorință, încă.</p>
      <?php else: ?>
      <div class="admin-scroll">
        <table class="admin-tabel">
          <thead>
            <tr>
              <th scope="col">Dorința</th>
              <th scope="col">Cine</th>
              <th scope="col">Oraș</th>
              <th scope="col">Stare</th>
              <th scope="col">Scrisă</th>
              <th scope="col"><span class="sr-only">Acțiuni</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($dorinte as $d):
              /**
               * ȘTEARSĂ DE AUTOR. Rândul rămâne — rândurile din `dorinte` nu
               * se șterg niciodată, ca mai târziu să se poată spune câte
               * dorințe și-au pus oamenii — dar nu mai e nimic de hotărât la
               * ea, iar o hotărâre luată acum n-ar face decât să pună înapoi
               * pe tablă ceva ce omul a retras. De aceea lista de mai jos îi
               * arată doar starea, stinsă, și ștergerea.
               */
              $retrasa = $d['sters_la'] !== null;
              $iese    = $retrasa ? '' : $panaCand($d);
            ?>
            <tr data-rand class="admin-rand--<?= h((string) $d['stare_moderare']) ?>">
              <td><?= h((string) $d['dorinta']) ?></td>
              <td><?= omulCuLegatura($d['nume'], $d['prenume'],
                                     $d['permalink'], $d['stare_cont']) ?></td>
              <td><?= h((string) $d['oras']) ?></td>

              <td>
                <?php if ($retrasa): ?>
                <span class="admin-stare admin-stare--respins">retrasă</span>
                <span class="admin-tabel__mic">de autor, <?= h(clipaScurta($d['sters_la'])) ?></span>
                <?php else: ?>
                <span class="admin-stare admin-stare--<?= h((string) $d['stare_moderare']) ?>">
                  <?= h($vorbeStare[$d['stare_moderare']] ?? (string) $d['stare_moderare']) ?>
                </span>
                <?php if ($iese !== ''): ?>
                <span class="admin-tabel__mic">până <?= h($iese) ?></span>
                <?php endif; ?>
                <?php endif; ?>
              </td>

              <td><?= h(clipaScurta($d['creat_la'])) ?></td>

              <td class="admin-tabel__unelte">
                <!--
                  O SINGURĂ LISTĂ, ca la starea contului din admin-useri.php.

                  Erau trei butoane, dintre care două se vedeau numai cât
                  dorința aștepta: o dată hotărâtă, nu mai era nicio cale
                  înapoi din interfață, iar un „Respinge" apăsat pe rândul
                  greșit se îndrepta doar din phpMyAdmin. Lista arată starea de
                  acum ȘI toate drumurile care pleacă din ea — inclusiv
                  întoarcerea în așteptare.

                  Motivul se cere NUMAI la respingere (`data-motiv-pentru`): la
                  aprobare n-are ce spune, iar o întrebare pusă degeaba e una
                  pe care omul învață s-o închidă fără să citească. Lăsat gol,
                  e-mailul spune limpede că nu s-a dat niciunul.

                  „Șterge" stă în aceeași listă, dar e ALTĂ faptă — de aceea își
                  poartă pe ea și `data-fapta`, și întrebarea de dinainte.
                  Ștergerea de aici e adevărată, un DELETE, singura de pe site.
                -->
                <select class="admin-select" data-fapta="modereaza-dorinta"
                        data-camp="hotarare" data-id="<?= (int) $d['id'] ?>"
                        data-motiv-pentru="respins"
                        data-motiv="De ce se respinge? Motivul îi pleacă pe e-mail."
                        aria-label="Ce se face cu dorința">
                  <?php if ($retrasa): ?>
                  <!--
                    RETRASĂ DE AUTOR: nu se mai moderează. A o pune înapoi pe
                    tablă ar însemna să-i scoatem omului din gură vorbele pe
                    care tocmai și le-a luat înapoi. Starea se arată doar, ca
                    lista să nu mintă — la fel ca un cont anonimizat în
                    admin-useri.php — și rămâne doar ștergerea.
                  -->
                  <option selected disabled>retrasă</option>
                  <?php else: ?>
                  <?php foreach ($hotarari as $val => $vorba):
                    /* Rândul în care dorința se află deja se scrie ca stare
                       („Aprobat"), celelalte ca fapte („Respinge"). */
                    $acumAici = $d['stare_moderare'] === $val;
                  ?>
                  <option value="<?= h($val) ?>" <?= $acumAici ? 'selected' : '' ?>>
                    <?= h($acumAici ? $hotarariAcum[$val] : $vorba) ?>
                  </option>
                  <?php endforeach; ?>
                  <?php endif; ?>

                  <option value="sters" data-fapta="sterge-dorinta"
                          data-intreb="Ștergi dorința asta de tot? Nu se mai numără nicăieri.">
                    Șterge
                  </option>
                </select>
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
