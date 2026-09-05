<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — setări locale.
 *
 * ACEST FIȘIER E DOAR UN MODEL. Copiază-l cu numele config.php:
 *
 *     copy inc\config.example.php inc\config.php      (Windows / XAMPP)
 *     cp   inc/config.example.php inc/config.php      (Linux, macOS)
 *
 * config.php e trecut în .gitignore, ca datele reale de acces să nu ajungă
 * niciodată pe GitHub.
 *
 * ATENȚIE LA URCAREA PE FTP: tocmai pentru că e în .gitignore, config.php NU
 * se descarcă odată cu restul codului. Trebuie făcut de mână pe server, cu
 * datele de acolo. Dacă lipsește, site-ul se oprește cu un mesaj care spune
 * exact asta.
 */

return [
    // --------------------------------------------------------------- orașe
    /**
     * Orașele în care se pot pune evenimente.
     *
     * Un oraș nou înseamnă un rând în plus aici, atât: lista se vede în
     * formular (`adauga_eveniment.php`) și e chiar lista pe care o acceptă
     * verificarea de pe server (`verificaEveniment`). Nu există tabel în bază
     * pentru ea — ar fi fost un tabel cu un rând și o pagină de administrare
     * pentru ceva ce se schimbă o dată pe an.
     *
     * Numele se scrie exact cum vrei să apară pe site: el ajunge ca atare în
     * coloana `evenimente.oras`. Un oraș scos de aici nu șterge evenimentele
     * de acolo — ele rămân cu orașul scris în bază, dar nu se mai pot alege
     * și, la o editare, organizatorul va fi nevoit să aleagă altul.
     */
    'orase' => ['Roman'],

    // ---------------------------------------------------------------- baza
    'db' => [
        /**
         * Aproape întotdeauna 'localhost', și în XAMPP, și pe găzduire.
         *
         * NU se pune aici numele domeniului. Baza de date stă pe aceeași
         * mașină cu site-ul, deci se ajunge la ea „din interior". Cu
         * domeniul, PHP ar ieși în internet și s-ar întoarce la server din
         * afară — lucru pe care aproape toate găzduirile îl blochează,
         * tocmai din motive de siguranță.
         *
         * Dacă găzduirea îți dă altceva (unele au un server separat, de
         * forma 'mysql.numefirma.ro'), pui exact ce scrie în panoul lor.
         */
        'host'  => 'localhost',

        // 3306 e portul obișnuit al MySQL. Se schimbă doar dacă găzduirea
        // spune explicit altul — și atunci scrie în panoul de control.
        'port'  => 3306,

        /**
         * Numele bazei, al utilizatorului și parola.
         *
         * În XAMPP: baza o creezi tu, utilizatorul e 'root' și parola goală.
         * Pe găzduire: toate trei se fac din panou, iar numele primesc automat
         * un prefix, de forma 'numecont_db' și 'numecont_utilizator'. Se pune
         * numele întreg, cu prefix cu tot.
         */
        'nume'  => 'pulsulorasului',
        'user'  => 'root',
        'parola'=> '',
    ],

    // --------------------------------------------------------------- site
    /**
     * Adresa de bază a site-ului.
     *
     * De ea atârnă linkurile din e-mailuri, adresa care pleacă spre Facebook
     * și WhatsApp la distribuire, și `og:image` — poza din cartonașul care se
     * vede când cineva pune linkul într-o conversație. WhatsApp cere poza aia
     * de pe alt server, deci dacă adresa de aici e greșită, cartonașul rămâne
     * fără poză, deși pe site totul arată bine.
     *
     * În XAMPP, dacă proiectul stă în htdocs/pulsulorasului:
     *     'http://localhost/pulsulorasului'
     * Pe găzduire, cu certificat SSL pornit:
     *     'https://pulsulorasului.ro'
     *
     * Fără bară la sfârșit.
     */
    'url_site' => 'http://localhost/pulsulorasului',

    // Fusul orar al aplicației. Toate momentele — înregistrare, blocare,
    // expirarea linkurilor — se calculează după el.
    'fus_orar' => 'Europe/Bucharest',

    // ------------------------------------------------------- cine ține site-ul
    /**
     * Cine răspunde, în fața legii, de site și de datele oamenilor.
     *
     * SE CITEȘTE DIN TREI PAGINI — termeni.php, confidentialitate.php și
     * cookies.php — prin operatorulSite() din inc/bootstrap.php. Scris o
     * singură dată aici, nu de trei ori în trei pagini, tocmai fiindcă e
     * genul de rând care se schimbă rar și atunci trebuie schimbat peste tot.
     *
     * CÂT TIMP 'nume' E GOL, cele trei pagini o spun pe față: scriu că datele
     * operatorului nu sunt încă trecute și trimit omul la pagina de contact.
     * Mai bine un gol recunoscut decât un nume inventat într-un document care
     * are putere juridică.
     *
     * 'tip' schimbă o singură vorbă din pagini: 'pf' pentru persoană fizică
     * („CNP-ul nu se publică, deci scrie doar numele"), 'srl' pentru firmă.
     */
    'operator' => [
        'tip'     => 'srl',            // 'srl' | 'pf'
        'nume'    => '',               // ex: 'EXEMPLU MEDIA S.R.L.'
        'cui'     => '',               // ex: 'RO12345678'
        'reg_com' => '',               // ex: 'J27/123/2020'
        'adresa'  => '',               // ex: 'Str. Ștefan cel Mare 1, Roman, Neamț'
        'email'   => '',               // gol = se folosește 'email_raspuns' de mai jos
    ],

    // ----------------------------------------------------------- dezvoltare
    /**
     * Cât timp e true:
     *   - erorile PHP se văd în pagină, nu doar în log;
     *   - linkul de confirmare e întors în răspuns, ca să poți încheia
     *     înregistrarea fără server de e-mail (util în XAMPP).
     *
     * PE SITE-UL PUBLIC TREBUIE PUS PE false. Altfel oricine poate cere o
     * înregistrare și primește înapoi linkul de confirmare, deci poate
     * activa conturi pe adrese care nu sunt ale lui.
     */
    'dezvoltare' => true,

    // ------------------------------------------------------------- în lucru
    /**
     * Cât timp e true, site-ul e închis: oricine intră vede pagina de
     * așteptare (`constructie.php`) și atât. Nu se pot citi evenimente, nu se
     * pot scrie comentarii, nu se pot face conturi — nu doar că nu se VĂD
     * paginile, ci nici API-urile din spatele lor nu răspund.
     *
     * Rămân deschise doar patru uși, cât să se poată intra și aștepta:
     * pagina de așteptare, `login.php` cu API-ul ei de intrare, formularul
     * de înscriere la vești și ieșirea din cont. Lista e scrisă întreagă în
     * inc/constructie.php.
     *
     * CINE TRECE: numai oamenii de casă (`membri.este_staff`). Ei intră pe
     * login.php ca de obicei, iar de la prima pagină încolo văd site-ul
     * întreg, ca și cum n-ar fi închis. Cine nu e staff nici măcar nu apucă
     * să se conecteze: parola bună nu-i deschide nimic, e trimis înapoi la
     * pagina de așteptare, iar sesiune nu se face deloc.
     *
     * Se pune pe true cât se lucrează la ceva mare și pe false la deschidere.
     * Nu ține loc de mentenanță programată și nu trimite niciun e-mail — e
     * doar o ușă închisă, cu un afiș pe ea.
     */
    'in_constructie' => false,

    // -------------------------------------------------------------- limite
    // Câte conturi pot fi create de la aceeași adresă IP într-o oră.
    'inregistrari_pe_ora' => 5,

    // Câte ore e valabil linkul de confirmare.
    'ore_valabilitate_token' => 48,

    // ------------------------------------------------------------ e-mail
    /**
     * De pe ce adresă pleacă mesajele.
     *
     * Trebuie să fie pe domeniul tău. Dacă pui aici o adresă de gmail.com,
     * mesajele ajung aproape sigur în „Spam": serverul care le trimite nu are
     * voie să trimită în numele Gmail, iar verificarea SPF observă asta.
     */
    'email_expeditor' => 'noreply@pulsulorasului.ro',
    'email_nume'      => 'PulsulOrasului.Ro',

    // Unde ajung răspunsurile, dacă cineva apasă „Reply".
    'email_raspuns'   => 'contact@pulsulorasului.ro',

    /**
     * Cum pleacă mesajele:
     *
     *   'auto'    — fișier în dezvoltare; pe site-ul public SMTP dacă datele de
     *               mai jos sunt trecute, altfel mail() (implicit)
     *   'smtp'    — prin serverul de poștă al găzduirii, cu parolă
     *   'mail'    — prin funcția mail() a serverului
     *   'fisier'  — nu se trimite nimic; mesajele se scriu în
     *               private/emailuri-trimise.log, iar ultimul și în
     *               private/ultimul-email.html, ca să-i poți vedea aspectul
     *
     * În XAMPP nu există server de e-mail, de aceea 'auto' scrie în fișier.
     */
    'email_metoda' => 'auto',

    /**
     * SERVERUL DE POȘTĂ AL GĂZDUIRII (SMTP).
     *
     * DE CE NU E DE AJUNS mail(). Pe cele mai multe găzduiri de tip cPanel,
     * serverul pune semnătura DKIM doar pe mesajele care intră pe ușa din față
     * — adică prin SMTP, cu parolă. Ce predă PHP direct pleacă nesemnat, iar un
     * mesaj nesemnat e cules azi de Gmail și Outlook aproape din reflex. Asta e
     * cea mai mare parte din povestea cu „mail() ajunge în spam": nu funcția e
     * de vină, ci semnătura pe care ea n-o primește.
     *
     * DAR SINGUR NU FACE MINUNI: dacă în DNS-ul domeniului nu stau SPF, DKIM și
     * DMARC cum trebuie, mesajele ajung tot în „Spam". Întâi DNS-ul, apoi asta.
     *
     * Valorile se iau din panoul găzduirii, de la „Connect Devices" al căsuței.
     * `smtp_user` TREBUIE să fie aceeași adresă ca `email_expeditor` de mai sus:
     * dacă te conectezi cu una și scrii „From" cu alta, verificările de
     * aliniere (DMARC) se supără, iar unele servere refuză de-a dreptul.
     *
     * Portul 465 merge cu 'smtps' (TLS de la prima vorbă) — așa scrie în panou.
     * Portul 587 merge cu 'tls' (conexiune curată, ridicată la TLS pe urmă).
     *
     * BIBLIOTECA NU VINE CU SITE-UL: se descarcă de la
     * https://github.com/PHPMailer/PHPMailer și se pune în „PHPMailer/" la
     * rădăcina site-ului, așa încât să existe „PHPMailer/src/PHPMailer.php".
     * Cât timp lipsește, mesajele pleacă mai departe prin mail() și se scrie un
     * rând în logul de erori — vezi deCeNuMergeSmtp() din inc/posta.php. Starea
     * se vede și în zona de administrare, jos pe panou.
     *
     * PAROLA ASTA E O PAROLĂ ADEVĂRATĂ: cine o are poate trimite mesaje în
     * numele domeniului. Fișierul de față nu urcă niciodată pe GitHub — vezi
     * .gitignore — și nu are ce căuta în altă parte.
     */
    'smtp_gazda'    => '',            // ex: mail.pulsulorasului.ro
    'smtp_port'     => 465,
    'smtp_user'     => '',            // aceeași adresă ca 'email_expeditor'
    'smtp_parola'   => '',
    'smtp_criptare' => 'smtps',       // 'smtps' pe 465, 'tls' pe 587

    /**
     * Câte mesaje duce o rulare a cronului care golește coada.
     *
     * NU E O ALEGERE DE-A NOASTRĂ: iese din plafonul găzduirii (zece mesaje pe
     * minut, șase sute pe ceas) și din cât de des îți lasă ea să pornești cronul.
     *
     * La un cron DIN MINUT ÎN MINUT — `* * * * *` — opt înseamnă opt pe minut:
     * sub plafon, cu loc rămas pentru mesajele care pleacă pe loc (confirmări de
     * cont, recuperări de parolă). Dacă găzduirea nu lasă cron mai des de cinci
     * minute, pui 40 și cronul la cinci minute: același ritm.
     *
     * ZECE AR FI FOST FIX ȘASE SUTE PE CEAS, adică plafonul lovit din plin, fără
     * nimic de rezervă. Opt lasă o pătrime liberă.
     *
     * DE CE CONTEAZĂ: cine sare peste plafon nu primește un avertisment, ci i se
     * oprește poșta pentru tot restul orei — inclusiv confirmările de cont ale
     * unor oameni care n-au nicio treabă cu ce s-a trimis.
     *
     * Vezi cron/trimite-emailuri.php și inc/coada.php.
     */
    'emailuri_pe_rulare' => 8,

    /**
     * CÂTE MESAJE PE MINUT DUCE GĂZDUIREA. Nu e o alegere de-a noastră, e
     * plafonul lor — de aceea stă aici, lângă cifra de mai sus.
     *
     * Se folosește la o singură socoteală, dar aceea contează: `plafon` minus
     * `emailuri_pe_rulare` = câte locuri rămân într-un minut pentru mesajele
     * care pleacă PE LOC (confirmarea de cont, parola temporară). Când iese
     * zero, panoul din admin („Poșta") o spune pe față: cine își face cont fix
     * în minutul în care cronul își duce teancul primește confirmarea abia la
     * rularea următoare.
     *
     * NU TAIE NIMIC: cifra asta nu îngrădește cronul, doar face socoteala
     * vizibilă. Un plafon scris greșit aici n-are cum să oprească poșta.
     */
    'plafon_pe_minut' => 10,

    // ------------------------------------------------------------ Google
    /**
     * Datele aplicației din Google Cloud Console.
     *
     * Cât timp sunt goale, butoanele „Continuă cu Google" nici nu se
     * tipăresc în pagină, iar site-ul merge normal fără ele.
     *
     * Pașii de urmat la Google sunt scriși pe larg în README, la secțiunea
     * „Intrarea cu Google". Pe scurt: faci un proiect, completezi ecranul de
     * acceptare, ceri „OAuth client ID" de tip „Web application" și treci la
     * „Authorized redirect URIs" exact adresa:
     *
     *     https://pulsulorasului.ro/google.php
     *
     * SECRETUL NU SE PUNE NICIODATĂ ÎN JAVASCRIPT sau în vreo pagină. Stă
     * doar aici, iar fișierul ăsta nu se vede din web.
     */
    'google_client_id'     => '',
    'google_client_secret' => '',

    /**
     * Adresele Google. NU LE SCHIMBA.
     *
     * Sunt aici doar ca fluxul să poată fi verificat automat, cu un server
     * care ține locul lui Google. Lăsate goale, se folosesc cele adevărate,
     * scrise în inc/google.php.
     */
    // 'google_url_autorizare' => 'https://accounts.google.com/o/oauth2/v2/auth',
    // 'google_url_token'      => 'https://oauth2.googleapis.com/token',

    // ---------------------------------------------------------- diagnostic
    /**
     * Cheia pentru verifica.php, pagina care spune ce nu merge pe server.
     *
     * Lasă-o goală cât timp n-ai nevoie de ea: fără cheie, pagina refuză să
     * spună ceva. Când vrei să o folosești, pui aici un șir lung și
     * întâmplător (nu un cuvânt), deschizi
     *
     *     https://site-ul-tau.ro/verifica.php?cheie=CHEIA_DE_AICI
     *
     * și ȘTERGI fișierul de pe server după ce ai terminat.
     */
    'cheie_diagnostic' => '',

    // -------------------------------------------------------- dezabonarea
    /**
     * Cheia cu care se semnează linkurile de dezabonare de la newsletterul
     * zilnic.
     *
     * POATE RĂMÂNE GOALĂ. Când e goală, se face una din datele care există
     * deja aici (vezi cheieSantier din inc/constructie.php), deci
     * newsletterul merge din prima, fără niciun pas de pregătire. Alegerea a
     * fost între asta și „încă o setare pe care cineva o uită, iar mesajul
     * pleacă fără ieșire" — iar un newsletter fără ieșire ajunge la „Spam", și
     * de acolo strică livrarea pentru toate celelalte mesaje ale site-ului.
     *
     * Pune aici un șir lung și întâmplător dacă vrei o cheie a ta. Schimbând-o,
     * linkurile din mesajele deja trimise nu mai merg — dar bifa din setări
     * rămâne oricând la îndemână, deci nimeni nu rămâne prins.
     */
    'cheie_dezabonare' => '',
];
