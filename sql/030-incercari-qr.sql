-- =========================================================================
--  PulsulOrasului.Ro — încercările de scanare a unui abțibild
--
--  Rulează după sql/029-eveniment-fixat.sql. În phpMyAdmin: alege întâi baza
--  din stânga, apoi „Import".
--
--  DE CE
--
--  Un cod de abțibild are cinci semne dintr-un alfabet de 32, adică vreo 33 de
--  milioane de combinații. Sună mult, dar nimeni n-are nevoie să le încerce pe
--  toate: e de ajuns să nimerească UNUL dintre cele câteva active la un moment
--  dat. Cu zece vânători în oraș, un program care încearcă cincizeci de coduri
--  pe secundă nimerește unul în câteva ore — de pe canapea, fără să se ridice.
--
--  Asta ar goli jocul de tot rostul lui. „FindMe" nu e un concurs de ghicit, e
--  un motiv de a te plimba prin oraș cu ochii deschiși.
--
--  Tabelul ăsta ține minte fiecare scanare care N-A NIMERIT nimic, ca să se
--  poată număra. Sub o limită pe ceas, un program n-are cum să treacă prin
--  milioane de combinații.
--
--  DE CE UN TABEL AL LUI, ȘI NU `incercari_autentificare`
--
--  Fiindcă acolo numărătoarea duce la BLOCAREA UNUI CONT, iar o limită de pe
--  altă pagină n-are voie să încuie contul cuiva — e chiar regula scrisă în
--  CLAUDE.md. Aici nu se încuie niciun cont: se încetinește o adresă IP.
--
--  DE CE SE ȚIN MINTE DOAR CELE GREȘITE
--
--  Cine scanează un abțibild adevărat n-are de ce să fie numărat: a fost
--  acolo, s-a uitat, a găsit. Numărăm doar bâjbâiala.
--
--  Rândurile vechi se șterg singure, la fiecare scanare (curataIncercarileQr),
--  ca tabelul să nu crească la nesfârșit. Nu e nevoie de niciun cron.
-- =========================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS incercari_qr (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip        VARBINARY(16) NOT NULL COMMENT 'inet_pton, ca să încapă și IPv6',
  creat_la  DATETIME     NOT NULL,
  PRIMARY KEY (id),
  -- Cheia după care se numără: „câte de la IP-ul ăsta, în ultimul ceas".
  KEY idx_incercari_qr_ip (ip, creat_la),
  -- Și după timp singur, pentru curățenie.
  KEY idx_incercari_qr_timp (creat_la)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
