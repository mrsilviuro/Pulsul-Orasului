<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — administrare: mesajele de la formularul de contact.
 *
 * Până acum se citeau numai din phpMyAdmin. Erau singurul lucru de pe site pe
 * care cineva ți-l TRIMITE ție și pe care nu-l vedeai nicăieri.
 *
 * Mesajul se arată ÎNTREG, nu ciuntit: e o scrisoare, iar o scrisoare pe
 * jumătate nu se poate citi. De aceea aici nu e un tabel, ci o listă de
 * cartonașe — un tabel cu o coloană de trei rânduri s-ar fi lățit peste ecran.
 *
 * „Citit / necitit" e o ștampilă pe care omul de casă și-o pune singur, ca să
 * știe unde a rămas. Nu e un semn de „am răspuns": răspunsul pleacă din
 * căsuța lui de e-mail, nu de aici.
 */

require_once __DIR__ . '/inc/admin.php';

cerePazaDeStaff('/admin-contact.php');

$mesaje = mesajeDeContact();

$titlu   = 'Contact — Admin';
$pagina  = 'admin';
$noindex = true;

require __DIR__ . '/inc/antet.php';
?>
<main id="main">
  <div class="wrap">
    <?= randeazaMeniulAdmin('contact') ?>

    <header class="page-head">
      <h1>Mesaje de la contact</h1>
      <p class="page-head__sub">
        Cele mai noi întâi. Răspunsul pleacă din căsuța ta de e-mail;
        „citit" e doar semnul tău, ca să știi unde ai rămas. „×" șterge
        mesajul de tot — omul care l-a trimis nu află nimic.
      </p>
    </header>

    <section class="admin-sect" data-admin data-csrf="<?= h(tokenCsrf()) ?>">
      <?php if ($mesaje === []): ?>
      <p class="admin-gol">Niciun mesaj încă.</p>
      <?php else: ?>

      <ul class="admin-mesaje">
        <?php foreach ($mesaje as $m):
          $necitit = $m['citit_la'] === null;
          $nume    = numeAfisat((string) $m['nume'], (string) $m['prenume']);
        ?>
        <li class="admin-mesaj<?= $necitit ? ' admin-mesaj--nou' : '' ?>"
            data-rand data-id="<?= (int) $m['id'] ?>">

          <div class="admin-mesaj__cap">
            <div>
              <strong class="admin-mesaj__om">
                <?php if ($m['permalink'] !== null && $m['stare_cont'] !== 'sters'): ?>
                <a href="<?= h(urlProfil((string) $m['permalink'])) ?>"><?= h($nume) ?></a>
                <?php else: ?>
                <?= h($nume) ?>
                <?php endif; ?>
              </strong>

              <!--
                Adresa și numărul sunt legături vii: de aici pleacă răspunsul,
                iar o adresă pe care trebuie s-o selectezi cu mouse-ul e o
                adresă pe care o scrii greșit într-o zi.
              -->
              <span class="admin-mesaj__contact">
                <a href="mailto:<?= h((string) $m['email']) ?>"><?= h((string) $m['email']) ?></a>
                <?php if (($m['telefon'] ?? '') !== ''): ?>
                · <a href="tel:<?= h((string) $m['telefon']) ?>"><?= h((string) $m['telefon']) ?></a>
                <?php endif; ?>
              </span>
            </div>

            <div class="admin-mesaj__dreapta">
              <span class="admin-mesaj__cand"><?= h(clipaScurta($m['creat_la'])) ?></span>

              <!--
                Un singur buton care comută. `data-citit` spune ce se cere
                ACUM; JS-ul îl întoarce pe loc, fără să reîncarce pagina —
                altfel omul ar fi pierdut locul din listă la fiecare bifă.
              -->
              <button class="admin-mesaj__bifa" type="button"
                      data-fapta="marcheaza-mesaj"
                      data-id="<?= (int) $m['id'] ?>"
                      data-citit="<?= $necitit ? '1' : '0' ?>">
                <?= $necitit ? 'Însemnează citit' : 'Citit' ?>
              </button>

              <!--
                Ștergerea e adevărată, fără piatră de mormânt: un mesaj de
                contact e o scrisoare primită, nu o urmă din viața site-ului.
                Când s-a răspuns la el — sau când e limpede că nu cere niciun
                răspuns — n-are de ce să rămână.

                Omul care l-a trimis nu află nimic: a scris cuiva, n-a pus ceva
                pe site.
              -->
              <button class="admin-mesaj__x" type="button"
                      data-fapta="sterge-mesaj"
                      data-id="<?= (int) $m['id'] ?>"
                      data-intreb="Ștergi mesajul de la <?= h($nume) ?>?"
                      title="Șterge mesajul" aria-label="Șterge mesajul">
                <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="m6.5 6.5 11 11M17.5 6.5l-11 11"/>
                </svg>
              </button>
            </div>
          </div>

          <p class="admin-mesaj__text"><?= nl2br(h((string) $m['mesaj'])) ?></p>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
