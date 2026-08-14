-- =========================================================================
--  PulsulOrasului.Ro — scoaterea cuiva de pe lista de participanți
--
--  Rulează după sql/015-comentarii.sql. În phpMyAdmin: alege întâi baza din
--  stânga, apoi „Import".
--
--  Până acum, de pe lista de participanți se ieșea doar singur. Dar locurile
--  sunt ale organizatorului: cine s-a înscris și nu mai vine, cine s-a înscris
--  de trei ori cu trei conturi, cine strică socoteala unui eveniment cu număr
--  limitat — pe toți trebuie să-i poată da jos cineva, ca să se elibereze
--  locul pentru cine chiar vine.
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  UN RÂND = un om dat jos de pe lista unui eveniment
--
--  Rândul din `interese_evenimente` se șterge — locul se eliberează pe loc.
--  Aici rămâne urma faptei: cine, pe cine, de ce, și dacă i s-a închis ușa.
--
--  De ce un tabel și nu doar ștergerea:
--
--   1. Omul primește un e-mail cu motivul, iar motivul trebuie să existe
--      undeva. Scris doar în e-mail, n-ar mai putea fi arătat nimănui după.
--   2. Fără el n-ar exista interdicția: cine e dat jos s-ar putea înscrie la
--      loc în clipa următoare, apăsând același buton.
--   3. Organizatorul trebuie să poată spune, peste o lună, de ce a scos pe
--      cineva. Mai ales dacă omul se plânge.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS excluderi_evenimente (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Aceleași tipuri ca `evenimente.id` și `membri.id` (INT UNSIGNED). O cheie
  -- străină cere potrivire exactă de tip, altfel ALTER TABLE pică cu un
  -- „errno: 150" care nu spune nimănui nimic.
  eveniment_id INT UNSIGNED    NOT NULL,
  membru_id    INT UNSIGNED    NOT NULL,

  -- -----------------------------------------------------------------------
  --  Cine a făcut-o, și în ce calitate
  --
  --  `rol` se scrie ACUM, nu se socotește la citire. Cine e staff azi poate să
  --  nu mai fie la anul, iar un eveniment își poate schimba organizatorul:
  --  întrebat mai târziu, `membri.este_staff` ar răspunde despre omul de azi,
  --  nu despre fapta de atunci. Aici se ține minte ce a fost în clipa aceea —
  --  și tot asta scrie în e-mailul primit de om.
  --
  --  Cine e și staff, și organizator, apare ca „organizator": e evenimentul
  --  lui, iar asta spune mai mult celui care citește.
  --
  --  `exclus_de_id` NU e cheie străină, dinadins: hotărârea nu are de ce să
  --  atârne de rândul celui care a luat-o. Dacă vreodată se șterge un cont de
  --  mână, din phpMyAdmin, interdicția trebuie să rămână în picioare, nu să
  --  plece odată cu el.
  -- -----------------------------------------------------------------------
  exclus_de_id INT UNSIGNED    NOT NULL,
  rol          ENUM('organizator','staff') NOT NULL,

  -- Text curat, neescapat — regula 9 din CLAUDE.md. Escaparea e la randare.
  -- Minimul (MOTIV_EXCLUDERE_MIN) îl ține inc/validare.php, în caractere.
  motiv        TEXT            NOT NULL,

  -- -----------------------------------------------------------------------
  --  interzis — i s-a închis ușa, sau doar a fost dat jos?
  --
  --  0: a fost scos, dar se poate înscrie la loc dacă vrea. Se întâmplă des —
  --     o listă plină de oameni care nu mai vin se face curat fără supărare.
  --  1: nu se mai poate înscrie la evenimentul ăsta. Verificarea e în
  --     api/interes.php, la apăsarea pe „Voi participa".
  --
  --  Se oprește DOAR participarea, nu și „Mă interesează": una ocupă un loc și
  --  aduce omul acolo, cealaltă e o însemnare în dreptul lui.
  -- -----------------------------------------------------------------------
  interzis     TINYINT UNSIGNED NOT NULL DEFAULT 0,

  -- DATETIME scris din PHP cu acum(), niciodată NOW() — regula ceasului unic.
  creat_la     DATETIME        NOT NULL,

  PRIMARY KEY (id),

  -- -----------------------------------------------------------------------
  --  Un om, un eveniment, un singur rând
  --
  --  Cine a fost scos fără interdicție se poate înscrie la loc, și poate fi
  --  scos din nou. A doua oară nu se adaugă un rând, se rescrie cel de dinainte
  --  („INSERT ... ON DUPLICATE KEY UPDATE" — vezi excludeParticipant()).
  --
  --  Nu ținem toată povestea, ci starea de acum: motivul din urmă și dacă ușa e
  --  închisă. Un jurnal al tuturor scoaterilor ar fi altceva, și n-are cine
  --  să-l citească — nu există încă pagină de administrare.
  --
  --  Tot indexul ăsta face și verificarea din api/interes.php: „are omul ăsta
  --  ușa închisă la evenimentul ăsta?" se citește dintr-o singură atingere.
  -- -----------------------------------------------------------------------
  UNIQUE KEY uk_excluderi_eveniment_membru (eveniment_id, membru_id),

  -- „De unde am fost dat jos?" — pentru când va exista o pagină care s-o spună.
  KEY idx_excluderi_membru (membru_id, creat_la),

  -- -----------------------------------------------------------------------
  --  CASCADE pe eveniment: dispare evenimentul, dispare și interdicția la el.
  --  N-ar mai avea la ce să oprească pe nimeni.
  --
  --  CASCADE pe membru e doar o plasă: conturile nu se șterg, se anonimizează
  --  (vezi inc/stergere.php).
  -- -----------------------------------------------------------------------
  CONSTRAINT fk_excluderi_eveniment
    FOREIGN KEY (eveniment_id) REFERENCES evenimente (id) ON DELETE CASCADE,

  CONSTRAINT fk_excluderi_membru
    FOREIGN KEY (membru_id) REFERENCES membri (id) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
