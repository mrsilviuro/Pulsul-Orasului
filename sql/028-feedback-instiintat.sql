-- =========================================================================
--  PulsulOrasului.Ro — semnul că vestea unei păreri a plecat o dată
--
--  Rulează după sql/027-instiintari-feedback.sql. În phpMyAdmin: alege întâi
--  baza din stânga, apoi „Import".
--
--  DE CE
--
--  Vestea „X ți-a lăsat un feedback" pleca la fiecare text NOU SAU SCHIMBAT.
--  Suna cuminte, dar în viață un om își îndreaptă vorbele: scrie în grabă,
--  vede o greșeală, se răzgândește asupra unui cuvânt. Zece îndreptări
--  însemnau zece e-mailuri despre ACEEAȘI părere — iar cel care le primea
--  învăța, pe bună dreptate, să nu le mai deschidă.
--
--  Coloana asta e ștampila: se pune în clipa în care a plecat vestea și nu se
--  mai șterge niciodată. Cât timp e pusă, nu mai pleacă nimic pentru perechea
--  aceea de oameni la evenimentul acela — oricâte îndreptări ar urma.
--
--  DE CE PE RÂNDUL EVALUĂRII, ȘI NU UNDEVA GLOBAL
--
--  Fiindcă exact acolo e unicitatea care ne trebuie: un rând din `evaluari`
--  ÎNSEAMNĂ „părerea lui X despre Y, la evenimentul Z" (are index unic pe cele
--  trei). O socoteală ținută în altă parte ar fi trebuit să refacă tocmai
--  cheia asta, cu mâna.
--
--  DE CE O DATĂ, ȘI NU UN 0/1. Cu un steag s-ar ști doar „da/nu"; cu data se
--  vede și CÂND a aflat omul — de folos când cineva scrie că n-a primit nimic.
--
--  RÂNDURILE DE PÂNĂ ACUM RĂMÂN NULL, adică „încă n-a plecat nicio veste". E
--  alegerea bună: cele mai multe păreri vechi n-au vestit pe nimeni, fiindcă
--  vestea abia ce a fost scrisă. Cel mult, cineva primește o dată un mesaj
--  despre o părere pe care o are deja pe profil — o supărare de o singură dată,
--  față de a stinge din start ceva ce n-a fost niciodată aprins.
-- =========================================================================

SET NAMES utf8mb4;

ALTER TABLE evaluari
  ADD COLUMN instiintat_la DATETIME NULL DEFAULT NULL AFTER automat;
