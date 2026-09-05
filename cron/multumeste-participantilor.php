<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — mulțumirile de după evenimentele încheiate.
 *
 * Caută evenimentele care s-au încheiat și cărora nu le-au plecat încă
 * mesajele, apoi trimite fiecărui participant o mulțumire și o invitație să
 * dea câte o stea celorlalți. Un eveniment e servit o singură dată — semnul
 * rămâne în `evenimente.multumiri_trimise_la`.
 *
 * DE MÂNĂ, pentru încercare:
 *     php cron/multumeste-participantilor.php
 *     php cron/multumeste-participantilor.php --uscat   (arată, nu trimite)
 *
 * DIN CRON (cPanel → Cron Jobs), DIN ORĂ ÎN ORĂ:
 *     php /home/UTILIZATOR/public_html/cron/multumeste-participantilor.php
 *
 * De ce din oră în oră, și nu o dată pe zi ca la anonimizare: acolo se aștepta
 * un răgaz de treizeci de zile, deci o zi în plus nu însemna nimic. Aici omul
 * tocmai s-a întors acasă de la eveniment, iar o mulțumire care vine a doua zi
 * seara nu mai are aceeași căldură. Nici mai des n-are rost: un eveniment care
 * își încheie ziua la miezul nopții n-are cui să-i pese de zece minute.
 *
 * Dacă nu rulează o zi, nu se pierde nimic: evenimentele așteaptă cuminți, cu
 * coloana goală, și își iau mesajele la următoarea trecere. Cele mai vechi
 * întâi.
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

require_once __DIR__ . '/../inc/multumiri.php';
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

spune('[' . date('Y-m-d H:i:s') . '] Caut evenimente încheiate fără mulțumiri…');

/**
 * „1 eveniment", „3 evenimente", „21 de evenimente".
 *
 * numaratoare() știe regula lui „de" (vezi inc/validare.php), dar nu și
 * singularul: ea primește substantivul gata ales. Aici se alege.
 */
$cateEvenimente = static function (int $cate): string {
    return numaratoare($cate, $cate === 1 ? 'eveniment' : 'evenimente');
};

$evenimente = evenimenteFaraMultumiri();

/**
 * „Nimic de trimis" înseamnă două lucruri foarte diferite, iar până acum le
 * spunea pe amândouă la fel.
 *
 * Ori chiar n-a avut loc nimic, ori evenimentele au fost servite demult și
 * ștampila din `multumiri_trimise_la` le ține deoparte pentru totdeauna. Al
 * doilea caz încurcă pe oricine încearcă să vadă dacă merge: pui un eveniment
 * pe „încheiat", cronul îl consumă la prima trecere — poate fără să trimită
 * nimic, dacă erau prea puțini oameni pe listă — iar de atunci încolo tace,
 * orice ai mai face cu el.
 *
 * Deci, când n-are ce trimite, spune de ce.
 */
if ($evenimente === []) {
    $servite = cateMultumiriTrimise();

    if ($servite === 0) {
        spune('  nimic de trimis: niciun eveniment încheiat fără mulțumiri.');
        exit(0);
    }

    /* Acordul se face după cifră: „1 eveniment a primit", „3 evenimente au
       primit". numaratoare() știe regula lui „de", dar nu și verbul. */
    spune('  nimic de trimis. ' . $cateEvenimente($servite)
        . ($servite === 1 ? ' a primit' : ' au primit') . ' deja mulțumirile:');

    foreach (multumiriDejaTrimise() as $ev) {
        spune('    „' . $ev['titlu'] . '" (' . $ev['data_eveniment'] . ')'
            . ' — trimise la ' . $ev['multumiri_trimise_la']);
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
        $oameni = participantiiDeMultumit((int) $ev['id']);
        $cati   = count($oameni);

        spune('  „' . $ev['titlu'] . '" (' . $ev['data_eveniment'] . '): '
            . $cati . ' pe listă'
            . ($cati < MULTUMIRI_MINIM_OAMENI ? ' — prea puțini, nu s-ar trimite nimic' : ''));
    }

    spune('Încercare uscată: ' . $cateEvenimente(count($evenimente)) . ', nimic trimis.');
    exit(0);
}

$trimise = 0;
$picate  = 0;

foreach ($evenimente as $ev) {
    $rezultat = trimiteMultumiriPentruEveniment($ev);

    $trimise += $rezultat['trimise'];
    $picate  += $rezultat['picate'];

    scrieInLogulMultumirilor(
        'evenimentul #' . $ev['id'] . ' („' . $ev['titlu'] . '"): '
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
 * Se cheamă pentru toate evenimentele găsite, nu doar pentru cele cu mesaje
 * plecate: unul cu prea puțini oameni pe listă nu trimite nimic, DAR își pune
 * ștampila și nu mai apare niciodată. Tăcut, ar fi fost tocmai felul de
 * întâmplare despre care afli peste o lună.
 */
sAIntamplatCeva();

/**
 * Rândul de încheiere se scrie în log doar când chiar a plecat ceva.
 *
 * Altfel, un cron din oră în oră ar umple fișierul cu 8760 de rânduri pe an
 * care spun „n-am avut ce face" — iar când s-ar întâmpla ceva, s-ar pierde
 * printre ele. Aceeași socoteală ca la cron/anonimizeaza-conturi.php.
 */
if ($trimise > 0 || $picate > 0) {
    scrieInLogulMultumirilor(
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
