-- =========================================================================
--  PulsulOrasului.Ro — evaluările dintre participanți
--
--  Rulează după sql/016-excluderi-evenimente.sql. În phpMyAdmin: alege întâi
--  baza din stânga, apoi „Import".
--
--  Stelele de pe profil erau până acum șablon: 4,6 dintr-o mie, scris de mână
--  în profil.php, cu o distribuție inventată și un formular care nu trimitea
--  nimic nicăieri. De aici încolo sunt ale oamenilor.
--
--  Se evaluează DUPĂ un eveniment, între cei care au fost la el. Nu oricine pe
--  oricine: nota are greutate tocmai fiindcă în spatele ei e o seară petrecută
--  împreună, nu o apăsare de pe un profil găsit la întâmplare.
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  UN RÂND = o notă dată de cineva, cuiva, după un anumit eveniment
--
--  Trei lucruri fac perechea: evenimentul, cel notat și cel care notează. La
--  alt eveniment, aceiași doi oameni se pot nota din nou — și e firesc: s-au
--  văzut de două ori, sunt două păreri, nu una rescrisă.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS evaluari (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Aceleași tipuri ca `evenimente.id` și `membri.id` (INT UNSIGNED). O cheie
  -- străină cere potrivire exactă de tip, altfel ALTER TABLE pică cu un
  -- „errno: 150" care nu spune nimănui nimic.
  eveniment_id  INT UNSIGNED    NOT NULL,

  -- Cine primește nota, și cine o dă.
  --
  -- `evaluator_id` NU se arată NICIODATĂ pe ecran. Notele sunt anonime, ca
  -- aprecierile de sub comentarii: se vede numărul de stele și textul, nu și
  -- cine le-a scris. Rândul îl ține minte doar ca să nu poată nota același om
  -- de zece ori și ca să-și poată schimba părerea o dată dată.
  --
  -- De ce anonime: altfel nimeni n-ar mai da patru stele cuiva pe care îl va
  -- reîntâlni sâmbăta viitoare. O notă care se semnează e o notă frumoasă,
  -- adică una care nu spune nimic.
  evaluat_id    INT UNSIGNED    NOT NULL,
  evaluator_id  INT UNSIGNED    NOT NULL,

  -- De la 1 la 5. Fără zero: „fără notă" înseamnă că nu există rândul.
  -- TINYINT, nu ENUM: e un număr cu care se face medie, nu o listă de valori.
  stele         TINYINT UNSIGNED NOT NULL,

  -- -----------------------------------------------------------------------
  --  text — vorbele, dacă a scris vreuna
  --
  --  NULL înseamnă „a dat doar stele". Așa se și întâmplă de cele mai multe
  --  ori: stelele se dau dintr-o apăsare, de pe pagina evenimentului, iar
  --  scrisul cere să te așezi.
  --
  --  Text curat, neescapat — regula 9 din CLAUDE.md. Escaparea e la randare.
  -- -----------------------------------------------------------------------
  text          TEXT                NULL DEFAULT NULL,

  -- -----------------------------------------------------------------------
  --  automat — nota pusă de site, nu de un om care a apăsat pe stele
  --
  --  Deocamdată una singură: „Nu s-a prezentat", pe care o pune organizatorul
  --  în dreptul cuiva care a lipsit. Aceea e o steluță și un text scris de noi,
  --  nu părerea lui despre om.
  --
  --  Se ține deoparte fiindcă se citește altfel: o notă automată e un fapt
  --  („n-a venit"), nu o opinie, iar pe profil se arată ca atare — cu numele
  --  evenimentului lângă ea, nu ca o părere anonimă printre celelalte.
  -- -----------------------------------------------------------------------
  automat       TINYINT UNSIGNED NOT NULL DEFAULT 0,

  -- DATETIME scris din PHP cu acum(), niciodată NOW() — regula ceasului unic.
  --
  -- Două momente, fiindcă nota se poate schimba: cine a dat trei stele de pe
  -- pagina evenimentului și apoi a scris pe profil un text și cinci stele
  -- rescrie același rând. `creat_la` ține minte când s-a format prima părere.
  creat_la      DATETIME        NOT NULL,
  actualizat_la DATETIME        NOT NULL,

  PRIMARY KEY (id),

  -- -----------------------------------------------------------------------
  --  Un om, un eveniment, o singură notă dată altui om
  --
  --  Regula o ține baza, nu codul: verificarea din PHP („a mai notat?") e bună
  --  pentru mesajul frumos, dar două apăsări în aceeași clipă, de pe telefon și
  --  de pe laptop, trec amândouă de ea.
  --
  --  Tot ea face posibil „INSERT ... ON DUPLICATE KEY UPDATE": stelele date de
  --  pe pagina evenimentului și textul scris mai târziu pe profil ajung în
  --  același rând, nu în două.
  -- -----------------------------------------------------------------------
  UNIQUE KEY uk_evaluari_eveniment_evaluat_evaluator (eveniment_id, evaluat_id, evaluator_id),

  -- „Ce note are omul ăsta?" — întrebarea de pe fiecare profil: media, câte
  -- sunt, distribuția pe stele și lista. Toate se citesc din indexul ăsta.
  KEY idx_evaluari_evaluat (evaluat_id, creat_la),

  -- „Pe cine am notat eu la evenimentul ăsta?" — întrebarea paginii unui
  -- eveniment încheiat, care aprinde stelele deja date, pentru toată lista
  -- dintr-o singură cerere.
  KEY idx_evaluari_evenimentul_meu (eveniment_id, evaluator_id),

  -- -----------------------------------------------------------------------
  --  CASCADE peste tot
  --
  --  Dispare evenimentul, dispar notele date după el: nu mai au despre ce să
  --  vorbească. Conturile nu se șterg, se anonimizează (vezi inc/stergere.php),
  --  deci cheile spre `membri` sunt doar o plasă — dar dacă vreodată se șterge
  --  unul de mână, din phpMyAdmin, nu rămân note care arată spre nimeni.
  -- -----------------------------------------------------------------------
  CONSTRAINT fk_evaluari_eveniment
    FOREIGN KEY (eveniment_id) REFERENCES evenimente (id) ON DELETE CASCADE,

  CONSTRAINT fk_evaluari_evaluat
    FOREIGN KEY (evaluat_id) REFERENCES membri (id) ON DELETE CASCADE,

  CONSTRAINT fk_evaluari_evaluator
    FOREIGN KEY (evaluator_id) REFERENCES membri (id) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
