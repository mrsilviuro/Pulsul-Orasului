<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — mementoul de dinaintea evenimentelor care stau să înceapă.
 *
 * Caută evenimentele aprobate care încep în mai puțin de ORE_AMINTIRE ceasuri
 * și cărora nu le-a plecat încă mementoul, apoi scrie fiecărui participant că
 * seara aceea e pe cale să înceapă. Un eveniment e servit o singură dată —
 * semnul rămâne în `evenimente.amintire_trimisa_la`.
 *
 * DE MÂNĂ, pentru încercare:
 *     php cron/aminteste-de-eveniment.php
 *     php cron/aminteste-de-eveniment.php --uscat   (arată, nu trimite)
 *
 * DIN CRON (cPanel → Cron Jobs), DIN ORĂ ÎN ORĂ:
 *     php /home/UTILIZATOR/public_html/cron/aminteste-de-eveniment.php
 *
 * DE CE DIN ORĂ ÎN ORĂ, și de ce e de ajuns: fereastra e de trei ore, deci
 * fiecare eveniment e prins de cel puțin două treceri chiar dacă una sare.
 * Omul primește mementoul cu două-trei ore înainte — nu la fix, și nici nu
 * trebuie: „mai e ceva azi" nu e o veste care se strică în patruzeci de minute.
 *
 * Mai des n-ar aduce nimic, iar mai rar ar strica: la o dată pe zi, un
 * eveniment publicat dimineața pentru seara aceea n-ar mai apuca niciun
 * memento.
 *
 * DACĂ NU RULEAZĂ O NOAPTE nu se trimite nimic pentru ce a trecut între timp,
 * și e voit: un memento pentru o seară care s-a terminat nu e o veste, e o
 * părere de rău. Vezi evenimenteDeAmintit() din inc/amintiri.php.
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

require_once __DIR__ . '/../inc/amintiri.php';
require_once __DIR__ . '/vorba.php';   // cronul vorbește numai când are ce spune

$uscat = in_array('--uscat', $argv ?? [], true);

/**
 * ÎNCERCAREA USCATĂ SE AUDE MEREU, chiar și printr-o țeavă: omul a cerut anume
 * să vadă. Hotărârea se ia AICI, nu în ramura care desenează, fiindcă ramura
 * „nimic de trimis" iese cu un `exit` înaintea ei — pusă acolo, tăcea tocmai la
 * rularea din care vrei să afli de ce nu pleacă nimic.
 */
if ($uscat) {
    vorbesteOricum();
}

/* ------------------------------ Treaba --------------------------------- */

$inceput = time();

/**
 * CEASUL SE CITEȘTE O SINGURĂ DATĂ, la pornire.
 *
 * Altfel o rulare cu multe evenimente ar folosi alt „acum" la primul și altul
 * la ultimul, iar fereastra s-ar plimba sub picioarele ei. Aceeași grijă ca la
 * cron/multumeste-participantilor.php.
 */
$clipa = time();

spune('[' . date('Y-m-d H:i:s', $clipa) . '] Caut evenimente care încep în cel mult '
    . ORE_AMINTIRE . ' ore…');

/** „1 eveniment", „3 evenimente", „21 de evenimente". */
$cateEvenimente = static function (int $cate): string {
    return numaratoare($cate, $cate === 1 ? 'eveniment' : 'evenimente');
};

$evenimente = evenimenteDeAmintit($clipa);

/**
 * „Nimic de trimis" înseamnă două lucruri deosebite, iar spuse la fel n-ajută
 * pe nimeni: ori chiar nu începe nimic în următoarele ore — cazul obișnuit,
 * de vreo douăzeci de ori pe zi —, ori evenimentele au fost servite deja și
 * ștampila le ține deoparte. Al doilea încurcă pe oricine încearcă scriptul.
 *
 * Aceeași socoteală ca la cron/multumeste-participantilor.php.
 */
if ($evenimente === []) {
    $servite = cateAmintiriTrimise();

    if ($servite === 0) {
        spune('  nimic de trimis: niciun eveniment nu începe în curând.');
        exit(0);
    }

    /* Acordul se face după cifră: „1 eveniment a primit", „3 evenimente au
       primit". numaratoare() știe regula lui „de", dar nu și verbul. */
    spune('  nimic de trimis acum. ' . $cateEvenimente($servite)
        . ($servite === 1 ? ' a primit' : ' au primit') . ' deja mementoul:');

    foreach (amintiriDejaTrimise() as $ev) {
        spune('    „' . $ev['titlu'] . '" (' . $ev['data_eveniment'] . ' '
            . oraScurta($ev['ora_inceput']) . ')'
            . ' — trimis la ' . $ev['amintire_trimisa_la']);
    }

    exit(0);
}

/**
 * Încercarea uscată arată exact ce s-ar întâmpla, fără să atingă nimic: nici
 * mesajele nu pleacă, nici coloana nu se scrie. E singurul fel omenesc de a
 * verifica un script care trimite e-mailuri unor oameni adevărați.
 */
if ($uscat) {
    foreach ($evenimente as $ev) {
        $cati = count(participantiiCuEmail((int) $ev['id']));

        spune('  „' . $ev['titlu'] . '" (' . candIncepeEvenimentul($ev, $clipa) . '): '
            . $cati . ' pe listă'
            . ($cati === 0 ? ' — nimeni, nu s-ar trimite nimic' : ''));
    }

    spune('Încercare uscată: ' . $cateEvenimente(count($evenimente)) . ', nimic trimis.');
    exit(0);
}

$trimise = 0;
$picate  = 0;

foreach ($evenimente as $ev) {
    $rezultat = trimiteAmintirilePentruEveniment($ev, $clipa);

    $trimise += $rezultat['trimise'];
    $picate  += $rezultat['picate'];

    scrieInLogulAmintirilor(
        'evenimentul #' . $ev['id'] . ' („' . $ev['titlu'] . '", '
        . candIncepeEvenimentul($ev, $clipa) . '): '
        . $rezultat['oameni'] . ' pe listă, '
        . $rezultat['trimise'] . ' trimise, '
        . $rezultat['picate'] . ' picate'
    );

    spune('  „' . $ev['titlu'] . '": '
        . $rezultat['trimise'] . ' trimise, ' . $rezultat['picate'] . ' picate');
}

/**
 * AICI S-A ÎNTÂMPLAT CEVA, deci rularea asta merită un mesaj.
 *
 * Pentru toate evenimentele găsite, nu doar pentru cele cu mesaje plecate: unul
 * fără nimeni pe listă nu trimite nimic, DAR își pune ștampila și nu mai apare
 * niciodată. Tăcut, ar fi fost tocmai felul de întâmplare despre care afli
 * peste o lună.
 */
sAIntamplatCeva();

/**
 * Rândul de încheiere se scrie în log doar când chiar a plecat ceva.
 *
 * Altfel, un cron din oră în oră ar umple fișierul cu 8760 de rânduri pe an
 * care spun „n-am avut ce face" — iar când s-ar întâmpla ceva, s-ar pierde
 * printre ele. Aceeași socoteală ca la cron/multumeste-participantilor.php.
 */
if ($trimise > 0 || $picate > 0) {
    scrieInLogulAmintirilor(
        'gata: ' . $cateEvenimente(count($evenimente)) . ', ' . $trimise . ' mesaje trimise, '
        . $picate . ' picate, în ' . (time() - $inceput) . 's'
    );
}

/* Conexiunea cu serverul de poștă a stat deschisă tot teancul (vezi postasul()
   din inc/posta.php). Aici s-a terminat treaba, deci se închide. */
inchidePostasul();

spune('[' . date('Y-m-d H:i:s') . '] Gata: ' . $cateEvenimente(count($evenimente)) . ', '
    . $trimise . ' trimise, ' . $picate . ' picate.');

exit($picate > 0 ? 1 : 0);
