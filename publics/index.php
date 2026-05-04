<?php
session_start();
require_once "../databases/db.php";
$conn = getDB();
include "../includes/header.php";

/* ================= SAFE COLUMN CHECKER ================= */
function columnExists($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($check && $check->num_rows > 0);
}

/* ================= AUTO FIX MISSING COLUMNS ================= */

/* jerseys */
if (!columnExists($conn, "jerseys", "is_top")) {
    $conn->query("ALTER TABLE jerseys ADD COLUMN is_top TINYINT(1) DEFAULT 0");
}
if (!columnExists($conn, "jerseys", "views")) {
    $conn->query("ALTER TABLE jerseys ADD COLUMN views INT DEFAULT 0");
}
if (!columnExists($conn, "jerseys", "created_at")) {
    $conn->query("ALTER TABLE jerseys ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

/* sport_items */
if (!columnExists($conn, "sport_items", "is_top")) {
    $conn->query("ALTER TABLE sport_items ADD COLUMN is_top TINYINT(1) DEFAULT 0");
}
if (!columnExists($conn, "sport_items", "views")) {
    $conn->query("ALTER TABLE sport_items ADD COLUMN views INT DEFAULT 0");
}
if (!columnExists($conn, "sport_items", "created_at")) {
    $conn->query("ALTER TABLE sport_items ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

/* boot */
if (!columnExists($conn, "boot", "is_top")) {
    $conn->query("ALTER TABLE boot ADD COLUMN is_top TINYINT(1) DEFAULT 0");
}
if (!columnExists($conn, "boot", "views")) {
    $conn->query("ALTER TABLE boot ADD COLUMN views INT DEFAULT 0");
}
if (!columnExists($conn, "boot", "rating")) {
    $conn->query("ALTER TABLE boot ADD COLUMN rating FLOAT DEFAULT 0");
}
if (!columnExists($conn, "boot", "sport_type")) {
    $conn->query("ALTER TABLE boot ADD COLUMN sport_type VARCHAR(100) DEFAULT 'Football'");
}
if (!columnExists($conn, "boot", "discount_percent")) {
    $conn->query("ALTER TABLE boot ADD COLUMN discount_percent FLOAT DEFAULT 0");
}
if (!columnExists($conn, "boot", "sold_out")) {
    $conn->query("ALTER TABLE boot ADD COLUMN sold_out TINYINT(1) DEFAULT 0");
}
if (!columnExists($conn, "boot", "is_new")) {
    $conn->query("ALTER TABLE boot ADD COLUMN is_new TINYINT(1) DEFAULT 0");
}
if (!columnExists($conn, "boot", "image")) {
    $conn->query("ALTER TABLE boot ADD COLUMN image VARCHAR(500) DEFAULT ''");
}
if (!columnExists($conn, "boot", "created_at")) {
    $conn->query("ALTER TABLE boot ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

/* cricket_bats */
if (!columnExists($conn, "cricket_bats", "sport_type")) {
    $conn->query("ALTER TABLE cricket_bats ADD COLUMN sport_type VARCHAR(50) DEFAULT 'Cricket'");
}
if (!columnExists($conn, "cricket_bats", "rating")) {
    $conn->query("ALTER TABLE cricket_bats ADD COLUMN rating FLOAT DEFAULT 0");
}
if (!columnExists($conn, "cricket_bats", "views")) {
    $conn->query("ALTER TABLE cricket_bats ADD COLUMN views INT DEFAULT 0");
}
if (!columnExists($conn, "cricket_bats", "is_top")) {
    $conn->query("ALTER TABLE cricket_bats ADD COLUMN is_top TINYINT(1) DEFAULT 0");
}
if (!columnExists($conn, "cricket_bats", "is_new")) {
    $conn->query("ALTER TABLE cricket_bats ADD COLUMN is_new TINYINT(1) DEFAULT 0");
}
if (!columnExists($conn, "cricket_bats", "created_at")) {
    $conn->query("ALTER TABLE cricket_bats ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

/* ================= VIEW TRACKING ================= */
if (isset($_GET['view_id'])) {
    $vid = intval($_GET['view_id']);
    if (!isset($_SESSION['viewed'])) {
        $_SESSION['viewed'] = [];
    }
    if (!isset($_SESSION['viewed'][$vid])) {
        $_SESSION['viewed'][$vid] = true;
        $conn->query("UPDATE jerseys SET views = views + 1 WHERE id = $vid");
    }
    header("Location: jersey_details.php?id=" . $vid);
    exit;
}

/* ================= SLIDER ================= */
$slider = $conn->query("SELECT * FROM sliders ORDER BY id DESC");

/* ================= JERSEYS ================= */
$all_cards = [];
$result2 = $conn->query("SELECT *, created_at FROM jerseys ORDER BY id DESC");
if ($result2) {
    while ($row2 = $result2->fetch_assoc()) {
        $row2['_source'] = 'jersey';
        $all_cards[] = $row2;
    }
}

/* ================= SPORT ITEMS ================= */
$all_sitems = [];
$si_result = $conn->query("SELECT *, created_at FROM sport_items ORDER BY id DESC");
if ($si_result) {
    while ($si = $si_result->fetch_assoc()) {
        $si['_source'] = 'item';
        $all_sitems[] = $si;
    }
}

/* ================= BOOTS ================= */
$all_boots = [];
$boot_result = $conn->query("SELECT *, created_at FROM boot ORDER BY id DESC");
if ($boot_result) {
    while ($boot = $boot_result->fetch_assoc()) {
        $boot['_source'] = 'boot';
        $all_boots[] = $boot;
    }
}

/* ================= CRICKET BATS ================= */
$all_bats = [];
$bat_result = $conn->query("SELECT *, created_at FROM cricket_bats WHERE visible = 1 ORDER BY id DESC");
if ($bat_result) {
    while ($bat = $bat_result->fetch_assoc()) {
        $bat['_source'] = 'bat';
        $all_bats[] = $bat;
    }
}

/* ================= MERGE ALL PRODUCTS AND SORT BY CREATED_AT (NEWEST FIRST) ================= */
$all_products = array_merge($all_cards, $all_sitems, $all_boots, $all_bats);
usort($all_products, function($a, $b) {
    $timeA = strtotime($a['created_at'] ?? '1970-01-01');
    $timeB = strtotime($b['created_at'] ?? '1970-01-01');
    if ($timeA == $timeB) {
        return $b['id'] - $a['id'];
    }
    return $timeB - $timeA;
});
$total_cards = count($all_products);

/* ================= CORRECTED IMAGE PATH FUNCTION ================= */
function getImagePath($row, $source) {
    // Get the stored image path
    if ($source === 'boot') {
        $img = $row['main_image'] ?? '';
    } elseif ($source === 'bat') {
        $img = $row['main_image'] ?? '';
    } else {
        $img = $row['image'] ?? '';
    }
    
    // If no image, return placeholder
    if (empty($img)) {
        return 'https://placehold.co/400x300?text=No+Image';
    }
    
    // If it's already an absolute URL (http/https), return as is
    if (preg_match('/^https?:\/\//i', $img)) {
        return $img;
    }
    
    // Remove any leading slash for consistency
    $img = ltrim($img, '/');
    
    // If the path already starts with '../' (relative from current file), keep it
    if (strpos($img, '../') === 0) {
        return $img;
    }
    
    // All images are stored in the root 'uploads' folder (one level above 'publics')
    // From publics/index.php, we need to go up one level: '../uploads/...'
    // The stored path is like 'uploads/boots/...' or 'uploads/jerseys/...'
    return '../' . $img;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>SportGhar — Nepal's #1 Sports Store</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800;900&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ---------- BASE STYLES (IMPROVED FOR SIMPLICITY, MOBILE) ---------- */
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;min-height:100vh;background:#f0f2f5;color:#1a1a2e;}
.sw{position:relative;width:100%;height:420px;overflow:hidden;border-radius:0 0 28px 28px;box-shadow:0 8px 40px rgba(0,0,0,.18);}
.sw-track{display:flex;height:100%;transition:transform .75s cubic-bezier(.77,0,.18,1);}
.sw-slide{min-width:100%;position:relative;flex-shrink:0;}
.sw-slide img{width:100%;height:100%;object-fit:cover;filter:brightness(.38);}
.sw-overlay{position:absolute;inset:0;display:flex;flex-direction:column;justify-content:center;align-items:flex-start;text-align:left;padding:24px 36px;}
.sw-tag{display:inline-block;background:rgba(249,115,22,.92);color:#fff;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:6px 18px;border-radius:30px;margin-bottom:14px;font-family:'Sora',sans-serif;}
.sw-title{font-size:44px;font-weight:900;color:#fff;line-height:1.08;margin-bottom:12px;text-shadow:0 4px 28px rgba(0,0,0,.65);font-family:'Sora',sans-serif;max-width:80%;}
.sw-desc{font-size:15px;color:#cbd5e1;max-width:520px;line-height:1.7;margin-bottom:22px;}
.sw-btn{display:inline-flex;align-items:center;gap:9px;padding:13px 32px;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;text-decoration:none;border-radius:50px;font-weight:700;font-size:14px;font-family:'Sora',sans-serif;border:none;cursor:pointer;transition:.3s;box-shadow:0 8px 24px rgba(234,88,12,.45);}
.sw-btn:hover{transform:translateY(-3px);box-shadow:0 14px 32px rgba(234,88,12,.6);}
/* SIMPLER SLIDER BUTTONS - LESS DESIGN */
.sw-arr{position:absolute;top:50%;transform:translateY(-50%);z-index:20;width:40px;height:40px;border-radius:40px;background:rgba(0,0,0,0.6);color:#fff;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.25s;border:none;}
.sw-arr:hover{background:#f97316;}
.sw-arr.l{left:16px;}.sw-arr.r{right:16px;}
.sw-prog{position:absolute;bottom:0;left:0;height:3px;background:linear-gradient(90deg,#f97316,#fbbf24);transition:width .1s linear;z-index:20;}
.sw-dots{position:absolute;bottom:16px;left:50%;transform:translateX(-50%);display:flex;gap:9px;z-index:20;}
.sw-dot{width:9px;height:9px;border-radius:50%;background:rgba(255,255,255,.3);cursor:pointer;transition:.35s;}
.sw-dot.on{background:#f97316;width:26px;border-radius:5px;}
.sw-counter{position:absolute;top:18px;right:20px;z-index:20;background:rgba(0,0,0,.48);color:#fff;font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px;backdrop-filter:blur(6px);}
.sec-hd{text-align:center;padding:38px 20px 6px;}
.sec-hd h2{font-size:24px;font-weight:800;color:#111827;margin-bottom:5px;font-family:'Sora',sans-serif;letter-spacing:-.3px;}
.sec-hd p{font-size:13.5px;color:#6b7280;}
.sec-line{width:44px;height:3px;background:linear-gradient(90deg,#f97316,#fbbf24);border-radius:2px;margin:10px auto 0;}
.ftabs-wrap{position:relative;padding:18px 0 6px;}
.ftabs-wrap::before{content:'';position:absolute;left:0;top:0;bottom:0;width:40px;background:linear-gradient(90deg,#f0f2f5,transparent);z-index:5;pointer-events:none;}
.ftabs-wrap::after{content:'';position:absolute;right:0;top:0;bottom:0;width:40px;background:linear-gradient(270deg,#f0f2f5,transparent);z-index:5;pointer-events:none;}
.ftabs{display:flex;gap:7px;overflow-x:auto;padding:4px 22px 10px;scrollbar-width:none;-ms-overflow-style:none;scroll-behavior:smooth;-webkit-overflow-scrolling:touch;}
.ftabs::-webkit-scrollbar{display:none;}
.ftab{flex-shrink:0;padding:7px 16px;border-radius:30px;font-size:12px;font-weight:600;cursor:pointer;font-family:'Sora',sans-serif;border:1.5px solid #e5e7eb;background:#fff;color:#6b7280;transition:.2s;white-space:nowrap;display:flex;align-items:center;gap:5px;box-shadow:0 1px 4px rgba(0,0,0,.05);}
.ftab i{font-size:11px;}
.ftab:hover{border-color:#f97316;color:#f97316;background:#fff7ed;}
.ftab.on{background:#f97316;border-color:#f97316;color:#fff;font-weight:700;box-shadow:0 4px 12px rgba(249,115,22,.32);}
.result-info{text-align:center;font-size:12.5px;color:#9ca3af;margin-bottom:4px;padding:0 20px;}
.result-info span{color:#111827;font-weight:700;}
.cgrid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;max-width:1500px;margin:0 auto;padding:14px 20px 40px;align-items:start;}
.card{background:#fff;border-radius:16px;border:1.5px solid #065af5;overflow:hidden;position:relative;opacity:0;transform:translateY(18px) scale(.98);transition:opacity .38s ease, transform .35s ease, box-shadow .28s ease, border-color .28s ease;box-shadow:0 2px 10px rgba(0,0,0,.06);cursor:pointer;text-decoration:none;display:block;}
/* Remove fixed height, let content decide */
.card.visible{opacity:1;transform:translateY(0) scale(1);}
.card.visible:hover{transform:translateY(-3px) scale(1.02);box-shadow:0 28px 55px rgba(249,115,22,.16),0 8px 24px rgba(0,0,0,.1),0 0 0 2px rgba(249,115,22,.25);border-color:#fdba74;z-index:10;}
.card.visible:hover .cimg img{transform:scale(1.05);}
.card.visible:hover .ctitle{color:#ea580c;}
.card-top{border-color:#fdba74;}
.top-pick-banner{display:flex;align-items:center;justify-content:center;gap:5px;background:linear-gradient(90deg,#f97316,#ea580c);color:#fff;font-size:9.5px;font-weight:700;letter-spacing:1.6px;text-transform:uppercase;padding:5px 0;font-family:'Sora',sans-serif;}
.cimg{position:relative;overflow:hidden;background:#f8f8f8;}
.cimg img{width:100%;height:212px;object-fit:cover;display:block;transition:transform .5s ease;}
.cbadge{position:absolute;top:9px;padding:3px 9px;font-size:9.5px;font-weight:800;border-radius:20px;letter-spacing:.4px;z-index:5;font-family:'Sora',sans-serif;}
.cb-disc{left:9px;background:#ef4444;color:#fff;}
.cb-hot{right:9px;background:linear-gradient(135deg,#f97316,#dc6806);color:#fff;}
.cb-new{right:9px;background:linear-gradient(135deg,#22c55e,#15803d);color:#fff;}
.cb-sell{right:9px;background:linear-gradient(135deg,#f97316,#dc2626);color:#fff;}
.cbody{padding:11px 12px 12px;}
.ctitle{font-weight:700;font-size:13px;color:#111827;font-family:'Sora',sans-serif;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px;transition:color .2s;}
.cclub{font-size:10.5px;color:#467843;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:8px;}
.cmeta{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;gap:5px;flex-wrap:wrap;}
.ctype{font-size:9.5px;font-weight:700;color:#f97316;background:#fff7ed;border:1px solid #fed7aa;padding:2px 7px;border-radius:20px;white-space:nowrap;flex-shrink:0;font-family:'Sora',sans-serif;}
.cprice{font-weight:800;font-size:14px;color:#111827;white-space:nowrap;text-align:right;font-family:'Sora',sans-serif;}
.cprice-old{font-size:10px;color:#f51f03;text-decoration:line-through;margin-right:2px;font-weight:400;}
.cfoot{border-top:1px solid #f3f4f6;padding-top:8px;display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:4px;}
.cviews{font-size:12px;color:#5b06fa;display:flex;align-items:center;gap:3px;}
.cviews i{color:#58f30b;font-size:9.5px;}
.cstars{color:#f03806;font-size:12px;letter-spacing:.7px;}
.csport{font-size:9px;font-weight:700;color:#6366f1;background:#eef2ff;border:1px solid #c7d2fe;padding:2px 6px;border-radius:9px;display:flex;align-items:center;gap:3px;white-space:nowrap;font-family:'Sora',sans-serif;}
.ctagline{margin-top:9px;padding:7px 10px;border:1.5px dashed #5ef826;border-radius:8px;background:linear-gradient(135deg, rgb(251, 248, 245) 0%, #f6f6f4 100%);display:flex;align-items:center;justify-content:center;gap:5px;font-size:8.5px;font-weight:700;color:#0f0f13;font-family:'Sora',sans-serif;letter-spacing:.5px;text-transform:uppercase;line-height:1.4;text-align:center;}
.ctagline i{font-size:8px;color:#f97316;flex-shrink:0;}
.pagination-wrap{display:flex;justify-content:center;align-items:center;gap:8px;padding:10px 20px 60px;flex-wrap:wrap;}
.pg-btn{display:inline-flex;align-items:center;justify-content:center;min-width:38px;height:38px;padding:0 12px;border-radius:9px;font-size:12.5px;font-weight:700;font-family:'Sora',sans-serif;text-decoration:none;cursor:pointer;border:1.5px solid #e5e7eb;background:#fff;color:#6b7280;transition:.2s;box-shadow:0 1px 4px rgba(0,0,0,.05);}
.pg-btn:hover{background:#fff7ed;border-color:#f97316;color:#f97316;}
.pg-btn.active{background:#f97316;border-color:#f97316;color:#fff;font-weight:800;box-shadow:0 4px 12px rgba(249,115,22,.32);}
.pg-btn.disabled{opacity:.3;pointer-events:none;}
.pg-ellipsis{color:#9ca3af;font-size:14px;padding:0 3px;line-height:38px;}
@keyframes shimmer{0%{background-position:-600px 0}100%{background-position:600px 0}}
.sk-box{background:linear-gradient(90deg,#efefef 25%,#e4e4e4 50%,#efefef 75%);background-size:1200px 100%;animation:shimmer 1.5s infinite linear;border-radius:8px;}
.card-skeleton{background:#fff;border-radius:16px;overflow:hidden;border:1.5px solid #ececec;box-shadow:0 2px 10px rgba(0,0,0,.05);}
.sk-img{height:170px;}
.sk-body{padding:11px 12px 12px;}
.sk-line{height:11px;margin-bottom:8px;}
.sk-line.w90{width:90%;}.sk-line.w60{width:60%;}.sk-line.w45{width:45%;}
.sk-btn{height:36px;border-radius:10px;margin-top:5px;}
#skeleton-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;max-width:1500px;margin:0 auto;padding:14px 20px 40px;}
.no-results{grid-column:1/-1;text-align:center;padding:60px 20px;color:#9ca3af;display:none;}
.no-results i{font-size:44px;color:#e5e7eb;margin-bottom:14px;display:block;}
.no-results h3{font-size:17px;font-weight:700;color:#6b7280;margin-bottom:7px;font-family:'Sora',sans-serif;}
.no-results p{font-size:13px;}
/* REDUCED GAP FOR LOCATION SECTION */
.loc-section{max-width:1500px;margin:0 auto 60px;padding:0 20px;}
.loc-hd{text-align:center;padding:48px 20px 28px;}
.loc-hd h2{font-size:24px;font-weight:800;color:#111827;font-family:'Sora',sans-serif;letter-spacing:-.3px;margin-bottom:6px;}
.loc-hd p{font-size:13.5px;color:#6b7280;}
.loc-hd .sec-line{width:44px;height:3px;background:linear-gradient(90deg,#f97316,#fbbf24);border-radius:2px;margin:10px auto 0;}
.loc-wrap{display:grid;grid-template-columns:1fr 380px;gap:16px;align-items:stretch;}
.loc-map{border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.10);border:1.5px solid #ececec;min-height:400px;background:#f8f8f8;position:relative;}
.loc-map iframe{width:100%;height:100%;min-height:400px;border:0;display:block;}
.loc-info{background:#fff;border-radius:20px;border:1.5px solid #ececec;box-shadow:0 4px 24px rgba(0,0,0,.08);padding:24px 20px;display:flex;flex-direction:column;gap:18px;}
.loc-brand{display:flex;align-items:center;gap:12px;padding-bottom:18px;border-bottom:1px solid #f3f4f6;}
.loc-brand-icon{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#f97316,#ea580c);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 12px rgba(249,115,22,.3);}
.loc-brand-icon i{font-size:20px;color:#fff;}
.loc-brand-text h3{font-size:16px;font-weight:800;color:#111827;font-family:'Sora',sans-serif;margin-bottom:2px;}
.loc-brand-text p{font-size:11.5px;color:#6b7280;}
.loc-row{display:flex;align-items:flex-start;gap:12px;}
.loc-row-icon{width:36px;height:36px;border-radius:10px;background:#fff7ed;border:1px solid #fed7aa;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;}
.loc-row-icon i{font-size:14px;color:#f97316;}
.loc-row-text strong{display:block;font-size:11.5px;font-weight:700;color:#374151;font-family:'Sora',sans-serif;margin-bottom:3px;letter-spacing:.2px;text-transform:uppercase;}
.loc-row-text span{font-size:13px;color:#111827;font-weight:500;line-height:1.5;}
.loc-hours-list{display:flex;flex-direction:column;gap:4px;margin-top:4px;}
.loc-hour-row{display:flex;justify-content:space-between;font-size:11.5px;}
.loc-hour-row .day{color:#6b7280;font-weight:500;}
.loc-hour-row .time{color:#111827;font-weight:600;font-family:'Sora',sans-serif;}
.loc-status{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:10.5px;font-weight:700;font-family:'Sora',sans-serif;letter-spacing:.5px;}
.loc-status.open{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;}
.loc-status.open::before{content:'';width:6px;height:6px;border-radius:50%;background:#22c55e;display:inline-block;}
.loc-dir-btn{display:flex;align-items:center;justify-content:center;gap:8px;padding:12px;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;border-radius:12px;font-size:13px;font-weight:700;font-family:'Sora',sans-serif;text-decoration:none;box-shadow:0 4px 14px rgba(249,115,22,.28);transition:.25s;margin-top:4px;}
.loc-dir-btn:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(234,88,12,.4);background:linear-gradient(135deg,#fb923c,#f97316);}
.loc-dir-btn i{font-size:14px;}
.loc-tagline{background:linear-gradient(135deg,#fff7ed,#ffedd5);border:1.5px dashed #fdba74;border-radius:10px;padding:10px 14px;display:flex;align-items:center;justify-content:center;gap:6px;font-size:9.5px;font-weight:700;color:#c2410c;font-family:'Sora',sans-serif;letter-spacing:.5px;text-transform:uppercase;text-align:center;}
.loc-tagline i{color:#f97316;font-size:9px;flex-shrink:0;}
/* ===== RESPONSIVE: TWO CARDS PER ROW, SINGLE-LINE TITLES ===== */
@media(max-width:1280px) {
    .cgrid, #skeleton-grid { grid-template-columns: repeat(4, 1fr); }
}
@media(max-width:1024px) {
    .cgrid, #skeleton-grid { grid-template-columns: repeat(3, 1fr); gap: 14px; }
}
@media(max-width:768px) {
    .cgrid, #skeleton-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        padding: 10px 12px 30px;
    }
    .card {
        height: auto;
        min-height: 340px;
        border-radius: 14px;
    }
    .cimg img {
        height: 170px;
        object-fit: cover;
    }
    .cbody {
        padding: 8px 10px 10px;
    }
    /* Force single line for title and club */
    .ctitle {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 12px;
        line-height: 1.3;
        margin-bottom: 4px;
    }
    .cclub {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 9px;
        margin-bottom: 6px;
    }
    .cmeta {
        flex-wrap: wrap;
        justify-content: space-between;
        margin-bottom: 6px;
        gap: 4px;
    }
    .ctype {
        font-size: 8px;
        padding: 2px 6px;
        white-space: nowrap;
    }
    .cprice {
        white-space: nowrap;
        font-size: 12px;
    }
    .cfoot {
        flex-wrap: wrap;
        gap: 4px;
        padding-top: 5px;
        margin-bottom: 6px;
    }
    .cviews, .csport, .cstars {
        font-size: 9px;
        white-space: nowrap;
    }
    .csport {
        white-space: nowrap;
    }
    .ctagline {
        font-size: 7px;
        padding: 4px 6px;
        margin-top: 5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    /* slider & location */
    .sw { height: 360px; }
    .sw-overlay { padding: 20px; }
    .sw-title { font-size: 32px; max-width: 100%; }
    .sw-desc { font-size: 13px; }
    .sw-arr { width: 32px; height: 32px; }
    .loc-wrap { grid-template-columns: 1fr; gap: 16px; }
    .loc-map { min-height: 260px; }
    .loc-map iframe { min-height: 260px; }
    .loc-info { padding: 18px; }
}
@media(max-width:550px) {
    .cgrid, #skeleton-grid {
        gap: 10px;
        padding: 8px 10px 25px;
    }
    .card {
        min-height: 300px;
    }
    .cimg img {
        height: 180px;
    }
    .ctitle {
        font-size: 11px;
    }
    .cclub {
        font-size: 8px;
    }
    .cprice {
        font-size: 11px;
    }
    .ctype {
        font-size: 7px;
    }
    .cviews, .csport, .cstars {
        font-size: 8px;
    }
    .ctagline {
        font-size: 6px;
        padding: 3px 4px;
    }
}
</style>
</head>
<body>

<!-- ==================== SLIDER ==================== -->
<?php
$slides_data = [];
while($row = $slider->fetch_assoc()) $slides_data[] = $row;
$total_slides = count($slides_data);
?>
<div class="sw" id="sw">
    <button class="sw-arr l" onclick="mv(-1)"><i class="fa fa-chevron-left"></i></button>
    <button class="sw-arr r" onclick="mv(1)"><i class="fa fa-chevron-right"></i></button>
    <div class="sw-counter" id="sw-ctr">1 / <?php echo $total_slides ?: 1; ?></div>
    <div class="sw-track" id="sw-trk">
    <?php if($total_slides > 0): foreach($slides_data as $i => $row): ?>
        <div class="sw-slide">
            <img src="<?php echo htmlspecialchars($row['image']); ?>" alt="">
            <div class="sw-overlay">
                <?php if(!empty($row['tag'])): ?>
                    <span class="sw-tag"><i class="fa fa-fire"></i> <?php echo htmlspecialchars($row['tag']); ?></span>
                <?php else: ?>
                    <span class="sw-tag"><i class="fa fa-star"></i> SportGhar Exclusive</span>
                <?php endif; ?>
                <h1 class="sw-title"><?php echo nl2br(htmlspecialchars($row['title'])); ?></h1>
                <p class="sw-desc"><?php echo htmlspecialchars($row['description']); ?></p>
                <?php if(!empty($row['link'])): ?>
                    <a href="<?php echo $row['link']; ?>" class="sw-btn"><i class="fa fa-bag-shopping"></i> Shop Now</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; else: ?>
        <div class="sw-slide">
            <img src="https://images.unsplash.com/photo-1551854838-212c50b4c184?w=1400&q=80" alt="">
            <div class="sw-overlay">
                <span class="sw-tag"><i class="fa fa-fire"></i> New Season 2025</span>
                <h1 class="sw-title">Nepal's #1<br>Sports Store</h1>
                <p class="sw-desc">Football, cricket, basketball ra sabai sports ko best gear — delivered to your door.</p>
                <a href="#" class="sw-btn"><i class="fa fa-bag-shopping"></i> Shop Now</a>
            </div>
        </div>
    <?php endif; ?>
    </div>
    <div class="sw-dots" id="sw-dots">
        <?php for($i=0;$i<max($total_slides,1);$i++): ?>
            <div class="sw-dot <?php echo $i==0?'on':''; ?>" onclick="go(<?php echo $i; ?>)"></div>
        <?php endfor; ?>
    </div>
    <div class="sw-prog" id="sw-prog"></div>
</div>

<!-- ==================== PRODUCTS ==================== -->
<div class="sec-hd">
    <h2><i class="fa fa-shirt" style="color:#f97316;margin-right:8px;"></i> Our Products</h2>
    <div class="sec-line"></div>
</div>

<!-- FILTER TABS -->
<div class="ftabs-wrap">
    <div class="ftabs" id="ftabs">
        <button class="ftab on" onclick="filterCards(this,'all')">All Products</button>
        <button class="ftab" onclick="filterCards(this,'Football')"><i class="fa fa-futbol"></i> Football Items</button>
        <button class="ftab" onclick="filterCards(this,'Cricket')"><i class="fa fa-cricket-bat-ball"></i> Cricket Items</button>
        <button class="ftab" onclick="filterCards(this,'Basketball')"><i class="fa fa-basketball"></i> Basketball Items</button>
        <button class="ftab" onclick="filterCards(this,'Badminton')"><i class="fa fa-table-tennis-paddle-ball"></i> Badminton Items</button>
        <button class="ftab" onclick="filterCards(this,'Volleyball')"><i class="fa fa-volleyball"></i> Volleyball Items</button>
        <button class="ftab" onclick="filterCards(this,'Tennis')"><i class="fa fa-baseball-bat-ball"></i> Tennis Items</button>
        <button class="ftab" onclick="filterCards(this,'Boxing')"><i class="fa fa-hand-fist"></i> Boxing Items</button>
        <button class="ftab" onclick="filterCards(this,'Cycling')"><i class="fa fa-bicycle"></i> Cycling Items</button>
        <button class="ftab" onclick="filterCards(this,'Rugby')"><i class="fa fa-football"></i> Rugby Items</button>
        <button class="ftab" onclick="filterCards(this,'Esports')"><i class="fa fa-gamepad"></i> Esports Items</button>
        <button class="ftab" onclick="filterCards(this,'Swimming')"><i class="fa fa-person-swimming"></i> Swimming Items</button>
        <button class="ftab" onclick="filterCards(this,'Gym')"><i class="fa fa-dumbbell"></i> Gym & Fitness Items</button>
    </div>
</div>

<!-- RESULT INFO -->
<p class="result-info">
    Showing <span id="showing-count">0</span> of
    <span id="total-count"><?php echo $total_cards; ?></span> products
</p>

<!-- SKELETON GRID -->
<div id="skeleton-grid">
    <?php for($s=0;$s<10;$s++): ?>
    <div class="card-skeleton">
        <div class="sk-img sk-box"></div>
        <div class="sk-body">
            <div class="sk-line w90 sk-box"></div>
            <div class="sk-line w60 sk-box"></div>
            <div class="sk-line w45 sk-box"></div>
            <div class="sk-btn sk-box"></div>
        </div>
    </div>
    <?php endfor; ?>
</div>

<!-- REAL CARDS – (SORTED BY created_at DESC) -->
<div class="cgrid" id="cgrid" style="display:none;">

<?php
$sport_icons = [
    'Football'   => 'fa-futbol',
    'Basketball' => 'fa-basketball',
    'Cricket'    => 'fa-cricket-bat-ball',
    'Badminton'  => 'fa-table-tennis-paddle-ball',
    'Volleyball' => 'fa-volleyball',
    'Tennis'     => 'fa-baseball-bat-ball',
    'Boxing'     => 'fa-hand-fist',
    'Cycling'    => 'fa-bicycle',
    'Rugby'      => 'fa-football',
    'Esports'    => 'fa-gamepad',
    'Gym'        => 'fa-dumbbell',
    'Swimming'   => 'fa-person-swimming',
];

foreach($all_products as $row):
    $source = $row['_source'];
    
    if ($source === 'jersey'):
        $sport      = htmlspecialchars($row['sport_type'] ?? 'Other');
        $is_top     = ($row['is_top'] == 1);
        $has_disc   = !empty($row['discount']) && $row['discount'] > 0;
        $is_sell    = !empty($row['sell']) && $row['sell'] == 'Yes';
        $is_new     = !empty($row['is_new']) && $row['is_new'] == 'Yes';
        $orig_price = $has_disc ? round($row['price'] / (1 - $row['discount']/100)) : 0;
        $avg        = round($row['rating'] ?? 0);
        $ic         = $sport_icons[$row['sport_type'] ?? ''] ?? 'fa-shirt';
        $detail_url = "jersey_details.php?id=" . $row['id'];
        $img_src    = getImagePath($row, 'jersey');
        $title      = htmlspecialchars($row['title']);
        $club_line  = htmlspecialchars($row['description'] ?? 'SportGhar Collection');
        $type_badge = htmlspecialchars($row['jersey_type']);
        $price      = $row['price'];
        ?>
        <div class="card <?php echo $is_top ? 'card-top' : ''; ?>" data-sport="<?php echo $sport; ?>" data-source="jersey" onclick="window.location='<?php echo $detail_url; ?>'">
            <div class="cimg">
                <?php if($has_disc): ?><div class="cbadge cb-disc"><?php echo $row['discount']; ?>% OFF</div><?php endif; ?>
                <?php if($is_new): ?><div class="cbadge cb-new">✦ NEW</div>
                <?php elseif($is_sell): ?><div class="cbadge cb-sell">Sell</div>
                <?php elseif(!$has_disc): ?><div class="cbadge cb-hot">POPULAR</div><?php endif; ?>
                <img src="<?php echo $img_src; ?>" alt="<?php echo $title; ?>" onerror="this.src='https://placehold.co/400x300?text=No+Image'">
            </div>
            <div class="cbody">
                <div class="ctitle"><?php echo $title; ?></div>
                <div class="cclub"><?php echo $club_line; ?></div>
                <div class="cmeta">
                    <span class="ctype"><?php echo $type_badge; ?></span>
                    <span class="cprice"><?php if($has_disc): ?><span class="cprice-old">Rs.<?php echo number_format($orig_price); ?></span><?php endif; ?>Rs.<?php echo number_format($price); ?></span>
                </div>
                <div class="cfoot">
                    <span class="cviews"><i class="fa fa-eye"></i> <?php echo number_format($row['views'] ?? 0); ?></span>
                    <span class="csport"><i class="fa <?php echo $ic; ?>"></i> <?php echo $sport; ?></span>
                    <span class="cstars"><?php for($i=1;$i<=5;$i++) echo $i<=$avg?'★':'☆'; ?></span>
                </div>
                <div class="ctagline"><i class="fa fa-heart"></i> Sport Ghar Nepal will always be with you &mdash;&mdash; ral</div>
            </div>
        </div>
    <?php elseif ($source === 'item'):
        $sport      = htmlspecialchars($row['sport_type'] ?? 'Other');
        $is_top     = ($row['is_top'] == 1);
        $has_disc   = !empty($row['discount']) && $row['discount'] > 0;
        $is_sell    = ($row['sell'] == 'Yes');
        $is_new     = (!empty($row['is_new']) && $row['is_new'] == 1);
        $orig_price = $has_disc ? round($row['price'] / (1 - $row['discount']/100)) : 0;
        $avg        = round($row['rating'] ?? 0);
        $ic         = $sport_icons[$row['sport_type'] ?? ''] ?? 'fa-layer-group';
        $detail_url = "item_details.php?id=" . $row['id'];
        $img_src    = getImagePath($row, 'item');
        $title      = htmlspecialchars($row['title']);
        $club_line  = 'SportGhar • ' . htmlspecialchars($row['item_type'] ?? 'Standard');
        $type_badge = htmlspecialchars($row['item_type'] ?? 'Standard');
        $price      = $row['price'];
        ?>
        <div class="card <?php echo $is_top ? 'card-top' : ''; ?>" data-sport="<?php echo $sport; ?>" data-source="item" onclick="window.location='<?php echo $detail_url; ?>'">
            <div class="cimg">
                <?php if($has_disc): ?><div class="cbadge cb-disc"><?php echo $row['discount']; ?>% OFF</div><?php endif; ?>
                <?php if($is_new): ?><div class="cbadge cb-new">✦ NEW</div>
                <?php elseif($is_sell): ?><div class="cbadge cb-sell">🔥 HOT</div>
                <?php elseif(!$has_disc): ?><div class="cbadge cb-hot">POPULAR</div><?php endif; ?>
                <img src="<?php echo $img_src; ?>" alt="<?php echo $title; ?>" onerror="this.src='https://placehold.co/400x300?text=No+Image'">
            </div>
            <div class="cbody">
                <div class="ctitle"><?php echo $title; ?></div>
                <div class="cclub"><?php echo $club_line; ?></div>
                <div class="cmeta">
                    <span class="ctype"><?php echo $type_badge; ?></span>
                    <span class="cprice"><?php if($has_disc): ?><span class="cprice-old">Rs.<?php echo number_format($orig_price); ?></span><?php endif; ?>Rs.<?php echo number_format($price); ?></span>
                </div>
                <div class="cfoot">
                    <span class="cviews"><i class="fa fa-eye"></i> <?php echo number_format($row['views'] ?? 0); ?></span>
                    <span class="csport"><i class="fa <?php echo $ic; ?>"></i> <?php echo $sport; ?></span>
                    <span class="cstars"><?php for($i=1;$i<=5;$i++) echo $i<=$avg?'★':'☆'; ?></span>
                </div>
                <div class="ctagline"><i class="fa fa-heart"></i> SportGharNepal will always be with you &mdash;&mdash; ral</div>
            </div>
        </div>
    <?php elseif ($source === 'boot'):
        $sport      = htmlspecialchars($row['sport_type'] ?? 'Football');
        $is_top     = ($row['is_top'] == 1);
        $has_disc   = !empty($row['discount_percent']) && $row['discount_percent'] > 0;
        $is_sell    = ($row['sold_out'] == 1);
        $is_new     = (!empty($row['is_new']) && $row['is_new'] == 1);
        $orig_price = $has_disc ? round($row['price'] / (1 - $row['discount_percent']/100)) : 0;
        $avg        = round($row['rating'] ?? 0);
        $ic         = $sport_icons[$sport] ?? 'fa-futbol';
        $detail_url = "boot_details.php?id=" . $row['id'];
        $img_src    = getImagePath($row, 'boot');
        $title      = htmlspecialchars($row['name']);
        $club_line  = htmlspecialchars($row['brand']) . ' • Football Boots';
        $type_badge = ucfirst(htmlspecialchars($row['category']));
        $price      = $row['price'];
        ?>
        <div class="card <?php echo $is_top ? 'card-top' : ''; ?>" data-sport="<?php echo $sport; ?>" data-source="boot" onclick="window.location='<?php echo $detail_url; ?>'">
            <div class="cimg">
                <?php if($has_disc): ?><div class="cbadge cb-disc"><?php echo $row['discount_percent']; ?>% OFF</div><?php endif; ?>
                <?php if($is_new): ?><div class="cbadge cb-new">✦ NEW</div>
                <?php elseif($is_sell): ?><div class="cbadge cb-sell">SOLD OUT</div>
                <?php elseif(!$has_disc): ?><div class="cbadge cb-hot">POPULAR</div><?php endif; ?>
                <img src="<?php echo $img_src; ?>" alt="<?php echo $title; ?>" onerror="this.src='https://placehold.co/400x300?text=No+Image'">
            </div>
            <div class="cbody">
                <div class="ctitle"><?php echo $title; ?></div>
                <div class="cclub"><?php echo $club_line; ?></div>
                <div class="cmeta">
                    <span class="ctype"><?php echo $type_badge; ?></span>
                    <span class="cprice"><?php if($has_disc): ?><span class="cprice-old">Rs.<?php echo number_format($orig_price); ?></span><?php endif; ?>Rs.<?php echo number_format($price); ?></span>
                </div>
                <div class="cfoot">
                    <span class="cviews"><i class="fa fa-eye"></i> <?php echo number_format($row['views'] ?? 0); ?></span>
                    <span class="csport"><i class="fa <?php echo $ic; ?>"></i> <?php echo $sport; ?></span>
                    <span class="cstars"><?php for($i=1;$i<=5;$i++) echo $i<=$avg?'★':'☆'; ?></span>
                </div>
                <div class="ctagline"><i class="fa fa-heart"></i> SportGharNepal will always be with you &mdash;&mdash; ral</div>
            </div>
        </div>
    <?php elseif ($source === 'bat'):
        $sport      = 'Cricket';
        $is_top     = ($row['is_top'] == 1);
        $has_disc   = !empty($row['discount_price']) && $row['discount_price'] > 0 && $row['discount_price'] < $row['original_price'];
        $is_sell    = ($row['stock_qty'] <= 0);
        $is_new     = (!empty($row['is_new']) && $row['is_new'] == 1);
        $orig_price = $row['original_price'];
        $final_price = $has_disc ? $row['discount_price'] : $orig_price;
        $disc_percent = $has_disc ? round((($orig_price - $final_price) / $orig_price) * 100) : 0;
        $avg        = round($row['rating'] ?? 0);
        $ic         = 'fa-cricket-bat-ball';
        $detail_url = "bat_details.php?id=" . $row['id'];
        $img_src    = getImagePath($row, 'bat');
        $title      = htmlspecialchars($row['bat_name']);
        $brand      = htmlspecialchars($row['brand']);
        $weight     = htmlspecialchars($row['weight']);
        ?>
        <div class="card <?php echo $is_top ? 'card-top' : ''; ?>" data-sport="<?php echo $sport; ?>" data-source="bat" onclick="window.location='<?php echo $detail_url; ?>'">
            <div class="cimg">
                <?php if($has_disc): ?><div class="cbadge cb-disc"><?php echo $disc_percent; ?>% OFF</div><?php endif; ?>
                <?php if($is_new): ?><div class="cbadge cb-new">✦ NEW</div>
                <?php elseif($is_sell): ?><div class="cbadge cb-sell">OUT OF STOCK</div>
                <?php elseif(!$has_disc): ?><div class="cbadge cb-hot">POPULAR</div><?php endif; ?>
                <img src="<?php echo $img_src; ?>" alt="<?php echo $title; ?>" onerror="this.src='https://placehold.co/400x300?text=Bat'">
            </div>
            <div class="cbody">
                <div class="ctitle"><?php echo $title; ?></div>
                <div class="cclub"><?php echo $brand; ?> • Cricket Bat</div>
                <div class="cmeta">
                    <span class="ctype"><?php echo $weight; ?></span>
                    <span class="cprice">
                        <?php if($has_disc): ?>
                            <span class="cprice-old">Rs.<?php echo number_format($orig_price); ?></span>
                        <?php endif; ?>
                        Rs.<?php echo number_format($final_price); ?>
                    </span>
                </div>
                <div class="cfoot">
                    <span class="cviews"><i class="fa fa-eye"></i> <?php echo number_format($row['views'] ?? 0); ?></span>
                    <span class="csport"><i class="fa <?php echo $ic; ?>"></i> <?php echo $sport; ?></span>
                    <span class="cstars"><?php for($i=1;$i<=5;$i++) echo $i<=$avg?'★':'☆'; ?></span>
                </div>
                <div class="ctagline"><i class="fa fa-heart"></i> Cricket Bat</div>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<div class="no-results" id="no-results">
    <i class="fa fa-search"></i>
    <h3>No products found</h3>
    <p>Try selecting a different category</p>
</div>

</div><!-- end cgrid -->

<!-- PAGINATION -->
<div class="pagination-wrap" id="pagination-wrap"></div>

<!-- LOCATION SECTION (REDUCED GAP) -->
<div class="loc-hd">
    <h2><i class="fa fa-location-dot" style="color:#f97316;margin-right:8px;"></i> Find Us</h2>
    <p>Visit us in Kathmandu — we're easy to find!</p>
    <div class="sec-line"></div>
</div>
<div class="loc-section">
    <div class="loc-wrap">
        <div class="loc-map">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3532.0!2d85.3145!3d27.7101!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39eb18a55d19f9bb%3A0x385050e253ae7395!2sJyatha%2C%20Kathmandu%2044600%2C%20Nepal!5e0!3m2!1sen!2snp!4v1700000000000!5m2!1sen!2snp" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
        <div class="loc-info">
            <div class="loc-brand">
                <div class="loc-brand-icon"><i class="fa fa-shirt"></i></div>
                <div class="loc-brand-text"><h3>SportGhar Nepal</h3><p>Nepal's #1 Sports Store</p></div>
            </div>
            <div class="loc-row">
                <div class="loc-row-icon"><i class="fa fa-map-pin"></i></div>
                <div class="loc-row-text"><strong>Address</strong><span>Jyatha, Kathmandu 44600<br>Bagmati Province, Nepal</span></div>
            </div>
            <div class="loc-row">
                <div class="loc-row-icon"><i class="fa fa-phone"></i></div>
                <div class="loc-row-text"><strong>Phone</strong><span><a href="tel:+9779849338543" style="color:#f97316;text-decoration:none;font-weight:700;">+977 984-9338543</a></span></div>
            </div>
            <div class="loc-row">
                <div class="loc-row-icon"><i class="fa fa-clock"></i></div>
                <div class="loc-row-text">
                    <strong>Hours <span class="loc-status open">Open Now</span></strong>
                    <div class="loc-hours-list">
                        <div class="loc-hour-row"><span class="day">Sun – Thu</span><span class="time">8:00 AM – 7:30 PM</span></div>
                        <div class="loc-hour-row"><span class="day">Fri – Sat</span><span class="time">9:00 AM – 7:30 PM</span></div>
                    </div>
                </div>
            </div>
            <a class="loc-dir-btn" href="https://www.google.com/maps/dir/?api=1&destination=27.7101,85.3145" target="_blank" rel="noopener"><i class="fa fa-diamond-turn-right"></i> Get Directions</a>
            <div class="loc-tagline"><i class="fa fa-heart"></i> SportGharNepal will always be with you &mdash;&mdash; ral</div>
        </div>
    </div>
</div>

<script>
var CARDS_PER_PAGE = 15;
var currentPage    = 1;
var currentSport   = 'all';

/* ===== SLIDER ===== */
var cur=0, total=<?php echo max($total_slides,1); ?>;
var trk=document.getElementById('sw-trk');
var dots=document.querySelectorAll('.sw-dot');
var prog=0, progEl=document.getElementById('sw-prog'), ptimer;
var ctr=document.getElementById('sw-ctr');
function go(n){cur=(n+total)%total;trk.style.transform='translateX(-'+(cur*100)+'%)';dots.forEach(function(d,i){d.className='sw-dot'+(i===cur?' on':'');});ctr.textContent=(cur+1)+' / '+total;resetProg();}
function mv(d){go(cur+d);}
function resetProg(){clearInterval(ptimer);prog=0;progEl.style.width='0%';ptimer=setInterval(function(){prog+=100/45;progEl.style.width=Math.min(prog,100)+'%';if(prog>=100) go(cur+1);},100);}
resetProg();
var tx=0;
document.getElementById('sw').addEventListener('touchstart',function(e){tx=e.touches[0].clientX;},{passive:true});
document.getElementById('sw').addEventListener('touchend',function(e){var dx=e.changedTouches[0].clientX-tx;if(Math.abs(dx)>50) mv(dx<0?1:-1);});

/* ===== SKELETON → REAL ===== */
window.addEventListener('load',function(){
    setTimeout(function(){
        document.getElementById('skeleton-grid').style.display='none';
        document.getElementById('cgrid').style.display='grid';
        renderPage(1);
    },600);
});

/* ===== FILTER ===== */
function filterCards(el,sport){
    document.querySelectorAll('.ftab').forEach(function(t){t.classList.remove('on');});
    el.classList.add('on');
    el.scrollIntoView({behavior:'smooth',block:'nearest',inline:'center'});
    currentSport=sport;
    currentPage=1;
    renderPage(1);
}
function getFilteredCards(){
    var all=Array.from(document.querySelectorAll('#cgrid .card'));
    if(currentSport==='all') return all;
    return all.filter(function(c){return c.dataset.sport===currentSport;});
}
function renderPage(page){
    currentPage=page;
    var filtered=getFilteredCards();
    var total=filtered.length;
    var totalPg=Math.ceil(total/CARDS_PER_PAGE)||1;
    if(page>totalPg) page=currentPage=totalPg;
    var start=(page-1)*CARDS_PER_PAGE;
    var end=start+CARDS_PER_PAGE;

    Array.from(document.querySelectorAll('#cgrid .card')).forEach(function(c){
        c.classList.remove('visible');
        c.style.display='none';
    });

    var visible=filtered.slice(start,end);
    var noRes=document.getElementById('no-results');
    if(visible.length===0){
        noRes.style.display='block';
    } else {
        noRes.style.display='none';
        visible.forEach(function(c,i){
            c.style.display='';
            setTimeout(function(){c.classList.add('visible');}, i*45);
        });
    }
    var showCount=document.getElementById('showing-count');
    if(showCount){
        var from=total===0?0:start+1;
        var to=Math.min(end,total);
        showCount.textContent=total===0?'0':from+'–'+to;
    }
    if(page>1){
        var secHd=document.querySelector('.sec-hd');
        if(secHd) secHd.scrollIntoView({behavior:'smooth'});
    }
    buildPagination(page,totalPg);
}
function buildPagination(current,totalPg){
    var wrap=document.getElementById('pagination-wrap');
    if(totalPg<=1){wrap.innerHTML='';return;}
    var html='';
    html+='<a class="pg-btn'+(current===1?' disabled':'')+'" onclick="renderPage('+(current-1)+')"><i class="fa fa-chevron-left"></i></a>';
    paginationRange(current,totalPg).forEach(function(p){
        if(p==='...'){html+='<span class="pg-ellipsis">…</span>';}
        else{html+='<a class="pg-btn'+(p===current?' active':'')+'" onclick="renderPage('+p+')">'+p+'</a>';}
    });
    html+='<a class="pg-btn'+(current===totalPg?' disabled':'')+'" onclick="renderPage('+(current+1)+')"><i class="fa fa-chevron-right"></i></a>';
    wrap.innerHTML=html;
}
function paginationRange(current,total){
    if(total<=7){var arr=[];for(var i=1;i<=total;i++)arr.push(i);return arr;}
    if(current<=4) return [1,2,3,4,5,'...',total];
    if(current>=total-3) return [1,'...',total-4,total-3,total-2,total-1,total];
    return [1,'...',current-1,current,current+1,'...',total];
}
</script>
</body>
</html>
<?php include "../includes/footer.php"; ?>