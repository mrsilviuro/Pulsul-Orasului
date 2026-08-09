<?php
declare(strict_types=1);
require __DIR__ . '/../inc/validare.php';

$treceri = 0; $picaturi = 0;
function verifica(string $ce, $asteptat, $primit) {
    global $treceri, $picaturi;
    $ok = $asteptat === $primit;
    $ok ? $treceri++ : $picaturi++;
    printf("%-58s %s%s\n", $ce, $ok ? 'OK' : 'PICAT',
        $ok ? '' : "  (aștept " . var_export($asteptat, true) . ", am primit " . var_export($primit, true) . ")");
}

echo "=== NUME: majusculă și caractere permise ===\n";
$azi = new DateTimeImmutable('2026-08-06');
function reg(array $peste, DateTimeImmutable $azi) {
    return verificaInregistrare(array_merge([
        'nume' => 'Popescu', 'prenume' => 'Ionut', 'email' => 'a@b.ro',
        'data_nasterii' => '1990-05-20', 'sex' => 'M',
        'parola' => 'parolamea1', 'parola_confirmare' => 'parolamea1', 'termeni' => '1',
    ], $peste), $azi);
}
verifica('"popescu" devine "Popescu"', 'Popescu', reg(['nume'=>'popescu'], $azi)['curat']['nume'] ?? null);
verifica('"POPESCU" devine "Popescu"', 'Popescu', reg(['nume'=>'POPESCU'], $azi)['curat']['nume'] ?? null);
verifica('"pOpEsCu" devine "Popescu"', 'Popescu', reg(['nume'=>'pOpEsCu'], $azi)['curat']['nume'] ?? null);
verifica('"popescu-ionescu" -> "Popescu-Ionescu"', 'Popescu-Ionescu', reg(['nume'=>'popescu-ionescu'], $azi)['curat']['nume'] ?? null);
verifica('spații multiple curățate', 'Ana Maria', reg(['prenume'=>'  ana    maria  '], $azi)['curat']['prenume'] ?? null);
verifica('diacritice păstrate', 'Ștefănescu', reg(['nume'=>'ștefănescu'], $azi)['curat']['nume'] ?? null);
verifica('sedila ş devine virgulă ș', 'Șerban', reg(['nume'=>"\u{015F}erban"], $azi)['curat']['nume'] ?? null);
verifica('prenume compus cu cratimă', 'Ana-Maria', reg(['prenume'=>'ana-maria'], $azi)['curat']['prenume'] ?? null);
verifica('nume maghiar cu diacritice', 'Szabó', reg(['nume'=>'szabó'], $azi)['curat']['nume'] ?? null);

echo "\n=== NUME: ce trebuie respins ===\n";
foreach ([
  'cifre în nume'          => 'Popescu2',
  'simboluri'              => 'Popescu@',
  'etichetă HTML'          => '<b>Popescu</b>',
  'injecție SQL'           => "Popescu'; DROP TABLE membri;--",
  'emoji'                  => 'Popescu😀',
  'doar spații'            => '   ',
  'o singură literă'       => 'P',
  'punct'                  => 'Popescu.',
  'underscore'             => 'Popescu_Ion',
] as $ce => $valoare) {
    $r = reg(['nume'=>$valoare], $azi);
    verifica("respins: $ce", true, isset($r['erori']['nume']));
}
verifica('nume prea lung (61 de litere) respins', true, isset(reg(['nume'=>str_repeat('a',61)], $azi)['erori']['nume']));

echo "\n=== E-MAIL ===\n";
verifica('adresă validă acceptată', 'ion.popescu@email.ro', reg(['email'=>'Ion.Popescu@Email.RO'], $azi)['curat']['email'] ?? null);
foreach (['fara-arond','a@','@b.ro','a@b','a b@c.ro','a@b..ro',''] as $rea) {
    verifica("respins: '$rea'", true, isset(reg(['email'=>$rea], $azi)['erori']['email']));
}

echo "\n=== DATA NAȘTERII ===\n";
verifica('dată validă', '1990-05-20', reg(['data_nasterii'=>'1990-05-20'], $azi)['curat']['data_nasterii'] ?? null);
verifica('30 februarie respins', true, isset(reg(['data_nasterii'=>'2000-02-30'], $azi)['erori']['data_nasterii']));
verifica('viitor respins', true, isset(reg(['data_nasterii'=>'2030-01-01'], $azi)['erori']['data_nasterii']));
verifica('format zz/ll/aaaa respins', true, isset(reg(['data_nasterii'=>'20/05/1990'], $azi)['erori']['data_nasterii']));
verifica('text respins', true, isset(reg(['data_nasterii'=>'ieri'], $azi)['erori']['data_nasterii']));
verifica('anul 1800 respins', true, isset(reg(['data_nasterii'=>'1800-01-01'], $azi)['erori']['data_nasterii']));
verifica('12 ani respins', true, isset(reg(['data_nasterii'=>'2014-08-06'], $azi)['erori']['data_nasterii']));
verifica('exact 13 ani acceptat', false, isset(reg(['data_nasterii'=>'2013-08-06'], $azi)['erori']['data_nasterii']));
verifica('13 ani fără o zi respins', true, isset(reg(['data_nasterii'=>'2013-08-07'], $azi)['erori']['data_nasterii']));

echo "\n=== SEX ===\n";
verifica('"M" acceptat', 'M', reg(['sex'=>'M'], $azi)['curat']['sex'] ?? null);
verifica('"Feminin" -> F', 'F', reg(['sex'=>'Feminin'], $azi)['curat']['sex'] ?? null);
foreach (['balaur','elicopter','X','nespecificat','','0','M; DROP'] as $rea) {
    verifica("respins: '$rea'", true, isset(reg(['sex'=>$rea], $azi)['erori']['sex']));
}

echo "\n=== PAROLA ===\n";
verifica('7 caractere respinse', true, isset(reg(['parola'=>'1234567','parola_confirmare'=>'1234567'], $azi)['erori']['parola']));
verifica('8 caractere acceptate', false, isset(reg(['parola'=>'12345678','parola_confirmare'=>'12345678'], $azi)['erori']['parola']));
verifica('parole diferite respinse', true, isset(reg(['parola_confirmare'=>'altceva12'], $azi)['erori']['parola_confirmare']));
verifica('peste 72 de octeți respinsă', true, isset(reg(['parola'=>str_repeat('a',73),'parola_confirmare'=>str_repeat('a',73)], $azi)['erori']['parola']));
verifica('doar spații respinsă', true, isset(reg(['parola'=>'         ','parola_confirmare'=>'         '], $azi)['erori']['parola']));

echo "\n=== TERMENI ===\n";
verifica('nebifat respins', true, isset(reg(['termeni'=>''], $azi)['erori']['termeni']));
verifica('bifat acceptat', false, isset(reg(['termeni'=>'1'], $azi)['erori']['termeni']));

echo "\n=== CÂMPURI LIPSĂ CU TOTUL ===\n";
$r = verificaInregistrare([], $azi);
verifica('formular gol: 8 erori', 8, count($r['erori']));

echo "\n=== PERMALINK ===\n";
$p = permalinkNou();
verifica('10 caractere', 10, strlen($p));
verifica('fără caractere confundabile', 0, preg_match('/[0O1lI]/', $p));
$set = []; for ($i=0;$i<5000;$i++) $set[permalinkNou()] = 1;
verifica('5000 generate, toate diferite', 5000, count($set));

echo "\n=== NUME AFIȘAT ===\n";
verifica('Popescu Ionuț -> P. Ionuț', 'P. Ionuț', numeAfisat('Popescu','Ionuț'));
verifica('ștefan -> Ș. ...', 'Ș. Ana', numeAfisat('ștefan','Ana'));

echo "\n=== PAROLA TEMPORARĂ ===\n";
$pt = parolaTemporaraNoua();
verifica('6 caractere', 6, strlen($pt));
verifica('doar cifre și litere mari', 1, preg_match('/^[A-Z0-9]{6}$/', $pt));
verifica('fără caractere confundabile (0, O, 1, I)', 0, preg_match('/[0O1I]/', $pt));

// Se generează multe și se verifică două lucruri deodată: că nu se repetă și
// că se folosește tot alfabetul, nu doar câteva litere.
$multe = []; $litere = [];
for ($i = 0; $i < 5000; $i++) {
    $x = parolaTemporaraNoua();
    $multe[$x] = 1;
    foreach (str_split($x) as $c) $litere[$c] = 1;
}
verifica('5000 generate, aproape toate diferite', true, count($multe) > 4990);
verifica('folosește toate cele 32 de caractere', 32, count($litere));

echo "\n=== TELEFON ===\n";

// Perechi, nu chei: PHP ar preface „40722334455" în număr întreg.
foreach ([
    ['0722334455',      '0722334455'],
    ['0722 33 44 55',   '0722334455'],
    ['0722-334-455',    '0722334455'],
    ['(0722) 334.455',  '0722334455'],
    ['+40 722 334 455', '0722334455'],
    ['0040722334455',   '0722334455'],
    ['40722334455',     '0722334455'],
    ['0212223344',      '0212223344'],
    ['0312223344',      '0312223344'],
    ['  0722334455  ',  '0722334455'],
] as [$scris, $asteptat]) {
    verifica('"' . trim($scris) . '" -> ' . $asteptat, $asteptat, verificaTelefon($scris)['curat']);
}

verifica('gol e bun (câmpul e opțional)', true, verificaTelefon('')['ok']);
verifica('doar spații e tot gol', '', verificaTelefon('   ')['curat']);

foreach ([
  'prea scurt'        => '0722334',
  'prea lung'         => '07223344556',
  'prefix inexistent' => '0522334455',
  'nu începe cu 0'    => '722334455',
  'litere'            => '07abcdefgh',
  'etichetă HTML'     => '<b>0722334455</b>',
  'injecție SQL'      => "0722334455'; DROP TABLE membri;--",
  'număr francez'     => '+33612345678',
  'plus la mijloc'    => '0722+334455',
  'doar plus'         => '+',
  'emoji'             => '0722334455😀',
] as $ce => $valoare) {
    verifica('respins: ' . $ce, false, verificaTelefon($valoare)['ok']);
}

echo "\n=== DATA SCURTĂ ===\n";

foreach ([
    ['2026-08-03', '3 aug 2026'],
    ['2026-01-15', '15 ian 2026'],
    ['2026-05-01', '1 mai 2026'],
    ['2026-06-30', '30 iun 2026'],
    ['2026-07-04', '4 iul 2026'],
    ['2026-12-25', '25 dec 2026'],
] as [$data, $asteptat]) {
    verifica('"' . $data . '" -> ' . $asteptat, $asteptat, dataScurta($data));
}

verifica('data lipsă → nimic', '', dataScurta(null));
verifica('data goală → nimic', '', dataScurta(''));
verifica('data aiurea → nimic, nu o ghicim', '', dataScurta('gogoașă'));

echo "\n=== ÎNCEPUT DE TEXT (pe cartonașe) ===\n";

verifica('textul scurt rămâne întreg', 'Ceva scurt.', inceputDeText('Ceva scurt.'));
verifica('rândurile noi devin spații', 'Un rând. Alt paragraf.',
    inceputDeText("Un rând.\n\nAlt paragraf."));
verifica('spațiile multiple se strâng', 'a b c', inceputDeText("a   b \t c"));

$lung = inceputDeText(str_repeat('cuvânt ', 60), 40);
verifica('se taie la limită', true, mb_strlen($lung, 'UTF-8') <= 41);
verifica('și se termină cu trei puncte', true, str_ends_with($lung, '…'));
verifica('fără cuvânt rupt în două', true, !str_contains($lung, 'cuv…'));

// 200 de „ă" = 400 de octeți. Cu strlen() s-ar fi tăiat la jumătate.
$diacritice = inceputDeText(str_repeat('ă', 200), 100);
verifica('se numără litere, nu octeți', 101, mb_strlen($diacritice, 'UTF-8'));

verifica('un cuvânt fără spații se taie sec', true,
    str_ends_with(inceputDeText(str_repeat('x', 300), 50), 'x…'));
verifica('textul gol rămâne gol', '', inceputDeText(''));

echo "\n=== DATA LUNGĂ, ORA ȘI COSTUL ===\n";

verifica('data cu ziua săptămânii', 'Duminică, 16 august 2026', dataLunga('2026-08-16'));
verifica('luni', 'Luni, 17 august 2026', dataLunga('2026-08-17'));
verifica('sâmbătă', 'Sâmbătă, 3 ianuarie 2026', dataLunga('2026-01-03'));
verifica('data aiurea → nimic', '', dataLunga('gogoașă'));

verifica('ora fără secunde', '19:00', oraScurta('19:00:00'));
verifica('ora lipsă → nimic', '', oraScurta(null));
verifica('ora stricată → nimic', '', oraScurta('mai târziu'));

verifica('NULL înseamnă gratuit', 'Gratuit', costScris(null));
verifica('și zero tot gratuit', 'Gratuit', costScris('0.00'));
verifica('suma rotundă, fără zecimale', '25 lei', costScris('25.00'));
verifica('suma cu bani, cu virgulă', '25,50 lei', costScris('25.50'));
verifica('miile despărțite', '1.200 lei', costScris('1200.00'));

echo "\n=== CALEA DE ÎNTOARCERE DUPĂ INTRARE ===\n";

verifica('o cale de-a noastră trece', '/setari.php', caleInterna('/setari.php'));
verifica('cu parametri cu tot', '/event.php?slug=a-b-c', caleInterna('/event.php?slug=a-b-c'));

/**
 * Fiecare dintre astea a fost, la un moment dat, o cale de a scoate omul de pe
 * site imediat după ce s-a conectat. Bara inversă e cea mai urâtă: browserul o
 * îndreaptă, iar „/\alt-site.ro" ajunge „//alt-site.ro", adică alt domeniu.
 */
foreach ([
    'protocol-relativ'      => '//alt-site.ro',
    'bară inversă'          => '/\\alt-site.ro',
    'două bare inverse'     => '\\\\alt-site.ro',
    'bară inversă la mijloc'=> '/pagina\\..\\alt',
    'adresă întreagă'       => 'https://alt-site.ro',
    'fără bară la început'  => 'setari.php',
    'javascript'            => 'javascript:alert(1)',
    'date:'                 => 'data:text/html,<script>alert(1)</script>',
    'rând nou'              => "/setari.php\nLocation: https://alt-site.ro",
    'tab'                   => "/\tsetari.php",
    'octet nul'             => "/setari.php\0",
    'gol'                   => '',
    'doar spații'           => '   ',
] as $ce => $valoare) {
    verifica('respinsă: ' . $ce, '', caleInterna($valoare));
}

verifica('nu se acceptă nici null', '', caleInterna(null));
verifica('prea lungă → respinsă', '', caleInterna('/' . str_repeat('a', 400)));

echo "\n=== DATA, ÎNTRE PAGINĂ ȘI BAZĂ ===\n";

// Din formular vine ZZ-LL-AAAA, cum se scrie o dată în România. În bază intră
// AAAA-LL-ZZ, cum o cere MySQL.
verifica('o dată obișnuită', '2026-12-25', dataDinFormular('25-12-2026'));
verifica('prima zi a anului', '2026-01-01', dataDinFormular('01-01-2026'));
verifica('cu spații în jur', '2026-12-25', dataDinFormular('  25-12-2026  '));

// 2028 e bisect, 2027 nu. checkdate() e cel care știe asta, nu noi.
verifica('29 februarie într-un an bisect', '2028-02-29', dataDinFormular('29-02-2028'));
verifica('29 februarie într-un an nebisect → nu', '', dataDinFormular('29-02-2027'));
verifica('31 aprilie → nu', '', dataDinFormular('31-04-2026'));
verifica('luna 13 → nu', '', dataDinFormular('01-13-2026'));
verifica('ziua 00 → nu', '', dataDinFormular('00-12-2026'));

/**
 * Formatul e strict. Mai ales AAAA-LL-ZZ nu se primește: două formate
 * acceptate înseamnă că într-o zi cineva trimite „01-02-2026" crezând una și
 * noi înțelegem alta.
 */
foreach ([
    'formatul bazei'   => '2026-12-25',
    'cu bare'          => '25/12/2026',
    'cu puncte'        => '25.12.2026',
    'fără zerouri'     => '5-3-2026',
    'an din două cifre'=> '25-12-26',
    'text'             => 'douăzeci și cinci',
    'gol'              => '',
    'doar cratime'     => '--',
] as $ce => $valoare) {
    verifica('respinsă: ' . $ce, '', dataDinFormular($valoare));
}

verifica('nici null', '', dataDinFormular(null));

// Drumul invers, pentru câmpul din formular.
verifica('din bază în formular', '25-12-2026', dataPentruFormular('2026-12-25'));
verifica('merge și cu ora lipită', '25-12-2026', dataPentruFormular('2026-12-25 19:00:00'));
verifica('dus-întors dă același lucru', '2026-12-25',
    dataDinFormular(dataPentruFormular('2026-12-25')));
verifica('o valoare stricată nu dă nimic', '', dataPentruFormular('mâine'));
verifica('nici null', '', dataPentruFormular(null));

echo "\n=== MOTIVUL ANULĂRII ===\n";

$motiv = static fn ($t): string => verificaMotivAnulare($t)['eroare'] === ''
    ? verificaMotivAnulare($t)['text'] : '';

verifica('un motiv cumsecade trece', 'S-a stricat vremea rău de tot.',
    $motiv('S-a stricat vremea rău de tot.'));
verifica('rândurile se păstrează', "Prima parte.\n\nA doua.",
    $motiv("Prima parte.\n\n\n\nA doua."));

verifica('gol → eroare', true, verificaMotivAnulare('')['eroare'] !== '');
verifica('doar spații → eroare', true, verificaMotivAnulare('    ')['eroare'] !== '');
verifica('prea scurt → eroare', true, verificaMotivAnulare('ploua')['eroare'] !== '');
verifica('nici null nu trece', true, verificaMotivAnulare(null)['eroare'] !== '');
verifica('prea lung → eroare', true,
    verificaMotivAnulare(str_repeat('a', MOTIV_ANULARE_MAX + 1))['eroare'] !== '');

// Se numără caractere, nu octeți: exact la limită, scris cu diacritice.
verifica('fix la limită, cu diacritice', true,
    verificaMotivAnulare(str_repeat('ă', MOTIV_ANULARE_MIN))['eroare'] === '');
verifica('cu unul mai puțin, nu', true,
    verificaMotivAnulare(str_repeat('ă', MOTIV_ANULARE_MIN - 1))['eroare'] !== '');

echo "\n=== CÂTE CARACTERE ARE DESCRIEREA ===\n";

/**
 * Contorul de sub casetă spunea „300 din 300", iar serverul răspundea „ai
 * 299": el măsura textul curățat, contorul pe cel brut.
 *
 * Perechea lui în browser e numaraCaractere(curataTextPeRanduri(...)) din
 * assets/js/main.js. Numerele de mai jos sunt exact ce trebuie să dea și
 * acolo — dacă vreunul se schimbă aici, s-a rupt și oglinda.
 */
$cate = static fn (string $t): int => mb_strlen(curataTextPeRanduri($t), 'UTF-8');

verifica('litere simple', 300, $cate(str_repeat('a', 300)));
verifica('diacriticele sunt caractere, nu octeți', 300, $cate(str_repeat('ă', 300)));
verifica('un emoji e un caracter, nu două', 300, $cate(str_repeat('a', 299) . '😀'));

// Emoji lipit din mai multe bucăți: patru chipuri și trei lipituri
// invizibile (U+200D). Se numără ca șapte — nu ce vede ochiul, dar exact
// același număr ca în browser, iar asta era problema.
verifica('familia e șapte caractere', 300, $cate(str_repeat('a', 293) . '👨‍👩‍👧‍👦'));
verifica('steagul e patru', 300, $cate(str_repeat('a', 296) . '🏳️‍🌈'));

verifica('spațiul de la coadă nu se numără', 299, $cate(str_repeat('a', 299) . ' '));
verifica('nici rândurile goale de la coadă', 298, $cate(str_repeat('a', 298) . "\n\n"));
verifica('nici cele de la început', 298, $cate("\n\n" . str_repeat('a', 298)));
verifica('sfârșitul de rând Windows e unul singur', 3, $cate("a\r\nb"));
verifica('cinci rânduri goale la mijloc devin unul', 302,
    $cate(str_repeat('a', 150) . "\n\n\n\n\n" . str_repeat('a', 150)));
verifica('caracterele de control cad', 300, $cate(str_repeat('a', 300) . "\x01\x7F"));
verifica('dar tabul rămâne', 301, $cate(str_repeat('a', 150) . "\t" . str_repeat('a', 150)));

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=',60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
