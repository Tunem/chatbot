<?php
// hae-lause.php
header('Content-Type: application/json');

$url = "https://zenquotes.io/api/random";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$res = curl_exec($ch);

$data = json_decode($res, true);

if (isset($data[0]['q'])) {
    echo json_encode([
        'teksti' => $data[0]['q'],
        'tekija' => $data[0]['a']
    ]);
} else {
    echo json_encode(['teksti' => 'Päivä on täynnä mahdollisuuksia.', 'tekija' => 'Botti']);
}