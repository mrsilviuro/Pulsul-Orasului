-- =========================================================================
--  PulsulOrasului.Ro — structura bazei de date
--
--  Rulează o singură dată, din phpMyAdmin sau din consolă:
--      mysql -u root -p < sql/schema.sql
-- =========================================================================

CREATE DATABASE IF NOT EXISTS pulsulorasului
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE pulsulorasului;

-- -------------------------------------------------------------------------
--  Membri
--
--  Câteva alegeri, explicate:
--
--  utf8mb4      — ca să încapă corect diacriticele românești (și emoji, dacă
--                 ajung vreodată într-un nume de localitate).
--
--  email(190)   — nu 255. Un index pe utf8mb4 ocupă 4 octeți pe caracter, iar
--                 limita veche de index din MySQL e 767 de octeți: 190 × 4 =
--                 760, deci intră. Adresele mai lungi de 190 de caractere nu
--                 există în practică.
--
--  parola_hash  — hash produs de password_hash(). NICIODATĂ parola în clar.
--                 255 de caractere ca să încapă și algoritmi viitori.
--
--  token_confirmare — se păstrează *hash-ul* token-ului, nu token-ul în sine.
--                 Dacă baza de date ajunge pe mâini străine, linkurile de
--                 confirmare nu pot fi folosite, exact ca la parole.
--
--  permalink    — adresa publică a profilului. Șir aleatoriu, nu numele:
--                 vezi explicația din README.
--
--  ip_inregistrare — VARBINARY(16), scris cu INET6_ATON / inet_pton, ca să
--                 încapă și IPv4, și IPv6. Se folosește doar la limitarea
--                 înregistrărilor repetate de la aceeași adresă.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS membri (
  id                INT UNSIGNED    NOT NULL AUTO_INCREMENT,

  permalink         VARCHAR(16)     NOT NULL,

  nume              VARCHAR(60)     NOT NULL,
  prenume           VARCHAR(60)     NOT NULL,
  email             VARCHAR(190)    NOT NULL,
  parola_hash       VARCHAR(255)    NOT NULL,
  data_nasterii     DATE            NOT NULL,
  sex               ENUM('M','F')   NOT NULL,
  localitate        VARCHAR(80)         NULL DEFAULT NULL,

  stare             ENUM('neconfirmat','activ','suspendat')
                                    NOT NULL DEFAULT 'neconfirmat',

  token_confirmare  CHAR(64)            NULL DEFAULT NULL,
  token_expira      DATETIME            NULL DEFAULT NULL,
  confirmat_la      DATETIME            NULL DEFAULT NULL,

  ip_inregistrare   VARBINARY(16)       NULL DEFAULT NULL,

  creat_la          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizat_la     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),

  -- Unicitatea e impusă de bază, nu doar verificată din PHP: două cereri
  -- trimise în aceeași clipă ar putea trece amândouă de verificare, dar
  -- doar una poate trece de index.
  UNIQUE KEY uk_membri_email     (email),
  UNIQUE KEY uk_membri_permalink (permalink),

  KEY idx_membri_token (token_confirmare),
  KEY idx_membri_ip    (ip_inregistrare, creat_la)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
