-- =========================================================================
--  PulsulOrasului.Ro — parola temporară, pentru cine și-a uitat parola
--
--  Rulează după schema.sql. În phpMyAdmin: alege întâi baza din stânga,
--  apoi „Import".
--
--  Dacă pornești de la zero, schema.sql conține deja tot ce e aici.
-- =========================================================================

-- -------------------------------------------------------------------------
--  parola_temporara_hash
--
--  Parola temporară se ține HASHUITĂ, exact ca parola adevărată — nu în clar,
--  deși e valabilă doar o oră.
--
--  Motivul: dacă baza de date ajunge vreodată pe mâini străine, o coloană cu
--  parole în clar înseamnă intrare imediată în toate conturile care au cerut
--  recuperare în ultima oră. Hashuită, nu înseamnă nimic. Costul e zero: tot
--  ce facem cu ea e s-o comparăm cu ce tastează omul, iar password_verify()
--  face fix asta.
-- -------------------------------------------------------------------------
ALTER TABLE membri
  ADD COLUMN parola_temporara_hash VARCHAR(255) NULL DEFAULT NULL AFTER parola_hash;

-- Când expiră. 60 de minute de la cerere.
ALTER TABLE membri
  ADD COLUMN parola_temporara_expira DATETIME NULL DEFAULT NULL AFTER parola_temporara_hash;

-- Când a fost cerută ultima dată. Ține la distanță cererile în lanț: cine
-- apasă de zece ori pe buton ar umple cutia poștală a altcuiva.
ALTER TABLE membri
  ADD COLUMN parola_temporara_ceruta_la DATETIME NULL DEFAULT NULL AFTER parola_temporara_expira;

-- -------------------------------------------------------------------------
--  parola_temporara_incercari
--
--  Câte încercări greșite s-au făcut cu ea. La a cincea, parola temporară se
--  șterge singură.
--
--  De ce nu ne bazăm doar pe cele 60 de minute: parola are șase caractere din
--  32 posibile, adică peste un miliard de combinații. Multe — dar limita de
--  timp singură nu spune nimic despre cât de repede poate cineva să încerce.
--  Un contor care stinge parola după cinci greșeli face ghicitul imposibil,
--  indiferent cât de multe calculatoare are cel care încearcă.
-- -------------------------------------------------------------------------
ALTER TABLE membri
  ADD COLUMN parola_temporara_incercari TINYINT UNSIGNED NOT NULL DEFAULT 0
  AFTER parola_temporara_ceruta_la;

-- Când și-a schimbat ultima dată parola. Se vede în cont și e de folos când
-- cineva ne scrie că i s-a schimbat parola fără să știe.
ALTER TABLE membri
  ADD COLUMN parola_schimbata_la DATETIME NULL DEFAULT NULL AFTER parola_temporara_incercari;
