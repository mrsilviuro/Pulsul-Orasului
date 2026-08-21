-- =========================================================================
--  PulsulOrasului.Ro — „FindMe": codurile QR ascunse prin oraș
--
--  Rulează după sql/024-categorii-doar-staff.sql. În phpMyAdmin: alege întâi
--  baza din stânga, apoi „Import".
--
--  CUM MERGE JOCUL, ca să se înțeleagă de ce arată tabelul așa
--
--    1. Omul de casă face un cod nou de pe coduri.php. Deocamdată e un rând
--       singur: un cod de cinci semne, fără eveniment, fără câștigător.
--    2. Tipărește abțibildul cu el și îl lipește undeva prin oraș.
--    3. ABIA PE URMĂ publică evenimentul, scriind codul în formular. Atunci
--       rândul de aici primește `eveniment_id`, iar vânătoarea a început.
--    4. Cine găsește abțibildul îl scanează, ajunge pe findme.php?qr=…, și
--       dacă e primul, câștigă: se scriu `gasit_de` și `gasit_la`, iar
--       evenimentul se declară încheiat.
--
--  ORDINEA ASTA E TOT ROSTUL TABELULUI. Codul trebuie să existe ÎNAINTE de
--  eveniment — altfel n-ai ce tipări. De aceea codurile nu stau într-o coloană
--  din `evenimente`: acolo n-ar fi avut cum să existe mai devreme decât rândul
--  care le ține.
--
--  Iar între pasul 2 și pasul 3 abțibildul e deja pe stâlp, dar nu duce
--  nicăieri. Cine îl scanează atunci trebuie să dea de un „încă n-a început",
--  nu de o pagină de eroare — de aceea `eveniment_id` are voie să fie NULL, și
--  de aceea nu e o lipsă, ci o treaptă a jocului.
-- =========================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
--  1. Care categorie E jocul
--
--  `doar_staff` (sql/024) spune cine poate publica într-o categorie. Nu e
--  același lucru: mâine poate fi o a doua categorie ținută pentru casă care
--  n-are nicio treabă cu abțibildele, și n-ar trebui să ceară un cod la
--  publicare.
--
--  DE CE O COLOANĂ ȘI NU `if ($slug === 'findme')`. Slugul e un text pe care
--  îl scrie omul când adaugă rândul; o literă greșită acolo ar fi stins tot
--  jocul fără să spună nimeni de ce. Iar la a doua categorie de felul ăsta
--  („FindMe iarna", să zicem) ar fi trebuit căutat prin PHP fiecare loc unde
--  scrie numele — exact greșeala de care ne-am ferit la sql/024.
--
--  Ce aduce steagul ăsta cu el, oriunde e 1:
--    * formularul de publicare cere un cod QR, și îl cere obligatoriu;
--    * pagina evenimentului nu mai întreabă „te interesează?" și n-are
--      taburile cu participanți și interesați — la o vânătoare nu se strânge
--      nimeni, se caută;
--    * în locul lor stă caseta vânătorii: ori numărătoarea inversă, ori
--      câștigătorul.
-- -------------------------------------------------------------------------
ALTER TABLE categorii
  ADD COLUMN joc_qr TINYINT(1) NOT NULL DEFAULT 0 AFTER doar_staff;

-- Categoria „FindMe" o ai deja din sql/024; asta o însemnează drept joc.
-- Dacă n-ai adăugat-o încă, rândul e:
--
--   INSERT INTO categorii (nume, slug, ordine, doar_staff, joc_qr)
--        VALUES ('FindMe', 'findme', 99, 1, 1);
UPDATE categorii SET joc_qr = 1 WHERE slug = 'findme';

-- -------------------------------------------------------------------------
--  2. UN RÂND = un abțibild
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS coduri_qr (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- -----------------------------------------------------------------------
  --  cod — cele cinci semne de pe abțibild
  --
  --  Din alfabetul fără semne care se confundă (fără O și 0, fără I, L și 1) —
  --  același cu al parolelor temporare, vezi codDeUnFel() din inc/validare.php.
  --  Codul se scanează, deci în mod obișnuit nu-l scrie nimeni de mână; dar
  --  când telefonul nu vrea să citească, omul se uită la abțibild și tastează,
  --  iar atunci un „0" care e de fapt „O" strică toată vânătoarea.
  --
  --  Cinci semne din 32 înseamnă vreo 33 de milioane de coduri. Nu e o parolă
  --  și nici nu trebuie să fie: cine ghicește unul nu află unde e lipit
  --  abțibildul, iar ca să câștige tot trebuie să fie primul.
  --
  --  MEREU CU MAJUSCULE în bază. Colația e _ci, deci baza n-ar face diferența,
  --  dar textul intră normalizat de curataCodQr() ca să nu ajungă același cod
  --  scris în două feluri pe două abțibilde.
  -- -----------------------------------------------------------------------
  cod           CHAR(5)      NOT NULL,

  -- -----------------------------------------------------------------------
  --  eveniment_id — vânătoarea la care ține codul, dacă a început
  --
  --  NULL cât timp abțibildul e tipărit dar anunțul încă nu s-a publicat.
  --  Vezi mai sus: e o treaptă a jocului, nu o lipsă.
  --
  --  ON DELETE SET NULL, nu CASCADE: dacă evenimentul se șterge vreodată,
  --  abțibildul e tot pe stâlp. Rândul trebuie să rămână, ca findme.php să
  --  aibă ce spune celui care îl scanează.
  -- -----------------------------------------------------------------------
  eveniment_id  INT UNSIGNED NULL DEFAULT NULL,

  -- -----------------------------------------------------------------------
  --  gasit_de / gasit_la — cine a câștigat și când
  --
  --  Amândouă NULL sau amândouă scrise, niciodată una singură: se scriu în
  --  aceeași frază, sub un lacăt (vezi revendicaCodul()).
  --
  --  ON DELETE RESTRICT, ca la organizatorul unui eveniment: câștigătorul e
  --  scris în istoria jocului. Contul șters se anonimizează oricum, nu se
  --  șterge, deci în practică nu se declanșează niciodată.
  -- -----------------------------------------------------------------------
  gasit_de      INT UNSIGNED NULL DEFAULT NULL,
  gasit_la      DATETIME     NULL DEFAULT NULL,

  -- Cine a făcut codul. Doar staff-ul poate, dar rândul spune care anume:
  -- la zece abțibilde lipite de trei oameni, e bine de știut al cui e.
  creat_de      INT UNSIGNED NOT NULL,
  creat_la      DATETIME     NOT NULL,

  PRIMARY KEY (id),

  -- -----------------------------------------------------------------------
  --  Un cod, o singură dată. Cheia asta e și cea care oprește coliziunile:
  --  faCodQrNou() aruncă zaruri și încearcă din nou dacă a dat de un cod care
  --  există deja, exact ca slugul unui eveniment.
  -- -----------------------------------------------------------------------
  UNIQUE KEY uq_cod (cod),

  -- -----------------------------------------------------------------------
  --  Un eveniment, un singur cod.
  --
  --  Nu e o lege a jocului, e o lege a paginii: caseta vânătorii arată UN
  --  abțibild, cu un timer și un câștigător. Cu două coduri pe același
  --  eveniment n-ar fi fost limpede care dintre ele îl încheie.
  --
  --  UNIQUE peste o coloană care poate fi NULL: în MySQL, mai multe NULL-uri
  --  nu se ceartă între ele, deci codurile încă nefolosite încap toate.
  -- -----------------------------------------------------------------------
  UNIQUE KEY uq_eveniment (eveniment_id),

  KEY idx_gasit_de (gasit_de),

  CONSTRAINT fk_qr_eveniment FOREIGN KEY (eveniment_id)
    REFERENCES evenimente (id) ON DELETE SET NULL,
  CONSTRAINT fk_qr_gasit_de FOREIGN KEY (gasit_de)
    REFERENCES membri (id) ON DELETE RESTRICT,
  CONSTRAINT fk_qr_creat_de FOREIGN KEY (creat_de)
    REFERENCES membri (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
