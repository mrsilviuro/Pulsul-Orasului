<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — politica de confidențialitate.
 *
 * A doua dintre cele trei pagini cu putere juridică (vezi termeni.php pentru
 * tiparul comun).
 *
 * AICI SE SCRIE CE FACE CODUL, rând cu rând. Fiecare lucru pomenit mai jos a
 * fost căutat în cod înainte de a fi scris:
 *
 *   – ce se strânge          → INSERT-urile din api/inregistrare.php,
 *                              api/finalizare-google.php, api/contact.php
 *   – cât se ține            → ZILE_RAGAZ_STERGERE, ZILE_PASTRARE_INCERCARI,
 *                              ZILE_TINE_MINTE
 *   – cui pleacă             → inc/google.php (intrarea cu Google) și fonturile
 *                              Google din inc/antet.php; ALTCINEVA NU E
 *   – ce se șterge cu adevărat → anonimizeazaMembru() din inc/stergere.php
 *
 * DE VERIFICAT LA FIECARE FUNCȚIE NOUĂ care strânge ceva despre om: dacă
 * pagina asta nu se schimbă odată cu ea, documentul începe să mintă. Un tabel
 * nou cu date personale înseamnă un rând nou aici, în aceeași zi.
 */

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/validare.php';
require_once __DIR__ . '/inc/auth.php';        // ZILE_TINE_MINTE, ZILE_PASTRARE_INCERCARI
require_once __DIR__ . '/inc/stergere.php';    // ZILE_RAGAZ_STERGERE

$titlu     = 'Politica de confidențialitate — PulsulOrasului.Ro';
$descriere = 'Ce date strângem, de ce, cât le ținem și ce drepturi ai asupra lor. '
           . 'Fără urmărire, fără reclame, fără vânzare de date.';
$pagina    = '';

/* Se schimbă DE MÂNĂ, la fiecare îndreptare a textului de mai jos. */
$dataDocumentului = '30 august 2026';

$operator = operatorulSite();
$areGoogle = trim((string) ($config['google_client_id'] ?? '')) !== '';

require __DIR__ . '/inc/antet.php';
?>

<main id="main">
  <div class="wrap">

  <nav class="crumbs" aria-label="Navigare">
  <a href="/index.php">Acasă</a>
  <span aria-hidden="true">/</span>
  <span class="crumbs__current">Politica de confidențialitate</span>
  </nav>

    <div class="prose">

    <h1>Politica de confidențialitate</h1>
    <p class="doc-data">Ultima actualizare: <?= h($dataDocumentului) ?></p>

    <p>
    Ce informații colectăm despre tine, de ce avem nevoie de ele, cât timp le păstrăm și
    ce drepturi ai cu privire la datele tale personale. Pe scurt: colectăm cât mai puține
    date posibil, nu urmărim activitatea nimănui pe internet și nu vindem sau închiriem
    informațiile tale către terți.
    </p>

    <h2>1. Ce NU facem cu datele tale</h2>

    <p>Începem cu această secțiune, deoarece este cea mai scurtă și mai importantă pentru noi:</p>

    <ul>
    <li>
    <strong>Fără Analytics:</strong> Nu avem Google Analytics și niciun alt program
    care să-ți numere pașii sau să urmărească pe unde navighezi.
    </li>
    <li>
    <strong>Fără Pixeli de urmărire:</strong> Nu folosim pixeli de la Facebook sau de la alte rețele sociale.
    </li>
    <li>
    <strong>Fără Cookie-uri de reclamă:</strong> Nu plasăm cookie-uri pentru reclame și
    nu construim profiluri de marketing.
    </li>
    <li>
    <strong>Fără Vânzare de date:</strong> Nu vindem și nu închiriem datele niciunei persoane.
    </li>
    <li>
    <strong>Fără Decizii automate:</strong> Nu luăm hotărâri automate sau bazate pe algoritmi care să te privească în vreun fel.
    </li>
    </ul>

    <h2>2. Ce date colectăm și de ce le folosim</h2>

    <h3>Când îți creezi un cont</h3>

    <p>
    Îți solicităm <strong>numele și prenumele</strong>, <strong>adresa de e-mail</strong>,
<strong>data nașterii</strong> și <strong>sexul</strong>.
</p>

<ul>
<li>
Numele apare prescurtat pe site (de exemplu: <em>P. Ionuț</em>) pentru a-ți proteja identitatea.
</li>
<li>
Data nașterii ne este necesară pentru a verifica dacă îndeplinești limita de vârstă cerută pentru participarea la un eveniment.
</li>
<li>
Sexul este utilizat pentru evenimentele anunțate doar pentru femei sau doar pentru bărbați.
</li>
<li>
Adresa de e-mail este modul prin care intri în cont și primești notificările importante.
</li>
</ul>

<p>
<strong>Parola ta este complet securizată.</strong> Nu o cunoaștem și nu o putem vedea,
deoarece este stocată prin criptare ireversibilă (funcția bcrypt). Astfel, nimeni nu poate afla ce ai scris.
</p>

<p>
Păstrăm și <strong>adresa IP</strong> utilizată la înregistrare, pentru a opri tentativele
de abuz (cum ar fi crearea automată a zeci de conturi într-o singură oră).
</p>

<h3>Ce adaugi tu în cont, în mod opțional</h3>

<ul>
<li>
<strong>Numărul de telefon:</strong> Este vizibil doar organizatorului unui eveniment la
care te-ai înscris și echipei noastre de administrare. Îl poți lăsa gol sau îl poți șterge oricând din setări.
</li>
<li>
<strong>Fotografia de profil:</strong> În momentul salvării, re-prelucrăm imaginea pixel cu pixel,
astfel încât datele ascunse în fișier (inclusiv locația GPS unde a fost făcută poza) sunt eliminate definitiv.
</li>
<li>
<strong>Conținutul publicat:</strong> Evenimentele create, comentariile, evaluările, părerile
scrise și dorințele adăugate sunt publice, așa cum este firesc pe o platformă comunitară.
</li>
<li>
<strong>Pe cine urmărești:</strong> Dacă apeși „Urmărește" pe profilul cuiva, păstrăm
perechea (tu, el), ca să-ți putem trimite un e-mail când publică un eveniment nou.
Pe profilul lui se vede doar <em>câți</em> îl urmăresc, niciodată cine anume. Apeși din
nou același buton și legătura se șterge pe loc, cu tot cu mesajele viitoare.
</li>
</ul>

<h3>Când ne scrii prin formularul de contact</h3>

<p>
Păstrăm numele, adresa de e-mail, numărul de telefon, mesajul transmis și adresa IP —
pentru a-ți putea răspunde și pentru a preveni mesajele trimise în lanț (spam).
</p>

<h3>Ce informații se colectează automat</h3>

<ul>
<li>
<strong>Încercările de autentificare:</strong> Păstrăm adresa de e-mail și IP-ul pentru a
bloca încercările de ghicire a parolelor. Aceste înregistrări se șterg automat după 30 de zile.
</li>
<li>
<strong>Scanările de abțibilduri greșite:</strong> Păstrăm doar adresa IP pentru a preveni
tentativele de a câștiga o vânătoare încercând coduri de pe canapea. Datele se șterg automat la fiecare scanare.
</li>
<li>
<strong>Jurnalele tehnice ale serverului (logs):</strong> Menținute de furnizorul de găzduire web, așa cum le păstrează orice server pentru securitate.
</li>
</ul>

<h2>3. Pe ce temei legal prelucrăm datele</h2>

<ul>
<li>
<strong>Executarea contractului:</strong> Pentru a-ți putea oferi acces la funcționalitățile site-ului (contul, evenimentele, înscrierile și comentariile).
</li>
<li>
<strong>Interesul nostru legitim:</strong> Pentru a menține site-ul în siguranță și a opri abuzurile (limitările pe IP, moderarea, jurnalele de securitate).
</li>
<li>
<strong>Consimțământul tău:</strong> Pentru e-mailul zilnic cu ce se întâmplă în oraș și pentru opțiunile bifate de tine în setări. Îți poți retrage consimțământul oricând, printr-o simplă apăsare din setările contului.
</li>
</ul>

<h2>4. Cine altcineva are acces la date</h2>

<p>Lista partenerilor noștri este foarte scurtă și limitată strict la ce este necesar:</p>

<ul>
<li>
<strong>Găzduirea web (Hosting):</strong> Unde este stocat site-ul, baza de date și serverul securizat prin care pleacă e-mailurile.
</li>
<li>
<strong>Google Fonts:</strong> Site-ul încarcă fonturile grafice de la Google (<code>fonts.googleapis.com</code> și <code>fonts.gstatic.com</code>). Browserul tău îi transmite adresa ta IP în acest proces. Este singurul lucru care pleacă spre un terț doar pentru că ai deschis o pagină.
</li>
<li>
<strong>Google (Autentificare opțională):</strong> Doar dacă alegi să te autentifici cu contul tău Google. Atunci primim de la ei numele, adresa de e-mail și un identificator. Dacă nu folosești acel buton, Google nu află nimic despre contul tău.
</li>
<li>
<strong>Autoritățile statului:</strong> Doar în cazul în care legea ne obligă, și exclusiv în limita cerințelor legale.
</li>
</ul>

<p>
Datele tale sunt stocate pe servere din <strong>Uniunea Europeană</strong> și nu le trimitem în afara acesteia.
</p>

<h2>5. Ce informații sunt vizibile public</h2>

<p>
Pe profilul tău public pot fi văzute de către ceilalți utilizatori: prenumele (numele prescurtat), fotografia de profil, evenimentele pe care le-ai organizat, cele la care ai fost, media evaluărilor și părerile scrise primite.
</p>

<p>
<strong>Confidențialitate garantată:</strong> Adresa de e-mail, numărul de telefon, data nașterii și adresa IP nu vor fi niciodată afișate public.
</p>

<p>
Profilurile de utilizator <strong>nu sunt indexate</strong> de motoarele de căutare (sunt protejate prin fișierul <code>robots.txt</code>). Datele tale au fost oferite pentru a ieși prin oraș, nu pentru a te găsi cineva după nume pe Google.
</p>

<h2>6. Cât timp păstrăm datele tale</h2>

<ul>
<li>
<strong>Contul activ:</strong> Păstrăm datele atâta timp cât ai contul deschis. Îl poți șterge oricând.
</li>
<li>
<strong>După solicitarea de ștergere:</strong> Există o perioadă de grație de <strong>30 de zile</strong> în care nu se schimbă nimic și te poți răzgândi prin simpla reautentificare. După cele 30 de zile, numele, adresa de e-mail, telefonul, data nașterii și poza dispar definitiv, fiind șterse și de pe disc.
</li>
<li>
<strong>Istoricul rămas:</strong> Evenimentele la care ai fost prezent și comentariile scrise rămân pe site, dar anonimizate complet — în locul numelui va scrie <em>„Utilizator șters”</em>, fără poză și fără legătură spre profil. Le păstrăm deoarece de ele depinde istoricul altor persoane (cine a organizat o ieșire la care ai fost și tu are dreptul să și-o vadă în continuare).
</li>
<li>
<strong>Mesajele din formularul de contact:</strong> Le păstrăm doar cât ne este necesar pentru a-ți oferi clarificări, apoi le ștergem.
</li>
<li>
<strong>Încercările de autentificare:</strong> Se șterg automat după 30 de zile.
</li>
</ul>

<h2>7. Drepturile tale legale (GDPR)</h2>

<p>Conform Regulamentului General privind Protecția Datelor (GDPR), ai dreptul:</p>

<ul>
<li>să afli ce date avem despre tine și să primești o copie a acestora;</li>
<li>să le corectezi dacă sunt greșite (pe cele mai multe le poți schimba singur din setări);</li>
<li>să ceri ștergerea lor (sau să o faci singur, direct din setări);</li>
<li>să soliciti restricționarea modului în care le folosim;</li>
<li>să le primești într-un format structurat, ce poate fi transferat altundeva;</li>
<li>să te opui utilizării lor pe temeiul interesului nostru legitim;</li>
<li>să-ți retragi oricând consimțământul pentru e-mailuri, fără să pierzi accesul la celelalte opțiuni.</li>
</ul>

<p>
Pentru a-ți exercita oricare dintre aceste drepturi, ne poți scrie prin formularul de contact sau la adresa <a href="mailto:contact@pulsulorasului.ro">contact@pulsulorasului.ro</a>. Îți vom răspunde în cel mult o lună, de obicei mult mai repede.
</p>

<p>
Dacă consideri că datele tale nu au fost prelucrate corect, ai dreptul de a adresa o plângere către <strong>Autoritatea Națională de Supraveghere a Prelucrării Datelor cu Caracter Personal (ANSPDCP)</strong> accesând <a href="https://www.dataprotection.ro" rel="noopener" target="_blank">dataprotection.ro</a>.
</p>

<h2>8. Protecția minorilor</h2>

<p>
Contul se poate crea de la vârsta de 10 ani în sus. Pentru persoanele sub 16 ani este necesar acordul unui părinte sau al tutorelui legal. Dacă afli că un copil și-a făcut cont fără acordul tău, te rugăm să ne scrii și vom șterge contul de îndată.
</p>

<h2>9. Securitatea datelor tale</h2>

<ul>
<li>Site-ul funcționează exclusiv prin conexiune criptată și securizată (<strong>HTTPS</strong>).</li>
<li>Parolele sunt protejate prin criptare ireversibilă (<strong>bcrypt</strong>) — fiind imposibil de citit chiar și de către noi.</li>
<li>Token-urile folosite în e-mailuri sunt stocate doar ca amprentă securizată și au o durată de valabilitate limitată.</li>
<li>Fiecare acțiune de pe site este protejată împotriva cererilor nesolicitate trimise de pe alte site-uri (protecție CSRF).</li>
<li>Fotografiile încărcate sunt redesenate pixel cu pixel, astfel încât nu poate ajunge un fișier ascuns într-o poză.</li>
</ul>

      <p class="doc-legatura">
        Vezi și <a href="/termeni.php">termenii și condițiile</a>
        și <a href="/cookies.php">politica de cookies</a>.
      </p>

    </div>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
