-- =========================================================================
--  PulsulOrasului.Ro — scoaterea newsletterului zilnic
--
--  Rulează după sql/035-comentariu-important.sql. În phpMyAdmin: alege întâi
--  baza din stânga, apoi „Import".
--
--  DE CE
--
--  Newsletterul zilnic și anunțul scris de mână din admin au fost scoase de pe
--  site: găzduirea duce zece mesaje pe minut și șase sute pe ceas, iar cine
--  sare plafonul rămâne fără poștă pentru tot restul orei — cu tot cu
--  confirmările de cont și recuperările de parolă ale unor oameni care n-au
--  nicio treabă cu newsletterul. S-a ales să nu mai plece de pe site NICIUN
--  mesaj nechemat.
--
--  Odată cu ele au rămas fără rost cele două coloane de mai jos: bifa prin care
--  omul cerea newsletterul, și ștampila zilei în care i-a plecat.
--
--  DE CE SE ȘTERG, DACĂ PE SITE NU SE ȘTERGE NIMIC NICIODATĂ
--
--  Regula aceea („contul șters se anonimizează", „rândurile din `dorinte` rămân
--  pe veci") e despre CE A SCRIS OMUL: vorbele lui, pe care mai târziu vrem să
--  le putem număra sau arăta. Aici nu e nimic scris de nimeni — e o bifă pentru
--  o funcție care nu mai există. Lăsată acolo, ar fi fost mai rea decât lipsa
--  ei: o coloană numită `newsletter` pe `membri` îl face pe următorul care
--  deschide baza să creadă că există un newsletter.
--
--  NU E GRABĂ, ȘI CODUL NU O AȘTEAPTĂ. Site-ul merge întocmai și cu coloanele
--  încă acolo — nicio interogare nu le mai cere. Se poate rula oricând, sau
--  niciodată.
--
--  DACĂ VREI SĂ PĂSTREZI LISTA celor care ceruseră newsletterul — de pildă
--  fiindcă te-ai putea răzgândi — ia-o înainte de a rula fișierul ăsta:
--
--      SELECT id, email FROM membri WHERE newsletter = 1;
-- =========================================================================

SET NAMES utf8mb4;

-- Indexul întâi: el atârnă de amândouă coloanele, iar MySQL nu lasă să cadă o
-- coloană cât timp o cheie o cuprinde.
DROP INDEX idx_membri_newsletter ON membri;

ALTER TABLE membri
  DROP COLUMN newsletter_trimis_la,
  DROP COLUMN newsletter;

-- -------------------------------------------------------------------------
--  CE NU SE ATINGE: tabelul `abonati_newsletter` (sql/019).
--
--  Numele îl face să pară al aceleiași funcții, dar n-are nicio treabă cu ea:
--  acolo ajung adresele lăsate pe AFIȘUL DE ȘANTIER, de oameni care n-au cont
--  și vor să afle când deschidem. Formularul acela e viu mai departe (vezi
--  inscrieLaVesti din inc/constructie.php), iar lista e singurul fel de a-i
--  anunța pe cei care au trecut pe la ușă înainte s-o deschidem.
-- -------------------------------------------------------------------------
