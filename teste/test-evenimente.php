<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — publicarea unui eveniment.
 *
 * Prin HTTP, cu cookie-uri adevărate și cu poze adevărate urcate ca
 * multipart/form-data — adică exact ce face browserul.
 *
 * Cum se rulează:
 *     php -S 127.0.0.1:8150 -t . &
 *     php teste/test-evenimente.php http://127.0.0.1:8150
 */

require_once __DIR__ . '/../inc/evenimente.php';

$baza = rtrim($argv[1] ?? 'http://127.0.0.1:8150', '/');

$treceri = 0; $picaturi = 0;

function verifica(string $ce, $asteptat, $primit): void
{
    global $treceri, $picaturi;
    $ok = $asteptat === $primit;
    $ok ? $treceri++ : $picaturi++;
    printf("%-58s %s%s\n", $ce, $ok ? 'OK' : 'PICAT',
        $ok ? '' : "  (aștept " . var_export($asteptat, true) . ", am primit " . var_export($primit, true) . ")");
}

/* ---------------------------- unelte HTTP ----------------------------- */

/**
 * O cerere multipart, cu fișier cu tot.
 *
 * $fisiere = ['coperta' => ['nume' => 'x.jpg', 'tip' => 'image/jpeg', 'continut' => '...']]
 */
function cerere(string $url, array &$cookies, ?array $campuri = null, array $fisiere = []): array
{
    $antete = ['User-Agent: Mozilla/5.0 (TestEvenimente)'];

    if ($cookies !== []) {
        $perechi = [];
        foreach ($cookies as $k => $v) { $perechi[] = $k . '=' . urlencode($v); }
        $antete[] = 'Cookie: ' . implode('; ', $perechi);
    }

    $optiuni = ['http' => [
        'method'          => $campuri === null ? 'GET' : 'POST',
        'ignore_errors'   => true,
        'follow_location' => 0,
        'timeout'         => 20,
    ]];

    if ($campuri !== null) {
        $granita = '----po' . bin2hex(random_bytes(8));
        $corp    = '';

        foreach ($campuri as $nume => $valoare) {
            if ($valoare === null) { continue; }

            // Un tablou se trimite ca „nume[]", de mai multe ori — așa arată
            // un câmp pe care cineva l-a stricat dinadins, ca să ajungă array
            // în $_POST acolo unde codul aștepta un șir.
            foreach (is_array($valoare) ? $valoare : [$valoare] as $bucata) {
                $cheie = is_array($valoare) ? $nume . '[]' : $nume;
                $corp .= "--$granita\r\n";
                $corp .= "Content-Disposition: form-data; name=\"$cheie\"\r\n\r\n";
                $corp .= $bucata . "\r\n";
            }
        }

        foreach ($fisiere as $nume => $f) {
            $corp .= "--$granita\r\n";
            $corp .= "Content-Disposition: form-data; name=\"$nume\"; filename=\"{$f['nume']}\"\r\n";
            $corp .= "Content-Type: {$f['tip']}\r\n\r\n";
            $corp .= $f['continut'] . "\r\n";
        }

        $corp .= "--$granita--\r\n";

        $antete[] = 'Content-Type: multipart/form-data; boundary=' . $granita;
        $optiuni['http']['content'] = $corp;
    }

    $optiuni['http']['header'] = $antete;

    $raspuns = @file_get_contents($url, false, stream_context_create($optiuni));
    $ant     = $http_response_header ?? [];

    foreach ($ant as $a) {
        if (stripos($a, 'Set-Cookie:') !== 0) { continue; }
        $prima = explode(';', trim(substr($a, 11)))[0];
        [$n, $v] = array_pad(explode('=', $prima, 2), 2, '');
        if ($v === '') { unset($cookies[$n]); } else { $cookies[$n] = urldecode($v); }
    }

    $stare = 0;
    if (isset($ant[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $ant[0], $m)) {
        $stare = (int) $m[1];
    }

    $unde = '';
    foreach ($ant as $a) {
        if (stripos($a, 'Location:') === 0) { $unde = trim(substr($a, 9)); }
    }

    return ['stare' => $stare, 'corp' => (string) $raspuns, 'unde' => $unde];
}

function json_din(array $r): array
{
    $d = json_decode($r['corp'], true);
    return is_array($d) ? $d : [];
}

/** O poză JPEG adevărată, de mărimea cerută. */
function pozaDeProba(int $latime, int $inaltime): string
{
    $im = imagecreatetruecolor($latime, $inaltime);
    imagefilledrectangle($im, 0, 0, $latime, $inaltime, imagecolorallocate($im, 200, 90, 60));
    // Câteva dungi, ca fișierul să nu fie o singură culoare comprimată la nimic.
    for ($i = 0; $i < $latime; $i += 40) {
        imagefilledrectangle($im, $i, 0, $i + 12, $inaltime, imagecolorallocate($im, 40, 60, 120));
    }
    ob_start();
    imagejpeg($im, null, 85);
    imagedestroy($im);
    return (string) ob_get_clean();
}

/**
 * O poză în degrade, de la negru la roșu, de la stânga la dreapta.
 *
 * Se poate citi din ea unde a fost tăiată: cât roșu are un pixel din poza
 * salvată spune din ce parte a originalului vine.
 */
function pozaInDegrade(int $latime, int $inaltime): string
{
    $im = imagecreatetruecolor($latime, $inaltime);

    for ($x = 0; $x < $latime; $x++) {
        $rosu = (int) round(255 * $x / max(1, $latime - 1));
        imageline($im, $x, 0, $x, $inaltime, imagecolorallocate($im, $rosu, 40, 40));
    }

    ob_start();
    imagejpeg($im, null, 92);
    imagedestroy($im);
    return (string) ob_get_clean();
}

/** Din ce parte a originalului (0…$latimeSursa) vine pixelul de la $x. */
function deUndeVine(string $cale, int $x, int $latimeSursa): int
{
    $im = imagecreatefromjpeg($cale);
    $culoare = imagecolorsforindex($im, imagecolorat($im, $x, 450));
    imagedestroy($im);

    return (int) round($culoare['red'] / 255 * ($latimeSursa - 1));
}

/* ---------------------------- teren curat ------------------------------ */

const EMAIL_ORG = 'organizator-test@exemplu-test.ro';
const PAROLA_TEST = 'ParolaDeTest#2026';

function faMembru(string $email, string $permalink): int
{
    db()->prepare('DELETE FROM evenimente WHERE membru_id IN (SELECT id FROM membri WHERE email = ?)')
        ->execute([$email]);
    db()->prepare('DELETE FROM membri WHERE email = ?')->execute([$email]);
    db()->prepare('DELETE FROM incercari_autentificare WHERE email = ?')->execute([$email]);

    db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, parola_hash, sex,
                             data_nasterii, stare, creat_la, confirmat_la)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    )->execute([$permalink, 'Popescu', 'Ionuț', $email,
        password_hash(PAROLA_TEST, PASSWORD_DEFAULT), 'M', '1990-05-20', 'activ', acum(), acum()]);

    return (int) db()->lastInsertId();
}

function intra(array &$cookies, string $email): void
{
    $p = cerere($GLOBALS['baza'] . '/login.php', $cookies);
    preg_match('/name="csrf"\s+value="([0-9a-f]+)"/', $p['corp'], $m);

    cerere($GLOBALS['baza'] . '/api/autentificare.php', $cookies, [
        'csrf' => $m[1] ?? '', 'email' => $email, 'parola' => PAROLA_TEST, 'tine_minte' => '1',
    ]);
}

/**
 * Token-ul CSRF al sesiunii, luat din linkul de ieșire.
 *
 * NU din formular: când omul are deja un eveniment activ, pagina arată panoul
 * de blocare, care n-are formular — și deci nici token. Linkul de ieșire e pe
 * orice pagină, oricând.
 */
function csrf(array &$cookies): string
{
    $r = cerere($GLOBALS['baza'] . '/index.php', $cookies);
    preg_match('/iesire\.php\?token=([0-9a-f]+)/', $r['corp'], $m);
    return $m[1] ?? '';
}

/** Un eveniment bun, cu ce vrem schimbat pe deasupra. */
function trimite(array &$cookies, array $peste = [], array $fisiere = []): array
{
    $descriere = str_repeat('Pornim din fața primăriei și mergem agale prin centrul vechi. ', 8);

    return json_din(cerere($GLOBALS['baza'] . '/api/eveniment.php', $cookies, array_merge([
        'csrf'             => csrf($cookies),
        'titlu'            => 'Cursa de seară prin centrul vechi',
        'categorie_id'     => '1',
        'oras'             => 'Roman',
        'locatie'          => 'Piața Sfatului, lângă fântână',
        // Formularul trimite ZZ-LL-AAAA, cum se scrie o dată în România;
        // traducerea spre AAAA-LL-ZZ o face dataDinFormular() pe server.
        'data_eveniment'   => date('d-m-Y', strtotime('+10 days')),
        'ora_inceput'      => '19:00',
        'fara_ora_sfarsit' => '1',
        'gratuit'          => '1',
        'varsta_minima'    => 'nespecificat',
        'gen_participanti' => 'nespecificat',
        'fara_participanti_min' => '1',
        'fara_participanti_max' => '1',
        'descriere'        => $descriere,
    ], $peste), $fisiere));
}

function ultimulEveniment(): array
{
    $r = db()->query('SELECT * FROM evenimente ORDER BY id DESC LIMIT 1')->fetch();
    return $r ?: [];
}

function cateEvenimente(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM evenimente')->fetchColumn();
}

db()->exec('DELETE FROM evenimente');
// Și dosarul cu coperți: o rulare anterioară (sau o probă din browser) poate
// lăsa fișiere acolo, iar mai jos numărăm câte au rămas după o încărcare rea.
foreach (glob(dirname(__DIR__) . '/' . COPERTA_DOSAR . '/*.jpg') ?: [] as $f) { @unlink($f); }
$idOrg = faMembru(EMAIL_ORG, 'organizat02');

/* ======================================================================= */
echo "=== CINE POATE INTRA PE PAGINĂ ===\n";

$anonim = [];
$r = cerere($baza . '/adauga_eveniment.php', $anonim);
verifica('nelogat: e trimis la intrare', 302, $r['stare']);
verifica('nelogat: cu întoarcere la formular', true, str_contains($r['unde'], 'adauga_eveniment.php'));
verifica('nelogat: NU vede formularul', false, str_contains($r['corp'], 'id="eveniment-form"'));

$c = [];
intra($c, EMAIL_ORG);
$pagina = cerere($baza . '/adauga_eveniment.php', $c);
verifica('logat: pagina se deschide', 200, $pagina['stare']);
verifica('are formularul', true, str_contains($pagina['corp'], 'id="eveniment-form"'));
verifica('are token CSRF', true, str_contains($pagina['corp'], 'name="csrf"'));

/**
 * Orele sunt câmpuri de text, nu `type="time"`.
 *
 * Ceasul nativ se scrie cu AM/PM sau fără după limba în care e pus browserul,
 * nu după limba paginii — `lang` pe input n-are niciun efect. Ora o scriem
 * noi, deci e mereu de 24 de ore, iar `pattern` cere din browser exact ce cere
 * și serverul.
 */
verifica('ora de început nu e ceasul browserului', false,
    str_contains($pagina['corp'], 'type="time" id="ev-ora-inceput"'));
verifica('e câmp de text', true,
    str_contains($pagina['corp'], 'type="text" id="ev-ora-inceput"'));
verifica('cu tiparul de 24 de ore', 2,
    substr_count($pagina['corp'], 'pattern="([01][0-9]|2[0-3]):[0-5][0-9]"'));

/**
 * Orașul: listă, nu text liber, iar lista vine din inc/config.php prin
 * oraseDisponibile() — nu e scrisă în formular. Nimic nu e ales dinainte:
 * prima opțiune e goală și `disabled`, ca la categorie.
 */
preg_match('/<select id="ev-oras".*?<\/select>/s', $pagina['corp'], $listaOrase);
$selectOrase = $listaOrase[0] ?? '';

verifica('câmpul de oraș e o listă', true, $selectOrase !== '');
verifica('e obligatoriu', true, str_contains($selectOrase, 'required'));
verifica('are un rând pentru fiecare oraș din config, plus placeholderul',
    count(oraseDisponibile()) + 1, substr_count($selectOrase, '<option'));

foreach (oraseDisponibile() as $orasDinConfig) {
    verifica('„' . $orasDinConfig . '" e în listă', true,
        str_contains($selectOrase, 'value="' . h($orasDinConfig) . '"'));
}

verifica('placeholderul e gol și needitabil', true,
    preg_match('/<option value=""[^>]*disabled>Selectează orașul</', $selectOrase) === 1);
verifica('și e cel ales la un formular gol', true,
    preg_match('/<option value="" selected disabled>/', $selectOrase) === 1);
verifica('deci niciun oraș nu e bifat dinainte', 0,
    preg_match_all('/<option value="[^"]+"[^>]*selected/', $selectOrase));
verifica('câmpul de oraș stă deasupra celui de locație', true,
    strpos($pagina['corp'], 'id="ev-oras"') < strpos($pagina['corp'], 'id="ev-locatie"'));

/**
 * `maxlength` nu mai stă pe descriere: el numără în unități UTF-16, deci ar
 * tăia un text cu emoji la jumătatea limitei ținute de server. Limitele pleacă
 * spre JS ca date, iar oprirea o face el, numărând caractere.
 */
verifica('descrierea n-are maxlength', false,
    preg_match('/<textarea id="ev-descriere"[^>]*maxlength/', $pagina['corp']) === 1);
verifica('dar poartă limitele ca date', true,
    str_contains($pagina['corp'], 'data-min="' . DESCRIERE_MIN . '" data-max="' . DESCRIERE_MAX . '"'));

/**
 * Contorul spune „din minim 300", nu „din 300": 300 e pragul de la care se
 * poate trimite, nu o cotă de umplut, iar „633 din 300" arăta ca o greșeală.
 *
 * Textul e scris în două locuri — aici, de PHP, și în main.js, la fiecare
 * tastă. Proba asta prinde doar jumătatea dinspre PHP; dacă se schimbă una,
 * trebuie schimbată și cealaltă, altfel se preface la prima literă scrisă.
 */
verifica('contorul descrierii spune că e un minim',
    '0 din minim ' . DESCRIERE_MIN . ' caractere',
    preg_match('/id="ev-numar"[^>]*>([^<]*)</', $pagina['corp'], $mc) === 1 ? trim($mc[1]) : '');

// Panoul de după trimitere duce la eveniment, nu pe prima pagină.
verifica('panoul de gata trimite la eveniment', true,
    str_contains($pagina['corp'], 'id="ev-done-link"'));
verifica('și nu mai zice „prima pagină"', false,
    str_contains($pagina['corp'], 'Mergi pe prima pagină'));
/**
 * Câte opțiuni are lista de categorii — numărate chiar din ea, nu scăzând din
 * toate opțiunile paginii ce nu e categorie. Scăderea aia se strica de fiecare
 * dată când se atingea altă listă.
 */
preg_match('/<select id="ev-categorie".*?<\/select>/s', $pagina['corp'], $listaCat);
verifica('categoriile vin din bază (toate cinci)', 5,
    substr_count($listaCat[0] ?? '', '<option value="') - 1);   // minus „Alege…"

echo "\n=== LISTA DE ORAȘE, DIN CONFIG ===\n";

/**
 * oraseDisponibile() e singurul loc de unde își iau lista și formularul, și
 * verificarea de pe server. Aici i se dau, pe rând, felurile în care poate
 * arăta cheia „orase" din inc/config.php după ce a umblat cineva la ea.
 */
$configOriginal = $config['orase'] ?? null;

foreach ([
    ['un oraș',                ['Roman'],                        ['Roman']],
    ['mai multe',              ['Roman', 'Piatra-Neamț'],        ['Roman', 'Piatra-Neamț']],
    ['spațiile din jur cad',   ['  Roman  '],                    ['Roman']],
    ['rândurile goale cad',    ['Roman', '', '   '],             ['Roman']],
    ['duplicatele cad',        ['Roman', 'Roman'],               ['Roman']],
    ['ce nu e text cade',      ['Roman', 42, null, ['x']],       ['Roman']],
    ['lista goală rămâne goală', [],                             []],
    ['o valoare care nu e listă', 'Roman',                       []],
] as [$ce, $pus, $asteptat]) {
    $config['orase'] = $pus;
    verifica($ce, $asteptat, oraseDisponibile());
}

$config['orase'] = $configOriginal;
verifica('configul adevărat are cel puțin un oraș', true, oraseDisponibile() !== []);

echo "\n=== UN EVENIMENT BUN ===\n";

$r = trimite($c);
verifica('trece', true, $r['ok'] ?? false);
verifica('mesajul spune că merge la aprobare', true,
    str_contains($r['mesaj'] ?? '', 'spre aprobare'));

$ev = ultimulEveniment();
verifica('intră cu starea de moderare corectă', 'in_asteptare', $ev['stare_moderare']);
verifica('e legat de organizator', $idOrg, (int) $ev['membru_id']);
verifica('fără copertă → NULL', null, $ev['coperta']);
verifica('gratuit → NULL, nu 0.00', null, $ev['cost']);
verifica('fără oră de sfârșit → NULL', null, $ev['ora_sfarsit']);
verifica('participanți nespecificați → NULL', null, $ev['participanti_min']);
verifica('orașul ajunge în bază așa cum e în config', 'Roman', $ev['oras']);
verifica('are slug', true, preg_match('/^[a-z0-9-]+-[0-9a-f]{6}$/', (string) $ev['slug']) === 1);
verifica('slugul e făcut din titlu', true, str_starts_with((string) $ev['slug'], 'cursa-de-seara'));
verifica('răspunsul aduce adresa paginii lui',
    'event.php?slug=' . $ev['slug'], $r['url'] ?? '');

echo "\n=== UN SINGUR EVENIMENT ACTIV ===\n";

$r = trimite($c, ['titlu' => 'Al doilea eveniment, care nu trebuie să intre']);
verifica('al doilea e oprit', false, $r['ok'] ?? true);
verifica('cu mesajul cerut', 'Ai deja un eveniment activ. Poți posta unul nou după ce acesta se încheie.',
    $r['mesaj'] ?? '');
verifica('n-a ajuns în bază', 1, cateEvenimente());

$pagina = cerere($baza . '/adauga_eveniment.php', $c);
verifica('pagina arată de ce, nu formularul', false, str_contains($pagina['corp'], 'id="eveniment-form"'));
verifica('și îi spune care e evenimentul', true, str_contains($pagina['corp'], 'Cursa de seară'));

/**
 * Ieșirea de aici e „Înapoi", adică fix pe pagina de unde s-a apăsat
 * „+ Eveniment nou". Saltul îl face main.js; „href" rămâne prima pagină,
 * pentru cine ajunge aici fără nimic în urmă.
 */
verifica('are butonul „Înapoi"', true, str_contains($pagina['corp'], 'id="ev-inapoi"'));
verifica('nu mai trimite direct pe prima pagină', false,
    str_contains($pagina['corp'], 'Înapoi pe prima pagină'));

// Îl împingem în trecut: de mâine încolo, evenimentul de ieri e încheiat.
db()->prepare('UPDATE evenimente SET data_eveniment = ? WHERE membru_id = ?')
    ->execute([date('Y-m-d', strtotime('-1 day')), $idOrg]);

$r = trimite($c, ['titlu' => 'Acum se poate, cel vechi s-a încheiat ieri']);
verifica('după ce a trecut ziua, se poate din nou', true, $r['ok'] ?? false);
verifica('fără niciun cron care să schimbe ceva în bază', 2, cateEvenimente());

// Un eveniment de AZI e încă activ: se încheie abia mâine.
db()->exec('DELETE FROM evenimente');
db()->prepare('INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, data_eveniment,
                                       ora_inceput, locatie, descriere, creat_la, actualizat_la)
              VALUES (?,1,?,?,?,?,?,?,?,?)')
    ->execute([$idOrg, 'Azi', 'azi-' . bin2hex(random_bytes(3)), date('Y-m-d'),
               '10:00:00', 'Undeva', 'Ceva', acum(), acum()]);

$r = trimite($c, ['titlu' => 'Nu, cel de azi e încă activ']);
verifica('un eveniment de AZI e încă activ', false, $r['ok'] ?? true);

echo "\n=== LIMITA SE POATE RIDICA ===\n";

db()->prepare('UPDATE membri SET limita_evenimente_active = 3 WHERE id = ?')->execute([$idOrg]);
$r = trimite($c, ['titlu' => 'Cu limita ridicată, al doilea intră']);
verifica('cu limita pusă pe 3, al doilea trece', true, $r['ok'] ?? false);
$r = trimite($c, ['titlu' => 'Și al treilea intră']);
verifica('și al treilea', true, $r['ok'] ?? false);
$r = trimite($c, ['titlu' => 'Al patrulea, nu']);
verifica('al patrulea, nu', false, $r['ok'] ?? true);
verifica('mesajul e la plural', true, str_contains($r['mesaj'] ?? '', '3 evenimente active'));

db()->prepare('UPDATE membri SET limita_evenimente_active = NULL WHERE id = ?')->execute([$idOrg]);
db()->exec('DELETE FROM evenimente');

echo "\n=== CE TREBUIE RESPINS ===\n";

$scurt = str_repeat('a', 299);

foreach ([
    ['titlu gol',            ['titlu' => ''],                                'titlu'],
    ['titlu prea scurt',     ['titlu' => 'Ceva'],                            'titlu'],
    ['categorie inexistentă',['categorie_id' => '99'],                       'categorie_id'],
    ['categorie negativă',   ['categorie_id' => '-1'],                       'categorie_id'],
    ['locație goală',        ['locatie' => ''],                              'locatie'],
    ['oraș gol',             ['oras' => ''],                                 'oras'],
    ['oraș din afara listei', ['oras' => 'București'],                       'oras'],
    ['oraș cu literă mică',  ['oras' => 'roman'],                            'oras'],
    ['oraș cu spații în plus, dar altul', ['oras' => 'Roman Nou'],           'oras'],
    ['dată în trecut',       ['data_eveniment' => date('d-m-Y', strtotime('-1 day'))], 'data_eveniment'],
    ['dată imposibilă',      ['data_eveniment' => '30-02-2027'],             'data_eveniment'],
    ['29 februarie într-un an nebisect', ['data_eveniment' => '29-02-2027'], 'data_eveniment'],
    ['dată în formatul bazei',['data_eveniment' => date('Y-m-d', strtotime('+10 days'))], 'data_eveniment'],
    ['dată cu bare',         ['data_eveniment' => date('d/m/Y', strtotime('+10 days'))], 'data_eveniment'],
    ['dată fără zerouri',    ['data_eveniment' => '5-3-2027'],               'data_eveniment'],
    ['dată prea departe',    ['data_eveniment' => date('d-m-Y', strtotime('+5 years'))], 'data_eveniment'],
    ['oră de început lipsă', ['ora_inceput' => ''],                          'ora_inceput'],
    ['oră imposibilă',       ['ora_inceput' => '25:99'],                     'ora_inceput'],
    ['fără oră de sfârșit și fără bifă', ['fara_ora_sfarsit' => null, 'ora_sfarsit' => ''], 'ora_sfarsit'],
    ['cost fără sumă',       ['gratuit' => null, 'cost' => ''],              'cost'],
    ['cost cu litere',       ['gratuit' => null, 'cost' => 'mult'],          'cost'],
    ['vârstă inventată',     ['varsta_minima' => '21'],                      'varsta_minima'],
    ['gen inventat',         ['gen_participanti' => 'altceva'],              'gen_participanti'],
    ['descriere sub 300',    ['descriere' => $scurt],                        'descriere'],
    ['descriere goală',      ['descriere' => ''],                            'descriere'],
    ['participanți fără număr', ['fara_participanti_min' => null, 'participanti_min' => ''], 'participanti_min'],
    ['participanți cu litere',  ['fara_participanti_min' => null, 'participanti_min' => 'zece'], 'participanti_min'],
    ['participanți zero',       ['fara_participanti_min' => null, 'participanti_min' => '0'], 'participanti_min'],
] as [$ce, $peste, $camp]) {
    $r = trimite($c, $peste);
    verifica("respins: $ce", true, !empty($r['erori'][$camp]));
}

$r = trimite($c, [
    'fara_participanti_min' => null, 'participanti_min' => '50',
    'fara_participanti_max' => null, 'participanti_max' => '10',
]);
verifica('respins: minimul mai mare decât maximul', true, !empty($r['erori']['participanti_max']));

verifica('niciunul n-a ajuns în bază', 0, cateEvenimente());

echo "\n=== DESCRIEREA: CARACTERE, NU OCTEȚI ===\n";

// 300 de „ă" = 600 de octeți, dar exact 300 de caractere. Trebuie să treacă.
$r = trimite($c, ['descriere' => str_repeat('ă', 300)]);
verifica('300 de „ă" trec (600 de octeți)', true, $r['ok'] ?? false);
verifica('și se salvează întregi', 300,
    mb_strlen((string) ultimulEveniment()['descriere'], 'UTF-8'));

db()->exec('DELETE FROM evenimente');

// 299 de „ă" = 598 de octeți. Cu strlen() ar fi trecut. Nu trebuie.
$r = trimite($c, ['descriere' => str_repeat('ă', 299)]);
verifica('299 de „ă" NU trec, deși au 598 de octeți', true, !empty($r['erori']['descriere']));

echo "\n=== TEXTUL RĂMÂNE CUM L-A SCRIS OMUL ===\n";

$descriereCuParagrafe = "Primul paragraf, despre ce facem.\n\nAl doilea paragraf, cu detalii.\n\n"
    . str_repeat('Mai scriem ceva ca să trecem de trei sute de caractere. ', 6);

$r = trimite($c, ['descriere' => $descriereCuParagrafe, 'titlu' => 'Meci Dinamo & Rapid <b>tare</b>']);
verifica('trece', true, $r['ok'] ?? false);

$ev = ultimulEveniment();
verifica('paragrafele rămân', true, str_contains((string) $ev['descriere'], "\n\n"));
verifica('„&" rămâne „&", nu „&amp;"', true, str_contains((string) $ev['titlu'], 'Dinamo & Rapid'));
verifica('nu s-a scăpat nimic la salvare', false, str_contains((string) $ev['titlu'], '&amp;'));
verifica('dar eticheta e scăpată la AFIȘARE', true,
    str_contains(cerere($baza . '/adauga_eveniment.php', $c)['corp'], '&lt;b&gt;tare&lt;/b&gt;'));

db()->exec('DELETE FROM evenimente');

echo "\n=== COPERTA ===\n";

$r = trimite($c, ['titlu' => 'Cu o copertă prea mică'], [
    'coperta' => ['nume' => 'mica.jpg', 'tip' => 'image/jpeg', 'continut' => pozaDeProba(800, 450)],
]);
verifica('sub 1600×900: respinsă', true, !empty($r['erori']['coperta']));
verifica('cu mesaj care spune mărimea', true, str_contains($r['erori']['coperta'] ?? '', '800×450'));
verifica('evenimentul nu s-a salvat', 0, cateEvenimente());

$r = trimite($c, ['titlu' => 'Cu o copertă bună'], [
    'coperta' => ['nume' => 'mare.jpg', 'tip' => 'image/jpeg', 'continut' => pozaDeProba(2400, 1000)],
]);
verifica('2400×1000: primită', true, $r['ok'] ?? false);

$ev = ultimulEveniment();
verifica('numele copertei e 32 hex', true,
    preg_match('/^[0-9a-f]{32}$/', (string) $ev['coperta']) === 1);

$caleCoperta = dirname(__DIR__) . '/' . COPERTA_DOSAR . '/' . $ev['coperta'] . '.jpg';
verifica('fișierul e pe disc', true, is_file($caleCoperta));

$masuri = @getimagesize($caleCoperta);
verifica('e fix 1600 lățime', 1600, (int) ($masuri[0] ?? 0));
verifica('e fix 900 înălțime', 900, (int) ($masuri[1] ?? 0));
verifica('e JPEG, orice s-ar fi urcat', IMAGETYPE_JPEG, (int) ($masuri[2] ?? 0));

db()->exec('DELETE FROM evenimente');
@unlink($caleCoperta);

echo "\n=== CADRUL ALES DIN PAGINĂ ===\n";

/**
 * Poza e un degrade de la negru (stânga) la roșu (dreapta), lată de 3200.
 * Cerem partea din dreapta: din 1500 până în 3200.
 */
$panoramic = pozaInDegrade(3200, 1000);

$r = trimite($c, ['titlu' => 'Cu un cadru ales de organizator', 'x' => '1500', 'y' => '0', 'l' => '1700'], [
    'coperta' => ['nume' => 'lat.jpg', 'tip' => 'image/jpeg', 'continut' => $panoramic],
]);
verifica('cu decupaj: primită', true, $r['ok'] ?? false);

$ev = ultimulEveniment();
$cale = dirname(__DIR__) . '/' . COPERTA_DOSAR . '/' . $ev['coperta'] . '.jpg';
$masuri = @getimagesize($cale);
verifica('tot 1600×900 iese', [1600, 900], [(int) $masuri[0], (int) $masuri[1]]);

// Marginile poveștii: pixelul 2 din stânga vine cam de la 1500, cel din
// dreapta cam de la 3200. Lăsăm 60 de pixeli scăpare, cât ia comprimarea.
$stanga  = deUndeVine($cale, 2, 3200);
$dreapta = deUndeVine($cale, 1597, 3200);
verifica('taie de unde am cerut (stânga ≈ 1500)', true, abs($stanga - 1500) < 60);
verifica('și până unde am cerut (dreapta ≈ 3200)', true, abs($dreapta - 3200) < 60);

db()->exec('DELETE FROM evenimente');
@unlink($cale);

// Un decupaj mai îngust de 1600 ar însemna o poză întinsă. Se lărgește.
$r = trimite($c, ['titlu' => 'Cu un cadru prea strâns', 'x' => '0', 'y' => '0', 'l' => '400'], [
    'coperta' => ['nume' => 'lat.jpg', 'tip' => 'image/jpeg', 'continut' => $panoramic],
]);
$ev = ultimulEveniment();
$cale = dirname(__DIR__) . '/' . COPERTA_DOSAR . '/' . $ev['coperta'] . '.jpg';
verifica('decupaj de 400: se lărgește la 1600', true,
    abs(deUndeVine($cale, 1597, 3200) - 1600) < 60);

db()->exec('DELETE FROM evenimente');
@unlink($cale);

/**
 * Numere pe care nu le-a scris nimeni de bunăvoie.
 *
 * Niciunul n-are voie să dea eroare de server sau un fișier stricat: un
 * decupaj greșit nu e un atac, e de obicei o fereastră redimensionată.
 */
foreach ([
    'negativ'         => ['x' => '-9000', 'y' => '-9000', 'l' => '1700'],
    'mai mare ca poza'=> ['x' => '99999', 'y' => '99999', 'l' => '99999'],
    'litere'          => ['x' => 'abc',   'y' => 'x',     'l' => 'mult'],
    'tablou'          => ['x' => ['1'],   'y' => ['1'],   'l' => ['1']],
    'zero'            => ['x' => '0',     'y' => '0',     'l' => '0'],
    'virgulă'         => ['x' => '1,5',   'y' => '2,5',   'l' => '1700,5'],
] as $fel => $numere) {
    $r = trimite($c, array_merge(['titlu' => 'Cu numere stricate: ' . $fel], $numere), [
        'coperta' => ['nume' => 'lat.jpg', 'tip' => 'image/jpeg', 'continut' => $panoramic],
    ]);

    $ev = ultimulEveniment();
    $cale = dirname(__DIR__) . '/' . COPERTA_DOSAR . '/' . ($ev['coperta'] ?? '') . '.jpg';
    $masuri = is_file($cale) ? @getimagesize($cale) : null;

    verifica('decupaj ' . $fel . ': tot iese 1600×900',
        [1600, 900], [(int) ($masuri[0] ?? 0), (int) ($masuri[1] ?? 0)]);

    db()->exec('DELETE FROM evenimente');
    @unlink($cale);
}

// Un fișier care nu e poză, dar are nume de poză.
$r = trimite($c, ['titlu' => 'Cu un fișier care nu e poză'], [
    'coperta' => ['nume' => 'rau.jpg', 'tip' => 'image/jpeg',
                  'continut' => "<?php echo 'salut'; ?>" . str_repeat('x', 400)],
]);
verifica('fișier care nu e poză: respins', true, !empty($r['erori']['coperta']));
verifica('și nimic n-a ajuns pe disc', 0,
    count(glob(dirname(__DIR__) . '/' . COPERTA_DOSAR . '/*.jpg') ?: []));

echo "\n=== APĂRAREA PUNCTULUI DE INTRARE ===\n";

$r = cerere($baza . '/api/eveniment.php', $c, ['csrf' => 'gresit', 'titlu' => 'x']);
verifica('fără CSRF bun → 419', 419, $r['stare']);

$gol = [];
$r = cerere($baza . '/api/eveniment.php', $gol, ['titlu' => 'x']);
verifica('nelogat → 401 sau 419', true, in_array($r['stare'], [401, 419], true));

$r = cerere($baza . '/api/eveniment.php', $c);
verifica('prin GET → 405', 405, $r['stare']);

verifica('nimic în bază', 0, cateEvenimente());

echo "\n=== EVENIMENTELE DE PE PROFIL ===\n";

const EMAIL_ALTUL = 'alt-organizator-test@exemplu-test.ro';

$idAltul = faMembru(EMAIL_ALTUL, 'organizat03');

/** Un eveniment scris direct în bază, ca să putem alege starea și data. */
function pune(int $membruId, string $titlu, string $stare, int $peste): int
{
    db()->prepare(
        'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, data_eveniment,
                                 ora_inceput, locatie, descriere, gen_participanti,
                                 stare_moderare, creat_la, actualizat_la)
         VALUES (?,1,?,?,?,\'19:00\',?,?,\'nespecificat\',?,?,?)'
    )->execute([
        $membruId, $titlu, slugEveniment($titlu),
        date('Y-m-d', strtotime(($peste >= 0 ? '+' : '-') . abs($peste) . ' days')),
        'Piața Sfatului', str_repeat('Povestea evenimentului. ', 15),
        $stare, acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

db()->exec('DELETE FROM evenimente');

// Gazda: două în așteptare (una peste 20 de zile, una peste 3), patru aprobate
// viitoare, unul aprobat de ieri și unul respins.
pune($idOrg, 'Aștept de mult',        'in_asteptare', 20);
pune($idOrg, 'Aștept de puțin',       'in_asteptare', 3);
pune($idOrg, 'Aprobat, poimâine',     'aprobat',      2);
pune($idOrg, 'Aprobat, peste o săptămână', 'aprobat', 9);
pune($idOrg, 'Aprobat, peste două',   'aprobat',      14);
pune($idOrg, 'Aprobat, peste o lună', 'aprobat',      30);
pune($idOrg, 'Aprobat, dar de ieri',  'aprobat',      -1);
pune($idOrg, 'Respins',               'respins',      5);

/* --------- ce spune funcția, înainte de orice pagină --------- */

$aleMele = evenimenteDePeProfil($idOrg, true);
$aleLui  = evenimenteDePeProfil($idOrg, false);

$titluri = static fn(array $lista): array
    => array_map(static fn(array $e): string => (string) $e['titlu'], $lista);

verifica('vizitatorul vede doar aprobatele viitoare', [
    'Aprobat, poimâine', 'Aprobat, peste o săptămână',
    'Aprobat, peste două', 'Aprobat, peste o lună',
], $titluri($aleLui));

verifica('proprietarul le vede și pe cele în așteptare, primele', [
    'Aștept de puțin', 'Aștept de mult',
    'Aprobat, poimâine', 'Aprobat, peste o săptămână',
    'Aprobat, peste două', 'Aprobat, peste o lună',
], $titluri($aleMele));

verifica('cel de ieri nu apare nicăieri', false,
    in_array('Aprobat, dar de ieri', $titluri($aleMele), true));
verifica('nici cel respins', false, in_array('Respins', $titluri($aleMele), true));

verifica('aceeași regulă ca la limita de postare',
    count(evenimenteActive($idOrg)), count($aleMele));

verifica('evenimentele altcuiva nu se amestecă', [], $titluri(evenimenteDePeProfil($idAltul, true)));

/* --------------------- ce ajunge în pagină --------------------- */

$catePe = static fn(string $corp, string $ce): int => substr_count($corp, $ce);

/**
 * „Vizitatorul" e de acum tot un om conectat: profilurile nu se mai deschid
 * pentru cine n-are cont (vezi cereIntrare din profil.php). Ce se schimbă e
 * doar cine se uită — al lui sau al altcuiva.
 */
$strain = [];
intra($strain, EMAIL_ALTUL);

$r = cerere($baza . '/profil.php?m=organizat02', $anonim);
verifica('nelogatul nu deschide niciun profil', 302, $r['stare']);
verifica('ci e trimis la intrare', true, str_contains($r['unde'], 'login.php'));
verifica('cu întoarcere fix pe profilul cerut', true,
    str_contains($r['unde'], urlencode('/profil.php?m=organizat02')));
verifica('și nu se scurge nimic din pagină', false, str_contains($r['corp'], '<article class="card'));

$pagina = cerere($baza . '/profil.php?m=organizat02', $strain)['corp'];

verifica('un membru străin primește patru cartonașe', 4, $catePe($pagina, '<article class="card'));
verifica('și citește „Ce pune la cale"', true, str_contains($pagina, 'Ce pune la cale'));
verifica('nu „Ce pui la cale", că nu e al lui', false, str_contains($pagina, 'Ce pui la cale'));
verifica('și niciunul în așteptare', 0, $catePe($pagina, 'card--in-asteptare'));
verifica('titlul unui eveniment în așteptare nu se scurge', false,
    str_contains($pagina, 'Aștept de puțin'));
verifica('fără buton „Vezi mai mult", că nu e nimic ascuns', false,
    str_contains($pagina, 'Vezi mai mult'));

$pagina = cerere($baza . '/profil.php', $c)['corp'];

verifica('proprietarul primește toate șase', 6, $catePe($pagina, '<article class="card'));
verifica('lui i se vorbește la persoana a doua', true, str_contains($pagina, 'Ce pui la cale'));
verifica('două sunt însemnate ca fiind în așteptare', 2, $catePe($pagina, 'card--in-asteptare'));
verifica('cu eticheta scrisă pe ele', 2, $catePe($pagina, 'În așteptare de aprobare'));
verifica('două stau ascunse', 2, $catePe($pagina, 'card ascuns'));
verifica('deci apare și butonul', true, str_contains($pagina, 'Vezi mai mult'));
verifica('cu numărul celor rămase pe el', true, str_contains($pagina, 'Vezi mai mult… (2)'));

// Ordinea în HTML: cele în așteptare înaintea celorlalte.
verifica('în pagină, cele în așteptare vin primele', true,
    strpos($pagina, 'Aștept de puțin') < strpos($pagina, 'Aprobat, poimâine'));

/* ------------------------- când nu e nimic ------------------------- */

db()->exec('DELETE FROM evenimente');

$pagina = cerere($baza . '/profil.php', $c)['corp'];
verifica('pe profilul propriu, o invitație', true,
    str_contains($pagina, 'Nu organizezi nimic, nu vrei să încerci?'));
verifica('cu butonul care duce la formular', true,
    str_contains($pagina, 'href="adauga_eveniment.php"'));

$pagina = cerere($baza . '/profil.php?m=organizat02', $strain)['corp'];
verifica('pe profilul altuia, o constatare', true,
    str_contains($pagina, 'nu organizează momentan nimic'));
verifica('cu prenumele omului, nu cu numele întreg', true,
    str_contains($pagina, 'Ionuț nu organizează momentan nimic'));
verifica('și fără butonul de adăugare', false,
    str_contains($pagina, 'Nu organizezi nimic'));

/* --------------- adresa cerută, când nu duce nicăieri --------------- */

foreach ([
    'permalink inexistent' => 'nuexista01',
    'injecție SQL'         => "' OR 1=1 --",
    'prea scurt'           => 'ab',
    'cu semne'             => '<script>',
] as $ce => $adresa) {
    $r = cerere($baza . '/profil.php?m=' . urlencode($adresa), $anonim);
    verifica($ce . ' → trimis pe prima pagină', 302, $r['stare']);
}

echo "\n=== PAGINA UNUI EVENIMENT (event.php) ===\n";

db()->exec('DELETE FROM evenimente');

$idAprobat  = pune($idOrg,   'Cursa aprobată',       'aprobat',      7);
$idAsteapta = pune($idOrg,   'Cursa în așteptare',   'in_asteptare', 8);
$idRespins  = pune($idOrg,   'Cursa respinsă',       'respins',      9);
$idStrain   = pune($idAltul, 'Al altuia, în așteptare', 'in_asteptare', 10);

$slugul = static function (int $id): string {
    $q = db()->prepare('SELECT slug FROM evenimente WHERE id = ?');
    $q->execute([$id]);
    return (string) $q->fetchColumn();
};

$laEveniment = static fn(int $id): string => $baza . '/event.php?slug=' . urlencode($slugul($id));

/* ------------------ un anunț publicat se vede de oricine ---------------- */

/**
 * Pagina a fost o vreme închisă, ca profilurile. Un anunț public are însă altă
 * treabă: e făcut ca să fie dat mai departe. O ușă la intrare l-ar fi oprit
 * tocmai pe cel căruia i s-a trimis linkul, și l-ar fi ținut și în afara
 * căutărilor Google.
 */
$r = cerere($laEveniment($idAprobat), $anonim);
verifica('nelogatul deschide un eveniment aprobat', 200, $r['stare']);
verifica('și chiar îi vede titlul', true, str_contains($r['corp'], 'Cursa aprobată'));
verifica('fără să fie trimis nicăieri', '', $r['unde']);
verifica('iar pagina se lasă indexată', false, str_contains($r['corp'], 'name="robots"'));

// Butoanele se văd, dar nu sunt de apăsat fără cont: JS trimite la login, iar
// api/interes.php cere cont oricum. Tokenul CSRF nu se scrie degeaba.
verifica('vede și butoanele de participare', true, str_contains($r['corp'], 'id="btn-going"'));
verifica('dar fără token CSRF, că n-are ce face cu el', false,
    str_contains($r['corp'], 'data-csrf'));
verifica('și fără caseta de confirmare', false, str_contains($r['corp'], 'id="rsvp-confirm"'));

// Ce nu e publicat rămâne închis, și pentru nelogați, și pentru ceilalți. Un
// slug care nu există arată EXACT la fel: altfel s-ar putea afla, ghicind, ce
// evenimente așteaptă la moderare.
$rAsteptare  = cerere($laEveniment($idAsteapta), $anonim);
$rInexistent = cerere($baza . '/event.php?slug=nu-exista-abc123', $anonim);
verifica('nelogatul NU vede ce așteaptă moderarea', 302, $rAsteptare['stare']);
verifica('nici ce a fost respins', 302, cerere($laEveniment($idRespins), $anonim)['stare']);
verifica('slug inexistent: același răspuns', $rAsteptare['stare'], $rInexistent['stare']);
verifica('și aceeași destinație', $rAsteptare['unde'], $rInexistent['unde']);

/* ------------------------- cine ce poate vedea ------------------------ */

verifica('aprobatul se deschide pentru oricine e conectat', 200,
    cerere($laEveniment($idAprobat), $c)['stare']);

verifica('organizatorul își vede evenimentul în așteptare', 200,
    cerere($laEveniment($idAsteapta), $c)['stare']);

verifica('și pe cel respins', 200, cerere($laEveniment($idRespins), $c)['stare']);

// Alt membru conectat, care nu e organizatorul.
$altul = [];
intra($altul, EMAIL_ALTUL);

verifica('altcineva NU vede ce așteaptă moderarea', 302,
    cerere($laEveniment($idAsteapta), $altul)['stare']);
verifica('nici ce a fost respins', 302, cerere($laEveniment($idRespins), $altul)['stare']);
verifica('dar vede aprobatul', 200, cerere($laEveniment($idAprobat), $altul)['stare']);
verifica('organizatorul nu vede ce așteaptă la altul', 302,
    cerere($laEveniment($idStrain), $c)['stare']);

verifica('slug inexistent → prima pagină', 302,
    cerere($baza . '/event.php?slug=nu-exista-abc123', $c)['stare']);
verifica('fără slug → prima pagină', 302, cerere($baza . '/event.php', $c)['stare']);

foreach ([
    'injecție SQL' => "' OR 1=1 --",
    'cale de fișier' => '../../inc/config.php',
    'majuscule'      => 'CURSA-APROBATA-ABC',
    'semne'          => '<script>alert(1)</script>',
] as $ce => $slugRau) {
    verifica('slug ' . $ce . ' → prima pagină', 302,
        cerere($baza . '/event.php?slug=' . urlencode($slugRau), $c)['stare']);
}

/* --------------------------- ce se afișează --------------------------- */

$pagina = cerere($laEveniment($idAprobat), $c)['corp'];

verifica('titlul e în pagină', true, str_contains($pagina, 'Cursa aprobată'));
verifica('categoria la fel', true, str_contains($pagina, 'Sport'));
verifica('data scrisă întreagă', true,
    str_contains($pagina, dataLunga(date('Y-m-d', strtotime('+7 days')))));
// Fără oră de sfârșit se scrie doar ora de început, atât — nicio mențiune
// despre ce nu se știe.
verifica('ora de început, singură', true, str_contains($pagina, '<strong>19:00</strong>'));
verifica('fără vorbe despre sfârșitul care lipsește', false,
    str_contains($pagina, 'nedeterminat'));

/**
 * Butoanele de distribuire. Au fost cândva lipite de numele organizatorului,
 * de unde au și fost scoase; acum stau între detalii și „Mergi la acest
 * eveniment?" — după ce omul a citit despre ce e vorba.
 *
 * Adresa care pleacă spre Facebook și WhatsApp e ÎNTREAGĂ, cu url_site din
 * config: „event.php?slug=…" singur n-ar duce nicăieri de pe telefonul
 * altcuiva.
 */
$adresaIntreaga = rtrim((string) ($config['url_site'] ?? ''), '/')
                . '/event.php?slug=' . $slugul($idAprobat);

verifica('are zona de distribuire', true, str_contains($pagina, 'post__share'));
verifica('cu link de Facebook, pe adresa întreagă', true,
    str_contains($pagina, 'facebook.com/sharer/sharer.php?u=' . h(urlencode($adresaIntreaga))));
verifica('cu link de WhatsApp', true, str_contains($pagina, 'wa.me/?text='));
verifica('care poartă și titlul, nu doar adresa', true,
    str_contains($pagina, urlencode('Uite ce eveniment am găsit: Cursa aprobată')));
verifica('și cu butonul de copiat', true, str_contains($pagina, 'id="copiaza-link"'));
verifica('care are textul gata scris, escapat', true,
    str_contains($pagina, 'data-copiaza="Uite ce eveniment am găsit: Cursa aprobată '
                        . h($adresaIntreaga) . '"'));
verifica('cele două linkuri se deschid în altă filă', 2,
    preg_match_all('/class="icon-btn"[^>]*target="_blank"[^>]*rel="noopener noreferrer"/', $pagina));
verifica('zona stă înaintea butoanelor de participare', true,
    strpos($pagina, 'post__share') < strpos($pagina, 'id="rsvp"'));
verifica('și după caseta cu detalii', true,
    strpos($pagina, 'event-box') < strpos($pagina, 'post__share'));
verifica('locația', true, str_contains($pagina, 'Piața Sfatului'));
verifica('costul lipsă înseamnă gratuit', true, str_contains($pagina, 'Gratuit'));

/**
 * Orașul se vede pentru toată lumea, înaintea locului, în același rând:
 * „Roman · Piața Sfatului". Un rând al lui ar fi repetat același cuvânt la
 * fiecare eveniment, cât timp orașul e unul singur.
 */
verifica('orașul se vede, înaintea locului', true,
    str_contains($pagina, '<strong>Roman · Piața Sfatului</strong>'));

// Un eveniment de dinaintea coloanei (oraș gol) nu lasă un punct rătăcit.
db()->prepare('UPDATE evenimente SET oras = ? WHERE id = ?')->execute(['', $idAprobat]);
verifica('fără oraș, se scrie doar locul', true,
    str_contains(cerere($laEveniment($idAprobat), $c)['corp'], '<strong>Piața Sfatului</strong>'));
db()->prepare('UPDATE evenimente SET oras = ? WHERE id = ?')->execute(['Roman', $idAprobat]);
verifica('organizatorul, cu link spre profilul lui', true,
    str_contains($pagina, 'profil.php?m=organizat02'));

/**
 * Câmpurile nespecificate nu lasă rânduri goale.
 *
 * Se caută eticheta așa cum e tipărită, „<span>Vârstă minimă</span>", nu
 * doar cuvintele: altfel un comentariu din sursă ar trece drept rând afișat —
 * cum s-a și întâmplat prima dată când am scris verificarea asta.
 */
verifica('patru rânduri de detalii, atât', 4, substr_count($pagina, 'event-box__item'));
verifica('fără rând de vârstă minimă', false, str_contains($pagina, '<span>Vârstă minimă</span>'));
verifica('fără rând de participanți', false, str_contains($pagina, '<span>Participanți</span>'));
verifica('fără rând „Pentru cine"', false, str_contains($pagina, '<span>Pentru cine</span>'));

// …iar când sunt completate, se văd toate.
db()->prepare(
    'UPDATE evenimente SET ora_sfarsit = ?, cost = ?, varsta_minima = ?,
            participanti_min = ?, participanti_max = ?, gen_participanti = ?
      WHERE id = ?'
)->execute(['22:30:00', '45.50', 18, 10, 50, 'femei', $idAprobat]);

$paginaPlina = cerere($laEveniment($idAprobat), $c)['corp'];

verifica('cu toate completate: șapte rânduri', 7, substr_count($paginaPlina, 'event-box__item'));
verifica('intervalul orar întreg', true, str_contains($paginaPlina, '19:00 — 22:30'));
verifica('costul scris pe românește', true, str_contains($paginaPlina, '45,50 lei'));
verifica('vârsta minimă', true, str_contains($paginaPlina, '18 ani'));
verifica('genul participanților', true, str_contains($paginaPlina, 'Doar femei'));
verifica('participanții, într-un singur rând', true,
    str_contains($paginaPlina, 'minimum 10, cel mult 50'));

verifica('organizatorul primește butonul de editare', true,
    str_contains($pagina, 'post__editeaza'));
verifica('și duce la formular, cu slugul lui', true,
    str_contains($pagina, 'href="adauga_eveniment.php?slug=' . $slugul($idAprobat) . '"'));

$paginaAltul = cerere($laEveniment($idAprobat), $altul)['corp'];
verifica('altcineva nu primește butonul de editare', false,
    str_contains($paginaAltul, 'post__editeaza'));

$paginaAsteapta = cerere($laEveniment($idAsteapta), $c)['corp'];
verifica('starea se scrie pe pagină', true,
    str_contains($paginaAsteapta, 'În așteptare de aprobare'));
verifica('și nu se lasă indexată', true, str_contains($paginaAsteapta, 'name="robots"'));

$paginaRespins = cerere($laEveniment($idRespins), $c)['corp'];
verifica('respinsul își spune starea altfel', true,
    str_contains($paginaRespins, 'nu a trecut de verificare'));

/* ------------------- textul rămâne text, nu cod ------------------- */

db()->prepare('UPDATE evenimente SET titlu = ?, descriere = ? WHERE id = ?')->execute([
    'Titlu cu <script>alert(1)</script> & semne',
    "Primul paragraf, cu <b>etichete</b> & \"ghilimele\".\n\nAl doilea paragraf.\n"
        . "Un rând nou.\n\n" . str_repeat('Umplem până la trei sute de caractere. ', 8),
    $idAprobat,
]);

$pagina = cerere($laEveniment($idAprobat), $c)['corp'];

verifica('eticheta din titlu e scăpată', true, str_contains($pagina, '&lt;script&gt;'));
verifica('și nu ajunge cod în pagină', false, str_contains($pagina, '<script>alert(1)</script>'));
verifica('„&" rămâne text, nu entitate ruptă', true, str_contains($pagina, '&amp; semne'));
verifica('paragrafele devin <p>', true, substr_count($pagina, '<p>Primul paragraf') === 1);
verifica('sunt trei paragrafe', 3, substr_count($pagina, '<p>Al doilea') + substr_count($pagina, '<p>Primul') + substr_count($pagina, '<p>Umplem'));
verifica('rândul simplu devine <br>', true, str_contains($pagina, 'Al doilea paragraf.<br'));

/* ------------- cifra de pe cartonașul „Evenimente organizate" ------------ */

echo "\n=== SCHIMBAREA UNUI EVENIMENT ===\n";

db()->exec('DELETE FROM evenimente');

$idDeSchimbat = pune($idOrg, 'Cum era la început', 'aprobat', 12);
$slugDeSchimbat = $slugul($idDeSchimbat);

// Îi punem tot ce se poate completa, ca să vedem că se întoarce în formular.
db()->prepare(
    'UPDATE evenimente SET ora_sfarsit = ?, cost = ?, varsta_minima = ?,
            participanti_min = ?, participanti_max = ?, gen_participanti = ?, coperta = ?
      WHERE id = ?'
)->execute(['21:30:00', '45.50', 16, 8, 40, 'barbati', str_repeat('ab', 16), $idDeSchimbat]);

$laFormular = static fn(string $slug): string
    => $baza . '/adauga_eveniment.php?slug=' . urlencode($slug);

/* ---------------------- cine ajunge la formular ---------------------- */

verifica('organizatorul își poate deschide evenimentul', 200,
    cerere($laFormular($slugDeSchimbat), $c)['stare']);

verifica('altcineva nu: e trimis pe prima pagină', 302,
    cerere($laFormular($slugDeSchimbat), $altul)['stare']);

verifica('un slug inexistent, la fel', 302,
    cerere($laFormular('nu-exista-abc123'), $c)['stare']);

$r = cerere($laFormular($slugDeSchimbat), $anonim);
verifica('nelogatul e trimis la login', 302, $r['stare']);
verifica('și adus înapoi la formularul lui', true,
    str_contains($r['unde'], urlencode('/adauga_eveniment.php?slug=' . $slugDeSchimbat)));

/* ------------------- ce scrie în câmpuri la deschidere ------------------- */

$formular = cerere($laFormular($slugDeSchimbat), $c)['corp'];

/** Valoarea unui câmp din HTML — atributele pot fi pe mai multe rânduri. */
$valoarea = static function (string $id, string $html): ?string {
    if (preg_match('/id="' . preg_quote($id, '/') . '"(.*?)>/s', $html, $m) !== 1) {
        return null;
    }
    return preg_match('/value="([^"]*)"/', $m[1], $v) === 1 ? $v[1] : null;
};

$bifat = static function (string $id, string $html): bool {
    return preg_match('/id="' . preg_quote($id, '/') . '"(.*?)>/s', $html, $m) === 1
        && str_contains($m[1], 'checked');
};

verifica('titlul e precompletat', 'Cum era la început', $valoarea('ev-titlu', $formular));
verifica('locația', 'Piața Sfatului', $valoarea('ev-locatie', $formular));

// La editare, orașul salvat e cel bifat — și numai el.
verifica('orașul salvat e ales', true,
    preg_match('/<option value="Roman"\s*selected/', $formular) === 1);
verifica('placeholderul nu mai e ales', false,
    preg_match('/<option value="" selected/', $formular) === 1);
// În formular data se scrie pe românește; în bază stă în formatul ei.
verifica('data, în format românesc', date('d-m-Y', strtotime('+12 days')),
    $valoarea('ev-data', $formular));
verifica('ora de început, fără secunde', '19:00', $valoarea('ev-ora-inceput', $formular));
verifica('ora de sfârșit', '21:30', $valoarea('ev-ora-sfarsit', $formular));
verifica('costul, fără zerouri de prisos', '45.5', $valoarea('ev-cost', $formular));
verifica('participanți minim', '8', $valoarea('ev-min', $formular));
verifica('participanți maxim', '40', $valoarea('ev-max', $formular));
verifica('descrierea e în textarea', true,
    str_contains($formular, 'Povestea evenimentului.'));

verifica('categoria e aleasă', true, str_contains($formular, 'value="1"' . "\n" . '                      selected'));
verifica('vârsta minimă e aleasă', true, preg_match('/value="16"\s*\n?\s*selected/', $formular) === 1);
verifica('genul e ales', true, preg_match('/value="barbati"\s*\n?\s*selected/', $formular) === 1);

verifica('bifa „gratuit" e scoasă, că are preț', false, $bifat('ev-gratuit', $formular));
verifica('bifa „nu se știe până când" e scoasă', false, $bifat('ev-fara-sfarsit', $formular));
verifica('bifele „nespecificat" sunt scoase', [false, false],
    [$bifat('ev-fara-min', $formular), $bifat('ev-fara-max', $formular)]);

verifica('slugul pleacă ascuns în formular', true,
    str_contains($formular, 'name="slug" value="' . $slugDeSchimbat . '"'));
verifica('poza de acum se arată', true, str_contains($formular, 'ev-coperta-acum'));
verifica('și apare butonul de anulare', true, str_contains($formular, 'ev-anuleaza'));

/* ------------- un eveniment gol: bifele se întorc la locul lor ------------- */

$idGol = pune($idOrg, 'Fără nimic în plus', 'aprobat', 13);
$golul = cerere($laFormular($slugul($idGol)), $c)['corp'];

verifica('fără preț → „gratuit" bifat', true, $bifat('ev-gratuit', $golul));
verifica('fără oră de sfârșit → bifa pusă', true, $bifat('ev-fara-sfarsit', $golul));
verifica('fără participanți → bifele puse', [true, true],
    [$bifat('ev-fara-min', $golul), $bifat('ev-fara-max', $golul)]);
verifica('fără copertă → niciun bloc de poză', false, str_contains($golul, 'ev-coperta-acum'));

// „0.00" e altceva decât NULL în bază, dar la afișare amândouă zic „Gratuit".
// Formularul trebuie să spună la fel, altfel omul vede o contradicție.
db()->prepare('UPDATE evenimente SET cost = ? WHERE id = ?')->execute(['0.00', $idGol]);
$golul = cerere($laFormular($slugul($idGol)), $c)['corp'];
verifica('costul zero e tot „gratuit"', true, $bifat('ev-gratuit', $golul));

/* -------------------- formularul de eveniment nou -------------------- */

db()->exec('DELETE FROM evenimente');
$formularNou = cerere($baza . '/adauga_eveniment.php', $c)['corp'];

verifica('la unul nou nu apare butonul de anulare', false, str_contains($formularNou, 'ev-anuleaza'));
verifica('nici slugul ascuns', false, str_contains($formularNou, 'name="slug"'));
verifica('titlul e gol', '', $valoarea('ev-titlu', $formularNou));
verifica('„gratuit" bifat, ca înainte', true, $bifat('ev-gratuit', $formularNou));
// Ora de început se știe mereu, cea de sfârșit aproape niciodată: bifa
// pornește pusă, ca s-o scoată doar cine chiar are ce scrie acolo.
verifica('„nu se știe până când" bifat din start', true, $bifat('ev-fara-sfarsit', $formularNou));
verifica('„nespecificat" bifat, ca înainte', true, $bifat('ev-fara-min', $formularNou));

/* --------------------------- salvarea --------------------------- */

$idDeSchimbat = pune($idOrg, 'Titlul dinainte', 'aprobat', 12);
$slugDeSchimbat = $slugul($idDeSchimbat);

db()->prepare('UPDATE evenimente SET coperta = ? WHERE id = ?')
    ->execute([str_repeat('cd', 16), $idDeSchimbat]);

$r = trimite($c, [
    'slug'         => $slugDeSchimbat,
    'titlu'        => 'Titlul de după schimbare',
    'categorie_id' => '3',
    'locatie'      => 'Cu totul altundeva, în alt cartier',
]);
verifica('schimbarea trece', true, $r['ok'] ?? false);

$dupa = db()->prepare('SELECT * FROM evenimente WHERE id = ?');
$dupa->execute([$idDeSchimbat]);
$dupa = $dupa->fetch();

verifica('titlul s-a schimbat', 'Titlul de după schimbare', $dupa['titlu']);
verifica('și categoria', 3, (int) $dupa['categorie_id']);
verifica('orașul se scrie și la editare', 'Roman', $dupa['oras']);

/**
 * Slugul NU se schimbă odată cu titlul: adresa poate fi deja dată mai
 * departe, iar un link stricat supără mai tare decât un slug care nu mai
 * seamănă cu titlul.
 */
verifica('slugul rămâne cel dinainte', $slugDeSchimbat, $dupa['slug']);

/**
 * Răspunsul poartă adresa evenimentului, ca panoul de „gata" să aibă unde
 * trimite omul. La un anunț nou e singura cale: slugul se naște la salvare,
 * deci formularul n-avea de unde să-l știe când s-a tipărit.
 */
verifica('răspunsul spune și unde e evenimentul',
    'event.php?slug=' . $slugDeSchimbat, $r['url'] ?? '');

/**
 * Orice schimbare trece din nou pe la moderare. Altfel s-ar putea publica
 * orice: trimiți un anunț cumsecade, îl aprobăm, iar a doua zi îi schimbi tot
 * conținutul fără să mai treacă pe la nimeni.
 */
verifica('din „aprobat" se întoarce la „în așteptare"', 'in_asteptare', $dupa['stare_moderare']);

// Fără fișier nou, poza rămâne. Un formular gol nu înseamnă „șterge poza".
verifica('coperta n-a fost atinsă', str_repeat('cd', 16), $dupa['coperta']);

verifica('nu s-a făcut un eveniment în plus', 1, cateEvenimente());

// Și un anunț respins se poate corecta — e chiar cel care are nevoie.
db()->prepare('UPDATE evenimente SET stare_moderare = ? WHERE id = ?')->execute(['respins', $idDeSchimbat]);
$r = trimite($c, ['slug' => $slugDeSchimbat, 'titlu' => 'Corectat după refuz']);
verifica('și cel respins se poate corecta', true, $r['ok'] ?? false);

$dupa = db()->prepare('SELECT stare_moderare FROM evenimente WHERE id = ?');
$dupa->execute([$idDeSchimbat]);
verifica('tot la „în așteptare" ajunge', 'in_asteptare', $dupa->fetchColumn());

/**
 * Limita de evenimente active nu se aplică la schimbare.
 *
 * Altfel omul cu un singur eveniment activ ar fi oprit tocmai de el, deci
 * n-ar mai putea corecta niciodată nimic. Aici are deja unul activ: dovada e
 * că un eveniment NOU e refuzat, iar schimbarea lui trece.
 */
verifica('un eveniment nou e refuzat, are deja unul', false, trimite($c)['ok'] ?? false);
verifica('dar schimbarea celui existent merge', true,
    trimite($c, ['slug' => $slugDeSchimbat, 'titlu' => 'Schimbat cu limita atinsă'])['ok'] ?? false);

/* ---------------- cine NU are voie să schimbe ---------------- */

$r = trimite($altul, ['slug' => $slugDeSchimbat, 'titlu' => 'Nu e al meu, dar încerc']);
verifica('altcineva nu poate schimba evenimentul meu', false, $r['ok'] ?? false);

$q = db()->prepare('SELECT titlu FROM evenimente WHERE id = ?');
$q->execute([$idDeSchimbat]);
verifica('titlul a rămas neatins', 'Schimbat cu limita atinsă', $q->fetchColumn());

$r = trimite($c, ['slug' => 'nu-exista-nicaieri', 'titlu' => 'Pe ce anume?']);
verifica('un slug inexistent nu creează nimic', false, $r['ok'] ?? false);
verifica('și nu apare vreun eveniment nou', 1, cateEvenimente());

/**
 * Verificările sunt aceleași ca la publicare. La schimbare nu se cere mai
 * puțin — altfel s-ar putea publica un anunț bun și „edita" până rămâne gol.
 */
$r = trimite($c, ['slug' => $slugDeSchimbat, 'descriere' => 'prea scurt']);
verifica('descrierea scurtă e refuzată și la schimbare', true, !empty($r['erori']['descriere']));

$r = trimite($c, ['slug' => $slugDeSchimbat, 'titlu' => 'ab']);
verifica('titlul scurt, la fel', true, !empty($r['erori']['titlu']));

echo "\n=== ANULAREA UNUI EVENIMENT ===\n";

db()->exec('DELETE FROM evenimente');

/** Cere anularea evenimentului cu slugul dat. */
function anuleaza(array &$cookies, string $slug, ?string $token = null,
                  ?string $motiv = null): array
{
    return json_din(cerere($GLOBALS['baza'] . '/api/anuleaza-eveniment.php', $cookies, [
        'csrf'  => $token ?? csrf($cookies),
        'slug'  => $slug,
        'motiv' => $motiv ?? 'S-a stricat vremea și nu avem unde ne adăposti.',
    ]));
}

/** Starea de moderare și motivul, direct din bază. */
function stareaSiMotivul(int $id): array
{
    $q = db()->prepare('SELECT stare_moderare, motiv_anulare FROM evenimente WHERE id = ?');
    $q->execute([$id]);

    return $q->fetch() ?: [];
}

$idDeAnulat = pune($idOrg, 'Unul care se anulează', 'aprobat', 8);
$slugDeAnulat = $slugul($idDeAnulat);

// Îi punem și o copertă pe disc, ca să vedem că nu rămâne orfană.
$numeCoperta = bin2hex(random_bytes(16));
$caleCoperta = dirname(__DIR__) . '/' . COPERTA_DOSAR . '/' . $numeCoperta . '.jpg';
file_put_contents($caleCoperta, pozaDeProba(1600, 900));
db()->prepare('UPDATE evenimente SET coperta = ? WHERE id = ?')->execute([$numeCoperta, $idDeAnulat]);

/* ----------------------- cine NU are voie ----------------------- */

$r = anuleaza($altul, $slugDeAnulat);
verifica('altcineva nu poate anula evenimentul meu', false, $r['ok'] ?? false);
verifica('și rândul e tot acolo', 1, cateEvenimente());

$r = anuleaza($c, 'nu-exista-nicaieri');
verifica('un slug inexistent nu șterge nimic', false, $r['ok'] ?? false);
verifica('tot un rând', 1, cateEvenimente());

$r = anuleaza($c, $slugDeAnulat, 'token-gresit');
verifica('fără CSRF bun nu se anulează', false, $r['ok'] ?? false);
verifica('și rândul rezistă', 1, cateEvenimente());

$gol = [];
$r = anuleaza($gol, $slugDeAnulat, 'orice');
verifica('nelogatul nu poate anula', false, $r['ok'] ?? false);

verifica('prin GET → 405', 405,
    cerere($baza . '/api/anuleaza-eveniment.php', $c)['stare']);
verifica('după toate încercările, evenimentul e neatins', 1, cateEvenimente());
verifica('și coperta lui e pe disc', true, is_file($caleCoperta));

/* --------------------- motivul e obligatoriu -------------------- */

/**
 * Motivul nu e o formalitate: el pleacă prin e-mail spre oamenii care voiau să
 * vină. Un câmp gol, sau un „ok" scris în grabă, n-ar spune nimic nimănui.
 */
foreach ([
    'gol'          => '',
    'doar spații'  => '   ',
    'prea scurt'   => 'ploua',
    'rânduri goale'=> "\n\n\n",
] as $ce => $motiv) {
    $r = anuleaza($c, $slugDeAnulat, null, $motiv);
    verifica('motiv ' . $ce . ' → refuzat', true, !empty($r['erori']['motiv']));
}

verifica('și evenimentul e neatins', 'aprobat', stareaSiMotivul($idDeAnulat)['stare_moderare']);

$r = anuleaza($c, $slugDeAnulat, null, str_repeat('a', MOTIV_ANULARE_MAX + 1));
verifica('motiv prea lung → refuzat', true, !empty($r['erori']['motiv']));

/* ------------------------ organizatorul ------------------------ */

$r = anuleaza($c, $slugDeAnulat, null, "S-a stricat vremea.\n\nNe vedem la primăvară.");
verifica('organizatorul îl poate anula', true, $r['ok'] ?? false);
verifica('și e trimis pe profilul lui', 'profil.php', $r['redirect'] ?? '');
verifica('cu mesajul cerut', 'Evenimentul a fost anulat.', $r['mesaj'] ?? '');

/**
 * NU se mai șterge nimic. Rândul rămâne, cu o stare nouă și cu motivul lângă
 * el: de un eveniment atârnă oameni care și-au făcut planuri, iar un rând
 * șters n-ar mai putea spune nimănui de ce nu mai are unde să se ducă.
 */
$dupaAnulare = stareaSiMotivul($idDeAnulat);
verifica('rândul rămâne în bază', 1, cateEvenimente());
verifica('cu starea „anulat"', 'anulat', $dupaAnulare['stare_moderare']);
verifica('și cu motivul scris de organizator',
    "S-a stricat vremea.\n\nNe vedem la primăvară.", $dupaAnulare['motiv_anulare']);

// Coperta rămâne cât timp rămâne rândul: face parte din el, iar staff-ul mai
// deschide pagina. Se duce odată cu el, la curățenia de mai târziu.
clearstatcache();
verifica('coperta rămâne pe disc, lângă rând', true, is_file($caleCoperta));

/* ------------------ cine mai vede un eveniment anulat ------------------ */

verifica('organizatorul nu mai deschide pagina', 302,
    cerere($baza . '/event.php?slug=' . urlencode($slugDeAnulat), $c)['stare']);
verifica('nici un alt membru', 302,
    cerere($baza . '/event.php?slug=' . urlencode($slugDeAnulat), $altul)['stare']);
verifica('nici formularul lui de editare', 302,
    cerere($baza . '/adauga_eveniment.php?slug=' . urlencode($slugDeAnulat), $c)['stare']);

// Staff: singurii care mai au ce căuta acolo.
db()->prepare('UPDATE membri SET este_staff = 1 WHERE email = ?')->execute([EMAIL_ALTUL]);
$rStaff = cerere($baza . '/event.php?slug=' . urlencode($slugDeAnulat), $altul);
verifica('staff-ul deschide pagina', 200, $rStaff['stare']);
verifica('cu banda de anulat', true, str_contains($rStaff['corp'], 'stare-anunt--anulat'));
verifica('și cu motivul scris pe ea', true,
    str_contains($rStaff['corp'], 'Ne vedem la primăvară'));
verifica('rândurile motivului se păstrează', true,
    str_contains($rStaff['corp'], '<br'));
verifica('fără butonul de editare', false, str_contains($rStaff['corp'], 'post__editeaza'));
db()->prepare('UPDATE membri SET este_staff = 0 WHERE email = ?')->execute([EMAIL_ALTUL]);

verifica('iar fără steagul de staff, aceeași pagină se închide', 302,
    cerere($baza . '/event.php?slug=' . urlencode($slugDeAnulat), $altul)['stare']);

/* ------------- anulatul nu mai apare pe nicăieri ------------- */

verifica('nu mai e pe profilul organizatorului', [], evenimenteDePeProfil($idOrg, true));
verifica('nici pentru ceilalți', [], evenimenteDePeProfil($idOrg, false));
verifica('nu se numără la „evenimente organizate"', 0, cateEvenimenteOrganizate($idOrg));
verifica('și nu mai ține pe nimeni blocat', [], evenimenteActive($idOrg));

$profilCorp = cerere($baza . '/profil.php', $c)['corp'];
verifica('nu apare niciun cartonaș pe profil', 0, substr_count($profilCorp, '<article class="card'));

// Mesajul e pus în sesiune și se arată o singură dată, pe pagina următoare.
verifica('mesajul apare pe profil', true,
    str_contains($profilCorp, 'data-mesaj="Evenimentul a fost anulat."'));
verifica('și nu se mai repetă la a doua încărcare', false,
    str_contains(cerere($baza . '/profil.php', $c)['corp'], 'Evenimentul a fost anulat.'));

// Anularea nu e o cale de a scăpa de limită mai repede decât de drept, dar
// nici nu te ține blocat cu un eveniment care nu mai are loc.
verifica('după anulare se poate posta din nou', true, trimite($c)['ok'] ?? false);
verifica('și sunt două rânduri: cel anulat și cel nou', 2, cateEvenimente());

// A doua anulare a aceluiași eveniment n-are ce mai anula.
$r = anuleaza($c, $slugDeAnulat);
verifica('un eveniment anulat nu se mai poate anula o dată', false, $r['ok'] ?? false);

/* ------------- butonul apare doar unde trebuie ------------- */

$idAlt = pune($idOrg, 'Altul, tot al meu', 'aprobat', 9);
$formular = cerere($baza . '/adauga_eveniment.php?slug=' . urlencode($slugul($idAlt)), $c)['corp'];
verifica('la editare apare butonul de anulare', true, str_contains($formular, 'ev-anuleaza'));
verifica('și întrebarea de confirmare, ascunsă', true, str_contains($formular, 'ev-anulare-sigur'));
verifica('și caseta pentru motiv', true, str_contains($formular, 'id="ev-motiv"'));
verifica('cu contorul care spune că e un minim',
    '0 din minim ' . MOTIV_ANULARE_MIN . ' caractere',
    preg_match('/id="ev-motiv-numar"[^>]*>([^<]*)</', $formular, $mm) === 1 ? trim($mm[1]) : '');
verifica('care nu pleacă odată cu formularul', false,
    preg_match('/<textarea id="ev-motiv"[^>]*name=/', $formular) === 1);
verifica('și nici nu-l blochează cât e goală', false,
    preg_match('/<textarea id="ev-motiv"[^>]*required/', $formular) === 1);
verifica('spune că anunțul nu mai poate fi adus înapoi', true,
    str_contains($formular, 'nu mai poate fi adus înapoi'));
verifica('și că oamenii sunt înștiințați', true, str_contains($formular, 'înștiințați prin e-mail'));

db()->exec('DELETE FROM evenimente');
$formularNou = cerere($baza . '/adauga_eveniment.php', $c)['corp'];
verifica('la eveniment nou nu apare nimic din toate astea', false,
    str_contains($formularNou, 'ev-anuleaza'));

echo "\n=== PREVIZUALIZAREA ===\n";

db()->exec('DELETE FROM evenimente');

/** Trimite datele spre previzualizare. Aceleași câmpuri ca la trimite(). */
function previzualizeaza(array &$cookies, array $peste = []): array
{
    $descriere = str_repeat('Pornim din fața primăriei și mergem agale prin centrul vechi. ', 8);

    return json_din(cerere($GLOBALS['baza'] . '/api/previzualizare.php', $cookies, array_merge([
        'csrf'             => csrf($cookies),
        'titlu'            => 'Cursa de seară prin centrul vechi',
        'categorie_id'     => '1',
        'oras'             => 'Roman',
        'locatie'          => 'Piața Sfatului, lângă fântână',
        // Formularul trimite ZZ-LL-AAAA, cum se scrie o dată în România;
        // traducerea spre AAAA-LL-ZZ o face dataDinFormular() pe server.
        'data_eveniment'   => date('d-m-Y', strtotime('+10 days')),
        'ora_inceput'      => '19:00',
        'fara_ora_sfarsit' => '1',
        'gratuit'          => '1',
        'varsta_minima'    => 'nespecificat',
        'gen_participanti' => 'nespecificat',
        'fara_participanti_min' => '1',
        'fara_participanti_max' => '1',
        'descriere'        => $descriere,
    ], $peste)));
}

/* -------------------- aceleași verificări ca la salvare ------------------- */

/**
 * Nu o copie „mai îngăduitoare": dacă previzualizarea ar trece peste ceva ce
 * salvarea refuză, omul ar vedea o pagină frumoasă și apoi un teanc de erori.
 */
$rPreviz = previzualizeaza($c, ['descriere' => 'prea scurt']);
$rSalvat = trimite($c, ['descriere' => 'prea scurt']);
verifica('descrierea scurtă e refuzată și la previzualizare', true,
    !empty($rPreviz['erori']['descriere']));
verifica('cu exact același mesaj ca la trimitere',
    $rSalvat['erori']['descriere'] ?? 'x', $rPreviz['erori']['descriere'] ?? 'y');

$rPreviz = previzualizeaza($c, ['titlu' => 'ab', 'locatie' => '']);
verifica('titlul scurt, la fel', true, !empty($rPreviz['erori']['titlu']));
verifica('și locația lipsă', true, !empty($rPreviz['erori']['locatie']));

// Orașul trece prin aceeași verificare: previzualizarea nu e o portiță.
$rPreviz = previzualizeaza($c, ['oras' => 'București']);
verifica('un oraș din afara listei e oprit și la previzualizare', true,
    !empty($rPreviz['erori']['oras']));
verifica('cu același mesaj ca la trimitere',
    trimite($c, ['oras' => 'București'])['erori']['oras'] ?? 'x',
    $rPreviz['erori']['oras'] ?? 'y');
$rPreviz = previzualizeaza($c, ['oras' => '']);
verifica('și unul gol, la fel', true, !empty($rPreviz['erori']['oras']));
verifica('când sunt erori, nu se dă nicio cheie', true, empty($rPreviz['cheie']));

verifica('nimic în bază, oricâte greșeli', 0, cateEvenimente());

/* ------------------------ când datele sunt bune ------------------------ */

$rPreviz = previzualizeaza($c);
verifica('cu date bune, se primește o cheie', true, !empty($rPreviz['cheie']));
verifica('și tot nu se salvează nimic', 0, cateEvenimente());

$cheie = (string) $rPreviz['cheie'];
$pagina = cerere($baza . '/previzualizare.php?p=' . urlencode($cheie), $c);

verifica('pagina se deschide', 200, $pagina['stare']);
verifica('cu titlul din formular', true,
    str_contains($pagina['corp'], 'Cursa de seară prin centrul vechi'));
verifica('cu categoria pe nume, nu pe id', true, str_contains($pagina['corp'], 'Sport'));
// Orașul înaintea locului, în același rând: „Roman · Piața Sfatului…".
verifica('cu orașul înaintea locului', true,
    str_contains($pagina['corp'], 'Roman · Piața Sfatului, lângă fântână'));
verifica('spune limpede că nu e publicat', true,
    str_contains($pagina['corp'], 'nu e publicat nimic'));
verifica('și nu se lasă indexată', true, str_contains($pagina['corp'], 'name="robots"'));

/**
 * Aceeași afișare condiționată ca pe pagina adevărată: ce lipsește nu se
 * arată. E și dovada că amândouă trec prin afiseazaEveniment().
 */
verifica('patru rânduri de detalii, ca la un eveniment fără opționale', 4,
    substr_count($pagina['corp'], 'event-box__item'));
verifica('fără rând de vârstă minimă', false,
    str_contains($pagina['corp'], '<span>Vârstă minimă</span>'));
verifica('fără vorbe despre ora de sfârșit care lipsește', false,
    str_contains($pagina['corp'], 'nedeterminat'));

$rPreviz = previzualizeaza($c, [
    'ora_sfarsit' => '22:30', 'fara_ora_sfarsit' => null,
    'gratuit' => null, 'cost' => '45,50',
    'varsta_minima' => '18', 'gen_participanti' => 'femei',
    'fara_participanti_min' => null, 'participanti_min' => '10',
    'fara_participanti_max' => null, 'participanti_max' => '50',
]);
$pagina = cerere($baza . '/previzualizare.php?p=' . urlencode((string) $rPreviz['cheie']), $c)['corp'];

verifica('cu toate completate: șapte rânduri', 7, substr_count($pagina, 'event-box__item'));
verifica('intervalul orar întreg', true, str_contains($pagina, '19:00 — 22:30'));
verifica('costul scris pe românește', true, str_contains($pagina, '45,50 lei'));
verifica('vârsta minimă', true, str_contains($pagina, '18 ani'));
verifica('genul', true, str_contains($pagina, 'Doar femei'));
verifica('participanții', true, str_contains($pagina, 'minimum 10, cel mult 50'));

/* ----------------------- textul rămâne text ----------------------- */

$rPreviz = previzualizeaza($c, [
    'titlu'     => 'Titlu cu <script>alert(1)</script> & semne',
    'descriere' => "Primul paragraf, cu <b>etichete</b>.\n\nAl doilea.\nUn rând nou.\n\n"
                 . str_repeat('Umplem până la trei sute de caractere. ', 8),
]);
$pagina = cerere($baza . '/previzualizare.php?p=' . urlencode((string) $rPreviz['cheie']), $c)['corp'];

verifica('eticheta din titlu e scăpată', true, str_contains($pagina, '&lt;script&gt;'));
verifica('și nu ajunge cod în pagină', false, str_contains($pagina, '<script>alert(1)</script>'));
verifica('paragrafele devin <p>', true, str_contains($pagina, '<p>Primul paragraf'));
verifica('rândul simplu devine <br>', true, str_contains($pagina, 'Al doilea.<br'));

/* --------------------- care copertă se arată --------------------- */

/**
 * Ordinea, și n-are voie să se inverseze:
 *
 *   1. poza nouă din formular — și la creare, și la editare
 *   2. la editare, dacă n-a ales alta, cea salvată pe eveniment
 *   3. altfel, nimic
 *
 * A doua peste prima a fost un bug adevărat: la editare, cine alegea altă
 * poză o vedea în previzualizare tot pe cea veche.
 *
 * Fișierul nu ajunge până aici — pagina îl ia din browser — deci formularul
 * spune prin „coperta_noua" că vine unul. Locul lui în pagină se face doar
 * atunci; altfel s-ar desena chiar poza din bază.
 */
$idCuPoza = pune($idOrg, 'Unul care are deja copertă', 'aprobat', 6);
db()->prepare('UPDATE evenimente SET coperta = ? WHERE id = ?')
    ->execute([str_repeat('ef', 16), $idCuPoza]);
$slugCuPoza = $slugul($idCuPoza);

$vedeCoperta = static function (array $r) use ($baza, &$c): string {
    $p = cerere($baza . '/previzualizare.php?p=' . urlencode((string) $r['cheie']), $c)['corp'];

    if (!str_contains($p, 'post__figure')) {
        return 'fara';
    }

    return str_contains($p, 'id="prev-coperta"') ? 'browser' : 'bd';
};

// 2. editare, fără poză nouă → cea din bază
verifica('la editare fără poză nouă: cea din bază', 'bd',
    $vedeCoperta(previzualizeaza($c, ['slug' => $slugCuPoza])));

// 1. editare, cu poză nouă → locul pentru cea din browser
verifica('la editare CU poză nouă: locul pentru cea nouă', 'browser',
    $vedeCoperta(previzualizeaza($c, ['slug' => $slugCuPoza, 'coperta_noua' => '1'])));

// 1. creare, cu poză nouă → tot cea din browser
verifica('la creare cu poză nouă: tot cea nouă', 'browser',
    $vedeCoperta(previzualizeaza($c, ['coperta_noua' => '1'])));

// 3. nimic nicăieri → fără figură
verifica('fără nicio poză: nicio figură', 'fara',
    $vedeCoperta(previzualizeaza($c)));

// Un eveniment fără copertă, editat fără poză nouă: tot nimic.
verifica('editare fără copertă și fără poză nouă: nimic', 'fara',
    $vedeCoperta(previzualizeaza($c, ['slug' => $slugDeSchimbat])));

db()->prepare('DELETE FROM evenimente WHERE id = ?')->execute([$idCuPoza]);

/* ------------------------ cine poate deschide ------------------------ */

verifica('cheia altcuiva nu duce nicăieri', 302,
    cerere($baza . '/previzualizare.php?p=' . urlencode($cheie), $altul)['stare']);
verifica('nici o cheie inventată', 302,
    cerere($baza . '/previzualizare.php?p=deadbeef', $c)['stare']);
verifica('nici fără cheie', 302, cerere($baza . '/previzualizare.php', $c)['stare']);

$r = cerere($baza . '/previzualizare.php?p=' . urlencode($cheie), $anonim);
verifica('nelogatul e trimis la login', 302, $r['stare']);
verifica('spre formular, unde voia să ajungă', true,
    str_contains($r['unde'], 'login.php'));

/* --------------------- apărarea punctului de intrare --------------------- */

$r = cerere($baza . '/api/previzualizare.php', $c, ['csrf' => 'gresit', 'titlu' => 'x']);
verifica('fără CSRF bun → 419', 419, $r['stare']);

$gol = [];
$r = cerere($baza . '/api/previzualizare.php', $gol, ['titlu' => 'x']);
verifica('nelogat → 401 sau 419', true, in_array($r['stare'], [401, 419], true));

$r = cerere($baza . '/api/previzualizare.php', $c);
verifica('prin GET → 405', 405, $r['stare']);

/**
 * Previzualizarea nu creează niciun eveniment, deci limita de evenimente
 * active n-are ce căuta aici. Facem unul, ca limita să fie atinsă.
 */
pune($idOrg, 'Unul activ, ca să atingem limita', 'aprobat', 4);
verifica('un eveniment nou e refuzat, are deja unul', false, trimite($c)['ok'] ?? false);
verifica('dar previzualizarea merge oricum', true, !empty(previzualizeaza($c)['cheie']));
verifica('și tot un singur eveniment e în bază', 1, cateEvenimente());

echo "\n=== CÂTE EVENIMENTE A ORGANIZAT ===\n";

db()->exec('DELETE FROM evenimente');
verifica('la început, zero', 0, cateEvenimenteOrganizate($idOrg));

pune($idOrg, 'Aprobat, peste o lună', 'aprobat', 30);
pune($idOrg, 'Aprobat, mâine',        'aprobat', 1);
verifica('se numără cele aprobate care urmează', 2, cateEvenimenteOrganizate($idOrg));

/**
 * Trecutul se numără și el. Cifra spune cât a făcut omul pentru oraș, iar ce
 * a făcut nu se șterge când trece ziua — spre deosebire de lista de dedesubt,
 * care arată doar ce urmează.
 */
pune($idOrg, 'Aprobat, de acum un an',    'aprobat', -365);
pune($idOrg, 'Aprobat, de săptămâna trecută', 'aprobat', -7);
verifica('și cele din trecut se numără', 4, cateEvenimenteOrganizate($idOrg));

pune($idOrg, 'Încă neaprobat', 'in_asteptare', 5);
pune($idOrg, 'Respins',        'respins',      6);
verifica('dar nu și ce așteaptă moderarea', 4, cateEvenimenteOrganizate($idOrg));

pune($idAltul, 'Al altuia, aprobat', 'aprobat', 3);
verifica('nici evenimentele altcuiva', 4, cateEvenimenteOrganizate($idOrg));
verifica('fiecare cu numărul lui', 1, cateEvenimenteOrganizate($idAltul));

/* ---- și cifra din pagină, care e alta decât lungimea listei de sub ea ---- */

$pagina = cerere($baza . '/profil.php', $c)['corp'];

preg_match('/stat__value">(\d+)</', $pagina, $m);
verifica('cifra din pagină e cea numărată', '4', $m[1] ?? '');

// Lista de dedesubt arată altceva: două aprobate care urmează + una în
// așteptare. Tocmai de-aia numărul nu se poate lua din lungimea ei.
verifica('lista de dedesubt are trei cartonașe', 3, substr_count($pagina, '<article class="card'));

$paginaDinAfara = cerere($baza . '/profil.php?m=organizat02', $altul)['corp'];
preg_match('/stat__value">(\d+)</', $paginaDinAfara, $m);
verifica('din afară se vede aceeași cifră', '4', $m[1] ?? '');

/* ---------------- cartonașele de pe profil duc la pagină ---------------- */

db()->exec('DELETE FROM evenimente');
$idAprobat = pune($idOrg, 'Cursa aprobată', 'aprobat', 7);

$pagina = cerere($baza . '/profil.php', $c)['corp'];
verifica('cartonașele de pe profil trimit la event.php', true,
    str_contains($pagina, 'href="event.php?slug=' . $slugul($idAprobat) . '"'));

/* -------------- „+ Eveniment nou", pe profilul propriu ----------------- */

/**
 * Butonul se vedea doar în locul gol, adică exact la cine n-avea niciun
 * eveniment. Acum stă în capul secțiunii și când lista are ceva în ea — dar
 * tot numai pe profilul propriu, și tot unul singur.
 */
verifica('cu evenimente în listă, butonul e acolo', 1,
    substr_count($pagina, 'href="adauga_eveniment.php"'));
verifica('și stă în capul secțiunii', true,
    preg_match('/<div class="section-head">.*?href="adauga_eveniment\.php".*?<\/div>\s*<\/div>/s', $pagina) === 1);

verifica('pe profilul altcuiva, niciun buton', 0,
    substr_count($paginaDinAfara = cerere($baza . '/profil.php?m=organizat02', $altul)['corp'],
                 'href="adauga_eveniment.php"'));

// Fără niciun eveniment rămâne invitația din locul gol — tot un buton, nu doi.
db()->exec('DELETE FROM evenimente');
$paginaGoala = cerere($baza . '/profil.php', $c)['corp'];
verifica('fără evenimente, tot un singur buton', 1,
    substr_count($paginaGoala, 'href="adauga_eveniment.php"'));
verifica('și e cel din invitație', true,
    str_contains($paginaGoala, 'Nu organizezi nimic'));


echo "\n=== MERGI LA ACEST EVENIMENT? ===\n";

require_once dirname(__DIR__) . '/inc/interese.php';

db()->exec('DELETE FROM evenimente');

/** Apasă un buton de pe pagina evenimentului. */
function apasa(array &$cookies, string $slug, string $stare, array $peste = []): array
{
    return json_din(cerere($GLOBALS['baza'] . '/api/interes.php', $cookies, array_merge([
        'csrf'  => csrf($cookies),
        'slug'  => $slug,
        'stare' => $stare,
    ], $peste)));
}

/** Starea unui om față de un eveniment, citită de-a dreptul din bază. */
function stareaDinBaza(int $evId, int $membruId): ?string
{
    $q = db()->prepare('SELECT stare FROM interese_evenimente WHERE eveniment_id=? AND membru_id=?');
    $q->execute([$evId, $membruId]);
    $s = $q->fetchColumn();

    return is_string($s) ? $s : null;
}

// Limita de evenimente active i-ar sta în cale mai jos, unde publică prin
// formular peste unul pus de mână. Aici nu ea se probează.
db()->prepare('UPDATE membri SET limita_evenimente_active = 5 WHERE id = ?')->execute([$idOrg]);

$idEv   = pune($idOrg, 'Unul la care se vine', 'aprobat', 15);
$slugEv = $slugul($idEv);

/* ------------------- organizatorul intră singur ------------------- */

verifica('organizatorul unui eveniment pus de mână n-are rând', null,
    stareaDinBaza($idEv, $idOrg));

// …dar unul publicat prin formular, da.
$r = trimite($c, ['titlu' => 'Publicat ca lumea, prin formular']);
verifica('evenimentul nou trece', true, $r['ok'] ?? false);
$idNou = (int) db()->query('SELECT id FROM evenimente ORDER BY id DESC LIMIT 1')->fetchColumn();
verifica('organizatorul e trecut singur ca participant', 'participant',
    stareaDinBaza($idNou, $idOrg));
verifica('fără să apese nimic', 1, (int) db()->query(
    'SELECT COUNT(*) FROM interese_evenimente WHERE eveniment_id = ' . $idNou)->fetchColumn());
db()->prepare('DELETE FROM evenimente WHERE id = ?')->execute([$idNou]);
verifica('iar ștergerea evenimentului îi ia rândul cu el', 0, (int) db()->query(
    'SELECT COUNT(*) FROM interese_evenimente WHERE eveniment_id = ' . $idNou)->fetchColumn());

/* --------------------------- „Mă interesează" -------------------------- */

$r = apasa($altul, $slugEv, 'interesat');
verifica('„mă interesează" merge din prima, fără confirmări', true, $r['ok'] ?? false);
verifica('starea e cea apăsată', 'interesat', $r['stare'] ?? '');
verifica('și în bază la fel', 'interesat', stareaDinBaza($idEv, $idAltul));
verifica('numărul de interesați a crescut', 1, $r['numar']['interesat'] ?? -1);
verifica('cel de participanți nu', 0, $r['numar']['participant'] ?? -1);

$r = apasa($altul, $slugEv, 'interesat');
verifica('a doua apăsare pe același buton îl scoate', true, $r['ok'] ?? false);
// „??" ar fi înlocuit un null adevărat cu ce e după el, deci se citește direct.
verifica('fără stare', true, array_key_exists('stare', $r) && $r['stare'] === null);
verifica('și fără rând în bază', null, stareaDinBaza($idEv, $idAltul));
verifica('numărul a scăzut la loc', 0, $r['numar']['interesat'] ?? -1);

/* --------------------------- „Voi participa" --------------------------- */

$r = apasa($altul, $slugEv, 'participant');
verifica('participarea fără confirmare e oprită', false, $r['ok'] ?? true);
verifica('cu mesajul cerut', 'Confirmă întâi participarea.', $r['mesaj'] ?? '');
verifica('și nimic în bază', null, stareaDinBaza($idEv, $idAltul));

// Fără telefon în cont, se cere acum.
db()->prepare('UPDATE membri SET telefon = NULL WHERE id = ?')->execute([$idAltul]);
$r = apasa($altul, $slugEv, 'participant', ['confirmat' => '1']);
verifica('fără telefon în cont, se cere', true, !empty($r['erori']['telefon']));
verifica('tot nimic în bază', null, stareaDinBaza($idEv, $idAltul));

$r = apasa($altul, $slugEv, 'participant', ['confirmat' => '1', 'telefon' => 'nu-i telefon']);
verifica('un număr stricat e refuzat', true, !empty($r['erori']['telefon']));

$r = apasa($altul, $slugEv, 'participant', ['confirmat' => '1', 'telefon' => '+40 722 33 44 55']);
verifica('un număr bun trece', true, $r['ok'] ?? false);
verifica('starea e „participant"', 'participant', stareaDinBaza($idEv, $idAltul));

$q = db()->prepare('SELECT telefon FROM membri WHERE id = ?');
$q->execute([$idAltul]);
verifica('numărul s-a salvat în cont, adus la o singură formă', '0722334455', $q->fetchColumn());

// A doua oară nu se mai cere: e deja în cont.
apasa($altul, $slugEv, 'participant');                       // se retrage
$r = apasa($altul, $slugEv, 'participant', ['confirmat' => '1']);
verifica('a doua oară nu se mai cere numărul', true, $r['ok'] ?? false);

/* ------------------ trecerea dintr-o stare în alta ------------------ */

apasa($altul, $slugEv, 'participant');                       // curat
apasa($altul, $slugEv, 'interesat');
$q = db()->prepare('SELECT id, creat_la FROM interese_evenimente WHERE eveniment_id=? AND membru_id=?');
$q->execute([$idEv, $idAltul]);
$inainte = $q->fetch();

$r = apasa($altul, $slugEv, 'participant', ['confirmat' => '1']);
$q->execute([$idEv, $idAltul]);
$dupa = $q->fetch();

verifica('trecerea la „particip" schimbă rândul', $inainte['id'], $dupa['id']);
verifica('nu adaugă altul', 1, (int) db()->query(
    'SELECT COUNT(*) FROM interese_evenimente WHERE eveniment_id = ' . $idEv)->fetchColumn());
verifica('și ține minte când a intrat prima dată', $inainte['creat_la'], $dupa['creat_la']);

/* ---------------------------- locurile ---------------------------- */

db()->prepare('UPDATE evenimente SET participanti_max = 1 WHERE id = ?')->execute([$idEv]);

$alTreilea = faMembru('al-treilea@exemplu-test.ro', 'trei02');
db()->prepare('UPDATE membri SET telefon = ? WHERE id = ?')->execute(['0733445566', $alTreilea]);
$c3 = [];
intra($c3, 'al-treilea@exemplu-test.ro');

$r = apasa($c3, $slugEv, 'participant', ['confirmat' => '1']);
verifica('locul e ocupat, deci e oprit', false, $r['ok'] ?? true);
verifica('cu mesajul cerut', 'Nu mai sunt locuri disponibile la acest eveniment.', $r['mesaj'] ?? '');
verifica('și cu semnul că e plin', true, $r['plin'] ?? false);
verifica('nimic în bază', null, stareaDinBaza($idEv, $alTreilea));

verifica('dar „mă interesează" merge oricum', true,
    apasa($c3, $slugEv, 'interesat')['ok'] ?? false);

// Cine e înăuntru se poate retrage oricând, chiar dacă e plin.
verifica('cel dinăuntru se poate retrage', true,
    apasa($altul, $slugEv, 'participant')['ok'] ?? false);
verifica('iar locul eliberat se poate ocupa', true,
    apasa($c3, $slugEv, 'participant', ['confirmat' => '1'])['ok'] ?? false);

// „nespecificat" nu oprește pe nimeni.
db()->prepare('UPDATE evenimente SET participanti_max = NULL WHERE id = ?')->execute([$idEv]);
verifica('fără limită, intră și al doilea', true,
    apasa($altul, $slugEv, 'participant', ['confirmat' => '1'])['ok'] ?? false);

/* --------------------------- cine n-are voie --------------------------- */

$gol = [];
verifica('nelogatul e refuzat', false, apasa($gol, $slugEv, 'interesat')['ok'] ?? true);
verifica('fără CSRF bun, nimic', false,
    json_din(cerere($baza . '/api/interes.php', $altul, [
        'csrf' => 'gresit', 'slug' => $slugEv, 'stare' => 'interesat',
    ]))['ok'] ?? true);
verifica('o stare inventată e refuzată', false,
    apasa($altul, $slugEv, 'ma-gandesc')['ok'] ?? true);
verifica('un slug inexistent, la fel', false,
    apasa($altul, 'nu-exista-nicaieri', 'interesat')['ok'] ?? true);
verifica('prin GET → 405', 405,
    cerere($baza . '/api/interes.php', $altul)['stare']);

// La un eveniment neaprobat nu se înscrie nimeni — nici organizatorul.
$idAstept = pune($idOrg, 'Încă nu s-a aprobat', 'in_asteptare', 11);
$r = apasa($c, $slugul($idAstept), 'interesat');
verifica('la un eveniment neaprobat nu se înscrie nimeni', false, $r['ok'] ?? true);

/* ------------------------ ce se vede în pagină ------------------------ */

$pagina = cerere($baza . '/event.php?slug=' . urlencode($slugEv), $altul)['corp'];

verifica('pagina arată numerele adevărate', true,
    preg_match('/data-count-for="participant"[^>]*>2</', $pagina) === 1);
verifica('și butonul apăsat e însemnat ca atare', true,
    preg_match('/id="btn-going"[^>]*aria-pressed="true"/s', $pagina) === 1);
verifica('caseta de confirmare e în pagină, ascunsă', true,
    preg_match('/id="rsvp-confirm"[^>]*hidden/', $pagina) === 1);
verifica('spune ce vede organizatorul', true,
    str_contains($pagina, 'văzute de'));
verifica('și pomenește WhatsApp', true, str_contains($pagina, 'WhatsApp'));
verifica('cu trimitere la termeni', true, str_contains($pagina, 'Termenii și condițiile'));
verifica('cine are telefon în cont nu mai e întrebat', false,
    str_contains($pagina, 'id="rsvp-telefon"'));

// Vorba de sub butoane: se numără interesații ȘI participanții.
verifica('vorba adună toată lumea', true,
    preg_match('/(este interesat|sunt interesa)/u', $pagina) === 1);
verifica('cu nume care duc la profiluri', true,
    preg_match('/rsvp__note[^>]*>.*?profil\.php\?m=/s', $pagina) === 1);
verifica('și cu chipuri, fără link', true,
    preg_match('/<div class="facepile"[^>]*>\s*<img/s', $pagina) === 1);

// La un eveniment fără nimeni, se spune altceva.
$idPustiu = pune($idOrg, 'La care nu vine nimeni', 'aprobat', 16);
$pustiu = cerere($baza . '/event.php?slug=' . urlencode($slugul($idPustiu)), $altul)['corp'];
verifica('fără nimeni, o invitație', true,
    str_contains($pustiu, 'Fii primul interesat de acest eveniment!'));
verifica('fără cercuri goale', false, str_contains($pustiu, 'class="facepile"'));

// La unul neaprobat, secțiunea lipsește cu totul.
$asteapta = cerere($baza . '/event.php?slug=' . urlencode($slugul($idAstept)), $c)['corp'];
verifica('la un eveniment neaprobat, secțiunea nici nu apare', false,
    str_contains($asteapta, 'id="rsvp"'));
// Nici butoanele de distribuire: n-are rost să dai mai departe un anunț pe
// care nu-l poate deschide nimeni.
verifica('nici butoanele de distribuire', false, str_contains($asteapta, 'post__share'));

db()->prepare('DELETE FROM membri WHERE email = ?')->execute(['al-treilea@exemplu-test.ro']);


echo "\n=== CARTONAȘUL DE PE WHATSAPP (Open Graph) ===\n";

/**
 * Fără og:image, WhatsApp arată doar titlu și text — de-aia lipsea coperta.
 * Adresele trebuie să fie ÎNTREGI: WhatsApp și Facebook cer pagina de pe alt
 * server, iar o cale de forma „assets/img/…" n-are acolo față de ce să se
 * socotească.
 */
$idOg = pune($idOrg, 'Un eveniment de dat mai departe', 'aprobat', 9);
$slugOg = $slugul($idOg);
$numeCopertaOg = bin2hex(random_bytes(16));
$caleCopertaOg = dirname(__DIR__) . '/' . COPERTA_DOSAR . '/' . $numeCopertaOg . '.jpg';
file_put_contents($caleCopertaOg, pozaDeProba(1600, 900));
db()->prepare('UPDATE evenimente SET coperta = ? WHERE id = ?')->execute([$numeCopertaOg, $idOg]);

$og = static function (string $corp, string $cheie): string {
    $atribut = str_starts_with($cheie, 'twitter:') ? 'name' : 'property';
    return preg_match('/<meta ' . $atribut . '="' . preg_quote($cheie, '/')
                    . '" content="([^"]*)"/', $corp, $m) === 1 ? $m[1] : '';
};

$paginaOg = cerere($baza . '/event.php?slug=' . urlencode($slugOg), $anonim)['corp'];
$siteOg   = rtrim((string) ($config['url_site'] ?? ''), '/');

verifica('og:title e titlul evenimentului', 'Un eveniment de dat mai departe',
    $og($paginaOg, 'og:title'));
verifica('og:type e „article"', 'article', $og($paginaOg, 'og:type'));
verifica('og:url e adresa întreagă a paginii',
    $siteOg . '/event.php?slug=' . $slugOg, $og($paginaOg, 'og:url'));
verifica('og:image e adresa întreagă a copertei',
    $siteOg . '/' . COPERTA_DOSAR . '/' . $numeCopertaOg . '.jpg',
    $og($paginaOg, 'og:image'));
verifica('și e o adresă absolută, nu o cale', true,
    str_starts_with($og($paginaOg, 'og:image'), 'http'));
verifica('cu mărimea spusă dinainte', ['1600', '900'],
    [$og($paginaOg, 'og:image:width'), $og($paginaOg, 'og:image:height')]);
verifica('twitter cere poza mare', 'summary_large_image', $og($paginaOg, 'twitter:card'));

$descriereOg = $og($paginaOg, 'og:description');
verifica('og:description e din descrierea evenimentului', true,
    str_starts_with(html_entity_decode($descriereOg), 'Povestea evenimentului.'));
verifica('scurtată, nu întreagă', true, mb_strlen($descriereOg) <= 200);
verifica('și fără etichete', false, str_contains(html_entity_decode($descriereOg), '<'));

// Fără copertă și fără imagine de categorie pe disc: mai bine nicio poză decât
// una care duce la 404.
db()->prepare('UPDATE evenimente SET coperta = NULL WHERE id = ?')->execute([$idOg]);
$faraPoza = cerere($baza . '/event.php?slug=' . urlencode($slugOg), $anonim)['corp'];
verifica('fără copertă, fără og:image', '', $og($faraPoza, 'og:image'));
verifica('și twitter cere cartonașul mic', 'summary', $og($faraPoza, 'twitter:card'));

// Restul paginilor primesc valorile obișnuite, fără să ceară nimic.
$paginaDespre = cerere($baza . '/despre.php', $anonim)['corp'];
verifica('o pagină obișnuită are og:type „website"', 'website', $og($paginaDespre, 'og:type'));
verifica('cu titlul ei', true, str_contains($og($paginaDespre, 'og:title'), 'Despre'));
verifica('și cu adresa ei întreagă', $siteOg . '/despre.php', $og($paginaDespre, 'og:url'));
verifica('fără poză, că n-are ce arăta', '', $og($paginaDespre, 'og:image'));

@unlink($caleCopertaOg);

echo "\n=== UN EVENIMENT CARE S-A ÎNCHEIAT ===\n";

/**
 * Ziua lui a trecut. Nu se ascunde și nu se închide — rămâne o pagină bună de
 * citit și de trimis mai departe. Se schimbă doar ce se poate face pe ea.
 */
$idTrecut   = pune($idOrg, 'Ce a fost anul trecut', 'aprobat', -3);
$slugTrecut = $slugul($idTrecut);
$laTrecut   = $baza . '/event.php?slug=' . urlencode($slugTrecut);

verifica('regula e aceeași ca la limita de postare', true,
    evenimentIncheiat(evenimentDupaSlug($slugTrecut)));
verifica('iar unul de azi NU e încheiat', false,
    evenimentIncheiat(['data_eveniment' => date('Y-m-d')]));

$paginaTrecut = cerere($laTrecut, $anonim);
verifica('pagina se deschide ca oricare alta', 200, $paginaTrecut['stare']);
verifica('și pentru cine nu are cont', true, str_contains($paginaTrecut['corp'], 'Ce a fost anul trecut'));

verifica('are banda de încheiat', true,
    str_contains($paginaTrecut['corp'], 'stare-anunt--incheiat'));
verifica('care spune limpede ce s-a întâmplat', true,
    str_contains($paginaTrecut['corp'], 'Acest eveniment s-a încheiat.'));
verifica('și nu se împrumută din culorile de eroare', false,
    str_contains($paginaTrecut['corp'], 'stare-anunt--respins'));

verifica('butonul „mă interesează" e stins', true,
    preg_match('/id="btn-interested".*?disabled/s', $paginaTrecut['corp']) === 1);
verifica('și cel de participare', true,
    preg_match('/id="btn-going".*?disabled/s', $paginaTrecut['corp']) === 1);
verifica('caseta de confirmare nici nu se scrie', false,
    str_contains($paginaTrecut['corp'], 'id="rsvp-confirm"'));
verifica('JS-ul e prevenit prin atribut', true,
    str_contains($paginaTrecut['corp'], 'data-incheiat="1"'));
verifica('și nu-l mai cheamă să fie primul interesat', false,
    str_contains($paginaTrecut['corp'], 'Fii primul interesat'));

// Numărătoarea rămâne: e istoria evenimentului, nu o invitație.
salveazaInteres($idTrecut, $idAltul, 'participant');
$paginaTrecut = cerere($laTrecut, $anonim)['corp'];
verifica('numărul celor care au fost rămâne afișat', true,
    preg_match('/data-count-for="participant"[^>]*>1</', $paginaTrecut) === 1);
verifica('și oamenii, la fel', true, str_contains($paginaTrecut, 'facepile'));

/* -------- serverul nu se bazează pe butonul stins din pagină -------- */

$r = apasa($altul, $slugTrecut, 'interesat');
verifica('serverul refuză o cerere venită pe lângă interfață', false, $r['ok'] ?? true);
verifica('cu mesajul cerut', 'Evenimentul s-a încheiat.', $r['mesaj'] ?? '');

$r = apasa($altul, $slugTrecut, 'participant', ['confirmat' => '1']);
verifica('și participarea, la fel', false, $r['ok'] ?? true);

// Nici retragerea: listele unui eveniment trecut sunt istorie.
verifica('nici retragerea nu se mai poate', false,
    apasa($altul, $slugTrecut, 'participant')['ok'] ?? true);
verifica('deci rândul e neatins', 'participant', stareaDinBaza($idTrecut, $idAltul));

/* ---------------------------- curățenie -------------------------------- */

db()->exec('DELETE FROM evenimente');
db()->prepare('DELETE FROM membri WHERE email IN (?, ?)')->execute([EMAIL_ORG, EMAIL_ALTUL]);
foreach (glob(dirname(__DIR__) . '/' . COPERTA_DOSAR . '/*.jpg') ?: [] as $f) { @unlink($f); }

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
