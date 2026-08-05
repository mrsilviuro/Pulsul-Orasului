// ============================================================
// PulsulOrasului.ro — nav mobil + toggle temă (fallback) + slideshow
// Setarea temei se face devreme în <head> (inline script) ca să nu apară "flash".
// ============================================================

document.addEventListener("DOMContentLoaded", function () {
  // ---------- Theme toggle ----------
  var themeToggle = document.getElementById("theme-toggle");
  if (themeToggle) {
    themeToggle.addEventListener("click", function () {
      var root = document.documentElement;
      var current = root.getAttribute("data-theme");
      var next = current === "dark" ? "light" : "dark";
      root.setAttribute("data-theme", next);
      localStorage.setItem("theme", next);
    });
  }

  // ---------- Mobile menu ----------
  var burger = document.getElementById("nav-burger");
  var navLinks = document.getElementById("nav-links");
  if (burger && navLinks) {
    burger.addEventListener("click", function () {
      var isOpen = navLinks.classList.toggle("open");
      burger.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });
    navLinks.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        navLinks.classList.remove("open");
        burger.setAttribute("aria-expanded", "false");
      });
    });
  }

  // ---------- Hero slideshow ----------
  // Ca sa adaugi un slide nou: adauga in HTML un nou <a class="slide"> in interiorul
  // .slides (vezi index.html) — scriptul de mai jos detecteaza automat toate slide-urile,
  // fara nicio modificare de cod.
  var slider = document.getElementById("hero-slider");
  if (slider) {
    var slidesWrap = slider.querySelector(".slides");
    var slides = Array.prototype.slice.call(slidesWrap.querySelectorAll(".slide"));
    var dotsWrap = slider.querySelector(".slider-dots");
    var prevBtn = slider.querySelector(".slider-arrow.prev");
    var nextBtn = slider.querySelector(".slider-arrow.next");
    var current = slides.findIndex(function (s) { return s.classList.contains("active"); });
    if (current < 0) current = 0;
    var AUTOPLAY_MS = 5000;
    var timer = null;

    // build dots dynamically based on however many slides exist
    var dots = slides.map(function (_, i) {
      var dot = document.createElement("button");
      dot.className = "slider-dot" + (i === current ? " active" : "");
      dot.setAttribute("aria-label", "Mergi la slide-ul " + (i + 1));
      dot.addEventListener("click", function () { goTo(i); restart(); });
      dotsWrap.appendChild(dot);
      return dot;
    });

    function render() {
      slides.forEach(function (s, i) { s.classList.toggle("active", i === current); });
      dots.forEach(function (d, i) { d.classList.toggle("active", i === current); });
    }

    function goTo(i) {
      current = (i + slides.length) % slides.length;
      render();
    }

    function next() { goTo(current + 1); }
    function prev() { goTo(current - 1); }

    function restart() {
      if (timer) clearInterval(timer);
      timer = setInterval(next, AUTOPLAY_MS);
    }

    if (nextBtn) nextBtn.addEventListener("click", function () { next(); restart(); });
    if (prevBtn) prevBtn.addEventListener("click", function () { prev(); restart(); });

    if (slides.length > 1) restart();
  }
});
