<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — mementoul de dinaintea unui eveniment.
 *
 * Cere BAZA DE DATE, nu și serverul: se cheamă direct funcțiile din
 * inc/amintiri.php, fără să treacă prin cron.
 *
 * MESAJELE NU PLEACĂ NICĂIERI cât `dezvoltare => true` în inc/config.php: se
 * scriu în private/emailuri-trimise.log (vezi trimiteEmail din inc/email.php).
 *
 * CE PĂZEȘTE, mai presus de orice: CELE DOUĂ CAPETE ALE FERESTREI. Un memento
 * care pleacă prea devreme e o supărare; unul care pleacă DUPĂ ce a început
 * evenimentul e mai rău decât niciunul — omul îl deschide și află că a
 * pierdut. Și amândouă se strică în tăcere: nimeni nu se plânge de un e-mail
 * pe care nu l-a primit.
 *
 * Cum se rulează:
 *     php teste/test-amintiri.php
 */

require_once __DIR__ . '/../inc/amintiri.php';
require_once __DIR__ . '/../inc/coada.php';
/**
 * GOLEȘTE COADA, ca mesajele să ajungă în log.
 *
 * De când trimiterile în serie se scriu în coadă și pleacă din cron, un mesaj
 * nu mai ajunge în private/emailuri-trimise.log în clipa apăsării. Proba face
 * aici ce face cronul — și e mai bine așa: acoperă drumul întreg, de la apăsare
 * până la plic, nu doar prima jumătate.
 *
 * În buclă, fiindcă o rulare duce cel mult COADA_PE_RULARE mesaje.
 */
function goleșteCoada(): void
{
    for ($i = 0; $i < 50; $i++) {
        if (trimiteDinCoada(50)['luate'] === 0) {
            return;
        }
    }
}


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

const SEMN = 'tstam-';

function curata(): void
{
    db()->prepare('DELETE e FROM evenimente e JOIN membri m ON m.id = e.membru_id
                    WHERE m.permalink LIKE ?')->execute([SEMN . '%']);
    db()->prepare('DELETE FROM membri WHERE permalink LIKE ?')->execute([SEMN . '%']);
}

curata();
register_shutdown_function('curata');

function faMembru(string $cheie, string $prenume, string $stare = 'activ'): int
{
    db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, sex, data_nasterii,
                             parola_hash, stare, este_staff, creat_la, actualizat_la)
         VALUES (?,?,?,?,\'M\',\'1990-01-01\',\'x\',?,0,?,?)'
    )->execute([
        substr(SEMN . $cheie, 0, 16), 'Probă', $prenume,
        SEMN . $cheie . '@invalid.local', $stare, acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

/**
 * Un eveniment care începe la o clipă anume, dată ca „+2 hours" ș.a.m.d.
 *
 * Ceasul contează până la minut, nu doar ziua: tot rostul probei ăsteia e
 * fereastra de trei ore, iar ea se socotește lipind data de oră.
 */
function faEveniment(string $slug, int $organizator, string $cand,
                     string $stare = 'aprobat'): array
{
    $clipa = strtotime($cand);

    db()->prepare(
        'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, descriere, oras,
                                 locatie, data_eveniment, ora_inceput, stare_moderare,
                                 creat_la, actualizat_la)
         VALUES (?, (SELECT MIN(id) FROM categorii), ?, ?, ?, \'Roman\', \'Centru\',
                 ?, ?, ?, ?, ?)'
    )->execute([
        $organizator, 'Eveniment ' . $slug, $slug, str_repeat('Povestea lui. ', 20),
        date('Y-m-d', $clipa), date('H:i:00', $clipa), $stare, acum(), acum(),
    ]);

    return evenimentDupaSlug($slug);
}

/** Slugurile noastre din ce a găsit cronul — restul bazei nu ne privește. */
function aleNoastre(array $evenimente): array
{
    $sluguri = [];

    foreach ($evenimente as $ev) {
        if (str_starts_with((string) $ev['slug'], SEMN)) {
            $sluguri[] = (string) $ev['slug'];
        }
    }

    return $sluguri;
}

$org  = faMembru('org', 'Ovidiu');
$ana  = faMembru('ana', 'Ana');
$vlad = faMembru('vld', 'Vlad');
$dus  = faMembru('dus', 'Plecat', 'sters');

/* ==================================================================== */
sectiune('cele două capete ale ferestrei');

/**
 * Ceasul probei stă pe loc. Altfel, o probă pornită la 23:59 ar socoti altfel
 * decât aceeași probă la 09:00 — iar o probă care pică o dată pe zi e mai rea
 * decât una care nu există.
 */
$acum = strtotime('2026-06-10 12:00:00');

faEveniment(SEMN . 'peste-1h',   $org, '2026-06-10 13:00');
faEveniment(SEMN . 'peste-2h',   $org, '2026-06-10 14:00');
faEveniment(SEMN . 'fix-3h',     $org, '2026-06-10 15:00');
faEveniment(SEMN . 'peste-4h',   $org, '2026-06-10 16:00');
faEveniment(SEMN . 'a-inceput',  $org, '2026-06-10 11:30');
faEveniment(SEMN . 'ieri',       $org, '2026-06-09 19:00');

$gasite = aleNoastre(evenimenteDeAmintit($acum));

verifica('cel de peste o oră intră',    true,  in_array(SEMN . 'peste-1h', $gasite, true));
verifica('cel de peste două, la fel',   true,  in_array(SEMN . 'peste-2h', $gasite, true));

/**
 * CAPĂTUL DE SUS E ÎNCHIS: „în cel mult trei ore" îl cuprinde și pe cel de la
 * fix trei. Un minut mai încolo, nu. Se scrie pe față, fiindcă un `<` pus în
 * locul unui `<=` nu se vede niciodată din afară.
 */
verifica('cel de la fix trei ore intră', true,  in_array(SEMN . 'fix-3h', $gasite, true));
verifica('cel de peste patru, nu',      false, in_array(SEMN . 'peste-4h', $gasite, true));

/**
 * CAPĂTUL DE JOS E DESCHIS, și ăsta e cel care doare: un memento pentru ceva ce
 * a început deja e mai rău decât niciunul. Ține și dacă cronul n-a rulat o
 * noapte — ce-a trecut nu se mai scrie nimănui.
 */
verifica('cel care a început deja, nu', false, in_array(SEMN . 'a-inceput', $gasite, true));
verifica('nici cel de ieri',            false, in_array(SEMN . 'ieri', $gasite, true));
verifica('deci trei',                   3,     count($gasite));

/**
 * PESTE MIEZUL NOPȚII. La 22:00, un eveniment de a doua zi de la 00:30 e la
 * două ore și jumătate distanță — deci intră. Aici s-ar fi rupt o socoteală
 * scrisă pe zile, nu pe clipe.
 */
$noaptea = strtotime('2026-06-10 22:00:00');
faEveniment(SEMN . 'la-00-30', $org, '2026-06-11 00:30');

verifica('unul de după miezul nopții intră', true,
    in_array(SEMN . 'la-00-30', aleNoastre(evenimenteDeAmintit($noaptea)), true));

/* ==================================================================== */
sectiune('ce fel de anunțuri intră');

/* Slugul nu poate purta „_", deci starea se scrie cu cratimă în el. */
$stari = ['in_asteptare' => 'asteptare', 'respins' => 'respins',
          'anulat' => 'anulat', 'incheiat' => 'incheiat'];

foreach ($stari as $stare => $bucata) {
    faEveniment(SEMN . 'st-' . $bucata, $org, '2026-06-10 14:00', $stare);
}

$gasite = aleNoastre(evenimenteDeAmintit($acum));

foreach ($stari as $stare => $bucata) {
    verifica('din „' . $stare . '" nu pleacă nimic', false,
        in_array(SEMN . 'st-' . $bucata, $gasite, true));
}

/* ==================================================================== */
sectiune('cine primește');

$ev   = faEveniment(SEMN . 'cu-oameni', $org, '2026-06-10 14:00');
$evId = (int) $ev['id'];

faOrganizatorulParticipant($evId, $org);
salveazaInteres($evId, $ana,  'participant');
salveazaInteres($evId, $vlad, 'interesat');
salveazaInteres($evId, $dus,  'participant');

$iduri = array_map(static fn ($o) => (int) $o['id'], participantiiCuEmail($evId));

verifica('organizatorul e pe listă',        true,  in_array($org, $iduri, true));
verifica('și cine a confirmat',             true,  in_array($ana, $iduri, true));
verifica('nu și cine era doar interesat',   false, in_array($vlad, $iduri, true));
verifica('nu și contul șters',              false, in_array($dus, $iduri, true));
verifica('deci doi',                        2,     count($iduri));

/* ==================================================================== */
sectiune('vorba despre când începe');

verifica('azi',   'azi la 14:00',   candIncepeEvenimentul($ev, $acum));
verifica('mâine', 'mâine la 00:30',
    candIncepeEvenimentul(['data_eveniment' => '2026-06-11', 'ora_inceput' => '00:30:00'], $noaptea));

/* Fără ceas, nicio vorbă — mai bine tace decât să spună o oră inventată. */
verifica('fără oră, nimic', '',
    candIncepeEvenimentul(['data_eveniment' => '2026-06-11', 'ora_inceput' => null], $acum));

/* ==================================================================== */
sectiune('pleacă o singură dată');

$log = static function (callable $ce): string {
    $fisier = __DIR__ . '/../private/emailuri-trimise.log';
    $inainte = is_file($fisier) ? filesize($fisier) : 0;
    $ce();
    goleșteCoada();

    if (!is_file($fisier)) {
        return '';
    }

    return (string) file_get_contents($fisier, false, null, $inainte);
};

$nou = $log(static function () use ($ev, $acum): void {
    trimiteAmintirilePentruEveniment($ev, $acum);
});

$faraRupturi = static fn(string $t): string => preg_replace('/\s+/u', ' ', $t);

verifica('ora e în subiect', true, str_contains($faraRupturi($nou), 'începe azi la 14:00'));
verifica('și titlul',        true, str_contains($nou, 'Eveniment ' . SEMN . 'cu-oameni'));

/* Organizatorul primește altă vorbă: pe el nu-l așteaptă nimeni, el așteaptă. */
verifica('omului i se spune că a zis că vine', true,
    str_contains($faraRupturi($nou), 'ai spus că vii'));
verifica('organizatorului, că l-a pus la cale', true,
    str_contains($faraRupturi($nou), 'l-ai pus la cale'));

/**
 * ȘTAMPILA. Fără ea, cronul din oră în oră ar trimite același memento de trei
 * ori — o dată la fiecare trecere cât ține fereastra.
 */
verifica('evenimentul e însemnat', false,
    evenimentDupaSlug(SEMN . 'cu-oameni')['amintire_trimisa_la'] === null);

verifica('și nu mai apare în lista cronului', false,
    in_array(SEMN . 'cu-oameni', aleNoastre(evenimenteDeAmintit($acum)), true));

$aDouaOara = $log(static function () use ($acum): void {
    foreach (evenimenteDeAmintit($acum) as $ev2) {
        trimiteAmintirilePentruEveniment($ev2, $acum);
    }
});

verifica('a doua trecere nu mai scrie nimănui', false,
    str_contains($aDouaOara, SEMN . 'ana@invalid.local'));

/**
 * ȘTAMPILA SE PUNE ȘI CÂND N-A PLECAT NIMIC. La un eveniment fără nimeni pe
 * listă — o vânătoare „FindMe", de pildă, unde nu se înscrie nimeni —, rândul
 * ar fi fost cercetat la fiecare trecere, degeaba, cât ține fereastra.
 */
$gol = faEveniment(SEMN . 'fara-nimeni', $org, '2026-06-10 14:30');
$rezultat = trimiteAmintirilePentruEveniment($gol, $acum);

verifica('nimeni pe listă',      0, $rezultat['oameni']);
verifica('nimic trimis',         0, $rezultat['trimise']);
verifica('dar tot e însemnat', false,
    evenimentDupaSlug(SEMN . 'fara-nimeni')['amintire_trimisa_la'] === null);

/* ==================================================================== */
sectiune('nu e un mesaj nechemat');

/**
 * Mementoul e răspunsul la „Particip"-ul apăsat de om, ca mulțumirea de după —
 * nu newsletter. Deci NU poartă link de dezabonare, iar ieșirea scrisă în el e
 * chiar butonul din care s-a intrat: lista de participanți.
 */
verifica('fără link de dezabonare', false, str_contains($nou, 'dezabonare.php'));
verifica('dar cu ieșirea scrisă',  true,
    str_contains($faraRupturi($nou), 'scoate-ți numele de pe listă'));

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
