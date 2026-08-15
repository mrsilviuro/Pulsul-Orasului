<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — chatul.
 *
 * Camerele, mesajele dintr-o cameră și felul în care arată pe ecran. HTML-ul
 * unui mesaj se scrie AICI și nicăieri altundeva: aceeași funcție îl tipărește
 * la încărcarea paginii și îl întoarce prin api/chat-mesaje.php, când browserul
 * întreabă ce s-a mai spus. Cu JSON de date, JS-ul ar fi trebuit să știe și el
 * să deseneze un mesaj — adică a doua descriere a aceluiași lucru, în alt
 * limbaj, care s-ar fi despărțit de prima la întâia corectură, și text venit de
 * la om lipit în pagină fără trecerea prin h().
 */

require_once __DIR__ . '/evenimente.php';

/* ============================== NUMERELE ============================== */

/**
 * Câte mesaje se văd la deschiderea unei camere.
 *
 * Ultimele, nu primele: cine intră într-o discuție vrea să știe ce se vorbește
 * acum, nu cu ce a început acum trei luni.
 */
const CHAT_MESAJE_DEODATA = 50;

/**
 * Cât se așteaptă între două mesaje ale aceluiași om.
 *
 * Două secunde nu se simt: nimeni nu scrie două mesaje cu mâna lui mai repede
 * de atât. E pentru cine ar vrea să umple o cameră dintr-un script.
 *
 * La comentarii sunt cincisprezece secunde, fiindcă acolo un om scrie o dată și
 * pleacă. Aici se vorbește, iar cincisprezece secunde între „da" și „vin și eu"
 * ar fi făcut chatul de nefolosit.
 */
const CHAT_SECUNDE_INTRE_MESAJE = 2;

/**
 * Câte mesaje poate scrie cineva într-un minut.
 *
 * Limita de sus, cea care chiar oprește un script: cu două secunde între mesaje
 * s-ar fi putut scrie treizeci pe minut, la nesfârșit. Douăzeci e mai mult
 * decât scrie un om grăbit, și destul de puțin cât să nu se poată îneca o
 * cameră.
 *
 * Se numără în tabelul propriu al funcției, `mesaje_chat`, nu într-un sistem
 * separat: aceeași alegere ca la conturile noi, la mesajele de contact și la
 * comentarii. `incercari_autentificare` rămâne doar pentru intrarea în cont,
 * unde numărătoarea duce la blocare — o limită de aici n-are voie să încuie
 * contul cuiva.
 */
const CHAT_MESAJE_PE_MINUT = 20;

/* ============================== CAMERELE ============================== */

/**
 * Ce cameră a cerut omul din adresă.
 *
 * `chat.php?camera=roman`. Numele din adresă e cel prietenos — slugul unui oraș
 * sau al unui eveniment — iar cheia din bază are felul camerei în față
 * ('general', 'oras:roman', 'ev:<slug>'). Vezi sql/019-chat.sql pentru de ce.
 *
 * Ordinea întrebărilor, care e și regula cerută:
 *
 *   1. EVENIMENTELE ÎNTÂI. Slugul se caută în tabelul `evenimente`; dacă e
 *      acolo și omul are voie să-l vadă, aceea e camera.
 *   2. Apoi ORAȘELE din config.php, după slugul numelui.
 *   3. Orice altceva înseamnă camera GENERALĂ.
 *
 * Al treilea pas nu e o scăpare, e regula: un nume care nu duce nicăieri
 * deschide chatul general, nu un ecran roșu. Camera unui eveniment șters, o
 * adresă veche dintr-o zi în care exista alt oraș, o literă greșită la scris —
 * toate duc unde e lume, nu într-o eroare.
 *
 * „Nu există" și „n-ai voie" primesc același răspuns, ca peste tot pe site: din
 * ce cameră se deschide nu trebuie să se poată afla dacă un eveniment ascuns
 * există sau nu.
 *
 * $membruId și $eStaff sunt ale celui care se uită — ei hotărăsc pasul 1.
 */
function cameraCeruta(?string $cerut, int $membruId = 0, bool $eStaff = false): array
{
    $cerut = mb_strtolower(trim((string) $cerut), 'UTF-8');

    /* ------------------------ 1. un eveniment? ------------------------ */

    if ($cerut !== '') {
        $eveniment = evenimentDupaSlug($cerut);

        /**
         * Aceleași două întrebări ca la deschiderea paginii evenimentului și ca
         * la comentarii, cu aceleași funcții. O cameră e discuția unui anunț: ce
         * nu se poate vedea nu se poate nici comenta, iar ce nu e publicat n-are
         * încă despre ce să discute.
         *
         * Un eveniment ÎNCHEIAT rămâne cu camera deschisă, ca și cu comentariile:
         * acolo se spune „ce seară a fost", iar asta se spune mai ales după.
         */
        if ($eveniment !== null
            && evenimentPublicat($eveniment)
            && poateVedeaEvenimentul($eveniment, $membruId, $eStaff)) {
            return [
                'cheie'     => 'ev:' . $eveniment['slug'],
                'fel'       => 'eveniment',
                'nume'      => (string) $eveniment['titlu'],
                'slug'      => (string) $eveniment['slug'],
                'eveniment' => $eveniment,
            ];
        }
    }

    /* -------------------------- 2. un oraș? --------------------------- */

    foreach (oraseDisponibile() as $oras) {
        if (slugSimplu($oras) === $cerut && $cerut !== '') {
            return [
                'cheie'     => 'oras:' . $cerut,
                'fel'       => 'oras',
                'nume'      => $oras,
                'slug'      => $cerut,
                'eveniment' => null,
            ];
        }
    }

    /* ------------------------- 3. camera tuturor ---------------------- */

    return [
        'cheie'     => 'general',
        'fel'       => 'general',
        'nume'      => 'General',
        'slug'      => '',
        'eveniment' => null,
    ];
}

/**
 * Camerele dintre care se poate alege în chatul general: „General", apoi
 * orașele din config.php.
 *
 * Nu e un tabel și nu e o listă scrisă de mână: se face din `oraseDisponibile()`,
 * ca tot ce ține de orașe pe site. Un oraș nou = un rând în config.php, atât —
 * camera lui apare singură aici.
 *
 * Camera unui EVENIMENT nu e în listă, dinadins: la ea se ajunge de pe pagina
 * evenimentului, nu dintr-o listă care ar fi crescut cu fiecare anunț publicat.
 */
function camereGenerale(): array
{
    $camere = [[
        'slug'  => '',
        'nume'  => 'General',
        'cheie' => 'general',
    ]];

    foreach (oraseDisponibile() as $oras) {
        $slug = slugSimplu($oras);

        // Un oraș scris numai din semne („???") n-ar avea slug, deci n-ar avea
        // adresă. Nu se întâmplă, dar dacă s-ar întâmpla ar fi o cameră la care
        // nu se poate ajunge — mai bine lipsește decât să stea moartă în listă.
        if ($slug === '') {
            continue;
        }

        $camere[] = [
            'slug'  => $slug,
            'nume'  => $oras,
            'cheie' => 'oras:' . $slug,
        ];
    }

    return $camere;
}

/** Adresa unei camere: „chat.php" pentru General, „chat.php?camera=…" în rest. */
function urlCamera(string $slug): string
{
    return $slug === '' ? 'chat.php' : 'chat.php?camera=' . urlencode($slug);
}

/* ============================== MESAJELE ============================== */

/**
 * Bucata de SELECT care aduce un mesaj cu tot ce trebuie ca să fie desenat.
 *
 * Se scrie o dată și se folosește de trei ori — ultimele mesaje, cele de după
 * un id, unul singur după id. Trei liste de coloane scrise de mână s-ar fi
 * despărțit la prima coloană adăugată, iar randeazaMesaj() ar fi primit un rând
 * cu o cheie lipsă exact pe drumul cel mai rar folosit.
 */
function coloaneMesajChat(): string
{
    return 'SELECT c.id, c.camera, c.mesaj, c.creat_la, c.membru_id, c.sters,
                   m.permalink, m.nume, m.prenume, m.poza, m.poza_actualizata_la,
                   m.stare
              FROM mesaje_chat c
              JOIN membri m ON m.id = c.membru_id';
}

/**
 * Ultimele mesaje dintr-o cameră, în ordinea în care s-au spus.
 *
 * Din bază vin de-a-ndoaselea — cele mai noi întâi, fiindcă altfel LIMIT ar fi
 * tăiat tocmai capătul care ne trebuie — și se întorc pe față aici. Un ORDER BY
 * crescător cu OFFSET ar fi cerut mai întâi un COUNT al camerei, la fiecare
 * încărcare, pentru o cifră care nu se arată nicăieri.
 *
 * Cele șterse nu se aduc deloc: la încărcare, un mesaj șters nu s-a spus
 * niciodată. Piatra lui de mormânt e doar pentru browserele care îl aveau deja
 * pe ecran — vezi mesajeSterseDupa().
 */
function mesajeleCamerei(string $camera, int $cate = CHAT_MESAJE_DEODATA): array
{
    $cate = max(1, min($cate, 200));

    $q = db()->prepare(
        coloaneMesajChat() . '
          WHERE c.camera = ? AND c.sters = 0
          ORDER BY c.id DESC
          LIMIT ' . $cate
    );
    $q->execute([$camera]);

    return array_reverse($q->fetchAll());
}

/**
 * Ce s-a mai spus în cameră după mesajul cu id-ul $dupa.
 *
 * Întrebarea pe care o pune browserul din când în când. $dupa e cel mai mare id
 * pe care îl are deja pe ecran, deci răspunsul e exact ce-i lipsește — nu toată
 * camera, și nici măcar mesajele lui, care au trecut deja pe acolo.
 *
 * Limita de sus e o plasă pentru cazul în care cineva a lăsat fila deschisă
 * peste noapte într-o cameră vorbăreață: mai bine îi lipsesc câteva mesaje
 * vechi decât să primească trei mii deodată.
 */
function mesajeDupa(string $camera, int $dupa, int $cate = CHAT_MESAJE_DEODATA): array
{
    $cate = max(1, min($cate, 200));

    $q = db()->prepare(
        coloaneMesajChat() . '
          WHERE c.camera = ? AND c.sters = 0 AND c.id > ?
          ORDER BY c.id ASC
          LIMIT ' . $cate
    );
    $q->execute([$camera, max(0, $dupa)]);

    return $q->fetchAll();
}

/**
 * Ce s-a mai ȘTERS în cameră după clipa $moment.
 *
 * Cealaltă jumătate a întrebării browserului. Un mesaj șters nu mai apare în
 * niciun răspuns cu mesaje, deci cine îl avea pe ecran l-ar fi ținut acolo până
 * la prima reîncărcare — adică exact omul de la care trebuia să dispară.
 *
 * $moment vine de la SERVER, nu de la ceasul browserului: fiecare răspuns
 * spune „am terminat de socotit la clipa asta", iar întrebarea următoare pleacă
 * de acolo. Cu ceasul browserului, unul rămas în urmă cu un minut ar fi cerut
 * de fiecare dată aceleași ștergeri, iar unul luat înainte le-ar fi sărit.
 */
function mesajeSterseDupa(string $camera, string $moment): array
{
    if ($moment === '') {
        return [];
    }

    $q = db()->prepare(
        'SELECT id FROM mesaje_chat
          WHERE camera = ? AND sters = 1 AND sters_la > ?
          ORDER BY sters_la ASC
          LIMIT 200'
    );
    $q->execute([$camera, $moment]);

    return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
}

/** Un mesaj după id, cu tot ce trebuie ca să fie desenat. */
function mesajChatDupaId(int $id): ?array
{
    $q = db()->prepare(coloaneMesajChat() . ' WHERE c.id = ? LIMIT 1');
    $q->execute([$id]);

    return $q->fetch() ?: null;
}

/**
 * Scrie mesajul și întoarce id-ul lui.
 *
 * Textul intră curat și neescapat, cum l-a scris omul (regula 9 din CLAUDE.md).
 * Ceasul e PHP, nu NOW() (regula 5).
 */
function salveazaMesajChat(string $camera, int $membruId, string $mesaj): int
{
    db()->prepare(
        'INSERT INTO mesaje_chat (camera, membru_id, mesaj, creat_la)
         VALUES (?, ?, ?, ?)'
    )->execute([$camera, $membruId, $mesaj, acum()]);

    return (int) db()->lastInsertId();
}

/**
 * Cât a mai rămas de așteptat până când omul ăsta poate scrie din nou.
 *
 * Întoarce 0 dacă poate scrie acum. Se uită la amândouă limitele și o dă pe cea
 * mai lungă: cea dintre două mesaje (CHAT_SECUNDE_INTRE_MESAJE) și cea pe minut
 * (CHAT_MESAJE_PE_MINUT).
 *
 * Se numără PESTE TOATE CAMERELE, nu doar în cea de față. Altfel cineva care
 * vrea să înece chatul ar fi trecut prin camere pe rând și ar fi avut din nou
 * dreptul la douăzeci de mesaje în fiecare.
 *
 * Ceasul e tot PHP, la fel ca la scriere: cu acum() de-o parte și NOW() de
 * cealaltă, o oră diferență între cele două ar fi însemnat ori nicio limită,
 * ori una veșnică.
 */
function asteptareChat(int $membruId): int
{
    $q = db()->prepare(
        'SELECT MAX(creat_la) AS ultimul,
                SUM(creat_la > ?) AS pe_minut
           FROM mesaje_chat
          WHERE membru_id = ? AND creat_la > ?'
    );

    // Fereastra cea mai largă de care avem nevoie: un minut.
    $unMinut = date('Y-m-d H:i:s', time() - 60);
    $q->execute([$unMinut, $membruId, $unMinut]);

    $r = $q->fetch() ?: [];

    $ultimul = (string) ($r['ultimul'] ?? '');
    $peMinut = (int) ($r['pe_minut'] ?? 0);

    $asteptare = 0;

    if ($ultimul !== '') {
        $trecute   = time() - (int) strtotime($ultimul);
        $asteptare = max($asteptare, CHAT_SECUNDE_INTRE_MESAJE - $trecute);
    }

    /**
     * La limita pe minut, așteptarea e până când cel mai vechi mesaj din
     * fereastră iese din ea — nu un minut întreg. Cine a scris douăzeci în
     * primele zece secunde așteaptă cincizeci, nu șaizeci.
     */
    if ($peMinut >= CHAT_MESAJE_PE_MINUT) {
        $celMaiVechi = db()->prepare(
            'SELECT MIN(creat_la) FROM mesaje_chat WHERE membru_id = ? AND creat_la > ?'
        );
        $celMaiVechi->execute([$membruId, $unMinut]);

        $primul = (string) ($celMaiVechi->fetchColumn() ?: '');

        if ($primul !== '') {
            $asteptare = max($asteptare, 60 - (time() - (int) strtotime($primul)));
        }
    }

    return max(0, $asteptare);
}

/**
 * Șterge un mesaj: rândul rămâne, vorbele pleacă.
 *
 * Textul se golește — tocmai el e motivul pentru care a apăsat cineva pe „×".
 * Rândul rămâne fiindcă browserele care au mesajul deja pe ecran află că a
 * plecat numai dintr-o piatră de mormânt; un DELETE curat le-ar fi lăsat cu el
 * acolo până la prima reîncărcare. Vezi sql/019-chat.sql.
 *
 * Întoarce false dacă mesajul nu există sau era deja șters — a doua apăsare pe
 * același „×", de pe două file deschise, nu e o eroare de arătat nimănui.
 */
function stergeMesajChat(int $id, int $deCatre): bool
{
    $q = db()->prepare(
        'UPDATE mesaje_chat
            SET mesaj = \'\', sters = 1, sters_de = ?, sters_la = ?
          WHERE id = ? AND sters = 0'
    );
    $q->execute([$deCatre, acum(), $id]);

    return $q->rowCount() > 0;
}

/**
 * Are omul ăsta voie să șteargă mesajul ăsta?
 *
 * Deocamdată numai staff-ul, și în orice cameră. Cine scrie ceva ce nu trebuie
 * o face de obicei tocmai pentru că îl vede lumea, iar dacă ar putea șterge și
 * autorul, ar rămâne răspunsurile celorlalți atârnate de o vorbă care nu mai
 * există și pe care n-o mai poate citi nimeni ca s-o judece.
 *
 * Funcția e aici, nu împrăștiată prin API, ca ziua în care se hotărăște altfel
 * — autorul își poate șterge mesajul în primul minut, de pildă — să aibă un
 * singur loc de schimbat.
 */
function poateStergeMesajChat(array $mesaj, int $membruId, bool $eStaff): bool
{
    return $eStaff && $membruId > 0;
}

/* ============================== PE ECRAN ============================== */

/** Contul din spatele mesajului mai e al cuiva? (ca la comentarii) */
function contActivChat(array $m): bool
{
    return ($m['stare'] ?? '') !== 'sters';
}

/**
 * Numele de sub care se arată mesajul.
 *
 * Un cont anonimizat n-are nume de scris: rămâne „Cont șters", ca la
 * comentarii. Mesajele lui rămân în discuție — sunt vorbele cuiva, iar restul
 * discuției atârnă de ele — dar fără nume și fără legătură spre profil.
 */
function numeleDinMesajChat(array $m): string
{
    if (!contActivChat($m)) {
        return 'Cont șters';
    }

    return numeAfisat((string) ($m['nume'] ?? ''), (string) ($m['prenume'] ?? ''));
}

/**
 * Un mesaj, gata desenat.
 *
 * $context ține ce e la fel pentru toate mesajele de pe ecran:
 *
 *   membru_id — cine se uită (0 = nimeni)
 *   e_staff   — cel care se uită e staff, deci vede „×"-urile
 *
 * AL CUI E mesajul hotărăște pe ce parte stă: al meu la dreapta, al altuia la
 * stânga. Nu e împodobire — e singurul lucru care se citește dintr-o privire
 * într-un șir de vorbe, fără să fie nevoie să se citească numele de fiecare
 * dată.
 *
 * De aceea numele nici nu se scrie pe mesajele mele: știu cine sunt. Chipul, la
 * fel — ar fi fost al meu, de cincizeci de ori, pe aceeași coloană.
 */
function randeazaMesajChat(array $m, array $context): string
{
    $id     = (int) $m['id'];
    $alMeu  = (int) $m['membru_id'] === (int) ($context['membru_id'] ?? 0)
              && (int) ($context['membru_id'] ?? 0) > 0;

    $clase = 'chat-msg' . ($alMeu ? ' chat-msg--eu' : '');

    /* ------------------------------ chipul ---------------------------- */

    $activ = contActivChat($m);
    $nume  = h(numeleDinMesajChat($m));

    /**
     * Chipul și numele, numai la mesajele altora. Numele e legătură spre profil
     * când contul mai e al cuiva — de acolo se vede cine e omul cu care
     * vorbești, ce evenimente a organizat, ce note a primit.
     */
    $chip = '';
    $cine = '';

    if (!$alMeu) {
        $poza = $activ ? urlPoza($m['poza'] ?? null, true) : POZA_IMPLICITA;

        $chip = '<img class="chat-msg__avatar" src="' . h($poza) . '" alt=""'
              . ' width="72" height="72" loading="lazy" decoding="async">';

        $cine = ($activ && ($m['permalink'] ?? '') !== '')
            ? '<a class="chat-msg__cine" href="profil.php?m=' . h((string) $m['permalink']) . '">' . $nume . '</a>'
            : '<span class="chat-msg__cine chat-msg__cine--sters">' . $nume . '</span>';
    }

    /* ------------------------------- ora ------------------------------ */

    /**
     * Pe ecran ora scurtă („14:32"), în `datetime` clipa întreagă. Într-un chat
     * „acum 6 ore" n-ajută pe nimeni: aici se caută ora la care s-a spus ceva,
     * ca să se poată lega de restul zilei.
     */
    $creat = (string) $m['creat_la'];
    $ora   = '<time class="chat-msg__ora" datetime="' . h(str_replace(' ', 'T', $creat)) . '">'
           . h(date('H:i', (int) strtotime($creat))) . '</time>';

    /* ------------------------------- „×" ------------------------------ */

    /**
     * Numai staff-ul îl vede. Nu e ascuns cu CSS de restul lumii — nici nu se
     * scrie în pagina lor, iar api/chat.php întreabă din nou la apăsare: un
     * buton care nu e în HTML se poate oricând face dintr-o consolă.
     */
    $sterge = '';

    if (!empty($context['e_staff'])) {
        $sterge = '<button class="chat-msg__sterge" type="button"'
                . ' data-sterge-mesaj="' . $id . '"'
                . ' aria-label="Șterge mesajul lui ' . $nume . '" title="Șterge mesajul">'
                . '<svg viewBox="0 0 24 24" aria-hidden="true">'
                . '<path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>'
                . '</button>';
    }

    /* ----------------------------- mesajul ---------------------------- */

    /**
     * Rândurile omului rămân ale lui: `nl2br` peste textul deja escapat, nu
     * invers. Escaparea vine prima, ca `<br>`-urile pe care le pune nl2br să
     * fie singurele etichete din text.
     */
    $text = nl2br(h((string) $m['mesaj']), false);

    return '<li class="' . $clase . '" data-mesaj="' . $id . '">'
         . $chip
         . '<div class="chat-msg__corp">'
         . ($cine !== '' ? '<div class="chat-msg__antet">' . $cine . '</div>' : '')
         . '<div class="chat-msg__bula">'
         . '<p class="chat-msg__text">' . $text . '</p>'
         . $ora
         . '</div>'
         . $sterge
         . '</li>';
}

/**
 * Un teanc de mesaje, gata desenate.
 *
 * Îl cer două locuri: chat.php, la încărcare, și api/chat-mesaje.php, la
 * fiecare întrebare a browserului. De aceea întoarce un șir, nu tipărește.
 */
function randeazaMesajeChat(array $mesaje, array $context): string
{
    $html = '';

    foreach ($mesaje as $m) {
        $html .= randeazaMesajChat($m, $context);
    }

    return $html;
}
