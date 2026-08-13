-- =========================================================================
--  PulsulOrasului.Ro — încheierea unui eveniment, înainte de vreme
--
--  Rulează după sql/013-interese-evenimente.sql. În phpMyAdmin: alege întâi
--  baza din stânga, apoi „Import".
--
--  Până acum, un eveniment se încheia singur: a doua zi după data lui. E bine
--  pentru cele care se petrec și gata, dar nu și pentru cele care se termină
--  mai devreme — s-au ocupat locurile, s-a stricat vremea la jumătate, s-a
--  strâns lumea și nu mai are rost să se înscrie nimeni. De acum organizatorul
--  o poate spune el, când se întâmplă.
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  stare_moderare: încă o valoare, „incheiat"
--
--  LA COADA listei, ca la 011. În MySQL un ENUM se ține pe disc ca numărul
--  poziției, nu ca text: o valoare strecurată la mijloc ar renumerota tot ce e
--  după ea și ar preface în tăcere fiecare rând existent în altceva.
--
--  „incheiat" nu e o formă de „anulat". Anulat înseamnă „nu a mai avut loc";
--  încheiat înseamnă „a avut loc, s-a terminat". De aceea unul se ascunde de
--  toată lumea, iar celălalt rămâne o pagină publică, de citit și de trimis
--  mai departe — se stinge doar ce se poate face pe ea.
--
--  Un eveniment e încheiat dacă i-a trecut ziua SAU dacă are starea asta. Cele
--  două se socotesc împreună, la fiecare citire, fără cron — vezi
--  evenimentIncheiat() și filtruNeincheiat() din inc/evenimente.php.
-- -------------------------------------------------------------------------
ALTER TABLE evenimente
  MODIFY COLUMN stare_moderare
    ENUM('in_asteptare','aprobat','respins','anulat','incheiat')
    NOT NULL DEFAULT 'in_asteptare';
