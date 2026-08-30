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

    <header class="page-head">
      <p class="eyebrow"><span class="pulse-dot" aria-hidden="true"></span> Documente</p>
      <h1 class="page-title">Politica de confidențialitate</h1>
      <p class="page-lead">
        Ce știm despre tine, de ce, cât timp și ce poți cere să facem cu datele
        tale. Pe scurt: strângem cât mai puțin, nu urmărim pe nimeni și nu vindem
        nimic nimănui.
      </p>
      <p class="doc-data">Ultima schimbare: <?= h($dataDocumentului) ?></p>
    </header>

    <div class="prose">

      <h2>Cine răspunde de datele tale</h2>

      <?php if ($operator['are_date']): ?>
      <p>
        Operatorul datelor e <strong><?= h($operator['nume']) ?></strong><?php
        if ($operator['cui'] !== '') { echo ', CUI ' . h($operator['cui']); }
        if ($operator['adresa'] !== '') { echo ', ' . h($operator['adresa']); }
        ?>.
        <?php if ($operator['email'] !== ''): ?>
        Pentru orice ține de datele tale, scrie la
        <a href="mailto:<?= h($operator['email']) ?>"><?= h($operator['email']) ?></a>.
        <?php endif; ?>
      </p>
      <?php else: ?>
      <p>
        Datele operatorului nu sunt încă trecute aici. Până le completăm, pentru
        orice ține de datele tale ne găsești prin
        <a href="/contact.php">formularul de contact</a>.
      </p>
      <?php endif; ?>

      <h2>Ce nu facem</h2>

      <p>Începem cu asta, fiindcă e partea scurtă și cea mai importantă:</p>

      <ul>
        <li><strong>Nu avem Google Analytics</strong> și niciun alt program care să numere pe unde umbli.</li>
        <li><strong>Nu avem pixeli de Facebook</strong> sau de altă rețea.</li>
        <li><strong>Nu punem cookie-uri de reclamă</strong> și nu facem profiluri de marketing.</li>
        <li><strong>Nu vindem și nu închiriem datele nimănui.</strong></li>
        <li><strong>Nu luăm hotărâri automate despre tine</strong> care să te privească în vreun fel.</li>
      </ul>

      <h2>Ce strângem, și de ce</h2>

      <h3>Când îți faci cont</h3>

      <p>
        Îți cerem <strong>numele și prenumele</strong>, <strong>adresa de
        e-mail</strong>, <strong>data nașterii</strong> și <strong>sexul</strong>.
        Numele apare prescurtat pe site (P. Ionuț), data nașterii ne trebuie
        pentru vârsta minimă a unui eveniment, iar sexul pentru evenimentele
        anunțate doar pentru femei sau doar pentru bărbați. Adresa de e-mail e
        felul în care intri în cont și primești vești.
      </p>

      <p>
        Parola nu o știm. Se păstrează trecută printr-o funcție care nu se poate
        întoarce (bcrypt), deci nici noi nu putem afla ce ai scris.
      </p>

      <p>
        Ținem și <strong>adresa IP de la înregistrare</strong>, ca să putem opri
        pe cine face o sută de conturi într-o oră.
      </p>

      <h3>Ce adaugi tu, dacă vrei</h3>

      <ul>
        <li><strong>Numărul de telefon</strong> — îl vede doar organizatorul unui eveniment la care te-ai înscris, și staff-ul. Îl poți lăsa gol sau șterge oricând din <a href="/setari.php">setări</a>.</li>
        <li><strong>Poza de profil</strong> — o redesenăm noi la salvare, iar datele ascunse în fișier (inclusiv locul unde a fost făcută poza) se pierd atunci. Nu ajung niciodată pe site.</li>
        <li><strong>Ce scrii pe site</strong> — evenimente, comentarii, note, păreri, dorințe. Astea sunt publice, cum te aștepți să fie.</li>
      </ul>

      <h3>Când ne scrii prin formularul de contact</h3>

      <p>
        Păstrăm numele, adresa de e-mail, telefonul, mesajul și adresa IP — ca să
        îți putem răspunde și ca să oprim mesajele trimise în lanț.
      </p>

      <h3>Ce se strânge singur</h3>

      <ul>
        <li>
          <strong>Încercările de intrare în cont</strong> (adresa de e-mail și
          IP-ul) — ca să blocăm ghicitul parolelor. Rândurile mai vechi de
          <?= ZILE_PASTRARE_INCERCARI ?> de zile se șterg singure.
        </li>
        <li>
          <strong>Scanările de abțibild care n-au nimerit nimic</strong> (doar
          IP-ul) — ca să nu se poată câștiga o vânătoare încercând coduri de pe
          canapea. Se șterg singure, la scanare.
        </li>
        <li>
          <strong>Jurnalele serverului</strong>, ținute de găzduire, cum le ține
          orice server.
        </li>
      </ul>

      <h2>Pe ce temei</h2>

      <ul>
        <li><strong>Ca să-ți putem da site-ul</strong> (contract): contul, evenimentele, înscrierile, comentariile.</li>
        <li><strong>Interesul nostru legitim</strong>: să ținem site-ul în picioare și să oprim abuzurile — limitele pe IP, moderarea, jurnalele.</li>
        <li><strong>Acordul tău</strong>: e-mailul zilnic cu ce se întâmplă în oraș și celelalte două bife din setări. Ți-l poți lua înapoi oricând, dintr-o apăsare.</li>
      </ul>

      <h2>Cine altcineva vede ceva</h2>

      <p>Lista e scurtă, și asta e tot:</p>

      <ul>
        <li>
          <strong>Găzduirea</strong>, unde stă site-ul și baza de date, și
          <strong>serverul de e-mail</strong> prin care pleacă mesajele.
        </li>
        <li>
          <strong>Google Fonts.</strong> Site-ul aduce fontul de la Google
          (<span class="mono">fonts.googleapis.com</span> și
          <span class="mono">fonts.gstatic.com</span>), iar browserul tău îi
          spune, prin asta, adresa ta IP. E singurul lucru care pleacă spre
          altcineva doar fiindcă ai deschis o pagină.
        </li>
        <?php if ($areGoogle): ?>
        <li>
          <strong>Google</strong>, dacă alegi să intri cu contul tău de Google.
          Atunci primim de la ei numele, adresa de e-mail și un identificator.
          Dacă nu apeși butonul acela, Google nu află nimic despre contul tău.
        </li>
        <?php endif; ?>
        <li>
          <strong>Autoritățile</strong>, dacă legea ne obligă. Doar atunci, și
          doar atât cât cere legea.
        </li>
      </ul>

      <p>
        Datele stau pe servere din Uniunea Europeană. Nu le trimitem în afara ei.
      </p>

      <h2>Ce vede lumea despre tine</h2>

      <p>
        Pe profilul tău public se văd: numele prescurtat, poza, evenimentele pe
        care le-ai organizat, cele la care ai fost, media notelor și părerile
        scrise pe care le-ai primit. <strong>Nu se văd</strong>: adresa de
        e-mail, telefonul, data nașterii și adresa IP.
      </p>

      <p>
        Profilurile nu sunt indexate de motoarele de căutare — le-am ținut
        deoparte în <span class="mono">robots.txt</span>. Datele tale le-ai dat
        ca să ieși prin oraș, nu ca să te găsească cineva după nume în Google.
      </p>

      <h2>Cât ținem datele</h2>

      <ul>
        <li><strong>Contul</strong> — cât timp îl ai. Îl poți șterge oricând.</li>
        <li>
          <strong>După ce ceri ștergerea</strong> — mai sunt
          <?= ZILE_RAGAZ_STERGERE ?> de zile în care nu se schimbă nimic și te
          poți răzgândi intrând în cont. După ele, numele, adresa de e-mail,
          telefonul, data nașterii și poza <strong>dispar definitiv</strong>, iar
          poza se șterge și de pe disc.
        </li>
        <li>
          <strong>Ce rămâne după ștergere</strong> — evenimentele la care ai fost
          și comentariile rămân pe site, dar <strong>fără tine în ele</strong>: în
          locul numelui scrie „Utilizator șters", fără poză și fără legătură spre
          profil. Le păstrăm fiindcă de ele atârnă istoricul altor oameni — cine
          a organizat o ieșire la care ai fost și tu are dreptul să și-o vadă mai
          departe.
        </li>
        <li><strong>Mesajele de contact</strong> — cât ne trebuie ca să ne lămurim, apoi le ștergem.</li>
        <li><strong>Încercările de intrare</strong> — <?= ZILE_PASTRARE_INCERCARI ?> de zile.</li>
      </ul>

      <h2>Drepturile tale</h2>

      <p>
        După Regulamentul general privind protecția datelor (GDPR), ai dreptul:
      </p>

      <ul>
        <li>să afli ce date avem despre tine și să primești o copie;</li>
        <li>să le îndrepți, dacă sunt greșite — pe cele mai multe le poți schimba singur din <a href="/setari.php">setări</a>;</li>
        <li>să ceri ștergerea — sau s-o faci singur, tot din setări;</li>
        <li>să ceri să nu le mai folosim într-un anume fel;</li>
        <li>să le primești într-o formă pe care o poți duce altundeva;</li>
        <li>să te opui folosirii lor pe temeiul interesului legitim;</li>
        <li>să-ți iei înapoi acordul pentru e-mailuri, oricând, fără să pierzi nimic altceva.</li>
      </ul>

      <p>
        Scrie-ne prin <a href="/contact.php">formularul de contact</a><?php
        if ($operator['email'] !== '') {
            echo ' sau la <a href="mailto:' . h($operator['email']) . '">'
               . h($operator['email']) . '</a>';
        } ?>. Răspundem în cel mult o lună, de obicei mult mai repede.
      </p>

      <p>
        Dacă ți se pare că nu ne purtăm cum trebuie cu datele tale, te poți plânge
        la <strong>Autoritatea Națională de Supraveghere a Prelucrării Datelor cu
        Caracter Personal</strong> (ANSPDCP) —
        <a href="https://www.dataprotection.ro" rel="noopener" target="_blank">dataprotection.ro</a>.
      </p>

      <h2>Copiii</h2>

      <p>
        Contul se face de la <?= VARSTA_MIN ?> ani în sus. Sub 16 ani e nevoie de
        acordul unui părinte sau al tutorelui. Dacă afli că un copil și-a făcut
        cont fără acordul tău, <a href="/contact.php">scrie-ne</a> și ștergem
        contul.
      </p>

      <h2>Cum ținem datele în siguranță</h2>

      <ul>
        <li>Site-ul merge numai pe conexiune criptată (HTTPS).</li>
        <li>Parolele sunt trecute prin bcrypt — nici noi nu le putem citi.</li>
        <li>Token-urile din e-mailuri se păstrează tot ca amprentă, niciodată în clar, și expiră.</li>
        <li>Fiecare acțiune care schimbă ceva e apărată împotriva cererilor puse la cale de pe alt site.</li>
        <li>Pozele primite sunt redesenate pixel cu pixel, deci nu poate ajunge un fișier ascuns într-o poză.</li>
      </ul>

      <p class="doc-legatura">
        Vezi și <a href="/termeni.php">termenii și condițiile</a>
        și <a href="/cookies.php">politica de cookies</a>.
      </p>

    </div>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
