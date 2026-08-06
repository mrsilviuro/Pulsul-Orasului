<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — pozele de profil.
 *
 * Tot ce ține de fișierul trimis de utilizator stă aici: verificările,
 * decuparea, micșorarea și ștergerea. Paginile și api/ doar cheamă funcțiile.
 *
 * ---------------------------------------------------------------------------
 *  PRINCIPIUL DE BAZĂ
 * ---------------------------------------------------------------------------
 *
 *  Fișierul primit nu este niciodată salvat așa cum a venit. Este citit,
 *  desenat din nou pixel cu pixel și scris ca JPEG nou, făcut de noi.
 *
 *  De aici vin, dintr-o singură mișcare, aproape toate protecțiile:
 *
 *  - Un fișier care se dă drept poză, dar are cod PHP lipit la coadă (sau
 *    ascuns într-un comentariu din interiorul JPEG-ului), își pierde acel
 *    cod: noi copiem doar pixelii, nu și restul fișierului.
 *  - Datele EXIF dispar. Contează mai mult decât pare: fotografiile făcute
 *    cu telefonul conțin de obicei coordonatele GPS ale locului unde au fost
 *    făcute. Ar fi fost publicate odată cu poza, fără ca nimeni să știe.
 *  - Extensia și numele alese de utilizator nu ajung niciodată pe disc.
 *    Numele îl dăm noi, aleatoriu, iar extensia e mereu .jpg.
 *
 *  Restul verificărilor (mărime, dimensiuni, tip) opresc fișierele care nici
 *  măcar nu apucă să fie desenate.
 */

require_once __DIR__ . '/bootstrap.php';

/* ============================== SETĂRI ================================= */

/** Latura pozei mari, în pixeli. Se afișează la ~90 px, deci 512 acoperă
 *  liniștit și ecranele cu densitate dublă. */
const POZA_LATURA = 512;

/** Latura pozei mici, folosită la comentarii și în bara de meniu. */
const POZA_LATURA_MICA = 128;

/** Calitatea JPEG. Sub 80 se văd pătrățele pe fundaluri netede; peste 88
 *  fișierul crește mult fără ca ochiul să câștige ceva. */
const POZA_CALITATE = 82;

/** Cât poate cântări fișierul trimis. */
const POZA_OCTETI_MAX = 6 * 1024 * 1024;

/** Cel mult atâția pixeli în total (lățime × înălțime).
 *
 *  Nu mărimea fișierului e problema, ci numărul de pixeli: o imagine PNG de
 *  câteva sute de kilobiți poate avea 30000×30000 de puncte și ar cere peste
 *  3 GB de memorie ca să fie desenată. Limita de 40 de megapixeli lasă loc
 *  oricărui aparat foto obișnuit. */
const POZA_PIXELI_MAX = 40 * 1000 * 1000;

/** Nicio latură peste atât. A doua plasă, pentru imagini lungi și înguste. */
const POZA_LATURA_MAX = 12000;

/** Sub atât nu are rost: ar ieși o poză neclară. */
const POZA_SURSA_MIN = 200;

/** Pauza minimă între două schimbări de poză, în secunde. */
const POZA_SECUNDE_PAUZA = 15;

/** Dosarul în care stau pozele, relativ la rădăcina site-ului. */
const POZA_DOSAR = 'assets/img/membri';

/** Silueta arătată celor care nu și-au pus poză. */
const POZA_IMPLICITA = 'assets/img/avatars/implicit.svg';

/* ============================ CĂI ȘI ADRESE ============================= */

/** Dosarul cu poze, ca și cale pe disc. */
function caleDosarPoze(): string
{
    return dirname(__DIR__) . '/' . POZA_DOSAR;
}

/**
 * Adresa pozei unui membru, pentru atributul src.
 *
 * $poza este ce scrie în coloana „poza" — sau null, dacă omul nu și-a pus
 * niciuna. Numele e verificat și aici, nu doar la scriere: dacă printr-o
 * scăpare viitoare ar ajunge în bază altceva decât 32 de caractere
 * hexazecimale, tot nu s-ar putea construi din el o cale către alt dosar.
 */
function urlPoza(?string $poza, bool $mica = false): string
{
    if (!estePozaValida($poza)) {
        return POZA_IMPLICITA;
    }

    return POZA_DOSAR . '/' . $poza . ($mica ? '-mic' : '') . '.jpg';
}

/** Numele din bază arată așa cum l-am scris noi? */
function estePozaValida(?string $poza): bool
{
    return is_string($poza) && preg_match('/^[0-9a-f]{32}$/', $poza) === 1;
}

/** Șterge de pe disc ambele fișiere ale unei poze. */
function stergePozaDeFisier(?string $poza): void
{
    if (!estePozaValida($poza)) {
        return;
    }

    foreach (['', '-mic'] as $sufix) {
        $cale = caleDosarPoze() . '/' . $poza . $sufix . '.jpg';
        if (is_file($cale)) {
            @unlink($cale);
        }
    }
}

/* ========================= VERIFICAREA FIȘIERULUI ====================== */

/**
 * Traduce codul de eroare al PHP-ului într-un mesaj de înțeles.
 *
 * Întoarce '' dacă fișierul a ajuns cu bine.
 */
function eroareIncarcare(int $cod): string
{
    switch ($cod) {
        case UPLOAD_ERR_OK:
            return '';
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Fișierul e prea mare. Alege o poză de cel mult '
                 . (int) (POZA_OCTETI_MAX / 1024 / 1024) . ' MB.';
        case UPLOAD_ERR_PARTIAL:
            return 'Fișierul a ajuns doar pe jumătate. Încearcă din nou.';
        case UPLOAD_ERR_NO_FILE:
            return 'Nu ai ales nicio poză.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
        default:
            // Astea sunt probleme ale serverului, nu ale utilizatorului. Nu
            // spunem care anume: nu l-ar ajuta cu nimic, iar pe cineva
            // rău-intenționat l-ar lămuri cum e configurat serverul.
            return 'Nu am putut primi fișierul. Încearcă din nou peste puțin.';
    }
}

/**
 * Ce tipuri de fișiere acceptăm, și cu ce funcție se citește fiecare.
 *
 * De ce nu doar .jpg, cum ar fi fost cel mai simplu: extensia nu contează
 * deloc, pentru că oricum nu o folosim — noi salvăm mereu JPEG făcut de noi.
 * Singura întrebare care contează e „putem citi corect pixelii?". Iar dacă
 * răspunsul e da, a-l trimite pe om înapoi să-și convertească poza dintr-un
 * PNG (cum sunt toate capturile de ecran de pe Android și Windows) ar fi o
 * piedică fără niciun câștig.
 *
 * GIF-ul lipsește intenționat: sunt imagini animate, cu paletă de 256 de
 * culori, nepotrivite ca poză de profil.
 */
function tipuriPozeAcceptate(): array
{
    $tipuri = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG  => 'imagecreatefrompng',
    ];

    // WebP e citit doar dacă GD-ul de pe server știe. În XAMPP știe.
    if (function_exists('imagecreatefromwebp') && defined('IMAGETYPE_WEBP')) {
        $tipuri[IMAGETYPE_WEBP] = 'imagecreatefromwebp';
    }

    return $tipuri;
}

/**
 * Încape în memorie o imagine de dimensiunile date?
 *
 * GD ține imaginea desfăcută, cu patru octeți de pixel. Peste asta mai punem
 * un sfert, pentru copia pe care o facem la decupare. Dacă nu încape, e mai
 * bine să spunem asta decât să lăsăm PHP-ul să se oprească la jumătatea
 * treabei, cu o pagină albă.
 */
function incapeInMemorie(int $latime, int $inaltime): bool
{
    $limita = ini_get('memory_limit');

    // -1 înseamnă „fără limită".
    if ($limita === false || (int) $limita === -1) {
        return true;
    }

    $unitate = strtolower(substr(trim((string) $limita), -1));
    $octeti  = (int) $limita;

    if ($unitate === 'g')      $octeti *= 1024 * 1024 * 1024;
    elseif ($unitate === 'm')  $octeti *= 1024 * 1024;
    elseif ($unitate === 'k')  $octeti *= 1024;

    $nevoie = $latime * $inaltime * 4 * 1.25;

    return ($octeti - memory_get_usage(true)) > $nevoie;
}

/* ========================== PRELUCRAREA POZEI ========================== */

/**
 * Ia fișierul primit și scoate din el două poze pătrate.
 *
 * $fisier  — un element din $_FILES
 * $decupaj — ['x' => int, 'y' => int, 'l' => int] în pixerii pozei originale,
 *            așa cum l-a ales utilizatorul din pagină. Poate lipsi.
 *
 * Întoarce ['ok' => true, 'nume' => '9f3c…'] sau ['ok' => false, 'mesaj' => …].
 */
function procesezaPozaProfil(array $fisier, ?array $decupaj = null): array
{
    /* ------------------------ 1. A ajuns întreg? ---------------------- */

    $eroare = eroareIncarcare((int) ($fisier['error'] ?? UPLOAD_ERR_NO_FILE));
    if ($eroare !== '') {
        return ['ok' => false, 'mesaj' => $eroare];
    }

    $temporar = (string) ($fisier['tmp_name'] ?? '');

    /**
     * Fișierul trebuie să fie chiar unul primit prin formular.
     *
     * Fără verificarea asta, cine ar reuși să ne trimită în tmp_name calea
     * unui fișier de pe server (de pildă inc/config.php) ne-ar pune să-l
     * citim noi în locul lui.
     */
    if ($temporar === '' || !is_uploaded_file($temporar)) {
        return ['ok' => false, 'mesaj' => 'Fișierul nu a ajuns cum trebuie. Încearcă din nou.'];
    }

    /* --------------------------- 2. Mărimea --------------------------- */

    $octeti = (int) @filesize($temporar);

    if ($octeti <= 0) {
        return ['ok' => false, 'mesaj' => 'Fișierul pare gol.'];
    }

    if ($octeti > POZA_OCTETI_MAX) {
        return [
            'ok'    => false,
            'mesaj' => 'Poza are ' . round($octeti / 1024 / 1024, 1) . ' MB, iar limita e de '
                     . (int) (POZA_OCTETI_MAX / 1024 / 1024) . ' MB.',
        ];
    }

    /* ------------------- 3. Chiar e o imagine? ------------------------ */

    // getimagesize nu se uită la extensie, ci la primii octeți din fișier.
    // Dacă nu recunoaște nimic, întoarce false.
    $info = @getimagesize($temporar);

    if ($info === false || empty($info[0]) || empty($info[1])) {
        return ['ok' => false, 'mesaj' => 'Fișierul nu e o imagine pe care să o putem citi.'];
    }

    $latime   = (int) $info[0];
    $inaltime = (int) $info[1];
    $tip      = (int) ($info[2] ?? 0);

    $acceptate = tipuriPozeAcceptate();

    if (!isset($acceptate[$tip])) {
        return ['ok' => false, 'mesaj' => 'Acceptăm poze JPG, PNG sau WEBP.'];
    }

    // A doua părere, de la altă bibliotecă. Nu strică să fie de acord amândouă.
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? (string) finfo_file($finfo, $temporar) : '';
        if ($finfo) finfo_close($finfo);

        if ($mime !== '' && !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return ['ok' => false, 'mesaj' => 'Acceptăm poze JPG, PNG sau WEBP.'];
        }
    }

    /* --------------------- 4. Dimensiuni rezonabile ------------------- */

    if ($latime > POZA_LATURA_MAX || $inaltime > POZA_LATURA_MAX
        || $latime * $inaltime > POZA_PIXELI_MAX) {
        return ['ok' => false, 'mesaj' => 'Poza are prea mulți pixeli. Micșoreaz-o puțin și încearcă din nou.'];
    }

    if (min($latime, $inaltime) < POZA_SURSA_MIN) {
        return [
            'ok'    => false,
            'mesaj' => 'Poza e prea mică. Avem nevoie de cel puțin '
                     . POZA_SURSA_MIN . '×' . POZA_SURSA_MIN . ' pixeli.',
        ];
    }

    if (!incapeInMemorie($latime, $inaltime)) {
        return ['ok' => false, 'mesaj' => 'Poza e prea mare pentru server. Micșoreaz-o și încearcă din nou.'];
    }

    /* ------------------------ 5. Desenarea ---------------------------- */

    $citeste = $acceptate[$tip];
    $sursa   = @$citeste($temporar);

    if (!$sursa instanceof GdImage) {
        return ['ok' => false, 'mesaj' => 'Nu am putut citi poza. Încearcă alt fișier.'];
    }

    try {
        // Fotografiile de pe telefon sunt aproape mereu salvate „culcat", cu o
        // notă în EXIF care spune cum trebuie rotite la afișare. Dacă nu
        // ținem cont de ea, poza iese întoarsă pe o parte.
        if ($tip === IMAGETYPE_JPEG) {
            $sursa = aplicaOrientarea($sursa, $temporar);
            $latime   = imagesx($sursa);
            $inaltime = imagesy($sursa);
        }

        $taietura = potrivesteDecupajul($decupaj, $latime, $inaltime);

        $nume = bin2hex(random_bytes(16));
        $caleDosar = caleDosarPoze();

        if (!is_dir($caleDosar) && !@mkdir($caleDosar, 0755, true) && !is_dir($caleDosar)) {
            return ['ok' => false, 'mesaj' => 'Nu am putut salva poza. Încearcă din nou peste puțin.'];
        }

        // Nu mărim niciodată peste ce ne-a dat omul: dintr-un decupaj de
        // 300 px iese o poză de 300 px, nu una de 512 întinsă și moale.
        $laturaMare = min(POZA_LATURA, $taietura['l']);
        $laturaMica = min(POZA_LATURA_MICA, $taietura['l']);

        $scrise = [];

        foreach ([['', $laturaMare], ['-mic', $laturaMica]] as [$sufix, $latura]) {
            $cale = $caleDosar . '/' . $nume . $sufix . '.jpg';

            if (!scriePatrat($sursa, $taietura, $latura, $cale)) {
                foreach ($scrise as $facut) @unlink($facut);
                return ['ok' => false, 'mesaj' => 'Nu am putut salva poza. Încearcă din nou peste puțin.'];
            }

            $scrise[] = $cale;
        }

        return ['ok' => true, 'nume' => $nume];
    } finally {
        // Memoria se eliberează orice s-ar întâmpla mai sus.
        if ($sursa instanceof GdImage) {
            imagedestroy($sursa);
        }
    }
}

/**
 * Rotește imaginea după nota din EXIF, dacă există una.
 */
function aplicaOrientarea(GdImage $imagine, string $cale): GdImage
{
    if (!function_exists('exif_read_data')) {
        return $imagine;
    }

    // Fișierul poate să nu aibă EXIF deloc, caz în care funcția dă un
    // avertisment. Nu e o problemă, deci îl trecem cu vederea.
    $exif = @exif_read_data($cale);

    if (!is_array($exif) || empty($exif['Orientation'])) {
        return $imagine;
    }

    $grade   = 0;
    $oglinda = false;

    switch ((int) $exif['Orientation']) {
        case 2: $oglinda = true;                break;
        case 3: $grade   = 180;                 break;
        case 4: $grade   = 180; $oglinda = true; break;
        case 5: $grade   = 270; $oglinda = true; break;
        case 6: $grade   = 270;                 break;
        case 7: $grade   =  90; $oglinda = true; break;
        case 8: $grade   =  90;                 break;
        default: return $imagine;
    }

    if ($grade !== 0) {
        $rotit = @imagerotate($imagine, $grade, 0);
        if ($rotit instanceof GdImage) {
            imagedestroy($imagine);
            $imagine = $rotit;
        }
    }

    if ($oglinda) {
        @imageflip($imagine, IMG_FLIP_HORIZONTAL);
    }

    return $imagine;
}

/**
 * Verifică decupajul cerut din pagină și îl aduce la ceva sigur.
 *
 * Valorile vin de la utilizator, deci pot fi orice: negative, uriașe, lipsă.
 * Nu ne supărăm pe ele — le potrivim. Un decupaj greșit nu e un atac, e de
 * obicei o fereastră redimensionată între timp.
 *
 * Dacă nu primim nimic folositor, decupăm din mijloc: e alegerea care merge
 * bine pentru aproape orice portret și e și ce se întâmplă când cineva
 * trimite formularul fără JavaScript.
 */
function potrivesteDecupajul(?array $decupaj, int $latime, int $inaltime): array
{
    $laturaMax = min($latime, $inaltime);

    $laturaCentru = [
        'x' => (int) floor(($latime   - $laturaMax) / 2),
        'y' => (int) floor(($inaltime - $laturaMax) / 2),
        'l' => $laturaMax,
    ];

    if ($decupaj === null) {
        return $laturaCentru;
    }

    $l = (int) round((float) ($decupaj['l'] ?? 0));
    $x = (int) round((float) ($decupaj['x'] ?? 0));
    $y = (int) round((float) ($decupaj['y'] ?? 0));

    // Prea mic ca să însemne ceva, sau lipsă de tot.
    if ($l < POZA_SURSA_MIN) {
        return $laturaCentru;
    }

    // Nu poate depăși imaginea, oricât ar cere.
    $l = min($l, $laturaMax);
    $x = max(0, min($x, $latime   - $l));
    $y = max(0, min($y, $inaltime - $l));

    return ['x' => $x, 'y' => $y, 'l' => $l];
}

/**
 * Scrie pe disc un pătrat din imagine, micșorat la latura cerută.
 */
function scriePatrat(GdImage $sursa, array $taietura, int $latura, string $cale): bool
{
    $tinta = imagecreatetruecolor($latura, $latura);

    if (!$tinta instanceof GdImage) {
        return false;
    }

    try {
        // JPEG-ul nu știe de transparență. Un PNG cu fundal transparent ar
        // ieși cu fundalul negru, așa că îl umplem întâi cu alb.
        $alb = imagecolorallocate($tinta, 255, 255, 255);
        imagefilledrectangle($tinta, 0, 0, $latura, $latura, $alb);

        $bun = imagecopyresampled(
            $tinta, $sursa,
            0, 0,
            $taietura['x'], $taietura['y'],
            $latura, $latura,
            $taietura['l'], $taietura['l']
        );

        if (!$bun) {
            return false;
        }

        // JPEG progresiv: se vede întâi neclar, apoi se limpezește, în loc să
        // apară rând cu rând de sus în jos.
        imageinterlace($tinta, true);

        return imagejpeg($tinta, $cale, POZA_CALITATE);
    } finally {
        imagedestroy($tinta);
    }
}
