<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — zona de administrare.
 *
 * Cere BAZA DE DATE. Partea de HTTP cere și SERVERUL, dar se sare singură dacă
 * nu i se dă o adresă.
 *
 * Cum se rulează:
 *     php teste/test-admin.php                        (fără HTTP)
 *     php teste/test-admin.php http://127.0.0.1:8099  (cu tot)
 *
 * Ce păzește, mai presus de orice: că PAGINILE ȘI FAPTELE SUNT NUMAI PENTRU
 * OAMENII CASEI. Restul verificărilor sunt despre ce se vede și ce se schimbă;
 * aceea e despre ce nu trebuie să se poată deloc.
 */

require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/comentarii.php';
// Pentru stampileazaCeleAprobate(), ștampila pe care o pune prima pagină.
require_once __DIR__ . '/../inc/dorinte.php';
// Pentru EVALUARE_ABSENT_STELE, steaua pe care o pune „Nu s-a prezentat".
require_once __DIR__ . '/../inc/evaluari.php';

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

const SEMN   = 'test-admin-';
const PAROLA = 'ParolaDeProba#2026';

function curata(): void
{
    db()->prepare('DELETE FROM mesaje_contact WHERE email LIKE ?')->execute([SEMN . '%']);
    db()->prepare('DELETE FROM evenimente WHERE slug LIKE ?')->execute(['tstadm-%']);
    db()->prepare(
        'DELETE d FROM dorinte d JOIN membri m ON m.id = d.membru_id
          WHERE m.permalink LIKE ?'
    )->execute(['tstadm-%']);
    db()->prepare('DELETE FROM membri WHERE permalink LIKE ?')->execute(['tstadm-%']);
}

curata();
register_shutdown_function('curata');

function faMembru(string $cheie, string $prenume, bool $staff = false): int
{
    db()->prepare(
        'INSERT INTO membri (permalink, nume, prenume, email, telefon, sex, data_nasterii,
                             parola_hash, stare, este_staff, creat_la, confirmat_la)
         VALUES (?,?,?,?,?,"M","1990-01-01",?,"activ",?,?,?)'
    )->execute([
        substr('tstadm-' . $cheie, 0, 16), 'Adminescu', $prenume,
        SEMN . $cheie . '@invalid.local', '',
        password_hash(PAROLA, PASSWORD_DEFAULT), $staff ? 1 : 0, acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

function faEveniment(int $cine, string $slug, string $stare): int
{
    db()->prepare(
        'INSERT INTO evenimente (membru_id, categorie_id, titlu, slug, oras, locatie,
                                 descriere, data_eveniment, ora_inceput, stare_moderare,
                                 creat_la, actualizat_la)
         VALUES (?,1,?,?,?,"Undeva",?,?,"19:00",?,?,?)'
    )->execute([
        $cine, 'Anunț de probă ' . $slug, $slug, oraseDisponibile()[0] ?? 'Roman',
        str_repeat('Povestea lui. ', 30), date('Y-m-d', strtotime('+9 days')),
        $stare, acum(), acum(),
    ]);

    return (int) db()->lastInsertId();
}

$gazda = faMembru('gazda', 'Silviu', true);
$omul  = faMembru('omul',  'Andrei');

/* ==================================================================== */
sectiune('secțiunile și cifrele');

$chei = array_map(static fn(array $s): string => (string) $s['cheie'], sectiuniAdmin());

verifica('șapte secțiuni', 7, count($chei));
verifica('în ordinea cerută',
    ['coduri', 'evenimente', 'comentarii', 'contact', 'useri', 'evaluari',
     'dorinte'], $chei);

/**
 * FIECARE SECȚIUNE ARE O CIFRĂ. A fost o vreme una fără — „Anunț pe e-mail",
 * o unealtă, nu o listă de lucru — dar a plecat odată cu anunțurile. Dacă mai
 * apare vreodată una fără cifră, aici e locul unde se află.
 */
$faraCifra = [];

foreach (sectiuniAdmin() as $s) {
    if ($s['cifra'] === null) { $faraCifra[] = $s['cheie']; }
}

verifica('toate au cifra lor', [], $faraCifra);

/**
 * Și fiecare cifră trebuie să se numere cu adevărat: o cheie scrisă
 * greșit ar fi dat tăcut zero, iar cartonașul n-ar fi spus niciodată că are
 * ceva de făcut.
 */
$cifre     = cifreleAdmin();
$toateBune = true;

foreach (sectiuniAdmin() as $s) {
    if ($s['cifra'] !== null && !array_key_exists($s['cifra'], $cifre)) {
        $toateBune = false;
    }
}

verifica('fiecare secțiune are cifra ei', true, $toateBune);

// Și fiecare duce undeva: o adresă scrisă greșit ar fi trecut neobservată.
$lipsesc = [];

foreach (sectiuniAdmin() as $s) {
    if (!is_file(__DIR__ . '/../' . $s['href'])) {
        $lipsesc[] = $s['href'];
    }
}

verifica('fiecare secțiune are pagina ei', [], $lipsesc);

/* ==================================================================== */
sectiune('evenimentele');

$evAst  = faEveniment($omul, 'tstadm-asteapta', 'in_asteptare');
$evResp = faEveniment($omul, 'tstadm-respins',  'respins');
$evApr  = faEveniment($omul, 'tstadm-aprobat',  'aprobat');

$sluguri = static fn(array $l): array =>
    array_map(static fn(array $e): string => (string) $e['slug'], $l);

verifica('cele în așteptare se adună', true,
    in_array('tstadm-asteapta', $sluguri(evenimenteDupaStare('in_asteptare')), true));
verifica('și cele respinse', true,
    in_array('tstadm-respins', $sluguri(evenimenteDupaStare('respins')), true));
verifica('fără să se amestece', false,
    in_array('tstadm-aprobat', $sluguri(evenimenteDupaStare('respins')), true));

/**
 * ȘTAMPILA DE CORECTURĂ. „Respinge, dar cu editare necesară" lasă anunțul în
 * așteptare — altfel omul n-ar mai fi avut ce îndrepta — și tocmai de aceea, în
 * listă, arăta la fel cu unul pe care nu-l citise nimeni. Ștampila spune care e
 * care, iar prima editare a omului o stinge.
 */
$randDupaSlug = static function (string $slug, string $stare): ?array {
    foreach (evenimenteDupaStare($stare) as $e) {
        if ((string) $e['slug'] === $slug) { return $e; }
    }
    return null;
};

verifica('la început, fără ștampilă', null,
    $randDupaSlug('tstadm-asteapta', 'in_asteptare')['corectura_ceruta_la']);

moderezaEveniment(evenimentDupaSlug('tstadm-asteapta'), 'in_asteptare', true);

verifica('„editare necesară" o pune', true,
    $randDupaSlug('tstadm-asteapta', 'in_asteptare')['corectura_ceruta_la'] !== null);
verifica('și anunțul rămâne în așteptare', 'in_asteptare',
    (string) evenimentDupaSlug('tstadm-asteapta')['stare_moderare']);

/* Orice editare a omului o stinge — nu una anume, ci oricare. */
$vechi = evenimentDupaSlug('tstadm-asteapta');
actualizeazaEveniment((int) $vechi['id'], [
    'categorie_id'     => (int) $vechi['categorie_id'],
    'titlu'            => (string) $vechi['titlu'],
    'data_eveniment'   => (string) $vechi['data_eveniment'],
    'ora_inceput'      => substr((string) $vechi['ora_inceput'], 0, 5),
    'ora_sfarsit'      => null,
    'oras'             => (string) $vechi['oras'],
    'locatie'          => (string) $vechi['locatie'],
    'cost'             => null,
    'varsta_minima'    => null,
    'participanti_min' => null,
    'participanti_max' => null,
    'descriere'        => (string) $vechi['descriere'],
    'gen_participanti' => 'nespecificat',
], null);

verifica('prima editare o stinge', null,
    $randDupaSlug('tstadm-asteapta', 'in_asteptare')['corectura_ceruta_la']);

/* Iar o hotărâre adevărată o stinge la fel: n-are ce corectură să mai aștepte. */
moderezaEveniment(evenimentDupaSlug('tstadm-asteapta'), 'in_asteptare', true);
moderezaEveniment(evenimentDupaSlug('tstadm-asteapta'), 'aprobat');

$q = db()->prepare('SELECT corectura_ceruta_la FROM evenimente WHERE slug = ?');
$q->execute(['tstadm-asteapta']);
verifica('aprobarea o șterge și ea', null, $q->fetchColumn());

/* Ce atârnă de el se numără, ca omul de casă să vadă cât se pierde. */
db()->prepare('INSERT INTO comentarii (eveniment_id, membru_id, text, creat_la)
               VALUES (?,?,?,?)')->execute([$evResp, $omul, 'Ceva scris.', acum()]);
db()->prepare('INSERT INTO interese_evenimente (eveniment_id, membru_id, stare, creat_la, actualizat_la)
               VALUES (?,?,"participant",?,?)')->execute([$evResp, $omul, acum(), acum()]);

$randul = null;
foreach (evenimenteDupaStare('respins') as $e) {
    if ((string) $e['slug'] === 'tstadm-respins') { $randul = $e; }
}

verifica('rândul spune câte comentarii are', 1, (int) $randul['cate_comentarii']);
verifica('și câte înscrieri',                1, (int) $randul['cati_inscrisi']);

/**
 * ȘTERGEREA IA TOT. Rândurile din celelalte tabele pleacă în cascadă, prin
 * cheile străine — nu prin vreo listă scrisă de mână, care într-o zi n-ar mai
 * fi fost la fel cu cheile.
 */
verifica('ștergerea reușește', true, stergeEvenimentDeTot($randul));
verifica('anunțul a plecat', null, evenimentDupaSlug('tstadm-respins'));

$cate = static function (string $tabel, int $id): int {
    $q = db()->prepare('SELECT COUNT(*) FROM ' . $tabel . ' WHERE eveniment_id = ?');
    $q->execute([$id]);
    return (int) $q->fetchColumn();
};

verifica('comentariile lui, la fel', 0, $cate('comentarii', $evResp));
verifica('și înscrierile',           0, $cate('interese_evenimente', $evResp));

/* ==================================================================== */
sectiune('comentariile și rapoartele');

$evPub = faEveniment($gazda, 'tstadm-public', 'aprobat');

$scrie = static function (int $ev, int $cine, string $text, ?int $parinte = null) use (&$idCom): int {
    db()->prepare('INSERT INTO comentarii (eveniment_id, membru_id, parinte_id, text, creat_la)
                   VALUES (?,?,?,?,?)')->execute([$ev, $cine, $parinte, $text, acum()]);
    return (int) db()->lastInsertId();
};

$c1 = $scrie($evPub, $omul,  'TSTADM primul, cu răspuns sub el');
$c2 = $scrie($evPub, $gazda, 'TSTADM al doilea, raportat de doi');
$scrie($evPub, $gazda, 'TSTADM un răspuns', $c1);

foreach ([$omul, $gazda] as $cine) {
    db()->prepare('INSERT INTO comentarii_rapoarte (comentariu_id, membru_id, creat_la)
                   VALUES (?,?,?)')->execute([$c2, $cine, acum()]);
}
db()->prepare('INSERT INTO comentarii_rapoarte (comentariu_id, membru_id, creat_la)
               VALUES (?,?,?)')->execute([$c1, $omul, acum()]);

$raportate = comentariiRaportate();
$aleNoastre = array_values(array_filter($raportate,
    static fn(array $c): bool => in_array((int) $c['id'], [$c1, $c2], true)));

verifica('amândouă cele raportate se văd', 2, count($aleNoastre));
verifica('cel mai raportat, primul', $c2, (int) $aleNoastre[0]['id']);
verifica('cu numărul lui',            2, (int) $aleNoastre[0]['cate_rapoarte']);
verifica('și al doilea, cu al lui',   1, (int) $aleNoastre[1]['cate_rapoarte']);

$ultimele = ultimeleComentarii();
verifica('cel mai nou e primul', true,
    str_contains((string) $ultimele[0]['text'], 'TSTADM un răspuns'));
verifica('cel mult cincizeci', true, count($ultimele) <= ADMIN_COMENTARII);

/**
 * Ștergerea din administrare e ACEEAȘI faptă ca pe pagina evenimentului: un
 * comentariu principal cu răspunsuri sub el se GOLEȘTE. O ștergere scrisă
 * separat pentru staff ar fi lăsat răspunsurile suspendate în aer.
 */
$ce = stergeComentariu(comentariuDupaId($c1));
verifica('cel cu răspunsuri se golește', 'golit', $ce['fel']);
verifica('rândul rămâne, gol', 1, (int) comentariuDupaId($c1)['sters']);

$ce = stergeComentariu(comentariuDupaId($c2));
verifica('cel fără răspunsuri se șterge', 'sters', $ce['fel']);
verifica('și chiar a plecat', null, comentariuDupaId($c2));

/* ==================================================================== */
sectiune('mesajele de la contact');

db()->prepare('INSERT INTO mesaje_contact (nume, prenume, email, telefon, mesaj, creat_la, citit_la)
               VALUES (?,?,?,?,?,?,NULL)')
    ->execute(['Popa', 'Ion', SEMN . 'unu@invalid.local', '', 'Un mesaj de probă.', acum()]);

$idMesaj = (int) db()->lastInsertId();

$aleMele = static fn(): array => array_values(array_filter(mesajeDeContact(),
    static fn(array $m): bool => str_starts_with((string) $m['email'], SEMN)));

verifica('mesajul se vede în listă', 1, count($aleMele()));
verifica('și e necitit',          null, $aleMele()[0]['citit_la']);

db()->prepare('UPDATE mesaje_contact SET citit_la = ? WHERE id = ?')->execute([acum(), $idMesaj]);
verifica('însemnat citit, se vede', true, $aleMele()[0]['citit_la'] !== null);

/* ==================================================================== */
sectiune('căutarea de oameni');

db()->prepare('UPDATE membri SET telefon = ? WHERE id = ?')->execute(['0733445566', $omul]);

$gasit = static fn(string $q, int $id): bool =>
    in_array($id, array_map(static fn(array $m): int => (int) $m['id'], cautaMembri($q)), true);

verifica('după e-mail',   true, $gasit(SEMN . 'omul@invalid.local', $omul));
verifica('după prenume',  true, $gasit('Andrei', $omul));
verifica('după nume',     true, $gasit('Adminescu', $omul));
verifica('după nume întreg', true, $gasit('Andrei Adminescu', $omul));
verifica('după telefon',  true, $gasit('0733445566', $omul));

/**
 * TELEFONUL SE CAUTĂ ȘI ADUS LA FORMA DIN BAZĂ. În `membri` numărul stă mereu
 * ca „0733445566", dar cine caută îl scrie cum îl are în telefon. Fără trecerea
 * prin verificaTelefon(), căutarea ar fi răspuns „nimeni" pentru un număr care
 * există — cea mai supărătoare formă de nimic.
 */
verifica('scris cu +40',        true, $gasit('+40733445566', $omul));
verifica('scris cu 0040',       true, $gasit('0040733445566', $omul));
verifica('scris cu spații',     true, $gasit('0733 445 566', $omul));
verifica('fără zeroul din față', true, $gasit('733445566', $omul));

verifica('cine nu e, nu se găsește', false, $gasit('Nimeni Pe Lume', $omul));

// `%` scris de om nu e joker: altfel ar fi adus toată lista.
verifica('procentul nu aduce pe toți', false, $gasit('%', $omul));

/**
 * AȘEZAȚI DUPĂ ULTIMA LOGARE, cel mult ADMIN_USERI. Un cont deschis acum doi
 * ani, dar folosit ieri, e mai interesant decât unul făcut alaltăieri și lăsat
 * baltă — iar lista întreagă, pe un site cu oameni, n-are cum să încapă.
 */
db()->prepare('UPDATE membri SET autentificat_la = ? WHERE id = ?')
    ->execute([acumMinus(60 * 24 * 30), $omul]);
db()->prepare('UPDATE membri SET autentificat_la = ? WHERE id = ?')
    ->execute([acum(), $gazda]);

$lista = cautaMembri('Adminescu');
$ordine = array_map(static fn(array $m): int => (int) $m['id'], $lista);

verifica('cel logat mai de curând, primul', [$gazda, $omul], $ordine);

db()->prepare('UPDATE membri SET autentificat_la = ? WHERE id = ?')
    ->execute([acum(), $omul]);
db()->prepare('UPDATE membri SET autentificat_la = ? WHERE id = ?')
    ->execute([acumMinus(60 * 24 * 30), $gazda]);

verifica('și invers, dacă se schimbă ceasul', [$omul, $gazda],
    array_map(static fn(array $m): int => (int) $m['id'], cautaMembri('Adminescu')));

verifica('lista întreagă e tăiată la ADMIN_USERI', true,
    count(cautaMembri('')) <= ADMIN_USERI);

// Cine n-a intrat niciodată nu se pierde: stă la coadă, nu dispare.
db()->prepare('UPDATE membri SET autentificat_la = NULL WHERE id = ?')->execute([$omul]);
verifica('cine n-a intrat niciodată tot se vede', true, $gasit('Andrei', $omul));

/* ==================================================================== */
sectiune('limita de evenimente: gol nu e zero');

/**
 * NULL în bază = regula obișnuită (EVENIMENTE_ACTIVE_IMPLICIT). Zero = „omul
 * ăsta nu mai publică nimic". Arată la fel într-o căsuță goală și înseamnă
 * lucruri opuse — de aceea pagina lasă căsuța GOALĂ pentru NULL, iar punctul de
 * intrare scrie NULL înapoi când vine gol.
 */
db()->prepare('UPDATE membri SET limita_evenimente_active = NULL WHERE id = ?')->execute([$omul]);
verifica('NULL înseamnă regula obișnuită', EVENIMENTE_ACTIVE_IMPLICIT, limitaEvenimente($omul));

db()->prepare('UPDATE membri SET limita_evenimente_active = 0 WHERE id = ?')->execute([$omul]);
verifica('zero înseamnă chiar zero', 0, limitaEvenimente($omul));

db()->prepare('UPDATE membri SET limita_evenimente_active = 5 WHERE id = ?')->execute([$omul]);
verifica('un număr înseamnă numărul', 5, limitaEvenimente($omul));

db()->prepare('UPDATE membri SET limita_evenimente_active = NULL WHERE id = ?')->execute([$omul]);

/* ==================================================================== */
sectiune('evaluările');

/**
 * DE CE PAGINA ASTA EXISTĂ: notele nu se retrag, nu se raportează și nu se
 * moderează nicăieri altundeva. O stea pusă din supărare rămânea pentru
 * totdeauna în media cuiva, iar cel notat n-avea cui să spună.
 */
$evNotat = faEveniment($gazda, 'tstadm-notat', 'incheiat');

$scrieNota = static function (int $ev, int $dela, int $catre, int $stele,
                              ?string $text, bool $automat = false) : int {
    db()->prepare(
        'INSERT INTO evaluari (eveniment_id, evaluator_id, evaluat_id, stele, text,
                               automat, creat_la, actualizat_la)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([$ev, $dela, $catre, $stele, $text, $automat ? 1 : 0, acum(), acum()]);

    return (int) db()->lastInsertId();
};

$notaScrisa = $scrieNota($evNotat, $omul,  $gazda, 2, 'TSTADM o părere aspră');
$notaGoala  = $scrieNota($evNotat, $gazda, $omul,  5, null);

$aNoastraNota = static function (int $id): ?array {
    foreach (toateEvaluarile() as $e) {
        if ((int) $e['id'] === $id) { return $e; }
    }
    return null;
};

verifica('nota scrisă se vede în listă', true, $aNoastraNota($notaScrisa) !== null);

/**
 * ȘI CEA FĂRĂ TEXT. Pe profil se arată doar părerile SCRISE — stelele singure
 * sunt anonime și nici nu apar. Dar tocmai ele fac media, deci tocmai ele
 * trebuie să se poată vedea de aici. E singurul loc de pe site unde o stea
 * are un nume lângă ea.
 */
verifica('și cea fără text, la fel', true, $aNoastraNota($notaGoala) !== null);

$rand = $aNoastraNota($notaScrisa);
verifica('cu cine a dat-o',   'Andrei', (string) $rand['autor_prenume']);
verifica('și cui i-a dat-o',  'Silviu', (string) $rand['tinta_prenume']);
verifica('cu stelele ei',            2, (int) $rand['stele']);
verifica('și cu evenimentul de unde vine',
    'tstadm-notat', (string) $rand['ev_slug']);

/* ------------------- cine împarte note, și cu ce mână ------------- */

$catAdat = static function (int $id) {
    foreach (ceAuDatOamenii() as $o) {
        if ((int) $o['id'] === $id) { return $o; }
    }
    return null;
};

$suma = $catAdat($omul);
verifica('omul apare printre cei care au dat', true, $suma !== null);
verifica('cu o notă la activ',           1, (int) $suma['cate']);
verifica('și cu cea mai mică a lui',     2, (int) $suma['cea_mai_mica']);

/* --------------------------- cifra de pe panou -------------------- */

/**
 * Cifra secțiunii NU e „câte note sunt", ci câte sunt sub trei stele —
 * singurele despre care poate veni cineva să se plângă. Peste tot pe panou,
 * cifra înseamnă „ceva de privit", iar la evaluări nu așteaptă nimic.
 */
$cifreDupa = (static function (): array {
    // cifreleAdmin() își ține minte răspunsul pentru cererea de acum, iar noi
    // tocmai am scris în bază după ce a fost chemată o dată mai sus. Se
    // numără din nou, de-a dreptul.
    return [
        'note_mici' => (int) db()->query('SELECT COUNT(*) FROM evaluari
                                           WHERE stele <= 2 AND automat = 0')->fetchColumn(),
    ];
})();

verifica('nota de două stele intră în cifra de pe panou', true, $cifreDupa['note_mici'] >= 1);

/**
 * „Nu s-a prezentat" pune o stea, dar NU e o părere: e o însemnare a
 * organizatorului. N-are ce căuta în cifra care spune „uită-te la asta".
 */
// Alt eveniment: o pereche (eveniment, cine, cui) nu poate avea două note —
// cheia `uk_evaluari_eveniment_evaluat_evaluator` are grijă de asta.
$evAbsent = faEveniment($gazda, 'tstadm-absent', 'incheiat');

$notaAutomata = $scrieNota($evAbsent, $gazda, $omul, EVALUARE_ABSENT_STELE,
                           EVALUARE_ABSENT_TEXT, true);

$dupaAutomata = (int) db()->query('SELECT COUNT(*) FROM evaluari
                                    WHERE stele <= 2 AND automat = 0')->fetchColumn();

verifica('dar cea automată nu intră în ea', $cifreDupa['note_mici'], $dupaAutomata);
verifica('deși se vede în listă', true, $aNoastraNota($notaAutomata) !== null);
verifica('cu semnul că e automată', 1, (int) $aNoastraNota($notaAutomata)['automat']);

/* ------------------------------ ștergerea ------------------------- */

verifica('nota se găsește după id', true, evaluareaDupaId($notaScrisa) !== null);

/**
 * ȘTERGERE ADEVĂRATĂ, nu golire. Un comentariu cu răspunsuri sub el se
 * golește, fiindcă de el atârnă discuția; de o notă nu atârnă decât o cifră
 * dintr-o medie, iar o cifră „golită" ar fi rămas tot o cifră.
 */
verifica('și pleacă de tot',  true, stergeEvaluarea($notaScrisa));
verifica('rândul nu mai e',   null, evaluareaDupaId($notaScrisa));
verifica('nici în listă',     null, $aNoastraNota($notaScrisa));
verifica('a doua oară nu mai are ce șterge', false, stergeEvaluarea($notaScrisa));

// Cele care n-au fost atinse au rămas la locul lor.
verifica('celelalte sunt tot acolo', true, $aNoastraNota($notaGoala) !== null);

/* ==================================================================== */
sectiune('dorințele');

db()->prepare('INSERT INTO dorinte (membru_id, oras, dorinta, stare_moderare, creat_la, publicat_la)
               VALUES (?,?,?,?,?,NULL)')
    ->execute([$omul, oraseDisponibile()[0] ?? 'Roman', 'TSTADM o dorință care așteaptă',
               'in_asteptare', acum()]);

$idDorinta = (int) db()->lastInsertId();

$aNoastra = static function () use ($idDorinta): ?array {
    foreach (toateDorintele() as $d) {
        if ((int) $d['id'] === $idDorinta) { return $d; }
    }
    return null;
};

verifica('dorința se vede în listă', true, $aNoastra() !== null);
verifica('cu starea ei', 'in_asteptare', (string) $aNoastra()['stare_moderare']);

/**
 * Cele care așteaptă stau în CAPUL listei: acolo e treaba. Așezată după dată,
 * dorința netrecută pe la nimeni ar fi rămas la mijloc, între alte douăzeci.
 */
$primele = array_slice(toateDorintele(), 0, cifreleAdmin()['dorinte']);
$toateAsteapta = true;

foreach ($primele as $d) {
    if ((string) $d['stare_moderare'] !== 'in_asteptare') { $toateAsteapta = false; }
}

verifica('cele în așteptare sunt în cap', true, $toateAsteapta);

/**
 * `publicat_la` NU se pune la aprobare: îl scrie stampileazaCeleAprobate() la
 * prima încărcare a primei pagini, cu ceasul PHP. Așa, o dorință aprobată de
 * aici și una aprobată din phpMyAdmin se poartă la fel.
 */
db()->prepare('UPDATE dorinte SET stare_moderare = "aprobat" WHERE id = ?')->execute([$idDorinta]);
verifica('aprobată, tot fără ștampilă', null, $aNoastra()['publicat_la']);

stampileazaCeleAprobate();
verifica('ștampila vine de la prima pagină', true, $aNoastra()['publicat_la'] !== null);

/**
 * SE ARATĂ TOATE, oricâte ar fi — singura listă de administrare fără tăietură.
 * Rândurile din `dorinte` nu se șterg niciodată, tocmai ca mai târziu să se
 * poată spune câte dorințe și-au pus oamenii; o limită ar fi tăiat chiar
 * istoria pentru care se păstrează.
 */
$q = db()->query('SELECT COUNT(*) FROM dorinte d JOIN membri m ON m.id = d.membru_id');
verifica('lista aduce tot ce e în bază', (int) $q->fetchColumn(), count(toateDorintele()));

// Rândul poartă cu el adresa: de ea atârnă vestea care pleacă la hotărâre.
verifica('rândul are adresa omului', true, ($aNoastra()['email'] ?? '') !== '');

/* ==================================================================== */
sectiune('vorba despre motiv');

require_once __DIR__ . '/../inc/email.php';

/**
 * Motivul e MEREU de scris, niciodată de cerut. Lăsat gol, mesajul spune
 * limpede că nu s-a dat niciunul — altfel omul ar fi primit o veste fără cap și
 * fără coadă, cu un gol în locul explicației.
 */
$gol = cuMotivul(['paragrafe' => ['Ceva s-a întâmplat.']], '');
verifica('fără motiv, niciun chenar', false, isset($gol['motiv']));
verifica('și un paragraf în plus',    2, count($gol['paragrafe']));
verifica('care spune că n-a fost niciunul', true,
    str_contains($gol['paragrafe'][1], 'Nu a fost adăugată o explicație'));
verifica('numai spații tot e „fără"', $gol, cuMotivul(['paragrafe' => ['Ceva s-a întâmplat.']], "  \n "));

/**
 * CU MOTIV, MOTIVUL INTRĂ ÎN CHENAR, nu în curgerea textului.
 *
 * Scris ca paragraf, între alte paragrafe, se citea la fel de repede ca
 * „Era programat pentru miercuri" — adică deloc, tocmai el, singurul rând
 * pentru care omul deschide mesajul. Merge în caseta de citat, aceeași în
 * care se arată comentariul cuiva.
 */
$cu = cuMotivul(['paragrafe' => ['Ceva s-a întâmplat.']], '  Nu se scrie așa.  ');
verifica('cu motiv, se face un chenar',  true, isset($cu['motiv']));
verifica('paragrafele rămân cum erau',   1, count($cu['paragrafe']));
verifica('vorba omului, fără spații',    'Nu se scrie așa.', $cu['motiv']['text']);
verifica('cu eticheta deasupra',         'Motivul, așa cum a fost scris', $cu['motiv']['eticheta']);

/* Eticheta se schimbă după cine a scris motivul. */
verifica('altă etichetă, când se cere', 'Iată explicația',
    cuMotivul([], 'Ceva.', 'Iată explicația')['motiv']['eticheta']);

/* Blocurile primite nu se ating: se întorc altele, cu motivul în ele. */
$inainte = ['paragrafe' => ['Unul.'], 'buton' => ['text' => 'Hai', 'href' => '/']];
cuMotivul($inainte, 'Un motiv.');
verifica('nu scrie peste ce a primit', ['paragrafe' => ['Unul.'], 'buton' => ['text' => 'Hai', 'href' => '/']], $inainte);

/**
 * ȘI CHENARUL CHIAR SE VEDE ÎN MESAJ: dunga din stânga în HTML, „> " în
 * varianta de text. Fără proba asta, un slot scris greșit în șablon ar fi
 * înghițit motivul cu totul — cel mai rău fel de a se strica, fiindcă mesajul
 * pleacă mai departe și pare întreg.
 */
$sablon = sablonEmail('Probă', cuMotivul(
    ['paragrafe' => ['Ceva s-a întâmplat.']], 'Fiindcă așa am vrut.'));

verifica('motivul ajunge în HTML',  true, str_contains($sablon['html'], 'Fiindcă așa am vrut.'));
verifica('cu eticheta lui',         true, str_contains($sablon['html'], 'Motivul, așa cum a fost scris'));
verifica('în caseta cu dungă',      true, str_contains($sablon['html'], 'border-left:3px solid'));
verifica('și citat în text simplu', true, str_contains($sablon['text'], '> Fiindcă așa am vrut.'));

/* Ce se scrie „după" stă DUPĂ chenar, nu înaintea lui. */
$sablon = sablonEmail('Probă', cuMotivul(
    ['paragrafe' => ['Ceva s-a întâmplat.'], 'dupa' => ['Poți încerca din nou.']],
    'Fiindcă așa am vrut.'));

verifica('îndemnul vine după motiv', true,
    strpos($sablon['html'], 'Fiindcă așa am vrut.') < strpos($sablon['html'], 'Poți încerca din nou.'));
verifica('la fel și în text simplu', true,
    strpos($sablon['text'], 'Fiindcă așa am vrut.') < strpos($sablon['text'], 'Poți încerca din nou.'));

/* Un motiv scris de om NU poate strecura etichete în mesajul altcuiva. */
$sablon = sablonEmail('Probă', cuMotivul([], 'Motiv cu <script>alert(1)</script> în el.'));
verifica('motivul e scăpat în HTML', false, str_contains($sablon['html'], '<script>'));

/* ==================================================================== */
if ($BAZA === '') {
    echo "\n(sar peste HTTP: dă adresa serverului ca argument, "
       . "ex. php teste/test-admin.php http://127.0.0.1:8099)\n";
} else {
    sectiune('paza: paginile');

    /** Ce răspunde o pagină pentru cine nu e conectat. */
    $ia = static function (string $cale) use ($BAZA): array {
        $raw = @file_get_contents($BAZA . $cale, false, stream_context_create([
            'http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10],
        ]));

        $cod  = 0;
        $unde = '';

        foreach ($http_response_header ?? [] as $rand) {
            if (preg_match('~^HTTP/\S+\s+(\d+)~', $rand, $m)) { $cod = (int) $m[1]; }
            if (stripos($rand, 'Location:') === 0) { $unde = trim(substr($rand, 9)); }
        }

        return ['cod' => $cod, 'unde' => $unde, 'corp' => (string) $raw];
    };

    $pagini = ['/admin.php', '/admin-evenimente.php', '/admin-comentarii.php',
               '/admin-contact.php', '/admin-useri.php', '/admin-evaluari.php',
               '/admin-dorinte.php', '/coduri.php'];

    $toateInchise = true;
    $scapate      = [];

    foreach ($pagini as $cale) {
        $r = $ia($cale);

        // 302 spre login: nimeni nu vede nimic fără cont.
        if ($r['cod'] !== 302 || !str_contains($r['unde'], 'login.php')) {
            $toateInchise = false;
            $scapate[]    = $cale . ' → ' . $r['cod'] . ' ' . $r['unde'];
        }
    }

    verifica('toate paginile cer cont', true, $toateInchise);

    if ($scapate !== []) {
        echo '   scăpate: ' . implode(', ', $scapate) . "\n";
    }

    // Și niciuna nu scapă nimic în corp, chiar dacă redirecționează.
    $scurgeri = false;

    foreach ($pagini as $cale) {
        if (str_contains($ia($cale)['corp'], 'admin-tabel')) { $scurgeri = true; }
    }

    verifica('și niciuna nu scapă tabelul', false, $scurgeri);

    sectiune('paza: punctul de intrare');

    $cheama = static function (array $date) use ($BAZA): array {
        $raw = @file_get_contents($BAZA . '/api/admin.php', false, stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => json_encode($date),
                'ignore_errors' => true, 'timeout' => 10,
            ],
        ]));

        $cod = 0;

        foreach ($http_response_header ?? [] as $rand) {
            if (preg_match('~^HTTP/\S+\s+(\d+)~', $rand, $m)) { $cod = (int) $m[1]; }
        }

        return ['cod' => $cod, 'corp' => json_decode((string) $raw, true)];
    };

    $r = $cheama(['fapta' => 'sterge-dorinta', 'id' => $idDorinta]);
    verifica('fără token CSRF, 419', 419, $r['cod']);

    $r = $cheama(['csrf' => 'oarecare', 'fapta' => 'sterge-dorinta', 'id' => $idDorinta]);
    verifica('nelogat, nu trece', true, in_array($r['cod'], [401, 419], true));

    verifica('și dorința e tot acolo', true, $aNoastra() !== null);

    // GET nu e primit deloc: faptele se fac numai prin POST.
    $raw = @file_get_contents($BAZA . '/api/admin.php', false, stream_context_create([
        'http' => ['ignore_errors' => true, 'timeout' => 10],
    ]));
    $cod = 0;
    foreach ($http_response_header ?? [] as $rand) {
        if (preg_match('~^HTTP/\S+\s+(\d+)~', $rand, $m)) { $cod = (int) $m[1]; }
    }
    verifica('GET nu e primit', 405, $cod);

    /* ================================================================== */
    sectiune('hotărârea unei dorințe, din lista de ales');

    /**
     * De aici încolo se cere o sesiune de om de casă: fapta se face numai prin
     * api/admin.php, iar aceea nu se lasă chemată de nimeni altcineva.
     */
    $ceruta = static function (string $cale, ?array $trup, string $cookie) use ($BAZA): array {
        $ctx = stream_context_create(['http' => [
            'method'        => $trup === null ? 'GET' : 'POST',
            'header'        => "Content-Type: application/json\r\n"
                             . ($cookie === '' ? '' : "Cookie: $cookie\r\n"),
            'content'       => $trup === null ? '' : json_encode($trup),
            'ignore_errors' => true,
            'timeout'       => 10,
        ]]);

        $corp = (string) @file_get_contents($BAZA . $cale, false, $ctx);
        $cod  = 0;
        $nou  = $cookie;

        foreach ($http_response_header ?? [] as $rand) {
            if (preg_match('~^HTTP/\S+\s+(\d+)~', $rand, $m)) { $cod = (int) $m[1]; }
            if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $rand, $m) === 1) { $nou = $m[1]; }
        }

        return ['cod' => $cod, 'corp' => json_decode($corp, true), 'brut' => $corp,
                'cookie' => $nou];
    };

    $pag = $ceruta('/login.php', null, '');
    preg_match('/name="csrf" value="([^"]+)"/', $pag['brut'], $m);

    $intrat = $ceruta('/api/autentificare.php',
        ['csrf' => $m[1] ?? '', 'email' => SEMN . 'gazda@invalid.local', 'parola' => PAROLA],
        $pag['cookie']);

    $cookieGazda = ($intrat['corp']['ok'] ?? false) === true ? $intrat['cookie'] : '';
    verifica('omul de casă a intrat', true, $cookieGazda !== '');

    if ($cookieGazda === '') {
        echo "   (fără sesiune nu se poate proba hotărârea)\n";
    } else {
        /* Tokenul CSRF se ia de pe chiar pagina de unde se apasă. */
        $pagDorinte = $ceruta('/admin-dorinte.php', null, $cookieGazda);
        preg_match('/data-csrf="([^"]+)"/', $pagDorinte['brut'], $m);
        $csrf = $m[1] ?? '';

        verifica('pagina se deschide pentru staff', 200, $pagDorinte['cod']);
        verifica('și are tokenul ei', true, $csrf !== '');

        /**
         * LISTA A LUAT LOCUL CELOR TREI BUTOANE. Se probează pe HTML, nu pe
         * vorbe: cine ar pune la loc un buton ar trece altfel neobservat.
         */
        verifica('e o listă de ales, nu butoane', true,
            str_contains($pagDorinte['brut'], 'data-fapta="modereaza-dorinta"')
            && str_contains($pagDorinte['brut'], 'data-camp="hotarare"'));
        verifica('cu ștergerea în ea', true,
            str_contains($pagDorinte['brut'], 'data-fapta="sterge-dorinta"'));
        verifica('și cu întrebarea de dinaintea ei', true,
            str_contains($pagDorinte['brut'], 'data-intreb="Ștergi dorința'));
        verifica('motivul se cere doar la respingere', true,
            str_contains($pagDorinte['brut'], 'data-motiv-pentru="respins"'));

        $hotaraste = static function (string $stare) use ($ceruta, $cookieGazda, $csrf, $idDorinta): array {
            return $ceruta('/api/admin.php', [
                'csrf' => $csrf, 'fapta' => 'modereaza-dorinta',
                'id' => $idDorinta, 'hotarare' => $stare,
            ], $cookieGazda);
        };

        /* Dorința e „aprobat" din secțiunea de mai sus. */
        verifica('pornim de la aprobat', 'aprobat', (string) $aNoastra()['stare_moderare']);

        /**
         * DRUMUL ÎNAPOI, care înainte nu exista: o dorință hotărâtă rămânea
         * hotărâtă, iar un „Respinge" apăsat pe rândul greșit se îndrepta doar
         * din phpMyAdmin.
         */
        $r = $hotaraste('in_asteptare');
        verifica('se poate întoarce în așteptare', true, ($r['corp']['ok'] ?? false) === true);
        verifica('și chiar se întoarce', 'in_asteptare', (string) $aNoastra()['stare_moderare']);
        verifica('fără să-i scrie omului', true,
            str_contains((string) ($r['corp']['mesaj'] ?? ''), 'Nu i-am scris'));

        /**
         * ALEASĂ STAREA ÎN CARE E DEJA: nu se scrie nimic și, mai ales, nu
         * pleacă niciun e-mail. Se întâmplă de la sine când cineva deschide
         * lista și o închide la loc.
         */
        $r = $hotaraste('in_asteptare');
        verifica('a doua oară nu schimbă nimic', true, ($r['corp']['ok'] ?? false) === true);
        verifica('și o spune', true,
            str_contains((string) ($r['corp']['mesaj'] ?? ''), 'Era deja acolo'));

        $r = $hotaraste('aprobat');
        verifica('se aprobă din listă', true, ($r['corp']['ok'] ?? false) === true);
        verifica('starea e aprobat', 'aprobat', (string) $aNoastra()['stare_moderare']);

        /**
         * RÂNDUL ÎN CARE DORINȚA E DEJA SE SCRIE CA STARE, nu ca poruncă.
         *
         * O listă strânsă arată un singur rând: cel ales. Cu „Aprobă" pe el,
         * o dorință aprobată purta o poruncă — un lucru rămas de făcut —
         * tocmai despre ceva ce se făcuse.
         */
        $dupaAprobare = $ceruta('/admin-dorinte.php', null, $cookieGazda)['brut'];
        preg_match('~<select[^>]*data-id="' . $idDorinta . '".*?</select>~s',
                   $dupaAprobare, $m);
        $listaEi = $m[0] ?? '';

        verifica('aprobată, rândul ales scrie „Aprobat"', true,
            str_contains($listaEi, 'selected>') && str_contains($listaEi, 'Aprobat'));
        verifica('iar celelalte rămân fapte', true,
            str_contains($listaEi, 'Respinge') && str_contains($listaEi, 'Așteptare'));
        verifica('și nu scrie „Aprobă" nicăieri în ea', false,
            str_contains($listaEi, 'Aprobă'));

        /* Răzgândirea merge, și ea trimite vestea: dorința tocmai a ieșit de pe tablă. */
        $r = $hotaraste('respins');
        verifica('și se poate răzgândi', 'respins', (string) $aNoastra()['stare_moderare']);
        verifica('la respingere se dă de veste', true,
            str_contains((string) ($r['corp']['mesaj'] ?? ''), 'I-am dat de veste'));

        /* Aceeași regulă și de partea cealaltă: „Respins", nu „Respinge". */
        preg_match('~<select[^>]*data-id="' . $idDorinta . '".*?</select>~s',
                   $ceruta('/admin-dorinte.php', null, $cookieGazda)['brut'], $m);
        $listaEi = $m[0] ?? '';

        verifica('respinsă, rândul ales scrie „Respins"', true,
            str_contains($listaEi, 'Respins') && !str_contains($listaEi, 'Respinge'));
        verifica('iar „Aprobă" e din nou o faptă', true,
            str_contains($listaEi, 'Aprobă'));

        /* O stare care nu există nu trece, oricât ar semăna cu una adevărată. */
        $r = $hotaraste('sters');
        verifica('„sters" nu e o hotărâre', 422, $r['cod']);
        verifica('și starea a rămas', 'respins', (string) $aNoastra()['stare_moderare']);

        /**
         * `publicat_la` NU se pune la aprobare nici pe drumul ăsta: îl scrie
         * tot stampileazaCeleAprobate(), de pe prima pagină.
         */
        db()->prepare('UPDATE dorinte SET publicat_la = NULL WHERE id = ?')->execute([$idDorinta]);
        $hotaraste('in_asteptare');
        $hotaraste('aprobat');
        verifica('aprobată prin API, tot fără ștampilă', null, $aNoastra()['publicat_la']);

        /* ============================================================== */
        sectiune('mesajele rămase pe drumuri');

        /**
         * Panoul arată rândurile din coadă care n-au plecat nici după toate
         * încercările, cu vorba serverului pe fiecare, și un „×" care le
         * șterge. Până acum se vedea doar cifra lor — ca să afli DE CE,
         * trebuia deschis phpMyAdmin.
         *
         * ȘTERGEREA E O FAPTĂ, deci se probează și PAZA ei: se face cu un
         * formular adevărat spre /admin.php, nu prin api/admin.php, deci
         * regula scrisă acolo n-o acoperă.
         */
        require_once __DIR__ . '/../inc/coada.php';

        $adresaMoarta = 'nimeni-' . bin2hex(random_bytes(4)) . '@coada-proba.invalid';

        /**
         * VORBA SERVERULUI E UNA IRECUNOSCIBILĂ, nu „No Such User Here".
         *
         * Aceea e scrisă și în lămurirea din admin.php, ca pildă — iar proba ar
         * fi trecut liniștită căutându-și propriul comentariu, chiar cu coloana
         * scoasă din tabel. S-a întâmplat la scriere.
         */
        $vorbaLui = 'Vorbă de probă ' . bin2hex(random_bytes(4));

        puneInCoada($adresaMoarta, 'Subiect de probă', ['paragrafe' => ['Ceva.']]);

        db()->prepare(
            'UPDATE coada_emailuri SET incercari = ?, eroare = ? WHERE catre = ?'
        )->execute([COADA_INCERCARI_MAX, $vorbaLui, $adresaMoarta]);

        $idPicat = (int) db()->query(
            'SELECT id FROM coada_emailuri WHERE catre = ' . db()->quote($adresaMoarta)
        )->fetchColumn();

        $panou = $ceruta('/admin.php', null, $cookieGazda);

        verifica('panoul arată adresa', true,
            str_contains($panou['brut'], $adresaMoarta));
        verifica('și vorba serverului',  true,
            str_contains($panou['brut'], $vorbaLui));

        preg_match('/name="csrf" value="([^"]+)"/', $panou['brut'], $m);
        $csrfPanou = $m[1] ?? '';

        /** Formularele de aici merg cu câmpuri, nu cu JSON. */
        $apasa = static function (array $campuri, string $cookie) use ($BAZA): int {
            $ctx = stream_context_create(['http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/x-www-form-urlencoded\r\n"
                                 . ($cookie === '' ? '' : "Cookie: $cookie\r\n"),
                'content'       => http_build_query($campuri),
                'follow_location' => 0,
                'ignore_errors' => true,
                'timeout'       => 10,
            ]]);

            @file_get_contents($BAZA . '/admin.php', false, $ctx);

            foreach ($http_response_header ?? [] as $rand) {
                if (preg_match('~^HTTP/\S+\s+(\d+)~', $rand, $mm)) { return (int) $mm[1]; }
            }

            return 0;
        };

        $maiE = static fn (): bool => (bool) db()->query(
            'SELECT COUNT(*) FROM coada_emailuri WHERE id = ' . $idPicat
        )->fetchColumn();

        /* Fără sesiune de om de casă nu se șterge nimic. */
        $apasa(['csrf' => $csrfPanou, 'picat' => $idPicat], '');
        verifica('un străin nu poate șterge', true, $maiE());

        /* Nici cu sesiune bună, dar fără token. */
        $apasa(['picat' => $idPicat], $cookieGazda);
        verifica('nici fără token CSRF',   true, $maiE());

        $cod = $apasa(['csrf' => $csrfPanou, 'picat' => $idPicat], $cookieGazda);
        verifica('omul de casă îl șterge', false, $maiE());
        verifica('și e trimis înapoi pe panou', 302, $cod);

        db()->prepare('DELETE FROM coada_emailuri WHERE catre = ?')->execute([$adresaMoarta]);
    }
}

printf("\n%s\nTOTAL: %d trecute, %d picate\n", str_repeat('=', 60), $treceri, $picaturi);
exit($picaturi > 0 ? 1 : 0);
