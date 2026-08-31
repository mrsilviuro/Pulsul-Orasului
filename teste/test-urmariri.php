<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — urmărirea unui organizator.
 *
 * Cere BAZA DE DATE. Partea de HTTP cere și SERVERUL, dar se sare singură dacă
 * nu i se dă o adresă.
 *
 *     php teste/test-urmariri.php
 *     php teste/test-urmariri.php http://127.0.0.1:8099
 *
 * CE PĂZEȘTE, mai presus de orice: că VESTEA PLEACĂ O SINGURĂ DATĂ pe anunț.
 * Restul — butonul, cifra, comutarea — se văd pe ecran și se descoperă repede
 * dacă se strică; un al doilea e-mail către aceiași oameni nu se vede nicăieri
 * până nu se plânge cineva.
 */

require_once __DIR__ . '/../inc/urmariri.php';

$BAZA = rtrim($argv[1] ?? '', '/');

$treceri = 0; $picaturi = 0;

function verifica(string $ce, $asteptat, $primit): void
{
    global $treceri, $picaturi;
    $ok = $asteptat === $primit;
    $ok ? $treceri++ : $picaturi++;
    printf("%-58s %s%s\n", $ce, $ok ? 'OK' : 'PICAT',
        $ok ? '' : "  (aștept " . var_export($asteptat, true) . ", am primit " . var_export($primit, true) . ")");
}

function sectiune(string $nume): void
{
    echo "\n" . str_repeat('-', 60) . "\n  " . mb_strtoupper($nume, 'UTF-8') . "\n"
       . str_repeat('-', 60) . "\n";
}

const SEMN   = 'tsturm-';
const PAROLA = 'ParolaDeProba#2026';

function curata(): void
{
    db()->prepare('DELETE u FROM urmariri u JOIN membri m
                     ON m.id = u.urmaritor_id OR m.id = u.urmarit_id
                    WHERE m.permalink LIKE ?')->execute([SEMN . '%']);
    db()->prepare('DELETE e FROM evenimente e JOIN membri m ON m.id = e.membru_id
                    WHERE m.permalink LIKE ?')->execute([SEMN . '%']);
    db()->prepare('DELETE FROM membri WHERE permalink LIKE ?')->execute([SEMN . '%']);
}

curata();
register_shutdown_function('curata');

function faMembru(string $cheie, string $prenume, string $stare = 'activ'): int
{
    db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, sex, data_nasterii,
                             parola_hash, stare, este_staff, creat_la, confirmat_la)
         VALUES (?,?,?,?,"M","1990-01-01",?,?,0,?,?)'
    )->execute([
        substr(SEMN . $cheie, 0, 16), 'Probă', $prenume,
        SEMN . $cheie . '@invalid.local',
        password_hash(PAROLA, PASSWORD_DEFAULT), $stare, acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

function faEveniment(int $cine, string $slug, string $stare): int
{
    db()->prepare(
        'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, oras, locatie,
                                 descriere, data_eveniment, ora_inceput, stare_moderare,
                                 creat_la, actualizat_la)
         VALUES (?, (SELECT MIN(id) FROM categorii), ?, ?, ?, "La pod", ?, ?, "19:00", ?,?,?)'
    )->execute([
        $cine, 'Proba ' . $slug, $slug, oraseDisponibile()[0] ?? 'Roman',
        str_repeat('Povestea lui. ', 20), date('Y-m-d', strtotime('+7 days')),
        $stare, acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

$org  = faMembru('org',  'Organizator');
$fan  = faMembru('fan',  'Urmaritor');
$fan2 = faMembru('fan2', 'Alta');
$dus  = faMembru('dus',  'Plecat', 'sters');
$sus  = faMembru('sus',  'Suspendat', 'suspendat');

$randOrg = ['id' => $org, 'stare' => 'activ'];

/* ==================================================================== */
sectiune('cine pe cine poate urmări');

verifica('un om oarecare, da', true,  poateFiUrmarit(['id' => $fan], $randOrg));
verifica('pe tine însuți, NU',  false, poateFiUrmarit(['id' => $org], $randOrg));
verifica('nelogat, NU',         false, poateFiUrmarit(null, $randOrg));

/**
 * Un cont golit n-o să mai pună niciodată nimic — un buton acolo ar fi o
 * promisiune pe care n-o poate ține nimeni.
 */
verifica('un cont șters, NU', false,
    poateFiUrmarit(['id' => $fan], ['id' => $dus, 'stare' => 'sters']));

/* ==================================================================== */
sectiune('apăsarea butonului');

verifica('la început nu-l urmărește', false, esteUrmarit($fan, $org));
verifica('și n-are niciun urmăritor', 0, catiUrmaritori($org));

$dupa = comutaUrmarirea($fan, $org);
verifica('prima apăsare începe urmărirea', true, $dupa['urmareste']);
verifica('cifra urcă',                     1,    $dupa['cati']);
verifica('și se vede din bază',            true, esteUrmarit($fan, $org));

/**
 * A DOUA APĂSARE O IA ÎNAPOI. Nu există „dez-urmărire" scrisă undeva: rândul
 * pur și simplu nu mai e.
 */
$dupa = comutaUrmarirea($fan, $org);
verifica('a doua apăsare o oprește', false, $dupa['urmareste']);
verifica('cifra coboară',            0,     $dupa['cati']);
verifica('și rândul chiar a plecat', false, esteUrmarit($fan, $org));

/* Se poate din nou, oricând. */
comutaUrmarirea($fan, $org);
verifica('se poate lua de la capăt', true, esteUrmarit($fan, $org));

/**
 * DOUĂ APĂSĂRI VENITE DEODATĂ nu scriu două rânduri: cheia unică din sql/033 e
 * cea care hotărăște, nu un „întreabă, apoi scrie" din PHP. Aici se probează
 * urmarea ei — a doua chemare vede rândul și îl ia înapoi, deci nu se poate
 * ajunge niciodată la doi.
 */
comutaUrmarirea($fan2, $org);
verifica('doi oameni, doi urmăritori', 2, catiUrmaritori($org));

/* ==================================================================== */
sectiune('cui i se scrie');

$catre = static fn(): array => array_map(
    static fn(array $o): int => (int) $o['id'], urmaritoriDeInstiintat($org));

verifica('amândoi sunt pe listă', [$fan, $fan2], $catre());

/**
 * CONTURILE CARE NU MAI SUNT ACTIVE nu primesc nimic — n-au unde. Aceeași
 * regulă ca la omulDeInstiintat() din inc/interese.php.
 */
comutaUrmarirea($sus, $org);
comutaUrmarirea($dus, $org);

verifica('suspendatul și cel șters nu intră în listă', [$fan, $fan2], $catre());
verifica('dar urmăririle lor sunt scrise',              4, catiUrmaritori($org));

/* Curat pentru ce urmează: rămân doar cei doi cărora chiar li se scrie. */
comutaUrmarirea($sus, $org);
comutaUrmarirea($dus, $org);

/* ==================================================================== */
sectiune('vestea pleacă o singură dată');

global $config;

$logEmail = __DIR__ . '/../private/emailuri-trimise.log';

$idEv = faEveniment($org, 'tsturm-1', 'aprobat');

$inainte = is_file($logEmail) ? (int) filesize($logEmail) : 0;

verifica('pleacă la câți urmăritori are', 2, instiinteazaUrmaritorii($idEv));

/**
 * A DOUA CHEMARE NU TRIMITE NIMIC. Ștampila e pusă în `WHERE`, ca la
 * revendicarea unui abțibild: cine n-o prinde, tace. Fără ea, un anunț respins
 * și aprobat din nou ar fi scris de două ori acelorași oameni.
 */
verifica('a doua oară, nimic', 0, instiinteazaUrmaritorii($idEv));
verifica('și a treia oară',    0, instiinteazaUrmaritorii($idEv));

$q = db()->prepare('SELECT urmaritori_instiintati_la FROM evenimente WHERE id = ?');
$q->execute([$idEv]);
verifica('ștampila e pusă', true, $q->fetchColumn() !== null);

if (!empty($config['dezvoltare'])) {
    $nou  = (string) preg_replace('/\s+/u', ' ',
        substr((string) file_get_contents($logEmail), $inainte));

    verifica('mesajul spune cine a pus anunțul', true,
        str_contains($nou, 'P. Organizator a pus un anunț nou'));
    verifica('și poartă titlul lui', true, str_contains($nou, 'Proba tsturm-1'));
    verifica('cu adresa evenimentului', true, str_contains($nou, 'tsturm-1'));

    /* Ieșirea e butonul, nu o pagină de dezabonare: mesajul spune unde e. */
    verifica('spune cum se oprește', true, str_contains($nou, 'apasă din nou'));
    verifica('fără link de dezabonare', false, str_contains($nou, 'dezabonare.php'));
}

/**
 * UN ANUNȚ CARE NU SE VEDE nu trimite nimic, oricâți urmăritori ar fi. Aceeași
 * întrebare ca peste tot pe site — evenimentPublicat() — nu una scrisă de mână
 * aici.
 */
foreach (['in_asteptare', 'respins', 'anulat', 'incheiat'] as $stare) {
    $idAlt = faEveniment($org, 'tsturm-' . $stare, $stare);
    verifica('din „' . $stare . '" nu pleacă nimic', 0, instiinteazaUrmaritorii($idAlt));
}

/**
 * ȘI UNUL APROBAT CU ÎNTÂRZIERE, pentru o zi care a trecut deja.
 *
 * Aici s-a găsit o scăpare la scriere: `evenimentPublicat()` spune DA și
 * pentru un anunț încheiat, fiindcă pagina lui se vede mai departe — deci
 * vestea ar fi plecat. „X a pus un anunț nou" despre o seară de acum trei
 * zile sună a bătaie de joc. Se cere acum și `!evenimentIncheiat()`, care se
 * uită la CEAS, nu doar la stare.
 */
db()->prepare(
    'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, oras, locatie,
                             descriere, data_eveniment, ora_inceput, stare_moderare,
                             creat_la, actualizat_la)
     VALUES (?, (SELECT MIN(id) FROM categorii), ?, ?, ?, "La pod", ?, ?, "19:00",
             "aprobat", ?, ?)'
)->execute([
    $org, 'Proba tsturm-trecut', 'tsturm-trecut', oraseDisponibile()[0] ?? 'Roman',
    str_repeat('Povestea lui. ', 20), date('Y-m-d', strtotime('-3 days')), acum(), acum(),
]);

verifica('aprobat, dar cu ziua trecută: nimic', 0,
    instiinteazaUrmaritorii((int) db()->lastInsertId()));

/* Și un eveniment care nu există nu supără pe nimeni. */
verifica('un id inexistent nu strică nimic', 0, instiinteazaUrmaritorii(0));

/* ==================================================================== */
sectiune('cum arată butonul');

$butonAltuia = randeazaButonUrmarire(['id' => $fan], $randOrg);

verifica('are fapta pe el',       true, str_contains($butonAltuia, 'data-urmareste="' . $org . '"'));
verifica('și tokenul lui',        true, str_contains($butonAltuia, 'data-csrf="'));
verifica('scrie „Urmărești"',    true, str_contains($butonAltuia, 'Urmărești'));
verifica('și e apăsat',           true, str_contains($butonAltuia, 'aria-pressed="true"'));

/* Pe propriul profil nu se desenează nimic: n-ai pe cine urmări acolo. */
verifica('pe propriul profil, nimic', '', randeazaButonUrmarire(['id' => $org], $randOrg));

/* Nici pe al unui cont golit. */
verifica('pe un cont șters, nimic', '',
    randeazaButonUrmarire(['id' => $fan], ['id' => $dus, 'stare' => 'sters']));

/**
 * NELOGAT VEDE BUTONUL, dar el duce la intrarea în cont. Un buton care lipsește
 * nu spune nimănui că ar putea exista.
 */
$butonStrain = randeazaButonUrmarire(null, $randOrg + ['permalink' => SEMN . 'org']);
verifica('nelogatul îl vede',        true, str_contains($butonStrain, 'Urmărește'));
verifica('și duce la intrarea în cont', true, str_contains($butonStrain, '/login.php?redirect='));
verifica('fără să poată apăsa fapta',  false, str_contains($butonStrain, 'data-urmareste'));

/* CIFRA SE VEDE ȘI CÂND E ZERO — e o informație despre om, nu o răsplată. */
$fara = faMembru('fara', 'Nimeni');
verifica('cifra zero se scrie', true,
    str_contains(randeazaButonUrmarire(['id' => $fan], ['id' => $fara, 'stare' => 'activ']),
                 '>0</span>'));

/* ==================================================================== */
sectiune('unde stă butonul pe pagina unui eveniment');

/**
 * BUTONUL STĂ ÎN RÂNDUL DE BUTOANE din antet, lângă „Fixează" și „Editează",
 * nu singur în rândul cu numele organizatorului.
 *
 * Cine se uită la anunțul altcuiva n-are niciun buton de-al lui acolo, deci
 * rândul are un singur locatar. Tocmai ăsta e cazul care s-a rupt o dată:
 * wrapper-ul era scris de $actiuni, iar $actiuni lipsește la un vizitator
 * oarecare — butonul rămânea pe dinafară. De aceea rândul se desenează acum
 * din inc/afisare-eveniment.php, care le vede pe amândouă.
 */
require_once __DIR__ . '/../inc/afisare-eveniment.php';

$deDesenat = [
    'titlu' => 'Proba', 'categorie' => 'Sport', 'oras' => 'Roman',
    'locatie' => 'La pod', 'descriere' => 'Text.', 'organizator' => 'P. Probă',
    'data_eveniment' => date('Y-m-d', strtotime('+7 days')), 'ora_inceput' => '19:00',
    'urmarire' => randeazaButonUrmarire(['id' => $fan], $randOrg),
];

ob_start();
afiseazaEveniment($deDesenat);
$antet = (string) ob_get_clean();

/* Rândul există chiar dacă nu i s-a dat niciun buton de organizator. */
verifica('rândul de butoane se desenează', 1, substr_count($antet, 'class="post__actiuni"'));

/* Și butonul e ÎNĂUNTRUL lui, nu înaintea lui. */
$dupaRand = substr($antet, (int) strpos($antet, 'class="post__actiuni"'));
verifica('butonul e în rând', true, str_contains($dupaRand, 'data-urmareste="' . $org . '"'));

/* FĂRĂ BUTON, FĂRĂ RÂND: previzualizarea nu-l cheamă cu nimic în el, iar un
   rând gol ar fi lăsat o gaură în antet. */
unset($deDesenat['urmarire']);
ob_start();
afiseazaEveniment($deDesenat);
verifica('fără nimic de pus, niciun rând', false,
    str_contains((string) ob_get_clean(), 'post__actiuni'));

/* ==================================================================== */
if ($BAZA === '') {
    echo "\n(sar peste HTTP: dă adresa serverului ca argument)\n";
} else {
    sectiune('paza punctului de intrare');

    $cheama = static function (array $date, string $cookie = '') use ($BAZA): array {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n"
                       . ($cookie === '' ? '' : "Cookie: $cookie\r\n"),
            'content' => json_encode($date),
            'ignore_errors' => true, 'timeout' => 10,
        ]]);

        $corp = (string) @file_get_contents($BAZA . '/api/urmareste.php', false, $ctx);
        $cod  = 0;

        foreach ($http_response_header ?? [] as $rand) {
            if (preg_match('~^HTTP/\S+\s+(\d+)~', $rand, $m)) { $cod = (int) $m[1]; }
        }

        return ['cod' => $cod, 'corp' => json_decode($corp, true)];
    };

    verifica('fără token CSRF, 419', 419, $cheama(['membru' => $org])['cod']);
    verifica('nelogat, nu trece', true,
        in_array($cheama(['csrf' => 'oarecare', 'membru' => $org])['cod'], [401, 419], true));

    // GET nu e primit deloc.
    $raw = @file_get_contents($BAZA . '/api/urmareste.php', false,
        stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]));
    $cod = 0;
    foreach ($http_response_header ?? [] as $rand) {
        if (preg_match('~^HTTP/\S+\s+(\d+)~', $rand, $m)) { $cod = (int) $m[1]; }
    }
    verifica('GET nu e primit', 405, $cod);

    /* Și nimic nu s-a schimbat în bază după toate încercările astea. */
    verifica('urmăririle au rămas cum erau', 2, catiUrmaritori($org));
}

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
