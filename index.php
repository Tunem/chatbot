<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <title>UutisBotti</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div id="cookie-popup" class="cookie-banner" style="display:none;">
        <span>🍪 <b>UutisBotti käyttää evästeitä</b> parantaakseen kokemustasi ja muistaakseen nimesi (30 päivää).</span>
        <button onclick="suljeKeksi()" class="cookie-btn">Selvä!</button>
    </div>

    <div id="chat-window">
        <div id="chat-header">
            <span>🤖 UutisBotti AI</span>
            <button onclick="vaihdaTeema()" style="background:none; border:none; color:white; cursor:pointer; font-size:12px;">Vaihda tumma/vaalea tila🌓</button>
            <button onclick="document.getElementById('messages').innerHTML = ''" style="background:none; border:none; color:white; cursor:pointer; font-size:12px;">Tyhjennä</button>
        </div>
        
        <div id="messages">
            </div>
        
        <div id="quick-buttons">
            <button class="quick-btn" onclick="sendCommand('uutiset')">Uutiset</button>
            <button class="quick-btn" onclick="sendCommand('sää')">Sää</button>
            <button class="quick-btn" onclick="sendCommand('vitsi')">Vitsi</button>
            <button class="quick-btn" onclick="sendCommand('lause')">Päivän lause</button>
            <button class="quick-btn" onclick="sendCommand('unohda nimi')">Unohda nimi</button>
            <button class="quick-btn" onclick="sendCommand('vaihda nimi')">Vaihda nimi</button>
        </div>

        <div id="input-area">
            <input type="text" id="user-input" placeholder="Kysy jotain...">
            <button class="send-btn" onclick="lahetaViesti()">➤</button>
        </div>
    </div>

    <button id="changelog-btn" onclick="avaaLoki()">📜 Muutoshistoria</button>
    <button id="ohjeet-btn" onclick="avaaOhjeet()">📖 Ohjeet</button>

    <div id="loki-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="suljeLoki()">&times;</span>
            <h2>UutisBotti - Päivitykset</h2>
            <hr>
            <div class="loki-lista">
                <p><strong>v1.7 (16.3.2026)</strong><br>
                - <b>Dynaamiset vitsit</b>: Official Joke API integraatio.<br>
                - <b>Älykäs kääntäjä</b>: MyMemory API kääntää vitsit lennosta suomeksi.<br>
                - <b>Interaktiiviset napit</b>: Kielivalinta katoavilla napeilla.</p>

                <p><strong>v1.6 (12.3.2026)</strong><br>
                - <b>Kaupunki haku</b>: Säätiedot kaupunkien mukaan.<br>

                <p><strong>v1.5 (12.3.2026)</strong><br>
                - <b>Dark Mode</b>: Täysi tuki tummalle teemalle ja teemavalinnan pysyvä tallennus evästeisiin.<br>
                - <b>Koodin optimointi</b>: JavaScriptin suoritusjärjestys korjattu ja evästeiden hallinta vakautettu.</p>

                <p><strong>v1.4 (12.3.2026)</strong><br>
                - <b>Pysyvä muisti</b>: Botti tallentaa nimesi evästeisiin (cookies).<br>
                - <b>Tietosuoja</b>: Pop-up-ilmoitus evästeistä ja "unohda nimeni" -toiminto tietojen poistamiseen.<br>
                - <b>Hallinta</b>: Mahdollisuus vaihtaa nimi komennolla.<br>
                - <b>Henkilökohtainen palvelu</b>: Tervehdykset ja viestit personoidaan nimesi mukaan myös selaimen uudelleenkäynnistyksen jälkeen.<br>
                - <b>Muistitoiminto</b>: Botti tunnistaa ja muistaa käyttäjän nimen (30 päivää)👤<br>
                - Personoidut tervehdykset nimen perusteella</p>

                <p><strong>v1.3 (12.3.2026)</strong><br>
                - Päivän lause haetaan dynaamisesti ZenQuotes APIsta 💡<br>
                - Lataustekstien hallinta optimoitu Switch-rakenteella ⚙️<br>
                - Alkutervehdykseen integroitu ajatuslaatikko 🧠</p>

                <p><strong>v1.2 (11.3.2026)</strong><br>
                - Interaktiiviset uutiset: Botti kysyy lähdettä (Yle, IS, Iltalehti) 📰<br>
                - Säävertailu: Yr.no vs. FMI + tuulitiedot 💨<br>
                - Älykkäämpi keskustelulogiikka ja istunnot (Sessions) 🧠</p>

                <p><strong>v1.1 (11.3.2026)</strong><br>
                - Siirrytty uuteen sää-rajapintaan (JSON)<br>
                - Kellonaikaan perustuvat tervehdykset 🌅<br>
                - Lisätty vitsiautomaatti 🤡<br>
                - Dynaamiset lataustekstit ja vastausviive lisätty</p>

                <p><strong>v1.0 (10.3.2026)</strong><br>
                - Ensimmäinen julkaisu: Iltalehti RSS-haku perustoteutuksena</p>
            </div>
        </div>
    </div>

    <div id="ohjeet-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="suljeOhjeet()">&times;</span>
            <h2>📖 Komennot</h2>
            <hr>
            <div class="loki-lista">
                <p><strong>💬 Keskustelu</strong><br>
                <code>hei</code>, <code>moi</code>, <code>terve</code>, <code>huomenta</code> — Tervehdys kellonajan mukaan</p>

                <p><strong>📰 Uutiset</strong><br>
                <code>uutiset</code> — Botti kysyy lähdettä<br>
                <code>yle</code>, <code>is</code>, <code>iltalehti</code> — Lähteen valinta</p>

                <p><strong>🌤️ Sää</strong><br>
                <code>sää</code> — Botti kysyy kaupunkia<br>
                <code>sää Helsinki</code> — Suora haku</p>

                <p><strong>💡 Muut</strong><br>
                <code>vitsi</code> — Satunnainen vitsi<br>
                <code>lause</code> tai <code>motivaatio</code> — Päivän ajatus<br>
                <code>ohjeet</code> — Tämä lista</p>

                <p><strong>👤 Nimi</strong><br>
                <code>nimeni on [nimi]</code> — Tallenna nimi<br>
                <code>vaihda nimi</code> — Vaihda nimi<br>
                <code>unohda nimi</code> — Poista nimi ja eväste</p>

                <p><strong>🥚 Easter eggs</strong><br>
                <code>salaliitto</code>, <code>foliohattu</code>, <code>folio</code> — 🕵️ <br>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>