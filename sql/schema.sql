-- =========================================================================
--  PulsulOrasului.Ro — structura bazei de date
--
--  Se rulează o singură dată, după ce baza de date există deja.
--
--  --- PE GĂZDUIREA REALĂ (cPanel, phpMyAdmin) --------------------------
--  Baza se face din panoul găzduirii, nu de aici: utilizatorul tău de MySQL
--  nu are (și nu trebuie să aibă) dreptul de a crea baze de date. Numele
--  primește automat un prefix, de forma „numecont_db".
--
--  În phpMyAdmin: alegi întâi baza din stânga, apoi „Import" și fișierul
--  ăsta. Fără pasul cu alegerea bazei, importul nu are unde să scrie.
--
--  --- ÎN XAMPP, LOCAL --------------------------------------------------
--  Baza se face o singură dată, cu linia de mai jos (o dai în fila „SQL"
--  din phpMyAdmin, sau din consolă):
--
--      CREATE DATABASE pulsulorasului
--        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--
--  Apoi alegi baza și rulezi fișierul ăsta.
--
--  De ce nu sunt „CREATE DATABASE" și „USE" chiar aici: ar merge în XAMPP,
--  unde ești root, dar pe găzduire ar opri importul din prima linie, cu
--  „access denied" — și, în plus, numele bazei e altul acolo.
-- =========================================================================

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

  -- Cine e omul după Google („sub"-ul lor). NULL pentru cine nu folosește
  -- Google. Vezi explicația din sql/005-google.sql.
  google_id         VARCHAR(32)         NULL DEFAULT NULL,

  -- NULL pentru cine intră doar cu Google: nu are parolă la noi.
  parola_hash       VARCHAR(255)        NULL DEFAULT NULL,

  -- Parola temporară, pentru cine și-a uitat parola. Se ține hashuită, exact
  -- ca cea adevărată. Vezi explicațiile din sql/004-parola-uitata.sql.
  parola_temporara_hash      VARCHAR(255)     NULL DEFAULT NULL,
  parola_temporara_expira    DATETIME         NULL DEFAULT NULL,
  parola_temporara_ceruta_la DATETIME         NULL DEFAULT NULL,
  parola_temporara_incercari TINYINT UNSIGNED NOT NULL DEFAULT 0,
  parola_schimbata_la        DATETIME         NULL DEFAULT NULL,

  data_nasterii     DATE            NOT NULL,
  sex               ENUM('M','F')   NOT NULL,
  localitate        VARCHAR(80)         NULL DEFAULT NULL,

  -- Poza de profil: doar partea aleatorie a numelui de fișier, fără cale și
  -- fără extensie. Vezi explicația pe larg din sql/003-poza-profil.sql.
  poza              VARCHAR(32)         NULL DEFAULT NULL,
  poza_actualizata_la DATETIME          NULL DEFAULT NULL,

  stare             ENUM('neconfirmat','activ','suspendat')
                                    NOT NULL DEFAULT 'neconfirmat',

  token_confirmare  CHAR(64)            NULL DEFAULT NULL,
  token_expira      DATETIME            NULL DEFAULT NULL,
  token_trimis_la   DATETIME            NULL DEFAULT NULL,
  confirmat_la      DATETIME            NULL DEFAULT NULL,
  autentificat_la   DATETIME            NULL DEFAULT NULL,

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
  -- Un cont de Google se leagă la un singur membru. MySQL nu numără valorile
  -- NULL într-un index unic, deci ceilalți membri nu se încurcă între ei.
  UNIQUE KEY uk_membri_google    (google_id),

  KEY idx_membri_token (token_confirmare),
  KEY idx_membri_ip    (ip_inregistrare, creat_la)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
--  Încercările de autentificare
--
--  Un rând la fiecare încercare, reușită sau nu. De aici se numără eșecurile
--  recente, ca să blocăm formularul temporar.
--
--  Se numără pe perechea (e-mail + IP), nu doar pe e-mail: altfel oricine ar
--  putea închide contul altcuiva trimițând trei parole greșite. Există și o
--  limită separată pe IP, pentru cine încearcă multe adrese de la același loc.
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
