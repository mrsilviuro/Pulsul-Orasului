-- =========================================================================
--  PulsulOrasului.Ro — anunțul fixat în capul primei pagini
--
--  Rulează după sql/028-feedback-instiintat.sql. În phpMyAdmin: alege întâi
--  baza din stânga, apoi „Import".
--
--  DE CE
--
--  Prima pagină se așază singură, după ceas: ce urmează întâi, apoi ce a
--  trecut. E ordinea bună în aproape toate zilele — dar nu în toate. Când
--  orașul are un lucru care CHIAR contează (o sărbătoare, o strângere de
--  ajutoare, o vânătoare pusă la cale de casă), el trebuie să stea sus,
--  oricât de departe ar fi ziua lui și oricâte s-ar întâmpla mai devreme.
--
--  Asta e coloana. Cât e pusă, anunțul stă primul și se vede altfel.
--
--  DOAR OMUL CASEI o pune și o ia. Nu e o unealtă a organizatorului: dacă ar
--  fi, ar apăsa-o toți, iar „primul în listă" n-ar mai însemna nimic — ar fi
--  doar rândul obișnuit, scris cu alt cuvânt.
--
--  NU SE STINGE SINGURĂ. Nici când evenimentul se încheie, nici când se
--  anulează. Un anunț anulat care rămâne fixat e chiar ce trebuie uneori:
--  vestea că nu se mai ține trebuie să ajungă la toți cei care o așteptau, și
--  ajunge tocmai stând sus. Se scoate cu mâna, de cine a pus-o.
--
--  DE CE O DATĂ, ȘI NU UN 0/1. Cu un steag s-ar ști doar „da/nu". Cu data se
--  vede și DE CÂND stă acolo — de folos peste trei luni, când cineva se
--  întreabă de ce anunțul ăla e tot în cap. Și mai e ceva: două anunțuri
--  fixate se așază între ele după ea, cel mai proaspăt fixat deasupra, fără
--  să mai fie nevoie de vreo a doua coloană.
-- =========================================================================

SET NAMES utf8mb4;

ALTER TABLE evenimente
  ADD COLUMN fixat_la DATETIME NULL DEFAULT NULL AFTER stare_moderare;

-- Prima pagină cerne după stare și așază după `fixat_la`. Fără index, fiecare
-- încărcare ar fi trecut prin tot tabelul ca să afle care sunt cele câteva
-- fixate.
ALTER TABLE evenimente
  ADD INDEX idx_evenimente_fixat (fixat_la);
