<?php
declare(strict_types=1);

$titlu     = 'Despre — PulsulOrasului.Ro';
$descriere = 'Cine suntem, ce publicăm și cum poți contribui la PulsulOrasului.Ro — locul unde afli ce se întâmplă în oraș.';
$pagina    = 'despre';

require __DIR__ . '/inc/antet.php';
?>


<main id="main">
  <div class="wrap">

    <!-- ============================ ANTET ============================== -->
    <header class="page-head">
      <p class="eyebrow"><span class="pulse-dot" aria-hidden="true"></span> Despre noi</p>
      <h1 class="page-title">Pulsul orașului, scris de oamenii lui</h1>
      <p class="page-lead">
        Un loc simplu unde afli ce se întâmplă în oraș săptămâna asta — și unde poți
        anunța tu ce organizezi, fără să depinzi de altcineva.
      </p>
    </header>

    <!-- ============================= TEXT ============================== -->
    <div class="prose">

      <p>
        <strong>PulsulOrasului.Ro</strong> a pornit dintr-o nemulțumire simplă: informația
        despre ce se întâmplă în oraș era împrăștiată peste tot. Un concert anunțat într-un
        grup de Facebook, un maraton pe un site care nu se mai actualizase de doi ani, un
        târg despre care aflai abia după ce se terminase.
      </p>

      <p>
        Ne-am propus să adunăm totul într-un singur loc, ordonat și ușor de citit — de pe
        telefon, în două minute, cât aștepți autobuzul.
      </p>

      <h2>Ce publicăm</h2>

      <p>
        Orice mișcă în oraș și îi poate interesa pe cei care locuiesc aici:
      </p>

      <ul>
        <li><strong>Sport</strong> — curse, meciuri, competiții locale, trasee noi de alergat sau de mers cu bicicleta.</li>
        <li><strong>Cultură</strong> — concerte, festivaluri, expoziții, spectacole, proiecții în aer liber.</li>
        <li><strong>Comunitate</strong> — acțiuni de voluntariat, ședințe publice, lucrări care schimbă circulația, inițiative de cartier.</li>
        <li><strong>Gastro</strong> — târguri, piețe de producători, locuri noi care merită încercate.</li>
      </ul>

      <p>
        Fiecare anunț are data, ora, locul și, unde e cazul, prețul. Iar dacă te interesează
        un eveniment, poți spune asta direct pe pagina lui — așa vezi cine mai vine și
        organizatorii își fac o idee despre câți oameni să aștepte.
      </p>

      <h2>Cine scrie</h2>

      <p>
        La început, câțiva oameni cărora le păsa. Acum, oricine vrea. Ideea de bază e că
        cel care organizează ceva știe cel mai bine cum să povestească — și merită să poată
        face asta singur, fără să roage un redactor.
      </p>

      <p>
        Îți faci cont, scrii anunțul, adaugi o poză și îl publici. Durează câteva minute și
        e gratuit. Verificăm doar ca lucrurile să fie reale și scrise cumsecade.
      </p>

      <blockquote>
        <p>
          Un oraș e mai plăcut de locuit când știi ce se întâmplă în el. Atât ne-am propus.
        </p>
      </blockquote>

      <h2>Ce nu facem</h2>

      <p>
        Nu publicăm reclamă deghizată în articol, nu vindem primele poziții din pagină și nu
        acceptăm atacuri la persoană în comentarii. Nu suntem un site de știri: nu ne ocupăm
        de politică, de scandaluri sau de accidente. Rămânem la ce se poate face în oraș.
      </p>

      <h2>Cum ne găsești</h2>

      <p>
        Dacă vrei să publici un eveniment, începe de la pagina
        <a href="login.php#inregistrare">Alătură-te și tu</a>. Dacă ai o propunere, o corectură sau
        vrei să colaborăm, scrie-ne prin <a href="contact.php">formularul de contact</a> —
        răspundem, de regulă, în aceeași zi lucrătoare.
      </p>

    </div>

    <!-- ============================= FINAL ============================= -->
    <section class="cta">
      <div class="cta__glow" aria-hidden="true"></div>
      <div class="cta__content">
        <p class="eyebrow eyebrow--light">Hai cu noi</p>
        <h2>Organizezi ceva în oraș?</h2>
        <p class="cta__text">
          Publică evenimentul tău pe PulsulOrasului.Ro. E gratuit și durează câteva minute.
        </p>
        <div class="cta__actions">
          <a class="btn btn--primary" href="login.php#inregistrare">Alătură-te și tu</a>
          <a class="btn btn--outline" href="contact.php">Scrie-ne</a>
        </div>
      </div>
    </section>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
