<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — evenimentele.
 *
 * Categoriile, regula „un eveniment activ", salvarea, editarea, anularea și
 * încheierea. Moderarea (aprobarea) e singura care încă n-are interfață.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validare.php';
require_once __DIR__ . '/imagini.php';

/**
 * Câte evenimente active poate avea un membru, dacă nu s-a hotărât altfel
 * pentru el (`membri.limita_evenimente_active`).
 */
const EVENIMENTE_ACTIVE_IMPLICIT = 1;

/* ============================ CATEGORIILE ============================= */

/** Categoriile, în ordinea în care se arată. */
function categoriiEvenimente(bool $cuAleStaffului = false): array
{
    /**
     * Cache-ul e ținut pe fiecare fel de listă în parte.
     *
     * O singură variabilă ar fi însemnat că prima întrebare din pagină o
     * hotărăște și pe a doua: dacă staff-ul cere întâi lista lui și pe urmă
     * cea obișnuită, ar fi primit-o tot pe prima.
     */
    static $cache = [];

    $cheie = $cuAleStaffului ? 'tot' : 'obisnuite';

    if (isset($cache[$cheie])) {
        return $cache[$cheie];
    }

    /**
     * Implicit, FĂRĂ categoriile ținute pentru casă (`doar_staff = 1`).
     *
     * Așa în jurul lui zero e răspunsul strâmt: o funcție nouă care uită să
     * ceară lista întreagă arată prea puțin, nu prea mult. Prima astfel de
     * categorie e „FindMe", jocul cu coduri QR: evenimentele lui nu le propune
     * nimeni, le pune casa.
     */
    $doarObisnuite = $cuAleStaffului ? '' : ' WHERE doar_staff = 0';

    $q = db()->query('SELECT id, nume, slug, doar_staff, joc_qr FROM categorii'
                   . $doarObisnuite . ' ORDER BY ordine, nume');

    return $cache[$cheie] = $q->fetchAll();
}

/**
 * Doar id-urile, pentru verificarea din formular.
 *
 * De aici atârnă cine POATE PUBLICA într-o categorie ținută pentru casă: cine
 * nu e staff nu primește id-ul ei, deci verificarea din verificaEveniment()
 * îl respinge chiar dacă a scris numărul de mână în cerere. Nu e de ajuns că
 * lista din formular n-o arată.
 */
function idCategoriiValide(bool $cuAleStaffului = false): array
{
    return array_map(static fn(array $c): int => (int) $c['id'],
                     categoriiEvenimente($cuAleStaffului));
}

/**
 * Categoriile care au măcar un eveniment public.
 *
 * Pentru filtrele de pe prima pagină. O categorie în care n-a pus nimeni nimic
 * e un buton care duce la un ecran gol: ocupă loc, se apasă o dată și pe urmă
 * nu mai are cine să se încreadă în rândul acela.
 *
 * ÎN TOATĂ BAZA, nu doar în orașul ales. Altfel rândul de chipuri s-ar
 * rearanja la fiecare schimbare de oraș, iar categoria pe care tocmai ai
 * apăsat-o ar putea să-ți fugă de sub deget. Așa rămâne așezat, iar „niciun
 * eveniment pe potriva filtrelor" își păstrează rostul: pentru potriviri care
 * chiar nu există, nu pentru categorii care nu există.
 *
 * Formularul de publicat un eveniment folosește mai departe categoriiEvenimente()
 * — acolo trebuie să apară TOATE cele obișnuite, tocmai ca ele să se poată
 * umple.
 *
 * Categoriile ținute pentru casă (`doar_staff = 1`, ca „FindMe") apar AICI ca
 * oricare alta, pentru toată lumea. `doar_staff` spune cine poate PUBLICA în
 * ea, nu cine o poate căuta: dacă „FindMe" e o ieșire adevărată, pe prima
 * pagină, atunci trebuie să se poată și filtra după ea — altfel evenimentele
 * ei ar fi rămas de găsit doar derulând, sub o categorie pe care n-o putea
 * alege nimeni. Singurul loc unde lipsește e formularul de publicare.
 *
 * „Public" înseamnă ACELEAȘI TREI STĂRI ca în evenimenteDePePrima(), inclusiv
 * „anulat". Un anunț anulat nu se mai ascunde de nimeni: se vede pe prima
 * pagină, stins, cu motivul pe pagina lui. Cât timp lista de aici cerea doar
 * „aprobat" sau „incheiat", o categorie cu un singur eveniment, anulat,
 * dispărea din filtre — iar evenimentul rămânea pe pagină, sub o categorie pe
 * care n-o mai putea alege nimeni.
 */
function categoriiCuEvenimente(): array
{
    $q = db()->query(
        'SELECT c.id, c.nume, c.slug
           FROM categorii c
          WHERE EXISTS (
                  SELECT 1 FROM evenimente e
                   WHERE e.categorie_id = c.id
                     AND e.stare_moderare IN (\'aprobat\', \'incheiat\', \'anulat\')
                )
          ORDER BY c.ordine, c.nume'
    );

    return $q->fetchAll();
}

/* ====================== UN EVENIMENT ACTIV ============================ */

/**
 * Ce înseamnă „încheiat", într-un singur loc.
 *
 * Un eveniment se încheie în două feluri: organizatorul îl marchează așa
 * (butonul „Încheie evenimentul" de pe pagina lui), sau trece ziua în care a
 * avut loc. Al doilea nu are
 * nevoie de nicio sarcină programată la miezul nopții: e destul să comparăm
 * data cu ziua de azi ATUNCI CÂND ÎNTREBĂM.
 *
 * Un rând în bază care s-ar schimba singur la ora 00:00 ar cere un cron care
 * poate să nu ruleze, iar o zi în care n-a rulat ar ține oamenii blocați fără
 * motiv. Așa, răspunsul e mereu corect, chiar dacă serverul a fost oprit o
 * săptămână.
 *
 * Întoarce bucata de WHERE și valoarea ei împreună, ca să nu poată fi folosită
 * una fără cealaltă: regula asta hotărăște și câte evenimente poate posta
 * cineva, și ce se vede pe profilul lui. Dacă cele două ar socoti altfel, omul
 * ar fi blocat de un eveniment pe care nu-l mai vede nicăieri.
 *
 * „data_eveniment >= CURDATE()" — dar data o dăm noi, din PHP, ca peste tot.
 * Ziua, nu ora: un eveniment de azi de la 19:00 e activ și la 20:00, fiindcă
 * se încheie abia mâine.
 */
function filtruNeincheiat(): array
{
    return ['stare_moderare <> \'incheiat\' AND data_eveniment >= ?', date('Y-m-d')];
}

/**
 * S-a încheiat evenimentul ăsta?
 *
 * DOUĂ feluri de a se încheia, și amândouă se socotesc la fiecare citire:
 *
 *   - i-a trecut ziua — se întâmplă singur, fără cron;
 *   - organizatorul a apăsat „Încheie evenimentul" — se poate întâmpla și
 *     mai devreme: s-au ocupat locurile, s-a stricat vremea la jumătate.
 *
 * Aceeași regulă ca filtruNeincheiat(), scrisă pentru un singur rând, nu
 * pentru o interogare — pagina evenimentului are deja rândul în mână și n-are
 * de ce să mai întrebe baza. Perechea ei stă lipită de ea dinadins: dacă una
 * se schimbă, cealaltă sare în ochi. Dacă ar socoti altfel, un eveniment ar
 * putea arăta „încheiat" pe pagina lui și ar bloca în același timp postarea
 * altuia.
 */
function evenimentIncheiat(array $eveniment): bool
{
    if (($eveniment['stare_moderare'] ?? '') === 'incheiat') {
        return true;
    }

    $data = (string) ($eveniment['data_eveniment'] ?? '');

    return $data !== '' && $data < date('Y-m-d');
}

/**
 * A început deja?
 *
 * De ea atârnă tot ce se închide în clipa în care evenimentul pornește: caseta
 * „Mergi la acest eveniment?", butoanele ei, scoaterea cuiva de pe listă. Deci
 * răspunsul ei trebuie să fie „da" ori de câte ori evenimentul nu mai e ceva
 * ce urmează.
 *
 * DOUĂ CĂI, și amândouă contează:
 *
 *   1. Ceasul: ziua ȘI ora, nu doar ziua — spre deosebire de „încheiat", care
 *      se socotește pe zile. Un eveniment de azi de la 19:00 n-a început la ora
 *      10 dimineața.
 *
 *   2. Starea: un eveniment ÎNCHEIAT a început, oricât ar arăta ceasul.
 *
 * A doua pare de prisos, fiindcă butonul „Încheie evenimentul" se dă doar după
 * ce a început (vezi $poateIncheia din event.php) — deci, pe drumul obișnuit,
 * încheiat vine mereu după început. Dar starea se poate schimba și pe alt drum:
 * de mână, din phpMyAdmin, așa cum se fac multe pe site-ul ăsta. Iar atunci
 * ieșea o pagină care spunea „Acest eveniment s-a încheiat." și dedesubt
 * întreba, cu butoane vii, „Mergi la acest eveniment?" — ba mai mult, lăsa
 * organizatorul să scoată oameni de pe o listă care nu mai era a nimănui.
 *
 * Ce e scris ca presupunere într-un comentariu se strică în tăcere; ce e scris
 * în cod, nu. Aici e scris în cod.
 *
 * Se compară șiruri „AAAA-LL-ZZ HH:MM:SS", care se ordonează la fel ca
 * momentele pe care le scriu. Ceasul e al PHP-ului, ca peste tot.
 */
function evenimentAInceput(array $eveniment): bool
{
    if (evenimentIncheiat($eveniment)) {
        return true;
    }

    $data = (string) ($eveniment['data_eveniment'] ?? '');

    if ($data === '') {
        return false;
    }

    // Ora de început e obligatorie în formular, dar dacă printr-o scăpare
    // lipsește, ziua începe la miezul nopții.
    $ora = oraScurta($eveniment['ora_inceput'] ?? null);

    return $data . ' ' . ($ora !== '' ? $ora : '00:00') . ':00' <= acum();
}

/**
 * E un anunț care se vede pe site?
 *
 * „aprobat" și „incheiat" — două stări, o singură purtare față de lume.
 * Încheiat nu înseamnă ascuns: evenimentul a avut loc, iar pagina lui rămâne
 * de citit și de trimis mai departe. Se stinge doar ce se poate face pe ea.
 *
 * Deosebirea față de „anulat", care se ascunde de toți în afară de staff: acela
 * n-a mai avut loc, deci n-are ce spune nimănui.
 */
function evenimentPublicat(array $eveniment): bool
{
    return in_array($eveniment['stare_moderare'] ?? '', ['aprobat', 'incheiat'], true);
}

/* ========================== ANUNȚUL FIXAT ============================ */

/**
 * Stă în capul primei pagini?
 *
 * O întrebare, un singur loc care o pune — ca la esteJocQr(). Coloana
 * (`fixat_la`, sql/029) e o DATĂ, nu un 0/1: cine se uită la ea află și de când
 * stă acolo, iar două anunțuri fixate se așază între ele după ea, fără să mai
 * fie nevoie de o a doua coloană.
 */
function esteFixat(?array $eveniment): bool
{
    return $eveniment !== null && ($eveniment['fixat_la'] ?? null) !== null;
}

/**
 * Cine poate umbla la piuneză.
 *
 * NUMAI OMUL CASEI, oricine ar fi scris anunțul. Nu e o unealtă a
 * organizatorului: dacă ar fi, ar apăsa-o toți, iar „primul în listă" n-ar mai
 * însemna nimic — ar fi doar rândul obișnuit, scris cu alt cuvânt.
 *
 * ORICE ANUNȚ CARE SE VEDE PE SITE, oricare i-ar fi starea: aprobat, încheiat
 * sau anulat. Un anunț anulat care rămâne fixat e chiar ce trebuie uneori —
 * vestea că nu se mai ține ajunge la toți cei care o așteptau tocmai stând sus.
 *
 * Ce NU se fixează: ce n-a trecut încă pe la nimeni (în așteptare) și ce a fost
 * respins. Acolo n-ar avea unde să stea primul, fiindcă nu sunt pe prima pagină
 * deloc — iar o piuneză pusă pe un anunț nevăzut de nimeni ar fi o unealtă care
 * nu face nimic, adică una pe care cineva o va apăsa într-o zi și se va întreba
 * de ce n-a mers.
 */
function poateFiFixat(?array $eveniment): bool
{
    return $eveniment !== null
        && in_array($eveniment['stare_moderare'] ?? '', ['aprobat', 'incheiat', 'anulat'], true);
}

/**
 * Pune sau ia piuneza.
 *
 * Nu verifică nimic: cine are voie se hotărăște în api/fixeaza-eveniment.php,
 * unde se știe și cine cere. Aici e doar scrierea — aceeași împărțire ca la
 * moderezaEveniment() și anuleazaEveniment().
 *
 * `actualizat_la` NU se atinge, spre deosebire de celelalte scrieri: piuneza nu
 * e o schimbare a anunțului, e o hotărâre despre unde stă el în listă. Dacă ar
 * atinge-o, un anunț fixat ar părea „editat acum" în lista de administrare, iar
 * ștampila de corectură (sql/026) și-ar pierde înțelesul.
 */
function fixeazaEveniment(array $eveniment, bool $fixat): void
{
    $q = db()->prepare('UPDATE evenimente SET fixat_la = ? WHERE id = ?');
    $q->execute([$fixat ? acum() : null, (int) $eveniment['id']]);
}

/* ============================== MODERAREA ============================ */

/**
 * Stările pe care le poate PUNE staff-ul, de pe pagina evenimentului.
 *
 * Doar astea două. „incheiat" și „anulat" nu sunt hotărâri de moderare, sunt
 * fapte petrecute — le pune organizatorul sau ceasul, fiecare pe drumul lui.
 */
const STARI_DE_MODERAT = ['aprobat', 'respins'];

/**
 * Stările DIN care se poate modera.
 *
 * „in_asteptare" e cazul obișnuit: anunțul așteaptă să fie citit. Celelalte
 * două sunt răzgândirea, în amândouă sensurile — un anunț respins din greșeală
 * trebuie să poată fi aprobat, iar unul aprobat prea repede trebuie să poată fi
 * oprit.
 *
 * ANULAT ȘI ÎNCHEIAT NU SUNT AICI, dinadins. Anulat e hotărârea
 * organizatorului, luată în fața oamenilor înscriși, care au primit deja un
 * e-mail cu motivul; o „aprobare" peste ea ar readuce pe site un eveniment
 * despre care toată lumea a aflat că nu mai are loc. Încheiat înseamnă că
 * seara aceea a trecut — n-are ce să mai fie aprobat sau respins din ea.
 */
const STARI_MODERABILE = ['in_asteptare', 'aprobat', 'respins'];

/** Se poate umbla la starea evenimentului ăstuia? */
function poateFiModerat(array $eveniment): bool
{
    return in_array($eveniment['stare_moderare'] ?? '', STARI_MODERABILE, true);
}

/**
 * Pune starea hotărâtă de staff.
 *
 * Nu verifică nimic: cine are voie și ce stare e îngăduită se hotărăsc în
 * api/modereaza-eveniment.php, unde se știe și cine cere. Aici e doar scrierea
 * — aceeași împărțire ca la anuleazaEveniment() și incheieEveniment().
 *
 * `motiv_anulare` NU se atinge: e al anulării, iar de acolo nu se ajunge aici.
 *
 * $cerutaCorectura — s-a apăsat „Respinge" cu bifa „editare necesară". Starea
 * rămâne „in_asteptare" (anunțul e viu, îl așteaptă pe om), dar se pune
 * ȘTAMPILA, ca în lista de administrare să se vadă că anunțul ăsta a fost deja
 * citit și i s-a cerut ceva. Fără ea, arăta exact ca unul necitit de nimeni.
 *
 * La ORICE ALTĂ hotărâre ștampila se șterge: un anunț tocmai aprobat sau
 * respins de-a binelea n-are ce corectură să mai aștepte.
 */
function moderezaEveniment(array $eveniment, string $stare, bool $cerutaCorectura = false): void
{
    $q = db()->prepare(
        'UPDATE evenimente SET stare_moderare = ?, corectura_ceruta_la = ?,
                actualizat_la = ? WHERE id = ?'
    );

    $q->execute([$stare, $cerutaCorectura ? acum() : null, acum(), (int) $eveniment['id']]);
}

/**
 * Golește tot ce s-a strâns în jurul unui eveniment, la respingerea lui.
 *
 * RÂNDUL EVENIMENTULUI RĂMÂNE. Se duc doar lucrurile pe care le-au făcut
 * oamenii în jurul lui: discuția, notele, listele. Anunțul rămâne al
 * organizatorului, cu starea „respins", ca să-l poată vedea și îndrepta.
 *
 * De ce se golește: un anunț respins n-a fost niciodată public, deci ce s-a
 * strâns în jurul lui e ori zgomot rămas de la o aprobare luată înapoi, ori
 * urma unei greșeli. Iar dacă tot nu se va publica, notele acelea ar rămâne
 * pentru totdeauna pe profilurile unor oameni, legate de o seară care n-a
 * existat.
 *
 * Se șterge în TRANZACȚIE: sunt patru tabele, iar o cădere la jumătate ar lăsa
 * un eveniment cu comentarii fără participanți sau cu note fără înscrieri.
 * `comentarii_aprecieri` nu apare aici fiindcă pleacă singură, în cascadă după
 * `comentarii` (vezi sql/015-comentarii.sql) — o ștergere scrisă de mână peste
 * cascadă ar fi fost o a doua regulă care poate să nu mai fie adevărată mâine.
 *
 * Întoarce câte rânduri au plecat din fiecare, pentru log și pentru probe.
 */
function golesteDateleEvenimentului(int $evenimentId): array
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $sters = [];

        /**
         * Ordinea nu contează pentru bază — niciuna nu atârnă de alta, toate
         * atârnă de eveniment — dar se scrie de la ce e mai „vorbit" spre ce e
         * mai tehnic, ca lista să se citească.
         */
        foreach ([
            'comentarii'            => 'comentarii',
            'evaluari'              => 'evaluari',
            'excluderi_evenimente'  => 'excluderi',
            'interese_evenimente'   => 'interese',
        ] as $tabel => $nume) {
            $q = $pdo->prepare('DELETE FROM ' . $tabel . ' WHERE eveniment_id = ?');
            $q->execute([$evenimentId]);
            $sters[$nume] = $q->rowCount();
        }

        $pdo->commit();

        return $sters;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Șterge un eveniment DE TOT: rândul, tot ce atârnă de el, și coperta de pe
 * disc.
 *
 * Nu e golirea de mai sus. Aceea lasă anunțul în picioare și îi mătură
 * împrejurimile; asta îl ia cu totul. Se cheamă dintr-un singur loc —
 * api/admin.php, la curățenia unui anunț RESPINS — și numai de acolo, fiindcă
 * e singura ștergere adevărată de pe tot site-ul: contul se anonimizează,
 * comentariul se golește, dorința rămâne în tabel. Un anunț respins n-a fost
 * niciodată public, deci nu lasă în urmă pe nimeni care să se întrebe unde a
 * dispărut.
 *
 * CINE ARE VOIE ȘI CE SE POATE ȘTERGE NU SE ÎNTREABĂ AICI. Funcția asta face
 * fapta; paza e la punctul de intrare, care cere și om de casă, și starea
 * „respins" — vezi api/admin.php.
 *
 * Rândurile din celelalte tabele pleacă în CASCADĂ, prin cheile străine
 * (comentarii, interese, evaluări, excluderi — și aprecierile, în cascada
 * comentariilor). Scrise de mână aici, ar fi fost o a doua listă, care într-o
 * zi n-ar mai fi fost la fel cu prima. Singura care NU pleacă e `coduri_qr`:
 * acolo cheia e ON DELETE SET NULL, iar asta e chiar ce trebuie — abțibildul e
 * lipit pe un stâlp și rămâne, doar că se întoarce în „nefolosit" și poate fi
 * legat de alt anunț.
 *
 * COPERTA SE ȘTERGE DE PE DISC LA URMĂ, după ce rândul a plecat cu bine. Invers
 * — fișierul întâi — o cădere la scriere ar fi lăsat un eveniment care arată
 * spre o poză care nu mai e.
 *
 * Întoarce true dacă rândul chiar a plecat.
 */
function stergeEvenimentDeTot(array $eveniment): bool
{
    $q = db()->prepare('DELETE FROM evenimente WHERE id = ?');
    $q->execute([(int) $eveniment['id']]);

    if ($q->rowCount() !== 1) {
        return false;
    }

    require_once __DIR__ . '/imagini.php';
    stergeCopertaDeFisier($eveniment['coperta'] ?? null);

    return true;
}

function evenimenteActive(int $membruId): array
{
    [$unde, $azi] = filtruNeincheiat();

    $q = db()->prepare(
        // Ce te împiedică să postezi altul: ce așteaptă moderarea și ce e
        // aprobat. Nici respinsul, nici anulatul nu mai sunt evenimente —
        // primul n-a ajuns să fie, al doilea a încetat să fie — și n-au voie
        // să țină pe cineva blocat.
        'SELECT id, titlu, slug, data_eveniment, stare_moderare
           FROM evenimente
          WHERE membru_id = ?
            AND stare_moderare IN (\'in_asteptare\', \'aprobat\')
            AND ' . $unde . '
          ORDER BY data_eveniment'
    );

    $q->execute([$membruId, $azi]);

    return $q->fetchAll();
}

/**
 * Câte evenimente active are voie omul ăsta.
 *
 * NULL în bază = regula obișnuită. Un număr = atâtea, pentru cine are nevoie.
 */
function limitaEvenimente(int $membruId): int
{
    $q = db()->prepare('SELECT limita_evenimente_active FROM membri WHERE id = ? LIMIT 1');
    $q->execute([$membruId]);
    $limita = $q->fetchColumn();

    if ($limita === null || $limita === false || $limita === '') {
        return EVENIMENTE_ACTIVE_IMPLICIT;
    }

    return max(0, (int) $limita);
}

/**
 * Poate publica acum?
 *
 * Întoarce ['poate' => bool, 'mesaj' => string, 'active' => array].
 *
 * STAFF-UL TRECE PESTE LIMITĂ. Ea e făcută să oprească pe cine ar umple prima
 * pagină cu zece anunțuri deodată — iar omul de casă publică tocmai zece:
 * târgul, concertul din parc, ziua orașului. Cu limita pe el, ar fi trebuit
 * să-și ridice singur numărul din phpMyAdmin înainte de fiecare al doilea
 * anunț, ceea ce e o piedică pusă exact în calea celui în care avem încredere.
 *
 * Lista celor active se citește oricum, și pentru el: e ce arată pagina sub
 * formular („ai deja pe astea"), și n-are de ce să dispară doar fiindcă nu-l
 * mai oprește nimic.
 */
function poatePublicaEveniment(int $membruId, bool $eStaff = false): array
{
    $active = evenimenteActive($membruId);
    $limita = limitaEvenimente($membruId);

    if ($eStaff || count($active) < $limita) {
        return ['poate' => true, 'mesaj' => '', 'active' => $active];
    }

    /**
     * Mesajul e la singular când limita e unu, fiindcă așa e pentru aproape
     * toată lumea, și n-are rost să sune ca un formular de la primărie.
     */
    $mesaj = $limita === 1
        ? 'Ai deja un eveniment activ. Poți posta unul nou după ce acesta se încheie.'
        : 'Ai deja ' . count($active) . ' evenimente active, câte poți avea în același timp. '
        . 'Poți posta unul nou după ce se încheie vreunul.';

    return ['poate' => false, 'mesaj' => $mesaj, 'active' => $active];
}

/* ====================== EVENIMENTELE DE PE PROFIL ===================== */

/**
 * Câte evenimente se citesc pentru un profil.
 *
 * Toate ajung în pagină deodată — cele de peste patru doar ascunse — deci
 * numărul are nevoie de un capăt. Cu regula obișnuită de un eveniment activ,
 * nimeni nu se apropie de el; e acolo pentru ziua în care cineva primește o
 * limită mare și pentru ca pagina să nu poată crește la nesfârșit.
 */
const EVENIMENTE_PE_PROFIL_MAX = 60;

/* ===================== LISTA DE PE PRIMA PAGINĂ ====================== */

/** Câte evenimente se văd la prima încărcare a paginii. */
const EVENIMENTE_PRIMA_TURA = 10;

/** Câte se mai aduc la fiecare apăsare pe „Vezi mai mult". */
const EVENIMENTE_INCA = 4;

/**
 * Evenimentele de pe prima pagină: mai întâi cele care urmează, apoi cele
 * încheiate.
 *
 * SE ADUC DIN BAZĂ CÂTE PUȚINE, nu toate deodată ca peste tot pe site. Restul
 * listelor de aici — comentariile, oamenii dintr-un tab, evaluările de pe
 * profil — intră întregi în pagină și se ascund din JS, fiindcă au un capăt
 * firesc: câți oameni pot fi la un eveniment, câte păreri poate primi un om.
 * Prima pagină n-are: peste un an sunt sute de evenimente, iar a le trimite pe
 * toate ca să se vadă zece ar fi o pagină de un megabyte pentru un ecran de
 * conținut. De aceea aici, și numai aici, e nevoie de o cerere pentru fiecare
 * teanc (vezi api/lista-evenimente.php).
 *
 * ORDINEA e cerută de felul în care se citește pagina: ce urmează, în ordinea
 * în care vine — cel mai apropiat sus. Apoi ce s-a încheiat, cel mai proaspăt
 * întâi: dintre două seri trecute, cea de ieri interesează mai mult decât cea
 * de acum trei luni.
 *
 * „Încheiat" se socotește la fel ca peste tot (vezi evenimentIncheiat): ori
 * i-a trecut ziua, ori organizatorul a apăsat butonul. Ziua o dă PHP-ul, ca
 * peste tot — MySQL e pe alt fus, iar CURDATE() ar rupe socoteala în cele două
 * ore de diferență.
 *
 * Întoarce și dacă mai e ceva după teancul ăsta: se cere un rând în plus și se
 * aruncă. Așa butonul „Vezi mai mult" știe dacă are ce descoperi, fără o a doua
 * cerere care să numere tot.
 */
function evenimenteDePePrima(string $oras = '', string $categorie = '', int $deLa = 0, int $cate = EVENIMENTE_PRIMA_TURA): array
{
    /**
     * Întâi se închid vânătorile cărora le-a trecut termenul, apoi se citește.
     *
     * Aceeași rânduială ca la tabla cu dorințe, unde stampileazaCeleAprobate()
     * e chemată de dorinteDePeTabla(): ștampila o pune codul, într-un singur
     * loc, chiar de cel care are nevoie ca rândurile să fie la zi. Pusă în
     * index.php, ar fi lipsit din api/lista-evenimente.php, iar teancul de la
     * „Vezi mai mult" ar fi arătat altceva decât primul.
     *
     * Fără rândul ăsta, o vânătoare cu termenul trecut la 18:00 stătea până la
     * miezul nopții printre cele care urmează — cu numărătoarea la zero.
     */
    incheieVanatorileTrecute();

    $azi   = date('Y-m-d');
    $deLa  = max(0, $deLa);
    $cate  = max(1, min($cate, EVENIMENTE_PRIMA_TURA));

    /**
     * Bucata care spune dacă s-a încheiat. Se scrie o dată și se folosește de
     * trei ori — în SELECT și de două ori în ORDER BY — ca cele trei să nu
     * poată ajunge vreodată să spună lucruri diferite.
     */
    $eIncheiat = '(e.stare_moderare IN (\'incheiat\', \'anulat\') OR e.data_eveniment < ?)';

    /**
     * ANULATELE INTRĂ ÎN LISTĂ, cu cele trecute.
     *
     * Au stat ascunse o vreme și era greșit: de un eveniment anulat atârnă
     * oameni care își făcuseră planuri, iar un anunț care dispare din listă îi
     * lasă să creadă că l-au visat. Acum stă la locul lui, stins, cu „Anulat"
     * scris în colț — o veste, nu o gaură.
     *
     * Se socotesc drept trecute chiar dacă ziua lor e în viitor: seara aceea
     * nu mai urmează pentru nimeni, deci n-are ce căuta printre cele la care
     * se mai poate ajunge.
     */
    $unde   = ['e.stare_moderare IN (\'aprobat\', \'incheiat\', \'anulat\')'];
    $valori = [$azi];   // primul „?" e cel din SELECT

    if ($oras !== '') {
        $unde[]   = 'e.oras = ?';
        $valori[] = $oras;
    }

    if ($categorie !== '') {
        $unde[]   = 'c.slug = ?';
        $valori[] = $categorie;
    }

    // Cele două „?" din ORDER BY, în ordinea în care apar.
    $valori[] = $azi;
    $valori[] = $azi;

    $q = db()->prepare(
        'SELECT e.id, e.titlu, e.slug, e.coperta, e.data_eveniment, e.ora_inceput,
                e.locatie, e.descriere, e.stare_moderare, e.oras, e.participanti_max,
                e.fixat_la,
                c.nume AS categorie, c.slug AS categorie_slug, c.imagine_default,
                c.joc_qr AS categorie_joc_qr,
                ' . CIFRE_CARTONAS . ',
                ' . $eIncheiat . ' AS incheiat
           FROM evenimente e
           JOIN categorii c ON c.id = e.categorie_id
          WHERE ' . implode(' AND ', $unde) . '
          -- CELE FIXATE, DEASUPRA TUTUROR — înaintea oricărei alte socoteli.
          --
          -- `fixat_la IS NULL` dă 0 pentru cele fixate și 1 pentru restul, iar
          -- `ASC` le pune pe cele cu 0 în cap. Cheia asta stă ÎNAINTEA celei
          -- care desparte trecutul de viitor, dinadins: altfel un anunț
          -- încheiat sau anulat, dar fixat, ar fi căzut oricum sub tot ce
          -- urmează, și tocmai atunci piuneza n-ar mai fi făcut nimic.
          --
          -- Între ele, cel fixat cel mai de curând stă primul: `fixat_la DESC`.
          -- Fără rândul ăsta, două anunțuri fixate s-ar fi așezat după ziua
          -- lor, iar omul casei n-ar fi avut niciun fel de a spune care
          -- contează mai mult.
          ORDER BY (e.fixat_la IS NULL) ASC,
                   e.fixat_la DESC,
                   ' . $eIncheiat . ' ASC,
                   CASE WHEN ' . $eIncheiat . ' THEN NULL ELSE e.data_eveniment END ASC,
                   e.data_eveniment DESC,
                   e.ora_inceput ASC,
                   e.id ASC
          LIMIT ' . ($cate + 1) . ' OFFSET ' . $deLa
    );

    $q->execute($valori);

    $randuri  = $q->fetchAll();
    $maiSunt  = count($randuri) > $cate;

    return [
        'evenimente' => array_slice($randuri, 0, $cate),
        'mai_sunt'   => $maiSunt,
    ];
}

/**
 * Teancul de cartonașe de pe prima pagină, gata desenat.
 *
 * Îl cer două locuri: index.php, la încărcare, și api/lista-evenimente.php, la
 * fiecare apăsare pe „Vezi mai mult". Scris în două locuri, ar fi început să
 * difere de la prima corectură — iar aici s-ar fi văzut din prima, fiindcă
 * cartonașele venite prin fetch stau lipite de cele scrise de PHP.
 *
 * Cartonașul e același ca pe profil (randeazaCartonasEveniment): ce se adaugă
 * aici e doar semnul că evenimentul a trecut.
 */
function randeazaListaEvenimente(array $evenimente): string
{
    $html = '';

    foreach ($evenimente as $ev) {
        /**
         * Unde se află evenimentul în timp. Se întreabă cu funcțiile de peste
         * tot, nu se socotește aici din date: „a început" și „s-a încheiat"
         * sunt scrise o singură dată pe site, iar aici doar se citesc.
         *
         * „Live" e ce a început și încă nu s-a terminat. Nu e o a treia stare
         * în bază — e o clipă între celelalte două, și se vede numai când
         * cineva se uită. Ziua în care are loc se termină la miezul nopții,
         * deci un eveniment de la 18:00 rămâne „Live" toată seara: n-avem ora
         * de sfârșit ca regulă, iar aceea e cea mai cinstită socoteală.
         */
        $anulat   = ($ev['stare_moderare'] ?? '') === 'anulat';
        $incheiat = !empty($ev['incheiat']);

        // „Anulat" bate „încheiat" și „live": e ce s-a întâmplat cu adevărat cu
        // seara aceea, oricât ar arăta ceasul.
        $stare = $anulat
            ? 'anulat'
            : ($incheiat ? 'incheiat' : (evenimentAInceput($ev) ? 'live' : ''));

        $html .= randeazaCartonasEveniment($ev, '', false, $stare);
    }

    return $html;
}

/** Câte evenimente se propun la coada paginii unui eveniment. */
const EVENIMENTE_SUGERATE = 2;

/**
 * Câteva evenimente la întâmplare, pentru „Ar putea să te intereseze".
 *
 * NUMAI CE N-A ÎNCEPUT ÎNCĂ. E o invitație, nu o listă: n-are rost să trimiți
 * pe cineva la o seară care se petrece chiar acum (n-are cum să mai ajungă) și
 * cu atât mai puțin la una încheiată. Aici „a început" ține de ceas, cu ora —
 * nu de ziua care trece, ca la „încheiat".
 *
 * DIN ORICE ORAȘ, dinadins. Pe prima pagină omul alege singur unde se uită;
 * aici e capătul unei pagini pe care a citit-o până la fund, și e locul potrivit
 * să afle că se petrec lucruri și dincolo de orașul lui.
 *
 * La întâmplare, nu „cele mai apropiate": altfel același om, intrând de trei
 * ori pe site, ar vedea de trei ori aceleași două. RAND() e destul — la câteva
 * sute de rânduri costă cât o citire, iar dacă lista crește cândva la zeci de
 * mii, aici se schimbă.
 *
 * $fara e evenimentul de pe pagina căruia se face propunerea: n-are rost să te
 * trimită unde ești deja.
 */
function evenimenteSugerate(int $fara = 0, int $cate = EVENIMENTE_SUGERATE): array
{
    $acum = acum();
    $cate = max(1, min($cate, 12));

    $q = db()->prepare(
        'SELECT e.id, e.titlu, e.slug, e.coperta, e.data_eveniment, e.ora_inceput,
                e.locatie, e.descriere, e.stare_moderare, e.oras, e.participanti_max,
                e.fixat_la,
                c.nume AS categorie, c.slug AS categorie_slug, c.imagine_default,
                c.joc_qr AS categorie_joc_qr,
                ' . CIFRE_CARTONAS . '
           FROM evenimente e
           JOIN categorii c ON c.id = e.categorie_id
          WHERE e.stare_moderare = \'aprobat\'
            AND e.id <> ?
            -- N-a început: ziua ȘI ora, ca la evenimentAInceput(). „incheiat"
            -- nu poate trece de aici, fiindcă starea e cernută mai sus.
            AND CONCAT(e.data_eveniment, \' \', COALESCE(e.ora_inceput, \'00:00:00\')) > ?
          ORDER BY RAND()
          LIMIT ' . $cate
    );

    $q->execute([$fara, $acum]);

    return $q->fetchAll();
}

/**
 * Adresa primei pagini, cu filtrele puse în ea.
 *
 * Se scrie într-un singur loc fiindcă o cer trei: legăturile categoriilor din
 * index.php, JS-ul care rescrie adresa după fiecare filtrare, și oricine ar mai
 * vrea vreodată să trimită pe cineva drept la „Sport în Roman".
 *
 * Ce e gol nu se scrie: „index.php" e mai frumos de citit și de dat mai departe
 * decât „index.php?oras=&categorie=", și înseamnă același lucru.
 */
function adresaFiltrata(string $oras = '', string $categorie = ''): string
{
    $parti = [];

    if ($oras !== '')      { $parti['oras']      = $oras; }
    if ($categorie !== '') { $parti['categorie'] = $categorie; }

    return $parti === [] ? '/index.php' : '/index.php?' . http_build_query($parti);
}

/**
 * Orașul cerut, dacă e unul dintre ale noastre — altfel, toate.
 *
 * Nu se pune în interogare ce a venit de afară: se caută în lista din
 * config.php și se folosește valoarea DE ACOLO. Așa, „Roman'; DROP" nu doar că
 * nu ajunge la bază (o țin oricum interogările pregătite), dar nici nu se
 * strecoară în pagină ca „ai ales orașul Roman'; DROP".
 *
 * Întoarce '' pentru „toate orașele", care e și ce se întâmplă când adresa
 * poartă un oraș care nu există.
 */
function orasulCerut(?string $cerut): string
{
    $cerut = trim((string) $cerut);

    if ($cerut === '') {
        return '';
    }

    foreach (oraseDisponibile() as $oras) {
        if ($oras === $cerut) {
            return $oras;
        }
    }

    return '';
}

/**
 * Categoria cerută, dacă slugul ei există în bază — altfel, toate.
 *
 * Aceeași grijă ca la orașe, doar că lista vine din tabelul `categorii`.
 *
 * CU TOT CU CELE ȚINUTE PENTRU CASĂ. Aici nu se întreabă cine are voie să
 * publice, ci după ce se filtrează — iar „FindMe" se filtrează ca oricare
 * alta. Cu lista strâmtă, butonul din prima pagină ar fi dus la o adresă pe
 * care funcția asta o citea ca „toate": omul apăsa „FindMe" și primea tot.
 */
function categoriaCeruta(?string $cerut): string
{
    $cerut = trim((string) $cerut);

    if ($cerut === '') {
        return '';
    }

    foreach (categoriiEvenimente(true) as $categorie) {
        if ((string) $categorie['slug'] === $cerut) {
            return $cerut;
        }
    }

    return '';
}

/**
 * Evenimentele arătate pe profilul cuiva.
 *
 * $vedeSiCeleInAsteptare — true doar pentru omul care își vede propriul
 * profil. Pentru oricine altcineva, ce n-a trecut încă pe la moderare nu
 * există: altfel ar fi de ajuns să deschizi profilul cuiva ca să vezi ce a
 * trimis, înainte ca noi să fi apucat să citim.
 *
 * Aceeași regulă de „încheiat" ca la limita de postare (filtruNeincheiat), și
 * pentru cele aprobate, și pentru cele în așteptare: mulțimea care te
 * împiedică să postezi altul e chiar mulțimea pe care o vezi pe profil.
 *
 * Anulatele nu apar nicăieri, nici măcar organizatorului — exact ca
 * respinsele... nu: respinsul îl vede omul lui, ca să-l poată corecta. Un
 * eveniment anulat nu mai are ce corectură să primească, deci iese din lista
 * de stări cerute și nu mai apare pentru nimeni. Rămâne în bază pentru staff,
 * până la curățenia finală.
 *
 * Ordinea: întâi cele în așteptare (sunt treaba ta, nu a vizitatorului), apoi
 * după dată, cea mai apropiată prima.
 *
 * ÎN AFARĂ de cele însemnate `ascuns_pe_profil` — anunțurile pe care staff-ul
 * le-a pus în numele orașului, nu ca ieșiri de-ale lui (vezi sql/022). Alea
 * lipsesc de aici și pentru vizitator, ȘI pentru omul care le-a pus: dacă
 * i s-ar arăta doar lui, ar crede de fiecare dată că bifa n-a mers. Anunțul
 * rămâne întreg pe prima pagină și pe pagina lui.
 */
function evenimenteDePeProfil(int $membruId, bool $vedeSiCeleInAsteptare): array
{
    [$unde, $azi] = filtruNeincheiat();

    $stari = $vedeSiCeleInAsteptare ? ['aprobat', 'in_asteptare'] : ['aprobat'];
    $semne = implode(',', array_fill(0, count($stari), '?'));

    $q = db()->prepare(
        'SELECT e.id, e.titlu, e.slug, e.coperta, e.data_eveniment, e.ora_inceput,
                e.locatie, e.descriere, e.stare_moderare, e.oras, e.participanti_max,
                e.fixat_la,
                c.nume AS categorie, c.slug AS categorie_slug, c.imagine_default,
                c.joc_qr AS categorie_joc_qr,
                ' . CIFRE_CARTONAS . '
           FROM evenimente e
           JOIN categorii c ON c.id = e.categorie_id
          WHERE e.membru_id = ?
            AND e.ascuns_pe_profil = 0
            AND e.stare_moderare IN (' . $semne . ')
            -- fără prefix de tabel: „data_eveniment" e doar în evenimente, iar
            -- bucata vine gata scrisă din filtruNeincheiat() — dacă i-am lipi
            -- un „e." în față, s-ar strica în ziua în care regula se schimbă.
            AND ' . $unde . '
          ORDER BY (e.stare_moderare = \'in_asteptare\') DESC,
                   e.data_eveniment, e.ora_inceput
          LIMIT ' . EVENIMENTE_PE_PROFIL_MAX
    );

    $q->execute(array_merge([$membruId], $stari, [$azi]));

    return $q->fetchAll();
}

/**
 * Câte evenimente a organizat omul, cu totul.
 *
 * Numai cele aprobate, dar de oricând: și cele care abia urmează, și cele de
 * acum trei ani. E cifra de pe cartonașul „Evenimente organizate", adică o
 * măsură a cât a făcut cineva pentru oraș — iar ce a făcut nu se șterge când
 * trece ziua. De aceea AICI nu se folosește filtruNeincheiat(): lista de mai
 * jos arată ce urmează, numărul ăsta spune ce a fost.
 *
 * Ce așteaptă moderarea sau a fost respins nu se numără: n-a ajuns niciodată
 * un eveniment adevărat, deci n-are ce căuta într-o cifră pe care o vede toată
 * lumea.
 *
 * Nici ce e ținut deoparte de profil (`ascuns_pe_profil`). Cifra stă chiar
 * deasupra listei; dacă ar număra și ce nu se vede în ea, ar scrie „12" peste
 * nouă cartonașe și n-ar avea nimeni de unde ști de ce.
 */
function cateEvenimenteOrganizate(int $membruId): int
{
    $q = db()->prepare(
        // „incheiat" se numără la fel ca „aprobat": e un eveniment care chiar
        // a avut loc. Fără el, cifra ar scădea în clipa în care organizatorul
        // apasă „Încheie evenimentul" — adică exact când a făcut treaba.
        'SELECT COUNT(*) FROM evenimente
          WHERE membru_id = ? AND ascuns_pe_profil = 0
            AND stare_moderare IN (\'aprobat\', \'incheiat\')'
    );
    $q->execute([$membruId]);

    return (int) $q->fetchColumn();
}

/* ====================== PAGINA UNUI EVENIMENT ========================= */

/**
 * Un eveniment după slugul din adresă, cu categoria și organizatorul lui.
 *
 * Întoarce null dacă slugul nu duce nicăieri. Nu se filtrează aici după starea
 * de moderare: cine are voie să vadă ce hotărăște poateVedeaEvenimentul(), ca
 * regula să stea într-un loc în care se poate citi, nu ascunsă într-un WHERE.
 */
function evenimentDupaSlug(string $slug): ?array
{
    // Alfabetul slugului, așa cum îl scrie slugEveniment(): litere mici, cifre
    // și cratime. Orice altceva nici nu ajunge până la bază.
    if (preg_match('/^[a-z0-9][a-z0-9-]{0,169}$/', $slug) !== 1) {
        return null;
    }

    $q = db()->prepare(
        'SELECT e.*,
                c.nume AS categorie, c.slug AS categorie_slug, c.imagine_default,
                c.joc_qr AS categorie_joc_qr,
                m.permalink AS org_permalink, m.nume AS org_nume, m.prenume AS org_prenume,
                m.poza AS org_poza, m.poza_actualizata_la AS org_poza_actualizata_la,
                m.stare AS org_stare
           FROM evenimente e
           JOIN categorii c ON c.id = e.categorie_id
           JOIN membri m    ON m.id = e.membru_id
          WHERE e.slug = ?
          LIMIT 1'
    );
    $q->execute([$slug]);

    return $q->fetch() ?: null;
}

/**
 * Are voie omul ăsta să vadă evenimentul ăsta?
 *
 * Aprobat — oricine e conectat. Neaprobat (în așteptare sau respins) — doar
 * organizatorul. Simetric dinadins: un anunț respins nu e o rușine de arătat
 * altora, e o treabă între noi și cel care l-a scris.
 *
 * Anulat — oricine, ca unul încheiat. A fost ascuns o vreme, și era greșit: de
 * el atârnă oameni care își făcuseră planuri, iar o pagină care dispare îi
 * lasă cu un link mort și cu întrebarea dacă n-au greșit ei ziua. Acum se
 * deschide, cu banda ei și cu motivul scris de organizator la vedere — cine
 * intră de pe un mesaj primit acum trei zile află pe loc ce s-a întâmplat.
 *
 * Ce NU se mai poate acolo rămâne oprit de evenimentPublicat(), care întoarce
 * „nu" pentru anulat: nimeni nu se mai înscrie și nimeni nu mai scrie
 * comentarii. Se citește tot, nu se mai face nimic.
 *
 * STAFF-UL VEDE TOT. Nu e un drept dat de dragul lui, ci singurul fel în care
 * moderarea poate exista: butoanele „Aprobă" și „Respinge" stau pe pagina
 * evenimentului, iar un anunț în așteptare e tocmai cel care are nevoie de ele.
 * Cât timp pagina nu se deschidea decât pentru organizator, omul de casă n-avea
 * de unde să apese nimic — anunțurile care așteptau erau invizibile exact
 * pentru cine trebuia să le citească.
 *
 * $membruId e 0 pentru cine nu e conectat, deci nu poate nimeri peste
 * membru_id-ul nimănui.
 */
function poateVedeaEvenimentul(array $eveniment, int $membruId, bool $eStaff = false): bool
{
    if ($eStaff) {
        return true;
    }

    // Anulat, dar public: pagina rămâne de citit pentru cine avea bilet la ea.
    if ($eveniment['stare_moderare'] === 'anulat') {
        return true;
    }

    if ((int) $eveniment['membru_id'] === $membruId && $membruId > 0) {
        return true;
    }

    return evenimentPublicat($eveniment);
}

/**
 * Adresa paginii unui eveniment. UN SINGUR LOC care o știe.
 *
 * `/eveniment/mergem-la-alergat`, nu `event.php?slug=mergem-la-alergat`.
 *
 * DE CE. Adresa unui eveniment e singura de pe site pe care oamenii o pun în
 * mesaje, o lipesc pe Facebook și o citesc unii altora la telefon. „Intră pe
 * pulsulorasului.ro slash eveniment slash mergem la alergat" se poate spune;
 * „event punct php întrebare slug egal" nu se poate. Iar în lista de rezultate
 * a lui Google, adresa se vede sub titlu.
 *
 * CUM MERGE. `.htaccess` din rădăcină rescrie `/eveniment/<slug>` în
 * `event.php?slug=<slug>`, iar event.php nu știe nimic despre asta: pentru el
 * cererea arată exact ca înainte. Fără mod_rewrite, adresa veche merge mai
 * departe — de aceea event.php nu s-a schimbat, doar trimite de la ea la cea
 * nouă (o redirecționare 301, ca să existe o singură adresă adevărată).
 *
 * `rawurlencode`, nu `urlencode`: al doilea scrie spațiul ca „+", ceea ce
 * într-o CALE înseamnă un plus adevărat, nu un spațiu. Slugurile n-au spații
 * (vezi slugEveniment), dar regula asta n-are voie să atârne de altă regulă.
 */
function urlEveniment(string $slug): string
{
    return '/eveniment/' . rawurlencode($slug);
}

/**
 * Adresa profilului cuiva. Tot un singur loc, din același motiv.
 *
 * Aici rămâne forma veche, cu întrebare: un permalink e zece semne la
 * întâmplare, deci n-are ce să câștige din a fi scos în cale. Funcția există
 * ca cele nouă locuri care o scriau de mână să nu mai poată ajunge să spună
 * lucruri deosebite — și ca ziua în care se schimbă și ea să fie o singură
 * linie.
 */
function urlProfil(string $permalink): string
{
    return '/profil.php?m=' . rawurlencode($permalink);
}

/**
 * Cartonașul unui eveniment, așa cum arată peste tot: poză, categorie, titlu,
 * început de text, ziua și locul.
 *
 * A fost scris o vreme de-a dreptul în profil.php, într-un `foreach`. Când a
 * venit și lista de istoric, tot de acolo, ar fi ajuns scris de două ori pe
 * aceeași pagină — iar două bucăți de HTML care trebuie să arate la fel încep
 * să difere de la prima corectură. Acum e într-un loc.
 *
 * Întoarce HTML, nu-l tipărește: așa poate fi pus și într-o listă, și într-un
 * răspuns JSON, dacă va fi vreodată nevoie.
 *
 * $insigne e HTML gata făcut, lipit peste poză, în dreapta sus: „Organizator",
 * „Absent". Cine cheamă funcția știe ce are de spus despre eveniment; funcția
 * asta știe doar cum arată un cartonaș.
 *
 * $ascuns pune clasa `.ascuns`, pentru cartonașele care intră în pagină dar nu
 * se văd până la „Vezi mai mult". Tot ce e de arătat pleacă în aceeași pagină:
 * butonul doar dă la o parte, nu cere nimic de la server. (Pe prima pagină nu
 * se folosește: acolo teancurile vin din bază, unul câte unul.)
 *
 * $stare îmbracă altfel cartonașul, după unde se află evenimentul în timp:
 *
 *   'incheiat' — a trecut: poza se stinge, în colț scrie „Încheiat"
 *   'anulat'   — n-a mai avut loc: la fel de stins, dar scrie „Anulat"
 *   'live'     — se petrece chiar acum: în colț clipește „Live"
 *   ''         — nimic, ca la un anunț care abia urmează
 *
 * „Anulat" arată ca „Încheiat", dinadins: amândouă sunt seri care nu mai
 * urmează, iar deosebirea dintre ele e un cuvânt, nu o culoare. O etichetă
 * roșie, de alarmă, ar fi strigat pe prima pagină la un anunț de acum trei
 * săptămâni — pe care oricum nu-l mai așteaptă nimeni.
 *
 * Nu se ghicește din rând, ci se spune. Pe prima pagină e ceva de deosebit
 * între ce urmează, ce e în toi și ce a fost; într-o listă unde totul e la fel
 * (o previzualizare, de pildă) un semn pe fiecare cartonaș ar fi doar zgomot.
 *
 * CIFRELE DIN COLȚUL DE JOS — câți vin și câte comentarii sunt — se scriu
 * numai dacă rândul le aduce cu el (`cati_participanti`, `cate_comentarii`).
 * Așa, o listă care nu le-a cerut din bază nu ajunge să arate zerouri
 * inventate; vezi cifreleCartonasului(), care hotărăște și cine le vede: la o
 * vânătoare „FindMe" participanții nu se scriu deloc.
 */
function randeazaCartonasEveniment(
    array $ev,
    string $insigne = '',
    bool $ascuns = false,
    string $stare = ''
): string {
    $inAsteptare = ($ev['stare_moderare'] ?? '') === 'in_asteptare';
    $incheiat    = $stare === 'incheiat';
    $anulat      = $stare === 'anulat';
    $live        = $stare === 'live';

    $fixat = esteFixat($ev);

    $clase = 'card';
    if ($inAsteptare)          { $clase .= ' card--in-asteptare'; }
    // Aceeași clasă pentru amândouă: stingerea pozei e la fel, se schimbă doar
    // cuvântul din colț.
    if ($incheiat || $anulat)  { $clase .= ' card--incheiat'; }
    if ($live)                 { $clase .= ' card--live'; }
    // Piuneza se pune PESTE stingere, nu în locul ei: un anunț încheiat, dar
    // fixat, rămâne stins ca oricare încheiat — doar că are chenar și stă
    // primul. Altfel n-ar mai fi „încheiat", ar fi „altceva".
    if ($fixat)                { $clase .= ' card--fixat'; }
    if ($ascuns)               { $clase .= ' ascuns'; }

    $coperta = urlCoperta($ev['coperta'] ?? null);

    // Imaginea implicită a categoriei, dacă anunțul n-are copertă a lui.
    // urlImagineCategorie() se uită și pe disc: fișierele se urcă de mână, iar
    // unele lipsesc încă (vezi roadmap-ul din CLAUDE.md).
    if ($coperta === '') {
        $coperta = urlImagineCategorie($ev['imagine_default'] ?? null);
    }

    $adresa = h(urlEveniment((string) $ev['slug']));

    $poza = $coperta !== ''
        ? '<img src="' . h($coperta) . '" alt="" width="1600" height="900"'
          . ' loading="lazy" decoding="async">'
        : '';

    /**
     * Starea anunțului rămâne separată de $insigne: e ceva despre eveniment,
     * nu despre omul de pe profilul căruia se uită cartonașul.
     *
     * Cele două nu se ceartă niciodată pentru colț: un eveniment în așteptare
     * se vede doar pe profilul organizatorului, iar acolo nu se pune semnul de
     * încheiat.
     */
    $semn = '';

    if ($inAsteptare) {
        $semn = '<span class="card__stare">În așteptare de aprobare</span>';
    } elseif ($anulat) {
        $semn = '<span class="card__stare card__stare--incheiat">Anulat</span>';
    } elseif ($incheiat) {
        $semn = '<span class="card__stare card__stare--incheiat">Încheiat</span>';
    } elseif ($live) {
        // Punctul care pulsează e același cu cel din „Live din oraș", de sus.
        $semn = '<span class="card__stare card__stare--live">'
              . '<span class="pulse-dot" aria-hidden="true"></span>Live</span>';
    }

    /**
     * Piuneza, în colțul de JOS-STÂNGA — singurul rămas liber pe poză.
     *
     * Sus-stânga e categoria, sus-dreapta e starea („Încheiat", „Anulat",
     * „Live"), jos-dreapta sunt cifrele. Toate patru trebuie să încapă odată:
     * un anunț fixat poate fi foarte bine unul anulat, și tocmai atunci
     * contează să se vadă și una, și alta.
     *
     * Fără cuvânt, doar semnul: „Fixat" scris lângă el n-ar spune nimic
     * cititorului obișnuit, care nu știe și nu trebuie să știe că există o
     * unealtă de casă. Ce spune piuneza, spune prin locul din listă. `title` e
     * acolo pentru cine se întreabă totuși.
     */
    $piuneza = $fixat
        ? '<span class="card__pin" title="Anunț fixat de echipă">'
          . '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
          . '<path d="M9 3h6l-1 6 4 3v2H6v-2l4-3-1-6Z"/><path d="M12 14v7"/>'
          . '</svg></span>'
        : '';

    /**
     * ORAȘUL, între dată și locul anume.
     *
     * Ordinea nu e întâmplătoare: se strânge dinspre larg spre îngust — când,
     * în ce oraș, și abia apoi unde anume. „30 aug · Roman · Piața Sfatului" se
     * citește ca o adresă spusă cu voce tare; invers, locul fără oraș te pune
     * să te întrebi unde e Piața Sfatului.
     *
     * Se scrie la TOATE cartonașele, oriunde ar fi ele. Pe prima pagină se
     * poate cerne după oraș, deci acolo pare de prisos — dar cine intră de pe
     * un mesaj primit, sau se uită pe profilul cuiva, sau la „Ar putea să te
     * intereseze", n-a cernut nimic. Iar când orașele vor fi mai multe, o listă
     * fără ele ar fi fost o listă din care lipsește tocmai ce alege omul.
     */
    $oras = trim((string) ($ev['oras'] ?? ''));

    $undeva = $oras !== ''
        ? '<span class="dot" aria-hidden="true"></span><span>' . h($oras) . '</span>'
        : '';

    return '<article class="' . $clase . '">'
         . '<a class="card__media" href="' . $adresa . '">'
         . $poza
         . '<span class="card__tag">' . h((string) ($ev['categorie'] ?? '')) . '</span>'
         . $piuneza
         . $semn
         . $insigne
         . cifreleCartonasului($ev)
         . '</a>'
         . '<div class="card__body">'
         . '<h3 class="card__title"><a href="' . $adresa . '">' . h((string) $ev['titlu']) . '</a></h3>'
         . '<p class="card__excerpt">' . h(inceputDeText((string) $ev['descriere'])) . '</p>'
         . '<div class="card__meta">'
         . '<time datetime="' . h((string) $ev['data_eveniment']) . '">'
         . h(dataScurta($ev['data_eveniment'])) . '</time>'
         . $undeva
         . '<span class="dot" aria-hidden="true"></span>'
         . '<span>' . h(inceputDeText((string) $ev['locatie'], 48)) . '</span>'
         . '</div></div></article>';
}

/**
 * Cele două subcereri care aduc cifrele de pe cartonaș.
 *
 * O bucată de SQL scrisă o dată și lipită în fiecare listă care desenează
 * cartonașe — prima pagină, profilul, istoricul. Scrisă de patru ori, ar fi
 * ajuns să numere patru lucruri ușor diferite, iar același eveniment ar fi
 * arătat „7" într-un loc și „8" în altul.
 *
 * PARTICIPANȚII se numără exact ca pe pagina evenimentului: numai conturile
 * ACTIVE (vezi INTERESE_DOAR_ACTIVI din inc/interese.php). Cine și-a șters
 * contul nu mai ține un loc, deci n-are ce căuta nici în cifra de pe cartonaș
 * — altfel omul ar fi văzut „12 / 12" pe listă și un loc liber pe pagină.
 *
 * COMENTARIILE se numără ca în numaraComentarii(): fără cele golite, care sunt
 * pietre de mormânt, nu vorbe de citit.
 *
 * Se aduc AMÂNDOUĂ, oricare ar fi evenimentul. Care dintre ele se și scrie pe
 * cartonaș e treaba lui cifreleCartonasului() — la o vânătoare „FindMe", de
 * pildă, participanții nu se arată. Subcererea rămâne totuși, ca bucata asta
 * de SQL să nu ajungă să se uite la ce fel de eveniment aduce: e o listă, nu o
 * hotărâre.
 *
 * Cere ca tabelul `evenimente` să fie aliasul `e` în cererea care o folosește —
 * așa e în toate cele de aici.
 */
const CIFRE_CARTONAS = '
                (SELECT COUNT(*)
                   FROM interese_evenimente i
                   JOIN membri mi ON mi.id = i.membru_id AND mi.stare = \'activ\'
                  WHERE i.eveniment_id = e.id AND i.stare = \'participant\') AS cati_participanti,
                (SELECT COUNT(*)
                   FROM comentarii cm
                  WHERE cm.eveniment_id = e.id AND cm.sters = 0) AS cate_comentarii';

/**
 * Cifrele din colțul de jos al pozei: câți vin și câte comentarii sunt.
 *
 * DE CE PE POZĂ, și nu sub titlu, lângă dată și loc: acolo jos e singurul loc
 * gol al cartonașului, iar amândouă răspund la aceeași întrebare tăcută pe care
 * și-o pune omul când trece cu ochii peste o listă — „se duce cineva, se
 * vorbește ceva despre asta?". Sub titlu ar fi împins data și locul pe al
 * doilea rând, la fiecare cartonaș.
 *
 * PARTICIPANȚII, în două feluri:
 *
 *   „7"      — când nu s-a pus nicio limită. Numărul e o veste bună, atât;
 *   „7 / 12" — când există un număr maxim. Atunci cifra singură n-ar spune
 *              nimic: șapte inși e mult la o partidă de tenis și puțin la un
 *              concert. Cu numitorul alături, omul vede dintr-o privire dacă
 *              mai are unde să intre.
 *
 * LA O VÂNĂTOARE „FINDME" NU SE SCRIU DELOC, oricât ar aduce rândul din bază.
 * Acolo nu se înscrie nimeni: caseta cu „Mă interesează / Voi participa" nici
 * nu există pe pagina anunțului (vezi $eVanatoare din event.php), deci lista de
 * participanți e goală prin însăși alcătuirea jocului. Un „0" lângă un omuleț
 * n-ar fi spus „încă nu s-a înscris nimeni", ci „nu se duce nimeni" — și ar fi
 * fost un neadevăr despre singurul fel de eveniment la care nimeni n-are unde
 * să se ducă. Rămân comentariile, care la o vânătoare chiar spun ceva: acolo
 * lumea se întreabă unde n-a căutat încă.
 *
 * La orice alt eveniment se scriu ca mai înainte.
 *
 * Nu se scrie nimic dacă rândul n-a adus cifrele din bază. Un cartonaș
 * desenat dintr-un rând care n-a cerut subcererile ar fi arătat „0 / 0" — un
 * neadevăr care pare o socoteală. De aceea se cere prezența CHEII, nu o
 * valoare adevărată: zero participanți e un răspuns cinstit și trebuie arătat.
 *
 * `aria-label` pe fiecare cifră, fiindcă iconița singură nu spune nimic unui
 * cititor de ecran, iar „7 / 12" citit ca atare n-ar avea niciun înțeles.
 */
function cifreleCartonasului(array $ev): string
{
    /**
     * Steagul călătorește cu rândul evenimentului (`categorie_joc_qr`), din
     * `categorii.joc_qr` — niciodată din numele sau slugul categoriei. Aceeași
     * întrebare o pune și pagina anunțului, prin esteJocQr().
     *
     * Se citește de-a dreptul, nu prin funcția aceea: ea stă în
     * inc/coduri-qr.php, care cere fișierul ăsta, iar două fișiere care se cer
     * unul pe altul de la început ar fi o buclă. E o singură comparație, și e
     * scrisă chiar lângă lămurirea de ce.
     */
    $eVanatoare = (int) ($ev['categorie_joc_qr'] ?? 0) === 1;

    $areParticipanti = !$eVanatoare && array_key_exists('cati_participanti', $ev);
    $areComentarii   = array_key_exists('cate_comentarii', $ev);

    if (!$areParticipanti && !$areComentarii) {
        return '';
    }

    $bucati = '';

    if ($areParticipanti) {
        $cati  = (int) $ev['cati_participanti'];
        $maxim = isset($ev['participanti_max']) && $ev['participanti_max'] !== null
            ? (int) $ev['participanti_max']
            : 0;

        $text  = $maxim > 0 ? $cati . ' / ' . $maxim : (string) $cati;
        $vorba = $maxim > 0
            ? $cati . ' din ' . $maxim . ' locuri ocupate'
            : ($cati === 1 ? 'o persoană participă' : $cati . ' persoane participă');

        $bucati .= '<span class="card__cifra" aria-label="' . h($vorba) . '">'
                 . '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
                 . '<circle cx="9" cy="8" r="3.4"/>'
                 . '<path d="M2.8 19.5c.5-3.2 3.1-5.2 6.2-5.2s5.7 2 6.2 5.2"/>'
                 . '<path d="M16.4 5.2a3.4 3.4 0 0 1 0 6.5"/>'
                 . '<path d="M18.2 14.6c1.7.7 2.8 2.4 3 4.4"/>'
                 . '</svg>' . h($text) . '</span>';
    }

    if ($areComentarii) {
        $cate  = (int) $ev['cate_comentarii'];
        $vorba = $cate === 1 ? 'un comentariu' : $cate . ' comentarii';

        $bucati .= '<span class="card__cifra" aria-label="' . h($vorba) . '">'
                 . '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
                 . '<path d="M20.5 12.5c0 4-3.8 7-8.5 7-1 0-2-.1-2.9-.4L4 21l1.3-3.4'
                 . 'A7.4 7.4 0 0 1 3.5 12.5c0-4 3.8-7 8.5-7s8.5 3 8.5 7Z"/>'
                 . '</svg>' . $cate . '</span>';
    }

    return '<span class="card__cifre">' . $bucati . '</span>';
}

/** Adresa formularului, în modul în care editează un eveniment anume. */
function urlEditareEveniment(string $slug): string
{
    return '/adauga_eveniment.php?slug=' . rawurlencode($slug);
}

/**
 * Adresa formularului care face încă unul la fel din evenimentul ăsta.
 *
 * Alt parametru decât la editare, dinadins: `slug=` înseamnă „schimbă rândul
 * ăsta", `remake=` înseamnă „scrie unul nou, pornind de la el". Cu același
 * nume, o greșeală de o literă ar fi rescris anunțul vechi în loc să facă unul
 * nou — și nimeni n-ar fi văzut până a doua zi.
 */
function urlRefacereEveniment(string $slug): string
{
    return '/adauga_eveniment.php?remake=' . rawurlencode($slug);
}

/**
 * Evenimentul pe care omul ăsta are voie să-l editeze, sau null.
 *
 * Regula stă aici, într-un singur loc, fiindcă o cer două fișiere: pagina cu
 * formularul (ca să știe ce să precompleteze) și punctul de intrare care
 * primește ce s-a scris. Dacă ar fi scrisă de două ori, ar fi de ajuns ca una
 * să rămână în urmă pentru ca cineva să poată edita evenimentul altcuiva.
 *
 * Editează doar organizatorul — oricare ar fi starea de moderare. Un anunț
 * respins e tocmai cel care are cea mai mare nevoie de o corectură.
 *
 * Singura stare care iese din regulă e „anulat": acolo nu mai e nimic de
 * corectat. Fără rândul ăsta, o editare ar întoarce evenimentul în
 * „in_asteptare" (așa face actualizeazaEveniment) și l-ar readuce la viață pe
 * lângă anularea pe care organizatorul tocmai o anunțase.
 */
function evenimentDeEditat(string $slug, int $membruId): ?array
{
    if ($membruId <= 0) {
        return null;
    }

    $eveniment = evenimentDupaSlug($slug);

    if ($eveniment === null || (int) $eveniment['membru_id'] !== $membruId) {
        return null;
    }

    /**
     * Anulat sau încheiat: nu mai e nimic de corectat.
     *
     * Fără rândurile astea, o editare ar întoarce evenimentul în
     * „in_asteptare" (așa face actualizeazaEveniment) și l-ar readuce la viață
     * pe lângă hotărârea pe care organizatorul tocmai o luase.
     */
    if (in_array($eveniment['stare_moderare'], ['anulat', 'incheiat'], true)) {
        return null;
    }

    return $eveniment;
}

/* ============================= SALVAREA =============================== */

/**
 * Cu ce stare intră un anunț la publicare sau la o corectură.
 *
 * Pentru toată lumea, „in_asteptare": nimic nu apare pe site până nu trece pe
 * la un om. Pentru staff, „aprobat" de-a dreptul — omul de casă E cel pe la
 * care ar fi trecut, iar un anunț care se așteaptă pe el însuși n-ar face
 * decât să adauge un drum în plus, pe pagina anunțului, ca să apese „Aprobă"
 * la ce tocmai a scris.
 *
 * ȘI LA EDITARE, nu doar la publicare. Altfel, o virgulă îndreptată de staff
 * în propriul anunț l-ar fi scos de pe site până la o a doua apăsare.
 *
 * Scrisă ca funcție, nu ca un `if` în fiecare loc, fiindcă o cer trei:
 * salvarea, actualizarea și pagina care alege ce scrie pe buton. Un `if`
 * copiat de trei ori e un `if` care într-o zi va spune altceva într-unul din
 * ele.
 */
function starePentruPublicare(bool $eStaff): string
{
    return $eStaff ? 'aprobat' : 'in_asteptare';
}

/**
 * Ține anunțul ăsta deoparte de profilul celui care l-a pus?
 *
 * Bifa se vede și se ascultă NUMAI de la staff. Pentru oricine altcineva
 * răspunsul e „nu", oricât ar scrie în cererea trimisă: caseta nici nu se
 * desenează în formularul lui, deci un „da" venit de acolo nu poate fi decât
 * scris de mână.
 *
 * Din bază intră ca 0/1; din formular vine ca „1" sau lipsește cu totul — o
 * bifă nebifată nu ajunge deloc în date, iar absența ei E răspunsul „nu".
 */
function ascundePeProfil(array $date, bool $eStaff): bool
{
    if (!$eStaff) {
        return false;
    }

    $valoare = $date['ascuns_pe_profil'] ?? null;

    return !empty($valoare) && $valoare !== 'false' && $valoare !== '0';
}

/**
 * Se trece organizatorul singur pe lista de participanți?
 *
 * De obicei, da: cine pune o ieșire la cale vine la ea, iar rândul se scrie
 * fără să apese nimeni nimic.
 *
 * La un anunț ținut deoparte de profil, NU. Acolo omul de casă nu e cel care
 * iese în oraș, e cel care a scris anunțul orașului — la târgul de Crăciun nu
 * „participă" el, îl anunță. Trecut pe listă, ar fi apărut printre chipurile de
 * sub „Cine vine", ar fi umflat numărul cu unu și ar fi putut fi notat de
 * participanți la sfârșit, ca și cum ar fi fost acolo cu ei.
 *
 * Poate să se înscrie oricând singur, de pe pagina evenimentului, ca oricine
 * altcineva — dacă chiar se duce.
 *
 * O funcție, nu un `if` copiat, fiindcă întrebarea se pune în două locuri
 * depărtate: la salvare (salveazaEveniment) și la aprobarea unui anunț care
 * așteptase (api/modereaza-eveniment.php). Scrisă de două ori, ar fi ajuns să
 * spună două lucruri.
 */
function organizatorulVineSingur(bool $ascunsPeProfil): bool
{
    return !$ascunsPeProfil;
}

/**
 * Titlul fără coada de numărare: „Fotbal #3" → „Fotbal".
 *
 * Se taie doar o coadă care arată EXACT așa — spațiu, diez, cifre, capăt de
 * text. „Sala #3 la ora 8" rămâne întreg, fiindcă acolo diezul e parte din ce
 * a vrut omul să spună, nu o numărătoare pusă de noi.
 */
function titluFaraNumar(string $titlu): string
{
    return (string) preg_replace('/\s*#\d+$/u', '', trim($titlu));
}

/**
 * Titlul cu care intră în bază un eveniment NOU: al doilea cu același nume
 * primește „ #2", al treilea „ #3", și tot așa.
 *
 * DOAR ÎN DREPTUL ACELUIAȘI OM. Doi vecini care pun amândoi „Fotbal în seara
 * asta" scriu despre două seri deosebite, iar un „#2" pus celui de-al doilea
 * l-ar fi făcut să pară continuarea unui anunț pe care nu l-a scris.
 *
 * Se numără din TOATE evenimentele lui, oricare le-ar fi starea — și cele
 * încheiate, și cele anulate. Tocmai alea sunt „din trecut": dacă anunțul de
 * săptămâna trecută s-ar fi scos din socoteală, cel de azi ar fi purtat același
 * nume cu el, iar pe profil ar fi stat două rânduri care nu se pot deosebi.
 *
 * COADA SE IA ÎNTÂI JOS. Cine scrie el însuși „Fotbal #2" nu cere un al doilea
 * anunț numit așa: cere un „Fotbal", iar numărul îl punem noi. Altfel s-ar fi
 * ajuns la „Fotbal #2 #2".
 *
 * Numărul următor se ia din CEL MAI MARE de până acum, nu din câte rânduri
 * sunt: dacă „Fotbal #2" se șterge vreodată de mână din phpMyAdmin, al treilea
 * anunț trebuie să rămână „#3" — două anunțuri cu același nume, la aceeași
 * persoană, sunt tocmai lucrul de care ne ferim.
 *
 * NU se cheamă la editare (actualizeazaEveniment). Titlul e deja numerotat, iar
 * o a doua trecere l-ar fi urcat cu unu la fiecare virgulă îndreptată.
 */
function titluCuNumar(int $membruId, string $titlu): string
{
    $baza = titluFaraNumar($titlu);

    if ($baza === '' || $membruId <= 0) {
        return trim($titlu);
    }

    /**
     * `%` și `_` din titlu ar fi fost jokeri în LIKE: „100% distracție" ar fi
     * potrivit orice titlu care începe cu „100". De aceea se scapă cu `\`, și
     * se spune limpede lui MySQL că `\` e semnul de scăpare.
     */
    $pentruLike = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $baza);

    $q = db()->prepare(
        'SELECT titlu FROM evenimente
          WHERE membru_id = ?
            AND (titlu = ? OR titlu LIKE ? ESCAPE \'\\\\\')'
    );
    $q->execute([$membruId, $baza, $pentruLike . ' #%']);

    $celMaiMare = 0;

    foreach ($q->fetchAll() as $rand) {
        $vechi = (string) $rand['titlu'];

        // Aceeași bază, alt număr? LIKE-ul de mai sus prinde și „Fotbal #2x",
        // pe care noi nu l-am scris niciodată — se cerne aici.
        if ($vechi === $baza) {
            $celMaiMare = max($celMaiMare, 1);
            continue;
        }

        if (preg_match('/^' . preg_quote($baza, '/') . ' #(\d+)$/u', $vechi, $m) === 1) {
            $celMaiMare = max($celMaiMare, (int) $m[1]);
        }
    }

    return $celMaiMare === 0 ? $baza : $baza . ' #' . ($celMaiMare + 1);
}

/**
 * Scrie evenimentul în bază. $curat vine gata verificat din verificaEveniment().
 *
 * Slugul se încearcă de câteva ori: coada lui e întâmplătoare, deci o
 * potrivire e foarte puțin probabilă, dar „puțin probabil" nu e „imposibil",
 * iar indexul unic din bază e cel care are ultimul cuvânt.
 *
 * Înapoi vine slugul, nu id-ul: după salvare, singurul lucru de care are
 * nevoie cine a chemat funcția e adresa paginii, ca omul să se poată duce
 * direct la evenimentul lui.
 *
 * $eStaff hotărăște două lucruri, amândouă prin funcțiile de mai sus: cu ce
 * stare intră anunțul (starePentruPublicare) și dacă bifa de ținut deoparte
 * are vreun cuvânt (ascundePeProfil). Se primește gata calculat, nu se
 * întreabă aici: cine e omul se știe la intrarea în API, nu în stratul care
 * scrie în bază.
 */
function salveazaEveniment(
    int $membruId,
    array $curat,
    ?string $coperta,
    bool $eStaff = false,
    bool $ascunsPeProfil = false
): string {
    /**
     * „Fotbal în seara asta", „Fotbal în seara asta #2", „… #3".
     *
     * Se face AICI, la scriere, nu în formular: omul scrie titlul pe care îl
     * are în cap, iar coada o pune site-ul. Vezi titluCuNumar().
     */
    $curat['titlu'] = titluCuNumar($membruId, (string) $curat['titlu']);

    $sql = 'INSERT INTO evenimente
                (membru_id, categorie_id, titlu, slug, coperta,
                 data_eveniment, ora_inceput, ora_sfarsit, oras, locatie,
                 cost, varsta_minima, participanti_min, participanti_max,
                 descriere, gen_participanti, stare_moderare, ascuns_pe_profil,
                 creat_la, actualizat_la)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';

    for ($incercare = 1; $incercare <= 5; $incercare++) {
        $slug = slugEveniment($curat['titlu']);

        try {
            $q = db()->prepare($sql);
            $q->execute([
                $membruId,
                $curat['categorie_id'],
                $curat['titlu'],
                $slug,
                $coperta,
                $curat['data_eveniment'],
                $curat['ora_inceput'],
                $curat['ora_sfarsit'],
                $curat['oras'],
                $curat['locatie'],
                $curat['cost'],
                $curat['varsta_minima'],
                $curat['participanti_min'],
                $curat['participanti_max'],
                $curat['descriere'],
                $curat['gen_participanti'],
                // Nimic nu apare pe site până nu trece pe la om — în afară de
                // ce pune chiar omul acela. Vezi starePentruPublicare().
                starePentruPublicare($eStaff),
                $ascunsPeProfil ? 1 : 0,
                acum(),
                acum(),
            ]);

            $idNou = (int) db()->lastInsertId();
        } catch (PDOException $e) {
            // 23000 = a dat de un index unic. Singurul de aici e slugul.
            if ($e->getCode() !== '23000' || $incercare === 5) {
                throw $e;
            }

            // Slugul s-a lovit de altul; se încearcă cu altă coadă.
            continue;
        }

        /**
         * Cine pune un eveniment la cale vine la el. Rândul se scrie singur,
         * fără ca organizatorul să apese ceva — se poate retrage mai târziu,
         * ca oricine altcineva.
         *
         * În afară de anunțurile ținute deoparte de profil, unde omul de casă
         * n-a scris o ieșire de-a lui, ci una a orașului — vezi
         * organizatorulVineSingur().
         *
         * AFARĂ din `try`, dinadins. Înăuntru, o eroare de aici cu codul
         * 23000 (o cheie străină, de pildă) ar fi fost luată drept „slugul s-a
         * lovit de altul" și ar fi pornit încă o rundă — adică un al doilea
         * eveniment, din senin.
         *
         * Funcția stă în inc/interese.php, care cere fișierul ăsta; de-aia se
         * cere aici, la folosire, și nu sus, printre celelalte: două fișiere
         * care se cer unul pe altul de la început ar fi o buclă.
         */
        if (organizatorulVineSingur($ascunsPeProfil)) {
            require_once __DIR__ . '/interese.php';
            faOrganizatorulParticipant($idNou, $membruId);
        }

        return $slug;
    }

    return '';
}

/**
 * Cât timp după ora de început se mai poate anula un eveniment.
 *
 * O oră. Nu e o cifră rotundă aleasă la întâmplare: e răstimpul în care se
 * hotărăște, de fapt, dacă o ieșire are loc sau nu. Plouă cu găleata, au venit
 * doi oameni din doisprezece, s-a închis terasa — toate se văd în primul sfert
 * de oră DE LA ora scrisă în anunț, nu înainte de ea. O anulare oprită fix la
 * minutul de început îl lăsa pe organizator cu un anunț care spune că e ceva,
 * exact în seara în care nu mai era.
 *
 * După ora asta nu se mai poate: cine avea de venit a venit sau nu, iar o
 * veste de „nu mai are loc" trimisă la 20:30 pentru ceva de la 19:00 nu mai
 * ajută pe nimeni. Ce rămâne atunci e „Încheie evenimentul", care spune
 * adevărul de după — a avut loc, s-a terminat — și nu trimite niciun e-mail.
 */
const MINUTE_ANULARE_DUPA_INCEPUT = 60;

/**
 * Se mai poate anula evenimentul ăsta?
 *
 * Da, până la o oră DUPĂ ora de început (vezi MINUTE_ANULARE_DUPA_INCEPUT).
 * Nu, dacă e deja anulat sau încheiat: la primul nu mai e ce anula, la al
 * doilea organizatorul a spus deja că a avut loc.
 *
 * ATENȚIE la ce înseamnă „început": ora de START din anunț, nu cea de sfârșit.
 * Un eveniment care ține de la 18:00 până seara târziu se poate anula până la
 * 19:00, nu până la 23:00.
 *
 * Ceasul e al PHP-ului, ca peste tot pe site. Aici se socotește din data și ora
 * evenimentului, nu prin evenimentAInceput(): acela răspunde „da" pentru orice
 * eveniment încheiat, oricât ar arăta ceasul, iar noi avem nevoie de clipa
 * adevărată ca să putem adăuga ora de răgaz peste ea.
 *
 * Proprietatea NU se verifică aici: o ține evenimentDeEditat() la editare, și
 * o întrebare despre organizator pe pagina evenimentului. Funcția asta
 * răspunde la o singură întrebare, ca să poată fi pusă din trei locuri — cele
 * două pagini cu buton și api/anuleaza-eveniment.php, care face fapta.
 */
function poateFiAnulat(array $eveniment): bool
{
    if (in_array($eveniment['stare_moderare'] ?? '', ['anulat', 'incheiat'], true)) {
        return false;
    }

    $inceput = momentulInceperii($eveniment);

    // Fără dată în rând n-avem de unde ști dacă a început; nu-i luăm dreptul.
    if ($inceput === null) {
        return true;
    }

    return time() <= $inceput + MINUTE_ANULARE_DUPA_INCEPUT * 60;
}

/**
 * Se mai poate SCHIMBA evenimentul ăsta?
 *
 * Nu, din clipa în care a început. Ce era de îndreptat se îndrepta înainte;
 * după ora de start, oamenii sunt deja pe drum, iar o schimbare de loc sau de
 * oră le-ar ajunge sub ochi prea târziu ca să mai folosească cuiva. Ce rămâne
 * de făcut atunci se face de pe pagina evenimentului: „Anulează evenimentul"
 * încă o oră (poateFiAnulat) și „Încheie evenimentul" cât timp anunțul mai e
 * în picioare (poateFiIncheiat — „oricând" scria aici, și tocmai de aceea
 * butonul de încheiere a ajuns să stea și pe un anunț anulat).
 *
 * DE CE NU E PUSĂ ÎN evenimentDeEditat(). Fiindcă de acela atârnă și
 * anularea: api/anuleaza-eveniment.php îl cheamă ca să afle al cui e anunțul.
 * Închisă acolo, regula asta ar fi luat înapoi tocmai ora de răgaz de după
 * început, care e rostul întreg al butonului de anulare de pe pagină.
 *
 * O întrebare, un singur loc — o pun trei: adauga_eveniment.php (nu deschide
 * formularul), api/eveniment.php (nu primește schimbarea) și event.php (nu
 * desenează butonul „Editează").
 */
function poateFiEditat(array $eveniment): bool
{
    if (in_array($eveniment['stare_moderare'] ?? '', ['anulat', 'incheiat'], true)) {
        return false;
    }

    return !evenimentAInceput($eveniment);
}

/**
 * Se mai poate ÎNCHEIA evenimentul ăsta?
 *
 * Fratele care lipsea dintre poateFiAnulat() și poateFiEditat(), iar lipsa lui
 * s-a văzut: pe pagina unui anunț ANULAT, butonul „Încheie evenimentul" stătea
 * mai departe acolo, viu. Întrebarea era scrisă de mână în event.php și avea
 * doi termeni din trei — „e al lui" și „a început" — dar nu și pe cel care
 * spune că anunțul mai e în picioare. Un eveniment anulat nu se încheie: a
 * încetat deja, altfel. Cele două stări nu se pun una peste alta.
 *
 * TREI CONDIȚII:
 *
 *   1. e PUBLICAT — deci nu în așteptare, nu respins, ȘI NU ANULAT;
 *   2. nu s-a încheiat deja — nici prin apăsare, nici prin ziua trecută;
 *   3. a început — ziua ȘI ora. Ce nu s-a petrecut încă nu se „încheie": ar
 *      ieși un anunț care arată ca și cum ar fi avut loc, deși n-a fost nimeni
 *      nicăieri. Ce vrea organizatorul atunci se cheamă anulare, are butonul
 *      lui și cere un motiv, fiindcă oamenii înscriși trebuie înștiințați.
 *
 * PROPRIETATEA NU SE VERIFICĂ AICI, ca la poateFiAnulat() și poateFiRefacut():
 * o pune cine cheamă. Așa funcția răspunde la o singură întrebare.
 *
 * api/incheie-eveniment.php cere aceleași trei lucruri, dar despărțite, fiindcă
 * el trebuie să spună CARE din ele n-a mers („nu e publicat" / „s-a încheiat
 * deja" / „n-a început încă"). Aici se cere doar da sau nu, pentru un buton.
 */
function poateFiIncheiat(array $eveniment): bool
{
    return evenimentPublicat($eveniment)
        && !evenimentIncheiat($eveniment)
        && evenimentAInceput($eveniment);
}

/**
 * Se poate face încă unul la fel din ăsta?
 *
 * Da, după ce s-a terminat sau s-a anulat: alergarea de duminică se face și
 * duminica viitoare, iar cea care a picat din cauza ploii se mută pe altă zi.
 * Tot ce a scris omul o dată — titlu, categorie, oraș, loc, poză, cine poate
 * veni, cât costă, descrierea — se copiază într-un formular nou. Data rămâne
 * goală: ea e singurul lucru care chiar se schimbă.
 *
 * ÎNAINTE de sfârșit nu: acolo e „Editează". Un buton de refăcut lângă unul
 * de editat, la un eveniment care încă urmează, ar fi două feluri de a face
 * același lucru, iar cel greșit ar lăsa în urmă un al doilea anunț.
 *
 * NUMAI pentru un anunț care chiar a fost pe site. Unul respins sau încă în
 * așteptare n-a avut loc niciodată, deci n-are ce reface — se îndreaptă din
 * „Editează" și se trimite din nou.
 *
 * Proprietatea NU se verifică aici, ca la poateFiAnulat(): o pune
 * evenimentDeRefacut() când se cere formularul, și o întrebare despre
 * organizator pe pagina evenimentului.
 */
function poateFiRefacut(array $eveniment): bool
{
    $stare = (string) ($eveniment['stare_moderare'] ?? '');

    if ($stare === 'anulat') {
        return true;
    }

    return in_array($stare, ['aprobat', 'incheiat'], true) && evenimentIncheiat($eveniment);
}

/**
 * Evenimentul din care se face unul nou — sau null, dacă nu se poate.
 *
 * Fratele lui evenimentDeEditat(): aceeași grijă, altă întrebare. Un slug care
 * nu duce nicăieri, unul al altcuiva și unul care încă n-a trecut sfârșesc la
 * fel — cu null, iar pagina trimite omul pe prima pagină. Ca la event.php,
 * același răspuns pentru toate: altfel, ghicind sluguri, s-ar putea afla ce
 * evenimente există.
 */
function evenimentDeRefacut(string $slug, int $membruId): ?array
{
    if ($membruId <= 0) {
        return null;
    }

    $eveniment = evenimentDupaSlug($slug);

    if ($eveniment === null || (int) $eveniment['membru_id'] !== $membruId) {
        return null;
    }

    return poateFiRefacut($eveniment) ? $eveniment : null;
}

/**
 * Clipa în care începe evenimentul, ca număr de secunde — sau null.
 *
 * Ora de început e obligatorie în formular, dar dacă printr-o scăpare lipsește,
 * ziua începe la miezul nopții — aceeași socoteală ca în evenimentAInceput(),
 * ca cele două să nu spună niciodată lucruri diferite despre același rând.
 */
function momentulInceperii(array $eveniment): ?int
{
    $data = (string) ($eveniment['data_eveniment'] ?? '');

    if ($data === '') {
        return null;
    }

    $ora   = oraScurta($eveniment['ora_inceput'] ?? null);
    $clipa = strtotime($data . ' ' . ($ora !== '' ? $ora : '00:00') . ':00');

    return $clipa === false ? null : $clipa;
}

/**
 * Anulează un eveniment: o stare nouă și un motiv, nu o ștergere.
 *
 * Ștergea rândul, la început. Era greșit: de un eveniment atârnă oameni care
 * și-au făcut planuri, iar un rând șters nu mai poate spune nimănui de ce nu
 * mai au unde să se ducă. Motivul scris de organizator e chiar textul care va
 * pleca spre ei — de aceea e obligatoriu, și de aceea rămâne în bază.
 *
 * PAGINA RĂMÂNE PUBLICĂ, ca la unul încheiat (poateVedeaEvenimentul), cu banda
 * de „anulat" și cu motivul scris dedesubt. A fost ascunsă o vreme, și era a
 * doua jumătate a aceleiași greșeli: cine intra de pe un mesaj primit acum trei
 * zile dădea de un „nu există" și se întreba dacă n-a greșit el ziua.
 *
 * Coperta NU se șterge de pe disc: cât timp rândul e acolo, poza face parte din
 * el; se duce odată cu el, la curățenie.
 *
 * VESTEA NU PLEACĂ DE AICI. Funcția asta doar schimbă rândul; e-mailurile
 * către cei de pe listă le trimite api/anuleaza-eveniment.php, imediat după ce
 * cheamă funcția — aceeași împărțire ca la scoaterea cuiva de pe listă, unde
 * excludeParticipant() scrie și API-ul trimite. Motivul: aici e stratul care
 * atinge baza, iar un `require` de email.php în evenimente.php ar lega între
 * ele două lucruri care n-au de ce să se cunoască.
 *
 * TODO: mai rămâne pasul al doilea, MAI TÂRZIU și ca acțiune de staff, nu
 * automat: curățenia finală — ștergerea rândului anulat, a înscrierilor și a
 * comentariilor lui, plus coperta de pe disc (stergeCopertaDeFisier).
 * Ordinea contează și acolo: dacă s-ar șterge întâi rândurile din
 * `interese_evenimente`, n-ar mai avea cine să afle nimic.
 */
function anuleazaEveniment(array $eveniment, string $motiv): void
{
    $q = db()->prepare(
        'UPDATE evenimente
            SET stare_moderare = \'anulat\', motiv_anulare = ?, actualizat_la = ?
          WHERE id = ?'
    );

    $q->execute([$motiv, acum(), (int) $eveniment['id']]);
}

/**
 * Încheie evenimentul înainte de vreme.
 *
 * Un eveniment se încheie oricum singur a doua zi după data lui. Asta e pentru
 * când se termină mai devreme: s-au ocupat locurile, s-a stricat vremea la
 * jumătate, s-a strâns lumea și nu mai are rost să se înscrie nimeni.
 *
 * Nu e o anulare. Anulat înseamnă „nu a mai avut loc" și se ascunde de toată
 * lumea; încheiat înseamnă „a avut loc, s-a terminat", iar pagina rămâne
 * publică. De aceea nu se cere niciun motiv: nu e nimic de explicat nimănui,
 * și nu pleacă niciun e-mail.
 *
 * Rândul rămâne cum e, cu tot cu copertă și cu lista celor care au fost —
 * doar starea se schimbă.
 */
function incheieEveniment(array $eveniment): void
{
    $q = db()->prepare(
        'UPDATE evenimente SET stare_moderare = \'incheiat\', actualizat_la = ? WHERE id = ?'
    );

    $q->execute([acum(), (int) $eveniment['id']]);
}

/**
 * Închide vânătorile „FindMe" cărora le-a trecut termenul. Întoarce câte.
 *
 * DE CE ARE NEVOIE DE FUNCȚIA ASTA. Un eveniment obișnuit ține o zi: se încheie
 * singur când trece ziua, iar asta se socotește la citire, fără să scrie nimeni
 * nimic (evenimentIncheiat). O vânătoare, în schimb, nu ține o zi — ține până
 * la o CLIPĂ ANUME, cea din „Când o să aibă loc?", care la ea înseamnă ora în
 * care se închide, nu ora la care se strânge lumea. La 18:00 termenul trecuse,
 * numărătoarea inversă ajunsese la zero, caseta scria deja „Nu l-a găsit
 * nimeni" — dar anunțul rămânea „aprobat" până la miezul nopții, cu tot ce ține
 * de asta: stătea printre cele care urmează pe prima pagină și avea buton de
 * încheiere. Singurul fel de a-l încheia la vreme era ca omul de casă să apese.
 *
 * DE CE SE SCRIE ÎN BAZĂ, și nu se socotește la citire ca „i-a trecut ziua".
 * Fiindcă „încheiat" e scris în PATRU locuri deosebite — evenimentIncheiat()
 * pentru un rând, filtruNeincheiat() pentru o interogare, plus condițiile din
 * istoricEvenimente() și evenimenteFaraMultumiri(). O regulă nouă socotită la
 * citire ar fi trebuit strecurată în toate patru, iar ziua în care una rămâne
 * în urmă e ziua în care un anunț arată „încheiat" pe pagina lui și blochează
 * în același timp postarea altuia. Scris în rând, adevărul e unul singur și
 * toate patru îl citesc deopotrivă.
 *
 * ȘI E CHIAR SIMETRIA CELUILALT CAPĂT: o vânătoare se termină în două feluri —
 * o găsește cineva, sau se scurge timpul. Primul scria deja starea în bază
 * (revendicaCodul, în inc/coduri-qr.php, sub aceeași tranzacție cu câștigul).
 * Al doilea o scrie acum la fel.
 *
 * HOTĂRÂREA E ÎN `WHERE`, ca peste tot: `stare_moderare = 'aprobat'` face ca
 * două cereri venite în aceeași clipă să nu se calce, și ca un anunț anulat
 * între timp să nu fie atins.
 *
 * CEASUL E AL LUI PHP (regula 5 din CLAUDE.md): `acum()` intră ca parametru,
 * niciodată NOW(). MySQL e pe alt fus, iar aici se compară clipe, nu zile —
 * două ore de diferență ar închide vânătorile cu două ore mai devreme.
 *
 * DE CE STĂ AICI, și nu în inc/coduri-qr.php, unde stă tot ce ține de joc:
 * fiindcă o cheamă evenimenteDePePrima(), din fișierul ăsta, iar coduri-qr.php
 * cere deja fișierul ăsta — două fișiere care se cer unul pe altul ar fi o
 * buclă. Din același motiv steagul se citește de-a dreptul, `c.joc_qr`, fără
 * esteJocQr(); e aceeași scutire ca la cifreleCartonasului(), scrisă tot lângă
 * lămurirea ei.
 *
 * @param int|null $doarAsta Numai evenimentul ăsta, când se știe care e.
 */
function incheieVanatorileTrecute(?int $doarAsta = null): int
{
    $sql = 'UPDATE evenimente e
              JOIN categorii c ON c.id = e.categorie_id
               SET e.stare_moderare = \'incheiat\', e.actualizat_la = ?
             WHERE c.joc_qr = 1
               AND e.stare_moderare = \'aprobat\'
               AND TIMESTAMP(e.data_eveniment, COALESCE(e.ora_inceput, \'00:00:00\')) <= ?';

    // Ora lipsă e socotită miezul nopții, ca în momentulInceperii() și
    // evenimentAInceput() — altfel un rând fără oră n-ar fi închis niciodată.
    $valori = [acum(), acum()];

    if ($doarAsta !== null) {
        $sql .= ' AND e.id = ?';
        $valori[] = $doarAsta;
    }

    $q = db()->prepare($sql);
    $q->execute($valori);

    return $q->rowCount();
}

/**
 * Scrie peste un eveniment care există deja. $curat vine gata verificat, prin
 * aceleași reguli ca la publicare — la editare nu se cere mai puțin.
 *
 * $copertaNoua e null când omul n-a ales altă poză: atunci coloana nu se
 * atinge, deci rămâne ce era. Un formular trimis fără fișier nu înseamnă
 * „șterge poza", înseamnă „n-am umblat la ea".
 *
 * Slugul NU se schimbă, nici dacă se schimbă titlul: adresa poate fi deja
 * dată mai departe, iar un link care se strică e mai supărător decât un slug
 * care nu mai seamănă cu titlul.
 *
 * Starea de moderare se întoarce mereu la „în așteptare", oricare ar fi fost.
 * Altfel s-ar putea publica orice: trimiți un anunț cumsecade, îl aprobăm, iar
 * a doua zi îi schimbi tot conținutul fără să mai treacă pe la nimeni.
 *
 * Pentru staff rămâne „aprobat" (starePentruPublicare): omul de casă e chiar
 * cel pe la care ar fi trecut anunțul, iar o virgulă îndreptată de el nu are
 * de ce să-i scoată anunțul de pe site până apasă a doua oară.
 */
function actualizeazaEveniment(
    int $id,
    array $curat,
    ?string $copertaNoua,
    bool $eStaff = false,
    bool $ascunsPeProfil = false
): void {
    $campuri = [
        'categorie_id'     => $curat['categorie_id'],
        'titlu'            => $curat['titlu'],
        'data_eveniment'   => $curat['data_eveniment'],
        'ora_inceput'      => $curat['ora_inceput'],
        'ora_sfarsit'      => $curat['ora_sfarsit'],
        'oras'             => $curat['oras'],
        'locatie'          => $curat['locatie'],
        'cost'             => $curat['cost'],
        'varsta_minima'    => $curat['varsta_minima'],
        'participanti_min' => $curat['participanti_min'],
        'participanti_max' => $curat['participanti_max'],
        'descriere'        => $curat['descriere'],
        'gen_participanti' => $curat['gen_participanti'],
        'stare_moderare'   => starePentruPublicare($eStaff),
        'ascuns_pe_profil' => $ascunsPeProfil ? 1 : 0,
        'actualizat_la'    => acum(),
    ];

    if ($copertaNoua !== null) {
        $campuri['coperta'] = $copertaNoua;
    }

    /**
     * ȘTAMPILA DE CORECTURĂ SE ȘTERGE LA PRIMA SCHIMBARE.
     *
     * Ea însemna „i s-a cerut ceva și încă n-a atins anunțul". Din clipa în
     * care omul a apăsat „Trimite", partea lui e făcută — chiar dacă n-a
     * schimbat mare lucru — iar în lista de administrare anunțul trebuie să
     * arate din nou ca unul care așteaptă să fie citit.
     *
     * Se șterge AICI, nu într-un loc anume pentru corecturi: orice editare
     * înseamnă că omul a umblat la anunț, iar noi n-avem de unde ști dacă a
     * îndreptat exact ce i s-a cerut. Asta se vede citind, și de-aia anunțul
     * se întoarce în listă.
     */
    $campuri['corectura_ceruta_la'] = null;

    $bucati = [];
    foreach (array_keys($campuri) as $nume) {
        $bucati[] = $nume . ' = ?';
    }

    $q = db()->prepare('UPDATE evenimente SET ' . implode(', ', $bucati) . ' WHERE id = ?');
    $q->execute([...array_values($campuri), $id]);
}
