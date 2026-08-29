<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — evaluările dintre participanți.
 *
 * Se dau DUPĂ un eveniment, între cei care au fost la el.
 *
 * STELELE SINGURE SUNT ANONIME, și chiar nevăzute: intră în medie și în barele
 * de pe profil, dar nu apar nicăieri ca rând. Nimeni nu află cine i-a dat trei
 * stele — altfel nimeni n-ar mai da trei stele cuiva pe care îl reîntâlnește
 * sâmbăta viitoare.
 *
 * VORBELE SE SEMNEAZĂ. Cine se așază să scrie ceva își pune și numele: o
 * părere semnată se cântărește altfel decât una venită de nicăieri, iar cel
 * care o primește are cui să-i răspundă.
 *
 * Nota automată — „Nu s-a prezentat", pusă de organizator — e un fapt, nu o
 * părere, și se arată ca atare. După ea, omul acela nu se mai poate nota de
 * nimeni: n-are ce judeca nimeni la cineva care n-a fost acolo, iar
 * organizatorul n-are cum să-și schimbe însemnarea mai târziu în cinci stele.
 */

require_once __DIR__ . '/interese.php';

/** Câte evaluări se văd pe profil, înainte de „Vezi mai mult". */
const EVALUARI_DEODATA = 20;

/** Nota pe care o primește cine nu s-a prezentat. */
const EVALUARE_ABSENT_STELE = 1;

/** Textul pus în locul părerii, când nota o pune site-ul. */
const EVALUARE_ABSENT_TEXT = 'Nu s-a prezentat la eveniment, deși s-a înscris pe lista de participanți.';

/* ============================== CITIREA ============================== */

/**
 * Nota pe care i-am dat-o eu, la evenimentul ăsta — sau null.
 *
 * Întoarce rândul întreg: pagina evenimentului are nevoie de stele, ca să
 * aprindă cele deja date, iar profilul și de text, ca să-l pună în casetă.
 */
function evaluareaMea(int $evenimentId, int $evaluatorId, int $evaluatId): ?array
{
    if ($evaluatorId <= 0 || $evaluatId <= 0) {
        return null;
    }

    $q = db()->prepare(
        'SELECT id, stele, text, automat FROM evaluari
          WHERE eveniment_id = ? AND evaluator_id = ? AND evaluat_id = ? LIMIT 1'
    );
    $q->execute([$evenimentId, $evaluatorId, $evaluatId]);

    $rand = $q->fetch();

    return $rand !== false ? $rand : null;
}

/**
 * Notele pe care le-am dat eu la evenimentul ăsta, după cine le-a primit.
 *
 * O singură cerere pentru toată lista de participanți, nu una per rând: la un
 * eveniment cu treizeci de oameni, ar fi fost treizeci de întrebări în care
 * răspunsul se știe dintr-o dată.
 *
 * Întoarce `id => ['stele' => int, 'text' => string]`. TEXTUL VINE ODATĂ CU
 * STELELE, fiindcă e cerut de același rând al aceleiași cereri: formularul de
 * părere de pe pagina evenimentului se deschide cu ce a scris omul data
 * trecută, iar a doua întrebare pusă bazei pentru asta ar fi fost una degeaba.
 *
 * E textul MEU despre altcineva, nu al altcuiva despre mine: ajunge în pagină
 * numai la cel care l-a scris, în dreptul rândului lui.
 */
function noteleMeleLaEveniment(int $evenimentId, int $evaluatorId): array
{
    if ($evaluatorId <= 0) {
        return [];
    }

    $q = db()->prepare(
        'SELECT evaluat_id, stele, text FROM evaluari
          WHERE eveniment_id = ? AND evaluator_id = ?'
    );
    $q->execute([$evenimentId, $evaluatorId]);

    $note = [];

    foreach ($q->fetchAll() as $rand) {
        $note[(int) $rand['evaluat_id']] = [
            'stele' => (int) $rand['stele'],
            'text'  => (string) ($rand['text'] ?? ''),
        ];
    }

    return $note;
}

/**
 * Media, câte sunt, și distribuția pe stele — tot ce arată profilul.
 *
 * Întoarce mereu aceleași chei, chiar și pentru cine n-a primit nimic, ca cine
 * cheamă funcția să nu fie nevoit să se apere de lipsa lor.
 *
 * Distribuția are mereu cele cinci trepte, de la 5 la 1, chiar goale: barele
 * de pe profil se desenează din ea, iar una lipsă ar fi sărit un rând.
 */
function rezumatEvaluari(int $membruId): array
{
    $gol = ['medie' => 0.0, 'cate' => 0, 'distributie' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]];

    if ($membruId <= 0) {
        return $gol;
    }

    $q = db()->prepare(
        'SELECT stele, COUNT(*) AS cate FROM evaluari
          WHERE evaluat_id = ? GROUP BY stele'
    );
    $q->execute([$membruId]);

    $rezumat = $gol;
    $suma    = 0;

    foreach ($q->fetchAll() as $rand) {
        $stele = (int) $rand['stele'];
        $cate  = (int) $rand['cate'];

        if ($stele < 1 || $stele > 5) {
            continue;
        }

        $rezumat['distributie'][$stele] = $cate;
        $rezumat['cate'] += $cate;
        $suma            += $stele * $cate;
    }

    if ($rezumat['cate'] > 0) {
        $rezumat['medie'] = round($suma / $rezumat['cate'], 1);
    }

    return $rezumat;
}

/**
 * Evaluările SCRISE, primite de un om, cele mai noi întâi.
 *
 * Doar cele cu vorbe. Stelele date dintr-o apăsare, de pe pagina
 * evenimentului, se duc în medie și în barele de sus, dar nu apar aici: un
 * rând care spune „cineva ți-a dat 4 stele" și atât nu are ce citi nimeni, iar
 * zece la rând ar îneca singura părere scrisă cu adevărat.
 *
 * De asta lista NU mai e anonimă. Cine se așază să scrie ceva își pune și
 * numele: o părere semnată se cântărește altfel decât una venită de nicăieri,
 * iar cel care o primește are cui să-i răspundă. Stelele, care rămân
 * nevăzute aici, rămân și anonime — acolo era rostul anonimatului.
 *
 * Vine cu titlul și slugul evenimentului: nota are greutate tocmai fiindcă în
 * spatele ei e o seară anume, iar cine citește profilul trebuie s-o poată
 * deschide.
 */
function evaluarilePrimite(int $membruId): array
{
    if ($membruId <= 0) {
        return [];
    }

    $q = db()->prepare(
        // `ev.id` se cere pentru butonul de ștergere al staff-ului: e singurul
        // lucru din rândul ăsta care spune CARE notă anume, iar fără el butonul
        // n-ar fi avut ce trimite la api/admin.php.
        'SELECT ev.id, ev.stele, ev.text, ev.automat, ev.creat_la, ev.actualizat_la,
                e.titlu AS eveniment_titlu, e.slug AS eveniment_slug,
                m.permalink, m.nume, m.prenume, m.poza, m.stare AS stare_cont
           FROM evaluari ev
           JOIN evenimente e ON e.id = ev.eveniment_id
           JOIN membri m     ON m.id = ev.evaluator_id
          WHERE ev.evaluat_id = ?
            AND ev.text IS NOT NULL AND ev.text <> \'\'
          ORDER BY ev.creat_la DESC, ev.id DESC'
    );
    $q->execute([$membruId]);

    return $q->fetchAll();
}

/* =========================== FEREASTRA ============================== */

/**
 * Câte ceasuri au oamenii la dispoziție ca să se noteze între ei.
 *
 * DE CE EXISTĂ O FEREASTRĂ. O părere despre cum a fost cineva se face cât
 * seara aceea e încă proaspătă. După o săptămână nu mai e o părere, e o
 * amintire — iar peste o lună, când nimeni nu mai ține minte nimic, o stea
 * pusă atunci spune mai mult despre ce s-a întâmplat între timp decât despre
 * eveniment. Notele nu se pot retrage și nu se pot raporta, deci singura
 * apărare împotriva unei note date la supărare, mult mai târziu, e ceasul.
 *
 * Două zile sunt de ajuns: cine s-a trezit a doua zi cu ceva de spus are
 * timp, iar cine n-a spus nimic până a treia zi n-avea nimic de spus.
 */
const ORE_PENTRU_NOTE = 48;

/**
 * Clipa în care se închid notele — sau null, dacă nu se poate ști.
 *
 * SE SOCOTEȘTE DIN CEASUL ANUNȚULUI, nu din ce scrie în bază despre când s-a
 * apăsat un buton. Adică: ora de sfârșit dacă organizatorul a dat una, altfel
 * miezul nopții de la capătul zilei — apoi încă ORE_PENTRU_NOTE.
 *
 * DE CE AȘA. Fiindcă termenul trebuie să se poată citi de pe pagină: omul se
 * uită la anunț și știe până când mai poate nota. Socotit din clipa în care
 * organizatorul a apăsat „Încheie evenimentul", ar fi fost un termen pe care
 * participanții nu-l văd nicăieri — și mai scurt pentru cei ai unui
 * organizator harnic decât pentru ai unuia care n-a apăsat nimic. Ceasul
 * pornește la fel pentru toți, din ce scrie în anunț.
 *
 * Fără oră de sfârșit, ziua se încheie la miezul nopții — aceeași socoteală ca
 * evenimentIncheiat(), care tot pe zile merge.
 *
 * Ceasul e al PHP-ului, ca peste tot (regula 5 din CLAUDE.md).
 */
function terminulNotelor(array $eveniment): ?int
{
    $zi = (string) ($eveniment['data_eveniment'] ?? '');

    if ($zi === '') {
        return null;
    }

    $ora     = oraScurta($eveniment['ora_sfarsit'] ?? null);
    $sfarsit = $ora !== ''
        ? strtotime($zi . ' ' . $ora . ':00')
        // Fără oră de sfârșit: capătul zilei, adică miezul nopții următoare.
        : strtotime($zi . ' 00:00:00 +1 day');

    return $sfarsit === false ? null : $sfarsit + ORE_PENTRU_NOTE * 3600;
}

/** A trecut fereastra de notare? */
function auTrecutNotele(array $eveniment): bool
{
    $termen = terminulNotelor($eveniment);

    return $termen !== null && time() > $termen;
}

/* ============================== SCRIEREA ============================= */

/**
 * De ce nu poate omul ăsta să-l noteze pe celălalt.
 *
 * Întoarce '' dacă poate, altfel motivul.
 *
 * UN SINGUR LOC pentru toate opreliștile, ca la participare: de el atârnă și
 * stelele desenate în pagină, și refuzul din api/evaluare.php. Scrise separat,
 * s-ar fi despărțit.
 *
 * Regulile, pe scurt: după ce s-a terminat, ÎN DOUĂ ZILE de la sfârșit, între
 * oameni care au fost acolo, și nu pe tine însuți.
 */
function motivBlocajEvaluare(array $eveniment, int $evaluatorId, int $evaluatId): string
{
    if ($evaluatorId <= 0) {
        return 'Intră în cont ca să dai o notă.';
    }

    if ($evaluatorId === $evaluatId) {
        return 'Nu te poți nota pe tine.';
    }

    /**
     * Abia după ce s-a terminat.
     *
     * În timpul evenimentului nimeni n-are ce nota: omul e acolo, iar părerea
     * despre cum a fost se face la sfârșit, nu la mijloc.
     */
    if (!evenimentIncheiat($eveniment)) {
        return 'Notele se dau după ce se încheie evenimentul.';
    }

    /**
     * Și numai DOUĂ ZILE după aceea (vezi terminulNotelor).
     *
     * Aici se închide și pentru cel care n-a apucat, și pentru cel care s-ar
     * întoarce peste o lună să schimbe o notă. Nu se face deosebire între
     * „adaugă" și „schimbă": după termen, rândul e cum a rămas.
     */
    if (auTrecutNotele($eveniment)) {
        return 'Au trecut ' . ORE_PENTRU_NOTE . ' de ore de la eveniment. '
             . 'Notele s-au închis.';
    }

    $evenimentId = (int) $eveniment['id'];

    // Amândoi trebuie să fi fost pe listă. Cine s-a uitat de departe n-are ce
    // spune despre cum s-a purtat cineva acolo.
    if (interesulMeu($evenimentId, $evaluatorId) !== 'participant') {
        return 'Poți nota doar dacă ai fost și tu pe lista de participanți.';
    }

    if (interesulMeu($evenimentId, $evaluatId) !== 'participant') {
        return 'Omul ăsta n-a fost pe lista de participanți.';
    }

    return '';
}

/**
 * Scrie nota, sau o schimbă pe cea de dinainte.
 *
 * „INSERT ... ON DUPLICATE KEY UPDATE": stelele date de pe pagina
 * evenimentului și textul scris mai târziu pe profil ajung în același rând, nu
 * în două. `creat_la` NU se atinge la schimbare — ține minte când s-a format
 * prima părere.
 *
 * $text null înseamnă „doar stele, fără vorbe". La o a doua trecere fără text,
 * textul de dinainte se PĂSTREAZĂ: cine schimbă stelele de pe pagina
 * evenimentului n-are de unde să știe că șterge cu asta ce scrisese pe profil.
 *
 * $eScriere RUPE regula aceea, și numai pentru un caz: cererea vine dintr-un
 * FORMULAR DE PĂRERE trimis de om — caseta de sub rândul lui, sau cea de pe
 * profil — iar acolo caseta golită înseamnă „îmi retrag vorbele", nu „n-am
 * atins textul". Deosebirea nu se poate face din valoare, fiindcă amândouă
 * ajung aici ca null; se face din CE A APĂSAT OMUL, iar asta o știe doar cel
 * care cheamă funcția.
 *
 * Fără ea, cine își ștergea părerea o vedea rămasă acolo la reîncărcare, fără
 * nicio vorbă — cea mai rea formă de „n-a mers": una care spune că a mers.
 */
function salveazaEvaluare(
    int $evenimentId,
    int $evaluatId,
    int $evaluatorId,
    int $stele,
    ?string $text = null,
    bool $automat = false,
    bool $eScriere = false
): void {
    $acum = acum();

    // Singura deosebire e ce se întâmplă cu textul la a doua trecere: îl
    // scriem așa cum vine (chiar gol), sau îl lăsăm pe cel de dinainte.
    $cumVineTextul = $eScriere ? 'VALUES(text)' : 'COALESCE(VALUES(text), text)';

    $q = db()->prepare(
        'INSERT INTO evaluari
                (eveniment_id, evaluat_id, evaluator_id, stele, text, automat, creat_la, actualizat_la)
         VALUES (?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE stele         = VALUES(stele),
                                 text          = ' . $cumVineTextul . ',
                                 automat       = VALUES(automat),
                                 actualizat_la = VALUES(actualizat_la)'
    );

    $q->execute([
        $evenimentId, $evaluatId, $evaluatorId, $stele, $text,
        $automat ? 1 : 0, $acum, $acum,
    ]);
}

/**
 * Cine află pe e-mail că i s-a scris o părere, și cine nu.
 *
 * NUMAI PENTRU CE E SCRIS. Stelele rămân anonime și tăcute: o înștiințare la
 * fiecare stea apăsată ar fi însemnat cinci mesaje după o ieșire cu cinci
 * oameni, fiecare spunând „cineva te-a notat, nu-ți spunem cine, nu-ți spunem
 * cât". Nefolositor în cel mai bun caz; apăsător în cel mai rău. Iar cu numele
 * în el, mesajul ar fi spart tocmai anonimatul care ține notele cinstite.
 *
 * CINE NU PRIMEȘTE NICIODATĂ:
 *
 *   - omul însuși, dacă cumva ar ajunge să-și scrie sieși;
 *   - cine a stins bifa din setări (`email_feedback`);
 *   - un cont care nu mai e activ — suspendat sau anonimizat. La cel
 *     anonimizat adresa nici nu mai e a cuiva (vezi inc/stergere.php).
 *
 * NICI LA O ÎNSEMNARE AUTOMATĂ („Nu s-a prezentat"): textul acela nu e părerea
 * nimănui, e scris de noi. Cine cheamă funcția are grijă să n-o cheme atunci —
 * regula stă scrisă în api/evaluare.php, lângă apăsare.
 *
 * Întoarce rândul omului (id, email, prenume) sau null. NU trimite nimic:
 * e-mailul pleacă din api/evaluare.php, ca la comentarii. Aici e stratul care
 * atinge baza.
 */
function omDeInstiintatLaFeedback(int $evaluatId, int $evaluatorId): ?array
{
    if ($evaluatId <= 0 || $evaluatId === $evaluatorId) {
        return null;
    }

    // `permalink` vine odată cu restul: butonul din e-mail duce pe profilul
    // LUI, unde stă părerea — nu pe al celui care a scris-o.
    $q = db()->prepare(
        'SELECT id, email, prenume, permalink
           FROM membri
          WHERE id = ? AND stare = \'activ\' AND email_feedback = 1
          LIMIT 1'
    );
    $q->execute([$evaluatId]);

    $om = $q->fetch();

    return $om === false ? null : $om;
}

/**
 * Pune ștampila „vestea a plecat" — și spune dacă ea CHIAR se pune acum.
 *
 * O SINGURĂ VESTE PENTRU O PĂRERE, ORICÂTE ÎNDREPTĂRI AR URMA. Vestea pleca,
 * până acum, la fiecare text nou sau schimbat. Suna cuminte, dar în viață un om
 * își îndreaptă vorbele: scrie în grabă, vede o greșeală, se răzgândește asupra
 * unui cuvânt. Zece îndreptări însemnau zece e-mailuri despre ACEEAȘI părere,
 * iar cel care le primea învăța, pe bună dreptate, să nu le mai deschidă.
 *
 * HOTĂRÂREA SE IA ÎN `WHERE`, nu într-un `SELECT` de dinainte urmat de un
 * `UPDATE`. Două file deschise deodată, sau două apăsări la o secundă distanță,
 * ar fi trecut amândouă de o verificare făcută separat și ar fi trimis două
 * mesaje — exact lucrul de care ne ferim. Aici, a doua cerere schimbă zero
 * rânduri și primește „nu".
 *
 * Aceeași croială ca la revendicarea unui abțibild (revendicaCodul) și ca la
 * ștampila de mulțumiri: cine scrie primul câștigă, ceilalți află că n-au ce
 * scrie.
 *
 * Ștampila NU se șterge niciodată, nici când omul își retrage vorbele. Dacă ar
 * fi ștearsă, „scrie – șterge – scrie" ar fi devenit felul de a trimite oricâte
 * mesaje.
 */
function insemneazaVesteaTrimisa(int $evenimentId, int $evaluatId, int $evaluatorId): bool
{
    $q = db()->prepare(
        'UPDATE evaluari SET instiintat_la = ?
          WHERE eveniment_id = ? AND evaluat_id = ? AND evaluator_id = ?
            AND instiintat_la IS NULL'
    );
    $q->execute([acum(), $evenimentId, $evaluatId, $evaluatorId]);

    return $q->rowCount() === 1;
}

/**
 * Cine a fost însemnat ca neprezentat la evenimentul ăsta.
 *
 * Se citește o dată, pentru toată lista, nu o dată pe rând. Întoarce o hartă
 * `id => true`, ca event.php să poată întreba dintr-o privire.
 *
 * Fără să întrebe cine a pus însemnarea: o poate pune doar organizatorul (vezi
 * api/evaluare.php), iar rezultatul se arată oricui deschide tabul. „Neprezentat"
 * nu e o părere pe care s-o vadă doar cel care a scris-o.
 *
 * Doar cele automate: o steluță dată cu mâna, fiindcă omul chiar a fost
 * nesuferit, nu înseamnă că n-a venit.
 */
function absentiiEvenimentului(int $evenimentId): array
{
    $q = db()->prepare(
        'SELECT evaluat_id FROM evaluari WHERE eveniment_id = ? AND automat = 1'
    );
    $q->execute([$evenimentId]);

    $absenti = [];

    foreach ($q->fetchAll() as $rand) {
        $absenti[(int) $rand['evaluat_id']] = true;
    }

    return $absenti;
}

/**
 * A fost însemnat ca neprezentat?
 *
 * Întrebarea de la o singură persoană, pentru API — pagina folosește harta de
 * mai sus. Cine n-a venit nu se mai notează de nimeni: n-are ce judeca nimeni
 * la un om care n-a fost acolo, iar organizatorul n-are cum să-și schimbe
 * însemnarea mai târziu în cinci stele.
 */
function esteNeprezentat(int $evenimentId, int $membruId): bool
{
    $q = db()->prepare(
        'SELECT 1 FROM evaluari
          WHERE eveniment_id = ? AND evaluat_id = ? AND automat = 1 LIMIT 1'
    );
    $q->execute([$evenimentId, $membruId]);

    return $q->fetchColumn() !== false;
}

/**
 * Poate omul ăsta să dea note la evenimentul ăsta?
 *
 * Se întreabă O DATĂ pentru toată lista, nu o dată pentru fiecare rând: la un
 * eveniment cu treizeci de oameni, motivBlocajEvaluare() ar fi însemnat
 * treizeci de întrebări cu același răspuns.
 *
 * Ce rămâne pe seama rândului e doar „nu pe mine însumi" — iar aceea se vede
 * din id, fără nicio cerere.
 */
function potNotaLaEveniment(array $eveniment, int $membruId): bool
{
    return $membruId > 0
        && evenimentIncheiat($eveniment)
        && !auTrecutNotele($eveniment)
        && interesulMeu((int) $eveniment['id'], $membruId) === 'participant';
}

/* ======================= CE SE VEDE PE PROFIL ======================== */

/**
 * Câte evenimente se descoperă odată în tabul „Istoric".
 *
 * Șase, cât un rând-două de cartonașe: sunt mari, cu poză, iar mai multe
 * deodată ar face pagina să sară. Numărul stă aici, nu în HTML și nu în JS —
 * pagina îl scrie în `data-deodata`, iar main.js îl citește de acolo. Ca la
 * comentarii, la listele din taburi și la evaluări.
 */
const ISTORIC_DEODATA = 6;

/**
 * Evenimentele ÎNCHEIATE la care a fost omul ăsta, cele mai noi întâi.
 *
 * Tabul „Istoric" de pe profil. Nu se amestecă cu „Evenimente organizate" de
 * mai sus: acolo se vede ce PUNE LA CALE, adică ce urmează; aici, doar ce a
 * fost. Un eveniment de sâmbăta viitoare n-are ce căuta într-un istoric —
 * omul n-a fost încă nicăieri.
 *
 * ÎNCHEIAT înseamnă același lucru ca peste tot pe site (vezi
 * evenimentIncheiat() din inc/evenimente.php): ori i-a trecut ziua, ori
 * organizatorul a apăsat „Încheie evenimentul". Condiția e ruda scrisă pentru
 * o interogare a lui filtruNeincheiat(), întoarsă pe dos — aici avem de ales
 * rânduri din bază, nu de cercetat unul pe care îl ținem în mână.
 *
 * NU se scot cele la care a fost însemnat absent. Cifra de sus spune la câte a
 * fost, deci absențele n-au ce căuta în ea; lista de aici e istoria lui, iar
 * din istorie nu se șterge o seară fiindcă n-a ajuns la ea. Se scrie „Absent"
 * pe cartonaș și rămâne la locul lui.
 *
 * Evenimentele ținute de el vin cu ele: organizatorul e trecut pe lista de
 * participanți ca oricare altul (vezi faOrganizatorulParticipant). Ca să se
 * poată scrie „Organizator" pe ele, se întoarce și `e_organizator`.
 *
 * DAR NU LA CELE ȚINUTE DEOPARTE DE PROFIL. La un anunț cu `ascuns_pe_profil`,
 * omul de casă nu e trecut singur pe listă (organizatorulVineSingur) — dacă
 * ajunge acolo, e fiindcă a apăsat el „Particip", ca oricare altul. Atunci
 * `e_organizator` iese 0 și cartonașul n-are insignă: a fost la ceva, n-a pus
 * la cale nimic.
 *
 * Cele care n-au ajuns niciodată publice lipsesc: n-avea cum să se înscrie
 * cineva la ele.
 *
 * CELE ANULATE, în schimb, rămân. Omul se înscrisese, își ținuse seara aceea
 * liberă, iar apoi n-a mai fost nimic — asta face parte din ce i s-a întâmplat,
 * la fel ca o seară care chiar a avut loc. Se vede pe cartonaș, stins, cu
 * „Anulat" scris în colț; nu se numără însă în „a participat la", fiindcă
 * n-a participat la nimic (vezi laCateEvenimenteAFost).
 *
 * ANUNȚURILE ÎNSEMNATE `ascuns_pe_profil` (vezi sql/022) INTRĂ ȘI ELE, inclusiv
 * pe profilul celui care le-a pus. Lipseau, și era greșit.
 *
 * Bifa aceea spune „nu e o ieșire de-a MEA" — atât. Ea ține anunțul deoparte de
 * „Ieșiri organizate" (evenimenteDePeProfil, cateEvenimenteOrganizate), și
 * acolo e locul ei: de obicei e un eveniment al primăriei sau al altcuiva, pe
 * care omul de casă doar îl scrie pe site. Dar dacă pe urmă apasă „Particip",
 * a fost acolo ca oricare altul, iar istoricul spune pe unde a fost omul, nu ce
 * a organizat. Scos de aici, ieșea o pagină care se contrazicea singură: pe
 * lista de participanți a evenimentului scria numele lui, iar în istoricul lui
 * seara aceea nu existase niciodată.
 *
 * Ce rămâne din bifă, aici: NU se scrie „Organizator" pe cartonaș — vezi
 * `e_organizator` din interogare.
 */
function istoricEvenimente(int $membruId): array
{
    if ($membruId <= 0) {
        return [];
    }

    $q = db()->prepare(
        'SELECT e.id, e.titlu, e.slug, e.coperta, e.data_eveniment, e.ora_inceput,
                e.locatie, e.descriere, e.stare_moderare, e.oras, e.participanti_max,
                e.fixat_la,
                c.nume AS categorie, c.slug AS categorie_slug, c.imagine_default,
                -- De el atârnă ce cifre se scriu în colțul cartonașului: la o
                -- vânătoare „FindMe" participanții nu se arată deloc. Vezi
                -- cifreleCartonasului() din inc/evenimente.php.
                c.joc_qr AS categorie_joc_qr,
                ' . CIFRE_CARTONAS . ',
                -- „Organizator" — dar NU la anunțurile ținute deoparte de
                -- profil. Acolo omul de casă n-a pus la cale o ieșire de-a lui:
                -- a scris pe site una a orașului, a primăriei, a altcuiva.
                -- Bifa aceea spune tocmai „nu e a mea", iar o insignă care ar
                -- spune contrariul, chiar pe cartonașul din istoricul lui, ar
                -- fi fost singura vorbă neadevărată de pe pagină.
                (e.membru_id = i.membru_id AND e.ascuns_pe_profil = 0) AS e_organizator,
                EXISTS (
                  SELECT 1 FROM evaluari ev
                   WHERE ev.eveniment_id = i.eveniment_id
                     AND ev.evaluat_id   = i.membru_id
                     AND ev.automat      = 1
                ) AS absent
           FROM interese_evenimente i
           JOIN evenimente e ON e.id = i.eveniment_id
           JOIN categorii  c ON c.id = e.categorie_id
          WHERE i.membru_id = ?
            AND i.stare = \'participant\'
            -- `ascuns_pe_profil` NU mai scoate nimic de aici: dacă omul de
            -- casă a apăsat „Particip", a fost acolo, iar istoricul spune pe
            -- unde a fost. Vezi explicația de deasupra funcției; ce se stinge
            -- e doar insigna „Organizator", în SELECT-ul de mai sus.
            AND e.stare_moderare IN (\'aprobat\', \'incheiat\', \'anulat\')
            -- Un eveniment anulat intră în istoric oricare i-ar fi ziua: nu mai
            -- urmează, oricât ar arăta calendarul.
            AND (e.stare_moderare IN (\'incheiat\', \'anulat\') OR e.data_eveniment < ?)
          ORDER BY e.data_eveniment DESC, e.ora_inceput DESC, e.id DESC'
    );
    $q->execute([$membruId, date('Y-m-d')]);

    return $q->fetchAll();
}

/**
 * Istoricul, gata desenat.
 *
 * Aceleași cartonașe ca peste tot (randeazaCartonasEveniment din
 * inc/evenimente.php); ce se adaugă aici sunt cele două însemne care se pot
 * pune numai privind de pe profilul cuiva anume.
 *
 * „Organizator" e o laudă mică: spune că seara aceea a existat fiindcă s-a
 * ocupat el de ea. „Absent" e celălalt capăt, și se scrie pe față — cine se
 * uită la istoricul cuiva are dreptul să vadă și de câte ori n-a ajuns, altfel
 * cifra de sus („A confirmat, dar nu a venit") ar rămâne fără nimic în spate.
 *
 * Toate intră în pagină; ascunsul îl face main.js, câte ISTORIC_DEODATA — ca
 * la comentarii, la listele din taburi și la evaluări. De aceea nu se pune
 * `.ascuns` de aici pe niciunul.
 */
function randeazaIstoric(array $evenimente): string
{
    $html = '';

    foreach ($evenimente as $ev) {
        $insigne = '';

        if (!empty($ev['e_organizator'])) {
            $insigne .= '<span class="card__rol card__rol--organizator">Organizator</span>';
        }

        if (!empty($ev['absent'])) {
            $insigne .= '<span class="card__rol card__rol--absent">Absent</span>';
        }

        /**
         * Cartonașele de aici se poartă ca cele de la coada primei pagini:
         * poza stinsă, cuvântul în colț. Până acum arătau ca cele care abia
         * urmează — aceeași culoare, aceeași greutate — și ochiul nu deosebea
         * o listă cu ce a fost de una cu ce vine.
         *
         * Toate cele de aici sunt trecute (funcția de mai sus nu aduce altele),
         * deci singura întrebare rămasă e dacă seara a avut loc sau a fost
         * anulată.
         */
        $stare = ($ev['stare_moderare'] ?? '') === 'anulat' ? 'anulat' : 'incheiat';

        $html .= randeazaCartonasEveniment($ev, $insigne, false, $stare);
    }

    return $html;
}

/* ====================== CIFRELE DE PE PROFIL ========================= */

/**
 * La câte evenimente a fost omul ăsta.
 *
 * Cele active ȘI cele încheiate — adică tot ce se vede pe site: unul la care
 * merge săptămâna viitoare se numără la fel ca unul de acum o lună. Ce nu se
 * numără sunt evenimentele care n-au ajuns niciodată publice (în așteptare,
 * respinse) și cele anulate: la primele n-avea cum să se înscrie nimeni, iar
 * la ultimele nimeni n-a fost nicăieri.
 *
 * SE SCAD cele la care organizatorul a însemnat că n-a venit. Altfel cele două
 * cifre de pe profil s-ar bate cap în cap: „prezent la 12" și „a confirmat,
 * dar nu a venit, la 3" ar însemna că trei dintre cele douăsprezece sunt
 * tocmai cele la care n-a fost. Așa, fiecare eveniment intră într-un singur
 * loc.
 *
 * Evenimentele lui, pe care le-a ținut chiar el, se numără și ele: e trecut pe
 * lista de participanți ca oricare altul (vezi faOrganizatorulParticipant) și
 * chiar a fost acolo.
 */
function laCateEvenimenteAFost(int $membruId): int
{
    if ($membruId <= 0) {
        return 0;
    }

    $q = db()->prepare(
        'SELECT COUNT(*)
           FROM interese_evenimente i
           JOIN evenimente e ON e.id = i.eveniment_id
          WHERE i.membru_id = ?
            AND i.stare = \'participant\'
            -- `ascuns_pe_profil` nu scoate nimic nici de aici, ca și la lista
            -- de dedesubt (istoricEvenimente): cifra trebuie să numere exact
            -- cartonașele care se văd sub ea. Cât timp scotea, omul de casă
            -- care spusese „particip" la un anunț al orașului avea zero scris
            -- sus și niciun cartonaș jos — deși fusese acolo.
            AND e.stare_moderare IN (\'aprobat\', \'incheiat\')
            AND NOT EXISTS (
                  SELECT 1 FROM evaluari ev
                   WHERE ev.eveniment_id = i.eveniment_id
                     AND ev.evaluat_id   = i.membru_id
                     AND ev.automat      = 1
                )'
    );
    $q->execute([$membruId]);

    return (int) $q->fetchColumn();
}

/**
 * De câte ori a confirmat și n-a mai ajuns.
 *
 * Se numără EVENIMENTELE, nu însemnările: „Nu s-a prezentat" o poate pune doar
 * organizatorul (vezi api/evaluare.php), deci într-un caz cinstit e oricum una
 * pe eveniment — dar cifra de pe profil trebuie să spună „la câte evenimente",
 * nu „câte rânduri sunt în tabel". DISTINCT o ține așa orice s-ar întâmpla mai
 * târziu cu regula.
 *
 * Rândul rămâne și după ce omul se șterge de pe listă: însemnarea e despre o
 * seară anume, nu despre ce scrie acum în dreptul lui.
 */
function laCateEvenimenteNuAVenit(int $membruId): int
{
    if ($membruId <= 0) {
        return 0;
    }

    $q = db()->prepare(
        'SELECT COUNT(DISTINCT ev.eveniment_id)
           FROM evaluari ev
          WHERE ev.evaluat_id = ? AND ev.automat = 1'
    );
    $q->execute([$membruId]);

    return (int) $q->fetchColumn();
}

/**
 * Câte abțibilde „FindMe" a găsit omul ăsta prin oraș — cifra de pe al patrulea
 * cartonaș al profilului.
 *
 * O singură frază, dar scrisă în inc/coduri-qr.php, lângă tabelul ei. Aici
 * rămâne doar ușa: profil.php cere de la fișierul ăsta toate cele patru cifre,
 * iar dacă a cincea ar veni de altundeva, pagina ar fi trebuit să știe din care
 * fișier vine fiecare.
 */
function cateCoduriQrGasite(int $membruId): int
{
    require_once __DIR__ . '/coduri-qr.php';

    return cateCoduriQrGasiteDe($membruId);
}

/* ========================= CUM ARATĂ PE ECRAN ======================== */

/**
 * Rezumatul de pe profil: media, câte sunt, barele pe stele.
 *
 * MEREU aceeași casetă, chiar și la un om care n-a primit nicio stea. Era
 * până acum un rând de text în locul ei — „Nicio evaluare încă" — iar profilul
 * arăta altfel după cum fusese sau nu notat cineva. Barele goale spun același
 * lucru, dar spun și CE va apărea acolo: cinci trepte care așteaptă. Un rând de
 * text nu arată nimic.
 *
 * Întoarce HTML, nu-l tipărește: îl cere profilul la încărcare, iar
 * api/evaluare.php după ce cineva tocmai a notat de pe pagina lui.
 */
function randeazaRezumatEvaluari(array $rezumat): string
{
    $cate  = (int) $rezumat['cate'];
    $medie = (float) $rezumat['medie'];

    $bare = '';

    // De la 5 la 1, ca la orice listă de note: sus e ce vrea toată lumea.
    foreach ([5, 4, 3, 2, 1] as $stele) {
        $cateAici = (int) ($rezumat['distributie'][$stele] ?? 0);
        $procent  = $cate > 0 ? round($cateAici / $cate * 100) : 0;

        $bare .= '<li><span>' . $stele . '</span>'
               . '<div class="rating-bar"><i style="width:' . $procent . '%"></i></div>'
               . '<span>' . $cateAici . '</span></li>';
    }

    /**
     * Fără nicio notă, în locul mediei stă o linie.
     *
     * Nu „0,0": aceea e o notă, și încă cea mai proastă cu putință. Un om care
     * n-a fost notat de nimeni n-a luat zero — n-a luat nimic. Aceeași linie o
     * arată și caseta de sus, din antetul profilului, până i-o umple JS-ul.
     */
    $valoare = $cate > 0
        ? '<span class="rating-summary__value">' . h(number_format($medie, 1, ',', '')) . '</span>'
        // Linia se scrie mai mică și mai stinsă decât o notă: la mărimea unui
        // „4,6" îngroșat, o liniuță ajunge o bară neagră care pare pusă peste
        // ceva, nu un loc gol care așteaptă.
        : '<span class="rating-summary__value rating-summary__value--gol">—</span>';

    $vorba = match (true) {
        $cate === 0 => 'Nicio evaluare încă',
        $cate === 1 => 'o evaluare',
        default     => $cate . ' evaluări',
    };

    return '<div class="rating-summary__score">'
         . $valoare
         . '<div class="rating__stars" data-stars="' . h((string) $medie) . '"></div>'
         . '<span class="rating-summary__count">' . $vorba . '</span></div>'
         . '<ul class="rating-bars">' . $bare . '</ul>';
}

/**
 * Lista de evaluări primite, gata desenată.
 *
 * Doar cele scrise ajung aici (vezi evaluarilePrimite), iar ele se semnează:
 * chip, nume prescurtat și legătură spre profil, ca la comentarii. Stelele
 * singure rămân nevăzute și anonime — acolo era rostul anonimatului.
 *
 * Toate intră în pagină; ascunsul îl face main.js, ca la comentarii și la
 * listele din taburi.
 *
 * `$eStaff` pune un „×" la capătul fiecărei păreri. E singurul fel în care o
 * vorbă nedreaptă poate pleca de pe profilul cuiva: notele nu se retrag, nu se
 * raportează și nu se moderează nicăieri altundeva. Butonul NU se ascunde din
 * CSS — pentru cine nu e de-al casei nici nu ajunge în pagină, iar api/admin.php
 * întreabă din nou, fiindcă o legătură care nu se vede rămâne o cerere care se
 * poate scrie de mână.
 */
function randeazaEvaluari(array $evaluari, bool $eStaff = false): string
{
    if ($evaluari === []) {
        return '';
    }

    $html = '';

    foreach ($evaluari as $ev) {
        $automat = (int) $ev['automat'] === 1;
        $stele   = (int) $ev['stele'];

        /**
         * Contul șters se anonimizează, nu dispare (vezi inc/stergere.php), iar
         * părerea lui rămâne. Dar omul a plecat de pe site: nu i se mai scrie
         * numele și nu se mai trimite nimeni la profilul lui. Aceeași regulă ca
         * la comentarii.
         */
        $activ = ($ev['stare_cont'] ?? '') !== 'sters';
        $nume  = $activ
            ? h(numeAfisat((string) $ev['nume'], (string) $ev['prenume']))
            : 'Utilizator șters';

        /**
         * O notă automată nu e o părere, e un fapt: în capul ei scrie de la
         * cine vine, nu numele cuiva care și-a spus părerea.
         */
        if ($automat) {
            $antet = '<span class="comment__author comment__author--sistem">Însemnare de la organizator</span>';
            $poza  = POZA_IMPLICITA;
        } else {
            $antet = ($activ && ($ev['permalink'] ?? '') !== '')
                ? '<a class="comment__author" href="' . h(urlProfil((string) $ev['permalink'])) . '">' . $nume . '</a>'
                : '<span class="comment__author comment__author--sters">' . $nume . '</span>';

            $poza = $activ ? urlPoza($ev['poza'] ?? null, true) : POZA_IMPLICITA;
        }

        $undeva = '<a class="evaluare__eveniment" href="'
                . h(urlEveniment((string) $ev['eveniment_slug'])) . '">'
                . h((string) $ev['eveniment_titlu']) . '</a>';

        $cand = (string) $ev['creat_la'];
        $text = trim((string) ($ev['text'] ?? ''));

        /**
         * „×"-ul omului de casă. Stă în ANTETUL părerii, lângă ora ei — nu sub
         * text, unde ar fi arătat ca un buton al cititorului. Aceeași
         * așezare ca steagul de raportare de la comentarii.
         *
         * `data-rand` e pe `<li>`: blocul de administrare din main.js scoate
         * rândul din care s-a apăsat, oricare ar fi el, iar aici rândul e chiar
         * părerea.
         */
        $sterge = $eStaff
            ? '<button class="evaluare__sterge" type="button"'
              . ' data-fapta="sterge-evaluare" data-id="' . (int) $ev['id'] . '"'
              . ' data-intreb="Ștergi părerea asta? Media se schimbă pe loc, iar nimeni nu află nimic."'
              . ' aria-label="Șterge părerea" title="Șterge părerea (doar staff)">'
              . '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
              . '<path d="m7 7 10 10M17 7 7 17"/></svg></button>'
            : '';

        $html .= '<li class="comment evaluare' . ($automat ? ' evaluare--automata' : '') . '"'
               . ($eStaff ? ' data-rand' : '') . '>'
               . '<article class="comment__body">'
               . '<img class="comment__avatar" src="' . h($poza) . '" alt=""'
               . ' width="96" height="96" loading="lazy" decoding="async">'
               . '<div class="comment__main">'
               . '<div class="comment__head">'
               . '<div class="comment__cine">' . $antet . '</div>'
               . '<div class="comment__cand">'
               . '<time datetime="' . h(str_replace(' ', 'T', $cand)) . '">' . h(timpRelativ($cand)) . '</time>'
               . '<span class="dot" aria-hidden="true"></span>' . $undeva
               . $sterge
               . '</div></div>'
               . '<div class="rating__stars rating__stars--sm" data-stars="' . $stele . '"></div>'
               . ($text !== '' ? '<p class="comment__text">' . nl2br(h($text), false) . '</p>' : '')
               . '</div></article></li>';
    }

    return $html;
}
