<?php
declare(strict_types=1);

/**
 * PulsulOrasului.Ro — ieșirea din cont.
 *
 * Linkul din meniu poartă token-ul CSRF, ca un alt site să nu poată deconecta
 * vizitatorul printr-o simplă imagine ascunsă care indică spre pagina asta.
 */

require_once __DIR__ . '/inc/auth.php';

$token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';

if (esteLogat() && tokenCsrfValid($token)) {
    deconecteaza();
    header('Location: index.php?iesit=1');
    exit;
}

// Token lipsă sau greșit: nu deconectăm, doar trimitem omul acasă.
header('Location: index.php');
exit;
