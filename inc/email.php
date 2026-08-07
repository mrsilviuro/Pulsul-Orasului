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
function trimiteEmail(string $catre, string $subiect, array $blocuri): bool
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

    $h[] = '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" '
         . 'style="width:600px;max-width:600px;">';

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

        // Font cu lățime fixă: la o parolă tastată de mână, e important să nu
        // se confunde literele între ele.
        $h[] = '<div style="font-family:Consolas,Menlo,Monaco,\'Courier New\',monospace;'
             . 'font-size:32px;font-weight:bold;letter-spacing:7px;color:' . $text . ';'
             . 'line-height:1.2;">' . h($blocuri['cod']['valoare']) . '</div>';

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
    $h[] = 'Ai primit mesajul ăsta pentru că cineva a folosit adresa ta pe '
         . '<a href="' . h($site) . '" style="color:' . $textMoale . ';">pulsulorasului.ro</a>.';
    $h[] = '<br />Evenimente locale, sport, cultură și tot ce mișcă în oraș.';
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
    $t[] = 'Ai primit mesajul ăsta pentru că cineva a folosit adresa ta pe';
    $t[] = $site;

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
        'incheiere' => 'Dacă nu tu ai cerut contul, nu trebuie să faci nimic — '
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
            'Imediat după ce intri, îți alegi o parolă nouă — până atunci nu poți face '
            . 'altceva în cont.',
        ],
        'cod'       => ['eticheta' => 'Parola ta temporară', 'valoare' => $parola],
        'buton'     => ['text' => 'Intră în cont', 'href' => $site . '/login.php'],
        'atentie'   => 'E valabilă ' . $minute . ' de minute și merge o singură dată.',
        'incheiere' => 'Dacă nu tu ai cerut-o, poți lăsa mesajul așa. Parola ta de până acum '
                     . 'nu s-a schimbat și rămâne bună.',
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
        'incheiere' => 'Dacă vrei să intri și cu parolă, nu doar cu Google, o poți pune '
                     . 'oricând din „Ți-ai uitat parola".',
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
            'Ai cerut ștergerea contului tău. Nu am șters încă nimic — mai e nevoie de '
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
                     . 'parola: cineva a ajuns în contul tău. Fără apăsarea de aici, '
                     . 'linkul se stinge singur în ' . $ore . ' ore.',
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
