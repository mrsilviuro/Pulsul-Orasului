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

  /* --- Taburi (refolosibile: orice container cu data-tabs) --- */
  // Merge pe comentariile din articol și pe autentificare/înregistrare deopotrivă.
  document.querySelectorAll('[data-tabs]').forEach(function (tablist) {
    var tabs = Array.prototype.slice.call(tablist.querySelectorAll('[role="tab"]'));
    if (!tabs.length) return;

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

    // permite deschiderea unui tab anume din exterior (ex. #inregistrare)
    tablist.selectTabById = function (id) {
      var tab = tabs.filter(function (t) { return t.id === id; })[0];
      if (tab) selectTab(tab, false);
    };
  });

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


  /* ---------------------- 6. FORMULARUL DE CONTACT ---------------------- */
  var contactForm = document.getElementById('contact-form');

  if (contactForm) {
    var successBox = document.getElementById('form-success');

    // Telefon: acceptăm formatele uzuale din România și cele internaționale
    // (cifre, spații, puncte, cratime, paranteze și prefixul +).
    var phonePattern = /^\+?[\d\s.\-()]{9,20}$/;
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

    var rules = [
      {
        id: 'cf-name', error: 'err-name',
        check: function (v) {
          if (!v) return 'Te rugăm să îți scrii numele.';
          if (v.length < 3) return 'Numele pare prea scurt.';
          return '';
        }
      },
      {
        id: 'cf-email', error: 'err-email',
        check: function (v) {
          if (!v) return 'Avem nevoie de adresa ta de e-mail ca să îți răspundem.';
          if (!emailPattern.test(v)) return 'Adresa de e-mail nu pare validă.';
          return '';
        }
      },
      {
        id: 'cf-phone', error: 'err-phone',
        check: function (v) {
          if (!v) return 'Te rugăm să ne lași un număr de telefon.';
          if (!phonePattern.test(v)) return 'Numărul de telefon nu pare valid.';
          return '';
        }
      },
      {
        id: 'cf-message', error: 'err-message',
        check: function (v) {
          if (!v) return 'Scrie-ne câteva rânduri despre ce e vorba.';
          if (v.length < 10) return 'Mesajul e prea scurt — mai spune-ne câte ceva.';
          return '';
        }
      }
    ];

    function showError(rule, message) {
      var input = document.getElementById(rule.id);
      var box   = document.getElementById(rule.error);
      var field = input.closest('.field');

      if (message) {
        field.classList.add('has-error');
        input.setAttribute('aria-invalid', 'true');
        box.textContent = message;
        box.hidden = false;
      } else {
        field.classList.remove('has-error');
        input.removeAttribute('aria-invalid');
        box.textContent = '';
        box.hidden = true;
      }
      return !message;
    }

    function validate(rule) {
      var input = document.getElementById(rule.id);
      return showError(rule, rule.check(input.value.trim()));
    }

    rules.forEach(function (rule) {
      var input = document.getElementById(rule.id);
      if (!input) return;
      // verificăm la ieșirea din câmp, apoi în timp real doar dacă e deja greșit
      input.addEventListener('blur', function () { validate(rule); });
      input.addEventListener('input', function () {
        if (input.closest('.field').classList.contains('has-error')) validate(rule);
      });
    });

    contactForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (successBox) successBox.hidden = true;

      var firstBad = null;
      rules.forEach(function (rule) {
        if (!validate(rule) && !firstBad) firstBad = document.getElementById(rule.id);
      });

      if (firstBad) {
        firstBad.focus();
        toast('Mai sunt câmpuri de completat.');
        return;
      }

      // TODO: aici se trimite mesajul către server (fetch POST către endpoint-ul tău).
      contactForm.reset();
      if (successBox) successBox.hidden = false;
      toast('Mesajul a fost trimis.');
    });
  }


  /* ------------------ 7. AUTENTIFICARE / ÎNREGISTRARE ------------------- */
  var authTabs = document.getElementById('auth-tabs');

  if (authTabs) {

    /* --- Deschide tabul corect din URL sau din butoanele de sub formular --- */
    // login.html#inregistrare sau ?tab=inregistrare deschide direct înregistrarea.
    var params = new URLSearchParams(window.location.search);
    var wanted = (window.location.hash === '#inregistrare' || params.get('tab') === 'inregistrare')
      ? 'tab-register' : null;
    if (wanted) authTabs.selectTabById(wanted);

    document.querySelectorAll('[data-go-tab]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        authTabs.selectTabById(btn.getAttribute('data-go-tab'));
        authTabs.scrollIntoView({ block: 'nearest' });
      });
    });

    /* --- Mesajul „intră în cont ca să continui" --- */
    // Acceptăm doar căi relative la site-ul nostru, ca parametrul redirect să nu
    // poată fi folosit pentru a trimite utilizatorul pe un domeniu străin.
    function safeRedirect() {
      var value = params.get('redirect');
      if (!value) return '';
      if (value.charAt(0) !== '/' || value.charAt(1) === '/') return '';
      return value;
    }

    var backTo = safeRedirect();
    var notice = document.getElementById('auth-notice');
    if (backTo && notice) notice.hidden = false;

    function afterAuth(message) {
      toast(message);
      setTimeout(function () {
        window.location.href = backTo || 'index.html';
      }, 1000);
    }

    /* --- Butonul de afișare a parolei --- */
    document.querySelectorAll('[data-toggle-pass]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = document.getElementById(btn.getAttribute('data-toggle-pass'));
        if (!input) return;
        var shown = input.type === 'text';
        input.type = shown ? 'password' : 'text';
        btn.setAttribute('aria-pressed', String(!shown));
        btn.setAttribute('aria-label', shown ? 'Arată parola' : 'Ascunde parola');
      });
    });

    /* --- Butoanele Google --- */
    document.querySelectorAll('[data-google]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        // TODO: aici pornești fluxul OAuth (redirect spre /auth/google
        // sau apel către SDK-ul Google Identity Services).
        toast('Autentificarea cu Google se leagă la implementarea serverului.');
      });
    });

    /* --- Verificări comune --- */
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

    function fieldOf(id) { return document.getElementById(id).closest('.field'); }

    function setError(id, errorId, message) {
      var input = document.getElementById(id);
      var boxEl = document.getElementById(errorId);
      var field = fieldOf(id);

      if (message) {
        field.classList.add('has-error');
        input.setAttribute('aria-invalid', 'true');
        boxEl.textContent = message;
        boxEl.hidden = false;
      } else {
        field.classList.remove('has-error');
        input.removeAttribute('aria-invalid');
        boxEl.textContent = '';
        boxEl.hidden = true;
      }
      return !message;
    }

    // Leagă un set de reguli la un formular: validare la blur, recontrol în
    // timp real după prima eroare, iar la submit focus pe primul câmp greșit.
    function wireForm(form, rules, onValid) {
      function validate(rule) {
        var input = document.getElementById(rule.id);
        var value = input.type === 'checkbox' ? input.checked : input.value.trim();
        return setError(rule.id, rule.error, rule.check(value));
      }

      rules.forEach(function (rule) {
        var input = document.getElementById(rule.id);
        if (!input) return;
        var event = (input.type === 'checkbox' || input.tagName === 'SELECT') ? 'change' : 'blur';
        input.addEventListener(event, function () { validate(rule); });
        input.addEventListener('input', function () {
          if (fieldOf(rule.id).classList.contains('has-error')) validate(rule);
        });
      });

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var firstBad = null;
        rules.forEach(function (rule) {
          if (!validate(rule) && !firstBad) firstBad = document.getElementById(rule.id);
        });
        if (firstBad) {
          firstBad.focus();
          toast('Mai sunt câmpuri de completat.');
          return;
        }
        onValid();
      });
    }

    /* --- Formularul de autentificare --- */
    var loginForm = document.getElementById('login-form');

    if (loginForm) {
      wireForm(loginForm, [
        {
          id: 'lg-email', error: 'err-lg-email',
          check: function (v) {
            if (!v) return 'Scrie adresa de e-mail.';
            if (!emailPattern.test(v)) return 'Adresa de e-mail nu pare validă.';
            return '';
          }
        },
        {
          id: 'lg-password', error: 'err-lg-password',
          check: function (v) { return v ? '' : 'Scrie parola.'; }
        }
      ], function () {
        // TODO: trimite datele către server și tratează răspunsul
        // (parolă greșită, cont inexistent etc.).
        afterAuth('Bine ai revenit!');
      });
    }

    /* --- Puterea parolei --- */
    // Punctaj simplu, orientativ: lungime + varietate de caractere.
    function passwordScore(value) {
      if (!value) return 0;
      var score = 0;
      if (value.length >= 8)  score++;
      if (value.length >= 12) score++;
      if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
      if (/\d/.test(value) && /[^\w\s]/.test(value)) score++;
      return Math.min(score, 4);
    }

    var passInput = document.getElementById('rg-password');
    var passMeter = document.getElementById('pass-meter');
    var passHint  = document.getElementById('pass-hint');
    var labels    = ['', 'Slabă', 'Acceptabilă', 'Bună', 'Puternică'];

    if (passInput && passMeter) {
      passInput.addEventListener('input', function () {
        var value = passInput.value;
        if (!value) { passMeter.hidden = true; return; }
        var score = passwordScore(value);
        passMeter.hidden = false;
        passMeter.setAttribute('data-score', String(score));
        passHint.textContent = labels[score];
      });
    }

    /* --- Formularul de înregistrare --- */
    var registerForm = document.getElementById('register-form');

    if (registerForm) {
      var minAge = 13;

      wireForm(registerForm, [
        {
          id: 'rg-lastname', error: 'err-rg-lastname',
          check: function (v) {
            if (!v) return 'Scrie numele.';
            if (v.length < 2) return 'Numele pare prea scurt.';
            return '';
          }
        },
        {
          id: 'rg-firstname', error: 'err-rg-firstname',
          check: function (v) {
            if (!v) return 'Scrie prenumele.';
            if (v.length < 2) return 'Prenumele pare prea scurt.';
            return '';
          }
        },
        {
          id: 'rg-email', error: 'err-rg-email',
          check: function (v) {
            if (!v) return 'Scrie adresa de e-mail.';
            if (!emailPattern.test(v)) return 'Adresa de e-mail nu pare validă.';
            return '';
          }
        },
        {
          id: 'rg-birthdate', error: 'err-rg-birthdate',
          check: function (v) {
            if (!v) return 'Alege data nașterii.';
            var born = new Date(v + 'T00:00:00');
            if (isNaN(born.getTime())) return 'Data nu pare validă.';

            var today = new Date();
            if (born > today) return 'Data nașterii nu poate fi în viitor.';
            if (born.getFullYear() < 1900) return 'Data nu pare validă.';

            var age = today.getFullYear() - born.getFullYear();
            var m = today.getMonth() - born.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < born.getDate())) age--;
            if (age < minAge) return 'Trebuie să ai cel puțin ' + minAge + ' ani.';
            return '';
          }
        },
        {
          id: 'rg-gender', error: 'err-rg-gender',
          check: function (v) { return v ? '' : 'Alege o opțiune.'; }
        },
        {
          id: 'rg-password', error: 'err-rg-password',
          check: function (v) {
            if (!v) return 'Alege o parolă.';
            if (v.length < 8) return 'Parola trebuie să aibă minimum 8 caractere.';
            if (passwordScore(v) < 2) return 'Parola e prea simplă — adaugă cifre sau litere mari.';
            return '';
          }
        },
        {
          id: 'rg-password2', error: 'err-rg-password2',
          check: function (v) {
            if (!v) return 'Scrie parola din nou.';
            if (v !== document.getElementById('rg-password').value) return 'Cele două parole nu coincid.';
            return '';
          }
        },
        {
          id: 'rg-terms', error: 'err-rg-terms',
          check: function (checked) {
            return checked ? '' : 'Trebuie să accepți termenii ca să continui.';
          }
        }
      ], function () {
        // TODO: trimite datele către server (verifică acolo dacă e-mailul
        // e deja folosit) și confirmă adresa prin e-mail.
        afterAuth('Contul a fost creat. Bine ai venit!');
      });

      // Când schimbi parola, reverificăm și confirmarea, dacă era deja greșită.
      var pass2 = document.getElementById('rg-password2');
      if (passInput && pass2) {
        passInput.addEventListener('input', function () {
          if (pass2.value && fieldOf('rg-password2').classList.contains('has-error')) {
            setError('rg-password2', 'err-rg-password2',
              pass2.value === passInput.value ? '' : 'Cele două parole nu coincid.');
          }
        });
      }
    }
  }

})();
