<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — chatul.
 *
 * O cameră pe ecran: mesajele sus, caseta de scris jos. Care cameră se
 * hotărăște din adresă (`?camera=roman`), prin cameraCeruta() din inc/chat.php
 * — vezi acolo pentru regulă și pentru de ce un nume care nu duce nicăieri
 * deschide camera generală în loc de un ecran roșu.
 */

require_once __DIR__ . '/inc/chat.php';

/**
 * Chatul se citește doar din interior.
 *
 * Spre deosebire de pagina unui eveniment, care se vede de oricine, aici nici
 * măcar cititul nu e deschis: o discuție între oameni care se cunosc din oraș
 * nu e o pagină de arătat lumii, iar cine scrie în ea trebuie să știe cine o
 * citește.
 *
 * cereIntrare() duce la login și aduce omul înapoi FIX în camera asta — calea
 * trece prin caleInterna(), care taie orice nu e de-al casei.
 */
if (!esteLogat()) {
    $interogare = (string) ($_SERVER['QUERY_STRING'] ?? '');

    cereIntrare('/chat.php' . ($interogare !== '' ? '?' . $interogare : ''));
}

$membru   = membruCurent();
$membruId = (int) $membru['id'];
$eStaff   = esteStaff($membru);

$camera = cameraCeruta($_GET['camera'] ?? null, $membruId, $eStaff);

/* ===================== MESAJUL TRIMIS FĂRĂ JAVASCRIPT ================= */

/**
 * Caseta de scris e un formular adevărat, cu `method="post"`. Cu JavaScript,
 * mesajul pleacă pe lângă pagină și apare pe loc; fără el, ajunge AICI.
 *
 * N-ar fi fost de ajuns să pun `method="post"` în HTML și să mă opresc: un
 * formular care nu duce nicăieri e mai rău decât unul care lipsește — omul
 * scrie, apasă, pagina clipește și mesajul lui nu e nicăieri.
 *
 * Verificările sunt EXACT cele din api/chat.php, prin aceleași funcții. Două
 * uși spre același lucru, una singură care hotărăște ce trece.
 */
$eroareChat = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!tokenCsrfValid(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
        $eroareChat = 'Sesiunea a expirat. Reîncarcă pagina și încearcă din nou.';
    } else {
        $verificat = verificaMesajChat($_POST['mesaj'] ?? null);
        $asteptare = asteptareChat($membruId);

        if ($verificat['eroare'] !== '') {
            $eroareChat = $verificat['eroare'];
        } elseif ($asteptare > 0) {
            $eroareChat = 'Mai încet — mai așteaptă ' . $asteptare
                        . ($asteptare === 1 ? ' secundă.' : ' secunde.');
        } else {
            salveazaMesajChat($camera['cheie'], $membruId, $verificat['text']);

            /**
             * Trimite-redirecționează-arată: după scriere se pleacă printr-un
             * Location, ca o reîncărcare a paginii să nu întrebe „retrimiți
             * formularul?" și să nu scrie mesajul a doua oară.
             */
            header('Location: ' . urlCamera($camera['slug']));
            exit;
        }
    }
}

/**
 * Primul teanc de mesaje îl scrie PHP, nu se cere din JS după încărcare.
 *
 * Fără el, cine deschide chatul ar vedea un dreptunghi gol până pleacă și se
 * întoarce o primă întrebare — iar pe o legătură proastă golul acela ține
 * secunde bune. Restul vin prin api/chat-mesaje.php, din când în când.
 */
$mesaje = mesajeleCamerei($camera['cheie']);

$contextChat = [
    'membru_id' => $membruId,
    'e_staff'   => $eStaff,
];

/** Cel mai mare id de pe ecran — de aici încolo întreabă browserul. */
$ultimId = 0;

foreach ($mesaje as $m) {
    $ultimId = max($ultimId, (int) $m['id']);
}

/**
 * Clipa de la care browserul întreabă ce s-a mai șters. E ceasul SERVERULUI,
 * pus în pagină, nu al lui — vezi mesajeSterseDupa().
 */
$momentChat = acum();

$titlu = $camera['fel'] === 'general'
    ? 'Chat — PulsulOrasului.Ro'
    : 'Chat: ' . $camera['nume'] . ' — PulsulOrasului.Ro';

$descriere = 'Discută cu oamenii din oraș: ce se întâmplă, unde se merge, cine vine.';
$pagina    = 'chat';

require __DIR__ . '/inc/antet.php';
?>

<main id="main" class="chat-pagina">
  <div class="wrap">

    <!-- ============================== ANTETUL ==========================
      Ce cameră e și cum se iese din ea. La camera unui eveniment, titlul e
      legătură spre eveniment: cine a intrat în discuție de pe o adresă dată pe
      WhatsApp trebuie să poată ajunge la anunțul despre care se vorbește.
    ============================================================== -->
    <div class="chat__antet">
      <div class="chat__cine">
        <p class="eyebrow"><span class="pulse-dot" aria-hidden="true"></span> Chat</p>

        <?php if ($camera['fel'] === 'eveniment'): ?>
        <h1 class="chat__titlu">
          <a href="<?= h(urlEveniment($camera['slug'])) ?>"><?= h($camera['nume']) ?></a>
        </h1>
        <p class="chat__lamurire">Discuția celor care merg la acest eveniment.</p>
        <?php else: ?>
        <h1 class="chat__titlu"><?= h($camera['nume']) ?></h1>
        <p class="chat__lamurire">
          <?= $camera['fel'] === 'oras'
                ? 'Ce se întâmplă în ' . h($camera['nume']) . '.'
                : 'Camera tuturor — orice oraș, orice subiect.' ?>
        </p>
        <?php endif; ?>
      </div>

      <?php if ($camera['fel'] !== 'eveniment'): ?>
      <!-- ========================== CAMERELE =========================
        Numai în chatul general. Camera unui eveniment nu e în listă,
        dinadins: la ea se ajunge de pe pagina evenimentului, nu dintr-o
        listă care ar fi crescut cu fiecare anunț publicat.

        Sunt LEGĂTURI, nu butoane: merg și fără JavaScript, se deschid în
        filă nouă cu clic pe mijloc și se pot da mai departe. Fiecare
        cameră are adresa ei — o discuție se dă pe WhatsApp cu tot cu ea.
      ============================================================== -->
      <nav class="chat__camere chips" aria-label="Alege camera">
        <?php foreach (camereGenerale() as $c): ?>
        <a class="chip<?= $camera['cheie'] === $c['cheie'] ? ' is-active' : '' ?>"
           href="<?= h(urlCamera($c['slug'])) ?>"
           <?= $camera['cheie'] === $c['cheie'] ? 'aria-current="true"' : '' ?>><?= h($c['nume']) ?></a>
        <?php endforeach; ?>
      </nav>
      <?php endif; ?>
    </div>

    <!-- ============================== CAMERA ===========================
      Firul de mesaje și caseta de scris, într-un singur bloc înalt cât
      ecranul de sub meniu. Amândouă au aceeași lățime — a lui `.wrap`, adică
      --wrap din :root — fiindcă sunt același lucru: o discuție.

      `data-chat` e mânerul de care se prinde JS-ul; fără el, blocul rămâne
      un formular obișnuit care se trimite cu POST și reîncarcă pagina.
    ============================================================== -->
    <section class="chat" data-chat
             data-camera="<?= h($camera['cheie']) ?>"
             data-camera-adresa="<?= h($camera['slug']) ?>"
             data-ultim-id="<?= $ultimId ?>"
             data-moment="<?= h($momentChat) ?>"
             aria-label="Discuția din camera <?= h($camera['nume']) ?>">

      <!--
        `aria-live="polite"` ca cine citește pagina cu urechea să afle mesajele
        noi fără să caute; „polite" și nu „assertive", fiindcă o discuție nu
        trebuie să întrerupă omul la fiecare vorbă.
      -->
      <ol class="chat__fir" id="chat-fir" data-chat-fir
          aria-live="polite" aria-label="Mesaje" tabindex="0">
        <?= randeazaMesajeChat($mesaje, $contextChat) ?>
      </ol>

      <!-- Se arată doar când firul e gol; JS-ul îl scoate la primul mesaj. -->
      <p class="chat__gol" data-chat-gol <?= $mesaje !== [] ? 'hidden' : '' ?>>
        Încă nu s-a spus nimic aici. Începe tu.
      </p>

      <!-- ============================ CASETA ==========================
        Un formular adevărat, cu `method="post"`: fără JavaScript se trimite
        mesajul și se reîncarcă pagina, exact ca înainte de JS. Cu el, se
        trimite pe lângă pagină și mesajul apare pe loc.
      ============================================================== -->
      <form class="chat__forma" method="post" action="chat.php<?= $camera['slug'] !== ''
                ? '?camera=' . h(urlencode($camera['slug'])) : '' ?>"
            data-chat-forma>
        <input type="hidden" name="csrf" value="<?= h(tokenCsrf()) ?>">
        <input type="hidden" name="camera" value="<?= h($camera['slug']) ?>">

        <label class="sr-only" for="chat-mesaj">Scrie un mesaj</label>

        <!--
          `rows="1"` și crescut din JS pe măsură ce se scrie: pe un rând cât e
          o vorbă scurtă — adică aproape mereu — iar cine are de scris un
          paragraf îl vede întreg, fără să deruleze într-o fantă de un rând.

          Nu e `<input>` tocmai ca să încapă rândul nou: Enter trimite,
          Shift+Enter trece la rândul următor.
        -->
        <textarea class="chat__camp" id="chat-mesaj" name="mesaj" rows="1"
                  maxlength="<?= MESAJ_CHAT_MAX ?>"
                  placeholder="Scrie un mesaj…"
                  data-chat-camp required></textarea>

        <button class="btn btn--primary chat__trimite" type="submit" data-chat-trimite>
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4 12 20 4l-8 16-2-6-6-2Z"/>
          </svg>
          <span class="sr-only">Trimite</span>
        </button>
      </form>

      <!-- Ce n-a mers: mesaj prea lung, prea repede, sesiune expirată. Îl scrie
           ori PHP (când s-a trimis fără JavaScript), ori JS-ul. Gol, nu ocupă
           loc. -->
      <p class="chat__eroare" data-chat-eroare role="alert"
         <?= $eroareChat === '' ? 'hidden' : '' ?>><?= h($eroareChat) ?></p>
    </section>

  </div>
</main>

<?php require __DIR__ . '/inc/subsol.php'; ?>
