-- =========================================================================
--  PulsulOrasului.Ro — urmărirea unui organizator
--
--  Rulează după sql/032-dorinte-mai-multe.sql. În phpMyAdmin: alege întâi
--  baza din stânga, apoi „Import".
--
--  CE ADUCE
--
--  Un buton „Urmărește" pe profilul unui om și pe pagina evenimentelor lui.
--  Cine îl apasă primește un e-mail ori de câte ori omul acela pune un anunț
--  nou pe site — un singur cartonaș, cu evenimentul, nu o listă.
--
--  DE CE, când există deja newsletterul zilnic: acela vine cu tot ce se
--  întâmplă în oraș, iar mulți nu vor atât. Cine ține la un singur om — cel
--  care organizează în fiecare joi alergarea de seară — vrea să afle despre
--  ELE, nu despre restul. Urmărirea e newsletterul strâns la un singur om.
--
--  DOUĂ SCHIMBĂRI
--
--  1. Tabelul `urmariri`, cu perechea (cine, pe cine). Cheia unică e chiar
--     regula jocului: nu se poate urmări de două ori același om, oricâte
--     apăsări ar veni deodată de pe două file. Nu se scrie „dez-urmărirea"
--     nicăieri — ea e ștergerea rândului, iar un rând care nu există spune
--     același lucru ca unul cu un steag pe „nu", doar că fără să mai fie
--     nevoie să-l întrebe nimeni.
--
--     `ON DELETE CASCADE` pe amândouă cheile: contul șters se anonimizează,
--     rândul din `membri` rămâne — dar dacă vreodată se șterge cu adevărat
--     unul, urmăririle lui n-au ce căuta în urmă.
--
--  2. `evenimente.urmaritori_instiintati_la` — ștampila care ține „o singură
--     dată". Fără ea, un anunț respins și apoi aprobat din nou ar fi trimis
--     de două ori aceluiași om, iar o rulare de mână ca să se vadă dacă merge
--     ar fi ajuns la toată lumea. Aceeași socoteală ca la
--     `multumiri_trimise_la` și `newsletter_trimis_la`: se pune ÎNAINTE de
--     trimitere, fiindcă un e-mail plecat nu se mai ia înapoi.
-- =========================================================================

CREATE TABLE IF NOT EXISTS urmariri (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Cine urmărește.
    urmaritor_id  INT UNSIGNED NOT NULL,

    -- Pe cine.
    urmarit_id    INT UNSIGNED NOT NULL,

    creat_la      DATETIME     NOT NULL,

    PRIMARY KEY (id),

    -- Regula jocului, scrisă în bază: o singură urmărire pentru o pereche.
    UNIQUE KEY urmarire_unica (urmaritor_id, urmarit_id),

    -- După asta se numără urmăritorii cuiva și se strâng cei de înștiințat:
    -- e întrebarea pusă la fiecare deschidere de profil.
    KEY dupa_urmarit (urmarit_id),

    CONSTRAINT urmariri_urmaritor FOREIGN KEY (urmaritor_id)
        REFERENCES membri (id) ON DELETE CASCADE,
    CONSTRAINT urmariri_urmarit FOREIGN KEY (urmarit_id)
        REFERENCES membri (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ștampila „le-am dat de veste urmăritorilor", pusă o singură dată pe anunț.
ALTER TABLE evenimente
    ADD COLUMN urmaritori_instiintati_la DATETIME NULL DEFAULT NULL
        COMMENT 'Când au fost înștiințați urmăritorii organizatorului'
        AFTER actualizat_la;
