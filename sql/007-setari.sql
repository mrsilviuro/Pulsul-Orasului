-- =========================================================================
--  PulsulOrasului.Ro — pagina de setări
--
--  Rulează după schema.sql. În phpMyAdmin: alege întâi baza din stânga,
--  apoi „Import".
--
--  Aduce: telefonul, preferința de newsletter și tot ce ține de ștergerea
--  contului cu răgaz de treizeci de zile.
-- =========================================================================

-- -------------------------------------------------------------------------
--  telefon
--
--  Opțional, cerut abia din setări, nu la înregistrare.
--
--  Se ține în forma națională, zece cifre, așa cum o scrie omul pe hârtie:
--  0722334455. Formele +40 și 0040 se aduc la fel încă din PHP, ca două
--  scrieri ale aceluiași număr să nu ajungă două rânduri diferite.
--
--  Nu se arată nicăieri deocamdată. Va folosi organizatorilor de evenimente,
--  când vom lega participanții de evenimente — atunci se va face și
--  vizibilitatea.
-- -------------------------------------------------------------------------
ALTER TABLE membri
  ADD COLUMN telefon VARCHAR(20) NULL DEFAULT NULL AFTER email;

-- -------------------------------------------------------------------------
--  newsletter
--
--  Pornit pentru toată lumea, și pentru conturile care există deja: NOT NULL
--  cu DEFAULT 1 umple din start rândurile vechi cu 1.
-- -------------------------------------------------------------------------
ALTER TABLE membri
  ADD COLUMN newsletter TINYINT(1) NOT NULL DEFAULT 1 AFTER telefon;

-- -------------------------------------------------------------------------
--  Ștergerea contului, cu răgaz
--
--  cerere_stergere = momentul în care omul a apăsat linkul din e-mail. Cât
--  timp e completat, contul e „pe ducă": rămâne întreg, dar are un termen.
--
--  De ce nu o valoare nouă în „stare": pentru că starea contului e citită la
--  fiecare cerere și decide dacă omul poate intra. Aici vrem exact pe dos —
--  intrarea trebuie să reușească, tocmai ca să putem anula ștergerea. O
--  coloană separată ține cele două lucruri despărțite.
--
--  Ceasul e al PHP-ului, ca peste tot: valorile se scriu din aplicație, nu cu
--  NOW(). Vezi regula ceasului unic din CLAUDE.md.
-- -------------------------------------------------------------------------
ALTER TABLE membri
  ADD COLUMN cerere_stergere DATETIME NULL DEFAULT NULL AFTER autentificat_la;

-- Token-ul din e-mailul de confirmare. Ca la confirmarea adresei, în bază stă
-- doar sha256 al lui, nu token-ul în sine.
ALTER TABLE membri
  ADD COLUMN token_stergere CHAR(64) NULL DEFAULT NULL AFTER cerere_stergere;

ALTER TABLE membri
  ADD COLUMN token_stergere_expira DATETIME NULL DEFAULT NULL AFTER token_stergere;

-- Când a fost anonimizat, dacă a fost. Rândul rămâne în bază pentru totdeauna:
-- de el atârnă evenimentele organizate și participările.
ALTER TABLE membri
  ADD COLUMN anonimizat_la DATETIME NULL DEFAULT NULL AFTER token_stergere_expira;

-- -------------------------------------------------------------------------
--  Starea „șters"
--
--  Contul anonimizat nu mai poate fi folosit la intrare: membruCurent() cere
--  „activ". Rândul rămâne, dar omul din spatele lui nu mai există.
-- -------------------------------------------------------------------------
ALTER TABLE membri
  MODIFY COLUMN stare ENUM('neconfirmat','activ','suspendat','sters')
    NOT NULL DEFAULT 'neconfirmat';

-- Căutarea după token, la apăsarea linkului din e-mail.
ALTER TABLE membri
  ADD KEY idx_membri_token_stergere (token_stergere);

-- Cronul cere „cine are cerere_stergere mai veche de treizeci de zile".
ALTER TABLE membri
  ADD KEY idx_membri_cerere_stergere (cerere_stergere);
