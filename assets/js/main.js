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


  /* ----------------------- 5. PAGINA DE ARTICOL ------------------------- */

  var body = document.body;

  // Steagul de autentificare vine din <body data-logged-in="...">.
  // Când implementezi login-ul, îl pui pe "true" din server.
  function isLoggedIn() { return body.getAttribute('data-logged-in') === 'true'; }

  function currentUser() {
    return {
      name:   body.getAttribute('data-user-name')   || 'Utilizator',
      avatar: body.getAttribute('data-user-avatar') || 'assets/img/avatars/cristi.svg'
    };
  }

  // Trimite spre login, păstrând pagina curentă ca destinație de întoarcere.
  function goToLogin() {
    var back = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.href = 'login.html?redirect=' + back;
  }

  // Mesaj scurt jos, pe mijloc
  var toastEl = document.getElementById('toast');
  var toastTimer = null;
  function toast(message) {
    if (!toastEl) return;
    toastEl.textContent = message;
    toastEl.classList.add('is-visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('is-visible'); }, 2600);
  }

  /* --- Taburi (comentarii / interesați / participă) --- */
  var tablist = document.querySelector('.tabs');

  if (tablist) {
    var tabs = Array.prototype.slice.call(tablist.querySelectorAll('.tab'));

    function selectTab(tab, focus) {
      tabs.forEach(function (t) {
        var on = t === tab;
        t.classList.toggle('is-active', on);
        t.setAttribute('aria-selected', String(on));
        t.setAttribute('tabindex', on ? '0' : '-1');

        var panel = document.getElementById(t.getAttribute('aria-controls'));
        if (panel) {
          panel.classList.toggle('is-active', on);
          panel.hidden = !on;
        }
      });
      if (focus) tab.focus();
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () { selectTab(tab, false); });
    });

    // navigare cu săgeți, cum se așteaptă la un tablist
    tablist.addEventListener('keydown', function (e) {
      var i = tabs.indexOf(document.activeElement);
      if (i < 0) return;
      var next = null;
      if (e.key === 'ArrowRight') next = tabs[(i + 1) % tabs.length];
      if (e.key === 'ArrowLeft')  next = tabs[(i - 1 + tabs.length) % tabs.length];
      if (e.key === 'Home')       next = tabs[0];
      if (e.key === 'End')        next = tabs[tabs.length - 1];
      if (next) { e.preventDefault(); selectTab(next, true); }
    });
  }

  /* --- Butoanele de participare --- */
  var rsvpButtons = document.querySelectorAll('[data-rsvp]');

  // Actualizează toate locurile unde apare numărul (buton, tab, text din panou).
  function setRsvpCount(kind, value) {
    document.querySelectorAll('[data-count-for="' + kind + '"]').forEach(function (el) {
      el.textContent = value;
    });
  }

  rsvpButtons.forEach(function (btn) {
    var kind = btn.getAttribute('data-rsvp');
    var base = parseInt(btn.getAttribute('data-count'), 10) || 0;

    btn.addEventListener('click', function () {
      if (!isLoggedIn()) {
        toast('Intră în cont ca să te adaugi pe listă.');
        setTimeout(goToLogin, 900);
        return;
      }

      var on = btn.getAttribute('aria-pressed') === 'true';
      btn.setAttribute('aria-pressed', String(!on));
      setRsvpCount(kind, base + (!on ? 1 : 0));

      // „Particip" implică „mă interesează": bifând unul, îl scoatem pe celălalt.
      if (!on) {
        rsvpButtons.forEach(function (other) {
          if (other === btn || other.getAttribute('aria-pressed') !== 'true') return;
          var otherKind = other.getAttribute('data-rsvp');
          other.setAttribute('aria-pressed', 'false');
          setRsvpCount(otherKind, parseInt(other.getAttribute('data-count'), 10) || 0);
        });
      }

      toast(!on
        ? (kind === 'going' ? 'Te-am trecut pe lista de participanți.' : 'Te-am trecut la interesați.')
        : 'Te-am scos de pe listă.');

      // TODO: aici va veni cererea către server (fetch POST cu id-ul evenimentului).
    });
  });

  /* --- Comentarii: like --- */
  document.querySelectorAll('[data-like]').forEach(function (btn) {
    var counter = btn.querySelector('[data-like-count]');
    var base = counter ? parseInt(counter.textContent, 10) || 0 : 0;

    btn.addEventListener('click', function () {
      if (!isLoggedIn()) {
        toast('Intră în cont ca să apreciezi un comentariu.');
        setTimeout(goToLogin, 900);
        return;
      }
      var on = btn.getAttribute('aria-pressed') === 'true';
      btn.setAttribute('aria-pressed', String(!on));
      if (counter) counter.textContent = base + (!on ? 1 : 0);
    });
  });

  /* --- Comentarii: răspuns (sub-comentariu) --- */
  document.querySelectorAll('[data-reply]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!isLoggedIn()) {
        toast('Intră în cont ca să răspunzi.');
        setTimeout(goToLogin, 900);
        return;
      }

      var main = btn.closest('.comment__main');
      var existing = main.querySelector(':scope > .reply-form');

      // al doilea click închide formularul
      if (existing) { existing.remove(); btn.textContent = 'Răspunde'; return; }

      var user = currentUser();
      var form = document.createElement('form');
      form.className = 'reply-form';
      form.innerHTML =
        '<img class="reply-form__avatar" src="' + user.avatar + '" alt="" width="96" height="96">' +
        '<div class="reply-form__main">' +
          '<textarea rows="2" placeholder="Scrie un răspuns…" aria-label="Scrie un răspuns"></textarea>' +
          '<div class="reply-form__actions">' +
            '<button class="btn btn--primary btn--xs" type="submit">Trimite</button>' +
            '<button class="btn btn--text" type="button" data-cancel-reply>Renunță</button>' +
          '</div>' +
        '</div>';

      // inserăm înaintea listei de răspunsuri, dacă există
      var replies = main.querySelector(':scope > .comment__replies');
      if (replies) main.insertBefore(form, replies);
      else main.appendChild(form);

      btn.textContent = 'Anulează';
      form.querySelector('textarea').focus();

      form.querySelector('[data-cancel-reply]').addEventListener('click', function () {
        form.remove();
        btn.textContent = 'Răspunde';
      });

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = form.querySelector('textarea').value.trim();
        if (!text) return;
        // TODO: trimitere către server; deocamdată doar confirmăm.
        form.remove();
        btn.textContent = 'Răspunde';
        toast('Răspunsul tău a fost trimis.');
      });
    });
  });

  /* --- Formularul de comentariu nou --- */
  document.querySelectorAll('[data-comment-form]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!isLoggedIn()) {
        toast('Intră în cont ca să comentezi.');
        setTimeout(goToLogin, 900);
        return;
      }
      var field = form.querySelector('textarea');
      if (!field.value.trim()) return;
      field.value = '';
      toast('Comentariul tău a fost trimis.');
    });
  });

  // Câmpurile care cer cont trimit spre login la focus, ca să nu scrie degeaba.
  if (!isLoggedIn()) {
    document.querySelectorAll('[data-comment-form] textarea').forEach(function (field) {
      field.addEventListener('focus', function () {
        field.blur();
        toast('Intră în cont ca să comentezi.');
        setTimeout(goToLogin, 900);
      });
    });
  }

  /* --- Copiază linkul articolului --- */
  var copyBtn = document.getElementById('copy-link');
  if (copyBtn) {
    copyBtn.addEventListener('click', function () {
      var url = window.location.href;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(
          function () { toast('Linkul a fost copiat.'); },
          function () { toast('Nu am putut copia linkul.'); }
        );
      } else {
        toast('Nu am putut copia linkul.');
      }
    });
  }

  /* --- Bara de progres a citirii --- */
  var progress = document.querySelector('#read-progress span');
  var postBody = document.querySelector('.post__body');

  if (progress && postBody) {
    var updateProgress = function () {
      var rect = postBody.getBoundingClientRect();
      var start = window.scrollY + rect.top;
      var total = rect.height - window.innerHeight;
      var done = total > 0 ? (window.scrollY - start) / total : (window.scrollY > start ? 1 : 0);
      progress.style.width = Math.max(0, Math.min(1, done)) * 100 + '%';
    };
    window.addEventListener('scroll', updateProgress, { passive: true });
    window.addEventListener('resize', updateProgress);
    updateProgress();
  }

})();
