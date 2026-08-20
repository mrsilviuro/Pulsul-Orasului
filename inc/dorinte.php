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
 * de index.php când nu e JavaScript, și de api/dorinta.php când e) ȘI cum
 * arată pe ecran (randeazaTablaDorinte, randeazaZonaDorinte). Un singur loc
 * pentru fiecare, ca la evenimente.
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
 * Tot atunci omul poate să-și pună alta.
 */
const ZILE_PE_TABLA = 7;

/** Ce i se spune omului după ce a trimis. Scris o dată, folosit în trei locuri. */
const MESAJ_DORINTA_TRIMISA =
    'Dorința ta a ajuns la noi. O vom citi, iar dacă respectă regulamentul '
  . 'nostru, o vom publica de îndată.';

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
            AND m.stare = ?
          ORDER BY RAND()
          LIMIT ' . DORINTE_PE_TABLA
    );
    $q->execute(['aprobat', acumMinus(ZILE_PE_TABLA * 24 * 60), 'activ']);

    return $q->fetchAll();
}

/* =================== CINE MAI POATE PUNE UNA ========================= */

/**
 * Ultima dorință a unui membru, oricare i-ar fi starea.
 *
 * „Ultima", nu „cea de pe tablă": de ea atârnă dacă omul mai poate scrie una,
 * iar una care așteaptă să fie citită îl oprește la fel de bine ca una
 * publicată.
 */
function dorintaMembrului(int $membruId): ?array
{
    $q = db()->prepare(
        'SELECT id, oras, dorinta, stare_moderare, creat_la, publicat_la
           FROM dorinte
          WHERE membru_id = ?
          ORDER BY id DESC
          LIMIT 1'
    );
    $q->execute([$membruId]);

    $rand = $q->fetch();

    return $rand === false ? null : $rand;
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
 * Mai are voie omul ăsta să-și pună o dorință? Și, dacă nu, de ce.
 *
 * Trei răspunsuri, nu două, fiindcă „nu" arată altfel pe ecran după cum e:
 *   - 'poate'    — n-are niciuna în lucru
 *   - 'asteapta' — a trimis una și n-a fost citită încă
 *   - 'e_pe_tabla' — are una publicată, care încă n-a împlinit șapte zile
 *
 * O dorință RESPINSĂ nu-l oprește: cine a scris ceva nepotrivit trebuie să
 * poată încerca altfel, altminteri prima greșeală l-ar fi scos de pe tablă
 * pentru totdeauna.
 */
function poatePuneODorinta(int $membruId): array
{
    $ultima = dorintaMembrului($membruId);

    if ($ultima === null) {
        return ['stare' => 'poate', 'dorinta' => null];
    }

    $stareEi = (string) ($ultima['stare_moderare'] ?? '');

    if ($stareEi === 'in_asteptare') {
        return ['stare' => 'asteapta', 'dorinta' => $ultima];
    }

    if ($stareEi === 'aprobat') {
        $iese = dorintaIeseDePeTabla($ultima);

        if ($iese !== null && time() < $iese) {
            return ['stare' => 'e_pe_tabla', 'dorinta' => $ultima];
        }
    }

    return ['stare' => 'poate', 'dorinta' => $ultima];
}

/* ========================= SCRIEREA EI =============================== */

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
function puneODorinta(int $membruId, array $date): array
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
     * două file deschise deodată ar fi trimis amândouă. Regula „o singură
     * dorință" se ține aici, la scriere, nu în butonul de pe ecran — acela e
     * doar politețe.
     */
    $voie = poatePuneODorinta($membruId);

    if ($voie['stare'] === 'asteapta') {
        return [
            'ok'    => false,
            'mesaj' => 'Ai deja o dorință care așteaptă să fie citită. '
                     . 'Mai lasă-ne puțin.',
            'cod'   => 409,
            'erori' => [],
        ];
    }

    if ($voie['stare'] === 'e_pe_tabla') {
        $iese = dorintaIeseDePeTabla($voie['dorinta']);
        $cand = $iese === null ? '' : dataScurta(date('Y-m-d', $iese));

        return [
            'ok'    => false,
            'mesaj' => 'Dorința ta e pe tablă'
                     . ($cand === '' ? '' : ' până pe ' . $cand)
                     . '. Poți pune alta după ce iese.',
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
        $rezultat['curat']['oras'],
        $rezultat['curat']['dorinta'],
        'in_asteptare',
        acum(),
    ]);

    return [
        'ok'    => true,
        'mesaj' => MESAJ_DORINTA_TRIMISA,
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
function randeazaZonaDorinte(bool $eLogat, string $stare, ?array $dorinta = null): string
{
    if (!$eLogat) {
        return '';
    }

    if ($stare === 'asteapta') {
        return '<p class="tabla__stare">'
             . 'Dorința ta așteaptă să fie citită. Îți apare pe tablă imediat ce trece.'
             . '</p>';
    }

    if ($stare === 'e_pe_tabla') {
        $iese = $dorinta === null ? null : dorintaIeseDePeTabla($dorinta);

        /**
         * „joi, 27 august", nu „27 aug 2026": data e la câteva zile distanță,
         * iar ziua săptămânii spune mai mult decât anul, care se înțelege.
         *
         * Cu literă mică: dataLunga() o scrie cu majusculă, fiindcă de obicei
         * stă singură, la începutul unui rând („Joi, 27 august, ora 19:00").
         * Aici intră în mijlocul unei fraze — „până Joi" ar fi fost o
         * majusculă în mijlocul propoziției.
         */
        $cand = '';

        if ($iese !== null) {
            $scris = dataLunga(date('Y-m-d', $iese), false);
            $cand  = mb_strtolower(mb_substr($scris, 0, 1, 'UTF-8'), 'UTF-8')
                   . mb_substr($scris, 1, null, 'UTF-8');
        }

        return '<p class="tabla__stare">'
             . 'Dorința ta e pe tablă'
             . ($cand === '' ? '' : ' până ' . h($cand))
             . '. Poți pune alta după ce iese.'
             . '</p>';
    }

    return '';
}

/**
 * Butonul „Pune-ți o dorință", cel din fereastra de bun venit.
 *
 * Stă lângă „Propune o ieșire", fiindcă acolo se hotărăște omul ce vrea să
 * facă: ori pune la cale o ieșire, ori spune doar ce i-ar plăcea. Sub tablă nu
 * mai e nimic de apăsat — acolo rămân doar vorbele despre dorința lui, dacă
 * are una (randeazaZonaDorinte).
 *
 * Întoarce '' pentru cine are deja o dorință în lucru: un buton care duce la
 * un formular pe care serverul îl refuză oricum n-are ce căuta pe ecran.
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
        : 'login.php?redirect=' . h(urlencode('/index.php#dorinta-formular'));

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
