-- =========================================================================
--  PulsulOrasului.Ro — „Mergi la acest eveniment?"
--
--  Rulează după sql/012-oras-eveniment.sql. În phpMyAdmin: alege întâi baza
--  din stânga, apoi „Import".
--
--  DOAR TABELUL. Butoanele „Mă interesează" și „Voi participa" de pe
--  event.php sunt încă șablon, cu numere inventate; se leagă de tabelul ăsta
--  separat. Migrarea se poate rula de pe acum, fără să schimbe nimic din ce
--  se vede pe site.
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  UN RÂND = un om care a spus ceva despre un eveniment
--
--  Nu două tabele („interesați" și „participanți") și nici două coloane de
--  tip bifă. Cele două stări se exclud: cine spune „voi participa" nu mai e
--  „interesat", e mai mult de-atât. Cu două bife ar fi fost posibil un om
--  bifat în amândouă, iar întrebarea „câți vin?" ar fi avut două răspunsuri.
--
--  De aceea starea e o coloană cu două valori și un singur rând per (om,
--  eveniment). Trecerea dintr-o stare în alta schimbă rândul, nu adaugă unul.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS interese_evenimente (
  -- BIGINT, nu INT: aici se scrie de fiecare dată când cineva apasă un buton
  -- și se șterge de fiecare dată când se răzgândește. Rândurile vin și pleacă,
  -- dar numerele nu se întorc niciodată din urmă.
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Aceleași tipuri ca `evenimente.id` și `membri.id` (INT UNSIGNED). O cheie
  -- străină cere potrivire exactă de tip, altfel ALTER TABLE pică cu un
  -- „errno: 150" care nu spune nimănui nimic.
  eveniment_id  INT UNSIGNED    NOT NULL,
  membru_id     INT UNSIGNED    NOT NULL,

  -- -----------------------------------------------------------------------
  --  stare
  --
  --  'interesat'   — „mă uit după el, poate vin"
  --  'participant' — „vin"
  --
  --  Valorile noi se adaugă MEREU la coada listei. În MySQL un ENUM se ține
  --  pe disc ca numărul poziției, nu ca text: una strecurată la mijloc ar
  --  renumerota tot ce e după ea și ar preface în tăcere fiecare rând
  --  existent în altceva. (Lecție plătită la sql/011-anulare-eveniment.sql.)
  -- -----------------------------------------------------------------------
  stare         ENUM('interesat','participant') NOT NULL,

  -- -----------------------------------------------------------------------
  --  Momentele
  --
  --  DATETIME scris din PHP cu acum(), niciodată NOW() — vezi regula ceasului
  --  unic din CLAUDE.md. Nu INT cu time() brut: ar fi singura coloană de timp
  --  de felul ăsta din bază, nu s-ar putea citi în phpMyAdmin și nu s-ar
  --  putea compara cu celelalte.
  --
  --  Două coloane, fiindcă starea se schimbă: `creat_la` ține minte când s-a
  --  băgat omul prima dată (cel care s-a arătat interesat acum o lună nu e
  --  același lucru cu cel care a intrat aseară), iar `actualizat_la` când
  --  s-a răzgândit. Cu una singură, prima dată s-ar fi pierdut la trecerea
  --  „interesat" → „participant".
  -- -----------------------------------------------------------------------
  creat_la      DATETIME        NOT NULL,
  actualizat_la DATETIME        NOT NULL,

  PRIMARY KEY (id),

  -- -----------------------------------------------------------------------
  --  Regula, ținută de bază — nu de cod
  --
  --  Un om, un eveniment, un singur rând. Verificarea din PHP („există deja?
  --  atunci fac UPDATE") e bună pentru mesajele frumoase, dar nu e o regulă:
  --  două apăsări în aceeași clipă, de pe telefon și de pe laptop, trec
  --  amândouă de ea și scriu două rânduri. Indexul unic e cel care nu poate
  --  fi păcălit, oricâte cereri ar veni deodată.
  --
  --  Tot el face posibil „INSERT ... ON DUPLICATE KEY UPDATE": o singură
  --  cerere care scrie rândul dacă nu e și îi schimbă starea dacă e, fără
  --  citire înainte și fără fereastră între citire și scriere.
  --
  --  Ordinea (eveniment_id, membru_id), nu invers: prima coloană e cea după
  --  care se caută cel mai des — toți oamenii unui eveniment, pentru numărul
  --  de pe pagină. Un index compus se poate folosi și doar pe începutul lui.
  -- -----------------------------------------------------------------------
  UNIQUE KEY uk_interese_eveniment_membru (eveniment_id, membru_id),

  -- „Câți sunt interesați și câți vin?" — întrebarea de pe fiecare pagină de
  -- eveniment. Cu (eveniment_id, stare) răspunsul se citește din index, fără
  -- să se atingă niciun rând.
  KEY idx_interese_eveniment_stare (eveniment_id, stare),

  -- „La ce evenimente merg eu?" — pentru profil, când va exista. Indexul unic
  -- de mai sus nu ajută aici: începe cu eveniment_id, iar întrebarea asta n-are
  -- eveniment, are om.
  KEY idx_interese_membru (membru_id, creat_la),

  -- -----------------------------------------------------------------------
  --  Ce se întâmplă când dispare evenimentul sau contul
  --
  --  CASCADE pe eveniment: când staff-ul șterge un eveniment anulat, se duc
  --  cu el și înscrierile. E chiar pasul 2 din TODO-ul lui anuleazaEveniment()
  --  din inc/evenimente.php, dar făcut de bază, nu de cod care poate fi uitat.
  --  ATENȚIE: nu schimbă ordinea de acolo — e-mailul către cei înscriși pleacă
  --  ÎNAINTE, fiindcă după ștergere nu mai are cui.
  --
  --  CASCADE pe membru e doar o plasă: conturile nu se șterg, se anonimizează
  --  (vezi inc/stergere.php), deci rândurile rămân legate de un rând care
  --  există. Dacă vreodată se șterge unul de mână, din phpMyAdmin, nu rămân
  --  înscrieri care arată spre nimeni.
  -- -----------------------------------------------------------------------
  CONSTRAINT fk_interese_eveniment
    FOREIGN KEY (eveniment_id) REFERENCES evenimente (id) ON DELETE CASCADE,

  CONSTRAINT fk_interese_membru
    FOREIGN KEY (membru_id) REFERENCES membri (id) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
--  Cum se va folosi (aici doar scris, nu implementat)
--
--  Apasă „Mă interesează" sau „Voi participa" — o singură cerere, care scrie
--  rândul dacă nu e și îi schimbă starea dacă e:
--
--    INSERT INTO interese_evenimente
--           (eveniment_id, membru_id, stare, creat_la, actualizat_la)
--    VALUES (?, ?, ?, ?, ?)
--    ON DUPLICATE KEY UPDATE stare = VALUES(stare),
--                            actualizat_la = VALUES(actualizat_la);
--
--  Apasă din nou pe butonul stării în care e deja — se răzgândește de tot:
--
--    DELETE FROM interese_evenimente
--     WHERE eveniment_id = ? AND membru_id = ? AND stare = ?;
--
--  Condiția pe `stare` din DELETE nu e de prisos: ea face ca butonul să
--  stingă doar starea pe care o arată. Fără ea, o apăsare veche, rămasă
--  într-o filă deschisă de ieri, ar șterge o hotărâre luată între timp în
--  altă filă.
--
--  Numărul de pe pagină:
--
--    SELECT stare, COUNT(*) FROM interese_evenimente
--     WHERE eveniment_id = ? GROUP BY stare;
-- -------------------------------------------------------------------------
