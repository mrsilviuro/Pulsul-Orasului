<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — antetul comun al tuturor paginilor.
 *
 * Se include la începutul fiecărei pagini. Înainte de include se pot seta:
 *
 *   $titlu     — titlul paginii (obligatoriu)
 *   $descriere — textul pentru motoarele de căutare
 *   $pagina    — care element de meniu e marcat activ:
 *                'acasa' | 'despre' | 'cont' | 'contact' | ''
 *   $noindex   — true dacă pagina nu trebuie indexată
 *   $bodyAttr  — atribute suplimentare pe <body>
 *
 * Meniul se construiește o singură dată, aici. Când se schimbă ceva în el,
 * se schimbă pe tot site-ul.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/imagini.php';

$titlu     = $titlu     ?? 'PulsulOrasului.Ro';
$descriere = $descriere ?? 'Evenimente locale, sport, cultură și tot ce mișcă în oraș.';
$pagina    = $pagina    ?? '';
$noindex   = $noindex   ?? false;
$bodyAttr  = $bodyAttr  ?? '';

$membru = membruCurent();
$logat  = $membru !== null;

// Cine a intrat cu parola temporară e trimis să-și aleagă una nouă, oriunde
// ar încerca să meargă. Verificarea stă aici, în antetul comun, ca să nu fie
// nevoie să fie repetată în fiecare pagină — și ca să nu poată fi uitată.
opresteDacaTrebuieParolaNoua();

/* ------------------------- Antete de siguranță ------------------------ */

// Pagina nu poate fi încărcată într-un cadru pe alt site (clickjacking).
header('X-Frame-Options: DENY');
// Browserul nu ghicește tipul fișierelor, ci îl respectă pe cel trimis.
header('X-Content-Type-Options: nosniff');
// Către alte site-uri nu se trimite calea completă, doar domeniul.
header('Referrer-Policy: strict-origin-when-cross-origin');
// Fără acces la cameră, microfon sau localizare.
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

/* --------------------------- Meniul principal ------------------------- */

$meniu = [
    [
        'cheie' => 'acasa',
        'href'  => 'index.php',
        'text'  => 'Acasă',
        'ico'   => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.5V20h13V9.5"/><path d="M10 20v-5.5h4V20"/>',
    ],
    [
        'cheie' => 'despre',
        'href'  => 'despre.php',
        'text'  => 'Despre',
        'ico'   => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5"/><path d="M12 7.6v.1"/>',
    ],
    [
        'cheie' => 'contact',
        'href'  => 'contact.php',
        'text'  => 'Contact',
        'ico'   => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.8 7 8.2 5.6L20.2 7"/>',
    ],
];

// După „Contact" vine fie invitația de înscriere, fie ieșirea din cont.
if ($logat) {
    $meniu[] = [
        'cheie' => 'iesire',
        'href'  => 'iesire.php?token=' . urlencode(tokenCsrf()),
        'text'  => 'Deloghează-te',
        'clasa' => 'nav__link--iesire',
        'ico'   => '<path d="M15 16.5v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-13a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v2"/>'
                 . '<path d="M10 12h10"/><path d="m17 8.5 3.5 3.5L17 15.5"/>',
    ];
} else {
    $meniu[] = [
        'cheie' => 'cont',
        'href'  => 'login.php#inregistrare',
        'text'  => 'Alătură-te și tu',
        'clasa' => 'nav__link--alatura',
        'ico'   => '<circle cx="9.5" cy="8.5" r="3.5"/><path d="M3 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/>'
                 . '<path d="M18.5 8v6"/><path d="M21.5 11h-6"/>',
    ];
}
?>
<!DOCTYPE html>
<html lang="ro" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($titlu) ?></title>
<meta name="description" content="<?= h($descriere) ?>">
<?php if ($noindex): ?>
<meta name="robots" content="noindex">
<?php endif; ?>
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0d1015" media="(prefers-color-scheme: dark)">

<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=28">

<!-- Setează tema ÎNAINTE de randare, ca să nu apară un flash alb pe dark mode -->
<script>
(function () {
  try {
    var saved = localStorage.getItem('po-theme');
    var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);
  } catch (e) {}
})();
</script>
</head>

<body data-logat="<?= $logat ? 'true' : 'false' ?>"
<?php if ($logat): ?>      data-user-nume="<?= h(numeAfisat($membru['nume'], $membru['prenume'])) ?>"
      data-user-initiala="<?= h(mb_substr($membru['prenume'], 0, 1, 'UTF-8')) ?>"
<?php endif; ?><?= $bodyAttr !== '' ? '      ' . $bodyAttr : '' ?>>

<a class="skip-link" href="#main">Sari la conținut</a>

<!-- ============================ BARA DE MENIU ============================ -->
<header class="site-header">
  <div class="nav">

    <a class="logo" href="index.php" aria-label="PulsulOrasului.Ro — Acasă">
      <span class="logo__mark" aria-hidden="true">
        <svg viewBox="0 0 32 32" fill="none">
          <path d="M2 16h6.2l2.6-7.4a1 1 0 0 1 1.9.05l4.1 14.2a1 1 0 0 0 1.9.04L21.4 16H30"
                stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
      <span class="logo__text">
        Pulsul<span class="logo__accent">Orasului</span><span class="logo__tld">.Ro</span>
      </span>
    </a>

    <nav class="nav__menu" id="nav-menu" aria-label="Meniu principal">
      <ul>
        <?php foreach ($meniu as $element): ?>
          <?php
            $activ   = ($pagina !== '' && $pagina === $element['cheie']);
            $clase   = 'nav__link';
            if (!empty($element['clasa'])) $clase .= ' ' . $element['clasa'];
            if ($activ)                    $clase .= ' is-active';
          ?>
        <li>
          <a class="<?= $clase ?>" href="<?= h($element['href']) ?>"<?= $activ ? ' aria-current="page"' : '' ?>>
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><?= $element['ico'] ?></svg>
            <span><?= h($element['text']) ?></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="nav__actions">
      <?php if ($logat): ?>
      <a class="nav__eu" href="profil.php" title="Profilul tău">
        <span class="nav__eu-nume"><?= h(numeAfisat($membru['nume'], $membru['prenume'])) ?></span>
        <!-- Cine are poză o vede aici; ceilalți văd inițiala prenumelui. -->
        <span class="nav__eu-avatar" aria-hidden="true"><?php
          if (estePozaValida($membru['poza'] ?? null)):
            ?><img src="<?= h(urlPoza($membru['poza'], true)) ?>" alt="" width="26" height="26"><?php
          else:
            echo h(mb_substr($membru['prenume'], 0, 1, 'UTF-8'));
          endif;
        ?></span>
      </a>
      <?php endif; ?>

      <?php if ($logat): ?>
      <!-- Rotița stă doar pentru cine e conectat: n-are ce seta un vizitator.
           Ca și creionul de pe poza de profil, nu e ascunsă din CSS, ci pur și
           simplu nu ajunge în pagină pentru ceilalți. -->
      <a class="nav__btn nav__setari" href="setari.php"
         title="Setările contului" aria-label="Setările contului">
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="12" r="3.2"/>
          <path d="M19.4 14.5a1.5 1.5 0 0 0 .3 1.7l.1.1a1.8 1.8 0 1 1-2.6 2.6l-.1-.1a1.5 1.5 0 0 0-1.7-.3 1.5 1.5 0 0 0-.9 1.4v.3a1.8 1.8 0 1 1-3.6 0v-.2a1.5 1.5 0 0 0-1-1.4 1.5 1.5 0 0 0-1.7.3l-.1.1a1.8 1.8 0 1 1-2.6-2.6l.1-.1a1.5 1.5 0 0 0 .3-1.7 1.5 1.5 0 0 0-1.4-.9h-.3a1.8 1.8 0 1 1 0-3.6h.2a1.5 1.5 0 0 0 1.4-1 1.5 1.5 0 0 0-.3-1.7l-.1-.1a1.8 1.8 0 1 1 2.6-2.6l.1.1a1.5 1.5 0 0 0 1.7.3h.1a1.5 1.5 0 0 0 .9-1.4v-.3a1.8 1.8 0 1 1 3.6 0v.2a1.5 1.5 0 0 0 .9 1.4 1.5 1.5 0 0 0 1.7-.3l.1-.1a1.8 1.8 0 1 1 2.6 2.6l-.1.1a1.5 1.5 0 0 0-.3 1.7v.1a1.5 1.5 0 0 0 1.4.9h.3a1.8 1.8 0 1 1 0 3.6h-.2a1.5 1.5 0 0 0-1.4.9Z"/>
        </svg>
      </a>
      <?php endif; ?>

      <button class="nav__btn theme-toggle" id="theme-toggle" type="button"
              aria-label="Schimbă tema" title="Schimbă tema (întuneric / luminos)">
        <svg class="ico ico--sun" viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="12" r="4.2"/>
          <path d="M12 2.5v2.2M12 19.3v2.2M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6"/>
        </svg>
        <svg class="ico ico--moon" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M20.5 14.3A8.6 8.6 0 0 1 9.7 3.5a8.6 8.6 0 1 0 10.8 10.8Z"/>
        </svg>
      </button>

      <button class="nav__burger" id="nav-burger" type="button"
              aria-label="Deschide meniul" aria-expanded="false" aria-controls="nav-menu">
        <span></span><span></span><span></span>
      </button>
    </div>

  </div>
</header>
