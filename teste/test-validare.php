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

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=',60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
