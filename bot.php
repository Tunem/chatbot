<?php
// ============================================================
// bot.php — UutisBotti
// ============================================================

// === ALUSTUS ===
session_start(); // TÄRKEÄ: Mahdollistaa botin "muistin"
header('Content-Type: application/json');
date_default_timezone_set('Europe/Helsinki'); // Varmistetaan oikea aika

// Luetaan viesti POST-pyynnöstä. Jos viestiä ei ole, käytetään tyhjää merkkijonoa.
$input = $_POST['message'] ?? '';

// === Puhdistetaan syöte ja määritellään komennot ===
$input_clean = mb_strtolower($input);
// Määritellään pääkategoriat tunnistusta varten
$on_uutiset = preg_match('/\buutis/i', $input_clean);
$on_saa = preg_match('/\bsää\b/i', $input_clean); // \b estää esim. "lisää" sanan sekoittumisen
$on_vitsi = preg_match('/\bvitsi\b/i', $input_clean);
$on_lause = preg_match('/\b(lause|motivaatio)\b/i', $input_clean);

// LASKURI: Kuinka monta eri komentoa viestissä on?
$komentoja_yhteensa = (int)$on_uutiset + (int)$on_saa + (int)$on_vitsi + (int)$on_lause;

// ESTO: Jos viestissä on liikaa eri tyyppisiä pyyntöjä
if ($komentoja_yhteensa > 1) {
    echo json_encode(['reply' => "Hups! Autoitko vähän: haluatko uutisia, säätä vai vitsin? Tehdään yksi asia kerrallaan. 😊"]);
    exit;
}

// Jos viesti on yli 100 merkkiä, se on luultavasti spämmiä tai liian monimutkainen
if (mb_strlen($input) > 100) {
    echo json_encode(['reply' => "Viestisi on vähän pitkä, en valitettavasti osaa lukea tarinoita vielä! Kokeile lyhyempää komentoa. 📝"]);
    exit;
}

// Estetään kielletyt merkit (esim. koodin syöttöyritykset)
if (preg_match('/[<>{}[\]]/', $input)) {
    echo json_encode(['reply' => "Käytit erikoismerkkejä, joita en ymmärrä. Pysytään tekstissä! 🚫"]);
    exit;
}

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
    echo json_encode(['reply' => "Mukava tutustua, $nimi! Muistan nimesi jatkossa 😊"]);
    exit;
}

// 2. Nimen unohdus
if (preg_match('/\bunohda\b/i', $input) && preg_match('/\bnimi\b/i', $input)) {
    unset($_SESSION['kayttaja_nimi']);
    // Poistetaan eväste asettamalla sen erääntymisaika menneisyyteen
    setcookie('botti_nimi', '', time() - 3600, "/");
    echo json_encode(['reply' => "Selvä, olen unohtanut nimesi ja poistanut evästeet. Voit kertoa uuden nimesi milloin vain! 🗑️"]);
    exit;
}

// 3. Nimen vaihto (ohjaa käyttäjää)
if (preg_match('/\bvaihda\b/i', $input) && preg_match('/\bnimi\b/i', $input)) {
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

// 4. Tervehdykset (Käytetään säännöllistä lausetta, joka vaatii sanan alun tai kokonaisen sanan)
if (preg_match('/^(hei|moi|terve|huomenta|iltaa)\b/i', $input)) {
    $nimi_lisa = $kayttaja ? " " . $kayttaja : "";
    $tunti = (int)date('H');
    if ($tunti >= 5 && $tunti < 10) $tervehdys = "Hyvää huomenta$nimi_lisa! 🌅";
    elseif ($tunti >= 10 && $tunti < 17) $tervehdys = "Hyvää päivää$nimi_lisa! ☀️";
    elseif ($tunti >= 17 && $tunti < 22) $tervehdys = "Hyvää iltaa$nimi_lisa! 🌙";
    else $tervehdys = "Hyvää yötä$nimi_lisa! 🌌";

    echo json_encode(['reply' => "$tervehdys Olen UutisBotti. Miten voin auttaa?"]);
    exit;
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
if ($on_uutiset) {
    // Sanoja, jotka haluamme poistaa hakusanasta, jotta ne eivät mene Google-hakuun
    // Huom: Nyt mukana myös "hei", "moi" jne. sananrajoilla (\b)
    $roskasanoja = [
        '/\buutiset\b/iu', '/\buutisia\b/iu', '/\bkerro\b/iu', 
        '/\baiheesta\b/iu', '/\baihe\b/iu', '/\bhei\b/iu', 
        '/\bmoi\b/iu', '/\bterve\b/iu', '/\bkiitos\b/iu'
    ];
    // Puhdistetaan hakusana säännöllisillä lausekkeilla
    $hakusana = trim(preg_replace($roskasanoja, '', $input_clean));

    // Määritellään RSS-syötteet
    $rss_lahteet = [
        'yle' => 'https://yle.fi/rss/uutiset/tuoreimmat',
        'is' => 'https://www.is.fi/rss/tuoreimmat.xml',
        'iltasanomat' => 'https://www.is.fi/rss/tuoreimmat.xml',
        'iltalehti' => 'https://www.iltalehti.fi/rss/uutiset.xml'
    ];

    $valittu_url = "";
    $valittu_nimi = "";

    // 1. Tarkistetaan, haluaako käyttäjä tietyn lähteen
    foreach ($rss_lahteet as $avain => $url) {
        if (preg_match("/\b$avain\b/i", $input_clean)) {
            $valittu_url = $url;
            $valittu_nimi = ucfirst($avain);
            // Poistetaan myös lähteen nimi hakusanasta, jos se jäi jäljelle
            $hakusana = trim(preg_replace("/\b$avain\b/i", '', $hakusana));
            break;
        }
    }

    // Jos hakusana on edelleen olemassa ja se ei ole tyhjä, käytetään Google Newsia
    if (empty($valittu_url)) {
        $q = empty($hakusana) ? "Suomi" : $hakusana;
        $valittu_url = "https://news.google.com/rss/search?q=" . urlencode($q) . "&hl=fi&gl=FI&ceid=FI:fi";
        $valittu_nimi = ($q === "Suomi") ? "Pääuutiset" : "Haku: " . ucfirst($q);
    }

    unset($_SESSION['odottaa_lahdetta']);

    // Haetaan RSS-data
    $ch = curl_init($valittu_url);
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
            $vastaus = "<b>{$valittu_nimi} - Tuoreimmat:</b><br><ul style='padding-left:20px; margin-top:10px;'>";
            
            $count = 0;
            foreach ($xml->channel->item as $item) {
                if ($count >= 5) break;

                $full_title = (string)$item->title;
                $link = (string)$item->link;

                // Siistitään otsikko vain, jos se tulee Google Newsistä (sisältää " - Lähde")
                if (str_contains($valittu_url, 'google.com')) {
                    $parts = explode(' - ', $full_title);
                    $lahde_nimi = (count($parts) > 1) ? array_pop($parts) : "";
                    $otsikko = implode(' - ', $parts);
                } else {
                    $otsikko = $full_title;
                    $lahde_nimi = $valittu_nimi;
                }
                
                $vastaus .= "<li style='margin-bottom:10px;'>";
                $vastaus .= "<a href='{$link}' target='_blank' style='text-decoration:none; font-weight:bold;'>{$otsikko}</a>";
                if ($lahde_nimi) {
                    $vastaus .= "<br><small style='color:#888;'>Lähde: {$lahde_nimi}</small>";
                }
                $vastaus .= "</li>";
                $count++;
            }
            $vastaus .= "</ul>";
            echo json_encode(['reply' => $vastaus]);
        } else {
            echo json_encode(['reply' => "Syötteen lukeminen epäonnistui lähteestä $valittu_nimi."]);
        }
    } else {
        echo json_encode(['reply' => "Yhteys uutispalveluun epäonnistui. Yritä myöhemmin uudestaan."]);
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