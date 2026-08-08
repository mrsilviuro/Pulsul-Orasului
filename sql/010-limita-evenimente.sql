-- =========================================================================
--  PulsulOrasului.Ro — câte evenimente active poate avea un membru
--
--  Rulează după sql/009-evenimente.sql.
-- =========================================================================

-- -------------------------------------------------------------------------
--  limita_evenimente_active
--
--  NULL = limita obișnuită, unu. Un număr = atâtea, pentru cine are nevoie
--  (o asociație care ține mai multe lucruri deodată, de pildă).
--
--  De ce NULL și nu „1" scris peste tot: dacă mâine limita obișnuită devine
--  doi, se schimbă o singură constantă în PHP. Cu 1 scris în fiecare rând, ar
--  trebui deosebit „unu, așa e regula" de „unu, așa am hotărât pentru omul
--  ăsta" — iar din bază nu se mai poate spune care e care.
--
--  Nu există încă nicio interfață prin care să fie schimbată; deocamdată se
--  pune de mână, din phpMyAdmin.
-- -------------------------------------------------------------------------
ALTER TABLE membri
  ADD COLUMN limita_evenimente_active TINYINT UNSIGNED NULL DEFAULT NULL
  AFTER newsletter;
