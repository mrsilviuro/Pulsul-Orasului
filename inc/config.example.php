<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — setări locale.
 *
 * ACEST FIȘIER E DOAR UN MODEL. Copiază-l cu numele config.php:
 *
 *     copy inc\config.example.php inc\config.php      (Windows / XAMPP)
 *     cp   inc/config.example.php inc/config.php      (Linux, macOS)
 *
 * config.php e trecut în .gitignore, ca datele reale de acces să nu ajungă
 * niciodată pe GitHub.
 *
 * ATENȚIE LA URCAREA PE FTP: tocmai pentru că e în .gitignore, config.php NU
 * se descarcă odată cu restul codului. Trebuie făcut de mână pe server, cu
 * datele de acolo. Dacă lipsește, site-ul se oprește cu un mesaj care spune
 * exact asta.
 */

return [
    // ---------------------------------------------------------------- baza
    'db' => [
        /**
         * Aproape întotdeauna 'localhost', și în XAMPP, și pe găzduire.
         *
         * NU se pune aici numele domeniului. Baza de date stă pe aceeași
         * mașină cu site-ul, deci se ajunge la ea „din interior". Cu
         * domeniul, PHP ar ieși în internet și s-ar întoarce la server din
         * afară — lucru pe care aproape toate găzduirile îl blochează,
         * tocmai din motive de siguranță.
         *
         * Dacă găzduirea îți dă altceva (unele au un server separat, de
         * forma 'mysql.numefirma.ro'), pui exact ce scrie în panoul lor.
         */
        'host'  => 'localhost',

        // 3306 e portul obișnuit al MySQL. Se schimbă doar dacă găzduirea
        // spune explicit altul — și atunci scrie în panoul de control.
        'port'  => 3306,

        /**
         * Numele bazei, al utilizatorului și parola.
         *
         * În XAMPP: baza o creezi tu, utilizatorul e 'root' și parola goală.
         * Pe găzduire: toate trei se fac din panou, iar numele primesc automat
         * un prefix, de forma 'numecont_db' și 'numecont_utilizator'. Se pune
         * numele întreg, cu prefix cu tot.
         */
        'nume'  => 'pulsulor_db',
        'user'  => 'pulsulor_mrsilviu',
        'parola'=> 'Silviu0792RO',
    ],

    // --------------------------------------------------------------- site
    /**
     * Adresa de bază, folosită la construirea linkului de confirmare.
     *
     * În XAMPP, dacă proiectul stă în htdocs/pulsulorasului:
     *     'http://localhost/pulsulorasului'
     * Pe găzduire, cu certificat SSL pornit:
     *     'https://pulsulorasului.ro'
     *
     * Fără bară la sfârșit.
     */
    'url_site' => 'https://pulsulorasului.ro',

    // Fusul orar al aplicației. Toate momentele — înregistrare, blocare,
    // expirarea linkurilor — se calculează după el.
    'fus_orar' => 'Europe/Bucharest',

    // ----------------------------------------------------------- dezvoltare
    /**
     * Cât timp e true:
     *   - erorile PHP se văd în pagină, nu doar în log;
     *   - linkul de confirmare e întors în răspuns, ca să poți încheia
     *     înregistrarea fără server de e-mail (util în XAMPP).
     *
     * PE SITE-UL PUBLIC TREBUIE PUS PE false. Altfel oricine poate cere o
     * înregistrare și primește înapoi linkul de confirmare, deci poate
     * activa conturi pe adrese care nu sunt ale lui.
     */
    'dezvoltare' => false,

    // -------------------------------------------------------------- limite
    // Câte conturi pot fi create de la aceeași adresă IP într-o oră.
    'inregistrari_pe_ora' => 5,

    // Câte ore e valabil linkul de confirmare.
    'ore_valabilitate_token' => 48,

    // ------------------------------------------------------------ e-mail
    /**
     * De pe ce adresă pleacă mesajele.
     *
     * Trebuie să fie pe domeniul tău. Dacă pui aici o adresă de gmail.com,
     * mesajele ajung aproape sigur în „Spam": serverul care le trimite nu are
     * voie să trimită în numele Gmail, iar verificarea SPF observă asta.
     */
    'email_expeditor' => 'noreply@pulsulorasului.ro',
    'email_nume'      => 'PulsulOrasului.Ro',

    // Unde ajung răspunsurile, dacă cineva apasă „Reply".
    'email_raspuns'   => 'contact@pulsulorasului.ro',

    /**
     * Cum pleacă mesajele:
     *
     *   'auto'    — mail() pe site-ul public, fișier în dezvoltare (implicit)
     *   'mail'    — mereu prin funcția mail() a serverului
     *   'fisier'  — nu se trimite nimic; mesajele se scriu în
     *               private/emailuri-trimise.log, iar ultimul și în
     *               private/ultimul-email.html, ca să-i poți vedea aspectul
     *
     * În XAMPP nu există server de e-mail, de aceea 'auto' scrie în fișier.
     */
    'email_metoda' => 'auto',

    // ------------------------------------------------------------ Google
    /**
     * Datele aplicației din Google Cloud Console.
     *
     * Cât timp sunt goale, butoanele „Continuă cu Google" nici nu se
     * tipăresc în pagină, iar site-ul merge normal fără ele.
     *
     * Pașii de urmat la Google sunt scriși pe larg în README, la secțiunea
     * „Intrarea cu Google". Pe scurt: faci un proiect, completezi ecranul de
     * acceptare, ceri „OAuth client ID" de tip „Web application" și treci la
     * „Authorized redirect URIs" exact adresa:
     *
     *     https://pulsulorasului.ro/google.php
     *
     * SECRETUL NU SE PUNE NICIODATĂ ÎN JAVASCRIPT sau în vreo pagină. Stă
     * doar aici, iar fișierul ăsta nu se vede din web.
     */
    'google_client_id'     => '',
    'google_client_secret' => '',

    /**
     * Adresele Google. NU LE SCHIMBA.
     *
     * Sunt aici doar ca fluxul să poată fi verificat automat, cu un server
     * care ține locul lui Google. Lăsate goale, se folosesc cele adevărate,
     * scrise în inc/google.php.
     */
    // 'google_url_autorizare' => 'https://accounts.google.com/o/oauth2/v2/auth',
    // 'google_url_token'      => 'https://oauth2.googleapis.com/token',

    // ---------------------------------------------------------- diagnostic
    /**
     * Cheia pentru verifica.php, pagina care spune ce nu merge pe server.
     *
     * Lasă-o goală cât timp n-ai nevoie de ea: fără cheie, pagina refuză să
     * spună ceva. Când vrei să o folosești, pui aici un șir lung și
     * întâmplător (nu un cuvânt), deschizi
     *
     *     https://site-ul-tau.ro/verifica.php?cheie=CHEIA_DE_AICI
     *
     * și ȘTERGI fișierul de pe server după ce ai terminat.
     */
    'cheie_diagnostic' => '',
];
