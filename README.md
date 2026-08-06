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

## Convenții

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

## De făcut mai departe

Nimic din meniu nu mai lipsește. Următorii pași firești: formularul de publicat
un eveniment și paginile de categorie.
