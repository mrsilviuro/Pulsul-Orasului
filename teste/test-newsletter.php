<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — newsletterul zilnic.
 *
 * Cere BAZA DE DATE. Partea de HTTP (pagina de dezabonare) cere și SERVERUL,
 * dar se sare singură dacă nu i se dă o adresă.
 *
 * MESAJELE NU PLEACĂ NICĂIERI cât `dezvoltare => true` în inc/config.php: se
 * scriu în private/emailuri-trimise.log (vezi trimiteEmail din inc/email.php).
 *
 * Cum se rulează:
 *     php teste/test-newsletter.php
 *     php teste/test-newsletter.php http://127.0.0.1:8099
 *
 * ATENȚIE: proba STINGE bifa de newsletter la toți ceilalți membri din bază pe
 * durata ei, ca să servească doar oamenii ei, și o pune la loc la sfârșit —
 * vezi curata(). Altfel, pe o bază de dezvoltare cu douăzeci de conturi vechi,
 * fiecare rulare ar fi scris douăzeci de mesaje în log și ar fi pus ștampila
 * zilei pe oameni care n-au nicio treabă cu proba.
 */

require_once __DIR__ . '/../inc/newsletter.php';

$BAZA = rtrim($argv[1] ?? '', '/');

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

const SEMN     = 'tstnews-';
const SLUG_CAT = 'tstnews-categorie';

/** Cine avea bifa pornită înainte de probă, ca să le-o punem la loc. */
$aveauBifa = [];

/** Oamenii, evenimentele și categoria probei, șterse. Atât — nimic altceva. */
function stergeOameniiProbei(): void
{
    db()->prepare('DELETE e FROM evenimente e JOIN membri m ON m.id = e.membru_id
                    WHERE m.permalink LIKE ?')->execute([SEMN . '%']);
    db()->prepare('DELETE FROM membri WHERE permalink LIKE ?')->execute([SEMN . '%']);
    db()->prepare('DELETE FROM categorii WHERE slug = ?')->execute([SLUG_CAT]);
}

/**
 * O categorie a probei, FĂRĂ imagine implicită.
 *
 * Evenimentele foloseau `(SELECT MIN(id) FROM categorii)`, adică prima
 * categorie din bază — iar aceleia i se poate pune oricând o imagine de pe
 * site. Atunci proba „fără poză, câmpul e gol" pica, deși codul era în
 * regulă. O categorie a ei înseamnă că proba spune același lucru pe orice
 * bază.
 */
function faCategoriaProbei(): int
{
    db()->prepare(
        'INSERT INTO categorii (nume, slug, ordine, doar_staff, joc_qr, imagine_default)
         VALUES (?,?,?,0,0,NULL)'
    )->execute(['Probă newsletter', SLUG_CAT, 96]);

    return (int) db()->lastInsertId();
}

/**
 * Curățenia de la sfârșit: ai noștri pleacă, iar bifele celorlalți se pun la
 * loc cum erau.
 *
 * CELE DOUĂ TREBURI STAU DESPĂRȚITE DINADINS. Când erau una singură, chemarea
 * de la începutul probei — care voia doar să șteargă rămășițele unei rulări de
 * dinainte — punea la loc și bifele pe care tocmai le stinsesem. Rezultatul:
 * un membru vechi din bază rămânea abonat, primea și el mesajul, iar proba
 * pica cu „aștept 2, am primit 3" — dar numai când baza avea așa ceva în ea,
 * adică numai câteodată.
 */
function curata(): void
{
    global $aveauBifa;

    stergeOameniiProbei();

    foreach ($aveauBifa as $id) {
        db()->prepare('UPDATE membri SET newsletter = 1 WHERE id = ?')->execute([(int) $id]);
    }

    $aveauBifa = [];
}

stergeOameniiProbei();

/* Cine are bifa acum se ține minte, apoi se stinge — proba servește doar
   oamenii ei. */
$aveauBifa = db()->query('SELECT id FROM membri WHERE newsletter = 1')->fetchAll(PDO::FETCH_COLUMN);
db()->exec('UPDATE membri SET newsletter = 0');

register_shutdown_function('curata');

function faMembru(string $cheie, string $prenume, bool $abonat,
                  string $stare = 'activ'): int
{
    db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, sex, data_nasterii,
                             parola_hash, stare, newsletter, creat_la, confirmat_la)
         VALUES (?,?,?,?,\'M\',\'1990-01-01\',\'x\',?,?,?,?)'
    )->execute([
        substr(SEMN . $cheie, 0, 16), 'Vestescu', $prenume,
        SEMN . $cheie . '@invalid.local', $stare, $abonat ? 1 : 0, acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

function faEveniment(int $cine, string $slug, string $titlu, string $zi,
                     string $ora, string $stare = 'aprobat'): int
{
    global $CATEGORIA;

    db()->prepare(
        'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, oras, locatie,
                                 descriere, data_eveniment, ora_inceput, stare_moderare,
                                 creat_la, actualizat_la)
         VALUES (?,?,?,?,?,\'Centrul vechi\',?,?,?,?,?,?)'
    )->execute([
        $cine, $CATEGORIA, $titlu, $slug, oraseDisponibile()[0] ?? 'Roman',
        str_repeat('Povestea lui. ', 30), $zi, $ora, $stare, acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

$azi   = date('Y-m-d');
$maine = date('Y-m-d', strtotime('+1 day'));
$ieri  = date('Y-m-d', strtotime('-1 day'));

$CATEGORIA = faCategoriaProbei();
$gazda     = faMembru('gazda', 'Silviu', true);

/* ==================================================================== */
sectiune('ce intră în mesaj');

/**
 * Proba se uită DOAR la evenimentele ei.
 *
 * `evenimenteleDeAzi()` citește tot ce e programat azi, iar pe serverul
 * adevărat asta înseamnă și evenimentele oamenilor. O probă care ar număra
 * rândurile ar pica în orice zi în care se întâmplă ceva în oraș — adică
 * tocmai în zilele care contează.
 */
$aleMele = static function (): array {
    $ale = [];

    foreach (evenimenteleDeAzi(200) as $e) {
        if (str_starts_with((string) $e['slug'], SEMN)) { $ale[] = $e; }
    }

    return $ale;
};

verifica('la început, niciunul de-al probei', [], $aleMele());

faEveniment($gazda, SEMN . 'seara',  'Alergare de seară',   $azi,   '19:00');
faEveniment($gazda, SEMN . 'diminea', 'Cafea de dimineață', $azi,   '08:30');

/* Ce NU are ce căuta în mesajul de azi. */
faEveniment($gazda, SEMN . 'maine',  'Ceva de mâine',       $maine, '20:00');
faEveniment($gazda, SEMN . 'ieri',   'Ceva de ieri',        $ieri,  '20:00');
faEveniment($gazda, SEMN . 'anulat', 'Ceva anulat azi',     $azi,   '21:00', 'anulat');
faEveniment($gazda, SEMN . 'astept', 'Ceva neaprobat azi',  $azi,   '22:00', 'in_asteptare');
faEveniment($gazda, SEMN . 'inch',   'Ceva încheiat azi',   $azi,   '07:00', 'incheiat');

$deAzi   = $aleMele();
$titluri = array_map(static fn(array $e): string => (string) $e['titlu'], $deAzi);

verifica('două evenimente azi', 2, count($deAzi));

/**
 * ÎN ORDINEA OREI, nu a scrierii. Cine deschide mesajul la prânz vrea să vadă
 * întâi ce urmează, iar lista citită de sus în jos trebuie să fie ziua lui.
 */
verifica('cel de dimineață primul',
    ['Cafea de dimineață', 'Alergare de seară'], $titluri);

verifica('cel de mâine nu intră',   false, in_array('Ceva de mâine', $titluri, true));
verifica('nici cel de ieri',        false, in_array('Ceva de ieri', $titluri, true));

/**
 * ANULATUL NU INTRĂ, deși ziua lui e azi. Pagina lui rămâne pe site, cu
 * motivul scris de organizator — dar a-l trimite dimineața ca pe ceva ce
 * urmează ar fi o minciună.
 */
verifica('nici cel anulat',         false, in_array('Ceva anulat azi', $titluri, true));
verifica('nici cel neaprobat',      false, in_array('Ceva neaprobat azi', $titluri, true));
verifica('nici cel deja încheiat',  false, in_array('Ceva încheiat azi', $titluri, true));

/* ==================================================================== */
sectiune('rândurile din mesaj');

$randuri = randuriPentruNewsletter($deAzi);

verifica('câte evenimente, atâtea rânduri', 2, count($randuri));
verifica('cu titlul lui', 'Cafea de dimineață', $randuri[0]['titlu']);

/** Ora fără secunde, cum se scrie între oameni: „08:30", nu „08:30:00". */
verifica('ora scrisă scurt', 'de la 08:30', $randuri[0]['cand']);
/* Orașul, apoi locul anume, despărțite cu „·" — ca pe cartonașele de pe site. */
verifica('cu orașul și locul', (oraseDisponibile()[0] ?? 'Roman') . ' · Centrul vechi',
    $randuri[0]['unde']);

/* Categoria și începutul textului, ca pe cartonaș. */
verifica('cu categoria lui', 'Probă newsletter', $randuri[0]['categorie']);
verifica('și cu un început de text', true,
    str_starts_with($randuri[0]['text'], 'Povestea lui.'));

/**
 * Textul e TĂIAT scurt: într-un mesaj cu patru evenimente, patru paragrafe
 * întregi ar fi însemnat un ecran de derulat până la primul lucru de apăsat.
 */
verifica('tăiat scurt', true, mb_strlen($randuri[0]['text'], 'UTF-8') <= 115);

/**
 * ADRESELE SUNT ÎNTREGI. Într-un e-mail nu există „pagina de acum" față de care
 * să se socotească o cale relativă: mesajul se deschide în Gmail, pe alt
 * server, în alt oraș.
 */
verifica('adresa e întreagă', true,
    str_starts_with($randuri[0]['href'], urlSite()));
verifica('și duce la adresa frumoasă', true,
    str_contains($randuri[0]['href'], '/eveniment/' . SEMN . 'diminea'));

// Evenimentele astea n-au copertă, iar categoria lor n-are imagine: caseta
// rămâne goală, dar de aceeași mărime (vezi blocul „lista" din inc/email.php).
verifica('fără poză, câmpul e gol', '', $randuri[0]['poza']);

/* ==================================================================== */
sectiune('cine primește');

$abonat   = faMembru('abonat', 'Ana',     true);
$fara     = faMembru('fara',   'Eusebiu', false);
$suspend  = faMembru('susp',   'Vlad',    true, 'suspendat');
$neconf   = faMembru('nec',    'Radu',    true, 'neconfirmat');

$idAbonati = static fn(): array => array_map(
    static fn(array $o): int => (int) $o['id'], abonatiiNewsletterului()
);

$lista = $idAbonati();

verifica('cine are bifa, primește',        true,  in_array($abonat, $lista, true));
verifica('cine n-o are, nu',               false, in_array($fara, $lista, true));

/**
 * Contul suspendat și cel neconfirmat n-au ce primi: unul e oprit, celălalt
 * n-a dovedit că adresa e a lui. A trimite pe o adresă neconfirmată înseamnă a
 * scrie unui om care nu ne-a cerut nimic.
 */
verifica('suspendatul, nu',                false, in_array($suspend, $lista, true));
verifica('neconfirmatul, nici el',         false, in_array($neconf, $lista, true));

/* ==================================================================== */
sectiune('cel mult unul pe zi');

verifica('ștampila se pune o dată',  true,  insemneazaNewsletterTrimis($abonat));

/**
 * A DOUA OARĂ NU MAI PRINDE. Hotărârea e în `WHERE`, nu într-un `SELECT` de
 * dinainte: două rulări pornite în aceeași clipă — cronul și o încercare de
 * mână — ar întreba amândouă „i-a plecat azi?", ar auzi amândouă „nu", și ar
 * trimite amândouă.
 */
verifica('a doua oară, nu',          false, insemneazaNewsletterTrimis($abonat));
verifica('și iese din lista de servit', false, in_array($abonat, $idAbonati(), true));

/* Cu ștampila de ieri, e din nou pe listă. */
db()->prepare('UPDATE membri SET newsletter_trimis_la = ? WHERE id = ?')
    ->execute([$ieri, $abonat]);

verifica('cu ștampila de ieri, revine', true, in_array($abonat, $idAbonati(), true));

/* --- trimiterea întreagă --- */

db()->prepare('UPDATE membri SET newsletter_trimis_la = NULL WHERE id = ?')->execute([$abonat]);
db()->prepare('UPDATE membri SET newsletter_trimis_la = NULL WHERE id = ?')->execute([$gazda]);

$r = trimiteNewsletterulZilei();

verifica('pleacă la amândoi abonații', 2, $r['trimise']);
verifica('fără nicio picătură',        0, $r['picate']);

/**
 * Și chiar la ai noștri: ștampila de azi e pe rândul lor. Cifra de mai sus
 * spune CÂTE au plecat, asta spune CĂTRE CINE.
 */
$stampila = static function (int $id): ?string {
    $q = db()->prepare('SELECT newsletter_trimis_la FROM membri WHERE id = ?');
    $q->execute([$id]);
    $v = $q->fetchColumn();
    return $v === false || $v === null ? null : (string) $v;
};

verifica('cu ștampila de azi pe primul', date('Y-m-d'), $stampila($gazda));
verifica('și pe al doilea',              date('Y-m-d'), $stampila($abonat));
verifica('iar cel fără bifă rămâne neștampilat', null, $stampila($fara));

$dinNou = trimiteNewsletterulZilei();

verifica('a doua rulare nu mai trimite nimic', 0, $dinNou['trimise']);
verifica('și nu mai are pe cine servi',        0, $dinNou['abonati']);

/* ==================================================================== */
sectiune('semnătura de dezabonare');

$semn = semnaturaDezabonare($abonat);

verifica('are 32 de semne hexazecimale', 1, preg_match('/^[a-f0-9]{32}$/', $semn));
verifica('aceeași de fiecare dată',      $semn, semnaturaDezabonare($abonat));
verifica('alta pentru alt om',           false, $semn === semnaturaDezabonare($fara));

verifica('linkul poartă id-ul și semnătura', true,
    str_contains(linkDezabonare($abonat), 'm=' . $abonat . '&s=' . $semn));

/* --- ce se acceptă și ce nu --- */

verifica('semnătura bună găsește omul', $abonat,
    (int) (membrulDinLinkulDeDezabonare((string) $abonat, $semn)['id'] ?? 0));

verifica('una stricată, nu', null,
    membrulDinLinkulDeDezabonare((string) $abonat, str_repeat('0', 32)));

/**
 * Semnătura altui om nu deschide contul ăsta: altfel oricine primește un
 * newsletter ar putea dezabona pe oricine, schimbând id-ul din adresă.
 */
verifica('semnătura altuia, nici ea', null,
    membrulDinLinkulDeDezabonare((string) $abonat, semnaturaDezabonare($fara)));

verifica('id strâmb, nu',      null, membrulDinLinkulDeDezabonare('abc', $semn));
verifica('id gol, nu',         null, membrulDinLinkulDeDezabonare('', $semn));
verifica('semnătură goală, nu', null, membrulDinLinkulDeDezabonare((string) $abonat, ''));
verifica('semnătură prea scurtă, nu', null,
    membrulDinLinkulDeDezabonare((string) $abonat, substr($semn, 0, 20)));

/* ==================================================================== */
sectiune('stinsul bifei');

verifica('se stinge o dată',   true,  opresteNewsletterul($abonat));
verifica('a doua oară, nu',    false, opresteNewsletterul($abonat));
verifica('și nu mai e pe listă', false, in_array($abonat, $idAbonati(), true));

/**
 * NUMAI bifa de newsletter. Cine se satură de mesajul zilnic n-a spus că nu mai
 * vrea să afle că i s-a anulat un eveniment la care se înscrisese.
 */
$q = db()->prepare('SELECT email_comentarii, email_feedback FROM membri WHERE id = ?');
$q->execute([$abonat]);
$celelalte = $q->fetch();

verifica('bifa de comentarii rămâne', 1, (int) $celelalte['email_comentarii']);
verifica('și cea de păreri, la fel',  1, (int) $celelalte['email_feedback']);

/* ==================================================================== */
if ($BAZA === '') {
    echo "\n(sar peste HTTP: dă adresa serverului ca argument, "
       . "ex. php teste/test-newsletter.php http://127.0.0.1:8099)\n";
} else {
    sectiune('pagina de dezabonare');

    $cere = static function (string $cale, bool $prinPost = false) use ($BAZA): array {
        $raw = @file_get_contents($BAZA . $cale, false, stream_context_create([
            'http' => [
                'method'        => $prinPost ? 'POST' : 'GET',
                'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content'       => '',
                'ignore_errors' => true,
                'timeout'       => 10,
            ],
        ]));

        $cod = 0;
        foreach ($http_response_header ?? [] as $rand) {
            if (preg_match('~^HTTP/\S+\s+(\d+)~', $rand, $m)) { $cod = (int) $m[1]; }
        }

        return ['cod' => $cod, 'corp' => (string) $raw];
    };

    /* Un om proaspăt, cu bifa pornită. */
    $omPagina = faMembru('pag', 'Maria', true);
    $calea    = '/dezabonare.php?m=' . $omPagina . '&s=' . semnaturaDezabonare($omPagina);

    $bifa = static function (int $id): int {
        $q = db()->prepare('SELECT newsletter FROM membri WHERE id = ?');
        $q->execute([$id]);
        return (int) $q->fetchColumn();
    };

    /**
     * SIMPLA DESCHIDERE NU STINGE NIMIC — și asta e partea care contează.
     *
     * Multe programe de e-mail și multe filtre de siguranță deschid singure
     * toate linkurile dintr-un mesaj, ca să vadă unde duc. Dacă deschiderea ar
     * dezabona, o parte dintre oameni s-ar trezi scoși de pe listă fără să fi
     * apăsat nimic — și n-ar afla niciodată de ce nu le mai vine nimic.
     */
    $r = $cere($calea);
    verifica('pagina se deschide',            200, $r['cod']);
    verifica('și întreabă, nu stinge',       true, str_contains($r['corp'], 'Nu mai vrei mesajul zilnic?'));
    verifica('bifa e neatinsă după GET',        1, $bifa($omPagina));

    /* Apăsarea omului, prin POST. */
    $r = $cere($calea, true);
    verifica('POST-ul o stinge',            true, str_contains($r['corp'], 'Gata, nu-ți mai trimitem'));
    verifica('și bifa chiar s-a stins',        0, $bifa($omPagina));

    /* A doua apăsare nu e o eroare. */
    $r = $cere($calea, true);
    verifica('a doua apăsare spune că era stins', true,
        str_contains($r['corp'], 'Nu-ți trimiteam oricum'));

    /* Semnătură stricată. */
    $r = $cere('/dezabonare.php?m=' . $omPagina . '&s=' . str_repeat('0', 32));
    verifica('semnătura stricată nu deschide nimic', true,
        str_contains($r['corp'], 'Linkul nu e bun'));

    /* Fără parametri deloc. */
    $r = $cere('/dezabonare.php');
    verifica('fără parametri, la fel', true, str_contains($r['corp'], 'Linkul nu e bun'));

    /**
     * Pagina nu se indexează: adresa ei poartă o semnătură, iar un robot care
     * ar trece pe-acolo ar duce-o în rezultatele căutării.
     */
    verifica('și nu se indexează', true,
        str_contains($r['corp'], '<meta name="robots" content="noindex">'));
}

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
