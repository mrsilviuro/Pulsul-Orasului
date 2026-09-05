<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — newsletterul zilnic: „ce se întâmplă azi în oraș".
 *
 * O dată pe zi, la ora pusă în cron, pleacă un mesaj către cine are bifa de vești
 * din setări (`membri.newsletter`) — aceeași bifă de care atârnă și anunțul scris
 * de mână din admin-anunt.php. Înăuntru: evenimentele de ASTĂZI CARE N-AU ÎNCEPUT ÎNCĂ,
 * ca niște cartonașe ca pe prima pagină — poză, oră și loc. Lista pornește de la
 * clipa trimiterii: ce a început deja rămâne pe site, dar nu se mai bate la ușa
 * nimănui.
 *
 * DACĂ AZI NU E NIMIC, NU PLEACĂ NIMIC. Un mesaj care spune „azi nu se
 * întâmplă nimic" e cel mai bun fel de a-l învăța pe om să nu-l mai deschidă —
 * iar peste o lună, când chiar e ceva, mesajul ajunge tot necitit. Tăcerea e
 * conținut: dacă a venit mesajul, înseamnă că are ce spune.
 *
 * SE TRIMITE O SINGURĂ DATĂ PE ZI, oricâte ori ar rula cronul. Ștampila e
 * `membri.newsletter_trimis_la` (sql/031) și se pune ÎNAINTE de trimitere —
 * vezi insemneazaNewsletterTrimis(). Un e-mail plecat nu se ia înapoi, deci
 * dintre cele două greșeli cu putință — „a plecat de două ori" și „n-a plecat
 * deloc pentru că s-a stins curentul între ștampilă și poștă" — se alege a
 * doua.
 *
 * Îl cheamă doar cron/newsletter-zilnic.php.
 */

require_once __DIR__ . '/evenimente.php';
require_once __DIR__ . '/email.php';

/**
 * Câți abonați se servesc la o rulare.
 *
 * Nu e o limită de siguranță, e o frână: găzduirile obișnuite au un plafon de
 * mesaje pe oră, iar un site care îl sare o dată se trezește cu poșta oprită
 * pentru toată ziua. Ce nu încape azi... rămâne pe mâine — și e în regulă, un
 * newsletter zilnic nu se recuperează. La numărul ăsta de oameni, plafonul nici
 * nu se atinge; cifra e aici pentru ziua în care va fi altfel.
 */
const NEWSLETTER_PE_RULARE = 400;

/** Câte evenimente se scriu în mesaj. */
const NEWSLETTER_MAX_EVENIMENTE = 12;

/* =========================== CE URMEAZĂ AZI ========================== */

/**
 * Evenimentele de astăzi CARE N-AU ÎNCEPUT ÎNCĂ, în ordinea orei.
 *
 * NUMAI CELE APROBATE. Cele „încheiate" au fost deja oprite de organizator, iar
 * cele „anulate" nu se mai țin — pagina lor rămâne pe site, cu motivul, dar a le
 * trimite dimineața ca pe ceva ce urmează ar fi o minciună.
 *
 * NUMAI CE URMEAZĂ. Mesajul pleacă pe la prânz, dinadins: până atunci apucă să
 * se scrie și anunțurile de dimineață, iar lista de la ora 12 e mai plină decât
 * cea de la 7. Prețul e că unele au și început deja — iar un mesaj care spune la
 * 12 „azi la 10 e o alergare" nu e o veste, e o părere de rău. Deci lista începe
 * de la CLIPA TRIMITERII: ce a pornit rămâne pe site, dar nu se mai bate la ușa
 * nimănui.
 *
 * DE AICI DECURGE: dacă tot ce era azi a trecut, lista e goală și NU PLEACĂ
 * NIMIC — exact ca într-o zi în care nu e nimic. Tăcerea spune același lucru.
 *
 * Ceasul e cel al lui PHP (regula 5 din CLAUDE.md), nu CURDATE()/NOW() din
 * MySQL: cele două pot fi în fusuri deosebite, iar la miezul nopții ar însemna
 * zile deosebite, la prânz — ore deosebite.
 *
 * ORA SE TAIE LA MINUT, nu la secundă: cronul pus la 12:00 pornește în fapt la
 * 12:00:07, iar un eveniment scris fix la 12:00 n-are de ce să cadă din listă
 * pentru șapte secunde. „Începând cu ora la care pleacă mesajul" înseamnă
 * împreună cu ea.
 *
 * @param int|null $clipa Momentul față de care se socotește (implicit, acum).
 *                        Există pentru probe: altfel n-ai cum să spui „ia
 *                        închipuie-ți că e 14:00" fără să muți ceasul mașinii.
 */
function evenimenteleDeAzi(int $celMult = NEWSLETTER_MAX_EVENIMENTE, ?int $clipa = null): array
{
    $clipa = $clipa ?? time();

    $q = db()->prepare(
        'SELECT e.id, e.titlu, e.slug, e.coperta, e.oras, e.locatie, e.descriere,
                e.data_eveniment, e.ora_inceput,
                c.nume AS categorie, c.imagine_default
           FROM evenimente e
           JOIN categorii c ON c.id = e.categorie_id
          WHERE e.data_eveniment = ?
            AND e.ora_inceput   >= ?
            AND e.stare_moderare = \'aprobat\'
          ORDER BY e.ora_inceput ASC, e.id ASC
          LIMIT ' . max(1, $celMult)
    );
    $q->execute([date('Y-m-d', $clipa), date('H:i:00', $clipa)]);

    return $q->fetchAll();
}

/**
 * Rândurile pentru blocul „lista" din șablonul de e-mail.
 *
 * ADRESELE SUNT ÎNTREGI. Într-un e-mail nu există „pagina de acum" față de care
 * să se socotească o cale relativă: mesajul se deschide în Gmail, pe alt
 * server, în alt oraș. Totul trece prin urlIntreg().
 *
 * Poza: coperta anunțului, iar dacă n-are, imaginea categoriei — aceeași
 * ordine ca pe cartonașele de pe prima pagină, prin aceleași două funcții. Dacă
 * nu e niciuna, rândul rămâne cu caseta goală, de aceeași mărime (vezi
 * lămurirea de la blocul „lista" din inc/email.php).
 */
function randuriPentruNewsletter(array $evenimente): array
{
    $randuri = [];

    foreach ($evenimente as $ev) {
        $poza = urlCoperta($ev['coperta'] ?? null);

        if ($poza === '') {
            $poza = urlImagineCategorie($ev['imagine_default'] ?? null);
        }

        /**
         * Ora fără secunde, cum se scrie între oameni: „19:00", nu „19:00:00".
         * Ziua nu se mai scrie — tot mesajul e despre ziua de azi, iar a repeta
         * data la fiecare rând ar fi trei cuvinte în plus care nu spun nimic.
         */
        $ora = substr((string) $ev['ora_inceput'], 0, 5);

        /**
         * Orașul, apoi locul anume, despărțite cu „·" — ca pe cartonașele de pe
         * site. Se strânge dinspre larg spre îngust: când, în ce oraș, și abia
         * apoi unde anume.
         */
        $unde = array_filter([
            (string) ($ev['oras'] ?? ''),
            (string) ($ev['locatie'] ?? ''),
        ], static fn(string $x): bool => $x !== '');

        $randuri[] = [
            'titlu' => (string) $ev['titlu'],
            'cand'  => $ora === '' ? '' : 'de la ' . $ora,
            'unde'  => implode(' · ', $unde),
            'poza'  => $poza === '' ? '' : urlIntreg($poza),
            'href'  => urlIntreg(urlEveniment((string) $ev['slug'])),

            // Categoria și începutul descrierii, ca pe cartonașul de pe site.
            // Textul e tăiat scurt dinadins: într-un mesaj cu patru evenimente,
            // patru paragrafe întregi ar fi însemnat un ecran de derulat până
            // la primul lucru de apăsat.
            'categorie' => (string) ($ev['categorie'] ?? ''),
            'text'      => inceputDeText(strip_tags((string) ($ev['descriere'] ?? '')), 110),
        ];
    }

    return $randuri;
}

/* ============================= ABONAȚII ============================== */

/**
 * Cine primește newsletterul de azi.
 *
 * Trei condiții, toate necesare:
 *   - bifa pornită (`newsletter = 1`);
 *   - contul ACTIV — un cont neconfirmat n-a dovedit că adresa e a lui, iar
 *     unul suspendat sau anonimizat n-are unde primi nimic;
 *   - nu i-a plecat deja astăzi.
 *
 * Cei mai vechi întâi (`id ASC`), ca la o rulare tăiată de NEWSLETTER_PE_RULARE
 * să nu fie mereu aceiași cei lăsați pe dinafară: cei serviți primesc ștampila,
 * deci la următoarea trecere sunt ceilalți în capul listei.
 */
function abonatiiNewsletterului(int $celMult = NEWSLETTER_PE_RULARE): array
{
    $q = db()->prepare(
        'SELECT id, prenume, email
           FROM membri
          WHERE newsletter = 1
            AND stare = \'activ\'
            AND email <> \'\'
            AND (newsletter_trimis_la IS NULL OR newsletter_trimis_la < ?)
          ORDER BY id ASC
          LIMIT ' . max(1, $celMult)
    );
    $q->execute([date('Y-m-d')]);

    return $q->fetchAll();
}

/** Câți abonați are lista, cu totul — pentru ce scrie cronul pe ecran. */
function catiAbonati(): int
{
    return (int) db()->query(
        'SELECT COUNT(*) FROM membri
          WHERE newsletter = 1 AND stare = \'activ\' AND email <> \'\''
    )->fetchColumn();
}

/**
 * Pune ștampila zilei pe rândul omului. Întoarce false dacă era deja pusă.
 *
 * HOTĂRÂREA E ÎN `WHERE`, ca la revendicarea unui abțibild și la vestea despre
 * o părere: două rulări pornite în aceeași clipă — cronul și o încercare de
 * mână — ar întreba amândouă „i-a plecat azi?", ar auzi amândouă „nu", și ar
 * trimite amândouă. Aici doar una dintre ele schimbă rândul, iar cealaltă
 * primește false și tace.
 *
 * De aceea se cheamă ÎNAINTE de trimitere, iar mesajul pleacă numai dacă a
 * prins ștampila.
 */
function insemneazaNewsletterTrimis(int $membruId): bool
{
    $q = db()->prepare(
        'UPDATE membri SET newsletter_trimis_la = ?
          WHERE id = ?
            AND (newsletter_trimis_la IS NULL OR newsletter_trimis_la < ?)'
    );
    $q->execute([date('Y-m-d'), $membruId, date('Y-m-d')]);

    return $q->rowCount() === 1;
}

/* =========================== DEZABONAREA ============================= */

/**
 * Cheia cu care se semnează linkurile de dezabonare.
 *
 * NU SE ȚINE NICIUN TOKEN ÎN BAZĂ, dinadins. Un token scris la fiecare
 * trimitere ar însemna că linkul de ieri moare azi — iar cine caută peste trei
 * luni un mesaj vechi ca să se dezaboneze ar da peste „link expirat" și ar
 * apăsa „Spam" în schimb. Un singur om care face asta strică livrarea pentru
 * toți ceilalți.
 *
 * Deci semnătura se SOCOTEȘTE de fiecare dată din id-ul omului și o cheie a
 * site-ului, cu HMAC: aceeași ieșire mereu, imposibil de ghicit fără cheie, și
 * nimic de păstrat nicăieri.
 *
 * DACĂ NU E PUSĂ ÎN CONFIG, se face una din datele care există deja acolo.
 * Alegerea e între „merge din prima pe orice găzduire" și „încă un pas de
 * pregătire pe care cineva îl uită, iar newsletterul pleacă fără ieșire". A
 * doua e mai rea. Cine vrea o cheie a lui o scrie în `cheie_dezabonare`; până
 * atunci, cea derivată e la fel de nedibuită din afară — și se schimbă doar
 * dacă se schimbă parola bazei, ceea ce nu se întâmplă des.
 */
function cheieDezabonare(): string
{
    global $config;

    $scrisa = trim((string) ($config['cheie_dezabonare'] ?? ''));

    if ($scrisa !== '') {
        return $scrisa;
    }

    return hash('sha256', 'dezabonare|'
        . (string) ($config['db']['parola'] ?? '')
        . '|' . (string) ($config['url_site'] ?? ''));
}

/** Semnătura care însoțește id-ul în linkul de dezabonare. */
function semnaturaDezabonare(int $membruId): string
{
    // 32 de semne din cele 64 ale unui sha256: destule cât să nu se ghicească
    // (2^128), și destul de scurte cât linkul să încapă pe un rând.
    return substr(hash_hmac('sha256', 'dezabonare:' . $membruId, cheieDezabonare()), 0, 32);
}

/** Adresa întreagă pe care o apasă omul ca să nu mai primească newsletterul. */
function linkDezabonare(int $membruId): string
{
    return urlIntreg('/dezabonare.php?m=' . $membruId . '&s=' . semnaturaDezabonare($membruId));
}

/**
 * Cine e omul din spatele unui link de dezabonare — sau null.
 *
 * Semnătura se compară cu hash_equals(), nu cu „===": comparația obișnuită se
 * oprește la prima literă deosebită, iar timpul până se oprește spune câte
 * litere s-au potrivit. Din afară, cu destule încercări, de acolo se poate
 * reconstrui o semnătură literă cu literă.
 */
function membrulDinLinkulDeDezabonare(string $id, string $semnatura): ?array
{
    if (preg_match('/^[0-9]{1,10}$/', $id) !== 1
        || preg_match('/^[a-f0-9]{32}$/', $semnatura) !== 1) {
        return null;
    }

    $membruId = (int) $id;

    if ($membruId <= 0 || !hash_equals(semnaturaDezabonare($membruId), $semnatura)) {
        return null;
    }

    $q = db()->prepare('SELECT id, prenume, email, newsletter, stare FROM membri WHERE id = ? LIMIT 1');
    $q->execute([$membruId]);

    $om = $q->fetch();

    return $om === false ? null : $om;
}

/**
 * Stinge bifa. Întoarce true dacă chiar era pornită.
 *
 * NUMAI bifa de newsletter, nimic altceva: cine se satură de mesajul zilnic
 * n-a spus că nu mai vrea să afle că i s-a anulat un eveniment la care se
 * înscrisese. Un „dezabonează-mă de la tot" care oprește și vestea aceea e o
 * capcană, nu o politețe.
 */
function opresteNewsletterul(int $membruId): bool
{
    $q = db()->prepare(
        'UPDATE membri SET newsletter = 0, actualizat_la = ?
          WHERE id = ? AND newsletter = 1'
    );
    $q->execute([acum(), $membruId]);

    return $q->rowCount() === 1;
}

/* ============================ TRIMITEREA ============================= */

/**
 * Trimite newsletterul de azi. Întoarce ce s-a întâmplat, pe cifre.
 *
 * `$uscat` arată ce s-ar trimite fără să atingă nimic — nici mesajele nu pleacă,
 * nici ștampilele nu se pun. E singurul fel omenesc de a proba un script care
 * scrie unor oameni adevărați.
 *
 * `$clipa` e momentul de la care începe lista: intră doar ce n-a pornit încă.
 * Se citește O SINGURĂ DATĂ, aici — o rulare cu câteva sute de mesaje ține
 * minute bune, iar dacă ora s-ar lua din nou pe parcurs, primii oameni și
 * ultimii ar primi liste deosebite.
 */
function trimiteNewsletterulZilei(bool $uscat = false, ?int $clipa = null): array
{
    $evenimente = evenimenteleDeAzi(NEWSLETTER_MAX_EVENIMENTE, $clipa ?? time());

    if ($evenimente === []) {
        return ['evenimente' => 0, 'abonati' => 0, 'trimise' => 0, 'picate' => 0, 'sarite' => 0];
    }

    $randuri = randuriPentruNewsletter($evenimente);
    $abonati = abonatiiNewsletterului();

    $rezultat = [
        'evenimente' => count($evenimente),
        'abonati'    => count($abonati),
        'trimise'    => 0,
        'picate'     => 0,
        'sarite'     => 0,
    ];

    if ($uscat) {
        return $rezultat;
    }

    foreach ($abonati as $om) {
        $membruId = (int) $om['id'];

        // Ștampila întâi. Cine n-o prinde a fost servit de altcineva.
        if (!insemneazaNewsletterTrimis($membruId)) {
            $rezultat['sarite']++;
            continue;
        }

        $plecat = emailNewsletterZilnic(
            (string) $om['email'],
            (string) $om['prenume'],
            $randuri,
            linkDezabonare($membruId)
        );

        $plecat ? $rezultat['trimise']++ : $rezultat['picate']++;
    }

    return $rezultat;
}

/**
 * Un rând în logul newsletterului.
 *
 * Fișier al lui, ca la mulțumiri: amestecat cu restul, un cron care trimite
 * câteva sute de mesaje ar acoperi tot ce s-a mai întâmplat în ziua aia.
 */
function scrieInLogulNewsletterului(string $rand): void
{
    scrieInLog('newsletter.log', $rand);
}
