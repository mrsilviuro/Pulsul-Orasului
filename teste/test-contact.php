<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — formularul de contact.
 *
 * Prin HTTP, cu cookie-uri adevărate, ca și celelalte suite.
 *
 * Cum se rulează:
 *     php -S 127.0.0.1:8129 -t . &
 *     php teste/test-contact.php http://127.0.0.1:8129
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/validare.php';

$baza = rtrim($argv[1] ?? 'http://127.0.0.1:8129', '/');

$treceri = 0; $picaturi = 0;

function verifica(string $ce, $asteptat, $primit): void
{
    global $treceri, $picaturi;
    $ok = $asteptat === $primit;
    $ok ? $treceri++ : $picaturi++;
    printf("%-58s %s%s\n", $ce, $ok ? 'OK' : 'PICAT',
        $ok ? '' : "  (aștept " . var_export($asteptat, true) . ", am primit " . var_export($primit, true) . ")");
}

function cerere(string $url, array &$cookies, ?array $post = null): array
{
    $antete = ['User-Agent: Mozilla/5.0 (TestContact)'];

    if ($cookies !== []) {
        $perechi = [];
        foreach ($cookies as $k => $v) { $perechi[] = $k . '=' . urlencode($v); }
        $antete[] = 'Cookie: ' . implode('; ', $perechi);
    }

    $optiuni = ['http' => [
        'method'          => $post === null ? 'GET' : 'POST',
        'header'          => $antete,
        'ignore_errors'   => true,
        'follow_location' => 0,
        'timeout'         => 10,
    ]];

    if ($post !== null) {
        $optiuni['http']['header'][] = 'Content-Type: application/json';
        $optiuni['http']['content']  = json_encode($post);
    }

    $corp = @file_get_contents($url, false, stream_context_create($optiuni));
    $ant  = $http_response_header ?? [];

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

    return ['stare' => $stare, 'corp' => (string) $corp];
}

function json_din(array $r): array
{
    $d = json_decode($r['corp'], true);
    return is_array($d) ? $d : [];
}

/** Token-ul CSRF, luat chiar din formularul de contact. */
function csrf(array &$cookies): string
{
    $r = cerere($GLOBALS['baza'] . '/contact.php', $cookies);
    preg_match('/name="csrf" value="([0-9a-f]+)"/', $r['corp'], $m);
    return $m[1] ?? '';
}

/** Un mesaj bun, cu ce vrem schimbat pe deasupra. */
function mesaj(array &$cookies, array $peste = []): array
{
    return json_din(cerere($GLOBALS['baza'] . '/api/contact.php', $cookies, array_merge([
        'csrf'    => csrf($cookies),
        'nume'    => 'Popescu Ion',
        'email'   => 'ion@exemplu-test.ro',
        'telefon' => '0722334455',
        'mesaj'   => 'Bună ziua, am un eveniment de propus pentru luna viitoare.',
    ], $peste)));
}

function cateMesaje(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM mesaje_contact')->fetchColumn();
}

function ultimulMesaj(): array
{
    $r = db()->query('SELECT * FROM mesaje_contact ORDER BY id DESC LIMIT 1')->fetch();
    return $r ?: [];
}

/* ---------------------------- teren curat ------------------------------ */

const EMAIL_MEMBRU = 'contact-membru@exemplu-test.ro';
const EMAIL_FARA_TEL = 'contact-fara-tel@exemplu-test.ro';
const PAROLA_TEST  = 'ParolaDeTest#2026';

db()->exec('DELETE FROM mesaje_contact');

function faMembru(string $email, ?string $telefon, string $permalink): int
{
    db()->prepare('DELETE FROM membri WHERE email = ?')->execute([$email]);
    db()->prepare('DELETE FROM incercari_autentificare WHERE email = ?')->execute([$email]);

    db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, parola_hash, telefon,
                             sex, data_nasterii, stare, creat_la, confirmat_la)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $permalink, 'Constantinescu', 'Silviu', $email,
        password_hash(PAROLA_TEST, PASSWORD_DEFAULT), $telefon,
        'M', '1990-05-20', 'activ', acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

function intra(array &$cookies, string $email): void
{
    $pagina = cerere($GLOBALS['baza'] . '/login.php', $cookies);
    preg_match('/name="csrf"\s+value="([0-9a-f]+)"/', $pagina['corp'], $m);

    cerere($GLOBALS['baza'] . '/api/autentificare.php', $cookies, [
        'csrf' => $m[1] ?? '', 'email' => $email, 'parola' => PAROLA_TEST, 'tine_minte' => '1',
    ]);
}

$idMembru  = faMembru(EMAIL_MEMBRU, '0733445566', 'contactmb1');
$idFaraTel = faMembru(EMAIL_FARA_TEL, null, 'contactft1');

/* ======================================================================= */
echo "=== VIZITATOR FĂRĂ CONT ===\n";

$c = [];
$pagina = cerere($baza . '/contact.php', $c);
verifica('pagina se deschide', 200, $pagina['stare']);
verifica('are token CSRF', true, str_contains($pagina['corp'], 'name="csrf"'));
verifica('are capcana pentru roboți', true, str_contains($pagina['corp'], 'name="website"'));
verifica('capcana NU e ascunsă cu display:none', false,
    str_contains($pagina['corp'], 'style="display:none"'));
verifica('câmpurile sunt goale și libere', false, str_contains($pagina['corp'], 'readonly'));

$r = mesaj($c);
verifica('mesajul bun trece', true, $r['ok'] ?? false);
verifica('s-a scris un rând', 1, cateMesaje());

$m = ultimulMesaj();
verifica('numele s-a despărțit corect', 'Popescu', (string) $m['nume']);
verifica('prenumele', 'Ion', (string) $m['prenume']);
verifica('vizitatorul n-are membru_id', null, $m['membru_id']);
verifica('adresa IP e păstrată', true, $m['ip'] !== null);
verifica('data e scrisă', true, !empty($m['creat_la']));

echo "\n=== CE TREBUIE RESPINS ===\n";

db()->exec('DELETE FROM mesaje_contact');

foreach ([
    ['nume gol',            ['nume' => ''],                       'nume'],
    ['doar un cuvânt',      ['nume' => 'Ion'],                    'nume'],
    ['cifre în nume',       ['nume' => 'Popescu2 Ion'],           'nume'],
    ['etichetă HTML',       ['nume' => '<b>Popescu</b> Ion'],     'nume'],
    ['e-mail gol',          ['email' => ''],                      'email'],
    ['e-mail stricat',      ['email' => 'nu-e-adresa'],           'email'],
    ['telefon gol',         ['telefon' => ''],                    'telefon'],
    ['telefon străin',      ['telefon' => '+33612345678'],        'telefon'],
    ['telefon cu litere',   ['telefon' => '07abcdefgh'],          'telefon'],
    ['mesaj gol',           ['mesaj' => ''],                      'mesaj'],
    ['mesaj prea scurt',    ['mesaj' => 'salut'],                 'mesaj'],
    ['mesaj prea lung',     ['mesaj' => str_repeat('a', 5001)],   'mesaj'],
] as [$ce, $peste, $camp]) {
    $r = mesaj($c, $peste);
    verifica("respins: $ce", true, !empty($r['erori'][$camp]));
}

verifica('niciunul n-a ajuns în bază', 0, cateMesaje());

$r = json_din(cerere($baza . '/api/contact.php', $c, [
    'csrf' => 'gresit', 'nume' => 'Popescu Ion', 'email' => 'ion@exemplu-test.ro',
    'telefon' => '0722334455', 'mesaj' => 'Un mesaj oarecare, destul de lung.',
]));
verifica('fără CSRF bun: refuzat', false, $r['ok'] ?? true);

$r = cerere($baza . '/api/contact.php', $c);
verifica('prin GET: 405', 405, $r['stare']);

verifica('tot niciunul în bază', 0, cateMesaje());

echo "\n=== CAPCANA PENTRU ROBOȚI ===\n";

// clearstatcache: PHP ține minte mărimea fișierului între apeluri, iar fără
// golire a doua citire ar da tot valoarea dinainte.
$caleLogSpam = dirname(__DIR__) . '/private/spam-contact.log';
clearstatcache(true, $caleLogSpam);
$logInainte = @filesize($caleLogSpam) ?: 0;

$r = mesaj($c, ['website' => 'http://spam.example.com']);
verifica('robotul primește „ok", ca să nu afle de capcană', true, $r['ok'] ?? false);
verifica('dar mesajul NU s-a salvat', 0, cateMesaje());

clearstatcache(true, $caleLogSpam);
$logDupa = @filesize($caleLogSpam) ?: 0;
verifica('încercarea e scrisă în log', true, $logDupa > $logInainte);

echo "\n=== LIMITAREA PENTRU VIZITATORI (5 / oră / IP) ===\n";

db()->exec('DELETE FROM mesaje_contact');

$trecute = 0;
for ($i = 1; $i <= 7; $i++) {
    $r = mesaj($c, ['mesaj' => 'Mesajul numărul ' . $i . ', destul de lung ca să treacă.']);
    if (!empty($r['ok'])) { $trecute++; }
}

verifica('exact 5 au trecut', 5, $trecute);
verifica('exact 5 în bază', 5, cateMesaje());

$r = mesaj($c, ['mesaj' => 'Încă unul, tot destul de lung ca să treacă.']);
verifica('al șaselea primește mesaj limpede', true,
    str_contains($r['mesaj'] ?? '', 'prea multe mesaje'));

// Limita nu trebuie să încuie și intrarea în cont.
$cLogin = [];
$pagina = cerere($baza . '/login.php', $cLogin);
preg_match('/name="csrf"\s+value="([0-9a-f]+)"/', $pagina['corp'], $mm);
$r = json_din(cerere($baza . '/api/autentificare.php', $cLogin, [
    'csrf' => $mm[1] ?? '', 'email' => EMAIL_MEMBRU, 'parola' => PAROLA_TEST,
]));
verifica('intrarea în cont merge mai departe', true, $r['ok'] ?? false);

echo "\n=== MEMBRU CU TELEFON ÎN CONT ===\n";

db()->exec('DELETE FROM mesaje_contact');

$cM = [];
intra($cM, EMAIL_MEMBRU);
$pagina = cerere($baza . '/contact.php', $cM);

verifica('numele e precompletat', true,
    str_contains($pagina['corp'], 'value="Constantinescu Silviu"'));
verifica('adresa e precompletată', true, str_contains($pagina['corp'], 'value="' . EMAIL_MEMBRU . '"'));
verifica('telefonul e precompletat', true, str_contains($pagina['corp'], 'value="0733445566"'));
verifica('câmpurile sunt blocate', 3, substr_count($pagina['corp'], ' readonly'));

// Chiar dacă trimite altceva, serverul ia din cont.
$r = mesaj($cM, [
    'nume' => 'Altcineva Cutare', 'email' => 'hacker@exemplu-test.ro', 'telefon' => '0700000000',
]);
verifica('mesajul trece', true, $r['ok'] ?? false);

$m = ultimulMesaj();
verifica('numele e cel din cont, nu cel trimis', 'Constantinescu', (string) $m['nume']);
verifica('prenumele la fel', 'Silviu', (string) $m['prenume']);
verifica('adresa e cea din cont', EMAIL_MEMBRU, (string) $m['email']);
verifica('telefonul e cel din cont', '0733445566', (string) $m['telefon']);
verifica('mesajul e legat de membru', $idMembru, (int) $m['membru_id']);

echo "\n=== LIMITAREA PENTRU MEMBRI (1 la 5 minute) ===\n";

$r = mesaj($cM, ['mesaj' => 'Al doilea mesaj, imediat după primul, destul de lung.']);
verifica('al doilea imediat: oprit', false, $r['ok'] ?? true);
verifica('cu mesaj limpede', true, str_contains($r['mesaj'] ?? '', 'câteva minute'));
verifica('n-a ajuns în bază', 1, cateMesaje());

// Împingem mesajul în trecut: după cinci minute trebuie să meargă din nou.
db()->prepare('UPDATE mesaje_contact SET creat_la = ? WHERE membru_id = ?')
    ->execute([date('Y-m-d H:i:s', time() - 6 * 60), $idMembru]);

$r = mesaj($cM, ['mesaj' => 'După șase minute, al doilea mesaj trece.']);
verifica('după șase minute: merge', true, $r['ok'] ?? false);

echo "\n=== MEMBRU FĂRĂ TELEFON ÎN CONT ===\n";

db()->exec('DELETE FROM mesaje_contact');

$cF = [];
intra($cF, EMAIL_FARA_TEL);
$pagina = cerere($baza . '/contact.php', $cF);

verifica('telefonul e gol', true, str_contains($pagina['corp'], 'id="cf-phone"'));
verifica('și NU e blocat', 2, substr_count($pagina['corp'], ' readonly'));
verifica('i se spune că se salvează în cont', true, str_contains($pagina['corp'], 'contul tău'));

$r = mesaj($cF, ['telefon' => '']);
verifica('fără telefon: respins, deși e membru', true, !empty($r['erori']['telefon']));

$r = mesaj($cF, ['telefon' => '+40 722 99 88 77']);
verifica('cu telefon bun: trece', true, $r['ok'] ?? false);

$m = ultimulMesaj();
verifica('telefonul e adus la forma din bază', '0722998877', (string) $m['telefon']);

$q = db()->prepare('SELECT telefon FROM membri WHERE id = ?');
$q->execute([$idFaraTel]);
verifica('și s-a salvat în contul lui', '0722998877', (string) $q->fetchColumn());

echo "\n=== E-MAILUL CĂTRE NOI ===\n";

$log = dirname(__DIR__) . '/private/emailuri-trimise.log';
$tot = is_file($log) ? (string) file_get_contents($log) : '';
verifica('a plecat un e-mail cu mesajul', true, str_contains($tot, 'Mesaj nou de la'));
verifica('care poartă adresa omului', true, str_contains($tot, EMAIL_FARA_TEL));

/* ---------------------------- curățenie -------------------------------- */

db()->exec('DELETE FROM mesaje_contact');
foreach ([EMAIL_MEMBRU, EMAIL_FARA_TEL] as $e) {
    db()->prepare('DELETE FROM membri WHERE email = ?')->execute([$e]);
}

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
