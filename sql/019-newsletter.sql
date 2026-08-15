-- =========================================================================
--  PulsulOrasului.Ro — cine vrea să afle când deschidem
--
--  Rulează după sql/018-multumiri-eveniment.sql. În phpMyAdmin: alege întâi
--  baza din stânga, apoi „Import".
--
--  Cât timp site-ul stă în lucru (`in_constructie` din inc/config.php), tot ce
--  se vede e pagina de așteptare. Singurul lucru pe care îl poate face cineva
--  acolo e să-și lase adresa, ca să afle când deschidem — iar adresele acelea
--  ajung aici.
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  UN RÂND = o adresă care așteaptă vestea
--
--  NU e un cont și nu are legătură cu `membri`. Cine se înscrie aici n-are
--  cont — n-are cum să-și facă unul, fiindcă înregistrarea e închisă cât ține
--  lucrarea. Sunt doar oameni care au trecut pe la ușă înainte s-o deschidem.
--
--  De aceea nici nu se leagă printr-o cheie străină de nimic: ziua în care
--  omul își face cont, adresa de aici rămâne oricum a lui, iar cele două liste
--  n-au de ce să se caute una pe alta.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS abonati_newsletter (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- -----------------------------------------------------------------------
  --  email — scris cu litere mici, ca peste tot pe site
  --
  --  190 și nu 255: cu utf8mb4 un index urcă la patru octeți de caracter, iar
  --  190×4 = 760 < 767, limita veche a InnoDB pentru un index. Aceeași lățime
  --  ca `membri.email`, din același motiv.
  -- -----------------------------------------------------------------------
  email    VARCHAR(190) NOT NULL,

  -- -----------------------------------------------------------------------
  --  UNIC, ca nimeni să nu ajungă de două ori pe listă
  --
  --  Regula o ține BAZA, nu codul. Verificarea din PHP („e deja înscris?") e
  --  bună pentru mesajul frumos, dar două apăsări în aceeași clipă, de pe
  --  telefon și de pe laptop, trec amândouă de ea.
  --
  --  Pe ecran, a doua înscriere arată exact ca prima: „te-am trecut pe listă".
  --  Nu fiindcă n-am ști, ci fiindcă un „ești deja înscris" ar fi transformat
  --  formularul într-un loc unde oricine poate afla, adresă cu adresă, cine e
  --  pe listă și cine nu.
  -- -----------------------------------------------------------------------
  UNIQUE KEY uq_newsletter_email (email),

  -- -----------------------------------------------------------------------
  --  ip — numai pentru limita de înscrieri
  --
  --  VARBINARY(16) fiindcă acolo încape și IPv6 (16 octeți), și IPv4 (4), așa
  --  cum le scrie inet_pton(). Se pune prin ipBinar() din inc/bootstrap.php,
  --  la fel ca la mesajele de contact și la conturile noi.
  --
  --  Se numără în tabelul ăsta, nu într-un sistem separat: aceeași alegere ca
  --  peste tot pe site. `incercari_autentificare` rămâne doar pentru intrarea
  --  în cont, unde numărătoarea duce la blocarea contului — o limită de aici
  --  n-are voie să încuie contul nimănui.
  -- -----------------------------------------------------------------------
  ip       VARBINARY(16)    NULL DEFAULT NULL,

  -- DATETIME scris din PHP cu acum(), niciodată NOW() — regula ceasului unic
  -- din CLAUDE.md. Fusul PHP și cel al MySQL au dat deja bug-uri adevărate.
  creat_la DATETIME     NOT NULL,

  PRIMARY KEY (id),

  -- „Câte înscrieri au venit de la adresa asta în ultima oră?" — singura
  -- întrebare pusă la fiecare trimitere.
  KEY idx_newsletter_ip (ip, creat_la)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
