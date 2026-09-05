<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — coada de e-mailuri.
 *
 * Cere BAZA DE DATE, nu și serverul. Nu pleacă niciun mesaj: cât `dezvoltare`
 * e pornit, tot ce iese din coadă se scrie în private/emailuri-trimise.log.
 *
 * Cum se rulează:
 *     php teste/test-coada.php
 *
 * CE PĂZEȘTE MAI PRESUS DE ORICE, două lucruri, și amândouă se văd numai aici:
 *
 *   1. CĂ MESAJELE CARE RĂSPUND UNEI APĂSĂRI NU INTRĂ ÎN COADĂ. Confirmarea de
 *      cont și parola temporară pleacă pe loc. Dacă ar ajunge în coadă, un cron
 *      oprit ar însemna că nimeni nu-și mai poate face cont sau recupera parola
 *      — și n-ar afla nimeni până nu se plânge cineva.
 *   2. CĂ DOUĂ RULĂRI SUPRAPUSE NU IAU ACELEAȘI RÂNDURI. Un cron din minut în
 *      minut peste o rulare împotmolită e o întâmplare obișnuită, nu una
 *      închipuită, iar urmarea ar fi că fiecare om primește mesajul de două ori.
 */

require_once __DIR__ . '/../inc/coada.php';

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

/** Toate adresele probei stau pe domeniul ăsta, ca să se poată deosebi. */
const GAZDA = '@coada-proba.invalid';

/**
 * GOLEȘTE COADA ÎNTREAGĂ, nu doar rândurile probei — și e dinadins.
 *
 * Mai toate probele de aici trec prin iaDinCoada() și trimiteDinCoada(), care
 * NU știu de nicio probă: iau ce urmează la rând, oricui i-ar fi. Cu rânduri
 * rămase de la altă suită, „ia primele două" ar fi luat două de-ale altcuiva,
 * iar proba ar fi picat — dar numai când suita se rulează întreagă, adică
 * tocmai când contează.
 *
 * Nu se pierde nimic: coada e trecătoare prin firea ei, iar celelalte probe și-o
 * golesc singure (goleșteCoada) înainte să se uite în log.
 */
function curata(): void
{
    db()->exec('DELETE FROM coada_emailuri');
}

curata();
register_shutdown_function('curata');

/** Rândurile probei, în ordinea în care le-ar lua cronul. */
function aleMele(): array
{
    $q = db()->prepare(
        'SELECT * FROM coada_emailuri WHERE catre LIKE ?
          ORDER BY prioritate DESC, id ASC'
    );
    $q->execute(['%' . GAZDA]);

    return $q->fetchAll();
}

/** Un mesaj de probă, scris de-a dreptul în coadă. */
function pune(string $cine, int $prioritate = COADA_NORMAL): bool
{
    return puneInCoada($cine . GAZDA, 'Probă pentru ' . $cine,
        ['salut' => 'Bună!', 'paragrafe' => ['Un rând de probă.']], [], $prioritate);
}

/* ==================================================================== */
sectiune('bucla de includeri');

/**
 * inc/email.php cere inc/coada.php, iar inc/coada.php îl cere pe el. Merge
 * fiindcă niciunul nu cheamă nimic din celălalt la includere — dar asta e
 * genul de lucru care se strică tăcut la prima mutare de `require`, iar
 * urmarea ar fi un „undefined function" în mijlocul unei trimiteri.
 */
verifica('amândouă fișierele și-au declarat funcțiile', [true, true],
    [function_exists('trimiteEmail'), function_exists('puneInCoada')]);

/* ==================================================================== */
sectiune('punerea la rând');

verifica('la început, coada probei e goală', 0, count(aleMele()));

verifica('un mesaj intră', true, pune('ana'));
verifica('și chiar e acolo', 1, count(aleMele()));

$rand = aleMele()[0];

verifica('cu adresa lui', 'ana' . GAZDA, (string) $rand['catre']);
verifica('netrimis încă', null, $rand['trimis_la']);
verifica('neîncercat încă', 0, (int) $rand['incercari']);
verifica('cu prioritate obișnuită', 0, (int) $rand['prioritate']);

/**
 * ÎN COADĂ INTRĂ BLOCURILE, NU HTML-UL. Un rând de o jumătate de kilooctet în
 * loc de doisprezece — și, mai important, o îndreptare a șablonului prinde și
 * mesajele deja puse la rând.
 */
$blocuri = json_decode((string) $rand['blocuri'], true);

verifica('cuprinsul e păstrat ca blocuri', 'Bună!', $blocuri['salut'] ?? null);
verifica('nu e HTML gata compus', false, str_contains((string) $rand['blocuri'], '<table'));

/* O adresă otrăvită nu intră deloc: ce nu e în coadă nu poate pleca din ea. */
$cate = count(aleMele());
verifica('adresa cu rând nou nu intră', false,
    puneInCoada("ana" . GAZDA . "\r\nBcc: altcineva@exemplu.ro", 'Ceva', ['paragrafe' => ['x']]));
verifica('și n-a rămas niciun rând nou', $cate, count(aleMele()));

/* ==================================================================== */
sectiune('cine intră în coadă și cine nu');

global $config;

$configVechi = $config;

curata();

/**
 * PRIMA DINTRE CELE DOUĂ PROBE CARE CONTEAZĂ.
 *
 * Un trimiteEmail() obișnuit — cel din spatele confirmării de cont — pleacă PE
 * LOC. Dacă ar ajunge în coadă, un cron oprit ar însemna că nimeni nu-și mai
 * poate face cont, iar asta nu se vede până nu se plânge cineva.
 */
trimiteEmail('singur' . GAZDA, 'Confirmă-ți adresa', ['paragrafe' => ['Text.']]);

verifica('un mesaj obișnuit NU intră în coadă', 0, count(aleMele()));

/* Învelit în laCoada(), ACELAȘI apel scrie la rând în loc să trimită. */
laCoada(static function (): void {
    trimiteEmail('unu' . GAZDA, 'În serie', ['paragrafe' => ['Text.']]);
    trimiteEmail('doi' . GAZDA, 'În serie', ['paragrafe' => ['Text.']]);
});

verifica('învelit în laCoada, intră', 2, count(aleMele()));

/* Iar după ce se închide învelișul, lucrurile revin cum erau. */
trimiteEmail('dupa' . GAZDA, 'Iar obișnuit', ['paragrafe' => ['Text.']]);

verifica('după înveliș, se trimite iar pe loc', 2, count(aleMele()));

/**
 * STEAGUL SE STINGE ȘI CÂND SE ARUNCĂ CEVA DINĂUNTRU.
 *
 * Fără `finally`, o excepție ar fi lăsat steagul pornit, iar de-atunci încolo
 * TOATE mesajele cererii — inclusiv confirmarea de cont a următorului om — ar
 * fi ajuns în coadă. E genul de scăpare care nu se vede decât peste o
 * săptămână, când cineva întreabă de ce n-a primit e-mailul.
 */
try {
    laCoada(static function (): void {
        throw new RuntimeException('ceva s-a rupt la mijloc');
    });
} catch (RuntimeException $e) {
    // se aștepta
}

verifica('steagul se stinge și după o excepție', false, scriemInCoada());

trimiteEmail('dupaExceptie' . GAZDA, 'Obișnuit', ['paragrafe' => ['Text.']]);
verifica('deci mesajul următor pleacă pe loc', 2, count(aleMele()));

/* Prioritatea trece prin înveliș. */
laCoada(static function (): void {
    trimiteEmail('urgent' . GAZDA, 'Anulare', ['paragrafe' => ['Text.']]);
}, COADA_URGENT);

verifica('urgentul e primul la rând', 'urgent' . GAZDA, (string) aleMele()[0]['catre']);

/* ==================================================================== */
sectiune('luarea rândurilor');

curata();

pune('a'); pune('b'); pune('c');

$luate = iaDinCoada(2);

verifica('ia câte i se cer', 2, count($luate));
verifica('în ordinea în care au venit', ['a' . GAZDA, 'b' . GAZDA],
    array_map(static fn(array $r): string => (string) $r['catre'], $luate));

/**
 * A DOUA PROBĂ CARE CONTEAZĂ: DOUĂ RULĂRI SUPRAPUSE.
 *
 * Cronul pornește din minut în minut. O rulare împotmolită (serverul de poștă
 * nu răspunde) e încă în lucru când pornește următoarea. Dacă amândouă ar lua
 * aceleași rânduri, fiecare om ar primi mesajul de două ori — iar asta nu se
 * vede nicăieri în loguri, doar în cutiile poștale ale oamenilor.
 *
 * Rândurile se iau cu un UPDATE care își scrie cifra pe ele, adică într-o
 * singură mișcare a bazei: a doua rulare găsește doar ce a rămas.
 */
$aDoua = iaDinCoada(5);

verifica('a doua rulare nu le mai ia pe primele', ['c' . GAZDA],
    array_map(static fn(array $r): string => (string) $r['catre'], $aDoua));

$aTreia = iaDinCoada(5);
verifica('a treia nu mai găsește nimic', 0, count($aTreia));

/* Luarea numără încercările — de ea atârnă plafonul. */
verifica('luarea numără o încercare', 1, (int) aleMele()[0]['incercari']);

/* ==================================================================== */
sectiune('rândurile agățate');

curata();
pune('agatat');

/* O rulare le ia și moare: rândul rămâne luat, dar netrimis. */
iaDinCoada(1);

verifica('cât e proaspăt luat, nu-l mai ia nimeni', 0, count(iaDinCoada(5)));

/**
 * După COADA_MINUTE_BLOCAT, rândul se ia din nou — altfel ar rămâne blocat pe
 * veci, adică un mesaj pierdut de-a binelea, nu doar întârziat.
 */
db()->prepare('UPDATE coada_emailuri SET luat_la = ? WHERE catre LIKE ?')
    ->execute([acumMinus(COADA_MINUTE_BLOCAT + 5), '%' . GAZDA]);

verifica('după răgaz, se ia din nou', 1, count(iaDinCoada(5)));

/**
 * DAR NU LA NESFÂRȘIT. Un mesaj către o adresă care nu mai există ar fi mâncat
 * un loc din cele opt la fiecare rulare, pentru totdeauna.
 */
db()->prepare('UPDATE coada_emailuri SET luat_la = ?, incercari = ? WHERE catre LIKE ?')
    ->execute([acumMinus(COADA_MINUTE_BLOCAT + 5), COADA_INCERCARI_MAX, '%' . GAZDA]);

verifica('după ' . COADA_INCERCARI_MAX . ' încercări, nu se mai ia', 0, count(iaDinCoada(5)));
verifica('și se numără ca rămas pe drumuri', true, catePicateInCoada() >= 1);

/* ==================================================================== */
sectiune('trimiterea');

curata();

/* Cât `dezvoltare` e pornit, mesajele se scriu în fișier — deci „pleacă". */
$config['dezvoltare']   = true;
$config['email_metoda'] = 'auto';

pune('unu'); pune('doi'); pune('trei');

$r = trimiteDinCoada(2);

verifica('duce câte i se cer', 2, $r['trimise']);
verifica('fără să pice vreunul', 0, $r['picate']);

$dupa = aleMele();
$cateTrimise = 0;

foreach ($dupa as $rand) {
    if ($rand['trimis_la'] !== null) { $cateTrimise++; }
}

verifica('două poartă ștampila', 2, $cateTrimise);

$netrimise = 0;
foreach ($dupa as $rand) {
    if ($rand['trimis_la'] === null) { $netrimise++; }
}

verifica('și una mai așteaptă', 1, $netrimise);

$r = trimiteDinCoada(10);
verifica('a doua rulare o duce și pe a treia', 1, $r['trimise']);
verifica('apoi coada e goală', 0, count(iaDinCoada(5)));

/**
 * ȘTAMPILA SE PUNE DUPĂ TRIMITERE, nu înainte — pe dos față de newsletterul de
 * altădată. Acolo nu se putea ști dacă mesajul a plecat; aici serverul
 * răspunde, deci ce n-a plecat rămâne în coadă și se mai încearcă.
 */
$q = db()->prepare('SELECT COUNT(*) FROM coada_emailuri WHERE catre LIKE ? AND trimis_la IS NULL');
$q->execute(['%' . GAZDA]);

verifica('nimic netrimis rămas în urmă', 0, (int) $q->fetchColumn());

/* ==================================================================== */
sectiune('curățenia');

curata();

pune('vechi');
pune('proaspat');

/* Unul trimis demult, unul trimis acum. */
db()->prepare('UPDATE coada_emailuri SET trimis_la = ? WHERE catre = ?')
    ->execute([acumMinus((COADA_ZILE_PASTRARE + 1) * 24 * 60), 'vechi' . GAZDA]);
db()->prepare('UPDATE coada_emailuri SET trimis_la = ? WHERE catre = ?')
    ->execute([acum(), 'proaspat' . GAZDA]);

$sterse = curataCoada();

verifica('cel vechi se șterge', true, $sterse >= 1);

$ramase = array_map(static fn(array $r): string => (string) $r['catre'], aleMele());

verifica('cel proaspăt rămâne', ['proaspat' . GAZDA], $ramase);

/**
 * CELE PICATE NU SE ȘTERG, oricât ar fi de vechi: sunt singurul semn că ceva nu
 * merge. Șterse odată cu restul, o parolă SMTP schimbată ar fi trecut
 * neobservată — mesajele ar fi dispărut în tăcere.
 */
curata();
pune('picat');

db()->prepare('UPDATE coada_emailuri SET incercari = ?, creat_la = ? WHERE catre = ?')
    ->execute([COADA_INCERCARI_MAX, acumMinus(60 * 24 * 365), 'picat' . GAZDA]);

curataCoada();

verifica('unul picat rămâne, oricât ar fi de vechi', 1, count(aleMele()));

/* ==================================================================== */
sectiune('adresa care nu există');

/**
 * UN REFUZ DEFINITIV OMOARĂ RÂNDUL DIN PRIMA. O adresă care nu există acum nu
 * se naște peste un minut, iar găzduirile numără refuzurile astea: cine insistă
 * pe adrese moarte ajunge să nu mai poată trimite nimănui.
 */
curata();
pune('inexistent');

$rand = aleMele()[0];

insemneazaDinCoadaPicat((int) $rand['id'], 'No Such User Here', true);

verifica('nu mai așteaptă la rând', 0, count(iaDinCoada(50)));
verifica('și se numără printre cele picate', 1, count(emailurilePicate()));

/**
 * CELELALTE PICĂRI ÎȘI PĂSTREAZĂ CELE TREI ÎNCERCĂRI. Fără deosebirea asta, o
 * parolă SMTP schimbată din greșeală ar fi omorât tăcut toată coada la prima
 * trecere — confirmări de cont cu tot.
 */
curata();
pune('trecator');

$rand = aleMele()[0];

insemneazaDinCoadaPicat((int) $rand['id'], 'Connection refused');

verifica('o picare obișnuită se mai încearcă', 1, count(iaDinCoada(50)));

/* ------------------------- ștergerea din admin ------------------------- */

curata();
pune('mort'); pune('viu');

$randuri = aleMele();

insemneazaDinCoadaPicat((int) $randuri[0]['id'], 'No Such User Here', true);

$picate = emailurilePicate();

verifica('lista arată doar rândul mort', 1, count($picate));
verifica('cu vorba serverului pe el', 'No Such User Here', (string) $picate[0]['eroare']);

/**
 * HOTĂRÂREA STĂ ÎN `WHERE`: butonul nu poate scoate din coadă un mesaj care
 * încă își așteaptă rândul, oricâte id-uri i-ar veni.
 */
verifica('cel viu nu se poate șterge de acolo', false,
    stergeDinCoada((int) $randuri[1]['id']));
verifica('cel mort, da', true, stergeDinCoada((int) $randuri[0]['id']));
verifica('și rămâne doar cel viu', 1, count(aleMele()));

curata();
pune('mort1'); pune('mort2'); pune('viu');

foreach (array_slice(aleMele(), 0, 2) as $r) {
    insemneazaDinCoadaPicat((int) $r['id'], 'No Such User Here', true);
}

verifica('„șterge-le pe toate" le ia pe amândouă', 2, stergeToateCelePicate());
verifica('și tot nu-l atinge pe cel viu', 1, count(aleMele()));

/* ==================================================================== */
sectiune('cifrele de pe panou');

curata();

/**
 * CIFRELE DE PE PANOU NUMĂRĂ TOATĂ COADA, nu doar rândurile probei — și așa și
 * trebuie: omul de casă vrea să știe câte așteaptă cu totul. Deci proba se uită
 * la CREȘTEREA lor, nu la valoarea lor. Una care ar fi cerut „exact 2" ar fi
 * picat de fiecare dată când altă probă lasă ceva în coadă, adică tocmai când
 * suita se rulează întreagă.
 */
$inainteDeToate = cateAsteaptaInCoada();

pune('unu'); pune('doi');

verifica('câte așteaptă a crescut cu două', $inainteDeToate + 2, cateAsteaptaInCoada());

$plecateInainte = catePlecateInUltimulCeas();

trimiteDinCoada(50);

verifica('ale probei nu mai așteaptă', 0, count(iaDinCoada(50)));
verifica('și se văd ca plecate în ultimul ceas', true,
    catePlecateInUltimulCeas() >= $plecateInainte + 2);

$config = $configVechi;

/* ==================================================================== */
echo "\n" . str_repeat('=', 60) . "\n";
echo "  $treceri trecute, $picaturi picate\n";
echo str_repeat('=', 60) . "\n";

exit($picaturi === 0 ? 0 : 1);
