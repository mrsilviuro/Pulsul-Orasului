-- =========================================================================
--  PulsulOrasului.Ro — comentariul „Important" al organizatorului
--
--  Rulează după sql/034-amintire-eveniment.sql. În phpMyAdmin: alege întâi
--  baza din stânga, apoi „Import".
--
--  DE CE
--
--  Organizatorul n-avea cum să spună un lucru care trebuie citit de toți.
--  Putea scrie un comentariu ca oricine altcineva — și se ducea la fundul
--  discuției de îndată ce alte două strângeau o apreciere. „Ne mutăm pe
--  terenul de alături" ajungea al șaptelea comentariu, sub o glumă.
--
--  Steagul ăsta face trei lucruri, și numai trei: ține comentariul PRIMUL
--  în listă, îl arată puțin altfel, și trimite o veste pe e-mail tuturor
--  celor înscriși. În rest e un comentariu obișnuit — se editează, se
--  șterge, se apreciază și i se răspunde întocmai ca oricare altul.
--
--  CINE ÎL POATE PUNE: doar organizatorul evenimentului, și doar pe un
--  comentariu PRINCIPAL. Un răspuns marcat important n-ar avea cum să urce
--  deasupra tuturor — locul lui e sub comentariul la care răspunde. Regula
--  se ține în cod (poateMarcaImportant din inc/comentarii.php), nu aici:
--  baza n-are de unde ști cine e organizatorul unui eveniment fără să se uite
--  în alt tabel, iar o regulă scrisă în două locuri se desparte la prima
--  schimbare.
--
--  DE CE NU E O COLOANĂ PE EVENIMENT („comentariul fixat")
--
--  Fiindcă pot fi mai multe. Un organizator care schimbă locul, apoi ora,
--  apoi cere să se vină cu bani gheață are trei lucruri de spus, nu unul
--  care îl înlocuiește pe celălalt. Stau toate sus, de la cel mai nou la cel
--  mai vechi — ultima veste e cea care contează întâi.
-- =========================================================================

SET NAMES utf8mb4;

ALTER TABLE comentarii
  ADD COLUMN important TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'comentariu de căpătâi, pus de organizator: stă primul și pleacă pe e-mail'
    AFTER text;

-- Nu se pune niciun index: ordinea se face în PHP (grupeazaComentarii), pe
-- rândurile deja citite ale unui singur eveniment — zeci, nu zeci de mii. Un
-- index aici ar fi costat la fiecare comentariu scris și n-ar fi fost citit
-- niciodată.
