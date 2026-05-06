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

/* ================= COLUMN CHECK ================= */
function columnExists($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($check && $check->num_rows > 0);
}

// Ensure boxing_items has required columns
if (!columnExists($conn, "boxing_items", "views")) {
    $conn->query("ALTER TABLE boxing_items ADD COLUMN views INT DEFAULT 0");
}
if (!columnExists($conn, "boxing_items", "is_top")) {
    $conn->query("ALTER TABLE boxing_items ADD COLUMN is_top TINYINT(1) DEFAULT 0");
}
if (!columnExists($conn, "boxing_items", "is_new")) {
    $conn->query("ALTER TABLE boxing_items ADD COLUMN is_new TINYINT(1) DEFAULT 0");
}
if (!columnExists($conn, "boxing_items", "attributes")) {
    $conn->query("ALTER TABLE boxing_items ADD COLUMN attributes TEXT DEFAULT NULL");
}

/* ================= CREATE TABLES FOR LIKES & COMMENTS ================= */
$conn->query("CREATE TABLE IF NOT EXISTS boxing_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS boxing_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Fetch product data
$stmt = $conn->prepare("SELECT * FROM boxing_items WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if(!$data){
    die("Product not found.");
}

// Increment view count
if(!isset($_SESSION['viewed_boxing'])) $_SESSION['viewed_boxing'] = [];
if(!in_array($id, $_SESSION['viewed_boxing'])){
    $_SESSION['viewed_boxing'][] = $id;
    $conn->query("UPDATE boxing_items SET views = views + 1 WHERE id = $id");
}

// Get images array
$images = json_decode($data['images'], true);
if(!is_array($images)) $images = [];
$main_image = $data['main_image'];
if(empty($main_image) && !empty($images)){
    $main_image = $images[0];
}

// Parse attributes
$attributes = json_decode($data['attributes'], true);
if(!is_array($attributes)) $attributes = [];

// ── LIKE HANDLING ──
if(isset($_GET['like'])){
    $conn->query("INSERT INTO boxing_likes (product_id) VALUES ($id)");
    header("Location: boxing_details.php?id=".$id);
    exit;
}
$likeCount = $conn->query("SELECT COUNT(*) as total FROM boxing_likes WHERE product_id=$id")->fetch_assoc()['total'];

// ── COMMENT HANDLING ──
if(isset($_POST['comment'])){
    $comment = $conn->real_escape_string($_POST['comment']);
    if($comment != ""){
        $conn->query("INSERT INTO boxing_comments (product_id, comment) VALUES ($id, '$comment')");
    }
}
$comments = $conn->query("SELECT * FROM boxing_comments WHERE product_id=$id ORDER BY id DESC");

// ── ADD TO CART / BUY NOW ──
if(isset($_POST['add_to_cart']) || isset($_POST['buy_now'])){
    if(!isset($_SESSION['user']['id'])){
        $redirect = urlencode("boxing_details.php?id=".$id);
        header("Location: login.php?redirect=".$redirect);
        exit;
    }
    $selected_options = [];
    if(isset($_POST['selected_options'])){
        $selected_options = $_POST['selected_options'];
    }
    $options_json = json_encode($selected_options);
    $cart_item = [
        "id"       => $data['id'],
        "name"     => $data['title'],
        "price"    => $data['regular_price'],
        "image"    => $main_image,
        "options"  => $options_json,
        "qty"      => 1,
        "source"   => "boxing",
        "brand"    => $data['brand']
    ];
    $_SESSION['cart'][$id] = $cart_item;
    if(isset($_POST['buy_now'])){
        header("Location: checkout.php");
    } else {
        header("Location: chart.php");
    }
    exit;
}

// ── TOP PICKS ──
$top_products = $conn->query("SELECT * FROM boxing_items WHERE is_top = 1 AND id != $id ORDER BY id DESC LIMIT 8");
$top_rows = [];
while($tr = $top_products->fetch_assoc()) $top_rows[] = $tr;

// Calculate discounted price
$orig_price = $data['regular_price'];
$disc_percent = $data['discount_percent'];
$final_price = $orig_price - ($orig_price * $disc_percent / 100);
$avg = round($data['rating'] ?? 0);
$is_logged_in = isset($_SESSION['user']['id']);
$redirect_url = urlencode("boxing_details.php?id=".$id);
$shop_display = !empty($data['store_name']) ? htmlspecialchars($data['store_name']) : "SportGhar Nepal";
$category = $data['category'];
$brand = $data['brand'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($data['title']); ?> — Boxing Gear | PlayZo</title>
<link rel="shortcut icon" href="../img_logo/cropped_circle_image.png" type="image/x-icon">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{
  font-family:'Outfit',sans-serif;
  background: #eef2ff;
  color: #12100f;
  min-height:100vh;
}
.breadcrumb{
  padding:14px 32px;
  font-size:13px;
  color: #64748b;
}
.breadcrumb a{
  color: #33a609;
  text-decoration:none;
  font-weight:600;
}
.breadcrumb a:hover{text-decoration:underline;color: #1702fb;}
.product-wrap{
  display:flex;
  gap:32px;
  max-width:1100px;
  margin:0 auto 40px;
  padding:0 24px;
  flex-wrap:wrap;
}
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
  background: rgba(255,255,255,0.12);
  border:2px solid rgba(255,255,255,0.35);
  box-shadow:0 8px 30px rgba(0,0,0,0.25);
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
  background: #ef4444;
  color:#fff;
  font-size:12px;font-weight:700;
  padding:5px 12px;
  border-radius:20px;
  z-index:2;
}
.img-stock{
  position:absolute;
  top:14px;right:14px;
  background:#22c55e;
  color:#fff;
  font-size:12px;font-weight:700;
  padding:5px 12px;
  border-radius:20px;
  z-index:2;
}
.side-zoom{
  width:260px;
  height:260px;
  min-width:260px;
  border-radius:18px;
  border:2.5px solid rgba(255,255,255,0.6);
  overflow:hidden;
  display:none;
  box-shadow:0 12px 40px rgba(0,0,0,0.3);
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
  background:rgba(201,75,1,0.88);
  color:#fff;
  font-size:11px;font-weight:600;
  padding:3px 12px;
  border-radius:20px;
  pointer-events:none;
  white-space:nowrap;
}
.main-img-outer:hover .side-zoom{display:block;}
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
  border:2px solid rgba(255,255,255,0.35);
  cursor:pointer;
  transition:0.25s;
  display:block;
}
.thumbs img:hover,.thumbs img.active{
  border-color: #fff;
  transform:translateY(-3px);
  box-shadow:0 6px 16px rgba(0,0,0,0.25);
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
  border:2.5px solid #fff;
  box-shadow:0 12px 40px rgba(0,0,0,0.3);
  z-index:100;
  pointer-events:none;
}
.zoom-popup img{
  width:100%;height:100%;
  object-fit:cover;
  transform:scale(1.15);
  transition:transform 0.3s;
}
.thumb-wrap:hover .zoom-popup{display:block;}
.info-section{
  flex:1;min-width:300px;
  display:flex;flex-direction:column;gap:16px;
}
.product-title{
  font-size:28px;font-weight:800;
  color: #e25001;line-height:1.25;
}
.product-meta{
  display:flex;align-items:center;gap:10px;flex-wrap:wrap;
}
.meta-badge{
  background: #eef2ff;color: #6366f1;
  border:1.5px solid rgba(238, 144, 43, 0.97);
  font-size:12px;font-weight:600;
  padding:4px 12px;border-radius:20px;
}
.shop-badge{
  background: #fef9e6;
  color: #c94b01;
  border:1.5px solid #facc15;
  font-size:12px;font-weight:700;
  padding:4px 12px;
  border-radius:20px;
  display:inline-flex;
  align-items:center;
  gap:6px;
}
.stars{display:flex;align-items:center;gap:6px;}
.stars i{color: #facc15;font-size:15px;}
.stars span{font-size:13px;color:  #64748b;}
.price-row{
  display:flex;align-items:baseline;gap:12px;
  flex-wrap:wrap;
}
.price-main{
  font-size:32px;font-weight:800;
  color: #16a34a;
}
.price-original{
  font-size:16px;
  color:rgba(247, 59, 17, 0.98);
  text-decoration:line-through;
}
.price-save{
  font-size:13px;font-weight:700;
  background:rgba(34,197,94,0.25);
  color: #f102e5;
  border:1px solid rgba(18, 6, 250, 0.96);
  padding:3px 10px;border-radius:12px;
}
.desc{
  font-size:14.5px;
  color: #fbf7f4;
  line-height:1.7;
  background:rgba(119, 138, 91, 0.97);
  border-left:3px solid rgba(237, 6, 6, 0.98);
  padding:12px 16px;
  border-radius:0 10px 10px 0;
}
.attr-label{
  font-size:13px;font-weight:700;
  color: #0119eb;
  margin-bottom:8px;
  margin-top:4px;
}
.attr-wrap{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  margin-bottom:12px;
}
.attr-btn{
  width: auto; min-width: 46px;
  padding: 8px 14px;
  border-radius:12px;
  border:2px solid rgba(113, 249, 9, 1);
  background:rgba(46, 35, 35, 0.97);
  font-size:13px;font-weight:700;
  cursor:pointer;
  transition:0.25s;
  display:flex;
  align-items:center;
  justify-content:center;
  color: #fff;
  backdrop-filter:blur(4px);
}
.attr-btn:hover{
  border-color: #fff;
  background:rgba(243, 102, 2, 0.97);
}
.attr-btn.active{
  background: #eda9f6;
  color: #c94b01;
  border-color: #0328fc;
  box-shadow:0 4px 14px rgba(0,0,0,0.25);
  font-weight:900;
}
.attr-value-badge{
  background: #e9ecef;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  display: inline-block;
  margin-right: 8px;
  margin-bottom: 8px;
}
.attr-value-badge i{
  color: #f97316;
  margin-right: 5px;
}
.action-row{
  display:flex;align-items:center;gap:12px;flex-wrap:wrap;
  margin-top:8px;
}
.like-btn{
  display:flex;align-items:center;gap:7px;
  background:rgb(252, 64, 35);
  color: #fff;
  border:1.5px solid rgba(186, 247, 4, 0.97);
  padding:9px 18px;border-radius:24px;
  font-size:14px;font-weight:600;
  text-decoration:none;
  transition:0.25s;
}
.like-btn:hover{background:#ef4444;color:#fff;border-color:#ef4444;}
.share-wrap{position:relative;display:inline-block;}
.share-btn{
  display:flex;align-items:center;gap:7px;
  background:rgb(116, 109, 95);
  color: #fff;
  border:1.5px solid rgba(239, 113, 2, 0.97);
  padding:9px 18px;border-radius:24px;
  font-size:14px;font-weight:600;
  cursor:pointer;
  transition:0.25s;
}
.share-btn:hover{background:rgba(253, 198, 14, 0.95);}
.share-dropdown{
  display:none;
  position:absolute;
  top:calc(100% + 10px);
  left:50%;
  transform:translateX(-50%);
  background:rgba(252, 143, 143, 0.97);
  border:1.5px solid rgba(255, 255, 255, 1);
  border-radius:18px;
  box-shadow:0 12px 40px rgba(69, 214, 36, 0.93);
  padding:12px 10px;
  z-index:999;
  min-width:180px;
  backdrop-filter:blur(12px);
}
.share-wrap:hover .share-dropdown,
.share-wrap.open .share-dropdown{display:block;}
.share-title{
  font-size:11px;font-weight:700;color:rgb(244, 249, 243);
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
  border:1.5px solid rgba(20, 16, 246, 0.98);
  color: #0beb03e5;
  background:rgba(255,255,255,0.08);
}
.share-link:hover{transform:translateX(4px);}
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
  background: #1e3a8a;
  color:#fff;
  border:2px solid rgba(255,255,255,0.55);
}
.cart-btn:hover{
  background:rgba(200, 7, 52, 0.94);
  transform:translateY(-3px);
}
.buy-btn{
  background: #1aaa01;
  color:#fff;
  border:2px solid rgba(255,255,255,0.55);
}
.buy-btn:hover{
  transform:translateY(-3px);
  background: #ea2f2f;
}
.attr-alert{
  background:rgba(239,68,68,0.2);
  border:1.5px solid rgba(239,68,68,0.5);
  color:#fca5a5;
  border-radius:10px;
  padding:10px 14px;
  font-size:13.5px;
  margin-top:12px;
  display:none;
}
.guarantee{
  display:flex;gap:10px;flex-wrap:wrap;margin-top:4px;
}
.guar-item{
  display:flex;align-items:center;gap:7px;
  font-size:12.5px;color:#ffe0c8;
  background:rgba(91, 169, 1, 0.97);
  padding:8px 14px;border-radius:12px;
  border:1px solid rgba(255,255,255,0.2);
  font-weight:600;
}
.comment-section{
  max-width:1100px;
  margin:0 auto 50px;
  padding:0 24px;
}
.comment-card{
  background:transparent;
  border-radius:20px;
  padding:28px;
  box-shadow:0 4px 20px rgb(167, 250, 65);
  border:1.5px solid rgb(16, 1, 224);
  backdrop-filter:blur(8px);
}
.comment-card h3{
  font-size:20px;font-weight:700;color: #070806;
  margin-bottom:18px;
}
.comment-input{
  width:100%;
  padding:13px 16px;
  border:1.5px solid rgb(90, 255, 14);
  border-radius:12px;
  font-size:14px;
  font-family:'Outfit',sans-serif;
  background:rgba(176, 0, 173, 0.85);
  color:#fff;
  min-height:90px;
}
.comment-input::placeholder{color:rgba(224, 242, 223, 0.92);}
.post-btn{
  margin-top:10px;
  padding:11px 28px;
  background: #0d0310;
  color: #fc5d01;
  border:none;border-radius:10px;
  font-size:14px;font-weight:700;
  cursor:pointer;
  transition:0.25s;
}
.post-btn:hover{background:#f59768;}
.comment-list{margin-top:22px;display:flex;flex-direction:column;gap:12px;}
.comment-item{
  background:rgba(251, 126, 2, 0.99);
  border:1px solid rgba(76, 250, 1, 0.96);
  border-radius:12px;
  padding:13px 16px;
  font-size:14px;color: #edebea;
  display:flex;align-items:flex-start;gap:12px;
}
.comment-avatar{
  width:36px;height:36px;min-width:36px;
  border-radius:50%;
  background:rgba(250, 7, 218, 0.95);
  color:#fff;font-size:14px;font-weight:700;
  display:flex;align-items:center;justify-content:center;
  border:2px solid rgba(255,255,255,0.4);
}
.top-picks-section{
  max-width:1100px;
  margin:0 auto 60px;
  padding:0 24px;
}
.top-strip-hd{
  display:flex;align-items:center;gap:12px;margin-bottom:22px;
}
.top-strip-hd h2{
  font-size:20px;font-weight:800;
  color: #1905f5;display:flex;align-items:center;gap:9px;white-space:nowrap;
}
.ts-line{flex:1;height:1px;background:linear-gradient(90deg,rgba(38, 1, 250, 0.97),transparent);}
.top-strip-hd a{
  font-size:12px;font-weight:700;color: #f90202;text-decoration:none;
  padding:5px 14px;border:1px solid rgba(32, 244, 4, 0.96);border-radius:20px;
  transition:.2s;background:rgba(255,255,255,0.1);
}
.top-strip-hd a:hover{background:rgba(251, 148, 5, 0.97);}
.tp-grid{display:flex;flex-wrap:wrap;gap:24px;justify-content:flex-start;}
.tp-card{
  width:240px;background: #eef2ff;
  border-radius:20px;border:1px solid rgba(15, 3, 252, 0.99);
  overflow:hidden;position:relative;
  opacity:0;transform:translateY(20px);
  transition:opacity .45s ease, transform .45s ease;
}
.tp-card.visible{opacity:1;transform:translateY(0);}
.tp-card.visible:hover{
  transform:translateY(-9px);
  border-color:rgba(57, 250, 4, 0.98);
  box-shadow:0 20px 50px rgba(0,0,0,0.3);
}
.tp-cimg{position:relative;overflow:hidden;}
.tp-cimg img{width:100%;height:200px;object-fit:cover;display:block;transition:.45s;}
.tp-card.visible:hover .tp-cimg img{transform:scale(1.08);}
.tp-cbadge{
  position:absolute;top:10px;padding:4px 11px;
  font-size:10.5px;font-weight:700;border-radius:20px;z-index:5;
}
.tp-cb-disc{left:10px;background: #f0eaea;color: #fd0303;}
.tp-cb-hot{right:10px;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;}
.tp-cb-new{right:10px;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;}
.tp-cb-sell{right:10px;background:linear-gradient(135deg,#a855f7,#7c3aed);color:#fff;}
.tp-qview{
  position:absolute;bottom:-44px;left:0;right:0;
  background:rgba(255,255,255,0.9);color:#c94b01;
  text-align:center;padding:10px;font-size:12px;font-weight:700;
  transition:.3s;text-decoration:none;display:block;
}
.tp-card.visible:hover .tp-qview{bottom:0;}
.tp-cbody{padding:13px 14px 14px;}
.tp-ctitle{font-weight:700;font-size:14px;color:#290d0d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px;}
.tp-cclub{font-size:11.5px;color:rgba(7, 164, 4, 0.76);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:10px;}
.tp-cmeta{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
.tp-ctype{font-size:11px;font-weight:700;color:#fff;background:rgba(46,5,5,0.98);border:1px solid rgba(255,255,255,0.3);padding:3px 10px;border-radius:20px;}
.tp-cprice{font-weight:800;font-size:15px;color:#c63e03;}
.tp-cprice-old{font-size:11px;color:rgba(244,2,2,0.93);text-decoration:line-through;margin-right:3px;}
.tp-cfoot{border-top:1px solid rgb(145,143,217);padding-top:9px;display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:5px;}
.tp-cviews{font-size:11px;color:rgba(255,17,108,1);display:flex;align-items:center;gap:4px;}
.tp-cstars{color:#ef3902;font-size:12px;}
.tp-csport{font-size:10.5px;font-weight:700;color:#e86501;background:rgba(255,255,255,0.87);border:1px solid rgba(255,255,255,0.2);padding:2px 9px;border-radius:12px;display:flex;align-items:center;gap:4px;}
.tp-cadd{
  width:100%;padding:10px;background:#f44f08;color:#fbf9f8;
  border:none;border-radius:12px;font-size:12.5px;font-weight:800;cursor:pointer;
  transition:.25s;display:flex;align-items:center;justify-content:center;gap:7px;
  text-decoration:none;
}
.tp-cadd:hover{transform:translateY(-2px);background:#fff5f0;}

@media(max-width:640px){
  .product-wrap{padding:0 12px;}
  .product-title{font-size:22px;}
  .price-main{font-size:26px;}
  .main-img-wrap img{height:300px;}
  .side-zoom{display:none !important;}
  .tp-card{width:90%;max-width:280px;}
  .tp-grid{justify-content:center;}
  .breadcrumb{padding:14px 16px;}
}

.login-modal-overlay{
  display:none;
  position:fixed;inset:0;
  background:rgba(237, 218, 0, 0.98);
  z-index:9999;
  align-items:center;justify-content:center;
  backdrop-filter:blur(6px);
}
.login-modal-overlay.show{display:flex;}
.login-modal{
  background:linear-gradient(135deg, #7c2100 0%, #a33200 40%, #c94b01 100%);
  border:2px solid rgba(198, 104, 104, 0.3);
  border-radius:28px;
  padding:44px 40px;
  max-width:420px;
  width:90%;
  text-align:center;
  box-shadow:0 32px 80px rgba(0,0,0,0.5);
  position:relative;
}
.modal-close{
  position:absolute;top:16px;right:18px;
  background:rgba(255,255,255,0.18);
  border:none;color:#fff;
  width:34px;height:34px;border-radius:50%;
  font-size:15px;cursor:pointer;
}
.modal-icon{
  width:72px;height:72px;
  border-radius:50%;
  background:rgba(255,255,255,0.18);
  border:2.5px solid rgba(255,255,255,0.4);
  display:flex;align-items:center;justify-content:center;
  font-size:30px;
  margin:0 auto 20px;
}
.modal-title{
  font-size:24px;font-weight:800;
  color:#fff;margin-bottom:10px;
}
.modal-sub{
  font-size:14px;color:rgba(255,255,255,0.75);
  margin-bottom:28px;
}
.modal-product{
  display:flex;align-items:center;gap:12px;
  background:rgba(0,0,0,0.25);
  border:1px solid rgba(255,255,255,0.2);
  border-radius:14px;
  padding:12px 16px;
  margin-bottom:28px;
  text-align:left;
}
.modal-product img{width:52px;height:52px;border-radius:10px;object-fit:cover;}
.modal-product-name{font-size:14px;font-weight:700;color:#fff;}
.modal-product-price{font-size:13px;color:#d1fae5;}
.modal-btns{display:flex;flex-direction:column;gap:10px;}
.modal-btn-login{
  display:flex;align-items:center;justify-content:center;gap:9px;
  padding:14px;border-radius:14px;
  background:#fff;color:#c94b01;
  font-size:15px;font-weight:800;
  text-decoration:none;
  font-family:'Outfit',sans-serif;
}
.modal-btn-register{
  display:flex;align-items:center;justify-content:center;gap:9px;
  padding:12px;border-radius:14px;
  background:rgba(255,255,255,0.15);
  color:#fff;
  border:1.5px solid rgba(255,255,255,0.4);
  text-decoration:none;
  font-weight:700;
}
.modal-skip{
  margin-top:10px;font-size:12px;color:rgba(255,255,255,0.5);
  cursor:pointer;
  background:none;border:none;
}
</style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="breadcrumb">
  <a href="../publics/index.php"><i class="fa fa-home"></i> Home</a> &rsaquo;
  <a href="#">Boxing</a> &rsaquo;
  <?php echo htmlspecialchars($data['title']); ?>
</div>

<!-- LOGIN MODAL -->
<div class="login-modal-overlay" id="loginModal">
  <div class="login-modal">
    <button class="modal-close" onclick="closeModal()"><i class="fa fa-times"></i></button>
    <div class="modal-icon">🔐</div>
    <div class="modal-title">Login Garnu Parcha!</div>
    <div class="modal-sub">Order garna pehile account ma login garnus.<br>Login garisakepachi automatically yaha farkaucha.</div>
    <div class="modal-product">
      <img src="<?php echo htmlspecialchars($main_image); ?>" alt="<?php echo htmlspecialchars($data['title']); ?>">
      <div>
        <div class="modal-product-name"><?php echo htmlspecialchars($data['title']); ?></div>
        <div class="modal-product-price">Rs. <?php echo number_format($final_price); ?></div>
      </div>
    </div>
    <div class="modal-btns">
      <a href="login.php?redirect=<?php echo $redirect_url; ?>" class="modal-btn-login"><i class="fa fa-sign-in-alt"></i> Login Garnus</a>
      <a href="register.php?redirect=<?php echo $redirect_url; ?>" class="modal-btn-register"><i class="fa fa-user-plus"></i> Naya Account Banaunus</a>
      <button class="modal-skip" onclick="closeModal()">Pachi garchhu — Continue browsing</button>
    </div>
  </div>
</div>

<!-- PRODUCT WRAPPER -->
<div class="product-wrap">
  <!-- LEFT: IMAGES -->
  <div class="img-section">
    <div class="main-img-outer">
      <div class="main-img-wrap" id="mainImgWrap">
        <?php if($disc_percent > 0): ?>
          <div class="img-discount"><?php echo $disc_percent; ?>% OFF</div>
        <?php endif; ?>
        <?php if($data['stock'] <= 0): ?>
          <div class="img-stock">OUT OF STOCK</div>
        <?php endif; ?>
        <img id="mainImg" src="<?php echo htmlspecialchars($main_image); ?>" alt="<?php echo htmlspecialchars($data['title']); ?>">
      </div>
      <div class="side-zoom" id="sideZoom">
        <img id="sideZoomImg" src="<?php echo htmlspecialchars($main_image); ?>" alt="zoom">
        <div class="side-zoom-label"><i class="fa fa-search-plus"></i> Zoomed View</div>
      </div>
    </div>
    <div class="thumbs">
      <div class="thumb-wrap">
        <img src="<?php echo htmlspecialchars($main_image); ?>" class="active" onclick="changeImg(this)">
        <div class="zoom-popup"><img src="<?php echo htmlspecialchars($main_image); ?>"></div>
      </div>
      <?php foreach($images as $img): 
          if($img == $main_image) continue;
      ?>
      <div class="thumb-wrap">
        <img src="<?php echo htmlspecialchars($img); ?>" onclick="changeImg(this)">
        <div class="zoom-popup"><img src="<?php echo htmlspecialchars($img); ?>"></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- RIGHT: INFO -->
  <div class="info-section">
    <h1 class="product-title"><?php echo htmlspecialchars($data['title']); ?></h1>
    <div class="product-meta">
      <span class="meta-badge"><i class="fa fa-tag"></i> <?php echo htmlspecialchars($category); ?></span>
      <?php if($brand): ?>
        <span class="meta-badge"><i class="fa fa-building"></i> <?php echo htmlspecialchars($brand); ?></span>
      <?php endif; ?>
      <span class="meta-badge"><i class="fa fa-eye"></i> <?php echo number_format($data['views'] ?? 0); ?> views</span>
      <span class="shop-badge"><i class="fa fa-store"></i> <?php echo $shop_display; ?></span>
      <?php if(!$is_logged_in): ?>
      <span class="meta-badge" style="background:rgba(255,200,100,0.2);border-color:rgba(255,200,100,0.4);">
        <i class="fa fa-lock"></i> Login required to order
      </span>
      <?php endif; ?>
    </div>
    <div class="stars">
      <?php for($i=1;$i<=5;$i++) echo $i<=$avg ? '<i class="fa fa-star"></i>' : '<i class="fa fa-star" style="color:rgba(255,255,255,0.25)"></i>'; ?>
      <span><?php echo number_format($avg,1); ?> rating</span>
    </div>
    <div class="price-row">
      <span class="price-main">Rs. <?php echo number_format($final_price); ?></span>
      <?php if($disc_percent > 0): ?>
        <span class="price-original">Rs. <?php echo number_format($orig_price); ?></span>
        <span class="price-save">Save <?php echo $disc_percent; ?>%</span>
      <?php endif; ?>
    </div>
    <div class="desc"><?php echo nl2br(htmlspecialchars($data['description'] ?? '')); ?></div>

    <!-- DYNAMIC ATTRIBUTE DISPLAY (Read-only from database) -->
    <?php if(!empty($attributes)): ?>
      <div class="attr-label"><i class="fa fa-info-circle"></i> Product Specifications</div>
      <div class="attr-wrap">
        <?php foreach($attributes as $key => $val): 
          $displayKey = ucfirst(str_replace('_', ' ', $key));
          if(is_array($val)){
            echo '<div class="attr-value-badge"><i class="fa fa-list"></i> ' . $displayKey . ': ' . implode(', ', array_map('htmlspecialchars', $val)) . '</div>';
          } else {
            echo '<div class="attr-value-badge"><i class="fa fa-check-circle"></i> ' . $displayKey . ': ' . htmlspecialchars($val) . '</div>';
          }
        endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- ADD TO CART FORM -->
    <form method="POST" id="mainForm">
      <input type="hidden" name="selected_options" id="selectedOptionsJson" value="{}">

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

      <div class="btn-group">
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
        <i class="fa fa-lock"></i> Order garna <a href="login.php?redirect=<?php echo $redirect_url; ?>" style="color:#fde68a;font-weight:700;">Login garnus</a> ya <a href="register.php?redirect=<?php echo $redirect_url; ?>" style="color:#fde68a;font-weight:700;">Register garnus</a>
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

<!-- COMMENT SECTION -->
<div class="comment-section">
  <div class="comment-card">
    <h3><i class="fa fa-comments"></i> Customer Reviews</h3>
    <form method="POST">
      <textarea class="comment-input" name="comment" placeholder="Share your experience about this product..."></textarea>
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

<!-- TOP PICKS -->
<?php if(count($top_rows) > 0): ?>
<div class="top-picks-section">
  <div class="top-strip-hd">
    <h2><i class="fa fa-star"></i> ⭐ Top Picks</h2>
    <div class="ts-line"></div>
    <a href="../publics/index.php?filter=Boxing">View All <i class="fa fa-arrow-right"></i></a>
  </div>
  <div class="tp-grid" id="tp-grid">
    <?php foreach($top_rows as $tp):
        $tp_imgs = json_decode($tp['images'], true);
        $tp_main = $tp['main_image'] ?? (is_array($tp_imgs) ? ($tp_imgs[0] ?? '') : '');
        $tp_has_disc = $tp['discount_percent'] > 0;
        $tp_orig = $tp_has_disc ? round($tp['regular_price'] / (1 - $tp['discount_percent']/100)) : 0;
        $tp_final = $tp['regular_price'] - ($tp['regular_price'] * $tp['discount_percent'] / 100);
        $tp_avg = round($tp['rating'] ?? 0);
        $tp_sport = 'Boxing';
    ?>
    <div class="tp-card">
      <div class="tp-cimg">
        <?php if($tp_has_disc): ?><div class="tp-cbadge tp-cb-disc"><?php echo $tp['discount_percent']; ?>% OFF</div><?php endif; ?>
        <?php if($tp['is_top']): ?><div class="tp-cbadge tp-cb-hot">TOP</div><?php endif; ?>
        <?php if($tp['is_new']): ?><div class="tp-cbadge tp-cb-new">NEW</div><?php endif; ?>
        <a href="boxing_details.php?id=<?php echo $tp['id']; ?>">
          <img src="<?php echo htmlspecialchars($tp_main); ?>" alt="<?php echo htmlspecialchars($tp['title']); ?>">
        </a>
        <a class="tp-qview" href="boxing_details.php?id=<?php echo $tp['id']; ?>"><i class="fa fa-eye"></i> Quick View</a>
      </div>
      <div class="tp-cbody">
        <div class="tp-ctitle"><?php echo htmlspecialchars($tp['title']); ?></div>
        <div class="tp-cclub"><?php echo htmlspecialchars($tp['brand'] ?? '') . ' • ' . htmlspecialchars($tp['store_name'] ?? 'PlayZo Nepal'); ?></div>
        <div class="tp-cmeta">
          <span class="tp-ctype"><?php echo htmlspecialchars($tp['category']); ?></span>
          <span class="tp-cprice">
            <?php if($tp_has_disc): ?><span class="tp-cprice-old">Rs.<?php echo number_format($tp_orig); ?></span><?php endif; ?>
            Rs. <?php echo number_format($tp_final); ?>
          </span>
        </div>
        <div class="tp-cfoot">
          <span class="tp-cviews"><i class="fa fa-eye"></i> <?php echo number_format($tp['views'] ?? 0); ?></span>
          <span class="tp-csport"><i class="fa fa-hand-fist"></i> <?php echo $tp_sport; ?></span>
          <span class="tp-cstars"><?php for($i=1;$i<=5;$i++) echo $i<=$tp_avg?'★':'☆'; ?></span>
        </div>
        <a class="tp-cadd" href="boxing_details.php?id=<?php echo $tp['id']; ?>"><i class="fa fa-eye"></i> View Details</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<script>
// --- LOGIN MODAL ---
function requireLogin(){ document.getElementById('loginModal').classList.add('show'); document.body.style.overflow = 'hidden'; }
function closeModal(){ document.getElementById('loginModal').classList.remove('show'); document.body.style.overflow = ''; }
document.getElementById('loginModal').addEventListener('click', function(e){ if(e.target === this) closeModal(); });
document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeModal(); });

// --- IMAGE ZOOM & THUMBS ---
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
function changeImg(el){
  mainImg.src     = el.src;
  sideZoomImg.src = el.src;
  document.querySelectorAll(".thumbs img").forEach(i=>i.classList.remove("active"));
  el.classList.add("active");
}

// --- SHARE ---
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
  setTimeout(()=>{ btn.innerHTML = '<i class="fa fa-link"></i> Copy Link'; }, 2000);
}

// --- FORM SUBMIT ---
function submitForm(action){
  const form = document.getElementById('mainForm');
  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = action;
  input.value = '1';
  form.appendChild(input);
  form.submit();
}

// --- TOP PICKS ANIMATION ---
window.addEventListener('load', function(){
  document.querySelectorAll('.tp-card').forEach((card,i)=>{
    setTimeout(()=> card.classList.add('visible'), i*80);
  });
});
</script>

</body>
</html>
<?php include "../includes/footer.php"; ?>