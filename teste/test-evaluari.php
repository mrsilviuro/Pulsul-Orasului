<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — notele dintre participanți.
 *
 * Cere BAZA DE DATE, nu și serverul: se cheamă direct funcțiile din
 * inc/evaluari.php, fără să treacă prin api/evaluare.php.
 *
 * Cum se rulează:
 *     php teste/test-evaluari.php
 */

require_once __DIR__ . '/../inc/evaluari.php';

$treceri = 0; $picaturi = 0;

function verifica(string $ce, $asteptat, $primit): void
{
    global $treceri, $picaturi;
    $ok = $asteptat === $primit;
    $ok ? $treceri++ : $picaturi++;
    printf("%-58s %s%s\n", $ce, $ok ? 'OK' : 'PICAT',
        $ok ? '' : "  (aștept " . var_export($asteptat, true) . ", am primit " . var_export($primit, true) . ")");
}

/* ========================= oamenii de probă ========================== */

function faMembru(string $cheie, string $nume, string $prenume): int
{
    db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, sex, data_nasterii,
                             parola_hash, stare, este_staff, creat_la, actualizat_la)
         VALUES (?,?,?,?,\'M\',\'1990-01-01\',\'x\',\'activ\',0,?,?)'
    )->execute(['tsteva-' . $cheie, $nume, $prenume,
                'test-evaluari-' . $cheie . '@invalid.local', acum(), acum()]);

    return (int) db()->lastInsertId();
}

/**
 * Un eveniment cu ziua în trecut sau în viitor, după cum are nevoie testul.
 *
 * `evenimentIncheiat()` se uită la ziua evenimentului, nu la o coloană: de aia
 * ajunge să punem data înapoi ca să avem un eveniment terminat.
 */
function faEveniment(string $slug, int $organizator, string $cand): array
{
    db()->prepare(
        'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, descriere, oras,
                                 locatie, data_eveniment, ora_inceput, stare_moderare,
                                 creat_la, actualizat_la)
         VALUES (?, (SELECT MIN(id) FROM categorii), ?, ?, ?, \'Roman\', \'Centru\',
                 ?, \'18:00:00\', \'aprobat\', ?, ?)'
    )->execute([
        $organizator, 'Eveniment ' . $slug, $slug, str_repeat('Text de probă. ', 30),
        date('Y-m-d', strtotime($cand)), acum(), acum(),
    ]);

    return evenimentDupaSlug($slug);
}

function curata(): void
{
    db()->prepare('DELETE e FROM evenimente e JOIN membri m ON m.id = e.membru_id
                    WHERE m.permalink LIKE ?')->execute(['tsteva-%']);
    db()->prepare('DELETE FROM membri WHERE permalink LIKE ?')->execute(['tsteva-%']);
}

curata();

$organizator = faMembru('org', 'Rusu',    'Ioana');
$ana         = faMembru('ana', 'Neagu',   'Elena');
$vlad        = faMembru('vld', 'Solomon', 'Vlad');
$strain      = faMembru('str', 'Popa',    'Dan');

$trecut = faEveniment('tsteva-trecut', $organizator, '-3 days');
$viitor = faEveniment('tsteva-viitor', $organizator, '+9 days');

foreach ([$trecut, $viitor] as $ev) {
    faOrganizatorulParticipant((int) $ev['id'], $organizator);
    salveazaInteres((int) $ev['id'], $ana, 'participant');
    salveazaInteres((int) $ev['id'], $vlad, 'participant');
    salveazaInteres((int) $ev['id'], $strain, 'interesat');
}

$trecutId = (int) $trecut['id'];

/* ====================== 1. CINE POATE SĂ NOTEZE ===================== */

echo "=== CINE POATE SĂ NOTEZE ===\n";

verifica('doi participanți, după eveniment', '',
    motivBlocajEvaluare($trecut, $ana, $vlad));

verifica('nu pe tine însuți', 'Nu te poți nota pe tine.',
    motivBlocajEvaluare($trecut, $ana, $ana));

verifica('vizitatorul fără cont', 'Intră în cont ca să dai o notă.',
    motivBlocajEvaluare($trecut, 0, $vlad));

// În timpul evenimentului nimeni n-are ce nota: părerea se face la sfârșit.
verifica('înainte de eveniment, nimeni', 'Notele se dau după ce se încheie evenimentul.',
    motivBlocajEvaluare($viitor, $ana, $vlad));

verifica('cine s-a arătat doar interesat nu notează',
    'Poți nota doar dacă ai fost și tu pe lista de participanți.',
    motivBlocajEvaluare($trecut, $strain, $ana));

verifica('și nici nu poate fi notat',
    'Omul ăsta n-a fost pe lista de participanți.',
    motivBlocajEvaluare($trecut, $ana, $strain));

// Scurtătura folosită de pagină, care întreabă o dată pentru toată lista.
verifica('scurtătura spune la fel, pentru participant', true,
    potNotaLaEveniment($trecut, $ana));
verifica('pentru cel doar interesat, nu', false, potNotaLaEveniment($trecut, $strain));
verifica('și nici înainte de eveniment', false, potNotaLaEveniment($viitor, $ana));

/* ========================= 2. NOTELE ============================== */

echo "\n=== NOTELE ===\n";

salveazaEvaluare($trecutId, $vlad, $ana, 4);

$nota = evaluareaMea($trecutId, $ana, $vlad);
verifica('nota s-a scris', 4, (int) $nota['stele']);
verifica('fără vorbe, deocamdată', null, $nota['text']);
verifica('și nu e automată', 0, (int) $nota['automat']);

// A doua trecere rescrie același rând: regula o ține indexul unic.
salveazaEvaluare($trecutId, $vlad, $ana, 5, 'Om de nădejde, a ajutat la strâns.');

$q = db()->prepare('SELECT COUNT(*) FROM evaluari WHERE eveniment_id = ? AND evaluat_id = ? AND evaluator_id = ?');
$q->execute([$trecutId, $vlad, $ana]);
verifica('tot un singur rând', 1, (int) $q->fetchColumn());

$nota = evaluareaMea($trecutId, $ana, $vlad);
verifica('stelele s-au schimbat', 5, (int) $nota['stele']);
verifica('și au apărut vorbele', 'Om de nădejde, a ajutat la strâns.', $nota['text']);

/**
 * Miezul: cine schimbă stelele de pe pagina evenimentului NU-și șterge cu asta
 * textul scris pe profil. N-are de unde să știe că ar face-o.
 */
salveazaEvaluare($trecutId, $vlad, $ana, 3);
$nota = evaluareaMea($trecutId, $ana, $vlad);

verifica('stelele iar s-au schimbat', 3, (int) $nota['stele']);
verifica('dar textul a rămas', 'Om de nădejde, a ajutat la strâns.', $nota['text']);

// Notele mele de la evenimentul ăsta, pentru toată lista dintr-o cerere.
salveazaEvaluare($trecutId, $organizator, $ana, 5);
$notele = noteleMeleLaEveniment($trecutId, $ana);

verifica('două note date', 2, count($notele));
verifica('a lui Vlad', 3, $notele[$vlad] ?? 0);
verifica('a organizatoarei', 5, $notele[$organizator] ?? 0);
verifica('cine n-a notat nimic', [], noteleMeleLaEveniment($trecutId, $vlad));

/* ======================== 3. MEDIA DE PE PROFIL ==================== */

echo "\n=== MEDIA DE PE PROFIL ===\n";

$gol = rezumatEvaluari($strain);
verifica('fără note, media e zero', 0.0, $gol['medie']);
verifica('și numărul la fel', 0, $gol['cate']);
verifica('dar cele cinci trepte există', [5, 4, 3, 2, 1], array_keys($gol['distributie']));

salveazaEvaluare($trecutId, $vlad, $organizator, 5);

$r = rezumatEvaluari($vlad);
verifica('două note: 3 și 5', 2, $r['cate']);
verifica('media e 4', 4.0, $r['medie']);
verifica('câte una pe fiecare treaptă', 1, $r['distributie'][5]);
verifica('și una la trei', 1, $r['distributie'][3]);
verifica('restul, zero', 0, $r['distributie'][4]);

// Media se rotunjește la o zecimală, ca să încapă lângă stele.
salveazaEvaluare($trecutId, $vlad, $vlad === $ana ? $strain : $strain, 4);
verifica('trei note: 3, 5, 4 → media 4', 4.0, rezumatEvaluari($vlad)['medie']);

/* ==================== 4. „NU S-A PREZENTAT" ======================== */

echo "\n=== NU S-A PREZENTAT ===\n";

salveazaEvaluare($trecutId, $ana, $organizator, EVALUARE_ABSENT_STELE, EVALUARE_ABSENT_TEXT, true);

$nota = evaluareaMea($trecutId, $organizator, $ana);
verifica('o stea', 1, (int) $nota['stele']);
verifica('însemnată ca automată', 1, (int) $nota['automat']);
verifica('cu textul scris de noi', EVALUARE_ABSENT_TEXT, $nota['text']);

/**
 * Harta se citește o dată, pentru toată lista, și e a tuturor: „Neprezentat" nu
 * e o părere pe care s-o vadă doar cine a scris-o, e un fapt scris pe rândul
 * omului. De aia funcția nici nu mai întreabă cine se uită.
 */
$absenti = absentiiEvenimentului($trecutId);
verifica('cel însemnat e în hartă', true, isset($absenti[$ana]));
verifica('dar nu și cine a luat note obișnuite', false, isset($absenti[$vlad]));

verifica('întrebarea de unul singur spune la fel', true, esteNeprezentat($trecutId, $ana));
verifica('și pentru ceilalți, nu', false, esteNeprezentat($trecutId, $vlad));

/**
 * Pe ea stă regula din api/evaluare.php: după însemnare, omul nu se mai
 * notează de nimeni — nici de organizatorul care a pus-o. Altfel, cel care a
 * scris „nu s-a prezentat" ar putea alege peste o săptămână cinci stele și ar
 * șterge cu ele exact ce a scris, poate după o vorbă bună de la cineva.
 *
 * O însemnare pusă a doua oară n-are ce adăuga, și se vede tot de aici.
 */
verifica('a doua oară n-are ce însemna', true, esteNeprezentat($trecutId, $ana));

/* ===================== 5. CUM ARATĂ PE ECRAN ====================== */

echo "\n=== CUM ARATĂ PE ECRAN ===\n";

/**
 * Vlad a primit trei note: 3 de la Ana (cu vorbe), 5 de la organizatoare și 4
 * de la Dan, amândouă dintr-o apăsare. Pe profil ajunge una singură.
 *
 * Stelele singure rămân nevăzute: un rând care spune „cineva ți-a dat 4 stele"
 * și atât n-are ce citi nimeni, iar zece la rând ar îneca singura părere scrisă
 * cu adevărat. În medie și în bare intră toate — vezi rezumatul de mai sus.
 */
$lista = evaluarilePrimite($vlad);

verifica('doar cea scrisă ajunge pe profil', 1, count($lista));
verifica('și e chiar textul scris', 'Om de nădejde, a ajutat la strâns.', $lista[0]['text']);
verifica('vine cu evenimentul de care atârnă', 'Eveniment tsteva-trecut',
    $lista[0]['eveniment_titlu']);

/**
 * VORBELE SE SEMNEAZĂ. Cine se așază să scrie ceva își pune și numele — de
 * asta lista vine acum cu omul de care atârnă părerea. Anonime rămân stelele,
 * care oricum nu se văd aici.
 *
 * Id-ul tot nu iese: pentru scris ajunge permalinkul, iar ce nu se citește nu
 * poate ajunge din greșeală în pagină.
 */
verifica('cu cine a scris-o', 'tsteva-ana', $lista[0]['permalink']);
verifica('fără id-ul lui', false, isset($lista[0]['evaluator_id']));

$html = randeazaEvaluari($lista);

verifica('numele, prescurtat ca la comentarii', true, str_contains($html, '>N. Elena<'));
verifica('cu legătură spre profilul lui', true,
    str_contains($html, 'profil.php?m=tsteva-ana'));
verifica('și cu chipul', true, str_contains($html, 'comment__avatar'));
verifica('cu legătură spre eveniment', true, str_contains($html, 'evaluare__eveniment'));

verifica('cine n-a primit nicio vorbă nu are listă', '',
    randeazaEvaluari(evaluarilePrimite($strain)));

$htmlAbsent = randeazaEvaluari(evaluarilePrimite($ana));
verifica('însemnarea automată se deosebește', true,
    str_contains($htmlAbsent, 'evaluare--automata'));
verifica('și spune de la cine vine', true,
    str_contains($htmlAbsent, 'Însemnare de la organizator'));
// Un fapt, nu o părere: nu se semnează cu numele organizatorului.
verifica('fără numele celui care a pus-o', false, str_contains($htmlAbsent, 'R. Ioana'));

/* --------------------------- rezumatul ---------------------------- */

/**
 * Aici intră TOATE notele, și cele fără vorbe: media e a stelelor, nu a
 * părerilor scrise. Vlad are trei note și o singură părere pe listă.
 */
$rezumat = randeazaRezumatEvaluari(rezumatEvaluari($vlad));

verifica('media, scrisă cu virgulă', true, str_contains($rezumat, '>4,0<'));
verifica('și numărul de evaluări', true, str_contains($rezumat, '3 evaluări'));
// „rating-bar" e și începutul lui „rating-bars", numele listei: se numără
// barele întregi, cu tot cu ghilimeaua de la coadă.
verifica('cu cele cinci bare', 5, substr_count($rezumat, '"rating-bar"'));

verifica('fără note, se spune pe față', true,
    str_contains(randeazaRezumatEvaluari(rezumatEvaluari($strain)), 'Nicio evaluare încă'));

/* --------------------------- stelele ------------------------------ */

$stele = randeazaSteleParticipant($vlad, 3, '', 'tsteva-vld');

verifica('stele pe care se poate apăsa', true, str_contains($stele, 'data-stele-input'));
verifica('cu nota deja dată aprinsă', true, str_contains($stele, 'data-nota="3"'));
verifica('și cu permalinkul, pentru invitația la scris', true,
    str_contains($stele, 'data-permalink="tsteva-vld"'));

$stinse = randeazaSteleParticipant($vlad, 0, 'Poți nota doar dacă ai fost și tu pe listă.', '');
verifica('cine n-are dreptul le vede stinse', true, str_contains($stinse, 'person__stele--stinse'));
verifica('fără nimic de apăsat', false, str_contains($stinse, 'data-stele-input'));
verifica('dar cu motivul la vedere', true, str_contains($stinse, 'title="Poți nota'));

/* ====================== 6. ISTORICUL DE PE PROFIL ================== */

echo "\n=== ISTORICUL DE PE PROFIL ===\n";

/**
 * Tabul de lângă evaluări: pe unde a fost omul. Cele de mai sus au pregătit
 * exact ce trebuie — două evenimente (unul trecut, unul viitor), participanți,
 * un om doar interesat, și o însemnare de neprezentare pe Ana.
 *
 * DOAR CE S-A ÎNCHEIAT. Un eveniment de peste nouă zile n-are ce căuta într-un
 * istoric: omul n-a fost încă nicăieri. Rămâne unul singur din cele două.
 */
$istoricAna = istoricEvenimente($ana);

verifica('numai cel trecut', 1, count($istoricAna));
verifica('și chiar el', 'tsteva-trecut', $istoricAna[0]['slug']);

verifica('cine s-a arătat doar interesat n-are istoric', [], istoricEvenimente($strain));
verifica('și nici cine n-are cont', [], istoricEvenimente(0));

/**
 * Al doilea fel de a fi încheiat: organizatorul apasă butonul, deși ziua abia
 * urmează. Aceeași regulă ca peste tot (vezi evenimentIncheiat).
 */
incheieEveniment($viitor);
verifica('cel încheiat cu mâna intră și el', 2, count(istoricEvenimente($ana)));
verifica('și trece în față, că e mai nou', 'tsteva-viitor', istoricEvenimente($ana)[0]['slug']);

$istoricAna = istoricEvenimente($ana);

/**
 * Absența NU scoate evenimentul din listă — spre deosebire de cifra de sus.
 * Cifra spune la câte a fost; lista e istoria lui, iar din istorie nu se șterge
 * o seară fiindcă n-a ajuns la ea. Se scrie „Absent" pe cartonaș și rămâne.
 */
$trecutulEi = null;

foreach ($istoricAna as $ev) {
    if ((string) $ev['slug'] === 'tsteva-trecut') { $trecutulEi = $ev; }
}

verifica('cel la care n-a ajuns e tot acolo', true, $trecutulEi !== null);
verifica('însemnat ca atare', 1, (int) ($trecutulEi['absent'] ?? 0));
verifica('dar nu se numără la prezențe', 1, laCateEvenimenteAFost($ana));

// Organizatoarea le-a ținut pe amândouă, deci amândouă sunt însemnate.
$istoricOrg = istoricEvenimente($organizator);
verifica('organizatoarea le are pe amândouă', 2, count($istoricOrg));
verifica('și scrie că-s ale ei', [1, 1],
    array_map(fn ($e) => (int) $e['e_organizator'], $istoricOrg));

/* --------------------------- pe ecran ------------------------------ */

$htmlIstoric = randeazaIstoric($istoricAna);

verifica('două cartonașe', 2, substr_count($htmlIstoric, '<article class="card'));
verifica('cu însemnul de absență', 1, substr_count($htmlIstoric, 'card__rol--absent'));
verifica('fără „Organizator", că nu-s ale ei', 0,
    substr_count($htmlIstoric, 'card__rol--organizator'));
verifica('duc la pagina evenimentului', true,
    str_contains($htmlIstoric, 'href="event.php?slug=tsteva-trecut"'));

// Nimic nu vine ascuns din PHP: ascunsul îl face main.js, câte ISTORIC_DEODATA.
verifica('și niciunul nu vine ascuns din PHP', 0, substr_count($htmlIstoric, 'ascuns'));

verifica('la organizatoare, celălalt însemn', 2,
    substr_count(randeazaIstoric($istoricOrg), 'card__rol--organizator'));

verifica('fără nimic, fără HTML', '', randeazaIstoric([]));

/* =========================== 7. NUMERELE ========================== */

echo "\n=== NUMĂRUL DE STELE PRIMIT DE LA BROWSER ===\n";

verifica('trei stele', 3, stelePrimite(3));
verifica('ca text, tot trei', 3, stelePrimite('3'));
verifica('zero nu e notă', 0, stelePrimite(0));
verifica('nici șase', 0, stelePrimite(6));
verifica('nici minus doi', 0, stelePrimite(-2));
verifica('nici „patru"', 0, stelePrimite('patru'));
verifica('nici o listă', 0, stelePrimite([4]));

echo "\n=== TEXTUL DE SUB NOTĂ ===\n";

verifica('gol e în regulă: doar stele', '', verificaTextEvaluare('')['eroare']);
verifica('și nu întoarce text', '', verificaTextEvaluare('   ')['text']);
verifica('două cuvinte de înțeles trec', '',
    verificaTextEvaluare('Om serios, a venit la timp.')['eroare']);
verifica('„ok" nu ajută pe nimeni', true, verificaTextEvaluare('ok')['eroare'] !== '');
verifica('prea lung, respins', true,
    verificaTextEvaluare(str_repeat('a', EVALUARE_TEXT_MAX + 1))['eroare'] !== '');

/* =========================== curățenie ============================= */

curata();

$q = db()->prepare('SELECT COUNT(*) FROM evaluari WHERE eveniment_id = ?');
$q->execute([$trecutId]);
verifica('evenimentul șters își ia notele cu el', 0, (int) $q->fetchColumn());

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
