<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — evaluările dintre participanți.
 *
 * Se dau DUPĂ un eveniment, între cei care au fost la el, și sunt ANONIME:
 * pe profil se vede nota și textul, niciodată cine le-a scris. Altfel nimeni
 * n-ar mai da patru stele cuiva pe care îl reîntâlnește sâmbăta viitoare — iar
 * o notă care se semnează e o notă frumoasă, adică una care nu spune nimic.
 *
 * Singura excepție e nota automată: „Nu s-a prezentat", pusă de organizator.
 * Aceea e un fapt, nu o părere, și se arată ca atare.
 */

require_once __DIR__ . '/interese.php';

/** Câte evaluări se văd pe profil, înainte de „Vezi mai mult". */
const EVALUARI_DEODATA = 10;

/** Nota pe care o primește cine nu s-a prezentat. */
const EVALUARE_ABSENT_STELE = 1;

/** Textul pus în locul părerii, când nota o pune site-ul. */
const EVALUARE_ABSENT_TEXT = 'Nu s-a prezentat la eveniment, deși confirmase participarea.';

/* ============================== CITIREA ============================== */

/**
 * Nota pe care i-am dat-o eu, la evenimentul ăsta — sau null.
 *
 * Întoarce rândul întreg: pagina evenimentului are nevoie de stele, ca să
 * aprindă cele deja date, iar profilul și de text, ca să-l pună în casetă.
 */
function evaluareaMea(int $evenimentId, int $evaluatorId, int $evaluatId): ?array
{
    if ($evaluatorId <= 0 || $evaluatId <= 0) {
        return null;
    }

    $q = db()->prepare(
        'SELECT id, stele, text, automat FROM evaluari
          WHERE eveniment_id = ? AND evaluator_id = ? AND evaluat_id = ? LIMIT 1'
    );
    $q->execute([$evenimentId, $evaluatorId, $evaluatId]);

    $rand = $q->fetch();

    return $rand !== false ? $rand : null;
}

/**
 * Notele pe care le-am dat eu la evenimentul ăsta, după cine le-a primit.
 *
 * O singură cerere pentru toată lista de participanți, nu una per rând: la un
 * eveniment cu treizeci de oameni, ar fi fost treizeci de întrebări în care
 * răspunsul se știe dintr-o dată.
 */
function noteleMeleLaEveniment(int $evenimentId, int $evaluatorId): array
{
    if ($evaluatorId <= 0) {
        return [];
    }

    $q = db()->prepare(
        'SELECT evaluat_id, stele FROM evaluari
          WHERE eveniment_id = ? AND evaluator_id = ?'
    );
    $q->execute([$evenimentId, $evaluatorId]);

    $note = [];

    foreach ($q->fetchAll() as $rand) {
        $note[(int) $rand['evaluat_id']] = (int) $rand['stele'];
    }

    return $note;
}

/**
 * Media, câte sunt, și distribuția pe stele — tot ce arată profilul.
 *
 * Întoarce mereu aceleași chei, chiar și pentru cine n-a primit nimic, ca cine
 * cheamă funcția să nu fie nevoit să se apere de lipsa lor.
 *
 * Distribuția are mereu cele cinci trepte, de la 5 la 1, chiar goale: barele
 * de pe profil se desenează din ea, iar una lipsă ar fi sărit un rând.
 */
function rezumatEvaluari(int $membruId): array
{
    $gol = ['medie' => 0.0, 'cate' => 0, 'distributie' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]];

    if ($membruId <= 0) {
        return $gol;
    }

    $q = db()->prepare(
        'SELECT stele, COUNT(*) AS cate FROM evaluari
          WHERE evaluat_id = ? GROUP BY stele'
    );
    $q->execute([$membruId]);

    $rezumat = $gol;
    $suma    = 0;

    foreach ($q->fetchAll() as $rand) {
        $stele = (int) $rand['stele'];
        $cate  = (int) $rand['cate'];

        if ($stele < 1 || $stele > 5) {
            continue;
        }

        $rezumat['distributie'][$stele] = $cate;
        $rezumat['cate'] += $cate;
        $suma            += $stele * $cate;
    }

    if ($rezumat['cate'] > 0) {
        $rezumat['medie'] = round($suma / $rezumat['cate'], 1);
    }

    return $rezumat;
}

/**
 * Evaluările primite de un om, cele mai noi întâi.
 *
 * Fără nimic despre cine le-a dat — nici id, nici nume, nici chip. Ce nu se
 * citește din bază nu poate ajunge din greșeală în pagină.
 *
 * Vine cu titlul și slugul evenimentului: nota are greutate tocmai fiindcă în
 * spatele ei e o seară anume, iar cine citește profilul trebuie s-o poată
 * deschide.
 */
function evaluarilePrimite(int $membruId): array
{
    if ($membruId <= 0) {
        return [];
    }

    $q = db()->prepare(
        'SELECT ev.stele, ev.text, ev.automat, ev.creat_la, ev.actualizat_la,
                e.titlu AS eveniment_titlu, e.slug AS eveniment_slug
           FROM evaluari ev
           JOIN evenimente e ON e.id = ev.eveniment_id
          WHERE ev.evaluat_id = ?
          ORDER BY ev.creat_la DESC, ev.id DESC'
    );
    $q->execute([$membruId]);

    return $q->fetchAll();
}

/* ============================== SCRIEREA ============================= */

/**
 * De ce nu poate omul ăsta să-l noteze pe celălalt.
 *
 * Întoarce '' dacă poate, altfel motivul.
 *
 * UN SINGUR LOC pentru toate opreliștile, ca la participare: de el atârnă și
 * stelele desenate în pagină, și refuzul din api/evaluare.php. Scrise separat,
 * s-ar fi despărțit.
 *
 * Regulile, pe scurt: după ce s-a terminat, între oameni care au fost acolo,
 * și nu pe tine însuți.
 */
function motivBlocajEvaluare(array $eveniment, int $evaluatorId, int $evaluatId): string
{
    if ($evaluatorId <= 0) {
        return 'Intră în cont ca să dai o notă.';
    }

    if ($evaluatorId === $evaluatId) {
        return 'Nu te poți nota pe tine.';
    }

    /**
     * Abia după ce s-a terminat.
     *
     * În timpul evenimentului nimeni n-are ce nota: omul e acolo, iar părerea
     * despre cum a fost se face la sfârșit, nu la mijloc.
     */
    if (!evenimentIncheiat($eveniment)) {
        return 'Notele se dau după ce se încheie evenimentul.';
    }

    $evenimentId = (int) $eveniment['id'];

    // Amândoi trebuie să fi fost pe listă. Cine s-a uitat de departe n-are ce
    // spune despre cum s-a purtat cineva acolo.
    if (interesulMeu($evenimentId, $evaluatorId) !== 'participant') {
        return 'Poți nota doar dacă ai fost și tu pe lista de participanți.';
    }

    if (interesulMeu($evenimentId, $evaluatId) !== 'participant') {
        return 'Omul ăsta n-a fost pe lista de participanți.';
    }

    return '';
}

/**
 * Scrie nota, sau o schimbă pe cea de dinainte.
 *
 * „INSERT ... ON DUPLICATE KEY UPDATE": stelele date de pe pagina
 * evenimentului și textul scris mai târziu pe profil ajung în același rând, nu
 * în două. `creat_la` NU se atinge la schimbare — ține minte când s-a format
 * prima părere.
 *
 * $text null înseamnă „doar stele, fără vorbe". La o a doua trecere fără text,
 * textul de dinainte se PĂSTREAZĂ: cine schimbă stelele de pe pagina
 * evenimentului n-are de unde să știe că șterge cu asta ce scrisese pe profil.
 */
function salveazaEvaluare(
    int $evenimentId,
    int $evaluatId,
    int $evaluatorId,
    int $stele,
    ?string $text = null,
    bool $automat = false
): void {
    $acum = acum();

    $q = db()->prepare(
        'INSERT INTO evaluari
                (eveniment_id, evaluat_id, evaluator_id, stele, text, automat, creat_la, actualizat_la)
         VALUES (?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE stele         = VALUES(stele),
                                 text          = COALESCE(VALUES(text), text),
                                 automat       = VALUES(automat),
                                 actualizat_la = VALUES(actualizat_la)'
    );

    $q->execute([
        $evenimentId, $evaluatId, $evaluatorId, $stele, $text,
        $automat ? 1 : 0, $acum, $acum,
    ]);
}

/**
 * Pe cine a însemnat organizatorul ca neprezentat, la evenimentul ăsta.
 *
 * Se citește o dată, pentru toată lista, nu o dată pe rând. Întoarce o hartă
 * `id => true`, ca event.php să poată întreba dintr-o privire.
 *
 * Doar cele automate: o steluță dată cu mâna, fiindcă omul chiar a fost
 * nesuferit, nu înseamnă că n-a venit.
 */
function absentiiInsemnati(int $evenimentId, int $organizatorId): array
{
    if ($organizatorId <= 0) {
        return [];
    }

    $q = db()->prepare(
        'SELECT evaluat_id FROM evaluari
          WHERE eveniment_id = ? AND evaluator_id = ? AND automat = 1'
    );
    $q->execute([$evenimentId, $organizatorId]);

    $absenti = [];

    foreach ($q->fetchAll() as $rand) {
        $absenti[(int) $rand['evaluat_id']] = true;
    }

    return $absenti;
}

/**
 * Poate omul ăsta să dea note la evenimentul ăsta?
 *
 * Se întreabă O DATĂ pentru toată lista, nu o dată pentru fiecare rând: la un
 * eveniment cu treizeci de oameni, motivBlocajEvaluare() ar fi însemnat
 * treizeci de întrebări cu același răspuns.
 *
 * Ce rămâne pe seama rândului e doar „nu pe mine însumi" — iar aceea se vede
 * din id, fără nicio cerere.
 */
function potNotaLaEveniment(array $eveniment, int $membruId): bool
{
    return $membruId > 0
        && evenimentIncheiat($eveniment)
        && interesulMeu((int) $eveniment['id'], $membruId) === 'participant';
}

/* ========================= CUM ARATĂ PE ECRAN ======================== */

/**
 * Rezumatul de pe profil: media, câte sunt, barele pe stele.
 *
 * Întoarce HTML, nu-l tipărește: îl cere profilul la încărcare, iar
 * api/evaluare.php după ce cineva tocmai a notat de pe pagina lui.
 */
function randeazaRezumatEvaluari(array $rezumat): string
{
    $cate  = (int) $rezumat['cate'];
    $medie = (float) $rezumat['medie'];

    if ($cate === 0) {
        return '<p class="feedback__gol">Nicio evaluare încă. '
             . 'Notele vin de la oamenii cu care a fost la evenimente.</p>';
    }

    $bare = '';

    // De la 5 la 1, ca la orice listă de note: sus e ce vrea toată lumea.
    foreach ([5, 4, 3, 2, 1] as $stele) {
        $cateAici = (int) ($rezumat['distributie'][$stele] ?? 0);
        $procent  = $cate > 0 ? round($cateAici / $cate * 100) : 0;

        $bare .= '<li><span>' . $stele . '</span>'
               . '<div class="rating-bar"><i style="width:' . $procent . '%"></i></div>'
               . '<span>' . $cateAici . '</span></li>';
    }

    return '<div class="rating-summary__score">'
         . '<span class="rating-summary__value">' . h(number_format($medie, 1, ',', '')) . '</span>'
         . '<div class="rating__stars" data-stars="' . h((string) $medie) . '"></div>'
         . '<span class="rating-summary__count">'
         . ($cate === 1 ? 'o evaluare' : $cate . ' evaluări')
         . '</span></div>'
         . '<ul class="rating-bars">' . $bare . '</ul>';
}

/**
 * Lista de evaluări primite, gata desenată.
 *
 * Fără chip și fără nume: notele sunt anonime. În locul autorului stă
 * evenimentul după care s-a dat nota — asta e și ce contează, fiindcă de acolo
 * i se trage greutatea.
 *
 * Toate intră în pagină; ascunsul îl face main.js, ca la comentarii și la
 * listele din taburi.
 */
function randeazaEvaluari(array $evaluari): string
{
    if ($evaluari === []) {
        return '';
    }

    $html = '';

    foreach ($evaluari as $ev) {
        $automat = (int) $ev['automat'] === 1;
        $stele   = (int) $ev['stele'];

        /**
         * O notă automată nu e o părere, e un fapt: se scrie pe față cine a
         * pus-o și de ce. Una obișnuită rămâne anonimă — în capul ei stă
         * evenimentul, nu omul.
         */
        $antet = $automat
            ? '<span class="comment__author comment__author--sistem">Însemnare de la organizator</span>'
            : '<span class="comment__author comment__author--sistem">Evaluare anonimă</span>';

        $undeva = '<a class="evaluare__eveniment" href="'
                . h(urlEveniment((string) $ev['eveniment_slug'])) . '">'
                . h((string) $ev['eveniment_titlu']) . '</a>';

        $cand = (string) $ev['creat_la'];

        $text = $automat
            ? EVALUARE_ABSENT_TEXT
            : trim((string) ($ev['text'] ?? ''));

        $html .= '<li class="comment evaluare' . ($automat ? ' evaluare--automata' : '') . '">'
               . '<article class="comment__body">'
               . '<div class="comment__main">'
               . '<div class="comment__head">'
               . '<div class="comment__cine">' . $antet . '</div>'
               . '<div class="comment__cand">'
               . '<time datetime="' . h(str_replace(' ', 'T', $cand)) . '">' . h(timpRelativ($cand)) . '</time>'
               . '<span class="dot" aria-hidden="true"></span>' . $undeva
               . '</div></div>'
               . '<div class="rating__stars rating__stars--sm" data-stars="' . $stele . '"></div>'
               . ($text !== '' ? '<p class="comment__text">' . nl2br(h($text), false) . '</p>' : '')
               . '</div></article></li>';
    }

    return $html;
}
