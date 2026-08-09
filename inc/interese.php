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
function randeazaOameniInteresati(int $evenimentId): string
{
    $numar  = numaraInterese($evenimentId);
    $total  = $numar['interesat'] + $numar['participant'];
    $oameni = $total > 0 ? oameniiInteresati($evenimentId) : [];

    if ($total === 0 || $oameni === []) {
        // Nimeni încă. Nu se arată un cerc gol și un „0 persoane": se spune
        // ceva ce se poate face.
        return '<p class="rsvp__note rsvp__note--gol">Fii primul interesat de acest eveniment!</p>';
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

    if (count($legate) === 1) {
        // Un singur om: acordul se face după el, nu după „persoane".
        $interesat = ($numiti[0]['sex'] ?? '') === 'F' ? 'interesată' : 'interesat';
        $vorba = $legate[0] . ' este ' . $interesat . ' de acest eveniment.';
    } elseif ($restul === 0) {
        $vorba = $legate[0] . ' și ' . $legate[1] . ' sunt interesați de acest eveniment.';
    } elseif ($restul === 1) {
        $vorba = $legate[0] . ', ' . $legate[1]
               . ' și încă o persoană sunt interesate de acest eveniment.';
    } else {
        $vorba = $legate[0] . ', ' . $legate[1] . ' și încă ' . $restul
               . ' persoane sunt interesate de acest eveniment.';
    }

    return '<div class="facepile" aria-hidden="true">' . $chipuri . '</div>'
         . '<p class="rsvp__note">' . $vorba . '</p>';
}
