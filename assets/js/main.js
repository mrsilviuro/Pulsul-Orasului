/* =========================================================================
   PulsulOrasului.Ro — main.js
   1. Temă light / dark
   2. Meniu mobil
   3. — (a fost sliderul; prima fereastră e acum numai CSS, vezi „PRIMA
         FEREASTRĂ" din style.css. Numărul rămâne gol dinadins: comentarii
         din alte fișiere trimit la „secțiunea 11 din main.js" și altele ca
         ea, iar o renumerotare le-ar fi lăsat să arate spre altceva.)
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

  // Numele și chipul vin din sesiune, prin atributele scrise de inc/antet.php.
  // Chipul era până acum scris de mână aici, același pentru toată lumea: cine
  // răspundea la un comentariu se vedea pe sine cu poza altcuiva.
  function currentUser() {
    return {
      name:   body.getAttribute('data-user-nume') || 'Utilizator',
      avatar: body.getAttribute('data-user-poza') || 'assets/img/avatars/implicit.svg'
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

  /* --- Numărătoarea de caractere: aceleași reguli ca pe server ------------
     Contorul de sub o casetă de text n-are voie să spună altceva decât
     numără serverul. Spunea: „300 din 300" cu un rând gol la coadă, iar
     serverul răspundea „ai 298". Două deosebiri erau la mijloc, iar amândouă
     se văd la fel de urât — omul crede că a terminat și e trimis înapoi.

     1. Serverul curăță textul înainte să-l măsoare (curataTextPeRanduri din
        inc/validare.php): sfârșiturile de rând Windows devin unul singur,
        caracterele de control cad, șirurile de rânduri goale se scurtează la
        unul, iar spațiile de la capete se taie. Contorul măsura textul brut.
        Funcția de mai jos e oglinda ei, mișcare cu mișcare.

     2. Se numără caractere Unicode, nu unități UTF-16: „😀" e UN caracter,
        deși în memoria browserului ocupă două locuri. [...sir] rupe șirul pe
        caractere, exact ca mb_strlen pe server. `.length` simplu ar fi numărat
        două, iar `maxlength` din HTML — care numără tot în unități UTF-16 —
        de aceea nici nu se mai folosește la descriere.

     Un emoji din mai multe bucăți lipite („👨‍👩‍👧‍👦" = patru chipuri și trei
     lipituri) se numără ca șapte în amândouă părțile. Nu e ce vede ochiul,
     dar e același număr aici și acolo, iar asta era problema. */

  /** Oglinda lui curataTextPeRanduri() din inc/validare.php. */
  function curataTextPeRanduri(text) {
    return String(text)
      .replace(/\r\n|\r/g, '\n')
      .replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '')
      .replace(/\n{3,}/g, '\n\n')
      // trim() din PHP taie exact " \t\n\r\0\x0B", nu orice spațiu Unicode;
      // cel din JS ar fi tăiat și spațiul neîntrerupt, deci ar fi numărat
      // altfel decât serverul.
      .replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, '');
  }

  /** Câte caractere are textul — la fel ca mb_strlen($text, 'UTF-8'). */
  function numaraCaractere(text) {
    return [...String(text)].length;
  }

  /** Taie textul la atâtea caractere, fără să rupă un emoji în două. */
  function taieLaCaractere(text, cate) {
    var litere = [...String(text)];
    return litere.length > cate ? litere.slice(0, cate).join('') : String(text);
  }

  /**
   * Masca de scriere pentru ore și date: cifrele omului, semnele noastre.
   *
   * `grupe` spune din câte cifre e fiecare bucată — [2,2] la oră, [2,2,4] la
   * dată — iar `semn` e ce se pune între ele.
   *
   * Nu se lucrează pe poziții absolute, ci pe bucăți, fiindcă omul poate pune
   * și el semnele. „5-3-2027", tăiat la poziții fixe, ar fi ieșit „53-20-27" —
   * o dată care nu există, dintr-una care era limpede. Când o bucată e
   * încheiată de om cu un semn și are o cifră în loc de două, îi punem noi
   * zeroul: „5-" înseamnă ziua 5, adică 05.
   */
  function mascaCifre(brut, grupe, semn) {
    // Semnele de la început se scot întâi: altfel prima bucată ar fi una
    // goală, iar „goală, urmată de semn" ar căpăta zerouri din senin.
    var bucati = String(brut).replace(/^\D+/, '').split(/\D+/);
    var cifre  = '';

    for (var i = 0; i < bucati.length; i++) {
      var grup = bucati[i];

      // Numai bucățile de două cifre (zi, lună, oră, minut) capătă zeroul.
      // La an, „27" nu înseamnă „0027".
      if (i < bucati.length - 1 && i < grupe.length && grupe[i] <= 2) {
        while (grup.length < grupe[i]) { grup = '0' + grup; }
      }

      cifre += grup;
    }

    var incap = 0;
    for (var g = 0; g < grupe.length; g++) { incap += grupe[g]; }
    cifre = cifre.slice(0, incap);

    var scris = '';
    var luat  = 0;

    for (var k = 0; k < grupe.length && luat < cifre.length; k++) {
      if (k > 0) { scris += semn; }
      scris += cifre.substr(luat, grupe[k]);
      luat  += grupe[k];
    }

    return scris;
  }

  /* ---------------------- Câmpul de dată, ZZ-LL-AAAA --------------------- */
  /*
    Perechea în JS a lui dataDinFormular()/dataPentruFormular() din PHP, plus
    legătura dintre câmpul de text, butonul de calendar și `type="date"`-ul
    ascuns de sub el. Stau aici, în partea de sus, fiindcă le folosesc trei
    formulare: data evenimentului, data nașterii la înregistrare și aceeași
    dată la finalizarea intrării cu Google.

    Marcajul îl scrie inc/camp-data.php, care lămurește și de ce nu e
    `type="date"` de-a dreptul.
  */

  /** „25-12-2026" → „2026-12-25", sau '' dacă nu e o dată adevărată. */
  function dataPentruBaza(scrisa) {
    var m = /^(\d{2})-(\d{2})-(\d{4})$/.exec(String(scrisa).trim());
    if (!m) return '';

    var zi = +m[1], luna = +m[2], an = +m[3];
    var d = new Date(an, luna - 1, zi);

    // Data „31-04-2026" ar aluneca singură pe 1 mai; o prindem întrebând
    // calendarul dacă a rămas ce i-am dat. Tot el știe de ani bisecți.
    if (d.getFullYear() !== an || d.getMonth() !== luna - 1 || d.getDate() !== zi) {
      return '';
    }

    return m[3] + '-' + m[2] + '-' + m[1];
  }

  /**
   * Ce a scris omul, adus la ZZ-LL-AAAA.
   *
   * De obicei e treaba lui mascaCifre(), care pune cratimele cifră cu cifră.
   * Excepția e o dată lipită dintr-o bucată sau pusă de completarea automată a
   * browserului (`autocomplete="bday"` dă „1990-05-20"): scrisă pe litere
   * n-ar ajunge niciodată în forma asta, dar venită dintr-odată da — iar masca
   * ar face din ea „19-90-0520". Așa că o întoarcem noi, pe românește.
   */
  function normalizeazaData(brut) {
    var iso = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(brut).trim());
    if (iso) return iso[3] + '-' + iso[2] + '-' + iso[1];

    return mascaCifre(brut, [2, 2, 4], '-');
  }

  /**
   * Leagă un câmp de dată: masca, butonul și calendarul nativ.
   *
   * Cele trei bucăți se caută după id: „<id>", „<id>-calendar" și
   * „<id>-nativ", exact cum le scrie inc/camp-data.php. Dacă lipsesc butonul
   * sau calendarul, câmpul rămâne bun de scris cu mâna.
   */
  function legaCampData(id) {
    var camp = document.getElementById(id);
    if (!camp) return;

    var nativ = document.getElementById(id + '-nativ');
    var buton = document.getElementById(id + '-calendar');

    var potriveste = function () { camp.value = normalizeazaData(camp.value); };

    // „change" prinde completarea automată a browserului, care umple câmpul
    // fără să treacă prin „input".
    camp.addEventListener('input', potriveste);
    camp.addEventListener('change', potriveste);

    if (!nativ || !buton) return;

    /**
     * Calendarul ascuns nu ține valoarea decât cât e deschis.
     *
     * Are „min" și „max", iar o dată din afara lor l-ar face `:invalid` — și
     * atunci browserul ar opri trimiterea formularului arătând spre un câmp pe
     * care nimeni nu-l vede. Așa că îi punem valoarea doar dacă intră între
     * margini, și i-o luăm îndată ce omul a ales.
     */
    var incape = function (iso) {
      if (iso === '') return false;
      if (nativ.min && iso < nativ.min) return false;
      if (nativ.max && iso > nativ.max) return false;
      return true;
    };

    buton.addEventListener('click', function () {
      // Calendarul se deschide pe data deja scrisă, dacă e una bună.
      var iso = dataPentruBaza(camp.value);
      nativ.value = incape(iso) ? iso : '';

      /**
       * showPicker() cere o apăsare a omului — apăsarea asta e. Unde nu
       * există (browsere mai vechi), încercăm o apăsare pe câmpul ascuns;
       * dacă nici aia nu deschide nimic, nu se pierde nimic: data se poate
       * scrie oricând de mână, iar câmpul de text e cel care contează.
       */
      try {
        if (typeof nativ.showPicker === 'function') {
          nativ.showPicker();
        } else {
          nativ.click();
        }
      } catch (e) {}
    });

    nativ.addEventListener('change', function () {
      var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(nativ.value);
      nativ.value = '';
      if (!m) return;

      camp.value = m[3] + '-' + m[2] + '-' + m[1];
      camp.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }

  /** Pagina dinainte e tot de pe site-ul nostru? */
  function dinaintePeSite() {
    if (!document.referrer) return false;
    try {
      return new URL(document.referrer).origin === window.location.origin;
    } catch (e) {
      return false;
    }
  }

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
  var VARSTA_MINIMA = 10;

  function verificaDataNasteriiInPagina(v) {
    v = String(v || '').trim();
    if (!v) return 'Scrie data nașterii.';

    // Din câmp vine ZZ-LL-AAAA, cum se scrie o dată în România; aceeași
    // trecere o face și serverul, cu dataDinFormular().
    var iso = dataPentruBaza(v);
    if (!iso) return 'Data nașterii nu e validă. Scrie-o ca 25-12-1990.';

    var born = new Date(iso + 'T00:00:00');
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

    /* ---------------- deschiderea din adresă (#panel-going) --------------
       Un panou închis are `hidden`, deci browserul nu poate sări la el
       singur: o adresă cu diez ar duce omul pe pagină și l-ar lăsa tot la
       comentarii, întrebându-se ce trebuia să vadă. De aceea deschidem noi
       tabul și ne ducem tot noi până la el.

       Se potrivește pe id-ul PANOULUI, care e și ce scrie în `aria-controls`
       — adică exact ce ar fi ținta firească a unui link. E scris aici, în
       componenta de taburi, nu în pagina evenimentului: la fel se poate lega
       de-acum orice tab de pe site. Butonul din e-mailul de mulțumire trimite
       drept la „#panel-going" (vezi inc/multumiri.php).

       Pagina de intrare are hash-urile ei („#inregistrare"), care nu sunt
       id-uri de panou: acolo nu se potrivește nimic aici, iar codul ei de mai
       jos rămâne singurul care le citește. */
    function dupaAdresa() {
      var id = (window.location.hash || '').slice(1);
      if (!id) return;

      var tab = tabs.filter(function (t) {
        return t.getAttribute('aria-controls') === id || t.id === id;
      })[0];

      if (!tab) return;

      selectTab(tab, false);

      /* Abia după ce panoul a ieșit din `hidden` are rost mutarea ecranului.

         Cât de sus se oprește NU se socotește aici, ci din CSS, prin
         `scroll-margin-top` pe `.panel`. Nu de dragul frumuseții: browserul
         încearcă și el, singur, să sară la elementul din adresă, iar
         încercarea lui vine după a noastră și ar da-o la o parte. Cu regula
         în CSS, amândoi se opresc în același loc — sub antetul lipit de sus și
         sub rândul de taburi, ca omul să vadă pe care tab a nimerit. */
      var panou = document.getElementById(tab.getAttribute('aria-controls'));

      if (panou && panou.scrollIntoView) {
        panou.scrollIntoView({ block: 'start', behavior: 'smooth' });
      }

      /* Pe ecran mic, rândul de taburi se dă la dreapta cu degetul: la 390px
         nu încap toate trei. Tabul deschis din adresă poate fi tocmai cel din
         afara ecranului, iar atunci degeaba l-am adus la vedere pe verticală —
         se vede o listă de oameni sub două taburi stinse.

         Se mută pe mijloc, dar numai când chiar e ceva de mutat. La lat, unde
         intră toate, `scrollWidth` e cât `clientWidth` și nu se atinge nimic.
         Socoteala se face din dreptunghiurile de pe ecran, nu din `offsetLeft`
         — acela se măsoară față de primul strămoș așezat, care poate fi
         oricare. */
      if (tablist.scrollWidth > tablist.clientWidth) {
        var dreptTab   = tab.getBoundingClientRect();
        var dreptRand  = tablist.getBoundingClientRect();

        tablist.scrollLeft += (dreptTab.left - dreptRand.left)
                            - (dreptRand.width - dreptTab.width) / 2;
      }
    }

    dupaAdresa();
    window.addEventListener('hashchange', dupaAdresa);
  });

  /* ---------------------------- PRIMA PAGINĂ ----------------------------
     Filtrele de sus și „Vezi mai mult" de la coada listei.

     SINGURUL loc de pe site unde o listă se cere din bucăți. Peste tot în
     rest, tot ce e de arătat intră în pagină de la început și butonul doar dă
     la o parte — merge, fiindcă acolo listele au un capăt firesc: câți oameni
     încap la un eveniment, câte păreri primește un om. Prima pagină n-are
     niciunul: peste un an sunt sute de evenimente, iar a le trimite pe toate
     ca să se vadă zece ar fi o pagină de un megabyte pentru un ecran.

     Tot ce se aduce vine gata desenat de pe server (vezi
     randeazaListaEvenimente din inc/evenimente.php). Aici nu se construiește
     niciun cartonaș: ar fi fost a doua descriere a aceluiași lucru, în alt
     limbaj, care s-ar fi despărțit de prima la întâia corectură.

     Fără JS, formularul de deasupra merge singur: e un `<form method="get">`
     cu legături adevărate pentru categorii. Ce se face aici e doar să nu mai
     fie nevoie de o reîncărcare.
  ------------------------------------------------------------------------ */

  /**
   * `listaPrima`, nu `listaEv`: tot fișierul e o singură funcție mare, iar
   * `var` ține de funcție, nu de bloc. Un al doilea `var listaEv` de mai jos
   * (lista de evenimente de pe profil) o strivea pe asta — fără nicio eroare
   * la încărcare, fiindcă hoistarea le face pe amândouă să existe. Se vedea
   * abia la apăsare, ca „null are children". Numele lungi și anume nu costă
   * nimic; ăsta a costat o oră.
   */
  var filtre = document.getElementById('filtre');
  var listaPrima = document.querySelector('[data-lista-evenimente]');

  if (filtre && listaPrima) {
    var selectOras   = filtre.querySelector('[data-filtru-oras]');
    var cutieMai     = document.querySelector('[data-mai-multe]');
    var butonMai     = document.querySelector('[data-mai-multe-buton]');
    var randGol      = document.querySelector('[data-lista-goala]');
    var seIncarca    = false;

    /** Ce e ales chiar acum. Categoria se ține aici, nu se citește din DOM. */
    var categorieAleasa = (function () {
      var activ = filtre.querySelector('.chip.is-active');
      return activ ? (activ.getAttribute('data-filtru-categorie') || '') : '';
    })();

    function adresaCerere(deLa) {
      var p = new URLSearchParams();

      if (selectOras && selectOras.value) p.set('oras', selectOras.value);
      if (categorieAleasa) p.set('categorie', categorieAleasa);
      if (deLa > 0) p.set('de_la', String(deLa));

      return 'api/lista-evenimente.php?' + p.toString();
    }

    /**
     * Adresa din bara browserului merge după filtre.
     *
     * `replaceState`, nu `pushState`: fiecare apăsare pe o categorie ar fi
     * lăsat altfel o treaptă în istoric, iar butonul „înapoi" ar fi trebuit
     * apăsat de șapte ori ca să iasă din pagină. Ce rămâne e o adresă bună de
     * dat mai departe și de reîncărcat.
     */
    function potrivesteAdresa() {
      var p = new URLSearchParams();

      if (selectOras && selectOras.value) p.set('oras', selectOras.value);
      if (categorieAleasa) p.set('categorie', categorieAleasa);

      var coada = p.toString();
      history.replaceState(null, '', 'index.php' + (coada ? '?' + coada : ''));
    }

    /**
     * Aduce un teanc. `deLa = 0` înseamnă „de la capăt": lista se înlocuiește,
     * nu se lipește la ea — asta se întâmplă la fiecare schimbare de filtru.
     */
    function adu(deLa) {
      if (seIncarca) return;
      seIncarca = true;

      var textInitial = butonMai ? butonMai.textContent : '';
      if (butonMai) { butonMai.disabled = true; butonMai.textContent = 'Se încarcă…'; }

      fetch(adresaCerere(deLa), { credentials: 'same-origin' })
        .then(citesteRaspuns)
        .then(function (rez) {
          seIncarca = false;
          if (butonMai) { butonMai.disabled = false; butonMai.textContent = textInitial; }

          if (!rez.corp || !rez.corp.ok) { toast(mesajRaspunsNeasteptat(rez)); return; }

          if (deLa === 0) {
            listaPrima.innerHTML = rez.corp.html;
          } else {
            listaPrima.insertAdjacentHTML('beforeend', rez.corp.html);
          }

          if (cutieMai) cutieMai.hidden = !rez.corp.mai_sunt;
          if (randGol)  randGol.hidden  = listaPrima.children.length > 0;
        })
        .catch(function () {
          seIncarca = false;
          if (butonMai) { butonMai.disabled = false; butonMai.textContent = textInitial; }
          toast(mesajFaraLegatura());
        });
    }

    /* ----------------------------- orașul ---------------------------- */

    if (selectOras) {
      selectOras.addEventListener('change', function () {
        potrivesteAdresa();
        adu(0);
      });
    }

    /* --------------------------- categoriile -------------------------- */

    // Ascultarea e pe formular, nu pe fiecare legătură: așa merge și dacă
    // vreodată categoriile ajung să se schimbe fără reîncărcare.
    filtre.addEventListener('click', function (e) {
      var chip = e.target.closest('[data-filtru-categorie]');
      if (!chip || !filtre.contains(chip)) return;

      // Cu tasta apăsată pentru „deschide în filă nouă", legătura rămâne
      // legătură: omul a cerut altă filă, nu o filtrare aici.
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;

      e.preventDefault();

      categorieAleasa = chip.getAttribute('data-filtru-categorie') || '';

      filtre.querySelectorAll('[data-filtru-categorie]').forEach(function (alt) {
        var on = alt === chip;
        alt.classList.toggle('is-active', on);
        if (on) { alt.setAttribute('aria-current', 'true'); }
        else    { alt.removeAttribute('aria-current'); }
      });

      potrivesteAdresa();
      adu(0);
    });

    /* -------------------------- „Vezi mai mult" ----------------------- */

    if (butonMai) {
      butonMai.addEventListener('click', function () {
        adu(listaPrima.children.length);
      });
    }

    /**
     * Butonul „Arată" de lângă lista de orașe e pentru cine n-are JS. Noi
     * avem, deci filtrarea se face la schimbarea din listă — iar butonul
     * pleacă, ca să nu stea acolo unul care nu mai are ce face.
     *
     * E scris în `<noscript>`, deci nici n-ar trebui să ajungă în DOM; rândul
     * ăsta e pentru browserele care îl lasă acolo.
     */
    var butonNoscript = filtre.querySelector('noscript');
    if (butonNoscript) butonNoscript.remove();
  }

  /* --- Copierea linkului evenimentului -------------------------------------
     Facebook și WhatsApp sunt linkuri obișnuite, deci merg fără JavaScript.
     Copierea nu are cum: are nevoie de clipboard.

     navigator.clipboard cere pagină sigură (https, sau localhost). Pe http
     simplu — cum e site-ul în dezvoltare — pur și simplu nu există, așa că
     avem și calea veche: un câmp de text ținut în afara ecranului, selectat
     și copiat cu document.execCommand. E scoasă din uz, dar merge peste tot
     unde cealaltă nu.
  */
  var butonCopiaza = document.getElementById('copiaza-link');

  if (butonCopiaza) {
    /** Calea veche, pentru unde nu există Clipboard API. Întoarce true/false. */
    function copiazaPeVechi(text) {
      var camp = document.createElement('textarea');
      camp.value = text;

      // Scos din ecran, nu ascuns: un câmp cu `display:none` nu se poate
      // selecta, deci nici copia. `readOnly` oprește tastatura de pe telefon.
      camp.setAttribute('readonly', '');
      camp.style.position = 'fixed';
      camp.style.top = '-1000px';
      camp.style.opacity = '0';

      document.body.appendChild(camp);
      camp.select();
      camp.setSelectionRange(0, text.length);

      var reusit = false;
      try { reusit = document.execCommand('copy'); } catch (e) {}

      document.body.removeChild(camp);
      return reusit;
    }

    var ceasCopiat = null;

    function aratatCopiat() {
      toast('Link copiat!');
      butonCopiaza.classList.add('s-a-copiat');
      clearTimeout(ceasCopiat);
      ceasCopiat = setTimeout(function () {
        butonCopiaza.classList.remove('s-a-copiat');
      }, 1600);
    }

    butonCopiaza.addEventListener('click', function () {
      var text = butonCopiaza.getAttribute('data-copiaza') || window.location.href;

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text)
          .then(aratatCopiat)
          .catch(function () {
            // Permisiunea poate fi refuzată chiar și pe https.
            if (copiazaPeVechi(text)) { aratatCopiat(); }
            else { toast('Nu am putut copia linkul. Copiază-l din bara de adrese.'); }
          });
        return;
      }

      if (copiazaPeVechi(text)) { aratatCopiat(); }
      else { toast('Nu am putut copia linkul. Copiază-l din bara de adrese.'); }
    });
  }

  /* --- „Încheie evenimentul" ----------------------------------------------
     Doar organizatorul îl vede, și doar cât mai e ceva de încheiat. În două
     trepte, ca anularea: butonul își schimbă locul cu întrebarea. Confirmarea
     e desenată de noi, nu de browser — window.confirm() arată altfel pe
     fiecare sistem.

     După ce merge, pagina se reîncarcă. Nu e lene: reîncărcarea e chiar
     lucrul care arată banda de „s-a încheiat", butoanele stinse și textul la
     trecut — toate vin de la server, dintr-un singur loc. Cusute de mână în
     JS, ar fi început să difere de pagina adevărată de la prima corectură.
  */
  var evIncheie    = document.getElementById('ev-incheie');
  var evIncheieVb  = document.getElementById('ev-incheie-sigur');
  var evIncheieDa  = document.getElementById('ev-incheie-da');
  var evIncheieNu  = document.getElementById('ev-incheie-nu');

  if (evIncheie && evIncheieVb) {
    /* Confirmarea stă agățată sub buton, în antetul anunțului, deci se poartă
       ca orice panou plutitor: se închide și cu Escape, și cu o apăsare în
       afara ei. Altfel ar rămâne peste text până se apasă „Renunță". */
    var inchideIncheierea = function (inapoiPeButon) {
      if (evIncheieVb.hidden) return;
      evIncheieVb.hidden = true;
      evIncheie.setAttribute('aria-expanded', 'false');
      if (inapoiPeButon) evIncheie.focus();
    };

    evIncheie.addEventListener('click', function () {
      evIncheieVb.hidden = false;
      evIncheie.setAttribute('aria-expanded', 'true');
      if (evIncheieNu) evIncheieNu.focus();   // atenția pe ieșire, nu pe faptă
    });

    if (evIncheieNu) {
      evIncheieNu.addEventListener('click', function () { inchideIncheierea(true); });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') inchideIncheierea(true);
    });

    document.addEventListener('click', function (e) {
      if (evIncheieVb.hidden) return;
      if (evIncheieVb.contains(e.target) || evIncheie.contains(e.target)) return;
      inchideIncheierea(false);
    });
  }

  if (evIncheie && evIncheieDa) {
    evIncheieDa.addEventListener('click', function () {
      var textInitial = evIncheieDa.textContent;
      evIncheieDa.disabled = true;
      evIncheieDa.textContent = 'Se încheie…';

      function gata() {
        evIncheieDa.disabled = false;
        evIncheieDa.textContent = textInitial;
      }

      fetch('api/incheie-eveniment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          csrf: evIncheie.getAttribute('data-csrf') || '',
          slug: evIncheie.getAttribute('data-slug') || ''
        })
      })
      .then(citesteRaspuns)
      .then(function (rez) {
        if (!rez.corp) { gata(); toast(mesajRaspunsNeasteptat(rez)); return; }
        var c = rez.corp;

        if (!c.ok) { gata(); toast(c.mesaj || 'Nu am putut încheia evenimentul.'); return; }

        // Butonul rămâne stins: nu mai e nimic de apăsat cât se reîncarcă.
        toast(c.mesaj || 'Evenimentul a fost încheiat.');
        setTimeout(function () {
          window.location.href = c.redirect || window.location.href;
        }, 700);
      })
      .catch(function () {
        gata();
        toast(mesajFaraLegatura());
      });
    });
  }

  /* --- Moderarea, pentru staff --------------------------------------------
     „Aprobă" și „Respinge", la coada anunțului. Blocul lor nici nu ajunge în
     pagină pentru cine nu e om de casă (vezi event.php), iar serverul întreabă
     din nou la fiecare apăsare — un buton care nu e în HTML se poate oricând
     face dintr-o consolă.

     Fără treaptă de confirmare, spre deosebire de încheiere sau de anulare:
     hotărârea asta se poate lua înapoi oricând, cu butonul de alături. Ce nu
     se poate desface merită o întrebare; ce se poate, nu.
  */
  var panouModerare = document.querySelector('[data-moderare]');

  if (panouModerare) {
    var moderareEroare  = panouModerare.querySelector('[data-moderare-eroare]');
    var moderareCaseta  = panouModerare.querySelector('[data-moderare-caseta]');
    var moderareMotiv   = panouModerare.querySelector('[data-moderare-motiv]');
    var moderareDeschide = panouModerare.querySelector('[data-moderare-deschide]');
    var moderareRenunta = panouModerare.querySelector('[data-moderare-renunta]');
    var moderareEditare = panouModerare.querySelector('[data-moderare-editare]');
    var moderareUrmare  = panouModerare.querySelector('[data-moderare-urmare]');
    var moderareTextButon = panouModerare.querySelector('[data-moderare-text-buton]');
    var moderarePleaca  = false;

    /* --- Bifa „Editare necesară" ---
       Nu schimbă doar ce se trimite, ci și ce SCRIE pe buton și dedesubt.
       Cele două hotărâri sunt foarte diferite — una se ia înapoi, cealaltă
       golește tot ce s-a strâns în jurul anunțului — iar un buton care spune
       același lucru în amândouă situațiile ar fi ascuns tocmai deosebirea. */
    function potriveste() {
      if (!moderareEditare) return;

      var cuEditare = moderareEditare.checked;

      if (moderareTextButon) {
        moderareTextButon.textContent = cuEditare ? 'Cere îndreptarea' : 'Respinge anunțul';
      }

      if (moderareUrmare) {
        moderareUrmare.textContent = cuEditare
          ? 'Anunțul rămâne în așteptare, iar organizatorul primește un e-mail cu '
            + 'ce are de îndreptat.'
          : 'Anunțul va fi respins, iar comentariile, notele și listele lui se '
            + 'șterg. Nu se mai pot aduce înapoi.';
        moderareUrmare.classList.toggle('moderare__urmare--grea', !cuEditare);
      }
    }

    if (moderareEditare) {
      moderareEditare.addEventListener('change', potriveste);
      potriveste();
    }

    /* --- Caseta cu motivul respingerii ---
       „Respinge" n-o trimite pe loc: deschide caseta de dedesubt, unde se poate
       scrie de ce. Motivul e opțional — se poate apăsa direct pe „Respinge
       anunțul", cu caseta goală — dar deschiderea ei e clipa în care omul de
       casă e întrebat dacă are ceva de spus. Aprobarea n-are ce explica, deci
       pleacă dintr-o apăsare. */
    function inchideCasetaModerare(inapoiPeButon) {
      if (!moderareCaseta || moderareCaseta.hidden) return;

      moderareCaseta.hidden = true;
      if (moderareDeschide) {
        moderareDeschide.setAttribute('aria-expanded', 'false');
        if (inapoiPeButon) moderareDeschide.focus();
      }
    }

    if (moderareDeschide && moderareCaseta) {
      moderareDeschide.addEventListener('click', function () {
        moderareCaseta.hidden = false;
        moderareDeschide.setAttribute('aria-expanded', 'true');
        if (moderareMotiv) moderareMotiv.focus();
      });
    }

    if (moderareRenunta) {
      moderareRenunta.addEventListener('click', function () { inchideCasetaModerare(true); });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') inchideCasetaModerare(true);
    });

    panouModerare.querySelectorAll('[data-modereaza]').forEach(function (buton) {
      buton.addEventListener('click', function () {
        if (moderarePleaca) return;

        var stare = buton.getAttribute('data-modereaza');
        var textInitial = buton.innerHTML;

        moderarePleaca = true;
        buton.disabled = true;
        buton.textContent = stare === 'aprobat'
          ? 'Se aprobă…'
          : ((moderareEditare && moderareEditare.checked) ? 'Se trimite…' : 'Se respinge…');

        function gata() {
          moderarePleaca = false;
          buton.disabled = false;
          buton.innerHTML = textInitial;
        }

        if (moderareEroare) { moderareEroare.hidden = true; }

        fetch('api/modereaza-eveniment.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            csrf:  panouModerare.getAttribute('data-csrf') || '',
            slug:  panouModerare.getAttribute('data-slug') || '',
            stare: stare,

            // Numai la respingere au rost; la aprobare serverul le lasă
            // deoparte oricum.
            motiv: (moderareMotiv && stare === 'respins') ? moderareMotiv.value : '',
            editare: (moderareEditare && stare === 'respins') ? moderareEditare.checked : false
          })
        })
        .then(citesteRaspuns)
        .then(function (rez) {
          if (!rez.corp) { gata(); toast(mesajRaspunsNeasteptat(rez)); return; }
          var c = rez.corp;

          if (!c.ok) {
            gata();

            // Ce n-a mers rămâne scris în panou, nu doar într-un toast care
            // pleacă: „e deja aprobat" e o lămurire, nu o veste de o clipă.
            var necaz = (c.erori && c.erori.motiv) || c.mesaj
                      || 'Nu am putut schimba starea.';

            if (moderareEroare) {
              moderareEroare.textContent = necaz;
              moderareEroare.hidden = false;
            } else {
              toast(necaz);
            }
            return;
          }

          /* Reîncărcarea e chiar lucrul care arată ce s-a schimbat: banda de
             stare de sus, butoanele de aici și, la aprobare, tot ce se poate
             face pe o pagină publicată. Nu încercăm să le potrivim din JS. */
          toast(c.mesaj || 'Gata.');
          setTimeout(function () {
            window.location.href = c.redirect || window.location.href;
          }, 700);
        })
        .catch(function () {
          gata();
          toast(mesajFaraLegatura());
        });
      });
    });
  }

  /* --- Butoanele de participare -------------------------------------------
     „Mă interesează" e o însemnare: se trimite pe loc. „Voi participa" e o
     hotărâre care dă numele și numărul de telefon mai departe, deci trece
     întâi printr-o treaptă de confirmare.

     Nimic nu se socotește aici. Numerele și rândul de sub butoane vin de la
     server, după fiecare apăsare: între două apăsări ale omului nostru pot
     intra alți zece, iar un număr crescut cu unu în browser ar fi rămas greșit
     până la următoarea reîncărcare.

     Retragerea n-are buton al ei — se apasă pe starea în care ești deja. Nici
     asta nu se hotărăște aici: spre server pleacă butonul apăsat, iar el știe
     starea adevărată. O filă rămasă deschisă de ieri n-are cum să ne pună să
     facem altceva decât se cuvine.
  */
  var rsvpSectiune = document.getElementById('rsvp');
  var rsvpButtons  = document.querySelectorAll('[data-rsvp]');

  // Actualizează toate locurile unde apare numărul (buton, tab, text din panou).
  function setRsvpCount(kind, value) {
    document.querySelectorAll('[data-count-for="' + kind + '"]').forEach(function (el) {
      el.textContent = value;
    });
  }

  /**
   * Cercurile suprapuse de sub butoane, puse la loc cu ce a trimis serverul.
   *
   * Gata desenate, din randeazaChipuri() — aceeași funcție care le scrie la
   * încărcarea paginii.
   */
  function setChipuri(html) {
    var cutie = document.getElementById('rsvp-people');
    if (cutie && typeof html === 'string') cutie.innerHTML = html;
  }

  /**
   * Listele din taburi, împrospătate după orice schimbare.
   *
   * Fiecare panou cu `data-oameni` și-a agățat pe el o funcție `__aplica`
   * (vezi blocul „PANOURILE CU OAMENI"). Aici se caută panoul după starea lui
   * și i se dă bucata care îl privește din răspunsul serverului.
   *
   * De asta atârnă tot ce se vede în timp real: cine apasă „Voi participa" își
   * vede numele apărând în tabul de dedesubt pe loc, nu după o reîncărcare pe
   * care n-avea de ce s-o ghicească.
   */
  function aplicaPanouriOameni(panouri) {
    if (!panouri) return;

    document.querySelectorAll('[data-oameni]').forEach(function (panou) {
      var alLui = panouri[panou.getAttribute('data-stare')];
      if (alLui && panou.__aplica) panou.__aplica(alLui);
    });
  }

  /**
   * Tot ce urmează se leagă doar dacă există caseta.
   *
   * La un eveniment care a început, event.php n-o mai desenează deloc, deci
   * `rsvpSectiune` e null și nu se leagă nimic. A fost o vreme aici un rând
   * care golea lista de butoane la un eveniment încheiat, fiindcă veneau
   * stinse din HTML și `gata()` le-ar fi putut aprinde la loc după o cerere;
   * acum n-are ce aprinde, fiindcă n-au ajuns în pagină. Oprirea adevărată e
   * oricum în api/interes.php.
   */
  if (rsvpSectiune && rsvpButtons.length) {
    var rsvpConfirm   = document.getElementById('rsvp-confirm');
    var rsvpConfirmDa = document.getElementById('rsvp-confirm-da');
    var rsvpConfirmNu = document.getElementById('rsvp-confirm-nu');
    var rsvpTelefon   = document.getElementById('rsvp-telefon');
    var rsvpOameni    = document.getElementById('rsvp-people');
    var rsvpPlin      = rsvpSectiune.querySelector('.rsvp__plin');

    /** Ce s-a schimbat după o apăsare reușită. */
    function rsvpPotriveste(c) {
      setRsvpCount('interesat', c.numar.interesat);
      setRsvpCount('participant', c.numar.participant);

      rsvpButtons.forEach(function (b) {
        b.setAttribute('aria-pressed', String(b.getAttribute('data-rsvp') === c.stare));
      });

      /**
       * Chipurile și vorba vin gata desenate de pe server, din aceeași funcție
       * care le scrie la încărcarea paginii (randeazaChipuri). De
       * aceea se pun cu innerHTML: e HTML făcut de noi, escapat cu h(), nu
       * text venit de la cine a apăsat.
       */
      if (rsvpOameni && typeof c.oameni === 'string') {
        rsvpOameni.innerHTML = c.oameni;
      }

      // Și listele din taburi: omul tocmai a intrat sau a ieșit de pe una din
      // ele, iar asta trebuie să se vadă pe loc, nu la următoarea reîncărcare.
      aplicaPanouriOameni(c.panouri);

      // Cine tocmai a intrat pe listă nu mai are de ce să vadă „nu mai sunt
      // locuri", iar cine s-a retras poate găsi ușa închisă la loc.
      if (rsvpPlin) rsvpPlin.hidden = c.stare === 'participant';

      /**
       * Butonul de participare se stinge la loc dacă s-au ocupat locurile —
       * dar niciodată pentru cine e chiar acum pe listă: acela trebuie să se
       * poată retrage.
       *
       * Opreliștile de om (ușa închisă, evenimentul pentru celălalt sex) nu se
       * ating aici: pe ele le-a hotărât serverul la desenarea paginii, prin
       * motivBlocajParticipare(), și nu se schimbă de la o apăsare la alta.
       */
      var btnGoing = document.getElementById('btn-going');

      if (btnGoing && rsvpPlin && !btnGoing.hasAttribute('title')) {
        btnGoing.disabled = !rsvpPlin.hidden;
      }
    }

    /** Trimite apăsarea. `confirmat` și `telefon` doar la participare. */
    function rsvpTrimite(stare, extra, buton) {
      /**
       * „Se trimite…" se scrie doar pe butoanele care sunt numai text —
       * „Da, particip". Butoanele mari de deasupra au înăuntru o iconiță, o
       * etichetă și numărul, iar `textContent` le-ar fi șters pe toate trei și
       * le-ar fi înlocuit cu un șir. Pe ele e de ajuns că se sting.
       */
      var doarText   = buton && buton.children.length === 0;
      var textInitial = doarText ? buton.textContent : '';

      if (buton) { buton.disabled = true; }
      if (doarText) { buton.textContent = 'Se trimite…'; }

      function gata() {
        if (buton) { buton.disabled = false; }
        if (doarText) { buton.textContent = textInitial; }
      }

      var trup = {
        csrf:  rsvpSectiune.getAttribute('data-csrf') || '',
        slug:  rsvpSectiune.getAttribute('data-slug') || '',
        stare: stare
      };

      Object.keys(extra || {}).forEach(function (k) { trup[k] = extra[k]; });

      fetch('api/interes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(trup)
      })
      .then(citesteRaspuns)
      .then(function (rez) {
        gata();

        if (!rez.corp) { toast(mesajRaspunsNeasteptat(rez)); return; }
        var c = rez.corp;

        // Sesiunea s-a stins între încărcarea paginii și apăsare.
        if (rez.stare === 401) {
          toast('Intră în cont ca să te adaugi pe listă.');
          setTimeout(goToLogin, 900);
          return;
        }

        if (c.erori) {
          setError('rsvp-telefon', 'err-rsvp-telefon',
            c.erori.telefon || 'Verifică numărul.');
          if (rsvpTelefon) rsvpTelefon.focus();
          return;
        }

        if (!c.ok) { toast(c.mesaj || 'Nu am putut trimite răspunsul.'); return; }

        if (rsvpConfirm) rsvpConfirm.hidden = true;
        rsvpPotriveste(c);
        toast(c.mesaj || 'Gata.');
      })
      .catch(function () {
        gata();
        toast(mesajFaraLegatura());
      });
    }

    rsvpButtons.forEach(function (btn) {
      var kind = btn.getAttribute('data-rsvp');

      btn.addEventListener('click', function () {
        // Pagina evenimentului oricum nu se deschide fără cont, dar dacă
        // sesiunea a expirat sub ochii omului, îl trimitem înapoi la intrare
        // cu adresa de acum în buzunar.
        if (!isLoggedIn()) {
          toast('Intră în cont ca să te adaugi pe listă.');
          setTimeout(goToLogin, 900);
          return;
        }

        var apasat = btn.getAttribute('aria-pressed') === 'true';

        // Participarea: dacă omul nu e deja înăuntru, întâi confirmarea.
        // Retragerea nu cere nimic — pleacă de pe listă, n-are ce confirma.
        if (kind === 'participant' && !apasat && rsvpConfirm) {
          rsvpConfirm.hidden = false;
          setError('rsvp-telefon', 'err-rsvp-telefon', '');
          (rsvpTelefon || rsvpConfirmNu || rsvpConfirm).focus();
          return;
        }

        rsvpTrimite(kind, {}, btn);
      });
    });

    if (rsvpConfirmNu && rsvpConfirm) {
      rsvpConfirmNu.addEventListener('click', function () {
        rsvpConfirm.hidden = true;
        var btnGoing = document.getElementById('btn-going');
        if (btnGoing) btnGoing.focus();
      });
    }

    if (rsvpConfirmDa) {
      rsvpConfirmDa.addEventListener('click', function () {
        rsvpTrimite('participant', {
          confirmat: true,
          telefon: rsvpTelefon ? rsvpTelefon.value : ''
        }, rsvpConfirmDa);
      });
    }

    // Cât scrie omul numărul, eroarea de dinainte nu mai are ce spune.
    if (rsvpTelefon) {
      rsvpTelefon.addEventListener('input', function () {
        setError('rsvp-telefon', 'err-rsvp-telefon', '');
      });
    }
  }

  /* ------------------------------ COMENTARII ----------------------------
     Discuția de sub un eveniment. Tot ce se vede aici vine gata desenat de pe
     server, din inc/comentarii.php: se scrie un comentariu, se primește
     înapoi `<li>`-ul lui, se pune în listă. Nu se lipește HTML din bucăți în
     JS — ar fi însemnat două locuri care desenează același lucru, iar al
     doilea ar fi rămas în urmă de la prima corectură.

     Ascunsul e singurul lucru care se face doar aici: toate comentariile
     intră în pagină de la început, iar „Vezi mai multe" nu aduce nimic, doar
     dă la o parte.
  ------------------------------------------------------------------------ */

  var panouComentarii = document.querySelector('[data-comentarii]');

  if (panouComentarii) {
    var listaComentarii = panouComentarii.querySelector('[data-lista-comentarii]');
    var slugComentarii  = panouComentarii.getAttribute('data-slug') || '';
    var golComentarii   = panouComentarii.querySelector('[data-comentarii-gol]');
    var maiMulte        = panouComentarii.querySelector('[data-mai-multe]');
    var maiMulteButon   = panouComentarii.querySelector('[data-mai-multe-buton]');
    var contorComentarii = document.querySelector('[data-count-for="comentarii"]');

    // Câte se arată deodată. Numărul vine din COMENTARII_DEODATA
    // (inc/comentarii.php), ca să fie scris într-un singur loc.
    var deodata  = parseInt(panouComentarii.getAttribute('data-deodata'), 10) || 15;
    var vizibile = deodata;

    /* --------------------------- ajutătoare ---------------------------- */

    /** „3 zile", dar „21 de zile" — aceeași regulă ca numaratoare() din PHP. */
    function numaratoare(cate, substantiv) {
      var ultimele = cate % 100;
      var direct = ultimele >= 1 && ultimele <= 19;
      return cate + (direct ? ' ' : ' de ') + substantiv;
    }

    /** Toate comentariile, în ordinea în care apar pe ecran. */
    function toateComentariile() {
      if (!listaComentarii) return [];
      return Array.prototype.slice.call(listaComentarii.querySelectorAll('.comment'));
    }

    /** Comentariul cu id-ul ăsta, oriunde ar fi în listă. */
    function comentariulCu(id) {
      if (!listaComentarii) return null;
      return listaComentarii.querySelector('[data-comentariu="' + id + '"]');
    }

    /**
     * Ascunde tot ce trece de câte se arată acum, și pune pe buton câte au
     * rămas.
     *
     * Se merge pe comentarii în ordinea din pagină — principal, apoi
     * răspunsurile lui, apoi următorul principal. Ordinea asta face ca
     * primele N să fie mereu un început întreg de discuție: dacă un principal
     * a rămas dincolo de tăietură, răspunsurile lui sunt și ele dincolo, deci
     * nu se poate întâmpla să atârne un răspuns fără întrebarea lui.
     */
    function potrivesteAscunsul() {
      var toate = toateComentariile();
      var ascunse = 0;

      // Fără mai puțin decât un teanc, oricâte s-ar fi șters între timp.
      if (vizibile < deodata) vizibile = deodata;

      toate.forEach(function (li, i) {
        var deAscuns = i >= vizibile;
        li.hidden = deAscuns;
        if (deAscuns) ascunse++;
      });

      /**
       * Un fir tăiat la mijloc: principalul se vede, dar toate răspunsurile
       * lui au rămas dincolo. Lista lor are dungă și spațiu deasupra, deci
       * goală s-ar fi văzut ca o cutie fără nimic în ea.
       */
      if (listaComentarii) {
        listaComentarii.querySelectorAll('.comment__replies').forEach(function (ul) {
          var vreunul = Array.prototype.some.call(ul.children, function (li) {
            return !li.hidden;
          });

          ul.hidden = !vreunul;
        });
      }

      if (maiMulte) {
        maiMulte.hidden = ascunse === 0;

        // Doar numărul în paranteză: „comentarii" e deja în eticheta
        // butonului, iar „încă 12 comentarii" acolo l-ar fi spus de două ori.
        if (maiMulteButon && ascunse > 0) {
          maiMulteButon.textContent = 'Vezi mai multe comentarii (încă ' + ascunse + ')';
        }
      }

      if (golComentarii) golComentarii.hidden = toate.length > 0;
    }

    /** Numărul de pe tabul „Comentarii", venit din bază după fiecare schimbare. */
    function setContor(cate) {
      if (contorComentarii && typeof cate === 'number') {
        contorComentarii.textContent = cate;
      }
    }

    /**
     * Textul așa cum l-a scris omul, recules din pagină pentru caseta de
     * editare.
     *
     * Se desface exact ce a împachetat textulComentariului() din PHP:
     * paragrafele au devenit `<p>`, rândurile dinăuntru `<br>`, iar restul e
     * text trecut prin h(). Nu se ține o a doua copie a textului într-un
     * atribut: ar fi fost aceleași vorbe scrise de două ori în pagină.
     */
    function textulBrut(articol) {
      var bucati = [];

      articol.querySelectorAll('.comment__text').forEach(function (p) {
        var cutie = document.createElement('div');
        cutie.innerHTML = p.innerHTML.replace(/<br\s*\/?>/gi, '\n');

        /**
         * „@N. Prenume" nu e al omului, e pus de noi.
         *
         * Stă în textul comentariului, dar nu vine din el: îl desenează
         * randeazaComentariu() din `raspuns_la_id`. Lăsat aici, ar fi apărut
         * în caseta de editare ca și cum l-ar fi scris omul — iar la salvare
         * ar fi intrat de-a binelea în bază, ca text. La a doua corectură ar
         * fi fost două.
         */
        var mentiune = cutie.querySelector('.comment__mentiune');
        if (mentiune) mentiune.remove();

        bucati.push(cutie.textContent.replace(/^\s+/, ''));
      });

      return bucati.join('\n\n');
    }

    /** Caseta de eroare de sub un câmp, aprinsă sau stinsă. */
    function aratErroarea(camp, text) {
      if (!camp) return;
      camp.textContent = text || '';
      camp.hidden = !text;
    }

    /* ---------------------- vorbitul cu serverul ----------------------- */

    /**
     * O cerere către api/comentarii.php.
     *
     * `laReusit` primește corpul răspunsului. Tot ce înseamnă „a picat" —
     * sesiune expirată, eroare de câmp, server mut — se rezolvă aici, o
     * singură dată, nu la fiecare apăsare în parte.
     */
    function trimiteComentariu(trup, buton, campEroare, laReusit) {
      /**
       * „Se trimite…" se scrie doar pe butoanele care sunt numai text — ca la
       * participare. Butonul de apreciere are înăuntru o iconiță și un număr,
       * iar `textContent` le-ar fi șters pe amândouă și le-ar fi înlocuit cu
       * un șir. Pe el e de ajuns că se stinge.
       */
      var doarText    = !!buton && buton.children.length === 0;
      var textInitial = doarText ? buton.textContent : '';

      if (buton) { buton.disabled = true; }
      if (doarText) { buton.textContent = 'Se trimite…'; }

      function gata() {
        if (buton) { buton.disabled = false; }
        if (doarText) { buton.textContent = textInitial; }
      }

      trup.csrf = panouComentarii.getAttribute('data-csrf') || '';
      trup.slug = slugComentarii;

      fetch('api/comentarii.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(trup)
      })
      .then(citesteRaspuns)
      .then(function (rez) {
        gata();

        if (!rez.corp) { toast(mesajRaspunsNeasteptat(rez)); return; }
        var c = rez.corp;

        // Sesiunea s-a stins între încărcarea paginii și apăsare.
        if (rez.stare === 401) {
          toast('Intră în cont ca să comentezi.');
          setTimeout(goToLogin, 900);
          return;
        }

        if (c.erori) {
          aratErroarea(campEroare, c.erori.text || 'Verifică ce ai scris.');
          return;
        }

        if (!c.ok) { toast(c.mesaj || 'Nu am putut trimite comentariul.'); return; }

        aratErroarea(campEroare, '');
        laReusit(c);
      })
      .catch(function () {
        gata();
        toast(mesajFaraLegatura());
      });
    }

    /* ------------------------ comentariu nou --------------------------- */

    var formComentariu = panouComentarii.querySelector('[data-comment-form]');

    if (formComentariu) {
      var campComentariu  = formComentariu.querySelector('textarea');
      var eroareComentariu = formComentariu.querySelector('#err-comentariu');

      formComentariu.addEventListener('submit', function (e) {
        e.preventDefault();

        var text = campComentariu.value.trim();

        if (!text) {
          aratErroarea(eroareComentariu, 'Scrie ceva înainte de a trimite.');
          campComentariu.focus();
          return;
        }

        trimiteComentariu(
          { fapta: 'adauga', text: text },
          formComentariu.querySelector('button[type="submit"]'),
          eroareComentariu,
          function (c) {
            campComentariu.value = '';
            adaugaInLista(c);
            toast(c.mesaj || 'Comentariul tău a fost publicat.');
          }
        );
      });

      // Cât scrie omul, eroarea de dinainte nu mai are ce spune.
      campComentariu.addEventListener('input', function () {
        aratErroarea(eroareComentariu, '');
      });
    }

    /**
     * Comentariul proaspăt, pus la locul lui.
     *
     * Principalele intră în capul listei, deși ordinea de pe server e după
     * aprecieri, iar unul proaspăt are zero. Dinadins: omul tocmai a apăsat
     * „Publică" și trebuie să-și vadă vorba, nu s-o caute printre cele
     * ridicate de alții. La următoarea deschidere a paginii se așază la locul
     * ei — vezi grupeazaComentarii() din inc/comentarii.php.
     *
     * Răspunsurile intră la coada firului lor, fiindcă acolo e o discuție, iar
     * o discuție se citește de la început — și acolo locul e chiar ăsta.
     *
     * `vizibile` crește cu unu: fără asta, un comentariu nou ar fi împins
     * ultimul comentariu vizibil dincolo de tăietură, și ar fi părut că
     * scrisul cuiva face să dispară scrisul altcuiva.
     */
    function adaugaInLista(c) {
      if (!listaComentarii) return;

      var cutie = document.createElement('div');
      cutie.innerHTML = c.html;

      var nou = cutie.firstElementChild;
      if (!nou) return;

      if (c.parinte) {
        var parinte = comentariulCu(c.parinte);
        if (!parinte) return;

        var raspunsuri = parinte.querySelector(':scope > .comment__replies');

        if (!raspunsuri) {
          raspunsuri = document.createElement('ul');
          raspunsuri.className = 'comment__replies';
          raspunsuri.setAttribute('data-raspunsuri', '');
          parinte.appendChild(raspunsuri);
        }

        raspunsuri.appendChild(nou);
      } else {
        listaComentarii.insertBefore(nou, listaComentarii.firstChild);
      }

      /**
       * Comentariul proaspăt trebuie să se vadă, oriunde ar fi căzut.
       *
       * Un principal intră în cap, deci e primul; un răspuns intră la coada
       * firului lui, care poate fi tocmai lângă tăietură. „Încă unul" n-ar fi
       * fost de-ajuns acolo — omul ar fi apăsat „Publică" și nu s-ar fi
       * întâmplat nimic pe ecran.
       */
      var locul = toateComentariile().indexOf(nou);

      vizibile = Math.max(vizibile + 1, locul + 1);

      potrivesteAscunsul();
      setContor(c.numar);

      // Sub un fir lung, comentariul nou poate cădea sub marginea ecranului.
      nou.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    /* --------------------------- răspunsul ----------------------------- */

    /**
     * Caseta de răspuns, deschisă sub comentariul pe care s-a apăsat.
     *
     * Una singură pe pagină: a doua ar fi însemnat două texte începute și
     * uitate. Apăsarea pe „Răspunde" la alt comentariu o mută, nu o dublează.
     */
    function inchideRaspunsul() {
      var deschis = panouComentarii.querySelector('[data-reply-form]');

      if (deschis) {
        var butonul = deschis.__buton;
        if (butonul) butonul.textContent = 'Răspunde';
        deschis.remove();
      }
    }

    function deschideRaspunsul(articol, buton) {
      var li = articol.closest('.comment');
      var id = li.getAttribute('data-comentariu');

      // A doua apăsare pe același buton închide caseta.
      var deschis = panouComentarii.querySelector('[data-reply-form]');
      if (deschis && deschis.__buton === buton) { inchideRaspunsul(); return; }

      inchideRaspunsul();

      var numeleLui = articol.querySelector('.comment__author');
      var numele = numeleLui ? numeleLui.textContent : 'acest comentariu';
      var user = currentUser();

      var form = document.createElement('form');
      form.className = 'reply-form';
      form.setAttribute('data-reply-form', '');
      form.__buton = buton;

      form.innerHTML =
        '<img class="reply-form__avatar" src="' + user.avatar + '" alt="" width="96" height="96">' +
        '<div class="reply-form__main">' +
          '<p class="reply-form__catre">Îi răspunzi lui <strong></strong></p>' +
          '<textarea rows="2" placeholder="Scrie un răspuns…" aria-label="Scrie un răspuns"></textarea>' +
          '<p class="field__error" data-eroare hidden></p>' +
          '<div class="reply-form__actions">' +
            '<button class="btn btn--primary btn--xs" type="submit">Trimite</button>' +
            '<button class="btn btn--text" type="button" data-cancel-reply>Renunță</button>' +
          '</div>' +
        '</div>';

      // Numele se pune ca TEXT, nu lipit în HTML: e numele altui om, iar acolo
      // pot fi ghilimele sau semne care ar strica marcajul din jur.
      form.querySelector('.reply-form__catre strong').textContent = numele;

      // Sub articol, nu în el: răspunsurile stau lângă `article`, iar caseta
      // trebuie să rămână între comentariu și discuția de sub el.
      articol.parentNode.insertBefore(form, articol.nextSibling);

      buton.textContent = 'Anulează';
      form.querySelector('textarea').focus();

      var eroare = form.querySelector('[data-eroare]');

      form.querySelector('[data-cancel-reply]').addEventListener('click', inchideRaspunsul);

      form.querySelector('textarea').addEventListener('input', function () {
        aratErroarea(eroare, '');
      });

      form.addEventListener('submit', function (e) {
        e.preventDefault();

        var camp = form.querySelector('textarea');
        var text = camp.value.trim();

        if (!text) {
          aratErroarea(eroare, 'Scrie ceva înainte de a trimite.');
          camp.focus();
          return;
        }

        trimiteComentariu(
          { fapta: 'adauga', text: text, raspunde_la: id },
          form.querySelector('button[type="submit"]'),
          eroare,
          function (c) {
            inchideRaspunsul();
            adaugaInLista(c);
            toast(c.mesaj || 'Răspunsul tău a fost publicat.');
          }
        );
      });
    }

    /* --------------------------- corectura ----------------------------- */

    function inchideEditarea() {
      var deschis = panouComentarii.querySelector('[data-edit-form]');

      if (deschis) {
        var articolul = deschis.closest('.comment__body');
        if (articolul) articolul.classList.remove('is-editing');
        deschis.remove();
      }
    }

    function deschideEditarea(articol) {
      var li = articol.closest('.comment');
      var id = li.getAttribute('data-comentariu');

      inchideEditarea();
      inchideRaspunsul();

      var form = document.createElement('form');
      form.className = 'edit-form';
      form.setAttribute('data-edit-form', '');

      form.innerHTML =
        '<label class="sr-only" for="edit-' + id + '">Editează comentariul</label>' +
        '<textarea id="edit-' + id + '" rows="3"></textarea>' +
        '<p class="field__error" data-eroare hidden></p>' +
        '<div class="reply-form__actions">' +
          '<button class="btn btn--primary btn--xs" type="submit">Salvează</button>' +
          '<button class="btn btn--text" type="button" data-cancel-edit>Renunță</button>' +
        '</div>';

      var camp = form.querySelector('textarea');
      camp.value = textulBrut(articol);

      // Caseta se pune în locul textului, iar textul și uneltele se sting din
      // CSS (`.is-editing`) — nu se scot din pagină. Așa se pot pune la loc
      // întregi dacă omul se răzgândește.
      articol.classList.add('is-editing');
      articol.querySelector('.comment__main').appendChild(form);

      camp.focus();
      camp.setSelectionRange(camp.value.length, camp.value.length);

      var eroare = form.querySelector('[data-eroare]');

      form.querySelector('[data-cancel-edit]').addEventListener('click', inchideEditarea);

      camp.addEventListener('input', function () { aratErroarea(eroare, ''); });

      form.addEventListener('submit', function (e) {
        e.preventDefault();

        var text = camp.value.trim();

        if (!text) {
          aratErroarea(eroare, 'Scrie ceva înainte de a salva.');
          camp.focus();
          return;
        }

        trimiteComentariu(
          { fapta: 'editeaza', id: id, text: text },
          form.querySelector('button[type="submit"]'),
          eroare,
          function (c) {
            inchideEditarea();
            inlocuiesteArticolul(c.id, c.html);
            toast(c.mesaj || 'Comentariul a fost salvat.');
          }
        );
      });
    }

    /**
     * Schimbă doar comentariul, nu și discuția de sub el.
     *
     * Se înlocuiește `<article>`, nu `<li>`-ul: răspunsurile stau în `<li>`,
     * ca frați ai articolului, iar înlocuirea întregului `<li>` le-ar fi luat
     * cu ea. De asta le-a despărțit inc/comentarii.php.
     */
    function inlocuiesteArticolul(id, html) {
      var li = comentariulCu(id);
      if (!li || !html) return;

      var vechi = li.querySelector(':scope > .comment__body');
      if (!vechi) return;

      var cutie = document.createElement('div');
      cutie.innerHTML = html;

      var nou = cutie.firstElementChild;
      if (nou) li.replaceChild(nou, vechi);
    }

    /* --------------------------- ștergerea ----------------------------- */

    function stergeComentariul(articol) {
      var li = articol.closest('.comment');
      var id = li.getAttribute('data-comentariu');

      if (!window.confirm('Ștergi comentariul? Nu se mai poate aduce înapoi.')) {
        return;
      }

      inchideEditarea();
      inchideRaspunsul();

      trimiteComentariu({ fapta: 'sterge', id: id }, null, null, function (c) {
        if (c.fel === 'golit') {
          /**
           * Avea răspunsuri, deci rândul rămâne — gol, fără nume și fără chip.
           * Scos de tot, ar fi lăsat răspunsurile la el atârnate în aer.
           */
          inlocuiesteArticolul(c.id, c.html);
        } else {
          var deScos = comentariulCu(c.id);
          if (deScos) deScos.remove();

          // Ultimul răspuns de sub o piatră de mormânt a plecat, deci pleacă
          // și ea: rămăsese doar ca să țină discuția legată.
          if (c.parinte_sters) {
            var parintele = comentariulCu(c.parinte_sters);
            if (parintele) parintele.remove();
          }
        }

        potrivesteAscunsul();
        setContor(c.numar);
        toast(c.mesaj || 'Comentariul a fost șters.');
      });
    }

    /* -------------------------- aprecierea ----------------------------- */

    function apreciaza(buton) {
      var li = buton.closest('.comment');
      var id = li.getAttribute('data-comentariu');
      var contor = buton.querySelector('[data-like-count]');

      /**
       * Numărul nu se socotește în browser, ci se citește din bază după
       * schimbare. Între două apăsări ale omului nostru pot intra alți zece,
       * iar un contor crescut cu unu în browser ar fi rămas greșit până la
       * următoarea reîncărcare — aceeași alegere ca la listele de participanți.
       */
      trimiteComentariu({ fapta: 'apreciaza', id: id }, buton, null, function (c) {
        buton.setAttribute('aria-pressed', String(!!c.apreciat));
        if (contor) contor.textContent = c.cate;
      });
    }

    /* --------------------------- raportul ------------------------------ */

    /**
     * Steagul de raportat: o apăsare îl pune, a doua îl ia înapoi.
     *
     * Butonul nu se scrie deloc pentru autorul comentariului și nici pentru
     * cine nu e conectat (vezi poateRaporta din inc/comentarii.php), deci aici
     * nu mai e nimic de cernut — dar serverul întreabă oricum din nou.
     *
     * Nu se arată niciun număr: câți au raportat e treaba staff-ului. Omul vede
     * doar dacă el însuși a raportat — din culoarea steagului și din vorba de
     * lângă el, care trece din „Raportează" în „Raportat".
     */
    function raporteaza(buton) {
      var li = buton.closest('.comment');
      var id = li.getAttribute('data-comentariu');
      var vorba = buton.querySelector('[data-raport-text]');

      trimiteComentariu({ fapta: 'raporteaza', id: id }, buton, null, function (c) {
        var raportat = !!c.raportat;

        buton.setAttribute('aria-pressed', String(raportat));
        buton.classList.toggle('is-raportat', raportat);

        buton.setAttribute('title', raportat
          ? 'Ai raportat comentariul. Apasă ca să retragi.'
          : 'Raportează comentariul');
        buton.setAttribute('aria-label', raportat ? 'Retrage raportul' : 'Raportează comentariul');

        if (vorba) vorba.textContent = raportat ? 'Raportat' : 'Raportează';

        toast(c.mesaj || (raportat ? 'Comentariul a fost raportat.' : 'Ai retras raportul.'));
      });
    }

    /* ------------------ o singură ureche pentru tot --------------------- */

    /**
     * Ascultarea e pe panou, nu pe fiecare buton.
     *
     * Comentariile apar și dispar sub ochii omului; ascultătorii puși pe
     * butoane la încărcarea paginii n-ar fi știut nimic despre cele venite
     * după. Așa, orice buton nou funcționează din clipa în care intră în
     * pagină, fără să i se lege nimic de mână.
     */
    panouComentarii.addEventListener('click', function (e) {
      var buton = e.target.closest('button');
      if (!buton || !panouComentarii.contains(buton)) return;

      /* --- „Vezi mai multe comentarii" --- */
      if (buton.hasAttribute('data-mai-multe-buton')) {
        vizibile += deodata;
        potrivesteAscunsul();
        return;
      }

      var articol = buton.closest('.comment__body');
      if (!articol) return;

      /* --- apreciere --- */
      if (buton.hasAttribute('data-like')) {
        // Fără cont nu se apreciază. Butonul nu se ascunde de vizitator —
        // numărul de pe el e o veste bună pentru discuție — dar apăsarea lui
        // duce la intrare, cu întoarcere fix aici.
        if (!isLoggedIn()) {
          toast('Intră în cont ca să apreciezi un comentariu.');
          setTimeout(goToLogin, 900);
          return;
        }

        apreciaza(buton);
        return;
      }

      /* --- raport --- */
      if (buton.hasAttribute('data-raport')) {
        raporteaza(buton);
        return;
      }

      /* --- răspuns --- */
      if (buton.hasAttribute('data-reply')) {
        if (!isLoggedIn()) {
          toast('Intră în cont ca să răspunzi.');
          setTimeout(goToLogin, 900);
          return;
        }

        deschideRaspunsul(articol, buton);
        return;
      }

      /* --- corectură --- */
      if (buton.hasAttribute('data-edit')) {
        deschideEditarea(articol);
        return;
      }

      /* --- ștergere --- */
      if (buton.hasAttribute('data-delete')) {
        stergeComentariul(articol);
      }
    });

    // Prima așezare: tot ce trece de primul teanc se dă la o parte.
    potrivesteAscunsul();

    /* ------------- comentariul cerut din adresă („#c123") -------------
       Linkul din e-mailul de înștiințare duce fix la comentariul despre care
       e vorba. De obicei browserul se descurcă singur — ținta e acolo, iar
       `scroll-margin-top` de pe `.comment__body` îl oprește sub antet.

       Nu și când comentariul a rămas dincolo de primul teanc: atunci e
       `hidden`, iar browserul n-are la ce sări. Omul primea un mesaj care
       spunea „ți-a răspuns cineva", apăsa, și ajungea în capul paginii, cu
       răspunsul închis sub un buton pe care nu știa că trebuie să-l apese.

       Se dau la o parte destule teancuri cât să iasă la iveală, apoi se sare
       la el. Nu se arată toate deodată: la o discuție de o sută de rânduri,
       cel căutat ar fi rămas tot îngropat, doar că într-o pagină mai lungă. */
    function aratăComentariulDinAdresa() {
      var potrivire = (window.location.hash || '').match(/^#c(\d+)$/);
      if (!potrivire) return;

      var articol = document.getElementById('c' + potrivire[1]);
      if (!articol) return;

      var li = articol.closest('.comment');
      if (!li) return;

      /* Câte teancuri trebuie desfăcute ca să ajungem la el. `indexOf` pe
         lista în ordinea de pe ecran — aceeași ordine după care se ascunde. */
      var loc = toateComentariile().indexOf(li);

      if (loc >= 0 && loc >= vizibile) {
        vizibile = Math.ceil((loc + 1) / deodata) * deodata;
        potrivesteAscunsul();
      }

      /* Sărit din nou, chiar dacă era vizibil de la început: browserul a
         încercat o dată, înainte ca CSS-ul și pozele să așeze pagina, și de
         multe ori s-a oprit în alt loc. `block: 'start'` lasă
         `scroll-margin-top` să hotărască unde. */
      if (articol.scrollIntoView) {
        articol.scrollIntoView({ block: 'start' });
      }
    }

    aratăComentariulDinAdresa();

    /* Și când diezul se schimbă fără reîncărcare — omul e deja pe pagină și
       apasă un al doilea link din același e-mail, sau se întoarce cu săgeata
       browserului. Fără rândul ăsta, a doua oară n-ar mai desface nimic. */
    window.addEventListener('hashchange', aratăComentariulDinAdresa);
  }

  /* -------------------------- PANOURILE CU OAMENI ------------------------
     Taburile „Interesați" și „Participă". Același cod pentru amândouă: sunt
     aceeași listă, cu altă valoare în `data-stare`.

     Toți intră în pagină de la început; „Vezi mai mult" nu aduce nimic, doar
     dă la o parte — ca la comentarii.

     Numai panoul de participanți are butoane de scoatere, iar ele se leagă
     doar dacă panoul poartă șablonul casetei de confirmare. Interesații n-au
     ce curăța: „Mă interesează" nu ocupă niciun loc.
  ------------------------------------------------------------------------ */

  /**
   * Caseta de confirmare de sub un om.
   *
   * Două fapte o folosesc, și amândouă sunt de același fel: ceva ce
   * organizatorul face ALTUIA și nu se mai poate lua înapoi — scoaterea de pe
   * listă și „Nu s-a prezentat". Una cere motiv, cealaltă nu; în rest, aceeași
   * casetă, în același loc.
   *
   * SUB RÂNDUL OMULUI, nu la capătul listei și nu într-o fereastră peste
   * pagină: cine confirmă trebuie să vadă pe cine, fără să caute cu ochii în
   * sus. Una singură deodată, în tot panoul.
   *
   * Șablonul vine din pagină (`<template>`), ca HTML-ul să fie scris tot în
   * PHP, ca peste tot pe site. Întoarce caseta clonată, sau null dacă apăsarea
   * a fost pe același buton — adică „închide-o".
   */
  function deschideCaseta(panou, buton, sablon, selectorNume) {
    var deschisa = panou.querySelector('[data-caseta]');

    // A doua apăsare pe același buton închide caseta.
    if (deschisa && deschisa.__buton === buton) { inchideCaseta(panou); return null; }

    inchideCaseta(panou);

    var rand = buton.closest('.person');
    if (!rand) return null;

    var caseta = sablon.content.firstElementChild.cloneNode(true);
    caseta.__buton = buton;

    // Numele se pune ca TEXT, nu lipit în HTML: e numele altui om.
    var locNume = selectorNume ? caseta.querySelector(selectorNume) : null;
    if (locNume) locNume.textContent = buton.getAttribute('data-nume') || '';

    rand.after(caseta);
    buton.setAttribute('aria-expanded', 'true');

    var renunta = caseta.querySelector('[data-renunta]');
    if (renunta) {
      renunta.addEventListener('click', function () { inchideCaseta(panou); });
    }

    return caseta;
  }

  function inchideCaseta(panou) {
    var deschisa = panou.querySelector('[data-caseta]');
    if (!deschisa) return;

    if (deschisa.__buton) deschisa.__buton.setAttribute('aria-expanded', 'false');
    deschisa.remove();
  }

  document.querySelectorAll('[data-oameni]').forEach(function (panou) {
    var lista        = panou.querySelector('[data-lista-oameni]');
    var maiMulti     = panou.querySelector('[data-mai-multi]');
    var maiMultiButon = panou.querySelector('[data-mai-multi-buton]');
    var sablonScoatere = panou.querySelector('#sablon-scoatere');
    var intro        = panou.querySelector('.panel__intro');
    var stare        = panou.getAttribute('data-stare') || '';

    var pePagina = parseInt(panou.getAttribute('data-deodata'), 10) || 10;
    var aratati  = pePagina;

    /* --------------------------- ascunsul ------------------------------ */

    function toti() {
      return lista ? Array.prototype.slice.call(lista.querySelectorAll('.person')) : [];
    }

    function potriveste() {
      var oameni = toti();
      var ascunsi = 0;

      if (aratati < pePagina) aratati = pePagina;

      oameni.forEach(function (li, i) {
        var deAscuns = i >= aratati;
        li.hidden = deAscuns;
        if (deAscuns) ascunsi++;
      });

      if (maiMulti) {
        maiMulti.hidden = ascunsi === 0;

        // Doar numărul în paranteză: „persoane" e deja în rândul de deasupra
        // listei, iar butonul spune ce face, nu ce numără.
        if (maiMultiButon && ascunsi > 0) {
          maiMultiButon.textContent = 'Vezi mai mult (încă ' + ascunsi + ')';
        }
      }
    }

    /* --------------------- împrospătarea din afară --------------------- */

    /**
     * Lista și rândul de deasupra, puse la loc cu ce a trimis serverul.
     *
     * Se agață de elementul panoului, ca oricine altcineva din fișier să-l
     * poată împrospăta fără să știe nimic despre închiderea asta: butoanele
     * „Mă interesează" / „Voi participa" o cheamă după fiecare apăsare, iar
     * scoaterea unui participant, după fiecare scoatere.
     *
     * Totul vine gata desenat de pe server, din aceleași funcții care scriu
     * pagina la încărcare (randeazaListaOameni, vorbaDespreCatiSunt). De aceea
     * se pune cu innerHTML: e HTML făcut de noi, escapat cu h(), nu text venit
     * de la cine a apăsat. Nimic nu se socotește aici — între încărcarea
     * paginii și apăsare pot intra sau ieși alții.
     */
    panou.__aplica = function (date) {
      if (!date) return;

      if (lista && typeof date.lista === 'string') {
        lista.innerHTML = date.lista;
      }

      if (intro && typeof date.intro === 'string') {
        intro.innerHTML = date.intro;
        // Fără nimeni pe listă, rândul e o invitație, nu o numărătoare: se
        // așază pe mijloc, ca „Niciun comentariu încă" din tabul de alături.
        intro.classList.toggle('panel__intro--gol', !!date.gol);
      }

      // Cine tocmai a intrat pe listă trebuie să se vadă, chiar dacă e al
      // unsprezecelea și teancul arătat era de zece.
      if (aratati < pePagina) aratati = pePagina;

      potriveste();
    };

    /* ------------------------------------------------------------------ *
     *  De aici încolo, doar panoul cu butoane de scoatere.
     * ------------------------------------------------------------------ */

    if (sablonScoatere) {
      var deschideScoaterea = function (buton) {
        var rand      = buton.closest('.person');
        var randCaseta = deschideCaseta(panou, buton, sablonScoatere, '[data-scoate-nume]');

        if (!randCaseta) return;

        var form   = randCaseta.querySelector('form');
        var motiv  = form.querySelector('textarea');
        var eroare = form.querySelector('#err-scoate');
        var bifa   = form.querySelector('[data-scoate-interzis]');

        motiv.focus();

        motiv.addEventListener('input', function () {
          eroare.hidden = true;
          eroare.textContent = '';
        });

        form.addEventListener('submit', function (e) {
          e.preventDefault();

          var text = motiv.value.trim();

          /**
           * Verificarea de aici e pentru confortul omului, nu o regulă:
           * aceeași limită e ținută de verificaMotivExcludere() pe server,
           * unde chiar contează. Numărăm cu [...text].length, nu cu .length —
           * în UTF-16 un „ă" e un caracter, dar un emoji e două, iar numărul
           * de aici trebuie să fie fix cel pe care îl socotește mb_strlen().
           */
          if ([...text].length < 15) {
            eroare.textContent = 'Scrie cel puțin 15 caractere. Omul primește textul ăsta pe e-mail.';
            eroare.hidden = false;
            motiv.focus();
            return;
          }

          trimiteScoaterea(
            rand.getAttribute('data-participant'),
            text,
            bifa.checked,
            form.querySelector('button[type="submit"]'),
            eroare
          );
        });
      };

      var trimiteScoaterea = function (id, motiv, interzis, buton, eroare) {
        var textInitial = buton.textContent;

        buton.disabled = true;
        buton.textContent = 'Se trimite…';

        function gata() {
          buton.disabled = false;
          buton.textContent = textInitial;
        }

        fetch('api/exclude-participant.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            csrf:     panou.getAttribute('data-csrf') || '',
            slug:     panou.getAttribute('data-slug') || '',
            membru:   id,
            motiv:    motiv,
            interzis: interzis
          })
        })
        .then(citesteRaspuns)
        .then(function (rez) {
          gata();

          if (!rez.corp) { toast(mesajRaspunsNeasteptat(rez)); return; }
          var c = rez.corp;

          if (rez.stare === 401) {
            toast('Intră în cont ca să faci asta.');
            setTimeout(goToLogin, 900);
            return;
          }

          if (c.erori) {
            eroare.textContent = c.erori.motiv || 'Verifică ce ai scris.';
            eroare.hidden = false;
            return;
          }

          if (!c.ok) { toast(c.mesaj || 'Nu am putut scoate omul de pe listă.'); return; }

          inchideCaseta(panou);

          // Amândouă panourile, numerele și chipurile — aceeași cale ca după
          // o apăsare pe „Mă interesează" sau „Voi participa".
          aplicaPanouriOameni(c.panouri);
          setChipuri(c.chipuri);

          if (c.numar) {
            setRsvpCount('participant', c.numar.participant);
            setRsvpCount('interesat', c.numar.interesat);
          }

          // Vorba vine de la server, unde se știe dacă e ea sau el. Aici, dacă
          // lipsește, rămâne una fără gen: mai bine seacă decât greșită.
          toast(c.mesaj || 'Am scos omul de pe listă.');
        })
        .catch(function () {
          gata();
          toast(mesajFaraLegatura());
        });
      };
    }

    /* ------------------ o singură ureche pentru tot -------------------- */

    /**
     * Ascultarea e pe panou, nu pe fiecare buton: lista se redesenează
     * întreagă după fiecare scoatere, iar ascultătorii legați de butoanele
     * vechi ar fi plecat odată cu ele.
     */
    panou.addEventListener('click', function (e) {
      var buton = e.target.closest('button');
      if (!buton || !panou.contains(buton)) return;

      if (buton.hasAttribute('data-mai-multi-buton')) {
        aratati += pePagina;
        potriveste();
        return;
      }

      if (buton.hasAttribute('data-scoate') && sablonScoatere) {
        deschideScoaterea(buton);
      }
    });

    // Prima așezare: tot ce trece de primul teanc se dă la o parte.
    potriveste();
  });

  /* ----------------------------- NOTELE ---------------------------------
     Stelele din dreptul fiecărui participant, la un eveniment încheiat, și
     formularul de evaluare de pe profil.

     Notele sunt anonime: nimic de aici nu spune cine le-a dat. Serverul ține
     minte, ca să nu se poată nota de zece ori, dar nu scoate niciodată afară.
  ------------------------------------------------------------------------ */

  /**
   * Trimite o notă și cheamă `laReusit` cu răspunsul.
   *
   * Aceeași cale pentru toate trei faptele — stelele de pe eveniment, textul de
   * pe profil, „Nu s-a prezentat" — fiindcă toate merg la același API și toate
   * pot pica la fel: sesiune expirată, eveniment neîncheiat, om care n-a fost
   * pe listă.
   */
  function trimiteNota(trup, buton, campEroare, laReusit) {
    var doarText    = !!buton && buton.children.length === 0;
    var textInitial = doarText ? buton.textContent : '';

    if (buton) { buton.disabled = true; }
    if (doarText) { buton.textContent = 'Se trimite…'; }

    function gata() {
      if (buton) { buton.disabled = false; }
      if (doarText) { buton.textContent = textInitial; }
    }

    fetch('api/evaluare.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(trup)
    })
    .then(citesteRaspuns)
    .then(function (rez) {
      gata();

      if (!rez.corp) { toast(mesajRaspunsNeasteptat(rez)); return; }
      var c = rez.corp;

      if (rez.stare === 401) {
        toast('Intră în cont ca să dai o notă.');
        setTimeout(goToLogin, 900);
        return;
      }

      if (c.erori && campEroare) {
        campEroare.textContent = c.erori.text || 'Verifică ce ai scris.';
        campEroare.hidden = false;
        return;
      }

      if (!c.ok) { toast(c.mesaj || 'Nu am putut trimite nota.'); return; }

      if (campEroare) { campEroare.hidden = true; campEroare.textContent = ''; }
      laReusit(c);
    })
    .catch(function () {
      gata();
      toast(mesajFaraLegatura());
    });
  }

  /* ------------- stelele din dreptul fiecărui participant ------------- */

  document.querySelectorAll('[data-stele-participant]').forEach(function (cutie) {
    var picker = cutie.querySelector('[data-stele-input]');
    if (!picker) return;

    var panou = cutie.closest('[data-oameni]');
    if (!panou) return;

    var id        = cutie.getAttribute('data-stele-participant');
    var permalink = cutie.getAttribute('data-permalink') || '';

    /**
     * Selectorul de stele e același cu cel din formularul de pe profil —
     * `steleInteractive()`, scris o dată mai jos. Aici e doar mai mic și fără
     * text lângă el: în dreptul unui nume nu încape „4 din 5 — Bun".
     */
    steleInteractive(picker, null, function (valoare) {
      trimiteNota({
        csrf:   panou.getAttribute('data-csrf') || '',
        slug:   panou.getAttribute('data-slug') || '',
        fapta:  'noteaza',
        membru: id,
        stele:  valoare
      }, null, null, function (c) {
        cutie.setAttribute('data-nota', String(c.stele));
        aratInvitatia(cutie, permalink, c.stele);
        toast(c.mesaj || 'Nota ta a fost trimisă.');
      });
    });

    // Nota dată data trecută: invitația la scris rămâne la vedere, ca omul să
    // poată adăuga vorbe și mai târziu, nu doar în clipa apăsării.
    var nota = parseInt(cutie.getAttribute('data-nota'), 10) || 0;
    if (nota > 0) aratInvitatia(cutie, permalink, nota);
  });

  /**
   * „Lasă și câteva cuvinte" — invitația de sub stele, după ce s-a dat o notă.
   *
   * Duce pe profilul omului, drept la formularul de evaluare, cu nota deja
   * aleasă. Nu deschide o casetă aici: textul se scrie pe profilul cuiva, unde
   * stă lângă celelalte păreri despre el, nu într-un rând de listă.
   *
   * Se deschide în filă nouă: cine tocmai a notat trei oameni nu vrea să
   * piardă lista de sub degete pentru al patrulea.
   */
  function aratInvitatia(cutie, permalink, stele) {
    if (!permalink) return;

    var link = cutie.querySelector('[data-scrie-parere]');

    if (!link) {
      link = document.createElement('a');
      link.className = 'person__parere';
      link.setAttribute('data-scrie-parere', '');
      link.target = '_blank';
      link.rel = 'noopener';
      link.textContent = 'Lasă și câteva cuvinte';
      cutie.appendChild(link);
    }

    var panou = cutie.closest('[data-oameni]');
    var slug  = panou ? (panou.getAttribute('data-slug') || '') : '';

    link.href = 'profil.php?m=' + encodeURIComponent(permalink)
              + '&ev=' + encodeURIComponent(slug)
              + '&stele=' + encodeURIComponent(stele)
              + '#review-form';
  }

  /* --------------------- „Nu s-a prezentat" --------------------------
     Se confirmă ÎN PAGINĂ, într-o casetă de sub omul pe care s-a apăsat —
     aceeași casetă ca la scoaterea de pe listă, prin aceeași deschideCaseta().

     A fost o vreme un `confirm()` din browser. E cel mai ușor de scris și cel
     mai prost lucru de arătat: o fereastră care sare peste toată pagina, cu
     alte litere și alte butoane decât tot restul site-ului, iar pe telefon
     lipită de bara de adrese. Iar fapta e destul de grea (o stea și o notă pe
     profilul altui om, definitiv) ca omul să merite s-o confirme uitându-se la
     rândul lui, nu la o casetă gri de sistem.

     Fără motiv, spre deosebire de scoatere: acolo textul pleacă în e-mailul
     omului. Aici nu se trimite nimănui nimic de citit.
  ------------------------------------------------------------------------ */

  document.querySelectorAll('[data-oameni]').forEach(function (panou) {
    var sablonAbsent = panou.querySelector('#sablon-absent');
    if (!sablonAbsent) return;

    /**
     * Ascultarea e pe panou, nu pe fiecare buton, ca la scoatere: butoanele
     * se schimbă sub noi de fiecare dată când lista se redesenează.
     */
    panou.addEventListener('click', function (e) {
      var buton = e.target.closest('[data-absent]');
      if (!buton || !panou.contains(buton)) return;

      var caseta = deschideCaseta(panou, buton, sablonAbsent, '[data-absent-nume]');
      if (!caseta) return;

      var form = caseta.querySelector('form');

      form.addEventListener('submit', function (ev) {
        ev.preventDefault();

        trimiteNota({
          csrf:   panou.getAttribute('data-csrf') || '',
          slug:   panou.getAttribute('data-slug') || '',
          fapta:  'absent',
          membru: buton.getAttribute('data-absent')
        }, form.querySelector('button[type="submit"]'), null, function () {
          /**
           * În locul stelelor și al butonului rămâne un singur cuvânt.
           *
           * Nu se sting, pleacă. Cine n-a venit nu se mai notează de nimeni —
           * și mai ales nu de cel care tocmai a pus însemnarea: cu stelele
           * rămase aprinse, ar fi putut alege peste o săptămână cinci și ar fi
           * șters cu ele exact ce a scris. Aceeași regulă e ținută de
           * api/evaluare.php, prin esteNeprezentat(), și de randeazaOm() la
           * reîncărcare.
           */
          var rand  = buton.closest('.person');
          var cutie = rand ? rand.querySelector('.person__note') : null;

          if (cutie) {
            cutie.innerHTML = '<span class="person__neprezentat"'
              + ' title="Nu s-a prezentat la eveniment">'
              + '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">'
              + '<circle cx="12" cy="12" r="9"/><path d="M8.5 8.5 15.5 15.5"/>'
              + '</svg><span>Neprezentat</span></span>';
          }

          inchideCaseta(panou);
          toast('Am însemnat că nu s-a prezentat. A primit o stea și o notă pe profil.');
        });
      });
    });
  });

  /* ------------------ „Vezi mai mult", peste tot ---------------------
     O listă lungă intră toată în pagină și se descoperă din câte-n câte.

     A fost scrisă întâi pentru evaluările de pe profil. Când a venit și
     istoricul, tot acolo, ar fi ajuns a doua copie a aceluiași lucru pe
     aceeași pagină — deci s-a mutat aici, fără nimic al ei: orice
     `[data-descopera]` cu o listă înăuntru merge, oricare i-ar fi rândurile.

     Ce se numără sunt COPIII listei, nu ceva după clasă. Așa merge și peste
     `<li class="evaluare">`, și peste `<article class="card">`, fără să știe
     nimic despre niciunul.

     Butonul îl scrie tot ea, cu câte au mai rămas: numărul nu se poate pune
     din PHP, fiindcă se schimbă la fiecare apăsare. De aceea rândul lui intră
     `hidden` din pagină — altfel s-ar vedea o clipă scris „Vezi mai mult",
     fără număr, și s-ar corecta singur sub ochii omului. */

  document.querySelectorAll('[data-descopera]').forEach(function (panou) {
    var lista    = panou.querySelector('[data-descopera-lista]');
    var cutie    = panou.querySelector('[data-descopera-mai-mult]');
    var buton    = panou.querySelector('[data-descopera-buton]');
    var pePagina = parseInt(panou.getAttribute('data-deodata'), 10) || 10;
    var aratate  = pePagina;

    if (!lista) return;

    function potriveste() {
      var toate   = Array.prototype.slice.call(lista.children);
      var ascunse = 0;

      toate.forEach(function (rand, i) {
        var deAscuns = i >= aratate;
        rand.hidden = deAscuns;
        if (deAscuns) ascunse++;
      });

      if (cutie) {
        cutie.hidden = ascunse === 0;
        if (buton && ascunse > 0) {
          buton.textContent = 'Vezi mai mult (încă ' + ascunse + ')';
        }
      }
    }

    if (buton) {
      buton.addEventListener('click', function () {
        aratate += pePagina;
        potriveste();
      });
    }

    /**
     * Lăsată la vedere pentru cine schimbă lista din afară.
     *
     * Formularul de evaluare de mai jos înlocuiește toată lista după ce
     * trimite o părere; fără rândul ăsta, rândurile noi ar rămâne toate
     * descoperite, iar butonul ar arăta un număr de dinainte. Câte se văd NU
     * se dă înapoi la prima pagină: cine tocmai a apăsat de trei ori „Vezi mai
     * mult" n-are de ce să se trezească iar la început.
     */
    panou.__descopera = potriveste;

    potriveste();
  });

  /* ------------- formularul de evaluare de pe profil ------------------ */

  var formEvaluare = document.querySelector('[data-evaluare-form]');

  if (formEvaluare) {
    var stelePicker = formEvaluare.querySelector('[data-stars-input]');
    var textEvaluare = formEvaluare.querySelector('#review-text');
    var eroareEvaluare = formEvaluare.querySelector('#err-evaluare');

    if (textEvaluare && eroareEvaluare) {
      textEvaluare.addEventListener('input', function () {
        eroareEvaluare.hidden = true;
        eroareEvaluare.textContent = '';
      });
    }

    formEvaluare.addEventListener('submit', function (e) {
      e.preventDefault();

      var stele = stelePicker && stelePicker.getChosen ? stelePicker.getChosen() : 0;

      if (!stele) {
        eroareEvaluare.textContent = 'Alege câte stele îi dai, de la 1 la 5.';
        eroareEvaluare.hidden = false;
        return;
      }

      trimiteNota({
        csrf:   formEvaluare.getAttribute('data-csrf') || '',
        slug:   formEvaluare.getAttribute('data-slug') || '',
        fapta:  'scrie',
        membru: formEvaluare.getAttribute('data-membru'),
        stele:  stele,
        text:   textEvaluare ? textEvaluare.value : ''
      }, formEvaluare.querySelector('button[type="submit"]'), eroareEvaluare, function (c) {
        /**
         * Rezumatul și lista vin gata desenate de pe server, din aceleași
         * funcții care scriu pagina la încărcare. Media e o împărțire peste
         * toate notele omului — nu ceva ce se poate ajusta cu un plus aici.
         */
        var rezumat = document.querySelector('[data-rezumat-evaluari]');
        if (rezumat && typeof c.rezumat === 'string') {
          rezumat.innerHTML = c.rezumat;
          deseneazaStele(rezumat);
        }

        var lista = document.querySelector('[data-lista-evaluari]');
        if (lista && typeof c.evaluari === 'string') {
          lista.innerHTML = c.evaluari;
          deseneazaStele(lista);

          // Rândurile sunt noi, deci ascunsul se face din nou. Funcția stă pe
          // panoul din jur, pusă acolo de componenta „Vezi mai mult".
          var panou = lista.closest('[data-descopera]');
          if (panou && panou.__descopera) panou.__descopera();
        }

        toast(c.mesaj || 'Evaluarea ta a fost trimisă.');
      });
    });
  }

  /* ----------------------- POZA MĂRITĂ ------------------------------
     Apeși pe poza de profil, se deschide cât încape pe ecran.

     Merge peste orice `[data-mareste="<adresa pozei>"]`, deci nu știe nimic
     despre profil: dacă mâine se apasă și pe coperta unui eveniment, tot asta
     o deschide.

     Caseta vine dintr-un `<template>` din pagină, ca la confirmările de pe
     pagina evenimentului: HTML-ul se scrie în PHP, aici se clonează. Una
     singură deodată — a doua apăsare n-are cum să vină, fiindcă prima
     acoperă tot ecranul.
  ------------------------------------------------------------------------ */

  var sablonLupa = document.getElementById('sablon-lupa');

  if (sablonLupa) {
    var lupa = null;

    function inchideLupa() {
      if (!lupa) return;

      var deschizator = lupa.__deschizator;

      lupa.remove();
      lupa = null;
      document.documentElement.classList.remove('cu-lupa');

      // Atenția se întoarce de unde a plecat. Fără rândul ăsta, cine merge cu
      // tastatura rămâne cu atenția pe un element care tocmai s-a evaporat,
      // adică nicăieri, și ar lua pagina de la capăt.
      if (deschizator) deschizator.focus();
    }

    function deschideLupa(buton) {
      var adresa = buton.getAttribute('data-mareste');
      if (!adresa) return;

      inchideLupa();

      lupa = sablonLupa.content.firstElementChild.cloneNode(true);
      lupa.__deschizator = buton;
      lupa.querySelector('.lupa__poza').src = adresa;

      document.body.appendChild(lupa);

      // Pagina de dedesubt nu se mai plimbă cât timp poza e peste ea: altfel,
      // o rotiță de mouse ar muta ce nu se vede oricum.
      document.documentElement.classList.add('cu-lupa');

      lupa.querySelector('.lupa__inchide').focus();

      // Oriunde în afara pozei închide. Poza însăși nu: cine apasă pe ea vrea
      // s-o vadă, nu s-o piardă.
      lupa.addEventListener('click', function (e) {
        if (!e.target.closest('.lupa__poza')) inchideLupa();
      });
    }

    document.addEventListener('click', function (e) {
      var buton = e.target.closest('[data-mareste]');
      if (buton) deschideLupa(buton);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && lupa) inchideLupa();
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


  /* ------------------ 7. AUTENTIFICARE / ÎNREGISTRARE -------------------
     Blocul ăsta atârna, tot, de existența TABURILOR. Când pagina de intrare a
     rămas fără ele — cât e site-ul în lucru se arată doar autentificarea —
     nimic dinăuntru nu s-a mai legat: nici formularul de login.

     Iar un formular fără JavaScript legat de el nu stă degeaba: browserul îl
     trimite el, cum știe. Cum n-are `method`, îl trimitea prin GET, adică
     PAROLA AJUNGEA ÎN BARA DE ADRESE — și de acolo în istoric, în loguri și în
     Referer. Pe ecran nu se întâmpla „nimic", fiindcă pagina se reîncărca la
     fel.

     De aceea acum se leagă dacă e ORICARE dintre ele: taburile sau formularul
     de intrare. Fiecare folosire a taburilor de mai jos și-a primit paza ei. */
  var authTabs  = document.getElementById('auth-tabs');
  var loginFormPrezent = document.getElementById('login-form');

  if (authTabs || loginFormPrezent) {

    /* --- Deschide tabul corect din URL sau din butoanele de sub formular --- */
    // login.php#inregistrare sau ?tab=inregistrare deschide direct înregistrarea.
    var params = new URLSearchParams(window.location.search);

    function tabFromUrl() {
      if (window.location.hash === '#inregistrare' || params.get('tab') === 'inregistrare') return 'tab-register';
      if (window.location.hash === '#autentificare') return 'tab-login';
      return null;
    }

    var wanted = tabFromUrl();
    if (authTabs && wanted) authTabs.selectTabById(wanted);

    // Linkul din meniu duce tot pe pagina asta, deci nu se reîncarcă nimic:
    // ascultăm schimbarea hash-ului ca să comutăm totuși tabul.
    window.addEventListener('hashchange', function () {
      var next = tabFromUrl();
      if (authTabs && next) authTabs.selectTabById(next);
    });

    document.querySelectorAll('[data-go-tab]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!authTabs) return;
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

    /* --- Data nașterii: același câmp ca data evenimentului --- */
    legaCampData('rg-birthdate');

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
    /* Data nașterii: același câmp ca la înregistrarea obișnuită. */
    legaCampData('fn-birthdate');

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

  /**
   * Conturul stelei stă ÎN funcție, nu într-o variabilă de deasupra.
   *
   * A stat o vreme afară, ca `var STAR_PATH`, și a mers cât timp toate stelele
   * se desenau din secțiunea asta. Când desenatul a ajuns să fie chemat și mai
   * sus în fișier — stelele din dreptul participanților, la un eveniment
   * încheiat — variabila era hoistată, deci nu dădea nicio eroare, dar încă
   * nedefinită: ieșeau `<path d="undefined">`, adică butoane goale, de mărimea
   * potrivită și cu totul invizibile.
   *
   * Aici nu se mai poate întâmpla: o funcție e gata din clipa în care fișierul
   * e citit, oriunde ar fi chemată din el.
   */
  function starSvg() {
    var contur = 'M12 2.6l2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 17.4 6.2 20.5l1.1-6.5L2.6 9.4l6.5-.9L12 2.6z';

    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' + contur +
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

  /**
   * Desenează stelele de afișare dintr-o bucată de pagină.
   *
   * `<div data-stars="4.6" data-stars-count="23">` → cinci stele goale peste
   * care se suprapun cinci pline, tăiate la procentul notei.
   *
   * Primește o rădăcină, ca să poată fi chemată din nou peste ce se aduce de
   * pe server: lista de evaluări de pe profil se redesenează după fiecare notă
   * trimisă, iar stelele din ea trebuie desenate încă o dată.
   */
  function deseneazaStele(radacina) {
    (radacina || document).querySelectorAll('[data-stars]').forEach(function (el) {
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
  }

  deseneazaStele(document);

  /**
   * Selectorul de stele: cinci butoane pe care se apasă.
   *
   * Scris o dată, folosit în două locuri foarte diferite — formularul mare de
   * pe profil (cu „4 din 5 — Bun" lângă el) și stelele mici din dreptul
   * fiecărui participant, la un eveniment încheiat. Ce le deosebește sunt
   * exact cele două lucruri primite ca argument: unde se scrie nota aleasă și
   * ce se întâmplă la apăsare.
   *
   * `data-chosen` de pe element e nota deja dată: stelele se aprind de la
   * început, ca omul să nu creadă că a pierdut-o.
   */
  function steleInteractive(el, output, laAlegere) {
    var names = ['', 'Foarte slab', 'Slab', 'Acceptabil', 'Bun', 'Foarte bun'];
    var chosen = parseInt(el.getAttribute('data-chosen'), 10) || 0;
    var buttons = [];

    el.setAttribute('role', 'radiogroup');
    el.setAttribute('aria-label', 'Alege o notă de la 1 la 5 stele');

    function paint(upTo) {
      buttons.forEach(function (b, i) { b.classList.toggle('is-on', i < upTo); });
    }

    function scrie(value) {
      if (output) {
        output.textContent = value > 0 ? value + ' din 5 — ' + names[value] : 'Nicio notă aleasă';
      }
    }

    for (var i = 1; i <= 5; i++) {
      (function (value) {
        var b = document.createElement('button');
        b.type = 'button';
        b.innerHTML = starSvg();
        b.setAttribute('role', 'radio');
        b.setAttribute('aria-checked', String(value === chosen));
        b.setAttribute('aria-label', value + (value === 1 ? ' stea' : ' stele'));

        b.addEventListener('mouseenter', function () { paint(value); });
        b.addEventListener('focus', function () { paint(value); });
        b.addEventListener('click', function () {
          el.setChosen(value);
          if (laAlegere) laAlegere(value);
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

    el.setChosen = function (value) {
      chosen = value;
      el.setAttribute('data-chosen', String(value));
      buttons.forEach(function (b, i) { b.setAttribute('aria-checked', String(i + 1 === value)); });
      paint(value);
      scrie(value);
    };

    el.reset = function () { el.setChosen(0); el.removeAttribute('data-chosen'); };

    paint(chosen);
    return el;
  }

  // Selectorul mare, din formularul de evaluare de pe profil. Cel mic, din
  // dreptul fiecărui participant, se leagă în blocul „NOTELE" — acolo apăsarea
  // trimite pe loc, fără buton de trimitere.
  document.querySelectorAll('[data-stars-input]').forEach(function (el) {
    steleInteractive(el, document.getElementById(el.getAttribute('aria-describedby') || 'review-chosen'));
  });

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

      var bifa    = document.getElementById('st-newsletter');
      var bifaCom = document.getElementById('st-comentarii');

      trimiteSetare(newsForm, newsForm.querySelector('button[type=submit]'),
        {
          sectiune: 'newsletter',
          newsletter: bifa && bifa.checked ? '1' : '',
          // Bifa a doua e citită la fel: netrimisă înseamnă „nu vreau".
          email_comentarii: bifaCom && bifaCom.checked ? '1' : ''
        },
        function (c) {
          if (!c.ok) { toast(c.mesaj || 'Nu am putut salva preferințele.'); return; }
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

  /*
    „Înapoi", de pe panoul „ai deja un eveniment în desfășurare". Omul a
    apăsat „+ Eveniment nou" de undeva — de pe profil, de pe prima pagină —
    și acolo vrea să se întoarcă, nu într-un loc ales de noi.

    Se cer două lucruri deodată: fila să aibă un „înainte de asta"
    (history.length > 1) și pagina dinainte să fie de la noi. A doua condiție
    e cea care contează: fără ea, cine ajunge aici dintr-un link de pe alt
    site ar fi trimis înapoi acolo, adică afară din PulsulOrasului. Când
    lipsește vreuna, linkul rămâne cum a venit din HTML și duce pe prima
    pagină.
  */
  var evInapoi = document.getElementById('ev-inapoi');

  if (evInapoi && window.history.length > 1 && dinaintePeSite()) {
    evInapoi.addEventListener('click', function (e) {
      e.preventDefault();
      window.history.back();
    });
  }

  /* ======================= ANULAREA UNUI EVENIMENT ===================
     Aceeași zonă, pe două pagini: formularul de editare și pagina
     evenimentului (sub caseta de interes). HTML-ul îl scrie
     randeazaZonaAnulare() din inc/afisare-eveniment.php, într-un singur loc;
     aici e purtarea lui, tot într-unul singur.

     Se caută după `data-anulare`, nu după formularul de eveniment: pe pagina
     evenimentului nu există niciun formular în jur, iar slugul și tokenul stau
     pe zona însăși.

     În două trepte, ca ștergerea contului din setări: butonul își schimbă
     locul cu întrebarea. Confirmarea e desenată de noi, nu de browser —
     window.confirm() arată altfel pe fiecare sistem.
  ==================================================================== */
  var zonaAnulare = document.querySelector('[data-anulare]');

  if (zonaAnulare) {
    var evAnuleaza  = document.getElementById('ev-anuleaza');
    var evSigur     = document.getElementById('ev-anulare-sigur');
    var evAnulareDa = document.getElementById('ev-anulare-da');
    var evAnulareNu = document.getElementById('ev-anulare-nu');

    if (evAnuleaza && evSigur) {
      evAnuleaza.addEventListener('click', function () {
        evAnuleaza.hidden = true;
        evSigur.hidden = false;
        if (evAnulareNu) evAnulareNu.focus();   // atenția pe ieșire, nu pe faptă
      });

      if (evAnulareNu) {
        evAnulareNu.addEventListener('click', function () {
          evSigur.hidden = true;
          evAnuleaza.hidden = false;
          evAnuleaza.focus();
        });
      }
    }

    /* --- motivul anulării: obligatoriu, numărat ca pe server --- */
    /*
      Aceeași numărătoare ca la descriere — vezi numaraCaractere() și oglinda
      lui curataTextPeRanduri(). Motivul ăsta pleacă prin e-mail spre oamenii
      care voiau să vină și rămâne apoi scris pe pagina evenimentului, deci
      contorul n-are voie să spună altceva decât acceptă serverul.
    */
    var evMotiv      = document.getElementById('ev-motiv');
    var evMotivNumar = document.getElementById('ev-motiv-numar');

    if (evMotiv && evMotivNumar) {
      var motivMin = parseInt(evMotiv.getAttribute('data-min'), 10) || 15;
      var motivMax = parseInt(evMotiv.getAttribute('data-max'), 10) || 1000;

      var numaraMotivul = function () {
        // Cât scrie omul, eroarea de dinainte nu mai are ce spune.
        setError('ev-motiv', 'err-ev-motiv', '');

        if (numaraCaractere(evMotiv.value) > motivMax) {
          evMotiv.value = taieLaCaractere(evMotiv.value, motivMax);
        }

        var cate = numaraCaractere(curataTextPeRanduri(evMotiv.value));
        evMotivNumar.textContent = cate + ' din minim ' + motivMin + ' caractere';
        evMotivNumar.classList.toggle('e-gata', cate >= motivMin);
      };

      evMotiv.addEventListener('input', numaraMotivul);
      numaraMotivul();
    }

    if (evAnulareDa) {
      evAnulareDa.addEventListener('click', function () {
        var slug = zonaAnulare.getAttribute('data-slug') || '';
        if (!slug) return;

        var textInitial = evAnulareDa.textContent;
        evAnulareDa.disabled = true;
        evAnulareDa.textContent = 'Se anulează…';

        function gata() {
          evAnulareDa.disabled = false;
          evAnulareDa.textContent = textInitial;
        }

        fetch('api/anuleaza-eveniment.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            csrf: zonaAnulare.getAttribute('data-csrf') || '',
            slug: slug,
            motiv: evMotiv ? evMotiv.value : ''
          })
        })
        .then(citesteRaspuns)
        .then(function (rez) {
          if (!rez.corp) { gata(); toast(mesajRaspunsNeasteptat(rez)); return; }
          var c = rez.corp;

          // Motivul lipsă sau prea scurt: eroarea stă sub casetă, ca la orice
          // alt câmp, nu într-un toast care se stinge singur.
          if (c.erori) {
            gata();
            setError('ev-motiv', 'err-ev-motiv', c.erori.motiv || 'Scrie de ce anulezi.');
            if (evMotiv) evMotiv.focus();
            return;
          }

          if (!c.ok) { gata(); toast(c.mesaj || 'Nu am putut anula evenimentul.'); return; }

          // Butonul rămâne stins: hotărârea e luată, n-are rost să se poată
          // apăsa încă o dată cât se face mutarea.
          toast(c.mesaj || 'Evenimentul a fost anulat.');
          setTimeout(function () {
            window.location.href = c.redirect || 'profil.php';
          }, 700);
        })
        .catch(function () {
          gata();
          toast(mesajFaraLegatura());
        });
      });
    }
  }

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

    /* --- data: ZZ-LL-AAAA, scrisă de mână sau aleasă din calendar --- */
    /*
      Masca, butonul și calendarul ascuns sunt aceleași pentru toate câmpurile
      de dată de pe site — vezi legaCampData(), sus. Aici rămâne doar chemarea.
    */
    legaCampData('ev-data');

    /* --- orele: scrise de mână, mereu de 24 de ore --- */
    /*
      Câmpurile sunt de text (vezi lămurirea din adauga_eveniment.php), deci
      două puncte le punem noi, cu mascaCifre() — aceeași funcție ca la dată.
      Cine scrie el două puncte („9:30") e ascultat: bucata de dinaintea lor
      capătă zeroul pe loc.

      Cine scrie doar cifre nu e corectat cât scrie — altfel „930" ar sări la
      „09:3" sub degetele lui. Îndreptarea vine la ieșirea din câmp:
      „9" → „09:00", „930" → „09:30", „1930" → „19:30".
    */
    ['ev-ora-inceput', 'ev-ora-sfarsit'].forEach(function (id) {
      var camp = document.getElementById(id);
      if (!camp) return;

      camp.addEventListener('input', function () {
        camp.value = mascaCifre(camp.value, [2, 2], ':');
      });

      camp.addEventListener('blur', function () {
        var cifre = camp.value.replace(/\D/g, '');
        if (cifre === '') { camp.value = ''; return; }

        // Una sau două cifre = ora fixă; trei = o cifră de oră și minutele.
        if (cifre.length <= 2) { cifre = ('0' + cifre).slice(-2) + '00'; }
        else if (cifre.length === 3) { cifre = '0' + cifre; }

        camp.value = cifre.slice(0, 2) + ':' + cifre.slice(2, 4);
      });
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
    /*
      Numerele vin din HTML, iar în HTML vin din constantele PHP: dacă
      DESCRIERE_MIN se schimbă vreodată, nu rămâne o copie uitată aici.

      Se numără textul curățat, nu cel brut — vezi numaraCaractere() și
      lămurirea de lângă ea. Așa contorul spune fix ce va spune serverul.
    */
    var evDescriere = document.getElementById('ev-descriere');
    var evNumar     = document.getElementById('ev-numar');

    if (evDescriere && evNumar) {
      var minCaractere = parseInt(evDescriere.getAttribute('data-min'), 10) || 300;
      var maxCaractere = parseInt(evDescriere.getAttribute('data-max'), 10) || 8000;

      var numara = function () {
        /**
         * Oprirea la limita de sus o facem noi, nu `maxlength`: acela numără
         * în unități UTF-16, deci ar fi tăiat textul cu emoji la jumătatea
         * limitei pe care o ține serverul. Se taie textul brut, fiindcă cel
         * curățat e mereu mai scurt — așa serverul primește sigur ceva ce-i
         * încape.
         */
        if (numaraCaractere(evDescriere.value) > maxCaractere) {
          var pozitie = evDescriere.selectionStart;
          evDescriere.value = taieLaCaractere(evDescriere.value, maxCaractere);
          try { evDescriere.setSelectionRange(pozitie, pozitie); } catch (e) {}
        }

        var cate = numaraCaractere(curataTextPeRanduri(evDescriere.value));
        // „din minim 300" — vezi lămurirea de lângă contor, în
        // adauga_eveniment.php. Textul de aici trebuie să spună exact ce
        // spune și cel tipărit de PHP, altfel se schimbă la prima tastă.
        evNumar.textContent = cate + ' din minim ' + minCaractere + ' caractere';
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

    /**
     * Poza de acum, la editare. Cât timp omul nu alege alta, ea rămâne — atât
     * pe ecran, cât și în bază: un formular trimis fără fișier înseamnă „n-am
     * umblat la poză". Când alege alta, blocul se ascunde, ca să nu stea două
     * poze una peste alta; când renunță, se întoarce.
     */
    var evCopertaAcum = document.getElementById('ev-coperta-acum');

    function arataPozaDeAcum(da) {
      if (evCopertaAcum) evCopertaAcum.hidden = !da;
    }

    function scoateCoperta() {
      if (evFisier) evFisier.value = '';
      if (evCrop) evCrop.hidden = true;
      if (evDecupator) evDecupator.uita();
      if (evAdresa) { URL.revokeObjectURL(evAdresa); evAdresa = null; }
      if (evNume) evNume.textContent = 'JPG, PNG sau WEBP, cel puțin 1600×900 px';
      arataPozaDeAcum(true);
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

        // Poza cea nouă o ia pe cea veche din loc.
        arataPozaDeAcum(false);

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

    /**
     * Erorile de la server, puse fiecare lângă câmpul ei.
     *
     * Scrisă o dată fiindcă o cer două butoane: cel de trimitere („Trimite
     * spre aprobare", sau „Publică evenimentul" la staff) și
     * „Previzualizează". Amândouă trec prin aceleași verificări pe server,
     * deci trebuie să arate la fel și când ceva nu e în regulă.
     */
    function arataErorile(erori) {
      var primul = null;

      [['titlu', 'ev-titlu'], ['categorie_id', 'ev-categorie'],
       ['oras', 'ev-oras'], ['locatie', 'ev-locatie'],
       ['data_eveniment', 'ev-data'], ['ora_inceput', 'ev-ora-inceput'],
       ['ora_sfarsit', 'ev-ora-sfarsit'], ['cost', 'ev-cost'],
       ['varsta_minima', 'ev-varsta'], ['gen_participanti', 'ev-gen'],
       ['participanti_min', 'ev-min'], ['participanti_max', 'ev-max'],
       ['descriere', 'ev-descriere'], ['coperta', 'ev-coperta']
      ].forEach(function (p) {
        var mesaj = erori[p[0]] || '';
        setError(p[1], 'err-' + p[1], mesaj);
        if (mesaj && !primul) primul = document.getElementById(p[1]);
      });

      if (primul) primul.focus();
      toast('Mai sunt câmpuri de corectat.');
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

        if (c.erori) { arataErorile(c.erori); return; }

        if (!c.ok) { toast(c.mesaj || 'Nu am putut trimite evenimentul.'); return; }

        document.getElementById('ev-block').hidden = true;
        var done = document.getElementById('ev-done');
        done.hidden = false;

        // La un eveniment nou, adresa paginii vine abia acum, din răspuns.
        var spreEveniment = document.getElementById('ev-done-link');
        if (spreEveniment && c.url) {
          spreEveniment.href = c.url;
          spreEveniment.hidden = false;
        }

        var panou = done.querySelector('.done');
        if (panou) panou.focus();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      })
      .catch(function () {
        gata();
        toast(mesajFaraLegatura());
      });
    });

    /* ------------------------ previzualizarea ------------------------- */
    /*
      Aceleași verificări ca la trimitere, pe server, cu aceeași funcție.
      Diferă doar ce se întâmplă după: una salvează, cealaltă doar desenează.

      Fișierul NU pleacă spre server. Coperta aleasă e deja în browser, așa că
      o desenăm pe o pânză exact cum ar tăia-o serverul — cu numerele din
      decupator — și o lăsăm în localStorage pentru fila care se deschide.
    */
    var evPreviz = document.getElementById('ev-previzualizeaza');

    function copertaCaImagine() {
      if (!evDecupator || !evFisier || !evFisier.files || !evFisier.files.length) return '';

      try {
        var t = evDecupator.decupaj();
        var panza = document.createElement('canvas');
        panza.width = COPERTA_L;
        panza.height = COPERTA_I;

        var ctx = panza.getContext('2d');
        // Alb dedesubt, ca la server: un PNG cu fundal transparent ar ieși negru.
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, COPERTA_L, COPERTA_I);
        ctx.drawImage(evCropImg, t.x, t.y, t.l, t.h, 0, 0, COPERTA_L, COPERTA_I);

        return panza.toDataURL('image/jpeg', 0.82);
      } catch (e) {
        // Dacă nu merge, previzualizarea se descurcă fără poză.
        return '';
      }
    }

    if (evPreviz) {
      evPreviz.addEventListener('click', function () {
        var textInitial = evPreviz.textContent;
        evPreviz.disabled = true;

        function gata() {
          evPreviz.disabled = false;
          evPreviz.textContent = textInitial;
        }

        // Fișierul nu pleacă: previzualizarea ia poza din browser. Dar
        // serverul trebuie să ȘTIE că vine una, altfel — la editare — ar
        // desena poza veche din bază și n-ar mai lăsa-o pe cea nouă la rând.
        var date = new FormData(evForm);
        var arePozaNoua = !!(evFisier && evFisier.files && evFisier.files.length);

        date.delete('coperta');
        date.append('coperta_noua', arePozaNoua ? '1' : '');

        fetch('api/previzualizare.php', {
          method: 'POST',
          credentials: 'same-origin',
          body: date
        })
        .then(citesteRaspuns)
        .then(function (rez) {
          gata();

          if (!rez.corp) { toast(mesajRaspunsNeasteptat(rez)); return; }
          var c = rez.corp;

          // Aceleași erori, în aceleași locuri. Fila nu se deschide deloc.
          if (c.erori) { arataErorile(c.erori); return; }
          if (!c.ok || !c.cheie) { toast(c.mesaj || 'Nu am putut pregăti previzualizarea.'); return; }

          var poza = copertaCaImagine();

          if (poza !== '') {
            try { localStorage.setItem('po-previzualizare-' + c.cheie, poza); } catch (e) {}
          }

          var fila = window.open('previzualizare.php?p=' + encodeURIComponent(c.cheie), '_blank');

          /**
           * Unele browsere nu lasă o filă să se deschidă dintr-un răspuns
           * primit mai târziu, chiar dacă totul a pornit de la o apăsare. Nu-l
           * lăsăm pe om să creadă că butonul e stricat: îi dăm un link pe care
           * să apese el, iar apăsarea aia e o mișcare a lui, deci trece.
           */
          if (!fila) {
            var link = document.getElementById('ev-previz-link');
            var rand = document.getElementById('ev-previz-rand');
            if (link && rand) {
              link.href = 'previzualizare.php?p=' + encodeURIComponent(c.cheie);
              rand.hidden = false;
            }
            toast('Deschide previzualizarea din linkul de sub buton.');
          }
        })
        .catch(function () {
          gata();
          toast(mesajFaraLegatura());
        });
      });
    }
  }


  /* ------------- 11.5. POZA DIN PREVIZUALIZARE (previzualizare.php) ------ */
  /*
    Coperta aleasă în formular n-a ajuns niciodată pe server — e doar în
    browser. Fila-mamă a lăsat-o în localStorage, tăiată deja cum ar tăia-o
    serverul; aici o punem la locul ei și ștergem urma.

    Dacă nu găsim nimic (fila deschisă a doua oară, altă fereastră, spațiu
    plin la scriere), figura se dă la o parte: mai bine fără poză decât cu
    silueta implicită, care n-are ce căuta pe un anunț.
  */
  var previzMain = document.querySelector('main[data-previzualizare]');
  var previzPoza = document.getElementById('prev-coperta');

  /**
   * Locul gol există DOAR când se așteaptă o poză din browser — serverul îl
   * face numai atunci. Dacă omul n-a ales alta, poza salvată e deja desenată
   * de pe server și nu trecem pe aici deloc.
   */
  if (previzMain && previzPoza) {
    var cheiaPreviz = previzMain.getAttribute('data-previzualizare');
    var pastrata = '';

    try { pastrata = localStorage.getItem('po-previzualizare-' + cheiaPreviz) || ''; } catch (e) {}

    if (pastrata !== '') {
      previzPoza.src = pastrata;
      try { localStorage.removeItem('po-previzualizare-' + cheiaPreviz); } catch (e) {}
    } else {
      /**
       * Se aștepta o poză și n-a ajuns.
       *
       * Se întâmplă când browserul nu lasă localStorage să fie scris sau citit
       * — navigare privată strânsă, extensii de confidențialitate — sau când
       * fila e deschisă a doua oară, după ce poza a fost deja luată.
       *
       * Nu ascundem nimic pe tăcute și nu oprim restul: titlul, detaliile și
       * descrierea se văd oricum. În locul pozei rămâne o vorbă limpede
       * despre ce s-a întâmplat.
       */
      var figura = previzPoza.closest('figure');

      if (figura) {
        figura.innerHTML = '';
        figura.className = 'post__figure coperta-lipsa';
        figura.textContent = 'Nu am putut încărca previzualizarea pozei — '
          + 'încearcă din nou sau verifică setările de confidențialitate ale browserului.';
      }
    }
  }

  /* --- ieșirea din previzualizare --- */
  /*
    Fila s-a deschis din formular cu window.open, deci window.close() are voie
    s-o închidă. Când n-are — fila redeschisă din istoric, adresa lipită de
    mână, browser care nu vrea — nu se află de dinainte și nu se prinde nicio
    eroare: apelul pur și simplu nu face nimic.

    De aceea nota apare imediat după apăsare. Dacă fila chiar s-a închis, n-o
    mai vede nimeni; dacă a rămas, omul află ce are de făcut.
  */
  var previzInchide = document.getElementById('previz-inchide');

  if (previzInchide) {
    previzInchide.addEventListener('click', function () {
      var nota = document.getElementById('previz-nota');
      window.close();
      if (nota) nota.hidden = false;
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

  /* ======================================================================
     PAGINA DE AȘTEPTARE — înscrierea la vești

     Formularul e unul adevărat, cu `method="post"`, iar constructie.php îl
     primește: fără JavaScript merge la fel, doar cu o reîncărcare. Aici se
     scurtează drumul — adresa pleacă pe lângă pagină și răspunsul apare pe
     loc, fără ca omul să piardă ce vedea.
     ====================================================================== */

  var formaVesti = document.querySelector('[data-newsletter]');

  if (formaVesti) {
    var vestiCamp    = formaVesti.querySelector('[name="email"]');
    var vestiButon   = formaVesti.querySelector('[data-newsletter-buton]');
    var vestiRaspuns = formaVesti.querySelector('[data-newsletter-raspuns]');
    var vestiPleaca  = false;

    function vestiSpune(text, eBun) {
      if (!vestiRaspuns) return;

      vestiRaspuns.textContent = text;
      vestiRaspuns.classList.toggle('constructie__raspuns--rau', !eBun);
      vestiRaspuns.hidden = !text;
    }

    formaVesti.addEventListener('submit', function (e) {
      e.preventDefault();

      if (vestiPleaca) return;

      var adresa = (vestiCamp.value || '').trim();

      // Verificarea din browser e doar confort: taie drumul până la server
      // pentru o casetă goală. Cea care hotărăște e verificaEmail() de pe
      // server — vezi inscrieLaVesti() din inc/constructie.php.
      if (!adresa) { vestiCamp.focus(); return; }

      vestiPleaca = true;
      if (vestiButon) { vestiButon.disabled = true; }

      fetch('api/newsletter.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          csrf:    (formaVesti.querySelector('[name="csrf"]') || {}).value || '',
          email:   adresa,
          website: (formaVesti.querySelector('[name="website"]') || {}).value || ''
        })
      })
      .then(citesteRaspuns)
      .then(function (rez) {
        vestiPleaca = false;
        if (vestiButon) { vestiButon.disabled = false; }

        if (!rez.corp) { vestiSpune(mesajRaspunsNeasteptat(rez), false); return; }
        var c = rez.corp;

        if (!c.ok) { vestiSpune(c.mesaj || 'Nu am putut salva adresa.', false); return; }

        // Reușita golește caseta: altfel omul se uită la adresa lui lângă un
        // „gata, te-am trecut" și nu știe dacă mai are ceva de apăsat.
        vestiCamp.value = '';
        vestiSpune(c.mesaj || 'Gata!', true);
      })
      .catch(function () {
        vestiPleaca = false;
        if (vestiButon) { vestiButon.disabled = false; }
        vestiSpune(mesajFaraLegatura(), false);
      });
    });
  }

})();
