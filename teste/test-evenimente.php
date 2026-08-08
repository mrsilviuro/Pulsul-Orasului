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
            $corp .= "--$granita\r\n";
            $corp .= "Content-Disposition: form-data; name=\"$nume\"\r\n\r\n";
            $corp .= $valoare . "\r\n";
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

/* ---------------------------- curățenie -------------------------------- */

db()->exec('DELETE FROM evenimente');
db()->prepare('DELETE FROM membri WHERE email = ?')->execute([EMAIL_ORG]);
foreach (glob(dirname(__DIR__) . '/' . COPERTA_DOSAR . '/*.jpg') ?: [] as $f) { @unlink($f); }

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
