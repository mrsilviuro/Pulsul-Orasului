<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — zona de administrare.
 *
 * Cere BAZA DE DATE. Partea de HTTP cere și SERVERUL, dar se sare singură dacă
 * nu i se dă o adresă.
 *
 * Cum se rulează:
 *     php teste/test-admin.php                        (fără HTTP)
 *     php teste/test-admin.php http://127.0.0.1:8099  (cu tot)
 *
 * Ce păzește, mai presus de orice: că PAGINILE ȘI FAPTELE SUNT NUMAI PENTRU
 * OAMENII CASEI. Restul verificărilor sunt despre ce se vede și ce se schimbă;
 * aceea e despre ce nu trebuie să se poată deloc.
 */

require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/comentarii.php';
// Pentru stampileazaCeleAprobate(), ștampila pe care o pune prima pagină.
require_once __DIR__ . '/../inc/dorinte.php';

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

const SEMN   = 'test-admin-';
const PAROLA = 'ParolaDeProba#2026';

function curata(): void
{
    db()->prepare('DELETE FROM mesaje_contact WHERE email LIKE ?')->execute([SEMN . '%']);
    db()->prepare('DELETE FROM evenimente WHERE slug LIKE ?')->execute(['tstadm-%']);
    db()->prepare(
        'DELETE d FROM dorinte d JOIN membri m ON m.id = d.membru_id
          WHERE m.permalink LIKE ?'
    )->execute(['tstadm-%']);
    db()->prepare('DELETE FROM membri WHERE permalink LIKE ?')->execute(['tstadm-%']);
}

curata();
register_shutdown_function('curata');

function faMembru(string $cheie, string $prenume, bool $staff = false): int
{
    db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, telefon, sex, data_nasterii,
                             parola_hash, stare, este_staff, creat_la, confirmat_la)
         VALUES (?,?,?,?,?,"M","1990-01-01",?,"activ",?,?,?)'
    )->execute([
        substr('tstadm-' . $cheie, 0, 16), 'Adminescu', $prenume,
        SEMN . $cheie . '@invalid.local', '',
        password_hash(PAROLA, PASSWORD_DEFAULT), $staff ? 1 : 0, acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

function faEveniment(int $cine, string $slug, string $stare): int
{
    db()->prepare(
        'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, oras, locatie,
                                 descriere, data_eveniment, ora_inceput, stare_moderare,
                                 creat_la, actualizat_la)
         VALUES (?,1,?,?,?,"Undeva",?,?,"19:00",?,?,?)'
    )->execute([
        $cine, 'Anunț de probă ' . $slug, $slug, oraseDisponibile()[0] ?? 'Roman',
        str_repeat('Povestea lui. ', 30), date('Y-m-d', strtotime('+9 days')),
        $stare, acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

$gazda = faMembru('gazda', 'Silviu', true);
$omul  = faMembru('omul',  'Andrei');

/* ==================================================================== */
sectiune('secțiunile și cifrele');

$chei = array_map(static fn(array $s): string => (string) $s['cheie'], sectiuniAdmin());

verifica('șase secțiuni', 6, count($chei));
verifica('în ordinea cerută',
    ['coduri', 'evenimente', 'comentarii', 'contact', 'useri', 'dorinte'], $chei);

/**
 * Fiecare secțiune trebuie să aibă o cifră care CHIAR se numără. O cheie
 * scrisă greșit ar fi dat tăcut zero, iar cartonașul n-ar fi spus niciodată că
 * are ceva de făcut.
 */
$cifre = cifreleAdmin();
$toateBune = true;

foreach (sectiuniAdmin() as $s) {
    if ($s['cifra'] !== null && !array_key_exists($s['cifra'], $cifre)) {
        $toateBune = false;
    }
}

verifica('fiecare secțiune are cifra ei', true, $toateBune);

// Și fiecare duce undeva: o adresă scrisă greșit ar fi trecut neobservată.
$lipsesc = [];

foreach (sectiuniAdmin() as $s) {
    if (!is_file(__DIR__ . '/../' . $s['href'])) {
        $lipsesc[] = $s['href'];
    }
}

verifica('fiecare secțiune are pagina ei', [], $lipsesc);

/* ==================================================================== */
sectiune('evenimentele');

$evAst  = faEveniment($omul, 'tstadm-asteapta', 'in_asteptare');
$evResp = faEveniment($omul, 'tstadm-respins',  'respins');
$evApr  = faEveniment($omul, 'tstadm-aprobat',  'aprobat');

$sluguri = static fn(array $l): array =>
    array_map(static fn(array $e): string => (string) $e['slug'], $l);

verifica('cele în așteptare se adună', true,
    in_array('tstadm-asteapta', $sluguri(evenimenteDupaStare('in_asteptare')), true));
verifica('și cele respinse', true,
    in_array('tstadm-respins', $sluguri(evenimenteDupaStare('respins')), true));
verifica('fără să se amestece', false,
    in_array('tstadm-aprobat', $sluguri(evenimenteDupaStare('respins')), true));

/* Ce atârnă de el se numără, ca omul de casă să vadă cât se pierde. */
db()->prepare('INSERT INTO comentarii (eveniment_id, membru_id, text, creat_la)
               VALUES (?,?,?,?)')->execute([$evResp, $omul, 'Ceva scris.', acum()]);
db()->prepare('INSERT INTO interese_evenimente (eveniment_id, membru_id, stare, creat_la, actualizat_la)
               VALUES (?,?,"participant",?,?)')->execute([$evResp, $omul, acum(), acum()]);

$randul = null;
foreach (evenimenteDupaStare('respins') as $e) {
    if ((string) $e['slug'] === 'tstadm-respins') { $randul = $e; }
}

verifica('rândul spune câte comentarii are', 1, (int) $randul['cate_comentarii']);
verifica('și câte înscrieri',                1, (int) $randul['cati_inscrisi']);

/**
 * ȘTERGEREA IA TOT. Rândurile din celelalte tabele pleacă în cascadă, prin
 * cheile străine — nu prin vreo listă scrisă de mână, care într-o zi n-ar mai
 * fi fost la fel cu cheile.
 */
verifica('ștergerea reușește', true, stergeEvenimentDeTot($randul));
verifica('anunțul a plecat', null, evenimentDupaSlug('tstadm-respins'));

$cate = static function (string $tabel, int $id): int {
    $q = db()->prepare('SELECT COUNT(*) FROM ' . $tabel . ' WHERE eveniment_id = ?');
    $q->execute([$id]);
    return (int) $q->fetchColumn();
};

verifica('comentariile lui, la fel', 0, $cate('comentarii', $evResp));
verifica('și înscrierile',           0, $cate('interese_evenimente', $evResp));

/* ==================================================================== */
sectiune('comentariile și rapoartele');

$evPub = faEveniment($gazda, 'tstadm-public', 'aprobat');

$scrie = static function (int $ev, int $cine, string $text, ?int $parinte = null) use (&$idCom): int {
    db()->prepare('INSERT INTO comentarii (eveniment_id, membru_id, parinte_id, text, creat_la)
                   VALUES (?,?,?,?,?)')->execute([$ev, $cine, $parinte, $text, acum()]);
    return (int) db()->lastInsertId();
};

$c1 = $scrie($evPub, $omul,  'TSTADM primul, cu răspuns sub el');
$c2 = $scrie($evPub, $gazda, 'TSTADM al doilea, raportat de doi');
$scrie($evPub, $gazda, 'TSTADM un răspuns', $c1);

foreach ([$omul, $gazda] as $cine) {
    db()->prepare('INSERT INTO comentarii_rapoarte (comentariu_id, membru_id, creat_la)
                   VALUES (?,?,?)')->execute([$c2, $cine, acum()]);
}
db()->prepare('INSERT INTO comentarii_rapoarte (comentariu_id, membru_id, creat_la)
               VALUES (?,?,?)')->execute([$c1, $omul, acum()]);

$raportate = comentariiRaportate();
$aleNoastre = array_values(array_filter($raportate,
    static fn(array $c): bool => in_array((int) $c['id'], [$c1, $c2], true)));

verifica('amândouă cele raportate se văd', 2, count($aleNoastre));
verifica('cel mai raportat, primul', $c2, (int) $aleNoastre[0]['id']);
verifica('cu numărul lui',            2, (int) $aleNoastre[0]['cate_rapoarte']);
verifica('și al doilea, cu al lui',   1, (int) $aleNoastre[1]['cate_rapoarte']);

$ultimele = ultimeleComentarii();
verifica('cel mai nou e primul', true,
    str_contains((string) $ultimele[0]['text'], 'TSTADM un răspuns'));
verifica('cel mult cincizeci', true, count($ultimele) <= ADMIN_COMENTARII);

/**
 * Ștergerea din administrare e ACEEAȘI faptă ca pe pagina evenimentului: un
 * comentariu principal cu răspunsuri sub el se GOLEȘTE. O ștergere scrisă
 * separat pentru staff ar fi lăsat răspunsurile suspendate în aer.
 */
$ce = stergeComentariu(comentariuDupaId($c1));
verifica('cel cu răspunsuri se golește', 'golit', $ce['fel']);
verifica('rândul rămâne, gol', 1, (int) comentariuDupaId($c1)['sters']);

$ce = stergeComentariu(comentariuDupaId($c2));
verifica('cel fără răspunsuri se șterge', 'sters', $ce['fel']);
verifica('și chiar a plecat', null, comentariuDupaId($c2));

/* ==================================================================== */
sectiune('mesajele de la contact');

db()->prepare('INSERT INTO mesaje_contact (nume, prenume, email, telefon, mesaj, creat_la, citit_la)
               VALUES (?,?,?,?,?,?,NULL)')
    ->execute(['Popa', 'Ion', SEMN . 'unu@invalid.local', '', 'Un mesaj de probă.', acum()]);

$idMesaj = (int) db()->lastInsertId();

$aleMele = static fn(): array => array_values(array_filter(mesajeDeContact(),
    static fn(array $m): bool => str_starts_with((string) $m['email'], SEMN)));

verifica('mesajul se vede în listă', 1, count($aleMele()));
verifica('și e necitit',          null, $aleMele()[0]['citit_la']);

db()->prepare('UPDATE mesaje_contact SET citit_la = ? WHERE id = ?')->execute([acum(), $idMesaj]);
verifica('însemnat citit, se vede', true, $aleMele()[0]['citit_la'] !== null);

/* ==================================================================== */
sectiune('căutarea de oameni');

db()->prepare('UPDATE membri SET telefon = ? WHERE id = ?')->execute(['0733445566', $omul]);

$gasit = static fn(string $q, int $id): bool =>
    in_array($id, array_map(static fn(array $m): int => (int) $m['id'], cautaMembri($q)), true);

verifica('după e-mail',   true, $gasit(SEMN . 'omul@invalid.local', $omul));
verifica('după prenume',  true, $gasit('Andrei', $omul));
verifica('după nume',     true, $gasit('Adminescu', $omul));
verifica('după nume întreg', true, $gasit('Andrei Adminescu', $omul));
verifica('după telefon',  true, $gasit('0733445566', $omul));

/**
 * TELEFONUL SE CAUTĂ ȘI ADUS LA FORMA DIN BAZĂ. În `membri` numărul stă mereu
 * ca „0733445566", dar cine caută îl scrie cum îl are în telefon. Fără trecerea
 * prin verificaTelefon(), căutarea ar fi răspuns „nimeni" pentru un număr care
 * există — cea mai supărătoare formă de nimic.
 */
verifica('scris cu +40',        true, $gasit('+40733445566', $omul));
verifica('scris cu 0040',       true, $gasit('0040733445566', $omul));
verifica('scris cu spații',     true, $gasit('0733 445 566', $omul));
verifica('fără zeroul din față', true, $gasit('733445566', $omul));

verifica('cine nu e, nu se găsește', false, $gasit('Nimeni Pe Lume', $omul));

// `%` scris de om nu e joker: altfel ar fi adus toată lista.
verifica('procentul nu aduce pe toți', false, $gasit('%', $omul));

/* ==================================================================== */
sectiune('limita de evenimente: gol nu e zero');

/**
 * NULL în bază = regula obișnuită (EVENIMENTE_ACTIVE_IMPLICIT). Zero = „omul
 * ăsta nu mai publică nimic". Arată la fel într-o căsuță goală și înseamnă
 * lucruri opuse — de aceea pagina lasă căsuța GOALĂ pentru NULL, iar punctul de
 * intrare scrie NULL înapoi când vine gol.
 */
db()->prepare('UPDATE membri SET limita_evenimente_active = NULL WHERE id = ?')->execute([$omul]);
verifica('NULL înseamnă regula obișnuită', EVENIMENTE_ACTIVE_IMPLICIT, limitaEvenimente($omul));

db()->prepare('UPDATE membri SET limita_evenimente_active = 0 WHERE id = ?')->execute([$omul]);
verifica('zero înseamnă chiar zero', 0, limitaEvenimente($omul));

db()->prepare('UPDATE membri SET limita_evenimente_active = 5 WHERE id = ?')->execute([$omul]);
verifica('un număr înseamnă numărul', 5, limitaEvenimente($omul));

db()->prepare('UPDATE membri SET limita_evenimente_active = NULL WHERE id = ?')->execute([$omul]);

/* ==================================================================== */
sectiune('dorințele');

db()->prepare('INSERT INTO dorinte (membru_id, oras, dorinta, stare_moderare, creat_la, publicat_la)
               VALUES (?,?,?,?,?,NULL)')
    ->execute([$omul, oraseDisponibile()[0] ?? 'Roman', 'TSTADM o dorință care așteaptă',
               'in_asteptare', acum()]);

$idDorinta = (int) db()->lastInsertId();

$aNoastra = static function () use ($idDorinta): ?array {
    foreach (toateDorintele() as $d) {
        if ((int) $d['id'] === $idDorinta) { return $d; }
    }
    return null;
};

verifica('dorința se vede în listă', true, $aNoastra() !== null);
verifica('cu starea ei', 'in_asteptare', (string) $aNoastra()['stare_moderare']);

/**
 * Cele care așteaptă stau în CAPUL listei: acolo e treaba. Așezată după dată,
 * dorința netrecută pe la nimeni ar fi rămas la mijloc, între alte douăzeci.
 */
$primele = array_slice(toateDorintele(), 0, cifreleAdmin()['dorinte']);
$toateAsteapta = true;

foreach ($primele as $d) {
    if ((string) $d['stare_moderare'] !== 'in_asteptare') { $toateAsteapta = false; }
}

verifica('cele în așteptare sunt în cap', true, $toateAsteapta);

/**
 * `publicat_la` NU se pune la aprobare: îl scrie stampileazaCeleAprobate() la
 * prima încărcare a primei pagini, cu ceasul PHP. Așa, o dorință aprobată de
 * aici și una aprobată din phpMyAdmin se poartă la fel.
 */
db()->prepare('UPDATE dorinte SET stare_moderare = "aprobat" WHERE id = ?')->execute([$idDorinta]);
verifica('aprobată, tot fără ștampilă', null, $aNoastra()['publicat_la']);

stampileazaCeleAprobate();
verifica('ștampila vine de la prima pagină', true, $aNoastra()['publicat_la'] !== null);

/* ==================================================================== */
if ($BAZA === '') {
    echo "\n(sar peste HTTP: dă adresa serverului ca argument, "
       . "ex. php teste/test-admin.php http://127.0.0.1:8099)\n";
} else {
    sectiune('paza: paginile');

    /** Ce răspunde o pagină pentru cine nu e conectat. */
    $ia = static function (string $cale) use ($BAZA): array {
        $raw = @file_get_contents($BAZA . $cale, false, stream_context_create([
            'http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10],
        ]));

        $cod  = 0;
        $unde = '';

        foreach ($http_response_header ?? [] as $rand) {
            if (preg_match('~^HTTP/\S+\s+(\d+)~', $rand, $m)) { $cod = (int) $m[1]; }
            if (stripos($rand, 'Location:') === 0) { $unde = trim(substr($rand, 9)); }
        }

        return ['cod' => $cod, 'unde' => $unde, 'corp' => (string) $raw];
    };

    $pagini = ['/admin.php', '/admin-evenimente.php', '/admin-comentarii.php',
               '/admin-contact.php', '/admin-useri.php', '/admin-dorinte.php',
               '/coduri.php'];

    $toateInchise = true;
    $scapate      = [];

    foreach ($pagini as $cale) {
        $r = $ia($cale);

        // 302 spre login: nimeni nu vede nimic fără cont.
        if ($r['cod'] !== 302 || !str_contains($r['unde'], 'login.php')) {
            $toateInchise = false;
            $scapate[]    = $cale . ' → ' . $r['cod'] . ' ' . $r['unde'];
        }
    }

    verifica('toate paginile cer cont', true, $toateInchise);

    if ($scapate !== []) {
        echo '   scăpate: ' . implode(', ', $scapate) . "\n";
    }

    // Și niciuna nu scapă nimic în corp, chiar dacă redirecționează.
    $scurgeri = false;

    foreach ($pagini as $cale) {
        if (str_contains($ia($cale)['corp'], 'admin-tabel')) { $scurgeri = true; }
    }

    verifica('și niciuna nu scapă tabelul', false, $scurgeri);

    sectiune('paza: punctul de intrare');

    $cheama = static function (array $date) use ($BAZA): array {
        $raw = @file_get_contents($BAZA . '/api/admin.php', false, stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => json_encode($date),
                'ignore_errors' => true, 'timeout' => 10,
            ],
        ]));

        $cod = 0;

        foreach ($http_response_header ?? [] as $rand) {
            if (preg_match('~^HTTP/\S+\s+(\d+)~', $rand, $m)) { $cod = (int) $m[1]; }
        }

        return ['cod' => $cod, 'corp' => json_decode((string) $raw, true)];
    };

    $r = $cheama(['fapta' => 'sterge-dorinta', 'id' => $idDorinta]);
    verifica('fără token CSRF, 419', 419, $r['cod']);

    $r = $cheama(['csrf' => 'oarecare', 'fapta' => 'sterge-dorinta', 'id' => $idDorinta]);
    verifica('nelogat, nu trece', true, in_array($r['cod'], [401, 419], true));

    verifica('și dorința e tot acolo', true, $aNoastra() !== null);

    // GET nu e primit deloc: faptele se fac numai prin POST.
    $raw = @file_get_contents($BAZA . '/api/admin.php', false, stream_context_create([
        'http' => ['ignore_errors' => true, 'timeout' => 10],
    ]));
    $cod = 0;
    foreach ($http_response_header ?? [] as $rand) {
        if (preg_match('~^HTTP/\S+\s+(\d+)~', $rand, $m)) { $cod = (int) $m[1]; }
    }
    verifica('GET nu e primit', 405, $cod);
}

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
