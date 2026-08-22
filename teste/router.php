<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — serverul de probă, cu adresele frumoase.
 *
 * Pe găzduire, `/eveniment/<slug>` ajunge la `event.php` fiindcă `.htaccess`
 * o rescrie (mod_rewrite). Serverul din PHP nu citește `.htaccess` deloc, deci
 * fără fișierul ăsta adresele frumoase dau 404 în dezvoltare — și n-ar avea
 * cum să fie probate.
 *
 * Se pornește așa, din rădăcina site-ului:
 *
 *     php -S 127.0.0.1:8099 teste/router.php
 *
 * și de atunci merge tot, exact ca pe server. Fără el, `php -S 127.0.0.1:8099`
 * merge mai departe pentru toate celelalte pagini.
 *
 * ATENȚIE: `.htaccess` rămâne locul adevărat al regulilor. Aici sunt scrise a
 * doua oară DOAR cele două rescrieri de care atârnă adrese — nu https, nu
 * împachetarea, nu memoria browserului, fiindcă niciuna dintre alea nu schimbă
 * ce vede codul. Când se adaugă o rescriere nouă acolo, se adaugă și aici,
 * altfel proba trece pe un site care nu e cel de pe server.
 */

$cale = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

/* /eveniment/<slug>  →  event.php?slug=<slug> */
if (preg_match('#^/eveniment/([a-z0-9][a-z0-9-]*)/?$#', $cale, $m) === 1) {
    // `slug` intră în $_GET, iar restul adresei rămâne cum era: QSA din
    // .htaccess face exact asta.
    $_GET['slug'] = $m[1];
    $_SERVER['SCRIPT_NAME']     = '/event.php';
    $_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__) . '/event.php';

    require dirname(__DIR__) . '/event.php';
    return true;
}

/* /sitemap.xml  →  sitemap.php */
if ($cale === '/sitemap.xml') {
    $_SERVER['SCRIPT_NAME']     = '/sitemap.php';
    $_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__) . '/sitemap.php';

    require dirname(__DIR__) . '/sitemap.php';
    return true;
}

/* Orice altceva: serverul se descurcă singur. */
return false;
