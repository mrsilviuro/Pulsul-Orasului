-- =========================================================================
--  PulsulOrasului.Ro — camerele de chat
--
--  Rulează după sql/018-multumiri-eveniment.sql. În phpMyAdmin: alege întâi
--  baza din stânga, apoi „Import".
--
--  Discuția de sub un eveniment (tabelul `comentarii`) e o dezbatere: se scrie
--  rar, se citește târziu, se răspunde punctual, se editează. Chatul e altceva
--  — se vorbește acum, cu cine e acum acolo, iar mâine nimeni nu se mai
--  întoarce să recitească. De aceea nu e o coloană în plus la comentarii, ci
--  un tabel al lui: n-are răspunsuri, n-are aprecieri, n-are editare.
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  UN RÂND = un mesaj scris de cineva într-o cameră
--
--  CAMERELE nu au tabel. Nu sunt lucruri administrate de nimeni: sunt niște
--  nume, iar o cameră „există" din clipa în care cineva scrie în ea. Un tabel
--  `camere` ar fi trebuit ținut la zi cu orașele din config.php și cu fiecare
--  eveniment publicat — două liste care se schimbă singure, în alte locuri, și
--  care s-ar fi despărțit de el la prima nepotrivire.
--
--  Ce e o cameră adevărată se hotărăște la citire, în inc/chat.php
--  (cameraCeruta): un nume care nu duce nicăieri înseamnă „General", nu o
--  eroare. Așa o adresă veche — camera unui eveniment care între timp s-a
--  șters — deschide chatul general, nu un ecran roșu.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mesaje_chat (
  -- BIGINT ca la comentarii: aici se scrie și mai des, iar id-ul e și cursorul
  -- după care browserul cere „ce s-a mai spus de atunci" — nu se poate întoarce
  -- niciodată din urmă.
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- -----------------------------------------------------------------------
  --  camera — numele camerei, cu felul ei în față
  --
  --  Trei forme, atât:
  --
  --    'general'      — camera tuturor
  --    'oras:roman'   — un oraș din config.php, după slugul lui
  --    'ev:targ-de-craciun-a1b2c3' — camera unui eveniment, după slug
  --
  --  Prefixul nu e împodobire. Fără el, un eveniment cu slugul „general" ar fi
  --  intrat peste camera generală, iar unul slugit „roman" peste orașul Roman.
  --  Slugurile de eveniment au o coadă întâmplătoare, deci ciocnirea e
  --  neverosimilă — dar „neverosimil" nu e „imposibil", iar aici despărțirea
  --  costă șase caractere.
  --
  --  VARCHAR(190) și nu mai mult: cu utf8mb4 un index pe coloană urcă la patru
  --  octeți de caracter, iar 190×4 = 760 < 767, limita veche a InnoDB pentru un
  --  index. Slugul de eveniment are cel mult 170, deci încape cu prefixul.
  -- -----------------------------------------------------------------------
  camera    VARCHAR(190)    NOT NULL,

  -- Același tip ca `membri.id` (INT UNSIGNED): o cheie străină cere potrivire
  -- exactă, altfel ALTER TABLE pică cu un „errno: 150" care nu spune nimic.
  membru_id INT UNSIGNED    NOT NULL,

  -- -----------------------------------------------------------------------
  --  mesaj — text curat, neescapat (regula 9 din CLAUDE.md)
  --
  --  Escaparea e la randare, cu h(). Escapat la salvare ar da „&amp;amp;" la a
  --  doua trecere și un text pe care nu-l mai poți căuta.
  --
  --  TEXT, nu VARCHAR: limita adevărată (MESAJ_CHAT_MAX) o ține
  --  inc/validare.php, în CARACTERE, nu în octeți — „ă" ocupă doi octeți, iar o
  --  limită pe octeți i-ar fi tăiat vorba tocmai celui care scrie cu diacritice.
  --
  --  Se golește la ștergere. Rândul rămâne (vezi mai jos), dar vorbele nu:
  --  tocmai ele sunt motivul pentru care a apăsat cineva pe „×".
  -- -----------------------------------------------------------------------
  mesaj     TEXT            NOT NULL,

  -- -----------------------------------------------------------------------
  --  sters / sters_de / sters_la — piatra de mormânt
  --
  --  De ce nu un DELETE curat, când mesajul nici nu are răspunsuri atârnate de
  --  el, ca la comentarii?
  --
  --  Fiindcă browserele care stau cu chatul deschis au deja mesajul pe ecran.
  --  Ele întreabă din când în când „ce s-a mai spus după id-ul N?" — iar un
  --  rând șters de tot nu se mai vede în niciun răspuns, deci mesajul ar fi
  --  rămas pe ecranele deja deschise până la prima reîncărcare. Adică exact la
  --  oamenii de la care trebuia să dispară.
  --
  --  `sters_la` e cursorul celălalt: browserul întreabă „ce s-a mai șters după
  --  clipa asta?", cu o clipă venită de la SERVER, nu de la ceasul lui. Fără
  --  coloană, întrebarea n-ar fi avut după ce să se așeze, iar clientul ar fi
  --  trebuit să reciteasă toată camera ca să bage de seamă o lipsă.
  --
  --  `sters_de` e răspunderea: cine a șters. Nu se arată nicăieri deocamdată —
  --  se citește din phpMyAdmin, ca tot ce ține de staff. ON DELETE SET NULL,
  --  fiindcă ștergerea e un fapt care rămâne adevărat și dacă omul care a
  --  făcut-o nu mai e nicăieri.
  -- -----------------------------------------------------------------------
  sters     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  sters_de  INT UNSIGNED         NULL DEFAULT NULL,
  sters_la  DATETIME             NULL DEFAULT NULL,

  -- DATETIME scris din PHP cu acum(), niciodată NOW() — regula ceasului unic
  -- din CLAUDE.md. Aici s-ar vedea imediat: o oră diferență între ceasul PHP și
  -- cel al bazei ar face ca fiecare mesaj proaspăt să se nască „acum o oră".
  creat_la  DATETIME        NOT NULL,

  PRIMARY KEY (id),

  -- Întrebarea de la fiecare încărcare și de la fiecare întrebare a
  -- browserului: „mesajele din camera asta, în ordine" și „cele de după id-ul
  -- N". Cu (camera, id) amândouă se citesc dintr-o singură trecere prin index,
  -- iar id-ul crescător ține și ordinea în timp.
  KEY idx_chat_camera (camera, id),

  -- „Ce s-a mai șters de atunci, în camera asta?" — a doua întrebare a
  -- browserului, la fiecare trecere.
  KEY idx_chat_sterse (camera, sters_la),

  -- „Când a scris omul ăsta ultima oară?" — limita dintre două mesaje și cea
  -- pe minut, numărate în tabelul propriu al funcției, ca la comentarii și la
  -- mesajele de contact. `incercari_autentificare` rămâne doar pentru intrarea
  -- în cont, unde numărătoarea duce la blocarea contului.
  KEY idx_chat_membru (membru_id, id),

  -- -----------------------------------------------------------------------
  --  Ce se întâmplă când dispare contul
  --
  --  CASCADE e doar o plasă: conturile nu se șterg, se anonimizează (vezi
  --  inc/stergere.php), deci rândurile rămân legate de un rând care există.
  --  Mesajele unui cont anonimizat rămân în discuție — sunt vorbele cuiva, iar
  --  restul discuției atârnă de ele — dar se arată fără nume și fără legătură
  --  spre profil, exact ca la comentarii.
  --
  --  Spre eveniment NU pleacă nicio cheie străină, deși camera poate fi a unui
  --  eveniment. Camera e un NUME, nu o legătură: dacă evenimentul se șterge,
  --  numele nu mai duce nicăieri și camera se deschide ca „General". O cheie
  --  străină ar fi cerut o coloană `eveniment_id` care ar fi trebuit să fie
  --  goală la camerele generale — adică o legătură care nu leagă nimic în cele
  --  mai multe rânduri din tabel.
  -- -----------------------------------------------------------------------
  CONSTRAINT fk_chat_membru
    FOREIGN KEY (membru_id) REFERENCES membri (id) ON DELETE CASCADE,

  CONSTRAINT fk_chat_sters_de
    FOREIGN KEY (sters_de) REFERENCES membri (id) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
