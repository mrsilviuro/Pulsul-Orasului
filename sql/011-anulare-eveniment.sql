-- =========================================================================
--  PulsulOrasului.Ro — anularea unui eveniment, cu motiv
--
--  Rulează după sql/010-limita-evenimente.sql. În phpMyAdmin: alege întâi
--  baza din stânga, apoi „Import".
--
--  Până acum, „Anulează evenimentul" ștergea rândul. Era o hotărâre proastă:
--  de un eveniment atârnă oameni care și-au făcut planuri, iar un rând șters
--  nu mai poate spune nimănui de ce nu mai are unde să se ducă. De aici
--  înainte evenimentul rămâne în bază, într-o stare nouă, cu motivul scris de
--  organizator lângă el.
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  stare_moderare: o valoare nouă, „anulat"
--
--  Se adaugă LA COADA listei, nu la mijloc. În MySQL/MariaDB un ENUM se ține
--  pe disc ca numărul poziției, nu ca text: o valoare strecurată între cele
--  existente ar renumerota tot ce e după ea și ar preface în tăcere fiecare
--  „respins" în altceva. La coadă, rândurile de acum nu se ating deloc.
--
--  „anulat" e altceva decât „respins". Respins înseamnă „noi n-am primit-o";
--  anulat înseamnă „organizatorul s-a răzgândit". Pentru public amândouă sunt
--  invizibile, dar pentru cine se uită în bază peste un an sunt două povești
--  diferite, și n-are rost să le amestecăm ca să economisim o valoare.
-- -------------------------------------------------------------------------
ALTER TABLE evenimente
  MODIFY COLUMN stare_moderare
    ENUM('in_asteptare','aprobat','respins','anulat')
    NOT NULL DEFAULT 'in_asteptare';

-- -------------------------------------------------------------------------
--  motiv_anulare
--
--  Ce a scris organizatorul când a anulat. NULL cât timp evenimentul n-a fost
--  anulat — și rămâne NULL pentru toate rândurile de până acum.
--
--  TEXT, nu VARCHAR: e o explicație scrisă de om, nu o etichetă, iar limita
--  adevărată (câte caractere, nu câți octeți) o ține oricum
--  verificaMotivAnulare() din inc/validare.php. O limită în octeți din bază ar
--  fi tăiat mai devreme pe cine scrie cu diacritice.
--
--  Textul intră NEESCAPAT, ca toate celelalte texte din site. Escaparea se
--  face la randare, cu h(). Vezi regula 9 din CLAUDE.md.
-- -------------------------------------------------------------------------
ALTER TABLE evenimente
  ADD COLUMN motiv_anulare TEXT NULL DEFAULT NULL
  AFTER stare_moderare;

-- -------------------------------------------------------------------------
--  membri.este_staff
--
--  Un eveniment anulat nu mai e al nimănui: nici publicul, nici organizatorul
--  n-au ce face cu el. Rămâne de văzut pentru cine face curățenia — staff.
--
--  Până acum nu exista niciun fel de rol în site. Se pune de mână, din
--  phpMyAdmin, exact ca limita_evenimente_active: nu există interfață pentru
--  el și nici nu trebuie deocamdată. Când va exista pagina de moderare (vezi
--  roadmapul din CLAUDE.md), tot de coloana asta va atârna.
--
--  TINYINT(1) NOT NULL DEFAULT 0, nu NULL: „nu e staff" e răspunsul pentru
--  toată lumea, nu o lipsă de informație.
-- -------------------------------------------------------------------------
ALTER TABLE membri
  ADD COLUMN este_staff TINYINT(1) NOT NULL DEFAULT 0
  AFTER limita_evenimente_active;
