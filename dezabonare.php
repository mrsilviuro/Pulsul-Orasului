<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — ieșirea de la newsletterul zilnic.
 *
 * Se ajunge aici din linkul de la coada mesajului zilnic, sau din butonul
 * „Dezabonează-te" pe care îl pune Gmail lângă numele expeditorului (antetul
 * `List-Unsubscribe`).
 *
 * FĂRĂ CONT. Cine s-a săturat de un mesaj n-are chef să-și amintească parola ca
 * să scape de el — iar dacă nu scapă în două secunde, apasă „Spam". Un singur
 * om care face asta strică livrarea pentru toți ceilalți. Semnătura din adresă
 * ține loc de dovadă: se socotește cu HMAC din id-ul omului și o cheie a
 * site-ului (vezi cheieDezabonare din inc/newsletter.php), deci n-are cum s-o
 * scrie altcineva.
 *
 * DAR NU SE STINGE NIMIC LA SIMPLA DESCHIDERE A ADRESEI.
 *
 * Aici e miezul paginii ăsteia. Multe programe de e-mail și multe filtre de
 * siguranță deschid singure toate linkurile dintr-un mesaj, ca să vadă unde
 * duc. Dacă simpla deschidere ar dezabona, o parte dintre oameni s-ar trezi
 * scoși de pe listă fără să fi apăsat nimic — și n-ar afla niciodată de ce nu
 * le mai vine nimic.
 *
 * Deci deschiderea doar ÎNTREABĂ; stinsul se face cu un buton, prin POST. Un
 * scaner apasă linkuri, nu butoane.
 *
 * De aceea nu se trimite nici „List-Unsubscribe-Post" în e-mail: acela le-ar
 * spune programelor să dezaboneze ele, cu o cerere trimisă în numele omului.
 * Aici apăsarea e a lui.
 */

require_once __DIR__ . '/inc/newsletter.php';

$membru = membrulDinLinkulDeDezabonare(
    is_string($_GET['m'] ?? null) ? $_GET['m'] : '',
    is_string($_GET['s'] ?? null) ? $_GET['s'] : ''
);

/**
 * Ce se vede pe ecran, într-un cuvânt:
 *
 *   'intreaba' — linkul e bun, bifa e pornită: se cere apăsarea
 *   'gata'     — tocmai s-a stins
 *   'era_stins'— linkul e bun, dar omul nu mai era abonat oricum
 *   'strambat' — semnătura nu se potrivește, sau contul nu mai e
 */
$ce = 'strambat';

if ($membru !== null) {
    $eActiv = (string) $membru['stare'] === 'activ';
    $abonat = (int) $membru['newsletter'] === 1;

    /**
     * Un cont care nu mai e activ nu primește oricum nimic. I se spune „gata",
     * nu „link stricat": pentru omul din fața ecranului rezultatul e același —
     * nu mai vine nimic — iar un mesaj de eroare l-ar trimite să caute o
     * problemă care nu există.
     */
    if (!$eActiv) {
        $ce = 'era_stins';
    } elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        // opresteNewsletterul() întoarce false dacă bifa era deja stinsă —
        // două apăsări pe același buton nu sunt o eroare.
        $ce = opresteNewsletterul((int) $membru['id']) ? 'gata' : 'era_stins';
    } else {
        $ce = $abonat ? 'intreaba' : 'era_stins';
    }
}

$vorbe = [
    'intreaba' => [
        'titlu' => 'Nu mai vrei mesajul zilnic?',
        'text'  => 'Apasă butonul de mai jos și nu-ți mai trimitem lista cu ce se '
                 . 'întâmplă azi în oraș. Restul mesajelor de pe site — cele despre '
                 . 'evenimentele la care te-ai înscris — rămân cum erau.',
    ],
    'gata' => [
        'titlu' => 'Gata, nu-ți mai trimitem',
        'text'  => 'Newsletterul zilnic e oprit. Dacă te răzgândești, îl pornești la '
                 . 'loc din setările contului, de la „E-mailuri de la noi".',
    ],
    'era_stins' => [
        'titlu' => 'Nu-ți trimiteam oricum',
        'text'  => 'Newsletterul zilnic era deja oprit pentru adresa asta. Dacă totuși '
                 . 'îți vine ceva, e un mesaj despre un eveniment la care ești înscris, '
                 . 'nu newsletterul.',
    ],
    'strambat' => [
        'titlu' => 'Linkul nu e bun',
        'text'  => 'Adresa asta nu duce nicăieri — poate a fost tăiată la copiere. '
                 . 'Newsletterul se oprește oricând din setările contului, de la '
                 . '„E-mailuri de la noi".',
    ],
];

$v = $vorbe[$ce];

$reusit  = in_array($ce, ['gata', 'era_stins'], true);
$titlu   = $v['titlu'] . ' — PulsulOrasului.Ro';
$noindex = true;

require __DIR__ . '/inc/antet.php';
?>


<main id="main">
  <div class="wrap">
    <div class="auth">
      <div class="auth__card">
        <div class="auth-panel">
          <div class="done <?= $reusit ? 'done--ok' : ($ce === 'intreaba' ? '' : 'done--fail') ?>">
            <span class="done__ico" aria-hidden="true">
              <?php if ($reusit): ?>
                <svg class="ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/></svg>
              <?php elseif ($ce === 'intreaba'): ?>
                <svg class="ico" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.8 7 8.2 5.6L20.2 7"/></svg>
              <?php else: ?>
                <svg class="ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5"/><path d="M12 16.4v.1"/></svg>
              <?php endif; ?>
            </span>

            <h1 class="done__title"><?= h($v['titlu']) ?></h1>
            <p class="done__text"><?= h($v['text']) ?></p>

            <?php if ($ce === 'intreaba'): ?>
            <!--
              Butonul trimite prin POST spre ACEEAȘI adresă, cu semnătura la
              locul ei. Fără token CSRF, ca la findme.php: omul nu e conectat,
              deci n-avem sesiune în care să-l ținem, iar tot ce se poate face
              cu o cerere pusă la cale de altcineva e să oprești un mesaj pe
              care oricum îl poți porni la loc dintr-o bifă.
            -->
            <form method="post"
                  action="/dezabonare.php?m=<?= (int) $membru['id'] ?>&amp;s=<?= h(semnaturaDezabonare((int) $membru['id'])) ?>">
              <div class="done__actions">
                <button class="btn btn--primary" type="submit">Da, oprește-l</button>
                <a class="btn btn--ghost" href="/index.php">Lasă-l pornit</a>
              </div>
            </form>
            <?php else: ?>
            <div class="done__actions">
              <a class="btn btn--primary" href="/index.php">Vezi ce se mai întâmplă în oraș</a>
              <a class="btn btn--ghost" href="/setari.php">Setările contului</a>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
