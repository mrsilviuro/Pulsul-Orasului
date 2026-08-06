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
 */

return [
    // ---------------------------------------------------------------- baza
    // Valorile implicite din XAMPP: utilizator „root", fără parolă.
    // Pe găzduirea reală se schimbă toate trei.
    'db' => [
        'host'  => '127.0.0.1',
        'port'  => 3306,
        'nume'  => 'pulsulorasului',
        'user'  => 'root',
        'parola'=> '',
    ],

    // --------------------------------------------------------------- site
    // Adresa de bază, folosită la construirea linkului de confirmare.
    // În XAMPP, dacă proiectul stă în htdocs/pulsulorasului, valoarea e
    // 'http://localhost/pulsulorasului'.
    'url_site' => 'http://localhost/pulsulorasului',

    // ----------------------------------------------------------- dezvoltare
    // Cât timp e true:
    //   - erorile PHP se văd în pagină, nu doar în log;
    //   - linkul de confirmare e întors în răspuns, ca să poți încheia
    //     înregistrarea fără server de e-mail (util în XAMPP).
    // PE SITE-UL PUBLIC TREBUIE PUS PE false.
    'dezvoltare' => true,

    // -------------------------------------------------------------- limite
    // Câte conturi pot fi create de la aceeași adresă IP într-o oră.
    'inregistrari_pe_ora' => 5,

    // Câte ore e valabil linkul de confirmare.
    'ore_valabilitate_token' => 48,
];
