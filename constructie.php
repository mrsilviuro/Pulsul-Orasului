<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — afișul de pe ușă, cât timp se lucrează la site.
 *
 * Se arată cât `in_constructie` din inc/config.php e pornit. Cine trece de ea
 * și pe ce uși — în inc/constructie.php; lacătul însuși stă la coada lui
 * inc/auth.php.
 *
 * NU FOLOSEȘTE inc/antet.php ȘI inc/subsol.php, singura pagină de pe site care
 * n-o face. Antetul aduce cu el meniul întreg — Acasă, Despre, Contact — adică
 * exact paginile închise în clipa asta. Un afiș de „revenim curând" cu un meniu
 * din care nu merge nimic e mai rău decât niciun meniu: omul apasă, ajunge
 * înapoi aici, și crede că s-a stricat ceva.
 *
 * Rămân comune cele care contează: `assets/css/style.css` și
 * `assets/js/main.js`, ca peste tot (regula 1 din CLAUDE.md).
 */

require_once __DIR__ . '/inc/auth.php';

/**
 * Cu site-ul deschis, pagina asta n-are ce spune.
 *
 * Fără redirecționarea de aici, ar fi rămas la adresa ei pentru totdeauna —
 * un „ne pregătim" pe care Google l-ar fi ținut în listă și după deschidere,
 * iar cine îl deschidea dintr-un bookmark ar fi crezut că încă n-am pornit.
 */
if (!siteInConstructie()) {
    header('Location: /index.php');
    exit;
}

/**
 * Omul de casă care ajunge aici e trimis înăuntru: pentru el site-ul e
 * deschis, deci afișul nu-l privește. Așa, un bookmark vechi nu-l lasă pe
 * dinafară din propriul site.
 */
if (esteStaff()) {
    header('Location: /index.php');
    exit;
}

/* ==================== ÎNSCRIEREA FĂRĂ JAVASCRIPT ===================== */

/**
 * Formularul e unul adevărat, cu `method="post"`, iar aici e capătul lui.
 *
 * Cu JavaScript adresa pleacă prin api/newsletter.php și pagina nu clipește;
 * fără el, ajunge aici. N-ar fi fost de ajuns să scriu `method="post"` în HTML
 * și să mă opresc: un formular care nu duce nicăieri e mai rău decât unul care
 * lipsește — omul scrie, apasă, pagina clipește și adresa lui nu e nicăieri.
 *
 * Verificările sunt EXACT cele din API, prin aceleași funcții. Două uși spre
 * același lucru, una singură care hotărăște ce trece.
 */
$mesajBun  = '';
$mesajRau  = '';
$emailScris = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $emailScris = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';

    if (!tokenCsrfValid(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
        $mesajRau = 'Reîncarcă pagina și încearcă din nou.';
    } elseif (trim((string) ($_POST['website'] ?? '')) !== '') {
        // Capcana pentru roboți: se răspunde ca și cum ar fi mers.
        $mesajBun = 'Gata! Îți dăm de veste imediat ce deschidem.';
    } else {
        // Aceeași funcție pe care o cheamă și api/newsletter.php: verificarea
        // adresei, limita pe IP și scrierea, într-un singur loc.
        $rezultat = inscrieLaVesti($emailScris);

        if ($rezultat['ok']) {
            $mesajBun   = $rezultat['mesaj'];
            $emailScris = '';
        } else {
            $mesajRau = $rezultat['mesaj'];
        }
    }
}

/**
 * „Nu acum", nu „nu există".
 *
 * 503 e răspunsul cinstit cât ține lucrarea: motoarele de căutare știu să
 * revină și nu pun afișul ăsta în locul site-ului adevărat. Cu 200, Google ar
 * fi indexat „ne pregătim" ca fiind pagina noastră de start, și ar fi rămas
 * așa săptămâni după deschidere.
 *
 * `Retry-After` în secunde: o zi. E o estimare, nu o promisiune — n-avem încă
 * o dată de lansare, iar antetul ăsta nu se vede nicăieri pe ecran.
 */
http_response_code(503);
header('Retry-After: 86400');

// Aceleași antete ca pe tot site-ul. Pagina asta nu trece prin inc/antet.php,
// dar e singura care se vede cu site-ul închis — deci tocmai ea n-are voie să
// rămână fără pază.
antetedeSiguranta();
?>
<!doctype html>
<html lang="ro" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Se pregătește ceva — PulsulOrasului.Ro</title>
<meta name="description" content="Pregătim ceva tare pentru orașul tău. Lasă-ne adresa și îți dăm de veste când deschidem.">

<!-- Cât e închis, n-are ce căuta în căutări. -->
<meta name="robots" content="noindex, follow">

<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css?v=109">
</head>

<!--
  `data-theme="dark"` scris de-a dreptul în <html>, nu lăsat pe seama temei
  omului: pagina e o singură fotografie de amurg, cu text alb peste ea. Pe alb
  n-ar avea ce să însemne, iar butonul de temă nici nu există aici.
-->
<body class="constructie">

<!-- ============================== FUNDALUL ==============================
  Poza stă într-un element al ei, nu ca `background-image` pe body: așa poate
  fi crescută peste marginile ecranului (`inset: -40px`) cât să nu se vadă
  colțurile spălăcite pe care le lasă blurul. Un blur aplicat pe fundalul
  paginii ar fi tras după el și textul.

  `aria-hidden` fiindcă n-are nimic de spus: e o stare, nu o informație.
============================================================== -->
<div class="constructie__fundal" aria-hidden="true">
  <img src="/assets/img/constructie-fundal.svg" alt="" width="1600" height="900" decoding="async">
</div>
<div class="constructie__voal" aria-hidden="true"></div>

<main class="constructie__cuprins" id="main">

  <a class="logo logo--constructie" href="/constructie.php" aria-label="PulsulOrasului.Ro">
    <span class="logo__mark" aria-hidden="true">
      <svg viewBox="0 0 32 32" fill="none">
        <path d="M2 16h6.2l2.6-7.4a1 1 0 0 1 1.9.05l4.1 14.2a1 1 0 0 0 1.9.04L21.4 16H30"
              stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </span>
    <span class="logo__text">Pulsul<span class="logo__accent">Orasului</span><span class="logo__tld">.Ro</span></span>
  </a>

  <p class="constructie__eyebrow">
    <span class="pulse-dot" aria-hidden="true"></span> Lucrăm la el
  </p>

  <h1 class="constructie__titlu">Se pregătește ceva tare<br>pentru orașul tău.</h1>

  <p class="constructie__text">
    Un loc în care afli ce se întâmplă prin oraș și găsești oameni cu care să
    ieși din casă. Încă îl construim, așa că n-avem o dată de lansare — dar
    când deschidem, vrem să fii printre primii care intră.
  </p>

  <!-- ============================ ÎNSCRIEREA ===========================
    Un formular adevărat, cu `method="post"`: fără JavaScript se trimite și se
    reîncarcă pagina, iar constructie.php îl primește sus. Cu JS, adresa pleacă
    pe lângă pagină și mesajul apare pe loc.
  ============================================================== -->
  <form class="constructie__forma" method="post" action="/constructie.php" data-newsletter>
    <input type="hidden" name="csrf" value="<?= h(tokenCsrf()) ?>">

    <!--
      Capcana pentru roboți. Nu e `type="hidden"`: un robot sare peste alea.
      E un câmp adevărat, dus din CSS unde nu-l vede nimeni, cu autocomplete
      stins ca browserul să nu i-l umple singur omului.
    -->
    <div class="capcana" aria-hidden="true">
      <label for="nl-website">Nu completa câmpul ăsta</label>
      <input type="text" id="nl-website" name="website" tabindex="-1" autocomplete="off">
    </div>

    <div class="constructie__rand">
      <label class="sr-only" for="nl-email">Adresa ta de e-mail</label>
      <input class="constructie__camp" type="email" id="nl-email" name="email"
             value="<?= h($emailScris) ?>"
             maxlength="<?= EMAIL_MAX ?>" autocomplete="email"
             placeholder="adresa@ta.ro" required
             aria-describedby="nl-raspuns">

      <button class="btn btn--primary constructie__buton" type="submit" data-newsletter-buton>
        Anunță-mă
      </button>
    </div>

    <p class="constructie__marunt">
      Îți scriem o singură dată, când deschidem. Nimic altceva, niciodată.
      Ce facem cu adresa ta scrie în
      <a href="/confidentialitate.php">politica de confidențialitate</a>.
    </p>

    <!-- Locul în care se scrie ce a ieșit — și din PHP (fără JS), și din JS. -->
    <p class="constructie__raspuns<?= $mesajRau !== '' ? ' constructie__raspuns--rau' : '' ?>"
       id="nl-raspuns" data-newsletter-raspuns role="status"
       <?= ($mesajBun === '' && $mesajRau === '') ? 'hidden' : '' ?>><?= h($mesajBun !== '' ? $mesajBun : $mesajRau) ?></p>
  </form>

  <!--
    Ușa oamenilor de casă. Nu scrie „staff" pe ea și nu e ascunsă: e legătura
    obișnuită de intrare în cont, care există pe orice site. Cine n-are ce
    căuta înăuntru nu trece de ea nici dacă apasă — vezi api/autentificare.php.
  -->
  <p class="constructie__intrare">
    <a href="/login.php">Ai deja cont? Intră aici</a>
  </p>

</main>

<script src="/assets/js/main.js?v=94"></script>
</body>
</html>
