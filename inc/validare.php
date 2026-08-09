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
const MESAJ_MIN     = 10;    // mesajul din formularul de contact
const MESAJ_MAX     = 5000;

/* Evenimente */
const TITLU_EVENIMENT_MIN = 8;
const TITLU_EVENIMENT_MAX = 140;
const LOCATIE_MIN         = 4;
const LOCATIE_MAX         = 160;
const DESCRIERE_MIN       = 300;   // caractere, nu octeți — vezi verificaEveniment()
const DESCRIERE_MAX       = 8000;
const COST_MAX            = 99999.99;
const PARTICIPANTI_MAX    = 65535; // cât încape în SMALLINT UNSIGNED
const ANI_INAINTE_MAX     = 2;     // cât de departe în viitor poate fi pus un eveniment
const MOTIV_ANULARE_MIN   = 15;    // caractere, ca la descriere — vezi verificaMotivAnulare()
const MOTIV_ANULARE_MAX   = 1000;

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
 * Un titlu, o locație — text scris de om, care ajunge pe pagină.
 *
 * Nu se curăță de HTML aici: textul se păstrează în bază exact cum l-a scris
 * omul, iar scăparea se face la AFIȘARE, cu h(). Dacă am curăța la salvare,
 * un titlu ca „Meci Dinamo & Rapid" ar ajunge în bază cu „&amp;" și l-am
 * scăpa a doua oară la afișare — omul ar citi „Dinamo &amp; Rapid".
 *
 * Se scot doar caracterele de control, care n-au ce căuta nicăieri.
 */
function curataTextLiber(string $text): string
{
    // Tot ce e sub spațiu, plus DEL. Rândurile noi și taburile se păstrează
    // separat, acolo unde au sens (vezi curataTextPeRanduri).
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text) ?? '';

    return curataSpatii($text);
}

/**
 * Text pe mai multe rânduri: descrierea unui eveniment.
 *
 * Paragrafele omului rămân întregi — el le-a pus dintr-un motiv. Se
 * îndreaptă doar sfârșiturile de rând (Windows scrie \r\n) și se taie
 * șirurile de peste două rânduri goale la rând, ca nimeni să nu-și împingă
 * anunțul mai jos în pagină cu cincizeci de Enter-uri.
 */
function curataTextPeRanduri(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // Caracterele de control, dar NU rândul nou și nu tabul.
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';

    // Cel mult un rând gol între paragrafe.
    $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? '';

    return trim($text);
}

/**
 * Începutul unui text, pentru cartonașele de pe listă.
 *
 * Se taie la ultimul spațiu dinaintea limitei, nu în mijlocul unui cuvânt, iar
 * rândurile noi devin spații: pe cartonaș descrierea e un rând-două, nu un
 * text cu paragrafe.
 *
 * Se numără cu mb_strlen, nu cu strlen: în UTF-8 „ă" ocupă doi octeți, deci o
 * tăiere pe octeți ar scurta mai tare tocmai textele scrise cu diacritice — și
 * ar putea reteza un caracter în două.
 */
function inceputDeText(string $text, int $caractere = 160): string
{
    $intrUnRand = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

    if (mb_strlen($intrUnRand, 'UTF-8') <= $caractere) {
        return $intrUnRand;
    }

    $taiat = mb_substr($intrUnRand, 0, $caractere, 'UTF-8');
    $ultimulSpatiu = mb_strrpos($taiat, ' ', 0, 'UTF-8');

    // Un text fără niciun spațiu în primele $caractere rămâne tăiat sec:
    // n-avem unde altundeva să-l rupem.
    if ($ultimulSpatiu !== false && $ultimulSpatiu > $caractere / 2) {
        $taiat = mb_substr($taiat, 0, $ultimulSpatiu, 'UTF-8');
    }

    return rtrim($taiat, " ,.;:–-") . '…';
}

/**
 * Formularul de eveniment, verificat pe server.
 *
 * $categoriiValide vine din bază, $oraseValide din inc/config.php — amândouă
 * ca argumente, fiindcă fișierul ăsta nu deschide nici baza, nici configul:
 * așa poate fi probat singur, cu `php teste/test-validare.php`.
 *
 * $azi se poate da din teste, ca verificarea datei să nu depindă de ziua în
 * care se rulează.
 *
 * Întoarce ['erori' => [...], 'curat' => [...]].
 */
function verificaEveniment(array $date, array $categoriiValide, array $oraseValide = [],
                           ?DateTimeImmutable $azi = null): array
{
    $azi   = $azi ?? new DateTimeImmutable('today');
    $erori = [];
    $curat = [];

    $citeste = static function (string $cheie) use ($date): string {
        $valoare = $date[$cheie] ?? '';
        return is_string($valoare) ? $valoare : '';
    };

    /* ------------------------------ Titlul ---------------------------- */
    $titlu = curataTextLiber($citeste('titlu'));

    if ($titlu === '') {
        $erori['titlu'] = 'Scrie un titlu.';
    } elseif (mb_strlen($titlu, 'UTF-8') < TITLU_EVENIMENT_MIN) {
        $erori['titlu'] = 'Titlul e prea scurt — spune în câteva cuvinte despre ce e vorba.';
    } elseif (mb_strlen($titlu, 'UTF-8') > TITLU_EVENIMENT_MAX) {
        $erori['titlu'] = 'Titlul e prea lung (cel mult ' . TITLU_EVENIMENT_MAX . ' de caractere).';
    } else {
        $curat['titlu'] = $titlu;
    }

    /* ---------------------------- Categoria --------------------------- */
    $categorie = (int) $citeste('categorie_id');

    if ($categorie <= 0) {
        $erori['categorie_id'] = 'Alege o categorie.';
    } elseif (!in_array($categorie, $categoriiValide, true)) {
        // Lista vine din baza de date, nu din formular: cine trimite un id
        // inventat nu poate strecura un eveniment într-o categorie care nu e.
        $erori['categorie_id'] = 'Alege o categorie din listă.';
    } else {
        $curat['categorie_id'] = $categorie;
    }

    /* ------------------------------ Data ------------------------------ */
    /**
     * Din formular vine ZZ-LL-AAAA, cum se scrie o dată în România. În bază
     * intră AAAA-LL-ZZ, cum o cere MySQL. Traducerea o face dataDinFormular(),
     * într-un singur loc, ca nici pagina, nici baza să nu vadă vreodată
     * formatul celeilalte.
     */
    $data = dataDinFormular($citeste('data_eveniment'));

    if (trim($citeste('data_eveniment')) === '') {
        $erori['data_eveniment'] = 'Alege data.';
    } elseif ($data === '') {
        $erori['data_eveniment'] = 'Data nu e validă. Scrie-o ca 25-12-2026.';
    } else {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data);

        if ($d === false) {
            $erori['data_eveniment'] = 'Data nu e validă.';
        } elseif ($d < $azi) {
            $erori['data_eveniment'] = 'Data a trecut deja. Alege una de azi înainte.';
        } elseif ($d > $azi->modify('+' . ANI_INAINTE_MAX . ' years')) {
            $erori['data_eveniment'] = 'Data e prea departe în viitor.';
        } else {
            $curat['data_eveniment'] = $data;
        }
    }

    /* ------------------------------ Orele ----------------------------- */
    $inceput = trim($citeste('ora_inceput'));

    if ($inceput === '') {
        $erori['ora_inceput'] = 'Scrie ora de început.';
    } elseif (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $inceput)) {
        $erori['ora_inceput'] = 'Ora nu e validă.';
    } else {
        $curat['ora_inceput'] = $inceput . ':00';
    }

    /**
     * „Nedeterminat" e o alegere, nu o omisiune.
     *
     * De aceea are bifa lui: altfel n-am putea deosebi „nu se știe cât ține"
     * de „am uitat să completez".
     */
    $faraSfarsit = !empty($date['fara_ora_sfarsit']);

    if ($faraSfarsit) {
        $curat['ora_sfarsit'] = null;
    } else {
        $sfarsit = trim($citeste('ora_sfarsit'));

        if ($sfarsit === '') {
            // Bifa se cheamă în pagină „Nu se știe până când ține". Mesajul o
            // numește la fel: altfel omul caută în formular ceva ce nu există.
            $erori['ora_sfarsit'] = 'Scrie ora de sfârșit, sau bifează „Nu se știe până când ține".';
        } elseif (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $sfarsit)) {
            $erori['ora_sfarsit'] = 'Ora nu e validă.';
        } else {
            // Nu comparăm cele două ore: un eveniment poate începe la 22:00 și
            // se poate termina la 02:00. Ar fi o „greșeală" care nu e greșeală.
            $curat['ora_sfarsit'] = $sfarsit . ':00';
        }
    }

    /* ------------------------------ Orașul ---------------------------- */
    /**
     * Nu e text liber: trebuie să fie unul dintre orașele din inc/config.php.
     *
     * Aceeași regulă ca la categorie, și din același motiv — lista vine din
     * altă parte decât formularul, deci cine trimite „Bucuresti" cu mâna lui,
     * ocolind pagina, nu poate strecura un eveniment într-un oraș în care nu
     * suntem. Comparația e exactă, cu in_array strict: „roman" cu literă mică
     * nu e același lucru cu „Roman", fiindcă în bază intră exact ce e aici.
     */
    $oras = trim($citeste('oras'));

    if ($oras === '') {
        $erori['oras'] = 'Alege orașul.';
    } elseif (!in_array($oras, $oraseValide, true)) {
        $erori['oras'] = 'Alege un oraș din listă.';
    } else {
        $curat['oras'] = $oras;
    }

    /* ----------------------------- Locația ---------------------------- */
    $locatie = curataTextLiber($citeste('locatie'));

    if ($locatie === '') {
        $erori['locatie'] = 'Scrie unde are loc.';
    } elseif (mb_strlen($locatie, 'UTF-8') < LOCATIE_MIN) {
        $erori['locatie'] = 'Locația e prea scurtă.';
    } elseif (mb_strlen($locatie, 'UTF-8') > LOCATIE_MAX) {
        $erori['locatie'] = 'Locația e prea lungă (cel mult ' . LOCATIE_MAX . ' de caractere).';
    } else {
        $curat['locatie'] = $locatie;
    }

    /* ------------------------------ Costul ---------------------------- */
    if (!empty($date['gratuit'])) {
        $curat['cost'] = null;
    } else {
        // Oamenii scriu „25,50" la fel de des ca „25.50".
        $cost = str_replace(',', '.', trim($citeste('cost')));

        if ($cost === '') {
            $erori['cost'] = 'Scrie cât costă, sau bifează „Gratuit".';
        } elseif (!preg_match('/^[0-9]{1,5}(\.[0-9]{1,2})?$/', $cost)) {
            $erori['cost'] = 'Scrie o sumă, de forma 25 sau 25.50.';
        } elseif ((float) $cost > COST_MAX) {
            $erori['cost'] = 'Suma e prea mare.';
        } else {
            $curat['cost'] = number_format((float) $cost, 2, '.', '');
        }
    }

    /* -------------------------- Vârsta minimă ------------------------- */
    $varsta = trim($citeste('varsta_minima'));

    if ($varsta === '' || $varsta === 'nespecificat') {
        $curat['varsta_minima'] = null;
    } elseif (!in_array($varsta, ['13', '16', '18'], true)) {
        $erori['varsta_minima'] = 'Alege o opțiune din listă.';
    } else {
        $curat['varsta_minima'] = (int) $varsta;
    }

    /* ------------------------- Participanții -------------------------- */
    foreach ([
        ['participanti_min', 'fara_participanti_min', 'Numărul minim'],
        ['participanti_max', 'fara_participanti_max', 'Numărul maxim'],
    ] as [$camp, $bifa, $eticheta]) {

        if (!empty($date[$bifa])) {
            $curat[$camp] = null;
            continue;
        }

        $valoare = trim($citeste($camp));

        if ($valoare === '') {
            $erori[$camp] = $eticheta . ' lipsește. Scrie-l, sau bifează „Nespecificat".';
        } elseif (!preg_match('/^[0-9]{1,5}$/', $valoare)) {
            $erori[$camp] = 'Scrie un număr întreg.';
        } elseif ((int) $valoare < 1) {
            $erori[$camp] = 'Numărul trebuie să fie cel puțin 1.';
        } elseif ((int) $valoare > PARTICIPANTI_MAX) {
            $erori[$camp] = 'Numărul e prea mare.';
        } else {
            $curat[$camp] = (int) $valoare;
        }
    }

    // Are sens doar dacă amândouă au fost completate cum trebuie.
    if (!isset($erori['participanti_min'], $erori['participanti_max'])
        && isset($curat['participanti_min'], $curat['participanti_max'])
        && $curat['participanti_min'] > $curat['participanti_max']) {
        $erori['participanti_max'] = 'Numărul maxim nu poate fi mai mic decât cel minim.';
    }

    /* ---------------------------- Descrierea -------------------------- */
    $descriere = curataTextPeRanduri($citeste('descriere'));

    /**
     * Se numără CARACTERE, nu octeți.
     *
     * În UTF-8, „ă" ocupă doi octeți. Cu strlen(), un text românesc de 280 de
     * caractere ar părea că are peste 300 și ar trece, iar unul scris fără
     * diacritice ar fi respins pe nedrept. mb_strlen numără ce vede omul.
     */
    $cateCaractere = mb_strlen($descriere, 'UTF-8');

    if ($descriere === '') {
        $erori['descriere'] = 'Scrie câteva rânduri despre eveniment.';
    } elseif ($cateCaractere < DESCRIERE_MIN) {
        $erori['descriere'] = 'Mai scrie puțin: ai ' . $cateCaractere . ' caractere din '
                            . DESCRIERE_MIN . ' cerute.';
    } elseif ($cateCaractere > DESCRIERE_MAX) {
        $erori['descriere'] = 'Descrierea e prea lungă (cel mult ' . DESCRIERE_MAX . ' de caractere).';
    } else {
        $curat['descriere'] = $descriere;
    }

    /* ------------------------ Sexul participanților ------------------- */
    $gen = trim($citeste('gen_participanti'));

    if ($gen === '') {
        $curat['gen_participanti'] = 'nespecificat';
    } elseif (!in_array($gen, ['barbati', 'femei', 'nespecificat'], true)) {
        $erori['gen_participanti'] = 'Alege o opțiune din listă.';
    } else {
        $curat['gen_participanti'] = $gen;
    }

    return ['erori' => $erori, 'curat' => $curat];
}

/**
 * Adresa publică a unui eveniment, făcută din titlu.
 *
 * Coada întâmplătoare e acolo fiindcă două evenimente pot avea același titlu
 * („Târg de Crăciun") în ani diferiți, iar adresa trebuie să rămână unică.
 */
function slugEveniment(string $titlu): string
{
    $slug = normalizeazaDiacritice($titlu);

    // Diacriticele devin literele de bază: „Cluj-Napoca în seară" → „...in seara".
    $slug = strtr(mb_strtolower($slug, 'UTF-8'), [
        'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't',
    ]);

    // Orice nu e literă sau cifră devine o singură cratimă.
    $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    // Tăiat la 140, ca împreună cu coada să încapă în coloana de 170.
    $slug = mb_substr($slug, 0, 140, 'UTF-8');

    if ($slug === '') {
        $slug = 'eveniment';
    }

    return $slug . '-' . bin2hex(random_bytes(3));
}

/**
 * O dată scrisă întreagă: „Duminică, 16 august 2026".
 *
 * Ziua săptămânii e acolo fiindcă asta caută omul întâi când se uită la un
 * eveniment: nu „16", ci „e sâmbătă sau e într-o zi de lucru?".
 */
function dataLunga(?string $data): string
{
    if ($data === null || $data === '') {
        return '';
    }

    try {
        $moment = new DateTimeImmutable($data);
    } catch (Exception $e) {
        return '';
    }

    $zile = ['duminică', 'luni', 'marți', 'miercuri', 'joi', 'vineri', 'sâmbătă'];
    $zi   = $zile[(int) $moment->format('w')];

    return mb_strtoupper(mb_substr($zi, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($zi, 1, null, 'UTF-8')
        . ', ' . $moment->format('j') . ' ' . numeleLunilor()[(int) $moment->format('n')]
        . ' ' . $moment->format('Y');
}

/** Ora fără secunde: „19:00:00" din bază devine „19:00". */
function oraScurta(?string $ora): string
{
    return ($ora !== null && preg_match('/^(\d{2}:\d{2})/', $ora, $m) === 1) ? $m[1] : '';
}

/* --------------------- Data, între pagină și bază ---------------------- */

/**
 * Data scrisă de om („25-12-2026") devine data cerută de bază („2026-12-25").
 *
 * Întoarce '' dacă nu e o dată adevărată — atunci cine a chemat funcția pune
 * eroarea, fiindcă tot el știe cum se cheamă câmpul.
 *
 * Formatul e strict: exact ZZ-LL-AAAA, cu zerourile puse. Nu primim și
 * AAAA-LL-ZZ „ca să fim îngăduitori": două formate acceptate înseamnă că
 * într-o zi cineva va trimite „01-02-2026" crezând una și noi vom înțelege
 * alta. Zerourile le pune oricum masca din main.js, iar cine trimite cererea
 * de-a dreptul are de citit o singură regulă.
 *
 * checkdate() e cel care are ultimul cuvânt: el știe că februarie are 29 de
 * zile doar în anii bisecți, iar un DateTime cu „31-04" ar fi alunecat singur
 * pe 1 mai, în loc să spună că data e greșită.
 */
function dataDinFormular(?string $cerut): string
{
    if (!is_string($cerut)) {
        return '';
    }

    $data = trim($cerut);

    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $data, $m) !== 1) {
        return '';
    }

    [, $zi, $luna, $an] = $m;

    if (!checkdate((int) $luna, (int) $zi, (int) $an)) {
        return '';
    }

    return $an . '-' . $luna . '-' . $zi;
}

/**
 * Drumul invers: data din bază, așa cum se scrie în câmpul din formular.
 *
 * Perechea lui dataDinFormular(). Stau una lângă alta ca să se vadă dintr-o
 * privire că sunt oglinzi — dacă una se schimbă, cealaltă sare în ochi.
 */
function dataPentruFormular(?string $data): string
{
    if (!is_string($data) || preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $data, $m) !== 1) {
        return '';
    }

    return $m[3] . '-' . $m[2] . '-' . $m[1];
}

/* ------------------------ Motivul unei anulări ------------------------- */

/**
 * Ce a scris organizatorul când a anulat evenimentul.
 *
 * Întoarce ['eroare' => string, 'text' => string]: eroarea e '' când e bine,
 * iar textul e cel curățat, gata de pus în bază.
 *
 * Motivul e OBLIGATORIU, și nu de formă. De un eveniment atârnă oameni care
 * și-au făcut planuri; ei vor primi textul ăsta prin e-mail (vezi TODO-ul din
 * anuleazaEveniment). „Anulat" singur nu e o veste, e o ușă închisă în nas.
 *
 * Se numără caractere, nu octeți — aceeași regulă ca la descriere, și același
 * curățător de text pe mai multe rânduri, ca numărul de aici să fie fix cel pe
 * care îl arată contorul din pagină.
 */
function verificaMotivAnulare($cerut): array
{
    $motiv = curataTextPeRanduri(is_string($cerut) ? $cerut : '');
    $cate  = mb_strlen($motiv, 'UTF-8');

    if ($motiv === '') {
        return ['eroare' => 'Scrie de ce anulezi. Cei care voiau să vină vor primi textul ăsta.', 'text' => ''];
    }

    if ($cate < MOTIV_ANULARE_MIN) {
        return [
            'eroare' => 'Mai scrie puțin: ai ' . $cate . ' caractere din '
                      . MOTIV_ANULARE_MIN . ' cerute.',
            'text'   => '',
        ];
    }

    if ($cate > MOTIV_ANULARE_MAX) {
        return [
            'eroare' => 'Motivul e prea lung (cel mult ' . MOTIV_ANULARE_MAX . ' de caractere).',
            'text'   => '',
        ];
    }

    return ['eroare' => '', 'text' => $motiv];
}

/**
 * Costul, scris pe românește.
 *
 * NULL înseamnă „gratuit", iar 0 înseamnă „am scris eu zero" — la afișare sunt
 * același lucru, dar în bază rămân diferite (vezi README). Zecimalele apar doar
 * când există: „25 lei", nu „25,00 lei".
 */
function costScris($cost): string
{
    if ($cost === null || $cost === '') {
        return 'Gratuit';
    }

    $bani = (float) $cost;

    if ($bani <= 0) {
        return 'Gratuit';
    }

    $scris = (fmod($bani, 1.0) === 0.0)
        ? number_format($bani, 0, ',', '.')
        : number_format($bani, 2, ',', '.');

    return $scris . ' lei';
}

/**
 * O cale de pe site-ul nostru, luată dintr-un parametru de adresă.
 *
 * Întoarce calea curățată, sau '' dacă nu e de încredere. Se folosește oriunde
 * ajunge un „?redirect=" — la intrarea obișnuită, la cea cu Google, și la orice
 * pagină care trimite omul să se conecteze și îl aduce înapoi.
 *
 * Verificarea era până acum scrisă de trei ori, în trei fișiere, și lăsa să
 * treacă exact lucrul de care se ferea. „/\alt-site.ro" începe cu o bară, deci
 * trecea — iar browserele îndreaptă bara inversă și ajung la „//alt-site.ro",
 * adică la o adresă de pe alt domeniu. La fel, un tab sau un rând nou în mijloc
 * e scos de browser înainte să se uite la adresă, deci „/\ttp://..." nu e ce
 * pare. De aceea aici nu mai întrebăm doar cu ce începe:
 *
 *   - trebuie să înceapă cu o singură bară obișnuită;
 *   - nicio bară inversă nicăieri;
 *   - niciun caracter de control;
 *   - nu mai lungă decât are rost.
 *
 * Restul — ce pagină, cu ce parametri — nu ne privește: e o cale de pe site.
 */
function caleInterna(?string $cerut, int $lungimeMax = 300): string
{
    if (!is_string($cerut)) {
        return '';
    }

    /**
     * Caracterele de control se caută în ce a venit, ÎNAINTE de orice tăiere.
     *
     * trim() înghite din capete și „\0", și „\n" — deci un „/pagina.php\0" ar
     * ieși curat din el și ar trece verificarea de mai jos ca și cum octetul
     * acela n-ar fi fost trimis niciodată. Or, dacă cineva l-a trimis, vrem să
     * ne oprim, nu să-i curățăm noi adresa.
     */
    if (preg_match('/[\x00-\x1F\x7F]/', $cerut) === 1) {
        return '';
    }

    $cale = trim($cerut, ' ');

    if ($cale === '' || strlen($cale) > $lungimeMax) {
        return '';
    }

    if ($cale[0] !== '/' || ($cale[1] ?? '') === '/') {
        return '';
    }

    if (str_contains($cale, '\\')) {
        return '';
    }

    return $cale;
}

/**
 * Mesajul din formularul de contact.
 *
 * Nu-și scrie propriile reguli: numele trec prin esteNumeValid(), adresa prin
 * aceeași verificare ca la înregistrare, telefonul prin verificaTelefon().
 * Dacă vreuna dintre ele se schimbă vreodată, se schimbă și aici, singură.
 *
 * $dinCont: pentru un membru conectat, numele, adresa și telefonul vin din
 * baza de date, nu din formular. Se verifică doar mesajul — restul nu-l scrie
 * omul, deci n-are ce fi greșit, iar dacă l-ar scrie ar putea semna cu numele
 * altcuiva.
 *
 * Întoarce ['erori' => [...], 'curat' => [...]].
 */
function verificaContact(array $date, array $dinCont = []): array
{
    $erori = [];
    $curat = [];

    $citeste = static function (string $cheie) use ($date): string {
        $valoare = $date[$cheie] ?? '';
        return is_string($valoare) ? $valoare : '';
    };

    $eLogat = $dinCont !== [];

    /* --------------------------- Nume și prenume ---------------------- */

    if ($eLogat) {
        $curat['nume']    = (string) $dinCont['nume'];
        $curat['prenume'] = (string) $dinCont['prenume'];
    } else {
        /**
         * Formularul are un singur câmp, „Nume și prenume", dar în bază stau
         * două coloane — ca peste tot în proiect.
         *
         * Despărțim la primul spațiu, în ordinea folosită la înregistrare:
         * numele de familie întâi, prenumele după („Popescu Ionuț").
         */
        $intreg = pregatesteText($citeste('nume'));
        $bucati = explode(' ', $intreg, 2);

        $nume    = $bucati[0] ?? '';
        $prenume = trim($bucati[1] ?? '');

        if ($intreg === '') {
            $erori['nume'] = 'Scrie-ți numele și prenumele.';
        } elseif ($prenume === '') {
            $erori['nume'] = 'Scrie și numele, și prenumele.';
        } elseif (mb_strlen($intreg, 'UTF-8') > NUME_MAX * 2) {
            $erori['nume'] = 'Numele e prea lung.';
        } elseif (!esteNumeValid($nume) || !esteNumeValid($prenume)) {
            $erori['nume'] = 'Numele poate conține doar litere, spații și cratime.';
        } else {
            $curat['nume']    = numeCuMajuscula($nume);
            $curat['prenume'] = numeCuMajuscula($prenume);
        }
    }

    /* ------------------------------ E-mail ---------------------------- */

    if ($eLogat) {
        $curat['email'] = (string) $dinCont['email'];
    } else {
        $email = curataSpatii($citeste('email'));

        if ($email === '') {
            $erori['email'] = 'Scrie adresa de e-mail.';
        } elseif (mb_strlen($email, 'UTF-8') > EMAIL_MAX) {
            $erori['email'] = 'Adresa de e-mail e prea lungă.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erori['email'] = 'Adresa de e-mail nu pare validă.';
        } else {
            $curat['email'] = mb_strtolower($email, 'UTF-8');
        }
    }

    /* ------------------------------ Telefon --------------------------- */

    /**
     * Membrul care are deja telefon în cont nu-l mai scrie.
     *
     * Cine nu are, îl scrie acum — și îi e cerut, ca oricărui vizitator: la
     * un mesaj de contact vrem să putem suna înapoi.
     */
    if ($eLogat && (string) ($dinCont['telefon'] ?? '') !== '') {
        $curat['telefon'] = (string) $dinCont['telefon'];
    } else {
        $telefon = $citeste('telefon');

        if (trim($telefon) === '') {
            $erori['telefon'] = 'Scrie un număr de telefon.';
        } elseif (strlen($telefon) > 40) {
            $erori['telefon'] = 'Numărul e prea lung.';
        } else {
            // Aceeași regulă ca la setări: o singură formă în bază.
            $rezultat = verificaTelefon($telefon);

            if (!$rezultat['ok']) {
                $erori['telefon'] = $rezultat['eroare'];
            } else {
                $curat['telefon'] = $rezultat['curat'];
            }
        }
    }

    /* ------------------------------- Mesajul -------------------------- */

    $mesaj = trim($citeste('mesaj'));

    if ($mesaj === '') {
        $erori['mesaj'] = 'Scrie-ne câteva rânduri despre ce e vorba.';
    } elseif (mb_strlen($mesaj, 'UTF-8') < MESAJ_MIN) {
        $erori['mesaj'] = 'Mesajul e prea scurt — mai spune-ne câte ceva.';
    } elseif (mb_strlen($mesaj, 'UTF-8') > MESAJ_MAX) {
        $erori['mesaj'] = 'Mesajul e prea lung (cel mult ' . MESAJ_MAX . ' de caractere).';
    } else {
        // Rândurile goale de la capete pleacă, dar cele dinăuntru rămân: omul
        // și-a împărțit mesajul pe paragrafe dintr-un motiv.
        $curat['mesaj'] = $mesaj;
    }

    return ['erori' => $erori, 'curat' => $curat];
}

/**
 * Numărul de telefon, în forma folosită în România.
 *
 * Câmpul e opțional, deci gol înseamnă bun — și se salvează NULL.
 *
 * Același număr poate fi scris în multe feluri: „0722 33 44 55",
 * „+40 722 334 455", „0040-722-334-455". Toate sunt același telefon, deci le
 * aducem la o singură formă înainte de a le pune în bază. Altfel două rânduri
 * ar arăta diferit fără să fie, iar o căutare de mai târziu ar da greș.
 *
 * Rămân zece cifre, cum le scrie omul pe hârtie: 0722334455. Prefixele 07
 * (mobil), 02 și 03 (fix) sunt singurele din planul de numerotare românesc.
 *
 * Întoarce ['ok' => bool, 'curat' => string, 'eroare' => string].
 */
function verificaTelefon(string $telefon): array
{
    $telefon = trim($telefon);

    if ($telefon === '') {
        return ['ok' => true, 'curat' => '', 'eroare' => ''];
    }

    // Ce scrie omul între cifre — spații, puncte, liniuțe, paranteze — nu
    // face parte din număr.
    $cifre = preg_replace('/[\s.\-()\/]+/u', '', $telefon) ?? '';

    // Orice altceva rămas în afară de cifre și un plus la început e semn că
    // n-a fost un număr de telefon.
    if (!preg_match('/^\+?[0-9]+$/', $cifre)) {
        return ['ok' => false, 'curat' => '', 'eroare' => 'Scrie doar cifre, de forma 0722334455.'];
    }

    // Cele trei feluri de a scrie prefixul de țară duc la aceeași formă.
    if (str_starts_with($cifre, '+40')) {
        $cifre = '0' . substr($cifre, 3);
    } elseif (str_starts_with($cifre, '0040')) {
        $cifre = '0' . substr($cifre, 4);
    } elseif (str_starts_with($cifre, '40') && strlen($cifre) === 11) {
        $cifre = '0' . substr($cifre, 2);
    }

    if (!preg_match('/^0[237][0-9]{8}$/', $cifre)) {
        return [
            'ok'     => false,
            'curat'  => '',
            'eroare' => 'Numărul nu pare românesc. Zece cifre, începând cu 07, 02 sau 03.',
        ];
    }

    return ['ok' => true, 'curat' => $cifre, 'eroare' => ''];
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

    return numeleLunilor()[(int) $moment->format('n')] . ' ' . $moment->format('Y');
}

/** Lunile pe românește, numerotate de la 1 ca în `date('n')`. */
function numeleLunilor(): array
{
    return [
        1 => 'ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie',
        'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie',
    ];
}

/**
 * O dată scrisă scurt: „3 aug 2026".
 *
 * Prescurtarea e chiar primele trei litere ale lunii — în română toate ies
 * cum trebuie (ian, feb, …, mai, iun, iul, …), deci nu e nevoie de încă o
 * listă care s-ar putea despărți de prima.
 *
 * Întoarce '' dacă data lipsește sau nu se înțelege: mai bine nimic decât o
 * dată greșită.
 */
function dataScurta(?string $data): string
{
    if ($data === null || $data === '') {
        return '';
    }

    try {
        $moment = new DateTimeImmutable($data);
    } catch (Exception $e) {
        return '';
    }

    $luna = numeleLunilor()[(int) $moment->format('n')];

    return $moment->format('j') . ' ' . mb_substr($luna, 0, 3, 'UTF-8') . ' ' . $moment->format('Y');
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
