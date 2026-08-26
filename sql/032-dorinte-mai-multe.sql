-- =========================================================================
--  PulsulOrasului.Ro — trei dorințe deodată, și ștergerea lor
--
--  Rulează după sql/031-newsletter-zilnic.sql. În phpMyAdmin: alege întâi
--  baza din stânga, apoi „Import".
--
--  CE SE SCHIMBĂ
--
--  Până acum omul avea dreptul la O SINGURĂ dorință, iar aceea nu se putea
--  lua înapoi: odată publicată, stătea șapte zile și atât. Amândouă erau
--  prea strâmte. Cine se gândește la trei lucruri deosebite trebuia să
--  aleagă unul și să aștepte o săptămână pentru al doilea; iar cine scria
--  ceva în grabă, sau se răzgândea, n-avea ce face.
--
--  De acum sunt trei deodată (DORINTE_DEODATA din inc/dorinte.php) și se pot
--  șterge. Cele șapte zile de tablă rămân cum erau.
--
--  Limita de trei se ține la SCRIERE, nu în butonul de pe ecran, și sub
--  lacăt — vezi scrieDorintaSubLacat(). Nu e o coloană aici: se numără
--  rândurile în lucru ale omului.
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  sters_la — când și-a luat omul dorința înapoi
--
--  ȘTERGEREA E MOALE, ȘI E DINADINS. Rândul rămâne pentru totdeauna, ca și
--  cel ieșit de pe tablă: antetul lui sql/023 spune de ce — mai târziu vrem
--  să putem spune câte dorințe și-au pus oamenii de-a lungul timpului, iar o
--  tablă golită n-ar mai fi știut. O ștergere adevărată ar fi luat din
--  numărătoarea aceea tocmai dorințele la care cineva chiar s-a gândit.
--
--  Ce face NULL-ul: cât timp e NULL, dorința e în viață — se vede pe tablă
--  (dacă e aprobată și n-a împlinit șapte zile) și ocupă unul din cele trei
--  locuri ale omului. Cu o dată în el, dispare de pe tablă, iese din
--  numărătoare și face loc alteia, dar rândul e tot acolo.
--
--  DATETIME scris din PHP cu acum(), niciodată NOW() — regula ceasului unic
--  din CLAUDE.md.
--
--  NU se confundă cu ștergerea staff-ului din admin-dorinte.php: aceea e un
--  DELETE adevărat și rămâne așa. Ea e pentru ce n-are ce căuta în
--  numărătoare — o înjurătură, un test. Asta e pentru omul care se
--  răzgândește, iar el n-a greșit cu nimic.
-- -------------------------------------------------------------------------
ALTER TABLE dorinte
  ADD COLUMN sters_la DATETIME NULL DEFAULT NULL AFTER publicat_la;

-- -------------------------------------------------------------------------
--  „Ce are omul ăsta în lucru?" — întrebarea pusă de trei ori la fiecare
--  încărcare a primei pagini: câte locuri din cele trei sunt luate, ce se
--  scrie în tabelul lui, și dacă mai are voie să scrie una.
--
--  Cheia veche (`membru_id`, `creat_la`) nu mai ajunge: acum se cerne întâi
--  după `sters_la IS NULL`, iar rândurile șterse rămân în tabel pentru
--  totdeauna, deci cu timpul ar fi tot mai multe de sărit.
-- -------------------------------------------------------------------------
ALTER TABLE dorinte
  ADD KEY idx_dorinte_ale_mele (membru_id, sters_la, stare_moderare);
