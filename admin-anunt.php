<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — administrare: anunțul pe e-mail către toată lista.
 *
 * Scrii un titlu și un text, vezi cum arată și către câți pleacă, apoi apeși
 * încă o dată. Mesajul iese prin același șablon ca toate celelalte de pe site.
 *
 * TREI PAȘI, NU UNUL, ȘI ĂSTA E TOT ROSTUL PAGINII. Un formular cu un singur
 * buton „Trimite" ar fi trimis, într-o zi, o frază neterminată către toți
 * membrii — iar un e-mail plecat nu se ia înapoi. Deci:
 *
 *   1. SCRII      — formularul obișnuit.
 *   2. VEZI       — textul așa cum va ajunge, tăiat în paragrafe, cu numărul
 *                   celor care îl primesc scris lângă buton. De aici se poate și
 *                   întoarce la scris, cu tot ce era în câmpuri.
 *   3. TRIMIȚI    — a doua apăsare, pe altă pagină, cu alte cuvinte pe buton.
 *
 * ȘI O PROBĂ, tot din pasul 2: „Trimite-mi mie". Același mesaj, prin aceeași
 * funcție, doar la adresa celui conectat — singurul fel de a vedea cu adevărat
 * cum arată în Gmail înainte de a-l scoate în lume. După ea se rămâne pe pagina
 * de confirmare, ca trimiterea adevărată să fie tot la o apăsare distanță.
 *
 * FĂRĂ O PICĂTURĂ DE JAVASCRIPT. Ca și comutatorul de șantier din admin.php:
 * formulare adevărate, `method="post"`, către pagina însăși. Restul zonei de
 * administrare merge prin api/admin.php, cu `fapta` — dar acelea sunt lucruri
 * făcute pe rânduri dintr-o listă, iar asta e o scrisoare de scris.
 *
 * JETONUL DE O SINGURĂ FOLOSINȚĂ, care ține locul ștampilei din bază. Pasul 2
 * pune în sesiune un jeton și îl scrie în formularul de confirmare; pasul 3 îl
 * scoate din sesiune ÎNAINTE de a trimite ceva. Un „reîncarcă" peste trimitere,
 * o dublă apăsare, un buton „înapoi" urmat de încă un „trimite" — toate găsesc
 * sesiunea goală și nu mai pleacă nimic. Token-ul CSRF singur n-ar fi ajuns: el
 * ține cât ține sesiunea și se poate folosi de câte ori vrei.
 */

require_once __DIR__ . '/inc/admin.php';
require_once __DIR__ . '/inc/anunt.php';

$membru = cerePazaDeStaff('/admin-anunt.php');

/** Ce s-a scris în câmpuri — se întoarce în ele la orice pas. */
$titluAnunt = '';
$mesajAnunt = '';

$erori = [];

/** Ce se desenează acum: 'scriu' (formularul) sau 'confirm' (previzualizarea). */
$pas = 'scriu';

/** Jetonul filei ăsteia — se scrie în formularul de confirmare. */
$jetonAnunt = '';

/** Vorba de după o trimitere, adusă prin sesiune (vezi redirecționarea). */
$vorba = '';
$mers  = true;

/* ========================= CE A VENIT PRIN POST ======================= */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    if (!tokenCsrfValid((string) ($_POST['csrf'] ?? ''))) {
        $vorba = 'Sesiunea a expirat. Reîncarcă pagina și încearcă din nou.';
        $mers  = false;
    } else {
        /**
         * SE VERIFICĂ LA FIECARE PAS, nu doar la primul.
         *
         * Pasul 3 nu se încrede în ce a văzut pasul 2: textul călătorește prin
         * câmpuri ascunse, adică prin browser, adică prin mâna oricui. Ce se
         * trimite e ce iese din verificarea de acum, nu ce s-a arătat atunci.
         */
        $ce    = verificaAnunt($_POST);
        $erori = $ce['erori'];

        $titluAnunt = is_string($_POST['titlu'] ?? null) ? $_POST['titlu'] : '';
        $mesajAnunt = is_string($_POST['mesaj'] ?? null) ? $_POST['mesaj'] : '';

        $fapta = (string) ($_POST['pas'] ?? 'vezi');

        if ($erori !== []) {
            // Înapoi la scris, cu vorbele omului în câmpuri și cu ce lipsește
            // scris sub fiecare. Nimic nu pleacă.
            $pas = 'scriu';

        } elseif ($fapta === 'inapoi') {
            $pas = 'scriu';

        } elseif ($fapta === 'vezi') {
            /* Textul curat intră în câmpuri: omul vede în pasul 2 exact ce va
               pleca, nu ce a tastat. Dacă a lăsat cinci rânduri goale, aici sunt
               deja strânse la unul. */
            $titluAnunt = (string) $ce['curat']['titlu'];
            $mesajAnunt = (string) $ce['curat']['mesaj'];

            $jetonAnunt = jetonNouDeAnunt();
            $pas        = 'confirm';

        } elseif ($fapta === 'proba' || $fapta === 'trimit') {
            $titluAnunt = (string) $ce['curat']['titlu'];
            $mesajAnunt = (string) $ce['curat']['mesaj'];

            /**
             * JETONUL SE CERE LA AMÂNDOUĂ, dar se CONSUMĂ doar la trimiterea
             * adevărată: proba se poate trimite de câte ori vrea omul, până i se
             * pare bine cum arată. Ce n-are voie să se întâmple de două ori e
             * plecarea către listă.
             */
            $jetonAnunt = (string) ($_POST['jeton'] ?? '');

            if (!jetonDeAnuntValid($jetonAnunt)) {
                $vorba = 'Anunțul ăsta a plecat deja o dată, sau pagina a stat prea'
                       . ' mult deschisă. Textul e mai jos, întreg: apasă din nou'
                       . ' „Vezi cum arată" dacă chiar vrei să-l trimiți încă o dată.';
                $mers  = false;

            } elseif ($fapta === 'proba') {
                $plecat = trimiteAnuntul($titluAnunt, $mesajAnunt, [
                    'id'      => (int)    $membru['id'],
                    'prenume' => (string) $membru['prenume'],
                    'email'   => (string) $membru['email'],
                ]);

                $vorba = $plecat['trimise'] === 1
                    ? 'Ți-am trimis proba pe ' . $membru['email']
                      . '. Uită-te la ea, apoi trimite-l tuturor de aici.'
                    : 'N-am putut trimite proba. Uită-te în private/emailuri-trimise.log.';
                $mers  = $plecat['trimise'] === 1;

                // Se rămâne pe confirmare: proba e un ocol, nu o ieșire.
                $pas = 'confirm';

            } else {
                // Jetonul se stinge ÎNAINTE de trimitere — vezi lămurirea de la
                // consumaJetonDeAnunt() din inc/anunt.php.
                consumaJetonDeAnunt($jetonAnunt);

                /**
                 * Câteva sute de mail() nu încap în cele treizeci de secunde
                 * obișnuite, iar o cerere tăiată la jumătate ar fi lăsat
                 * jumătate de listă neînștiințată — fără ca nimeni să afle,
                 * fiindcă jetonul e deja stins. `ignore_user_abort` ține treaba
                 * mai departe și dacă omul închide fila între timp: mesajele
                 * încep să plece din prima clipă, deci oprirea n-ar mai lua
                 * nimic înapoi, doar ar ciunti.
                 *
                 * Amândouă pot fi închise din găzduire — de aici nu se poate
                 * face nimic în privința asta, dar tăietura din
                 * ANUNT_PE_TRIMITERE e chiar plasa de dedesubt.
                 */
                @set_time_limit(0);
                ignore_user_abort(true);

                $iesit = trimiteAnuntul($titluAnunt, $mesajAnunt);

                scrieInLogulAnunturilor(sprintf(
                    '%s | membru #%d | „%s" | %d trimise, %d picate',
                    acum(), (int) $membru['id'], $titluAnunt,
                    $iesit['trimise'], $iesit['picate']
                ));

                if ($iesit['catre'] === 0) {
                    $vorba = 'Nu are cine primi anunțul: nimeni n-are bifa pornită acum.';
                    $mers  = false;
                } elseif ($iesit['picate'] === 0) {
                    $vorba = 'Am trimis anunțul către ' . catiOameni($iesit['trimise']) . '.';
                } else {
                    $vorba = 'Am trimis anunțul către ' . catiOameni($iesit['trimise'])
                           . ', dar la ' . catiOameni($iesit['picate'])
                           . ' n-a ajuns. Uită-te în private/emailuri-trimise.log.';
                    $mers  = false;
                }

                // Trimis, câmpurile se golesc: pagina nu mai are ce ține minte.
                $titluAnunt = $mesajAnunt = '';
            }
        }
    }

    /**
     * REDIRECȚIONARE DUPĂ CE S-A TRIMIS CEVA, ca la comutatorul de șantier: un
     * „reîncarcă" peste un POST care a trimis e-mailuri ar fi cea mai scumpă
     * apăsare de pe site. Doar când chiar a plecat ceva — la o greșeală de scris
     * pagina rămâne unde e, cu textul în câmpuri.
     */
    if ($vorba !== '') {
        $_SESSION['vorba_anunt'] = [
            'text'  => $vorba,      'mers'  => $mers,
            'pas'   => $pas,        'jeton' => $jetonAnunt,
            'titlu' => $titluAnunt, 'mesaj' => $mesajAnunt,
        ];
        header('Location: /admin-anunt.php');
        exit;
    }
}

if (!empty($_SESSION['vorba_anunt'])) {
    $adus       = $_SESSION['vorba_anunt'];
    $vorba      = (string) $adus['text'];
    $mers       = (bool)   $adus['mers'];
    $pas        = (string) $adus['pas'];
    $jetonAnunt = (string) $adus['jeton'];
    $titluAnunt = (string) $adus['titlu'];
    $mesajAnunt = (string) $adus['mesaj'];

    unset($_SESSION['vorba_anunt']);
}

/**
 * Cifra de lângă buton, cerută abia acum: la pasul 2 e informația de care atârnă
 * apăsarea, iar la pasul 1 arată dinainte cât de departe ajunge ce se scrie.
 */
$catiPrimesc = catiPrimescAnuntul();

$titlu   = 'Anunț pe e-mail — Admin';
$pagina  = 'admin';
$noindex = true;

require __DIR__ . '/inc/antet.php';
?>
<main id="main">
  <div class="wrap">
    <?= randeazaMeniulAdmin('anunt') ?>

    <section class="admin-sect anunt">

      <?php if ($vorba !== ''): ?>
      <p class="anunt__raspuns<?= $mers ? '' : ' anunt__raspuns--rau' ?>">
        <?= h($vorba) ?>
      </p>
      <?php endif; ?>

      <?php if ($pas === 'confirm'): ?>
      <!-- ======================= PASUL 2: VEZI ==========================
        Nu e o poză a mesajului, e textul lui: aceleași paragrafe, în aceeași
        ordine, tăiate de aceeași funcție care le taie și la trimitere. O
        previzualizare care ar desena altfel decât trimite ar fi mai rea decât
        niciuna — ar da încredere degeaba.
      ============================================================== -->
      <h1 class="anunt__titlu">Așa pleacă anunțul</h1>

      <p class="anunt__vorba">
        Uită-te încă o dată. După ce apeși, mesajul nu se mai poate lua înapoi.
      </p>

      <div class="anunt__proba">
        <p class="anunt__eticheta">Subiectul mesajului</p>
        <p class="anunt__subiect"><?= h($titluAnunt) ?></p>

        <p class="anunt__eticheta">Cuprinsul</p>
        <p class="anunt__salut">Bună, <?= h((string) $membru['prenume']) ?>!</p>
        <?php foreach (paragrafeleAnuntului($mesajAnunt) as $paragraf): ?>
        <p class="anunt__paragraf"><?= nl2br(h($paragraf)) ?></p>
        <?php endforeach; ?>

        <p class="anunt__subsol">
          Jos, în subsol, mesajul poartă butonul de dezabonare — ca newsletterul
          zilnic. Vine nechemat, deci ieșirea trebuie să fie la vedere.
        </p>
      </div>

      <form class="anunt__form" method="post" action="/admin-anunt.php">
        <input type="hidden" name="csrf"  value="<?= h(tokenCsrf()) ?>">
        <input type="hidden" name="jeton" value="<?= h($jetonAnunt) ?>">
        <input type="hidden" name="titlu" value="<?= h($titluAnunt) ?>">

        <!--
          Mesajul călătorește printr-un câmp ascuns, cu rândurile noi scrise ca
          `&#10;`: htmlspecialchars() nu le atinge, iar un rând nou lăsat crud
          într-un atribut e la mila fiecărui browser. Scris așa, textul se
          întoarce la pasul 3 exact cum a plecat din pasul 2 — iar acolo trece
          din nou prin verificaAnunt(), fiindcă ce a călătorit prin browser nu se
          crede pe cuvânt.
        -->
        <input type="hidden" name="mesaj"
               value="<?= str_replace("\n", '&#10;', h($mesajAnunt)) ?>">

        <p class="anunt__cati">
          <?php if ($catiPrimesc === 0): ?>
          Nimeni n-are bifa pornită acum, deci n-are cine primi anunțul.
          <?php else: ?>
          Pleacă la <strong><?= h(catiOameni($catiPrimesc)) ?></strong>,
          câți au bifa de vești în setări.
          <?php if ($catiPrimesc > ANUNT_PE_TRIMITERE): ?>
          <!-- Tăietura se spune ÎNAINTE, nu se face în tăcere: la newsletter,
               cine n-a încăput azi primește mâine; aici nu revine nimeni. -->
          <strong>Dar dintr-o apăsare pleacă cel mult
            <?= ANUNT_PE_TRIMITERE ?></strong>, deci restul nu vor primi nimic.
          <?php endif; ?>
          <?php endif; ?>
        </p>

        <div class="anunt__butoane">
          <button class="btn btn--primary" type="submit" name="pas" value="trimit"
                  <?= $catiPrimesc === 0 ? 'disabled' : '' ?>>
            Trimite anunțul acum
          </button>

          <button class="btn btn--ghost" type="submit" name="pas" value="proba">
            Trimite-mi mie o probă
          </button>

          <button class="btn btn--ghost" type="submit" name="pas" value="inapoi">
            Mai lucrez la el
          </button>
        </div>
      </form>

      <?php else: ?>
      <!-- ====================== PASUL 1: SCRII ======================== -->
      <h1 class="anunt__titlu">Un anunț pe e-mail</h1>

      <p class="anunt__vorba">
        Pleacă la toți membrii cu bifa de vești din setări, staff inclus.
        <?php if ($catiPrimesc === 0): ?>
        Acum n-o are nimeni, deci n-are cine primi anunțul.
        <?php else: ?>
        Acum sunt <strong><?= h(catiOameni($catiPrimesc)) ?></strong>.
        <?php endif; ?>
        Nu se trimite din butonul de mai jos: întâi vezi cum arată.
      </p>

      <form class="form anunt__form" method="post" action="/admin-anunt.php">
        <input type="hidden" name="csrf" value="<?= h(tokenCsrf()) ?>">

        <div class="field">
          <label for="an-titlu">Titlul <span class="req" aria-hidden="true">*</span></label>
          <input type="text" id="an-titlu" name="titlu"
                 maxlength="<?= ANUNT_TITLU_MAX ?>" required
                 placeholder="Ne vedem sâmbătă în parc"
                 value="<?= h($titluAnunt) ?>"
                 <?= isset($erori['titlu']) ? 'aria-describedby="err-titlu"' : '' ?>>
          <p class="field__hint">Ajunge subiectul mesajului — singurul lucru care se
            vede înainte de deschidere.</p>
          <?php if (isset($erori['titlu'])): ?>
          <p class="field__error" id="err-titlu"><?= h($erori['titlu']) ?></p>
          <?php endif; ?>
        </div>

        <div class="field">
          <label for="an-mesaj">Mesajul <span class="req" aria-hidden="true">*</span></label>
          <textarea id="an-mesaj" name="mesaj" rows="12"
                    maxlength="<?= ANUNT_TEXT_MAX ?>" required
                    placeholder="Scrie aici ce ai de spus…"
                    <?= isset($erori['mesaj']) ? 'aria-describedby="err-mesaj"' : '' ?>><?= h($mesajAnunt) ?></textarea>
          <p class="field__hint">Lasă un rând gol între paragrafe. Nu scrie HTML —
            ce pui aici ajunge text, oricum l-ai scrie.</p>
          <?php if (isset($erori['mesaj'])): ?>
          <p class="field__error" id="err-mesaj"><?= h($erori['mesaj']) ?></p>
          <?php endif; ?>
        </div>

        <div class="form__foot">
          <p class="form__note">Salutul („Bună, Ana!") și subsolul le pune site-ul.</p>
          <button class="btn btn--primary" type="submit" name="pas" value="vezi">
            Vezi cum arată
          </button>
        </div>
      </form>
      <?php endif; ?>
    </section>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
