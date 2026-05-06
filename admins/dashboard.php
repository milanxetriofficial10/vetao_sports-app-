<?php

require_once "../databases/db.php";
$conn = getDB();

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

include "sidenav.php";

// --- Handle Profile Update ---
$profile_message = '';
$profile_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $new_name = trim($_POST['admin_name']);
        $new_email = trim($_POST['admin_email']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($new_name) || empty($new_email)) {
            $profile_error = "Name and Email are required.";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $profile_error = "Invalid email format.";
        } else {
            $query = "SELECT password FROM admins WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin_data = $result->fetch_assoc();

            if (!password_verify($current_password, $admin_data['password'])) {
                $profile_error = "Current password is incorrect.";
            } else {
                $update_query = "UPDATE admins SET admin_name = ?, email = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ssi", $new_name, $new_email, $admin_id);

                if ($update_stmt->execute()) {
                    if (!empty($new_password)) {
                        if ($new_password !== $confirm_password) {
                            $profile_error = "Passwords do not match.";
                        } elseif (strlen($new_password) < 6) {
                            $profile_error = "Password too short.";
                        } else {
                            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                            $p = $conn->prepare("UPDATE admins SET password=? WHERE id=?");
                            $p->bind_param("si", $hashed, $admin_id);
                            $p->execute();
                            $p->close();
                        }
                    }
                    if (empty($profile_error)) {
                        $_SESSION['admin_name'] = $new_name;
                        $profile_message = "Profile updated!";
                    }
                }
                $update_stmt->close();
            }
            $stmt->close();
        }
    }
}

// Fetch admin
$stmt = $conn->prepare("SELECT admin_name, email FROM admins WHERE id=?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_info = $stmt->get_result()->fetch_assoc();

$admin_name  = $admin_info['admin_name'] ?? 'Admin';
$admin_email = $admin_info['email'] ?? '';

function safeCount($conn, $q)
{
    $r = $conn->query($q);
    return $r ? ($r->fetch_assoc()['total'] ?? 0) : 0;
}

$totalJerseys = safeCount($conn, "SELECT COUNT(*) as total FROM jerseys");
$totalSlider  = safeCount($conn, "SELECT COUNT(*) as total FROM slider");
$totalViews   = safeCount($conn, "SELECT COALESCE(SUM(views), 0) as total FROM jerseys");

// ----- Bar Chart Data -----
$chartLabels = [];
$chartData   = [];
$chartType   = 'monthly';

$hasCreatedAt = $conn->query("SHOW COLUMNS FROM jerseys LIKE 'created_at'")->num_rows > 0;

if ($hasCreatedAt) {
    // FIXED: use MIN(created_at) inside DATE_FORMAT to satisfy ONLY_FULL_GROUP_BY
    $monthlyQuery = "
        SELECT DATE_FORMAT(MIN(created_at), '%b %Y') as month_label, COUNT(*) as jersey_count
        FROM jerseys
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY MIN(created_at) DESC
        LIMIT 6
    ";
    $result = $conn->query($monthlyQuery);
    if ($result && $result->num_rows > 0) {
        $rows = array_reverse($result->fetch_all(MYSQLI_ASSOC));
        foreach ($rows as $row) {
            $chartLabels[] = $row['month_label'];
            $chartData[]   = (int)$row['jersey_count'];
        }
        $chartType = 'monthly';
    } else {
        $chartLabels = ['No Data'];
        $chartData   = [0];
    }
} else {
    $hasTeam = $conn->query("SHOW COLUMNS FROM jerseys LIKE 'team'")->num_rows > 0;
    if ($hasTeam) {
        $teamQuery = "
            SELECT team, COUNT(*) as count FROM jerseys
            GROUP BY team ORDER BY count DESC LIMIT 5
        ";
        $result = $conn->query($teamQuery);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $chartLabels[] = !empty($row['team']) ? $row['team'] : 'Unassigned';
                $chartData[]   = (int)$row['count'];
            }
            $chartType = 'team';
        } else {
            $chartLabels = ['Total Jerseys'];
            $chartData   = [$totalJerseys];
            $chartType   = 'simple';
        }
    } else {
        $chartLabels = ['Total Jerseys'];
        $chartData   = [$totalJerseys];
        $chartType   = 'simple';
    }
}

// ----- Donut Chart: Sport Type Distribution -----
$donutLabels = [];
$donutData   = [];
$hasSportType = $conn->query("SHOW COLUMNS FROM jerseys LIKE 'sport_type'")->num_rows > 0;
if ($hasSportType) {
    $sportResult = $conn->query("SELECT sport_type, COUNT(*) as cnt FROM jerseys GROUP BY sport_type ORDER BY cnt DESC LIMIT 6");
    if ($sportResult) {
        while ($row = $sportResult->fetch_assoc()) {
            $donutLabels[] = !empty($row['sport_type']) ? $row['sport_type'] : 'Other';
            $donutData[]   = (int)$row['cnt'];
        }
    }
}
if (empty($donutData)) {
    $donutLabels = ['All Jerseys'];
    $donutData   = [$totalJerseys];
}

// ----- Recent Jerseys -----
$recentJerseys = [];
$recentResult  = $conn->query("
    SELECT id, title AS name, sport_type AS team, price, views
    FROM jerseys ORDER BY id DESC LIMIT 5
");
if ($recentResult) {
    $recentJerseys = $recentResult->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | Admin Control</title>
<link rel="icon" href="/admins/logo/cropped_circle_image.png" type="image/png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* ======= ROOT & RESET ======= */
:root {
    --sidebar-w: 260px;
    --primary-light: #e74c3c;
    --dark-bg: #0d0d1a;
    --dark-card: rgba(255,255,255,0.06);
    --dark-border: rgba(255,255,255,0.10);
    --glass: rgba(255,255,255,0.08);
    --glass-border: rgba(255,255,255,0.14);
    --text-bright: #f0f0f0;
    --text-muted: #9ba4b4;
    --accent-green: #00c9a7;
    --accent-blue: #4facfe;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Segoe UI', Roboto, Arial, sans-serif;
    background: #dadbf6;
    color: #250404;
    overflow-x: hidden;
}

/* ======= SNAKE CANVAS ======= */
#snakeCanvas {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    z-index: 0;
    pointer-events: none;
    opacity: 0.22;
}

/* ======= MAIN WRAPPER ======= */
.main {
    margin-left: var(--sidebar-w);
    padding: 28px 32px 48px;
    position: relative;
    z-index: 1;
    min-height: 100vh;
    transition: margin 0.3s;
}

/* ======= TOP HEADER ======= */
.admin-header {
    background: var(--glass);
    border: 1px solid #05cc0c;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-radius: 18px;
    padding: 20px 24px;
    margin-bottom: 28px;
    color: #022edf;
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
}

.admin-header-icon {
    width: 52px; height: 52px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
    box-shadow: 0 4px 20px rgba(192,57,43,0.4);
}

.admin-header-text h1 {
    font-size: 22px;
    font-weight: 600;
    color: #1f1c02;
}

.admin-header-text p {
    font-size: 14px;
    color: red;
    margin-top: 4px;
}

.welcome-pill {
    margin-left: auto;
    background: rgba(222, 199, 219, 0.18);
    border: 1px solid rgba(165, 0, 201, 0.85);
    color: #241713;
    padding: 8px 18px;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 500;
    display: flex; align-items: center; gap: 8px;
    white-space: nowrap;
}


.pulse-dot {
    width: 8px; height: 8px;
    background: #43c900;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.3); }
}

/* ======= STAT CARDS ======= */
.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 18px;
    margin-bottom: 28px;
}

.card {
    background: var(--glass);
    border: 1px solid #df4f01;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 18px;
    padding: 22px 20px;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    position: relative;
    overflow: hidden;
}

.card::before {
    content: '';
    position: absolute;
    top: -30px; right: -30px;
    width: 80px; height: 80px;
    border-radius: 50%;
    background: rgba(119, 191, 4, 0.83);
    transition: transform 0.3s;
}

.card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.3);
    border-color: rgba(50, 192, 43, 0.35);
}

.card:hover::before {
    transform: scale(1.4);
}

.card-icon {
    width: 50px; height: 50px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    margin-bottom: 14px;
}

.card-icon.red   { background: rgba(27, 148, 3, 0.87); color: #f4eeed; }
.card-icon.green { background: rgba(218, 61, 17, 0.93); color: #f4eeed; }
.card-icon.blue  { background: rgba(1, 33, 113, 0.72); color: #fff; }

.card h3 {
    font-size: 34px;
    font-weight: 700;
    color: black;
    letter-spacing: -1px;
}

.card p {
    font-size: 13px;
    color: red;
    margin-top: 4px;
    font-weight: 500;
}

.card-trend {
    font-size: 11px;
    color: #eb3e09;
    margin-top: 6px;
    display: flex; align-items: center; gap: 4px;
}

/* ======= CHART GRID ======= */
.chart-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 28px;
}

.chart-box {
    background: var(--glass);
    border: 1px solid #3000a0;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 18px;
    padding: 22px;
}

.chart-box-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
    padding-bottom: 14px;
    border-bottom: 1px solid rgba(16, 138, 5, 0.85);
}

.chart-box-header h3 {
    font-size: 15px;
    font-weight: 600;
    color: #1f1c02;
}

.chart-box-header .chart-icon {
    width: 32px; height: 32px;
    background: rgba(60, 23, 19, 0.2);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #f08070;
    font-size: 14px;
}

.chart-note {
    font-size: 12px;
    color: #040803;
    text-align: center;
    margin-top: 12px;
}

canvas {
    max-height: 280px;
    width: 100% !important;
}

/* ======= RECENT TABLE ======= */
.recent-section {
    background: var(--glass);
    border: 1px solid #0704ba;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 18px;
    padding: 22px 24px;
}

.recent-header {
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 18px;
    padding-bottom: 14px;
    border-bottom: 1px solid rgb(220, 79, 3);
}

.recent-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: #063503;
}

.recent-header .badge-count {
    margin-left: auto;
    background: rgba(192,57,43,0.18);
    color: #ba1c04;
    border: 1px solid rgba(11, 204, 4, 0.88);
    padding: 3px 12px;
    border-radius: 30px;
    font-size: 12px;
}

.jersey-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.jersey-table thead th {
    padding: 10px 12px;
    text-align: left;
    color: #150505;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: rgba(255, 255, 255, 0.49);
    border-radius: 6px;
}

.jersey-table tbody td {
    padding: 12px 12px;
    border-bottom: 1px solid rgba(236, 88, 88, 0.93);
    color: #1f1c02;
    vertical-align: middle;
}

.jersey-table tbody tr:last-child td {
    border-bottom: none;
}

.jersey-table tbody tr:hover td {
    background: rgba(255,255,255,0.04);
}

.jersey-name { font-weight: 500; }

.sport-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    background: rgba(79,172,254,0.15);
    color: #2d5e02;
    border: 1px solid rgba(29, 121, 13, 0.2);
}

.view-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 600;
    background: rgba(5, 114, 3, 0.7);
    color: #fff;
    border: 1px solid rgba(0,201,167,0.2);
}

.price-text {
    font-weight: 600;
    color: #f5c542;
}

.empty-list {
    text-align: center;
    padding: 40px;
    color: var(--text-muted);
    font-size: 14px;
}

/* ======= OVERLAY & RESPONSIVE ======= */
.overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 99;
}

@media (max-width: 1024px) {
    .chart-grid { grid-template-columns: 1fr; }
}

@media (max-width: 900px) {
    .main { margin-left: 0; padding: 16px; }
    .admin-header { gap: 12px; }
    .welcome-pill { margin-left: 0; }
    .jersey-table th, .jersey-table td { padding: 8px 6px; font-size: 12px; }
    .cards { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
}

/* ======= PROFILE MODAL ======= */
.modal-backdrop {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.65);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-box {
    background: #1a1a2e;
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 28px;
    width: 420px;
    max-width: 92%;
}

.modal-box h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 18px;
    color: var(--text-bright);
}

.modal-box input {
    width: 100%;
    padding: 10px 14px;
    margin-bottom: 12px;
    border-radius: 12px;
    border: 1px solid var(--dark-border);
    background: rgba(255,255,255,0.07);
    color: var(--text-bright);
    font-size: 14px;
    outline: none;
    transition: border 0.2s;
}

.modal-box input:focus {
    border-color: rgba(192,57,43,0.6);
}

.modal-box input::placeholder { color: var(--text-muted); }

.btn-save {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    padding: 10px 24px;
    border-radius: 40px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: opacity 0.2s;
}

.btn-save:hover { opacity: 0.85; }

.btn-cancel {
    background: rgba(255,255,255,0.1);
    border: 1px solid var(--dark-border);
    color: var(--text-muted);
    padding: 10px 20px;
    border-radius: 40px;
    margin-left: 8px;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.2s;
}

.btn-cancel:hover { background: rgba(255,255,255,0.16); }

.msg-success { color: #00c9a7; font-size: 13px; margin-bottom: 12px; }
.msg-error   { color: #f08070; font-size: 13px; margin-bottom: 12px; }
</style>
</head>
<body>

<!-- SNAKE ANIMATION CANVAS -->
<canvas id="snakeCanvas"></canvas>

<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<div class="main">

    <!-- HEADER -->
    <div class="admin-header">
        <div class="admin-header-icon">
            <i class="fas fa-chalkboard-user"></i>
        </div>
        <div class="admin-header-text">
            <h1>Dashboard Control</h1>
            <p><i class="fas fa-globe" style="margin-right:6px;"></i>Manage jerseys, slider, analytics &amp; inventory</p>
        </div>
        <div class="welcome-pill">
            <span class="pulse-dot"></span>
            <i class="fas fa-user-shield"></i>
            Welcome, <?= htmlspecialchars($admin_name) ?>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="cards">
        <div class="card">
            <div class="card-icon red"><i class="fas fa-tshirt"></i></div>
            <h3><?= $totalJerseys ?></h3>
            <p>Total Jerseys</p>
            <div class="card-trend"><i class="fas fa-arrow-trend-up"></i> Active inventory</div>
        </div>
        <div class="card">
            <div class="card-icon blue"><i class="fas fa-images"></i></div>
            <h3><?= $totalSlider ?></h3>
            <p>Slider Items</p>
            <div class="card-trend"><i class="fas fa-circle-check"></i> Homepage slides</div>
        </div>
        <div class="card">
            <div class="card-icon green"><i class="fas fa-eye"></i></div>
            <h3><?= number_format($totalViews) ?></h3>
            <p>Total Views</p>
            <div class="card-trend"><i class="fas fa-arrow-trend-up"></i> Lifetime count</div>
        </div>
    </div>

    <!-- CHARTS ROW -->
    <div class="chart-grid">

        <!-- Bar Chart -->
        <div class="chart-box">
            <div class="chart-box-header">
                <div class="chart-icon"><i class="fas fa-chart-column"></i></div>
                <h3>
                    <?php if ($chartType === 'monthly'): ?>
                        Jersey Additions Over Time
                    <?php elseif ($chartType === 'team'): ?>
                        Jersey Count by Team
                    <?php else: ?>
                        Jersey Summary
                    <?php endif; ?>
                </h3>
            </div>
            <canvas id="jerseyBarChart"></canvas>
            <div class="chart-note">
                <i class="fas fa-calendar-alt" style="margin-right:5px;"></i>
                <?php if ($chartType === 'monthly'): ?>
                    Monthly jersey additions — latest 6 months
                <?php elseif ($chartType === 'team'): ?>
                    Distribution across top 5 teams
                <?php else: ?>
                    Overall jersey data
                <?php endif; ?>
            </div>
        </div>

        <!-- Donut Chart -->
        <div class="chart-box">
            <div class="chart-box-header">
                <div class="chart-icon"><i class="fas fa-chart-pie"></i></div>
                <h3>Sport Type Split</h3>
            </div>
            <canvas id="jerseyDonutChart" style="max-height:220px;"></canvas>
            <div class="chart-note">
                <i class="fas fa-futbol" style="margin-right:5px;"></i>
                Jersey breakdown by sport category
            </div>
        </div>

    </div>

    <!-- RECENT JERSEYS TABLE -->
    <div class="recent-section">
        <div class="recent-header">
            <div class="chart-icon" style="width:32px;height:32px;background:rgba(192,57,43,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#f08070;font-size:14px;">
                <i class="fas fa-list-ul"></i>
            </div>
            <h3>Recently Added Jerseys</h3>
            <span class="badge-count">Latest 5 items</span>
        </div>

        <?php if (count($recentJerseys) > 0): ?>
        <table class="jersey-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Jersey Name</th>
                    <th>Sport / Team</th>
                    <th>Price (NPR)</th>
                    <th>Views</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentJerseys as $jersey): ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:13px;"><?= htmlspecialchars($jersey['id']) ?></td>
                    <td><span class="jersey-name"><?= htmlspecialchars($jersey['name'] ?? 'Unnamed') ?></span></td>
                    <td><span class="sport-badge"><?= htmlspecialchars($jersey['team'] ?? 'General') ?></span></td>
                    <td><span class="price-text">Rs. <?= number_format($jersey['price'] ?? 0) ?></span></td>
                    <td>
                        <span class="view-badge">
                            <i class="fas fa-eye" style="font-size:10px;"></i>
                            <?= number_format((int)($jersey['views'] ?? 0)) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-list">
            <i class="fas fa-box-open" style="font-size:28px;margin-bottom:10px;display:block;"></i>
            No jerseys found yet. Start adding jerseys to see the list here.
        </div>
        <?php endif; ?>
    </div>

</div><!-- end .main -->

<!-- PROFILE MODAL -->
<div id="profileModal" class="modal-backdrop">
<div class="modal-box">
    <h3><i class="fas fa-user-edit" style="color:#f08070;margin-right:8px;"></i>Update Profile</h3>

    <?php if ($profile_message): ?>
        <p class="msg-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($profile_message) ?></p>
    <?php elseif ($profile_error): ?>
        <p class="msg-error"><i class="fas fa-triangle-exclamation"></i> <?= htmlspecialchars($profile_error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="text"     name="admin_name"       value="<?= htmlspecialchars($admin_name) ?>"  placeholder="Full Name">
        <input type="email"    name="admin_email"      value="<?= htmlspecialchars($admin_email) ?>" placeholder="Email Address">
        <input type="password" name="current_password" placeholder="Current Password">
        <input type="password" name="new_password"     placeholder="New Password (optional)">
        <input type="password" name="confirm_password" placeholder="Confirm New Password">
        <div style="margin-top:4px;">
            <button type="submit" name="update_profile" class="btn-save">Save Changes</button>
            <button type="button" class="btn-cancel" onclick="document.getElementById('profileModal').style.display='none'">Cancel</button>
        </div>
    </form>
</div>
</div>

<script>
/* =============================================
   SNAKE CANVAS ANIMATION
   ============================================= */
(function() {
    const canvas = document.getElementById('snakeCanvas');
    const ctx    = canvas.getContext('2d');
    const CELL   = 18;
    const SPEED  = 60; // ms per frame

    function resize() {
        canvas.width  = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    const COLORS = [
        '#3003f4', '#d41601', '#8eee09',
        '#4facfe', '#eaac02', '#f502d0'
    ];

    function makeSnake(index) {
        const cols  = Math.floor(canvas.width  / CELL);
        const rows  = Math.floor(canvas.height / CELL);
        const x     = Math.floor(Math.random() * cols) * CELL;
        const y     = Math.floor(Math.random() * rows) * CELL;
        const dirs  = [{dx:CELL,dy:0},{dx:-CELL,dy:0},{dx:0,dy:CELL},{dx:0,dy:-CELL}];
        const dir   = dirs[Math.floor(Math.random() * dirs.length)];
        return {
            body:      [{x, y}],
            dx:        dir.dx,
            dy:        dir.dy,
            length:    20 + Math.floor(Math.random() * 20),
            color:     COLORS[index % COLORS.length],
            turnTimer: 0,
            turnEvery: 8 + Math.floor(Math.random() * 16)
        };
    }

    const NUM_SNAKES = 7;
    let snakes = [];
    for (let i = 0; i < NUM_SNAKES; i++) snakes.push(makeSnake(i));

    function hexToRgb(hex) {
        const r = parseInt(hex.slice(1,3),16);
        const g = parseInt(hex.slice(3,5),16);
        const b = parseInt(hex.slice(5,7),16);
        return {r,g,b};
    }

    function step() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        snakes.forEach(s => {
            // Maybe turn
            s.turnTimer++;
            if (s.turnTimer >= s.turnEvery) {
                s.turnTimer = 0;
                s.turnEvery = 8 + Math.floor(Math.random() * 16);
                const turns = s.dx === 0
                    ? [{dx:CELL,dy:0},{dx:-CELL,dy:0}]
                    : [{dx:0,dy:CELL},{dx:0,dy:-CELL}];
                if (Math.random() < 0.55) {
                    const t = turns[Math.floor(Math.random() * turns.length)];
                    s.dx = t.dx; s.dy = t.dy;
                }
            }

            // Move head
            let nx = s.body[0].x + s.dx;
            let ny = s.body[0].y + s.dy;

            // Wrap around edges
            if (nx < 0)              nx = Math.floor(canvas.width  / CELL) * CELL;
            if (nx >= canvas.width)  nx = 0;
            if (ny < 0)              ny = Math.floor(canvas.height / CELL) * CELL;
            if (ny >= canvas.height) ny = 0;

            s.body.unshift({x: nx, y: ny});
            if (s.body.length > s.length) s.body.pop();

            // Draw body segments
            const rgb = hexToRgb(s.color);
            s.body.forEach((seg, i) => {
                const ratio = 1 - i / s.body.length;
                const alpha = ratio * 0.75;
                const size  = Math.max(3, CELL * ratio * 0.85);
                ctx.beginPath();
                ctx.arc(seg.x + CELL/2, seg.y + CELL/2, size/2, 0, Math.PI*2);
                ctx.fillStyle = `rgba(${rgb.r},${rgb.g},${rgb.b},${alpha})`;
                ctx.fill();
            });

            // Draw eyes on head
            const head = s.body[0];
            const eyeOffset = CELL * 0.22;
            const perpX = s.dy === 0 ? 0 : eyeOffset;
            const perpY = s.dx === 0 ? 0 : eyeOffset;
            const fwdX  = s.dx === 0 ? 0 : eyeOffset * 0.8;
            const fwdY  = s.dy === 0 ? 0 : eyeOffset * 0.8;
            const cx    = head.x + CELL/2;
            const cy    = head.y + CELL/2;

            [[cx - perpY + fwdX, cy - perpX + fwdY],
             [cx + perpY + fwdX, cy + perpX + fwdY]].forEach(([ex, ey]) => {
                ctx.beginPath();
                ctx.arc(ex, ey, 2.2, 0, Math.PI*2);
                ctx.fillStyle = '#ffffff';
                ctx.fill();
                ctx.beginPath();
                ctx.arc(ex + 0.5, ey + 0.5, 1, 0, Math.PI*2);
                ctx.fillStyle = '#000';
                ctx.fill();
            });
        });
    }

    setInterval(step, SPEED);
})();

/* =============================================
   BAR CHART
   ============================================= */
(function() {
    const ctx2 = document.getElementById('jerseyBarChart').getContext('2d');
    const labels = <?= json_encode($chartLabels) ?>;
    const data   = <?= json_encode($chartData) ?>;
    const chartType = '<?= $chartType ?>';

    const gradient = ctx2.createLinearGradient(0, 0, 0, 280);
    gradient.addColorStop(0,   'rgba(192,57,43,0.85)');
    gradient.addColorStop(1,   'rgba(231,76,60,0.25)');

    new Chart(ctx2, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: chartType === 'monthly' ? 'Jerseys Added' : 'Jersey Count',
                data,
                backgroundColor: gradient,
                borderColor: '#C0392B',
                borderWidth: 1,
                borderRadius: 10,
                borderSkipped: false,
                barPercentage: 0.6,
                categoryPercentage: 0.75
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            animation: { duration: 900, easing: 'easeOutQuart' },
            plugins: {
                legend: {
                    labels: {
                        color: '#9ba4b4',
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    backgroundColor: '#1a1a2e',
                    titleColor: '#f0f0f0',
                    bodyColor: '#9ba4b4',
                    borderColor: 'rgba(192,57,43,0.4)',
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 10
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        color: '#9ba4b4',
                        font: { size: 11 }
                    },
                    grid: { color: 'rgba(255,255,255,0.06)' },
                    title: {
                        display: true,
                        text: 'Jerseys',
                        color: '#9ba4b4',
                        font: { size: 11, weight: '600' }
                    }
                },
                x: {
                    ticks: {
                        color: '#9ba4b4',
                        font: { size: 11 },
                        maxRotation: 30
                    },
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    title: {
                        display: true,
                        text: chartType === 'monthly' ? 'Month' : (chartType === 'team' ? 'Team' : 'Category'),
                        color: '#9ba4b4',
                        font: { size: 11, weight: '600' }
                    }
                }
            }
        }
    });
})();

/* =============================================
   DONUT CHART
   ============================================= */
(function() {
    const ctx3 = document.getElementById('jerseyDonutChart').getContext('2d');
    const labels = <?= json_encode($donutLabels) ?>;
    const data   = <?= json_encode($donutData) ?>;

    const palette = [
        '#C0392B','#4facfe','#00c9a7',
        '#f5c542','#a29bfe','#fd79a8'
    ];

    new Chart(ctx3, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: palette.slice(0, data.length),
                borderColor: '#0d0d1a',
                borderWidth: 3,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '62%',
            animation: { duration: 1000, easing: 'easeOutBounce' },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#9ba4b4',
                        font: { size: 11 },
                        padding: 14,
                        boxWidth: 12,
                        boxHeight: 12
                    }
                },
                tooltip: {
                    backgroundColor: '#1a1a2e',
                    titleColor: '#f0f0f0',
                    bodyColor: '#9ba4b4',
                    borderColor: 'rgba(255,255,255,0.12)',
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 10
                }
            }
        }
    });
})();

/* =============================================
   HELPERS
   ============================================= */
function openProfileModal() {
    document.getElementById('profileModal').style.display = 'flex';
}
function closeSidebar() {
    document.getElementById('overlay').style.display = 'none';
}
</script>
</body>
</html>