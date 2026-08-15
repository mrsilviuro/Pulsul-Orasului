<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — teancul următor de evenimente, pentru prima pagină.
 *
 * Singurul API de pe site care doar CITEȘTE. De aceea e și singurul care
 * primește GET, și singurul fără token CSRF: tokenul apără de fapte făcute în
 * numele cuiva fără voia lui, iar aici nu se face nimic. Nici cont nu cere —
 * lista de evenimente e publică, la fel ca prima pagină din care vine.
 *
 * Întoarce cartonașele gata desenate, nu date brute. Cine le cere le lipește
 * la coada grilei și atât; forma lor se scrie o singură dată, în
 * randeazaListaEvenimente(), și e aceeași cu a celor tipărite de index.php la
 * încărcare. Cu JSON de date, browserul ar fi trebuit să știe și el să
 * deseneze un cartonaș — adică a doua copie a aceleiași chestii, în alt
 * limbaj.
 *
 * Parametri, toți neobligatorii:
 *   oras      — unul din config.php; orice altceva înseamnă „toate"
 *   categorie — slugul unei categorii; la fel
 *   de_la     — de la al câtelea eveniment încolo (offset)
 */

require_once __DIR__ . '/../inc/evenimente.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    raspunsJson(['ok' => false, 'mesaj' => 'Metodă nepermisă.'], 405);
}

/**
 * Ce a cerut omul, trecut prin sita listelor noastre.
 *
 * Un oraș care nu e în config.php sau o categorie care nu e în bază nu sunt o
 * eroare: înseamnă „toate". O adresă veche, dintr-o zi în care exista alt
 * oraș, trebuie să arate prima pagină, nu un ecran de eroare.
 */
$oras      = orasulCerut($_GET['oras'] ?? null);
$categorie = categoriaCeruta($_GET['categorie'] ?? null);

$deLa = (int) ($_GET['de_la'] ?? 0);
$deLa = max(0, $deLa);

/**
 * Primul teanc e mai mare decât următoarele.
 *
 * Zece la început, ca pagina să aibă ce arăta, apoi patru la fiecare apăsare:
 * cine a ajuns la capătul listei caută ceva anume, iar patru cartonașe se
 * citesc dintr-o privire. Numerele stau în inc/evenimente.php, nu aici și nu
 * în JS — se schimbă într-un singur loc.
 *
 * Cât se cere hotărăște SERVERUL, după `de_la`, nu browserul. Altfel, o cerere
 * scrisă de mână ar fi putut cere zece mii deodată.
 */
$cate = $deLa > 0 ? EVENIMENTE_INCA : EVENIMENTE_PRIMA_TURA;

$rezultat = evenimenteDePePrima($oras, $categorie, $deLa, $cate);

raspunsJson([
    'ok'       => true,
    'html'     => randeazaListaEvenimente($rezultat['evenimente']),
    'cate'     => count($rezultat['evenimente']),
    'mai_sunt' => $rezultat['mai_sunt'],
]);
