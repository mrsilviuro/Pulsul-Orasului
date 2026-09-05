<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — gura cronurilor: vorbesc numai când au ce spune.
 *
 * DE CE EXISTĂ. Un cron nu trimite el e-mailuri despre sine; cron-ul găzduirii
 * ia CE SCRIE SCRIPTUL PE ECRAN și-l pune într-un mesaj către stăpânul casei.
 * Deci fiecare `echo` dintr-un cron e un e-mail. Cu unul din oră în oră, un
 * rând vesel de forma „caut… nimic de trimis" înseamnă douăzeci și patru de
 * mesaje pe zi, opt mii șapte sute pe an, toate spunând că nu s-a întâmplat
 * nimic — iar singurul care chiar avea ceva de spus se pierde între ele. Omul
 * învață în două săptămâni să le șteargă fără să le citească, și de-atunci
 * cronul poate să tacă stricat luni în șir.
 *
 * CUM SE FACE. Vorba se strânge în gură (spune) și se rostește la sfârșit doar
 * dacă rularea a avut ce spune (sAIntamplatCeva). Altfel se înghite, și
 * cron-ul găzduirii n-are ce trimite: un script care nu scrie nimic nu naște
 * niciun e-mail.
 *
 * NU E O TĂCERE OARBĂ, și asta e partea care contează: ce s-a făcut cu adevărat
 * se scrie mai departe în loguri (private/*.log), la fiecare rulare, fie că
 * pleacă vreun e-mail, fie că nu. Aici se hotărăște doar dacă te CAUTĂ cineva
 * pe tine, nu dacă se ține socoteala.
 *
 * DE MÂNĂ SE AUDE TOT. Când scriptul e pornit de un om, dintr-un terminal,
 * tăcerea ar fi de-a dreptul rea: dai comanda și nu se întâmplă nimic pe ecran.
 * De aceea seAudeCronul() întreabă dacă la celălalt capăt e un om
 * (stream_isatty) și, dacă da, vorbește oricum. Tot așa la `--uscat`: acolo
 * omul a cerut anume să vadă.
 */

/* ------------------------------ Gura --------------------------------- */

/** Ce s-a strâns, cât încă nu se știe dacă merită spus. */
$poVorbaCronului = '';

/** S-a întâmplat ceva? Atunci se rostește tot. */
$poAreCeSpune = false;

/**
 * Un rând de spus — mai târziu, dacă rularea se dovedește a avea ce spune.
 *
 * Ia locul lui `echo`. Rândul nou de la capăt se pune aici, o dată, ca să nu
 * fie uitat prin locuri.
 */
function spune(string $rand): void
{
    global $poVorbaCronului;

    $poVorbaCronului .= $rand . "\n";
}

/**
 * S-a întâmplat ceva care merită un mesaj: au plecat mesaje, s-au anonimizat
 * conturi, a picat ceva. De acum, tot ce s-a strâns chiar se rostește.
 *
 * SE CHEAMĂ PENTRU CE S-A FĂCUT ȘI PENTRU CE S-A STRICAT, niciodată pentru ce
 * s-a căutat. „Am căutat și n-am găsit nimic" e chiar rândul din pricina căruia
 * există fișierul ăsta.
 */
function sAIntamplatCeva(): void
{
    global $poAreCeSpune;

    $poAreCeSpune = true;
}

/**
 * La celălalt capăt e un om, nu un e-mail?
 *
 * `stream_isatty` spune dacă ieșirea e un terminal. Din cron nu e niciodată
 * (acolo e o țeavă către poștă), de la tastatură e mereu. Așa, aceeași comandă
 * scrisă de mână arată tot, iar din cron tace — fără nicio setare de pus și
 * fără să se schimbe rândul din cPanel.
 */
function seAudeCronul(): bool
{
    static $seAude = null;

    if ($seAude === null) {
        $seAude = function_exists('stream_isatty') && @stream_isatty(STDOUT);
    }

    return $seAude;
}

/** Pentru `--uscat`: omul a cerut anume să vadă, deci se vorbește oricum. */
function vorbesteOricum(): void
{
    sAIntamplatCeva();
}

/**
 * Rostirea, la sfârșit.
 *
 * Prin register_shutdown_function, ca să se întâmple și după un `exit()` din
 * mijlocul scriptului — ramura „nimic de făcut" iese chiar de acolo, iar o
 * golire scrisă de mână la fiecare capăt ar fi fost uitată la primul capăt nou.
 */
register_shutdown_function(static function (): void {
    global $poVorbaCronului, $poAreCeSpune;

    if ($poVorbaCronului === '') {
        return;
    }

    if ($poAreCeSpune || seAudeCronul()) {
        echo $poVorbaCronului;
    }

    $poVorbaCronului = '';
});
