<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../databases/db.php';

$conn = getDB();

$sql = "SELECT id, name_en, name_np, phone 
        FROM sellers_locals 
        WHERE status='active' 
        ORDER BY name_en";

$sellers_result = $conn->query($sql);

if (!$sellers_result) {
    die("Query Failed: " . $conn->error);
}

$sellers_local = [];
while($row = $sellers_result->fetch_assoc()) {
    $sellers_local[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title>Seller Location App — Nepal</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@300;400;600;700&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--red:#c0392b;--blue:#1a3a5c;--white:#f7f3ee;--gold:#d4a017;--dark:#0f1e2e;--green:#27ae60;--border:#d0cbc4;--text:#2c3e50;--muted:#7f8c8d;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Mukta',sans-serif;background:#f0f4f8;color:var(--text);min-height:100vh;}

/* Mobile-first design for sellers */
.seller-app{max-width:480px;margin:0 auto;padding:16px;min-height:100vh;display:flex;flex-direction:column;gap:14px;}

.s-header{background:linear-gradient(135deg,var(--dark),var(--blue));color:#fff;border-radius:14px;padding:18px 20px;display:flex;align-items:center;gap:12px;}
.s-header .flag{font-size:2rem;}
.s-header-text h1{font-family:'Rajdhani',sans-serif;font-size:1.3rem;font-weight:700;color:var(--gold);}
.s-header-text p{font-size:0.78rem;opacity:0.6;margin-top:2px;}

.card{background:#fff;border-radius:14px;box-shadow:0 3px 14px rgba(0,0,0,0.08);border:1px solid var(--border);overflow:hidden;}
.card-head{background:var(--blue);color:#fff;padding:12px 16px;font-family:'Rajdhani',sans-serif;font-size:0.95rem;font-weight:600;display:flex;align-items:center;gap:7px;}

/* Login section */
.login-body{padding:18px;}
.fg{margin-bottom:14px;}
.fg label{display:block;font-size:0.82rem;font-weight:600;color:var(--blue);margin-bottom:5px;}
.fg select,.fg input{width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'Mukta',sans-serif;font-size:0.9rem;outline:none;background:#fafafa;}
.fg select:focus,.fg input:focus{border-color:var(--blue);background:#fff;}

.btn-start{width:100%;padding:13px;background:linear-gradient(135deg,var(--green),#1e8449);color:#fff;border:none;border-radius:10px;font-family:'Rajdhani',sans-serif;font-size:1.05rem;font-weight:700;cursor:pointer;letter-spacing:0.5px;transition:transform 0.15s,box-shadow 0.15s;}
.btn-start:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(39,174,96,0.4);}
.btn-stop{background:linear-gradient(135deg,var(--red),#a93226);}
.btn-stop:hover{box-shadow:0 4px 14px rgba(192,57,43,0.4);}

/* Status card */
.status-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;}
.stat-cell{padding:14px 16px;border-right:1px solid var(--border);border-bottom:1px solid var(--border);}
.stat-cell:nth-child(2n){border-right:none;}
.stat-cell:nth-child(3),.stat-cell:nth-child(4){border-bottom:none;}
.stat-val{font-family:'Rajdhani',sans-serif;font-size:1.3rem;font-weight:700;color:var(--blue);line-height:1.1;}
.stat-lbl{font-size:0.7rem;color:var(--muted);margin-top:2px;}

/* Live indicator */
.live-strip{background:#f0fff4;border:1px solid #a9dfbf;border-radius:10px;padding:11px 14px;display:flex;align-items:center;gap:10px;}
.live-strip.off{background:#fff5f5;border-color:#f5b7b1;}
.live-dot{width:10px;height:10px;border-radius:50%;background:var(--green);animation:blink 1.2s infinite;flex-shrink:0;}
.live-dot.off{background:#e74c3c;animation:none;}
.live-text{font-size:0.85rem;font-weight:600;color:var(--green);}
.live-text.off{color:var(--red);}
.live-sub{font-size:0.72rem;color:var(--muted);margin-top:1px;}

/* Map */
#seller-map{height:280px;width:100%;}

/* Log */
.log-body{padding:12px 16px;max-height:160px;overflow-y:auto;}
.log-entry{font-size:0.75rem;padding:4px 0;border-bottom:1px solid #f5f5f5;color:var(--text);display:flex;gap:8px;}
.log-time{color:var(--muted);flex-shrink:0;font-family:'Rajdhani',sans-serif;}
.log-msg.ok{color:var(--green);}
.log-msg.err{color:var(--red);}
.log-msg.info{color:var(--blue);}

@keyframes blink{0%,100%{opacity:1}50%{opacity:0.3}}

/* Accuracy meter */
.acc-bar{height:5px;background:#eee;border-radius:3px;margin-top:4px;overflow:hidden;}
.acc-fill{height:100%;border-radius:3px;background:var(--green);transition:width 0.5s;}
</style>
</head>
<body>
<div class="seller-app">

  <!-- Header -->
  <div class="s-header">
    <span class="flag">📍</span>
    <div class="s-header-text">
      <h1>SELLER LOCATION APP</h1>
      <p>Share your live GPS location with admin</p>
    </div>
  </div>

  <!-- Login / Select Seller -->
  <div class="card" id="login-card">
    <div class="card-head">👤 Select Your Profile</div>
    <div class="login-body">
      <div class="fg">
        <label>Your Name</label>
        <select id="sel-seller">
          <option value="">— Select your name —</option>
          <?php foreach($sellers_locals as $s): ?>
          <option value="<?=$s['id']?>" data-name="<?=htmlspecialchars($s['name_en'])?>" data-phone="<?=htmlspecialchars($s['phone'])?>">
            <?=htmlspecialchars($s['name_en'])?> — <?=htmlspecialchars($s['name_np'])?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg">
        <label>Phone (verify)</label>
        <input type="tel" id="inp-phone" placeholder="98XXXXXXXX">
      </div>
      <button class="btn-start" onclick="startTracking()">🟢 Start Sharing Location</button>
    </div>
  </div>

  <!-- Live Status -->
  <div id="tracking-section" style="display:none;flex-direction:column;gap:14px;">

    <!-- Live indicator -->
    <div class="live-strip" id="live-strip">
      <div class="live-dot" id="live-dot"></div>
      <div>
        <div class="live-text" id="live-text">● Location Sharing Active</div>
        <div class="live-sub" id="live-sub">Waiting for GPS fix...</div>
      </div>
      <div style="margin-left:auto">
        <button class="btn-start btn-stop" style="padding:8px 14px;font-size:0.82rem;border-radius:8px;" onclick="stopTracking()">■ Stop</button>
      </div>
    </div>

    <!-- Stats -->
    <div class="card">
      <div class="card-head">📊 Location Stats</div>
      <div class="status-grid">
        <div class="stat-cell">
          <div class="stat-val" id="st-lat">—</div>
          <div class="stat-lbl">Latitude</div>
        </div>
        <div class="stat-cell">
          <div class="stat-val" id="st-lng">—</div>
          <div class="stat-lbl">Longitude</div>
        </div>
        <div class="stat-cell">
          <div class="stat-val" id="st-acc">—</div>
          <div class="stat-lbl">Accuracy (m)</div>
          <div class="acc-bar"><div class="acc-fill" id="acc-fill" style="width:0%"></div></div>
        </div>
        <div class="stat-cell">
          <div class="stat-val" id="st-updates">0</div>
          <div class="stat-lbl">Updates Sent</div>
        </div>
      </div>
    </div>

    <!-- Map -->
    <div class="card">
      <div class="card-head">🗺️ My Current Location</div>
      <div id="seller-map"></div>
    </div>

    <!-- Activity Log -->
    <div class="card">
      <div class="card-head">📋 Activity Log</div>
      <div class="log-body" id="log-body">
        <div class="log-entry"><span class="log-time">—</span><span class="log-msg info">Waiting to start...</span></div>
      </div>
    </div>

  </div>

</div><!-- seller-app -->

<script>
let watchId = null;
let sellerId = null;
let sellerName = '';
let updateCount = 0;
let map = null;
let myMarker = null;
let myCircle = null;

function startTracking() {
  const sel = document.getElementById('sel-seller');
  const phone = document.getElementById('inp-phone').value.trim();
  sellerId = parseInt(sel.value);
  sellerName = sel.options[sel.selectedIndex]?.dataset.name || '';
  const expectedPhone = sel.options[sel.selectedIndex]?.dataset.phone || '';

  if (!sellerId) { alert('Please select your name'); return; }
  if (!phone) { alert('Please enter your phone number'); return; }
  if (phone !== expectedPhone) { alert('Phone number does not match our records!'); return; }

  if (!navigator.geolocation) { alert('Geolocation not supported on this device'); return; }

  document.getElementById('login-card').style.display = 'none';
  document.getElementById('tracking-section').style.display = 'flex';

  addLog('info', `Started tracking for ${sellerName}`);

  // Init map
  map = L.map('seller-map', { zoomControl: true, attributionControl: false });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
  map.setView([28.3, 84.0], 7);

  // Start watching
  watchId = navigator.geolocation.watchPosition(
    onPosition,
    onGeoError,
    { enableHighAccuracy: true, maximumAge: 10000, timeout: 15000 }
  );
}

function onPosition(pos) {
  const lat = pos.coords.latitude;
  const lng = pos.coords.longitude;
  const acc = Math.round(pos.coords.accuracy);
  const speed = pos.coords.speed || 0;
  const heading = pos.coords.heading || 0;

  // Update stats
  document.getElementById('st-lat').textContent  = lat.toFixed(5);
  document.getElementById('st-lng').textContent  = lng.toFixed(5);
  document.getElementById('st-acc').textContent  = acc + 'm';
  document.getElementById('st-updates').textContent = ++updateCount;

  // Accuracy bar (green if < 20m)
  const accPct = Math.max(0, Math.min(100, 100 - (acc/50*100)));
  document.getElementById('acc-fill').style.width = accPct + '%';
  document.getElementById('acc-fill').style.background = acc < 20 ? '#27ae60' : acc < 50 ? '#f39c12' : '#e74c3c';

  // Map marker
  if (!myMarker) {
    myMarker = L.marker([lat, lng], {
      icon: L.divIcon({
        className: '',
        html: `<div style="background:#c0392b;width:22px;height:22px;border-radius:50%;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.5);animation:pulseM 1.2s infinite;"></div>`,
        iconSize: [22,22], iconAnchor:[11,11]
      })
    }).addTo(map).bindPopup(`<b>📍 ${sellerName}</b><br>Accuracy: ${acc}m`);
    myCircle = L.circle([lat, lng], { radius: acc, color:'#3498db', fillOpacity:0.1, weight:1 }).addTo(map);
    map.setView([lat, lng], 14);
  } else {
    myMarker.setLatLng([lat, lng]);
    myCircle.setLatLng([lat, lng]);
    myCircle.setRadius(acc);
  }

  // Update live strip
  document.getElementById('live-sub').textContent =
    `Last fix: ${new Date().toLocaleTimeString()} · Accuracy: ${acc}m`;

  // Send to server
  sendLocation(lat, lng, acc, speed, heading);
}

function sendLocation(lat, lng, acc, speed, heading) {
  const fd = new FormData();
  fd.append('action', 'update_location');
  fd.append('seller_id', sellerId);
  fd.append('lat', lat);
  fd.append('lng', lng);
  fd.append('accuracy', acc);
  fd.append('speed', speed);
  fd.append('heading', heading);

  fetch('api_locations.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        addLog('ok', `Location sent ✓ — ${lat.toFixed(4)}, ${lng.toFixed(4)}`);
      } else {
        addLog('err', 'Server error: ' + (d.msg||'unknown'));
      }
    })
    .catch(() => addLog('err', 'Network error — will retry'));
}

function onGeoError(err) {
  const msgs = {1:'Permission denied',2:'Position unavailable',3:'GPS timeout'};
  addLog('err', 'GPS error: ' + (msgs[err.code]||err.message));
  document.getElementById('live-dot').classList.add('off');
  document.getElementById('live-text').classList.add('off');
  document.getElementById('live-text').textContent = '● GPS Error';
}

function stopTracking() {
  if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
  addLog('info', 'Location sharing stopped.');
  document.getElementById('live-dot').classList.add('off');
  document.getElementById('live-text').classList.add('off');
  document.getElementById('live-strip').classList.add('off');
  document.getElementById('live-text').textContent = '■ Sharing Stopped';
}

function addLog(type, msg) {
  const lb = document.getElementById('log-body');
  const entry = document.createElement('div');
  entry.className = 'log-entry';
  entry.innerHTML = `<span class="log-time">${new Date().toLocaleTimeString()}</span><span class="log-msg ${type}">${msg}</span>`;
  lb.insertBefore(entry, lb.firstChild);
  // Trim log
  while(lb.children.length > 50) lb.removeChild(lb.lastChild);
}

const sty = document.createElement('style');
sty.textContent = `@keyframes pulseM{0%{transform:scale(1)}50%{transform:scale(1.3)}100%{transform:scale(1)}}`;
document.head.appendChild(sty);
</script>
</body>
</html>