<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — lista de participanți și scoaterea de pe ea.
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

$lista = participantiiEvenimentului($evenimentId);

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
verifica('acum sunt patru', 4, count(participantiiEvenimentului($evenimentId)));

anonimizeazaMembru($diana);
verifica('contul șters iese de pe listă', 3, count(participantiiEvenimentului($evenimentId)));
verifica('și nu se mai numără', 3, numaraInterese($evenimentId)['participant']);

/* ====================== 2. SCOATEREA DE PE LISTĂ ==================== */

echo "\n=== SCOATEREA ===\n";

verifica('locurile sunt pline (4 din 4… minus cel șters)', true,
    maiSuntLocuri($eveniment, numaraInterese($evenimentId)['participant']));

excludeParticipant($evenimentId, $vlad, $organizator, 'organizator',
    'Nu a mai confirmat prezența la telefon.', false);

verifica('a plecat de pe listă', 2, count(participantiiEvenimentului($evenimentId)));
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

$html = randeazaParticipanti($evenimentId, $organizator, true);

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
$htmlGol = randeazaParticipanti($evenimentId, $organizator, false);
verifica('fără drepturi, niciun buton', 0, substr_count($htmlGol, 'data-scoate'));
verifica('dar lista se vede întreagă', 3, substr_count($htmlGol, 'class="person"'));

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
