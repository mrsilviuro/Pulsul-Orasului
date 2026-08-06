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
        'nume'  => 'pulsulorasului',
        'user'  => 'root',
        'parola'=> '',
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
    'url_site' => 'http://localhost/pulsulorasului',

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
    'dezvoltare' => true,

    // -------------------------------------------------------------- limite
    // Câte conturi pot fi create de la aceeași adresă IP într-o oră.
    'inregistrari_pe_ora' => 5,

    // Câte ore e valabil linkul de confirmare.
    'ore_valabilitate_token' => 48,

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
