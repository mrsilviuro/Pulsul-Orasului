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
 *                  rând de tabel n-ai ce citi. Cele cărora li s-a cerut deja o
 *                  îndreptare poartă un SEMN — vezi coloana „Stare".
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
              <th scope="col">Stare</th>
              <th scope="col">Trimis</th>
              <th scope="col"><span class="sr-only">Acțiuni</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($inAsteptare as $e):
              $asteaptaCorectura = $e['corectura_ceruta_la'] !== null;
            ?>
            <tr<?= $asteaptaCorectura ? ' class="admin-rand--corectura"' : '' ?>>
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

              <td>
                <?php if ($asteaptaCorectura): ?>
                <!--
                  SEMNUL. I s-a cerut o îndreptare și omul încă n-a atins
                  anunțul — deci nu e nimic nou de citit aici.

                  Se stinge singur la PRIMA schimbare făcută de el
                  (actualizeazaEveniment), iar atunci rândul se întoarce în
                  listă ca unul care așteaptă să fie citit. Fără semnul ăsta,
                  un anunț citit-și-întors arăta exact ca unul necitit de
                  nimeni: ori îl citeai a doua oară degeaba, ori îl lăsai
                  deoparte („l-am mai văzut") deși omul îl schimbase de mult.
                -->
                <span class="admin-stare admin-stare--corectura"
                      title="Cerută pe <?= h(clipaScurta($e['corectura_ceruta_la'])) ?>. Semnul dispare când organizatorul umblă la anunț.">
                  i s-a cerut o îndreptare
                </span>
                <?php else: ?>
                <span class="admin-stare admin-stare--in_asteptare">necitit</span>
                <?php endif; ?>
              </td>

              <td><?= h(clipaScurta($e['creat_la'])) ?></td>
              <td class="admin-tabel__unelte">
                <!-- Tab nou: hotărârea se ia CITIND anunțul, iar lista rămâne
                     deschisă în spate, cu locul păstrat. Altfel, la fiecare
                     anunț cercetat urma un drum înapoi. -->
                <a class="btn btn--ghost btn--xs" target="_blank" rel="noopener"
                   href="<?= h(urlEveniment((string) $e['slug'])) ?>">Vezi</a>
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
                <a href="<?= h(urlEveniment((string) $e['slug'])) ?>">
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
