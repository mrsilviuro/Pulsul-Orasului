# PulsulOrasului.Ro — context pentru Claude Code

Blog/platformă de evenimente locale. PHP + MySQL pe server, fără framework, fără build step.
Site live: https://pulsulorasului.ro

## Cum se lucrează cu git

- **Se comite direct pe branch-ul implicit al repo-ului.** Fără branch-uri noi,
  fără Pull Request-uri pentru fiecare task. Dacă din motive tehnice chiar e
  nevoie de un branch temporar, se spune explicit înainte și se face merge
  imediat ce modificarea e confirmată — nu se lasă branch-uri deschise.
- **`inc/config.php` NU trebuie să ajungă niciodată urmărit de git.** S-a
  întâmplat o dată, la o încărcare prin interfața GitHub care a redenumit
  `config.example.php` în `config.php`: din acel moment `.gitignore` n-a mai
  avut niciun efect, fiindcă el ține deoparte doar fișierele încă neurmărite.
  Datele reale de acces au ajuns pe un repo public. Înainte de orice commit
  care atinge `inc/`, verifică:
  ```
  git ls-files --error-unmatch inc/config.php && echo "PERICOL: e urmărit"
  ```
- Când urci fișiere prin interfața web a GitHub, ai grijă ce nume le dai:
  o redenumire acolo intră direct în repo, fără să treacă pe lângă `.gitignore`.

## Reguli de editare — CRITICE

- **NICIODATĂ nu rescrie un fișier întreg** pentru o modificare punctuală (ex: schimbare
  poziție imagine, schimbare link, un text). Folosește editări țintite pe liniile
  relevante (find & replace / diff), nu regenerare completă a fișierului.
- **Înainte de orice modificare, citește fișierul de pe disc din nou** — nu te baza pe
  versiunea din context/memoria conversației. Fișierul poate fi fost modificat manual
  (local sau direct pe GitHub) între sesiuni, iar rescrierea din memorie ar șterge acele
  modificări fără ca cineva să-și dea seama.
- **Fă `git pull` la începutul sesiunii / înainte de orice task nou**, ca să pornești
  de la starea reală a repo-ului, nu de la ce a fost ultima dată citit.
- **Arată `git diff` înainte de commit/push**, ca schimbările neintenționate (fișiere
  rescrise complet, linii șterse) să fie vizibile și verificabile înainte să ajungă pe GitHub.
- Dacă un task cere modificare într-un fișier mare (ex: `style.css`, `main.js`), citește
  doar secțiunea relevantă dacă e posibil, și editează strict acolo.

## Reguli de bază — citește astea înainte să atingi cod

1. **Un singur CSS, un singur JS.** Tot site-ul folosește `assets/css/style.css`
   și `assets/js/main.js`. Nu crea stiluri per-pagină. Valorile comune (culori,
   `--wrap`, `--header-h`, `--field-h`, `--radius`) stau în `:root` / `[data-theme="dark"]`
   la începutul `style.css` — modifică acolo, nu în componente.

2. **Golește cache-ul la fiecare modificare de style.css sau main.js**, crescând
   numărul de versiune în `inc/antet.php` și `inc/subsol.php`:
   ```
   sed -i 's/style\.css?v=[0-9]*/style.css?v=NOUA_VERSIUNE/' inc/antet.php
   sed -i 's/main\.js?v=[0-9]*/main.js?v=NOUA_VERSIUNE/'     inc/subsol.php
   ```

3. **Pagină nouă = șablon fix:**
   ```php
   <?php
   $titlu  = 'Titlul paginii';
   $pagina = 'contact';   // elementul de meniu activ
   require __DIR__ . '/inc/antet.php';
   ?>
   <main id="main"> … </main>
   <?php require __DIR__ . '/inc/subsol.php'; ?>
   ```
   `inc/antet.php` = head, meniu, antete de siguranță. `inc/subsol.php` = footer, scripturi.
   Pentru cartonașul de pe WhatsApp/Facebook se pot pune, tot înainte de
   `require`: `$ogTitlu`, `$ogDescriere`, `$ogImagine` (ADRESĂ ÎNTREAGĂ, prin
   `urlIntreg()`), `$ogUrl`, `$ogTip`. Fără ele, se folosesc titlul și
   descrierea paginii.

4. **Verificările din browser sunt doar confort. Validarea reală e mereu server-side**,
   în `inc/validare.php`. Niciodată nu te bazezi doar pe `required` din HTML sau pe JS.
   O REGULĂ CARE ȚINE DE CEAS SE UITĂ LA CLIPĂ, NU LA ZI: un eveniment nu poate
   începe mai devreme de două ceasuri de-acum (`ORE_MINIM_INAINTE`), iar
   verificarea lipește data de oră — cu data singură, la ora 15:00 se putea
   publica „azi, de la 14:00". La editare se cere doar dacă se schimbă chiar
   clipa de început (al cincilea argument al lui `verificaEveniment()`).

5. **Un singur ceas: PHP (`time()`), niciodată `NOW()` din MySQL.** Fusul orar e
   `Europe/Bucharest`, setat în `inc/config.php`. Diferențele de fus PHP↔MySQL au
   cauzat deja bug-uri reale (10 min deveneau 70).

6. **Toate momentele de timp, token-urile, parolele temporare — hash-uite în DB**,
   niciodată în clar. Verifică `inc/email.php` / `inc/validare.php` pentru pattern-uri
   existente înainte de a inventa altele noi.

7. **Contul șters se anonimizează, nu se șterge cu DELETE.** Rândul din `membri`
   rămâne pentru totdeauna: de el atârnă evenimentele organizate și participările.
   Se golește omul din el (`inc/stergere.php`), nu rândul. Ștergerea are răgaz de
   30 de zile, iar simpla intrare în cont o anulează — vezi `autentifica()`.

8. **Poze de profil: niciodată nu se salvează fișierul primit așa cum a venit.**
   Se redesenează pixel cu pixel (`inc/imagini.php`), EXIF dispare, nume random hex.
   Orice funcționalitate nouă de upload trebuie să respecte acest pattern.
   Verificările comune stau în `deschidePozaPrimita()` — o folosesc și poza de
   profil, și coperta de eveniment. Nu face a treia cale.

9. **În bază intră text curat, neescapat. Escaparea e la randare, cu `h()`.**
   Escapat la salvare ar da `&amp;amp;` la a doua trecere și un text pe care nu-l
   mai poți căuta sau exporta.

10. **Caracterele se numără cu `mb_strlen()`, niciodată cu `strlen()`.** În UTF-8
    „ă" ocupă doi octeți, deci o limită pe octeți ar avantaja pe cine scrie fără
    diacritice. În JS la fel: `[...text].length`, nu `.length`.

11. **O pagină numai pentru cine e conectat cheamă `cereIntrare('/calea.php')`**
    (profil, setări, poza, formularul de eveniment — dar NU `event.php`: un
    anunț publicat se vede de oricine, restricționată e doar interacțiunea),
    nu-și scrie singură antetul de redirecționare. Calea de întoarcere trece
    mereu prin `caleInterna()` — o verificare scrisă de mână lasă să treacă
    „/\alt-site.ro", pe care browserul îl îndreaptă în „//alt-site.ro".

## Structură fișiere

```
index.php, event.php, contact.php, despre.php, login.php,
profil.php, poza.php, setari.php, adauga_eveniment.php, previzualizare.php,
parola-uitata.php, parola-noua.php, google.php, finalizare.php, confirma.php,
stergere.php, iesire.php, verifica.php, constructie.php,
findme.php, admin.php, admin-evenimente.php, admin-comentarii.php,
admin-contact.php, admin-useri.php, admin-dorinte.php, coduri.php

  findme.php  → capătul unui abțibild „FindMe": aici ajunge cine scanează
                codul QR. SINGURA pagină de pe site care schimbă starea
                printr-un GET, fără token CSRF — un scaner de coduri nu
                poate trimite un POST, iar tot ce se poate face cu o cerere
                pusă la cale de altcineva e să CÂȘTIGI un abțibild
  admin*.php  → ZONA DE ADMINISTRARE, toată numai pentru staff. O SINGURĂ
                intrare în meniu, „Admin", fiindcă șase ar fi înecat „Acasă /
                Despre / Contact". Paza NU se scrie în fiecare pagină, se
                cheamă: cerePazaDeStaff() din inc/admin.php, prima linie, mereu.
                Lista secțiunilor stă tot acolo (sectiuniAdmin) — o secțiune
                nouă e un rând în tabloul acela și apare singură și pe panou, și
                în rândul de legături. Faptele trec toate prin api/admin.php,
                cu `fapta`, ca paza să fie scrisă o dată. CE DOARE PLEACĂ PE
                E-MAIL: comentariul șters, poza ștearsă, contul suspendat și
                hotărârea unei dorințe. Toate patru primesc un MOTIV care poate
                lipsi — `data-motiv` pe buton îl cere din JS, iar
                paragrafeleMotivului() din inc/email.php scrie, când e gol, că
                nu s-a dat niciunul. Un mesaj cu un loc gol în el ar fi fost mai
                rău decât niciun mesaj. La lista de stări, `data-motiv-pentru`
                îngustează întrebarea la singura valoare care trimite ceva
                („suspendat"): o întrebare pusă degeaba e una pe care omul
                învață s-o închidă fără să citească
  coduri.php  → pagina omului de casă: face coduri noi și le arată starea.
                Prima pagină de administrare de pe site; azi e „Abțibilduri" în
                zona de admin. Se aduc cel mult CODURI_QR_PASTRATE (50) și se
                văd CODURI_QR_DEODATA (10), restul sub „Vezi mai mult". „×"-ul
                șterge un cod — dar NU unul găsit (poateFiStersCodul): de el
                atârnă cifra de pe profilul câștigătorului

inc/
  admin.php        → partea comună a zonei de administrare: PAZA
                      (cerePazaDeStaff — se cheamă prima, în fiecare pagină),
                      lista secțiunilor (sectiuniAdmin), cifrele care se aprind
                      pe cartonașe (cifreleAdmin — CÂTE AȘTEAPTĂ ceva, nu câte
                      sunt) și interogările fiecărei liste. ADMIN_RANDURI (200)
                      taie fiecare tabel: e o listă de lucru, iar ce n-a fost
                      făcut în două sute de rânduri nu se face nici în al
                      treilea sutar. DOUĂ EXCEPȚII, amândouă anume:
                      cautaMembri() taie la ADMIN_USERI (50) și așază DUPĂ
                      ULTIMA LOGARE, nu după înscriere — un cont vechi, dar
                      folosit ieri, e mai interesant decât unul nou și lăsat
                      baltă; iar toateDorintele() NU taie deloc, fiindcă
                      rândurile din `dorinte` nu se șterg niciodată și tabelul
                      ăsta e singurul loc unde se vede tot ce s-a scris vreodată
  antet.php        → head + meniu + antete siguranță (folosit de toate paginile)
  subsol.php        → footer + scripturi
  bootstrap.php     → config, conexiune DB, sesiune, CSRF
  validare.php      → toate verificările server-side (fără atingere DB)
  imagini.php       → procesare/validare poze de profil ȘI coperți de eveniment.
                      TOT AICI copiazaCoperta(): singurul loc de pe site unde
                      un fișier de imagine ajunge pe disc fără să treacă prin
                      GD — și nu e o portiță, fiindcă fișierul de plecare e
                      unul scris chiar de noi, pixel cu pixel, la prima
                      încărcare. O cere „Remake"-ul
  evenimente.php    → categorii (categoriiEvenimente($cuAleStaffului) = LISTA DIN
                      CARE SE ALEGE LA PUBLICARE — implicit FĂRĂ cele cu
                      `doar_staff = 1`, ca „FindMe": așa o funcție nouă care
                      uită să ceară lista întreagă arată prea puțin, nu prea
                      mult; de idCategoriiValide() atârnă și cine poate PUBLICA
                      acolo, nu doar ce se vede în formular. `doar_staff` spune
                      ATÂT: formularul de publicare e SINGURUL loc din care
                      lipsește categoria. Cipurile de pe prima pagină
                      (categoriiCuEvenimente) o arată ca pe oricare alta, iar
                      categoriaCeruta() cere anume lista întreagă, altfel
                      `?categorie=findme` ar fi însemnat „toate"), regula
                      „un eveniment activ", lista de pe
                      profil, LISTA DE PE PRIMA PAGINĂ (evenimenteDePePrima —
                      singura care se aduce din bază în teancuri, nu toată
                      deodată), pagina unui eveniment, salvarea ȘI cartonașul
                      unui eveniment (randeazaCartonasEveniment) — un singur
                      loc pentru cum arată, oriunde ar fi pus; al patrulea
                      parametru al lui e starea
                      ('' | 'incheiat' | 'anulat' | 'live'), iar „Live" se
                      citește la fiecare afișare, nu se ține în bază. TOT AICI cele două întrebări de care atârnă ce se
                      poate face pe pagina unui eveniment: evenimentIncheiat()
                      și evenimentAInceput() — iar „încheiat" înseamnă
                      ÎNTOTDEAUNA și „a început", oricât ar arăta ceasul. De
                      al doilea atârnă DOUĂ întrebări care NU se confundă:
                      poateFiEditat() se stinge la MINUTUL ZERO al orei de
                      început (butonul „Editează" dispare, adauga_eveniment.php
                      redirecționează, api/eveniment.php dă 409 — după start,
                      oamenii sunt deja pe drum), iar poateFiAnulat() ține mai
                      departe: anularea se mai poate
                      O ORĂ după ora de început
                      (MINUTE_ANULARE_DUPA_INCEPUT) — în răstimpul acela se
                      vede, de fapt, dacă ieșirea are loc. După el DISPARE de
                      pe amândouă paginile cu buton (adauga_eveniment.php și
                      event.php, sub caseta de interes), iar
                      api/anuleaza-eveniment.php răspunde 409; ce rămâne e
                      „Încheie evenimentul". Zona cu butonul se desenează
                      dintr-un singur loc: randeazaZonaAnulare() din
                      inc/afisare-eveniment.php. LA CELĂLALT CAPĂT AL VIEȚII
                      UNUI ANUNȚ: poateFiRefacut() și evenimentDeRefacut() —
                      butonul „Remake" de pe pagina evenimentului, care apare
                      DOAR după ce s-a încheiat sau s-a anulat, doar
                      organizatorului. Duce la adauga_eveniment.php?remake=…
                      (alt parametru decât `slug=`, dinadins) și aduce tot ce
                      scrisese omul o dată, ca un anunț NOU: „Când o să aibă
                      loc?" rămâne gol, cel vechi nu se atinge. Coperta se
                      COPIAZĂ pe disc (copiazaCoperta din inc/imagini.php), nu
                      se împarte — două rânduri care ar arăta spre același
                      fișier ar fi rămas amândouă fără poză la prima ștergere.
                      TOT AICI:
                      randeazaCartonasEveniment() primește starea
                      ('' | 'incheiat' | 'anulat' | 'live') și
                      cifreleCartonasului() scrie în colțul de jos câți vin
                      („7" sau „7 / 12", dacă e limită) și câte comentarii sunt
                      — cifrele vin din CIFRE_CARTONAS, o bucată de SQL lipită
                      în toate listele care desenează cartonașe. LA O VÂNĂTOARE
                      „FINDME" participanții NU se scriu deloc (după
                      `categorie_joc_qr`): acolo nu se înscrie nimeni, caseta de
                      interes nici nu există pe pagină, iar un „0" lângă un
                      omuleț ar fi spus „nu se duce nimeni" despre singurul fel
                      de eveniment la care nimeni n-are unde să se ducă.
                      Subcererea se aduce oricum — ce se ARATĂ e treaba funcției
                      de desenat, nu a bucății de SQL. TOT AICI titluCuNumar() — al doilea anunț cu
                      același nume AL ACELUIAȘI OM primește „ #2", al treilea
                      „ #3"; se cheamă din salveazaEveniment(), niciodată la
                      editare, iar numărul se ia din CEL MAI MARE de până acum,
                      nu din câte rânduri sunt. UN ANUNȚ
                      ANULAT nu se mai ascunde: se vede de oricine, pe prima
                      pagină și în istoricul de pe profil, stins ca unul
                      încheiat dar cu „Anulat" în colț, iar pe pagina lui stă
                      motivul scris de organizator. TOT
                      AICI: categoriiCuEvenimente() (doar cele cu cel puțin un
                      eveniment public — ACELEAȘI TREI STĂRI ca în
                      evenimenteDePePrima, „anulat" inclus: altfel o categorie
                      cu un singur eveniment, anulat, dispărea din filtre, iar
                      evenimentul rămânea în listă sub o categorie pe care n-o
                      mai putea alege nimeni; filtrele de pe prima pagină; formularul
                      de publicare folosește mai departe categoriiEvenimente(),
                      cu toate, ca o categorie goală să se poată umple) și
                      evenimenteSugerate() („Ar putea să te intereseze" de pe
                      pagina unui eveniment: două la întâmplare, doar ce
                      urmează, fără cel curent, fără oraș). ȘI cele două
                      întrebări de la publicare: starePentruPublicare() (staff
                      → „aprobat" de-a dreptul, restul → „in_asteptare"; ȘI la
                      editare, altfel o virgulă îndreptată de staff i-ar scoate
                      anunțul de pe site) și ascundePeProfil() (bifa „nu-l
                      arăta pe profilul meu", ascultată NUMAI de la staff,
                      oricât ar scrie în cerere — vezi sql/022). TOT AICI:
                      organizatorulVineSingur() — de obicei cine pune ceva la
                      cale vine la el, DAR nu la un anunț ținut deoparte de
                      profil: acolo omul de casă n-a scris o ieșire de-a lui, ci
                      una a orașului, deci nu se trece pe lista de participanți.
                      Întrebarea se pune în două locuri: salveazaEveniment() și
                      api/modereaza-eveniment.php (la aprobare)
  interese.php      → „Mergi la acest eveniment?" — cine e interesat, cine
                      vine, numărătoarea, locurile, rândul cu chipuri, listele
                      din taburi (aceeași funcție pentru amândouă), scoaterea
                      cuiva de pe cea de participanți. TOT AICI:
                      poateVedeaTelefoanele() — NUMERELE DE TELEFON de pe lista
                      de participanți le văd DOAR organizatorul și staff-ul,
                      nici măcar omul în dreptul numărului lui. Pentru ceilalți
                      coloana nici nu se cere din bază (al treilea argument al
                      lui oameniiCuStarea), ca să nu fie la un pas de a ajunge
                      în pagină. Numai pe lista de PARTICIPANȚI: interesatului
                      nu i s-a cerut niciodată numărul. Regula o cer trei
                      locuri — event.php, api/interes.php și
                      api/exclude-participant.php ȘI
                      oameniiDeInstiintatLaAnulare() — cine primește vestea
                      când se anulează un eveniment (și cei care confirmaseră,
                      și cei doar interesați; fără organizator, care tocmai a
                      apăsat butonul)
  coduri-qr.php     → „FINDME": abțibildele cu coduri QR ascunse prin oraș.
                      Codul are CINCI semne, din alfabetul fără O/0 și I/L/1
                      (curataCodQr din inc/validare.php). ORDINEA JOCULUI E TOT
                      ROSTUL TABELULUI: întâi se face codul (coduri.php, doar
                      staff), se tipărește și se lipește, ABIA PE URMĂ se
                      publică anunțul cu el în formular — de aceea
                      `coduri_qr.eveniment_id` are voie să fie NULL, iar cine
                      scanează atunci află că vânătoarea n-a început. „E joc?"
                      NU se întreabă niciodată după numele sau slugul
                      categoriei, ci după `categorii.joc_qr` (esteJocQr), steag
                      care călătorește cu rândul evenimentului
                      (`categorie_joc_qr`). `joc_qr` și `doar_staff` sunt
                      DIFERITE: al doilea spune cine poate publica, primul ce
                      fel de eveniment iese. Câștigul se hotărăște ÎN `WHERE`,
                      nu în PHP (revendicaCodul: `gasit_de IS NULL`), și merge
                      împreună cu încheierea evenimentului, sub aceeași
                      tranzacție. Legarea la fel (legaCodulDeEveniment:
                      `eveniment_id IS NULL`) — deCeNuSePoateLega() e doar
                      pentru vorba din formular. TOT AICI caseta de pe pagina
                      evenimentului (randeazaCasetaFindMe), care ia locul lui
                      „Ce zici, te interesează?": ori numărătoarea inversă până
                      la termen, ori câștigătorul. CODUL NU SE SCRIE NICIODATĂ
                      ÎN PAGINĂ — cine deschide anunțul ar câștiga fără să se
                      ridice de pe scaun
  evaluari.php      → notele dintre participanți, după eveniment: cine poate
                      nota, media și distribuția de pe profil, „Nu s-a
                      prezentat". STELELE SINGURE sunt anonime și nici nu se
                      arată pe profil; doar părerile SCRISE ajung în listă, iar
                      acelea vin semnate. Cine e însemnat neprezentat nu se mai
                      notează de nimeni (esteNeprezentat). PĂREREA SE SCRIE PE
                      PAGINA EVENIMENTULUI, într-o casetă sub rândul omului
                      (main.js + șablonul din event.php) — nu pe profilul lui,
                      ca înainte. De aceea noteleMeleLaEveniment() aduce ȘI
                      textul, nu doar stelele: caseta redeschisă arată ce a
                      scris omul, ca să ÎNDREPTE. Golită, își retrage vorbele —
                      dar numai când vine dintr-un formular ($eScriere din
                      salveazaEvaluare); o stea apăsată nu șterge niciodată un
                      text. TOT AICI omDeInstiintatLaFeedback(): cine află pe
                      e-mail că i s-a scris ceva — DOAR pentru părerile SCRISE,
                      niciodată pentru stele, care rămân anonime (bifa
                      `email_feedback`, sql/027). Mesajul îl trimite
                      api/evaluare.php, O SINGURĂ DATĂ pentru o părere:
                      insemneazaVesteaTrimisa() pune ștampila
                      (`evaluari.instiintat_la`, sql/028) ÎN `WHERE`, iar cine
                      n-o prinde nu trimite nimic. Zece îndreptări ale
                      aceluiași text nu mai înseamnă zece e-mailuri, iar
                      ștampila nu se șterge nici la retragerea vorbelor —
                      altfel „scrie–șterge–scrie" ar fi fost robinetul.
                      TOT AICI: cifrele de
                      pe profil și tabul „Istoric" (istoricEvenimente) — pe
                      unde a fost omul, DOAR evenimente încheiate, cu
                      „Organizator" și „Absent" pe cartonașe
  multumiri.php     → e-mailul de mulțumire de după eveniment: cine îl
                      primește, când, și semnul că a plecat o singură dată
                      (evenimente.multumiri_trimise_la). Îl cheamă doar
                      cron/multumeste-participantilor.php. ATENȚIE la ștampilă:
                      se pune ȘI când n-a plecat niciun mesaj (sub
                      MULTUMIRI_MINIM_OAMENI pe listă), iar de atunci
                      evenimentul nu mai apare niciodată. Cronul spune asta
                      când nu găsește nimic — multumiriDejaTrimise()
  comentarii.php    → discuția de sub eveniment: cele două niveluri,
                      aprecierile, ștergerea cu piatră de mormânt, ȘI cum
                      arată pe ecran (HTML-ul se scrie doar aici). TOT AICI
                      discutiaEDeschisa() — ÎNTREBARE DEOSEBITĂ de
                      evenimentPublicat(), dinadins: aceea spune dacă lumea se
                      poartă cu anunțul ca și cu unul de pe site (înscrieri,
                      scoateri, indexare), asta doar dacă oamenii au unde
                      vorbi. Se deschide și la ANULAT — o ieșire anulată e
                      tocmai momentul în care oamenii au ceva de zis, iar
                      organizatorul rămânea fără felul cel mai firesc de a-și
                      cere scuze. Numai discuția, nu și înscrierile:
                      api/interes.php cere mai departe evenimentPublicat().
                      TOT AICI
                      raportarea: poateRaporta() (oricine e conectat, în afară
                      de autorul comentariului — lui nu i se scrie steagul
                      deloc) și comutaRaport() (apasă = raportează, apasă iar =
                      retrage). Steagul se desenează în ANTET, după ora
                      comentariului (randeazaSteagulDeRaport), cu vorba scrisă
                      lângă el — nu în rândul de unelte de jos. NUMĂRUL rapoartelor nu ajunge niciodată în
                      pagină: omul vede doar dacă EL a raportat.
                      numaraRapoarte() există pentru lista de mai târziu a
                      staff-ului. TOT AICI: omDeInstiintatLaComentariu() — cine
                      află pe e-mail că s-a scris ceva. La un comentariu
                      PRINCIPAL, organizatorul; la un RĂSPUNS, cel căruia i s-a
                      apăsat butonul (oricât de adânc). Niciodată omul însuși,
                      cine a stins bifa `email_comentarii` din setări sau un
                      cont care nu mai e activ. Mesajul îl trimite
                      api/comentarii.php, nu fișierul ăsta
  afisare-eveniment.php → CUM ARATĂ un eveniment pe ecran (antet, copertă,
                      caseta cu detalii, descrierea). Folosit și de event.php,
                      și de previzualizare.php — schimbă aici, nu în pagini
  email.php         → șablon unic pentru toate email-urile (table-based, inline style)
  google.php        → OAuth Google (authorization code flow + PKCE)
  buton-google.php  → butonul de login Google
  stergere.php      → ștergerea contului cu răgaz + anonimizarea
  camp-parola.php   → un câmp de parolă cu ochi (folosit de toate paginile)
  dorinte.php       → TABLA CU DORINȚE de pe prima pagină: treapta de
                      dinaintea unui eveniment, pentru cine n-ar vrea să
                      organizeze, dar ar veni la ceva. Cel mult zece dorințe
                      LA ÎNTÂMPLARE (DORINTE_PE_TABLA), doar aprobate și doar
                      din ultimele șapte zile (ZILE_PE_TABLA), doar de la
                      conturi active. Cele șapte zile se numără de la
                      `publicat_la`, NU de la trimitere — cine a așteptat
                      moderarea n-are de ce să fie pedepsit. Ștampila o pune
                      codul, într-un singur loc (stampileazaCeleAprobate,
                      chemată de dorinteDePeTabla), tot cu ceasul PHP: ca să
                      publici o dorință din phpMyAdmin e de ajuns să-i schimbi
                      `stare_moderare` în „aprobat". O SINGURĂ DORINȚĂ o dată
                      de om — regula se ține la SCRIERE (puneODorinta), nu în
                      butonul de pe ecran, fiindcă două file deschise deodată
                      ar fi trimis amândouă. O dorință RESPINSĂ nu-l oprește
                      să încerce din nou; una în așteptare sau una încă pe
                      tablă, da (poatePuneODorinta → 'poate' | 'asteapta' |
                      'e_pe_tabla'). Rândurile NU se șterg niciodată, nici
                      după ce ies de pe tablă: mai târziu vrem să putem spune
                      câte dorințe și-au pus oamenii de-a lungul timpului.
                      TOT AICI cum arată: randeazaTablaDorinte(),
                      butonulDorintei() — butonul „Pune-ți o dorință", care stă
                      în FEREASTRA DE BUN VENIT, lângă „Propune o ieșire", și
                      DISPARE pentru cine are deja una în lucru — și
                      randeazaZonaDorinte(), vorba despre dorința lui („e pe
                      tablă până joi, 27 august"), care se desenează în DOUĂ
                      locuri (sub tablă și, când nu e nicio dorință, în capul
                      listei de evenimente), de aceea e o funcție, nu HTML
                      scris de două ori. Data se scrie cu dataLunga($d, false):
                      cu ziua săptămânii, fără an. puneODorinta() e chemată și de
                      api/dorinta.php (cu JS), și de index.php (fără)
  constructie.php   → LACĂTUL de pe site (`in_constructie` din config.php):
                      cine trece (doar staff), ce uși rămân deschise
                      (usileDeschiseInConstructie) și oprirea propriu-zisă
                      (opresteDacaEInConstructie, chemată la COADA lui
                      auth.php — nu în fiecare pagină, fiindcă o listă de
                      pagini care-și pun singure lacătul e o listă din care
                      lipsește mereu cea scrisă mâine). Paginile primesc o
                      redirecționare, API-urile un 503 în JSON. TOT AICI:
                      inscrieLaVesti() — lista de adrese de pe afiș, cerută
                      și de api/newsletter.php, și de constructie.php (fără JS)

api/                → endpoint-uri JSON apelate din JS (fetch); eveniment.php e
                      singurul care primește multipart, fiindcă urcă un fișier
cron/               → scripturi rulate din cron (doar CLI, .htaccess le blochează)
                      anonimizeaza-conturi.php      — o dată pe zi
                      multumeste-participantilor.php — din oră în oră
sql/                → schema.sql + migrări numerotate (002, 003, 004, 005-google,
                      006-tine-minte, 007-setari, 008-mesaje-contact,
                      009-evenimente, 010-limita-evenimente,
                      011-anulare-eveniment, 012-oras-eveniment,
                      013-interese-evenimente, 014-incheiere-eveniment,
                      015-comentarii, 016-excluderi-evenimente, 017-evaluari,
                      018-multumiri-eveniment, 019-newsletter,
                      020-rapoarte-comentarii,
                      021-instiintari-comentarii,
                      022-evenimente-staff, 023-dorinte,
                      024-categorii-doar-staff, 025-coduri-qr,
                      026-corectura-eveniment, 027-instiintari-feedback,
                      028-feedback-instiintat)
                      Zona de administrare citește și schimbă, în cea mai mare
                      parte, ce era deja acolo. SINGURA coloană nouă a ei e
                      `evenimente.corectura_ceruta_la` (026): ștampila pusă la
                      „respinge, dar cu editare necesară" și ștearsă la prima
                      editare a omului. E DATETIME, nu 0/1, tocmai ca să se vadă
                      și de cât timp așteaptă
teste/              → test-validare.php (verificările din inc/validare.php;
                      verificaDorinta e probată în test-dorinte.php, lângă
                      restul tablei)
                      test-comentarii.php, test-participanti.php,
                      test-evaluari.php, test-multumiri.php
                      (toate patru cer baza de date, nu și serverul)
                      test-admin.php (zona de administrare; cere baza, iar
                      partea de HTTP cere și serverul — se sare singură. Păzește
                      mai presus de orice că paginile și faptele sunt NUMAI
                      pentru staff)
                      test-findme.php (abțibildele cu coduri QR; cere baza,
                      iar partea de HTTP cere și serverul — se sare singură)
                      test-dorinte.php (tabla cu dorințe; cere baza, iar
                      partea de HTTP cere și serverul — se sare singură)
                      test-tine-minte.php, test-setari.php, test-contact.php,
                      test-evenimente.php, test-prima-pagina.php,
                      test-constructie.php, test-anulare.php, test-moderare.php
                      (ultimele opt cer serverul pornit — vezi antetul lor;
                      test-prima-pagina, test-constructie și test-anulare merg
                      și fără, sar doar partea care cere serverul. ATENȚIE:
                      test-constructie
                      pornește și oprește `in_constructie` din inc/config.php,
                      și îl pune la loc cum l-a găsit)
private/            → loguri (emailuri-trimise.log), protejat prin .htaccess
assets/css/style.css, assets/js/main.js, assets/img/
  assets/img/hero-zi.svg, hero-noapte.svg
                    → fundalul primei ferestre de pe index.php, unul pentru
                      fiecare temă. Panoul e cât toată fereastra browserului,
                      lat cât ea (singurul loc care nu se oprește la --wrap),
                      și e NUMAI CSS — vezi „5. PRIMA FEREASTRĂ" din
                      style.css. Fundalul e un <div> gol cu background-image,
                      nu un <img>: se schimbă după [data-theme], iar tema se
                      pune de mână, nu din sistem. Desenele sunt panoramice
                      (3200×1400) fiindcă imaginea se plimbă: elementul e lat
                      132% și merge până la -24.242% din el, adică fix 32% din
                      fereastră — cele două cifre se schimbă împreună sau
                      deloc. Săgeata de jos e un <a href="#main">, nu un buton
                      de JS: unde se oprește pagina scrie în
                      `#main { scroll-margin-top }`
```

## Config

- `inc/config.example.php` → copiezi la `inc/config.php` (gitignored, nu urcă pe GitHub)
- `orase => ['Roman']` — orașele în care se pot pune evenimente. Un oraș nou =
  un rând aici, atât: lista se citește prin `oraseDisponibile()` și de formular,
  și de `verificaEveniment()`. Nu există tabel în bază pentru ea.
- `dezvoltare => true/false` — în dev, linkurile de confirmare apar direct în pagină +
  log în `private/`. Pe producție OBLIGATORIU `false`.
- `in_constructie => true/false` — site închis, cu pagina de așteptare peste
  tot. Trec doar oamenii de casă (`membri.este_staff`), iar cine nu e staff nu
  apucă să se conecteze deloc. Vezi `inc/constructie.php`
- `fus_orar => 'Europe/Bucharest'`
- `url_site` — fără `/` la final
- `email_expeditor` — trebuie să fie pe domeniul propriu (SPF/DKIM), altfel spam
- `google_client_id` / `google_client_secret` — goale = butoanele Google nu se afișează deloc

## Autentificare — ce trebuie respectat mereu

- Mesaj identic pentru "parolă greșită" și "cont inexistent" (nu se scurge care conturi există)
- Blocare pe (email + IP), 3 greșeli → 10 min lockout, plus limită 15/oră per IP
- `session_regenerate_id()` la fiecare login/schimbare parolă
- CSRF token la orice acțiune care schimbă starea (inclusiv logout)
- Amprentă browser legată de sesiune (fără IP — se schimbă legit pe mobil)
- Stare cont (suspendat etc.) se citește din DB la fiecare cerere, nu din sesiune
- Limitele pe IP se numără în tabelul propriu al funcției (conturi noi în `membri`,
  mesaje în `mesaje_contact`), nu într-un sistem separat. `incercari_autentificare`
  rămâne doar pentru intrarea în cont: acolo numărătoarea duce la blocare, iar o
  limită de pe altă pagină n-are voie să încuie contul cuiva.
- „Ține-mă minte" = rând în `sesiuni_amintite`, nu un cookie de sesiune lung.
  Cookie-ul are forma `selector:secret`, în DB stă doar sha256 al secretului,
  se rotește la fiecare folosire, e legat de amprenta browserului, iar
  reapariția unui secret vechi stinge toate amintirile membrului.
  Intrarea cu Google îl pornește din start (n-are unde sta bifa).

## Convenții de nume/date

- Nume proprii: capitalizare per cuvânt, diacritice ș/ț cu virgulă (nu ş/ţ cu sedilă)
- Email: lowercase la salvare
- Permalink membru: 10 caractere random, alfabet fără `0/O/1/l/I` (dictabil la telefon)
- Parolă temporară (recuperare): 6 caractere, același alfabet, valabilă 60 min, o singură dată
- Telefon: o singură formă în bază, `0722334455` (verificaTelefon din inc/validare.php
  aduce +40 / 0040 la 0). Se cere la contact, e opțional în setări.

## Ce e neterminat (roadmap)

- Interdicția de reînscriere (`excluderi_evenimente.interzis`) nu se poate
  ridica din interfață — rândul se schimbă de mână, din phpMyAdmin. Nu există
  nici pagină care să-i arate omului de unde a fost scos: află doar din e-mail
- Pagina de termeni și condiții nu există. Linkurile spre ea sunt `href="#"`
  peste tot (înregistrare, subsol, confirmarea participării)
- Moderarea: fiecare eveniment intră cu `stare_moderare = 'in_asteptare'` și se
  vede organizatorului și staff-ului. Aprobarea, respingerea și „editare
  necesară" se fac de pe pagina evenimentului, din blocul dintre anunț și
  comentarii (`api/modereaza-eveniment.php`) — bloc care DISPARE odată ce
  anunțul e aprobat: după aceea nu mai e nimic de hotărât, iar un rând de
  butoane rămas acolo e doar o apăsare greșită care așteaptă. Lista celor care
  așteaptă e în `admin-evenimente.php`, iar cele cărora li s-a cerut o îndreptare
  poartă un semn (`evenimente.corectura_ceruta_la`), stins de prima editare a
  omului. ATENȚIE: respingerea cu bifa scoasă GOLEȘTE comentariile, notele,
  excluderile și înscrierile anunțului (`golesteDateleEvenimentului`) — rândul
  evenimentului rămâne
- Staff: există doar steagul `membri.este_staff` (se pune de mână, din
  phpMyAdmin) și funcția `esteStaff()` — staff înseamnă orice valoare în afară
  de 0. Ce deschide: vede ORICE eveniment, oricare i-ar fi starea
  (poateVedeaEvenimentul), poate aproba și respinge anunțuri, PUBLICĂ DIRECT
  (anunțul lui intră „aprobat", iar butonul din formular scrie „Publică
  evenimentul") și poate bifa „nu-l arăta pe profilul meu", poate scoate oameni
  de pe liste, trece de lacătul de șantier și intră în zona de administrare
  (`admin*.php`). Steagul însuși NU se dă din interfață, dinadins.
  Limita de evenimente active NU i se aplică: poatePublicaEveniment() îl lasă
  să treacă peste ea, fiindcă e făcută împotriva celui care ar umple prima
  pagină, iar el publică tocmai zece anunțuri ale orașului
- Curățenia evenimentelor anulate: rândul cu `stare_moderare = 'anulat'` rămâne
  în bază pentru totdeauna, iar pagina lui se vede de oricine (cu banda și
  motivul). Ștergerea lui (cu tot cu coperta de pe disc, înscrieri și
  comentarii) e o acțiune viitoare de staff — vezi `TODO`-ul din
  `anuleazaEveniment()`
- Adresele frumoase: acum sunt `profil.php?m=<permalink>` și `event.php?slug=…`
- Înștiințările pe e-mail care pleacă azi: mulțumirea de după eveniment
  (cron/multumeste-participantilor.php), vestea că cineva a fost scos de pe
  listă, vestea că un eveniment s-a anulat (api/anuleaza-eveniment.php),
  hotărârea moderării (api/modereaza-eveniment.php), comentariile noi
  (api/comentarii.php → instiinteazaDeComentariu, cu bifa `email_comentarii`
  din setări) și PĂRERILE SCRISE pe profil (api/evaluare.php →
  omDeInstiintatLaFeedback, cu bifa `email_feedback`; stelele singure NU
  vestesc nimic, fiindcă sunt anonime). PLUS CELE PATRU ALE ZONEI DE ADMINISTRARE, toate cu motiv care
  poate lipsi (api/admin.php): comentariul șters, poza ștearsă, contul
  suspendat și hotărârea unei dorințe. NU pleacă nimic când cineva se înscrie
  la un eveniment, când se apreciază un comentariu sau când se raportează ceva
- Tabla cu dorințe se moderează din `admin-dorinte.php`. Fiecare dorință intră
  cu `stare_moderare = 'in_asteptare'` și nu se vede nicăieri până nu e
  aprobată; omul află pe e-mail în amândouă cazurile, iar la respingere se poate
  scrie un motiv (gol = „nu s-a dat niciunul"). Se poate și din phpMyAdmin, mai
  departe — e de ajuns `stare_moderare`:
  ```sql
  UPDATE dorinte SET stare_moderare = 'aprobat' WHERE id = 7;   -- sau 'respins'
  ```
  Atât: `publicat_la` NU se pune de mână, nici de aici, nici de acolo. Îl scrie
  codul, cu ceasul PHP, la prima încărcare a primei pagini
  (stampileazaCeleAprobate) — tocmai ca să nu intre `NOW()`-ul lui MySQL, din
  alt fus, în ceva ce se numără în zile
- Autorul nu poate șterge o dorință publicată (i se spune asta în formular,
  înainte să apese). Staff-ul poate, din `admin-dorinte.php` — și e SINGURA
  ștergere adevărată de pe site, tocmai fiindcă rândurile astea nu se șterg
  niciodată altfel: butonul e pentru ce n-are ce căuta în numărătoare, o
  înjurătură sau un test
- Dorințele nu se numără nicăieri. Rândurile rămân în `dorinte` pentru
  totdeauna, și după ce ies de pe tablă, tocmai ca mai târziu să se poată
  arăta câte dorințe și-au pus oamenii de-a lungul timpului — dar pagina care
  s-o spună nu există încă
- Comentariile raportate se adună în `admin-comentarii.php`, cel mai raportat
  în cap. Două butoane, nu unul: „Șterge" (cu motiv, care îi pleacă autorului pe
  e-mail) și „E în regulă", care ȘTERGE RAPOARTELE, nu comentariul — se
  raportează și din greșeală, și din răutate, iar fără al doilea buton singurul
  fel de a închide un raport nedrept ar fi fost să ștergi ce n-avea nimic. Cine
  a raportat NU află nimic, în niciunul din cazuri
- Notele nu se pot retrage și nici raporta. Cine a primit o stea pe nedrept nu
  are cui să spună — nu există pagină de moderare a evaluărilor. Nici
  „Nu s-a prezentat" nu se ia înapoi: e definitivă, dinadins, ca organizatorul
  să n-o poată schimba mai târziu — dar dacă a pus-o din greșeală, rândul se
  șterge de mână din phpMyAdmin
- `evenimente.varsta_minima` nu se verifică la înscriere: coloana există, dar
  nimeni nu se uită la ea (spre deosebire de `gen_participanti`, care se ține)
- Paginile de categorie (slugurile sunt în tabelul `categorii`)
- Imaginile implicite de categorie (`categorii.imagine_default`) — coloana
  există, fișierele nu; se urcă de mână, nu prin `inc/imagini.php`

## Workflow recomandat cu Claude Code

- La task-uri mari/neînrudite, pornește sesiune nouă (`/clear`) în loc să lași
  contextul să crească spre auto-compact.
- Referă fișiere explicit (ex: "citește inc/validare.php") în loc de "cum am discutat
  mai devreme" — mai fiabil decât memoria conversației.
- Rulează `php teste/test-validare.php` după orice modificare la `inc/validare.php`.
