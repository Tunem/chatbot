<?php
header('Content-Type: application/json');

// Otetaan vastaan JavaScriptin lähettämä viesti
$viesti = $_POST['viesti'] ?? 'Ei viestiä';

// Lähetetään vastaus takaisin
echo json_encode([
    'vastaus' => 'Palvelin vastaanotti viestisi: ' . $viesti,
    'aika' => date('H:i:s')
]);