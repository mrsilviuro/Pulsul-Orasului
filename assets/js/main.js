/* =========================================================================
   PulsulOrasului.Ro — main.js
   1. Temă light / dark
   2. Meniu mobil
   3. Slideshow
   4. Diverse
   5. Pagina de articol (taburi, participare, comentarii)
   6. Formularul de contact
   7. Autentificare / înregistrare
   7b. Parola uitată și parola nouă
   8. Stele și pagina de profil
   9. Poza de profil
   10. Setările contului
   11. Publicarea unui eveniment
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

  // Starea de autentificare vine din sesiunea de pe server: inc/antet.php
  // scrie data-logat pe <body> la fiecare încărcare de pagină.
  function isLoggedIn() { return body.getAttribute('data-logat') === 'true'; }

  function currentUser() {
    return {
      name:   body.getAttribute('data-user-nume') || 'Utilizator',
      avatar: 'assets/img/avatars/cristi.svg'
    };
  }

  // Trimite spre login, păstrând pagina curentă ca destinație de întoarcere.
  function goToLogin() {
    var back = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.href = 'login.php?redirect=' + back;
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

  // Mesajul lăsat de server pentru pagina asta (vezi inc/subsol.php).
  if (toastEl && toastEl.getAttribute('data-mesaj')) {
    toast(toastEl.getAttribute('data-mesaj'));
    toastEl.removeAttribute('data-mesaj');
  }

  /* --- Vorbitul cu serverul ----------------------------------------------
     Toate formularele trimit cu fetch și așteaptă JSON înapoi. Când primesc
     altceva — o eroare de PHP, o pagină de la găzduire, un fișier de setări
     lipsă — `r.json()` aruncă, iar codul ajungea în `.catch()`, care spunea
     „verifică conexiunea".

     Mesajul acela era greșit și trimitea omul să caute unde nu e: serverul
     RĂSPUNSESE, doar că nu cu ce trebuie. De aceea citim întâi textul brut și
     abia apoi încercăm să-l înțelegem, ca să putem spune care din cele trei
     lucruri s-a întâmplat:

       - JSON valid            → { corp: {...} }
       - alt răspuns           → { corp: null, brut: "textul primit" }
       - cererea n-a ajuns     → fetch respinge, deci se ajunge în .catch()
  */
  function citesteRaspuns(r) {
    return r.text().then(function (text) {
      try {
        return { stare: r.status, corp: JSON.parse(text), brut: text };
      } catch (e) {
        return { stare: r.status, corp: null, brut: text };
      }
    });
  }

  /**
   * Ce spunem când serverul a răspuns, dar nu cu JSON.
   *
   * Textul întreg se scrie în consolă: acolo se vede eroarea de PHP, singurul
   * lucru care ajută la reparat. În pagină nu-l arătăm, pentru că poate
   * conține căi de pe server sau nume de tabele.
   */
  function mesajRaspunsNeasteptat(rez) {
    if (window.console && console.error) {
      console.error('Răspuns neașteptat de la server (HTTP ' + rez.stare + '):\n' +
                    (rez.brut || '(gol)'));
    }
    return 'Serverul a răspuns cu o eroare (HTTP ' + rez.stare +
           '). Apasă F12 și uită-te în „Console" ca să vezi ce a spus.';
  }

  /** Când cererea nu a plecat deloc: internet căzut, adresă greșită. */
  function mesajFaraLegatura() {
    return 'Nu am putut lua legătura cu serverul. Verifică internetul și încearcă din nou.';
  }

  /* --- Formulare: bucățile folosite de mai multe pagini -------------------
     Stau aici, în afara oricărei pagini anume, pentru că le folosesc și
     autentificarea, și înregistrarea, și cele două pagini de parolă. Aceeași
     regulă ca la CSS: un singur loc, nu câte o copie pentru fiecare pagină. */

  var tiparEmail = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

  function campul(id) {
    var input = document.getElementById(id);
    return input ? input.closest('.field') : null;
  }

  /** Pune sau șterge mesajul de eroare al unui câmp. Întoarce true dacă e bun. */
  function setError(id, errorId, message) {
    var input = document.getElementById(id);
    var boxEl = document.getElementById(errorId);
    var field = campul(id);
    if (!input || !boxEl || !field) return !message;

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

  /** Butonul cu ochiul, care arată parola. Merge pe orice pagină. */
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

  /** Punctaj simplu, orientativ: lungime plus varietate de caractere. */
  function putereParolei(value) {
    if (!value) return 0;
    var score = 0;
    if (value.length >= 8)  score++;
    if (value.length >= 12) score++;
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
    if (/\d/.test(value) && /[^\w\s]/.test(value)) score++;
    return Math.min(score, 4);
  }

  /**
   * Verifică o dată a nașterii. Întoarce '' dacă e bună, altfel mesajul.
   *
   * Folosită și la înregistrarea obișnuită, și la finalizarea celei cu Google.
   * Rămâne doar o comoditate pentru cel care completează — adevărata verificare
   * se face pe server, în verificaDataNasterii().
   */
  var VARSTA_MINIMA = 13;

  function verificaDataNasteriiInPagina(v) {
    if (!v) return 'Alege data nașterii.';

    var born = new Date(v + 'T00:00:00');
    if (isNaN(born.getTime())) return 'Data nu pare validă.';

    var today = new Date();
    if (born > today) return 'Data nașterii nu poate fi în viitor.';
    if (born.getFullYear() < 1900) return 'Data nu pare validă.';

    var age = today.getFullYear() - born.getFullYear();
    var m = today.getMonth() - born.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < born.getDate())) age--;
    if (age < VARSTA_MINIMA) return 'Trebuie să ai cel puțin ' + VARSTA_MINIMA + ' ani.';

    return '';
  }

  /** Leagă indicatorul de putere de un câmp de parolă. */
  function legIndicatorulDeParola(idInput, idMetru, idText) {
    var input = document.getElementById(idInput);
    var metru = document.getElementById(idMetru);
    var text  = document.getElementById(idText);
    if (!input || !metru) return;

    var etichete = ['', 'Slabă', 'Acceptabilă', 'Bună', 'Puternică'];

    input.addEventListener('input', function () {
      if (!input.value) { metru.hidden = true; return; }
      var scor = putereParolei(input.value);
      metru.hidden = false;
      metru.setAttribute('data-score', String(scor));
      if (text) text.textContent = etichete[scor];
    });
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

    // Un membru conectat are numele, adresa și (poate) telefonul luate din
    // cont, blocate în pagină. Pe acelea nu le mai verificăm aici.
    var eLogat = contactForm.getAttribute('data-logat') === 'true';

    function blocat(id) {
      var el = document.getElementById(id);
      return !el || el.readOnly;
    }

    var toateReguli = [
      {
        id: 'cf-name', error: 'err-name',
        check: function (v) {
          if (!v) return 'Scrie-ți numele și prenumele.';
          if (v.indexOf(' ') < 0) return 'Scrie și numele, și prenumele.';
          return '';
        }
      },
      {
        id: 'cf-email', error: 'err-email',
        check: function (v) {
          if (!v) return 'Avem nevoie de adresa ta de e-mail ca să îți răspundem.';
          if (!tiparEmail.test(v)) return 'Adresa de e-mail nu pare validă.';
          return '';
        }
      },
      {
        id: 'cf-phone', error: 'err-phone',
        check: function (v) {
          if (!v) return 'Scrie un număr de telefon.';
          // Aceeași regulă ca pe server (verificaTelefon din inc/validare.php):
          // prefixele +40 / 0040 se aduc la 0, apoi zece cifre, 07, 02 sau 03.
          var cifre = v.replace(/[\s.\-()\/]+/g, '');
          if (/^\+40/.test(cifre))      cifre = '0' + cifre.slice(3);
          else if (/^0040/.test(cifre)) cifre = '0' + cifre.slice(4);
          else if (/^40/.test(cifre) && cifre.length === 11) cifre = '0' + cifre.slice(2);
          if (!/^0[237]\d{8}$/.test(cifre)) {
            return 'Numărul nu pare românesc. Zece cifre, începând cu 07, 02 sau 03.';
          }
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

    var rules = toateReguli.filter(function (r) { return !blocat(r.id); });

    // setError e cel folosit de toate celelalte formulare de pe site (vezi mai
    // sus). Formularul ăsta avea o copie a lui, scrisă înainte să existe cea
    // comună; acum o folosește pe aceea.
    function validate(rule) {
      var input = document.getElementById(rule.id);
      return setError(rule.id, rule.error, rule.check(input.value.trim()));
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

      var buton = contactForm.querySelector('button[type=submit]');
      var eticheta = buton.querySelector('span');
      var textInitial = eticheta ? eticheta.textContent : buton.textContent;
      buton.disabled = true;
      if (eticheta) eticheta.textContent = 'Se trimite…';

      function gata() {
        buton.disabled = false;
        if (eticheta) eticheta.textContent = textInitial;
      }

      fetch('api/contact.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          csrf: (contactForm.querySelector('[name="csrf"]') || {}).value || '',
          nume: document.getElementById('cf-name').value,
          email: document.getElementById('cf-email').value,
          telefon: document.getElementById('cf-phone').value,
          mesaj: document.getElementById('cf-message').value,
          // Capcana pleacă goală de la un om. De la un robot, nu.
          website: (document.getElementById('cf-website') || {}).value || ''
        })
      })
      .then(citesteRaspuns)
      .then(function (rez) {
        gata();

        if (!rez.corp) { toast(mesajRaspunsNeasteptat(rez)); return; }
        var c = rez.corp;

        if (c.erori) {
          var primul = null;
          [['nume', 'cf-name', 'err-name'],
           ['email', 'cf-email', 'err-email'],
           ['telefon', 'cf-phone', 'err-phone'],
           ['mesaj', 'cf-message', 'err-message']].forEach(function (p) {
            var mesaj = c.erori[p[0]] || '';
            setError(p[1], p[2], mesaj);
            if (mesaj && !primul) primul = document.getElementById(p[1]);
          });
          if (primul) primul.focus();
          toast('Mai sunt câmpuri de corectat.');
          return;
        }

        if (!c.ok) { toast(c.mesaj || 'Nu am putut trimite mesajul.'); return; }

        // Câmpurile luate din cont rămân pe loc: omul le are tot acolo.
        document.getElementById('cf-message').value = '';
        if (!eLogat) {
          document.getElementById('cf-name').value = '';
          document.getElementById('cf-email').value = '';
        }
        if (!blocat('cf-phone') && eLogat) {
          // Telefonul tocmai s-a salvat în cont; îl lăsăm scris.
        } else if (!eLogat) {
          document.getElementById('cf-phone').value = '';
        }

        if (successBox) successBox.hidden = false;
        toast(c.mesaj || 'Mesajul a fost trimis.');
      })
      .catch(function () {
        gata();
        toast(mesajFaraLegatura());
      });
    });
  }


  /* ------------------ 7. AUTENTIFICARE / ÎNREGISTRARE ------------------- */
  var authTabs = document.getElementById('auth-tabs');

  if (authTabs) {

    /* --- Deschide tabul corect din URL sau din butoanele de sub formular --- */
    // login.php#inregistrare sau ?tab=inregistrare deschide direct înregistrarea.
    var params = new URLSearchParams(window.location.search);

    function tabFromUrl() {
      if (window.location.hash === '#inregistrare' || params.get('tab') === 'inregistrare') return 'tab-register';
      if (window.location.hash === '#autentificare') return 'tab-login';
      return null;
    }

    var wanted = tabFromUrl();
    if (wanted) authTabs.selectTabById(wanted);

    // Linkul din meniu duce tot pe pagina asta, deci nu se reîncarcă nimic:
    // ascultăm schimbarea hash-ului ca să comutăm totuși tabul.
    window.addEventListener('hashchange', function () {
      var next = tabFromUrl();
      if (next) authTabs.selectTabById(next);
    });

    document.querySelectorAll('[data-go-tab]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        authTabs.selectTabById(btn.getAttribute('data-go-tab'));
        authTabs.scrollIntoView({ block: 'nearest' });
      });
    });

    /* --- Mesajul „intră în cont ca să continui" --- */
    // Acceptăm doar căi relative la site-ul nostru, ca parametrul redirect să nu
    // poată fi folosit pentru a trimite utilizatorul pe un domeniu străin.
    /**
     * Unde ne întoarcem după intrare — dar numai dacă e o cale de pe site.
     *
     * Aceleași reguli ca la caleInterna() din inc/validare.php, fiindcă
     * valoarea asta ajunge în window.location: o bară inversă („/\alt-site.ro")
     * e îndreptată de browser și devine o adresă de pe alt domeniu, iar un tab
     * sau un rând nou e scos înainte ca browserul să se uite la adresă.
     *
     * Serverul verifică oricum a doua oară; aici e ca omul să nu ajungă în
     * altă parte nici măcar pentru o clipă.
     */
    function safeRedirect() {
      var value = params.get('redirect');
      if (!value) return '';
      if (value.charAt(0) !== '/' || value.charAt(1) === '/') return '';
      if (value.indexOf('\\') !== -1) return '';
      if (/[\x00-\x1F\x7F]/.test(value)) return '';
      return value;
    }

    var backTo = safeRedirect();
    var notice = document.getElementById('auth-notice');
    if (backTo && notice) notice.hidden = false;

    function afterAuth(message) {
      toast(message);
      setTimeout(function () {
        window.location.href = backTo || 'index.php';
      }, 1000);
    }

    /* Butonul „Continuă cu Google" nu are nevoie de JavaScript: e o legătură
       obișnuită spre google.php, care face tot restul pe server.
       Vezi inc/buton-google.php. */

    /* --- Verificări comune ---
       tiparEmail, campul() și setError() vin de mai sus, din partea folosită
       de toate paginile. */
    var emailPattern = tiparEmail;
    var fieldOf = campul;

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
      ], trimiteAutentificarea);
    }

    /* --- Trimiterea formularului de autentificare --- */

    function arataPanou(id) {
      ['login-block', 'login-neconfirmat', 'login-blocat'].forEach(function (cheie) {
        var el = document.getElementById(cheie);
        if (el) el.hidden = (cheie !== id);
      });
      var panou = document.getElementById(id);
      if (panou && id !== 'login-block') {
        panou.scrollIntoView({ block: 'nearest' });
      }
    }

    function trimiteAutentificarea() {
      var buton = loginForm.querySelector('button[type="submit"]');
      var textInitial = buton ? buton.textContent : '';
      if (buton) { buton.disabled = true; buton.textContent = 'Se verifică…'; }

      var email = document.getElementById('lg-email').value;

      fetch('api/autentificare.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          csrf:       (loginForm.querySelector('[name="csrf"]') || {}).value || '',
          email:      email,
          parola:     document.getElementById('lg-password').value,
          tine_minte: document.getElementById('lg-remember').checked ? '1' : '',
          redirect:   backTo
        })
      })
      .then(citesteRaspuns)
      .then(function (rez) {
        if (buton) { buton.disabled = false; buton.textContent = textInitial; }

        if (!rez.corp) { toast(mesajRaspunsNeasteptat(rez)); return; }
        var c = rez.corp;

        if (c.ok) {
          toast(c.mesaj || 'Bine ai revenit!');
          setTimeout(function () { window.location.href = c.redirect || 'index.php'; }, 700);
          return;
        }

        // contul există și parola e bună, dar adresa nu a fost confirmată
        if (c.stare === 'neconfirmat') {
          var unde = document.getElementById('neconfirmat-email');
          if (unde) unde.textContent = c.email || email;
          emailNeconfirmat = c.email || email;
          arataPanou('login-neconfirmat');
          return;
        }

        // prea multe greșeli: formularul se închide temporar
        if (c.stare === 'blocat') {
          pornesteNumaratoarea(c.secunde || 600);
          arataPanou('login-blocat');
          return;
        }

        if (c.erori) {
          setError('lg-email',    'err-lg-email',    c.erori.email  || '');
          setError('lg-password', 'err-lg-password', c.erori.parola || '');

          if (c.erori.parola && typeof c.incercari_ramase === 'number') {
            var n = c.incercari_ramase;
            toast(n === 1 ? 'Mai ai o singură încercare.' : 'Mai ai ' + n + ' încercări.');
          }
          return;
        }

        toast(c.mesaj || 'Nu am putut verifica datele. Încearcă din nou.');
      })
      .catch(function () {
        if (buton) { buton.disabled = false; buton.textContent = textInitial; }
        toast(mesajFaraLegatura());
      });
    }

    /* --- Numărătoarea inversă de pe panoul de blocare --- */

    var emailNeconfirmat = '';
    var ceasBlocare = null;

    function pornesteNumaratoarea(secunde) {
      var afisaj = document.getElementById('blocat-timp');
      if (!afisaj) return;

      clearInterval(ceasBlocare);

      var scrie = function () {
        if (secunde <= 0) {
          clearInterval(ceasBlocare);
          afisaj.textContent = 'câteva clipe';
          arataPanou('login-block');
          toast('Poți încerca din nou.');
          return;
        }
        var m = Math.floor(secunde / 60);
        var s = secunde % 60;
        afisaj.textContent = m > 0
          ? m + ':' + (s < 10 ? '0' : '') + s + ' minute'
          : s + ' secunde';
        secunde--;
      };

      scrie();
      ceasBlocare = setInterval(scrie, 1000);
    }

    /* --- Retrimiterea e-mailului de confirmare --- */

    var btnRetrimite = document.getElementById('btn-retrimite');

    if (btnRetrimite) {
      btnRetrimite.addEventListener('click', function () {
        var textInitial = btnRetrimite.textContent;
        btnRetrimite.disabled = true;
        btnRetrimite.textContent = 'Se trimite…';

        fetch('api/retrimite-confirmare.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            csrf:  (loginForm.querySelector('[name="csrf"]') || {}).value || '',
            email: emailNeconfirmat
          })
        })
        .then(citesteRaspuns)
        .then(function (rez) {
          btnRetrimite.textContent = textInitial;

          if (!rez.corp) {
            btnRetrimite.disabled = false;
            toast(mesajRaspunsNeasteptat(rez));
            return;
          }
          var c = rez.corp;

          toast(c.mesaj || 'Gata.');

          if (c.ok) {
            // se poate cere din nou abia peste 10 minute
            var dev  = document.getElementById('neconfirmat-dev');
            var link = document.getElementById('neconfirmat-link');
            if (dev && link && c.link_confirmare) {
              link.href = c.link_confirmare;
              link.textContent = c.link_confirmare;
              dev.hidden = false;
            }
            asteaptaRetrimiterea(btnRetrimite, textInitial, 10 * 60);
          } else if (c.secunde) {
            asteaptaRetrimiterea(btnRetrimite, textInitial, c.secunde);
          } else {
            btnRetrimite.disabled = false;
          }
        })
        .catch(function () {
          btnRetrimite.disabled = false;
          btnRetrimite.textContent = textInitial;
          toast(mesajFaraLegatura());
        });
      });
    }

    // Ține butonul închis până trece răgazul dintre două trimiteri.
    function asteaptaRetrimiterea(buton, textInitial, secunde) {
      buton.disabled = true;

      var ceas = setInterval(function () {
        if (secunde <= 0) {
          clearInterval(ceas);
          buton.disabled = false;
          buton.textContent = textInitial;
          return;
        }
        var m = Math.floor(secunde / 60);
        var s = secunde % 60;
        buton.textContent = 'Mai poți cere în ' + (m > 0 ? m + ':' + (s < 10 ? '0' : '') + s : s + 's');
        secunde--;
      }, 1000);
    }

    var btnInapoi = document.getElementById('btn-inapoi-login');
    if (btnInapoi) {
      btnInapoi.addEventListener('click', function () { arataPanou('login-block'); });
    }

    /* --- Puterea parolei --- */
    legIndicatorulDeParola('rg-password', 'pass-meter', 'pass-hint');
    var passInput = document.getElementById('rg-password');

    /* --- Formularul de înregistrare --- */
    var registerForm = document.getElementById('register-form');

    if (registerForm) {
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
          check: verificaDataNasteriiInPagina
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
            if (putereParolei(v) < 2) return 'Parola e prea simplă — adaugă cifre sau litere mari.';
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
      ], trimiteInregistrarea);

      /* --- Trimiterea către server --- */

      // Numele câmpurilor din răspunsul serverului → locul unde se afișează
      // eroarea în pagină. Serverul poate găsi probleme pe care browserul
      // nu le vede: e-mail deja folosit, dată nerealistă, sesiune expirată.
      var campuriServer = {
        nume:              ['rg-lastname',  'err-rg-lastname'],
        prenume:           ['rg-firstname', 'err-rg-firstname'],
        email:             ['rg-email',     'err-rg-email'],
        data_nasterii:     ['rg-birthdate', 'err-rg-birthdate'],
        sex:               ['rg-gender',    'err-rg-gender'],
        parola:            ['rg-password',  'err-rg-password'],
        parola_confirmare: ['rg-password2', 'err-rg-password2'],
        termeni:           ['rg-terms',     'err-rg-terms']
      };

      function trimiteInregistrarea() {
        var buton = registerForm.querySelector('button[type="submit"]');
        var textInitial = buton ? buton.textContent : '';

        if (buton) { buton.disabled = true; buton.textContent = 'Se creează…'; }

        var date = {
          csrf:              (registerForm.querySelector('[name="csrf"]') || {}).value || '',
          nume:              document.getElementById('rg-lastname').value,
          prenume:           document.getElementById('rg-firstname').value,
          email:             document.getElementById('rg-email').value,
          data_nasterii:     document.getElementById('rg-birthdate').value,
          sex:               document.getElementById('rg-gender').value,
          parola:            document.getElementById('rg-password').value,
          parola_confirmare: document.getElementById('rg-password2').value,
          termeni:           document.getElementById('rg-terms').checked ? '1' : ''
        };

        fetch('api/inregistrare.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(date)
        })
        .then(citesteRaspuns)
        .then(function (rez) {
          if (buton) { buton.disabled = false; buton.textContent = textInitial; }

          if (!rez.corp) { toast(mesajRaspunsNeasteptat(rez)); return; }

          if (rez.corp.ok) {
            arataConfirmarea(rez.corp);
            return;
          }

          // erori pe câmpuri
          var erori = (rez.corp && rez.corp.erori) || null;
          if (erori) {
            var primul = null;
            Object.keys(campuriServer).forEach(function (camp) {
              var pereche = campuriServer[camp];
              var mesaj = erori[camp] || '';
              setError(pereche[0], pereche[1], mesaj);
              if (mesaj && !primul) primul = document.getElementById(pereche[0]);
            });
            if (primul) primul.focus();
            toast('Mai sunt câmpuri de corectat.');
            return;
          }

          toast((rez.corp && rez.corp.mesaj) || 'Nu am putut crea contul. Încearcă din nou.');
        })
        .catch(function () {
          if (buton) { buton.disabled = false; buton.textContent = textInitial; }
          toast(mesajFaraLegatura());
        });
      }

      // Formularul dispare, mesajul de confirmare îi ia locul.
      function arataConfirmarea(raspuns) {
        var bloc  = document.getElementById('register-block');
        var gata  = document.getElementById('register-done');
        var unde  = document.getElementById('register-done-email');
        if (!gata) return;

        if (bloc) bloc.hidden = true;
        if (unde && raspuns.email) unde.textContent = raspuns.email;

        // în dezvoltare, serverul trimite înapoi și linkul de confirmare
        var dev  = document.getElementById('register-done-dev');
        var link = document.getElementById('register-done-link');
        if (dev && link && raspuns.link_confirmare) {
          link.href = raspuns.link_confirmare;
          link.textContent = raspuns.link_confirmare;
          dev.hidden = false;
        }

        gata.hidden = false;
        gata.scrollIntoView({ block: 'nearest' });
        gata.setAttribute('tabindex', '-1');
        gata.focus();
      }

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


  /* ------------- 7b. PAROLA UITATĂ ȘI PAROLA NOUĂ ----------------------- */

  /* --- „Mi-am uitat parola": cererea unei parole temporare --- */
  var uitataForm = document.getElementById('uitata-form');

  if (uitataForm) {
    var uitataButon = uitataForm.querySelector('button[type=submit]');

    uitataForm.addEventListener('submit', function (e) {
      e.preventDefault();

      var camp  = document.getElementById('uit-email');
      var email = camp.value.trim();

      if (!email) {
        setError('uit-email', 'err-uit-email', 'Scrie adresa de e-mail.');
        camp.focus();
        return;
      }
      if (!tiparEmail.test(email)) {
        setError('uit-email', 'err-uit-email', 'Adresa de e-mail nu pare validă.');
        camp.focus();
        return;
      }
      setError('uit-email', 'err-uit-email', '');

      var textInitial = uitataButon.textContent;
      uitataButon.disabled = true;
      uitataButon.textContent = 'Se trimite…';

      fetch('api/parola-uitata.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          csrf:  (uitataForm.querySelector('[name="csrf"]') || {}).value || '',
          email: email
        })
      })
      .then(citesteRaspuns)
      .then(function (rez) {
        uitataButon.disabled = false;
        uitataButon.textContent = textInitial;

        if (!rez.corp) { toast(mesajRaspunsNeasteptat(rez)); return; }
        var c = rez.corp;

        if (c.erori && c.erori.email) {
          setError('uit-email', 'err-uit-email', c.erori.email);
          camp.focus();
          return;
        }

        if (!c.ok) { toast(c.mesaj || 'Nu a mers. Mai încearcă o dată.'); return; }

        var unde = document.getElementById('uitata-email');
        if (unde) unde.textContent = email;

        // Doar în dezvoltare, unde nu pleacă e-mailuri.
        if (c.parola_dezvoltare) {
          var cutie = document.getElementById('uitata-dev');
          var text  = document.getElementById('uitata-parola');
          if (cutie && text) { text.textContent = c.parola_dezvoltare; cutie.hidden = false; }
        }

        document.getElementById('uitata-block').hidden = true;
        var gata = document.getElementById('uitata-done');
        gata.hidden = false;
        gata.setAttribute('tabindex', '-1');
        gata.focus();
      })
      .catch(function () {
        uitataButon.disabled = false;
        uitataButon.textContent = textInitial;
        toast(mesajFaraLegatura());
      });
    });
  }

  /* --- Alegerea unei parole noi --- */
  var parolaForm = document.getElementById('parola-form');

  if (parolaForm) {
    legIndicatorulDeParola('pn-noua', 'pn-noua-meter', 'pn-noua-hint');

    // Când e cerută și parola veche, câmpul ei există; altfel, nu.
    var areVeche = document.getElementById('pn-veche') !== null;

    var reguliParola = [];

    if (areVeche) {
      reguliParola.push({
        id: 'pn-veche', error: 'err-pn-veche',
        check: function (v) { return v ? '' : 'Scrie parola de acum.'; }
      });
    }

    reguliParola.push({
      id: 'pn-noua', error: 'err-pn-noua',
      check: function (v) {
        if (!v) return 'Alege o parolă.';
        if (v.length < 8) return 'Parola trebuie să aibă cel puțin 8 caractere.';
        if (putereParolei(v) < 2) return 'Parola e prea simplă — adaugă cifre sau litere mari.';
        return '';
      }
    });

    reguliParola.push({
      id: 'pn-noua2', error: 'err-pn-noua2',
      check: function (v) {
        var noua = document.getElementById('pn-noua').value;
        if (!v) return 'Repetă parola.';
        if (v !== noua) return 'Cele două parole nu coincid.';
        return '';
      }
    });

    reguliParola.forEach(function (regula) {
      var input = document.getElementById(regula.id);
      if (!input) return;
      input.addEventListener('blur', function () {
        setError(regula.id, regula.error, regula.check(input.value.trim()));
      });
      input.addEventListener('input', function () {
        var f = campul(regula.id);
        if (f && f.classList.contains('has-error')) {
          setError(regula.id, regula.error, regula.check(input.value.trim()));
        }
      });
    });

    parolaForm.addEventListener('submit', function (e) {
      e.preventDefault();

      var primulGresit = null;
      reguliParola.forEach(function (regula) {
        var input = document.getElementById(regula.id);
        var bun = setError(regula.id, regula.error, regula.check(input.value.trim()));
        if (!bun && !primulGresit) primulGresit = input;
      });

      if (primulGresit) {
        primulGresit.focus();
        toast('Mai sunt câmpuri de corectat.');
        return;
      }

      var buton = parolaForm.querySelector('button[type=submit]');
      var textInitial = buton.textContent;
      buton.disabled = true;
      buton.textContent = 'Se salvează…';

      var trimitem = {
        csrf: (parolaForm.querySelector('[name="csrf"]') || {}).value || '',
        parola: document.getElementById('pn-noua').value,
        parola_confirmare: document.getElementById('pn-noua2').value
      };
      if (areVeche) trimitem.parola_veche = document.getElementById('pn-veche').value;

      fetch('api/parola-noua.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(trimitem)
      })
      .then(citesteRaspuns)
      .then(function (rez) {
        buton.disabled = false;
        buton.textContent = textInitial;

        if (!rez.corp) { toast(mesajRaspunsNeasteptat(rez)); return; }
        var c = rez.corp;

        if (c.erori) {
          var primul = null;
          [['parola_veche', 'pn-veche', 'err-pn-veche'],
           ['parola', 'pn-noua', 'err-pn-noua'],
           ['parola_confirmare', 'pn-noua2', 'err-pn-noua2']].forEach(function (p) {
            var mesaj = c.erori[p[0]] || '';
            setError(p[1], p[2], mesaj);
            if (mesaj && !primul) primul = document.getElementById(p[1]);
          });
          if (primul) primul.focus();
          toast('Mai sunt câmpuri de corectat.');
          return;
        }

        if (!c.ok) { toast(c.mesaj || 'Nu am putut schimba parola.'); return; }

        document.getElementById('parola-block').hidden = true;
        var gata = document.getElementById('parola-done');
        gata.hidden = false;
        gata.setAttribute('tabindex', '-1');
        gata.focus();
        toast(c.mesaj || 'Parola a fost schimbată.');
      })
      .catch(function () {
        buton.disabled = false;
        buton.textContent = textInitial;
        toast(mesajFaraLegatura());
      });
    });
  }


  /* ---------- 7c. ULTIMUL PAS LA ÎNREGISTRAREA CU GOOGLE ---------------- */

  var finalForm = document.getElementById('final-form');

  if (finalForm) {
    var reguliFinal = [
      {
        id: 'fn-lastname', error: 'err-fn-lastname',
        check: function (v) {
          if (!v) return 'Scrie numele.';
          if (v.length < 2) return 'Numele pare prea scurt.';
          return '';
        }
      },
      {
        id: 'fn-firstname', error: 'err-fn-firstname',
        check: function (v) {
          if (!v) return 'Scrie prenumele.';
          if (v.length < 2) return 'Prenumele pare prea scurt.';
          return '';
        }
      },
      { id: 'fn-birthdate', error: 'err-fn-birthdate', check: verificaDataNasteriiInPagina },
      { id: 'fn-gender', error: 'err-fn-gender',
        check: function (v) { return v ? '' : 'Alege o opțiune.'; } },
      { id: 'fn-terms', error: 'err-fn-terms',
        check: function (v) { return v ? '' : 'Trebuie să accepți termenii.'; } }
    ];

    function valoareaLui(input) {
      return input.type === 'checkbox' ? input.checked : input.value.trim();
    }

    reguliFinal.forEach(function (regula) {
      var input = document.getElementById(regula.id);
      if (!input) return;
      var eveniment = (input.type === 'checkbox' || input.tagName === 'SELECT') ? 'change' : 'blur';
      input.addEventListener(eveniment, function () {
        setError(regula.id, regula.error, regula.check(valoareaLui(input)));
      });
      input.addEventListener('input', function () {
        var f = campul(regula.id);
        if (f && f.classList.contains('has-error')) {
          setError(regula.id, regula.error, regula.check(valoareaLui(input)));
        }
      });
    });

    finalForm.addEventListener('submit', function (e) {
      e.preventDefault();

      var primulGresit = null;
      reguliFinal.forEach(function (regula) {
        var input = document.getElementById(regula.id);
        if (!setError(regula.id, regula.error, regula.check(valoareaLui(input))) && !primulGresit) {
          primulGresit = input;
        }
      });

      if (primulGresit) {
        primulGresit.focus();
        toast('Mai sunt câmpuri de completat.');
        return;
      }

      var buton = finalForm.querySelector('button[type=submit]');
      var textInitial = buton.textContent;
      buton.disabled = true;
      buton.textContent = 'Se creează…';

      fetch('api/finalizare-google.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          csrf:          (finalForm.querySelector('[name="csrf"]') || {}).value || '',
          nume:          document.getElementById('fn-lastname').value,
          prenume:       document.getElementById('fn-firstname').value,
          data_nasterii: document.getElementById('fn-birthdate').value,
          sex:           document.getElementById('fn-gender').value,
          termeni:       document.getElementById('fn-terms').checked ? '1' : ''
        })
      })
      .then(citesteRaspuns)
      .then(function (rez) {
        buton.disabled = false;
        buton.textContent = textInitial;

        if (!rez.corp) { toast(mesajRaspunsNeasteptat(rez)); return; }
        var c = rez.corp;

        if (c.erori) {
          var primul = null;
          [['nume', 'fn-lastname', 'err-fn-lastname'],
           ['prenume', 'fn-firstname', 'err-fn-firstname'],
           ['data_nasterii', 'fn-birthdate', 'err-fn-birthdate'],
           ['sex', 'fn-gender', 'err-fn-gender'],
           ['termeni', 'fn-terms', 'err-fn-terms']].forEach(function (p) {
            var mesaj = c.erori[p[0]] || '';
            setError(p[1], p[2], mesaj);
            if (mesaj && !primul) primul = document.getElementById(p[1]);
          });
          if (primul) primul.focus();
          toast('Mai sunt câmpuri de corectat.');
          return;
        }

        if (!c.ok) {
          toast(c.mesaj || 'Nu am putut crea contul.');
          // Când sesiunea a expirat, singurul drum e de la capăt.
          if (c.redirect) setTimeout(function () { window.location.href = c.redirect; }, 1600);
          return;
        }

        toast(c.mesaj || 'Contul e gata.');
        setTimeout(function () { window.location.href = c.redirect || 'index.php'; }, 900);
      })
      .catch(function () {
        buton.disabled = false;
        buton.textContent = textInitial;
        toast(mesajFaraLegatura());
      });
    });
  }


  /* -------------------- 8. STELE ȘI PAGINA DE PROFIL -------------------- */

  var STAR_PATH = 'M12 2.6l2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 17.4 6.2 20.5l1.1-6.5L2.6 9.4l6.5-.9L12 2.6z';

  function starSvg() {
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' + STAR_PATH +
           '" stroke-linejoin="round"/></svg>';
  }

  function starRow(cls) {
    var html = '';
    for (var i = 0; i < 5; i++) html += starSvg();
    return '<div class="stars__row ' + cls + '">' + html + '</div>';
  }

  // Scrie numărul cu virgulă, cum se folosește în română (4.6 → „4,6").
  function roNumber(value) {
    return (Math.round(value * 10) / 10).toString().replace('.', ',');
  }

  /* --- Stelele de afișare: <div data-stars="4.6" data-stars-count="23"> --- */
  document.querySelectorAll('[data-stars]').forEach(function (el) {
    var value = parseFloat(el.getAttribute('data-stars'));
    if (isNaN(value) || value < 0) value = 0;
    if (value > 5) value = 5;

    var count = parseInt(el.getAttribute('data-stars-count'), 10);
    var hasCount = !isNaN(count);

    el.classList.add('stars');
    el.innerHTML = starRow('stars__row--empty') + starRow('stars__row--full');
    el.querySelector('.stars__row--full').style.width = (value / 5 * 100) + '%';

    el.setAttribute('role', 'img');
    el.setAttribute('aria-label', value > 0
      ? roNumber(value) + ' din 5 stele' + (hasCount ? ', din ' + count + ' evaluări' : '')
      : 'Fără rating');

    // Textul de lângă stele, dacă pagina a pregătit un loc pentru el.
    var box = el.closest('.rating');
    if (!box) return;

    var label = box.querySelector('[data-stars-label]');
    var score = box.querySelector('[data-stars-value]');

    if (value > 0) {
      box.classList.remove('is-empty');
      if (score) score.textContent = roNumber(value);
      if (label) label.textContent = hasCount
        ? (count === 1 ? 'dintr-o evaluare' : 'din ' + count + ' evaluări')
        : '';
    } else {
      // fără nicio notă primită: stele goale și un text explicit
      box.classList.add('is-empty');
      if (label) label.textContent = 'Fără rating';
    }
  });

  /* --- Selectorul de stele din formularul de evaluare --- */
  document.querySelectorAll('[data-stars-input]').forEach(function (el) {
    var chosen = 0;
    var names = ['', 'Foarte slab', 'Slab', 'Acceptabil', 'Bun', 'Foarte bun'];
    var output = document.getElementById(el.getAttribute('aria-describedby') || 'review-chosen');
    var buttons = [];

    el.setAttribute('role', 'radiogroup');
    el.setAttribute('aria-label', 'Alege o notă de la 1 la 5 stele');

    function paint(upTo) {
      buttons.forEach(function (b, i) { b.classList.toggle('is-on', i < upTo); });
    }

    for (var i = 1; i <= 5; i++) {
      (function (value) {
        var b = document.createElement('button');
        b.type = 'button';
        b.innerHTML = starSvg();
        b.setAttribute('role', 'radio');
        b.setAttribute('aria-checked', 'false');
        b.setAttribute('aria-label', value + (value === 1 ? ' stea' : ' stele'));

        b.addEventListener('mouseenter', function () { paint(value); });
        b.addEventListener('focus', function () { paint(value); });
        b.addEventListener('click', function () {
          chosen = value;
          el.setAttribute('data-chosen', String(value));
          buttons.forEach(function (other, i) {
            other.setAttribute('aria-checked', String(i + 1 === value));
          });
          paint(value);
          if (output) output.textContent = value + ' din 5 — ' + names[value];
        });

        el.appendChild(b);
        buttons.push(b);
      })(i);
    }

    // la ieșirea cu mouse-ul revenim la nota aleasă, nu la cea survolată
    el.addEventListener('mouseleave', function () { paint(chosen); });
    el.addEventListener('focusout', function () {
      if (!el.contains(document.activeElement)) paint(chosen);
    });

    el.getChosen = function () { return chosen; };
    el.reset = function () {
      chosen = 0;
      el.removeAttribute('data-chosen');
      buttons.forEach(function (b) { b.setAttribute('aria-checked', 'false'); });
      paint(0);
      if (output) output.textContent = 'Nicio notă aleasă';
    };
  });

  /* --- Trimiterea unei evaluări --- */
  var reviewForm = document.getElementById('review-form');

  if (reviewForm) {
    var starsInput = document.getElementById('review-stars');
    var reviewText = document.getElementById('review-text');

    reviewForm.addEventListener('submit', function (e) {
      e.preventDefault();

      if (!isLoggedIn()) {
        toast('Intră în cont ca să lași o evaluare.');
        setTimeout(goToLogin, 900);
        return;
      }
      if (!starsInput || !starsInput.getChosen()) {
        toast('Alege mai întâi o notă, de la 1 la 5 stele.');
        return;
      }
      if (!reviewText.value.trim()) {
        toast('Scrie și câteva cuvinte despre cum a fost.');
        reviewText.focus();
        return;
      }

      // TODO: trimite nota și comentariul către server, apoi recalculează media.
      reviewText.value = '';
      starsInput.reset();
      toast('Evaluarea ta a fost trimisă.');
    });
  }


  /* ------------------- 8.5. DECUPATORUL (mutat + mărit) ------------------ */
  /*
    Un singur motor pentru toate ramele din site: cercul pozei de profil și
    dreptunghiul lat al copertei de eveniment. Nu știe nimic despre ce se
    decupează — primește o ramă și o poză, le ține una peste alta și spune, la
    cerere, ce dreptunghi din poza originală se vede prin ramă.

    Scris o dată fiindcă a doua oară s-ar fi scris altfel: pinch-ul de pe
    telefon și marginile care nu lasă colțuri goale sunt exact genul de cod pe
    care nimeni nu-l mai corectează în două locuri.

    Rama poate fi pătrată sau lată — se ia din câți pixeli are pe ecran, deci
    forma o hotărăște CSS-ul, nu JavaScriptul.
  */
  function faDecupator(rama, poza) {
    var nW = 0, nH = 0;          // dimensiunile reale ale pozei
    var ramaL = 0, ramaI = 0;    // rama, în pixeli de ecran
    var baseScale = 1, zoom = 1, zoomMax = 4, pozX = 0, pozY = 0;
    var degete = {};             // pointerId → ultima poziție
    var pinchStart = 0, pinchZoom = 1;
    var anunta = null;

    function scara() { return baseScale * zoom; }

    function masoara() {
      ramaL = rama.clientWidth;
      ramaI = rama.clientHeight;
    }

    /** Poza nu are voie să lase colțuri goale în ramă. */
    function tine() {
      var s = scara();
      pozX = Math.min(0, Math.max(ramaL - nW * s, pozX));
      pozY = Math.min(0, Math.max(ramaI - nH * s, pozY));
    }

    function deseneaza() {
      tine();
      var s = scara();
      poza.style.width  = (nW * s) + 'px';
      poza.style.height = (nH * s) + 'px';
      poza.style.transform = 'translate(' + pozX + 'px, ' + pozY + 'px)';
    }

    /** Mărește sau micșorează păstrând sub deget același punct din poză. */
    function schimbaZoom(nou, focX, focY) {
      nou = Math.min(zoomMax, Math.max(1, nou));
      if (!nW || nou === zoom) return;

      var vechi = scara();
      zoom = nou;
      var acum = scara();

      if (focX === undefined) { focX = ramaL / 2; focY = ramaI / 2; }

      pozX = focX - (focX - pozX) * (acum / vechi);
      pozY = focY - (focY - pozY) * (acum / vechi);

      deseneaza();
      if (anunta) anunta(zoom);
    }

    function pozitiaIn(e) {
      var r = rama.getBoundingClientRect();
      return { x: e.clientX - r.left, y: e.clientY - r.top };
    }

    function distanta(a, b) {
      return Math.sqrt((a.x - b.x) * (a.x - b.x) + (a.y - b.y) * (a.y - b.y));
    }

    rama.addEventListener('pointerdown', function (e) {
      if (!nW) return;
      rama.setPointerCapture(e.pointerId);
      degete[e.pointerId] = pozitiaIn(e);
      rama.classList.add('is-dragging');

      var chei = Object.keys(degete);
      if (chei.length === 2) {
        pinchStart = distanta(degete[chei[0]], degete[chei[1]]);
        pinchZoom = zoom;
      }
    });

    rama.addEventListener('pointermove', function (e) {
      if (!degete[e.pointerId]) return;
      e.preventDefault();

      var acum = pozitiaIn(e);
      var chei = Object.keys(degete);

      if (chei.length >= 2) {
        // două degete: cât se depărtează unul de altul, atât se mărește poza
        degete[e.pointerId] = acum;

        var a = degete[chei[0]], b = degete[chei[1]];
        var d = distanta(a, b);

        if (pinchStart > 0) {
          schimbaZoom(pinchZoom * (d / pinchStart), (a.x + b.x) / 2, (a.y + b.y) / 2);
        }
        return;
      }

      var vechi = degete[e.pointerId];
      pozX += acum.x - vechi.x;
      pozY += acum.y - vechi.y;
      degete[e.pointerId] = acum;
      deseneaza();
    });

    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (nume) {
      rama.addEventListener(nume, function (e) {
        delete degete[e.pointerId];
        if (!Object.keys(degete).length) {
          rama.classList.remove('is-dragging');
          pinchStart = 0;
        }
      });
    });

    // rotița mausului, pe calculator
    rama.addEventListener('wheel', function (e) {
      if (!nW) return;
      e.preventDefault();
      var p = pozitiaIn(e);
      schimbaZoom(zoom * (e.deltaY < 0 ? 1.12 : 1 / 1.12), p.x, p.y);
    }, { passive: false });

    // La rotirea telefonului rama își schimbă lățimea, deci refacem socoteala.
    window.addEventListener('resize', function () {
      if (!nW) return;

      var vechiL = ramaL || 1, vechiI = ramaI || 1;
      masoara();
      if (!ramaL) return;          // rama e ascunsă acum

      pozX *= ramaL / vechiL;
      pozY *= ramaI / vechiI;
      baseScale = Math.max(ramaL / nW, ramaI / nH);
      deseneaza();
    });

    return {
      /** Pune o poză nouă în ramă, potrivită din mijloc. Rama trebuie să fie
          deja vizibilă — altfel n-are lățime din care să calculăm nimic. */
      asaza: function (latime, inaltime) {
        nW = latime;
        nH = inaltime;
        masoara();
        baseScale = Math.max(ramaL / nW, ramaI / nH);
        zoom = 1;
        pozX = (ramaL - nW * baseScale) / 2;
        pozY = (ramaI - nH * baseScale) / 2;
        deseneaza();
        if (anunta) anunta(zoom);
      },

      /** Uită poza: de aici înainte, trasul și rotița nu mai fac nimic. */
      uita: function () { nW = 0; nH = 0; },

      zoomLa: schimbaZoom,

      /** Cât se poate mări. Peste asta, la copertă ar ieși o poză întinsă. */
      zoomMaxim: function (cat) {
        zoomMax = Math.max(1, cat);
        if (zoom > zoomMax) schimbaZoom(zoomMax);
      },

      arePeCeMari: function () { return zoomMax > 1.001; },

      laZoom: function (fn) { anunta = fn; },

      /** Ce se vede prin ramă, în pixelii pozei originale. */
      decupaj: function () {
        var s = scara();
        return {
          x: Math.max(0, Math.round(-pozX / s)),
          y: Math.max(0, Math.round(-pozY / s)),
          l: Math.round(ramaL / s),
          h: Math.round(ramaI / s)
        };
      }
    };
  }


  /* --------------------- 9. POZA DE PROFIL (poza.php) -------------------- */
  /*
    Decuparea se face aici doar ca să vadă omul ce alege. Poza tăiată nu se
    trimite: trimitem fișierul original plus cele trei numere ale decupajului
    (colțul din stânga-sus și latura), iar serverul taie el.

    De ce așa: orice ar veni din pagină poate fi măsluit. Dacă am trimite
    imaginea gata tăiată de JavaScript, ne-am baza pe ea — și am primi ce
    vrea cel de la tastatură, nu ce am cerut noi. Așa, tot ce poate face
    cineva stricând numerele e să-și decupeze prost propria poză.
  */
  var pozaDrop = document.getElementById('poza-drop');

  if (pozaDrop) {
    var fisierInput = document.getElementById('poza-fisier');
    var crop        = document.getElementById('crop');
    var stage       = document.getElementById('crop-stage');
    var cropImg     = document.getElementById('crop-img');
    var zoomInput   = document.getElementById('crop-zoom');
    var btnSalvez   = document.getElementById('poza-salveaza');
    var btnSterg    = document.getElementById('poza-sterge');
    var csrfPoza    = document.getElementById('poza-csrf');
    var mesajBox    = document.getElementById('poza-mesaj');
    var mesajText   = document.getElementById('poza-mesaj-text');
    var mesajIco    = document.getElementById('poza-mesaj-ico');
    var acumImg     = document.getElementById('poza-acum-img');
    var acumTitlu   = document.getElementById('poza-acum-titlu');
    var acumSub     = document.getElementById('poza-acum-sub');

    // Aceleași limite ca pe server. Aici sunt doar ca să nu pornească o
    // încărcare de câteva megaocteți care oricum ar fi refuzată; adevărul
    // rămâne cel din inc/imagini.php.
    var OCTETI_MAX = 6 * 1024 * 1024;
    var SURSA_MIN  = 200;
    var TIPURI     = ['image/jpeg', 'image/png', 'image/webp'];

    var fisierAles = null;   // File
    var adresaTemp = null;   // URL-ul creat pentru previzualizare
    var seTrimite = false;

    // Mutatul, mărirea, pinch-ul și marginile ramei — toate în faDecupator().
    // Aici rămâne doar ce ține de poza de profil: fișierul, mesajele, salvarea.
    var decupator = faDecupator(stage, cropImg);

    if (zoomInput) {
      decupator.laZoom(function (z) { zoomInput.value = String(z); });
      zoomInput.addEventListener('input', function () {
        decupator.zoomLa(parseFloat(zoomInput.value));
      });
    }

    /* ---------------------------- mesaje ------------------------------- */
    var ICO_INFO = '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5"/><path d="M12 7.6v.1"/>';
    var ICO_BINE = '<circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>';

    function spune(text, fel) {
      if (!mesajBox) return;
      mesajText.textContent = text;
      mesajBox.classList.toggle('poza-mesaj--rau', fel === 'rau');
      mesajBox.classList.toggle('poza-mesaj--bun', fel === 'bun');
      mesajIco.innerHTML = fel === 'bun' ? ICO_BINE : ICO_INFO;
      mesajBox.hidden = false;
    }

    function taci() { if (mesajBox) mesajBox.hidden = true; }

    /* ------------------------ alegerea fișierului ---------------------- */
    function preiaFisier(file) {
      taci();

      if (!file) return;

      if (TIPURI.indexOf(file.type) === -1) {
        spune('Acceptăm poze JPG, PNG sau WEBP.', 'rau');
        return;
      }

      if (file.size > OCTETI_MAX) {
        spune('Poza are ' + (file.size / 1024 / 1024).toFixed(1) +
              ' MB, iar limita e de ' + (OCTETI_MAX / 1024 / 1024) + ' MB.', 'rau');
        return;
      }

      if (adresaTemp) URL.revokeObjectURL(adresaTemp);
      adresaTemp = URL.createObjectURL(file);

      cropImg.onload = function () {
        var latime = cropImg.naturalWidth;
        var inaltime = cropImg.naturalHeight;

        if (Math.min(latime, inaltime) < SURSA_MIN) {
          ascundeDecuparea();
          spune('Poza e prea mică. Avem nevoie de cel puțin ' +
                SURSA_MIN + '×' + SURSA_MIN + ' pixeli.', 'rau');
          return;
        }

        fisierAles = file;
        // Rama trebuie să fie vizibilă înainte de așezare: cât timp e ascunsă,
        // n-are lățime, deci n-am avea din ce socoti scara.
        crop.hidden = false;
        decupator.asaza(latime, inaltime);
        btnSalvez.disabled = false;

        // Pe telefon, rama apare sub marginea ecranului: fără rândul ăsta,
        // omul alege poza și pare că nu s-a întâmplat nimic.
        crop.scrollIntoView({ block: 'center' });
      };

      cropImg.onerror = function () {
        ascundeDecuparea();
        spune('Nu am putut deschide fișierul. Încearcă altul.', 'rau');
      };

      cropImg.src = adresaTemp;
    }

    function ascundeDecuparea() {
      fisierAles = null;
      crop.hidden = true;
      decupator.uita();
      btnSalvez.disabled = true;
      if (fisierInput) fisierInput.value = '';
    }

    /* ------------------ alegerea: buton, tragere, lipire --------------- */
    if (fisierInput) {
      fisierInput.addEventListener('change', function () {
        preiaFisier(fisierInput.files && fisierInput.files[0]);
      });
    }

    ['dragenter', 'dragover'].forEach(function (nume) {
      pozaDrop.addEventListener(nume, function (e) {
        e.preventDefault();
        pozaDrop.classList.add('is-over');
      });
    });

    ['dragleave', 'drop'].forEach(function (nume) {
      pozaDrop.addEventListener(nume, function (e) {
        e.preventDefault();
        pozaDrop.classList.remove('is-over');
      });
    });

    pozaDrop.addEventListener('drop', function (e) {
      var dt = e.dataTransfer;
      if (dt && dt.files && dt.files.length) preiaFisier(dt.files[0]);
    });

    /* ------------------------- trimiterea ------------------------------ */
    function cere(date, laBine) {
      if (seTrimite) return;
      seTrimite = true;
      btnSalvez.disabled = true;
      blocheazaStergerea(true);

      fetch('api/poza-profil.php', {
        method: 'POST',
        body: date,
        headers: { 'X-Requested-With': 'fetch' },
        credentials: 'same-origin'
      })
      .then(citesteRaspuns)
      .then(function (rez) {
        if (!rez.corp) {
          spune(mesajRaspunsNeasteptat(rez), 'rau');
          return;
        }

        if (!rez.corp.ok) {
          spune(rez.corp.mesaj || 'Nu a mers. Mai încearcă o dată.', 'rau');
          return;
        }

        laBine(rez.corp);
      })
      .catch(function () {
        spune(mesajFaraLegatura(), 'rau');
      })
      .then(function () {
        seTrimite = false;
        btnSalvez.disabled = !fisierAles;
        blocheazaStergerea(false);
      });
    }

    function blocheazaStergerea(da) {
      [btnSterg, document.getElementById('poza-sterge-da')].forEach(function (b) {
        if (b) b.disabled = da;
      });
    }

    btnSalvez.addEventListener('click', function () {
      if (!fisierAles) return;
      taci();

      var t = decupator.decupaj();
      var date = new FormData();
      date.append('csrf', csrfPoza.value);
      date.append('actiune', 'salveaza');
      date.append('poza', fisierAles);
      date.append('x', String(t.x));
      date.append('y', String(t.y));
      date.append('l', String(t.l));

      spune('Se încarcă…', '');

      cere(date, function (c) {
        // Numele fișierului e nou de fiecare dată, deci ce se vede acum e
        // sigur poza proaspătă, nu una ținută minte de browser.
        actualizeazaPeste(c.poza, c.poza_mica);
        ascundeDecuparea();
        spune(c.mesaj || 'Poza a fost schimbată.', 'bun');
        toast('Poza de profil a fost schimbată.');
      });
    });

    /* Ștergerea: întâi întrebarea, apoi fapta. Confirmarea e desenată de noi,
       nu de browser — vezi explicația din poza.php. */
    var cutiaSigur = document.getElementById('poza-sigur');
    var btnSigurDa = document.getElementById('poza-sterge-da');
    var btnSigurNu = document.getElementById('poza-sterge-nu');

    function intreaba(pornit) {
      if (!cutiaSigur || !btnSterg) return;
      cutiaSigur.hidden = !pornit;
      btnSterg.hidden = pornit;
      if (pornit && btnSigurNu) btnSigurNu.focus();
    }

    if (btnSterg && cutiaSigur) {
      btnSterg.addEventListener('click', function () { taci(); intreaba(true); });
      btnSigurNu.addEventListener('click', function () { intreaba(false); btnSterg.focus(); });

      btnSigurDa.addEventListener('click', function () {
        intreaba(false);
        taci();

        var date = new FormData();
        date.append('csrf', csrfPoza.value);
        date.append('actiune', 'sterge');

        cere(date, function (c) {
          actualizeazaPeste(c.poza, c.poza_mica);
          spune(c.mesaj || 'Am șters poza.', 'bun');
          toast('Poza de profil a fost ștearsă.');
        });
      });
    }

    /** Pune noua poză peste tot unde se vede în pagina asta. */
    function actualizeazaPeste(mare, mica) {
      var arePoza = mare && mare.indexOf('/membri/') !== -1;

      if (acumImg) acumImg.src = mare;
      if (acumTitlu) acumTitlu.textContent = arePoza ? 'Poza de acum' : 'Nu ai încă nicio poză';
      if (acumSub) {
        acumSub.textContent = arePoza
          ? 'Poți să o înlocuiești sau să o ștergi.'
          : 'Până alegi una, se vede silueta asta.';
      }
      if (cutiaSigur) cutiaSigur.hidden = true;
      if (btnSterg) btnSterg.hidden = !arePoza;

      // și cercul din bara de meniu, ca schimbarea să se vadă imediat
      var inBara = document.querySelector('.nav__eu-avatar');
      if (inBara) {
        var pozaBara = inBara.querySelector('img');
        if (arePoza) {
          if (!pozaBara) {
            pozaBara = document.createElement('img');
            pozaBara.width = 26;
            pozaBara.height = 26;
            pozaBara.alt = '';
            inBara.textContent = '';
            inBara.appendChild(pozaBara);
          }
          pozaBara.src = mica;
        } else if (pozaBara) {
          // fără poză se întoarce inițiala prenumelui
          inBara.textContent = (body.getAttribute('data-user-initiala') || '').trim();
        }
      }
    }
  }

  /* ---------------------- 10. SETĂRILE CONTULUI ------------------------- */
  /* Parola e luată de bucata 7b de mai sus: pagina de setări folosește exact
     aceleași id-uri ca parola-noua.php, deci n-are nevoie de cod propriu. */

  function trimiteSetare(form, buton, date, laReusita) {
    var textInitial = buton.textContent;
    buton.disabled = true;
    buton.textContent = 'Se salvează…';

    date.csrf = (form.querySelector('[name="csrf"]') || {}).value || '';

    fetch('api/setari.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(date)
    })
    .then(citesteRaspuns)
    .then(function (rez) {
      buton.disabled = false;
      buton.textContent = textInitial;

      if (!rez.corp) { toast(mesajRaspunsNeasteptat(rez)); return; }
      laReusita(rez.corp);
    })
    .catch(function () {
      buton.disabled = false;
      buton.textContent = textInitial;
      toast(mesajFaraLegatura());
    });
  }

  /* --- Telefonul --- */
  var telefonForm = document.getElementById('telefon-form');

  if (telefonForm) {
    telefonForm.addEventListener('submit', function (e) {
      e.preventDefault();

      var camp = document.getElementById('st-telefon');

      trimiteSetare(telefonForm, telefonForm.querySelector('button[type=submit]'),
        { sectiune: 'telefon', telefon: camp.value },
        function (c) {
          if (c.erori) {
            setError('st-telefon', 'err-st-telefon', c.erori.telefon || '');
            camp.focus();
            return;
          }
          if (!c.ok) { toast(c.mesaj || 'Nu am putut salva numărul.'); return; }

          // Serverul întoarce numărul adus la forma lui: îl arătăm pe acela,
          // ca omul să vadă exact ce s-a salvat.
          setError('st-telefon', 'err-st-telefon', '');
          camp.value = c.telefon || '';
          toast(c.mesaj || 'Salvat.');
        });
    });
  }

  /* --- Newsletterul --- */
  var newsForm = document.getElementById('newsletter-form');

  if (newsForm) {
    newsForm.addEventListener('submit', function (e) {
      e.preventDefault();

      var bifa = document.getElementById('st-newsletter');

      trimiteSetare(newsForm, newsForm.querySelector('button[type=submit]'),
        { sectiune: 'newsletter', newsletter: bifa.checked ? '1' : '' },
        function (c) {
          if (!c.ok) { toast(c.mesaj || 'Nu am putut salva preferința.'); return; }
          toast(c.mesaj || 'Salvat.');
        });
    });
  }

  /* --- Ștergerea contului --- */
  var stergStart  = document.getElementById('stergere-start');
  var stergForm   = document.getElementById('stergere-form');
  var stergRenunt = document.getElementById('stergere-renunt');

  if (stergStart && stergForm) {
    stergStart.addEventListener('click', function () {
      stergStart.hidden = true;
      stergForm.hidden = false;

      var parola = document.getElementById('st-parola');
      if (parola) parola.focus();
    });

    if (stergRenunt) {
      stergRenunt.addEventListener('click', function () {
        stergForm.hidden = true;
        stergStart.hidden = false;
        var parola = document.getElementById('st-parola');
        if (parola) { parola.value = ''; setError('st-parola', 'err-st-parola', ''); }
        stergStart.focus();
      });
    }

    stergForm.addEventListener('submit', function (e) {
      e.preventDefault();

      var parola = document.getElementById('st-parola');

      if (parola && !parola.value) {
        setError('st-parola', 'err-st-parola', 'Scrie-ți parola.');
        parola.focus();
        return;
      }

      var buton = stergForm.querySelector('button[type=submit]');
      var textInitial = buton.textContent;
      buton.disabled = true;
      buton.textContent = 'Se trimite…';

      var trimitem = { csrf: (stergForm.querySelector('[name="csrf"]') || {}).value || '' };
      if (parola) trimitem.parola = parola.value;

      fetch('api/stergere-cere.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(trimitem)
      })
      .then(citesteRaspuns)
      .then(function (rez) {
        buton.disabled = false;
        buton.textContent = textInitial;

        if (!rez.corp) { toast(mesajRaspunsNeasteptat(rez)); return; }
        var c = rez.corp;

        if (c.erori) {
          setError('st-parola', 'err-st-parola', c.erori.parola || '');
          if (parola) parola.focus();
          return;
        }

        if (!c.ok) { toast(c.mesaj || 'Nu am putut trimite e-mailul.'); return; }

        document.getElementById('stergere-block').hidden = true;
        var gata = document.getElementById('stergere-done');
        var text = document.getElementById('stergere-done-text');
        if (text && c.mesaj) text.textContent = c.mesaj;
        gata.hidden = false;
        gata.setAttribute('tabindex', '-1');
        gata.focus();
      })
      .catch(function () {
        buton.disabled = false;
        buton.textContent = textInitial;
        toast(mesajFaraLegatura());
      });
    });
  }

  /* ------------------ 11. PUBLICAREA UNUI EVENIMENT --------------------- */

  var evForm = document.getElementById('eveniment-form');

  if (evForm) {
    var evFisier = document.getElementById('ev-coperta');
    var evNume   = document.getElementById('ev-coperta-nume');
    var evDrop   = document.getElementById('ev-drop');
    var evCrop   = document.getElementById('ev-crop');
    var evRama   = document.getElementById('ev-crop-stage');
    var evCropImg = document.getElementById('ev-crop-img');
    var evCropZoom = document.getElementById('ev-crop-zoom');

    /* --- bifele care ascund câmpul de sub ele --- */
    // Aceeași poveste de trei ori: gratuit, minim, maxim. O scriem o dată.
    [
      ['ev-gratuit',      'ev-cost-camp', true],
      ['ev-fara-min',     'ev-min',       false],
      ['ev-fara-max',     'ev-max',       false]
    ].forEach(function (p) {
      var bifa = document.getElementById(p[0]);
      var tinta = document.getElementById(p[1]);
      if (!bifa || !tinta) return;

      var potriveste = function () {
        /**
         * La cost se ascunde tot câmpul (cu etichetă cu tot), la participanți
         * doar caseta: eticheta și lămurirea de deasupra rămân de citit, ca
         * omul să știe ce anume a lăsat nespecificat.
         *
         * `hidden` singur n-ar ajunge: un câmp ascuns tot pleacă în FormData.
         * `disabled` e cel care îl ține acasă.
         */
        if (p[2]) {
          tinta.hidden = bifa.checked;
        } else {
          tinta.hidden = tinta.disabled = bifa.checked;
          if (bifa.checked) tinta.value = '';
        }
      };

      bifa.addEventListener('change', potriveste);
      potriveste();
    });

    var evFaraSfarsit = document.getElementById('ev-fara-sfarsit');
    var evOraSfarsit  = document.getElementById('ev-ora-sfarsit');

    if (evFaraSfarsit && evOraSfarsit) {
      var potrivesteOra = function () {
        evOraSfarsit.disabled = evFaraSfarsit.checked;
        if (evFaraSfarsit.checked) evOraSfarsit.value = '';
      };
      evFaraSfarsit.addEventListener('change', potrivesteOra);
      potrivesteOra();
    }

    /* --- numărătoarea de caractere --- */
    var evDescriere = document.getElementById('ev-descriere');
    var evNumar     = document.getElementById('ev-numar');
    var minCaractere = 300;

    if (evDescriere && evNumar) {
      var numara = function () {
        // [...sir].length numără caractere, nu unități UTF-16 — la fel ca
        // mb_strlen pe server. Cu .length simplu, un emoji ar conta ca două.
        var cate = [...evDescriere.value].length;
        evNumar.textContent = cate + ' din ' + minCaractere + ' de caractere';
        evNumar.classList.toggle('e-gata', cate >= minCaractere);
      };
      evDescriere.addEventListener('input', numara);
      numara();
    }

    /* --- coperta: rama de așezare și mărimea minimă, încă din browser --- */
    /*
      Rama e și previzualizarea: ce se vede prin ea e exact ce se salvează.
      Ca la poza de profil, la server pleacă fișierul original plus colțul și
      lățimea decupajului — poza tăiată aici n-ar fi de încredere.
    */
    var COPERTA_L = 1600, COPERTA_I = 900;
    var evDecupator = null;
    var evAdresa = null;         // URL-ul temporar al pozei alese

    if (evRama && evCropImg) {
      evDecupator = faDecupator(evRama, evCropImg);

      if (evCropZoom) {
        evDecupator.laZoom(function (z) { evCropZoom.value = String(z); });
        evCropZoom.addEventListener('input', function () {
          evDecupator.zoomLa(parseFloat(evCropZoom.value));
        });
      }
    }

    function scoateCoperta() {
      if (evFisier) evFisier.value = '';
      if (evCrop) evCrop.hidden = true;
      if (evDecupator) evDecupator.uita();
      if (evAdresa) { URL.revokeObjectURL(evAdresa); evAdresa = null; }
      if (evNume) evNume.textContent = 'JPG, PNG sau WEBP, cel puțin 1600×900 px';
    }

    function arataCoperta(fisier) {
      if (!fisier || !evDecupator) return;

      if (evAdresa) URL.revokeObjectURL(evAdresa);
      evAdresa = URL.createObjectURL(fisier);

      evCropImg.onload = function () {
        var latime = evCropImg.naturalWidth;
        var inaltime = evCropImg.naturalHeight;

        // Aceeași regulă ca pe server. Aici e doar ca omul să afle imediat,
        // fără să aștepte încărcarea unui fișier de câțiva megabytes.
        if (latime < COPERTA_L || inaltime < COPERTA_I) {
          scoateCoperta();
          setError('ev-coperta', 'err-ev-coperta',
            'Poza e prea mică: are ' + latime + '×' + inaltime +
            ' pixeli, iar noi avem nevoie de cel puțin 1600×900. Încarcă alta, mai mare.');
          return;
        }

        setError('ev-coperta', 'err-ev-coperta', '');

        // Rama întâi vizibilă, apoi așezarea: ascunsă, n-are lățime.
        evCrop.hidden = false;
        evDecupator.asaza(latime, inaltime);

        /**
         * Cât se poate mări fără ca poza salvată să iasă întinsă.
         *
         * La zoom 1 se vede tot ce încape în ramă; de acolo, fiecare mărire
         * micșorează decupajul. Când decupajul ar scădea sub 1600 px lățime,
         * ne-am apuca să întindem pixeli care nu există — deci ne oprim exact
         * acolo. La o poză fix de 1600×900 nu se poate mări deloc, iar bara
         * dispare de tot: n-are rost o unealtă care nu face nimic.
         */
        var plin = evDecupator.decupaj();
        var maxim = Math.max(1, plin.l / COPERTA_L);

        evDecupator.zoomMaxim(maxim);
        if (evCropZoom) evCropZoom.max = String(Math.min(4, maxim));
        evCrop.classList.toggle('crop--fix', !evDecupator.arePeCeMari());

        if (evNume) evNume.textContent = fisier.name;
      };

      evCropImg.onerror = function () {
        scoateCoperta();
        setError('ev-coperta', 'err-ev-coperta', 'Fișierul nu pare o poză.');
      };

      evCropImg.src = evAdresa;
    }

    if (evFisier) {
      evFisier.addEventListener('change', function () {
        arataCoperta(evFisier.files && evFisier.files[0]);
      });
    }

    if (evDrop && evFisier) {
      ['dragenter', 'dragover'].forEach(function (e) {
        evDrop.addEventListener(e, function (ev) {
          ev.preventDefault(); evDrop.classList.add('is-over');
        });
      });
      ['dragleave', 'drop'].forEach(function (e) {
        evDrop.addEventListener(e, function (ev) {
          ev.preventDefault(); evDrop.classList.remove('is-over');
        });
      });
      evDrop.addEventListener('drop', function (ev) {
        var f = ev.dataTransfer && ev.dataTransfer.files && ev.dataTransfer.files[0];
        if (!f) return;
        // DataTransfer merge direct în input, ca formularul să-l trimită.
        var dt = new DataTransfer();
        dt.items.add(f);
        evFisier.files = dt.files;
        arataCoperta(f);
      });
    }

    var evRenunt = document.getElementById('ev-coperta-renunt');
    if (evRenunt) {
      evRenunt.addEventListener('click', function () {
        scoateCoperta();
        setError('ev-coperta', 'err-ev-coperta', '');
      });
    }

    /* --- trimiterea --- */
    evForm.addEventListener('submit', function (e) {
      e.preventDefault();

      var buton = evForm.querySelector('button[type=submit]');
      var textInitial = buton.textContent;
      buton.disabled = true;
      buton.textContent = 'Se trimite…';

      function gata() {
        buton.disabled = false;
        buton.textContent = textInitial;
      }

      /**
       * FormData, nu JSON: e singurul fel în care poate pleca și fișierul.
       * Câmpurile dezactivate nu intră în FormData — exact ce vrem, fiindcă
       * bifa „Nespecificat" e cea care le dezactivează.
       */
      var date = new FormData(evForm);

      // Cadrul ales din ramă, dacă e vreo copertă. Serverul îl verifică oricum.
      if (evDecupator && evFisier && evFisier.files && evFisier.files.length) {
        var t = evDecupator.decupaj();
        date.append('x', String(t.x));
        date.append('y', String(t.y));
        date.append('l', String(t.l));
      }

      fetch('api/eveniment.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: date
      })
      .then(citesteRaspuns)
      .then(function (rez) {
        gata();

        if (!rez.corp) { toast(mesajRaspunsNeasteptat(rez)); return; }
        var c = rez.corp;

        if (c.erori) {
          var primul = null;
          [['titlu', 'ev-titlu'], ['categorie_id', 'ev-categorie'], ['locatie', 'ev-locatie'],
           ['data_eveniment', 'ev-data'], ['ora_inceput', 'ev-ora-inceput'],
           ['ora_sfarsit', 'ev-ora-sfarsit'], ['cost', 'ev-cost'],
           ['varsta_minima', 'ev-varsta'], ['gen_participanti', 'ev-gen'],
           ['participanti_min', 'ev-min'], ['participanti_max', 'ev-max'],
           ['descriere', 'ev-descriere'], ['coperta', 'ev-coperta']
          ].forEach(function (p) {
            var mesaj = c.erori[p[0]] || '';
            setError(p[1], 'err-' + p[1], mesaj);
            if (mesaj && !primul) primul = document.getElementById(p[1]);
          });
          if (primul) primul.focus();
          toast('Mai sunt câmpuri de corectat.');
          return;
        }

        if (!c.ok) { toast(c.mesaj || 'Nu am putut trimite evenimentul.'); return; }

        document.getElementById('ev-block').hidden = true;
        var done = document.getElementById('ev-done');
        done.hidden = false;
        var panou = done.querySelector('.done');
        if (panou) panou.focus();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      })
      .catch(function () {
        gata();
        toast(mesajFaraLegatura());
      });
    });
  }


  /* --------------- 12. „VEZI MAI MULT…" PE PROFIL ----------------------- */
  /*
    Evenimentele de peste al patrulea sunt deja în pagină, cu clasa .ascuns.
    Butonul le descoperă scoțând clasa — nimic nu se cere de la server, fiindcă
    nimic nu lipsește.

    Se descoperă toate deodată, nu câte patru: la câte evenimente active poate
    avea cineva, o a doua apăsare ar fi o treaptă degeaba.
  */
  var maiMult = document.getElementById('evenimente-mai-mult');
  var listaEv = document.getElementById('evenimente-lista');

  if (maiMult && listaEv) {
    maiMult.addEventListener('click', function () {
      var ascunse = listaEv.querySelectorAll('.ascuns');

      for (var i = 0; i < ascunse.length; i++) {
        ascunse[i].classList.remove('ascuns');
      }

      /**
       * Butonul pleacă: nu mai are ce descoperi.
       *
       * Înainte să dispară, mutăm atenția pe primul cartonaș scos la iveală.
       * Cine merge cu tastatura sau cu cititorul de ecran ar rămâne altfel cu
       * atenția pe un buton care tocmai s-a evaporat, adică nicăieri.
       */
      var primulNou = ascunse.length ? ascunse[0] : null;

      // Se duce rândul întreg, nu doar butonul: altfel ar rămâne în urma lui
      // un gol cât marginea lui, fără nimic în el.
      (maiMult.closest('.vezi-mai-mult') || maiMult).remove();

      if (primulNou) {
        primulNou.setAttribute('tabindex', '-1');
        primulNou.focus({ preventScroll: true });
      }
    });
  }

})();
