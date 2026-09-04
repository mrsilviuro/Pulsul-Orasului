<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — comentariile de sub un eveniment.
 *
 * Două feluri, nu mai multe: principale și secundare. Un răspuns la un
 * răspuns tot secundar se face — se pune sub același principal și doar SPUNE
 * cui îi răspunde. La al treilea nivel de indentare, pe un telefon, un
 * comentariu are lățimea unui cuvânt.
 *
 * Aici stau și citirea, și scrierea, și CUM ARATĂ pe ecran — ca la
 * inc/afisare-eveniment.php. HTML-ul se scrie o singură dată, fiindcă îl cer
 * două locuri: pagina, când se încarcă, și api/comentarii.php, după fiecare
 * apăsare. Scris în două locuri, ar fi început să difere de la prima
 * corectură.
 */

require_once __DIR__ . '/evenimente.php';
require_once __DIR__ . '/imagini.php';

/**
 * Câte comentarii se văd deodată, înainte de „Vezi mai multe comentarii".
 *
 * Se numără la grămadă, principale și secundare la un loc, în ordinea în care
 * apar pe ecran: cincisprezece rânduri de discuție, nu cincisprezece fire.
 *
 * Numărul stă aici, nu în main.js: pagina îl trimite mai departe printr-un
 * atribut, ca să nu fie scris în două locuri și schimbat doar într-unul.
 */
const COMENTARII_DEODATA = 15;

/**
 * Se poate scrie sub anunțul ăsta?
 *
 * ÎNTREBARE DEOSEBITĂ DE evenimentPublicat(), dinadins, deși până acum era
 * aceeași. Aceea răspunde la „se poartă lumea cu el ca și cu unul de pe site" —
 * de ea atârnă înscrierile, scoaterile de pe listă, încheierea, indexarea la
 * Google. Asta răspunde doar la „au oamenii unde vorbi".
 *
 * TREI STĂRI, nu două:
 *
 *   aprobat  — firește;
 *   incheiat — mai ales: aici se spune cum a fost, iar asta se întâmplă după;
 *   anulat   — ȘI EL. Comentariile erau închise, și era greșit. O ieșire
 *              anulată e tocmai momentul în care oamenii au ceva de zis: „ce
 *              păcat", „mai încercăm?", „eu tot mă duc". Închizându-le, îi
 *              lăsam pe cei care se înscriseseră fără niciun loc în care să-și
 *              răspundă unul altuia — iar organizatorul rămânea fără felul cel
 *              mai firesc de a-și cere scuze.
 *
 * Ce NU se deschide odată cu ele: înscrierile. Nu poți spune „vin" la ceva ce
 * nu se mai ține, iar api/interes.php cere mai departe evenimentPublicat().
 */
function discutiaEDeschisa(array $eveniment): bool
{
    return evenimentPublicat($eveniment)
        || ($eveniment['stare_moderare'] ?? '') === 'anulat';
}

/* ============================== CITIREA ============================== */

/**
 * Toate comentariile unui eveniment, într-o singură cerere.
 *
 * Toate, nu primele cincisprezece: ascunsul e treaba paginii (vezi
 * COMENTARII_DEODATA), nu a bazei. Un eveniment are zeci de comentarii, nu
 * zeci de mii, iar „Vezi mai multe" trebuie să răspundă pe loc, fără să mai
 * întrebe serverul nimic.
 *
 * $membruId e 0 pentru cine nu e conectat — un id peste care nu nimerește
 * niciun rând, deci „apreciat" iese 0 pentru tot, fără nicio ramură în plus.
 *
 * Rândurile vin în ordinea id-ului, adică în ordinea în care s-a scris.
 * Așezarea lor în fire o face grupeazaComentarii(), în PHP: e o mână de
 * rânduri deja citite, iar în SQL ar fi cerut o sortare cu două reguli
 * potrivnice (principalele de la nou la vechi, răspunsurile invers).
 */
function comentariileEvenimentului(int $evenimentId, int $membruId = 0): array
{
    $q = db()->prepare(
        'SELECT c.id, c.parinte_id, c.raspuns_la_id, c.text, c.sters, c.important,
                c.creat_la, c.editat_la, c.membru_id,
                m.permalink, m.nume, m.prenume, m.poza,
                m.stare AS stare_cont, m.este_staff,

                (SELECT COUNT(*) FROM comentarii_aprecieri a
                  WHERE a.comentariu_id = c.id) AS aprecieri,

                (SELECT COUNT(*) FROM comentarii_aprecieri a
                  WHERE a.comentariu_id = c.id AND a.membru_id = ?) AS apreciat,

                -- Dacă EU am raportat comentariul ăsta. Numărul rapoartelor nu
                -- se aduce dinadins: nu se arată nimănui în pagină (vezi
                -- sql/020-rapoarte-comentarii.sql), deci n-are ce căuta aici.
                (SELECT COUNT(*) FROM comentarii_rapoarte r
                  WHERE r.comentariu_id = c.id AND r.membru_id = ?) AS raportat,

                -- Insigna „Participant". Se citește din același tabel din care
                -- se numără participanții de pe butoane, ca să nu spună două
                -- lucruri diferite despre același om.
                (SELECT COUNT(*) FROM interese_evenimente i
                  WHERE i.eveniment_id = c.eveniment_id
                    AND i.membru_id    = c.membru_id
                    AND i.stare        = \'participant\') AS participa

           FROM comentarii c
           JOIN membri m ON m.id = c.membru_id
          WHERE c.eveniment_id = ?
          ORDER BY c.id'
    );

    $q->execute([$membruId, $membruId, $evenimentId]);

    return $q->fetchAll();
}

/**
 * Rândurile plate, așezate în fire.
 *
 * Întoarce o listă de principale, fiecare cu răspunsurile lui sub cheia
 * „raspunsuri".
 *
 * ÎNTÂI CELE DE CĂPĂTÂI, ale organizatorului, oricâte aprecieri ar avea
 * celelalte. Ele nu sunt o părere între păreri, sunt o veste despre eveniment:
 * „ne mutăm pe terenul de alături" trebuie citit de toți, nu ridicat prin vot.
 * Între ele hotărăște doar vechimea, de la nou la vechi — aprecierile nu intră
 * deloc în socoteală, fiindcă ultima veste o înlocuiește pe cea dinainte, iar
 * una veche și îndrăgită n-are ce căuta peste una proaspătă.
 *
 * Restul după aprecieri, apoi de la nou la vechi. Sus stă ce a găsit
 * lumea de cuviință să ridice, iar la egalitate — și mai ales la zero, unde
 * sunt cele mai multe — hotărăște vechimea, ca la orice listă de noutăți.
 *
 * Răspunsurile, invers și fără socoteala aprecierilor: de la vechi la nou.
 * Acolo nu e o listă, e o discuție, iar o discuție se citește de la început.
 * Sortată după aprecieri, ar fi ajuns răspunsul înaintea întrebării la care
 * răspunde.
 */
function grupeazaComentarii(array $randuri): array
{
    $principale = [];
    $raspunsuri = [];

    foreach ($randuri as $rand) {
        if ($rand['parinte_id'] === null) {
            $rand['raspunsuri'] = [];
            $principale[(int) $rand['id']] = $rand;
        } else {
            $raspunsuri[(int) $rand['parinte_id']][] = $rand;
        }
    }

    foreach ($raspunsuri as $parinteId => $aleLui) {
        // Un răspuns rămas fără principal n-ar avea unde să stea. Nu se
        // întâmplă — ștergerea principalului își ia răspunsurile cu ea (vezi
        // stergeComentariu) — dar dacă s-ar întâmpla, e mai bine să lipsească
        // din pagină decât să oprească pagina.
        if (isset($principale[$parinteId])) {
            $principale[$parinteId]['raspunsuri'] = $aleLui;
        }
    }

    $lista = array_values($principale);

    /**
     * Ordinea se face aici, în PHP, nu în SQL.
     *
     * Cererea aduce rândurile după id, adică în ordinea în care s-au scris —
     * o singură trecere prin index, fără sortare. Aici sunt zeci de rânduri
     * deja citite, iar cele două reguli sunt potrivnice (principalele într-un
     * fel, răspunsurile în altul), deci n-ar fi încăput oricum într-un singur
     * ORDER BY.
     *
     * Ordinea se socotește la fiecare încărcare, nu se ține minte nicăieri: o
     * apreciere dată acum mută comentariul abia la următoarea deschidere a
     * paginii. Dinadins — dacă s-ar rearanja sub ochii omului, ar fugi rândul
     * pe care tocmai îl citea.
     */
    usort($lista, static function (array $a, array $b): int {
        $aE = esteImportant($a);
        $bE = esteImportant($b);

        // Cele de căpătâi, deasupra tuturor. Între ele, doar vechimea.
        if ($aE !== $bE) {
            return $bE <=> $aE;
        }

        if ($aE) {
            return (int) $b['id'] <=> (int) $a['id'];
        }

        $dupaAprecieri = (int) ($b['aprecieri'] ?? 0) <=> (int) ($a['aprecieri'] ?? 0);

        return $dupaAprecieri !== 0
            ? $dupaAprecieri
            : (int) $b['id'] <=> (int) $a['id'];
    });

    return $lista;
}

/**
 * Numele scurt al fiecărui comentariu, după id.
 *
 * De el atârnă „către X" de deasupra unui răspuns dat altui răspuns. Se
 * strânge din rândurile deja citite, nu cu încă o cerere: e vorba de numele
 * unor oameni care sunt oricum pe ecran.
 */
function numeleComentatorilor(array $randuri): array
{
    $nume = [];

    foreach ($randuri as $rand) {
        // Un comentariu golit n-are nume de arătat, deci nici de trimis la el.
        if ((int) $rand['sters'] === 1) {
            continue;
        }

        $nume[(int) $rand['id']] = [
            'nume'      => numeleDinComentariu($rand),
            'permalink' => contActiv($rand) ? (string) $rand['permalink'] : '',
        ];
    }

    return $nume;
}

/** Câte comentarii are evenimentul — fără cele golite, care nu spun nimic. */
function numaraComentarii(int $evenimentId): int
{
    $q = db()->prepare(
        'SELECT COUNT(*) FROM comentarii WHERE eveniment_id = ? AND sters = 0'
    );
    $q->execute([$evenimentId]);

    return (int) $q->fetchColumn();
}

/**
 * Un comentariu anume, cu tot ce trebuie ca să se poată hotărî ce se face cu
 * el: al cui e, sub ce eveniment stă și dacă e principal sau răspuns.
 */
function comentariuDupaId(int $id): ?array
{
    $q = db()->prepare(
        'SELECT id, eveniment_id, membru_id, parinte_id, raspuns_la_id, text, sters,
                important, creat_la, editat_la
           FROM comentarii WHERE id = ? LIMIT 1'
    );
    $q->execute([$id]);

    $rand = $q->fetch();

    return $rand !== false ? $rand : null;
}

/** Câte răspunsuri atârnă de un comentariu principal. */
function cateRaspunsuri(int $comentariuId): int
{
    $q = db()->prepare('SELECT COUNT(*) FROM comentarii WHERE parinte_id = ?');
    $q->execute([$comentariuId]);

    return (int) $q->fetchColumn();
}

/**
 * Are omul ăsta voie să umble la comentariul ăsta?
 *
 * Al lui, sau al oricui dacă e staff. Un comentariu deja golit nu se mai
 * atinge: n-are text de schimbat și nici nume de apărat.
 *
 * Aceeași funcție și pentru editare, și pentru ștergere, dinadins: două
 * verificări scrise separat se despart la prima corectură făcută doar în una.
 */
function poateModificaComentariul(array $comentariu, int $membruId, bool $eStaff): bool
{
    if ($membruId <= 0 || (int) $comentariu['sters'] === 1) {
        return false;
    }

    return $eStaff || (int) $comentariu['membru_id'] === $membruId;
}

/**
 * Poate omul ăsta să pună un comentariu „Important" aici?
 *
 * DOAR ORGANIZATORUL, și doar pe un comentariu PRINCIPAL.
 *
 * Nu și staff-ul, deși el poate aproape orice altceva: bifa asta nu e o unealtă
 * de moderare, e vocea celui care ține evenimentul. „Ne mutăm pe terenul de
 * alături" e o vorbă pe care numai el o poate spune — omul casei n-are de unde
 * ști dacă e adevărată. Pe anunțurile lui, puse în numele orașului, el ESTE
 * organizatorul, deci o are oricum.
 *
 * NU LA UN RĂSPUNS: un răspuns stă sub comentariul la care răspunde, deci n-are
 * cum să urce deasupra tuturor, iar un „Important" care nu e primul e o
 * promisiune neținută. $laUnRaspuns spune dacă se răspunde cuiva.
 *
 * Regula se ține ÎNTR-UN SINGUR LOC, de unde o citesc și formularul (dacă se
 * desenează bifa), și api/comentarii.php (dacă se ia în seamă ce s-a trimis).
 * Cea din pagină e o purtare frumoasă; asta de aici e regula — cererea poate
 * veni de oriunde, nu doar de pe bifa aceea.
 */
function poateMarcaImportant(array $eveniment, int $membruId, bool $laUnRaspuns = false): bool
{
    if ($membruId <= 0 || $laUnRaspuns) {
        return false;
    }

    return (int) ($eveniment['membru_id'] ?? 0) === $membruId;
}

/**
 * E de căpătâi comentariul ăsta — și mai are cine să-l citească?
 *
 * Steagul singur nu e de ajuns: un comentariu GOLIT păstrează rândul în bază ca
 * să țină legată discuția de sub el, dar în locul lui scrie „Comentariu șters".
 * Rămas important, o piatră de mormânt ar fi stat pironită în capul listei,
 * deasupra a tot ce mai au oamenii de spus.
 *
 * Întrebarea se pune AICI, la citire, nu se stinge steagul la ștergere: un rând
 * golit de mână din phpMyAdmin — se mai întâmplă pe site-ul ăsta — n-ar fi
 * trecut pe la codul care l-ar fi stins.
 */
function esteImportant(array $c): bool
{
    return (int) ($c['important'] ?? 0) === 1 && (int) ($c['sters'] ?? 0) !== 1;
}

/* ============================== SCRIEREA ============================= */

/**
 * Scrie un comentariu și întoarce id-ul lui.
 *
 * $catre e comentariul pe care s-a apăsat „Răspunde", sau null pentru unul
 * principal. Aici se ține regula celor două niveluri:
 *
 *   - răspuns la un principal → stă sub el, fără mențiune (se vede de unde e)
 *   - răspuns la un răspuns   → stă sub ACELAȘI principal, cu mențiune
 *
 * Adâncimea n-o hotărăște cine apasă, ci locul: oriunde ar apăsa, răspunsul
 * ajunge pe al doilea nivel. De aceea nu primim „ce nivel", ci „la cine".
 *
 * $important vine gata cernut de cel care cheamă funcția — el are în mână și
 * evenimentul, și pe cel care scrie, deci el poate întreba poateMarcaImportant().
 * Aici se scrie doar ce s-a hotărât acolo; funcția asta nu deschide o a doua
 * portiță prin care s-ar putea strecura un steag necuvenit.
 */
function salveazaComentariu(int $evenimentId, int $membruId, string $text,
                            ?array $catre = null, bool $important = false): int
{
    $parinteId   = null;
    $raspunsLaId = null;

    if ($catre !== null) {
        $eSecundar = $catre['parinte_id'] !== null;

        $parinteId = $eSecundar ? (int) $catre['parinte_id'] : (int) $catre['id'];

        // Mențiunea numai când răspunsul nu e limpede din poziție. Sub un
        // principal, primul răspuns e evident pentru cine e; al doilea, dat
        // unui răspuns, nu mai e.
        $raspunsLaId = $eSecundar ? (int) $catre['id'] : null;
    }

    $q = db()->prepare(
        'INSERT INTO comentarii
                (eveniment_id, membru_id, parinte_id, raspuns_la_id, text, important, creat_la)
         VALUES (?,?,?,?,?,?,?)'
    );

    /* Un răspuns nu poate fi de căpătâi, oricât s-ar cere: locul lui e sub
       comentariul la care răspunde, deci n-are cum să stea primul. */
    $important = $important && $parinteId === null;

    $q->execute([$evenimentId, $membruId, $parinteId, $raspunsLaId, $text,
                 $important ? 1 : 0, acum()]);

    return (int) db()->lastInsertId();
}

/**
 * Corectura.
 *
 * `editat_la` se scrie abia acum, nu la naștere: după el se pune „(editat)"
 * lângă oră, iar dacă ar porni egal cu `creat_la`, fiecare comentariu s-ar
 * naște deja corectat.
 */
function actualizeazaComentariu(int $id, string $text): void
{
    $q = db()->prepare('UPDATE comentarii SET text = ?, editat_la = ? WHERE id = ?');
    $q->execute([$text, acum(), $id]);
}

/**
 * Ștergerea — și de ce nu e mereu o ștergere.
 *
 * Un comentariu principal care are răspunsuri nu poate să dispară: ar rămâne
 * suspendate în aer răspunsurile la el, iar discuția n-ar mai avea început.
 * Atunci se golește — rândul rămâne, dar fără text, fără nume și fără chip.
 *
 * Restul se șterg de tot: un principal fără răspunsuri și orice secundar
 * n-au pe cine lăsa atârnat.
 *
 * Și încă un pas, la sfârșit: dacă răspunsul tocmai șters era ultimul de sub
 * o piatră de mormânt, se duce și ea. Rămăsese doar ca să țină discuția
 * legată, iar discuția nu mai e.
 *
 * Întoarce ce s-a întâmplat, ca pagina să știe dacă scoate rândul din listă
 * sau doar îl redesenează:
 *
 *   ['fel' => 'sters'|'golit', 'parinte_sters' => id|null]
 */
function stergeComentariu(array $comentariu): array
{
    $id        = (int) $comentariu['id'];
    $parinteId = $comentariu['parinte_id'] !== null ? (int) $comentariu['parinte_id'] : null;

    $pdo = db();
    $pdo->beginTransaction();

    try {
        /* ---------------------- un comentariu principal ------------------ */
        if ($parinteId === null) {
            if (cateRaspunsuri($id) > 0) {
                // Se golește. Aprecierile se duc: erau pentru ce scria acolo,
                // iar acolo nu mai scrie nimic.
                $pdo->prepare('DELETE FROM comentarii_aprecieri WHERE comentariu_id = ?')
                    ->execute([$id]);

                $pdo->prepare('UPDATE comentarii SET text = \'\', sters = 1, editat_la = ? WHERE id = ?')
                    ->execute([acum(), $id]);

                $pdo->commit();

                return ['fel' => 'golit', 'parinte_sters' => null];
            }

            // Fără răspunsuri: se duce de tot. Aprecierile pleacă odată cu el,
            // prin cheia străină din sql/015-comentarii.sql.
            $pdo->prepare('DELETE FROM comentarii WHERE id = ?')->execute([$id]);
            $pdo->commit();

            return ['fel' => 'sters', 'parinte_sters' => null];
        }

        /* ------------------------- un răspuns ---------------------------- */
        $pdo->prepare('DELETE FROM comentarii WHERE id = ?')->execute([$id]);

        /**
         * A rămas singură piatra de mormânt?
         *
         * Legătura părinte→copil nu e cheie străină (vezi migrarea: două
         * cascade care se întâlnesc pe același tabel sunt exact locul unde
         * InnoDB nu mai garantează nimic), deci pasul ăsta îl facem noi.
         */
        $parinteSters = null;

        $q = $pdo->prepare('SELECT sters FROM comentarii WHERE id = ? LIMIT 1');
        $q->execute([$parinteId]);
        $parinte = $q->fetch();

        if ($parinte !== false && (int) $parinte['sters'] === 1 && cateRaspunsuri($parinteId) === 0) {
            $pdo->prepare('DELETE FROM comentarii WHERE id = ?')->execute([$parinteId]);
            $parinteSters = $parinteId;
        }

        $pdo->commit();

        return ['fel' => 'sters', 'parinte_sters' => $parinteSters];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Apreciere: apasă o dată o pune, apasă din nou o ia înapoi.
 *
 * Ca la „Mă interesează", nu primim „ce să facem", ci „pe ce s-a apăsat":
 * hotărăște serverul, care știe starea adevărată. Un browser rămas cu o
 * pagină veche în față n-are cum să ne pună să numărăm de două ori.
 *
 * Se încearcă întâi ștergerea. Dacă a șters ceva, aprecierea era pusă și
 * tocmai s-a luat înapoi; dacă n-a șters nimic, se pune acum. Așa nu există
 * clipa dintre „citesc dacă există" și „scriu", în care ar încăpea a doua
 * apăsare.
 *
 * INSERT IGNORE și nu INSERT simplu: dacă între ștergere și scriere se
 * strecoară totuși o apăsare de pe alt dispozitiv, a doua nu se sparge de
 * cheia primară — se așază peste aceeași hotărâre.
 */
function comutaApreciere(int $comentariuId, int $membruId): array
{
    $sters = db()->prepare(
        'DELETE FROM comentarii_aprecieri WHERE comentariu_id = ? AND membru_id = ?'
    );
    $sters->execute([$comentariuId, $membruId]);

    $apreciat = $sters->rowCount() === 0;

    if ($apreciat) {
        db()->prepare(
            'INSERT IGNORE INTO comentarii_aprecieri (comentariu_id, membru_id, creat_la)
             VALUES (?,?,?)'
        )->execute([$comentariuId, $membruId, acum()]);
    }

    $q = db()->prepare('SELECT COUNT(*) FROM comentarii_aprecieri WHERE comentariu_id = ?');
    $q->execute([$comentariuId]);

    return ['apreciat' => $apreciat, 'cate' => (int) $q->fetchColumn()];
}

/* ============================== RAPOARTELE =========================== */

/**
 * Are omul ăsta de unde raporta comentariul ăsta?
 *
 * Trei condiții, toate din bun-simț:
 *
 *   - trebuie să fie CONECTAT. Un raport anonim n-ar putea fi nici luat
 *     înapoi, nici cântărit de staff: cinci rapoarte de la același om, de pe
 *     cinci file, arată ca cinci oameni;
 *   - NU POATE FI AL LUI. Cine nu-și mai vrea comentariul îl șterge — are
 *     butonul alături. Un buton de raportat pe propria vorbă n-ar duce
 *     nicăieri și ar cere staff-ului să citească o pâră a omului despre el
 *     însuși;
 *   - comentariul să nu fie deja GOLIT. La o piatră de mormânt („Acest
 *     comentariu a fost șters") nu mai e nimic de citit și nimic de raportat.
 *
 * Aceeași funcție hotărăște și dacă se scrie butonul în pagină, și dacă trece
 * cererea prin api/comentarii.php. Un singur loc, două uși.
 */
function poateRaporta(array $comentariu, int $membruId): bool
{
    if ($membruId <= 0) {
        return false;
    }

    if ((int) ($comentariu['sters'] ?? 0) === 1) {
        return false;
    }

    return (int) $comentariu['membru_id'] !== $membruId;
}

/**
 * Poate omul ăsta să răspundă comentariului ăstuia?
 *
 * NU LA AL LUI. Un răspuns la propria vorbă nu e un răspuns, e o adăugire — iar
 * pentru adăugiri există „Editează", chiar lângă. Butonul îi dădea omului o cale
 * de a-și rupe gândul în două rânduri cu propriul nume de amândouă părțile, ca
 * o discuție cu sine.
 *
 * VIZITATORUL ÎL VEDE, ca la „Apreciază": apăsat, îl duce la intrare, cu
 * întoarcere fix aici. Ascuns, i-ar fi arătat o discuție la care pare că n-are
 * cum să ia parte. De aceea 0 trece — nu e niciodată autorul nimănui.
 *
 * NU întreabă dacă discuția e deschisă: aceea e altă întrebare, a locului, nu a
 * omului (discutiaEDeschisa), și se pune separat în amândouă capetele. Aceeași
 * croială ca poateRaporta(), de mai sus — și tot ca acolo, regula e citită din
 * DOUĂ locuri: rândul de unelte, ca purtare frumoasă, și api/comentarii.php, ca
 * regulă. Cererea poate veni de oriunde, nu doar de pe butonul acela.
 */
function poateRaspunde(array $comentariu, int $membruId): bool
{
    if ((int) ($comentariu['sters'] ?? 0) === 1) {
        return false;
    }

    if ($membruId <= 0) {
        return true;
    }

    return (int) $comentariu['membru_id'] !== $membruId;
}

/**
 * Raportează comentariul, sau ia raportul înapoi dacă era deja dat.
 *
 * Un singur buton pentru amândouă, ca la apreciere: spre server pleacă „am
 * apăsat", nu „vreau să raportez". Așa o filă rămasă deschisă de ieri nu poate
 * cere altceva decât se cuvine — starea adevărată o știe baza, nu browserul.
 *
 * Întoarce dacă e raportat ACUM. Numărul rapoartelor nu se întoarce: nu se
 * arată nimănui în pagină. Un contor la vedere ar fi devenit o unealtă de
 * rușinare publică, și încă una ușor de umflat de câțiva prieteni.
 */
function comutaRaport(int $comentariuId, int $membruId): array
{
    $sters = db()->prepare(
        'DELETE FROM comentarii_rapoarte WHERE comentariu_id = ? AND membru_id = ?'
    );
    $sters->execute([$comentariuId, $membruId]);

    $raportat = $sters->rowCount() === 0;

    if ($raportat) {
        db()->prepare(
            'INSERT IGNORE INTO comentarii_rapoarte (comentariu_id, membru_id, creat_la)
             VALUES (?,?,?)'
        )->execute([$comentariuId, $membruId, acum()]);
    }

    return ['raportat' => $raportat];
}

/**
 * Câte rapoarte are un comentariu.
 *
 * Nu se cheamă de nicăieri din pagină — numărul nu se arată — dar e cifra de
 * care va avea nevoie lista de moderare, și e mai bine să stea aici, lângă
 * celelalte, decât să fie scrisă atunci într-un SELECT de pe altă pagină.
 */
function numaraRapoarte(int $comentariuId): int
{
    $q = db()->prepare('SELECT COUNT(*) FROM comentarii_rapoarte WHERE comentariu_id = ?');
    $q->execute([$comentariuId]);

    return (int) $q->fetchColumn();
}

/* ======================= CINE AFLĂ PE E-MAIL ========================= */

/**
 * Cui i se scrie când apare un comentariu nou — sau nimănui.
 *
 * DOUĂ SITUAȚII, un singur om de fiecare dată:
 *
 *   - comentariu PRINCIPAL → organizatorul evenimentului. E anunțul lui, iar
 *     el e cel care are ce răspunde la o întrebare pusă acolo;
 *   - RĂSPUNS → cel căruia i se răspunde, oricât de adânc ar fi în discuție.
 *     Și la un răspuns dat unui răspuns, și la unul dat sub un comentariu
 *     principal, omul înștiințat e AUTORUL comentariului pe care s-a apăsat.
 *
 * Organizatorul NU primește nimic pentru răspunsurile de sub anunțul lui.
 * Altfel, la o discuție de treizeci de rânduri ar fi primit treizeci de
 * mesaje pentru vorbe care nu-i erau adresate, și ar fi stins bifa după al
 * treilea.
 *
 * CINE NU PRIMEȘTE NICIODATĂ:
 *
 *   - omul însuși. Cine își răspunde singur, sau cine comentează sub propriul
 *     anunț, știe deja;
 *   - cine a stins bifa din setări (`email_comentarii`);
 *   - un cont care nu mai e activ — suspendat sau anonimizat. La cel
 *     anonimizat adresa nici nu mai e a cuiva (vezi inc/stergere.php).
 *
 * Întoarce rândul omului (id, email, prenume) sau null. NU trimite nimic:
 * e-mailul pleacă din api/comentarii.php, ca la anularea unui eveniment. Aici
 * e stratul care atinge baza, iar un `require` de email.php în fișierul ăsta
 * ar lega două lucruri care n-au de ce să se cunoască.
 */
function omDeInstiintatLaComentariu(array $eveniment, int $autorId, ?array $catre): ?array
{
    $cineId = $catre !== null
        ? (int) $catre['membru_id']
        : (int) ($eveniment['membru_id'] ?? 0);

    // El însuși, sau un comentariu rămas fără autor.
    if ($cineId <= 0 || $cineId === $autorId) {
        return null;
    }

    $q = db()->prepare(
        'SELECT id, email, prenume
           FROM membri
          WHERE id = ? AND stare = \'activ\' AND email_comentarii = 1
          LIMIT 1'
    );
    $q->execute([$cineId]);

    $om = $q->fetch();

    return $om === false ? null : $om;
}

/* ========================= CUM SE ARATĂ PE ECRAN ====================== */

/**
 * Contul din spatele comentariului mai e al cuiva?
 *
 * Întrebarea propriu-zisă stă în esteContSters(), din inc/validare.php, lângă
 * numeAfisat() — o pun toate locurile în care se scrie un nume. Aici a rămas
 * doar scurtătura care ia starea din rândul comentariului.
 */
function contActiv(array $c): bool
{
    return !esteContSters($c['stare_cont'] ?? null);
}

/**
 * Numele de deasupra comentariului: „P. Ionuț".
 *
 * Doar inițiala numelui de familie — aceeași formă ca peste tot pe site, prin
 * aceeași funcție (numeAfisat din inc/validare.php).
 *
 * Contul șters se anonimizează, nu dispare (vezi inc/stergere.php), iar
 * comentariile lui rămân în discuție — sunt vorbele cuiva, iar restul
 * discuției atârnă de ele. Dar omul a plecat de pe site: nu i se mai scrie
 * numele și nu se mai trimite nimeni la profilul lui.
 */
function numeleDinComentariu(array $c): string
{
    if (!contActiv($c)) {
        return NUME_CONT_STERS;
    }

    return numeAfisat((string) $c['nume'], (string) $c['prenume']);
}

/**
 * Insignele de lângă nume.
 *
 * Staff și Organizator se pot arăta amândouă — sunt două lucruri diferite, iar
 * cine le are pe amândouă le merită pe amândouă.
 *
 * „Participant" NU se mai scrie lângă „Organizator": cine pune evenimentul la
 * cale vine la el, iar rândul lui de participant se scrie automat la salvare
 * (vezi faOrganizatorulParticipant). Ar fi fost o insignă care spune ce se
 * înțelege oricum din cealaltă.
 *
 * Contul șters nu poartă nicio insignă: nu mai e nimeni acolo.
 */
function insigneleComentariului(array $c, int $organizatorId): string
{
    if (!contActiv($c)) {
        return '';
    }

    $insigne = '';

    if ((int) ($c['este_staff'] ?? 0) === 1) {
        $insigne .= '<span class="badge badge--staff">Staff</span>';
    }

    if ((int) $c['membru_id'] === $organizatorId) {
        $insigne .= '<span class="badge badge--author">Organizator</span>';
    } elseif ((int) ($c['participa'] ?? 0) > 0) {
        $insigne .= '<span class="badge">Participant</span>';
    }

    /**
     * ANUNȚUL DE CĂPĂTÂI NU POARTĂ PASTILĂ. A purtat una, scrisă „Important",
     * și era una în plus: caseta e deja altfel (vezi `.comment__body--important`
     * din style.css), iar „Organizator" stă chiar lângă ea — două semne pentru
     * același lucru, pe un rând care are și-așa trei.
     *
     * Rămâne însă o vorbă pentru cine NU vede caseta: cititoarele de ecran nu
     * citesc culori și nici dungi în stânga, deci fără rândul ăsta anunțul ar fi
     * fost, pentru un om orb, un comentariu ca oricare altul. Nu se vede pe
     * ecran și nu schimbă nimic din ce a cerut ochiul.
     */
    if (esteImportant($c)) {
        $insigne .= '<span class="sr-only">Anunț important</span>';
    }

    return $insigne;
}

/**
 * Textul, cu paragrafele omului păstrate.
 *
 * Escaparea se face ÎNAINTE de nl2br, ca la descrierea evenimentului: invers,
 * `<br>` ar fi fost și el escapat și s-ar fi citit pe ecran.
 *
 * $mentiune e „@N. Prenume", gata desenat, și intră ÎN text — lipit de primul
 * cuvânt, ca o adresare. Nu deasupra, lângă numele celui care scrie: acolo
 * arăta ca încă o etichetă a autorului, ca insignele, și se citea greșit —
 * „R. Ioana către N. Elena" pare o însușire a lui Ioana, nu începutul vorbei
 * ei. Așa cum e acum, se citește cum se și vorbește: „@N. Elena, la 18:00".
 *
 * În primul paragraf, nu într-unul al lui: un rând numai cu numele ar fi rupt
 * răspunsul în două și ar fi împins vorba mai jos degeaba.
 */
function textulComentariului(string $text, string $mentiune = ''): string
{
    $paragrafe = preg_split('/\n{2,}/', $text) ?: [];
    $html      = '';

    foreach ($paragrafe as $paragraf) {
        if (trim($paragraf) === '') {
            continue;
        }

        $html .= '<p class="comment__text">' . $mentiune . nl2br(h($paragraf), false) . '</p>';

        // Doar o dată, la început. Un „@N. Elena" în capul fiecărui paragraf
        // ar fi părut că i se strigă numele de trei ori.
        $mentiune = '';
    }

    /**
     * Text gol, dar cu mențiune.
     *
     * Nu se poate întâmpla — verificaComentariu() nu lasă să treacă un
     * comentariu gol — dar dacă vreodată ar ajunge unul aici, numele celui
     * căruia i se răspunde n-are de ce să se piardă.
     */
    if ($html === '' && $mentiune !== '') {
        $html = '<p class="comment__text">' . $mentiune . '</p>';
    }

    return $html;
}

/**
 * Un comentariu, fără răspunsurile lui.
 *
 * Întoarce doar `<article class="comment__body">…</article>`, nu și `<li>`-ul
 * din jur — fiindcă asta cere main.js după o editare sau după o golire: să
 * schimbe comentariul pe loc, fără să atingă răspunsurile de sub el. De aceea
 * răspunsurile stau în `<li>`, lângă `article`, nu înăuntrul lui.
 *
 * $context ține ce e la fel pentru toate comentariile de pe pagină:
 *
 *   organizator_id — al cui e evenimentul (pentru insignă)
 *   membru_id      — cine se uită (0 = nimeni)
 *   e_staff        — cel care se uită e staff
 *   poate_scrie    — se mai poate răspunde aici
 *   nume           — numele fiecărui comentariu, pentru „către X"
 */
function randeazaComentariu(array $c, array $context): string
{
    $id     = (int) $c['id'];
    $golit  = (int) $c['sters'] === 1;

    /* ------------------------- piatra de mormânt ---------------------- */

    /**
     * Un principal golit: rândul e acolo doar ca să țină legată discuția de
     * sub el. Fără nume, fără chip, fără nimic de apăsat — nu mai e al
     * nimănui și nu mai are ce să i se facă.
     */
    if ($golit) {
        return '<article class="comment__body comment__body--sters">'
             . '<img class="comment__avatar" src="' . h(POZA_IMPLICITA) . '" alt=""'
             . ' width="96" height="96" loading="lazy" decoding="async">'
             . '<div class="comment__main">'
             . '<div class="comment__head">'
             . '<span class="comment__author comment__author--sters">Comentariu șters</span>'
             . '</div>'
             . '<p class="comment__text comment__text--sters">Acest comentariu a fost șters</p>'
             . '</div></article>';
    }

    /* ------------------------------ chipul ---------------------------- */

    $activ  = contActiv($c);
    $nume   = h(numeleDinComentariu($c));
    $poza   = $activ ? urlPoza($c['poza'] ?? null, true) : POZA_IMPLICITA;

    $autor = ($activ && ($c['permalink'] ?? '') !== '')
        ? '<a class="comment__author" href="' . h(urlProfil((string) $c['permalink'])) . '">' . $nume . '</a>'
        : '<span class="comment__author comment__author--sters">' . $nume . '</span>';

    /* ------------------------------- ora ------------------------------ */

    /**
     * Pe ecran scrie „acum 6 ore", în `datetime` stă clipa exactă. Prima e
     * pentru om, a doua pentru browser și pentru cine citește pagina cu alt
     * program decât ochii.
     */
    $creat = (string) $c['creat_la'];
    $ora   = '<time datetime="' . h(str_replace(' ', 'T', $creat)) . '">'
           . h(timpRelativ($creat)) . '</time>';

    // „(editat)" doar dacă s-a umblat la el. Nu e o rușine, e o lămurire:
    // altfel un răspuns care nu se mai potrivește cu întrebarea pare o
    // neînțelegere.
    $editat = $c['editat_la'] !== null
        ? ' <span class="comment__editat" title="Comentariul a fost editat">(editat)</span>'
        : '';

    /* -------------------------- „@N. Prenume" ------------------------- */

    /**
     * Numai la un răspuns dat altui răspuns. Sub un principal, primul răspuns
     * se vede de la sine pentru cine e; al doilea, nu.
     *
     * Intră ÎN text, în capul primului paragraf, ca o adresare — nu deasupra,
     * lângă numele celui care scrie. Vezi textulComentariului().
     *
     * Dacă cel căruia i se răspundea a șters între timp, nu se scrie nimic:
     * mai bine fără mențiune decât cu una care duce în gol.
     */
    $mentiune = '';

    if ($c['raspuns_la_id'] !== null) {
        $tinta = $context['nume'][(int) $c['raspuns_la_id']] ?? null;

        if ($tinta !== null) {
            /**
             * „@" lipit de nume, amândouă în aceeași legătură: e o adresare
             * întreagă, nu un semn lângă un link.
             *
             * Semnul are învelișul lui fiindcă în Plus Jakarta Sans stă vizibil
             * mai jos decât literele de lângă el — e desenat în jurul liniei de
             * bază, nu deasupra ei, ca la mai toate fonturile. Din CSS se ridică
             * la rând cu numele; fără învelișul ăsta n-ar fi avut de ce să se
             * agațe, ::first-letter neavând ce căuta pe un element din rând.
             *
             * Rămâne de citit cu voce tare, nu e ascuns de cititoarele de ecran:
             * „at N. Elena" spune că e o adresare, pe când numele singur ar
             * părea că răspunsul începe pur și simplu cu el.
             */
            $numeTinta = '<span class="comment__at">@</span>' . h($tinta['nume']);

            $mentiune = ($tinta['permalink'] !== ''
                    ? '<a class="comment__mentiune" href="' . h(urlProfil((string) $tinta['permalink'])) . '">'
                      . $numeTinta . '</a>'
                    : '<span class="comment__mentiune">' . $numeTinta . '</span>')
                // Spațiul stă AICI, nu în CSS: el desparte două cuvinte, iar
                // la copierea textului trebuie să vină cu ele.
                . ' ';
        }
    }

    /* ----------------------------- uneltele --------------------------- */

    $unelte = randeazaUneltele($c, $context);

    /**
     * `id` pe articol, nu pe `<li>`-ul din jur.
     *
     * E ținta linkului din e-mailul de înștiințare: „…/event.php?slug=x#c123"
     * duce browserul fix la comentariul despre care e vorba, oriunde ar fi el
     * în discuție. Aici, fiindcă `<li>`-ul se scrie în TREI locuri (lista
     * întreagă, răspunsurile, și răspunsul întors de api/comentarii.php), iar
     * articolul într-unul singur — ăsta.
     *
     * Piatra de mormânt n-are id: la un comentariu golit nu trimitem pe nimeni.
     */
    /* Un comentariu de căpătâi se vede altfel — puțin, cât să se deosebească
       dintr-o privire. Cum anume, scrie în style.css. */
    $felul = esteImportant($c) ? ' comment__body--important' : '';

    return '<article class="comment__body' . $felul . '" id="c' . $id . '">'
         . '<img class="comment__avatar" src="' . h($poza) . '" alt=""'
         . ' width="96" height="96" loading="lazy" decoding="async">'
         . '<div class="comment__main">'
         /**
          * Antetul, pe două rânduri: cine a scris, și dedesubt când.
          *
          * Toate pe un rând, ora venea după insigne — care sunt când una, când
          * două, când niciuna — deci pornea din alt loc la fiecare comentariu,
          * iar la unul cu nume lung se rupea singură pe rândul următor, aliniată
          * aiurea. Pe rândul ei stă mereu în același loc, sub nume.
          *
          * Punctul dintre ele a plecat odată cu rândul comun: despărțea două
          * lucruri care nu mai sunt unul lângă altul.
          */
         . '<div class="comment__head">'
         . '<div class="comment__cine">'
         . $autor
         . insigneleComentariului($c, (int) $context['organizator_id'])
         . '</div>'
         . '<div class="comment__cand">' . $ora . $editat
         . randeazaSteagulDeRaport($c, $context) . '</div>'
         . '</div>'
         . textulComentariului((string) $c['text'], $mentiune)
         . $unelte
         . '</div></article>';
}

/**
 * Rândul de butoane de sub un comentariu.
 *
 * Aprecierea o vede oricine — și cine nu e conectat: butonul lui duce la
 * intrare, iar numărul de pe el e o veste bună pentru discuție. „Răspunde",
 * „Editează" și „Șterge" apar doar cui au ce să-i folosească.
 *
 * Ce se vede aici e o purtare frumoasă, nu o regulă. Regula e în
 * api/comentarii.php, care întreabă din nou tot ce se întreabă și aici —
 * fiindcă o cerere poate veni de oriunde, nu doar de pe butoanele astea.
 */
function randeazaUneltele(array $c, array $context): string
{
    $apreciat = (int) ($c['apreciat'] ?? 0) > 0;

    $unelte = '<button class="comment__tool" type="button" data-like'
            . ' aria-pressed="' . ($apreciat ? 'true' : 'false') . '"'
            . ' aria-label="Apreciază comentariul">'
            . '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path d="M7 20V9.5l4.2-6a1.6 1.6 0 0 1 2.9 1.2L13.3 9H19a2 2 0 0 1 2 2.4l-1.4 6.4A2.6 2.6 0 0 1 17 20Z"/>'
            . '<path d="M7 9.8H4.2A1.2 1.2 0 0 0 3 11v7.8c0 .7.5 1.2 1.2 1.2H7"/>'
            . '</svg>'
            . '<span data-like-count>' . (int) ($c['aprecieri'] ?? 0) . '</span>'
            . '</button>';

    /* Două întrebări deosebite, și amândouă trebuie să spună da: locul e
       deschis (poate_scrie) ȘI omul are cui răspunde — nu lui însuși. */
    if (!empty($context['poate_scrie'])
        && poateRaspunde($c, (int) $context['membru_id'])) {
        $unelte .= '<button class="comment__tool" type="button" data-reply>Răspunde</button>';
    }

    $alMeu = (int) $context['membru_id'] > 0
          && (int) $c['membru_id'] === (int) $context['membru_id'];

    if ($alMeu || !empty($context['e_staff'])) {
        $unelte .= '<button class="comment__tool" type="button" data-edit>Editează</button>'
                 . '<button class="comment__tool comment__tool--sterge" type="button" data-delete>Șterge</button>';
    }

    return '<div class="comment__tools">' . $unelte . '</div>';
}

/**
 * Steagul de raportat, în antet, imediat după ora comentariului.
 *
 * A stat o vreme la capătul rândului de unelte, printre „Apreciază",
 * „Răspunde", „Editează" și „Șterge" — și era locul greșit. Alea sunt lucruri
 * pe care le faci ție (îți place, răspunzi, îți corectezi vorba); raportul e
 * ceva ce faci DESPRE comentariul altuia. Lângă ora la care a fost scris, în
 * antet, se citește ca ce e: o însemnare pe eticheta comentariului, nu încă o
 * unealtă în rândul tău de unelte.
 *
 * ȘI CU VORBA SCRISĂ, nu doar semnul. Un steag singur nu spune nimic nimănui —
 * omul care nu l-a mai văzut nicăieri trebuia să apese ca să afle ce face,
 * adică exact ce nu vrei la un buton care nu se ia înapoi decât apăsând din
 * nou. Acum scrie „Raportează", iar după apăsare „Raportat".
 *
 * Nu se scrie deloc pentru cine n-are ce raporta — nici pentru autorul
 * comentariului, nici pentru cine nu e conectat. Un buton stins, care spune
 * „nu poți", ar fi fost o invitație în plus la o faptă pe care n-o cere
 * nimeni. Fără număr, ca întotdeauna: vezi comutaRaport().
 */
function randeazaSteagulDeRaport(array $c, array $context): string
{
    if (!poateRaporta($c, (int) $context['membru_id'])) {
        return '';
    }

    $raportat = (int) ($c['raportat'] ?? 0) > 0;

    return '<button class="comment__raport' . ($raportat ? ' is-raportat' : '') . '"'
         . ' type="button" data-raport'
         . ' aria-pressed="' . ($raportat ? 'true' : 'false') . '"'
         . ' title="' . ($raportat ? 'Ai raportat comentariul. Apasă ca să retragi.'
                                   : 'Raportează comentariul') . '"'
         . ' aria-label="' . ($raportat ? 'Retrage raportul' : 'Raportează comentariul') . '">'
         . '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
         . '<path d="M5 21V4"/>'
         . '<path d="M5 4.8h9.6l-.9 3.4h5.1l-1.2 5.2H5"/>'
         . '</svg>'
         . '<span data-raport-text>' . ($raportat ? 'Raportat' : 'Raportează') . '</span>'
         . '</button>';
}

/**
 * Un comentariu cu tot cu `<li>`-ul lui și cu răspunsurile de sub el.
 *
 * `data-comentariu` e cum îl găsește main.js: după apăsare, răspunsul
 * serverului spune ce id s-a schimbat, iar pagina caută rândul după atributul
 * ăsta. Fără el, ar trebui numărate pozițiile — iar pozițiile se schimbă la
 * fiecare comentariu nou al altcuiva.
 */
function randeazaComentariuIntreg(array $c, array $context): string
{
    $html = '<li class="comment" data-comentariu="' . (int) $c['id'] . '">'
          . randeazaComentariu($c, $context);

    $raspunsuri = $c['raspunsuri'] ?? [];

    if ($raspunsuri !== []) {
        $html .= '<ul class="comment__replies" data-raspunsuri>';

        foreach ($raspunsuri as $raspuns) {
            $html .= '<li class="comment" data-comentariu="' . (int) $raspuns['id'] . '">'
                   . randeazaComentariu($raspuns, $context)
                   . '</li>';
        }

        $html .= '</ul>';
    }

    return $html . '</li>';
}

/**
 * Toată lista, gata desenată.
 *
 * Toate comentariile intră în pagină, până la ultimul: ascunsul e treaba lui
 * main.js, care lasă la vedere primele COMENTARII_DEODATA și le arată pe
 * celelalte la apăsarea butonului, fără să mai întrebe serverul.
 *
 * De ce așa și nu cerute pe rând, când se apasă: aici discuția e scurtă (zeci
 * de rânduri, nu mii), iar în schimbul câtorva kiloocteți în plus se câștigă
 * un buton care răspunde pe loc, o pagină care se poate căuta cu Ctrl+F
 * întreagă și comentarii pe care le vede și Google.
 */
function randeazaComentarii(array $fire, array $context): string
{
    if ($fire === []) {
        return '';
    }

    $html = '';

    foreach ($fire as $principal) {
        $html .= randeazaComentariuIntreg($principal, $context);
    }

    return $html;
}
