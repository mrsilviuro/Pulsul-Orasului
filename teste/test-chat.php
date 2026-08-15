<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — chatul.
 *
 * Cere BAZA DE DATE. Partea de API cere și SERVERUL, dar se sare singură dacă
 * nu i se dă o adresă.
 *
 * Cum se rulează:
 *     php teste/test-chat.php                        (fără API)
 *     php teste/test-chat.php http://127.0.0.1:8099  (cu tot)
 *
 * Își face singur oamenii și evenimentul de care are nevoie, cu nume care nu se
 * pot încurca cu ale nimănui, și le șterge la sfârșit — și dacă pică ceva la
 * mijloc, prin curata() de la coadă.
 */

require_once __DIR__ . '/../inc/chat.php';

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

/* ========================= oamenii de probă ========================== */

const SEMN = 'test-chat-';

/**
 * Camerele testului sunt ALE LUI, nu cele adevărate.
 *
 * Prima oară scriam de-a dreptul în 'general' și verificam că e goală la
 * început — ceea ce ținea numai cât nimeni nu vorbise încă acolo. O încărcare
 * de probă din browser a lăsat patru vorbe acolo, iar testul a început să pice
 * pe ceva ce nu era stricat.
 *
 * Pentru funcțiile de aici, numele unei camere e doar un șir: nu se uită la ce
 * e în ea, ci la egalitate. Deci camerele proprii verifică exact același lucru,
 * fără să atârne de starea bazei.
 */
const CAMERA  = 'tst-chat-c1';
const CAMERA2 = 'tst-chat-c2';

function faMembru(string $cheie, string $nume, string $prenume, bool $staff = false): int
{
    $q = db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, sex, data_nasterii,
                             parola_hash, stare, este_staff, creat_la, actualizat_la)
         VALUES (?,?,?,?,\'M\',\'1990-01-01\',?,\'activ\',?,?,?)'
    );

    $q->execute([
        substr('tstchat-' . $cheie, 0, 16),
        $nume, $prenume,
        SEMN . $cheie . '@invalid.local',
        password_hash('parolamea1', PASSWORD_DEFAULT),
        $staff ? 1 : 0,
        acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

function curata(): void
{
    // Mesajele pleacă în cascadă după membri (fk_chat_membru ON DELETE CASCADE),
    // dar camera unui eveniment nu e legată de nimic — se șterge de mână.
    db()->prepare('DELETE FROM mesaje_chat WHERE camera LIKE ?')->execute(['tst-chat-%']);
    db()->prepare('DELETE FROM mesaje_chat WHERE camera LIKE ?')->execute(['ev:tst-chat-%']);
    db()->prepare('DELETE FROM evenimente WHERE slug LIKE ?')->execute(['tst-chat-%']);
    db()->prepare('DELETE FROM membri WHERE email LIKE ?')->execute([SEMN . '%']);
}

curata();
register_shutdown_function('curata');

$ana   = faMembru('ana',   'Neagu', 'Elena');
$radu  = faMembru('radu',  'Rusu',  'Radu');
$sef   = faMembru('sef',   'Popa',  'Dan', true);

/* Un eveniment publicat, ca să existe o cameră de eveniment adevărată. */
$q = db()->prepare(
    'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, descriere, oras,
                             locatie, data_eveniment, ora_inceput, stare_moderare,
                             creat_la, actualizat_la)
     VALUES (?, (SELECT MIN(id) FROM categorii), ?, ?, ?, ?, "Centru", ?, "18:00:00",
             "aprobat", ?, ?)'
);
$q->execute([
    $ana, 'Seară de probă', 'tst-chat-ev', str_repeat('Text de probă. ', 20),
    oraseDisponibile()[0] ?? 'Roman', date('Y-m-d', strtotime('+5 days')), acum(), acum(),
]);

/* Și unul care N-A FOST APROBAT: camera lui nu trebuie să se deschidă nimănui
   în afară de organizator. */
$q->execute([
    $ana, 'Anunț neaprobat', 'tst-chat-ascuns', str_repeat('Text de probă. ', 20),
    oraseDisponibile()[0] ?? 'Roman', date('Y-m-d', strtotime('+5 days')), acum(), acum(),
]);
db()->prepare('UPDATE evenimente SET stare_moderare = ? WHERE slug = ?')
    ->execute(['in_asteptare', 'tst-chat-ascuns']);

/* ====================== 1. SLUGUL UNUI NUME ========================== */

sectiune('slugul');

verifica('un nume simplu',            'roman',        slugSimplu('Roman'));
verifica('diacriticele se aplatizează', 'piatra-neamt', slugSimplu('Piatra Neamț'));
verifica('și cele cu sedilă',         'targu-mures',  slugSimplu('Târgu Mureş'));
verifica('spațiile devin o cratimă',  'baia-mare',    slugSimplu('  Baia   Mare  '));
verifica('semnele se duc',            'sfantu-gheorghe', slugSimplu('Sfântu-Gheorghe!'));
verifica('numai semne = nimic',       '',             slugSimplu('!?...'));
verifica('se taie la lungimea cerută', 'abcde',       slugSimplu('abcdefghij', 5));

// Slugul de eveniment se face din același loc, deci trebuie să-l urmeze.
$slugEv = slugEveniment('Târg de Crăciun');
verifica('slugul de eveniment pornește la fel', 'targ-de-craciun-',
    substr($slugEv, 0, strlen('targ-de-craciun-')));
verifica('și are coadă întâmplătoare', 6, strlen($slugEv) - strlen('targ-de-craciun-'));
verifica('un titlu numai din semne are totuși slug', 'eveniment-',
    substr(slugEveniment('!!!'), 0, 10));

/* ========================== 2. CAMERELE ============================== */

sectiune('camerele');

$c = cameraCeruta(null, $ana);
verifica('fără nimic în adresă = General', 'general', $c['cheie']);
verifica('și se numește așa',              'General', $c['nume']);
verifica('felul e „general"',              'general', $c['fel']);

$c = cameraCeruta('', $ana);
verifica('adresa goală = tot General', 'general', $c['cheie']);

$primulOras = oraseDisponibile()[0] ?? 'Roman';
$slugOras   = slugSimplu($primulOras);

$c = cameraCeruta($slugOras, $ana);
verifica('slugul unui oraș din config deschide camera lui',
    'oras:' . $slugOras, $c['cheie']);
verifica('cu numele lui adevărat, cu diacritice', $primulOras, $c['nume']);
verifica('felul e „oras"',                        'oras',      $c['fel']);

$c = cameraCeruta('un-oras-inventat-xyz', $ana);
verifica('un oraș inventat cade pe General', 'general', $c['cheie']);

$c = cameraCeruta('tst-chat-ev', $ana);
verifica('slugul unui eveniment publicat deschide camera lui',
    'ev:tst-chat-ev', $c['cheie']);
verifica('felul e „eveniment"',   'eveniment',      $c['fel']);
verifica('numele e titlul lui',   'Seară de probă', $c['nume']);
verifica('și ține evenimentul cu el', 'tst-chat-ev',
    (string) ($c['eveniment']['slug'] ?? ''));

/**
 * Evenimentul ÎNTÂI, orașul pe urmă: dacă un eveniment ar fi slugit chiar ca un
 * oraș, camera lui câștigă. Regula cerută, verificată pe viu.
 */
db()->prepare('UPDATE evenimente SET slug = ? WHERE slug = ?')
    ->execute(['tst-chat-ev2', 'tst-chat-ev']);
$c = cameraCeruta('tst-chat-ev2', $ana);
verifica('slugul mutat duce în camera mutată', 'ev:tst-chat-ev2', $c['cheie']);
db()->prepare('UPDATE evenimente SET slug = ? WHERE slug = ?')
    ->execute(['tst-chat-ev', 'tst-chat-ev2']);

/* Un eveniment care nu e publicat: camera lui nu se deschide altcuiva. */
$c = cameraCeruta('tst-chat-ascuns', $radu);
verifica('camera unui anunț neaprobat cade pe General (pentru altcineva)',
    'general', $c['cheie']);

$c = cameraCeruta('tst-chat-ascuns', $ana);
verifica('și tot pe General pentru organizator, cât nu e publicat',
    'general', $c['cheie']);

/**
 * Un anunț PUBLICAT se vede de oricine — aceeași regulă ca pe event.php
 * (poateVedeaEvenimentul), deci camera lui se deschide și fără cont. La chat nu
 * se ajunge așa oricum: și pagina, și API-ul cer întâi intrarea în cont. Ce se
 * verifică aici e că regula e A LOR, nu una scrisă a doua oară pentru chat.
 */
$c = cameraCeruta('tst-chat-ev', 0);
verifica('camera unui anunț publicat ține de aceeași regulă ca pagina lui',
    'ev:tst-chat-ev', $c['cheie']);

/* Dar cea a unui anunț nepublicat rămâne închisă și fără cont. */
$c = cameraCeruta('tst-chat-ascuns', 0);
verifica('a unuia neaprobat, nu', 'general', $c['cheie']);

/* Litere mari în adresă: slugurile sunt mereu mici. */
$c = cameraCeruta(mb_strtoupper($slugOras, 'UTF-8'), $ana);
verifica('adresa scrisă cu majuscule duce tot acolo',
    'oras:' . $slugOras, $c['cheie']);

/* --- lista camerelor generale --- */

$camere = camereGenerale();
verifica('prima cameră din listă e General', 'general', $camere[0]['cheie']);
verifica('și n-are slug în adresă',          '',        $camere[0]['slug']);
verifica('sunt General + orașele din config',
    1 + count(oraseDisponibile()), count($camere));

$sluguri = array_column($camere, 'slug');
verifica('orașul din config e printre ele', true, in_array($slugOras, $sluguri, true));

verifica('adresa camerei generale',  'chat.php',              urlCamera(''));
verifica('adresa unei camere cu nume', 'chat.php?camera=roman', urlCamera('roman'));

/* ========================== 3. MESAJELE ============================== */

sectiune('mesajele');

verifica('camera e goală la început', 0, count(mesajeleCamerei(CAMERA)));

$m1 = salveazaMesajChat(CAMERA,  $ana,  'Salut tuturor!');
$m2 = salveazaMesajChat(CAMERA,  $radu, 'Salut, Elena.');
$m3 = salveazaMesajChat(CAMERA2, $ana,  'Aici e alt oraș.');

$fir = mesajeleCamerei(CAMERA);
verifica('două mesaje în camera generală', 2, count($fir));
verifica('în ordinea în care s-au spus', $m1, (int) $fir[0]['id']);
verifica('și al doilea după',            $m2, (int) $fir[1]['id']);
verifica('mesajul din altă cameră nu se amestecă', 1, count(mesajeleCamerei(CAMERA2)));

verifica('textul intră curat, cum a fost scris', 'Salut tuturor!', $fir[0]['mesaj']);
verifica('și cu autorul lui',                     $ana, (int) $fir[0]['membru_id']);
verifica('rândul știe în ce cameră e',            CAMERA, $fir[0]['camera']);

/* --- ce s-a mai spus de atunci --- */

$noi = mesajeDupa(CAMERA, $m1);
verifica('după primul mesaj a mai fost unul', 1,   count($noi));
verifica('și e chiar al doilea',              $m2, (int) $noi[0]['id']);

verifica('după ultimul nu mai e nimic', 0, count(mesajeDupa(CAMERA, $m2)));
verifica('„după 0" aduce tot',          2, count(mesajeDupa(CAMERA, 0)));

/* --- ultimele N --- */

for ($i = 0; $i < 5; $i++) {
    salveazaMesajChat(CAMERA, $ana, 'Mesajul ' . $i);
}

$ultimele = mesajeleCamerei(CAMERA, 3);
verifica('se cer ultimele trei', 3, count($ultimele));
verifica('și sunt chiar ultimele, în ordine',
    'Mesajul 4', $ultimele[2]['mesaj']);
verifica('cel mai vechi din teanc e al treilea de la coadă',
    'Mesajul 2', $ultimele[0]['mesaj']);

/* ========================== 4. ȘTERGEREA ============================= */

sectiune('ștergerea');

$inainte = count(mesajeleCamerei(CAMERA, 100));

$momentInainte = acum();
sleep(1);   // ca sters_la să fie limpede după $momentInainte

verifica('staff-ul poate șterge',      true,  poateStergeMesajChat(['id' => $m1], $sef,  true));
verifica('autorul nu poate',           false, poateStergeMesajChat(['id' => $m1], $ana,  false));
verifica('nici un alt membru',         false, poateStergeMesajChat(['id' => $m1], $radu, false));

verifica('ștergerea reușește', true, stergeMesajChat($m1, $sef));

$dupa = mesajeleCamerei(CAMERA, 100);
verifica('mesajul a plecat din fir', $inainte - 1, count($dupa));

$randSters = db()->query('SELECT * FROM mesaje_chat WHERE id = ' . $m1)->fetch();
verifica('dar rândul a rămas',        true, $randSters !== false);
verifica('cu semnul de șters pe el',  1,    (int) $randSters['sters']);
verifica('vorbele s-au dus',          '',   $randSters['mesaj']);
verifica('și se știe cine l-a șters', $sef, (int) $randSters['sters_de']);

verifica('a doua ștergere nu mai are ce face', false, stergeMesajChat($m1, $sef));
verifica('un mesaj care nu există, la fel',    false, stergeMesajChat(999999999, $sef));

/* --- piatra de mormânt, pentru filele deja deschise --- */

$sterse = mesajeSterseDupa(CAMERA, $momentInainte);
verifica('ștergerea se vede în piatra de mormânt', [$m1], $sterse);

verifica('din altă cameră nu se vede', [], mesajeSterseDupa(CAMERA2, $momentInainte));
verifica('de după ștergere nu se mai vede', [], mesajeSterseDupa(CAMERA, acum()));
verifica('fără clipă de plecare nu se întoarce nimic', [], mesajeSterseDupa(CAMERA, ''));

/* ========================= 5. VERIFICĂRILE =========================== */

sectiune('verificările mesajului');

verifica('mesajul gol nu trece',      'Scrie ceva înainte de a trimite.',
    verificaMesajChat('')['eroare']);
verifica('nici cel din spații',       'Scrie ceva înainte de a trimite.',
    verificaMesajChat("   \n  ")['eroare']);
verifica('nici altceva decât un text', 'Scrie ceva înainte de a trimite.',
    verificaMesajChat(null)['eroare']);

verifica('un singur caracter e de ajuns', '?', verificaMesajChat('?')['text']);
verifica('și nu se plânge de el',         '',  verificaMesajChat('?')['eroare']);

verifica('spațiile de la capete se taie', 'salut', verificaMesajChat('  salut  ')['text']);
verifica('rândurile omului rămân', "unu\ndoi", verificaMesajChat("unu\ndoi")['text']);
verifica('dar nu cincizeci de Enter-uri', "unu\n\ndoi",
    verificaMesajChat("unu\n\n\n\n\ndoi")['text']);

$prea = str_repeat('ă', MESAJ_CHAT_MAX + 1);
verifica('mesajul prea lung nu trece', true,
    str_starts_with(verificaMesajChat($prea)['eroare'], 'Mesajul e prea lung'));
verifica('exact la limită trece', MESAJ_CHAT_MAX,
    mb_strlen(verificaMesajChat(str_repeat('ă', MESAJ_CHAT_MAX))['text'], 'UTF-8'));

/**
 * Caracterele se numără cu mb_strlen, nu cu strlen — regula 10 din CLAUDE.md.
 * „ă" ocupă doi octeți, deci pe octeți limita ar fi fost pe jumătate pentru
 * cine scrie cu diacritice.
 */
verifica('se numără caractere, nu octeți', '',
    verificaMesajChat(str_repeat('ă', MESAJ_CHAT_MAX))['eroare']);

/* Textul intră NEESCAPAT în bază — regula 9 din CLAUDE.md. */
$idAmp = salveazaMesajChat(CAMERA, $ana, 'Dinamo & Rapid <b>azi</b>');
$randAmp = db()->query('SELECT mesaj FROM mesaje_chat WHERE id = ' . $idAmp)->fetch();
verifica('în bază intră text curat, neescapat',
    'Dinamo & Rapid <b>azi</b>', $randAmp['mesaj']);

/* ========================= 6. CUM ARATĂ ============================== */

sectiune('cum arată pe ecran');

$mesajAna = mesajChatDupaId($m2);   // scris de Radu

$html = randeazaMesajChat($mesajAna, ['membru_id' => $radu, 'e_staff' => false]);
verifica('mesajul meu stă la dreapta', true, str_contains($html, 'chat-msg--eu'));
verifica('și n-are chip',              false, str_contains($html, 'chat-msg__avatar'));
verifica('nici numele meu deasupra',   false, str_contains($html, 'chat-msg__cine'));

$html = randeazaMesajChat($mesajAna, ['membru_id' => $ana, 'e_staff' => false]);
verifica('al altuia stă la stânga', false, str_contains($html, 'chat-msg--eu'));
verifica('cu chipul lui',           true,  str_contains($html, 'chat-msg__avatar'));
verifica('și cu numele scurtat',    true,  str_contains($html, 'R. Radu'));
verifica('care duce pe profilul lui', true, str_contains($html, 'profil.php?m='));

verifica('fără drept de ștergere, niciun „×"', false,
    str_contains($html, 'data-sterge-mesaj'));

$html = randeazaMesajChat($mesajAna, ['membru_id' => $sef, 'e_staff' => true]);
verifica('staff-ul are „×"', true, str_contains($html, 'data-sterge-mesaj="' . $m2 . '"'));

/* Escaparea e la randare, cu h() — regula 9, celălalt capăt al ei. */
$htmlAmp = randeazaMesajChat(mesajChatDupaId($idAmp), ['membru_id' => $radu, 'e_staff' => false]);
verifica('la afișare, textul e escapat', true,
    str_contains($htmlAmp, 'Dinamo &amp; Rapid &lt;b&gt;azi&lt;/b&gt;'));
verifica('și nu se strecoară etichete', false, str_contains($htmlAmp, '<b>azi</b>'));

/* Rândurile omului rămân ale lui, ca <br>, nu ca text lipit. */
$idRanduri = salveazaMesajChat(CAMERA, $ana, "unu\ndoi");
$htmlRanduri = randeazaMesajChat(mesajChatDupaId($idRanduri), ['membru_id' => 0, 'e_staff' => false]);
// nl2br PUNE <br> înaintea rândului nou, nu în locul lui — de aceea „\n" e tot
// acolo. În HTML nu se vede: browserul îl socotește un spațiu.
verifica('rândul nou devine <br>', true, str_contains($htmlRanduri, "unu<br>\ndoi"));

/* Un teanc întreg. */
$teanc = randeazaMesajeChat(mesajeleCamerei(CAMERA, 3), ['membru_id' => $ana, 'e_staff' => false]);
verifica('teancul are trei bule', 3, substr_count($teanc, 'data-mesaj='));

/* Contul anonimizat: mesajul rămâne, numele nu. */
db()->prepare('UPDATE membri SET stare = ? WHERE id = ?')->execute(['sters', $radu]);
$htmlSters = randeazaMesajChat(mesajChatDupaId($m2), ['membru_id' => $ana, 'e_staff' => false]);
verifica('mesajul unui cont șters rămâne', true, str_contains($htmlSters, 'Salut, Elena.'));
verifica('dar fără nume',                  true, str_contains($htmlSters, 'Cont șters'));
verifica('și fără legătură spre profil',   false, str_contains($htmlSters, 'profil.php?m='));
db()->prepare('UPDATE membri SET stare = ? WHERE id = ?')->execute(['activ', $radu]);

/* ========================== 7. LIMITELE ============================== */

sectiune('limitele');

/* Ana tocmai a scris: cele două secunde dintre mesaje o opresc. */
$asteptare = asteptareChat($ana);
verifica('imediat după un mesaj se așteaptă', true, $asteptare > 0);
verifica('dar nu mai mult decât regula', true, $asteptare <= CHAT_SECUNDE_INTRE_MESAJE);

/* Cineva care n-a scris nimic scrie pe loc. */
verifica('cine n-a scris nimic nu așteaptă', 0, asteptareChat($sef));

/* Limita pe minut: se numără PESTE TOATE CAMERELE. */
$grabit = faMembru('grabit', 'Iancu', 'Vlad');

for ($i = 0; $i < CHAT_MESAJE_PE_MINUT; $i++) {
    // Scrise de-a dreptul în bază, cu ceasul dat înapoi, ca să nu fie nevoie
    // să aștepte testul două secunde între ele.
    db()->prepare('INSERT INTO mesaje_chat (camera, membru_id, mesaj, creat_la) VALUES (?,?,?,?)')
        ->execute([$i % 2 ? CAMERA : CAMERA2, $grabit, 'spam ' . $i,
                   date('Y-m-d H:i:s', time() - 30)]);
}

$asteptare = asteptareChat($grabit);
verifica('douăzeci de mesaje pe minut opresc al douăzeci și unulea', true, $asteptare > 0);
verifica('se așteaptă cât mai are fereastra, nu un minut întreg',
    true, $asteptare <= 31);

/* ============================ 8. API ================================= */

if ($BAZA === '') {
    echo "\n(partea de API s-a sărit — dă o adresă ca s-o rulezi:"
       . " php teste/test-chat.php http://127.0.0.1:8099)\n";
} else {
    sectiune('api');

    /** O cerere care ține minte cookie-urile, ca un browser. */
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
        $cod = 0;
        $cookieNou = $cookie;

        foreach ($http_response_header ?? [] as $rand) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $rand, $m) === 1) { $cod = (int) $m[1]; }
            if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $rand, $m) === 1) { $cookieNou = $m[1]; }
        }

        return [
            'cod'    => $cod,
            'corp'   => json_decode((string) $raspuns, true),
            'cookie' => $cookieNou,
        ];
    }

    /* --- fără cont nu se intră --- */

    $r = cere('/api/chat-mesaje.php?camera=');
    verifica('cititul cere cont', 401, $r['cod']);

    $r = cere('/api/chat.php', ['fapta' => 'trimite', 'mesaj' => 'salut']);
    verifica('scrisul cere întâi token', 419, $r['cod']);

    $r = cere('/api/chat-mesaje.php', ['ceva' => 1]);
    verifica('cititul nu primește POST', 405, $r['cod']);

    /* --- cu cont --- */

    $intrare = cere('/api/autentificare.php');   // ca să luăm un cookie de sesiune
    $cookie  = $intrare['cookie'];

    // Intrarea adevărată: pagina de login, ca să avem token + sesiune.
    $pagina = @file_get_contents($BAZA . '/login.php', false, stream_context_create([
        'http' => ['header' => "Cookie: $cookie\r\n", 'ignore_errors' => true],
    ]));

    foreach ($http_response_header ?? [] as $rand) {
        if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $rand, $m) === 1) { $cookie = $m[1]; }
    }

    preg_match('/name="csrf" value="([^"]+)"/', (string) $pagina, $m);
    $token = $m[1] ?? '';

    $r = cere('/api/autentificare.php', [
        'csrf'   => $token,
        'email'  => SEMN . 'ana@invalid.local',
        'parola' => 'parolamea1',
    ], $cookie);

    if (($r['corp']['ok'] ?? false) !== true) {
        echo "  (intrarea în cont n-a mers — restul părții de API se sare)\n";
    } else {
        $cookie = $r['cookie'];

        /* Tokenul de pe pagina de chat, cel de după autentificare. */
        $pagChat = @file_get_contents($BAZA . '/chat.php', false, stream_context_create([
            'http' => ['header' => "Cookie: $cookie\r\n", 'ignore_errors' => true],
        ]));
        preg_match('/name="csrf" value="([^"]+)"/', (string) $pagChat, $m);
        $token = $m[1] ?? '';

        verifica('pagina de chat se deschide cu cont', true,
            str_contains((string) $pagChat, 'data-chat'));
        verifica('și are camera generală în ea', true,
            str_contains((string) $pagChat, 'data-camera="general"'));

        /* --- citirea --- */

        $r = cere('/api/chat-mesaje.php?camera=&dupa=0', null, $cookie);
        verifica('cititul merge cu cont',    200,       $r['cod']);
        verifica('și spune din ce cameră',   'general', $r['corp']['camera'] ?? '');
        verifica('cu o clipă de la server',  true,
            (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $r['corp']['moment'] ?? ''));

        /* --- scrierea --- */

        sleep(CHAT_SECUNDE_INTRE_MESAJE);

        $r = cere('/api/chat.php', [
            'csrf' => $token, 'camera' => '', 'fapta' => 'trimite',
            'mesaj' => 'Mesaj prin API',
        ], $cookie);

        verifica('mesajul se scrie',    true, $r['corp']['ok'] ?? false);
        verifica('și vine gata desenat', true,
            str_contains($r['corp']['html'] ?? '', 'Mesaj prin API'));

        $idNou = (int) ($r['corp']['id'] ?? 0);

        /* Al doilea, imediat: limita dintre două mesaje îl oprește. */
        $r = cere('/api/chat.php', [
            'csrf' => $token, 'camera' => '', 'fapta' => 'trimite', 'mesaj' => 'și încă unul',
        ], $cookie);
        verifica('al doilea, pe loc, e oprit', 429, $r['cod']);
        verifica('și spune cât mai are de așteptat', true,
            ($r['corp']['asteptare'] ?? 0) > 0);

        /* Mesajul gol. */
        sleep(CHAT_SECUNDE_INTRE_MESAJE);
        $r = cere('/api/chat.php', [
            'csrf' => $token, 'camera' => '', 'fapta' => 'trimite', 'mesaj' => '   ',
        ], $cookie);
        verifica('mesajul gol e refuzat', 422, $r['cod']);

        /* O faptă pe care n-o știm. */
        $r = cere('/api/chat.php', [
            'csrf' => $token, 'camera' => '', 'fapta' => 'zboara',
        ], $cookie);
        verifica('o faptă necunoscută e refuzată', 400, $r['cod']);

        /* --- ștergerea: Ana nu e staff --- */

        $r = cere('/api/chat.php', [
            'csrf' => $token, 'camera' => '', 'fapta' => 'sterge', 'id' => $idNou,
        ], $cookie);
        verifica('cine nu e staff nu șterge', 403, $r['cod']);

        $ramas = db()->query('SELECT sters FROM mesaje_chat WHERE id = ' . $idNou)->fetchColumn();
        verifica('și mesajul e tot acolo', 0, (int) $ramas);

        /* --- un mesaj din ALTĂ cameră nu se șterge de aici --- */

        $idAlta = salveazaMesajChat(CAMERA2, $ana, 'din alt oraș');

        $r = cere('/api/chat.php', [
            'csrf' => $token, 'camera' => '', 'fapta' => 'sterge', 'id' => $idAlta,
        ], $cookie);
        verifica('un mesaj din altă cameră nu se vede de aici', 404, $r['cod']);

        /* --- GET-ul nu primește POST și invers --- */

        $r = cere('/api/chat.php?x=1', null, $cookie);
        verifica('scrisul nu primește GET', 405, $r['cod']);
    }
}

/* ============================= GATA ================================== */

echo "\n" . str_repeat('=', 60) . "\n";
echo "TOTAL: $treceri trecute, $picaturi picate\n";

exit($picaturi > 0 ? 1 : 0);
