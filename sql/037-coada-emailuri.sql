-- =========================================================================
--  PulsulOrasului.Ro — coada de e-mailuri
--
--  Rulează după sql/036-fara-newsletter.sql. În phpMyAdmin: alege întâi baza
--  din stânga, apoi „Import".
--
--  DE CE
--
--  Găzduirea duce zece mesaje pe minut și șase sute pe ceas. Sunt însă locuri
--  pe site unde O SINGURĂ APĂSARE naște zeci de mesaje: cineva cu două sute de
--  urmăritori publică un anunț, un eveniment cu treizeci de înscriși se
--  anulează, organizatorul scrie un anunț important. Trimise pe loc, alea
--  sar plafonul — iar cine îl sare nu primește un avertisment, ci rămâne fără
--  poștă pentru tot restul orei, cu tot cu confirmările de cont și
--  recuperările de parolă ale unor oameni care n-aveau nicio treabă cu ele.
--
--  De acum, mesajele acelea nu mai pleacă din cererea web: se scriu aici, iar
--  un cron le ia câte puține și le duce. Pagina răspunde imediat, oricâți ar fi
--  de înștiințat.
--
--  CE INTRĂ AICI ȘI CE NU. Numai ce pleacă în serie. Confirmarea de cont,
--  parola temporară și celelalte mesaje care răspund unei apăsări pleacă mai
--  departe PE LOC: omul stă și așteaptă, sunt rare, și — cel mai important —
--  un cron oprit n-are voie să însemne că nimeni nu-și mai poate face cont.
--  Ele ajung aici DOAR dacă trimiterea pe loc a dat greș, ca să fie
--  reîncercate (vezi trimiteEmail din inc/email.php).
--
--  CE SE ȚINE ÎN RÂND: nu HTML-ul gata compus (ar fi fost 12 KB pe rând), ci
--  BLOCURILE din care îl face șablonul — un salut, câteva paragrafe, un buton.
--  Vreo jumătate de kilooctet. Șablonul se aplică la trimitere.
-- =========================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS coada_emailuri (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- -----------------------------------------------------------------------
  --  catre / subiect — cui și cu ce scrie pe plic
  --
  --  190 la adresă, ca peste tot pe site: cu utf8mb4 un index urcă la patru
  --  octeți de caracter, iar 190×4 = 760 < 767, limita veche a InnoDB.
  -- -----------------------------------------------------------------------
  catre      VARCHAR(190) NOT NULL,
  subiect    VARCHAR(255) NOT NULL,

  -- -----------------------------------------------------------------------
  --  blocuri — cuprinsul mesajului, ca JSON
  --
  --  Exact tabloul pe care îl primește sablonEmail(): 'salut', 'paragrafe',
  --  'buton', 'lista' și celelalte. NU HTML-ul ieșit din el.
  --
  --  DE CE AȘA. Trei motive, și toate trei contează:
  --    - rândurile rămân mici (o jumătate de KB, nu douăsprezece);
  --    - o îndreptare de text din șablon prinde și mesajele deja puse la rând;
  --    - nu ținem zile întregi copii ale unor scrisori întregi.
  --
  --  TEXT, nu JSON: tipul JSON al MySQL n-ar aduce nimic aici — nu se caută și
  --  nu se filtrează niciodată după ce e înăuntru, se citește întreg și se dă
  --  mai departe. TEXT merge pe orice versiune de MySQL și MariaDB.
  -- -----------------------------------------------------------------------
  blocuri    TEXT         NOT NULL,

  -- Anteturi în plus, tot ca JSON. Aproape mereu gol; există pentru ziua în
  -- care va fi din nou un mesaj care vine nechemat și are nevoie de
  -- „List-Unsubscribe".
  anteturi   TEXT             NULL DEFAULT NULL,

  -- -----------------------------------------------------------------------
  --  prioritate — 0 obișnuit, 1 înaintea celorlalte
  --
  --  DOUĂ VALORI, NU CINCI. Există dintr-un motiv anume: cineva cu două sute
  --  de urmăritori publică un anunț, iar rândul acela ține douăzeci și cinci
  --  de minute. Dacă tocmai atunci se anulează un eveniment de diseară, cei
  --  înscriși la el n-au de ce să afle abia peste o jumătate de oră.
  --
  --  Deci: anularea unui eveniment și reîncercarea unui mesaj care a dat greș
  --  trec înainte. Restul așteaptă la rând, în ordinea în care au venit.
  -- -----------------------------------------------------------------------
  prioritate TINYINT UNSIGNED NOT NULL DEFAULT 0,

  -- DATETIME scris din PHP cu acum(), niciodată NOW() — regula ceasului unic.
  creat_la   DATETIME     NOT NULL,

  -- -----------------------------------------------------------------------
  --  luat_de / luat_la — semnul că un cron a pus mâna pe rândul ăsta
  --
  --  DE CE E NEVOIE DE ELE. O rulare care se împotmolește (serverul de poștă
  --  nu răspunde, zece secunde de așteptare de fiecare mesaj) poate să nu se
  --  fi terminat când pornește următoarea. Fără semnul ăsta, amândouă ar citi
  --  aceleași rânduri și ar trimite aceleași mesaje.
  --
  --  Se pune cu un UPDATE … LIMIT, adică într-o singură mișcare a bazei: cine
  --  apucă primul le are, celălalt nu găsește nimic. Aceeași croială ca la
  --  revendicarea unui abțibild — hotărârea stă în `WHERE`, nu în PHP.
  --
  --  `luat_de` e o cifră de unică folosință a rulării, nu un id de proces:
  --  după UPDATE se citesc înapoi exact rândurile care poartă cifra aceea.
  -- -----------------------------------------------------------------------
  luat_de    CHAR(32)         NULL DEFAULT NULL,
  luat_la    DATETIME         NULL DEFAULT NULL,

  -- Când a plecat cu adevărat. Cât e NULL, mesajul încă așteaptă.
  trimis_la  DATETIME         NULL DEFAULT NULL,

  -- -----------------------------------------------------------------------
  --  incercari / eroare — de câte ori s-a încercat și ce a spus serverul
  --
  --  Un rând luat de o rulare care a murit între „am luat" și „am trimis"
  --  rămâne agățat. După COADA_MINUTE_BLOCAT se ia din nou — dar numai dacă
  --  n-a trecut de COADA_INCERCARI_MAX. Fără plafonul ăla, un mesaj către o
  --  adresă care nu există s-ar învârti în coadă pentru totdeauna.
  --
  --  `eroare` e vorba serverului de poștă, tăiată la 255: „over quota",
  --  „relay denied". E singurul loc de pe site unde se vede de ce n-a plecat
  --  un mesaj.
  -- -----------------------------------------------------------------------
  incercari  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  eroare     VARCHAR(255)     NULL DEFAULT NULL,

  PRIMARY KEY (id),

  -- „Ce urmează la rând?" — singura întrebare pusă la fiecare pornire a
  -- cronului, adică o dată pe minut. Ordinea din cheie e chiar ordinea din
  -- `ORDER BY`: întâi ce e urgent, apoi ce a venit mai demult.
  KEY idx_coada_urmatorul (trimis_la, prioritate, id),

  -- „Ce e destul de vechi cât să se șteargă?" — o dată pe rulare.
  KEY idx_coada_curatenie (trimis_la, creat_la)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
--  RÂNDURILE DE AICI SE ȘTERG, spre deosebire de restul site-ului.
--
--  Nu calcă regula „nu se șterge nimic": aceea e despre ce a scris omul —
--  dorințe, comentarii, evenimente — lucruri pe care mai târziu vrem să le
--  putem număra sau arăta. Un rând de aici e un plic, iar un plic ajuns la
--  destinație nu mai e nimănui de folos.
--
--  NU LA TRIMITERE, ÎNSĂ. Se șterg după COADA_ZILE_PASTRARE (7), în aceeași
--  rulare a cronului, ca rândurile vechi din `incercari_qr`. Cele șapte zile
--  sunt tot ce ține loc de log: „i-a plecat lui X mesajul?" e o întrebare care
--  se pune, iar ștergerea pe loc ar fi lăsat-o fără răspuns. Tot ele fac cu
--  putință și numărătoarea „câte au plecat în ultimul ceas".
-- -------------------------------------------------------------------------
