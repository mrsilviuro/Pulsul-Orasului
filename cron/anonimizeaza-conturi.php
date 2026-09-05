<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — anonimizarea conturilor cărora li s-a împlinit răgazul.
 *
 * Se rulează o dată pe zi. Caută conturile care au cerut ștergerea acum mai
 * bine de treizeci de zile și nu au fost readuse la viață între timp printr-o
 * intrare în cont, apoi le șterge omul din ele — dar le păstrează rândul.
 *
 * DE MÂNĂ, pentru încercare:
 *     php cron/anonimizeaza-conturi.php
 *     php cron/anonimizeaza-conturi.php --uscat     (arată, nu schimbă nimic)
 *
 * DIN CRON (cPanel → Cron Jobs), o dată pe zi, noaptea:
 *     php /home/UTILIZATOR/public_html/cron/anonimizeaza-conturi.php
 *
 * Nu e nevoie de nicio grabă cu ora: dacă cronul nu rulează o zi, conturile
 * așteaptă cuminți și se anonimizează a doua zi. Nimic nu se pierde.
 */

/* --------------------------- Doar din consolă -------------------------- */

/**
 * Scriptul stă sub rădăcina site-ului, deci ar putea fi deschis din browser.
 * Nu trebuie să se poată: oricine ar putea grăbi ștergeri.
 *
 * .htaccess din cron/ oprește accesul, dar verificarea asta nu costă nimic și
 * ține și acolo unde .htaccess nu e citit (nginx, de pildă).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Se rulează doar din linia de comandă.\n");
}

require_once __DIR__ . '/../inc/stergere.php';
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

spune('[' . date('Y-m-d H:i:s') . '] Caut conturi cu răgazul împlinit…');

if ($uscat) {
    $limita = date('Y-m-d H:i:s', $inceput - ZILE_RAGAZ_STERGERE * 24 * 3600);

    $q = db()->prepare(
        'SELECT id, cerere_stergere
           FROM membri
          WHERE cerere_stergere IS NOT NULL
            AND cerere_stergere <= ?
            AND stare <> \'sters\'
          ORDER BY cerere_stergere'
    );
    $q->execute([$limita]);
    $gasite = $q->fetchAll();

    foreach ($gasite as $m) {
        spune('  ar fi anonimizat: membrul #' . $m['id']
            . ' (a cerut pe ' . $m['cerere_stergere'] . ')');
    }

    spune('Încercare uscată: ' . count($gasite) . ' de conturi, nimic schimbat.');
    exit(0);
}

$facute = anonimizeazaConturileExpirate($inceput);

$reusite = 0;
$picate  = 0;

foreach ($facute as $f) {
    if ($f['reusit']) {
        $reusite++;
        scrieInLogulStergerii(
            'anonimizat membrul #' . $f['id'] . ', cerut pe ' . $f['cerut']
        );
        spune('  anonimizat: membrul #' . $f['id']);
    } else {
        $picate++;
        scrieInLogulStergerii('NU AM PUTUT anonimiza membrul #' . $f['id']);
        spune('  PICAT: membrul #' . $f['id']);
    }
}

/**
 * Rândul de încheiere se scrie în log doar când chiar a fost ceva de făcut.
 *
 * Altfel, un cron zilnic ar umple fișierul cu 365 de rânduri pe an care spun
 * „n-am avut ce face" — iar când s-ar întâmpla ceva, s-ar pierde printre ele.
 */
if ($facute !== []) {
    /* Un cont anonimizat e o schimbare pe care nu o mai poate lua nimeni
       înapoi. Rularea asta merită un mesaj. */
    sAIntamplatCeva();

    scrieInLogulStergerii(
        'gata: ' . $reusite . ' anonimizate, ' . $picate . ' picate, '
        . 'în ' . (time() - $inceput) . 's'
    );
}

spune('[' . date('Y-m-d H:i:s') . '] Gata: ' . $reusite . ' anonimizate, '
    . $picate . ' picate.');

exit($picate > 0 ? 1 : 0);
