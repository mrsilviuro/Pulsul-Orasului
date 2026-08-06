-- =========================================================================
--  PulsulOrasului.Ro — intrarea cu Google
--
--  Rulează după schema.sql. În phpMyAdmin: alege întâi baza din stânga,
--  apoi „Import".
--
--  Dacă pornești de la zero, schema.sql conține deja tot ce e aici.
-- =========================================================================

-- -------------------------------------------------------------------------
--  google_id — cine e omul, după Google
--
--  E câmpul „sub" din răspunsul lor: un șir de cifre care nu se schimbă
--  NICIODATĂ, nici dacă omul își schimbă adresa de Gmail sau numele.
--
--  De aceea legătura se ține pe el, nu pe adresa de e-mail. Dacă am lega
--  conturile după adresă, cineva care își schimbă adresa la Google ar ajunge
--  la noi ca om nou, iar contul lui vechi ar rămâne orfan.
-- -------------------------------------------------------------------------
ALTER TABLE membri
  ADD COLUMN google_id VARCHAR(32) NULL DEFAULT NULL AFTER email;

-- Unic: un cont de Google se leagă la un singur membru. Coloana acceptă NULL,
-- iar MySQL nu numără valorile NULL într-un index unic — deci toți membrii
-- care nu folosesc Google pot sta liniștiți unul lângă altul.
ALTER TABLE membri
  ADD UNIQUE KEY uk_membri_google (google_id);

-- -------------------------------------------------------------------------
--  parola_hash devine opțională
--
--  Cine intră doar cu Google nu are parolă la noi și nu are de ce să aibă.
--  Nu punem un hash inventat, pentru că atunci n-am mai putea deosebi „nu are
--  parolă" de „are una pe care n-o știe nimeni".
--
--  Codul care verifică parola trece deja peste NULL: `$membru['parola_hash']
--  ?? $hashFals` întoarce hash-ul fals, iar verificarea pică liniștit.
-- -------------------------------------------------------------------------
ALTER TABLE membri
  MODIFY COLUMN parola_hash VARCHAR(255) NULL DEFAULT NULL;
