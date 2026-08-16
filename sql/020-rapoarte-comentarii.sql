-- =========================================================================
--  PulsulOrasului.Ro — comentariile raportate
--
--  Rulează după sql/019-newsletter.sql. În phpMyAdmin: alege întâi baza din
--  stânga, apoi „Import".
--
--  Până acum, un comentariu răutăcios se putea doar șterge — și numai de
--  autorul lui sau de staff, care trebuia să treacă întâmplător pe acolo. Cine
--  îl citea n-avea cum să spună nimănui. De aici încolo are un buton.
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  UN RÂND = un om care a raportat un comentariu
--
--  DE CE UN TABEL, ȘI NU O COLOANĂ `raportat` PE COMENTARIU
--
--  Fiindcă raportul se poate lua înapoi. Cu un simplu semn pe comentariu,
--  al doilea om care apasă ar fi stins raportul primului, iar același om ar fi
--  putut apăsa de o sută de ori. Rândul de aici e memoria apăsării fiecăruia,
--  iar numărul se numără din el, cu COUNT(*).
--
--  E exact alegerea făcută la `comentarii_aprecieri` (vezi sql/015), din exact
--  același motiv, și e scrisă la fel dinadins: două lucruri care se poartă la
--  fel n-au de ce să arate altfel.
--
--  Spre deosebire de aprecieri, numărul NU se arată nimănui în pagină. Un
--  contor de rapoarte la vedere ar fi devenit o unealtă de rușinare publică,
--  și încă una ușor de umflat de câțiva prieteni. Omul vede doar dacă el
--  însuși a raportat; restul e treaba staff-ului.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comentarii_rapoarte (
  comentariu_id BIGINT UNSIGNED NOT NULL,
  membru_id     INT UNSIGNED    NOT NULL,
  creat_la      DATETIME        NOT NULL,

  -- -----------------------------------------------------------------------
  --  Cheia primară e chiar perechea, fără o coloană `id` de prisos
  --
  --  Un om, un comentariu, un singur raport — iar regula o ține BAZA, nu
  --  codul. Verificarea din PHP („a raportat deja?") e bună pentru butonul
  --  care se aprinde, dar două apăsări în aceeași clipă, de pe telefon și de
  --  pe laptop, trec amândouă de ea.
  --
  --  Ordinea (comentariu_id, membru_id), nu invers: prima coloană e cea după
  --  care se caută cel mai des — toate rapoartele unui comentariu, pentru
  --  lista de mai târziu a staff-ului. Un index compus se poate folosi și doar
  --  pe începutul lui.
  -- -----------------------------------------------------------------------
  PRIMARY KEY (comentariu_id, membru_id),

  -- „Pe care dintre comentariile de pe pagina asta le-am raportat eu?" — o
  -- singură cerere la încărcare, pentru toate butoanele deodată.
  KEY idx_rapoarte_membru (membru_id, comentariu_id),

  -- „Ce s-a raportat, și când?" — întrebarea paginii de moderare care va veni.
  -- Fără ea, lista aceea ar fi cerut o trecere prin tot tabelul.
  KEY idx_rapoarte_cand (creat_la),

  -- -----------------------------------------------------------------------
  --  Ce se întâmplă când dispare comentariul sau contul
  --
  --  CASCADE pe comentariu: un comentariu șters de tot nu mai are ce să fie
  --  raportat, iar rândurile lui n-ar mai duce nicăieri. Cel GOLIT (cu piatră
  --  de mormânt, `sters = 1`) rămâne în bază, deci rapoartele lui rămân și
  --  ele — și e bine așa: staff-ul are dreptul să vadă ce s-a raportat, chiar
  --  dacă autorul a șters între timp.
  --
  --  CASCADE pe membru e o plasă: conturile nu se șterg, se anonimizează (vezi
  --  inc/stergere.php). Dacă totuși dispare un rând din `membri`, rapoartele
  --  lui n-au de ce să rămână: un raport fără cel care l-a dat nu se poate nici
  --  cântări, nici lua înapoi.
  -- -----------------------------------------------------------------------
  CONSTRAINT fk_rapoarte_comentariu
    FOREIGN KEY (comentariu_id) REFERENCES comentarii (id) ON DELETE CASCADE,

  CONSTRAINT fk_rapoarte_membru
    FOREIGN KEY (membru_id) REFERENCES membri (id) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
