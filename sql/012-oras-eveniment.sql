-- =========================================================================
--  PulsulOrasului.Ro — orașul evenimentului
--
--  Rulează după sql/011-anulare-eveniment.sql. În phpMyAdmin: alege întâi
--  baza din stânga, apoi „Import".
--
--  Site-ul a pornit pentru un singur oraș, Roman, așa că orașul nu se scria
--  nicăieri: se subînțelegea. De acum se scrie, ca ziua în care apare al
--  doilea oraș să fie o linie în plus în inc/config.php, nu o migrare pe un
--  tabel plin de evenimente despre care nimeni nu mai știe unde au avut loc.
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  oras
--
--  Lângă `locatie`, fiindcă asta e: prima jumătate a adresei. Împreună se
--  citesc „Roman · Piața Roman-Vodă".
--
--  VARCHAR(80), nu o legătură către un tabel de orașe: lista trăiește în
--  inc/config.php (cheia 'orase'), iar un tabel cu un rând ar fi cerut o
--  pagină de administrare pentru ceva ce se schimbă o dată pe an. Aceeași
--  lungime ca `membri.localitate`, ca să nu existe două limite pentru același
--  fel de nume.
--
--  Numele intră ca text, exact cum e scris în config. Dacă mâine un oraș iese
--  din listă, evenimentele lui rămân cu numele scris aici — nu se pierde
--  nimic, doar nu se mai poate alege.
--
--  NOT NULL, dar cu DEFAULT: coloana se adaugă peste rânduri care există
--  deja, iar ele n-au de unde ști ce oraș aveau. Le punem pe toate pe 'Roman'
--  la pasul următor — e adevărul, fiindcă până azi n-a existat altul.
-- -------------------------------------------------------------------------
ALTER TABLE evenimente
  ADD COLUMN oras VARCHAR(80) NOT NULL DEFAULT 'Roman'
  AFTER descriere;

-- Evenimentele de dinainte de coloană. DEFAULT-ul de mai sus le-a pus deja
-- 'Roman', dar o scriem apăsat: un ALTER care într-o zi ar fi rulat fără
-- DEFAULT ar fi lăsat rândurile cu șirul gol, și nimeni n-ar fi observat.
UPDATE evenimente SET oras = 'Roman' WHERE oras = '' OR oras IS NULL;

-- „Ce se întâmplă în Roman luna asta?" — întrebarea pe care o va pune prima
-- pagină în ziua în care are mai multe orașe de arătat. Fără index, ar citi
-- tot tabelul ca să afle.
ALTER TABLE evenimente
  ADD KEY idx_evenimente_oras (oras, stare_moderare, data_eveniment);
