-- =========================================================================
--  PulsulOrasului.Ro — evenimentele și categoriile lor
--
--  Rulează după schema.sql. În phpMyAdmin: alege întâi baza din stânga,
--  apoi „Import".
--
--  Deocamdată doar tabelele. Formularul de publicare și încărcarea copertei
--  vin separat.
-- =========================================================================

-- E singura migrare care aduce și text cu diacritice („Cultură", „Muzică").
-- Fără rândul ăsta, un client care se conectează pe latin1 — clientul din
-- linia de comandă o face implicit pe multe sisteme — citește octeții UTF-8 ai
-- fișierului ca latin1 și îi mai încodează o dată: în bază ajunge „CulturÄƒ",
-- și de acolo pe site. Nu se vede la rulare, se vede peste o săptămână.
SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  categorii
--
--  Până acum categoriile erau scrise de mână în trei locuri: filtrele din
--  index.php, eticheta de pe fiecare articol și lista din despre.php. De aici
--  înainte au un singur loc.
--
--  „ordine" există fiindcă ordinea din bara de filtre e o alegere, nu o
--  urmare a alfabetului: Sport stă primul pentru că așa a fost gândită pagina,
--  nu pentru că începe cu S.
--
--  imagine_default ține numele fișierului din assets/img/categorii/, nu calea
--  întreagă — calea o construiește PHP-ul, ca la pozele de profil. Fișierele
--  astea se urcă de mână, deci numele lor e ales de om și rămâne citibil.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorii (
  id              TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,

  nume            VARCHAR(40)      NOT NULL,
  slug            VARCHAR(40)      NOT NULL,
  imagine_default VARCHAR(120)         NULL DEFAULT NULL,
  ordine          TINYINT UNSIGNED NOT NULL DEFAULT 0,

  PRIMARY KEY (id),
  -- Slugul intră în adrese, deci nu pot exista două la fel.
  UNIQUE KEY uk_categorii_slug (slug),
  KEY idx_categorii_ordine (ordine)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Cele de pe prima pagină, în ordinea de acolo. Slugurile sunt fără
-- diacritice: intră în adrese, iar „cultură" în URL devine un șir de procente.
INSERT INTO categorii (nume, slug, ordine) VALUES
  ('Sport',      'sport',      1),
  ('Cultură',    'cultura',    2),
  ('Comunitate', 'comunitate', 3),
  ('Gastro',     'gastro',     4),
  ('Muzică',     'muzica',     5)
ON DUPLICATE KEY UPDATE nume = VALUES(nume), ordine = VALUES(ordine);

-- -------------------------------------------------------------------------
--  evenimente
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS evenimente (
  id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,

  -- -----------------------------------------------------------------------
  --  Cine îl organizează
  --
  --  ON DELETE RESTRICT, nu CASCADE: un eveniment care a avut loc rămâne
  --  parte din istoricul celor care au fost la el, chiar dacă organizatorul
  --  pleacă. Și oricum contul șters se anonimizează, nu dispare (vezi
  --  inc/stergere.php), deci rândul e mereu acolo.
  -- -----------------------------------------------------------------------
  membru_id     INT UNSIGNED     NOT NULL,
  categorie_id  TINYINT UNSIGNED NOT NULL,

  titlu         VARCHAR(140)     NOT NULL,

  -- Adresa publică: titlul adus la forma de URL, plus câteva caractere
  -- întâmplătoare la coadă. Coada e acolo pentru că două evenimente pot avea
  -- același titlu („Târg de Crăciun") în ani diferiți.
  slug          VARCHAR(170)     NOT NULL,

  -- Doar partea aleatorie din numele fișierului, ca la pozele de profil (vezi
  -- sql/003). NULL → se folosește imagine_default de la categorie.
  coperta       CHAR(32)             NULL DEFAULT NULL,

  -- Data și ora stau despărțit: se caută mult după zi („ce e sâmbătă?"), iar
  -- un index pe DATE e mai simplu și mai rapid decât pe un DATETIME din care
  -- trebuie tăiată ora la fiecare comparație.
  data_eveniment DATE            NOT NULL,
  ora_inceput    TIME            NOT NULL,
  ora_sfarsit    TIME                NULL DEFAULT NULL,   -- NULL = nedeterminat

  locatie        VARCHAR(160)    NOT NULL,

  -- -----------------------------------------------------------------------
  --  Costul
  --
  --  NULL înseamnă gratuit — altceva decât 0.00, care ar însemna „am scris
  --  eu zero". Deosebirea contează la afișare: „Gratuit" nu e același lucru
  --  cu „0 lei".
  --
  --  DECIMAL, niciodată FLOAT: în virgulă mobilă binară, 0,10 lei nu se poate
  --  scrie exact, iar greșelile se adună la fiecare adunare.
  -- -----------------------------------------------------------------------
  cost           DECIMAL(8,2)        NULL DEFAULT NULL,

  -- Număr, nu ENUM: se poate compara și sorta („WHERE varsta_minima <= 16"),
  -- iar dacă mâine apare 21 nu e nevoie de ALTER TABLE. NULL = nespecificat.
  varsta_minima  TINYINT UNSIGNED    NULL DEFAULT NULL,

  participanti_min SMALLINT UNSIGNED NULL DEFAULT NULL,
  participanti_max SMALLINT UNSIGNED NULL DEFAULT NULL,

  descriere      TEXT            NOT NULL,

  -- Pentru cine e deschis evenimentul. „nespecificat" = poate veni oricine;
  -- celelalte două sunt pentru evenimentele care chiar se adresează unui
  -- singur sex (un meci de fotbal feminin, o seară pentru mame).
  gen_participanti ENUM('barbati','femei','nespecificat')
                                 NOT NULL DEFAULT 'nespecificat',

  -- Nimic nu apare pe site până nu trece pe la om.
  stare_moderare ENUM('in_asteptare','aprobat','respins')
                                 NOT NULL DEFAULT 'in_asteptare',

  -- Ceasul e al PHP-ului, niciodată NOW(). Vezi regula din CLAUDE.md.
  creat_la       DATETIME        NOT NULL,
  actualizat_la  DATETIME        NOT NULL,

  PRIMARY KEY (id),

  UNIQUE KEY uk_evenimente_slug (slug),

  -- „Ce e aprobat și urmează?" — întrebarea listei publice.
  KEY idx_evenimente_public   (stare_moderare, data_eveniment),
  -- „Ce e aprobat la categoria asta?" — filtrele de pe prima pagină.
  KEY idx_evenimente_categorie (categorie_id, stare_moderare, data_eveniment),
  -- „Evenimentele mele" și coada de moderare.
  KEY idx_evenimente_membru   (membru_id, creat_la),

  CONSTRAINT fk_evenimente_membru
    FOREIGN KEY (membru_id) REFERENCES membri (id) ON DELETE RESTRICT,

  -- La fel: o categorie cu evenimente în ea nu se poate șterge din greșeală.
  CONSTRAINT fk_evenimente_categorie
    FOREIGN KEY (categorie_id) REFERENCES categorii (id) ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
