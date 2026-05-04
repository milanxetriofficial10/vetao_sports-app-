<?php
require_once 'config/db.php';
require_once 'district_data.php';

// Get admin office location
$stmtOffice = $pdo->query("SELECT * FROM admin_office_settings WHERE id = 1");
$office = $stmtOffice->fetch(PDO::FETCH_ASSOC);
if (!$office) {
    $office = ['office_name' => 'Admin Office - Chabahil', 'office_lat' => 27.7250, 'office_lng' => 85.3457];
}

// Fetch all sellers
$sellers = $pdo->query("SELECT * FROM sellers ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Handle deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM sellers WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: admin.php?msg=deleted");
    exit();
}

$successMsg = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'deleted') $successMsg = 'Seller removed successfully!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Seller Tracking with Route Line</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: #f0f2f5; overflow-x: hidden; }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100%;
            background: linear-gradient(180deg, #1a2a3a 0%, #0f1a24 100%);
            color: white;
            z-index: 100;
            transition: all 0.3s ease;
            box-shadow: 2px 0 12px rgba(0,0,0,0.1);
        }
        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        .sidebar-header h2 { font-size: 1.4rem; display: flex; align-items: center; gap: 8px; }
        .nav-links { list-style: none; padding: 0 16px; }
        .nav-links li { margin-bottom: 8px; }
        .nav-links a {
            display: flex; align-items: center; gap: 12px; padding: 12px 16px;
            color: #e0e4e8; text-decoration: none; border-radius: 12px; transition: 0.2s;
            font-weight: 500;
        }
        .nav-links a i { width: 24px; }
        .nav-links a:hover, .nav-links .active a { background: rgba(255,255,255,0.15); color: white; }
        
        /* Main content */
        .main-content { margin-left: 260px; transition: all 0.3s; }
        .top-header {
            background: white; padding: 14px 28px; display: flex; justify-content: space-between;
            align-items: center; box-shadow: 0 1px 4px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 99;
        }
        .menu-toggle { display: none; font-size: 1.5rem; cursor: pointer; }
        .page-title { font-weight: 700; font-size: 1.4rem; color: #1e2a3a; }
        .admin-badge { background: #e67e22; padding: 6px 14px; border-radius: 30px; color: white; font-size: 0.8rem; font-weight: 600; }
        
        .content-wrapper { padding: 24px 28px; }
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .stat-card {
            background: white; padding: 20px; border-radius: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            display: flex; align-items: center; justify-content: space-between;
        }
        .dashboard-split {
            display: grid; grid-template-columns: 1fr 380px; gap: 24px;
        }
        .map-container {
            background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.06);
        }
        .map-header {
            padding: 14px 20px; background: #1e2a3a; color: white; font-weight: 600;
        }
        #tracking-map { height: 450px; width: 100%; }
        .sellers-list {
            background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.06);
        }
        .list-header { padding: 16px 20px; background: #f8f9fc; border-bottom: 1px solid #e9ecef; font-weight: 700; }
        .seller-items { max-height: 500px; overflow-y: auto; }
        .seller-card {
            padding: 14px 18px; border-bottom: 1px solid #edf2f7; cursor: pointer;
            transition: background 0.2s;
        }
        .seller-card:hover { background: #f8fafc; }
        .seller-name { font-weight: 700; font-size: 1rem; }
        .seller-location { font-size: 0.75rem; color: #5a6e7c; margin-top: 4px; }
        .seller-actions { margin-top: 8px; display: flex; gap: 12px; }
        .delete-btn { color: #e74c3c; font-size: 0.75rem; text-decoration: none; }
        .track-btn { color: #2980b9; font-size: 0.75rem; cursor: pointer; }
        
        .alert { background: #27ae60; color: white; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; }
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .dashboard-split { grid-template-columns: 1fr; }
        }
        .distance-badge {
            background: #2ecc71; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600;
            display: inline-block;
        }
        .info-footer { padding: 12px 16px; background:#fef9e6; font-size:0.8rem; border-top:1px solid #eee; }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2><i class="fas fa-map-marked-alt"></i> NepalTrack</h2>
        <p>Admin Control Panel</p>
    </div>
    <ul class="nav-links">
        <li class="active"><a href="admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="seller.php"><i class="fas fa-store"></i> Add New Seller</a></li>
        <li><a href="#"><i class="fas fa-chart-line"></i> Tracking Reports</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="top-header">
        <div class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></div>
        <div class="page-title">Seller Tracking Dashboard</div>
        <div class="admin-badge"><i class="fas fa-user-shield"></i> Admin</div>
    </div>
    
    <div class="content-wrapper">
        <?php if ($successMsg): ?>
            <div class="alert"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMsg) ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card"><div><h3>Total Sellers</h3><p style="font-size:2rem; font-weight:800;"><?= count($sellers) ?></p></div><i class="fas fa-users fa-2x" style="color:#3498db;"></i></div>
            <div class="stat-card"><div><h3>Admin Office</h3><p style="font-size:0.9rem;"><?= htmlspecialchars($office['office_name']) ?><br>Lat: <?= $office['office_lat'] ?>, Lng: <?= $office['office_lng'] ?></p></div><i class="fas fa-building fa-2x"></i></div>
        </div>
        
        <div class="dashboard-split">
            <div class="map-container">
                <div class="map-header"><i class="fas fa-route"></i> Live Tracking Map - Click seller marker to draw route line from Admin Office (Chabahil)</div>
                <div id="tracking-map"></div>
                <div class="info-footer"><i class="fas fa-info-circle"></i> <strong>Tracking Line:</strong> Click on any seller marker → draws a <span style="color:#c0392b; font-weight:bold;">red dashed line</span> from Admin Office (Chabahil) to Seller's home. Distance calculated automatically.</div>
            </div>
            
            <div class="sellers-list">
                <div class="list-header"><i class="fas fa-list-ul"></i> Registered Sellers (Click to track)</div>
                <div class="seller-items" id="seller-list-container">
                    <?php if (count($sellers) > 0): ?>
                        <?php foreach ($sellers as $seller): ?>
                            <div class="seller-card" data-lat="<?= $seller['lat'] ?>" data-lng="<?= $seller['lng'] ?>" data-name="<?= htmlspecialchars($seller['seller_name']) ?>">
                                <div class="seller-name"><?= htmlspecialchars($seller['seller_name']) ?></div>
                                <div class="seller-location"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($seller['district_name']) ?> | <?= htmlspecialchars($seller['address_detail'] ?: 'Address provided') ?></div>
                                <div class="seller-actions">
                                    <span class="track-btn" onclick="trackSeller(<?= $seller['lat'] ?>, <?= $seller['lng'] ?>, '<?= addslashes($seller['seller_name']) ?>')"><i class="fas fa-route"></i> Draw Route Line</span>
                                    <a href="admin.php?delete=<?= $seller['id'] ?>" class="delete-btn" onclick="return confirm('Delete this seller?')"><i class="fas fa-trash-alt"></i> Delete</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 40px; text-align: center; color: #7f8c8d;">No sellers registered yet. <a href="seller.php">Add first seller</a></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const sellersData = <?= json_encode($sellers) ?>;
const officeLat = <?= $office['office_lat'] ?>;
const officeLng = <?= $office['office_lng'] ?>;
let map;
let activePolyline = null;
let officeMarker = null;
let sellerMarkers = {};

function initMap() {
    map = L.map('tracking-map').setView([officeLat, officeLng], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);
    
    // Admin Office marker (Chabahil)
    const officeIcon = L.divIcon({
        html: '<div style="background:#c0392b; width:28px; height:28px; border-radius:50%; border:3px solid white; box-shadow:0 0 0 2px #f1c40f; display:flex; align-items:center; justify-content:center;"><i class="fas fa-building" style="font-size:14px; color:white;"></i></div>',
        iconSize: [28,28], className: 'office-marker'
    });
    officeMarker = L.marker([officeLat, officeLng], { icon: officeIcon }).addTo(map)
        .bindTooltip('<strong>🏢 Admin Office - Chabahil, Kathmandu</strong><br>Starting point for all routes', { permanent: false });
    
    // Seller markers
    sellersData.forEach(seller => {
        const sellerIcon = L.divIcon({
            html: `<div style="background:#2980b9; width:16px; height:16px; border-radius:50%; border:2px solid white; box-shadow:0 1px 3px black;"></div>`,
            iconSize: [16,16]
        });
        const marker = L.marker([seller.lat, seller.lng], { icon: sellerIcon }).addTo(map);
        marker.bindPopup(`
            <strong>${escapeHtml(seller.seller_name)}</strong><br>
            <i class="fas fa-location-dot"></i> ${escapeHtml(seller.district_name)}<br>
            📍 Lat: ${seller.lat}, Lng: ${seller.lng}<br>
            <button onclick="trackSeller(${seller.lat}, ${seller.lng}, '${escapeHtml(seller.seller_name)}')" style="margin-top:6px; background:#e67e22; border:none; padding:4px 12px; border-radius:20px; color:white; cursor:pointer;">🚀 Draw Route Line</button>
        `);
        marker.on('click', () => trackSeller(seller.lat, seller.lng, seller.seller_name));
        sellerMarkers[seller.id] = marker;
    });
}

function trackSeller(lat, lng, sellerName) {
    if (activePolyline) map.removeLayer(activePolyline);
    
    const start = [officeLat, officeLng];
    const end = [lat, lng];
    activePolyline = L.polyline([start, end], { color: '#c0392b', weight: 4, dashArray: '10, 8', opacity: 0.9 }).addTo(map);
    
    // Calculate distance
    const distance = getDistanceFromLatLonInKm(officeLat, officeLng, lat, lng);
    
    // Add popup at midpoint
    const midLat = (officeLat + lat) / 2;
    const midLng = (officeLng + lng) / 2;
    L.popup()
        .setLatLng([midLat, midLng])
        .setContent(`<b>Route: ${escapeHtml(sellerName)}</b><br>📏 Distance: ${distance.toFixed(2)} km<br>🏢 Admin Office (Chabahil) → 🏠 Seller Home`)
        .openOn(map);
    
    map.fitBounds([start, end], { padding: [40, 40] });
}

function getDistanceFromLatLonInKm(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const dLat = deg2rad(lat2 - lat1);
    const dLon = deg2rad(lon2 - lon1);
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}
function deg2rad(deg) { return deg * (Math.PI/180); }
function escapeHtml(str) { return str.replace(/[&<>]/g, function(m){ if(m==='&') return '&amp;'; if(m==='<') return '&lt;'; if(m==='>') return '&gt;'; return m;}); }

document.getElementById('menuToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('active');
});

document.addEventListener('DOMContentLoaded', initMap);
</script>
</body>
</html>