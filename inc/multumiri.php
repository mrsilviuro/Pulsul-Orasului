<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — mulțumirea de după eveniment.
 *
 * După ce un eveniment s-a încheiat, fiecare om rămas pe lista de participanți
 * primește o dată un e-mail: mulțumim că ai venit, iar dacă vrei, treci pe
 * pagină și dă câte o stea celorlalți.
 *
 * DE CE PRINTR-UN CRON, și nu în clipa încheierii
 *
 * Fiindcă încheierea se întâmplă în două feluri, iar unul dintre ele nu se
 * întâmplă nicăieri. Organizatorul poate apăsa „Încheie evenimentul", și
 * atunci există un moment anume; dar un eveniment se încheie și singur, când
 * îi trece ziua — iar aceea nu e o faptă a nimănui, e doar ceasul care merge
 * mai departe. Nu există nicio cerere pe care s-o prindem, niciun buton apăsat.
 * Singurul loc din care se poate observa e ceva care trece din când în când și
 * se uită.
 *
 * Iar dacă tot trebuie un cron pentru al doilea fel, îl lăsăm să le facă pe
 * amândouă: altfel același mesaj ar pleca din două locuri diferite, cu două
 * feluri de a socoti cine îl primește, și s-ar despărți la prima corectură.
 * Organizatorul care apasă butonul nu trimite nimic — doar schimbă starea, iar
 * cronul vede la următoarea trecere.
 *
 * CUM SE ȚINE MINTE CE-A PLECAT
 *
 * În `evenimente.multumiri_trimise_la` (vezi sql/018-multumiri-eveniment.sql).
 * Fără coloana aia, cronul n-ar avea cum să deosebească un eveniment încheiat
 * ieri cu mesajele trimise de unul încheiat ieri cu mesajele netrimise, și
 * le-ar trimite din nou la fiecare rulare — din oră în oră, pentru totdeauna.
 *
 * DE MÂNĂ, pentru încercare:
 *     php cron/multumeste-participantilor.php --uscat
 */

require_once __DIR__ . '/evenimente.php';
require_once __DIR__ . '/interese.php';
require_once __DIR__ . '/email.php';

/**
 * Sub atâția oameni pe listă, nu pleacă nimic.
 *
 * Mesajul e, în cea mai mare parte, o invitație la note — iar notele se dau
 * între oameni. La un eveniment pe care l-a ținut cineva singur nu e cui să i
 * se dea o stea, deci n-are ce spune mesajul: ar fi o scrisoare care te
 * trimite pe o pagină unde nu e nimeni.
 */
const MULTUMIRI_MINIM_OAMENI = 2;

/* ============================== CITIREA ============================== */

/**
 * Evenimentele încheiate cărora nu le-au plecat încă mulțumirile.
 *
 * Aceleași două feluri de a fi încheiat ca peste tot (vezi evenimentIncheiat()
 * din inc/evenimente.php): starea pusă de organizator, sau ziua care a trecut.
 * Scrise aici ca o singură condiție, fiindcă aici avem de ales rânduri din
 * bază, nu de cercetat unul pe care îl ținem deja în mână.
 *
 * Doar ce a fost publicat: la un eveniment rămas în așteptare sau respins nu
 * s-a putut înscrie nimeni, iar la unul anulat oamenii au primit deja alt
 * mesaj — și n-au la ce să se uite înapoi.
 *
 * Cele mai vechi întâi: dacă cronul n-a rulat câteva zile, se încep cu cele
 * care așteaptă de cel mai mult timp.
 */
function evenimenteFaraMultumiri(int $celMult = 200): array
{
    $q = db()->prepare(
        'SELECT id, titlu, slug, data_eveniment, membru_id
           FROM evenimente
          WHERE multumiri_trimise_la IS NULL
            AND stare_moderare IN (\'aprobat\', \'incheiat\')
            AND (stare_moderare = \'incheiat\' OR data_eveniment < ?)
          ORDER BY data_eveniment, id
          LIMIT ' . max(1, $celMult)
    );
    $q->execute([date('Y-m-d')]);

    return $q->fetchAll();
}

/**
 * Evenimentele încheiate cărora LE-AU PLECAT deja mulțumirile.
 *
 * Nu e nevoie de ea ca să se trimită ceva — e nevoie ca să se poată răspunde la
 * întrebarea „de ce nu trimite nimic?".
 *
 * Fără ea, cronul spunea „nimic de trimis" și atât, iar asta înseamnă două
 * lucruri complet diferite: ori chiar n-a avut loc nimic, ori evenimentele au
 * fost servite demult și ștampila lor le ține deoparte pentru totdeauna. Al
 * doilea caz e cel care încurcă pe oricine încearcă să vadă dacă merge:
 * pune un eveniment pe „încheiat", cronul îl consumă în tăcere (poate fără să
 * trimită nimic, dacă erau prea puțini oameni), iar de atunci încolo tace.
 *
 * Cele mai proaspăt servite întâi: la o încercare, aia e cea despre care se
 * întreabă.
 */
function multumiriDejaTrimise(int $celMult = 5): array
{
    $q = db()->prepare(
        'SELECT id, titlu, slug, data_eveniment, multumiri_trimise_la
           FROM evenimente
          WHERE multumiri_trimise_la IS NOT NULL
          ORDER BY multumiri_trimise_la DESC
          LIMIT ' . max(1, $celMult)
    );
    $q->execute();

    return $q->fetchAll();
}

/** Câte evenimente au primit deja mulțumirile. */
function cateMultumiriTrimise(): int
{
    return (int) db()->query(
        'SELECT COUNT(*) FROM evenimente WHERE multumiri_trimise_la IS NOT NULL'
    )->fetchColumn();
}

/**
 * Cine primește mesajul: oamenii de pe lista de participanți.
 *
 * Întrebarea e scrisă o singură dată, în participantiiCuEmail() din
 * inc/interese.php — acolo unde stă tot ce știe site-ul despre cine e pe o
 * listă. O cere și mementoul de dinaintea evenimentului (inc/amintiri.php), iar
 * cele două n-au voie să socotească altfel: dacă unul dintre ele ar număra un om
 * în plus, acela ar primi un e-mail pe care celălalt spune că nu i se cuvine.
 *
 * Numele rămâne al lui: aici se cheamă ca să se mulțumească.
 */
function participantiiDeMultumit(int $evenimentId): array
{
    return participantiiCuEmail($evenimentId);
}

/* ============================== SCRIEREA ============================= */

/**
 * Semnul că pentru evenimentul ăsta s-a terminat treaba.
 *
 * Se pune ȘI când n-a plecat niciun mesaj — la un eveniment fără oameni, sau
 * la unul unde toate încercările au picat. Altfel rândul acela ar fi cercetat
 * la fiecare rulare, pentru totdeauna, iar o adresă care nu primește azi n-o
 * să primească nici la a suta încercare.
 */
function insemneazaMultumiriTrimise(int $evenimentId): void
{
    $q = db()->prepare(
        'UPDATE evenimente SET multumiri_trimise_la = ? WHERE id = ?'
    );
    $q->execute([acum(), $evenimentId]);
}

/**
 * Trimite mulțumirile pentru un eveniment și însemnează că s-a făcut.
 *
 * Întoarce ce s-a întâmplat, ca să poată fi scris în log și pe ecran:
 * `['oameni' => n, 'trimise' => n, 'picate' => n]`.
 *
 * Însemnarea se pune la SFÂRȘIT, dar se pune întotdeauna. Dacă scriptul cade
 * la jumătate, rândul rămâne neînsemnat și oamenii dinaintea căderii vor primi
 * mesajul de două ori la următoarea rulare. E partea nefericită a alegerii —
 * dar cealaltă (să însemnăm întâi) ar face ca o cădere să lase pe cineva fără
 * mesaj, definitiv. Dintre „încă o dată" și „niciodată", alegem prima.
 */
function trimiteMultumiriPentruEveniment(array $eveniment): array
{
    $evenimentId  = (int) $eveniment['id'];
    $organizator  = (int) $eveniment['membru_id'];
    $titlu        = (string) $eveniment['titlu'];

    /**
     * Adresa duce drept la tabul cu oameni, nu la capul paginii.
     *
     * „#panel-going" e chiar id-ul panoului; taburile se deschid după hash
     * (vezi „data-tabs" din assets/js/main.js). Fără el, omul ar ateriza pe
     * descrierea unui eveniment la care tocmai a fost și ar trebui să caute
     * singur unde se dau stelele.
     */
    $adresa = urlIntreg(urlEveniment((string) $eveniment['slug'])) . '#panel-going';

    $oameni = participantiiDeMultumit($evenimentId);
    $cati   = count($oameni);

    $rezultat = ['oameni' => $cati, 'trimise' => 0, 'picate' => 0];

    if ($cati < MULTUMIRI_MINIM_OAMENI) {
        insemneazaMultumiriTrimise($evenimentId);
        return $rezultat;
    }

    foreach ($oameni as $om) {
        $plecat = emailMultumireParticipare(
            (string) $om['email'],
            (string) $om['prenume'],
            $titlu,
            $adresa,
            (int) $om['id'] === $organizator
        );

        $plecat ? $rezultat['trimise']++ : $rezultat['picate']++;
    }

    insemneazaMultumiriTrimise($evenimentId);

    return $rezultat;
}

/** Un rând în private/multumiri-trimise.log. */
function scrieInLogulMultumirilor(string $rand): void
{
    scrieInLog('multumiri-trimise.log', $rand);
}
