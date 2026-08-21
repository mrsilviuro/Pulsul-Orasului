<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — „FindMe": abțibildele cu coduri QR ascunse prin oraș.
 *
 * Jocul, în trei clipe:
 *
 *   1. Omul de casă face un cod de pe coduri.php, îl tipărește pe abțibild și
 *      îl lipește undeva. Codul există, dar nu duce nicăieri.
 *   2. Publică evenimentul cu acel cod în formular. Vânătoarea a început, iar
 *      pe pagina anunțului merge o numărătoare inversă.
 *   3. Cine găsește abțibildul îl scanează. Dacă e primul, câștigă: numele lui
 *      apare pe pagina evenimentului, iar evenimentul se declară încheiat.
 *
 * TOT CE ȚINE DE CODURI STĂ AICI: și interogările, și cum arată caseta de pe
 * pagina evenimentului. Un singur loc, ca la evenimente și la comentarii — HTML
 * scris de două ori se ceartă cu el însuși la a treia schimbare.
 *
 * Regula de aur a fișierului: „e joc cu abțibilde?" nu se întreabă niciodată
 * după numele sau slugul categoriei, ci după steagul `categorii.joc_qr`. Vezi
 * esteJocQr() și antetul lui sql/025-coduri-qr.sql.
 */

require_once __DIR__ . '/evenimente.php';

/* ====================== CARE CATEGORII SUNT JOC ====================== */

/**
 * Id-urile categoriilor care sunt un joc cu abțibilde.
 *
 * Pentru verificaEveniment(), care de aici află dacă trebuie să ceară un cod.
 * Se ia din aceeași listă ca restul categoriilor, ca să nu fie încă o
 * interogare: categoriiEvenimente(true) e deja ținută minte în memorie.
 *
 * CU TOT CU CELE ȚINUTE PENTRU CASĂ, dinadins. Steagurile sunt două și nu se
 * confundă: `doar_staff` spune cine poate publica, `joc_qr` spune ce fel de
 * eveniment iese. Astăzi „FindMe" le are pe amândouă, dar dacă mâine jocul se
 * deschide către toată lumea, întrebarea de aici trebuie să rămână la fel.
 */
function idCategoriiJocQr(): array
{
    $id = [];

    foreach (categoriiEvenimente(true) as $categorie) {
        if ((int) ($categorie['joc_qr'] ?? 0) === 1) {
            $id[] = (int) $categorie['id'];
        }
    }

    return $id;
}

/**
 * Evenimentul ăsta e o vânătoare de abțibilde?
 *
 * Steagul călătorește cu rândul evenimentului (`c.joc_qr AS categorie_joc_qr`
 * în interogările din inc/evenimente.php), tocmai ca întrebarea să se poată
 * pune oriunde a ajuns rândul, fără încă o interogare.
 *
 * De ea atârnă trei lucruri pe pagina evenimentului: caseta „te interesează?"
 * dispare, taburile cu participanți și interesați dispar, iar în locul lor
 * apare caseta vânătorii.
 */
function esteJocQr(?array $eveniment): bool
{
    return $eveniment !== null && (int) ($eveniment['categorie_joc_qr'] ?? 0) === 1;
}

/* =========================== CODURILE ================================ */

/** De câte ori se încearcă un cod nou până se dă bătut. */
const COD_QR_INCERCARI = 8;

/**
 * Un cod nou, scris în bază. Întoarce codul, sau '' dacă n-a ieșit.
 *
 * Zarurile se pot lovi de un cod care există deja; atunci se aruncă din nou.
 * Coliziunea NU se caută cu un SELECT înainte, ci se lasă să se izbească de
 * cheia unică `uq_cod` — între „am întrebat dacă e liber" și „l-am scris" încap
 * două cereri venite în aceeași clipă, iar cheia e singura care nu se înșală.
 *
 * Același tipar ca la slugul unui eveniment (salveazaEveniment).
 */
function faCodQrNou(int $creatDe): string
{
    $q = db()->prepare(
        'INSERT INTO coduri_qr (cod, creat_de, creat_la) VALUES (?,?,?)'
    );

    for ($incercare = 1; $incercare <= COD_QR_INCERCARI; $incercare++) {
        $cod = parolaTemporaraNoua(COD_QR_LUNGIME);

        try {
            $q->execute([$cod, $creatDe, acum()]);
            return $cod;
        } catch (PDOException $e) {
            // 23000 = a dat de un index unic. Singurul de aici e codul.
            if ($e->getCode() !== '23000' || $incercare === COD_QR_INCERCARI) {
                throw $e;
            }
        }
    }

    return '';
}

/**
 * Bucata de SELECT care aduce, pe lângă rândul codului, și ce se vede pe ecran:
 * evenimentul de care ține și omul care l-a găsit.
 *
 * Scrisă o dată fiindcă o cer trei locuri (findme.php, pagina evenimentului,
 * lista staff-ului), iar trei interogări scrise de mână ar fi ajuns să aducă
 * lucruri ușor diferite.
 */
const CQR_SELECT = '
    SELECT q.*,
           e.slug        AS ev_slug,
           e.titlu       AS ev_titlu,
           e.oras        AS ev_oras,
           e.data_eveniment, e.ora_inceput,
           e.stare_moderare,
           m.prenume     AS gasit_prenume,
           m.nume        AS gasit_nume,
           m.poza        AS gasit_poza,
           m.permalink   AS gasit_permalink,
           m.stare       AS gasit_stare
      FROM coduri_qr q
      LEFT JOIN evenimente e ON e.id = q.eveniment_id
      LEFT JOIN membri     m ON m.id = q.gasit_de';

/**
 * Codul scris pe un abțibild. null dacă nu există așa ceva.
 *
 * $cod vine din adresă, deci trece întâi prin curataCodQr(): un „findme.php?qr="
 * cu orice înăuntru nu are voie să ajungă până la bază.
 */
function codQrDupaCod(?string $cod): ?array
{
    $cod = curataCodQr($cod);

    if ($cod === '') {
        return null;
    }

    $q = db()->prepare(CQR_SELECT . ' WHERE q.cod = ?');
    $q->execute([$cod]);

    $rand = $q->fetch();

    return $rand === false ? null : $rand;
}

/** Abțibildul unui eveniment, dacă are unul. */
function codQrAlEvenimentului(int $evenimentId): ?array
{
    $q = db()->prepare(CQR_SELECT . ' WHERE q.eveniment_id = ?');
    $q->execute([$evenimentId]);

    $rand = $q->fetch();

    return $rand === false ? null : $rand;
}

/**
 * Câte coduri se aduc din bază pentru pagina staff-ului, și câte se văd din
 * prima.
 *
 * Cincizeci, nu toate: dincolo de ele sunt abțibilde de acum trei luni, demult
 * dezlipite, iar un șir care curge o jumătate de metru în jos nu e o listă, e
 * un morman. Cine chiar are treabă cu unul vechi îl găsește în phpMyAdmin.
 *
 * Din cele cincizeci se văd zece; restul intră în pagină, dar ascunse, și se
 * aprind din „Vezi mai mult" — același tipar ca la comentarii și la listele de
 * oameni, unde numărul stă tot într-un `data-deodata`, ca să fie scris într-un
 * singur loc.
 */
const CODURI_QR_PASTRATE = 50;
const CODURI_QR_DEODATA  = 10;

/**
 * Codurile, cel mai nou întâi — pentru pagina staff-ului.
 *
 * Fără paginare adevărată, dinadins: abțibildele se tipăresc pe hârtie și se
 * lipesc de mână prin oraș, deci n-o să fie niciodată mii de care să-ți pese
 * deodată. Se taie sec la CODURI_QR_PASTRATE.
 */
function toateCodurileQr(int $cate = CODURI_QR_PASTRATE): array
{
    $q = db()->prepare(CQR_SELECT . ' ORDER BY q.id DESC LIMIT ' . max(1, $cate));
    $q->execute();

    return $q->fetchAll();
}

/**
 * Se poate șterge abțibildul ăsta?
 *
 * NU, dacă a fost găsit — și e singura oprire.
 *
 * Rândul acela nu mai e o unealtă, e istoria cuiva: de el atârnă cifra „Coduri
 * QR găsite" de pe profilul câștigătorului. Un om de casă care face curat prin
 * listă n-are de unde să știe că apăsând un „×" scade cu unu ceva de pe pagina
 * altcuiva. Aceeași regulă ca peste tot pe site: ce a fost nu se șterge —
 * contul se anonimizează, comentariul se golește, dorința rămâne în tabel.
 *
 * Cele încă nefolosite și cele aflate în joc se șterg: primele sunt hârtie
 * netipărită, iar la a doua vânătoarea rămâne fără abțibild — supărător, dar
 * al organizatorului, care poate lega altul din „Editează".
 */
function poateFiStersCodul(array $cod): bool
{
    return $cod['gasit_de'] === null;
}

/**
 * Șterge un cod. Întoarce true dacă rândul chiar a plecat.
 *
 * `gasit_de IS NULL` stă în WHERE, nu doar în întrebarea de dinainte: între
 * apăsarea „×"-ului și fapta asta încape scanarea care tocmai a câștigat, iar
 * pagina din fața omului nu știe de ea.
 */
function stergeCodulQr(int $id): bool
{
    $q = db()->prepare('DELETE FROM coduri_qr WHERE id = ? AND gasit_de IS NULL');
    $q->execute([$id]);

    return $q->rowCount() === 1;
}

/**
 * În ce stare e abțibildul. Un singur cuvânt, ca să nu se citească de fiecare
 * dată trei coloane și să se ajungă, undeva, la altă socoteală:
 *
 *   'nefolosit' — tipărit, poate lipit, dar niciun eveniment nu ține de el.
 *                 Cine îl scanează află că vânătoarea n-a început.
 *   'in_joc'    — are eveniment și nu l-a găsit nimeni. Se poate câștiga.
 *   'gasit'     — s-a terminat, are câștigător.
 */
function stareaCoduluiQr(array $cod): string
{
    if ($cod['gasit_de'] !== null) {
        return 'gasit';
    }

    return $cod['eveniment_id'] === null ? 'nefolosit' : 'in_joc';
}

/* ================== LEGAREA CODULUI DE EVENIMENT ===================== */

/**
 * De ce nu se poate lega codul ăsta de evenimentul ăsta — sau '' dacă se poate.
 *
 * Întrebarea de dinainte de salvare, cea care dă vorba din formular. Adevărul
 * îl spune tot legaCodulDeEveniment(), care scrie sub o cheie; asta e aici ca
 * omul să afle CE e în neregulă, nu doar că n-a mers.
 *
 * $evenimentId e anunțul care se salvează, sau null la unul nou. De el atârnă
 * un singur caz, dar unul care se întâmplă des: la editarea unei vânători, fără
 * să se umble la cod, codul e deja al acelui eveniment — și asta nu e „ocupat".
 */
function deCeNuSePoateLega(string $cod, ?int $evenimentId = null): string
{
    $randul = codQrDupaCod($cod);

    if ($randul === null) {
        return 'necunoscut';
    }

    if ($randul['gasit_de'] !== null) {
        return 'gasit';
    }

    if ($randul['eveniment_id'] !== null
        && (int) $randul['eveniment_id'] !== (int) $evenimentId) {
        return 'ocupat';
    }

    return '';
}

/** Vorba pe care o vede omul în formular pentru fiecare dintre ele. */
function vorbaDespreCodulQr(string $de_ce): string
{
    return [
        'necunoscut' => 'Nu există niciun abțibild cu codul ăsta. Verifică-l pe pagina codurilor.',
        'ocupat'     => 'Codul ăsta e deja al altui eveniment. Fă unul nou și lipește alt abțibild.',
        'gasit'      => 'Abțibildul ăsta a fost deja găsit — vânătoarea lui s-a încheiat.',
    ][$de_ce] ?? 'Codul ăsta nu se poate folosi.';
}

/**
 * Leagă un cod de un eveniment — pasul care PORNEȘTE vânătoarea.
 *
 * Întoarce un cuvânt, nu true/false, fiindcă lucrurile care pot merge prost
 * sunt diferite și fiecare are altă vorbă de spus în formular:
 *
 *   'gata'        — s-a legat.
 *   'necunoscut'  — nu există niciun abțibild cu codul ăsta.
 *   'ocupat'      — codul e deja al altui eveniment.
 *   'gasit'       — codul a fost deja găsit; abțibildul nu mai e pe stâlp.
 *
 * ATENȚIE la ordinea din UPDATE: se cere `eveniment_id IS NULL` chiar în WHERE,
 * nu doar în întrebarea de dinainte. Două anunțuri publicate în aceeași clipă
 * cu același cod ar fi trecut amândouă de un SELECT, iar al doilea l-ar fi
 * furat pe primul.
 */
function legaCodulDeEveniment(string $cod, int $evenimentId): string
{
    $cod = curataCodQr($cod);

    if ($cod === '') {
        return 'necunoscut';
    }

    $q = db()->prepare(
        'UPDATE coduri_qr SET eveniment_id = ?
          WHERE cod = ? AND eveniment_id IS NULL AND gasit_de IS NULL'
    );
    $q->execute([$evenimentId, $cod]);

    if ($q->rowCount() === 1) {
        return 'gata';
    }

    // N-a mers: se citește rândul ca să se spună DE CE.
    $randul = codQrDupaCod($cod);

    if ($randul === null) {
        return 'necunoscut';
    }

    if ($randul['gasit_de'] !== null) {
        return 'gasit';
    }

    // Deja al altui eveniment — sau chiar al acestuia, dacă se salvează a doua
    // oară aceeași editare. Al doilea caz nu e o greșeală.
    return (int) $randul['eveniment_id'] === $evenimentId ? 'gata' : 'ocupat';
}

/**
 * Desprinde de un eveniment codurile lui.
 *
 * Se cheamă la editare, înainte de a lega codul scris acum: omul poate să fi
 * greșit abțibildul, sau să fi mutat anunțul la altă categorie. Codul desprins
 * se întoarce în „nefolosit" și se poate lega de altceva — abțibildul lui e tot
 * pe stâlp, netipărit degeaba.
 *
 * NU atinge codurile deja găsite: acolo vânătoarea s-a terminat, iar rândul e
 * istorie, nu unealtă.
 */
function dezleagaCodurileEvenimentului(int $evenimentId): void
{
    $q = db()->prepare(
        'UPDATE coduri_qr SET eveniment_id = NULL
          WHERE eveniment_id = ? AND gasit_de IS NULL'
    );
    $q->execute([$evenimentId]);
}

/* ======================= REVENDICAREA ================================ */

/**
 * De ce nu se poate revendica un cod — sau '' dacă se poate.
 *
 * Aceleași cuvinte le folosește și findme.php ca să aleagă ce scrie pe ecran,
 * și revendicaCodul() ca să se oprească înainte de a scrie ceva. Întrebarea se
 * pune într-un singur loc tocmai ca pagina să nu poată spune „ai câștigat" în
 * timp ce baza a hotărât altceva.
 *
 *   'nepornit'  — codul n-are încă eveniment. Abțibildul e lipit, anunțul nu e
 *                 publicat. Cine l-a scanat din greșeală n-a stricat nimic.
 *   'nepublic'  — evenimentul există dar nu se vede pe site (în așteptare,
 *                 respins, anulat). Ca și cum n-ar fi început.
 *   'tarziu'    — a trecut termenul. Vânătoarea s-a încheiat fără câștigător.
 *   'luat'      — l-a găsit altcineva înaintea lui.
 *   'nelogat'   — e bun, dar nu știm cine e. Îl trimitem la login și revine.
 */
function deCeNuSePoateRevendica(array $cod, ?array $membru): string
{
    if ($cod['gasit_de'] !== null) {
        return 'luat';
    }

    if ($cod['eveniment_id'] === null) {
        return 'nepornit';
    }

    // Aceleași stări ca peste tot: „aprobat" și „incheiat" se văd pe site.
    // Un anunț anulat nu mai e o vânătoare, oricât ar fi abțibildul pe stâlp.
    if (!in_array((string) $cod['stare_moderare'], ['aprobat', 'incheiat'], true)) {
        return 'nepublic';
    }

    /**
     * Termenul. „Când o să aibă loc?" înseamnă, la o vânătoare, CLIPA ÎN CARE
     * SE ÎNCHIDE — nu ora la care se strânge lumea, fiindcă la o vânătoare nu
     * se strânge nimeni.
     *
     * momentulInceperii() lipește data de oră, ca peste tot; ceasul e al
     * PHP-ului, niciodată al bazei.
     */
    $termen = momentulInceperii([
        'data_eveniment' => $cod['data_eveniment'],
        'ora_inceput'    => $cod['ora_inceput'],
    ]);

    if ($termen !== null && time() >= $termen) {
        return 'tarziu';
    }

    return $membru === null ? 'nelogat' : '';
}

/**
 * Scrie câștigătorul și încheie evenimentul. Întoarce true dacă chiar el a fost
 * primul.
 *
 * DOUĂ SCHIMBĂRI CARE TREBUIE SĂ SE ÎNTÂMPLE ÎMPREUNĂ: codul primește un
 * câștigător, evenimentul devine „încheiat". Dacă a doua ar pica singură, ar
 * rămâne un anunț cu numărătoarea inversă mergând peste un abțibild deja găsit.
 * De aceea sunt sub aceeași tranzacție.
 *
 * CINE E PRIMUL SE HOTĂRĂȘTE ÎN `WHERE`, nu în PHP. Doi oameni care scanează
 * același abțibild în aceeași secundă trec amândoi de orice verificare de
 * dinainte; dar `gasit_de IS NULL` din UPDATE îl lasă să treacă pe unul singur,
 * fiindcă al doilea găsește rândul deja scris. Al doilea primește false și
 * vede „l-a găsit altcineva" — ceea ce e adevărat.
 */
function revendicaCodul(array $cod, array $membru): bool
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $q = $pdo->prepare(
            'UPDATE coduri_qr SET gasit_de = ?, gasit_la = ?
              WHERE id = ? AND gasit_de IS NULL AND eveniment_id IS NOT NULL'
        );
        $q->execute([(int) $membru['id'], acum(), (int) $cod['id']]);

        if ($q->rowCount() !== 1) {
            $pdo->rollBack();
            return false;
        }

        /**
         * Evenimentul se încheie. Nu prin incheieEveniment(): aceea cere rândul
         * întreg al evenimentului, iar aici avem doar id-ul lui din cod. Fraza
         * e aceeași, cu o singură grijă în plus — nu se atinge un anunț care
         * între timp a fost anulat.
         */
        $q = $pdo->prepare(
            'UPDATE evenimente SET stare_moderare = \'incheiat\', actualizat_la = ?
              WHERE id = ? AND stare_moderare = \'aprobat\''
        );
        $q->execute([acum(), (int) $cod['eveniment_id']]);

        $pdo->commit();

        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/** Câte abțibilde a găsit omul ăsta — cifra de pe profil. */
function cateCoduriQrGasiteDe(int $membruId): int
{
    $q = db()->prepare('SELECT COUNT(*) FROM coduri_qr WHERE gasit_de = ?');
    $q->execute([$membruId]);

    return (int) $q->fetchColumn();
}

/* ====================== CUM ARATĂ PE ECRAN =========================== */

/**
 * Caseta vânătorii, de pe pagina evenimentului — cea care ia locul lui „Ce
 * zici, te interesează?".
 *
 * Are două chipuri, după cum s-a găsit abțibildul sau nu:
 *
 *   NEGĂSIT  — o numărătoare inversă până la termen, cu vorba că abțibildul e
 *              tot pe stâlp. JS-ul o mișcă din secundă în secundă; fără JS
 *              rămâne clipa scrisă în litere, care e adevărul, doar că stă pe
 *              loc.
 *   GĂSIT    — câștigătorul, cu poza și cu legătură spre profilul lui.
 *
 * Se desenează pe server, ca tot restul: cine intră pe pagină vede răspunsul
 * din prima, nu o casetă goală care se umple după ce pornește JS-ul.
 */
function randeazaCasetaFindMe(array $eveniment, ?array $cod): string
{
    $termen = momentulInceperii($eveniment);

    /* --------------------- Abțibildul e găsit --------------------- */
    if ($cod !== null && $cod['gasit_de'] !== null) {
        $nume = numeAfisat((string) $cod['gasit_nume'], (string) $cod['gasit_prenume']);

        /**
         * Contul anonimizat n-are nume și n-are profil. Câștigul rămâne scris
         * în bază — de-aia cheia e RESTRICT — dar pe ecran nu se mai poate
         * arăta spre nimeni.
         */
        $areProfil = (string) $cod['gasit_stare'] === 'activ' && $nume !== '';

        /**
         * Poza MARE, nu cea mică: aici e chipul câștigătorului, nu o bulină
         * dintr-un rând de participanți. urlPoza() cu al doilea argument false
         * dă fișierul întreg — cel mic e făcut pentru 40 de pixeli și s-ar
         * vedea moale la 130.
         */
        $poza = urlPoza($cod['gasit_poza'] ?? null);

        $chip = '<img class="findme__poza" src="' . h($poza) . '" alt=""'
              . ' width="130" height="130" loading="lazy">';

        $cine = $areProfil
            ? '<a class="findme__castigator" href="profil.php?m='
              . h(urlencode((string) $cod['gasit_permalink'])) . '">' . h($nume) . '</a>'
            : '<span class="findme__castigator">Cineva</span>';

        $cand = '';

        if ($cod['gasit_la'] !== null) {
            $clipa = strtotime((string) $cod['gasit_la']);

            if ($clipa !== false) {
                $cand = '<p class="findme__cand">L-a găsit '
                      . h(dataLunga(date('Y-m-d', $clipa), false))
                      . ', la ' . h(date('H:i', $clipa)) . '.</p>';
            }
        }

        /**
         * ALTĂ AȘEZARE decât celelalte două chipuri ale casetei: poza în
         * stânga, pe toată înălțimea, iar cele patru rânduri de text alături.
         *
         * De aceea eticheta, titlul, vorba și data stau împreună într-un
         * `.findme__spus`, nu risipite direct în secțiune: poza trebuie să aibă
         * un singur frate cu care să se alinieze, altfel „pe toată înălțimea"
         * n-ar fi avut față de ce să se măsoare.
         */
        return '<section class="findme findme--gasit" aria-labelledby="findme-title">'
             . '<div class="findme__om">'
             . $chip
             . '<div class="findme__spus">'
             . '<p class="findme__eticheta">Abțibildul a fost găsit</p>'
             . '<h2 class="findme__titlu" id="findme-title">Avem un câștigător</h2>'
             . '<p class="findme__vorba">' . $cine
             . ' a găsit abțibildul și a încheiat vânătoarea.</p>'
             . $cand
             . '</div></div>'
             . '</section>';
    }

    /* ------------------- Abțibildul nu s-a găsit ------------------- */
    $aTrecut = $termen !== null && time() >= $termen;

    if ($aTrecut) {
        return '<section class="findme findme--trecut" aria-labelledby="findme-title">'
             . '<p class="findme__eticheta">Vânătoarea s-a încheiat</p>'
             . '<h2 class="findme__titlu" id="findme-title">Nu l-a găsit nimeni</h2>'
             . '<p class="findme__vorba">Abțibildul a rămas ascuns până la capăt. '
             . 'Data viitoare, poate.</p>'
             . '</section>';
    }

    /**
     * Numărătoarea inversă. `datetime` e clipa în litere, pentru cititoarele de
     * ecran și pentru cine n-are JS; `data-findme-timer` e aceeași clipă în
     * secunde, de care se prinde JS-ul.
     *
     * Fără eveniment legat (`$cod === null`) caseta arată la fel: din afară,
     * un anunț publicat fără cod e tot o vânătoare care n-a pornit. Nu se
     * întâmplă — formularul cere codul — dar pagina n-are voie să se strice
     * dacă cineva umblă în bază.
     */
    $cifre = $termen === null ? '' :
        '<p class="findme__ceas" data-findme-timer="' . (int) $termen . '" role="timer">'
        . '<span class="findme__ceas-cifre">—</span>'
        . '</p>';

    $cand = $termen === null ? '' :
        '<p class="findme__cand">Se închide <time datetime="' . h(date('c', $termen)) . '">'
        . h(dataLunga(date('Y-m-d', $termen), false)) . ', la ' . h(date('H:i', $termen))
        . '</time>.</p>';

    return '<section class="findme findme--cautare" aria-labelledby="findme-title">'
         . '<p class="findme__eticheta">Abțibildul e încă ascuns</p>'
         . '<h2 class="findme__titlu" id="findme-title">Nu l-a găsit nimeni, încă</h2>'
         . '<p class="findme__vorba">Undeva prin oraș e lipit un abțibild cu un cod. '
         . 'Primul care îl scanează câștigă și încheie vânătoarea.</p>'
         . $cifre . $cand
         . '</section>';
}
