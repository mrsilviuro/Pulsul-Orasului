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

Telefonul e singurul câmp opțional; se validează doar dacă a fost completat.

Verificarea se face în JS, nu prin `required` din browser, ca mesajele să fie
în română și în stilul paginii. Se validează la ieșirea din câmp, iar dacă un
câmp e deja marcat greșit, se recontrolează pe măsură ce scrii. La trimitere,
focusul sare pe primul câmp cu probleme.

Formularul nu trimite nimic încă — afișează doar confirmarea. Locul unde legi
serverul e marcat cu `// TODO` în `main.js`; acolo pui un `fetch` către
endpoint-ul tău (sau `action` + `method` pe `<form>`, dacă preferi fără JS).

## De făcut mai departe

`despre.html`, `alatura-te.html` și `login.html` sunt deja legate, dar nu
există încă.
