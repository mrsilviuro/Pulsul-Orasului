<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — butonul „Continuă cu Google", plus linia „sau".
 *
 * Se include acolo unde e nevoie de el. Înainte de include se pot seta:
 *
 *   $textButon      — ce scrie pe buton
 *   $textDespartitor— ce scrie pe linia de sub el
 *   $redirectDupa   — unde să ajungă omul după ce intră (cale de pe site)
 *
 * Dacă în config.php nu sunt puse datele de la Google, nu se tipărește nimic:
 * nici butonul, nici linia. Site-ul merge normal și nimeni nu apasă un buton
 * care oricum ar da eroare.
 */

require_once __DIR__ . '/google.php';

if (!googleEsteConfigurat()) {
    return;
}

$textButon       = $textButon       ?? 'Continuă cu Google';
$textDespartitor = $textDespartitor ?? 'sau cu e-mail';
$redirectDupa    = $redirectDupa    ?? '';

$adresa = 'google.php';
if ($redirectDupa !== '') {
    $adresa .= '?redirect=' . urlencode($redirectDupa);
}
?>
<!--
  E o legătură, nu un buton de formular: plecarea spre Google e o simplă
  schimbare de pagină, deci merge și fără JavaScript, se poate deschide în
  filă nouă și se comportă cum se așteaptă oricine de la un link.
-->
<a class="btn-google" href="<?= h($adresa) ?>" rel="nofollow">
  <svg class="btn-google__ico" viewBox="0 0 48 48" aria-hidden="true">
    <path fill="#4285F4" d="M45.1 24.5c0-1.6-.1-3.2-.4-4.7H24v8.9h11.8c-.5 2.7-2 5-4.4 6.6v5.5h7.1c4.2-3.8 6.6-9.5 6.6-16.3z"/>
    <path fill="#34A853" d="M24 46c6 0 11-2 14.6-5.3l-7.1-5.5c-2 1.3-4.5 2.1-7.5 2.1-5.8 0-10.7-3.9-12.4-9.1H4.3v5.7C7.9 41.1 15.4 46 24 46z"/>
    <path fill="#FBBC05" d="M11.6 28.2c-.5-1.3-.7-2.7-.7-4.2s.3-2.9.7-4.2v-5.7H4.3A22 22 0 0 0 2 24c0 3.6.9 6.9 2.3 9.9l7.3-5.7z"/>
    <path fill="#EA4335" d="M24 10.7c3.3 0 6.2 1.1 8.5 3.3l6.3-6.3C35 4.1 30 2 24 2 15.4 2 7.9 6.9 4.3 14.1l7.3 5.7c1.7-5.2 6.6-9.1 12.4-9.1z"/>
  </svg>
  <span><?= h($textButon) ?></span>
</a>

<div class="auth__divider"><span><?= h($textDespartitor) ?></span></div>
<?php
// Variabilele se golesc, ca a doua includere din aceeași pagină să nu
// moștenească din greșeală textele de la prima.
unset($textButon, $textDespartitor, $redirectDupa);
