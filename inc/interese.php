<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — „Mergi la acest eveniment?"
 *
 * Cine s-a arătat interesat de un eveniment și cine a spus că vine. Un om are
 * cel mult o stare per eveniment; trecerea dintr-una în alta schimbă rândul,
 * iar apăsarea pe starea în care e deja îl șterge.
 *
 * Regula „un singur rând" o ține baza, prin indexul unic din
 * sql/013-interese-evenimente.sql, nu codul de aici: două apăsări în aceeași
 * clipă, de pe telefon și de pe laptop, ar trece amândouă de o verificare
 * scrisă în PHP.
 */

require_once __DIR__ . '/evenimente.php';
require_once __DIR__ . '/imagini.php';

/** Câte poze de profil se văd în grupul de cercuri suprapuse. */
const INTERESE_CHIPURI = 5;

/** Câte nume se scriu pe litere în rândul de dedesubt. */
const INTERESE_NUME = 2;

/**
 * Numai oamenii cu contul activ se numără și se arată.
 *
 * Un cont șters se anonimizează, nu dispare din bază (vezi inc/stergere.php),
 * deci rândurile lui de aici rămân. Dar omul a plecat de pe site: n-are ce
 * căuta în „încă 84 de persoane", n-are ce chip să arate, iar locul pe care îl
 * ținea la un eveniment cu număr limitat se cuvine să se elibereze.
 *
 * Aceeași bucată de SQL peste tot, ca numărul de pe buton, numele de dedesubt
 * și socoteala locurilor să nu spună niciodată trei lucruri diferite.
 */
const INTERESE_DOAR_ACTIVI = 'JOIN membri m ON m.id = i.membru_id AND m.stare = \'activ\'';

/**
 * În ce stare e omul ăsta față de evenimentul ăsta.
 *
 * Întoarce 'interesat', 'participant', sau null dacă n-a spus nimic.
 */
function interesulMeu(int $evenimentId, int $membruId): ?string
{
    if ($membruId <= 0) {
        return null;
    }

    $q = db()->prepare(
        'SELECT stare FROM interese_evenimente WHERE eveniment_id = ? AND membru_id = ? LIMIT 1'
    );
    $q->execute([$evenimentId, $membruId]);

    $stare = $q->fetchColumn();

    return is_string($stare) ? $stare : null;
}

/**
 * Câți sunt interesați și câți vin.
 *
 * Întoarce mereu amândouă cheile, chiar și cu zero — ca cine cheamă funcția
 * să nu fie nevoit să se apere de lipsa lor.
 */
function numaraInterese(int $evenimentId): array
{
    $q = db()->prepare(
        'SELECT i.stare, COUNT(*) AS cate
           FROM interese_evenimente i
           ' . INTERESE_DOAR_ACTIVI . '
          WHERE i.eveniment_id = ?
          GROUP BY i.stare'
    );
    $q->execute([$evenimentId]);

    $numar = ['interesat' => 0, 'participant' => 0];

    foreach ($q->fetchAll() as $rand) {
        $numar[$rand['stare']] = (int) $rand['cate'];
    }

    return $numar;
}

/**
 * Câțiva dintre oamenii adunați în jurul evenimentului, luați la întâmplare.
 *
 * Interesați și participanți la un loc: sub butoane se spune câți sunt cu
 * totul, nu cine ce a apăsat.
 *
 * O singură alegere la întâmplare, nu două — chipurile și numele scrise pe
 * litere ies din aceeași mână de oameni. Altfel s-ar fi văzut cinci chipuri și
 * dedesubt două nume care nu sunt ale niciunuia dintre ele, iar ochiul caută
 * fără să vrea potrivirea.
 *
 * ORDER BY RAND() e în regulă aici: se sortează rândurile unui singur
 * eveniment, adică zeci, nu tot tabelul.
 */
function oameniiInteresati(int $evenimentId, int $cati = INTERESE_CHIPURI): array
{
    $q = db()->prepare(
        'SELECT m.permalink, m.nume, m.prenume, m.poza, m.sex
           FROM interese_evenimente i
           ' . INTERESE_DOAR_ACTIVI . '
          WHERE i.eveniment_id = ?
          ORDER BY RAND()
          LIMIT ' . max(1, $cati)
    );
    $q->execute([$evenimentId]);

    return $q->fetchAll();
}

/**
 * Scrie sau schimbă starea, dintr-o singură cerere.
 *
 * „INSERT ... ON DUPLICATE KEY UPDATE" în loc de „citesc, apoi scriu": între
 * citire și scriere încape o a doua apăsare, iar atunci una dintre ele ar da
 * peste indexul unic și ar arunca o eroare în fața omului.
 *
 * `creat_la` NU se atinge la schimbare: cine s-a arătat interesat acum o lună
 * nu e același lucru cu cine a intrat aseară, iar asta se pierde dacă o
 * trecere la „particip" rescrie data.
 */
function salveazaInteres(int $evenimentId, int $membruId, string $stare): void
{
    $acum = acum();

    $q = db()->prepare(
        'INSERT INTO interese_evenimente (eveniment_id, membru_id, stare, creat_la, actualizat_la)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE stare = VALUES(stare), actualizat_la = VALUES(actualizat_la)'
    );

    $q->execute([$evenimentId, $membruId, $stare, $acum, $acum]);
}

/**
 * Retragerea: omul apasă din nou pe butonul stării în care e deja.
 *
 * Condiția pe `stare` nu e de prisos. Ea face ca butonul să stingă doar starea
 * pe care o arată: fără ea, o apăsare rămasă într-o filă deschisă de ieri ar
 * șterge o hotărâre luată între timp în altă filă.
 */
function stergeInteres(int $evenimentId, int $membruId, string $stare): void
{
    $q = db()->prepare(
        'DELETE FROM interese_evenimente WHERE eveniment_id = ? AND membru_id = ? AND stare = ?'
    );

    $q->execute([$evenimentId, $membruId, $stare]);
}

/**
 * Organizatorul vine la ce pune la cale — fără să apese nimic.
 *
 * Se cheamă la salvarea unui eveniment nou. IGNORE, nu INSERT simplu: dacă
 * rândul există deja (o migrare rulată de două ori, un eveniment refăcut),
 * nu se oprește nimic.
 *
 * Se poate retrage ca oricine altcineva, iar dacă se răzgândește nu i se mai
 * cere numărul de telefon: al lui e, n-are cui să și-l dea.
 */
function faOrganizatorulParticipant(int $evenimentId, int $membruId): void
{
    $acum = acum();

    $q = db()->prepare(
        'INSERT IGNORE INTO interese_evenimente
                (eveniment_id, membru_id, stare, creat_la, actualizat_la)
         VALUES (?,?,\'participant\',?,?)'
    );

    $q->execute([$evenimentId, $membruId, $acum, $acum]);
}

/**
 * Cine vede numerele de telefon din lista de participanți.
 *
 * Organizatorul, fiindcă el trebuie să poată suna pe cineva care întârzie sau
 * să dea de veste dacă se schimbă ceva în ultima clipă — de aceea se și cere
 * numărul la înscriere. Și staff-ul, care răspunde de tot ce se întâmplă pe
 * site.
 *
 * NIMENI ALTCINEVA, nici măcar omul în dreptul numărului lui. Un participant
 * și-a dat numărul organizatorului, nu celor douăzeci de pe listă; dacă și-l
 * vede pe al lui acolo, e firesc să creadă că-l văd și ceilalți pe-al lor, iar
 * data viitoare nu-l mai scrie. Al lui îl are oricum în setări.
 *
 * Regula stă într-un singur loc fiindcă o cer trei: pagina evenimentului și
 * cele două puncte de intrare care redesenează listele (api/interes.php și
 * api/exclude-participant.php). Scrisă de trei ori, ar fi fost de ajuns ca una
 * să rămână în urmă ca numerele să plece spre cine nu trebuie.
 */
function poateVedeaTelefoanele(array $eveniment, ?array $membru): bool
{
    if ($membru === null) {
        return false;
    }

    return (int) $eveniment['membru_id'] === (int) $membru['id'] || esteStaff($membru);
}

/**
 * Numărul de telefon al unui membru, sau '' dacă n-a dat niciunul.
 *
 * Se citește separat, nu din membruCurent(): acolo sunt coloanele de care are
 * nevoie fiecare pagină, iar telefonul îl cer două locuri (setările și
 * confirmarea participării). Aceeași alegere ca în setari.php.
 */
function telefonulMembrului(int $membruId): string
{
    $q = db()->prepare('SELECT telefon FROM membri WHERE id = ? LIMIT 1');
    $q->execute([$membruId]);

    return trim((string) ($q->fetchColumn() ?: ''));
}

/**
 * Mai sunt locuri?
 *
 * `participanti_max` gol înseamnă „câți or veni" — atunci nu se numără nimic
 * și nu se oprește nimeni.
 *
 * Organizatorul intră și el în socoteală: e un om care ocupă un loc, ca toți
 * ceilalți. Un eveniment de zece persoane înseamnă organizatorul plus nouă.
 */
function maiSuntLocuri(array $eveniment, int $catiParticipanti): bool
{
    $maxim = $eveniment['participanti_max'] ?? null;

    if ($maxim === null || (int) $maxim <= 0) {
        return true;
    }

    return $catiParticipanti < (int) $maxim;
}

/* ========================= CUM SE ARATĂ PE ECRAN ====================== */

/**
 * Rândul de sub butoane: chipurile și vorba despre câți sunt.
 *
 * Întoarce HTML, nu-l tipărește, fiindcă îl cer două locuri: pagina, când se
 * încarcă, și api/interes.php, după fiecare apăsare. Scris în două locuri, ar
 * fi început să difere de la prima corectură.
 *
 * TOT LA PREZENT. A avut o vreme și o formă la trecut („a fost interesat sau a
 * participat"), pentru evenimentele încheiate. N-o mai are cui s-o arate:
 * rândul ăsta trăiește doar în caseta „Mergi la acest eveniment?", iar aceea
 * nu se mai desenează după ora de început (vezi event.php). Cine vrea să vadă
 * cine a fost are taburile de dedesubt, unde scrie „Au participat".
 */
function randeazaChipuri(int $evenimentId): string
{
    $numar  = numaraInterese($evenimentId);
    $total  = $numar['interesat'] + $numar['participant'];
    $oameni = $total > 0 ? oameniiInteresati($evenimentId) : [];

    if ($total === 0 || $oameni === []) {
        // Nimeni. Nu se arată un cerc gol și un „0 persoane": se spune ceva ce
        // se poate face — iar aici mai e mereu ceva de făcut, fiindcă rândul
        // ăsta apare numai la un eveniment care n-a început.
        return '<p class="rsvp__note rsvp__note--gol">'
             . 'Fii primul care se arată interesat!</p>';
    }

    /* ----------------------------- chipurile --------------------------- */
    $chipuri = '';

    foreach ($oameni as $om) {
        // Fără link: e un grup de cercuri, nu o listă de legături. Cine caută
        // un om anume îl găsește în numele scrise dedesubt.
        $chipuri .= '<img src="' . h(urlPoza($om['poza'] ?? null, true)) . '" alt=""'
                  . ' width="96" height="96" loading="lazy" decoding="async">';
    }

    /* ------------------------------ vorba ------------------------------ */
    $numiti = array_slice($oameni, 0, INTERESE_NUME);
    $legate = [];

    foreach ($numiti as $om) {
        $nume = h(numeAfisat((string) $om['nume'], (string) $om['prenume']));
        $legate[] = ($om['permalink'] ?? '') !== ''
            ? '<a href="' . h(urlProfil((string) $om['permalink'])) . '"><strong>' . $nume . '</strong></a>'
            : '<strong>' . $nume . '</strong>';
    }

    $restul = $total - count($legate);

    if (count($legate) === 1) {
        // Un singur om: acordul se face după el, nu după „persoane".
        $eF = ($numiti[0]['sex'] ?? '') === 'F';

        $vorba = $legate[0] . ' este ' . ($eF ? 'interesată' : 'interesat')
               . ' de această activitate.';
    } elseif ($restul === 0) {
        $vorba = $legate[0] . ' și ' . $legate[1] . ' sunt interesați de această activitate.';
    } else {
        $cati = $restul === 1 ? 'o persoană' : $restul . ' persoane';

        $vorba = $legate[0] . ', ' . $legate[1] . ' și încă ' . $cati
               . ' sunt interesate de această activitate.';
    }

    return '<div class="facepile" aria-hidden="true">' . $chipuri . '</div>'
         . '<p class="rsvp__note">' . $vorba . '</p>';
}

/* ===================== LISTELE DIN TABURI ============================ */

/**
 * Câți oameni se văd deodată, înainte de „Vezi mai mult".
 *
 * Zece, nu cincisprezece ca la comentarii: un om e un rând scurt, cu chip și
 * nume, iar zece dintre ei ocupă cât patru comentarii. Numărul stă aici, nu în
 * main.js — pagina îl trimite mai departe printr-un atribut.
 */
const OAMENI_DEODATA = 10;

/**
 * Toți oamenii dintr-o stare, cu tot ce trebuie ca să fie arătați.
 *
 * O singură funcție pentru amândouă taburile: „Interesați" și „Participă" pun
 * aceeași întrebare, doar cu altă valoare în `stare`. Două funcții aproape la
 * fel s-ar fi despărțit la prima corectură făcută doar în una.
 *
 * Toți, nu primii zece: ascunsul e treaba paginii, ca la comentarii. Un
 * eveniment are zeci de oameni, nu zeci de mii, iar „Vezi mai mult" trebuie să
 * răspundă pe loc.
 *
 * Doar conturile active — aceeași bucată de SQL ca la numărătoarea de pe
 * butoane (INTERESE_DOAR_ACTIVI), ca lista și numărul de deasupra ei să nu
 * spună niciodată două lucruri diferite.
 *
 * În ordinea înscrierii, de la primul venit: la un eveniment cu locuri
 * limitate, ordinea aia chiar înseamnă ceva. `creat_la`, nu `actualizat_la`:
 * cine s-a arătat interesat acum o lună și a trecut aseară la „particip" s-a
 * băgat acum o lună.
 */
function oameniiCuStarea(int $evenimentId, string $stare, bool $cuTelefon = false): array
{
    /**
     * Telefonul se CERE DIN BAZĂ doar când are cine să-l vadă.
     *
     * Adus mereu și ascuns la desenare, ar fi fost la un pas de a ajunge în
     * pagină: o funcție nouă care tipărește rândul întreg, un `var_dump` uitat
     * într-o zi de căutat un bug. Așa, pentru un vizitator oarecare numărul nu
     * iese din bază deloc. Cine hotărăște e poateVedeaTelefoanele().
     */
    $telefon = $cuTelefon ? ', m.telefon' : '';

    $q = db()->prepare(
        'SELECT m.id, m.permalink, m.nume, m.prenume, m.poza, m.sex, m.este_staff' . $telefon . ',
                i.creat_la
           FROM interese_evenimente i
           ' . INTERESE_DOAR_ACTIVI . '
          WHERE i.eveniment_id = ? AND i.stare = ?
          ORDER BY i.creat_la, i.id'
    );
    $q->execute([$evenimentId, $stare]);

    return $q->fetchAll();
}

/**
 * I s-a închis ușa la evenimentul ăsta?
 *
 * Se întreabă în api/interes.php, înainte de „Voi participa". Doar acolo:
 * interdicția oprește ocuparea unui loc, nu și însemnarea „mă interesează".
 *
 * Rândul poate exista cu `interzis = 0` — cineva scos de pe listă fără să i se
 * închidă ușa. Aceluia nu i se oprește nimic; rândul lui e doar urma faptei.
 */
function esteInterzisLaEveniment(int $evenimentId, int $membruId): bool
{
    if ($membruId <= 0) {
        return false;
    }

    $q = db()->prepare(
        'SELECT interzis FROM excluderi_evenimente
          WHERE eveniment_id = ? AND membru_id = ? LIMIT 1'
    );
    $q->execute([$evenimentId, $membruId]);

    return (int) $q->fetchColumn() === 1;
}

/**
 * De ce nu se poate înscrie omul ăsta ca participant.
 *
 * Întoarce '' dacă poate, altfel motivul, scris pentru el.
 *
 * UN SINGUR LOC pentru toate opreliștile, fiindcă de el atârnă două lucruri
 * care trebuie să spună la fel: butonul stins din pagină (cu motivul scris sub
 * el) și refuzul din api/interes.php. Scrise separat, s-ar fi despărțit — și
 * s-ar fi ajuns iar la un buton viu care duce la un refuz, adică exact ce se
 * repară aici.
 *
 * Se întreabă doar pentru cine NU e deja pe listă. Cine e înăuntru trebuie să
 * poată ieși oricând, chiar dacă între timp evenimentul s-a închis pentru el —
 * un eveniment poate fi schimbat în „doar pentru femei" după ce s-au înscris
 * bărbați, iar ei nu au de ce să rămână prinși acolo.
 *
 * Vizitatorul fără cont nu e oprit de nimic: butonul lui duce la intrare, iar
 * ce se poate și ce nu se hotărăște după ce se știe cine e.
 */
function motivBlocajParticipare(array $eveniment, ?array $membru): string
{
    if ($membru === null) {
        return '';
    }

    $membruId = (int) $membru['id'];

    /**
     * Organizatorul nu e oprit de regula de gen la evenimentul lui.
     *
     * El e omul de care se leagă evenimentul — cineva trebuie să răspundă de o
     * seară pentru mame, chiar dacă n-ar putea veni la ea ca participant. De
     * obicei e trecut pe listă din start (vezi faOrganizatorulParticipant); dacă
     * s-a retras, sau dacă anunțul e unul ținut deoparte de profil, la care nu
     * s-a trecut deloc, tot nu-l oprim din ceva ce i se cuvine.
     */
    if ((int) $eveniment['membru_id'] === $membruId) {
        return '';
    }

    // Ușa închisă de organizator sau de staff. Motivul a plecat întreg în
    // e-mailul de la scoatere; aici se spune doar ce se poate și ce nu.
    if (esteInterzisLaEveniment((int) $eveniment['id'], $membruId)) {
        return 'Nu te mai poți înscrie la acest eveniment.';
    }

    /**
     * Evenimentele care chiar se adresează unui singur sex: un meci de fotbal
     * feminin, o seară pentru mame. „nespecificat" înseamnă că poate veni
     * oricine — și așa sunt aproape toate.
     */
    $gen = (string) ($eveniment['gen_participanti'] ?? 'nespecificat');
    $sex = (string) ($membru['sex'] ?? '');

    if ($gen === 'femei' && $sex !== 'F') {
        return 'Evenimentul e doar pentru femei.';
    }

    if ($gen === 'barbati' && $sex !== 'M') {
        return 'Evenimentul e doar pentru bărbați.';
    }

    /**
     * VÂRSTA MINIMĂ, cerută de organizator.
     *
     * Coloana `varsta_minima` exista de mult, formularul o cerea, pagina o
     * scria în caseta cu detalii — dar nimeni nu se uita la ea la înscriere.
     * Adică site-ul spunea „18+" și lăsa înăuntru pe oricine, ceea ce e mai rău
     * decât să nu fi spus nimic: organizatorul se bizuia pe o regulă care nu
     * exista.
     *
     * SE SOCOTEȘTE LA ZIUA EVENIMENTULUI, nu la ziua de azi. Cine împlinește
     * 16 ani poimâine și vine la ceva de peste trei zile va avea 16 ani acolo —
     * asta cere organizatorul, nu ca omul să-i fi avut deja când s-a înscris.
     * Invers nu se întâmplă niciodată: un anunț nu se mută înapoi în timp.
     *
     * „Împlinit" înseamnă împlinit: la 16 ani ceruți, cel care are exact 16
     * intră, cel care are 15 nu. De-aia `<` și nu `<=`.
     */
    $varstaCeruta = $eveniment['varsta_minima'] ?? null;
    $nascut       = (string) ($membru['data_nasterii'] ?? '');

    if ($varstaCeruta !== null && $nascut !== '') {
        $varsta = varstaLaZiua($nascut, (string) ($eveniment['data_eveniment'] ?? ''));

        if ($varsta !== null && $varsta < (int) $varstaCeruta) {
            return 'Organizatorul cere cel puțin ' . (int) $varstaCeruta
                 . ' ani împliniți pentru evenimentul ăsta.';
        }
    }

    return '';
}

/**
 * Câți ani are cineva născut în `$nascut`, în ziua `$zi`.
 *
 * Întoarce null dacă una dintre date lipsește sau nu se poate citi — atunci
 * cine întreabă hotărăște singur ce face, iar aici nu se inventează o vârstă.
 *
 * Se socotește cu DateTime, nu scăzând ani din ani: „29 februarie 2008" plus
 * șaisprezece ani nu e o zi care există, iar o socoteală făcută cu mâna ar fi
 * greșit exact în cazul pe care nimeni nu-l probează niciodată.
 */
function varstaLaZiua(string $nascut, string $zi): ?int
{
    if ($nascut === '' || $zi === '') {
        return null;
    }

    try {
        $n = new DateTimeImmutable($nascut);
        $z = new DateTimeImmutable($zi);
    } catch (Exception $e) {
        return null;
    }

    if ($z < $n) {
        return 0;
    }

    return (int) $n->diff($z)->y;
}

/**
 * Scoate un om de pe listă și ține minte de ce.
 *
 * Două scrieri care trebuie să se întâmple amândouă sau niciuna, deci într-o
 * tranzacție: dacă ar pica a doua, omul ar fi jos de pe listă fără ca nimeni
 * să mai poată spune de ce — și fără interdicția care poate era tot rostul.
 *
 * Locul se eliberează prin ștergerea rândului din `interese_evenimente`, nu
 * printr-o coloană „scos": numărătoarea locurilor rămase se face peste tabelul
 * acela, iar un rând rămas acolo ar ține un loc ocupat degeaba.
 *
 * „INSERT ... ON DUPLICATE KEY UPDATE" pentru urmă: cine a fost scos o dată
 * fără interdicție se poate înscrie la loc și poate fi scos din nou. A doua
 * oară se rescrie rândul de dinainte — ține minte starea de acum, nu toată
 * povestea.
 */
function excludeParticipant(
    int $evenimentId,
    int $membruId,
    int $exclusDeId,
    string $rol,
    string $motiv,
    bool $interzis
): void {
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->prepare(
            'DELETE FROM interese_evenimente
              WHERE eveniment_id = ? AND membru_id = ? AND stare = \'participant\''
        )->execute([$evenimentId, $membruId]);

        $pdo->prepare(
            'INSERT INTO excluderi_evenimente
                    (eveniment_id, membru_id, exclus_de_id, rol, motiv, interzis, creat_la)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE exclus_de_id = VALUES(exclus_de_id),
                                     rol          = VALUES(rol),
                                     motiv        = VALUES(motiv),
                                     interzis     = VALUES(interzis),
                                     creat_la     = VALUES(creat_la)'
        )->execute([
            $evenimentId, $membruId, $exclusDeId, $rol, $motiv, $interzis ? 1 : 0, acum(),
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Adresa, prenumele și sexul celui scos, pentru e-mailul care îl înștiințează.
 *
 * Sexul, fiindcă mesajul se scrie cu acord: „Ai fost scoasă" pentru ea, „Ai
 * fost scos" pentru el. E o veste neplăcută oricum — măcar să fie scrisă ca
 * pentru cineva anume.
 *
 * Se citesc ÎNAINTE de scoatere, nu după: după, rândul din
 * `interese_evenimente` nu mai e, iar dacă între timp și-ar șterge contul n-am
 * mai avea unde trimite. Întoarce null dacă omul nu mai are cont activ — atunci
 * nu se trimite nimic, dar scoaterea se face oricum.
 */
function omulDeInstiintat(int $membruId): ?array
{
    $q = db()->prepare(
        'SELECT prenume, sex, email FROM membri WHERE id = ? AND stare = \'activ\' LIMIT 1'
    );
    $q->execute([$membruId]);

    $rand = $q->fetch();

    return $rand !== false ? $rand : null;
}

/**
 * Toți oamenii care trebuie să afle că evenimentul s-a anulat.
 *
 * ȘI cei care ziceau că vin, ȘI cei doar interesați. Al doilea grup n-a promis
 * nimic, dar s-a uitat într-acolo tocmai fiindcă se gândea să meargă — iar cine
 * ține sâmbăta liberă „poate mă duc" trebuie să afle că n-are unde, la fel ca
 * cel care apucase să confirme.
 *
 * FĂRĂ ORGANIZATOR. El e cel care tocmai a apăsat pe „Anulează", iar
 * faOrganizatorulParticipant() îl trece singur pe lista de participanți la
 * publicare — deci fără rândul ăsta și-ar fi trimis singur vestea. $faraMembruId
 * e el; se dă din afară, nu se citește aici, fiindcă cel care cheamă funcția îl
 * are deja în mână.
 *
 * Numai conturile ACTIVE și cu adresă: cine și-a șters contul n-are unde primi
 * nimic, iar rândul lui anonimizat n-are adresă. Aceeași grijă ca la
 * omulDeInstiintat() de mai sus și la participantiiDeMultumit() din
 * inc/multumiri.php.
 *
 * Se citesc ÎNAINTE de orice curățenie: rândurile din `interese_evenimente`
 * sunt singurul loc unde scrie cine aștepta seara aceea.
 */
function oameniiDeInstiintatLaAnulare(int $evenimentId, int $faraMembruId = 0): array
{
    return oameniiDeInstiintat($evenimentId, $faraMembruId);
}

/**
 * TOȚI CEI CARE AȘTEAPTĂ SEARA ASTA — și cei înscriși, și cei doar interesați.
 *
 * O cer DOUĂ vești, și amândouă întreabă exact același lucru: anularea
 * evenimentului (api/anuleaza-eveniment.php) și comentariul de căpătâi al
 * organizatorului (api/comentarii.php). Amândouă sunt lucruri despre EVENIMENT,
 * nu despre discuția de sub el — de aceea ajung și la cine s-a arătat doar
 * interesat: el își ține seara aceea deoparte, chiar dacă n-a apăsat încă
 * „Particip".
 *
 * Aceeași listă pentru amândouă, scrisă o dată: dacă una ar număra un om în
 * plus, acela ar primi un e-mail pe care cealaltă spune că nu i se cuvine.
 *
 * $faraMembruId e cel care tocmai a apăsat butonul — de obicei organizatorul,
 * pe care faOrganizatorulParticipant() îl trece singur pe listă la publicare.
 * Fără el, și-ar trimite singur vestea. Se dă din afară, nu se citește aici,
 * fiindcă cel care cheamă funcția îl are deja în mână.
 *
 * Numai conturile ACTIVE și cu adresă: cine și-a șters contul n-are unde primi
 * nimic, iar rândul lui anonimizat n-are adresă. Aceeași grijă ca la
 * omulDeInstiintat() și la participantiiCuEmail(), de mai sus.
 *
 * Se citesc ÎNAINTE de orice curățenie: rândurile din `interese_evenimente`
 * sunt singurul loc unde scrie cine aștepta seara aceea.
 */
function oameniiDeInstiintat(int $evenimentId, int $faraMembruId = 0): array
{
    $q = db()->prepare(
        'SELECT m.id, m.prenume, m.sex, m.email, i.stare
           FROM interese_evenimente i
           JOIN membri m ON m.id = i.membru_id AND m.stare = \'activ\'
          WHERE i.eveniment_id = ?
            AND i.membru_id <> ?
            AND m.email <> \'\'
          ORDER BY i.creat_la, i.id'
    );
    $q->execute([$evenimentId, $faraMembruId]);

    return $q->fetchAll();
}

/**
 * PARTICIPANȚII CĂRORA LI SE POATE SCRIE — cei de pe listă, cu adresă bună.
 *
 * O cer DOUĂ cronuri, și amândouă întreabă exact același lucru: mulțumirea de
 * după eveniment (inc/multumiri.php) și mementoul de dinaintea lui
 * (inc/amintiri.php). Scrisă de două ori, a doua copie ar fi rămas în urmă în
 * ziua în care se schimbă cine socotim că e „pe listă" — iar urmarea n-ar fi
 * fost o pagină strâmbă, ci un e-mail plecat către cine nu trebuia.
 *
 * Nu oameniiCuStarea(), deși ar fi fost la îndemână: aceea aduce ce trebuie
 * pentru un rând desenat pe ecran — chip, permalink, insigne — și NU aduce
 * adresa de e-mail, care aici e tot ce contează. Două treburi, două cereri.
 *
 * Numai conturile active, ca peste tot: cine și-a șters contul nu mai primește
 * nimic, iar rândul lui anonimizat nici n-are unde. Aceeași grijă ca la
 * omulDeInstiintat() și la oameniiDeInstiintatLaAnulare(), de mai sus.
 *
 * NUMAI „participant", niciodată „interesat": cine s-a uitat într-acolo fără
 * să se hotărască n-a promis nimănui nimic, deci nu i se mulțumește că a fost
 * și nu i se amintește de o seară la care nu s-a înscris.
 */
function participantiiCuEmail(int $evenimentId): array
{
    $q = db()->prepare(
        'SELECT m.id, m.prenume, m.email
           FROM interese_evenimente i
           JOIN membri m ON m.id = i.membru_id AND m.stare = \'activ\'
          WHERE i.eveniment_id = ? AND i.stare = \'participant\'
            AND m.email <> \'\'
          ORDER BY i.creat_la, i.id'
    );
    $q->execute([$evenimentId]);

    return $q->fetchAll();
}

/* ======================= CUM ARATĂ PE ECRAN ========================== */

/**
 * „Confirmată acum 3 ore", „Interesat de ieri" — de când e omul pe listă.
 *
 * Acordul se face după om, ca la rândul de sub butoane: „confirmată" pentru
 * ea, „confirmat" pentru el. Iar verbul, după stare: pe una scrie ce a hotărât
 * omul, pe cealaltă doar că se uită într-acolo.
 */
function candSAInscris(array $om, string $stare): string
{
    $eF = ($om['sex'] ?? '') === 'F';

    $verb = $stare === 'participant'
        ? ($eF ? 'Confirmată' : 'Confirmat')
        : ($eF ? 'Interesată'  : 'Interesat');

    return $verb . ' ' . timpRelativ((string) $om['creat_la']);
}

/**
 * Stelele de lângă un participant, la un eveniment încheiat.
 *
 * Cinci butoane pe care se apasă, sau cinci stele doar de privit — după cum
 * are omul dreptul să noteze. Desenul îl face main.js, din `data-stele-input`
 * și `data-stars`: aici se scriu doar starea și motivul.
 *
 * `data-nota` e nota deja dată, ca stelele să se aprindă la încărcarea paginii.
 *
 * `data-parere` e ce a SCRIS omul data trecută despre cel din dreptul căruia
 * stau stelele. De el atârnă formularul de părere care se deschide chiar aici,
 * sub rând: redeschis, trebuie să arate ce era, ca omul să ÎNDREPTE, nu să
 * scrie a doua oară. Fără el ar fi trebuit o a doua cerere la server, la
 * fiecare apăsare.
 *
 * E textul CELUI CARE SE UITĂ, despre altcineva — nu părerile altora despre
 * el. Ajunge în pagină numai la cel care l-a scris.
 */
function randeazaSteleParticipant(int $evaluatId, int $nota, string $blocaj,
                                  string $permalink, string $parere = ''): string
{
    /**
     * Cine n-are dreptul să noteze vede stelele stinse, cu motivul în `title`.
     * Nu ascunse: sunt tot atâtea rânduri, iar unul fără stele ar fi părut
     * scăpat din greșeală.
     */
    if ($blocaj !== '') {
        return '<div class="person__stele person__stele--stinse" title="' . h($blocaj) . '">'
             . '<div class="rating__stars rating__stars--sm" data-stars="' . $nota . '"></div>'
             . '</div>';
    }

    return '<div class="person__stele" data-stele-participant="' . $evaluatId . '"'
         . ' data-permalink="' . h($permalink) . '"'
         . ' data-parere="' . h($parere) . '"'
         . ' data-nota="' . $nota . '">'
         . '<div class="stars-input stars-input--sm" data-stele-input'
         . ' data-chosen="' . ($nota > 0 ? $nota : '') . '"></div>'
         . '</div>';
}

/**
 * Un rând de listă: un chip, un nume, de când e acolo.
 *
 * Același rând pentru amândouă taburile. Se deosebesc prin două lucruri, și
 * amândouă vin de afară: verbul de sub nume (după `$stare`) și butonul de
 * scoatere, care apare doar la participanți și doar pentru cine are dreptul.
 *
 * `data-participant` e cum îl găsește main.js după ce serverul confirmă
 * scoaterea: răspunsul spune ce id a plecat, iar pagina caută rândul după
 * atributul ăsta. Fără el ar trebui numărate pozițiile — iar pozițiile se
 * schimbă la fiecare om scos.
 */
function randeazaOm(
    array $om,
    string $stare,
    int $organizatorId,
    bool $poateScoate,
    ?array $evaluare = null,
    bool $cuTelefon = false
): string {
    $id           = (int) $om['id'];
    $eOrganizator = $id === $organizatorId;
    $nume         = h(numeAfisat((string) $om['nume'], (string) $om['prenume']));

    $legatura = ($om['permalink'] ?? '') !== ''
        ? '<a class="person__name" href="' . h(urlProfil((string) $om['permalink'])) . '">' . $nume . '</a>'
        : '<span class="person__name">' . $nume . '</span>';

    /* ---------------------------- insignele --------------------------- */

    $insigne = '';

    if ($eOrganizator) {
        $insigne .= '<span class="person__badge">Organizator</span>';
    } elseif ((int) ($om['este_staff'] ?? 0) === 1) {
        $insigne .= '<span class="person__badge person__badge--staff">Staff</span>';
    }

    /* ----------------------------- butonul ---------------------------- */

    /**
     * Se scoate doar de pe lista de participanți.
     *
     * „Mă interesează" nu ocupă niciun loc — e o însemnare în dreptul omului,
     * nu o hotărâre — deci n-are ce curăța nimeni acolo. De aceea cine cheamă
     * funcția trimite `false` pentru tabul „Interesați", iar
     * api/exclude-participant.php cere oricum starea `participant`.
     *
     * Organizatorul nu se scoate de pe lista lui. Nici de el însuși — n-ar avea
     * cui să-și trimită e-mailul de înștiințare și ar rămâne un eveniment fără
     * nimeni care să răspundă de el — nici de staff, care are alte unelte
     * pentru un eveniment care nu-i place: îl poate anula cu totul. Aceeași
     * regulă e verificată din nou în API; aici e doar butonul.
     */
    $buton = ($poateScoate && !$eOrganizator)
        ? '<button class="person__scoate" type="button" data-scoate'
          . ' data-nume="' . $nume . '"'
          . ' aria-label="Scoate-l de pe listă pe ' . $nume . '" title="Scoate de pe listă">'
          . '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
          . '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>'
          . '</svg></button>'
        : '';

    /* ----------------------------- stelele ---------------------------- */

    /**
     * Numai la un eveniment încheiat, și numai pe lista de participanți: nota
     * se dă pentru cum a fost omul acolo, iar cine s-a arătat doar interesat
     * n-a fost nicăieri.
     *
     * $evaluare vine gata socotită din event.php — cine poate nota, ce note a
     * dat deja — ca să nu se întrebe baza o dată pentru fiecare rând. Regula
     * adevărată e în motivBlocajEvaluare(), pe care o cheamă API-ul.
     */
    $stele = '';

    if ($evaluare !== null) {
        $euId = (int) $evaluare['eu'];

        // Nota mea despre el, și ce i-am scris: vin din același rând al
        // aceleiași cereri (noteleMeleLaEveniment), nu din două.
        $aMea       = $evaluare['notele_mele'][$id] ?? null;
        $notaLui    = (int) ($aMea['stele'] ?? 0);
        $parereaMea = (string) ($aMea['text'] ?? '');

        // Harta vine gata făcută din absentiiEvenimentului(), ca să nu fie
        // nevoie de constantele din inc/evaluari.php aici — altfel cele două
        // fișiere s-ar fi cerut unul pe altul, în cerc.
        $absent = !empty($evaluare['absenti'][$id]);

        if ($absent) {
            /**
             * Cine n-a venit nu se mai notează de nimeni.
             *
             * Nici stele, nici „Lasă și câteva cuvinte" — doar cuvântul
             * „Neprezentat", pe care îl vede toată lumea. N-are ce judeca
             * nimeni la un om care n-a fost acolo.
             *
             * Iar dacă stelele ar rămâne aprinse, organizatorul ar putea
             * alege peste o săptămână cinci și ar șterge cu ele exact
             * însemnarea pe care tocmai a pus-o — poate după o vorbă bună de
             * la cineva. Regula e ținută și de api/evaluare.php, prin
             * esteNeprezentat().
             */
            $stele = '<span class="person__neprezentat" title="Nu s-a prezentat la eveniment">'
                   . '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
                   . '<circle cx="12" cy="12" r="9"/><path d="M8.5 8.5 15.5 15.5"/>'
                   . '</svg><span>Neprezentat</span></span>';
        } elseif ($id === $euId) {
            // Pe tine nu te notezi. Nici stele stinse: n-are ce să însemne.
            $stele = '';
        } elseif (!empty($evaluare['pot_nota'])) {
            $stele = randeazaSteleParticipant($id, $notaLui, '',
                (string) ($om['permalink'] ?? ''), $parereaMea);
        } elseif (!empty($evaluare['eu_participant'])) {
            /**
             * A FOST ȘI EL ACOLO, dar nu mai poate nota: au trecut cele două
             * zile. Stelele rămân, stinse — în ele scrie nota pe care a dat-o
             * el, iar aceea merită să rămână la vedere. Motivul vine gata
             * scris din event.php (`motiv_stins`): aici nu se poate cere
             * inc/evaluari.php pentru constanta orelor, fiindcă cele două
             * fișiere s-ar cere unul pe altul.
             */
            $stele = randeazaSteleParticipant($id, $notaLui,
                (string) ($evaluare['motiv_stins'] ?? 'Notele s-au închis.'), '');
        } else {
            /**
             * N-A FOST ACOLO: nu vede nicio stea.
             *
             * Vedea cinci stele înghețate în dreptul fiecărui om — un buton
             * care nu se apasă, pus acolo pentru un drept pe care nu-l are.
             * Iar ele nici nu spuneau ceva despre omul din dreptul lor: erau
             * nota pe care ar fi dat-o CEL CARE SE UITĂ, adică zero, la
             * toți. Cinci stele goale citite ca „nota lui" sunt mai rele decât
             * nimic.
             *
             * Cine n-a avut treabă cu evenimentul vede cine a fost acolo, și
             * atât.
             */
            $stele = '';
        }

        /**
         * „Nu s-a prezentat" — numai organizatorul, și niciodată în dreptul lui
         * însuși. E o însemnare de fapt, nu o părere: pune o stea și un text
         * scris de noi pe profilul omului.
         *
         * Butonul dispare după apăsare: însemnarea nu se ia înapoi, iar ce a
         * rămas în locul lui e cuvântul „Neprezentat", de mai sus.
         */
        /**
         * ...și numai cât mai e vreme. După termenul notelor, butonul dispare:
         * „Nu s-a prezentat" pune o stea în media omului, iar dacă notele s-au
         * închis pentru toți, nu se poate ca tocmai asta să rămână deschisă la
         * nesfârșit. Regula adevărată e în motivBlocajEvaluare(), pe care o
         * cheamă api/evaluare.php.
         */
        if (!$absent && empty($evaluare['inchise'])
            && !empty($evaluare['e_organizator']) && $id !== $euId && $id !== $organizatorId) {
            $stele .= '<button class="person__absent" type="button"'
                    . ' data-absent="' . $id . '" data-nume="' . $nume . '"'
                    . ' title="Nu s-a prezentat">'
                    . '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
                    . '<circle cx="12" cy="12" r="9"/><path d="M8.5 8.5 15.5 15.5"/>'
                    . '</svg>'
                    . '<span>Nu s-a prezentat</span>'
                    . '</button>';
        }
    }

    /* ---------------------------- telefonul --------------------------- */

    /**
     * Numărul, sub nume — dar numai pentru organizator și staff.
     *
     * Nu se decide aici cine are voie: `$cuTelefon` vine de sus, din
     * poateVedeaTelefoanele(), iar pentru ceilalți coloana nici măcar nu s-a
     * cerut din bază. Aici se hotărăște doar cum arată.
     *
     * `tel:` fiindcă ăsta e rostul lui: organizatorul se uită pe listă tocmai
     * când vrea să sune pe cineva care întârzie, și de cele mai multe ori se
     * uită de pe telefon. Numărul rămâne scris la vedere, nu ascuns sub un
     * buton: se copiază, se citește cu voce tare, se compară.
     *
     * Cine n-a lăsat niciun număr n-are rând gol dedesubt — organizatorul a
     * primit numărul de la cine l-a dat, atât.
     */
    $telefon = '';

    if ($cuTelefon) {
        $numar = trim((string) ($om['telefon'] ?? ''));

        if ($numar !== '') {
            $telefon = '<a class="person__telefon" href="tel:' . h($numar) . '">'
                     . '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
                     . '<path d="M7 3.5h3l1.5 4-2 1.5a12 12 0 0 0 5.5 5.5l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2'
                     . 'A16.5 16.5 0 0 1 5 5.7 2 2 0 0 1 7 3.5Z"/></svg>'
                     . '<span>' . h($numar) . '</span></a>';
        }
    }

    return '<li class="person' . ($stele !== '' ? ' person--cu-stele' : '') . '"'
         . ' data-participant="' . $id . '">'
         . '<img class="person__avatar" src="' . h(urlPoza($om['poza'] ?? null, true)) . '" alt=""'
         . ' width="96" height="96" loading="lazy" decoding="async">'
         . '<div class="person__info">'
         . $legatura
         . '<span class="person__meta">' . h(candSAInscris($om, $stare)) . '</span>'
         . $telefon
         . '</div>'
         . $insigne
         . $buton
         . ($stele !== '' ? '<div class="person__note">' . $stele . '</div>' : '')
         . '</li>';
}

/**
 * Toată lista unui tab, gata desenată.
 *
 * Întoarce HTML, nu-l tipărește, fiindcă îl cer două locuri: pagina, când se
 * încarcă, și api/exclude-participant.php, care întoarce lista din nou după
 * fiecare scoatere. Scrise în două locuri, ar fi început să difere.
 */
function randeazaListaOameni(
    int $evenimentId,
    string $stare,
    int $organizatorId,
    bool $poateScoate = false,
    ?array $evaluare = null,
    bool $cuTelefoane = false
): string {
    $html = '';

    // Stelele sunt numai pe lista de participanți: nota se dă pentru cum a fost
    // omul la eveniment, iar cine s-a arătat doar interesat n-a fost nicăieri.
    $cuStele = $stare === 'participant' ? $evaluare : null;

    /**
     * Numerele, la fel: numai pe lista de participanți.
     *
     * Interesatului nu i s-a cerut niciodată numărul — „Mă interesează" nu e o
     * hotărâre, e o însemnare — deci n-are ce arăta acolo. Iar cine a apucat
     * să-și lase numărul altundeva (la contact, în setări) nu l-a lăsat pentru
     * asta.
     */
    $telefoane = $cuTelefoane && $stare === 'participant';

    foreach (oameniiCuStarea($evenimentId, $stare, $telefoane) as $om) {
        $html .= randeazaOm($om, $stare, $organizatorId, $poateScoate, $cuStele, $telefoane);
    }

    return $html;
}

/**
 * Rândul de deasupra listei: „12 persoane au confirmat că vor participa."
 *
 * Un singur loc pentru toate cele opt feluri în care se poate spune asta:
 * două taburi × una/mai multe persoane × înainte/după eveniment. Scrise în
 * pagină, s-ar fi copiat între panouri și s-ar fi despărțit.
 *
 * Numărul e într-un `<span>` cu `data-count-for`, ca main.js să-l poată
 * schimba după o scoatere fără să rescrie toată propoziția — și ca să se
 * schimbe odată cu numărul de pe tab și cu cel de pe buton, care poartă același
 * atribut.
 */
function vorbaDespreCatiSunt(int $cati, string $stare, bool $incheiat): string
{
    $eParticipare = $stare === 'participant';

    /**
     * La trecut există numai pentru participanți.
     *
     * Lista de interesați nu se mai arată deloc după ce evenimentul s-a
     * încheiat — nici tabul, nici panoul (vezi event.php): sunt oameni care
     * s-au uitat într-acolo și n-au venit, iar asta nu spune nimic despre
     * seara care a fost. Deci n-are cine să citească un „au fost interesate".
     */
    if ($cati === 0) {
        if ($incheiat && $eParticipare) {
            return 'Nu a confirmat nimeni participarea.';
        }

        return $eParticipare
            ? 'Nimeni nu a confirmat încă participarea. Poți fi primul.'
            : 'Nimeni nu s-a arătat încă interesat. Poți fi primul.';
    }

    $numar = '<strong><span data-count-for="' . h($stare) . '">' . $cati . '</span> '
           . '<span data-cuvant-persoane>' . ($cati === 1 ? 'persoană' : 'persoane') . '</span></strong>';

    if ($eParticipare) {
        // La trecut după ce s-a terminat: „vor participa" sub un anunț de acum
        // trei luni sună a invitație la ceva ce nu se mai poate.
        return $numar . ($incheiat
            ? ($cati === 1 ? ' a confirmat participarea.' : ' au confirmat participarea.')
            : ($cati === 1 ? ' a confirmat că va participa.' : ' au confirmat că vor participa.'));
    }

    // Tot la prezent, oricare ar fi starea evenimentului: vezi de ce, mai sus.
    return $numar . ($cati === 1
        ? ' este interesată de această activitate.'
        : ' sunt interesate de această activitate.');
}

/**
 * Cele două panouri cu oameni, gata desenate, pentru răspunsurile JSON.
 *
 * Le cer amândouă API-urile: api/interes.php, după ce cineva a apăsat „Mă
 * interesează" sau „Voi participa", și api/exclude-participant.php, după o
 * scoatere. Amândouă schimbă listele, deci amândouă trebuie să le trimită
 * înapoi — altfel omul își vede numele apărând pe buton, dar nu și în tabul de
 * dedesubt, până la o reîncărcare pe care nu are de ce s-o ghicească.
 *
 * Aceeași formă pentru amândouă, ca main.js să aibă o singură funcție care le
 * aplică: cheia e starea, adică exact ce scrie în `data-stare` pe panou.
 *
 * `gol` nu se socotește în JS din lungimea listei: rândul de deasupra e
 * altceva când nu e nimeni („Nimeni nu s-a arătat încă interesat") și se
 * așază pe mijloc, nu în stânga. Cine desenează textul spune și cum se așază.
 */
function raspunsulPanourilor(array $eveniment, bool $poateScoate = false,
                             ?array $evaluare = null, bool $cuTelefoane = false): array
{
    $evenimentId   = (int) $eveniment['id'];
    $organizatorId = (int) $eveniment['membru_id'];
    $incheiat      = evenimentIncheiat($eveniment);
    $numar         = numaraInterese($evenimentId);

    $panouri = [];

    foreach (['interesat', 'participant'] as $stare) {
        $cati = (int) $numar[$stare];

        $panouri[$stare] = [
            // Butoanele de scoatere sunt numai pe lista de participanți, și
            // numai pentru cine are dreptul. La interesați nu se scoate nimeni:
            // „Mă interesează" nu ocupă niciun loc.
            'lista' => randeazaListaOameni(
                $evenimentId,
                $stare,
                $organizatorId,
                $stare === 'participant' && $poateScoate,
                $evaluare,
                $cuTelefoane
            ),
            'intro' => vorbaDespreCatiSunt($cati, $stare, $incheiat),
            'gol'   => $cati === 0,
        ];
    }

    return $panouri;
}
