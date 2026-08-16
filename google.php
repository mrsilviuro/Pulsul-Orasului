<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — plecarea spre Google și întoarcerea de acolo.
 *
 * Același fișier face amândouă, ca să fie o singură adresă de scris în
 * consola Google:
 *
 *   google.php                      → trimite omul la Google
 *   google.php?code=...&state=...   → întoarcerea, cu codul
 *   google.php?error=access_denied  → omul s-a răzgândit acolo
 */

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/google.php';

/** Trimite omul înapoi la pagina de cont, cu un mesaj de necaz. */
function inapoiCuNecaz(string $mesaj): void
{
    pornesteSesiunea();
    $_SESSION['google_necaz'] = $mesaj;
    header('Location: login.php');
    exit;
}

if (!googleEsteConfigurat()) {
    inapoiCuNecaz('Intrarea cu Google nu e pornită pe site-ul ăsta.');
}

// Cine e deja conectat n-are ce căuta aici.
if (esteLogat()) {
    header('Location: index.php');
    exit;
}

/* ===================== 1. Omul s-a răzgândit la Google ================= */

if (isset($_GET['error'])) {
    $motiv = (string) $_GET['error'];

    inapoiCuNecaz($motiv === 'access_denied'
        ? 'Nu ai dat voie aplicației, deci nu te-am putut conecta.'
        : 'Google a oprit cererea. Încearcă din nou sau folosește e-mail și parolă.');
}

/* ========================= 2. Plecarea spre Google ===================== */

if (!isset($_GET['code'])) {
    /**
     * Unde vrem să ajungă omul după ce intră.
     *
     * Se acceptă doar căi din interiorul site-ului, ca la autentificarea
     * obișnuită: altfel parametrul ar putea fi folosit ca să trimită oamenii
     * pe un domeniu străin, imediat după ce s-au conectat la noi.
     */
    $inapoiLa = caleInterna($_GET['redirect'] ?? null);

    header('Location: ' . googleAdresaDePlecare($inapoiLa));
    exit;
}

/* ======================= 3. Întoarcerea de la Google =================== */

$necaz = googleVerificaState(isset($_GET['state']) && is_string($_GET['state']) ? $_GET['state'] : '');

if ($necaz !== '') {
    inapoiCuNecaz($necaz);
}

$rezultat = googleSchimbaCodul((string) $_GET['code']);

if (!$rezultat['ok']) {
    inapoiCuNecaz($rezultat['mesaj']);
}

$om = $rezultat['om'];

pornesteSesiunea();
$inapoiLa = (string) ($_SESSION['google_inapoi_la'] ?? '');
unset($_SESSION['google_inapoi_la']);

/* ------------------- 3a. Îl știm deja după Google? -------------------- */

$q = db()->prepare(
    'SELECT id, prenume, email, stare FROM membri WHERE google_id = ? LIMIT 1'
);
$q->execute([$om['sub']]);
$membru = $q->fetch();

/* -------------- 3b. Sau are cont făcut cu e-mail și parolă? ----------- */

if (!$membru) {
    $q = db()->prepare(
        'SELECT id, prenume, email, stare, google_id FROM membri WHERE email = ? LIMIT 1'
    );
    $q->execute([$om['email']]);
    $existent = $q->fetch();

    if ($existent) {
        /**
         * Se leagă cele două conturi.
         *
         * E în regulă tocmai pentru că Google ne-a spus că adresa e verificată
         * de el — altfel oricine și-ar putea trece în contul lui de Google
         * adresa altcuiva și ar intra peste el.
         *
         * Dacă adresa nu era încă confirmată la noi, o confirmăm acum: Google
         * tocmai a dovedit că omul chiar are cutia poștală aia.
         */
        if (!empty($existent['google_id']) && $existent['google_id'] !== $om['sub']) {
            inapoiCuNecaz('Adresa asta e deja legată de alt cont de Google.');
        }

        $u = db()->prepare(
            'UPDATE membri
                SET google_id = ?,
                    stare = IF(stare = \'neconfirmat\', \'activ\', stare),
                    confirmat_la = IFNULL(confirmat_la, ?),
                    token_confirmare = NULL, token_expira = NULL
              WHERE id = ?'
        );
        $u->execute([$om['sub'], acum(), (int) $existent['id']]);

        $q = db()->prepare('SELECT id, prenume, email, stare FROM membri WHERE id = ? LIMIT 1');
        $q->execute([(int) $existent['id']]);
        $membru = $q->fetch();
    }
}

/* ---------------------- 3c. Îl cunoaștem: intră ----------------------- */

if ($membru) {
    if ($membru['stare'] === 'suspendat') {
        inapoiCuNecaz('Contul este suspendat. Scrie-ne dacă vrei lămuriri.');
    }

    if ($membru['stare'] !== 'activ') {
        inapoiCuNecaz('Contul nu e activ. Verifică e-mailul de confirmare.');
    }

    /**
     * Cât e site-ul în lucru, intră doar oamenii de casă — pe orice ușă.
     *
     * Aceeași regulă și în același loc ca la intrarea cu parolă: ÎNAINTE de
     * autentifica(), ca sesiunea să nu se facă deloc. Altfel omul ar fi rămas
     * conectat în spatele unui site închis, fără nicio pagină la care să
     * ajungă.
     *
     * esteStaff() primește rândul citit mai sus; dacă acela n-are coloana
     * (SELECT-urile de aici cer doar câteva câmpuri), o citește singur din
     * bază după id — vezi inc/auth.php.
     */
    if (siteInConstructie() && !esteStaff($membru)) {
        inapoiCuNecaz('Site-ul e în lucru. Îți dăm de veste imediat ce deschidem.');
    }

    $u = db()->prepare('UPDATE membri SET autentificat_la = ? WHERE id = ?');
    $u->execute([acum(), (int) $membru['id']]);

    scrieIncercare((string) $membru['email'], true);

    /**
     * „Ține-mă minte" e pornit din start la intrarea cu Google.
     *
     * La intrarea clasică bifa are rost: omul își știe parola, deci poate
     * alege să nu rămână conectat, iar data viitoare o tastează din nou.
     * Aici n-avem unde pune bifa — drumul trece prin Google și se întoarce
     * singur — și nici n-ar avea ce alege: fără parolă la noi, singura cale
     * înapoi în cont e tot pe la Google. A-l scoate afară la fiecare
     * închidere de browser ar fi doar o plimbare în plus, fără niciun câștig.
     *
     * Folosim exact mecanismul de la login-ul clasic, al doilea parametru al
     * lui autentifica(), ca să nu ținem două feluri de „ține minte" în casă.
     */
    autentifica($membru, true);

    // „??=", nu „=": dacă intrarea tocmai a oprit o ștergere de cont,
    // autentifica() a lăsat acolo un mesaj mai important decât salutul.
    $_SESSION['mesaj_bun'] ??= 'Bine ai revenit, ' . $membru['prenume'] . '!';

    header('Location: ' . ($inapoiLa !== '' ? $inapoiLa : 'index.php'));
    exit;
}

/* ------------------ 3d. E om nou: îi mai cerem două date -------------- */

/**
 * Dar nu cât e site-ul în lucru: atunci nu se fac conturi noi, pe nicio ușă.
 *
 * Oprirea e AICI, nu în finalizare.php, fiindcă aici se află întâi că omul
 * n-are cont. Așa, `finalizare.php` și API-ul lui nici nu trebuie ținute
 * deschise în lista din inc/constructie.php: la ele nu se mai ajunge.
 *
 * Mesajul e același cu cel de la intrarea cu parolă, dinadins: nu spune dacă
 * adresa are sau n-are cont la noi.
 */
if (siteInConstructie()) {
    inapoiCuNecaz('Site-ul e în lucru. Îți dăm de veste imediat ce deschidem.');
}

/**
 * Google ne dă numele și adresa, dar nu și data nașterii sau sexul — iar pe
 * acelea le arătăm pe pagina de profil.
 *
 * Deci contul NU se face aici. Datele stau în sesiune, iar omul e trimis la o
 * pagină scurtă unde completează ce lipsește. Contul apare abia după.
 */
$_SESSION['google_nou'] = [
    'sub'      => $om['sub'],
    'email'    => $om['email'],
    'nume'     => $om['nume'],
    'prenume'  => $om['prenume'],
    'intreg'   => $om['intreg'],
    'inapoi_la'=> $inapoiLa,
    'la'       => time(),
];

header('Location: finalizare.php');
exit;
