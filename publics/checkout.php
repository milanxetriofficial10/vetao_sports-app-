<?php
if(session_status() === PHP_SESSION_NONE){ session_start(); }
require_once "../databases/db.php";
$conn = getDB();

$error = "";
$success = false;

// Redirect if cart is empty
if(empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

// ─────────────────────────────────────────────
// 🔹 Get shop name from the first product in cart (using jerseys table)
// ─────────────────────────────────────────────
$display_shop_name = "SportGhar";  // default fallback
foreach($_SESSION['cart'] as $cart_item) {
    $iname = $conn->real_escape_string($cart_item['name']);
    
    // Look up the jersey by title (size matching is optional, but we just need shop name)
    $lookup = $conn->query("
        SELECT j.id, j.title, s.shop_name 
        FROM jerseys j
        LEFT JOIN shops s ON j.shop_id = s.id
        WHERE j.title = '$iname'
        LIMIT 1
    ");
    if ($lookup && $lookup->num_rows > 0) {
        $prod = $lookup->fetch_assoc();
        if (!empty($prod['shop_name'])) {
            $display_shop_name = $prod['shop_name'];
            break;
        }
    }
}

// ─────────────────────────────────────────────
// 🔹 Top Picks (trending jerseys)
// ─────────────────────────────────────────────
$top_picks_data = [];
$top_q = $conn->query("SELECT * FROM jerseys WHERE is_top = 1 ORDER BY id DESC LIMIT 8");
while($tp = $top_q->fetch_assoc()) {
    $top_picks_data[] = $tp;
}

if(isset($_POST['place_order'])){
    $name    = $conn->real_escape_string(trim($_POST['name']));
    $phone   = $conn->real_escape_string(trim($_POST['phone']));
    $email   = $conn->real_escape_string(trim($_POST['email']));
    $area    = $conn->real_escape_string(trim($_POST['area'] ?? ''));
    $landmark= $conn->real_escape_string(trim($_POST['landmark'] ?? ''));
    $address = $conn->real_escape_string(trim($_POST['address']));
    $city    = $conn->real_escape_string(trim($_POST['city']));
    $district= $conn->real_escape_string(trim($_POST['district']));
    $province= $conn->real_escape_string(trim($_POST['province']));
    $payment = $conn->real_escape_string($_POST['payment']);

    $fullAddress = $address;
    if(!empty($area))     $fullAddress .= ", " . $area;
    if(!empty($landmark)) $fullAddress .= " (near " . $landmark . ")";

    if(!$name || !$phone || !$city || !$district || !$province || !$address){
        $error = "Please fill all required fields.";
    } else {

        $total = 0;
        foreach($_SESSION['cart'] as $item){
            $total += $item['price'] * $item['qty'];
        }

        $shipping_fee = $total > 2000 ? 0 : 120;
        $total += $shipping_fee;

        // eSewa flow
        if($payment === 'eSewa') {
            $conn->query("INSERT INTO orders (name, phone, email, address, city, district, province, payment_method, total, status, created_at)
                VALUES ('$name','$phone','$email','$fullAddress','$city','$district','$province','eSewa','$total','Pending_Payment', NOW())");
            $order_id = $conn->insert_id;

            foreach($_SESSION['cart'] as $item){
                $iname  = $conn->real_escape_string($item['name']);
                $iprice = $item['price'];
                $iqty   = $item['qty'];
                $isize  = $conn->real_escape_string(trim($item['size'] ?? ''));

                // Find product in jerseys by title
                $lookup = $conn->query("
                    SELECT id, seller_id, shop_id 
                    FROM jerseys 
                    WHERE title = '$iname'
                    LIMIT 1
                ");
                if ($lookup && $lookup->num_rows > 0) {
                    $prod = $lookup->fetch_assoc();
                    $product_id = (int)$prod['id'];
                    $seller_id  = (int)$prod['seller_id'];
                } else {
                    $product_id = 0;
                    $seller_id  = 0;
                }

                $stmt = $conn->prepare("
                    INSERT INTO order_items 
                    (order_id, jersey_name, size, price, qty, product_id, seller_id, seller_status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
                ");
                $stmt->bind_param("issdiii", $order_id, $iname, $isize, $iprice, $iqty, $product_id, $seller_id);
                $stmt->execute();
                $stmt->close();
            }

            $_SESSION['esewa_amount'] = $total;
            header("Location: esewa_payment.php?order_id=$order_id&amount=$total");
            exit;
        }

        // COD / Khalti / Mastercard / IME Pay / Bank Transfer
        $conn->query("INSERT INTO orders (name, phone, email, address, city, district, province, payment_method, total, status, created_at)
            VALUES ('$name','$phone','$email','$fullAddress','$city','$district','$province','$payment','$total','Pending', NOW())");
        $order_id = $conn->insert_id;

        foreach($_SESSION['cart'] as $item){
            $iname  = $conn->real_escape_string($item['name']);
            $iprice = $item['price'];
            $iqty   = $item['qty'];
            $isize  = $conn->real_escape_string(trim($item['size'] ?? ''));

            $lookup = $conn->query("
                SELECT id, seller_id, shop_id 
                FROM jerseys 
                WHERE title = '$iname'
                LIMIT 1
            ");
            if ($lookup && $lookup->num_rows > 0) {
                $prod = $lookup->fetch_assoc();
                $product_id = (int)$prod['id'];
                $seller_id  = (int)$prod['seller_id'];
            } else {
                $product_id = 0;
                $seller_id  = 0;
            }

            $stmt = $conn->prepare("
                INSERT INTO order_items 
                (order_id, jersey_name, size, price, qty, product_id, seller_id, seller_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
            ");
            $stmt->bind_param("issdiii", $order_id, $iname, $isize, $iprice, $iqty, $product_id, $seller_id);
            $stmt->execute();
            $stmt->close();
        }

        // Notification for logged-in user
        $user_id = $_SESSION['user']['id'] ?? null;
        if($user_id){
            $uid      = (int)$user_id;
            $pay_disp = $conn->real_escape_string($payment);
            $amt_disp = number_format($total);
            $notif_msg = $conn->real_escape_string(
                "Order #" . str_pad($order_id, 5, '0', STR_PAD_LEFT) .
                " confirmed! Rs. {$amt_disp} — {$pay_disp}. " .
                "Hami chado nai delivery ko lagi sampark garné chhau. Dhanyabad!"
            );
            $conn->query("INSERT INTO notifications (user_id, type, message, related_id, is_read, created_at)
                VALUES ($uid, 'confirmed', '$notif_msg', $order_id, 0, NOW())");
        }

        $_SESSION['cart'] = [];
        $_SESSION['last_order_id'] = $order_id;
        $success = true;
    }
}

// Calculate totals for display
$subtotal = 0;
foreach($_SESSION['cart'] as $item){
    $subtotal += $item['price'] * $item['qty'];
}
$shipping = $subtotal > 2000 ? 0 : 120;
$total    = $subtotal + $shipping;

include "../includes/header.php";
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout — <?php echo htmlspecialchars($display_shop_name); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ═══════════════════════════════════════════════
   RESET & BASE
═══════════════════════════════════════════════ */
*{ margin:0; padding:0; box-sizing:border-box; }

body{
    font-family:'Outfit',sans-serif;
    background:#c94b01;
    color:#fff1e6;
    min-height:100vh;
}

/* ═══════════════════════════════════════════════
   CHECKOUT NAVBAR (modified logo)
═══════════════════════════════════════════════ */
.co-nav{
    position:sticky;
    top:0;
    z-index:200;
    background:rgba(120,30,0,0.92);
    backdrop-filter:blur(18px);
    -webkit-backdrop-filter:blur(18px);
    border-bottom:1px solid rgba(255,255,255,.18);
    padding:0 32px;
    height:64px;
    display:flex;
    align-items:center;
    gap:0;
}
.co-nav-logo{
    font-size:22px;
    font-weight:900;
    color:#fff;
    text-decoration:none;
    display:flex;
    align-items:center;
    gap:9px;
    letter-spacing:-.3px;
}
.co-nav-logo i{ color:#fde68a; font-size:20px; }
.co-nav-logo .shop-name{
    background:rgba(255,255,255,0.15);
    padding:4px 12px;
    border-radius:40px;
    font-size:14px;
    font-weight:700;
    white-space:nowrap;
}

.co-nav-steps{
    display:flex;
    align-items:center;
    gap:0;
    margin:0 auto;
}
.co-step{
    display:flex;
    align-items:center;
    gap:8px;
    padding:0 6px;
    position:relative;
}
.co-step:not(:last-child)::after{
    content:'';
    display:block;
    width:44px;
    height:1.5px;
    background:rgba(255,255,255,.2);
    margin:0 6px;
}
.co-step.done:not(:last-child)::after{ background:#86efac; }
.co-step-num{
    width:30px; height:30px;
    border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:12px; font-weight:800;
    transition:.3s;
}
.co-step.done .co-step-num{ background:#22c55e; color:#fff; }
.co-step.active .co-step-num{ background:#fff; color:#c94b01; box-shadow:0 0 0 5px rgba(255,255,255,.2); }
.co-step.idle .co-step-num{ background:rgba(255,255,255,.15); color:rgba(255,255,255,.5); }
.co-step-label{ font-size:12px; font-weight:600; color:rgba(255,255,255,.6); }
.co-step.done .co-step-label{ color:#86efac; }
.co-step.active .co-step-label{ color:#fff; }

.co-nav-back{
    margin-left:auto;
    display:flex; align-items:center; gap:7px;
    color:rgba(255,255,255,.8); font-size:13px; font-weight:600;
    text-decoration:none;
    padding:8px 16px;
    border:1px solid rgba(255,255,255,.25);
    border-radius:30px;
    transition:.2s;
}
.co-nav-back:hover{ color:#fff; border-color:rgba(255,255,255,.6); background:rgba(255,255,255,.12); }

@media(max-width:700px){
    .co-nav{ padding:0 16px; }
    .co-step-label{ display:none; }
    .co-nav-back span{ display:none; }
}

/* ═══════════════════════════════════════════════
   TOP PICKS STRIP (fixed)
═══════════════════════════════════════════════ */
.top-strip{ padding:32px 32px 0; }
.top-strip-hd{
    display:flex; align-items:center; gap:12px; margin-bottom:18px;
}
.top-strip-hd h2{
    font-size:18px; font-weight:800; color:#fff;
    display:flex; align-items:center; gap:9px;
}
.top-strip-hd h2 i{ color:#fde68a; }
.top-strip-hd .ts-line{
    flex:1; height:1px;
    background:linear-gradient(90deg,rgba(255,255,255,.5),transparent);
}
.top-strip-hd a{
    font-size:12px; font-weight:700;
    color:#fff; text-decoration:none;
    padding:5px 14px;
    border:1px solid rgba(255,255,255,.4);
    border-radius:20px;
    transition:.2s;
    white-space:nowrap;
    background:rgba(255,255,255,.1);
}
.top-strip-hd a:hover{ background:rgba(255,255,255,.25); }

.top-scroll{
    display:flex; gap:16px; overflow-x:auto;
    padding-bottom:12px; scrollbar-width:none;
    -ms-overflow-style:none; scroll-behavior:smooth;
}
.top-scroll::-webkit-scrollbar{ display:none; }

.top-card{
    flex-shrink:0; width:175px;
    background:rgba(0,0,0,0.25);
    border:1px solid rgba(255,255,255,.25);
    border-radius:16px; overflow:hidden;
    transition:.3s; text-decoration:none;
    display:block; position:relative;
    backdrop-filter:blur(6px);
}
.top-card:hover{
    transform:translateY(-6px);
    border-color:rgba(255,255,255,.55);
    box-shadow:0 14px 32px rgba(0,0,0,.35);
}
.top-card-img{
    width:100%; height:130px;
    object-fit:cover; display:block; transition:.4s;
}
.top-card:hover .top-card-img{ transform:scale(1.07); }
.top-card-badge{
    position:absolute; top:8px; left:8px;
    background:rgba(255,255,255,0.9);
    color:#c94b01; font-size:9px; font-weight:800;
    padding:3px 8px; border-radius:12px; letter-spacing:.8px;
}
.top-card-body{ padding:10px 11px 12px; }
.top-card-title{
    font-size:12.5px; font-weight:700; color:#fff;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:4px;
}
.top-card-price{ font-size:13px; font-weight:800; color:#d1fae5; }
.top-card-price small{ font-size:10px; color:rgba(255,255,255,.5); text-decoration:line-through; margin-right:3px; }

/* ═══════════════════════════════════════════════
   MAIN LAYOUT (unchanged)
═══════════════════════════════════════════════ */
.page{
    max-width:1180px;
    margin:28px auto 80px;
    padding:0 32px;
    display:grid;
    grid-template-columns:1fr 390px;
    gap:26px;
    align-items:start;
}
@media(max-width:900px){
    .page{ grid-template-columns:1fr; padding:0 16px; }
    .top-strip{ padding:24px 16px 0; }
}

@keyframes fadeUp{
    from{ opacity:0; transform:translateY(28px); }
    to{ opacity:1; transform:translateY(0); }
}
.anim{ animation:fadeUp .5s cubic-bezier(.2,.9,.4,1.1) both; }
.anim-1{ animation-delay:.04s; }
.anim-2{ animation-delay:.1s; }
.anim-3{ animation-delay:.16s; }
.anim-4{ animation-delay:.06s; }
.anim-5{ animation-delay:.14s; }
.anim-6{ animation-delay:.22s; }

/* CARD SHELL (unchanged) */
.ck-card{
    background:rgba(0,0,0,0.22);
    border:1px solid rgba(255,255,255,.18);
    border-radius:22px;
    overflow:hidden;
    transition:border-color .3s, box-shadow .3s;
    backdrop-filter:blur(8px);
}
.ck-card:hover{
    border-color:rgba(255,255,255,.4);
    box-shadow:0 8px 32px rgba(0,0,0,.2);
}
.ck-card-head{
    padding:18px 24px 15px;
    display:flex; align-items:center; gap:14px;
    border-bottom:1px solid rgba(255,255,255,.1);
    background:rgba(255,255,255,.05);
}
.ck-head-icon{
    width:42px; height:42px;
    border-radius:14px;
    display:flex; align-items:center; justify-content:center;
    font-size:17px;
    background:rgba(255,255,255,.15);
    color:#fff;
    transition:.3s;
    flex-shrink:0;
}
.ck-card:hover .ck-head-icon{ background:#fff; color:#c94b01; }
.ck-head-title{
    font-size:17px; font-weight:800; color:#fff;
    flex:1;
}
.ck-head-badge{
    font-size:11px; font-weight:700;
    color:#fff;
    background:rgba(255,255,255,.18);
    border:1px solid rgba(255,255,255,.3);
    padding:4px 12px; border-radius:30px;
}
.ck-card-body{ padding:24px; }

/* FORM FIELDS (unchanged) */
.fg{ display:grid; gap:16px; }
.fg.c2{ grid-template-columns:1fr 1fr; }
.fg.c3{ grid-template-columns:1fr 1fr 1fr; }
@media(max-width:560px){
    .fg.c2,.fg.c3{ grid-template-columns:1fr; }
}
.fld{ display:flex; flex-direction:column; gap:7px; }
.fld-lbl{
    font-size:11px; font-weight:700;
    color:rgba(255,255,255,.65);
    text-transform:uppercase;
    letter-spacing:.7px;
    display:flex; align-items:center; gap:6px;
    transition:color .2s;
}
.fld:focus-within .fld-lbl{ color:#fff; }
.fld-lbl .req{ color:#fca5a5; font-size:13px; }
.inp-wrap{
    position:relative;
    display:flex; align-items:center;
}
.inp-wrap .ico{
    position:absolute; left:14px;
    color:rgba(255,255,255,.4);
    font-size:13px; pointer-events:none;
    transition:.2s; z-index:1;
}
.inp-wrap:focus-within .ico{ color:#fff; }
.fld input,
.fld select,
.fld textarea{
    width:100%;
    padding:13px 14px 13px 40px;
    background:rgba(255,255,255,.1);
    border:1.5px solid rgba(255,255,255,.25);
    border-radius:12px;
    color:#fff;
    font-size:14px;
    font-family:'Outfit',sans-serif;
    outline:none;
    transition:.25s;
}
.fld input::placeholder,
.fld textarea::placeholder{ color:rgba(255,255,255,.4); }
.fld input:hover,
.fld select:hover,
.fld textarea:hover{
    border-color:rgba(255,255,255,.45);
    background:rgba(255,255,255,.15);
}
.fld input:focus,
.fld select:focus,
.fld textarea:focus{
    border-color:#fff;
    background:rgba(255,255,255,.18);
    box-shadow:0 0 0 4px rgba(255,255,255,.12);
}
.fld input.err,
.fld select.err,
.fld textarea.err{
    border-color:#fca5a5;
    background:rgba(239,68,68,.15);
}
.fld select{
    cursor:pointer;
    appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23ffffff' d='M6 8L0 0h12z'/%3E%3C/svg%3E");
    background-repeat:no-repeat;
    background-position:right 14px center;
    padding-right:36px;
}
.fld select option{ background:#7a1e00; color:#fff; }
.fld textarea{ resize:vertical; min-height:80px; line-height:1.55; padding-top:13px; }
.fld .check-ok{
    position:absolute; right:13px;
    color:#86efac; font-size:14px;
    opacity:0; transition:.2s; pointer-events:none;
}
.fld.valid .check-ok{ opacity:1; }
.sec-div{
    display:flex; align-items:center; gap:10px;
    margin:20px 0 14px;
}
.sec-div span{
    font-size:11px; font-weight:700; color:rgba(255,255,255,.6);
    text-transform:uppercase; letter-spacing:.8px;
    white-space:nowrap; padding:0 8px;
}
.sec-div::before,.sec-div::after{
    content:''; flex:1; height:1px;
    background:rgba(255,255,255,.15);
}
.pay-grid{
    display:grid; grid-template-columns:1fr 1fr; gap:12px;
}
@media(max-width:480px){ .pay-grid{ grid-template-columns:1fr; } }
.pay-opt{
    position:relative;
    border:1.5px solid rgba(255,255,255,.2);
    border-radius:16px; padding:14px 15px;
    cursor:pointer;
    display:flex; align-items:center; gap:13px;
    background:rgba(255,255,255,.08);
    transition:.28s; user-select:none;
}
.pay-opt:hover{
    border-color:rgba(255,255,255,.5);
    background:rgba(255,255,255,.15);
    transform:translateY(-2px);
}
.pay-opt.sel{
    border-color:#fff;
    background:rgba(255,255,255,.2);
    box-shadow:0 0 0 3px rgba(255,255,255,.15), 0 10px 26px rgba(0,0,0,.2);
}
.pay-tick{
    position:absolute; top:9px; right:10px;
    width:18px; height:18px;
    border-radius:50%;
    background:#fff; color:#c94b01;
    display:flex; align-items:center; justify-content:center;
    font-size:9px; font-weight:900;
    opacity:0; transform:scale(0);
    transition:.28s cubic-bezier(.2,.9,.4,1.4);
}
.pay-opt.sel .pay-tick{ opacity:1; transform:scale(1); }
.pay-logo{
    width:50px; height:50px;
    border-radius:14px;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0; overflow:hidden;
    font-size:11px; font-weight:900; letter-spacing:-.2px;
}
.pay-cod-logo{ background:linear-gradient(135deg,#92400e,#78350f); color:#fbbf24; font-size:20px; }
.pay-esewa-logo{ background:#60bb46; color:#fff; font-size:13px; font-weight:900; flex-direction:column; line-height:1; gap:1px; }
.pay-khalti-logo{ background:linear-gradient(135deg,#5c2d91,#7b3fe4); color:#fff; font-size:12px; font-weight:900; }
.pay-mc-logo{ background:#252525; position:relative; overflow:visible; }
.pay-ime-logo{ background:linear-gradient(135deg,#c0392b,#e74c3c); color:#fff; font-size:10px; font-weight:900; }
.pay-bank-logo{ background:linear-gradient(135deg,#1e3a5f,#2563eb); color:#fff; font-size:19px; }
.pay-info{ flex:1; min-width:0; }
.pay-name{
    font-size:14px; font-weight:800; color:#fff;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.pay-sub{ font-size:11px; color:rgba(255,255,255,.55); margin-top:3px; }
.mc-circles{ display:flex; align-items:center; }
.mc-c{ width:28px; height:28px; border-radius:50%; opacity:.9; }
.mc-c1{ background:#eb001b; margin-right:-10px; z-index:1; }
.mc-c2{ background:#f79e1b; z-index:0; }
.err-alert{
    background:rgba(239,68,68,.2);
    border:1px solid rgba(239,68,68,.4);
    border-left:4px solid #ef4444;
    padding:14px 16px; border-radius:12px;
    margin-bottom:18px;
    display:flex; align-items:center; gap:10px;
    font-size:13.5px; color:#fca5a5;
}
.order-list{
    display:flex; flex-direction:column; gap:10px;
    margin-bottom:18px; max-height:310px;
    overflow-y:auto; padding-right:4px;
}
.order-list::-webkit-scrollbar{ width:4px; }
.order-list::-webkit-scrollbar-track{ background:rgba(255,255,255,.06); border-radius:4px; }
.order-list::-webkit-scrollbar-thumb{ background:rgba(255,255,255,.4); border-radius:4px; }
.o-item{
    display:flex; align-items:center; gap:12px;
    padding:10px 12px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.12);
    border-radius:14px; transition:.2s;
}
.o-item:hover{ background:rgba(255,255,255,.15); border-color:rgba(255,255,255,.3); transform:translateX(4px); }
.o-item img{
    width:56px; height:56px; object-fit:cover;
    border-radius:12px; border:1px solid rgba(255,255,255,.2); flex-shrink:0;
}
.o-item-info{ flex:1; min-width:0; }
.o-item-name{
    font-size:13.5px; font-weight:700; color:#fff;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.o-item-meta{ font-size:11px; color:rgba(255,255,255,.55); margin-top:3px; }
.o-item-price{ font-size:14px; font-weight:800; color:#d1fae5; flex-shrink:0; }
.promo-row{ display:flex; gap:9px; margin:14px 0 6px; }
.promo-inp{ flex:1; position:relative; }
.promo-inp i{
    position:absolute; left:13px; top:50%;
    transform:translateY(-50%); color:rgba(255,255,255,.4); font-size:12px;
}
.promo-inp input{
    width:100%; padding:11px 12px 11px 34px;
    background:rgba(255,255,255,.1);
    border:1.5px solid rgba(255,255,255,.25);
    border-radius:11px; color:#fff;
    font-family:'Outfit',sans-serif; font-size:13px;
    outline:none; transition:.2s;
}
.promo-inp input::placeholder{ color:rgba(255,255,255,.4); }
.promo-inp input:focus{ border-color:#fff; box-shadow:0 0 0 3px rgba(255,255,255,.12); }
.promo-btn{
    padding:11px 20px;
    background:rgba(255,255,255,.18);
    border:1px solid rgba(255,255,255,.4);
    color:#fff; font-weight:800; font-size:13px;
    font-family:'Outfit',sans-serif;
    border-radius:11px; cursor:pointer; transition:.2s; white-space:nowrap;
}
.promo-btn:hover{ background:rgba(255,255,255,.3); }
.promo-msg{ font-size:12px; display:flex; align-items:center; gap:5px; margin-top:5px; }
.promo-msg.ok{ color:#86efac; }
.promo-msg.no{ color:#fca5a5; }
.dash-sep{ border:none; border-top:1.5px dashed rgba(255,255,255,.2); margin:16px 0; }
.tot-row{
    display:flex; justify-content:space-between;
    font-size:13.5px; color:rgba(255,255,255,.7); padding:5px 0;
}
.tot-row.grand{
    font-size:21px; font-weight:900; color:#fff;
    border-top:1px solid rgba(255,255,255,.2);
    padding-top:14px; margin-top:10px;
}
.free-badge{
    background:rgba(34,197,94,.25);
    color:#86efac; border:1px solid rgba(34,197,94,.4);
    padding:3px 10px; border-radius:20px;
    font-size:11px; font-weight:700;
}
.free-hint{
    background:rgba(34,197,94,.12);
    border:1px solid rgba(34,197,94,.25);
    color:#86efac;
    padding:8px 12px; border-radius:12px;
    font-size:12px; display:flex; align-items:center; gap:8px; margin:7px 0;
}
.trust-row{
    display:grid; grid-template-columns:repeat(3,1fr);
    gap:10px; margin:12px 0;
}
.trust-it{
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.15);
    border-radius:14px; padding:12px 6px;
    text-align:center; transition:.25s;
}
.trust-it:hover{ border-color:rgba(255,255,255,.4); background:rgba(255,255,255,.18); transform:translateY(-3px); }
.trust-it i{ font-size:19px; color:#fde68a; margin-bottom:6px; display:block; }
.trust-it span{ font-size:10.5px; font-weight:700; color:rgba(255,255,255,.7); }
.place-btn{
    width:100%; padding:17px;
    background:#fff;
    color:#c94b01; border:none; border-radius:16px;
    font-size:16px; font-weight:900;
    font-family:'Outfit',sans-serif;
    cursor:pointer; transition:.3s;
    box-shadow:0 10px 28px rgba(0,0,0,.25);
    display:flex; align-items:center; justify-content:center; gap:10px;
    letter-spacing:.2px;
}
.place-btn:hover{ transform:translateY(-4px); box-shadow:0 18px 36px rgba(0,0,0,.35); background:#fff5f0; }
.place-btn:active{ transform:translateY(1px); }
.secure-note{
    text-align:center; font-size:11.5px; color:rgba(255,255,255,.55);
    margin-top:14px;
    display:flex; align-items:center; justify-content:center; gap:6px;
}
.suc-wrap{
    max-width:580px; margin:60px auto;
    padding:0 20px; text-align:center;
}
.suc-ring{
    width:110px; height:110px;
    background:linear-gradient(135deg,#16a34a,#22c55e);
    border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:46px; color:#fff;
    margin:0 auto 28px;
    box-shadow:0 0 0 16px rgba(34,197,94,.2);
    animation:popIn .6s cubic-bezier(.2,.9,.4,1.4);
}
@keyframes popIn{
    from{ transform:scale(0) rotate(-12deg); opacity:0; }
    to{ transform:scale(1) rotate(0); opacity:1; }
}
.suc-card{
    background:rgba(0,0,0,0.25);
    border:1px solid rgba(255,255,255,.25);
    border-radius:24px; padding:36px 30px;
    box-shadow:0 20px 60px rgba(0,0,0,.3);
    backdrop-filter:blur(10px);
}
.suc-card h2{ font-size:24px; font-weight:900; color:#fff; margin-bottom:10px; }
.suc-card p{ font-size:14px; color:rgba(255,255,255,.7); line-height:1.7; }
.order-num-box{
    background:rgba(255,255,255,.1);
    border:1px solid rgba(255,255,255,.25);
    padding:18px; border-radius:16px; margin:22px 0;
}
.order-num-box small{ display:block; font-size:11px; color:rgba(255,255,255,.6); margin-bottom:5px; letter-spacing:.8px; text-transform:uppercase; }
.order-num-box strong{ font-size:30px; font-weight:900; color:#fde68a; letter-spacing:-1px; }
.track{
    display:flex; justify-content:space-between;
    margin:26px 0; position:relative;
}
.track::before{
    content:''; position:absolute;
    top:16px; left:12%; right:12%;
    height:2px; background:rgba(255,255,255,.15); z-index:0;
}
.t-step{
    flex:1; display:flex; flex-direction:column; align-items:center;
    gap:8px; z-index:1; font-size:11px; font-weight:700; color:rgba(255,255,255,.5);
}
.t-dot{
    width:34px; height:34px;
    background:rgba(255,255,255,.1);
    border:1.5px solid rgba(255,255,255,.2);
    border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    color:rgba(255,255,255,.5);
}
.t-step.done .t-dot{ background:#22c55e; border-color:#22c55e; color:#fff; box-shadow:0 0 0 6px rgba(34,197,94,.2); }
.t-step.done{ color:#86efac; }
.cont-btn{
    display:inline-flex; align-items:center; gap:10px;
    background:#fff; color:#c94b01;
    padding:14px 36px; border-radius:50px;
    font-weight:800; font-size:15px;
    text-decoration:none; transition:.3s;
    box-shadow:0 8px 24px rgba(0,0,0,.2);
}
.cont-btn:hover{ transform:translateY(-3px); box-shadow:0 14px 32px rgba(0,0,0,.3); background:#fff5f0; }
.loc-pill{
    display:inline-flex; align-items:center; gap:7px;
    background:rgba(255,255,255,.15);
    border:1px solid rgba(255,255,255,.3);
    color:#fff; font-size:12px; font-weight:700;
    padding:6px 16px; border-radius:30px; margin-bottom:16px;
}
</style>
</head>
<body>

<br><br><br>

<!-- ═══ NAVBAR ═══ (logo replaced with dynamic shop name) -->
<nav class="co-nav">
    <a href="../publics/index.php" class="co-nav-logo">
        <i class="fa fa-store"></i>
        <span class="shop-name"><?php echo htmlspecialchars($display_shop_name); ?></span>
    </a>
    <div class="co-nav-steps">
        <div class="co-step done">
            <div class="co-step-num"><i class="fa fa-check" style="font-size:11px;"></i></div>
            <div class="co-step-label">Cart</div>
        </div>
        <div class="co-step active">
            <div class="co-step-num">2</div>
            <div class="co-step-label">Checkout</div>
        </div>
        <div class="co-step idle">
            <div class="co-step-num">3</div>
            <div class="co-step-label">Confirm</div>
        </div>
    </div>
    <a href="cart.php" class="co-nav-back">
        <i class="fa fa-arrow-left"></i>
        <span>Back to Cart</span>
    </a>
</nav>


<?php if ($success): ?>
<!-- ═══ SUCCESS ═══ -->
<div class="suc-wrap">
    <div class="suc-ring"><i class="fa fa-check"></i></div>
    <div class="suc-card">
        <h2>🎉 Order Confirmed!</h2>
        <p>Tapai ko order successfully place bhayo.<br>Hami chitai delivery ko lagi sampark garné chhau.</p>
        <div class="order-num-box">
            <small>Order ID</small>
            <strong>#<?php echo str_pad($_SESSION['last_order_id'], 5, '0', STR_PAD_LEFT); ?></strong>
        </div>
        <div class="track">
            <div class="t-step done"><div class="t-dot"><i class="fa fa-check" style="font-size:12px;"></i></div><span>Ordered</span></div>
            <div class="t-step"><div class="t-dot"><i class="fa fa-box" style="font-size:12px;"></i></div><span>Packing</span></div>
            <div class="t-step"><div class="t-dot"><i class="fa fa-truck" style="font-size:12px;"></i></div><span>Shipped</span></div>
            <div class="t-step"><div class="t-dot"><i class="fa fa-house" style="font-size:12px;"></i></div><span>Delivered</span></div>
        </div>
        <a href="../publics/index.php" class="cont-btn"><i class="fa fa-store"></i> Continue Shopping</a>
    </div>
</div>

<?php else: ?>

<!-- ═══ CHECKOUT FORM ═══ -->
<form method="POST" id="checkoutForm">
<div class="page">

    <!-- ─── LEFT COLUMN ─── -->
    <div style="display:flex;flex-direction:column;gap:22px;">

        <!-- CONTACT -->
        <div class="ck-card anim anim-1">
            <div class="ck-card-head">
                <div class="ck-head-icon"><i class="fa fa-user"></i></div>
                <div class="ck-head-title">Contact Info</div>
                <div class="ck-head-badge">Step 1/3</div>
            </div>
            <div class="ck-card-body">
                <?php if($error): ?>
                <div class="err-alert"><i class="fa fa-triangle-exclamation"></i><?php echo $error; ?></div>
                <?php endif; ?>
                <div class="fg c2">
                    <div class="fld" id="fld-name">
                        <label class="fld-lbl"><i class="fa fa-user" style="font-size:10px;"></i> Full Name <span class="req">*</span></label>
                        <div class="inp-wrap">
                            <i class="ico fa fa-user"></i>
                            <input type="text" name="name" placeholder="Hari Thapa"
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                   oninput="liveValid(this,'fld-name')">
                            <i class="check-ok fa fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="fld" id="fld-phone">
                        <label class="fld-lbl"><i class="fa fa-phone" style="font-size:10px;"></i> Phone <span class="req">*</span></label>
                        <div class="inp-wrap">
                            <i class="ico fa fa-phone-alt"></i>
                            <input type="tel" name="phone" placeholder="98XXXXXXXX"
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                   oninput="liveValid(this,'fld-phone')">
                            <i class="check-ok fa fa-check-circle"></i>
                        </div>
                    </div>
                </div>
                <div class="fg" style="margin-top:16px;">
                    <div class="fld">
                        <label class="fld-lbl"><i class="fa fa-envelope" style="font-size:10px;"></i> Email <span style="color:rgba(255,255,255,.45);font-weight:500;text-transform:none;letter-spacing:0;">(optional)</span></label>
                        <div class="inp-wrap">
                            <i class="ico fa fa-envelope"></i>
                            <input type="email" name="email" placeholder="hello@example.com"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- DELIVERY ADDRESS -->
        <div class="ck-card anim anim-2">
            <div class="ck-card-head">
                <div class="ck-head-icon"><i class="fa fa-map-location-dot"></i></div>
                <div class="ck-head-title">Delivery Address</div>
                <div class="ck-head-badge">Step 2/3</div>
            </div>
            <div class="ck-card-body">
                <div class="loc-pill"><i class="fa fa-flag-checkered"></i> Nepal — Smart Selector</div>

                <div class="fld" id="fld-province">
                    <label class="fld-lbl"><i class="fa fa-map" style="font-size:10px;"></i> Province <span class="req">*</span></label>
                    <div class="inp-wrap">
                        <i class="ico fa fa-map"></i>
                        <select name="province" id="province" onchange="loadDistricts()">
                            <option value="">— Select Province —</option>
                            <option value="Koshi">Koshi Province</option>
                            <option value="Madhesh">Madhesh Province</option>
                            <option value="Bagmati">Bagmati Province</option>
                            <option value="Gandaki">Gandaki Province</option>
                            <option value="Lumbini">Lumbini Province</option>
                            <option value="Karnali">Karnali Province</option>
                            <option value="Sudurpashchim">Sudurpashchim Province</option>
                        </select>
                    </div>
                </div>

                <div class="sec-div"><span>📍 Location Details</span></div>

                <div class="fg c2">
                    <div class="fld" id="fld-district">
                        <label class="fld-lbl"><i class="fa fa-compass" style="font-size:10px;"></i> District <span class="req">*</span></label>
                        <div class="inp-wrap">
                            <i class="ico fa fa-compass"></i>
                            <select name="district" id="district" onchange="loadCities()" disabled>
                                <option value="">— Province first —</option>
                            </select>
                        </div>
                    </div>
                    <div class="fld" id="fld-city">
                        <label class="fld-lbl"><i class="fa fa-city" style="font-size:10px;"></i> City / Municipality <span class="req">*</span></label>
                        <div class="inp-wrap">
                            <i class="ico fa fa-city"></i>
                            <select name="city" id="city" disabled>
                                <option value="">— District first —</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="fg c2" style="margin-top:16px;">
                    <div class="fld">
                        <label class="fld-lbl"><i class="fa fa-location-dot" style="font-size:10px;"></i> Area / Tole</label>
                        <div class="inp-wrap">
                            <i class="ico fa fa-location-dot"></i>
                            <input type="text" name="area" placeholder="e.g. Thamel, Baneshwor"
                                   value="<?php echo htmlspecialchars($_POST['area'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="fld">
                        <label class="fld-lbl"><i class="fa fa-building" style="font-size:10px;"></i> Landmark</label>
                        <div class="inp-wrap">
                            <i class="ico fa fa-building"></i>
                            <input type="text" name="landmark" placeholder="Near temple / school"
                                   value="<?php echo htmlspecialchars($_POST['landmark'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="fg" style="margin-top:16px;">
                    <div class="fld" id="fld-address">
                        <label class="fld-lbl"><i class="fa fa-address-card" style="font-size:10px;"></i> Full Address <span class="req">*</span></label>
                        <div class="inp-wrap" style="align-items:flex-start;">
                            <i class="ico fa fa-address-card" style="top:14px;transform:none;"></i>
                            <textarea name="address" rows="2" placeholder="Street, house number, ward no."><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PAYMENT -->
        <div class="ck-card anim anim-3">
            <div class="ck-card-head">
                <div class="ck-head-icon"><i class="fa fa-credit-card"></i></div>
                <div class="ck-head-title">Payment Method</div>
                <div class="ck-head-badge">Step 3/3</div>
            </div>
            <div class="ck-card-body">
                <div class="pay-grid">
                    <div class="pay-opt sel pay-cod" onclick="selPay(this,'Cash on Delivery')">
                        <div class="pay-tick"><i class="fa fa-check" style="font-size:8px;"></i></div>
                        <div class="pay-logo pay-cod-logo"><i class="fa fa-money-bill-wave"></i></div>
                        <div class="pay-info"><div class="pay-name">Cash on Delivery</div><div class="pay-sub">Pay when received</div></div>
                    </div>
                    <div class="pay-opt pay-esewa" onclick="selPay(this,'eSewa')">
                        <div class="pay-tick"><i class="fa fa-check" style="font-size:8px;"></i></div>
                        <div class="pay-logo pay-esewa-logo"><svg width="38" height="38" viewBox="0 0 38 38" fill="none"><rect width="38" height="38" rx="10" fill="#60bb46"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" fill="white" font-size="8.5" font-weight="900" font-family="Outfit,sans-serif">eSewa</text></svg></div>
                        <div class="pay-info"><div class="pay-name">eSewa</div><div class="pay-sub">Digital wallet</div></div>
                    </div>
                    <div class="pay-opt pay-khalti" onclick="selPay(this,'Khalti')">
                        <div class="pay-tick"><i class="fa fa-check" style="font-size:8px;"></i></div>
                        <div class="pay-logo pay-khalti-logo"><svg width="38" height="38" viewBox="0 0 38 38" fill="none"><rect width="38" height="38" rx="10" fill="#5c2d91"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" fill="white" font-size="8" font-weight="900" font-family="Outfit,sans-serif">Khalti</text></svg></div>
                        <div class="pay-info"><div class="pay-name">Khalti</div><div class="pay-sub">Mobile payment</div></div>
                    </div>
                    <div class="pay-opt pay-mc" onclick="selPay(this,'Mastercard')">
                        <div class="pay-tick"><i class="fa fa-check" style="font-size:8px;"></i></div>
                        <div class="pay-logo pay-mc-logo" style="background:#1a1a1a;"><div class="mc-circles"><div class="mc-c mc-c1"></div><div class="mc-c mc-c2"></div></div></div>
                        <div class="pay-info"><div class="pay-name">Mastercard</div><div class="pay-sub">Debit / Credit</div></div>
                    </div>
                    <div class="pay-opt pay-ime" onclick="selPay(this,'IME Pay')">
                        <div class="pay-tick"><i class="fa fa-check" style="font-size:8px;"></i></div>
                        <div class="pay-logo pay-ime-logo"><svg width="38" height="38" viewBox="0 0 38 38" fill="none"><rect width="38" height="38" rx="10" fill="#c0392b"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" fill="white" font-size="7.5" font-weight="900" font-family="Outfit,sans-serif">IME Pay</text></svg></div>
                        <div class="pay-info"><div class="pay-name">IME Pay</div><div class="pay-sub">Fast payment</div></div>
                    </div>
                    <div class="pay-opt pay-bank" onclick="selPay(this,'Bank Transfer')">
                        <div class="pay-tick"><i class="fa fa-check" style="font-size:8px;"></i></div>
                        <div class="pay-logo pay-bank-logo"><i class="fa fa-building-columns"></i></div>
                        <div class="pay-info"><div class="pay-name">Bank Transfer</div><div class="pay-sub">Direct transfer</div></div>
                    </div>
                </div>
                <input type="hidden" name="payment" id="payInput" value="Cash on Delivery">
            </div>
        </div>

    </div><!-- end left col -->

    <!-- ─── RIGHT COLUMN ─── -->
    <div style="display:flex;flex-direction:column;gap:18px;position:sticky;top:80px;">

        <!-- ORDER SUMMARY -->
        <div class="ck-card anim anim-4">
            <div class="ck-card-head">
                <div class="ck-head-icon"><i class="fa fa-bag-shopping"></i></div>
                <div class="ck-head-title">Your Order</div>
                <div class="ck-head-badge"><?php echo count($_SESSION['cart']); ?> items</div>
            </div>
            <div class="ck-card-body">
                <div class="order-list">
                    <?php foreach($_SESSION['cart'] as $item): ?>
                    <div class="o-item">
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt=""
                             onerror="this.src='https://via.placeholder.com/56x56/7a1e00/ffffff?text=?'">
                        <div class="o-item-info">
                            <div class="o-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="o-item-meta">Size: <?php echo $item['size']; ?> · Qty: <?php echo $item['qty']; ?></div>
                        </div>
                        <div class="o-item-price">Rs. <?php echo number_format($item['price'] * $item['qty']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="promo-row">
                    <div class="promo-inp">
                        <i class="fa fa-tag"></i>
                        <input type="text" id="promoInp" placeholder="Promo code e.g. SPORT10">
                    </div>
                    <button type="button" class="promo-btn" onclick="applyPromo()">Apply</button>
                </div>
                <div class="promo-msg" id="promoMsg"></div>

                <hr class="dash-sep">
                <div class="tot-row"><span>Subtotal</span><span>Rs. <?php echo number_format($subtotal); ?></span></div>
                <div class="tot-row">
                    <span>Shipping</span>
                    <?php if($shipping == 0): ?>
                        <span class="free-badge"><i class="fa fa-check"></i> FREE</span>
                    <?php else: ?>
                        <span>Rs. <?php echo number_format($shipping); ?></span>
                    <?php endif; ?>
                </div>
                <?php if($subtotal < 2000): ?>
                <div class="free-hint"><i class="fa fa-truck-fast"></i> Rs. <?php echo number_format(2000 - $subtotal); ?> more for FREE shipping!</div>
                <?php endif; ?>
                <div class="tot-row grand">
                    <span>Total</span>
                    <span id="grandTotal">Rs. <?php echo number_format($total); ?></span>
                </div>
            </div>
        </div>

        <div class="trust-row anim anim-5">
            <div class="trust-it"><i class="fa fa-shield-halved"></i><span>Secure</span></div>
            <div class="trust-it"><i class="fa fa-rotate-left"></i><span>Returns</span></div>
            <div class="trust-it"><i class="fa fa-truck-fast"></i><span>Express</span></div>
        </div>

        <div class="anim anim-6">
            <button type="submit" name="place_order" class="place-btn" id="placeBtn">
                <i class="fa fa-lock"></i>
                Place Order · Rs. <?php echo number_format($total); ?>
            </button>
            <div class="secure-note">
                <i class="fa fa-shield-halved"></i>
                256-bit SSL encrypted — safe & secure
            </div>
        </div>

    </div><!-- end right col -->

</div>
</form>

<?php endif; ?>

<!-- ═══ TOP PICKS STRIP (now populated) ═══ -->
<?php if(!empty($top_picks_data)): ?>
<div class="top-strip">
    <div class="top-strip-hd">
        <h2><i class="fa fa-star"></i> ⭐ Top Picks</h2>
        <div class="ts-line"></div>
        <a href="../publics/index.php">View All <i class="fa fa-arrow-right" style="font-size:10px;"></i></a>
    </div>
    <div class="top-scroll" id="topScroll">
        <?php foreach($top_picks_data as $tp):
            $has_d = !empty($tp['discount']) && $tp['discount'] > 0;
            $orig  = $has_d ? round($tp['price'] / (1 - $tp['discount']/100)) : 0;
        ?>
        <a class="top-card" href="jersey_details.php?id=<?php echo $tp['id']; ?>">
            <div class="top-card-badge">⭐ TOP</div>
            <img class="top-card-img" src="<?php echo htmlspecialchars($tp['image']); ?>"
                 alt="<?php echo htmlspecialchars($tp['title']); ?>"
                 onerror="this.src='https://via.placeholder.com/175x130/7a1e00/ffffff?text=No+Img'">
            <div class="top-card-body">
                <div class="top-card-title"><?php echo htmlspecialchars($tp['title']); ?></div>
                <div class="top-card-price">
                    <?php if($has_d): ?><small>Rs.<?php echo number_format($orig); ?></small><?php endif; ?>
                    Rs. <?php echo number_format($tp['price']); ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
const nData = {
  Koshi:{Bhojpur:["Bhojpur","Shadananda"],Dhankuta:["Dhankuta","Pakhribas"],Ilam:["Ilam","Suryodaya"],Jhapa:["Birtamod","Mechinagar","Bhadrapur"],Khotang:["Diktel","Halesi"],Morang:["Biratnagar","Rangeli","Sundarharaicha"],Okhaldhunga:["Siddhicharan","Molung"],Panchthar:["Phidim","Hilihang"],Sankhuwasabha:["Chainpur","Dharmadevi"],Solukhumbu:["Solududhkunda","Dudhkoshi"],Sunsari:["Dharan","Inaruwa","Itahari"],Taplejung:["Phungling","Sirijangha"],Terhathum:["Myanglung","Lahachok"]},
  Madhesh:{Bara:["Kalaiya","Jitpur Simara"],Dhanusha:["Janakpur","Chhireshwornath"],Mahottari:["Jaleshwar","Aurahi"],Parsa:["Birgunj","Bahudarmai"],Rautahat:["Gaur","Baudhimai"],Saptari:["Rajbiraj","Agnisair"],Sarlahi:["Malangwa","Bagmati"],Siraha:["Lahan","Arnama"]},
  Bagmati:{Bhaktapur:["Bhaktapur","Changunarayan"],Chitwan:["Bharatpur","Ratnanagar"],Dhading:["Nilkantha","Benighat"],Dolakha:["Charikot","Bhimeshwor"],Kathmandu:["Kathmandu","Kirtipur"],Kavrepalanchok:["Dhulikhel","Banepa"],Lalitpur:["Lalitpur","Godawari"],Makwanpur:["Hetauda","Bakaiya"],Nuwakot:["Bidur","Belkotgadhi"],Ramechhap:["Manthali","Doramba"],Rasuwa:["Uttargaya","Gosaikunda"],Sindhuli:["Kamalamai","Dudhouli"],Sindhupalchok:["Chautara","Balefi"]},
  Gandaki:{Baglung:["Baglung","Burtibang"],Gorkha:["Gorkha","Aarughat"],Kaski:["Pokhara","Annapurna"],Lamjung:["Besisahar","Dordi"],Manang:["Chame","Narpa"],Mustang:["Lomanthang","Gharapjhong"],Myagdi:["Beni","Annapurna"],Nawalpur:["Kawasoti","Devchuli"],Parbat:["Kusma","Airawati"],Syangja:["Waling","Arjunchaupari"]},
  Lumbini:{Arghakhanchi:["Sandhikharka","Bhumekasthan"],Banke:["Nepalgunj","Baijanath"],Bardiya:["Gulariya","Badhaiyatal"],Dang:["Tulsipur","Banglachuli"],Gulmi:["Tamghas","Chandrakot"],Kapilvastu:["Kapilvastu","Banganga"],Nawalparasi_West:["Sunwal","Palhinandan"],Palpa:["Tansen","Mathagadhi"],Pyuthan:["Pyuthan","Gaumukhi"],Rolpa:["Rolpa","Duikholi"],Rupandehi:["Butwal","Devdaha"]},
  Karnali:{Dailekh:["Narayan","Aathabis"],Dolpa:["Thuli Bheri","Chharka"],Humla:["Simkot","Adanchuli"],Jajarkot:["Bheri","Barekot"],Jumla:["Chandannath","Guthichaur"],Kalikot:["Khandachakra","Mahawai"],Mugu:["Chhayanath Rara","Khatyad"],Rukum_West:["Musikot","Aathbiskot"],Salyan:["Sharada","Bagchaur"],Surkhet:["Birendranagar","Bheriganga"]},
  Sudurpashchim:{Achham:["Mangalsen","Bannigadhi"],Baitadi:["Dasharathchand","Dogdakedar"],Bajhang:["Jayaprithvi","Bungal"],Bajura:["Badimalika","Budhinanda"],Dadeldhura:["Amargadhi","Aalital"],Darchula:["Shailyashikhar","Apihimal"],Doti:["Dipayal","Aadarsha"],Kailali:["Dhangadhi","Ghodaghodi"],Kanchanpur:["Bhimdatta","Bedkot"]}
};

function loadDistricts(){
    let p=document.getElementById('province').value;
    let d=document.getElementById('district');
    let c=document.getElementById('city');
    d.innerHTML='<option value="">— Select District —</option>';
    c.innerHTML='<option value="">— District first —</option>';
    c.disabled=true;
    if(!p){d.disabled=true;return;}
    d.disabled=false;
    Object.keys(nData[p]).forEach(function(dist){
        let o=document.createElement('option');
        o.value=dist; o.textContent=dist;
        d.appendChild(o);
    });
}
function loadCities(){
    let p=document.getElementById('province').value;
    let d=document.getElementById('district').value;
    let c=document.getElementById('city');
    c.innerHTML='<option value="">— Select City —</option>';
    if(!d){c.disabled=true;return;}
    c.disabled=false;
    (nData[p][d]||[]).forEach(function(city){
        let o=document.createElement('option');
        o.value=city; o.textContent=city;
        c.appendChild(o);
    });
}
function selPay(el,val){
    document.querySelectorAll('.pay-opt').forEach(function(c){c.classList.remove('sel');});
    el.classList.add('sel');
    document.getElementById('payInput').value=val;
}
function liveValid(inp,fldId){
    let fld=document.getElementById(fldId);
    if(!fld) return;
    fld.classList.toggle('valid', inp.value.trim()!=='');
}
function applyPromo(){
    let inp=document.getElementById('promoInp');
    let msg=document.getElementById('promoMsg');
    if(inp.value.trim().toUpperCase()==='SPORT10'){
        msg.innerHTML='<i class="fa fa-check-circle"></i> 10% off applied! (demo)';
        msg.className='promo-msg ok';
    } else {
        msg.innerHTML='<i class="fa fa-times-circle"></i> Invalid promo code';
        msg.className='promo-msg no';
    }
}
document.getElementById('checkoutForm')?.addEventListener('submit',function(e){
    let name=document.querySelector('input[name="name"]');
    let phone=document.querySelector('input[name="phone"]');
    let province=document.querySelector('select[name="province"]');
    let district=document.querySelector('select[name="district"]');
    let city=document.querySelector('select[name="city"]');
    let address=document.querySelector('textarea[name="address"]');
    if(!name.value.trim()||!phone.value.trim()||!province.value||!district.value||!city.value||!address.value.trim()){
        e.preventDefault();
        alert('Please fill all required fields — Name, Phone, Province, District, City, Address.');
    }
});
</script>

</body>
</html>
<?php include "../includes/footer.php"; ?>