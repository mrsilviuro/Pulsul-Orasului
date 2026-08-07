<?php
declare(strict_types=1);

$titlu     = 'Contact — PulsulOrasului.Ro';
$descriere = 'Scrie-ne: propuneri de evenimente, sesizări, colaborări sau orice altceva legat de PulsulOrasului.Ro.';
$pagina    = 'contact';

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/validare.php';   // MESAJ_MAX

/**
 * Pentru cine e conectat, câmpurile vin din cont.
 *
 * Sunt și blocate în pagină, dar asta e doar comoditate: verificarea adevărată
 * e pe server, unde datele membrului se iau din baza de date, nu din ce a venit
 * în formular (vezi api/contact.php).
 */
$eu = membruCurent();

$euNume = $euEmail = $euTelefon = '';

if ($eu !== null) {
    $q = db()->prepare('SELECT nume, prenume, email, telefon FROM membri WHERE id = ? LIMIT 1');
    $q->execute([(int) $eu['id']]);
    $contul = $q->fetch() ?: [];

    $euNume    = trim(($contul['nume'] ?? '') . ' ' . ($contul['prenume'] ?? ''));
    $euEmail   = (string) ($contul['email'] ?? '');
    $euTelefon = (string) ($contul['telefon'] ?? '');
}

// Token-ul se cere înaintea antetului: după ce pagina începe să se tipărească,
// sesiunea nu mai poate fi pornită.
$csrf = tokenCsrf();

require __DIR__ . '/inc/antet.php';
?>


<main id="main">
  <div class="wrap">

    <!-- ============================ ANTET ============================== -->
    <header class="page-head">
      <p class="eyebrow"><span class="pulse-dot" aria-hidden="true"></span> Scrie-ne</p>
      <h1 class="page-title">Contact</h1>
      <p class="page-lead">
        Ai un eveniment de propus, o sesizare sau vrei să colaborăm? Scrie-ne direct
        aici — răspundem, de regulă, în aceeași zi lucrătoare.
      </p>
    </header>

    <div class="contact">

      <!-- ========================= FORMULARUL ========================== -->
      <section class="contact__form-wrap" aria-labelledby="form-title">
        <h2 class="contact__h2" id="form-title">Trimite-ne un mesaj</h2>

        <form class="form" id="contact-form" novalidate
              data-logat="<?= $eu !== null ? 'true' : 'false' ?>">

          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

          <!--
            Capcana pentru roboți.
            Ascunsă prin scoaterea din ecran, NU prin display:none: mulți roboți
            sar peste câmpurile ascunse așa, dar îl completează pe acesta. Un om
            nu-l vede și nu ajunge la el nici cu tastatura (tabindex="-1").
            Numele „website" e ales dinadins: e printre cele mai râvnite de roboți.
          -->
          <div class="capcana" aria-hidden="true">
            <label for="cf-website">Nu completa acest câmp</label>
            <input type="text" id="cf-website" name="website" tabindex="-1" autocomplete="off">
          </div>

          <div class="field">
            <label for="cf-name">Nume și prenume <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="cf-name" name="nume" autocomplete="name"
                   placeholder="Popescu Ion" required
                   value="<?= h($euNume) ?>"<?= $eu !== null ? ' readonly' : '' ?>
                   aria-describedby="err-name">
            <p class="field__error" id="err-name" hidden></p>
          </div>

          <div class="field-row">
            <div class="field">
              <label for="cf-email">Adresa de e-mail <span class="req" aria-hidden="true">*</span></label>
              <input type="email" id="cf-email" name="email" autocomplete="email"
                     placeholder="adresa@email.ro" required
                     value="<?= h($euEmail) ?>"<?= $eu !== null ? ' readonly' : '' ?>
                     aria-describedby="err-email">
              <p class="field__error" id="err-email" hidden></p>
            </div>

            <div class="field">
              <label for="cf-phone">Telefon <span class="req" aria-hidden="true">*</span></label>
              <!-- Membrul care n-are telefon în cont îl scrie acum, și tot
                   obligatoriu: la un mesaj de contact vrem să putem suna înapoi.
                   Ce scrie aici se salvează și în contul lui. -->
              <input type="tel" id="cf-phone" name="telefon" autocomplete="tel"
                     inputmode="tel" placeholder="0722 334 455" maxlength="40" required
                     value="<?= h($euTelefon) ?>"<?= $euTelefon !== '' ? ' readonly' : '' ?>
                     aria-describedby="err-phone<?= $eu !== null && $euTelefon === '' ? ' cf-phone-hint' : '' ?>">
              <?php if ($eu !== null && $euTelefon === ''): ?>
              <p class="field__hint" id="cf-phone-hint">Îl salvăm și în contul tău, ca să nu-l mai scrii data viitoare.</p>
              <?php endif; ?>
              <p class="field__error" id="err-phone" hidden></p>
            </div>
          </div>

          <div class="field">
            <label for="cf-message">Mesajul tău <span class="req" aria-hidden="true">*</span></label>
            <textarea id="cf-message" name="mesaj" rows="6" maxlength="<?= MESAJ_MAX ?>"
                      placeholder="Scrie aici despre ce e vorba…" required
                      aria-describedby="err-message"></textarea>
            <p class="field__error" id="err-message" hidden></p>
          </div>

          <div class="form__foot">
            <p class="form__note">Câmpurile marcate cu <span class="req">*</span> sunt obligatorii.</p>
            <button class="btn btn--primary" type="submit">
              <span>Trimite mesajul</span>
              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 12h15"/><path d="m13 6 6 6-6 6"/>
              </svg>
            </button>
          </div>

          <!-- confirmarea apare aici, după trimitere -->
          <p class="form__success" id="form-success" role="status" hidden>
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>
            </svg>
            <span>Mesajul a fost trimis. Îți mulțumim — revenim cu un răspuns în cel mai scurt timp.</span>
          </p>
        </form>
      </section>

      <!-- ======================= DATE DE CONTACT ======================= -->
      <aside class="contact__info" aria-labelledby="info-title">
        <h2 class="contact__h2" id="info-title">Date de contact</h2>

        <ul class="info-list">
          <li class="info-item">
            <span class="info-item__ico" aria-hidden="true">
              <svg class="ico" viewBox="0 0 24 24">
                <rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.8 7 8.2 5.6L20.2 7"/>
              </svg>
            </span>
            <div>
              <span class="info-item__label">E-mail</span>
              <a class="info-item__value" href="mailto:contact@pulsulorasului.ro">contact@pulsulorasului.ro</a>
            </div>
          </li>

          <li class="info-item">
            <span class="info-item__ico" aria-hidden="true">
              <svg class="ico" viewBox="0 0 24 24">
                <path d="M6.5 3.5h3l1.5 4-2 1.4a12 12 0 0 0 6.1 6.1l1.4-2 4 1.5v3a2 2 0 0 1-2.2 2A16.8 16.8 0 0 1 4.5 5.7a2 2 0 0 1 2-2.2Z"/>
              </svg>
            </span>
            <div>
              <span class="info-item__label">Telefon</span>
              <a class="info-item__value" href="tel:+40700000000">+40 700 000 000</a>
            </div>
          </li>

          <li class="info-item">
            <span class="info-item__ico" aria-hidden="true">
              <svg class="ico" viewBox="0 0 24 24">
                <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>
              </svg>
            </span>
            <div>
              <span class="info-item__label">Adresă</span>
              <span class="info-item__value">Str. Exemplu nr. 10<br>Oraș, România</span>
            </div>
          </li>

          <li class="info-item">
            <span class="info-item__ico" aria-hidden="true">
              <svg class="ico" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.4 2"/>
              </svg>
            </span>
            <div>
              <span class="info-item__label">Program</span>
              <span class="info-item__value">Luni — Vineri, 09:00 — 18:00</span>
            </div>
          </li>
        </ul>

        <div class="info-social">
          <span class="info-item__label">Ne găsești și pe</span>
          <div class="socials">
            <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8.5V7a1.5 1.5 0 0 1 1.5-1.5H17V3h-2.2A3.8 3.8 0 0 0 11 6.8v1.7H9V11h2v10h3V11h2.2l.4-2.5H14Z" fill="currentColor" stroke="none"/></svg></a>
            <a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r="1.1" fill="currentColor" stroke="none"/></svg></a>
            <a href="#" aria-label="YouTube"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="m10.5 9.5 5 2.5-5 2.5z"/></svg></a>
          </div>
        </div>

        <div class="info-cta">
          <p>Vrei să publici tu evenimente pe site?</p>
          <a class="btn btn--ghost btn--sm" href="login.php#inregistrare">Alătură-te și tu</a>
        </div>
      </aside>

    </div>
  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
