<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — listele din taburi și scoaterea de pe cea de participanți.
 *
 * Cere BAZA DE DATE, nu și serverul: se cheamă direct funcțiile din
 * inc/interese.php, fără să treacă prin api/exclude-participant.php.
 *
 * Cum se rulează:
 *     php teste/test-participanti.php
 *
 * Își face singur oamenii și evenimentul de care are nevoie, cu nume care nu
 * se pot încurca cu ale nimănui, și le șterge la sfârșit — și dacă pică ceva
 * la mijloc, prin curata() de la coadă.
 *
 * E-mailul de înștiințare NU se trimite de aici: pleacă din API, nu din
 * excludeParticipant(). În dezvoltare ajunge oricum în private/, nu în lume
 * (vezi `dezvoltare` din inc/config.php).
 */

require_once __DIR__ . '/../inc/interese.php';
require_once __DIR__ . '/../inc/stergere.php';

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

const SEMN = 'test-participanti-';

function faMembru(string $cheie, string $nume, string $prenume, string $sex = 'M', bool $staff = false): int
{
    $q = db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, sex, data_nasterii,
                             parola_hash, stare, este_staff, creat_la, actualizat_la)
         VALUES (?,?,?,?,?,\'1990-01-01\',\'x\',\'activ\',?,?,?)'
    );

    $q->execute([
        substr('tstpar-' . $cheie, 0, 16),
        $nume, $prenume,
        SEMN . $cheie . '@invalid.local',
        $sex,
        $staff ? 1 : 0,
        acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

function curata(): void
{
    // Înscrierile și excluderile pleacă în cascadă după eveniment și după
    // oameni (vezi cheile străine din sql/016-excluderi-evenimente.sql).
    db()->prepare('DELETE e FROM evenimente e JOIN membri m ON m.id = e.membru_id
                    WHERE m.permalink LIKE ?')->execute(['tstpar-%']);
    // După permalink, nu după e-mail: anonimizarea schimbă adresa.
    db()->prepare('DELETE FROM membri WHERE permalink LIKE ?')->execute(['tstpar-%']);
}

curata();

$organizator = faMembru('org',  'Rusu',     'Ioana', 'F');
$staff       = faMembru('stf',  'Munteanu', 'Andrei', 'M', true);
$ana         = faMembru('ana',  'Neagu',    'Elena', 'F');
$vlad        = faMembru('vld',  'Solomon',  'Vlad');
$diana       = faMembru('dia',  'Popa',     'Diana', 'F');

$slug = 'tstpar-eveniment';

db()->prepare(
    'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, descriere, oras,
                             locatie, data_eveniment, ora_inceput, participanti_max,
                             stare_moderare, creat_la, actualizat_la)
     VALUES (?, (SELECT MIN(id) FROM categorii), ?, ?, ?, \'Roman\', \'Centru\',
             ?, \'18:00:00\', 4, \'aprobat\', ?, ?)'
)->execute([
    $organizator, 'Eveniment de probă pentru participanți', $slug,
    str_repeat('Text de probă. ', 30),
    date('Y-m-d', strtotime('+10 days')), acum(), acum(),
]);

$evenimentId = (int) db()->lastInsertId();
$eveniment   = evenimentDupaSlug($slug);

// Organizatorul intră automat; ceilalți se înscriu, în ordinea asta.
faOrganizatorulParticipant($evenimentId, $organizator);
salveazaInteres($evenimentId, $ana, 'participant');
salveazaInteres($evenimentId, $vlad, 'participant');
salveazaInteres($evenimentId, $diana, 'interesat');

/* ========================== 1. CINE E PE LISTĂ ====================== */

echo "=== CINE E PE LISTĂ ===\n";

$lista = oameniiCuStarea($evenimentId, 'participant');

verifica('trei participanți', 3, count($lista));
verifica('în ordinea înscrierii, organizatorul primul', $organizator, (int) $lista[0]['id']);
verifica('apoi Elena', $ana, (int) $lista[1]['id']);
verifica('apoi Vlad', $vlad, (int) $lista[2]['id']);

// Cine e doar interesat n-are ce căuta pe lista de participanți.
$ids = array_map(static fn (array $o): int => (int) $o['id'], $lista);
verifica('interesata nu e pe listă', false, in_array($diana, $ids, true));

/**
 * Un cont șters se anonimizează, nu dispare, dar omul a plecat de pe site:
 * n-are ce chip să arate și n-are de ce să țină un loc la un eveniment cu
 * număr limitat. Aceeași regulă ca la numărul de pe butoane.
 */
salveazaInteres($evenimentId, $diana, 'participant');
verifica('acum sunt patru', 4, count(oameniiCuStarea($evenimentId, 'participant')));

anonimizeazaMembru($diana);
verifica('contul șters iese de pe listă', 3, count(oameniiCuStarea($evenimentId, 'participant')));
verifica('și nu se mai numără', 3, numaraInterese($evenimentId)['participant']);

/* ====================== 2. SCOATEREA DE PE LISTĂ ==================== */

echo "\n=== SCOATEREA ===\n";

verifica('locurile sunt pline (4 din 4… minus cel șters)', true,
    maiSuntLocuri($eveniment, numaraInterese($evenimentId)['participant']));

excludeParticipant($evenimentId, $vlad, $organizator, 'organizator',
    'Nu a mai confirmat prezența la telefon.', false);

verifica('a plecat de pe listă', 2, count(oameniiCuStarea($evenimentId, 'participant')));
verifica('și numărul a scăzut', 2, numaraInterese($evenimentId)['participant']);

// Locul chiar s-a eliberat: rândul din interese_evenimente s-a dus, nu a fost
// doar însemnat. De asta atârnă socoteala locurilor rămase.
verifica('nu mai are nicio stare la evenimentul ăsta', null, interesulMeu($evenimentId, $vlad));

$q = db()->prepare('SELECT rol, motiv, interzis FROM excluderi_evenimente
                     WHERE eveniment_id = ? AND membru_id = ?');
$q->execute([$evenimentId, $vlad]);
$urma = $q->fetch();

verifica('a rămas urma faptei', true, $urma !== false);
verifica('cu rolul scris atunci', 'organizator', $urma['rol']);
verifica('și cu motivul întreg', 'Nu a mai confirmat prezența la telefon.', $urma['motiv']);
verifica('fără interdicție', 0, (int) $urma['interzis']);

/* ========================== 3. UȘA ÎNCHISĂ ========================= */

echo "\n=== UȘA ÎNCHISĂ ===\n";

verifica('scos fără bifă, se poate întoarce', false, esteInterzisLaEveniment($evenimentId, $vlad));
verifica('cine n-a fost scos deloc, la fel', false, esteInterzisLaEveniment($evenimentId, $ana));
verifica('vizitatorul fără cont nu e interzis nicăieri', false, esteInterzisLaEveniment($evenimentId, 0));

// Se înscrie la loc, apoi e scos a doua oară — de data asta cu ușa închisă.
salveazaInteres($evenimentId, $vlad, 'participant');
verifica('s-a putut înscrie din nou', 'participant', interesulMeu($evenimentId, $vlad));

excludeParticipant($evenimentId, $vlad, $staff, 'staff',
    'A treia oară când se înscrie și nu vine.', true);

verifica('acum are ușa închisă', true, esteInterzisLaEveniment($evenimentId, $vlad));

/**
 * A doua scoatere rescrie rândul de dinainte, nu adaugă unul: ținem starea de
 * acum, nu toată povestea. Regula o ține indexul unic din migrare.
 */
$q = db()->prepare('SELECT COUNT(*) FROM excluderi_evenimente
                     WHERE eveniment_id = ? AND membru_id = ?');
$q->execute([$evenimentId, $vlad]);
verifica('tot un singur rând', 1, (int) $q->fetchColumn());

$q = db()->prepare('SELECT rol, motiv FROM excluderi_evenimente
                     WHERE eveniment_id = ? AND membru_id = ?');
$q->execute([$evenimentId, $vlad]);
$urma = $q->fetch();

verifica('cu rolul din urmă', 'staff', $urma['rol']);
verifica('și motivul din urmă', 'A treia oară când se înscrie și nu vine.', $urma['motiv']);

// Interdicția e legată de UN eveniment, nu de tot site-ul.
db()->prepare(
    'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, descriere, oras,
                             locatie, data_eveniment, ora_inceput, stare_moderare,
                             creat_la, actualizat_la)
     VALUES (?, (SELECT MIN(id) FROM categorii), ?, ?, ?, \'Roman\', \'Centru\',
             ?, \'19:00:00\', \'aprobat\', ?, ?)'
)->execute([
    $staff, 'Alt eveniment de probă', 'tstpar-altul',
    str_repeat('Text de probă. ', 30),
    date('Y-m-d', strtotime('+11 days')), acum(), acum(),
]);

$altulId = (int) db()->lastInsertId();
verifica('la alt eveniment ușa e deschisă', false, esteInterzisLaEveniment($altulId, $vlad));

/* ====================== 4. CUM ARATĂ PE ECRAN ====================== */

echo "\n=== CUM ARATĂ PE ECRAN ===\n";

// Andrei intră pe listă înainte de randare: până acum rămăseseră două femei,
// iar acordul „Confirmat" n-ar fi avut pe cine să cadă.
salveazaInteres($evenimentId, $staff, 'participant');

$html = randeazaListaOameni($evenimentId, 'participant', $organizator, true);

verifica('numele e prescurtat: „R. Ioana"', true, str_contains($html, '>R. Ioana<'));
verifica('și duce la profil', true, str_contains($html, 'href="profil.php?m=tstpar-org"'));
verifica('organizatorul poartă insigna lui', true,
    str_contains($html, '<span class="person__badge">Organizator</span>'));

// Acordul se face după om, ca la rândul de sub butoane.
verifica('ea e „Confirmată"', true, str_contains($html, 'Confirmată acum'));
verifica('el e „Confirmat"', true, str_contains($html, '>Confirmat acum'));

verifica('fiecare rând se poate găsi după id', true,
    str_contains($html, 'data-participant="' . $ana . '"'));

/**
 * Organizatorul nu se scoate de pe lista lui — nici de el însuși, nici de
 * staff. Butonul nu se desenează în dreptul lui, iar regula e verificată din
 * nou în api/exclude-participant.php.
 */
verifica('trei pe listă, dar două butoane de scos', 2, substr_count($html, 'data-scoate'));

// Staff-ul care e și el pe listă își poartă insigna — și poate fi scos ca
// oricine altcineva. Doar organizatorul e neatins.
verifica('staff-ul poartă „Staff"', true,
    str_contains($html, '<span class="person__badge person__badge--staff">Staff</span>'));

// Pentru cine nu are ce umbla la listă, niciun buton.
$htmlGol = randeazaListaOameni($evenimentId, 'participant', $organizator, false);
verifica('fără drepturi, niciun buton', 0, substr_count($htmlGol, 'data-scoate'));
verifica('dar lista se vede întreagă', 3, substr_count($htmlGol, 'class="person"'));

/* ==================== 4b. TABUL „INTERESAȚI" ======================= */

echo "\n=== TABUL INTERESAȚI ===\n";

/**
 * Aceleași funcții, altă valoare în `stare`. Dacă lista de interesați ar fi
 * fost scrisă separat, s-ar fi despărțit de cea de participanți la prima
 * corectură făcută doar în una.
 */
$curios = faMembru('cur', 'Anghel', 'Tudor');
salveazaInteres($evenimentId, $curios, 'interesat');

$interesati = oameniiCuStarea($evenimentId, 'interesat');

verifica('un singur interesat', 1, count($interesati));
verifica('și e chiar el', $curios, (int) $interesati[0]['id']);

// Cele două liste nu se amestecă: o stare, un rând, un singur tab.
$idParticipanti = array_map(static fn (array $o): int => (int) $o['id'],
    oameniiCuStarea($evenimentId, 'participant'));
verifica('interesatul nu apare la participanți', false, in_array($curios, $idParticipanti, true));

$htmlInteresati = randeazaListaOameni($evenimentId, 'interesat', $organizator);

verifica('numele, la fel de prescurtat', true, str_contains($htmlInteresati, '>A. Tudor<'));

// Verbul de sub nume se schimbă cu starea: acolo scrie ce a hotărât omul,
// aici doar că se uită într-acolo.
verifica('sub nume scrie „Interesat"', true, str_contains($htmlInteresati, '>Interesat acum'));
verifica('nu „Confirmat"', false, str_contains($htmlInteresati, 'Confirmat'));

/**
 * La interesați nu se scoate nimeni: „Mă interesează" nu ocupă niciun loc.
 * Funcția primește `false` implicit, tocmai ca panoul acela să nu poată cere
 * din greșeală butoane.
 */
verifica('niciun buton de scos, nici măcar implicit', 0,
    substr_count($htmlInteresati, 'data-scoate'));
verifica('și nici dacă panoul ar cere', 0,
    substr_count(randeazaListaOameni($evenimentId, 'interesat', $organizator, false), 'data-scoate'));

/* ====================== 4c. VORBA DE DEASUPRA ====================== */

echo "\n=== VORBA DE DEASUPRA LISTEI ===\n";

verifica('niciun interesat', 'Nimeni nu s-a arătat încă interesat. Poți fi primul.',
    vorbaDespreCatiSunt(0, 'interesat', false));
verifica('niciun participant', 'Nimeni nu a confirmat încă participarea. Poți fi primul.',
    vorbaDespreCatiSunt(0, 'participant', false));

// După ce s-a terminat, la trecut: „vor participa" sub un anunț de acum trei
// luni sună a invitație la ceva ce nu se mai poate.
verifica('la un eveniment încheiat, fără nimeni', 'Nu a confirmat nimeni participarea.',
    vorbaDespreCatiSunt(0, 'participant', true));

verifica('un singur om, acord la singular', true,
    str_contains(vorbaDespreCatiSunt(1, 'participant', false), 'persoană</span></strong> a confirmat că va participa.'));
verifica('mai mulți, la plural', true,
    str_contains(vorbaDespreCatiSunt(12, 'participant', false), 'persoane</span></strong> au confirmat că vor participa.'));
verifica('interesați, la prezent', true,
    str_contains(vorbaDespreCatiSunt(3, 'interesat', false), 'sunt interesate de acest eveniment.'));
/**
 * Interesații rămân la prezent oricare ar fi starea evenimentului, fiindcă la
 * unul încheiat lista lor nu se mai arată deloc — nici tabul, nici panoul
 * (vezi event.php). Sunt oameni care s-au uitat într-acolo și n-au venit, iar
 * asta nu spune nimic despre seara care a fost.
 */
verifica('interesații rămân la prezent, oricum ar fi', true,
    str_contains(vorbaDespreCatiSunt(3, 'interesat', true), 'sunt interesate de acest eveniment.'));
verifica('și fără nimeni, tot așa',
    'Nimeni nu s-a arătat încă interesat. Poți fi primul.',
    vorbaDespreCatiSunt(0, 'interesat', true));

// Numărul poartă `data-count-for`, ca main.js să-l schimbe odată cu cel de pe
// tab și cu cel de pe butonul mare — toate au același atribut.
verifica('numărul se poate găsi din JS', true,
    str_contains(vorbaDespreCatiSunt(5, 'interesat', false), 'data-count-for="interesat"'));

/* ==================== 4d. CINE NU SE POATE ÎNSCRIE ================= */

echo "\n=== OPRELIȘTILE LA PARTICIPARE ===\n";

/**
 * Un singur loc pentru toate: de motivBlocajParticipare() atârnă și butonul
 * stins din pagină, și refuzul din api/interes.php. Dacă ar fi două funcții,
 * s-ar ajunge iar la un buton viu care duce la un refuz.
 */
$omul = static function (int $id): array {
    $q = db()->prepare('SELECT id, sex FROM membri WHERE id = ?');
    $q->execute([$id]);
    return $q->fetch();
};

// Vlad are ușa închisă de la scoaterea de mai devreme.
verifica('ușa închisă oprește participarea',
    'Nu te mai poți înscrie la acest eveniment.',
    motivBlocajParticipare($eveniment, $omul($vlad)));

verifica('cine n-a fost scos, nimic', '', motivBlocajParticipare($eveniment, $omul($ana)));

// Vizitatorul fără cont nu e oprit de nimic: butonul lui duce la intrare, iar
// ce se poate și ce nu se hotărăște după ce se știe cine e.
verifica('vizitatorul nu e oprit aici', '', motivBlocajParticipare($eveniment, null));

/* ------------------------ doar pentru un sex ---------------------- */

$doarFemei = $eveniment;
$doarFemei['gen_participanti'] = 'femei';

verifica('la un eveniment pentru femei, ea poate', '',
    motivBlocajParticipare($doarFemei, $omul($ana)));
verifica('dar el, nu', 'Evenimentul e doar pentru femei.',
    motivBlocajParticipare($doarFemei, $omul($staff)));

$doarBarbati = $eveniment;
$doarBarbati['gen_participanti'] = 'barbati';

verifica('la unul pentru bărbați, el poate', '',
    motivBlocajParticipare($doarBarbati, $omul($staff)));
verifica('dar ea, nu', 'Evenimentul e doar pentru bărbați.',
    motivBlocajParticipare($doarBarbati, $omul($ana)));

// „nespecificat" e ce sunt aproape toate: poate veni oricine.
verifica('nespecificat nu oprește pe nimeni', '',
    motivBlocajParticipare($eveniment, $omul($staff)));

/**
 * Organizatorul nu e oprit de regula de gen la evenimentul lui: e trecut
 * oricum pe listă la salvare, fiindcă e omul de care se leagă evenimentul.
 * Ioana e femeie, iar evenimentul de mai jos e pentru bărbați.
 */
verifica('organizatorul trece de regula de gen', '',
    motivBlocajParticipare($doarBarbati, $omul($organizator)));

// Ușa închisă bate genul: cine e scos nu intră nici dacă e de sexul potrivit.
$doarBarbatiSiInterzis = $doarBarbati;
verifica('ușa închisă se spune prima',
    'Nu te mai poți înscrie la acest eveniment.',
    motivBlocajParticipare($doarBarbatiSiInterzis, $omul($vlad)));

/* ====================== 4e. RĂSPUNSUL PENTRU JS =================== */

echo "\n=== RĂSPUNSUL CU PANOURI ===\n";

$panouri = raspunsulPanourilor($eveniment);

verifica('are amândouă panourile', ['interesat', 'participant'], array_keys($panouri));
verifica('fiecare cu lista, vorba și dacă e gol',
    ['lista', 'intro', 'gol'], array_keys($panouri['participant']));

verifica('lista de participanți nu e goală', false, $panouri['participant']['gol']);
verifica('și are rânduri', true, str_contains($panouri['participant']['lista'], 'class="person"'));
verifica('vorba de deasupra vine cu ea', true,
    str_contains($panouri['participant']['intro'], 'au confirmat'));

/**
 * Butoanele de scoatere NU pleacă niciodată în răspunsul de la „Voi
 * participa": cine apasă acolo e un participant oarecare, nu organizatorul
 * care face curat. Se cer pe față, și numai de api/exclude-participant.php.
 */
verifica('fără butoane de scos, implicit', 0,
    substr_count($panouri['participant']['lista'], 'data-scoate'));
verifica('cu ele, când se cer', true,
    str_contains(raspunsulPanourilor($eveniment, true)['participant']['lista'], 'data-scoate'));
verifica('dar nici atunci la interesați', 0,
    substr_count(raspunsulPanourilor($eveniment, true)['interesat']['lista'], 'data-scoate'));

/* ========================== 5. MOTIVUL ============================= */

echo "\n=== MOTIVUL ===\n";

verifica('un motiv obișnuit trece', '',
    verificaMotivExcludere('Nu a mai confirmat prezența.')['eroare']);
verifica('gol, respins', true, verificaMotivExcludere('')['eroare'] !== '');
verifica('doar spații, respins', true, verificaMotivExcludere("   \n\n ")['eroare'] !== '');
verifica('prea scurt, respins', true, verificaMotivExcludere('nu vine')['eroare'] !== '');
verifica('fix la limită, trece', '',
    verificaMotivExcludere(str_repeat('ă', MOTIV_EXCLUDERE_MIN))['eroare']);
verifica('cu unul mai puțin, nu', true,
    verificaMotivExcludere(str_repeat('ă', MOTIV_EXCLUDERE_MIN - 1))['eroare'] !== '');
verifica('prea lung, respins', true,
    verificaMotivExcludere(str_repeat('a', MOTIV_EXCLUDERE_MAX + 1))['eroare'] !== '');
verifica('altceva decât text, respins', true, verificaMotivExcludere(['a'])['eroare'] !== '');

// În bază intră text curat, neescapat — regula 9 din CLAUDE.md.
verifica('textul nu se escapează la salvare', 'Dinamo & Rapid, iar nu vine',
    verificaMotivExcludere('Dinamo & Rapid, iar nu vine')['text']);

/* =========================== curățenie ============================= */

curata();

$q = db()->prepare('SELECT COUNT(*) FROM excluderi_evenimente WHERE eveniment_id IN (?, ?)');
$q->execute([$evenimentId, $altulId]);
verifica('evenimentul șters își ia excluderile cu el', 0, (int) $q->fetchColumn());

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
