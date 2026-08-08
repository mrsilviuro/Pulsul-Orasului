# PulsulOrasului.Ro

Șablon minimalist modern pentru blog de evenimente locale. HTML + CSS + JS simplu,
fără build, fără dependențe. Deschizi `index.html` direct în browser.

## Structură

```
index.html
assets/
  css/style.css      → tokens de culoare, layout, componente, responsive
  js/main.js         → temă, meniu mobil, slideshow
  img/slides/        → imaginile din slideshow (16:9)
  img/posts/         → thumbnail-urile articolelor (16:9)
  img/favicon.svg
```

Imaginile incluse sunt SVG-uri generate, doar ca placeholder — le înlocuiești cu
poze reale (JPG/WebP), păstrând raportul 16:9.

## Cum adaugi un slide

Copiezi un bloc din `#slideshow-track` și schimbi `href` + `src`. Punctele de
navigare se generează automat din JS, deci nu trebuie să modifici nimic altundeva.

```html
<a class="slide" href="pagina-ta.html">
  <img src="assets/img/slides/slide-4.jpg" alt="Descriere scurtă" width="1600" height="900" decoding="async">
</a>
```

Intervalul de rulare se schimbă din atributul `data-interval` (milisecunde):

```html
<div class="slideshow" id="slideshow" data-interval="5000">
```

Notă: pe imaginile din slideshow nu se pune `loading="lazy"` — slide-urile care nu
sunt pe ecran nu s-ar încărca la timp și ar apărea gol la trecerea la ele.

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
accesibil — merg și cu săgețile de la tastatură. Sub-comentariile stau într-un
`<ul class="comment__replies">` în interiorul comentariului părinte; formularul
de răspuns se generează din JS la click pe „Răspunde".

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
cu tot cu urcarea copertei și secțiunea de pe profil (266).

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

### Newsletterul

O bifă, pornită din start. Coloana e `NOT NULL DEFAULT 1`, deci și conturile
care există deja se trezesc cu ea pornită, fără vreun `UPDATE` de migrare.
Trimiterea propriu-zisă nu e făcută încă.

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

### Ce nu e făcut

Nu există încă niciun loc unde să citești mesajele din site. Se citesc din
`mesaje_contact` sau din e-mail, până facem panoul de administrare. Coloana
`citit_la` e pregătită pentru atunci.


## Evenimentele

`sql/009-evenimente.sql` aduce `categorii` și `evenimente`, iar
`sql/010-limita-evenimente.sql` coloana prin care se poate ridica, pentru un om
anume, limita de evenimente active. Formularul de publicare e
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

Minimum 300 de caractere, **numărate ca litere, nu ca octeți**. În UTF-8, „ă"
ocupă doi octeți, deci `strlen()` ar fi lăsat să treacă un text de 150 de
caractere scris cu diacritice — cine scrie corect românește ar fi fost
avantajat, ceea ce e o prostie. Server-side se numără cu `mb_strlen()`, iar
contorul din pagină cu `[...text].length`, nu cu `.length`, care numără tot
unități UTF-16.

Paragrafele se păstrează: `curataTextPeRanduri()` normalizează rândurile și
strânge trei sau mai multe rânduri goale la unul singur, dar nu turtește textul
într-un bloc. **În bază intră textul curat, neescapat.** Escaparea se face la
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

Celelalte două casete („Prezent la evenimente", „A confirmat, dar nu a venit")
sunt încă numere scrise de mână: participările nu există în bază.

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
tastare — nu e o eroare de arătat: pagina trimite omul pe prima pagină. E
minimul de care avea nevoie secțiunea de mai sus ca să existe și pentru
vizitatori; adresele frumoase, de forma `/membru/<permalink>`, vin mai târziu.

## Pagina unui eveniment

`event.php?slug=<slug>` — fostul `articol.php`, care era doar un șablon cu
text inventat. Șablonul a rămas, textul e acum din bază.

**Slugul, nu id-ul.** Se citește la telefon, spune despre ce e vorba, și nu dă
în vileag câte evenimente are site-ul. Coada lui întâmplătoare face ca nici
ghicitul să nu ducă undeva.

### Cine intră

Pagina e **strict pentru cine e conectat**. Verificarea se face *înaintea*
căutării în bază, dinadins: așa, cine nu are cont nu poate afla nici măcar dacă
un slug duce undeva.

| starea anunțului | organizatorul | alt membru conectat | nelogat |
|---|---|---|---|
| aprobat | vede | vede | trimis la login |
| în așteptare | vede | prima pagină | trimis la login |
| respins | vede | prima pagină | trimis la login |

**Un slug inexistent și unul interzis sfârșesc la fel: pe prima pagină.** Dacă
„nu există" ar arăta altfel decât „nu ai voie", oricine ar putea afla, ghicind,
ce evenimente așteaptă la moderare.

Organizatorul vede în plus o bandă cu starea anunțului și butonul „Editează"
(care duce la formular — modul de editare se construiește separat).

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

### Ce se arată și ce nu

Ce lipsește nu se arată gol: un rând „Vârstă minimă: —" nu spune nimic, dar
ocupă locul unuia care ar fi spus. Vârsta, participanții și genul apar doar
când sunt completate. Ora de sfârșit lipsă nu se pomenește deloc — scrie doar
„19:00", nu „19:00 — nedeterminat": o mențiune despre ce nu se știe ocupă un
rând ca să nu spună nimic. `cost` gol sau zero devine „Gratuit".

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

**Verificările sunt aceleași.** Descrierea tot de 300 de caractere, titlul tot
de minimum opt — la editare nu se cere mai puțin, altfel s-ar putea publica un
anunț bun și „edita" până rămâne gol.

### Ce nu e făcut

Butonul **„Anulează evenimentul"**, sub „Trimite spre aprobare", e deocamdată
doar desenat: apăsarea lui nu face nimic. Stă despărțit de restul formularului
și în roșu stins, ca zona de ștergere a contului din setări, fiindcă va fi
singura apăsare de pe pagina aia care nu se poate lua înapoi.

„Mergi la acest eveniment?" și comentariile de sub el sunt încă șablonul, cu
numere și oameni inventați — se leagă de bază separat. Cât timp sunt acolo, un
eveniment adevărat arată sub el 128 de „interesați" care nu există.

Nu există încă interfață de aprobare și nici încheiere manuală. Evenimentul
intră cu `stare_moderare = 'in_asteptare'` și nu se vede pe prima pagină; omul
vede doar „Evenimentul tău a fost trimis spre aprobare" — dinadins fără detalii
despre cât durează, cât timp nu putem promite nimic.

Lipsesc și imaginile implicite de categorie (`categorii.imagine_default`):
codul le preferă deja când există, dar fișierele nu sunt urcate, deci un
eveniment fără copertă rămâne fără poza mare.

Verificările: `php teste/test-evenimente.php http://127.0.0.1:8126`
(266 de cazuri, cere serverul pornit).


## E-mailurile

Fișier: `inc/email.php` — un singur șablon pentru toate mesajele, exact ca la
CSS: dacă se schimbă culoarea sau subsolul, se schimbă peste tot dintr-un loc.

Se trimit patru feluri de mesaje: confirmarea adresei (la înregistrare și la
retrimitere), parola temporară și înștiințarea că parola a fost schimbată.

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

2. **Faci baza de date din panoul găzduirii** (cPanel → MySQL Databases), apoi
   un utilizator, apoi **îl legi de bază** — „Add User To Database", cu toate
   drepturile bifate. Legarea e un pas separat și se uită des; fără ea, PHP
   primește „Access denied ... to database".

   Numele bazei și al utilizatorului primesc automat un prefix, de forma
   `numecont_db`. Se folosesc întregi, cu prefix cu tot.

3. **Importi `sql/schema.sql`** în phpMyAdmin: alegi întâi baza din lista din
   stânga, abia apoi „Import". Fără pasul cu alegerea bazei, importul nu are
   unde să scrie.

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

Formularul de publicat un eveniment și paginile de categorie.
