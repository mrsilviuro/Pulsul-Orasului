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
        'SELECT COUNT(*) FROM evenimente WHERE membru_id = ? AND stare_moderare = \'aprobat\''
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
 * $membruId e 0 pentru cine nu e conectat, deci nu poate nimeri peste
 * membru_id-ul nimănui.
 */
function poateVedeaEvenimentul(array $eveniment, int $membruId, bool $eStaff = false): bool
{
    if ($eveniment['stare_moderare'] === 'anulat') {
        return $eStaff;
    }

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

    if ($eveniment['stare_moderare'] === 'anulat') {
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

            return $slug;
        } catch (PDOException $e) {
            // 23000 = a dat de un index unic. Singurul de aici e slugul.
            if ($e->getCode() !== '23000' || $incercare === 5) {
                throw $e;
            }
        }
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
 * TODO: la implementarea sistemului de interese/participări, ordinea e:
 *   1) AICI, în clipa anulării: e-mail către toți cei interesați/confirmați,
 *      cu textul din motiv_anulare. Ăsta e singurul pas automat.
 *   2) MAI TÂRZIU, ca acțiune de staff, nu automat: curățenia finală —
 *      ștergerea rândului anulat, a înscrierilor și a comentariilor lui, plus
 *      coperta de pe disc (stergeCopertaDeFisier).
 * Ordinea contează: dacă s-ar șterge întâi, n-ar mai avea cui trimite
 * e-mailul și nici ce să scrie în el.
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
