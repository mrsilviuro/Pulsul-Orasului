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
 * Ștergerea e ADEVĂRATĂ, spre deosebire de restul site-ului. Rândurile din
 * `dorinte` nu se șterg de obicei niciodată, nici după ce ies de pe tablă —
 * vrem să putem spune, mai târziu, câte dorințe și-au pus oamenii de-a lungul
 * timpului. Butonul de aici e pentru ce n-are ce căuta în numărătoarea aceea:
 * o înjurătură, un test, o adresă strecurată în text.
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

$vorbeStare = [
    'in_asteptare' => 'așteaptă',
    'aprobat'      => 'pe tablă',
    'respins'      => 'respinsă',
];

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
        Ce așteaptă o hotărâre stă în capul listei. O dorință aprobată apare pe
        prima pagină și stă acolo <?= ZILE_PE_TABLA ?> zile; omul nu află pe
        e-mail nici că i-a fost aprobată, nici că i-a fost respinsă — vede
        singur.
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
              $asteapta = $d['stare_moderare'] === 'in_asteptare';
              $iese     = $panaCand($d);
            ?>
            <tr data-rand class="admin-rand--<?= h((string) $d['stare_moderare']) ?>">
              <td><?= h((string) $d['dorinta']) ?></td>
              <td><?= omulCuLegatura($d['nume'], $d['prenume'],
                                     $d['permalink'], $d['stare_cont']) ?></td>
              <td><?= h((string) $d['oras']) ?></td>

              <td>
                <span class="admin-stare admin-stare--<?= h((string) $d['stare_moderare']) ?>">
                  <?= h($vorbeStare[$d['stare_moderare']] ?? (string) $d['stare_moderare']) ?>
                </span>
                <?php if ($iese !== ''): ?>
                <span class="admin-tabel__mic">până <?= h($iese) ?></span>
                <?php endif; ?>
              </td>

              <td><?= h(clipaScurta($d['creat_la'])) ?></td>

              <td class="admin-tabel__unelte">
                <?php if ($asteapta): ?>
                <button class="btn btn--primary btn--xs" type="button"
                        data-fapta="modereaza-dorinta" data-hotarare="aprobat"
                        data-id="<?= (int) $d['id'] ?>">Aprobă</button>
                <button class="btn btn--ghost btn--xs" type="button"
                        data-fapta="modereaza-dorinta" data-hotarare="respins"
                        data-id="<?= (int) $d['id'] ?>">Respinge</button>
                <?php endif; ?>

                <button class="btn btn--rau btn--xs" type="button"
                        data-fapta="sterge-dorinta" data-id="<?= (int) $d['id'] ?>"
                        data-intreb="Ștergi dorința asta de tot? Nu se mai numără nicăieri.">
                  Șterge
                </button>
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
