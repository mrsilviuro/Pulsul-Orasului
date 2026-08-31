<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — politica de cookies.
 *
 * A treia dintre paginile cu putere juridică (vezi termeni.php pentru tiparul
 * comun).
 *
 * TABELUL DE MAI JOS E LISTA ADEVĂRATĂ, nu una copiată de pe alt site. Fiecare
 * rând a fost căutat în cod:
 *
 *   PHPSESSID  → session_start() din inc/bootstrap.php, cu parametrii de acolo
 *   po_amintit → COOKIE_TINE_MINTE din inc/auth.php, ZILE_TINE_MINTE zile
 *   po-theme        → localStorage, în assets/js/main.js și inc/antet.php
 *   po-toast        → sessionStorage, main.js
 *   po-previzualizare-* → localStorage, main.js
 *
 * DE CE NU AVEM BANNER DE COOKIE-URI, și de ce e corect așa: nu punem niciun
 * cookie de măsurare sau de reclamă. Cele două care rămân sunt strict necesare
 * ca site-ul să funcționeze (sesiunea și „ține-mă minte", pusă doar dacă omul
 * bifează el însuși), iar pentru acelea legea nu cere acord. Un banner care
 * cere acord pentru nimic e teatru, și încă unul care învață oamenii să apese
 * „Accept" fără să citească.
 *
 * DACĂ SE ADAUGĂ VREODATĂ ceva de măsurare — Analytics, un pixel, orice —
 * rândul ăsta nu mai e adevărat și trebuie banner cu acord ÎNAINTE de încărcare.
 */

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';   // ZILE_TINE_MINTE

$titlu     = 'Politica de cookies — PulsulOrasului.Ro';
$descriere = 'Ce cookie-uri folosim și de ce. Sunt două, amândouă necesare ca '
           . 'site-ul să meargă. Niciunul de reclamă sau de urmărire.';
$pagina    = '';

/* Se schimbă DE MÂNĂ, la fiecare îndreptare a textului de mai jos. */
$dataDocumentului = '30 august 2026';

require __DIR__ . '/inc/antet.php';
?>

<main id="main">
  <div class="wrap">

  <nav class="crumbs" aria-label="Navigare">
  <a href="/index.php">Acasă</a>
  <span aria-hidden="true">/</span>
  <span class="crumbs__current">Politica de cookies</span>
  </nav>
  <h1>Politica de cookies</h1>
  <p class="doc-data">Ultima actualizare: <?= h($dataDocumentului) ?></p>

    <div class="prose">

    <h2>Ce este un fișier cookie</h2>

    <p>
    Un fișier de mici dimensiuni pe care site-ul nostru îl salvează în browserul tău
    pentru a-și aminti anumite preferințe de la o pagină la alta, cum ar fi starea de
    autentificare. Fără acest fișier, ar fi necesar să introduci parola la fiecare
    accesare a unei pagini noi.
    </p>

    <h2>De ce nu afișăm un banner pentru acordul de cookie-uri</h2>

    <p>
    Deoarece nu avem motive să facem asta! Legea impune afișarea unui banner de consimțământ
    pentru cookie-urile de analiză (trafic) sau pentru reclame,  iar platforma noastră nu
    folosește niciunul dintre acestea. Folosim exclusiv cookie-uri tehnic esențiale pentru
    funcționarea site-ului, iar pentru acestea legea nu solicită acord prealabil.
    </p>

    <p>
    Un banner care ți-ar cere să apeși „Accept” fără un motiv real doar te-ar obosi și te-ar
    obișnui să dai click fără să citești, ceea ce nu aduce niciun beneficiu.
    </p>

    <h2>Cookie-urile pe care le utilizăm</h2>

    <div class="tabel-scroll">
    <table class="tabel-doc">
    <thead>
    <tr>
    <th scope="col">Nume</th>
    <th scope="col">La ce folosește</th>
    <th scope="col">Cât timp este păstrat</th>
    </tr>
    </thead>
    <tbody>
    <tr>
    <td><span class="mono">PHPSESSID</span></td>
    <td>
    Păstrează starea ta de autentificare pe parcursul navigării pe site. De
    asemenea, asigură protecția împotriva cererilor neautorizate trimise de pe
    alte platforme. Fără acest fișier, conectarea la cont nu este posibilă.
    </td>
    <td>Până la închiderea browserului</td>
    </tr>
    <tr>
    <td><span class="mono">po_amintit</span></td>
    <td>
    Activează opțiunea „Ține-mă minte”, doar dacă bifezi căsuța respectivă la
    autentificare. Îți menține contul conectat chiar și după închiderea
    browserului. Se reînnoiește automat la fiecare utilizare și este asociat
    în siguranță browserului tău.
    </td>
    <td><?= ZILE_TINE_MINTE ?> de zile</td>
    </tr>
    </tbody>
    </table>
    </div>

    <p>
    Ambele cookie-uri sunt setate ca <span class="mono">HttpOnly</span> (ceea ce înseamnă
    că nu pot fi citite de scripturile din pagină) și <span class="mono">SameSite=Lax</span>
    (nu pot fi transmise prin cereri efectuate de pe alte site-uri). Când navighezi
    securizat prin HTTPS, acestea poartă și marcajul <span class="mono">Secure</span>.
    </p>

    <h2>Ce alte informații stocăm local în browser</h2>

    <p>
    Aceste elemente nu sunt fișiere cookie, ele nu sunt transmise niciodată către serverul
    nostru, ci rămân stocate exclusiv în browserul tău. Le menționăm aici pentru transparență
    totală privind datele salvate local:
    </p>

    <div class="tabel-scroll">
    <table class="tabel-doc">
    <thead>
    <tr>
    <th scope="col">Nume</th>
    <th scope="col">La ce folosește</th>
    <th scope="col">Cât timp este păstrat</th>
    </tr>
    </thead>
    <tbody>
    <tr>
    <td><span class="mono">po-theme</span></td>
    <td>Tema grafică pe care ai selectat-o (luminoasă sau întunecată).</td>
    <td>Până când o ștergi</td>
    </tr>
    <tr>
    <td><span class="mono">po-toast</span></td>
    <td>
    Un mesaj de confirmare sau notificare temporară transmis dintr-o pagină în alta
    (de exemplu, când pagina se reîncarcă după ce ai salvat ceva).
    </td>
    <td>Câteva secunde</td>
    </tr>
    <tr>
    <td><span class="mono">po-previzualizare-…</span></td>
    <td>
    Imaginea selectată pentru un eveniment, pe durata previzualizării acesteia
    într-o filă nouă.
    </td>
    <td>Până la închiderea filei</td>
    </tr>
    </tbody>
    </table>
    </div>

    <h2>Servicii externe integrate</h2>

    <p>
    Site-ul nostru încarcă fonturile grafice prin <strong>Google Fonts</strong>. Google nu
    plasează cookie-uri în acest proces, însă browserul tău îi transmite adresa ta IP
    pentru a descărca fontul. Acesta este singurul element transmis către un terț prin
    simpla deschidere a unei pagini.
    </p>

    <p>
    Dacă alegi să te autentifici folosind contul <strong>Google</strong>, vei fi redirecționat
    pentru câteva momente către pagina lor securizată de autentificare, unde se aplică
    termenii Google. Pe platforma noastră nu rămâne niciun cookie furnizat de ei.
    </p>

    <p>
    Nu folosim daruri integrate de la rețele sociale (butoane de Facebook), videoclipuri
    încorporate, hărți externe sau servicii terțe de chat, adică tocmai acele elemente
    care plasează de obicei cookie-uri terțe pe alte site-uri.
    </p>

    <h2>Cum poți șterge aceste date</h2>

    <p>
    Poți șterge cookie-urile oricând direct din setările browserului tău, la secțiunea
    „Cookie-uri și date de site”. Reține că, dacă le elimini, vei fi deconectat automat și
    site-ul va reveni la tema grafică implicită. De asemenea, poți deconecta contul apăsând
    pe <a href="/iesire.php">Deloghează-te</a>, caz în care cele două cookie-uri principale
    vor fi șterse automat.
    </p>

      <p class="doc-legatura">
        Vezi și <a href="/termeni.php">termenii și condițiile</a>
        și <a href="/confidentialitate.php">politica de confidențialitate</a>.
      </p>

    </div>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
