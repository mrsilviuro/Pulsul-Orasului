<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — aprobarea și respingerea anunțurilor, de către staff.
 *
 * Cere BAZA DE DATE. Partea de pagini și de API cere și SERVERUL, dar se sare
 * singură dacă nu i se dă o adresă.
 *
 * Cum se rulează:
 *     php teste/test-moderare.php                        (fără API)
 *     php teste/test-moderare.php http://127.0.0.1:8099  (cu tot)
 *
 * Își face singur oamenii și evenimentele de care are nevoie, cu nume care nu
 * se pot încurca cu ale nimănui, și le șterge la sfârșit — și dacă pică ceva la
 * mijloc, prin curata() de la coadă.
 */

require_once __DIR__ . '/../inc/evenimente.php';

$BAZA = $argv[1] ?? '';

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

const SEMN   = 'test-moderare-';
const PAROLA = 'ParolaDeProba#2026';

function curata(): void
{
    db()->prepare('DELETE FROM evenimente WHERE slug LIKE ?')->execute(['tst-mod-%']);
    db()->prepare('DELETE FROM membri WHERE email LIKE ?')->execute([SEMN . '%']);
    db()->prepare('DELETE FROM membri WHERE permalink LIKE ?')->execute(['tstmod-%']);
}

curata();
register_shutdown_function('curata');

function faMembru(string $cheie, bool $staff): int
{
    db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, sex, data_nasterii,
                             parola_hash, stare, este_staff, creat_la, confirmat_la)
         VALUES (?,?,?,?,\'M\',\'1990-01-01\',?,\'activ\',?,?,?)'
    )->execute([
        substr('tstmod-' . $cheie, 0, 16), 'Popa', 'Dan',
        SEMN . $cheie . '@invalid.local',
        password_hash(PAROLA, PASSWORD_DEFAULT),
        $staff ? 1 : 0, acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

$sef = faMembru('sef', true);
$org = faMembru('org', false);

function faEveniment(string $slug, int $organizator, string $stare): array
{
    db()->prepare(
        'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, descriere, oras,
                                 locatie, data_eveniment, ora_inceput, stare_moderare,
                                 creat_la, actualizat_la)
         VALUES (?, (SELECT MIN(id) FROM categorii), ?, ?, ?, ?, "Centru", ?, "18:00:00",
                 ?, ?, ?)'
    )->execute([
        $organizator, 'Proba ' . $slug, $slug, str_repeat('Text de probă. ', 20),
        oraseDisponibile()[0] ?? 'Roman', date('Y-m-d', strtotime('+8 days')),
        $stare, acum(), acum(),
    ]);

    return evenimentDupaSlug($slug);
}

/* ===================== 1. CE SE POATE MODERA ======================== */

sectiune('ce se poate modera');

foreach (['in_asteptare', 'aprobat', 'respins'] as $stare) {
    verifica('din „' . $stare . '" se poate', true,
        poateFiModerat(['stare_moderare' => $stare]));
}

foreach (['anulat', 'incheiat'] as $stare) {
    verifica('din „' . $stare . '" NU se poate', false,
        poateFiModerat(['stare_moderare' => $stare]));
}

verifica('nici dintr-o stare pe care n-o știm', false,
    poateFiModerat(['stare_moderare' => 'altceva']));
verifica('nici dintr-un rând fără stare', false, poateFiModerat([]));

verifica('se pot pune doar două stări', ['aprobat', 'respins'], STARI_DE_MODERAT);

/* ===================== 2. SCRIEREA ================================== */

sectiune('scrierea');

$ev = faEveniment('tst-mod-1', $org, 'in_asteptare');

moderezaEveniment($ev, 'aprobat');
verifica('aprobarea se scrie', 'aprobat',
    (string) evenimentDupaSlug('tst-mod-1')['stare_moderare']);

$ev = evenimentDupaSlug('tst-mod-1');
moderezaEveniment($ev, 'respins');
verifica('și răzgândirea, la fel', 'respins',
    (string) evenimentDupaSlug('tst-mod-1')['stare_moderare']);

/* Motivul anulării nu se atinge — nu e treaba moderării. */
db()->prepare('UPDATE evenimente SET motiv_anulare = ? WHERE slug = ?')
    ->execute(['un motiv oarecare', 'tst-mod-1']);
moderezaEveniment(evenimentDupaSlug('tst-mod-1'), 'aprobat');
verifica('motivul anulării rămâne neatins', 'un motiv oarecare',
    (string) evenimentDupaSlug('tst-mod-1')['motiv_anulare']);

/* ===================== 3. PRIN SERVER =============================== */

if ($BAZA === '') {
    echo "\n(partea de API s-a sărit — dă o adresă ca s-o rulezi:"
       . " php teste/test-moderare.php http://127.0.0.1:8099)\n";
} else {
    sectiune('prin server');

    function cere(string $cale, ?array $trup = null, string $cookie = ''): array
    {
        global $BAZA;

        $ctx = [
            'http' => [
                'method'        => $trup === null ? 'GET' : 'POST',
                'header'        => "Content-Type: application/json\r\n"
                                 . ($cookie !== '' ? "Cookie: $cookie\r\n" : ''),
                'content'       => $trup === null ? '' : json_encode($trup),
                'ignore_errors' => true,
            ],
        ];

        $raspuns = @file_get_contents($BAZA . $cale, false, stream_context_create($ctx));
        $cod = 0; $cookieNou = $cookie;

        foreach ($http_response_header ?? [] as $rand) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $rand, $m) === 1) { $cod = (int) $m[1]; }
            if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $rand, $m) === 1) { $cookieNou = $m[1]; }
        }

        return ['cod' => $cod, 'corp' => (string) $raspuns, 'cookie' => $cookieNou];
    }

    /** Intră în cont și întoarce cookie-ul plus tokenul de pe pagina cerută. */
    function intra(string $email, string $pagina): array
    {
        $p = cere('/login.php');
        $cookie = $p['cookie'];
        preg_match('/name="csrf" value="([^"]+)"/', $p['corp'], $m);

        $r = cere('/api/autentificare.php', [
            'csrf' => $m[1] ?? '', 'email' => $email, 'parola' => PAROLA,
        ], $cookie);

        $corp = json_decode($r['corp'], true) ?: [];

        if (($corp['ok'] ?? false) !== true) {
            return ['cookie' => '', 'token' => '', 'corp' => ''];
        }

        $cookie = $r['cookie'];
        $pag = cere($pagina, null, $cookie);

        $token = '';
        if (preg_match('/data-csrf="([^"]+)"/', $pag['corp'], $m) === 1
            || preg_match('/name="csrf" value="([^"]+)"/', $pag['corp'], $m) === 1) {
            $token = $m[1];
        }

        return ['cookie' => $cookie, 'token' => $token, 'corp' => $pag['corp']];
    }

    /* Un anunț proaspăt, în așteptare. */
    faEveniment('tst-mod-2', $org, 'in_asteptare');
    $adresa = '/event.php?slug=tst-mod-2';

    /* --- ce vede fiecare în PAGINĂ --- */

    $caOrg = intra(SEMN . 'org@invalid.local', $adresa);

    if ($caOrg['cookie'] === '') {
        echo "  (intrarea în cont n-a mers — restul s-a sărit)\n";
    } else {
        verifica('organizatorul își vede anunțul', true,
            str_contains($caOrg['corp'], 'Proba tst-mod-2'));
        verifica('dar NU vede blocul de moderare', false,
            str_contains($caOrg['corp'], 'data-moderare'));
        verifica('nici butonul de aprobat', false,
            str_contains($caOrg['corp'], 'data-modereaza="aprobat"'));
        verifica('nici pe cel de respins', false,
            str_contains($caOrg['corp'], 'data-modereaza="respins"'));

        $caSef = intra(SEMN . 'sef@invalid.local', $adresa);

        verifica('omul de casă vede blocul', true,
            str_contains($caSef['corp'], 'data-moderare'));
        verifica('cu butonul de aprobat', true,
            str_contains($caSef['corp'], 'data-modereaza="aprobat"'));
        verifica('și cu cel de respins', true,
            str_contains($caSef['corp'], 'data-modereaza="respins"'));

        /* --- cine NU e staff nu poate, nici dacă cere de-a dreptul --- */

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caOrg['token'], 'slug' => 'tst-mod-2', 'stare' => 'aprobat',
        ], $caOrg['cookie']);

        verifica('cererea de la cine nu e staff e refuzată', 403, $r['cod']);
        verifica('și anunțul rămâne neatins', 'in_asteptare',
            (string) evenimentDupaSlug('tst-mod-2')['stare_moderare']);

        /* Nici fără cont. */
        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => 'orice', 'slug' => 'tst-mod-2', 'stare' => 'aprobat',
        ]);
        verifica('fără cont, nici atât', true, in_array($r['cod'], [401, 419], true), (string) $r['cod']);

        /* --- omul de casă poate --- */

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-2', 'stare' => 'aprobat',
        ], $caSef['cookie']);

        $corp = json_decode($r['corp'], true) ?: [];

        verifica('staff-ul aprobă',            true,      $corp['ok'] ?? false);
        verifica('și starea chiar s-a scris',  'aprobat',
            (string) evenimentDupaSlug('tst-mod-2')['stare_moderare']);
        verifica('cu întoarcerea pe pagină',   true,
            str_contains((string) ($corp['redirect'] ?? ''), 'tst-mod-2'));

        /* A doua oară, aceeași stare: nu e o eroare adevărată, dar se spune. */
        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-2', 'stare' => 'aprobat',
        ], $caSef['cookie']);
        verifica('aprobat de două ori nu se poate', 409, $r['cod']);

        /* Respingerea unui anunț aprobat: răzgândirea merge. */
        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-2', 'stare' => 'respins',
        ], $caSef['cookie']);
        verifica('răzgândirea merge', true, (json_decode($r['corp'], true) ?: [])['ok'] ?? false);
        verifica('și se scrie',       'respins',
            (string) evenimentDupaSlug('tst-mod-2')['stare_moderare']);

        /* --- stări pe care nu le știm --- */

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-2', 'stare' => 'incheiat',
        ], $caSef['cookie']);
        verifica('„incheiat" nu se pune de aici', 422, $r['cod']);

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-2', 'stare' => 'anulat',
        ], $caSef['cookie']);
        verifica('nici „anulat"', 422, $r['cod']);

        verifica('iar anunțul a rămas respins', 'respins',
            (string) evenimentDupaSlug('tst-mod-2')['stare_moderare']);

        /* --- anulat și încheiat nu se mai moderează --- */

        faEveniment('tst-mod-anulat', $org, 'anulat');
        faEveniment('tst-mod-incheiat', $org, 'incheiat');

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-anulat', 'stare' => 'aprobat',
        ], $caSef['cookie']);
        verifica('un anunț anulat nu se aprobă', 409, $r['cod']);
        verifica('și rămâne anulat', 'anulat',
            (string) evenimentDupaSlug('tst-mod-anulat')['stare_moderare']);

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'tst-mod-incheiat', 'stare' => 'respins',
        ], $caSef['cookie']);
        verifica('nici unul încheiat nu se respinge', 409, $r['cod']);

        /* Și nici blocul nu se mai arată la ele. */
        $pag = cere('/event.php?slug=tst-mod-anulat', null, $caSef['cookie']);
        verifica('la anulat, blocul nu se mai scrie', false,
            str_contains($pag['corp'], 'data-moderare'));

        $pag = cere('/event.php?slug=tst-mod-incheiat', null, $caSef['cookie']);
        verifica('nici la încheiat', false, str_contains($pag['corp'], 'data-moderare'));

        /* --- butonul stării de acum lipsește --- */

        $pag = cere('/event.php?slug=tst-mod-2', null, $caSef['cookie']);   // e respins
        verifica('la un anunț respins nu se mai oferă „Respinge"', false,
            str_contains($pag['corp'], 'data-modereaza="respins"'));
        verifica('dar „Aprobă" se oferă', true,
            str_contains($pag['corp'], 'data-modereaza="aprobat"'));

        /* --- un slug care nu duce nicăieri --- */

        $r = cere('/api/modereaza-eveniment.php', [
            'csrf' => $caSef['token'], 'slug' => 'nu-exista-nicaieri', 'stare' => 'aprobat',
        ], $caSef['cookie']);
        verifica('un slug inventat e 404', 404, $r['cod']);

        /* --- doar POST --- */

        $r = cere('/api/modereaza-eveniment.php', null, $caSef['cookie']);
        verifica('nu primește GET', 405, $r['cod']);
    }
}

/* ============================= GATA ================================== */

echo "\n" . str_repeat('=', 60) . "\n";
echo "TOTAL: $treceri trecute, $picaturi picate\n";

exit($picaturi > 0 ? 1 : 0);
