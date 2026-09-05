<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — poștașul: duce ce e la rând în coada de e-mailuri.
 *
 * Ia cel mult COADA_PE_RULARE mesaje, le trimite unul după altul și se încheie.
 * Câteva secunde, de obicei zero mesaje și o singură interogare.
 *
 * DE MÂNĂ, pentru încercare:
 *     php cron/trimite-emailuri.php
 *     php cron/trimite-emailuri.php --vezi    (arată ce așteaptă, nu trimite)
 *
 * DIN CRON (cPanel → Cron Jobs), DIN MINUT ÎN MINUT:
 *     * * * * *  php /home/UTILIZATOR/public_html/cron/trimite-emailuri.php
 *
 * CADENȚA CRONULUI E PASUL, și de aceea nu se așteaptă nimic înăuntru. La opt
 * mesaje pe rulare și o pornire pe minut ies opt pe minut — sub cele zece pe
 * care le duce găzduirea, cu loc rămas pentru mesajele care pleacă pe loc
 * (confirmări de cont, recuperări de parolă).
 *
 * DACĂ GĂZDUIREA NU LASĂ CRON DIN MINUT ÎN MINUT — unele dau minim cinci —,
 * merge la fel de bine: pui `emailuri_pe_rulare` pe 40 și cronul la cinci
 * minute. Ritmul iese același; se schimbă doar cât așteaptă un mesaj până îi
 * vine rândul.
 *
 * NU E NEVOIE DE NICIUN LACĂT pe deasupra. Rândurile se iau cu un UPDATE care
 * își scrie cifra pe ele (vezi iaDinCoada), deci două rulări suprapuse nu pot
 * lua aceleași mesaje — a doua pur și simplu nu găsește nimic.
 */

/* --------------------------- Doar din consolă -------------------------- */

/**
 * Scriptul stă sub rădăcina site-ului, deci ar putea fi deschis din browser.
 * Nu trebuie să se poată: oricine ar putea goli coada într-un val de cereri.
 * .htaccess din cron/ îl închide oricum, dar verificarea de aici ține și acolo
 * unde .htaccess nu e citit (nginx, de pildă).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Se rulează doar din linia de comandă.\n");
}

require_once __DIR__ . '/../inc/coada.php';
require_once __DIR__ . '/vorba.php';   // cronul vorbește numai când are ce spune

$doarVad = in_array('--vezi', $argv ?? [], true);

/* ------------------------------ Treaba --------------------------------- */

$inceput   = time();
$asteapta  = cateAsteaptaInCoada();
$picate    = catePicateInCoada();

/**
 * „niciun mesaj", „un mesaj", „3 mesaje", „21 de mesaje".
 *
 * numaratoare() știe regula lui „de" (21 DE mesaje), dar nu și capetele:
 * „0 de mesaje" și „1 mesaje" sunt amândouă strâmbe.
 */
$cateMesaje = static function (int $cate): string {
    if ($cate === 0) { return 'niciun mesaj'; }
    if ($cate === 1) { return 'un mesaj'; }

    return numaratoare($cate, 'mesaje');
};

if ($doarVad) {
    vorbesteOricum();   // omul a cerut anume să vadă

    spune('[' . date('Y-m-d H:i:s') . '] Încercare uscată — nu pleacă nimic.');
    spune('    la rând:            ' . $cateMesaje($asteapta));
    spune('    plecate în ultimul ceas: ' . catePlecateInUltimulCeas());
    spune('    rămase pe drumuri:  ' . $picate);
    spune('    o rulare duce:      ' . coadaPeRulare());
    exit(0);
}

/**
 * NIMIC LA RÂND E CAZUL OBIȘNUIT, de vreo mie patru sute de ori pe zi.
 *
 * Se iese ÎNAINTE de orice altceva, ca rularea aceea să coste o singură
 * interogare indexată. Mai ales: NU se deschide nicio conexiune cu serverul de
 * poștă — poștașul din inc/posta.php se face abia la primul mesaj, deci un
 * cron care nu găsește nimic nu bate la ușa nimănui.
 */
if ($asteapta === 0) {
    /* Chiar și atunci se mai face curat: e singurul loc care o face, iar dacă
       s-ar face doar când e de trimis, o coadă liniștită n-ar fi măturată
       niciodată. */
    $sterse = curataCoada();

    if ($sterse > 0) {
        spune('[' . date('Y-m-d H:i:s') . '] Nimic la rând. Am șters '
            . $sterse . ' rânduri vechi.');
    }

    exit(0);
}

$r = trimiteDinCoada();

/* Conexiunea cu serverul de poștă a stat deschisă tot teancul. Aici s-a
   terminat treaba, deci se închide. */
inchidePostasul();

$sterse = curataCoada();

scrieInLogulCozii(
    $r['trimise'] . ' trimise, ' . $r['picate'] . ' picate, din '
    . $cateMesaje($asteapta) . ' la rând; ' . $sterse . ' rânduri vechi șterse, în '
    . (time() - $inceput) . 's'
);

spune('[' . date('Y-m-d H:i:s') . '] Gata: ' . $r['trimise'] . ' trimise, '
    . $r['picate'] . ' picate.');

/**
 * RĂMASELE PE DRUMURI SE SPUN PE FAȚĂ. Cifra asta ar trebui să fie zero; când
 * nu e, ceva e stricat de-a binelea — o parolă SMTP schimbată, plafonul sărit,
 * un domeniu care ne refuză — iar motivul stă scris în `coada_emailuri.eroare`.
 * Un cron care ar tăcea despre ele le-ar lăsa să se adune la nesfârșit.
 */
$picateAcum = catePicateInCoada();

/**
 * SINGURUL LUCRU DIN POȘTAȘ CARE MERITĂ UN E-MAIL: că a picat ceva ACUM.
 *
 * Restul — „am dus opt" — se scrie în coada.log la fiecare rulare și n-are de
 * ce să ajungă la nimeni: din minut în minut, un mesaj pe rulare ar însemna, la
 * un val de vești către urmăritori, o jumătate de ceas de e-mailuri despre
 * e-mailuri.
 *
 * SE ÎNTREABĂ DE RULAREA ASTA (`$r['picate']`), NU DE CÂTE ZAC ÎN COADĂ.
 * Rândurile rămase pe drumuri NU se șterg niciodată singure — sunt acolo tocmai
 * ca să se vadă —, deci „spune cât timp sunt" ar fi însemnat același e-mail
 * despre aceleași două mesaje, din minut în minut, până le-ar fi șters cineva.
 * Adică exact zgomotul din care s-a născut fișierul cron/vorba.php, doar că mai
 * des. Se spune o dată, la picare; de acolo încolo se văd în admin, la „Poșta".
 */
if ($r['picate'] > 0) {
    sAIntamplatCeva();

    spune('    ATENȚIE: ' . $cateMesaje($r['picate'])
        . ($r['picate'] === 1 ? ' n-a plecat' : ' n-au plecat') . ' la rularea asta.');
    /* Cele care ZAC în coadă sunt altă cifră: unul picat acum se mai încearcă
       de două ori, deci de obicei încă nu e printre ele. */
    if ($picateAcum > 0) {
        spune('    În coadă mai zac ' . $cateMesaje($picateAcum) . ', rămase pe drumuri'
            . ' după ' . COADA_INCERCARI_MAX . ' încercări.');
    }

    spune('    În admin, la „Poșta", scrie pe fiecare ce a răspuns serverul,');
    spune('    și se pot șterge de acolo.');
}

exit($r['picate'] > 0 ? 1 : 0);
