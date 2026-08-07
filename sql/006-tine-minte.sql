-- =========================================================================
--  PulsulOrasului.Ro — „ține-mă minte" care chiar ține treizeci de zile
--
--  Rulează după schema.sql. În phpMyAdmin: alege întâi baza din stânga,
--  apoi „Import".
--
--  Dacă pornești de la zero, schema.sql conține deja tot ce e aici.
-- =========================================================================

-- -------------------------------------------------------------------------
--  sesiuni_amintite
--
--  De ce e nevoie de un tabel, când cookie-ul de sesiune putea primi pur și
--  simplu o dată de expirare peste o lună:
--
--  Cookie-ul spune doar cât timp îl păstrează BROWSERUL. Datele sesiunii stau
--  pe server, într-un fișier, iar acela e șters de PHP după
--  session.gc_maxlifetime — pe majoritatea găzduirilor, douăzeci și patru de
--  minute. Pe deasupra, pe găzduirile partajate fișierele stau într-un dosar
--  comun, unde măturăm și noi, și vecinii. Un cookie de treizeci de zile care
--  arată spre un fișier șters de o lună nu conectează pe nimeni.
--
--  Deci ceea ce trebuie să dureze treizeci de zile nu e sesiunea, ci DOVADA
--  că omul s-a autentificat cândva de pe dispozitivul ăsta. Dovada aia stă
--  aici, iar sesiunea se ridică din ea ori de câte ori e nevoie.
--
--  Un rând = un dispozitiv. Cine intră de pe telefon și de pe laptop are două
--  rânduri și le poate pierde separat.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sesiuni_amintite (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  membru_id   INT UNSIGNED    NOT NULL,

  -- -----------------------------------------------------------------------
  --  De ce cookie-ul are două părți, „selector:secret"
  --
  --  Selectorul spune CARE rând, secretul dovedește că e al tău. Dacă am
  --  ține un singur șir, ar trebui să-l căutăm chiar pe el în tabel — adică
  --  fie îl păstrăm în clar (și atunci baza furată deschide toate conturile),
  --  fie căutăm după hash, ceea ce merge, dar ne obligă să comparăm rând cu
  --  rând când vrem și rotația.
  --
  --  Cu două părți: căutăm după selector, care e public și indexat, apoi
  --  comparăm secretul cu hash_equals(). Selectorul nu deschide nimic
  --  singur, iar secretul nu se află nicăieri în clar.
  -- -----------------------------------------------------------------------
  selector    CHAR(32)        NOT NULL,

  -- sha256 al secretului. Nu password_hash(): secretul e generat de noi, are
  -- 32 de octeți întâmplători, deci nu poate fi ghicit prin încercări. Acolo
  -- unde nu e nimic de ghicit, un hash lent n-aduce nimic în plus.
  token_hash  CHAR(64)        NOT NULL,

  -- Aceeași amprentă ca la sesiune (vezi amprentaBrowser() din inc/auth.php).
  -- Un cookie copiat pe alt browser nu mai e bun de nimic.
  amprenta    CHAR(64)        NOT NULL,

  -- Treizeci de zile de la autentificare. Se scrie din PHP, cu time(),
  -- niciodată cu NOW() — vezi regula ceasului unic din CLAUDE.md.
  expira      DATETIME        NOT NULL,

  creat_la    DATETIME        NOT NULL,
  folosit_la  DATETIME        NOT NULL,

  PRIMARY KEY (id),
  UNIQUE KEY uk_amintite_selector (selector),
  KEY idx_amintite_membru (membru_id),
  KEY idx_amintite_expira (expira),

  -- Contul șters își ia amintirile cu el.
  CONSTRAINT fk_amintite_membru
    FOREIGN KEY (membru_id) REFERENCES membri (id) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
--  Curățarea rândurilor expirate
--
--  Se face automat din PHP (vezi curataAmintirileVechi() din inc/auth.php),
--  la aproximativ una din 50 de scrieri. Nu e nevoie de nimic în plus.
-- -------------------------------------------------------------------------
