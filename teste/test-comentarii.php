<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — comentariile de sub un eveniment.
 *
 * Cere BAZA DE DATE, nu și serverul: se cheamă direct funcțiile din
 * inc/comentarii.php, fără să treacă prin api/comentarii.php.
 *
 * Cum se rulează:
 *     php teste/test-comentarii.php
 *
 * Își face singur oamenii și evenimentul de care are nevoie, cu nume care nu
 * se pot încurca cu ale nimănui, și le șterge la sfârșit — și dacă pică ceva
 * la mijloc, prin curata() de la coadă.
 */

require_once __DIR__ . '/../inc/comentarii.php';
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

const SEMN = 'test-comentarii-';

function faMembru(string $cheie, string $nume, string $prenume, bool $staff = false): int
{
    $q = db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, sex, data_nasterii,
                             parola_hash, stare, este_staff, creat_la, actualizat_la)
         VALUES (?,?,?,?,\'M\',\'1990-01-01\',\'x\',\'activ\',?,?,?)'
    );

    $q->execute([
        // Permalinkul are loc de 16 caractere, iar SEMN mănâncă tot: de aceea
        // aici e o prescurtare a lui, nu el întreg.
        substr('tstcom-' . $cheie, 0, 16),
        $nume, $prenume,
        SEMN . $cheie . '@invalid.local',
        $staff ? 1 : 0,
        acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

function curata(): void
{
    // Comentariile, aprecierile și înscrierile pleacă în cascadă după
    // eveniment și după oameni (vezi cheile străine din sql/015-comentarii.sql).
    db()->prepare('DELETE FROM evenimente WHERE slug LIKE ?')->execute([SEMN . '%']);
    /**
     * După permalink, nu după e-mail.
     *
     * Ștergerea unui cont îl anonimizează, nu îl scoate din bază (vezi
     * inc/stergere.php), iar anonimizarea îi schimbă și adresa. Omul de probă
     * pe care testul îl trece prin ștergere ar fi scăpat de curățenie și ar fi
     * încurcat rularea următoare, cu permalinkul lui rămas ocupat.
     */
    db()->prepare('DELETE FROM membri WHERE permalink LIKE ?')->execute(['tstcom-%']);
}

curata();

$organizator = faMembru('org',   'Rusu',      'Ioana');
$staff       = faMembru('staff', 'Munteanu',  'Andrei', true);
$participant = faMembru('part',  'Neagu',     'Elena');
$strain      = faMembru('str',   'Solomon',   'Vlad');

$slug = SEMN . 'eveniment';

db()->prepare(
    'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, descriere, oras,
                             locatie, data_eveniment, ora_inceput, stare_moderare,
                             creat_la, actualizat_la)
     VALUES (?, (SELECT MIN(id) FROM categorii), ?, ?, ?, \'Roman\', \'Centru\',
             ?, \'18:00:00\', \'aprobat\', ?, ?)'
)->execute([
    $organizator, 'Eveniment de probă pentru comentarii', $slug,
    str_repeat('Text de probă. ', 30),
    date('Y-m-d', strtotime('+10 days')), acum(), acum(),
]);

$evenimentId = (int) db()->lastInsertId();

// Organizatorul intră automat ca participant; pe Elena o trecem noi.
faOrganizatorulParticipant($evenimentId, $organizator);
salveazaInteres($evenimentId, $participant, 'participant');

$eveniment = evenimentDupaSlug($slug);

function context(array $eveniment, int $membruId, bool $eStaff = false): array
{
    $randuri = comentariileEvenimentului((int) $eveniment['id'], $membruId);

    return [
        'organizator_id' => (int) $eveniment['membru_id'],
        'membru_id'      => $membruId,
        'e_staff'        => $eStaff,
        'poate_scrie'    => true,
        'nume'           => numeleComentatorilor($randuri),
    ];
}

/* ===================== 1. CELE DOUĂ NIVELURI ======================== */

echo "=== CELE DOUĂ NIVELURI ===\n";

$principal = salveazaComentariu($evenimentId, $organizator, 'Ne vedem în centru vechi.');
$c1 = comentariuDupaId($principal);

verifica('un comentariu nou e principal', null, $c1['parinte_id']);
verifica('și nu răspunde nimănui', null, $c1['raspuns_la_id']);

$raspuns = salveazaComentariu($evenimentId, $participant, 'Venim și noi.', $c1);
$c2 = comentariuDupaId($raspuns);

verifica('răspunsul stă sub principal', $principal, (int) $c2['parinte_id']);
verifica('fără mențiune: se vede de unde e', null, $c2['raspuns_la_id']);

/**
 * Miezul: un răspuns la un răspuns NU coboară pe al treilea nivel. Se pune
 * sub același principal și doar spune cui îi răspunde.
 */
$alDoilea = salveazaComentariu($evenimentId, $strain, 'La ce oră?', $c2);
$c3 = comentariuDupaId($alDoilea);

verifica('răspunsul la un răspuns rămâne pe nivelul doi', $principal, (int) $c3['parinte_id']);
verifica('dar spune cui îi răspunde', $raspuns, (int) $c3['raspuns_la_id']);

/* ========================== 2. AȘEZAREA ============================= */

echo "\n=== AȘEZAREA PE ECRAN ===\n";

$alDoileaPrincipal = salveazaComentariu($evenimentId, $staff, 'Se poate ajunge cu autobuzul.');

$fire = grupeazaComentarii(comentariileEvenimentului($evenimentId));

verifica('două fire', 2, count($fire));
verifica('principalele, de la nou la vechi', $alDoileaPrincipal, (int) $fire[0]['id']);
verifica('firul cu discuție e al doilea', $principal, (int) $fire[1]['id']);
verifica('cu două răspunsuri', 2, count($fire[1]['raspunsuri']));
verifica('răspunsurile, de la vechi la nou', $raspuns, (int) $fire[1]['raspunsuri'][0]['id']);
verifica('nimic pe al treilea nivel', [], $fire[1]['raspunsuri'][0]['raspunsuri'] ?? []);

verifica('numărul de pe tab', 4, numaraComentarii($evenimentId));

/* =========================== 3. INSIGNE ============================= */

echo "\n=== INSIGNE ===\n";

$randuri = comentariileEvenimentului($evenimentId);
$dupaId  = [];

foreach ($randuri as $rand) {
    $dupaId[(int) $rand['id']] = $rand;
}

$insigne = static fn (int $id): string
    => insigneleComentariului($dupaId[$id], (int) $eveniment['membru_id']);

verifica('organizatorul poartă „Organizator"',
    '<span class="badge badge--author">Organizator</span>', $insigne($principal));

/**
 * Organizatorul e și participant — rândul i se scrie automat la salvarea
 * evenimentului. Insigna aia nu se mai scrie: ar spune ce se înțelege oricum
 * din cealaltă.
 */
verifica('dar nu și „Participant"', false, str_contains($insigne($principal), 'Participant'));

verifica('cine vine poartă „Participant"',
    '<span class="badge">Participant</span>', $insigne($raspuns));

verifica('staff-ul poartă „Staff"',
    '<span class="badge badge--staff">Staff</span>', $insigne($alDoileaPrincipal));

verifica('cine doar trece pe-acolo, nimic', '', $insigne($alDoilea));

// Staff ȘI organizator: amândouă, în ordinea asta.
db()->prepare('UPDATE membri SET este_staff = 1 WHERE id = ?')->execute([$organizator]);
$randuriDupa = comentariileEvenimentului($evenimentId);

foreach ($randuriDupa as $rand) {
    if ((int) $rand['id'] === $principal) {
        verifica('staff + organizator, amândouă',
            '<span class="badge badge--staff">Staff</span>'
            . '<span class="badge badge--author">Organizator</span>',
            insigneleComentariului($rand, (int) $eveniment['membru_id']));
    }
}

db()->prepare('UPDATE membri SET este_staff = 0 WHERE id = ?')->execute([$organizator]);

/* ========================== 4. APRECIERI ============================ */

echo "\n=== APRECIERI ===\n";

$r = comutaApreciere($principal, $participant);
verifica('prima apăsare pune', true, $r['apreciat']);
verifica('și se numără', 1, $r['cate']);

$r = comutaApreciere($principal, $strain);
verifica('încă un om', 2, $r['cate']);

$r = comutaApreciere($principal, $participant);
verifica('a doua apăsare ia înapoi', false, $r['apreciat']);
verifica('numărul scade', 1, $r['cate']);

// „Le-am apreciat eu?" — se citește odată cu comentariile.
$aleMele = comentariileEvenimentului($evenimentId, $strain);

foreach ($aleMele as $rand) {
    if ((int) $rand['id'] === $principal) {
        verifica('cine a apreciat își vede butonul apăsat', 1, (int) $rand['apreciat']);
        verifica('și numărul întreg', 1, (int) $rand['aprecieri']);
    }
}

$aleAltuia = comentariileEvenimentului($evenimentId, $participant);

foreach ($aleAltuia as $rand) {
    if ((int) $rand['id'] === $principal) {
        verifica('cine nu a apreciat, nu', 0, (int) $rand['apreciat']);
    }
}

$fara = comentariileEvenimentului($evenimentId, 0);

foreach ($fara as $rand) {
    if ((int) $rand['id'] === $principal) {
        verifica('vizitatorul vede numărul, nu și butonul apăsat', 0, (int) $rand['apreciat']);
    }
}

/* ======================= 5. CINE POATE UMBLA ======================== */

echo "\n=== CINE POATE UMBLA LA UN COMENTARIU ===\n";

$alLui = comentariuDupaId($raspuns);

verifica('al lui, poate', true,  poateModificaComentariul($alLui, $participant, false));
verifica('al altuia, nu',  false, poateModificaComentariul($alLui, $strain, false));
verifica('staff-ul, la orice', true, poateModificaComentariul($alLui, $strain, true));
verifica('vizitatorul, la nimic', false, poateModificaComentariul($alLui, 0, false));

/* ========================== 6. CORECTURA =========================== */

echo "\n=== CORECTURA ===\n";

verifica('la naștere, necorectat', null, $alLui['editat_la']);

actualizeazaComentariu($raspuns, 'Venim și noi, cu tot cu copii.');
$dupa = comentariuDupaId($raspuns);

verifica('textul se schimbă', 'Venim și noi, cu tot cu copii.', $dupa['text']);
verifica('și se ține minte că s-a umblat la el', true, $dupa['editat_la'] !== null);

/* ========================== 7. ȘTERGEREA =========================== */

echo "\n=== ȘTERGEREA ===\n";

// a) un principal FĂRĂ răspunsuri se duce de tot
$ce = stergeComentariu(comentariuDupaId($alDoileaPrincipal));
verifica('principal fără răspunsuri: șters de tot', 'sters', $ce['fel']);
verifica('și chiar nu mai e', null, comentariuDupaId($alDoileaPrincipal));

// b) un principal CU răspunsuri se golește, nu dispare
$ce = stergeComentariu(comentariuDupaId($principal));
verifica('principal cu răspunsuri: golit', 'golit', $ce['fel']);

$piatra = comentariuDupaId($principal);
verifica('rândul rămâne', true, $piatra !== null);
verifica('însemnat ca șters', 1, (int) $piatra['sters']);
verifica('fără text', '', $piatra['text']);

$q = db()->prepare('SELECT COUNT(*) FROM comentarii_aprecieri WHERE comentariu_id = ?');
$q->execute([$principal]);
verifica('aprecierile s-au dus cu textul', 0, (int) $q->fetchColumn());

verifica('cel golit nu se mai numără', 2, numaraComentarii($evenimentId));

// Nici staff-ul nu mai are ce-i face.
verifica('la o piatră de mormânt nu se mai umblă', false,
    poateModificaComentariul($piatra, $strain, true));

// c) răspunsurile se șterg de tot
$ce = stergeComentariu(comentariuDupaId($alDoilea));
verifica('răspunsul: șters de tot', 'sters', $ce['fel']);
verifica('piatra rămâne, mai are un răspuns', null, $ce['parinte_sters']);

// d) ultimul răspuns de sub o piatră de mormânt o ia și pe ea
$ce = stergeComentariu(comentariuDupaId($raspuns));
verifica('ultimul răspuns duce și piatra', $principal, $ce['parinte_sters']);
verifica('piatra chiar a plecat', null, comentariuDupaId($principal));
verifica('nu a mai rămas nimic', 0, numaraComentarii($evenimentId));

/* ====================== 8. CUM ARATĂ PE ECRAN ====================== */

echo "\n=== CUM ARATĂ PE ECRAN ===\n";

$unu = salveazaComentariu($evenimentId, $organizator, 'Dinamo & Rapid, la 18:00.');
$doi = salveazaComentariu($evenimentId, $participant, 'Mulțumim!', comentariuDupaId($unu));
$trei = salveazaComentariu($evenimentId, $strain, 'Și eu.', comentariuDupaId($doi));

$ctx  = context($eveniment, $organizator);
$fire = grupeazaComentarii(comentariileEvenimentului($evenimentId, $organizator));
$html = randeazaComentarii($fire, $ctx);

verifica('numele e prescurtat: „R. Ioana"', true, str_contains($html, '>R. Ioana<'));
verifica('numele duce la profil', true,
    str_contains($html, 'href="profil.php?m=' . 'tstcom-org'));

verifica('„&" se escapează la afișare', true, str_contains($html, 'Dinamo &amp; Rapid'));
verifica('dar în bază a rămas curat', 'Dinamo & Rapid, la 18:00.',
    comentariuDupaId($unu)['text']);

/**
 * Mențiunea stă ÎN text, în capul primului paragraf — nu deasupra, lângă
 * numele autorului. Acolo se citea ca încă o insignă a lui: „R. Ioana către
 * N. Elena" pare o însușire a lui Ioana, nu începutul vorbei ei.
 */
verifica('răspunsul la un răspuns începe cu @numele lui',
    '<p class="comment__text"><a class="comment__mentiune" href="profil.php?m=tstcom-part">'
    . '@N. Elena</a> Și eu.</p>',
    (static function (string $html): string {
        preg_match('#<p class="comment__text"><a class="comment__mentiune".*?</p>#', $html, $g);
        return $g[0] ?? '';
    })($html));

verifica('primul răspuns, fără mențiune', 1, substr_count($html, 'comment__mentiune'));

// Spațiul dintre nume și vorbă e în HTML, nu în CSS: el desparte două cuvinte,
// deci trebuie să vină cu ele la copierea textului.
verifica('un spațiu între @nume și text', true, str_contains($html, '</a> Și eu.'));

// „@" e în legătură, nu lipit lângă ea.
verifica('@ intră în legătură', true, str_contains($html, '">@N. Elena</a>'));

verifica('răspunsurile stau lângă articol, nu în el', true,
    str_contains($html, '</article><ul class="comment__replies"'));

verifica('organizatorul își vede uneltele', true,
    str_contains($html, 'data-edit') && str_contains($html, 'data-delete'));

// Al altuia: apreciere și răspuns, dar nu corectură.
$ctxStrain  = context($eveniment, $strain);
$htmlStrain = randeazaComentarii(
    grupeazaComentarii(comentariileEvenimentului($evenimentId, $strain)), $ctxStrain);

verifica('la comentariile altora, două unelte pentru fiecare', 3,
    substr_count($htmlStrain, 'data-reply'));
verifica('și corectură doar la al lui', 1, substr_count($htmlStrain, 'data-edit'));

// Staff-ul: peste tot.
$ctxStaff  = context($eveniment, $staff, true);
$htmlStaff = randeazaComentarii(
    grupeazaComentarii(comentariileEvenimentului($evenimentId, $staff)), $ctxStaff);

verifica('staff-ul poate umbla la toate', 3, substr_count($htmlStaff, 'data-edit'));
verifica('și le poate șterge pe toate', 3, substr_count($htmlStaff, 'data-delete'));

// Vizitatorul: vede numărul de aprecieri, dar nicio unealtă de-ale lui.
$ctxGol  = context($eveniment, 0);
$htmlGol = randeazaComentarii(
    grupeazaComentarii(comentariileEvenimentului($evenimentId, 0)), $ctxGol);

verifica('vizitatorul nu poate edita nimic', 0, substr_count($htmlGol, 'data-edit'));
verifica('nici șterge', 0, substr_count($htmlGol, 'data-delete'));
verifica('dar vede butonul de apreciere', 3, substr_count($htmlGol, 'data-like-count'));

/* ------------------------ piatra de mormânt ----------------------- */

stergeComentariu(comentariuDupaId($unu));

$htmlPiatra = randeazaComentarii(
    grupeazaComentarii(comentariileEvenimentului($evenimentId, $organizator)), $ctx);

verifica('piatra spune ce s-a întâmplat', true,
    str_contains($htmlPiatra, 'Acest comentariu a fost șters'));
verifica('în loc de nume, „Comentariu șters"', true,
    str_contains($htmlPiatra, '>Comentariu șters<'));
verifica('cu un chip fără chip', true, str_contains($htmlPiatra, POZA_IMPLICITA));
verifica('numele omului nu mai apare pe ea', false,
    str_contains($htmlPiatra, 'profil.php?m=' . 'tstcom-org'));
verifica('și nimic de apăsat', 2, substr_count($htmlPiatra, 'data-like-count'));
verifica('răspunsurile de sub ea au rămas', true, str_contains($htmlPiatra, 'Mulțumim!'));

/* ------------------------- contul șters --------------------------- */

echo "\n=== CONTUL ȘTERS ===\n";

anonimizeazaMembru($participant);

/**
 * Contextul se face din nou, nu se reia cel de dinainte.
 *
 * În el stau numele pentru „către X", strânse din rândurile citite atunci —
 * iar unul dintre ele tocmai a plecat de pe site. Aceeași purtare ca în
 * event.php, care îl construiește la fiecare cerere din rândurile ei.
 */
$ctxDupa  = context($eveniment, $organizator);
$htmlDupa = randeazaComentarii(
    grupeazaComentarii(comentariileEvenimentului($evenimentId, $organizator)), $ctxDupa);

verifica('vorbele rămân în discuție', true, str_contains($htmlDupa, 'Mulțumim!'));
verifica('dar fără nume', true, str_contains($htmlDupa, '>Utilizator șters<'));
verifica('și fără drum spre profil', false,
    str_contains($htmlDupa, 'profil.php?m=' . 'tstcom-part'));
verifica('nici insigna de participant nu mai stă pe el', 0,
    substr_count($htmlDupa, '<span class="badge">Participant</span>'));

/* =========================== curățenie ============================= */

curata();

$q = db()->prepare('SELECT COUNT(*) FROM comentarii WHERE eveniment_id = ?');
$q->execute([$evenimentId]);
verifica('evenimentul șters își ia comentariile cu el', 0, (int) $q->fetchColumn());

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
