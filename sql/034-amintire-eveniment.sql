-- =========================================================================
--  PulsulOrasului.Ro — ștampila mementoului de dinaintea unui eveniment
--
--  Rulează după sql/033-urmariri.sql. În phpMyAdmin: alege întâi baza din
--  stânga, apoi „Import".
--
--  DE CE
--
--  Site-ul avea nouăsprezece feluri de e-mail și niciunul nu-i spunea omului
--  că URMEAZĂ ceva la care s-a înscris. Îi mulțumea după, îi trimitea
--  newsletterul dimineața — dar cine apăsa „Particip" pe 3 pentru o seară de
--  pe 20 nu mai auzea nimic până când trecea. Acolo se pierd oamenii: nu la
--  înscriere, ci între înscriere și seara aceea. Iar la un eveniment cu locuri
--  limitate, unul care uită ține degeaba un loc ocupat.
--
--  Coloana asta e singurul lucru care ține „o singură dată pe eveniment".
--  Fără ea, cronul din oră în oră ar trimite același memento la fiecare
--  trecere cât timp evenimentul e în fereastra de trei ore — adică de trei
--  ori, fiecărui om.
--
--  DE CE PE EVENIMENT, ȘI NU PE OM
--
--  Fiindcă mementoul pleacă o dată, către toți cei de pe listă în clipa
--  aceea. Un tabel cu un rând pe om pe eveniment ar fi răspuns la aceeași
--  întrebare cu de zece ori mai multe rânduri.
--
--  URMAREA, care e voită: cine se înscrie DUPĂ ce a plecat mementoul nu mai
--  primește niciunul. E în regulă — tocmai s-a înscris la ceva care începe în
--  mai puțin de trei ore, deci știe foarte bine.
--
--  DE CE E DATETIME, NU DATE
--
--  Spre deosebire de `membri.newsletter_trimis_la`, unde întrebarea e „i-a
--  plecat ceva ASTĂZI?", aici întrebarea e „a plecat?", iar ceasul se
--  păstrează fiindcă la o încercare vrei să vezi ora, nu ziua: fereastra
--  întreagă are trei ore. E aceeași croială ca `multumiri_trimise_la`.
-- =========================================================================

SET NAMES utf8mb4;

ALTER TABLE evenimente
  ADD COLUMN amintire_trimisa_la DATETIME NULL DEFAULT NULL
    COMMENT 'când le-a plecat participanților mementoul de dinaintea începerii'
    AFTER multumiri_trimise_la;

-- Cheia după care se aleg evenimentele: „neamintit încă ȘI cu ziua prin
-- preajmă". Fără ea, cronul citește la fiecare oră tabelul întreg de
-- evenimente — care crește pentru totdeauna, fiindcă rândurile nu se șterg.
CREATE INDEX idx_evenimente_amintire
  ON evenimente (amintire_trimisa_la, data_eveniment);
