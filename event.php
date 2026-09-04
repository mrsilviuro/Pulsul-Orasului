<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — pagina unui eveniment.
 *
 * Adresa: `/eveniment/<slugul-evenimentului>`. Slugul, nu id-ul: se poate citi
 * la telefon, spune despre ce e vorba și nu dă în vileag câte evenimente are
 * site-ul.
 *
 * Adresa frumoasă e o RESCRIERE din .htaccess: pentru fișierul ăsta cererea
 * arată tot ca `event.php?slug=…`, exact ca înainte. De aceea forma veche
 * merge mai departe fără nicio schimbare de cod — și de aceea, tot aici, e
 * trimisă cu 301 spre cea nouă: un anunț trebuie să aibă o singură adresă
 * adevărată, nu două care arată același lucru.
 */

require_once __DIR__ . '/inc/evenimente.php';
require_once __DIR__ . '/inc/urmariri.php';
require_once __DIR__ . '/inc/afisare-eveniment.php';
require_once __DIR__ . '/inc/interese.php';
require_once __DIR__ . '/inc/comentarii.php';
require_once __DIR__ . '/inc/evaluari.php';
require_once __DIR__ . '/inc/coduri-qr.php';

$slug = trim((string) ($_GET['slug'] ?? ''));

/**
 * Un eveniment publicat se vede de oricine, fără cont.
 *
 * A fost o vreme închisă și pagina asta, ca profilurile. Dar un anunț public
 * are altă treabă decât un profil: e făcut ca să fie dat mai departe, pus pe
 * Facebook, trimis pe WhatsApp. O ușă la intrare l-ar fi oprit tocmai pe cel
 * căruia i s-a trimis linkul, și l-ar fi ținut și în afara căutărilor Google.
 *
 * Restricționată rămâne INTERACȚIUNEA, nu privitul: butoanele de mai jos duc
 * spre login la apăsare, iar api/interes.php cere cont oricum.
 *
 * $membru poate fi null de aici încolo. Tot ce urmează îl citește cu grijă,
 * iar $membruId e 0 pentru cine nu e conectat — un id peste care nu nimerește
 * niciun rând din bază.
 */
$membru   = membruCurent();
$membruId = (int) ($membru['id'] ?? 0);

$eveniment = evenimentDupaSlug($slug);

/**
 * Un slug care nu duce nicăieri și un eveniment pe care n-ai voie să-l vezi
 * sfârșesc la fel: pe prima pagină.
 *
 * Același răspuns pentru amândouă, dinadins. Dacă „nu există" ar arăta altfel
 * decât „nu ai voie", oricine ar putea afla, ghicind sluguri, ce evenimente
 * așteaptă la moderare.
 */
$eStaff = esteStaff($membru);

if ($eveniment === null || !poateVedeaEvenimentul($eveniment, $membruId, $eStaff)) {
    header('Location: /index.php');
    exit;
}

/**
 * DE LA ADRESA VECHE LA CEA NOUĂ, o dată pentru totdeauna.
 *
 * Linkurile trimise pe WhatsApp înainte de schimbare arată `event.php?slug=x`
 * și trebuie să meargă mai departe — dar nu ca a doua adresă a aceluiași
 * lucru. Google numără două adrese cu același conținut drept conținut repetat
 * și alege singur pe care s-o arate; 301 îi spune care e cea adevărată, și mută
 * spre ea și ce a strâns cea veche.
 *
 * ABIA AICI, DUPĂ CE SE ȘTIE CĂ ANUNȚUL EXISTĂ ȘI SE POATE VEDEA. Pus mai sus,
 * ar fi trimis cu 301 și un slug scris aiurea — adică o redirecționare
 * PERMANENTĂ, pe care browserul o ține minte, către o adresă care dă 404. Așa,
 * ce nu duce nicăieri sfârșește tot pe prima pagină, ca înainte.
 *
 * SE ÎNTREABĂ DE `REQUEST_URI`, nu de `$_GET`: după rescrierea din .htaccess,
 * pentru PHP cele două cereri arată la fel. Doar adresa cerută de browser le
 * deosebește.
 */
$caleCeruta = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

if (str_ends_with($caleCeruta, '/event.php')) {
    // Tot ce mai era în adresă, în afară de `slug` — el intră acum în cale.
    parse_str((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY), $restul);
    unset($restul['slug']);

    header('Location: ' . urlEveniment((string) $eveniment['slug'])
        . ($restul === [] ? '' : '?' . http_build_query($restul)), true, 301);
    exit;
}

/**
 * DACĂ E O VÂNĂTOARE ȘI I-A TRECUT TERMENUL, se încheie chiar acum.
 *
 * Prima pagină le închide pe toate, la fiecare încărcare a listei
 * (evenimenteDePePrima); rândul ăsta o închide pe cea la care se uită omul,
 * fără să aștepte ca cineva să treacă pe acasă. Cine vine de pe un abțibild
 * intră de-a dreptul aici, iar pagina trebuie să fie adevărată din prima
 * clipă — nu la primul vizitator al primei pagini.
 *
 * Se cere ANUME evenimentul ăsta, nu toate: e-o singură pagină, nu un teanc.
 * Regula însăși stă în `WHERE`-ul funcției, într-un singur loc — aici nu se
 * întreabă nimic, doar se cheamă.
 *
 * Când chiar s-a închis, se schimbă și rândul din mână: tot ce urmează în
 * pagină se socotește din el, iar o pagină care ar arăta starea de acum o
 * clipă și-ar contrazice singură caseta de dedesubt, unde numărătoarea e deja
 * la zero.
 */
if (incheieVanatorileTrecute((int) $eveniment['id']) === 1) {
    $eveniment['stare_moderare'] = 'incheiat';
}

$eOrganizatorul = $membruId > 0 && (int) $eveniment['membru_id'] === $membruId;
/**
 * „Publicat" înseamnă aprobat SAU încheiat: două stări, o singură purtare față
 * de lume. Un eveniment încheiat nu se ascunde — a avut loc, iar pagina lui
 * rămâne de citit și de trimis mai departe.
 */
$ePublicat      = evenimentPublicat($eveniment);
$eAnulat        = $eveniment['stare_moderare'] === 'anulat';

/**
 * Discuția e deschisă și la un anunț ANULAT — vezi discutiaEDeschisa() din
 * inc/comentarii.php. E singurul lucru care rămâne deschis acolo: nu poți
 * spune „vin" la ceva ce nu se mai ține, dar poți spune „ce păcat".
 */
$discutieDeschisa = discutiaEDeschisa($eveniment);

/**
 * Ziua lui a trecut.
 *
 * Nu-l ascunde și nu-l închide: un eveniment de acum două luni rămâne o
 * pagină bună de citit și de trimis mai departe. Se schimbă doar ce se poate
 * face pe ea — nimeni nu se mai poate înscrie la ceva ce s-a terminat.
 *
 * Aceeași regulă ca la limita de un eveniment activ, prin aceeași funcție.
 */
$eIncheiat = evenimentIncheiat($eveniment);

/**
 * A început deja.
 *
 * De aici încolo listele îngheață: nimeni nu mai intră, nimeni nu mai iese, iar
 * organizatorul nu mai poate scoate pe nimeni. Până acum regula pornea abia la
 * încheiere, dar între început și încheiere e chiar evenimentul — tocmai
 * răstimpul în care o retragere n-ar mai însemna nimic, fiindcă omul e (sau nu
 * e) deja acolo. Iar cine se șterge de pe listă în timpul evenimentului scapă
 * de „Nu s-a prezentat".
 *
 * Un eveniment încheiat a început, dinadins — și e scris în cod, nu presupus:
 * evenimentAInceput() răspunde „da" pentru orice eveniment încheiat, oricât ar
 * arăta ceasul. Presupunerea de dinainte (butonul se dă doar după început,
 * deci încheiat vine mereu după) se strica dacă starea se punea de mână, din
 * phpMyAdmin: ieșea o pagină care spunea „s-a încheiat" și dedesubt întreba,
 * cu butoane vii, „Mergi la acest eveniment?".
 */
$aInceput = evenimentAInceput($eveniment);

/**
 * Poate organizatorul să-l încheie chiar acum?
 *
 * Întrebarea o pune poateFiIncheiat(), din inc/evenimente.php — aici rămâne
 * doar „e al lui?". Era scrisă de mână chiar aici și îi lipsea un termen: că
 * anunțul mai e în picioare. Pe unul ANULAT, butonul „Încheie evenimentul"
 * stătea mai departe acolo, viu — deși un eveniment anulat a încetat deja,
 * altfel, iar cele două stări nu se pun una peste alta. Apăsat, api-ul răspundea
 * cu 409 și bine făcea; dar un buton care nu poate să facă nimic n-are ce căuta
 * pe ecran.
 */
$poateIncheia = $eOrganizatorul && poateFiIncheiat($eveniment);

/**
 * Poate să-l anuleze chiar de pe pagina asta?
 *
 * Doar organizatorul, și doar cât ține ceasul — o oră după ora de început
 * (poateFiAnulat din inc/evenimente.php, aceeași funcție care hotărăște și
 * butonul din formularul de editare, și fapta din API).
 *
 * Butonul stă sub caseta de interes fiindcă acolo se ia hotărârea: omul se uită
 * la câți au spus că vin și, dacă sunt doi din doisprezece, anulează. Ținut
 * numai în formularul de editare, era la două pagini distanță de cifra care îl
 * face să-l apese.
 */
$poateAnula = $eOrganizatorul && poateFiAnulat($eveniment);

/* ------------------------ „Mergi la acest eveniment?" ------------------ */

/**
 * Cine s-a adunat în jurul evenimentului, și unde stă omul care se uită.
 *
 * Se citește o dată, aici, și se folosește în toată secțiunea de mai jos.
 * Numerele astea sunt o veste, nu o rezervare: între încărcarea paginii și
 * apăsare pot intra alții, de aceea locurile se numără din nou în
 * api/interes.php, în clipa apăsării.
 */
$evenimentId  = (int) $eveniment['id'];
$numarInterese = numaraInterese($evenimentId);
$stareaMea     = interesulMeu($evenimentId, $membruId);
$maiSuntLocuri = maiSuntLocuri($eveniment, $numarInterese['participant']);

/**
 * Numărul de telefon i se cere doar cui nu l-a dat încă — și niciodată
 * organizatorului: al lui e, n-are cui să și-l dea.
 */
$eLogat         = $membru !== null;
$imiCereTelefon = $eLogat && !$eOrganizatorul && telefonulMembrului($membruId) === '';

/**
 * Cine poate da pe cineva jos de pe lista de participanți.
 *
 * Organizatorul, fiindcă locurile sunt ale lui, și staff-ul, ca la comentarii.
 * Nu și la un eveniment neaprobat sau încheiat: acolo n-are ce curăța nimeni —
 * la primul nu s-a înscris nimeni, iar listele celui de-al doilea sunt istorie,
 * nu o socoteală deschisă.
 *
 * Aici se hotărăște doar dacă se DESENEAZĂ butoanele. Regula adevărată e în
 * api/exclude-participant.php, care întreabă din nou tot ce se întreabă aici.
 */
$poateScoateParticipanti = ($eOrganizatorul || $eStaff) && $ePublicat && !$aInceput;

/**
 * Cine vede numerele de telefon din lista de participanți: organizatorul și
 * staff-ul, nimeni altcineva — nici măcar omul în dreptul numărului lui.
 *
 * Regula e a lui poateVedeaTelefoanele() din inc/interese.php, fiindcă o cer
 * și cele două puncte de intrare care redesenează listele. Aici se hotărăște
 * doar ce se trimite la desenat; pentru ceilalți, coloana nici nu se cere din
 * bază.
 */
$vedeTelefoanele = poateVedeaTelefoanele($eveniment, $membru);

/**
 * E o vânătoare de abțibilde („FindMe")?
 *
 * De întrebarea asta atârnă TREI lucruri pe pagină, și toate trei înseamnă
 * „nu se desenează":
 *
 *   1. caseta „Ce zici, te interesează?" — în locul ei stă caseta vânătorii;
 *   2. tabul „Participă" și tabul „Interesați" — cu tot cu panourile lor;
 *   3. rândul de chipuri de sub caseta de interes, care pleacă odată cu ea.
 *
 * La o vânătoare nu se strânge nimeni: fiecare caută singur prin oraș, iar
 * singurul lucru care contează e cine ajunge primul la abțibild. Rămân
 * comentariile — acolo lumea se întreabă unde n-a căutat încă.
 *
 * Steagul vine cu rândul evenimentului (`categorie_joc_qr`), din
 * `categorii.joc_qr` — niciodată din numele sau slugul categoriei. Vezi
 * esteJocQr() din inc/coduri-qr.php.
 */
$eVanatoare = esteJocQr($eveniment);

/**
 * Abțibildul ei — de care atârnă dacă se arată numărătoarea inversă sau
 * câștigătorul. Se cere din bază doar când chiar e o vânătoare.
 */
$codulVanatorii = $eVanatoare ? codQrAlEvenimentului($evenimentId) : null;

/**
 * De ce nu se poate înscrie omul care se uită — dacă nu se poate.
 *
 * Ușa închisă de organizator, sau un eveniment care nu e pentru el (doar
 * pentru femei, doar pentru bărbați). Aceeași funcție care hotărăște și
 * refuzul din api/interes.php: aici stinge butonul, acolo oprește cererea.
 *
 * Cine e DEJA pe listă nu e oprit de nimic: butonul lui e cel de retragere, iar
 * din el trebuie să poată ieși oricând. Un eveniment poate fi schimbat în „doar
 * pentru femei" după ce s-au înscris bărbați — ei nu au de ce să rămână prinși
 * acolo.
 */
$blocajParticipare = $stareaMea === 'participant'
    ? ''
    : motivBlocajParticipare($eveniment, $membru);

/* --------------------------- Notele de la final ----------------------- */

/**
 * Stelele din dreptul fiecărui participant, la un eveniment încheiat.
 *
 * Se socotește O DATĂ, aici, pentru toată lista: cine poate nota, ce note a dat
 * deja, pe cine a însemnat ca neprezentat. Întrebate rând cu rând, ar fi fost
 * treizeci de cereri cu același răspuns.
 *
 * `null` cât evenimentul n-a trecut: atunci nu se desenează nicio stea, iar
 * randeazaOm() nu are ce să caute în el.
 */
$contextEvaluare = null;

if ($eIncheiat && $ePublicat) {
    /**
     * NOTELE SE ÎNCHID LA DOUĂ ZILE de la sfârșitul evenimentului
     * (terminulNotelor din inc/evaluari.php). După termen nu se mai adaugă și
     * nu se mai schimbă nimic — nici stele, nici păreri scrise, nici „Nu s-a
     * prezentat".
     *
     * Motivul se scrie AICI, gata făcut, și se duce mai departe în context:
     * inc/interese.php desenează stelele, dar n-are voie să ceară
     * inc/evaluari.php pentru o constantă — cele două fișiere s-ar cere unul
     * pe altul, în cerc. Aceeași scutire ca la `absenti`, câteva rânduri mai
     * jos.
     */
    $noteleAuTrecut = auTrecutNotele($eveniment);

    $contextEvaluare = [
        'eu'            => $membruId,
        'slug'          => (string) $eveniment['slug'],
        'pot_nota'      => potNotaLaEveniment($eveniment, $membruId),
        /**
         * A FOST ȘI EL ACOLO?
         *
         * Deosebită de `pot_nota`, care se stinge și la termenul notelor. De
         * ea atârnă dacă în dreptul oamenilor se desenează stele STINSE sau
         * nimic: cine n-a fost pe listă n-are ce judeca, iar cinci stele
         * înghețate în dreptul fiecărui om nu-i spun nimic — sunt un buton
         * care nu se apasă, pus acolo pentru un drept pe care nu-l are.
         *
         * Cine A FOST, în schimb, le vede și după termen: acolo scrie nota pe
         * care a dat-o el, iar aceea merită să rămână la vedere.
         */
        'eu_participant' => $stareaMea === 'participant',
        'inchise'       => $noteleAuTrecut,
        'motiv_stins'   => $noteleAuTrecut
            ? 'S-au închis notele — au trecut ' . ORE_PENTRU_NOTE
              . ' de ore de la sfârșitul evenimentului.'
            : 'Poți nota doar dacă ai fost și tu pe lista de participanți.',
        'termen'        => terminulNotelor($eveniment),
        'e_organizator' => $eOrganizatorul,
        'notele_mele'   => noteleMeleLaEveniment($evenimentId, $membruId),
        // Pentru toată lumea, nu doar pentru organizator: „Neprezentat" se
        // vede de oricine deschide tabul, iar în dreptul acelui om nu se mai
        // desenează stele pentru nimeni.
        'absenti'       => absentiiEvenimentului($evenimentId),
    ];
}

/* ------------------------------ Discuția ------------------------------ */

/**
 * Comentariile, toate deodată.
 *
 * Toate, până la ultimul: ascunsul e treaba lui main.js, care lasă la vedere
 * primele COMENTARII_DEODATA și le arată pe celelalte la apăsarea butonului,
 * fără să mai întrebe serverul. Aici discuția e scurtă — zeci de rânduri, nu
 * mii — iar în schimbul câtorva kiloocteți în plus se câștigă un buton care
 * răspunde pe loc, o pagină care se poate căuta cu Ctrl+F întreagă și
 * comentarii pe care le vede și Google.
 *
 * Se scrie sub un eveniment publicat, fie el și încheiat. Listele de
 * participanți se închid odată cu evenimentul, discuția nu: acolo se închide o
 * socoteală, aici oamenii spun cum a fost — și asta se întâmplă mai ales după.
 */
$randuriComentarii = comentariileEvenimentului($evenimentId, $membruId);
$fireComentarii    = grupeazaComentarii($randuriComentarii);
$cateComentarii    = 0;

foreach ($randuriComentarii as $randComentariu) {
    // Cele golite nu se numără: pe ecran sunt o piatră de mormânt, nu o vorbă.
    $cateComentarii += (int) $randComentariu['sters'] === 1 ? 0 : 1;
}

/**
 * „poate_scrie" nu întreabă dacă omul e conectat, ci dacă locul e deschis.
 *
 * Butonul „Răspunde" se vede și de vizitator, ca și cel de apreciere: apăsat,
 * îl duce la intrare, cu întoarcere fix aici. Ascuns, i-ar fi arătat o
 * discuție la care pare că n-are cum să ia parte.
 */
$contextComentarii = [
    'organizator_id' => (int) $eveniment['membru_id'],
    'membru_id'      => $membruId,
    'e_staff'        => $eStaff,
    'poate_scrie'    => $discutieDeschisa,
    'nume'           => numeleComentatorilor($randuriComentarii),
];

/* --------------------- Ar putea să te intereseze ---------------------- */

/**
 * Câteva evenimente la întâmplare, pentru coada paginii.
 *
 * Numai ce N-A ÎNCEPUT încă, din orice oraș — vezi evenimenteSugerate(). Fără
 * niciunul, secțiunea nu se scrie deloc: pagina se oprește la comentarii și
 * sare la subsol. Un titlu cu nimic sub el e mai rău decât lipsa lui.
 *
 * Se citește și la un eveniment neaprobat sau anulat, pe care îl vede doar
 * organizatorul: și acolo capătul paginii poate să ducă undeva.
 */
$sugerate = evenimenteSugerate($evenimentId);

/* --------------------------- ce se afișează --------------------------- */

// Coperta, orele, numele organizatorului — toate se pregătesc în
// evenimentDinBaza(), din inc/afisare-eveniment.php. Aici rămâne doar ce ține
// de pagină: titlul din bara browserului și dacă se lasă indexată.
$titlu     = $eveniment['titlu'] . ' — PulsulOrasului.Ro';
$descriere = inceputDeText((string) $eveniment['descriere'], 155);

// Cât timp nu e aprobat, n-are ce căuta în motoarele de căutare.
$noindex   = !$ePublicat;

/* --------------------- Cartonașul de pe WhatsApp ---------------------- */

/**
 * Ce se vede când cineva pune linkul evenimentului într-o conversație.
 *
 * Titlul și descrierea le aveam deja. Lipsea poza: fără `og:image`, WhatsApp
 * arată doar două rânduri de text, iar un anunț fără coperta lui nu spune
 * mare lucru.
 *
 * Adresa pozei trebuie să fie ÎNTREAGĂ. WhatsApp și Facebook nu se uită la
 * pagină din browserul omului: o cer ele, de pe alt server, iar o cale de
 * forma „assets/img/…" n-are acolo față de ce să se socotească.
 */
$ogTitlu = (string) $eveniment['titlu'];
$ogUrl   = urlIntreg(urlEveniment((string) $eveniment['slug']));
$ogTip   = 'article';

// Fără etichete: în bază intră text curat, dar dacă cineva a scris „<b>" cu
// mâna lui, n-are ce căuta în cartonaș.
$ogDescriere = inceputDeText(strip_tags((string) $eveniment['descriere']), 180);

/**
 * Coperta lui, iar dacă n-are, imaginea categoriei.
 *
 * urlImagineCategorie() se uită și pe disc, nu doar în bază: coloana
 * `categorii.imagine_default` există de mult, fișierele se urcă de mână și unele
 * lipsesc încă (vezi roadmapul din CLAUDE.md). O adresă care duce la 404 e mai
 * rea decât niciuna — WhatsApp ar încerca s-o ia, n-ar găsi-o, și ar arăta un
 * cartonaș ciuntit în loc de unul curat.
 */
$ogImagine = urlCoperta($eveniment['coperta'] ?? null);

if ($ogImagine === '') {
    $ogImagine = urlImagineCategorie($eveniment['imagine_default'] ?? null);
}

$ogImagine = urlIntreg($ogImagine);

require __DIR__ . '/inc/antet.php';
?>


<!-- bara de progres a citirii -->
<div class="read-progress" id="read-progress" aria-hidden="true"><span></span></div>

<main id="main">
  <div class="wrap">

    <!-- Firimituri -->
    <nav class="crumbs" aria-label="Navigare">
      <a href="/index.php">Acasă</a>
      <span aria-hidden="true">/</span>
      <span class="crumbs__current"><?= h(inceputDeText($eveniment['titlu'], 60)) ?></span>
    </nav>

    <article class="post">

      <?php
        /**
         * Tot ce se vede din datele evenimentului — antet, copertă, caseta cu
         * detalii, descrierea — se desenează în inc/afisare-eveniment.php.
         *
         * Acolo, și nu aici, fiindcă aceeași bucată desenează și
         * previzualizarea din formular. Dacă ar fi scrisă în două locuri,
         * previzualizarea ar rămâne în urmă la prima schimbare, și tocmai ea
         * trebuie să arate exact ca pagina adevărată.
         *
         * Ce ține de pagina asta se dă din afară: banda cu starea anunțului
         * (o vede doar organizatorul, ceilalți nu deschid pagina deloc) și
         * butonul „Editează".
         */
        $banda = null;

        if ($eAnulat) {
            /**
             * Anulat, dar la vedere: pagina se deschide de oricine, ca una
             * încheiată. Cine intră de pe un mesaj primit acum trei zile
             * trebuie să afle pe loc ce s-a întâmplat — nu să dea de un „nu
             * există" și să se întrebe dacă a greșit el ziua.
             *
             * Motivul merge alături, întocmai cum l-a scris organizatorul: e
             * același text care a plecat prin e-mail spre oamenii înscriși, iar
             * un rezumat făcut de noi ar fi spus altceva decât mesajul primit.
             */
            $banda = [
                'fel'   => 'anulat',
                'text'  => 'Acest eveniment a fost anulat de organizator.',
                'motiv' => (string) ($eveniment['motiv_anulare'] ?? ''),
            ];
        } elseif (!$ePublicat) {
            $banda = $eveniment['stare_moderare'] === 'respins'
                ? ['fel' => 'respins',   'text' => 'Anunțul nu a fost aprobat de moderatori.']
                : ['fel' => 'asteptare', 'text' => 'Se așteaptă aprobarea din partea unui moderator.'];
        } elseif ($eIncheiat) {
            /**
             * Trecut, nu greșit. De aceea banda e cenușie, nu galbenă și nici
             * roșie: culorile alea sunt pentru ce n-a mers bine, iar aici n-a
             * greșit nimeni — doar a trecut ziua.
             */
            $banda = [
                'fel'  => 'incheiat',
                'text' => 'Acest eveniment s-a încheiat.',
            ];
        }

        /**
         * Butonul „Editează" dispare de îndată ce evenimentul a început — și,
         * firește, la anulare și la încheiere.
         *
         * Ce era de îndreptat se îndrepta înainte: după ora de start oamenii
         * sunt deja pe drum, iar o schimbare de loc sau de oră le-ar ajunge
         * sub ochi prea târziu. Ce rămâne de făcut e chiar aici, pe pagină:
         * „Anulează evenimentul" încă o oră și „Încheie evenimentul".
         *
         * Aceeași întrebare o pun și adauga_eveniment.php, și
         * api/eveniment.php — vezi poateFiEditat() din inc/evenimente.php.
         */
        $poateEdita = $eOrganizatorul && poateFiEditat($eveniment);

        /**
         * „Remake" — pe dos față de „Editează": el apare abia după ce s-a
         * terminat sau s-a anulat.
         *
         * Alergarea de duminică se face și duminica viitoare, iar cea căzută
         * din cauza ploii se mută pe altă zi. Butonul duce la formularul de
         * publicare cu tot ce scrisese omul o dată, gata completat — numai
         * „Când o să aibă loc?" rămâne gol. Anunțul vechi nu se atinge: se
         * naște unul nou, care trece pe la moderare ca oricare altul.
         */
        $poateReface = $eOrganizatorul && poateFiRefacut($eveniment);

        /**
         * PIUNEZA — singurul buton din rândul ăsta care nu e al
         * organizatorului, ci al OMULUI CASEI, oricine ar fi scris anunțul.
         *
         * Stă aici, lângă celelalte, fiindcă e tot o hotărâre despre anunțul
         * ăsta, luată de pe pagina lui, cu ochii pe el. Într-o listă de
         * administrare ar fi însemnat să fixezi din titlu — iar ce merită
         * capul primei pagini se vede citind, nu dintr-un rând de tabel.
         *
         * Se vede și la un anunț încheiat sau anulat: piuneza nu se stinge
         * singură niciodată, deci trebuie să existe de unde s-o iei.
         */
        $poateFixa = $eStaff && poateFiFixat($eveniment);
        $eFixat    = esteFixat($eveniment);

        /**
         * Butoanele organizatorului, sus, lângă numele lui.
         *
         * „Editează" și „Încheie evenimentul" sunt amândouă ale lui și se
         * exclud rareori: cel care poate încheia (a început) poate de obicei
         * și edita. Stau împreună, unde omul se uită întâi, nu una sus și una
         * pe la mijlocul paginii, printre iconițele de distribuire — alea sunt
         * pentru oricine.
         */
        /**
         * Butonul „Urmărește" al organizatorului.
         *
         * Se scrie AICI, unde se știe cine citește pagina, și se dă mai departe
         * prin tabloul de afișare, care îl pune în același rând cu butoanele de
         * mai jos — vezi lămurirea din inc/afisare-eveniment.php.
         * Omului i se dau doar cele trei lucruri de care are nevoie butonul;
         * restul rândului lui n-are ce căuta în plus prin pagină.
         */
        $dateDeAfisat = evenimentDinBaza($eveniment);
        $dateDeAfisat['urmarire'] = randeazaButonUrmarire($membru, [
            'id'        => (int) $eveniment['membru_id'],
            'stare'     => $eveniment['org_stare'] ?? 'activ',
            'permalink' => (string) ($eveniment['org_permalink'] ?? ''),
        ]);

        afiseazaEveniment($dateDeAfisat, $banda,
          ($poateEdita || $poateIncheia || $poateReface || $poateFixa)
            ? function () use ($eveniment, $poateEdita, $poateIncheia, $poateReface,
                              $poateFixa, $eFixat) {
            ?>
              <?php if ($poateFixa): ?>
              <!--
                Butonul comută, și SPUNE ce e acum: apăsat, scrie „Fixat" și e
                aprins; neapăsat, scrie „Fixează" și e stins. Un buton care
                arată la fel în amândouă stările l-ar fi pus pe omul casei să
                deschidă prima pagină ca să afle ce a făcut.

                `data-fixat` e starea de acum, citită de JS ca să trimită
                OPUSUL ei. Se trimite ce se vrea, nu „schimbă": vezi
                api/fixeaza-eveniment.php.
              -->
              <button class="btn btn--ghost btn--sm post__fixeaza<?= $eFixat ? ' is-on' : '' ?>"
                      type="button" data-fixeaza
                      data-slug="<?= h((string) $eveniment['slug']) ?>"
                      data-csrf="<?= h(tokenCsrf()) ?>"
                      data-fixat="<?= $eFixat ? '1' : '0' ?>"
                      aria-pressed="<?= $eFixat ? 'true' : 'false' ?>"
                      title="Ține anunțul primul pe prima pagină">
                <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M9 3h6l-1 6 4 3v2H6v-2l4-3-1-6Z"/><path d="M12 14v7"/>
                </svg>
                <span data-fixeaza-vorba><?= $eFixat ? 'Fixat' : 'Fixează' ?></span>
              </button>
              <?php endif; ?>

              <?php if ($poateEdita): ?>
              <!-- Doar pentru cel care l-a scris. Slugul spune formularului ce
                   eveniment să încarce; acolo se verifică din nou al cui e. -->
              <a class="btn btn--ghost btn--sm post__editeaza"
                 href="<?= h(urlEditareEveniment((string) $eveniment['slug'])) ?>">
                <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M4 20h4l10-10a2.4 2.4 0 0 0-3.4-3.4L4.6 16.6z"/>
                  <path d="m14.2 7.4 2.4 2.4"/>
                </svg>
                <span>Editează</span>
              </a>
              <?php endif; ?>

              <?php if ($poateReface): ?>
              <!--
                Tot pentru cel care l-a scris, dar la celălalt capăt al vieții
                unui anunț: după ce s-a încheiat sau s-a anulat. Slugul spune
                formularului din ce să copieze; acolo se verifică din nou al
                cui e și dacă chiar s-a terminat.
              -->
              <a class="btn btn--ghost btn--sm post__remake"
                 href="<?= h(urlRefacereEveniment((string) $eveniment['slug'])) ?>">
                <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M20 11a8 8 0 1 0-.9 4.6"/><path d="M20 5v6h-6"/>
                </svg>
                <span>Remake</span>
              </a>
              <?php endif; ?>

              <?php if ($poateIncheia): ?>
              <!--
                „Încheie evenimentul" — doar pentru cel care l-a pus la cale,
                doar cât mai e ceva de încheiat, și doar DUPĂ ce a început. Un
                eveniment se termină oricum singur a doua zi după data lui;
                butonul ăsta e pentru când se termină mai devreme: s-au ocupat
                locurile, s-a stricat vremea la jumătate, s-a strâns lumea și
                gata.
              -->
              <button class="btn btn--ghost btn--sm post__incheie" type="button" id="ev-incheie"
                      data-slug="<?= h((string) $eveniment['slug']) ?>"
                      data-csrf="<?= h(tokenCsrf()) ?>"
                      aria-expanded="false" aria-controls="ev-incheie-sigur">
                <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                  <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
                </svg>
                <span>Încheie evenimentul</span>
              </button>

              <!--
                Confirmarea, desenată de noi, ca la anulare: o fereastră a
                browserului arată altfel pe Windows, pe Android și pe iPhone.
                Se deschide chiar sub buton, ca întrebarea să fie lângă mâna
                care a apăsat.

                Nu e roșie — încheierea nu e o pierdere, e un lucru firesc la
                capătul unui eveniment. Dar tot se cere o apăsare în plus: nu
                se poate lua înapoi, iar butonul stă lângă altele pe care
                oricine le apasă din curiozitate.
              -->
              <div class="incheiere-confirm" id="ev-incheie-sigur" hidden>
                <p class="incheiere-confirm__titlu">
                  <strong>Sigur vrei să încheii evenimentul?</strong>
                </p>
                <p class="incheiere-confirm__text">
                  Evenimentul rămâne disponibil pentru cititori, dar nu se vor mai
                  putea face înscrieri noi. Astfel, eliberezi un loc pentru un
                  eveniment nou. Reține că această modificare este definitivă.
                </p>

                <div class="incheiere-confirm__actiuni">
                  <button class="btn btn--primary btn--sm" type="button" id="ev-incheie-da">Da, încheie</button>
                  <button class="btn btn--ghost btn--sm" type="button" id="ev-incheie-nu">Renunță</button>
                </div>
              </div>
              <?php endif; ?>
            <?php
        } : null);
      ?>

      <?php if ($ePublicat): ?>
      <!-- ========================== DISTRIBUIRE ===========================
        Trei iconițe, între detalii și „Mergi la acest eveniment?". Aceleași
        desene ca cele scoase odinioară de lângă organizator — acolo erau
        lipite de numele cuiva, aici sunt la locul lor: după ce omul a citit
        despre ce e vorba și înainte să hotărască dacă vine.

        Numai la un eveniment publicat: n-are rost să dai mai departe un anunț
        pe care nu-l poate deschide nimeni.

        Adresa se scrie ÎNTREAGĂ, prin urlIntreg() (adică url_site din config).
        Facebook și WhatsApp primesc un link, nu o cale — „/eveniment/…" singur
        n-ar duce nicăieri de pe telefonul altcuiva.
      ================================================================== -->
      <?php
        $adresaEveniment = urlIntreg(urlEveniment((string) $eveniment['slug']));

        // Textul care pleacă pe WhatsApp și în clipboard. Scurt dinadins:
        // pe WhatsApp intră în căsuța de scris, iar omul îl termină cum vrea.
        $textDistribuire = 'Uite ce eveniment am găsit pe Pulsul Orașului: ' . $eveniment['titlu'];

        // Mesajul gata scris din caseta de mai jos. Altul decât cel de sus,
        // fiindcă acela pleacă lipit de un buton care spune deja unde duce, pe
        // când ăsta e trimis de om, cu mâna lui, într-o discuție.
        $mesajDeCopiat = 'Salut! Am găsit ceva fain pe PulsulOrașului.Ro '
                       . 'și m-am gândit că ți-ar plăcea și ție: ' . $adresaEveniment;
      ?>

      <!-- ===================== CASETA DE SPRIJIN ==========================
        DE CE E DEOSEBITĂ DE RÂNDUL DE ICONIȚE DE MAI JOS, deși spun același
        lucru: fiindcă blocantele de reclame ascund rândul acela. AdGuard și
        celelalte au filtre gata scrise pentru butoanele de distribuire — le
        recunosc după numele claselor și după adresele către facebook.com și
        wa.me. Nu e nimic de reparat acolo: un link către sharer-ul Facebook
        ARATĂ exact ca un link către sharer-ul Facebook, oricum l-am scrie.

        Caseta asta trece pe lângă toate filtrele fiindcă nu seamănă cu ele:
        n-are în ea nicio adresă către o rețea, iar numele clasei nu poartă
        niciun cuvânt după care se caută. E text și un buton de copiat.

        DE ACEEA STĂ SINGURĂ, nu în același înveliș cu iconițele și fără
        vreo legătură de vecinătate cu ele: o regulă care ascunde rândul de
        mai jos n-are cum s-o ia și pe ea cu el.
      ================================================================== -->
      <section class="sprijin" aria-labelledby="sprijin-titlu">
        <p class="sprijin__titlu" id="sprijin-titlu">PulsulOrașului trăiește prin oameni ca tine!</p>

        <p class="sprijin__text">Suntem un proiect non-profit creat din drag pentru
          comunitate, iar fiecare distribuire ne ajută enorm să mergem mai departe.
          Trimite acest eveniment prietenilor tăi pe WhatsApp sau pe rețelele sociale!</p>

        <p class="sprijin__indemn">Am pregătit noi mesajul, tu doar copiază-l:</p>

        <!--
          Mesajul stă într-un `<p>` obișnuit, nu într-un buton, ca să se poată
          SELECTA CU MÂNA. Fără JavaScript, copierea nu are cum să meargă — și
          atunci singura cale rămasă e ca omul să treacă peste text și să-l ia
          singur. Într-un buton, textul nu se lasă selectat.

          Butonul de lângă e unealta adevărată: se ajunge la el cu tastatura și
          îl citesc cititoarele de ecran. Apăsarea pe text e doar o scurtătură
          în plus, pentru cine dă cu degetul direct pe vorbe — de aceea poartă
          același `data-copiaza`, iar main.js le prinde pe amândouă cu o
          singură regulă.
        -->
        <div class="sprijin__mesaj">
          <p class="sprijin__vorba" data-copiaza="<?= h($mesajDeCopiat) ?>"><?= h($mesajDeCopiat) ?></p>

          <button class="btn btn--primary btn--sm sprijin__copiaza" type="button"
                  data-copiaza="<?= h($mesajDeCopiat) ?>"
                  data-copiat="Am copiat mesajul. Dă-l mai departe!">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <rect x="9" y="9" width="11" height="11" rx="2"/>
              <path d="M5 15V5a2 2 0 0 1 2-2h8"/>
            </svg>
            <span>Copiază</span>
          </button>
        </div>
      </section>

      <div class="post__trimite" role="group" aria-label="Distribuie evenimentul">
        <a class="icon-btn" target="_blank" rel="noopener noreferrer"
           href="https://www.facebook.com/sharer/sharer.php?u=<?= h(urlencode($adresaEveniment)) ?>"
           aria-label="Distribuie pe Facebook" title="Distribuie pe Facebook">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8.5V7a1.5 1.5 0 0 1 1.5-1.5H17V3h-2.2A3.8 3.8 0 0 0 11 6.8v1.7H9V11h2v10h3V11h2.2l.4-2.5H14Z" fill="currentColor" stroke="none"/></svg>
        </a>

        <a class="icon-btn" target="_blank" rel="noopener noreferrer"
           href="https://wa.me/?text=<?= h(urlencode($textDistribuire . ' ' . $adresaEveniment)) ?>"
           aria-label="Trimite pe WhatsApp" title="Trimite pe WhatsApp">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-11.9 7L4 20l1.6-4A8 8 0 1 1 20 11.5Z"/><path d="M9 9.5c.4 2 1.6 3.2 3.6 3.6l.9-1.2 1.7.8-.4 1.4c-2.9.3-5.8-2.6-5.5-5.5l1.4-.4.8 1.7z" fill="currentColor" stroke="none"/></svg>
        </a>

        <!--
          Copierea o face JS. Textul stă într-un atribut, nu se lipește în JS
          din bucăți: aici e escapat de h(), iar un titlu cu ghilimele sau cu
          „&" nu poate strica nimic.
        -->
        <button class="icon-btn" type="button" id="copiaza-link"
                data-copiaza="<?= h($textDistribuire . ' ' . $adresaEveniment) ?>"
                aria-label="Copiază linkul" title="Copiază linkul">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3A4 4 0 0 0 13 5.3l-1.4 1.4"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3A4 4 0 0 0 11 18.7l1.4-1.4"/></svg>
        </button>
      </div>

      <?php if ($eVanatoare): ?>
      <!-- ========================= VÂNĂTOAREA ============================
        La un eveniment „FindMe", în locul casetei de interes stă caseta
        abțibildului: ori numărătoarea inversă până la termen, ori câștigătorul.

        DE CE ÎN LOCUL EI, nu pe lângă. „Mă interesează / Voi participa" sunt
        două butoane despre a te strânge undeva la o oră anume. La o vânătoare
        nu se strânge nimeni: fiecare caută singur, iar singurul lucru care
        contează e cine ajunge primul la abțibild. O listă de participanți la
        așa ceva ar fi fost o listă de oameni care au apăsat un buton.

        FĂRĂ $aInceput, spre deosebire de caseta obișnuită: aici „a început"
        n-are înțeles. Ora din anunț e clipa în care vânătoarea SE ÎNCHIDE, iar
        caseta are ce spune și după ea („Nu l-a găsit nimeni"). Regula stă
        într-un singur loc, randeazaCasetaFindMe().
      ============================================================== -->
      <?= randeazaCasetaFindMe($eveniment, $codulVanatorii) ?>

      <?php elseif (!$aInceput): ?>
      <!-- =========================== PARTICIPARE ==========================
        Numai la un eveniment publicat CARE N-A ÎNCEPUT ÎNCĂ.

        După ora de început, caseta dispare cu totul — nu se stinge, pleacă. O
        casetă mare care întreabă „Mergi la acest eveniment?" deasupra unui
        eveniment care se petrece chiar acum, sau care s-a terminat, e o
        întrebare fără rost pusă în cel mai vizibil loc al paginii. Cine vrea
        să vadă cine a fost are taburile de mai jos, unde scrie „Au participat".

        De aici încolo nu se mai întreabă nici dacă evenimentul s-a încheiat:
        între „a început" și „s-a încheiat" nu mai e nimic de desenat aici.
        Oprirea adevărată rămâne oricum pe server, în api/interes.php, prin
        aceeași evenimentAInceput().

        Cât e în așteptare, respins sau anulat, pagina se deschide doar pentru
        organizator sau pentru staff — și n-are rost o listă de participanți la
        ceva ce nu se vede pe site.

        `aria-pressed` spune în ce stare e omul chiar acum; JS îl schimbă după
        fiecare apăsare, iar la reîncărcare vine de la server. Retragerea n-are
        buton al ei: apăsarea pe starea în care ești deja o stinge.
      ================================================================== -->
      <!--
        Tokenul CSRF se scrie DOAR pentru cine e conectat: un vizitator n-are
        ce face cu el, fiindcă butoanele îl duc la login înainte de orice
        cerere. (Sesiunea tot se deschide — membruCurent() o cere pe fiecare
        pagină, ca peste tot pe site — dar un token în plus în HTML, pentru
        cineva care nu-l poate folosi, n-are de ce să existe.)
      -->
      <section class="rsvp" id="rsvp" aria-labelledby="rsvp-title"
               data-slug="<?= h((string) $eveniment['slug']) ?>"
               <?= $eLogat ? 'data-csrf="' . h(tokenCsrf()) . '"' : '' ?>>
        <div class="rsvp__head">
          <h2 id="rsvp-title">Ce zici, te interesează?</h2>
          <p>Spune-le și celorlalți. O să apari în lista de mai jos.</p>
        </div>

        <div class="rsvp__actions">
          <button class="rsvp__btn rsvp__btn--interested" type="button"
                  id="btn-interested" data-rsvp="interesat"
                  aria-pressed="<?= $stareaMea === 'interesat' ? 'true' : 'false' ?>">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path d="m12 3.8 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 10l5.9-.9L12 3.8Z"/>
            </svg>
            <span class="rsvp__label">Mă interesează</span>
            <span class="rsvp__count" data-count-for="interesat"><?= (int) $numarInterese['interesat'] ?></span>
          </button>

          <!--
            Butonul de participare se stinge în două cazuri: s-au ocupat toate
            locurile, sau omul nu are ce căuta pe listă — i s-a închis ușa, ori
            evenimentul e pentru celălalt sex.

            Amândouă, doar pentru cine nu e deja înăuntru: cel care e pe listă
            trebuie să se poată retrage oricând.

            Stins de-a binelea, nu doar „duce la un refuz": până acum caseta de
            confirmare se deschidea, omul apăsa „Da, particip" și abia atunci
            afla că nu se poate. Oprirea adevărată rămâne pe server, în
            api/interes.php, prin aceeași motivBlocajParticipare().
          -->
          <button class="rsvp__btn rsvp__btn--going" type="button"
                  id="btn-going" data-rsvp="participant"
                  aria-pressed="<?= $stareaMea === 'participant' ? 'true' : 'false' ?>"
                  <?= $blocajParticipare !== '' ? 'title="' . h($blocajParticipare) . '"' : '' ?>
                  <?= ($blocajParticipare !== '' || (!$maiSuntLocuri && $stareaMea !== 'participant')) ? 'disabled' : '' ?>>
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
            </svg>
            <span class="rsvp__label">Voi participa</span>
            <span class="rsvp__count" data-count-for="participant"><?= (int) $numarInterese['participant'] ?></span>
          </button>
        </div>

        <?php if ($blocajParticipare !== ''): ?>
        <!--
          Un buton stins fără nicio vorbă lasă omul să creadă că s-a stricat
          ceva. Motivul se scrie sub el, o singură dată — `title` de pe buton e
          pentru mouse, iar pe telefon nu se vede niciodată.
        -->
        <p class="rsvp__blocaj"><?= h($blocajParticipare) ?></p>
        <?php endif; ?>

        <?php if (!$maiSuntLocuri && $stareaMea !== 'participant'): ?>
        <p class="rsvp__plin">S-au ocupat toate locurile.</p>
        <?php endif; ?>

        <!-- ------------------- confirmarea participării -------------------
          „Mă interesează" e o însemnare; „voi participa" e o hotărâre care
          dă datele omului mai departe. De aceea treapta asta există, și de
          aceea spune pe față ce se întâmplă înainte, nu după.

          Se deschide din JS și se verifică din nou pe server: fără
          `confirmat`, api/interes.php nu scrie nimic.
        -->
        <?php if ($eLogat): ?>
        <div class="rsvp__confirm" id="rsvp-confirm" hidden>
          <p class="rsvp__confirm-titlu"><strong>Confirmi participarea?</strong></p>

          <p class="rsvp__confirm-text">
            Organizatorul o să-ți vadă numele întreg și numărul de telefon, ca să
            te poată suna sau scrie pe WhatsApp. Dacă nu te găsește, s-ar putea
            să te scoată de pe listă, ca locul să ajungă la altcineva.
          </p>

          <!--
            Se deschide în FILĂ NOUĂ, ca toate legăturile spre termeni pornite
            din mijlocul unui formular: omul e la un pas de a apăsa „Da,
            particip", iar o pagină care i-ar lua locul l-ar trimite înapoi la
            început. Pe restul site-ului (subsol) se deschide normal.
          -->
          <p class="rsvp__confirm-text">
            Dacă mergi mai departe, înseamnă că ai citit și ești de acord cu
            <a href="/termeni.php" target="_blank" rel="noopener">Termenii și condițiile</a> site-ului.
          </p>

          <?php if ($imiCereTelefon): ?>
          <!--
            Numărul se cere o singură dată: la confirmare se salvează în cont,
            ca la setări, cu aceeași verificare. A doua oară nu se mai întreabă.
          -->
          <div class="field">
            <label for="rsvp-telefon">Numărul tău de telefon <span class="req" aria-hidden="true">*</span></label>
            <input type="tel" id="rsvp-telefon" name="telefon" inputmode="tel"
                   autocomplete="tel" maxlength="20" placeholder="0722334455"
                   aria-describedby="err-rsvp-telefon rsvp-telefon-hint">
            <p class="field__hint" id="rsvp-telefon-hint">
              Se salvează automat în contul tău, ca să nu-l mai scrii din nou data viitoare.
              Îl poți schimba oricând din <a href="/setari.php">setări</a>.
            </p>
            <p class="field__error" id="err-rsvp-telefon" hidden></p>
          </div>
          <?php endif; ?>

          <div class="rsvp__confirm-actiuni">
            <button class="btn btn--primary" type="button" id="rsvp-confirm-da">Da, particip</button>
            <button class="btn btn--ghost" type="button" id="rsvp-confirm-nu">Renunță</button>
          </div>
        </div>
        <?php endif; ?>

        <!--
          Chipurile și vorba de dedesubt se desenează într-un singur loc,
          randeazaChipuri() din inc/interese.php — de acolo vin și la
          încărcarea paginii, și după fiecare apăsare, prin api/interes.php.
          Scrise în două locuri, ar fi început să difere de la prima corectură.
        -->
        <div class="rsvp__people" id="rsvp-people">
          <?= randeazaChipuri($evenimentId) ?>
        </div>
      </section>
      <?php endif; /* $eVanatoare / !$aInceput */ ?>
      <?php endif; /* $ePublicat */ ?>

      <?php if ($poateAnula): ?>
      <!-- ======================= ANULAREA ==============================
        Imediat sub caseta de interes, fiindcă asta e clipa în care se apasă:
        organizatorul se uită la câți au spus că vin, vede doi din
        doisprezece, și hotărăște. Până acum butonul era doar în formularul de
        editare, la două pagini distanță de cifra care îl face să-l apese.

        AFARĂ din blocul lui $ePublicat, dinadins: se anulează și un anunț care
        încă așteaptă aprobarea, iar acela n-are casetă de interes sub care să
        stea. Pe formularul de editare butonul îi era oricum dat; aici trebuie
        să fie același lucru, nu unul pe jumătate.

        DOAR PENTRU ORGANIZATOR, și doar cât ține ceasul — o oră după ora de
        început (poateFiAnulat). Nu e ascunsă cu CSS: pentru oricine altcineva
        blocul nici nu se scrie în pagină. Iar api/anuleaza-eveniment.php pune
        amândouă întrebările din nou, fiindcă o cerere poate veni de oriunde.

        Același HTML ca în formularul de editare, din același loc — vezi
        randeazaZonaAnulare() din inc/afisare-eveniment.php.
      ============================================================== -->
      <?= randeazaZonaAnulare($eveniment, tokenCsrf()) ?>
      <?php endif; ?>

      <?php if ($eStaff && poateFiModerat($eveniment)
                 && $eveniment['stare_moderare'] !== 'aprobat'): ?>
      <!-- ========================= MODERAREA ==============================
        Numai pentru staff, și numai la un anunț care mai poate fi hotărât —
        nici anulat, nici încheiat (vezi STARI_MODERABILE din inc/evenimente.php).

        ȘI NICI APROBAT. Acolo treaba e făcută: anunțul e pe site, iar blocul
        n-ar mai fi decât un teanc de butoane peste care omul de casă trece de
        fiecare dată când deschide o pagină, ca oricine altcineva. Cine chiar
        vrea să ia aprobarea înapoi o face din phpMyAdmin — e o răzgândire
        rară, nu o unealtă de zi cu zi.

        Verificarea stă AICI, nu în poateFiModerat(): aceea e regula pe care o
        cere și api/modereaza-eveniment.php, iar acolo „aprobat" trebuie să
        rămână o stare din care se poate ieși. Ce se schimbă e doar ce se
        DESENEAZĂ.

        NU E ASCUNSĂ CU CSS: pentru cine nu e om de casă, blocul ăsta nici nu se
        scrie în pagină. Un buton ascuns rămâne un buton — se găsește din consolă
        și se apasă. Iar api/modereaza-eveniment.php întreabă din nou cine cere,
        fiindcă o cerere poate veni și de altundeva decât de pe pagina asta.

        Stă între anunț și discuție: hotărârea se ia după ce ai citit ce a scris
        omul, dar înainte să te pierzi prin comentarii — care oricum nu au ce
        căuta în socoteala asta.
      ============================================================== -->
      <section class="moderare" aria-labelledby="moderare-title"
               data-moderare
               data-slug="<?= h((string) $eveniment['slug']) ?>"
               data-csrf="<?= h(tokenCsrf()) ?>">

        <div class="moderare__cap">
          <h2 class="moderare__titlu" id="moderare-title">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 3 4 6.5v5c0 4.6 3.2 8.4 8 9.5 4.8-1.1 8-4.9 8-9.5v-5L12 3Z"/>
            </svg>
            Moderare
          </h2>

          <p class="moderare__stare">
            Acum:
            <strong><?= h(match ((string) $eveniment['stare_moderare']) {
                'aprobat' => 'aprobat, se vede pe site',
                'respins' => 'respins, îl vede doar organizatorul',
                default   => 'în așteptare',
            }) ?></strong>
          </p>
        </div>

        <p class="moderare__lamurire">
          Organizatorul primește un e-mail cu ce ai hotărât. Te poți răzgândi
          oricând: un anunț respins din greșeală poate fi aprobat la loc, iar
          unul aprobat prea repede poate fi oprit.
        </p>

        <div class="moderare__butoane">
          <!--
            Butonul stării de acum lipsește: n-ai ce aproba de două ori. Serverul
            răspunde oricum cu „e deja aprobat", dar mai bine nu-l pui pe om să
            apese ca să afle.
          -->
          <?php if ((string) $eveniment['stare_moderare'] !== 'aprobat'): ?>
          <button class="btn btn--primary btn--sm" type="button" data-modereaza="aprobat">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path d="m5 12.5 4.5 4.5L19 7.5"/>
            </svg>
            <span>Aprobă</span>
          </button>
          <?php endif; ?>

          <?php if ((string) $eveniment['stare_moderare'] !== 'respins'): ?>
          <!--
            „Respinge" deschide caseta de mai jos în loc să trimită pe loc:
            acolo se scrie motivul, care pleacă în e-mailul organizatorului.
            Aprobarea n-are ce explica, deci pleacă dintr-o apăsare.
          -->
          <button class="btn btn--ghost btn--sm moderare__respinge" type="button"
                  data-moderare-deschide aria-expanded="false" aria-controls="moderare-motiv-caseta">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M6 6l12 12"/><path d="M18 6 6 18"/>
            </svg>
            <span>Respinge</span>
          </button>
          <?php endif; ?>
        </div>

        <?php if ((string) $eveniment['stare_moderare'] !== 'respins'): ?>
        <!-- ==================== MOTIVUL RESPINGERII ====================
          Se deschide la apăsarea pe „Respinge". Motivul e OPȚIONAL: uneori
          anunțul e limpede greșit și n-ai ce scrie. Când lipsește, e-mailul
          spune asta pe față și îl trimite pe om la noi — vezi
          inc/email.php, emailModerareAnunt().
        ============================================================== -->
        <div class="moderare__caseta" id="moderare-motiv-caseta" data-moderare-caseta hidden>
          <label class="moderare__eticheta" for="moderare-motiv">
            Ce nu e în regulă? <span class="field__optional">(opțional)</span>
          </label>

          <textarea class="moderare__motiv" id="moderare-motiv" rows="3"
                    maxlength="<?= MOTIV_RESPINGERE_MAX ?>"
                    placeholder="Ce ar trebui schimbat ca să poată fi publicat?"
                    data-moderare-motiv aria-describedby="moderare-motiv-hint"></textarea>

          <p class="field__hint" id="moderare-motiv-hint">
            Textul ăsta pleacă întreg în e-mailul organizatorului. Dacă îl lași
            gol, mesajul o să spună limpede că nu s-a scris niciun motiv.
          </p>

          <!-- ================== EDITARE NECESARĂ ======================
            BIFATĂ DIN START, fiindcă e drumul obișnuit. Un anunț bun, dar cu o
            oră lipsă sau cu locul scris pe jumătate, nu merită respins: e mai
            bine să i se spună omului ce n-a mers și să poată drege, fără s-o ia
            de la capăt.

            Cu bifa pusă, anunțul RĂMÂNE în așteptare și pleacă doar vestea.
            Scoasă, e o respingere adevărată — iar atunci se golește tot ce s-a
            strâns în jurul anunțului. De aceea scrie asta sub ea: e singurul
            lucru din tot blocul care nu se poate lua înapoi.
          ============================================================== -->
          <label class="check moderare__bifa">
            <input type="checkbox" checked data-moderare-editare>
            <span>Editare necesară</span>
          </label>

          <p class="moderare__urmare" data-moderare-urmare>
            Anunțul rămâne în așteptare, iar organizatorul primește un e-mail cu
            ce are de îndreptat.
          </p>

          <div class="moderare__caseta-actiuni">
            <button class="btn btn--primary btn--xs" type="button" data-modereaza="respins">
              <span data-moderare-text-buton>Cere îndreptarea</span>
            </button>
            <button class="btn btn--text" type="button" data-moderare-renunta>Renunță</button>
          </div>
        </div>
        <?php endif; ?>

        <p class="moderare__eroare" data-moderare-eroare role="alert" hidden></p>
      </section>
      <?php endif; ?>

      <!-- ====================== TABURI: DISCUȚII ========================== -->
      <!--
        La o vânătoare rămâne un singur tab, „Comentarii" — vezi $eVanatoare,
        mai sus. Rândul de taburi tot se desenează, cu el singur: e locul în
        care oamenii se întreabă unde n-au căutat încă, iar caseta de scris de
        dedesubt atârnă de panoul lui.
      -->
      <section class="tabs-section" aria-labelledby="tabs-title">
        <h2 class="sr-only" id="tabs-title">Discuții și participanți</h2>

        <div class="tabs" role="tablist" data-tabs aria-label="Comentarii și participanți">
          <button class="tab is-active" type="button" role="tab" id="tab-comments"
                  aria-controls="panel-comments" aria-selected="true" tabindex="0">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M20.5 12.5c0 4-3.8 7-8.5 7-1 0-2-.1-2.9-.4L4 21l1.3-3.4A7.4 7.4 0 0 1 3.5 12.5c0-4 3.8-7 8.5-7s8.5 3 8.5 7Z"/>
            </svg>
            <span>Comentarii</span>
            <span class="tab__count" data-count-for="comentarii"><?= $cateComentarii ?></span>
          </button>

          <?php if (!$eVanatoare): ?>
          <!--
            „Participă" înaintea lui „Interesați", dinadins: cine a spus că vine
            e vestea, ceilalți sunt doar o promisiune. Iar la un eveniment
            încheiat rămâne oricum numai ăsta, deci tot el trebuie să fie primul
            pe care cade ochiul.
          -->
          <button class="tab" type="button" role="tab" id="tab-going"
                  aria-controls="panel-going" aria-selected="false" tabindex="-1">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
            </svg>
            <!--
              Trei vorbe, fiindcă lista înseamnă trei lucruri deosebite:

                anulat   → „Ar fi participat". Oamenii ăștia se înscriseseră la
                           o seară care nu s-a mai ținut. „Au participat" ar fi
                           o minciună curată — n-au fost nicăieri — iar
                           „Participă", la prezent, i-ar fi trimis pe unii să-și
                           ia haina de pe cuier;
                încheiat → „Au participat", ce a fost;
                restul   → „Participă", ce urmează.

              Anulatul se întreabă ÎNAINTEA încheiatului: un anunț anulat pentru
              o zi care a trecut de-atunci e și una, și alta, iar anularea e
              vestea care contează.
            -->
            <span><?= $eAnulat ? 'Ar fi participat' : ($eIncheiat ? 'Au participat' : 'Participă') ?></span>
            <span class="tab__count" data-count-for="participant"><?= (int) $numarInterese['participant'] ?></span>
          </button>

          <?php if (!$eIncheiat): ?>
          <!--
            „Interesați" ține numai cât mai e ceva de hotărât.

            După ce s-a încheiat, lista aceea nu mai spune nimic despre seara
            care a fost: sunt oameni care s-au uitat într-acolo și n-au venit.
            Cine a fost cu adevărat e în tabul de alături, iar între cele două
            rămâne doar unul care contează. Panoul lui nici nu se mai
            desenează, mai jos.
          -->
          <button class="tab" type="button" role="tab" id="tab-interested"
                  aria-controls="panel-interested" aria-selected="false" tabindex="-1">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path d="m12 3.8 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 10l5.9-.9L12 3.8Z"/>
            </svg>
            <span>Interesați</span>
            <span class="tab__count" data-count-for="interesat"><?= (int) $numarInterese['interesat'] ?></span>
          </button>
          <?php endif; /* !$eIncheiat */ ?>
          <?php endif; /* !$eVanatoare */ ?>
        </div>

        <!-- ------------------------ PANOU: COMENTARII --------------------- -->
        <!--
          Tokenul CSRF se scrie DOAR pentru cine e conectat, ca la secțiunea de
          participare: un vizitator n-are ce face cu el, fiindcă vede o
          invitație la intrare în locul casetei de scris.

          `data-deodata` vine din COMENTARII_DEODATA (inc/comentarii.php), ca
          numărul să fie scris într-un singur loc și schimbat într-unul singur.
        -->
        <div class="panel is-active" id="panel-comments" role="tabpanel"
             aria-labelledby="tab-comments" tabindex="0"
             data-comentarii
             data-slug="<?= h((string) $eveniment['slug']) ?>"
             data-deodata="<?= COMENTARII_DEODATA ?>"
             <?= $eLogat ? 'data-csrf="' . h(tokenCsrf()) . '"' : '' ?>>

          <?php if ($eLogat && $discutieDeschisa): ?>
          <!--
            Formularul de comentariu nou. Numai pentru cine e conectat: mai jos
            e varianta pentru vizitatori, care nu are unde să scrie, ci unde să
            intre în cont.
          -->
          <form class="comment-form" data-comment-form>
            <img class="comment-form__avatar" src="<?= h(urlPoza($membru['poza'] ?? null, true)) ?>"
                 alt="" width="96" height="96">
            <div class="comment-form__main">
              <label class="sr-only" for="new-comment">Scrie un comentariu</label>
              <textarea id="new-comment" name="text" rows="3" maxlength="<?= COMENTARIU_MAX ?>"
                        placeholder="Scrie un comentariu…"
                        aria-describedby="err-comentariu"></textarea>
              <p class="field__error" id="err-comentariu" hidden></p>

              <?php if (poateMarcaImportant($eveniment, $membruId)): ?>
              <!--
                Bifa „Important", numai pentru organizator. Regula o știe
                poateMarcaImportant() din inc/comentarii.php, de unde o citește
                și api/comentarii.php — aici e doar purtarea frumoasă de a nu
                arăta un buton pe care omul n-are voie să-l apese.

                Stă DEASUPRA vorbei despre purtare frumoasă, dinadins: aceea e
                pentru toată lumea și se citește o dată, pe când asta e o
                unealtă, iar uneltele stau lângă mâna care le folosește. Sub ea
                scrie ce se întâmplă când o apeși — un e-mail către toți
                înscrișii nu e un lucru pe care omul să-l afle după ce l-a
                trimis.
              -->
              <label class="check comment-form__important">
                <input type="checkbox" id="comentariu-important" name="important" value="1">
                <span>Anunț important
                  <small>Comentariul va fi evidențiat și ținut sus iar toți cei înscriși vor fi notificați prin email.</small>
                </span>
              </label>
              <?php endif; ?>

              <div class="comment-form__actions">
                <p class="comment-form__hint">Hai să ne purtăm frumos unii cu alții. Comentariile urâte se șterg, iar dacă se repetă, contul poate fi suspendat.</p>
                <button class="btn btn--primary btn--sm" type="submit">Publică</button>
              </div>
            </div>
          </form>
          <?php elseif ($discutieDeschisa): ?>
          <!--
            Vizitatorul nu primește o casetă în care să scrie degeaba: ar fi
            aflat abia la apăsarea butonului că trebuie cont, cu textul scris
            deja. Primește direct ușa, cu întoarcere fix aici.
          -->
          <p class="comment-form__intra">
            <a class="btn btn--primary btn--sm"
               href="/login.php?redirect=<?= h(urlencode('/' . urlEveniment((string) $eveniment['slug']))) ?>">Intră în cont</a>
            <span>ca să lași un comentariu.</span>
          </p>
          <?php endif; ?>

          <!--
            Lista de comentarii. Fiecare `<li class="comment">` are înăuntru
            `<article class="comment__body">` și, dacă e principal cu discuție
            sub el, `<ul class="comment__replies">` — ca frate al articolului,
            nu în el. Așa main.js poate înlocui un comentariu editat sau golit
            fără să șteargă odată cu el răspunsurile de dedesubt.

            Toate intră în pagină; ascunsul îl face main.js — vezi
            randeazaComentarii() din inc/comentarii.php.
          -->
          <ul class="comments" data-lista-comentarii>
            <?= randeazaComentarii($fireComentarii, $contextComentarii) ?>
          </ul>

          <!--
            Nicio vorbă încă. Se scrie mereu în pagină, nu doar când lista e
            goală: dacă omul își șterge singurul comentariu, main.js are ce să
            aprindă la loc, fără să lipească text din cod.
          -->
          <!--
            Trei stări, trei vorbe — fiindcă golul are trei înțelesuri
            deosebite, iar unul singur pentru toate mințea:

              publicat → nu s-a scris nimic încă, e o invitație;
              anulat   → discuția e DESCHISĂ și aici (vezi discutiaEDeschisa),
                         dar invitația nu poate fi aceeași: „fii primul care
                         spune ceva" sub un anunț tocmai anulat sună a
                         petrecere. Se spune ce s-a întâmplat, și că se poate
                         vorbi mai departe. Aici a scris o vreme „comentariile
                         au fost închise" — era adevărat și era greșit: o
                         ieșire anulată e tocmai momentul în care oamenii au
                         ceva de zis unul altuia;
              restul   → anunțul chiar n-a trecut încă pe la nimeni.
          -->
          <p class="comments__gol" data-comentarii-gol <?= $fireComentarii === [] ? '' : 'hidden' ?>>
            <?php if ($eAnulat): ?>
            Evenimentul a fost anulat, dar discuția rămâne deschisă. Scrie, dacă
            ai ceva de spus.
            <?php elseif ($ePublicat): ?>
            Niciun comentariu încă. Fii primul care spune ceva.
            <?php else: ?>
            Discuția se deschide după ce evenimentul e publicat.
            <?php endif; ?>
          </p>

          <!--
            Butonul se aprinde din JS, cu numărul celor rămase ascunse. Cât e
            fără JS, e ascuns — toate comentariile sunt deja în pagină, deci
            n-ar avea ce să mai aducă.
          -->
          <div class="load-more" data-mai-multe hidden>
            <button class="btn btn--ghost" type="button" data-mai-multe-buton>Vezi mai multe comentarii</button>
          </div>
        </div>

        <!-- =================== PANOURILE CU OAMENI ======================
          „Interesați" și „Participă" sunt același panou de două ori, cu altă
          valoare în `data-stare`. Lista se desenează într-un singur loc,
          randeazaListaOameni() din inc/interese.php, iar ascunsul și butonul
          „Vezi mai mult" le face un singur bloc din main.js, care merge peste
          orice panou cu `data-oameni`.

          Se deosebesc prin trei lucruri, și atât: ce stare arată, ce scrie
          deasupra listei (vorbaDespreCatiSunt) și dacă au butoane de scoatere.
          Interesații n-au: „Mă interesează" nu ocupă niciun loc, e o însemnare
          în dreptul omului, deci n-are ce curăța nimeni acolo.

          `data-deodata` vine din OAMENI_DEODATA, ca numărul să fie scris
          într-un singur loc și schimbat într-unul singur.
        ============================================================== -->

        <?php if (!$eVanatoare): ?>
        <!-- ------------------------ PANOU: PARTICIPĂ ---------------------- -->
        <!--
          Tokenul CSRF se scrie doar aici, și doar pentru cine poate scoate pe
          cineva de pe listă — organizatorul și staff-ul. Pe panoul de
          interesați n-ar avea ce face.
        -->
        <div class="panel" id="panel-going" role="tabpanel" aria-labelledby="tab-going" tabindex="0" hidden
             data-oameni
             data-stare="participant"
             data-slug="<?= h((string) $eveniment['slug']) ?>"
             data-deodata="<?= OAMENI_DEODATA ?>"
             <?= ($poateScoateParticipanti || $contextEvaluare !== null) ? 'data-csrf="' . h(tokenCsrf()) . '"' : '' ?>>

          <p class="panel__intro<?= $numarInterese['participant'] === 0 ? ' panel__intro--gol' : '' ?>">
            <?= vorbaDespreCatiSunt((int) $numarInterese['participant'], 'participant', $eIncheiat) ?>
          </p>

          <?php
          /**
           * TERMENUL NOTELOR, scris pe față.
           *
           * Stelele stinse poartă motivul într-un `title`, dar acela se vede
           * doar dacă ții cursorul pe ele — iar pe telefon, niciodată. Cine a
           * fost la eveniment trebuie să afle din pagină cât mai are, nu să
           * descopere din stele care nu se apasă.
           *
           * Numai pentru cine A FOST acolo: pentru un vizitator oarecare,
           * termenul de notare nu spune nimic.
           */
          $vorbaDespreNote = '';

          if ($contextEvaluare !== null && $stareaMea === 'participant') {
              $termen = $contextEvaluare['termen'] ?? null;

              if (!empty($contextEvaluare['inchise'])) {
                  $vorbaDespreNote = 'Notele s-au închis — au trecut '
                                   . ORE_PENTRU_NOTE . ' de ore de la sfârșitul evenimentului.';
              } elseif ($termen !== null) {
                  // „joi, 28 august" — cu literă mică, fiindcă intră în
                  // mijlocul frazei (dataScrisaMic din inc/validare.php).
                  $vorbaDespreNote = 'Mai poți da note și scrie păreri până '
                                   . dataScrisaMic(date('Y-m-d', $termen))
                                   . ', la ' . date('H:i', $termen) . '.';
              }
          }
          ?>
          <?php if ($vorbaDespreNote !== ''): ?>
          <p class="panel__nota<?= !empty($contextEvaluare['inchise']) ? ' panel__nota--stinsa' : '' ?>">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            </svg>
            <span><?= h($vorbaDespreNote) ?></span>
          </p>
          <?php endif; ?>

          <ul class="people" data-lista-oameni>
            <?= randeazaListaOameni($evenimentId, 'participant', (int) $eveniment['membru_id'], $poateScoateParticipanti, $contextEvaluare, $vedeTelefoanele) ?>
          </ul>

          <div class="load-more" data-mai-multi hidden>
            <button class="btn btn--ghost" type="button" data-mai-multi-buton>Vezi mai mult</button>
          </div>

          <?php if ($poateScoateParticipanti): ?>
          <!--
            Caseta de confirmare a scoaterii. Una singură, mutată de JS sub
            omul pe care s-a apăsat: câte una pentru fiecare rând ar fi însemnat
            zeci de formulare ascunse în pagină, fiecare cu textarea și bifa lui.

            Motivul e obligatoriu (MOTIV_EXCLUDERE_MIN caractere) fiindcă pleacă
            întreg în e-mailul primit de omul scos — el are dreptul să știe de ce.
          -->
          <template id="sablon-scoatere">
            <!--
              Un `<li>`, nu un `<form>` pus de-a dreptul în listă: copiii unui
              `<ul>` sunt `<li>`-uri, iar lista e o grilă pe două coloane — așa
              caseta poate să le cuprindă pe amândouă, sub omul pe care s-a
              apăsat.
            -->
            <li class="scoate-rand" data-caseta>
            <form class="scoate-form">
              <p class="scoate-form__titlu">Vrei să-l scoți din listă pe <strong data-scoate-nume></strong>?</p>

              <label class="sr-only" for="scoate-motiv">De ce îl scoți</label>
              <textarea id="scoate-motiv" rows="3" maxlength="<?= MOTIV_EXCLUDERE_MAX ?>"
                        placeholder="Spune-ne de ce îl scoți (cel puțin <?= MOTIV_EXCLUDERE_MIN ?> caractere)…"
                        aria-describedby="scoate-motiv-hint err-scoate"></textarea>
              <p class="field__hint" id="scoate-motiv-hint">
                Îi trimitem un e-mail ca să afle, iar motivul scris aici merge în el.
              </p>
              <p class="field__error" id="err-scoate" hidden></p>

              <!--
                Bifa care închide ușa. `.check` e componenta de bifă a
                site-ului, aceeași ca la termeni sau la newsletter: fără ea,
                pătratul ar fi rămas nedesenat — resetul global stinge decorul
                nativ al oricărui `<input>` (vezi „Fără decor nativ pe
                controale" din style.css).
              -->
              <label class="check scoate-form__bifa">
                <input type="checkbox" data-scoate-interzis>
                <span>Nu vreau să se mai poată înscrie la acest eveniment</span>
              </label>

              <div class="scoate-form__actiuni">
                <button class="btn btn--primary btn--xs" type="submit">Scoate-l de pe listă</button>
                <button class="btn btn--text" type="button" data-renunta>Renunță</button>
              </div>
            </form>
            </li>
          </template>
          <?php endif; ?>

          <?php if ($contextEvaluare !== null && $eOrganizatorul): ?>
          <!--
            Caseta de confirmare a însemnării „Nu s-a prezentat".

            Aceeași casetă ca la scoaterea de pe listă, cu aceleași clase și
            același loc — sub omul pe care s-a apăsat — fiindcă e același fel
            de faptă: ceva ce organizatorul face ALTUIA și nu se mai poate lua
            înapoi. A fost o vreme un `confirm()` din browser, adică o fereastră
            care sare peste toată pagina și nu seamănă cu nimic din site; pe
            telefon, nici măcar cu site-ul de dedesubt.

            Fără motiv, spre deosebire de scoatere: acolo textul pleacă în
            e-mailul omului, care are dreptul să știe de ce. Aici nu se trimite
            nimănui nimic de citit — se pune o stea și un text scris de noi.
          -->
          <template id="sablon-absent">
            <li class="scoate-rand" data-caseta>
            <form class="scoate-form">
              <p class="scoate-form__titlu">
                Însemnezi că <strong data-absent-nume></strong> nu s-a prezentat?
              </p>

              <p class="field__hint">
                O să primească automat o stea și o însemnare pe profil. Nu se mai
                poate lua înapoi, așa că mai gândește-te o clipă.
              </p>

              <div class="scoate-form__actiuni">
                <button class="btn btn--primary btn--xs" type="submit">Da, nu s-a prezentat</button>
                <button class="btn btn--text" type="button" data-renunta>Renunță</button>
              </div>
            </form>
            </li>
          </template>
          <?php endif; ?>

          <?php if ($contextEvaluare !== null): ?>
          <!--
            „LASĂ ȘI CÂTEVA CUVINTE" — caseta se deschide AICI, sub rândul
            omului.

            Până acum, apăsarea deschidea profilul lui într-o filă nouă și
            derula până la formularul de acolo. Se pierdeau două lucruri
            deodată: locul din listă (cine tocmai notase trei oameni pleca de
            lângă al patrulea) și firul gândului — omul se trezea pe o pagină
            despre altcineva, printre păreri vechi, ca să scrie o propoziție.

            Acum e aceeași casetă ca la scoatere și la „Nu s-a prezentat":
            aceleași clase, același loc, aceeași deschideCaseta(). Nu e altă
            unealtă, e a treia folosire a uneia care exista.

            SE ÎNDREAPTĂ, NU SE ADAUGĂ. Redeschisă, caseta arată ce a scris
            omul data trecută (`data-parere` de pe stelele lui), iar salvarea
            trece prin același ON DUPLICATE KEY UPDATE ca înainte — un om nu
            poate lăsa două păreri la același eveniment, oricât ar apăsa.

            Stelele nu se aleg de aici: sunt deja alese în rândul de deasupra,
            iar caseta se arată numai după ce s-a apăsat una. Formularul de pe
            profil rămâne cum era — la el se ajunge tot cu „?ev=…", și tot
            acolo se scrie pentru cine deschide profilul de-a dreptul.
          -->
          <template id="sablon-parere">
            <li class="scoate-rand" data-caseta>
            <form class="scoate-form parere-form">
              <p class="scoate-form__titlu">
                Ce ai de spus despre <strong data-parere-nume></strong>?
              </p>

              <label class="sr-only" for="parere-text">Părerea ta</label>
              <textarea id="parere-text" rows="3" maxlength="<?= EVALUARE_TEXT_MAX ?>"
                        placeholder="Spune pe scurt cum a fost…"
                        aria-describedby="parere-hint err-parere"></textarea>
              <p class="field__hint" id="parere-hint">
                Textul apare semnat pe profilul lui — spre deosebire de stele,
                care rămân anonime. Îl poți schimba oricând.
              </p>
              <p class="field__error" id="err-parere" hidden></p>

              <div class="scoate-form__actiuni">
                <button class="btn btn--primary btn--xs" type="submit">Trimite</button>
                <button class="btn btn--text" type="button" data-renunta>Renunță</button>
              </div>
            </form>
            </li>
          </template>
          <?php endif; ?>
        </div>

        <!-- ------------------------ PANOU: INTERESAȚI --------------------- -->
        <!-- Numai cât mai e ceva de hotărât — vezi tabul lui, de mai sus. -->
        <?php if (!$eIncheiat): ?>
        <div class="panel" id="panel-interested" role="tabpanel" aria-labelledby="tab-interested" tabindex="0" hidden
             data-oameni
             data-stare="interesat"
             data-deodata="<?= OAMENI_DEODATA ?>">

          <!-- Fără nimeni pe listă, rândul ăsta e o invitație, nu o
               numărătoare: se așază pe mijloc, ca „Niciun comentariu încă" din
               tabul de alături. Cu oameni pe listă, rămâne în stânga. -->
          <p class="panel__intro<?= $numarInterese['interesat'] === 0 ? ' panel__intro--gol' : '' ?>">
            <?= vorbaDespreCatiSunt((int) $numarInterese['interesat'], 'interesat', $eIncheiat) ?>
          </p>

          <ul class="people" data-lista-oameni>
            <?= randeazaListaOameni($evenimentId, 'interesat', (int) $eveniment['membru_id']) ?>
          </ul>

          <div class="load-more" data-mai-multi hidden>
            <button class="btn btn--ghost" type="button" data-mai-multi-buton>Vezi mai mult</button>
          </div>
        </div>
        <?php endif; /* !$eIncheiat */ ?>
        <?php endif; /* !$eVanatoare */ ?>

      </section>
    </article>

    <?php if ($sugerate !== []): ?>
    <!-- ======================= AR PUTEA SĂ TE INTERESEZE ==================
      Câteva evenimente la întâmplare, din orice oraș, dintre cele care N-AU
      ÎNCEPUT ÎNCĂ. E o invitație, nu o listă: n-are rost să trimiți pe cineva
      la o seară care se petrece chiar acum — n-are cum să mai ajungă — și cu
      atât mai puțin la una încheiată.

      Fără niciunul, secțiunea nu se scrie deloc: pagina se oprește la
      comentarii și sare la subsol. Un titlu cu nimic sub el e mai rău decât
      lipsa lui.

      Cartonașele sunt cele de peste tot (randeazaCartonasEveniment), fără
      niciun semn de stare: aici sunt, prin alegere, numai evenimente care
      urmează.
    ============================================================== -->
    <section class="related" aria-labelledby="related-title">
      <div class="section-head">
        <h2 class="section-title" id="related-title">Ar putea să te intereseze și ...</h2>
      </div>

      <div class="grid">
        <?php foreach ($sugerate as $ev): ?>
        <?= randeazaCartonasEveniment($ev) ?>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
