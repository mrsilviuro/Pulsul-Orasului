<?php
declare(strict_types=1);

$titlu     = 'Despre — PulsulOrasului.Ro';
$descriere = 'Cine suntem, ce publicăm și cum poți contribui la PulsulOrasului.Ro — locul unde afli ce se întâmplă în oraș.';
$pagina    = 'despre';

require __DIR__ . '/inc/antet.php';
?>


<main id="main">
  <div class="wrap">

  <nav class="crumbs" aria-label="Navigare">
  <a href="/index.php">Acasă</a>
  <span aria-hidden="true">/</span>
  <span class="crumbs__current">Despre</span>
  </nav>

    <div class="prose">

    <h1 class="page-title">Salut! Mă bucur că ai dat de pagina asta și că ți-ai rupt două minute să citești povestea din spatele site-ului.</h1>

    <figure class="post__figure">
    <img src="/assets/img/despre.jpg" alt=""
    width="1600" height="900" fetchpriority="high" decoding="async">
    </figure><br>

    <p>Dacă ești din Roman, probabil ai observat și tu același lucru pe care l-am văzut eu în ultimii ani: terenuri de fotbal goale pe care altădată abia găseai loc să joci, terase tot mai amorțite, pub-uri locale care au pus lacătul pe ușă și o liniște apăsătoare pe străzi. După pandemie, lucrurile s-au schimbat mult. Toate s-au scumpit masiv, iar pentru mulți dintre noi, statul în casă a devenit cea mai ieftină și accesibilă opțiune. Dar mai e ceva: am rămas prinși în ecrane.</p>

    <p>Ne trezim că trec ore, zile, verile și anii pe lângă noi în timp ce rulăm la nesfârșit videoclipuri pe TikTok, Reels sau Facebook. Teoretic, rețelele sociale ar fi trebuit să ne aducă mai aproape. În realitate, ne-au izolat mai mult ca niciodată. Algoritmii sunt concepuți să ne țină captivi în virtual și să ne bage pe gât doar reclame sau conținut promovat. Iar dacă încerci vreodată să pui o postare simplă pe un grup local, gen „Cine vine la un fotbal sau la o cafea?”, ideea ta e rapid îngropată sub promovări sau, mai rău, apar conturi anonime și trolli care strică toată bucuria. Oamenii postează acolo mai mult probleme decât rezolvări.</p>

    <p><b>De asta am început să lucrez la Pulsul Orașului.</b></p>

    <p>Am vrut să creez un loc curat, simplu și fără zgomot de fundal, dedicat exclusiv oamenilor care vor să mai iasă din casă. Nu e un site de reclame și nici un ziar local. Este o platformă dedicată activităților necomerciale. Când zic necomercial, mă refer la lucrurile simple care ne făceau plăcere înainte: o ieșire la tenis de masă, un meci de fotbal, o tură cu bicicleta sau cu motocicleta, o drumeție, o seară de boardgames sau o simplă plimbare prin parc. Ceva unde nu plătești bilet de intrare și unde nimeni nu încearcă să-ți vândă nimic.</p>

    <p>Asta nu înseamnă că totul trebuie să fie 100% gratuit. Dacă închiriați un teren de sport, împărțiți benzina pentru o ieșire din oraș sau vă plătiți propria consumație la o terasă, astea sunt doar costuri comune și firești între prieteni, nu o afacere.</p>

    <p>Un lucru la care țin enorm este transparența: pe Pulsul Orașului nu există anonimat. În viața reală nu ieșim în oraș cu punga pe cap, așa că nu are niciun sens să încurajăm anonimatul sau trolling-ul online. Vrem să construim un spațiu în care să te simți în siguranță și să știi cu cine te întâlnești.</p>

    <p>Acesta este un proiect de suflet, 100% non-profit. Nu am investit bani în promovare plătită și nici nu o voi face. Site-ul ăsta va trăi și va crește doar dacă noi, comunitatea, ne dorim asta. Dacă îl distribuim prietenilor, dacă propunem ieșiri și dacă alegem să fim mai prezenți în viața reală. Momentan am pornit la drum în Roman, dar dacă lucrurile merg bine și vor exista oameni din alte orașe care vor să preia ideea și să o aplice la ei (tot fără interese financiare), ușa e larg deschisă.</p>

    <blockquote><p>O mică paranteză de bun-simț: din când în când, voi mai prelua și promova aici activități faine pe care le găsesc publice prin alte părți, doar ca să ajutăm la vizibilitatea lor. Dacă ești organizatorul unui astfel de eveniment și din orice motiv dorești retragerea lui, trimite-ne un mesaj folosind formularul de contact cu link-ul catre evenimentul respectiv, specifică motivul și îl voi șterge în cel mai scurt timp.</p></blockquote>

    <p>Închei cu un gând simplu. Ne-am obișnuit să spunem prea des: „N-ai ce să faci în orașul ăsta”, „N-am cu cine să ies” sau „E mort totul”. Adevărul e că orașul suntem noi. Dacă vrei să faci o activitate și nu găsești pe cineva care să o organizeze, fii tu cel care face primul pas. Propune o ieșire, trimite linkul mai departe și hai să ne adunăm.</p>

    <p><b>Mulțumesc că ești aici. Hai să facem inima orașului să bată din nou!</b></p>

    </div>

    <!-- ============================= FINAL ============================= -->
    <section class="cta">
    <div class="cta__glow" aria-hidden="true"></div>
    <div class="cta__content">
    <p class="eyebrow eyebrow--light">REDESCHIDEM ORAȘUL ÎMPREUNĂ</p>
    <h2>Vrei să ieși afară dar n-ai cu cine?</h2>
    <p class="cta__text">De la jocuri de weekend până la drumeții sau ieșiri cu motoarele. Adaugă activitatea ta pe site și cunoaște oameni faini din oraș.</p>
    <div class="cta__actions">
    <a class="btn btn--primary" href="/adauga_eveniment.php">Propune o ieșire</a>
    <a class="btn btn--outline" href="/login.php">Alătură-te și tu!</a>
    </div>
    </div>
    </section>

  </div>
</main>
<?php require __DIR__ . '/inc/subsol.php'; ?>
