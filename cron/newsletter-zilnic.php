<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — newsletterul zilnic: „ce se întâmplă azi în oraș".
 *
 * Adună evenimentele de ASTĂZI și le trimite celor care au bifa „Vreau să
 * primesc e-mail cu evenimente noi (cel mult unul pe zi)" din setări.
 *
 * DACĂ AZI NU E NIMIC, NU PLEACĂ NIMIC — nici măcar un „azi nu se întâmplă
 * nimic". Un mesaj care nu spune nimic e cel mai bun fel de a-l învăța pe om să
 * nu-l mai deschidă, iar peste o lună, când chiar e ceva, mesajul ajunge tot
 * necitit.
 *
 * DE MÂNĂ, pentru încercare:
 *     php cron/newsletter-zilnic.php
 *     php cron/newsletter-zilnic.php --uscat   (arată, nu trimite)
 *
 * DIN CRON (cPanel → Cron Jobs), O DATĂ PE ZI, LA 12:00:
 *     0 12 * * *  php /home/UTILIZATOR/public_html/cron/newsletter-zilnic.php
 *
 * De ce la prânz și nu dimineața: la 12 se știe deja cum e ziua, iar pentru
 * ceva de la 19:00 mai sunt șapte ore în care omul poate să-și facă un plan.
 * Un mesaj la 7 dimineața se citește în autobuz și se uită până seara.
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

echo '[' . date('Y-m-d H:i:s') . "] Mă uit ce se întâmplă azi…\n";

/** „1 eveniment", „3 evenimente", „21 de evenimente". */
$cateEvenimente = static fn(int $cate): string =>
    numaratoare($cate, $cate === 1 ? 'eveniment' : 'evenimente');

$evenimente = evenimenteleDeAzi();

if ($evenimente === []) {
    echo "  azi nu e programat nimic. Nu trimit nimic — și e bine așa.\n";
    exit(0);
}

echo '  ' . $cateEvenimente(count($evenimente)) . " azi:\n";

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

$r = trimiteNewsletterulZilei();

scrieInLogulNewsletterului(
    $cateEvenimente($r['evenimente']) . ' azi, '
    . $r['abonati'] . ' abonați de servit: '
    . $r['trimise'] . ' trimise, ' . $r['picate'] . ' picate, '
    . $r['sarite'] . ' sărite, în ' . (time() - $inceput) . 's'
);

echo '[' . date('Y-m-d H:i:s') . '] Gata: '
   . $r['trimise'] . ' trimise, ' . $r['picate'] . ' picate'
   . ($r['sarite'] > 0 ? ', ' . $r['sarite'] . ' sărite (primiseră deja)' : '')
   . ".\n";

exit($r['picate'] > 0 ? 1 : 0);
