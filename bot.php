<?php
// ============================================================
// bot.php — UutisBotti v1.5
// ============================================================

// === ALUSTUS ===
session_start(); // TÄRKEÄ: Mahdollistaa botin "muistin"
header('Content-Type: application/json');
date_default_timezone_set('Europe/Helsinki'); // Varmistetaan oikea aika

// === APUFUNKTIOT ===
function haeSaaVastaus($kaupunki) {
    $kaupunki = ucfirst(trim($kaupunki));

    // 1. GEOLOKAATIO (Muutetaan kaupunki koordinaateiksi Yr.no:ta varten)
    $geo_url = "https://nominatim.openstreetmap.org/search?city=" . urlencode($kaupunki) . "&format=json&limit=1";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $geo_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'UutisBotti/1.6');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $geo_res = json_decode(curl_exec($ch), true);

    if (empty($geo_res)) {
        return json_encode(['reply' => "En löytänyt kaupunkia '$kaupunki'. Kokeile tarkempaa nimeä!"]);
    }

    $lat = number_format($geo_res[0]['lat'], 2);
    $lon = number_format($geo_res[0]['lon'], 2);

    // --- YR.NO HAKU ---
    $url_yr = "https://api.met.no/weatherapi/locationforecast/2.0/compact?lat={$lat}&lon={$lon}";
    curl_setopt($ch, CURLOPT_URL, $url_yr);
    $res_yr_raw = curl_exec($ch);

    $temp_yr = "??";
    $symboli_yr = "clearsky_day";
    if ($res_yr_raw) {
        $res_yr = json_decode($res_yr_raw, true);
        if (isset($res_yr['properties']['timeseries'][0]['data'])) {
            $current = $res_yr['properties']['timeseries'][0]['data']['instant']['details'];
            $temp_yr = $current['air_temperature'] ?? "??";
            $wind_yr = $current['wind_speed'] ?? "??";
            $symboli_yr = $res_yr['properties']['timeseries'][0]['data']['next_1_hours']['summary']['symbol_code'] ?? "clearsky_day";
            $desc_yr = str_replace('_', ' ', $symboli_yr);
        }
    }

    // --- FMI HAKU (UUSI VARMA LOGIIKKA) ---
    $url_fmi = "https://opendata.fmi.fi/wfs?service=WFS&version=2.0.0&request=getFeature&storedquery_id=fmi::observations::weather::simple&place=" . urlencode($kaupunki) . "&maxlocations=1";
    curl_setopt($ch, CURLOPT_URL, $url_fmi);
    $res_fmi_raw = curl_exec($ch);

    $temp_fmi = "??";
    $wind_fmi = "??";
    if ($res_fmi_raw) {
        $xml_fmi = simplexml_load_string($res_fmi_raw);
        if ($xml_fmi !== false) {
            // Rekisteröidään nimiavaruudet, jotta haku toimii
            $xml_fmi->registerXPathNamespace('wfs', 'http://www.opengis.net/wfs/2.0');
            $xml_fmi->registerXPathNamespace('BsWfs', 'http://xml.fmi.fi/schema/wfs/2.0');

            // Haetaan lämpötila (t2m) ja tuuli (ws_10min)
            $t = $xml_fmi->xpath("//BsWfs:BsWfsElement[BsWfs:ParameterName='t2m']/BsWfs:ParameterValue");
            $w = $xml_fmi->xpath("//BsWfs:BsWfsElement[BsWfs:ParameterName='ws_10min']/BsWfs:ParameterValue");
            
            if (!empty($t)) $temp_fmi = number_format((float)$t[0], 1);
            if (!empty($w)) $wind_fmi = number_format((float)$w[0], 1);
        }
    }

    // Muotoillaan vastaus
    $vastaus = "<strong>Säävertailu: $kaupunki</strong><br>";
    $vastaus .= "<div style='display: flex; gap: 8px; margin-top: 10px;'>";

    // --- VASTAUSLOHKO YR.NO ---
    $vastaus .= "<div class='saa-lohko'>";
    $vastaus .= "<img src='https://raw.githubusercontent.com/metno/weathericons/master/weather/svg/{$symboli_yr}.svg' style='width:35px;'><br>";
    $vastaus .= "<small>Yr.no Ennuste</small><br>";
    $vastaus .= "<b>{$temp_yr}°C</b><br>";
    $vastaus .= "<span style='font-size:11px;'>💨 {$wind_yr} m/s<br><i>{$desc_yr}</i></span></div>";

    // --- VASTAUSLOHKO FMI ---
    $vastaus .= "<div class='saa-lohko'>";
    $vastaus .= "<div style='font-size:18px;'>🇫🇮</div>";
    $vastaus .= "<small>FMI Havainto</small><br>";
    $vastaus .= "<b>{$temp_fmi}°C</b><br>";
    $vastaus .= "<span style='font-size:11px;'>💨 {$wind_fmi} m/s<br><i>Lähin asema</i></span></div>";
    
    $vastaus .= "</div>";
    
    echo json_encode(['reply' => $vastaus]);
    exit;
}

// Apufunktio kääntämiseen (LibreTranslate)
function kaannaSuomeksi($teksti) {
    $url_mymemory = "https://api.mymemory.translated.net/get?q=" . urlencode($teksti) . "&langpair=en|fi";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_mymemory);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $res = curl_exec($ch);
    $data = json_decode($res, true);

    if (isset($data['responseData']['translatedText'])) {
        return $data['responseData']['translatedText'];
    }
    // Jos kääntäminen epäonnistuu, palautetaan alkuperäinen teksti ja pieni huomautus
    return $teksti . " (Käännös ei onnistunut juuri nyt)";
}

// Apufunktio vitsin hakuun
function haeVitsi($kieli) {
    $url = "https://official-joke-api.appspot.com/random_joke";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    $vitsi = json_decode($res, true);

    if (!isset($vitsi['setup'])) return json_encode(['reply' => "Vitsipankki on jumissa! 🤡"]);

    $alku = $vitsi['setup'];
    $loppu = $vitsi['punchline'];

    if ($kieli === 'fi') {
        $alku = kaannaSuomeksi($alku);
        $loppu = kaannaSuomeksi($loppu);
    }

    $vastaus = "<strong>$alku</strong><br><br>... $loppu 😂";
    return json_encode(['reply' => $vastaus]);
}

// === SYÖTTEEN VASTAANOTTO ===
// Haetaan viesti JavaScriptiltä
$input = mb_strtolower($_POST['message'] ?? '');
// Lisätään viive (mikrosekunteina). 1 000 000 = 1 sekunti.
// 1.5 sekuntia on yleensä sopiva aika "lukemiseen".
usleep(1500000);

// Jos viesti on tyhjä, lopetetaan heti
if (empty($input)) {
    echo json_encode(['reply' => 'En saanut viestiäsi.']);
    exit;
}

$kayttaja = $_SESSION['kayttaja_nimi'] ?? $_COOKIE['botti_nimi'] ?? null;

// === KÄSITTELY ===

// 1. Nimen muistaminen
// Tarkistetaan, kertooko käyttäjä nimensä
if (preg_match('/(?:nimeni on|olen) ([a-zåäö]+)/i', $input, $osumat)) {
    $nimi = ucfirst($osumat[1]);
    $_SESSION['kayttaja_nimi'] = $nimi;

    setcookie('botti_nimi', $nimi, time() + (86400 * 30), "/"); // Tallennetaan myös cookieen, jotta nimi säilyy uudelleenkäynneillä

    echo json_encode(['reply' => "Mukava tutustua, $nimi! Muistan nimesi, vaikka sulkisit selaimen.. 😊"]);
    exit;
}

// 2. Nimen unohdus
if (str_contains($input, 'unohda') && str_contains($input, 'nimi')) {
    unset($_SESSION['kayttaja_nimi']);
    // Poistetaan eväste asettamalla sen erääntymisaika menneisyyteen
    setcookie('botti_nimi', '', time() - 3600, "/");
    
    echo json_encode(['reply' => "Selvä, olen unohtanut nimesi ja poistanut evästeet. Voit kertoa uuden nimesi milloin vain! 🗑️"]);
    exit;
}

// 3. Nimen vaihto (ohjaa käyttäjää)
if (str_contains($input, 'vaihda') && str_contains($input, 'nimi')) {
    echo json_encode(['reply' => "Sopiihan se! Kerro uusi nimesi muodossa: 'Nimeni on [uusi nimi]'."]);
    exit;
}

// 4. Sää
if (preg_match('/^sää\s+(.+)/i', $input, $osumat)) {
    echo haeSaaVastaus($osumat[1]);
    exit;
}

if ($input === 'sää') {
    $_SESSION['odottaa_kaupunkia'] = true;
    echo json_encode(['reply' => "Minkä kaupungin sään haluat tietää?"]);
    exit;
}

if (isset($_SESSION['odottaa_kaupunkia'])) {
    unset($_SESSION['odottaa_kaupunkia']);
    echo haeSaaVastaus($input);
    exit;
}

// 5. Tervehdys (Kellonajalla)
$tervehdys_sanat = ['moi', 'hei', 'terve', 'huomenta'];
foreach ($tervehdys_sanat as $sana) {
    if (str_contains($input, $sana)) {
        unset($_SESSION['odottaa_lahdetta']); // Nollataan uutistila jos tervehditään

        $nimi_lisa = $kayttaja ? " " . $kayttaja : "";
        $tunti = (int)date('H');

        if ($tunti >= 5 && $tunti < 10) $tervehdys = "Hyvää huomenta$nimi_lisa! 🌅";
        elseif ($tunti >= 10 && $tunti < 17) $tervehdys = "Hyvää päivää$nimi_lisa! ☀️";
        elseif ($tunti >= 17 && $tunti < 22) $tervehdys = "Hyvää iltaa$nimi_lisa! 🌙";
        else $tervehdys = "Hyvää yötä$nimi_lisa! 🌌";

        echo json_encode(['reply' => "$tervehdys Olen UutisBotti. Kysy uutisia tai säätä!"]);
        exit;
    }
}

// 6. Salaliittoteoriat (Easter Egg)
$salaliitto_sanat = ['folio', 'foliohattu', 'salaliitto'];
foreach ($salaliitto_sanat as $sana) {
    if (str_contains($input, $sana)) {
        unset($_SESSION['odottaa_lahdetta']); // Nollataan uutistila jos puhutaan salaliitoista

        $vastaus = <<<HTML
                Hmm… kuulostaa siltä, että olet löytänyt salaisuuden! 🕵️‍♂️🔍
                Mutta valitettavasti en voi paljastaa enempää.
                Pidetään tämä meidän välisenä salaisuutena! 🤫
                Muu­­ssa tapauksessa voi käydä täällä:
                <a href="https://te26tv.okuserveri.com/foliohattu/index.html" target="_blank">foliohattu</a>
                HTML;

        echo json_encode(['reply' => $vastaus]);
        exit;
    }
}

// 7. Vitsit
// Käsitellään kielivalinta
if ($input === 'vitsi_fi') { echo haeVitsi('fi'); exit; }
if ($input === 'vitsi_en') { echo haeVitsi('en'); exit; }

if (str_contains($input, 'vitsi') || str_contains($input, 'naurata')) {
    // Lähetetään kielivalintakysymys ja erikoiskenttä 'vitsi_napit'
    if (!str_contains($input, 'vitsi_')) {
            echo json_encode([
                'reply' => "Haluatko vitsin suomeksi vai englanniksi?",
                'vitsi_napit' => true
            ]);
        exit;
    }
}


// 8. Päivän lause ---
if (str_contains($input, 'lause') || str_contains($input, 'motivaatio')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://zenquotes.io/api/random");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $lause_data = json_decode(curl_exec($ch), true);

    if (isset($lause_data[0]['q'])) {
        // Huom: Nämä ovat englanniksi. Jos haluat suomeksi, ne pitäisi kierrättää kääntäjän kautta.
        $vastaus = "<strong>Päivän ajatus:</strong><br><i>\"{$lause_data[0]['q']}\"</i><br>- {$lause_data[0]['a']}";
    } else {
        $vastaus = "Aina ei tarvita sanoja. (Lauseen haku epäonnistui)";
    }
    
    echo json_encode(['reply' => $vastaus]);
    exit;
}

// --- 9. Uutiset — lähteen valinta ---
if (str_contains($input, 'uutis') || isset($_SESSION['odottaa_lahdetta'])) {
    // Määritellään RSS-syötteet
    $rss_lahteet = [
        'yle' => ['nimi' => 'Yle', 'url' => 'https://yle.fi/rss/uutiset/tuoreimmat'],
        'iltasanomat' => ['nimi' => 'Ilta-Sanomat', 'url' => 'https://www.is.fi/rss/tuoreimmat.xml'],
        'iltalehti' => ['nimi' => 'Iltalehti', 'url' => 'https://www.iltalehti.fi/rss/uutiset.xml'],
        'kaikki' => ['nimi' => 'Kaikki (High.fi)', 'url' => 'https://high.fi/uutiset/rss']
    ];

    $valittu_avain = null;
    foreach ($rss_lahteet as $avain => $tiedot) {
        if (str_contains($input, $avain)) {
            $valittu_avain = $avain;
            break;
        }
    }

    if ($input === 'uutiset' && !$valittu_avain) {
        $_SESSION['odottaa_lahdetta'] = true;
        echo json_encode(['reply' => "Mistä lähteestä haluaisit uutisia? (<b>Yle</b>, <b>IS</b>, <b>Iltalehti</b> tai <b>Kaikki</b>)"]);
        exit;
    }

    // Jos lähdettä ei tunnistettu, käytetään High.fi:tä oletuksena
    if (!$valittu_avain) $valittu_avain = 'kaikki';
    unset($_SESSION['odottaa_lahdetta']);

    $valittu = $rss_lahteet[$valittu_avain];

    // Haetaan RSS-data
    $ch = curl_init($valittu['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (UutisBotti/1.8)');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $rss_raw = curl_exec($ch);

    if ($rss_raw) {
        // RSS on XML-muodossa
        // PUHDISTUS: Poistetaan tyhjät välit ja mahdolliset merkit ennen <?xml -tunnistetta
        $rss_raw = trim($rss_raw);
        $alkuPos = strpos($rss_raw, '<?xml');
        if ($alkuPos !== false && $alkuPos > 0) {
            $rss_raw = substr($rss_raw, $alkuPos);
        }

        $xml = simplexml_load_string($rss_raw, 'SimpleXMLElement', LIBXML_NOCDATA);
        
        if ($xml && isset($xml->channel->item)) {
            $vastaus = "<b>{$valittu['nimi']} - Tuoreimmat:</b><br><ul style='padding-left:20px; margin-top:10px;'>";
            
            for ($i = 0; $i < 5; $i++) {
                if (!isset($xml->channel->item[$i])) break;
                
                $item = $xml->channel->item[$i];
                $title = (string)$item->title;
                $link = (string)$item->link;
                
                $vastaus .= "<li style='margin-bottom:8px;'><a href='{$link}' target='_blank'>{$title}</a></li>";
            }
            $vastaus .= "</ul>";
            echo json_encode(['reply' => $vastaus]);
        } else {
            echo json_encode(['reply' => "RSS-syötteen lukeminen epäonnistui. XML-muoto on virheellinen. 📰"]);
        }
    } else {
        echo json_encode(['reply' => "Yhteys uutispalvelimeen epäonnistui. 🌐"]);
    }
    exit;
}


// 9. Ohjeet
if (str_contains($input, 'ohjeet')) {
    $vastaus = "Tässä kaikki komennot:";
    echo json_encode(['reply' => $vastaus, 'avaa_ohjeet' => true]);
    exit;
}

// 11. Oletusvastaus jos mitään ei löydy
echo json_encode(['reply' => "En ihan ymmärtänyt. Kokeile sanoa 'uutiset' tai 'sää'."]);