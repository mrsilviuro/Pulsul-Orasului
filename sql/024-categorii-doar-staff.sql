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
--  doar_staff — cine o vede în listă
--
--  0 (implicit) = categorie obișnuită: apare în formularul de publicare
--                 pentru toată lumea și în filtrele de pe prima pagină.
--
--  1            = numai staff-ul o vede în formular și numai el poate publica
--                 în ea. Din filtrele de pe prima pagină lipsește pentru toți,
--                 inclusiv pentru staff: filtrele sunt pentru cine caută o
--                 ieșire, nu o unealtă de administrare.
--
--  ATENȚIE la ce NU face steagul ăsta: evenimentele dintr-o astfel de
--  categorie NU se ascund. Ele apar pe prima pagină, au pagina lor, se pot da
--  mai departe — altfel n-ar avea cine să caute codurile. Ascunsă e doar
--  CATEGORIA, ca alegere.
--
--  Rândul propriu-zis („FindMe") se adaugă de mână, din phpMyAdmin:
--
--    INSERT INTO categorii (nume, slug, ordine, doar_staff)
--         VALUES ('FindMe', 'findme', 99, 1);
-- -------------------------------------------------------------------------
ALTER TABLE categorii
  ADD COLUMN doar_staff TINYINT(1) NOT NULL DEFAULT 0 AFTER ordine;
