<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — administrare: notele dintre participanți.
 *
 * DE CE EXISTĂ. Notele nu se pot retrage și nu se pot raporta. Cine a primit o
 * stea pe nedrept n-avea până acum cui să-i spună, iar media de pe profilul lui
 * se socotește din ele. Era singurul loc de pe site unde cineva putea face rău
 * altuia fără ca nimeni să poată îndrepta.
 *
 * DOUĂ TABELE, fiindcă sunt două întrebări deosebite:
 *   „cine împarte note, și cu ce mână"  → tabelul mic, de sus
 *   „ce s-a dat, cui, când și de unde"  → tabelul mare, de jos
 *
 * O notă singură de una nu spune nimic — poate chiar a fost o seară proastă.
 * Douăzeci de la același om spun altceva, și numai tabelul de sus le arată.
 *
 * SE VĂD ȘI CELE FĂRĂ TEXT. Pe profil apar doar părerile SCRISE; stelele
 * singure sunt anonime și nici nu se arată. Dar tocmai ele fac media, deci
 * tocmai ele trebuie să se poată vedea de aici. E SINGURUL loc de pe site unde
 * o stea are un nume lângă ea — de aceea pagina e numai pentru staff, ca toate
 * celelalte de aici.
 */

require_once __DIR__ . '/inc/admin.php';
require_once __DIR__ . '/inc/evaluari.php';

cerePazaDeStaff('/admin-evaluari.php');

$evaluari = toateEvaluarile();
$autori   = ceAuDatOamenii();

$titlu   = 'Evaluări — Admin';
$pagina  = 'admin';
$noindex = true;

/** Stelele, scrise ca cifră și desenate. */
$stelute = static function (int $stele): string {
    return '<span class="admin-stele' . ($stele <= 2 ? ' admin-stele--mici' : '') . '">'
         . '<span class="rating__stars rating__stars--sm" data-stars="' . $stele . '"></span>'
         . '<span class="admin-stele__cifra">' . $stele . '</span></span>';
};

require __DIR__ . '/inc/antet.php';
?>
<main id="main">
  <div class="wrap">
    <?= randeazaMeniulAdmin('evaluari') ?>

    <header class="page-head">
      <h1>Evaluări</h1>
      <p class="page-head__sub">
        Notele pe care și le dau oamenii după un eveniment. Aici se văd toate,
        și cele scrise, și stelele singure — pe profil se arată doar părerile
        scrise, dar în medie intră amândouă. Ștergerea e definitivă și nu
        înștiințează pe nimeni.
      </p>
    </header>

    <!-- ==================== CINE CÂTE A DAT ====================== -->
    <section class="admin-sect">
      <h2>Cine împarte note <span class="admin-sect__cate"><?= count($autori) ?></span></h2>

      <?php if ($autori === []): ?>
      <p class="admin-gol">Nimeni n-a notat pe nimeni, încă.</p>
      <?php else: ?>
      <p class="admin-sect__vorba">
        Cei care au dat mai multe, primii. O singură notă de unu poate fi o
        seară proastă; douăzeci sunt un obicei — și numai tabelul ăsta le pune
        una lângă alta. „Automate" sunt însemnările de „Nu s-a prezentat", care
        pun o stea fără să fie o părere.
      </p>
      <div class="admin-scroll">
        <table class="admin-tabel">
          <thead>
            <tr>
              <th scope="col">Cine</th>
              <th scope="col">Câte</th>
              <th scope="col">Media dată</th>
              <th scope="col">Cea mai mică</th>
              <th scope="col">Automate</th>
              <th scope="col">Ultima</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($autori as $a): ?>
            <tr>
              <td><?= omulCuLegatura($a['nume'], $a['prenume'], $a['permalink'], $a['stare_cont']) ?></td>
              <td><?= (int) $a['cate'] ?></td>
              <td><?= h(number_format((float) $a['media'], 1, ',', '')) ?></td>
              <td><?= $stelute((int) $a['cea_mai_mica']) ?></td>
              <td><?= (int) $a['cate_automate'] > 0
                    ? (int) $a['cate_automate']
                    : '<span class="admin-tabel__gol">—</span>' ?></td>
              <td><?= h(clipaScurta($a['ultima'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>

    <!-- ======================= TOATE NOTELE ====================== -->
    <section class="admin-sect" data-admin data-csrf="<?= h(tokenCsrf()) ?>">
      <h2>Ultimele <?= ADMIN_RANDURI ?>
          <span class="admin-sect__cate"><?= count($evaluari) ?></span></h2>

      <?php if ($evaluari === []): ?>
      <p class="admin-gol">Nicio notă pe site, încă.</p>
      <?php else: ?>
      <div class="admin-scroll">
        <table class="admin-tabel">
          <thead>
            <tr>
              <th scope="col">Cine a dat</th>
              <th scope="col">Cui</th>
              <th scope="col">Stele</th>
              <th scope="col">Ce a scris</th>
              <th scope="col">De la ce eveniment</th>
              <th scope="col">Când</th>
              <th scope="col"><span class="sr-only">Acțiuni</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($evaluari as $ev): ?>
            <tr data-rand>
              <td>
                <?= omulCuLegatura($ev['autor_nume'], $ev['autor_prenume'],
                                   $ev['autor_permalink'], $ev['autor_stare']) ?>
                <?php if ((int) $ev['automat'] === 1): ?>
                <!--
                  „Nu s-a prezentat" pune o stea, dar nu e o părere: e o
                  însemnare a organizatorului. Fără eticheta asta, un om de casă
                  ar fi văzut un „1" lângă un nume și ar fi crezut că cineva a
                  fost răutăcios.
                -->
                <span class="admin-eticheta">automat</span>
                <?php endif; ?>
              </td>
              <td><?= omulCuLegatura($ev['tinta_nume'], $ev['tinta_prenume'],
                                     $ev['tinta_permalink'], $ev['tinta_stare']) ?></td>
              <td><?= $stelute((int) $ev['stele']) ?></td>
              <td><?= bucataDeText($ev['text'], 110) ?></td>
              <td>
                <a href="<?= h(urlEveniment((string) $ev['ev_slug'])) ?>">
                  <?= bucataDeText($ev['ev_titlu'], 40) ?>
                </a>
              </td>
              <td><?= h(clipaScurta($ev['creat_la'])) ?></td>
              <td class="admin-tabel__unelte">
                <!--
                  Fără `data-motiv`: nu pleacă niciun e-mail, deci n-ar avea
                  cine să-l citească. O întrebare pusă degeaba e una pe care
                  omul învață s-o închidă fără să citească.
                -->
                <button class="btn btn--rau btn--xs" type="button"
                        data-fapta="sterge-evaluare" data-id="<?= (int) $ev['id'] ?>"
                        data-intreb="Ștergi nota asta? Media celui notat se schimbă pe loc, iar nimeni nu află nimic.">Șterge</button>
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
