<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — anunțul pe e-mail către toată lista.
 *
 * Cere BAZA DE DATE. Partea de HTTP (pagina din admin) cere și SERVERUL, dar se
 * sare singură dacă nu i se dă o adresă.
 *
 * MESAJELE NU PLEACĂ NICĂIERI cât `dezvoltare => true` în inc/config.php: se
 * scriu în private/emailuri-trimise.log (vezi trimiteEmail din inc/email.php).
 * De acolo le numără și proba asta.
 *
 * Cum se rulează:
 *     php teste/test-anunt.php
 *     php teste/test-anunt.php http://127.0.0.1:8099
 *
 * CE PĂZEȘTE MAI PRESUS DE ORICE: că mesajul NU pleacă de două ori. Un buton
 * care nu se vede se descoperă în cinci minute; un al doilea e-mail către toată
 * lista nu se vede nicăieri — nici în pagină, nici în loguri, dacă nu-l cauți —
 * și nu se ia înapoi. De aceea jetonul de o singură folosință are aici mai multe
 * probe decât tot restul paginii la un loc.
 *
 * ATENȚIE: ca proba newsletterului, STINGE bifa de vești la toți ceilalți membri
 * din bază pe durata ei, și o pune la loc la sfârșit (vezi curata()). Fără asta,
 * pe o bază de dezvoltare cu douăzeci de conturi vechi, fiecare rulare ar fi
 * trimis douăzeci de anunțuri unor oameni care n-au nicio treabă cu proba.
 */

require_once __DIR__ . '/../inc/anunt.php';

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

const SEMN   = 'tstanunt-';
const PAROLA = 'ProbaAnunt!2024';

/** Cine avea bifa pornită înainte de probă, ca să le-o punem la loc. */
$aveauBifa = [];

function stergeOameniiProbei(): void
{
    db()->prepare('DELETE FROM membri WHERE permalink LIKE ?')->execute([SEMN . '%']);
}

function curata(): void
{
    global $aveauBifa;

    stergeOameniiProbei();

    foreach ($aveauBifa as $id) {
        db()->prepare('UPDATE membri SET newsletter = 1 WHERE id = ?')->execute([(int) $id]);
    }

    $aveauBifa = [];
}

stergeOameniiProbei();

$aveauBifa = db()->query('SELECT id FROM membri WHERE newsletter = 1')->fetchAll(PDO::FETCH_COLUMN);
db()->exec('UPDATE membri SET newsletter = 0');

register_shutdown_function('curata');

function faMembru(string $cheie, string $prenume, bool $abonat,
                  string $stare = 'activ', bool $staff = false): int
{
    db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, sex, data_nasterii,
                             parola_hash, stare, este_staff, newsletter,
                             creat_la, confirmat_la)
         VALUES (?,?,?,?,\'M\',\'1990-01-01\',?,?,?,?,?,?)'
    )->execute([
        substr(SEMN . $cheie, 0, 16), 'Vestescu', $prenume,
        SEMN . $cheie . '@invalid.local',
        password_hash(PAROLA, PASSWORD_DEFAULT),
        $stare, $staff ? 1 : 0, $abonat ? 1 : 0, acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

/** Câte mesaje cu subiectul ăsta stau în logul de e-mailuri. */
function cateMesajeCu(string $subiect): int
{
    $log = __DIR__ . '/../private/emailuri-trimise.log';

    return is_file($log)
        ? substr_count((string) @file_get_contents($log), 'subiect: ' . $subiect)
        : 0;
}

/* ==================================================================== */
sectiune('paragrafele');

/**
 * Rândul GOL desparte paragrafele; Enterul simplu rămâne în paragraf.
 *
 * E deosebirea de care atârnă o enumerare scrisă pe trei rânduri: tăiată la
 * fiecare Enter, ar fi ieșit trei paragrafe cu spațiu alb între ele, adică
 * altceva decât a scris omul.
 */
verifica('rândul gol desparte', ['Unu.', 'Doi.'],
    paragrafeleAnuntului("Unu.\n\nDoi."));

verifica('Enterul simplu NU desparte', ["Unu.\nDoi."],
    paragrafeleAnuntului("Unu.\nDoi."));

verifica('rândurile goale de la capete nu fac paragrafe goale', ['Unu.'],
    paragrafeleAnuntului("\n\nUnu.\n\n"));

verifica('un text fără rânduri goale e un paragraf', ['O singură frază.'],
    paragrafeleAnuntului('O singură frază.'));

/* ==================================================================== */
sectiune('verificarea');

$bun = ['titlu' => 'Ne vedem sâmbătă',
        'mesaj' => str_repeat('Avem o veste bună pentru voi. ', 3)];

verifica('un anunț scris ca lumea trece', [], verificaAnunt($bun)['erori']);

verifica('fără titlu, nu trece', true,
    isset(verificaAnunt(['titlu' => '', 'mesaj' => $bun['mesaj']])['erori']['titlu']));

verifica('fără mesaj, nu trece', true,
    isset(verificaAnunt(['titlu' => $bun['titlu'], 'mesaj' => ''])['erori']['mesaj']));

verifica('un mesaj început și lăsat baltă, nu trece', true,
    isset(verificaAnunt(['titlu' => $bun['titlu'], 'mesaj' => 'Voiam sa'])['erori']['mesaj']));

verifica('un titlu prea lung, nu trece', true,
    isset(verificaAnunt(['titlu' => str_repeat('a', ANUNT_TITLU_MAX + 1),
                         'mesaj' => $bun['mesaj']])['erori']['titlu']));

verifica('un mesaj prea lung, nu trece', true,
    isset(verificaAnunt(['titlu' => $bun['titlu'],
                         'mesaj' => str_repeat('a', ANUNT_TEXT_MAX + 1)])['erori']['mesaj']));

/**
 * TITLUL AJUNGE ANTET DE E-MAIL, deci n-are voie să poarte un rând nou: un
 * subiect rupt în două ar însemna un antet inventat lipit sub el (vezi
 * lămurirea despre injecția în anteturi din inc/email.php).
 */
$cuEnter = verificaAnunt(['titlu' => "Ceva\nși încă ceva", 'mesaj' => $bun['mesaj']]);
verifica('titlul rămâne pe un rând', false,
    str_contains((string) ($cuEnter['curat']['titlu'] ?? "\n"), "\n"));

/* Mesajul, dimpotrivă, își ține paragrafele. */
$cuParagrafe = verificaAnunt(['titlu' => $bun['titlu'],
                              'mesaj' => "Primul paragraf lung.\n\n\n\nAl doilea la fel."]);
verifica('mesajul își ține paragrafele', 2,
    count(paragrafeleAnuntului((string) $cuParagrafe['curat']['mesaj'])));

/* Și șirurile de rânduri goale se strâng la unul, ca la descrierea unui anunț. */
verifica('și rândurile goale se strâng', false,
    str_contains((string) $cuParagrafe['curat']['mesaj'], "\n\n\n"));

/* ==================================================================== */
sectiune('cine primește');

$gazda   = faMembru('gazda',   'Silviu', true,  'activ', true);
$abonat  = faMembru('abonat',  'Ana',    true);
$fara    = faMembru('fara',    'Radu',   false);
$suspend = faMembru('suspend', 'Mihai',  true,  'suspendat');
$nou     = faMembru('nou',     'Elena',  true,  'neconfirmat');

$aiMei = static function (): array {
    $ai = [];

    foreach (destinatariiAnuntului() as $om) {
        if (str_starts_with((string) $om['email'], SEMN)) { $ai[] = (int) $om['id']; }
    }

    return $ai;
};

verifica('primesc doar cei cu bifa, activi', [$gazda, $abonat], $aiMei());

/**
 * STAFF-UL INTRĂ ȘI EL, dacă are bifa. Nu e o scăpare: cine trimite trebuie să
 * vadă mesajul așa cum îl văd ceilalți, altfel n-are cum să-l îndrepte.
 */
verifica('staff-ul cu bifă e pe listă', true, in_array($gazda, $aiMei(), true));

verifica('cifra scrisă pe pagină e chiar câți sunt', count($aiMei()), catiPrimescAnuntul());

/**
 * FĂRĂ ȘTAMPILĂ, spre deosebire de newsletter: acolo, `newsletter_trimis_la`
 * scoate din listă pe cine a primit azi. Aici nu există „azi" — un anunț se
 * trimite când are cineva ceva de spus, iar două anunțuri în aceeași zi sunt
 * două anunțuri, nu unul.
 */
db()->prepare('UPDATE membri SET newsletter_trimis_la = ? WHERE id = ?')
    ->execute([date('Y-m-d'), $abonat]);

verifica('ștampila newsletterului nu taie pe nimeni', [$gazda, $abonat], $aiMei());

/* ==================================================================== */
sectiune('mesajul');

$titluProba = 'Probă de anunț ' . bin2hex(random_bytes(4));

$inainte = cateMesajeCu($titluProba);
$iesit   = trimiteAnuntul($titluProba, "Primul paragraf al probei.\n\nAl doilea.");

verifica('a plecat la toți cei de pe listă', count($aiMei()), $iesit['trimise']);
verifica('și niciunul n-a picat', 0, $iesit['picate']);
verifica('atâtea mesaje s-au și scris', $inainte + count($aiMei()),
    cateMesajeCu($titluProba));

/**
 * AL DOILEA MESAJ NECHEMAT DE PE SITE, deci al doilea cu ieșire la vedere.
 * Cine n-o găsește în două secunde apasă „Spam", iar un singur om care face asta
 * strică livrarea și pentru newsletterul de a doua zi.
 */
$sablon = sablonEmail($titluProba, [
    'salut'      => 'Bună, Ana!',
    'paragrafe'  => ['Un paragraf.'],
    'dezabonare' => linkDezabonare($abonat),
]);

/* În HTML adresa e scăpată, ca tot ce intră în pagină: `&` din ea ajunge
   `&amp;`. Deci se caută forma scăpată — cea crudă n-ar fi acolo, și bine că
   nu e. */
verifica('mesajul poartă ieșirea', true,
    str_contains($sablon['html'], h(linkDezabonare($abonat))));
verifica('și în varianta text', true,
    str_contains($sablon['text'], linkDezabonare($abonat)));

/**
 * SUBSOLUL NU MAI SPUNE „ZILNIC". E același pentru newsletterul de fiecare zi și
 * pentru un anunț scris de mână, care nu vine zilnic — iar o ieșire care descrie
 * greșit mesajul din care pleacă e o ieșire în care omul nu are încredere.
 */
verifica('și nu-i mai zice „zilnic"', false,
    str_contains($sablon['html'], 'mesajul ăsta zilnic'));

/* Ce scrie omul ajunge TEXT, oricâte etichete ar pune în el. */
$cuEticheta = sablonEmail('Probă', ['paragrafe' => ['Ceva <script>alert(1)</script> aici.']]);
verifica('textul omului e scăpat în HTML', false,
    str_contains($cuEticheta['html'], '<script>'));

/* ==================================================================== */
sectiune('jetonul de o singură folosință');

/**
 * ĂSTA E LACĂTUL. Fără el, un „reîncarcă" peste pagina care tocmai a trimis ar
 * fi trimis din nou — cea mai scumpă apăsare de pe site.
 */
$_SESSION = [];

$unu = jetonNouDeAnunt();
verifica('un jeton nou e bun', true, jetonDeAnuntValid($unu));

consumaJetonDeAnunt($unu);
verifica('consumat, nu mai e', false, jetonDeAnuntValid($unu));

verifica('unul inventat nu trece', false, jetonDeAnuntValid(str_repeat('a', 32)));
verifica('și nici unul gol', false, jetonDeAnuntValid(''));

/**
 * DOUĂ FILE DESCHISE DEODATĂ. Cu un singur jeton în sesiune, a doua
 * previzualizare îl scria peste al primei, iar fila dintâi primea „a plecat
 * deja" despre un anunț care nu plecase niciodată.
 */
$a = jetonNouDeAnunt();
$b = jetonNouDeAnunt();

verifica('două file, amândouă bune', [true, true],
    [jetonDeAnuntValid($a), jetonDeAnuntValid($b)]);

consumaJetonDeAnunt($a);
verifica('una trimite, cealaltă rămâne bună', [false, true],
    [jetonDeAnuntValid($a), jetonDeAnuntValid($b)]);

/* Lista nu crește la nesfârșit: la al (N+1)-lea, primul cade. */
$_SESSION = [];
$primul   = jetonNouDeAnunt();

for ($i = 0; $i < ANUNT_JETOANE; $i++) { $ultimul = jetonNouDeAnunt(); }

verifica('cel mai vechi cade după ' . ANUNT_JETOANE, false, jetonDeAnuntValid($primul));
verifica('cel mai nou e tot acolo', true, jetonDeAnuntValid($ultimul));

/* ==================================================================== */
if ($BAZA === '') {
    echo "\n(sar peste HTTP: dă adresa serverului ca argument, "
       . "ex. php teste/test-anunt.php http://127.0.0.1:8099)\n";
} else {
    /** O cerere, cu sau fără trup de formular. */
    $cere = static function (string $cale, ?array $camp, string $cookie) use ($BAZA): array {
        $trup = $camp === null ? '' : http_build_query($camp);

        $ctx = stream_context_create(['http' => [
            'method'  => $camp === null ? 'GET' : 'POST',
            'header'  => ($camp === null ? '' : "Content-Type: application/x-www-form-urlencoded\r\n")
                       . ($cookie === '' ? '' : "Cookie: $cookie\r\n"),
            'content' => $trup,
            'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 20,
        ]]);

        $corp = (string) @file_get_contents($BAZA . $cale, false, $ctx);
        $cod  = 0;
        $unde = '';
        $nou  = $cookie;

        foreach ($http_response_header ?? [] as $rand) {
            if (preg_match('~^HTTP/\S+\s+(\d+)~', $rand, $m)) { $cod = (int) $m[1]; }
            if (stripos($rand, 'Location:') === 0) { $unde = trim(substr($rand, 9)); }
            if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $rand, $m) === 1) { $nou = $m[1]; }
        }

        return ['cod' => $cod, 'unde' => $unde, 'brut' => $corp, 'cookie' => $nou];
    };

    /** Intră în cont și întoarce cookie-ul, sau '' dacă n-a mers. */
    $intra = static function (string $email) use ($cere): string {
        $pag = $cere('/login.php', null, '');
        preg_match('/name="csrf" value="([^"]+)"/', $pag['brut'], $m);

        $r = $cere('/api/autentificare.php',
            ['csrf' => $m[1] ?? '', 'email' => $email, 'parola' => PAROLA],
            $pag['cookie']);

        return str_contains($r['brut'], '"ok":true') ? $r['cookie'] : '';
    };

    sectiune('paza paginii');

    $anonim = $cere('/admin-anunt.php', null, '');
    verifica('nelogat, duce la intrare', true,
        $anonim['cod'] === 302 && str_contains($anonim['unde'], 'login.php'));
    verifica('și nu scapă formularul', false, str_contains($anonim['brut'], 'name="mesaj"'));

    $cookieStrain = $intra(SEMN . 'abonat@invalid.local');
    verifica('un om obișnuit a intrat în cont', true, $cookieStrain !== '');

    $strain = $cere('/admin-anunt.php', null, $cookieStrain);
    verifica('dar nu ajunge la pagină', true,
        $strain['cod'] === 302 && str_contains($strain['unde'], 'index.php'));

    $cookieGazda = $intra(SEMN . 'gazda@invalid.local');
    verifica('omul de casă a intrat', true, $cookieGazda !== '');

    if ($cookieGazda === '') {
        echo "   (fără sesiune de staff nu se poate proba restul)\n";
    } else {
        sectiune('cei trei pași');

        $pagina = $cere('/admin-anunt.php', null, $cookieGazda);
        preg_match('/name="csrf" value="([^"]+)"/', $pagina['brut'], $m);
        $csrf = $m[1] ?? '';

        verifica('pagina se deschide pentru staff', 200, $pagina['cod']);
        verifica('și are formularul de scris', true,
            str_contains($pagina['brut'], 'name="titlu"')
            && str_contains($pagina['brut'], 'name="mesaj"'));

        /* Butonul primului pas NU trimite nimic: el duce la previzualizare. */
        verifica('primul buton doar arată', true,
            str_contains($pagina['brut'], 'value="vezi"'));
        verifica('și nu e niciun buton de trimis pe el', false,
            str_contains($pagina['brut'], 'value="trimit"'));

        $titluHttp = 'Probă HTTP ' . bin2hex(random_bytes(4));
        $inainte   = cateMesajeCu($titluHttp);

        /* ---------- un mesaj neterminat nu trece de primul pas ---------- */
        $scurt = $cere('/admin-anunt.php',
            ['csrf' => $csrf, 'pas' => 'vezi', 'titlu' => $titluHttp, 'mesaj' => 'Voiam sa'],
            $cookieGazda);

        verifica('un mesaj neterminat rămâne la scris', true,
            str_contains($scurt['brut'], 'field__error'));
        verifica('și nu pleacă nimic', $inainte, cateMesajeCu($titluHttp));

        /* ------------------------- pasul 2 ----------------------------- */
        $vazut = $cere('/admin-anunt.php', [
            'csrf' => $csrf, 'pas' => 'vezi', 'titlu' => $titluHttp,
            'mesaj' => "Primul paragraf al probei, destul de lung.\n\nAl doilea.",
        ], $cookieGazda);

        preg_match('/name="jeton" value="([^"]+)"/', $vazut['brut'], $m);
        $jeton = $m[1] ?? '';

        verifica('previzualizarea se deschide', 200, $vazut['cod']);
        verifica('cu jetonul ei', true, $jeton !== '');
        verifica('și cu butonul de trimis', true,
            str_contains($vazut['brut'], 'value="trimit"'));
        verifica('previzualizarea NU trimite nimic', $inainte, cateMesajeCu($titluHttp));

        /* Textul omului se vede acolo, întreg. */
        verifica('scrie în ea ce se va trimite', true,
            str_contains($vazut['brut'], 'Al doilea.'));

        /* ---------------------- ocolul: proba -------------------------- */

        /**
         * „Trimite-mi mie o probă" pleacă la UNUL SINGUR, oricâți ar fi pe
         * listă, și NU stinge jetonul: proba se poate repeta până arată bine,
         * iar trimiterea adevărată rămâne la o apăsare distanță după ea.
         */
        $inainteDeProba = cateMesajeCu($titluHttp);

        $proba = $cere('/admin-anunt.php', [
            'csrf' => $csrf, 'pas' => 'proba', 'jeton' => $jeton, 'titlu' => $titluHttp,
            'mesaj' => "Primul paragraf al probei, destul de lung.\n\nAl doilea.",
        ], $cookieGazda);

        verifica('proba pleacă la unul singur', $inainteDeProba + 1,
            cateMesajeCu($titluHttp));

        /* Și se rămâne pe previzualizare, cu același jeton — proba e un ocol. */
        $dupaProba = $cere('/admin-anunt.php', null, $cookieGazda);

        preg_match('/name="jeton" value="([^"]+)"/', $dupaProba['brut'], $m);

        verifica('după probă se rămâne pe previzualizare', true,
            str_contains($dupaProba['brut'], 'value="trimit"'));
        verifica('cu același jeton, nestins', $jeton, $m[1] ?? '');

        /* ------------------------- pasul 3 ----------------------------- */
        $inainte = cateMesajeCu($titluHttp);

        $trimis = $cere('/admin-anunt.php', [
            'csrf' => $csrf, 'pas' => 'trimit', 'jeton' => $jeton, 'titlu' => $titluHttp,
            'mesaj' => "Primul paragraf al probei, destul de lung.\n\nAl doilea.",
        ], $cookieGazda);

        verifica('a doua apăsare trimite', 302, $trimis['cod']);
        verifica('și pleacă la toți cei de pe listă',
            $inainte + count($aiMei()), cateMesajeCu($titluHttp));

        /**
         * A DOUA APĂSARE PE ACELAȘI JETON NU MAI TRIMITE NIMIC.
         *
         * Asta e proba de care atârnă toată pagina. Un „reîncarcă" peste
         * trimitere, o dublă apăsare, un „înapoi" urmat de încă un „trimite" —
         * toate ajung aici, iar ce trebuie să se întâmple e nimic.
         */
        $cateAcum = cateMesajeCu($titluHttp);

        $dinNou = $cere('/admin-anunt.php', [
            'csrf' => $csrf, 'pas' => 'trimit', 'jeton' => $jeton, 'titlu' => $titluHttp,
            'mesaj' => "Primul paragraf al probei, destul de lung.\n\nAl doilea.",
        ], $cookieGazda);

        verifica('cu același jeton nu mai pleacă nimic', $cateAcum, cateMesajeCu($titluHttp));

        /* Nici fără jeton deloc — cine ar sări peste previzualizare. */
        $faraJeton = $cere('/admin-anunt.php', [
            'csrf' => $csrf, 'pas' => 'trimit', 'titlu' => $titluHttp,
            'mesaj' => "Primul paragraf al probei, destul de lung.\n\nAl doilea.",
        ], $cookieGazda);

        verifica('și fără jeton, nimic', $cateAcum, cateMesajeCu($titluHttp));

        /**
         * Nici fără tokenul CSRF, la niciunul dintre pași: aceeași regulă ca la
         * orice schimbă starea.
         *
         * Se probează pe VORBA de după redirecționare, nu pe numărul de mesaje:
         * la pasul „vezi" n-ar fi plecat nimic oricum, deci o probă pe cifre ar
         * fi trecut și cu paza scoasă cu totul. Ce trebuie să se vadă e că
         * cererea a fost OPRITĂ, nu că n-a avut ce trimite.
         */
        $cere('/admin-anunt.php', [
            'pas' => 'vezi', 'titlu' => $titluHttp,
            'mesaj' => "Primul paragraf al probei, destul de lung.\n\nAl doilea.",
        ], $cookieGazda);

        $dupa = $cere('/admin-anunt.php', null, $cookieGazda);

        verifica('fără CSRF, cererea e oprită', true,
            str_contains($dupa['brut'], 'Sesiunea a expirat'));
        verifica('și nu se ajunge la previzualizare', false,
            str_contains($dupa['brut'], 'value="trimit"'));

        $cere('/admin-anunt.php', [
            'pas' => 'trimit', 'jeton' => $jeton, 'titlu' => $titluHttp,
            'mesaj' => "Primul paragraf al probei, destul de lung.\n\nAl doilea.",
        ], $cookieGazda);

        verifica('și nici la trimitere nu pleacă nimic', $cateAcum,
            cateMesajeCu($titluHttp));
    }
}

/* ==================================================================== */
echo "\n" . str_repeat('=', 60) . "\n";
echo "  $treceri trecute, $picaturi picate\n";
echo str_repeat('=', 60) . "\n";

exit($picaturi === 0 ? 0 : 1);
