<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — harta site-ului, pentru motoarele de căutare.
 *
 * Se cere la `/sitemap.xml` (rescrisă din .htaccess), dar merge și de-a
 * dreptul, la `/sitemap.php`, dacă găzduirea n-are mod_rewrite. Din robots.txt
 * se arată spre cea dintâi.
 *
 * SE SCRIE DIN BAZĂ, LA FIECARE CERERE. Un fișier făcut o dată și lăsat acolo
 * ar fi rămas în urmă la primul eveniment nou — iar o hartă care minte e mai
 * rea decât niciuna: Google se duce după ea, găsește pagini care nu mai există,
 * și învață să n-o mai citească.
 *
 * CE INTRĂ: paginile care se văd de oricine și evenimentele publice. CE NU
 * INTRĂ: tot ce are `$noindex` (formularul de publicare, zona de admin,
 * intrarea în cont, „FindMe"), fiindcă o hartă care trimite spre pagini
 * închise se contrazice cu ea însăși. Și PROFILURILE — vezi robots.txt.
 *
 * FĂRĂ inc/antet.php: aici nu iese HTML, iese XML, iar antetul ar fi scris un
 * `<!DOCTYPE html>` peste el.
 */

require_once __DIR__ . '/inc/evenimente.php';

/**
 * Cu site-ul închis, harta n-are ce arăta.
 *
 * Fără oprirea asta, un robot care trece în timpul lucrărilor ar fi luat lista
 * întreagă de adrese și s-ar fi dus, pe rând, la fiecare — găsind afișul de
 * șantier peste tot. Ar fi ținut minte că site-ul e gol tocmai în ziua în care
 * nu trebuia.
 */
if (siteInConstructie()) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Retry-After: 86400');
    exit("Se lucrează la site. Revino mai târziu.\n");
}

/**
 * Ce pagini fixe intră, și cât de des au rost să fie recitite.
 *
 * `changefreq` și `priority` sunt sfaturi, nu porunci — Google le citește cum
 * vrea el. Se scriu totuși, fiindcă nu costă nimic și spun adevărul: prima
 * pagină se schimbă zilnic, „Despre" o dată pe an.
 */
$paginiFixe = [
    ['cale' => '/',           'freq' => 'daily',   'prio' => '1.0'],
    ['cale' => 'despre.php',  'freq' => 'yearly',  'prio' => '0.5'],
    ['cale' => 'contact.php', 'freq' => 'yearly',  'prio' => '0.5'],
];

/**
 * Evenimentele publice — ACELEAȘI TREI STĂRI ca pe prima pagină.
 *
 * „anulat" inclus, dinadins: pagina lui rămâne pe site, cu motivul scris de
 * organizator, tocmai ca oamenii care își făcuseră planuri să afle ce s-a
 * întâmplat. Ea trebuie găsită, nu ascunsă.
 *
 * `actualizat_la` e ce se scrie ca `lastmod`: e clipa în care s-a schimbat
 * ultima oară CEVA din anunț. Nu `data_eveniment`, care e cu totul altceva —
 * ziua în care are loc, adesea în viitor.
 */
$q = db()->query(
    'SELECT slug, actualizat_la, creat_la, data_eveniment
       FROM evenimente
      WHERE stare_moderare IN (\'aprobat\', \'incheiat\', \'anulat\')
      ORDER BY data_eveniment DESC, id DESC
      LIMIT 40000'
);

header('Content-Type: application/xml; charset=utf-8');

/**
 * Nu se ține minte în browser. Harta se schimbă la fiecare eveniment nou, iar
 * un robot care ar primi-o din memorie ar fi citit una veche.
 */
header('Cache-Control: no-cache');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

/** Un rând de hartă. Adresa e mereu ÎNTREAGĂ — protocolul cere asta. */
$rand = static function (string $cale, ?string $cand, string $freq, string $prio): void {
    echo '  <url>' . "\n"
       . '    <loc>' . h(urlIntreg($cale)) . '</loc>' . "\n";

    $clipa = $cand === null || $cand === '' ? false : strtotime($cand);

    if ($clipa !== false) {
        echo '    <lastmod>' . date('Y-m-d', $clipa) . '</lastmod>' . "\n";
    }

    echo '    <changefreq>' . $freq . '</changefreq>' . "\n"
       . '    <priority>' . $prio . '</priority>' . "\n"
       . '  </url>' . "\n";
};

/**
 * Prima pagină poartă drept `lastmod` ziua celui mai proaspăt eveniment: ea se
 * schimbă exact atunci, nu la altă oră.
 */
$celMaiNou = db()->query(
    'SELECT MAX(actualizat_la) FROM evenimente
      WHERE stare_moderare IN (\'aprobat\', \'incheiat\', \'anulat\')'
)->fetchColumn();

foreach ($paginiFixe as $p) {
    $rand($p['cale'], $p['cale'] === '/' ? ($celMaiNou ?: null) : null, $p['freq'], $p['prio']);
}

$azi = date('Y-m-d');

foreach ($q as $ev) {
    /**
     * Un anunț care încă urmează se mai schimbă (se înscriu oameni, se
     * comentează); unul trecut nu se mai atinge niciodată. De aceea al doilea
     * primește „monthly" și o însemnătate mai mică — nu ca să fie pedepsit, ci
     * ca robotul să-și cheltuiască trecerile pe ce e viu.
     */
    $aTrecut = (string) $ev['data_eveniment'] < $azi;

    $rand(
        urlEveniment((string) $ev['slug']),
        (string) ($ev['actualizat_la'] ?: $ev['creat_la']),
        $aTrecut ? 'monthly' : 'daily',
        $aTrecut ? '0.4' : '0.8'
    );
}

echo '</urlset>' . "\n";
