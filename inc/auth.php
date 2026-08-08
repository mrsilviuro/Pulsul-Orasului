<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — sesiunea membrului autentificat.
 *
 * Aici stau: cine e conectat, cum se conectează și cum se deconectează, plus
 * numărătoarea încercărilor greșite.
 */

require_once __DIR__ . '/bootstrap.php';

const INCERCARI_MAXIME       = 3;    // greșeli permise înainte de blocare
const MINUTE_BLOCARE         = 10;   // cât ține blocarea
const INCERCARI_MAXIME_IP    = 15;   // limită mai largă, pe adresă IP
const MINUTE_INTRE_RETRIMITERI = 10; // între două e-mailuri de confirmare
const MINUTE_INACTIVITATE    = 120;  // sesiunea expiră după atâta liniște
const ZILE_TINE_MINTE        = 30;   // cât ține „ține-mă minte"
const COOKIE_TINE_MINTE      = 'po_amintit'; // cookie-ul care o poartă
const ZILE_PASTRARE_INCERCARI = 30;  // cât timp păstrăm încercările vechi

/* Parola temporară, pentru cine și-a uitat parola */
const MINUTE_PAROLA_TEMPORARA   = 60; // cât e valabilă
const MINUTE_INTRE_CERERI_PAROLA = 10; // între două cereri de recuperare
const INCERCARI_PAROLA_TEMPORARA = 5;  // greșeli după care se stinge singură

/* ======================= CINE E CONECTAT ============================== */

/**
 * Datele membrului conectat, sau null dacă nu e nimeni.
 *
 * Se citesc din baza de date la fiecare cerere, nu din sesiune: dacă un cont
 * e suspendat sau șters, efectul e imediat, nu la următoarea autentificare.
 */
function membruCurent(): ?array
{
    // Rezultatul se ține minte pentru cererea curentă, ca să nu întrebăm baza
    // de mai multe ori. Nu e „static" în funcție, ci global, tocmai ca
    // deconecteaza() să îl poată goli — altfel, o pagină care deconectează și
    // apoi afișează meniul l-ar desena ca și cum omul ar fi încă înăuntru.
    global $poMembruCache, $poMembruCitit;

    if (!empty($poMembruCitit)) {
        return $poMembruCache;
    }
    $poMembruCitit = true;
    $membru = null;

    pornesteSesiunea();

    // Fără sesiune deschisă mai există o cale: cine a bifat „ține-mă minte"
    // are un cookie din care ridicăm sesiunea la loc, fără parolă.
    if (empty($_SESSION['membru_id']) && !inviaDinAmintire()) {
        return null;
    }

    // Sesiunea trebuie să fi fost deschisă din același browser. Nu e o
    // barieră de netrecut, dar îngreunează folosirea unui cookie furat.
    if (($_SESSION['amprenta'] ?? '') !== amprentaBrowser()) {
        deconecteaza();
        return null;
    }

    /**
     * Prea mult timp fără nicio mișcare → sesiunea se închide.
     *
     * Pentru cine a bifat „ține-mă minte" asta nu înseamnă ieșire din cont:
     * sesiunea se stinge, dar amintirea rămâne și ridică imediat alta. Omul
     * nu simte nimic, iar cele două ore rămân bune pentru toți ceilalți.
     */
    $ultima = (int) ($_SESSION['ultima_activitate'] ?? 0);
    if ($ultima > 0 && (time() - $ultima) > MINUTE_INACTIVITATE * 60) {
        inchideSesiunea();

        if (!inviaDinAmintire()) {
            return null;
        }
    }
    $_SESSION['ultima_activitate'] = time();

    $q = db()->prepare(
        'SELECT id, permalink, nume, prenume, email, sex, data_nasterii,
                localitate, poza, poza_actualizata_la, stare, creat_la,
                parola_schimbata_la
           FROM membri
          WHERE id = ?
          LIMIT 1'
    );
    $q->execute([(int) $_SESSION['membru_id']]);
    $gasit = $q->fetch();

    // Contul a dispărut sau a fost suspendat între timp.
    if (!$gasit || $gasit['stare'] !== 'activ') {
        deconecteaza();
        return null;
    }

    $poMembruCache = $gasit;
    return $poMembruCache;
}

/**
 * Un membru după adresa lui publică (permalink), pentru profilul văzut din
 * afară. Întoarce null dacă nu există sau dacă nu e activ.
 *
 * Aceleași coloane ca la membrul conectat, minus e-mailul: pe profilul altcuiva
 * n-are ce căuta. Conturile suspendate sau anonimizate nu se deschid deloc —
 * altfel un cont șters ar rămâne o pagină care se poate deschide la nesfârșit.
 */
function membruDupaPermalink(string $permalink): ?array
{
    // Alfabetul permalinkului: cifre și litere, fără 0/O/1/l/I. Orice altceva
    // nici nu ajunge până la bază.
    if (preg_match('/^[A-Za-z0-9]{6,20}$/', $permalink) !== 1) {
        return null;
    }

    $q = db()->prepare(
        'SELECT id, permalink, nume, prenume, sex, data_nasterii,
                localitate, poza, poza_actualizata_la, stare, creat_la
           FROM membri
          WHERE permalink = ?
          LIMIT 1'
    );
    $q->execute([$permalink]);
    $gasit = $q->fetch();

    return ($gasit && $gasit['stare'] === 'activ') ? $gasit : null;
}

/**
 * Trimite omul să se conecteze și îl aduce înapoi de unde a plecat.
 *
 * $inapoiLa — calea de pe site la care voia să ajungă, cu parametri cu tot
 *             („/event.php?slug=..."). Trece prin caleInterna(): dacă nu e o
 *             cale de-a noastră, se pierde, și omul rămâne pe prima pagină.
 *
 * Nu se mai întoarce: opreșe pagina pe loc. De aceea se cheamă înainte să se
 * fi tipărit ceva — după primul octet trimis, un antet nu mai poate pleca.
 */
function cereIntrare(string $inapoiLa = ''): void
{
    $cale = caleInterna($inapoiLa);

    header('Location: login.php' . ($cale !== '' ? '?redirect=' . urlencode($cale) : ''));
    exit;
}

function esteLogat(): bool
{
    return membruCurent() !== null;
}

/**
 * O amprentă simplă a browserului. Nu include adresa IP, pentru că se schimbă
 * firesc la trecerea de pe Wi-Fi pe date mobile și ar deconecta oamenii pe
 * nedrept.
 */
function amprentaBrowser(): string
{
    return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|po-amprenta');
}

/* ========================= „ȚINE-MĂ MINTE" =========================== */

/**
 * De ce nu e de ajuns un cookie de sesiune cu dată de expirare peste o lună.
 *
 * Cookie-ul spune doar cât timp îl păstrează BROWSERUL. Conținutul sesiunii
 * stă pe server, într-un fișier, iar acela e șters de PHP după
 * session.gc_maxlifetime — pe majoritatea găzduirilor, douăzeci și patru de
 * minute de liniște. Pe deasupra, pe găzduirile partajate fișierele stau
 * într-un dosar comun, unde mătură și vecinii, după setările lor.
 *
 * Așa că ce trebuie să dureze treizeci de zile nu e sesiunea, ci DOVADA că
 * omul s-a autentificat cândva de pe dispozitivul ăsta. Dovada stă în
 * sesiuni_amintite, iar sesiunea se ridică din ea de câte ori e nevoie.
 *
 * Cookie-ul are forma „selector:secret". Selectorul spune care rând, secretul
 * dovedește că e al tău; în baza de date intră doar sha256 al secretului.
 */

/** Cookie-ul de amintire, cu aceleași apărări ca cel de sesiune. */
function scrieCookieAmintire(string $valoare, int $expira): void
{
    if (headers_sent()) {
        return;
    }

    setcookie(COOKIE_TINE_MINTE, $valoare, [
        'expires'  => $expira,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,   // JavaScript nu-l vede
        'samesite' => 'Lax',  // nu pleacă la cereri pornite de pe alt site
    ]);
}

function stergeCookieAmintire(): void
{
    unset($_COOKIE[COOKIE_TINE_MINTE]);
    scrieCookieAmintire('', time() - 42000);
}

/**
 * Ține minte dispozitivul ăsta.
 *
 * Fără $rotit scrie un rând nou. Cu el, împrospătează unul existent: același
 * selector, secret nou.
 *
 * De ce se păstrează selectorul la rotație, în loc să ștergem rândul și să
 * scriem altul: selectorul rămas în tabel e singurul fel în care putem
 * recunoaște mai târziu un cookie vechi. Dacă rândul ar dispărea, un cookie
 * replayat n-ar mai nimeri nimic și l-am trata ca pe o amintire oarecare,
 * expirată — fără să bănuim că cineva încearcă unul furat.
 *
 * Data expirării se ia tot din rândul vechi, ca cele treizeci de zile să
 * curgă de la autentificare, nu de la ultima vizită. Altfel cine intră zilnic
 * n-ar mai fi întrebat niciodată de parolă.
 */
function tineMinteAcest(int $membruId, ?array $rotit = null): void
{
    $secret = bin2hex(random_bytes(32));

    try {
        if ($rotit !== null) {
            $selector = (string) $rotit['selector'];
            $expira   = strtotime((string) $rotit['expira']);

            db()->prepare(
                'UPDATE sesiuni_amintite
                    SET token_hash = ?, amprenta = ?, folosit_la = ?
                  WHERE id = ?'
            )->execute([
                hash('sha256', $secret), amprentaBrowser(), acum(), (int) $rotit['id'],
            ]);
        } else {
            $selector = bin2hex(random_bytes(16));
            $expira   = time() + ZILE_TINE_MINTE * 24 * 3600;

            db()->prepare(
                'INSERT INTO sesiuni_amintite
                    (membru_id, selector, token_hash, amprenta, expira, creat_la, folosit_la)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $membruId,
                $selector,
                hash('sha256', $secret),
                amprentaBrowser(),
                date('Y-m-d H:i:s', $expira),
                acum(),
                acum(),
            ]);
        }
    } catch (PDOException $e) {
        // Fără rând nu are rost nici cookie-ul: omul rămâne conectat pe
        // sesiunea obișnuită, doar că nu treizeci de zile.
        return;
    }

    scrieCookieAmintire($selector . ':' . $secret, $expira);
    curataAmintirileVechi();
}

/** Uită dispozitivul curent (la ieșirea din cont). */
function uitaAmintirea(): void
{
    $brut = $_COOKIE[COOKIE_TINE_MINTE] ?? null;

    if (is_string($brut) && str_contains($brut, ':')) {
        [$selector] = explode(':', $brut, 2);

        if (preg_match('/^[0-9a-f]{32}$/', $selector)) {
            try {
                db()->prepare('DELETE FROM sesiuni_amintite WHERE selector = ?')
                    ->execute([$selector]);
            } catch (PDOException $e) {
                // Cookie-ul tot dispare mai jos.
            }
        }
    }

    stergeCookieAmintire();
}

/**
 * Uită toate dispozitivele unui membru.
 *
 * Se cheamă la schimbarea parolei: cine tocmai și-a luat contul înapoi
 * trebuie să dea afară pe oricine era rămas conectat, oriunde.
 */
function uitaToateAle(int $membruId): void
{
    try {
        db()->prepare('DELETE FROM sesiuni_amintite WHERE membru_id = ?')
            ->execute([$membruId]);
    } catch (PDOException $e) {
        // Nu blocăm schimbarea parolei pentru atâta lucru.
    }
}

/**
 * Ridică sesiunea din cookie-ul de amintire. Întoarce true dacă a reușit.
 */
function inviaDinAmintire(): bool
{
    // Ridicarea unei sesiuni cere antete: unul pentru sesiune, altul pentru
    // cookie-ul rotit. După ce pagina a început să se tipărească, n-avem cum.
    if (headers_sent()) {
        return false;
    }

    $brut = $_COOKIE[COOKIE_TINE_MINTE] ?? null;

    if (!is_string($brut) || !str_contains($brut, ':')) {
        return false;
    }

    [$selector, $secret] = explode(':', $brut, 2);

    // Formă greșită = cookie meșterit de cineva. Nu întrebăm baza degeaba.
    if (!preg_match('/^[0-9a-f]{32}$/', $selector) || !preg_match('/^[0-9a-f]{64}$/', $secret)) {
        stergeCookieAmintire();
        return false;
    }

    try {
        $q = db()->prepare(
            'SELECT id, membru_id, selector, token_hash, amprenta, expira
               FROM sesiuni_amintite
              WHERE selector = ?
              LIMIT 1'
        );
        $q->execute([$selector]);
        $rand = $q->fetch();
    } catch (PDOException $e) {
        return false;   // baza tace acum; cookie-ul rămâne, poate merge mai târziu
    }

    if (!$rand) {
        stergeCookieAmintire();
        return false;
    }

    /**
     * Selector bun, secret greșit — semn rău.
     *
     * Un cookie cinstit are mereu perechea potrivită. Nepotrivirea înseamnă
     * ori un cookie vechi rămas după rotație, ori unul furat și încercat. Nu
     * putem ști care, așa că ștergem toate amintirile omului: în cel mai rău
     * caz o dăm afară pe hoț, iar stăpânul contului mai tastează o dată
     * parola.
     */
    if (!hash_equals((string) $rand['token_hash'], hash('sha256', $secret))) {
        uitaToateAle((int) $rand['membru_id']);
        stergeCookieAmintire();
        return false;
    }

    // Ceasul e tot al nostru, al PHP-ului, nu al bazei.
    if (strtotime((string) $rand['expira']) <= time()) {
        stergeAmintirea((int) $rand['id']);
        stergeCookieAmintire();
        return false;
    }

    // Cookie mutat pe alt browser.
    if (!hash_equals((string) $rand['amprenta'], amprentaBrowser())) {
        stergeAmintirea((int) $rand['id']);
        stergeCookieAmintire();
        return false;
    }

    $q = db()->prepare('SELECT id, stare FROM membri WHERE id = ? LIMIT 1');
    $q->execute([(int) $rand['membru_id']]);
    $membru = $q->fetch();

    if (!$membru || $membru['stare'] !== 'activ') {
        stergeAmintirea((int) $rand['id']);
        stergeCookieAmintire();
        return false;
    }

    /**
     * Rotația: același rând, secret nou.
     *
     * Așa un cookie citit de pe fir sau de pe un calculator împrumutat e bun
     * o singură dată. A doua oară cade pe ramura de mai sus — selector bun,
     * secret vechi — și stinge toate amintirile omului.
     */
    autentifica($membru, true, false, [
        'id'       => (int) $rand['id'],
        'selector' => (string) $rand['selector'],
        'expira'   => (string) $rand['expira'],
    ]);

    return true;
}

function stergeAmintirea(int $id): void
{
    try {
        db()->prepare('DELETE FROM sesiuni_amintite WHERE id = ?')->execute([$id]);
    } catch (PDOException $e) {
        // Nimic de făcut: cookie-ul e oricum șters de cel care ne-a chemat.
    }
}

/** Rândurile expirate, măturate din când în când. */
function curataAmintirileVechi(): void
{
    if (random_int(1, 50) !== 1) {
        return;
    }

    try {
        db()->prepare('DELETE FROM sesiuni_amintite WHERE expira < ?')->execute([acum()]);
    } catch (PDOException $e) {
        // Curățenia nu e esențială.
    }
}

/* ====================== INTRAREA ȘI IEȘIREA ========================== */

/**
 * Deschide sesiunea pentru un membru.
 */
function autentifica(
    array $membru,
    bool $tineMinte = false,
    bool $cuParolaTemporara = false,
    ?array $amintire = null
): void {
    pornesteSesiunea();

    // Pas obligatoriu: dacă atacatorul a reușit să impună mai devreme un
    // identificator de sesiune, acesta devine inutil în clipa asta.
    session_regenerate_id(true);

    $_SESSION['membru_id']        = (int) $membru['id'];
    $_SESSION['amprenta']         = amprentaBrowser();
    $_SESSION['ultima_activitate'] = time();
    $_SESSION['autentificat_la']  = time();

    /**
     * Cine intră cu parola temporară nu poate face nimic până nu-și alege una
     * nouă. E singurul final cu sens pentru recuperare: altfel omul ar rămâne
     * în cont cu parola veche, cea uitată, tot acolo — și data viitoare ar fi
     * din nou pe dinafară. În plus, o parolă trimisă prin e-mail a trecut prin
     * prea multe mâini ca să rămână singura cheie a contului.
     */
    $_SESSION['trebuie_parola_noua'] = $cuParolaTemporara;

    // Token nou după schimbarea sesiunii.
    unset($_SESSION['csrf']);
    tokenCsrf();

    anuleazaStergereaLaIntrare((int) $membru['id']);

    if ($tineMinte) {
        /**
         * Două cookie-uri, două treburi diferite.
         *
         * Cel de sesiune primește și el dată de expirare, ca omul să rămână
         * conectat după ce închide browserul. Dar el ține doar cât ține și
         * fișierul sesiunii pe server — adică puțin.
         *
         * Treizeci de zile le ține al doilea, cel de amintire: din el se
         * ridică o sesiune nouă ori de câte ori cea veche s-a stins.
         */
        $durata = ZILE_TINE_MINTE * 24 * 3600;
        $p = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires'  => time() + $durata,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $_SESSION['tine_minte'] = true;
        tineMinteAcest((int) $membru['id'], $amintire);
    }
}

/**
 * Intrarea în cont oprește ștergerea cerută mai devreme.
 *
 * Stă aici, în autentifica(), fiindcă prin ea trec toate drumurile de intrare
 * — parolă, Google, ultimul pas al înregistrării cu Google. Un singur loc,
 * deci nu poate fi uitat la vreunul dintre ele.
 *
 * Nu are buton și nu întreabă nimic: omului i s-a scris în e-mail că e destul
 * să intre. Scrierea se face doar dacă chiar era ceva de anulat, iar
 * rowCount() ne spune asta fără o citire în plus.
 */
function anuleazaStergereaLaIntrare(int $membruId): void
{
    try {
        $u = db()->prepare(
            'UPDATE membri
                SET cerere_stergere = NULL, token_stergere = NULL, token_stergere_expira = NULL
              WHERE id = ? AND cerere_stergere IS NOT NULL'
        );
        $u->execute([$membruId]);

        if ($u->rowCount() > 0) {
            $_SESSION['mesaj_bun'] = 'Bine ai revenit! Ștergerea contului a fost anulată — '
                                   . 'contul tău rămâne activ, cu toate datele lui.';
        }
    } catch (PDOException $e) {
        // Migrarea 007 nu e rulată încă. Intrarea în cont nu are de suferit.
    }
}

/**
 * Închide sesiunea și șterge cookie-ul.
 */
function deconecteaza(): void
{
    global $poMembruCache, $poMembruCitit;

    pornesteSesiunea();

    // Ieșirea din cont înseamnă și uitarea dispozitivului: altfel cookie-ul
    // de amintire l-ar aduce înapoi la următoarea pagină deschisă.
    uitaAmintirea();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_destroy();

    // De acum, pentru restul cererii, nu mai e nimeni conectat.
    $poMembruCache = null;
    $poMembruCitit = true;
}

/**
 * Închide sesiunea, dar lasă amintirea în pace.
 *
 * Diferența față de deconecteaza(): aia e ieșirea din cont, cerută de om, și
 * uită dispozitivul. Asta e doar sesiunea care s-a stins de la sine, iar
 * cookie-ul de amintire trebuie să rămână, ca să poată ridica alta.
 */
function inchideSesiunea(): void
{
    global $poMembruCache;

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }

    $poMembruCache = null;
}

/* ======================== PAROLA TEMPORARĂ =========================== */

/**
 * Se potrivește ce a tastat omul cu parola temporară trimisă pe e-mail?
 *
 * Funcția face și curățenia: parola temporară e bună o singură dată, deci se
 * șterge fie când e folosită, fie când se termină cele cinci încercări.
 *
 * $membru poate fi null (adresă inexistentă). Chiar și atunci se face o
 * verificare pe un hash inventat, ca durata răspunsului să fie aceeași — vezi
 * explicația despre enumerarea conturilor din api/autentificare.php.
 */
function incearcaParolaTemporara(?array $membru, string $parola): bool
{
    $hashFals = '$2y$12$usesomesillystringfoeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    $areTemporara = $membru !== null && !empty($membru['parola_temporara_hash']);

    $expirata = $areTemporara
        && (empty($membru['parola_temporara_expira'])
            || strtotime((string) $membru['parola_temporara_expira']) <= time());

    $prea_multe = $areTemporara
        && (int) ($membru['parola_temporara_incercari'] ?? 0) >= INCERCARI_PAROLA_TEMPORARA;

    // Verificarea se face oricum, chiar și pe un hash care nu e al nimănui.
    $potrivire = password_verify(
        $parola,
        ($areTemporara && !$expirata && !$prea_multe)
            ? (string) $membru['parola_temporara_hash']
            : $hashFals
    );

    if (!$areTemporara) {
        return false;
    }

    // O parolă expirată sau consumată nu mai are ce căuta în bază.
    if ($expirata || $prea_multe) {
        stergeParolaTemporara((int) $membru['id']);
        return false;
    }

    if ($potrivire) {
        stergeParolaTemporara((int) $membru['id']);
        return true;
    }

    // Greșeală: crește contorul. La a cincea, parola se stinge singură.
    $q = db()->prepare(
        'UPDATE membri SET parola_temporara_incercari = parola_temporara_incercari + 1
          WHERE id = ?'
    );
    $q->execute([(int) $membru['id']]);

    if ((int) ($membru['parola_temporara_incercari'] ?? 0) + 1 >= INCERCARI_PAROLA_TEMPORARA) {
        stergeParolaTemporara((int) $membru['id']);
    }

    return false;
}

function stergeParolaTemporara(int $idMembru): void
{
    $q = db()->prepare(
        'UPDATE membri
            SET parola_temporara_hash = NULL, parola_temporara_expira = NULL,
                parola_temporara_incercari = 0
          WHERE id = ?'
    );
    $q->execute([$idMembru]);
}

/* ===================== PAROLA CARE TREBUIE SCHIMBATĂ ================= */

/** A intrat cu parola temporară și încă nu și-a ales una nouă? */
function trebuieParolaNoua(): bool
{
    pornesteSesiunea();
    return !empty($_SESSION['trebuie_parola_noua']);
}

/** Se cheamă după ce omul și-a pus parola nouă. */
function gataCuParolaTemporara(): void
{
    pornesteSesiunea();
    $_SESSION['trebuie_parola_noua'] = false;
}

/**
 * Oprește orice altceva până când parola e schimbată.
 *
 * Se cheamă din inc/antet.php, deci acoperă toate paginile dintr-un singur
 * loc, și din punctele de intrare din api/, care nu trec pe acolo.
 *
 * Paginile lăsate deschise sunt doar cele fără de care omul ar rămâne blocat:
 * cea de schimbat parola și ieșirea din cont.
 */
function opresteDacaTrebuieParolaNoua(bool $esteApi = false): void
{
    if (!trebuieParolaNoua()) {
        return;
    }

    $pagina = basename($_SERVER['SCRIPT_NAME'] ?? '');

    if (in_array($pagina, ['parola-noua.php', 'iesire.php'], true)) {
        return;
    }

    if ($esteApi) {
        raspunsJson([
            'ok'       => false,
            'mesaj'    => 'Alege-ți întâi o parolă nouă.',
            'redirect' => 'parola-noua.php',
        ], 403);
    }

    if (!headers_sent()) {
        header('Location: parola-noua.php');
        exit;
    }
}

/* ==================== ÎNCERCĂRILE DE AUTENTIFICARE =================== */

function scrieIncercare(string $email, bool $reusita): void
{
    // Momentul e scris de PHP, nu lăsat pe seama bazei de date: vezi
    // explicația despre ceasuri din inc/bootstrap.php.
    $q = db()->prepare(
        'INSERT INTO incercari_autentificare (email, ip, reusita, creat_la)
         VALUES (?, ?, ?, ?)'
    );
    $q->execute([mb_substr($email, 0, 190), ipBinar(), $reusita ? 1 : 0, acum()]);

    curataIncercariVechi();
}

/**
 * Șterge încercările mai vechi de 30 de zile.
 *
 * Blocarea se uită doar la ultimele 10 minute, deci rândurile vechi nu mai
 * folosesc la nimic. Le păstrăm o lună doar cât să se poată vedea un tipar
 * de atacuri, apoi dispar — atât ca tabelul să nu crească la nesfârșit, cât
 * și pentru că sunt date personale (adresă de e-mail plus adresă IP) pe care
 * nu avem motiv să le ținem mai mult.
 *
 * Curățarea se face din când în când, la aproximativ una din 50 de scrieri,
 * nu la fiecare: e o ștergere ieftină, dar n-are rost făcută de fiecare dată.
 * Așa nu e nevoie nici de o sarcină programată separat, care în XAMPP oricum
 * ar trebui pornită de mână.
 */
function curataIncercariVechi(): void
{
    if (random_int(1, 50) !== 1) {
        return;
    }

    try {
        $q = db()->prepare('DELETE FROM incercari_autentificare WHERE creat_la < ?');
        $q->execute([acumMinus(ZILE_PASTRARE_INCERCARI * 24 * 60)]);
    } catch (PDOException $e) {
        // Curățenia nu e esențială: dacă dă greș, autentificarea continuă.
    }
}

/**
 * Câte secunde mai durează blocarea, sau 0 dacă formularul e liber.
 *
 * Se numără doar greșelile de după ultima intrare reușită, ca cineva care
 * a greșit de două ori și apoi a intrat corect să nu rămână cu ele în spate.
 */
function secundeBlocare(string $email): int
{
    $ip = ipBinar();

    $de_cand = acumMinus(MINUTE_BLOCARE);

    // 1. greșeli pentru această adresă, de la acest calculator
    $q = db()->prepare(
        'SELECT COUNT(*) AS greseli, MAX(creat_la) AS ultima
           FROM incercari_autentificare
          WHERE email = ?
            AND ip <=> ?
            AND reusita = 0
            AND creat_la > ?
            AND creat_la > COALESCE(
                  (SELECT MAX(creat_la) FROM incercari_autentificare
                    WHERE email = ? AND ip <=> ? AND reusita = 1),
                  \'1970-01-01\')'
    );
    $q->execute([$email, $ip, $de_cand, $email, $ip]);
    $r = $q->fetch();

    if ((int) $r['greseli'] >= INCERCARI_MAXIME) {
        return secundeRamase((string) $r['ultima']);
    }

    // 2. limită mai largă: multe adrese încercate de la același calculator
    if ($ip !== null) {
        $q = db()->prepare(
            'SELECT COUNT(*) AS greseli, MAX(creat_la) AS ultima
               FROM incercari_autentificare
              WHERE ip = ? AND reusita = 0
                AND creat_la > ?'
        );
        $q->execute([$ip, $de_cand]);
        $r = $q->fetch();

        if ((int) $r['greseli'] >= INCERCARI_MAXIME_IP) {
            return secundeRamase((string) $r['ultima']);
        }
    }

    return 0;
}

function secundeRamase(string $ultimaIncercare): int
{
    if ($ultimaIncercare === '') {
        return 0;
    }

    $sfarsit = (new DateTimeImmutable($ultimaIncercare))
        ->modify('+' . MINUTE_BLOCARE . ' minutes');

    $ramas = $sfarsit->getTimestamp() - time();
    return max(0, $ramas);
}

/**
 * Câte greșeli mai sunt permise înainte de blocare (doar informativ).
 */
function incercariRamase(string $email): int
{
    $ip = ipBinar();

    $q = db()->prepare(
        'SELECT COUNT(*) FROM incercari_autentificare
          WHERE email = ? AND ip <=> ? AND reusita = 0
            AND creat_la > ?
            AND creat_la > COALESCE(
                  (SELECT MAX(creat_la) FROM incercari_autentificare
                    WHERE email = ? AND ip <=> ? AND reusita = 1),
                  \'1970-01-01\')'
    );
    $q->execute([$email, $ip, acumMinus(MINUTE_BLOCARE), $email, $ip]);

    return max(0, INCERCARI_MAXIME - (int) $q->fetchColumn());
}

/* ========================= TEXTE AJUTĂTOARE ========================== */

/** „3 minute", „45 de secunde" — pentru mesajele către utilizator. */
function durataInCuvinte(int $secunde): string
{
    if ($secunde >= 60) {
        $minute = (int) ceil($secunde / 60);
        return $minute === 1 ? 'un minut' : $minute . ' minute';
    }
    return $secunde <= 1 ? 'o secundă' : $secunde . ' de secunde';
}
