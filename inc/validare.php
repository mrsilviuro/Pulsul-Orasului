<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — verificarea și curățarea datelor de la formulare.
 *
 * Fișierul nu atinge baza de date și nu tipărește nimic. E doar text intrat
 * și text curățat, ca să poată fi testat separat.
 *
 * Regula de bază: tot ce vine de la utilizator e considerat ostil până la
 * proba contrarie. Verificările din browser sunt pentru confortul omului;
 * cele de aici sunt cele care contează, pentru că browserul poate fi ocolit
 * cu un simplu curl.
 */

const NUME_MIN      = 2;
const NUME_MAX      = 60;
const EMAIL_MAX     = 190;   // cât încape în indexul din baza de date
const VARSTA_MIN    = 13;
const VARSTA_MAX    = 120;
const PAROLA_MIN    = 8;
const PAROLA_MAX    = 72;    // bcrypt ignoră tot ce trece de 72 de octeți

/**
 * Aduce diacriticele românești la forma corectă (virgulă dedesubt).
 *
 * Multe tastaturi și programe mai vechi produc ş și ţ cu sedilă — arată
 * aproape la fel, dar sunt alte caractere. Dacă nu le unificăm, „Şerban" și
 * „Șerban" ajung două nume diferite în baza de date.
 */
function normalizeazaDiacritice(string $text): string
{
    return strtr($text, [
        "\u{015F}" => "\u{0219}",  // ş → ș
        "\u{015E}" => "\u{0218}",  // Ş → Ș
        "\u{0163}" => "\u{021B}",  // ţ → ț
        "\u{0162}" => "\u{021A}",  // Ţ → Ț
    ]);
}

/**
 * Scoate spațiile de la capete și le strânge pe cele din interior.
 * „  Ana   Maria " devine „Ana Maria".
 */
function curataSpatii(string $text): string
{
    $text = str_replace("\u{00A0}", ' ', $text);           // spațiu insecabil
    $text = preg_replace('/[\p{Z}\s]+/u', ' ', $text) ?? '';
    return trim($text);
}

/**
 * Pregătește un text venit de la formular: fără octeți de control, cu
 * diacriticele unificate și spațiile curățate.
 */
function pregatesteText(string $text): string
{
    // scoatem caracterele de control, inclusiv \0, care poate fi folosit
    // pentru a păcăli verificările care se opresc la primul octet nul
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text) ?? '';
    return curataSpatii(normalizeazaDiacritice($text));
}

/**
 * Verifică dacă un text arată a nume de persoană.
 *
 * Acceptă: litere latine cu orice semne diacritice, spațiu, cratimă și
 * apostrof între cuvinte — „Ana-Maria", „Ionuț", „O'Neill", „Szabó Anna".
 * Respinge: cifre, semne de punctuație, emoji, etichete HTML.
 *
 * \p{Latin} acoperă și diacriticele maghiare sau germane, care apar firesc
 * în România. \p{M} lasă să treacă și scrierea descompusă (literă + semn
 * separat), pe care o produc unele tastaturi de pe telefon.
 */
function esteNumeValid(string $nume): bool
{
    $litera = '(?:\p{Latin}\p{M}*)';
    return (bool) preg_match('/^' . $litera . '+(?:[ \'\-]' . $litera . '+)*$/u', $nume);
}

/**
 * Scrie numele cu majusculă la fiecare cuvânt: „popescu" → „Popescu",
 * „ANA-MARIA" → „Ana-Maria", „ion popescu" → „Ion Popescu".
 */
function numeCuMajuscula(string $nume): string
{
    return mb_convert_case(mb_strtolower($nume, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

/**
 * Verifică o dată de naștere scrisă ca AAAA-LL-ZZ.
 *
 * Întoarce mesajul de eroare, sau șir gol dacă data e bună.
 */
function verificaDataNasterii(string $data, ?DateTimeImmutable $azi = null): string
{
    $azi = $azi ?? new DateTimeImmutable('today');

    if ($data === '') {
        return 'Alege data nașterii.';
    }

    // Formatul trebuie să fie exact cel trimis de <input type="date">.
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data);

    // Verificarea inversă prinde datele imposibile: createFromFormat acceptă
    // „2026-02-30" și o mută singur pe 2 martie, fără să se plângă.
    if ($d === false || $d->format('Y-m-d') !== $data) {
        return 'Data nașterii nu are un format valid.';
    }

    if ($d > $azi) {
        return 'Data nașterii nu poate fi în viitor.';
    }

    $ani = (int) $d->diff($azi)->y;

    if ($ani < VARSTA_MIN) {
        return 'Trebuie să ai cel puțin ' . VARSTA_MIN . ' ani ca să îți faci cont.';
    }
    if ($ani > VARSTA_MAX) {
        return 'Data nașterii nu pare reală.';
    }

    return '';
}

/**
 * Verifică tot formularul de înregistrare.
 *
 * Primește datele brute, așa cum au venit de la browser.
 * Întoarce:
 *   [
 *     'erori' => ['camp' => 'mesaj', ...],   gol dacă totul e în regulă
 *     'curat' => ['nume' => 'Popescu', ...]  valorile gata de pus în bază
 *   ]
 */
function verificaInregistrare(array $date, ?DateTimeImmutable $azi = null): array
{
    $erori = [];
    $curat = [];

    $citeste = static function (string $cheie) use ($date): string {
        $valoare = $date[$cheie] ?? '';
        return is_string($valoare) ? $valoare : '';
    };

    /* ------------------------------ Nume ------------------------------ */
    $nume = pregatesteText($citeste('nume'));

    if ($nume === '') {
        $erori['nume'] = 'Scrie numele de familie.';
    } elseif (mb_strlen($nume, 'UTF-8') < NUME_MIN) {
        $erori['nume'] = 'Numele pare prea scurt.';
    } elseif (mb_strlen($nume, 'UTF-8') > NUME_MAX) {
        $erori['nume'] = 'Numele e prea lung (maximum ' . NUME_MAX . ' de caractere).';
    } elseif (!esteNumeValid($nume)) {
        $erori['nume'] = 'Numele poate conține doar litere, spații și cratime.';
    } else {
        $curat['nume'] = numeCuMajuscula($nume);
    }

    /* ----------------------------- Prenume ---------------------------- */
    $prenume = pregatesteText($citeste('prenume'));

    if ($prenume === '') {
        $erori['prenume'] = 'Scrie prenumele.';
    } elseif (mb_strlen($prenume, 'UTF-8') < NUME_MIN) {
        $erori['prenume'] = 'Prenumele pare prea scurt.';
    } elseif (mb_strlen($prenume, 'UTF-8') > NUME_MAX) {
        $erori['prenume'] = 'Prenumele e prea lung (maximum ' . NUME_MAX . ' de caractere).';
    } elseif (!esteNumeValid($prenume)) {
        $erori['prenume'] = 'Prenumele poate conține doar litere, spații și cratime.';
    } else {
        $curat['prenume'] = numeCuMajuscula($prenume);
    }

    /* ------------------------------ E-mail ---------------------------- */
    $email = curataSpatii($citeste('email'));

    if ($email === '') {
        $erori['email'] = 'Scrie adresa de e-mail.';
    } elseif (mb_strlen($email, 'UTF-8') > EMAIL_MAX) {
        $erori['email'] = 'Adresa de e-mail e prea lungă.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erori['email'] = 'Adresa de e-mail nu pare validă.';
    } else {
        // Adresele se păstrează cu litere mici, ca „Ion@Email.ro" și
        // „ion@email.ro" să nu ajungă două conturi diferite.
        $curat['email'] = mb_strtolower($email, 'UTF-8');
    }

    /* -------------------------- Data nașterii ------------------------- */
    $dataNasterii = trim($citeste('data_nasterii'));
    $eroareData = verificaDataNasterii($dataNasterii, $azi);

    if ($eroareData !== '') {
        $erori['data_nasterii'] = $eroareData;
    } else {
        $curat['data_nasterii'] = $dataNasterii;
    }

    /* -------------------------------- Sex ----------------------------- */
    // Doar două valori sunt acceptate. Orice altceva — inclusiv o valoare
    // trimisă direct, fără formular — e respinsă.
    $sex = strtoupper(trim($citeste('sex')));
    $echivalente = ['M' => 'M', 'MASCULIN' => 'M', 'F' => 'F', 'FEMININ' => 'F'];

    if ($sex === '') {
        $erori['sex'] = 'Alege o opțiune.';
    } elseif (!isset($echivalente[$sex])) {
        $erori['sex'] = 'Alege o opțiune validă.';
    } else {
        $curat['sex'] = $echivalente[$sex];
    }

    /* ------------------------------ Parola ---------------------------- */
    $parola  = $citeste('parola');
    $parola2 = $citeste('parola_confirmare');
    $octeti  = strlen($parola);           // octeți, nu caractere: bcrypt taie la 72

    if ($parola === '') {
        $erori['parola'] = 'Alege o parolă.';
    } elseif (mb_strlen($parola, 'UTF-8') < PAROLA_MIN) {
        $erori['parola'] = 'Parola trebuie să aibă minimum ' . PAROLA_MIN . ' caractere.';
    } elseif ($octeti > PAROLA_MAX) {
        $erori['parola'] = 'Parola e prea lungă. Alege una de cel mult ' . PAROLA_MAX . ' de caractere.';
    } elseif (preg_match('/^\s+$/u', $parola)) {
        $erori['parola'] = 'Parola nu poate fi formată doar din spații.';
    } else {
        $curat['parola'] = $parola;
    }

    // Câmpul gol se semnalează întotdeauna, ca browserul și serverul să
    // marcheze exact aceleași câmpuri. Nepotrivirea are sens doar dacă
    // parola în sine a trecut de verificări.
    if ($parola2 === '') {
        $erori['parola_confirmare'] = 'Scrie parola din nou.';
    } elseif (!isset($erori['parola']) && !hash_equals($parola, $parola2)) {
        $erori['parola_confirmare'] = 'Cele două parole nu coincid.';
    }

    /* ------------------------------ Termeni --------------------------- */
    $termeni = $date['termeni'] ?? '';
    $acceptat = in_array($termeni, [true, 1, '1', 'on', 'true', 'da'], true);

    if (!$acceptat) {
        $erori['termeni'] = 'Trebuie să accepți termenii ca să continui.';
    }

    return ['erori' => $erori, 'curat' => $curat];
}

/**
 * Construiește adresa publică a profilului.
 *
 * Un șir aleatoriu, nu numele persoanei. Motivele sunt scrise pe larg în
 * README; pe scurt: numele se repetă, adresa ar rămâne greșită după o
 * schimbare de nume, iar profilurile n-ar trebui să poată fi ghicite.
 */
function permalinkNou(int $lungime = 10): string
{
    // Fără 0/O și 1/l/I, ca să nu fie confundate când cineva dictează adresa.
    $alfabet = '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $maxim   = strlen($alfabet) - 1;
    $rezultat = '';

    for ($i = 0; $i < $lungime; $i++) {
        $rezultat .= $alfabet[random_int(0, $maxim)];
    }

    return $rezultat;
}

/**
 * Prescurtează numele pentru afișare: „Popescu", „Ionuț" → „P. Ionuț".
 *
 * Se folosește la scriere, ca forma prescurtată să fie singura care ajunge
 * vreodată în pagină.
 */
function numeAfisat(string $nume, string $prenume): string
{
    $initiala = mb_substr($nume, 0, 1, 'UTF-8');
    return mb_strtoupper($initiala, 'UTF-8') . '. ' . $prenume;
}

/**
 * Vârsta în ani împliniți, dintr-o dată de forma „1990-05-17".
 *
 * Întoarce null dacă data lipsește sau nu se înțelege — mai bine nu afișăm
 * nimic decât o vârstă greșită.
 */
function varstaDin(?string $dataNasterii, ?DateTimeImmutable $azi = null): ?int
{
    if ($dataNasterii === null || $dataNasterii === '') {
        return null;
    }

    try {
        $nasterea = new DateTimeImmutable($dataNasterii);
    } catch (Exception $e) {
        return null;
    }

    $azi = $azi ?? new DateTimeImmutable('today');
    $ani = (int) $azi->diff($nasterea)->y;

    return ($ani >= 0 && $ani <= VARSTA_MAX) ? $ani : null;
}

/** „34 de ani", „21 de ani", „1 an" — cu regula românească a lui „de". */
function aniInCuvinte(int $ani): string
{
    if ($ani === 1) {
        return 'un an';
    }

    // În română, „de" apare de la 20 în sus, iar apoi din nou după 100, 101…
    $rest = $ani % 100;
    $are_de = ($rest === 0 || $rest >= 20);

    return $ani . ($are_de ? ' de ani' : ' ani');
}

/**
 * „mai 2024" — luna și anul, scrise cu literă mică, ca în text curent.
 *
 * Numele lunilor sunt scrise aici, nu luate de la sistem: pe un server fără
 * pachetul de limbă românească instalat, strftime() ar da „May 2024".
 */
function lunaSiAnul(?string $data): string
{
    if ($data === null || $data === '') {
        return '';
    }

    try {
        $moment = new DateTimeImmutable($data);
    } catch (Exception $e) {
        return '';
    }

    $luni = [
        1 => 'ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie',
        'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie',
    ];

    return $luni[(int) $moment->format('n')] . ' ' . $moment->format('Y');
}

/**
 * O parolă temporară, pentru cine și-a uitat parola.
 *
 * Șase caractere, cifre și litere mari. Fără 0 și O, fără 1 și I: parola asta
 * se citește dintr-un e-mail și se tastează de mână, iar perechile alea sunt
 * cele care se confundă. Rămân 32 de caractere, adică peste un miliard de
 * combinații — destule, mai ales că parola se stinge după cinci greșeli.
 *
 * random_int() ia numerele de la sursa criptografică a sistemului, nu de la
 * rand(), ale cărui rezultate se pot prezice după ce vezi câteva.
 */
function parolaTemporaraNoua(int $lungime = 6): string
{
    $alfabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $maxim   = strlen($alfabet) - 1;
    $rezultat = '';

    for ($i = 0; $i < $lungime; $i++) {
        $rezultat .= $alfabet[random_int(0, $maxim)];
    }

    return $rezultat;
}
