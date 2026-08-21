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
 *   marcheaza-mesaj   — un mesaj de contact, citit sau necitit
 *   sterge-dorinta    — o dorință, oricare i-ar fi starea
 *   modereaza-dorinta — aprobă sau respinge o dorință care așteaptă
 *   sterge-poza       — poza de profil a cuiva
 *   schimba-membru    — starea contului sau limita de evenimente active
 */

require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/comentarii.php';
require_once __DIR__ . '/../inc/dorinte.php';
require_once __DIR__ . '/../inc/imagini.php';

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
     * Aceeași funcție ca pe pagina evenimentului, nu una „de administrare".
     * Ea știe regula care contează: un comentariu principal cu răspunsuri sub
     * el se GOLEȘTE, nu se șterge — altfel răspunsurile ar rămâne suspendate în
     * aer, iar discuția n-ar mai avea început. O ștergere scrisă separat aici
     * ar fi fost a doua regulă, care într-o zi n-ar mai fi fost la fel.
     */
    $ce = stergeComentariu($com);

    raspunsJson([
        'ok'    => true,
        'fel'   => $ce['fel'],
        'mesaj' => $ce['fel'] === 'golit'
            ? 'Comentariul a fost golit — avea răspunsuri sub el.'
            : 'Comentariul a fost șters.',
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

    if (!in_array($hotarare, ['aprobat', 'respins'], true)) {
        raspunsJson(['ok' => false, 'mesaj' => 'Hotărâre necunoscută.'], 422);
    }

    /**
     * `publicat_la` NU se pune aici, dinadins: îl scrie stampileazaCeleAprobate()
     * la prima încărcare a primei pagini, tot cu ceasul PHP. Așa, o dorință
     * aprobată de mână din phpMyAdmin și una aprobată de aici se poartă la fel —
     * iar cele șapte zile de pe tablă se numără din același loc.
     *
     * `stare_moderare = 'in_asteptare'` stă în WHERE: o dorință deja hotărâtă nu
     * se răzgândește dintr-o filă lăsată deschisă.
     */
    $q = db()->prepare(
        'UPDATE dorinte SET stare_moderare = ?
          WHERE id = ? AND stare_moderare = \'in_asteptare\''
    );
    $q->execute([$hotarare, $idCerut('id')]);

    if ($q->rowCount() !== 1) {
        raspunsJson([
            'ok'    => false,
            'mesaj' => 'Dorința asta a fost deja hotărâtă. Reîncarcă pagina.',
        ], 409);
    }

    raspunsJson([
        'ok'    => true,
        'stare' => $hotarare,
        'mesaj' => $hotarare === 'aprobat'
            ? 'Dorința a fost aprobată. Apare pe tablă de la prima încărcare a primei pagini.'
            : 'Dorința a fost respinsă. Omul poate pune alta.',
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

    raspunsJson(['ok' => true, 'mesaj' => 'Poza a fost ștearsă.']);
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

    raspunsJson(['ok' => true, 'mesaj' => 'Gata, am schimbat.']);
    break;

default:
    raspunsJson(['ok' => false, 'mesaj' => 'Nu știu ce să fac.'], 422);
}
