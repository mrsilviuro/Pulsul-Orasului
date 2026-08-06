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
const ZILE_PASTRARE_INCERCARI = 30;  // cât timp păstrăm încercările vechi

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

    if (empty($_SESSION['membru_id'])) {
        return null;
    }

    // Sesiunea trebuie să fi fost deschisă din același browser. Nu e o
    // barieră de netrecut, dar îngreunează folosirea unui cookie furat.
    if (($_SESSION['amprenta'] ?? '') !== amprentaBrowser()) {
        deconecteaza();
        return null;
    }

    // Prea mult timp fără nicio mișcare → sesiunea se închide.
    $ultima = (int) ($_SESSION['ultima_activitate'] ?? 0);
    if ($ultima > 0 && (time() - $ultima) > MINUTE_INACTIVITATE * 60) {
        deconecteaza();
        return null;
    }
    $_SESSION['ultima_activitate'] = time();

    $q = db()->prepare(
        'SELECT id, permalink, nume, prenume, email, sex, data_nasterii,
                localitate, poza, poza_actualizata_la, stare, creat_la
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

/* ====================== INTRAREA ȘI IEȘIREA ========================== */

/**
 * Deschide sesiunea pentru un membru.
 */
function autentifica(array $membru, bool $tineMinte = false): void
{
    pornesteSesiunea();

    // Pas obligatoriu: dacă atacatorul a reușit să impună mai devreme un
    // identificator de sesiune, acesta devine inutil în clipa asta.
    session_regenerate_id(true);

    $_SESSION['membru_id']        = (int) $membru['id'];
    $_SESSION['amprenta']         = amprentaBrowser();
    $_SESSION['ultima_activitate'] = time();
    $_SESSION['autentificat_la']  = time();

    // Token nou după schimbarea sesiunii.
    unset($_SESSION['csrf']);
    tokenCsrf();

    if ($tineMinte) {
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
    }
}

/**
 * Închide sesiunea și șterge cookie-ul.
 */
function deconecteaza(): void
{
    global $poMembruCache, $poMembruCitit;

    pornesteSesiunea();

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
