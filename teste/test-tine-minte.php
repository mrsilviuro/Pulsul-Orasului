<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — „ține-mă minte" pe treizeci de zile.
 *
 * Testul nu cheamă funcțiile direct, ci vorbește cu site-ul prin HTTP, cu
 * cookie-uri adevărate. Numai așa se vede lucrul care contează: că omul
 * rămâne conectat CHIAR DACĂ fișierul sesiunii de pe server a dispărut.
 *
 * Cum se rulează:
 *     php -S 127.0.0.1:8126 -t . &
 *     php teste/test-tine-minte.php http://127.0.0.1:8126
 */

/* --------------------------- Doar din consolă -------------------------- */

/**
 * Probele nu se rulează din browser. `teste/.htaccess` le închide dosarul, dar
 * el se citește doar pe Apache cu AllowOverride pornit — verificarea asta ține
 * oriunde. Aceeași pereche de încuietori ca la cron/.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Se rulează doar din linia de comandă.\n");
}
require_once __DIR__ . '/../inc/auth.php';

$baza = rtrim($argv[1] ?? 'http://127.0.0.1:8126', '/');

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

const UA_NORMAL = 'Mozilla/5.0 (TestTineMinte)';

/**
 * O cerere, cu borcanul de cookie-uri dat de noi.
 *
 * $cookies e trimis prin referință: ce pune serverul se întoarce în el, ca
 * într-un browser adevărat.
 */
function cerere(string $url, array &$cookies, array $post = null, string $ua = UA_NORMAL): array
{
    $antete = ['User-Agent: ' . $ua];

    if ($cookies !== []) {
        $perechi = [];
        foreach ($cookies as $k => $v) { $perechi[] = $k . '=' . urlencode($v); }
        $antete[] = 'Cookie: ' . implode('; ', $perechi);
    }

    $optiuni = ['http' => [
        'method'        => $post === null ? 'GET' : 'POST',
        'header'        => $antete,
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout'       => 10,
    ]];

    if ($post !== null) {
        $corp = json_encode($post);
        $optiuni['http']['header'][] = 'Content-Type: application/json';
        $optiuni['http']['content']  = $corp;
    }

    $corpRaspuns = @file_get_contents($url, false, stream_context_create($optiuni));
    $antRaspuns  = $http_response_header ?? [];

    // Cookie-urile primite intră în borcan; cele cu dată din trecut ies.
    foreach ($antRaspuns as $a) {
        if (stripos($a, 'Set-Cookie:') !== 0) { continue; }

        $bucata = trim(substr($a, 11));
        $prima  = explode(';', $bucata)[0];
        [$nume, $valoare] = array_pad(explode('=', $prima, 2), 2, '');

        $sters = preg_match('/expires=([^;]+)/i', $bucata, $m) && strtotime($m[1]) < time();

        // setcookie() codează valoarea (două puncte devin %3A), iar PHP o
        // decodează singur la citire. Ținem în borcan valoarea decodată, ca
        // în test să se vadă „selector:secret", și o codăm la trimitere.
        if ($valoare === '' || $sters) { unset($cookies[$nume]); }
        else                           { $cookies[$nume] = urldecode($valoare); }
    }

    $stare = 0;
    if (isset($antRaspuns[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $antRaspuns[0], $m)) {
        $stare = (int) $m[1];
    }

    return ['stare' => $stare, 'corp' => (string) $corpRaspuns, 'antete' => $antRaspuns];
}

/** E cineva conectat, după pagina de profil? */
function esteConectat(array &$cookies, string $ua = UA_NORMAL): bool
{
    $r = cerere($GLOBALS['baza'] . '/profil.php', $cookies, null, $ua);

    // Creionul de schimbat poza apare doar pe profilul propriu, deci e semnul
    // cel mai sigur că serverul chiar știe cine e omul.
    return str_contains($r['corp'], 'profile__poza-edit');
}

/** Șterge fișierul sesiunii de pe server, ca și cum l-ar fi măturat PHP. */
function stingeSesiuneaDePeServer(array $cookies): int
{
    $cale = session_save_path() ?: sys_get_temp_dir();
    $sterse = 0;

    foreach (glob($cale . '/sess_*') ?: [] as $f) {
        if (@unlink($f)) { $sterse++; }
    }

    return $sterse;
}

/* ------------------------- membrul de încercare ------------------------ */

const EMAIL_TEST  = 'tine-minte@exemplu-test.ro';
const PAROLA_TEST = 'ParolaDeTest#2026';

db()->prepare('DELETE FROM membri WHERE email = ?')->execute([EMAIL_TEST]);

db()->prepare(
    'INSERT INTO membri (permalink, nume, prenume, email, parola_hash, sex,
                         data_nasterii, stare, creat_la, confirmat_la)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([
    'tinemint01', 'Popescu', 'Ionuț', EMAIL_TEST,
    password_hash(PAROLA_TEST, PASSWORD_DEFAULT),
    'M', '1990-05-20', 'activ', acum(), acum(),
]);

$membruId = (int) db()->lastInsertId();

/** Câte amintiri are membrul de încercare. */
function amintiri(): int
{
    $q = db()->prepare('SELECT COUNT(*) FROM sesiuni_amintite WHERE membru_id = ?');
    $q->execute([$GLOBALS['membruId']]);
    return (int) $q->fetchColumn();
}

/** Șterge toate amintirile, ca fiecare secțiune să pornească de la zero. */
function curataAmintirile(): void
{
    db()->prepare('DELETE FROM sesiuni_amintite WHERE membru_id = ?')
        ->execute([$GLOBALS['membruId']]);
}

/** Token-ul CSRF al sesiunii, luat din linkul de ieșire al paginii de profil. */
function csrfDinPagina(array &$cookies): string
{
    $r = cerere($GLOBALS['baza'] . '/profil.php', $cookies);
    preg_match('/iesire\.php\?token=([0-9a-f]+)/', $r['corp'], $m);
    return $m[1] ?? '';
}

/** Intrarea în cont, cu sau fără bifă. */
function intra(array &$cookies, bool $tineMinte, string $ua = UA_NORMAL): array
{
    // Token-ul CSRF se ia din pagina de intrare, ca un vizitator adevărat.
    $pagina = cerere($GLOBALS['baza'] . '/login.php', $cookies, null, $ua);
    preg_match('/name="csrf"\s+value="([0-9a-f]+)"/', $pagina['corp'], $m);

    return cerere($GLOBALS['baza'] . '/api/autentificare.php', $cookies, [
        'csrf'       => $m[1] ?? '',
        'email'      => EMAIL_TEST,
        'parola'     => PAROLA_TEST,
        'tine_minte' => $tineMinte ? '1' : '',
    ], $ua);
}

/* ======================================================================= */
echo "=== BIFA NEBIFATĂ: nicio amintire ===\n";

curataAmintirile();

$c = [];
$r = intra($c, false);
verifica('intrarea reușește', 200, $r['stare']);
verifica('omul e conectat', true, esteConectat($c));
verifica('fără cookie de amintire', false, isset($c[COOKIE_TINE_MINTE]));
verifica('fără rând în sesiuni_amintite', 0, amintiri());

echo "\n=== BIFA BIFATĂ: amintire scrisă ===\n";
curataAmintirile();

$c = [];
intra($c, true);
verifica('omul e conectat', true, esteConectat($c));
verifica('cookie de amintire primit', true, isset($c[COOKIE_TINE_MINTE]));
verifica('un singur rând în sesiuni_amintite', 1, amintiri());

$cookieAmintire = $c[COOKIE_TINE_MINTE] ?? '';
verifica('cookie-ul are forma selector:secret', 1,
    preg_match('/^[0-9a-f]{32}:[0-9a-f]{64}$/', $cookieAmintire));

$q = db()->prepare('SELECT selector, token_hash, expira FROM sesiuni_amintite WHERE membru_id = ?');
$q->execute([$membruId]);
$rand = $q->fetch();

[$selector, $secret] = explode(':', $cookieAmintire, 2);
verifica('selectorul din cookie e cel din baza de date', $rand['selector'], $selector);
verifica('secretul NU stă în clar în baza de date', false,
    str_contains(json_encode($rand), $secret));
verifica('în baza de date stă sha256 al secretului', $rand['token_hash'], hash('sha256', $secret));

$zile = (int) round((strtotime($rand['expira']) - time()) / 86400);
verifica('expiră peste 30 de zile', ZILE_TINE_MINTE, $zile);

echo "\n=== MIEZUL: sesiunea de pe server dispare ===\n";

// Asta se întâmplă de la sine după session.gc_maxlifetime — pe majoritatea
// găzduirilor, 24 de minute. Aici o grăbim.
$sterse = stingeSesiuneaDePeServer($c);
verifica('fișierele de sesiune au fost șterse', true, $sterse > 0);

verifica('omul e TOT conectat, fără parolă', true, esteConectat($c));
verifica('tot un singur rând (vechiul s-a rotit)', 1, amintiri());

$cookieNou = $c[COOKIE_TINE_MINTE] ?? '';
verifica('cookie-ul de amintire s-a schimbat (rotație)', true,
    $cookieNou !== '' && $cookieNou !== $cookieAmintire);

$q->execute([$membruId]);
$randNou = $q->fetch();
$zileNou = (int) round((strtotime($randNou['expira']) - time()) / 86400);
verifica('rotația NU prelungește cele 30 de zile', $zile, $zileNou);

echo "\n=== COOKIE VECHI, DEJA ROTIT ===\n";

// Cine mai încearcă o dată cookie-ul vechi e ori un cookie uitat, ori un hoț.
// Nu putem ști care, deci cad toate amintirile.
$cHot = [COOKIE_TINE_MINTE => $cookieAmintire];
verifica('cookie-ul vechi nu mai conectează', false, esteConectat($cHot));
verifica('toate amintirile membrului au căzut', 0, amintiri());

echo "\n=== COOKIE MUTAT PE ALT BROWSER ===\n";
curataAmintirile();

$c = [];
intra($c, true);
$cAlt = [COOKIE_TINE_MINTE => $c[COOKIE_TINE_MINTE]];
verifica('amintirea nu merge cu alt User-Agent', false,
    esteConectat($cAlt, 'Mozilla/5.0 (AltBrowser)'));

echo "\n=== COOKIE MEȘTERIT ===\n";

foreach ([
    'gol'                  => '',
    'fără două puncte'     => str_repeat('a', 32),
    'selector prea scurt'  => 'abc:' . str_repeat('b', 64),
    'litere neermitice'    => str_repeat('z', 32) . ':' . str_repeat('z', 64),
    'injecție SQL'         => "' OR 1=1 --:" . str_repeat('b', 64),
    'selector bun, secret greșit' => $selector . ':' . str_repeat('0', 64),
] as $ce => $valoare) {
    $cRau = [COOKIE_TINE_MINTE => $valoare];
    verifica("respins: $ce", false, esteConectat($cRau));
}

echo "\n=== AMINTIRE EXPIRATĂ ===\n";
curataAmintirile();

$c = [];
intra($c, true);
db()->prepare('UPDATE sesiuni_amintite SET expira = ? WHERE membru_id = ?')
    ->execute([date('Y-m-d H:i:s', time() - 60), $membruId]);
stingeSesiuneaDePeServer($c);

verifica('amintirea expirată nu conectează', false, esteConectat($c));
verifica('rândul expirat e șters', 0, amintiri());

echo "\n=== IEȘIREA DIN CONT UITĂ DISPOZITIVUL ===\n";
curataAmintirile();

$c = [];
intra($c, true);
verifica('conectat, cu amintire', 1, amintiri());

cerere($baza . '/iesire.php?token=' . csrfDinPagina($c), $c);

verifica('rândul a fost șters', 0, amintiri());
verifica('cookie-ul de amintire a dispărut', false, isset($c[COOKIE_TINE_MINTE]));
verifica('omul nu mai e conectat', false, esteConectat($c));

echo "\n=== PAROLĂ NOUĂ: celelalte dispozitive ies ===\n";
curataAmintirile();

// Două dispozitive: telefonul și laptopul, amândouă ținute minte.
$telefon = []; intra($telefon, true);
$laptop  = []; intra($laptop,  true);
verifica('două dispozitive ținute minte', 2, amintiri());

$r = cerere($baza . '/api/parola-noua.php', $laptop, [
    'csrf'              => csrfDinPagina($laptop),
    'parola_veche'      => PAROLA_TEST,
    'parola'            => 'AltaParola#2026',
    'parola_confirmare' => 'AltaParola#2026',
]);

if ($r['stare'] === 200) {
    verifica('laptopul rămâne ținut minte', 1, amintiri());

    stingeSesiuneaDePeServer($telefon);
    verifica('telefonul a fost dat afară', false, esteConectat($telefon));

    // Parola înapoi, pentru rulările următoare.
    db()->prepare('UPDATE membri SET parola_hash = ? WHERE id = ?')
        ->execute([password_hash(PAROLA_TEST, PASSWORD_DEFAULT), $membruId]);
} else {
    echo "  (sărit: api/parola-noua.php a răspuns {$r['stare']})\n";
}

/* ---------------------------- curățenie -------------------------------- */

db()->prepare('DELETE FROM membri WHERE email = ?')->execute([EMAIL_TEST]);

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
