<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — aprobarea sau respingerea unui anunț, de către staff.
 *
 * Primește slugul, starea cerută („aprobat" sau „respins"), un motiv opțional
 * și bifa „editare necesară". Pune starea, dă de veste organizatorului prin
 * e-mail și răspunde cu unde să meargă omul mai departe.
 *
 * TREI HOTĂRÂRI IES DIN DOUĂ BUTOANE:
 *
 *   Aprobă                        → 'aprobat'
 *   Respinge, cu bifa pusă        → rămâne 'in_asteptare', pleacă doar vestea
 *   Respinge, cu bifa scoasă      → 'respins', ȘI SE GOLEȘTE TOT ce s-a strâns
 *                                   în jurul anunțului
 *
 * Cea din mijloc e drumul obișnuit: un anunț bun, dar cu o oră lipsă, nu
 * merită respins — e mai bine să i se spună omului ce n-a mers și să poată
 * drege, fără s-o ia de la capăt. De aceea bifa e pusă din start.
 *
 * BUTOANELE DIN PAGINĂ NU SUNT O DOVADĂ. Ele nici măcar nu se scriu în HTML
 * pentru cine nu e staff — dar asta e purtare frumoasă, nu pază. Cererea de
 * față poate veni de oriunde, cu orice slug și orice stare în ea, așa că
 * fiecare condiție se pune din nou aici.
 */

require_once __DIR__ . '/../inc/evenimente.php';
require_once __DIR__ . '/../inc/urmariri.php';
require_once __DIR__ . '/../inc/interese.php';
require_once __DIR__ . '/../inc/email.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    raspunsJson(['ok' => false, 'mesaj' => 'Metodă nepermisă.'], 405);
}

// Din pagină vine JSON, dar primim și un formular obișnuit — aceeași
// îngăduință ca la celelalte puncte de intrare.
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

$membru = membruCurent();

if ($membru === null) {
    raspunsJson(['ok' => false, 'mesaj' => 'Trebuie să fii conectat.'], 401);
}

opresteDacaTrebuieParolaNoua(true);

/**
 * NUMAI STAFF. E singura poartă care contează aici.
 *
 * esteStaff() citește din bază la fiecare cerere, nu dintr-un semn pus în
 * sesiune: un drept luat înapoi trebuie să dispară imediat, nu la următoarea
 * conectare. Iar „staff" înseamnă orice valoare în afară de 0 în
 * `membri.este_staff` — vezi inc/auth.php.
 *
 * 403, nu 404: aici nu e nimic de ascuns. Pagina evenimentului se vede oricum
 * de oricine, deci un „nu există" ar fi o minciună fără niciun câștig.
 */
if (!esteStaff($membru)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Doar echipa poate aproba sau respinge anunțuri.',
    ], 403);
}

/* ---------------------------- ce se cere ------------------------------ */

$stare = (string) ($date['stare'] ?? '');

if (!in_array($stare, STARI_DE_MODERAT, true)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Nu știu starea asta.',
    ], 422);
}

/**
 * Motivul e OPȚIONAL și are rost doar la respingere.
 *
 * Aprobarea n-are ce explica, deci un motiv trimis odată cu ea se lasă
 * deoparte fără vorbă: nu e o greșeală a nimănui, doar un câmp care n-a fost
 * golit în pagină.
 */
$motiv = verificaMotivRespingere($date['motiv'] ?? null);

if ($motiv['eroare'] !== '') {
    raspunsJson([
        'ok'    => false,
        'erori' => ['motiv' => $motiv['eroare']],
    ], 422);
}

/**
 * Bifa „Editare necesară" — pusă din start în pagină.
 *
 * Are rost doar la respingere: la aprobare n-are ce însemna „mai trebuie
 * lucrat". Trimisă odată cu o aprobare, se lasă deoparte fără vorbă, ca și
 * motivul.
 */
$editare = $stare === 'respins' && !empty($date['editare']);

/**
 * Ce stare ajunge în bază. NU e mereu ce s-a apăsat.
 *
 * Cu bifa pusă, anunțul rămâne acolo unde e — în așteptare — și pleacă doar
 * vestea. Asta e toată deosebirea dintre „îndreaptă-l" și „nu se publică".
 */
$stareNoua = $editare ? 'in_asteptare' : $stare;

$motivScris = $stare === 'respins' ? $motiv['text'] : '';

$slug      = trim((string) ($date['slug'] ?? ''));
$eveniment = evenimentDupaSlug($slug);

if ($eveniment === null) {
    raspunsJson(['ok' => false, 'mesaj' => 'Evenimentul nu mai există.'], 404);
}

/**
 * Anulat sau încheiat: nu mai e nimic de hotărât.
 *
 * Anulat e hotărârea organizatorului, luată în fața oamenilor înscriși, care au
 * primit deja un e-mail cu motivul; o aprobare peste ea ar readuce pe site un
 * eveniment despre care toată lumea a aflat că nu mai are loc. Încheiat
 * înseamnă că seara aceea a trecut.
 *
 * În pagină butoanele nici nu se văd atunci — dar, ca peste tot, asta e
 * purtare frumoasă, nu regulă: o filă lăsată deschisă peste noapte arată
 * butoane vechi.
 */
if (!poateFiModerat($eveniment)) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Anunțul e ' . ($eveniment['stare_moderare'] === 'anulat' ? 'anulat' : 'încheiat')
                 . ', deci nu mai e nimic de hotărât.',
    ], 409);
}

/**
 * Starea pe care o are deja nu se pune a doua oară.
 *
 * Nu e o eroare adevărată — nu s-a stricat nimic — dar un „gata, l-am aprobat"
 * pentru un anunț aprobat de altcineva acum trei minute i-ar spune omului de
 * casă că el a făcut ceva ce n-a făcut. Se întâmplă des la doi moderatori pe
 * aceeași listă.
 */
/**
 * Verificarea NU se pune la „editare necesară", dinadins.
 *
 * Acolo starea rămâne aceeași cu cea de acum — de cele mai multe ori
 * „in_asteptare" — iar asta e chiar rostul ei. Un „e deja în așteptare" i-ar fi
 * închis ușa exact la drumul obișnuit: un anunț care așteaptă, căruia i se cere
 * o îndreptare.
 */
if (!$editare && (string) $eveniment['stare_moderare'] === $stareNoua) {
    raspunsJson([
        'ok'    => false,
        'mesaj' => 'Anunțul e deja ' . ($stare === 'aprobat' ? 'aprobat' : 'respins') . '.',
    ], 409);
}

/**
 * Organizatorul se citește ÎNAINTE de scriere, ca peste tot: dacă între timp
 * și-a șters contul, n-am mai avea unde trimite, iar omulDeInstiintat()
 * întoarce null pentru un cont care nu mai e activ.
 */
$organizator = omulDeInstiintat((int) $eveniment['membru_id']);

// Al treilea argument pune ȘTAMPILA de corectură, ca lista de administrare
// să deosebească un anunț citit-și-întors de unul necitit de nimeni.
moderezaEveniment($eveniment, $stareNoua, $editare);

$evenimentId = (int) $eveniment['id'];
$sters = [];

/**
 * La respingerea adevărată se golește tot ce s-a strâns în jurul anunțului:
 * comentarii (cu aprecierile lor, în cascadă), note, excluderi și înscrieri.
 *
 * Doar acolo — nu și la „editare necesară", unde anunțul rămâne viu și oamenii
 * care s-au înscris n-au greșit cu nimic.
 *
 * Rândul evenimentului RĂMÂNE: e al organizatorului, cu starea „respins", ca
 * să-l poată vedea și îndrepta. Se duce doar ce au făcut alții în jurul lui.
 */
if ($stareNoua === 'respins') {
    $sters = golesteDateleEvenimentului($evenimentId);
}

/**
 * La aprobare, organizatorul se pune la loc pe lista de participanți.
 *
 * De obicei e deja acolo, pus de faOrganizatorulParticipant() la publicare, iar
 * funcția e INSERT IGNORE — deci a doua chemare nu face nimic. Contează într-un
 * singur caz, dar unul care se poate întâmpla: un anunț respins (căruia i s-au
 * golit listele) și apoi aprobat la a doua citire ar fi rămas fără organizator
 * pe lista lui, iar de rândul acela atârnă mulțumirile de după eveniment și
 * notele dintre participanți.
 *
 * NU la anunțurile ținute deoparte de profil: acolo organizatorul n-a fost pus
 * pe listă nici la publicare, iar aprobarea n-are de ce să-l pună acum. Aceeași
 * întrebare, aceeași funcție ca la salvare — vezi organizatorulVineSingur().
 */
if ($stareNoua === 'aprobat'
    && organizatorulVineSingur((int) ($eveniment['ascuns_pe_profil'] ?? 0) === 1)) {
    faOrganizatorulParticipant($evenimentId, (int) $eveniment['membru_id']);
}

/**
 * Vestea pleacă DUPĂ ce starea e scrisă în bază.
 *
 * Aceeași ordine ca la anulare și la scoaterea de pe listă: un e-mail trimis
 * peste o scriere care apoi pică i-ar spune omului un neadevăr. Invers, dacă
 * scrierea reușește și e-mailul nu pleacă, hotărârea rămâne luată — iar asta se
 * poate îndrepta.
 *
 * Nu oprim răspunsul dacă mesajul nu pleacă: fapta e făcută, iar omul de casă
 * trebuie să vadă starea nouă, nu o eroare despre serverul de e-mail.
 */
$instiintat = false;

if ($organizator !== null) {
    /**
     * LA RÂND: hotărârea e scrisă deja în bază, iar anunțul se vede (sau nu) pe
     * site din clipa asta. Vestea către organizator doar îi spune ce s-a
     * întâmplat — poate ajunge într-un minut.
     */
    $laRand = laCoada(static fn (): bool => emailModerareAnunt(
        (string) $organizator['email'],
        (string) $organizator['prenume'],
        (string) $eveniment['titlu'],
        urlIntreg(urlEveniment((string) $eveniment['slug'])),
        $editare ? 'editare' : $stare,
        $motivScris
    ));

    /* „Înștiințat" înseamnă de acum „pus la rând": mesajul pleacă din cron, nu
       din cererea asta. Pentru omul de casă e același lucru — vestea și-a
       găsit drumul. */
    $instiintat = $laRand;

    if (!$laRand) {
        error_log('PulsulOrasului: nu am putut pune la rând e-mailul de moderare '
                . 'pentru evenimentul #' . (int) $eveniment['id']);
    }
}

/**
 * ȘI CEI CARE ÎL URMĂRESC PE ORGANIZATOR AFLĂ — dar numai la aprobare, adică
 * abia acum, când anunțul chiar se vede pe site.
 *
 * Se cheamă necondiționat: instiinteazaUrmaritorii() întreabă singură dacă
 * anunțul e public și pune ștampila înainte de a trimite ceva. Așa, un anunț
 * respins și aprobat din nou nu scrie a doua oară acelorași oameni, iar două
 * apăsări deodată pe „Aprobă" nu trimit de două ori.
 *
 * DUPĂ vestea către organizator, nu înaintea ei: dacă pică ceva la mijloc, cel
 * care trebuie să afle întâi e omul al cărui anunț e în joc.
 */
instiinteazaUrmaritorii((int) $eveniment['id']);

$vorba = match (true) {
    $editare            => 'I-am cerut organizatorului o îndreptare. Anunțul rămâne în așteptare.',
    $stare === 'aprobat' => 'Am aprobat anunțul. Se vede de acum pe site.',
    default             => 'Am respins anunțul.',
};

// Mesajul îl citește inc/subsol.php pe pagina următoare și îl arată o singură
// dată, ca la încheiere și la anulare.
pornesteSesiunea();
$_SESSION['mesaj_bun'] = $vorba;

/**
 * Înapoi pe aceeași pagină.
 *
 * Reîncărcarea e chiar lucrul care arată ce s-a schimbat: banda de stare de sus
 * și butoanele, care de acum spun altceva. Un anunț respins rămâne de văzut
 * pentru staff, deci nimeni nu e trimis într-o ușă închisă.
 */
raspunsJson([
    'ok'       => true,
    'stare'    => $stareNoua,
    'editare'  => $editare,
    'redirect' => urlEveniment((string) $eveniment['slug']),
    'mesaj'    => $vorba,

    // Dacă organizatorul a aflat. Pagina nu-l arată deocamdată, dar proba îl
    // citește — iar un „da" scris degeaba ar fi mai rău decât nimic.
    'instiintat' => $instiintat,

    // Câte rânduri au plecat, la respingerea adevărată. Gol în rest.
    'sters'      => $sters,
]);
