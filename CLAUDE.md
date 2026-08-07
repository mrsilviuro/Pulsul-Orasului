# PulsulOrasului.Ro — context pentru Claude Code

Blog/platformă de evenimente locale. PHP + MySQL pe server, fără framework, fără build step.
Site live: https://pulsulorasului.ro

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

## Structură fișiere

```
index.php, articol.php, contact.php, despre.php, login.php,
profil.php, poza.php, setari.php, parola-uitata.php, parola-noua.php,
google.php, finalizare.php, confirma.php, stergere.php, iesire.php, verifica.php

inc/
  antet.php        → head + meniu + antete siguranță (folosit de toate paginile)
  subsol.php        → footer + scripturi
  bootstrap.php     → config, conexiune DB, sesiune, CSRF
  validare.php      → toate verificările server-side (fără atingere DB)
  imagini.php       → procesare/validare poze de profil
  email.php         → șablon unic pentru toate email-urile (table-based, inline style)
  google.php        → OAuth Google (authorization code flow + PKCE)
  buton-google.php  → butonul de login Google
  stergere.php      → ștergerea contului cu răgaz + anonimizarea
  camp-parola.php   → un câmp de parolă cu ochi (folosit de toate paginile)

api/                → endpoint-uri JSON apelate din JS (fetch)
cron/               → scripturi rulate din cron (doar CLI, .htaccess le blochează)
sql/                → schema.sql + migrări numerotate (002, 003, 004, 005-google,
                      006-tine-minte, 007-setari.sql)
teste/              → test-validare.php (verificările din inc/validare.php)
                      test-tine-minte.php, test-setari.php
                      (ultimele două cer serverul pornit — vezi antetul lor)
private/            → loguri (emailuri-trimise.log), protejat prin .htaccess
assets/css/style.css, assets/js/main.js, assets/img/
```

## Config

- `inc/config.example.php` → copiezi la `inc/config.php` (gitignored, nu urcă pe GitHub)
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

## Ce e neterminat (roadmap)

- Formularul de publicat un eveniment
- Paginile de categorie

## Workflow recomandat cu Claude Code

- La task-uri mari/neînrudite, pornește sesiune nouă (`/clear`) în loc să lași
  contextul să crească spre auto-compact.
- Referă fișiere explicit (ex: "citește inc/validare.php") în loc de "cum am discutat
  mai devreme" — mai fiabil decât memoria conversației.
- Rulează `php teste/test-validare.php` după orice modificare la `inc/validare.php`.
