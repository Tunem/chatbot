// ===== APUFUNKTIOT =====

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

function lisaaViesti(teksti, tyyppi) {
    const div = document.createElement('div');
    div.className = 'msg ' + tyyppi;
    div.innerHTML = teksti;
    const msgContainer = document.getElementById('messages');
    msgContainer.appendChild(div);
    msgContainer.scrollTop = msgContainer.scrollHeight;
}

// Lisää tämä funktio muiden joukkoon
function valitseVitsiKieli(kieli) {
    // Poistetaan napit näkyvistä
    const nappiAlue = document.getElementById('temp-joke-buttons');
    if (nappiAlue) nappiAlue.remove();
    
    // Lähetetään valinta botille (piilotettu komento)
    document.getElementById('user-input').value = 'vitsi_' + kieli;
    lahetaViesti();
}

// ===== TEEMA =====

function vaihdaTeema() {
    const isDark = document.body.classList.toggle('dark-theme');
    document.cookie = "teema=" + (isDark ? "dark" : "light") + "; max-age=" + (86400 * 30) + "; path=/";
}

// ===== MODALIT =====

function suljeKeksi() {
    document.getElementById('cookie-popup').style.display = 'none';
}

function avaaLoki() { document.getElementById("loki-modal").style.display = "block"; }
function suljeLoki() { document.getElementById("loki-modal").style.display = "none"; }

function avaaOhjeet() { document.getElementById("ohjeet-modal").style.display = "block"; }
function suljeOhjeet() { document.getElementById("ohjeet-modal").style.display = "none"; }

window.onclick = function(event) {
    if (event.target == document.getElementById("loki-modal")) suljeLoki();
    if (event.target == document.getElementById("ohjeet-modal")) suljeOhjeet();
}

// ===== PIKAKOMENTONAPPI =====

function sendCommand(komento) {
    document.getElementById('user-input').value = komento;
    lahetaViesti();
}

// ===== VIESTIN LÄHETYS =====

async function lahetaViesti() {
    const input = document.getElementById('user-input');
    const teksti = input.value.trim();
    if (!teksti) return;

    lisaaViesti(teksti, 'user');
    input.value = '';

    const pieniTeksti = teksti.toLowerCase();
    let latausTeksti = "Botti miettii...";

    switch (true) {
        case pieniTeksti.startsWith("sää"):         latausTeksti = "Haetaan sääviestiä..."; break;
        case pieniTeksti.includes("vitsi"):         latausTeksti = "Muistellaan vitsiä..."; break;
        case pieniTeksti.includes("uutis"):         latausTeksti = "Etsitään tuoreimpia uutisia..."; break;
        case pieniTeksti.includes("yle"):           latausTeksti = "Haetaan Ylen uutisia..."; break;
        case pieniTeksti.includes("is"):            latausTeksti = "Ladataan Ilta-Sanomia..."; break;
        case pieniTeksti.includes("il"):            latausTeksti = "Ladataan Iltalehteä..."; break;
        case pieniTeksti.includes("lause"):         latausTeksti = "Hakee päivän ajatusta..."; break;
        case pieniTeksti.includes("salaliitto"):    latausTeksti = "Kokoonnutaan salaliittoteorioiden äärelle..."; break;
        case pieniTeksti.includes("nimeni on"):
        case pieniTeksti.includes("olen "):         latausTeksti = "Tallennetaan nimesi muistiin..."; break;
        case pieniTeksti.includes("unohda"):        latausTeksti = "Tyhjennetään muistia ja evästeitä..."; break;
        case pieniTeksti.includes("vaihda"):        latausTeksti = "Valmistellaan nimen vaihtoa..."; break;
        case pieniTeksti.includes("ohjeet"):        latausTeksti = "Avataan ohjeet..."; break;
    }

    const latausId = "typing-indicator";
    const msgContainer = document.getElementById('messages');
    const latausDiv = document.createElement('div');
    latausDiv.className = 'msg bot';
    latausDiv.id = latausId;
    latausDiv.innerHTML = `<div style="margin-bottom: 5px;">${latausTeksti}</div><div class="typing"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div>`;
    msgContainer.appendChild(latausDiv);
    msgContainer.scrollTop = msgContainer.scrollHeight;

    try {
        const formData = new FormData();
        formData.append('message', teksti);
        const vastaus = await fetch('bot.php', { method: 'POST', body: formData });
        const data = await vastaus.json();
        if (document.getElementById(latausId)) document.getElementById(latausId).remove();
        lisaaViesti(data.reply, 'bot');

        // --- UUSI LOGIIKKA: VITSI-NAPIT ---
        if (data.vitsi_napit) {
            const msgContainer = document.getElementById('messages');

            const nappiKupla = document.createElement('div');
            nappiKupla.id = 'temp-joke-buttons';
            nappiKupla.className = 'msg bot'; // Käytetään samoja tyylejä kuin botin viesteissä
            nappiKupla.style.textAlign = 'center';
            nappiKupla.style.marginTop = '5px';
            
            // Lisätään napit kuplan sisälle
            nappiKupla.innerHTML = `
                <div style="margin-bottom: 10px; font-size: 0.9em; opacity: 0.8;">Valitse kieli:</div>
                <button class="quick-btn" onclick="valitseVitsiKieli('fi')" style="margin: 2px;">🇫🇮 Suomeksi</button>
                <button class="quick-btn" onclick="valitseVitsiKieli('en')" style="margin: 2px;">🇬🇧 Englanniksi</button>
            `;
            
            msgContainer.appendChild(nappiKupla);
            msgContainer.scrollTop = msgContainer.scrollHeight;
        }

        if (data.avaa_ohjeet) avaaOhjeet();
    } catch (error) {
        if (document.getElementById(latausId)) document.getElementById(latausId).remove();
        lisaaViesti("Hups! Jotain meni pieleen.", 'bot');
    }
}

// ===== SIVUN LATAUS =====

window.onload = function() {
    if (getCookie('teema') === 'dark') {
        document.body.classList.add('dark-theme');
    }

    const nimi = getCookie('botti_nimi');
    if (!nimi) {
        const popup = document.getElementById('cookie-popup');
        if (popup) popup.style.display = 'flex';
    }

    const tunti = new Date().getHours();
    let tervehdys = "Hei!";
    if (tunti < 10)       tervehdys = "Hyvää huomenta! 🌅";
    else if (tunti < 17)  tervehdys = "Hyvää päivää! ☀️";
    else if (tunti < 22)  tervehdys = "Hyvää iltaa! 🌙";
    else                  tervehdys = "Hyvää yötä! 🌌";

    const nimiLisa = nimi ? " " + decodeURIComponent(nimi) : "";
    lisaaViesti(`${tervehdys}${nimiLisa}, olen UutisBotti.`, 'bot');

    fetch('hae-lause.php')
        .then(response => response.json())
        .then(data => {
            const lauseHtml = `
                Tässä päivän ajatus sinulle:<br><br>
                <blockquote style="border-left: 3px solid #0084ff; padding-left: 10px; font-style: italic; margin: 0;">
                    "${data.teksti}"<br>
                    <small>— ${data.tekija}</small>
                </blockquote><br>
                Miten voin auttaa? (uutiset, sää tai vitsi)`;
            lisaaViesti(lauseHtml, 'bot');
        })
        .catch(() => {
            lisaaViesti("Miten voin auttaa tänään?", 'bot');
        });
};

// Enter-näppäimen kuuntelu
document.getElementById('user-input').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') lahetaViesti();
});