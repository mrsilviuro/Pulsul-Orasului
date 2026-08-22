# PulsulOrasului.Ro

Șablon minimalist modern pentru blog de evenimente locale. HTML + CSS + JS simplu,
fără build, fără dependențe. Deschizi `index.html` direct în browser.

## Structură

```
index.html
assets/
  css/style.css      → tokens de culoare, layout, componente, responsive
  js/main.js         → temă, meniu mobil, paginile cu formulare
  img/hero-zi.svg    → fundalul primei ferestre, tema deschisă (3200×1400)
  img/hero-noapte.svg→ același, tema închisă
  img/posts/         → thumbnail-urile articolelor (16:9)
  img/favicon.svg
```

Thumbnail-urile din `img/posts/` sunt SVG-uri generate, doar ca placeholder — le
înlocuiești cu poze reale (JPG/WebP), păstrând raportul 16:9. Cele două
`hero-*.svg` nu sunt placeholder: sunt desenul primei ferestre (vezi mai jos).

## Prima fereastră (index.php)

Prima pagină se deschide cu un panou cât toată fereastra browserului, imediat
sub bara de meniu: singura bucată de site care nu se oprește la `--wrap`.
Înăuntru: „Bun venit pe / PulsulOrasului.Ro", îndemnul, butonul „Propune o
ieșire" și o săgeată spre restul paginii.

Nu are nicio linie de JavaScript. Trei straturi suprapuse, toate din CSS
(secțiunea „5. PRIMA FEREASTRĂ" din `style.css`):

| strat | ce face |
|---|---|
| `.hero__fundal` | desenul orașului, blurat ușor și plimbat încet stânga↔dreapta |
| `.hero__voal`   | îl stinge cât să se poată citi textul, și se topește în culoarea paginii la marginea de jos |
| `.hero__continut` | textul și butonul |

Ce e bine de știut dacă umbli la el:

- **Fundalul e un `<div>` gol cu `background-image`, nu un `<img>`.** Se schimbă
  după `[data-theme]`, iar tema de pe site se pune de mână, din butonul cu
  luna. Un `<picture>` cu `prefers-color-scheme` ar fi ascultat de sistem și ar
  fi lăsat poza de zi pe cine apasă butonul.
- **Desenele sunt panoramice (3200×1400) dinadins:** lățimea în plus e tocmai
  ce se plimbă. Elementul e lat 132% și merge până la `-24.242%` din el, adică
  exact 32% din fereastră — nici un pixel mai încolo, ca să nu se vadă capătul.
  Dacă schimbi una din cifre, schimbă-le pe amândouă.
- **Le desenează un script**, nu mâna: vezi comentariul din capul fișierelor
  SVG. Sunt forme mari și puține, fiindcă peste ele stă text și oricum le
  blurăm — o fotografie cu amănunte ar fi ieșit tot o pată, doar una de 400KB.
- **Săgeata de jos e o legătură adevărată** (`<a href="#main">`), nu un buton de
  JS: merge și cu JavaScript-ul stins. Unde se oprește pagina scrie în
  `#main { scroll-margin-top }`, nu în JS.
- Pe telefon cadrul e înalt și îngust, iar „cover" ar fi intrat tot desenul —
  adică jumătate de cer gol. Sub 560px fundalul e făcut cu 18% mai înalt decât
  cadrul și împins în jos, ca orașul să urce în ecran.

## Tabla cu dorințe

Imediat sub prima fereastră, deasupra listei de evenimente. E treapta de
dinaintea unui eveniment: ca să organizezi ceva trebuie să-ți iei o răspundere
— ziua, locul, ora, oamenii care vin — și nu toți vor asta. Dar mulți ar veni
la ceva, dacă s-ar face. Tabla e locul unde spui doar atât: „mi-ar plăcea să
se facă asta".

Un rând arată așa: **P. Ana** și-ar dori: *un turneu de șah în parc*, cu orașul
dedesubt. Cel mult zece deodată, alese **la întâmplare** la fiecare încărcare a
primei pagini — cu „ultimele zece" a unsprezecea n-ar fi fost citită niciodată.

Tot codul stă în `inc/dorinte.php`; tabelul, în `sql/023-dorinte.sql`.

### Regulile

| | |
|---|---|
| lungime | cel mult **100 de caractere** (`DORINTA_MAX`), numărate cu `mb_strlen` |
| câte deodată | **una** de om. O dorință respinsă nu-l oprește să încerce din nou; una în așteptare sau una încă pe tablă, da |
| cât stă | **7 zile** (`ZILE_PE_TABLA`), numărate **de la aprobare**, nu de la trimitere |
| după aceea | iese de pe tablă și omul poate pune alta. Rândul NU se șterge |
| se poate schimba? | nu. Nici șterge. Omul e înștiințat în formular, înainte să apese |

Regula „o singură dorință" se ține **la scriere** (`puneODorinta`), nu în
butonul de pe ecran: două file deschise deodată ar fi trimis amândouă.

### Unde stă butonul

**„Pune-ți o dorință" e în fereastra de bun venit**, lângă „Propune o ieșire" —
acolo se hotărăște omul ce vrea să facă: ori pune la cale o ieșire, ori spune
doar ce i-ar plăcea. Îl desenează `butonulDorintei()`.

Butonul **dispare** pentru cine are deja o dorință în lucru: ar fi dus la un
formular pe care serverul îl refuză oricum. Ce-i rămâne de aflat scrie sub
tablă, prin `randeazaZonaDorinte()`:

| starea lui | ce se vede în fereastră | ce scrie sub tablă |
|---|---|---|
| n-are niciuna | butonul | nimic |
| a trimis una, se citește | — | „Dorința ta așteaptă să fie citită." |
| e pe tablă | — | „Dorința ta e pe tablă până joi, 27 august." |

Data e scrisă cu ziua săptămânii și **fără an** (`dataLunga($d, false)`): ziua
săptămânii e ce caută omul întâi („mai am până joi"), iar anul, la șapte zile
depărtare, nu spune nimic. În mijlocul frazei intră cu literă mică.

Când tabla nu se desenează (n-are nicio dorință), vorba trece în capul listei,
lângă „Ce facem zilele astea?".

### Cum se aprobă o dorință

Din `admin-dorinte.php`, cu două butoane. Merge mai departe și din phpMyAdmin —
e de ajuns `stare_moderare`:

```sql
UPDATE dorinte SET stare_moderare = 'aprobat' WHERE id = 7;   -- sau 'respins'
```

Atât. `publicat_la` **nu se pune de mână**: îl scrie codul, cu ceasul PHP, la
prima încărcare a primei pagini (`stampileazaCeleAprobate`). Altfel ar fi
intrat `NOW()`-ul lui MySQL, din alt fus, într-un lucru care se numără în zile.

### Ce e bine de știut dacă umbli la ea

- **Merge fără JavaScript, tot.** Butonul „Pune-ți o dorință" e o legătură
  către `#dorinta-formular`, iar `:target` deschide formularul; „Renunț" duce
  la `#main` și-l închide. Formularul e un `<form method="post">` adevărat, pe
  care îl primește `index.php` — aceeași funcție pe care o cheamă și
  `api/dorinta.php`. Fără JS se vede o dorință din cele zece, nu zece.
- **În HTML formularul stă ÎNAINTEA tablei**, deși pe ecran apare în același
  loc. Așa, deschis, o poate ascunde cu un selector de frați (`~`), fără nicio
  linie de JS.
- **Când nu e nicio dorință, tabla nu se desenează deloc** — o tablă goală cu
  „încă nimeni n-a scris nimic" ar fi un anunț de pustiu chiar în capul
  paginii. Butonul nu se clatină: el stă în fereastra de bun venit oricum.
- **Dorința cuiva care și-a șters contul dispare de pe tablă**, fiindcă tabla
  cere `membri.stare = 'activ'`, iar contul anonimizat nu mai e. Rândul rămâne
  în bază.

## Cum adaugi un articol

Copiezi un `<article class="card">` din `.grid`. Pentru articolul mare, lat cât
grila, adaugi și clasa `card--wide`.

## Temă light / dark

- La prima vizită se ia automat setarea dispozitivului (`prefers-color-scheme`).
- Dacă utilizatorul apasă butonul din bara de meniu, alegerea lui se salvează în
  `localStorage` (cheia `po-theme`) și are prioritate la vizitele următoare.
- Cât timp nu a ales manual, tema urmărește în timp real schimbările din sistem.
- Scriptul din `<head>` aplică tema înainte de randare, ca să nu apară un flash alb.

Culorile se schimbă dintr-un singur loc, din variabilele de la începutul
`style.css` (`:root` pentru light, `[data-theme="dark"]` pentru dark).

## Layout

- Conținutul e limitat la **1000px** (variabila `--wrap`).
- Bara de meniu și footerul se întind pe toată lățimea ecranului.
- Bara e `sticky`, cu fundal opac constant — nu își schimbă culoarea sau
  opacitatea la scroll.
- Fără sidebar.

## Pagina de articol (`articol.html`)

Conține antet cu autor și dată, thumbnail 16:9, caseta cu detaliile
evenimentului, corpul articolului, butoanele de participare, taburile cu
discuții și articole similare.

### Participare (interesat / particip)

Butoanele au `data-rsvp="interested|going"` și `data-count="<număr din baza de
date>"`. Numărul apare în trei locuri — pe buton, pe tab și în textul din panou —
toate marcate cu `data-count-for="..."` și actualizate simultan din JS.

Regula implementată: cele două stări se exclud. Cine bifează „Voi participa"
este scos automat din „Mă interesează", ca să nu fie numărat de două ori.

Locul unde se leagă serverul e marcat cu `// TODO` în `main.js`.

### Autentificare

Totul depinde de un singur atribut, `<body data-logged-in="false">`:

- **`false`** → click pe participare, like, răspuns sau comentariu afișează un
  mesaj scurt și trimite spre `login.html?redirect=<pagina curentă>`.
- **`true`** → acțiunile se execută normal. Numele și poza celui logat se iau din
  `data-user-name` și `data-user-avatar` (folosite la formularul de răspuns).

Când implementezi login-ul, serverul scrie `data-logged-in="true"` și restul
merge fără modificări în JS.

### Taburi și comentarii

Cele trei taburi (Comentarii / Interesați / Participă) sunt un `tablist`
accesibil — merg și cu săgețile de la tastatură. Comentariile nu mai sunt
șablon: vin din bază, prin `inc/comentarii.php`. Sub-comentariile stau într-un
`<ul class="comment__replies">` care e FRATE cu `<article class="comment__body">`,
nu copil al lui — vezi secțiunea „Comentariile"; formularul de răspuns se
generează din JS la click pe „Răspunde".

## Pagina de contact (`contact.html`)

Antet de pagină, formular cu patru câmpuri (nume și prenume, e-mail, telefon,
mesaj) și coloana cu datele de contact. Datele afișate sunt exemple — le
înlocuiești cu cele reale direct în HTML.

Toate cele patru câmpuri sunt obligatorii.

Verificarea se face în JS, nu prin `required` din browser, ca mesajele să fie
în română și în stilul paginii. Se validează la ieșirea din câmp, iar dacă un
câmp e deja marcat greșit, se recontrolează pe măsură ce scrii. La trimitere,
focusul sare pe primul câmp cu probleme.

Formularul nu trimite nimic încă — afișează doar confirmarea. Locul unde legi
serverul e marcat cu `// TODO` în `main.js`; acolo pui un `fetch` către
endpoint-ul tău (sau `action` + `method` pe `<form>`, dacă preferi fără JS).

## Pagina de cont (`login.html`)

Un singur card, cu taburi de selecție între **Autentificare** și
**Înregistrare**. Accesul cu Google e prezent în ambele, deasupra formularului,
separat printr-o linie „sau".

- **Autentificare:** e-mail, parolă, „ține-mă minte", link de parolă uitată.
- **Înregistrare:** nume, prenume, e-mail, data nașterii, sex, parolă și
  confirmarea ei, plus bifa de acceptare a termenilor.

Ambele câmpuri de parolă au buton de afișare. La înregistrare, sub parolă apare
un indicator de putere (patru trepte, calculate din lungime și varietatea
caracterelor).

### Cum deschizi direct un tab

`login.html#inregistrare` sau `login.html?tab=inregistrare` deschide direct
formularul de înregistrare. Butoanele „Creează unul" / „Autentifică-te" de sub
formulare comută între taburi fără reîncărcarea paginii.

### Întoarcerea după autentificare

Paginile care cer cont trimit spre `login.html?redirect=<cale>`. După
autentificare sau înregistrare, utilizatorul e dus înapoi acolo; dacă
parametrul lipsește, ajunge pe prima pagină.

Din motive de siguranță se acceptă doar căi relative de pe acest site — o
valoare de forma `https://alt-domeniu.ro` sau `//alt-domeniu.ro` e ignorată, ca
parametrul să nu poată fi folosit pentru a trimite utilizatorii pe un site
străin.

### Ce rămâne de legat

Butoanele Google și cele două formulare sunt marcate cu `// TODO` în `main.js`.
Verificările din browser sunt doar pentru comoditatea utilizatorului —
**validarea reală trebuie făcută pe server** (e-mail deja folosit, parolă
corectă, vârstă minimă).

## Pagina Despre (`despre.html`)

Pagină simplă, doar text: antet de pagină, secțiuni cu titluri, o listă, un
citat și o casetă de final către înregistrare și contact. Textul e scris ca
punct de plecare — îl rescrii direct în HTML.

Folosește clasa `.prose`, care e același stil de text lung ca `.post__body` de
la articol. Dacă schimbi tipografia într-un loc, se schimbă în ambele.

## Pagina de profil (`profil.html`)

Antetul profilului (poză, nume, date scurte, nota medie), trei casete cu
activitatea și secțiunea de evaluări.

### Numele

Se afișează prescurtat: inițiala numelui de familie, punct, prenumele întreg —
„Popescu Ionuț" devine **„P. Ionuț"**. Prescurtarea o face serverul, iar numele
complet nu se trimite deloc în pagină, ca să nu poată fi citit din sursă.

### Sexul

Doar simbolul, fără cuvinte: `fact--m` pentru Marte, `fact--f` pentru Venus.
Simbolurile sunt desenate de noi în SVG — caracterele ♂ și ♀ arată altfel de la
un font la altul, uneori chiar ca emoji colorat. Pentru „nespecificat" se scoate
tot elementul `<li>`.

### Stele

Orice element cu `data-stars` devine un grup de cinci stele. Valoarea poate fi
zecimală (`4.6`), fiindcă rândul de stele pline stă peste cel gol și e tăiat pe
lățime — așa se văd și jumătățile de stea.

```html
<div class="rating__stars" data-stars="4.6" data-stars-count="23"></div>
<div class="rating__stars rating__stars--sm" data-stars="5"></div>
```

`data-stars="0"` (sau lipsa notelor) → cinci stele goale, cifra se ascunde și
apare textul **„Fără rating"**. Pentru asta, stelele trebuie să fie într-un
container `.rating` care conține `[data-stars-value]` și `[data-stars-label]`.

Selectorul de note din formular se generează în `[data-stars-input]`; are
`getChosen()` și `reset()`.

### Activitate

Trei casete: evenimente organizate, prezențe și „a confirmat, dar nu a venit".
Numerele vin din HTML — le pune serverul.

### Poza de profil

Sub poză, spre colțul din dreapta-jos, stă un creion mic. **Se tipărește doar
pe profilul propriu** — nu e ascuns din CSS, pur și simplu nu ajunge în pagină
pentru ceilalți. Duce spre `poza.php`.

Vezi mai jos secțiunea „Poza de profil" pentru cum funcționează încărcarea.

## Partea de server (PHP + MySQL)

Din acest punct, site-ul are și cod care rulează pe server. `login.html` a
devenit `login.php`.

### Ce trebuie făcut o singură dată, în XAMPP

1. Pune proiectul în `htdocs`, de exemplu `htdocs/pulsulorasului`.
2. Creează baza rulând `sql/schema.sql` din phpMyAdmin (fila **Import**).
3. Copiază setările și pune-ți datele de acces:

   ```
   copy inc\config.example.php inc\config.php
   ```

`inc/config.php` e trecut în `.gitignore` — datele reale de acces nu ajung
niciodată pe GitHub.

### Fișiere

| Fișier | Rol |
|---|---|
| `login.php` | pagina cu cele două formulare |
| `api/inregistrare.php` | primește formularul, verifică, salvează |
| `confirma.php` | activează contul din linkul primit pe e-mail |
| `inc/validare.php` | verificările, fără atingere de bază de date |
| `inc/bootstrap.php` | setări, legătura cu baza, sesiune, token CSRF |
| `sql/schema.sql` | structura bazei de date |

### Cum ajung datele la server

Formularul nu reîncarcă pagina: JavaScript trimite datele către
`api/inregistrare.php`, iar la reușită formularul dispare și în locul lui apare
mesajul „Verifică-ți e-mailul". Serverul răspunde cu JSON:

```json
{"ok": true,  "email": "ion@email.ro", "mesaj": "..."}
{"ok": false, "erori": {"email": "Există deja un cont cu această adresă."}}
```

Erorile venite de la server se afișează sub câmpul potrivit, exact ca cele
verificate în browser.

### Verificările de pe server

Verificările din browser sunt doar pentru confortul omului — pot fi ocolite cu
un `curl`. Cele care contează sunt în `inc/validare.php`:

- **Nume și prenume** — doar litere latine (cu diacritice românești, maghiare
  sau germane), spații, cratime și apostrofuri. Fără cifre, simboluri, emoji
  sau etichete HTML. Se salvează cu majusculă la fiecare cuvânt: `popescu` →
  `Popescu`, `ana-maria` → `Ana-Maria`.
- **Diacritice** — `ş` și `ţ` cu sedilă sunt aduse la `ș` și `ț` cu virgulă,
  altfel „Şerban" și „Șerban" ar ajunge două persoane diferite în bază.
- **E-mail** — validat și păstrat cu litere mici, ca `Ion@Email.ro` și
  `ion@email.ro` să nu poată deveni două conturi.
- **Data nașterii** — format `AAAA-LL-ZZ`, nu în viitor, cel puțin 13 ani,
  cel mult 120. `2000-02-30` e respinsă (PHP altfel o mută singur pe 2 martie).
- **Sex** — doar `M` sau `F`. A treia opțiune a fost scoasă din formular.
- **Parolă** — minimum 8 caractere, maximum 72 de octeți, pentru că bcrypt
  ignoră tăcut tot ce trece de atât.

Rularea verificărilor: `php teste/test-validare.php` (133 de cazuri).

Patru suite vorbesc cu site-ul prin HTTP, cu cookie-uri adevărate, și cer
serverul pornit: „ține-mă minte" (35 de cazuri), setările, cu tot cu ștergerea
contului și cron (95), formularul de contact (60) și publicarea evenimentelor,
cu tot cu urcarea copertei și secțiunea de pe profil (292).

```
php -S 127.0.0.1:8126 -t . &
php teste/test-tine-minte.php  http://127.0.0.1:8126
php teste/test-setari.php      http://127.0.0.1:8126
php teste/test-contact.php     http://127.0.0.1:8126
php teste/test-evenimente.php  http://127.0.0.1:8126
```

### Unicitatea adresei de e-mail

Se verifică în două locuri, și amândouă sunt necesare:

1. o interogare înainte de salvare, pentru un mesaj de eroare frumos;
2. un index `UNIQUE` în baza de date.

Fără al doilea, două cereri trimise în aceeași clipă ar putea trece amândouă de
verificare și ar crea două conturi cu aceeași adresă.

### Adresa profilului (permalink)

Fiecare membru primește un șir aleatoriu de 10 caractere:
`pulsulorasului.ro/membru/5E6LyyWXyG`.

Nu folosim numele (`membru/p.ionut`) din trei motive:

1. **se repetă** — al doilea „P. Ionuț" ar avea nevoie de `p.ionut-2`, ceea ce
   arată prost și spune tuturor al câtelea e;
2. **profilurile ar putea fi ghicite** — cine vrea o listă de membri o obține
   încercând nume obișnuite; numele îl scurtăm tocmai ca să protejăm persoana,
   iar adresa l-ar da înapoi;
3. **numele se poate schimba**, iar adresa ar rămâne greșită sau ar trebui
   redirecționată.

Alfabetul folosit sare peste `0/O` și `1/l/I`, ca adresa să poată fi dictată la
telefon fără confuzii.

### Parolele

Se păstrează doar hash-ul, produs de `password_hash()` cu algoritmul implicit
(bcrypt). Parola în clar nu ajunge niciodată în baza de date și nu poate fi
recuperată — nici măcar de tine. La autentificare se folosește
`password_verify()`.

### Token-ul de confirmare

În e-mail pleacă token-ul în clar; în baza de date se salvează doar hash-ul
lui, exact ca la parole. Dacă baza ajunge pe mâini străine, linkurile nu pot fi
folosite. Token-ul e valabil 48 de ore și se șterge la prima folosire.

### Fără server de e-mail (XAMPP)

Cât timp `dezvoltare` e `true` în `inc/config.php`:

- linkul de confirmare apare direct în pagină, sub mesajul de succes;
- se scrie și în `private/emailuri-trimise.log`.

Fișierul conține token-uri valabile, deci stă în `private/`, unde `.htaccess`
refuză accesul prin web, **și** se scrie doar în modul dezvoltare. Pe site-ul
public, cu `dezvoltare` pe `false`, fișierul nici nu se creează.

### Alte măsuri

- **Token CSRF** la fiecare trimitere, ca alt site să nu poată trimite
  formulare în numele vizitatorului.
- **Cel mult 5 conturi pe oră** de la aceeași adresă IP.
- **Interogări pregătite** peste tot, cu `ATTR_EMULATE_PREPARES` oprit, deci
  datele nu ating niciodată textul interogării.
- **`.htaccess`** care refuză accesul web la `inc/`, `sql/` și `private/`.

## Autentificarea

### Paginile sunt acum PHP

Meniul trebuie să știe dacă ești conectat, deci nu mai poate fi HTML fix.
Toate paginile s-au mutat pe `.php` și folosesc două fișiere comune:

- `inc/antet.php` — `<head>`, bara de meniu, antetele de siguranță;
- `inc/subsol.php` — footerul și scripturile.

O pagină nouă arată așa:

```php
<?php
$titlu  = 'Titlul paginii';
$pagina = 'contact';            // ce element de meniu e marcat activ
require __DIR__ . '/inc/antet.php';
?>
<main id="main"> … </main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
```

Meniul se schimbă acum într-un singur loc, în `inc/antet.php`.

### Meniul, în funcție de starea de autentificare

| Deslogat | Logat |
|---|---|
| Acasă, Despre, Contact, **Alătură-te și tu** | Acasă, Despre, Contact, **Deloghează-te** |

„Alătură-te și tu" stă după „Contact" și are fundal colorat, ca invitație
principală. Când ești conectat, dispare și în locul ei apare ieșirea din cont,
scrisă discret. Lângă butonul de temă apare numele tău prescurtat.

Tot pentru cei conectați, între nume și butonul de temă stă o rotiță care duce
la `setari.php`. Nu e ascunsă din CSS, ci pur și simplu nu ajunge în pagină
pentru vizitatori — ca și creionul de pe poza de profil.

Rotița și butonul de temă poartă amândouă clasa `.nav__btn`, care ține pătratul
de 34px, chenarul, colțul și starea de hover. Un singur loc de schimbat, ca
cele două să nu se despartă niciodată la aspect. `.theme-toggle` a rămas doar
cu ce e al ei: schimbul dintre soare și lună.

Cu rotița, bara are în dreapta patru lucruri pentru un membru conectat — cerc,
rotiță, temă, hamburger — adică 158px. Sub **360px** nu mai încap și ele, și
numele scris al site-ului, așa că acolo rămâne doar semnul din logo, tot ca
legătură spre prima pagină.

### Ce se întâmplă la autentificare

- **Parolă greșită sau adresă inexistentă** — același mesaj, „E-mail sau parolă
  greșite". Un mesaj diferit ar spune cine are cont pe site.
- **Cont neconfirmat** — formularul e înlocuit cu un panou care explică
  situația și are un buton de retrimitere a e-mailului, o dată la 10 minute.
- **Trei greșeli** — formularul se închide 10 minute, cu o numărătoare inversă
  și un buton „Mi-am uitat parola". (Pagina de recuperare urmează.)

### De ce blocarea ține cont și de adresa IP

Dacă am număra greșelile doar după adresa de e-mail, oricine ar putea închide
contul altcuiva trimițând trei parole greșite. De aceea numărătoarea se face pe
perechea (e-mail + IP), plus o limită mai largă de 15 greșeli pe oră de la
aceeași adresă IP, pentru cine încearcă multe conturi de la același calculator.

### Un singur ceas în toată aplicația

Toate momentele — înregistrare, încercări de autentificare, expirarea
linkurilor — se calculează **în PHP** și se trimit către baza de date ca
parametri obișnuiți. Nicio interogare nu mai folosește `NOW()`.

Motivul e o problemă reală, pe care am avut-o: dacă un moment se scrie cu
`NOW()` (ceasul serverului de baze de date) și se compară apoi cu `time()`
(ceasul PHP), iar cele două au fusuri orare diferite — ceea ce în XAMPP e
obișnuit — toate socotelile de tipul „mai ai 10 minute" ies greșite exact cu
diferența dintre fusuri. La o diferență de o oră, cele 10 minute deveneau 70.

Fusul orar se stabilește o singură dată, în `inc/config.php`:

```php
'fus_orar' => 'Europe/Bucharest',
```

**Dacă ai deja un `inc/config.php`, adaugă linia asta.** Fără ea se folosește
tot `Europe/Bucharest`, dar e mai bine să fie scrisă explicit.

### Măsuri de siguranță

- **Sesiunea se reface la intrare** (`session_regenerate_id`), ca un
  identificator impus dinainte de un atacator să devină inutil.
- **Verificarea parolei rulează întotdeauna**, chiar dacă adresa nu există.
  Altfel, un răspuns instantaneu ar însemna „adresa nu există", iar unul
  întârziat „adresa există" — adică o metodă simplă de a afla cine are cont.
- **Starea contului se citește din baza de date la fiecare cerere**, nu din
  sesiune: un cont suspendat e dat afară imediat, nu la următoarea intrare.
- **Amprentă de browser** legată de sesiune, ca un cookie furat să fie mai
  greu de folosit. Nu include adresa IP, care se schimbă firesc la trecerea
  de pe Wi-Fi pe date mobile.
- **Sesiunea expiră** după 2 ore de inactivitate. Cine a bifat „ține-mă minte"
  nu simte nimic: sesiunea moare, dar e ridicată imediat la loc din amintire
  (mai jos), până la 30 de zile.
- **Hash-ul parolei se reface** la intrare, dacă între timp s-a schimbat
  algoritmul (`password_needs_rehash`).
- **Ieșirea din cont cere token CSRF**, ca alt site să nu poată deconecta
  vizitatorul cu o imagine ascunsă.
- **Antete trimise la fiecare pagină:** `X-Frame-Options: DENY` (împotriva
  clickjacking-ului), `X-Content-Type-Options: nosniff`, `Referrer-Policy`,
  `Permissions-Policy`.
- **Parola primită e limitată la 4096 de octeți**, ca nimeni să nu încarce
  serverul trimițând parole uriașe doar ca să-l pună să calculeze hash-uri.

### „Ține-mă minte" — 30 de zile care chiar țin 30 de zile

Prima încercare a fost cea care pare evidentă: cookie-ul de sesiune primește
dată de expirare peste o lună. Nu ajunge, și merită înțeles de ce.

Cookie-ul spune doar cât timp îl **păstrează browserul**. Conținutul sesiunii
stă pe server, într-un fișier, iar acela e șters de PHP după
`session.gc_maxlifetime` — implicit **24 de minute** de liniște. Pe deasupra,
pe găzduirile partajate fișierele stau într-un dosar comun, unde mătură și
vecinii, după setările lor. Un cookie de 30 de zile care arată spre un fișier
șters de acum trei săptămâni nu conectează pe nimeni.

Deci ce trebuie să dureze 30 de zile nu e sesiunea, ci **dovada că omul s-a
autentificat cândva de pe dispozitivul ăsta**. Dovada stă în tabelul
`sesiuni_amintite` (`sql/006-tine-minte.sql`), iar sesiunea se ridică din ea
ori de câte ori e nevoie. Un rând = un dispozitiv.

**Cookie-ul are două părți, `selector:secret`.** Selectorul spune care rând,
secretul dovedește că e al tău. În baza de date intră doar `sha256` al
secretului — dacă baza ajunge pe mâini străine, rândurile nu deschid nimic,
exact ca la parole și la token-urile de confirmare.

**Se rotește la fiecare folosire.** Secretul se schimbă, selectorul rămâne.
Așa un cookie citit de pe fir sau rămas pe un calculator împrumutat e bun o
singură dată.

**Iar rotația e și un detector de furt.** Dacă apare un cookie cu selector bun
și secret vechi, înseamnă ori un cookie uitat, ori unul furat — nu putem ști
care. Atunci cad **toate** amintirile omului: în cel mai rău caz dăm afară
hoțul, iar stăpânul contului mai tastează o dată parola.

Restul apărărilor:

- **Legat de amprenta browserului**, ca și sesiunea. Cookie-ul mutat pe alt
  browser nu mai e bun de nimic.
- **Cele 30 de zile curg de la autentificare**, nu de la ultima vizită. Cine
  intră zilnic e întrebat de parolă tot o dată pe lună.
- **Ieșirea din cont șterge rândul**, nu doar cookie-ul.
- **Parola nouă dă afară toate dispozitivele.** Cine își schimbă parola o face
  adesea tocmai fiindcă bănuiește pe cineva în cont; altfel intrusul ar rămâne
  conectat 30 de zile fără parola cea nouă. Dispozitivul de pe care se schimbă
  parola e ținut minte din nou, curat.
- **Parola temporară nu primește amintire.** Sesiunea aia trebuie să țină cât
  îi ia omului să-și pună o parolă nouă, nu o lună.
- **Rândurile expirate** se mătură din PHP, la aproximativ una din 50 de
  scrieri, ca și încercările de autentificare.

**La intrarea cu Google e pornit din start.** N-are unde sta bifa — drumul
pleacă spre Google și se întoarce singur — și n-ar avea nici ce alege: conturile
Google n-au parolă la noi, deci singura cale înapoi tot pe la Google trece.

Dacă tabelul lipsește (ai urcat fișierele dar n-ai rulat `sql/006`), site-ul
merge normal: se intră în cont ca înainte, doar că nu se ține minte.

### login.php cât timp ești conectat

Pagina redirecționează spre prima pagină. Deconectarea se face doar apăsând
„Deloghează-te", care duce la `iesire.php` și cere token CSRF.

Așa, un semn de carte, o intrare veche din istoric sau o preîncărcare făcută de
browser nu mai pot da omul afară fără ca el să fi cerut-o.

### Ce se întâmplă cu încercările de autentificare

Rândurile din `incercari_autentificare` **nu rămân definitiv**. Blocarea se uită
doar la ultimele 10 minute, deci cele vechi nu mai folosesc la nimic.

`curataIncercariVechi()` șterge tot ce e mai vechi de **30 de zile**. Se
declanșează din PHP, la aproximativ una din 50 de scrieri — destul de rar cât să
nu încarce nimic, destul de des cât să nu se adune. Nu e nevoie de o sarcină
programată separat, care în XAMPP ar trebui oricum pornită de mână.

Cele 30 de zile sunt și o chestiune de date personale: tabelul ține adrese de
e-mail și adrese IP, pe care nu avem motiv să le păstrăm mai mult decât ne
trebuie ca să vedem un tipar de atacuri.

Dacă preferi ca ștergerea să o facă baza de date singură, în `sql/002` e scris
și evenimentul MySQL echivalent.

## Intrarea cu Google

Fișiere: `inc/google.php` (partea de OAuth), `google.php` (plecarea și
întoarcerea), `finalizare.php` + `api/finalizare-google.php` (ultimul pas la
înregistrare), `inc/buton-google.php` (butonul), `sql/005-google.sql`.

### Ce ai de făcut la Google, pas cu pas

Totul se face o singură dată, pe **console.cloud.google.com**, cu contul tău de
Google. E gratuit pentru ce facem noi.

**1. Fă un proiect.**
Sus, lângă sigla Google Cloud, e un selector de proiect. *New project* → nume:
`PulsulOrasului` → *Create*. Așteaptă câteva secunde și asigură-te că proiectul
nou e cel selectat.

**2. Completează ecranul de acceptare** (*APIs & Services → OAuth consent
screen*). E ce vede omul când apasă butonul.

- *User type*: **External**. „Internal" e doar pentru firme cu Google Workspace.
- *App name*: `PulsulOrasului.Ro` — apare scris pe ecranul de acceptare, deci
  scrie-l cum vrei să fie văzut.
- *User support email*: adresa ta.
- *App domain*: `https://pulsulorasului.ro`, plus linkurile către termeni și
  confidențialitate dacă le ai (nu sunt obligatorii cât timp aplicația e în
  „Testing").
- *Authorized domains*: adaugă **`pulsulorasului.ro`** (doar domeniul, fără
  `https://` și fără `www`).
- *Developer contact information*: adresa ta.

**3. Scopes** — apasă *Add or remove scopes* și bifează doar:
`openid`, `.../auth/userinfo.email`, `.../auth/userinfo.profile`.

Atât. Sunt „non-sensitive", adică **nu ai nevoie de verificare de la Google**.
Dacă ceri mai mult (contacte, calendar, ce-o fi), intri într-un proces de
verificare care durează săptămâni. Noi cerem strictul necesar: cine e și ce
adresă are.

**4. Fă datele de acces** (*APIs & Services → Credentials*):

- *Create credentials* → **OAuth client ID**
- *Application type*: **Web application**
- *Name*: `PulsulOrasului web` (e doar pentru tine, nu se vede nicăieri)
- *Authorized JavaScript origins*: `https://pulsulorasului.ro`
- *Authorized redirect URIs*: **`https://pulsulorasului.ro/google.php`**

Adresa de întoarcere trebuie scrisă **exact** așa: cu `https`, fără `www` dacă
site-ul tău e fără `www`, fără bară la sfârșit. Google compară caracter cu
caracter, iar cea mai frecventă eroare la început — `redirect_uri_mismatch` —
de aici vine.

Dacă folosești și `www.pulsulorasului.ro`, adaugă și
`https://www.pulsulorasului.ro/google.php`.

**5. Copiază** *Client ID* și *Client secret* în `inc/config.php`, la
`google_client_id` și `google_client_secret`. Gata.

**Secretul nu se pune niciodată în JavaScript sau în vreo pagină.** Stă doar în
`inc/config.php`, care nu se vede din web. De aceea nici nu folosim varianta cu
buton desenat de Google — acolo secretul n-ar avea unde să stea în siguranță.

**6. Publică aplicația.** Cât timp e în *Testing*, pot intra doar adresele
trecute manual la *Test users* (cel mult 100). Când ești gata, pe *OAuth consent
screen* apeși *Publish app*. Pentru scopurile astea trei nu ți se cere nicio
verificare — trece imediat.

### Cum merge, în cuvinte simple

1. Omul apasă butonul și e trimis pe google.com.
2. Se autentifică **acolo, la ei**. Noi nu-i vedem niciodată parola — ăsta e tot
   rostul.
3. Google îl trimite înapoi la `google.php` cu un cod scurt, prin bara de adrese.
4. Serverul nostru sună la Google, de la server la server, și schimbă codul pe
   datele omului.

Se cheamă *authorization code flow*. Butonul e o **legătură obișnuită**, nu un
buton de JavaScript: merge și cu JS oprit, se poate deschide în filă nouă, și nu
aducem niciun script străin în pagină.

### De ce atâtea verificări

Pasul 3 trece prin mâinile vizitatorului, deci acolo poate ajunge orice:

- **`state`** — un șir aleatoriu ținut în sesiune și cerut înapoi la
  întoarcere. Fără el, cineva ți-ar putea trimite un link care te conectează în
  contul **lui** fără să-ți dai seama, iar tot ce faci apoi ajunge acolo.
- **PKCE** — spre Google pleacă doar amprenta unui secret de unică folosință;
  secretul întreg rămâne la noi. Dacă cineva fură codul din bara de adrese, nu-i
  folosește la nimic.
- **`state` e bun o singură dată** — se șterge din sesiune imediat ce a fost
  verificat, deci același link nu poate fi refolosit.
- **`aud`, `iss`, `exp`** — verificăm pentru cine e token-ul, cine l-a emis și
  până când e valabil.

Semnătura token-ului **nu** se verifică, și e în regulă: token-ul nu vine prin
browser, ci direct de la Google, printr-o legătură HTTPS al cărei certificat
tocmai l-am verificat. Google însuși scrie în documentație că pentru fluxul ăsta
verificarea semnăturii nu mai e necesară.

### `email_verified` — verificarea care contează cel mai mult

Dacă adresa venită de la Google coincide cu a unui cont existent, cele două se
leagă și omul intră în contul lui de la noi. Asta e în regulă **numai** pentru
că Google ne spune că a verificat el adresa.

Fără verificarea aia, oricine și-ar putea trece în contul lui de Google adresa
altcuiva și ar intra peste el. De aceea, dacă `email_verified` lipsește sau e
`false`, cererea e refuzată.

### Ultimul pas la înregistrare

Google ne dă numele și adresa, dar nu data nașterii și nu sexul — iar pe alea le
arătăm pe pagina de profil. Așa că un om nou e trimis la `finalizare.php`, unde
completează cele două date; **contul se creează abia atunci**. Dacă închide fila
înainte, nu rămâne în urmă un cont pe jumătate.

Contul e activ din prima, fără e-mail de confirmare: confirmarea există ca să
dovedească faptul că omul chiar are cutia poștală aia, iar Google tocmai a
dovedit-o.

### Conturi fără parolă

Cine intră doar cu Google are `parola_hash` **NULL** — nu-i punem un hash
inventat, ca să se poată deosebi „nu are parolă" de „are una pe care n-o știe
nimeni". Dacă vrea să poată intra și cu parolă, o pune prin „Ți-ai uitat parola".

### Dacă nu e configurat

Cât timp `google_client_id` e gol, butoanele și linia „sau cu e-mail" **nu se
tipăresc deloc**, iar `google.php` spune că nu e pornit. Site-ul merge normal
fără ele, deci poți urca tot codul înainte de a-ți face contul la Google.

### Ce nu e făcut încă

Nimic din ce ai cerut. Rămân: formularul de publicat un eveniment și paginile de
categorie.

## Setările contului (`setari.php`)

Se ajunge din rotița din bara de meniu. Patru lucruri de greutăți foarte
diferite, fiecare în cutia lui, cu butonul lui de salvare.

### Parola — două formulare, unul singur în cod

Cine are parolă vede trei câmpuri: cea de acum, cea nouă, încă o dată cea nouă.
Cine a deschis contul cu Google vede doar două: n-are parolă veche, fiindcă
`parola_hash` e `NULL`. Aceea e chiar întrebarea din baza de date — nu a fost
nevoie de nicio coloană nouă ca să știm cine are parolă.

Pentru contul fără parolă, sărirea peste parola veche nu e o portiță: ca să
ajungă în pagină, omul a trecut deja prin Google și e conectat. Cheia contului
lui e contul de Google, iar acela se verifică la fiecare intrare.

Pagina folosește aceleași id-uri ca `parola-noua.php`, deci aceeași bucată din
`main.js` le duce pe amândouă. Lipsa câmpului `#pn-veche` e chiar semnul după
care JS-ul știe să nu-l ceară. Regulile de complexitate sunt cele de la
înregistrare, din `inc/validare.php` — nu există un al doilea set.

Cele douăzeci de rânduri ale unui câmp de parolă cu ochi erau scrise de trei ori
numai în `parola-noua.php`. Au ieșit în `inc/camp-parola.php` și se cheamă acum
de șase ori din două pagini.

### Telefonul

Opțional, cerut abia aici. **Nu se vede pe profil și nicăieri altundeva** — va
folosi organizatorilor de evenimente, când vom lega participanții de evenimente.

Același număr poate fi scris în multe feluri: `0722 33 44 55`,
`+40 722 334 455`, `0040-722-334-455`. Toate ajung în bază la fel,
`0722334455`, ca două scrieri ale aceluiași telefon să nu pară două numere.

### E-mailurile de la noi

Trei bife, toate pornite din start. Coloanele sunt `NOT NULL DEFAULT 1`, deci și
conturile care există deja se trezesc cu ele pornite, fără vreun `UPDATE` de
migrare.

| Bifa | Coloana | Ce oprește |
|---|---|---|
| „…e-mail cu evenimente noi" | `newsletter` | trimiterea nu e făcută încă |
| „…când cineva comentează sau îmi răspunde" | `email_comentarii` | înștiințările din `api/comentarii.php` |
| „…când cineva îmi lasă un feedback scris" | `email_feedback` | înștiințările din `api/evaluare.php` |

**Câte o coloană pentru fiecare, nu una singură,** deși stau în același
formular. `newsletter` înseamnă „trimiteți-mi ce se mai întâmplă prin oraș" —
ceva ce n-am cerut anume. `email_comentarii` înseamnă „spuneți-mi când cineva
îmi răspunde mie", iar `email_feedback` „spuneți-mi când cineva scrie ceva
despre mine". Cine stinge reclamele nu cere prin asta să i se ascundă și
răspunsurile la propriile vorbe; cine stinge zgomotul discuțiilor nu cere să nu
mai afle ce se scrie despre el.

**A treia bifă spune, chiar în rândul ei, ce NU face:** stelele rămân anonime,
deci despre ele nu pleacă niciodată niciun mesaj. O înștiințare la fiecare stea
apăsată ar fi însemnat cinci e-mailuri după o ieșire cu cinci oameni, fiecare
spunând „cineva te-a notat, nu-ți spunem cine, nu-ți spunem cât" — nefolositor
în cel mai bun caz, apăsător în cel mai rău. Iar cu numele în el, mesajul ar fi
spart tocmai anonimatul care ține notele cinstite.

**Cele trei bife stau la distanțe egale**, și asta a cerut o regulă a lor
(`.form--bife`). Cei 18px dintre câmpurile unui formular obișnuit sunt buni
acolo unde fiecare câmp are eticheta lui deasupra și o cutie desenată dedesubt:
marginile spun singure unde se termină unul și începe altul. La bife nu e nimic
de felul ăsta, doar rânduri de text — iar un rând sare la altul, în aceeași
frază, la 20px. Cu 18px *între* bife, rândurile aceleiași fraze ajungeau mai
depărtate decât două alegeri deosebite, și ochiul le citea pe toate ca pe un
bloc. 24px e destul cât să se vadă unde se termină una și începe alta, oricâte
rânduri ar avea fiecare — și tocmai de aceea spațiile *arată* egale, deși
frazele n-au aceeași lungime.

**Un singur buton, totuși:** toate trei răspund la aceeași întrebare și pleacă
într-o singură cerere, cu un singur `UPDATE`. Trei butoane alăturate ar fi pus
omul să apese de trei ori pentru o hotărâre. Ca peste tot, o bifă scoasă nu
ajunge deloc în datele trimise de browser — absența ei **este** răspunsul „nu
vreau".

Mesajul de confirmare **înșiră ce a rămas pornit**, în loc să aibă o frază
pentru fiecare împerechere: cu trei bife ar fi fost opt fraze, iar la a patra
bifă șaisprezece. Așa, o bifă nouă înseamnă un rând nou în tabloul din
`api/setari.php`.

Anonimizarea contului le stinge pe toate trei (`inc/stergere.php`), deși adresa
oricum nu mai e a nimănui.

### Ștergerea contului, cu răgaz de 30 de zile

Zonă roșie, jos de tot, despărțită de restul. Trei pași, dinadins:

1. **Apeși butonul.** Apare al doilea pas, cu parola. Nimeni nu-și șterge contul
   dintr-o singură mișcare greșită.
2. **Scrii parola** (dacă ai una) și pleacă un e-mail. Până aici **nu s-a
   schimbat nimic** în cont. Parola dovedește că ești omul din fața
   calculatorului, e-mailul că ai și cutia poștală — un calculator lăsat deschis
   nu e de ajuns ca să pierzi contul.
3. **Apeși linkul din e-mail.** Abia acum pornește răgazul, iar tu ești dat afară
   din cont.

**Datele rămân neatinse cele 30 de zile.** Numele, poza, tot ce ai scris. Tocmai
ca întoarcerea să aducă totul înapoi exact cum era.

**Anularea nu are buton: e destul să intri în cont.** Verificarea stă în
`autentifica()` din `inc/auth.php`, adică în locul prin care trec toate drumurile
de intrare — parolă, Google, ultimul pas al înregistrării cu Google. Un singur
loc, deci nu poate fi uitat la vreunul dintre ele. Omul vede pe ecran că
ștergerea a fost oprită.

La confirmare se uită și toate dispozitivele ținute minte. Altfel un telefon
rămas conectat ar deschide site-ul singur peste două zile, ar trece prin
`autentifica()` și ar anula ștergerea fără ca omul să fi cerut asta. Anularea
trebuie să fie o faptă, nu un accident.

### După 30 de zile: anonimizare, nu ștergere

Rândul din `membri` **rămâne în baza de date pentru totdeauna**. De el atârnă
evenimentele organizate și participările; un `DELETE` ar lăsa găuri în istoricul
altor oameni. Se golește omul din rând, nu rândul.

Numele devine „Șters Utilizator", adresa primește o valoare unică pe
`@invalid.local` (unică, deci indexul nu se supără; pe un domeniu care nu există,
deci nu poate fi folosită la intrare), telefonul, parola, legătura cu Google și
poza de pe disc dispar, iar starea devine `sters` — pe care `membruCurent()` n-o
primește niciodată.

### Cronul

```
php /home/UTILIZATOR/public_html/cron/anonimizeaza-conturi.php
```

**O dată pe zi e destul.** Ora nu contează: dacă nu rulează o zi, conturile
așteaptă cuminți și se anonimizează a doua zi.

Pentru încercare, fără să schimbe nimic:

```
php cron/anonimizeaza-conturi.php --uscat
```

Scriptul refuză să pornească din browser (`PHP_SAPI !== 'cli'`), iar `cron/.htaccess`
îl blochează și de acolo — două încuietori, fiindcă `.htaccess` nu e citit peste
tot.

Ce s-a făcut se scrie în `private/conturi-anonimizate.log`. **Adresa nu se scrie
în log**: ar însemna să păstrăm exact lucrul pe care omul ne-a cerut să-l
ștergem. Rămâne doar id-ul, cât să putem răspunde dacă cineva întreabă mai
târziu. Rândul de încheiere se scrie doar când chiar a fost ceva de făcut —
altfel un cron zilnic ar umple fișierul cu 365 de rânduri pe an care spun
„n-am avut ce face".


## Formularul de contact

Până acum nu trimitea nimic nicăieri — era marcat cu `TODO`. Acum mesajul se
scrie în `mesaje_contact` **și** pleacă pe e-mail la adresa din
`email_raspuns`. Un e-mail se poate pierde în „Spam", iar un rând în bază nu
sună când ajunge; fiecare acoperă slăbiciunea celuilalt.

Vizitatorii fără cont au voie să scrie — e o pagină de contact, nu una de
membri. De aceea are nevoie de apărare.

### Pentru cine e conectat

Numele, adresa și telefonul vin din cont și sunt blocate în pagină. Blocarea e
doar comoditate: **pe server datele se iau din baza de date**, nu din ce a venit
în formular. Altfel oricine ar putea trimite cererea de-a dreptul și și-ar semna
mesajul cu numele altui membru.

Membrul care n-are telefon în cont îl scrie acum — și îi e cerut, ca oricui: la
un mesaj de contact vrem să putem suna înapoi. **Numărul rămâne doar pe mesaj**,
nu intră în cont: datele din cont se schimbă dintr-un singur loc, din setări,
unde omul le vede pe toate. Un formular care schimbă pe furiș altceva decât
spune e exact felul de surpriză pe care nu-l vrem.

Invitația „Alătură-te și tu" din coloana din dreapta se tipărește doar pentru
vizitatori: unui membru deja înscris i-ar spune să facă ceva ce a făcut demult.

Formularul are un singur câmp „Nume și prenume", dar în bază stau două coloane.
Pentru vizitatori se desparte la primul spațiu, în ordinea de la înregistrare:
numele de familie întâi.

### Capcana pentru roboți

Un câmp în plus, `website`, pe care niciun om nu-l vede. Ascuns prin scoaterea
din ecran, **nu prin `display: none`**: mulți roboți sar peste câmpurile ascunse
așa, tocmai fiindcă e cel mai des folosit truc.

Dacă vine completat, răspunsul e **„ok"**, nu o eroare. Dacă i-am spune că l-am
prins, cine scrie robotul ar afla din prima încercare că există capcana și ar
ocoli-o mâine. Așa, robotul pleacă mulțumit și mesajul nu ajunge nicăieri.
Încercarea se scrie în `private/spam-contact.log`.

Capcana are `aria-hidden` și `tabindex="-1"`, deci nu stă în drumul cititoarelor
de ecran sau al tastaturii: un om orb nu trebuie să pățească nimic din cauza ei.

### Cât de des se poate scrie

- **Vizitatori:** cel mult **5 mesaje pe oră** de la aceeași adresă IP.
- **Membri:** cel mult **unul la 5 minute** — mai larg, fiindcă știm cine sunt.

Limita **nu folosește un sistem nou**. Numără chiar rândurile din
`mesaje_contact`, la fel cum limita de conturi noi numără rândurile din `membri`.
Nu apare o a doua socoteală de ținut, iar limita nu poate rămâne nepotrivită cu
realitatea: dacă rândul există, a fost numărat.

`incercari_autentificare` are și el o limită pe IP, dar nu l-am atins: acolo se
numără greșeli de parolă și se ajunge la blocarea intrării în cont. Cine scrie
de multe ori pe pagina de contact n-are de ce să rămână pe dinafara contului.

Fără reCAPTCHA sau alt serviciu străin — capcana și limitele sunt de ajuns
deocamdată, și nu intră cod din afară în pagină.

### Unde se citesc

În `admin-contact.php`, cu „citit / necitit" (coloana `citit_la`, pregătită de la
bun început pentru asta) și cu un „×" care șterge mesajul. Ștergerea e adevărată:
un mesaj de la formular n-are de cine să atârne, iar spamul care trece de capcană
n-are de ce să rămână în bază pentru totdeauna.


## Evenimentele

`sql/009-evenimente.sql` aduce `categorii` și `evenimente`,
`sql/010-limita-evenimente.sql` coloana prin care se poate ridica, pentru un om
anume, limita de evenimente active, `sql/011-anulare-eveniment.sql` starea
`anulat`, coloana `motiv_anulare` și steagul `membri.este_staff`, iar
`sql/012-oras-eveniment.sql` coloana `oras`, iar
`sql/013-interese-evenimente.sql` tabelul `interese_evenimente`, iar
`sql/014-incheiere-eveniment.sql` starea `incheiat`. Formularul de
publicare e
`adauga_eveniment.php`.

Categoriile erau până acum scrise de mână în trei locuri: filtrele din
`index.php`, eticheta de pe fiecare articol și lista din `despre.php`. De acum
au un singur loc. Sunt cinci: Sport, Cultură, Comunitate, Gastro, Muzică —
ultima apărea doar ca etichetă pe un articol, fără să fie în filtre.

Coloana `ordine` există fiindcă ordinea din bara de filtre e o alegere, nu o
urmare a alfabetului: Sport stă primul pentru că așa a fost gândită pagina.

### Câteva alegeri, explicate

**`cost` e `DECIMAL`, nu `FLOAT`.** În virgulă mobilă binară, 0,10 lei nu se
poate scrie exact, iar greșelile se adună la fiecare adunare. Bani în `FLOAT`
înseamnă, mai devreme sau mai târziu, o factură care nu iese.

**`cost = NULL` înseamnă gratuit, `0.00` înseamnă „am scris eu zero".** Sunt
lucruri diferite la afișare.

**`varsta_minima` e număr, nu `ENUM`.** Se poate compara și sorta
(`WHERE varsta_minima <= 16`), iar dacă mâine apare 21 nu e nevoie de un
`ALTER TABLE`. Ca text, „18" ar fi mai mic decât „6".

**Data și ora stau despărțit.** Se caută mult după zi („ce e sâmbătă?"), iar un
index pe `DATE` e mai simplu decât unul pe `DATETIME` din care trebuie tăiată
ora la fiecare comparație.

**`coperta` ține doar numele aleatoriu**, nu calea — ca la pozele de profil.
Fișierul va trece prin `inc/imagini.php`, deci se redesenează pixel cu pixel și
primește nume random. `imagine_default` de la categorii e altceva: acelea se
urcă de mână, deci numele lor rămâne citibil.

**Ștergerile sunt oprite (`ON DELETE RESTRICT`)** și pentru organizator, și
pentru categorie. Un eveniment care a avut loc rămâne parte din istoricul celor
care au fost la el. Nu e o piedică: contul șters se anonimizează, nu dispare,
deci rândul e mereu acolo.

**Slugul are o coadă întâmplătoare.** Două evenimente pot avea același titlu
(„Târg de Crăciun") în ani diferiți, iar adresa trebuie să rămână unică.

### Butonul de pe prima pagină

În locul linkului „Toate articolele" e acum **„+ Eveniment nou"**. Se vede și
fără cont: cine nu e înscris trebuie să afle că poate publica, nu să descopere
după ce se înregistrează. Fără cont duce la `login.php?redirect=…`, deci după
autentificare omul pică direct pe formular, nu pe prima pagină.

### Formularul de publicare

`adauga_eveniment.php` e pagina, `api/eveniment.php` e punctul de intrare,
`inc/evenimente.php` ține regulile, iar verificările stau unde stau toate
celelalte: în `inc/validare.php` (`verificaEveniment()`).

Pagina e **strict pentru cine e conectat**: cine nu e, e trimis la
`login.php?redirect=…` înainte să se scrie ceva în pagină. Nu e ascundere de
conținut — nelogatul nu primește formularul deloc.

Datele vin ca `multipart/form-data`, nu ca JSON, fiindcă e singurul fel în care
poate urca și un fișier. De aici o capcană care merită scrisă: un formular mai
mare decât `post_max_size` ajunge în PHP **gol**, cu `$_POST` și `$_FILES`
goale și fără nicio eroare. Omul, care completase tot, ar fi primit
„completează câmpurile". Comparăm `CONTENT_LENGTH` cu limita și spunem ce s-a
întâmplat de fapt.

**După trimitere, panoul de „gata" duce la eveniment**, nu pe prima pagină:
„Vezi pagina evenimentului". La editare adresa se știe de când se tipărește
pagina; la un anunț nou nu, fiindcă slugul se naște abia la salvare — de aceea
`salveazaEveniment()` întoarce slugul, `api/eveniment.php` îl trimite înapoi ca
`url`, iar `main.js` umple linkul. Fără el, butonul rămâne ascuns.

Cine are deja un eveniment activ nu primește formularul, ci pagina care-i
spune de ce. Ieșirea de acolo e **„Înapoi"**, adică fix pagina de unde s-a
apăsat „+ Eveniment nou". Saltul îl face `history.back()`, dar numai dacă
`document.referrer` e de pe site-ul nostru: altfel cine ajunge aici dintr-un
link de pe alt site ar fi trimis înapoi acolo. Când nu e, linkul rămâne cum a
venit din HTML și duce pe prima pagină — merge și fără JS.

### Orașul

Site-ul a pornit pentru un singur oraș, Roman, așa că orașul nu se scria
nicăieri: se subînțelegea. Acum se scrie, ca ziua în care apare al doilea oraș
să fie o linie în plus în config, nu o migrare pe un tabel plin de evenimente
despre care nimeni nu mai știe unde au avut loc.

**Lista trăiește în `inc/config.php`**, cheia `orase`:

```php
'orase' => ['Roman'],
```

Un oraș nou înseamnă un rând în plus acolo, atât. **Nu există tabel în bază**
pentru ea — ar fi fost un tabel cu un rând și o pagină de administrare pentru
ceva ce se schimbă o dată pe an.

`oraseDisponibile()` din `inc/bootstrap.php` e singurul loc de unde o citesc și
formularul, și verificarea de pe server; altfel s-ar putea alege în pagină un
oraș pe care serverul îl refuză, sau invers. Tot ea o curăță de valorile goale
și de duplicate, ca o virgulă în plus în config să nu ajungă o opțiune fără
nume în listă.

În formular e o listă, nu text liber, așezată **deasupra locației**. Prima
opțiune e goală și `disabled`, ca la categorie: nimic nu e ales dinainte, nici
măcar când e un singur oraș în listă — omul trebuie să spună el unde are loc,
ca să nu publice din greșeală în alt oraș în ziua în care lista are mai multe.

Pe server, `verificaEveniment()` primește lista ca argument (ca
`$categoriiValide`, și din același motiv: `inc/validare.php` nu deschide nici
baza, nici configul, ca să poată fi probat singur) și cere ca valoarea să fie
**exact** una dintre ele — `in_array` strict, deci „roman" cu literă mică nu
trece. Cine trimite „București" cu mâna lui, ocolind pagina, primește o eroare
de câmp, nu un eveniment strecurat într-un oraș în care nu suntem. Aceeași
verificare rulează și la previzualizare.

Coloana e `VARCHAR(80) NOT NULL DEFAULT 'Roman'`, lângă `locatie`: numele intră
ca text, exact cum e scris în config. Evenimentele de dinaintea migrării au
primit toate `'Roman'` — e adevărul, fiindcă până atunci n-a existat altul.
Dacă mâine un oraș iese din listă, evenimentele lui rămân cu numele scris în
bază; nu se pierde nimic, doar nu se mai poate alege, iar la o editare
organizatorul va fi nevoit să aleagă altul.

Pe pagina evenimentului, orașul stă **înaintea locului, în același rând**:
„Roman · Piața Roman-Vodă". Un rând al lui, cu eticheta „Oraș", ar fi repetat
același cuvânt la fiecare eveniment cât timp orașul e unul singur — iar când
vor fi mai multe, tot lângă adresă e locul lui, fiindcă asta e: prima ei
jumătate.

### Data și orele: câmpuri de text, nu `type="date"` / `type="time"`

Ceasul nativ al browserului se scrie cu AM/PM sau fără **după limba în care e
pus browserul, nu după limba paginii**. `lang="ro"` pe input n-are niciun
efect — verificat în Chromium, cu `lang` și pe element, și pe `<html>`. Un om
cu Chrome în engleză ar fi văzut „07:30 PM" pe un site românesc, lângă o dată
scrisă tot în formatul lui.

Așa că orele sunt `<input type="text" class="camp-ora">`, cu
`inputmode="numeric"` (tastatura de cifre pe telefon) și
`pattern="([01][0-9]|2[0-3]):[0-5][0-9]"` — exact tiparul cerut și de
`verificaEveniment()`. Cele două puncte le pune `main.js`: cât scrie omul nu-l
corectăm (altfel „930" ar sări la „09:3" sub degete), iar la ieșirea din câmp
se îndreaptă — „9" → „09:00", „930" → „09:30", „1930" → „19:30". Se pierde
selectorul nativ de oră; se câștigă faptul că toată lumea vede același lucru.

**Data merge la fel, cu o cifră în plus:** `ZZ-LL-AAAA`, cum se scrie o dată în
România. Câmpul vizibil e text, cu `pattern` și cu aceeași mască.

Calendarul nu s-a pierdut. Lângă câmp stă un `type="date"` adevărat, ascuns și
**fără `name`** (deci nimic din el nu pleacă spre server), pe care butonul din
dreapta îl deschide cu `showPicker()`. Ce se alege acolo se scrie înapoi în
câmpul vizibil, pe românește. Pe telefon rămâne astfel roata nativă de dată.
`showPicker()` cere o apăsare a omului — apăsarea pe buton e; unde nu există,
se încearcă un click pe câmpul ascuns, iar dacă nici acela nu deschide nimic nu
se pierde nimic, fiindcă data se poate scrie oricând de mână. Câmpul ascuns nu
e `display:none`: asta i-ar lua dreptul de a deschide selectorul.

Traducerea între cele două formate se face **într-un singur loc**:
`dataDinFormular()` („25-12-2026" → „2026-12-25") și perechea ei
`dataPentruFormular()`, amândouă în `inc/validare.php`. Formatul e strict — nu
se primește și `AAAA-LL-ZZ` „ca să fim îngăduitori": două formate acceptate
înseamnă că într-o zi cineva trimite „01-02-2026" crezând una și noi înțelegem
alta. `checkdate()` are ultimul cuvânt, fiindcă el știe că februarie are 29 de
zile doar în anii bisecți; un `DateTime` cu „31-04" ar fi alunecat singur pe 1
mai, în loc să spună că data e greșită.

### Masca, într-un singur loc

`mascaCifre(brut, grupe, semn)` din `main.js` e folosită și de dată (`[2,2,4]`,
`-`), și de ore (`[2,2]`, `:`).

Nu lucrează pe poziții absolute, ci pe bucăți, fiindcă omul poate pune și el
semnele. Tăiat la poziții fixe, „5-3-2027" ar fi ieșit „53-20-27" — o dată care
nu există, dintr-una care era limpede. Când o bucată e încheiată de om cu un
semn și are o cifră în loc de două, primește zeroul pe loc: „5-" e ziua 05, iar
„9:30" devine „09:30" în timp ce se scrie. Anul nu se completează niciodată cu
zerouri — „27" nu înseamnă „0027".

Cine scrie doar cifre nu e corectat cât scrie (altfel „930" ar sări la „09:3"
sub degete); îndreptarea vine la ieșirea din câmp.

Bifa **„Nu se știe până când ține" pornește pusă** la un formular gol: ora de
început se știe mereu, cea de sfârșit aproape niciodată. Cine o știe scoate
bifa și scrie ora — o mișcare, în loc de una pe care ar fi trebuit s-o facă
toți ceilalți. La editare urmează ce e în bază, ca toate celelalte bife.

### Un singur eveniment activ

Regula e „un eveniment activ per om", dar nu e scrisă `1` în cod: e citită din
`membri.limita_evenimente_active`, care e `NULL` pentru toată lumea și
înseamnă „regula obișnuită". Când cineva are nevoie de mai multe, se schimbă un
număr în bază. Nu există interfață pentru asta și nici nu trebuie.

**Un eveniment se încheie fără cron.** Fie organizatorul îl marchează așa
(butonul nu e făcut încă), fie trece ziua în care a avut loc — iar a doua se
află comparând data cu ziua de azi *în clipa în care întrebăm*. O sarcină
programată la miezul nopții care ar întoarce un rând în bază poate să nu ruleze,
iar ziua în care n-a rulat ar ține oamenii blocați degeaba. Așa, răspunsul e
corect chiar dacă serverul a stat oprit o săptămână.

Verificarea se face **înainte** de procesarea copertei: n-are rost să
redesenăm o imagine de 1600×900 pentru un eveniment pe care oricum nu-l primim.

### Coperta

Trece prin `inc/imagini.php`, exact pe unde trec și pozele de profil — decodare,
redesenare pixel cu pixel (deci EXIF-ul și orice s-ar fi lipit la coada
fișierului dispar), nume întâmplător. Ca să nu existe două căi paralele,
verificările comune au fost scoase în `deschidePozaPrimita()`, folosită și de
poza de profil, și de copertă.

Diferența e forma: coperta se scrie **16:9, la 1600×900**. Iar dacă imaginea
primită e mai mică de-atât, e **respinsă** și se cere alta — o poză de 800×450
întinsă la dublu arată rău pe orice ecran, și e mai cinstit să spui asta decât
să publici ceva încețoșat. Măsurarea se face **după** rotirea EXIF: un telefon
ținut vertical trimite adesea imaginea culcată, cu orientarea într-o etichetă,
iar altfel am fi respins poze bune.

Calitatea JPEG e mai apăsată decât la poza de profil: `COPERTA_CALITATE = 80`,
față de `POZA_CALITATE = 82`. Coperta e de zece ori mai mare decât un chip de
512 px și se încarcă pe prima pagină de câte ori intră cineva, adesea pe date
mobile; cele două trepte scad fișierul cu vreo 8%, iar la mărire de două ori
deosebirea nu se vede — nici pe cer, care e locul unde pătrățelele apar prima
dată. Poza de profil rămâne unde era: e mică, se încarcă o dată, n-are ce
economisi.

Cadrul îl alege omul: aceeași ramă de mutat și mărit ca la poza de profil, doar
lată în loc de pătrată. Motorul din spate (`faDecupator()` din `main.js`) e
scris o dată și folosit de amândouă — pinch-ul de pe telefon și marginile care
nu lasă colțuri goale sunt exact genul de cod pe care nimeni nu-l mai
corectează în două locuri.

La server pleacă **fișierul original plus trei numere** (colțul din
stânga-sus și lățimea), nu poza tăiată de JavaScript: altfel am salva ce vrea
cel de la tastatură, nu ce am cerut noi. `potrivesteDecupajCoperta()` le aduce
la ceva care încape în poză — numere negative, uriașe, litere sau tablouri nu
sunt un atac, sunt de obicei o fereastră redimensionată, deci nu ne supărăm pe
ele, le potrivim.

Mărirea se oprește acolo unde decupajul ar scădea sub 1600 px lățime: mai
departe am întinde pixeli care nu există. La o poză fix de 1600×900 nu e nimic
de mișcat, iar bara dispare de tot — o unealtă care nu face nimic e mai rea
decât niciuna. Cine trimite totuși un decupaj mai strâns (formular măsluit) îl
primește lărgit înapoi la 1600, nu refuzat.

Coperta e opțională. Fără ea, în bază intră `NULL`, iar la afișare se ia
imaginea implicită a categoriei. Dacă scrierea în bază pică după ce fișierul a
ajuns pe disc, fișierul e șters — altfel ar rămâne acolo pentru totdeauna,
nelegat de nimic.

### Descrierea

Minimum **200** de caractere, **numărate ca litere, nu ca octeți**. În UTF-8,
„ă" ocupă doi octeți, deci `strlen()` ar fi lăsat să treacă un text pe jumătate
scris cu diacritice — cine scrie corect românește ar fi fost avantajat, ceea ce
e o prostie.

Pragul a fost 300, și s-a plâns lumea — pe drept. La o partidă de fotbal în parc
sau la o cafea sâmbătă dimineață, trei sute de caractere înseamnă că trebuie să
*inventezi* ceva ca să treci de contor. Un prag care cere umplutură nu aduce
anunțuri mai bune, aduce anunțuri mai lungi, și îl trimite acasă tocmai pe omul
care avea de spus puțin și limpede. Două sute e cât o vorbă întreagă: unde, ce
se face, ce să-ți iei cu tine.

Numărul stă o singură dată, în `DESCRIERE_MIN`; formularul, contorul din pagină
și mesajele de eroare îl citesc toate de acolo. Server-side se numără cu `mb_strlen()`, iar
contorul din pagină cu `[...text].length`, nu cu `.length`, care numără tot
unități UTF-16.

Paragrafele se păstrează: `curataTextPeRanduri()` normalizează rândurile și
strânge trei sau mai multe rânduri goale la unul singur, dar nu turtește textul
într-un bloc.

**Contorul numără exact ce numără serverul.** Nu e de la sine înțeles, și a
fost un bug adevărat: contorul spunea „300 din 300", iar serverul răspundea
„ai 299". Serverul măsoară textul *după* `curataTextPeRanduri()` — care taie
spațiile de la capete și strânge rândurile goale — iar contorul îl măsura pe
cel brut, așa că un singur rând gol la coadă era de ajuns ca omul să fie
trimis înapoi la un formular care-i spunea că totul e în regulă. În
`assets/js/main.js` stă acum `curataTextPeRanduri()`, oglinda mișcare cu
mișcare a celei din `inc/validare.php` (inclusiv lista de caractere tăiate de
`trim()` din PHP, care nu e aceeași cu a lui `String.trim()` din JS), iar
contorul numără rezultatul ei.

Un emoji simplu (😀) e un caracter de amândouă părțile. Unul lipit din mai
multe bucăți (👨‍👩‍👧‍👦 = patru chipuri și trei U+200D) se numără ca șapte — nu ce
vede ochiul, dar **același număr** aici și acolo, ceea ce era toată problema.
Numerele astea sunt fixate în `teste/test-validare.php`, secțiunea „câte
caractere are descrierea": dacă se schimbă vreunul, s-a rupt oglinda.

Din același motiv, textarea **nu are `maxlength`**: el numără în unități
UTF-16, deci ar fi tăiat un text cu emoji cam la jumătatea limitei ținute de
server. Oprirea la `DESCRIERE_MAX` o face JS, numărând caractere; limitele
ajung la el prin `data-min` / `data-max`, ca să nu existe o a doua copie a
constantelor PHP. **În bază intră textul curat, neescapat.** Escaparea se face la
randare, cu `h()`. Invers — escapat la salvare — ar fi însemnat `&amp;amp;` la
a doua editare și un text pe care nu-l mai poți căuta sau exporta.

### Evenimentele de pe profil

Sub cele trei casete cu statistici, `profil.php` arată ce organizează omul.
Cartonașele sunt **exact cele de pe prima pagină** (`.card`, `.card__media`,
`.card__body`…), ca să nu existe două feluri de a arăta un eveniment.

**Cine ce vede:**

| | aprobate, viitoare | în așteptare |
|---|---|---|
| oricine | da | nu |
| omul însuși, pe profilul lui | da | da, primele |

Ce n-a trecut încă pe la moderare nu se vede din afară. Altfel ar fi de ajuns
să deschizi profilul cuiva ca să citești ce a trimis, înainte ca noi să fi
apucat să ne uităm.

Deasupra titlului, eticheta mică se schimbă după cine se uită: „Ce pui la
cale" pe profilul propriu, „Ce pune la cale" pe al altcuiva. Aceeași condiție
(`$eProfilulMeu`) hotărăște și mesajele de mai jos, când nu e nimic de arătat.

**„+ Eveniment nou" e mereu la îndemână pe profilul propriu.** Înainte apărea
doar în locul gol — adică exact la cine n-avea niciun eveniment, și niciodată
la cine tocmai s-a obișnuit să publice. Acum stă în capul secțiunii când lista
are ceva în ea, și rămâne în invitația din locul gol când n-are: unul singur,
oricum ar fi, fiindcă două butoane care spun același lucru unul sub altul nu
ajută pe nimeni. Pe profilul altcuiva nu apare deloc. Cine are deja un
eveniment activ ajunge pe pagina care-i spune asta, iar „Înapoi" de acolo îl
aduce fix înapoi pe profil.

Cele în așteptare stau primele și poartă eticheta „În așteptare de aprobare",
cu chenar punctat galben și poza mai stinsă — sunt treaba ta, nu a
vizitatorului, și trebuie să se vadă dintr-o privire că nu sunt încă publice.
Galbenul e cel de la „a confirmat, dar nu a venit", ca să nu apară a treia
culoare de avertizare în site.

**„Încheiat" se socotește într-un singur loc.** `filtruNeincheiat()` întoarce
bucata de `WHERE` și valoarea ei împreună, iar de ea se folosesc și limita de
postare, și lista de pe profil. Dacă cele două ar socoti altfel, omul ar fi
blocat de un eveniment pe care nu-l mai vede nicăieri.

Cartonașele duc la `event.php?slug=…` — pagina evenimentului, descrisă mai jos.

### Cifra de sus nu e lungimea listei

Cartonașul „Evenimente organizate", dintre cele trei casete cu statistici,
numără **tot ce a organizat omul și a fost aprobat, de oricând** — și ce
urmează, și ce a fost acum trei ani (`cateEvenimenteOrganizate()`). Lista de
dedesubt arată doar ce nu s-a încheiat încă.

Sunt două lucruri diferite, dinadins: cifra spune cât a făcut cineva pentru
oraș, iar ce a făcut nu se șterge când trece ziua. Un organizator cu douăzeci
de evenimente în urmă și niciunul în față are „20" scris sus și lista goală
dedesubt. De aceea `cateEvenimenteOrganizate()` **nu** folosește
`filtruNeincheiat()`, deși aproape tot restul din `inc/evenimente.php` o face.

Ce așteaptă moderarea sau a fost respins nu intră în cifră: n-a ajuns niciodată
un eveniment adevărat, deci n-are ce căuta într-un număr pe care îl vede toată
lumea.

### Celelalte două casete

Erau numere scrise de mână — „47" și „3", aceleași pe orice profil. Acum sunt
ale omului: `laCateEvenimenteAFost()` și `laCateEvenimenteNuAVenit()`, amândouă
în `inc/evaluari.php`.

**„Prezent la evenimente"** numără evenimentele la care e pe lista de
participanți — și cele active, și cele încheiate. Unul la care merge săptămâna
viitoare se numără la fel ca unul de acum o lună. Nu se numără cele care n-au
ajuns niciodată publice (în așteptare, respinse) și nici cele anulate: la
primele n-avea cum să se înscrie nimeni, la ultimele nimeni n-a fost nicăieri.
Evenimentele pe care le-a ținut chiar el intră și ele — e trecut pe lista de
participanți ca oricare altul, și chiar a fost acolo.

**„A confirmat, dar nu a venit"** numără evenimentele la care organizatorul a
apăsat „Nu s-a prezentat" (`evaluari.automat = 1`). Evenimentele, nu
însemnările: `DISTINCT`, ca cifra să spună „la câte evenimente", nu „câte
rânduri sunt în tabel".

Cele două **se exclud una pe alta**, dinadins: un eveniment la care omul a
confirmat, dar n-a ajuns, iese din prima și intră în a doua. Altfel profilul ar
spune „prezent la 12" și „n-a venit la 3" despre aceleași douăsprezece, iar
cine le citește n-ar ști care e adevărul.

Lista din spatele lor e tabul „Istoric", descris mai jos.

**Primele patru se văd, restul intră ascunse.** Tot ce e de arătat pleacă în
aceeași pagină; peste al patrulea, cartonașele primesc clasa `.ascuns`, iar
butonul „Vezi mai mult… (2)" apare doar dacă are ce descoperi — cu numărul
scris de PHP, nu de JavaScript, ca butonul să nu apară o clipă fără el și să se
corecteze singur sub ochii omului.

Apăsarea lui scoate clasa de pe toate cartonașele ascunse deodată și apoi își
ia rândul cu tot cu el: nu mai are ce descoperi, iar un rând gol în urma lui
n-ar avea ce căuta. Nu se cere nimic de la server, fiindcă nimic nu lipsește —
tot ce se vede era deja în pagină. Se descoperă toate odată, nu încă patru: la
câte evenimente active poate avea cineva, o a doua apăsare ar fi o treaptă
degeaba.

Înainte să dispară butonul, atenția trece pe primul cartonaș descoperit. Cine
merge cu tastatura sau cu cititorul de ecran ar rămâne altfel cu atenția pe un
buton care tocmai s-a evaporat, adică nicăieri.

Când nu e nimic: pe profilul propriu, „Nu organizezi nimic, nu vrei să
încerci?" cu butonul „+ Eveniment nou"; pe al altcuiva, „Ana nu organizează
momentan nimic." — pe primul prenume, cum i-ai spune în față.

### Profilul altcuiva

`profil.php?m=<permalink>` deschide profilul membrului cu adresa aia publică.
Un permalink care nu duce nicăieri — cont șters, suspendat, sau o greșeală de
tastare — nu e o eroare de arătat: pagina trimite omul pe prima pagină.
Adresele frumoase, de forma `/membru/<permalink>`, vin mai târziu.

**Niciun profil nu se mai deschide fără cont** — nici al altcuiva, nici al tău.
Cine nu e conectat e trimis la `login.php` cu adresa de acum în buzunar, prin
aceeași `cereIntrare()` pe care o folosește și `event.php`, așa că după
conectare ajunge fix pe profilul pe care voia să-l vadă. Vezi „Întoarcerea după
intrare", mai jos.

## Pagina unui eveniment

`event.php?slug=<slug>` — fostul `articol.php`, care era doar un șablon cu
text inventat. Șablonul a rămas, textul e acum din bază.

**Slugul, nu id-ul.** Se citește la telefon, spune despre ce e vorba, și nu dă
în vileag câte evenimente are site-ul. Coada lui întâmplătoare face ca nici
ghicitul să nu ducă undeva.

### Cine intră

**Un anunț publicat se vede de oricine, fără cont.**

A fost o vreme închisă și pagina asta, ca profilurile. Dar un anunț public are
altă treabă decât un profil: e făcut ca să fie dat mai departe, pus pe
Facebook, trimis pe WhatsApp. O ușă la intrare l-ar fi oprit tocmai pe cel
căruia i s-a trimis linkul, și l-ar fi ținut și în afara căutărilor Google.

| starea anunțului | organizatorul | alt membru conectat | staff | nelogat |
|---|---|---|---|---|
| aprobat | vede | vede | vede | **vede** |
| în așteptare | vede | prima pagină | prima pagină | prima pagină |
| respins | vede | prima pagină | prima pagină | prima pagină |
| anulat | prima pagină | prima pagină | vede | prima pagină |

**Restricționată e interacțiunea, nu privitul.** Butoanele „Mă interesează" și
„Voi participa" se văd, dar apăsarea duce la `login.php` cu întoarcere pe
eveniment, iar `api/interes.php` cere cont oricum. Pentru un vizitator nu se
scrie nici token CSRF, nici caseta de confirmare: n-are ce face cu ele.

**Un slug inexistent și unul interzis sfârșesc la fel: pe prima pagină.** Dacă
„nu există" ar arăta altfel decât „nu ai voie", oricine ar putea afla, ghicind,
ce evenimente așteaptă la moderare. (Nu e un 404 adevărat, fiindcă site-ul n-are
pagină de 404; redirecționarea ascunde deosebirea la fel de bine.)

De aici încolo `$membru` poate fi null, iar `$membruId` e 0 pentru cine nu e
conectat — un id peste care nu nimerește niciun rând din bază, deci
`interesulMeu()` și celelalte răspund fără să fie nevoie de ocolișuri.

`cereIntrare()` rămâne folosit de restul paginilor închise (profil, setări,
formularul de eveniment); doar `event.php` nu-l mai cheamă la intrare.

Organizatorul vede în plus o bandă cu starea anunțului și butonul „Editează".

### Cartonașul de pe WhatsApp (Open Graph)

Preview-ul care apare când cineva lipește linkul într-o conversație arăta
titlul și puțin text, dar nu și coperta: lipseau meta-tagurile `og:*`.

`inc/antet.php` le scrie acum pentru **toate** paginile, cu valori implicite
luate din `$titlu` și `$descriere` — atât are de spus o pagină obișnuită. Cine
are mai mult le schimbă înainte de `require`: `$ogTitlu`, `$ogDescriere`,
`$ogImagine`, `$ogUrl`, `$ogTip`. `event.php` pune titlul evenimentului,
primele 180 de caractere din descriere (fără etichete) și coperta lui.

**Adresele trebuie să fie întregi.** WhatsApp și Facebook nu se uită la pagină
din browserul omului: o cer ele, de pe alt server, iar o cale de forma
`assets/img/…` n-are acolo față de ce să se socotească. De aceea există
`urlIntreg()` în `inc/bootstrap.php`, care lipește o cale de `url_site` —
și de aceea `url_site` trebuie să fie corect pe producție, altfel cartonașul
rămâne fără poză deși pe site totul arată bine.

Dacă evenimentul n-are copertă, se încearcă imaginea categoriei — dar **numai
dacă fișierul chiar există pe disc** (`urlImagineCategorie()`): coloana
`categorii.imagine_default` există de mult, iar fișierele se urcă de mână, deci
unele lipsesc. O adresă care duce la 404 e mai rea decât niciuna, fiindcă
WhatsApp ar încerca s-o ia și ar arăta un cartonaș ciuntit. Fără poză, `twitter:card` scade de la `summary_large_image` la
`summary`.

### Un eveniment care s-a încheiat

**Două feluri de a se încheia**, socotite amândouă la fiecare citire:

- **i-a trecut ziua** — se întâmplă singur, fără cron;
- **organizatorul a apăsat „Încheie evenimentul"** — `stare_moderare` devine
  `incheiat` (`sql/014-incheiere-eveniment.sql`). E pentru când se termină mai
  devreme: s-au ocupat locurile, s-a stricat vremea la jumătate, s-a strâns
  lumea și nu mai are rost să se înscrie nimeni.

Nu se ascunde și nu se închide — rămâne o pagină bună de citit și de trimis mai
departe, cu tot cu butoanele de distribuire, și se lasă indexată. Se schimbă
doar ce se poate face pe ea.

Regula e aceeași ca la limita de un eveniment activ, prin `evenimentIncheiat()`
din `inc/evenimente.php` — perechea lui `filtruNeincheiat()`, scrisă pentru un
rând în loc de o interogare, și pusă lipită de ea dinadins: dacă una se
schimbă, cealaltă sare în ochi. Altfel un eveniment ar putea arăta „încheiat"
pe pagina lui și ar bloca în același timp postarea altuia.

**`incheiat` nu e o formă de `anulat`.** Anulat înseamnă „nu a mai avut loc" și
se ascunde de toți în afară de staff; încheiat înseamnă „a avut loc, s-a
terminat". De aceea `evenimentPublicat()` le pune la un loc pe `aprobat` și
`incheiat`: două stări, o singură purtare față de lume.

Ce se schimbă la încheiere: butonul dispare, „Editează" la fel (o editare ar fi
întors evenimentul în `in_asteptare` și l-ar fi readus la viață), evenimentul
iese din lista de active — deci organizatorul poate publica altul — și de pe
profil. **Dar se numără mai departe la „Evenimente organizate":** cifra aia
spune ce a făcut omul pentru oraș, iar un eveniment încheiat e chiar dovada că
a făcut. Ar fi fost pe dos să scadă exact în clipa în care a dus treaba la
capăt.

**Se poate încheia doar DUPĂ ce a început** — ziua *și* ora, prin
`evenimentAInceput()`. Spre deosebire de „încheiat", care se socotește pe zile,
aici e nevoie de ceas: un eveniment de azi de la 19:00 n-a început la ora 10
dimineața. Ce nu s-a petrecut încă nu se poate „încheia" — ar apărea pe site ca
și cum ar fi avut loc, deși nimeni n-a fost nicăieri. Ce vrea organizatorul
atunci se cheamă **anulare**, are butonul lui în formularul de editare și cere
un motiv, fiindcă oamenii înscriși trebuie înștiințați; la încheiere n-are
rost, tocmai fiindcă evenimentul a avut loc.

Ceasul e al PHP-ului, ca peste tot. (Proba din browser a căzut prima dată
tocmai fiindcă era scrisă cu `NOW()` din MySQL, care aici e cu trei ore în
urmă — exact capcana din CLAUDE.md, prinsă din nou.)

Butonul „Încheie evenimentul" stă în dreapta iconițelor de distribuire, se vede
doar organizatorului și doar cât mai e ceva de încheiat. Confirmarea e în două
trepte, desenată în pagină ca la anulare — dar nu roșie: încheierea nu e o
pierdere, e un lucru firesc la capătul unui eveniment. După ce merge, pagina se
reîncarcă; asta nu e lene, ci felul în care banda, butoanele stinse și textul
la trecut vin toate de la server, dintr-un singur loc.

Ce se vede: o bandă **cenușie**, nu galbenă și nici roșie. Culorile alea sunt
pentru ce n-a mers bine — un anunț neaprobat, unul respins. Aici n-a greșit
nimeni, doar a trecut ziua.

**Butoanele nu mai cer, ci spun ce numără:** „Cine a fost interesat" și „Cine
a participat". „Mă interesează 12" sub un anunț de acum trei luni sună a
invitație la ceva ce nu se mai poate.

Ce nu se mai poate: butoanele sunt stinse,
caseta de confirmare nici nu se scrie, iar „Fii primul interesat" devine „Nu
s-a înscris nimeni" — o invitație la ceva imposibil e mai rea decât o
constatare. Numerele și chipurile rămân: sunt istoria evenimentului.

Și vorba de sub butoane trece la trecut: „X a fost interesat sau a participat
la acest eveniment", „X și Y au fost interesați sau au participat", „X, Y și
încă N persoane au fost interesate sau au participat". „Sunt interesate de
acest eveniment" sub un anunț de acum trei luni sună ca și cum s-ar mai putea
veni. Că s-a încheiat o spune banda de sus, o dată — aici nu se repetă.

**Oprirea adevărată e pe server.** `api/interes.php` refuză cu „Evenimentul
s-a încheiat.", fiindcă butonul stins e purtare frumoasă, nu regulă: o filă
deschisă alaltăieri, pe când evenimentul era încă în față, arată butoanele vii.
Nici retragerea nu se mai poate — listele unui eveniment trecut sunt istorie,
iar organizatorul care se uită peste ele a doua zi trebuie să vadă ce a fost,
nu ce a mai rămas.

### Distribuirea

Trei iconițe între detaliile evenimentului și „Mergi la acest eveniment?":
Facebook, WhatsApp și copierea linkului. Aceleași desene ca cele scoase
odinioară de lângă numele organizatorului — acolo erau lipite de un om, aici
sunt la locul lor: după ce s-a citit despre ce e vorba și înainte de hotărâre.

Numai la un anunț publicat. N-are rost să dai mai departe ceva ce nu poate
deschide nimeni.

Adresa se scrie **întreagă**, cu `url_site` din config: „event.php?slug=…"
singur n-ar duce nicăieri de pe telefonul altcuiva. Primele două sunt linkuri
obișnuite (`target="_blank" rel="noopener noreferrer"`), deci merg și fără
JavaScript.

Copierea nu poate: are nevoie de `navigator.clipboard`, care există doar pe
pagini sigure (https, sau localhost). Pe http simplu — cum e site-ul în
dezvoltare — pur și simplu nu e, așa că există și calea veche: un câmp de text
ținut în afara ecranului, selectat și copiat cu `document.execCommand`. Scoasă
din uz, dar merge unde cealaltă nu; și e încercată și atunci când Clipboard API
există, dar refuză permisiunea. Confirmarea e dublă: un toast „Link copiat!" și
iconița care se face verde pentru o clipă — un toast singur, jos de tot, se
pierde.

Textul de copiat stă gata scris într-un atribut (`data-copiaza`), escapat cu
`h()`, nu lipit din bucăți în JS: un titlu cu ghilimele sau cu „&" n-are cum
să strice nimic.

### Întoarcerea după intrare

Mecanismul exista deja și se refolosește: pagina trimite omul la
`login.php?redirect=/calea/de/unde/a/plecat`, iar `api/autentificare.php`
răspunde cu acea cale. Nou e doar că valoarea trece acum prin **un singur loc**,
`caleInterna()` din `inc/validare.php`, în locul aceleiași verificări scrise de
trei ori — și că verificarea aia lăsa să treacă exact ce voia să oprească:

- `/\alt-site.ro` începe cu o bară, deci trecea. Browserul îndreaptă bara
  inversă și ajunge la `//alt-site.ro`, adică pe alt domeniu — imediat după ce
  omul s-a conectat la noi.
- un tab sau un rând nou în mijloc e scos de browser *înainte* să se uite la
  adresă, deci `/\ttp://…` nu e ce pare.

Acum se cere o singură bară la început, nicio bară inversă nicăieri, niciun
caracter de control, și o lungime cu capăt. Aceleași reguli sunt și în
`safeRedirect()` din `main.js`, fiindcă valoarea ajunge și în `window.location`.

Paginile nu-și mai scriu singure antetul: `cereIntrare('/calea.php')` din
`inc/auth.php` face redirecționarea și oprește pagina.

**Profilurile cer și ele cont**, prin exact aceeași mișcare:
`cereIntrare('/profil.php?m=<permalink>')`, tot înaintea căutării în bază, deci
un permalink nu se poate încerca din afară nici măcar ca să se afle dacă duce
undeva. Un profil spune vârsta, orașul, chipul și ce pune omul la cale — nu e
ceva ce se lasă la vedere pe internet, unde poate fi cules de oricine.
Linkurile spre profiluri au rămas peste tot cum erau; s-a schimbat doar cine
poate deschide pagina.

**Pagina unui eveniment nu-l mai cheamă la intrare** — vezi „Cine intră", mai
sus. Îl folosesc în continuare profilul, setările, poza și formularul de
eveniment; pe event.php a rămas doar la apăsarea butoanelor de participare,
făcută din JS.

### Ce se arată și ce nu

Ce lipsește nu se arată gol: un rând „Vârstă minimă: —" nu spune nimic, dar
ocupă locul unuia care ar fi spus. Vârsta, participanții și genul apar doar
când sunt completate. Ora de sfârșit lipsă nu se pomenește deloc — scrie doar
„19:00", nu „19:00 — nedeterminat": o mențiune despre ce nu se știe ocupă un
rând ca să nu spună nimic. `cost` gol sau zero devine „Gratuit".

Rândul „Locul" scrie orașul înaintea adresei, despărțite printr-un punct
ridicat: „Roman · Piața Roman-Vodă". Evenimentele de dinaintea coloanei `oras`
n-au ce pune acolo, deci se scrie doar adresa — fără un punct rătăcit la
început. Vezi „Orașul", mai sus.

Descrierea e **escapată la randare, nu la salvare** — se escapează întâi și se
pun etichetele după, altfel `<p>` și `<br>` ar fi escapate și ele, iar omul ar
vedea codul în loc de paragrafe. Rândurile goale despart paragrafe, cele simple
rămân rânduri.

## Previzualizarea, înainte de trimitere

Butonul **„Previzualizează"**, lângă „Trimite spre aprobare", arată cum va
arăta anunțul — cu tot ce e scris în formular chiar atunci, inclusiv ce n-a
fost încă salvat.

### Unde se uită cine vrea să schimbe ceva

| fișier | ce face |
|---|---|
| `inc/afisare-eveniment.php` | **cum arată** un eveniment: antet, copertă, caseta cu detalii, descrierea |
| `api/previzualizare.php` | primește formularul, verifică, pune datele deoparte în sesiune |
| `previzualizare.php` | pagina care se deschide în fila nouă |

`afiseazaEveniment()` e bucata de care atârnă amândouă paginile — și
`event.php`, cu datele din bază, și `previzualizare.php`, cu datele din
formular. **Orice schimbare vizuală se face acolo**, și se vede pe amândouă
deodată; scrisă în două locuri, previzualizarea ar rămâne în urmă la prima
corectură, și tocmai ea trebuie să arate exact ca pagina adevărată.

Funcția nu atinge baza și nu știe nimic despre moderare sau despre cine e
conectat: primește un tablou cu ce e de arătat. Ce diferă între pagini — banda
de sus, butonul „Editează" — se dă din afară, ca argument. Rândul din bază se
traduce în forma aia cu `evenimentDinBaza()`, tot acolo, ca numele coloanelor
să nu se împrăștie prin pagini.

### De ce în doi pași

Un formular obișnuit cu `target="_blank"` ar deschide fila **înainte** să se
știe dacă datele sunt bune, iar erorile ar ajunge acolo, nu pe formular. Or,
tocmai asta trebuie evitat: omul rămâne pe formular și vede ce are de
corectat.

Așa că butonul trimite datele cu `fetch`. Serverul le trece prin
`verificaEveniment()` — **exact funcția de la salvare**, nu o copie mai
îngăduitoare — și răspunde fie cu erorile, fie cu o cheie. Erorile se pun
lângă câmpuri prin aceeași `arataErorile()` pe care o folosește și trimiterea
adevărată; fila se deschide numai când chiar are ce arăta.

Datele stau în sesiune, sub o cheie întâmplătoare, cel mult un sfert de oră și
cel mult trei deodată. Cheia e legată de sesiune: pentru altcineva, aceeași
adresă nu duce nicăieri. Nimic nu se scrie în `evenimente`, iar limita de
evenimente active nici nu se verifică — previzualizarea nu creează nimic.

### Care copertă se arată

Ordinea, și n-are voie să se inverseze:

1. **poza nouă din formular** — și la creare, și la editare. E cea pe care omul
   vrea s-o vadă;
2. la editare, dacă n-a ales alta, **poza salvată** pe eveniment;
3. altfel, **nimic** — nicio figură.

A doua peste prima a fost un bug adevărat: la editarea unui eveniment care avea
deja copertă, cine alegea alta o vedea în previzualizare tot pe cea veche.
Cauza: fișierul nu ajunge la server, deci serverul nu putea vedea singur că s-a
ales unul, și desena ce știa el — poza din bază. Acum formularul i-o spune,
printr-un câmp `coperta_noua`, iar locul pentru poza din browser se face numai
atunci.

Coperta nouă nu se urcă doar ca s-o arătăm: pagina-mamă o desenează pe o pânză
exact cum ar tăia-o serverul — cu numerele din decupator, la 1600×900, cu alb
dedesubt — și o lasă în `localStorage` sub cheia previzualizării. Fila nouă o
ia de acolo și șterge urma.

**Când localStorage nu merge** — navigare privată strânsă, extensii de
confidențialitate, filă redeschisă după ce poza a fost deja luată — în locul
imaginii rămâne o vorbă limpede: „Nu am putut încărca previzualizarea pozei…".
Restul previzualizării (titlu, detalii, descriere) se vede normal; nu se
încearcă alt drum pentru poză și nu se ascunde nimic pe tăcute.

### Ieșirea din previzualizare

La capătul paginii stă **„Închide previzualizarea"**. Fila s-a deschis cu
`window.open` din formular, deci `window.close()` are voie s-o închidă.

Când n-are — filă redeschisă din istoric, adresă lipită de mână — apelul pur
și simplu nu face nimic, fără eroare de prins și fără vreun fel de a ști
dinainte. De aceea nota „Poți închide această filă" apare **imediat după
apăsare**: dacă fila chiar s-a închis, n-o mai citește nimeni; dacă a rămas,
omul află ce are de făcut.

## „Mergi la acest eveniment?"

Două butoane sub anunț — **„Mă interesează"** și **„Voi participa"** — și un
singur rând în `interese_evenimente` pentru fiecare om și fiecare eveniment.
Codul stă în `inc/interese.php`, apăsarea trece prin `api/interes.php`.

Nu două tabele și nici două bife: cele două stări se exclud. Cine spune „voi
participa" nu mai e „interesat", e mai mult de-atât. Cu două bife ar fi fost
posibil un om bifat în amândouă, iar întrebarea „câți vin?" ar fi avut două
răspunsuri.

### Cine hotărăște ce se întâmplă

Spre server pleacă **butonul apăsat**, nu fapta de făcut. El știe starea
adevărată și alege:

| starea de acum | s-a apăsat | ce se întâmplă |
|---|---|---|
| nimic | „mă interesează" | rând nou, `interesat` |
| nimic | „voi participa" | rând nou, `participant` (după confirmare) |
| `interesat` | „voi participa" | **același rând**, stare schimbată |
| `interesat` | „mă interesează" | rândul se șterge |
| `participant` | „mă interesează" | același rând, stare schimbată |
| `participant` | „voi participa" | rândul se șterge |

Retragerea n-are buton al ei: se apasă pe starea în care ești deja. O filă
rămasă deschisă de ieri n-are cum să ne pună să facem altceva decât se cuvine,
fiindcă nu ea hotărăște.

`creat_la` nu se atinge la schimbare — cine s-a arătat interesat acum o lună nu
e același lucru cu cine a intrat aseară. `DELETE`-ul are condiție și pe
`stare`, ca butonul să stingă doar starea pe care o arată.

Scrierea e un singur `INSERT ... ON DUPLICATE KEY UPDATE`, nu „citesc, apoi
scriu": între citire și scriere încape o a doua apăsare, iar atunci una dintre
ele ar fi dat peste indexul unic și ar fi aruncat o eroare în fața omului.

### „Voi participa" trece printr-o treaptă

„Mă interesează" e o însemnare: se trimite din prima. „Voi participa" e o
hotărâre care **dă datele omului mai departe**, deci se deschide întâi o casetă
care spune, înainte și nu după, că numele complet și numărul de telefon vor fi
văzute de organizator, care poate suna sau scrie pe WhatsApp ca să reconfirme.
Tot acolo se cere acordul cu termenii și condițiile.

Confirmarea se verifică **pe server**, nu doar în JS: fără `confirmat`,
`api/interes.php` nu scrie nimic. Altfel cineva ar fi putut ajunge pe lista de
participanți fără să fi văzut vreodată ce dă din el.

**Numărul de telefon** se cere doar cui nu l-a dat încă, printr-un câmp în
aceeași casetă, și se salvează în cont — nu se ține doar pentru evenimentul
ăsta. Trece prin `verificaTelefon()` din `inc/validare.php`, aceeași funcție ca
la setări și la contact, deci „+40 722 33 44 55" ajunge în bază „0722334455".
A doua oară nu se mai întreabă.

Organizatorului nu i se cere niciodată: e numărul lui, n-are cui să și-l dea.

### Locurile

Dacă evenimentul are `participanti_max`, se numără participanții și, la limită,
butonul se stinge cu un rând de lămurire. `participanti_max` gol înseamnă „câți
or veni" — atunci nu se numără nimic și nu se oprește nimeni.

Numărul de pe ecran e o **veste, nu o rezervare**: între încărcarea paginii și
apăsare pot intra alții, de aceea locurile se numără din nou în clipa apăsării.
Butonul stins e un semn, oprirea adevărată e pe server.

Cine e deja înăuntru se poate retrage oricând, chiar dacă evenimentul e plin —
tocmai eliberează un loc. De aceea butonul rămâne apăsabil pentru el.

Organizatorul intră și el în socoteală: un eveniment de zece persoane înseamnă
organizatorul plus nouă.

### Organizatorul vine la ce pune la cale

`salveazaEveniment()` îi scrie rândul de `participant` fără ca el să apese
nimic. Se poate retrage ca oricine altcineva, iar dacă se răzgândește nu i se
cere numărul.

Evenimentele de dinaintea tabelului au primit rândul la migrare
(`INSERT IGNORE ... SELECT`), fără cele anulate — acolo nu mai vine nimeni.

### Rândul de sub butoane

Chipurile și vorba se desenează într-un singur loc,
`randeazaChipuri()`, folosit și de pagină la încărcare, și de
`api/interes.php` după fiecare apăsare. Scrise în două locuri, ar fi început să
difere de la prima corectură.

Se adună **toți** — și interesații, și participanții: dedesubt se spune câți
sunt cu totul, nu cine ce a apăsat. Cel mult cinci chipuri și două nume, luate
la întâmplare, dintr-**o singură** alegere: altfel s-ar fi văzut cinci chipuri
și dedesubt două nume care nu sunt ale niciunuia dintre ele, iar ochiul caută
fără să vrea potrivirea. Numele duc la profiluri; chipurile nu, sunt un grup de
cercuri, nu o listă de legături.

Acordul se face după câți sunt: unul singur („**X** este interesat**ă** de
acest eveniment.", după `sex`), doi, sau doi plus restul. Când nu e nimeni, nu
se arată un cerc gol și un „0 persoane", ci „Fii primul interesat de acest
eveniment!".

### Cine se numără

Peste tot — numere, nume, chipuri, locuri — se numără **doar conturile
active**. Un cont șters se anonimizează, nu dispare din bază (vezi
`inc/stergere.php`), deci rândurile lui rămân; dar omul a plecat de pe site,
n-are ce căuta în „încă 84 de persoane" și n-are de ce să țină un loc ocupat.
Aceeași bucată de SQL peste tot (`INTERESE_DOAR_ACTIVI`), ca numărul de pe
buton, numele de dedesubt și socoteala locurilor să nu spună trei lucruri
diferite.

### Ce nu e făcut încă

Panourile din taburile „Interesați" și „Participanți" sunt tot șablonul vechi,
cu oameni inventați — numerele de pe taburi sunt însă adevărate. Evenimentele
la care merge cineva nu apar pe profilul lui. Nu pleacă niciun e-mail la aceste
apăsări.

**Pagina de termeni și condiții nu există** — linkul din casetă e `href="#"`,
ca toate celelalte trimiteri spre termeni de pe site (înregistrare, subsol).

## Schimbarea unui eveniment

`adauga_eveniment.php?slug=…` — **același formular** ca la publicare, doar
precompletat. Două formulare aproape la fel s-ar despărți la prima corectură,
iar regulile de verificare ar începe să difere între „nou" și „schimbat" —
exact acolo unde n-au voie.

Se ajunge din butonul „Editează" de pe pagina evenimentului, care apare numai
organizatorului. Cine altcineva cere adresa — sau cere un slug care nu duce
nicăieri — e trimis pe prima pagină, la fel ca la `event.php`.

Regula „al cui e" stă într-un singur loc, `evenimentDeEditat()`, fiindcă o cer
două fișiere: pagina cu formularul și punctul de intrare care primește ce s-a
scris. Scrisă de două ori, ar fi de ajuns ca una să rămână în urmă pentru ca
cineva să poată edita evenimentul altuia. Punctul de intrare **nu** se bazează
pe faptul că formularul s-a deschis: cererea poate veni de oriunde, cu orice
slug în ea.

### Editarea se închide când începe evenimentul

Din clipa orei de start, anunțul nu se mai schimbă: butonul „Editează" nu se
mai desenează, pagina de editare trimite înapoi la pagina evenimentului, iar
punctul de intrare răspunde **409**. Ce era de îndreptat se îndrepta înainte —
după ora de start oamenii sunt deja pe drum, iar o schimbare de loc sau de oră
le-ar ajunge sub ochi prea târziu ca să mai folosească cuiva.

Ce rămâne de făcut e chiar pe pagina evenimentului: **„Anulează evenimentul"**
încă o oră (vezi mai jos) și **„Încheie evenimentul"** oricând.

Întrebarea o pune `poateFiEditat()`, și e ALTA decât `poateFiAnulat()`:

| | se stinge la |
|---|---|
| `poateFiEditat()` | ora de start, la minutul zero |
| `poateFiAnulat()` | o oră după ora de start (`MINUTE_ANULARE_DUPA_INCEPUT`) |

De aceea regula **nu** stă în `evenimentDeEditat()`: de acela atârnă și
anularea (`api/anuleaza-eveniment.php` îl cheamă ca să afle al cui e anunțul).
Închisă acolo, ar fi luat înapoi tocmai ora de răgaz care e rostul întreg al
butonului de anulare de pe pagină.

### Cel mai devreme peste două ore

Un eveniment nu poate începe mai devreme de **două ceasuri** de-acum
(`ORE_MINIM_INAINTE`). Data singură se uita doar la ZI: la ora 15:00 se putea
publica liniștit ceva „azi, de la 14:00" — un eveniment deja început în clipa
în care apărea pe site. Verificarea pune data și ora cap la cap și se uită la
clipa de început.

La **editare** regula se cere doar dacă se schimbă chiar clipa de început —
altfel, cine îndreaptă o virgulă cu o oră înainte de start ar fi fost trimis
să-și amâne ieșirea cu două ore. `verificaEveniment()` primește pentru asta un
al cincilea argument: ce scrie acum în bază, sau `null` la un eveniment nou.

## Numerele de telefon din lista de participanți

Le văd **doar organizatorul și staff-ul**. Nimeni altcineva — nici măcar omul
în dreptul numărului lui.

Motivul e simplu: un participant și-a dat numărul organizatorului, nu celor
douăzeci de pe listă. Dacă și-l vede pe al lui acolo, e firesc să creadă că-l
văd și ceilalți pe-al lor, iar data viitoare nu-l mai scrie. Al lui îl are
oricum în setări.

Numai pe lista de **participanți**. La „Interesați" nu se scrie niciodată, nici
pentru organizator: interesatului nu i s-a cerut vreodată numărul — „Mă
interesează" nu e o hotărâre, e o însemnare.

Regula stă într-un singur loc, `poateVedeaTelefoanele()`, fiindcă o cer trei:
`event.php`, `api/interes.php` și `api/exclude-participant.php`. Al doilea e cel
care contează: el redesenează listele pentru **oricine** apasă „Voi participa",
deci un steag uitat acolo ar fi trimis numerele spre toți.

Pentru cine n-are voie, coloana **nici nu se cere din bază** (al treilea
argument al lui `oameniiCuStarea()`). Adusă mereu și ascunsă la desenare, ar fi
fost la un pas de a ajunge în pagină: o funcție nouă care tipărește rândul
întreg, un `var_dump` uitat într-o zi de căutat un bug.

### Ordinea taburilor

„Participă" e înaintea lui „Interesați": cine a spus că vine e vestea, ceilalți
sunt o promisiune. Iar la un eveniment încheiat rămâne oricum numai primul,
deci tot el trebuie să fie cel pe care cade ochiul.

## Zona de administrare

Șase unelte ale casei, adunate sub `admin.php`. O singură intrare în meniu,
„Admin", vizibilă numai pentru staff — șase legături acolo ar fi înecat „Acasă /
Despre / Contact", adică tocmai meniul pentru care intră lumea pe site.

| Secțiune | Ce face |
|---|---|
| Abțibilduri | `coduri.php`, cea de dinainte: coduri noi și starea fiecăruia |
| Evenimente | ce așteaptă o hotărâre, și ce a fost respins |
| Comentarii | ce s-a raportat, și ultimele 50 scrise pe site |
| Contact | mesajele de la formular, cu „citit / necitit" și „×" |
| Useri | caută pe cineva, schimbă-i starea, limita sau șterge-i poza |
| Dorințe | aprobă, respinge sau șterge o dorință |

### Paza se cheamă, nu se scrie

`cerePazaDeStaff()` e prima linie a fiecărei pagini. Nu e o purtare frumoasă, e
regula: cine nu e conectat ajunge la login și se întoarce, cine e conectat dar nu
e de-al casei pleacă pe prima pagină — același răspuns ca la un eveniment pe care
n-are voie să-l vadă, ca să nu afle nimeni din purtarea site-ului ce pagini de
administrare există.

Toate faptele trec printr-un singur punct de intrare, `api/admin.php`, cu
`fapta` care spune care anume. Motivul e tot paza: fiecare faptă cere ACELAȘI
lucru (token bun, cont, om de casă), iar șapte fișiere ar fi însemnat șapte copii
ale aceleiași verificări, dintre care una va fi într-o zi mai îngăduitoare decât
celelalte.

### Tabelele se derulează singure, nu toată pagina

Un tabel de opt coloane nu încape pe un telefon, și nici n-are cum: sunt opt
lucruri de citit în dreptul aceluiași rând. Se derulează pe orizontală, în cutia
lui — asta a fost limpede de la început, și de aceea fiecare tabel stă într-un
`.admin-scroll` cu `overflow-x: auto`.

Numai că **nu era de ajuns.** Cutia chiar prindea derularea, dar tabelul de
dedesubt tot lățea *documentul*, iar pe telefon asta înseamnă că se plimbă toată
pagina în lateral, cu antet cu tot: omul trăgea de tabel și pleca site-ul de sub
degete, lăsând o fâșie goală în dreapta.

Leacul e `contain: paint` pe cutia care se derulează. Spune limpede că ce e
înăuntru se desenează înăuntru și nu iese nicăieri, deci nu mai are de ce să fie
luat în seamă la lățimea paginii. (`overflow-x: clip` pe `body` **nu** rezolvă
nimic aici — l-am încercat și l-am măsurat: documentul rămânea lat. Merită scris,
fiindcă e primul lucru la care sare mintea.)

Alături, `overscroll-behavior-x: contain`: ajuns la capătul tabelului, gestul nu
mai trece mai departe la pagină sau la „înapoi" din browser.

Aceeași pereche de reguli o poartă și `.coduri__scroll`, fiindcă e același fel de
tabel în aceeași cutie prea îngustă.

### Cifrele care se aprind

Fiecare cartonaș arată **câte lucruri așteaptă**, nu câte sunt. Un cartonaș care
ar sta aprins mereu n-ar mai însemna nimic — exact ca un bec de avarie care arde
de trei luni. Când nu e nimic de făcut, scrie „nimic de făcut" și rămâne stins.

Cifrele se cer o dată (`cifreleAdmin()`) și se folosesc în amândouă locurile: pe
cartonașe și în rândul de legături de sus.

### Semnul că i s-a cerut deja o îndreptare

„Respinge, dar cu editare necesară" lasă anunțul **în așteptare** — altfel omul
n-ar mai fi avut ce corecta. Numai că, în lista de administrare, un anunț citit
și trimis înapoi arăta exact ca unul pe care nu-l deschisese nimeni: aceeași
stare, același rând, aceeași zi. Al doilea om de casă îl citea a doua oară,
degeaba.

Acum poartă un semn — *„i s-a cerut o îndreptare"* — iar rândul e stins, fiindcă
mingea e la celălalt: nu e nimic de făcut până nu se atinge omul de el.

**Se stinge la prima editare, oricare ar fi ea.** Nu la una anume: din clipa în
care omul a apăsat „Trimite", partea lui e făcută, iar noi n-avem de unde ști
dacă a îndreptat exact ce i s-a cerut. Asta se vede citind — și de-aia anunțul
trebuie să se întoarcă în listă arătând ca unul necitit.

În bază e `evenimente.corectura_ceruta_la`, un DATETIME și nu un 0/1, ca să se
vadă și **de cât timp** așteaptă. O hotărâre adevărată — aprobat sau respins —
îl șterge la loc: acolo nu mai e nicio corectură de așteptat.

Tot aici, două lucruri mărunte care se simt: „Vezi" deschide anunțul într-o filă
nouă (lista rămâne unde era, cu locul păstrat), iar odată ce anunțul e
**aprobat**, blocul de „Moderare" de pe pagina lui dispare cu totul. După ce
s-a hotărât, un rând de butoane rămas acolo nu mai e o unealtă, ci o apăsare
greșită care așteaptă.

### Ce se poate șterge, și ce nu

Zona asta e plină de butoane care nu se iau înapoi, așa că fiecare are o regulă
scrisă lângă el:

- **Un eveniment se șterge de tot doar dacă e RESPINS.** E singura ștergere
  adevărată de pe site — contul se anonimizează, comentariul se golește, dorința
  rămâne în tabel — și e îngăduită tocmai fiindcă un anunț respins n-a fost
  niciodată public: nu lasă în urmă pe nimeni care să se întrebe unde a dispărut
  ceva. Un anunț aprobat cu doisprezece oameni înscriși **nu** se șterge de aici;
  dacă nu mai are loc, se anulează, și atunci oamenii primesc o veste și un
  motiv. Coloana „ce atârnă de el" e acolo ca să se vadă cât se pierde la o
  apăsare: un „0 · 0" liniștește, un „14 · 9" te face să te uiți încă o dată.
- **Un comentariu cu răspunsuri sub el se golește, nu dispare.** E aceeași faptă
  ca pe pagina evenimentului, prin aceeași funcție — o ștergere scrisă separat
  „pentru staff" ar fi lăsat răspunsurile suspendate în aer.
- **Pe un om de casă nu se umblă din tabelul de useri.** Nu e o pază (cine e
  staff poate oricum orice), e o ferire de apăsarea greșită: doi oameni de casă
  care se suspendă unul pe altul dintr-o listă de două sute de rânduri ar rămâne
  amândoi pe dinafară.
- **„Șters" nu e o stare care se pune de aici.** Ștergerea unui cont înseamnă
  anonimizare; un `UPDATE stare='sters'` ar fi lăsat numele, adresa și poza în
  bază, sub o stare care spune că nu mai sunt.
- **Ștergerea pozei nu șterge omul.** Rândul cădea, o vreme, în aceeași ramură cu
  ștergerile și pleca din tabel — iar la următoarea reîncărcare omul apărea la
  loc, fiindcă nu se ștersese niciodată. Adică lista mințea până la refresh, în
  cel mai neliniștitor fel cu putință: „unde a dispărut omul ăsta?". Acum rândul
  rămâne și se schimbă doar ce s-a schimbat — chipul se întoarce la inițială, iar
  butonul face loc aceleiași liniuțe pe care o are din capul locului cine n-avea
  poză. Adresa chipului implicit vine din răspunsul serverului, prin `urlPoza()`:
  scrisă cu mâna în JS, ar fi fost al doilea loc care știe cum arată inițiala
  cuiva.

### Limita de evenimente: gol nu e zero

În `membri.limita_evenimente_active`, **NULL înseamnă „regula obișnuită"** (adică
`EVENIMENTE_ACTIVE_IMPLICIT`), iar **0 înseamnă „nu mai publică nimic"**. Cele
două arată la fel într-o căsuță și înseamnă lucruri opuse.

De aceea căsuța din tabel rămâne **goală** pentru NULL, cu numărul obișnuit scris
ca îndemn, iar golită la loc scrie NULL înapoi. Un `(int)` peste NULL ar fi
arătat „0" pentru toți cei neatinși vreodată — și la prima salvare minciuna ar fi
devenit adevăr, blocându-i pe toți.

### Dorințele se aprobă, în sfârșit, dintr-o pagină

Până acum se făcea de mână, din phpMyAdmin: o dorință scrisă de om nu se vedea
nicăieri până nu intra cineva în bază să-i schimbe starea, ceea ce însemna că
tabla se umplea numai cât își aducea cineva aminte.

Se arată **toate**, oricâte ar fi — singura listă de administrare fără tăietură.
Ce așteaptă o hotărâre stă în cap, apoi restul de la cele mai noi la cele mai
vechi. Rândurile din `dorinte` nu se șterg niciodată, tocmai ca mai târziu să se
poată spune câte dorințe și-au pus oamenii; tabelul ăsta e singurul loc unde se
vede tot ce s-a scris vreodată, iar o limită ar fi tăiat chiar istoria pentru
care se păstrează rândurile.

Omul află pe e-mail în amândouă cazurile, iar la respingere se poate scrie un
motiv. La aprobare nu se cere niciunul: n-are ce spune.

`publicat_la` **nu** se pune la aprobare: îl scrie `stampileazaCeleAprobate()` la
prima încărcare a primei pagini, tot cu ceasul PHP. Așa, o dorință aprobată de
aici și una aprobată din phpMyAdmin se poartă la fel, iar cele șapte zile de pe
tablă se numără dintr-un singur loc.

Ștergerea unei dorințe e, și ea, adevărată — spre deosebire de restul site-ului,
unde rândurile din `dorinte` nu se șterg niciodată, ca mai târziu să se poată
spune câte dorințe și-au pus oamenii. Butonul e pentru ce n-are ce căuta în
numărătoarea aceea: o înjurătură, un test, o adresă strecurată în text.

### Rapoartele se văd, în sfârșit

Steagul de la capătul rândului de unelte se putea apăsa de mult, dar nimeni
n-avea unde să se uite la ce a ieșit din el. Acum comentariile raportate stau în
capul paginii, cel mai raportat primul.

Numărul de raportări apare **numai aici**: pe pagina evenimentului omul află
doar dacă el însuși a raportat.

**Două butoane, nu unul.** Lângă „Șterge" stă „E în regulă", care șterge
*rapoartele*, nu comentariul. Se raportează și din greșeală, și din răutate — iar
cu un singur buton, singurul fel de a închide un raport nedrept ar fi fost să
ștergi tocmai ce n-avea nimic. Rândul iese din listă, comentariul rămâne pe site
neatins, iar cine a raportat nu află nimic: nici la „șterge", nici la „e în
regulă". Un răspuns la un raport ar fi făcut din steag un fel de a începe o
ceartă.

### Când se șterge ceva al cuiva, omul află

Patru fapte din zona asta ajung la un om anume, și toate patru pleacă pe e-mail:
comentariul șters, poza de profil ștearsă, contul suspendat și hotărârea unei
dorințe. Fără mesaj, omul intra într-o zi pe profil și găsea inițiala în locul
chipului lui, fără nicio lămurire.

Fiecare cere un **motiv, care poate lipsi**. Lăsat gol, mesajul spune limpede că
nu s-a dat niciunul (`paragrafeleMotivului()`, un singur loc pentru amândouă
cazurile) — un mesaj cu un gol în el, în locul explicației, ar fi fost mai rău
decât niciun mesaj.

Întrebarea se pune numai unde are ce spune: `data-motiv` pe butonul care o cere,
iar la lista de stări `data-motiv-pentru="suspendat"` o îngustează la singura
valoare care trimite ceva. La „activ" sau „neconfirmat" nu pleacă niciun e-mail,
deci n-ar avea cui să-i folosească — iar o întrebare pusă degeaba e o întrebare
pe care omul învață s-o închidă fără să citească.

Textul comentariului șters **nu** se pune în mesaj. Dacă a fost șters fiindcă era
urât, retrimiterea lui ar fi fost a doua trecere a aceluiași lucru — de data asta
în cutia poștală a omului, unde rămâne pentru totdeauna.

### În tabelul de useri nu scrie nicio adresă

Nu e nevoie de ea acolo: căutarea o primește oricum, iar dacă tot trebuie scris
cuiva, se scrie de pe pagina lui. Un tabel de cincizeci de rânduri cu adrese e,
în plus, tocmai ce n-ar trebui lăsat deschis pe un ecran. Rămâne telefonul — care
se cere rar și e de folos când chiar se cere — sau o liniuță, fiindcă acolo gol
înseamnă „nu ni l-a dat", și trebuie să se vadă.

Lista e așezată **după ultima logare**, nu după data înscrierii: un cont deschis
acum doi ani, dar folosit ieri, e mai interesant decât unul făcut alaltăieri și
lăsat baltă. Cine n-a intrat niciodată cade la coadă, nu dispare. Se arată cel
mult `ADMIN_USERI` (50) — cine caută pe cineva anume are căutarea de deasupra.

### Ce rămâne în phpMyAdmin

Scris chiar pe pagina de admin, ca să nu caute nimeni degeaba:

- steagul de om al casei (`membri.este_staff`) — nu se dă din nicio pagină,
  dinadins;
- ridicarea unei interdicții de reînscriere (`excluderi_evenimente.interzis`);
- ștergerea unei note date pe nedrept, sau a unui „Nu s-a prezentat" pus din
  greșeală;
- ștergerea unui abțibild deja găsit.

Iar **evenimentele anulate** încă nu se pot șterge din interfață: butonul de
curățenie e doar pentru cele respinse. Rândul unui anunț anulat rămâne în bază,
cu pagina lui publică — vezi `TODO`-ul din `anuleazaEveniment()`.

## Al doilea anunț cu același nume

„Fotbal în seara asta", pus a doua oară de același om, intră în bază drept
**„Fotbal în seara asta #2"**. Al treilea, „#3".

Numărul îl pune site-ul, la scriere (`titluCuNumar()`, chemat din
`salveazaEveniment()`) — nu formularul. Omul scrie titlul pe care îl are în cap;
dacă l-ar fi numărat el, ar fi trebuit să țină minte la ce a rămas.

**În dreptul fiecărui om.** Doi vecini care pun amândoi „Fotbal în seara asta"
scriu despre două seri deosebite; un „#2" pus celui de-al doilea l-ar fi făcut
să pară continuarea unui anunț pe care nu l-a scris.

**Se numără din toate ale lui**, oricare le-ar fi starea — și cele încheiate, și
cele anulate. Tocmai acelea sunt „din trecut": scoase din socoteală, anunțul de
azi ar fi purtat același nume cu unul de pe profilul lui, iar cele două rânduri
n-ar mai fi putut fi deosebite.

Trei amănunte care par mărunte până se lovește cineva de ele:

- **Coada se taie întâi.** Cine scrie el însuși „Fotbal #2" nu cere un al doilea
  anunț numit așa: cere un „Fotbal", iar numărul îl punem noi. Altfel ieșea
  „Fotbal #2 #2".
- **Doar coada.** „Sala #3 la ora 8" rămâne întreg — acolo diezul e parte din ce
  a vrut omul să spună.
- **Numărul următor vine din cel mai mare, nu din câte rânduri sunt.** Dacă „#2"
  se șterge vreodată de mână din phpMyAdmin, următorul rămâne „#4", nu se
  întoarce la „#3" peste unul care există deja.

**Nu se cheamă la editare.** Titlul e deja numerotat, iar o a doua trecere l-ar
fi urcat cu unu la fiecare virgulă îndreptată.

La „Remake" merge de la sine: formularul vine completat cu „Fotbal #2", iar la
salvare iese „Fotbal #3".

## Cifrele din colțul cartonașului

Câți vin („7", sau „7 / 12" când e limită) și câte comentarii sunt — la orice
eveniment, **în afară de vânătorile „FindMe"**, unde participanții nu se scriu
deloc.

Acolo nu se înscrie nimeni: caseta cu „Mă interesează / Voi participa" nici nu
există pe pagina anunțului, deci lista e goală prin însăși alcătuirea jocului. Un
„0" lângă un omuleț n-ar fi spus „încă nu s-a înscris nimeni", ci „nu se duce
nimeni" — și ar fi fost un neadevăr despre singurul fel de eveniment la care
nimeni n-are unde să se ducă. Rămân comentariile, care la o vânătoare chiar spun
ceva: acolo lumea se întreabă unde n-a căutat încă.

**Hotărârea stă în funcția care desenează** (`cifreleCartonasului()`, după
`categorie_joc_qr`), nu în `CIFRE_CARTONAS`. Subcererea aduce mai departe
amândouă cifrele, oricare ar fi evenimentul: e o listă, nu o hotărâre, iar o
bucată de SQL care se uită la ce fel de eveniment aduce ar fi ajuns să fie
scrisă în două feluri.

## „FindMe" — abțibildele cu coduri QR

Un abțibild lipit pe un stâlp, cu un cod QR pe el. Cine îl găsește și îl scanează
primul câștigă, iar evenimentul se încheie pe loc.

### Ordinea, care e tot rostul tabelului

1. Omul de casă face un cod pe `coduri.php`. E un rând singur: cinci semne, fără
   eveniment, fără câștigător.
2. Tipărește abțibildul și îl lipește undeva prin oraș.
3. **Abia pe urmă** publică anunțul, scriind codul în formular. Atunci rândul
   primește `eveniment_id` — vânătoarea a început.
4. Cine găsește abțibildul îl scanează, ajunge pe `findme.php?qr=…`, și dacă e
   primul, câștigă.

Codul trebuie să existe **înaintea** evenimentului — altfel n-ai ce tipări. De
aceea nu e o coloană în `evenimente`: acolo n-ar fi avut cum să existe mai
devreme decât rândul care-l ține.

Și de aceea `eveniment_id` are voie să fie NULL. Începând de la pasul 2,
abțibildul e pe stâlp dar nu duce nicăieri; cine îl scanează atunci trebuie să dea
de un „încă n-a început", nu de o pagină de eroare. Nu e o lipsă — e o treaptă a
jocului.

### Codul

Cinci semne din alfabetul fără perechile care se confundă: fără **O** și **0**, fără
**I**, **L** și **1** — același cu al parolelor temporare. În mod obișnuit nu-l
scrie nimeni de mână, dar când telefonul nu vrea să citească, omul se uită la
abțibild și tastează — și atunci un „0" citit ca „O" strică toată vânătoarea.

Nu e o parolă și nici nu trebuie să fie: cine ghicește un cod nu află unde e lipit
abțibildul, iar ca să câștige tot trebuie să fie primul.

**Codul nu se scrie NICIODATĂ în pagina evenimentului.** Dacă ar apărea acolo,
cine deschide anunțul ar câștiga fără să se ridice de pe scaun — adică exact
opusul jocului. E probat, nu doar spus (vezi `teste/test-findme.php`).

### Care categorie E jocul

`categorii.joc_qr`, niciodată numele sau slugul. Cu un `if ($slug === 'findme')`
prin PHP, o literă greșită în rândul adăugat de mână ar fi stins tot jocul fără să
spună nimeni de ce.

**`joc_qr` și `doar_staff` sunt două lucruri diferite** și nu se confundă: al
doilea spune **cine poate publica**, primul spune **ce fel de eveniment iese**.
Astăzi „FindMe" le are pe amândouă, dar dacă mâine jocul se deschide către toată
lumea, întrebarea „e joc?" trebuie să rămână la fel.

Steagul călătorește cu rândul evenimentului (`categorie_joc_qr`), ca `esteJocQr()`
să poată fi întrebat oriunde a ajuns rândul, fără încă o interogare.

### Formularul de publicare se subțiază

La o vânătoare, jumătate din întrebările formularului n-au răspuns. Nu există
„până la": ora de început **e** capătul, clipa în care căutarea se închide. Nu
se înscrie nimeni, deci nu există „de la câți" și „până la câți". Și nu se vinde
niciun bilet.

| Câmp | eveniment obișnuit | vânătoare |
|---|---|---|
| „Codul de pe abțibild" | — | **apare**, obligatoriu |
| „Ora de sfârșit" + bifa ei | da | **pleacă** |
| tot chenarul „Cine poate veni și cât costă?" | da | **pleacă** |
| titlul „Când o să aibă loc?" | așa | **„Data și ora limită"** |
| „Ora de început" | așa | **„Ora"** |

**De ce se ascund, în loc să fie lămurite.** Un om de casă nou nu citește notele
de subsol, dar completează orice câmp îi stă în față — și îl completează greșit,
fiindcă întrebarea nu se potrivește cu ce publică. Un formular mai scurt e mai
greu de greșit decât unul lung cu explicații.

**Vorba se schimbă unde ar minți.** „Ora de început" la un concert e ora la care
se începe; la o vânătoare e ora la care căutarea se **termină**. Cine citește
„început" scrie exact pe dos.

Trei atribute fac toată treaba, iar `main.js` le ascultă dintr-un singur loc:
`data-fara-joc` (pleacă și se întoarce), `data-vorba-joc` (altă vorbă) și
`data-vorba` (bucata care se schimbă, ca steluța de „obligatoriu" să rămână pe
loc). Ce e ascuns **se și stinge** (`disabled`), ca un cost scris înainte de
răzgândire să nu plece pe furiș odată cu anunțul — aceeași regulă ca la codul de
abțibild.

Serverul nu se bizuie pe asta. `verificaEveniment()` întreabă o dată dacă e o
vânătoare — după **categorie**, nu după ce lipsește din cerere — și trece ora de
sfârșit, costul și numărul de participanți drept nespecificate. Altfel oricine ar
fi scăpat de reguli trimițând un formular ciuntit, iar noi am fi numit asta „a
ales să nu completeze". Iar fără scutirile astea, un anunț de vânătoare ar fi
fost oprit cu trei erori care arată spre bife pe care omul nu le mai vede — cea
mai rea formă de refuz.

Fără JS rămâne totul vizibil, cu vorbele obișnuite. E supărător, nu stricăcios.

### Pagina evenimentului arată altfel

| | eveniment obișnuit | vânătoare |
|---|---|---|
| „Ce zici, te interesează?" | da | **nu** — în locul ei, caseta vânătorii |
| taburile „Participă" / „Interesați" | da | **nu** |
| tabul „Comentarii" | da | da |

La o vânătoare nu se strânge nimeni: fiecare caută singur prin oraș, iar singurul
lucru care contează e cine ajunge primul la abțibild. O listă de participanți la
așa ceva ar fi fost o listă de oameni care au apăsat un buton. Rămân comentariile
— acolo lumea se întreabă unde n-a căutat încă.

Caseta vânătorii are două chipuri: **numărătoarea inversă** până la termen, sau
**câștigătorul**, cu poza mare și cu legătură spre profilul lui. Sub cifre stă
mereu și clipa scrisă în litere: dacă JS-ul nu pornește, omul tot află până când
are de căutat. Cifrele sunt doar mai vii.

Numărătoarea se scrie cu vorbe — „4 zile, 5 ore, 33 min și 21 sec" — iar
**bucățile goale de la început cad**, una câte una: „5 ore, 33 min și 21 sec",
apoi „33 min și 21 sec", iar în ultimul minut doar „32 secunde". Un „0 zile, 0
ore" scris în față ar fi împins secundele — singurul lucru care se mai mișcă — în
coada rândului. Cad numai cele de la început: „2 ore, 0 min și 5 sec" rămâne
întreg, fiindcă acolo zeroul chiar spune ceva.

### Termenul

„Când o să aibă loc?" înseamnă, la o vânătoare, **clipa în care se închide** — nu
ora la care se strânge lumea. După ea, cine scanează află că vânătoarea s-a
terminat fără câștigător, și tot i se cere să dezlipească abțibildul. Ceasul e al
PHP-ului, ca peste tot pe site.

### `findme.php` schimbă starea printr-un GET

E singura pagină de pe site care face asta, și e dinadins.

Un scaner de coduri QR deschide o adresă. Nu poate trimite un POST, nu poate
purta un token CSRF. Dacă am fi cerut o apăsare („Revendică abțibildul"), am fi
pus un drum în plus exact în clipa în care omul stă în stradă, cu telefonul în
mână, lângă un stâlp.

Iar ce s-ar putea face cu o cerere pusă la cale de altcineva e să **câștigi** un
abțibild. Nu se pierde nimic, nu se șterge nimic, nu se dă nimic altcuiva. E
singurul loc de pe site unde asta e adevărat — și tocmai de aceea e singurul care
are voie să lucreze pe GET.

Pagina nu se indexează: o adresă de-asta ajunsă în rezultatele căutării ar face
jocul de prisos.

### Cine e primul se hotărăște în `WHERE`

Doi oameni care scanează același abțibild în aceeași secundă trec amândoi de
orice verificare de dinainte. `gasit_de IS NULL` din `UPDATE` îl lasă să treacă pe
unul singur; al doilea primește false și vede „l-a găsit altcineva" — ceea ce e
adevărat.

Același tipar la legarea codului de eveniment (`eveniment_id IS NULL`):
`deCeNuSePoateLega()` există doar pentru **vorba** din formular, nu pentru
siguranță.

Iar câștigul și încheierea evenimentului merg **împreună**, sub aceeași tranzacție.
Dacă a doua ar pica singură, ar rămâne un anunț cu numărătoarea inversă mergând
peste un abțibild deja găsit.

### Nelogatul

I se spune că l-a găsit și că trebuie să intre în cont ca să-l revendice, cu
întoarcere înapoi la `findme.php?qr=…`. **Nu i se ține deoparte**: cât e la login,
altcineva i-o poate lua înainte. De aceea butonul zice „Intră în cont", nu
„Revendică".

### `coduri.php` — prima pagină de administrare de pe site

Restul lucrurilor de casă se fac din phpMyAdmin (aprobarea unei dorințe, steagul
de staff, ridicarea unei interdicții). Asta n-avea cum: un cod de cinci semne
ales de mână s-ar fi lovit într-o zi de altul, iar cheia unică ar fi respins
abțibildul **după** ce era deja tipărit și lipit pe stâlp.

Merge și fără JS: e un formular obișnuit, cu Post/Redirect/Get, fiindcă pagina se
deschide în stradă, de pe telefon, adesea pe o rețea proastă.

Butonul spre ea stă în antet, lângă rotiță, numai pentru staff — și trebuie să
existe, fiindcă jocul cere ca întâi să faci codul și abia pe urmă să publici
anunțul: pagina se cere înaintea formularului, deci n-are cum s-o dea formularul.

Pagina **nu desenează codul QR** — dă adresa întreagă care intră în el. Imaginea
o face omul cu unealta lui, și o lipește în pătratul alb din mijlocul
abțibildului.

### Ce rămâne de făcut

- Codul QR nu se desenează pe site; adresa se copiază și se dă unei unelte din
  afară.
- Un abțibild găsit nu se poate desface din interfață (nici de staff). Rândul se
  schimbă de mână, din phpMyAdmin.
- Nu pleacă niciun e-mail când cineva câștigă: află pe loc, pe `findme.php`.
- Un cod desprins de eveniment se poate lega de altul, dar nu se refolosește
  după ce a fost găsit — atunci rândul rămâne pentru totdeauna.

### Ștergerea unui cod

„×"-ul din capătul rândului, pe `coduri.php`. Cere o încuviințare, fiindcă
abțibildul e deja lipit undeva prin oraș și codul nu se mai poate face la loc: o
apăsare din greșeală înseamnă o hârtie care nu mai duce nicăieri.

**Unul găsit nu se șterge** (`poateFiStersCodul`). Rândul acela nu mai e o
unealtă, e istoria cuiva: de el atârnă cifra „Coduri QR găsite" de pe profilul
câștigătorului, iar omul de casă care face curat prin listă n-are de unde să
știe că apăsând un „×" scade cu unu ceva de pe pagina altcuiva. Aceeași regulă ca
peste tot pe site: ce a fost nu se șterge — contul se anonimizează, comentariul
se golește, dorința rămâne în tabel.

Pentru rândurile acelea nu se desenează un buton stins, ci nu se desenează
nimic: un buton care nu face nimic e o întrebare fără răspuns. În locul lui stă
o liniuță, cu explicația în `title`.

`gasit_de IS NULL` stă în `WHERE`-ul ștergerii, nu doar în întrebarea de
dinainte: între apăsarea „×"-ului și fapta propriu-zisă încape scanarea care
tocmai a câștigat, iar pagina din fața omului nu știe de ea.

## Categorii ținute pentru casă

Coloana `categorii.doar_staff` (vezi `sql/024-categorii-doar-staff.sql`). Prima
astfel de categorie e **„FindMe"**: jocul cu coduri QR ascunse prin oraș, pe
care oamenii le caută și le scanează. Evenimentele lui nu le propune nimeni —
le pune casa.

`doar_staff` spune **cine poate publica** acolo, și atât. Nu cine o vede, nu
cine caută prin ea — și nici ce fel de eveniment iese: aia e treaba lui
`joc_qr`, un steag cu totul separat (vezi secțiunea „FindMe" de mai sus). Cele
două cad astăzi peste aceeași categorie, dar nu se citesc niciodată una prin
alta.

| | `doar_staff = 0` | `doar_staff = 1` |
|---|---|---|
| se poate publica în ea | toată lumea | **doar staff** |
| în formularul de publicare | toată lumea | **doar staff** |
| în filtrele de pe prima pagină | da | **da, la fel** |
| evenimentele din ea | se văd | se văd la fel |

Formularul de publicare e **singurul** loc din care lipsește, și lipsește
tocmai fiindcă e locul unde se alege unde publici. Peste tot altundeva e o
categorie ca oricare: are cip pe prima pagină, se filtrează după ea,
evenimentele ei au pagina lor și se pot da mai departe. Altfel n-ar avea cine
să caute codurile.

Două funcții, două liste:

- `categoriiEvenimente()` — lista **din care se alege la publicare**. Întoarce
  implicit doar categoriile obișnuite: așa, o funcție nouă care uită să ceară
  lista întreagă arată prea puțin, nu prea mult. De `idCategoriiValide()`, care
  iese din ea, atârnă și cine **poate** publica acolo — nu e de ajuns că lista
  din formular n-o arată, fiindcă numărul se poate scrie de mână în cerere.
- `categoriiCuEvenimente()` — cipurile de pe prima pagină. **Fără nicio
  deosebire după `doar_staff`**, doar după „are măcar un eveniment public".

`categoriaCeruta()` cere anume lista întreagă (`categoriiEvenimente(true)`): el
verifică slugul venit din adresă, iar cu lista strâmtă `?categorie=findme` ar fi
însemnat „toate" — omul apăsa cipul și primea tot.

Rândul se adaugă de mână, din phpMyAdmin:

```sql
INSERT INTO categorii (nume, slug, ordine, doar_staff)
     VALUES ('FindMe', 'findme', 99, 1);
```

## „Coduri QR găsite" — al patrulea cartonaș de pe profil

Lângă „Ieșiri organizate", „Prezent la activități" și „A confirmat, dar nu a
venit". Câte abțibilde „FindMe" a găsit omul prin oraș.

Cartonașul a stat o vreme pe zero, dinadins: a fost pus în rând **înainte** să
existe jocul, fiindcă adăugat mai târziu ar fi rearanjat un rând pe care oamenii
se obișnuiseră să-l citească. `cateCoduriQrGasite()` întorcea atunci zero
de-a dreptul; astăzi numără din `coduri_qr`, și n-a fost nimic de căutat prin
`profil.php` — un singur loc de schimbat, exact cum fusese gândit.

Funcția stă mai departe în `inc/evaluari.php`, deși fraza e scrisă în
`inc/coduri-qr.php`, lângă tabelul ei: `profil.php` cere toate cele patru cifre
din același fișier, iar dacă a cincea ar veni de altundeva, pagina ar trebui să
știe din care fișier vine fiecare.

## „Remake": încă unul la fel

Pe dos față de „Editează": butonul apare abia **după ce evenimentul s-a
încheiat sau s-a anulat**, și numai organizatorului. Alergarea de duminică se
face și duminica viitoare; cea căzută din cauza ploii se mută pe altă zi.

Duce la `adauga_eveniment.php?remake=<slug>` — **alt parametru decât `slug=`**,
dinadins: acela înseamnă „schimbă rândul ăsta", ăsta înseamnă „scrie unul nou,
pornind de la el". Cu același nume, o greșeală de o literă ar fi rescris
anunțul vechi în loc să facă unul nou, și nimeni n-ar fi văzut până a doua zi.

Ce se aduce, și ce nu:

| | |
|---|---|
| titlu, categorie, oraș, unde are loc | **da** |
| poza de copertă | **da**, dacă există |
| „Cine poate veni și cât costă?" (cost, vârstă, gen, minim/maxim) | **da**, cu bifele lor |
| descrierea | **da** |
| „Când o să aibă loc?" (dată, oră de început, oră de sfârșit) | **nu** — ăsta e singurul lucru care chiar se schimbă |

Anunțul vechi **nu se atinge**: se naște unul nou, care intră la moderare ca
oricare altul și căruia i se aplică limita de evenimente active.

### Cum ajunge poza dincolo

Toate celelalte câmpuri vin completate în pagină și pleacă de acolo, ca la
orice eveniment nou. Poza însă stă pe disc, iar formularul n-are cum s-o
trimită înapoi fără s-o ceară omului din nou — de aceea slugul de refăcut
călătorește într-un câmp ascuns, și de aceea e singurul lucru pentru care e
nevoie de el la salvare.

Fișierul se **copiază** (`copiazaCoperta()`), nu se împarte. Două anunțuri care
ar arăta spre aceeași poză ar fi rămas amândouă fără ea la prima ștergere — iar
curățenia evenimentelor anulate, care încă nu există, tocmai asta va face
într-o zi.

E singurul loc de pe site unde un fișier de imagine ajunge pe disc fără să
treacă prin GD. Nu e o portiță: fișierul de plecare nu vine de la nimeni, e
unul pe care l-am scris chiar noi, pixel cu pixel, la prima încărcare — ce ar
fi putut fi ascuns în el a rămas afară atunci, o dată pentru totdeauna.

Slugul din formular nu e o dovadă: `evenimentDeRefacut()` întreabă din nou al
cui e anunțul și dacă chiar s-a încheiat. Dacă nu, anunțul nou se face oricum,
doar fără poză — „evenimentul din care copiezi nu mai e al tău" nu e ceva ce
omul poate îndrepta la mijlocul unei publicări.

### Ce se schimbă la salvare, și ce nu

**Starea se întoarce mereu la „în așteptare"**, oricare ar fi fost — aprobat,
în așteptare sau respins. Altfel s-ar putea publica orice: trimiți un anunț
cumsecade, îl aprobăm, iar a doua zi îi schimbi tot conținutul fără să mai
treacă pe la nimeni. (Un anunț respins se poate corecta la fel de bine: e chiar
cel care are cea mai mare nevoie.)

**Slugul nu se schimbă**, nici dacă se schimbă titlul: adresa poate fi deja
dată mai departe, iar un link stricat supără mai tare decât un slug care nu mai
seamănă cu titlul.

**Poza rămâne dacă nu alegi alta.** Un formular trimis fără fișier înseamnă
„n-am umblat la poză", nu „șterge-o" — coloana nici nu e atinsă. Cine alege
alta o vede pe cea veche dându-se la o parte; dacă se răzgândește, se întoarce.
Fișierul vechi se șterge de pe disc abia după ce rândul s-a schimbat cu bine.

**Limita de evenimente active nu se aplică la schimbare.** Ar fi chiar
evenimentul care se editează: omul cu un singur eveniment activ ar fi oprit
tocmai de el, deci n-ar mai putea corecta niciodată nimic.

**Verificările sunt aceleași.** Descrierea tot de `DESCRIERE_MIN` caractere,
titlul tot de minimum opt — la editare nu se cere mai puțin, altfel s-ar putea publica un
anunț bun și „edita" până rămâne gol.

### Anularea

Butonul **„Anulează evenimentul"**, în zona roșie de sub formular, apare doar
la editare — la un eveniment care încă nu există n-are ce anula.

Confirmarea e **desenată de noi, în pagină**, nu cu `window.confirm()`: o
fereastră a browserului arată altfel pe Windows, pe Android și pe iPhone, iar
noi vrem aceeași interfață peste tot. Același tipar ca la ștergerea contului
din setări — butonul își schimbă locul cu întrebarea, care numește evenimentul
și spune că anunțul iese de pe site și că oamenii interesați vor fi înștiințați
prin e-mail. Atenția pleacă pe „Renunță", nu pe „Da, anulează": cine apasă
Enter din obișnuință n-are voie să anuleze din greșeală.

#### Motivul e obligatoriu

Împreună cu întrebarea apare o casetă: **de ce anulezi?** Minimum
`MOTIV_ANULARE_MIN` caractere (15), cel mult 1000, verificate pe server cu
`verificaMotivAnulare()` din `inc/validare.php` și numărate cu aceeași
numărătoare ca descrierea, deci contorul din pagină spune fix ce spune
serverul.

Nu e o formalitate: textul ăsta va pleca prin e-mail spre toți cei care voiau
să vină. „Anulat" singur nu e o veste, e o ușă închisă în nas.

Caseta stă înăuntrul formularului de eveniment, dar **fără `name` și fără
`required`** — cu ele ar fi plecat odată cu „Trimite spre aprobare" și ar fi
blocat trimiterea cât e goală. JS o citește după id și o trimite el, la
anulare. Eroarea vine înapoi pe câmp, ca la orice alt câmp, nu într-un toast
care se stinge singur.

#### Nu se șterge nimic

**Rândul rămâne, cu o stare nouă.** Prima variantă ștergea rândul; era greșit.
De un eveniment atârnă oameni care și-au făcut planuri, iar un rând șters nu
mai poate spune nimănui de ce nu mai au unde să se ducă — nici acum, nici la
sfârșitul lunii, când cineva întreabă ce s-a întâmplat.

Așa că `stare_moderare` devine `anulat` (valoare adusă de
`sql/011-anulare-eveniment.sql`, pusă **la coada** ENUM-ului, fiindcă MySQL
ține un ENUM ca numărul poziției — o valoare strecurată la mijloc ar fi
prefăcut în tăcere fiecare „respins" în altceva), iar `motiv_anulare` ia textul
organizatorului, neescapat, ca toate textele din site.

„Anulat" e altceva decât „respins": respins înseamnă „noi n-am primit-o",
anulat înseamnă „organizatorul s-a răzgândit". Pentru public amândouă sunt
invizibile, dar în bază sunt două povești diferite.

Coperta **nu** se șterge de pe disc. Cât timp rândul e acolo, poza face parte
din el; se duce odată cu el, la curățenie.

#### Cine mai vede un eveniment anulat

| | pagina evenimentului | profil, prima pagină | formularul de editare |
|---|---|---|---|
| nelogat | nu (nici nu ajunge) | nu | nu |
| oricine e conectat | nu | nu | nu |
| organizatorul | **nu** | nu | **nu** |
| staff | da | nu | nu |

Nici măcar organizatorul: a spus ce avea de spus și a închis subiectul, iar o
pagină care se mai deschide pentru el ar fi o promisiune că se mai poate face
ceva. Editarea e închisă din același motiv, dar și pentru unul mecanic — o
editare ar fi întors evenimentul în `in_asteptare` (așa face
`actualizeazaEveniment()`) și l-ar fi readus la viață pe lângă anularea pe care
tocmai o anunțase. Regula stă în `evenimentDeEditat()`.

Un eveniment anulat **nu mai ține pe nimeni blocat**: iese din
`evenimenteActive()`, deci organizatorul poate publica altul imediat. Nu se
numără nici la „Evenimente organizate", și nu apare în nicio listă.

**Staff** înseamnă `membri.este_staff = 1`, citit la fiecare cerere prin
`esteStaff()` din `inc/auth.php` — ca starea contului, fiindcă un drept luat
înapoi trebuie să dispară pe loc, nu la următoarea conectare. Nu există
interfață prin care cineva să fie făcut staff; se pune de mână, ca limita de
evenimente. Pe pagina lor, evenimentul poartă banda `stare-anunt--anulat` și,
sub ea, motivul scris de organizator, citat întocmai (rândurile se păstrează
prin `nl2br`, escaparea se face înainte — altfel `<br>` ar fi escapat și el).

Cine anulează e verificat prin aceeași `evenimentDeEditat()` ca la editare, plus
token CSRF. Punctul de intrare nu se bazează pe faptul că butonul s-a văzut în
pagină: cererea poate veni de oriunde, cu orice slug. Și fiindcă
`evenimentDeEditat()` refuză un eveniment deja anulat, a doua anulare nu are ce
mai anula.

După anulare, omul ajunge pe profilul lui cu un „Evenimentul a fost anulat." —
mesajul trece prin `$_SESSION['mesaj_bun']`, ca la intrarea cu Google, deci se
arată o singură dată.

**Niciun e-mail nu pleacă acum**, fiindcă n-are cui: „mă interesează" și „voi
participa" nu există încă. `TODO`-ul din `anuleazaEveniment()` scrie ordinea de
atunci, și ordinea contează: **întâi** e-mailul cu textul din `motiv_anulare`,
în clipa anulării — ăsta e singurul pas automat — și **abia mai târziu**,
ca acțiune de staff, curățenia finală: ștergerea rândului anulat, a
înscrierilor, a comentariilor și a copertei de pe disc. Invers, n-ar mai avea
cui trimite e-mailul și nici ce să scrie în el.

### Ce nu e făcut

Nu mai e nimic șablon pe pagina unui eveniment: „Mergi la acest eveniment?",
comentariile și amândouă listele din taburi vin din bază; vezi secțiunile lor.

Nu există încă interfață de aprobare și nici încheiere manuală. Evenimentul
intră cu `stare_moderare = 'in_asteptare'` și nu se vede pe prima pagină; omul
vede doar „Evenimentul tău a fost trimis spre aprobare" — dinadins fără detalii
despre cât durează, cât timp nu putem promite nimic.

Imaginile implicite de categorie (`categorii.imagine_default`) se urcă de mână
în `assets/img/categorii/`; unde lipsesc, un eveniment fără copertă rămâne fără
poza mare. Adresa lor se scrie printr-un singur loc, `urlImagineCategorie()`.

Verificările: `php teste/test-evenimente.php http://127.0.0.1:8126`
(292 de cazuri, cere serverul pornit).


## Comentariile

Discuția de sub un eveniment. Se vede de oricine, se scrie doar cu cont.

`sql/015-comentarii.sql`, `inc/comentarii.php`, `api/comentarii.php`, partea de
jos a lui `event.php` și blocul „COMENTARII" din `assets/js/main.js`.

### Două niveluri, și de ce nu trei

Un comentariu e ori **principal** (`parinte_id` gol, stă direct sub eveniment),
ori **secundar** (`parinte_id` = principalul sub care stă). Atât.

Un răspuns dat unui secundar **nu** coboară pe al treilea nivel: se pune sub
același principal și doar spune cui îi răspunde, prin `raspuns_la_id`. La al
treilea nivel de indentare, pe un telefon, un comentariu are lățimea unui
cuvânt — iar o discuție care se ramifică în copac nu se mai poate citi de sus
în jos, fiindcă nimeni nu știe de unde s-o reia.

Adâncimea n-o hotărăște cine apasă, ci locul. De aceea `salveazaComentariu()`
nu primește „ce nivel", ci „la cine":

```php
$eSecundar   = $catre['parinte_id'] !== null;
$parinteId   = $eSecundar ? (int) $catre['parinte_id'] : (int) $catre['id'];
$raspunsLaId = $eSecundar ? (int) $catre['id'] : null;
```

Mențiunea apare numai la al doilea caz. Sub un principal, primul răspuns se
vede de la sine pentru cine e; al doilea, nu.

Ea stă **în text**, lipită de primul cuvânt, ca o adresare:

```html
<p class="comment__text"><a class="comment__mentiune" href="profil.php?m=…"
   ><span class="comment__at">@</span>N. Elena</a> Perfect, vă așteptăm.</p>
```

Semnul are învelișul lui fiindcă în Plus Jakarta Sans — ca la mai toate
fonturile — „@" e desenat în jurul liniei de bază, nu deasupra ei, deci lângă
litere pare căzut. `.comment__at` îl ridică `0.07em`, cu `position: relative`
și nu `vertical-align`: așa se mută semnul fără să crească înălțimea rândului.
Rămâne de citit cu voce tare — „at N. Elena" spune că e o adresare, pe când
numele singur ar părea că răspunsul începe pur și simplu cu el.

Nu deasupra, lângă numele celui care scrie — acolo arăta ca încă o etichetă a
autorului, ca insignele, și se citea greșit: „R. Ioana către N. Elena" pare o
însușire a lui Ioana, nu începutul vorbei ei. Așa cum e acum, se citește cum se
și vorbește.

Numai în primul paragraf, nu în fiecare: un „@N. Elena" în capul tuturor ar fi
părut că i se strigă numele de trei ori. Iar spațiul dintre nume și vorbă e în
HTML, nu în CSS — el desparte două cuvinte, deci trebuie să vină cu ele la
copierea textului.

**Mențiunea nu e în bază.** Se desenează din `raspuns_la_id`, la fiecare
randare. De aceea `textulBrut()` din `main.js` o scoate din DOM înainte să
umple caseta de editare: lăsată acolo, ar fi apărut ca și cum ar fi scris-o
omul, iar la salvare ar fi intrat de-a binelea în text — la a doua corectură ar
fi fost două.

### Ordinea

Principalele **după aprecieri**, apoi de la nou la vechi. Sus stă ce a găsit
lumea de cuviință să ridice, iar la egalitate — și mai ales la zero, unde sunt
cele mai multe — hotărăște vechimea, ca la orice listă de noutăți.

Răspunsurile, **invers și fără socoteala aprecierilor**: de la vechi la nou.
Acolo nu e o listă, e o discuție, iar o discuție se citește de la început.
Sortate după aprecieri, ar fi ajuns răspunsul înaintea întrebării la care
răspunde.

Ordinea se face în `grupeazaComentarii()`, în PHP, nu în SQL. Cererea aduce
rândurile după id — o singură trecere prin index, fără sortare — iar cele două
reguli sunt potrivnice, deci n-ar fi încăput oricum într-un singur `ORDER BY`.

Se socotește la fiecare încărcare și nu se ține minte nicăieri: o apreciere
dată acum mută comentariul abia la următoarea deschidere a paginii. Dinadins —
dacă lista s-ar rearanja sub ochii omului, ar fugi rândul pe care tocmai îl
citea. Din același motiv, un comentariu proaspăt intră în capul listei deși are
zero aprecieri: omul trebuie să-și vadă vorba, nu s-o caute printre cele
ridicate de alții.

### Cum arată un om

Numele e mereu prescurtat — „R. Ioana", prin `numeAfisat()`, aceeași funcție ca
peste tot pe site — și duce la `profil.php?m=<permalink>`.

Lângă el, insignele:

| Insignă | Când |
|---|---|
| `Staff` | `membri.este_staff = 1` |
| `Organizator` | comentariul e al celui care a pus evenimentul |
| `Participant` | are rând `participant` în `interese_evenimente` |

**Staff și Organizator se poartă amândouă** — sunt două lucruri diferite, iar
cine le are pe amândouă le merită pe amândouă. „Participant" nu se mai scrie
lângă „Organizator": cine pune evenimentul la cale vine la el, iar rândul lui
de participant se scrie automat la salvare (vezi `faOrganizatorulParticipant()`).
Ar fi fost o insignă care spune ce se înțelege oricum din cealaltă.

Contul șters se anonimizează, nu dispare (vezi `inc/stergere.php`), iar
comentariile lui rămân în discuție — sunt vorbele cuiva, iar restul discuției
atârnă de ele. Dar omul a plecat: se scrie „Utilizator șters", fără chip, fără
insigne și fără drum spre profil.

### Aprecierile

Anonime pe ecran — se vede doar numărul, niciodată cine. Atunci de ce un rând
per om în `comentarii_aprecieri`, și nu un contor pe comentariu? Fiindcă altfel
nimeni n-ar putea să-și ia aprecierea înapoi, iar același om ar putea apăsa de
o sută de ori.

Ca la „Mă interesează", serverul nu primește „ce să facem", ci „pe ce s-a
apăsat" — el știe starea adevărată. Se încearcă întâi ștergerea; dacă n-a șters
nimic, se pune acum:

```php
$sters->execute([$comentariuId, $membruId]);
$apreciat = $sters->rowCount() === 0;
```

Așa nu există clipa dintre „citesc dacă există" și „scriu", în care ar încăpea
a doua apăsare. Numărul de pe buton se citește din bază **după** schimbare, nu
se socotește în browser: între două apăsări ale omului nostru pot intra alți
zece.

Vizitatorul neconectat vede butonul și numărul — e o veste bună pentru discuție
— dar apăsarea îl duce la `login.php`, cu întoarcere fix pe evenimentul ăsta.

### Raportarea

Un steag mic, în antet, imediat după ora comentariului, cu „Raportează" scris
lângă el. Apeși — comentariul e raportat și scrie „Raportat"; apeși iar —
raportul se retrage. Rândurile stau în `comentarii_rapoarte`
(`sql/020`), cu perechea (comentariu, om) drept cheie primară, iar comutarea se
face cu exact pattern-ul de la aprecieri: se încearcă întâi `DELETE`, iar dacă
n-a șters nimic, se scrie acum. Serverul primește „s-a apăsat", nu „raportează"
— o filă rămasă deschisă de ieri nu poate cere o stare care între timp s-a
schimbat.

De ce un tabel și nu o coloană `raportat` pe comentariu, cum ar fi fost mai la
îndemână: fiindcă raportul se ia înapoi. Cu un singur semn, al doilea om care
apasă l-ar fi stins pe al primului, iar același om ar fi putut apăsa de o sută
de ori.

**Numărul rapoartelor nu ajunge niciodată în pagină.** Omul vede doar dacă
_el_ a raportat; câți au raportat e treaba staff-ului. Un contor la vedere ar fi
devenit o unealtă de rușinare publică, și încă una ușor de umflat de câțiva
prieteni. `numaraRapoarte()` există, dar deocamdată n-o cheamă decât viitoarea
listă de moderare.

Cine vede steagul:

| Cine | Vede steagul |
|---|---|
| vizitator neconectat | nu |
| autorul comentariului | nu |
| oricine altcineva, conectat | da |
| staff | da (n-are drepturi în plus aici) |

**De ce în antet, și nu printre unelte.** „Apreciază", „Răspunde", „Editează" și
„Șterge" sunt lucruri pe care le faci tu — îți place, răspunzi, îți corectezi
vorba. Raportul e ceva ce faci DESPRE comentariul altuia; lângă ora la care a
fost scris se citește ca ce e, o însemnare pe eticheta comentariului, nu încă o
unealtă în rândul tău.

**Și cu vorba scrisă, nu doar semnul.** Un steag singur nu spune nimic nimănui:
cine nu-l mai văzuse trebuia să apese ca să afle ce face, adică exact ce nu vrei
la un buton care nu se ia înapoi decât apăsând din nou.

Pentru autor nu se scrie deloc în HTML, nu se desenează stins: un buton care
spune „nu poți" ar fi fost o invitație în plus la o faptă pe care n-o cere
nimeni. Regula e una singură, `poateRaporta()` — o cheamă și randarea, și
`api/comentarii.php`, care întreabă din nou tot ce s-a întrebat în pagină.

Un comentariu **golit** (piatră de mormânt) nu se mai raportează: n-a mai rămas
nimic de citit acolo. Rapoartele lui rămân însă în bază — staff-ul are dreptul
să vadă ce s-a raportat, chiar dacă autorul a șters între timp. Abia ștergerea
de tot a comentariului le duce cu ea, în cascadă.

### Cine află pe e-mail

Un comentariu scris în gol nu ajuta pe nimeni: organizatorul nu vedea întrebarea
decât dacă intra din nou pe pagină, iar cine primea un răspuns nu afla niciodată.
De aici încolo pleacă un mesaj — către **un singur om**, de fiecare dată:

| Ce s-a scris | Cine află |
|---|---|
| comentariu **principal** | organizatorul evenimentului |
| **răspuns** la un comentariu principal | autorul acelui comentariu |
| **răspuns** la un răspuns | autorul comentariului pe care s-a apăsat |

Organizatorul **nu** primește nimic pentru răspunsurile de sub anunțul lui. La o
discuție de treizeci de rânduri ar fi primit treizeci de mesaje despre vorbe care
nu-i erau adresate, și ar fi stins bifa după al treilea.

Nu primesc niciodată nimic: omul însuși (cine își răspunde singur, sau comentează
sub propriul anunț, știe deja), cine a stins `email_comentarii` din setări, și un
cont care nu mai e activ — suspendat sau anonimizat.

Regula stă într-un singur loc, `omDeInstiintatLaComentariu()` din
`inc/comentarii.php`, care întoarce omul sau `null`. **Nu trimite el mesajul:**
asta face `instiinteazaDeComentariu()` din `api/comentarii.php`, exact aceeași
împărțire ca la anularea unui eveniment. Acolo e stratul care atinge baza, iar un
`require` de `email.php` în `comentarii.php` ar lega două lucruri care n-au de ce
să se cunoască.

**Textul comentariului intră întreg în e-mail**, ca citat, cu rândurile scrise de
om păstrate (`nl2br` peste textul deja scăpat cu `h()`, în ordinea asta — invers,
`<br />`-urile noastre s-ar fi văzut ca text). Un „ai primit un răspuns, intră pe
site" e chiar felul de mesaj pe care nu-l mai deschide nimeni a doua oară; cu
vorbele în față, omul știe pe loc dacă are ce răspunde.

Butonul duce fix la comentariu: `event.php?slug=…#c123`, unde `c123` e `id`-ul
pus pe `<article class="comment__body">` în `randeazaComentariu()`. Acolo, și nu
pe `<li>`-ul din jur, fiindcă `<li>`-ul se scrie în trei locuri (lista întreagă,
răspunsurile, și răspunsul întors de API), iar articolul într-unul singur.

**Unde se oprește pagina** o spune `scroll-margin-top` de pe `.comment__body`,
în CSS — nu în JS, la fel ca la `.panel`: browserul încearcă și el să sară singur
la elementul din adresă, iar încercarea lui vine după a noastră, deci amândoi
trebuie să se oprească în același loc. Fără regula asta, comentariul se lipea de
marginea de sus a ecranului și antetul îi acoperea primele rânduri: omul primea
un mesaj care spunea „ți-a răspuns cineva", apăsa, și ateriza pe o vorbă tăiată
la jumătate.

**Și dacă a rămas dincolo de primul teanc?** Din cele cincisprezece care se văd
la început, comentariul căutat poate fi al douăzecilea — deci `hidden`, iar
browserul n-are la ce sări. `main.js` citește diezul la încărcare (și la
`hashchange`), socotește al câtelea e în listă, desface exact atâtea teancuri cât
să iasă la iveală, și abia apoi sare la el. Nu le arată pe toate deodată: la o
discuție de o sută de rânduri, cel căutat ar fi rămas tot îngropat, doar că
într-o pagină mai lungă.

Vestea pleacă **după** ce comentariul e scris în bază, niciodată înainte — și un
mesaj care nu pleacă nu oprește publicarea. Cel care a scris n-are nicio vină și
n-are ce face cu o eroare despre serverul de e-mail; ce n-a mers ajunge în log.

### Ștergerea, și de ce nu e mereu o ștergere

| Ce se șterge | Ce se întâmplă |
|---|---|
| principal **fără** răspunsuri | `DELETE`, se duce de tot |
| principal **cu** răspunsuri | se golește: `sters = 1`, text gol |
| orice secundar | `DELETE`, se duce de tot |

Un principal cu răspunsuri nu poate să dispară: ar rămâne suspendate în aer
răspunsurile la el, iar discuția n-ar mai avea început. Rândul rămâne ca piatră
de mormânt — în locul numelui scrie **„Comentariu șters"**, în locul textului
**„Acest comentariu a fost șters"**, iar chipul e cel implicit. Nu mai e al
nimănui: nici staff-ul nu mai are ce-i face, iar la ea nu se mai răspunde.

Aprecierile ei se șterg: erau pentru ce scria acolo, iar acolo nu mai scrie
nimic.

Și încă un pas, la sfârșit: dacă răspunsul tocmai șters era **ultimul** de sub
o piatră de mormânt, se duce și ea. Rămăsese doar ca să țină discuția legată,
iar discuția nu mai e.

Legătura părinte→copil nu e cheie străină, dinadins: ar fi una care arată spre
același tabel peste care trece deja cascada de la ștergerea evenimentului, iar
două cascade care se întâlnesc pe un tabel sunt exact locul unde InnoDB nu mai
garantează nimic. Ștergerea răspunsurilor odată cu principalul o face
`stergeComentariu()`, într-o tranzacție.

### Staff-ul

Cine are altceva decât `0` în `membri.este_staff` poate edita și șterge orice comentariu, ca
și cum ar fi al lui. Aceeași funcție hotărăște și dacă butonul se desenează în
pagină, și dacă cererea trece prin API:

```php
function poateModificaComentariul(array $comentariu, int $membruId, bool $eStaff): bool
```

În pagină e o purtare frumoasă; în `api/comentarii.php` e regula — cererea
poate veni de oriunde, nu doar de pe butoanele noastre.

### „Vezi mai multe comentarii"

**Toate** comentariile intră în pagină de la prima încărcare. Butonul nu aduce
nimic de pe server, doar dă la o parte: `main.js` lasă la vedere primele
`COMENTARII_DEODATA` (15) și mai arată câte 15 la fiecare apăsare, scriind pe
buton câte au rămas ascunse — „Vezi mai multe comentarii (încă 12)".

De ce așa și nu cerute pe rând: aici discuția e scurtă — zeci de rânduri, nu
mii — iar în schimbul câtorva kiloocteți în plus se câștigă un buton care
răspunde pe loc, o pagină care se poate căuta cu Ctrl+F întreagă și comentarii
pe care le vede și Google.

Se numără la grămadă, principale și secundare la un loc, **în ordinea în care
apar pe ecran**. Ordinea asta face ca primele 15 să fie mereu un început întreg
de discuție: dacă un principal a rămas dincolo de tăietură, răspunsurile lui
sunt și ele dincolo, deci nu se poate întâmpla să atârne un răspuns fără
întrebarea lui.

### Antetul, pe două rânduri

Cine a scris, și dedesubt când:

```html
<div class="comment__head">
  <div class="comment__cine"><a class="comment__author">…</a><span class="badge">…</span></div>
  <div class="comment__cand"><time datetime="…">acum 6 ore</time></div>
</div>
```

Toate pe un rând, ora venea după insigne — care sunt când una, când două, când
niciuna — deci pornea din alt loc la fiecare comentariu, iar la unul cu nume
lung se rupea singură pe rândul următor, aliniată aiurea. Punctul care le
despărțea a plecat odată cu rândul comun.

Pe ecran scrie „acum 6 ore" (`timpRelativ()`), în `datetime` stă clipa exactă:
prima e pentru om, a doua pentru browser și pentru cine citește pagina cu alt
program decât ochii.

### Structura din pagină

```html
<li class="comment" data-comentariu="12">
  <article class="comment__body">…</article>
  <ul class="comment__replies">…</ul>      <!-- FRATE, nu copil -->
</li>
```

Răspunsurile stau **lângă** `article`, nu în el. Așa se poate înlocui un
comentariu editat sau golit — serverul întoarce doar `<article>` — fără să se
ducă odată cu el discuția de dedesubt.

Tot HTML-ul vine gata desenat din `inc/comentarii.php`, și la încărcarea
paginii, și după fiecare apăsare. Nu se lipește din bucăți în JS: ar fi
însemnat două locuri care desenează același lucru, iar al doilea ar fi rămas în
urmă de la prima corectură — și ar fi însemnat text venit de la om lipit în
pagină fără trecerea prin `h()`.

### Ce mai verifică serverul

- **CSRF** la fiecare cerere, ca peste tot.
- **Cont**, altfel 401 — semnul după care `main.js` trimite omul la login.
- **Evenimentul**: aceeași `poateVedeaEvenimentul()` ca la deschiderea paginii,
  și același răspuns pentru „nu există" și „nu ai voie".
- **Publicat**: sub unul în așteptare, respins sau anulat nu se discută. Un
  eveniment **încheiat** rămâne însă deschis la comentarii, spre deosebire de
  listele de participanți: acolo se închide o socoteală, aici oamenii spun cum
  a fost — și asta se întâmplă mai ales după.
- **Textul**: `verificaComentariu()`, între 2 și 2000 de caractere (nu octeți),
  curățat cu `curataTextPeRanduri()`. În bază intră text curat, neescapat.
- **Cât de des**: cel mult un comentariu la 15 secunde per om, numărat în
  tabelul propriu al funcției. Nu ca să-l încetinească pe cel care are ceva de
  spus, ci pentru cine ar vrea să umple o discuție dintr-un script.

### Ce nu e făcut

Nimeni nu e înștiințat de nimic: cine primește un răspuns nu află decât dacă se
întoarce singur pe pagină. Nu există raportare și nici pagină de moderare —
staff-ul umblă la comentarii de pe pagina evenimentului, ca oricare autor.

Verificările: `php teste/test-comentarii.php` (86 de cazuri, cere baza de date,
nu și serverul).


## Listele din taburi

Taburile „Interesați" și „Participă" de pe pagina evenimentului: cine s-a
adunat în jurul lui, și uneltele prin care organizatorul face curat pe lista de
participanți.

`sql/016-excluderi-evenimente.sql`, partea de jos a lui `inc/interese.php`,
`api/exclude-participant.php`, cele două panouri din `event.php` și blocul
„PANOURILE CU OAMENI" din `assets/js/main.js`.

### Un singur panou, de două ori

Cele două taburi sunt **același panou cu altă valoare în `data-stare`**. Lista
se desenează dintr-un singur loc, `randeazaListaOameni()`, iar ascunsul și
butonul „Vezi mai mult" le face un singur bloc din `main.js`, care merge peste
orice element cu `data-oameni`.

Se deosebesc prin trei lucruri, și atât:

| | Interesați | Participă |
|---|---|---|
| `data-stare` | `interesat` | `participant` |
| sub nume | „Interesată acum 2 ore" | „Confirmat ieri" |
| butoane de scoatere | nu | da, pentru organizator și staff |

Interesații n-au ce curăța: „Mă interesează" nu ocupă niciun loc, e o însemnare
în dreptul omului, nu o hotărâre. De aceea `randeazaListaOameni()` primește
`false` implicit pentru butoane — panoul acela nu le poate cere nici din
greșeală.

### Cine se vede

Toți cei cu starea cerută în `interese_evenimente`, **în ordinea înscrierii**:
la un eveniment cu locuri limitate, ordinea aia chiar înseamnă ceva. Numele e
prescurtat — „R. Ioana", prin `numeAfisat()` — și duce la
`profil.php?m=<permalink>`, ca peste tot pe site.

Doar conturile active, prin aceeași bucată de SQL (`INTERESE_DOAR_ACTIVI`) din
care se numără oamenii de pe butoane: lista și numărul de deasupra ei nu au
voie să spună două lucruri diferite.

Ca la comentarii, **toți intră în pagină de la început**; `main.js` lasă la
vedere primii `OAMENI_DEODATA` (10) și mai arată câte zece la fiecare apăsare
pe „Vezi mai mult (încă 13)". Zece, nu cincisprezece ca la comentarii: un om e
un rând scurt, iar zece dintre ei ocupă cât patru comentarii.

Rândul de deasupra listei — „12 persoane au confirmat că vor participa." — se
scrie tot dintr-un singur loc, `vorbaDespreCatiSunt()`: opt feluri de a spune
același lucru (două taburi × una/mai multe persoane × înainte/după eveniment),
care copiate în pagină s-ar fi despărțit. Numărul poartă `data-count-for`, ca
`main.js` să-l schimbe odată cu cel de pe tab și cu cel de pe butonul mare.

### Ce se schimbă în timp real

Cine apasă „Mă interesează" sau „Voi participa" **se vede pe loc în tabul de
dedesubt** — nu după o reîncărcare pe care n-avea de ce s-o ghicească. La fel
la retragere, la trecerea dintr-o listă în alta și la scoaterea unui
participant.

Amândouă API-urile întorc aceeași bucată, `raspunsulPanourilor()`: pentru
fiecare stare, lista desenată, rândul de deasupra ei și dacă a rămas goală.
`main.js` o aplică printr-o singură funcție, `aplicaPanouriOameni()`, care
caută panourile după `data-stare`. Fiecare panou și-a agățat pe el un
`__aplica`, deci orice parte a fișierului îl poate împrospăta fără să știe
nimic despre închiderea lui.

Nimic nu se socotește în browser: între încărcarea paginii și apăsare pot intra
sau ieși alții, iar un număr crescut cu unu aici ar fi rămas greșit până la
reîncărcare.

### Cine nu se poate înscrie

`motivBlocajParticipare()` — **un singur loc** pentru toate opreliștile,
fiindcă de el atârnă două lucruri care trebuie să spună la fel: butonul stins
din pagină (cu motivul scris sub el) și refuzul din `api/interes.php`.

| Oprelişte | Ce scrie |
|---|---|
| ușa închisă la scoatere | „Nu te mai poți înscrie la acest eveniment." |
| `gen_participanti = 'femei'`, iar omul nu e femeie | „Evenimentul e doar pentru femei." |
| `gen_participanti = 'barbati'`, iar omul nu e bărbat | „Evenimentul e doar pentru bărbați." |

Butonul e **stins de-a binelea**, nu „duce la un refuz": înainte, caseta de
confirmare se deschidea, omul apăsa „Da, particip" și abia atunci afla că nu se
poate. Motivul se scrie sub buton, fiindcă `title` e pentru mouse și pe telefon
nu se vede niciodată.

Trei lucruri pe care opreliștile NU le ating:

- **„Mă interesează"** rămâne deschis. Nu ocupă niciun loc și nu duce pe nimeni
  acolo; e o însemnare în dreptul omului.
- **Retragerea.** Cine e deja pe listă nu e oprit de nimic — un eveniment poate
  fi schimbat în „doar pentru femei" după ce s-au înscris bărbați, iar ei nu au
  de ce să rămână prinși acolo.
- **Organizatorul**, la regula de gen. E trecut oricum pe listă la salvare,
  fiindcă e omul de care se leagă evenimentul: cineva trebuie să răspundă de o
  seară pentru mame, chiar dacă n-ar putea veni la ea ca participant.

Vizitatorul fără cont nu e oprit de nimic aici: butonul lui duce la intrare, iar
ce se poate și ce nu se hotărăște după ce se știe cine e.

### Scoaterea de pe listă

Butonul se vede doar organizatorului și staff-ului, și doar la un eveniment
publicat și neîncheiat — la unul trecut lista e istorie, nu o socoteală
deschisă. **Organizatorul nu poate fi scos**, nici de el însuși, nici de staff:
n-ar mai rămâne nimeni care să răspundă de eveniment, iar staff-ul are altă
unealtă pentru un eveniment care nu-i place — îl poate anula cu totul.

Caseta de confirmare cere un motiv de cel puțin `MOTIV_EXCLUDERE_MIN` (15)
caractere, fiindcă motivul **pleacă întreg în e-mailul primit de omul scos**.
„nu" sau „ok" nu i-ar spune nimic, iar el are dreptul să știe.

Sub motiv e o bifă: *„Nu se mai poate înscrie la acest eveniment."* Fără ea,
omul e doar dat jos și se poate întoarce — se întâmplă des, o listă plină de
oameni care nu mai vin se face curat fără supărare. Cu ea, `api/interes.php` îl
oprește la „Voi participa".

Interdicția oprește **doar participarea**, nu și „Mă interesează": una ocupă un
loc și aduce omul acolo, cealaltă e o însemnare în dreptul lui. Și nu-l ține pe
nimeni prizonier — retragerea de pe listă rămâne deschisă oricui.

### Ce se întâmplă în bază

Rândul din `interese_evenimente` **se șterge** — locul se eliberează pe loc.
Un rând rămas acolo, însemnat „scos", ar fi ținut un loc ocupat degeaba:
socoteala locurilor rămase se face peste tabelul acela.

Urma faptei rămâne în `excluderi_evenimente`: cine, pe cine, de ce, în ce
calitate, și dacă i s-a închis ușa. Amândouă scrierile într-o tranzacție — dacă
ar pica a doua, omul ar fi jos de pe listă fără ca nimeni să mai poată spune de
ce, și fără interdicția care poate era tot rostul.

`rol` se scrie **atunci**, nu se socotește la citire. Cine e staff azi poate să
nu mai fie la anul: întrebat mai târziu, `membri.este_staff` ar răspunde despre
omul de azi, nu despre fapta de atunci. Cine e și staff, și organizator, apare
ca „organizator" — e evenimentul lui, iar asta spune mai mult celui care
citește.

Un om, un eveniment, un singur rând (`INSERT ... ON DUPLICATE KEY UPDATE`):
cine a fost scos fără interdicție se poate înscrie la loc și poate fi scos din
nou, iar a doua oară se rescrie rândul de dinainte. Ținem starea de acum, nu
toată povestea.

### E-mailul

Singurul pas automat din toată povestea. Pleacă **după** scoatere, nu înainte:
invers, un e-mail trimis și o scriere picată i-ar fi spus omului o neadevărat.
Dacă e-mailul nu pleacă, scoaterea rămâne făcută — iar asta se poate îndrepta,
spre deosebire de un mesaj trimis degeaba. Cel care a apăsat vede lista nouă,
nu o eroare despre serverul de e-mail.

Textul spune cine l-a scos (organizator sau staff), motivul întreg, și — într-o
casetă care se vede, nu topit într-un paragraf — dacă ușa i s-a închis. Fără
acea casetă, omul s-ar întoarce pe pagină, ar apăsa „Voi participa" și ar primi
un refuz fără să înțeleagă de ce.

Acordul se face după om: „Ai fost scoasă" pentru ea, „Ai fost scos" pentru el.
E o veste neplăcută oricum — măcar să fie scrisă ca pentru cineva anume.

### Ce nu e făcut

Interdicția nu se poate ridica din interfață; rândul se schimbă de mână, din
phpMyAdmin. Nu există nici o pagină care să-i arate omului de unde a fost scos —
află doar din e-mail.

Nici `varsta_minima` nu e verificată la înscriere — coloana există din
`sql/009-evenimente.sql`, dar nimeni nu se uită la ea. E aceeași scăpare ca la
gen, doar că neatinsă încă.

Verificările: `php teste/test-participanti.php` (79 de cazuri, cere baza de
date, nu și serverul).


## Notele dintre participanți

Stelele de pe profil erau șablon: 4,6 din 23, scrise de mână, cu o distribuție
inventată și un formular care nu trimitea nimic nicăieri. Acum sunt ale
oamenilor.

`sql/017-evaluari.sql`, `inc/evaluari.php`, `api/evaluare.php`, stelele din
tabul „Au participat" (`event.php`) și secțiunea „Evaluări" din `profil.php`.

### Când și între cine

**După ce s-a încheiat**, între oameni care au fost amândoi pe lista de
participanți, și niciodată pe tine însuți. Nota are greutate tocmai fiindcă în
spatele ei e o seară petrecută împreună, nu o apăsare de pe un profil găsit la
întâmplare.

Toate opreliștile stau într-un loc, `motivBlocajEvaluare()`, de care atârnă și
stelele stinse din pagină, și refuzul din API — ca la participare.

### Stelele sunt anonime. Vorbele se semnează

**Stelele singure nu se văd deloc pe profil.** Intră în medie și în barele de
sus, dar nu apar nicăieri ca rând. Nimeni nu află cine i-a dat trei stele —
altfel nimeni n-ar mai da trei stele cuiva pe care îl reîntâlnește sâmbăta
viitoare, iar o notă care se semnează ajunge o notă frumoasă, adică una care nu
spune nimic. Rândul ține minte cine a dat-o doar ca să nu se poată nota același
om de zece ori, și ca să-și poată schimba părerea o dată dată.

**Cine se așază să scrie ceva își pune și numele.** `evaluarilePrimite()` cere
`ev.text IS NOT NULL AND ev.text <> ''`, iar pe profil părerea vine cu chip, cu
„N. Prenume" și cu legătură spre profilul autorului, ca la comentarii. O părere
semnată se cântărește altfel decât una venită de nicăieri, iar cel care o
primește are cui să-i răspundă.

Un rând care ar spune „cineva ți-a dat 4 stele" și atât n-are ce citi nimeni,
iar zece la rând ar îneca singura părere scrisă cu adevărat. De aceea numărul de
sub medie („31 evaluări") și numărul de rânduri de dedesubt nu se potrivesc, și
e în regulă: media e a stelelor, lista e a vorbelor.

Contul șters rămâne „Utilizator șters", fără legătură — aceeași regulă ca la
comentarii.

Lista intră toată în pagină și se ascunde din JS, **20 deodată**, cu „Vezi mai
mult (încă N)" — ca la comentarii și ca la listele din taburi. Fără nicio
părere scrisă se arată un singur rând: „Prenume nu a primit niciun feedback
scris."

**Caseta cu bare se arată întotdeauna**, chiar și la un om care n-a primit
nicio stea. Era până acum un rând de text în locul ei — „Nicio evaluare încă" —
iar profilul arăta altfel după cum fusese sau nu notat cineva. Barele goale spun
același lucru, dar spun și CE va apărea acolo: cinci trepte care așteaptă. În
locul mediei stă o linie, nu „0,0": aceea e o notă, și încă cea mai proastă cu
putință. Un om care n-a fost notat de nimeni n-a luat zero — n-a luat nimic.

**Steaua stinsă are nevoie de contur, nu de umplutură.** Pe alb, conturul de
`var(--border)` la 18px e practic invizibil: un „Fără rating" fără nimic sub el,
și o medie de 2,8 din care nu se înțelege din câte stele. Acum e același contur
ca la stelele pe care se apasă, din dreptul participanților
(`.stars-input--sm`) — acolo problema s-a pus prima dată, tot pe fundal deschis,
și tot așa s-a rezolvat.

Pe profilul propriu, lipit sub bare, scrie de unde vin cifrele: „Notele sunt
date în mod anonim de oamenii cu care ai participat la evenimente." Stă acolo,
nu la capătul de jos al secțiunii, fiindcă despre bare vorbește — e subsolul
lor, nu un paragraf de sine stătător. Și apare **numai odată cu ele**: fără
nicio notă, deasupra scrie oricum „Nicio evaluare încă. Notele vin de la
oamenii cu care a fost la evenimente", iar două rânduri care spun același lucru
unul sub altul ar fi vorbă în plus. Anonimatul e ceva de lămurit despre note
care există.

### Două căi către aceeași notă

De pe **pagina evenimentului**, dintr-o apăsare pe stele, în dreptul fiecărui
participant. După apăsare apare „Lasă și câteva cuvinte", care deschide caseta
de scris **chiar acolo, sub rândul omului**.

De pe **profil**, cu stele și text. Formularul apare doar cu `?ev=<slug>` în
adresă — adică doar dacă omul a venit de pe pagina unui eveniment încheiat la
care au fost amândoi. Fără el nici nu se desenează: un formular care se vede și
refuză la apăsare e mai rău decât unul care lipsește.

Amândouă scriu în același rând (`INSERT ... ON DUPLICATE KEY UPDATE`). Textul
se PĂSTREAZĂ când vin doar stele: cine schimbă nota de pe pagina evenimentului
n-are de unde să știe că altfel și-ar șterge vorbele scrise pe profil.

### Părerea se scrie unde stau stelele

„Lasă și câteva cuvinte" a fost o vreme o legătură: deschidea profilul omului
într-o filă nouă și derula până la formularul de acolo. Pierdea două lucruri
deodată — **locul din listă** (cine tocmai notase trei oameni pleca de lângă al
patrulea) și **firul gândului**, fiindcă pentru o propoziție ajungeai pe o
pagină despre altcineva, printre păreri vechi.

Acum e un `<button>` care deschide o casetă sub rândul lui. Nu e o unealtă
nouă: e a treia folosire a aceleiași `deschideCaseta()` din `main.js`, după
scoaterea de pe listă și „Nu s-a prezentat" — aceleași clase, același loc,
același „a doua apăsare o închide". Șablonul stă în `event.php`, ca tot HTML-ul
de pe site.

**Se îndreaptă, nu se adaugă.** Redeschisă, caseta arată ce a scris omul data
trecută. Textul vine în pagină odată cu stelele (`data-parere`, scris de
`randeazaSteleParticipant()`), din același rând al aceleiași cereri —
`noteleMeleLaEveniment()` aduce acum și `text`, nu doar `stele`. A doua
întrebare pusă bazei pentru asta ar fi fost una degeaba. E textul **celui care
se uită**, despre altcineva, nu părerile altora despre el: ajunge în pagină
numai la cel care l-a scris.

**Caseta golită își retrage vorbele.** Asta a cerut un semnal nou în
`salveazaEvaluare()`: `$eScriere`. Fără el, `null` însemna peste tot „nu atinge
ce scria înainte" — bun pentru o stea apăsată, care nu trimite niciun text, dar
greșit pentru un formular pe care omul îl vede și îl trimite gol. Deosebirea nu
se poate face din valoare, fiindcă amândouă ajung ca `null`; se face din **ce a
apăsat omul**, iar asta o știe doar cel care cheamă funcția. Fără ea, cine își
ștergea părerea o găsea rămasă acolo la reîncărcare, fără nicio vorbă — cea mai
rea formă de „n-a mers": una care spune că a mers.

### Cine află că i s-a scris ceva

**Doar pentru părerile SCRISE.** Când cineva îți lasă câteva cuvinte pe profil,
primești un e-mail cu numele lui, cu textul și cu un buton către profilul tău.
`omDeInstiintatLaFeedback()` hotărăște cine îl primește; mesajul pleacă din
`api/evaluare.php`, după scriere.

**Niciodată pentru stele.** Ele rămân anonime, iar un mesaj despre ele ar fi
fost ori inutil („cineva te-a notat, nu-ți spunem cine, nu-ți spunem cât"), ori
o spargere a anonimatului care ține notele cinstite. Cinci oameni la o ieșire ar
fi însemnat cinci astfel de mesaje.

**O singură dată pentru o părere, oricâte îndreptări ar urma.** Vestea pleca, o
vreme, la fiecare text schimbat. Suna cuminte, dar în viață omul își îndreaptă
vorbele: scrie în grabă, vede o greșeală, se răzgândește asupra unui cuvânt.
Zece îndreptări însemnau zece e-mailuri despre *aceeași* părere — iar cel care
le primea învăța, pe bună dreptate, să nu le mai deschidă.

Ștampila e `evaluari.instiintat_la` (`sql/028`), pusă pe chiar rândul părerii —
acolo e unicitatea care ne trebuie, fiindcă un rând din `evaluari` **înseamnă**
„părerea lui X despre Y, la evenimentul Z". O socoteală ținută în altă parte ar
fi trebuit să refacă tocmai cheia asta, cu mâna.

**Hotărârea se ia în `WHERE`**, nu într-un `SELECT` urmat de un `UPDATE`:

```sql
UPDATE evaluari SET instiintat_la = ?
 WHERE eveniment_id = ? AND evaluat_id = ? AND evaluator_id = ?
   AND instiintat_la IS NULL
```

Două file deschise deodată ar fi trecut amândouă de o verificare făcută separat.
Aici, a doua cerere schimbă zero rânduri și primește „nu" — aceeași croială ca
la revendicarea unui abțibild și ca la ștampila de mulțumiri: cine scrie primul
câștigă, ceilalți află că n-au ce scrie.

**Ordinea contează:** se ștampilează întâi, și abia dacă ștampila a prins se
trimite. Invers, cele două file ar fi trimis amândouă înainte ca vreuna să apuce
să însemne ceva. Dacă poșta pică după ștampilă se pierde un mesaj — mai bine
decât un potop, iar părerea rămâne scrisă oricum.

**Ștampila nu se șterge niciodată**, nici când omul își retrage vorbele: altfel
„scrie – șterge – scrie" ar fi devenit felul de a trimite oricâte mesaje.

Și nici la „Nu s-a prezentat" nu pleacă nimic: textul acela nu e părerea
nimănui, e scris de noi.

Nu primesc nimic: omul însuși, cine a stins bifa `email_feedback` din setări, și
un cont care nu mai e activ.

### „Nu s-a prezentat"

Numai organizatorul, numai după încheiere. Pune **o stea** și un text scris de
noi („Nu s-a prezentat la eveniment, deși s-a înscris pe lista de
participanți."), însemnat cu `automat = 1`.

Se ține deoparte fiindcă se citește altfel: e un fapt („n-a venit"), nu o
părere. Pe profil apare cu dungă în stânga și în capul ei scrie „Însemnare de
la organizator", nu numele cuiva.

**După ea, omul acela nu se mai notează de nimeni.** În dreptul lui rămâne un
singur cuvânt — „Neprezentat" — pe care îl vede toată lumea: nici stele, nici
„Lasă și câteva cuvinte", nici butonul care tocmai s-a apăsat. Nu se sting,
pleacă.

Regula asta îl privește mai ales pe cel care a pus însemnarea: cu stelele
rămase aprinse, organizatorul ar fi putut alege peste o săptămână cinci și ar fi
șters cu ele exact ce scrisese — poate după o vorbă bună de la cineva. Se ține
în trei locuri care nu se pot despărți: `randeazaOm()` la desenare,
`esteNeprezentat()` în `api/evaluare.php` la orice cerere (409), și JS-ul care
înlocuiește rândul pe loc, fără reîncărcare.

### Când îngheață listele

Odată ce evenimentul **a început** — nu când se încheie, cum era înainte:

- **caseta „Mergi la acest eveniment?" dispare cu totul.** Nu se stinge, pleacă:
  o casetă mare care pune întrebarea asta deasupra unui eveniment care se
  petrece chiar acum, sau care s-a terminat, e o întrebare fără rost în cel mai
  vizibil loc al paginii. Cine vrea să vadă cine a fost are taburile de mai jos,
  unde scrie „Au participat".
- organizatorul și staff-ul nu mai pot scoate pe nimeni

Între început și încheiere e chiar evenimentul: o retragere de atunci n-ar mai
însemna nimic, fiindcă omul e (sau nu e) deja acolo. Iar cine s-ar șterge de pe
listă în timpul lui ar scăpa de „Nu s-a prezentat".

Butoanele nu mai există în pagină, deci n-au ce fi apăsate; oprirea adevărată
rămâne în `api/interes.php`, prin aceeași `evenimentAInceput()`, pentru o filă
lăsată deschisă de dinainte.

**Încheiat înseamnă și „a început".** Pe drumul obișnuit, încheierea vine
oricum după început: butonul „Încheie evenimentul" se dă doar după ce a pornit.
Dar starea se poate pune și de mână, din phpMyAdmin, așa cum se fac multe pe
site-ul ăsta — iar atunci ieșea o pagină care spunea „Acest eveniment s-a
încheiat." și dedesubt întreba, cu butoane vii, „Mergi la acest eveniment?", ba
mai lăsa organizatorul să scoată oameni de pe o listă care nu mai era a
nimănui. Acum `evenimentAInceput()` întreabă întâi dacă e încheiat: ce era
scris ca presupunere într-un comentariu e scris în cod.

Iar la **încheiere**, pleacă și tabul „Interesați" — și tabul, și panoul lui.
Rămân două: „Comentarii" și „Au participat". Lista de interesați nu spune nimic
despre seara care a fost, fiindcă sunt oameni care s-au uitat într-acolo și n-au
venit; cine a fost cu adevărat e în tabul de alături.

### „Nu s-a prezentat" se confirmă în pagină

Aceeași casetă ca la scoaterea de pe listă, în același loc — sub omul pe care
s-a apăsat — prin aceeași funcție (`deschideCaseta()` din `main.js`). E același
fel de faptă: ceva ce organizatorul face altuia și nu se mai poate lua înapoi.

A fost o vreme un `confirm()` din browser. E cel mai ușor de scris și cel mai
prost lucru de arătat: o fereastră care sare peste toată pagina, cu alte litere
și alte butoane decât tot restul site-ului, iar pe telefon lipită de bara de
adrese. Fapta e destul de grea — o stea și o notă pe profilul altui om,
definitiv — ca omul să merite s-o confirme uitându-se la rândul lui, nu la o
casetă gri de sistem.

Fără motiv, spre deosebire de scoatere: acolo textul pleacă în e-mailul omului,
care are dreptul să știe de ce. Aici nu se trimite nimănui nimic de citit.

### Vorba de după scoatere se face după om

„L-am scos de pe listă" despre o femeie e o scăpare care se vede din prima, mai
ales de cea despre care e vorba. Mesajul se alege pe server, unde sexul e deja
citit — se citea oricum, ca să i se poată scrie cum trebuie și în e-mail. Când
contul e șters între timp și nu mai e cine să fie întrebat, rămâne o vorbă fără
gen: mai bine seacă decât greșită.

În română, participiul de după „a avea" nu se acordă („am scos"), deci genul se
vede în pronume: **„L-am scos de pe listă"** pentru el, **„Am scos-o de pe
listă"** pentru ea. La e-mail e altfel — acolo e pasiv, cu „a fi", care se
acordă: „Ai fost scoasă" / „Ai fost scos".

Verificările: `php teste/test-evaluari.php` (96 de cazuri, cere baza de date,
nu și serverul).


## Prima pagină: evenimentele adevărate

Erau cinci articole scrise de mână în HTML, cu poze de umplutură și linkuri
care duceau la `event.php` fără slug — adică înapoi acasă. Acum sunt
evenimentele din bază, cu filtre și cu „Vezi mai mult".

`evenimenteDePePrima()` și `randeazaListaEvenimente()` din `inc/evenimente.php`,
`api/lista-evenimente.php`, blocul „PRIMA PAGINĂ" din `main.js`.

### Singurul loc unde lista se aduce din bucăți

Peste tot în rest — comentariile, oamenii dintr-un tab, evaluările de pe profil,
istoricul — tot ce e de arătat intră în pagină de la început, iar butonul doar
dă la o parte. Merge, fiindcă acolo listele au un capăt firesc: câți oameni
încap la un eveniment, câte păreri primește un om.

Prima pagină n-are niciunul. Peste un an sunt sute de evenimente, iar a le
trimite pe toate ca să se vadă zece ar fi o pagină de un megabyte pentru un
ecran de conținut. De aceea aici, și numai aici, fiecare teanc e o cerere.

**Primele zece le scrie PHP**, la încărcare. Fără ele, cine intră pe site ar
vedea o pagină goală până pleacă și se întoarce o a doua cerere — iar Google ar
indexa exact golul acela. Următoarele vin **patru câte patru**: cine a ajuns la
capătul listei caută ceva anume, iar patru cartonașe se citesc dintr-o privire.

Butonul nu spune câte au mai rămas, spre deosebire de celelalte „Vezi mai
mult" de pe site: acolo se știe, fiindcă tot ce e de arătat e deja în pagină.
Aici s-ar afla doar numărând tot ce e în bază, la fiecare apăsare, pentru un
număr care oricum n-ar ajuta pe nimeni. Se știe doar **dacă mai e ceva** — atât
îi trebuie butonului ca să hotărască dacă rămâne pe ecran, iar asta se află
cerând un rând în plus și aruncându-l.

Cât se aduce hotărăște serverul, după `de_la`, nu browserul: altfel o cerere
scrisă de mână ar fi putut cere zece mii deodată.

### Ordinea

Întâi ce e **fixat de echipă** (vezi mai jos). Apoi ce urmează, de la cel mai
apropiat. Apoi ce s-a încheiat, de la cel mai proaspăt: dintre două seri
trecute, cea de ieri interesează mai mult decât cea de acum trei luni.

Cele încheiate se văd din prima — poza se stinge, iar în colț scrie „Încheiat".
Semnul e pus de pe prima pagină, nu din cartonaș: în tabul „Istoric" de pe
profil totul e încheiat, iar un semn pe fiecare cartonaș ar fi doar zgomot.

### Anunțul fixat de echipă

Prima pagină se așază singură, după ceas. E ordinea bună în aproape toate
zilele — dar nu în toate. Când orașul are un lucru care *chiar* contează (o
sărbătoare, o strângere de ajutoare, o vânătoare pusă la cale de casă), el
trebuie să stea sus, oricât de departe ar fi ziua lui și oricâte s-ar întâmpla
mai devreme.

Piuneza (`evenimente.fixat_la`, `sql/029`) face exact atât: anunțul stă primul
și primește un chenar în culoarea site-ului, plus un semn în colțul de
jos-stânga al pozei — singurul rămas liber, fiindcă sus-stânga e categoria,
sus-dreapta e starea și jos-dreapta sunt cifrele. Toate patru trebuie să încapă
odată: un anunț fixat poate fi foarte bine unul anulat.

**Cheia de sortare stă înaintea tuturor**, și mai ales înaintea celei care
desparte viitorul de trecut:

```sql
ORDER BY (e.fixat_la IS NULL) ASC,
         e.fixat_la DESC,
         <s-a încheiat?> ASC,
         …
```

Altfel un anunț încheiat sau anulat, dar fixat, ar fi căzut oricum sub tot ce
urmează — și tocmai atunci piuneza n-ar mai fi făcut nimic. Între două fixate,
cel pus mai de curând stă deasupra: de aceea coloana e o **dată**, nu un 0/1 —
ea ține și ordinea, și răspunde la „de când stă ăsta în cap?".

**Numai omul casei** o pune și o ia, de pe pagina anunțului, cu butonul de lângă
„Editează". Nu e o unealtă a organizatorului: dacă ar fi, ar apăsa-o toți, iar
„primul în listă" n-ar mai însemna nimic — ar fi doar rândul obișnuit, scris cu
alt cuvânt. Butonul stă acolo, și nu într-o listă de administrare, fiindcă ce
merită capul primei pagini se vede citind anunțul, nu dintr-un rând de tabel.

**Se pune pe orice anunț care se vede pe site** — aprobat, încheiat sau anulat.
Un anunț anulat care rămâne fixat e chiar ce trebuie uneori: vestea că nu se mai
ține ajunge la toți cei care o așteptau tocmai stând sus. Ce nu se fixează e ce
n-a trecut încă pe la nimeni și ce a fost respins: acolo piuneza ar fi o unealtă
care nu face nimic, fiindcă anunțul nu e pe prima pagină deloc.

**Nu se stinge singură niciodată.** Se scoate cu mâna, de cine a pus-o.

Piuneza se pune **peste** stingere, nu în locul ei: un anunț încheiat, dar
fixat, rămâne stins ca oricare încheiat și păstrează „Încheiat" în colț — doar
că are chenar și stă primul. Altfel n-ar mai fi „încheiat", ar fi „altceva".

Butonul **comută și spune ce e acum**: stins scrie „Fixează", aprins scrie
„Fixat". Dintr-o apăsare, fără confirmare și fără reîncărcare — nu se pierde
nimic și se ia înapoi cu aceeași apăsare; cele două trepte de la „Încheie" și
„Anulează" sunt pentru fapte definitive. Se trimite **ce se vrea**, nu „comută":
cu o comutare, două file deschise deodată ar fi apăsat una după alta și ar fi
lăsat piuneza exact cum era.

### „Live": ce se petrece chiar acum

Între „urmează" și „s-a încheiat" mai e o stare, care ținea până acum de nimeni:
evenimentul a început, dar nu s-a terminat. Pe listă arăta ca oricare altul care
urmează, deși cine se uită la el nu mai are ce plănui — are unde să se ducă în
clipa asta.

Cartonașul primește un semn roșu cu un punct care pulsează. Starea nu se ține
minte nicăieri și nu se scrie în bază: se citește la fiecare afișare, prin
`evenimentAInceput()`, aceeași funcție de care atârnă și ce se poate face pe
pagina evenimentului. Un rând din bază ar fi trebuit întors de un cron la ora
potrivită și ar fi rămas mincinos între două rulări.

Ordinea nu se schimbă: cele Live sunt tot „neîncheiate", deci stau în capul
listei, unde ziua lor le duce oricum — a început azi, deci e cea mai apropiată.

Un cartonaș nu poate fi și Live, și încheiat, fiindcă întrebarea se pune într-o
singură ordine: dacă s-a încheiat, atât se scrie; abia dacă nu, se întreabă dacă
a început.

### Filtrele

Orașul dintr-o listă (din `config.php`, cu „Toate orașele" în frunte) și
categoriile din tabelul `categorii`, ca niște chipuri. Orice schimbare ia lista
de la capăt: iar primele zece, iar butonul de la început.

**Se arată doar categoriile care au ceva de arătat**, prin
`categoriiCuEvenimente()`. Un filtru care duce garantat la „niciun eveniment" nu
e un filtru, e o promisiune mincinoasă: omul apasă pe „Muzică", vede gol și
pleacă cu impresia că s-a stricat ceva. Cum categoriile se pot umple mâine,
lista se calculează la fiecare încărcare, nu se ține minte.

Formularul de publicare folosește mai departe `categoriiEvenimente()`, cu toate:
acolo se pun evenimente noi, deci o categorie goală trebuie să se poată alege —
altfel n-ar avea niciodată cum să înceteze să fie goală.

**Merg și fără JavaScript.** E un `<form method="get">` adevărat, iar
categoriile sunt legături, nu butoane: fără JS se reîncarcă pagina cu filtrele
în adresă și se vede exact același lucru. Cu JS, aceleași filtre aduc doar
lista. Butonul „Arată" de lângă lista de orașe stă în `<noscript>` și e scos
din pagină de JS, ca să nu rămână acolo unul care nu mai are ce face.

Filtrele stau în adresă, nu în sesiune: așa o pagină filtrată se poate da mai
departe pe WhatsApp și se deschide la fel la celălalt capăt. JS o rescrie cu
`replaceState`, nu `pushState` — altfel fiecare apăsare pe o categorie ar lăsa
o treaptă în istoric, iar „înapoi" ar trebui apăsat de șapte ori ca să ieși din
pagină.

Ce vine din adresă trece prin sitele lui: `orasulCerut()` caută în lista din
config și folosește **valoarea de acolo**, `categoriaCeruta()` la fel, cu
tabelul. Un oraș care nu există înseamnă „toate", nu o eroare — o adresă veche,
dintr-o zi în care exista alt oraș, trebuie să arate prima pagină, nu un ecran
roșu.

### Un API care doar citește

`api/lista-evenimente.php` e singurul de pe site care nu schimbă nimic. De
aceea e și singurul care primește **GET**, și singurul **fără token CSRF**:
tokenul apără de fapte făcute în numele cuiva fără voia lui, iar aici nu se
face nimic. Nici cont nu cere — lista e publică, la fel ca pagina din care
vine.

Întoarce **cartonașe gata desenate**, nu date brute, prin aceeași funcție care
scrie pagina la încărcare. Cu JSON de date, browserul ar fi trebuit să știe și
el să deseneze un cartonaș — adică a doua descriere a aceluiași lucru, în alt
limbaj, care s-ar fi despărțit de prima la întâia corectură.

### „Ar putea să te intereseze"

Jos, pe pagina unui eveniment, stăteau tot articole scrise de mână. Acum stau
**două evenimente luate la întâmplare** (`evenimenteSugerate()`), prin același
`randeazaCartonasEveniment()` ca peste tot.

Doar ce **urmează**, niciodată ce s-a încheiat și nici ce se petrece chiar
acum: locul ăsta e o invitație, iar la o seară care a trecut sau care a început
deja nu mai are cine să te invite. Evenimentul de pe pagina căruia ești e scos
din listă — n-are rost să te trimită unde ești.

Fără oraș: pe prima pagină omul a ales el ce vrea, aici nu ceruse nimic, iar
două evenimente din alt oraș sunt mai bune decât niciunul. Și chiar la
întâmplare, `ORDER BY RAND()`: la o mână de evenimente, „cele mai apropiate"
ar fi arătat aceleași două săptămâni la rând, oricui, pe orice pagină.

**Dacă nu iese niciunul, secțiunea nu se scrie deloc** — nici titlul, nici
legătura, nici chenarul gol. Pagina se oprește la comentarii și sare la subsol.
Un „Ar putea să te intereseze" cu nimic dedesubt e mai rău decât lipsa lui: pare
o pagină ruptă. Iar în ziua în care e un singur eveniment pe tot site-ul, exact
pagina lui e cea care ajunge să fie citită.

Legătura de lângă titlu spunea „Toate articolele" — o vorbă rămasă de pe vremea
blogului. Acum spune „Prima pagină" și duce chiar acolo.

Verificările: `php teste/test-prima-pagina.php http://127.0.0.1:8128` (60 de
cazuri, plus unul dacă în `config.php` e mai mult de un oraș; fără adresă merge
și fără server, sare doar partea de API).


## Taburile de pe profil: „Evaluări" și „Istoric"

Jumătatea de jos a profilului stă de-acum în două taburi. Primul e ce era
înainte — notele primite. Al doilea e nou: **pe unde a fost omul.**

`istoricEvenimente()` și `randeazaIstoric()` din `inc/evaluari.php`,
`randeazaCartonasEveniment()` din `inc/evenimente.php`, componenta
`[data-descopera]` din `main.js`.

### Ce intră în „Istoric"

Evenimentele **încheiate** la care e pe lista de participanți — ale lui și ale
altora, cele mai noi întâi. Încheiat înseamnă același lucru ca peste tot pe
site: ori i-a trecut ziua, ori organizatorul a apăsat „Încheie evenimentul".
Unul de sâmbăta viitoare n-are ce căuta într-un istoric: omul n-a fost încă
nicăieri.

Lipsesc și cele anulate, și cele care n-au ajuns niciodată publice: la primele
nimeni n-a fost nicăieri, la celelalte n-avea cum să se înscrie cineva.

**Nu se amestecă cu „Evenimente organizate"** de mai sus. Acolo se vede ce
*pune la cale*, adică ce urmează; aici, ce a fost. Un organizator cu douăzeci
de seri în urmă și niciuna în față are lista de sus goală și istoricul plin.

Cartonașele sunt cele de pe prima pagină, prin aceeași funcție. Două însemne se
adaugă în colțul pozei, fiindcă se pot spune numai privind de pe profilul cuiva
anume: **„Organizator"**, o laudă mică — seara aceea a existat fiindcă s-a
ocupat el de ea — și **„Absent"**, celălalt capăt. Al doilea se scrie pe față:
cine se uită la istoricul cuiva are dreptul să vadă și de câte ori n-a ajuns,
altfel cifra de sus ar rămâne fără nimic în spate.

Absența **nu scoate** evenimentul din listă, deși îl scoate din cifra „Prezent
la evenimente". Cifra spune la câte a fost; lista e istoria lui, iar din istorie
nu se șterge o seară fiindcă n-a ajuns la ea.

Fără niciun eveniment: „Prenume nu a mai participat la niciun eveniment.", pe
mijloc, ca „Niciun comentariu încă" din tabul de alături.

### Poza de profil se mărește

Apeși pe cerc, poza se deschide peste toată pagina. Fișierul de pe server e
512×512 (`POZA_LATURA`), iar în antetul profilului se vede la 86 px — deci e ce
arăta, nu o mărire pe degeaba. Peste 512 px nu se întinde: ar fi pixeli
inventați.

Un `<button>`, nu un `<img>` cu ascultător pus pe el: se ajunge la el cu tabul,
se apasă cu Enter, iar cititorul de ecran spune ce face. Creionul de schimbare
a pozei rămâne frate cu butonul, nu copil — un link în interiorul unui buton
n-ar fi HTML valid.

Se închide din „×", din Escape, sau apăsând oriunde în afara pozei; pe poză nu,
fiindcă cine apasă pe ea vrea s-o vadă, nu s-o piardă. Cât e deschisă, pagina de
dedesubt nu se mai plimbă, iar la închidere atenția se întoarce pe cerc — cine
merge cu tastatura ar rămâne altfel cu atenția pe un element care tocmai s-a
evaporat.

Fără poză nu se deschide nimic: chipul implicit e un desen, nu un om, și nici
șablonul casetei nu mai ajunge în pagină. Caseta merge peste orice
`[data-mareste="<adresa>"]`, deci nu știe nimic despre profil — dacă mâine se
apasă și pe coperta unui eveniment, tot ea o deschide.

### Ce s-a refolosit

Trei lucruri, și niciunul n-a fost scris a doua oară:

- **Taburile.** Aceeași componentă `[data-tabs]` ca pe pagina evenimentului, cu
  `role="tab"` și `aria-controls`. A venit cu tot cu navigarea din săgeți și cu
  deschiderea din adresă — `profil.php?m=…#panel-istoric` cade drept pe istoric.
- **Cartonașul.** Era scris de-a dreptul în `profil.php`, într-un `foreach`. Cu
  a doua listă pe aceeași pagină ar fi ajuns scris de două ori, iar două bucăți
  de HTML care trebuie să arate la fel încep să difere de la prima corectură.
  Acum e `randeazaCartonasEveniment()`, cu un loc unde cine cheamă funcția poate
  lipi ce are de spus el.
- **„Vezi mai mult".** Era scris pentru evaluări. S-a mutat în
  `[data-descopera]`, fără nimic al lui: numără **copiii listei**, deci merge la
  fel peste `<li class="evaluare">` și peste `<article class="card">`. Câte
  deodată vine din `data-deodata` — 20 la evaluări, 6 la istoric, fiindcă un
  cartonaș cu poză ocupă cât patru păreri scrise.

Funcția de reașezare rămâne agățată de panou (`panou.__descopera`), ca formularul
de evaluare să poată chema ascunsul din nou după ce înlocuiește toată lista.
Câte se văd nu se dă înapoi la prima pagină: cine tocmai a apăsat de trei ori
„Vezi mai mult" n-are de ce să se trezească iar la început.

O grijă mică, dar care s-a mai plătit o dată: `.card` are `display: flex`, care
bate `hidden`-ul browserului. Fără `.card[hidden] { display: none; }`, butonul
n-ar ascunde nimic — exact bug-ul avut la `.comment__replies`.

Verificările: `php teste/test-evaluari.php` (96 de cazuri, cere baza de date,
nu și serverul).


## Mulțumirea de după eveniment

După ce un eveniment s-a încheiat, fiecare om rămas pe lista de participanți
primește **o dată** un e-mail: mulțumim că ai venit, iar dacă vrei, treci pe
pagină și dă câte o stea celorlalți. Butonul din el duce drept la tabul „Au
participat", nu la capul paginii.

`sql/018-multumiri-eveniment.sql`, `inc/multumiri.php`,
`cron/multumeste-participantilor.php`, `emailMultumireParticipare()` din
`inc/email.php`.

### De ce printr-un cron, și nu în clipa încheierii

Fiindcă încheierea se întâmplă în două feluri, iar unul dintre ele nu se
întâmplă nicăieri. Organizatorul poate apăsa „Încheie evenimentul", și atunci
există un moment anume — dar un eveniment se încheie și singur, când îi trece
ziua, iar aceea nu e fapta nimănui, e doar ceasul care merge mai departe. Nu
există nicio cerere de prins, niciun buton apăsat. Singurul loc din care se
poate observa e ceva care trece din când în când și se uită.

Și dacă tot trebuie un cron pentru al doilea fel, îl lăsăm să le facă pe
amândouă. Altfel același mesaj ar pleca din două locuri, cu două feluri de a
socoti cine îl primește, și s-ar despărți la prima corectură. Organizatorul
care apasă butonul nu trimite nimic — doar schimbă starea, iar cronul vede la
următoarea trecere.

```
php /home/UTILIZATOR/public_html/cron/multumeste-participantilor.php
```

**Din oră în oră.** Nu o dată pe zi ca la anonimizare: acolo se aștepta un
răgaz de treizeci de zile, deci o zi în plus nu însemna nimic. Aici omul tocmai
s-a întors acasă, iar o mulțumire care vine a doua zi seara n-are aceeași
căldură. Nici mai des n-are rost.

Pentru încercare, fără să trimită nimic și fără să atingă baza:

```
php cron/multumeste-participantilor.php --uscat
```

### Cum se ține minte că au plecat

Într-o coloană, `evenimente.multumiri_trimise_la`. Fără ea, cronul n-ar avea
cum să deosebească un eveniment încheiat ieri **cu** mesajele trimise de unul
încheiat ieri **fără** ele: le-ar trimite din nou la fiecare rulare, din oră în
oră, pentru totdeauna.

Semnul se pune și când n-a plecat nimic — la un eveniment fără oameni, sau
unde toate încercările au picat. Altfel rândul acela ar fi cercetat la
nesfârșit, iar o adresă care nu primește azi n-o să primească nici la a suta
încercare.

Se pune **după** trimitere, nu înainte. Dacă scriptul cade la jumătate, cei
dinaintea căderii primesc mesajul de două ori la următoarea rulare. E partea
nefericită a alegerii — dar cealaltă ar face ca o cădere să lase pe cineva
fără mesaj, definitiv. Dintre „încă o dată" și „niciodată", prima.

Migrarea închide trecutul dintr-o dată: tot ce e deja încheiat în clipa
importului primește semnul, fără să plece nimic. Fără rândul acela, prima
rulare ar trimite oamenilor mulțumiri pentru o seară de acum jumătate de an.

### Cine primește

Cine e pe lista de **participanți**, cu contul activ. Nu și cei care s-au
arătat doar interesați: n-au fost acolo. Nu și cine a fost scos de pe listă —
el a primit deja alt mesaj, cel cu motivul, și ar fi de prost gust să-i vină
după aceea și „mulțumim că ai fost".

Organizatorul e și el pe listă, ca oricare altul, dar primește alt prim
paragraf: lui nu i se mulțumește că a venit, ci că l-a ținut. Tot lui i se
amintește că poate însemna cine a confirmat și n-a mai ajuns.

**Sub doi oameni nu pleacă nimic.** Mesajul e, în cea mai mare parte, o
invitație la note, iar notele se dau între oameni: la un eveniment ținut de
cineva singur ar fi o scrisoare care te trimite pe o pagină unde nu e nimeni.

Bifa de newsletter din setări nu se citește aici. Aceea e pentru „e-mail cu
evenimente noi", adică pentru ce n-a cerut nimeni anume; mesajul de față vine
după o seară la care omul s-a înscris el însuși. Un mesaj despre ceva ce ai
făcut tu nu e reclamă.

### Linkul care deschide tabul

Butonul duce la `event.php?slug=…#panel-going`. Un panou închis are `hidden`,
deci browserul nu poate sări la el singur — omul ar ateriza pe pagină și ar
rămâne tot la comentarii. De aceea componenta de taburi din `main.js` citește
diezul, deschide tabul potrivit și mută ecranul până la el; pe telefon aduce și
tabul pe mijlocul rândului, fiindcă la 390px nu încap toate trei.

Cât de sus se oprește pagina se scrie în CSS, `scroll-margin-top` pe `.panel`,
nu în JS: browserul încearcă și el să sară singur la elementul din adresă, iar
încercarea lui vine după a noastră. Cu regula în CSS, amândoi se opresc în
același loc — sub antet și sub rândul de taburi, ca să se vadă pe care tab a
nimerit.

Se potrivește pe id-ul panoului, care e și ce scrie în `aria-controls`. E
scris în componenta de taburi, nu în pagina evenimentului, deci de acum se
poate lega așa orice tab de pe site.

Ce s-a trimis se scrie în `private/multumiri-trimise.log`, câte un rând pe
eveniment. Rândul de încheiere doar când chiar a plecat ceva — altfel un cron
orar ar umple fișierul cu 8760 de rânduri pe an care spun „n-am avut ce face".

Verificările: `php teste/test-multumiri.php` (34 de cazuri, cere baza de date,
nu și serverul; mesajele nu pleacă nicăieri cât `dezvoltare => true`).


## E-mailurile

Fișier: `inc/email.php` — un singur șablon pentru toate mesajele, exact ca la
CSS: dacă se schimbă culoarea sau subsolul, se schimbă peste tot dintr-un loc.

Se trimit: confirmarea adresei (la înregistrare și la retrimitere), bun venit,
parola temporară, înștiințarea că parola a fost schimbată, cele două despre
ștergerea contului, mesajul de contact, vestea că cineva a fost scos de pe
lista unui eveniment, mulțumirea de după eveniment, vestea că un eveniment s-a
anulat, hotărârea moderării, și înștiințarea că cineva a comentat sau a
răspuns.

Blocurile din care se compune un mesaj (`sablonEmail`): `salut`, `paragrafe`,
`citat`, `cod`, `buton`, `link_gol`, `atentie`, `incheiere`. `citat` e cutia cu
dungă în stânga în care intră vorbele altcuiva — textul unui comentariu, de
pildă; în varianta de text simplu se scrie cu „> " la începutul fiecărui rând,
cum se citează de când e e-mailul.

### De ce e-mailul se scrie altfel decât o pagină

Programele de e-mail sunt cu douăzeci de ani în urma browserelor, iar fiecare
taie altceva din HTML. De aici regulile care în orice altă parte a proiectului
ar fi greșeli:

- **așezarea se face cu `<table>`**, nu cu flexbox sau grid — Outlook pe Windows
  randează prin motorul lui Word, care nu știe nici măcar `float`;
- **stilurile se scriu în atributul `style`** al fiecărui element, fiindcă Gmail
  taie din `<style>` tot ce nu-i place;
- **lățime maximă 600px**, cât încape în panoul de previzualizare;
- **fonturi de sistem** — unul descărcat nu se încarcă nicăieri.

**Fără nicio imagine**, cum ai cerut — și e alegerea bună oricum: aproape toți
furnizorii blochează imaginile până când omul apasă „afișează imaginile", iar un
mesaj construit din imagini ajunge atunci un dreptunghi gol. Aici tot ce se vede
e text și chenare colorate.

Mesajul pleacă în **două variante deodată** (`multipart/alternative`): text
simplu și HTML. Varianta text nu e o formalitate — o citesc ceasurile
inteligente și cititoarele de ecran, iar lipsa ei e unul dintre semnele după
care filtrele de spam pun mesajele deoparte.

Se declară și `color-scheme: light`, ca Apple Mail și Outlook să nu răstoarne
singure culorile pe tema întunecată și să iasă negru pe negru.

### Ca să nu ajungă în „Spam"

- `email_expeditor` din `config.php` **trebuie să fie pe domeniul tău**. Cu o
  adresă de gmail.com, mesajele ajung aproape sigur la spam: serverul care le
  trimite nu are voie să trimită în numele Gmail, iar verificarea SPF observă.
- Al cincilea parametru al lui `mail()` (`-f`) pune adresa și în „plicul"
  mesajului, nu doar în antetul `From`. Fără el, serverul trimite de pe adresa
  contului de găzduire, iar nepotrivirea aia e fix ce caută SPF.
- În panoul găzduirii, verifică să existe **SPF** și **DKIM** pentru domeniu.
  De obicei se pun singure când adaugi domeniul; dacă nu, e o setare din zona
  de e-mail sau de DNS.

### Injecția în anteturi

Anteturile unui e-mail se despart prin rânduri noi, deci o adresă care conține
un rând nou poate adăuga anteturi inventate — de pildă un `Bcc:` către altcineva.
Așa un site devine, fără să știe, unealtă de trimis spam.

`esteAdresaSigura()` respinge orice adresă cu `\r`, `\n`, tab sau caractere de
control, iar textele care ajung în anteturi trec prin `mimeNume()`. Testat cu
trei feluri de adrese otrăvite: niciuna nu produce vreun mesaj.

### În XAMPP, unde nu există server de e-mail

`'email_metoda' => 'auto'` (implicit) scrie mesajele în
`private/emailuri-trimise.log`, iar ultimul și în `private/ultimul-email.html`,
ca să-i poți deschide aspectul în browser. Nimic nu pleacă nicăieri.

## Parola uitată

Fișiere: `parola-uitata.php`, `parola-noua.php`, `api/parola-uitata.php`,
`api/parola-noua.php`, `sql/004-parola-uitata.sql`.

### Cum merge

1. Omul scrie adresa pe `parola-uitata.php`.
2. Primește pe e-mail o parolă de **șase caractere**, valabilă **60 de minute**
   și bună **o singură dată**.
3. Intră cu ea pe `login.php`, ca și cum ar fi parola obișnuită.
4. E dus direct la `parola-noua.php` și **nu poate face nimic altceva** până nu
   își alege o parolă nouă.

Parola veche rămâne bună tot timpul ăsta: cea temporară e o intrare în plus, nu
un înlocuitor. Abia la pasul 4 se schimbă ceva.

### De ce e obligatorie schimbarea

Fără ea, omul ar rămâne în cont cu parola veche — cea uitată — tot acolo, și
data viitoare ar fi din nou pe dinafară. În plus, o parolă trimisă prin e-mail a
trecut prin prea multe mâini ca să rămână singura cheie a contului.

Oprirea se face din `inc/antet.php`, deci acoperă toate paginile dintr-un
singur loc și nu poate fi uitată la una nouă. Punctele din `api/` nu trec pe
acolo, așa că își cheamă singure `opresteDacaTrebuieParolaNoua(true)`. Rămân
deschise doar `parola-noua.php` și `iesire.php` — fără ele omul ar fi blocat.

### Alfabetul parolei

`ABCDEFGHJKLMNPQRSTUVWXYZ23456789` — 32 de caractere. Lipsesc **0 și O, 1 și I**:
parola se citește dintr-un e-mail și se tastează de mână, iar alea sunt
perechile care se confundă. Aceeași regulă ca la permalink.

32⁶ înseamnă peste un miliard de combinații.

### Ce o apără, în afară de cele 60 de minute

Timpul singur nu spune nimic despre cât de repede poate cineva să încerce. De
aceea există și un **contor de greșeli**: la a cincea încercare ratată, parola
temporară se șterge singură din bază. Ghicitul devine imposibil, indiferent câte
calculatoare are cel care încearcă.

Peste asta se adaugă:

- se ține **hashuită**, ca parola adevărată. Dacă baza ajunge pe mâini străine,
  o coloană cu parole în clar ar însemna intrare imediată în toate conturile
  care au cerut recuperare în ultima oră;
- **o cerere la 10 minute** per cont, ca nimeni să nu poată umple cutia poștală
  a altcuiva;
- **cel mult 10 cereri pe oră de la același IP** — limita asta se verifică
  *înainte* de a căuta adresa în bază, altfel cine încearcă o mie de adrese
  străine n-ar fi oprit de nimic;
- răspunsul e **același pentru orice adresă**, existentă sau nu, ca butonul să
  nu devină o unealtă de aflat cine e înscris pe site;
- un cont **neconfirmat** nu primește parolă temporară — întâi își confirmă
  adresa;
- „ține-mă minte" e ignorat la intrarea cu parolă temporară: sesiunea aia
  trebuie să dureze cât îi ia omului să-și pună o parolă nouă, nu o lună;
- la schimbarea parolei se face `session_regenerate_id()` și pleacă un e-mail de
  înștiințare — așa afli dacă altcineva ți-a luat contul.

## Poza de profil

Fișiere: `poza.php` (pagina), `api/poza-profil.php` (primirea),
`inc/imagini.php` (toate verificările și prelucrarea),
`assets/img/membri/` (unde ajung pozele), `sql/003-poza-profil.sql`.

### Principiul de bază

**Fișierul primit nu e salvat niciodată așa cum a venit.** Îl citim, îl
desenăm din nou pixel cu pixel și scriem un JPEG nou, făcut de noi.

De aici vin, dintr-o singură mișcare, aproape toate protecțiile:

- un fișier care se dă drept poză, dar are cod PHP lipit la coadă, își pierde
  acel cod — noi copiem doar pixelii;
- **datele EXIF dispar**, inclusiv coordonatele GPS pe care telefoanele le pun
  în fotografii. Altfel ar fi fost publicate odată cu poza, fără ca cineva să
  bănuiască;
- numele și extensia alese de utilizator nu ajung niciodată pe disc.

### Ce se acceptă

**JPG, PNG și WEBP**, nu doar JPG. Extensia nu contează, pentru că oricum n-o
folosim: singura întrebare e „putem citi corect pixelii?". Iar dacă răspunsul e
da, a trimite omul înapoi să-și convertească un PNG (cum sunt toate capturile
de ecran de pe Android și Windows) ar fi o piedică fără niciun câștig.

GIF-ul lipsește intenționat: e animat și cu paletă de 256 de culori.

### Verificările, în ordine

1. codul de eroare al încărcării (inclusiv „prea mare pentru `post_max_size`",
   caz în care `$_POST` ajunge gol și token-ul CSRF pare că lipsește — e tratat
   separat, altfel omul ar primi „sesiunea a expirat" pentru o poză prea mare);
2. `is_uploaded_file()` — fișierul chiar a venit prin formular, nu e o cale de
   pe server strecurată în cerere;
3. mărimea: cel mult 6 MB;
4. `getimagesize()` — se uită la primii octeți, nu la extensie;
5. `finfo` — a doua părere, de la altă bibliotecă;
6. numărul de pixeli: cel mult 40 de megapixeli și nicio latură peste 12000.
   **Nu mărimea fișierului e problema, ci pixelii**: un PNG de doi kilobiți
   poate declara 30000×30000 și ar cere peste 3 GB de memorie la desenare;
7. cel puțin 200×200, altfel ar ieși o poză neclară;
8. cât mai e liber din `memory_limit`.

### Mărimile salvate

Din fiecare poză ies două fișiere pătrate:

| fișier          | latura  | unde se folosește           |
|-----------------|---------|-----------------------------|
| `<nume>.jpg`     | 512 px  | pagina de profil            |
| `<nume>-mic.jpg` | 128 px  | bara de meniu, comentarii   |

Calitate JPEG 82 și mod progresiv. Ies în jur de 20–40 kB pentru cea mare.
Nu mărim niciodată peste ce a dat omul: dintr-un decupaj de 300 px iese o poză
de 300 px, nu una de 512 întinsă și moale.

### Numele fișierului

32 de caractere hexazecimale, aleatorii, fără nicio legătură cu membrul. În
baza de date se ține doar partea asta; căile se construiesc în `urlPoza()`.

Trei motive: nu poate fi ghicit (cu id-ul în nume, oricine ar putea cere pe
rând toate pozele de pe site), se schimbă la fiecare încărcare (deci browserul
nu mai servește poza veche din cache), iar numele ales de utilizator nu ajunge
niciodată pe disc — acolo poate fi orice, inclusiv `..\..\index.php`.

### Decuparea

Utilizatorul își potrivește singur poza: o trage cu degetul sau cu mausul, o
mărește din bară, din rotița mausului sau apropiind două degete. Cercul arată
exact cât va intra în poza finală.

**Poza tăiată nu se trimite.** Se trimite fișierul original plus trei numere:
colțul din stânga-sus și latura pătratului. Serverul taie el. Dacă am trimite
imaginea gata tăiată din JavaScript, ne-am baza pe ea — și am primi ce vrea cel
de la tastatură, nu ce am cerut noi.

Numerele primite sunt potrivite, nu crezute: `potrivesteDecupajul()` le aduce
în limitele imaginii. Fără ele (sau fără JavaScript) se decupează din mijloc.

### Dosarul cu poze

`assets/img/membri/` are un `.htaccess` care:

- oprește interpretarea codului, în ambele feluri în care poate rula PHP
  (`php_flag engine off` pentru mod_php, `RemoveHandler` pentru CGI și FPM);
- servește **doar** fișierele cu numele dat de noi — 32 de caractere hex,
  eventual `-mic`, apoi `.jpg`. Orice altceva e refuzat;
- trimite `X-Content-Type-Options: nosniff` și cache de un an (numele fiind
  nou de fiecare dată, poza veche nu mai e cerută niciodată).

Pozele nu intră în depozitul de cod: `.gitignore` din dosar le lasă afară.

### Alte măsuri

- se schimbă **doar poza contului conectat**; nu există niciun parametru prin
  care să se spună al cui profil se modifică;
- token CSRF la fiecare cerere;
- cel mult o schimbare la 15 secunde — redimensionarea costă timp de procesor;
- poza veche se șterge **după** ce cea nouă e sigur în bază. În ordine inversă,
  o eroare la scriere ar lăsa omul fără nicio poză;
- dacă scrierea în bază dă greș, fișierele proaspăt create se șterg, ca să nu
  rămână gunoi pe disc;
- transparența devine albă înainte de a fi scrisă ca JPEG, care nu știe de ea
  (altfel fundalul ar ieși negru);
- confirmarea la ștergere e desenată de noi, nu `window.confirm()` — vezi
  regula despre aspectul nativ, mai jos.

## Convenții

### Golirea cache-ului la actualizări

Legăturile către CSS și JS au un număr de versiune, puse o singură dată în
`inc/antet.php` și `inc/subsol.php`:

```html
<link rel="stylesheet" href="assets/css/style.css?v=16">
<script src="assets/js/main.js?v=16"></script>
```

**De fiecare dată când modifici `style.css` sau `main.js`, crește numărul.**
Altfel browserele păstrează versiunea veche din cache, iar paginile noi apar
nestilate — HTML-ul e nou, dar CSS-ul rămâne cel vechi.

Comandă rapidă (înlocuiește 17 cu versiunea nouă):

```bash
sed -i 's/style\.css?v=[0-9]*/style.css?v=17/' inc/antet.php
sed -i 's/main\.js?v=[0-9]*/main.js?v=17/'     inc/subsol.php
```

### Un singur CSS, un singur JS

Tot site-ul folosește `assets/css/style.css` și `assets/js/main.js`. Nu există
stiluri per pagină — nici `meniu-index`, nici `footer-contact`. Meniul, footerul,
butoanele, câmpurile și cardurile sunt aceleași clase peste tot, iar paginile
diferă doar prin conținutul dintre ele.

Concret: dacă schimbi înălțimea barii de meniu, se schimbă pe toate paginile
dintr-un singur loc. Când adaugi o pagină nouă, copiezi antetul și footerul din
oricare pagină existentă și nu scrii CSS nou decât dacă apare o componentă care
chiar nu există încă.

Valorile comune stau în variabilele de la începutul fișierului: `--wrap`
(lățimea conținutului), `--header-h`, `--field-h`, `--radius`, culorile.
Schimbă acolo, nu în componente.

### Nu ne bazăm pe aspectul nativ al controalelor

`select`, `input[type="date"]`, bifele și butoanele primesc `appearance: none`
și decor desenat de noi, pentru că fiecare browser le desenează altfel (vezi
cazul câmpului de dată pe Android). Orice control nou trebuie tratat la fel.

### Aceeași interfață pe iPhone și pe Android

Secțiunea 19 din `style.css` adună, într-un singur loc, diferențele pe care le
introduc browserele de la ele:

- **Toate câmpurile au textul de 16px.** Sub acest prag, Safari pe iPhone
  mărește automat pagina la atingerea unui câmp, iar pe Android nu — ecranul ar
  „sări" doar pe iPhone. Dacă adaugi un câmp nou, respectă pragul.
- **Fără decor nativ** pe inputuri și butoane (iOS pune singur colțuri rotunjite
  și umbre interioare).
- **`text-size-adjust: 100%`**, altfel Safari îngroașă textul la rotirea ecranului.
- **`font-synthesis: none`**, ca literele îngroșate să nu fie simulate diferit
  de la un sistem la altul cât timp fontul încă se încarcă.
- **Fără dreptunghiul gri la atingere** (`-webkit-tap-highlight-color`).
- **Zona sigură** pe telefoanele cu decupaj, prin `env(safe-area-inset-*)`.
- **`line-height` fixat** pe butoane, pe care Safari altfel îl ignoră.
- **Fundalul galben de la completarea automată** din Chrome, înlocuit cu
  culorile temei.

Ce nu se poate controla din CSS: formatul afișat în câmpul de dată
(`zz.ll.aaaa` vs `mm/dd/yyyy`) depinde de limba dispozitivului.

## Mutarea pe găzduire

### Pașii, în ordine

1. **Urci fișierele pe FTP.**

   **Pornește întâi afișarea fișierelor ascunse în programul de FTP.** Fișierele
   care încep cu punct — `.htaccess` — sunt ascunse implicit în FileZilla,
   Total Commander și în majoritatea celorlalte. Dacă nu se urcă, dosarul `inc/`
   rămâne deschis, iar `inc/config.php` se poate descărca de oricine, cu parola
   bazei de date în el. În FileZilla: *Server → Force showing hidden files*.

   Sunt **șapte** fișiere `.htaccess`: unul în rădăcină și câte unul în `inc/`,
   `sql/`, `cron/`, `private/`, `assets/img/membri/` și
   `assets/img/evenimente/`. Cele șase din dosare închid; cel din rădăcină nu
   închide nimic — trece site-ul pe https, împachetează textul la trimitere și
   spune browserului ce are voie să țină minte (vezi mai jos).

2. **Faci baza de date din panoul găzduirii** (cPanel → MySQL Databases), apoi
   un utilizator, apoi **îl legi de bază** — „Add User To Database", cu toate
   drepturile bifate. Legarea e un pas separat și se uită des; fără ea, PHP
   primește „Access denied ... to database".

   Numele bazei și al utilizatorului primesc automat un prefix, de forma
   `numecont_db`. Se folosesc întregi, cu prefix cu tot.

3. **Importi `sql/schema.sql`** în phpMyAdmin: alegi întâi baza din lista din
   stânga, abia apoi „Import". Fără pasul cu alegerea bazei, importul nu are
   unde să scrie.

   Apoi **migrările, în ordinea numerelor**, de la `002` până la ultima. Sunt
   bucăți care adaugă coloane și tabele apărute după prima schemă; sărită una,
   pagina care are nevoie de ea dă eroare, iar celelalte merg — deci se vede
   greu. Ultima de azi e `sql/031-newsletter-zilnic.sql`.

4. **Faci `inc/config.php` pe server**, pornind de la `inc/config.example.php`.

   Fișierul e trecut în `.gitignore` tocmai ca parolele să nu ajungă pe GitHub —
   ceea ce înseamnă și că **nu se copiază odată cu restul codului**. E cauza cea
   mai frecventă a mesajului „nu am putut lua legătura cu serverul" imediat după
   mutare.

5. **Pui `'dezvoltare' => false`.** Cât timp e `true`, oricine cere o
   înregistrare primește înapoi linkul de confirmare, deci poate activa conturi
   pe adrese care nu sunt ale lui.

6. **Pui `'url_site' => 'https://domeniul-tau.ro'`**, fără bară la sfârșit. Din
   el se construiesc linkurile de confirmare.

7. **Pui `'email_expeditor'` pe o adresă de pe domeniul tău.** Vezi secțiunea
   despre e-mailuri pentru ce contează la livrare.

8. **Dacă vrei intrarea cu Google**, urmează pașii din secțiunea „Intrarea cu
   Google". Până atunci butoanele nici nu se tipăresc, deci nu e grabă.

### `.htaccess`-ul din rădăcină

Celelalte șase închid dosare. Ăsta nu închide nimic: face site-ul să se
deschidă mai repede și îl ține pe https.

**https, mereu.** Toate cookie-urile de pe site primesc steagul `secure` când
cererea vine pe https (`pornesteSesiunea()`). Pe http pleacă în clar — deci nu e
de ajuns că https *merge*, trebuie să fie singurul drum. Redirecționarea
întreabă și de `X-Forwarded-Proto`, fiindcă multe găzduiri termină certificatul
într-un proxy și trimit mai departe pe http; fără întrebarea aia s-ar fi făcut o
buclă fără sfârșit.

**Textul pleacă împachetat.** `style.css` are vreo 180 KB și `main.js` vreo
230 KB — patru sute și ceva de kilobiți pe care fiecare om îi descarcă la prima
vizită. Împachetate (`mod_deflate`), rămân sub o sută. Pe un telefon cu semnal
slab, asta e diferența dintre „s-a deschis" și „nu merge". Pozele nu se
împachetează: JPEG-ul e deja împachetat, iar a doua trecere ar da un fișier mai
mare.

**Ce nu se schimbă, se ține minte.** CSS-ul și JS-ul se cer cu `?v=88` la coadă,
iar numărul crește la fiecare modificare (regula 2 din CLAUDE.md) — adresa fiind
alta, browserul aduce fișierul nou oricum. De aceea au voie să fie ținute minte
un an, cu `immutable`. **HTML-ul, niciodată:** el poartă numărul de versiune al
celorlalte, iar ținut minte ar fi cerut mai departe fișierele vechi. Și mai e un
motiv: cine iese din cont și apasă „înapoi" ar vedea pagina dinainte, cu numele
lui pe ea, luată din memoria browserului.

**Ce nu se descarcă.** Fișierele răzlețe care ar putea ajunge pe server dintr-o
scăpare: o copie de siguranță, un `.env`, dosarul `.git` dacă site-ul s-a pus
vreodată prin `git clone` în loc de FTP. `.git/` are TOT codul și toată istoria
lui, inclusiv fișiere șterse cândva, și se descarcă fișier cu fișier cu unelte
care se găsesc de-a gata.

**Tot ce e în el e învelit în `<IfModule>`.** Dacă găzduirea n-are modulul,
bucata se sare în tăcere în loc să dea „500" pe tot site-ul. Și totuși, dacă
după ce-l urci site-ul dă 500: **îl redenumești în `htaccess.txt` prin FTP** și
se întoarce cum era. Nimic din el nu e necesar ca site-ul să funcționeze.

### Gazda bazei de date

**`localhost`**, nu numele domeniului. Baza stă pe aceeași mașină cu site-ul,
deci se ajunge la ea din interior. Cu domeniul, PHP iese în internet și se
întoarce la server din afară — lucru pe care aproape toate găzduirile îl
blochează, tocmai din motive de siguranță. Se vede ca „Connection timed out"
sau „Connection refused".

Portul e 3306 și nu se schimbă decât dacă găzduirea spune explicit altul.

### Când ceva nu merge: `verifica.php`

Urci `verifica.php`, pui în `inc/config.php` o cheie lungă la
`'cheie_diagnostic'` și deschizi:

```
https://domeniul-tau.ro/verifica.php?cheie=CHEIA_PUSĂ
```

Îți spune, pe rând: versiunea PHP și extensiile, dacă setările sunt citite,
dacă baza răspunde (și **ce anume** e greșit, tradus din eroarea MySQL), dacă
tabelele există și au toate coloanele, dacă se poate scrie în dosare, dacă
`inc/` chiar e închis din web.

**Șterge fișierul de pe server după ce ai terminat.** Fără cheie nu spune
nimic, dar un instrument de diagnostic n-are de ce să stea permanent la vedere.

### Ce se vede când serverul e stricat

Toate formularele trimit cu `fetch` și așteaptă JSON. Când primesc altceva —
o eroare de PHP, o pagină de la găzduire, setări lipsă — `citesteRaspuns()` din
`main.js` deosebește trei cazuri:

| ce s-a întâmplat | ce vede omul |
|---|---|
| serverul a răspuns cu JSON | mesajul din JSON |
| serverul a răspuns cu altceva | „Serverul a răspuns cu o eroare (HTTP 500)…", iar textul întreg în consolă |
| cererea n-a plecat deloc | „Verifică internetul…" |

Distincția contează: până acum, orice răspuns care nu era JSON primea
„verifică conexiunea" — un sfat greșit, care trimitea căutarea unde nu e.
Textul brut se scrie doar în consolă, nu în pagină: poate conține căi de pe
server și nume de tabele.

## De făcut mai departe

Paginile de categorie, și paginile de administrare care lipsesc: lista
anunțurilor care așteaptă aprobarea, lista comentariilor raportate
(`comentarii_rapoarte` se umple, dar nu se citește de nicăieri) și moderarea
notelor. Moderarea anunțurilor în sine există, dar se face de pe pagina fiecărui
anunț, cu adresa în mână.


## Site închis pentru lucrări

`in_constructie` din `inc/config.php` pus pe `true` închide site-ul: oricine
intră vede un afiș pe ușă și atât. Se ia înapoi punându-l pe `false` — nimic
altceva, nicio bază de schimbat, niciun fișier de mutat.

`inc/constructie.php` (regula), `constructie.php` (afișul), `api/newsletter.php`,
`sql/019-newsletter.sql`.

### Nu e o ascundere de ochi

Nu se ascund doar paginile, se opresc și API-urile din spatele lor. Altfel
oricine ar fi putut citi lista de evenimente cu un `curl`, sau și-ar fi făcut
cont, cât timp pe ecran scria „ne pregătim". O ușă închisă doar la vedere nu e
o ușă închisă.

Paginile primesc o redirecționare spre afiș; API-urile primesc **503** în JSON,
fiindcă un `fetch` care se trezește cu HTML în loc de răspuns arată pe ecran
„ceva n-a mers", fără să spună ce. Iar 503 e cuvântul potrivit: nu „n-ai voie",
ci „nu acum".

### Lacătul stă într-un singur loc

La **coada lui `inc/auth.php`**, nu în fiecare pagină. O listă de pagini care
trebuie să-și pună singure lacătul e o listă din care lipsește mereu una — cea
scrisă mâine. Așa, orice fișier care are de-a face cu un om conectat trece pe
acolo fără să știe, iar cine adaugă o pagină nouă n-are ce uita.

La coadă, nu la început: până acolo trebuie să fie deja definite `esteStaff()`
și `membruCurent()`, de care atârnă întrebarea „are voie înăuntru?".

Un singur API scăpa: `api/inregistrare.php`, singurul care nu cerea `auth.php`
— tocmai fiindcă înregistrarea o face cine n-are cont. Acum îl cere, pentru
lacăt. Era exact gaura prin care se puteau face conturi cu site-ul închis.

Din **linia de comandă** nu se pune niciun lacăt: cronurile și testele nu sunt
vizitatori, sunt casa însăși.

### Ușile care rămân deschise

`constructie.php`, `login.php`, `api/autentificare.php`, `api/newsletter.php` și
`iesire.php`. Ultima e acolo ca nimeni să nu rămână închis înăuntru: cine era
conectat în clipa în care s-a pus lacătul trebuie să poată ieși din cont.

Potrivirea se face pe **fișierul de pe disc**, adus la forma lui adevărată cu
`realpath()`, nu pe adresa cerută. E singurul fel în care lista chiar înseamnă
ceva: o comparație pe URL ar fi trebuit să se apere singură de
`/api/../index.php`, de bara în plus și de literele mari — adică exact felul de
verificare scrisă de mână care lasă mereu ceva să treacă.

`google.php` e și el pe listă, și trebuie să fie: omul de casă care și-a făcut
contul cu Google **n-are parolă la noi**, deci fără ușa aia n-ar avea pe unde
intra deloc. Drumul lui e deschis până unde se *intră*; de unde încolo s-ar
face un cont nou — `finalizare.php` și API-ul ei — rămâne închis, iar
`google.php` se oprește singur când vede că omul întors de la Google n-are încă
un cont aici. Așa, cele două pagini nici nu trebuie ținute deschise: la ele nu
se mai ajunge.

Ce se scoate din `login.php` e doar tabul de înregistrare: un formular care se
vede și nu merge e mai supărător decât unul care lipsește.

### Cine nu e staff nu apucă să se conecteze

Verificarea stă în `api/autentificare.php`, **înainte** de `autentifica()`,
tocmai ca sesiunea să nu se facă deloc. Lăsată pe urmă — sau, mai rău, doar pe
lacătul de la intrarea în pagini — omul ar fi rămas conectat în spatele unui
site închis: cu cont, cu cookie, cu tot, dar fără nicio pagină la care să
ajungă. Așa nu se întâmplă nimic: e ca și cum n-ar fi apăsat.

Nu se numără ca încercare greșită. Parola a fost bună, iar omul n-a făcut nimic
rău — ar fi fost cel mai nedrept fel de blocare: trei intrări corecte și contul
încuiat zece minute, fiindcă site-ul e în lucru.

### „E om de casă?" nu se mai ghicește

Interogarea de la intrare nu aducea `este_staff`, iar `esteStaff()` lua lipsa
coloanei drept „nu e staff" și tăcea. Cu lacătul pus, tăcerea aia a devenit o
ușă închisă în nasul omului de casă: parolă bună, cont bun, dar tratat ca un
vizitator oarecare. Aceleași trei interogări din `google.php` aveau aceeași
lipsă.

Acum `esteStaff()` nu mai ghicește: dacă rândul primit n-are coloana, o citește
din bază după `id`. E o citire în plus, pe un drum care oricum tocmai a făcut
câteva, și scapă odată pentru totdeauna de întrebarea „am pus coloana în
SELECT-ul ăsta?".

Staff înseamnă **orice valoare în afară de 0**, nu „= 1". Coloana se pune de
mână, din phpMyAdmin, iar cine scrie acolo un 2 pentru „administrator" se
așteaptă să fie tot om de casă, nu să cadă înapoi printre vizitatori fără să
afle de ce.

### Formularul de intrare trimite prin POST

Tot blocul de JavaScript al paginii de cont atârna de existența **taburilor**.
Când pagina a rămas fără ele — cât e site-ul în lucru se arată doar
autentificarea — nimic dinăuntru nu s-a mai legat, nici formularul de login.

Iar un formular fără JavaScript legat de el nu stă degeaba: browserul îl trimite
el, cum știe. Cum n-avea `method`, îl trimitea prin **GET** — adică parola
ajungea în bara de adrese, și de acolo în istoric, în logurile serverului și în
`Referer`. Pe ecran „nu se întâmpla nimic", fiindcă pagina se reîncărca la fel.

Două lucruri s-au schimbat: blocul se leagă acum dacă există *ori* taburile,
*ori* formularul de intrare; iar amândouă formularele au primit `method="post"`,
ca plasă de sub ele. Cel mai rău caz e o pagină reîncărcată degeaba.

### Afișul

Singura pagină a site-ului **fără antet și fără subsol**. Antetul aduce cu el
meniul întreg — Acasă, Despre, Contact — adică exact paginile închise în clipa
aceea. Un „revenim curând" cu un meniu din care nu merge nimic e mai rău decât
niciun meniu: omul apasă, ajunge înapoi la afiș și crede că s-a stricat ceva.

Rămân comune cele care contează: `style.css` și `main.js`, ca peste tot.

O fotografie de amurg peste tot ecranul, blurată. Poza stă în elementul ei, nu
ca `background-image` pe `body`: un blur pus pe fundalul paginii ar fi tras
după el și textul. E crescută cu 40 de puncte peste marginile ecranului, ca
dunga spălăcită pe care blurul o lasă pe margini să cadă în afara lui.

Pagina e mereu întunecată — `data-theme="dark"` scris de-a dreptul în `<html>`.
Pe alb, o fotografie de noapte cu text alb peste ea n-ar avea ce să însemne,
iar butonul de temă nici nu ajunge în pagină.

Răspunde cu **503** și `Retry-After`. Cu 200, Google ar fi indexat „ne
pregătim" ca fiind pagina noastră de start și ar fi ținut-o așa săptămâni după
deschidere.

Cu site-ul deschis, afișul se redirecționează singur spre prima pagină — altfel
ar fi rămas la adresa lui pentru totdeauna, iar cine îl deschidea dintr-un
bookmark ar fi crezut că încă n-am pornit. La fel pentru omul de casă: pentru
el site-ul e deschis, deci afișul nu-l privește.

### Adresele celor care așteaptă

Un singur câmp și un buton. `abonati_newsletter` nu are legătură cu `membri`:
cine se înscrie acolo n-are cont și nici n-ar putea să-și facă unul.

A doua înscriere cu aceeași adresă arată **exact ca prima**. Nu fiindcă n-am
ști — cheia unică din bază o știe — ci fiindcă un „ești deja înscris" ar fi
făcut din formular un loc unde oricine află, adresă cu adresă, cine e pe listă.
Unicitatea o ține baza, prin `ON DUPLICATE KEY UPDATE id = id`, nu o întrebare
pusă înainte, pe lângă care două apăsări în aceeași clipă ar trece amândouă.

Verificarea adresei, limita pe IP și scrierea stau într-o singură funcție,
`inscrieLaVesti()`, fiindcă o cer două uși: API-ul, când pagina are JavaScript,
și `constructie.php` însuși, când n-are. Formularul are `method="post"` și un
capăt adevărat care îl primește — nu doar un atribut scris în HTML.

Regula adresei de e-mail s-a mutat în `verificaEmail()`. O aveau scrisă la fel
înregistrarea și formularul de contact; a treia copie ar fi însemnat că într-o
zi o adresă trece pe undeva și e refuzată în altă parte.

Verificările: `php teste/test-constructie.php http://127.0.0.1:8128` (85 de
cazuri; fără adresă merge și fără server, 45). Partea cu serverul pornește și
oprește `in_constructie` din `config.php` și îl pune la loc cum l-a găsit — și
dacă pică ceva la mijloc. După fiecare schimbare **așteaptă până când serverul
chiar o vede**: PHP ține fișierele compilate în OPcache și se uită dacă s-au
schimbat pe disc doar din două în două secunde, iar proba pica pe un lacăt care
era pus, dar nu apucase să se audă.


## Vestea că un eveniment s-a anulat

Un eveniment anulat nu se mai vede de nimeni în afară de staff. Până acum,
oamenii care își făcuseră planuri pentru seara aceea aflau asta singuri —
intrând pe o pagină care nu se mai deschidea. Acum primesc un e-mail în clipa
în care organizatorul apasă „Anulează".

`oameniiDeInstiintatLaAnulare()` din `inc/interese.php`,
`emailAnulareEveniment()` din `inc/email.php`, trimiterea în
`api/anuleaza-eveniment.php`.

### Până când se poate anula

**Până la o oră DUPĂ ora de început** (`MINUTE_ANULARE_DUPA_INCEPUT`). După
ceasul acela butonul dispare de pe amândouă paginile care îl au, iar
`api/anuleaza-eveniment.php` răspunde **409**.

Ora aceea nu e o cifră rotundă aleasă la întâmplare: e răstimpul în care se
hotărăște, de fapt, dacă o ieșire are loc. Plouă cu găleata, au venit doi din
doisprezece, s-a închis terasa — toate se văd **de la** ora scrisă în anunț, nu
înainte de ea. O anulare oprită fix la minutul de început îl lăsa pe organizator
cu un anunț care spune că e ceva, exact în seara în care nu mai era.

După fereastră nu se mai poate: cine avea de venit a venit sau nu, iar o veste de
„nu mai are loc" trimisă la 20:30 pentru ceva de la 19:00 nu mai ajută pe nimeni.
Ce rămâne atunci e **„Încheie evenimentul"**, care spune adevărul de după și nu
trimite niciun e-mail.

Atenție la ce înseamnă „început": ora de **start**, nu cea de sfârșit. Un
eveniment care ține de la 18:00 până seara târziu se poate anula până la 19:00, nu
până la 23:00.

Întrebarea o pune `poateFiAnulat()` din `inc/evenimente.php` — aceeași funcție
pentru amândouă paginile cu buton și pentru server. Ceasul e al PHP-ului, socotit
din data și ora evenimentului prin `momentulInceperii()`, nu prin
`evenimentAInceput()`: acela răspunde „da" pentru orice eveniment încheiat, oricât
ar arăta ceasul, iar aici avem nevoie de clipa adevărată ca să putem adăuga ora de
răgaz peste ea.

În pagină zona nu se desenează stinsă, ci **dispare de tot**; în locul ei rămâne un
rând care spune de ce. Un buton pe care scrie „anulează" și care nu anulează e mai
rău decât niciun buton. Verificarea de pe server nu e o repetare de prisos: pagina
poate fi deschisă cu cinci minute înainte de fereastră și apăsată după.

**409, nu 403.** N-are legătură cu drepturile omului — evenimentul e al lui — ci
cu starea lucrurilor, care s-a schimbat între timp.

### Butonul, pe două pagini

Zona de anulare stă și în formularul de editare, și pe **pagina evenimentului**,
imediat sub caseta de interes. Acolo se ia hotărârea: omul se uită la câți au spus
că vin și, dacă sunt doi din doisprezece, anulează. Ținut numai în formularul de
editare, butonul era la două pagini distanță de cifra care îl face să-l apese.

Același HTML în amândouă locurile, dintr-un singur loc: `randeazaZonaAnulare()`
din `inc/afisare-eveniment.php`. Scris de două ori, s-ar fi despărțit la prima
corectură — și tocmai aici, unde textul de avertizare e singurul lucru pe care
omul îl citește înainte de o apăsare care nu se ia înapoi.

`main.js` o găsește după `data-anulare`, nu după formularul de eveniment: pe
pagina anunțului nu există niciun formular în jur, iar slugul și tokenul stau pe
zona însăși.

Se vede **doar organizatorului**, și nu prin CSS: pentru oricine altcineva blocul
nici nu se scrie în pagină.

### Ce se vede după anulare

**Pagina rămâne publică**, ca la un eveniment încheiat. A fost ascunsă o vreme, și
era greșit: de un eveniment anulat atârnă oameni care își făcuseră planuri, iar o
pagină care dispare îi lasă cu un link mort și cu întrebarea dacă n-au greșit ei
ziua. Cine intră de pe un mesaj primit acum trei zile află pe loc ce s-a
întâmplat.

Sus e banda de „anulat", iar sub ea **motivul scris de organizator**, întocmai cum
l-a scris — e același text care a plecat prin e-mail, iar un rezumat făcut de noi
ar fi spus altceva decât mesajul primit.

Ce nu se mai poate acolo rămâne oprit de `evenimentPublicat()`, care întoarce
„nu" pentru anulat: **nimeni nu se mai înscrie.** Nu poți spune „vin" la ceva ce
nu se mai ține.

**Discuția, în schimb, rămâne deschisă.** A fost și ea închisă o vreme, și era
greșit. O ieșire anulată e tocmai momentul în care oamenii au ceva de zis — „ce
păcat", „mai încercăm?", „eu tot mă duc" — iar cu comentariile închise cei care
se înscriseseră rămâneau fără niciun loc în care să-și răspundă unul altuia, iar
organizatorul fără felul cel mai firesc de a-și cere scuze.

De aceea a apărut a doua întrebare, `discutiaEDeschisa()`, alături de
`evenimentPublicat()`. Prima spune „au oamenii unde vorbi" (aprobat, încheiat,
**anulat**), a doua „se poartă lumea cu el ca și cu unul de pe site" (aprobat,
încheiat — de ea atârnă înscrierile, scoaterile de pe listă, indexarea la
Google). Două întrebări care răspundeau la fel până acum, dar care nu erau
aceeași întrebare; anulatul le-a despărțit.

Se schimbă și **vorba din caseta goală**. „Fii primul care spune ceva" sub un
anunț tocmai anulat ar suna a petrecere; acolo scrie „Evenimentul a fost anulat,
dar discuția rămâne deschisă. Scrie, dacă ai ceva de spus." Înainte scria că
„comentariile au fost închise", ceea ce era adevărat și era greșit deodată.

Și **tabul își schimbă numele**: la un anunț anulat scrie „Ar fi participat", nu
„Au participat". Oamenii aceia se înscriseseră la o seară care nu s-a mai ținut
— „au participat" ar fi o minciună curată, iar „Participă", la prezent, i-ar
trimite pe unii să-și ia haina de pe cuier. Anulatul se întreabă **înaintea**
încheiatului: un anunț anulat pentru o zi care a trecut de-atunci e și una, și
alta, iar anularea e vestea care contează.

Anunțul rămâne și în liste — pe prima pagină și în tabul „Istoric" de pe profil —
stins ca unul încheiat, dar cu **„Anulat"** scris în colț în loc de „Încheiat".
Aceeași clasă, `card--incheiat`: amândouă sunt seri care nu mai urmează, iar
deosebirea dintre ele e un cuvânt, nu o culoare. O etichetă roșie, de alarmă, ar
fi strigat pe prima pagină la un anunț de acum trei săptămâni, pe care oricum nu-l
mai așteaptă nimeni.

Pe prima pagină se socotește drept trecut chiar dacă ziua lui e în viitor: seara
aceea nu mai vine pentru nimeni, deci n-are ce căuta printre cele la care se mai
poate ajunge. În „Istoric" rămâne la fel — omul se înscrisese și își ținuse seara
liberă, iar asta face parte din ce i s-a întâmplat — dar **nu se numără** la „a
participat la": n-a participat la nimic.

### Cifrele de pe cartonaș

În colțul de jos-dreapta al fiecărei poze stau două cifre: câți vin și câte
comentarii sunt. Amândouă răspund la aceeași întrebare tăcută pe care și-o pune
omul când trece cu ochii peste o listă — „se duce cineva, se vorbește ceva despre
asta?".

| Ce arată | Când |
|---|---|
| `7` | evenimentul n-are număr maxim de locuri |
| `7 / 12` | are limită — cifra singură n-ar spune nimic |

Șapte inși e mult la o partidă de tenis și puțin la un concert; cu numitorul
alături, omul vede dintr-o privire dacă mai are unde să intre.

Participanții se numără **exact ca pe pagina evenimentului**: numai conturile
active. Cine și-a șters contul nu mai ține un loc, deci n-are ce căuta nici în
cifra de pe cartonaș — altfel s-ar fi văzut „12 / 12" pe listă și un loc liber pe
pagină. Comentariile se numără fără cele golite, care sunt pietre de mormânt, nu
vorbe de citit.

Bucata de SQL e scrisă o dată, `CIFRE_CARTONAS`, și lipită în fiecare listă care
desenează cartonașe. Scrisă de patru ori, ar fi ajuns să numere patru lucruri ușor
diferite, iar același eveniment ar fi arătat „7" într-un loc și „8" în altul.

Cifrele se scriu numai dacă rândul le-a adus din bază — un cartonaș desenat dintr-un
rând care n-a cerut subcererile ar fi arătat „0 / 0", un neadevăr care pare o
socoteală.

### Rândul de sub cartonaș: când, unde, unde anume

Sub fiecare cartonaș stau trei lucruri, în ordinea asta: **data, orașul, locul
anume** — „30 aug 2026 · Roman · Piața Sfatului".

Ordinea nu e întâmplătoare: se strânge dinspre larg spre îngust. Așa se citește
ca o adresă spusă cu voce tare; invers, locul fără oraș te pune să te întrebi
unde e Piața Sfatului.

Orașul lipsea, și se simțea mai ales în afara primei pagini. Acolo se poate
cerne după oraș, deci pare de prisos — dar cine intră de pe un mesaj primit, sau
se uită pe profilul cuiva, sau la „Ar putea să te intereseze", n-a cernut nimic.
Iar când orașele vor fi mai multe, o listă fără ele ar fi fost o listă din care
lipsește tocmai ce alege omul.

Se scrie la **toate** cartonașele, oriunde ar fi ele — ceea ce înseamnă că
`e.oras` trebuie cerut în fiecare interogare care desenează un cartonaș. Două nu
îl cereau (lista de pe profil și „Istoric"), iar acolo rândul ar fi ieșit tăcut
fără oraș: cartonașul nu se plânge de o coloană lipsă, doar nu scrie nimic.

### Cine află

**Și cei care confirmaseră, și cei doar interesați.** Al doilea grup n-a promis
nimic, dar s-a uitat într-acolo tocmai fiindcă se gândea să meargă — iar cine
și-a ținut sâmbăta liberă „poate mă duc" trebuie să afle că n-are unde, la fel
ca cel care apucase să bifeze.

**Fără organizator.** El e cel care tocmai a apăsat butonul, iar la publicare se
trece singur pe lista de participanți (`faOrganizatorulParticipant`) — fără
rândul care îl scoate, și-ar fi trimis singur vestea.

Numai conturile active și cu adresă, ca peste tot: cine și-a șters contul n-are
unde primi nimic, iar rândul lui anonimizat n-are adresă.

### Ordinea

Oamenii se citesc **înainte** de anulare; vestea pleacă **după** ce anularea e
scrisă în bază.

Prima jumătate nu contează azi — anularea nu șterge rândurile din
`interese_evenimente` — dar rămâne adevărată în ziua în care se va face
curățenia despre care vorbește `anuleazaEveniment()`. Cine citește lista
înainte n-are cum să rămână cu mâna goală.

A doua contează acum: aceeași ordine ca la scoaterea cuiva de pe listă. Un
e-mail trimis peste o scriere care apoi pică ar spune oamenilor un neadevăr —
„nu mai are loc", pentru un eveniment care e tot acolo. Invers, dacă scrierea
reușește și e-mailul nu pleacă, anularea rămâne făcută, iar asta se poate
îndrepta. Ce n-a plecat ajunge în log, dar nu oprește răspunsul: organizatorul
trebuie să vadă că evenimentul e anulat, nu o eroare despre serverul de e-mail.

### Mesajul n-are buton spre eveniment

Pagina se deschide de oricine acum, cu banda și motivul la vedere — dar motivul e
deja în mesajul ăsta, iar omul care tocmai a aflat că i s-a stricat seara are
nevoie de altceva de făcut, nu de încă o citire a aceleiași vești. Butonul duce în
oraș, unde mai sunt și altele.

Ziua se spune din nou, întreagă. Mesajul poate fi citit peste o săptămână, iar
„nu mai are loc" fără dată nu-i spune omului ce seară i s-a eliberat.

O singură propoziție diferă între cele două feluri de oameni: cine confirmase
primește o vorbă care recunoaște că își făcuse un plan, cine era doar interesat
una mai ușoară. Vestea și motivul sunt aceleași pentru toți.

Motivul intră ca paragraf obișnuit, deci trece prin aceeași ieșire ca restul
textelor din șablon — scăpat în varianta HTML. Nimeni nu poate strecura
etichete în e-mailul altcuiva prin caseta de motiv.

### De ce nu pleacă din `anuleazaEveniment()`

Funcția aceea e stratul care atinge baza. Trimiterea stă în API, imediat după
ce o cheamă — aceeași împărțire ca la scoaterea de pe listă, unde
`excludeParticipant()` scrie și API-ul trimite. Un `require` de `email.php` în
`evenimente.php` ar lega două lucruri care n-au de ce să se cunoască.

A doua anulare nu există: `evenimentDeEditat()` refuză un eveniment deja
anulat, deci nu are cum să plece vestea de două ori.

Verificările: `php teste/test-anulare.php http://127.0.0.1:8128` (39 de cazuri;
fără adresă merge și fără server, 24). Nu trimite e-mailuri adevărate — cu
`dezvoltare => true` mesajele se scriu în `private/emailuri-trimise.log`, și de
acolo se citesc.


## „Aprobă" și „Respinge", pe pagina anunțului

Fiecare eveniment intră cu `stare_moderare = 'in_asteptare'`. Până acum n-avea
cine să-l scoată de acolo: steagul se schimba de mână, din phpMyAdmin.

`STARI_MODERABILE` și `moderezaEveniment()` din `inc/evenimente.php`,
`api/modereaza-eveniment.php`, blocul de la coada articolului din `event.php`.

### Staff-ul trebuia întâi să poată VEDEA anunțul

Butoanele stau pe pagina evenimentului, iar un anunț în așteptare e tocmai cel
care are nevoie de ele — dar `poateVedeaEvenimentul()` nu deschidea pagina unui
anunț neaprobat decât pentru organizator. Adică exact anunțurile care așteptau
erau invizibile pentru cine trebuia să le citească.

Așa că staff-ul vede acum orice eveniment, oricare i-ar fi starea. Nu e un drept
dat de dragul lui: fără el, moderarea n-ar fi putut exista pe pagina anunțului.

Blocul stă **între anunț și discuție**: hotărârea se ia după ce ai citit ce a
scris omul, dar înainte să te pierzi prin comentarii — care oricum n-au ce
căuta în socoteala asta.

### Blocul nu se ascunde, lipsește

Pentru cine nu e om de casă, blocul nici nu se scrie în HTML. Nu e `display:
none`, fiindcă un buton ascuns rămâne un buton — se găsește din consolă și se
apasă. Iar `api/modereaza-eveniment.php` întreabă din nou cine cere, cu
`esteStaff()`, care citește din bază la fiecare cerere: un drept luat înapoi
trebuie să dispară imediat, nu la următoarea conectare.

Refuzul e **403**, nu 404: aici nu e nimic de ascuns. Pagina evenimentului se
vede oricum, deci un „nu există" ar fi o minciună fără niciun câștig.

### Trei hotărâri din două butoane

Sub caseta de motiv stă o bifă, **„Editare necesară", pusă din start**:

| ce apeși | ce se întâmplă |
|---|---|
| Aprobă | `aprobat` |
| Respinge, cu bifa pusă | rămâne `in_asteptare`, pleacă doar vestea |
| Respinge, cu bifa scoasă | `respins`, **și se golește tot** ce s-a strâns în jur |

Cea din mijloc e drumul obișnuit, și de aceea e bifată din start. Un anunț bun,
dar cu o oră lipsă sau cu locul scris pe jumătate, nu merită respins: e mai bine
să i se spună omului ce n-a mers și să poată drege, fără s-o ia de la capăt.
E-mailul lui are alt ton — nu e un „nu", e un „aproape".

Butonul și rândul de sub bifă se schimbă odată cu ea: „Cere îndreptarea" cu
lămurirea liniștită, „Respinge anunțul" cu avertizarea roșie că ce se șterge nu
se mai aduce înapoi. Două hotărâri foarte diferite n-au ce căuta sub același
cuvânt.

Verificarea „e deja în starea asta" **nu se pune la editare necesară**: acolo
starea rămâne cea de acum, de obicei `in_asteptare`, iar asta e chiar rostul ei.
Altfel drumul obișnuit ar fi fost singurul refuzat.

### La respingerea adevărată se golește tot

Comentarii (cu aprecierile lor, în cascadă), note, excluderi, înscrieri —
`golesteDateleEvenimentului()`, într-o tranzacție, fiindcă sunt patru tabele și
o cădere la jumătate ar lăsa comentarii fără participanți.

**Rândul anunțului rămâne**: e al organizatorului, cu starea `respins`, ca să-l
poată vedea și îndrepta. Se duce doar ce au făcut alții în jurul lui.

De ce se golește: un anunț respins n-a fost niciodată public, deci ce s-a strâns
în jurul lui e ori zgomot rămas de la o aprobare luată înapoi, ori urma unei
greșeli. Iar dacă tot nu se publică, notele acelea ar rămâne pentru totdeauna pe
profilurile unor oameni, legate de o seară care n-a existat.

**Organizatorului nu i se spune.** E-mailul zice ce-l privește — că anunțul nu
se publică — nu ce am făcut noi prin bază.

Un efect al golirii, tratat: dacă un anunț respins e aprobat mai târziu,
organizatorul nu mai era pe lista lui de participanți, iar de rândul acela
atârnă mulțumirile de după eveniment și notele. De aceea aprobarea îl pune la
loc, prin `faOrganizatorulParticipant()` — care e `INSERT IGNORE`, deci în cazul
obișnuit nu face nimic.

### Ce se poate hotărî, și din ce

Se pot pune două stări: `aprobat` și `respins`. `incheiat` și `anulat` nu sunt
hotărâri de moderare, sunt fapte petrecute — le pune organizatorul sau ceasul.

Se poate modera din `in_asteptare` (cazul obișnuit) și din celelalte două, în
amândouă sensurile: un anunț respins din greșeală trebuie să poată fi aprobat,
iar unul aprobat prea repede trebuie să poată fi oprit. De aceea butoanele n-au
treaptă de confirmare — ce se poate lua înapoi nu merită o întrebare în plus.

**Din `anulat` și `incheiat` nu se poate.** Anulat e hotărârea organizatorului,
luată în fața oamenilor înscriși, care au primit deja un e-mail cu motivul; o
aprobare peste ea ar readuce pe site un eveniment despre care toată lumea a
aflat că nu mai are loc. Încheiat înseamnă că seara aceea a trecut.

Butonul stării de acum lipsește din pagină: n-ai ce aproba de două ori.
Serverul răspunde oricum cu „e deja aprobat" — se întâmplă la doi moderatori pe
aceeași listă — dar mai bine nu-l pui pe om să apese ca să afle.

### Organizatorul află printr-un e-mail

La aprobare, un mesaj scurt: anunțul se vede de acum pe site. La respingere,
unul care spune ce s-a hotărât, de ce, și pe unde se merge mai departe — orice
schimbare îl pune înapoi în așteptare.

**Motivul respingerii e opțional.** Spre deosebire de motivul anulării, care
pleacă spre zeci de oameni ce își făcuseră un plan și au dreptul la o
explicație, ăsta merge spre unul singur — iar uneori anunțul e limpede greșit
și n-ai ce scrie. A-l sili pe omul de casă să compună o frază ar naște fraze de
umplut („nu se poate").

Când lipsește, mesajul **nu tace și nu se preface**: scrie „Nu s-a specificat
nici un motiv. Pentru orice nelămurire, te rugăm să ne contactezi." Un „nu"
fără explicație și fără nicio ușă e cel mai prost fel de a închide o discuție.

„Respinge" nu trimite pe loc: deschide o casetă unde se poate scrie motivul.
Nu e o treaptă de confirmare — hotărârea rămâne reversibilă — e clipa în care
omul de casă e întrebat dacă are ceva de spus. Aprobarea n-are ce explica, deci
pleacă dintr-o apăsare.

Vestea pleacă **după** ce starea e scrisă în bază, ca la anulare: un e-mail
trimis peste o scriere care apoi pică i-ar spune omului un neadevăr. Dacă
mesajul nu pleacă, hotărârea rămâne luată și necazul ajunge în log — omul de
casă trebuie să vadă starea nouă, nu o eroare despre serverul de e-mail.

### Ce nu face încă

Motivul respingerii **nu se păstrează în bază**: pleacă în e-mail și atât.
Banda de pe pagina organizatorului spune doar că n-a fost aprobat, fără să
spună de ce — cine caută motivul trebuie să se uite în e-mail. O coloană ca
`motiv_anulare` ar rezolva-o.

Respingerea unui anunț **deja aprobat**, la care s-au înscris oameni, nu-i
înștiințează pe aceia — spre deosebire de anulare, care o face.

Verificările: `php teste/test-moderare.php http://127.0.0.1:8128` (156 de
cazuri; fără adresă merge și fără server, 80).


## Staff-ul publică direct

Pentru cine are `membri.este_staff` diferit de `0`, formularul de pe
`adauga_eveniment.php` arată puțin altfel:

| | Om obișnuit | Staff |
|---|---|---|
| butonul | „Trimite spre aprobare" | **„Publică evenimentul"** |
| starea la salvare | `in_asteptare` | **`aprobat`** |
| panoul de după | „Îl citim și, dacă e totul în regulă, apare pe site." | „Se vede de acum pe site, fără să mai treacă pe la nimeni." |
| bifa „nu-l arăta pe profil" | nu există | **da** |

Un anunț al omului de casă n-are pe cine să aștepte: el E cel pe la care ar fi
trecut. Fără schimbarea asta, ar fi trebuit să-și deschidă propriul anunț și să
apese „Aprobă" la ce tocmai scrisese.

Regula stă în `starePentruPublicare()` din `inc/evenimente.php` — o funcție de
o linie, scrisă ca funcție fiindcă o cer trei locuri: salvarea, editarea și
pagina care alege ce scrie pe buton. **Și la editare**, nu doar la publicare:
altfel o virgulă îndreptată de staff în propriul anunț l-ar fi scos de pe site
până la o a doua apăsare.

### Bifa „Nu arăta evenimentul pe profilul meu"

Stă între ultimul chenar („Detalii") și butoane — ultimul lucru de hotărât
înainte de apăsare, nu unul dintre câmpurile anunțului. Nebifată din start:
alegerea obișnuită e ca anunțul să se vadă pe profilul celui care l-a pus.

E pentru anunțurile puse **în numele orașului**, care n-au ce căuta pe profilul
personal al omului de casă, la „Ieșiri organizate", ca și cum ar fi ieșirile
lui.

**Ce ascunde, și ce nu.** Coloana e `evenimente.ascuns_pe_profil` (`sql/022`) și
lucrează în trei locuri, toate pe profil:

| Unde | Ce se întâmplă |
|---|---|
| lista „Ieșiri organizate" | lipsește (`evenimenteDePeProfil`) |
| cifra „Evenimente organizate" | nu-l numără (`cateEvenimenteOrganizate`) |
| tabul „Istoric" | lipsește — **doar de pe profilul organizatorului** |

Nicăieri altundeva. Anunțul rămâne întreg pe prima pagină, în filtre, în „Ar
putea să te intereseze" și pe pagina lui, cu numele organizatorului la vedere.
Nu e o coloană de anonimat — e una care spune „ăsta nu e o ieșire de-a mea".

Jumătatea cu `e.membru_id` din condiția istoricului nu e de prisos: fără ea, un
anunț al orașului ar fi dispărut din istoricul celor cincizeci de oameni care au
fost la el. Pentru cine a fost acolo, seara aceea a existat.

Lipsește **și de pe profilul lui, când și-l vede el însuși**. Dacă i s-ar arăta
doar lui, ar crede de fiecare dată că bifa n-a mers, iar profilul lui ar arăta
altfel pentru el decât pentru lume.

**Ascultată numai de la staff.** `ascundePeProfil()` întoarce `false` pentru
oricine altcineva, oricât ar scrie în cererea trimisă — caseta nici nu se
desenează în formularul lui, deci un „1" venit de acolo e scris de mână. Ca
peste tot, ce se vede în pagină e purtare frumoasă; regula e în
`api/eveniment.php`, care întreabă baza la fiecare cerere.

### Organizatorul nu se mai trece singur pe listă

La un anunț obișnuit, cine îl pune la cale apare din start printre participanți:
rândul se scrie singur, fără să apese nimeni nimic
(`faOrganizatorulParticipant`). La unul ținut deoparte, **nu**.

Omul de casă nu e cel care iese în oraș, e cel care a scris anunțul orașului: la
târgul de Crăciun nu „participă" el, îl anunță. Trecut pe listă, ar fi apărut
printre chipurile de sub „Cine vine", ar fi umflat numărul cu unu și ar fi putut
fi notat de participanți la sfârșit, ca și cum ar fi fost acolo cu ei.

Poate să se înscrie oricând singur, de pe pagina evenimentului, ca oricine
altcineva — dacă chiar se duce. Regula de gen nu-l oprește nici atunci: e
evenimentul lui.

Întrebarea se pune prin `organizatorulVineSingur()`, în două locuri depărtate —
la salvare și la aprobarea unui anunț care așteptase
(`api/modereaza-eveniment.php`, unde organizatorul se pune la loc pe listă după
o respingere care i-a golit-o). Scrisă de două ori, ar fi ajuns să spună două
lucruri.

**Ce nu face:** dacă bifezi caseta la un anunț unde erai deja pe listă, rândul
tău rămâne — regula lucrează la scriere, nu curăță în urmă. Te scoți de pe listă
de pe pagina evenimentului, ca oricine altcineva.

### Limita de evenimente active nu i se aplică

`poatePublicaEveniment()` îl lasă pe staff să treacă peste ea. Limita e făcută
împotriva celui care ar umple prima pagină cu zece anunțuri deodată — iar omul
de casă publică tocmai zece: târgul, concertul din parc, ziua orașului. Cu
limita pe el, ar fi trebuit să-și ridice singur numărul din phpMyAdmin înainte
de fiecare al doilea anunț, adică o piedică pusă exact în calea celui în care
avem încredere.

Lista celor active se citește oricum, și pentru el: e ce arată pagina sub
formular („ai deja pe astea"), și n-are de ce să dispară doar fiindcă nu-l mai
oprește nimic. Iar steagul se citește din bază la fiecare cerere, deci un drept
luat înapoi îl pune la loc sub limită imediat, nu la următoarea conectare.

## Trei plase de siguranță, puse înainte de deschidere

Înainte de a scoate site-ul din construcție s-a mai citit o dată tot codul, cu
ochii pe partea de siguranță. Cea mai mare parte era în regulă: escaparea la
randare cu `h()`, tokenul CSRF la orice faptă, întrebarea „e al tău?" pusă la
fiecare API, pozele redesenate pixel cu pixel, aceleași vorbe pentru „parolă
greșită" și „cont inexistent". Trei lucruri au trebuit îndreptate, și toate
trei sunt de același fel: nu greșeli de scriere, ci lucruri care merg perfect
cu un om și se rup cu o mie.

### 1. Ultimul loc, cerut de opt oameni deodată

`api/interes.php` întreba, în ordinea asta: „mai sunt locuri?", iar dacă da,
„scrie-mă pe listă". Între cele două întrebări încape o clipă, iar într-o clipă
încap alte șapte cereri. Fiecare dintre ele a apucat să întrebe înainte ca
vreuna să apuce să scrie, fiecare a primit „mai sunt", și toate opt s-au trecut
pe listă.

Nu e o închipuire: la un eveniment cu două locuri, opt cereri pornite în aceeași
clipă lăsau cinci oameni înăuntru. De fiecare dată, la fiecare rulare.

Se vedea greu fiindcă pe un server de dezvoltare nu se întâmplă niciodată — el
răspunde la o singură cerere odată. A trebuit pornit unul cu mai mulți lucrători
(`PHP_CLI_SERVER_WORKERS=8`) ca să se poată arăta.

Îndreptarea e o tranzacție cu `SELECT … FOR UPDATE` pe rândul evenimentului:
primul care intră ține ușa, ceilalți așteaptă pe hol, iar când le vine rândul
întreabă din nou și află adevărul. Aceleași opt cereri, acum: unul intră, șapte
primesc „nu mai sunt locuri". Numărul din bază: exact doi.

Regula rămâne pentru orice altceva se numără: **la un lucru cu număr limitat, nu
se citește într-un loc și se scrie în altul.** Ori hotărârea intră în `WHERE`
(cum face de la bun început revendicarea unui abțibild), ori se pune o
tranzacție în jurul amândurora.

### 2. „FindMe" jucat de pe canapea

Un cod de abțibild are cinci semne dintr-un alfabet de treizeci și două — vreo
treizeci și trei de milioane de combinații. Pare mult, dar socoteala e greșită:
nimeni n-are nevoie să le încerce pe toate. E de ajuns să nimerească **unul**
dintre cele câteva lipite prin oraș la un moment dat. Cu zece vânători active,
un program care încearcă cincizeci de coduri pe secundă nimerește unul în câteva
ore, fără să se ridice de pe scaun.

Asta ar fi golit jocul de tot rostul lui. „FindMe" nu e un concurs de ghicit, e
un motiv de a te plimba prin oraș cu ochii deschiși.

Acum se ține minte fiecare scanare care **n-a nimerit nimic**, într-un tabel al
ei (`incercari_qr`, `sql/030`), și de la treizeci într-un ceas de la aceeași
adresă IP pagina spune „hai să ne oprim puțin" fără să se mai uite în bază. La
ritmul ăsta, cele treizeci și trei de milioane cer o sută douăzeci de ani.

Trei hotărâri din spatele lui, toate cu socoteală:

- **Doar cele greșite se numără.** Cine scanează un abțibild adevărat a fost
  acolo, s-a uitat, a găsit — n-are de ce să fie pedepsit că mai scanează unul.
- **Un tabel al lui, nu `incercari_autentificare`.** Acolo numărătoarea duce la
  blocarea unui cont, iar o limită de pe altă pagină n-are voie să încuie contul
  cuiva. Aici nu se încuie nimic: se încetinește o adresă.
- **Întrebarea se pune înainte de a se uita în bază după codul cerut.** O frână
  pusă după ce a trecut mașina nu e o frână.

Treizeci e larg pentru orice om — telefonul prinde codul dintr-o dată, iar cine
îl tastează de mână are cinci semne de scris — și strâmt pentru orice program.

### 3. A doua plasă sub escapare

Escaparea cu `h()` e prima apărare împotriva unui `<script>` strecurat în
pagină, și e pusă peste tot: s-au verificat toate cele douăzeci și două de
locuri în care se scrie text venit de la oameni, plus șabloanele de e-mail.
Toate curate.

Dar „toate curate azi" nu e „toate curate mereu". A doua plasă se cheamă
politică de conținut (CSP), și e un antet prin care pagina îi spune browserului
de unde are voie să aducă fiecare fel de lucru:

```
default-src 'self'; script-src 'self' 'nonce-…'; style-src 'self' 'unsafe-inline'
https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;
img-src 'self' data:; connect-src 'self'; form-action 'self'; base-uri 'self';
frame-ancestors 'none'; frame-src 'none'; object-src 'none'
```

E o listă de îngăduințe, nu una de opreliști: tot ce nu scrie acolo e oprit.
Dacă un `<script>` ar scăpa vreodată într-o pagină, browserul refuză să-l ruleze
— fiindcă n-are cifra cererii.

**Cifra** e un număr făcut din nou la fiecare cerere (`nonceCsp()`). Site-ul are
un singur script scris direct în HTML, cel care pune tema înainte de randare ca
să nu clipească alb, iar el o poartă:

```php
<script nonce="<?= h(nonceCsp()) ?>">
```

De aici o regulă pentru mai târziu: **un `<script>` nou scris direct în pagină
nu va merge fără cifră.** Mai bine îl pui în `assets/js/main.js`, ca tot restul —
regula 1 spune oricum același lucru.

`style-src 'unsafe-inline'` e o portiță lăsată deschisă dinadins: cifra nu
funcționează pe atributele `style=`, iar site-ul are trei (bara de note de pe
profil, care are lățimea în procente, și pagina de eroare). Ce se poate face cu
un stil strecurat e mult mai puțin decât cu un script — nu citește sesiunea, nu
trimite nimic nicăieri, fiindcă `connect-src 'self'` și `form-action 'self'` îi
taie și drumul de întoarcere.

Antetele stau într-un singur loc, `antetedeSiguranta()` din `inc/bootstrap.php`,
și se cheamă din două: `inc/antet.php`, adică tot site-ul, și `constructie.php`,
singura pagină care nu trece prin antet. Scrise de două ori, a doua copie ar fi
rămas în urmă la prima schimbare — și tocmai afișul de pe ușă, singura pagină
care se vede cu site-ul închis, ar fi fost cel rămas fără pază. (Chiar așa era:
până acum, afișul n-avea niciun antet de siguranță.)

### Ce s-a verificat și era în regulă

Ca să nu se caute a doua oară: drepturile și „e al tău?" la toate cele
douăzeci și cinci de API-uri, tokenul CSRF, metoda HTTP, injecția SQL (totul
trece prin interogări pregătite), XSS-ul în cele douăzeci și două de locuri de
randare și în cele patru șabloane de e-mail, injecția de antete în e-mail,
verificarea fișierelor încărcate, `.htaccess`-urile care închid `private/`,
`cron/` și `sql/`, scurgerea de conturi la intrare și la recuperarea parolei, și
adresa IP — care se ia mereu din `REMOTE_ADDR`, niciodată dintr-un antet scris
de client.

## Adresele frumoase

`pulsulorasului.ro/eveniment/mergem-la-alergat`, nu
`pulsulorasului.ro/event.php?slug=mergem-la-alergat`.

Adresa unui eveniment e singura de pe site pe care oamenii o pun în mesaje, o
lipesc pe Facebook și o citesc unii altora la telefon. „Intră pe
pulsulorasului.ro slash eveniment slash mergem la alergat" se poate spune;
„event punct php întrebare slug egal" nu se poate. Iar în lista de rezultate a
lui Google, adresa se vede sub titlu.

### Cum merge

`.htaccess` din rădăcină rescrie `/eveniment/<slug>` în `event.php?slug=<slug>`.
E o **rescriere**, nu o redirecționare: browserul rămâne la adresa frumoasă, iar
`event.php` primește cererea exact ca înainte și nu știe nimic despre ea.

Drumul invers îl face `event.php` însuși, cu un **301**: cine deschide un link
vechi de pe WhatsApp ajunge la adresa nouă. 301, nu 302, fiindcă Google numără
două adrese cu același conținut drept conținut repetat și alege singur pe care
s-o arate — 301 îi spune care e cea adevărată și mută spre ea și ce a strâns cea
veche. Tot pentru asta, fiecare pagină poartă acum un `<link rel="canonical">`.

Redirecționarea se face **abia după** ce s-a găsit anunțul și s-a văzut că omul
are voie să-l vadă. Pusă înaintea căutării, ar fi trimis permanent — iar
browserul ține minte un 301 — și un slug scris aiurea, către o adresă care
oricum dă 404. Așa, ce nu duce nicăieri sfârșește tot pe prima pagină, ca
înainte.

### Capcana: toate adresele pornesc acum de la rădăcină

Pagina unui eveniment stă la **adâncimea 1**. O adresă relativă scrisă acolo —
`assets/css/style.css`, `profil.php?m=…`, `fetch('api/interes.php')` — s-ar
căuta în `/eveniment/assets/…`, `/eveniment/profil.php`, `/eveniment/api/…`.

Ăsta e felul obișnuit în care se rupe un site la trecerea la adrese frumoase, și
se rupe **tăcut**: pozele lipsesc, butoanele nu mai fac nimic, legăturile dau
404 — dar numai pe pagina aia, iar restul site-ului pare în regulă. De aceea
toate adresele pe care le scrie site-ul au acum `/` în față:

```
href="/index.php"          fetch('/api/interes.php')
src="/assets/img/…"        header('Location: /login.php')
urlPoza()  → /assets/img/membri/…
urlCoperta() → /assets/img/evenimente/…
```

Există o probă care păzește regula: „nicio adresă relativă în pagină", din
`teste/test-evenimente.php`, adună toate `href`, `src` și `action` de pe pagina
unui eveniment și pică dacă vreuna nu începe cu `/`.

**De aici decurge că site-ul stă la rădăcina domeniului**, nu într-un subdosar.
Nu era altfel nici înainte, dar acum contează.

### Adresele se scriu într-un singur loc

`urlEveniment($slug)` și `urlProfil($permalink)`, amândouă în
`inc/evenimente.php`. Erau nouă locuri care le scriau de mână; acum ziua în care
se schimbă forma unei adrese e o singură linie.

Profilul a rămas cu întrebare (`/profil.php?m=<permalink>`), dinadins: un
permalink e zece semne la întâmplare, n-are ce câștiga din a fi scos în cale.

### În dezvoltare

Serverul din PHP nu citește `.htaccess`, deci fără ajutor adresele frumoase dau
404 local. `teste/router.php` face cele două rescrieri:

```
php -S 127.0.0.1:8099 teste/router.php
```

`.htaccess` rămâne locul adevărat al regulilor; în router sunt scrise a doua
oară **doar** cele două de care atârnă adrese.

## Vârsta minimă, în sfârșit verificată

`evenimente.varsta_minima` exista de la început. Formularul o cerea, pagina o
scria în caseta cu detalii — și nimeni nu se uita la ea la înscriere. Site-ul
scria „18+" și lăsa înăuntru pe oricine.

Asta e mai rău decât să nu fi spus nimic: organizatorul se bizuia pe o regulă
care nu exista, iar el n-avea de unde să știe.

Acum regula stă în `motivBlocajParticipare()`, lângă ușa închisă și regula de
gen — un singur loc de care atârnă și butonul stins din pagină, și refuzul din
`api/interes.php`.

**Se socotește la ziua evenimentului, nu la ziua de azi.** Cine împlinește 16
ani poimâine îi va avea la ceva de peste trei zile, și asta cere organizatorul —
nu ca omul să-i fi avut deja când s-a înscris. Invers nu se întâmplă niciodată:
un anunț nu se mută înapoi în timp.

**„Împlinit" înseamnă împlinit.** La 16 ani ceruți, cel care are exact 16 intră,
cel care are 15 nu.

Socoteala o face `varstaLaZiua()`, cu `DateTime`, nu scăzând ani din ani: „29
februarie 2008" plus șaisprezece ani nu e o zi care există, iar o socoteală
făcută cu mâna ar fi greșit exact în cazul pe care nimeni nu-l probează
niciodată.

Organizatorul trece de regulă, ca și la gen: el e omul de care se leagă
evenimentul, iar un anunț „18+" pus de cineva de 17 ani e o greșeală de
moderare, nu ceva de reparat scoțându-l de pe propria listă.

## Moderarea notelor

Până acum, o stea pusă din supărare rămânea pentru totdeauna în media cuiva.
Notele nu se retrag, nu se raportează, iar pagină de moderare nu exista — era
singurul loc de pe site unde cineva putea face rău altuia fără ca nimeni să
poată îndrepta.

### `admin-evaluari.php`

Două tabele, fiindcă sunt două întrebări deosebite.

**„Cine împarte note, și cu ce mână"** — câte a dat fiecare, media lor, cea mai
mică, câte automate, când a fost ultima. Cei cu mai multe, primii. O notă de unu
poate fi o seară proastă; douăzeci de la același om sunt un obicei, și numai
tabelul ăsta le pune una lângă alta.

**„Ce s-a dat, cui, când și de la ce eveniment"** — notele, cele mai noi întâi.

Se văd **și cele fără text**. Pe profil apar doar părerile scrise — stelele
singure sunt anonime și nici nu se arată. Dar tocmai ele fac media, deci tocmai
ele trebuie să se poată vedea de undeva. E singurul loc de pe site unde o stea
are un nume lângă ea, și de aceea pagina e numai pentru staff.

Notele automate — „Nu s-a prezentat", care pune o stea — poartă o etichetă.
Fără ea, un om de casă ar fi văzut un „1" lângă un nume și ar fi crezut că
cineva a fost răutăcios, când de fapt organizatorul a bifat o absență.

### Cifra de pe panou

E **„câte sunt sub trei stele"**, nu „câte sunt". Peste tot pe panoul de
administrare, cifra înseamnă „câte așteaptă ceva de făcut" — iar la evaluări nu
așteaptă niciuna: nu se aprobă și nu se resping. Se aprinde deci ce merită o
privire: notele de una sau două stele, singurele despre care ar putea veni
cineva să spună că sunt nedrepte. Fără cele automate. Zero acolo înseamnă
„nimeni n-a fost aspru cu nimeni", și asta chiar e o veste bună.

### „×"-ul de pe profil

Același buton stă și pe profilul omului, în dreptul fiecărei păreri scrise,
numai pentru staff — fiindcă de obicei așa afli de o vorbă nedreaptă: intri pe
profilul cuiva și vezi ce scrie acolo. Stă în antetul părerii, lângă oră, ca
steagul de raportare de la comentarii; nu sub text, unde ar fi arătat ca un
buton al cititorului.

Pentru cine nu e de-al casei, butonul nu e ascuns din CSS — nu ajunge în pagină
deloc. Iar `api/admin.php` întreabă din nou, fiindcă o legătură care nu se vede
rămâne o cerere care se poate scrie de mână.

### Ștergerea

E o **ștergere adevărată**, a doua de pe site după cea a unei dorințe. Un
comentariu cu răspunsuri sub el se golește, fiindcă de el atârnă discuția; de o
notă nu atârnă decât o cifră dintr-o medie, iar o cifră „golită" ar fi rămas tot
o cifră.

**Nu pleacă niciun e-mail**, dinadins. Nici către cel care a dat nota: dacă a
pus-o din răutate, o veste i-ar spune doar că merită încercat de pe alt cont, iar
dacă a pus-o cinstit, i-ar spune că cineva i-a citit părerea și a hotărât s-o
șteargă — mai rău decât tăcerea. Nici către cel notat: pentru el media a fost
mereu doar o cifră care se mișcă, iar „ți-am șters o notă de una" înseamnă
„cineva ți-a dat una și n-ai știut". De aceea butonul nici nu cere un motiv:
n-ar avea cine să-l citească.

După ștergere pagina se cere din nou, fiindcă amândouă locurile de unde se poate
apăsa au un rezumat care se socotește din note: profilul are media și barele,
admin-evaluari.php are tabelul de sus. Scos doar rândul, media ar fi rămas cea
veche pe ecran — exact cifra despre care mesajul tocmai a spus că s-a schimbat.

## Dorința pusă de două ori

Aceeași formă ca la ultimul loc de la un eveniment: `puneODorinta()` întreba „ai
deja una?" și, dacă nu, scria. Între cele două întrebări încape o clipă.

Reprodusă cu șase cereri pornite în aceeași clipă: **două-trei dorințe** de
fiecare dată. Cu lacătul pus: una singură, la fiecare rulare.

Interesant e ce a trebuit ca s-o vezi. Cu șase **file ale aceluiași browser**
proba trecea mereu — fiindcă PHP ține un lacăt exclusiv pe fișierul sesiunii cât
ține o cerere, deci două file ale aceluiași om se așteaptă oricum una pe alta.
Cursa adevărată se vede numai când sesiunile sunt **deosebite**: laptopul și
telefonul aceluiași om, sau o filă privată.

Asta merită ținut minte pentru orice altă socoteală de forma „citește,
hotărăște, scrie" care ține de un singur om: nu e apărată de lacătul sesiunii
decât dacă omul are o singură sesiune.

Se încuie rândul omului din `membri` (`SELECT id … FOR UPDATE`): regula e a lui,
deci acolo e locul firesc de făcut rândul la coadă, iar doi oameni deosebiți nu
se așteaptă unul pe altul.

## Harta site-ului și robots.txt

`sitemap.php`, cerută la `/sitemap.xml` printr-o rescriere. **Se scrie din bază
la fiecare cerere**: una făcută o dată și lăsată acolo ar fi rămas în urmă la
primul eveniment nou, iar o hartă care minte e mai rea decât niciuna — Google se
duce după ea, găsește pagini care nu există, și învață să n-o mai citească.

Intră prima pagină, „Despre", „Contact" și toate evenimentele publice — cu
„anulat" inclus, fiindcă pagina unui anunț anulat rămâne pe site cu motivul
scris de organizator, tocmai ca oamenii care își făcuseră planuri să afle ce s-a
întâmplat. Ea trebuie găsită, nu ascunsă.

Cu site-ul în construcție răspunde 503, nu o listă de adrese: altfel un robot
care trece în timpul lucrărilor ar lua lista întreagă, s-ar duce la fiecare, ar
găsi afișul de șantier peste tot, și ar ține minte că site-ul e gol tocmai în
ziua în care nu trebuia.

`robots.txt` e o **rugăminte, nu un lacăt** — ce chiar trebuie închis se închide
în cod. Ține deoparte zona de administrare, uneltele contului, API-urile (care
răspund JSON, deci n-au nimic de citit pentru un motor de căutare) și filtrele
de pe prima pagină, care sunt aceleași evenimente așezate altfel.

**Și profilurile.** Pe un profil scriu numele omului, chipul lui, notele primite
și pe unde a fost — date pe care le-a dat ca să meargă la ieșiri prin oraș, nu ca
să fie găsit după nume în Google. Paginile rămân publice pentru cine primește
linkul: nu se ascund, doar nu se caută.

## Newsletterul zilnic

O dată pe zi, la 12, pleacă un mesaj cu **ce se întâmplă azi în oraș** către
cine are bifa „Vreau să primesc e-mail cu evenimente noi (cel mult unul pe zi)"
din setări.

```
0 12 * * *  php /home/UTILIZATOR/public_html/cron/newsletter-zilnic.php
```

De ce la prânz și nu dimineața: la 12 se știe deja cum e ziua, iar pentru ceva
de la 19:00 mai sunt șapte ore în care omul poate să-și facă un plan. Un mesaj
la 7 dimineața se citește în autobuz și se uită până seara.

### Dacă azi nu e nimic, nu pleacă nimic

Nici măcar un „azi nu se întâmplă nimic". Un mesaj care nu spune nimic e cel mai
bun fel de a-l învăța pe om să nu-l mai deschidă — iar peste o lună, când chiar
e ceva, mesajul ajunge tot necitit.

**Tăcerea e conținut:** dacă a venit mesajul, înseamnă că are ce spune.

### Ce intră în el

Evenimentele **aprobate** de azi, în ordinea orei. Nu intră:

- cele de mâine sau de ieri — mesajul e despre ziua de azi;
- cele **anulate**, deși ziua lor e azi. Pagina lor rămâne pe site, cu motivul
  scris de organizator, dar a le trimite dimineața ca pe ceva ce urmează ar fi o
  minciună;
- cele care așteaptă moderarea, sau pe care organizatorul le-a încheiat deja.

Ordinea e a orei, nu a scrierii: cine deschide mesajul la prânz vrea să vadă
întâi ce urmează, iar lista citită de sus în jos trebuie să fie ziua lui.

### Poza care nu se încarcă

Gmail, Outlook și aproape toate celelalte **nu aduc pozele** până nu cere omul.
Un mesaj gândit doar pentru cazul fericit se face praf la prima deschidere: ori
rândurile se strâng la câțiva pixeli, ori alt-textul se rupe pe trei rânduri și
face un rând de trei ori mai înalt decât vecinii lui.

Trei lucruri țin blocul întreg:

1. **Poza stă într-o celulă de lățime fixă** (120px), cu `width` scris și ca
   atribut, nu doar în `style` — Outlook nu citește lățimile din CSS. Celula are
   lățimea aia și când e goală.
2. **`<img>` are `width` și `height` ca atribute.** Locul e rezervat înainte să
   vină poza; fără înălțime, rândul se strânge și apoi sare când poza sosește.
3. **`alt=""`, gol dinadins.** Un alt scris („Coperta evenimentului") s-ar arăta
   în locul pozei, s-ar rupe într-o casetă de 120px și ar umfla rândul. Titlul e
   oricum scris alături, deci poza n-are nimic de spus în plus: e decor.

Celula are un fundal stins, ca locul gol să arate a loc gol *anume*, nu a ceva
stricat. Un eveniment fără nicio poză primește exact aceeași casetă, deci toate
rândurile rămân aliniate.

Măsurat în Chromium, cu pozele lăsate să vină și cu ele oprite: **aceleași
măsuri, aceeași înălțime totală a mesajului, pixel cu pixel.**

Poza e coperta anunțului, iar dacă n-are, imaginea categoriei — aceeași ordine
ca pe cartonașele de pe prima pagină, prin aceleași două funcții. Adresele sunt
**întregi**: într-un e-mail nu există „pagina de acum" față de care să se
socotească o cale relativă.

### Cel mult unul pe zi

Bifa promite „cel mult unul pe zi", iar `membri.newsletter_trimis_la`
(`sql/031`) e singurul lucru care chiar o ține. Fără ea, un cron pornit din
greșeală de două ori trimite de două ori, iar o rulare de mână ca să se vadă
dacă merge ajunge la toată lumea.

Ștampila se pune **înainte** de trimitere, nu după, iar hotărârea e în `WHERE`,
ca la revendicarea unui abțibild: două rulări pornite în aceeași clipă ar
întreba amândouă „i-a plecat azi?", ar auzi amândouă „nu", și ar trimite
amândouă.

Dintre cele două greșeli cu putință — „a plecat de două ori" și „n-a plecat
pentru că a căzut curentul între ștampilă și poștă" — se alege a doua. **Un
e-mail plecat nu se ia înapoi.**

Dacă nu rulează o zi, ziua aceea se pierde, și e în regulă: nimeni nu vrea să
afle mâine ce era ieri.

Pentru încercare, fără să atingă nimic:

```
php cron/newsletter-zilnic.php --uscat
```

### Dezabonarea

Singurul mesaj de pe site care vine **nechemat**, deci singurul cu ieșire la
vedere: un link în subsol și antetul `List-Unsubscribe`, pe care Gmail îl
citește și pune **el** un buton „Dezabonează-te" lângă numele expeditorului. E
cel mai apăsat buton dintre toate — și e mult mai bun pentru noi decât „Spam",
care e vecinul lui pe ecran.

Celelalte mesaje sunt răspunsuri la ceva ce a făcut omul — o confirmare, vestea
că i s-a anulat un eveniment — iar alea nu se „dezabonează".

**Fără cont.** Cine s-a săturat de un mesaj n-are chef să-și amintească parola
ca să scape de el; dacă nu scapă în două secunde, apasă „Spam", și un singur om
care face asta strică livrarea pentru toți ceilalți. Semnătura din adresă ține
loc de dovadă.

**Semnătura nu e un token din bază**, ci un HMAC socotit din id-ul omului și o
cheie a site-ului. Un token scris la fiecare trimitere ar fi însemnat că linkul
de ieri moare azi — iar cine caută peste trei luni un mesaj vechi ca să se
dezaboneze ar da peste „link expirat". Cheia se pune în `cheie_dezabonare` din
config; lăsată goală, se face una din datele care există deja acolo, deci
newsletterul merge din prima, fără niciun pas de pregătire.

**Deschiderea linkului doar întreabă; stinsul se face cu un buton, prin POST.**
Multe programe de e-mail și multe filtre de siguranță deschid singure toate
linkurile dintr-un mesaj, ca să vadă unde duc. Cu un GET care stinge, o parte
dintre oameni s-ar fi trezit scoși de pe listă fără să fi apăsat nimic — și n-ar
fi aflat niciodată de ce nu le mai vine nimic. Un scaner apasă linkuri, nu
butoane. De aceea nu se trimite nici `List-Unsubscribe-Post`: acela le-ar spune
programelor să dezaboneze ele, în numele omului.

Se stinge **numai** bifa de newsletter. Cine se satură de mesajul zilnic n-a
spus că nu mai vrea să afle că i s-a anulat un eveniment la care se înscrisese;
un „dezabonează-mă de la tot" care oprește și vestea aceea e o capcană, nu o
politețe.

Verificările: `php teste/test-newsletter.php http://127.0.0.1:8099` (55 de
cazuri; partea cu pagina de dezabonare cere serverul pornit).
