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
        'locatie'          => 'Piața Sfatului, lângă fântână',
        'data_eveniment'   => date('Y-m-d', strtotime('+10 days')),
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
verifica('categoriile vin din bază (toate cinci)', 5,
    substr_count($pagina['corp'], '<option value="') - substr_count($pagina['corp'], 'value="nespecificat"')
    - substr_count($pagina['corp'], 'value="13"') - substr_count($pagina['corp'], 'value="16"')
    - substr_count($pagina['corp'], 'value="18"') - substr_count($pagina['corp'], 'value="barbati"')
    - substr_count($pagina['corp'], 'value="femei"') - substr_count($pagina['corp'], 'value=""'));

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
verifica('are slug', true, preg_match('/^[a-z0-9-]+-[0-9a-f]{6}$/', (string) $ev['slug']) === 1);
verifica('slugul e făcut din titlu', true, str_starts_with((string) $ev['slug'], 'cursa-de-seara'));

echo "\n=== UN SINGUR EVENIMENT ACTIV ===\n";

$r = trimite($c, ['titlu' => 'Al doilea eveniment, care nu trebuie să intre']);
verifica('al doilea e oprit', false, $r['ok'] ?? true);
verifica('cu mesajul cerut', 'Ai deja un eveniment activ. Poți posta unul nou după ce acesta se încheie.',
    $r['mesaj'] ?? '');
verifica('n-a ajuns în bază', 1, cateEvenimente());

$pagina = cerere($baza . '/adauga_eveniment.php', $c);
verifica('pagina arată de ce, nu formularul', false, str_contains($pagina['corp'], 'id="eveniment-form"'));
verifica('și îi spune care e evenimentul', true, str_contains($pagina['corp'], 'Cursa de seară'));

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
    ['dată în trecut',       ['data_eveniment' => date('Y-m-d', strtotime('-1 day'))], 'data_eveniment'],
    ['dată imposibilă',      ['data_eveniment' => '2027-02-30'],             'data_eveniment'],
    ['dată prea departe',    ['data_eveniment' => date('Y-m-d', strtotime('+5 years'))], 'data_eveniment'],
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

$pagina = cerere($baza . '/profil.php?m=organizat02', $anonim)['corp'];

verifica('vizitatorul primește patru cartonașe', 4, $catePe($pagina, '<article class="card'));
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

$pagina = cerere($baza . '/profil.php?m=organizat02', $anonim)['corp'];
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

/* -------------------- fără cont nu se intră deloc -------------------- */

$r = cerere($laEveniment($idAprobat), $anonim);
verifica('nelogat → trimis la login', 302, $r['stare']);
verifica('spre login, nu în altă parte', true, str_contains($r['unde'], 'login.php'));
verifica('și adus înapoi la eveniment după intrare', true,
    str_contains($r['unde'], 'redirect=' . urlencode('/event.php?slug=' . $slugul($idAprobat))));

// Un slug care nu există trebuie să arate EXACT ca unul care există: altfel,
// cine nu are cont ar putea afla ce sluguri sunt bune.
$rInexistent = cerere($baza . '/event.php?slug=nu-exista-abc123', $anonim);
verifica('nelogat, slug inexistent: același răspuns', $r['stare'], $rInexistent['stare']);

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

// Nici urmă de butoanele de distribuire: au fost scoase de tot.
verifica('fără iconițe de share', false, str_contains($pagina, 'post__share'));
verifica('și fără butonul de copiat linkul', false, str_contains($pagina, 'copy-link'));
verifica('locația', true, str_contains($pagina, 'Piața Sfatului'));
verifica('costul lipsă înseamnă gratuit', true, str_contains($pagina, 'Gratuit'));
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
verifica('și duce la formular', true, str_contains($pagina, 'href="adauga_eveniment.php"'));

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

$paginaDinAfara = cerere($baza . '/profil.php?m=organizat02', $anonim)['corp'];
preg_match('/stat__value">(\d+)</', $paginaDinAfara, $m);
verifica('din afară se vede aceeași cifră', '4', $m[1] ?? '');

/* ---------------- cartonașele de pe profil duc la pagină ---------------- */

db()->exec('DELETE FROM evenimente');
$idAprobat = pune($idOrg, 'Cursa aprobată', 'aprobat', 7);

$pagina = cerere($baza . '/profil.php', $c)['corp'];
verifica('cartonașele de pe profil trimit la event.php', true,
    str_contains($pagina, 'href="event.php?slug=' . $slugul($idAprobat) . '"'));

/* ---------------------------- curățenie -------------------------------- */

db()->exec('DELETE FROM evenimente');
db()->prepare('DELETE FROM membri WHERE email IN (?, ?)')->execute([EMAIL_ORG, EMAIL_ALTUL]);
foreach (glob(dirname(__DIR__) . '/' . COPERTA_DOSAR . '/*.jpg') ?: [] as $f) { @unlink($f); }

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
