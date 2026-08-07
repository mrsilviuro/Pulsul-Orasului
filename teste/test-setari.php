<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — pagina de setări și ștergerea contului.
 *
 * Ca și la „ține-mă minte", testul vorbește cu site-ul prin HTTP, cu cookie-uri
 * adevărate, și citește e-mailurile din private/emailuri-trimise.log — adică
 * exact ce ar face omul care apasă linkul.
 *
 * Cum se rulează:
 *     php -S 127.0.0.1:8128 -t . &
 *     php teste/test-setari.php http://127.0.0.1:8128
 */

require_once __DIR__ . '/../inc/stergere.php';

$baza = rtrim($argv[1] ?? 'http://127.0.0.1:8128', '/');

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

function cerere(string $url, array &$cookies, ?array $post = null): array
{
    $antete = ['User-Agent: Mozilla/5.0 (TestSetari)'];

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

    $unde = '';
    foreach ($ant as $a) {
        if (stripos($a, 'Location:') === 0) { $unde = trim(substr($a, 9)); }
    }

    return ['stare' => $stare, 'corp' => (string) $corp, 'unde' => $unde];
}

function json_din(array $r): array
{
    $d = json_decode($r['corp'], true);
    return is_array($d) ? $d : [];
}

/* ------------------------ membrii de încercare ------------------------- */

const EMAIL_CLASIC = 'setari-clasic@exemplu-test.ro';
const EMAIL_GOOGLE = 'setari-google@exemplu-test.ro';
const PAROLA_TEST  = 'ParolaDeTest#2026';

function faMembru(string $email, ?string $parola, ?string $googleId, string $permalink): int
{
    db()->prepare('DELETE FROM membri WHERE email = ?')->execute([$email]);
    db()->prepare('DELETE FROM incercari_autentificare WHERE email = ?')->execute([$email]);

    db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, parola_hash, google_id,
                             sex, data_nasterii, stare, creat_la, confirmat_la)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $permalink, 'Popescu', 'Ionuț', $email,
        $parola === null ? null : password_hash($parola, PASSWORD_DEFAULT),
        $googleId, 'M', '1990-05-20', 'activ', acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

function intra(array &$cookies, string $email, string $parola): array
{
    $pagina = cerere($GLOBALS['baza'] . '/login.php', $cookies);
    preg_match('/name="csrf"\s+value="([0-9a-f]+)"/', $pagina['corp'], $m);

    return cerere($GLOBALS['baza'] . '/api/autentificare.php', $cookies, [
        'csrf' => $m[1] ?? '', 'email' => $email, 'parola' => $parola, 'tine_minte' => '1',
    ]);
}

/** Token-ul CSRF al sesiunii, din linkul de ieșire. */
function csrf(array &$cookies): string
{
    $r = cerere($GLOBALS['baza'] . '/setari.php', $cookies);
    preg_match('/iesire\.php\?token=([0-9a-f]+)/', $r['corp'], $m);
    return $m[1] ?? '';
}

function coloana(int $id, string $coloana)
{
    $q = db()->prepare("SELECT `$coloana` FROM membri WHERE id = ? LIMIT 1");
    $q->execute([$id]);
    return $q->fetchColumn();
}

/** Ultimul link de ștergere scris în logul de e-mailuri. */
function ultimulLinkStergere(): string
{
    $log = dirname(__DIR__) . '/private/emailuri-trimise.log';
    $tot = is_file($log) ? (string) file_get_contents($log) : '';

    preg_match_all('#stergere\.php\?token=([0-9a-f]{64})#', $tot, $m);
    return $m[1] ? end($m[1]) : '';
}

$idClasic = faMembru(EMAIL_CLASIC, PAROLA_TEST, null, 'setclasic1');
$idGoogle = faMembru(EMAIL_GOOGLE, null, 'google-sub-test-1', 'setgoogle1');

/* ======================================================================= */
echo "=== CINE POATE INTRA PE PAGINĂ ===\n";

$anonim = [];
$r = cerere($baza . '/setari.php', $anonim);
verifica('nelogat: e trimis la intrare', 302, $r['stare']);
verifica('nelogat: cu întoarcere la setări', true, str_contains($r['unde'], 'setari.php'));

$c = [];
intra($c, EMAIL_CLASIC, PAROLA_TEST);
$pagina = cerere($baza . '/setari.php', $c);
verifica('logat: pagina se deschide', 200, $pagina['stare']);
verifica('are toate cele patru secțiuni', 4,
    (int) (str_contains($pagina['corp'], 'id="parola-form"')
         + str_contains($pagina['corp'], 'id="telefon-form"')
         + str_contains($pagina['corp'], 'id="newsletter-form"')
         + str_contains($pagina['corp'], 'id="stergere-form"')));

echo "\n=== 1. PAROLA: CONT CU PAROLĂ ===\n";

verifica('i se cere parola veche', true, str_contains($pagina['corp'], 'id="pn-veche"'));

$r = json_din(cerere($baza . '/api/parola-noua.php', $c, [
    'csrf' => csrf($c), 'parola_veche' => 'GresitaRau#1',
    'parola' => 'AltaParola#2026', 'parola_confirmare' => 'AltaParola#2026',
]));
verifica('parola veche greșită e respinsă', 'Parola de acum nu e corectă.',
    $r['erori']['parola_veche'] ?? '');

$r = json_din(cerere($baza . '/api/parola-noua.php', $c, [
    'csrf' => csrf($c), 'parola_veche' => PAROLA_TEST,
    'parola' => 'scurt', 'parola_confirmare' => 'scurt',
]));
verifica('parola prea scurtă e respinsă (regula de la înregistrare)', true,
    str_contains($r['erori']['parola'] ?? '', (string) PAROLA_MIN));

$r = json_din(cerere($baza . '/api/parola-noua.php', $c, [
    'csrf' => csrf($c), 'parola_veche' => PAROLA_TEST,
    'parola' => 'AltaParola#2026', 'parola_confirmare' => 'AltaParola#2026',
]));
verifica('parola bună se schimbă', true, $r['ok'] ?? false);
verifica('hash-ul din bază e cel nou', true,
    password_verify('AltaParola#2026', (string) coloana($idClasic, 'parola_hash')));

// Înapoi, pentru restul testelor.
db()->prepare('UPDATE membri SET parola_hash = ? WHERE id = ?')
    ->execute([password_hash(PAROLA_TEST, PASSWORD_DEFAULT), $idClasic]);

echo "\n=== 1b. PAROLA: CONT GOOGLE, FĂRĂ PAROLĂ ===\n";

verifica('în bază, parola_hash e NULL', null, coloana($idGoogle, 'parola_hash'));

// Intrarea se face pe scurtătură: fluxul Google are testul lui separat.
$cG = [];
db()->prepare('UPDATE membri SET parola_hash = ? WHERE id = ?')
    ->execute([password_hash('DoarPentruIntrare#1', PASSWORD_DEFAULT), $idGoogle]);
intra($cG, EMAIL_GOOGLE, 'DoarPentruIntrare#1');
db()->prepare('UPDATE membri SET parola_hash = NULL WHERE id = ?')->execute([$idGoogle]);

$paginaG = cerere($baza . '/setari.php', $cG);
verifica('NU i se cere parola veche', false, str_contains($paginaG['corp'], 'id="pn-veche"'));
verifica('scrie că a deschis contul cu Google', true,
    str_contains($paginaG['corp'], 'deschis contul cu Google'));

$r = json_din(cerere($baza . '/api/parola-noua.php', $cG, [
    'csrf' => csrf($cG),
    'parola' => 'PrimaMeaParola#7', 'parola_confirmare' => 'PrimaMeaParola#7',
]));
verifica('își pune prima parolă, fără cea veche', true, $r['ok'] ?? false);
verifica('hash-ul a apărut în bază', true,
    password_verify('PrimaMeaParola#7', (string) coloana($idGoogle, 'parola_hash')));
verifica('contul de Google rămâne legat', 'google-sub-test-1', coloana($idGoogle, 'google_id'));

echo "\n=== 2. TELEFONUL ===\n";

// Listă de perechi, nu tablou cu chei: PHP preface cheile numerice în
// numere întregi, iar „40722334455" ar pleca spre server ca număr, nu ca text.
foreach ([
    ['0722334455',      '0722334455'],
    ['0722 33 44 55',   '0722334455'],
    ['+40 722 334 455', '0722334455'],
    ['0040722334455',   '0722334455'],
    ['40722334455',     '0722334455'],
    ['0722-334-455',    '0722334455'],
    ['(0722) 334.455',  '0722334455'],
    ['0212223344',      '0212223344'],
] as [$scris, $asteptat]) {
    $r = json_din(cerere($baza . '/api/setari.php', $c, [
        'csrf' => csrf($c), 'sectiune' => 'telefon', 'telefon' => $scris,
    ]));
    verifica("„$scris" . '" → ' . $asteptat, $asteptat, (string) coloana($idClasic, 'telefon'));
}

foreach ([
    'prea scurt'        => '0722334',
    'prefix inexistent' => '0522334455',
    'litere'            => '07abcdefgh',
    'etichetă HTML'     => '<b>0722334455</b>',
    'injecție SQL'      => "0722334455'; DROP TABLE membri;--",
    'număr străin'      => '+33612345678',
] as $ce => $valoare) {
    $r = json_din(cerere($baza . '/api/setari.php', $c, [
        'csrf' => csrf($c), 'sectiune' => 'telefon', 'telefon' => $valoare,
    ]));
    verifica("respins: $ce", true, !empty($r['erori']['telefon']));
}

// Trimis ca număr JSON, nu ca text: nu trebuie luat drept „gol", altfel ar
// șterge numărul din cont fără ca nimeni să fi cerut asta.
db()->prepare('UPDATE membri SET telefon = ? WHERE id = ?')->execute(['0212223344', $idClasic]);
$r = json_din(cerere($baza . '/api/setari.php', $c, [
    'csrf' => csrf($c), 'sectiune' => 'telefon', 'telefon' => 722334455,
]));
verifica('respins: număr JSON în loc de text', true, !empty($r['erori']['telefon']));
verifica('și numărul din cont a rămas neatins', '0212223344', (string) coloana($idClasic, 'telefon'));

verifica('numărul bun a rămas neatins după încercările rele', '0212223344',
    (string) coloana($idClasic, 'telefon'));

$r = json_din(cerere($baza . '/api/setari.php', $c, [
    'csrf' => csrf($c), 'sectiune' => 'telefon', 'telefon' => '   ',
]));
verifica('gol înseamnă NULL, nu șir gol', null, coloana($idClasic, 'telefon'));

verifica('tabela membri e întreagă după injecție', 1,
    (int) db()->query('SELECT COUNT(*) FROM membri WHERE id = ' . $idClasic)->fetchColumn());

echo "\n=== 3. NEWSLETTERUL ===\n";

verifica('pornit din start la conturile care există deja', 1, (int) coloana($idClasic, 'newsletter'));

$r = json_din(cerere($baza . '/api/setari.php', $c, [
    'csrf' => csrf($c), 'sectiune' => 'newsletter', 'newsletter' => '',
]));
verifica('se poate opri', 0, (int) coloana($idClasic, 'newsletter'));
verifica('bifa apare nebifată în pagină', false,
    str_contains(cerere($baza . '/setari.php', $c)['corp'], 'id="st-newsletter"' . "\n" . '                   checked'));

$r = json_din(cerere($baza . '/api/setari.php', $c, [
    'csrf' => csrf($c), 'sectiune' => 'newsletter', 'newsletter' => '1',
]));
verifica('se poate porni la loc', 1, (int) coloana($idClasic, 'newsletter'));

echo "\n=== APĂRAREA PUNCTELOR DE INTRARE ===\n";

foreach ([
    'api/setari.php'        => ['sectiune' => 'telefon', 'telefon' => '0722334455'],
    'api/stergere-cere.php' => ['parola' => PAROLA_TEST],
] as $cale => $date) {
    $r = cerere($baza . '/' . $cale, $c, array_merge($date, ['csrf' => 'gresit']));
    verifica("$cale fără CSRF bun → 419", 419, $r['stare']);

    $gol = [];
    $r = cerere($baza . '/' . $cale, $gol, $date);
    verifica("$cale nelogat → 419 sau 401", true, in_array($r['stare'], [401, 419], true));

    $r = cerere($baza . '/' . $cale, $c);
    verifica("$cale prin GET → 405", 405, $r['stare']);
}

$r = json_din(cerere($baza . '/api/setari.php', $c, [
    'csrf' => csrf($c), 'sectiune' => 'inventat',
]));
verifica('secțiune necunoscută e refuzată', false, $r['ok'] ?? true);

echo "\n=== 4. ȘTERGEREA: CEREREA ===\n";

$r = json_din(cerere($baza . '/api/stergere-cere.php', $c, [
    'csrf' => csrf($c), 'parola' => 'GresitaRau#1',
]));
verifica('parola greșită e respinsă', 'Parola nu e corectă.', $r['erori']['parola'] ?? '');
verifica('nu s-a scris niciun token', null, coloana($idClasic, 'token_stergere'));

$r = json_din(cerere($baza . '/api/stergere-cere.php', $c, [
    'csrf' => csrf($c), 'parola' => '',
]));
verifica('parola goală e respinsă', true, !empty($r['erori']['parola']));

$r = json_din(cerere($baza . '/api/stergere-cere.php', $c, [
    'csrf' => csrf($c), 'parola' => PAROLA_TEST,
]));
verifica('parola bună trimite e-mailul', true, $r['ok'] ?? false);
verifica('în bază stă un token', 64, strlen((string) coloana($idClasic, 'token_stergere')));
verifica('contul e încă neatins', null, coloana($idClasic, 'cerere_stergere'));
verifica('e încă conectat', true,
    str_contains(cerere($baza . '/setari.php', $c)['corp'], 'id="parola-form"'));

$link = ultimulLinkStergere();
verifica('linkul din e-mail are un token de 64 de caractere', 64, strlen($link));
verifica('token-ul din e-mail NU e cel din bază (e hashuit)', true,
    $link !== '' && hash('sha256', $link) === (string) coloana($idClasic, 'token_stergere'));

echo "\n=== 4b. ȘTERGEREA: LINKUL DIN E-MAIL ===\n";

$strain = [];
$r = cerere($baza . '/stergere.php?token=' . str_repeat('a', 64), $strain);
verifica('token inventat: refuzat', true, str_contains($r['corp'], 'Link invalid'));

$r = cerere($baza . '/stergere.php?token=nuEsteToken', $strain);
verifica('token fără formă: refuzat', true, str_contains($r['corp'], 'Link invalid'));
verifica('contul tot neatins', null, coloana($idClasic, 'cerere_stergere'));

$r = cerere($baza . '/stergere.php?token=' . $link, $c);
verifica('token bun: răgazul pornește', true, coloana($idClasic, 'cerere_stergere') !== null);
verifica('token-ul s-a consumat', null, coloana($idClasic, 'token_stergere'));
verifica('pagina spune data ștergerii', true, str_contains($r['corp'], 'va fi șters pe'));
verifica('omul e dat afară din cont', false,
    str_contains(cerere($baza . '/setari.php', $c)['corp'], 'id="parola-form"'));
verifica('dispozitivele ținute minte au fost uitate', 0,
    (int) db()->query('SELECT COUNT(*) FROM sesiuni_amintite WHERE membru_id = ' . $idClasic)->fetchColumn());

$r = cerere($baza . '/stergere.php?token=' . $link, $strain);
verifica('același link, a doua oară: refuzat', true, str_contains($r['corp'], 'Link invalid'));

echo "\n=== 4c. INTRAREA ANULEAZĂ ȘTERGEREA ===\n";

$c2 = [];
$r = json_din(intra($c2, EMAIL_CLASIC, PAROLA_TEST));
verifica('contul încă primește intrarea', true, $r['ok'] ?? false);
verifica('cererea de ștergere a fost anulată', null, coloana($idClasic, 'cerere_stergere'));

$acasa = cerere($baza . '/index.php', $c2);
verifica('i se spune limpede pe ecran', true,
    str_contains($acasa['corp'], 'Ștergerea contului a fost anulată'));

echo "\n=== 4d. CRONUL DE ANONIMIZARE ===\n";

// Două conturi: unuia i s-a împlinit răgazul, celuilalt nu.
$idExpirat = faMembru('sters-expirat@exemplu-test.ro', PAROLA_TEST, null, 'setexpir01');
$idProaspat = faMembru('sters-proaspat@exemplu-test.ro', PAROLA_TEST, null, 'setproa001');

db()->prepare('UPDATE membri SET cerere_stergere = ?, telefon = ? WHERE id = ?')
    ->execute([date('Y-m-d H:i:s', time() - 31 * 24 * 3600), '0722334455', $idExpirat]);
db()->prepare('UPDATE membri SET cerere_stergere = ? WHERE id = ?')
    ->execute([date('Y-m-d H:i:s', time() - 29 * 24 * 3600), $idProaspat]);

$iesire = [];
exec('php ' . escapeshellarg(dirname(__DIR__) . '/cron/anonimizeaza-conturi.php') . ' --uscat 2>&1', $iesire);
$uscat = implode("\n", $iesire);
verifica('încercarea uscată îl vede pe cel expirat', true, str_contains($uscat, '#' . $idExpirat));
verifica('încercarea uscată NU îl atinge pe cel proaspăt', false, str_contains($uscat, '#' . $idProaspat));
verifica('încercarea uscată chiar nu schimbă nimic', 'Popescu', (string) coloana($idExpirat, 'nume'));

$iesire = [];
exec('php ' . escapeshellarg(dirname(__DIR__) . '/cron/anonimizeaza-conturi.php') . ' 2>&1', $iesire);

verifica('cel de 31 de zile a fost anonimizat', 'Șters', (string) coloana($idExpirat, 'nume'));
verifica('prenumele lui', 'Utilizator', (string) coloana($idExpirat, 'prenume'));
verifica('starea lui', 'sters', (string) coloana($idExpirat, 'stare'));
verifica('telefonul a dispărut', null, coloana($idExpirat, 'telefon'));
verifica('parola a dispărut', null, coloana($idExpirat, 'parola_hash'));
verifica('adresa nu mai e a lui', false,
    str_contains((string) coloana($idExpirat, 'email'), 'sters-expirat@exemplu-test.ro'));
verifica('adresa nouă e pe un domeniu care nu există', true,
    str_ends_with((string) coloana($idExpirat, 'email'), '@invalid.local'));
verifica('e scris când a fost anonimizat', true, coloana($idExpirat, 'anonimizat_la') !== null);

verifica('RÂNDUL RĂMÂNE ÎN BAZĂ', 1,
    (int) db()->query('SELECT COUNT(*) FROM membri WHERE id = ' . $idExpirat)->fetchColumn());

verifica('cel de 29 de zile e neatins', 'Popescu', (string) coloana($idProaspat, 'nume'));
verifica('și încă are cererea în picioare', true, coloana($idProaspat, 'cerere_stergere') !== null);

$cSters = [];
$r = json_din(intra($cSters, 'sters-expirat@exemplu-test.ro', PAROLA_TEST));
verifica('contul anonimizat nu mai primește pe nimeni', false, $r['ok'] ?? false);

$log = dirname(__DIR__) . '/private/conturi-anonimizate.log';
$continut = is_file($log) ? (string) file_get_contents($log) : '';
verifica('logul pomenește contul', true, str_contains($continut, '#' . $idExpirat));
verifica('logul NU păstrează adresa ștearsă', false,
    str_contains($continut, 'sters-expirat@exemplu-test.ro'));

// A doua rulare nu mai are ce face: nu anonimizează de două ori.
$iesire = [];
exec('php ' . escapeshellarg(dirname(__DIR__) . '/cron/anonimizeaza-conturi.php') . ' 2>&1', $iesire);
verifica('a doua rulare nu mai găsește nimic', true,
    str_contains(implode("\n", $iesire), '0 anonimizate'));

echo "\n=== ȘABLONUL DE E-MAIL: CUTIA CU VALOARE ===\n";

/**
 * Cutia a fost făcută pentru o parolă de șase caractere: mare, rărită,
 * monospațiată. Aceleași reguli aplicate unei date de 17 caractere făceau
 * mesajul să pară scris după alt tipar. Mărimea se ia acum după lungime.
 */
$paragraf = ['Un rând de text obișnuit, ca să avem cu ce compara.'];

$codScurt = sablonEmail('Parolă', [
    'paragrafe' => $paragraf,
    'cod' => ['eticheta' => 'Parola', 'valoare' => 'A7K2M9'],
])['html'];

$codLung = sablonEmail('Data', [
    'paragrafe' => $paragraf,
    'cod' => ['eticheta' => 'Data', 'valoare' => '6 septembrie 2026'],
])['html'];

verifica('parola scurtă rămâne mare (32px)', true, str_contains($codScurt, 'font-size:32px'));
verifica('și monospațiată, ca să se tasteze fără greșeală', true,
    str_contains($codScurt, 'Consolas'));

verifica('data lungă NU mai e la 32px', false, str_contains($codLung, 'font-size:32px'));
verifica('ci la mărimea obișnuită (22px)', true, str_contains($codLung, 'font-size:22px'));
verifica('fără rărirea de 7px între litere', false, str_contains($codLung, 'letter-spacing:7px'));
verifica('și fără font monospațiat', false,
    str_contains(substr($codLung, strpos($codLung, 'Data') ?: 0), 'Consolas'));

// Restul șablonului trebuie să rămână la fel în amândouă.
foreach (['font-size:16px', 'font-size:25px', 'max-width:600px'] as $bucata) {
    verifica('ambele păstrează ' . $bucata, true,
        str_contains($codScurt, $bucata) && str_contains($codLung, $bucata));
}

echo "\n=== CRONUL NU SE DESCHIDE DIN BROWSER ===\n";

$oricine = [];
$r = cerere($baza . '/cron/anonimizeaza-conturi.php', $oricine);
verifica('prin web răspunde 404', 404, $r['stare']);
verifica('și nu rulează nimic', false, str_contains($r['corp'], 'anonimizate'));

/* ---------------------------- curățenie -------------------------------- */

foreach ([EMAIL_CLASIC, EMAIL_GOOGLE] as $e) {
    db()->prepare('DELETE FROM membri WHERE email = ?')->execute([$e]);
}
db()->prepare('DELETE FROM membri WHERE id IN (?, ?)')->execute([$idExpirat, $idProaspat]);

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
