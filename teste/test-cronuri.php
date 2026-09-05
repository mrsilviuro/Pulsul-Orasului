<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — gura cronurilor: vorbesc numai când au ce spune.
 *
 * Cere BAZA DE DATE (cronurile o deschid), nu și serverul.
 *
 * Cum se rulează:
 *     php teste/test-cronuri.php
 *
 * CE PĂZEȘTE, ȘI DE CE NU SE VEDE NICĂIERI ALTUNDEVA. Cron-ul găzduirii ia ce
 * scrie scriptul pe ecran și-l trimite pe e-mail. Deci fiecare `echo` dintr-un
 * cron e un mesaj în cutia poștală a omului de casă, iar unul din oră în oră
 * care spune vesel „n-am găsit nimic" face opt mii șapte sute de mesaje pe an.
 * Nu se strică nimic vizibil: site-ul merge, probele trec, doar că omul învață
 * în două săptămâni să șteargă mesajele cronului fără să le citească — și
 * de-atunci cronul poate să tacă stricat luni în șir.
 *
 * DE ACEEA SE PROBEAZĂ RULÂND CHIAR SCRIPTURILE, nu funcțiile din ele: ce
 * contează aici e ce iese pe ieșirea standard, iar asta nu se poate afla
 * chemând o funcție. Se pornesc cu proc_open, cu ieșirea legată la o ȚEAVĂ —
 * exact cum le pornește cron-ul.
 */

require_once __DIR__ . '/../inc/bootstrap.php';

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

/** Cele patru, așa cum le pornește cron-ul din cPanel. */
const CRONURI = [
    'multumeste-participantilor',
    'aminteste-de-eveniment',
    'anonimizeaza-conturi',
    'trimite-emailuri',
];

/**
 * Pornește un cron cu ieșirea legată la o țeavă, ca din cron, și dă înapoi ce a
 * scris. NU prin shell_exec cu `2>&1`: acolo s-ar fi amestecat erorile de PHP
 * cu vorba scriptului, iar o probă care se uită la tăcere n-are voie să
 * confunde un „nimic de spus" cu un „a crăpat".
 */
function ruleaza(string $cron, array $argumente = []): array
{
    $cai = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

    $p = proc_open(
        array_merge([PHP_BINARY, __DIR__ . '/../cron/' . $cron . '.php'], $argumente),
        $cai, $tevi
    );

    if (!is_resource($p)) {
        return ['iesire' => '', 'erori' => 'n-am putut porni scriptul', 'cod' => -1];
    }

    $iesire = (string) stream_get_contents($tevi[1]);
    $erori  = (string) stream_get_contents($tevi[2]);

    fclose($tevi[1]);
    fclose($tevi[2]);

    return ['iesire' => $iesire, 'erori' => $erori, 'cod' => proc_close($p)];
}

/* ==================================================================== */
sectiune('tăcerea, când n-au ce spune');

/**
 * PROBA CEA MAI IMPORTANTĂ DE AICI. Baza e goală de treabă (probele își fac
 * curat după ele), deci fiecare cron caută, nu găsește nimic și trebuie să
 * TACĂ — nici măcar rândul „caut…" nu are voie să iasă, fiindcă și el, singur,
 * ar fi un e-mail.
 */
foreach (CRONURI as $cron) {
    $r = ruleaza($cron);

    verifica($cron . ' tace printr-o țeavă', '', $r['iesire']);
    verifica('  …și fără să crape',          '', trim($r['erori']));
}

/* ==================================================================== */
sectiune('vorba, când omul o cere');

/**
 * ÎNCERCAREA USCATĂ SE AUDE ORICUM, chiar și printr-o țeavă: omul a scris
 * comanda anume ca să vadă ceva. O tăcere acolo ar fi însemnat o unealtă care
 * nu răspunde — cel mai bun fel de a face pe cineva să creadă că e stricată.
 *
 * Hotărârea se ia acolo unde se citește steagul, nu în ramura care desenează:
 * ramura „nimic de trimis" iese cu `exit` înaintea ei, iar proba asta e cea
 * care ține minte de ce.
 */
foreach ([['multumeste-participantilor', '--uscat'],
          ['aminteste-de-eveniment',     '--uscat'],
          ['anonimizeaza-conturi',       '--uscat'],
          ['trimite-emailuri',           '--vezi']] as [$cron, $steag]) {

    $r = ruleaza($cron, [$steag]);

    verifica($cron . ' ' . $steag . ' vorbește', true, trim($r['iesire']) !== '');
}

/* ==================================================================== */
sectiune('vorba, când chiar s-a întâmplat ceva');

require_once __DIR__ . '/../inc/coada.php';

/**
 * UN MESAJ CARE PICĂ TREBUIE SĂ SE AUDĂ. Aici e capătul celălalt al regulii:
 * tăcerea e bună doar cât nu se întâmplă nimic. Un rând cu cuprinsul stricat nu
 * se poate trimite niciodată, deci se numără drept picat — și e singurul fel de
 * picare care merge fără server de poștă.
 */
$adresa = 'proba-cron-' . bin2hex(random_bytes(4)) . '@coada-proba.invalid';

puneInCoada($adresa, 'Probă', ['paragrafe' => ['Ceva.']]);
db()->prepare('UPDATE coada_emailuri SET blocuri = ? WHERE catre = ?')
    ->execute(['nu-i JSON', $adresa]);

$r = ruleaza('trimite-emailuri');

verifica('poștașul spune că a picat ceva', true,
    str_contains($r['iesire'], 'ATENȚIE'));

/**
 * ȘI SE SPUNE O SINGURĂ DATĂ. Rândurile rămase pe drumuri NU se șterg
 * niciodată singure — sunt acolo tocmai ca să se vadă —, deci un „spune cât
 * timp sunt" ar fi însemnat același e-mail despre același mesaj, din minut în
 * minut, până l-ar fi șters cineva. Adică exact zgomotul împotriva căruia e
 * scris tot fișierul ăsta, doar că de șaizeci de ori mai des.
 */
$r = ruleaza('trimite-emailuri');

verifica('dar nu se repetă la rularea următoare', '', $r['iesire']);

db()->prepare('DELETE FROM coada_emailuri WHERE catre = ?')->execute([$adresa]);

/* ==================================================================== */
sectiune('nimeni nu scrie de-a dreptul pe ecran');

/**
 * PAZA CELUI CARE SCRIE MÂINE UN CRON. Regula de mai sus ține doar cât toate
 * rândurile trec prin spune(); un singur `echo` strecurat înapoi o rupe, și o
 * rupe TĂCUT — nu pică nimic, doar că, peste o lună, cutia poștală e iar plină.
 *
 * Ieșirea din PHP_SAPI e altceva și are voie: acolo scriptul a fost cerut din
 * browser, nu pornit de cron, iar răspunsul e chiar rostul lui.
 */
$cuEcho = [];

foreach (CRONURI as $cron) {
    $cod = (string) file_get_contents(__DIR__ . '/../cron/' . $cron . '.php');

    /* Se taie capul fișierului, până la verificarea de PHP_SAPI: acolo stă
       singurul `exit` cu text care are voie. */
    $dupaPaza = (int) strpos($cod, "PHP_SAPI !== 'cli'");
    $cod      = substr($cod, $dupaPaza + 40);

    if (preg_match('/^\s*(echo|print)\s/m', $cod) === 1) {
        $cuEcho[] = $cron;
    }
}

verifica('niciun echo rămas în cronuri', [], $cuEcho);

/* ==================================================================== */
echo "\n" . str_repeat('=', 60) . "\n";
echo "  $treceri trecute, $picaturi picate\n";
echo str_repeat('=', 60) . "\n";

exit($picaturi === 0 ? 0 : 1);
