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
function categoriiEvenimente(): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $q = db()->query('SELECT id, nume, slug FROM categorii ORDER BY ordine, nume');

    return $cache = $q->fetchAll();
}

/** Doar id-urile, pentru verificarea din formular. */
function idCategoriiValide(): array
{
    return array_map(static fn(array $c): int => (int) $c['id'], categoriiEvenimente());
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
 * — acolo trebuie să apară TOATE, tocmai ca ele să se poată umple.
 */
function categoriiCuEvenimente(): array
{
    $q = db()->query(
        'SELECT c.id, c.nume, c.slug
           FROM categorii c
          WHERE EXISTS (
                  SELECT 1 FROM evenimente e
                   WHERE e.categorie_id = c.id
                     AND e.stare_moderare IN (\'aprobat\', \'incheiat\')
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
 */
function moderezaEveniment(array $eveniment, string $stare): void
{
    $q = db()->prepare(
        'UPDATE evenimente SET stare_moderare = ?, actualizat_la = ? WHERE id = ?'
    );

    $q->execute([$stare, acum(), (int) $eveniment['id']]);
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
 */
function poatePublicaEveniment(int $membruId): array
{
    $active = evenimenteActive($membruId);
    $limita = limitaEvenimente($membruId);

    if (count($active) < $limita) {
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
    $azi   = date('Y-m-d');
    $deLa  = max(0, $deLa);
    $cate  = max(1, min($cate, EVENIMENTE_PRIMA_TURA));

    /**
     * Bucata care spune dacă s-a încheiat. Se scrie o dată și se folosește de
     * trei ori — în SELECT și de două ori în ORDER BY — ca cele trei să nu
     * poată ajunge vreodată să spună lucruri diferite.
     */
    $eIncheiat = '(e.stare_moderare = \'incheiat\' OR e.data_eveniment < ?)';

    $unde   = ['e.stare_moderare IN (\'aprobat\', \'incheiat\')'];
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
                e.locatie, e.descriere, e.stare_moderare, e.oras,
                c.nume AS categorie, c.slug AS categorie_slug, c.imagine_default,
                ' . $eIncheiat . ' AS incheiat
           FROM evenimente e
           JOIN categorii c ON c.id = e.categorie_id
          WHERE ' . implode(' AND ', $unde) . '
          ORDER BY ' . $eIncheiat . ' ASC,
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
        $incheiat = !empty($ev['incheiat']);
        $stare    = $incheiat ? 'incheiat' : (evenimentAInceput($ev) ? 'live' : '');

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
                e.locatie, e.descriere, e.stare_moderare, e.oras,
                c.nume AS categorie, c.slug AS categorie_slug, c.imagine_default
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

    return $parti === [] ? 'index.php' : 'index.php?' . http_build_query($parti);
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
 */
function categoriaCeruta(?string $cerut): string
{
    $cerut = trim((string) $cerut);

    if ($cerut === '') {
        return '';
    }

    foreach (categoriiEvenimente() as $categorie) {
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
 */
function evenimenteDePeProfil(int $membruId, bool $vedeSiCeleInAsteptare): array
{
    [$unde, $azi] = filtruNeincheiat();

    $stari = $vedeSiCeleInAsteptare ? ['aprobat', 'in_asteptare'] : ['aprobat'];
    $semne = implode(',', array_fill(0, count($stari), '?'));

    $q = db()->prepare(
        'SELECT e.id, e.titlu, e.slug, e.coperta, e.data_eveniment, e.ora_inceput,
                e.locatie, e.descriere, e.stare_moderare,
                c.nume AS categorie, c.slug AS categorie_slug, c.imagine_default
           FROM evenimente e
           JOIN categorii c ON c.id = e.categorie_id
          WHERE e.membru_id = ?
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
 */
function cateEvenimenteOrganizate(int $membruId): int
{
    $q = db()->prepare(
        // „incheiat" se numără la fel ca „aprobat": e un eveniment care chiar
        // a avut loc. Fără el, cifra ar scădea în clipa în care organizatorul
        // apasă „Încheie evenimentul" — adică exact când a făcut treaba.
        'SELECT COUNT(*) FROM evenimente
          WHERE membru_id = ? AND stare_moderare IN (\'aprobat\', \'incheiat\')'
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
                m.permalink AS org_permalink, m.nume AS org_nume, m.prenume AS org_prenume,
                m.poza AS org_poza, m.poza_actualizata_la AS org_poza_actualizata_la
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
 * Anulat — doar staff. Nici măcar organizatorul, deși el l-a anulat: a spus ce
 * avea de spus și a închis subiectul, iar o pagină care se mai deschide încă
 * pentru el ar fi o promisiune că se mai poate face ceva. Rândul rămâne în
 * bază pentru cine face curățenia și pentru e-mailurile care trebuie trimise.
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

    if ($eveniment['stare_moderare'] === 'anulat') {
        return false;
    }

    if ((int) $eveniment['membru_id'] === $membruId && $membruId > 0) {
        return true;
    }

    return evenimentPublicat($eveniment);
}

/** Adresa paginii unui eveniment. Un singur loc care o știe. */
function urlEveniment(string $slug): string
{
    return 'event.php?slug=' . urlencode($slug);
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
 *   'live'     — se petrece chiar acum: în colț clipește „Live"
 *   ''         — nimic, ca la un anunț care abia urmează
 *
 * Nu se ghicește din rând, ci se spune. Pe prima pagină e ceva de deosebit
 * între ce urmează, ce e în toi și ce a fost; în tabul „Istoric" de pe profil
 * totul e încheiat, iar un semn pe fiecare cartonaș ar fi doar zgomot.
 */
function randeazaCartonasEveniment(
    array $ev,
    string $insigne = '',
    bool $ascuns = false,
    string $stare = ''
): string {
    $inAsteptare = ($ev['stare_moderare'] ?? '') === 'in_asteptare';
    $incheiat    = $stare === 'incheiat';
    $live        = $stare === 'live';

    $clase = 'card';
    if ($inAsteptare) { $clase .= ' card--in-asteptare'; }
    if ($incheiat)    { $clase .= ' card--incheiat'; }
    if ($live)        { $clase .= ' card--live'; }
    if ($ascuns)      { $clase .= ' ascuns'; }

    $coperta = urlCoperta($ev['coperta'] ?? null);

    // Imaginea implicită a categoriei, când va exista. Coloana e deja în bază,
    // fișierele se urcă de mână — vezi roadmap-ul din CLAUDE.md.
    if ($coperta === '' && !empty($ev['imagine_default'])) {
        $coperta = 'assets/img/categorii/' . $ev['imagine_default'];
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
    } elseif ($incheiat) {
        $semn = '<span class="card__stare card__stare--incheiat">Încheiat</span>';
    } elseif ($live) {
        // Punctul care pulsează e același cu cel din „Live din oraș", de sus.
        $semn = '<span class="card__stare card__stare--live">'
              . '<span class="pulse-dot" aria-hidden="true"></span>Live</span>';
    }

    return '<article class="' . $clase . '">'
         . '<a class="card__media" href="' . $adresa . '">'
         . $poza
         . '<span class="card__tag">' . h((string) ($ev['categorie'] ?? '')) . '</span>'
         . $semn
         . $insigne
         . '</a>'
         . '<div class="card__body">'
         . '<h3 class="card__title"><a href="' . $adresa . '">' . h((string) $ev['titlu']) . '</a></h3>'
         . '<p class="card__excerpt">' . h(inceputDeText((string) $ev['descriere'])) . '</p>'
         . '<div class="card__meta">'
         . '<time datetime="' . h((string) $ev['data_eveniment']) . '">'
         . h(dataScurta($ev['data_eveniment'])) . '</time>'
         . '<span class="dot" aria-hidden="true"></span>'
         . '<span>' . h(inceputDeText((string) $ev['locatie'], 48)) . '</span>'
         . '</div></div></article>';
}

/** Adresa formularului, în modul în care editează un eveniment anume. */
function urlEditareEveniment(string $slug): string
{
    return 'adauga_eveniment.php?slug=' . urlencode($slug);
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
 * Scrie evenimentul în bază. $curat vine gata verificat din verificaEveniment().
 *
 * Slugul se încearcă de câteva ori: coada lui e întâmplătoare, deci o
 * potrivire e foarte puțin probabilă, dar „puțin probabil" nu e „imposibil",
 * iar indexul unic din bază e cel care are ultimul cuvânt.
 *
 * Înapoi vine slugul, nu id-ul: după salvare, singurul lucru de care are
 * nevoie cine a chemat funcția e adresa paginii, ca omul să se poată duce
 * direct la evenimentul lui.
 */
function salveazaEveniment(int $membruId, array $curat, ?string $coperta): string
{
    $sql = 'INSERT INTO evenimente
                (membru_id, categorie_id, titlu, slug, coperta,
                 data_eveniment, ora_inceput, ora_sfarsit, oras, locatie,
                 cost, varsta_minima, participanti_min, participanti_max,
                 descriere, gen_participanti, stare_moderare, creat_la, actualizat_la)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';

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
                // Nimic nu apare pe site până nu trece pe la om.
                'in_asteptare',
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
         * AFARĂ din `try`, dinadins. Înăuntru, o eroare de aici cu codul
         * 23000 (o cheie străină, de pildă) ar fi fost luată drept „slugul s-a
         * lovit de altul" și ar fi pornit încă o rundă — adică un al doilea
         * eveniment, din senin.
         *
         * Funcția stă în inc/interese.php, care cere fișierul ăsta; de-aia se
         * cere aici, la folosire, și nu sus, printre celelalte: două fișiere
         * care se cer unul pe altul de la început ar fi o buclă.
         */
        require_once __DIR__ . '/interese.php';
        faOrganizatorulParticipant($idNou, $membruId);

        return $slug;
    }

    return '';
}

/**
 * Anulează un eveniment: o stare nouă și un motiv, nu o ștergere.
 *
 * Ștergea rândul, la început. Era greșit: de un eveniment atârnă oameni care
 * și-au făcut planuri, iar un rând șters nu mai poate spune nimănui de ce nu
 * mai au unde să se ducă. Motivul scris de organizator e chiar textul care va
 * pleca spre ei — de aceea e obligatoriu, și de aceea rămâne în bază.
 *
 * Coperta NU se șterge de pe disc. Cât timp rândul e acolo și staff-ul îl mai
 * poate deschide, poza face parte din el; se duce odată cu el, la curățenie.
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
 */
function actualizeazaEveniment(int $id, array $curat, ?string $copertaNoua): void
{
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
        'stare_moderare'   => 'in_asteptare',
        'actualizat_la'    => acum(),
    ];

    if ($copertaNoua !== null) {
        $campuri['coperta'] = $copertaNoua;
    }

    $bucati = [];
    foreach (array_keys($campuri) as $nume) {
        $bucati[] = $nume . ' = ?';
    }

    $q = db()->prepare('UPDATE evenimente SET ' . implode(', ', $bucati) . ' WHERE id = ?');
    $q->execute([...array_values($campuri), $id]);
}
