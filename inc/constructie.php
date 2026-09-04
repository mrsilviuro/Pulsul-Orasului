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

/**
 * Semnul de pe disc care ține lacătul pus, în locul unei linii din config.
 *
 * DE CE UN FIȘIER, ȘI NU UN RÂND ÎN BAZĂ. Tocmai fiindcă ăsta e lacătul pe
 * care îl pui CÂND UMBLI LA BAZĂ. Un comutator care are nevoie de o
 * interogare ca să spună „e închis" e stins exact în clipa în care ai cea mai
 * mare nevoie de el.
 *
 * DE CE NU SE SCRIE ÎN inc/config.php. Fișierul acela e `require`-uit la
 * fiecare cerere: o scriere pe jumătate — curent căzut, disc plin, două
 * apăsări deodată — nu strică o setare, ci face tot site-ul să nu mai
 * pornească. Pe deasupra, pe multe găzduiri utilizatorul serverului nici n-are
 * drept de scriere acolo, și e bine că n-are.
 *
 * `private/` e locul în care aplicația scrie oricum (logurile), e închis din
 * .htaccess și e ținut deoparte de git. Un fișier gol: nu contează ce e în el,
 * ci dacă există.
 */
const FISIER_CONSTRUCTIE = __DIR__ . '/../private/in-constructie';

/**
 * E site-ul închis pentru lucrări?
 *
 * DOUĂ LACĂTE, iar cel din setări e mai tare: dacă `in_constructie` e pornit
 * în inc/config.php, site-ul e închis și comutatorul din admin nu-l poate
 * deschide. E dinadins — ăla e drumul care merge oricum, chiar dacă discul e
 * doar de citit, chiar dacă cineva a intrat peste o sesiune de om al casei.
 * Comutatorul din admin e cel de zi cu zi; linia din config e cheia de rezervă.
 */
function siteInConstructie(): bool
{
    return lacatulEDinSetari() || is_file(FISIER_CONSTRUCTIE);
}

/** Lacătul e pus din inc/config.php, deci nu se poate scoate din admin. */
function lacatulEDinSetari(): bool
{
    global $config;

    return !empty($config['in_constructie']);
}

/**
 * Pune sau scoate lacătul de zi cu zi. Întoarce dacă a mers.
 *
 * ÎNTOARCE FALSE ÎN LOC SĂ ARUNCE: cel care cheamă funcția e o pagină de
 * administrare, iar ea trebuie să poată spune „n-am putut scrie în private/,
 * uită-te la drepturi" — nu să se prăbușească peste omul care tocmai a apăsat
 * un buton.
 *
 * `clearstatcache()` fiindcă `is_file()` ține minte: fără el, pagina care
 * răspunde chiar apăsării ar arăta starea de dinaintea ei.
 */
function puneLacatul(bool $pornit): bool
{
    $mers = $pornit
        ? (is_file(FISIER_CONSTRUCTIE) || file_put_contents(FISIER_CONSTRUCTIE, '') !== false)
        : (!is_file(FISIER_CONSTRUCTIE) || @unlink(FISIER_CONSTRUCTIE));

    clearstatcache(true, FISIER_CONSTRUCTIE);

    return (bool) $mers;
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
        // Afișul cere o adresă de e-mail, deci documentele care spun ce
        // facem cu ea trebuie să se poată citi de acolo. O politică de
        // confidențialitate încuiată e o contradicție în termeni.
        'termeni.php',
        'confidentialitate.php',
        'cookies.php',
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

/* ==================== DISPOZITIVUL OMULUI DE CASĂ ===================== */

/**
 * Cât ține minte site-ul un dispozitiv pe care a intrat un om de casă.
 *
 * Treizeci de zile, ca „ține-mă minte" — și din același motiv: e răstimpul
 * după care o casă în care s-a schimbat cine intră trebuie să întrebe din nou.
 */
const ZILE_SANTIER   = 30;
const COOKIE_SANTIER = 'po_santier';

/**
 * Cheia cu care se semnează permisul de dispozitiv.
 *
 * Aceeași croială ca cheieDezabonare() din inc/newsletter.php: dacă e scrisă
 * una în setări, aceea e; altfel se face una din lucruri care sunt oricum
 * deosebite de la un site la altul. Nu e nevoie de o cheie nouă în config, dar
 * se poate pune una.
 */
function cheieSantier(): string
{
    global $config;

    $scrisa = trim((string) ($config['cheie_santier'] ?? ''));

    if ($scrisa !== '') {
        return $scrisa;
    }

    return hash('sha256', 'santier|'
        . (string) ($config['db']['parola'] ?? '')
        . '|' . (string) ($config['url_site'] ?? ''));
}

/**
 * PERMISUL DE ȘANTIER: „pe dispozitivul ăsta a intrat cândva un om de casă".
 *
 * DE CE EXISTĂ. Cât e site-ul închis, înăuntru trecea numai staff-ul — deci
 * omul de casă nu-și putea proba propria muncă. Ca să vadă site-ul cu ochii
 * unui om obișnuit, trebuia să iasă din contul lui de staff, iar în clipa
 * aceea rămânea el însuși pe dinafară. Un site închis pe care nu-l poți încerca
 * decât ca administrator nu e închis pentru lucrări, e închis pentru lucru.
 *
 * CE E ÎN EL: doar clipa în care expiră și o semnătură a ei. Nici un id de om,
 * nimic despre cine a fost — de aceea trece dintr-un cont în altul, ceea ce e
 * chiar rostul lui.
 *
 * CE NU E: o cheie de-a casei. Nu deschide nimic din ce e închis cu adevărat —
 * zona de admin, faptele, paginile care cer cont — fiindcă alea întreabă toate
 * `esteStaff()` din bază, la fiecare cerere. Deschide un singur lucru: ușa de
 * la stradă, cât e pusă tăblia „se lucrează". Cel mai rău lucru pe care îl
 * poate face cineva cu un permis furat e să vadă site-ul așa cum îl va vedea
 * oricum toată lumea peste o zi.
 *
 * SEMNAT, nu doar scris: „expira.HMAC(expira)". Fără semnătură ar fi fost de
 * ajuns ca cineva să-și scrie singur un cookie ca să treacă de lacăt, iar
 * atunci lacătul n-ar mai fi fost lacăt.
 */
function permisSantier(int $expira): string
{
    return $expira . '.' . hash_hmac('sha256', 'santier:' . $expira, cheieSantier());
}

/** Poartă dispozitivul ăsta un permis bun și neexpirat? */
function dispozitivCunoscut(): bool
{
    $brut = $_COOKIE[COOKIE_SANTIER] ?? null;

    if (!is_string($brut) || !str_contains($brut, '.')) {
        return false;
    }

    [$expira, ] = explode('.', $brut, 2);

    if (!ctype_digit($expira) || (int) $expira <= time()) {
        return false;
    }

    /* hash_equals, nu „===": o comparație obișnuită se oprește la prima
       literă deosebită, iar din cât durează se poate ghici semnătura. */
    return hash_equals(permisSantier((int) $expira), $brut);
}

/**
 * Scrie permisul pe dispozitiv. Aceleași apărări ca la cookie-ul de amintire.
 */
function scrieCookieSantier(string $valoare, int $expira): void
{
    if (headers_sent()) {
        return;
    }

    setcookie(COOKIE_SANTIER, $valoare, [
        'expires'  => $expira,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,   // JavaScript nu-l vede
        'samesite' => 'Lax',  // nu pleacă la cereri pornite de pe alt site
    ]);
}

/** Uită dispozitivul — butonul de lângă comutatorul din admin. */
function uitaDispozitivul(): void
{
    unset($_COOKIE[COOKIE_SANTIER]);
    scrieCookieSantier('', time() - 42000);
}

/**
 * Ține minte dispozitivul, dacă la volan e un om de casă.
 *
 * SE CHEAMĂ ORICÂND, nu doar cât e închis — și asta e partea care contează.
 * Permisul trebuie să fie deja pe dispozitiv ÎNAINTE de a se pune lacătul:
 * altfel omul închide site-ul, iese din contul de staff ca să încerce cu unul
 * de probă, și abia atunci află că nu mai are cum să intre nici într-un fel.
 *
 * Se scrie doar când lipsește: un `Set-Cookie` la fiecare cerere n-ar aduce
 * nimic în plus. Deci permisul se înnoiește o dată la ZILE_SANTIER.
 */
function tineMinteDispozitivul(): void
{
    if (dispozitivCunoscut() || !esteStaff()) {
        return;
    }

    $expira = time() + ZILE_SANTIER * 86400;
    scrieCookieSantier(permisSantier($expira), $expira);
}

/**
 * Are omul ăsta voie înăuntru cât e închis?
 *
 * DOUĂ CĂI, și amândouă sunt bune:
 *
 *   esteStaff()          — omul de casă, întrebat DIN BAZĂ la fiecare cerere,
 *                          nu dintr-un semn pus în sesiune. Un drept luat
 *                          înapoi trebuie să dispară imediat.
 *   dispozitivCunoscut() — dispozitivul pe care a intrat cândva unul, oricine
 *                          ar fi conectat acum. Vezi permisSantier(), mai sus.
 *
 * $membru se dă din afară acolo unde SESIUNEA NU S-A FĂCUT ÎNCĂ: la intrarea
 * în cont (api/autentificare.php) și la cea cu Google, întrebarea se pune
 * despre rândul tocmai citit din bază, fiindcă membruCurent() ar fi încă null.
 * Lăsat gol, se întreabă despre cine e conectat acum.
 *
 * SE CHEAMĂ DIN TREI LOCURI, și trebuie să rămână așa: lacătul de la intrarea
 * în pagini (opresteDacaEInConstructie, mai jos) și cele două uși de intrare
 * în cont. Scrisă de mână în vreuna din ele — cum a fost o vreme, cu
 * `esteStaff()` singur — regula se desparte: pe pagini treceai cu permisul de
 * dispozitiv, dar nu te puteai conecta ca să le vezi.
 */
function poateIntraInConstructie(?array $membru = null): bool
{
    return esteStaff($membru) || dispozitivCunoscut();
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
    if (!cerereDinBrowser()) {
        return;
    }

    /**
     * Permisul de dispozitiv se scrie ÎNAINTE de întrebarea „e închis?", nu
     * după — deci și cât e site-ul deschis.
     *
     * Altfel s-ar fi scris abia la prima cerere de după punerea lacătului, iar
     * omul de casă care închide site-ul și iese din cont ca să încerce cu unul
     * de probă ar fi rămas pe dinafară exact atunci.
     */
    tineMinteDispozitivul();

    if (!siteInConstructie()) {
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
