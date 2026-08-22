<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — „Mă interesează" / „Voi participa".
 *
 * Primește slugul evenimentului și starea apăsată; hotărăște singur dacă e o
 * intrare, o schimbare sau o retragere, și răspunde cu numerele noi și cu
 * rândul de sub butoane, gata desenat.
 *
 * Retragerea nu are buton al ei: apăsarea pe starea în care omul e deja o
 * stinge. De aceea nu primim „ce să facem", ci „pe ce s-a apăsat" — cine
 * hotărăște e serverul, care știe starea adevărată. Un browser rămas cu o
 * pagină veche în față n-are cum să ne pună să facem altceva decât se cuvine.
 */

require_once __DIR__ . '/../inc/interese.php';

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
 * Fără cont nu se apasă nimic.
 *
 * Pagina evenimentului oricum nu se deschide fără cont (vezi event.php), deci
 * aici se ajunge doar dacă sesiunea a expirat între încărcarea paginii și
 * apăsare. Codul 401 e semnul după care main.js trimite omul la login, cu
 * întoarcere fix pe evenimentul ăsta.
 */
$membru = membruCurent();

if ($membru === null) {
    raspunsJson(['ok' => false, 'mesaj' => 'Trebuie să fii conectat.'], 401);
}

opresteDacaTrebuieParolaNoua(true);

$membruId = (int) $membru['id'];

/* ========================= 1. Care eveniment ========================= */

$slug      = trim((string) ($date['slug'] ?? ''));
$eveniment = evenimentDupaSlug($slug);

/**
 * Aceeași regulă ca la deschiderea paginii, aceeași funcție. Un eveniment pe
 * care n-ai voie să-l vezi e unul la care n-ai voie să te înscrii — și tot ca
 * acolo, „nu există" și „nu ai voie" primesc același răspuns.
 */
if ($eveniment === null
    || !poateVedeaEvenimentul($eveniment, $membruId, esteStaff($membru))) {
    raspunsJson(['ok' => false, 'mesaj' => 'Evenimentul nu mai există.'], 404);
}

$evenimentId = (int) $eveniment['id'];

/**
 * La un eveniment neaprobat nu se înscrie nimeni.
 *
 * Pagina lui se deschide, dar numai pentru organizator (cât e în așteptare sau
 * respins) sau pentru staff (cât e anulat) — și niciunul dintre ei n-are ce
 * căuta pe o listă de participanți la ceva ce nu s-a publicat încă.
 */
if (!evenimentPublicat($eveniment)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Evenimentul nu e publicat, deci nu are încă listă.',
    ], 409);
}

/**
 * Ce a început nu se mai schimbă.
 *
 * Regula pornea până acum abia la încheiere, dar între început și încheiere e
 * chiar evenimentul — tocmai răstimpul în care o retragere n-ar mai însemna
 * nimic, fiindcă omul e (sau nu e) deja acolo. Iar cine se șterge de pe listă
 * în timpul evenimentului ar scăpa de „Nu s-a prezentat".
 *
 * Nici retragerea nu se mai poate. Listele unui eveniment care a început sunt
 * istoria lui: cine a fost pe ele a fost, iar organizatorul care se uită peste
 * ele trebuie să vadă ce a fost, nu ce a mai rămas.
 *
 * În pagină butoanele sunt deja stinse, dar asta e o purtare frumoasă, nu o
 * regulă: cererea poate veni de oriunde, iar o filă deschisă azi-dimineață,
 * pe când evenimentul era încă în față, arată butoanele vii.
 */
if (evenimentAInceput($eveniment)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => evenimentIncheiat($eveniment)
            ? 'Evenimentul s-a încheiat.'
            : 'Evenimentul a început, lista nu se mai schimbă.',
    ], 409);
}

/* ====================== 2. Pe ce buton s-a apăsat ==================== */

$apasat = (string) ($date['stare'] ?? '');

if (!in_array($apasat, ['interesat', 'participant'], true)) {
    raspunsJson(['ok' => false, 'mesaj' => 'Nu știu ce să fac cu asta.'], 422);
}

$acum = interesulMeu($evenimentId, $membruId);

/* ========================== 3. Retragerea =========================== */

/**
 * A apăsat pe starea în care e deja: se retrage.
 *
 * Se face înaintea oricărei alte verificări. Cine iese de pe listă nu are de
 * ce să dea un număr de telefon și nu are de ce să fie oprit de faptul că
 * evenimentul s-a umplut — tocmai eliberează un loc.
 */
if ($acum === $apasat) {
    stergeInteres($evenimentId, $membruId, $apasat);
    raspunsJson(raspunsulListei($eveniment, $membruId, 'Te-am scos de pe listă.'));
}

/* ===================== 4. „Mă interesează" ========================== */

// Nimic de cerut și nimic de verificat: e o însemnare, nu o hotărâre.
if ($apasat === 'interesat') {
    salveazaInteres($evenimentId, $membruId, 'interesat');
    raspunsJson(raspunsulListei($eveniment, $membruId, 'Te-am trecut la interesați.'));
}

/* ====================== 5. „Voi participa" ========================== */

$eOrganizatorul = (int) $eveniment['membru_id'] === $membruId;

/**
 * Confirmarea explicită, cerută în pagină.
 *
 * Nu e o formalitate de bifat în JS: aici e locul unde se verifică, fiindcă
 * cererea poate veni de oriunde. Fără ea, cineva ar putea ajunge pe lista de
 * participanți — cu numele și numărul de telefon arătate organizatorului —
 * fără să fi văzut vreodată ce anume dă din el.
 */
if (empty($date['confirmat'])) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Confirmă întâi participarea.',
    ], 422);
}

/* -------------------------- a) opreliștile -------------------------- */

/**
 * Ușa închisă de organizator, sau un eveniment care nu e pentru el.
 *
 * Aceeași funcție care stinge butonul în pagină — vezi
 * motivBlocajParticipare() din inc/interese.php. În pagină e o purtare
 * frumoasă; aici e regula, fiindcă cererea poate veni de oriunde: dintr-o filă
 * deschisă alaltăieri, sau de-a dreptul cu curl.
 *
 * Se verifică abia acum, nu la începutul fișierului: retragerea (pasul 3) și
 * „Mă interesează" (pasul 4) rămân deschise pentru toată lumea. Opreliștile
 * astea țin de ocuparea unui loc, nu de însemnarea din dreptul omului — și nu
 * țin pe nimeni prizonier pe o listă de pe care vrea să iasă.
 */
$blocaj = motivBlocajParticipare($eveniment, $membru);

if ($blocaj !== '') {
    raspunsJson(['ok' => false, 'mesaj' => $blocaj], 403);
}

/* --------------------------- b) telefonul --------------------------- */

/**
 * Organizatorul nu-și dă numărul lui însuși, deci nu i se cere.
 *
 * Pentru toți ceilalți: numărul se salvează în cont, nu se ține doar pentru
 * evenimentul ăsta. Cine l-a dat o dată nu-l mai scrie a doua oară, iar
 * organizatorul are pe ce suna.
 */
if (!$eOrganizatorul && telefonulMembrului($membruId) === '') {
    $primit = $date['telefon'] ?? '';

    if (!is_string($primit)) {
        raspunsJson([
            'ok'    => false,
            'erori' => ['telefon' => 'Scrie numărul ca text, de forma 0722334455.'],
        ], 422);
    }

    // Tăiem înainte de verificare, ca la setări: expresiile regulate n-au ce
    // căuta peste un șir uriaș.
    if (strlen($primit) > 40) {
        raspunsJson(['ok' => false, 'erori' => ['telefon' => 'Numărul e prea lung.']], 422);
    }

    // Aceeași verificare ca la setări și la contact — o singură regulă pentru
    // ce înseamnă un număr de telefon pe site-ul ăsta.
    $rezultat = verificaTelefon($primit);

    if (!$rezultat['ok'] || $rezultat['curat'] === '') {
        raspunsJson([
            'ok'    => false,
            'erori' => ['telefon' => $rezultat['eroare'] !== ''
                ? $rezultat['eroare']
                : 'Scrie numărul la care te poate găsi organizatorul.'],
        ], 422);
    }

    db()->prepare('UPDATE membri SET telefon = ?, actualizat_la = ? WHERE id = ?')
        ->execute([$rezultat['curat'], acum(), $membruId]);
}

/* --------------------------- c) locurile ---------------------------- */

/**
 * NUMĂRATUL ȘI ÎNSCRIEREA, ÎNTR-O SINGURĂ MIȘCARE.
 *
 * Se numără ACUM, nu la desenarea paginii: între încărcare și apăsare pot
 * intra alții. Numărul de pe ecran e o veste, nu o rezervare.
 *
 * Dar „acum" nu era de ajuns. Numărătoarea și scrierea erau două cereri
 * despărțite, iar la un eveniment cu un singur loc rămas zece oameni care
 * apasă în aceeași clipă citeau toți același număr, treceau toți de
 * verificare și intrau toți pe listă. Nu e o închipuire: cu opt cereri
 * pornite deodată, un eveniment de două locuri ajungea la cinci participanți,
 * de fiecare dată.
 *
 * De aceea cele două stau acum într-o tranzacție, iar rândul evenimentului se
 * încuie cu `FOR UPDATE`: al doilea venit așteaptă la ușă până termină
 * primul, și abia apoi numără — găsind locul luat. Se încuie RÂNDUL
 * EVENIMENTULUI, nu tabelul de înscrieri: e cheia după care se face
 * socoteala, deci e locul firesc în care se face rândul la coadă, iar două
 * evenimente deosebite nu se așteaptă unul pe altul.
 *
 * Aceeași croială ca la revendicarea unui abțibild și ca la ștampila
 * mulțumirilor: cine ajunge primul câștigă, ceilalți află că n-au ce câștiga.
 *
 * Cine e deja participant nu trece pe aici — a apăsat pe starea lui și s-a
 * retras la pasul 3. Cine e „interesat" și trece la „particip" ocupă un loc
 * nou, deci se numără ca oricine altcineva.
 */
$pdo = db();
$pdo->beginTransaction();

try {
    $pdo->prepare('SELECT id FROM evenimente WHERE id = ? FOR UPDATE')->execute([$evenimentId]);

    $numar = numaraInterese($evenimentId);

    if (!maiSuntLocuri($eveniment, $numar['participant'])) {
        $pdo->rollBack();

        raspunsJson([
            'ok'    => false,
            'plin'  => true,
            'mesaj' => 'Nu mai sunt locuri disponibile la acest eveniment.',
        ], 409);
    }

    salveazaInteres($evenimentId, $membruId, 'participant');

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    throw $e;
}

raspunsJson(raspunsulListei($eveniment, $membruId, 'Te-am trecut pe lista de participanți.'));

/* ===================================================================== */

/**
 * Ce se trimite înapoi după orice apăsare care a reușit.
 *
 * Numerele și rândul de sub butoane se citesc din bază DUPĂ schimbare, nu se
 * socotesc în JS pornind de la ce era pe ecran: între două apăsări ale
 * omului nostru pot intra alți zece, iar un număr crescut cu unu în browser
 * ar fi rămas greșit până la următoarea reîncărcare.
 */
function raspunsulListei(array $eveniment, int $membruId, string $mesaj): array
{
    $evenimentId = (int) $eveniment['id'];
    $numar       = numaraInterese($evenimentId);

    return [
        'ok'     => true,
        'stare'  => interesulMeu($evenimentId, $membruId),
        'numar'  => $numar,
        'oameni' => randeazaChipuri($evenimentId),
        /**
         * Listele din taburi, gata desenate.
         *
         * Fără ele, omul se vedea apărând pe buton și în numărătoare, dar nu
         * și în tabul de dedesubt — acolo intra abia după o reîncărcare pe
         * care n-avea de ce s-o ghicească.
         *
         * Nu se lipesc în JS dintr-un rând nou: aceleași funcții care desenează
         * pagina desenează și asta, deci nu se pot despărți. Butoanele de
         * scoatere nu se trimit niciodată de aici — cine apasă „Voi participa"
         * e un participant oarecare, nu organizatorul care face curat.
         */
        'panouri' => raspunsulPanourilor($eveniment, false, null,
                                         poateVedeaTelefoanele($eveniment, membruCurent())),
        'mesaj'  => $mesaj,
    ];
}
