-- =========================================================================
--  PulsulOrasului.Ro — e-mailul de mulțumire de după eveniment
--
--  Rulează după sql/017-evaluari.sql. În phpMyAdmin: alege întâi baza din
--  stânga, apoi „Import".
--
--  După ce un eveniment s-a încheiat, fiecare om de pe lista de participanți
--  primește o dată un e-mail: mulțumim că ai venit, iar dacă vrei, treci pe
--  pagină și dă câte o stea celorlalți.
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  evenimente.multumiri_trimise_la — când au plecat mesajele
--
--  Coloana asta există dintr-un singur motiv: „o dată" trebuie să însemne o
--  dată. Un eveniment se încheie în două feluri — organizatorul apasă butonul,
--  sau pur și simplu îi trece ziua — iar al doilea fel nu schimbă nimic în
--  bază: se socotește la fiecare citire, din data evenimentului
--  (vezi evenimentIncheiat() din inc/evenimente.php). Fără un semn scris
--  undeva, cronul n-ar avea cum să deosebească un eveniment încheiat ieri, cu
--  mesajele trimise, de unul încheiat ieri, cu mesajele netrimise: le-ar
--  trimite din nou la fiecare rulare.
--
--  NULL înseamnă „încă n-au plecat". Se umple și când n-a fost nimic de
--  trimis (un eveniment la care n-a venit nimeni), tocmai ca rândul acela să
--  nu fie cercetat la nesfârșit.
--
--  Nu se pune pe evenimentele vechi, existente: dacă le-am lăsa goale, prima
--  rulare a cronului ar trimite deodată mulțumiri pentru tot ce s-a întâmplat
--  vreodată pe site. De aceea, mai jos, tot ce e deja încheiat se însemnează
--  ca „trimis" din capul locului — fără să plece niciun mesaj.
-- -------------------------------------------------------------------------
ALTER TABLE evenimente
  ADD COLUMN multumiri_trimise_la DATETIME NULL DEFAULT NULL AFTER motiv_anulare;

-- -------------------------------------------------------------------------
--  Indexul: cronul întreabă mereu același lucru
--
--  „care evenimente n-au primit încă mesajele" — adică o căutare după coloana
--  de mai sus, cu data evenimentului pe post de a doua condiție. Cât e site-ul
--  mic nu se simte, dar cronul rulează din oră în oră, pentru totdeauna.
-- -------------------------------------------------------------------------
ALTER TABLE evenimente
  ADD KEY idx_evenimente_multumiri (multumiri_trimise_la, data_eveniment);

-- -------------------------------------------------------------------------
--  Trecutul se închide acum, dintr-o dată
--
--  Toate evenimentele care sunt DEJA încheiate în clipa migrării primesc un
--  semn, ca și cum mesajele ar fi plecat. Nu pleacă nimic: e doar felul de a-i
--  spune cronului „de aici încolo".
--
--  Fără rândul ăsta, prima lui rulare ar scoate din bază tot ce s-a petrecut
--  vreodată pe site și ar trimite oamenilor mulțumiri pentru o seară de acum
--  jumătate de an. Data se ia din PHP peste tot pe site; aici, în migrare,
--  n-are cine s-o dea, așa că se folosesc funcțiile bazei. E singurul loc unde
--  se face asta, și nu se compară cu nimic — doar se scrie.
-- -------------------------------------------------------------------------
UPDATE evenimente
   SET multumiri_trimise_la = NOW()
 WHERE multumiri_trimise_la IS NULL
   AND (stare_moderare = 'incheiat' OR data_eveniment < CURDATE());
