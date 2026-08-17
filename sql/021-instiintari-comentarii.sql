-- =========================================================================
--  PulsulOrasului.Ro — înștiințarea pe e-mail când cineva scrie sub anunț
--
--  Rulează după sql/020-rapoarte-comentarii.sql. În phpMyAdmin: alege întâi
--  baza din stânga, apoi „Import".
--
--  Până acum, cine primea un răspuns la comentariu nu afla decât dacă se
--  întorcea singur pe pagină. De aici încolo primește un e-mail — dacă vrea.
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  Vrea omul să afle pe e-mail când i se scrie?
--
--  PORNITĂ pentru toată lumea, și pentru conturile care există deja: NOT NULL
--  cu DEFAULT 1 umple din start rândurile vechi cu 1. E alegerea bună aici,
--  spre deosebire de o listă de reclame: mesajul ăsta e răspunsul cuiva la
--  vorbele TALE, adică exact lucrul pentru care ai scris. Cine nu-l vrea îl
--  stinge dintr-o bifă, în setări.
--
--  DE CE O COLOANĂ NOUĂ, ȘI NU BIFA DE NEWSLETTER
--
--  Fiindcă sunt două lucruri deosebite. `newsletter` înseamnă „trimiteți-mi
--  ce se mai întâmplă prin oraș" — ceva ce n-am cerut anume. Asta înseamnă
--  „spuneți-mi când cineva îmi răspunde mie". Cine stinge reclamele nu
--  cere prin asta să i se ascundă și răspunsurile la propriile vorbe.
--
--  Lângă `newsletter` (AFTER), fiindcă amândouă răspund la aceeași întrebare
--  — ce e-mailuri vrea omul — și se citesc în aceeași cerere din setări.
-- -------------------------------------------------------------------------
ALTER TABLE membri
  ADD COLUMN email_comentarii TINYINT(1) NOT NULL DEFAULT 1 AFTER newsletter;
