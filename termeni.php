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

    <header class="page-head">
      <p class="eyebrow"><span class="pulse-dot" aria-hidden="true"></span> Documente</p>
      <h1 class="page-title">Termeni și condiții</h1>
      <p class="page-lead">
        Regulile după care merge site-ul. Le-am scris pe înțelesul tuturor, fără
        formule de avocat — dar rămân regulile după care ne purtăm, și noi, și tu.
      </p>
      <p class="doc-data">Ultima schimbare: <?= h($dataDocumentului) ?></p>
    </header>

    <div class="prose">

      <h2>Cine ține site-ul</h2>

      <?php if ($operator['are_date']): ?>
      <p>
        PulsulOrasului.Ro e ținut de <strong><?= h($operator['nume']) ?></strong><?php
        if ($operator['cui'] !== '') { echo ', CUI ' . h($operator['cui']); }
        if ($operator['reg_com'] !== '') { echo ', înregistrată la Registrul Comerțului sub ' . h($operator['reg_com']); }
        if ($operator['adresa'] !== '') { echo ', cu sediul în ' . h($operator['adresa']); }
        ?>.
        <?php if ($operator['email'] !== ''): ?>
        Ne scrii oricând la <a href="mailto:<?= h($operator['email']) ?>"><?= h($operator['email']) ?></a>
        sau prin <a href="/contact.php">formularul de contact</a>.
        <?php endif; ?>
      </p>
      <?php else: ?>
      <p>
        Datele celui care ține site-ul nu sunt încă trecute aici. Până le
        completăm, ne găsești prin <a href="/contact.php">formularul de contact</a>
        — răspundem la tot ce ne vine.
      </p>
      <?php endif; ?>

      <h2>Ce e site-ul ăsta</h2>

      <p>
        Un loc unde oamenii dintr-un oraș anunță ce pun la cale și află ce fac
        ceilalți. Noi ținem site-ul; <strong>evenimentele le scriu oamenii</strong>.
        Asta înseamnă ceva important: cine organizează ceva răspunde de ce a
        scris în anunț și de ce se întâmplă la fața locului. Noi nu suntem
        organizatorii și nu putem garanta că un eveniment are loc, că e așa cum a
        fost descris sau că e potrivit pentru tine.
      </p>

      <p>
        Folosirea site-ului e gratuită. Nu vindem locuri în pagină și nu luăm
        bani ca să punem un anunț mai sus.
      </p>

      <h2>Contul tău</h2>

      <p>
        Poți citi site-ul fără cont. Îți trebuie unul doar ca să publici un
        eveniment, să te înscrii la unul, să comentezi sau să dai note.
      </p>

      <ul>
        <li>
          Contul se face de la <strong><?= VARSTA_MIN ?> ani</strong> în sus. Dacă
          n-ai împlinit 16 ani, ai nevoie de acordul unui părinte sau al
          tutorelui ca să-ți faci cont.
        </li>
        <li>
          Datele pe care le scrii trebuie să fie ale tale și adevărate. Un cont
          pe numele altcuiva nu e o glumă, e o problemă pentru omul acela.
        </li>
        <li>
          Parola e a ta și numai a ta. Dacă bănuiești că a aflat-o cineva,
          schimb-o din <a href="/setari.php">setări</a> și scrie-ne.
        </li>
        <li>Un om, un cont.</li>
      </ul>

      <p>
        Îți poți șterge contul oricând, din <a href="/setari.php">setări</a>.
        Ștergerea are un răgaz de <strong><?= ZILE_RAGAZ_STERGERE ?> de zile</strong>,
        în care nu se schimbă nimic și te poți răzgândi — e destul să intri din
        nou în cont. Ce se întâmplă cu datele tale după aceea scrie în
        <a href="/confidentialitate.php">politica de confidențialitate</a>.
      </p>

      <h2>Ce publici</h2>

      <p>
        Anunțul rămâne al tău. Ne dai doar dreptul de a-l arăta pe site și în
        e-mailul zilnic — atât cât e nevoie ca site-ul să funcționeze. Nu-l
        vindem, nu-l dăm altcuiva și nu-l folosim în altă parte.
      </p>

      <p>Ce nu are ce căuta aici:</p>

      <ul>
        <li>evenimente care nu există, sau descrise altfel decât sunt;</li>
        <li>reclamă deghizată în eveniment;</li>
        <li>lucruri care încalcă legea, sau care cheamă la asta;</li>
        <li>
          atacuri la persoană, jigniri, amenințări, incitare la ură — după
          origine, religie, sex, orientare, dizabilitate sau orice altceva;
        </li>
        <li>conținut pentru adulți, sau nepotrivit pentru cine e minor pe site;</li>
        <li>
          poze care nu sunt ale tale și pentru care n-ai dreptul să le publici,
          sau poze cu alți oameni fără voia lor;
        </li>
        <li>datele altcuiva — număr de telefon, adresă, orice — fără acordul lui;</li>
        <li>spam, escrocherii, linkuri care duc la ele.</li>
      </ul>

      <p>
        <strong>Fiecare anunț trece pe la noi înainte să apară.</strong> Îl
        citim și îți spunem pe e-mail ce am hotărât. Dacă e ceva de îndreptat, îți
        scriem ce anume și îl poți trimite din nou — nu se pierde nimic din ce ai
        scris. Dacă un anunț nu se potrivește cu regulile de mai sus, îl
        respingem și îți spunem de ce.
      </p>

      <h2>Când te înscrii la un eveniment</h2>

      <p>
        Când apeși „Particip", organizatorul îți vede numele întreg și numărul de
        telefon, ca să te poată căuta dacă se schimbă ceva. Restul lumii vede
        doar numele prescurtat și poza.
      </p>

      <p>
        E o înțelegere între oameni, nu un contract: dacă nu mai poți ajunge,
        scoate-te de pe listă din vreme, ca locul să ajungă la altcineva.
        Organizatorul poate să te scoată de pe listă — atunci primești un e-mail
        cu motivul — și poate însemna că nu te-ai prezentat, dacă ai confirmat și
        n-ai venit.
      </p>

      <h2>Note și păreri</h2>

      <p>
        După un eveniment, cei care au fost acolo se pot nota între ei, timp de
        <strong><?= ORE_PENTRU_NOTE ?> de ore</strong>. Stelele sunt anonime.
        Părerile scrise apar semnate, pe profilul omului.
      </p>

      <p>
        O notă se dă despre cum a fost cineva la eveniment, nu despre cine e ca
        om. Notele date din supărare, ca răzbunare sau ca înțelegere între
        prieteni strică singurul lucru pentru care există — încrederea că merită
        să ieși cu cineva pe care nu-l cunoști. Le ștergem când le găsim.
      </p>

      <h2>Ce putem face noi</h2>

      <p>Ca să ținem site-ul în regulă, putem:</p>

      <ul>
        <li>să respingem un anunț, sau să cerem o îndreptare;</li>
        <li>să ștergem un comentariu, o notă sau o poză care încalcă regulile;</li>
        <li>să suspendăm un cont;</li>
        <li>să ștergem o dorință de pe tablă.</li>
      </ul>

      <p>
        <strong>Îți spunem întotdeauna, pe e-mail</strong>, când ștergem un
        comentariu sau o poză de-a ta, când îți suspendăm contul și când hotărâm
        ceva despre un anunț sau o dorință. Îți scriem și motivul, dacă a fost
        scris unul. Dacă ți se pare o greșeală,
        <a href="/contact.php">scrie-ne</a> — citim tot și răspundem.
      </p>

      <h2>Ce nu putem promite</h2>

      <p>
        Site-ul e oferit așa cum e. Ne străduim să meargă tot timpul și să fie
        corect, dar nu putem promite că nu se va opri niciodată, că nu va avea
        nicio greșeală sau că fiecare anunț scris de altcineva e adevărat.
      </p>

      <p>
        Nu răspundem pentru ce se întâmplă la un eveniment la care te-ai dus, nici
        pentru înțelegerile dintre tine și alți oameni de pe site. Mergi la
        întâlniri cu oameni pe care nu-i cunoști cu aceeași minte cu care ai face-o
        oriunde altundeva: într-un loc public, spunând cuiva unde ești.
      </p>

      <p>
        Nimic din pagina asta nu-ți ia drepturile pe care ți le dă legea ca
        utilizator sau consumator.
      </p>

      <h2>Dacă se schimbă ceva</h2>

      <p>
        Când schimbăm regulile, punem data nouă în capul paginii. Dacă e o
        schimbare care contează cu adevărat, îți dăm de veste și pe e-mail sau pe
        site. Dacă nu ești de acord cu ele, îți poți șterge contul oricând.
      </p>

      <h2>Legea și neînțelegerile</h2>

      <p>
        Se aplică legea română. Dacă apare o neînțelegere, hai să încercăm întâi
        să o lămurim scriindu-ne — de obicei se rezolvă din câteva vorbe. Dacă
        tot nu iese, se ocupă instanțele competente din România. Ca și consumator,
        poți folosi și platforma europeană de
        <a href="https://ec.europa.eu/consumers/odr" rel="noopener" target="_blank">soluționare online a litigiilor</a>.
      </p>

      <p class="doc-legatura">
        Vezi și <a href="/confidentialitate.php">politica de confidențialitate</a>
        și <a href="/cookies.php">politica de cookies</a>.
      </p>

    </div>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
