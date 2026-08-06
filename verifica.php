<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — pagina de verificare a serverului.
 *
 * Spune, pe rând, ce merge și ce nu: setările, baza de date, tabelele,
 * drepturile de scriere, extensiile de PHP. E de folos mai ales imediat după
 * mutarea pe o găzduire nouă, când site-ul se încarcă dar formularele nu.
 *
 * ---------------------------------------------------------------------------
 *  DE CITIT ÎNAINTE DE A O FOLOSI
 * ---------------------------------------------------------------------------
 *
 *  1. Pagina nu spune nimic fără cheie. Cheia se pune în inc/config.php, la
 *     'cheie_diagnostic', și trebuie să fie un șir lung și întâmplător.
 *
 *  2. ȘTERGE FIȘIERUL DE PE SERVER după ce ai terminat. Nu arată parole și nu
 *     arată căi de pe disc, dar tot spune cuiva din afară cu ce e construit
 *     serverul — iar asta nu are de ce să stea la vedere permanent.
 *
 *  3. Nu se sprijină pe restul aplicației: dacă ar face-o, în cazurile în care
 *     chiar e nevoie de ea nu ar porni nici ea.
 */

header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: text/html; charset=utf-8');

$RADACINA = __DIR__;

/* ===================== 1. Setările, înainte de orice ==================== */

$caleConfig = $RADACINA . '/inc/config.php';
$config     = null;
$eroareConfig = '';

if (!is_file($caleConfig)) {
    $eroareConfig = 'lipsă';
} else {
    try {
        $config = require $caleConfig;
        if (!is_array($config)) {
            $eroareConfig = 'nu întoarce un tablou de setări';
            $config = null;
        }
    } catch (Throwable $e) {
        $eroareConfig = 'are o greșeală de scriere și nu poate fi citit';
    }
}

/* ---------------------- Poarta: cheia de diagnostic -------------------- */

$cheieAsteptata = is_array($config) ? (string) ($config['cheie_diagnostic'] ?? '') : '';
$cheiePrimita   = isset($_GET['cheie']) && is_string($_GET['cheie']) ? $_GET['cheie'] : '';

/**
 * Când fișierul de setări lipsește cu totul, spunem asta fără cheie: e chiar
 * răspunsul căutat, iar cheia oricum ar fi trebuit citită tot de acolo.
 */
$potIntra = ($eroareConfig !== '')
    || ($cheieAsteptata !== '' && hash_equals($cheieAsteptata, $cheiePrimita));

/* ============================ 2. Verificările ========================== */

$sectiuni = [];

/** Adaugă un rezultat: stare = 'bun' | 'atentie' | 'rau' | 'nestiut'. */
function rezultat(string $sectiune, string $ce, string $stare, string $detaliu = '', string $sfat = ''): void
{
    global $sectiuni;
    $sectiuni[$sectiune][] = compact('ce', 'stare', 'detaliu', 'sfat');
}

if ($potIntra) {

    /* ------------------------------ PHP ------------------------------- */

    $phpBun = version_compare(PHP_VERSION, '8.0', '>=');
    rezultat('PHP', 'Versiunea PHP', $phpBun ? 'bun' : 'rau', PHP_VERSION,
        $phpBun ? '' : 'Aplicația cere PHP 8.0 sau mai nou. Se schimbă din panoul găzduirii.');

    $extensii = [
        'pdo_mysql' => ['rau',     'Fără ea nu se poate vorbi cu baza de date.'],
        'mbstring'  => ['rau',     'Fără ea, diacriticele se strică.'],
        'json'      => ['rau',     'Fără ea, formularele nu pot răspunde.'],
        'gd'        => ['rau',     'Fără ea nu merg pozele de profil.'],
        'fileinfo'  => ['atentie', 'Se pierde o verificare la încărcarea pozelor.'],
        'exif'      => ['atentie', 'Pozele făcute cu telefonul pot ieși culcate pe o parte.'],
    ];

    foreach ($extensii as $nume => [$gravitate, $sfat]) {
        $are = extension_loaded($nume);
        rezultat('PHP', 'Extensia ' . $nume, $are ? 'bun' : $gravitate,
            $are ? 'pornită' : 'lipsește', $are ? '' : $sfat);
    }

    $limitaIncarcare = min(
        octetiDin((string) ini_get('upload_max_filesize')),
        octetiDin((string) ini_get('post_max_size'))
    );
    rezultat('PHP', 'Cât se poate încărca', $limitaIncarcare >= 6 * 1024 * 1024 ? 'bun' : 'atentie',
        round($limitaIncarcare / 1024 / 1024, 1) . ' MB',
        'Pozele de profil sunt limitate la 6 MB. Dacă serverul acceptă mai puțin, '
      . 'ridică upload_max_filesize și post_max_size din php.ini sau din panou.');

    /* ---------------------------- Setările ---------------------------- */

    if ($eroareConfig === 'lipsă') {
        rezultat('Setări', 'Fișierul inc/config.php', 'rau', 'lipsește de pe server',
            'E cea mai probabilă cauză a mesajului „nu am putut lua legătura cu serverul". '
          . 'Fișierul e trecut în .gitignore, tocmai ca parolele să nu ajungă pe GitHub, '
          . 'deci nu se copiază odată cu restul codului. Fă-l pe server, pornind de la '
          . 'inc/config.example.php, cu datele găzduirii.');
    } elseif ($eroareConfig !== '') {
        rezultat('Setări', 'Fișierul inc/config.php', 'rau', $eroareConfig,
            'Compară-l cu inc/config.example.php.');
    } else {
        rezultat('Setări', 'Fișierul inc/config.php', 'bun', 'citit cu bine');

        $d = is_array($config['db'] ?? null) ? $config['db'] : [];

        foreach (['host' => 'gazda', 'nume' => 'numele bazei', 'user' => 'utilizatorul'] as $cheie => $cum) {
            $are = trim((string) ($d[$cheie] ?? '')) !== '';
            rezultat('Setări', 'Este completat ' . $cum, $are ? 'bun' : 'rau',
                $are ? 'da' : 'gol');
        }

        // Gazda: „localhost" e răspunsul corect în marea majoritate a cazurilor.
        $gazda = (string) ($d['host'] ?? '');
        if ($gazda !== '' && !in_array($gazda, ['localhost', '127.0.0.1', '::1'], true)) {
            rezultat('Setări', 'Gazda bazei de date', 'atentie', $gazda,
                'De obicei aici se pune „localhost", nu numele domeniului: baza stă pe '
              . 'aceeași mașină cu site-ul. Dacă găzduirea îți dă un server separat '
              . '(gen mysql.ceva.ro), atunci e în regulă așa.');
        }

        // Parola goală trece în XAMPP, dar pe un site public e o problemă.
        $parolaGoala = (string) ($d['parola'] ?? '') === '';
        $local = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true)
              || str_starts_with((string) ($_SERVER['HTTP_HOST'] ?? ''), 'localhost:');

        if ($parolaGoala && !$local) {
            rezultat('Setări', 'Parola bazei de date', 'rau', 'goală',
                'Pe un server public, un cont de bază de date fără parolă nu are ce căuta.');
        }

        // url_site trebuie să fie chiar adresa la care ești acum.
        $urlSite = rtrim((string) ($config['url_site'] ?? ''), '/');
        $acum    = ($_SERVER['HTTPS'] ?? '') ? 'https://' : 'http://';
        $acum   .= (string) ($_SERVER['HTTP_HOST'] ?? '');

        $seSuprapune = $urlSite !== '' && str_starts_with($acum, parse_url($urlSite, PHP_URL_SCHEME) . '://' . parse_url($urlSite, PHP_URL_HOST));
        rezultat('Setări', 'Adresa site-ului (url_site)', $seSuprapune ? 'bun' : 'atentie',
            $urlSite !== '' ? $urlSite : 'goală',
            $seSuprapune ? '' : 'Nu se potrivește cu adresa pe care ești acum (' . $acum . '). '
          . 'Din ea se construiesc linkurile de confirmare, deci ar ieși greșite.');

        // Modul dezvoltare, pe un site public, e o gaură.
        $dezvoltare = !empty($config['dezvoltare']);
        if ($dezvoltare && !$local) {
            rezultat('Setări', 'Modul dezvoltare', 'rau', 'pornit',
                'Pe site-ul public trebuie pus pe false. Cât timp e pornit, oricine cere o '
              . 'înregistrare primește înapoi linkul de confirmare, deci poate activa '
              . 'conturi pe adrese care nu sunt ale lui. Tot atunci se văd în pagină și '
              . 'erorile PHP, cu nume de tabele și căi de pe server.');
        } else {
            rezultat('Setări', 'Modul dezvoltare', 'bun', $dezvoltare ? 'pornit (local)' : 'oprit');
        }
    }

    /* -------------------------- Baza de date --------------------------- */

    if (is_array($config)) {
        $d = is_array($config['db'] ?? null) ? $config['db'] : [];

        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $d['host'] ?? 'localhost', (int) ($d['port'] ?? 3306), $d['nume'] ?? '');

            $pdo = new PDO($dsn, $d['user'] ?? '', $d['parola'] ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 5,
            ]);

            rezultat('Baza de date', 'Legătura cu baza', 'bun', 'deschisă');

            // Tabelele
            $asteptate = [
                'membri' => ['id', 'permalink', 'nume', 'prenume', 'email', 'parola_hash',
                             'data_nasterii', 'sex', 'localitate', 'poza', 'poza_actualizata_la',
                             'stare', 'token_confirmare', 'token_expira', 'token_trimis_la',
                             'confirmat_la', 'autentificat_la', 'ip_inregistrare', 'creat_la'],
                'incercari_autentificare' => ['id', 'email', 'ip', 'reusita', 'creat_la'],
            ];

            foreach ($asteptate as $tabel => $coloane) {
                $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables
                                     WHERE table_schema = DATABASE() AND table_name = ?');
                $q->execute([$tabel]);

                if (!(int) $q->fetchColumn()) {
                    rezultat('Baza de date', 'Tabelul ' . $tabel, 'rau', 'lipsește',
                        'Alege baza de date în phpMyAdmin, apoi importă sql/schema.sql. '
                      . 'Dacă importul s-a oprit cu o eroare, cel mai des e din cauză că nu '
                      . 'ai ales întâi baza.');
                    continue;
                }

                $q = $pdo->prepare('SELECT column_name FROM information_schema.columns
                                     WHERE table_schema = DATABASE() AND table_name = ?');
                $q->execute([$tabel]);
                $are = array_map('strtolower', array_column($q->fetchAll(), 'column_name'));

                $lipsa = array_diff($coloane, $are);

                rezultat('Baza de date', 'Tabelul ' . $tabel, $lipsa ? 'atentie' : 'bun',
                    $lipsa ? 'există, dar îi lipsesc coloane: ' . implode(', ', $lipsa) : 'complet',
                    $lipsa ? 'Rulează, în ordine, fișierele din sql/ pe care nu le-ai rulat încă '
                           . '(002-autentificare.sql, 003-poza-profil.sql).' : '');
            }

            // Codificarea: fără utf8mb4, diacriticele se strică.
            $set = $pdo->query("SHOW VARIABLES LIKE 'character_set_database'")->fetch();
            $codificare = (string) ($set['Value'] ?? '?');
            rezultat('Baza de date', 'Codificarea bazei',
                str_starts_with($codificare, 'utf8') ? 'bun' : 'atentie', $codificare,
                str_starts_with($codificare, 'utf8') ? '' : 'Ar trebui utf8mb4, altfel „ș" și „ț" ajung stricate.');

        } catch (PDOException $e) {
            /**
             * Aici se dă un pic mai mult decât în restul aplicației, pentru că
             * pagina e închisă cu cheie și tocmai asta caută cel care o deschide.
             * Parola nu apare nicăieri: PDO nu o pune în mesajele lui.
             */
            rezultat('Baza de date', 'Legătura cu baza', 'rau', $e->getMessage(),
                talmaceste($e));
        }
    }

    /* ------------------------ Dosare și drepturi ----------------------- */

    foreach ([
        'assets/img/membri' => 'Aici ajung pozele de profil.',
        'private'           => 'Aici se scriu fișierele care nu trebuie să se vadă din web.',
    ] as $dosar => $laCe) {
        $cale = $RADACINA . '/' . $dosar;

        if (!is_dir($cale)) {
            rezultat('Dosare', $dosar, 'atentie', 'nu există',
                $laCe . ' Se face singur la prima folosire, dacă dosarul de deasupra permite scrierea.');
            continue;
        }

        rezultat('Dosare', $dosar, is_writable($cale) ? 'bun' : 'rau',
            is_writable($cale) ? 'se poate scrie' : 'NU se poate scrie',
            is_writable($cale) ? '' : 'Pune-i drepturile pe 755 din managerul de fișiere al găzduirii.');
    }

    /* --------------------- Ce se vede din afară ------------------------ */

    // inc/ și private/ au .htaccess care le închide. Dacă serverul nu ține cont
    // de el, parola bazei de date ar putea fi descărcată de oricine.
    $gazdaAcum = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($gazdaAcum !== '') {
        $adresa = (($_SERVER['HTTPS'] ?? '') ? 'https://' : 'http://') . $gazdaAcum . '/inc/config.php';
        $context = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
        $raspuns = @file_get_contents($adresa, false, $context);

        // Codul HTTP contează mai mult decât conținutul: un fișier .php cerut
        // din browser poate întoarce un corp gol pentru că PHP l-a EXECUTAT,
        // nu pentru că serverul l-a oprit. Corp gol plus 200 înseamnă că
        // .htaccess nu ține — iar dacă mâine PHP se oprește pentru dosarul
        // ăla, fișierul începe să fie trimis ca text simplu, cu parolă cu tot.
        $cod = 0;
        foreach ($http_response_header ?? [] as $antet) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $antet, $m)) $cod = (int) $m[1];
        }

        if ($raspuns === false && $cod === 0) {
            rezultat('Siguranță', 'Dosarul inc/ este închis din web', 'nestiut',
                'nu am putut verifica de aici',
                'Deschide manual ' . $adresa . ' într-o filă nouă. Trebuie să primești '
              . '„Forbidden" sau „Not Found" — nu o pagină goală și cu atât mai puțin '
              . 'conținutul fișierului.');
        } elseif (str_contains((string) $raspuns, 'parola') || str_contains((string) $raspuns, '<?php')) {
            rezultat('Siguranță', 'Dosarul inc/ este închis din web', 'rau',
                'SE VEDE — parola bazei se poate descărca de oricine',
                'Găzduirea nu ține cont de fișierele .htaccess, iar PHP nu rulează în dosarul '
              . 'ăsta. Schimbă acum parola bazei, apoi mută dosarul inc/ deasupra rădăcinii '
              . 'site-ului sau cere-le celor de la găzduire să pornească AllowOverride.');
        } elseif ($cod >= 200 && $cod < 300) {
            rezultat('Siguranță', 'Dosarul inc/ este închis din web', 'atentie',
                'răspunde cu ' . $cod . ', dar nu scurge nimic',
                'Fișierul a fost executat de PHP, deci deocamdată nu se vede nimic din el. '
              . 'Dar .htaccess nu îl oprește, iar asta e o plasă de siguranță care ar trebui '
              . 'să existe. Verifică dacă găzduirea are AllowOverride pornit.');
        } else {
            rezultat('Siguranță', 'Dosarul inc/ este închis din web', 'bun',
                'serverul răspunde cu ' . $cod);
        }
    }

    /* ------------------------------ Diverse ---------------------------- */

    $sesiune = @session_start();
    rezultat('Diverse', 'Sesiunile', $sesiune ? 'bun' : 'rau',
        $sesiune ? 'merg' : 'nu pornesc',
        $sesiune ? '' : 'Fără sesiuni nu se poate ține minte cine e conectat.');

    rezultat('Diverse', 'Trimiterea de e-mailuri', function_exists('mail') ? 'atentie' : 'rau',
        function_exists('mail') ? 'funcția mail() există' : 'funcția mail() lipsește',
        'Deocamdată aplicația NU trimite încă e-mailuri — locul e marcat cu TODO în '
      . 'api/inregistrare.php. Până se face, nimeni nu-și poate confirma contul pe '
      . 'site-ul public.');
}

/* =========================== Funcții mici ============================== */

/** „8M", „2G" → octeți. */
function octetiDin(string $valoare): int
{
    $valoare = trim($valoare);
    if ($valoare === '') return 0;

    $unitate = strtolower(substr($valoare, -1));
    $numar   = (int) $valoare;

    if ($unitate === 'g') return $numar * 1024 * 1024 * 1024;
    if ($unitate === 'm') return $numar * 1024 * 1024;
    if ($unitate === 'k') return $numar * 1024;

    return $numar;
}

/** Traduce cele mai dese erori de MySQL în ceva de făcut. */
function talmaceste(PDOException $e): string
{
    $m = $e->getMessage();

    // 1044: utilizatorul e recunoscut, dar nu are voie în baza cerută.
    if (str_contains($m, 'Access denied') && str_contains($m, 'to database')) {
        return 'Utilizatorul e recunoscut, dar nu are voie în baza asta. Ori numele bazei nu '
             . 'e cel bun (pe găzduire are un prefix, de forma „numecont_db", și se scrie '
             . 'întreg), ori utilizatorul n-a fost legat de ea. Pe cPanel legarea e un pas '
             . 'separat: „Add User To Database", cu toate drepturile bifate. Se uită des.';
    }
    // 1045: nici măcar nu e recunoscut.
    if (str_contains($m, 'Access denied')) {
        return 'Numele utilizatorului sau parola nu sunt bune. Amândouă au prefixul contului '
             . 'de găzduire, de forma „numecont_utilizator".';
    }
    if (str_contains($m, 'Unknown database')) {
        return 'Baza asta nu există pe server. Pe găzduire numele are un prefix, de forma '
             . '„numecont_db" — se pune întreg, cu prefix cu tot.';
    }
    if (str_contains($m, 'Connection refused') || str_contains($m, "Can't connect")) {
        return 'Nu răspunde nimic la adresa și portul date. Cel mai des: la „host" e scris '
             . 'numele domeniului în loc de „localhost".';
    }
    if (str_contains($m, 'timed out') || str_contains($m, 'timeout')) {
        return 'Cererea a expirat fără răspuns — semn că se încearcă o legătură din afară. '
             . 'Pune „localhost" la host.';
    }
    if (str_contains($m, 'could not find driver')) {
        return 'Lipsește extensia pdo_mysql din PHP. Se pornește din panoul găzduirii.';
    }

    return 'Compară datele cu cele din panoul găzduirii.';
}

function scapa(string $t): string
{
    return htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$semne = ['bun' => '✓', 'atentie' => '!', 'rau' => '✕', 'nestiut' => '?'];
$total = ['bun' => 0, 'atentie' => 0, 'rau' => 0, 'nestiut' => 0];

foreach ($sectiuni as $lista) {
    foreach ($lista as $r) $total[$r['stare']]++;
}
?>
<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Verificarea serverului — PulsulOrasului.Ro</title>
<style>
  /* Stilurile stau aici, nu în style.css: pagina asta trebuie să arate a ceva
     și atunci când restul site-ului nu pornește. */
  * { box-sizing: border-box; }
  body { margin: 0; padding: 28px 18px 60px;
         font: 16px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif;
         color: #1a1f27; background: #f7f8fa; }
  .wrap { max-width: 780px; margin: 0 auto; }
  h1 { font-size: 25px; margin: 0 0 6px; letter-spacing: -.02em; }
  .sub { color: #5a6472; margin: 0 0 26px; }
  h2 { font-size: 15px; text-transform: uppercase; letter-spacing: .08em;
       color: #5a6472; margin: 30px 0 10px; }
  .card { background: #fff; border: 1px solid #e5e8ee; border-radius: 14px; overflow: hidden; }
  .rand { display: flex; gap: 12px; padding: 13px 16px; border-top: 1px solid #eef0f4; }
  .rand:first-child { border-top: 0; }
  .semn { flex: none; width: 22px; height: 22px; border-radius: 50%; margin-top: 2px;
          display: grid; place-items: center; font-size: 13px; font-weight: 700; color: #fff; }
  .bun .semn { background: #16a34a; }
  .atentie .semn { background: #d97706; }
  .rau .semn { background: #dc2626; }
  .nestiut .semn { background: #94a3b8; }
  .text { min-width: 0; }
  .ce { font-weight: 600; }
  .detaliu { color: #5a6472; font-size: 14.5px; overflow-wrap: anywhere; }
  .sfat { margin-top: 6px; font-size: 14px; background: #f7f8fa; border-left: 3px solid #cbd2dc;
          padding: 8px 12px; border-radius: 0 8px 8px 0; }
  .rau .sfat { background: #fef2f2; border-color: #dc2626; }
  .atentie .sfat { background: #fffbeb; border-color: #d97706; }
  .rezumat { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px; }
  .bula { padding: 5px 13px; border-radius: 99px; font-size: 14px; font-weight: 600;
          border: 1px solid #e5e8ee; background: #fff; }
  .avertisment { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
                 padding: 14px 16px; border-radius: 12px; margin-bottom: 24px; }
  code { background: #eef0f4; padding: 1px 6px; border-radius: 5px; font-size: 14px; }
</style>
</head>
<body>
<div class="wrap">

<h1>Verificarea serverului</h1>
<p class="sub">PulsulOrasului.Ro — ce merge și ce nu, pe serverul ăsta.</p>

<?php if (!$potIntra): ?>

  <div class="avertisment">
    <strong>Pagina are nevoie de o cheie.</strong><br>
    Deschide <code>inc/config.php</code>, pune la <code>'cheie_diagnostic'</code> un șir
    lung și întâmplător, apoi intră pe
    <code>verifica.php?cheie=CHEIA_PUSĂ</code>.
  </div>

  <p>Fără cheie nu spune nimic, ca să nu poată afla oricine cum e construit serverul.</p>

<?php else: ?>

  <div class="rezumat">
    <span class="bula"><?= $total['bun'] ?> în regulă</span>
    <?php if ($total['atentie']): ?><span class="bula"><?= $total['atentie'] ?> de văzut</span><?php endif; ?>
    <?php if ($total['rau']): ?><span class="bula"><?= $total['rau'] ?> de reparat</span><?php endif; ?>
    <?php if ($total['nestiut']): ?><span class="bula"><?= $total['nestiut'] ?> neverificate</span><?php endif; ?>
  </div>

  <div class="avertisment">
    <strong>Șterge fișierul ăsta de pe server</strong> după ce ai terminat de reparat.
  </div>

  <?php foreach ($sectiuni as $titlu => $lista): ?>
  <h2><?= scapa($titlu) ?></h2>
  <div class="card">
    <?php foreach ($lista as $r): ?>
    <div class="rand <?= $r['stare'] ?>">
      <span class="semn"><?= $semne[$r['stare']] ?></span>
      <div class="text">
        <div class="ce"><?= scapa($r['ce']) ?></div>
        <?php if ($r['detaliu'] !== ''): ?>
        <div class="detaliu"><?= scapa($r['detaliu']) ?></div>
        <?php endif; ?>
        <?php if ($r['sfat'] !== ''): ?>
        <div class="sfat"><?= scapa($r['sfat']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>

<?php endif; ?>

</div>
</body>
</html>
