<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — mementoul de dinaintea unui eveniment.
 *
 * Cu puțin înainte să înceapă, fiecare om de pe lista de participanți primește
 * o dată un e-mail: „«X» începe azi la 19:00, la Sala Sporturilor". Atât.
 *
 * DE CE EXISTĂ
 *
 * Site-ul avea nouăsprezece feluri de e-mail și niciunul nu-i spunea omului că
 * URMEAZĂ ceva la care s-a înscris. I se mulțumea DUPĂ, îi venea newsletterul
 * dimineața — dar acela merge după o bifă din setări, care n-are nicio legătură
 * cu înscrierile lui. Cine apăsa „Particip" pe 3 pentru o seară de pe 20 nu mai
 * auzea nimic despre ea până când trecea.
 *
 * Acolo se pierd oamenii: nu la înscriere, ci între înscriere și seara aceea.
 * Iar la un eveniment cu locuri limitate, unul care uită ține degeaba ocupat un
 * loc pe care l-ar fi luat altcineva.
 *
 * NU E UN MESAJ NECHEMAT, deci n-are link de dezabonare și n-are bifă în
 * setări. E răspunsul la o faptă a omului — a apăsat el „Particip" —, ca
 * mulțumirea de după sau ca vestea că i s-a anulat ceva. Newsletterul zilnic
 * rămâne singurul mesaj de pe site care vine fără să-l fi cerut nimeni anume.
 * Cine nu-l mai vrea are unde apăsa: se scoate de pe lista de participanți.
 *
 * DE CE PRINTR-UN CRON
 *
 * Fiindcă „mai sunt trei ore până începe" nu e fapta nimănui. Nu există nicio
 * cerere de prins, niciun buton apăsat — e doar ceasul care merge mai departe,
 * exact ca la încheierea unui eveniment căruia îi trece ziua. Singurul loc din
 * care se poate observa e ceva care trece din când în când și se uită.
 *
 * DE MÂNĂ, pentru încercare:
 *     php cron/aminteste-de-eveniment.php --uscat
 */

require_once __DIR__ . '/evenimente.php';
require_once __DIR__ . '/interese.php';
require_once __DIR__ . '/newsletter.php';
require_once __DIR__ . '/email.php';

/**
 * Cu cât înainte pleacă mementoul.
 *
 * TREI ORE, și cronul trece din oră în oră — deci în fapt omul îl primește cu
 * două-trei ore înainte. E răstimpul în care încă mai poate face ceva cu
 * vestea: să plece de acasă, să-și mute altceva, ori să se scoată de pe listă
 * dacă vede că nu ajunge — și atunci locul lui se eliberează cât mai e cineva
 * care să-l ia.
 *
 * Cu o zi înainte ar fi fost prea devreme (uiți până a doua zi), cu o oră prea
 * târziu (dacă nu ești deja pe drum, n-ai ce face cu ea).
 *
 * ORE_MINIM_INAINTE din inc/validare.php e două: un eveniment nu poate fi
 * publicat mai devreme de-atât. Deci un anunț scris în ultima clipă intră în
 * fereastra asta de la bun început, iar oamenii lui primesc mementoul la prima
 * trecere a cronului. E bine așa — cu cât e mai pe nepusă masă, cu atât mai
 * mult are nevoie cineva să i se amintească.
 */
const ORE_AMINTIRE = 3;

/* ============================== CITIREA ============================== */

/**
 * Evenimentele care stau să înceapă și cărora nu le-a plecat încă mementoul.
 *
 * FEREASTRA SE UITĂ LA CLIPĂ, NU LA ZI. Aceeași regulă ca la publicare (vezi
 * ORE_MINIM_INAINTE din inc/validare.php): data se lipește de oră, fiindcă
 * altfel „azi" ar fi însemnat și 07:00, și 23:00. Se cer amândouă capetele:
 *
 *   - încă n-a început  — un memento pentru ceva ce a pornit deja e mai rău
 *                          decât niciunul: omul îl citește și află că a
 *                          pierdut. Ține și dacă cronul n-a rulat o noapte:
 *                          ce-a trecut, a trecut, nu se mai scrie nimănui;
 *   - începe în cel mult ORE_AMINTIRE — altfel ar pleca la fiecare trecere,
 *                          din clipa publicării.
 *
 * Numai cele APROBATE: la unul în așteptare sau respins nu s-a putut înscrie
 * nimeni, la unul anulat oamenii au primit deja alt mesaj, iar unul încheiat
 * cu mâna nu mai are de ce să înceapă. Nu se cere `stare_moderare` să nu fie
 * „incheiat" pe deasupra — nu e în lista de stări cerute.
 *
 * O VÂNĂTOARE „FINDME" nu iese niciodată de aici cu cineva pe listă: acolo nu
 * se înscrie nimeni, caseta de interes nici nu există pe pagină. Deci nu se
 * cere nimic anume despre `joc_qr` — lista de participanți iese goală singură,
 * iar evenimentul se ștampilează și e lăsat în pace.
 *
 * Condiția pe `data_eveniment` e de prisos pentru adevăr, dar nu și pentru
 * viteză: ea singură poate folosi cheia din sql/034, pe când cea care lipește
 * data de oră nu. Fără ea, cronul ar citi din oră în oră tabelul întreg de
 * evenimente — care crește pentru totdeauna, fiindcă rândurile nu se șterg.
 *
 * $clipa există pentru probe: altfel n-ai cum să spui „ia închipuie-ți că e cu
 * două ore înainte" fără să muți ceasul mașinii. Aceeași ușă ca la
 * evenimenteleDeAzi() din inc/newsletter.php.
 */
function evenimenteDeAmintit(?int $clipa = null, int $celMult = 200): array
{
    $clipa  = $clipa ?? time();
    $pana   = $clipa + ORE_AMINTIRE * 3600;

    $q = db()->prepare(
        'SELECT e.id, e.titlu, e.slug, e.coperta, e.oras, e.locatie, e.descriere,
                e.data_eveniment, e.ora_inceput, e.membru_id,
                c.nume AS categorie, c.imagine_default
           FROM evenimente e
           JOIN categorii c ON c.id = e.categorie_id
          WHERE e.amintire_trimisa_la IS NULL
            AND e.stare_moderare = \'aprobat\'
            AND e.data_eveniment BETWEEN ? AND ?
            AND TIMESTAMP(e.data_eveniment, e.ora_inceput) >  ?
            AND TIMESTAMP(e.data_eveniment, e.ora_inceput) <= ?
          ORDER BY e.data_eveniment, e.ora_inceput, e.id
          LIMIT ' . max(1, $celMult)
    );
    $q->execute([
        date('Y-m-d', $clipa), date('Y-m-d', $pana),
        date('Y-m-d H:i:s', $clipa), date('Y-m-d H:i:s', $pana),
    ]);

    return $q->fetchAll();
}

/**
 * Evenimentele cărora LE-A PLECAT deja mementoul.
 *
 * Nu e nevoie de ea ca să se trimită ceva — e nevoie ca să se poată răspunde la
 * întrebarea „de ce nu trimite nimic?". Aceeași grijă ca la
 * multumiriDejaTrimise() din inc/multumiri.php, și din același motiv: „nimic de
 * trimis" înseamnă două lucruri foarte deosebite, iar cel care încearcă
 * scriptul are nevoie să știe care din ele.
 */
function amintiriDejaTrimise(int $celMult = 5): array
{
    $q = db()->prepare(
        'SELECT id, titlu, slug, data_eveniment, ora_inceput, amintire_trimisa_la
           FROM evenimente
          WHERE amintire_trimisa_la IS NOT NULL
          ORDER BY amintire_trimisa_la DESC
          LIMIT ' . max(1, $celMult)
    );
    $q->execute();

    return $q->fetchAll();
}

/** Câte evenimente au primit deja mementoul. */
function cateAmintiriTrimise(): int
{
    return (int) db()->query(
        'SELECT COUNT(*) FROM evenimente WHERE amintire_trimisa_la IS NOT NULL'
    )->fetchColumn();
}

/* ============================== VORBELE ============================== */

/**
 * „azi la 19:00", „mâine la 00:30" — când începe, scris pentru un om.
 *
 * DE CE NU „peste două ore". Un mesaj poate sta un sfert de oră prin poștă și
 * încă o jumătate în telefonul cuiva; „peste două ore" ar fi atunci o
 * minciună, pe când „la 19:00" rămâne adevărat oricând ar fi citit.
 *
 * DE CE SE SCRIE ȘI ZIUA. Fereastra e de trei ore, deci de obicei e chiar azi
 * — dar nu întotdeauna: la 22:00 intră în ea și un eveniment de a doua zi, de
 * la 00:30. Scris fără zi, mesajul acela ar fi spus „la 00:30" despre ceva ce
 * pare că a trecut demult.
 */
function candIncepeEvenimentul(array $eveniment, ?int $clipa = null): string
{
    $clipa = $clipa ?? time();
    $zi    = (string) ($eveniment['data_eveniment'] ?? '');
    $ora   = oraScurta($eveniment['ora_inceput'] ?? null);

    if ($zi === '' || $ora === '') {
        return '';
    }

    $cand = match ($zi) {
        date('Y-m-d', $clipa)              => 'azi',
        date('Y-m-d', $clipa + 86400)      => 'mâine',
        default                            => dataScrisaMic($zi),
    };

    return $cand . ' la ' . $ora;
}

/* ============================== SCRIEREA ============================= */

/**
 * Semnul că pentru evenimentul ăsta s-a trimis mementoul.
 *
 * Se pune ȘI când n-a plecat niciun mesaj — la unul fără nimeni pe listă, sau
 * la unul unde toate încercările au picat. Altfel rândul ar fi cercetat la
 * fiecare trecere cât ține fereastra, iar o adresă care nu primește la 16:00
 * n-o să primească nici la 17:00.
 */
function insemneazaAmintireaTrimisa(int $evenimentId): void
{
    $q = db()->prepare('UPDATE evenimente SET amintire_trimisa_la = ? WHERE id = ?');
    $q->execute([acum(), $evenimentId]);
}

/**
 * Trimite mementoul pentru un eveniment și însemnează că s-a făcut.
 *
 * Întoarce ce s-a întâmplat, ca să poată fi scris în log și pe ecran:
 * `['oameni' => n, 'trimise' => n, 'picate' => n]`.
 *
 * ȘTAMPILA SE PUNE LA SFÂRȘIT, ca la mulțumiri și spre deosebire de
 * newsletter. Dacă scriptul cade la jumătate, oamenii dinaintea căderii vor
 * primi mementoul de două ori la trecerea următoare; dacă am ștampila întâi, o
 * cădere ar lăsa restul listei fără niciunul, definitiv.
 *
 * Aici alegerea e mai ușoară decât oriunde altundeva pe site, fiindcă un
 * memento trimis de două ori NU sună greșit a doua oară: mesajul spune ora la
 * care începe, nu „peste cât timp", deci a doua copie e la fel de adevărată ca
 * prima. Iar cine e lăsat fără niciunul nu află nimic. Dintre „încă o dată" și
 * „niciodată", alegem prima — și aici o alegem fără să ne doară.
 *
 * CARTONAȘUL e desenat de randuriPentruNewsletter() din inc/newsletter.php,
 * același din newsletterul zilnic și din vestea către urmăritori: poza lată
 * deasupra, categoria, titlul, ora și locul. Un singur fel de a arăta un
 * eveniment într-un e-mail, oriunde ar fi pus.
 */
function trimiteAmintirilePentruEveniment(array $eveniment, ?int $clipa = null): array
{
    $evenimentId = (int) $eveniment['id'];
    $organizator = (int) $eveniment['membru_id'];

    $oameni   = participantiiCuEmail($evenimentId);
    $rezultat = ['oameni' => count($oameni), 'trimise' => 0, 'picate' => 0];

    if ($oameni === []) {
        insemneazaAmintireaTrimisa($evenimentId);
        return $rezultat;
    }

    $cartonas = randuriPentruNewsletter([$eveniment])[0] ?? [];
    $cand     = candIncepeEvenimentul($eveniment, $clipa);

    foreach ($oameni as $om) {
        $plecat = emailAminteDeEveniment(
            (string) $om['email'],
            (string) $om['prenume'],
            (string) $eveniment['titlu'],
            $cand,
            $cartonas,
            (int) $om['id'] === $organizator
        );

        $plecat ? $rezultat['trimise']++ : $rezultat['picate']++;
    }

    insemneazaAmintireaTrimisa($evenimentId);

    return $rezultat;
}

/** Un rând în private/amintiri-trimise.log. */
function scrieInLogulAmintirilor(string $rand): void
{
    scrieInLog('amintiri-trimise.log', $rand);
}
