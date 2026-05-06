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
$data = $conn->query("SELECT * FROM sport_items WHERE id=$id")->fetch_assoc();

if(!$data){
    header("Location: index.php");
    exit;
}

// Helper: resolve image path (same as index.php)
function getItemImagePath($img) {
    if (empty($img)) {
        return 'https://placehold.co/600x500?text=No+Image';
    }
    if (preg_match('/^https?:\/\//i', $img)) {
        return $img;
    }
    $img = ltrim($img, '/');
    if (strpos($img, '../') === 0) {
        return $img;
    }
    return '../' . $img;
}

// Process extra images with correct paths
$extraImagesRaw = !empty($data['images']) ? explode(",", $data['images']) : [];
$extraImages = [];
foreach ($extraImagesRaw as $img) {
    $img = trim($img);
    if (!empty($img)) {
        $extraImages[] = getItemImagePath($img);
    }
}

// ---- VIEWS ----
if(!isset($_SESSION['viewed_item'][$id])){
    $_SESSION['viewed_item'][$id] = true;
    $conn->query("UPDATE sport_items SET views = views + 1 WHERE id = $id");
}

// ---- LIKES ----
if(isset($_GET['like'])){
    $conn->query("INSERT INTO item_likes (item_id) VALUES ($id)");
    header("Location: item_details.php?id=".$id);
    exit;
}

$likeCount = 0;
$lk = $conn->query("SELECT COUNT(*) as total FROM item_likes WHERE item_id=$id");
if($lk) $likeCount = $lk->fetch_assoc()['total'];

// ---- COMMENTS ----
if(isset($_POST['comment'])){
    $c = $conn->real_escape_string($_POST['comment']);
    if($c != ""){
        $conn->query("INSERT INTO item_comments (item_id, comment) VALUES ($id,'$c')");
        header("Location: item_details.php?id=".$id);
        exit;
    }
}
$comments = $conn->query("SELECT * FROM item_comments WHERE item_id=$id ORDER BY id DESC");

// ---- ADD TO CART ----
if(isset($_POST['add_to_cart'])){
    $_SESSION['cart']['item_'.$id] = [
        "id"    => $data['id'],
        "name"  => $data['title'],
        "price" => $data['price'],
        "image" => getItemImagePath($data['image']),  // store resolved path
        "type"  => "item",
        "qty"   => 1
    ];
    header("Location: cart.php"); exit;
}

if(isset($_POST['buy_now'])){
    $_SESSION['cart'] = [];
    $_SESSION['cart']['item_'.$id] = [
        "id"    => $data['id'],
        "name"  => $data['title'],
        "price" => $data['price'],
        "image" => getItemImagePath($data['image']),
        "type"  => "item",
        "qty"   => 1
    ];
    header("Location: checkout.php"); exit;
}

// ---- TOP PICKS (other sport items) ----
$top_products = $conn->query("SELECT * FROM sport_items WHERE is_top = 1 AND id != $id ORDER BY id DESC LIMIT 8");
$top_rows = [];
if($top_products) while($tr = $top_products->fetch_assoc()) $top_rows[] = $tr;

$avg = round($data['rating'] ?? 0);
$disc = $data['discount'] ?? 0;
$orig_price = $data['price'];
$final_price = $disc > 0 ? round($orig_price - ($orig_price * $disc / 100)) : $orig_price;

// Main image resolved path
$mainImage = getItemImagePath($data['image']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($data['title']); ?> — SportGhar</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* ... (keep all existing CSS exactly as provided) ... */
/* The CSS remains unchanged – paste your original styles here */
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Outfit',sans-serif;background: #eef2ff;color:#1e293b;}
/* ... (rest of the CSS) ... */


*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Outfit',sans-serif;background: #eef2ff;color:#1e293b;}

/* BREADCRUMB */
.breadcrumb{
  padding:14px 32px;
  font-size:13px;
  color: #64748b;
}
.breadcrumb a{color: #6366f1;text-decoration:none;}
.breadcrumb a:hover{text-decoration:underline;}

/* MAIN WRAPPER */
.product-wrap{
  display:flex;
  gap:32px;
  max-width:1100px;
  margin:0 auto 40px;
  padding:0 24px;
  flex-wrap:wrap;
}

/* ══ LEFT: IMAGES ══ */
.img-section{flex:1;min-width:300px;}

.main-img-outer{
  display:flex;
  gap:14px;
  align-items:flex-start;
}

.main-img-wrap{
  position:relative;
  border-radius:20px;
  overflow:hidden;
  background:#fff;
  border:2px solid #e0e7ff;
  box-shadow:0 8px 30px rgba(99,102,241,0.12);
  flex:1;
  cursor:crosshair;
}
.main-img-wrap img{
  width:100%;
  height:420px;
  object-fit:cover;
  display:block;
}

.img-discount{
  position:absolute;
  top:14px;left:14px;
  background:#ef4444;
  color:#fff;
  font-size:12px;font-weight:700;
  padding:5px 12px;
  border-radius:20px;
  z-index:2;
}
.img-sell{
  position:absolute;
  top:14px;right:14px;
  background:#22c55e;
  color:#fff;
  font-size:12px;font-weight:700;
  padding:5px 12px;
  border-radius:20px;
  z-index:2;
}
.img-new{
  position:absolute;
  top:14px;right:14px;
  background:linear-gradient(135deg,#6366f1,#8b5cf6);
  color:#fff;
  font-size:12px;font-weight:700;
  padding:5px 12px;
  border-radius:20px;
  z-index:2;
}

/* ══ SIDE ZOOM PANEL ══ */
.side-zoom{
  width:260px;
  height:260px;
  min-width:260px;
  border-radius:18px;
  border:2.5px solid #6366f1;
  overflow:hidden;
  display:none;
  box-shadow:0 12px 40px rgba(99,102,241,0.25);
  background:#fff;
  position:relative;
}
.side-zoom img{
  width:100%;
  height:100%;
  object-fit:cover;
  transform-origin:0 0;
  transform:scale(2.5);
  pointer-events:none;
  transition:none;
}
.side-zoom-label{
  position:absolute;
  bottom:8px;left:50%;
  transform:translateX(-50%);
  background:rgba(99,102,241,0.85);
  color:#fff;
  font-size:11px;font-weight:600;
  padding:3px 12px;
  border-radius:20px;
  pointer-events:none;
  white-space:nowrap;
}
.main-img-outer:hover .side-zoom{display:block;}

/* THUMBS */
.thumbs{
  display:flex;
  gap:10px;
  margin-top:14px;
  flex-wrap:wrap;
}

.thumb-wrap{position:relative;display:inline-block;}
.thumbs img{
  width:72px;height:72px;
  object-fit:cover;
  border-radius:12px;
  border:2px solid #e0e7ff;
  cursor:pointer;
  transition:0.25s;
  display:block;
}
.thumbs img:hover,.thumbs img.active{
  border-color:#6366f1;
  transform:translateY(-3px);
  box-shadow:0 6px 16px rgba(99,102,241,0.2);
}
.zoom-popup{
  display:none;
  position:absolute;
  bottom:calc(100% + 10px);
  left:50%;
  transform:translateX(-50%);
  width:200px;height:200px;
  border-radius:16px;
  overflow:hidden;
  border:2.5px solid #6366f1;
  box-shadow:0 12px 40px rgba(99,102,241,0.3);
  z-index:100;
  pointer-events:none;
}
.zoom-popup img{
  width:100%;height:100%;
  object-fit:cover;
  transform:scale(1.15);
}
.thumb-wrap:hover .zoom-popup{display:block;}

/* ══ RIGHT: INFO ══ */
.info-section{
  flex:1;min-width:300px;
  display:flex;flex-direction:column;gap:16px;
}

.product-title{
  font-size:28px;font-weight:800;
  color: #1e3a8a;line-height:1.25;
}

.product-meta{
  display:flex;align-items:center;gap:10px;flex-wrap:wrap;
}
.meta-badge{
  background: #eef2ff;color: #6366f1;
  border:1.5px solid #c7d2fe;
  font-size:12px;font-weight:600;
  padding:4px 12px;border-radius:20px;
  display:inline-flex;align-items:center;gap:6px;
}

.stars{display:flex;align-items:center;gap:6px;}
.stars i{color: #facc15;font-size:15px;}
.stars span{font-size:13px;color: #64748b;}

.price-row{
  display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;
}
.price-main{
  font-size:32px;font-weight:800;color: #16a34a;
}
.price-original{
  font-size:16px;color: #fb410d;text-decoration:line-through;
}
.price-save{
  font-size:13px;font-weight:700;
  background:#dcfce7;color:#16a34a;
  padding:3px 10px;border-radius:12px;
}

.desc{
  font-size:14.5px;color:#475569;line-height:1.7;
  background:#f8fafc;
  border-left:3px solid #818cf8;
  padding:12px 16px;
  border-radius:0 10px 10px 0;
}

/* SPORT & ITEM TYPE BADGES */
.sport-info-row{
  display:flex;align-items:center;gap:10px;flex-wrap:wrap;
}
.sport-badge{
  display:inline-flex;align-items:center;gap:7px;
  background:linear-gradient(135deg,#f0fdf4,#dcfce7);
  border:1.5px solid #86efac;
  color:#15803d;
  font-size:13px;font-weight:700;
  padding:7px 16px;border-radius:24px;
}
.type-badge{
  display:inline-flex;align-items:center;gap:7px;
  background:linear-gradient(135deg,#fefce8,#fef9c3);
  border:1.5px solid #fcd34d;
  color:#b45309;
  font-size:13px;font-weight:700;
  padding:7px 16px;border-radius:24px;
}

.action-row{
  display:flex;align-items:center;gap:12px;flex-wrap:wrap;
}
.like-btn{
  display:flex;align-items:center;gap:7px;
  background:#fff0f0;color:#ef4444;
  border:1.5px solid #fecaca;
  padding:9px 18px;border-radius:24px;
  font-size:14px;font-weight:600;
  text-decoration:none;
  transition:0.25s;
  cursor:pointer;
}
.like-btn:hover{background:#ef4444;color:#fff;border-color:#ef4444;}

/* SHARE DROPDOWN */
.share-wrap{position:relative;display:inline-block;}
.share-btn{
  display:flex;align-items:center;gap:7px;
  background:#f0f9ff;color:#0284c7;
  border:1.5px solid #bae6fd;
  padding:9px 18px;border-radius:24px;
  font-size:14px;font-weight:600;
  cursor:pointer;
  transition:0.25s;
}
.share-btn:hover{background:#0284c7;color:#fff;border-color:#0284c7;}
.share-dropdown{
  display:none;
  position:absolute;
  top:calc(100% + 10px);
  left:50%;
  transform:translateX(-50%);
  background:#fff;
  border:1.5px solid #e0e7ff;
  border-radius:18px;
  box-shadow:0 12px 40px rgba(99,102,241,0.18);
  padding:12px 10px;
  z-index:999;
  min-width:180px;
  animation:fadeSlideDown 0.22s ease;
}
@keyframes fadeSlideDown{
  from{opacity:0;transform:translateX(-50%) translateY(-8px);}
  to{opacity:1;transform:translateX(-50%) translateY(0);}
}
.share-wrap.open .share-dropdown{display:block;}
.share-title{
  font-size:11px;font-weight:700;color:#94a3b8;
  text-transform:uppercase;letter-spacing:1px;
  text-align:center;margin-bottom:10px;
}
.share-icons{display:flex;flex-direction:column;gap:6px;}
.share-link{
  display:flex;align-items:center;gap:10px;
  padding:9px 14px;border-radius:12px;
  font-size:13px;font-weight:600;
  text-decoration:none;
  transition:0.2s;
  color:#334155;
}
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

.btn-group{
  display:flex;gap:12px;flex-wrap:wrap;margin-top:4px;
}
.btn{
  flex:1;min-width:140px;
  padding:14px 20px;
  border:none;border-radius:14px;
  font-size:15px;font-weight:700;
  cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:8px;
  transition:0.3s;
  font-family:'Outfit',sans-serif;
}
.cart-btn{
  background: #1e3a8a;color: #fff;
  box-shadow:0 4px 16px rgba(30,58,138,0.25);
}
.cart-btn:hover{background:#1e40af;transform:translateY(-3px);box-shadow:0 8px 24px rgba(30,58,138,0.35);}
.buy-btn{
  background:linear-gradient(135deg, #22c55e, #16a34a);color:#fff;
  box-shadow:0 4px 16px rgba(34,197,94,0.3);
}
.buy-btn:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(34,197,94,0.4);}

.guarantee{
  display:flex;gap:10px;flex-wrap:wrap;margin-top:4px;
}
.guar-item{
  display:flex;align-items:center;gap:7px;
  font-size:12.5px;color:#64748b;
  background:#f8fafc;
  padding:8px 14px;border-radius:12px;
  border:1px solid #e2e8f0;
}
.guar-item i{color:#22c55e;font-size:13px;}

/* SPECS TABLE */
.specs-box{
  background:#f8fafc;
  border:1.5px solid #e0e7ff;
  border-radius:16px;
  overflow:hidden;
}
.specs-title{
  background:linear-gradient(135deg,#1e3a8a,#3b82f6);
  color:#fff;
  font-size:13px;font-weight:700;
  padding:10px 16px;
  display:flex;align-items:center;gap:8px;
  letter-spacing:.5px;
}
.specs-row{
  display:flex;
  border-bottom:1px solid #e0e7ff;
  font-size:13px;
}
.specs-row:last-child{border-bottom:none;}
.specs-key{
  width:40%;
  padding:10px 16px;
  font-weight:600;
  color:#475569;
  background:#f1f5f9;
  border-right:1px solid #e0e7ff;
}
.specs-val{
  flex:1;
  padding:10px 16px;
  color:#334155;
  font-weight:500;
}

/* ══ COMMENT SECTION ══ */
.comment-section{
  max-width:1100px;
  margin:0 auto 50px;
  padding:0 24px;
}
.comment-card{
  background:#fff;
  border-radius:20px;
  padding:28px;
  box-shadow:0 4px 20px rgba(99,102,241,0.08);
  border:1.5px solid #e0e7ff;
}
.comment-card h3{
  font-size:20px;font-weight:700;color:#1e3a8a;
  margin-bottom:18px;
  display:flex;align-items:center;gap:8px;
}
.comment-input{
  width:100%;
  padding:13px 16px;
  border:1.5px solid #e0e7ff;
  border-radius:12px;
  font-size:14px;
  font-family:'Outfit',sans-serif;
  resize:vertical;
  outline:none;
  transition:0.3s;
  color:#334155;
  background:#f8fafc;
  min-height:90px;
}
.comment-input:focus{
  border-color:#818cf8;
  box-shadow:0 0 0 3px rgba(129,140,248,0.15);
  background:#fff;
}
.post-btn{
  margin-top:10px;
  padding:11px 28px;
  background:#1e3a8a;color:#fff;
  border:none;border-radius:10px;
  font-size:14px;font-weight:600;
  font-family:'Outfit',sans-serif;
  cursor:pointer;transition:0.25s;
}
.post-btn:hover{background:#1e40af;transform:translateY(-2px);}

.comment-list{margin-top:22px;display:flex;flex-direction:column;gap:12px;}
.comment-item{
  background:#f8fafc;
  border:1px solid #e0e7ff;
  border-radius:12px;
  padding:13px 16px;
  font-size:14px;color:#334155;
  display:flex;align-items:flex-start;gap:12px;
}
.comment-avatar{
  width:36px;height:36px;min-width:36px;
  border-radius:50%;
  background:linear-gradient(135deg,#f97316,#ea580c);
  color:#fff;font-size:14px;font-weight:700;
  display:flex;align-items:center;justify-content:center;
}

/* ══ TOP PICKS SECTION ══ */
.top-picks-section{
  max-width:1100px;
  margin:0 auto 60px;
  padding:0 24px;
}

.top-strip-hd{
  display:flex;
  align-items:center;
  gap:12px;
  margin-bottom:20px;
}
.top-strip-hd h2{
  font-size:18px;font-weight:800;color:#1e293b;
  display:flex;align-items:center;gap:9px;
}
.top-strip-hd h2 i{color:#f97316;}
.top-strip-hd .ts-line{
  flex:1;height:1px;
  background:linear-gradient(90deg,rgba(249,115,22,.5),transparent);
}
.top-strip-hd a{
  font-size:12px;font-weight:700;
  color:#f97316;text-decoration:none;
  padding:5px 14px;
  border:1px solid rgba(249,115,22,.35);
  border-radius:20px;
  transition:.2s;
  white-space:nowrap;
}
.top-strip-hd a:hover{background:rgba(249,115,22,.1);}

.tp-grid{
  display:flex;flex-wrap:wrap;
  gap:20px;
  justify-content:flex-start;
}

/* SAME DARK CARD AS INDEX */
.tp-card{
  width:240px;
  background:linear-gradient(160deg,#1a1a2e,#0f172a);
  border-radius:20px;
  border:1px solid rgba(249,115,22,.25);
  overflow:hidden;
  position:relative;
  opacity:0;transform:translateY(20px);
  transition:.45s;
}
.tp-card.visible{opacity:1;transform:translateY(0);}
.tp-card.visible:hover{
  transform:translateY(-9px);
  border-color:rgba(249,115,22,.6);
  box-shadow:0 20px 50px rgba(0,0,0,.35);
}
.top-pick-banner{
  display:flex;align-items:center;justify-content:center;gap:5px;
  background:linear-gradient(135deg,#f97316,#ea580c);
  color:#fff;font-size:10px;font-weight:700;
  letter-spacing:1.5px;padding:5px 0;text-align:center;
}
.tp-cimg{position:relative;overflow:hidden;}
.tp-cimg img{width:100%;height:195px;object-fit:cover;display:block;transition:.45s;}
.tp-card.visible:hover .tp-cimg img{transform:scale(1.08);}
.tp-cbadge{position:absolute;top:10px;padding:4px 11px;font-size:10px;font-weight:700;border-radius:20px;z-index:5;}
.tp-cb-disc{left:10px;background:#ef4444;color:#fff;}
.tp-cb-hot{right:10px;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;}
.tp-cb-new{right:10px;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;}
.tp-cb-sell{right:10px;background:linear-gradient(135deg,#a855f7,#7c3aed);color:#fff;}
.tp-qview{
  position:absolute;bottom:-44px;left:0;right:0;
  background:rgba(249,115,22,.92);color:#fff;
  text-align:center;padding:10px;font-size:12px;font-weight:600;
  transition:.3s;text-decoration:none;display:block;
}
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

/* RESPONSIVE */
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

<!-- BREADCRUMB -->
<div class="breadcrumb">
  <a href="../publics/index.php"><i class="fa fa-home"></i> Home</a> &rsaquo;
  <a href="../publics/index.php">Sport Items</a> &rsaquo;
  <?php echo htmlspecialchars($data['title']); ?>
</div>

<!-- PRODUCT -->
<div class="product-wrap">

  <!-- LEFT: IMAGES -->
  <div class="img-section">
    <div class="main-img-outer">
      <div class="main-img-wrap" id="mainImgWrap">
        <?php if(!empty($data['discount']) && $data['discount'] > 0): ?>
          <div class="img-discount"><?php echo $data['discount']; ?>% OFF</div>
        <?php endif; ?>
        <?php if(!empty($data['is_new']) && $data['is_new'] == 1): ?>
          <div class="img-new">✦ NEW</div>
        <?php elseif(!empty($data['sell']) && $data['sell']=='Yes'): ?>
          <div class="img-sell">🔥 TRENDING</div>
        <?php endif; ?>
        <img id="mainImg" src="<?php echo $mainImage; ?>"
             alt="<?php echo htmlspecialchars($data['title']); ?>">
      </div>
      <div class="side-zoom" id="sideZoom">
        <img id="sideZoomImg" src="<?php echo $mainImage; ?>" alt="zoom">
        <div class="side-zoom-label"><i class="fa fa-search-plus"></i> Zoomed View</div>
      </div>
    </div>

    <!-- THUMBNAILS -->
    <div class="thumbs">
      <div class="thumb-wrap">
        <img src="<?php echo $mainImage; ?>" class="active" onclick="changeImg(this)">
        <div class="zoom-popup">
          <img src="<?php echo $mainImage; ?>">
        </div>
      </div>
      <?php foreach($extraImages as $img): ?>
      <div class="thumb-wrap">
        <img src="<?php echo htmlspecialchars($img); ?>" onclick="changeImg(this)">
        <div class="zoom-popup">
          <img src="<?php echo htmlspecialchars($img); ?>">
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- RIGHT: INFO (unchanged) -->
  <div class="info-section">
    <h1 class="product-title"><?php echo htmlspecialchars($data['title']); ?></h1>
    <div class="product-meta">
      <span class="meta-badge"><i class="fa fa-eye"></i> <?php echo number_format($data['views'] ?? 0); ?> views</span>
      <?php if(!empty($data['brand'])): ?>
        <span class="meta-badge"><i class="fa fa-tag"></i> <?php echo htmlspecialchars($data['brand']); ?></span>
      <?php endif; ?>
    </div>
    <div class="sport-info-row">
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
      $sport = $data['sport_type'] ?? 'Other';
      $ic = $sport_icons[$sport] ?? 'fa-layer-group';
      ?>
      <span class="sport-badge"><i class="fa <?php echo $ic; ?>"></i> <?php echo htmlspecialchars($sport); ?></span>
      <?php if(!empty($data['item_type'])): ?>
      <span class="type-badge"><i class="fa fa-box"></i> <?php echo htmlspecialchars($data['item_type']); ?></span>
      <?php endif; ?>
    </div>
    <div class="stars">
      <?php for($i=1;$i<=5;$i++) echo $i<=$avg ? '<i class="fa fa-star"></i>' : '<i class="fa fa-star" style="color:#e2e8f0"></i>'; ?>
      <span><?php echo $avg; ?>.0 rating</span>
    </div>
    <div class="price-row">
      <span class="price-main">Rs. <?php echo number_format($final_price); ?></span>
      <?php if($disc > 0): ?>
        <span class="price-original">Rs. <?php echo number_format($orig_price); ?></span>
        <span class="price-save">Save <?php echo $disc; ?>%</span>
      <?php endif; ?>
    </div>
    <?php if(!empty($data['description'])): ?>
    <div class="desc"><?php echo nl2br(htmlspecialchars($data['description'])); ?></div>
    <?php endif; ?>
    <?php
    $specs = [];
    if(!empty($data['sport_type']))  $specs['Sport']       = $data['sport_type'];
    if(!empty($data['item_type']))   $specs['Item Type']   = $data['item_type'];
    if(!empty($data['brand']))       $specs['Brand']       = $data['brand'];
    if(!empty($data['color']))       $specs['Color']       = $data['color'];
    if(!empty($data['size']))        $specs['Size']        = $data['size'];
    if(!empty($data['material']))    $specs['Material']    = $data['material'];
    if(!empty($data['weight']))      $specs['Weight']      = $data['weight'];
    if(count($specs) > 0):
    ?>
    <div class="specs-box">
      <div class="specs-title"><i class="fa fa-list-check"></i> Product Specifications</div>
      <?php foreach($specs as $k => $v): ?>
      <div class="specs-row"><div class="specs-key"><?php echo $k; ?></div><div class="specs-val"><?php echo htmlspecialchars($v); ?></div></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <form method="POST" id="itemForm">
      <div class="action-row">
        <a href="?id=<?php echo $id; ?>&like=1" class="like-btn"><i class="fa fa-heart"></i> <?php echo $likeCount; ?> Likes</a>
        <div class="share-wrap" id="shareWrap">
          <div class="share-btn" onclick="toggleShare()"><i class="fa fa-share-alt"></i> Share</div>
          <div class="share-dropdown" id="shareDropdown">
            <div class="share-title">Share via</div>
            <div class="share-icons">
              <a class="share-link facebook" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>" target="_blank"><i class="fab fa-facebook-f"></i> Facebook</a>
              <a class="share-link instagram" href="https://www.instagram.com/" target="_blank"><i class="fab fa-instagram"></i> Instagram</a>
              <a class="share-link twitter" href="https://twitter.com/intent/tweet?url=<?php echo urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode($data['title']); ?>" target="_blank"><i class="fab fa-x-twitter"></i> X (Twitter)</a>
              <a class="share-link copy" href="#" onclick="copyLink(event)"><i class="fa fa-link"></i> Copy Link</a>
            </div>
          </div>
        </div>
      </div>
      <div class="btn-group" style="margin-top:16px;">
        <button class="btn cart-btn" type="button" onclick="submitForm('add_to_cart')"><i class="fa fa-cart-plus"></i> Add to Cart</button>
        <button class="btn buy-btn" type="button" onclick="submitForm('buy_now')"><i class="fa fa-bolt"></i> Buy Now</button>
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
      <textarea class="comment-input" name="comment" placeholder="Share your experience about this product..."></textarea>
      <button class="post-btn" type="submit"><i class="fa fa-paper-plane"></i> Post Comment</button>
    </form>
    <div class="comment-list">
      <?php
      $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
      if($comments) while($row = $comments->fetch_assoc()):
        $letter = $letters[rand(0,25)];
      ?>
      <div class="comment-item"><div class="comment-avatar"><?php echo $letter; ?></div><div><?php echo htmlspecialchars($row['comment']); ?></div></div>
      <?php endwhile; ?>
    </div>
  </div>
</div>

<!-- TOP PICKS (fixed image paths) -->
<?php if(count($top_rows) > 0): ?>
<div class="top-picks-section">
  <div class="top-strip-hd">
    <h2><i class="fa fa-star"></i> Top Picks</h2>
    <div class="ts-line"></div>
    <a href="../publics/index.php">View All <i class="fa fa-arrow-right" style="font-size:10px;"></i></a>
  </div>
  <div class="tp-grid" id="tp-grid">
    <?php foreach($top_rows as $tp):
      $tp_sport    = htmlspecialchars($tp['sport_type'] ?? 'Other');
      $tp_has_disc = !empty($tp['discount']) && $tp['discount'] > 0;
      $tp_is_sell  = ($tp['sell'] == 'Yes');
      $tp_is_new   = (!empty($tp['is_new']) && $tp['is_new'] == 1);
      $tp_orig     = $tp_has_disc ? round($tp['price'] / (1 - $tp['discount']/100)) : 0;
      $tp_avg      = round($tp['rating'] ?? 0);
      $tp_ic       = $sport_icons[$tp_sport] ?? 'fa-layer-group';
      $tp_img_src  = getItemImagePath($tp['image']); // ← fixed image path
    ?>
    <div class="tp-card">
      <?php if(!empty($tp['is_top']) && $tp['is_top'] == 1): ?>
      <div class="top-pick-banner"><i class="fa fa-crown"></i> TOP PICK</div>
      <?php endif; ?>
      <div class="tp-cimg">
        <?php if($tp_has_disc): ?><div class="tp-cbadge tp-cb-disc"><?php echo $tp['discount']; ?>% OFF</div><?php endif; ?>
        <?php if($tp_is_new): ?><div class="tp-cbadge tp-cb-new">NEW</div>
        <?php elseif($tp_is_sell): ?><div class="tp-cbadge tp-cb-sell">TRENDING</div>
        <?php elseif(!$tp_has_disc): ?><div class="tp-cbadge tp-cb-hot">HOT</div><?php endif; ?>
        <a href="item_details.php?id=<?php echo $tp['id']; ?>">
          <img src="<?php echo $tp_img_src; ?>" alt="<?php echo htmlspecialchars($tp['title']); ?>">
        </a>
        <a class="tp-qview" href="item_details.php?id=<?php echo $tp['id']; ?>"><i class="fa fa-eye"></i> Quick View</a>
      </div>
      <div class="tp-cbody">
        <div class="tp-ctitle"><?php echo htmlspecialchars($tp['title']); ?></div>
        <div class="tp-cclub">SportGhar • <?php echo htmlspecialchars($tp['item_type'] ?? 'Standard'); ?></div>
        <div class="tp-cmeta">
          <span class="tp-ctype"><?php echo htmlspecialchars($tp['item_type'] ?? 'Standard'); ?></span>
          <span class="tp-cprice">
            <?php if($tp_has_disc): ?><span class="tp-cprice-old">Rs.<?php echo number_format($tp_orig); ?></span><?php endif; ?>
            Rs.<?php echo number_format($tp['price']); ?>
          </span>
        </div>
        <div class="tp-cfoot">
          <span class="tp-cviews"><i class="fa fa-eye"></i> <?php echo number_format($tp['views'] ?? 0); ?></span>
          <span class="tp-csport"><i class="fa <?php echo $tp_ic; ?>"></i> <?php echo $tp_sport; ?></span>
          <span class="tp-cstars"><?php for($i=1;$i<=5;$i++) echo $i<=$tp_avg?'★':'☆'; ?></span>
        </div>
        <a class="tp-cadd" href="cart.php?item_id=<?php echo $tp['id']; ?>&type=item"><i class="fa fa-cart-plus"></i> Add to Cart</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<script>
/* ... (keep all JavaScript exactly as provided) ... */
const mainWrap = document.getElementById('mainImgWrap');
const mainImg = document.getElementById('mainImg');
const sideZoomImg = document.getElementById('sideZoomImg');
if(mainWrap) {
  mainWrap.addEventListener('mousemove', function(e){
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
function changeImg(el){
  mainImg.src = el.src;
  sideZoomImg.src = el.src;
  document.querySelectorAll('.thumbs img').forEach(i => i.classList.remove('active'));
  el.classList.add('active');
}
function toggleShare(){
  document.getElementById('shareWrap').classList.toggle('open');
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
  btn.style.background = '#22c55e';
  btn.style.color = '#fff';
  setTimeout(()=>{
    btn.innerHTML = '<i class="fa fa-link"></i> Copy Link';
    btn.style.background = '';
    btn.style.color = '';
  }, 2000);
}
function submitForm(action){
  const form = document.getElementById('itemForm');
  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = action;
  input.value = '1';
  form.appendChild(input);
  form.submit();
}
window.addEventListener('load', function(){
  document.querySelectorAll('.tp-card').forEach(function(card, i){
    setTimeout(function(){ card.classList.add('visible'); }, i * 80);
  });
});
</script>

</body>
</html>
<?php include "../includes/footer.php"; ?>




