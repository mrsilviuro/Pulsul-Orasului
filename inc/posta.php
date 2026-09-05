<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — POȘTA: cum PLEACĂ un mesaj de pe server.
 *
 * inc/email.php spune CE scrie într-un mesaj; fișierul ăsta spune pe ce drum
 * iese din casă. Erau amândouă acolo cât drumul era unul singur — funcția
 * mail() a serverului, două rânduri. De când e SMTP cu autentificare, „cum
 * pleacă" are destule de spus cât să stea deoparte.
 *
 * DE CE SMTP ȘI NU mail(). Nu fiindcă mail() ar fi „rea" — pe o găzduire
 * obișnuită ea predă mesajul chiar aceluiași server de poștă la care ne-am
 * conecta noi. Deosebirile care contează sunt trei, și toate trei sunt reale:
 *
 *   1. SEMNĂTURA DKIM. Pe cele mai multe găzduiri de tip cPanel, serverul
 *      semnează cu DKIM doar ce intră pe ușa din față, adică prin SMTP cu
 *      parolă. Un mesaj predat de PHP ca utilizator al site-ului intră pe ușa
 *      din dos și pleacă NESEMNAT — iar un mesaj nesemnat, azi, e cules de
 *      filtrele Gmail și Outlook aproape din reflex. Asta e, de departe, cea
 *      mai mare parte din „zvonul" cu spamul: nu funcția PHP e vinovată, ci
 *      semnătura pe care ea n-o primește.
 *   2. AFLI CÂND N-A MERS. mail() întoarce `true` dacă serverul local a LUAT
 *      mesajul din mână — nu dacă l-a și dus. Cu SMTP primim răspunsul
 *      serverului, cu codul și vorba lui („mailbox full", „over quota", „relay
 *      denied"). Până acum, un mesaj căzut se vedea exact nicăieri.
 *   3. ADRESA DE PLECARE E SPUSĂ PE FAȚĂ, nu strecurată printr-un „-f" pe care
 *      unele găzduiri îl ignoră. SPF se uită tocmai la ea.
 *
 * CE NU REZOLVĂ. Dacă în DNS-ul domeniului nu stau SPF, DKIM și DMARC așa cum
 * trebuie, SMTP nu ajută cu nimic — mesajele ajung tot în „Spam", doar că acum
 * știm de ce. Ăsta e ordinea de făcut lucrurile: întâi DNS-ul, apoi drumul.
 *
 * BIBLIOTECA NU E ÎN REPO, dinadins: e cod străin, se ține la zi de altcineva,
 * și se încarcă de mână în `PHPMailer/` la rădăcina site-ului. De aceea totul
 * de aici întreabă ÎNTÂI dacă e acolo (vezi deCeNuMergeSmtp) și, dacă nu e,
 * spune de ce și lasă mesajul să plece pe drumul vechi. Un site care nu mai
 * poate confirma un cont fiindcă lipsește un dosar e mai rău decât unul ale
 * cărui mesaje ajung în „Spam".
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * Unde se caută biblioteca.
 *
 * Locul ei e `PHPMailer/src/` la rădăcina site-ului. Celelalte două căi sunt
 * pentru arhiva descărcată de pe GitHub și dezarhivată fără să i se scoată
 * dosarul din capăt — se întâmplă des, iar altfel omul ar fi rămas cu un „nu
 * găsesc biblioteca" despre un dosar pe care îl VEDE acolo.
 */
function caiPhpMailer(): array
{
    $radacina = dirname(__DIR__);

    return [
        $radacina . '/PHPMailer/src',
        $radacina . '/PHPMailer/PHPMailer/src',
        $radacina . '/PHPMailer/PHPMailer-master/src',
    ];
}

/** Dosarul în care chiar stă biblioteca, sau '' dacă nu e nicăieri. */
function dosarulPhpMailer(): string
{
    foreach (caiPhpMailer() as $cale) {
        if (is_file($cale . '/PHPMailer.php')
            && is_file($cale . '/SMTP.php')
            && is_file($cale . '/Exception.php')) {
            return $cale;
        }
    }

    return '';
}

/**
 * Aduce clasele în memorie. Întoarce false dacă nu sunt de unde.
 *
 * Fără autoloader și fără Composer — site-ul n-are niciunul, și n-are de ce.
 * Trei `require_once` fac exact ce trebuie, iar ordinea contează: `Exception`
 * și `SMTP` sunt cerute de `PHPMailer`.
 */
function incarcaPhpMailer(): bool
{
    if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class, false)) {
        return true;
    }

    $dosar = dosarulPhpMailer();

    if ($dosar === '') {
        return false;
    }

    require_once $dosar . '/Exception.php';
    require_once $dosar . '/SMTP.php';
    require_once $dosar . '/PHPMailer.php';

    return class_exists(\PHPMailer\PHPMailer\PHPMailer::class, false);
}

/** Setările de conectare, din config.php. */
function setarileSmtp(): array
{
    global $config;

    return [
        'gazda'    => trim((string) ($config['smtp_gazda']  ?? '')),
        'port'     => (int)         ($config['smtp_port']   ?? 465),
        'user'     => trim((string) ($config['smtp_user']   ?? '')),
        'parola'   =>      (string) ($config['smtp_parola'] ?? ''),
        // 'smtps' = TLS de la prima vorbă (portul 465, cel din panoul găzduirii);
        // 'tls'   = conexiune curată, ridicată la TLS cu STARTTLS (portul 587).
        'criptare' => trim((string) ($config['smtp_criptare'] ?? 'smtps')),
    ];
}

/**
 * De ce NU se poate trimite prin SMTP — o vorbă scrisă pentru un om, sau '' dacă
 * totul e la locul lui.
 *
 * O ÎNTREBARE, UN RĂSPUNS, UN SINGUR LOC. O citesc trei: alegerea drumului din
 * trimiteEmail(), rândul de stare din admin.php (ca omul de casă să vadă fără
 * SSH dacă poșta merge) și proba din teste/test-posta.php. Scrisă de trei ori,
 * a treia ar fi rămas în urmă.
 */
function deCeNuMergeSmtp(): string
{
    $s = setarileSmtp();

    if ($s['gazda'] === '' || $s['user'] === '' || $s['parola'] === '') {
        return 'Nu sunt trecute datele de conectare în inc/config.php '
             . '(smtp_gazda, smtp_user, smtp_parola).';
    }

    if (dosarulPhpMailer() === '') {
        return 'Nu găsesc biblioteca PHPMailer. Locul ei e „PHPMailer/src/" '
             . 'la rădăcina site-ului.';
    }

    if (!incarcaPhpMailer()) {
        return 'Am găsit dosarul PHPMailer, dar clasele nu s-au încărcat. '
             . 'Uită-te dacă fișierele din „src/" sunt întregi.';
    }

    return '';
}

/**
 * Se potrivește contul cu care ne conectăm cu adresa de pe mesaj?
 *
 * NU E O PIEDICĂ, E O STRÂMBĂTATE. Mesajul pleacă și așa, dar te conectezi cu o
 * adresă și scrii „From" cu alta — iar DMARC tocmai asta cerne: „adresa care
 * scrie e aceeași cu cea care a dovedit că are voie?". Nepotrivite, o parte din
 * servere pun mesajul deoparte, iar câteva îl resping de-a dreptul.
 *
 * De aceea nu intră în deCeNuMergeSmtp(): aia răspunde la „se poate trimite?",
 * iar aici se poate. Se scrie doar pe panoul din admin, ca omul de casă să vadă
 * ce a încurcat, fără să i se închidă poșta pentru o strâmbătate.
 */
function adreseleSePotrivesc(): bool
{
    global $config;

    $user      = mb_strtolower(trim((string) ($config['smtp_user'] ?? '')));
    $expeditor = mb_strtolower(trim((string) ($config['email_expeditor'] ?? '')));

    // Nescrise încă: nu e o nepotrivire, e o lipsă — o spune cealaltă funcție.
    if ($user === '' || $expeditor === '') {
        return true;
    }

    return $user === $expeditor;
}

/**
 * Pe ce drum pleacă mesajele ACUM: 'smtp', 'mail' sau 'fisier'.
 *
 * Aici se desface 'auto' în ce înseamnă el de fapt, iar întrebarea e pusă
 * într-un singur loc fiindcă o pun trei: trimiteEmail() (ca să știe pe unde s-o
 * ia), asteaptaRandulUrmator() (ca să nu frâneze degeaba când nu pleacă nimic)
 * și rândul de stare din admin.php.
 *
 * NU spune dacă drumul ales chiar merge — SMTP cerut și nefolosibil se vede din
 * deCeNuMergeSmtp(). Spune ce s-a cerut, desfăcut.
 */
function drumulPostei(): string
{
    global $config;

    $metoda = (string) ($config['email_metoda'] ?? 'auto');

    if ($metoda !== 'auto') {
        return $metoda;
    }

    // În dezvoltare, fișier: în XAMPP nu există server de poștă, iar pe mașina
    // de lucru nici n-are cine primi. În rest, SMTP dacă e cu ce.
    return !empty($config['dezvoltare'])
        ? 'fisier'
        : (deCeNuMergeSmtp() === '' ? 'smtp' : 'mail');
}

/* ============================== POȘTAȘUL ============================== */

/**
 * Poștașul, unul singur pe toată cererea, cu conexiunea ținută deschisă.
 *
 * DE CE UNUL SINGUR. La newsletter pleacă zeci de mesaje unul după altul, iar
 * fiecare `new PHPMailer` ar fi însemnat o conexiune nouă: alt salut, altă
 * parolă, altă strângere de mână TLS. Cincizeci de conectări într-un minut, de
 * la aceeași adresă, arată din afară exact ca cineva care încearcă parole — și
 * sunt găzduiri care închid ușa pentru asta. Cu `SMTPKeepAlive`, conexiunea se
 * deschide o dată și rămâne.
 *
 * DE AICI DECURGE PARTEA PERICULOASĂ: un poștaș folosit de două ori ține minte
 * ce a dus prima oară. PHPMailer NU golește singur lista de destinatari după
 * send(), deci al doilea mesaj ar pleca și către primul om. Nu e o închipuire:
 * e greșeala clasică a bibliotecii ăsteia, și e cea mai urâtă cu putință — omul
 * primește scrisoarea altcuiva. De aceea golirea se face la ÎNCEPUTUL fiecărei
 * trimiteri, nu la sfârșitul celei dinainte: una uitată la sfârșit nu se vede
 * niciodată, una uitată la început strică chiar mesajul care se scrie acum.
 * O păzește o probă, în teste/test-posta.php.
 */
function postasul(): ?\PHPMailer\PHPMailer\PHPMailer
{
    static $postas = null;

    if ($postas !== null) {
        return $postas;
    }

    if (!incarcaPhpMailer()) {
        return null;
    }

    $s = setarileSmtp();

    // `true` = biblioteca aruncă excepții în loc să întoarcă tăcut false.
    // Tocmai pentru vorba dinăuntrul lor am trecut la SMTP.
    $postas = new \PHPMailer\PHPMailer\PHPMailer(true);

    $postas->isSMTP();
    $postas->Host        = $s['gazda'];
    $postas->Port        = $s['port'];
    $postas->SMTPAuth    = true;
    $postas->Username    = $s['user'];
    $postas->Password    = $s['parola'];
    $postas->SMTPSecure  = $s['criptare'] === 'tls'
        ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
        : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;

    $postas->SMTPKeepAlive = true;
    $postas->CharSet       = 'UTF-8';
    $postas->Encoding      = 'quoted-printable';

    /**
     * Zece secunde, nu treizeci (cât e implicit).
     *
     * Un mesaj se trimite ÎN TIMPUL unei cereri a omului: la înscriere, la
     * recuperarea parolei. Dacă serverul de poștă tace, omul se uită la o
     * pagină care se învârte. Mai bine cade repede și se scrie în log — contul
     * s-a făcut oricum, iar mesajul se poate cere din nou.
     */
    $postas->Timeout = 10;

    // Ce program a trimis mesajul. Gol, nu „PHPMailer 7.1.1": versiunea
    // bibliotecii scrisă în fiecare mesaj e o vorbă spusă degeaba oricui
    // adună ținte după biblioteci vechi.
    $postas->XMailer = ' ';

    return $postas;
}

/**
 * Închide conexiunea, dacă a fost deschisă.
 *
 * Se cheamă la capătul unei rulări de cron. Într-o cerere obișnuită nu e
 * nevoie — PHP închide oricum totul la sfârșit — dar un cron care ține minute
 * bune n-are de ce să lase o legătură atârnând după ce a terminat.
 */
function inchidePostasul(): void
{
    static $postas = null;

    $postas = $postas ?? postasul();

    if ($postas !== null) {
        $postas->smtpClose();
    }
}

/**
 * Trimite un mesaj gata compus, prin SMTP. Întoarce true dacă serverul l-a luat.
 *
 * Anteturile pe care le socotea singur inc/email.php — Message-ID, Date,
 * MIME-Version, granița dintre variante — NU se mai scriu de mână: le face
 * biblioteca, și le face mai bine. Ce rămâne al nostru sunt cele care spun ceva
 * despre mesajul ăsta anume: „Auto-Submitted", „List-Unsubscribe".
 */
function trimitePrinSmtp(
    string $catre,
    string $subiect,
    array  $corp,
    string $expeditor,
    string $numeExpeditor,
    string $raspunsCatre,
    array  $anteturiInPlus = []
): bool {
    $postas = postasul();

    if ($postas === null) {
        error_log('PulsulOrasului: PHPMailer nu e încărcat, mesajul nu pleacă prin SMTP.');
        return false;
    }

    try {
        /* Golirea de la începutul trimiterii — vezi lămurirea de la postasul(). */
        $postas->clearAllRecipients();
        $postas->clearReplyTos();
        $postas->clearCustomHeaders();
        $postas->clearAttachments();

        $postas->setFrom($expeditor, $numeExpeditor, false);

        /**
         * Adresa din PLIC, nu doar cea din antet.
         *
         * Ține locul lui „-f" de la mail(): SPF se uită la ea, iar mesajele
         * care nu se pot livra se întorc tot acolo. Fără ea, unele găzduiri
         * pun adresa contului de găzduire, care nu e pe domeniul nostru — și
         * tocmai nepotrivirea aia e ce caută verificarea SPF.
         */
        $postas->Sender = $expeditor;

        $postas->addReplyTo($raspunsCatre);
        $postas->addAddress($catre);

        $postas->Subject = $subiect;
        $postas->isHTML(true);
        $postas->Body    = $corp['html'];
        $postas->AltBody = $corp['text'];

        /**
         * Spune programelor de e-mail că mesajul e trimis de un program, nu de
         * un om: fără el, un răspuns automat de tip „sunt în concediu" ar veni
         * înapoi la noi, iar în cazuri nefericite se ajunge la o buclă între
         * cele două servere.
         */
        $postas->addCustomHeader('Auto-Submitted', 'auto-generated');
        $postas->addCustomHeader('X-Auto-Response-Suppress', 'All');

        foreach ($anteturiInPlus as $nume => $valoare) {
            $nume    = preg_replace('/[^A-Za-z0-9-]/', '', (string) $nume);
            $valoare = str_replace(["\r", "\n"], '', (string) $valoare);

            if ($nume !== '' && $valoare !== '') {
                $postas->addCustomHeader($nume, $valoare);
            }
        }

        return $postas->send();

    } catch (\Throwable $e) {
        /**
         * AICI E CÂȘTIGUL CEL MAI MARE AL TRECERII LA SMTP.
         *
         * mail() spunea doar „n-a mers", și nici măcar asta de cele mai multe
         * ori. Serverul de poștă, în schimb, spune de ce: „over quota", „relay
         * access denied", „authentication failed", „too many messages". Vorba
         * aia e singurul fel de a afla că ai depășit plafonul găzduirii ÎNAINTE
         * să ți se închidă contul.
         */
        error_log('PulsulOrasului: SMTP a dat greș pentru „' . $subiect . '": '
                . ($postas->ErrorInfo !== '' ? $postas->ErrorInfo : $e->getMessage()));

        return false;
    }
}

/* ========================= PASUL DE MELC ============================== */

/**
 * Câte mesaje pe minut duce găzduirea.
 *
 * NU E O ALEGERE DE-A NOASTRĂ, e un plafon scris în panoul găzduirii, și se
 * schimbă odată cu ea — de aceea stă în config.php, nu aici. Zero sau mai puțin
 * înseamnă „fără frână", pentru cine ajunge vreodată pe un server al lui.
 */
function emailuriPeMinut(): int
{
    global $config;

    return (int) ($config['email_pe_minut'] ?? 10);
}

/**
 * Ține pasul cerut de găzduire, între două mesaje dintr-o trimitere în serie.
 *
 * SE CHEAMĂ DOAR DIN CRONURILE CARE TRIMIT ÎN SERIE — mementoul de dinaintea
 * unui eveniment (inc/amintiri.php) și mulțumirea de după (inc/multumiri.php)
 * —, niciodată dintr-un mesaj obișnuit: la o confirmare de cont nu există
 * „mesajul dinainte", iar o pauză acolo ar face omul să se uite la o pagină
 * care se învârte degeaba.
 *
 * NU E O POLITEȚE, E O APĂRARE. Găzduirea numără mesajele pe minut și pe ceas;
 * cine sare plafonul nu primește un avertisment, ci i se oprește poșta — pentru
 * tot ce urmează în ora aceea, inclusiv confirmările de cont și recuperările de
 * parolă ale unor oameni care n-au nicio treabă cu ce tocmai s-a trimis. De
 * aceea frâna stă aici, în drumul de plecare, nu în fiecare loc care trimite.
 *
 * CE NU E FRÂNAT, ȘI DE CE. Trei locuri trimit în serie DINTR-O CERERE WEB:
 * anularea unui eveniment (api/anuleaza-eveniment.php), anunțul important al
 * organizatorului (api/comentarii.php) și vestea către urmăritori
 * (inc/urmariri.php). Acolo frâna ar ține omul cu ochii pe o pagină care se
 * învârte câte șase secunde de fiecare înscris — la treizeci de oameni, trei
 * minute. Deci NU se cheamă, iar la un eveniment mare plafonul se poate sări.
 * Adevărata dezlegare e o coadă și un cron, ca la restul; până atunci, asta e
 * scris aici ca să se știe, nu ca să pară rezolvat.
 *
 * Se socotește din CLIPA MESAJULUI DINAINTE, nu dintr-o pauză fixă: dacă
 * trimiterea a durat ea însăși două secunde, se mai așteaptă doar patru.
 */
function asteaptaRandulUrmator(): void
{
    static $ultimul = null;

    $peMinut = emailuriPeMinut();

    if ($peMinut <= 0) {
        return;
    }

    /**
     * CÂND NU PLEACĂ NIMIC, NU SE AȘTEAPTĂ NIMIC.
     *
     * Frâna e pusă pentru serverul de poștă al găzduirii. Cu mesajele scrise în
     * fișier — în dezvoltare, și în toate probele — nu există niciun server de
     * apărat, iar o probă cu douăzeci de mesaje ar fi stat două minute uitându-se
     * la tavan. Ceea ce, în practică, înseamnă o probă pe care n-o mai rulează
     * nimeni.
     */
    if (drumulPostei() === 'fisier') {
        return;
    }

    $pasul = 60.0 / $peMinut;   // secunde între două mesaje

    if ($ultimul !== null) {
        $trecut = microtime(true) - $ultimul;

        if ($trecut < $pasul) {
            usleep((int) round(($pasul - $trecut) * 1_000_000));
        }
    }

    $ultimul = microtime(true);
}
