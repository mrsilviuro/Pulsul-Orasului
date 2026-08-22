<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — ușa închisă cât timp se lucrează la site.
 *
 * `in_constructie` din inc/config.php pus pe true înseamnă că tot ce se vede
 * de afară e pagina de așteptare. Aici stă regula întreagă: cine trece, ce uși
 * rămân deschise și ce se întâmplă cu restul.
 *
 * NU E O ASCUNDERE DE OCHI. Nu se ascund doar paginile, ci se opresc și
 * API-urile din spatele lor: altfel oricine ar fi putut citi lista de
 * evenimente cu un curl, sau și-ar fi făcut cont, cât timp pe ecran scria „ne
 * pregătim". O ușă închisă doar la vedere nu e o ușă închisă.
 */

require_once __DIR__ . '/bootstrap.php';

// Pentru verificaEmail(), la înscrierea pe lista de vești. Se cere aici, nu se
// lasă pe seama celui care ne include: fișierul trebuie să se țină singur pe
// picioare, oricine l-ar chema primul.
require_once __DIR__ . '/validare.php';

/** E site-ul închis pentru lucrări? */
function siteInConstructie(): bool
{
    global $config;

    return !empty($config['in_constructie']);
}

/**
 * Ușile care rămân deschise, cât e site-ul închis.
 *
 * Căile sunt relative la rădăcina site-ului, iar potrivirea se face pe
 * FIȘIERUL DE PE DISC, nu pe adresa cerută. E singurul fel în care lista chiar
 * înseamnă ceva: o comparație pe URL ar fi trebuit să se apere singură de
 * „/api/../index.php", de bara în plus și de literele mari, adică exact felul
 * de verificare scrisă de mână care lasă mereu ceva să treacă.
 *
 * De ce fiecare:
 *
 *   constructie.php      — pagina de așteptare. Fără ea, închiderea ar fi o
 *                          buclă de redirecționări.
 *   login.php            — pe unde intră oamenii de casă.
 *   api/autentificare.php— ce face login.php de fapt: pagina trimite datele
 *                          acolo, prin fetch. Fără ea, formularul ar fi doar
 *                          un desen.
 *   api/newsletter.php   — singurul lucru pe care îl poate face un vizitator:
 *                          să-și lase adresa.
 *   iesire.php           — ca nimeni să nu rămână închis înăuntru. Cine era
 *                          conectat în clipa în care s-a pus lacătul trebuie
 *                          să poată ieși din cont, altfel ar rămâne cu o
 *                          sesiune pe care n-o poate încheia.
 *   google.php           — a doua ușă de intrare, la fel de bună ca prima:
 *                          omul de casă care și-a făcut contul cu Google n-are
 *                          parolă la noi, deci fără ea n-ar avea pe unde intra
 *                          deloc.
 *
 * `finalizare.php` și `api/finalizare-google.php` NU sunt pe listă, deși țin
 * de același drum: ele fac un cont NOU, iar cât e site-ul închis nu se fac
 * conturi noi. Nici nu se ajunge la ele — google.php se oprește singur înainte,
 * când vede că omul care s-a întors de la Google n-are încă un cont aici.
 */
function usileDeschiseInConstructie(): array
{
    return [
        'constructie.php',
        'login.php',
        'iesire.php',
        'api/autentificare.php',
        'api/newsletter.php',
        'google.php',
    ];
}

/** Rulăm dintr-o pagină cerută de cineva, sau din linia de comandă? */
function cerereDinBrowser(): bool
{
    return PHP_SAPI !== 'cli';
}

/**
 * E fișierul care rulează acum una dintre ușile deschise?
 *
 * Se compară căi de pe disc, aduse amândouă la forma lor adevărată cu
 * realpath() — așa „api/../login.php" și „login.php" ajung același lucru, iar
 * legăturile simbolice nu mai pot arăta două nume pentru același fișier.
 */
function eUsaDeschisa(): bool
{
    $rulat = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));

    if ($rulat === false) {
        return false;
    }

    $radacina = dirname(__DIR__);

    foreach (usileDeschiseInConstructie() as $cale) {
        $usa = realpath($radacina . '/' . $cale);

        if ($usa !== false && $usa === $rulat) {
            return true;
        }
    }

    return false;
}

/** Cererea de acum e către un API, deci așteaptă JSON, nu o pagină? */
function cerereDeApi(): bool
{
    $rulat = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));

    if ($rulat === false) {
        return false;
    }

    return str_starts_with($rulat, realpath(dirname(__DIR__) . '/api') . DIRECTORY_SEPARATOR);
}

/**
 * Are omul ăsta voie înăuntru cât e închis?
 *
 * Numai staff-ul. Se citește din bază la fiecare cerere, prin esteStaff(), nu
 * dintr-un semn pus în sesiune: aceeași regulă ca la starea contului. Un drept
 * luat înapoi trebuie să dispară imediat, nu la următoarea conectare.
 */
function poateIntraInConstructie(): bool
{
    return esteStaff();
}

/**
 * Lacătul. Se cheamă o singură dată, la coada lui inc/auth.php.
 *
 * De ce ACOLO și nu în fiecare pagină: fiindcă o listă de pagini care trebuie
 * să-și pună singure lacătul e o listă din care lipsește mereu una — cea
 * scrisă mâine. Așa, orice fișier care are de-a face cu un om conectat trece
 * pe aici fără să știe, iar cine adaugă o pagină nouă n-are ce uita.
 *
 * Nu se mai întoarce dacă ușa e închisă: oprește cererea pe loc, înainte să se
 * fi tipărit ceva.
 */
function opresteDacaEInConstructie(): void
{
    // Cronurile și testele rulează din linia de comandă. Ele n-au ce căuta
    // după ușă: nu sunt vizitatori, sunt casa însăși.
    if (!cerereDinBrowser() || !siteInConstructie()) {
        return;
    }

    if (eUsaDeschisa() || poateIntraInConstructie()) {
        return;
    }

    /**
     * API-urile primesc JSON, nu o redirecționare.
     *
     * Un fetch care se trezește cu HTML-ul paginii de așteptare în loc de
     * răspuns ar arăta pe ecran „ceva n-a mers", fără să spună ce. Iar 503 e
     * răspunsul potrivit: nu „n-ai voie" (403), ci „nu acum".
     */
    if (cerereDeApi()) {
        raspunsJson([
            'ok'    => false,
            'mesaj' => 'Site-ul e în lucru. Revino în curând.',
        ], 503);
    }

    /**
     * Cale relativă, ca peste tot pe site („Location: index.php" din login.php,
     * „Location: login.php" din cereIntrare()). Nu `urlSite()`:
     *
     *   - toate paginile stau în rădăcină, deci „constructie.php" se rezolvă
     *     corect și dacă site-ul e pus într-un subdosar
     *     (http://localhost/pulsulorasului), unde un „/constructie.php" ar fi
     *     ieșit din el;
     *   - iar o adresă întreagă luată din setări duce omul exact acolo unde
     *     scrie în ele — chiar și când scrie greșit. Un `url_site` uitat pe
     *     valoarea din exemplu ar fi trimis tot site-ul pe alt domeniu.
     *
     * API-urile nu ajung aici niciodată: ele au primit JSON mai sus. Altfel un
     * „constructie.php" relativ, cerut de la /api/, ar fi arătat spre
     * /api/constructie.php.
     */
    header('Location: /constructie.php', true, 302);
    exit;
}

/* ===================== CINE VREA SĂ AFLE CÂND DESCHIDEM ================ */

/** Câte înscrieri se primesc de la aceeași adresă IP într-o oră. */
const ABONARI_PE_ORA_PE_IP = 5;

/**
 * Trece o adresă pe lista celor care vor să afle când deschidem.
 *
 * Întoarce ['ok' => bool, 'mesaj' => 'ce se scrie pe ecran', 'cod' => 200].
 *
 * Stă aici, nu în api/newsletter.php, fiindcă o cer DOUĂ uși: API-ul, când
 * pagina are JavaScript, și constructie.php însuși, când n-are. Scrisă în
 * amândouă, ar fi fost două limite care se despart la prima schimbare și două
 * feluri de a răspunde la aceeași adresă.
 *
 * Ce NU face: nu se uită la token și nu se uită la capcana de roboți. Alea țin
 * de cererea de afară, nu de listă, și se verifică sus, la fiecare ușă.
 */
function inscrieLaVesti($emailCerut): array
{
    $email = verificaEmail($emailCerut);

    if ($email['eroare'] !== '') {
        return ['ok' => false, 'mesaj' => $email['eroare'], 'cod' => 422];
    }

    $ip = ipBinar();

    if ($ip !== null) {
        $q = db()->prepare(
            'SELECT COUNT(*) FROM abonati_newsletter WHERE ip = ? AND creat_la > ?'
        );
        $q->execute([$ip, acumMinus(60)]);

        if ((int) $q->fetchColumn() >= ABONARI_PE_ORA_PE_IP) {
            return [
                'ok'    => false,
                'mesaj' => 'Prea multe înscrieri de aici. Încearcă mai târziu.',
                'cod'   => 429,
            ];
        }
    }

    /**
     * A doua înscriere cu aceeași adresă nu e o eroare și nu se spune că e.
     *
     * `ON DUPLICATE KEY UPDATE id = id` e felul de a spune „dacă e deja acolo,
     * las-o în pace": rândul își păstrează data dintâi, iar cererea se încheie
     * liniștit. Nu se face un UPDATE adevărat, ca o a doua apăsare să nu poată
     * împinge pe cineva mai încolo în listă.
     *
     * Pe ecran, răspunsul e ACELAȘI ca la prima înscriere. Nu fiindcă n-am
     * ști, ci fiindcă un „ești deja înscris" ar fi făcut din formular un loc
     * unde oricine poate afla, adresă cu adresă, cine e pe listă. Unicitatea o
     * ține baza (vezi sql/019-newsletter.sql), nu o întrebare pusă înainte, pe
     * lângă care două apăsări în aceeași clipă ar trece amândouă.
     */
    db()->prepare(
        'INSERT INTO abonati_newsletter (email, ip, creat_la)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE id = id'
    )->execute([$email['email'], $ip, acum()]);

    return [
        'ok'    => true,
        'mesaj' => 'Gata! Îți dăm de veste imediat ce deschidem.',
        'cod'   => 200,
    ];
}
