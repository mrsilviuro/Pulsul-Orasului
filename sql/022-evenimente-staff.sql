-- =========================================================================
--  PulsulOrasului.Ro — anunțul ținut deoparte de profilul organizatorului
--
--  Rulează după sql/021-instiintari-comentarii.sql. În phpMyAdmin: alege
--  întâi baza din stânga, apoi „Import".
--
--  Vine odată cu publicarea directă de către staff: ce pune omul de casă e
--  de multe ori un anunț al orașului, nu o ieșire de-a lui, și n-are ce
--  căuta pe profilul lui personal, la „Ieșiri organizate".
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  ascuns_pe_profil
--
--  STINSĂ pentru toată lumea, și pentru evenimentele care există deja: NOT
--  NULL cu DEFAULT 0 umple din start rândurile vechi cu 0. Purtarea de până
--  acum rămâne purtarea implicită — un eveniment apare pe profilul celui
--  care l-a pus, cum a apărut mereu.
--
--  CE ASCUNDE, ȘI CE NU
--
--  Ascunde DOAR de pe profilul organizatorului: lista „Ieșiri organizate",
--  cifra „Evenimente organizate" de deasupra ei și tabul „Istoric" — dar
--  acolo numai pentru el, fiindcă pentru oricine altcineva evenimentul e o
--  seară adevărată la care chiar a fost.
--
--  NU ascunde nimic altundeva. Anunțul rămâne întreg pe prima pagină, în
--  filtre, în „Ar putea să te intereseze" și pe pagina lui, cu numele
--  organizatorului scris la vedere. Nu e o coloană de anonimat — e o coloană
--  care spune „ăsta nu e o ieșire de-a mea".
--
--  DE CE O COLOANĂ PE EVENIMENT, ȘI NU UN STEAG PE CONT
--
--  Fiindcă alegerea se face de fiecare dată, pentru un anunț anume. Același
--  om de casă pune și evenimente ale orașului (ascunse), și ieșiri de-ale
--  lui (la vedere). Un steag pe cont ar fi cerut o hotărâre o dată pentru
--  totdeauna, tocmai acolo unde ea se schimbă de la un anunț la altul.
--
--  Lângă `stare_moderare` (AFTER), fiindcă amândouă răspund la aceeași
--  întrebare — cui i se arată anunțul — și se citesc în aceleași cereri.
-- -------------------------------------------------------------------------
ALTER TABLE evenimente
  ADD COLUMN ascuns_pe_profil TINYINT(1) NOT NULL DEFAULT 0 AFTER stare_moderare;
