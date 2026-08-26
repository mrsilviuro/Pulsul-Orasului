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
               * ea, iar butoanele de moderare n-ar face decât să pună înapoi
               * pe tablă ceva ce omul a retras.
               */
              $retrasa  = $d['sters_la'] !== null;
              $asteapta = $d['stare_moderare'] === 'in_asteptare' && !$retrasa;
              $iese     = $retrasa ? '' : $panaCand($d);
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
                <?php if ($asteapta): ?>
                <button class="btn btn--primary btn--xs" type="button"
                        data-fapta="modereaza-dorinta" data-hotarare="aprobat"
                        data-id="<?= (int) $d['id'] ?>">Aprobă</button>
                <!--
                  Motivul se cere NUMAI la respingere: la aprobare n-are ce
                  spune, iar o întrebare pusă degeaba e o întrebare pe care
                  omul învață s-o închidă fără să citească. Lăsat gol, e-mailul
                  spune limpede că nu s-a dat niciunul.
                -->
                <button class="btn btn--ghost btn--xs" type="button"
                        data-fapta="modereaza-dorinta" data-hotarare="respins"
                        data-motiv="De ce se respinge? Motivul îi pleacă pe e-mail."
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
