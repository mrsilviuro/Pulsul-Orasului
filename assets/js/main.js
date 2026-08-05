/* =========================================================================
   PulsulOrasului.Ro — main.js
   1. Temă light / dark
   2. Meniu mobil
   3. Slideshow
   4. Diverse
   ========================================================================= */
(function () {
  'use strict';

  /* --------------------------- 1. TEMĂ ---------------------------------- */
  var root   = document.documentElement;
  var toggle = document.getElementById('theme-toggle');
  var mq     = window.matchMedia('(prefers-color-scheme: dark)');

  function store(key, value) {
    try { localStorage.setItem(key, value); } catch (e) {}
  }
  function read(key) {
    try { return localStorage.getItem(key); } catch (e) { return null; }
  }

  function setTheme(theme, persist) {
    root.setAttribute('data-theme', theme);
    if (persist) store('po-theme', theme);
    if (toggle) {
      toggle.setAttribute('aria-label',
        theme === 'dark' ? 'Comută pe tema luminoasă' : 'Comută pe tema întunecată');
    }
  }

  // Tema inițială e deja aplicată de scriptul inline din <head>.
  // Aici doar sincronizăm eticheta butonului.
  setTheme(root.getAttribute('data-theme') || 'light', false);

  if (toggle) {
    toggle.addEventListener('click', function () {
      // animăm tranziția doar după prima interacțiune (evită flash-ul la load)
      document.body.classList.add('theme-anim');
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      setTheme(next, true);
    });
  }

  // Dacă utilizatorul nu a ales manual, urmărim setarea sistemului în timp real.
  var onSystemChange = function (e) {
    if (!read('po-theme')) setTheme(e.matches ? 'dark' : 'light', false);
  };
  if (mq.addEventListener) mq.addEventListener('change', onSystemChange);
  else if (mq.addListener) mq.addListener(onSystemChange);


  /* ------------------------ 2. MENIU MOBIL ------------------------------ */
  var burger = document.getElementById('nav-burger');
  var menu   = document.getElementById('nav-menu');

  if (burger && menu) {
    var closeMenu = function () {
      menu.classList.remove('is-open');
      burger.setAttribute('aria-expanded', 'false');
      burger.setAttribute('aria-label', 'Deschide meniul');
    };

    burger.addEventListener('click', function () {
      var open = menu.classList.toggle('is-open');
      burger.setAttribute('aria-expanded', String(open));
      burger.setAttribute('aria-label', open ? 'Închide meniul' : 'Deschide meniul');
    });

    menu.addEventListener('click', function (e) {
      if (e.target.closest('a')) closeMenu();
    });

    document.addEventListener('click', function (e) {
      if (!menu.contains(e.target) && !burger.contains(e.target)) closeMenu();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeMenu();
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth > 800) closeMenu();
    });
  }


  /* -------------------------- 3. SLIDESHOW ------------------------------ */
  var box = document.getElementById('slideshow');

  if (box) {
    var track    = document.getElementById('slideshow-track');
    var dotsWrap = document.getElementById('slideshow-dots');
    var slides   = Array.prototype.slice.call(track.querySelectorAll('.slide'));
    var interval = parseInt(box.getAttribute('data-interval'), 10) || 5000;
    var index    = 0;
    var timer    = null;
    var reduced  = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (slides.length) {
      // Punctele se generează automat → adaugi un <a class="slide"> și gata.
      var dots = slides.map(function (slide, i) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'slideshow__dot';
        dot.setAttribute('role', 'tab');
        dot.setAttribute('aria-label', 'Slide ' + (i + 1) + ' din ' + slides.length);
        dot.addEventListener('click', function () { goTo(i); restart(); });
        dotsWrap.appendChild(dot);
        return dot;
      });

      function goTo(i) {
        index = (i + slides.length) % slides.length;
        track.style.transform = 'translate3d(' + (-100 * index) + '%,0,0)';

        slides.forEach(function (slide, n) {
          var hidden = n !== index;
          slide.setAttribute('aria-hidden', String(hidden));
          // slide-urile ascunse ies din ordinea de tabulare
          slide.setAttribute('tabindex', hidden ? '-1' : '0');
        });
        dots.forEach(function (dot, n) {
          dot.classList.toggle('is-active', n === index);
          dot.setAttribute('aria-selected', String(n === index));
        });
      }

      function next(step) { goTo(index + (step || 1)); }

      function play() {
        if (reduced || slides.length < 2) return;
        stop();
        timer = setInterval(function () { next(1); }, interval);
      }
      function stop()    { if (timer) { clearInterval(timer); timer = null; } }
      function restart() { stop(); play(); }

      // Săgeți
      box.querySelectorAll('.slideshow__arrow').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          next(parseInt(btn.getAttribute('data-dir'), 10));
          restart();
        });
      });

      // Pauză la hover / focus / tab inactiv
      box.addEventListener('mouseenter', stop);
      box.addEventListener('mouseleave', play);
      box.addEventListener('focusin', stop);
      box.addEventListener('focusout', play);
      document.addEventListener('visibilitychange', function () {
        document.hidden ? stop() : play();
      });

      // Tastatură
      box.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft')  { next(-1); restart(); }
        if (e.key === 'ArrowRight') { next(1);  restart(); }
      });

      // Swipe pe mobil
      var startX = 0, startY = 0, swiping = false;
      box.addEventListener('touchstart', function (e) {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        swiping = true;
        stop();
      }, { passive: true });

      box.addEventListener('touchend', function (e) {
        if (!swiping) return;
        swiping = false;
        var dx = e.changedTouches[0].clientX - startX;
        var dy = e.changedTouches[0].clientY - startY;
        if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) {
          // împiedicăm click-ul pe link după swipe
          e.preventDefault();
          next(dx < 0 ? 1 : -1);
        }
        play();
      });

      goTo(0);
      play();
    }
  }


  /* --------------------------- 4. DIVERSE ------------------------------- */
  var year = document.getElementById('year');
  if (year) year.textContent = new Date().getFullYear();

  // Filtre categorii (deocamdată doar starea vizuală)
  var chips = document.querySelectorAll('.chip');
  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      chips.forEach(function (c) {
        c.classList.remove('is-active');
        c.setAttribute('aria-selected', 'false');
      });
      chip.classList.add('is-active');
      chip.setAttribute('aria-selected', 'true');
    });
  });

})();
