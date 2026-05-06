<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../databases/db.php";
$conn = getDB();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$id = (int)($_GET['id'] ?? 0);

// ================= SAFE COLUMN CHECKER =================
function columnExists($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $col   = $conn->real_escape_string($column);
    $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    return ($check && $check->num_rows > 0);
}

// Ensure required tables exist
if (!columnExists($conn, "cricket_bats", "description")) {
    $conn->query("ALTER TABLE cricket_bats ADD COLUMN description TEXT DEFAULT NULL");
}
if (!columnExists($conn, "cricket_bats", "weight")) {
    $conn->query("ALTER TABLE cricket_bats ADD COLUMN weight VARCHAR(50) DEFAULT NULL");
}
if (!columnExists($conn, "cricket_bats", "size")) {
    $conn->query("ALTER TABLE cricket_bats ADD COLUMN size VARCHAR(50) DEFAULT NULL");
}
if (!columnExists($conn, "cricket_bats", "material")) {
    $conn->query("ALTER TABLE cricket_bats ADD COLUMN material VARCHAR(100) DEFAULT NULL");
}

// Create bat_likes table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS bat_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bat_id INT NOT NULL,
    liked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create bat_comments table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS bat_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bat_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Fetch bat details
$query = "SELECT * FROM cricket_bats WHERE id = $id AND visible = 1";
$result = $conn->query($query);
if (!$result || $result->num_rows === 0) {
    header("Location: index.php");
    exit;
}
$data = $result->fetch_assoc();

// Extra images
$extraImages = !empty($data['images']) ? explode(",", $data['images']) : [];

// ---- VIEWS ----
if (!isset($_SESSION['viewed_bat'][$id])) {
    $_SESSION['viewed_bat'][$id] = true;
    $conn->query("UPDATE cricket_bats SET views = views + 1 WHERE id = $id");
}

// ---- LIKES ----
if (isset($_GET['like'])) {
    $conn->query("INSERT INTO bat_likes (bat_id) VALUES ($id)");
    header("Location: bat_details.php?id=" . $id);
    exit;
}
$likeCount = 0;
$lk = $conn->query("SELECT COUNT(*) as total FROM bat_likes WHERE bat_id = $id");
if ($lk) {
    $likeCount = $lk->fetch_assoc()['total'];
}

// ---- COMMENTS ----
if (isset($_POST['comment'])) {
    $comment = $conn->real_escape_string($_POST['comment']);
    if (trim($comment) !== "") {
        $conn->query("INSERT INTO bat_comments (bat_id, comment) VALUES ($id, '$comment')");
        header("Location: bat_details.php?id=" . $id);
        exit;
    }
}
$comments = $conn->query("SELECT * FROM bat_comments WHERE bat_id = $id ORDER BY id DESC");

// ---- PRICE LOGIC ----
$original_price = (float)($data['original_price'] ?? 0);
$discount_price = (float)($data['discount_price'] ?? 0);
$has_discount = ($discount_price > 0 && $discount_price < $original_price);
$final_price = $has_discount ? $discount_price : $original_price;
$discount_percent = 0;
if ($has_discount && $original_price > 0) {
    $discount_percent = round((($original_price - $final_price) / $original_price) * 100);
}

$is_new = !empty($data['is_new']) && $data['is_new'] == 1;
$is_top = !empty($data['is_top']) && $data['is_top'] == 1;
$stock_qty = (int)($data['stock_qty'] ?? 0);
$is_sold_out = ($stock_qty <= 0);
$avg_rating = round($data['rating'] ?? 0);

// ---- TOP PICKS (other bats with is_top = 1) ----
$top_picks = [];
$tp_query = "SELECT * FROM cricket_bats WHERE is_top = 1 AND id != $id AND visible = 1 ORDER BY id DESC LIMIT 8";
$tp_res = $conn->query($tp_query);
if ($tp_res) {
    while ($tp = $tp_res->fetch_assoc()) {
        $top_picks[] = $tp;
    }
}

// ---- IMAGE PATH HELPER ----
function getBatImagePath($imagePath) {
    if (empty($imagePath)) {
        return 'https://placehold.co/600x500?text=Cricket+Bat';
    }
    if (preg_match('/^https?:\/\//i', $imagePath)) {
        return $imagePath;
    }
    $imagePath = ltrim($imagePath, '/');
    if (strpos($imagePath, '../') === 0) {
        return $imagePath;
    }
    return '../' . $imagePath;
}

$mainImage = getBatImagePath($data['main_image'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($data['bat_name']); ?> | Premium Cricket Bat | SportGhar Nepal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Outfit',sans-serif;background:#eef2ff;color:#1e293b;}

        .breadcrumb{padding:14px 32px;font-size:13px;color:#64748b;}
        .breadcrumb a{color:#6366f1;text-decoration:none;}
        .breadcrumb a:hover{text-decoration:underline;}

        .product-wrap{display:flex;gap:32px;max-width:1100px;margin:0 auto 40px;padding:0 24px;flex-wrap:wrap;}
        .img-section{flex:1;min-width:300px;}
        .main-img-outer{display:flex;gap:14px;align-items:flex-start;}
        .main-img-wrap{position:relative;border-radius:20px;overflow:hidden;background:#fff;border:2px solid #e0e7ff;box-shadow:0 8px 30px rgba(99,102,241,0.12);flex:1;cursor:crosshair;}
        .main-img-wrap img{width:100%;height:420px;object-fit:cover;display:block;}
        .img-discount{position:absolute;top:14px;left:14px;background:#ef4444;color:#fff;font-size:12px;font-weight:700;padding:5px 12px;border-radius:20px;z-index:2;}
        .img-sell{position:absolute;top:14px;right:14px;background:#f97316;color:#fff;font-size:12px;font-weight:700;padding:5px 12px;border-radius:20px;z-index:2;}
        .img-new{position:absolute;top:14px;right:14px;background:linear-gradient(135deg,#22c55e,#15803d);color:#fff;font-size:12px;font-weight:700;padding:5px 12px;border-radius:20px;z-index:2;}

        .side-zoom{width:260px;height:260px;min-width:260px;border-radius:18px;border:2.5px solid #6366f1;overflow:hidden;display:none;box-shadow:0 12px 40px rgba(99,102,241,0.25);background:#fff;position:relative;}
        .side-zoom img{width:100%;height:100%;object-fit:cover;transform-origin:0 0;transform:scale(2.5);pointer-events:none;transition:none;}
        .side-zoom-label{position:absolute;bottom:8px;left:50%;transform:translateX(-50%);background:rgba(99,102,241,0.85);color:#fff;font-size:11px;font-weight:600;padding:3px 12px;border-radius:20px;pointer-events:none;white-space:nowrap;}
        .main-img-outer:hover .side-zoom{display:block;}

        .thumbs{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;}
        .thumb-wrap{position:relative;display:inline-block;}
        .thumbs img{width:72px;height:72px;object-fit:cover;border-radius:12px;border:2px solid #e0e7ff;cursor:pointer;transition:0.25s;display:block;}
        .thumbs img:hover,.thumbs img.active{border-color:#6366f1;transform:translateY(-3px);box-shadow:0 6px 16px rgba(99,102,241,0.2);}
        .zoom-popup{display:none;position:absolute;bottom:calc(100% + 10px);left:50%;transform:translateX(-50%);width:200px;height:200px;border-radius:16px;overflow:hidden;border:2.5px solid #6366f1;box-shadow:0 12px 40px rgba(99,102,241,0.3);z-index:100;pointer-events:none;}
        .zoom-popup img{width:100%;height:100%;object-fit:cover;transform:scale(1.15);}
        .thumb-wrap:hover .zoom-popup{display:block;}

        .info-section{flex:1;min-width:300px;display:flex;flex-direction:column;gap:16px;}
        .product-title{font-size:28px;font-weight:800;color:#1e3a8a;line-height:1.25;}
        .product-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
        .meta-badge{background:#eef2ff;color:#6366f1;border:1.5px solid #c7d2fe;font-size:12px;font-weight:600;padding:4px 12px;border-radius:20px;display:inline-flex;align-items:center;gap:6px;}
        .stars{display:flex;align-items:center;gap:6px;}
        .stars i{color:#facc15;font-size:15px;}
        .stars span{font-size:13px;color:#64748b;}
        .price-row{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;}
        .price-main{font-size:32px;font-weight:800;color:#16a34a;}
        .price-original{font-size:16px;color:#fb410d;text-decoration:line-through;}
        .price-save{font-size:13px;font-weight:700;background:#dcfce7;color:#16a34a;padding:3px 10px;border-radius:12px;}
        .desc{font-size:14.5px;color:#475569;line-height:1.7;background:#f8fafc;border-left:3px solid #818cf8;padding:12px 16px;border-radius:0 10px 10px 0;}
        .sport-info-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
        .sport-badge{display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #86efac;color:#15803d;font-size:13px;font-weight:700;padding:7px 16px;border-radius:24px;}
        .stock-badge{display:inline-flex;align-items:center;gap:7px;background:#fef2f2;border:1.5px solid #fecaca;color:#dc2626;font-size:13px;font-weight:700;padding:7px 16px;border-radius:24px;}
        .stock-badge.in-stock{background:#e0f2fe;border-color:#bae6fd;color:#0284c7;}
        
        .action-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
        .like-btn{display:flex;align-items:center;gap:7px;background:#fff0f0;color:#ef4444;border:1.5px solid #fecaca;padding:9px 18px;border-radius:24px;font-size:14px;font-weight:600;text-decoration:none;transition:0.25s;cursor:pointer;}
        .like-btn:hover{background:#ef4444;color:#fff;border-color:#ef4444;}
        .share-wrap{position:relative;display:inline-block;}
        .share-btn{display:flex;align-items:center;gap:7px;background:#f0f9ff;color:#0284c7;border:1.5px solid #bae6fd;padding:9px 18px;border-radius:24px;font-size:14px;font-weight:600;cursor:pointer;transition:0.25s;}
        .share-btn:hover{background:#0284c7;color:#fff;border-color:#0284c7;}
        .share-dropdown{display:none;position:absolute;top:calc(100% + 10px);left:50%;transform:translateX(-50%);background:#fff;border:1.5px solid #e0e7ff;border-radius:18px;box-shadow:0 12px 40px rgba(99,102,241,0.18);padding:12px 10px;z-index:999;min-width:180px;animation:fadeSlideDown 0.22s ease;}
        @keyframes fadeSlideDown{from{opacity:0;transform:translateX(-50%) translateY(-8px);}to{opacity:1;transform:translateX(-50%) translateY(0);}}
        .share-wrap.open .share-dropdown{display:block;}
        .share-title{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;text-align:center;margin-bottom:10px;}
        .share-icons{display:flex;flex-direction:column;gap:6px;}
        .share-link{display:flex;align-items:center;gap:10px;padding:9px 14px;border-radius:12px;font-size:13px;font-weight:600;text-decoration:none;transition:0.2s;color:#334155;}
        .share-link:hover{transform:translateX(4px);}
        .share-link.facebook{background:#eef2ff;}
        .share-link.facebook:hover{background:#1877F2;color:#fff;}
        .share-link.instagram{background:#fdf2f8;}
        .share-link.instagram:hover{background:linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);color:#fff;}
        .share-link.twitter{background:#f0f9ff;}
        .share-link.twitter:hover{background:#000;color:#fff;}
        .share-link.copy{background:#f8fafc;}
        .share-link.copy:hover{background:#6366f1;color:#fff;}
        .share-link i{font-size:16px;width:20px;text-align:center;}
        .share-link.facebook i{color:#1877F2;}
        .share-link.instagram i{color:#E1306C;}
        .share-link.twitter i{color:#000;}
        .share-link.copy i{color:#6366f1;}
        .share-link:hover i{color:#fff !important;}

        .btn-group{display:flex;gap:12px;flex-wrap:wrap;margin-top:4px;}
        .btn{flex:1;min-width:140px;padding:14px 20px;border:none;border-radius:14px;font-size:15px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:0.3s;font-family:'Outfit',sans-serif;}
        .cart-btn{background:#1e3a8a;color:#fff;box-shadow:0 4px 16px rgba(30,58,138,0.25);}
        .cart-btn:hover{background:#1e40af;transform:translateY(-3px);box-shadow:0 8px 24px rgba(30,58,138,0.35);}
        .buy-btn{background:linear-gradient(135deg, #22c55e, #16a34a);color:#fff;box-shadow:0 4px 16px rgba(34,197,94,0.3);}
        .buy-btn:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(34,197,94,0.4);}
        .btn-disabled{opacity:0.5;pointer-events:none;filter:grayscale(0.1);}

        .guarantee{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px;}
        .guar-item{display:flex;align-items:center;gap:7px;font-size:12.5px;color:#64748b;background:#f8fafc;padding:8px 14px;border-radius:12px;border:1px solid #e2e8f0;}

        .specs-box{background:#f8fafc;border:1.5px solid #e0e7ff;border-radius:16px;overflow:hidden;}
        .specs-title{background:linear-gradient(135deg,#1e3a8a,#3b82f6);color:#fff;font-size:13px;font-weight:700;padding:10px 16px;display:flex;align-items:center;gap:8px;letter-spacing:.5px;}
        .specs-row{display:flex;border-bottom:1px solid #e0e7ff;font-size:13px;}
        .specs-row:last-child{border-bottom:none;}
        .specs-key{width:40%;padding:10px 16px;font-weight:600;color:#475569;background:#f1f5f9;border-right:1px solid #e0e7ff;}
        .specs-val{flex:1;padding:10px 16px;color:#334155;font-weight:500;}

        .comment-section{max-width:1100px;margin:0 auto 50px;padding:0 24px;}
        .comment-card{background:#fff;border-radius:20px;padding:28px;box-shadow:0 4px 20px rgba(99,102,241,0.08);border:1.5px solid #e0e7ff;}
        .comment-card h3{font-size:20px;font-weight:700;color:#1e3a8a;margin-bottom:18px;display:flex;align-items:center;gap:8px;}
        .comment-input{width:100%;padding:13px 16px;border:1.5px solid #e0e7ff;border-radius:12px;font-size:14px;font-family:'Outfit',sans-serif;resize:vertical;outline:none;transition:0.3s;color:#334155;background:#f8fafc;min-height:90px;}
        .comment-input:focus{border-color:#818cf8;box-shadow:0 0 0 3px rgba(129,140,248,0.15);background:#fff;}
        .post-btn{margin-top:10px;padding:11px 28px;background:#1e3a8a;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:600;font-family:'Outfit',sans-serif;cursor:pointer;transition:0.25s;}
        .post-btn:hover{background:#1e40af;transform:translateY(-2px);}
        .comment-list{margin-top:22px;display:flex;flex-direction:column;gap:12px;}
        .comment-item{background:#f8fafc;border:1px solid #e0e7ff;border-radius:12px;padding:13px 16px;font-size:14px;color:#334155;display:flex;align-items:flex-start;gap:12px;}
        .comment-avatar{width:36px;height:36px;min-width:36px;border-radius:50%;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;}

        .top-picks-section{max-width:1100px;margin:0 auto 60px;padding:0 24px;}
        .top-strip-hd{display:flex;align-items:center;gap:12px;margin-bottom:20px;}
        .top-strip-hd h2{font-size:18px;font-weight:800;color:#1e293b;display:flex;align-items:center;gap:9px;}
        .top-strip-hd h2 i{color:#f97316;}
        .top-strip-hd .ts-line{flex:1;height:1px;background:linear-gradient(90deg,rgba(249,115,22,.5),transparent);}
        .top-strip-hd a{font-size:12px;font-weight:700;color:#f97316;text-decoration:none;padding:5px 14px;border:1px solid rgba(249,115,22,.35);border-radius:20px;transition:.2s;white-space:nowrap;}
        .top-strip-hd a:hover{background:rgba(249,115,22,.1);}
        .tp-grid{display:flex;flex-wrap:wrap;gap:20px;justify-content:flex-start;}
        .tp-card{width:240px;background:linear-gradient(160deg,#1a1a2e,#0f172a);border-radius:20px;border:1px solid rgba(249,115,22,.25);overflow:hidden;position:relative;opacity:0;transform:translateY(20px);transition:.45s;}
        .tp-card.visible{opacity:1;transform:translateY(0);}
        .tp-card.visible:hover{transform:translateY(-9px);border-color:rgba(249,115,22,.6);box-shadow:0 20px 50px rgba(0,0,0,.35);}
        .top-pick-banner{display:flex;align-items:center;justify-content:center;gap:5px;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;font-size:10px;font-weight:700;letter-spacing:1.5px;padding:5px 0;text-align:center;}
        .tp-cimg{position:relative;overflow:hidden;}
        .tp-cimg img{width:100%;height:195px;object-fit:cover;display:block;transition:.45s;}
        .tp-card.visible:hover .tp-cimg img{transform:scale(1.08);}
        .tp-cbadge{position:absolute;top:10px;padding:4px 11px;font-size:10px;font-weight:700;border-radius:20px;z-index:5;}
        .tp-cb-disc{left:10px;background:#ef4444;color:#fff;}
        .tp-cb-hot{right:10px;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;}
        .tp-cb-new{right:10px;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;}
        .tp-cb-sell{right:10px;background:linear-gradient(135deg,#a855f7,#7c3aed);color:#fff;}
        .tp-qview{position:absolute;bottom:-44px;left:0;right:0;background:rgba(249,115,22,.92);color:#fff;text-align:center;padding:10px;font-size:12px;font-weight:600;transition:.3s;text-decoration:none;display:block;}
        .tp-card.visible:hover .tp-qview{bottom:0;}
        .tp-cbody{padding:13px 14px 14px;}
        .tp-ctitle{font-weight:700;font-size:14px;color:#f1f5f9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px;}
        .tp-cclub{font-size:11.5px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:10px;}
        .tp-cmeta{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
        .tp-ctype{font-size:11px;font-weight:700;color:#fb923c;background:rgba(251,146,60,.1);border:1px solid rgba(251,146,60,.2);padding:3px 10px;border-radius:20px;}
        .tp-cprice{font-weight:800;font-size:15px;color:#4ade80;}
        .tp-cprice-old{font-size:11px;color:#64748b;text-decoration:line-through;margin-right:3px;}
        .tp-cfoot{border-top:1px solid rgba(255,255,255,.06);padding-top:9px;display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:5px;}
        .tp-cviews{font-size:11px;color:#64748b;display:flex;align-items:center;gap:4px;}
        .tp-cstars{color:#fbbf24;font-size:12px;}
        .tp-csport{font-size:10.5px;font-weight:700;color:#818cf8;background:rgba(129,140,248,.1);border:1px solid rgba(129,140,248,.2);padding:2px 9px;border-radius:12px;display:flex;align-items:center;gap:4px;}
        .tp-cadd{width:100%;padding:10px;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;border:none;border-radius:12px;font-size:12.5px;font-weight:700;cursor:pointer;transition:.25s;display:flex;align-items:center;justify-content:center;gap:7px;font-family:'Outfit',sans-serif;text-decoration:none;}
        .tp-cadd:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(234,88,12,.45);}

        @media(max-width:640px){
            .product-wrap{padding:0 12px;}
            .breadcrumb{padding:14px 16px;}
            .product-title{font-size:22px;}
            .price-main{font-size:26px;}
            .main-img-wrap img{height:300px;}
            .side-zoom{display:none !important;}
            .tp-card{width:90%;max-width:280px;}
            .tp-grid{justify-content:center;}
            .comment-section,.top-picks-section{padding:0 14px;}
        }
    </style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="breadcrumb">
    <a href="../publics/index.php"><i class="fa fa-home"></i> Home</a> &rsaquo;
    <a href="../publics/index.php">Cricket Bats</a> &rsaquo;
    <?php echo htmlspecialchars($data['bat_name']); ?>
</div>

<div class="product-wrap">

    <!-- LEFT: IMAGES -->
    <div class="img-section">
        <div class="main-img-outer">
            <div class="main-img-wrap" id="mainImgWrap">
                <?php if ($has_discount): ?>
                    <div class="img-discount"><?php echo $discount_percent; ?>% OFF</div>
                <?php endif; ?>
                <?php if ($is_new): ?>
                    <div class="img-new">✦ NEW</div>
                <?php elseif ($is_top): ?>
                    <div class="img-sell"><i class="fa fa-crown"></i> TOP PICK</div>
                <?php endif; ?>
                <img id="mainImg" src="<?php echo $mainImage; ?>" alt="<?php echo htmlspecialchars($data['bat_name']); ?>">
            </div>
            <div class="side-zoom" id="sideZoom">
                <img id="sideZoomImg" src="<?php echo $mainImage; ?>" alt="zoom">
                <div class="side-zoom-label"><i class="fa fa-search-plus"></i> Zoomed View</div>
            </div>
        </div>

        <div class="thumbs">
            <div class="thumb-wrap">
                <img src="<?php echo $mainImage; ?>" class="active" onclick="changeImg(this)">
                <div class="zoom-popup"><img src="<?php echo $mainImage; ?>"></div>
            </div>
            <?php foreach ($extraImages as $img): 
                $img = trim($img);
                if (empty($img)) continue;
                $thumbSrc = getBatImagePath($img);
            ?>
            <div class="thumb-wrap">
                <img src="<?php echo $thumbSrc; ?>" onclick="changeImg(this)">
                <div class="zoom-popup"><img src="<?php echo $thumbSrc; ?>"></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- RIGHT: INFO -->
    <div class="info-section">
        <h1 class="product-title"><?php echo htmlspecialchars($data['bat_name']); ?></h1>
        <div class="product-meta">
            <span class="meta-badge"><i class="fa fa-eye"></i> <?php echo number_format($data['views'] ?? 0); ?> views</span>
            <?php if (!empty($data['brand'])): ?>
                <span class="meta-badge"><i class="fa fa-tag"></i> <?php echo htmlspecialchars($data['brand']); ?></span>
            <?php endif; ?>
        </div>

        <div class="sport-info-row">
            <span class="sport-badge"><i class="fa fa-cricket-bat-ball"></i> Cricket Bat</span>
            <?php if ($is_sold_out): ?>
                <span class="stock-badge"><i class="fa fa-ban"></i> Out of Stock</span>
            <?php else: ?>
                <span class="stock-badge in-stock"><i class="fa fa-check-circle"></i> In Stock (<?php echo $stock_qty; ?> left)</span>
            <?php endif; ?>
        </div>

        <div class="stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <?php if ($i <= $avg_rating): ?>
                    <i class="fa fa-star"></i>
                <?php else: ?>
                    <i class="fa fa-star" style="color:#e2e8f0"></i>
                <?php endif; ?>
            <?php endfor; ?>
            <span><?php echo number_format($avg_rating, 1); ?> rating</span>
        </div>

        <div class="price-row">
            <span class="price-main">Rs. <?php echo number_format($final_price); ?></span>
            <?php if ($has_discount): ?>
                <span class="price-original">Rs. <?php echo number_format($original_price); ?></span>
                <span class="price-save">Save <?php echo $discount_percent; ?>%</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($data['description'])): ?>
            <div class="desc"><?php echo nl2br(htmlspecialchars($data['description'])); ?></div>
        <?php endif; ?>

        <!-- SPECIFICATIONS TABLE -->
        <?php
        $specs = [];
        if (!empty($data['brand'])) $specs['Brand'] = $data['brand'];
        if (!empty($data['weight'])) $specs['Weight'] = $data['weight'];
        if (!empty($data['size'])) $specs['Size'] = $data['size'];
        if (!empty($data['material'])) $specs['Material'] = $data['material'];
        if (!empty($data['sport_type'])) $specs['Sport Type'] = $data['sport_type'];
        if ($stock_qty > 0) $specs['Stock'] = $stock_qty . ' units';
        ?>
        <?php if (count($specs) > 0): ?>
        <div class="specs-box">
            <div class="specs-title"><i class="fa fa-list-check"></i> Bat Specifications</div>
            <?php foreach ($specs as $k => $v): ?>
            <div class="specs-row">
                <div class="specs-key"><?php echo $k; ?></div>
                <div class="specs-val"><?php echo htmlspecialchars($v); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="batForm">
            <div class="action-row">
                <a href="?id=<?php echo $id; ?>&like=1" class="like-btn">
                    <i class="fa fa-heart"></i> <?php echo $likeCount; ?> Likes
                </a>
                <div class="share-wrap" id="shareWrap">
                    <div class="share-btn" onclick="toggleShare()">
                        <i class="fa fa-share-alt"></i> Share
                    </div>
                    <div class="share-dropdown" id="shareDropdown">
                        <div class="share-title">Share via</div>
                        <div class="share-icons">
                            <a class="share-link facebook" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>" target="_blank"><i class="fab fa-facebook-f"></i> Facebook</a>
                            <a class="share-link instagram" href="https://www.instagram.com/" target="_blank"><i class="fab fa-instagram"></i> Instagram</a>
                            <a class="share-link twitter" href="https://twitter.com/intent/tweet?url=<?php echo urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode($data['bat_name']); ?>" target="_blank"><i class="fab fa-x-twitter"></i> X (Twitter)</a>
                            <a class="share-link copy" href="#" onclick="copyLink(event)"><i class="fa fa-link"></i> Copy Link</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="btn-group" style="margin-top:16px;">
                <?php if ($is_sold_out): ?>
                    <button class="btn cart-btn btn-disabled" type="button" disabled><i class="fa fa-cart-plus"></i> Out of Stock</button>
                    <button class="btn buy-btn btn-disabled" type="button" disabled><i class="fa fa-bolt"></i> Notify Me</button>
                <?php else: ?>
                    <button class="btn cart-btn" type="button" onclick="submitForm('add_to_cart')"><i class="fa fa-cart-plus"></i> Add to Cart</button>
                    <button class="btn buy-btn" type="button" onclick="submitForm('buy_now')"><i class="fa fa-bolt"></i> Buy Now</button>
                <?php endif; ?>
            </div>
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
        <h3><i class="fa fa-comments" style="color:#818cf8;"></i> Customer Reviews</h3>
        <form method="POST">
            <textarea class="comment-input" name="comment" placeholder="Share your experience about this cricket bat..."></textarea>
            <button class="post-btn" type="submit"><i class="fa fa-paper-plane"></i> Post Comment</button>
        </form>
        <div class="comment-list">
            <?php
            $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            if ($comments && $comments->num_rows > 0):
                while ($row = $comments->fetch_assoc()):
                    $letter = $letters[random_int(0, 25)];
            ?>
            <div class="comment-item">
                <div class="comment-avatar"><?php echo $letter; ?></div>
                <div><?php echo htmlspecialchars($row['comment']); ?></div>
            </div>
            <?php endwhile; else: ?>
            <div style="text-align:center;padding:20px;color:#94a3b8;">Be the first to review this bat!</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- TOP PICKS -->
<?php if (count($top_picks) > 0): ?>
<div class="top-picks-section">
    <div class="top-strip-hd">
        <h2><i class="fa fa-star"></i> You May Also Like</h2>
        <div class="ts-line"></div>
        <a href="../publics/index.php">View All <i class="fa fa-arrow-right" style="font-size:10px;"></i></a>
    </div>
    <div class="tp-grid" id="tp-grid">
        <?php foreach ($top_picks as $tp):
            $tp_orig = (float)$tp['original_price'];
            $tp_disc = (float)$tp['discount_price'];
            $tp_has_disc = ($tp_disc > 0 && $tp_disc < $tp_orig);
            $tp_final = $tp_has_disc ? $tp_disc : $tp_orig;
            $tp_disc_percent = 0;
            if ($tp_has_disc && $tp_orig > 0) {
                $tp_disc_percent = round((($tp_orig - $tp_final) / $tp_orig) * 100);
            }
            $tp_is_new = !empty($tp['is_new']) && $tp['is_new'] == 1;
            $tp_is_top = !empty($tp['is_top']) && $tp['is_top'] == 1;
            $tp_avg = round($tp['rating'] ?? 0);
            $tp_img = getBatImagePath($tp['main_image'] ?? '');
        ?>
        <div class="tp-card">
            <?php if ($tp_is_top): ?>
            <div class="top-pick-banner"><i class="fa fa-crown"></i> TOP PICK</div>
            <?php endif; ?>
            <div class="tp-cimg">
                <?php if ($tp_has_disc): ?>
                    <div class="tp-cbadge tp-cb-disc"><?php echo $tp_disc_percent; ?>% OFF</div>
                <?php endif; ?>
                <?php if ($tp_is_new): ?>
                    <div class="tp-cbadge tp-cb-new">NEW</div>
                <?php elseif (!$tp_has_disc): ?>
                    <div class="tp-cbadge tp-cb-hot">HOT</div>
                <?php endif; ?>
                <a href="bat_details.php?id=<?php echo $tp['id']; ?>">
                    <img src="<?php echo $tp_img; ?>" alt="<?php echo htmlspecialchars($tp['bat_name']); ?>">
                </a>
                <a class="tp-qview" href="bat_details.php?id=<?php echo $tp['id']; ?>">
                    <i class="fa fa-eye"></i> Quick View
                </a>
            </div>
            <div class="tp-cbody">
                <div class="tp-ctitle"><?php echo htmlspecialchars($tp['bat_name']); ?></div>
                <div class="tp-cclub"><?php echo htmlspecialchars($tp['brand'] ?? 'SportGhar'); ?> • Cricket Bat</div>
                <div class="tp-cmeta">
                    <span class="tp-ctype"><?php echo htmlspecialchars($tp['weight'] ?? 'Standard'); ?></span>
                    <span class="tp-cprice">
                        <?php if ($tp_has_disc): ?>
                            <span class="tp-cprice-old">Rs.<?php echo number_format($tp_orig); ?></span>
                        <?php endif; ?>
                        Rs.<?php echo number_format($tp_final); ?>
                    </span>
                </div>
                <div class="tp-cfoot">
                    <span class="tp-cviews"><i class="fa fa-eye"></i> <?php echo number_format($tp['views'] ?? 0); ?></span>
                    <span class="tp-csport"><i class="fa fa-cricket-bat-ball"></i> Cricket</span>
                    <span class="tp-cstars"><?php for($i=1;$i<=5;$i++) echo $i<=$tp_avg?'★':'☆'; ?></span>
                </div>
                <a class="tp-cadd" href="cart.php?bat_id=<?php echo $tp['id']; ?>&type=bat">
                    <i class="fa fa-cart-plus"></i> Add to Cart
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
    // Main image zoom
    const mainWrap = document.getElementById('mainImgWrap');
    const mainImg = document.getElementById('mainImg');
    const sideZoomImg = document.getElementById('sideZoomImg');
    if (mainWrap) {
        mainWrap.addEventListener('mousemove', function(e) {
            const rect = mainWrap.getBoundingClientRect();
            const xRatio = (e.clientX - rect.left) / rect.width;
            const yRatio = (e.clientY - rect.top) / rect.height;
            const scale = 2.5;
            const xPct = Math.max(0, Math.min(100, xRatio * 100));
            const yPct = Math.max(0, Math.min(100, yRatio * 100));
            sideZoomImg.style.transformOrigin = xPct + '% ' + yPct + '%';
            sideZoomImg.style.transform = 'scale(' + scale + ')';
        });
    }

    function changeImg(el) {
        mainImg.src = el.src;
        sideZoomImg.src = el.src;
        document.querySelectorAll('.thumbs img').forEach(i => i.classList.remove('active'));
        el.classList.add('active');
    }

    function toggleShare() {
        document.getElementById('shareWrap').classList.toggle('open');
    }
    document.addEventListener('click', function(e) {
        const wrap = document.getElementById('shareWrap');
        if (wrap && !wrap.contains(e.target)) wrap.classList.remove('open');
    });
    function copyLink(e) {
        e.preventDefault();
        navigator.clipboard.writeText(window.location.href);
        const btn = e.currentTarget;
        btn.innerHTML = '<i class="fa fa-check"></i> Copied!';
        btn.style.background = '#22c55e';
        btn.style.color = '#fff';
        setTimeout(() => {
            btn.innerHTML = '<i class="fa fa-link"></i> Copy Link';
            btn.style.background = '';
            btn.style.color = '';
        }, 2000);
    }

    function submitForm(action) {
        const form = document.getElementById('batForm');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = action;
        input.value = '1';
        form.appendChild(input);
        form.submit();
    }

    // Top picks animation
    window.addEventListener('load', function() {
        document.querySelectorAll('.tp-card').forEach((card, i) => {
            setTimeout(() => card.classList.add('visible'), i * 80);
        });
    });
</script>

<?php include "../includes/footer.php"; ?>
</body>
</html>