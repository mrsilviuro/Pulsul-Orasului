<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — setările contului.
 *
 * Se ajunge aici din rotița din bara de meniu, care se vede doar cine e
 * conectat.
 *
 * Patru lucruri, despărțite vizual pentru că sunt de greutăți foarte
 * diferite: parola, telefonul, înștiințările pe e-mail și, jos de tot,
 * ștergerea contului.
 */

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/validare.php';   // PAROLA_MIN
require_once __DIR__ . '/inc/stergere.php';   // ZILE_RAGAZ_STERGERE

$membru = membruCurent();

if ($membru === null) {
    cereIntrare('/setari.php');
}

/**
 * Are sau nu parolă la noi?
 *
 * Conturile deschise cu Google au parola_hash NULL — nu e o lipsă, e chiar
 * felul lor de a fi. Lor nu avem ce parolă veche să le cerem.
 */
$q = db()->prepare('SELECT parola_hash, telefon, email_comentarii, email_feedback
                      FROM membri WHERE id = ? LIMIT 1');
$q->execute([(int) $membru['id']]);
$setari = $q->fetch() ?: [];

$areParola  = !empty($setari['parola_hash']);
$telefon    = (string) ($setari['telefon'] ?? '');
// Lipsa coloanei înseamnă „pornit": amândouă sunt pornite din start (vezi
// sql/021 și sql/027), iar un rând citit dintr-o bază mai veche nu trebuie să
// arate bifa stinsă cât timp serverul îi scrie oricum.
$emailComentarii = !isset($setari['email_comentarii']) || (int) $setari['email_comentarii'] === 1;
$emailFeedback   = !isset($setari['email_feedback'])   || (int) $setari['email_feedback']   === 1;

$titlu     = 'Setările contului — PulsulOrasului.Ro';
$descriere = 'Parola, telefonul și preferințele contului tău.';
$noindex   = true;
$pagina    = '';

// Token-ul se cere înaintea antetului: după ce pagina începe să se tipărească,
// sesiunea nu mai poate fi pornită.
$csrf = tokenCsrf();

require __DIR__ . '/inc/antet.php';
?>


<main id="main">
  <div class="wrap wrap--ingust">

    <nav class="crumbs" aria-label="Navigare">
      <a href="/index.php">Acasă</a>
      <span aria-hidden="true">/</span>
      <a href="/profil.php">Profilul tău</a>
      <span aria-hidden="true">/</span>
      <span class="crumbs__current">Setări</span>
    </nav>

    <h1 class="setari__titlu">Setări</h1>
    <p class="setari__lead">
      Ce ține de contul tău, nu de profilul public. Nimic de aici nu se vede pe
      pagina ta de membru.
    </p>

    <!-- ========================= 1. PAROLA ============================ -->
    <section class="card-set" aria-labelledby="t-parola">
      <div id="parola-block">
        <h2 class="card-set__titlu" id="t-parola">
          <?= $areParola ? 'Schimbă-ți parola' : 'Pune-ți o parolă' ?>
        </h2>

        <p class="card-set__lead">
          <?php if ($areParola): ?>
            Scrie parola de acum, apoi pe cea nouă, de două ori.
          <?php else: ?>
            Ai deschis contul cu Google, deci nu ai parolă la noi. Poți pune una
            acum, dacă vrei să intri și fără Google. Contul de Google rămâne
            legat, oricum.
          <?php endif; ?>
        </p>

        <!-- Aceleași id-uri ca pe parola-noua.php, deci aceeași bucată din
             main.js le duce pe amândouă. Lipsa lui #pn-veche e chiar semnul
             după care JS-ul știe să nu ceară parola veche. -->
        <form class="form" id="parola-form" novalidate data-dupa-temporara="false">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

          <?php if ($areParola): ?>
          <?php
            $campId     = 'pn-veche';
            $campNume   = 'parola_veche';
            $campText   = 'Parola de acum';
            $campAjutor = 'Parola ta de acum';
            require __DIR__ . '/inc/camp-parola.php';
          ?>
          <?php endif; ?>

          <?php
            $campId     = 'pn-noua';
            $campNume   = 'parola';
            $campText   = $areParola ? 'Parola nouă' : 'Parola';
            $campAjutor = 'Cel puțin ' . PAROLA_MIN . ' caractere';
            $campAuto   = 'new-password';
            $campMetru  = true;
            require __DIR__ . '/inc/camp-parola.php';
          ?>

          <?php
            $campId     = 'pn-noua2';
            $campNume   = 'parola_confirmare';
            $campText   = $areParola ? 'Repetă parola nouă' : 'Repetă parola';
            $campAjutor = 'Aceeași parolă';
            $campAuto   = 'new-password';
            require __DIR__ . '/inc/camp-parola.php';
          ?>

          <button class="btn btn--primary" type="submit">
            <?= $areParola ? 'Salvează parola' : 'Pune parola' ?>
          </button>
        </form>
      </div>

      <div class="done" id="parola-done" hidden>
        <span class="done__ico" aria-hidden="true">
          <svg class="ico" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
          </svg>
        </span>
        <h3 class="done__title">Gata</h3>
        <p class="done__text">
          Ți-am trimis și un e-mail de înștiințare. Dacă nu tu ai făcut
          schimbarea, scrie-ne imediat.
        </p>
        <div class="done__actions">
          <a class="btn btn--ghost" href="/setari.php">Înapoi la setări</a>
        </div>
      </div>
    </section>

    <!-- ======================== 2. TELEFONUL ========================== -->
    <section class="card-set" aria-labelledby="t-telefon">
      <h2 class="card-set__titlu" id="t-telefon">Numărul de telefon</h2>
      <p class="card-set__lead">
        Nu apare pe profilul tău și nu-l vede nimeni deocamdată. Îl cerem ca,
        mai târziu, organizatorul unui eveniment la care te înscrii să te poată
        anunța dacă se schimbă ceva. Poți să-l lași gol.
      </p>

      <form class="form" id="telefon-form" novalidate>
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

        <div class="field">
          <label for="st-telefon">Telefon <span class="field__optional">(opțional)</span></label>
          <input type="tel" id="st-telefon" name="telefon" autocomplete="tel"
                 inputmode="tel" placeholder="0722 334 455" maxlength="40"
                 value="<?= h($telefon) ?>" aria-describedby="err-st-telefon">
          <p class="field__error" id="err-st-telefon" hidden></p>
        </div>

        <button class="btn btn--primary" type="submit">Salvează numărul</button>
      </form>
    </section>

    <!-- ======================= 3. E-MAILURILE ========================= -->
    <!--
      Două bife, un singur buton: amândouă răspund la aceeași întrebare — ce
      e-mailuri vrea omul — și se salvează în aceeași cerere. Două butoane
      alăturate, fiecare cu câte o bifă, ar fi pus omul să apese de două ori
      pentru o singură hotărâre.

      Sunt însă DOUĂ coloane în bază, nu una (vezi sql/021): reclamele și
      răspunsurile la propriile vorbe nu se sting împreună.
    -->
    <section class="card-set" aria-labelledby="t-news">
      <h2 class="card-set__titlu" id="t-news">E-mailuri de la noi</h2>

      <!-- `form--bife`: numai bife cu text lung, deci un spațiu mai larg între
           ele decât între câmpuri obișnuite — vezi lămurirea din style.css. -->
      <form class="form form--bife" id="instiintari-form" novalidate>
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

        <div class="field">
          <label class="check">
            <input type="checkbox" id="st-comentarii" name="email_comentarii"
                   <?= $emailComentarii ? 'checked' : '' ?>>
            <span>Vreau să primesc e-mail când cineva comentează la evenimentul meu
                  sau îmi răspunde la un comentariu.</span>
          </label>
        </div>

        <!--
          A treia bifă, tot cu coloana ei (vezi sql/027). DOAR PENTRU CE E
          SCRIS: stelele rămân anonime, deci despre ele nu pleacă niciodată
          niciun mesaj — o înștiințare la fiecare stea ar fi însemnat cinci
          e-mailuri după o ieșire cu cinci oameni, fiecare spunând „cineva
          te-a notat, nu-ți spunem cine, nu-ți spunem cât". Scrie chiar în
          rândul bifei, ca nimeni să nu se aștepte la altceva.
        -->
        <div class="field">
          <label class="check">
            <input type="checkbox" id="st-feedback" name="email_feedback"
                   <?= $emailFeedback ? 'checked' : '' ?>>
            <span>Vreau să primesc e-mail când cineva îmi lasă o părere scrisă pe
                  profil. (Notele cu stele rămân anonime — despre ele nu-ți
                  scriem.)</span>
          </label>
        </div>

        <button class="btn btn--primary" type="submit">Salvează</button>
      </form>
    </section>

    <!-- ====================== 4. ȘTERGEREA ============================ -->
    <!-- Zonă separată, cu roșu, ca să nu fie apăsată din greșeală de cineva
         care voia doar să-și schimbe telefonul. -->
    <section class="card-set card-set--pericol" aria-labelledby="t-stergere">
      <div id="stergere-block">
        <h2 class="card-set__titlu" id="t-stergere">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 4.5 2.8 20h18.4L12 4.5Z"/><path d="M12 10v4"/><path d="M12 16.8v.1"/>
          </svg>
          Șterge contul
        </h2>

        <p class="card-set__lead">
          Contul intră într-un răgaz de <?= ZILE_RAGAZ_STERGERE ?> de zile. În tot acest timp
          datele tale rămân neatinse, iar dacă intri din nou în cont ștergerea se
          oprește singură. După cele <?= ZILE_RAGAZ_STERGERE ?> de zile, numele, adresa de
          e-mail și telefonul dispar definitiv.
        </p>
        <p class="card-set__lead">
          Evenimentele la care ai participat rămân în istoricul site-ului, dar
          fără numele tău — altfel s-ar strica socotelile celorlalți.
        </p>

        <button class="btn btn--rau" type="button" id="stergere-start">Vreau să-mi șterg contul</button>

        <!-- Pasul al doilea apare abia după apăsare: nimeni nu-și șterge
             contul dintr-o singură mișcare greșită. -->
        <form class="form stergere-confirm" id="stergere-form" novalidate hidden>
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

          <?php if ($areParola): ?>
          <?php
            $campId     = 'st-parola';
            $campNume   = 'parola';
            $campText   = 'Scrie-ți parola, ca să fim siguri că ești tu';
            $campAjutor = 'Parola ta';
            require __DIR__ . '/inc/camp-parola.php';
          ?>
          <?php else: ?>
          <p class="card-set__lead">
            Contul tău nu are parolă, deci trecem direct la e-mail.
          </p>
          <?php endif; ?>

          <p class="card-set__lead">
            Îți trimitem un e-mail cu un link de confirmare. Ștergerea pornește
            abia când apeși linkul acela.
          </p>

          <div class="stergere-confirm__actiuni">
            <button class="btn btn--rau" type="submit">Trimite-mi e-mailul</button>
            <button class="btn btn--ghost" type="button" id="stergere-renunt">Renunț</button>
          </div>
        </form>
      </div>

      <div class="done" id="stergere-done" hidden>
        <span class="done__ico" aria-hidden="true">
          <svg class="ico" viewBox="0 0 24 24">
            <rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.8 7 8.2 5.6L20.2 7"/>
          </svg>
        </span>
        <h3 class="done__title">Verifică-ți e-mailul</h3>
        <p class="done__text" id="stergere-done-text">
          Ți-am trimis un link de confirmare. Contul tău e neatins până apeși pe el.
        </p>
      </div>
    </section>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
