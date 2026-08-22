-- =========================================================================
--  PulsulOrasului.Ro — ștampila newsletterului zilnic
--
--  Rulează după sql/030-incercari-qr.sql. În phpMyAdmin: alege întâi baza din
--  stânga, apoi „Import".
--
--  DE CE
--
--  Bifa din setări spune „cel mult unul pe zi", iar coloana asta e singurul
--  lucru care chiar o ține. Fără ea:
--
--    - un cron pornit din greșeală de două ori trimite de două ori;
--    - o rulare de mână, ca să se vadă dacă merge, ajunge la toată lumea;
--    - un cron care se blochează la jumătate și e repornit trimite din nou
--      celor care primiseră deja.
--
--  Nimic din astea nu se poate lua înapoi: un e-mail plecat a plecat. De aceea
--  ștampila se pune ÎNAINTE de trimitere, nu după — vezi
--  insemneazaNewsletterTrimis() din inc/newsletter.php.
--
--  DE CE E O DATĂ, NU UN DATETIME
--
--  Fiindcă întrebarea e „i-a plecat ceva ASTĂZI?", nu „când anume". O dată se
--  compară de-a dreptul cu ziua de azi, fără socoteli cu ceasul, iar o
--  întârziere a cronului de la 12:00 la 12:40 nu schimbă nimic.
--
--  DE CE PE RÂNDUL OMULUI, ȘI NU ÎNTR-UN TABEL AL LOR
--
--  Fiindcă nu ne trebuie istoria: „i-a plecat azi?" e tot ce se întreabă
--  vreodată. Un tabel cu un rând pe om pe zi ar fi crescut cu câteva sute de
--  rânduri zilnic ca să răspundă la aceeași întrebare. Dacă vreodată vrem să
--  numărăm câte newslettere au plecat de-a lungul timpului, atunci se face
--  tabelul — dar nu înainte să existe pagina care s-o arate.
-- =========================================================================

SET NAMES utf8mb4;

ALTER TABLE membri
  ADD COLUMN newsletter_trimis_la DATE NULL DEFAULT NULL
    COMMENT 'ziua în care i-a plecat ultimul newsletter zilnic'
    AFTER newsletter;

-- Cheia după care se aleg abonații: „bifa pornită ȘI nu i-a plecat azi".
-- Fără ea, la fiecare rulare se citește tabelul întreg de membri.
CREATE INDEX idx_membri_newsletter
  ON membri (newsletter, newsletter_trimis_la);
