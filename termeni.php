<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — termenii și condițiile.
 *
 * Una dintre cele trei pagini cu putere juridică, alături de
 * confidentialitate.php și cookies.php. Toate trei sunt scrise după ACELAȘI
 * tipar: antet cu data ultimei schimbări, `.prose`, și — la coadă — celelalte
 * două, ca omul care a deschis-o pe una să le găsească pe toate.
 *
 * CINE ȚINE SITE-UL se citește din inc/config.php, prin operatorulSite(). Cât
 * timp acolo nu scrie nimic, pagina o spune pe față și trimite la contact: un
 * gol recunoscut e mai bun decât un nume închipuit într-un document pe care
 * oamenii îl citesc ca pe o promisiune.
 *
 * TEXTUL SPUNE CE FACE CODUL, nu ce ar fi frumos să facă. Fiecare cifră de aici
 * — vârsta minimă, răgazul de ștergere, cele 48 de ore pentru note — e scoasă
 * din constanta care o hotărăște cu adevărat, nu scrisă de mână. Un document
 * care se desparte de cod la prima schimbare e mai rău decât niciunul.
 */

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/validare.php';   // VARSTA_MIN, limitele de text
require_once __DIR__ . '/inc/evaluari.php';   // ORE_PENTRU_NOTE
require_once __DIR__ . '/inc/stergere.php';   // ZILE_RAGAZ_STERGERE

$titlu     = 'Termeni și condiții — PulsulOrasului.Ro';
$descriere = 'Regulile după care funcționează PulsulOrasului.Ro: ce poți publica, '
           . 'ce nu, și la ce te poți aștepta de la noi.';
$pagina    = '';

/* Se schimbă DE MÂNĂ, la fiecare îndreptare a textului de mai jos. */
$dataDocumentului = '30 august 2026';

$operator = operatorulSite();

require __DIR__ . '/inc/antet.php';
?>

<main id="main">
  <div class="wrap">
  <nav class="crumbs" aria-label="Navigare">
  <a href="/index.php">Acasă</a>
  <span aria-hidden="true">/</span>
  <span class="crumbs__current">Termeni și Condiții</span>
  </nav>
  <div class="prose">

  <p><h1>Termeni și Condiții</h1></p>
  <p><h2>1. Ce este PulsulOrașului.Ro</h2></p>

  <p>PulsulOrașului.Ro este locul în care comunitatea locală prinde viață ... un spațiu creat pentru ca oamenii dintr-un oraș să împărtășească ce pun la cale și să descopere activitățile celorlalți. Noi ne ocupăm de găzduirea și buna funcționare a platformei, iar evenimentele sunt publicate și organizate de către membrii comunității.</p>

  <p>Deoarece fiecare organizator răspunde direct de conținutul anunțului său și de desfășurarea activității la fața locului, noi nu putem garanta că un eveniment va avea loc exact așa cum a fost descris sau că este potrivit pentru tine.</p>

  <p>Utilizarea platformei este 100% gratuită. Nu vindem spații de promovare și nu percepem taxe pentru a poziționa un anunț mai sus pe pagină.</p>

  <p><h2>2. Contul tău</h2></p>

  <p>Poți explora platforma și poți citi anunțurile fără a avea un cont. Totuși, pentru a publica un eveniment, a te înscrie la o activitate, a lăsa comentarii sau a evalua alți participanți, vei avea nevoie de un cont personal.</p>

  <ul>
  <li><strong>Vârsta minimă:</strong> Contul poate fi creat de către persoanele cu vârsta de cel puțin 10 ani. Pentru utilizatorii cu vârsta sub 16 ani, este necesar acordul unui părinte sau al tutorelui legal.</li>
  <li><strong>Corectitudinea datelor:</strong> Te rugăm să folosești date reale și proprii. Crearea unui cont pe numele altcuiva dăunează comunității și poate crea neplăceri grave persoanei respective.</li>
  <li><strong>Securitatea parolei:</strong> Parola ta este confidențială. Dacă bănuiești că altcineva ți-a aflat parola, te rugăm să o schimbi imediat din setări și să ne scrii.</li>
  <li><strong>Un singur cont:</strong> Regula noastră este simplă: un singur cont pentru fiecare persoană.</li>
  </ul>

  <p><b>Ștergerea contului:</b></p>

  <p>Îți poți șterge contul oricând din secțiunea de setări. Pentru siguranța ta, ștergerea include o perioadă de grație de 30 de zile, timp în care nu se schimbă nimic și te poți răzgândi oricând, fiind suficient să te reautentifici. Detaliile despre gestionarea datelor tale după această perioadă sunt prezentate în <a href="/confidentialitate.php">Politica de Confidențialitate</a>.</p>

  <p><h2>3. Ce publici pe platformă</h2></p>

  <p>Anunțurile pe care le creezi îți aparțin în totalitate. Prin publicare, ne acorzi doar dreptul de a le afișa pe site și de a le include în e-mailul zilnic, strictul necesar pentru ca platforma să funcționeze. Nu le vom vinde, nu le vom înstrăina și nu le vom folosi în alt scop.</p>

  <p><b>Ce nu își are locul pe site:</b></p>

  <ul>
  <li>Evenimente fictive sau descrise în mod înșelător;</li>
  <li>Reclamă sau promovare comercială mascată sub formă de eveniment;</li>
  <li>Conținut sau activități care încalcă legea sau care îndeamnă la nerespectarea acesteia;</li>
  <li>Atacuri la persoană, jigniri, amenințări sau incitare la ură (indiferent dacă sunt bazate pe origine, religie, sex, orientare, dizabilitate sau orice alt criteriu);</li>
  <li>Conținut destinat exclusiv adulților sau nepotrivit pentru minorii din comunitate;</li>
  <li>Fotografii protejate de drepturi de autor pentru care nu deții dreptul de publicare, sau poze cu alte persoane încărcate fără acordul acestora;</li>
  <li>Date cu caracter personal ale altor persoane (numere de telefon, adrese etc.) publicate fără consimțământul lor;</li>
  <li>Spam, tentative de fraudă, escrocherii sau linkuri către pagini nesigure.</li>
  </ul>

  <p><b>Verificarea anunțurilor:</b></p>

  <p>Fiecare anunț este verificat de echipa noastră înainte de publicare. Te vom anunța pe e-mail cu privire la decizia luată. Dacă sunt necesare mici ajustări, îți vom explica exact ce este de modificat și vei putea retrimite anunțul fără a pierde datele introduse. În cazul în care un anunț nu respectă regulile de mai sus, acesta va fi respins, menționându-se motivul.</p>

  <p><h2>4. Când te înscrii la un eveniment</h2></p>

  <p>În momentul în care apeși butonul „Particip”, organizatorul va putea vedea numele tău complet și numărul tău de telefon, astfel încât să te poată contacta dacă apar modificări legate de eveniment. Ceilalți utilizatori vor vedea doar prenumele tău (sau numele prescurtat) și fotografia de profil.</p>

  <p>Înscrierea este un angajament amical față de ceilalți participanți. Dacă nu mai poți ajunge, te rugăm să te retragi de pe listă din timp, pentru a elibera locul altcuiva. Organizatorul are opțiunea de a te retrage de pe listă (caz în care vei primi un e-mail explicativ) și te poate marca ca absent în cazul în care ai confirmat prezența, dar nu ai mai ajuns.</p>

  <p><h2>5. Evaluări, note și păreri</h2></p>

  <p>După încheierea unui eveniment, participanții își pot acorda reciproc evaluări timp de 48 de ore. Notarea prin steluțe este complet anonimă. Impresiile și comentariile scrise sunt publice, purtând semnătura autorului, și vor fi afișate pe profilul persoanei evaluate.</p>

  <p>Evaluările trebuie să reflecte strict comportamentul și implicarea persoanei în cadrul evenimentului, nu părerea generală despre aceasta ca om. Notele acordate din frustrare, ca răzbunare sau prin înțelegeri amicale între prieteni distrug încrederea în comunitate, singurul motiv pentru care acest sistem există. Astfel de evaluări vor fi șterse imediat ce sunt identificate.</p>

  <p><h2>6. Ce putem face noi (Moderarea platformei)</h2></p>

  <p>Pentru a menține o comunitate sigură, corectă și plăcută, ne rezervăm dreptul de a:</p>

  <ul>
  <li>Solicita modificări pentru un anunț sau de a-l respinge;</li>
  <li>Șterge comentarii, evaluări sau fotografii care încalcă regulamentul;</li>
  <li>Suspenda un cont de utilizator;</li>
  <li>Elimina o dorință de pe tabla de dorințe.</li>
  </ul>

  <p><b>Transparență:</b> Te vom informa întotdeauna pe e-mail de fiecare dată când intervenim asupra unui conținut creat de tine (ștergerea unui comentariu, a unei poze, suspendarea contului sau decizii legate de anunțuri și dorințe). Îți vom comunica și motivul deciziei. Dacă consideri că a fost vorba despre o greșeală, <a href="/contact.php">scrie-ne</a> oricând, citim fiecare mesaj și răspundem cu drag.</p>

  <p><h2>7. Ce nu putem promite (Limitări de răspundere)</h2></p>

  <p>Platforma este furnizată „așa cum este”. Depunem toate eforturile pentru ca site-ul să funcționeze impecabil și fără întreruperi, însă nu putem garanta absența erorilor tehnice sau veridicitatea fiecărui anunț publicat de utilizatori.</p>

  <p>Nu purtăm răspunderea pentru desfășurarea evenimentelor și nici pentru înțelegerile private dintre participanți. Te încurajăm să abordezi întâlnirile cu persoane noi cu aceeași prudență pe care ai avea-o în viața de zi cu zi: alegeți spații publice și anunță pe cineva apropiat unde te afli.</p>

  <p>Nicio prevedere din această pagină nu îți limitează drepturile legale garantate în calitate de utilizator sau consumator.</p>

  <p><h2>8. Modificarea termenilor și condițiilor</h2></p>

  <p>Atunci când actualizăm aceste reguli, vom menționa data revizuirii în partea de sus a paginii. În cazul unor modificări majore, te vom notifica și prin e-mail sau printr-un anunț direct pe site. Dacă nu ești de acord cu noile prevederi, îți poți închide contul în orice moment.</p>

  <p><h2>9. Cadrul legal și rezolvarea neînțelegerilor</h2></p>

  <p>Acest regulament este guvernat de legea română. În cazul în care apare o neînțelegere, ne dorim să o rezolvăm mai întâi pe cale amiabilă. O simplă <a href="/contact.php">discuție prin e-mail</a> poate clarifica aproape orice situație.</p>

  <p>Dacă nu ajungem la o soluție comună, litigiul va fi soluționat de către instanțele judecătorești competente din România. În calitate de consumator, ai de asemenea posibilitatea de a utiliza platforma europeană de <a href="https://ec.europa.eu/consumers/odr" rel="noopener" target="_blank">Soluționare Online a Litigiilor (SOL)</a>.</p>

      <p class="doc-legatura">
        Vezi și <a href="/confidentialitate.php">politica de confidențialitate</a>
        și <a href="/cookies.php">politica de cookies</a>.
      </p>

      </div>
    </div>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
