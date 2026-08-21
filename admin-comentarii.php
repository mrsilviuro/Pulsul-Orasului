<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — administrare: comentariile.
 *
 * Două tabele: ce s-a raportat (sus, fiindcă acolo e treaba) și ultimele
 * cincizeci scrise pe site (dedesubt, ca să se vadă ce se întâmplă).
 *
 * AICI SE VĂD, ÎN SFÂRȘIT, RAPOARTELE. Steagul de la capătul rândului de unelte
 * se putea apăsa de mult, dar nimeni n-avea unde să se uite la ce a ieșit din
 * el: singurul fel de a afla era un SELECT pe `comentarii_rapoarte`.
 *
 * „Șterge" e ACEEAȘI faptă ca pe pagina evenimentului — un comentariu principal
 * cu răspunsuri sub el se golește, nu se șterge. Vezi api/admin.php.
 */

require_once __DIR__ . '/inc/admin.php';
require_once __DIR__ . '/inc/comentarii.php';

cerePazaDeStaff('/admin-comentarii.php');

$raportate = comentariiRaportate();
$ultimele  = ultimeleComentarii();

$titlu   = 'Comentarii — Admin';
$pagina  = 'admin';
$noindex = true;

/**
 * Un rând de tabel, scris o dată fiindcă îl cer amândouă tabelele. Se
 * deosebesc printr-o singură coloană — câte raportări are — deci ar fi fost
 * două bucăți de HTML aproape la fel, care într-o zi n-ar mai fi fost.
 */
$randComentariu = static function (array $c, bool $cuRapoarte): string {
    /**
     * Aceeași ancoră spre care trimite și e-mailul de înștiințare: `#c<id>`,
     * scrisă de randeazaComentarii(). Aici e singurul loc din afara acelui
     * fișier care o folosește — dacă se schimbă vreodată acolo, se schimbă și
     * aici, iar testul care păzește ancora e în teste/test-comentarii.php.
     */
    $ancora = 'event.php?slug=' . urlencode((string) $c['ev_slug'])
            . '#c' . (int) $c['id'];

    $text = (int) $c['sters'] === 1
        ? '<span class="admin-tabel__gol">(golit)</span>'
        : bucataDeText($c['text'], 120);

    $felul = $c['parinte_id'] !== null
        ? '<span class="admin-eticheta">răspuns</span>' : '';

    $rapoarte = $cuRapoarte
        ? '<td><span class="admin-cifra-rea">' . (int) $c['cate_rapoarte'] . '</span></td>'
        : '';

    return '<tr data-rand>'
         . '<td>' . $text . ' ' . $felul . '</td>'
         . '<td>' . omulCuLegatura($c['nume'], $c['prenume'], $c['permalink'], $c['stare_cont']) . '</td>'
         . '<td><a href="' . h($ancora) . '">' . bucataDeText($c['ev_titlu'], 40) . '</a></td>'
         . $rapoarte
         . '<td>' . h(clipaScurta($c['creat_la'])) . '</td>'
         . '<td class="admin-tabel__unelte">'
         . '<button class="btn btn--rau btn--xs" type="button"'
         . ' data-fapta="sterge-comentariu" data-id="' . (int) $c['id'] . '"'
         . ' data-intreb="Ștergi comentariul ăsta?">Șterge</button>'
         . '</td></tr>';
};

require __DIR__ . '/inc/antet.php';
?>
<main id="main">
  <div class="wrap">
    <?= randeazaMeniulAdmin('comentarii') ?>

    <header class="page-head">
      <h1>Comentarii</h1>
      <p class="page-head__sub">
        Ce a fost raportat, și ce s-a scris de curând. Un comentariu cu
        răspunsuri sub el se golește în loc să dispară, ca discuția să-și
        păstreze începutul.
      </p>
    </header>

    <!-- ====================== RAPORTATE ========================== -->
    <section class="admin-sect" data-admin data-csrf="<?= h(tokenCsrf()) ?>">
      <h2>Raportate <span class="admin-sect__cate"><?= count($raportate) ?></span></h2>

      <?php if ($raportate === []): ?>
      <p class="admin-gol">Nimic raportat. Liniște.</p>
      <?php else: ?>
      <p class="admin-sect__vorba">
        Cel mai raportat, primul. Numărul ăsta nu se vede nicăieri altundeva pe
        site: pe pagina evenimentului omul află doar dacă el însuși a raportat.
      </p>
      <div class="admin-scroll">
        <table class="admin-tabel">
          <thead>
            <tr>
              <th scope="col">Ce scrie</th>
              <th scope="col">Cine</th>
              <th scope="col">Unde</th>
              <th scope="col">Raportări</th>
              <th scope="col">Scris</th>
              <th scope="col"><span class="sr-only">Acțiuni</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($raportate as $c) { echo $randComentariu($c, true); } ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>

    <!-- =================== ULTIMELE CINCIZECI ==================== -->
    <section class="admin-sect" data-admin data-csrf="<?= h(tokenCsrf()) ?>">
      <h2>Ultimele <?= ADMIN_COMENTARII ?>
          <span class="admin-sect__cate"><?= count($ultimele) ?></span></h2>

      <?php if ($ultimele === []): ?>
      <p class="admin-gol">Niciun comentariu pe site, încă.</p>
      <?php else: ?>
      <div class="admin-scroll">
        <table class="admin-tabel">
          <thead>
            <tr>
              <th scope="col">Ce scrie</th>
              <th scope="col">Cine</th>
              <th scope="col">Unde</th>
              <th scope="col">Scris</th>
              <th scope="col"><span class="sr-only">Acțiuni</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ultimele as $c) { echo $randComentariu($c, false); } ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
