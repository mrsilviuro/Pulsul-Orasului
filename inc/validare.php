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
const VARSTA_MIN    = 10;
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
/**
 * Cât de lung trebuie să fie „Detalii".
 *
 * A fost 300. S-a plâns lumea, și pe drept: la o partidă de fotbal în parc sau
 * la o cafea sâmbătă dimineață, trei sute de caractere înseamnă că trebuie să
 * INVENTEZI ceva ca să treci de contor. Un prag care cere umplutură nu aduce
 * anunțuri mai bune, aduce anunțuri mai lungi — și îl trimite acasă tocmai pe
 * omul care avea de spus puțin și limpede.
 *
 * Două sute e cât o vorbă întreagă: unde, ce se face, ce să-ți iei cu tine.
 */
const DESCRIERE_MIN       = 200;   // caractere, nu octeți — vezi verificaEveniment()
const DESCRIERE_MAX       = 8000;
const COST_MAX            = 99999.99;
const PARTICIPANTI_MAX    = 65535; // cât încape în SMALLINT UNSIGNED
const ANI_INAINTE_MAX     = 2;     // cât de departe în viitor poate fi pus un eveniment

/**
 * Cât de curând poate începe un eveniment nou-publicat
 *
 * Data singură se uită doar la ZI: la ora 15:00 se putea publica ceva „azi, de
 * la 14:00", adică deja început. Verificarea din verificaEveniment() pune data
 * și ora cap la cap și cere cel puțin atâtea ceasuri de-acum încolo.
 *
 * Două, fiindcă un anunț trece întâi pe la moderare și pe urmă trebuie să
 * apuce să-l și vadă cineva. Sub atât, ieșirea e a organizatorului și a
 * nimănui altcuiva.
 */
const ORE_MINIM_INAINTE   = 2;
const MOTIV_ANULARE_MIN   = 15;    // caractere, ca la descriere — vezi verificaMotivAnulare()
const MOTIV_ANULARE_MAX   = 1000;

/* Comentarii */
const COMENTARIU_MIN = 2;    // caractere — vezi verificaComentariu()
const COMENTARIU_MAX = 2000;

/**
 * Tabla cu dorințe
 *
 * 100 de caractere, cât scrie și în coloana din bază (vezi sql/023). Scurt
 * dinadins: pe tablă stau zece dorințe una după alta, iar una cât un paragraf
 * le-ar fi înghițit pe celelalte. Cine are de scris mai mult are unde —
 * anunțul unui eveniment primește opt mii.
 *
 * Minimul e mic fiindcă o dorință CHIAR poate fi scurtă: „un turneu de șah"
 * are 17 caractere și spune tot.
 */
const DORINTA_MIN = 10;
const DORINTA_MAX = 100;

/**
 * Motivul respingerii unui anunț, scris de staff
 *
 * Fără minim, spre deosebire de motivul anulării: uneori anunțul e limpede
 * greșit și n-ai ce scrie, iar a-l sili pe omul de casă să compună o frază ar
 * naște fraze de umplut („nu se poate"). Când lipsește, e-mailul spune asta pe
 * față și îl trimite pe organizator la noi.
 */
const MOTIV_RESPINGERE_MAX = 1000;

/* Scoaterea cuiva de pe lista de participanți */
const MOTIV_EXCLUDERE_MIN = 15;   // caractere — vezi verificaMotivExcludere()
const MOTIV_EXCLUDERE_MAX = 1000;

/* Evaluările dintre participanți */
const EVALUARE_TEXT_MIN = 10;     // caractere — vezi verificaTextEvaluare()
const EVALUARE_TEXT_MAX = 1500;

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
 * O adresă de e-mail: curățată, verificată, adusă la litere mici.
 *
 * Întoarce ['eroare' => '', 'email' => 'ion@email.ro'] sau eroarea, cu adresa
 * goală. Un singur loc pentru regula asta — o aveau scrisă la fel formularul
 * de înregistrare și cel de contact, iar înscrierea la vești ar fi fost a
 * treia copie. Trei copii înseamnă că într-o zi o adresă trece pe undeva și e
 * refuzată în altă parte.
 *
 * Literele mici la salvare sunt jumătatea care contează: fără ele,
 * „Ion@Email.ro" și „ion@email.ro" ajung două rânduri diferite, iar cheia
 * unică din bază nu mai apără nimic.
 */
function verificaEmail($cerut): array
{
    $email = curataSpatii(is_string($cerut) ? $cerut : '');

    if ($email === '') {
        return ['eroare' => 'Avem nevoie și de adresa ta de e-mail.', 'email' => ''];
    }

    if (mb_strlen($email, 'UTF-8') > EMAIL_MAX) {
        return ['eroare' => 'Adresa asta e neobișnuit de lungă. Mai aruncă-i un ochi.', 'email' => ''];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['eroare' => 'Adresa nu pare completă. Mai aruncă un ochi pe ea.', 'email' => ''];
    }

    return ['eroare' => '', 'email' => mb_strtolower($email, 'UTF-8')];
}

/**
 * Verifică o dată de naștere scrisă ca ZZ-LL-AAAA, cum se scrie în România.
 *
 * Întoarce mesajul de eroare, sau șir gol dacă data e bună. Cine o primește
 * bună o trece prin dataDinFormular() ca s-o ducă în bază — acolo datele stau
 * tot în forma lor, AAAA-LL-ZZ.
 *
 * Formatul e același cu cel al datei de eveniment, și tot prin dataDinFormular()
 * trece: un singur fel de a scrie o dată pe tot site-ul. Înainte aici se cerea
 * AAAA-LL-ZZ, fiindcă atât trimitea `<input type="date">`; de când câmpul e de
 * text (vezi inc/camp-data.php), scrisul omului ajunge nemijlocit la server.
 */
function verificaDataNasterii(string $data, ?DateTimeImmutable $azi = null): string
{
    $azi  = $azi ?? new DateTimeImmutable('today');
    $data = trim($data);

    if ($data === '') {
        return 'Spune-ne și când te-ai născut.';
    }

    // dataDinFormular() ține și forma, și adevărul datei: „30-02-2000" nu trece,
    // fiindcă în spate stă checkdate(), nu un DateTime care ar fi alunecat
    // singur pe 2 martie.
    $iso = dataDinFormular($data);

    if ($iso === '') {
        return 'Data asta nu ne iese. Scrie-o ca 25-12-1990.';
    }

    $d = new DateTimeImmutable($iso);

    if ($d > $azi) {
        return 'Data e în viitor — probabil s-a strecurat o greșeală.';
    }

    $ani = (int) $d->diff($azi)->y;

    if ($ani < VARSTA_MIN) {
        return 'Ne pare rău, contul se poate face de la ' . VARSTA_MIN . ' ani în sus.';
    }
    if ($ani > VARSTA_MAX) {
        return 'Anul acesta nu prea se potrivește. Mai verifică-l.';
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
        $erori['nume'] = 'Ne trebuie și numele tău de familie.';
    } elseif (mb_strlen($nume, 'UTF-8') < NUME_MIN) {
        $erori['nume'] = 'Numele pare cam scurt. Mai adaugă câteva litere.';
    } elseif (mb_strlen($nume, 'UTF-8') > NUME_MAX) {
        $erori['nume'] = 'Numele e cam lung — încape în cel mult ' . NUME_MAX . ' de caractere).';
    } elseif (!esteNumeValid($nume)) {
        $erori['nume'] = 'La nume merg doar litere, spații și cratime.';
    } else {
        $curat['nume'] = numeCuMajuscula($nume);
    }

    /* ----------------------------- Prenume ---------------------------- */
    $prenume = pregatesteText($citeste('prenume'));

    if ($prenume === '') {
        $erori['prenume'] = 'Spune-ne și prenumele tău.';
    } elseif (mb_strlen($prenume, 'UTF-8') < NUME_MIN) {
        $erori['prenume'] = 'Prenumele pare cam scurt. Mai adaugă câteva litere.';
    } elseif (mb_strlen($prenume, 'UTF-8') > NUME_MAX) {
        $erori['prenume'] = 'Prenumele e cam lung — încape în cel mult ' . NUME_MAX . ' de caractere).';
    } elseif (!esteNumeValid($prenume)) {
        $erori['prenume'] = 'La prenume merg doar litere, spații și cratime.';
    } else {
        $curat['prenume'] = numeCuMajuscula($prenume);
    }

    /* ------------------------------ E-mail ---------------------------- */
    $email = verificaEmail($citeste('email'));

    if ($email['eroare'] !== '') {
        $erori['email'] = $email['eroare'];
    } else {
        $curat['email'] = $email['email'];
    }

    /* -------------------------- Data nașterii ------------------------- */
    $dataNasterii = trim($citeste('data_nasterii'));
    $eroareData = verificaDataNasterii($dataNasterii, $azi);

    if ($eroareData !== '') {
        $erori['data_nasterii'] = $eroareData;
    } else {
        // Din formular vine ZZ-LL-AAAA, în bază intră AAAA-LL-ZZ — aceeași
        // trecere ca la data evenimentului.
        $curat['data_nasterii'] = dataDinFormular($dataNasterii);
    }

    /* -------------------------------- Sex ----------------------------- */
    // Doar două valori sunt acceptate. Orice altceva — inclusiv o valoare
    // trimisă direct, fără formular — e respinsă.
    $sex = strtoupper(trim($citeste('sex')));
    $echivalente = ['M' => 'M', 'MASCULIN' => 'M', 'F' => 'F', 'FEMININ' => 'F'];

    if ($sex === '') {
        $erori['sex'] = 'Alege o opțiune.';
    } elseif (!isset($echivalente[$sex])) {
        $erori['sex'] = 'Varianta asta nu e dintre cele de mai sus.';
    } else {
        $curat['sex'] = $echivalente[$sex];
    }

    /* ------------------------------ Parola ---------------------------- */
    $parola  = $citeste('parola');
    $parola2 = $citeste('parola_confirmare');
    $octeti  = strlen($parola);           // octeți, nu caractere: bcrypt taie la 72

    if ($parola === '') {
        $erori['parola'] = 'Alege-ți o parolă.';
    } elseif (mb_strlen($parola, 'UTF-8') < PAROLA_MIN) {
        $erori['parola'] = 'Parola are nevoie de cel puțin ' . PAROLA_MIN . ' caractere.';
    } elseif ($octeti > PAROLA_MAX) {
        $erori['parola'] = 'Parola e cam lungă — alege una de cel mult ' . PAROLA_MAX . ' de caractere.';
    } elseif (preg_match('/^\s+$/u', $parola)) {
        $erori['parola'] = 'O parolă numai din spații n-o să te apere prea mult.';
    } else {
        $curat['parola'] = $parola;
    }

    // Câmpul gol se semnalează întotdeauna, ca browserul și serverul să
    // marcheze exact aceleași câmpuri. Nepotrivirea are sens doar dacă
    // parola în sine a trecut de verificări.
    if ($parola2 === '') {
        $erori['parola_confirmare'] = 'Mai scrie o dată parola, ca să fim siguri.';
    } elseif (!isset($erori['parola']) && !hash_equals($parola, $parola2)) {
        $erori['parola_confirmare'] = 'Cele două parole nu se potrivesc. Mai încearcă o dată.';
    }

    /* ------------------------------ Termeni --------------------------- */
    $termeni = $date['termeni'] ?? '';
    $acceptat = in_array($termeni, [true, 1, '1', 'on', 'true', 'da'], true);

    if (!$acceptat) {
        $erori['termeni'] = 'Ca să mergem mai departe, avem nevoie să accepți termenii.';
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
 * Cele cinci semne de pe un abțibild „FindMe", curățate.
 *
 * Întoarce codul cu majuscule dacă e bun, sau '' dacă nu e. Un singur loc care
 * hotărăște ce e un cod: îl cheamă și formularul de publicare, și findme.php cu
 * ce a venit din adresă, și pagina de coduri.
 *
 * Alfabetul e al parolelor temporare — cifre și litere mari, fără perechile
 * care se confundă (O/0, I/L/1). Codul se scanează, deci de obicei nu-l scrie
 * nimeni; dar când telefonul nu vrea să citească, omul se uită la abțibild și
 * tastează, iar atunci diferența contează.
 *
 * Literele mici se ridică, nu se resping: cine tastează „k3m7p" a citit bine
 * abțibildul, doar că telefonul lui scrie cu litere mici.
 */
const COD_QR_LUNGIME  = 5;
const COD_QR_ALFABET  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

function curataCodQr(?string $cod): string
{
    $cod = mb_strtoupper(trim((string) $cod), 'UTF-8');

    if ($cod === '' || mb_strlen($cod, 'UTF-8') !== COD_QR_LUNGIME) {
        return '';
    }

    // strspn numără câte semne de la început sunt din alfabet. Dacă le prinde
    // pe toate, n-a rămas niciunul străin. Merge pe octeți, dar aici e în
    // regulă: alfabetul e numai ASCII, iar lungimea s-a numărat deja cu
    // mb_strlen — un „ă" strecurat înăuntru ar fi picat la lungime.
    return strspn($cod, COD_QR_ALFABET) === COD_QR_LUNGIME ? $cod : '';
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
 * $categoriiJocQr — id-urile categoriilor care sunt un joc cu abțibilde
 * („FindMe", vezi sql/025). Dacă omul a ales una dintre ele, formularul TREBUIE
 * să vină și cu un cod QR: fără el n-ar avea ce încheia vânătoarea. Lista vine
 * ca argument din același motiv ca celelalte două — aici nu se deschide baza.
 *
 * Ce NU se verifică aici: dacă acel cod există în bază și dacă e liber. Alea
 * cer o interogare, deci le face api/eveniment.php prin inc/coduri-qr.php.
 * Aici se hotărăște doar dacă a fost scris ceva și dacă acel ceva ARATĂ a cod.
 *
 * Întoarce ['erori' => [...], 'curat' => [...]].
 */
function verificaEveniment(array $date, array $categoriiValide, array $oraseValide = [],
                           ?DateTimeImmutable $azi = null,
                           ?string $inceputulDeAcum = null,
                           array $categoriiJocQr = []): array
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
        $erori['titlu'] = 'Titlul e cam scurt. Spune în câteva cuvinte despre ce e vorba.';
    } elseif (mb_strlen($titlu, 'UTF-8') > TITLU_EVENIMENT_MAX) {
        $erori['titlu'] = 'Titlul e cam lung — încape în cel mult ' . TITLU_EVENIMENT_MAX . ' de caractere).';
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
        $erori['categorie_id'] = 'Alege categoria care se potrivește cel mai bine.';
    } else {
        $curat['categorie_id'] = $categorie;
    }

    /* ---------------------------- Codul QR ---------------------------- */
    /**
     * Numai la categoriile de joc, și acolo obligatoriu.
     *
     * Se întreabă de categoria CITITĂ, nu de cea curată: dacă id-ul a picat mai
     * sus, nu mai are rost o a doua eroare despre un cod cerut de o categorie
     * care nu există.
     *
     * La celelalte categorii câmpul nici nu se citește. Un cod trimis din
     * greșeală odată cu un anunț de la „Sport" nu e o eroare — e un câmp rămas
     * completat în formular când omul s-a răzgândit; se lasă pe dinafară, tăcut,
     * fiindcă în `curat` nu intră decât ce chiar se salvează.
     */
    /**
     * E o vânătoare? De răspunsul ăsta atârnă TREI lucruri mai jos, nu doar
     * codul: ora de sfârșit și costul nu se mai cer deloc, fiindcă la o
     * vânătoare nu există nici una, nici alta.
     *
     * Se hotărăște o dată, aici, după CATEGORIE — nu după ce lipsește din
     * cerere. Altfel oricine ar fi scăpat de reguli trimițând un formular
     * ciuntit, iar noi am fi numit asta „a ales să nu completeze".
     */
    $eVanatoare = isset($curat['categorie_id'])
               && in_array($categorie, $categoriiJocQr, true);

    if ($eVanatoare) {
        $scris  = trim($citeste('cod_qr'));
        $codQr  = curataCodQr($scris);

        if ($scris === '') {
            $erori['cod_qr'] = 'Scrie codul de pe abțibild.';
        } elseif ($codQr === '') {
            $erori['cod_qr'] = 'Codul are ' . COD_QR_LUNGIME
                             . ' semne — cifre și litere mari, cum scrie pe abțibild.';
        } else {
            $curat['cod_qr'] = $codQr;
        }
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
        $erori['data_eveniment'] = 'Data asta nu ne iese. Scrie-o ca 25-12-2026.';
    } else {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data);

        if ($d === false) {
            $erori['data_eveniment'] = 'Data nu e validă.';
        } elseif ($d < $azi) {
            $erori['data_eveniment'] = 'Ziua asta a trecut deja. Alege una de azi înainte.';
        } elseif ($d > $azi->modify('+' . ANI_INAINTE_MAX . ' years')) {
            $erori['data_eveniment'] = 'Data e cam departe în viitor.';
        } else {
            $curat['data_eveniment'] = $data;
        }
    }

    /* ------------------------------ Orele ----------------------------- */
    $inceput = trim($citeste('ora_inceput'));

    if ($inceput === '') {
        $erori['ora_inceput'] = 'Spune-ne și de la ce oră începe.';
    } elseif (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $inceput)) {
        $erori['ora_inceput'] = 'Ora asta nu ne iese. Scrie-o ca 19:00.';
    } else {
        $curat['ora_inceput'] = $inceput . ':00';
    }

    /**
     * „Nedeterminat" e o alegere, nu o omisiune.
     *
     * De aceea are bifa lui: altfel n-am putea deosebi „nu se știe cât ține"
     * de „am uitat să completez".
     *
     * LA O VÂNĂTOARE „FINDME", ÎNSĂ, NU E NICI UNA, NICI ALTA: acolo pur și
     * simplu nu există „până la". Ora de mai sus E capătul — clipa în care
     * căutarea se închide — iar formularul nici nu mai arată câmpul (vezi
     * `data-fara-joc` din adauga_eveniment.php). Fără rândul ăsta, un anunț de
     * vânătoare ar fi fost oprit cu „scrie ora de sfârșit, sau bifează…",
     * arătând spre o bifă care nu mai e pe ecran.
     *
     * $eVanatoare se hotărăște mai sus, după CATEGORIE, nu după ce lipsește
     * din cerere.
     */
    $faraSfarsit = $eVanatoare || !empty($date['fara_ora_sfarsit']);

    if ($faraSfarsit) {
        $curat['ora_sfarsit'] = null;
    } else {
        $sfarsit = trim($citeste('ora_sfarsit'));

        if ($sfarsit === '') {
            // Bifa se cheamă în pagină „Nu se știe până când ține". Mesajul o
            // numește la fel: altfel omul caută în formular ceva ce nu există.
            $erori['ora_sfarsit'] = 'Spune până la ce oră ține, sau bifează „Nu se știe până când ține".';
        } elseif (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $sfarsit)) {
            $erori['ora_sfarsit'] = 'Ora asta nu ne iese. Scrie-o ca 19:00.';
        } else {
            // Nu comparăm cele două ore: un eveniment poate începe la 22:00 și
            // se poate termina la 02:00. Ar fi o „greșeală" care nu e greșeală.
            $curat['ora_sfarsit'] = $sfarsit . ':00';
        }
    }

    /* ------------------- Cât de curând poate începe -------------------- */
    /**
     * Cel puțin două ceasuri de-acum încolo (ORE_MINIM_INAINTE).
     *
     * Data singură nu era de ajuns: ea se uită doar la ZI, deci la ora 15:00
     * se putea publica liniștit ceva „azi, de la 14:00" — un eveniment care
     * începuse deja în clipa în care apărea pe site. Verificarea de aici pune
     * data și ora cap la cap și se uită la CLIPA de început, nu la zi.
     *
     * Cele două ceasuri nu sunt un număr rotund ales la întâmplare: un anunț
     * trece întâi pe la moderare, iar pe urmă trebuie să apuce să-l vadă
     * cineva. Sub atât, ieșirea e a organizatorului și a nimănui altcuiva.
     *
     * LA EDITARE, regula se aplică doar dacă se SCHIMBĂ clipa de început.
     * Altfel, cine voia să îndrepte o virgulă cu o oră înainte de start ar fi
     * fost trimis să-și mute evenimentul cu două ore mai încolo — ceea ce n-a
     * cerut nimeni. `$inceputulDeAcum` e ce scrie în bază acum („2026-08-20
     * 19:00:00"); pentru un eveniment nou e null.
     *
     * Se pune ultima, după ce data și ora au trecut fiecare de ale ei: fără
     * amândouă curate n-avem ce lipi cap la cap, iar omul are deja de citit un
     * mesaj despre câmpul pe care l-a greșit.
     */
    if (isset($curat['data_eveniment'], $curat['ora_inceput'])) {
        $inceputNou = $curat['data_eveniment'] . ' ' . $curat['ora_inceput'];
        $seSchimba  = $inceputulDeAcum === null || $inceputNou !== $inceputulDeAcum;

        if ($seSchimba) {
            $clipa = strtotime($inceputNou);
            $prag  = time() + ORE_MINIM_INAINTE * 3600;

            if ($clipa !== false && $clipa < $prag) {
                $erori['ora_inceput'] =
                    'Mai lasă-le oamenilor puțin timp: alege o oră cu cel puțin ' . ORE_MINIM_INAINTE
                  . ' ore înainte, deci cel mai devreme ' . date('H:i', $prag)
                  . ', ' . dataLunga(date('Y-m-d', $prag), false) . '.';
            }
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
        $erori['oras'] = 'Alege orașul din listă.';
    } else {
        $curat['oras'] = $oras;
    }

    /* ----------------------------- Locația ---------------------------- */
    $locatie = curataTextLiber($citeste('locatie'));

    if ($locatie === '') {
        $erori['locatie'] = 'Spune-ne și unde are loc.';
    } elseif (mb_strlen($locatie, 'UTF-8') < LOCATIE_MIN) {
        $erori['locatie'] = 'Locul e scris cam pe scurt. Mai dă câteva detalii.';
    } elseif (mb_strlen($locatie, 'UTF-8') > LOCATIE_MAX) {
        $erori['locatie'] = 'Locul e scris cam pe lung — încape în cel mult ' . LOCATIE_MAX . ' de caractere).';
    } else {
        $curat['locatie'] = $locatie;
    }

    /* ------------------------------ Costul ---------------------------- */
    /**
     * O vânătoare e gratuită prin firea ei: nu se vinde niciun bilet, iar tot
     * chenarul „Cine poate veni și cât costă?" nici nu mai e pe ecran. Fără
     * rândul ăsta, anunțul ar fi fost oprit cu „scrie cât costă, sau bifează
     * «Gratuit»" — arătând spre o bifă pe care omul n-o mai vede.
     */
    if ($eVanatoare || !empty($date['gratuit'])) {
        $curat['cost'] = null;
    } else {
        // Oamenii scriu „25,50" la fel de des ca „25.50".
        $cost = str_replace(',', '.', trim($citeste('cost')));

        if ($cost === '') {
            $erori['cost'] = 'Spune-ne cât costă, sau bifează „Gratuit".';
        } elseif (!preg_match('/^[0-9]{1,5}(\.[0-9]{1,2})?$/', $cost)) {
            $erori['cost'] = 'Scrie suma ca 25 sau ca 25.50.';
        } elseif ((float) $cost > COST_MAX) {
            $erori['cost'] = 'Suma asta pare prea mare.';
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
    /**
     * LA O VÂNĂTOARE NU SE ÎNSCRIE NIMENI, deci nu există nici „de la câți",
     * nici „până la câți" — caseta de participare nici nu se desenează pe
     * pagina anunțului (vezi randeazaCasetaFindMe). Ca și la cost și la ora de
     * sfârșit, întrebarea nu se pune: $eVanatoare le trece pe amândouă drept
     * nespecificate, fără să ceară vreo bifă pe care omul n-o mai vede.
     */
    foreach ([
        ['participanti_min', 'fara_participanti_min', 'Numărul minim'],
        ['participanti_max', 'fara_participanti_max', 'Numărul maxim'],
    ] as [$camp, $bifa, $eticheta]) {

        if ($eVanatoare || !empty($date[$bifa])) {
            $curat[$camp] = null;
            continue;
        }

        $valoare = trim($citeste($camp));

        if ($valoare === '') {
            $erori[$camp] = $eticheta . ' lipsește. Scrie-l, sau bifează „Nespecificat".';
        } elseif (!preg_match('/^[0-9]{1,5}$/', $valoare)) {
            $erori[$camp] = 'Aici merge doar un număr întreg.';
        } elseif ((int) $valoare < 1) {
            $erori[$camp] = 'Numărul trebuie să fie măcar 1.';
        } elseif ((int) $valoare > PARTICIPANTI_MAX) {
            $erori[$camp] = 'Numărul ăsta pare prea mare.';
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
        $erori['descriere'] = 'Povestește-ne în câteva rânduri despre ce e vorba.';
    } elseif ($cateCaractere < DESCRIERE_MIN) {
        $erori['descriere'] = 'Mai scrie puțin: ai ' . $cateCaractere . ' caractere din '
                            . DESCRIERE_MIN . ' cerute.';
    } elseif ($cateCaractere > DESCRIERE_MAX) {
        $erori['descriere'] = 'Descrierea e cam lungă — încape în cel mult ' . DESCRIERE_MAX . ' de caractere).';
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
 *
 * Cu `$cuAnul = false` iese „Duminică, 16 august". Pentru lucrurile care se
 * petrec în zilele astea — o dorință care iese de pe tablă săptămâna viitoare
 * — anul nu spune nimic: e limpede că e cel de-acum, iar scris ar fi doar
 * patru cifre în plus de citit.
 */
function dataLunga(?string $data, bool $cuAnul = true): string
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
        . ($cuAnul ? ' ' . $moment->format('Y') : '');
}

/**
 * Aceeași dată, dar cu literă mică: „joi, 27 august".
 *
 * dataLunga() o scrie cu majusculă, fiindcă de obicei stă singură la începutul
 * unui rând („Joi, 27 august, ora 19:00"). Când intră în MIJLOCUL unei fraze —
 * „e pe tablă până joi", „mai poți da note până joi" — majuscula aceea ar fi
 * fost una în mijlocul propoziției.
 *
 * Fără an, ca `dataLunga($d, false)`: se folosește pentru lucruri care se
 * petrec în zilele astea, iar acolo anul nu spune nimic.
 *
 * Stă aici, lângă sora ei, fiindcă o cer două locuri depărtate: tabla cu
 * dorințe (inc/dorinte.php) și termenul notelor de pe pagina unui eveniment.
 * Scrisă în amândouă, s-ar fi despărțit la prima corectură.
 */
function dataScrisaMic(?string $data): string
{
    $scris = dataLunga($data, false);

    if ($scris === '') {
        return '';
    }

    return mb_strtolower(mb_substr($scris, 0, 1, 'UTF-8'), 'UTF-8')
         . mb_substr($scris, 1, null, 'UTF-8');
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
        return ['eroare' => 'Spune-ne de ce anulezi. Le trimitem textul ăsta celor care voiau să vină.', 'text' => ''];
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
 * De ce a fost respins un anunț — scris de staff, pentru organizator.
 *
 * OPȚIONAL, spre deosebire de motivul anulării. Acolo textul pleacă spre zeci
 * de oameni care își făcuseră un plan și au dreptul la o explicație; aici merge
 * spre unul singur, iar uneori anunțul e limpede greșit și n-ai ce scrie. A-l
 * sili pe omul de casă să compună o frază ar naște fraze de umplut.
 *
 * Textul gol nu e o eroare: e-mailul spune atunci pe față că nu s-a specificat
 * niciun motiv și îl trimite pe organizator să ne scrie.
 */
function verificaMotivRespingere($cerut): array
{
    $motiv = curataTextPeRanduri(is_string($cerut) ? $cerut : '');

    if (mb_strlen($motiv, 'UTF-8') > MOTIV_RESPINGERE_MAX) {
        return [
            'eroare' => 'Motivul e prea lung (cel mult ' . MOTIV_RESPINGERE_MAX . ' de caractere).',
            'text'   => '',
        ];
    }

    return ['eroare' => '', 'text' => $motiv];
}

/**
 * De ce e scos cineva de pe lista de participanți.
 *
 * Cincisprezece caractere, ca la motivul de anulare, și din același motiv:
 * textul ăsta nu rămâne între noi, ci pleacă întreg în e-mailul primit de omul
 * dat jos. „nu" sau „ok" nu i-ar spune nimic, iar el are dreptul să știe.
 *
 * Funcție a ei, nu verificaMotivAnulare(): sunt două fapte diferite, cu două
 * mesaje diferite pe ecran, iar cine schimbă limita uneia n-are de ce s-o
 * schimbe pe a celeilalte.
 */
function verificaMotivExcludere($cerut): array
{
    $motiv = curataTextPeRanduri(is_string($cerut) ? $cerut : '');
    $cate  = mb_strlen($motiv, 'UTF-8');

    if ($motiv === '') {
        return [
            'eroare' => 'Spune-ne de ce îl scoți de pe listă. Îi trimitem textul ăsta pe e-mail.',
            'text'   => '',
        ];
    }

    if ($cate < MOTIV_EXCLUDERE_MIN) {
        return [
            'eroare' => 'Mai scrie puțin: ai ' . $cate . ' caractere din '
                      . MOTIV_EXCLUDERE_MIN . ' cerute.',
            'text'   => '',
        ];
    }

    if ($cate > MOTIV_EXCLUDERE_MAX) {
        return [
            'eroare' => 'Motivul e prea lung (cel mult ' . MOTIV_EXCLUDERE_MAX . ' de caractere).',
            'text'   => '',
        ];
    }

    return ['eroare' => '', 'text' => $motiv];
}

/**
 * Vorbele de sub o notă, dacă omul a scris vreuna.
 *
 * Textul e OPȚIONAL: nota se poate da și dintr-o apăsare pe stele, de pe pagina
 * evenimentului. De aceea gol înseamnă „doar stele", nu greșeală — funcția
 * întoarce text gol fără eroare, iar cine o cheamă știe să nu-l scrie.
 *
 * Dacă totuși scrie ceva, îi cerem zece caractere: „ok" pus sub o notă de o
 * stea nu ajută pe nimeni, nici pe cel care o primește, nici pe cine îi citește
 * profilul peste o lună.
 */
function verificaTextEvaluare($cerut): array
{
    $text = curataTextPeRanduri(is_string($cerut) ? $cerut : '');

    if ($text === '') {
        return ['eroare' => '', 'text' => ''];
    }

    $cate = mb_strlen($text, 'UTF-8');

    if ($cate < EVALUARE_TEXT_MIN) {
        return [
            'eroare' => 'Ori scrii ceva de înțeles (cel puțin ' . EVALUARE_TEXT_MIN
                      . ' caractere), ori lași caseta goală și dai doar stele.',
            'text'   => '',
        ];
    }

    if ($cate > EVALUARE_TEXT_MAX) {
        return [
            'eroare' => 'E prea lung (cel mult ' . EVALUARE_TEXT_MAX . ' de caractere).',
            'text'   => '',
        ];
    }

    return ['eroare' => '', 'text' => $text];
}

/**
 * Un număr de stele venit de la browser: 1…5, sau 0 dacă nu e bun.
 *
 * Zero nu e o notă — înseamnă „n-a ales nimic" — deci cine cheamă funcția
 * oprește cererea când primește zero. Se verifică aici, într-un loc, fiindcă
 * întrebarea vine din două pagini: de pe evenimentul încheiat și de pe profil.
 */
function stelePrimite($cerut): int
{
    if (!is_numeric($cerut)) {
        return 0;
    }

    $stele = (int) $cerut;

    return ($stele >= 1 && $stele <= 5) ? $stele : 0;
}

/**
 * Un comentariu de sub un eveniment.
 *
 * Se numără caractere, nu octeți, și se trece prin același curățător de text
 * pe mai multe rânduri ca descrierea: omul poate scrie două paragrafe, dar nu
 * își poate împinge vorba mai jos în pagină cu cincizeci de Enter-uri.
 *
 * Minimul e mic dinadins. „Da." e un comentariu întreg într-o discuție, iar o
 * limită de zece caractere l-ar fi oprit tocmai pe cel care răspunde scurt și
 * la obiect. Ce se apără aici e doar comentariul gol, trimis din greșeală.
 */
function verificaComentariu($cerut): array
{
    $text = curataTextPeRanduri(is_string($cerut) ? $cerut : '');
    $cate = mb_strlen($text, 'UTF-8');

    if ($text === '' || $cate < COMENTARIU_MIN) {
        return ['eroare' => 'Scrie ceva mai întâi.', 'text' => ''];
    }

    if ($cate > COMENTARIU_MAX) {
        return [
            'eroare' => 'Comentariul e prea lung: ai ' . $cate . ' caractere din '
                      . COMENTARIU_MAX . ' câte încap.',
            'text'   => '',
        ];
    }

    return ['eroare' => '', 'text' => $text];
}

/**
 * O dorință de pe tablă: orașul și rândul scris de om.
 *
 * `$oraseValide` vine din inc/config.php, prin oraseDisponibile() — nu se
 * cheamă de aici, ca fișierul ăsta să rămână ce e: verificări curate, fără
 * nimic din afară. Aceeași înțelegere ca la verificaEveniment().
 *
 * Întoarce erorile pe câmpuri, ca formularul să le poată pune fiecare sub
 * căsuța ei — la fel ca verificaEveniment(), nu ca verificaComentariu(), care
 * n-are decât un câmp.
 */
function verificaDorinta(array $date, array $oraseValide): array
{
    $erori = [];
    $curat = [];

    $oras = trim(is_string($date['oras'] ?? null) ? $date['oras'] : '');

    if ($oras === '') {
        $erori['oras'] = 'Alege orașul.';
    } elseif (!in_array($oras, $oraseValide, true)) {
        $erori['oras'] = 'Alege orașul din listă.';
    } else {
        $curat['oras'] = $oras;
    }

    /**
     * Un singur rând, nu paragrafe: pe tablă dorința stă într-o frază, iar
     * cine ar fi apăsat Enter de zece ori ar fi făcut un cartonaș cât ecranul.
     *
     * Enterul se preface în SPAȚIU înainte de pregatesteText(), nu după: acela
     * scoate caracterele de control, iar „\n" e unul dintre ele. Lăsat pe mâna
     * lui, „un turneu\nde șah" ieșea „un turneude șah" — două cuvinte lipite,
     * fără ca omul să înțeleagă de ce.
     */
    $brut = is_string($date['dorinta'] ?? null) ? $date['dorinta'] : '';
    $brut = preg_replace('/[\r\n\t\x0B\f]+/u', ' ', $brut) ?? '';

    $text = pregatesteText($brut);
    $cate = mb_strlen($text, 'UTF-8');

    if ($text === '') {
        $erori['dorinta'] = 'Spune-ne ce ți-ai dori.';
    } elseif ($cate < DORINTA_MIN) {
        $erori['dorinta'] = 'Mai scrie puțin: ai ' . $cate . ' caractere din '
                          . DORINTA_MIN . ' cerute.';
    } elseif ($cate > DORINTA_MAX) {
        // Se numără cu mb_strlen, nu cu strlen: în UTF-8 „ă" ocupă doi octeți,
        // iar o limită pe octeți i-ar fi dat mai puțin loc celui cu diacritice.
        $erori['dorinta'] = 'E prea lungă: ai ' . $cate . ' caractere din '
                          . DORINTA_MAX . ' câte încap.';
    } else {
        $curat['dorinta'] = $text;
    }

    return ['erori' => $erori, 'curat' => $curat];
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
            $erori['nume'] = 'La nume merg doar litere, spații și cratime.';
        } else {
            $curat['nume']    = numeCuMajuscula($nume);
            $curat['prenume'] = numeCuMajuscula($prenume);
        }
    }

    /* ------------------------------ E-mail ---------------------------- */

    if ($eLogat) {
        $curat['email'] = (string) $dinCont['email'];
    } else {
        $email = verificaEmail($citeste('email'));

        if ($email['eroare'] !== '') {
            $erori['email'] = $email['eroare'];
        } else {
            $curat['email'] = $email['email'];
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
        return ['ok' => false, 'curat' => '', 'eroare' => 'Scrie doar cifre, ca 0722334455.'];
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
            'eroare' => 'Numărul nu pare românesc — zece cifre, care încep cu 07, 02 sau 03.',
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
 * Ce scrie în locul numelui, când omul din spatele rândului nu mai e.
 *
 * Contul șters se ANONIMIZEAZĂ, nu se șterge (inc/stergere.php): rândul rămâne,
 * fiindcă de el atârnă evenimentele organizate și participările altora, dar
 * omul din el se golește — `nume = 'Șters'`, `prenume = 'Utilizator'`.
 *
 * Numai că prin numeAfisat() cele două ieșeau „Ș. Utilizator": o inițială
 * urmată de un prenume, adică o prescurtare care arată exact ca un nume de om
 * adevărat, doar că unul pe care nu-l cheamă nimeni așa. De aceea vorba se
 * scrie de-a dreptul, într-un singur loc.
 */
const NUME_CONT_STERS = 'Utilizator șters';

/**
 * S-a dus omul din rândul ăsta?
 *
 * ÎNTREABĂ DOAR DE `stare`, nu și de `cerere_stergere`. Cele treizeci de zile
 * de răgaz sunt dinadins un răstimp în care nu se schimbă NIMIC: contul e
 * întreg, iar simpla intrare în el anulează ștergerea (vezi autentifica()).
 * Un anunț care și-ar pierde organizatorul în ziua în care omul a apăsat
 * butonul i-ar lăsa pe cei înscriși fără să știe cu cine se întâlnesc — la un
 * eveniment care poate are loc mâine, și cu un om care poate se răzgândește
 * poimâine.
 *
 * Aceeași întrebare o pune și omulCuLegatura() din inc/admin.php, tot pe
 * `stare`: acolo un cont șters își pierde legătura către profil.
 */
function esteContSters(?string $stare): bool
{
    return $stare === 'sters';
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
 * „acum 6 ore", „ieri", „3 aug 2026" — cât de demult s-a scris ceva.
 *
 * Sub o săptămână se spune în cuvinte, fiindcă atât ține mintea: „acum 20 de
 * minute" se înțelege dintr-o privire, „13 aug 2026, 14:32" cere socoteală.
 * Peste o săptămână se întoarce la data scurtă — „acum 43 de zile" nu mai
 * spune nimănui nimic, iar în pagină apare oricum ora exactă, în `datetime`.
 *
 * Ceasul e PHP (`time()`), niciodată NOW() din MySQL — regula 5 din CLAUDE.md.
 * Aici s-ar simți imediat: o oră diferență între cele două ceasuri ar face ca
 * fiecare comentariu proaspăt să se nască „acum o oră".
 */
function timpRelativ(?string $moment): string
{
    if ($moment === null || $moment === '') {
        return '';
    }

    $clipa = strtotime($moment);

    if ($clipa === false) {
        return '';
    }

    $secunde = time() - $clipa;

    // Viitor. Nu se întâmplă firesc, dar un ceas dat înapoi pe server sau un
    // rând scris de mână în phpMyAdmin ar da „acum -3 minute".
    if ($secunde < 0) {
        return 'chiar acum';
    }

    if ($secunde < 60) {
        return 'acum câteva secunde';
    }

    $minute = (int) floor($secunde / 60);

    if ($minute < 60) {
        return $minute === 1 ? 'acum un minut' : 'acum ' . numaratoare($minute, 'minute');
    }

    $ore = (int) floor($minute / 60);

    if ($ore < 24) {
        return $ore === 1 ? 'acum o oră' : 'acum ' . numaratoare($ore, 'ore');
    }

    /**
     * „Ieri" se socotește pe zile de calendar, nu pe 24 de ore.
     *
     * Ceva scris aseară la 23:00 e „ieri" și azi-dimineață la 8, deși între
     * ele sunt nouă ore. Invers, ceva scris alaltăieri la 23:00 nu mai e
     * „ieri" azi la 22:00, deși între ele n-au trecut nici două zile.
     */
    $azi  = new DateTimeImmutable('today');
    $ziua = (new DateTimeImmutable('@' . $clipa))
        ->setTimezone($azi->getTimezone())
        ->setTime(0, 0);

    $zile = (int) $azi->diff($ziua)->days;

    if ($zile === 1) {
        return 'ieri';
    }

    if ($zile < 7) {
        return 'acum ' . numaratoare($zile, 'zile');
    }

    return dataScurta($moment);
}

/**
 * „3 zile", dar „21 de zile".
 *
 * În română, de la 20 în sus numărul cere „de" înaintea substantivului — cu
 * excepția celor care se termină în 01…19 („101 zile", nu „101 de zile").
 * Regula scrisă o dată aici, ca să nu fie ghicită de fiecare dată.
 */
function numaratoare(int $cate, string $substantiv): string
{
    $ultimele = $cate % 100;
    $direct   = $ultimele >= 1 && $ultimele <= 19;

    return $cate . ($direct ? ' ' : ' de ') . $substantiv;
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
