<?php
session_start();
require_once __DIR__ . '/../databases/db.php';
$db = getDB();

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['seller_id'])) {
    $seller_id = (int)$_POST['seller_id'];
    $action = $_POST['action'];
    $new_status = null;
    if ($action === 'approve') $new_status = 'approved';
    if ($action === 'reject') $new_status = 'rejected';
    if ($new_status) {
        $stmt = $db->prepare("UPDATE sellers SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $seller_id);
        $stmt->execute();
        $stmt->close();
    }
    // Redirect to avoid form resubmission
    header("Location: admin_dashboard.php");
    exit;
}

// Fetch sellers with optional status filter from GET
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$sql = "SELECT id, full_name, email, shop_name, status, created_at FROM sellers";
if (in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
    $sql .= " WHERE status = '" . $db->real_escape_string($statusFilter) . "'";
}
$sql .= " ORDER BY id DESC";
$sellers = $db->query($sql);
$all_rows = [];
while ($r = $sellers->fetch_assoc()) $all_rows[] = $r;

$total     = count($all_rows);
$approved  = count(array_filter($all_rows, fn($r) => $r['status'] === 'approved'));
$pending   = count(array_filter($all_rows, fn($r) => $r['status'] === 'pending'));
$rejected  = count(array_filter($all_rows, fn($r) => $r['status'] === 'rejected'));
?>

<!DOCTYPE html>
<html lang="en">
<hea>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | Playzo</title>
    <link rel="icon" href="../logo/cropped_circle_image.png?v=2" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        :root {
            --primary:    #C0392B;
            --ink:        #100500;
            --ink-soft:   #6B5044;
            --bg:         #F9F3EC;
            --surface:    #FFFFFF;
            --border:     #E5D9CE;
            --green:      #1a8a4a;
            --amber:      #b86a00;
            --sidebar-w:  240px;
            --top-h:      62px;
            --radius-lg:  18px;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--ink);
            min-height: 100vh;
        }

        /* ================== SIDEBAR ================== */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar-w);
            height: 100vh;
            background: #c94b01;
            display: flex;
            flex-direction: column;
            z-index: 300;
            transition: transform .28s cubic-bezier(.4,0,.2,1);
        }

        .sidebar-logo {
            padding: 0 22px;
            height: var(--top-h);
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
        }

        .logo-mark {
            width: 34px; height: 34px;
            background: #fff;
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Sora', sans-serif;
            font-weight: 800;
            font-size: 15px;
            color: #27fb02;
        }

        .logo-word {
            font-family: 'Sora', sans-serif;
            font-weight: 700;
            font-size: 16px;
            color: #56f705;
            letter-spacing: -.3px;
        }
        .logo-word em { color: #fff; font-style: normal; }

        .sidebar-nav {
            flex: 1;
            padding: 18px 12px;
            overflow-y: auto;
        }

        .nav-section {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: rgba(255,255,255,.35);
            padding: 0 10px;
            margin: 18px 0 6px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border-radius: 9px;
            color: rgba(255,255,255,.75);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: all .15s;
        }

        .nav-item:hover {
            background: rgba(255,255,255,.08);
            color: #fff;
        }
        .nav-item.active {
            background: var(--primary);
            color: #fff;
        }

        .nav-item .ni { 
            font-size: 15px; 
            width: 20px; 
            text-align: center; 
            flex-shrink: 0; 
        }

        .nav-badge {
            margin-left: auto;
            background: rgba(255,255,255,.25);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
        }

        .sidebar-foot {
            padding: 14px 12px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .logout-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border-radius: 9px;
            color: rgba(255,255,255,.75);
            text-decoration: none;
            font-size: 13.5px;
        }
        .logout-link:hover {
            background: rgba(192,57,43,.25);
            color: #ff8a80;
        }

        /* ================== TOPBAR ================== */
        .topbar {
            position: fixed;
            top: 0; left: var(--sidebar-w); right: 0;
            height: var(--top-h);
            background: #c94b01;
            display: flex;
            align-items: center;
            padding: 0 24px;
            gap: 16px;
            z-index: 200;
            color: #fff;
        }

        .hamburger {
            display: none;
            width: 34px; height: 34px;
            border: 1px solid rgba(255,255,255,0.3);
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        .hamburger span {
            width: 16px; height: 1.5px;
            background: #fff;
            border-radius: 2px;
        }

        .topbar-title {
            font-family: 'Sora', sans-serif;
            font-weight: 700;
            font-size: 16px;
            flex: 1;
        }
        .topbar-title small {
            font-size: 12px;
            opacity: 0.85;
            display: block;
            margin-top: 1px;
        }

        .search-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.95);
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 10px;
            padding: 0 12px;
            height: 36px;
            width: 220px;
            transition: all .2s;
        }
        .search-wrap:focus-within {
            width: 260px;
            background: #fff;
        }
        .search-wrap svg { color: #666; }
        .search-wrap input {
            border: none;
            background: transparent;
            font-size: 13px;
            color: var(--ink);
            outline: none;
            width: 100%;
        }
        .search-wrap input::placeholder { color: #666; }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .icon-btn {
            width: 34px; height: 34px;
            border: none;
            background: rgba(255,255,255,0.12);
            color: #fff;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .icon-btn:hover { background: rgba(255,255,255,0.25); }

        .notif-dot {
            position: absolute; top: 6px; right: 6px;
            width: 7px; height: 7px;
            background: #ff4757;
            border: 1.5px solid #c94b01;
            border-radius: 50%;
        }

        /* Message badge with number */
        .msg-badge {
            position: absolute; top: 2px; right: 2px;
            background: #ff4757;
            color: white;
            font-size: 9px;
            font-weight: 700;
            border-radius: 20px;
            padding: 2px 5px;
            min-width: 16px;
            text-align: center;
            border: 1.5px solid #c94b01;
            line-height: 1;
        }

        .admin-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 12px 4px 4px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 40px;
            cursor: pointer;
            color: #fff;
        }
        .admin-pill:hover { background: rgba(255,255,255,0.25); }

        .admin-avatar {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: #fff;
            color: #c94b01;
            font-weight: 700;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .admin-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            min-width: 200px;
            z-index: 600;
        }
        .admin-dropdown.open { display: block; }

        /* ================== MAIN ================== */
        .main {
            margin-left: var(--sidebar-w);
            margin-top: var(--top-h);
            padding: 30px 28px;
            min-height: calc(100vh - var(--top-h));
        }

        /* Stats & Table Styles */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }

        .stat {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
            transition: all 0.2s;
        }
        .stat:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }

        .stat-num {
            font-family: 'Sora', sans-serif;
            font-size: 32px;
            font-weight: 800;
            color: var(--ink);
        }

        .stat-lbl {
            font-size: 12.5px;
            color: var(--ink-soft);
        }

        .table-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow-x: auto;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }

        .seller-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            min-width: 680px;
        }

        .seller-table th {
            text-align: left;
            padding: 14px 16px;
            background: #FDF8F2;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            color: var(--ink-soft);
        }

        .seller-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            color: var(--ink);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 60px;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
        }
        .status-approved { background: #e0f7ea; color: #1a8a4a; }
        .status-pending { background: #fff0e0; color: #b86a00; }
        .status-rejected { background: #ffe6e5; color: #c0392b; }

        .action-btns {
            display: flex;
            gap: 8px;
        }
        .btn-sm {
            border: none;
            background: transparent;
            font-size: 12px;
            font-weight: 500;
            padding: 4px 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.1s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-approve { background: #e0f7ea; color: #1a8a4a; }
        .btn-approve:hover { background: #bde0cc; }
        .btn-reject { background: #ffe6e5; color: #c0392b; }
        .btn-reject:hover { background: #ffcdca; }
        .btn-view { background: #f0f0f0; color: #4b3b2e; }

        /* Overlay & Responsive */
        .overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.3);
            z-index: 250;
        }
        .overlay.show { display: block; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .topbar { left: 0; }
            .main { margin-left: 0; }
            .hamburger { display: flex; }
            .search-wrap { width: 160px; }
            .search-wrap:focus-within { width: 200px; }
        }

        .dd-head {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            font-size: 12px; color: var(--ink-soft);
        }
        .dd-head strong { display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 2px; }
        .dd-link {
            display: flex; align-items: center; gap: 8px;
            padding: 9px 14px;
            font-size: 13px; color: var(--ink-soft);
            text-decoration: none;
            transition: background .12s, color .12s;
        }
        .dd-link:hover { background: var(--bg); color: var(--ink); }
        .dd-link.danger:hover { background: #ffe6e5; color: var(--primary); }
        .dd-sep { border: none; border-top: 1px solid var(--border); margin: 4px 0; }

        .logo-img{
            width: 150px;
            height: 50px;


        }
    </style>
</head>
<body>

<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- ================== SIDEBAR ================== -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="../img_logo/playzo.w.png" alt="BazaarNepal Logo" class="logo-img">
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Overview</div>
        <a href="dashboard.php" class="nav-item active">
            <span class="ni">🏠</span> Dashboard
        </a>

        <div class="nav-section">Sellers</div>
        <a href="seller_dashboard.php" class="nav-item">
            <span class="ni">🏪</span> Sellers Manages
            <span class="nav-badge"><?= $total ?></span>
        </a>
        <a href="history_sales.php?status=pending" class="nav-item">
            <span class="ni">📅</span> Daily Sales History 
            <span class="nav-badge"><?= $pending ?></span>
        </a>
        <a href="admin_dashboard.php?status=approved" class="nav-item">
            <span class="ni">🛒</span> Orders summary
            <span class="nav-badge"><?= $approved ?></span>
        </a>
        <a href="admin_dashboard.php?status=rejected" class="nav-item">
            <span class="ni">💬</span> Commission
            <span class="nav-badge"><?= $rejected ?></span>
        </a>

        <a href="admin_form.php?status=rejected" class="nav-item">
            <span class="ni">💬</span> Sellers Tracting
            <span class="nav-badge"><?= $rejected ?></span>
        </a>

        <a href="add_slider.php?status=rejected" class="nav-item">
            <span class="ni">🖼️</span> Add Slider 
            <span class="nav-badge"><?= $rejected ?></span>
        </a>

        <div class="nav-section">Products</div>
        <a href="products.php" class="nav-item">
            <span class="ni">📦</span> Manage products
        </a>
        <a href="products.php" class="nav-item">
            <span class="ni">📋</span> Stock control
        </a>
        <a href="products.php" class="nav-item">
            <span class="ni">📝</span> Product reviews
        </a>


          <div class="nav-section">Users Management</div>
        <a href="manage_users.php" class="nav-item">
            <span class="ni">👥</span> Block/unblock users
        </a>
         <a href="user_collection.php" class="nav-item">
            <span class="ni">👥</span> User Collection 
        </a>

          <div class="nav-section">Payments & Transactions</div>
        <a href="settings.php" class="nav-item">
            <span class="ni">💳</span> Payment history
        </a>
         <a href="settings.php" class="nav-item">
            <span class="ni">🔄</span> Refund management
        </a>


        <div class="nav-section">Settings</div>
        <a href="settings.php" class="nav-item">
            <span class="ni">⚙️</span> Settings
        </a>
    </nav>

    <div class="sidebar-foot">
        <a href="logout.php" class="logout-link">
            <span style="font-size:15px;">🚪</span> Logout
        </a>
    </div>
</aside>

<!-- ================== TOPBAR ================== -->
<header class="topbar">
    <button class="hamburger" onclick="toggleSidebar()">
        <span></span><span></span><span></span>
    </button>

    <div class="topbar-title">
        Admin Panel
        <small>BazaarNepal — Seller Management</small>
    </div>

    <div class="search-wrap">
        <svg width="16" height="16" fill="none" stroke="#666" stroke-width="2.5" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8"/>
            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" id="searchInput" placeholder="Search sellers..." oninput="filterTable()">
    </div>

    <div class="topbar-right">
        <!-- NEW MESSAGE BOX ICON with badge -->
       <button class="icon-btn messenger-btn" onclick="alert('💬 Messenger: You have 3 new messages')">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 2C6.48 2 2 6.02 2 10.99c0 2.83 1.46 5.35 3.74 7l-.8 3.01 3.11-1.63c1.22.34 2.53.52 3.95.52 5.52 0 10-4.03 10-9S17.52 2 12 2zm1.03 11.41l-2.54-2.71-4.96 2.71 5.46-5.79 2.63 2.71 4.87-2.71-5.46 5.79z"/>
    </svg>
    <span class="msg-badge">3</span>
</button>

        <!-- Notification icon -->
        <button class="icon-btn">
            <div class="notif-dot"></div>
            <svg width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
        </button>

        <div style="position:relative;">
            <button class="admin-pill" onclick="toggleAdminDD()">
                <div class="admin-avatar">AD</div>
                <span class="admin-label">Admin</span>
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>

            <div class="admin-dropdown" id="adminDD">
                <div class="dd-head">
                    <strong>Administrator</strong>
                    BazaarNepal Admin Panel
                </div>
                <a href="profile.php" class="dd-link">👤 My Profile</a>
                <a href="settings.php" class="dd-link">⚙️ Settings</a>
                <hr class="dd-sep">
                <a href="logout.php" class="dd-link danger">🚪 Logout</a>
            </div>
        </div>
    </div>
</header>



<script>
// Sidebar toggles
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('overlay').classList.remove('show');
}

// Admin dropdown
function toggleAdminDD() {
    document.getElementById('adminDD').classList.toggle('open');
}
document.addEventListener('click', function(e) {
    const dd = document.getElementById('adminDD');
    if (dd && !e.target.closest('.admin-pill')) {
        dd.classList.remove('open');
    }
});

// Real-time search filter for seller table
function filterTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const rows = document.querySelectorAll('#sellerTable tbody .seller-row');
    rows.forEach(row => {
        const name = row.querySelector('.seller-name')?.innerText.toLowerCase() || '';
        const shop = row.querySelector('.seller-shop')?.innerText.toLowerCase() || '';
        const email = row.querySelector('.seller-email')?.innerText.toLowerCase() || '';
        if (name.includes(filter) || shop.includes(filter) || email.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>
</body>
</html>