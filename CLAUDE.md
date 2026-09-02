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
   DE AICI DECURGE: oriunde se scrie un nume, rândul golit trebuie întrebat
   întâi dacă mai are un om în el — `esteContSters($stare)` din `inc/validare.php`,
   iar în locul numelui se scrie `NUME_CONT_STERS` („Utilizator șters"). Fără
   întrebarea asta, `numeAfisat('Șters', 'Utilizator')` scotea „Ș. Utilizator":
   o prescurtare care arată exact ca un nume de om adevărat, cu legătură cu tot
   spre un profil gol. Se întreabă DOAR de `stare`, niciodată de
   `cerere_stergere`: cele 30 de zile sunt dinadins un răstimp în care nu se
   schimbă nimic. O pun TOATE locurile în care se scrie un nume: antetul unui
   eveniment (`evenimentDinBaza`), comentariile (`contActiv` din
   `inc/comentarii.php`), părerile scrise (`inc/evaluari.php`), câștigătorul
   unei vânători (`inc/coduri-qr.php` — acolo scrie „Cineva", nu „Utilizator
   șters": e altă vorbă fiindcă e altă propoziție) și zona de administrare
   (`omulCuLegatura`). Listele de participanți nici n-au nevoie:
   `INTERESE_DOAR_ACTIVI` taie din bază tot ce nu e `activ`.
   ODATĂ CU NUMELE SE STINGE ȘI CHIPUL, și se stinge ÎN COD, nu se așteaptă să
   fie NULL în bază: anonimizarea chiar șterge poza, dar un rând golit de mână
   din phpMyAdmin — se mai întâmplă — ar rămâne altfel cu chipul pe el sub vorba
   „Utilizator șters". Aici s-au găsit două scăpări, în două rânduri: antetul
   evenimentului și caseta FindMe.

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
admin-contact.php, admin-useri.php, admin-evaluari.php, admin-dorinte.php,
coduri.php, sitemap.php, robots.txt, dezabonare.php,
termeni.php, confidentialitate.php, cookies.php

  dezabonare.php → ieșirea de la newsletterul zilnic, FĂRĂ CONT: cine s-a
                săturat de un mesaj n-are chef să-și amintească parola ca să
                scape de el, iar dacă nu scapă în două secunde apasă „Spam".
                Semnătura din adresă ține loc de dovadă (HMAC din id + o cheie
                a site-ului, vezi cheieDezabonare). DESCHIDEREA DOAR ÎNTREABĂ;
                stinsul se face cu un buton, prin POST — multe programe de
                e-mail deschid singure toate linkurile dintr-un mesaj, iar cu
                un GET care stinge, oamenii s-ar fi trezit scoși de pe listă
                fără să fi apăsat nimic. De aceea nu se trimite nici
                „List-Unsubscribe-Post" în mesaj

  event.php   → ADRESA LUI E `/eveniment/<slug>`, nu `event.php?slug=`.
                Rescrierea o face .htaccess-ul din rădăcină (și
                teste/router.php, în dezvoltare): pentru event.php cererea
                arată exact ca înainte. Drumul invers îl face event.php,
                cu 301 — DAR ABIA DUPĂ ce s-a găsit anunțul și s-a văzut
                că omul are voie la el: pus mai sus, ar fi trimis
                PERMANENT și un slug scris aiurea. Adresa se scrie DOAR
                prin urlEveniment() (inc/evenimente.php); la fel profilul,
                prin urlProfil()
  sitemap.php → harta pentru motoarele de căutare, cerută la `/sitemap.xml`
                (rescriere). SE SCRIE DIN BAZĂ la fiecare cerere: una făcută
                o dată și lăsată acolo ar fi rămas în urmă la primul
                eveniment nou, iar o hartă care minte e mai rea decât
                niciuna. Cu site-ul închis răspunde 503, nu o listă de
                adrese care duc toate la afișul de șantier
  robots.txt  → ce n-au voie roboții să ceară. E o RUGĂMINTE, nu un lacăt:
                ce chiar trebuie închis se închide în cod. Ține deoparte
                zona de admin, uneltele contului, API-urile, filtrele de pe
                prima pagină ȘI PROFILURILE — pe un profil scriu numele
                omului, chipul lui, notele și pe unde a fost, date pe care
                le-a dat ca să iasă prin oraș, nu ca să fie găsit după nume
                în Google. Paginile rămân publice pentru cine primește
                linkul; doar nu se caută

  findme.php  → capătul unui abțibild „FindMe": aici ajunge cine scanează
                codul QR. SINGURA pagină de pe site care schimbă starea
                printr-un GET, fără token CSRF — un scaner de coduri nu
                poate trimite un POST, iar tot ce se poate face cu o cerere
                pusă la cale de altcineva e să CÂȘTIGI un abțibild
  admin-evaluari.php → NOTELE dintre participanți: „cine împarte note, și cu
                ce mână" (tabelul mic, cu media dată și cea mai mică) și „ce
                s-a dat, cui, când și de la ce eveniment" (tabelul mare).
                Există fiindcă notele NU se retrag, NU se raportează și nu se
                moderau nicăieri: o stea pusă din supărare rămânea pentru
                totdeauna în media cuiva. SE VĂD ȘI CELE FĂRĂ TEXT — pe profil
                apar doar părerile scrise, dar în medie intră amândouă, deci
                tocmai stelele singure trebuie să se poată vedea de aici. E
                SINGURUL loc de pe site unde o stea are un nume lângă ea.
                Cifra de pe panou e „câte sunt sub trei stele", nu „câte sunt":
                la evaluări nu așteaptă nimic o hotărâre, deci se aprinde ce
                merită o privire — fără cele automate („Nu s-a prezentat" pune
                o stea, dar aia e o absență, nu o părere). Ștergerea e
                ADEVĂRATĂ (a doua de pe site, după dorințe: o notă nu se poate
                goli ca un comentariu, fiindcă e o cifră într-o medie) și NU
                trimite niciun e-mail — vezi lămurirea din api/admin.php.
                Același „×" stă și pe profil, în dreptul fiecărei păreri
                scrise, doar pentru staff: de obicei așa afli de o vorbă
                nedreaptă
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
                lipsi — `data-motiv` pe buton îl cere din JS, iar cuMotivul()
                din inc/email.php îl pune în mesaj: în CHENAR când există, iar
                când e gol, o vorbă care spune că nu s-a dat niciunul. Un mesaj
                cu un loc gol în el ar fi fost mai
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
  antet.php        → head + meniu (folosit de toate paginile). ANTETELE DE
                      SIGURANȚĂ nu se scriu aici, se cheamă:
                      antetedeSiguranta() din inc/bootstrap.php
  subsol.php        → footer + scripturi
  bootstrap.php     → config, conexiune DB, sesiune, CSRF, ANTETELE DE
                      SIGURANȚĂ. antetedeSiguranta() le pune pe toate, inclusiv
                      POLITICA DE CONȚINUT (CSP): „doar de la noi", cu fonturile
                      Google singura excepție. Se cheamă din DOUĂ locuri, fiindcă
                      atâtea desenează pagini: inc/antet.php (tot site-ul) și
                      constructie.php (afișul de șantier, singura pagină care nu
                      trece prin antet). Scrise de două ori, a doua copie ar fi
                      rămas în urmă la prima schimbare. SINGURUL script scris în
                      pagină de pe tot site-ul e cel care pune tema, în
                      inc/antet.php, iar el poartă cifra de unică folosință a
                      cererii (nonceCsp) — fără ea nu rulează. Deci un
                      `<script>` nou scris direct în HTML NU va merge dacă nu
                      primește `nonce="<?= h(nonceCsp()) ?>"`; mai bine îl pui
                      în assets/js/main.js, ca tot restul. `style-src` are
                      'unsafe-inline' dinadins: cifra nu merge pe atributele
                      `style=`, iar site-ul are trei.
                      `img-src` ARE ȘI `blob:`, ȘI NU SE SCOATE: poza de profil
                      (poza.php) și coperta de eveniment (adauga_eveniment.php)
                      îi arată omului ce a ales, prin URL.createObjectURL() →
                      `<img src="blob:…">`, ca s-o potrivească în ramă înainte
                      de a o trimite. Fără el, browserul refuză poza, `onerror`
                      se aprinde, și omul primește „Nu am putut deschide
                      fișierul" / „Fișierul nu pare o poză" la ORICE poză — se
                      rupe în browser, nimic nu ajunge pe server, deci nu se
                      vede nici în loguri. S-a întâmplat o dată. Nu e o portiță:
                      o adresă „blob:" nu se naște decât din scriptul nostru, pe
                      fișierul ales de om. Proba care păzește rândul e în
                      teste/test-constructie.php. ATENȚIE, REGULA GENERALĂ: CSP
                      rupe ÎN TĂCERE — nu dă 500, nu scrie în log, iar probele
                      din PHP (curl) nu văd nimic, fiindcă un client care nu
                      rulează JS n-are cum să fie oprit de el. Orice funcție
                      nouă care aduce ceva în pagină pe altă cale (o adresă
                      nouă, un `Worker`, un `<iframe>`) se probează ÎN BROWSER
  validare.php      → toate verificările server-side (fără atingere DB)
  imagini.php       → procesare/validare poze de profil ȘI coperți de eveniment.
                      TOT AICI copiazaCoperta(): singurul loc de pe site unde
                      un fișier de imagine ajunge pe disc fără să treacă prin
                      GD — și nu e o portiță, fiindcă fișierul de plecare e
                      unul scris chiar de noi, pixel cu pixel, la prima
                      încărcare. O cere „Remake"-ul
  evenimente.php    → PIUNEZA: esteFixat(), poateFiFixat(), fixeazaEveniment()
                      și coloana `fixat_la` (sql/029). Un anunț fixat stă PRIMUL
                      pe prima pagină și are chenar — cheia de sortare
                      `(e.fixat_la IS NULL) ASC` stă ÎNAINTEA celei care
                      desparte viitorul de trecut, altfel un anunț încheiat
                      sau anulat, dar fixat, ar fi căzut oricum jos. Numai
                      STAFF-UL o pune și o ia, de pe pagina evenimentului
                      (api/fixeaza-eveniment.php), pe ORICE anunț care se vede
                      pe site — aprobat, încheiat sau anulat. NU se stinge
                      singură niciodată. TOT AICI: categorii (categoriiEvenimente($cuAleStaffului) = LISTA DIN
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
                      loc pentru cum arată, oriunde ar fi pus. TOT ACOLO se
                      scrie ORAȘUL, între dată și locul anume: se strânge
                      dinspre larg spre îngust, iar cine intră de pe un mesaj
                      n-a cernut nimic după oraș. Se cere `e.oras` în TOATE
                      interogările care desenează cartonașe, altfel rândul iese
                      gol tăcut; al patrulea
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
                      inc/afisare-eveniment.php. A TREIA DIN FAMILIE, și merge
                      INVERS: poateFiIncheiat() nu se stinge la o oră, se
                      APRINDE la ea — publicat (deci NU anulat) + neîncheiat +
                      a început. Era scrisă de mână în event.php, cu doi termeni
                      din trei, și de aceea butonul „Încheie evenimentul" stătea
                      viu pe pagina unui anunț ANULAT: un eveniment anulat a
                      încetat deja, altfel, iar cele două stări nu se pun una
                      peste alta. api/incheie-eveniment.php cere aceleași trei
                      lucruri, dar despărțite, fiindcă el trebuie să spună CARE
                      din ele n-a mers. LA CELĂLALT CAPĂT AL VIEȚII
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
                      nu din câte rânduri sunt. O CHEAMĂ ȘI
                      api/previzualizare.php, cu aceeași condiție
                      (`$deEditat === null`): altfel omul vedea în
                      previzualizare un titlu și pe prima pagină altul. Acolo
                      doar se ARATĂ — numărul adevărat se pune tot la scriere,
                      din bază, în clipa aceea. UN ANUNȚ
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
                      Se poate însă înscrie SINGUR, ca oricare altul — și atunci
                      anunțul îi intră în „Istoric" și în „Prezent la
                      activități", doar fără însemnul „Organizator" (vezi
                      istoricEvenimente din inc/evaluari.php).
                      Întrebarea se pune în două locuri: salveazaEveniment() și
                      api/modereaza-eveniment.php (la aprobare)
  interese.php      → „Mergi la acest eveniment?" — cine e interesat, cine
                      vine, numărătoarea, locurile, rândul cu chipuri, listele
                      din taburi (aceeași funcție pentru amândouă), scoaterea
                      cuiva de pe cea de participanți. TOT AICI TOATE
                      OPRELIȘTILE LA ÎNSCRIERE, într-un singur loc
                      (motivBlocajParticipare): ușa închisă de organizator,
                      regula de gen ȘI VÂRSTA MINIMĂ. Ultima se socotește LA
                      ZIUA EVENIMENTULUI, nu la ziua de azi — cine împlinește
                      16 ani poimâine îi are la ceva de peste trei zile, și
                      asta cere organizatorul; „împlinit" înseamnă împlinit, la
                      16 ceruți cel de 16 intră și cel de 15 nu (varstaLaZiua,
                      socotită cu DateTime, ca 29 februarie să nu strice
                      socoteala). Organizatorul trece de toate trei: e omul de
                      care se leagă evenimentul.
                      TOT AICI:
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
                      la termen, ori câștigătorul. O VÂNĂTOARE SE TERMINĂ ÎN
                      DOUĂ FELURI, și amândouă SCRIU starea în bază: o găsește
                      cineva (revendicaCodul, sub tranzacție cu câștigul), ori
                      se scurge timpul — incheieVanatorileTrecute(), care stă în
                      inc/evenimente.php, lângă incheieEveniment(), fiindcă o
                      cheamă evenimenteDePePrima() (două fișiere care se cer
                      unul pe altul ar fi o buclă; steagul se citește de-a
                      dreptul, `c.joc_qr`, ca la cifreleCartonasului). Un
                      eveniment obișnuit ține o zi și se încheie SOCOTIT LA
                      CITIRE, fără să scrie nimeni nimic; o vânătoare ține până
                      la o CLIPĂ ANUME, și de aceea se scrie: „încheiat" e scris
                      în PATRU locuri (evenimentIncheiat, filtruNeincheiat,
                      istoricEvenimente, evenimenteFaraMultumiri), iar o regulă
                      nouă socotită la citire ar fi trebuit strecurată în toate
                      patru. O cheamă DOUĂ locuri: evenimenteDePePrima() (deci
                      și index.php, și api/lista-evenimente.php) și event.php,
                      țintit pe anunțul cerut, ca pagina să fie adevărată din
                      prima clipă pentru cine vine de pe un abțibild.
                      CODUL NU SE SCRIE NICIODATĂ
                      ÎN PAGINĂ — cine deschide anunțul ar câștiga fără să se
                      ridice de pe scaun. TOT AICI FRÂNA ÎMPOTRIVA GHICITULUI:
                      preaMulteIncercariQr() și insemneazaIncercareaQr(),
                      QR_INCERCARI_PE_CEAS (30) de la o adresă IP, în
                      QR_MINUTE_FEREASTRA (60). Se numără DOAR scanările care
                      n-au nimerit nimic — cine scanează un abțibild adevărat a
                      fost acolo, s-a uitat, a găsit. findme.php întreabă ÎNAINTE
                      să se uite în bază după codul cerut, altfel frâna ar fi
                      fost pusă după ce trecea mașina
  evaluari.php      → notele dintre participanți, după eveniment: cine poate
                      nota, media și distribuția de pe profil, „Nu s-a
                      prezentat". FEREASTRA E DE 48 DE ORE (ORE_PENTRU_NOTE):
                      după ea nu se mai adaugă și nu se mai SCHIMBĂ nimic —
                      nici stele, nici păreri scrise, nici „Nu s-a prezentat".
                      O părere se face cât seara aceea e proaspătă, iar notele
                      nu se pot retrage și nu se pot raporta, deci ceasul e
                      singura apărare împotriva uneia date la supărare peste o
                      lună. TERMENUL SE SOCOTEȘTE DIN CEASUL ANUNȚULUI
                      (terminulNotelor): ora de sfârșit dacă e una, altfel
                      capătul zilei — apoi 48 de ore. Nu din clipa în care s-a
                      apăsat „Încheie evenimentul": acela ar fi un termen pe
                      care participanții nu-l văd nicăieri, și mai scurt pentru
                      cei ai unui organizator harnic. Regula stă într-un singur
                      loc, motivBlocajEvaluare(), de unde o iau și stelele
                      desenate în pagină, și api/evaluare.php, și formularul de
                      părere de pe profil. Termenul se scrie PE FAȚĂ în capul
                      tabului „Au participat" (.panel__nota din event.php):
                      motivul dintr-un `title` nu se vede pe telefon niciodată.
                      CINE N-A FOST ACOLO NU VEDE NICIO STEA — nici stinsă:
                      vedea cinci înghețate în dreptul fiecărui om, un buton
                      care nu se apasă, iar ele erau nota pe care ar fi dat-o EL
                      (zero), nu a omului din dreptul lor. Hotărăște
                      `eu_participant` din contextul făcut în event.php,
                      DEOSEBIT de `pot_nota`, care se stinge și la termen: cine
                      A FOST își vede stelele stinse și după el, fiindcă în ele
                      scrie nota pe care a dat-o. STELELE SINGURE sunt anonime și nici nu se
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
                      „Organizator" și „Absent" pe cartonașe. BIFA
                      `ascuns_pe_profil` NU SCOATE NIMIC DE AICI, nici măcar de
                      pe profilul celui care a pus anunțul: istoricul spune pe
                      unde a fost omul, nu ce a organizat. Dacă omul de casă a
                      apăsat „Particip" la un anunț al orașului, a fost acolo ca
                      oricare altul, iar cartonașul intră în istoric și se
                      numără la „Prezent la activități" (laCateEvenimenteAFost).
                      CIFRA ACEEA NUMĂRĂ DOAR CE S-A ÎNCHEIAT: „prezent la" e
                      la timpul trecut, iar cine tocmai a apăsat „Particip" la
                      ceva de săptămâna viitoare n-a fost încă nicăieri. Se
                      numărau și cele care urmează, și de aceea cifra ieșea mai
                      mare decât lista de sub ea. Condiția e scrisă la fel ca în
                      istoricEvenimente, ca cele două să se vadă că sunt surori
                      — doar că din cifră lipsește și „anulat". DECI CIFRA E MAI
                      MICĂ DECÂT NUMĂRUL DE CARTONAȘE la cine a avut parte de o
                      anulare, și e voit: seara anulată rămâne în istoric,
                      fiindcă omul își ținuse timpul liber pentru ea, dar nu e o
                      prezență — n-a fost nimic. Sunt două întrebări deosebite.
                      Ce face bifa AICI e un singur lucru: stinge
                      însemnul „Organizator" (`e_organizator` iese 0 în
                      interogare), fiindcă el n-a pus nimic la cale. Restul
                      bifei rămâne unde-i e rostul: „Ieșiri organizate"
                      (evenimenteDePeProfil, cateEvenimenteOrganizate)
  urmariri.php      → URMĂRIREA UNUI ORGANIZATOR: butonul „Urmărește" de pe
                      profil și de pe pagina unui eveniment, cifra
                      urmăritorilor, și vestea pe e-mail la fiecare anunț nou
                      al omului urmărit — UN SINGUR CARTONAȘ, desenat de
                      randuriPentruNewsletter(), nu o listă. Ăsta e tot rostul
                      lui față de newsletterul zilnic: cine ține la un singur
                      om vrea să afle despre EL, nu despre tot orașul.
                      NU EXISTĂ „DEZ-URMĂRIRE": a doua apăsare șterge rândul,
                      iar comutaUrmarirea() lasă cheia unică din sql/033 să
                      hotărască — se încearcă întâi scrierea, nu se întreabă
                      întâi. De aceea nu e nici bifă nouă în setări: ieșirea e
                      chiar butonul din care s-a intrat.
                      UNDE STĂ BUTONUL, în amândouă paginile: nu pe rândul unui
                      nume, ci pe rândul lui. Pe PROFIL, SUB nume la orice
                      lățime — a stat o vreme în dreapta, rupt de `flex-wrap`
                      doar când nu mai încăpea, adică locul lui îl hotăra
                      lungimea numelui: la unul scurt rămânea sus, la unul lung
                      cobora, iar același om vedea altceva pe telefon și pe
                      calculator. Pe PAGINA UNUI EVENIMENT, în rândul de butoane
                      din antet (`.post__actiuni`), lângă „Fixează", „Editează",
                      „Remake" și „Încheie evenimentul": toate sunt lucruri de
                      apăsat, deci stau împreună. Nu se adună niciodată mai mult
                      de două-trei — cine poate urmări nu e organizatorul, iar
                      organizatorul nu se poate urmări pe sine. RÂNDUL SE
                      DESENEAZĂ ÎN inc/afisare-eveniment.php, nu în callback-ul
                      din event.php, fiindcă ce intră în el vine din două locuri
                      și unul dintre ele lipsește tocmai la vizitatorul obișnuit
                      — wrapper-ul scris de callback l-ar fi lăsat pe dinafară.
                      Fără niciunul (previzualizarea) nu se desenează deloc
                      VESTEA PLEACĂ O SINGURĂ DATĂ PE ANUNȚ
                      (`urmaritori_instiintati_la`, sql/033), cu ștampila pusă
                      ÎNAINTE de trimitere și hotărârea în `WHERE`, ca la
                      newsletter. Fără ea, un anunț respins și aprobat din nou
                      ar scrie de două ori acelorași oameni. SE CHEAMĂ DIN DOUĂ
                      LOCURI, fiindcă atâtea fac un anunț să se vadă:
                      api/eveniment.php (omul de casă publică direct) și
                      api/modereaza-eveniment.php (aprobarea). Amândouă cheamă
                      necondiționat — funcția întreabă singură.
                      CE SE CERE nu e doar „publicat": și `!evenimentIncheiat()`.
                      `evenimentPublicat()` spune DA și pentru unul încheiat,
                      fiindcă pagina lui se vede mai departe — dar „X a pus un
                      anunț nou" despre o seară care a trecut sună a bătaie de
                      joc. S-a găsit la scriere, e păzit de o probă
  newsletter.php    → NEWSLETTERUL ZILNIC: „ce se întâmplă azi în oraș". O dată
                      pe zi, la 12, către cine are bifa `membri.newsletter`.
                      Evenimentele se scriu ca niște CARTONAȘE ca pe prima
                      pagină — poza lată DEASUPRA, apoi categoria, titlul, un
                      început de text și rândul cu ora și locul. Poza a stat o
                      vreme în stânga, într-o casetă de 120px: la 16:9 asta
                      înseamnă 68px înălțime, adică o dungă în care nu se vede
                      nimic din afiș. Blocul „lista" din inc/email.php e cel
                      care le desenează; acolo scrie și de ce `<img>` poartă
                      `width`/`height` CA ATRIBUTE (singurul lucru pe care îl
                      citesc programele când poza e blocată) și de ce `alt` e
                      GOL.
                      DACĂ AZI NU E NIMIC, NU PLEACĂ NIMIC — un mesaj care
                      spune „azi nu se întâmplă nimic" e cel mai bun fel de
                      a-l învăța pe om să nu-l mai deschidă, iar peste o lună,
                      când chiar e ceva, ajunge tot necitit. Intră numai
                      evenimentele APROBATE de azi, în ordinea orei: cele
                      anulate au pagina lor mai departe pe site, dar a le
                      trimite dimineața ca pe ceva ce urmează ar fi o
                      minciună. ȘI NUMAI CE N-A ÎNCEPUT ÎNCĂ: lista pornește
                      de la CLIPA TRIMITERII, nu de la miezul nopții (al
                      doilea parametru al lui evenimenteleDeAzi(), citit o
                      singură dată pe rulare). Mesajul pleacă la 12 dinadins —
                      până atunci apucă să se scrie și anunțurile de dimineață
                      — dar prețul e că unele au și început: „azi la 10 e o
                      alergare", spus la 12, nu e o veste, e o părere de rău.
                      ORA SE TAIE LA MINUT, nu la secundă: cronul pus la 12:00
                      pornește în fapt la 12:00:07, iar un eveniment scris fix
                      la 12:00 n-are de ce să cadă pentru șapte secunde. Dacă
                      tot ce era azi a trecut, nu pleacă nimic — la fel ca
                      într-o zi goală. Ștampila (`newsletter_trimis_la`, sql/031) se
                      pune ÎNAINTE de trimitere și hotărârea e în `WHERE`, ca
                      la revendicarea unui abțibild: dintre „a plecat de două
                      ori" și „n-a plecat pentru că a căzut curentul între
                      ștampilă și poștă", se alege a doua — un e-mail plecat
                      nu se ia înapoi. TOT AICI DEZABONAREA:
                      cheieDezabonare() / semnaturaDezabonare() /
                      linkDezabonare() — un HMAC din id-ul omului și o cheie a
                      site-ului, NU un token ținut în bază. Un token scris la
                      fiecare trimitere ar fi însemnat că linkul de ieri moare
                      azi, iar cine caută peste trei luni un mesaj vechi ca să
                      se dezaboneze ar da peste „link expirat" și ar apăsa
                      „Spam" în schimb
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
  email.php         → șablon unic pentru toate email-urile (table-based, inline
                      style). CASETA DE CITAT e desenată dintr-un singur loc și
                      o cer DOUĂ blocuri: `citat` (ce a scris omul — comentariul
                      la care i s-a răspuns, dorința lui; deasupra stă NUMELE) și
                      `motiv` (de ce s-a luat o hotărâre care îl privește;
                      deasupra stă o VORBĂ: „Motivul, așa cum a fost scris de
                      organizator"). Pentru ochi sunt același lucru — un text
                      scos din curgerea mesajului — deci se desenează la fel.
                      MOTIVUL NU MAI E UN PARAGRAF ÎNTRE PARAGRAFE: scris așa,
                      se citea la fel de repede ca „Era programat pentru
                      miercuri", adică deloc, tocmai el, singurul rând pentru
                      care omul deschide mesajul. Îl pune cuMotivul(), pe care îl
                      cheamă TOATE cele șapte vești care poartă unul: anularea
                      unui eveniment, scoaterea de pe o listă, cele trei
                      hotărâri ale moderării, comentariul șters, poza ștearsă,
                      contul suspendat, dorința respinsă. FĂRĂ MOTIV NU SE
                      DESENEAZĂ NICIUN CHENAR — unul gol ar fi scos în evidență
                      tocmai lipsa; se scrie atunci un paragraf obișnuit, cu
                      aceeași vorbă peste tot (FARA_MOTIV). Al treilea bloc nou,
                      `dupa`, e pentru ce se spune DUPĂ casete („poți încerca
                      din nou", „intră și îndreaptă-l"): pus între celelalte
                      paragrafe, îndemnul ajungea ÎNAINTEA motivului, adică omul
                      citea ce are de făcut înainte să afle de ce
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
                      `stare_moderare` în „aprobat". DORINȚA OMULUI DE CASĂ
                      INTRĂ APROBATĂ DE-A DREPTUL (stareaDorinteiNoi, ca
                      starePentruPublicare la evenimente): el e cel pe la care
                      ar fi trecut, iar „în așteptare" ar fi însemnat să se
                      aprobe singur. NU pleacă niciun e-mail — vestea despre
                      hotărârea moderării o trimite api/admin.php, când chiar
                      hotărăște cineva ceva. `publicat_la` tot nu se pune la
                      scriere: îl pune stampileazaCeleAprobate(), ca la oricare
                      alta. TREI DORINȚE DEODATĂ de
                      om (DORINTE_DEODATA) — a fost UNA singură, și era prea
                      strâmt. „În lucru" înseamnă cele care așteaptă să fie
                      citite ȘI cele publicate care n-au împlinit șapte zile
                      (dorinteleInLucru); una respinsă, una ieșită de pe tablă
                      și una ștearsă de el nu țin niciun loc. Regula se ține la
                      SCRIERE (puneODorinta), nu în butonul de pe ecran, fiindcă
                      două file deschise deodată ar fi trimis amândouă. ȘI SUB
                      LACĂT: întrebarea „mai încape una?" și scrierea stau
                      într-o tranzacție, cu
                      `SELECT … FOR UPDATE` pe rândul omului din `membri`
                      (scrieDorintaSubLacat). Fără el, două SESIUNI deosebite
                      ale aceluiași om — laptopul și telefonul — intrau
                      amândouă; două file ale ACELUIAȘI browser nu erau de
                      ajuns ca să se vadă, fiindcă PHP ține un lacăt pe
                      fișierul sesiunii. poatePuneODorinta() are acum DOUĂ
                      stări, nu trei ('poate' | 'prea_multe'): de când sunt trei
                      dorințe, nu mai există „starea omului", ci starea fiecărei
                      dorințe în parte, iar aceea se scrie în tabelul de sub
                      tablă. AUTORUL ÎȘI POATE ȘTERGE O DORINȚĂ
                      (stergeDorintaOmului), și atunci se face loc pentru alta.
                      ȘTERGEREA E MOALE: se scrie `sters_la` (sql/032), rândul
                      rămâne — rândurile NU se șterg niciodată, nici după ce ies
                      de pe tablă, fiindcă mai târziu vrem să putem spune câte
                      dorințe și-au pus oamenii de-a lungul timpului. Totul se
                      hotărăște în `WHERE`: `membru_id = ?` (a lui, nu a altuia)
                      și `sters_la IS NULL` (nu se șterge de două ori). NU se
                      confundă cu ștergerea staff-ului din admin-dorinte.php,
                      care rămâne un DELETE adevărat, pentru ce n-are ce căuta
                      în numărătoare.
                      TOT AICI cum arată: randeazaTablaDorinte(),
                      butonulDorintei() — butonul „Pune-ți o dorință", care stă
                      în FEREASTRA DE BUN VENIT, lângă „Propune o ieșire", și
                      DISPARE doar pentru cine le are pe toate trei — și
                      randeazaZonaDorinte(), vorba despre dorințele lui („Ai
                      două dorințe în lucru. Mai poți pune una."). STĂ ÎN
                      SECȚIUNEA TABLEI, și când tabla nu se desenează — dar
                      atunci NUMAI dacă omul are ceva care AȘTEAPTĂ MODERAREA
                      ($aratZonaDorinte din index.php): o dorință publicată și
                      proaspătă e chiar una de pe tablă, deci tabla s-ar fi
                      desenat oricum. De aceea
                      `<section class="tabla">` din index.php se scrie și când
                      n-are nici tablă, nici formular. Ajungea, în cazul acela,
                      în `.section-head`, adică pe rândul lui „Ce facem zilele
                      astea?" — un rând cu `align-items: flex-end`, unde butonul
                      ieșea lipit de linia de bază a titlului, ca și cum ar fi
                      fost al lui. SUB EA, randeazaDorinteleMele():
                      butonul „Dorințele mele (2)" și tabelul care se deschide
                      din el, cu un „×" roșu în dreptul fiecărei dorințe. E un
                      `<details>`, nu un panou de JS — se deschide singur, în
                      orice browser, FĂRĂ o linie de JavaScript. Fiecare „×" e
                      un `<form method="post">` adevărat spre /index.php, ca să
                      meargă și cu JS-ul stins; cu JS, main.js îi ia locul și
                      cheamă api/sterge-dorinta.php. FĂRĂ CONFIRMARE, dinadins:
                      o dorință e un rând de o sută de caractere, iar ce se
                      pierde e o frază pe care omul o poate scrie din nou.
                      Data se scrie cu dataScrisaMic() (stă în
                      inc/validare.php, lângă dataLunga — o cere și termenul
                      notelor de pe pagina unui eveniment): cu ziua săptămânii,
                      fără an, cu literă mică. puneODorinta()
                      și stergeDorintaOmului() sunt chemate amândouă din DOUĂ
                      locuri: api/ (cu JS) și index.php (fără)
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
                      singurul care primește multipart, fiindcă urcă un fișier.
                      DOUĂ dintre cele de pe pagina evenimentului nu întreabă
                      „e al tău?", ci „ești de-al casei?": modereaza-eveniment.php
                      și fixeaza-eveniment.php (piuneza). Restul —
                      incheie-eveniment.php, anuleaza-eveniment.php — sunt ale
                      organizatorului.
                      interes.php ÎNSCRIE SUB TRANZACȚIE, cu
                      `SELECT … FOR UPDATE` pe rândul evenimentului: între
                      „mai sunt locuri?" și „scrie-mă pe listă" încap alte
                      cereri, iar opt oameni care apasă deodată la un eveniment
                      cu două locuri intrau toți. Cine vine al doilea așteaptă
                      la ușă până se hotărăște primul. Orice altă socoteală de
                      forma „citește, hotărăște, scrie" pe un lucru cu număr
                      limitat se face la fel
cron/               → scripturi rulate din cron (doar CLI, .htaccess le blochează)
                      anonimizeaza-conturi.php      — o dată pe zi
                      multumeste-participantilor.php — din oră în oră
                      newsletter-zilnic.php         — o dată pe zi, la 12:00
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
                      028-feedback-instiintat, 029-eveniment-fixat,
                      030-incercari-qr, 031-newsletter-zilnic,
                      032-dorinte-mai-multe, 033-urmariri)
                      `dorinte.sters_la` (032) e tot ce trebuie ca omul să-și
                      poată lua o dorință înapoi: ștergerea e MOALE, rândul
                      rămâne pentru numărătoarea de mai târziu. Cele TREI
                      dorințe deodată NU au coloană — se numără rândurile în
                      lucru ale omului (dorinteleInLucru)
                      `membri.newsletter_trimis_la` (031) e singurul lucru care
                      ține „cel mult unul pe zi": fără el, un cron pornit de
                      două ori trimite de două ori, iar o rulare de mână ca să
                      se vadă dacă merge ajunge la toată lumea. Se pune ÎNAINTE
                      de trimitere, nu după — un e-mail plecat nu se ia înapoi
                      `incercari_qr` (030) ține minte scanările de abțibild care
                      N-AU NIMERIT nimic, ca să se poată număra: fără ea, un
                      program care încearcă coduri de pe canapea nimerea unul
                      dintre cele active în câteva ore, și „FindMe" nu mai era o
                      plimbare prin oraș. TABEL AL LUI, nu `incercari_autentificare`
                      — acolo numărătoarea duce la blocarea unui cont, iar o
                      limită de pe altă pagină n-are voie să încuie contul
                      cuiva. Rândurile vechi se șterg singure, la scanare, fără
                      cron
                      Zona de administrare citește și schimbă, în cea mai mare
                      parte, ce era deja acolo. SINGURA coloană nouă a ei e
                      `evenimente.corectura_ceruta_la` (026): ștampila pusă la
                      „respinge, dar cu editare necesară" și ștearsă la prima
                      editare a omului. E DATETIME, nu 0/1, tocmai ca să se vadă
                      și de cât timp așteaptă
teste/              → router.php: serverul de probă cu ADRESE FRUMOASE.
                      `php -S 127.0.0.1:8099 teste/router.php` din rădăcină, și
                      merge tot ca pe găzduire; fără el, `/eveniment/<slug>` dă
                      404 în dezvoltare, fiindcă serverul din PHP nu citește
                      .htaccess. ACOLO rămâne locul adevărat al regulilor —
                      aici sunt scrise a doua oară DOAR cele două rescrieri de
                      care atârnă adrese
                      test-validare.php (verificările din inc/validare.php;
                      verificaDorinta e probată în test-dorinte.php, lângă
                      restul tablei)
                      test-comentarii.php, test-participanti.php,
                      test-evaluari.php, test-multumiri.php
                      test-newsletter.php (newsletterul zilnic; cere baza, iar
                      pagina de dezabonare cere și serverul — se sare singură.
                      ATENȚIE: stinge bifa de newsletter la toți membrii din
                      bază pe durata ei, ca să servească doar oamenii ei, și o
                      pune la loc la sfârșit. Se uită DOAR la evenimentele ei,
                      după slug: una care ar număra rândurile din
                      evenimenteleDeAzi() ar pica în orice zi în care se
                      întâmplă ceva în oraș, adică tocmai în zilele care
                      contează)
                      (toate patru cer baza de date, nu și serverul)
                      test-admin.php (zona de administrare; cere baza, iar
                      partea de HTTP cere și serverul — se sare singură. Păzește
                      mai presus de orice că paginile și faptele sunt NUMAI
                      pentru staff)
                      test-findme.php (abțibildele cu coduri QR ȘI frâna
                      împotriva ghicitului; cere baza,
                      iar partea de HTTP cere și serverul — se sare singură.
                      Secțiunea „bâjbâiala" își pune singură un REMOTE_ADDR
                      din gama de probe 203.0.113.x, fiindcă din linia de
                      comandă nu există unul, și îl ia de acolo la sfârșit)
                      test-dorinte.php (tabla cu dorințe; cere baza, iar
                      partea de HTTP cere și serverul — se sare singură)
                      test-urmariri.php (urmărirea unui organizator; cere baza,
                      iar paza punctului de intrare cere și serverul — se sare
                      singură. Păzește mai presus de orice că VESTEA PLEACĂ O
                      SINGURĂ DATĂ pe anunț: butonul se vede pe ecran și se
                      descoperă repede dacă se strică, dar un al doilea e-mail
                      către aceiași oameni nu se vede nicăieri)
                      test-documente.php (termeni, confidențialitate, cookies;
                      cere SERVERUL, se sare singură fără el. Leagă vorbele din
                      documente de lucrul din cod care le face adevărate — și
                      păzește promisiunea „fără urmărire", căutând în cod
                      Analytics, pixeli și celelalte)
                      test-tine-minte.php, test-setari.php, test-contact.php,
                      test-evenimente.php, test-prima-pagina.php,
                      test-constructie.php, test-anulare.php, test-moderare.php
                      (ultimele opt cer serverul pornit — vezi antetul lor;
                      test-prima-pagina, test-constructie și test-anulare merg
                      și fără, sar doar partea care cere serverul. ATENȚIE:
                      test-constructie
                      pornește și oprește `in_constructie` din inc/config.php,
                      și îl pune la loc cum l-a găsit. TOT EL păzește ANTETELE
                      DE SIGURANȚĂ, fiindcă e singurul care cere și afișul de
                      șantier, singura pagină din afara lui inc/antet.php: că
                      există politică de conținut, că scriptul temei poartă
                      aceeași cifră ca antetul, și că la a doua cerere cifra e
                      alta)
private/            → loguri (emailuri-trimise.log), protejat prin .htaccess
.htaccess           → CEL DIN RĂDĂCINĂ nu închide nimic (celelalte șase, da):
                      https obligatoriu, mod_deflate pe text (style.css 180 KB
                      + main.js 230 KB → sub 100 împachetate), cache de un an
                      pe css/js — au voie fiindcă adresa poartă `?v=`, vezi
                      regula 2 — și `no-store` pe .php, ca „înapoi" după ieșirea
                      din cont să nu arate pagina cu numele omului pe ea. TOTUL
                      în <IfModule>: o găzduire fără modulul cerut sare bucata,
                      nu dă 500 pe tot site-ul. TOT EL rescrie ADRESELE
                      FRUMOASE: `/eveniment/<slug>` → event.php?slug=…, și
                      `/sitemap.xml` → sitemap.php. Fără mod_rewrite site-ul
                      merge mai departe, doar că adresele frumoase dau 404 —
                      de aceea urlEveniment() e într-un singur loc
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
  mesaje în `mesaje_contact`, scanări greșite de abțibild în `incercari_qr`), nu
  într-un sistem separat. `incercari_autentificare`
  rămâne doar pentru intrarea în cont: acolo numărătoarea duce la blocare, iar o
  limită de pe altă pagină n-are voie să încuie contul cuiva.
- Adresa IP se ia MEREU din `REMOTE_ADDR`, printr-o singură funcție —
  `ipBinar()` din `inc/bootstrap.php` — și NICIODATĂ dintr-un antet de forma
  „X-Forwarded-For": pe acelea le scrie oricine, iar o limită care se ocolește
  schimbând un antet nu e o limită. Dacă site-ul ajunge vreodată în spatele unui
  Cloudflare sau al unui alt proxy, se schimbă funcția aceea, într-un singur
  loc, și se scrie acolo pe ce proxy anume se are încredere.
- Politica de conținut (CSP) e a doua plasă sub escaparea cu `h()`: dacă un
  `<script>` ar scăpa vreodată într-o pagină, browserul refuză să-l ruleze
  fiindcă n-are cifra cererii. Vezi `antetedeSiguranta()` în `inc/bootstrap.php`.
- „Ține-mă minte" = rând în `sesiuni_amintite`, nu un cookie de sesiune lung.
  Cookie-ul are forma `selector:secret`, în DB stă doar sha256 al secretului,
  se rotește la fiecare folosire, e legat de amprenta browserului, iar
  reapariția unui secret vechi stinge toate amintirile membrului.
  Intrarea cu Google îl pornește din start (n-are unde sta bifa).

## Cum se scrie către om

Tot ce citește un utilizator — mesaje de sub câmpuri, bule de confirmare,
e-mailuri, texte de pe pagini — se scrie ca și cum ar vorbi un om, nu un
sistem. Regulile care s-au strâns din trecerea de purificare:

- **Confirmările sunt la persoana întâi**: „Am șters comentariul.", nu
  „Comentariul a fost șters." La pasiv, mesajul e un sistem care anunță o
  stare; la activ, e cineva care spune ce a făcut.
- **Verificările spun ce lipsește ȘI ce e de făcut**: „Numele pare cam scurt.
  Mai adaugă câteva litere.", nu „Numele pare prea scurt." Când e o limită, se
  scrie „cam lung — încape în cel mult N", nu „prea lung (maximum N)".
- **Niciodată limbă de ghișeu.** Sunt de ocolit: „vă rugăm", „în cel mai scurt
  timp", „prezenta acțiune", „își rezervă dreptul", „în mod automat", „în
  cadrul", „nu s-a specificat", „menționat", „ulterior", „necesită".
- **Vestea proastă are întotdeauna o ușă la capăt.** Un „nu" fără nicio cale
  înainte e cel mai prost fel de a închide o discuție: la respingere se spune
  „Nimic nu e pierdut: intră pe pagina de editare…".
- **Un singur nume pentru un lucru**: „eveniment", nu „activitate"; „publică",
  nu „postează"; „părere", nu „feedback".
- **MESAJELE DE CÂMP SUNT SCRISE DE DOUĂ ORI**, o dată în `inc/validare.php` și
  o dată în `assets/js/main.js` (verificarea din browser). SCHIMBATE ÎNTR-UN
  SINGUR LOC, omul citește o vorbă înainte de trimitere și alta după, pe
  același câmp, la o secundă distanță. S-a întâmplat: opt perechi rămăseseră în
  urmă. Când schimbi un mesaj, caută-l în amândouă, plus în `api/` (unele API-uri
  își au copia lor: autentificare, parola-uitata, parola-noua,
  retrimite-confirmare).
- Probele se uită la vorbele exacte. Un text schimbat înseamnă și o probă
  mutată — niciodată una ștearsă. Uneori proba are dreptate: la trimiterea unui
  anunț, vorba TREBUIE să spună „merge spre aprobare", fiindcă acolo se
  desparte omul de casă (publică direct) de restul.

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
- CELE TREI DOCUMENTE — `termeni.php`, `confidentialitate.php`, `cookies.php` —
  sunt scrise după același tipar și duc una la alta. CIFRELE DIN ELE NU SE SCRIU
  DE MÂNĂ: vin din constantele care hotărăsc purtarea site-ului
  (`ZILE_RAGAZ_STERGERE`, `ORE_PENTRU_NOTE`, `VARSTA_MIN`, `ZILE_TINE_MINTE`,
  `ZILE_PASTRARE_INCERCARI`, `COOKIE_TINE_MINTE`), tocmai ca documentul să nu
  poată rămâne în urma codului. `teste/test-documente.php` prinde ziua în care
  cineva scrie totuși o cifră de mână. CINE ȚINE SITE-UL se citește din
  `config.php` (cheia `operator`), printr-un singur loc — `operatorulSite()` din
  `inc/bootstrap.php`; cât timp numele e gol, paginile spun pe față că datele nu
  sunt trecute, în loc să lase un gol sau un nume închipuit.
  SUNT UȘI DESCHISE ÎN CONSTRUCȚIE (`usileDeschiseInConstructie`): afișul de
  șantier cere o adresă de e-mail, iar o politică de confidențialitate încuiată
  tocmai pentru omul de care e scrisă e o contradicție în termeni.
  DE VERIFICAT LA FIECARE FUNCȚIE NOUĂ care strânge ceva despre om: dacă
  `confidentialitate.php` nu se schimbă odată cu ea, documentul începe să mintă.
  La fel, orice program de măsurare adăugat vreodată strică promisiunea „fără
  urmărire" din `cookies.php` ȘI cere banner de acord — proba „nu e niciun
  program de urmărire în cod" e acolo ca să nu treacă tăcut
- VÂRSTA MINIMĂ E `VARSTA_MIN` = 10 ANI, iar asta e o problemă juridică
  nerezolvată: sub GDPR, în România, un copil își poate da singur acordul pentru
  prelucrarea datelor abia de la 16 ani. Documentele scriu ce face codul (de la
  10 ani, cu acordul părintelui sub 16), dar site-ul NU cere și nu verifică
  nicăieri acordul acela. De hotărât: ori se ridică `VARSTA_MIN` la 16, ori se
  face un drum adevărat pentru acordul părintesc
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
- Adresele frumoase există pentru evenimente: `/eveniment/<slug>`. Profilul a
  rămas `profil.php?m=<permalink>`, dinadins — un permalink e zece semne la
  întâmplare, n-are ce câștiga din a fi scos în cale.
  TOATE ADRESELE PE CARE LE SCRIE SITE-UL PORNESC DE LA RĂDĂCINĂ, cu `/` în
  față: `/index.php`, `/assets/css/style.css`, `fetch('/api/…')`. Nu e o
  preferință, e o cerință — pagina unui eveniment stă la adâncimea 1, iar un
  `assets/…` scris fără `/` ar fi căutat în `/eveniment/assets/…`. Așa se rupe
  un site la trecerea la adrese frumoase, și se rupe TĂCUT: pozele lipsesc,
  legăturile dau 404. Există o probă care păzește regula asta
  („nicio adresă relativă în pagină", în teste/test-evenimente.php).
  DE AICI DECURGE: site-ul stă la RĂDĂCINA domeniului, nu într-un subdosar.
- Înștiințările pe e-mail care pleacă azi: mulțumirea de după eveniment
  (cron/multumeste-participantilor.php), vestea că cineva a fost scos de pe
  listă, vestea că un eveniment s-a anulat (api/anuleaza-eveniment.php),
  hotărârea moderării (api/modereaza-eveniment.php), comentariile noi
  (api/comentarii.php → instiinteazaDeComentariu, cu bifa `email_comentarii`
  din setări) și PĂRERILE SCRISE pe profil (api/evaluare.php →
  omDeInstiintatLaFeedback, cu bifa `email_feedback`; stelele singure NU
  vestesc nimic, fiindcă sunt anonime). PLUS CELE PATRU ALE ZONEI DE ADMINISTRARE, toate cu motiv care
  poate lipsi (api/admin.php): comentariul șters, poza ștearsă, contul
  suspendat și hotărârea unei dorințe. PLUS VESTEA CĂTRE URMĂRITORI (inc/urmariri.php →
  instiinteazaUrmaritorii, la fiecare anunț nou al cuiva urmărit; ieșirea nu e
  o bifă, ci butonul de pe profilul lui). PLUS NEWSLETTERUL ZILNIC
  (cron/newsletter-zilnic.php, cu bifa `newsletter`) — SINGURUL mesaj de pe
  site care vine nechemat, și de aceea singurul cu link de dezabonare și cu
  antetul `List-Unsubscribe`. Celelalte sunt răspunsuri la ceva ce a făcut
  omul, iar alea nu se „dezabonează". NU pleacă nimic când cineva se înscrie
  la un eveniment, când se apreciază un comentariu sau când se raportează ceva
- Tabla cu dorințe se moderează din `admin-dorinte.php`, DINTR-O SINGURĂ LISTĂ
  DE ALES (aceeași unealtă ca starea contului din `admin-useri.php`), cu patru
  rânduri: „Așteptare", „Aprobă", „Respinge", „Șterge". RÂNDUL ÎN CARE DORINȚA
  E DEJA se scrie ca STARE, nu ca poruncă („Aprobat", „Respins" — tabloul
  `$hotarariAcum` lângă `$hotarari`): o listă strânsă arată un singur rând, iar
  „Aprobă" pe o dorință aprobată era un lucru rămas de făcut tocmai despre ceva
  făcut. „Așteptare" e la fel în amândouă, fiindcă e un nume, nu o faptă.
  Erau trei butoane, și
  două dintre ele se vedeau numai cât dorința aștepta: o dată hotărâtă, nu mai
  era nicio cale înapoi din interfață. De aceea `modereaza-dorinta` primește
  acum și `in_asteptare`, iar starea DIN CARE se pleacă a intrat în `WHERE`
  (citită în aceeași cerere, nu `'in_asteptare'` scris de mână) — apărarea de
  filele lăsate deschise rămâne, dar nu mai închide drumul înapoi. Aleasă
  starea în care e deja, nu se scrie și nu pleacă nimic. ÎNTOARCEREA ÎN
  AȘTEPTARE NU TRIMITE NICIUN E-MAIL (acolo nu s-a hotărât nimic), dar
  RĂZGÂNDIREA trimite: dorința tocmai a intrat sau a ieșit de pe tablă. O
  dorință RETRASĂ de autor nu se mai moderează deloc — lista arată „retrasă",
  stinsă, și „Șterge". „Șterge" e ALTĂ FAPTĂ (`sterge-dorinta`): `<option>`-ul
  își poartă pe el `data-fapta` și `data-intreb`, iar în main.js fapta scrisă
  pe alegere bate fapta scrisă pe listă.
  Fiecare dorință intră
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
- Autorul ÎȘI POATE ȘTERGE dorințele, din „Dorințele mele", de sub tablă — un
  „×" roșu în dreptul fiecăreia, fără confirmare. Ștergerea e MOALE: se scrie
  `dorinte.sters_la` (sql/032), rândul rămâne pentru numărătoarea de mai
  târziu, dar dorința dispare de pe tablă și face loc alteia dintre cele trei.
  Ștergerea staff-ului din `admin-dorinte.php` e ALTCEVA și rămâne un DELETE
  adevărat — singura ștergere adevărată de pe site: e pentru ce n-are ce căuta
  în numărătoare, o înjurătură sau un test. O dorință retrasă de autor se vede
  mai departe în tabelul staff-ului, însemnată „retrasă", dar nu mai are
  butoane de moderare și nu mai aprinde cifra de pe panou: omul și-a luat
  vorbele înapoi, deci nu mai e nimic de hotărât
- Dorințele nu se numără nicăieri. Rândurile rămân în `dorinte` pentru
  totdeauna — și după ce ies de pe tablă, și după ce le șterge autorul (atunci
  au doar `sters_la` scris) — tocmai ca mai târziu să se poată arăta câte
  dorințe și-au pus oamenii de-a lungul timpului. Pagina care s-o spună nu
  există încă
- Comentariile raportate se adună în `admin-comentarii.php`, cel mai raportat
  în cap. Două butoane, nu unul: „Șterge" (cu motiv, care îi pleacă autorului pe
  e-mail) și „E în regulă", care ȘTERGE RAPOARTELE, nu comentariul — se
  raportează și din greșeală, și din răutate, iar fără al doilea buton singurul
  fel de a închide un raport nedrept ar fi fost să ștergi ce n-avea nimic. Cine
  a raportat NU află nimic, în niciunul din cazuri
- Notele nu se pot retrage și nici raporta DE CĂTRE OAMENI — dar staff-ul le
  vede și le poate șterge, din `admin-evaluari.php` sau de-a dreptul de pe
  profil, cu „×"-ul din dreptul fiecărei păreri scrise. Nici
  „Nu s-a prezentat" nu se ia înapoi: e definitivă, dinadins, ca organizatorul
  să n-o poată schimba mai târziu — dar dacă a pus-o din greșeală, rândul se
  șterge de mână din phpMyAdmin
- Paginile de categorie (slugurile sunt în tabelul `categorii`)
- Imaginile implicite de categorie (`categorii.imagine_default`) — se urcă DE
  MÂNĂ în `assets/img/categorii/`, nu prin `inc/imagini.php`, iar în bază stă
  doar numele fișierului („socializare.jpg"). Adresa se scrie DOAR prin
  `urlImagineCategorie()` (inc/imagini.php), care se uită și pe disc: un fișier
  lipsă înseamnă „fără poză", nu o adresă care dă 404. E singura cale de imagine
  care vine DIN BAZĂ, nu din cod — de aceea a scăpat la trecerea la adrese
  absolute și dădea 404 de pe `/eveniment/<slug>`.
  ATENȚIE: dosarul ăsta NU primește un `.htaccess` ca `membri/` și
  `evenimente/` — acolo lista albă e `^[0-9a-f]{32}\.jpg$`, adică ar bloca
  tocmai fișierele cu nume citibil de aici. Nici nu-i trebuie: în el nu scrie
  nimeni din afară.

## Workflow recomandat cu Claude Code

- La task-uri mari/neînrudite, pornește sesiune nouă (`/clear`) în loc să lași
  contextul să crească spre auto-compact.
- Referă fișiere explicit (ex: "citește inc/validare.php") în loc de "cum am discutat
  mai devreme" — mai fiabil decât memoria conversației.
- Rulează `php teste/test-validare.php` după orice modificare la `inc/validare.php`.
