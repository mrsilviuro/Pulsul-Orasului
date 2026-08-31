<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — urmărirea unui organizator.
 *
 * Butonul „Urmărește" de pe profilul cuiva și de pe pagina anunțurilor lui.
 * Cine îl apasă primește un e-mail ori de câte ori omul acela pune un anunț
 * nou pe site: UN SINGUR CARTONAȘ, cu evenimentul acela, nu o listă.
 *
 * DE CE, când există deja newsletterul zilnic. Acela vine cu tot ce se
 * întâmplă în oraș, iar mulți nu vor atât. Cine ține la un singur om — cel
 * care organizează în fiecare joi alergarea de seară — vrea să afle despre
 * ELE, nu despre restul. Urmărirea e newsletterul strâns la un singur om.
 *
 * NU EXISTĂ „DEZ-URMĂRIRE" SCRISĂ NICĂIERI. A doua apăsare șterge rândul, iar
 * un rând care nu există spune același lucru ca unul cu un steag pe „nu" —
 * doar că nimeni nu mai trebuie să-l întrebe. Tot de aceea nu e nevoie de o
 * bifă nouă în setări: ieșirea de la mesajele astea e chiar butonul din care
 * s-a intrat, iar el stă pe profilul omului, nu ascuns în altă pagină.
 *
 * VESTEA PLEACĂ O SINGURĂ DATĂ PE ANUNȚ (`urmaritori_instiintati_la`, sql/033),
 * și ștampila se pune ÎNAINTE de trimitere, ca la newsletter: dintre „a plecat
 * de două ori" și „n-a plecat fiindcă a căzut curentul între ștampilă și
 * poștă", se alege a doua. Un e-mail plecat nu se ia înapoi. Fără ea, un anunț
 * respins și aprobat din nou ar fi scris de două ori acelorași oameni.
 */

require_once __DIR__ . '/evenimente.php';
require_once __DIR__ . '/newsletter.php';   // randuriPentruNewsletter()
require_once __DIR__ . '/email.php';

/* ============================== ÎNTREBĂRI ============================= */

/**
 * Se poate urmări omul ăsta?
 *
 * NU pe tine însuți — ar fi un buton care nu duce nicăieri, iar apoi ți-ai fi
 * trimis singur e-mail la fiecare anunț. Și nu un cont care nu mai e al
 * nimănui: acolo n-o să mai apară niciodată nimic.
 */
function poateFiUrmarit(?array $eu, array $omul): bool
{
    if ($eu === null) {
        return false;
    }

    if ((int) $eu['id'] === (int) $omul['id']) {
        return false;
    }

    return !esteContSters($omul['stare'] ?? null);
}

/** Îl urmăresc deja? */
function esteUrmarit(int $urmaritorId, int $urmaritId): bool
{
    if ($urmaritorId <= 0 || $urmaritId <= 0) {
        return false;
    }

    $q = db()->prepare(
        'SELECT 1 FROM urmariri WHERE urmaritor_id = ? AND urmarit_id = ? LIMIT 1'
    );
    $q->execute([$urmaritorId, $urmaritId]);

    return $q->fetchColumn() !== false;
}

/** Câți îl urmăresc. Cifra de lângă buton. */
function catiUrmaritori(int $urmaritId): int
{
    if ($urmaritId <= 0) {
        return 0;
    }

    $q = db()->prepare('SELECT COUNT(*) FROM urmariri WHERE urmarit_id = ?');
    $q->execute([$urmaritId]);

    return (int) $q->fetchColumn();
}

/* =============================== FAPTA =============================== */

/**
 * Apasă butonul: dacă nu-l urmărea, începe; dacă îl urmărea, se oprește.
 *
 * Întoarce ['urmareste' => bool, 'cati' => int] — starea de DUPĂ, ca pagina
 * să n-o mai ceară o dată.
 *
 * HOTĂRÂREA E ÎN BAZĂ, nu în PHP. Se încearcă întâi scrierea, iar cheia unică
 * din sql/033 e cea care spune dacă mai era acolo: două apăsări venite deodată
 * de pe două file n-au cum să scrie două rânduri, oricât s-ar nimeri. Un
 * „întreabă, apoi scrie" ar fi lăsat loc între cele două întrebări.
 */
function comutaUrmarirea(int $urmaritorId, int $urmaritId): array
{
    $q = db()->prepare(
        'INSERT IGNORE INTO urmariri (urmaritor_id, urmarit_id, creat_la) VALUES (?,?,?)'
    );
    $q->execute([$urmaritorId, $urmaritId, acum()]);

    $aInceput = $q->rowCount() === 1;

    if (!$aInceput) {
        // Era deja acolo: a doua apăsare o ia înapoi.
        db()->prepare('DELETE FROM urmariri WHERE urmaritor_id = ? AND urmarit_id = ?')
            ->execute([$urmaritorId, $urmaritId]);
    }

    return ['urmareste' => $aInceput, 'cati' => catiUrmaritori($urmaritId)];
}

/* ========================== CUI I SE SCRIE =========================== */

/**
 * Urmăritorii cărora chiar li se poate scrie.
 *
 * Doar conturile ACTIVE: unul suspendat sau anonimizat n-are unde primi nimic
 * — aceeași regulă ca la omulDeInstiintat() din inc/interese.php.
 *
 * Organizatorul nu se poate urmări pe sine (vezi poateFiUrmarit), deci nu e
 * nevoie să fie scos și aici.
 */
function urmaritoriDeInstiintat(int $urmaritId): array
{
    $q = db()->prepare(
        'SELECT m.id, m.email, m.prenume
           FROM urmariri u
           JOIN membri m ON m.id = u.urmaritor_id
          WHERE u.urmarit_id = ?
            AND m.stare = \'activ\'
            AND m.email <> \'\'
          ORDER BY u.id'
    );
    $q->execute([$urmaritId]);

    return $q->fetchAll();
}

/**
 * Pune ștampila „le-am dat de veste" — și spune dacă a prins ea.
 *
 * HOTĂRÂREA E ÎN `WHERE`, ca la revendicarea unui abțibild: cine n-o prinde nu
 * trimite nimic. Două cereri venite deodată (staff-ul apasă „Aprobă" de două
 * ori, sau anunțul se aprobă în timp ce se salvează) nu pot trimite amândouă.
 */
function insemneazaUrmaritoriiInstiintati(int $evenimentId): bool
{
    $q = db()->prepare(
        'UPDATE evenimente SET urmaritori_instiintati_la = ?
          WHERE id = ? AND urmaritori_instiintati_la IS NULL'
    );
    $q->execute([acum(), $evenimentId]);

    return $q->rowCount() === 1;
}

/**
 * Vestea către urmăritorii organizatorului, la un anunț care tocmai a ajuns
 * public.
 *
 * Se cheamă din DOUĂ locuri, fiindcă atâtea fac un anunț să se vadă:
 *   – api/eveniment.php, când îl scrie un om de casă (intră „aprobat" de-a
 *     dreptul, fără să treacă pe la nimeni);
 *   – api/modereaza-eveniment.php, când e aprobat de staff.
 *
 * Întoarce câte mesaje au plecat. Zero e un răspuns bun: n-are urmăritori, sau
 * vestea plecase deja.
 *
 * NU se uită la bifa de newsletter din setări. Aceea e pentru „tot ce se
 * întâmplă azi în oraș", adică pentru ce n-a cerut nimeni anume; asta vine
 * fiindcă omul a apăsat el însuși un buton, pe profilul cuiva. Ieșirea e tot
 * acolo, la o apăsare.
 */
function instiinteazaUrmaritorii(int $evenimentId): int
{
    $q = db()->prepare(
        'SELECT e.id, e.titlu, e.slug, e.coperta, e.descriere, e.oras, e.locatie,
                e.ora_inceput, e.data_eveniment, e.membru_id, e.stare_moderare,
                c.nume AS categorie, c.imagine_default,
                m.nume AS org_nume, m.prenume AS org_prenume, m.stare AS org_stare,
                m.permalink AS org_permalink
           FROM evenimente e
           JOIN categorii c ON c.id = e.categorie_id
           JOIN membri m    ON m.id = e.membru_id
          WHERE e.id = ?
          LIMIT 1'
    );
    $q->execute([$evenimentId]);

    $ev = $q->fetch();

    /**
     * NUMAI UN ANUNȚ CARE SE VEDE ȘI CARE N-A TRECUT ÎNCĂ.
     *
     * Amândouă întrebările sunt cele de peste tot de pe site, nu unele scrise
     * de mână aici — dacă mâine se schimbă ce înseamnă „publicat", funcția asta
     * nu rămâne în urmă.
     *
     * A DOUA E CEA CARE SURPRINDE: `evenimentPublicat()` spune DA și pentru
     * unul încheiat, fiindcă pagina lui se vede mai departe. Dar „X a pus un
     * anunț nou" despre o seară care a trecut deja e o veste care sună a
     * bătaie de joc. Iar `evenimentIncheiat()` se uită și la ceas, nu doar la
     * stare: un anunț aprobat cu întârziere, pentru o zi care a trecut, nu
     * trezește pe nimeni degeaba.
     */
    if ($ev === false || !evenimentPublicat($ev) || evenimentIncheiat($ev)) {
        return 0;
    }

    // Ștampila întâi. Ce n-a prins-o, nu trimite.
    if (!insemneazaUrmaritoriiInstiintati((int) $ev['id'])) {
        return 0;
    }

    $oameni = urmaritoriDeInstiintat((int) $ev['membru_id']);

    if ($oameni === []) {
        return 0;
    }

    /**
     * Cartonașul e ACELAȘI ca în newsletter, prin aceeași funcție: coperta sau
     * poza categoriei, ora, orașul și locul, un început de text. Scris a doua
     * oară aici, s-ar fi despărțit de el la prima îndreptare.
     */
    $randuri = randuriPentruNewsletter([$ev]);

    if ($randuri === []) {
        return 0;
    }

    // Numele organizatorului, prescurtat ca peste tot — și „Utilizator șters"
    // dacă rândul lui s-a golit între timp.
    $cine = esteContSters($ev['org_stare'] ?? null)
        ? NUME_CONT_STERS
        : numeAfisat((string) $ev['org_nume'], (string) $ev['org_prenume']);

    $plecate = 0;

    foreach ($oameni as $om) {
        if (emailEvenimentDeLaUrmarit(
            (string) $om['email'],
            (string) $om['prenume'],
            $cine,
            $randuri[0],
            urlIntreg(urlProfil((string) ($ev['org_permalink'] ?? '')))
        )) {
            $plecate++;
        }
    }

    return $plecate;
}

/* ============================ CUM ARATĂ ============================== */

/**
 * Butonul „Urmărește", cu cifra urmăritorilor lângă el.
 *
 * SCRIS ÎNTR-UN SINGUR LOC, fiindcă stă în două: pe profil, lângă nume, și pe
 * pagina unui eveniment, lângă organizator. Două copii s-ar fi despărțit la
 * prima schimbare, iar butonul ăsta își schimbă textul la fiecare apăsare.
 *
 * CIFRA SE VEDE ȘI CÂND E ZERO, și celui care nu e conectat: e o informație
 * despre om, ca numărul de ieșiri organizate, nu o răsplată pentru cine a
 * apăsat. Ascunsă la zero, ar fi apărut din senin la primul urmăritor și ar fi
 * părut o stricăciune.
 *
 * Cine nu e conectat vede butonul, dar el duce la intrarea în cont — nu se
 * ascunde. Un buton care lipsește nu spune nimănui că ar putea exista.
 */
function randeazaButonUrmarire(?array $eu, array $omul, ?int $cati = null): string
{
    $idOm = (int) $omul['id'];
    $cati = $cati ?? catiUrmaritori($idOm);

    // Nu se desenează pe propriul profil: n-ai pe cine urmări acolo.
    if ($eu !== null && (int) $eu['id'] === $idOm) {
        return '';
    }

    if (esteContSters($omul['stare'] ?? null)) {
        return '';
    }

    $numar = '<span class="urmarire__cati" data-urmarire-cati>' . $cati . '</span>';

    if ($eu === null) {
        return '<a class="btn btn--ghost btn--sm urmarire" '
             . 'href="/login.php?redirect='
             . h(urlencode(urlProfil((string) ($omul['permalink'] ?? '')))) . '">'
             . iconitaUrmarire()
             . '<span>Urmărește</span>' . $numar . '</a>';
    }

    $urmareste = esteUrmarit((int) $eu['id'], $idOm);

    /* Tokenul stă pe buton, nu pe o secțiune de deasupra: butonul ăsta se
       desenează în două pagini deosebite, iar a doua oară n-ar mai fi avut de
       unde să-l ia. Aceeași cale ca la „Fixează" de pe pagina evenimentului. */
    return '<button class="btn btn--sm urmarire' . ($urmareste ? ' urmarire--merge' : '')
         . '" type="button" data-urmareste="' . $idOm . '"'
         . ' data-csrf="' . h(tokenCsrf()) . '"'
         . ' aria-pressed="' . ($urmareste ? 'true' : 'false') . '">'
         . iconitaUrmarire()
         . '<span data-urmarire-text>' . ($urmareste ? 'Urmărești' : 'Urmărește') . '</span>'
         . $numar
         . '</button>';
}

/** Desenul de pe buton — un omuleț cu un plus, ca peste tot pe site. */
function iconitaUrmarire(): string
{
    return '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
         . '<circle cx="9.5" cy="8" r="3.4"/>'
         . '<path d="M3.6 19.4c0-3.1 2.6-5.2 5.9-5.2s5.9 2.1 5.9 5.2"/>'
         . '<path d="M18.5 8.5v5M21 11h-5"/>'
         . '</svg>';
}
