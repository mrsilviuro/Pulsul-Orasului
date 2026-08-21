-- =========================================================================
--  PulsulOrasului.Ro — categorii pe care le vede numai staff-ul
--
--  Rulează după sql/023-dorinte.sql. În phpMyAdmin: alege întâi baza din
--  stânga, apoi „Import".
--
--  DE CE
--
--  Prima categorie de felul ăsta e „FindMe": jocul cu coduri QR ascunse prin
--  oraș, pe care oamenii le caută și le scanează. Evenimentele lui nu se
--  propun de nimeni — le pune casa — deci categoria n-are ce căuta în lista
--  din care își alege omul obișnuit când publică o ieșire.
--
--  O COLOANĂ, NU UN NUME SCRIS ÎN COD. Cu un `if ($slug === 'findme')` prin
--  PHP, a doua categorie de felul ăsta ar fi cerut încă un `if`, în alt loc,
--  iar al treilea l-ar fi uitat cineva. Așa, un rând nou cu `doar_staff = 1`
--  se poartă singur cum trebuie, oriunde ar fi întrebată lista.
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  doar_staff — cine poate PUBLICA în ea
--
--  0 (implicit) = categorie obișnuită: oricine poate publica în ea.
--
--  1            = numai staff-ul. Ea nu apare în formularul de publicare
--                 pentru ceilalți, iar cine scrie numărul ei de mână în cerere
--                 e respins (idCategoriiValide).
--
--  ATENȚIE la ce NU face steagul ăsta — și e aproape tot:
--
--    * categoria SE VEDE. Are cip în filtrele de pe prima pagină, ca oricare
--      alta (de îndată ce are măcar un eveniment public), și se poate filtra
--      după ea.
--    * evenimentele din ea se văd la fel ca oricare: pe prima pagină, cu
--      pagina lor, și se pot da mai departe. Altfel n-ar avea cine să caute
--      codurile.
--
--  Singurul loc din care lipsește e formularul de publicare — adică tocmai
--  locul unde se alege unde publici.
--
--  Rândul propriu-zis („FindMe") se adaugă de mână, din phpMyAdmin:
--
--    INSERT INTO categorii (nume, slug, ordine, doar_staff)
--         VALUES ('FindMe', 'findme', 99, 1);
-- -------------------------------------------------------------------------
ALTER TABLE categorii
  ADD COLUMN doar_staff TINYINT(1) NOT NULL DEFAULT 0 AFTER ordine;
