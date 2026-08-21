<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — administrare: evenimentele.
 *
 * Două tabele, două treburi deosebite:
 *
 *   ÎN AȘTEPTARE — ce n-a trecut încă pe la nimeni. Butonul duce la pagina
 *                  anunțului, unde stă blocul de moderare cu „Aprobă" și
 *                  „Respinge". Hotărârea NU se ia de aici, dinadins: ca s-o
 *                  poți lua trebuie să citești ce a scris omul, iar dintr-un
 *                  rând de tabel n-ai ce citi.
 *   RESPINSE     — ce a fost refuzat. Aici se face curat: „Șterge" ia anunțul
 *                  cu tot ce atârnă de el.
 *
 * FĂRĂ COPERTE. E o listă de lucru, nu o vitrină.
 */

require_once __DIR__ . '/inc/admin.php';

cerePazaDeStaff('/admin-evenimente.php');

$inAsteptare = evenimenteDupaStare('in_asteptare');
$respinse    = evenimenteDupaStare('respins');

$titlu   = 'Evenimente — Admin';
$pagina  = 'admin';
$noindex = true;

require __DIR__ . '/inc/antet.php';
?>
<main id="main">
  <div class="wrap">
    <?= randeazaMeniulAdmin('evenimente') ?>

    <header class="page-head">
      <h1>Evenimente</h1>
      <p class="page-head__sub">
        Ce așteaptă o hotărâre și ce a fost respins. Aprobarea se face pe pagina
        anunțului, după ce l-ai citit.
      </p>
    </header>

    <!-- ==================== ÎN AȘTEPTARE ======================== -->
    <section class="admin-sect" data-admin data-csrf="<?= h(tokenCsrf()) ?>">
      <h2>În așteptare <span class="admin-sect__cate"><?= count($inAsteptare) ?></span></h2>

      <?php if ($inAsteptare === []): ?>
      <p class="admin-gol">Niciun anunț nu așteaptă. Curat.</p>
      <?php else: ?>
      <div class="admin-scroll">
        <table class="admin-tabel">
          <thead>
            <tr>
              <th scope="col">Anunțul</th>
              <th scope="col">Organizator</th>
              <th scope="col">Când are loc</th>
              <th scope="col">Trimis</th>
              <th scope="col"><span class="sr-only">Acțiuni</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($inAsteptare as $e): ?>
            <tr>
              <td>
                <strong><?= h((string) $e['titlu']) ?></strong>
                <span class="admin-tabel__mic">
                  <?= h((string) $e['categorie']) ?> · <?= h((string) $e['oras']) ?>
                  · <?= bucataDeText($e['locatie'], 40) ?>
                </span>
              </td>
              <td><?= omulCuLegatura($e['org_nume'], $e['org_prenume'],
                                     $e['org_permalink'], $e['org_stare']) ?></td>
              <td><?= h(dataScurta((string) $e['data_eveniment'])) ?>,
                  <?= h(oraScurta($e['ora_inceput'])) ?></td>
              <td><?= h(clipaScurta($e['creat_la'])) ?></td>
              <td class="admin-tabel__unelte">
                <a class="btn btn--ghost btn--xs"
                   href="event.php?slug=<?= h(urlencode((string) $e['slug'])) ?>">Vezi</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>

    <!-- ====================== RESPINSE =========================== -->
    <section class="admin-sect" data-admin data-csrf="<?= h(tokenCsrf()) ?>">
      <h2>Respinse <span class="admin-sect__cate"><?= count($respinse) ?></span></h2>
      <p class="admin-sect__vorba">
        Un anunț respins n-a fost niciodată public, deci ștergerea lui nu lasă pe
        nimeni în urmă. Pleacă tot: comentariile, înscrierile, notele,
        excluderile și coperta de pe disc. Nu se poate lua înapoi.
      </p>

      <?php if ($respinse === []): ?>
      <p class="admin-gol">Niciun anunț respins.</p>
      <?php else: ?>
      <div class="admin-scroll">
        <table class="admin-tabel">
          <thead>
            <tr>
              <th scope="col">Anunțul</th>
              <th scope="col">Organizator</th>
              <th scope="col">Ce atârnă de el</th>
              <th scope="col">Trimis</th>
              <th scope="col"><span class="sr-only">Acțiuni</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($respinse as $e): ?>
            <tr data-rand>
              <td>
                <a href="event.php?slug=<?= h(urlencode((string) $e['slug'])) ?>">
                  <strong><?= h((string) $e['titlu']) ?></strong></a>
                <span class="admin-tabel__mic">
                  <?= h((string) $e['categorie']) ?> · <?= h((string) $e['oras']) ?>
                </span>
              </td>
              <td><?= omulCuLegatura($e['org_nume'], $e['org_prenume'],
                                     $e['org_permalink'], $e['org_stare']) ?></td>
              <td>
                <!-- Cifrele astea sunt tot rostul coloanei: ele spun cât se
                     pierde la o apăsare. Un „0 · 0" liniștește; un „14 · 9"
                     te face să te uiți încă o dată. -->
                <span class="admin-tabel__mic">
                  <?= (int) $e['cate_comentarii'] ?> comentarii ·
                  <?= (int) $e['cati_inscrisi'] ?> înscrieri
                </span>
              </td>
              <td><?= h(clipaScurta($e['creat_la'])) ?></td>
              <td class="admin-tabel__unelte">
                <button class="btn btn--rau btn--xs" type="button"
                        data-fapta="sterge-eveniment"
                        data-id="<?= (int) $e['id'] ?>"
                        data-intreb="Ștergi definitiv „<?= h((string) $e['titlu']) ?>", cu tot ce atârnă de el?">
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
