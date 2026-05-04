<?php
session_start();
include "../includes/header.php";
include "../databases/db.php";

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
<title>Your Cart — SportGhar</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
    max-width: 1100px;
    margin: 0 auto;
    padding: 40px 20px 80px;
}

/* ── HEADER ── */
.cart-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 36px;
    flex-wrap: wrap;
    gap: 12px;
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

/* ── LAYOUT ── */
.cart-layout {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 24px;
    align-items: start;
}
@media(max-width: 900px){
    .cart-layout { grid-template-columns: 1fr; }
}

/* ── CART ITEMS LIST ── */
.cart-items { display: flex; flex-direction: column; gap: 16px; }

/* ── SINGLE CART CARD ── */
.cart-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--card-r);
    overflow: hidden;
    display: grid;
    grid-template-columns: 160px 1fr;
    transition: border-color 0.25s, box-shadow 0.25s;
    animation: slideUp 0.35s ease both;
}
.cart-card:hover {
    border-color: rgba(249,115,22,0.25);
    box-shadow: 0 8px 32px rgba(0,0,0,0.4);
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
    min-height: 160px;
}
.card-image-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.4s ease;
}
.cart-card:hover .card-image-wrap img { transform: scale(1.06); }
.card-image-wrap .img-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to right, transparent 60%, var(--surface));
}

/* ── CARD BODY ── */
.card-body {
    padding: 22px 24px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 16px;
}

.product-name {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 22px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: var(--text);
    line-height: 1.2;
}

.product-price-row {
    display: flex;
    align-items: baseline;
    gap: 10px;
}
.unit-price {
    font-size: 14px;
    color: var(--muted);
}
.subtotal-price {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 24px;
    font-weight: 800;
    color: var(--accent);
}

/* ── QTY CONTROL ── */
.qty-control {
    display: flex;
    align-items: center;
    gap: 0;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    width: fit-content;
}
.qty-btn {
    width: 36px; height: 36px;
    background: transparent;
    border: none;
    color: var(--text);
    font-size: 16px;
    cursor: pointer;
    transition: background 0.2s;
    display: flex; align-items: center; justify-content: center;
}
.qty-btn:hover { background: rgba(255,255,255,0.08); }
.qty-input {
    width: 46px;
    text-align: center;
    background: transparent;
    border: none;
    border-left: 1px solid var(--border);
    border-right: 1px solid var(--border);
    color: var(--text);
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 18px;
    font-weight: 700;
    padding: 0;
    height: 36px;
    -moz-appearance: textfield;
}
.qty-input::-webkit-outer-spin-button,
.qty-input::-webkit-inner-spin-button { -webkit-appearance: none; }
.qty-input:focus { outline: none; }

/* ── CARD ACTIONS ── */
.card-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-detail {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.12);
    color: var(--text);
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: 0.2s;
    cursor: pointer;
}
.btn-detail:hover {
    background: rgba(255,255,255,0.1);
    border-color: rgba(255,255,255,0.2);
}

.btn-remove {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.2);
    color: var(--red);
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: 0.2s;
    cursor: pointer;
}
.btn-remove:hover {
    background: rgba(239,68,68,0.18);
    border-color: rgba(239,68,68,0.4);
}

/* ── ORDER SUMMARY PANEL ── */
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
.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 11px 0;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
}
.summary-row:last-of-type { border-bottom: none; }
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
    align-items: center;
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
    font-size: 34px;
    font-weight: 900;
    color: var(--accent);
    line-height: 1;
}

/* ── BIG CHECKOUT BUTTON ── */
.btn-checkout {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #ea6c0a, #f97316);
    color: #fff;
    border: none;
    border-radius: 12px;
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
.btn-checkout:active { transform: translateY(0); }

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
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    font-family: 'Barlow', sans-serif;
    margin-top: 10px;
    transition: 0.2s;
}
.btn-update:hover {
    background: rgba(255,255,255,0.09);
    color: var(--text);
}

.continue-link {
    display: block;
    text-align: center;
    margin-top: 14px;
    color: var(--muted);
    font-size: 13px;
    text-decoration: none;
    transition: color 0.2s;
}
.continue-link:hover { color: var(--accent); }
.continue-link i { margin-right: 5px; }

/* ── FREE SHIPPING NOTE ── */
.shipping-note {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(34,197,94,0.07);
    border: 1px solid rgba(34,197,94,0.2);
    border-radius: 10px;
    padding: 11px 14px;
    margin-bottom: 20px;
    font-size: 13px;
    color: var(--green);
}

/* ── EMPTY STATE ── */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 100px 20px;
}
.empty-icon {
    font-size: 72px;
    margin-bottom: 20px;
    opacity: 0.15;
}
.empty-state h2 {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 32px;
    font-weight: 900;
    text-transform: uppercase;
    margin-bottom: 10px;
    letter-spacing: 0.5px;
}
.empty-state p { color: var(--muted); margin-bottom: 28px; font-size: 15px; }
.btn-shop {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 32px;
    background: var(--accent);
    color: #fff;
    border-radius: 10px;
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 18px;
    font-weight: 800;
    text-transform: uppercase;
    text-decoration: none;
    letter-spacing: 0.5px;
    transition: 0.2s;
}
.btn-shop:hover { opacity: 0.9; }

@media(max-width: 600px){
    .cart-card { grid-template-columns: 120px 1fr; }
    .card-image-wrap { min-height: 130px; }
    .card-body { padding: 16px; }
    .product-name { font-size: 17px; }
}
</style>
</head>
<body>

<div class="cart-page">

    <!-- PAGE HEADER -->
    <div class="cart-header">
        <h1>Your <span>Cart</span></h1>
        <?php if($item_count > 0): ?>
        <span class="cart-badge">
            <i class="fa fa-shopping-bag"></i>
            <?php echo $item_count; ?> item<?php echo $item_count > 1 ? 's' : ''; ?>
        </span>
        <?php endif; ?>
    </div>

    <?php if(empty($_SESSION['cart'])): ?>

    <!-- EMPTY STATE -->
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

        <!-- LEFT: CART ITEMS -->
        <div class="cart-items">
            <?php
            $total = 0;
            foreach($_SESSION['cart'] as $id => $item):
                $price = isset($item['price']) ? $item['price'] : 0;
                $qty   = isset($item['qty'])   ? $item['qty']   : 1;
                $name  = isset($item['name'])  ? $item['name']  : 'Unknown Product';
                $image = isset($item['image']) ? $item['image'] : 'assets/no-image.png';
                $sub   = $price * $qty;
                $total += $sub;
                // product detail link — adjust if your URL structure differs
                $detail_link = "product.php?id=" . urlencode($id);
            ?>
            <div class="cart-card">

                <!-- PRODUCT IMAGE (bigger, left column) -->
                <div class="card-image-wrap">
                    <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($name); ?>">
                    <div class="img-overlay"></div>
                </div>

                <!-- CARD BODY -->
                <div class="card-body">

                    <div>
                        <div class="product-name"><?php echo htmlspecialchars($name); ?></div>
                        <div class="product-price-row">
                            <span class="unit-price">Rs. <?php echo number_format($price); ?> × <?php echo $qty; ?></span>
                            <span class="subtotal-price">Rs. <?php echo number_format($sub); ?></span>
                        </div>
                    </div>

                    <!-- QTY + ACTIONS ROW -->
                    <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
                        <!-- QTY STEPPER -->
                        <div class="qty-control">
                            <button type="button" class="qty-btn" onclick="changeQty('<?php echo $id; ?>', -1)">
                                <i class="fa fa-minus" style="font-size:11px;"></i>
                            </button>
                            <input
                                class="qty-input"
                                type="number"
                                id="qty_<?php echo $id; ?>"
                                name="qty[<?php echo $id; ?>]"
                                value="<?php echo $qty; ?>"
                                min="1"
                                max="99"
                            >
                            <button type="button" class="qty-btn" onclick="changeQty('<?php echo $id; ?>', 1)">
                                <i class="fa fa-plus" style="font-size:11px;"></i>
                            </button>
                        </div>
                    </div>

                    <!-- BUTTONS -->
                    <div class="card-actions">
                        <a href="<?php echo $detail_link; ?>" class="btn-detail">
                            <i class="fa fa-eye"></i> View Detail
                        </a>
                        <a href="cart.php?remove=<?php echo urlencode($id); ?>" class="btn-remove">
                            <i class="fa fa-trash"></i> Remove
                        </a>
                    </div>

                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- /cart-items -->

        <!-- RIGHT: ORDER SUMMARY -->
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
        <!-- /summary-panel -->

    </div>
    </form>

    <?php endif; ?>
</div>

<script>
function changeQty(id, delta) {
    var input = document.getElementById('qty_' + id);
    var val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    if (val > 99) val = 99;
    input.value = val;
    // Auto-submit on change for instant subtotal update
    // (comment out if you prefer manual Update Cart button only)
    // document.getElementById('cartForm').submit();
}
</script>

</body>
</html>
<?php include "../includes/footer.php"; ?>