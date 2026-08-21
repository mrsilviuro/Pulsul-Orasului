-- =========================================================================
--  PulsulOrasului.Ro — semnul că unui anunț i s-a cerut o îndreptare
--
--  Rulează după sql/025-coduri-qr.sql. În phpMyAdmin: alege întâi baza din
--  stânga, apoi „Import".
--
--  DE CE
--
--  Când staff-ul respinge un anunț cu bifa „editare necesară", starea NU se
--  face „respins": rămâne „in_asteptare", fiindcă anunțul e viu și îl așteaptă
--  pe organizator să-l îndrepte. Bun pentru organizator — dar în lista de
--  administrare, anunțul acela arăta exact ca unul care n-a fost citit
--  niciodată de nimeni.
--
--  Așa se ajungea să fie citit a doua oară degeaba, sau — mai rău — să fie
--  lăsat deoparte („l-am mai văzut"), deși omul îl schimbase de mult.
--
--  Coloana asta e semnul. Se pune la „editare necesară" și SE ȘTERGE LA PRIMA
--  SCHIMBARE făcută de organizator (actualizeazaEveniment). Adică:
--
--    are ștampilă  → i s-a cerut ceva și încă n-a atins anunțul
--    n-are         → ori n-a fost citit încă, ori a fost îndreptat între timp
--
--  DE CE O DATĂ, ȘI NU UN 0/1. Cu un steag s-ar fi știut doar „da/nu"; cu data
--  se vede și de când așteaptă. La un anunț cerut spre îndreptare acum trei
--  săptămâni, asta e chiar vestea.
-- =========================================================================

SET NAMES utf8mb4;

ALTER TABLE evenimente
  ADD COLUMN corectura_ceruta_la DATETIME NULL DEFAULT NULL AFTER stare_moderare;
