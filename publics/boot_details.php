<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}
require_once "../databases/db.php";
$conn = getDB();

if(!isset($_SESSION['cart'])){
    $_SESSION['cart'] = [];
}

$id = (int)$_GET['id'];

// Fetch boot with shop name
$data = $conn->query("SELECT b.*, s.shop_name
                      FROM boot b
                      LEFT JOIN shops s ON b.shop_id = s.id
                      WHERE b.id = $id")->fetch_assoc();

if(!$data){
    die("Boot not found.");
}

// ------------------------------------------------------------------
// EXTRA IMAGES – handle JSON or comma-separated values
// ------------------------------------------------------------------
$extraImages = [];
if(!empty($data['images'])){
    // Try JSON first (in case you store as JSON)
    $decoded = json_decode($data['images'], true);
    if(is_array($decoded)){
        $extraImages = $decoded;
    } else {
        // Fallback: comma separated
        $extraImages = array_map('trim', explode(',', $data['images']));
    }
}
// Remove any empty entries
$extraImages = array_filter($extraImages);

// ------------------------------------------------------------------
// LIKE & COMMENT tables (already fine)
// ------------------------------------------------------------------
if(isset($_GET['like'])){
    $conn->query("CREATE TABLE IF NOT EXISTS likes_boot (id INT AUTO_INCREMENT PRIMARY KEY, boot_id INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $conn->query("INSERT IGNORE INTO likes_boot (boot_id) VALUES ($id)");
    header("Location: boot_details.php?id=".$id);
    exit;
}
$conn->query("CREATE TABLE IF NOT EXISTS likes_boot (id INT AUTO_INCREMENT PRIMARY KEY, boot_id INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$likeCount = $conn->query("SELECT COUNT(*) as total FROM likes_boot WHERE boot_id=$id")->fetch_assoc()['total'];

$conn->query("CREATE TABLE IF NOT EXISTS comments_boot (id INT AUTO_INCREMENT PRIMARY KEY, boot_id INT, comment TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
if(isset($_POST['comment'])){
    $c = $conn->real_escape_string($_POST['comment']);
    if($c != ""){
        $conn->query("INSERT INTO comments_boot (boot_id, comment) VALUES ($id,'$c')");
    }
}
$comments = $conn->query("SELECT * FROM comments_boot WHERE boot_id=$id ORDER BY id DESC");

// ------------------------------------------------------------------
// CART & BUY NOW (unchanged)
// ------------------------------------------------------------------
if(isset($_POST['add_to_cart'])){
    if(!isset($_SESSION['user']['id'])){
        $redirect = urlencode("boot_details.php?id=".$id);
        header("Location: login.php?redirect=".$redirect);
        exit;
    }
    $size = $_POST['size'] ?? '';
    if($size != ""){
        $boot_img = !empty($data['main_image']) ? $data['main_image'] : ($data['image'] ?? '');
        $_SESSION['cart']['boot_'.$id] = [
            "id"    => $data['id'],
            "name"  => $data['name'],
            "price" => $data['price'],
            "image" => $boot_img,
            "size"  => $size,
            "qty"   => 1,
            "type"  => "boot"
        ];
        header("Location: chart.php"); exit;
    }
}

if(isset($_POST['buy_now'])){
    if(!isset($_SESSION['user']['id'])){
        $redirect = urlencode("boot_details.php?id=".$id);
        header("Location: login.php?redirect=".$redirect);
        exit;
    }
    $size = $_POST['size'] ?? '';
    if($size != ""){
        $boot_img = !empty($data['main_image']) ? $data['main_image'] : ($data['image'] ?? '');
        $_SESSION['cart'] = [];
        $_SESSION['cart']['boot_'.$id] = [
            "id"    => $data['id'],
            "name"  => $data['name'],
            "price" => $data['price'],
            "image" => $boot_img,
            "size"  => $size,
            "qty"   => 1,
            "type"  => "boot"
        ];
        header("Location: checkout.php"); exit;
    }
}

// ------------------------------------------------------------------
// VIEW COUNT
// ------------------------------------------------------------------
if(!isset($_SESSION['boot_viewed'])) $_SESSION['boot_viewed'] = [];
if(!isset($_SESSION['boot_viewed'][$id])){
    $_SESSION['boot_viewed'][$id] = true;
    $conn->query("UPDATE boot SET views = views + 1 WHERE id = $id");
}

// ------------------------------------------------------------------
// TOP PICKS
// ------------------------------------------------------------------
$top_boots = $conn->query("SELECT * FROM boot WHERE is_top = 1 AND id != $id ORDER BY id DESC LIMIT 8");
$top_rows = [];
while($tr = $top_boots->fetch_assoc()) $top_rows[] = $tr;

$avg = round($data['rating'] ?? 0);
$disc = $data['discount_percent'] ?? 0;
$orig = $data['price'];
$final = $disc > 0 ? $orig - ($orig * $disc / 100) : $orig;

$main_img_raw = !empty($data['main_image']) ? $data['main_image'] : ($data['image'] ?? '');

// ------------------------------------------------------------------
// IMAGE PATH FIX FUNCTION – SUPER ROBUST
// ------------------------------------------------------------------
function fixBootImagePath($img) {
    if (empty($img)) return '';
    // Absolute URL or root-relative
    if (strpos($img, 'http') === 0 || strpos($img, '/') === 0) {
        return htmlspecialchars($img);
    }
    // Remove any leading '../' or 'sellers/' duplication
    $img = ltrim($img, '/');
    $img = preg_replace('#^(\.\./)+#', '', $img);
    // If it already contains 'sellers/uploads/boots', keep as is (but remove duplicate 'sellers/')
    if (strpos($img, 'sellers/uploads/boots') !== false) {
        $img = preg_replace('#^sellers/?#', '', $img);
        return '../sellers/' . $img;
    }
    // If it starts with 'uploads/boots' – typical scenario
    if (strpos($img, 'uploads/boots') === 0) {
        return '../sellers/' . $img;
    }
    // Otherwise assume it's just a filename, build full path
    return '../sellers/uploads/boots/' . $img;
}

$main_img_fixed = fixBootImagePath($main_img_raw);

$is_logged_in = isset($_SESSION['user']['id']);
$redirect_url = urlencode("boot_details.php?id=".$id);
$shop_display = !empty($data['shop_name']) ? htmlspecialchars($data['shop_name']) : "General Store";

// Sizes
$sizes_raw = $data['sizes'] ?? '39,40,41,42,43,44';
$sizes = array_filter(array_map('trim', explode(',', $sizes_raw)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($data['name']); ?> — SportGhar</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* ========== ALL YOUR EXISTING STYLES GO HERE – keep exactly the same ========== */
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Outfit',sans-serif;background:#eef2ff;color:#12100f;min-height:100vh;}
.breadcrumb{padding:14px 32px;font-size:13px;color:#64748b;}
.breadcrumb a{color:#33a609;text-decoration:none;font-weight:600;}
.breadcrumb a:hover{text-decoration:underline;color:#1702fb;}
.product-wrap{display:flex;gap:32px;max-width:1100px;margin:0 auto 40px;padding:0 24px;flex-wrap:wrap;}
.img-section{flex:1;min-width:300px;}
.main-img-outer{display:flex;gap:14px;align-items:flex-start;}
.main-img-wrap{position:relative;border-radius:20px;overflow:hidden;background:rgba(255,255,255,0.12);border:2px solid rgba(255,255,255,0.35);box-shadow:0 8px 30px rgba(0,0,0,0.25);flex:1;cursor:crosshair;}
.main-img-wrap img{width:100%;height:420px;object-fit:cover;display:block;}
.img-discount{position:absolute;top:14px;left:14px;background:#ef4444;color:#fff;font-size:12px;font-weight:700;padding:5px 12px;border-radius:20px;z-index:2;}
.img-sell{position:absolute;top:14px;right:14px;background:#dc2626;color:#fff;font-size:12px;font-weight:700;padding:5px 12px;border-radius:20px;z-index:2;}
.img-new{position:absolute;top:14px;right:14px;background:#22c55e;color:#fff;font-size:12px;font-weight:700;padding:5px 12px;border-radius:20px;z-index:2;}
.side-zoom{width:260px;height:260px;min-width:260px;border-radius:18px;border:2.5px solid rgba(255,255,255,0.6);overflow:hidden;display:none;box-shadow:0 12px 40px rgba(0,0,0,0.3);background:#fff;position:relative;}
.side-zoom img{width:100%;height:100%;object-fit:cover;transform-origin:0 0;transform:scale(2.5);pointer-events:none;transition:none;}
.side-zoom-label{position:absolute;bottom:8px;left:50%;transform:translateX(-50%);background:rgba(201,75,1,0.88);color:#fff;font-size:11px;font-weight:600;padding:3px 12px;border-radius:20px;pointer-events:none;white-space:nowrap;}
.main-img-outer:hover .side-zoom{display:block;}
.thumbs{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;}
.thumb-wrap{position:relative;display:inline-block;}
.thumbs img{width:72px;height:72px;object-fit:cover;border-radius:12px;border:2px solid rgba(255,255,255,0.35);cursor:pointer;transition:0.25s;display:block;}
.thumbs img:hover,.thumbs img.active{border-color:#fff;transform:translateY(-3px);box-shadow:0 6px 16px rgba(0,0,0,0.25);}
.zoom-popup{display:none;position:absolute;bottom:calc(100% + 10px);left:50%;transform:translateX(-50%);width:200px;height:200px;border-radius:16px;overflow:hidden;border:2.5px solid #fff;box-shadow:0 12px 40px rgba(0,0,0,0.3);z-index:100;pointer-events:none;}
.zoom-popup img{width:100%;height:100%;object-fit:cover;transform:scale(1.15);transition:transform 0.3s;}
.thumb-wrap:hover .zoom-popup{display:block;}
.spec-tags{display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;}
.spec-tag{display:flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;background:#fff;color:#374151;border:1.5px solid #e5e7eb;padding:5px 12px;border-radius:20px;box-shadow:0 1px 4px rgba(0,0,0,0.06);}
.spec-tag i{color:#f97316;font-size:11px;}
.info-section{flex:1;min-width:300px;display:flex;flex-direction:column;gap:16px;}
.product-title{font-size:28px;font-weight:800;color:#e25001;line-height:1.25;text-shadow:0 2px 8px rgba(95,245,8,0.97);}
.product-brand{font-size:15px;font-weight:700;color:#6366f1;letter-spacing:0.5px;}
.product-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.meta-badge{background:#eef2ff;color:#6366f1;border:1.5px solid rgba(238,144,43,0.97);font-size:12px;font-weight:600;padding:4px 12px;border-radius:20px;backdrop-filter:blur(6px);}
.shop-badge{background:#fef9e6;color:#c94b01;border:1.5px solid #facc15;font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px;display:inline-flex;align-items:center;gap:6px;}
.shop-badge i{font-size:12px;}
.stars{display:flex;align-items:center;gap:6px;}
.stars i{color:#facc15;font-size:15px;}
.stars span{font-size:13px;color:#64748b;}
.price-row{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;}
.price-main{font-size:32px;font-weight:800;color:#16a34a;text-shadow:0 2px 8px rgba(203,133,206,0.95);}
.price-original{font-size:16px;color:rgba(247,59,17,0.98);text-decoration:line-through;}
.price-save{font-size:13px;font-weight:700;background:rgba(34,197,94,0.25);color:#f102e5;border:1px solid rgba(18,6,250,0.96);padding:3px 10px;border-radius:12px;}
.desc{font-size:14.5px;color:#fbf7f4;line-height:1.7;background:rgba(119,138,91,0.97);border-left:3px solid rgba(237,6,6,0.98);padding:12px 16px;border-radius:0 10px 10px 0;backdrop-filter:blur(4px);}
.size-label{font-size:13px;font-weight:700;color:#0119eb;margin-bottom:8px;}
.size-wrap{display:flex;gap:10px;flex-wrap:wrap;}
.size-btn{min-width:52px;height:46px;padding:0 10px;border-radius:12px;border:2px solid rgba(113,249,9,1);background:rgba(46,35,35,0.97);font-size:13px;font-weight:700;cursor:pointer;transition:0.25s;display:flex;align-items:center;justify-content:center;color:#fff;backdrop-filter:blur(4px);}
.size-btn:hover{border-color:#fff;background:rgba(243,102,2,0.97);}
.size-btn.active{background:#eda9f6;color:#c94b01;border-color:#0328fc;box-shadow:0 4px 14px rgba(0,0,0,0.25);font-weight:900;}
.size-guide{font-size:11.5px;color:rgba(100,116,139,0.9);margin-top:6px;display:inline-flex;align-items:center;gap:4px;cursor:pointer;text-decoration:underline;}
.action-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.like-btn{display:flex;align-items:center;gap:7px;background:rgb(252,64,35);color:#fff;border:1.5px solid rgba(186,247,4,0.97);padding:9px 18px;border-radius:24px;font-size:14px;font-weight:600;text-decoration:none;transition:0.25s;backdrop-filter:blur(4px);}
.like-btn:hover{background:#ef4444;color:#fff;border-color:#ef4444;}
.like-btn i{color:#fca5a5;}
.like-btn:hover i{color:#fff;}
.share-wrap{position:relative;display:inline-block;}
.share-btn{display:flex;align-items:center;gap:7px;background:rgb(116,109,95);color:#fff;border:1.5px solid rgba(239,113,2,0.97);padding:9px 18px;border-radius:24px;font-size:14px;font-weight:600;cursor:pointer;transition:0.25s;backdrop-filter:blur(4px);}
.share-btn:hover{background:rgba(253,198,14,0.95);}
.share-dropdown{display:none;position:absolute;top:calc(100% + 10px);left:50%;transform:translateX(-50%);background:rgba(252,143,143,0.97);border:1.5px solid rgba(255,255,255,1);border-radius:18px;box-shadow:0 12px 40px rgba(69,214,36,0.93);padding:12px 10px;z-index:999;min-width:180px;animation:fadeSlideDown 0.22s ease;backdrop-filter:blur(12px);}
@keyframes fadeSlideDown{from{opacity:0;transform:translateX(-50%) translateY(-8px);}to{opacity:1;transform:translateX(-50%) translateY(0);}}
.share-wrap:hover .share-dropdown,.share-wrap.open .share-dropdown{display:block;}
.share-title{font-size:11px;font-weight:700;color:rgb(244,249,243);text-transform:uppercase;letter-spacing:1px;text-align:center;margin-bottom:10px;}
.share-icons{display:flex;flex-direction:column;gap:6px;}
.share-link{display:flex;align-items:center;gap:10px;padding:9px 14px;border-radius:12px;font-size:13px;font-weight:600;text-decoration:none;transition:0.2s;border:1.5px solid rgba(20,16,246,0.98);color:#0beb03e5;background:rgba(255,255,255,0.08);}
.share-link:hover{transform:translateX(4px);}
.share-link.facebook:hover{background:#1877F2;}
.share-link.instagram:hover{background:linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);}
.share-link.twitter:hover{background:rgba(255,255,255,0.9);color:#000;}
.share-link.copy:hover{background:rgba(255,255,255,0.85);color:#c94b01;}
.share-link i{font-size:16px;width:20px;text-align:center;color:#ffe0c8;}
.share-link:hover i{color:inherit !important;}
.btn-group{display:flex;gap:12px;flex-wrap:wrap;margin-top:4px;}
.btn{flex:1;min-width:140px;padding:14px 20px;border:none;border-radius:14px;font-size:15px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:0.3s;letter-spacing:0.3px;font-family:'Outfit',sans-serif;text-decoration:none;position:relative;overflow:hidden;}
.cart-btn{background:#1e3a8a;color:#fff;border:2px solid rgba(255,255,255,0.55);backdrop-filter:blur(6px);}
.cart-btn:hover{background:rgba(200,7,52,0.94);transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,0.2);}
.buy-btn{background:#1aaa01;color:#fff;border:2px solid rgba(255,255,255,0.55);backdrop-filter:blur(6px);}
.buy-btn:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,0.3);background:#ea2f2f;}
.login-modal-overlay{display:none;position:fixed;inset:0;background:rgba(237,218,0,0.98);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(6px);animation:fadeIn 0.25s ease;}
.login-modal-overlay.show{display:flex;}
@keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
.login-modal{background:linear-gradient(135deg,#7c2100 0%,#a33200 40%,#c94b01 100%);border:2px solid rgba(198,104,104,0.3);border-radius:28px;padding:44px 40px;max-width:420px;width:90%;text-align:center;box-shadow:0 32px 80px rgba(0,0,0,0.5);animation:popUp 0.3s cubic-bezier(0.34,1.56,0.64,1);position:relative;}
@keyframes popUp{from{opacity:0;transform:scale(0.85) translateY(20px);}to{opacity:1;transform:scale(1) translateY(0);}}
.modal-close{position:absolute;top:16px;right:18px;background:rgba(255,255,255,0.18);border:none;color:#fff;width:34px;height:34px;border-radius:50%;font-size:15px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:0.2s;}
.modal-close:hover{background:rgba(255,255,255,0.35);}
.modal-icon{width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,0.18);border:2.5px solid rgba(255,255,255,0.4);display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 20px;}
.modal-title{font-size:24px;font-weight:800;color:#fff;margin-bottom:10px;}
.modal-sub{font-size:14px;color:rgba(255,255,255,0.75);margin-bottom:28px;line-height:1.6;}
.modal-product{display:flex;align-items:center;gap:12px;background:rgba(0,0,0,0.25);border:1px solid rgba(255,255,255,0.2);border-radius:14px;padding:12px 16px;margin-bottom:28px;text-align:left;}
.modal-product img{width:52px;height:52px;border-radius:10px;object-fit:cover;border:2px solid rgba(255,255,255,0.3);}
.modal-product-name{font-size:14px;font-weight:700;color:#fff;margin-bottom:3px;}
.modal-product-price{font-size:13px;color:#d1fae5;font-weight:600;}
.modal-btns{display:flex;flex-direction:column;gap:10px;}
.modal-btn-login{display:flex;align-items:center;justify-content:center;gap:9px;padding:14px;border-radius:14px;background:#fff;color:#c94b01;font-size:15px;font-weight:800;text-decoration:none;transition:0.25s;font-family:'Outfit',sans-serif;}
.modal-btn-login:hover{background:#fff5f0;transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,0.25);}
.modal-btn-register{display:flex;align-items:center;justify-content:center;gap:9px;padding:12px;border-radius:14px;background:rgba(255,255,255,0.15);color:#fff;border:1.5px solid rgba(255,255,255,0.4);font-size:14px;font-weight:700;text-decoration:none;transition:0.25s;font-family:'Outfit',sans-serif;}
.modal-btn-register:hover{background:rgba(255,255,255,0.28);}
.modal-skip{margin-top:10px;font-size:12px;color:rgba(255,255,255,0.5);cursor:pointer;background:none;border:none;font-family:'Outfit',sans-serif;}
.modal-skip:hover{color:rgba(255,255,255,0.8);}
.guarantee{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px;}
.guar-item{display:flex;align-items:center;gap:7px;font-size:12.5px;color:#ffe0c8;background:rgba(91,169,1,0.97);padding:8px 14px;border-radius:12px;border:1px solid rgba(255,255,255,0.2);font-weight:600;}
.guar-item i{color:#86efac;font-size:13px;}
#size-alert{background:rgba(239,68,68,0.2);border:1.5px solid rgba(239,68,68,0.5);color:#fca5a5;border-radius:10px;padding:10px 14px;font-size:13.5px;margin-top:12px;}
.comment-section{max-width:1100px;margin:0 auto 50px;padding:0 24px;}
.comment-card{background:transparent;border-radius:20px;padding:28px;box-shadow:0 4px 20px rgb(167,250,65);border:1.5px solid rgb(16,1,224);backdrop-filter:blur(8px);}
.comment-card h3{font-size:20px;font-weight:700;color:#070806;margin-bottom:18px;display:flex;align-items:center;gap:8px;}
.comment-card h3 i{color:#022bc0;}
.comment-input{width:100%;padding:13px 16px;border:1.5px solid rgb(90,255,14);border-radius:12px;font-size:14px;font-family:'Outfit',sans-serif;resize:vertical;outline:none;transition:0.3s;color:#fff;background:rgba(176,0,173,0.85);min-height:90px;}
.comment-input::placeholder{color:rgba(224,242,223,0.92);}
.comment-input:focus{border-color:rgba(109,87,87,0.91);box-shadow:0 0 0 3px rgba(255,255,255,0.12);background:rgba(220,49,2,0.97);}
.post-btn{margin-top:10px;padding:11px 28px;background:#0d0310;color:#fc5d01;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;transition:0.25s;font-family:'Outfit',sans-serif;}
.post-btn:hover{background:#f59768;transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,0.2);}
.comment-list{margin-top:22px;display:flex;flex-direction:column;gap:12px;}
.comment-item{background:rgba(251,126,2,0.99);border:1px solid rgba(76,250,1,0.96);border-radius:12px;padding:13px 16px;font-size:14px;color:#edebea;display:flex;align-items:flex-start;gap:12px;backdrop-filter:blur(4px);}
.comment-avatar{width:36px;height:36px;min-width:36px;border-radius:50%;background:rgba(250,7,218,0.95);color:#fff;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid rgba(255,255,255,0.4);}
.top-picks-section{max-width:1100px;margin:0 auto 60px;padding:0 24px;}
.top-strip-hd{display:flex;align-items:center;gap:12px;margin-bottom:22px;}
.top-strip-hd h2{font-size:20px;font-weight:800;color:#1905f5;display:flex;align-items:center;gap:9px;white-space:nowrap;}
.top-strip-hd h2 i{color:#2afc05;}
.ts-line{flex:1;height:1px;background:linear-gradient(90deg,rgba(38,1,250,0.97),transparent);}
.top-strip-hd a{font-size:12px;font-weight:700;color:#f90202;text-decoration:none;padding:5px 14px;border:1px solid rgba(32,244,4,0.96);border-radius:20px;transition:.2s;white-space:nowrap;background:rgba(255,255,255,0.1);}
.top-strip-hd a:hover{background:rgba(251,148,5,0.97);}
.tp-grid{display:flex;flex-wrap:wrap;gap:24px;justify-content:flex-start;}
.tp-card{width:240px;background:#eef2ff;border-radius:20px;border:1px solid rgba(15,3,252,0.99);overflow:hidden;position:relative;transition:.45s opacity,.45s transform;opacity:0;transform:translateY(20px);backdrop-filter:blur(6px);}
.tp-card.visible{opacity:1;transform:translateY(0);}
.tp-card.visible:hover{transform:translateY(-9px);border-color:rgba(57,250,4,0.98);box-shadow:0 20px 50px rgba(0,0,0,0.3);}
.tp-cimg{position:relative;overflow:hidden;}
.tp-cimg img{width:100%;height:200px;object-fit:cover;display:block;transition:.45s;}
.tp-card.visible:hover .tp-cimg img{transform:scale(1.08);}
.tp-cbadge{position:absolute;top:10px;padding:4px 11px;font-size:10.5px;font-weight:700;border-radius:20px;z-index:5;}
.tp-cb-disc{left:10px;background:#f0eaea;color:#fd0303;}
.tp-cb-hot{right:10px;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;}
.tp-cb-new{right:10px;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;}
.tp-cb-sell{right:10px;background:linear-gradient(135deg,#a855f7,#7c3aed);color:#fff;}
.tp-qview{position:absolute;bottom:-44px;left:0;right:0;background:rgba(255,255,255,0.9);color:#c94b01;text-align:center;padding:10px;font-size:12px;font-weight:700;transition:.3s;text-decoration:none;display:block;}
.tp-card.visible:hover .tp-qview{bottom:0;}
.tp-cbody{padding:13px 14px 14px;}
.tp-ctitle{font-weight:700;font-size:14px;color:#290d0d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px;}
.tp-cclub{font-size:11.5px;color:rgba(7,164,4,0.76);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:10px;}
.tp-cmeta{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
.tp-ctype{font-size:11px;font-weight:700;color:#fff;background:rgba(46,5,5,0.98);border:1px solid rgba(255,255,255,0.3);padding:3px 10px;border-radius:20px;}
.tp-cprice{font-weight:800;font-size:15px;color:#c63e03;}
.tp-cprice-old{font-size:11px;color:rgba(244,2,2,0.93);text-decoration:line-through;margin-right:3px;}
.tp-cfoot{border-top:1px solid rgb(145,143,217);padding-top:9px;display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:5px;}
.tp-cviews{font-size:11px;color:rgba(255,17,108,1);display:flex;align-items:center;gap:4px;}
.tp-cstars{color:#ef3902;font-size:12px;}
.tp-cadd{width:100%;padding:10px;background:#f44f08;color:#fbf9f8;border:none;border-radius:12px;font-size:12.5px;font-weight:800;cursor:pointer;transition:.25s;display:flex;align-items:center;justify-content:center;gap:7px;font-family:'Outfit',sans-serif;text-decoration:none;}
.tp-cadd:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(0,0,0,0.25);background:#fff5f0;}
@media(max-width:640px){
  .product-wrap{padding:0 12px;}
  .product-title{font-size:22px;}
  .price-main{font-size:26px;}
  .main-img-wrap img{height:300px;}
  .side-zoom{display:none !important;}
  .tp-card{width:90%;max-width:280px;}
  .tp-grid{justify-content:center;}
  .breadcrumb{padding:14px 16px;}
  .login-modal{padding:32px 24px;}
}
</style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<!-- BREADCRUMB -->
<div class="breadcrumb">
  <a href="../publics/index.php"><i class="fa fa-home"></i> Home</a> &rsaquo;
  <a href="../publics/index.php">Boots</a> &rsaquo;
  <?php echo htmlspecialchars($data['name']); ?>
</div>

<!-- LOGIN REQUIRED MODAL -->
<div class="login-modal-overlay" id="loginModal">
  <div class="login-modal">
    <button class="modal-close" onclick="closeModal()"><i class="fa fa-times"></i></button>
    <div class="modal-icon">🔐</div>
    <div class="modal-title">Login Garnu Parcha!</div>
    <div class="modal-sub">Order garna pehile account ma login garnus.<br>Login garisakepachi automatically yo boot ma farkaucha.</div>
    <div class="modal-product">
      <img src="<?php echo fixBootImagePath($main_img_raw); ?>" alt="<?php echo htmlspecialchars($data['name']); ?>" onerror="this.src='https://placehold.co/100x100?text=Boot'">
      <div>
        <div class="modal-product-name"><?php echo htmlspecialchars($data['name']); ?></div>
        <div class="modal-product-price">Rs. <?php echo number_format($final); ?></div>
      </div>
    </div>
    <div class="modal-btns">
      <a href="login.php?redirect=<?php echo $redirect_url; ?>" class="modal-btn-login"><i class="fa fa-sign-in-alt"></i> Login Garnus</a>
      <a href="register.php?redirect=<?php echo $redirect_url; ?>" class="modal-btn-register"><i class="fa fa-user-plus"></i> Naya Account Banaunus</a>
      <button class="modal-skip" onclick="closeModal()">Pachi garchhu — Continue browsing</button>
    </div>
  </div>
</div>

<!-- PRODUCT -->
<div class="product-wrap">

  <!-- LEFT: IMAGES -->
  <div class="img-section">
    <div class="main-img-outer">
      <div class="main-img-wrap" id="mainImgWrap">
        <?php if($disc > 0): ?>
          <div class="img-discount"><?php echo $disc; ?>% OFF</div>
        <?php endif; ?>
        <?php if(!empty($data['sold_out']) && $data['sold_out'] == 1): ?>
          <div class="img-sell">SOLD OUT</div>
        <?php elseif(!empty($data['is_new']) && $data['is_new'] == 1): ?>
          <div class="img-new">NEW</div>
        <?php endif; ?>
        <img id="mainImg"
             src="<?php echo $main_img_fixed; ?>"
             alt="<?php echo htmlspecialchars($data['name']); ?>"
             onerror="this.src='https://placehold.co/600x420?text=Boot'">
      </div>
      <div class="side-zoom" id="sideZoom">
        <img id="sideZoomImg" src="<?php echo $main_img_fixed; ?>" alt="zoom">
        <div class="side-zoom-label"><i class="fa fa-search-plus"></i> Zoomed View</div>
      </div>
    </div>

    <!-- THUMBS (MAIN + EXTRA IMAGES) -->
    <div class="thumbs">
      <!-- Main image thumbnail -->
      <div class="thumb-wrap">
        <img src="<?php echo $main_img_fixed; ?>" class="active" onclick="changeImg(this)" onerror="this.src='https://placehold.co/72x72?text=Boot'">
        <div class="zoom-popup"><img src="<?php echo $main_img_fixed; ?>"></div>
      </div>
      <!-- Extra images -->
      <?php 
      $extra_count = 0;
      foreach($extraImages as $img): 
        $img = trim($img);
        if(empty($img)) continue;
        $extra_count++;
        $fixed_extra = fixBootImagePath($img);
      ?>
      <div class="thumb-wrap">
        <img src="<?php echo $fixed_extra; ?>" onclick="changeImg(this)" onerror="this.src='https://placehold.co/72x72?text=Boot'">
        <div class="zoom-popup"><img src="<?php echo $fixed_extra; ?>"></div>
      </div>
      <?php endforeach; ?>
      <?php if($extra_count == 0): ?>
        <!-- Optional: hint that no extra images exist -->
        <div style="font-size:11px; color:#aaa; margin-left:5px;">No extra images</div>
      <?php endif; ?>
    </div>

    <!-- BOOT SPEC TAGS -->
    <div class="spec-tags" style="margin-top:16px;">
      <?php if(!empty($data['brand'])): ?>
      <span class="spec-tag"><i class="fa fa-tag"></i> <?php echo htmlspecialchars($data['brand']); ?></span>
      <?php endif; ?>
      <?php if(!empty($data['category'])): ?>
      <span class="spec-tag"><i class="fa fa-layer-group"></i> <?php echo ucfirst(htmlspecialchars($data['category'])); ?></span>
      <?php endif; ?>
      <?php if(!empty($data['sport_type'])): ?>
      <span class="spec-tag"><i class="fa fa-futbol"></i> <?php echo htmlspecialchars($data['sport_type']); ?></span>
      <?php endif; ?>
      <?php if(!empty($data['stud_type'])): ?>
      <span class="spec-tag"><i class="fa fa-circle-dot"></i> <?php echo htmlspecialchars($data['stud_type']); ?></span>
      <?php endif; ?>
      <?php if(!empty($data['upper_material'])): ?>
      <span class="spec-tag"><i class="fa fa-shirt"></i> <?php echo htmlspecialchars($data['upper_material']); ?></span>
      <?php endif; ?>
    </div>
  </div>

  <!-- RIGHT: INFO (unchanged) -->
  <div class="info-section">
    <div>
      <?php if(!empty($data['brand'])): ?>
      <div class="product-brand"><i class="fa fa-tag"></i> <?php echo htmlspecialchars($data['brand']); ?></div>
      <?php endif; ?>
      <h1 class="product-title"><?php echo htmlspecialchars($data['name']); ?></h1>
    </div>
    <div class="product-meta">
      <?php if(!empty($data['category'])): ?>
      <span class="meta-badge"><i class="fa fa-layer-group"></i> <?php echo ucfirst(htmlspecialchars($data['category'])); ?></span>
      <?php endif; ?>
      <span class="meta-badge"><i class="fa fa-eye"></i> <?php echo number_format($data['views'] ?? 0); ?> views</span>
      <span class="shop-badge"><i class="fa fa-store"></i> <?php echo $shop_display; ?></span>
      <?php if(!$is_logged_in): ?>
      <span class="meta-badge" style="background:rgba(255,200,100,0.2);border-color:rgba(255,200,100,0.4);"><i class="fa fa-lock"></i> Login required to order</span>
      <?php endif; ?>
    </div>
    <div class="stars">
      <?php for($i=1;$i<=5;$i++) echo $i<=$avg ? '<i class="fa fa-star"></i>' : '<i class="fa fa-star" style="color:rgba(255,255,255,0.25)"></i>'; ?>
      <span><?php echo $avg; ?>.0 rating</span>
    </div>
    <div class="price-row">
      <span class="price-main">Rs. <?php echo number_format($final); ?></span>
      <?php if($disc > 0): ?>
        <span class="price-original">Rs. <?php echo number_format($orig); ?></span>
        <span class="price-save">Save <?php echo $disc; ?>%</span>
      <?php endif; ?>
    </div>
    <?php if(!empty($data['description'])): ?>
    <div class="desc"><?php echo htmlspecialchars($data['description']); ?></div>
    <?php endif; ?>

    <!-- SIZE + FORM -->
    <form method="POST" id="mainForm">
      <div class="size-label"><i class="fa fa-shoe-prints"></i> &nbsp;Select Size (EU)</div>
      <div class="size-wrap">
        <?php foreach($sizes as $s): ?>
          <div class="size-btn" onclick="selectSize(this,'<?php echo htmlspecialchars($s); ?>')"><?php echo htmlspecialchars($s); ?></div>
        <?php endforeach; ?>
      </div>
      <div class="size-guide"><i class="fa fa-ruler"></i> Size Guide</div>
      <input type="hidden" name="size" id="selectedSize">

      <!-- LIKE + SHARE -->
      <div class="action-row" style="margin-top:16px;">
        <a href="?id=<?php echo $id; ?>&like=1" class="like-btn"><i class="fa fa-heart"></i> <?php echo $likeCount; ?> Likes</a>
        <div class="share-wrap" id="shareWrap">
          <div class="share-btn" onclick="toggleShare()"><i class="fa fa-share-alt"></i> Share</div>
          <div class="share-dropdown" id="shareDropdown">
            <div class="share-title">Share via</div>
            <div class="share-icons">
              <a class="share-link facebook" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>" target="_blank"><i class="fab fa-facebook-f"></i> Facebook</a>
              <a class="share-link instagram" href="https://www.instagram.com/" target="_blank"><i class="fab fa-instagram"></i> Instagram</a>
              <a class="share-link twitter" href="https://twitter.com/intent/tweet?url=<?php echo urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode($data['name']); ?>" target="_blank"><i class="fab fa-x-twitter"></i> X (Twitter)</a>
              <a class="share-link copy" href="#" onclick="copyLink(event)"><i class="fa fa-link"></i> Copy Link</a>
            </div>
          </div>
        </div>
      </div>

      <!-- SIZE ALERT -->
      <div id="size-alert" style="display:none;"><i class="fa fa-circle-exclamation"></i> Please select a size first!</div>

      <!-- CTA BUTTONS -->
      <div class="btn-group" style="margin-top:16px;">
        <?php if($is_logged_in): ?>
          <button class="btn cart-btn" type="button" onclick="submitForm('add_to_cart')"><i class="fa fa-shopping-cart"></i> Add to Cart</button>
          <button class="btn buy-btn" type="button" onclick="submitForm('buy_now')"><i class="fa fa-bolt"></i> Buy Now</button>
        <?php else: ?>
          <button class="btn cart-btn" type="button" onclick="requireLogin()"><i class="fa fa-shopping-cart"></i> Add to Cart</button>
          <button class="btn buy-btn" type="button" onclick="requireLogin()"><i class="fa fa-bolt"></i> Buy Now</button>
        <?php endif; ?>
      </div>
      <?php if(!$is_logged_in): ?>
      <div style="margin-top:10px;text-align:center;font-size:12.5px;color:rgba(255,255,255,0.55);">
        <i class="fa fa-lock" style="font-size:11px;"></i> Order garna <a href="login.php?redirect=<?php echo $redirect_url; ?>" style="color:#fde68a;font-weight:700;">Login garnus</a> ya <a href="register.php?redirect=<?php echo $redirect_url; ?>" style="color:#fde68a;font-weight:700;">Register garnus</a>
      </div>
      <?php endif; ?>
    </form>

    <div class="guarantee">
      <div class="guar-item"><i class="fa fa-shield-alt"></i> Authentic Quality</div>
      <div class="guar-item"><i class="fa fa-undo"></i> Easy Returns</div>
      <div class="guar-item"><i class="fa fa-truck"></i> Fast Delivery</div>
    </div>
  </div>
</div>

<!-- COMMENTS -->
<div class="comment-section">
  <div class="comment-card">
    <h3><i class="fa fa-comments"></i> Customer Reviews</h3>
    <form method="POST">
      <textarea class="comment-input" name="comment" placeholder="Share your experience about this boot..."></textarea>
      <button class="post-btn" type="submit"><i class="fa fa-paper-plane"></i> Post Comment</button>
    </form>
    <div class="comment-list">
      <?php
      $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
      while($row = $comments->fetch_assoc()):
        $letter = $letters[rand(0,25)];
      ?>
      <div class="comment-item">
        <div class="comment-avatar"><?php echo $letter; ?></div>
        <div><?php echo htmlspecialchars($row['comment']); ?></div>
      </div>
      <?php endwhile; ?>
    </div>
  </div>
</div>

<!-- TOP PICKS (other boots) -->
<?php if(count($top_rows) > 0): ?>
<div class="top-picks-section">
  <div class="top-strip-hd">
    <h2><i class="fa fa-star"></i> ⭐ More Top Boots</h2>
    <div class="ts-line"></div>
    <a href="../publics/index.php">View All <i class="fa fa-arrow-right" style="font-size:10px;"></i></a>
  </div>
  <div class="tp-grid" id="tp-grid">
    <?php foreach($top_rows as $tp):
      $tp_has_disc = !empty($tp['discount_percent']) && $tp['discount_percent'] > 0;
      $tp_is_new   = !empty($tp['is_new'])   && $tp['is_new']   == 1;
      $tp_sold_out = !empty($tp['sold_out']) && $tp['sold_out'] == 1;
      $tp_orig     = $tp_has_disc ? round($tp['price'] / (1 - $tp['discount_percent']/100)) : 0;
      $tp_avg      = round($tp['rating'] ?? 0);
      $tp_img_raw  = !empty($tp['main_image']) ? $tp['main_image'] : ($tp['image'] ?? '');
      $tp_img_fixed = fixBootImagePath($tp_img_raw);
    ?>
    <div class="tp-card">
      <div class="tp-cimg">
        <?php if($tp_has_disc): ?><div class="tp-cbadge tp-cb-disc"><?php echo $tp['discount_percent']; ?>% OFF</div><?php endif; ?>
        <?php if($tp_is_new): ?><div class="tp-cbadge tp-cb-new">NEW</div>
        <?php elseif($tp_sold_out): ?><div class="tp-cbadge tp-cb-sell">SOLD OUT</div>
        <?php elseif(!$tp_has_disc): ?><div class="tp-cbadge tp-cb-hot">HOT</div>
        <?php endif; ?>
        <a href="boot_details.php?id=<?php echo $tp['id']; ?>">
          <img src="<?php echo $tp_img_fixed; ?>" alt="<?php echo htmlspecialchars($tp['name']); ?>" onerror="this.src='https://placehold.co/240x200?text=Boot'">
        </a>
        <a class="tp-qview" href="boot_details.php?id=<?php echo $tp['id']; ?>"><i class="fa fa-eye"></i> Quick View</a>
      </div>
      <div class="tp-cbody">
        <div class="tp-ctitle"><?php echo htmlspecialchars($tp['name']); ?></div>
        <div class="tp-cclub"><?php echo htmlspecialchars($tp['brand'] ?? 'SportGhar'); ?> • Football Boots</div>
        <div class="tp-cmeta">
          <span class="tp-ctype"><?php echo ucfirst(htmlspecialchars($tp['category'] ?? 'Boot')); ?></span>
          <span class="tp-cprice">
            <?php if($tp_has_disc): ?><span class="tp-cprice-old">Rs.<?php echo number_format($tp_orig); ?></span><?php endif; ?>
            Rs. <?php echo number_format($tp['price']); ?>
          </span>
        </div>
        <div class="tp-cfoot">
          <span class="tp-cviews"><i class="fa fa-eye"></i> <?php echo number_format($tp['views'] ?? 0); ?></span>
          <span class="tp-cstars"><?php for($i=1;$i<=5;$i++) echo $i<=$tp_avg ? '★' : '☆'; ?></span>
        </div>
        <a class="tp-cadd" href="boot_details.php?id=<?php echo $tp['id']; ?>"><i class="fa fa-eye"></i> View Boot</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<script>
/* ── LOGIN ── */
const IS_LOGGED_IN = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
function requireLogin(){
  document.getElementById('loginModal').classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeModal(){
  document.getElementById('loginModal').classList.remove('show');
  document.body.style.overflow = '';
}
document.getElementById('loginModal').addEventListener('click', function(e){
  if(e.target === this) closeModal();
});
document.addEventListener('keydown', function(e){
  if(e.key === 'Escape') closeModal();
});

/* ── IMAGE ZOOM ── */
const mainWrap    = document.getElementById('mainImgWrap');
const mainImg     = document.getElementById('mainImg');
const sideZoomImg = document.getElementById('sideZoomImg');
mainWrap.addEventListener('mousemove', function(e){
  const rect   = mainWrap.getBoundingClientRect();
  const xRatio = (e.clientX - rect.left)  / rect.width;
  const yRatio = (e.clientY - rect.top)   / rect.height;
  const scale  = 2.5;
  const xPct   = Math.max(0, Math.min(100, xRatio * 100));
  const yPct   = Math.max(0, Math.min(100, yRatio * 100));
  sideZoomImg.style.transformOrigin = xPct + '% ' + yPct + '%';
  sideZoomImg.style.transform       = 'scale(' + scale + ')';
});

/* ── THUMBNAIL ── */
function changeImg(el){
  mainImg.src     = el.src;
  sideZoomImg.src = el.src;
  document.querySelectorAll(".thumbs img").forEach(i=>i.classList.remove("active"));
  el.classList.add("active");
}

/* ── SIZE SELECT ── */
function selectSize(el, val){
  document.querySelectorAll(".size-btn").forEach(s=>s.classList.remove("active"));
  el.classList.add("active");
  document.getElementById("selectedSize").value = val;
}

/* ── SHARE ── */
function toggleShare(){
  const wrap = document.getElementById('shareWrap');
  wrap.classList.toggle('open');
}
document.addEventListener('click', function(e){
  const wrap = document.getElementById('shareWrap');
  if(wrap && !wrap.contains(e.target)) wrap.classList.remove('open');
});
function copyLink(e){
  e.preventDefault();
  navigator.clipboard.writeText(window.location.href);
  const btn = e.currentTarget;
  btn.innerHTML = '<i class="fa fa-check"></i> Copied!';
  btn.style.background = 'rgba(34,197,94,0.3)';
  btn.style.color = '#fff';
  setTimeout(()=>{
    btn.innerHTML = '<i class="fa fa-link"></i> Copy Link';
    btn.style.background = '';
    btn.style.color = '';
  }, 2000);
}

/* ── FORM SUBMIT ── */
function submitForm(action){
  const size    = document.getElementById("selectedSize").value;
  const alertEl = document.getElementById("size-alert");
  if(!size){
    alertEl.style.display = "block";
    document.querySelectorAll(".size-btn").forEach(s=>{
      s.style.borderColor = "rgba(239,68,68,0.8)";
      s.style.background  = "rgba(239,68,68,0.2)";
    });
    setTimeout(()=>{
      document.querySelectorAll(".size-btn").forEach(s=>{
        s.style.borderColor = "";
        s.style.background  = "";
      });
    }, 1500);
    return;
  }
  alertEl.style.display = "none";
  const form  = document.getElementById("mainForm");
  const input = document.createElement("input");
  input.type  = "hidden";
  input.name  = action;
  input.value = "1";
  form.appendChild(input);
  form.submit();
}

/* ── TOP PICKS ANIMATION ── */
window.addEventListener('load', function(){
  document.querySelectorAll('.tp-card').forEach(function(card, i){
    setTimeout(function(){ card.classList.add('visible'); }, i * 80);
  });
});
</script>

</body>
</html>
<?php include "../includes/footer.php"; ?>