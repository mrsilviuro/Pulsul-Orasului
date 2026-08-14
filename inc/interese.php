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
 */
function randeazaOameniInteresati(int $evenimentId, bool $incheiat = false): string
{
    $numar  = numaraInterese($evenimentId);
    $total  = $numar['interesat'] + $numar['participant'];
    $oameni = $total > 0 ? oameniiInteresati($evenimentId) : [];

    if ($total === 0 || $oameni === []) {
        /**
         * Nimeni. Nu se arată un cerc gol și un „0 persoane": se spune ceva ce
         * se poate face — dar numai dacă mai e ceva de făcut. La un eveniment
         * trecut, „fii primul interesat" ar fi o invitație la ceva imposibil.
         */
        return '<p class="rsvp__note rsvp__note--gol">'
             . ($incheiat ? 'Nu s-a înscris nimeni.' : 'Fii primul interesat de acest eveniment!')
             . '</p>';
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
            ? '<a href="profil.php?m=' . h((string) $om['permalink']) . '"><strong>' . $nume . '</strong></a>'
            : '<strong>' . $nume . '</strong>';
    }

    $restul = $total - count($legate);

    /**
     * Prezent cât timp mai e ceva de făcut, trecut după ce s-a terminat.
     *
     * „sunt interesate de acest eveniment" sub un anunț de acum trei luni sună
     * ca și cum s-ar mai putea veni. După încheiere se spune ce a fost —
     * fiindcă lista aia chiar asta e acum: istoria evenimentului.
     *
     * Că s-a încheiat o spune banda de sus, o dată; aici nu se mai repetă.
     */
    if (count($legate) === 1) {
        // Un singur om: acordul se face după el, nu după „persoane".
        $eF = ($numiti[0]['sex'] ?? '') === 'F';

        $vorba = $legate[0] . ($incheiat
            ? ' a fost ' . ($eF ? 'interesată' : 'interesat') . ' sau a participat la acest eveniment.'
            : ' este ' . ($eF ? 'interesată' : 'interesat') . ' de acest eveniment.');
    } elseif ($restul === 0) {
        $vorba = $legate[0] . ' și ' . $legate[1] . ($incheiat
            ? ' au fost interesați sau au participat la acest eveniment.'
            : ' sunt interesați de acest eveniment.');
    } else {
        $cati = $restul === 1 ? 'o persoană' : $restul . ' persoane';

        $vorba = $legate[0] . ', ' . $legate[1] . ' și încă ' . $cati . ($incheiat
            ? ' au fost interesate sau au participat la acest eveniment.'
            : ' sunt interesate de acest eveniment.');
    }

    return '<div class="facepile" aria-hidden="true">' . $chipuri . '</div>'
         . '<p class="rsvp__note">' . $vorba . '</p>';
}

/* ===================== LISTA DE PARTICIPANȚI ========================= */

/**
 * Câți participanți se văd deodată, înainte de „Vezi mai mult".
 *
 * Zece, nu cincisprezece ca la comentarii: un participant e un rând scurt, cu
 * chip și nume, iar zece dintre ei ocupă cât patru comentarii. Numărul stă
 * aici, nu în main.js — pagina îl trimite mai departe printr-un atribut.
 */
const PARTICIPANTI_DEODATA = 10;

/**
 * Toți cei care au spus că vin, cu tot ce trebuie ca să fie arătați.
 *
 * Toți, nu primii zece: ascunsul e treaba paginii, ca la comentarii. Un
 * eveniment are zeci de participanți, nu zeci de mii, iar „Vezi mai mult"
 * trebuie să răspundă pe loc.
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
function participantiiEvenimentului(int $evenimentId): array
{
    $q = db()->prepare(
        'SELECT m.id, m.permalink, m.nume, m.prenume, m.poza, m.sex, m.este_staff,
                i.creat_la
           FROM interese_evenimente i
           ' . INTERESE_DOAR_ACTIVI . '
          WHERE i.eveniment_id = ? AND i.stare = \'participant\'
          ORDER BY i.creat_la, i.id'
    );
    $q->execute([$evenimentId]);

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

/* ======================= CUM ARATĂ PE ECRAN ========================== */

/**
 * Un rând din lista de participanți.
 *
 * `data-participant` e cum îl găsește main.js după ce serverul confirmă
 * scoaterea: răspunsul spune ce id a plecat, iar pagina caută rândul după
 * atributul ăsta. Fără el ar trebui numărate pozițiile — iar pozițiile se
 * schimbă la fiecare om scos.
 */
function randeazaParticipant(array $om, int $organizatorId, bool $poateScoate): string
{
    $id        = (int) $om['id'];
    $eOrganizator = $id === $organizatorId;
    $nume      = h(numeAfisat((string) $om['nume'], (string) $om['prenume']));

    $legatura = ($om['permalink'] ?? '') !== ''
        ? '<a class="person__name" href="profil.php?m=' . h((string) $om['permalink']) . '">' . $nume . '</a>'
        : '<span class="person__name">' . $nume . '</span>';

    /**
     * „Confirmat acum 3 ore" — de când e pe listă.
     *
     * Acordul după om, ca la rândul de sub butoane: „confirmată" pentru ea,
     * „confirmat" pentru el.
     */
    $cand = ($om['sex'] ?? '') === 'F' ? 'Confirmată ' : 'Confirmat ';
    $cand .= timpRelativ((string) $om['creat_la']);

    /* ---------------------------- insignele --------------------------- */

    $insigne = '';

    if ($eOrganizator) {
        $insigne .= '<span class="person__badge">Organizator</span>';
    } elseif ((int) ($om['este_staff'] ?? 0) === 1) {
        $insigne .= '<span class="person__badge person__badge--staff">Staff</span>';
    }

    /* ----------------------------- butonul ---------------------------- */

    /**
     * Organizatorul nu se scoate de pe lista lui.
     *
     * Nici de el însuși — n-ar avea cui să-și trimită e-mailul de înștiințare
     * și ar rămâne un eveniment fără nimeni care să răspundă de el — nici de
     * staff, care are alte unelte pentru un eveniment care nu-i place: îl
     * poate anula cu totul. Aceeași regulă e verificată din nou în
     * api/exclude-participant.php; aici e doar butonul.
     */
    $buton = ($poateScoate && !$eOrganizator)
        ? '<button class="person__scoate" type="button" data-scoate'
          . ' data-nume="' . $nume . '"'
          . ' aria-label="Scoate-l de pe listă pe ' . $nume . '" title="Scoate de pe listă">'
          . '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
          . '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>'
          . '</svg></button>'
        : '';

    return '<li class="person" data-participant="' . $id . '">'
         . '<img class="person__avatar" src="' . h(urlPoza($om['poza'] ?? null, true)) . '" alt=""'
         . ' width="96" height="96" loading="lazy" decoding="async">'
         . '<div class="person__info">'
         . $legatura
         . '<span class="person__meta">' . h($cand) . '</span>'
         . '</div>'
         . $insigne
         . $buton
         . '</li>';
}

/**
 * Toată lista, gata desenată.
 *
 * Întoarce HTML, nu-l tipărește, fiindcă îl cer două locuri: pagina, când se
 * încarcă, și api/exclude-participant.php, care întoarce lista din nou după
 * fiecare scoatere. Scrise în două locuri, ar fi început să difere.
 */
function randeazaParticipanti(int $evenimentId, int $organizatorId, bool $poateScoate): string
{
    $html = '';

    foreach (participantiiEvenimentului($evenimentId) as $om) {
        $html .= randeazaParticipant($om, $organizatorId, $poateScoate);
    }

    return $html;
}
