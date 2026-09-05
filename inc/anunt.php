<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — ANUNȚUL SCRIS DE MÂNĂ, către toată lista.
 *
 * Omul de casă scrie un titlu și un text în admin-anunt.php, iar mesajul pleacă
 * la toți cei care au bifa de newsletter — staff inclus, fiindcă și ei sunt
 * membri, iar cine a bifat vrea să afle. Șablonul e același ca la toate
 * celelalte mesaje de pe site; ce se schimbă e doar ce scrie înăuntru.
 *
 * DE CE EXISTĂ. Site-ul avea douăzeci de feluri de e-mail și niciunul nu putea
 * spune un lucru simplu — „sâmbătă ținem o întâlnire", „am schimbat regulile de
 * publicare", „mulțumim, am trecut de o sută de membri". Ca să ajungă la oameni,
 * o astfel de veste trebuia scrisă ca un eveniment care nu are loc.
 *
 * AL DOILEA MESAJ NECHEMAT DE PE SITE, după newsletterul zilnic — și de aceea
 * poartă aceeași ieșire: link de dezabonare în subsol și antetul
 * „List-Unsubscribe". Bifa e una singură (`membri.newsletter`), deci și butonul
 * care o stinge e unul singur: cine iese de aici iese de la amândouă.
 *
 * NU SE ȚINE MINTE NIMIC ÎN BAZĂ. Nu e nici o coloană nouă, nici un tabel: un
 * anunț n-are „a plecat deja azi?" de întrebat, fiindcă nu-l pornește un cron
 * din oră în oră, ci un om care apasă un buton. Ce ține locul ștampilei e
 * jetonul de o singură folosință din admin-anunt.php: pagina de confirmare îl
 * poartă, trimiterea îl consumă, iar un „reîncarcă" pe deasupra nu mai găsește
 * nimic. Rămâne un rând în private/anunturi.log — cine, ce și către câți.
 */

require_once __DIR__ . '/newsletter.php';   // catiAbonati(), linkDezabonare()
require_once __DIR__ . '/email.php';

/**
 * Câți oameni se servesc la o apăsare.
 *
 * ALTĂ SOCOTEALĂ DECÂT LA NEWSLETTER. Acolo, NEWSLETTER_PE_RULARE e o frână
 * pentru un cron care poate reveni mâine, iar cine n-a încăput azi primește
 * mâine. Aici nu revine nimeni: dacă tăietura ar cădea la jumătatea listei,
 * jumătate din oameni n-ar afla niciodată, și nimeni n-ar băga de seamă.
 *
 * De aceea cifra nu taie în tăcere. Pagina o compară cu câți sunt ÎNAINTE de
 * trimitere și o scrie pe față (vezi admin-anunt.php): dacă lista a crescut
 * peste ea, omul de casă află înainte să apese, nu după.
 *
 * Trei sute e cam cât duce o cerere de PHP într-un minut de mail() și cam cât
 * plafonul de mesaje pe oră al unei găzduiri obișnuite. La numărul de acum al
 * membrilor nici nu se atinge; cifra e aici pentru ziua în care va fi altfel.
 */
const ANUNT_PE_TRIMITERE = 300;

/**
 * Cine primește anunțul.
 *
 * ACELEAȘI TREI CONDIȚII ca la newsletterul zilnic (vezi
 * abonatiiNewsletterului) — bifa pornită, contul activ, o adresă la care se
 * poate scrie — fără a patra, cea cu ștampila zilei: aceea răspunde la „i-a
 * plecat deja azi?", iar un anunț nu se trimite o dată pe zi, se trimite când
 * are cineva ceva de spus.
 *
 * STAFF-UL INTRĂ ȘI EL, dacă are bifa. Nu e o scăpare: un mesaj despre care nu
 * știi cum arată când ajunge e un mesaj pe care nu-l poți îndrepta, iar omul de
 * casă e primul care trebuie să-l vadă așa cum îl văd ceilalți.
 *
 * Cei mai vechi întâi, ca la newsletter — la o listă tăiată de
 * ANUNT_PE_TRIMITERE, ordinea trebuie măcar să fie una care se poate spune.
 */
function destinatariiAnuntului(int $celMult = ANUNT_PE_TRIMITERE): array
{
    $q = db()->prepare(
        'SELECT id, prenume, email
           FROM membri
          WHERE newsletter = 1
            AND stare = \'activ\'
            AND email <> \'\'
          ORDER BY id ASC
          LIMIT ' . max(1, $celMult)
    );
    $q->execute();

    return $q->fetchAll();
}

/**
 * Câți au bifa, cu totul — cifra scrisă pe pagină înainte de apăsare.
 *
 * E chiar catiAbonati() din inc/newsletter.php: aceeași întrebare, deci același
 * răspuns. Scrisă a doua oară, ar fi ajuns într-o zi să numere altceva decât
 * numără destinatariiAnuntului(), iar pagina ar fi spus „pleacă la 40" trimițând
 * la 38.
 */
function catiPrimescAnuntul(): int
{
    return catiAbonati();
}

/**
 * Textul omului, tăiat în paragrafe pentru șablonul de e-mail.
 *
 * Blocul „paragrafe" primește un tablou de fraze și scrie câte un <p> din
 * fiecare. Despărțitorul e rândul gol, ca peste tot unde se scrie text lung:
 * curataTextPeRanduri() a strâns deja șirurile de rânduri goale la unul singur.
 *
 * Un Enter simplu, în schimb, rămâne ÎN paragraf — el desparte rândurile unei
 * enumerări, nu paragrafele, iar tăiat acolo ar fi făcut din trei rânduri de
 * listă trei paragrafe cu spațiu între ele.
 */
function paragrafeleAnuntului(string $mesaj): array
{
    $bucati = preg_split('/\n[ \t]*\n/u', $mesaj) ?: [];

    $paragrafe = [];

    foreach ($bucati as $bucata) {
        $bucata = trim($bucata);

        if ($bucata !== '') {
            $paragrafe[] = $bucata;
        }
    }

    return $paragrafe;
}

/**
 * Trimite anunțul. Întoarce ce s-a întâmplat, pe cifre.
 *
 * FĂRĂ NICIO ȘTAMPILĂ ÎN BAZĂ, spre deosebire de newsletter: acolo, ștampila
 * pusă înainte de trimitere apără de un cron pornit de două ori. Aici nu e
 * niciun cron — e un om și un buton — iar apărarea de a doua apăsare e jetonul
 * din admin-anunt.php, consumat înainte să se ajungă aici.
 *
 * `$doarCatre` trimite mesajul la o singură adresă, fără să atingă lista:
 * proba pe care omul de casă și-o trimite lui însuși înainte de a-l scoate în
 * lume. E același mesaj, prin aceeași funcție — o probă care ar merge pe alt
 * drum n-ar dovedi nimic despre drumul adevărat.
 */
function trimiteAnuntul(string $titlu, string $mesaj, ?array $doarCatre = null): array
{
    $paragrafe = paragrafeleAnuntului($mesaj);

    $oameni = $doarCatre !== null ? [$doarCatre] : destinatariiAnuntului();

    $rezultat = ['catre' => count($oameni), 'trimise' => 0, 'picate' => 0];

    foreach ($oameni as $om) {
        $plecat = emailAnuntCatreMembri(
            (string) $om['email'],
            (string) $om['prenume'],
            $titlu,
            $paragrafe,
            linkDezabonare((int) $om['id'])
        );

        $plecat ? $rezultat['trimise']++ : $rezultat['picate']++;
    }

    return $rezultat;
}

/* ===================== JETONUL DE O SINGURĂ FOLOSINȚĂ ================= */

/**
 * Câte jetoane se țin vii deodată.
 *
 * DE CE MAI MULT DE UNUL. Cu un singur jeton în sesiune, două file deschise
 * deodată se calcă: a doua previzualizare l-ar fi scris peste al primei, iar
 * fila dintâi ar fi primit, la apăsare, „anunțul ăsta a plecat deja" despre unul
 * care nu plecase niciodată. Cu o mână de jetoane, fiecare filă îl are pe al ei.
 *
 * Nu e o listă care crește: la al șaselea, primul cade. Cinci file deschise cu
 * cinci anunțuri nescrise e deja mai mult decât se întâmplă vreodată.
 */
const ANUNT_JETOANE = 5;

/** Un jeton nou, ținut minte în sesiune. */
function jetonNouDeAnunt(): string
{
    $jeton = bin2hex(random_bytes(16));
    $vii   = $_SESSION['jetoane_anunt'] ?? [];
    $vii[] = $jeton;

    $_SESSION['jetoane_anunt'] = array_slice($vii, -ANUNT_JETOANE);

    return $jeton;
}

/**
 * Mai e viu jetonul ăsta?
 *
 * hash_equals, nu „===": aceeași regulă ca la permisul de șantier și la
 * semnătura de dezabonare. O comparație obișnuită se oprește la prima literă
 * deosebită, iar din cât durează se poate ghici, literă cu literă.
 */
function jetonDeAnuntValid(string $jeton): bool
{
    if ($jeton === '') {
        return false;
    }

    foreach (($_SESSION['jetoane_anunt'] ?? []) as $viu) {
        if (hash_equals((string) $viu, $jeton)) {
            return true;
        }
    }

    return false;
}

/**
 * Stinge jetonul. Se cheamă ÎNAINTE de trimitere, ca ștampila newsletterului:
 * dintre „a plecat de două ori" și „n-a plecat fiindcă s-a rupt ceva între
 * jeton și poștă", se alege a doua — un e-mail plecat nu se ia înapoi.
 */
function consumaJetonDeAnunt(string $jeton): void
{
    $_SESSION['jetoane_anunt'] = array_values(array_filter(
        $_SESSION['jetoane_anunt'] ?? [],
        static fn($viu): bool => !hash_equals((string) $viu, $jeton)
    ));
}

/**
 * „un om" / „3 oameni" / „21 de oameni".
 *
 * numaratoare() știe regula lui „de", dar nu și singularul — ea primește
 * substantivul gata ales. La unul singur se scrie „un om", nu „1 oameni".
 */
function catiOameni(int $cate): string
{
    return $cate === 1 ? 'un om' : numaratoare($cate, 'oameni');
}

/**
 * Un rând în logul anunțurilor.
 *
 * Fișier al lui, ca la newsletter și la mulțumiri. Un anunț către toată lista e
 * lucrul cel mai greu de luat înapoi de pe site: dacă cineva întreabă vreodată
 * „ce le-am scris oamenilor în martie", răspunsul trebuie să fie undeva, iar
 * amestecat cu restul s-ar fi pierdut între o sută de rânduri despre confirmări
 * de cont.
 */
function scrieInLogulAnunturilor(string $rand): void
{
    scrieInLog('anunturi.log', $rand);
}
