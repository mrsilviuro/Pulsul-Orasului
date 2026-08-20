-- =========================================================================
--  PulsulOrasului.Ro — tabla cu dorințe
--
--  Rulează după sql/022-evenimente-staff.sql. În phpMyAdmin: alege întâi baza
--  din stânga, apoi „Import".
--
--  DE CE EXISTĂ
--
--  Ca să pui la cale un eveniment trebuie să-ți iei o răspundere: să alegi
--  ziua, locul, ora, să răspunzi celor care vin. Nu toată lumea vrea asta, și
--  e în regulă. Dar mulți ar veni la ceva, dacă s-ar face.
--
--  Tabla cu dorințe e treapta de dinaintea aceleia: un rând scris de om,
--  „mi-ar plăcea să se facă asta", pe care îl citește cine tocmai caută o
--  idee. N-are dată, n-are loc, n-are listă de participanți — tocmai de aceea
--  nu e un eveniment și nu stă în `evenimente`.
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  UN RÂND = o dorință
--
--  Rândul rămâne pentru totdeauna, și după ce dorința iese de pe tablă. Nu se
--  șterge nimic: mai târziu vrem să putem spune câte dorințe și-au pus
--  oamenii de-a lungul timpului, iar o tablă golită n-ar mai fi știut.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dorinte (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- -----------------------------------------------------------------------
  --  membru_id — cine și-a pus-o
  --
  --  Fără ON DELETE CASCADE nu s-ar putea, dar în practică nu se declanșează
  --  niciodată: contul șters se anonimizează, nu se șterge (vezi
  --  inc/stergere.php). Rândul din `membri` rămâne, golit de om.
  --
  --  Ce se întâmplă atunci cu dorința: DISPARE DE PE TABLĂ, fiindcă tabla
  --  cere `membri.stare = 'activ'`, iar contul anonimizat nu mai e activ. Dar
  --  rândul de aici rămâne, pentru numărătoarea de mai târziu.
  -- -----------------------------------------------------------------------
  membru_id      INT UNSIGNED NOT NULL,

  -- Orașul în care omul ar vrea să se întâmple. Aceeași listă ca la
  -- evenimente — `orase` din inc/config.php, prin oraseDisponibile(). Nu
  -- există tabel pentru ea, dinadins: un oraș nou e un rând în config.
  oras           VARCHAR(80)  NOT NULL,

  -- -----------------------------------------------------------------------
  --  dorinta — ce a scris omul, cel mult 100 de CARACTERE
  --
  --  `dorinta`, nu `text`: „text" e un tip de date în MySQL, iar o coloană cu
  --  numele unui tip cere ghilimele în jumătate din interogări.
  --
  --  VARCHAR(100) numără caractere, nu octeți — la fel ca mb_strlen din PHP,
  --  care ține limita. Cu 255 în loc de 100 baza ar fi lăsat să treacă ce
  --  respinge codul, și atunci limita ar fi fost scrisă în două locuri care
  --  se pot certa.
  -- -----------------------------------------------------------------------
  dorinta        VARCHAR(100) NOT NULL,

  -- -----------------------------------------------------------------------
  --  stare_moderare — nimic nu ajunge pe tablă necitit
  --
  --  Aceleași trei stări ca la evenimente, din același motiv: pe prima pagină
  --  a site-ului nu se scrie singur nimeni. Omul e înștiințat de asta chiar
  --  în formular, ca să nu-și caute dorința pe tablă imediat după trimitere.
  --
  --  „respins" nu se șterge: fără el, cine a scris ceva nepotrivit ar fi putut
  --  încerca din nou la nesfârșit, iar noi n-am fi avut de unde ști.
  -- -----------------------------------------------------------------------
  stare_moderare ENUM('in_asteptare', 'aprobat', 'respins')
                 NOT NULL DEFAULT 'in_asteptare',

  -- DATETIME scrise din PHP cu acum(), niciodată NOW() — regula ceasului unic
  -- din CLAUDE.md. Fusul PHP și cel al MySQL au dat deja bug-uri adevărate.
  creat_la       DATETIME     NOT NULL,

  -- -----------------------------------------------------------------------
  --  publicat_la — de când se numără cele șapte zile
  --
  --  NU de la trimitere. Dacă o dorință stă trei zile până e citită, cele
  --  șapte zile de tablă ar fi rămas patru — omul ar fi fost pedepsit pentru
  --  că noi am întârziat.
  --
  --  Stă NULL până la aprobare. Îl pune codul, într-un singur loc
  --  (stampileazaCeleAprobate din inc/dorinte.php), tot cu ceasul PHP. De
  --  aceea, ca să publici o dorință din phpMyAdmin, e de ajuns să-i schimbi
  --  `stare_moderare` în „aprobat": ștampila se pune singură la prima
  --  încărcare a primei pagini.
  -- -----------------------------------------------------------------------
  publicat_la    DATETIME         NULL DEFAULT NULL,

  PRIMARY KEY (id),

  -- „Ce e pe tablă acum?" — întrebarea pusă la fiecare încărcare a primei
  -- pagini: stare aprobată și `publicat_la` din ultimele șapte zile.
  KEY idx_dorinte_tabla (stare_moderare, publicat_la),

  -- „Omul ăsta mai are voie să-și pună una?" — se caută ultima lui dorință.
  KEY idx_dorinte_membru (membru_id, creat_la),

  CONSTRAINT fk_dorinte_membru
    FOREIGN KEY (membru_id) REFERENCES membri (id) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
