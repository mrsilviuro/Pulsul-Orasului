<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — zona de administrare: partea comună a tuturor paginilor.
 *
 * Trei lucruri, și toate trei trebuie să stea într-un singur loc:
 *
 *   1. PAZA. cerePazaDeStaff() se cheamă la începutul FIECĂREI pagini de
 *      administrare, înainte să se tipărească ceva. O pagină care și-ar scrie
 *      singură verificarea e o pagină din care într-o zi va lipsi.
 *   2. LISTA SECȚIUNILOR. Din ea se fac și cartonașele de pe admin.php, și
 *      rândul de legături de sus. O secțiune nouă e un rând în tabloul de mai
 *      jos, atât.
 *   3. CIFRELE. Câte lucruri așteaptă în fiecare secțiune. Se cer o dată, cu o
 *      singură trecere prin bază, și se folosesc în amândouă locurile.
 *
 * TOT CE E AICI E NUMAI PENTRU OAMENII CASEI. Fiecare punct de intrare din
 * api/admin.php întreabă din nou, fiindcă o cerere poate veni de oriunde.
 */

require_once __DIR__ . '/evenimente.php';

/**
 * Paza. Nu se mai întoarce dacă omul n-are ce căuta aici.
 *
 * Cine nu e conectat ajunge la login și se întoarce (cereIntrare); cine e
 * conectat dar nu e de-al casei pleacă pe prima pagină — același răspuns ca la
 * un eveniment pe care n-are voie să-l vadă, ca să nu afle nimeni din purtarea
 * site-ului ce pagini de administrare există.
 *
 * $cale trebuie să fie calea paginii de acum, ca întoarcerea de la login să
 * ducă înapoi exact aici.
 */
function cerePazaDeStaff(string $cale): array
{
    $membru = membruCurent();

    if ($membru === null) {
        cereIntrare($cale);
    }

    if (!esteStaff($membru)) {
        header('Location: /index.php');
        exit;
    }

    return $membru;
}

/**
 * Secțiunile zonei de administrare, în ordinea în care se arată.
 *
 *   cheie   — se potrivește cu $adminPagina din fiecare pagină, ca legătura ei
 *             să se aprindă în rândul de sus
 *   cifra   — ce se numără pentru ea, sau null dacă n-are ce (vezi
 *             cifreleAdmin()). Cifra nu e „câte sunt", ci CÂTE AȘTEAPTĂ CEVA:
 *             cartonașul se aprinde numai când e ceva de făcut, iar un teanc
 *             de cifre care se aprind mereu n-ar mai spune nimic.
 */
function sectiuniAdmin(): array
{
    return [
        [
            'cheie'  => 'coduri',
            'href'   => '/coduri.php',
            'titlu'  => 'Abțibilduri',
            'vorba'  => 'Codurile QR „FindMe": fă unele noi, vezi care sunt în joc.',
            'cifra'  => 'coduri',
            'unitate'=> 'în joc',
            'ico'    => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/>'
                      . '<rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/>'
                      . '<rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/>'
                      . '<path d="M13.5 13.5h3v3h-3zM20.5 13.5h-1M20.5 17.5v3h-3M16.5 20.5h-1"/>',
        ],
        [
            'cheie'  => 'evenimente',
            'href'   => '/admin-evenimente.php',
            'titlu'  => 'Evenimente',
            'vorba'  => 'Anunțurile care așteaptă o hotărâre și cele respinse.',
            'cifra'  => 'evenimente',
            'unitate'=> 'în așteptare',
            'ico'    => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/>'
                      . '<path d="M3.5 9.5h17M8 3.5v3M16 3.5v3"/>',
        ],
        [
            'cheie'  => 'comentarii',
            'href'   => '/admin-comentarii.php',
            'titlu'  => 'Comentarii',
            'vorba'  => 'Ce s-a scris de curând, și tot ce a fost raportat.',
            'cifra'  => 'rapoarte',
            'unitate'=> 'raportate',
            'ico'    => '<path d="M20.5 12.5c0 4-3.8 7-8.5 7-1 0-2-.1-2.9-.4L4 21l1.3-3.4'
                      . 'A7.4 7.4 0 0 1 3.5 12.5c0-4 3.8-7 8.5-7s8.5 3 8.5 7Z"/>',
        ],
        [
            'cheie'  => 'contact',
            'href'   => '/admin-contact.php',
            'titlu'  => 'Contact',
            'vorba'  => 'Mesajele primite prin formularul de contact.',
            'cifra'  => 'mesaje',
            'unitate'=> 'necitite',
            'ico'    => '<rect x="3" y="5" width="18" height="14" rx="2.5"/>'
                      . '<path d="m3.8 7 8.2 5.6L20.2 7"/>',
        ],
        [
            'cheie'  => 'useri',
            'href'   => '/admin-useri.php',
            'titlu'  => 'Useri',
            'vorba'  => 'Caută pe cineva, schimbă-i starea sau limita de evenimente.',
            'cifra'  => 'suspendati',
            'unitate'=> 'suspendați',
            'ico'    => '<circle cx="9.5" cy="8" r="3.5"/>'
                      . '<path d="M3 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/>'
                      . '<path d="M16.5 5.2a3.4 3.4 0 0 1 0 5.6"/>'
                      . '<path d="M18.4 14.5c1.8.8 3 2.6 3.1 4.8"/>',
        ],
        [
            'cheie'  => 'evaluari',
            'href'   => '/admin-evaluari.php',
            'titlu'  => 'Evaluări',
            'vorba'  => 'Cine cui a dat stele, de la ce eveniment și când.',
            /**
             * CIFRA E „CELE MICI", nu „câte sunt".
             *
             * Peste tot pe panou, cifra înseamnă CÂTE AȘTEAPTĂ ceva de făcut —
             * iar la evaluări nu așteaptă niciuna: nu se aprobă și nu se
             * resping. Se aprinde deci ce merită o privire: notele de una sau
             * două stele, singurele despre care cineva ar putea veni vreodată
             * să spună că sunt nedrepte. Zero acolo înseamnă „nimeni n-a fost
             * aspru cu nimeni", și asta chiar e o veste bună.
             */
            'cifra'  => 'note_mici',
            'unitate'=> 'sub 3 stele',
            'ico'    => '<path d="m12 3.8 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 10l5.9-.9L12 3.8Z"'
                      . ' fill="none"/><path d="M12 8.5v4M12 15.2v.1"/>',
        ],
        [
            'cheie'  => 'dorinte',
            'href'   => '/admin-dorinte.php',
            'titlu'  => 'Dorințe',
            'vorba'  => 'Tabla cu dorințe: ce așteaptă aprobarea și ce e pe ea acum.',
            'cifra'  => 'dorinte',
            'unitate'=> 'în așteptare',
            'ico'    => '<path d="m12 3.8 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 10l5.9-.9L12 3.8Z"/>',
        ],
    ];
}

/**
 * Câte lucruri așteaptă, în fiecare secțiune.
 *
 * Se cer o dată și se țin minte: le cere și cartonașul de pe admin.php, și
 * rândul de legături de pe fiecare pagină, iar șase interogări făcute de două
 * ori la fiecare încărcare ar fi douăsprezece degeaba.
 *
 * Toate sunt COUNT-uri peste coloane indexate — ieftine. Dacă vreodată nu vor
 * mai fi, locul de schimbat e ăsta, unul singur.
 */
function cifreleAdmin(): array
{
    static $cifre = null;

    if ($cifre !== null) {
        return $cifre;
    }

    $numara = static fn(string $sql): int => (int) db()->query($sql)->fetchColumn();

    return $cifre = [
        // Abțibilde legate de un eveniment și încă negăsite: vânătorile care
        // chiar se joacă acum.
        'coduri'     => $numara('SELECT COUNT(*) FROM coduri_qr
                                  WHERE eveniment_id IS NOT NULL AND gasit_de IS NULL'),

        'evenimente' => $numara('SELECT COUNT(*) FROM evenimente
                                  WHERE stare_moderare = \'in_asteptare\''),

        // Comentarii DISTINCTE raportate, nu numărul de raportări: două
        // degete arătând spre același rând sunt o singură treabă de făcut.
        'rapoarte'   => $numara('SELECT COUNT(DISTINCT comentariu_id) FROM comentarii_rapoarte'),

        'mesaje'     => $numara('SELECT COUNT(*) FROM mesaje_contact WHERE citit_la IS NULL'),
        'suspendati' => $numara('SELECT COUNT(*) FROM membri WHERE stare = \'suspendat\''),
        'dorinte'    => $numara('SELECT COUNT(*) FROM dorinte
                                  WHERE stare_moderare = \'in_asteptare\''),

        /**
         * Notele aspre, singurele despre care poate veni cineva să se plângă.
         * FĂRĂ cele automate: „Nu s-a prezentat" pune o stea, dar aia nu e o
         * părere rea, e o absență însemnată de organizator.
         */
        'note_mici'  => $numara('SELECT COUNT(*) FROM evaluari
                                  WHERE stele <= 2 AND automat = 0'),
    ];
}

/**
 * Rândul de legături de sus, de pe fiecare pagină de administrare.
 *
 * Scris o dată fiindcă îl cer șase pagini. `$acum` e cheia secțiunii pe care
 * se află omul; legătura ei se aprinde și nu mai duce nicăieri — un link către
 * pagina pe care ești deja e o promisiune care nu se ține.
 */
function randeazaMeniulAdmin(string $acum): string
{
    $cifre = cifreleAdmin();
    $html  = '<nav class="admin-nav" aria-label="Zona de administrare">'
           . '<a class="admin-nav__acasa" href="/admin.php">'
           . '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
           . '<path d="M14 6.5 8.5 12l5.5 5.5"/></svg>'
           . '<span>Admin</span></a><ul>';

    foreach (sectiuniAdmin() as $s) {
        $eAici = $s['cheie'] === $acum;
        $cate  = $s['cifra'] !== null ? (int) ($cifre[$s['cifra']] ?? 0) : 0;

        $insemn = $cate > 0
            ? '<span class="admin-nav__cifra">' . $cate . '</span>'
            : '';

        $html .= '<li>' . ($eAici
            ? '<span class="admin-nav__link is-active" aria-current="page">'
              . h($s['titlu']) . $insemn . '</span>'
            : '<a class="admin-nav__link" href="' . h($s['href']) . '">'
              . h($s['titlu']) . $insemn . '</a>') . '</li>';
    }

    return $html . '</ul></nav>';
}

/* ======================= BUCĂȚI CARE SE REPETĂ ======================= */

/**
 * O dată și o oră, scrise scurt: „21 aug 2026, 15:06".
 *
 * În tabelele de administrare nu se scrie „acum trei ore" — omul de casă se
 * uită la ele ca să pună lucrurile cap la cap, iar „acum trei ore" nu se poate
 * pune lângă altceva. Un „—" pentru ce lipsește: acolo, gol înseamnă „nu s-a
 * întâmplat niciodată", și trebuie să se vadă.
 */
function clipaScurta(?string $cand): string
{
    if ($cand === null || $cand === '') {
        return '—';
    }

    $clipa = strtotime($cand);

    return $clipa === false ? '—' : date('j M Y, H:i', $clipa);
}

/**
 * Numele omului, cu legătură spre profilul lui — sau doar numele, dacă n-are
 * profil de arătat (cont șters, rând anonimizat).
 *
 * Se scrie la fel în cinci tabele; scris de cinci ori, ar fi ajuns să arate
 * altfel în al patrulea.
 */
function omulCuLegatura(?string $nume, ?string $prenume, ?string $permalink,
                        ?string $stare = 'activ'): string
{
    $numeAfisat = numeAfisat((string) $nume, (string) $prenume);

    if ($numeAfisat === '') {
        return '<span class="admin-tabel__gol">(fără nume)</span>';
    }

    if ($permalink === null || $permalink === '' || $stare === 'sters') {
        return h($numeAfisat);
    }

    return '<a href="' . h(urlProfil($permalink)) . '">' . h($numeAfisat) . '</a>';
}

/**
 * Un început de text, pentru coloanele în care încape doar atât.
 *
 * inceputDeText() strânge rândurile noi într-un spațiu, ceea ce e chiar ce
 * trebuie într-un tabel: un comentariu scris în cinci paragrafe n-are voie să
 * facă rândul cât un ecran.
 */
function bucataDeText(?string $text, int $caractere = 90): string
{
    $text = trim((string) $text);

    return $text === ''
        ? '<span class="admin-tabel__gol">—</span>'
        : h(inceputDeText($text, $caractere));
}

/* ==================== CE SE ADUCE DIN BAZĂ, PE SECȚIUNI ============== */

/**
 * Câte rânduri se aduc într-un tabel de administrare.
 *
 * Nu e o paginare, e o tăietură. Tabelele astea sunt liste de lucru: ce n-a
 * fost făcut în primele două sute de rânduri n-o să fie făcut nici în al
 * treilea sutar, iar o pagină care aduce tot tabelul se face din ce în ce mai
 * grea, tăcut, până într-o zi când nu se mai deschide deloc.
 */
const ADMIN_RANDURI = 200;

/** Câte comentarii proaspete se arată. */
const ADMIN_COMENTARII = 50;

/**
 * Câți oameni se arată deodată.
 *
 * Cincizeci, așezați după ULTIMA LOGARE — cei care au trecut de curând pe site
 * sunt cei despre care se pune o întrebare. Cine caută pe cineva anume are
 * căutarea de deasupra; cine se uită așa, peste listă, se uită la ce mișcă.
 */
const ADMIN_USERI = 50;

/**
 * Evenimentele dintr-o stare anume, cu organizatorul lor.
 *
 * FĂRĂ COPERTĂ, dinadins: aici e o listă de lucru, nu o vitrină. Două sute de
 * poze aduse ca să fie micșorate la douăzeci de pixeli ar fi îngreunat pagina
 * pentru nimic — omul de casă caută un titlu și un nume, nu o imagine.
 *
 * Rândul poartă totuși `coperta`, fiindcă de ea are nevoie ștergerea: fișierul
 * de pe disc pleacă odată cu anunțul.
 */
function evenimenteDupaStare(string $stare, int $cate = ADMIN_RANDURI): array
{
    $q = db()->prepare(
        'SELECT e.id, e.titlu, e.slug, e.coperta, e.oras, e.locatie,
                e.data_eveniment, e.ora_inceput, e.creat_la, e.stare_moderare,
                e.corectura_ceruta_la,
                c.nume AS categorie,
                m.nume AS org_nume, m.prenume AS org_prenume,
                m.permalink AS org_permalink, m.stare AS org_stare,
                (SELECT COUNT(*) FROM comentarii cm
                  WHERE cm.eveniment_id = e.id) AS cate_comentarii,
                (SELECT COUNT(*) FROM interese_evenimente i
                  WHERE i.eveniment_id = e.id) AS cati_inscrisi
           FROM evenimente e
           JOIN categorii c ON c.id = e.categorie_id
           JOIN membri    m ON m.id = e.membru_id
          WHERE e.stare_moderare = ?
          ORDER BY e.creat_la DESC, e.id DESC
          LIMIT ' . max(1, $cate)
    );
    $q->execute([$stare]);

    return $q->fetchAll();
}

/**
 * Ultimele comentarii scrise pe site, oricare ar fi evenimentul.
 *
 * CU TOT CU CELE GOLITE (pietrele de mormânt): omul de casă se uită aici ca să
 * vadă ce se întâmplă, iar un rând care spune „aici a fost ceva și s-a șters"
 * e chiar o veste. Pe pagina evenimentului ele se arată ca atare; aici se
 * însemnează cu o etichetă.
 */
function ultimeleComentarii(int $cate = ADMIN_COMENTARII): array
{
    $q = db()->prepare(
        'SELECT cm.id, cm.text, cm.sters, cm.creat_la, cm.parinte_id,
                e.titlu AS ev_titlu, e.slug AS ev_slug,
                m.nume, m.prenume, m.permalink, m.stare AS stare_cont,
                (SELECT COUNT(*) FROM comentarii_rapoarte r
                  WHERE r.comentariu_id = cm.id) AS cate_rapoarte
           FROM comentarii cm
           JOIN evenimente e ON e.id = cm.eveniment_id
           JOIN membri     m ON m.id = cm.membru_id
          ORDER BY cm.creat_la DESC, cm.id DESC
          LIMIT ' . max(1, $cate)
    );
    $q->execute();

    return $q->fetchAll();
}

/**
 * Comentariile raportate, cel mai raportat întâi.
 *
 * Aici se vede, în sfârșit, ce s-a raportat: până acum steagul se putea apăsa,
 * dar nimeni n-avea unde să se uite la ce a ieșit din el — vezi
 * sql/020-rapoarte-comentarii.sql.
 *
 * Numărul de raportări NU ajunge niciodată în pagina evenimentului (acolo omul
 * vede doar dacă EL a raportat); aici, da — e tocmai cifra după care se așază
 * treaba.
 */
function comentariiRaportate(int $cate = ADMIN_RANDURI): array
{
    $q = db()->prepare(
        'SELECT cm.id, cm.text, cm.sters, cm.creat_la, cm.parinte_id,
                e.titlu AS ev_titlu, e.slug AS ev_slug,
                m.nume, m.prenume, m.permalink, m.stare AS stare_cont,
                -- `comentarii_rapoarte` n-are coloană `id`: cheia primară e
                -- perechea (comentariu_id, membru_id), tocmai ca nimeni să nu
                -- poată raporta același rând de două ori. Se numără degetele,
                -- adică membrii.
                COUNT(r.membru_id) AS cate_rapoarte,
                MAX(r.creat_la)    AS ultimul_raport
           FROM comentarii_rapoarte r
           JOIN comentarii cm ON cm.id = r.comentariu_id
           JOIN evenimente e  ON e.id  = cm.eveniment_id
           JOIN membri     m  ON m.id  = cm.membru_id
          GROUP BY cm.id
          ORDER BY cate_rapoarte DESC, ultimul_raport DESC
          LIMIT ' . max(1, $cate)
    );
    $q->execute();

    return $q->fetchAll();
}

/** Mesajele de la formularul de contact, cele mai noi întâi. */
function mesajeDeContact(int $cate = ADMIN_RANDURI): array
{
    $q = db()->prepare(
        'SELECT c.id, c.nume, c.prenume, c.email, c.telefon, c.mesaj,
                c.creat_la, c.citit_la,
                m.permalink, m.stare AS stare_cont
           FROM mesaje_contact c
           LEFT JOIN membri m ON m.id = c.membru_id
          ORDER BY c.creat_la DESC, c.id DESC
          LIMIT ' . max(1, $cate)
    );
    $q->execute();

    return $q->fetchAll();
}

/**
 * Oamenii, căutați după nume, prenume, e-mail sau telefon.
 *
 * O singură căutare peste patru coloane: omul de casă are în mână un lucru — un
 * nume auzit, o adresă dintr-un e-mail, un număr de pe o listă de participanți
 * — și nu vrea să aleagă întâi „după ce caut".
 *
 * TELEFONUL SE CAUTĂ ȘI ADUS LA FORMA DIN BAZĂ. În `membri` numărul stă mereu
 * ca „0722334455" (vezi verificaTelefon), dar cine caută îl scrie cum îl are
 * scris în telefon: „+40722334455", „0040 722 334 455". Fără trecerea asta,
 * căutarea ar fi răspuns „nimeni" pentru un număr care există.
 */
function cautaMembri(string $cauta, int $cate = ADMIN_USERI): array
{
    $cauta = trim($cauta);

    $unde   = [];
    $valori = [];

    if ($cauta !== '') {
        // `%` și `_` scrise de om ar fi fost jokeri; se scapă, ca la titluri.
        $bucata = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cauta) . '%';

        $unde[]   = '(m.nume LIKE ? ESCAPE \'\\\\\' OR m.prenume LIKE ? ESCAPE \'\\\\\'
                      OR m.email LIKE ? ESCAPE \'\\\\\' OR m.telefon LIKE ? ESCAPE \'\\\\\'
                      OR CONCAT(m.prenume, \' \', m.nume) LIKE ? ESCAPE \'\\\\\')';
        $valori   = [$bucata, $bucata, $bucata, $bucata, $bucata];

        /**
         * Numărul adus la forma din bază, dacă seamănă a număr.
         *
         * verificaTelefon() întoarce un TABLOU — ['ok', 'curat', 'eroare'] — nu
         * un șir: e făcută pentru formulare, unde eroarea trebuie și ea scrisă
         * undeva. Aici ne trebuie doar ce a ieșit curat, și doar dacă a ieșit:
         * pentru un nume, 'ok' e false, deci nu se lipește nimic la căutare.
         */
        $numar   = verificaTelefon($cauta);
        $telefon = $numar['ok'] ? (string) $numar['curat'] : '';

        if ($telefon !== '') {
            $unde[count($unde) - 1] = rtrim($unde[count($unde) - 1], ')') . ' OR m.telefon = ?)';
            $valori[] = $telefon;
        }
    }

    $q = db()->prepare(
        'SELECT m.id, m.permalink, m.nume, m.prenume, m.email, m.telefon,
                m.poza, m.stare, m.este_staff, m.limita_evenimente_active,
                m.creat_la, m.autentificat_la, m.cerere_stergere,
                (SELECT COUNT(*) FROM evenimente e WHERE e.membru_id = m.id) AS cate_evenimente
           FROM membri m'
        . ($unde !== [] ? ' WHERE ' . implode(' AND ', $unde) : '')
        /**
         * DUPĂ ULTIMA LOGARE, nu după data înscrierii. Un cont făcut acum doi
         * ani, dar folosit ieri, e mai interesant decât unul deschis alaltăieri
         * și lăsat baltă.
         *
         * Cine n-a intrat niciodată (`autentificat_la` NULL) cade la coadă: în
         * MySQL, NULL e „mai mic" decât orice la ORDER BY DESC. E chiar ce
         * trebuie — un cont neatins n-are ce spune nimănui. La egalitate (adică
         * printre cei niciodată conectați) se așază după înscriere.
         */
        . ' ORDER BY m.autentificat_la DESC, m.creat_la DESC, m.id DESC
          LIMIT ' . max(1, $cate)
    );
    $q->execute($valori);

    return $q->fetchAll();
}

/**
 * Dorințele, TOATE, cu omul lor — cele în așteptare întâi, apoi restul de la
 * cele mai noi la cele mai vechi.
 *
 * Ordinea nu e întâmplătoare: în capul listei stă ce are nevoie de o hotărâre.
 * Un tabel așezat numai după dată ar fi ținut dorința netrecută pe la nimeni la
 * mijloc, între alte douăzeci.
 *
 * FĂRĂ TĂIETURĂ, spre deosebire de celelalte liste. Rândurile din `dorinte` nu
 * se șterg niciodată — nici după ce ies de pe tablă — tocmai ca mai târziu să
 * se poată spune câte dorințe și-au pus oamenii de-a lungul timpului. Tabelul
 * ăsta e singurul loc unde se vede tot ce s-a scris vreodată, iar o limită
 * pusă aici ar fi tăiat tocmai istoria pentru care se păstrează rândurile.
 *
 * Și e ieftin: o dorință e un rând de o sută de caractere, iar oamenii își pun
 * cel mult una la șapte zile.
 */
function toateDorintele(): array
{
    $q = db()->prepare(
        'SELECT d.id, d.oras, d.dorinta, d.stare_moderare, d.creat_la, d.publicat_la,
                m.id AS membru_id, m.email, m.nume, m.prenume, m.permalink,
                m.stare AS stare_cont
           FROM dorinte d
           JOIN membri m ON m.id = d.membru_id
          ORDER BY (d.stare_moderare = \'in_asteptare\') DESC,
                   d.creat_la DESC, d.id DESC'
    );
    $q->execute();

    return $q->fetchAll();
}

/* ============================ EVALUĂRILE ============================= */

/**
 * Notele dintre participanți, toate: cine, cui, câte stele, când și de la ce
 * eveniment.
 *
 * DE CE EXISTĂ PAGINA ASTA. Până acum, o notă dată pe nedrept n-avea cui i se
 * spune. Nu se poate retrage, nu se poate raporta, nu există moderare — iar
 * media de pe profilul cuiva se socotește din ele. Cu zece oameni pe site nu
 * conta; cu o sută, o singură stea pusă din supărare rămâne acolo pentru
 * totdeauna, și e singurul loc de pe site unde cineva poate face rău altuia
 * fără ca nimeni să poată îndrepta.
 *
 * SE ADUC ȘI CELE FĂRĂ TEXT. Pe profil se văd doar părerile SCRISE — stelele
 * singure sunt anonime și nici nu se arată (vezi randeazaEvaluari). Dar tocmai
 * ele intră în medie, deci tocmai ele trebuie să se poată vedea de aici. E
 * singurul loc de pe site unde o stea are un nume lângă ea, și de-aia pagina e
 * numai pentru staff.
 *
 * `automat` deosebește „Nu s-a prezentat" — o însemnare a organizatorului, nu
 * o părere — ca omul de casă să nu creadă că cineva a dat o stea din răutate
 * când de fapt a bifat o absență.
 */
function toateEvaluarile(int $cate = ADMIN_RANDURI): array
{
    $q = db()->prepare(
        'SELECT ev.id, ev.stele, ev.text, ev.automat, ev.creat_la, ev.actualizat_la,
                e.titlu AS ev_titlu, e.slug AS ev_slug, e.data_eveniment,
                aut.id   AS autor_id,   aut.nume AS autor_nume,
                aut.prenume AS autor_prenume, aut.permalink AS autor_permalink,
                aut.stare AS autor_stare,
                tin.id   AS tinta_id,   tin.nume AS tinta_nume,
                tin.prenume AS tinta_prenume, tin.permalink AS tinta_permalink,
                tin.stare AS tinta_stare
           FROM evaluari ev
           JOIN evenimente e ON e.id  = ev.eveniment_id
           JOIN membri aut   ON aut.id = ev.evaluator_id
           JOIN membri tin   ON tin.id = ev.evaluat_id
          ORDER BY ev.creat_la DESC, ev.id DESC
          LIMIT ' . max(1, $cate)
    );
    $q->execute();

    return $q->fetchAll();
}

/**
 * Câte note a dat fiecare om, cu media lor.
 *
 * Tabelul mic de deasupra celui mare: „cine, câte stelușe a oferit". De aici se
 * vede dintr-o privire cine împarte note cu mâna largă și cine dă numai unu —
 * adică tocmai ce nu se poate vedea uitându-te la o notă singură.
 *
 * Cei care au dat mai multe, primii: o singură notă de unu poate fi o seară
 * proastă, douăzeci sunt un obicei.
 */
function ceAuDatOamenii(int $cate = ADMIN_USERI): array
{
    $q = db()->prepare(
        'SELECT m.id, m.nume, m.prenume, m.permalink, m.stare AS stare_cont,
                COUNT(*)          AS cate,
                AVG(ev.stele)     AS media,
                SUM(ev.automat)   AS cate_automate,
                MIN(ev.stele)     AS cea_mai_mica,
                MAX(ev.creat_la)  AS ultima
           FROM evaluari ev
           JOIN membri m ON m.id = ev.evaluator_id
          GROUP BY m.id, m.nume, m.prenume, m.permalink, m.stare
          ORDER BY cate DESC, ultima DESC
          LIMIT ' . max(1, $cate)
    );
    $q->execute();

    return $q->fetchAll();
}

/** O evaluare anume, cu numele celor doi — pentru ștergerea din api/admin.php. */
function evaluareaDupaId(int $id): ?array
{
    $q = db()->prepare(
        'SELECT ev.id, ev.stele, ev.text, ev.evaluat_id, ev.evaluator_id,
                e.titlu AS ev_titlu,
                tin.prenume AS tinta_prenume
           FROM evaluari ev
           JOIN evenimente e ON e.id  = ev.eveniment_id
           JOIN membri tin   ON tin.id = ev.evaluat_id
          WHERE ev.id = ?
          LIMIT 1'
    );
    $q->execute([$id]);

    return $q->fetch() ?: null;
}

/**
 * Șterge o notă, de tot.
 *
 * A DOUA ȘTERGERE ADEVĂRATĂ DE PE SITE, după cea a unei dorințe — și dinadins:
 * o notă nu se poate goli ca un comentariu, fiindcă nu e un text pe care să-l
 * ștergi lăsând rândul. E o cifră care intră într-o medie. Golită, ar fi rămas
 * acolo tot ca o cifră.
 *
 * Nu pleacă niciun e-mail. Cine a dat nota n-are ce afla: dacă a pus-o din
 * răutate, o veste i-ar spune doar că merită încercat din nou de pe alt cont;
 * dacă a pus-o cinstit, i-ar spune că cineva i-a citit părerea și a hotărât s-o
 * șteargă, ceea ce e mai rău decât tăcerea. Nici cel notat: pentru el, media a
 * fost mereu doar o cifră care se mișcă.
 */
function stergeEvaluarea(int $id): bool
{
    $q = db()->prepare('DELETE FROM evaluari WHERE id = ?');
    $q->execute([$id]);

    return $q->rowCount() === 1;
}
