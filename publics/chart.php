<?php
session_start();

// Database connection FIRST
include "../databases/db.php";
$conn = getDB();

if(!$conn){
    die("Database connection failed");
}

// Now include header (which may also use session)
include "../includes/header.php";

// Get logged-in user info (if any)
$userName = '';
$userEmail = '';
if(isset($_SESSION['user']['id'])){
    $userId = (int)$_SESSION['user']['id'];
    $userQuery = $conn->query("SELECT name, email FROM users WHERE id = $userId");
    if($userQuery && $userQuery->num_rows > 0){
        $userData = $userQuery->fetch_assoc();
        $userName = $userData['name'];
        $userEmail = $userData['email'];
    }
}

// CART INITIALIZE
if(!isset($_SESSION['cart'])){
    $_SESSION['cart'] = [];
}

// REMOVE ITEM
if(isset($_GET['remove'])){
    $id = $_GET['remove'];
    unset($_SESSION['cart'][$id]);
    header("Location: cart.php");
    exit;
}

// UPDATE QUANTITY
if(isset($_POST['update'])){
    foreach($_POST['qty'] as $id => $qty){
        if($qty <= 0){
            unset($_SESSION['cart'][$id]);
        } else {
            $_SESSION['cart'][$id]['qty'] = (int)$qty;
        }
    }
    header("Location: cart.php");
    exit;
}

// GRAND TOTAL
$grand_total = 0;
foreach($_SESSION['cart'] as $item){
    $grand_total += (isset($item['price']) ? $item['price'] : 0) * (isset($item['qty']) ? $item['qty'] : 1);
}
$item_count = count($_SESSION['cart']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your Cart — PlayZo</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- SEO tags -->
<meta name="description" content="PlayZo is Nepal’s complete online sports marketplace where you can buy jerseys, sports gear, fitness equipment, shoes, and accessories all in one place.">
<meta name="keywords" content="PlayZo, sports shop Nepal, buy sports gear online, sports jerseys Nepal, fitness equipment Nepal, sports accessories, online sports store">
<meta name="author" content="PlayZo">
<meta name="robots" content="index, follow">

<!-- Open Graph / Social Media SEO -->
<meta property="og:title" content="PlayZo - All Sports Products in One Place">
<meta property="og:description" content="Shop all sports gear, jerseys, fitness equipment, and accessories online at PlayZo Nepal.">
<meta property="og:type" content="website">
<meta property="og:url" content="https://www.playzo.com.np">
<meta property="og:image" content="https://www.playzo.com.np/logo.png">

<!-- Twitter SEO -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="PlayZo - All Sports Products in One Place">
<meta name="twitter:description" content="Nepal’s one-stop online sports marketplace for all sports products.">
<meta name="twitter:image" content="https://www.playzo.com.np/logo.png">

<!-- Canonical URL -->
<link rel="canonical" href="https://www.playzo.com.np">

<style>
:root {
    --bg:        #0b0e17;
    --surface:   #12161f;
    --surface-2: #1a1f2e;
    --border:    rgba(255,255,255,0.07);
    --accent:    #f97316;
    --accent-2:  #fb923c;
    --green:     #22c55e;
    --red:       #ef4444;
    --text:      #f1f5f9;
    --muted:     #64748b;
    --card-r:    18px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Barlow', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
}

/* ── PAGE WRAPPER ── */
.cart-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 40px 20px 80px;
}

/* ── HEADER with User Info (right side) ── */
.cart-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 36px;
    flex-wrap: wrap;
    gap: 16px;
}
.cart-header h1 {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: clamp(32px, 5vw, 48px);
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: -0.5px;
    line-height: 1;
}
.cart-header h1 span { color: var(--accent); }

/* User Info Box */
.user-info {
    text-align: right;
    background: rgba(255,255,255,0.03);
    backdrop-filter: blur(8px);
    border-radius: 20px;
    padding: 8px 20px;
    border: 1px solid rgba(255,255,255,0.08);
}
.user-name {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 28px;
    font-weight: 800;
    letter-spacing: 0.5px;
    color: var(--accent);
    line-height: 1.2;
}
.user-email {
    font-size: 13px;
    color: var(--muted);
    font-weight: 500;
    margin-top: 2px;
}
.cart-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(249,115,22,0.12);
    border: 1px solid rgba(249,115,22,0.3);
    color: var(--accent);
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.5px;
    padding: 5px 14px;
    border-radius: 100px;
    text-transform: uppercase;
}

/* ── LAYOUT: 50% left (cart items) + 50% right (summary) ── */
.cart-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;    /* 50% - 50% split */
    gap: 28px;
    align-items: start;
}
@media(max-width: 900px){
    .cart-layout { grid-template-columns: 1fr; }  /* stack on mobile */
}

/* ── LEFT SIDE CONTAINER WITH BACKGROUND IMAGE ── */
.cart-items {
    display: flex;
    flex-direction: column;
    gap: 20px;
    position: relative;
    /* Background image (sports / stadium) with dark overlay for readability */
    background-image: url('https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?q=80&w=2071&auto=format&fit=crop');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    border-radius: 24px;
    padding: 24px;
    z-index: 1;
    min-height: 400px;
}

/* Dark overlay to increase text contrast */
.cart-items::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(2px);
    border-radius: 24px;
    z-index: -1;
}

/* Product cards become slightly translucent but still readable */
.cart-card {
    background: rgba(18, 22, 31, 0.9);
    backdrop-filter: blur(8px);
    border: 1px solid var(--border);
    border-radius: var(--card-r);
    overflow: hidden;
    display: grid;
    grid-template-columns: 140px 1fr;
    transition: border-color 0.25s, box-shadow 0.25s;
    animation: slideUp 0.35s ease both;
}
.cart-card:hover {
    border-color: rgba(249,115,22,0.45);
    box-shadow: 0 8px 32px rgba(0,0,0,0.5);
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Stagger animation per card */
<?php $i=0; foreach($_SESSION['cart'] as $id => $_): ?>
.cart-card:nth-child(<?php echo ++$i; ?>) { animation-delay: <?php echo ($i-1)*0.07; ?>s; }
<?php endforeach; ?>

/* ── PRODUCT IMAGE ── */
.card-image-wrap {
    position: relative;
    overflow: hidden;
    background: var(--surface-2);
    height: 100%;
    min-height: 140px;
}
.card-image-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.4s ease;
}
.cart-card:hover .card-image-wrap img { transform: scale(1.05); }

/* ── CARD BODY ── */
.card-body {
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.product-name {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 20px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: var(--text);
    line-height: 1.2;
}
.product-price-row {
    display: flex;
    align-items: baseline;
    gap: 12px;
    flex-wrap: wrap;
}
.unit-price {
    font-size: 13px;
    color: var(--muted);
}
.subtotal-price {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 22px;
    font-weight: 800;
    color: var(--accent);
}

/* ── QTY CONTROL ── */
.qty-control {
    display: flex;
    align-items: center;
    gap: 0;
    background: rgba(0,0,0,0.4);
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    width: fit-content;
}
.qty-btn {
    width: 34px; height: 34px;
    background: transparent;
    border: none;
    color: var(--text);
    font-size: 14px;
    cursor: pointer;
    transition: background 0.2s;
    display: flex; align-items: center; justify-content: center;
}
.qty-btn:hover { background: rgba(255,255,255,0.15); }
.qty-input {
    width: 44px;
    text-align: center;
    background: transparent;
    border: none;
    border-left: 1px solid var(--border);
    border-right: 1px solid var(--border);
    color: var(--text);
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 16px;
    font-weight: 700;
    padding: 0;
    height: 34px;
    -moz-appearance: textfield;
}
.qty-input::-webkit-outer-spin-button,
.qty-input::-webkit-inner-spin-button { -webkit-appearance: none; }
.qty-input:focus { outline: none; }

/* ── CARD ACTIONS ── */
.card-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.btn-detail, .btn-remove {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: 0.2s;
    cursor: pointer;
}
.btn-detail {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    color: var(--text);
}
.btn-detail:hover {
    background: rgba(255,255,255,0.15);
    border-color: rgba(255,255,255,0.25);
}
.btn-remove {
    background: rgba(239,68,68,0.12);
    border: 1px solid rgba(239,68,68,0.25);
    color: var(--red);
}
.btn-remove:hover {
    background: rgba(239,68,68,0.22);
    border-color: rgba(239,68,68,0.5);
}

/* ── ORDER SUMMARY PANEL (right side) ── */
.summary-panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--card-r);
    padding: 28px;
    position: sticky;
    top: 24px;
}
.summary-panel h2 {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 22px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 24px;
    color: var(--text);
}
.shipping-note {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(34,197,94,0.07);
    border: 1px solid rgba(34,197,94,0.2);
    border-radius: 12px;
    padding: 12px 14px;
    margin-bottom: 20px;
    font-size: 13px;
    color: var(--green);
}
.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
}
.summary-row .label { color: var(--muted); }
.summary-row .value { font-weight: 600; color: var(--text); }
.summary-row .value.green { color: var(--green); }
.summary-divider {
    height: 1px;
    background: var(--border);
    margin: 16px 0;
}
.summary-total {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 24px;
}
.summary-total .t-label {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 18px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--muted);
}
.summary-total .t-value {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 32px;
    font-weight: 900;
    color: var(--accent);
    line-height: 1;
}

/* Checkout & Update buttons */
.btn-checkout {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, #ea6c0a, #f97316);
    color: #fff;
    border: none;
    border-radius: 14px;
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 20px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    cursor: pointer;
    text-decoration: none;
    transition: 0.2s;
    box-shadow: 0 6px 24px rgba(249,115,22,0.3);
}
.btn-checkout:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 32px rgba(249,115,22,0.45);
}
.btn-update {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 12px;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border);
    color: var(--muted);
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    font-family: 'Barlow', sans-serif;
    margin-top: 12px;
    transition: 0.2s;
}
.btn-update:hover {
    background: rgba(255,255,255,0.09);
    color: var(--text);
}
.continue-link {
    display: block;
    text-align: center;
    margin-top: 16px;
    color: var(--muted);
    font-size: 13px;
    text-decoration: none;
    transition: color 0.2s;
}
.continue-link:hover { color: var(--accent); }

/* Empty state */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 20px;
}
.empty-icon {
    font-size: 72px;
    margin-bottom: 20px;
    opacity: 0.2;
}
.empty-state h2 {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 32px;
    font-weight: 900;
    text-transform: uppercase;
    margin-bottom: 10px;
}
.empty-state p { color: var(--muted); margin-bottom: 28px; }
.btn-shop {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 32px;
    background: var(--accent);
    color: #fff;
    border-radius: 40px;
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 18px;
    font-weight: 800;
    text-decoration: none;
}

/* Responsive adjustments */
@media(max-width: 600px){
    .cart-card { grid-template-columns: 100px 1fr; }
    .card-body { padding: 14px; }
    .product-name { font-size: 16px; }
    .user-name { font-size: 22px; }
    .user-info { padding: 6px 14px; }
    .cart-items { padding: 16px; }
}
</style>
</head>
<body>

<div class="cart-page">

    <!-- HEADER: left title + right user info (name & email) -->
    <div class="cart-header">
        <h1>Your <span>Cart</span></h1>
        <?php if($userName): ?>
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
            <div class="user-email"><?php echo htmlspecialchars($userEmail); ?></div>
        </div>
        <?php else: ?>
        <div class="cart-badge">
            <i class="fa fa-shopping-bag"></i>
            <?php echo $item_count; ?> item<?php echo $item_count > 1 ? 's' : ''; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if(empty($_SESSION['cart'])): ?>
        <!-- EMPTY CART STATE -->
        <div class="empty-state">
            <div class="empty-icon"><i class="fa fa-shopping-cart"></i></div>
            <h2>Your cart is empty</h2>
            <p>Looks like you haven't added anything yet.</p>
            <a href="shop.php" class="btn-shop">
                <i class="fa fa-arrow-left"></i> Start Shopping
            </a>
        </div>
    <?php else: ?>

    <form method="post" id="cartForm">
    <div class="cart-layout">

        <!-- LEFT SIDE: CART ITEMS with background image -->
        <div class="cart-items">
            <?php
            foreach($_SESSION['cart'] as $id => $item):
                $price = isset($item['price']) ? $item['price'] : 0;
                $qty   = isset($item['qty'])   ? $item['qty']   : 1;
                $name  = isset($item['name'])  ? $item['name']  : 'Unknown Product';
                $image = isset($item['image']) ? $item['image'] : 'assets/no-image.png';
                $sub   = $price * $qty;
                $detail_link = "product.php?id=" . urlencode($id);
            ?>
            <div class="cart-card">
                <div class="card-image-wrap">
                    <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($name); ?>">
                </div>
                <div class="card-body">
                    <div>
                        <div class="product-name"><?php echo htmlspecialchars($name); ?></div>
                        <div class="product-price-row">
                            <span class="unit-price">Rs. <?php echo number_format($price); ?> × <?php echo $qty; ?></span>
                            <span class="subtotal-price">Rs. <?php echo number_format($sub); ?></span>
                        </div>
                    </div>
                    <div class="qty-control">
                        <button type="button" class="qty-btn" onclick="changeQty('<?php echo $id; ?>', -1)"><i class="fa fa-minus"></i></button>
                        <input class="qty-input" type="number" id="qty_<?php echo $id; ?>" name="qty[<?php echo $id; ?>]" value="<?php echo $qty; ?>" min="1" max="99">
                        <button type="button" class="qty-btn" onclick="changeQty('<?php echo $id; ?>', 1)"><i class="fa fa-plus"></i></button>
                    </div>
                    <div class="card-actions">
                        <a href="<?php echo $detail_link; ?>" class="btn-detail"><i class="fa fa-eye"></i> View Detail</a>
                        <a href="cart.php?remove=<?php echo urlencode($id); ?>" class="btn-remove"><i class="fa fa-trash"></i> Remove</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- RIGHT SIDE: ORDER SUMMARY -->
        <div class="summary-panel">
            <h2>Order Summary</h2>
            <div class="shipping-note">
                <i class="fa fa-truck"></i>
                <span>Free delivery on orders above Rs. 2,000</span>
            </div>
            <?php foreach($_SESSION['cart'] as $id => $item):
                $p = isset($item['price']) ? $item['price'] : 0;
                $q = isset($item['qty'])   ? $item['qty']   : 1;
                $n = isset($item['name'])  ? $item['name']  : 'Unknown';
            ?>
            <div class="summary-row">
                <span class="label"><?php echo htmlspecialchars($n); ?> ×<?php echo $q; ?></span>
                <span class="value">Rs. <?php echo number_format($p * $q); ?></span>
            </div>
            <?php endforeach; ?>
            <div class="summary-divider"></div>
            <div class="summary-row">
                <span class="label">Subtotal</span>
                <span class="value">Rs. <?php echo number_format($grand_total); ?></span>
            </div>
            <div class="summary-row">
                <span class="label">Delivery</span>
                <span class="value green">
                    <?php echo $grand_total >= 2000 ? 'Free' : 'Rs. 100'; ?>
                </span>
            </div>
            <div class="summary-divider"></div>
            <div class="summary-total">
                <span class="t-label">Grand Total</span>
                <span class="t-value">
                    Rs. <?php echo number_format($grand_total + ($grand_total >= 2000 ? 0 : 100)); ?>
                </span>
            </div>
            <a href="checkout.php" class="btn-checkout">
                <i class="fa fa-lock"></i> Proceed to Checkout
            </a>
            <button type="submit" name="update" class="btn-update">
                <i class="fa fa-rotate-right"></i> Update Cart
            </button>
            <a href="shop.php" class="continue-link">
                <i class="fa fa-arrow-left"></i> Continue Shopping
            </a>
        </div>

    </div>
    </form>

    <?php endif; ?>
</div>

<script>
function changeQty(id, delta) {
    let input = document.getElementById('qty_' + id);
    let val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > 99) val = 99;
    input.value = val;
}
</script>

</body>
</html>
<?php include "../includes/footer.php"; ?>