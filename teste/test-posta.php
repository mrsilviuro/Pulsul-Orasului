<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — POȘTA: pe ce drum pleacă un mesaj.
 *
 * NU CERE NICI BAZA, NICI SERVERUL, și nu trimite niciun e-mail: totul se
 * petrece în memorie. Partea de SMTP se probează fără să se atingă vreun server
 * de poștă — PHPMailer știe să COMPUNĂ mesajul fără să-l și ducă (preSend), iar
 * asta e tocmai ce ne trebuie: ce ne interesează e ce SCRIE în plic și cui i se
 * dă, nu dacă serverul din Roman răspunde.
 *
 * Cum se rulează:
 *     php teste/test-posta.php
 *
 * CE PĂZEȘTE MAI PRESUS DE ORICE: că al doilea mesaj nu pleacă și către omul
 * dintâi. Poștașul e unul singur, cu conexiunea ținută deschisă, iar PHPMailer
 * NU-și golește singur lista de destinatari după send(). E greșeala clasică a
 * bibliotecii, e cea mai urâtă cu putință — omul primește scrisoarea altcuiva —
 * și nu se vede NICĂIERI din afară: mesajele pleacă, logurile spun „trimis",
 * totul pare în regulă.
 */

require_once __DIR__ . '/../inc/posta.php';
require_once __DIR__ . '/../inc/email.php';
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

/* ==================================================================== */
sectiune('găsirea bibliotecii');

/**
 * Biblioteca NU e în repo — e cod străin, încărcat de mână în „PHPMailer/".
 * Deci proba trebuie să spună ceva folositor în amândouă cazurile: și când e
 * acolo, și când nu e. O probă care ar cere-o ar fi picat pe orice mașină pe
 * care încă n-a fost pusă, adică ar fi învățat pe toată lumea s-o sară.
 */
$dosar = dosarulPhpMailer();
$avem  = $dosar !== '';

if (!$avem) {
    echo "PHPMailer nu e instalat aici.\n";
    echo "Descarcă-l de la https://github.com/PHPMailer/PHPMailer și pune-l\n";
    echo "în „PHPMailer/", ", așa încât să existe „PHPMailer/src/PHPMailer.php\".\n";
    echo "Sar peste tot ce ține de SMTP; restul probelor merg mai departe.\n";
} else {
    verifica('dosarul găsit e unul dintre cele știute', true,
        in_array($dosar, caiPhpMailer(), true));
    verifica('clasele se încarcă', true, incarcaPhpMailer());
}

/* ==================================================================== */
sectiune('de ce nu merge SMTP');

/**
 * Vorba asta o citesc TREI locuri — alegerea drumului din trimiteEmail(),
 * rândul de stare din admin.php și proba de față. Trebuie să fie una singură și
 * să spună ceva unui om, nu un cod de eroare.
 */
global $config;

$configVechi = $config;

$config['smtp_gazda'] = $config['smtp_user'] = $config['smtp_parola'] = '';
verifica('fără datele de conectare, spune asta', true,
    str_contains(deCeNuMergeSmtp(), 'inc/config.php'));

$config['smtp_gazda']  = 'mail.exemplu-probe.ro';
$config['smtp_user']   = 'noreply@exemplu-probe.ro';
$config['smtp_parola'] = 'nu-e-o-parola-adevarata';

/* Adresele de pe mesaj sunt ALTE setări decât cele de conectare: `smtp_user`
   spune cu ce cont ne autentificăm, `email_expeditor` ce scrie la „From".
   Trebuie să fie aceeași adresă — vezi lămurirea de la aliniere, mai jos — dar
   sunt două chei, și proba le pune pe amândouă. */
$config['email_expeditor'] = 'noreply@exemplu-probe.ro';
$config['email_raspuns']   = 'contact@exemplu-probe.ro';

verifica('cu datele puse și biblioteca la locul ei, merge',
    $avem ? '' : 'nu-merge', deCeNuMergeSmtp() === '' ? '' : ($avem ? deCeNuMergeSmtp() : 'nu-merge'));

/**
 * Contul de conectare și adresa de pe mesaj trebuie să fie una și aceeași —
 * altfel DMARC vede că scrie una și a dovedit alta. Nu oprește trimiterea (vezi
 * lămurirea de la adreseleSePotrivesc), dar se scrie pe panoul din admin.
 */
verifica('aceeași adresă, se potrivesc', true, adreseleSePotrivesc());

$config['email_expeditor'] = 'altcineva@exemplu-probe.ro';
verifica('adrese deosebite, nu se potrivesc', false, adreseleSePotrivesc());

/* Lipsa nu e o nepotrivire: despre ea vorbește deCeNuMergeSmtp(). */
$config['email_expeditor'] = '';
verifica('lipsa nu se numără ca nepotrivire', true, adreseleSePotrivesc());

$config['email_expeditor'] = 'noreply@exemplu-probe.ro';

/* ==================================================================== */
sectiune('unde a plecat frâna');

/**
 * A STAT AICI o probă pentru asteaptaRandulUrmator(), pauza de șase secunde
 * dintre două mesaje ale unei trimiteri în serie. A plecat odată cu ea: azi nu
 * se mai trimite nimic în serie dintr-o cerere web, iar ritmul îl dă cadența
 * cronului care golește coada (vezi inc/coada.php și teste/test-coada.php).
 *
 * Ce a rămas de păzit AICI e că n-a mai rămas nimic din ea: o funcție moartă
 * lăsată în urmă ar fi fost chemată într-o zi de cineva care crede că frânează
 * ceva.
 */
verifica('frâna de pas chiar a plecat', [false, false],
    [function_exists('asteaptaRandulUrmator'), function_exists('emailuriPeMinut')]);

/* Cifra ei a rămas însă, cu alt rost: câte duce o rulare a cronului. */
$config['emailuri_pe_rulare'] = 8;
verifica('câte duce o rulare vine din config', 8, coadaPeRulare());

/* Un zero pus din greșeală n-are voie să oprească poșta de tot. */
$config['emailuri_pe_rulare'] = 0;
verifica('zero nu înseamnă „niciunul"', 8, coadaPeRulare());

$config['emailuri_pe_rulare'] = 8;

/**
 * DIN CE CHEIE VINE CIFRA, și cât loc mai rămâne.
 *
 * Sunt trei căi spre numărul acela — cheia nouă, cea veche și valoarea din
 * lipsă — iar panoul scria doar cifra. Când ea nu se potrivea cu ce era scris
 * în config, n-avea cum să se lămurească nimeni fără SSH; cel mai des vinovatul
 * e un `inc/config.php` mai vechi decât `config.example.php`, rămas cu
 * `email_pe_minut`.
 */
unset($config['emailuri_pe_rulare'], $config['email_pe_minut']);
verifica('fără nicio cheie, cifra e cea din lipsă', 8, coadaPeRulare());
verifica('și se spune că vine din lipsă',           '', deUndeVineCoadaPeRulare());

$config['email_pe_minut'] = 10;
verifica('cheia veche se citește mai departe',   10, coadaPeRulare());
verifica('și se spune pe nume', 'email_pe_minut', deUndeVineCoadaPeRulare());

$config['emailuri_pe_rulare'] = 8;
verifica('cea nouă bate cheia veche',                 8, coadaPeRulare());
verifica('și se spune care a câștigat', 'emailuri_pe_rulare', deUndeVineCoadaPeRulare());

/**
 * LOCURILE RĂMASE ÎNTR-UN MINUT pentru ce pleacă pe loc. Zero e cazul care
 * doare: cine își face cont fix în minutul în care cronul își duce teancul
 * primește confirmarea abia la rularea următoare. Nu se pierde nimic — mesajul
 * intră în coadă cu prioritate —, dar omul stă cu ochii pe cutia poștală.
 */
$config['plafon_pe_minut'] = 10;
verifica('opt duse din zece lasă două locuri', 2, locuriRamasePeMinut());

$config['emailuri_pe_rulare'] = 10;
verifica('zece duse din zece nu lasă niciunul', 0, locuriRamasePeMinut());

$config['emailuri_pe_rulare'] = 8;
unset($config['plafon_pe_minut']);
verifica('plafonul are și el o valoare din lipsă', 10, plafonPeMinut());

/* ==================================================================== */
sectiune('ce refuz e definitiv');

/**
 * ÎNTREBAREA DE CARE ATÂRNĂ DACĂ UN MESAJ SE MAI ÎNCEARCĂ.
 *
 * Partea care doare nu e să recunoști adresa moartă — aia e ușoară —, ci să NU
 * iei drept moarte piedicile care sunt despre NOI. Un „550 relay access denied"
 * ori o parolă SMTP schimbată din greșeală ar fi omorât tăcut toată coada la
 * prima trecere, confirmări de cont cu tot, iar semnul ar fi fost o cifră
 * crescută pe un panou pe care nu se uită nimeni.
 */
$refuzuri = [
    'adresa nu există' => [
        "SMTP Error: The following recipients failed: email6@pulsulorasului.ro: "
        . "No Such User Here\n SMTP server error: RCPT TO command failed "
        . "Detail: No Such User Here\n SMTP code: 550",
        true,
    ],
    'parolă SMTP greșită' => [
        'SMTP Error: Could not authenticate. SMTP code: 535',
        false,
    ],
    'cutie plină pe moment' => [
        "SMTP Error: The following recipients failed: ana@exemplu.ro: "
        . "Mailbox busy\n SMTP code: 452",
        false,
    ],
    'conexiunea a picat' => ['SMTP connect() failed.', false],
    'nicio vorbă'        => ['', false],
];

foreach ($refuzuri as $ce => [$vorba, $asteptat]) {
    verifica($ce . ($asteptat ? ' — nu se mai încearcă' : ' — se mai încearcă'),
        $asteptat, esteRefuzDefinitiv($vorba));
}

/* ==================================================================== */
if (!$avem) {
    echo "\n(sar peste plicul și poștașul: n-am biblioteca)\n";
} else {
    /**
     * UN SERVER DE POȘTĂ DE MINCIUNĂ, care spune „da" la tot și ține minte ce
     * i s-a dat.
     *
     * DE CE AȘA, ȘI NU COMPUNÂND MESAJUL DE MÂNĂ ÎN PROBĂ. Prima variantă a
     * probei ăsteia își scria singură golirea destinatarilor și pe urmă verifica
     * dacă… s-au golit. Adică se proba pe sine. Codul adevărat —
     * trimitePrinSmtp() — putea să nu golească nimic, și proba trecea liniștită.
     *
     * Cu serverul de minciună strecurat prin setSMTPInstance(), proba cheamă
     * chiar funcția din inc/posta.php, pe drumul ei întreg: verificarea adresei,
     * șablonul, poștașul, biblioteca. Singurul lucru care lipsește e firul până
     * la Roman.
     *
     * `connected()` care spune „da" scurtează tot: PHPMailer nu mai deschide
     * niciun socket, nu mai salută, nu mai trimite nicio parolă.
     */
    class ServerDeMinciuna extends \PHPMailer\PHPMailer\SMTP
    {
        /** Plicurile primite, în ordine: ['de_la' => …, 'catre' => [], 'text' => …] */
        public array $primite = [];

        private array $acum = ['de_la' => '', 'catre' => [], 'text' => ''];

        public function connected(): bool { return true; }

        public function mail($from): bool
        {
            $this->acum = ['de_la' => $from, 'catre' => [], 'text' => ''];
            return true;
        }

        public function recipient($address, $dsn = ''): bool
        {
            $this->acum['catre'][] = $address;
            return true;
        }

        public function data($msg_data): bool
        {
            $this->acum['text'] = $msg_data;
            $this->primite[]    = $this->acum;
            return true;
        }

        public function reset(): bool { return true; }
        public function quit($close_on_error = true): bool { return true; }
        public function close(): void {}
        public function getLastTransactionID() { return false; }
        public function getServerExt($name) { return true; }

        /** Ultimul plic primit. */
        public function ultimul(): array
        {
            return $this->primite === [] ? ['de_la' => '', 'catre' => [], 'text' => '']
                                         : $this->primite[count($this->primite) - 1];
        }
    }

    $server = new ServerDeMinciuna();
    postasul()->setSMTPInstance($server);

    /**
     * Trimite pe drumul ADEVĂRAT și întoarce plicul care a ajuns la server.
     * `$config['email_metoda'] = 'smtp'` cu dezvoltarea stinsă, ca trimiteEmail()
     * să aleagă chiar ramura de probat.
     */
    $config['dezvoltare']   = false;
    $config['email_metoda'] = 'smtp';

    $trimite = static function (string $catre, string $subiect,
                                array $anteturi = []) use ($server): array {
        $mers = trimiteEmail($catre, $subiect, ['paragrafe' => ['Un mesaj de probă.']],
                             $anteturi);

        return ['mers' => $mers] + $server->ultimul();
    };

    /* ================================================================== */
    sectiune('ce scrie în plic');

    $unu = $trimite('ana@exemplu-probe.ro', 'Bun venit');

    verifica('mesajul a ajuns la server', true, $unu['mers']);
    verifica('pleacă la cine trebuie', ['ana@exemplu-probe.ro'], $unu['catre']);

    /**
     * ADRESA DIN PLIC, cea la care se uită SPF și la care se întorc mesajele
     * nelivrate. Ține locul lui „-f" de la mail(). Fără ea, unele găzduiri pun
     * adresa contului de găzduire, care nu e pe domeniul nostru — și tocmai
     * nepotrivirea aia caută verificarea SPF.
     */
    verifica('cu adresa noastră în plic', 'noreply@exemplu-probe.ro', $unu['de_la']);

    verifica('cu expeditorul nostru în antet', true,
        str_contains($unu['text'], 'noreply@exemplu-probe.ro'));
    verifica('cu Reply-To spre contact', true,
        str_contains($unu['text'], 'contact@exemplu-probe.ro'));

    /* Amândouă variantele, ca la mail(): text pentru ceasuri, cititoare de
       ecran și filtrele de spam, HTML pentru ochi. */
    verifica('are și text, și HTML', true,
        str_contains($unu['text'], 'multipart/alternative')
        && str_contains($unu['text'], 'text/plain')
        && str_contains($unu['text'], 'text/html'));

    /* Anteturile care rămân ale noastre: biblioteca le face pe celelalte. */
    verifica('spune că e trimis de un program', true,
        str_contains($unu['text'], 'Auto-Submitted: auto-generated'));

    /* Versiunea bibliotecii NU se scrie în mesaj: e o vorbă spusă degeaba
       oricui adună ținte după biblioteci vechi. */
    verifica('nu-și spune versiunea', false,
        str_contains($unu['text'], \PHPMailer\PHPMailer\PHPMailer::VERSION));

    /* ================================================================== */
    sectiune('poștașul nu ține minte pe cine a servit');

    /**
     * AICI E PROBA CARE CONTEAZĂ CEL MAI MULT DIN TOT FIȘIERUL.
     *
     * Poștașul e unul singur, cu conexiunea deschisă, fiindcă altfel
     * un eveniment mare ar deschide zeci de conexiuni într-un minut — ceea ce
     * arată din afară exact ca cineva care încearcă parole. Prețul e că
     * PHPMailer NU golește singur lista de destinatari după send(): al doilea
     * mesaj ar pleca și către primul om, al treilea către primii doi.
     *
     * Și nu s-ar vedea nicăieri. Mesajele pleacă, funcția întoarce true, logul
     * scrie „trimis". Ar afla doar oamenii — primind scrisorile altora.
     */
    $doi = $trimite('bogdan@exemplu-probe.ro', 'Parolă nouă');

    verifica('al doilea pleacă DOAR la al doilea om',
        ['bogdan@exemplu-probe.ro'], $doi['catre']);
    verifica('și nu-l pomenește pe primul în plic', false,
        str_contains($doi['text'], 'ana@exemplu-probe.ro'));

    $trei = $trimite('carmen@exemplu-probe.ro', 'Cineva ți-a răspuns');

    verifica('nici al treilea nu-i cară pe primii doi',
        ['carmen@exemplu-probe.ro'], $trei['catre']);
    verifica('plicul lui e curat', false,
        str_contains($trei['text'], 'ana@exemplu-probe.ro')
        || str_contains($trei['text'], 'bogdan@exemplu-probe.ro'));

    /**
     * ȘI ANTETURILE DE PRISOS SE GOLESC.
     *
     * Azi niciun mesaj de pe site nu mai cere anteturi în plus — au plecat
     * odată cu newsletterul zilnic și cu anunțul scris de mână, singurele două
     * care veneau nechemate și de aceea purtau „List-Unsubscribe". Mecanismul
     * rămâne însă în trimiteEmail(), pentru ziua în care va fi din nou un mesaj
     * de felul acela, iar proba rămâne cu el: un antet lipit de poștaș ar fi
     * ajuns pe confirmarea de cont a următorului om, cu un buton
     * „Dezabonează-te" pe un mesaj de la care n-are de unde să se dezaboneze.
     */
    $cuIesire = $trimite('dan@exemplu-probe.ro', 'Azi în oraș',
        ['List-Unsubscribe' => '<https://exemplu-probe.ro/dezabonare.php?m=1&s=abc>']);

    verifica('un antet în plus ajunge în mesaj', true,
        str_contains($cuIesire['text'], 'List-Unsubscribe'));

    $dupaEl = $trimite('emil@exemplu-probe.ro', 'Bun venit');

    verifica('dar mesajul următor NU îl mai poartă', false,
        str_contains($dupaEl['text'], 'List-Unsubscribe'));

    /* Cinci mesaje date serverului, nu patru și nici șase. */
    verifica('atâtea plicuri, atâtea mesaje', 5, count($server->primite));

    /* ================================================================== */
    sectiune('adresele otrăvite');

    /**
     * Verificarea noastră stă ÎNAINTEA bibliotecii și oprește mesajul de tot:
     * la server nu ajunge nimic.
     */
    $cateAveam = count($server->primite);

    verifica('o adresă otrăvită nu pleacă', false,
        trimiteEmail("ana@exemplu-probe.ro\r\nBcc: altcineva@exemplu.ro",
                     'Ceva', ['paragrafe' => ['Text.']]));
    verifica('și nu ajunge nimic la server', $cateAveam, count($server->primite));

    /**
     * Un rând nou într-o adresă poate lipi anteturi inventate în mesaj — de
     * pildă un „Bcc:" către altcineva. Verificarea noastră stă ÎNAINTEA
     * bibliotecii (esteAdresaSigura din inc/email.php) și rămâne acolo chiar
     * dacă și PHPMailer se apără: două încuietori pe aceeași ușă.
     */
    verifica('adresa cu rând nou e oprită', false,
        esteAdresaSigura("ana@exemplu-probe.ro\r\nBcc: altcineva@exemplu.ro"));
    verifica('și una cu caracter de control', false,
        esteAdresaSigura("ana@exemplu-probe.ro\0"));
    verifica('una obișnuită trece', true, esteAdresaSigura('ana@exemplu-probe.ro'));
}

/* ==================================================================== */
sectiune('drumul ales');

/**
 * În dezvoltare NU pleacă nimic, oricât ar fi de bine puse datele SMTP: mesajele
 * se scriu în private/emailuri-trimise.log. Altfel, o rulare de probă pe
 * mașina de lucru ar fi scris unor oameni adevărați.
 */
$config['dezvoltare']   = true;
$config['email_metoda'] = 'auto';

$log     = __DIR__ . '/../private/emailuri-trimise.log';
$inainte = is_file($log) ? filesize($log) : 0;

$subiectProbe = 'Probă de drum ' . bin2hex(random_bytes(4));
trimiteEmail('ana@exemplu-probe.ro', $subiectProbe, ['paragrafe' => ['Ceva.']]);

verifica('în dezvoltare, mesajul se scrie în fișier', true,
    is_file($log) && filesize($log) > $inainte);

$config = $configVechi;

/* ==================================================================== */
echo "\n" . str_repeat('=', 60) . "\n";
echo "  $treceri trecute, $picaturi picate\n";
echo str_repeat('=', 60) . "\n";

exit($picaturi === 0 ? 0 : 1);
