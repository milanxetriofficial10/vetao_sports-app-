<?php
require_once '../databases/db.php';
// Fetch all active sellers
$sellers_result = $conn->query("SELECT id, name_en, name_np, phone, district_name, province, lat, lng, status FROM sellers WHERE status='active' ORDER BY name_en");
$sellers = [];
while($row = $sellers_result->fetch_assoc()) $sellers[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Live Track Map — Nepal Admin</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@300;400;600;700&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--red:#c0392b;--blue:#1a3a5c;--white:#f7f3ee;--gold:#d4a017;--dark:#0f1e2e;--card:#fff;--border:#d0cbc4;--text:#2c3e50;--muted:#7f8c8d;--shadow:0 4px 20px rgba(0,0,0,0.1);}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Mukta',sans-serif;background:#f0f4f8;color:var(--text);}
.page-content{padding:20px;}
.track-grid{display:grid;grid-template-columns:300px 1fr;gap:18px;height:calc(100vh - 130px);}
@media(max-width:900px){.track-grid{grid-template-columns:1fr;height:auto;}}
/* Seller List Panel */
.seller-list-panel{background:var(--card);border-radius:12px;box-shadow:var(--shadow);border:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;}
.panel-head{background:var(--blue);color:#fff;padding:13px 16px;font-family:'Rajdhani',sans-serif;font-size:1rem;font-weight:600;display:flex;align-items:center;gap:6px;}
.live-dot{width:8px;height:8px;border-radius:50%;background:#2ecc71;animation:blink 1.2s infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0.3}}
.refresh-info{font-size:0.7rem;color:rgba(255,255,255,0.5);margin-left:auto;}
.seller-search{padding:10px 12px;border-bottom:1px solid var(--border);}
.seller-search input{width:100%;padding:7px 10px;border:1.5px solid var(--border);border-radius:7px;font-size:0.85rem;outline:none;}
.seller-search input:focus{border-color:var(--blue);}
.seller-items{overflow-y:auto;flex:1;}
.s-item{padding:11px 14px;border-bottom:1px solid #f0f0f0;cursor:pointer;transition:background 0.15s;display:flex;align-items:center;gap:10px;}
.s-item:hover,.s-item.active{background:#e8f0f8;}
.s-avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.95rem;font-weight:700;color:#fff;flex-shrink:0;}
.s-info{flex:1;min-width:0;}
.s-name{font-weight:600;font-size:0.88rem;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.s-sub{font-size:0.72rem;color:var(--muted);margin-top:1px;}
.s-status{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.s-status.online{background:#27ae60;}
.s-status.offline{background:#bdc3c7;}
/* Map Panel */
.map-panel{background:var(--card);border-radius:12px;box-shadow:var(--shadow);border:1px solid var(--border);overflow:hidden;display:flex;flex-direction:column;}
#track-map{flex:1;min-height:400px;}
.map-controls{padding:10px 16px;background:#f8f9fa;border-top:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.ctrl-btn{padding:6px 14px;border:1.5px solid var(--border);background:#fff;border-radius:20px;font-size:0.78rem;font-weight:600;cursor:pointer;color:var(--blue);transition:all 0.15s;font-family:'Rajdhani',sans-serif;}
.ctrl-btn:hover,.ctrl-btn.active{background:var(--blue);color:#fff;border-color:var(--blue);}
.ctrl-btn.danger{border-color:var(--red);color:var(--red);}
.ctrl-btn.danger:hover{background:var(--red);color:#fff;}
.map-status{font-size:0.78rem;color:var(--muted);margin-left:auto;}
/* Selected seller info bar */
.seller-info-bar{padding:11px 16px;background:linear-gradient(90deg,#e8f0f8,#f0f4f8);border-top:1px solid var(--border);font-size:0.82rem;color:var(--blue);display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.seller-info-bar strong{color:var(--red);}
.info-chip{background:#fff;border:1px solid var(--border);border-radius:20px;padding:3px 10px;font-size:0.75rem;}
/* Route colors */
.route-legend{display:flex;gap:8px;flex-wrap:wrap;}
.r-leg{display:flex;align-items:center;gap:4px;font-size:0.72rem;color:var(--muted);}
.r-dot{width:12px;height:4px;border-radius:2px;}
</style>
</head>
<body>
<div class="app-wrapper">
<?php include 'sidenav.php'; ?>
<div class="main-content">
<div class="topbar">
  <div class="topbar-inner">
    <button class="hamburger" onclick="openNav()">☰</button>
    <div>
      <div class="topbar-title">LIVE SELLER TRACKING MAP</div>
      <div class="topbar-sub">Real-time GPS location of all sellers — refreshes every 15s</div>
    </div>
    <div class="topbar-right">
      <div class="live-dot" style="width:10px;height:10px;border-radius:50%;background:#2ecc71;animation:blink 1.2s infinite;"></div>
      <span style="font-size:0.8rem;opacity:0.8;">LIVE</span>
      <a href="admin_form.php" style="color:#d4a017;font-size:0.82rem;text-decoration:none;margin-left:8px;">+ Add Seller</a>
    </div>
  </div>
</div>

<div class="page-content">
<div class="track-grid">

  <!-- SELLER LIST -->
  <div class="seller-list-panel">
    <div class="panel-head">
      <div class="live-dot"></div> Active Sellers
      <span class="refresh-info" id="last-refresh">—</span>
    </div>
    <div class="seller-search">
      <input type="text" id="s-search" placeholder="🔍 Search seller..." oninput="filterSellers()">
    </div>
    <div class="seller-items" id="seller-items">
      <?php
      $colors = ['#e74c3c','#e67e22','#27ae60','#2980b9','#8e44ad','#16a085','#d35400','#c0392b','#2c3e50'];
      foreach($sellers as $i => $s): ?>
      <div class="s-item" id="si-<?=$s['id']?>" onclick="selectSeller(<?=$s['id']?>)"
           data-name="<?=htmlspecialchars(strtolower($s['name_en'].' '.$s['name_np']))?>">
        <div class="s-avatar" style="background:<?=$colors[$i%count($colors)]?>">
          <?=strtoupper(substr($s['name_en'],0,1))?>
        </div>
        <div class="s-info">
          <div class="s-name"><?=htmlspecialchars($s['name_en'])?></div>
          <div class="s-sub"><?=htmlspecialchars($s['name_np'])?> · <?=htmlspecialchars($s['phone'])?></div>
          <div class="s-sub" style="color:#666"><?=htmlspecialchars($s['district_name']??'—')?></div>
        </div>
        <div class="s-status online" id="st-<?=$s['id']?>" title="Online"></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- MAP -->
  <div class="map-panel">
    <div class="panel-head">
      🗺️ Live Tracking Map
      <span class="refresh-info">Click seller to view route &amp; zoom</span>
    </div>
    <div id="track-map"></div>
    <div class="seller-info-bar" id="sel-info-bar">
      👆 Click a seller from the list to see their live location and route on the map
    </div>
    <div class="map-controls">
      <button class="ctrl-btn" onclick="showAllSellers()">👥 Show All</button>
      <button class="ctrl-btn" id="btn-route" onclick="toggleRoute()" style="display:none">📍 Show Route</button>
      <button class="ctrl-btn" id="btn-fitnepal" onclick="fitNepal()">🇳🇵 Nepal View</button>
      <div class="route-legend" id="route-legend"></div>
      <span class="map-status" id="map-status">Auto-refresh: ON</span>
    </div>
  </div>

</div>
</div>
</div>
</div>

<script>
const allSellers = <?=json_encode($sellers)?>;
const selColors = ['#e74c3c','#e67e22','#27ae60','#2980b9','#8e44ad','#16a085','#d35400','#c0392b','#2c3e50'];

const map = L.map('track-map',{center:[28.3,84.0],zoom:7});
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap'}).addTo(map);
map.fitBounds([[26.3,80.0],[30.5,88.2]]);

const liveMarkers = {}; // seller_id -> L.marker
const routeLines = {};  // seller_id -> L.polyline
let selectedSellerId = null;
let showRoute = false;

// ── Build initial markers ─────────────────────────────────────────────────
allSellers.forEach((s,i) => {
  if (!s.lat || !s.lng) return;
  const color = selColors[i % selColors.length];
  const icon = makeIcon(s.name_en, color, false);
  const mk = L.marker([s.lat, s.lng], { icon })
    .addTo(map)
    .bindPopup(popupHTML(s));
  liveMarkers[s.id] = { marker: mk, color, seller: s };
});

function makeIcon(name, color, selected) {
  const sz = selected ? 44 : 36;
  return L.divIcon({
    className: '',
    html: `<div style="
      background:${color};
      width:${sz}px;height:${sz}px;
      border-radius:50%;
      border:${selected?'3px solid #fff':'2px solid rgba(255,255,255,0.8)'};
      box-shadow:0 2px 8px rgba(0,0,0,0.4);
      display:flex;align-items:center;justify-content:center;
      font-size:${selected?'1rem':'0.75rem'};font-weight:700;color:white;
      ${selected?'animation:pulseM 1.2s infinite;':''}
      cursor:pointer;
    ">${name.charAt(0).toUpperCase()}</div>`,
    iconSize: [sz, sz],
    iconAnchor: [sz/2, sz/2]
  });
}

function popupHTML(s) {
  return `<div style="font-family:'Mukta',sans-serif;min-width:160px;">
    <div style="font-weight:700;font-size:0.95rem;color:#1a3a5c">${s.name_en}</div>
    <div style="font-size:0.8rem;color:#666;margin-top:2px">${s.name_np}</div>
    <hr style="margin:6px 0;border-color:#eee">
    <div style="font-size:0.78rem">📞 ${s.phone}</div>
    <div style="font-size:0.78rem">📍 ${s.district_name||'—'}</div>
    <div style="font-size:0.78rem">🕐 <span id="popup-time-${s.id}">Fetching...</span></div>
  </div>`;
}

// ── Select seller ──────────────────────────────────────────────────────────
function selectSeller(id) {
  selectedSellerId = id;
  showRoute = false;

  // Deactivate all list items
  document.querySelectorAll('.s-item').forEach(el => el.classList.remove('active'));
  const listItem = document.getElementById('si-'+id);
  if (listItem) listItem.classList.add('active');

  // Reset all markers
  allSellers.forEach((s,i) => {
    const obj = liveMarkers[s.id];
    if (!obj) return;
    const color = selColors[i % selColors.length];
    obj.marker.setIcon(makeIcon(s.name_en, color, s.id == id));
  });

  const s = allSellers.find(x => x.id == id);
  if (!s) return;

  // Zoom to seller
  if (s.lat && s.lng) {
    map.flyTo([s.lat, s.lng], 12, { duration: 1.2 });
    liveMarkers[id]?.marker.openPopup();
  }

  // Update info bar
  document.getElementById('sel-info-bar').innerHTML =
    `<strong>📍 ${s.name_en}</strong>
     <span class="info-chip">📞 ${s.phone}</span>
     <span class="info-chip">🏙️ ${s.district_name||'—'}</span>
     <span class="info-chip" id="ib-time">🕐 Loading...</span>
     <span class="info-chip" id="ib-coords">—</span>`;

  document.getElementById('btn-route').style.display = 'inline-block';

  // Load route
  loadRoute(id);
}

// ── Load route ─────────────────────────────────────────────────────────────
function loadRoute(id, hours=6) {
  fetch(`api_locations.php?action=route&seller_id=${id}&hours=${hours}`)
    .then(r => r.json())
    .then(data => {
      // Remove old route
      if (routeLines[id]) { map.removeLayer(routeLines[id]); delete routeLines[id]; }

      if (!data.points || data.points.length < 2) return;

      const latlngs = data.points.map(p => [parseFloat(p.lat), parseFloat(p.lng)]);

      // Draw dashed route line
      const line = L.polyline(latlngs, {
        color: liveMarkers[id]?.color || '#3498db',
        weight: 3,
        opacity: 0.75,
        dashArray: '8, 6'
      }).addTo(map);

      routeLines[id] = line;

      // Start & end markers
      const startIcon = L.divIcon({className:'',html:`<div style="background:#27ae60;color:#fff;padding:2px 7px;border-radius:4px;font-size:0.65rem;font-weight:700;white-space:nowrap;">▶ START</div>`,iconSize:[55,20],iconAnchor:[27,10]});
      const endIcon   = L.divIcon({className:'',html:`<div style="background:#c0392b;color:#fff;padding:2px 7px;border-radius:4px;font-size:0.65rem;font-weight:700;white-space:nowrap;">★ NOW</div>`,iconSize:[50,20],iconAnchor:[25,10]});

      L.marker(latlngs[0], {icon: startIcon}).addTo(map);
      L.marker(latlngs[latlngs.length-1], {icon: endIcon}).addTo(map);

      // Fit route if showing
      if (showRoute) map.fitBounds(line.getBounds(), {padding:[30,30]});

      // Update info bar coords
      const last = data.points[data.points.length-1];
      const ib = document.getElementById('ib-coords');
      if (ib) ib.textContent = `📌 ${parseFloat(last.lat).toFixed(4)}, ${parseFloat(last.lng).toFixed(4)}`;
      const ibt = document.getElementById('ib-time');
      if (ibt && last.logged_at) ibt.textContent = `🕐 ${last.logged_at}`;
    });
}

function toggleRoute() {
  if (!selectedSellerId) return;
  showRoute = !showRoute;
  document.getElementById('btn-route').textContent = showRoute ? '🗺️ Hide Route' : '📍 Show Route';
  document.getElementById('btn-route').classList.toggle('active', showRoute);
  const line = routeLines[selectedSellerId];
  if (line) {
    showRoute ? map.fitBounds(line.getBounds(), {padding:[40,40]}) : map.flyTo(liveMarkers[selectedSellerId]?.marker.getLatLng(),12);
  }
}

// ── Show all sellers ───────────────────────────────────────────────────────
function showAllSellers() {
  selectedSellerId = null;
  document.querySelectorAll('.s-item').forEach(el => el.classList.remove('active'));
  allSellers.forEach((s,i) => {
    const obj = liveMarkers[s.id];
    if (!obj) return;
    obj.marker.setIcon(makeIcon(s.name_en, selColors[i % selColors.length], false));
    obj.marker.closePopup();
  });
  Object.values(routeLines).forEach(l => map.removeLayer(l));
  fitNepal();
  document.getElementById('sel-info-bar').innerHTML = '👆 Click a seller from the list to see their live location and route on the map';
  document.getElementById('btn-route').style.display = 'none';
}

function fitNepal() { map.fitBounds([[26.3,80.0],[30.5,88.2]]); }

// ── Live refresh ───────────────────────────────────────────────────────────
function refreshPositions() {
  fetch('api_locations.php?action=live_positions')
    .then(r => r.json())
    .then(data => {
      if (!data.sellers) return;
      const now = new Date();
      document.getElementById('last-refresh').textContent =
        now.toLocaleTimeString('ne-NP', {hour:'2-digit',minute:'2-digit',second:'2-digit'});

      data.sellers.forEach(s => {
        if (!s.lat || !s.lng) return;
        const obj = liveMarkers[s.id];
        if (!obj) return;
        // Move marker smoothly
        obj.marker.setLatLng([parseFloat(s.lat), parseFloat(s.lng)]);
        obj.seller.lat = s.lat; obj.seller.lng = s.lng;

        // Online status dot
        const stEl = document.getElementById('st-'+s.id);
        if (stEl) {
          const lastSeen = s.last_seen ? new Date(s.last_seen) : null;
          const isOnline = lastSeen && (Date.now() - lastSeen.getTime()) < 300000; // 5min
          stEl.className = 's-status ' + (isOnline ? 'online' : 'offline');
          stEl.title = lastSeen ? 'Last seen: '+lastSeen.toLocaleTimeString() : 'Unknown';
        }
      });

      // Refresh route for selected seller
      if (selectedSellerId) loadRoute(selectedSellerId);
    })
    .catch(() => {
      document.getElementById('map-status').textContent = 'Refresh: connection error';
    });
}

// Pulse animation
const sty = document.createElement('style');
sty.textContent = `@keyframes pulseM{0%{transform:scale(1)}50%{transform:scale(1.15)}100%{transform:scale(1)}}@keyframes blink{0%,100%{opacity:1}50%{opacity:0.3}}`;
document.head.appendChild(sty);

// Search sellers
function filterSellers() {
  const q = document.getElementById('s-search').value.toLowerCase();
  document.querySelectorAll('.s-item').forEach(el => {
    el.style.display = el.dataset.name.includes(q) ? '' : 'none';
  });
}

// Auto refresh
refreshPositions();
setInterval(refreshPositions, 15000);

// Legend for route
document.getElementById('route-legend').innerHTML =
  `<div class="r-leg"><div class="r-dot" style="background:#27ae60"></div>Start</div>
   <div class="r-leg"><div class="r-dot" style="background:#c0392b"></div>Current</div>
   <div class="r-leg"><div class="r-dot" style="background:#3498db;border-top:2px dashed #3498db;height:0"></div>Route</div>`;
</script>
</body>
</html>