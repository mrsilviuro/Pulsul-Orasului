<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — COADA DE E-MAILURI.
 *
 * inc/email.php spune CE scrie într-un mesaj, inc/posta.php pe ce drum iese din
 * casă, iar fișierul ăsta CÂND. Sunt trei întrebări deosebite și de-abia acum
 * are fiecare casa ei.
 *
 * DE CE EXISTĂ. Găzduirea duce zece mesaje pe minut și șase sute pe ceas. Sunt
 * însă locuri pe site unde o singură apăsare naște zeci de mesaje: cineva cu
 * două sute de urmăritori publică un anunț, un eveniment cu treizeci de înscriși
 * se anulează. Trimise pe loc, alea sar plafonul — iar cine îl sare rămâne fără
 * poștă pentru tot restul orei, cu tot cu confirmările de cont ale unor oameni
 * care n-aveau nicio treabă cu ele. Pe deasupra, omul care a apăsat „Anulează"
 * se uita la o pagină care se învârtea până plecau toate.
 *
 * CADENȚA CRONULUI E PASUL. Nu se mai așteaptă între mesaje: o rulare ia
 * COADA_PE_RULARE (8) și le duce una după alta, în câteva secunde, apoi se
 * încheie. Cronul pornește peste un minut. Iese același ritm, dar rulările nu se
 * mai suprapun niciodată — pe când o rulare care ar fi dormit șase secunde între
 * mesaje ar fi ținut fix cât intervalul dintre porniri, adică s-ar fi călcat cu
 * următoarea la fiecare trecere.
 *
 * OPT, NU ZECE: zece pe minut înseamnă exact șase sute pe ceas, adică plafonul
 * lovit din plin, fără niciun loc rămas pentru mesajele care pleacă pe loc.
 *
 * CE INTRĂ ÎN COADĂ ȘI CE NU — vezi lămurirea de la laCoada(), mai jos. Pe
 * scurt: numai ce pleacă în serie. Confirmarea de cont pleacă mai departe pe
 * loc, fiindcă un cron oprit n-are voie să însemne că nimeni nu-și mai poate
 * face cont.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/posta.php';

/**
 * DA, SE CER UNUL PE ALTUL — și e în regulă, spre deosebire de bucla dintre
 * inc/coduri-qr.php și inc/evenimente.php, care chiar trebuia ocolită.
 *
 * inc/email.php cere fișierul ăsta ca să poată scrie la rând în loc să trimită;
 * fișierul ăsta îl cere pe el ca să poată trimite ce a scris. Dependența e cu
 * adevărat în amândouă sensurile, fiindcă „scrie la rând" și „du ce e la rând"
 * sunt cele două capete ale aceleiași treburi.
 *
 * Nu se rupe nimic fiindcă NICIUNUL nu cheamă nimic din celălalt LA INCLUDERE —
 * doar declară funcții. Al doilea `require_once` dintr-un lanț care se întoarce
 * la primul se întoarce liniștit cu `true`, iar la capăt ambele fișiere și-au
 * declarat funcțiile. Merge la fel oricare ar fi cerut primul; e probat în
 * teste/test-coada.php, în amândouă ordinile.
 */
require_once __DIR__ . '/email.php';

/**
 * Câte mesaje duce o rulare.
 *
 * Vine din config, fiindcă e o cifră a GĂZDUIRII, nu a noastră, și trebuie să
 * se potrivească cu cât de des pornește cronul: la un cron din minut în minut,
 * opt înseamnă opt pe minut. Dacă găzduirea nu lasă cron mai des de cinci
 * minute, cifra crește pe măsură (40), iar ritmul rămâne același.
 *
 * `emailuri_pe_rulare` e numele bun. `email_pe_minut` a fost primul, de pe
 * vremea când frâna era o pauză între mesaje; se citește mai departe, ca o
 * setare deja scrisă într-un config.php să nu se stingă tăcut.
 */
function coadaPeRulare(): int
{
    global $config;

    $cate = (int) ($config['emailuri_pe_rulare'] ?? $config['email_pe_minut'] ?? 8);

    return $cate > 0 ? $cate : 8;
}

/**
 * De câte ori se încearcă un mesaj înainte să fie lăsat în pace.
 *
 * Fără plafonul ăsta, un mesaj către o adresă care nu mai există s-ar învârti
 * în coadă pentru totdeauna, mâncând de fiecare dată un loc din cele opt.
 */
const COADA_INCERCARI_MAX = 3;

/**
 * După câte minute se ia din nou un rând rămas agățat.
 *
 * O rulare care moare între „am luat rândul" și „am trimis mesajul" îl lasă
 * însemnat ca luat, dar netrimis. Zece minute e mult mai mult decât ține o
 * rulare sănătoasă (secunde), deci un rând care stă atâta chiar e orfan.
 *
 * PREȚUL, SPUS PE FAȚĂ: dacă rularea a murit între trimitere și ștampilă,
 * mesajul pleacă de două ori. E singurul loc de pe site unde se alege așa —
 * peste tot altundeva se preferă „n-a plecat" în locul lui „a plecat de două
 * ori". Aici nu se poate: un rând care n-ar fi luat niciodată din nou ar
 * rămâne blocat pe veci, iar asta înseamnă un mesaj pierdut de-a binelea, nu
 * doar întârziat. Plafonul de încercări îngrădește paguba.
 */
const COADA_MINUTE_BLOCAT = 10;

/** Câte zile rămân rândurile trimise, ca să se poată vedea ce a plecat. */
const COADA_ZILE_PASTRARE = 7;

/** Prioritatea obișnuită și cea care trece înainte. */
const COADA_NORMAL = 0;
const COADA_URGENT = 1;

/** Câte rânduri rămase pe drumuri se arată pe panoul din admin. */
const COADA_PICATE_ARATATE = 20;

/* ========================== PUNEREA LA RÂND =========================== */

/**
 * Scrie un mesaj în coadă. Întoarce true dacă rândul a intrat.
 *
 * NU SE CHEAMĂ DE-A DREPTUL din locurile care trimit e-mailuri — alea cheamă
 * mai departe funcțiile email* obișnuite din inc/email.php, învelite în
 * laCoada(). Vezi de ce acolo.
 */
function puneInCoada(string $catre, string $subiect, array $blocuri,
                     array $anteturi = [], int $prioritate = COADA_NORMAL): bool
{
    /**
     * Adresa se verifică ȘI aici, nu doar la trimitere.
     *
     * Un rând cu o adresă otrăvită ar fi stat în coadă până când cronul ar fi
     * încercat s-o trimită, adică s-ar fi oprit abia la a doua încuietoare. Mai
     * bine nu intră deloc: ce nu e în coadă nu poate pleca din ea.
     */
    if (!esteAdresaSigura($catre)) {
        error_log('PulsulOrasului: adresă respinsă la punerea în coadă.');
        return false;
    }

    $q = db()->prepare(
        'INSERT INTO coada_emailuri (catre, subiect, blocuri, anteturi, prioritate, creat_la)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $q->execute([
        $catre,
        $subiect,
        (string) json_encode($blocuri, JSON_UNESCAPED_UNICODE),
        $anteturi === [] ? null : (string) json_encode($anteturi, JSON_UNESCAPED_UNICODE),
        $prioritate,
        acum(),
    ]);

    return $q->rowCount() === 1;
}

/**
 * Cât ține închisoarea asta, orice trimiteEmail() SCRIE ÎN COADĂ în loc să
 * trimită. Întoarce ce a întors funcția dinăuntru.
 *
 * DE CE UN ÎNVELIȘ, ȘI NU UN PARAMETRU ÎN PLUS LA FIECARE MESAJ.
 *
 * Site-ul are douăzeci de funcții email*, fiecare cu parametrii ei. Un „pune-l
 * la rând" adăugat ca al șaptelea argument ar fi trebuit strecurat prin cinci
 * dintre ele, iar a șasea — cea scrisă la anul — l-ar fi uitat, și ar fi trimis
 * două sute de mesaje dintr-o cerere web fără ca cineva să bage de seamă.
 *
 * Așa, locul care trimite ÎN SERIE spune o singură dată „tot ce iese de aici
 * merge la rând", iar funcțiile de mesaje rămân neatinse. O funcție nouă intră
 * în coadă fiindcă e chemată dinăuntru, nu fiindcă și-a adus aminte cineva.
 *
 * `try/finally` nu e o politețe: dacă mesajul dinăuntru aruncă ceva, steagul
 * TREBUIE stins oricum. Altfel confirmarea de cont a următorului om de pe
 * server ar fi ajuns și ea în coadă, iar el ar fi așteptat un minut degeaba —
 * sau, mai rău, ar fi rămas acolo dacă cronul e oprit.
 *
 * NU E RECURSIV, dinadins: două învelișuri unul în altul se poartă ca unul
 * singur, fiindcă steagul se pune, nu se numără. N-am nevoie de mai mult, iar
 * un contor ar fi fost o piesă în plus fără niciun chemător.
 */
function laCoada(callable $ce, int $prioritate = COADA_NORMAL)
{
    global $poCoadaPornita, $poCoadaPrioritate;

    $vechi           = $poCoadaPornita ?? false;
    $vecheaPrioritate = $poCoadaPrioritate ?? COADA_NORMAL;

    $poCoadaPornita    = true;
    $poCoadaPrioritate = $prioritate;

    try {
        return $ce();
    } finally {
        $poCoadaPornita    = $vechi;
        $poCoadaPrioritate = $vecheaPrioritate;
    }
}

/** Suntem înăuntrul unui laCoada()? O întreabă doar trimiteEmail(). */
function scriemInCoada(): bool
{
    global $poCoadaPornita;

    return !empty($poCoadaPornita);
}

/** Cu ce prioritate scrie coada acum. */
function prioritateaCozii(): int
{
    global $poCoadaPrioritate;

    return (int) ($poCoadaPrioritate ?? COADA_NORMAL);
}

/* ============================= TRIMITEREA ============================= */

/**
 * Ia din coadă cel mult $cate rânduri și le însemnează ca luate.
 *
 * DOI PAȘI, ȘI PRIMUL E O SINGURĂ MIȘCARE A BAZEI. Se scrie întâi cifra rulării
 * pe rândurile care urmează (UPDATE … ORDER BY … LIMIT), abia apoi se citesc
 * înapoi cele care o poartă. Două rulări pornite deodată nu pot lua aceleași
 * rânduri: UPDATE-ul e atomic, iar a doua rulare găsește rândurile deja
 * însemnate cu altă cifră.
 *
 * Un SELECT urmat de un UPDATE ar fi lăsat loc între ele — exact fereastra prin
 * care intră a doua rulare. Aceeași regulă ca la revendicarea unui abțibild și
 * la ștampila urmăritorilor: HOTĂRÂREA STĂ ÎN `WHERE`.
 */
function iaDinCoada(int $cate): array
{
    $cifra   = bin2hex(random_bytes(16));
    $inainte = acumMinus(COADA_MINUTE_BLOCAT);

    $ia = db()->prepare(
        'UPDATE coada_emailuri
            SET luat_de = ?, luat_la = ?, incercari = incercari + 1
          WHERE trimis_la IS NULL
            AND incercari < ?
            AND (luat_la IS NULL OR luat_la < ?)
          ORDER BY prioritate DESC, id ASC
          LIMIT ' . max(1, $cate)
    );
    $ia->execute([$cifra, acum(), COADA_INCERCARI_MAX, $inainte]);

    if ($ia->rowCount() === 0) {
        return [];
    }

    $q = db()->prepare(
        'SELECT id, catre, subiect, blocuri, anteturi
           FROM coada_emailuri
          WHERE luat_de = ?
          ORDER BY prioritate DESC, id ASC'
    );
    $q->execute([$cifra]);

    return $q->fetchAll();
}

/** Ștampila de „a plecat". */
function insemneazaDinCoadaTrimis(int $id): void
{
    db()->prepare('UPDATE coada_emailuri SET trimis_la = ?, eroare = NULL WHERE id = ?')
        ->execute([acum(), $id]);
}

/**
 * Mesajul n-a plecat: se scrie de ce și se dă drumul rândului.
 *
 * `luat_la = NULL` îl pune înapoi la rând pe loc, fără să mai aștepte cele zece
 * minute — nu e un rând orfan, e unul încercat și picat, iar rularea următoare
 * poate să-l ia liniștită. `incercari` a crescut deja la luare, deci după al
 * treilea nu-l mai ia nimeni.
 *
 * CU $definitiv, RÂNDUL MOARE DIN PRIMA: `incercari` se duce de-a dreptul la
 * capăt. Se cheamă așa doar când serverul a spus limpede că ADRESA nu există
 * (vezi esteRefuzDefinitiv din inc/posta.php) — iar o adresă care nu există
 * acum nu se naște peste un minut, deci a doua și a treia încercare n-ar fi
 * decât încă două bătăi la ușa unui om care nu e acolo. Găzduirile numără
 * refuzurile astea („protecție la Bounces"): cine insistă pe adrese moarte
 * ajunge să nu mai poată trimite nimănui.
 */
function insemneazaDinCoadaPicat(int $id, string $eroare, bool $definitiv = false): void
{
    $incercari = $definitiv ? ', incercari = ' . COADA_INCERCARI_MAX : '';

    db()->prepare(
        'UPDATE coada_emailuri
            SET luat_de = NULL, luat_la = NULL, eroare = ?' . $incercari . '
          WHERE id = ?'
    )->execute([mb_substr($eroare, 0, 255), $id]);
}

/**
 * Trimite ce urmează la rând. Întoarce cifrele rulării.
 *
 * NU SE DESCHIDE NICIO CONEXIUNE dacă n-are ce trimite: iaDinCoada() întreabă
 * baza, iar poștașul din inc/posta.php se face abia la primul mesaj. Un cron
 * care pornește din minut în minut și de obicei nu găsește nimic costă atunci o
 * singură interogare indexată — mai puțin decât o deschidere de pagină.
 */
function trimiteDinCoada(?int $cate = null): array
{
    $randuri = iaDinCoada($cate ?? coadaPeRulare());

    $iesit = ['luate' => count($randuri), 'trimise' => 0, 'picate' => 0];

    foreach ($randuri as $rand) {
        $blocuri  = json_decode((string) $rand['blocuri'], true);
        $anteturi = $rand['anteturi'] === null
            ? []
            : json_decode((string) $rand['anteturi'], true);

        /**
         * Un rând cu JSON stricat nu se poate trimite niciodată, deci n-are
         * rost să mai fie încercat: se însemnează ca trimis, ca să iasă din
         * coadă, iar motivul rămâne scris pe el.
         */
        if (!is_array($blocuri)) {
            insemneazaDinCoadaTrimis((int) $rand['id']);
            db()->prepare('UPDATE coada_emailuri SET eroare = ? WHERE id = ?')
                ->execute(['Cuprinsul mesajului nu s-a putut citi.', (int) $rand['id']]);
            $iesit['picate']++;
            continue;
        }

        /**
         * Ștampila se pune DUPĂ trimitere, nu înainte — pe dos față de
         * newsletterul de altădată, și dinadins.
         *
         * Acolo nu se putea afla dacă mesajul a plecat, deci se alegea răul mai
         * mic: ștampila întâi, ca să nu plece de două ori. Aici SE ȘTIE, fiindcă
         * SMTP-ul răspunde — deci se poate face lucrul drept: se trimite, și
         * abia ce a plecat cu adevărat primește ștampila. Ce a picat rămâne în
         * coadă, cu vorba serverului scrisă pe el, și se mai încearcă.
         */
        $plecat = trimiteEmail(
            (string) $rand['catre'],
            (string) $rand['subiect'],
            $blocuri,
            is_array($anteturi) ? $anteturi : [],
            true   // vine DIN coadă: să nu se pună singur la loc în ea
        );

        if ($plecat) {
            insemneazaDinCoadaTrimis((int) $rand['id']);
            $iesit['trimise']++;
        } else {
            /* „Adresa aia nu există" se crede din prima; orice altceva se mai
               încearcă de trei ori. */
            $vorba = ultimaVorbaAPostei();

            insemneazaDinCoadaPicat(
                (int) $rand['id'],
                $vorba,
                esteRefuzDefinitiv($vorba)
            );
            $iesit['picate']++;
        }
    }

    return $iesit;
}

/* ============================ ÎNTREBĂRI ============================== */

/** Câte mesaje așteaptă acum la rând. */
function cateAsteaptaInCoada(): int
{
    return (int) db()->query(
        'SELECT COUNT(*) FROM coada_emailuri
          WHERE trimis_la IS NULL AND incercari < ' . COADA_INCERCARI_MAX
    )->fetchColumn();
}

/**
 * Câte au rămas pe drumuri: încercate de prea multe ori și nelivrate.
 *
 * Cifra asta ar trebui să fie zero. Când nu e, ceva e stricat — o parolă SMTP
 * schimbată, plafonul sărit, un domeniu care ne refuză — și se vede în `eroare`
 * de pe rânduri.
 */
function catePicateInCoada(): int
{
    return (int) db()->query(
        'SELECT COUNT(*) FROM coada_emailuri
          WHERE trimis_la IS NULL AND incercari >= ' . COADA_INCERCARI_MAX
    )->fetchColumn();
}

/**
 * Rândurile rămase pe drumuri, cu tot cu vorba serverului — pentru panoul din
 * admin. Cele mai noi întâi: pe alea le caută omul care tocmai a văzut cifra.
 *
 * SE ADUC CEL MULT COADA_PICATE_ARATATE. Lista e una de privit și de golit, nu
 * un raport: dacă sunt o sută, e limpede din cifră că ceva e stricat rău, iar
 * o sută de rânduri pe panou n-ar spune mai mult decât primele douăzeci.
 */
function emailurilePicate(): array
{
    return db()->query(
        'SELECT id, catre, subiect, eroare, incercari, creat_la
           FROM coada_emailuri
          WHERE trimis_la IS NULL AND incercari >= ' . COADA_INCERCARI_MAX . '
          ORDER BY id DESC
          LIMIT ' . COADA_PICATE_ARATATE
    )->fetchAll();
}

/**
 * Șterge un rând picat. „Aia e, n-a plecat, la revedere."
 *
 * HOTĂRÂREA STĂ ÎN `WHERE`, ca peste tot: se șterge doar ce chiar a rămas pe
 * drumuri. Un id cules de pe un buton vechi n-are cum să scoată din coadă un
 * mesaj care între timp și-a găsit drumul sau încă își așteaptă rândul.
 */
function stergeDinCoada(int $id): bool
{
    $q = db()->prepare(
        'DELETE FROM coada_emailuri
          WHERE id = ? AND trimis_la IS NULL AND incercari >= ' . COADA_INCERCARI_MAX
    );
    $q->execute([$id]);

    return $q->rowCount() > 0;
}

/** Le șterge pe toate cele picate deodată. Întoarce câte au plecat. */
function stergeToateCelePicate(): int
{
    $q = db()->prepare(
        'DELETE FROM coada_emailuri
          WHERE trimis_la IS NULL AND incercari >= ' . COADA_INCERCARI_MAX
    );
    $q->execute();

    return $q->rowCount();
}

/** Câte au plecat în ultimul ceas — pentru cine vrea să vadă cât s-a consumat. */
function catePlecateInUltimulCeas(): int
{
    $q = db()->prepare('SELECT COUNT(*) FROM coada_emailuri WHERE trimis_la > ?');
    $q->execute([acumMinus(60)]);

    return (int) $q->fetchColumn();
}

/**
 * Șterge rândurile vechi. Întoarce câte au plecat.
 *
 * SINGURA ȘTERGERE ADEVĂRATĂ CARE SE FACE SINGURĂ pe site. Nu calcă regula „nu
 * se șterge nimic": aceea e despre ce a scris omul, iar un rând de aici e un
 * plic — iar un plic ajuns la destinație nu mai e nimănui de folos.
 *
 * NU LA TRIMITERE, ci după COADA_ZILE_PASTRARE. Cele șapte zile sunt tot ce ține
 * loc de log: „i-a plecat lui X mesajul?" e o întrebare care chiar se pune.
 *
 * SE ȘTERG DOAR CELE TRIMISE. Unul picat de trei ori rămâne acolo până se uită
 * cineva la el — e singurul semn că ceva nu merge.
 */
function curataCoada(): int
{
    $q = db()->prepare(
        'DELETE FROM coada_emailuri
          WHERE trimis_la IS NOT NULL AND trimis_la < ?
          LIMIT 500'
    );
    $q->execute([acumMinus(COADA_ZILE_PASTRARE * 24 * 60)]);

    return $q->rowCount();
}

/** Un rând în logul cozii. Fișier al lui, ca la mulțumiri și amintiri. */
function scrieInLogulCozii(string $rand): void
{
    scrieInLog('coada.log', $rand);
}
