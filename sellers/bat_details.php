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

// ── FETCH BAT DETAILS (with shop name if you have shop_id in cricket_bats) ──
// If cricket_bats has a shop_id column, join with shops; otherwise leave shop_name as generic.
$data = $conn->query("SELECT b.*, s.shop_name 
                      FROM cricket_bats b 
                      LEFT JOIN shops s ON b.shop_id = s.id 
                      WHERE b.id = $id")->fetch_assoc();

if(!$data){
    die("Bat not found.");
}

// Extra images (JSON stored in additional_images)
$extraImages = [];
if(!empty($data['additional_images'])){
    $extraImages = json_decode($data['additional_images'], true);
    if(!is_array($extraImages)) $extraImages = [];
}

// ── LIKE (create likes_bat table if needed) ──
$conn->query("CREATE TABLE IF NOT EXISTS likes_bat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bat_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
if(isset($_GET['like'])){
    $conn->query("INSERT INTO likes_bat (bat_id) VALUES ($id)");
    header("Location: bat_details.php?id=".$id);
    exit;
}
$likeCount = $conn->query("SELECT COUNT(*) as total FROM likes_bat WHERE bat_id=$id")->fetch_assoc()['total'];

// ── COMMENT ──
$conn->query("CREATE TABLE IF NOT EXISTS comments_bat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bat_id INT,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
if(isset($_POST['comment'])){
    $c = $conn->real_escape_string($_POST['comment']);
    if($c != ""){
        $conn->query("INSERT INTO comments_bat (bat_id, comment) VALUES ($id,'$c')");
    }
}
$comments = $conn->query("SELECT * FROM comments_bat WHERE bat_id=$id ORDER BY id DESC");

// ── ADD TO CART (no size‑selection – treat as single item) ──
if(isset($_POST['add_to_cart'])){
    if(!isset($_SESSION['user']['id'])){
        $redirect = urlencode("bat_details.php?id=".$id);
        header("Location: login.php?redirect=".$redirect);
        exit;
    }
    $size = 'One Size'; // fixed because bats have no size variations
    $_SESSION['cart']['bat_'.$id] = [
        "id"    => $data['id'],
        "name"  => $data['bat_name'],
        "price" => $data['discount_price'] > 0 ? $data['discount_price'] : $data['original_price'],
        "image" => $data['main_image'],
        "size"  => $size,
        "qty"   => 1,
        "type"  => "bat"
    ];
    header("Location: chart.php"); exit;
}

// ── BUY NOW ──
if(isset($_POST['buy_now'])){
    if(!isset($_SESSION['user']['id'])){
        $redirect = urlencode("bat_details.php?id=".$id);
        header("Location: login.php?redirect=".$redirect);
        exit;
    }
    $size = 'One Size';
    $_SESSION['cart'] = [];
    $_SESSION['cart']['bat_'.$id] = [
        "id"    => $data['id'],
        "name"  => $data['bat_name'],
        "price" => $data['discount_price'] > 0 ? $data['discount_price'] : $data['original_price'],
        "image" => $data['main_image'],
        "size"  => $size,
        "qty"   => 1,
        "type"  => "bat"
    ];
    header("Location: checkout.php"); exit;
}

// ── UPDATE VIEW COUNT ──
if(!isset($_SESSION['bat_viewed'])) $_SESSION['bat_viewed'] = [];
if(!isset($_SESSION['bat_viewed'][$id])){
    $_SESSION['bat_viewed'][$id] = true;
    $conn->query("UPDATE cricket_bats SET views = views + 1 WHERE id = $id");
}

// ── TOP PICKS (other cricket bats, visible, not current) ──
$top_bats = $conn->query("SELECT * FROM cricket_bats WHERE visible = 1 AND id != $id ORDER BY is_top DESC, id DESC LIMIT 8");
$top_rows = [];
while($tr = $top_bats->fetch_assoc()) $top_rows[] = $tr;

// Price & discount
$orig = $data['original_price'];
$disc_price = $data['discount_price'];
$has_disc = ($disc_price > 0 && $disc_price < $orig);
$final_price = $has_disc ? $disc_price : $orig;
$disc_percent = $has_disc ? round((($orig - $final_price) / $orig) * 100) : 0;
$avg = round($data['rating'] ?? 0);

// Is user logged in?
$is_logged_in = isset($_SESSION['user']['id']);
$redirect_url = urlencode("bat_details.php?id=".$id);

// Shop name (if shop_id exists and joined)
$shop_display = !empty($data['shop_name']) ? htmlspecialchars($data['shop_name']) : "SportsBazaar";

// Helper function to fix image path (from sellers/uploads/bats/)
function fixBatImagePath($img) {
    if (empty($img)) return '';
    if (strpos($img, 'http') === 0 || strpos($img, '/') === 0) return htmlspecialchars($img);
    // Images are stored in sellers/uploads/bats/ from project root
    // From publics/ folder, we need ../sellers/uploads/bats/...
    return '../sellers/' . ltrim($img, '/');
}
$main_img_fixed = fixBatImagePath($data['main_image']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($data['bat_name']); ?> — Cricket Bat | SportGhar</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* ========== INSERT THE EXACT SAME STYLES FROM jersey_details.php ========== */
/* They are too long to repeat, but you must copy the entire <style> block from your jersey_details.php */
/* Make sure it includes all classes: .product-wrap, .img-section, .side-zoom, .thumbs, .info-section, .comment-section, .top-picks-section, etc. */
/* For brevity, I will assume you copy that style block from your existing file. */
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Outfit',sans-serif;background:#eef2ff;color:#12100f;min-height:100vh;}
/* ... (copy all CSS from jersey_details.php) ... */
</style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<!-- BREADCRUMB -->
<div class="breadcrumb">
  <a href="../publics/index.php"><i class="fa fa-home"></i> Home</a> &rsaquo;
  <a href="../publics/index.php">Cricket Bats</a> &rsaquo;
  <?php echo htmlspecialchars($data['bat_name']); ?>
</div>

<!-- LOGIN MODAL (same as jersey_details) -->
<div class="login-modal-overlay" id="loginModal">
  <div class="login-modal">
    <button class="modal-close" onclick="closeModal()"><i class="fa fa-times"></i></button>
    <div class="modal-icon">🔐</div>
    <div class="modal-title">Login Garnu Parcha!</div>
    <div class="modal-sub">Order garna pehile account ma login garnus.<br>Login garisakepachi automatically yo bat ma farkaucha.</div>
    <div class="modal-product">
      <img src="<?php echo $main_img_fixed; ?>" alt="<?php echo htmlspecialchars($data['bat_name']); ?>">
      <div>
        <div class="modal-product-name"><?php echo htmlspecialchars($data['bat_name']); ?></div>
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
        <?php if($has_disc): ?>
          <div class="img-discount"><?php echo $disc_percent; ?>% OFF</div>
        <?php endif; ?>
        <?php if($data['stock_qty'] <= 0): ?>
          <div class="img-sell">OUT OF STOCK</div>
        <?php elseif(!empty($data['is_new']) && $data['is_new'] == 1): ?>
          <div class="img-sell" style="background:#22c55e;">NEW</div>
        <?php endif; ?>
        <img id="mainImg" src="<?php echo $main_img_fixed; ?>" alt="<?php echo htmlspecialchars($data['bat_name']); ?>">
      </div>
      <div class="side-zoom" id="sideZoom">
        <img id="sideZoomImg" src="<?php echo $main_img_fixed; ?>" alt="zoom">
        <div class="side-zoom-label"><i class="fa fa-search-plus"></i> Zoomed View</div>
      </div>
    </div>

    <!-- THUMBS -->
    <div class="thumbs">
      <div class="thumb-wrap">
        <img src="<?php echo $main_img_fixed; ?>" class="active" onclick="changeImg(this)">
        <div class="zoom-popup"><img src="<?php echo $main_img_fixed; ?>"></div>
      </div>
      <?php foreach($extraImages as $img): if(empty($img)) continue; $fixed = fixBatImagePath($img); ?>
      <div class="thumb-wrap">
        <img src="<?php echo $fixed; ?>" onclick="changeImg(this)">
        <div class="zoom-popup"><img src="<?php echo $fixed; ?>"></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- RIGHT: INFO -->
  <div class="info-section">

    <h1 class="product-title"><?php echo htmlspecialchars($data['bat_name']); ?></h1>

    <div class="product-meta">
      <span class="meta-badge"><i class="fa fa-tag"></i> <?php echo htmlspecialchars($data['brand']); ?></span>
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
      <span><?php echo $avg; ?>.0 rating</span>
    </div>

    <div class="price-row">
      <span class="price-main">Rs. <?php echo number_format($final_price); ?></span>
      <?php if($has_disc): ?>
        <span class="price-original">Rs. <?php echo number_format($orig); ?></span>
        <span class="price-save">Save <?php echo $disc_percent; ?>%</span>
      <?php endif; ?>
    </div>

    <!-- BAT SPECIFICATIONS (instead of size selector) -->
    <div class="specs-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; background:rgba(0,0,0,0.2); border-radius:16px; padding:12px; margin-top:8px;">
      <?php if(!empty($data['bat_type'])): ?>
        <div><strong>Bat Type:</strong> <?php echo htmlspecialchars($data['bat_type']); ?></div>
      <?php endif; ?>
      <?php if(!empty($data['grade'])): ?>
        <div><strong>Grade:</strong> <?php echo htmlspecialchars($data['grade']); ?></div>
      <?php endif; ?>
      <?php if(!empty($data['weight'])): ?>
        <div><strong>Weight:</strong> <?php echo htmlspecialchars($data['weight']); ?></div>
      <?php endif; ?>
      <?php if(!empty($data['handle_type'])): ?>
        <div><strong>Handle:</strong> <?php echo htmlspecialchars($data['handle_type']); ?></div>
      <?php endif; ?>
      <?php if(!empty($data['sweet_spot'])): ?>
        <div><strong>Sweet Spot:</strong> <?php echo htmlspecialchars($data['sweet_spot']); ?></div>
      <?php endif; ?>
      <?php if(!empty($data['edges_size'])): ?>
        <div><strong>Edges:</strong> <?php echo htmlspecialchars($data['edges_size']); ?></div>
      <?php endif; ?>
      <?php if($data['stock_qty'] > 0): ?>
        <div><strong>Stock:</strong> <?php echo $data['stock_qty']; ?> units</div>
      <?php else: ?>
        <div><strong>Stock:</strong> <span style="color:#ef4444;">Out of Stock</span></div>
      <?php endif; ?>
    </div>

    <?php if(!empty($data['description'])): ?>
    <div class="desc"><?php echo nl2br(htmlspecialchars($data['description'])); ?></div>
    <?php endif; ?>

    <!-- FORM (no size selection; hidden field with dummy size) -->
    <form method="POST" id="mainForm">
      <input type="hidden" name="size" id="selectedSize" value="One Size">

      <!-- LIKE + SHARE -->
      <div class="action-row" style="margin-top:16px;">
        <a href="?id=<?php echo $id; ?>&like=1" class="like-btn">
          <i class="fa fa-heart"></i> <?php echo $likeCount; ?> Likes
        </a>
        <div class="share-wrap" id="shareWrap">
          <div class="share-btn" onclick="toggleShare()"><i class="fa fa-share-alt"></i> Share</div>
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

      <!-- ALERT (no size needed, but keep for consistency) -->
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

<!-- COMMENTS SECTION -->
<div class="comment-section">
  <div class="comment-card">
    <h3><i class="fa fa-comments"></i> Customer Reviews</h3>
    <form method="POST">
      <textarea class="comment-input" name="comment" placeholder="Share your experience about this bat..."></textarea>
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

<!-- TOP PICKS (other bats) -->
<?php if(count($top_rows) > 0): ?>
<div class="top-picks-section">
  <div class="top-strip-hd">
    <h2><i class="fa fa-star"></i> ⭐ More Cricket Bats</h2>
    <div class="ts-line"></div>
    <a href="../publics/index.php">View All <i class="fa fa-arrow-right" style="font-size:10px;"></i></a>
  </div>
  <div class="tp-grid" id="tp-grid">
    <?php foreach($top_rows as $tp):
      $tp_has_disc = !empty($tp['discount_price']) && $tp['discount_price'] > 0;
      $tp_is_new   = !empty($tp['is_new']) && $tp['is_new'] == 1;
      $tp_sold_out = ($tp['stock_qty'] <= 0);
      $tp_orig     = $tp['original_price'];
      $tp_final    = $tp_has_disc ? $tp['discount_price'] : $tp_orig;
      $tp_avg      = round($tp['rating'] ?? 0);
      $tp_img      = fixBatImagePath($tp['main_image']);
    ?>
    <div class="tp-card">
      <div class="tp-cimg">
        <?php if($tp_has_disc): ?><div class="tp-cbadge tp-cb-disc"><?php echo round(100 - ($tp_final/$tp_orig*100)); ?>% OFF</div><?php endif; ?>
        <?php if($tp_is_new): ?><div class="tp-cbadge tp-cb-new">NEW</div>
        <?php elseif($tp_sold_out): ?><div class="tp-cbadge tp-cb-sell">OUT OF STOCK</div>
        <?php elseif(!$tp_has_disc): ?><div class="tp-cbadge tp-cb-hot">HOT</div><?php endif; ?>
        <a href="bat_details.php?id=<?php echo $tp['id']; ?>">
          <img src="<?php echo $tp_img; ?>" alt="<?php echo htmlspecialchars($tp['bat_name']); ?>">
        </a>
        <a class="tp-qview" href="bat_details.php?id=<?php echo $tp['id']; ?>"><i class="fa fa-eye"></i> Quick View</a>
      </div>
      <div class="tp-cbody">
        <div class="tp-ctitle"><?php echo htmlspecialchars($tp['bat_name']); ?></div>
        <div class="tp-cclub"><?php echo htmlspecialchars($tp['brand']); ?> • <?php echo htmlspecialchars($tp['bat_type']); ?></div>
        <div class="tp-cmeta">
          <span class="tp-ctype"><?php echo htmlspecialchars($tp['grade']); ?></span>
          <span class="tp-cprice">
            <?php if($tp_has_disc): ?><span class="tp-cprice-old">Rs.<?php echo number_format($tp_orig); ?></span><?php endif; ?>
            Rs. <?php echo number_format($tp_final); ?>
          </span>
        </div>
        <div class="tp-cfoot">
          <span class="tp-cviews"><i class="fa fa-eye"></i> <?php echo number_format($tp['views'] ?? 0); ?></span>
          <span class="tp-csport"><i class="fa fa-cricket-bat-ball"></i> Cricket</span>
          <span class="tp-cstars"><?php for($i=1;$i<=5;$i++) echo $i<=$tp_avg ? '★' : '☆'; ?></span>
        </div>
        <a class="tp-cadd" href="bat_details.php?id=<?php echo $tp['id']; ?>"><i class="fa fa-eye"></i> View Bat</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<script>
/* ── LOGIN MODAL ── */
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
if(mainWrap){
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
}

/* ── THUMBNAIL CLICK ── */
function changeImg(el){
  mainImg.src     = el.src;
  sideZoomImg.src = el.src;
  document.querySelectorAll(".thumbs img").forEach(i=>i.classList.remove("active"));
  el.classList.add("active");
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

/* ── FORM SUBMIT (no size check – always valid) ── */
function submitForm(action){
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