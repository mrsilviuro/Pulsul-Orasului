-- =========================================================================
--  PulsulOrasului.Ro — mesajele din formularul de contact
--
--  Rulează după schema.sql. În phpMyAdmin: alege întâi baza din stânga,
--  apoi „Import".
-- =========================================================================

-- -------------------------------------------------------------------------
--  mesaje_contact
--
--  Până acum formularul de contact nu trimitea nimic nicăieri. De acum
--  mesajul se scrie aici ȘI pleacă pe e-mail. Baza e pentru viitorul panou de
--  administrare; e-mailul e ca să afli imediat, fără să intri în panou.
--
--  De ce amândouă: un e-mail se poate pierde în „Spam", iar un rând în bază
--  nu sună când ajunge. Fiecare acoperă slăbiciunea celuilalt.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mesaje_contact (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- -----------------------------------------------------------------------
  --  Cine a scris
  --
  --  NULL pentru vizitatorii care nu au cont — și aceia au voie să scrie.
  --  Pentru membri, numele, adresa și telefonul se iau din cont, nu din
  --  formular: altfel oricine ar putea scrie sub numele altcuiva, iar mesajul
  --  ar arăta ca venind de la un membru cunoscut.
  --
  --  ON DELETE SET NULL, nu CASCADE: contul care pleacă nu trebuie să ia cu el
  --  mesaje la care poate n-am apucat să răspundem. Rămân, fără legătură.
  -- -----------------------------------------------------------------------
  membru_id  INT UNSIGNED     NULL DEFAULT NULL,

  -- Copiile de la momentul trimiterii. Se păstrează chiar dacă membrul își
  -- schimbă între timp datele sau își șterge contul: mesajul trebuie să spună
  -- cine l-a scris atunci, nu cine e omul acum.
  nume       VARCHAR(60)  NOT NULL,
  prenume    VARCHAR(60)  NOT NULL,
  email      VARCHAR(190) NOT NULL,
  telefon    VARCHAR(20)  NOT NULL,
  mesaj      TEXT         NOT NULL,

  -- Pentru limitarea numărului de mesaje de la aceeași conexiune. VARBINARY(16)
  -- ca peste tot în proiect, ca să încapă și IPv4, și IPv6 (vezi ipBinar()).
  ip         VARBINARY(16)    NULL DEFAULT NULL,

  -- Ceasul e al PHP-ului, niciodată NOW(). Vezi regula din CLAUDE.md.
  creat_la   DATETIME     NOT NULL,

  -- Pentru panoul de administrare de mai târziu. Nu se folosește încă, dar e
  -- mai ieftin acum decât un ALTER pe un tabel plin.
  citit_la   DATETIME         NULL DEFAULT NULL,

  PRIMARY KEY (id),

  -- „Câte mesaje de la acest IP în ultima oră?" — exact forma întrebării.
  KEY idx_mesaje_ip     (ip, creat_la),
  -- „Când a scris ultima dată acest membru?"
  KEY idx_mesaje_membru (membru_id, creat_la),
  -- Pentru listarea din panou, cele mai noi întâi.
  KEY idx_mesaje_data   (creat_la),

  CONSTRAINT fk_mesaje_membru
    FOREIGN KEY (membru_id) REFERENCES membri (id) ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
