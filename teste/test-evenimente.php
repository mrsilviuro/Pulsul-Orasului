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
    urlEveniment((string) $ev['slug']), $r['url'] ?? '');

echo "\n=== UN SINGUR EVENIMENT ACTIV ===\n";

$r = trimite($c, ['titlu' => 'Al doilea eveniment, care nu trebuie să intre']);
verifica('al doilea e oprit', false, $r['ok'] ?? true);
verifica('cu mesajul cerut', 'Ai deja un eveniment activ. Poți publica altul după ce se încheie ăsta.',
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

// Cu un caracter sub prag, oricare ar fi pragul: scris de mână, numărul ar fi
// rămas în urmă la prima schimbare a lui DESCRIERE_MIN — și chiar a rămas, când
// pragul a coborât de la 300 la 200.
$scurt = str_repeat('a', DESCRIERE_MIN - 1);

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
    ['descriere sub prag',   ['descriere' => $scurt],                        'descriere'],
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

// Fix cât pragul, în „ă": de două ori mai mulți octeți decât caractere. Cu
// strlen() ar fi părut de ajuns cu mult înainte de a fi. Trebuie să treacă.
$r = trimite($c, ['descriere' => str_repeat('ă', DESCRIERE_MIN)]);
verifica('fix ' . DESCRIERE_MIN . ' de „ă" trec (dublu în octeți)', true, $r['ok'] ?? false);
verifica('și se salvează întregi', DESCRIERE_MIN,
    mb_strlen((string) ultimulEveniment()['descriere'], 'UTF-8'));

db()->exec('DELETE FROM evenimente');

// Cu unul mai puțin: în octeți ar fi trecut de mult, în caractere nu.
$r = trimite($c, ['descriere' => str_repeat('ă', DESCRIERE_MIN - 1)]);
verifica('unul mai puțin NU trece, deși are octeți destui', true,
    !empty($r['erori']['descriere']));

echo "\n=== TEXTUL RĂMÂNE CUM L-A SCRIS OMUL ===\n";

$descriereCuParagrafe = "Primul paragraf, despre ce facem.\n\nAl doilea paragraf, cu detalii.\n\n"
    . str_repeat('Mai scriem ceva ca să trecem de pragul de caractere. ', 6);

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

echo "\n=== CÂT DE CURÂND POATE ÎNCEPE ===\n";

/**
 * Data singură se uita doar la ZI: la ora 15:00 se putea publica ceva „azi, de
 * la 14:00" — un eveniment început în clipa în care apărea pe site. Acum
 * verificarea pune data și ora cap la cap și cere cel puțin două ceasuri
 * (ORE_MINIM_INAINTE).
 */
$r = trimite($c, [
    'titlu'          => 'Ceva care începe cam prea repede',
    'data_eveniment' => date('d-m-Y', time() + 3600),
    'ora_inceput'    => date('H:i',   time() + 3600),
]);
verifica('peste o oră: respins', true, !empty($r['erori']['ora_inceput']));
verifica('și n-a intrat în bază', 0, cateEvenimente());

$r = trimite($c, [
    'titlu'          => 'Ceva care a început deja',
    'data_eveniment' => date('d-m-Y', time() - 3600),
    'ora_inceput'    => date('H:i',   time() - 3600),
]);
verifica('o oră în urmă, azi: respins', true,
    !empty($r['erori']['ora_inceput']) || !empty($r['erori']['data_eveniment']));
verifica('nici acela n-a intrat', 0, cateEvenimente());

$r = trimite($c, [
    'titlu'          => 'Ceva de peste trei ceasuri',
    'data_eveniment' => date('d-m-Y', time() + 3 * 3600),
    'ora_inceput'    => date('H:i',   time() + 3 * 3600),
]);
verifica('peste trei ore: primit', true, !empty($r['ok']));

/* ============ EDITAREA SE ÎNCHIDE CÂND ÎNCEPE EVENIMENTUL ============ */

echo "\n=== EDITAREA, DUPĂ CE A ÎNCEPUT ===\n";

$evAlNostru = ultimulEveniment();
$slugAlNostru = (string) $evAlNostru['slug'];

/* Îl mutăm cu o oră în urmă: a început, dar e încă în fereastra de anulare. */
db()->prepare('UPDATE evenimente SET data_eveniment = ?, ora_inceput = ? WHERE id = ?')
    ->execute([date('Y-m-d'), date('H:i:s', time() - 1800), (int) $evAlNostru['id']]);

// `trimite()` întoarce doar trupul răspunsului, nu și codul — de aceea se
// verifică mesajul, care e al acestei porți și numai al ei.
$r = trimite($c, ['slug' => $slugAlNostru, 'titlu' => 'Titlu schimbat după start']);
verifica('a început → editarea e refuzată', false, !empty($r['ok']));
verifica('și i se spune de ce', true,
    str_contains((string) ($r['mesaj'] ?? ''), 'a început'));
verifica('cu îndrumarea spre ce mai poate face', true,
    str_contains((string) ($r['mesaj'] ?? ''), 'anula sau încheia'));
verifica('și titlul a rămas cel vechi', (string) $evAlNostru['titlu'],
    (string) (ultimulEveniment()['titlu'] ?? ''));

/* Mutat înapoi în viitor, se editează din nou. */
db()->prepare('UPDATE evenimente SET data_eveniment = ?, ora_inceput = ? WHERE id = ?')
    ->execute([date('Y-m-d', strtotime('+10 days')), '19:00:00', (int) $evAlNostru['id']]);

$r = trimite($c, [
    'slug'           => $slugAlNostru,
    'titlu'          => 'Titlu schimbat înainte de start',
    'data_eveniment' => date('d-m-Y', strtotime('+10 days')),
    'ora_inceput'    => '19:00',
]);
verifica('înainte de start, editarea merge', true, !empty($r['ok']));
verifica('și titlul chiar s-a schimbat', 'Titlu schimbat înainte de start',
    (string) (ultimulEveniment()['titlu'] ?? ''));

/**
 * Editată fără să i se schimbe ora, chiar și cu startul la mai puțin de două
 * ceasuri: trece. Altfel, cine îndreaptă o virgulă cu o oră înainte de start
 * ar fi fost trimis să-și amâne ieșirea.
 */
$peste90 = time() + 90 * 60;
db()->prepare('UPDATE evenimente SET data_eveniment = ?, ora_inceput = ? WHERE id = ?')
    ->execute([date('Y-m-d', $peste90), date('H:i', $peste90) . ':00', (int) $evAlNostru['id']]);

$r = trimite($c, [
    'slug'           => $slugAlNostru,
    'titlu'          => 'O virgulă îndreptată în ultima oră',
    'data_eveniment' => date('d-m-Y', $peste90),
    'ora_inceput'    => date('H:i', $peste90),
]);
verifica('la 90 de minute de start, o corectură tot trece', true, !empty($r['ok']));

/* Dar mutat și mai aproape, nu. */
$r = trimite($c, [
    'slug'           => $slugAlNostru,
    'titlu'          => 'Mutat și mai aproape',
    'data_eveniment' => date('d-m-Y', time() + 1800),
    'ora_inceput'    => date('H:i',   time() + 1800),
]);
verifica('dar mutat și mai aproape, nu', true, !empty($r['erori']['ora_inceput']));

/* Curățăm, ca restul probei să pornească de la zero. */
db()->prepare('DELETE FROM evenimente WHERE id = ?')->execute([(int) $evAlNostru['id']]);
verifica('am făcut curat', 0, cateEvenimente());

/* ================== ÎNCĂ UNUL LA FEL („Remake") ===================== */

echo "\n=== REMAKE: CINE ȘI CÂND ===\n";

/**
 * Pe dos față de „Editează": butonul apare abia după ce evenimentul s-a
 * terminat sau s-a anulat. Alergarea de duminică se face și duminica
 * viitoare; cea căzută din cauza ploii se mută pe altă zi.
 */
$candva = static function (string $stare, int $pesteZile): array {
    return [
        'stare_moderare' => $stare,
        'data_eveniment' => date('Y-m-d', strtotime(($pesteZile >= 0 ? '+' : '-')
                                . abs($pesteZile) . ' days')),
        'ora_inceput'    => '19:00:00',
    ];
};

verifica('unul anulat se poate reface',   true,  poateFiRefacut($candva('anulat', 6)));
verifica('unul încheiat de mână, la fel', true,  poateFiRefacut($candva('incheiat', 6)));
verifica('unul cu ziua trecută, la fel',  true,  poateFiRefacut($candva('aprobat', -2)));

verifica('unul care încă urmează, NU',    false, poateFiRefacut($candva('aprobat', 6)));
verifica('nici unul în așteptare',        false, poateFiRefacut($candva('in_asteptare', -2)));
verifica('nici unul respins',             false, poateFiRefacut($candva('respins', -2)));

/**
 * „Editează" și „Remake" nu se suprapun niciodată: unul ține până la ora de
 * început, celălalt pornește după sfârșit. La un eveniment care încă urmează
 * merge doar primul; la unul trecut, doar al doilea.
 */
$viitor = $candva('aprobat', 6);
$trecut = $candva('aprobat', -2);

verifica('la unul viitor: se editează, nu se reface',
    [true, false], [poateFiEditat($viitor), poateFiRefacut($viitor)]);
verifica('la unul trecut: se reface, nu se editează',
    [false, true], [poateFiEditat($trecut), poateFiRefacut($trecut)]);

echo "\n=== REMAKE: AL CUI E ===\n";

/* Un al doilea om, ca să avem cui refuza. */
$idStrain = faMembru('strain-remake-test@exemplu-test.ro', 'organizat09');

$idRefacut = pune($idOrg, 'De refăcut mai târziu', 'incheiat', -3);
$slugRefacut = (string) db()->query('SELECT slug FROM evenimente WHERE id = ' . $idRefacut)
    ->fetchColumn();

verifica('organizatorul îl primește', $idRefacut,
    (int) (evenimentDeRefacut($slugRefacut, $idOrg)['id'] ?? 0));
verifica('altcineva nu', null, evenimentDeRefacut($slugRefacut, $idStrain));
verifica('nici cine nu e conectat', null, evenimentDeRefacut($slugRefacut, 0));
verifica('un slug care nu duce nicăieri', null,
    evenimentDeRefacut('nu-exista-slugul-asta', $idOrg));

$idViitor = pune($idOrg, 'Care încă urmează', 'aprobat', 8);
$slugViitor = (string) db()->query('SELECT slug FROM evenimente WHERE id = ' . $idViitor)
    ->fetchColumn();

verifica('unul care încă urmează nu se dă', null,
    evenimentDeRefacut($slugViitor, $idOrg));

echo "\n=== REMAKE: COPERTA SE COPIAZĂ ===\n";

/**
 * Se copiază FIȘIERUL, nu doar numele lui. Două anunțuri care ar arăta spre
 * aceeași poză ar fi însemnat că ștergerea unuia îl lasă pe celălalt fără ea.
 */
$dosarCop = dirname(__DIR__) . '/' . COPERTA_DOSAR;
@mkdir($dosarCop, 0755, true);

$numeVechi = bin2hex(random_bytes(16));
$panza = imagecreatetruecolor(160, 90);
imagejpeg($panza, $dosarCop . '/' . $numeVechi . '.jpg', 80);
imagedestroy($panza);

$numeNou = copiazaCoperta($numeVechi);

verifica('numele nou e altul', true, is_string($numeNou) && $numeNou !== $numeVechi);
verifica('și arată a copertă', true, esteCopertaValida($numeNou));
verifica('fișierul nou există', true, is_file($dosarCop . '/' . $numeNou . '.jpg'));
verifica('cel vechi a rămas la locul lui', true, is_file($dosarCop . '/' . $numeVechi . '.jpg'));
verifica('și au același cuprins',
    md5_file($dosarCop . '/' . $numeVechi . '.jpg'),
    md5_file($dosarCop . '/' . $numeNou . '.jpg'));

verifica('fără copertă, nimic de copiat', null, copiazaCoperta(null));
verifica('un nume inventat nu trece', null, copiazaCoperta('../../inc/config'));
verifica('nici unul care nu e pe disc', null, copiazaCoperta(bin2hex(random_bytes(16))));

@unlink($dosarCop . '/' . $numeVechi . '.jpg');
@unlink($dosarCop . '/' . $numeNou . '.jpg');

db()->exec('DELETE FROM evenimente');
verifica('curat înainte de mai departe', 0, cateEvenimente());

echo "\n=== REMAKE: PRIN SERVER ===\n";

/**
 * Drumul întreg: un eveniment încheiat, cu poză, din care se face unul nou.
 * Ce trimite formularul e ce era completat în pagină, plus slugul de refăcut
 * într-un câmp ascuns — singurul lucru pentru care e nevoie de el la salvare
 * fiind coperta, care stă pe disc și n-are cum să plece prin formular.
 */
$numeSursa = bin2hex(random_bytes(16));
$panza = imagecreatetruecolor(1600, 900);
imagejpeg($panza, $dosarCop . '/' . $numeSursa . '.jpg', 80);
imagedestroy($panza);

$idSursa = pune($idOrg, 'Alergarea de duminică', 'incheiat', -5);
db()->prepare('UPDATE evenimente SET coperta = ? WHERE id = ?')->execute([$numeSursa, $idSursa]);

$slugSursa = (string) db()->query('SELECT slug FROM evenimente WHERE id = ' . $idSursa)
    ->fetchColumn();

$peste9 = strtotime('+9 days');
$r = trimite($c, [
    'remake'         => $slugSursa,
    'titlu'          => 'Alergarea de duminică',
    'data_eveniment' => date('d-m-Y', $peste9),
    'ora_inceput'    => '09:00',
]);

verifica('remake-ul se publică', true, !empty($r['ok']));

$nou = ultimulEveniment();

verifica('e un eveniment NOU, nu cel vechi', true, (int) $nou['id'] !== $idSursa);
verifica('cel vechi a rămas neatins', 'incheiat',
    (string) db()->query('SELECT stare_moderare FROM evenimente WHERE id = ' . $idSursa)
        ->fetchColumn());
verifica('cel nou intră la verificare', 'in_asteptare', (string) $nou['stare_moderare']);
verifica('cu data cea nouă', date('Y-m-d', $peste9), (string) $nou['data_eveniment']);

verifica('coperta s-a copiat, cu alt nume', true,
    esteCopertaValida($nou['coperta'] ?? null) && $nou['coperta'] !== $numeSursa);
verifica('și e un al doilea fișier pe disc', true,
    is_file($dosarCop . '/' . $nou['coperta'] . '.jpg'));
verifica('cel vechi tot acolo e', true, is_file($dosarCop . '/' . $numeSursa . '.jpg'));

/**
 * Slugul din formular NU e o dovadă. Cu al altcuiva, anunțul se face oricum —
 * dar fără poză. O eroare la mijlocul unei publicări n-ar ajuta pe nimeni:
 * „evenimentul din care copiezi nu mai e al tău" nu e ceva ce omul poate
 * îndrepta din formular.
 */
db()->prepare('DELETE FROM evenimente WHERE id = ?')->execute([(int) $nou['id']]);
stergeCopertaDeFisier($nou['coperta'] ?? null);

$r = trimite($c, [
    'remake'         => 'nu-e-al-meu-slugul-asta',
    'titlu'          => 'Fără poză, dar publicat',
    'data_eveniment' => date('d-m-Y', $peste9),
    'ora_inceput'    => '09:00',
]);

verifica('cu un slug străin, anunțul se face tot', true, !empty($r['ok']));
// `??` ar fi înghițit tocmai NULL-ul căutat: null ?? 'ceva' dă 'ceva'.
$faraPoza = ultimulEveniment();
verifica('dar fără copertă', true,
    array_key_exists('coperta', $faraPoza) && $faraPoza['coperta'] === null);

@unlink($dosarCop . '/' . $numeSursa . '.jpg');
db()->exec('DELETE FROM evenimente');

/* ============ CATEGORIILE ȚINUTE PENTRU CASĂ („FindMe") ============= */

echo "\n=== CATEGORIA DOAR PENTRU STAFF ===\n";

/**
 * Prima e „FindMe": jocul cu coduri QR ascunse prin oraș. Evenimentele lui nu
 * le propune nimeni, le pune casa — deci categoria n-are ce căuta în lista din
 * care alege omul obișnuit.
 *
 * Ascunsă e CATEGORIA, ca alegere. Evenimentele din ea se văd ca oricare
 * altele: altfel n-ar avea cine să caute codurile.
 */
db()->prepare('DELETE FROM categorii WHERE slug = ?')->execute(['tst-cat-casa']);
db()->prepare('INSERT INTO categorii (nume, slug, ordine, doar_staff) VALUES (?,?,?,1)')
    ->execute(['Doar pentru casă', 'tst-cat-casa', 98]);
$idCasa = (int) db()->lastInsertId();

$slugurile = static fn(array $c): array =>
    array_map(static fn(array $x): string => (string) $x['slug'], $c);

verifica('lista obișnuită n-o are', false,
    in_array('tst-cat-casa', $slugurile(categoriiEvenimente()), true));
verifica('cea a staff-ului, da', true,
    in_array('tst-cat-casa', $slugurile(categoriiEvenimente(true)), true));

verifica('omul obișnuit n-are id-ul ei', false,
    in_array($idCasa, idCategoriiValide(), true));
verifica('staff-ul îl are', true, in_array($idCasa, idCategoriiValide(true), true));

/**
 * De id-uri atârnă cine POATE PUBLICA acolo: nu e de ajuns că lista din
 * formular n-o arată — cine scrie numărul de mână în cerere trebuie respins.
 */
$campuriCasa = static fn(): array => [
    'titlu'            => 'Ceva pus la cale de casă',
    'categorie_id'     => (string) $idCasa,
    'oras'             => 'Roman',
    'locatie'          => 'Piața Sfatului, lângă fântână',
    'data_eveniment'   => date('d-m-Y', strtotime('+10 days')),
    'ora_inceput'      => '19:00',
    'fara_ora_sfarsit' => '1',
    'gratuit'          => '1',
    'varsta_minima'    => 'nespecificat',
    'gen_participanti' => 'nespecificat',
    'fara_participanti_min' => '1',
    'fara_participanti_max' => '1',
    'descriere'        => str_repeat('Povestea evenimentului de casă. ', 12),
];

verifica('cu lista omului, categoria e respinsă', true,
    !empty(verificaEveniment($campuriCasa(), idCategoriiValide(), ['Roman'])['erori']['categorie_id']));
verifica('cu lista staff-ului, trece', [],
    verificaEveniment($campuriCasa(), idCategoriiValide(true), ['Roman'])['erori']);

/**
 * În filtrele de pe prima pagină intră ca oricare alta, pentru toată lumea.
 * `doar_staff` spune cine poate PUBLICA acolo, nu cine poate căuta: dacă
 * evenimentele ei se văd în listă, trebuie să se poată și filtra după ea.
 */
verifica('goală, nu intră în filtre (ca oricare alta)', false,
    in_array('tst-cat-casa', $slugurile(categoriiCuEvenimente()), true));

$idCasaEv = pune($idOrg, 'Un eveniment de casă', 'aprobat', 7);
db()->prepare('UPDATE evenimente SET categorie_id = ? WHERE id = ?')->execute([$idCasa, $idCasaEv]);

verifica('cu un eveniment în ea, intră în filtre', true,
    in_array('tst-cat-casa', $slugurile(categoriiCuEvenimente()), true));

// Iar slugul ei din adresă chiar filtrează — altfel butonul n-ar face nimic.
verifica('slugul ei din adresă e primit', 'tst-cat-casa', categoriaCeruta('tst-cat-casa'));
verifica('și filtrarea îl scoate', 1,
    count(evenimenteDePePrima('', 'tst-cat-casa', 0, 10)['evenimente']));

db()->exec('DELETE FROM evenimente');
db()->prepare('DELETE FROM categorii WHERE id = ?')->execute([$idCasa]);
verifica('am făcut curat după categoria de casă', 0, cateEvenimente());

/* ============ AL DOILEA ANUNȚ CU ACELAȘI NUME („#2") ============== */

echo "\n=== TITLURI CARE SE REPETĂ ===\n";

/**
 * Cine pune „Fotbal în seara asta" a doua oară primește „#2". Numai la
 * evenimente NOI, și numai în dreptul aceluiași om.
 */
db()->exec('DELETE FROM evenimente');

verifica('coada se taie', 'Fotbal', titluFaraNumar('Fotbal #12'));
verifica('și cu spații în plus', 'Fotbal', titluFaraNumar('  Fotbal #3  '));
verifica('fără coadă, rămâne cum e', 'Fotbal', titluFaraNumar('Fotbal'));

/**
 * Un diez la MIJLOC nu e o numărătoare de-a noastră, e ce a vrut omul să
 * spună. Se taie doar coada, și doar dacă arată exact așa.
 */
verifica('diezul de la mijloc rămâne', 'Sala #3 la ora 8', titluFaraNumar('Sala #3 la ora 8'));
verifica('și „#" fără cifre rămâne',   'Fotbal #',         titluFaraNumar('Fotbal #'));

$faUnEveniment = static function (int $cine, string $titlu) use (&$idCategorie): string {
    $curat = [
        'titlu' => $titlu, 'categorie_id' => 1, 'oras' => 'Roman',
        'locatie' => 'Undeva prin oraș', 'descriere' => str_repeat('Povestea lui. ', 30),
        'data_eveniment' => date('Y-m-d', strtotime('+9 days')), 'ora_inceput' => '19:00',
        'ora_sfarsit' => null, 'cost' => null, 'varsta_minima' => null,
        'participanti_min' => null, 'participanti_max' => null,
        'gen_participanti' => 'nespecificat',
    ];

    salveazaEveniment($cine, $curat, null, true);

    $q = db()->prepare('SELECT titlu FROM evenimente WHERE membru_id = ? ORDER BY id DESC LIMIT 1');
    $q->execute([$cine]);

    return (string) $q->fetchColumn();
};

verifica('primul rămâne cum l-a scris', 'Fotbal în seara asta',
    $faUnEveniment($idOrg, 'Fotbal în seara asta'));
verifica('al doilea ia „#2"', 'Fotbal în seara asta #2',
    $faUnEveniment($idOrg, 'Fotbal în seara asta'));
verifica('al treilea ia „#3"', 'Fotbal în seara asta #3',
    $faUnEveniment($idOrg, 'Fotbal în seara asta'));

// Cine scrie el coada nu cere un al doilea „#2": cere un „Fotbal…", iar
// numărul îl punem noi. Altfel s-ar fi ajuns la „Fotbal #2 #2".
verifica('coada scrisă de om nu se dublează', 'Fotbal în seara asta #4',
    $faUnEveniment($idOrg, 'Fotbal în seara asta #2'));

verifica('alt titlu pornește curat', 'Volei duminică',
    $faUnEveniment($idOrg, 'Volei duminică'));

/**
 * ÎN DREPTUL FIECĂRUI OM. Doi vecini care pun amândoi „Fotbal în seara asta"
 * scriu despre două seri deosebite; un „#2" pus celui de-al doilea l-ar fi
 * făcut să pară continuarea unui anunț pe care nu l-a scris.
 */
$idVecin = faMembru('vecin-titluri-test@exemplu-test.ro', 'organizat11');
verifica('la alt om, fără număr', 'Fotbal în seara asta',
    $faUnEveniment($idVecin, 'Fotbal în seara asta'));

/**
 * Se numără din TOATE, oricare le-ar fi starea. Tocmai cele încheiate și cele
 * anulate sunt „din trecut": scoase din socoteală, anunțul de azi ar fi purtat
 * același nume cu unul de pe profilul omului.
 */
db()->prepare('UPDATE evenimente SET stare_moderare = \'anulat\' WHERE membru_id = ?')
    ->execute([$idOrg]);

verifica('și cele anulate se numără', 'Fotbal în seara asta #5',
    $faUnEveniment($idOrg, 'Fotbal în seara asta'));

/**
 * Numărul următor vine din CEL MAI MARE, nu din câte rânduri sunt: dacă „#2"
 * se șterge de mână din phpMyAdmin, al șaselea rămâne „#6". Două anunțuri cu
 * același nume, la același om, sunt tocmai ce evităm.
 */
db()->prepare('DELETE FROM evenimente WHERE membru_id = ? AND titlu = ?')
    ->execute([$idOrg, 'Fotbal în seara asta #2']);

verifica('un rând șters nu întoarce numărătoarea', 'Fotbal în seara asta #6',
    $faUnEveniment($idOrg, 'Fotbal în seara asta'));

// `%` din titlu ar fi fost joker în LIKE: „100%% reducere" ar fi potrivit orice
// titlu care începe cu „100".
$faUnEveniment($idOrg, '100% distracție');
verifica('procentul nu e joker', '100% distracție #2',
    $faUnEveniment($idOrg, '100% distracție'));
verifica('și nu prinde alte titluri', '100 de motive',
    $faUnEveniment($idOrg, '100 de motive'));

db()->exec('DELETE FROM evenimente');
db()->prepare('DELETE FROM membri WHERE id = ?')->execute([$idVecin]);
verifica('am făcut curat după titluri', 0, cateEvenimente());

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
verifica('sub titlul secțiunii de ieșiri', true, str_contains($pagina, 'Ieșiri organizate'));
verifica('și niciunul în așteptare', 0, $catePe($pagina, 'card--in-asteptare'));
verifica('titlul unui eveniment în așteptare nu se scurge', false,
    str_contains($pagina, 'Aștept de puțin'));
// Butonul evenimentelor, nu oricare: pagina are și unul pentru evaluări, care
// stă mereu în HTML (ascuns) și n-are treabă cu lista de cartonașe.
verifica('fără buton „Vezi mai mult", că nu e nimic ascuns', false,
    str_contains($pagina, 'id="evenimente-mai-mult"'));

$pagina = cerere($baza . '/profil.php', $c)['corp'];

verifica('proprietarul primește toate șase', 6, $catePe($pagina, '<article class="card'));
verifica('sub același titlu de secțiune', true, str_contains($pagina, 'Ieșiri organizate'));
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
    str_contains($pagina, 'href="/adauga_eveniment.php"'));

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

$laEveniment = static fn(int $id): string => $baza . urlEveniment($slugul($id));

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
$rInexistent = cerere($baza . '/eveniment/nu-exista-abc123', $anonim);
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
    cerere($baza . '/eveniment/nu-exista-abc123', $c)['stare']);
verifica('fără slug → prima pagină', 302, cerere($baza . '/event.php', $c)['stare']);

foreach ([
    'injecție SQL' => "' OR 1=1 --",
    'cale de fișier' => '../../inc/config.php',
    'majuscule'      => 'CURSA-APROBATA-ABC',
    'semne'          => '<script>alert(1)</script>',
] as $ce => $slugRau) {
    /**
     * Pe adresa VECHE, cu întrebare: acolo se scrie un slug de mână, și acolo
     * ar încerca cineva. Adresa frumoasă nici nu se poate forma cu semnele
     * astea — tiparul din .htaccess primește doar litere mici, cifre și
     * cratime, deci un slug strâmb se oprește la serverul web, înainte de PHP.
     * Aici se probează cealaltă ușă, care e tot deschisă.
     */
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
 * config: „/eveniment/…" singur n-ar duce nicăieri de pe telefonul
 * altcuiva.
 */
$adresaIntreaga = rtrim((string) ($config['url_site'] ?? ''), '/')
                . urlEveniment($slugul($idAprobat));

verifica('are zona de distribuire', true, str_contains($pagina, 'post__share'));
verifica('cu link de Facebook, pe adresa întreagă', true,
    str_contains($pagina, 'facebook.com/sharer/sharer.php?u=' . h(urlencode($adresaIntreaga))));
verifica('cu link de WhatsApp', true, str_contains($pagina, 'wa.me/?text='));
verifica('care poartă și titlul, nu doar adresa', true,
    str_contains($pagina, urlencode('Uite ce eveniment am găsit pe Pulsul Orașului: Cursa aprobată')));
verifica('și cu butonul de copiat', true, str_contains($pagina, 'id="copiaza-link"'));
verifica('care are textul gata scris, escapat', true,
    str_contains($pagina, 'data-copiaza="Uite ce eveniment am găsit pe Pulsul Orașului: Cursa aprobată '
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
 * ORGANIZATORUL CARE ȘI-A ȘTERS CONTUL.
 *
 * Rândul lui rămâne în `membri` — de el atârnă anunțul — dar omul din el s-a
 * golit. Antetul scria mai departe „Ș. Utilizator", adică o prescurtare care
 * arată ca un nume adevărat, și ducea la un profil gol.
 *
 * Se probează întâi pe uscat, pe evenimentDinBaza(): acolo se ia hotărârea, iar
 * un rând scris de mână spune limpede de ce anume atârnă ea.
 */
require_once __DIR__ . '/../inc/afisare-eveniment.php';

$randOrg = [
    'org_nume' => 'Popescu', 'org_prenume' => 'Mihai',
    'org_permalink' => 'abcdefghij', 'org_poza' => str_repeat('a', 32),
];

$viu = evenimentDinBaza($randOrg + ['org_stare' => 'activ']);
verifica('contul viu își păstrează numele',  'P. Mihai', $viu['organizator']);
verifica('și legătura spre profil',          true, $viu['organizator_url'] !== '');
verifica('și chipul',                        true, $viu['organizator_poza'] !== null);

$dus = evenimentDinBaza($randOrg + ['org_stare' => 'sters']);
verifica('contul șters scrie „Utilizator șters"', 'Utilizator șters', $dus['organizator']);
verifica('fără legătură spre profil',            '',   $dus['organizator_url']);
/* Poza se stinge aici, nu doar pe disc: un rând golit de mână din phpMyAdmin
   ar fi rămas altfel cu chipul pe el. */
verifica('și fără chip, chiar dacă rândul mai are unul', null, $dus['organizator_poza']);

/**
 * CELE TREIZECI DE ZILE DE RĂGAZ NU SCHIMBĂ NIMIC. Contul e întreg, iar simpla
 * intrare în el anulează ștergerea — un anunț care și-ar pierde organizatorul
 * în ziua apăsării butonului i-ar lăsa pe cei înscriși fără să știe cu cine se
 * întâlnesc.
 */
$inRagaz = evenimentDinBaza($randOrg + ['org_stare' => 'activ', 'org_cerere_stergere' => acum()]);
verifica('în răgaz, organizatorul e tot el', 'P. Mihai', $inRagaz['organizator']);

/**
 * Și acum prin HTTP, pe pagina adevărată. Starea se pune la loc imediat.
 *
 * SE CERE CU ALT BORCAN DE COOKIE-URI, nu cu al organizatorului: starea
 * contului se citește din bază la FIECARE cerere, iar una făcută cu sesiunea
 * lui cât e „sters" i-ar fi stins-o — și tot ce vine mai jos în proba asta se
 * sprijină pe ea. Un anunț aprobat se vede oricum de oricine.
 */
$vizitator = [];
db()->prepare('UPDATE membri SET stare = "sters" WHERE id = ?')->execute([$idOrg]);
$paginaSters = cerere($laEveniment($idAprobat), $vizitator)['corp'];
db()->prepare('UPDATE membri SET stare = "activ" WHERE id = ?')->execute([$idOrg]);

verifica('pe pagină scrie „Utilizator șters"', true,
    str_contains($paginaSters, '>Utilizator șters<'));
verifica('și nu mai duce la profilul lui', false,
    str_contains($paginaSters, 'profil.php?m=organizat02'));
verifica('iar chipul e silueta implicită', true,
    str_contains($paginaSters, 'post__avatar" src="/assets/img/avatars/implicit.svg'));

// Pusă starea la loc, totul se întoarce cum era.
verifica('starea pusă la loc, numele se întoarce', true,
    str_contains(cerere($laEveniment($idAprobat), $c)['corp'], 'profil.php?m=organizat02'));

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
    str_contains($pagina, 'href="/adauga_eveniment.php?slug=' . $slugul($idAprobat) . '"'));

$paginaAltul = cerere($laEveniment($idAprobat), $altul)['corp'];
verifica('altcineva nu primește butonul de editare', false,
    str_contains($paginaAltul, 'post__editeaza'));

$paginaAsteapta = cerere($laEveniment($idAsteapta), $c)['corp'];
verifica('starea se scrie pe pagină', true,
    str_contains($paginaAsteapta, 'Se așteaptă aprobarea din partea unui moderator.'));
verifica('și nu se lasă indexată', true, str_contains($paginaAsteapta, 'name="robots"'));

$paginaRespins = cerere($laEveniment($idRespins), $c)['corp'];
verifica('respinsul își spune starea altfel', true,
    str_contains($paginaRespins, 'Anunțul nu a fost aprobat de moderatori.'));

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

/**
 * Că `<option value="1">` e cel bifat — fără să ne agățăm de spațierea din
 * pagină. Prima scriere cerea o potrivire literală, cu tot cu rândul nou și
 * cele 22 de spații dinaintea lui „selected": a picat de îndată ce în
 * `<option>` a mai intrat un atribut (`data-joc-qr`), deși pagina era
 * neschimbată. Un test care se sperie de o îndreptare de indentare nu apără
 * nimic, doar dă alarme false.
 */
verifica('categoria e aleasă', true,
    preg_match('/<option value="1"[^>]*\bselected\b/', $formular) === 1);
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
    urlEveniment($slugDeSchimbat), $r['url'] ?? '');

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
/**
 * Înapoi pe pagina evenimentului, nu pe profil: anunțul rămâne la vedere, cu
 * banda de „anulat" și cu motivul dedesubt — adică exact dovada că apăsarea a
 * mers, și exact ce vor citi oamenii care intră după el.
 */
verifica('și e trimis înapoi la anunț', urlEveniment($slugDeAnulat), $r['redirect'] ?? '');
verifica('cu mesajul cerut', 'Am anulat evenimentul.', $r['mesaj'] ?? '');

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

/**
 * TOATĂ LUMEA, ca la unul încheiat.
 *
 * A stat ascuns o vreme și era greșit: de el atârnă oameni care își făcuseră
 * planuri, iar o pagină care dispare îi lasă cu un link mort și cu întrebarea
 * dacă n-au greșit ei ziua. Acum se deschide, cu banda ei și cu motivul scris
 * de organizator la vedere.
 */
$rOrg = cerere($baza . urlEveniment($slugDeAnulat), $c);
verifica('organizatorul deschide pagina', 200, $rOrg['stare']);
verifica('cu banda de anulat', true, str_contains($rOrg['corp'], 'stare-anunt--anulat'));

/**
 * Mesajul pus în sesiune se arată o singură dată, pe PRIMA pagină de după
 * apăsare — iar aceea e chiar pagina evenimentului, unde îl trimite API-ul.
 */
verifica('cu mesajul de o singură dată', true,
    str_contains($rOrg['corp'], 'data-mesaj="Am anulat evenimentul."'));

$rStrain = cerere($baza . urlEveniment($slugDeAnulat), $altul);
verifica('și un alt membru, la fel', 200, $rStrain['stare']);
verifica('cu motivul scris pe ea', true,
    str_contains($rStrain['corp'], 'Ne vedem la primăvară'));
verifica('rândurile motivului se păstrează', true,
    str_contains($rStrain['corp'], '<br'));

// Chiar și cine nu e conectat deloc: e o veste publică, nu o socoteală internă.
verifica('și vizitatorul fără cont', 200,
    cerere($baza . urlEveniment($slugDeAnulat), $anonim)['stare']);

/* Ce NU se mai poate acolo: nici editare, nici înscriere, nici comentarii. */
verifica('formularul lui de editare rămâne închis', 302,
    cerere($baza . '/adauga_eveniment.php?slug=' . urlencode($slugDeAnulat), $c)['stare']);
verifica('fără butonul de editare', false, str_contains($rOrg['corp'], 'post__editeaza'));
verifica('fără caseta de interes', false, str_contains($rOrg['corp'], 'id="rsvp"'));
verifica('și fără zona de anulare — s-a anulat deja', false,
    str_contains($rOrg['corp'], 'data-anulare'));

/* ------------- anulatul nu mai apare pe nicăieri ------------- */

verifica('nu mai e pe profilul organizatorului', [], evenimenteDePeProfil($idOrg, true));
verifica('nici pentru ceilalți', [], evenimenteDePeProfil($idOrg, false));
verifica('nu se numără la „evenimente organizate"', 0, cateEvenimenteOrganizate($idOrg));
verifica('și nu mai ține pe nimeni blocat', [], evenimenteActive($idOrg));

$profilCorp = cerere($baza . '/profil.php', $c)['corp'];
verifica('nu apare niciun cartonaș pe profil', 0, substr_count($profilCorp, '<article class="card'));

// ...și nu se mai repetă nicăieri după aceea.
verifica('mesajul nu se mai repetă', false,
    str_contains($profilCorp, 'Evenimentul a fost anulat.'));

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

/* ------------------- coada „ #2", ca la publicare -------------------- */

/**
 * Cine are deja „Cursa de seară prin centrul vechi" și scrie încă unul la fel
 * primește, la publicare, „… #2" — o pune titluCuNumar(). PREVIZUALIZAREA
 * N-O PUNEA: omul vedea aici un titlu și pe prima pagină altul, iar o
 * previzualizare care nu spune adevărul despre exact lucrul pe care îl arată e
 * mai rea decât niciuna.
 */
verifica('la primul, titlul e curat', true,
    str_contains($pagina['corp'], 'Cursa de seară prin centrul vechi'));
verifica('fără nicio coadă', false, str_contains($pagina['corp'], 'centrul vechi #'));

/**
 * Se publică unul cu numele ăsta, și se previzualizează încă unul la fel.
 *
 * Limita de evenimente active i se ridică omului: aici se probează numărul din
 * titlu, nu numărătoarea de anunțuri, iar cu limita obișnuită al doilea nici
 * n-ar fi apucat să se scrie.
 */
$limitaDeDinainte = (int) db()->query(
    'SELECT limita_evenimente_active FROM membri WHERE id = ' . $idOrg)->fetchColumn();

db()->prepare('UPDATE membri SET limita_evenimente_active = 20 WHERE id = ?')->execute([$idOrg]);

verifica('primul chiar s-a publicat', true, !empty(trimite($c)['ok']));
verifica('cu titlul curat', 'Cursa de seară prin centrul vechi',
    (string) (ultimulEveniment()['titlu'] ?? ''));

$pAlDoilea = cerere($baza . '/previzualizare.php?p='
    . urlencode((string) previzualizeaza($c)['cheie']), $c)['corp'];

verifica('al doilea se previzualizează cu „ #2"', true,
    str_contains($pAlDoilea, 'Cursa de seară prin centrul vechi #2'));

/* Și chiar așa se și publică — cele două trebuie să spună la fel. */
trimite($c);
$alDoilea = ultimulEveniment();
verifica('iar publicat, tot „ #2"', 'Cursa de seară prin centrul vechi #2',
    (string) ($alDoilea['titlu'] ?? ''));

/* Al treilea: „#3" în amândouă locurile. */
$pAlTreilea = cerere($baza . '/previzualizare.php?p='
    . urlencode((string) previzualizeaza($c)['cheie']), $c)['corp'];
verifica('al treilea, „ #3"', true,
    str_contains($pAlTreilea, 'Cursa de seară prin centrul vechi #3'));

/**
 * LA EDITARE, NU. Titlul e deja numerotat, iar o a doua trecere l-ar fi urcat
 * cu unu la fiecare virgulă îndreptată — aceeași grijă ca în
 * actualizeazaEveniment(), care tocmai de aceea nu cheamă titluCuNumar().
 */
$pEditare = cerere($baza . '/previzualizare.php?p=' . urlencode((string) previzualizeaza($c, [
    'slug'  => (string) ($alDoilea['slug'] ?? ''),
    'titlu' => 'Cursa de seară prin centrul vechi #2',
])['cheie']), $c)['corp'];

verifica('la editare, titlul rămâne cum e', true,
    str_contains($pEditare, 'Cursa de seară prin centrul vechi #2'));
verifica('și nu urcă la „ #3"', false,
    str_contains($pEditare, 'Cursa de seară prin centrul vechi #3'));

/* ------------- poza categoriei, când omul n-a ales una ------------- */

/**
 * UN ANUNȚ FĂRĂ COPERTĂ NU RĂMÂNE FĂRĂ POZĂ pe site: primește imaginea
 * categoriei (vezi evenimentDinBaza). Previzualizarea n-o punea, deci arăta
 * un anunț gol în cap — adică altceva decât ce urma să apară, exact lucrul
 * pe care previzualizarea are datoria să nu-l facă.
 *
 * Proba își pune singură o poză de categorie și o ia de acolo la sfârșit:
 * în baza de dezvoltare coloana e goală la toate categoriile, iar fără ea
 * n-ar fi avut ce să se vadă.
 */
$dosarCat = __DIR__ . '/../assets/img/categorii';
$pozaCat  = $dosarCat . '/tstcat-proba.jpg';
$eraCat   = db()->query('SELECT imagine_default FROM categorii WHERE id = 1')->fetchColumn();

@mkdir($dosarCat, 0775, true);
$im = imagecreatetruecolor(40, 24);
imagejpeg($im, $pozaCat, 70);
imagedestroy($im);

db()->prepare('UPDATE categorii SET imagine_default = ? WHERE id = 1')
    ->execute(['tstcat-proba.jpg']);

$fara = previzualizeaza($c);
$pFara = cerere($baza . '/previzualizare.php?p=' . urlencode((string) $fara['cheie']), $c)['corp'];

verifica('fără copertă, se vede poza categoriei', true,
    str_contains($pFara, '/assets/img/categorii/tstcat-proba.jpg'));

/**
 * DAR NU TRECE ÎNAINTEA POZEI OMULUI. Când formularul spune că s-a ales una
 * nouă („coperta_noua"), pagina lasă un loc gol pe care îl umple JS-ul din
 * fila-mamă; poza casei acolo ar fi acoperit tocmai ce alesese omul.
 */
$cuNoua = previzualizeaza($c, ['coperta_noua' => '1']);
$pCuNoua = cerere($baza . '/previzualizare.php?p=' . urlencode((string) $cuNoua['cheie']), $c)['corp'];

verifica('cu poză aleasă, categoria nu se bagă', false,
    str_contains($pCuNoua, '/assets/img/categorii/tstcat-proba.jpg'));
verifica('ci se lasă locul pentru poza din browser', true,
    str_contains($pCuNoua, 'data:image/gif;base64,'));

/**
 * Iar o categorie FĂRĂ poză pe disc rămâne fără poză, ca înainte — nu cu o
 * adresă care dă 404. urlImagineCategorie() se uită și pe disc.
 */
db()->prepare('UPDATE categorii SET imagine_default = ? WHERE id = 1')
    ->execute(['tstcat-lipsa.jpg']);

$lipsa = previzualizeaza($c);
$pLipsa = cerere($baza . '/previzualizare.php?p=' . urlencode((string) $lipsa['cheie']), $c)['corp'];

verifica('o poză de categorie lipsă nu lasă o adresă moartă', false,
    str_contains($pLipsa, 'tstcat-lipsa.jpg'));

@unlink($pozaCat);
db()->prepare('UPDATE categorii SET imagine_default = ? WHERE id = 1')->execute([$eraCat]);

/**
 * Curat pentru ce urmează: probele de mai jos numără de la zero, iar una
 * dintre ele probează chiar limita de evenimente active — care trebuie să fie
 * cea dinainte, nu cea ridicată aici.
 */
db()->exec('DELETE FROM evenimente');
db()->prepare('UPDATE membri SET limita_evenimente_active = ? WHERE id = ?')
    ->execute([$limitaDeDinainte, $idOrg]);

$rPreviz = previzualizeaza($c);
$cheie   = (string) $rPreviz['cheie'];
$pagina  = cerere($baza . '/previzualizare.php?p=' . urlencode($cheie), $c);

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

    if (str_contains($p, 'id="prev-coperta"')) { return 'browser'; }

    // Poza pusă de casă pentru categorie, nu una aleasă de om.
    return str_contains($p, '/assets/img/categorii/') ? 'categorie' : 'bd';
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

/**
 * 3. NIMIC ALES DE OM → poza categoriei, dacă are una; altfel, nimic.
 *
 * Amândouă stările se pun ANUME din probă, nu se iau cum s-au nimerit în
 * bază: `imagine_default` e goală în baza de dezvoltare și plină pe site,
 * deci o probă care s-ar bizui pe ce găsește ar spune altceva în fiecare
 * loc. Se pune la loc la sfârșit ce era.
 */
$dosarCat2 = __DIR__ . '/../assets/img/categorii';
$pozaCat2  = $dosarCat2 . '/tstcat-fig.jpg';
$eraCat2   = db()->query('SELECT imagine_default FROM categorii WHERE id = 1')->fetchColumn();

/* a) categoria N-ARE poză → pagina rămâne fără figură, ca înainte */
db()->prepare('UPDATE categorii SET imagine_default = NULL WHERE id = 1')->execute();

verifica('fără nicio poză, nici la categorie: nicio figură', 'fara',
    $vedeCoperta(previzualizeaza($c)));
verifica('editare fără copertă, categorie fără poză: nimic', 'fara',
    $vedeCoperta(previzualizeaza($c, ['slug' => $slugDeSchimbat])));

/* b) categoria ARE poză → ea se vede, și la creare, și la editare */
@mkdir($dosarCat2, 0775, true);
$imFig = imagecreatetruecolor(40, 24);
imagejpeg($imFig, $pozaCat2, 70);
imagedestroy($imFig);
db()->prepare('UPDATE categorii SET imagine_default = ? WHERE id = 1')
    ->execute(['tstcat-fig.jpg']);

verifica('fără copertă, dar categoria are poză: a ei', 'categorie',
    $vedeCoperta(previzualizeaza($c)));
verifica('și la editarea unuia fără copertă, tot a ei', 'categorie',
    $vedeCoperta(previzualizeaza($c, ['slug' => $slugDeSchimbat])));

/* …dar niciodată peste ce a ales omul. */
verifica('poza omului rămâne deasupra celei de categorie', 'browser',
    $vedeCoperta(previzualizeaza($c, ['coperta_noua' => '1'])));
verifica('și coperta salvată rămâne deasupra ei', 'bd',
    $vedeCoperta(previzualizeaza($c, ['slug' => $slugCuPoza])));

@unlink($pozaCat2);
db()->prepare('UPDATE categorii SET imagine_default = ? WHERE id = 1')->execute([$eraCat2]);

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

/**
 * CELE CARE ABIA URMEAZĂ NU SE NUMĂRĂ. Cifra spune cât a ȚINUT omul pentru
 * oraș, iar un anunț pus ieri pentru săptămâna viitoare nu e încă nimic ținut
 * — se poate și anula până atunci. Cât se numărau, ajungea să scrii un anunț
 * ca să-ți crească cifra de pe profil.
 */
pune($idOrg, 'Aprobat, peste o lună', 'aprobat', 30);
pune($idOrg, 'Aprobat, mâine',        'aprobat', 1);
verifica('cele care urmează nu se numără', 0, cateEvenimenteOrganizate($idOrg));

/**
 * Trecutul, în schimb, rămâne: ce a făcut omul nu se șterge când trece ziua.
 * Exact pe dos față de lista de dedesubt, care arată doar ce urmează.
 */
pune($idOrg, 'Aprobat, de acum un an',    'aprobat', -365);
pune($idOrg, 'Aprobat, de săptămâna trecută', 'aprobat', -7);
verifica('cele din trecut se numără', 2, cateEvenimenteOrganizate($idOrg));

/**
 * ȘI CEL ÎNCHEIAT CU MÂNA, oricât ar arăta calendarul — altfel cifra ar fi
 * scăzut chiar în clipa în care organizatorul apasă „Încheie evenimentul",
 * adică exact când a terminat treaba.
 */
pune($idOrg, 'Încheiat devreme', 'incheiat', 20);
verifica('și cel încheiat cu mâna', 3, cateEvenimenteOrganizate($idOrg));

pune($idOrg, 'Anulat, deși a trecut', 'anulat', -3);
verifica('dar nu și cel anulat', 3, cateEvenimenteOrganizate($idOrg));

pune($idOrg, 'Încă neaprobat', 'in_asteptare', 5);
pune($idOrg, 'Respins',        'respins',      6);
verifica('nici ce așteaptă moderarea', 3, cateEvenimenteOrganizate($idOrg));

pune($idAltul, 'Al altuia, din trecut', 'aprobat', -3);
verifica('nici evenimentele altcuiva', 3, cateEvenimenteOrganizate($idOrg));
verifica('fiecare cu numărul lui', 1, cateEvenimenteOrganizate($idAltul));

/* ---- și cifra din pagină, care e alta decât lungimea listei de sub ea ---- */

$pagina = cerere($baza . '/profil.php', $c)['corp'];

preg_match('/stat__value">(\d+)</', $pagina, $m);
verifica('cifra din pagină e cea numărată', '3', $m[1] ?? '');

/**
 * Lista de dedesubt arată ALTCEVA, și aproape pe dos: cele două aprobate care
 * urmează. Cele în așteptare le vede doar omul însuși — aici e chiar profilul
 * lui, deci intră și ele. Tocmai de-aia numărul nu se poate lua din lungimea
 * listei: una spune ce a fost, cealaltă unde se mai poate ajunge.
 */
verifica('lista de dedesubt are trei cartonașe', 3, substr_count($pagina, '<article class="card'));

$paginaDinAfara = cerere($baza . '/profil.php?m=organizat02', $altul)['corp'];
preg_match('/stat__value">(\d+)</', $paginaDinAfara, $m);
verifica('din afară se vede aceeași cifră', '3', $m[1] ?? '');

/* ---------------- cartonașele de pe profil duc la pagină ---------------- */

db()->exec('DELETE FROM evenimente');
$idAprobat = pune($idOrg, 'Cursa aprobată', 'aprobat', 7);

$pagina = cerere($baza . '/profil.php', $c)['corp'];
verifica('cartonașele de pe profil trimit la event.php', true,
    str_contains($pagina, 'href="/eveniment/' . $slugul($idAprobat) . '"'));

/* -------------- „+ Eveniment nou", pe profilul propriu ----------------- */

/**
 * Butonul se vedea doar în locul gol, adică exact la cine n-avea niciun
 * eveniment. Acum stă în capul secțiunii și când lista are ceva în ea — dar
 * tot numai pe profilul propriu, și tot unul singur.
 */
verifica('cu evenimente în listă, butonul e acolo', 1,
    substr_count($pagina, 'href="/adauga_eveniment.php"'));
verifica('și stă în capul secțiunii', true,
    preg_match('/<div class="section-head">.*?href="\/adauga_eveniment\.php".*?<\/div>\s*<\/div>/s', $pagina) === 1);

verifica('pe profilul altcuiva, niciun buton', 0,
    substr_count($paginaDinAfara = cerere($baza . '/profil.php?m=organizat02', $altul)['corp'],
                 'href="/adauga_eveniment.php"'));

// Fără niciun eveniment rămâne invitația din locul gol — tot un buton, nu doi.
db()->exec('DELETE FROM evenimente');
$paginaGoala = cerere($baza . '/profil.php', $c)['corp'];
verifica('fără evenimente, tot un singur buton', 1,
    substr_count($paginaGoala, 'href="/adauga_eveniment.php"'));
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
verifica('cu mesajul cerut', 'S-au ocupat toate locurile la acest eveniment.', $r['mesaj'] ?? '');
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

$pagina = cerere($baza . urlEveniment($slugEv), $altul)['corp'];

verifica('pagina arată numerele adevărate', true,
    preg_match('/data-count-for="participant"[^>]*>2</', $pagina) === 1);
verifica('și butonul apăsat e însemnat ca atare', true,
    preg_match('/id="btn-going"[^>]*aria-pressed="true"/s', $pagina) === 1);
verifica('caseta de confirmare e în pagină, ascunsă', true,
    preg_match('/id="rsvp-confirm"[^>]*hidden/', $pagina) === 1);
verifica('spune ce vede organizatorul', true,
    str_contains($pagina, 'numele întreg și numărul de telefon'));
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
$pustiu = cerere($baza . urlEveniment($slugul($idPustiu)), $altul)['corp'];
verifica('fără nimeni, o invitație', true,
    str_contains($pustiu, 'Fii primul care se arată interesat!'));
verifica('fără cercuri goale', false, str_contains($pustiu, 'class="facepile"'));

// La unul neaprobat, secțiunea lipsește cu totul.
$asteapta = cerere($baza . urlEveniment($slugul($idAstept)), $c)['corp'];
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

$paginaOg = cerere($baza . urlEveniment($slugOg), $anonim)['corp'];
$siteOg   = rtrim((string) ($config['url_site'] ?? ''), '/');

verifica('og:title e titlul evenimentului', 'Un eveniment de dat mai departe',
    $og($paginaOg, 'og:title'));
verifica('og:type e „article"', 'article', $og($paginaOg, 'og:type'));
verifica('og:url e adresa întreagă a paginii',
    $siteOg . urlEveniment($slugOg), $og($paginaOg, 'og:url'));
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

/**
 * Fără copertă și fără imagine de categorie pe disc: mai bine nicio poză decât
 * una care duce la 404.
 *
 * IMAGINEA CATEGORIEI SE STINGE ANUME pentru bucata asta, și se pune la loc
 * după. Proba se uita înainte la categoria din bază așa cum era — iar aceleia
 * i se poate pune oricând o imagine implicită de pe site. Atunci evenimentul
 * primea poza categoriei, pe bună dreptate, iar proba pica deși codul era în
 * regulă. Pe serverul adevărat, unde categoriile CHIAR au imagini, ar fi picat
 * mereu.
 */
$catOg = (int) db()->query('SELECT categorie_id FROM evenimente WHERE id = ' . $idOg)
                   ->fetchColumn();
$imgCatOg = db()->query('SELECT imagine_default FROM categorii WHERE id = ' . $catOg)
                ->fetchColumn();

db()->prepare('UPDATE categorii SET imagine_default = NULL WHERE id = ?')->execute([$catOg]);
db()->prepare('UPDATE evenimente SET coperta = NULL WHERE id = ?')->execute([$idOg]);

$faraPoza = cerere($baza . urlEveniment($slugOg), $anonim)['corp'];
verifica('fără copertă, fără og:image', '', $og($faraPoza, 'og:image'));
verifica('și twitter cere cartonașul mic', 'summary', $og($faraPoza, 'twitter:card'));

/* Imaginea categoriei, pusă la loc cum era. */
db()->prepare('UPDATE categorii SET imagine_default = ? WHERE id = ?')
    ->execute([$imgCatOg, $catOg]);

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
$laTrecut   = $baza . urlEveniment($slugTrecut);

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

/**
 * Caseta „Mergi la acest eveniment?" nu se stinge — pleacă.
 *
 * A fost o vreme desenată și la un eveniment trecut, cu butoanele stinse și
 * etichetele puse la trecut. Dar o casetă mare care întreabă asta deasupra a
 * ceva ce s-a terminat e o întrebare fără rost în cel mai vizibil loc al
 * paginii. Cine vrea să vadă cine a fost are taburile de dedesubt.
 */
verifica('caseta de participare nu mai există', false,
    str_contains($paginaTrecut['corp'], 'class="rsvp"'));
verifica('nici butonul „mă interesează"', false,
    str_contains($paginaTrecut['corp'], 'id="btn-interested"'));
verifica('nici cel de participare', false,
    str_contains($paginaTrecut['corp'], 'id="btn-going"'));
verifica('caseta de confirmare nici nu se scrie', false,
    str_contains($paginaTrecut['corp'], 'id="rsvp-confirm"'));
verifica('și nu-l mai cheamă să fie primul interesat', false,
    str_contains($paginaTrecut['corp'], 'Fii primul interesat'));

// Dar taburile rămân, cu etichetele puse la trecut: acolo se vede cine a fost.
verifica('taburile rămân', true, str_contains($paginaTrecut['corp'], 'id="tab-going"'));
verifica('cu eticheta la trecut', true,
    str_contains($paginaTrecut['corp'], '>Au participat</span>'));
// Iar „Interesați" a plecat cu totul: la un eveniment încheiat, lista aceea
// nu spune nimic despre seara care a fost — sunt oameni care s-au uitat
// într-acolo și n-au venit.
verifica('fără tabul „Interesați"', false,
    str_contains($paginaTrecut['corp'], 'id="tab-interested"'));
verifica('și fără panoul lui', false,
    str_contains($paginaTrecut['corp'], 'id="panel-interested"'));

// Numărătoarea rămâne: e istoria evenimentului, nu o invitație.
salveazaInteres($idTrecut, $idAltul, 'participant');
$paginaTrecut = cerere($laTrecut, $anonim)['corp'];
verifica('numărul celor care au fost rămâne afișat', true,
    preg_match('/data-count-for="participant"[^>]*>1</', $paginaTrecut) === 1);
verifica('și oamenii, la fel', true, str_contains($paginaTrecut, 'data-participant='));

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


echo "\n=== ÎNCHEIEREA UNUI EVENIMENT, ÎNAINTE DE VREME ===\n";

/** Cere încheierea evenimentului cu slugul dat. */
function incheie(array &$cookies, string $slug, ?string $token = null): array
{
    return json_din(cerere($GLOBALS['baza'] . '/api/incheie-eveniment.php', $cookies, [
        'csrf' => $token ?? csrf($cookies),
        'slug' => $slug,
    ]));
}

$idInch   = pune($idOrg, 'Unul pe care îl încheie el', 'aprobat', 12);
$slugInch = $slugul($idInch);
$laInch   = $baza . urlEveniment($slugInch);
$stareaLui = static fn(int $id): string => (string) db()->query(
    'SELECT stare_moderare FROM evenimente WHERE id = ' . $id)->fetchColumn();

/* --------------------- cele două feluri de a fi încheiat -------------- */

verifica('unul din viitor NU e încheiat', false,
    evenimentIncheiat(evenimentDupaSlug($slugInch)));
verifica('unul cu data trecută, da', true,
    evenimentIncheiat(['data_eveniment' => date('Y-m-d', strtotime('-1 day'))]));
verifica('și unul cu starea „incheiat", chiar dacă e în viitor', true,
    evenimentIncheiat(['data_eveniment' => date('Y-m-d', strtotime('+30 days')),
                       'stare_moderare' => 'incheiat']));
verifica('„aprobat" și „incheiat" sunt amândouă publicate', [true, true],
    [evenimentPublicat(['stare_moderare' => 'aprobat']),
     evenimentPublicat(['stare_moderare' => 'incheiat'])]);
verifica('dar nu și celelalte', [false, false, false],
    [evenimentPublicat(['stare_moderare' => 'in_asteptare']),
     evenimentPublicat(['stare_moderare' => 'respins']),
     evenimentPublicat(['stare_moderare' => 'anulat'])]);

/* --------------- doar după ce a început: ziua ȘI ora ----------------- */

/**
 * Ce nu s-a petrecut încă nu se poate încheia — ar fi un eveniment care apare
 * ca și cum ar fi avut loc, deși nimeni n-a fost nicăieri. Ce vrea
 * organizatorul atunci se cheamă anulare, are butonul lui și cere un motiv.
 *
 * Ceasul e al PHP-ului, ca peste tot: MySQL e pe alt fus (aici, trei ore
 * diferență), iar o probă scrisă cu NOW() ar fi „pus" ora peste două ore în
 * trecut. Vezi regula ceasului unic din CLAUDE.md.
 */
verifica('unul de peste cinci zile n-a început', false,
    evenimentAInceput(['data_eveniment' => date('Y-m-d', strtotime('+5 days')),
                       'ora_inceput'    => '19:00:00']));
/**
 * ZIUA ȘI ORA SE IAU DIN ACEEAȘI CLIPĂ, nu fiecare din alta.
 *
 * Erau scrise `date('Y-m-d')` și `date('H:i:00', strtotime('+2 hours'))` —
 * adică ziua de AZI lipită de o oră care, după 22:00, e de mâine. La 22:19,
 * „peste două ore" ieșea „azi la 00:19", adică acum douăzeci și două de ore în
 * URMĂ, iar proba pica în fiecare seară după zece. Un `strtotime` o dată, și
 * amândouă câmpurile din el.
 */
$pesteDouaOre = strtotime('+2 hours');
$acumOOra     = strtotime('-1 hour');

verifica('nici cel de peste două ore', false,
    evenimentAInceput(['data_eveniment' => date('Y-m-d', $pesteDouaOre),
                       'ora_inceput'    => date('H:i:00', $pesteDouaOre)]));
verifica('dar cel de acum o oră, da', true,
    evenimentAInceput(['data_eveniment' => date('Y-m-d', $acumOOra),
                       'ora_inceput'    => date('H:i:00', $acumOOra)]));
verifica('și unul de ieri', true,
    evenimentAInceput(['data_eveniment' => date('Y-m-d', strtotime('-1 day')),
                       'ora_inceput'    => '23:00:00']));

/**
 * ȘI UNUL ÎNCHEIAT, oricât ar arăta ceasul.
 *
 * Pe drumul obișnuit, încheiat vine mereu după început: butonul se dă doar
 * după ce a pornit. Dar starea se poate pune și de mână, din phpMyAdmin, așa
 * cum se fac multe pe site-ul ăsta — iar atunci ieșea o pagină care spunea
 * „Acest eveniment s-a încheiat." și dedesubt întreba, cu butoane vii, „Mergi
 * la acest eveniment?", ba mai lăsa organizatorul să scoată oameni de pe o
 * listă care nu mai era a nimănui.
 *
 * Presupunerea scrisă într-un comentariu s-a stricat în tăcere. Acum e scrisă
 * în cod, și e verificată aici.
 */
verifica('unul încheiat a început, deși ziua lui abia vine', true,
    evenimentAInceput(['data_eveniment'  => date('Y-m-d', strtotime('+5 days')),
                       'ora_inceput'     => '19:00:00',
                       'stare_moderare'  => 'incheiat']));
verifica('dar unul aprobat, la aceeași dată, nu', false,
    evenimentAInceput(['data_eveniment'  => date('Y-m-d', strtotime('+5 days')),
                       'ora_inceput'     => '19:00:00',
                       'stare_moderare'  => 'aprobat']));

// Evenimentul de probă e peste 12 zile, deci nu se poate încheia încă.
$r = incheie($c, $slugInch);
verifica('unul care n-a început nu se poate încheia', false, $r['ok'] ?? true);
verifica('cu mesaj care îndrumă spre anulare', true,
    str_contains($r['mesaj'] ?? '', 'Îl poți doar anula'));
verifica('și starea e neatinsă', 'aprobat', $stareaLui($idInch));
verifica('nici butonul nu se vede în pagină', false,
    str_contains(cerere($laInch, $c)['corp'], 'id="ev-incheie"'));

// Îl aducem la ziua de azi, cu ora trecută: de aici încolo se poate.
db()->prepare('UPDATE evenimente SET data_eveniment = ?, ora_inceput = ? WHERE id = ?')
    ->execute([date('Y-m-d'), date('H:i:00', strtotime('-1 hour')), $idInch]);

verifica('după ora de început, butonul apare', true,
    str_contains(cerere($laInch, $c)['corp'], 'id="ev-incheie"'));

/* --------------------------- cine n-are voie -------------------------- */

verifica('altcineva nu poate încheia evenimentul meu', false,
    incheie($altul, $slugInch)['ok'] ?? false);
verifica('fără CSRF bun, nici atât', false,
    incheie($c, $slugInch, 'token-gresit')['ok'] ?? false);
$gol = [];
verifica('nelogatul, la fel', false, incheie($gol, $slugInch, 'orice')['ok'] ?? false);
verifica('un slug inexistent nu încheie nimic', false,
    incheie($c, 'nu-exista-nicaieri')['ok'] ?? false);
verifica('prin GET → 405', 405,
    cerere($baza . '/api/incheie-eveniment.php', $c)['stare']);
verifica('după toate încercările, e tot aprobat', 'aprobat', $stareaLui($idInch));

// Un anunț care încă așteaptă moderarea n-a început, deci n-are ce încheia.
$idAstept2 = pune($idOrg, 'Încă la moderare', 'in_asteptare', 12);
verifica('unul neaprobat nu se poate încheia', false,
    incheie($c, $slugul($idAstept2))['ok'] ?? false);

/* --------------------------- organizatorul ---------------------------- */

$r = incheie($c, $slugInch);
verifica('organizatorul îl poate încheia', true, $r['ok'] ?? false);
verifica('cu mesajul cerut', 'Am încheiat evenimentul.', $r['mesaj'] ?? '');
verifica('și e trimis înapoi pe pagina lui',
    urlEveniment($slugInch), $r['redirect'] ?? '');
verifica('starea din bază s-a schimbat', 'incheiat', $stareaLui($idInch));
verifica('a doua oară n-are ce mai încheia', false, incheie($c, $slugInch)['ok'] ?? false);

/* ------------------ ce se schimbă peste tot în site ------------------- */

// Rămâne public, cu tot cu cartonașul de distribuire.
$paginaInch = cerere($laInch, $anonim);
verifica('pagina rămâne deschisă pentru oricine', 200, $paginaInch['stare']);
verifica('și se lasă indexată', false, str_contains($paginaInch['corp'], 'name="robots"'));
verifica('cu banda de încheiat', true,
    str_contains($paginaInch['corp'], 'stare-anunt--incheiat'));
verifica('caseta de participare a plecat cu totul', false,
    str_contains($paginaInch['corp'], 'class="rsvp"'));
verifica('deci n-are ce mai fi stins', false,
    str_contains($paginaInch['corp'], 'id="btn-going"'));

/**
 * Ce rămâne sunt taburile, iar ele nu mai cer nimic: spun ce numără.
 * „Participă 12" sub un anunț trecut sună a invitație la ceva ce nu se mai
 * poate.
 */
verifica('tabul „Interesați" a plecat', false,
    str_contains($paginaInch['corp'], 'id="tab-interested"'));
verifica('rămâne „Au participat"', true,
    str_contains($paginaInch['corp'], '>Au participat</span>'));
verifica('fără prezent pe taburi', false,
    str_contains($paginaInch['corp'], '>Participă</span>'));
verifica('iar butonul de încheiere a dispărut', false,
    str_contains(cerere($laInch, $c)['corp'], 'id="ev-incheie"'));

// Nu mai ține pe nimeni blocat și nu mai apare pe profil.
// Organizatorul are și alte evenimente din probele de mai sus; ne uităm doar
// dacă ăsta a ieșit din listă.
verifica('nu mai e printre cele active', false, in_array($idInch,
    array_map('intval', array_column(evenimenteActive($idOrg), 'id')), true));
verifica('nici pe profil', false, in_array('Unul pe care îl încheie el',
    array_column(evenimenteDePeProfil($idOrg, true), 'titlu'), true));

/**
 * DAR ABIA ACUM SE NUMĂRĂ la „Evenimente organizate". Cifra aia spune ce a
 * ȚINUT omul pentru oraș: cât timp seara era încă în față, nu ținuse nimic —
 * putea și să anuleze. Butonul „Încheie evenimentul" e tocmai clipa în care a
 * dus treaba la capăt, deci cifra urcă atunci, nu scade.
 */
$inainteDeIncheiere = cateEvenimenteOrganizate($idOrg);

/**
 * Unul de AZI, care a și început: singura fereastră în care butonul chiar se
 * poate apăsa. Unul de mâine n-a început, iar unuia de ieri i-a trecut ziua,
 * deci e încheiat oricum — vezi poateFiIncheiat(). Ora se mută cu mâna,
 * fiindcă pune() scrie 19:00 și proba ar fi mers doar seara.
 */
$idInch2 = pune($idOrg, 'Încă unul, pe care îl încheie', 'aprobat', 0);
db()->prepare('UPDATE evenimente SET ora_inceput = \'00:01\' WHERE id = ?')->execute([$idInch2]);

verifica('cât n-a trecut ziua, nu se numără', $inainteDeIncheiere,
    cateEvenimenteOrganizate($idOrg));

verifica('și butonul chiar a mers', true, (incheie($c, $slugul($idInch2))['ok'] ?? false) === true);
verifica('după încheiere, da', $inainteDeIncheiere + 1,
    cateEvenimenteOrganizate($idOrg));

// Nu se mai poate edita: o editare l-ar întoarce în „in_asteptare".
verifica('nu se mai poate edita', null, evenimentDeEditat($slugInch, $idOrg));
verifica('nici formularul lui nu se deschide', 302,
    cerere($baza . '/adauga_eveniment.php?slug=' . urlencode($slugInch), $c)['stare']);

// Nimeni nu se mai înscrie, nici măcar retragere.
verifica('nu se mai poate apăsa „mă interesează"', false,
    apasa($altul, $slugInch, 'interesat')['ok'] ?? true);
verifica('cu mesajul de încheiere', 'Evenimentul s-a încheiat.',
    apasa($altul, $slugInch, 'interesat')['mesaj'] ?? '');

/* ------------- textul de sub butoane, la un eveniment trecut ---------- */

/**
 * Nu mai există deloc, fiindcă nu mai există nici butoanele deasupra lui.
 *
 * Rândul cu chipuri („X este interesat de acest eveniment") trăiește doar în
 * caseta „Mergi la acest eveniment?", iar aceea pleacă odată cu ora de
 * început. A avut o vreme și o formă la trecut; acum n-are cui s-o arate, așa
 * că nu mai e scrisă nicăieri (vezi randeazaChipuri din inc/interese.php).
 */
salveazaInteres($idInch, $idAltul, 'participant');
$corpInch = cerere($laInch, $anonim)['corp'];
verifica('nu mai e niciun rând cu chipuri', false, str_contains($corpInch, 'facepile'));
verifica('nici la prezent', false, str_contains($corpInch, 'este interesat'));
verifica('nici la trecut', false, str_contains($corpInch, 'a fost interesat sau a participat'));

// Dar participanții se văd tot în tabul de dedesubt: numai vorba a plecat.
$corpInch = cerere($laInch, $anonim)['corp'];
verifica('oamenii se văd în taburi', true, str_contains($corpInch, 'data-participant='));
verifica('cu numărul lor pe tab', true,
    preg_match('/data-count-for="participant"[^>]*>1</', $corpInch) === 1);

// Iar cine s-a arătat doar interesat nu se mai numără nicăieri pe pagină.
salveazaInteres($idInch, $idOrg, 'interesat');
$corpInch = cerere($laInch, $anonim)['corp'];
verifica('interesații nu mai apar deloc', false,
    str_contains($corpInch, 'data-count-for="interesat"'));

// …iar la unul încă activ, prezentul rămâne neatins.
$idViitor = pune($idOrg, 'Care încă urmează', 'aprobat', 20);
salveazaInteres($idViitor, $idAltul, 'interesat');
verifica('la unul activ, prezentul rămâne', true,
    preg_match('/este interesat(ă)? de această activitate\./u',
        cerere($baza . urlEveniment($slugul($idViitor)), $anonim)['corp']) === 1);

/* ============ ÎNCHEIAT DE MÂNĂ, CU ZIUA ÎNCĂ ÎN VIITOR ================
   Starea se poate pune și din phpMyAdmin, nu doar din buton. Atunci ieșea o
   pagină care spunea „Acest eveniment s-a încheiat." și dedesubt întreba, cu
   butoane vii, „Mergi la acest eveniment?" — ba mai lăsa organizatorul să
   scoată oameni de pe o listă care nu mai era a nimănui.

   Se verifică pe toată calea: ce se desenează, și ce refuză serverul.
   ====================================================================== */

echo "\n=== ÎNCHEIAT DE MÂNĂ, CU ZIUA ÎN VIITOR ===\n";

$idCiudat   = pune($idOrg, 'Încheiat înainte de vreme', 'aprobat', 9);
$slugCiudat = $slugul($idCiudat);
$laCiudat   = $baza . urlEveniment($slugCiudat);

salveazaInteres($idCiudat, $idOrg, 'participant');
salveazaInteres($idCiudat, $idAltul, 'participant');

// Ca în phpMyAdmin: numai coloana, fără să treacă pe la nicio regulă.
db()->prepare('UPDATE evenimente SET stare_moderare = \'incheiat\' WHERE id = ?')
    ->execute([$idCiudat]);

$evCiudat = evenimentDupaSlug($slugCiudat);

verifica('e încheiat', true, evenimentIncheiat($evCiudat));
verifica('deci a și început', true, evenimentAInceput($evCiudat));

$corpCiudat = cerere($laCiudat, $c)['corp'];

verifica('pagina spune că s-a încheiat', true,
    str_contains($corpCiudat, 'Acest eveniment s-a încheiat.'));
verifica('fără caseta de participare', false, str_contains($corpCiudat, 'class="rsvp"'));
verifica('fără butoane', false, str_contains($corpCiudat, 'id="btn-going"'));
verifica('fără tabul „Interesați"', false, str_contains($corpCiudat, 'id="tab-interested"'));
verifica('și fără niciun „X" de scoatere', false, str_contains($corpCiudat, 'person__scoate'));

// Serverul refuză și el, nu doar pagina: cererea poate veni de oriunde.
$r = json_din(cerere($baza . '/api/exclude-participant.php', $c, [
    'csrf'   => csrf($c),
    'slug'   => $slugCiudat,
    'membru' => $idAltul,
    'motiv'  => 'Un motiv destul de lung ca să treacă de verificare.',
]));

verifica('serverul refuză scoaterea', false, $r['ok'] ?? true);
verifica('cu motivul limpede', true, str_contains($r['mesaj'] ?? '', 'a început'));
verifica('iar omul e tot pe listă', 'participant', stareaDinBaza($idCiudat, $idAltul));

verifica('și înscrierea e refuzată', false, apasa($altul, $slugCiudat, 'interesat')['ok'] ?? true);

/* ============ VORBA DE DUPĂ SCOATERE, DUPĂ OM =========================
   „L-am scos de pe listă" despre o femeie e o scăpare care se vede din prima,
   mai ales de cea despre care e vorba. Sexul se citește oricum, ca să i se
   poată scrie cum trebuie și în e-mail.
   ====================================================================== */

echo "\n=== VORBA DE DUPĂ SCOATERE ===\n";

$idScos   = pune($idOrg, 'De pe care se scot oameni', 'aprobat', 14);
$slugScos = $slugul($idScos);
$motivBun = 'Un motiv destul de lung ca să treacă de verificare.';

$scoate = static function (int $cine) use (&$c, $slugScos, $motivBun): array {
    return json_din(cerere($GLOBALS['baza'] . '/api/exclude-participant.php', $c, [
        'csrf'   => csrf($c),
        'slug'   => $slugScos,
        'membru' => $cine,
        'motiv'  => $motivBun,
    ]));
};

// El: contul de probă e bărbat.
salveazaInteres($idScos, $idAltul, 'participant');
$r = $scoate($idAltul);

verifica('scoaterea merge', true, $r['ok'] ?? false);
verifica('pentru el, „L-am scos"', true, str_contains($r['mesaj'] ?? '', 'L-am scos'));

// Ea: același om, trecut pe „F" — sexul e singurul lucru care se schimbă.
db()->prepare('UPDATE membri SET sex = \'F\' WHERE id = ?')->execute([$idAltul]);
db()->prepare('DELETE FROM excluderi_evenimente WHERE eveniment_id = ?')->execute([$idScos]);
salveazaInteres($idScos, $idAltul, 'participant');

$r = $scoate($idAltul);

verifica('și acum tot merge', true, $r['ok'] ?? false);
verifica('pentru ea, „Am scos-o"', true, str_contains($r['mesaj'] ?? '', 'Am scos-o'));
verifica('și nicidecum „L-am scos"', false, str_contains($r['mesaj'] ?? '', 'L-am scos'));
verifica('nici la înștiințare', true,
    str_contains($r['mesaj'] ?? '', 'am înștiințat-o') || !($r['instiintat'] ?? false));

db()->prepare('UPDATE membri SET sex = \'M\' WHERE id = ?')->execute([$idAltul]);

/* ------------------------- ADRESELE FRUMOASE --------------------------- */

echo "\n--- adresele frumoase ---\n";

/**
 * `/eveniment/<slug>`, nu `event.php?slug=<slug>`.
 *
 * Adresa unui eveniment e singura de pe site pe care oamenii o pun în mesaje și
 * o citesc unii altora la telefon. Rescrierea o face .htaccess pe găzduire și
 * teste/router.php în dezvoltare — pentru event.php cererea arată la fel.
 */
verifica('urlEveniment scrie calea nouă', '/eveniment/un-slug-oarecare',
    urlEveniment('un-slug-oarecare'));

/**
 * `rawurlencode`, nu `urlencode`: al doilea scrie spațiul ca „+", ceea ce
 * într-o CALE înseamnă un plus adevărat, nu un spațiu. Slugurile n-au spații,
 * dar regula asta n-are voie să atârne de altă regulă.
 */
verifica('și nu pune „+" în locul spațiului', '/eveniment/a%20b', urlEveniment('a b'));

verifica('urlProfil rămâne cu întrebare', '/profil.php?m=abcdef1234',
    urlProfil('abcdef1234'));

/* Toate adresele scrise de site pornesc de la rădăcină, cu „/" în față. */
verifica('adresa filtrată e absolută', '/index.php?categorie=sport',
    adresaFiltrata('', 'sport'));
verifica('și cea goală, la fel', '/index.php', adresaFiltrata('', ''));

/* ---------------- imaginea implicită a unei categorii ------------------ */

/**
 * Fișierele astea NU trec prin inc/imagini.php: se urcă de mână pe FTP, iar în
 * bază stă doar numele lor (`categorii.imagine_default`). Adică o cale care
 * vine DIN BAZĂ, nu din cod — de aceea a scăpat de măturatul adreselor și a dat
 * 404 de pe `/eveniment/<slug>`, căutată în `/eveniment/assets/img/categorii/`.
 */
$dosarCat = dirname(__DIR__) . '/' . CATEGORIE_DOSAR;
$aveaDosar = is_dir($dosarCat);

if (!$aveaDosar) { @mkdir($dosarCat, 0775, true); }

$pozaCat = $dosarCat . '/tst-categorie.jpg';
$imCat = imagecreatetruecolor(80, 45);
imagejpeg($imCat, $pozaCat, 70);
imagedestroy($imCat);

verifica('adresa pornește de la rădăcină', '/' . CATEGORIE_DOSAR . '/tst-categorie.jpg',
    urlImagineCategorie('tst-categorie.jpg'));

/**
 * SE VERIFICĂ PE DISC, nu doar în bază: coloana există de mult, fișierele se
 * urcă de mână, iar unele lipsesc. O adresă care duce la 404 e mai rea decât
 * niciuna — cartonașul ar avea o gaură în loc de poză.
 */
verifica('un fișier care nu e pe disc nu dă adresă', '',
    urlImagineCategorie('nu-exista-pe-disc.jpg'));

/* Numele vine din bază, deci se cerne. */
verifica('nicio cale în sus',  '', urlImagineCategorie('../../inc/config.php'));
verifica('nici cu bară',       '', urlImagineCategorie('categorii/x.jpg'));
verifica('nici altă extensie', '', urlImagineCategorie('tst-categorie.php'));
verifica('gol, nu',            '', urlImagineCategorie(''));
verifica('null, nici el',      '', urlImagineCategorie(null));

@unlink($pozaCat);
if (!$aveaDosar) { @rmdir($dosarCat); }

if ($baza !== '') {
    // Un anunț al lui, făcut aici: cele de mai sus au fost șterse pe parcurs.
    $idFrumos   = pune($idOrg, 'Un anunț cu adresă frumoasă', 'aprobat', 8);
    $slugFrumos = $slugul($idFrumos);

    /**
     * ANUNȚUL ĂSTA N-ARE COPERTĂ, IAR CATEGORIA LUI ARE IMAGINE.
     *
     * Fără asta, proba de mai jos („nicio adresă relativă în pagină") desenează
     * o pagină în care imaginea de categorie nici nu apare — și tocmai ea a
     * fost adresa relativă care a scăpat. O probă care nu vede lucrul stricat
     * nu-l poate prinde.
     */
    if (!is_dir($dosarCat)) { @mkdir($dosarCat, 0775, true); }

    $imCat = imagecreatetruecolor(80, 45);
    imagejpeg($imCat, $pozaCat, 70);
    imagedestroy($imCat);

    $catFrumos = (int) db()->query('SELECT categorie_id FROM evenimente WHERE id = ' . $idFrumos)
                           ->fetchColumn();
    $imgVeche = db()->query('SELECT imagine_default FROM categorii WHERE id = ' . $catFrumos)
                    ->fetchColumn();

    db()->prepare('UPDATE categorii SET imagine_default = ? WHERE id = ?')
        ->execute(['tst-categorie.jpg', $catFrumos]);

    /**
     * ADRESA VECHE TRIMITE LA CEA NOUĂ, cu 301 — nu cu 302.
     *
     * Linkurile de pe WhatsApp dinainte de schimbare trebuie să meargă, dar nu
     * ca a doua adresă a aceluiași lucru: Google numără două adrese cu același
     * conținut drept conținut repetat.
     */
    $rVechi = cerere($baza . '/event.php?slug=' . urlencode($slugFrumos), $c);
    verifica('adresa veche răspunde cu 301', 301, $rVechi['stare']);

    /**
     * Ce mai era în adresă se lipește la loc: un „?comentariu=12" venit dintr-un
     * e-mail n-are voie să se piardă pe drum.
     */
    $rCuCoada = cerere($baza . '/event.php?slug=' . urlencode($slugFrumos) . '&comentariu=12', $c);
    verifica('și duce mai departe și coada adresei', 301, $rCuCoada['stare']);

    // Adresa nouă se deschide de-a dreptul.
    verifica('adresa nouă se deschide', 200,
        cerere($baza . urlEveniment($slugFrumos), $c)['stare']);

    /**
     * UN SLUG CARE NU DUCE NICĂIERI NU PRIMEȘTE 301.
     *
     * Redirecționarea se face ABIA după ce se știe că anunțul există și se
     * poate vedea. Pusă înainte, ar fi trimis permanent — iar browserul ține
     * minte un 301 — către o adresă care oricum n-are ce arăta.
     */
    verifica('un slug inexistent nu primește 301', 302,
        cerere($baza . '/event.php?slug=nu-exista-nicicum-xyz', $c)['stare']);

    /* Pagina desenată de pe adresa nouă poartă adresa nouă peste tot. */
    $paginaFrumos = cerere($baza . urlEveniment($slugFrumos), $c)['corp'];

    verifica('canonical arată spre adresa nouă', true,
        str_contains($paginaFrumos,
            '<link rel="canonical" href="' . h(urlIntreg(urlEveniment($slugFrumos))) . '">'));

    verifica('și og:url la fel',
        urlIntreg(urlEveniment($slugFrumos)), $og($paginaFrumos, 'og:url'));

    /**
     * NICIO ADRESĂ RELATIVĂ ÎN PAGINĂ.
     *
     * Pagina asta stă la adâncimea 1 (`/eveniment/…`), deci un „assets/…" sau
     * un „profil.php" scrise fără „/" în față ar fi căutate în
     * `/eveniment/assets/…`. E chiar felul în care se rupe un site la trecerea
     * la adrese frumoase, și se rupe tăcut: pozele lipsesc, legăturile dau 404.
     */
    preg_match_all('/(?:href|src|action)="([^"]+)"/', $paginaFrumos, $adrese);

    $relative = array_values(array_filter(
        $adrese[1],
        static fn(string $a): bool =>
            $a !== ''
            && !str_starts_with($a, '/')
            && !str_starts_with($a, '#')
            && preg_match('#^(https?:|mailto:|tel:|data:|javascript:)#i', $a) !== 1
    ));

    verifica('nicio adresă relativă în pagină', [], $relative);

    /* Și imaginea categoriei chiar a ajuns în ea — altfel proba de sus n-a
       avut ce cerne. */
    verifica('imaginea categoriei e în pagină, de la rădăcină', true,
        str_contains($paginaFrumos, 'src="/' . CATEGORIE_DOSAR . '/tst-categorie.jpg"'));

    /* Și în cartonașul de pe WhatsApp, ca adresă întreagă. */
    verifica('și în og:image, ca adresă întreagă',
        urlIntreg('/' . CATEGORIE_DOSAR . '/tst-categorie.jpg'),
        $og($paginaFrumos, 'og:image'));

    /* Înapoi cum era. */
    db()->prepare('UPDATE categorii SET imagine_default = ? WHERE id = ?')
        ->execute([$imgVeche, $catFrumos]);

    @unlink($pozaCat);
    if (!$aveaDosar) { @rmdir($dosarCat); }
}

/* ---------------------------- curățenie -------------------------------- */

db()->exec('DELETE FROM evenimente');
db()->prepare('DELETE FROM membri WHERE email IN (?, ?)')->execute([EMAIL_ORG, EMAIL_ALTUL]);
foreach (glob(dirname(__DIR__) . '/' . COPERTA_DOSAR . '/*.jpg') ?: [] as $f) { @unlink($f); }

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
