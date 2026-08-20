<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — lista de evenimente de pe prima pagină.
 *
 * Cere BAZA DE DATE și, pentru ultima parte, SERVERUL pornit — acolo se
 * verifică api/lista-evenimente.php, care e singurul API de citire de pe site.
 *
 * Cum se rulează:
 *     php teste/test-prima-pagina.php
 *     php teste/test-prima-pagina.php http://127.0.0.1:8128
 *
 * Fără adresă, partea cu serverul se sare și se spune limpede că s-a sărit.
 */

require_once __DIR__ . '/../inc/evenimente.php';

$baza = rtrim($argv[1] ?? '', '/');

$treceri = 0; $picaturi = 0;

function verifica(string $ce, $asteptat, $primit): void
{
    global $treceri, $picaturi;
    $ok = $asteptat === $primit;
    $ok ? $treceri++ : $picaturi++;
    printf("%-58s %s%s\n", $ce, $ok ? 'OK' : 'PICAT',
        $ok ? '' : "  (aștept " . var_export($asteptat, true) . ", am primit " . var_export($primit, true) . ")");
}

/* ========================= datele de probă ========================== */

const SEMN = 'tstprima-';

function faMembru(): int
{
    db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, sex, data_nasterii,
                             parola_hash, stare, este_staff, creat_la, actualizat_la)
         VALUES (?,?,?,?,\'F\',\'1990-01-01\',\'x\',\'activ\',0,?,?)'
    )->execute([SEMN . 'org', 'Rusu', 'Ioana', SEMN . 'org@invalid.local', acum(), acum()]);

    return (int) db()->lastInsertId();
}

/**
 * Un eveniment cu ziua, orașul, categoria și starea cerute.
 *
 * Ziua se dă în zile față de azi: negativ înseamnă trecut, pozitiv viitor.
 */
function faEveniment(int $org, string $cheie, int $peste, string $oras,
                     int $categorieId, string $stare = 'aprobat'): int
{
    db()->prepare(
        'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, descriere, oras,
                                 locatie, data_eveniment, ora_inceput, stare_moderare,
                                 creat_la, actualizat_la)
         VALUES (?,?,?,?,?,?,\'Centru\',?,\'18:00:00\',?,?,?)'
    )->execute([
        $org, $categorieId, 'Ev ' . $cheie, SEMN . $cheie, str_repeat('Text de probă. ', 20),
        $oras, date('Y-m-d', strtotime(($peste >= 0 ? '+' : '') . $peste . ' days')),
        $stare, acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

function curata(): void
{
    db()->prepare('DELETE FROM evenimente WHERE slug LIKE ?')->execute([SEMN . '%']);
    db()->prepare('DELETE FROM membri WHERE permalink LIKE ?')->execute([SEMN . '%']);
}

/** Slugurile din rezultat, doar ale noastre, în ordinea în care au venit. */
function aleNoastre(array $rezultat): array
{
    $sluguri = [];

    foreach ($rezultat['evenimente'] as $ev) {
        if (str_starts_with((string) $ev['slug'], SEMN)) {
            $sluguri[] = substr((string) $ev['slug'], strlen(SEMN));
        }
    }

    return $sluguri;
}

curata();

/**
 * Baza poate avea și alte evenimente — de la celelalte teste, sau puse de
 * mână — iar ele s-ar amesteca în numărătoare. Le dăm deoparte pe durata
 * probei și le punem înapoi la sfârșit. NIMIC NU SE ȘTERGE: se schimbă doar
 * starea, iar starea dinainte se ține minte aici, în PHP.
 *
 * Punerea la loc se leagă de sfârșitul scriptului, nu de ultimul rând: dacă
 * pică ceva la mijloc, evenimentele altcuiva nu rămân ascunse.
 */
$deoparte = db()->query(
    'SELECT id, stare_moderare FROM evenimente WHERE slug NOT LIKE \'' . SEMN . '%\''
)->fetchAll();

register_shutdown_function(static function () use ($deoparte): void {
    $q = db()->prepare('UPDATE evenimente SET stare_moderare = ? WHERE id = ?');

    foreach ($deoparte as $ev) {
        $q->execute([$ev['stare_moderare'], (int) $ev['id']]);
    }
});

db()->exec('UPDATE evenimente SET stare_moderare = \'respins\' WHERE slug NOT LIKE \'' . SEMN . '%\'');

$org = faMembru();

$categorii = categoriiEvenimente();
$catSport  = (int) $categorii[0]['id'];
$catAlta   = (int) $categorii[1]['id'];

/**
 * Al doilea oraș, dacă există.
 *
 * Site-ul pleacă la drum cu unul singur în config.php, iar proba trebuie să
 * treacă și așa. Când e doar unul, se sare peste comparațiile care au nevoie
 * de două — și se spune limpede că s-a sărit, ca nimeni să nu creadă că sunt
 * verificate.
 */
$orase    = oraseDisponibile();
$orasUnu  = $orase[0] ?? 'Roman';
$orasDoi  = $orase[1] ?? null;
$alDoilea = $orasDoi ?? $orasUnu;

/* Trei care urmează, în altă ordine decât cea în care se scriu. */
faEveniment($org, 'viitor-departe', 20, $orasUnu, $catSport);
faEveniment($org, 'viitor-aproape',  2, $orasUnu, $catAlta);
faEveniment($org, 'viitor-mijloc',   9, $alDoilea, $catSport);

/* Două încheiate, tot amestecate. */
faEveniment($org, 'trecut-demult',  -30, $orasUnu, $catSport);
faEveniment($org, 'trecut-recent',   -2, $alDoilea, $catAlta);

/* Unul încheiat cu mâna, deși ziua lui abia vine. */
$idInchManual = faEveniment($org, 'incheiat-manual', 15, $orasUnu, $catSport, 'incheiat');

/* Ce nu se vede pe prima pagină. */
faEveniment($org, 'anulat',    5, $orasUnu, $catSport, 'anulat');
faEveniment($org, 'asteptare', 5, $orasUnu, $catSport, 'in_asteptare');
faEveniment($org, 'respins',   5, $orasUnu, $catSport, 'respins');

/* ============================ 1. ORDINEA ============================ */

echo "=== ORDINEA ===\n";

$toate = aleNoastre(evenimenteDePePrima());

verifica('se văd toate cele publice', 7, count($toate));

/**
 * ANULATUL stă printre cele trecute, nu printre cele care urmează — chiar dacă
 * ziua lui e în viitor. Seara aceea nu mai vine pentru nimeni.
 *
 * Locul lui între cele trecute îl dă data, ca la toate celelalte: ziua lui e
 * mai proaspătă decât a celor două din urmă, deci vine înaintea lor.
 */
verifica('întâi ce urmează, de la cel mai apropiat, apoi ce a fost, de la cel mai proaspăt',
    ['viitor-aproape', 'viitor-mijloc', 'viitor-departe',
     'incheiat-manual', 'anulat', 'trecut-recent', 'trecut-demult'],
    $toate);

/* ============================ 2. CE LIPSEȘTE ======================== */

echo "\n=== CE NU AJUNGE PE PRIMA PAGINĂ ===\n";

/**
 * CEL ANULAT AJUNGE, de acum. A stat ascuns o vreme și era greșit: de el atârnă
 * oameni care își făcuseră planuri, iar un anunț care dispare din listă îi lasă
 * să creadă că l-au visat. Stă la locul lui, stins, cu „Anulat" în colț.
 */
verifica('cel anulat E în listă', true, in_array('anulat', $toate, true));
verifica('cel în așteptare',    false, in_array('asteptare', $toate, true));
verifica('cel respins',          false, in_array('respins', $toate, true));

/**
 * Cel încheiat de mână e acolo, dar în grupa celor trecute, deși ziua lui abia
 * vine. Aceeași regulă ca peste tot: „încheiat" bate ceasul.
 */
$rand = array_search('incheiat-manual', $toate, true);
verifica('cel încheiat cu mâna stă între cele trecute', 3, $rand);

/* ========================== 3. ÎNSEMNAREA ========================== */

echo "\n=== ÎNSEMNAREA CELOR ÎNCHEIATE ===\n";

$rezultat = evenimenteDePePrima();
$dupaSlug = [];

foreach ($rezultat['evenimente'] as $ev) {
    $dupaSlug[substr((string) $ev['slug'], strlen(SEMN))] = (int) $ev['incheiat'];
}

verifica('cel de peste două zile nu e încheiat', 0, $dupaSlug['viitor-aproape']);
verifica('cel de acum două zile, da',            1, $dupaSlug['trecut-recent']);
verifica('și cel închis cu mâna',                1, $dupaSlug['incheiat-manual']);

$html = randeazaListaEvenimente($rezultat['evenimente']);

// Trei încheiate plus cel anulat: aceeași clasă, poza la fel de stinsă.
verifica('patru cartonașe poartă semnul', 4, substr_count($html, 'card--incheiat'));
verifica('și scrie „Încheiat" pe trei', 3, substr_count($html, '>Încheiat</span>'));
verifica('iar pe al patrulea, „Anulat"', 1, substr_count($html, '>Anulat</span>'));
verifica('șapte cartonașe cu totul', 7, substr_count($html, '<article class="card'));

/* Cifrele de pe poză: câți vin și câte comentarii. */
verifica('fiecare cartonaș poartă cifrele', 7, substr_count($html, 'card__cifre'));

/* ============================ 4. FILTRELE =========================== */

echo "\n=== FILTRELE ===\n";

verifica('după categorie',
    ['viitor-aproape', 'trecut-recent'],
    aleNoastre(evenimenteDePePrima('', (string) $categorii[1]['slug'])));

if ($orasDoi !== null) {
    verifica('după oraș', ['viitor-mijloc', 'trecut-recent'],
        aleNoastre(evenimenteDePePrima($orasDoi)));

    verifica('amândouă deodată', ['trecut-recent'],
        aleNoastre(evenimenteDePePrima($orasDoi, (string) $categorii[1]['slug'])));
} else {
    // Cu un singur oraș în config, filtrul nu poate despărți nimic — dar tot
    // trebuie să lase să treacă ce e în el.
    verifica('cu un singur oraș, filtrul lasă tot să treacă', 7,
        count(aleNoastre(evenimenteDePePrima($orasUnu))));
    echo "(sar peste comparațiile pe două orașe: în config.php e doar unul)\n";
}

/**
 * Ce nu e în listele noastre înseamnă „toate", nu o eroare: o adresă veche,
 * dintr-o zi în care exista alt oraș, trebuie să arate prima pagină.
 */
verifica('un oraș inventat e cernut la intrare', '', orasulCerut('Vaslui'));
verifica('și o categorie inventată', '', categoriaCeruta('balet'));
verifica('dar cele adevărate trec', $orasUnu, orasulCerut($orasUnu));

/* ======================= 5. TEANCURILE ============================== */

echo "\n=== TEANCURILE ===\n";

$primul = evenimenteDePePrima('', '', 0, 4);

verifica('primul teanc are patru', 4, count($primul['evenimente']));
verifica('și spune că mai sunt', true, $primul['mai_sunt']);

$aldoilea = evenimenteDePePrima('', '', 4, 4);

verifica('al doilea aduce restul', 3, count($aldoilea['evenimente']));
verifica('și spune că s-a terminat', false, $aldoilea['mai_sunt']);

verifica('fără suprapuneri', [],
    array_intersect(aleNoastre($primul), aleNoastre($aldoilea)));

verifica('dincolo de capăt, nimic', 0,
    count(evenimenteDePePrima('', '', 500, 4)['evenimente']));

// Nimeni nu poate cere o mie deodată: numărul se strânge la capătul de sus.
verifica('cererea de o mie se strânge la zece', 7,
    count(evenimenteDePePrima('', '', 0, 1000)['evenimente']));

/* ==================== 6. LIVE, ȘI CATEGORIILE GOALE ================= */

echo "\n=== LIVE ===\n";

/**
 * „Live" e ce a început și încă nu s-a încheiat — o clipă între celelalte
 * două stări, care nu se scrie nicăieri în bază. Se vede doar când cineva se
 * uită, deci se socotește la desenare.
 */
faEveniment($org, 'chiar-acum', 0, $orasUnu, $catSport);
db()->prepare('UPDATE evenimente SET ora_inceput = ? WHERE slug = ?')
    ->execute([date('H:i:00', strtotime('-2 hours')), SEMN . 'chiar-acum']);

$acumRand = null;

foreach (evenimenteDePePrima()['evenimente'] as $ev) {
    if ((string) $ev['slug'] === SEMN . 'chiar-acum') { $acumRand = $ev; }
}

verifica('cel de azi, de acum două ore, e în listă', true, $acumRand !== null);
verifica('și nu e socotit încheiat', 0, (int) $acumRand['incheiat']);
verifica('dar a început', true, evenimentAInceput($acumRand));

$htmlLive = randeazaListaEvenimente([$acumRand]);
verifica('cartonașul lui poartă semnul', true, str_contains($htmlLive, 'card--live'));
verifica('și scrie „Live"', true, str_contains($htmlLive, '>Live</span>'));
verifica('fără semnul de încheiat', false, str_contains($htmlLive, 'card--incheiat'));

// Unul care abia urmează n-are niciun semn.
$maiVine = null;

foreach (evenimenteDePePrima()['evenimente'] as $ev) {
    if ((string) $ev['slug'] === SEMN . 'viitor-aproape') { $maiVine = $ev; }
}

$htmlVine = randeazaListaEvenimente([$maiVine]);
verifica('cel care abia urmează, curat', false, str_contains($htmlVine, 'card--live'));
verifica('și tot curat', false, str_contains($htmlVine, 'card--incheiat'));

// Iar unul trecut e încheiat, nu „live".
$celTrecut = null;

foreach (evenimenteDePePrima()['evenimente'] as $ev) {
    if ((string) $ev['slug'] === SEMN . 'trecut-recent') { $celTrecut = $ev; }
}

$htmlTrecut = randeazaListaEvenimente([$celTrecut]);
verifica('cel trecut e încheiat', true, str_contains($htmlTrecut, 'card--incheiat'));
verifica('și nu e niciodată Live', false, str_contains($htmlTrecut, 'card--live'));

echo "\n=== CATEGORIILE DIN FILTRE ===\n";

/**
 * Numai cele în care s-a pus ceva. O categorie goală e un buton care duce la
 * un ecran gol.
 */
$inFiltre = array_map(static fn(array $c): string => (string) $c['slug'], categoriiCuEvenimente());
$toateCat = array_map(static fn(array $c): string => (string) $c['slug'], categoriiEvenimente());

verifica('categoria cu evenimente e în filtre', true,
    in_array((string) $categorii[0]['slug'], $inFiltre, true));

/**
 * Ultima categorie nu are niciun eveniment de-al nostru — iar ale altora sunt
 * date deoparte mai sus, deci trebuie să lipsească.
 */
$cateGoale = 0;

foreach ($toateCat as $slug) {
    if (!in_array($slug, $inFiltre, true)) { $cateGoale++; }
}

verifica('și rămân pe dinafară cele goale', true, $cateGoale > 0);
verifica('dar formularul le are pe toate', true, count($toateCat) > count($inFiltre));

/* ==================== 7. AR PUTEA SĂ TE INTERESEZE ================== */

echo "\n=== SUGESTIILE DE PE PAGINA UNUI EVENIMENT ===\n";

$deAcasa = evenimenteSugerate(0, 10);
$sluguriSugerate = [];

foreach ($deAcasa as $ev) {
    $sluguriSugerate[] = substr((string) $ev['slug'], strlen(SEMN));
}

sort($sluguriSugerate);

verifica('numai ce n-a început încă',
    ['viitor-aproape', 'viitor-departe', 'viitor-mijloc'], $sluguriSugerate);

verifica('fără cel care se petrece chiar acum', false,
    in_array('chiar-acum', $sluguriSugerate, true));
verifica('fără cele încheiate', false, in_array('trecut-recent', $sluguriSugerate, true));
verifica('fără cel închis cu mâna', false, in_array('incheiat-manual', $sluguriSugerate, true));
verifica('fără cel anulat', false, in_array('anulat', $sluguriSugerate, true));
verifica('fără cel în așteptare', false, in_array('asteptare', $sluguriSugerate, true));

// Nu se propune pe el însuși.
$q = db()->prepare('SELECT id FROM evenimente WHERE slug = ?');
$q->execute([SEMN . 'viitor-aproape']);
$idAproape = (int) $q->fetchColumn();

$fara = array_map(static fn(array $e): int => (int) $e['id'], evenimenteSugerate($idAproape, 10));
verifica('nu se propune pe el însuși', false, in_array($idAproape, $fara, true));

verifica('și se cer câte două, atât', 2, count(evenimenteSugerate(0)));
verifica('nici cererea de o mie nu trece de doisprezece', 3,
    count(evenimenteSugerate(0, 1000)));

/* ======================== 8. ADRESA FILTRATĂ ======================== */

echo "\n=== ADRESA ===\n";

verifica('fără filtre, adresă curată', 'index.php', adresaFiltrata());
verifica('numai categoria', 'index.php?categorie=sport', adresaFiltrata('', 'sport'));
verifica('numai orașul', 'index.php?oras=Roman', adresaFiltrata('Roman'));
verifica('amândouă', 'index.php?oras=Roman&categorie=sport', adresaFiltrata('Roman', 'sport'));
verifica('diacriticele se scriu ca la carte', 'index.php?oras=Ia%C8%99i', adresaFiltrata('Iași'));

/* ============================= 7. API ============================== */

if ($baza === '') {
    echo "\n(sar peste API: dă adresa serverului ca argument, ex. "
       . "php teste/test-prima-pagina.php http://127.0.0.1:8128)\n";
} else {
    echo "\n=== API ===\n";

    $cheama = static function (string $coada) use ($baza): array {
        $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
        $raw = @file_get_contents($baza . '/api/lista-evenimente.php' . $coada, false, $ctx);
        $cod = 0;

        foreach ($http_response_header ?? [] as $rand) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $rand, $m) === 1) { $cod = (int) $m[1]; }
        }

        return ['cod' => $cod, 'corp' => json_decode((string) $raw, true) ?: []];
    };

    /**
     * Câte sunt ale noastre chiar acum. Nu un număr scris de mână: tot ce s-a
     * adăugat mai sus (cel „chiar acum", de pildă) intră și el în listă, iar o
     * cifră fixă aici s-ar strica la fiecare probă nouă.
     */
    $cateAvem = count(aleNoastre(evenimenteDePePrima('', '', 0, EVENIMENTE_PRIMA_TURA)));

    $r = $cheama('');
    verifica('răspunde cu ok', true, $r['corp']['ok'] ?? false);
    verifica('cu primul teanc întreg', $cateAvem, $r['corp']['cate'] ?? 0);
    verifica('și spune că s-a terminat', false, $r['corp']['mai_sunt'] ?? true);
    verifica('cu cartonașe gata desenate', $cateAvem,
        substr_count($r['corp']['html'] ?? '', '<article class="card'));

    $r = $cheama('?de_la=2');
    verifica('de la al treilea încolo, patru', 4, $r['corp']['cate'] ?? 0);

    $r = $cheama('?oras=' . rawurlencode($alDoilea));
    verifica('filtrul de oraș merge și prin API',
        count(aleNoastre(evenimenteDePePrima($alDoilea, '', 0, EVENIMENTE_PRIMA_TURA))),
        $r['corp']['cate'] ?? 0);

    $r = $cheama('?oras=Vaslui');
    verifica('un oraș inventat nu e o eroare', $cateAvem, $r['corp']['cate'] ?? 0);

    // Numărul cerut nu se ia de la browser: serverul îl hotărăște după `de_la`.
    $r = $cheama('?de_la=0&cate=999');
    verifica('nu se poate cere cât vrea browserul', $cateAvem, $r['corp']['cate'] ?? 0);
}

/* =========================== curățenie ============================= */

// Evenimentele celorlalte teste se întorc de unde au plecat prin
// register_shutdown_function, de mai sus — și atunci când scriptul pică.
curata();

$q = db()->prepare('SELECT COUNT(*) FROM evenimente WHERE slug LIKE ?');
$q->execute([SEMN . '%']);
verifica('evenimentele de probă s-au dus', 0, (int) $q->fetchColumn());

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
