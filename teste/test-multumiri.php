<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — mulțumirile de după eveniment.
 *
 * Cere BAZA DE DATE, nu și serverul: se cheamă direct funcțiile din
 * inc/multumiri.php, fără să treacă prin cron.
 *
 * MESAJELE NU PLEACĂ NICĂIERI cât `dezvoltare => true` în inc/config.php: se
 * scriu în private/emailuri-trimise.log (vezi trimiteEmail din inc/email.php).
 * Testul se uită la ce s-a ales de fiecare eveniment, nu la ce a ajuns în
 * cutia poștală a nimănui.
 *
 * Cum se rulează:
 *     php teste/test-multumiri.php
 */

require_once __DIR__ . '/../inc/multumiri.php';
// Cifrele de pe profil („Prezent la evenimente" / „A confirmat, dar nu a
// venit") stau în evaluari.php, fiindcă a doua se citește din însemnările de
// acolo. inc/multumiri.php n-are treabă cu ele, deci nu-l cere el.
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

function faMembru(string $cheie, string $nume, string $prenume, string $stare = 'activ'): int
{
    db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, sex, data_nasterii,
                             parola_hash, stare, este_staff, creat_la, actualizat_la)
         VALUES (?,?,?,?,\'M\',\'1990-01-01\',\'x\',?,0,?,?)'
    )->execute(['tstmul-' . $cheie, $nume, $prenume,
                'test-multumiri-' . $cheie . '@invalid.local', $stare, acum(), acum()]);

    return (int) db()->lastInsertId();
}

/**
 * Un eveniment cu ziua și starea cerute de test.
 *
 * Amândouă contează: un eveniment e încheiat ori fiindcă i-a trecut ziua, ori
 * fiindcă organizatorul a apăsat butonul. Aici se pot face de-a fir a păr
 * amândouă felurile, plus cele care n-au ce căuta în listă.
 */
function faEveniment(string $slug, int $organizator, string $cand, string $stare = 'aprobat'): array
{
    db()->prepare(
        'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, descriere, oras,
                                 locatie, data_eveniment, ora_inceput, stare_moderare,
                                 creat_la, actualizat_la)
         VALUES (?, (SELECT MIN(id) FROM categorii), ?, ?, ?, \'Roman\', \'Centru\',
                 ?, \'18:00:00\', ?, ?, ?)'
    )->execute([
        $organizator, 'Eveniment ' . $slug, $slug, str_repeat('Text de probă. ', 30),
        date('Y-m-d', strtotime($cand)), $stare, acum(), acum(),
    ]);

    return evenimentDupaSlug($slug);
}

function curata(): void
{
    db()->prepare('DELETE e FROM evenimente e JOIN membri m ON m.id = e.membru_id
                    WHERE m.permalink LIKE ?')->execute(['tstmul-%']);
    db()->prepare('DELETE FROM membri WHERE permalink LIKE ?')->execute(['tstmul-%']);
}

/** Sluguri de-ale noastre din lista cronului — restul bazei nu ne privește. */
function aleNoastre(array $evenimente): array
{
    $sluguri = [];

    foreach ($evenimente as $ev) {
        if (str_starts_with((string) $ev['slug'], 'tstmul-')) {
            $sluguri[] = (string) $ev['slug'];
        }
    }

    return $sluguri;
}

curata();

$organizator = faMembru('org', 'Rusu',    'Ioana');
$ana         = faMembru('ana', 'Neagu',   'Elena');
$vlad        = faMembru('vld', 'Solomon', 'Vlad');
$plecat      = faMembru('plc', 'Ionescu', 'Radu', 'sters');

/* =================== 1. CINE INTRĂ ÎN LISTA CRONULUI ================= */

echo "=== CINE INTRĂ ÎN LISTA CRONULUI ===\n";

// Trecut prin ziua care i-a trecut, fără ca nimeni să apese nimic.
$trecut = faEveniment('tstmul-trecut', $organizator, '-3 days');

// Încheiat cu mâna, deși ziua lui e abia peste o săptămână.
$devreme = faEveniment('tstmul-devreme', $organizator, '+7 days', 'incheiat');

// Ce n-are ce căuta în listă.
$viitor    = faEveniment('tstmul-viitor',    $organizator, '+9 days');
$anulat    = faEveniment('tstmul-anulat',    $organizator, '-2 days', 'anulat');
$asteptare = faEveniment('tstmul-asteptare', $organizator, '-2 days', 'in_asteptare');

$gasite = aleNoastre(evenimenteFaraMultumiri());

verifica('cel căruia i-a trecut ziua', true, in_array('tstmul-trecut', $gasite, true));
verifica('și cel încheiat de organizator', true, in_array('tstmul-devreme', $gasite, true));
verifica('nu și cel care abia urmează', false, in_array('tstmul-viitor', $gasite, true));
verifica('nu și cel anulat', false, in_array('tstmul-anulat', $gasite, true));
verifica('nu și cel rămas în așteptare', false, in_array('tstmul-asteptare', $gasite, true));
verifica('deci doi', 2, count($gasite));

/* ==================== 2. CINE PRIMEȘTE MESAJUL ====================== */

echo "\n=== CINE PRIMEȘTE MESAJUL ===\n";

$trecutId = (int) $trecut['id'];

faOrganizatorulParticipant($trecutId, $organizator);
salveazaInteres($trecutId, $ana,    'participant');
salveazaInteres($trecutId, $vlad,   'interesat');
salveazaInteres($trecutId, $plecat, 'participant');

$oameni = participantiiDeMultumit($trecutId);
$iduri  = array_map(fn ($o) => (int) $o['id'], $oameni);

verifica('organizatorul e și el pe listă', true, in_array($organizator, $iduri, true));
verifica('și cine a confirmat', true, in_array($ana, $iduri, true));
verifica('nu și cine era doar interesat', false, in_array($vlad, $iduri, true));
verifica('nu și contul șters', false, in_array($plecat, $iduri, true));
verifica('deci doi', 2, count($oameni));

verifica('vine cu adresa, că de-aia se cheamă', true,
    ($oameni[0]['email'] ?? '') !== '');

/* ================= 3. CINE E SCOS NU MAI PRIMEȘTE =================== */

echo "\n=== CINE E SCOS DE PE LISTĂ ===\n";

/**
 * Omul scos de organizator a primit deja alt mesaj, cel cu motivul. Ar fi de
 * prost gust să-i vină după aceea și „mulțumim că ai fost".
 */
excludeParticipant($trecutId, $ana, $organizator, 'staff', 'Motiv de probă, destul de lung.', false);

$iduri = array_map(fn ($o) => (int) $o['id'], participantiiDeMultumit($trecutId));
verifica('nu mai e pe listă', false, in_array($ana, $iduri, true));

// Înapoi pe listă, pentru testele următoare.
salveazaInteres($trecutId, $ana, 'participant');

/* ====================== 4. TRIMITEREA, O DATĂ ======================= */

echo "\n=== TRIMITEREA ===\n";

$rezultat = trimiteMultumiriPentruEveniment($trecut);

verifica('doi oameni pe listă', 2, $rezultat['oameni']);
verifica('două mesaje plecate', 2, $rezultat['trimise']);
verifica('niciunul picat', 0, $rezultat['picate']);

// Semnul rămâne scris în rând, altfel cronul ar trimite din nou peste o oră.
$q = db()->prepare('SELECT multumiri_trimise_la FROM evenimente WHERE id = ?');
$q->execute([$trecutId]);
verifica('evenimentul e însemnat', true, $q->fetchColumn() !== null);

verifica('și nu mai apare în lista cronului', false,
    in_array('tstmul-trecut', aleNoastre(evenimenteFaraMultumiri()), true));

/* ================ 5. UN EVENIMENT LA CARE N-A FOST NIMENI ============ */

echo "\n=== PREA PUȚINI OAMENI ===\n";

/**
 * Mesajul e, în cea mai mare parte, o invitație la note — iar notele se dau
 * între oameni. Cu organizatorul singur pe listă, n-ar avea cui să dea nimic.
 */
faOrganizatorulParticipant((int) $devreme['id'], $organizator);

$rezultat = trimiteMultumiriPentruEveniment($devreme);

verifica('un singur om pe listă', 1, $rezultat['oameni']);
verifica('nu pleacă nimic', 0, $rezultat['trimise']);
verifica('și nici nu pică nimic', 0, $rezultat['picate']);

// Dar rândul se însemnează oricum: altfel ar fi cercetat la fiecare rulare.
verifica('rândul e totuși închis', false,
    in_array('tstmul-devreme', aleNoastre(evenimenteFaraMultumiri()), true));

/* ================== 6. CIFRELE DE PE PROFIL ========================= */

echo "\n=== CIFRELE DE PE PROFIL ===\n";

verifica('Ana a fost la unul', 1, laCateEvenimenteAFost($ana));
verifica('și n-a lipsit de nicăieri', 0, laCateEvenimenteNuAVenit($ana));

// Organizatorul e pe listele amândurora — și la ce a ținut el se numără.
verifica('organizatoarea, la două', 2, laCateEvenimenteAFost($organizator));

// Cine s-a arătat doar interesat n-a fost nicăieri.
verifica('cine era doar interesat, la niciunul', 0, laCateEvenimenteAFost($vlad));

/* --------------------- însemnat ca neprezentat --------------------- */

salveazaEvaluare($trecutId, $ana, $organizator, EVALUARE_ABSENT_STELE, EVALUARE_ABSENT_TEXT, true);

verifica('acum e trecută la absențe', 1, laCateEvenimenteNuAVenit($ana));

/**
 * Miezul: cele două cifre se exclud una pe alta. Altfel profilul ar spune
 * „prezent la 1" și „n-a venit la 1" despre același eveniment.
 */
verifica('și iese din numărul prezențelor', 0, laCateEvenimenteAFost($ana));

// Un eveniment anulat nu se numără nicăieri, oricine ar fi pe lista lui.
salveazaInteres((int) $anulat['id'], $ana, 'participant');
verifica('evenimentul anulat nu se numără', 0, laCateEvenimenteAFost($ana));

// Nici cel rămas în așteptare, care n-a fost niciodată public.
salveazaInteres((int) $asteptare['id'], $ana, 'participant');
verifica('nici cel din așteptare', 0, laCateEvenimenteAFost($ana));

// Dar unul care abia urmează, da: „active și încheiate", amândouă.
salveazaInteres((int) $viitor['id'], $ana, 'participant');
verifica('cel care urmează se numără', 1, laCateEvenimenteAFost($ana));

verifica('fără cont, zero', 0, laCateEvenimenteAFost(0));
verifica('și la absențe la fel', 0, laCateEvenimenteNuAVenit(0));

/* =========================== curățenie ============================= */

curata();

$q = db()->prepare('SELECT COUNT(*) FROM evenimente WHERE slug LIKE ?');
$q->execute(['tstmul-%']);
verifica('evenimentele de probă s-au dus', 0, (int) $q->fetchColumn());

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
