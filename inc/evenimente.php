<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — evenimentele.
 *
 * Deocamdată doar publicarea: categoriile, regula „un eveniment activ" și
 * salvarea. Moderarea, editarea și încheierea manuală vin separat.
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

/* ====================== UN EVENIMENT ACTIV ============================ */

/**
 * Ce înseamnă „încheiat", într-un singur loc.
 *
 * Un eveniment se încheie în două feluri: organizatorul îl marchează așa (n-am
 * construit încă butonul), sau trece ziua în care a avut loc. Al doilea nu are
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
    return ['data_eveniment >= ?', date('Y-m-d')];
}

function evenimenteActive(int $membruId): array
{
    [$unde, $azi] = filtruNeincheiat();

    $q = db()->prepare(
        'SELECT id, titlu, slug, data_eveniment, stare_moderare
           FROM evenimente
          WHERE membru_id = ?
            AND stare_moderare <> \'respins\'
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
 * $membruId e 0 pentru cine nu e conectat, deci nu poate nimeri peste
 * membru_id-ul nimănui.
 */
function poateVedeaEvenimentul(array $eveniment, int $membruId): bool
{
    if ((int) $eveniment['membru_id'] === $membruId && $membruId > 0) {
        return true;
    }

    return $eveniment['stare_moderare'] === 'aprobat';
}

/** Adresa paginii unui eveniment. Un singur loc care o știe. */
function urlEveniment(string $slug): string
{
    return 'event.php?slug=' . urlencode($slug);
}

/* ============================= SALVAREA =============================== */

/**
 * Scrie evenimentul în bază. $curat vine gata verificat din verificaEveniment().
 *
 * Slugul se încearcă de câteva ori: coada lui e întâmplătoare, deci o
 * potrivire e foarte puțin probabilă, dar „puțin probabil" nu e „imposibil",
 * iar indexul unic din bază e cel care are ultimul cuvânt.
 */
function salveazaEveniment(int $membruId, array $curat, ?string $coperta): int
{
    $sql = 'INSERT INTO evenimente
                (membru_id, categorie_id, titlu, slug, coperta,
                 data_eveniment, ora_inceput, ora_sfarsit, locatie,
                 cost, varsta_minima, participanti_min, participanti_max,
                 descriere, gen_participanti, stare_moderare, creat_la, actualizat_la)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';

    for ($incercare = 1; $incercare <= 5; $incercare++) {
        try {
            $q = db()->prepare($sql);
            $q->execute([
                $membruId,
                $curat['categorie_id'],
                $curat['titlu'],
                slugEveniment($curat['titlu']),
                $coperta,
                $curat['data_eveniment'],
                $curat['ora_inceput'],
                $curat['ora_sfarsit'],
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

            return (int) db()->lastInsertId();
        } catch (PDOException $e) {
            // 23000 = a dat de un index unic. Singurul de aici e slugul.
            if ($e->getCode() !== '23000' || $incercare === 5) {
                throw $e;
            }
        }
    }

    return 0;
}
