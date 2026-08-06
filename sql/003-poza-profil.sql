-- =========================================================================
--  PulsulOrasului.Ro — poza de profil
--
--  Rulează după schema.sql:
--      mysql -u root -p pulsulorasului < sql/003-poza-profil.sql
--
--  Dacă pornești de la zero, schema.sql conține deja tot ce e aici.
-- =========================================================================

USE pulsulorasului;

-- -------------------------------------------------------------------------
--  poza — numele fișierului, fără cale și fără extensie
--
--  În bază se ține doar partea aleatorie a numelui, de forma
--  „9f3c1a7d2b8e4056a1c9f0d7e3b45286" (32 de caractere). Din ea se
--  construiesc, în PHP, cele două fișiere de pe disc:
--
--      assets/img/membri/9f3c…286.jpg       — 512 px, pentru pagina de profil
--      assets/img/membri/9f3c…286-mic.jpg   — 128 px, pentru comentarii
--
--  De ce un nume aleatoriu și nu „poza-membrului-17.jpg":
--
--  1. Nu se poate ghici. Cu id-ul membrului în nume, oricine ar putea cere
--     pe rând toate pozele de pe site, fără să treacă prin nicio pagină.
--  2. Se schimbă la fiecare încărcare, deci browserele nu mai servesc din
--     memoria proprie poza veche. Fără asta, cine își schimbă poza ar
--     continua să o vadă pe cea dinainte, uneori zile întregi.
--  3. Numele ales de utilizator nu ajunge niciodată pe disc — și bine face,
--     pentru că acolo poate fi orice, inclusiv „..\..\index.php".
--
--  NULL înseamnă „fără poză": se afișează silueta implicită.
-- -------------------------------------------------------------------------
ALTER TABLE membri
  ADD COLUMN poza VARCHAR(32) NULL DEFAULT NULL AFTER localitate;

-- Când a fost schimbată ultima dată. Ține la distanță trimiterile în rafală:
-- redimensionarea unei fotografii mari costă timp de procesor, iar fără o
-- pauză minimă cineva ar putea cere sute pe minut doar ca să încarce serverul.
ALTER TABLE membri
  ADD COLUMN poza_actualizata_la DATETIME NULL DEFAULT NULL AFTER poza;
