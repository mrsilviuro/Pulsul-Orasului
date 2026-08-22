-- =========================================================================
--  PulsulOrasului.Ro — înștiințarea pe e-mail când cineva îți scrie o părere
--
--  Rulează după sql/026-corectura-eveniment.sql. În phpMyAdmin: alege întâi
--  baza din stânga, apoi „Import".
--
--  Până acum, cine primea o părere scrisă pe profil nu afla decât dacă
--  intra singur acolo și se uita la listă. De aici încolo primește un e-mail
--  — dacă vrea.
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  Vrea omul să afle pe e-mail când cineva îi lasă o părere SCRISĂ?
--
--  PORNITĂ pentru toată lumea, și pentru conturile care există deja: NOT NULL
--  cu DEFAULT 1 umple din start rândurile vechi cu 1. Aceeași alegere ca la
--  `email_comentarii`, și din același motiv: nu e o reclamă, e ceva ce a
--  scris un om anume despre tine.
--
--  DOAR PENTRU CE E SCRIS. Stelele rămân anonime și tăcute — vezi
--  inc/evaluari.php. O înștiințare la fiecare stea apăsată ar fi însemnat
--  cinci mesaje după o ieșire cu cinci oameni, fiecare spunând „cineva
--  te-a notat, nu-ți spunem cine, nu-ți spunem cât". Ar fi fost, în cel mai
--  bun caz, nefolositor; în cel mai rău, apăsător.
--
--  DE CE O COLOANĂ NOUĂ, ȘI NU `email_comentarii`
--
--  Fiindcă sunt două lucruri deosebite. Un comentariu e o vorbă sub un anunț,
--  la vedere, într-o discuție. O părere scrisă e ceva despre TINE, pus pe
--  profilul tău, unde rămâne. Cine stinge zgomotul discuțiilor nu cere prin
--  asta să nu mai afle ce se scrie despre el.
--
--  Lângă `email_comentarii` (AFTER), fiindcă amândouă răspund la aceeași
--  întrebare — ce e-mailuri vrea omul — și se citesc în aceeași cerere din
--  setări.
-- -------------------------------------------------------------------------
ALTER TABLE membri
  ADD COLUMN email_feedback TINYINT(1) NOT NULL DEFAULT 1 AFTER email_comentarii;
