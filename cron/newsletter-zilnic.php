<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — newsletterul zilnic: „ce se întâmplă azi în oraș".
 *
 * Adună evenimentele de ASTĂZI CARE N-AU ÎNCEPUT ÎNCĂ și le trimite celor care
 * au bifa de vești din setări (`membri.newsletter`).
 *
 * DACĂ NU E NIMIC, NU PLEACĂ NIMIC — nici măcar un „azi nu se întâmplă nimic".
 * Un mesaj care nu spune nimic e cel mai bun fel de a-l învăța pe om să nu-l
 * mai deschidă, iar peste o lună, când chiar e ceva, mesajul ajunge tot necitit.
 * Asta se întâmplă și în ziua în care tot ce era a trecut deja: la 12 nu mai are
 * rost să spui că la 10 a fost o alergare.
 *
 * DE MÂNĂ, pentru încercare:
 *     php cron/newsletter-zilnic.php
 *     php cron/newsletter-zilnic.php --uscat   (arată, nu trimite)
 *
 * DIN CRON (cPanel → Cron Jobs), DIN SFERT ÎN SFERT DE CEAS, DE LA 12 LA 17:
 *     0,15,30,45 12-17 * * *  php /home/UTILIZATOR/public_html/cron/newsletter-zilnic.php
 *
 * DE CE DE MAI MULTE ORI PE ZI, DACĂ E UN NEWSLETTER ZILNIC. Fiindcă găzduirea
 * duce zece mesaje pe minut și șase sute pe ceas. O rulare care ar servi toată
 * lista deodată sare plafonul, iar cine îl sare nu primește un avertisment: i se
 * oprește poșta pentru tot restul orei — cu tot cu confirmările de cont și
 * recuperările de parolă ale unor oameni care n-au nicio treabă cu newsletterul.
 * Deci lista se servește în teancuri de NEWSLETTER_PE_RULARE (50), cu pas de
 * melc între mesaje, iar rulările de după prima continuă de unde s-a ajuns.
 *
 * SE POATE AȘA DOAR FIINDCĂ ȘTAMPILA E PE OM, nu pe rulare: cine a primit azi
 * nu mai intră în teancul următor (vezi abonatiiNewsletterului). Rulările în
 * plus dintr-o zi în care lista s-a terminat din prima nu fac nimic și nu costă
 * nimic — o interogare care nu găsește pe nimeni.
 *
 * PÂNĂ LA 17, nu toată noaptea: un newsletter care spune „ce se întâmplă azi"
 * și ajunge la 23:00 e o batjocură. Dacă lista a crescut atât încât să nu
 * încapă în cele cinci ceasuri, nu se mai lungește fereastra — atunci chiar e
 * momentul unui serviciu de trimis e-mailuri.
 *
 * De ce la prânz și nu dimineața: la 12 se știe deja cum e ziua — au apucat să
 * se scrie și anunțurile puse în cursul dimineții — iar pentru ceva de la 19:00
 * mai sunt șapte ore în care omul poate să-și facă un plan. Un mesaj la 7
 * dimineața se citește în autobuz și se uită până seara.
 *
 * PREȚUL orei ăsteia e că unele evenimente au și început până la 12. De aceea
 * lista începe de la CLIPA TRIMITERII, nu de la miezul nopții: ce a pornit
 * rămâne pe site, dar nu se mai bate la ușa nimănui. Un mesaj care la 12 spune
 * „azi la 10 e o alergare" nu e o veste, e o părere de rău.
 *
 * SE TRIMITE O SINGURĂ DATĂ PE ZI, oricâte ori ar rula. Ștampila e
 * `membri.newsletter_trimis_la` (sql/031) și se pune ÎNAINTE de trimitere, nu
 * după: un e-mail plecat nu se ia înapoi, deci dintre „a plecat de două ori" și
 * „n-a plecat pentru că s-a stins curentul între ștampilă și poștă" se alege a
 * doua. De aceea o rulare de mână, ca să se vadă dacă merge, nu strică nimic —
 * cel mult „mănâncă" ziua unui om care primise deja.
 *
 * Dacă nu rulează o zi, ziua aceea se pierde, și e în regulă: un newsletter
 * zilnic nu se recuperează. Nimeni nu vrea să afle mâine ce era ieri.
 */

/* --------------------------- Doar din consolă -------------------------- */

/**
 * Scriptul stă sub rădăcina site-ului, deci ar putea fi deschis din browser.
 * Nu trebuie să se poată: oricine ar putea porni un val de e-mailuri.
 *
 * .htaccess din cron/ oprește accesul, dar verificarea asta nu costă nimic și
 * ține și acolo unde .htaccess nu e citit (nginx, de pildă).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Se rulează doar din linia de comandă.\n");
}

require_once __DIR__ . '/../inc/newsletter.php';

$uscat = in_array('--uscat', $argv ?? [], true);

/* ------------------------------ Treaba --------------------------------- */

$inceput = time();

/**
 * O SINGURĂ CITIRE A CEASULUI, dusă mai departe peste tot.
 *
 * Lista începe de la clipa asta, iar rularea ține minute bune. Dacă ora s-ar
 * lua din nou mai încolo, ce se scrie aici pe ecran și ce ajunge în mesaje ar
 * putea fi liste deosebite — un eveniment fix la minutul care tocmai a trecut
 * ar fi în prima și nu în a doua.
 */
$acum = time();

echo '[' . date('Y-m-d H:i:s', $acum) . "] Mă uit ce urmează azi…\n";

/** „1 eveniment", „3 evenimente", „21 de evenimente". */
$cateEvenimente = static fn(int $cate): string =>
    numaratoare($cate, $cate === 1 ? 'eveniment' : 'evenimente');

$evenimente = evenimenteleDeAzi(NEWSLETTER_MAX_EVENIMENTE, $acum);

/**
 * Lista goală înseamnă două lucruri deosebite, ca și „niciun abonat de servit"
 * de mai jos: ori azi chiar nu e nimic, ori tot ce era a început deja. În
 * amândouă cazurile nu pleacă nimic — dar cine rulează de mână, seara, ca să
 * vadă dacă merge, trebuie să afle care din două e.
 *
 * Ziua întreagă se cere tot prin evenimenteleDeAzi(), doar că socotită de la
 * miezul nopții: aceeași întrebare, alt punct de plecare.
 */
if ($evenimente === []) {
    $toataZiua = evenimenteleDeAzi(200, strtotime('today', $acum));

    if ($toataZiua === []) {
        echo "  azi nu e programat nimic. Nu trimit nimic — și e bine așa.\n";
    } else {
        echo '  ' . $cateEvenimente(count($toataZiua))
           . " azi, dar toate au început deja. Nu trimit nimic.\n";
    }

    exit(0);
}

echo '  ' . $cateEvenimente(count($evenimente)) . ' de acum înainte (după '
   . date('H:i', $acum) . "):\n";

foreach ($evenimente as $ev) {
    echo '    ' . substr((string) $ev['ora_inceput'], 0, 5)
       . ' — „' . $ev['titlu'] . '" (' . $ev['locatie'] . ")\n";
}

$abonati = abonatiiNewsletterului();

/**
 * „Niciun abonat de servit" înseamnă două lucruri foarte diferite, iar
 * amândouă arată la fel dacă nu se spune care e.
 *
 * Ori chiar n-are nimeni bifa pornită, ori toți au primit deja astăzi și
 * ștampila îi ține deoparte. Al doilea caz încurcă pe oricine încearcă să vadă
 * dacă merge: rulezi o dată, pleacă mesajele, rulezi din nou și tace.
 */
if ($abonati === []) {
    $toti = catiAbonati();

    if ($toti === 0) {
        echo "  dar nimeni n-are bifa de newsletter pornită.\n";
    } else {
        echo '  dar toți cei ' . $toti . " abonați au primit deja astăzi.\n";
    }

    exit(0);
}

echo '  ' . count($abonati) . ' de servit (din ' . catiAbonati() . " abonați).\n";

if ($uscat) {
    echo "\nÎncercare uscată — nimic trimis, nicio ștampilă pusă.\n";
    echo "Primii câțiva care ar primi:\n";

    foreach (array_slice($abonati, 0, 5) as $om) {
        echo '    ' . $om['prenume'] . ' <' . $om['email'] . ">\n";
    }

    if (count($abonati) > 5) {
        echo '    …și încă ' . (count($abonati) - 5) . ".\n";
    }

    exit(0);
}

$r = trimiteNewsletterulZilei(false, $acum);

/* Conexiunea cu serverul de poștă a stat deschisă tot teancul (vezi postasul()
   din inc/posta.php). Aici s-a terminat treaba, deci se închide — o cerere web
   n-ar avea nevoie, PHP închide oricum tot la sfârșit, dar o rulare de cron
   ține minute bune și n-are de ce să lase o legătură atârnând. */
inchidePostasul();

scrieInLogulNewsletterului(
    $cateEvenimente($r['evenimente']) . ' după ' . date('H:i', $acum) . ', '
    . $r['abonati'] . ' abonați de servit: '
    . $r['trimise'] . ' trimise, ' . $r['picate'] . ' picate, '
    . $r['sarite'] . ' sărite, în ' . (time() - $inceput) . 's'
);

echo '[' . date('Y-m-d H:i:s') . '] Gata: '
   . $r['trimise'] . ' trimise, ' . $r['picate'] . ' picate'
   . ($r['sarite'] > 0 ? ', ' . $r['sarite'] . ' sărite (primiseră deja)' : '')
   . ".\n";

exit($r['picate'] > 0 ? 1 : 0);
