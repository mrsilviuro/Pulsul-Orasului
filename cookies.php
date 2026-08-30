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

    <header class="page-head">
      <p class="eyebrow"><span class="pulse-dot" aria-hidden="true"></span> Documente</p>
      <h1 class="page-title">Politica de cookies</h1>
      <p class="page-lead">
        Sunt două cookie-uri pe tot site-ul, amândouă necesare ca lucrurile să
        meargă. Niciunul nu e de reclamă și niciunul nu te urmărește pe alte
        site-uri.
      </p>
      <p class="doc-data">Ultima schimbare: <?= h($dataDocumentului) ?></p>
    </header>

    <div class="prose">

      <h2>Ce e un cookie</h2>

      <p>
        Un fișier mic pe care site-ul îl lasă în browserul tău ca să-și
        amintească ceva de la o pagină la alta — de pildă că ești conectat. Fără
        el, ar trebui să-ți scrii parola la fiecare apăsare.
      </p>

      <h2>De ce nu-ți cerem acordul printr-un banner</h2>

      <p>
        Fiindcă n-avem pentru ce. Legea cere acord pentru cookie-urile de
        măsurare și de reclamă — noi n-avem niciunul. Ce ne-a rămas e strict
        necesar ca site-ul să funcționeze, iar pentru acelea nu se cere acord.
      </p>

      <p>
        Un banner care cere „Accept" pentru nimic n-ar face decât să te învețe să
        apeși fără să citești, iar asta n-ajută pe nimeni.
      </p>

      <h2>Cookie-urile pe care le punem</h2>

      <div class="tabel-scroll">
        <table class="tabel-doc">
          <thead>
            <tr>
              <th scope="col">Nume</th>
              <th scope="col">La ce e</th>
              <th scope="col">Cât ține</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><span class="mono">PHPSESSID</span></td>
              <td>
                Ține minte că ești conectat, cât timp umbli prin site. Tot el
                poartă și apărarea împotriva cererilor puse la cale de pe alt
                site. Fără el nu te poți autentifica deloc.
              </td>
              <td>Până închizi browserul</td>
            </tr>
            <tr>
              <td><span class="mono">po_amintit</span></td>
              <td>
                „Ține-mă minte", și numai dacă bifezi tu căsuța la intrarea în
                cont. Te lasă conectat și după ce închizi browserul. Se
                înnoiește la fiecare folosire și e legat de browserul tău.
              </td>
              <td><?= ZILE_TINE_MINTE ?> de zile</td>
            </tr>
          </tbody>
        </table>
      </div>

      <p>
        Amândouă sunt <span class="mono">HttpOnly</span> — adică niciun script
        din pagină nu le poate citi — și <span class="mono">SameSite=Lax</span>,
        deci nu pleacă la cereri venite de pe alte site-uri. Pe HTTPS sunt
        marcate și <span class="mono">Secure</span>.
      </p>

      <h2>Ce mai ținem minte în browser</h2>

      <p>
        Astea nu sunt cookie-uri — nu pleacă niciodată spre server, rămân doar la
        tine în browser. Le scriem aici fiindcă tot despre ce-ți lasă site-ul în
        calculator e vorba:
      </p>

      <div class="tabel-scroll">
        <table class="tabel-doc">
          <thead>
            <tr>
              <th scope="col">Nume</th>
              <th scope="col">La ce e</th>
              <th scope="col">Cât ține</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><span class="mono">po-theme</span></td>
              <td>Tema pe care ai ales-o, luminoasă sau întunecată.</td>
              <td>Până o ștergi</td>
            </tr>
            <tr>
              <td><span class="mono">po-toast</span></td>
              <td>
                Un mesaj de confirmare care trebuie să treacă dintr-o pagină în
                următoarea — de pildă când pagina se reîncarcă imediat după ce ai
                apăsat ceva.
              </td>
              <td>Câteva secunde</td>
            </tr>
            <tr>
              <td><span class="mono">po-previzualizare-…</span></td>
              <td>
                Poza aleasă pentru un eveniment, cât timp îi vezi
                previzualizarea în altă filă.
              </td>
              <td>Până închizi fila</td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2>Ce vine de la alții</h2>

      <p>
        Site-ul aduce fontul de la <strong>Google Fonts</strong>. Google nu pune
        cookie-uri prin asta, dar browserul tău îi spune adresa ta IP când cere
        fișierul. E singurul lucru care pleacă spre altcineva doar fiindcă ai
        deschis o pagină.
      </p>

      <p>
        Dacă intri în cont cu <strong>Google</strong>, ești dus pentru câteva
        clipe pe pagina lor de autentificare, unde se aplică regulile Google. Pe
        site-ul nostru nu rămâne niciun cookie de-al lor.
      </p>

      <p>
        Nu avem butoane de Facebook, videoclipuri încorporate, hărți sau chat-uri
        de la alții — adică tocmai lucrurile care aduc de obicei cookie-uri
        străine pe un site.
      </p>

      <h2>Cum le ștergi</h2>

      <p>
        Din setările browserului, la „Cookie-uri și date de site". Ține minte că,
        dacă le ștergi, ieși din cont și site-ul uită tema aleasă. Poți și să
        ieși din cont apăsând <a href="/iesire.php">Deloghează-te</a> — atunci
        amândouă cookie-urile se șterg singure.
      </p>

      <p class="doc-legatura">
        Vezi și <a href="/termeni.php">termenii și condițiile</a>
        și <a href="/confidentialitate.php">politica de confidențialitate</a>.
      </p>

    </div>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
