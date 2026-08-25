<?php
declare(strict_types=1);

$titlu     = 'PulsulOrasului.Ro — Evenimente locale, sport și comunitate';
$descriere = 'Pulsul Orașului — evenimente locale, sportive, culturale și tot ce mișcă în oraș. Postat de comunitate, pentru comunitate.';
$pagina    = 'acasa';

require_once __DIR__ . '/inc/evenimente.php';

/**
 * Filtrele vin din adresă, nu din sesiune.
 *
 * Așa o pagină filtrată se poate da mai departe pe WhatsApp și se deschide la
 * fel la celălalt capăt, iar butonul „înapoi" al browserului face ce trebuie.
 * JS-ul rescrie adresa la fiecare filtrare (history.replaceState), tocmai ca
 * cele două să nu se despartă.
 *
 * Amândouă trec prin sitele lor: un oraș care nu e în config.php sau o
 * categorie care nu e în bază înseamnă „toate", nu o eroare. O adresă veche
 * trebuie să arate prima pagină, nu un ecran roșu.
 */
$orasAles      = orasulCerut($_GET['oras'] ?? null);
$categorieAleasa = categoriaCeruta($_GET['categorie'] ?? null);

/**
 * Primul teanc se scrie de PHP, nu se cere din JS după încărcare.
 *
 * Fără el, cine intră pe site ar vedea o pagină goală până pleacă și se
 * întoarce o a doua cerere — iar Google ar indexa exact golul acela. Restul
 * teancurilor vin prin api/lista-evenimente.php, la apăsare.
 */
$primaTura = evenimenteDePePrima($orasAles, $categorieAleasa);

/* ========================= TABLA CU DORINȚE ==========================
   Formularul dorinței se trimite cu JavaScript (api/dorinta.php), dar merge
   și fără: atunci e un `<form method="post">` obișnuit, care se întoarce aici.
   Aceeași înțelegere ca la pagina de așteptare — verificarea și scrierea stau
   într-un singur loc (puneODorinta din inc/dorinte.php), chemat din amândouă
   părțile. Scrise de două ori, s-ar fi despărțit la prima schimbare.

   Trebuie făcut ÎNAINTE de antet.php: acolo încep să plece anteturile HTTP,
   iar dincolo de ele nu se mai poate nici redirecționa, nici pus un cookie.
   ==================================================================== */
require_once __DIR__ . '/inc/dorinte.php';

$membruAcum   = membruCurent();
$dorintaRau   = '';
$dorintaErori = [];                        // pe câmpuri: 'oras', 'dorinta'
$dorintaScrisa = ['oras' => '', 'dorinta' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['dorinta'])) {
    $dorintaScrisa = [
        'oras'    => is_string($_POST['oras'] ?? null) ? $_POST['oras'] : '',
        'dorinta' => is_string($_POST['dorinta'] ?? null) ? $_POST['dorinta'] : '',
    ];

    if (!tokenCsrfValid(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
        $dorintaRau = 'Reîncarcă pagina și încearcă din nou.';
    } elseif ($membruAcum === null) {
        $dorintaRau = 'Trebuie să fii conectat ca să-ți pui o dorință.';
    } else {
        $rezultat = puneODorinta((int) $membruAcum['id'], $_POST);

        /**
         * Reușita se întoarce printr-o REDIRECȚIONARE, nu direct în pagină.
         *
         * Altfel, adresa ar fi rămas la un POST: o apăsare pe „reîncarcă" ar
         * fi trimis dorința a doua oară, iar omul ar fi văzut, în locul
         * mulțumirii, un „ai deja o dorință care așteaptă". Cu JavaScript
         * lucrul ăsta nu se întâmplă niciodată (cererea pleacă prin fetch),
         * dar fără el se întâmpla la prima tastă F5.
         *
         * Greșelile NU se redirecționează: acolo trebuie păstrat ce a scris
         * omul, ca să nu-l punem să scrie din nou.
         */
        if ($rezultat['ok']) {
            header('Location: /index.php?dorinta=trimisa#dorinta-formular');
            exit;
        }

        $dorintaRau   = $rezultat['mesaj'];
        $dorintaErori = $rezultat['erori'];
    }
}

/**
 * ȘTERGEREA UNEI DORINȚE, FĂRĂ JAVASCRIPT.
 *
 * „×"-ul din „Dorințele mele" e un formular adevărat (randeazaDorinteleMele
 * din inc/dorinte.php). Cu JavaScript, main.js îi ia locul și cheamă
 * api/sterge-dorinta.php; fără el, apăsarea ajunge aici.
 *
 * Aceeași funcție în amândouă drumurile — stergeDorintaOmului(), care ține și
 * regula „a lui, și încă în viață", scrisă în `WHERE`. Aici nu se întreabă
 * nimic, doar se cheamă.
 *
 * SE ÎNTOARCE PRINTR-O REDIRECȚIONARE, ca și punerea: altfel adresa ar rămâne
 * la un POST, iar o apăsare pe „reîncarcă" ar trimite ștergerea din nou. A
 * doua oară nu strică nimic (`sters_la IS NULL` n-o mai prinde), dar omul ar
 * fi văzut întrebarea browserului „retrimiți datele?" pentru nimic.
 *
 * NICIUN MESAJ DE GREȘEALĂ. Dacă id-ul nu era al lui, nu se întâmplă nimic și
 * pagina arată exact ce arăta — ca peste tot pe site, „nu există" și „nu e a
 * ta" se poartă la fel.
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['sterge_dorinta'])) {
    if ($membruAcum !== null
        && tokenCsrfValid(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) {
        stergeDorintaOmului((int) $membruAcum['id'], (int) $_POST['sterge_dorinta']);
    }

    header('Location: /index.php#dorintele-mele');
    exit;
}

/**
 * Ce se vede pe tablă și ce scrie pe buton.
 *
 * Amândouă se citesc DUPĂ trimitere, nu înainte: cine tocmai și-a pus o
 * dorință trebuie să vadă pe loc „așteaptă să fie citită", nu butonul care
 * l-ar pune s-o scrie a doua oară.
 */
$dorinteleDePeTabla = dorinteDePeTabla();

$voieLaDorinta = $membruAcum === null
    ? ['stare' => '', 'dorinte' => [], 'cate' => 0]
    : poatePuneODorinta((int) $membruAcum['id']);

require __DIR__ . '/inc/antet.php';
?>


<!-- =========================== PRIMA FEREASTRĂ ==========================
  Cât toată fereastra browserului, imediat sub bara de meniu, și lată cât ea:
  singurul loc de pe site care NU stă în `.wrap`. De aceea nici nu are un
  `<div class="wrap">` înăuntru — doar textul din mijloc e strunit la o lățime
  citibilă (`.hero__continut`), fundalul merge dintr-o margine în alta.

  Fundalul e un `<div>` gol, nu un `<img>`: se schimbă cu tema, iar tema de pe
  site se pune de mână (data-theme), nu se citește din sistem. Un `<picture>`
  cu `prefers-color-scheme` ar fi ascultat de sistem și ar fi rămas cu poza de
  zi pe cine apasă luna. Vezi „PRIMA FEREASTRĂ" din style.css.

  Săgeata de jos e o legătură adevărată spre `#main`, nu un buton de JS: merge
  și cu JavaScript-ul stins, iar derularea lină vine din `scroll-behavior`.
-->
<section class="hero" aria-labelledby="hero-titlu">
  <div class="hero__fundal" aria-hidden="true"></div>
  <div class="hero__voal" aria-hidden="true"></div>

  <div class="hero__continut">
    <p class="hero__salut">Bun venit pe</p>
    <h1 class="hero__nume" id="hero-titlu">PulsulOrasului.Ro</h1>
    <p class="hero__vorba">
      Fiecare ieșire contează. Tu vii cu ideea, noi venim cu energia. Implică-te!
    </p>

    <!--
      CELE DOUĂ FELURI DE A INTRA ÎN JOC, unul lângă altul.

      „Propune o ieșire" e pentru cine își ia răspunderea; „Pune-ți o dorință"
      e treapta de dinaintea ei, pentru cine n-ar organiza, dar ar veni la
      ceva. Aici e locul unde omul se hotărăște, deci aici stau amândouă.

      Amândouă se văd și fără cont: cine nu e înscris trebuie să afle că poate
      face lucrurile astea, nu să descopere după ce se înregistrează. Cine nu e
      conectat ajunge la pagina de intrare, dar cu drumul de întoarcere în
      adresă — după autentificare pică direct unde voia.

      Al doilea DISPARE pentru cine are deja o dorință în lucru: ce i-a rămas
      de aflat („e pe tablă până joi") scrie sub tablă, nu pe un buton.
    -->
    <div class="hero__butoane">
      <a class="btn btn--primary hero__cta" href="<?= $logat
            ? '/adauga_eveniment.php'
            : '/login.php?redirect=' . h(urlencode('/adauga_eveniment.php')) ?>">
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M12 5v14"/><path d="M5 12h14"/>
        </svg>
        <span>Propune o ieșire</span>
      </a>

      <?= butonulDorintei($logat, $voieLaDorinta['stare']) ?>
    </div>
  </div>

  <a class="hero__jos" href="#main">
    <span>Vezi ieșirile</span>
    <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M12 5v14"/><path d="m19 12-7 7-7-7"/>
    </svg>
  </a>
</section>

<!-- =============================== CONȚINUT ============================= -->
<main id="main">
  <div class="wrap">

    <!-- ========================= TABLA CU DORINȚE =======================
      Treapta de dinaintea unui eveniment: cine n-ar vrea să organizeze poate
      măcar să spună ce i-ar plăcea să se facă, iar cine caută o idee o
      găsește aici.

      BUTONUL NU E AICI: stă sus, în fereastra de bun venit, lângă „Propune o
      ieșire" (butonulDorintei din inc/dorinte.php). Aici rămâne doar vorba
      despre dorința omului, dacă are una în lucru.

      TREI STĂRI:
        - sunt dorințe → tabla se desenează
        - nu sunt      → tabla nu se desenează DELOC (o tablă goală cu „încă
                         nimeni n-a scris nimic" ar fi un anunț de pustiu chiar
                         în capul paginii), iar vorba trece în capul listei
        - formularul deschis → ia locul tablei

      ORDINEA DIN DOM NU E CEA DE PE ECRAN, ȘI E DINADINS. Formularul stă
      ÎNAINTEA tablei tocmai ca, deschis, s-o poată ascunde cu un selector de
      frați (`~`) — fără JavaScript, doar din `:target`. Vezi „TABLA CU
      DORINȚE" din style.css. Așa butonul merge și cu JS-ul stins: e o
      legătură adevărată către `#dorinta-formular`.
    ================================================================== -->
    <?php
    $tablaHtml = randeazaTablaDorinte($dorinteleDePeTabla);
    $areTabla  = $tablaHtml !== '';

    /**
     * Mulțumirea se arată după redirecționarea de mai sus (`?dorinta=trimisa`),
     * dar NUMAI dacă omul chiar are o dorință în așteptare. Fără verificarea
     * asta, adresa scrisă de mână ar fi arătat oricui un „am primit-o" pentru
     * ceva ce n-a trimis nimeni.
     */
    $areUnaNecitita = false;

    foreach ($voieLaDorinta['dorinte'] as $aMea) {
        if ((string) $aMea['stare_moderare'] === 'in_asteptare') { $areUnaNecitita = true; break; }
    }

    $dorintaGata = ($_GET['dorinta'] ?? '') === 'trimisa' && $areUnaNecitita;

    /* Formularul se desenează pentru cine mai poate pune o dorință — sau
       pentru cine tocmai a trimis una, ca să vadă mulțumirea. */
    $poateDori = $dorintaGata || ($logat && $voieLaDorinta['stare'] === 'poate');

    /* Vorba despre dorința lui, dacă are una în lucru. Poate fi '' — atunci
       nu se desenează nici casa în care ar fi stat. */
    $zonaDorinte = randeazaZonaDorinte($logat, $voieLaDorinta['stare'],
                                       $voieLaDorinta['dorinte']);
    ?>

    <?php if ($areTabla || $poateDori): ?>
    <section class="tabla<?= $areTabla ? '' : ' tabla--fara' ?>" aria-label="Tabla cu dorințe">

      <?php if ($poateDori): ?>
      <!-- Deschis de `:target` (fără JS) sau de clasa de mai jos (cu JS, și
           după o trimitere care s-a întors cu ceva de spus).

           Se desenează DOAR pentru cine chiar mai poate pune una. Cine are
           deja o dorință în lucru ar fi găsit altfel, scriind `#` în adresă,
           un formular pe care serverul n-avea cum să-l primească. -->
      <div class="tabla__cutie<?= ($dorintaRau !== '' || $dorintaGata) ? ' e-deschis' : '' ?>"
           id="dorinta-formular">

        <form class="dorinta-form" method="post" action="/index.php#dorinta-formular"
              data-dorinta-form <?= $dorintaGata ? 'hidden' : '' ?>>
          <input type="hidden" name="csrf" value="<?= h(tokenCsrf()) ?>">

          <p class="dorinta-form__titlu">Ce ți-ar plăcea să se întâmple în oraș?</p>

          <!-- Ce s-a întors de la server și nu ține de un anume câmp:
               „ai deja o dorință", „sesiunea a expirat". -->
          <p class="dorinta-form__rau" id="err-dorinta" role="alert"
             <?= $dorintaRau === '' ? 'hidden' : '' ?>><?= h($dorintaRau) ?></p>

          <div class="field-row">
            <!-- ORAȘUL. Aceeași listă ca la filtrele de mai jos și ca în
                 formularul de eveniment: `orase` din inc/config.php, prin
                 oraseDisponibile(). Un oraș nou e un rând acolo, atât. -->
            <div class="field">
              <label for="dorinta-oras">Unde? <span class="req" aria-hidden="true">*</span></label>
              <select id="dorinta-oras" name="oras" required
                      aria-describedby="err-dorinta-oras">
                <option value="" <?= $dorintaScrisa['oras'] === '' ? 'selected' : '' ?> disabled>Alege…</option>
                <?php foreach (oraseDisponibile() as $oras): ?>
                <option value="<?= h($oras) ?>" <?= $dorintaScrisa['oras'] === $oras ? 'selected' : '' ?>>
                  <?= h($oras) ?>
                </option>
                <?php endforeach; ?>
              </select>
              <p class="field__error" id="err-dorinta-oras"
                 <?= isset($dorintaErori['oras']) ? '' : 'hidden' ?>><?= h($dorintaErori['oras'] ?? '') ?></p>
            </div>
          </div>

          <div class="field">
            <label for="dorinta-text">Ce anume? <span class="req" aria-hidden="true">*</span></label>
            <textarea id="dorinta-text" name="dorinta" rows="2"
                      maxlength="<?= DORINTA_MAX ?>" data-min="<?= DORINTA_MIN ?>" required
                      aria-describedby="err-dorinta-text"
                      placeholder="Ex: un turneu de șah în parc, într-o seară de vineri"><?= h($dorintaScrisa['dorinta']) ?></textarea>
            <p class="field__hint contor-caractere" id="dorinta-numar" role="status">
              0 din <?= DORINTA_MAX ?> de caractere
            </p>
            <p class="field__error" id="err-dorinta-text"
               <?= isset($dorintaErori['dorinta']) ? '' : 'hidden' ?>><?= h($dorintaErori['dorinta'] ?? '') ?></p>
          </div>

          <!-- Ce trebuie să știe omul ÎNAINTE să apese, nu după: că nu apare
               pe loc, câte poate avea deodată, și că textul nu se mai schimbă.
               Al treilea rând spunea până acum că nici nu se mai poate șterge;
               de când se poate, e tocmai vestea bună de dat aici. -->
          <ul class="dorinta-form__reguli">
            <li>O citim înainte de a o pune pe tablă — nu apare pe loc.</li>
            <li>Stă pe tablă <?= ZILE_PE_TABLA ?> zile. Poți avea cel mult
                <?= DORINTE_DEODATA ?> deodată; după ce iese una, se face loc pentru alta.</li>
            <li>Odată publicată nu se mai poate schimba — dar o poți șterge oricând,
                din „Dorințele mele", de sub tablă.</li>
          </ul>

          <div class="dorinta-form__butoane">
            <button class="btn btn--primary btn--sm" type="submit">
              <span>Trimite dorința</span>
            </button>
            <!-- „Renunț" e tot o legătură: `#main` stinge `:target`, deci
                 închide formularul și fără JavaScript. -->
            <a class="btn btn--text" href="#main">Renunț</a>
          </div>

          <p class="dorinta-form__lege">
            Trimițând-o, ești de acord cu <a href="#">termenii și condițiile</a> site-ului.
          </p>
        </form>

        <!-- Panoul de mulțumire. Stă în pagină de la început, ascuns: și PHP
             (fără JS), și JS (după fetch) doar îl dau la iveală. Textul vine
             din MESAJ_DORINTA_TRIMISA, scris o singură dată. -->
        <div class="dorinta-gata" data-dorinta-gata <?= $dorintaGata ? '' : 'hidden' ?>>
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M20 6 9 17l-5-5"/>
          </svg>
          <p><?= h(MESAJ_DORINTA_TRIMISA) ?></p>
        </div>
      </div>
      <?php endif; ?>

      <?= $tablaHtml ?>

      <?php if ($areTabla && $zonaDorinte !== ''): ?>
      <div class="tabla__unelte"><?= $zonaDorinte ?></div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <!--
      Titlu secțiune. `h2`, nu `h1`: numele site-ului din prima fereastră e
      titlul paginii, iar pe o pagină nu stau două titluri de rang întâi.
      Butoanele s-au mutat amândouă sus, în fereastra de bun venit.

      Aici ajunge doar vorba despre dorința omului („e pe tablă până joi"), și
      numai când tabla nu se desenează — altfel are casa ei, sub tablă.
    -->
    <div class="section-head">
      <div>
        <h2 class="section-title">Ce facem zilele astea?</h2>
      </div>
      <?php if (!$areTabla): ?><?= $zonaDorinte ?><?php endif; ?>
    </div>

    <!-- ============================ FILTRELE ============================
      Un formular adevărat, cu `method="get"`, nu doar butoane pe care ascultă
      JS-ul. Fără JavaScript, oricare dintre ele duce tot unde trebuie: se
      încarcă pagina din nou, cu filtrele în adresă. Cu JavaScript, aceleași
      filtre aduc doar lista, fără să clatine pagina — vezi „PRIMA PAGINĂ" din
      main.js.

      De aceea categoriile sunt LEGĂTURI, nu butoane: o legătură merge și fără
      JS, se deschide în filă nouă cu clic pe mijloc și se poate da mai
      departe. Butoanele de dinainte nu făceau nimic.
    ============================================================== -->
    <form class="filtre" id="filtre" method="get" action="/index.php"
          data-filtre aria-label="Filtrează evenimentele">

      <!-- ORAȘUL. `.field select` e caseta de selectare a site-ului, cu
           săgeata desenată inline — aceeași ca în formularul de eveniment. -->
      <div class="filtre__oras field">
        <label class="sr-only" for="filtru-oras">Orașul</label>
        <select id="filtru-oras" name="oras" data-filtru-oras>
          <option value="">Toate orașele</option>
          <?php foreach (oraseDisponibile() as $oras): ?>
          <option value="<?= h($oras) ?>" <?= $orasAles === $oras ? 'selected' : '' ?>>
            <?= h($oras) ?>
          </option>
          <?php endforeach; ?>
        </select>

        <!-- Numai pentru cine n-are JS: cu el, schimbarea din listă filtrează
             singură, iar butonul ăsta n-are ce face. JS îl ia din pagină. -->
        <noscript>
          <button class="btn btn--primary btn--sm" type="submit">Arată</button>
        </noscript>
      </div>

      <!-- CATEGORIILE, din tabelul `categorii` — dar numai cele în care chiar
           s-a pus ceva. O categorie goală e un buton care duce la un ecran
           gol: ocupă loc, se apasă o dată și pe urmă nu mai are cine să se
           încreadă în rândul ăsta. Ordinea vine din tabel (`ordine`).

           În formularul de publicat un eveniment apar și cele goale, tocmai
           ca ele să se poată umple — acolo se cheamă categoriiEvenimente(),
           iar aceea e singura listă din care lipsesc, pentru cine nu e staff,
           categoriile ținute pentru casă (`doar_staff`, ca „FindMe"). Aici
           se filtrează după ele ca după oricare alta. -->
      <div class="chips" aria-label="Filtrează după categorie">
        <a class="chip<?= $categorieAleasa === '' ? ' is-active' : '' ?>"
           href="<?= h(adresaFiltrata($orasAles, '')) ?>"
           data-filtru-categorie=""
           <?= $categorieAleasa === '' ? 'aria-current="true"' : '' ?>>Toate</a>

        <?php foreach (categoriiCuEvenimente() as $categorie): ?>
        <a class="chip<?= $categorieAleasa === $categorie['slug'] ? ' is-active' : '' ?>"
           href="<?= h(adresaFiltrata($orasAles, (string) $categorie['slug'])) ?>"
           data-filtru-categorie="<?= h((string) $categorie['slug']) ?>"
           <?= $categorieAleasa === $categorie['slug'] ? 'aria-current="true"' : '' ?>><?= h((string) $categorie['nume']) ?></a>
        <?php endforeach; ?>
      </div>
    </form>

    <!-- ============================ EVENIMENTELE ========================
      Primul teanc e scris de PHP; următoarele vin prin fetch și se lipesc la
      coadă. Amândouă trec prin randeazaListaEvenimente(), deci arată la fel.

      `aria-live="polite"` ca cititorul de ecran să spună că au apărut altele,
      fără să întrerupă ce citea.
    ============================================================== -->
    <div class="grid" id="lista-evenimente" data-lista-evenimente aria-live="polite">
      <?= randeazaListaEvenimente($primaTura['evenimente']) ?>
    </div>

    <!-- Când nu e nimic de arătat. Se scrie și când filtrele nu potrivesc
         nimic, iar JS îl aprinde și-l stinge fără să reîncarce pagina. -->
    <p class="grid-gol" data-lista-goala <?= $primaTura['evenimente'] !== [] ? 'hidden' : '' ?>>
      Niciun eveniment pe potriva filtrelor. Încearcă alt oraș sau altă categorie.
    </p>

    <!--
      „Vezi mai mult", fără numărul celor rămase: pe prima pagină nu se știe
      câte mai sunt fără să le numeri pe toate, iar numărul acela n-ar spune
      nimic folositor. Se știe doar dacă MAI E ceva — atât cere butonul ca să
      hotărască dacă rămâne pe ecran.
    -->
    <div class="load-more" data-mai-multe <?= $primaTura['mai_sunt'] ? '' : 'hidden' ?>>
      <button class="btn btn--ghost" type="button" data-mai-multe-buton>Vezi mai mult</button>
    </div>

    <!-- CTA: contribuie -->
    <section class="cta">
      <div class="cta__glow" aria-hidden="true"></div>
      <div class="cta__content">
        <p class="eyebrow eyebrow--light">REDESCHIDEM ORAȘUL ÎMPREUNĂ</p>
        <h2>Vrei să ieși afară dar n-ai cu cine?</h2>
        <p class="cta__text">De la jocuri de weekend până la drumeții sau ieșiri cu motoarele. Adaugă activitatea ta pe site și cunoaște oameni faini din oraș.</p>
        <div class="cta__actions">
          <a class="btn btn--primary" href="/adauga_eveniment.php">Propune o ieșire</a>
          <a class="btn btn--outline" href="/despre.php">Despre Pulsul Orașului</a>
        </div>
      </div>
    </section>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
