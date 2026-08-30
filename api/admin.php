<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — faptele din zona de administrare.
 *
 * UN SINGUR PUNCT DE INTRARE pentru toate, cu `fapta` care spune care anume —
 * ca la api/comentarii.php. Motivul e paza: fiecare faptă de aici cere ACELAȘI
 * lucru (token bun, cont, om de casă), iar șapte fișiere ar fi însemnat șapte
 * copii ale aceleiași verificări, dintre care una va fi într-o zi mai
 * îngăduitoare decât celelalte.
 *
 * Ce se poate face:
 *   sterge-eveniment  — un anunț RESPINS, cu tot ce atârnă de el
 *   sterge-comentariu — un comentariu (golit, dacă are răspunsuri sub el)
 *   curata-rapoarte   — „e în regulă": raportările se șterg, comentariul rămâne
 *   marcheaza-mesaj   — un mesaj de contact, citit sau necitit
 *   sterge-mesaj      — un mesaj de contact, de tot
 *   sterge-evaluare   — o notă dintre participanți, de tot
 *   sterge-dorinta    — o dorință, oricare i-ar fi starea
 *   modereaza-dorinta — aprobă, respinge, sau întoarce în așteptare o dorință
 *   sterge-poza       — poza de profil a cuiva
 *   schimba-membru    — starea contului sau limita de evenimente active
 *
 * PATRU DINTRE ELE TRIMIT UN E-MAIL: ștergerea unui comentariu, ștergerea unei
 * poze, suspendarea unui cont și hotărârea asupra unei dorințe. Toate primesc
 * un MOTIV, care poate să lipsească — atunci mesajul spune limpede că nu s-a
 * dat niciunul, în loc să tacă (vezi paragrafeleMotivului din inc/email.php).
 *
 * Vestea pleacă mereu DUPĂ ce fapta s-a scris cu bine, și niciodată invers: un
 * e-mail care spune „ți-am șters poza" pentru o ștergere care n-a mers e mai
 * rău decât nimic. Iar dacă e-mailul nu pleacă, fapta rămâne făcută — poșta nu
 * are voie să întoarcă din drum o hotărâre a casei.
 */

require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/comentarii.php';
require_once __DIR__ . '/../inc/dorinte.php';
require_once __DIR__ . '/../inc/imagini.php';
require_once __DIR__ . '/../inc/email.php';

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

$membru = membruCurent();

if ($membru === null) {
    raspunsJson(['ok' => false, 'mesaj' => 'Trebuie să fii conectat.'], 401);
}

opresteDacaTrebuieParolaNoua(true);

/**
 * Om de casă, întrebat din bază la fiecare cerere — nu din sesiune, și nici pe
 * temeiul că pagina cu butonul s-a deschis. Un drept luat înapoi trebuie să
 * dispară pe loc.
 */
if (!esteStaff($membru)) {
    raspunsJson(['ok' => false, 'mesaj' => 'Nu ai voie.'], 403);
}

$membruId = (int) $membru['id'];
$idCerut  = static fn(string $cheie) => (int) ($date[$cheie] ?? 0);

/**
 * Motivul scris de omul casei. Poate lipsi cu totul — atunci e-mailul spune că
 * nu s-a dat niciunul.
 *
 * Se taie la o mie de caractere: e o vorbă către un om, nu un dosar, iar un
 * text de zece mii lipit din greșeală ar fi umflat mesajul fără să spună mai
 * mult.
 */
$motivCerut = static fn(): string =>
    mb_substr(trim((string) ($date['motiv'] ?? '')), 0, 1000, 'UTF-8');

/**
 * Cui i se trimite vestea. Întoarce null pentru un cont care nu mai e activ —
 * un suspendat sau un anonimizat n-are unde primi nimic, iar noi n-avem ce-i
 * spune.
 *
 * Aceeași funcție ca la anularea unui eveniment: un singur loc care hotărăște
 * cine mai poate fi înștiințat.
 */
$destinatarul = static function (int $id): ?array {
    require_once __DIR__ . '/../inc/interese.php';
    return omulDeInstiintat($id);
};

switch ((string) ($date['fapta'] ?? '')) {

/* ==================== un anunț respins, cu totul ===================== */

case 'sterge-eveniment':
    $q = db()->prepare('SELECT id, titlu, slug, coperta, stare_moderare
                          FROM evenimente WHERE id = ?');
    $q->execute([$idCerut('id')]);
    $ev = $q->fetch();

    if ($ev === false) {
        raspunsJson(['ok' => false, 'mesaj' => 'Evenimentul nu mai există.'], 404);
    }

    /**
     * NUMAI CELE RESPINSE. E singura ștergere adevărată de pe tot site-ul, iar
     * un anunț respins e singurul care n-a fost niciodată public: nu lasă în
     * urmă pe nimeni care să se întrebe unde a dispărut ceva.
     *
     * Un anunț aprobat cu doisprezece oameni înscriși nu se șterge de aici, cu
     * o apăsare și fără întoarcere. Dacă nu mai are loc, se anulează — atunci
     * oamenii primesc o veste și un motiv.
     *
     * 409, nu 403: omul ARE dreptul să umble aici, doar că anunțul ăsta nu se
     * poate șterge. Un 403 l-ar fi trimis să creadă că nu e de-al casei.
     */
    if ((string) $ev['stare_moderare'] !== 'respins') {
        raspunsJson([
            'ok'    => false,
            'mesaj' => 'Se șterg doar anunțurile respinse. Ăsta e „'
                     . $ev['stare_moderare'] . '" — dacă nu mai are loc, se anulează.',
        ], 409);
    }

    if (!stergeEvenimentDeTot($ev)) {
        raspunsJson(['ok' => false, 'mesaj' => 'N-a putut fi șters. Reîncarcă pagina.'], 409);
    }

    raspunsJson(['ok' => true, 'mesaj' => 'Anunțul „' . $ev['titlu'] . '" a fost șters de tot.']);
    break;

/* ======================== un comentariu ============================== */

case 'sterge-comentariu':
    $com = comentariuDupaId($idCerut('id'));

    if ($com === null) {
        raspunsJson(['ok' => false, 'mesaj' => 'Comentariul nu mai există.'], 404);
    }

    /**
     * Cui îi scriem, și despre ce anunț — se citesc ÎNAINTE de ștergere.
     * După ea, rândul comentariului poate fi golit sau plecat cu totul, iar
     * atunci n-am mai fi avut nici numele evenimentului, nici pe cine să
     * întrebăm.
     */
    $autorul = $destinatarul((int) $com['membru_id']);

    $q = db()->prepare('SELECT titlu FROM evenimente WHERE id = ?');
    $q->execute([(int) $com['eveniment_id']]);
    $titluEv = (string) ($q->fetchColumn() ?: 'un anunț de pe site');

    /**
     * Aceeași funcție ca pe pagina evenimentului, nu una „de administrare".
     * Ea știe regula care contează: un comentariu principal cu răspunsuri sub
     * el se GOLEȘTE, nu se șterge — altfel răspunsurile ar rămâne suspendate în
     * aer, iar discuția n-ar mai avea început. O ștergere scrisă separat aici
     * ar fi fost a doua regulă, care într-o zi n-ar mai fi fost la fel.
     */
    $ce = stergeComentariu($com);

    /**
     * Vestea pleacă și când comentariul a fost doar GOLIT: pentru omul care
     * l-a scris, cele două înseamnă același lucru — ce a scris nu se mai
     * citește.
     *
     * Nu-și scrie sieși: un om de casă care își șterge propriul comentariu
     * n-are de ce să primească o veste despre asta.
     */
    $aPlecatVestea = $autorul !== null && (int) $com['membru_id'] !== $membruId;

    if ($aPlecatVestea) {
        emailComentariuSters($autorul['email'], (string) $autorul['prenume'],
                             $titluEv, $motivCerut());
    }

    raspunsJson([
        'ok'    => true,
        'fel'   => $ce['fel'],
        // Vorba de pe ecran spune ce s-a întâmplat CU ADEVĂRAT: „i-am dat de
        // veste" numai dacă a plecat ceva. Altfel omul de casă ar fi rămas cu
        // gândul că autorul a aflat, când de fapt n-a aflat nimeni.
        'mesaj' => ($ce['fel'] === 'golit'
            ? 'Comentariul a fost golit — avea răspunsuri sub el.'
            : 'Comentariul a fost șters.')
            . ($aPlecatVestea ? ' I-am dat de veste autorului.' : ''),
    ]);
    break;

/* ==================== „e în regulă", la un raport =================== */

case 'curata-rapoarte':
    $com = comentariuDupaId($idCerut('id'));

    if ($com === null) {
        raspunsJson(['ok' => false, 'mesaj' => 'Comentariul nu mai există.'], 404);
    }

    /**
     * Raportările se șterg, comentariul rămâne pe loc.
     *
     * Se raportează și din greșeală, și din supărare, și fiindcă cineva n-a
     * fost de acord cu ce scrie acolo. Fără butonul ăsta, singurul fel de a
     * scoate un rând din lista de raportate era să-l ștergi — adică să
     * pedepsești pe cineva ca să faci curat pe o listă.
     *
     * NU se ține minte că a fost cercetat: dacă cineva îl raportează din nou
     * mâine, se întoarce în listă. E chiar ce trebuie — o a doua mână care
     * arată spre același rând poate arăta spre altceva decât prima.
     */
    $q = db()->prepare('DELETE FROM comentarii_rapoarte WHERE comentariu_id = ?');
    $q->execute([(int) $com['id']]);

    raspunsJson([
        'ok'    => true,
        'cate'  => $q->rowCount(),
        'mesaj' => 'Gata, l-am lăsat în pace. Raportările s-au șters.',
    ]);
    break;

/* ==================== un mesaj de la contact ========================= */

case 'marcheaza-mesaj':
    $citit = !empty($date['citit']);

    $q = db()->prepare('UPDATE mesaje_contact SET citit_la = ? WHERE id = ?');
    $q->execute([$citit ? acum() : null, $idCerut('id')]);

    raspunsJson([
        'ok'    => true,
        'citit' => $citit,
        'mesaj' => $citit ? 'Însemnat ca citit.' : 'Însemnat ca necitit.',
    ]);
    break;

case 'sterge-mesaj':
    /**
     * Ștergere adevărată, fără piatră de mormânt: un mesaj de contact e o
     * scrisoare primită, nu o urmă din viața site-ului. Când s-a răspuns la
     * el — sau când e limpede că nu cere niciun răspuns — n-are de ce să
     * rămână.
     *
     * Omul care l-a trimis nu află nimic. Ar fi fost ciudat: a scris cuiva, nu
     * a pus ceva pe site.
     */
    $q = db()->prepare('DELETE FROM mesaje_contact WHERE id = ?');
    $q->execute([$idCerut('id')]);

    if ($q->rowCount() !== 1) {
        raspunsJson(['ok' => false, 'mesaj' => 'Mesajul nu mai există.'], 404);
    }

    raspunsJson(['ok' => true, 'mesaj' => 'Mesajul a fost șters.']);
    break;

/* ============================= o notă ================================ */

case 'sterge-evaluare':
    $nota = evaluareaDupaId($idCerut('id'));

    if ($nota === null) {
        raspunsJson(['ok' => false, 'mesaj' => 'Nota nu mai există.'], 404);
    }

    /**
     * ȘTERGERE ADEVĂRATĂ, nu golire.
     *
     * Un comentariu cu răspunsuri sub el se golește, fiindcă de el atârnă
     * discuția. De o notă nu atârnă nimic în afară de o cifră care intră într-o
     * medie — iar o cifră „golită" ar fi rămas tot o cifră. Deci pleacă.
     *
     * NU PLEACĂ NICIUN E-MAIL, dinadins. Nici către cel care a dat-o: dacă a
     * pus-o din răutate, o veste i-ar spune doar că merită încercat de pe alt
     * cont, iar dacă a pus-o cinstit, i-ar spune că cineva i-a citit părerea și
     * a hotărât s-o șteargă — mai rău decât tăcerea. Nici către cel notat:
     * pentru el media a fost mereu doar o cifră care se mișcă, iar „ți-am
     * șters o notă de una" înseamnă „cineva ți-a dat una și n-ai știut".
     *
     * De aceea butonul nici nu cere un motiv: n-ar avea cine să-l citească.
     */
    if (!stergeEvaluarea((int) $nota['id'])) {
        raspunsJson(['ok' => false, 'mesaj' => 'N-a putut fi ștearsă. Reîncarcă pagina.'], 409);
    }

    raspunsJson([
        'ok'    => true,
        'mesaj' => 'Nota a fost ștearsă. Media lui '
                 . ($nota['tinta_prenume'] !== null && $nota['tinta_prenume'] !== ''
                    ? $nota['tinta_prenume'] : 'cel notat')
                 . ' s-a schimbat pe loc.',
    ]);
    break;

/* =========================== o dorință =============================== */

case 'sterge-dorinta':
    $q = db()->prepare('DELETE FROM dorinte WHERE id = ?');
    $q->execute([$idCerut('id')]);

    if ($q->rowCount() !== 1) {
        raspunsJson(['ok' => false, 'mesaj' => 'Dorința nu mai există.'], 404);
    }

    raspunsJson(['ok' => true, 'mesaj' => 'Dorința a fost ștearsă.']);
    break;

case 'modereaza-dorinta':
    $hotarare = (string) ($date['hotarare'] ?? '');

    /**
     * TREI STĂRI, NU DOUĂ. „in_asteptare" e acolo fiindcă de când hotărârea se
     * ia dintr-o listă, și nu din două butoane, ea trebuie să poată fi și
     * ÎNTOARSĂ: cine a apăsat din greșeală „Respinge" pe rândul de deasupra
     * n-avea până acum nicio cale înapoi din interfață.
     */
    if (!in_array($hotarare, ['in_asteptare', 'aprobat', 'respins'], true)) {
        raspunsJson(['ok' => false, 'mesaj' => 'Hotărâre necunoscută.'], 422);
    }

    /**
     * `publicat_la` NU se pune aici, dinadins: îl scrie stampileazaCeleAprobate()
     * la prima încărcare a primei pagini, tot cu ceasul PHP. Așa, o dorință
     * aprobată de mână din phpMyAdmin și una aprobată de aici se poartă la fel —
     * iar cele șapte zile de pe tablă se numără din același loc.
     */
    /* Cui îi scriem, ce scria în dorință și unde era — citite înaintea scrierii. */
    $q = db()->prepare('SELECT membru_id, dorinta, stare_moderare FROM dorinte WHERE id = ?');
    $q->execute([$idCerut('id')]);
    $dorintaRand = $q->fetch();

    if ($dorintaRand === false) {
        raspunsJson(['ok' => false, 'mesaj' => 'Dorința nu mai există.'], 404);
    }

    $dinainte = (string) $dorintaRand['stare_moderare'];

    /**
     * Aleasă starea în care e deja: nu se scrie nimic și, mai ales, nu pleacă
     * niciun e-mail. Se întâmplă de la sine când cineva deschide lista și o
     * închide la loc.
     */
    if ($dinainte === $hotarare) {
        raspunsJson([
            'ok'    => true,
            'stare' => $hotarare,
            'mesaj' => 'Era deja acolo. N-am schimbat nimic.',
        ]);
    }

    /**
     * Starea citită mai sus stă în WHERE — nu „in_asteptare" scris de mână.
     * Așa rămâne apărarea de dinainte (o filă lăsată deschisă nu răstoarnă ce
     * a hotărât altcineva între timp), dar fără să mai închidă drumul înapoi:
     * ce se compară e ce scria pe ecran, nu o singură stare anume.
     */
    $q = db()->prepare(
        'UPDATE dorinte SET stare_moderare = ? WHERE id = ? AND stare_moderare = ?'
    );
    $q->execute([$hotarare, $idCerut('id'), $dinainte]);

    if ($q->rowCount() !== 1) {
        raspunsJson([
            'ok'    => false,
            'mesaj' => 'Dorința asta a fost hotărâtă între timp. Reîncarcă pagina.',
        ], 409);
    }

    /**
     * Omul află pe e-mail, în sfârșit — și la aprobare, și la respingere.
     *
     * Până acum nu afla nimic: vedea singur, DACĂ trecea pe prima pagină în
     * cele șapte zile cât stă pe tablă. La respingere nu afla niciodată —
     * dorința lui pur și simplu nu apărea, iar el nu știa dacă e citită sau
     * uitată.
     *
     * NU PLEACĂ NIMIC LA ÎNTOARCEREA ÎN AȘTEPTARE: acolo nu s-a hotărât nimic,
     * iar „dorința ta așteaptă din nou" e o veste despre o nehotărâre. Pleacă
     * însă la RĂZGÂNDIRE (aprobat → respins și invers): dorința tocmai a intrat
     * sau a ieșit de pe tablă, iar omul trebuie să știe unde e acum, nu unde a
     * fost prima oară.
     *
     * Motivul se ia în seamă numai la respingere: la aprobare n-are ce spune.
     */
    $omulDorintei = $hotarare === 'in_asteptare'
        ? null
        : $destinatarul((int) $dorintaRand['membru_id']);

    if ($omulDorintei !== null) {
        emailDorintaHotarata(
            $omulDorintei['email'],
            (string) $omulDorintei['prenume'],
            (string) $dorintaRand['dorinta'],
            $hotarare === 'aprobat',
            $hotarare === 'aprobat' ? '' : $motivCerut(),
            ZILE_PE_TABLA
        );
    }

    if ($hotarare === 'in_asteptare') {
        $vorba = 'Dorința s-a întors în așteptare. Nu i-am scris nimic omului.';
    } elseif ($hotarare === 'aprobat') {
        $vorba = 'Dorința a fost aprobată. Apare pe tablă de la prima încărcare a primei pagini.';
    } else {
        $vorba = 'Dorința a fost respinsă. Omul poate pune alta.';
    }

    raspunsJson([
        'ok'    => true,
        'stare' => $hotarare,
        'mesaj' => $vorba . ($omulDorintei !== null ? ' I-am dat de veste.' : ''),
    ]);
    break;

/* ======================== poza cuiva ================================= */

case 'sterge-poza':
    $q = db()->prepare('SELECT id, poza FROM membri WHERE id = ?');
    $q->execute([$idCerut('id')]);
    $om = $q->fetch();

    if ($om === false) {
        raspunsJson(['ok' => false, 'mesaj' => 'Contul nu mai există.'], 404);
    }

    if (($om['poza'] ?? null) === null) {
        raspunsJson(['ok' => false, 'mesaj' => 'Omul ăsta n-are poză.'], 409);
    }

    /**
     * Coloana întâi, fișierul pe urmă — ca la copertă. Invers, o cădere la
     * scriere ar fi lăsat un cont care arată spre o poză care nu mai e.
     */
    db()->prepare('UPDATE membri SET poza = NULL, poza_actualizata_la = NULL,
                          actualizat_la = ? WHERE id = ?')
        ->execute([acum(), (int) $om['id']]);

    stergePozaDeFisier($om['poza']);

    /**
     * Omul află de ce i-a dispărut poza. Fără vestea asta, ar fi intrat într-o
     * zi pe profil și ar fi găsit inițiala în locul chipului lui, fără nicio
     * lămurire — iar cel mai firesc gând ar fi fost că s-a stricat ceva la
     * site, nu că i s-a cerut, tăcut, să pună alta.
     */
    $omulPozei = $destinatarul((int) $om['id']);

    if ($omulPozei !== null) {
        emailPozaStearsa($omulPozei['email'], (string) $omulPozei['prenume'], $motivCerut());
    }

    raspunsJson([
        'ok'    => true,
        // Chipul de pus în locul celui șters, ca rândul să nu mai arate spre un
        // fișier care nu mai e. Îl socotește tot urlPoza(), într-un singur loc:
        // scris cu mâna în JS, ar fi fost al doilea loc care știe cum arată
        // inițiala cuiva.
        'poza'  => urlPoza(null, true),
        'mesaj' => 'Poza a fost ștearsă.'
                 . ($omulPozei !== null ? ' I-am dat de veste.' : ''),
    ]);
    break;

/* ==================== starea și limita unui cont ===================== */

case 'schimba-membru':
    $id = $idCerut('id');

    $q = db()->prepare('SELECT id, nume, prenume, stare, este_staff,
                               limita_evenimente_active FROM membri WHERE id = ?');
    $q->execute([$id]);
    $om = $q->fetch();

    if ($om === false) {
        raspunsJson(['ok' => false, 'mesaj' => 'Contul nu mai există.'], 404);
    }

    $campuri = [];
    $valori  = [];

    /* ---------------------------- starea ---------------------------- */
    if (array_key_exists('stare', $date)) {
        $stare = (string) $date['stare'];

        /**
         * „sters" NU se dă de aici. Ștergerea unui cont înseamnă anonimizare —
         * se golește omul din rând, se pun ștampilele, se trimite vestea (vezi
         * inc/stergere.php). Un `UPDATE stare='sters'` scris din tabelul ăsta
         * ar fi lăsat numele, adresa și poza în bază, sub o stare care spune că
         * nu mai sunt.
         */
        if (!in_array($stare, ['neconfirmat', 'activ', 'suspendat'], true)) {
            raspunsJson([
                'ok'    => false,
                'mesaj' => 'Starea asta nu se pune de aici. Ștergerea unui cont '
                         . 'trece prin anonimizare, nu printr-o schimbare de stare.',
            ], 422);
        }

        /**
         * Pe un om de casă nu se umblă din pagina asta. Nu e o regulă de
         * siguranță — cine e staff poate oricum orice — ci una care ferește de
         * o apăsare greșită: doi oameni de casă care se suspendă unul pe altul
         * dintr-un tabel de două sute de rânduri ar rămâne amândoi pe dinafară.
         * Steagul se dă și se ia din phpMyAdmin; tot de acolo și starea.
         */
        if ((int) $om['este_staff'] !== 0) {
            raspunsJson([
                'ok'    => false,
                'mesaj' => 'E om de casă. Starea lui se schimbă din phpMyAdmin.',
            ], 409);
        }

        $campuri[] = 'stare = ?';
        $valori[]  = $stare;

        /**
         * SE SUSPENDĂ CHIAR ACUM? Se ține minte aici, cât timp mai știm și
         * starea de dinainte: după UPDATE, cele două ar arăta la fel, iar
         * vestea ar fi plecat și la a doua apăsare pe același om.
         *
         * Numai la suspendare pleacă un e-mail. La ridicarea ei nu: acolo omul
         * intră pur și simplu în cont și merge mai departe, iar un mesaj care
         * spune „acum poți din nou" ar fi amintit, fără rost, de o pedeapsă
         * încheiată.
         */
        $tocmaiSuspendat = $stare === 'suspendat'
                        && (string) $om['stare'] !== 'suspendat';
    }

    /* --------------------- limita de evenimente ---------------------- */
    if (array_key_exists('limita', $date)) {
        $scris = trim((string) $date['limita']);

        /**
         * GOL ÎNSEAMNĂ „REGULA OBIȘNUITĂ", adică NULL în bază — nu zero.
         *
         * limitaEvenimente() citește NULL ca EVENIMENTE_ACTIVE_IMPLICIT. Un
         * `(int)` peste un câmp golit ar fi scris 0, iar 0 e altceva: „omul
         * ăsta nu mai publică nimic". Cele două arată la fel într-o căsuță
         * goală și înseamnă lucruri opuse, de aceea se deosebesc aici, o dată,
         * și nu în pagină.
         */
        if ($scris === '') {
            $campuri[] = 'limita_evenimente_active = NULL';
        } else {
            if (!ctype_digit($scris)) {
                raspunsJson([
                    'ok'    => false,
                    'mesaj' => 'Limita e un număr între 0 și 255, sau gol pentru regula obișnuită.',
                ], 422);
            }

            $limita = (int) $scris;

            // Coloana e TINYINT UNSIGNED: 0…255. Zero e o valoare adevărată și
            // folositoare, deci nu se respinge — doar se ține între maluri.
            if ($limita > 255) {
                raspunsJson(['ok' => false, 'mesaj' => 'Limita nu poate trece de 255.'], 422);
            }

            $campuri[] = 'limita_evenimente_active = ?';
            $valori[]  = $limita;
        }
    }

    if ($campuri === []) {
        raspunsJson(['ok' => false, 'mesaj' => 'Nu e nimic de schimbat.'], 422);
    }

    $campuri[] = 'actualizat_la = ?';
    $valori[]  = acum();
    $valori[]  = $id;

    db()->prepare('UPDATE membri SET ' . implode(', ', $campuri) . ' WHERE id = ?')
        ->execute($valori);

    /**
     * Vestea pleacă DUPĂ scriere, și se cere DUPĂ ea — omulDeInstiintat()
     * întoarce null pentru un cont care nu mai e activ, iar contul tocmai a
     * devenit „suspendat".
     *
     * De aceea aici se citește de-a dreptul: la suspendare vestea trebuie să
     * plece tocmai către cel care nu mai e activ. E singurul loc de pe site
     * unde asta are rost.
     */
    $vesteSuspendare = '';

    if (!empty($tocmaiSuspendat)) {
        $q = db()->prepare('SELECT prenume, email FROM membri WHERE id = ?');
        $q->execute([$id]);
        $celSuspendat = $q->fetch();

        if ($celSuspendat !== false && (string) $celSuspendat['email'] !== '') {
            emailContSuspendat((string) $celSuspendat['email'],
                               (string) $celSuspendat['prenume'], $motivCerut());
            $vesteSuspendare = ' I-am dat de veste pe e-mail.';
        }
    }

    raspunsJson(['ok' => true, 'mesaj' => 'Gata, am schimbat.' . $vesteSuspendare]);
    break;

default:
    raspunsJson(['ok' => false, 'mesaj' => 'Nu știu ce să fac.'], 422);
}
