<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — un câmp de dată, scris ZZ-LL-AAAA.
 *
 * Câmpul vizibil e de TEXT, nu `type="date"`. Motivul e simplu și l-am
 * încercat: `type="date"` se desenează după limba browserului, nu a paginii.
 * `lang="ro"` pe input n-are niciun efect — cine are Chrome în engleză vedea
 * „mm/dd/yyyy" pe un site românesc, adică exact formatul în care 05-12 e „5
 * decembrie" pentru noi și „12 mai" pentru browser. Aici scriem noi cratimele,
 * cifră cu cifră, din main.js.
 *
 * Calendarul nu s-a pierdut: lângă câmp stă un `type="date"` adevărat,
 * ascuns, pe care butonul îl deschide cu showPicker(). El poartă „min" și
 * „max" (în formatul lui, AAAA-LL-ZZ), iar ce se alege acolo se scrie înapoi
 * în câmpul vizibil, pe românește. Pe telefon rămâne astfel roata nativă.
 *
 * Câmpul ascuns NU are „name": nimic din el nu pleacă spre server, ca să nu
 * existe două date în aceeași cerere.
 *
 * JS-ul găsește singur cele trei bucăți, după id: „<id>", „<id>-nativ" și
 * „<id>-calendar". Nu schimba tiparul fără să schimbi și legaCampData().
 *
 * Se folosește așa:
 *
 *     $dataId      = 'rg-birthdate';
 *     $dataNume    = 'data_nasterii';
 *     $dataText    = 'Data nașterii';
 *     $dataValoare = '1990-05-20';   // opțional, cum stă în bază (AAAA-LL-ZZ)
 *     $dataExemplu = '25-12-1990';   // opțional, textul palid din câmp
 *     $dataMin     = '1906-01-01';   // opțional, pentru calendar (AAAA-LL-ZZ)
 *     $dataMax     = '2016-08-13';   // opțional, la fel
 *     $dataAuto    = 'bday';         // opțional, autocomplete
 *     $dataStea    = true;           // opțional, steluța de „obligatoriu"
 *     require __DIR__ . '/inc/camp-data.php';
 *
 * Variabilele se golesc la final, ca al doilea câmp de pe pagină să nu
 * moștenească din greșeală ce a rămas de la primul.
 */

$dataValoare = $dataValoare ?? '';
$dataExemplu = $dataExemplu ?? '25-12-2026';
$dataAuto    = $dataAuto    ?? 'off';
$dataMin     = $dataMin     ?? '';
$dataMax     = $dataMax     ?? '';
$dataStea    = $dataStea    ?? false;
?>
<div class="field">
  <label for="<?= h($dataId) ?>"><?= h($dataText) ?><?php if ($dataStea): ?> <span class="req" aria-hidden="true">*</span><?php endif; ?></label>

  <div class="camp-data">
    <!-- `pattern` spune și browserului aceeași regulă pe care o ține serverul
         în dataDinFormular(): exact ZZ-LL-AAAA, cu zerourile puse. -->
    <input type="text" id="<?= h($dataId) ?>" name="<?= h($dataNume) ?>" required
           class="camp-data__text" inputmode="numeric" autocomplete="<?= h($dataAuto) ?>"
           maxlength="10" placeholder="<?= h($dataExemplu) ?>"
           pattern="(0[1-9]|[12][0-9]|3[01])-(0[1-9]|1[0-2])-[0-9]{4}"
           value="<?= h(dataPentruFormular($dataValoare ?: null)) ?>"
           aria-describedby="err-<?= h($dataId) ?>">

    <button type="button" class="camp-data__buton" id="<?= h($dataId) ?>-calendar"
            aria-label="Alege data din calendar">
      <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
        <rect x="3.5" y="5" width="17" height="16" rx="3"/>
        <path d="M8 3v4M16 3v4M3.5 10h17"/>
      </svg>
    </button>

    <input type="date" id="<?= h($dataId) ?>-nativ" class="camp-data__nativ"
           tabindex="-1" aria-hidden="true"
           <?= $dataMin !== '' ? 'min="' . h($dataMin) . '"' : '' ?>
           <?= $dataMax !== '' ? 'max="' . h($dataMax) . '"' : '' ?>>
  </div>

  <p class="field__error" id="err-<?= h($dataId) ?>" hidden></p>
</div>
<?php
unset($dataId, $dataNume, $dataText, $dataValoare, $dataExemplu, $dataAuto,
      $dataMin, $dataMax, $dataStea);
