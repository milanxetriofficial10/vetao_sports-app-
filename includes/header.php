<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart_count    = count($_SESSION['cart']);
$user_name     = $_SESSION['user']['name'] ?? null;
$user_id       = $_SESSION['user']['id']   ?? null;
$profile_image = $_SESSION['user']['profile_image'] ?? null;

$user_initials = $user_name ? strtoupper(substr($user_name, 0, 2)) : 'SG';

// Load database only if needed and not already available
if (!isset($conn) && (($user_id && !$profile_image) || (isset($_SESSION['user']['id'])))) {
    include_once "../databases/db.php";
}
// Ensure $conn is defined for notification count (fallback)
if (!isset($conn) && file_exists("../databases/db.php")) {
    include_once "../databases/db.php";
}

if (!$profile_image && $user_id && isset($conn) && $conn instanceof mysqli) {
    $stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($r = $res->fetch_assoc()) {
        $profile_image = $r['profile_image'];
        $_SESSION['user']['profile_image'] = $profile_image;
    }
    $stmt->close();
}

$unread_count = 0;
if (isset($_SESSION['user']['id']) && isset($conn) && $conn instanceof mysqli) {
    $uid = (int)$_SESSION['user']['id'];
    $res = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id=$uid AND is_read=0");
    if ($res) {
        $unread_count = (int)$res->fetch_assoc()['cnt'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>JerseyGhar Nepal</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="shortcut icon" href="../img_logo/592152420_122294180810024667_2881232935832127715_n-modified.png" type="image/x-icon">
<style>
:root{
  --h:66px;
  --bg: #b94502;
  --bg-dark: #a83900;
  --bg-deeper:#8a2e00;
  --acc:#fde68a;
  --acc2:#fbbf24;
  --white:#ffffff;
  --white-80:rgba(255,255,255,0.80);
  --white-70:rgba(255,255,255,0.70);
  --white-40:rgba(255,255,255,0.40);
  --white-15:rgba(255,255,255,0.15);
  --white-10:rgba(255,255,255,0.10);
  --white-08:rgba(255,255,255,0.08);
  --shadow-md:0 20px 35px -12px rgba(0,0,0,0.35);
  --ease-spring:cubic-bezier(0.34,1.56,0.64,1);
  --ease-out:cubic-bezier(0.22,1,0.36,1);
  --f:'Barlow',sans-serif;
  --fc:'Barlow Condensed',sans-serif;
}
*{margin:0;padding:0;box-sizing:border-box;}

/* ── KEYFRAMES ── */
@keyframes slideDown{from{opacity:0;transform:translateY(-100%)}to{opacity:1;transform:translateY(0)}}
@keyframes popIn{0%{transform:scale(0) rotate(-20deg)}80%{transform:scale(1.2)}100%{transform:scale(1)}}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(253,230,138,.5)}50%{box-shadow:0 0 0 8px rgba(253,230,138,0)}}
@keyframes dropFade{from{opacity:0;transform:translateY(-12px) scale(0.96)}to{opacity:1;transform:translateY(0) scale(1)}}
@keyframes shimmer{from{background-position:200% center}to{background-position:-200% center}}

/* ══════════════════ NAVBAR BASE ══════════════════ */
.navbar{
  display:flex;align-items:center;justify-content:space-between;
  height:var(--h);padding:0 28px;
  background:linear-gradient(135deg, var(--bg) 0%, var(--bg-dark) 60%, var(--bg-deeper) 100%);
  border-bottom:1px solid rgba(255,255,255,0.12);
  position:sticky;top:0;z-index:1000;
  font-family:var(--f);
  animation:slideDown .45s var(--ease-out);
  box-shadow:0 6px 20px rgba(0,0,0,0.2);
}
.navbar::before{
  content:'';
  position:absolute;inset:0;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
  pointer-events:none;opacity:0.5;
}
.navbar::after{
  content:'';position:absolute;bottom:0;left:0;right:0;height:2px;
  background:linear-gradient(90deg,transparent 0%,rgba(253,230,138,0.5) 30%,rgba(253,230,138,0.9) 50%,rgba(253,230,138,0.5) 70%,transparent 100%);
}

/* ══════════════════ LOGO ══════════════════ */
.logo {
  display: flex;
  align-items: center;
  gap: 14px;
  text-decoration: none;
  flex-shrink: 0;
  position: relative;
  z-index: 1;
}
.logo-img-wrap {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  height: 70px;
  width: auto;
}
.logo-img {
  height: 100%;
  width: auto;
  max-width: 130px;
  border-radius: 14px;
  display: block;
  object-fit: contain;
}
.logo-img-fallback {
  width: 100%;
  height: 100%;
  min-width: 60px;
  min-height: 60px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.12);
  border: 2px solid rgba(253, 230, 138, 0.5);
  display: none;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(2px);
}
.logo-img-fallback svg {
  width: 34px;
  height: 34px;
  stroke: #fde68a;
  stroke-width: 1.5;
}
.logo-img.error-img {
  display: none;
}
.logo-img.error-img + .logo-img-fallback {
  display: flex;
}

.logo-sub {
  font-size: 12px;
  font-weight: 700;
  color: rgba(82, 225, 4, 0.97);
  letter-spacing: 3.5px;
  text-transform: uppercase;
  margin-top: 35px;
  white-space: nowrap;
}
@media (max-width: 480px) {
  .logo-img-wrap { height: 55px; }
  .logo-main { font-size: 20px; }
  .logo-sub { font-size: 8px; letter-spacing: 2.5px; }
  .logo { gap: 10px; }
}

/* ══════════════════ NAV LINKS — MODERN PILL DESIGN ══════════════════ */
.nav-links{
  display:flex;align-items:center;gap:4px;
  list-style:none;position:relative;z-index:1;
}
.nav-links a{
  display:flex;align-items:center;gap:8px;
  color:var(--white-70);text-decoration:none;
  font-size:14px;font-weight:600;
  padding:6px 16px;
  border-radius:40px;           /* pill shape */
  transition:all 0.25s var(--ease-out);
  white-space:nowrap;
  border:1px solid transparent;
  letter-spacing:0.3px;
}
.nav-links a i{font-size:12px;opacity:0.7;transition:opacity .2s;}
.nav-links a:hover{
  background:rgba(255,255,255,0.14);
  border-color:rgba(255,255,255,0.2);
  color:var(--white);
  transform:translateY(-1px);
}
.nav-links a.active{
  background:rgba(253,230,138,0.18);
  border-color:rgba(253,230,138,0.35);
  color:var(--acc);
  font-weight:700;
}
.nav-links a.active i{opacity:1;color:var(--acc);}
.nav-links a:hover i,.nav-links a.active i{opacity:1;}

/* ══════════════════ RIGHT SIDE / ICONS ══════════════════ */
.nav-right{
  display:flex;align-items:center;gap:8px;
  position:relative;z-index:1;
}

/* SEARCH BAR — refined */
.search-wrap{
  display:flex;align-items:center;gap:8px;
  background:rgba(0,0,0,0.2);
  border:1px solid rgba(255,255,255,0.2);
  border-radius:32px;
  padding:0 16px;
  height:38px;
  transition:all .3s var(--ease-out);
  width:170px;
  cursor:text;
}
.search-wrap:focus-within{
  background:rgba(0,0,0,0.3);
  border-color:rgba(253,230,138,0.7);
  box-shadow:0 0 0 3px rgba(253,230,138,0.1);
  width:220px;
}
.search-wrap i{
  color:rgba(255,255,255,0.5);
  font-size:13px;flex-shrink:0;
}
.search-wrap:focus-within i{color:var(--acc);}
.search-wrap input{
  background:transparent;border:none;outline:none;
  color:var(--white);font-size:13px;font-family:var(--f);
  font-weight:500;width:100%;
}
.search-wrap input::placeholder{color:rgba(255,255,255,0.45);font-weight:400;}

/* ICON BUTTONS */
.icon-btn{
  position:relative;
  width:40px;height:40px;
  border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  color:rgba(255,255,255,0.8);
  cursor:pointer;background:transparent;border:none;
  transition:all .2s var(--ease-spring);
}
.icon-btn:hover{
  color:var(--white);
  background:rgba(255,255,255,0.12);
  transform:translateY(-2px);
}
.icon-btn:active{transform:scale(0.92);}
.icon-btn i{font-size:19px;}

.cart-badge{
  position:absolute;top:-4px;right:-4px;
  min-width:18px;height:18px;
  background:var(--acc);color:#7a3800;
  font-size:10px;font-weight:800;
  font-family:var(--fc);
  border-radius:40px;padding:0 5px;
  display:flex;align-items:center;justify-content:center;
  border:2px solid var(--bg-dark);
  animation:popIn .35s var(--ease-spring);
}
.notif-dot{
  position:absolute;top:8px;right:8px;
  width:8px;height:8px;
  background:var(--acc);border-radius:50%;
  border:1.5px solid var(--bg-dark);
  animation:pulse 2.2s infinite;
}

/* USER BUTTON (avatar) */
.user-btn{
  position:relative;
  width:40px;height:40px;
  border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;
  background:rgba(255,255,255,0.08);
  border:1px solid rgba(255,255,255,0.2);
  transition:all .22s var(--ease-spring);
}
.user-btn:hover{
  background:rgba(255,255,255,0.16);
  border-color:rgba(253,230,138,0.5);
  transform:translateY(-2px);
}
.user-btn:active{transform:scale(0.94);}
.u-avatar{
  width:32px;height:32px;border-radius:10px;
  background:linear-gradient(135deg,rgba(253,230,138,0.8),rgba(249,115,22,0.6));
  display:flex;align-items:center;justify-content:center;
  font-size:11px;font-weight:800;font-family:var(--fc);
  color:var(--bg-deeper);overflow:hidden;
  background-size:cover;background-position:center;
  letter-spacing:0.5px;
}

/* ══════════════════ DROPDOWN — ENHANCED DESIGN ══════════════════ */
.dropdown{
  position:absolute;
  top:calc(var(--h) + 8px);right:20px;
  background:rgba(15,8,3,0.96);
  backdrop-filter:blur(24px);
  border-radius:20px;
  width:260px;
  padding:10px;
  box-shadow:var(--shadow-md), 0 0 0 1px rgba(253,230,138,0.1);
  display:none;flex-direction:column;gap:4px;
  animation:dropFade 0.2s var(--ease-out);
  z-index:1050;
  border:1px solid rgba(255,255,255,0.15);
  transform-origin: top right;
}
.dropdown.show{display:flex;}

/* user header inside dropdown */
.dd-user-info{
  display:flex;align-items:center;gap:12px;
  padding:12px 12px 16px;
  border-bottom:1px solid rgba(255,255,255,0.1);
  margin-bottom:6px;
}
.dd-avatar-lg{
  width:46px;height:46px;border-radius:16px;
  background:linear-gradient(135deg,rgba(253,230,138,0.8),rgba(201,75,1,0.7));
  display:flex;align-items:center;justify-content:center;
  font-size:15px;font-weight:800;font-family:var(--fc);
  color:#6b2900;flex-shrink:0;overflow:hidden;
  background-size:cover;background-position:center;
  box-shadow:0 2px 6px rgba(0,0,0,0.2);
}
.dd-user-text{flex:1;}
.dd-user-name{
  font-size:15px;font-weight:700;color:var(--white);
  letter-spacing:-0.2px;
}
.dd-user-role{
  font-size:10px;color:rgba(253,230,138,0.7);
  font-weight:600;text-transform:uppercase;margin-top:2px;
}
.guest-header{
  padding:12px 12px 8px;
  border-bottom:1px solid rgba(255,255,255,0.08);
  margin-bottom:6px;
}
.guest-header span{
  font-size:11px;color:rgba(255,255,255,0.5);
  text-transform:uppercase;letter-spacing:1.2px;font-weight:700;
}
.dropdown a{
  display:flex;align-items:center;gap:12px;
  color:rgba(255,255,255,0.7);text-decoration:none;
  padding:10px 12px;border-radius:14px;
  font-size:13.5px;font-weight:500;
  transition:all 0.2s;
}
.dropdown a .dd-ico{
  width:32px;height:32px;border-radius:10px;
  background:rgba(255,255,255,0.05);
  display:flex;align-items:center;justify-content:center;
  font-size:14px;color:rgba(255,255,255,0.5);
  flex-shrink:0;transition:all 0.2s;
}
.dropdown a:hover{
  background:rgba(201,75,1,0.25);
  color:var(--white);
  transform:translateX(4px);
}
.dropdown a:hover .dd-ico{
  background:rgba(201,75,1,0.5);
  color:var(--acc);
}
.dd-sep{
  height:1px;background:rgba(255,255,255,0.08);
  margin:6px 0;
}
.dd-count{
  margin-left:auto;background:rgba(201,75,1,0.4);
  color:var(--acc);font-size:10px;font-weight:700;
  padding:2px 8px;border-radius:30px;
}
.dd-logout{color:rgba(255,120,120,0.85) !important;}
.dd-logout .dd-ico{color:rgba(255,100,100,0.7) !important;}
.dd-logout:hover{background:rgba(255,60,60,0.12) !important;color:#ff9e9e !important;}

/* ══════════════════ HAMBURGER ══════════════════ */
.hamburger{
  display:none;flex-direction:column;
  gap:5px;cursor:pointer;
  padding:8px;border-radius:12px;
  transition:background .2s;
}
.hamburger:hover{background:rgba(255,255,255,0.1);}
.hamburger span{
  display:block;height:2px;
  background:rgba(255,255,255,0.8);
  border-radius:2px;
  transition:all .32s var(--ease-spring);transform-origin:center;
}
.hamburger span:nth-child(1){width:22px;}
.hamburger span:nth-child(2){width:15px;}
.hamburger span:nth-child(3){width:22px;}
.hamburger.open span:nth-child(1){width:22px;transform:translateY(7px) rotate(45deg);background:var(--acc);}
.hamburger.open span:nth-child(2){transform:scaleX(0);opacity:0;}
.hamburger.open span:nth-child(3){width:22px;transform:translateY(-7px) rotate(-45deg);background:var(--acc);}

/* ══════════════════ MOBILE DRAWER ══════════════════ */
.mob-drawer{
  font-family:var(--f);
  background:linear-gradient(180deg, rgba(140,45,0,0.98) 0%, rgba(26,10,0,0.98) 100%);
  backdrop-filter:blur(8px);
  max-height:0;overflow:hidden;
  transition:max-height .45s cubic-bezier(0.4,0,0.2,1);
  position:sticky;top:var(--h);z-index:999;
  border-top:1px solid rgba(255,255,255,0.1);
}
.mob-drawer.open{max-height:720px;}
.mob-inner{padding:16px 20px 32px;}
.mob-user-card{
  display:flex;align-items:center;gap:14px;
  background:rgba(255,255,255,0.08);
  border:1px solid rgba(255,255,255,0.12);
  border-radius:24px;
  padding:12px 16px;
  margin-bottom:16px;
}
.mob-avatar{
  width:48px;height:48px;border-radius:16px;
  background:linear-gradient(135deg,rgba(253,230,138,0.7),rgba(201,75,1,0.6));
  display:flex;align-items:center;justify-content:center;
  color:var(--bg-deeper);font-size:17px;font-weight:800;
  background-size:cover;background-position:center;
}
.mob-user-info small{
  font-size:10px;color:rgba(255,255,255,0.5);
  text-transform:uppercase;letter-spacing:1px;font-weight:700;
}
.mob-user-info strong{font-size:15px;color:var(--white);font-weight:700;}
.mob-search{
  display:flex;align-items:center;gap:12px;
  background:rgba(0,0,0,0.3);
  border:1px solid rgba(255,255,255,0.1);
  border-radius:40px;padding:12px 16px;margin-bottom:16px;
}
.mob-search:focus-within{border-color:rgba(253,230,138,0.6);}
.mob-search i{color:rgba(255,255,255,0.5);font-size:14px;}
.mob-search input{
  background:transparent;border:none;outline:none;
  color:var(--white);font-size:15px;width:100%;
}
.mob-link{
  display:flex;align-items:center;gap:14px;
  color:rgba(255,255,255,0.7);text-decoration:none;
  padding:12px 12px;border-radius:16px;
  font-size:15px;font-weight:600;
  margin-bottom:4px;transition:all .2s;
}
.mob-link .m-ico{
  width:36px;height:36px;border-radius:12px;
  background:rgba(255,255,255,0.05);
  display:flex;align-items:center;justify-content:center;
  font-size:14px;color:rgba(255,255,255,0.5);
}
.mob-link:hover,.mob-link.active{
  background:rgba(201,75,1,0.3);
  color:var(--white);
}
.mob-link:hover .m-ico,.mob-link.active .m-ico{
  background:rgba(201,75,1,0.45);
  color:var(--acc);
}
.mob-sep{height:1px;background:rgba(255,255,255,0.08);margin:12px 0;}
.mob-badge{
  margin-left:auto;background:var(--acc);
  color:var(--bg-deeper);font-size:10px;font-weight:800;
  padding:2px 9px;border-radius:20px;
}
.mob-logout{color:rgba(255,120,120,0.8) !important;}
/* Seller link additional style */
.seller-link{
    display:flex;
    align-items:center;
    position:relative;
    margin-right: 4px;
}
.seller-icon{
    width:42px;
    height:42px;
    border-radius:50%;
    border:2px solid #ffa559;
    transition:0.25s;
    object-fit: cover;
}
.seller-text{
    position:absolute;
    right:48px;
    background: #2c5e2a;
    color: #fff;
    padding:4px 12px;
    font-size:13px;
    font-weight:500;
    border-radius:30px;
    opacity:0;
    transform:translateX(8px);
    transition:0.25s;
    white-space:nowrap;
    box-shadow:0 2px 8px rgba(0,0,0,0.2);
    pointer-events:none;
}
.seller-link:hover .seller-text{
    opacity:1;
    transform:translateX(0);
}
.seller-link:hover .seller-icon{
    transform:scale(1.08);
    box-shadow:0 0 0 4px rgba(255,165,89,0.3);
}

/* RESPONSIVE */
@media(max-width:920px){
  .nav-links,.search-wrap{display:none !important;}
  .hamburger{display:flex;}
  .navbar{padding:0 18px;}
}
@media(max-width:500px){
  .logo-sub{display:none;}
  .logo-img-wrap{height:48px;}
  .nav-right{gap:4px;}
  .icon-btn{width:38px;height:38px;}
  .user-btn{width:38px;height:38px;}
  .seller-icon{width:36px;height:36px;}
}
</style>
</head>
<body>

<nav class="navbar">
  <!-- LOGO -->
  <a href="../publics/index.php" class="logo">
    <div class="logo-img-wrap">
      <img src="../img_logo/playzo.w.png"
           alt="JerseyGhar" class="logo-img"
           onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
      <span class="logo-sub"> Nepal</span>
    </div>
  </a>

  <!-- DESKTOP NAV PILLS -->
  <ul class="nav-links">
    <li><a href="../publics/index.php" class="nav-link"><i class="fa fa-house"></i> Home</a></li>
    <li><a href="#" class="nav-link">Jerseys</a></li>
    <li><a href="#" class="nav-link">Categories</a></li>
    <li><a href="#" class="nav-link">Trending</a></li>
    <li><a href="#" class="nav-link">Contact</a></li>
  </ul>

  <!-- seller link -->
  <div class="right">
    <a href="../sellers/seller_verify.php" class="seller-link" target="_blank">
        <span class="seller-text">Sell on JerseyGhar</span>
        <img src="../img_logo/sellerlogo22.jpg" class="seller-icon" alt="Seller Login">
    </a>
  </div>

  <!-- RIGHT SECTION -->
  <div class="nav-right">
    <!-- Search -->
    <div class="search-wrap">
      <i class="fa fa-magnifying-glass"></i>
      <input type="text" id="searchInput" placeholder="Search jerseys..." autocomplete="off">
    </div>

    <!-- Cart -->
    <a href="../publics/chart.php" class="icon-btn cart-btn" title="Cart">
      <i class="fa-solid fa-cart-arrow-down"></i>
      <?php if($cart_count > 0): ?>
        <span class="cart-badge"><?php echo $cart_count; ?></span>
      <?php endif; ?>
    </a>

    <!-- Notifications -->
    <a href="notifications.php" class="icon-btn" title="Notifications">
      <i class="fa fa-bell"></i>
      <?php if($unread_count > 0): ?>
        <span class="notif-dot"></span>
      <?php endif; ?>
    </a>

    <!-- User Avatar Button -->
    <div class="user-btn" id="userBtn" onclick="toggleDD()" title="My Account">
      <div class="u-avatar"
           style="<?php if($profile_image): ?>background-image:url('../<?php echo htmlspecialchars($profile_image); ?>');<?php endif; ?>">
        <?php if(!$profile_image): echo $user_initials; endif; ?>
      </div>
    </div>

    <!-- Hamburger menu -->
    <div class="hamburger" id="ham" onclick="toggleMob()">
      <span></span><span></span><span></span>
    </div>
  </div>
</nav>

<!-- DESKTOP DROPDOWN (refined) -->
<div class="dropdown" id="dd">
  <?php if($user_name): ?>
    <div class="dd-user-info">
      <div class="dd-avatar-lg"
           style="<?php if($profile_image): ?>background-image:url('../<?php echo htmlspecialchars($profile_image); ?>');<?php endif; ?>">
        <?php if(!$profile_image): echo $user_initials; endif; ?>
      </div>
      <div class="dd-user-text">
        <div class="dd-user-name"><?php echo htmlspecialchars($user_name); ?></div>
        <div class="dd-user-role">⚡ Member</div>
      </div>
    </div>
    <a href="profile.php"><span class="dd-ico"><i class="fa fa-circle-user"></i></span> My Profile</a>
    <a href="dashboard.php"><span class="dd-ico"><i class="fa fa-gauge-high"></i></span> Dashboard</a>
    <a href="../publics/chart.php"><span class="dd-ico"><i class="fa fa-bag-shopping"></i></span> My Cart
      <?php if($cart_count > 0): ?><span class="dd-count"><?php echo $cart_count; ?></span><?php endif; ?>
    </a>
    <a href="orders.php"><span class="dd-ico"><i class="fa fa-truck-fast"></i></span> My Orders</a>
    <div class="dd-sep"></div>
    <a href="logout.php" class="dd-logout"><span class="dd-ico"><i class="fa fa-right-from-bracket"></i></span> Logout</a>
  <?php else: ?>
    <div class="guest-header"><span>welcome back</span></div>
    <a href="login.php"><span class="dd-ico"><i class="fa fa-right-to-bracket"></i></span> Sign In</a>
    <a href="../publics/singup.php"><span class="dd-ico"><i class="fa fa-user-plus"></i></span> Create Account</a>
    <div class="dd-sep"></div>
    <a href="../publics/chart.php"><span class="dd-ico"><i class="fa fa-bag-shopping"></i></span> Shopping Cart
      <?php if($cart_count > 0): ?><span class="dd-count"><?php echo $cart_count; ?></span><?php endif; ?>
    </a>
  <?php endif; ?>
</div>

<!-- MOBILE DRAWER (responsive refined) -->
<div class="mob-drawer" id="mob">
  <div class="mob-inner">
    <div class="mob-user-card">
      <div class="mob-avatar"
           style="<?php if($profile_image): ?>background-image:url('../<?php echo htmlspecialchars($profile_image); ?>');<?php endif; ?>">
        <?php if(!$profile_image): echo $user_initials; endif; ?>
      </div>
      <div class="mob-user-info">
        <small><?php echo $user_name ? 'Logged in as' : 'Guest User'; ?></small>
        <strong><?php echo $user_name ? htmlspecialchars($user_name) : 'Sign in for better experience'; ?></strong>
      </div>
    </div>
    <div class="mob-search">
      <i class="fa fa-magnifying-glass"></i>
      <input type="text" placeholder="Search jerseys...">
    </div>
    <a href="../publics/index.php" class="mob-link"><span class="m-ico"><i class="fa fa-house"></i></span> Home</a>
    <a href="#" class="mob-link"><span class="m-ico"><i class="fa fa-shirt"></i></span> Jerseys</a>
    <a href="#" class="mob-link"><span class="m-ico"><i class="fa fa-layer-group"></i></span> Categories</a>
    <a href="#" class="mob-link"><span class="m-ico"><i class="fa fa-fire"></i></span> Trending</a>
    <a href="#" class="mob-link"><span class="m-ico"><i class="fa fa-envelope"></i></span> Contact</a>
    <div class="mob-sep"></div>
    <a href="../publics/chart.php" class="mob-link"><span class="m-ico"><i class="fa fa-bag-shopping"></i></span> Cart
      <?php if($cart_count > 0): ?><span class="mob-badge"><?php echo $cart_count; ?></span><?php endif; ?>
    </a>
    <a href="notifications.php" class="mob-link"><span class="m-ico"><i class="fa fa-bell"></i></span> Notifications
      <?php if($unread_count > 0): ?><span class="mob-badge"><?php echo $unread_count; ?></span><?php endif; ?>
    </a>
    <?php if($user_name): ?>
      <a href="profile.php" class="mob-link"><span class="m-ico"><i class="fa fa-circle-user"></i></span> Profile</a>
      <a href="dashboard.php" class="mob-link"><span class="m-ico"><i class="fa fa-gauge"></i></span> Dashboard</a>
      <a href="orders.php" class="mob-link"><span class="m-ico"><i class="fa fa-box"></i></span> My Orders</a>
      <div class="mob-sep"></div>
      <a href="logout.php" class="mob-link mob-logout"><span class="m-ico"><i class="fa fa-right-from-bracket"></i></span> Logout</a>
    <?php else: ?>
      <div class="mob-sep"></div>
      <a href="login.php" class="mob-link"><span class="m-ico"><i class="fa fa-right-to-bracket"></i></span> Login</a>
      <a href="../publics/singup.php" class="mob-link"><span class="m-ico"><i class="fa fa-user-plus"></i></span> Sign Up</a>
    <?php endif; ?>
  </div>
</div>

<script>
// Toggle user dropdown
function toggleDD(){
  const d=document.getElementById('dd');
  const b=document.getElementById('userBtn');
  if(!d) return;
  const isOpen = d.classList.toggle('show');
  if(b) b.classList.toggle('open', isOpen);
}
// Close dropdown on outside click
document.addEventListener('click',function(e){
  const btn=document.getElementById('userBtn');
  const dd=document.getElementById('dd');
  if(btn && dd && !btn.contains(e.target) && !dd.contains(e.target)){
    dd.classList.remove('show');
    btn.classList.remove('open');
  }
});
// Mobile drawer toggle
function toggleMob(){
  const mob=document.getElementById('mob');
  const ham=document.getElementById('ham');
  if(!mob || !ham) return;
  const isOpen = mob.classList.toggle('open');
  ham.classList.toggle('open', isOpen);
  document.body.style.overflow = isOpen ? 'hidden' : '';
}
// Close drawer on resize if > desktop
window.addEventListener('resize',function(){
  if(window.innerWidth > 920){
    const mob=document.getElementById('mob');
    const ham=document.getElementById('ham');
    if(mob && mob.classList.contains('open')) {
      mob.classList.remove('open');
      if(ham) ham.classList.remove('open');
      document.body.style.overflow = '';
    }
  }
});
// Active link highlight (desktop + mobile)
function setActiveLinks() {
  const currentUrl = window.location.pathname;
  const navLinks = document.querySelectorAll('.nav-links a, .mob-link');
  navLinks.forEach(link => {
    const href = link.getAttribute('href');
    if(href && href !== '#' && currentUrl.includes(href.replace(/^\.\.\/publics\//,''))) {
      link.classList.add('active');
    } else if(link.getAttribute('href') === '../publics/index.php' && (currentUrl === '/' || currentUrl.endsWith('index.php'))) {
      link.classList.add('active');
    } else {
      // prevent multiple active if not matched
      if(!link.classList.contains('mob-logout')) link.classList.remove('active');
    }
  });
}
setActiveLinks();
// Also handle search input (optional)
const searchInput = document.querySelector('#searchInput');
if(searchInput){
  searchInput.addEventListener('keypress', function(e){
    if(e.key === 'Enter'){
      let query = this.value.trim();
      if(query) window.location.href = `../publics/shop.php?search=${encodeURIComponent(query)}`;
    }
  });
}
const mobSearch = document.querySelector('.mob-search input');
if(mobSearch){
  mobSearch.addEventListener('keypress', function(e){
    if(e.key === 'Enter'){
      let query = this.value.trim();
      if(query) window.location.href = `../publics/shop.php?search=${encodeURIComponent(query)}`;
    }
  });
}
</script>
</body>
</html>