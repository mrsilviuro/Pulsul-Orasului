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
        'SELECT c.id, c.parinte_id, c.raspuns_la_id, c.text, c.sters,
                c.creat_la, c.editat_la, c.membru_id,
                m.permalink, m.nume, m.prenume, m.poza,
                m.stare AS stare_cont, m.este_staff,

                (SELECT COUNT(*) FROM comentarii_aprecieri a
                  WHERE a.comentariu_id = c.id) AS aprecieri,

                (SELECT COUNT(*) FROM comentarii_aprecieri a
                  WHERE a.comentariu_id = c.id AND a.membru_id = ?) AS apreciat,

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

    $q->execute([$membruId, $evenimentId]);

    return $q->fetchAll();
}

/**
 * Rândurile plate, așezate în fire.
 *
 * Întoarce o listă de principale, fiecare cu răspunsurile lui sub cheia
 * „raspunsuri".
 *
 * Principalele după aprecieri, apoi de la nou la vechi. Sus stă ce a găsit
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
                creat_la, editat_la
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
 */
function salveazaComentariu(int $evenimentId, int $membruId, string $text, ?array $catre = null): int
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
                (eveniment_id, membru_id, parinte_id, raspuns_la_id, text, creat_la)
         VALUES (?,?,?,?,?,?)'
    );

    $q->execute([$evenimentId, $membruId, $parinteId, $raspunsLaId, $text, acum()]);

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

/* ========================= CUM SE ARATĂ PE ECRAN ====================== */

/** Contul din spatele comentariului mai e al cuiva? */
function contActiv(array $c): bool
{
    return ($c['stare_cont'] ?? '') !== 'sters';
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
        return 'Utilizator șters';
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
        ? '<a class="comment__author" href="profil.php?m=' . h((string) $c['permalink']) . '">' . $nume . '</a>'
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
                    ? '<a class="comment__mentiune" href="profil.php?m=' . h($tinta['permalink']) . '">'
                      . $numeTinta . '</a>'
                    : '<span class="comment__mentiune">' . $numeTinta . '</span>')
                // Spațiul stă AICI, nu în CSS: el desparte două cuvinte, iar
                // la copierea textului trebuie să vină cu ele.
                . ' ';
        }
    }

    /* ----------------------------- uneltele --------------------------- */

    $unelte = randeazaUneltele($c, $context);

    return '<article class="comment__body">'
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
         . '<div class="comment__cand">' . $ora . $editat . '</div>'
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

    if (!empty($context['poate_scrie'])) {
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
