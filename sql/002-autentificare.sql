-- =========================================================================
--  PulsulOrasului.Ro — completări pentru autentificare
--
--  Rulează după schema.sql, dacă ai deja tabelul „membri" creat:
--      mysql -u root -p pulsulorasului < sql/002-autentificare.sql
--
--  Dacă pornești de la zero, schema.sql conține deja tot ce e aici.
-- =========================================================================

-- Alege întâi baza de date (în phpMyAdmin, din lista din stânga).
-- Nu punem „USE" aici: numele bazei e altul pe găzduire decât în XAMPP.

-- Când a fost trimis ultima dată e-mailul de confirmare. Folosit ca să nu
-- poată fi cerut la nesfârșit (o dată la 10 minute).
ALTER TABLE membri
  ADD COLUMN token_trimis_la DATETIME NULL DEFAULT NULL AFTER token_expira;

-- Ultima autentificare reușită. Utilă și pentru utilizator („ai intrat ultima
-- dată pe..."), și pentru tine, ca să vezi conturile nefolosite.
ALTER TABLE membri
  ADD COLUMN autentificat_la DATETIME NULL DEFAULT NULL AFTER confirmat_la;

-- -------------------------------------------------------------------------
--  Încercările de autentificare
--
--  Se scrie un rând la fiecare încercare, reușită sau nu. De aici se numără
--  eșecurile recente, ca să blocăm formularul temporar.
--
--  De ce se numără pe perechea (e-mail + IP), nu doar pe e-mail: dacă am
--  bloca doar după e-mail, oricine ar putea închide contul altcuiva trimițând
--  trei parole greșite. Există și o limită separată, mai largă, pe IP, pentru
--  cazul în care cineva încearcă multe adrese de la același calculator.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS incercari_autentificare (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email     VARCHAR(190)    NOT NULL,
  ip        VARBINARY(16)       NULL DEFAULT NULL,
  reusita   TINYINT(1)      NOT NULL DEFAULT 0,
  creat_la  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_incercari_email_ip (email, ip, creat_la),
  KEY idx_incercari_ip       (ip, creat_la),
  KEY idx_incercari_data     (creat_la)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
--  Curățarea încercărilor vechi
--
--  Se face automat din PHP (vezi curataIncercariVechi() din inc/auth.php),
--  la aproximativ una din 50 de scrieri. Nu e nevoie de nimic în plus.
--
--  Dacă preferi să o facă baza de date singură, pe o găzduire unde
--  planificatorul de evenimente e pornit, poți folosi în schimb:
--
--  SET GLOBAL event_scheduler = ON;
--
--  CREATE EVENT IF NOT EXISTS curata_incercari
--    ON SCHEDULE EVERY 1 DAY
--    DO DELETE FROM incercari_autentificare
--        WHERE creat_la < (NOW() - INTERVAL 30 DAY);
-- -------------------------------------------------------------------------
