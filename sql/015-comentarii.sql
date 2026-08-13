-- =========================================================================
--  PulsulOrasului.Ro — comentariile de sub un eveniment
--
--  Rulează după sql/014-incheiere-eveniment.sql. În phpMyAdmin: alege întâi
--  baza din stânga, apoi „Import".
--
--  Până acum, secțiunea de comentarii de pe event.php era șablon: oameni
--  inventați sub un eveniment adevărat. De aici încolo e a oamenilor.
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  UN RÂND = un comentariu
--
--  Două feluri, nu mai multe: principale (`parinte_id` gol) și secundare
--  (`parinte_id` = principalul sub care stau). Un răspuns la un răspuns tot
--  secundar se face — se pune sub același principal și doar SPUNE cui îi
--  răspunde, prin `raspuns_la_id`.
--
--  De ce nu adâncime nelimitată: la al treilea nivel de indentare, pe un
--  telefon, comentariul are lățimea unui cuvânt. Iar o discuție care se
--  ramifică în copac nu se mai poate citi de sus în jos — nimeni nu știe de
--  unde să reia. Aici, sub fiecare principal e o singură coloană, în ordinea
--  în care s-a vorbit.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comentarii (
  -- BIGINT, ca la interese_evenimente: aici se scrie des și se șterge des,
  -- iar numerele nu se întorc niciodată din urmă.
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Aceleași tipuri ca `evenimente.id` și `membri.id` (INT UNSIGNED). O cheie
  -- străină cere potrivire exactă de tip, altfel ALTER TABLE pică cu un
  -- „errno: 150" care nu spune nimănui nimic.
  eveniment_id  INT UNSIGNED    NOT NULL,
  membru_id     INT UNSIGNED    NOT NULL,

  -- -----------------------------------------------------------------------
  --  parinte_id — sub care comentariu principal stă
  --
  --  NULL înseamnă „e principal, stă direct sub eveniment".
  --
  --  Un secundar are aici MEREU id-ul unui principal, niciodată al altui
  --  secundar. Regula asta o ține inc/comentarii.php la scriere: dacă cineva
  --  răspunde unui secundar, se ia părintele ACELUIA. Așa lista rămâne plată
  --  și se poate citi dintr-o singură trecere, fără recursivitate.
  -- -----------------------------------------------------------------------
  parinte_id    BIGINT UNSIGNED     NULL DEFAULT NULL,

  -- -----------------------------------------------------------------------
  --  raspuns_la_id — cui i se răspunde, când nu e limpede din poziție
  --
  --  Numai pentru secundarele născute din „Răspunde" apăsat pe un alt
  --  secundar. Atunci sub nume se scrie „către X", fiindcă altfel răspunsul
  --  ar părea că e pentru principal.
  --
  --  Fără cheie străină, dinadins. Comentariul către care arată se poate
  --  șterge, iar o cheie cu ON DELETE SET NULL, pusă pe ACELAȘI tabel pe care
  --  cade și ștergerea în cascadă a evenimentului, e exact locul unde InnoDB
  --  devine imprevizibil. Aici e o trimitere, nu o legătură: dacă nu duce
  --  nicăieri, nu se scrie nimic sub nume și atât.
  -- -----------------------------------------------------------------------
  raspuns_la_id BIGINT UNSIGNED     NULL DEFAULT NULL,

  -- Text curat, neescapat — regula 9 din CLAUDE.md. Escaparea e la randare,
  -- cu h(). TEXT, nu VARCHAR: limita adevărată (COMENTARIU_MAX) o ține
  -- inc/validare.php, în caractere, nu în octeți.
  text          TEXT            NOT NULL,

  -- -----------------------------------------------------------------------
  --  sters — piatra de mormânt
  --
  --  Un comentariu principal care are răspunsuri NU se poate șterge de tot:
  --  ar rămâne suspendate în aer răspunsurile la el, iar discuția n-ar mai
  --  avea început. Atunci se golește: rândul rămâne, dar cu 1 aici, iar în
  --  pagină se scrie „Acest comentariu a fost șters", fără nume și fără chip.
  --
  --  Un principal FĂRĂ răspunsuri și orice secundar se șterg de tot, cu
  --  DELETE. Nu are cine să rămână atârnat de ele.
  --
  --  TINYINT și nu ENUM: e o singură întrebare cu două răspunsuri, iar un
  --  ENUM ar fi trebuit lărgit odată cu fiecare stare nouă la care nu ne
  --  gândim acum.
  -- -----------------------------------------------------------------------
  sters         TINYINT UNSIGNED NOT NULL DEFAULT 0,

  -- -----------------------------------------------------------------------
  --  Momentele
  --
  --  DATETIME scris din PHP cu acum(), niciodată NOW() — regula ceasului unic
  --  din CLAUDE.md.
  --
  --  `editat_la` e NULL până la prima corectură. Nu e același lucru cu
  --  `creat_la`: după el se scrie „(editat)" lângă oră, iar dacă amândouă ar
  --  porni egale, fiecare comentariu s-ar naște deja corectat.
  -- -----------------------------------------------------------------------
  creat_la      DATETIME        NOT NULL,
  editat_la     DATETIME            NULL DEFAULT NULL,

  PRIMARY KEY (id),

  -- Întrebarea de pe fiecare pagină de eveniment: „toate comentariile de
  -- aici, în ordine". Cu (eveniment_id, id) se citesc dintr-o singură trecere
  -- prin index, iar id-ul crescător ține și ordinea în timp.
  KEY idx_comentarii_eveniment (eveniment_id, id),

  -- „Are principalul ăsta răspunsuri?" — întrebarea de la fiecare ștergere.
  KEY idx_comentarii_parinte (parinte_id, id),

  -- „Ale cui sunt?" — pentru când vor apărea pe profil, și pentru curățenie.
  KEY idx_comentarii_membru (membru_id, id),

  -- -----------------------------------------------------------------------
  --  Ce se întâmplă când dispare evenimentul sau contul
  --
  --  CASCADE pe eveniment: când staff-ul șterge un eveniment anulat, se duc
  --  cu el și comentariile. E pasul care lipsea din TODO-ul lui
  --  anuleazaEveniment() din inc/evenimente.php, dar făcut de bază, nu de cod
  --  care poate fi uitat.
  --
  --  CASCADE pe membru e doar o plasă: conturile nu se șterg, se anonimizează
  --  (vezi inc/stergere.php), deci rândurile rămân legate de un rând care
  --  există. Comentariile unui cont anonimizat rămân în discuție — sunt
  --  vorbele cuiva, iar restul discuției atârnă de ele — dar se arată fără
  --  nume și fără legătură spre profil.
  --
  --  Legătura părinte→copil NU e cheie străină. Ar fi una care arată spre
  --  ACELAȘI tabel peste care trece deja cascada de mai sus, iar două cascade
  --  care se întâlnesc pe un tabel sunt exact locul unde InnoDB nu mai
  --  garantează nimic. Ștergerea răspunsurilor odată cu principalul o face
  --  stergeComentariu() din inc/comentarii.php, într-o tranzacție.
  -- -----------------------------------------------------------------------
  CONSTRAINT fk_comentarii_eveniment
    FOREIGN KEY (eveniment_id) REFERENCES evenimente (id) ON DELETE CASCADE,

  CONSTRAINT fk_comentarii_membru
    FOREIGN KEY (membru_id) REFERENCES membri (id) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
--  UN RÂND = un om care a apreciat un comentariu
--
--  Aprecierile se arată ANONIM: pe ecran se vede doar numărul, niciodată
--  cine. Atunci de ce un rând per om, și nu un simplu contor pe comentariu?
--
--  Fiindcă altfel nimeni n-ar putea să-și ia aprecierea înapoi, iar același
--  om ar putea apăsa de o sută de ori. Contorul ar fi arătat o cifră pe care
--  n-ar fi susținut-o nimeni. Rândul aici e memoria apăsării, nu o listă de
--  afișat: numărul se numără din el, cu COUNT(*).
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comentarii_aprecieri (
  comentariu_id BIGINT UNSIGNED NOT NULL,
  membru_id     INT UNSIGNED    NOT NULL,
  creat_la      DATETIME        NOT NULL,

  -- -----------------------------------------------------------------------
  --  Cheia primară e chiar perechea, fără o coloană `id` de prisos
  --
  --  Un om, un comentariu, o singură apreciere — iar regula o ține baza, nu
  --  codul. Verificarea din PHP („a apreciat deja?") e bună pentru mesajul
  --  frumos, dar două apăsări în aceeași clipă, de pe telefon și de pe
  --  laptop, trec amândouă de ea.
  --
  --  Ordinea (comentariu_id, membru_id), nu invers: prima coloană e cea după
  --  care se caută cel mai des — toate aprecierile unui comentariu, pentru
  --  numărul de pe buton. Un index compus se poate folosi și doar pe
  --  începutul lui.
  -- -----------------------------------------------------------------------
  PRIMARY KEY (comentariu_id, membru_id),

  -- „Care dintre comentariile de pe pagina asta le-am apreciat eu?" — o
  -- singură cerere la încărcare, pentru toate butoanele deodată.
  KEY idx_aprecieri_membru (membru_id, comentariu_id),

  CONSTRAINT fk_aprecieri_comentariu
    FOREIGN KEY (comentariu_id) REFERENCES comentarii (id) ON DELETE CASCADE,

  CONSTRAINT fk_aprecieri_membru
    FOREIGN KEY (membru_id) REFERENCES membri (id) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
