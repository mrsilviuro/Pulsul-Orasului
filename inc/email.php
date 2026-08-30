<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — trimiterea e-mailurilor.
 *
 * Un singur șablon pentru toate mesajele, exact ca la CSS: dacă se schimbă
 * culoarea sau subsolul, se schimbă peste tot dintr-un loc.
 *
 * ---------------------------------------------------------------------------
 *  DE CE E-MAILUL SE SCRIE ALTFEL DECÂT O PAGINĂ
 * ---------------------------------------------------------------------------
 *
 *  Programele de e-mail sunt cu douăzeci de ani în urma browserelor, iar
 *  fiecare taie altceva din HTML. De aici regulile de mai jos, care în orice
 *  altă parte a proiectului ar fi greșeli:
 *
 *  - Așezarea se face cu <table>, nu cu flexbox sau grid. Outlook pe Windows
 *    randează prin motorul lui Word, care nu știe nici măcar `float`.
 *  - Stilurile se scriu în atributul `style` al fiecărui element. Gmail taie
 *    din <style> tot ce nu-i place, iar unele programe îl aruncă întreg.
 *  - Lățimea maximă e 600px, cât încape în panoul de previzualizare.
 *  - Fonturile sunt cele de sistem. Un font descărcat nu se încarcă nicăieri.
 *
 *  Fără nicio imagine, la cererea ta — și e alegerea bună oricum: aproape toți
 *  furnizorii blochează imaginile până când omul apasă „afișează imaginile",
 *  iar un mesaj construit din imagini ajunge atunci un dreptunghi gol. Aici
 *  tot ce se vede e text și chenare colorate, deci arată la fel de la început.
 */

require_once __DIR__ . '/bootstrap.php';

/* ============================ TRIMITEREA =============================== */

/**
 * Trimite un mesaj gata compus.
 *
 * $blocuri descrie conținutul; vezi sablonEmail() pentru ce se poate pune.
 * Întoarce true dacă mesajul a plecat (sau a fost scris în fișier, în modul
 * de dezvoltare).
 */
function trimiteEmail(string $catre, string $subiect, array $blocuri,
                      array $anteturiInPlus = []): bool
{
    global $config;

    /**
     * Adresa e verificată din nou aici, chiar dacă a fost verificată și la
     * intrarea în formular.
     *
     * Motivul e o clasă de atac numită „injecție în anteturi": anteturile unui
     * e-mail se despart prin rânduri noi, deci o adresă care conține un rând
     * nou poate adăuga anteturi inventate — de pildă un „Bcc:" către altcineva.
     * Așa un site devine, fără să știe, unealtă de trimis spam.
     */
    if (!esteAdresaSigura($catre)) {
        error_log('PulsulOrasului: adresă de e-mail respinsă la trimitere.');
        return false;
    }

    $expeditor    = (string) ($config['email_expeditor'] ?? 'noreply@pulsulorasului.ro');
    $numeExpedior = (string) ($config['email_nume'] ?? 'PulsulOrasului.Ro');
    $raspunsCatre = (string) ($config['email_raspuns'] ?? $expeditor);

    if (!esteAdresaSigura($expeditor) || !esteAdresaSigura($raspunsCatre)) {
        error_log('PulsulOrasului: adresa de expeditor din config.php nu e validă.');
        return false;
    }

    $corp = sablonEmail($subiect, $blocuri);

    /* ------------------------- Anteturile ----------------------------- */

    $granita = '=_po_' . bin2hex(random_bytes(16));
    $gazda   = parse_url((string) ($config['url_site'] ?? ''), PHP_URL_HOST) ?: 'pulsulorasului.ro';

    $anteturi = [
        'From'         => mimeNume($numeExpedior) . ' <' . $expeditor . '>',
        'Reply-To'     => $raspunsCatre,
        'Message-ID'   => '<' . bin2hex(random_bytes(16)) . '@' . $gazda . '>',
        'Date'         => date('r'),
        'MIME-Version' => '1.0',
        'Content-Type' => 'multipart/alternative; boundary="' . $granita . '"',

        // Spune programelor de e-mail că mesajul e trimis de un program, nu de
        // un om. Fără el, un răspuns automat de tip „sunt în concediu" ar
        // ajunge înapoi la noi, iar în cazuri nefericite se poate ajunge la o
        // buclă de mesaje între cele două servere.
        'Auto-Submitted' => 'auto-generated',
        'X-Auto-Response-Suppress' => 'All',
    ];

    /**
     * Anteturile în plus, cerute de cine trimite. Azi doar „List-Unsubscribe",
     * de la newsletterul zilnic: programele de e-mail îl citesc și pun ELE un
     * buton „Dezabonează-te" lângă numele expeditorului. Butonul acela e cel
     * mai apăsat dintre toate — și e mult mai bun pentru noi decât „Spam", care
     * e vecinul lui pe ecran.
     *
     * Rândurile noi se taie din valoare, ca la adresa de e-mail: un antet e
     * despărțit de următorul printr-un rând nou, deci o valoare care conține
     * unul ar putea adăuga anteturi inventate. Aici valorile vin din codul
     * nostru, dar regula se ține la scriere, nu la încredere.
     */
    foreach ($anteturiInPlus as $nume => $valoare) {
        $nume    = preg_replace('/[^A-Za-z0-9-]/', '', (string) $nume);
        $valoare = str_replace(["\r", "\n"], '', (string) $valoare);

        if ($nume !== '' && $valoare !== '') {
            $anteturi[$nume] = $valoare;
        }
    }

    $textAnteturi = '';
    foreach ($anteturi as $nume => $valoare) {
        $textAnteturi .= $nume . ': ' . $valoare . "\r\n";
    }

    /* ---------------------------- Corpul ------------------------------ */

    /**
     * Mesajul pleacă în două variante deodată: text simplu și HTML.
     *
     * „multipart/alternative" înseamnă „aceeași scrisoare, scrisă de două ori"
     * — programul alege ce știe să afișeze. Varianta de text nu e o formalitate:
     * o citesc ceasurile inteligente, cititoarele de ecran și oamenii care au
     * închis HTML-ul, iar lipsa ei e unul dintre semnele după care filtrele de
     * spam pun mesajele deoparte.
     */
    $mesaj  = "Mesajul acesta are și o variantă text, și una HTML.\r\n\r\n";

    $mesaj .= '--' . $granita . "\r\n";
    $mesaj .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $mesaj .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
    $mesaj .= quoted_printable_encode($corp['text']) . "\r\n\r\n";

    $mesaj .= '--' . $granita . "\r\n";
    $mesaj .= "Content-Type: text/html; charset=UTF-8\r\n";
    $mesaj .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
    $mesaj .= quoted_printable_encode($corp['html']) . "\r\n\r\n";

    $mesaj .= '--' . $granita . "--\r\n";

    $subiectCodat = mimeNume($subiect, true);

    /* --------------------------- Plecarea ----------------------------- */

    $metoda = (string) ($config['email_metoda'] ?? 'auto');

    if ($metoda === 'auto') {
        // În XAMPP nu există server de e-mail, deci mail() ar da mereu greș.
        $metoda = !empty($config['dezvoltare']) ? 'fisier' : 'mail';
    }

    if ($metoda === 'fisier') {
        return scrieEmailInFisier($catre, $subiect, $textAnteturi, $corp);
    }

    /**
     * Al cincilea parametru pune adresa și în „plicul" mesajului, nu doar în
     * antetul From. Fără el, serverul trimite de pe adresa contului de
     * găzduire (ceva de forma user@srv123.host.ro), care nu se potrivește cu
     * domeniul nostru — iar nepotrivirea aia e exact ce caută verificarea SPF
     * când hotărăște dacă mesajul e spam.
     */
    $plecat = @mail($catre, $subiectCodat, $mesaj, $textAnteturi, '-f' . $expeditor);

    if (!$plecat) {
        error_log('PulsulOrasului: mail() a dat greș pentru un mesaj de tip „' . $subiect . '".');
    }

    // În dezvoltare păstrăm și o copie, ca să se poată citi ce s-a trimis.
    if (!empty($config['dezvoltare'])) {
        scrieEmailInFisier($catre, $subiect, $textAnteturi, $corp);
    }

    return $plecat;
}

/**
 * O adresă bună de pus într-un antet.
 *
 * Pe lângă forma obișnuită, se cere să nu conțină rânduri noi sau caractere de
 * control — vezi explicația despre injecția în anteturi de mai sus.
 */
function esteAdresaSigura(string $adresa): bool
{
    if ($adresa === '' || strlen($adresa) > 254) {
        return false;
    }

    if (preg_match('/[\r\n\t\x00-\x1F\x7F]/', $adresa)) {
        return false;
    }

    return filter_var($adresa, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Pregătește un text pentru un antet de e-mail.
 *
 * Anteturile știu doar ASCII, deci diacriticele se codează. Rândurile noi se
 * scot înainte de orice altceva.
 */
function mimeNume(string $text, bool $singur = false): string
{
    $text = str_replace(["\r", "\n", "\t"], ' ', $text);
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

    // Dacă e doar ASCII, se lasă așa: se citește mai ușor în sursa mesajului.
    if (preg_match('/^[\x20-\x7E]*$/', $text)) {
        return $singur ? $text : '"' . str_replace('"', '', $text) . '"';
    }

    return mb_encode_mimeheader($text, 'UTF-8', 'B', "\r\n");
}

/**
 * Scrie mesajul într-un fișier, în locul trimiterii.
 *
 * Fișierul conține linkuri de confirmare și parole temporare valabile, deci
 * stă în private/, unde .htaccess refuză accesul prin web.
 */
function scrieEmailInFisier(string $catre, string $subiect, string $anteturi, array $corp): bool
{
    $dosar = dirname(__DIR__) . '/private';

    if (!is_dir($dosar) && !@mkdir($dosar, 0755, true) && !is_dir($dosar)) {
        return false;
    }

    $randuri = str_repeat('=', 70);

    $continut = "\n$randuri\n"
        . '[' . date('Y-m-d H:i:s') . "] către: $catre\n"
        . "subiect: $subiect\n"
        . "$randuri\n"
        . $anteturi . "\n"
        . $corp['text'] . "\n";

    $scris = @file_put_contents($dosar . '/emailuri-trimise.log', $continut, FILE_APPEND);

    // Și varianta HTML, ca să se poată deschide în browser și verifica aspectul.
    @file_put_contents($dosar . '/ultimul-email.html', $corp['html']);

    return $scris !== false;
}

/* ============================== ȘABLONUL =============================== */

/**
 * Construiește mesajul, în ambele variante.
 *
 * $blocuri poate conține:
 *   'salut'      — „Bună, Ionuț!"
 *   'paragrafe'  — listă de texte
 *   'buton'      — ['text' => ..., 'href' => ...]
 *   'link_gol'   — adresa scrisă și ca text, sub buton
 *   'cod'        — ['valoare' => 'A7K2M9', 'eticheta' => 'Parola ta temporară']
 *                  Cutia își ia mărimea după lungimea valorii: un cod scurt
 *                  rămâne mare și rărit, ca să poată fi tastat; ceva mai lung
 *                  (o dată, de pildă) se scrie ca restul mesajului.
 *   'atentie'    — text pus într-o casetă gălbuie
 *   'incheiere'  — ultimul paragraf, mai mic
 */
function sablonEmail(string $titlu, array $blocuri): array
{
    global $config;

    $site = rtrim((string) ($config['url_site'] ?? 'https://pulsulorasului.ro'), '/');

    /* ---------------------------- culorile ---------------------------- */
    $rosu     = '#d4392b';
    $text     = '#1a1f27';
    $textMoale= '#5a6472';
    $margine  = '#e5e8ee';
    $fundal   = '#f4f5f8';

    $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

    /* ============================ HTML ============================== */

    $h = [];

    $h[] = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
    $h[] = '<html xmlns="http://www.w3.org/1999/xhtml" lang="ro">';
    $h[] = '<head>';
    $h[] = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';
    $h[] = '<meta name="viewport" content="width=device-width, initial-scale=1" />';

    /**
     * Mesajul rămâne luminos, chiar și la cine are tema întunecată.
     *
     * Fără rândurile astea, Apple Mail și Outlook răstoarnă singure culorile:
     * fundalul alb devine închis, dar culorile scrise de noi pentru text rămân
     * închise și ele — adică negru pe negru. E mai bine să spunem clar că ne
     * ocupăm noi de culori, decât să lăsăm fiecare program să ghicească.
     */
    $h[] = '<meta name="color-scheme" content="light" />';
    $h[] = '<meta name="supported-color-schemes" content="light" />';

    $h[] = '<title>' . h($titlu) . '</title>';
    $h[] = '<style>';
    $h[] = ':root{color-scheme:light;supported-color-schemes:light}';
    // Puținul din <style> care merită pus: pe ecran mic, marginile se strâng.
    // Ce nu trece de filtrul programului de e-mail nu strică nimic, pentru că
    // aspectul de bază vine din atributele style ale fiecărui element.
    $h[] = '@media only screen and (max-width:620px){';
    $h[] = '  .po-pad{padding-left:22px !important;padding-right:22px !important}';
    $h[] = '  .po-titlu{font-size:22px !important}';
    // Cartonașele au marginile lor, mai strânse decât ale mesajului: pe un
    // ecran de 360px, 18px de fiecare parte mănâncă o zecime din lățime.
    $h[] = '  .po-pad-card{padding-left:14px !important;padding-right:14px !important}';
    $h[] = '}';
    $h[] = '</style>';
    $h[] = '</head>';
    $h[] = '<body style="margin:0;padding:0;background:' . $fundal . ';">';

    // Textul de previzualizare: ce se vede în listă, lângă subiect, înainte de
    // deschidere. Fără el, programele iau primele cuvinte din corp — de obicei
    // exact ce nu trebuie.
    $previzualizare = $blocuri['paragrafe'][0] ?? $titlu;
    $h[] = '<div style="display:none;font-size:1px;color:' . $fundal . ';line-height:1px;'
         . 'max-height:0;max-width:0;opacity:0;overflow:hidden;">'
         . h(mb_substr(strip_tags($previzualizare), 0, 120)) . '</div>';

    $h[] = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
         . 'style="background:' . $fundal . ';">';
    $h[] = '<tr><td align="center" style="padding:28px 12px;">';

    /**
     * Șase sute de pixeli, dar nu MAI MULT decât are ecranul.
     *
     * `width="600"` rămâne ca atribut, pentru Outlook, care nu citește CSS-ul
     * și oricum desenează pe lat. În `style` însă e `width:100%` cu un plafon
     * de 600: pe un telefon de 412px mesajul se strânge la 412, în loc să iasă
     * cu două sute de pixeli în afara ecranului.
     *
     * Era `width:600px` fix. Gmail pe telefon micșorează singur tot mesajul
     * până încape, deci se vedea bine — dar nu toate programele o fac, iar
     * acolo unde nu se face, omul trebuie să miște mesajul în lături ca să
     * citească un rând. Se vede mai ales de când cartonașele au poza lată
     * deasupra.
     */
    $h[] = '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" '
         . 'style="width:100%;max-width:600px;">';

    /* --------------------------- antetul ------------------------------ */
    // Numele site-ului, scris cu litere — nu o imagine cu logo, care ar fi
    // blocată până când omul cere afișarea imaginilor.
    $h[] = '<tr><td align="center" style="padding:0 0 18px 0;font-family:' . $font . ';'
         . 'font-size:19px;font-weight:bold;letter-spacing:-0.3px;color:' . $text . ';">'
         . 'Pulsul<span style="color:' . $rosu . ';">Orasului</span>'
         . '<span style="color:' . $textMoale . ';font-weight:normal;">.Ro</span>'
         . '</td></tr>';

    /* ---------------------------- cardul ------------------------------ */
    $h[] = '<tr><td style="background:#ffffff;border:1px solid ' . $margine . ';border-radius:14px;">';

    // Dunga colorată de sus, în locul unei imagini de antet.
    $h[] = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">';
    $h[] = '<tr><td style="height:4px;background:' . $rosu . ';border-radius:14px 14px 0 0;'
         . 'font-size:0;line-height:0;">&nbsp;</td></tr>';
    $h[] = '</table>';

    $h[] = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">';
    $h[] = '<tr><td class="po-pad" style="padding:32px 36px;font-family:' . $font . ';">';

    $h[] = '<h1 class="po-titlu" style="margin:0 0 18px 0;font-size:25px;line-height:1.25;'
         . 'font-weight:bold;color:' . $text . ';letter-spacing:-0.4px;">' . h($titlu) . '</h1>';

    if (!empty($blocuri['salut'])) {
        $h[] = '<p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;color:' . $text . ';">'
             . h($blocuri['salut']) . '</p>';
    }

    foreach (($blocuri['paragrafe'] ?? []) as $paragraf) {
        $h[] = '<p style="margin:0 0 16px 0;font-size:16px;line-height:1.6;color:' . $textMoale . ';">'
             . h($paragraf) . '</p>';
    }

    /* --------------------------- citatul ------------------------------ */

    /**
     * Vorbele altcuiva, arătate ca vorbele altcuiva.
     *
     * Nu e cutia de la `cod`: aceea e făcută pentru șase caractere de tastat,
     * cu litere mari și rărite. Aici e un text de citit, poate lung, scris de
     * un om — și trebuie să se vadă limpede unde începe și unde se termină,
     * altfel s-ar amesteca cu ce spunem noi. De aceea o dungă în stânga și un
     * fundal stins, ca un citat dintr-o carte.
     *
     * Rândurile scrise de om se păstrează: `nl2br` peste textul DEJA scăpat cu
     * h(). În ordinea asta, nu invers — altfel `<br />`-urile puse de noi ar fi
     * fost scăpate și ele, și s-ar fi văzut ca text.
     */
    if (!empty($blocuri['citat']['text'])) {
        $h[] = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
             . 'style="margin:20px 0;"><tr><td '
             . 'style="background:#f7f8fa;border-left:3px solid ' . $margine . ';'
             . 'border-radius:0 8px 8px 0;padding:16px 18px;font-family:' . $font . ';">';

        if (!empty($blocuri['citat']['cine'])) {
            $h[] = '<div style="font-size:13.5px;font-weight:bold;color:' . $text . ';'
                 . 'margin:0 0 8px 0;">' . h($blocuri['citat']['cine']) . '</div>';
        }

        $h[] = '<div style="font-size:15.5px;line-height:1.6;color:' . $textMoale . ';">'
             . nl2br(h((string) $blocuri['citat']['text'])) . '</div>';

        $h[] = '</td></tr></table>';
    }

    /* ------------------------- lista de anunțuri ---------------------- */

    /**
     * Evenimentele zilei, unul sub altul, ca niște CARTONAȘE — aceleași ca pe
     * prima pagină a site-ului: poza lată deasupra, apoi categoria, titlul,
     * începutul textului și rândul cu ora și locul.
     *
     * Fiecare rând primește:
     * ['titlu', 'cand', 'unde', 'poza', 'href', 'categorie', 'text'].
     *
     * DE CE POZA DEASUPRA, ȘI NU ÎN STÂNGA. A stat o vreme în stânga, într-o
     * casetă de 120px. Coperțile sunt 16:9, iar 120px lățime înseamnă 68px
     * înălțime: pe telefon ieșea o dungă în care nu se vedea nimic din afiș —
     * exact partea pentru care omul se uită la un anunț. Lată cât mesajul, are
     * peste 500px și se vede ca pe site.
     *
     * CE SE ÎNTÂMPLĂ CÂND POZELE NU SE ÎNCARCĂ. Gmail, Outlook și aproape toate
     * celelalte NU aduc pozele până nu cere omul; un mesaj gândit doar pentru
     * cazul fericit se face praf la prima deschidere.
     *
     * Ce îl ține întreg:
     *
     *   1. `<img>` are `width` ȘI `height` scrise CA ATRIBUTE, nu doar în
     *      `style`. Atributele sunt tot ce citesc programele când poza lipsește
     *      — ele rezervă locul. Outlook nu se uită deloc la lățimile din CSS.
     *   2. `width:100%;height:auto` în `style`, pentru cine ȘTIE CSS: acolo
     *      poza se strânge singură pe un ecran îngust, în loc să iasă din
     *      mesaj. Cele două nu se ceartă — fiecare program îl ascultă pe cel pe
     *      care îl înțelege.
     *   3. `alt=""`, GOL DINADINS. Un alt scris („Coperta evenimentului") s-ar
     *      arăta în locul pozei și ar umfla caseta cu două rânduri de text.
     *      Titlul e oricum scris dedesubt, deci poza n-are nimic de spus în
     *      plus: e decor.
     *
     * Celula pozei are un fundal stins, ca locul gol să arate a loc gol anume,
     * nu a ceva stricat.
     *
     * UN ANUNȚ FĂRĂ NICIO POZĂ n-are casetă deloc — cartonașul începe de-a
     * dreptul cu titlul, exact ca pe site. Cartonașele stau unul sub altul, nu
     * unul lângă altul, deci nimic nu se strâmbă din asta: nu e nimic de
     * aliniat.
     */
    if (!empty($blocuri['lista'])) {
        /**
         * Lățimea pozei, în pixeli, pentru atributul `width`.
         *
         * Cartonașul stă în cardul mesajului, care are 600px și `padding:32px
         * 36px` — rămân 528. Minus cele două linii ale chenarului: 526.
         */
        $latimePoza   = 526;
        $inaltimePoza = (int) round($latimePoza * 9 / 16);   // 16:9, ca pe site

        $h[] = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
             . 'style="margin:8px 0 4px 0;">';

        foreach ($blocuri['lista'] as $rand) {
            $titluRand = (string) ($rand['titlu'] ?? '');
            $href      = (string) ($rand['href'] ?? '');
            $poza      = (string) ($rand['poza'] ?? '');
            $categorie = (string) ($rand['categorie'] ?? '');
            $textRand  = (string) ($rand['text'] ?? '');

            $h[] = '<tr><td style="padding:0 0 18px 0;">';
            $h[] = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
                 . 'style="border:1px solid ' . $margine . ';border-radius:12px;">';

            /* --- poza, lată cât cartonașul --- */
            if ($poza !== '') {
                $h[] = '<tr><td style="background:#e9ecf2;border-radius:12px 12px 0 0;'
                     . 'font-size:0;line-height:0;">'
                     . '<a href="' . h($href) . '" style="text-decoration:none;">'
                     . '<img src="' . h($poza) . '" width="' . $latimePoza . '" '
                     . 'height="' . $inaltimePoza . '" alt="" '
                     . 'style="display:block;width:100%;max-width:' . $latimePoza . 'px;'
                     . 'height:auto;border:0;outline:none;border-radius:12px 12px 0 0;" />'
                     . '</a></td></tr>';
            }

            /* --- textul --- */
            $h[] = '<tr><td class="po-pad-card" style="padding:16px 18px;font-family:' . $font . ';">';

            /**
             * Categoria, ca o etichetă mică deasupra titlului.
             *
             * Pe site stă peste poză, în colțul de sus-stânga. Aici nu se poate
             * pune peste: așezarea în straturi („position:absolute") nu merge în
             * Outlook, iar o etichetă căzută din colț peste titlu ar fi fost mai
             * rea decât una mutată. Spune același lucru, cu un rând mai sus.
             */
            if ($categorie !== '') {
                $h[] = '<div style="font-size:11.5px;font-weight:bold;letter-spacing:0.06em;'
                     . 'text-transform:uppercase;color:' . $rosu . ';margin:0 0 7px 0;">'
                     . h($categorie) . '</div>';
            }

            $h[] = '<div style="font-size:19px;line-height:1.3;font-weight:bold;">'
                 . '<a href="' . h($href) . '" style="color:' . $text . ';text-decoration:none;">'
                 . h($titluRand) . '</a></div>';

            if ($textRand !== '') {
                $h[] = '<div style="margin:8px 0 0 0;font-size:14.5px;line-height:1.5;'
                     . 'color:' . $textMoale . ';">' . h($textRand) . '</div>';
            }

            $subtitlu = array_filter([
                (string) ($rand['cand'] ?? ''),
                (string) ($rand['unde'] ?? ''),
            ], static fn(string $x): bool => $x !== '');

            if ($subtitlu !== []) {
                $h[] = '<div style="margin:10px 0 0 0;font-size:13px;line-height:1.45;'
                     . 'color:' . $textMoale . ';">' . h(implode(' · ', $subtitlu)) . '</div>';
            }

            $h[] = '</td></tr></table>';
            $h[] = '</td></tr>';
        }

        $h[] = '</table>';
    }

    /* --------------------------- codul -------------------------------- */
    if (!empty($blocuri['cod']['valoare'])) {
        $h[] = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
             . 'style="margin:22px 0;"><tr><td align="center" '
             . 'style="background:#f7f8fa;border:1px solid ' . $margine . ';border-radius:12px;padding:20px;">';

        if (!empty($blocuri['cod']['eticheta'])) {
            $h[] = '<div style="font-family:' . $font . ';font-size:13px;font-weight:bold;'
                 . 'text-transform:uppercase;letter-spacing:1px;color:' . $textMoale . ';'
                 . 'margin-bottom:10px;">' . h($blocuri['cod']['eticheta']) . '</div>';
        }

        /**
         * Cutia asta a fost făcută pentru o parolă temporară: șase caractere,
         * mari, rărite, cu lățime fixă, ca să poată fi tastate fără greșeală.
         *
         * La un șir scurt e exact ce trebuie. La unul lung — o dată, de pildă
         * „6 septembrie 2026" — aceleași 32px cu 7px între litere ocupă
         * aproape toată lățimea mesajului, iar rezultatul nu mai seamănă cu
         * restul e-mailurilor: pare scris după alte reguli.
         *
         * Deci mărimea se ia după cât de lung e ce avem de arătat. Un cod
         * rămâne cum era; ceva de citit, nu de tastat, primește o mărime
         * obișnuită și litere lipite normal.
         */
        $valoare  = (string) $blocuri['cod']['valoare'];
        $eSirScurt = mb_strlen($valoare, 'UTF-8') <= 8;

        $marime  = $eSirScurt ? '32px' : '22px';
        $rarire  = $eSirScurt ? '7px'  : '0.5px';

        // Lățimea fixă a literelor are rost la ce se tastează de mână, ca „0"
        // și „O" să nu se confunde. La un text de citit n-aduce nimic, deci
        // rămâne fontul obișnuit al mesajului.
        $fontCod = $eSirScurt ? 'Consolas,Menlo,Monaco,\'Courier New\',monospace' : $font;

        $h[] = '<div style="font-family:' . $fontCod . ';'
             . 'font-size:' . $marime . ';font-weight:bold;letter-spacing:' . $rarire . ';'
             . 'color:' . $text . ';line-height:1.3;">' . h($valoare) . '</div>';

        $h[] = '</td></tr></table>';
    }

    /* --------------------------- butonul ------------------------------ */
    if (!empty($blocuri['buton']['href'])) {
        $h[] = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" '
             . 'style="margin:24px 0;"><tr>';
        $h[] = '<td align="center" style="background:' . $rosu . ';border-radius:999px;">';
        $h[] = '<a href="' . h($blocuri['buton']['href']) . '" '
             . 'style="display:inline-block;padding:14px 32px;font-family:' . $font . ';'
             . 'font-size:16px;font-weight:bold;color:#ffffff;text-decoration:none;'
             . 'border-radius:999px;">' . h($blocuri['buton']['text']) . '</a>';
        $h[] = '</td></tr></table>';
    }

    /* ------------------------- linkul, ca text ------------------------ */
    if (!empty($blocuri['link_gol'])) {
        $h[] = '<p style="margin:0 0 16px 0;font-size:13.5px;line-height:1.6;color:' . $textMoale . ';">'
             . 'Dacă butonul nu merge, copiază adresa asta în bara browserului:</p>';
        $h[] = '<p style="margin:0 0 18px 0;font-size:13px;line-height:1.5;word-break:break-all;">'
             . '<a href="' . h($blocuri['link_gol']) . '" style="color:' . $rosu . ';">'
             . h($blocuri['link_gol']) . '</a></p>';
    }

    /* --------------------------- atenție ------------------------------ */
    if (!empty($blocuri['atentie'])) {
        $h[] = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
             . 'style="margin:20px 0;"><tr><td '
             . 'style="background:#fffbeb;border-left:3px solid #d97706;border-radius:0 8px 8px 0;'
             . 'padding:13px 16px;font-family:' . $font . ';font-size:14.5px;line-height:1.55;'
             . 'color:#7c4a03;">' . h($blocuri['atentie']) . '</td></tr></table>';
    }

    if (!empty($blocuri['incheiere'])) {
        $h[] = '<p style="margin:18px 0 0 0;font-size:14px;line-height:1.6;color:' . $textMoale . ';">'
             . h($blocuri['incheiere']) . '</p>';
    }

    $h[] = '</td></tr></table>';
    $h[] = '</td></tr>';

    /* --------------------------- subsolul ----------------------------- */
    $h[] = '<tr><td align="center" style="padding:20px 12px 0 12px;font-family:' . $font . ';'
         . 'font-size:12.5px;line-height:1.6;color:' . $textMoale . ';">';
    $h[] = 'Ai primit mesajul ăsta deoarece ești membru pe '
         . '<a href="' . h($site) . '" style="color:' . $textMoale . ';">pulsulorasului.ro</a>.';
    $h[] = '<br />Evenimente locale, sport, cultură și tot ce mișcă în oraș.';

    /**
     * IEȘIREA DE LA NEWSLETTER, scrisă negru pe alb.
     *
     * Numai mesajele trimise în serie o primesc — cele pe care omul nu le-a
     * cerut de fiecare dată. O confirmare de cont sau vestea că i s-a anulat un
     * eveniment nu se „dezabonează": alea sunt răspunsuri la ceva ce a făcut el.
     *
     * Se scrie chiar în subsol, cu adresa întreagă alături: cine caută ieșirea o
     * caută jos, iar dacă n-o găsește în două secunde apasă „Spam". Un singur om
     * care face asta strică livrarea pentru toți ceilalți.
     */
    if (!empty($blocuri['dezabonare'])) {
        $h[] = '<br /><br />Nu mai vrei mesajul ăsta zilnic? '
             . '<a href="' . h($blocuri['dezabonare']) . '" '
             . 'style="color:' . $textMoale . ';text-decoration:underline;">Dezabonează-te</a> '
             . 'sau schimbă bifa din setările contului.';
    }

    $h[] = '</td></tr>';

    $h[] = '</table>';
    $h[] = '</td></tr></table>';
    $h[] = '</body></html>';

    /* ============================ TEXT ============================== */

    $t = [];

    // Titlul se lasă scris normal, nu cu majuscule.
    //
    // Două motive: strtoupper() nu știe de diacritice, deci „Confirmă-ți" ar
    // ieși „CONFIRMă-țI"; iar textul scris integral cu majuscule e unul dintre
    // semnele după care filtrele de spam dau puncte negative.
    $t[] = $titlu;
    $t[] = str_repeat('=', min(70, mb_strlen($titlu, 'UTF-8')));
    $t[] = '';

    if (!empty($blocuri['salut'])) {
        $t[] = $blocuri['salut'];
        $t[] = '';
    }

    foreach (($blocuri['paragrafe'] ?? []) as $paragraf) {
        $t[] = wordwrap($paragraf, 72, "\n", false);
        $t[] = '';
    }

    /**
     * În text simplu, citatul se cunoaște după „> " la începutul fiecărui rând
     * — cum se citează de când e e-mailul. Ruperea se face înainte, ca prefixul
     * să ajungă și pe rândurile născute din wordwrap, nu doar pe cele scrise de
     * om.
     */
    if (!empty($blocuri['citat']['text'])) {
        if (!empty($blocuri['citat']['cine'])) {
            $t[] = $blocuri['citat']['cine'] . ':';
        }

        $rupt = wordwrap((string) $blocuri['citat']['text'], 68, "\n", false);
        $t[] = '> ' . str_replace("\n", "\n> ", $rupt);
        $t[] = '';
    }

    /**
     * Lista, în text simplu. Fiecare anunț: titlu, când și unde, apoi adresa.
     *
     * Adresa se scrie pe rândul ei, întreagă: în text simplu nu există legături
     * ascunse sub cuvinte, iar o adresă tăiată de wordwrap nu se mai poate
     * apăsa. De aceea rândul ăsta NU trece prin wordwrap.
     */
    if (!empty($blocuri['lista'])) {
        foreach ($blocuri['lista'] as $rand) {
            $t[] = '* ' . (string) ($rand['titlu'] ?? '');

            $subtitlu = array_filter([
                (string) ($rand['categorie'] ?? ''),
                (string) ($rand['cand'] ?? ''),
                (string) ($rand['unde'] ?? ''),
            ], static fn(string $x): bool => $x !== '');

            if ($subtitlu !== []) {
                $t[] = '  ' . implode(' · ', $subtitlu);
            }

            if (!empty($rand['text'])) {
                $t[] = '  ' . wordwrap((string) $rand['text'], 68, "\n  ", false);
            }

            if (!empty($rand['href'])) {
                $t[] = '  ' . (string) $rand['href'];
            }

            $t[] = '';
        }
    }

    if (!empty($blocuri['cod']['valoare'])) {
        $t[] = ($blocuri['cod']['eticheta'] ?? 'Codul tău') . ':';
        $t[] = '';
        $t[] = '    ' . $blocuri['cod']['valoare'];
        $t[] = '';
    }

    if (!empty($blocuri['buton']['href'])) {
        $t[] = $blocuri['buton']['text'] . ':';
        $t[] = $blocuri['buton']['href'];
        $t[] = '';
    } elseif (!empty($blocuri['link_gol'])) {
        $t[] = $blocuri['link_gol'];
        $t[] = '';
    }

    if (!empty($blocuri['atentie'])) {
        $t[] = '! ' . wordwrap($blocuri['atentie'], 70, "\n  ", false);
        $t[] = '';
    }

    if (!empty($blocuri['incheiere'])) {
        $t[] = wordwrap($blocuri['incheiere'], 72, "\n", false);
        $t[] = '';
    }

    $t[] = str_repeat('-', 60);
    $t[] = 'Ai primit mesajul ăsta deoarece ești membru pe ';
    $t[] = $site;

    if (!empty($blocuri['dezabonare'])) {
        $t[] = '';
        $t[] = 'Nu mai vrei mesajul ăsta zilnic? Deschide adresa de mai jos, sau';
        $t[] = 'schimbă bifa din setările contului.';
        $t[] = (string) $blocuri['dezabonare'];
    }

    return [
        'html' => implode("\n", $h),
        // Rândurile din corpul unui e-mail se despart cu CRLF, nu cu LF.
        'text' => str_replace("\n", "\r\n", implode("\n", $t)),
    ];
}

/* ====================== MESAJELE PROPRIU-ZISE ========================== */

/** Confirmarea adresei, la înregistrare sau la retrimitere. */
function emailConfirmare(string $catre, string $prenume, string $link, bool $retrimitere = false): bool
{
    global $config;
    $ore = (int) ($config['ore_valabilitate_token'] ?? 48);

    return trimiteEmail($catre, 'Confirmă-ți adresa pe PulsulOrasului.Ro', [
        'salut'     => 'Bună, ' . $prenume . '!',
        'paragrafe' => [
            $retrimitere
                ? 'Ți-am trimis din nou linkul de confirmare, așa cum ai cerut.'
                : 'Ne bucurăm că ți-ai făcut cont. A mai rămas un singur pas: '
                . 'confirmă că adresa asta e a ta.',
            'Apasă butonul de mai jos și contul devine activ.',
        ],
        'buton'     => ['text' => 'Confirmă adresa', 'href' => $link],
        'link_gol'  => $link,
        'atentie'   => 'Linkul e valabil ' . $ore . ' de ore și poate fi folosit o singură dată.',
        'incheiere' => 'Dacă nu tu ai cerut contul, nu trebuie să faci nimic ... '
                     . 'fără confirmare, contul nu se activează niciodată.',
    ]);
}

/** Parola temporară, la recuperare. */
function emailParolaTemporara(string $catre, string $prenume, string $parola, int $minute): bool
{
    global $config;
    $site = rtrim((string) ($config['url_site'] ?? ''), '/');

    return trimiteEmail($catre, 'Parola ta temporară pentru PulsulOrasului.Ro', [
        'salut'     => 'Bună, ' . $prenume . '!',
        'paragrafe' => [
            'Ai cerut să intri în cont fără parolă. Folosește parola temporară de mai jos, '
            . 'exact așa cum e scrisă, cu litere mari.',
            'Imediat după ce intri, îți alegi o parolă nouă ... până atunci nu poți face '
            . 'altceva în cont.',
        ],
        'cod'       => ['eticheta' => 'Parola ta temporară', 'valoare' => $parola],
        'buton'     => ['text' => 'Intră în cont', 'href' => $site . '/login.php'],
        'atentie'   => 'E valabilă ' . $minute . ' de minute și merge o singură dată.',
        'incheiere' => 'Dacă nu tu ai cerut-o, poți lăsa mesajul așa. Parola ta de până acum '
                     . 'nu s-a schimbat și rămâne valabilă.',
    ]);
}

/**
 * „Bine ai venit", pentru cine s-a înscris cu Google.
 *
 * Nu are link de confirmat: Google ne-a spus deja că adresa e a lui. Mesajul
 * are totuși rost — e urma scrisă că s-a deschis un cont pe adresa asta, deci
 * omul află dacă altcineva i-a folosit contul de Google.
 */
function emailBunVenit(string $catre, string $prenume): bool
{
    global $config;
    $site = rtrim((string) ($config['url_site'] ?? ''), '/');

    return trimiteEmail($catre, 'Bine ai venit pe PulsulOrasului.Ro', [
        'salut'     => 'Bună, ' . $prenume . '!',
        'paragrafe' => [
            'Contul tău e gata și activ. L-ai deschis cu Google, deci nu ai de ținut '
            . 'minte nicio parolă nouă.',
            'De acum poți publica evenimente, te poți înscrie la cele ale altora și '
            . 'poți lăsa comentarii.',
        ],
        'buton'     => ['text' => 'Vezi ce se întâmplă în oraș', 'href' => $site . '/index.php'],
        'incheiere' => 'Dacă vrei să intri și cu parolă, nu doar cu Google, o poți seta '
                     . 'oricând din setarile contului tău.',
    ]);
}

/** Înștiințarea că parola a fost schimbată. */
function emailParolaSchimbata(string $catre, string $prenume): bool
{
    global $config;
    $site = rtrim((string) ($config['url_site'] ?? ''), '/');

    return trimiteEmail($catre, 'Parola contului tău a fost schimbată', [
        'salut'     => 'Bună, ' . $prenume . '!',
        'paragrafe' => [
            'Îți scriem doar ca să știi: parola contului tău de pe PulsulOrasului.Ro '
            . 'tocmai a fost schimbată.',
        ],
        'atentie'   => 'Dacă nu tu ai făcut schimbarea, scrie-ne imediat: cineva are acces '
                     . 'la adresa ta de e-mail sau la contul tău.',
        'buton'     => ['text' => 'Scrie-ne', 'href' => $site . '/contact.php'],
        'incheiere' => 'Dacă tu ai schimbat-o, nu trebuie să faci nimic.',
    ]);
}

/**
 * Linkul care pornește ștergerea contului.
 *
 * E-mailul e a doua încuietoare: cine a pus mâna pe un calculator lăsat
 * deschis poate apăsa butonul, dar nu poate duce ștergerea la capăt fără să
 * ajungă și la cutia poștală.
 */
function emailStergereCont(string $catre, string $prenume, string $link, int $ore, int $zile): bool
{
    return trimiteEmail($catre, 'Confirmă ștergerea contului de pe PulsulOrasului.Ro', [
        'salut'     => 'Bună, ' . $prenume . '!',
        'paragrafe' => [
            'Ai cerut ștergerea contului tău. Nu am șters încă nimic, mai e nevoie de '
            . 'o apăsare, aici, din e-mail.',
            'După ce apeși, contul intră într-un răgaz de ' . $zile . ' de zile. În tot '
            . 'acest timp datele tale rămân neatinse.',
        ],
        'buton'     => ['text' => 'Da, șterge-mi contul', 'href' => $link],
        'link_gol'  => $link,
        'atentie'   => 'Te răzgândești? Intră pur și simplu în cont oricând în cele '
                     . $zile . ' de zile. Simpla intrare oprește ștergerea, fără să mai '
                     . 'ai ceva de făcut. Abia după ' . $zile . ' de zile fără nicio '
                     . 'intrare datele se șterg definitiv.',
        'incheiere' => 'Dacă nu tu ai cerut ștergerea, nu apăsa butonul și schimbă-ți '
                     . 'parola: cineva a ajuns în contul tău. Fără confirmarea de aici, '
                     . 'linkul se stinge singur în ' . $ore . ' Sore.',
    ]);
}

/** Confirmarea că răgazul a pornit, cu data limpede scrisă. */
function emailStergereConfirmata(string $catre, string $prenume, string $cand, int $zile): bool
{
    global $config;
    $site = rtrim((string) ($config['url_site'] ?? ''), '/');

    return trimiteEmail($catre, 'Contul tău va fi șters pe ' . $cand, [
        'salut'     => 'Bună, ' . $prenume . '!',
        'paragrafe' => [
            'Am primit confirmarea. Contul tău e programat pentru ștergere pe ' . $cand . '.',
            'Până atunci nu se schimbă nimic: numele, poza și tot ce ai scris rămân '
            . 'exact cum le-ai lăsat.',
        ],
        'cod'       => ['eticheta' => 'Datele se șterg pe', 'valoare' => $cand],
        'buton'     => ['text' => 'M-am răzgândit, intru în cont', 'href' => $site . '/login.php'],
        'atentie'   => 'Ca să oprești ștergerea, e destul să intri în cont o dată, oricând '
                     . 'în cele ' . $zile . ' de zile. Nu ai de apăsat niciun buton anume: '
                     . 'intrarea singură anulează cererea.',
        'incheiere' => 'După ' . $cand . ', numele, adresa de e-mail și telefonul dispar '
                     . 'pentru totdeauna. Evenimentele la care ai fost rămân în istoricul '
                     . 'site-ului, dar fără numele tău.',
    ]);
}

/**
 * Mesajul din formularul de contact, trimis mai departe către noi.
 *
 * Pleacă la adresa de răspuns din config — cea la care oricum ar scrie omul
 * dacă apăsa „Reply". Mesajul e deja în baza de date când ajunge aici, deci
 * dacă e-mailul nu pleacă nu s-a pierdut nimic.
 */
function emailMesajDeContact(array $mesaj, ?int $membruId): bool
{
    global $config;

    $catre = (string) ($config['email_raspuns'] ?? $config['email_expeditor'] ?? '');

    /**
     * Fără adresă în config n-avem unde trimite.
     *
     * Nu e o piedică pentru omul care a scris — mesajul lui e deja în baza de
     * date. Dar e o piedică pentru noi, care n-am afla de el, așa că rămâne
     * scrisă undeva în loc să se piardă în tăcere.
     */
    if ($catre === '') {
        scrieInLog('spam-contact.log',
            'ATENȚIE: mesaj primit, dar nu l-am putut trimite pe e-mail — '
            . 'lipsesc email_raspuns și email_expeditor din inc/config.php');
        return false;
    }

    $cine = $membruId === null
        ? 'Vizitator fără cont.'
        : 'Membrul #' . $membruId . ' — datele sunt luate din contul lui.';

    /**
     * Mesajul omului intră ca paragraf obișnuit, deci trece prin aceeași
     * ieșire ca restul textelor din șablon (vezi sablonEmail): în varianta
     * HTML e scăpat cu htmlspecialchars, deci nimeni nu ne poate strecura
     * etichete în mesajul pe care îl citim noi.
     */
    return trimiteEmail($catre, 'Mesaj nou de la ' . $mesaj['prenume'] . ' ' . $mesaj['nume'], [
        'salut'     => 'Mesaj nou din formularul de contact',
        'paragrafe' => [
            $cine,
            'De la: ' . $mesaj['prenume'] . ' ' . $mesaj['nume'],
            'E-mail: ' . $mesaj['email'],
            'Telefon: ' . $mesaj['telefon'],
            '— — —',
            $mesaj['mesaj'],
        ],
        'incheiere' => 'Mesajul e salvat și în baza de date, în tabelul mesaje_contact.',
    ]);
}

/**
 * „Ai fost scos de pe lista de participanți."
 *
 * Pleacă în clipa scoaterii, singurul e-mail automat din toată povestea asta.
 * Omul are dreptul să afle de la noi, nu ajungând pe pagină peste trei zile și
 * negăsindu-se pe listă.
 *
 * $rol e „organizator" sau „staff", exact cum s-a scris în bază la scoatere
 * (vezi sql/016-excluderi-evenimente.sql). Nu se socotește aici: cine e staff
 * azi poate să nu mai fie mâine, iar mesajul trebuie să spună ce a fost atunci.
 *
 * Motivul intră ca paragraf obișnuit, deci trece prin aceeași ieșire ca restul
 * textelor din șablon — scăpat cu htmlspecialchars în varianta HTML. Nimeni nu
 * poate strecura etichete în e-mailul altcuiva prin caseta de motiv.
 */
function emailExcludereParticipant(
    string $catre,
    string $prenume,
    string $sex,
    string $titluEveniment,
    string $adresaEveniment,
    string $rol,
    string $motiv,
    bool $interzis
): bool {
    $cineA = $rol === 'organizator' ? 'organizatorul evenimentului' : 'un membru al echipei';

    // Acordul se face după om, ca peste tot pe site: „scoasă" pentru ea,
    // „scos" pentru el. E un mesaj neplăcut oricum — măcar să fie scris ca
    // pentru cineva anume, nu ca un formular.
    $scos = $sex === 'F' ? 'scoasă' : 'scos';

    $blocuri = [
        'salut'     => 'Bună, ' . $prenume . '!',
        'paragrafe' => [
            'Ai fost ' . $scos . ' de pe lista de participanți la „' . $titluEveniment . '", '
            . 'de către ' . $cineA . '. Locul tău s-a eliberat.',
            'Motivul, așa cum a fost scris:',
            $motiv,
        ],
        'buton'     => ['text' => 'Vezi evenimentul', 'href' => $adresaEveniment],
    ];

    /**
     * Ușa închisă se spune limpede, într-o casetă care se vede.
     *
     * Altfel omul s-ar întoarce pe pagină, ar apăsa „Voi participa" și ar primi
     * un refuz fără să înțeleagă de ce. E vestea cea mai grea din tot mesajul,
     * deci nu are ce căuta topită într-un paragraf.
     */
    if ($interzis) {
        $blocuri['atentie'] = 'Nu te mai poți înscrie la acest eveniment. '
                            . 'Celelalte evenimente de pe site rămân deschise pentru tine.';
    } else {
        $blocuri['incheiere'] = 'Te poți înscrie din nou, dacă mai sunt locuri.';
    }

    return trimiteEmail($catre, 'Ai fost ' . $scos . ' de pe lista de la „' . $titluEveniment . '"', $blocuri);
}

/**
 * Mulțumirea de după eveniment, cu invitația la note.
 *
 * Pleacă O SINGURĂ DATĂ, din cron/multumeste-participantilor.php, către
 * fiecare om rămas pe lista de participanți. Cine a fost scos de pe listă
 * înainte nu mai e pe ea, deci nu primește nimic — și e bine așa: el a primit
 * deja alt mesaj, cel cu motivul.
 *
 * NU se uită la bifa de newsletter din setări. Aceea e pentru „e-mail cu
 * evenimente noi", adică pentru ce n-a cerut nimeni anume; mesajul de față
 * vine după o seară la care omul s-a înscris el însuși, și e ultimul lucru
 * legat de ea. Un mesaj despre ceva ce ai făcut tu nu e reclamă.
 *
 * $adresaParticipanti e adresa ÎNTREAGĂ a paginii, cu „#panel-going" la coadă:
 * butonul trebuie să deschidă direct tabul cu oameni, nu să lase omul să-l
 * caute. Taburile se deschid după hash — vezi „data-tabs" din assets/js/main.js.
 *
 * Organizatorul primește același mesaj, cu alt prim paragraf: lui nu i se
 * mulțumește că a venit, ci că l-a ținut.
 */
function emailMultumireParticipare(
    string $catre,
    string $prenume,
    string $titluEveniment,
    string $adresaParticipanti,
    bool $eOrganizator = false
): bool {
    $primul = $eOrganizator
        ? 'S-a încheiat „' . $titluEveniment . '". Mulțumim că l-ai adus la viață — fără cineva care să-l pună la cale, această experiență n-ar fi existat.'
        : 'S-a încheiat „' . $titluEveniment . '". Mulțumim că ai fost acolo — sufletul unui eveniment sunt oamenii care iau parte la el.';

        $alDoilea = $eOrganizator
        ? 'Intră pe pagina evenimentului și oferă-le un rating celor care au venit. Tot de acolo îi poți bifa pe cei care au confirmat înscrierea, dar nu au mai ajuns.'
        : 'Intră pe pagina evenimentului și lasă un calificativ pentru cei alături de care ai fost. Le poți acorda steluțe direct din dreptul fiecăruia, în tabul „Au participat”.';

        $blocuri = [
            'salut'     => 'Bună, ' . $prenume . '!',
            'paragrafe' => [
                $primul,
                $alDoilea,
                'Calificativele cu steluțe rămân anonime: nimeni nu află cine le-a acordat. Dacă vrei, poți să lași și câteva cuvinte ce vor fi publicate pe profilurile membrilor. Cuvintele vor purta semnatura ta.',
            ],
            'buton'     => [
                'text' => 'Lasă o impresie celorlalți',
                'href' => $adresaParticipanti,
            ],
            'link_gol'  => $adresaParticipanti,
            'incheiere' => 'Nu există nicio obligație să acorzi note. Este doar un mod simplu prin care membrii comunității află cu cine vor merge la drum data viitoare.',
        ];

    $subiect = $eOrganizator
        ? 'S-a încheiat „' . $titluEveniment . '"'
        : 'Mulțumim că ai fost la „' . $titluEveniment . '"';

    return trimiteEmail($catre, $subiect, $blocuri);
}

/**
 * Newsletterul zilnic: ce se întâmplă azi în oraș.
 *
 * Pleacă doar când ARE ce spune — cronul nici nu ajunge aici dacă ziua e goală
 * (vezi trimiteNewsletterulZilei din inc/newsletter.php).
 *
 * SINGURUL MESAJ DE PE SITE CU BUTON DE DEZABONARE. Celelalte sunt răspunsuri
 * la ceva ce a făcut omul — o confirmare, vestea că i s-a anulat un eveniment —
 * și alea nu se „dezabonează". Ăsta vine nechemat în fiecare zi, deci ieșirea
 * trebuie să fie la vedere: cine n-o găsește în două secunde apasă „Spam", iar
 * un singur om care face asta strică livrarea pentru toți ceilalți.
 *
 * `$randuri` vin gata pregătite din inc/newsletter.php, cu adrese întregi și
 * poze care se pot lipsi — blocul „lista" din șablon ține locul gol de aceeași
 * mărime, ca mesajul să arate la fel și cu pozele blocate.
 */
function emailNewsletterZilnic(
    string $catre,
    string $prenume,
    array $randuri,
    string $linkDezabonare
): bool {
    $cate = count($randuri);

    /**
     * „un eveniment" / „3 evenimente" / „21 de evenimente".
     *
     * numaratoare() știe regula lui „de" (21 DE evenimente), dar nu și
     * singularul — ea primește substantivul gata ales. La unul singur se scrie
     * „un eveniment", nu „1 eveniment": cifra singură sună a inventar.
     */
    $cateSpus = $cate === 1 ? 'un eveniment' : numaratoare($cate, 'evenimente');

    /**
     * „CE URMEAZĂ", nu „ce se întâmplă".
     *
     * Lista începe de la clipa în care pleacă mesajul: ce a pornit deja nu mai
     * intră (vezi evenimenteleDeAzi din inc/newsletter.php). „Ce se întâmplă
     * azi" ar fi promis ziua întreagă, iar omul care știa de o alergare de
     * dimineață și n-o găsește în mesaj ar fi crezut că mesajul minte.
     */
    $primul = $cate === 1
    ? 'Uite ce se întâmplă azi în oraș. Avem un singur eveniment, dar poate e fix pe gustul tău.'
    : 'Uite ce se întâmplă azi în oraș. Sunt ' . $cateSpus . ', ordonate în funcție de oră.';

    $blocuri = [
        'salut'      => 'Bună, ' . $prenume . '!',
        'paragrafe'  => [$primul],
        'lista'      => $randuri,
        'incheiere'  => $cate === 1
        ? 'Dacă te hotărăști să mergi, apasă pe el și anunță că vii, așa organizatorul va ști pe cine să se bazeze!'
        : 'Dacă te hotărăști să mergi la vreunul, apasă pe el și anunță că vii, așa organizatorul va ști pe cine să se bazeze!',
        'dezabonare' => $linkDezabonare,
    ];

    $subiect = $cate === 1
        ? 'Azi în oraș: ' . $randuri[0]['titlu']
        : 'Azi în oraș: ' . $cateSpus;

    /**
     * „List-Unsubscribe" e antetul pe care îl citesc Gmail, Outlook și
     * celelalte ca să pună ELE un buton „Dezabonează-te" chiar lângă numele
     * expeditorului. E cel mai apăsat buton dintre toate — și e mult mai bun
     * pentru noi decât „Spam", care e vecinul lui pe ecran.
     *
     * Fără „List-Unsubscribe-Post", dinadins: acela le spune programelor să
     * dezaboneze de-a dreptul, cu o cerere trimisă de ele. Aici linkul duce la
     * o pagină cu un buton, iar apăsarea aia e a omului. Vezi dezabonare.php.
     */
    return trimiteEmail($catre, $subiect, $blocuri, [
        'List-Unsubscribe' => '<' . $linkDezabonare . '>',
    ]);
}

/**
 * Vestea că un eveniment s-a anulat.
 *
 * Pleacă în clipa în care organizatorul apasă „Anulează", către toți cei care
 * erau pe listă — ȘI cei care confirmaseră, ȘI cei doar interesați. Al doilea
 * grup n-a promis nimic, dar și-a ținut ziua liberă „poate mă duc"; și el
 * trebuie să afle că n-are unde.
 *
 * NU ARE BUTON SPRE EVENIMENT, deși ar fi părut lucrul firesc. Din clipa
 * anulării, pagina lui se deschide doar pentru staff (vezi
 * poateVedeaEvenimentul din inc/evenimente.php): un buton „Vezi evenimentul" ar
 * fi dus fiecare om exact într-o ușă închisă, la capătul unui mesaj care deja
 * îi strica planul. Butonul duce în oraș, unde mai sunt și altele.
 *
 * Motivul intră ca paragraf obișnuit, deci trece prin aceeași ieșire ca restul
 * textelor din șablon — scăpat cu htmlspecialchars în varianta HTML. Nimeni nu
 * poate strecura etichete în e-mailul altcuiva prin caseta de motiv.
 *
 * $eraParticipant schimbă o singură propoziție: cine confirmase primește o
 * vorbă care recunoaște că își făcuse un plan; cine era doar interesat, una mai
 * ușoară. Restul mesajului e la fel — vestea și motivul sunt aceleași pentru
 * toți.
 */
function emailAnulareEveniment(
    string $catre,
    string $prenume,
    string $titluEveniment,
    string $candAvutLoc,
    string $motiv,
    string $adresaSite,
    bool $eraParticipant
): bool {
    $primul = $eraParticipant
    ? 'Avem o veste neplăcută: „' . $titluEveniment . '” nu se mai ține. Organizatorul a fost nevoit să îl anuleze, iar tu erai înscris pe listă.'
    : 'Avem o veste neplăcută: „' . $titluEveniment . '” nu se mai ține. Organizatorul l-a anulat, iar noi știam că erai interesat de el.';

    $paragrafe = [$primul];

    // Ziua se spune din nou, întreagă: mesajul poate fi citit peste o săptămână,
    // iar „nu mai are loc" fără dată nu-i spune omului ce zi i s-a eliberat.
    if ($candAvutLoc !== '') {
        $paragrafe[] = 'Era programat pentru ' . $candAvutLoc . '.';
    }

    $paragrafe[] = 'Motivul, așa cum a fost scris de organizator:';
    $paragrafe[] = $motiv;

    $blocuri = [
        'salut'     => 'Bună, ' . $prenume . '!',
        'paragrafe' => $paragrafe,
        'buton'     => [
            'text' => 'Vezi ce alte evenimente au loc în oraș',
            'href' => $adresaSite,
        ],
        'link_gol'  => $adresaSite,
        'incheiere' => 'Nu trebuie să faci nimic, locul tău s-a eliberat automat. Dacă evenimentul se va reprograma pe viitor, va fi afișat un anunț nou.',
    ];

    return trimiteEmail($catre, '„' . $titluEveniment . '" a fost anulat', $blocuri);
}

/**
 * Hotărârea moderării, spusă organizatorului.
 *
 * Pleacă în clipa în care staff-ul apasă „Aprobă" sau „Respinge", pe pagina
 * anunțului. Un singur om îl primește — cel care l-a scris.
 *
 * Butonul duce pe pagina anunțului în amândouă cazurile, fiindcă organizatorul
 * își vede evenimentul oricare i-ar fi starea (vezi poateVedeaEvenimentul din
 * inc/evenimente.php). La aprobare e pagina pe care o vede acum toată lumea; la
 * respingere, cea pe care o vede doar el, cu banda care spune de ce.
 *
 * TREI HOTĂRÂRI, nu două — $hotarare:
 *
 *   'aprobat' — anunțul se vede de acum pe site
 *   'editare' — mai are nevoie de câteva schimbări, dar rămâne în așteptare;
 *               organizatorul îl îndreaptă și nu-l ia nimeni de la capăt
 *   'respins' — nu se publică
 *
 * Cea din mijloc e cea care lipsea. Un anunț bun, dar cu o oră lipsă sau cu
 * locul scris pe jumătate, nu merită respins: e mai bine să i se spună omului
 * ce n-a mers și să poată drege. De aceea are alt ton — nu e un „nu", e un
 * „aproape".
 *
 * MOTIVUL E OPȚIONAL, și numai la ultimele două. Aprobarea n-are ce explica.
 * Când lipsește, nu se tace: se spune pe față că nu s-a specificat niciunul și
 * omul e trimis să ne scrie — altfel ar rămâne cu un „nu" fără nicio ușă.
 *
 * Ce NU spune mesajul de respingere: că odată cu hotărârea s-au șters
 * comentariile, notele și listele. Omului i se spune ce-l privește — că
 * anunțul nu se publică — nu ce am făcut noi prin bază.
 *
 * Motivul intră ca paragraf obișnuit, deci trece prin aceeași ieșire ca restul
 * textelor din șablon — scăpat cu htmlspecialchars în varianta HTML. Nimeni nu
 * poate strecura etichete în e-mailul altcuiva prin caseta de motiv.
 */
function emailModerareAnunt(
    string $catre,
    string $prenume,
    string $titluEveniment,
    string $adresaEveniment,
    string $hotarare,
    string $motiv = ''
): bool {
    /* ------------------------ mai are de lucru ------------------------ */

    if ($hotarare === 'editare') {
        $paragrafe = [
            'Evenimentul tău, „' . $titluEveniment . '”, e foarte aproape de publicare! Mai sunt doar câteva mici detalii de pus la punct înainte să apară pe site.',
        ];

        if ($motiv !== '') {
            $paragrafe[] = 'Iată ce ar fi de ajustat:';
            $paragrafe[] = $motiv;
        } else {
            $paragrafe[] = 'Nu s-a menționat un motiv anume. Dacă ai întrebări, scrie-ne oricând!';
        }

        $paragrafe[] = 'Mergi pe pagina evenimentului, apasă butonul „Editează” și retrimite-l. Îl vom verifica imediat!';

        return trimiteEmail(
            $catre,
            'Mai e puțin! „' . $titluEveniment . '” are nevoie de câteva ajustări',
            [
                'salut'     => 'Bună, ' . $prenume . '!',
                'paragrafe' => $paragrafe,
                'buton'     => ['text' => 'Vezi și editează anunțul', 'href' => $adresaEveniment],
                'link_gol'  => $adresaEveniment,
                'incheiere' => 'Toate datele completate sunt la locul lor, așa că poți face modificările foarte rapid.',
            ]
        );
    }

    if ($hotarare === 'aprobat') {
        $blocuri = [
            'salut'     => 'Bună, ' . $prenume . '!',
            'paragrafe' => [
                'Vești bune! Anunțul tău, „' . $titluEveniment . '”, a fost aprobat și este deja vizibil pe site. Membrii comunității îl pot descoperi și se pot înscrie chiar acum!',
                'Dacă decizi să îl editezi ulterior, anunțul va trece din nou printr-o scurtă verificare înainte de a fi republicat, pentru a păstra informațiile mereu clare și sigure.',
            ],
            'buton'     => ['text' => 'Vezi anunțul tău', 'href' => $adresaEveniment],
            'link_gol'  => $adresaEveniment,
            'incheiere' => 'Îți mulțumim din suflet că pui lucrurile în mișcare și creezi experiențe faine pentru oraș!',
        ];

        return trimiteEmail($catre, 'Vești bune! „' . $titluEveniment . '” a fost aprobat', $blocuri);
    }

    $paragrafe = [
        'Îți mulțumim că ai vrut să împărtășești „' . $titluEveniment . '” cu noi! Din păcate, de această dată anunțul tău nu a fost aprobat pentru publicare pe site. Totuși, el rămâne vizibil în continuare pe pagina lui, unde îl poți consulta oricând.',
    ];

    if ($motiv !== '') {
        $paragrafe[] = 'Motivul, așa cum a fost scris:';
        $paragrafe[] = $motiv;
    } else {
        /**
         * Fără motiv, mesajul nu tace și nu se preface.
         *
         * Un „nu" fără nicio explicație și fără nicio ușă e cel mai prost fel
         * de a închide o discuție. Așa, omul știe și că n-a fost scris nimic,
         * și pe unde să întrebe.
         */
        $paragrafe[] = 'Nu a fost adăugată o explicație detaliată, însă dacă ai orice întrebare sau dorești mai multe clarificări, scrie-ne oricând ... suntem bucuroși să te ajutăm!';
    }

    $paragrafe[] = 'Dacă vrei să reajustezi anunțul, îl poți edita și retrimite oricând! De îndată ce salvezi o modificare, noi îl vom reanaliza cu mare drag.';

    $blocuri = [
        'salut'     => 'Bună, ' . $prenume . '!',
        'paragrafe' => $paragrafe,
        'buton'     => ['text' => 'Vezi anunțul tău', 'href' => $adresaEveniment],
        'link_gol'  => $adresaEveniment,
    ];

    return trimiteEmail($catre, 'Despre anunțul tău, „' . $titluEveniment . '”', $blocuri);
}

/**
 * „Cineva ți-a scris" — înștiințarea de sub un anunț.
 *
 * DOUĂ MESAJE, o singură funcție, fiindcă poartă același lucru: vorbele
 * cuiva, adresa la care se răspunde și un buton care duce fix acolo. Ce
 * deosebește:
 *
 *   'comentariu' — cineva a scris sub anunțul TĂU. Îl primește organizatorul;
 *   'raspuns'    — cineva a răspuns comentariului TĂU. Îl primește autorul
 *                  comentariului pe care s-a apăsat, oricât de adânc ar fi.
 *
 * Cine primește (unul singur de fiecare dată) și cine nu primește niciodată se
 * hotărăsc în omDeInstiintatLaComentariu() din inc/comentarii.php — inclusiv
 * bifa din setări. Aici se scrie doar mesajul.
 *
 * TEXTUL COMENTARIULUI intră întreg în e-mail, ca citat. Nu e o risipă: un
 * „ai primit un răspuns, intră pe site" e chiar felul de mesaj pe care nu-l
 * mai deschide nimeni a doua oară. Cu vorbele în față, omul știe pe loc dacă
 * are ce răspunde.
 *
 * Textul trece prin blocul de citat al șablonului, deci e scăpat cu h() în
 * varianta HTML, ca orice altceva. Nimeni nu poate strecura etichete în
 * e-mailul altcuiva printr-un comentariu.
 */
function emailComentariuNou(
    string $catre,
    string $prenume,
    string $fel,
    string $numeAutor,
    string $titluEveniment,
    string $textComentariu,
    string $adresaComentariu
): bool {
    $eRaspuns = $fel === 'raspuns';

    if ($eRaspuns) {
        $subiect   = $numeAutor . ' ți-a răspuns la comentariu!';
        $paragrafe = [
            $numeAutor . ' ți-a lăsat un răspuns la comentariul tău de la „' . $titluEveniment . '”.',
        ];
        $incheiere = 'Dacă dorești să nu mai primești aceste notificări prin e-mail, le poți dezactiva oricând din setările contului. Comentariile vor rămâne în continuare pe site.';
    } else {
        $subiect   = 'Comentariu nou la „' . $titluEveniment . '”';
        $paragrafe = [
            $numeAutor . ' a lăsat un comentariu la evenimentul tău, „' . $titluEveniment . '”.',
        ];
        $incheiere = 'Primești acest e-mail deoarece ești organizatorul evenimentului. Poți gestiona sau opri aceste notificări din setările contului tău.';
    }

    $blocuri = [
        'salut'     => 'Bună, ' . $prenume . '!',
        'paragrafe' => $paragrafe,
        'citat'     => ['cine' => $numeAutor, 'text' => $textComentariu],
        'buton'     => [
            'text' => $eRaspuns ? 'Vezi răspunsul' : 'Vezi și răspunde',
            'href' => $adresaComentariu,
        ],
        'link_gol'  => $adresaComentariu,
        'incheiere' => $incheiere,
    ];

    return trimiteEmail($catre, $subiect, $blocuri);
}

/**
 * Vestea că cineva ți-a scris o părere pe profil.
 *
 * NUMAI PENTRU CE E SCRIS. Stelele rămân anonime și tăcute — vezi
 * omDeInstiintatLaFeedback() din inc/evaluari.php, unde stă regula întreagă.
 * Aici, dimpotrivă, numele se spune limpede: o părere scrisă vine semnată, iar
 * un mesaj care ar ascunde cine a scris-o n-ar face decât să trimită omul pe
 * profil ca să afle ceea ce oricum scrie acolo.
 *
 * TEXTUL SE PUNE ÎN MESAJ, ca la un comentariu nou. E ceva ce cineva a ales să
 * spună despre om, în văzul tuturor; ascunzându-l în spatele unui buton l-am fi
 * făcut să pară mai grav decât e. Cine vrea să răspundă are butonul.
 *
 * Butonul duce pe PROFILUL LUI, nu pe al celui care a scris: acolo stă părerea,
 * lângă celelalte, iar de acolo se vede și cum arată pentru toată lumea.
 */
function emailFeedbackNou(
    string $catre,
    string $prenume,
    string $numeAutor,
    string $titluEveniment,
    string $textParerii,
    string $adresaProfil
): bool {
    return trimiteEmail($catre, $numeAutor . ' ți-a lăsat o recenzie pe profil', [
        'salut'     => 'Bună, ' . $prenume . '!',
        'paragrafe' => [
            $numeAutor . ' ți-a scris câteva gânduri pe profil după evenimentul „' . $titluEveniment . '”.',
        ],
        'citat'     => ['cine' => $numeAutor, 'text' => $textParerii],
        'buton'     => ['text' => 'Vezi impresia pe profil', 'href' => $adresaProfil],
        'link_gol'  => $adresaProfil,
        'incheiere' => 'Evaluările prin steluțe rămân mereu anonime, așa că te anunțăm doar când primești mesaje scrise. Dacă dorești să dezactivezi aceste notificări, o poți face oricând din setările contului tău.',
    ]);
}

/* ================== VEȘTILE DIN ZONA DE ADMINISTRARE ================= */

/**
 * Motivul scris de omul casei, gata de pus într-un e-mail.
 *
 * Toate cele patru vești de mai jos au aceeași croială: se poate da un motiv,
 * și de cele mai multe ori nu se dă. Fraza pentru „n-a scris nimeni nimic"
 * trebuie să fie ACEEAȘI peste tot — altfel patru locuri ar fi ajuns să spună
 * patru lucruri ușor diferite despre aceeași tăcere.
 *
 * Întoarce paragrafele care se lipesc la coada mesajului.
 */
function paragrafeleMotivului(string $motiv, string $intrebare = 'Iată explicația'): array
{
    $motiv = trim($motiv);

    if ($motiv === '') {
        return [
            'Nu a fost adăugată o explicație detaliată, însă dacă ai orice întrebare sau nelămurire, scrie-ne oricând, suntem bucuroși să te ajutăm!',
        ];
    }

    return [$intrebare . ':', $motiv];
}

/**
 * Vestea că un comentariu a fost șters de un om al casei.
 *
 * Se trimite ORICUM s-ar fi dus comentariul — șters de tot sau golit (cel cu
 * răspunsuri sub el). Pentru omul care l-a scris, cele două înseamnă același
 * lucru: ce a scris nu se mai citește.
 *
 * Textul comentariului NU se pune în mesaj. Dacă a fost șters fiindcă era
 * urât, retrimiterea lui pe e-mail ar fi fost o a doua trecere a aceluiași
 * lucru — de data asta în cutia poștală a omului, unde rămâne pentru totdeauna.
 */
function emailComentariuSters(string $catre, string $prenume, string $titluEveniment,
                              string $motiv = ''): bool
                              {
                                  global $config;
                                  $site = rtrim((string) ($config['url_site'] ?? ''), '/');

                                  $paragrafe = array_merge(
                                      [
                                          'Un comentariu scris de tine la anunțul „' . $titluEveniment . '" a fost '
                                          . 'șters de echipa PulsulOrasului.Ro.',
                                      ],
                                      paragrafeleMotivului($motiv)
                                  );

                                  return trimiteEmail($catre, 'Un comentariu de-al tău a fost șters', [
                                      'salut'     => 'Bună, ' . $prenume . '!',
                                      'paragrafe' => $paragrafe,
                                      'buton'     => ['text' => 'Scrie-ne', 'href' => $site . '/contact.php'],
                                      'incheiere' => 'Contul tău nu e atins: poți scrie mai departe pe site.',
                                  ]);
                              }

/**
 * Vestea că poza de profil a fost ștearsă de un om al casei.
 *
 * Se spune limpede că poza poate fi încărcată la loc: altfel omul rămâne cu
 * gândul că i s-a luat ceva pentru totdeauna, când de fapt i s-a cerut, tăcut,
 * să pună alta.
 */
function emailPozaStearsa(string $catre, string $prenume, string $motiv = ''): bool
{
    global $config;
    $site = rtrim((string) ($config['url_site'] ?? ''), '/');

    $paragrafe = array_merge(
        ['Te informăm că fotografia ta de profil de pe PulsulOrasului.Ro a fost îndepărtată de către echipa noastră.'],
        paragrafeleMotivului($motiv)
    );

    $paragrafe[] = 'Te invităm să încarci o nouă fotografie oricând dorești, direct din pagina profilului tău.';

    return trimiteEmail($catre, 'Actualizare privind fotografia ta de profil', [
        'salut'     => 'Bună, ' . $prenume . '!',
        'paragrafe' => $paragrafe,
        'buton'     => ['text' => 'Alege o altă fotografie', 'href' => $site . '/poza.php'],
        'incheiere' => 'Restul datelor și activitatea din contul tău rămân complet neatinse.',
    ]);
}

/**
 * Vestea că un cont a fost suspendat.
 *
 * NU se trimite și la ridicarea suspendării, dinadins: acolo omul intră pur și
 * simplu în cont și merge mai departe, iar un e-mail care spune „acum poți din
 * nou" ar fi amintit, fără rost, de o pedeapsă încheiată.
 */
function emailContSuspendat(string $catre, string $prenume, string $motiv = ''): bool
{
    global $config;
    $site = rtrim((string) ($config['url_site'] ?? ''), '/');

    $paragrafe = array_merge(
        [
            'Te informăm că accesul la contul tău de pe PulsulOrasului.Ro a fost temporar suspendat de către echipa noastră. În această perioadă, autentificarea în cont nu va fi posibilă.',
        ],
        paragrafeleMotivului($motiv)
    );

    return trimiteEmail($catre, 'Informații importante privind contul tău', [
        'salut'     => 'Bună, ' . $prenume . '!',
        'paragrafe' => $paragrafe,
        'atentie'   => 'Dacă consideri că este vorba despre o neînțelegere, te rugăm să ne scrii ... citim cu atenție fiecare mesaj și căutăm mereu cea mai bună soluție.',
        'buton'     => ['text' => 'Scrie-ne pentru clarificări', 'href' => $site . '/contact.php'],
        'incheiere' => 'Evenimentele create de tine și istoricul participărilor rămân salvate în siguranță.',
    ]);
}

/**
 * Vestea că o dorință a fost aprobată sau respinsă.
 *
 * Până acum omul nu afla nimic: vedea singur, dacă trecea pe prima pagină în
 * cele șapte zile cât stă pe tablă. La respingere nu afla niciodată — dorința
 * lui pur și simplu nu apărea, iar el nu știa dacă e citită sau uitată.
 *
 * La APROBARE nu se cere niciun motiv, evident, dar nici nu se primește: cine
 * ar scrie unul acolo ar scrie de fapt o vorbă bună, iar aceea n-are ce căuta
 * într-un e-mail de bifă.
 */
function emailDorintaHotarata(string $catre, string $prenume, string $dorinta,
                              bool $aprobat, string $motiv = '', int $zile = 7): bool
                              {
                                  global $config;
                                  $site = rtrim((string) ($config['url_site'] ?? ''), '/');

                                  if ($aprobat) {
                                      return trimiteEmail($catre, 'Vești bune! Dorința ta a ajuns pe tablă', [
                                          'salut'     => 'Bună, ' . $prenume . '!',
                                          'paragrafe' => [
                                              'Am citit dorința ta și am publicat-o cu drag! Dorința ta este acum vizibilă pe tabla de pe prima pagină, unde o poate descoperi întreaga comunitate.',
                                              'Va rămâne pe tablă timp de ' . $zile . ' zile. Dacă cineva se inspiră din ideea ta și decide să organizeze o ieșire, vei vedea noul eveniment chiar pe prima pagină.',
                                          ],
                                          'citat'     => ['cine' => $prenume, 'text' => $dorinta],
                                          'buton'     => ['text' => 'Vezi tabla de dorințe', 'href' => $site . '/'],
                                          'incheiere' => 'După cele ' . $zile . ' zile, dorința aceasta va fi înlăturată de pe tablă.',
                                      ]);
                                  }

                                  $paragrafe = array_merge(
                                      [
                                          'Îți mulțumim că ai împărtășit ideea ta cu noi! Am citit dorința trimisă, însă de această dată nu am putut să o afișăm pe tablă.',
                                      ],
                                      paragrafeleMotivului($motiv)
                                  );

                                  $paragrafe[] = 'Te încurajăm să încerci din nou! Poți adăuga oricând o altă dorință pe tablă, fără nicio perioadă de așteptare.';

                                  return trimiteEmail($catre, 'Informații despre dorința ta', [
                                      'salut'     => 'Bună, ' . $prenume . '!',
                                      'paragrafe' => $paragrafe,
                                      'citat'     => ['cine' => $prenume, 'text' => $dorinta],
                                      'buton'     => ['text' => 'Adaugă o altă dorință', 'href' => $site . '/'],
                                      'incheiere' => 'Dacă simți că a fost vorba despre o neînțelegere, ne poți scrie oricând prin pagina de contact.',
                                  ]);
                              }
