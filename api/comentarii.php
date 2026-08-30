<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — comentariile de sub un eveniment.
 *
 * Cinci lucruri se pot cere de aici: scrierea unui comentariu (principal sau
 * răspuns), corectura, ștergerea, aprecierea și raportarea.
 *
 * Comentariul se întoarce mereu GATA DESENAT, din aceleași funcții care îl
 * scriu la încărcarea paginii (inc/comentarii.php). Nu se lipește HTML în JS:
 * ar fi însemnat două locuri care desenează același lucru, iar al doilea ar fi
 * rămas în urmă de la prima corectură — și ar fi însemnat text venit de la om
 * lipit în pagină fără trecerea prin h().
 */

require_once __DIR__ . '/../inc/comentarii.php';
require_once __DIR__ . '/../inc/interese.php';
require_once __DIR__ . '/../inc/email.php';

/**
 * Cât se așteaptă între două comentarii ale aceluiași om.
 *
 * Nu ca să-l încetinească pe cel care are ceva de spus — cincisprezece secunde
 * nu-l încurcă pe nimeni care scrie cu mâna lui. E pentru cine ar vrea să
 * umple o discuție dintr-un script.
 *
 * Se numără în tabelul propriu al funcției, `comentarii`, nu într-un sistem
 * separat: aceeași alegere ca la conturile noi și la mesajele de contact.
 * `incercari_autentificare` rămâne doar pentru intrarea în cont, unde
 * numărătoarea duce la blocare — o limită de aici n-are voie să încuie contul
 * cuiva.
 */
const SECUNDE_INTRE_COMENTARII = 15;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    raspunsJson(['ok' => false, 'mesaj' => 'Metodă nepermisă.'], 405);
}

$tip = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($tip, 'application/json') !== false) {
    $date = json_decode(file_get_contents('php://input') ?: '', true);
    $date = is_array($date) ? $date : [];
} else {
    $date = $_POST;
}

if (!tokenCsrfValid(is_string($date['csrf'] ?? null) ? $date['csrf'] : '')) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Sesiunea a expirat. Reîncarcă pagina și încearcă din nou.',
    ], 419);
}

/**
 * Fără cont nu se scrie și nu se apreciază nimic.
 *
 * Pagina evenimentului se deschide de oricine, dar sub formularul de
 * comentariu vizitatorul vede o invitație la intrare, nu o casetă de scris.
 * Aici se ajunge doar dacă sesiunea a expirat între încărcarea paginii și
 * apăsare. Codul 401 e semnul după care main.js îl trimite la login, cu
 * întoarcere fix pe evenimentul ăsta.
 */
$membru = membruCurent();

if ($membru === null) {
    raspunsJson(['ok' => false, 'mesaj' => 'Trebuie să fii conectat.'], 401);
}

opresteDacaTrebuieParolaNoua(true);

$membruId = (int) $membru['id'];
$eStaff   = esteStaff($membru);

/* ========================= 1. Care eveniment ========================= */

$slug      = trim((string) ($date['slug'] ?? ''));
$eveniment = evenimentDupaSlug($slug);

/**
 * Aceeași regulă ca la deschiderea paginii, aceeași funcție. Un eveniment pe
 * care n-ai voie să-l vezi e unul sub care n-ai voie să scrii — și tot ca
 * acolo, „nu există" și „nu ai voie" primesc același răspuns.
 */
if ($eveniment === null
    || !poateVedeaEvenimentul($eveniment, $membruId, $eStaff)) {
    raspunsJson(['ok' => false, 'mesaj' => 'Evenimentul nu mai există.'], 404);
}

$evenimentId = (int) $eveniment['id'];

/**
 * Discuția e a evenimentelor care au apucat să se vadă.
 *
 * Cât e în așteptare sau respins, pagina se deschide doar pentru organizator
 * sau pentru staff — n-are cine să discute acolo.
 *
 * Un eveniment ÎNCHEIAT rămâne deschis la comentarii, spre deosebire de
 * listele de participanți. Acolo se închide o socoteală; aici oamenii spun cum
 * a fost, iar asta se întâmplă mai ales după. Un eveniment ANULAT, la fel: e
 * tocmai momentul în care oamenii au ceva de zis. Vezi discutiaEDeschisa() din
 * inc/comentarii.php, unde stă regula întreagă.
 */
if (!discutiaEDeschisa($eveniment)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Evenimentul nu e publicat, deci nu are încă discuție.',
    ], 409);
}

/* ======================== 2. Ce se cere să facem ===================== */

$fapta = (string) ($date['fapta'] ?? '');

switch ($fapta) {
    case 'adauga':
        adaugaComentariul($date, $eveniment, $membruId, $eStaff);
        break;

    case 'editeaza':
        editeazaComentariul($date, $eveniment, $membruId, $eStaff);
        break;

    case 'sterge':
        stergeComentariul($date, $eveniment, $membruId, $eStaff);
        break;

    case 'apreciaza':
        apreciazaComentariul($date, $eveniment, $membruId);
        break;

    case 'raporteaza':
        raporteazaComentariul($date, $eveniment, $membruId);
        break;

    default:
        raspunsJson(['ok' => false, 'mesaj' => 'Nu știu ce să fac cu asta.'], 422);
}

/* ===================================================================== */

/**
 * Ce e la fel pentru toate comentariile de pe pagina asta.
 *
 * Aceeași formă ca la randarea din event.php, ca un comentariu întors de aici
 * să arate exact ca unul desenat la încărcarea paginii.
 */
function contextulPaginii(array $eveniment, int $membruId, bool $eStaff, array $randuri): array
{
    return [
        'organizator_id' => (int) $eveniment['membru_id'],
        'membru_id'      => $membruId,
        'e_staff'        => $eStaff,
        'poate_scrie'    => true, // aici se ajunge doar la un eveniment publicat
        'nume'           => numeleComentatorilor($randuri),
    ];
}

/**
 * Comentariul cerut, din rândurile proaspăt citite — cu tot ce ține de el:
 * aprecieri, insigne, numele celui căruia îi răspunde.
 *
 * Se recitesc toate rândurile evenimentului, nu doar cel scris acum. Pare
 * risipă, dar e singurul fel în care comentariul întors de aici știe TOT ce
 * știe unul desenat la încărcarea paginii — inclusiv numele celui căruia îi
 * răspunde, care e într-un cu totul alt rând.
 */
function comentariulDesenat(int $id, array $eveniment, int $membruId, bool $eStaff): array
{
    $randuri = comentariileEvenimentului((int) $eveniment['id'], $membruId);
    $context = contextulPaginii($eveniment, $membruId, $eStaff, $randuri);

    foreach ($randuri as $rand) {
        if ((int) $rand['id'] === $id) {
            return ['rand' => $rand, 'context' => $context];
        }
    }

    return ['rand' => null, 'context' => $context];
}

/**
 * Comentariul pe care s-a apăsat, verificat că e chiar de sub evenimentul
 * ăsta.
 *
 * Fără verificarea asta, un id ghicit ar fi lăsat pe cineva să șteargă un
 * comentariu de sub cu totul alt eveniment — unul la care poate n-are nici
 * măcar acces.
 */
function comentariulCerut($id, int $evenimentId): array
{
    $id = (int) $id;

    if ($id <= 0) {
        raspunsJson(['ok' => false, 'mesaj' => 'Nu știu despre ce comentariu e vorba.'], 422);
    }

    $comentariu = comentariuDupaId($id);

    if ($comentariu === null || (int) $comentariu['eveniment_id'] !== $evenimentId) {
        raspunsJson(['ok' => false, 'mesaj' => 'Comentariul nu mai există.'], 404);
    }

    return $comentariu;
}

/* --------------------------- a) scrierea ----------------------------- */

function adaugaComentariul(array $date, array $eveniment, int $membruId, bool $eStaff): void
{
    $evenimentId = (int) $eveniment['id'];

    /* --------------------------- textul ------------------------------ */

    $rezultat = verificaComentariu($date['text'] ?? '');

    if ($rezultat['eroare'] !== '') {
        raspunsJson(['ok' => false, 'erori' => ['text' => $rezultat['eroare']]], 422);
    }

    /* ------------------------ prea des ------------------------------- */

    $q = db()->prepare(
        'SELECT creat_la FROM comentarii
          WHERE membru_id = ? ORDER BY id DESC LIMIT 1'
    );
    $q->execute([$membruId]);

    $ultimul = $q->fetchColumn();

    if (is_string($ultimul)) {
        $trecute = time() - (int) strtotime($ultimul);

        if ($trecute >= 0 && $trecute < SECUNDE_INTRE_COMENTARII) {
            raspunsJson([
                'ok'    => false,
                'mesaj' => 'Mai așteaptă puțin înainte de următorul comentariu.',
            ], 429);
        }
    }

    /* --------------------------- cui ------------------------------- */

    $catre = null;

    if (!empty($date['raspunde_la'])) {
        $catre = comentariulCerut($date['raspunde_la'], $evenimentId);

        // La un comentariu golit nu se răspunde: nu mai e nimeni acolo.
        if ((int) $catre['sters'] === 1) {
            raspunsJson([
                'ok'    => false,
                'mesaj' => 'Comentariul la care răspundeai a fost șters.',
            ], 409);
        }
    }

    $id = salveazaComentariu($evenimentId, $membruId, $rezultat['text'], $catre);

    $desenat = comentariulDesenat($id, $eveniment, $membruId, $eStaff);

    if ($desenat['rand'] === null) {
        raspunsJson(['ok' => false, 'mesaj' => 'Comentariul nu s-a putut scrie.'], 500);
    }

    $rand = $desenat['rand'];

    /**
     * Vestea pleacă DUPĂ ce comentariul e scris în bază, niciodată înainte.
     *
     * Aceeași ordine ca la anularea unui eveniment: un e-mail trimis peste o
     * scriere care apoi pică ar trimite omul la un comentariu care nu există.
     * Invers, dacă scrierea reușește și mesajul nu pleacă, comentariul e tot
     * acolo — se vede pe pagină, ca înainte de a exista înștiințarea asta.
     *
     * De aceea nici nu oprim răspunsul dacă mesajul n-a plecat: cel care a
     * scris n-are nicio vină și n-are ce face cu o eroare despre serverul de
     * e-mail. Comentariul lui e publicat; ce n-a mers ajunge în log.
     */
    instiinteazaDeComentariu($eveniment, $rand, $catre, $membruId);

    raspunsJson([
        'ok'     => true,
        'id'     => $id,
        // Unde îl pune main.js: sub un principal anume, sau în capul listei.
        'parinte' => $rand['parinte_id'] !== null ? (int) $rand['parinte_id'] : null,
        'html'   => '<li class="comment" data-comentariu="' . $id . '">'
                  . randeazaComentariu($rand, $desenat['context'])
                  . '</li>',
        'numar'  => numaraComentarii($evenimentId),
        'mesaj'  => $catre === null ? 'Gata, comentariul tău e publicat.' : 'Gata, răspunsul tău e publicat.',
    ]);
}

/**
 * Dă de veste omului pe care îl privește comentariul tocmai scris.
 *
 * CINE E ACELA se hotărăște în omDeInstiintatLaComentariu() (inc/comentarii.php)
 * — organizatorul la un comentariu principal, cel căruia i se răspunde la un
 * răspuns — și tot acolo se citește bifa din setări. Aici se compune adresa și
 * se trimite, fiindcă asta e treaba unui punct de intrare, nu a stratului care
 * atinge baza.
 *
 * Adresa duce fix la comentariu, cu ancoră: „#c123" e id-ul pus pe articol în
 * randeazaComentariu(). Fără ea, omul ar fi aterizat în capul unei pagini
 * lungi și ar fi trebuit să caute singur ce i s-a scris.
 *
 * Nu întoarce nimic și nu oprește nimic: un mesaj care nu pleacă nu are voie
 * să strice publicarea unui comentariu.
 */
function instiinteazaDeComentariu(array $eveniment, array $rand, ?array $catre, int $autorId): void
{
    $om = omDeInstiintatLaComentariu($eveniment, $autorId, $catre);

    if ($om === null) {
        return;
    }

    $adresa = urlIntreg(urlEveniment((string) $eveniment['slug'])) . '#c' . (int) $rand['id'];

    emailComentariuNou(
        (string) $om['email'],
        (string) $om['prenume'],
        $catre === null ? 'comentariu' : 'raspuns',
        numeleDinComentariu($rand),
        (string) $eveniment['titlu'],
        (string) $rand['text'],
        $adresa
    );
}

/* -------------------------- b) corectura ----------------------------- */

function editeazaComentariul(array $date, array $eveniment, int $membruId, bool $eStaff): void
{
    $evenimentId = (int) $eveniment['id'];
    $comentariu  = comentariulCerut($date['id'] ?? 0, $evenimentId);

    /**
     * Al lui, sau al oricui dacă e staff — aceeași funcție care hotărăște și
     * dacă butonul se desenează în pagină. Acolo e o purtare frumoasă, aici e
     * regula: cererea poate veni de oriunde, nu doar de pe butonul acela.
     */
    if (!poateModificaComentariul($comentariu, $membruId, $eStaff)) {
        raspunsJson(['ok' => false, 'mesaj' => 'Nu poți edita comentariul ăsta.'], 403);
    }

    $rezultat = verificaComentariu($date['text'] ?? '');

    if ($rezultat['eroare'] !== '') {
        raspunsJson(['ok' => false, 'erori' => ['text' => $rezultat['eroare']]], 422);
    }

    actualizeazaComentariu((int) $comentariu['id'], $rezultat['text']);

    $desenat = comentariulDesenat((int) $comentariu['id'], $eveniment, $membruId, $eStaff);

    if ($desenat['rand'] === null) {
        raspunsJson(['ok' => false, 'mesaj' => 'Comentariul nu mai există.'], 404);
    }

    /**
     * Se întoarce doar `<article>`, fără `<li>`-ul din jur: răspunsurile de
     * sub un comentariu principal stau în `<li>`, lângă `article`, iar dacă
     * s-ar înlocui tot `<li>`-ul s-ar șterge odată cu el toată discuția de
     * dedesubt.
     */
    raspunsJson([
        'ok'    => true,
        'id'    => (int) $comentariu['id'],
        'html'  => randeazaComentariu($desenat['rand'], $desenat['context']),
        'mesaj' => 'Am salvat comentariul.',
    ]);
}

/* --------------------------- c) ștergerea ---------------------------- */

function stergeComentariul(array $date, array $eveniment, int $membruId, bool $eStaff): void
{
    $evenimentId = (int) $eveniment['id'];
    $comentariu  = comentariulCerut($date['id'] ?? 0, $evenimentId);

    if (!poateModificaComentariul($comentariu, $membruId, $eStaff)) {
        raspunsJson(['ok' => false, 'mesaj' => 'Nu poți șterge comentariul ăsta.'], 403);
    }

    $ce = stergeComentariu($comentariu);

    /**
     * Golit, nu șters: avea răspunsuri, iar ele trebuie să rămână legate de
     * ceva. Se întoarce piatra de mormânt gata desenată, ca pagina să
     * înlocuiască doar `<article>`-ul și să lase discuția de dedesubt pe loc.
     */
    $html = '';

    if ($ce['fel'] === 'golit') {
        $desenat = comentariulDesenat((int) $comentariu['id'], $eveniment, $membruId, $eStaff);

        if ($desenat['rand'] !== null) {
            $html = randeazaComentariu($desenat['rand'], $desenat['context']);
        }
    }

    raspunsJson([
        'ok'            => true,
        'fel'           => $ce['fel'],
        'id'            => (int) $comentariu['id'],
        'html'          => $html,
        // Ultimul răspuns de sub o piatră de mormânt a plecat, deci pleacă și
        // ea: rămăsese doar ca să țină discuția legată.
        'parinte_sters' => $ce['parinte_sters'],
        'numar'         => numaraComentarii($evenimentId),
        'mesaj'         => 'Am șters comentariul.',
    ]);
}

/* -------------------------- d) aprecierea ---------------------------- */

/**
 * Raportează un comentariu, sau ia raportul înapoi.
 *
 * Un singur buton pentru amândouă: spre server pleacă „am apăsat", nu „vreau
 * să raportez". Starea adevărată o știe baza, deci o filă rămasă deschisă de
 * ieri nu poate cere altceva decât se cuvine.
 *
 * CINE ARE VOIE se hotărăște cu poateRaporta(), aceeași funcție care hotărăște
 * și dacă butonul se scrie în pagină. În pagină butonul nici nu apare pentru
 * autorul comentariului — dar asta e purtare frumoasă, nu pază: cererea de
 * față poate veni de oriunde, cu orice id în ea.
 *
 * Numărul rapoartelor NU se întoarce. Nu se arată nimănui în pagină: un contor
 * la vedere ar fi devenit o unealtă de rușinare publică, și încă una ușor de
 * umflat de câțiva prieteni. Omul află doar dacă el însuși a raportat.
 */
function raporteazaComentariul(array $date, array $eveniment, int $membruId): void
{
    $comentariu = comentariulCerut($date['id'] ?? 0, (int) $eveniment['id']);

    if (!poateRaporta($comentariu, $membruId)) {
        /**
         * Un singur mesaj pentru amândouă piedicile — al lui, sau golit.
         *
         * Nu e nimic de ascuns aici (comentariul se vede oricum pe pagină), dar
         * nici n-are rost să numărăm motivele: cine ajunge aici a trimis cererea
         * de mână, iar cine apasă butonul din pagină nu ajunge niciodată.
         */
        raspunsJson([
            'ok'    => false,
            'mesaj' => 'Comentariul ăsta nu poate fi raportat.',
        ], 403);
    }

    $rezultat = comutaRaport((int) $comentariu['id'], $membruId);

    raspunsJson([
        'ok'       => true,
        'id'       => (int) $comentariu['id'],
        'raportat' => $rezultat['raportat'],
        'mesaj'    => $rezultat['raportat']
            ? 'Mulțumim! Comentariul merge la verificare.'
            : 'Ai retras raportul.',
    ]);
}

function apreciazaComentariul(array $date, array $eveniment, int $membruId): void
{
    $comentariu = comentariulCerut($date['id'] ?? 0, (int) $eveniment['id']);

    // Un comentariu golit n-are ce să fie apreciat: nu mai scrie nimic acolo.
    if ((int) $comentariu['sters'] === 1) {
        raspunsJson(['ok' => false, 'mesaj' => 'Am șters comentariul.'], 409);
    }

    $rezultat = comutaApreciere((int) $comentariu['id'], $membruId);

    raspunsJson([
        'ok'       => true,
        'id'       => (int) $comentariu['id'],
        'apreciat' => $rezultat['apreciat'],
        'cate'     => $rezultat['cate'],
    ]);
}
