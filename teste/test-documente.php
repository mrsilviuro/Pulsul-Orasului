<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — cele trei pagini cu putere juridică.
 *
 * Cere SERVERUL pornit. Fără o adresă, se sare singură.
 *
 *     php -S 127.0.0.1:8099 teste/router.php
 *     php teste/test-documente.php http://127.0.0.1:8099
 *
 * CE PĂZEȘTE, mai presus de orice: că DOCUMENTELE NU MINT. Un text despre
 * datele oamenilor care rămâne în urma codului e mai rău decât niciunul —
 * omul citește o promisiune care nu mai e adevărată și nu are cum să afle.
 *
 * De aceea cele mai multe probe de aici nu se uită la cum arată pagina, ci
 * leagă o vorbă din document de lucrul din cod care o face adevărată: cifra
 * din pagină vine din aceeași constantă ca purtarea site-ului, iar cookie-urile
 * pomenite sunt chiar cele pe care le pune codul.
 */

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/stergere.php';
require_once __DIR__ . '/../inc/evaluari.php';

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

/* ==================================================================== */
sectiune('cine ține site-ul');

/**
 * operatorulSite() întoarce MEREU aceleași chei, ca paginile să nu fie nevoite
 * să se apere de lipsa lor.
 */
$op = operatorulSite();

foreach (['tip', 'nume', 'cui', 'reg_com', 'adresa', 'email', 'are_date'] as $cheie) {
    verifica('are cheia „' . $cheie . '"', true, array_key_exists($cheie, $op));
}

verifica('„tip" e una dintre cele două', true, in_array($op['tip'], ['srl', 'pf'], true));
verifica('„are_date" spune dacă e nume', $op['nume'] !== '', $op['are_date']);

if ($BAZA === '') {
    echo "\n(sar peste HTTP: dă adresa serverului ca argument, "
       . "ex. php teste/test-documente.php http://127.0.0.1:8099)\n";
} else {
    $ia = static function (string $cale) use ($BAZA): array {
        $raw = @file_get_contents($BAZA . $cale, false, stream_context_create([
            'http' => ['ignore_errors' => true, 'timeout' => 10],
        ]));

        $cod = 0;

        foreach ($http_response_header ?? [] as $rand) {
            if (preg_match('~^HTTP/\S+\s+(\d+)~', $rand, $m)) { $cod = (int) $m[1]; }
        }

        return ['cod' => $cod, 'corp' => (string) $raw];
    };

    /* ================================================================ */
    sectiune('paginile se deschid');

    $pagini = [
        '/termeni.php'           => 'Termeni și Condiții',
        '/confidentialitate.php' => 'Politica de confidențialitate',
        '/cookies.php'           => 'Politica de cookies',
    ];

    $doc = [];

    foreach ($pagini as $cale => $titlu) {
        $r = $ia($cale);
        $doc[$cale] = $r['corp'];

        verifica('„' . $cale . '" răspunde', 200, $r['cod']);

        /* Numele paginii, oriunde l-ar scrie: în titlul filei, în firimituri
           sau într-un titlu de nivel oarecare. Proba de dinainte cerea o
           clasă anume și a căzut la prima rescriere a paginii — degeaba,
           fiindcă pagina era în regulă. */
        verifica('  își spune numele', true, str_contains($r['corp'], $titlu));
        verifica('  și e a noastră', true, str_contains($r['corp'], 'PulsulOrasului.Ro'));
    }

    /**
     * FIECARE DUCE LA CELELALTE DOUĂ. Cine deschide una dintre ele are, de
     * obicei, o întrebare la care răspunde alta.
     */
    foreach ($pagini as $cale => $_) {
        $celelalte = array_diff(array_keys($pagini), [$cale]);
        $toate = true;

        foreach ($celelalte as $alta) {
            if (!str_contains($doc[$cale], 'href="' . $alta . '"')) { $toate = false; }
        }

        verifica('„' . $cale . '" duce la celelalte două', true, $toate);
    }

    /* ================================================================ */
    sectiune('documentele nu mint');

    /**
     * Cifrele din pagini vin din constantele care hotărăsc purtarea site-ului.
     *
     * CE PĂZEȘTE ANUME PROBA ASTA, ca să nu pară mai mult decât e: cât timp
     * pagina scrie `<?= ZILE_RAGAZ_STERGERE ?>`, documentul e adevărat prin
     * construcție — se schimbă odată cu codul, iar proba trece oricum ai
     * întoarce-o. Rostul ei e ALTUL: prinde ziua în care cineva scrie cifra de
     * mână în text („30 de zile"), fiindcă din clipa aceea documentul poate
     * rămâne în urmă. Probat: cu cifra scrisă de mână și constanta schimbată,
     * proba pică.
     */
    verifica('termenii spun răgazul adevărat', true,
        str_contains($doc['/termeni.php'], ZILE_RAGAZ_STERGERE . ' de zile'));
    verifica('și fereastra adevărată pentru note', true,
        str_contains($doc['/termeni.php'], ORE_PENTRU_NOTE . ' de ore'));
    verifica('și vârsta minimă adevărată', true,
        str_contains($doc['/termeni.php'], VARSTA_MIN . ' ani'));

    verifica('confidențialitatea spune răgazul adevărat', true,
        str_contains($doc['/confidentialitate.php'], ZILE_RAGAZ_STERGERE . ' de zile'));
    verifica('și cât se țin încercările de intrare', true,
        str_contains($doc['/confidentialitate.php'], ZILE_PASTRARE_INCERCARI . ' de zile'));

    verifica('cookies spune cât ține „ține-mă minte"', true,
        str_contains($doc['/cookies.php'], ZILE_TINE_MINTE . ' de zile'));

    /**
     * COOKIE-URILE POMENITE SUNT CHIAR CELE PUSE DE COD. Numele lui „ține-mă
     * minte" stă într-o constantă; un tabel care ar rămâne cu numele vechi ar
     * trimite omul să caute în browser ceva ce nu există.
     */
    verifica('numele cookie-ului „ține-mă minte" e cel adevărat', true,
        str_contains($doc['/cookies.php'], COOKIE_TINE_MINTE));

    /**
     * ȘI NU POMENEȘTE CEVA CE NU AVEM. Ziua în care cineva adaugă Analytics,
     * proba asta rămâne verde — dar cea de dedesubt cade, și acolo e vorba.
     */
    verifica('nu pomenește cookie-uri de reclamă ca ale noastre', false,
        str_contains($doc['/cookies.php'], 'cookie-uri de publicitate proprii'));

    /* ================================================================ */
    sectiune('promisiunea „fără urmărire"');

    /**
     * Documentul spune, negru pe alb, că nu avem Analytics și niciun pixel.
     * Proba se uită în COD, nu în document: dacă apare vreodată așa ceva,
     * promisiunea devine minciună, iar asta trebuie să pice zgomotos.
     */
    $radacina = dirname(__DIR__);
    $urme     = [];

    $deCautat = ['google-analytics', 'googletagmanager', 'gtag(', 'fbq(',
                 'connect.facebook.net', 'hotjar', 'matomo', 'piwik'];

    $fisiere = array_merge(
        glob($radacina . '/*.php') ?: [],
        glob($radacina . '/inc/*.php') ?: [],
        glob($radacina . '/api/*.php') ?: [],
        [$radacina . '/assets/js/main.js']
    );

    foreach ($fisiere as $f) {
        $text = (string) @file_get_contents($f);

        foreach ($deCautat as $semn) {
            if (str_contains($text, $semn)) {
                $urme[] = basename($f) . ' → ' . $semn;
            }
        }
    }

    verifica('nu e niciun program de urmărire în cod', [], $urme);

    /* ================================================================ */
    sectiune('legăturile spre documente');

    /**
     * Erau `href="#"` peste tot, fiindcă paginile nu existau. Proba păzește
     * locurile în care omul e pus să fie de acord cu ceva: acolo, o legătură
     * care nu duce nicăieri e mai rea decât lipsa ei.
     */
    $legaturi = [
        '/login.php'    => ['/termeni.php', '/confidentialitate.php'],
        '/index.php'    => ['/termeni.php'],
        '/contact.php'  => ['/termeni.php', '/confidentialitate.php', '/cookies.php'],
    ];

    foreach ($legaturi as $cale => $cerute) {
        $corp  = $ia($cale)['corp'];
        $lipsa = [];

        foreach ($cerute as $c) {
            if (!str_contains($corp, 'href="' . $c . '"')) { $lipsa[] = $c; }
        }

        verifica('„' . $cale . '" le are pe toate', [], $lipsa);
    }

    /* Și niciunul dintre locurile alea nu mai are legătura moartă de dinainte. */
    foreach (['/login.php', '/index.php'] as $cale) {
        verifica('„' . $cale . '" n-are „Termenii" spre nicăieri', false,
            (bool) preg_match('/<a href="#">\s*(Termenii|termenii)/u', $ia($cale)['corp']));
    }

    /* ================================================================ */
    sectiune('harta pentru motoarele de căutare');

    $harta = $ia('/sitemap.xml')['corp'];

    foreach (array_keys($pagini) as $cale) {
        verifica('„' . $cale . '" e în sitemap', true,
            str_contains($harta, rtrim(urlSite(), '/') . $cale));
    }
}

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
