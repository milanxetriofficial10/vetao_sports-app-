<?php
session_start();
require_once "../databases/db.php";

$conn = getDB();
if (!$conn) die("Database connection failed.");

/* ── SESSION CHECK ── */
if (!isset($_SESSION['seller_id'])) {
    header("Location: ../sellers/login.php");
    exit;
}

$seller_id = (int)$_SESSION['seller_id'];

/* ── SELLER INFO ── */
$stmt = $conn->prepare("SELECT full_name, shop_name, status FROM sellers WHERE id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$seller = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$seller) { session_destroy(); header("Location: register.php"); exit; }

$seller_name = $seller['full_name'];
$shop_name   = $seller['shop_name'];
$status      = $seller['status'] ?? 'pending';
$_SESSION['status'] = $status;

$name_parts = explode(" ", trim($seller_name));
$first_name = $name_parts[0];
$initials   = strtoupper(substr($name_parts[0], 0, 1));
if (count($name_parts) > 1) $initials .= strtoupper(substr(end($name_parts), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seller Dashboard | SportsBazaar</title>
<link rel="shortcut icon" href="/../cropped_circle_image.png" type="image/x-icon">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}

:root {
    --brand:      #C94B01;
    --brand-dk:   #a33a00;
    --brand-lt:   #fff2ec;
    --bg:         #f0f2f5;
    --surface:    #ffffff;
    --border:     #e2e6ea;
    --text:       #1a1a2e;
    --muted:      #6c757d;
    --green:      #1a8a4a;
    --green-bg:   #edfaf3;
    --amber:      #b86a00;
    --amber-bg:   #fff7e6;
    --sidebar-w:  245px;
    --top-h:      64px;
}

body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

/* ─── SIDEBAR ─── */
.sidebar {
    position: fixed;
    top: 0; left: 0;
    width: var(--sidebar-w);
    height: 100vh;
    background: #c94b01;
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    z-index: 200;
    transition: transform 0.28s ease;
}

.sidebar-logo {
    background:  #c94b01;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: var(--top-h);
}

.sidebar-nav {
    padding: 20px 12px;
    flex: 1;
    overflow-y: auto;
}

.nav-label {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #f7f7f8;
    padding: 0 12px;
    margin: 18px 0 6px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 10px;
    text-decoration: none;
    color: #f1f5f1;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.15s, color 0.15s;
    position: relative;
}

.nav-item:hover {
    background: #c44b05;
    color: #3ff701;
}

.nav-item.active {
    background: #a44400;
    color: #fff;
}

.nav-item .nav-icon {
    width: 20px; height: 20px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.nav-item .badge-count {
    margin-left: auto;
    background: #24e102;
    color: #fff;
    font-size: 10px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 20px;
}

.sidebar-bottom {
    padding: 16px 12px;
    border-top: 1px solid var(--border);
}

.logout-nav {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 10px;
    color: var(--primary);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    background: #00fcc1;
    width: 100%;
    transition: background 0.15s;
    text-decoration: none;
}
.logout-nav:hover { background: var(--primary-lt); }

/* ─── TOPBAR ─── */
.topbar {
    position: fixed;
    top: 0;
    left: var(--sidebar-w);
    right: 0;
    height: var(--top-h);
    background: #c94b01;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    gap: 16px;
    z-index: 100;
}

.hamburger {
    display: none;
    width: 36px; height: 36px;
    border: none;
    background: rgba(255,255,255,0.15);
    border-radius: 8px;
    cursor: pointer;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    flex-shrink: 0;
}
.hamburger span {
    display: block;
    width: 18px; height: 2px;
    background: #fff;
    border-radius: 2px;
    transition: 0.25s;
}

.topbar-title {
    font-family: 'Sora', sans-serif;
    font-weight: 600;
    font-size: 16px;
    color: #f1f5f1;
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.topbar-title small {
    font-size: 12px;
    font-weight: normal;
    opacity: 0.85;
    margin-left: 6px;
}

.search-bar {
    display: flex;
    align-items: center;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 0 12px;
    gap: 8px;
    height: 38px;
    width: 220px;
    transition: width 0.2s, border-color 0.2s;
}
.search-bar:focus-within {
    border-color: var(--brand);
    width: 260px;
}
.search-bar svg { flex-shrink: 0; color: #6c757d; }
.search-bar input {
    border: none;
    background: transparent;
    font-size: 13px;
    font-family: 'DM Sans', sans-serif;
    color: var(--text);
    outline: none;
    width: 100%;
}
.search-bar input::placeholder { color: #9aa3ae; }

.topbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.icon-btn {
    width: 40px; height: 40px;
    border: none;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    transition: background 0.15s, color 0.15s;
    position: relative;
}
.icon-btn:hover { background: rgba(255,255,255,0.15); color: #fff; }

.notif-dot {
    position: absolute;
    top: 6px; right: 6px;
    width: 7px; height: 7px;
    background: #ffc107;
    border-radius: 50%;
    border: 1.5px solid #c94b01;
}

.user-wrap {
    position: relative;
}

.user-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    background: transparent;
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 40px;
    padding: 4px 10px 4px 4px;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
}
.user-pill:hover {
    background: rgba(255,255,255,0.1);
    border-color: rgba(255,255,255,0.5);
}

.avatar {
    width: 30px; height: 30px;
    border-radius: 50%;
    background: #fff;
    color: var(--brand);
    font-size: 11px;
    font-weight: 800;
    font-family: 'Sora', sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.user-label {
    font-size: 13px;
    font-weight: 500;
    color: #fff;
    max-width: 90px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.user-pill svg {
    transition: transform 0.2s;
    color: rgba(255,255,255,0.9);
}

.dd-menu {
    display: none;
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    box-shadow: 0 16px 40px rgba(0,0,0,.12);
    min-width: 200px;
    overflow: hidden;
    z-index: 600;
}
.dd-menu.open { display: block; }
.dd-head {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}
.dd-uname { font-weight: 600; font-size: 14px; color: var(--text); }
.dd-shop  { font-size: 12px; color: var(--muted); }
.dd-link {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 9px 16px;
    font-size: 13px;
    color: var(--muted);
    text-decoration: none;
    transition: background .12s, color .12s;
}
.dd-link:hover { background: var(--bg); color: var(--text); }
.dd-link.danger:hover { background: #fef2f2; color: var(--brand); }
.dd-sep { border: none; border-top: 1px solid var(--border); margin: 4px 0; }

/* ═══ MAIN ═══ */
.main {
    margin-left: var(--sidebar-w);
    margin-top: var(--top-h);
    padding: 28px 26px;
    min-height: calc(100vh - var(--top-h));
}

.welcome-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 28px 26px;
    max-width: 720px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
}
.welcome-card h1 {
    font-family: 'Sora', sans-serif;
    font-size: 24px;
    font-weight: 700;
    color: var(--brand);
    margin-bottom: 8px;
}
.welcome-card p {
    font-size: 14px;
    color: var(--muted);
    line-height: 1.5;
    margin-bottom: 18px;
}
.shop-badge {
    background: var(--brand-lt);
    color: var(--brand);
    padding: 6px 14px;
    border-radius: 40px;
    font-size: 13px;
    font-weight: 500;
    display: inline-block;
}

.overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 250;
}
.overlay.show { display: block; }

/* RESPONSIVE */
@media (max-width:860px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open {
        transform: translateX(0);
        box-shadow: 6px 0 24px rgba(0,0,0,.25);
    }
    .topbar {
        left: 0;
        padding: 0 16px;
    }
    .main {
        margin-left: 0;
        padding: 18px 14px;
    }
    .hamburger { display: flex; }
    .user-label { display: none; }
    .user-pill { padding: 4px; }
    .search-bar { display: none; }
    .topbar-title small { display: none; }
    .topbar-right { gap: 8px; }
}


.logo-img {
    width: 150px; height: 50px;
    
    flex-shrink: 0;

}
</style>
</head>
<body>

<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- ─── SIDEBAR ─── -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img class="logo-img" src="../img_logo/playzo.w.png" alt="SportsBazaar Logo">
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Main</div>
        <a href="seller_dashboard.php" class="nav-item active">
            <span class="nav-icon">🏠</span> Dashboard
        </a>

        <?php if ($status === 'approved'): ?>
            <!-- ADD PRODUCTS SECTION - DROPDOWN REMOVED, NOW SEPARATE LINKS -->
            <div class="nav-label">Add Products</div>
            <a href="../sellers/add_jersey.php" class="nav-item">
                <span class="nav-icon">👕</span> Add Jersey
            </a>
            <a href="../sellers/add_ball.php" class="nav-item">
                <span class="nav-icon">⚽</span> Add Ball
            </a>
            <a href="../sellers/add_boot.php" class="nav-item">
                <span class="nav-icon">👟</span> Add Boot
            </a>
            <a href="../sellers/cricket_bat.php" class="nav-item">
                <span class="nav-icon">🏏</span> Add Cricket Bat
            </a>

            <a href="../sellers/add_gymitem.php" class="nav-item">
                <span class="nav-icon">💪</span> Gym & Fitness Item
            </a>
            <a href="../sellers/add_batminton.php" class="nav-item">
                <span class="nav-icon">🏸</span> Add Badminton Item
            </a>
               <a href="../sellers/add_boxing.php" class="nav-item">
                <span class="nav-icon">🥊</span> Add Boxing Item
            </a>
            <div class="nav-label">Sales</div>
            <a href="seller_order.php" class="nav-item">
                <span class="nav-icon">🛒</span> Customer Orders
                <span class="badge-count">3</span>
            </a>
            <a href="analytics.php" class="nav-item">
                <span class="nav-icon">📢</span> Order Infrom
            </a>

            <div class="nav-label">Shipping / Delivery</div>
            <a href="delivery_partners.php" class="nav-item">
                <span class="nav-icon">🤝</span> Delivery Partners
            </a>
            <a href="shipping_charges.php" class="nav-item">
                <span class="nav-icon">💵</span> Shipping Charges
            </a>

            <div class="nav-label">Earnings / Payments</div>
            <a href="withdraw.php" class="nav-item">
                <span class="nav-icon">🏦</span> Withdraw Funds
            </a>
            <a href="commission.php" class="nav-item">
                <span class="nav-icon">🧾</span> Commission Details
            </a>

            <div class="nav-label">Customers</div>
            <a href="customer_list.php" class="nav-item">
                <span class="nav-icon">👥</span> Customer List
            </a>
            <a href="messages.php" class="nav-item">
                <span class="nav-icon">💬</span> Messages/Chats
            </a>

            <div class="nav-label">Account Settings</div>
            <a href="profile.php" class="nav-item">
                <span class="nav-icon">👤</span> Profile Info
            </a>
            <a href="shop_details.php" class="nav-item">
                <span class="nav-icon">🏪</span> Shop Details
            </a>

            <a href="../publics/index.php" class="nav-item">
                <span class="nav-icon">🏠</span> Back To Home
            </a>

            <div class="nav-label">Seller Support</div>
            <a href="help_center.php" class="nav-item">
                <span class="nav-icon">❓</span> Help Center
            </a>
            <a href="admin_contact.php" class="nav-item">
                <span class="nav-icon">📞</span> Contact Admin
            </a>
            <a href="seller_policies.php" class="nav-item">
                <span class="nav-icon">📜</span> Seller Policies
            </a>
            <a href="faq.php" class="nav-item">
                <span class="nav-icon">💡</span> FAQ
            </a>
        <?php else: ?>
            <div class="nav-label">Account</div>
            <a href="../sellers/seller_edit.php" class="nav-item">
                <span class="nav-icon">👤</span> Profile
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-bottom">
        <a href="seller_logout.php" class="logout-nav">
            <span style="font-size:16px;">🚪</span> Logout
        </a>
    </div>
</aside>

<!-- TOPBAR -->
<header class="topbar">
    <button class="hamburger" onclick="toggleSidebar()">
        <span></span><span></span><span></span>
    </button>
    <div class="topbar-title">
        Dashboard
        <small>Welcome back, <?= htmlspecialchars($first_name) ?></small>
    </div>
    <div class="search-bar">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" placeholder="Search...">
    </div>
    <div class="topbar-right">
        <button class="icon-btn">
            <div class="notif-dot"></div>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
        </button>
        <div class="user-wrap">
            <button class="user-pill" onclick="toggleDD()">
                <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                <span class="user-label"><?= htmlspecialchars($first_name) ?></span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.8)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>
            <div class="dd-menu" id="ddMenu">
                <div class="dd-head">
                    <div class="avatar" style="width:38px;height:38px;font-size:14px;"><?= htmlspecialchars($initials) ?></div>
                    <div>
                        <div class="dd-uname"><?= htmlspecialchars($seller_name) ?></div>
                        <div class="dd-shop">🏪 <?= htmlspecialchars($shop_name) ?></div>
                    </div>
                </div>
                <a href="profile.php" class="dd-link">👤 My Profile</a>
                <a href="settings.php" class="dd-link">⚙️ Settings</a>
                <hr class="dd-sep">
                <a href="seller_logout.php" class="dd-link danger">🚪 Logout</a>
            </div>
        </div>
    </div>
</header>



<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('overlay').classList.remove('show');
}
function toggleDD() {
    document.getElementById('ddMenu').classList.toggle('open');
}
document.addEventListener('click', function(e) {
    var w = document.querySelector('.user-wrap');
    if (w && !w.contains(e.target)) document.getElementById('ddMenu').classList.remove('open');
});
</script>

</body>
</html>