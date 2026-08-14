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
stergere.php, iesire.php, verifica.php

inc/
  antet.php        → head + meniu + antete siguranță (folosit de toate paginile)
  subsol.php        → footer + scripturi
  bootstrap.php     → config, conexiune DB, sesiune, CSRF
  validare.php      → toate verificările server-side (fără atingere DB)
  imagini.php       → procesare/validare poze de profil ȘI coperți de eveniment
  evenimente.php    → categorii, regula „un eveniment activ", lista de pe
                      profil, pagina unui eveniment, salvarea
  interese.php      → „Mergi la acest eveniment?" — cine e interesat, cine
                      vine, numărătoarea, locurile, rândul cu chipuri, listele
                      din taburi (aceeași funcție pentru amândouă) ȘI
                      scoaterea cuiva de pe cea de participanți
  comentarii.php    → discuția de sub eveniment: cele două niveluri,
                      aprecierile, ștergerea cu piatră de mormânt, ȘI cum
                      arată pe ecran (HTML-ul se scrie doar aici)
  afisare-eveniment.php → CUM ARATĂ un eveniment pe ecran (antet, copertă,
                      caseta cu detalii, descrierea). Folosit și de event.php,
                      și de previzualizare.php — schimbă aici, nu în pagini
  email.php         → șablon unic pentru toate email-urile (table-based, inline style)
  google.php        → OAuth Google (authorization code flow + PKCE)
  buton-google.php  → butonul de login Google
  stergere.php      → ștergerea contului cu răgaz + anonimizarea
  camp-parola.php   → un câmp de parolă cu ochi (folosit de toate paginile)

api/                → endpoint-uri JSON apelate din JS (fetch); eveniment.php e
                      singurul care primește multipart, fiindcă urcă un fișier
cron/               → scripturi rulate din cron (doar CLI, .htaccess le blochează)
sql/                → schema.sql + migrări numerotate (002, 003, 004, 005-google,
                      006-tine-minte, 007-setari, 008-mesaje-contact,
                      009-evenimente, 010-limita-evenimente,
                      011-anulare-eveniment, 012-oras-eveniment,
                      013-interese-evenimente, 014-incheiere-eveniment,
                      015-comentarii, 016-excluderi-evenimente)
teste/              → test-validare.php (verificările din inc/validare.php)
                      test-comentarii.php, test-participanti.php
                      (amândouă cer baza de date, nu și serverul)
                      test-tine-minte.php, test-setari.php, test-contact.php,
                      test-evenimente.php
                      (ultimele patru cer serverul pornit — vezi antetul lor)
private/            → loguri (emailuri-trimise.log), protejat prin .htaccess
assets/css/style.css, assets/js/main.js, assets/img/
```

## Config

- `inc/config.example.php` → copiezi la `inc/config.php` (gitignored, nu urcă pe GitHub)
- `orase => ['Roman']` — orașele în care se pot pune evenimente. Un oraș nou =
  un rând aici, atât: lista se citește prin `oraseDisponibile()` și de formular,
  și de `verificaEveniment()`. Nu există tabel în bază pentru ea.
- `dezvoltare => true/false` — în dev, linkurile de confirmare apar direct în pagină +
  log în `private/`. Pe producție OBLIGATORIU `false`.
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
- Prima pagină (`index.php`) e tot cu articole scrise de mână; linkurile duc la
  `event.php` fără slug, deci se întorc pe `index.php`
- Moderarea: fiecare eveniment intră cu `stare_moderare = 'in_asteptare'`; se
  vede doar organizatorului, pe profilul lui și pe pagina evenimentului; nu
  există interfață de aprobare
- Staff: există doar steagul `membri.este_staff` (se pune de mână, din
  phpMyAdmin) și funcția `esteStaff()`. Singurul lucru pe care îl deschide e
  pagina unui eveniment anulat. Nu există pagină de administrare
- Curățenia evenimentelor anulate: rândul cu `stare_moderare = 'anulat'` rămâne
  în bază pentru totdeauna. Ștergerea lui (cu tot cu coperta de pe disc,
  înscrieri și comentarii) e o acțiune viitoare de staff — vezi `TODO`-ul din
  `anuleazaEveniment()`
- Adresele frumoase: acum sunt `profil.php?m=<permalink>` și `event.php?slug=…`
- Înștiințările: nimeni nu află nimic prin e-mail. Cine primește un răspuns la
  comentariu nu vede decât dacă se întoarce singur pe pagină, iar la anularea
  unui eveniment vezi `TODO`-ul din `anuleazaEveniment()` — trebuie trimis
  e-mail cu textul din `motiv_anulare`, ÎNAINTE de ștergerea făcută de staff
- Comentariile: nu există raportare și nici pagină de moderare. Staff-ul umblă
  la ele de pe pagina evenimentului, ca oricare autor
- Evenimentele la care merge cineva nu apar pe profilul lui
- Paginile de categorie (slugurile sunt în tabelul `categorii`)
- Imaginile implicite de categorie (`categorii.imagine_default`) — coloana
  există, fișierele nu; se urcă de mână, nu prin `inc/imagini.php`

## Workflow recomandat cu Claude Code

- La task-uri mari/neînrudite, pornește sesiune nouă (`/clear`) în loc să lași
  contextul să crească spre auto-compact.
- Referă fișiere explicit (ex: "citește inc/validare.php") în loc de "cum am discutat
  mai devreme" — mai fiabil decât memoria conversației.
- Rulează `php teste/test-validare.php` după orice modificare la `inc/validare.php`.
