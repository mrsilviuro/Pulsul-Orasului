# PulsulOrasului.ro — șablon blog

Șablon static (HTML/CSS/JS, fără build step) pentru un blog personal despre evenimente locale, sportive și culturale.

## Structură

```
index.html          Pagina principală (slideshow + grilă articole)
despre.html          Despre
alatura-te.html      Alătură-te și tu
contact.html         Contact (formular)
articol.html         Exemplu de pagină pentru un articol individual
css/style.css        Tot stilul, cu variabile pentru tema light/dark
js/main.js           Toggle temă, meniu mobil, slideshow
assets/img/          Imagini placeholder (SVG, 16:9) — înlocuiește-le cu poze reale
```

## Cum rulezi local

Nu ai nevoie de build. Deschide `index.html` direct în browser, sau pornește un server simplu:

```
python3 -m http.server 8000
```

apoi accesează `http://localhost:8000`.

## Cum adaugi un slide nou în header

În `index.html`, în interiorul `<div class="slides">`, copiază un bloc:

```html
<a class="slide" href="pagina-ta.html" aria-label="Descriere">
  <img src="assets/img/slide-4.svg" alt="Descriere">
</a>
```

Scriptul din `js/main.js` detectează automat toate slide-urile și adaugă punctele de navigare — nu trebuie modificat nimic în JS.

## Cum adaugi un articol nou

Copiază un bloc `<a class="card">...</a>` din `index.html`, schimbă imaginea (16:9), categoria, titlul, textul și data. Pentru pagina articolului, duplică `articol.html` și completează conținutul.

## Temă light/dark

Tema se setează automat după preferința sistemului (`prefers-color-scheme`) la prima vizită, iar butonul din meniu permite schimbarea manuală — alegerea se salvează în `localStorage`.
