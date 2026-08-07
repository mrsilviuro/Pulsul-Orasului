<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — un câmp de parolă, cu ochiul de „arată parola".
 *
 * Aceleași douăzeci de rânduri erau scrise de trei ori numai în parola-noua.php.
 * Odată cu pagina de setări ar fi ajuns la șase. Aici stau o dată.
 *
 * Se folosește așa:
 *
 *     $campId    = 'pn-noua';
 *     $campNume  = 'parola';
 *     $campText  = 'Parola nouă';
 *     $campAjutor= 'Cel puțin 8 caractere';   // opțional, textul din câmp
 *     $campAuto  = 'new-password';            // opțional
 *     $campMetru = true;                      // opțional, indicatorul de putere
 *     require __DIR__ . '/inc/camp-parola.php';
 *
 * Variabilele se golesc la final, ca al doilea câmp de pe pagină să nu
 * moștenească din greșeală ce a rămas de la primul.
 */

$campAuto   = $campAuto   ?? 'current-password';
$campAjutor = $campAjutor ?? '';
$campMetru  = $campMetru  ?? false;
?>
<div class="field">
  <label for="<?= h($campId) ?>"><?= h($campText) ?></label>
  <div class="input-pass">
    <input type="password" id="<?= h($campId) ?>" name="<?= h($campNume) ?>"
           autocomplete="<?= h($campAuto) ?>" placeholder="<?= h($campAjutor) ?>"
           required aria-describedby="err-<?= h($campId) ?>">
    <button class="input-pass__toggle" type="button" data-toggle-pass="<?= h($campId) ?>"
            aria-label="Arată parola" aria-pressed="false">
      <svg class="ico ico--eye" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M2.5 12S6 5.8 12 5.8 21.5 12 21.5 12 18 18.2 12 18.2 2.5 12 2.5 12Z"/>
        <circle cx="12" cy="12" r="3.2"/>
      </svg>
      <svg class="ico ico--eye-off" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M9.9 5.9A9.6 9.6 0 0 1 12 5.8c6 0 9.5 6.2 9.5 6.2a17 17 0 0 1-3.5 4.2M6 7.8A17 17 0 0 0 2.5 12S6 18.2 12 18.2c1.4 0 2.6-.3 3.7-.8"/>
        <path d="m4 4 16 16"/>
      </svg>
    </button>
  </div>

  <?php if ($campMetru): ?>
  <!-- Aceeași structură ca la înregistrare, deci același CSS și același cod
       din main.js. -->
  <div class="pass-meter" id="<?= h($campId) ?>-meter" hidden>
    <div class="pass-meter__bars" aria-hidden="true">
      <span></span><span></span><span></span><span></span>
    </div>
    <span class="pass-meter__label" id="<?= h($campId) ?>-hint" role="status"></span>
  </div>
  <?php endif; ?>

  <p class="field__error" id="err-<?= h($campId) ?>" hidden></p>
</div>
<?php
unset($campId, $campNume, $campText, $campAjutor, $campAuto, $campMetru);
