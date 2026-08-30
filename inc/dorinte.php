<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — tabla cu dorințe.
 *
 * Ca să pui la cale un eveniment trebuie să-ți iei o răspundere: alegi ziua,
 * locul, ora, și pe urmă răspunzi celor care vin. Nu toată lumea vrea asta.
 * Dar mulți ar veni la ceva, dacă s-ar face.
 *
 * Tabla cu dorințe e treapta de dinaintea aceleia: un rând scris de om, „mi-ar
 * plăcea să se facă asta", pe care îl citește tocmai cine caută o idee. N-are
 * dată, n-are loc, n-are listă de participanți — de aceea nu e un eveniment.
 *
 * AICI STAU TOATE: ce se vede pe tablă (dorinteDePeTabla), cine mai are voie
 * să-și pună una (poatePuneODorinta), scrierea ei (puneODorinta — chemată și
 * de index.php când nu e JavaScript, și de api/dorinta.php când e), ștergerea
 * ei de către autor (stergeDorintaOmului) ȘI cum arată pe ecran
 * (randeazaTablaDorinte, randeazaZonaDorinte, randeazaDorinteleMele). Un
 * singur loc pentru fiecare, ca la evenimente.
 *
 * TREI DEODATĂ, ȘI SE POT ȘTERGE. A fost una singură, și pentru totdeauna:
 * odată publicată, stătea șapte zile și atât. Amândouă erau prea strâmte —
 * vezi DORINTE_DEODATA și antetul lui sql/032.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validare.php';

/**
 * Câte dorințe încap pe tablă deodată.
 *
 * Zece, nu toate: tabla e un slide care se schimbă singur, iar peste zece
 * nimeni n-ar apuca să le vadă pe ultimele. Se aleg LA ÎNTÂMPLARE la fiecare
 * încărcare a primei pagini, tocmai ca să nu fie mereu aceleași zece — altfel
 * a unsprezecea n-ar fi fost citită niciodată.
 */
const DORINTE_PE_TABLA = 10;

/**
 * Câte zile stă o dorință pe tablă, de la aprobare.
 *
 * După ele nu se șterge: iese de pe tablă, dar rândul rămâne în bază, ca mai
 * târziu să putem spune câte dorințe și-au pus oamenii de-a lungul timpului.
 * Tot atunci se eliberează locul ei dintre cele trei.
 */
const ZILE_PE_TABLA = 7;

/**
 * Câte dorințe poate avea un om ÎN LUCRU deodată.
 *
 * A fost una singură, și era prea strâmt: cine se gândește la trei lucruri
 * deosebite trebuia să aleagă unul și să aștepte o săptămână pentru al doilea.
 * Trei e câte încap fără ca tabla să devină a unui singur om — la zece locuri
 * pe tablă, trei de-ale aceluiași ar fi deja o treime.
 *
 * „ÎN LUCRU" înseamnă: cele care așteaptă să fie citite ȘI cele publicate care
 * n-au împlinit încă șapte zile. Una ieșită de pe tablă, una respinsă și una
 * ștearsă de el nu ocupă niciun loc — vezi dorinteleInLucru().
 */
const DORINTE_DEODATA = 3;

/** Ce i se spune omului după ce a trimis. Scris o dată, folosit în trei locuri. */
const MESAJ_DORINTA_TRIMISA =
    'Dorința ta a ajuns la noi. O citim și, dacă e în regulă, o punem pe '
  . 'tablă.';

/**
 * Ce i se spune omului de casă, a cărui dorință nu trece pe la nimeni.
 *
 * „O vom citi" ar fi fost o vorbă goală spusă chiar celui care citește: el E
 * moderarea. Iar dacă i s-ar fi spus totuși asta, s-ar fi dus pe urmă în admin
 * să caute ce n-avea ce căuta acolo.
 */
const MESAJ_DORINTA_PUBLICATA =
    'Dorința ta e pe tablă. Apare pe prima pagină de la următoarea încărcare.';

/* ===================== CE SE VEDE PE TABLĂ ============================ */

/**
 * Pune ștampila de publicare pe dorințele tocmai aprobate.
 *
 * Cele șapte zile se numără de la `publicat_la`, nu de la trimitere: dacă o
 * dorință stă trei zile până e citită, omul n-are de ce să fie pedepsit că am
 * întârziat noi.
 *
 * DE CE AICI, ȘI NU LA APROBARE. Fiindcă aprobarea se face deocamdată de mână,
 * din phpMyAdmin — nu există (încă) pagină de moderare. Așa, tot ce are de
 * făcut omul de casă e să schimbe `stare_moderare` în „aprobat"; ștampila și-o
 * pune singură, la prima încărcare a primei pagini, cu ceasul PHP. Fără asta,
 * ștampila ar fi fost pusă de mână cu NOW()-ul lui MySQL, adică din alt fus —
 * exact bug-ul de care ne ferim peste tot (vezi CLAUDE.md, regula ceasului).
 *
 * Scrie cel mult o dată pentru fiecare dorință: a doua oară `publicat_la` nu
 * mai e NULL, iar UPDATE-ul nu mai are ce prinde.
 */
function stampileazaCeleAprobate(): void
{
    $u = db()->prepare(
        'UPDATE dorinte
            SET publicat_la = ?
          WHERE stare_moderare = ? AND publicat_la IS NULL'
    );
    $u->execute([acum(), 'aprobat']);
}

/**
 * Dorințele de pe tablă: cel mult zece, la întâmplare, din ultimele șapte zile.
 *
 * `ORDER BY RAND()` e de obicei o greșeală — pune baza să amestece tot tabelul
 * ca să ia zece rânduri. Aici nu: se amestecă doar dorințele APROBATE din
 * ultimele șapte zile, adică zeci, nu zeci de mii. Ziua în care vor fi zeci de
 * mii, întrebarea se schimbă; până atunci, o listă „ultimele zece" ar fi
 * însemnat că a unsprezecea nu e citită niciodată.
 *
 * `m.stare = 'activ'` scoate de pe tablă dorințele celor plecați: contul șters
 * se anonimizează, nu se șterge (vezi inc/stergere.php), iar dorința lui n-are
 * de ce să rămână la vedere sub un nume care nu mai e al nimănui.
 *
 * `d.sters_la IS NULL` scoate ce și-a luat omul înapoi. Rândul rămâne în bază
 * pentru numărătoarea de mai târziu (vezi sql/032), dar de pe tablă dispare în
 * clipa apăsării — altfel ștergerea n-ar fi însemnat nimic.
 */
function dorinteDePeTabla(): array
{
    stampileazaCeleAprobate();

    $q = db()->prepare(
        'SELECT d.id, d.oras, d.dorinta, d.publicat_la,
                m.nume, m.prenume
           FROM dorinte d
           JOIN membri m ON m.id = d.membru_id
          WHERE d.stare_moderare = ?
            AND d.publicat_la IS NOT NULL
            AND d.publicat_la > ?
            AND d.sters_la IS NULL
            AND m.stare = ?
          ORDER BY RAND()
          LIMIT ' . DORINTE_PE_TABLA
    );
    $q->execute(['aprobat', acumMinus(ZILE_PE_TABLA * 24 * 60), 'activ']);

    return $q->fetchAll();
}

/* =================== CINE MAI POATE PUNE UNA ========================= */

/**
 * Dorințele ÎN LUCRU ale unui membru, cea mai nouă întâi.
 *
 * „În lucru" înseamnă că ocupă unul din cele trei locuri (DORINTE_DEODATA):
 *
 *   - cele care AȘTEAPTĂ să fie citite — una netrecută pe la nimeni oprește
 *     un loc la fel de bine ca una publicată, altfel omul ar fi putut trimite
 *     zece deodată și le-ar fi găsit moderarea pe toate;
 *   - cele APROBATE care n-au împlinit încă șapte zile.
 *
 * Nu intră: cele RESPINSE (cine a scris ceva nepotrivit trebuie să poată
 * încerca altfel — prima greșeală nu-l scoate de pe tablă pentru totdeauna),
 * cele ieșite de pe tablă, și cele pe care omul le-a ȘTERS.
 *
 * `publicat_la IS NULL` la una aprobată înseamnă că ștampila n-a apucat încă
 * să se pună (stampileazaCeleAprobate rulează la prima încărcare a primei
 * pagini). Aceea tocmai urcă pe tablă, deci ocupă un loc. Fără rândul ăsta,
 * întrebarea pusă dintr-un API — care nu trece pe la tablă — ar fi socotit-o
 * inexistentă, și omul ar fi putut scrie una peste numărul îngăduit.
 *
 * Cea mai nouă întâi: în tabelul de sub tablă, ultima scrisă e cea la care se
 * gândește omul când îl deschide.
 */
function dorinteleInLucru(int $membruId): array
{
    $q = db()->prepare(
        'SELECT id, oras, dorinta, stare_moderare, creat_la, publicat_la
           FROM dorinte
          WHERE membru_id = ?
            AND sters_la IS NULL
            AND ( stare_moderare = \'in_asteptare\'
               OR ( stare_moderare = \'aprobat\'
                    AND (publicat_la IS NULL OR publicat_la > ?) ) )
          ORDER BY id DESC'
    );
    $q->execute([$membruId, acumMinus(ZILE_PE_TABLA * 24 * 60)]);

    return $q->fetchAll();
}

/**
 * Când iese de pe tablă o dorință publicată. NULL dacă nu e (încă) publicată.
 */
function dorintaIeseDePeTabla(array $dorinta): ?int
{
    $publicat = (string) ($dorinta['publicat_la'] ?? '');

    if ($publicat === '') {
        return null;
    }

    $moment = strtotime($publicat);

    return $moment === false ? null : $moment + ZILE_PE_TABLA * 24 * 3600;
}

/**
 * Mai are voie omul ăsta să-și pună o dorință?
 *
 * Întoarce ['stare', 'dorinte', 'cate']:
 *   - 'poate'       — are mai puțin de DORINTE_DEODATA în lucru
 *   - 'prea_multe'  — le are pe toate trei. Poate șterge una, ca să facă loc.
 *
 * DOUĂ STĂRI, NU TREI. Erau 'asteapta' și 'e_pe_tabla', fiindcă atunci omul
 * avea o singură dorință și tot ce se putea spune despre el era povestea
 * aceleia. Acum are până la trei, cu stări deosebite între ele — nu mai există
 * „starea omului", ci starea fiecărei dorințe în parte, iar aceea se scrie în
 * tabelul de sub tablă (randeazaDorinteleMele). Aici a rămas o singură
 * întrebare: mai încape una?
 *
 * `dorinte` vin cu răspunsul, gata citite, ca pagina să nu le mai ceară o dată.
 */
function poatePuneODorinta(int $membruId): array
{
    $inLucru = dorinteleInLucru($membruId);
    $cate    = count($inLucru);

    return [
        'stare'   => $cate < DORINTE_DEODATA ? 'poate' : 'prea_multe',
        'dorinte' => $inLucru,
        'cate'    => $cate,
    ];
}

/* ========================= ȘTERGEREA EI ============================== */

/**
 * Omul își ia dorința înapoi. Întoarce true dacă chiar a fost a lui și în viață.
 *
 * ȘTERGERE MOALE: se scrie `sters_la`, rândul rămâne. Motivul e cel din
 * antetul lui sql/023 și e mai vechi decât funcția asta — rândurile din
 * `dorinte` se păstrează ca mai târziu să se poată spune câte dorințe și-au
 * pus oamenii de-a lungul timpului. O ștergere adevărată ar fi luat din
 * numărătoarea aceea tocmai dorințele la care cineva chiar s-a gândit.
 *
 * Din clipa asta dorința dispare de pe tablă, iese din tabelul lui și face loc
 * alteia dintre cele trei — adică tot ce înseamnă „ștearsă" pentru cel care
 * apasă.
 *
 * TOTUL SE HOTĂRĂȘTE ÎN `WHERE`, nu într-un SELECT de dinainte:
 *
 *   - `membru_id = ?` — a lui, nu a altuia. Cererea poate veni de oriunde, cu
 *     orice id în ea; „nu există" și „nu e a ta" primesc același răspuns, ca
 *     peste tot pe site, altfel numerele încercate pe rând ar spune cine ce a
 *     scris.
 *   - `sters_la IS NULL` — o dorință ștearsă nu se șterge a doua oară. Două
 *     apăsări în aceeași clipă, sau o filă lăsată deschisă, nu mută ștampila.
 *
 * NU se confundă cu ștergerea staff-ului din admin-dorinte.php: aceea e un
 * DELETE adevărat și rămâne așa, fiindcă e pentru ce n-are ce căuta în
 * numărătoare — o înjurătură, un test. Omul care se răzgândește n-a greșit cu
 * nimic, iar dorința lui a fost o dorință adevărată.
 */
function stergeDorintaOmului(int $membruId, int $dorintaId): bool
{
    if ($membruId <= 0 || $dorintaId <= 0) {
        return false;
    }

    $q = db()->prepare(
        'UPDATE dorinte SET sters_la = ?
          WHERE id = ? AND membru_id = ? AND sters_la IS NULL'
    );
    $q->execute([acum(), $dorintaId, $membruId]);

    return $q->rowCount() === 1;
}

/* ========================= SCRIEREA EI =============================== */

/**
 * Cu ce stare intră o dorință nouă.
 *
 * OMUL DE CASĂ PUBLICĂ DE-A DREPTUL. El e cel pe la care ar fi trecut dorința:
 * a o pune „în așteptare" ar fi însemnat să se aprobe singur, dintr-o pagină
 * în alta, la fiecare rând scris. Aceeași socoteală ca la evenimente, unde
 * starePentruPublicare() face exact asta (vezi inc/evenimente.php).
 *
 * `publicat_la` NU se pune aici, dinadins: îl scrie stampileazaCeleAprobate()
 * la prima încărcare a primei pagini, tot cu ceasul PHP. Așa, o dorință
 * aprobată din admin, una schimbată de mână din phpMyAdmin și una scrisă de
 * omul de casă se poartă toate la fel — iar cele șapte zile de tablă se numără
 * dintr-un singur loc.
 *
 * ȘI NU PLEACĂ NICIUN E-MAIL. Vestea despre hotărârea moderării o trimite
 * api/admin.php, când chiar hotărăște cineva ceva; aici n-a hotărât nimeni
 * nimic, iar omul tocmai a apăsat butonul — știe deja.
 */
function stareaDorinteiNoi(bool $eStaff): string
{
    return $eStaff ? 'aprobat' : 'in_asteptare';
}

/**
 * Scrie o dorință nouă.
 *
 * Chemată din DOUĂ locuri: api/dorinta.php (cu JavaScript) și index.php (fără
 * el, când formularul se trimite ca orice formular). Scrisă în amândouă, s-ar
 * fi despărțit la prima schimbare — aceeași înțelegere ca la inscrieLaVesti()
 * din inc/constructie.php.
 *
 * Întoarce ['ok', 'mesaj', 'cod', 'erori'], unde `erori` sunt pe câmpuri, ca
 * formularul să le poată pune fiecare sub căsuța ei.
 */
function puneODorinta(int $membruId, array $date, bool $eStaff = false): array
{
    $rezultat = verificaDorinta($date, oraseDisponibile());

    if ($rezultat['erori'] !== []) {
        return [
            'ok'    => false,
            'mesaj' => 'Uită-te încă o dată peste ce ai scris.',
            'cod'   => 422,
            'erori' => $rezultat['erori'],
        ];
    }

    /**
     * Verificarea se face ACUM, nu când s-a desenat pagina.
     *
     * Între desenarea formularului și apăsarea butonului pot trece minute, iar
     * două file deschise deodată ar fi trimis amândouă. Regula celor trei se
     * ține aici, la scriere, nu în butonul de pe ecran — acela e doar politețe.
     *
     * ÎNTREBAREA ȘI SCRIEREA, ÎNTR-O SINGURĂ MIȘCARE.
     *
     * „Politețea" de mai sus n-a fost totuși de ajuns. Între „mai încape una?"
     * și „scrie-o pe asta" încape o clipă, iar în clipa aia încape a doua
     * cerere: două file trimise în aceeași secundă întrebau amândouă înainte ca
     * vreuna să apuce să scrie, amândouă auzeau „mai încape", și amândouă
     * scriau. Omul trecea peste numărul îngăduit, iar moderarea se trezea cu un
     * rând în plus de hotărât. Se vede mai greu de când locurile sunt trei, dar
     * a treia și a patra se calcă exact ca prima și a doua.
     *
     * SE ÎNCUIE RÂNDUL OMULUI din `membri`: regula e a lui, deci acolo e locul
     * firesc de făcut rândul la coadă. Al doilea venit așteaptă la ușă și abia
     * apoi întreabă — găsind, de data asta, dorința pe care tocmai a scris-o
     * primul. Doi oameni deosebiți nu se așteaptă unul pe altul.
     *
     * Aceeași croială ca la ultimul loc de la un eveniment (api/interes.php).
     */
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->prepare('SELECT id FROM membri WHERE id = ? FOR UPDATE')->execute([$membruId]);

        $raspuns = scrieDorintaSubLacat($membruId, $rezultat['curat'], $eStaff);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }

    return $raspuns;
}

/**
 * Partea din puneODorinta() care se petrece cu rândul omului încuiat:
 * întrebarea „mai poate?" și scrierea.
 *
 * E o funcție separată doar ca tranzacția de deasupra să aibă un singur drum
 * de ieșire. Scrisă la locul ei, fiecare `return` de refuz ar fi trebuit să-și
 * amintească să și încheie tranzacția — iar unul dintre ele n-ar fi făcut-o.
 *
 * NU se cheamă de nicăieri altundeva: fără lacătul de deasupra, e chiar
 * socoteala pe care o repară el.
 */
function scrieDorintaSubLacat(int $membruId, array $curat, bool $eStaff = false): array
{
    $voie = poatePuneODorinta($membruId);

    if ($voie['stare'] === 'prea_multe') {
        return [
            'ok'    => false,
            'mesaj' => 'Ai deja ' . DORINTE_DEODATA . ' dorințe în lucru — atâtea '
                     . 'se pot deodată. Șterge una din „Dorințele mele", de sub '
                     . 'tablă, ca să faci loc alteia.',
            'cod'   => 409,
            'erori' => [],
        ];
    }

    $i = db()->prepare(
        'INSERT INTO dorinte (membru_id, oras, dorinta, stare_moderare, creat_la)
              VALUES (?, ?, ?, ?, ?)'
    );
    $i->execute([
        $membruId,
        $curat['oras'],
        $curat['dorinta'],
        stareaDorinteiNoi($eStaff),
        acum(),
    ]);

    return [
        'ok'    => true,
        'mesaj' => $eStaff ? MESAJ_DORINTA_PUBLICATA : MESAJ_DORINTA_TRIMISA,
        'cod'   => 200,
        'erori' => [],
    ];
}

/* ========================= CUM ARATĂ ================================= */

/**
 * Tabla întreagă: cartonașele, unul peste altul, și punctele de sub ele.
 *
 * Primul are `is-activa`, celelalte nu. Fără JavaScript se vede exact acela și
 * atât — o dorință în loc de zece, dar niciun ecran gol. Cu JavaScript, ele se
 * schimbă între ele (vezi „TABLA CU DORINȚE" din main.js).
 *
 * Întoarce '' dacă n-are ce arăta, iar prima pagină nu desenează atunci nicio
 * tablă. O tablă goală cu „încă nimeni n-a scris nimic" ar fi fost un anunț de
 * pustiu tocmai în capul paginii.
 */
function randeazaTablaDorinte(array $dorinte): string
{
    if ($dorinte === []) {
        return '';
    }

    $cartonase = '';
    $puncte    = '';
    $nr        = 0;

    foreach ($dorinte as $d) {
        $activa = $nr === 0;
        $cine   = numeAfisat((string) $d['nume'], (string) $d['prenume']);

        $cartonase .= '<li class="dorinta' . ($activa ? ' is-activa' : '') . '"'
                    . ' data-dorinta'
                    . ' aria-hidden="' . ($activa ? 'false' : 'true') . '">'
                    . '<p class="dorinta__vorba">'
                    . '<span class="dorinta__cine">' . h($cine) . '</span>'
                    . ' și-ar dori: '
                    . '<span class="dorinta__text">' . h((string) $d['dorinta']) . '</span>'
                    . '</p>'
                    . '<p class="dorinta__unde">'
                    . '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
                    . '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/>'
                    . '<circle cx="12" cy="10" r="3"/></svg>'
                    . h((string) $d['oras'])
                    . '</p>'
                    . '</li>';

        $puncte .= '<button class="tabla__punct' . ($activa ? ' is-activ' : '') . '"'
                 . ' type="button" data-dorinta-punct="' . $nr . '"'
                 . ' aria-label="Dorința ' . ($nr + 1) . ' din ' . count($dorinte) . '"'
                 . ' aria-current="' . ($activa ? 'true' : 'false') . '"></button>';

        $nr++;
    }

    // Punctele au rost doar de la a doua dorință încolo.
    $randPuncte = count($dorinte) > 1
        ? '<div class="tabla__puncte" role="tablist" aria-label="Alege dorința">' . $puncte . '</div>'
        : '';

    /**
     * Eticheta de deasupra. Fără ea, primul lucru de sub prima fereastră ar
     * fi fost o frază a unui necunoscut, fără nimic care să spună ce e și de
     * ce stă acolo. Aceeași `.eyebrow` ca peste tot pe site.
     */
    $eticheta = '<p class="eyebrow tabla__eticheta">' . stelutaDorinta()
              . '<span>Tabla cu dorințe</span></p>';

    return '<div class="tabla__slide" data-tabla>'
         . $eticheta
         . '<ul class="tabla__lista">' . $cartonase . '</ul>'
         . $randPuncte
         . '</div>';
}

/**
 * Rândul de sub tablă: butonul „Pune-ți o dorință", sau ce-i ține locul.
 *
 * Se desenează din același loc și sub tablă, și în capul listei de evenimente
 * (când nu e nicio dorință de arătat și tabla nu se desenează deloc). Scris în
 * două locuri, al doilea ar fi rămas cu butonul vechi la prima schimbare.
 *
 * `$stare` vine din poatePuneODorinta(); pentru cine nu e conectat se dă ''.
 * Butonul e o LEGĂTURĂ, nu un buton de JS: fără JavaScript, adresa `#`
 * deschide formularul prin `:target` (vezi „TABLA CU DORINȚE" din style.css),
 * iar cine nu e conectat ajunge la pagina de intrare, cu drumul de întoarcere
 * în adresă.
 */
function randeazaZonaDorinte(bool $eLogat, string $stare, array $dorinte = []): string
{
    if (!$eLogat || $dorinte === []) {
        return '';
    }

    $cate = count($dorinte);

    /**
     * Câte mai încap. Se scrie ca vorbă, nu ca cifră scăzută pe loc: „mai poți
     * pune două" e ce vrea omul să afle, iar „ai 1 din 3" e un formular.
     */
    $libere = max(0, DORINTE_DEODATA - $cate);

    $are = $cate === 1
        ? 'Ai o dorință în lucru'
        : 'Ai ' . $cate . ' dorințe în lucru';

    if ($stare === 'prea_multe') {
        $urmarea = ' — atâtea se pot deodată. Șterge una ca să faci loc alteia.';
    } elseif ($libere === 1) {
        $urmarea = '. Mai poți pune una.';
    } else {
        $urmarea = '. Mai poți pune ' . $libere . '.';
    }

    return '<p class="tabla__stare">' . h($are . $urmarea) . '</p>'
         . randeazaDorinteleMele($dorinte);
}

/**
 * „Dorințele mele": butonul de sub tablă și tabelul care se deschide sub el.
 *
 * E un `<details>`, nu un buton de JS — și e cea mai bună parte a alegerii:
 * `<details>` se deschide și se închide singur, în orice browser, FĂRĂ o linie
 * de JavaScript. Restul tablei se sprijină pe `:target` ca să meargă cu JS-ul
 * stins; aici n-a fost nevoie nici măcar de atât.
 *
 * FIECARE RÂND E UN FORMULAR ADEVĂRAT, cu `method="post"` spre /index.php.
 * Fără JavaScript, „×"-ul șterge dorința și reîncarcă pagina; cu JavaScript,
 * main.js îi ia locul, cheamă api/sterge-dorinta.php și scoate rândul pe loc.
 * Un buton care ar fi ascultat doar de JS n-ar fi făcut nimic pentru cine nu-l
 * are — iar aici e vorba de singurul fel în care omul își poate lua vorbele
 * înapoi.
 *
 * FĂRĂ CONFIRMARE, dinadins. O dorință e un rând de o sută de caractere, iar
 * omul care apasă „×" în dreptul propriei fraze știe foarte bine ce face. O
 * fereastră „ești sigur?" la fiecare apăsare e o piedică pusă tuturor pentru
 * greșeala unuia. Ce se pierde e o frază pe care o poate scrie din nou.
 */
function randeazaDorinteleMele(array $dorinte): string
{
    if ($dorinte === []) {
        return '';
    }

    $randuri = '';

    foreach ($dorinte as $d) {
        $id = (int) ($d['id'] ?? 0);

        /**
         * Ce se întâmplă cu ea acum: ori așteaptă să fie citită, ori e pe
         * tablă până într-o zi anume. Starea se scrie pentru FIECARE dorință
         * în parte — de când sunt trei, nu mai există „starea omului".
         */
        if ((string) ($d['stare_moderare'] ?? '') === 'in_asteptare') {
            $stareaEi = 'Așteaptă să fie citită';
        } else {
            $iese     = dorintaIeseDePeTabla($d);
            $stareaEi = $iese === null
                ? 'Pe tablă'
                : 'Pe tablă până ' . dataScrisaMic(date('Y-m-d', $iese));
        }

        $randuri .= '<li class="dorintele__rand">'
                  . '<div class="dorintele__ce">'
                  . '<p class="dorintele__text">' . h((string) ($d['dorinta'] ?? '')) . '</p>'
                  . '<p class="dorintele__amanunt">'
                  . h((string) ($d['oras'] ?? '')) . ' · ' . h($stareaEi)
                  . '</p></div>'

                  /* Formularul e al rândului, ca să meargă și fără JavaScript. */
                  . '<form class="dorintele__sterg" method="post"'
                  . ' action="/index.php#dorintele-mele" data-sterge-dorinta="' . $id . '">'
                  . '<input type="hidden" name="csrf" value="' . h(tokenCsrf()) . '">'
                  . '<input type="hidden" name="sterge_dorinta" value="' . $id . '">'
                  . '<button class="dorintele__x" type="submit"'
                  . ' aria-label="Șterge dorința „'
                  . h(inceputDeText((string) ($d['dorinta'] ?? ''), 40)) . '"'
                  . ' title="Șterge dorința">'
                  . '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
                  . '<path d="M18 6 6 18M6 6l12 12"/></svg>'
                  . '</button></form>'
                  . '</li>';
    }

    $cate = count($dorinte);

    return '<details class="dorintele" id="dorintele-mele">'
         . '<summary class="dorintele__buton">'
         . '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
         . '<path d="M4 7h16M4 12h16M4 17h10"/></svg>'
         . '<span>Dorințele mele (' . $cate . ')</span>'
         . '</summary>'
         . '<ul class="dorintele__lista">' . $randuri . '</ul>'
         . '</details>';
}

/**
 * Butonul „Pune-ți o dorință", cel din fereastra de bun venit.
 *
 * Stă lângă „Propune o ieșire", fiindcă acolo se hotărăște omul ce vrea să
 * facă: ori pune la cale o ieșire, ori spune doar ce i-ar plăcea. Sub tablă
 * rămân vorbele despre dorințele lui și „Dorințele mele", de unde le poate
 * șterge (randeazaZonaDorinte).
 *
 * Întoarce '' DOAR pentru cine le are deja pe toate trei: un buton care duce
 * la un formular pe care serverul îl refuză oricum n-are ce căuta pe ecran.
 * Cu una sau două în lucru, butonul rămâne — tocmai asta s-a schimbat.
 *
 * E o LEGĂTURĂ, nu un buton de JS: fără JavaScript, `#dorinta-formular`
 * deschide formularul prin `:target` (vezi „TABLA CU DORINȚE" din style.css),
 * iar cine nu e conectat ajunge la pagina de intrare, cu drumul de întoarcere
 * în adresă.
 */
function butonulDorintei(bool $eLogat, string $stare): string
{
    if ($eLogat && $stare !== 'poate') {
        return '';
    }

    $unde = $eLogat
        ? '#dorinta-formular'
        : '/login.php?redirect=' . h(urlencode('/index.php#dorinta-formular'));

    return '<a class="btn btn--ghost hero__cta hero__cta--dorinta" href="' . $unde . '">'
         . stelutaDorinta() . '<span>Pune-ți o dorință</span></a>';
}

/** Semnul de pe butonul dorinței. Scris o dată, pus în amândouă locurile. */
function stelutaDorinta(): string
{
    return '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
         . '<path d="M12 3.5 13.9 9l5.6.2-4.4 3.5 1.6 5.4L12 15l-4.7 3.1 1.6-5.4L4.5 9.2 10.1 9Z"/>'
         . '</svg>';
}
