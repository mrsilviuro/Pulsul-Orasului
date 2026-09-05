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

/* --------------------------- Doar din consolă -------------------------- */

/**
 * Probele nu se rulează din browser. `teste/.htaccess` le închide dosarul, dar
 * el se citește doar pe Apache cu AllowOverride pornit — verificarea asta ține
 * oriunde. Aceeași pereche de încuietori ca la cron/.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Se rulează doar din linia de comandă.\n");
}
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
verifica('fără aprecieri, principalele de la nou la vechi', $alDoileaPrincipal, (int) $fire[0]['id']);
verifica('firul cu discuție e al doilea', $principal, (int) $fire[1]['id']);
verifica('cu două răspunsuri', 2, count($fire[1]['raspunsuri']));
verifica('răspunsurile, de la vechi la nou', $raspuns, (int) $fire[1]['raspunsuri'][0]['id']);
verifica('nimic pe al treilea nivel', [], $fire[1]['raspunsuri'][0]['raspunsuri'] ?? []);

verifica('numărul de pe tab', 4, numaraComentarii($evenimentId));

/* ======================== 2b. ORDINEA LOR ========================== */

echo "\n=== ORDINEA PRINCIPALELOR ===\n";

/**
 * Sus stă ce a ridicat lumea, iar la egalitate hotărăște vechimea.
 *
 * Cel de-al doilea principal e mai nou, deci stă primul cât timp amândouă au
 * zero aprecieri. O singură apreciere pe cel vechi trebuie să-l ridice peste el.
 */
comutaApreciere($principal, $participant);

$dupaAprecieri = grupeazaComentarii(comentariileEvenimentului($evenimentId));

verifica('o apreciere ridică vechiul deasupra noului', $principal, (int) $dupaAprecieri[0]['id']);
verifica('cel fără aprecieri coboară', $alDoileaPrincipal, (int) $dupaAprecieri[1]['id']);

// Încă doi oameni pe cel nou: trece el în față.
comutaApreciere($alDoileaPrincipal, $participant);
comutaApreciere($alDoileaPrincipal, $strain);

$dupaAprecieri = grupeazaComentarii(comentariileEvenimentului($evenimentId));

verifica('două aprecieri bat una', $alDoileaPrincipal, (int) $dupaAprecieri[0]['id']);
verifica('și cel cu una rămâne al doilea', $principal, (int) $dupaAprecieri[1]['id']);

/**
 * Răspunsurile NU se socotesc după aprecieri: acolo nu e o listă, e o discuție.
 * Al doilea răspuns e cel care spune „La ce oră?" — o apreciere pe el n-are voie
 * să-l ridice înaintea celui la care răspunde.
 */
comutaApreciere($alDoilea, $participant);
comutaApreciere($alDoilea, $strain);
comutaApreciere($alDoilea, $organizator);

$fireAcum = grupeazaComentarii(comentariileEvenimentului($evenimentId));
$firulCuDiscutie = null;

foreach ($fireAcum as $fir) {
    if ((int) $fir['id'] === $principal) {
        $firulCuDiscutie = $fir;
    }
}

verifica('răspunsurile rămân de la vechi la nou', $raspuns,
    (int) $firulCuDiscutie['raspunsuri'][0]['id']);
verifica('oricâte aprecieri ar avea cel de-al doilea', $alDoilea,
    (int) $firulCuDiscutie['raspunsuri'][1]['id']);

// Înapoi la zero, ca restul testului să pornească de unde pornea.
comutaApreciere($principal, $participant);
comutaApreciere($alDoileaPrincipal, $participant);
comutaApreciere($alDoileaPrincipal, $strain);
comutaApreciere($alDoilea, $participant);
comutaApreciere($alDoilea, $strain);
comutaApreciere($alDoilea, $organizator);

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
    str_contains($html, 'href="/profil.php?m=' . 'tstcom-org'));

verifica('„&" se escapează la afișare', true, str_contains($html, 'Dinamo &amp; Rapid'));
verifica('dar în bază a rămas curat', 'Dinamo & Rapid, la 18:00.',
    comentariuDupaId($unu)['text']);

/**
 * Mențiunea stă ÎN text, în capul primului paragraf — nu deasupra, lângă
 * numele autorului. Acolo se citea ca încă o insignă a lui: „R. Ioana către
 * N. Elena" pare o însușire a lui Ioana, nu începutul vorbei ei.
 */
verifica('răspunsul la un răspuns începe cu @numele lui',
    '<p class="comment__text"><a class="comment__mentiune" href="/profil.php?m=tstcom-part">'
    . '<span class="comment__at">@</span>N. Elena</a> Și eu.</p>',
    (static function (string $html): string {
        preg_match('#<p class="comment__text"><a class="comment__mentiune".*?</p>#', $html, $g);
        return $g[0] ?? '';
    })($html));

verifica('primul răspuns, fără mențiune', 1, substr_count($html, 'comment__mentiune'));

// Spațiul dintre nume și vorbă e în HTML, nu în CSS: el desparte două cuvinte,
// deci trebuie să vină cu ele la copierea textului.
verifica('un spațiu între @nume și text', true, str_contains($html, '</a> Și eu.'));

// „@" e în legătură, nu lipit lângă ea — și are învelișul lui, ca să poată fi
// ridicat din CSS la rândul literelor de lângă.
verifica('@ intră în legătură', true,
    str_contains($html, '"><span class="comment__at">@</span>N. Elena</a>'));

verifica('răspunsurile stau lângă articol, nu în el', true,
    str_contains($html, '</article><ul class="comment__replies"'));

// Antetul, pe două rânduri: cine a scris, și dedesubt când. Punctul dintre ele
// a plecat odată cu rândul comun.
verifica('numele și insignele, într-un rând al lor', true,
    str_contains($html, '<div class="comment__cine"><a class="comment__author"'));
verifica('ora, pe rândul ei', true, str_contains($html, '<div class="comment__cand"><time'));
verifica('fără punct de despărțire în antet', false, str_contains($html, '<span class="dot"'));

verifica('organizatorul își vede uneltele', true,
    str_contains($html, 'data-edit') && str_contains($html, 'data-delete'));

// Al altuia: apreciere și răspuns, dar nu corectură.
$ctxStrain  = context($eveniment, $strain);
$htmlStrain = randeazaComentarii(
    grupeazaComentarii(comentariileEvenimentului($evenimentId, $strain)), $ctxStrain);

/**
 * DOUĂ DIN TREI, nu trei: la propriul comentariu nu se răspunde. Un răspuns la
 * propria vorbă nu e un răspuns, e o adăugire — iar pentru adăugiri e
 * „Editează", chiar lângă. Vlad a scris unul dintre cele trei, deci acolo are
 * „Editează", nu „Răspunde"; cele două rânduri se citesc împreună.
 */
verifica('răspunde la ale altora, nu la al lui', 2,
    substr_count($htmlStrain, 'data-reply'));
verifica('și corectură doar la al lui', 1, substr_count($htmlStrain, 'data-edit'));

/* Cele două nu se suprapun niciodată: pe un comentariu e ori unul, ori altul. */
verifica('cele trei sunt acoperite o dată', 3,
    substr_count($htmlStrain, 'data-reply') + substr_count($htmlStrain, 'data-edit'));

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

/**
 * ȘI LE VEDE PE TOATE TREI „RĂSPUNDE", ca pe cel de apreciere: apăsat, îl duce
 * la intrare, cu întoarcere fix aici. Ascuns, i-ar fi arătat o discuție la care
 * pare că n-are cum să ia parte. Nefiind conectat, nu e autorul nimănui — deci
 * regula „nu la al tău" nu-l atinge.
 */
verifica('vizitatorul vede toate butoanele de răspuns', 3,
    substr_count($htmlGol, 'data-reply'));

/* ------------------- la al tău nu se răspunde --------------------- */

/**
 * Regula, luată de-a dreptul. O citesc DOUĂ locuri — rândul de unelte, ca
 * purtare frumoasă, și api/comentarii.php, ca regulă — deci trebuie să fie
 * adevărată în afara amândurora.
 */
verifica('la al meu, nu',        false, poateRaspunde(['membru_id' => $strain, 'sters' => 0], $strain));
verifica('la al altuia, da',     true,  poateRaspunde(['membru_id' => $organizator, 'sters' => 0], $strain));
verifica('vizitatorul poate',    true,  poateRaspunde(['membru_id' => $organizator, 'sters' => 0], 0));
verifica('la unul golit, nimeni', false, poateRaspunde(['membru_id' => $organizator, 'sters' => 1], $strain));

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

/* ========================== RAPOARTELE ============================== */

echo "\n=== RAPOARTELE ===\n";

$deRaportat = salveazaComentariu($evenimentId, $participant, 'Un comentariu de raportat.');
$alRaportat = comentariuDupaId($deRaportat);

/* --- cine poate raporta --- */

verifica('un străin poate raporta',  true,  poateRaporta($alRaportat, $strain));
verifica('și staff-ul poate',        true,  poateRaporta($alRaportat, $staff));
verifica('AUTORUL nu poate',         false, poateRaporta($alRaportat, $participant));
verifica('nici cine nu e conectat',  false, poateRaporta($alRaportat, 0));

/* Un comentariu golit n-are ce să fie raportat. */
$golit = salveazaComentariu($evenimentId, $participant, 'Ăsta se golește.');
salveazaComentariu($evenimentId, $strain, 'Un răspuns, ca să rămână piatra.', [
    'id' => $golit, 'parinte_id' => null, 'membru_id' => $participant,
]);
stergeComentariu(comentariuDupaId($golit));
verifica('un comentariu golit nu se raportează', false,
    poateRaporta(comentariuDupaId($golit), $strain));

/* --- comutarea --- */

verifica('la început, niciun raport', 0, numaraRapoarte($deRaportat));

$r = comutaRaport($deRaportat, $strain);
verifica('prima apăsare raportează', true, $r['raportat']);
verifica('și se numără',             1,    numaraRapoarte($deRaportat));

$r = comutaRaport($deRaportat, $strain);
verifica('a doua îl ia înapoi', false, $r['raportat']);
verifica('și numărul scade',    0,     numaraRapoarte($deRaportat));

/**
 * Doi oameni, două rapoarte care nu se calcă.
 *
 * Ăsta e motivul pentru care rapoartele stau într-un tabel, nu într-o coloană
 * pe comentariu: cu un simplu semn, al doilea om care apasă l-ar fi stins pe
 * al primului.
 */
comutaRaport($deRaportat, $strain);
comutaRaport($deRaportat, $staff);
verifica('doi oameni, două rapoarte', 2, numaraRapoarte($deRaportat));

comutaRaport($deRaportat, $strain);
verifica('unul îl retrage, al celuilalt rămâne', 1, numaraRapoarte($deRaportat));

/* --- cum arată pe ecran --- */

$htmlStrainRap = randeazaComentarii(
    grupeazaComentarii(comentariileEvenimentului($evenimentId, $strain)),
    context($eveniment, $strain));

verifica('străinul vede steagul', true, str_contains($htmlStrainRap, 'data-raport'));

/**
 * Steagul stă în ANTET, după oră — nu în rândul de unelte de jos.
 *
 * Se măsoară pe pozițiile din HTML: ora vine înaintea lui, iar rândul de
 * unelte după. Dacă butonul s-ar întoarce printre unelte, a doua socoteală ar
 * cădea pe dos.
 */
/* Rândul comentariului de raportat, singur — pe pagină sunt și altele. */
$randulLuiRap = '';
foreach (explode('data-comentariu="', $htmlStrainRap) as $bucata) {
    if (str_starts_with($bucata, (string) $deRaportat . '"')) {
        $randulLuiRap = explode('</article>', $bucata)[0];
        break;
    }
}

verifica('am găsit rândul de raportat', true, $randulLuiRap !== '');

$undeOra    = strpos($randulLuiRap, 'comment__cand');
$undeSteag  = strpos($randulLuiRap, 'data-raport');
$undeUnelte = strpos($randulLuiRap, 'comment__tools');

verifica('după ora comentariului', true,
    $undeOra !== false && $undeSteag !== false && $undeSteag > $undeOra);
verifica('și ÎNAINTEA rândului de unelte', true,
    $undeUnelte !== false && $undeSteag < $undeUnelte);
verifica('nu mai e o unealtă printre celelalte', false,
    str_contains($htmlStrainRap, 'comment__tool comment__tool--raport'));

/* Cu vorba scrisă, nu doar semnul: un steag singur nu spune nimic nimănui. */
verifica('cu „Raportează" scris lângă el', true,
    str_contains($htmlStrainRap, '>Raportează</span>'));
verifica('și nu ascunsă doar pentru cititoarele de ecran', false,
    str_contains($htmlStrainRap, 'sr-only" data-raport-text'));

$htmlAutor = randeazaComentarii(
    grupeazaComentarii(comentariileEvenimentului($evenimentId, $participant)),
    context($eveniment, $participant));

/**
 * Autorul nu-l vede pe al lui. Are alte comentarii pe pagină, ale altora, deci
 * se caută anume în rândul lui.
 */
$randulLui = '';
foreach (explode('data-comentariu="', $htmlAutor) as $bucata) {
    if (str_starts_with($bucata, (string) $deRaportat . '"')) { $randulLui = $bucata; break; }
}

verifica('am găsit rândul autorului', true, $randulLui !== '');
verifica('iar el NU are steag pe comentariul lui', false,
    str_contains(explode('</article>', $randulLui)[0], 'data-raport'));

/* Steagul aprins pentru cine a raportat deja. */
comutaRaport($deRaportat, $strain);

$htmlDupaRaport = randeazaComentarii(
    grupeazaComentarii(comentariileEvenimentului($evenimentId, $strain)),
    context($eveniment, $strain));

verifica('cine a raportat vede steagul aprins', true,
    str_contains($htmlDupaRaport, 'is-raportat'));
verifica('iar vorba de lângă el se schimbă', true,
    str_contains($htmlDupaRaport, '>Raportat</span>'));
verifica('și butonul o spune și cu vorba', true,
    str_contains($htmlDupaRaport, 'aria-pressed="true"'));

/* Numărul NU se arată nicăieri: e treaba staff-ului. */
verifica('numărul rapoartelor nu ajunge în pagină', false,
    str_contains($htmlDupaRaport, 'data-raport-count'));

/* Ștergerea comentariului își ia rapoartele cu ea, în cascadă. */
$idCuRaport = salveazaComentariu($evenimentId, $participant, 'Ăsta se șterge de tot.');
comutaRaport($idCuRaport, $strain);
verifica('are un raport', 1, numaraRapoarte($idCuRaport));

stergeComentariu(comentariuDupaId($idCuRaport));
verifica('șters de tot, rapoartele pleacă în cascadă', 0, numaraRapoarte($idCuRaport));

/* ===================== 12. CINE AFLĂ PE E-MAIL ====================== */

echo "\n=== CÂND E DESCHISĂ DISCUȚIA ===\n";

/**
 * ÎNTREBARE DEOSEBITĂ DE evenimentPublicat(), dinadins. Aceea răspunde la „se
 * poartă lumea cu el ca și cu unul de pe site" — de ea atârnă înscrierile,
 * scoaterile de pe listă, indexarea. Asta răspunde doar la „au oamenii unde
 * vorbi", iar acolo ANULATUL intră și el.
 *
 * Comentariile la un anunț anulat au fost o vreme închise, și era greșit: o
 * ieșire anulată e tocmai momentul în care oamenii au ceva de zis unul altuia,
 * iar organizatorul rămânea fără felul cel mai firesc de a-și cere scuze.
 */
$cuStarea = static fn (string $stare): array => ['stare_moderare' => $stare];

verifica('la unul aprobat, deschisă',   true,  discutiaEDeschisa($cuStarea('aprobat')));
verifica('la unul încheiat, deschisă',  true,  discutiaEDeschisa($cuStarea('incheiat')));
verifica('LA UNUL ANULAT, deschisă',    true,  discutiaEDeschisa($cuStarea('anulat')));
verifica('cât așteaptă, închisă',       false, discutiaEDeschisa($cuStarea('in_asteptare')));
verifica('respins, închisă',            false, discutiaEDeschisa($cuStarea('respins')));
verifica('fără stare, închisă',         false, discutiaEDeschisa([]));

/**
 * Se deschide DOAR discuția. Nu poți spune „vin" la ceva ce nu se mai ține, iar
 * api/interes.php cere mai departe evenimentPublicat() — cele două întrebări
 * trebuie să răspundă altfel la „anulat", altfel n-avea rost să fie două.
 */
verifica('dar anulatul rămâne nepublicat', false, evenimentPublicat($cuStarea('anulat')));

echo "\n=== ÎNȘTIINȚĂRILE ===\n";

/**
 * Aici se verifică doar CINE ar trebui înștiințat. Trimiterea propriu-zisă e
 * în api/comentarii.php (instiinteazaDeComentariu) și nu se cheamă de aici:
 * testul ăsta nu pornește serverul, iar un e-mail plecat dintr-un test ar fi
 * exact felul de lucru care ajunge într-o zi în lume.
 */

$cine = static fn (?array $om): ?int => $om === null ? null : (int) $om['id'];

/* ------------------------ comentariu principal --------------------- */

// Un străin scrie sub anunț → află organizatorul.
verifica('la un comentariu principal, află organizatorul',
    $organizator, $cine(omDeInstiintatLaComentariu($eveniment, $strain, null)));

// Organizatorul scrie sub propriul anunț → nu-și scrie singur.
verifica('organizatorul nu-și scrie singur',
    null, $cine(omDeInstiintatLaComentariu($eveniment, $organizator, null)));

/* ----------------------------- răspunsuri --------------------------- */

$alStrainului = salveazaComentariu($evenimentId, $strain, 'Întrebare de la un străin.');
$catreStrain  = comentariuDupaId($alStrainului);

verifica('la un răspuns, află autorul comentariului',
    $strain, $cine(omDeInstiintatLaComentariu($eveniment, $organizator, $catreStrain)));

verifica('și nu organizatorul, când răspunsul nu-i e adresat',
    $strain, $cine(omDeInstiintatLaComentariu($eveniment, $participant, $catreStrain)));

verifica('cine își răspunde singur nu primește nimic',
    null, $cine(omDeInstiintatLaComentariu($eveniment, $strain, $catreStrain)));

/**
 * Răspunsul dat unui RĂSPUNS. Comentariul ajunge tot pe nivelul al doilea (vezi
 * salveazaComentariu), dar vestea pleacă spre cel căruia i s-a apăsat butonul,
 * nu spre autorul principalului de deasupra.
 */
// Un om nou, nu $participant: acela a trecut prin ștergere de cont mai sus, iar
// un cont anonimizat n-ar mai primi nimic — verificarea ar fi trecut degeaba.
$mijlocas   = faMembru('mij', 'Barbu', 'Cristina');
$alTreilea  = salveazaComentariu($evenimentId, $mijlocas, 'Răspund eu.', $catreStrain);
$catreElMij = comentariuDupaId($alTreilea);

verifica('la un răspuns dat unui răspuns, află tot cel apăsat',
    $mijlocas, $cine(omDeInstiintatLaComentariu($eveniment, $strain, $catreElMij)));

/* ------------------------- bifa din setări -------------------------- */

db()->prepare('UPDATE membri SET email_comentarii = 0 WHERE id = ?')->execute([$organizator]);

verifica('cu bifa stinsă, organizatorul nu mai primește nimic',
    null, $cine(omDeInstiintatLaComentariu($eveniment, $strain, null)));

db()->prepare('UPDATE membri SET email_comentarii = 1 WHERE id = ?')->execute([$organizator]);

verifica('pusă la loc, primește iar',
    $organizator, $cine(omDeInstiintatLaComentariu($eveniment, $strain, null)));

/* -------------------------- conturi plecate ------------------------- */

$plecat   = faMembru('plec', 'Toma', 'Radu');
$alPlecat = comentariuDupaId(salveazaComentariu($evenimentId, $plecat, 'Scriu și plec.'));

verifica('cât e activ, primește', $plecat,
    $cine(omDeInstiintatLaComentariu($eveniment, $strain, $alPlecat)));

anonimizeazaMembru($plecat);

verifica('contul anonimizat nu mai primește nimic', null,
    $cine(omDeInstiintatLaComentariu($eveniment, $strain, $alPlecat)));

db()->prepare('UPDATE membri SET stare = \'suspendat\' WHERE id = ?')->execute([$mijlocas]);

verifica('nici contul suspendat', null,
    $cine(omDeInstiintatLaComentariu($eveniment, $strain, $catreElMij)));

db()->prepare('UPDATE membri SET stare = \'activ\' WHERE id = ?')->execute([$mijlocas]);

verifica('scos din suspendare, primește iar', $mijlocas,
    $cine(omDeInstiintatLaComentariu($eveniment, $strain, $catreElMij)));

/* ------------------- ancora spre care duce mesajul ------------------ */

/**
 * Linkul din e-mail e „…#c<id>", iar ținta lui e id-ul de pe articol. Dacă
 * dispare de acolo, mesajul rămâne valid dar aterizează în capul paginii — o
 * stricăciune tăcută, care nu se vede în niciun alt test.
 */
$htmlAncora = randeazaComentarii(
    grupeazaComentarii(comentariileEvenimentului($evenimentId, $strain)),
    context($eveniment, $strain));

verifica('comentariul poartă ancora spre care trimite e-mailul', true,
    str_contains($htmlAncora, 'id="c' . $alStrainului . '"'));

/* ================= ANUNȚUL IMPORTANT AL ORGANIZATORULUI ============== */

echo "\n=== ANUNȚUL IMPORTANT ===\n";

/* ---------------------------- cine poate ---------------------------- */

verifica('organizatorul poate',            true,  poateMarcaImportant($eveniment, $organizator));
verifica('un participant, nu',             false, poateMarcaImportant($eveniment, $participant));
verifica('un străin, nici atât',           false, poateMarcaImportant($eveniment, $strain));
verifica('nici nelogatul',                 false, poateMarcaImportant($eveniment, 0));

/**
 * NICI STAFF-UL, pe evenimentul altcuiva. Bifa nu e o unealtă de moderare, e
 * vocea celui care ține evenimentul: „ne mutăm pe terenul de alături" e o
 * vorbă pe care omul casei n-are de unde s-o știe adevărată.
 */
verifica('nici staff-ul, pe anunțul altuia', false, poateMarcaImportant($eveniment, $staff));

/**
 * NU LA UN RĂSPUNS, nici măcar organizatorului: un răspuns stă sub comentariul
 * la care răspunde, deci n-are cum să urce deasupra tuturor, iar un „Important"
 * care nu e primul e o promisiune neținută.
 */
verifica('nici organizatorul, la un răspuns', false,
    poateMarcaImportant($eveniment, $organizator, true));

/* ------------------------ steagul chiar se scrie -------------------- */

$anunt = salveazaComentariu($evenimentId, $organizator,
    'Ne mutăm pe terenul de alături, lângă vestiare.', null, true);

verifica('steagul e scris în bază', 1, (int) comentariuDupaId($anunt)['important']);

/**
 * UN RĂSPUNS NU POATE FI DE CĂPĂTÂI, oricât s-ar cere de la funcție. Poarta e
 * poateMarcaImportant(), dar salveazaComentariu() o mai închide o dată: e
 * chemată din două locuri, iar al doilea ar putea într-o zi să uite să întrebe.
 */
$raspunsImportant = salveazaComentariu($evenimentId, $organizator, 'Și încă ceva.',
    comentariuDupaId($anunt), true);

verifica('un răspuns nu se face important', 0,
    (int) comentariuDupaId($raspunsImportant)['important']);

/* --------------------------- unde se așază -------------------------- */

/**
 * DEASUPRA TUTUROR, oricâte aprecieri ar avea celelalte. Ca să nu fie o
 * potriveală, îi dăm altui comentariu tot ce se poate strânge: aprecierile a
 * trei oameni. Fără steag, el ar fi fost primul.
 */
$iubit = salveazaComentariu($evenimentId, $participant, 'Ce idee bună!');

foreach ([$organizator, $participant, $strain] as $cine) {
    comutaApreciere($iubit, $cine);
}

$fire = grupeazaComentarii(comentariileEvenimentului($evenimentId, $strain));

verifica('anunțul stă primul', $anunt, (int) $fire[0]['id']);
verifica('cel cu trei aprecieri, abia al doilea', $iubit, (int) $fire[1]['id']);

/**
 * MAI MULTE ANUNȚURI: de la nou la vechi, iar aprecierile nu intră deloc în
 * socoteală. Ultima veste o înlocuiește pe cea dinainte, deci una veche și
 * îndrăgită n-are ce căuta peste una proaspătă.
 */
foreach ([$organizator, $participant] as $cine) {
    comutaApreciere($anunt, $cine);
}

$alDoileaAnunt = salveazaComentariu($evenimentId, $organizator,
    'Aduceți și bani gheață, terenul se plătește pe loc.', null, true);

$fire = grupeazaComentarii(comentariileEvenimentului($evenimentId, $strain));

verifica('cel mai nou anunț e primul',   $alDoileaAnunt, (int) $fire[0]['id']);
verifica('cel vechi, al doilea',         $anunt,         (int) $fire[1]['id']);
verifica('și abia apoi cel îndrăgit',    $iubit,         (int) $fire[2]['id']);

/* ------------------------- cum se vede pe ecran --------------------- */

$htmlAnunt = randeazaComentarii($fire, context($eveniment, $strain));

verifica('caseta e altfel', true, str_contains($htmlAnunt, 'comment__body--important'));

/**
 * FĂRĂ PASTILĂ pe rândul numelui. A purtat una, scrisă „Important", și era una
 * în plus: caseta e deja altfel, iar „Organizator" stă chiar lângă ea.
 */
verifica('fără pastilă în plus', false, str_contains($htmlAnunt, 'badge--important'));

/**
 * DAR CU O VORBĂ PENTRU CINE NU VEDE CASETA. Cititoarele de ecran nu citesc
 * culori și nici dungi în stânga; fără rândul ăsta, anunțul ar fi fost pentru
 * un om orb un comentariu ca oricare altul.
 */
verifica('dar spusă pentru cititoarele de ecran', true,
    str_contains($htmlAnunt, '<span class="sr-only">Anunț important</span>'));

/* Un comentariu obișnuit nu capătă nimic din toate astea. */
$htmlObisnuit = randeazaComentariu(
    comentariuDupaId($iubit) + ['participa' => 1, 'stare_cont' => 'activ',
                                'este_staff' => 0, 'permalink' => '', 'poza' => null,
                                'nume' => 'Neagu', 'prenume' => 'Elena', 'aprecieri' => 3],
    context($eveniment, $strain));

verifica('cel obișnuit rămâne cum era', false,
    str_contains($htmlObisnuit, 'comment__body--important'));

/**
 * O PIATRĂ DE MORMÂNT NU RĂMÂNE PIRONITĂ SUS. Un anunț golit păstrează rândul
 * ca să țină legată discuția de sub el — dar în locul lui scrie „Comentariu
 * șters", iar acela n-are ce căuta în capul listei, deasupra a tot ce mai au
 * oamenii de spus.
 *
 * Întrebarea se pune la CITIRE (esteImportant), nu se stinge steagul la
 * ștergere: un rând golit de mână din phpMyAdmin n-ar fi trecut pe la codul
 * care l-ar fi stins. De aceea proba îl golește chiar așa, direct în bază.
 */
db()->prepare('UPDATE comentarii SET sters = 1 WHERE id = ?')->execute([$alDoileaAnunt]);

$fire = grupeazaComentarii(comentariileEvenimentului($evenimentId, $strain));

verifica('anunțul golit nu mai e important', false,
    esteImportant(comentariuDupaId($alDoileaAnunt)));
verifica('și nu mai stă primul', $anunt, (int) $fire[0]['id']);

/* ------------------------ cine primește vestea ---------------------- */

/**
 * TOȚI CEI CARE AȘTEAPTĂ SEARA ASTA — și înscriși, și doar interesați. E o
 * veste despre EVENIMENT, nu despre discuția de sub el.
 *
 * Fără organizator: el tocmai a scris-o. Și fără conturile golite — Elena a
 * trecut prin ștergere mai sus, în proba pietrei de mormânt, deci n-are unde
 * primi nimic. De aceea înscrisul de aici e un om nou, nu ea.
 */
$inscris = faMembru('insc', 'Popa', 'Radu');
salveazaInteres($evenimentId, $inscris, 'participant');
salveazaInteres($evenimentId, $strain,  'interesat');

$iduri = array_map(static fn ($o) => (int) $o['id'],
                   oameniiDeInstiintat($evenimentId, $organizator));

verifica('cel înscris primește',       true,  in_array($inscris, $iduri, true));
verifica('și cel doar interesat',      true,  in_array($strain, $iduri, true));
verifica('organizatorul, nu',          false, in_array($organizator, $iduri, true));
verifica('nici contul golit',          false, in_array($participant, $iduri, true));

echo "\n=== ACORDUL DIN E-MAILURI ===\n";

/**
 * CUM SE SCRIE DESPRE UN OM, în cele două mesaje care pleacă de sub un
 * comentariu. Se cheamă funcțiile de e-mail de-a dreptul: aici se probează
 * VORBELE, iar cine le duce e treaba altei suite.
 *
 * Ce a fost stricat: „organizatorul evenimentului" scris la fel pentru
 * oricine. La un anunț important pus de o femeie ieșea „P. Ioana,
 * organizatorul evenimentului", iar celui care primea vestea despre un
 * comentariu nou i se spunea „ești organizatorul" chiar dacă era ea.
 */
global $config;

$logEmail = __DIR__ . '/../private/emailuri-trimise.log';

if (empty($config['dezvoltare'])) {
    echo "  (`dezvoltare` e oprit în config.php — partea asta s-a sărit)\n";
} else {
    /** Ce a intrat în log de la o trimitere, cu spațiile strânse. */
    $scrisIn = static function (callable $ce) use ($logEmail): string {
        $de_la = is_file($logEmail) ? (int) filesize($logEmail) : 0;
        $ce();

        return (string) preg_replace('/\s+/u', ' ',
            substr((string) file_get_contents($logEmail), $de_la));
    };

    /* --- anunțul important: acordul e cu cel care l-a SCRIS --- */
    $text = $scrisIn(static fn () => emailComentariuImportant(
        'ea@invalid.local', 'Radu', 'P. Ioana', 'F',
        'Ieșire de probă', 'Ne mutăm pe terenul de alături.', 'https://exemplu.test/'));

    verifica('anunț de la o femeie: „organizatoarea"', true,
        str_contains($text, 'P. Ioana, organizatoarea evenimentului'));
    verifica('fără „organizatorul" rătăcit',           false,
        str_contains($text, ', organizatorul evenimentului'));

    $text = $scrisIn(static fn () => emailComentariuImportant(
        'el@invalid.local', 'Ioana', 'P. Radu', 'M',
        'Ieșire de probă', 'Ne mutăm pe terenul de alături.', 'https://exemplu.test/'));

    verifica('de la un bărbat: „organizatorul"', true,
        str_contains($text, 'P. Radu, organizatorul evenimentului'));

    /* --- comentariul nou: acordul e cu cel care CITEȘTE --- */
    $text = $scrisIn(static fn () => emailComentariuNou(
        'ea@invalid.local', 'Ioana', 'comentariu', 'P. Radu',
        'Ieșire de probă', 'Ce faină pare!', 'https://exemplu.test/', 'F'));

    verifica('vestea către o organizatoare', true,
        str_contains($text, 'ești organizatoarea evenimentului'));

    $text = $scrisIn(static fn () => emailComentariuNou(
        'el@invalid.local', 'Radu', 'comentariu', 'P. Ioana',
        'Ieșire de probă', 'Ce faină pare!', 'https://exemplu.test/', 'M'));

    verifica('și către un organizator', true,
        str_contains($text, 'ești organizatorul evenimentului'));

    /**
     * LA UN RĂSPUNS NU SE SPUNE NIMIC DESPRE ROL, deci acordul n-are ce
     * strica: încheierea e alta, despre bifa din setări.
     */
    $text = $scrisIn(static fn () => emailComentariuNou(
        'ea@invalid.local', 'Ioana', 'raspuns', 'P. Radu',
        'Ieșire de probă', 'Da, vin!', 'https://exemplu.test/', 'F'));

    verifica('la un răspuns nu se pomenește rolul', false,
        str_contains($text, 'organizator'));
}

/* =========================== curățenie ============================= */

curata();

$q = db()->prepare('SELECT COUNT(*) FROM comentarii WHERE eveniment_id = ?');
$q->execute([$evenimentId]);
verifica('evenimentul șters își ia comentariile cu el', 0, (int) $q->fetchColumn());

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
