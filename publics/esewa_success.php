<?php
// ============================================
// esewa_success.php — SportGhar
// ============================================
ob_start();
session_start();
include "../databases/db.php"; // DB connection include garnu (path adjust garnu)

$order_id = (int)($_GET['order_id'] ?? $_SESSION['esewa_order_id'] ?? 1001);
$amount   = (float)($_GET['amount'] ?? $_SESSION['esewa_amount'] ?? 1500);
$status   = $_GET['status'] ?? 'success';
$order_id_str = str_pad($order_id, 5, '0', STR_PAD_LEFT);

// Customer info
$customer_name  = $_SESSION['customer_name'] ?? 'Valued Customer';
$customer_phone = $_SESSION['customer_phone'] ?? '9841234567';

// ─── DB MA NOTIFICATION INSERT (only once per order) ───
// Session check — double insert hudaina
$notif_key = 'notif_inserted_' . $order_id;
if (!isset($_SESSION[$notif_key]) && isset($_SESSION['user']['id'])) {
    $user_id = (int)$_SESSION['user']['id'];
    $notif_message = "Tapaiको Order #" . $order_id_str . " ko Rs. " . number_format($amount) . " payment eSewa bata safaltapurvak prapt bhayo. Dhanyabad!";
    $notif_message_escaped = $conn->real_escape_string($notif_message);

    $conn->query("INSERT INTO notifications (user_id, message, related_id, is_read, created_at)
                  VALUES ($user_id, '$notif_message_escaped', $order_id, 0, NOW())");

    $_SESSION[$notif_key] = true; // Mark so it doesn't re-insert on refresh
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Successful — SportGhar</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{
    font-family:'Outfit',sans-serif;
    background:#0a0f1a;
    min-height:100vh;
    display:flex;flex-direction:column;
    align-items:center;justify-content:center;
    padding:20px;
}
.card{
    background:linear-gradient(160deg,#111827,#0d1520);
    border:1px solid rgba(255,255,255,.07);
    border-radius:24px;
    padding:40px 32px;
    max-width:420px;width:100%;
    text-align:center;
    box-shadow:0 24px 60px rgba(0,0,0,.5);
}
.check-circle{
    width:80px;height:80px;
    border-radius:50%;
    background:linear-gradient(135deg,#3d8b2a,#60bb46);
    display:flex;align-items:center;justify-content:center;
    margin:0 auto 20px;
    font-size:32px;color:#fff;
    animation:pop .4s cubic-bezier(.34,1.56,.64,1);
}
@keyframes pop{0%{transform:scale(0);}100%{transform:scale(1);}}
h1{font-size:24px;font-weight:800;color:#f1f5f9;margin-bottom:6px;}
.sub{font-size:14px;color:#64748b;margin-bottom:28px;}
.detail-box{
    background:rgba(255,255,255,.03);
    border:1px solid rgba(255,255,255,.07);
    border-radius:14px;
    padding:18px;
    margin-bottom:22px;
    text-align:left;
}
.row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;}
.row:not(:last-child){border-bottom:1px solid rgba(255,255,255,.05);}
.row .lbl{font-size:12px;color:#64748b;font-weight:600;}
.row .val{font-size:13px;color:#f1f5f9;font-weight:700;}
.row .val.green{color:#60bb46;}
.notif-box{
    background:rgba(96,187,70,.07);
    border:1px solid rgba(96,187,70,.2);
    border-radius:14px;
    padding:14px 16px;
    margin-bottom:22px;
    text-align:left;
}
.notif-title{font-size:12px;font-weight:700;color:#60bb46;margin-bottom:6px;display:flex;align-items:center;gap:6px;}
.notif-msg{font-size:13px;color:#94a3b8;line-height:1.5;}
.notif-msg strong{color:#f1f5f9;}
.btn{
    display:block;width:100%;padding:14px;
    background:linear-gradient(135deg,#3d8b2a,#60bb46);
    color:#fff;border:none;border-radius:12px;
    font-size:15px;font-weight:800;font-family:'Outfit',sans-serif;
    cursor:pointer;text-decoration:none;margin-bottom:10px;
    transition:.2s;
}
.btn:hover{opacity:.9;}
.btn-ghost{
    display:block;width:100%;padding:13px;
    background:transparent;
    color:#60bb46;border:1.5px solid rgba(96,187,70,.3);
    border-radius:12px;font-size:14px;font-weight:700;
    font-family:'Outfit',sans-serif;cursor:pointer;
    text-decoration:none;transition:.2s;
}
.btn-ghost:hover{background:rgba(96,187,70,.07);}

/* NOTIFICATION TOAST */
#toast{
    position:fixed;top:20px;right:20px;
    background:linear-gradient(135deg,#1a2e1a,#1e3a1e);
    border:1px solid rgba(96,187,70,.3);
    border-radius:16px;padding:14px 18px;
    display:flex;align-items:flex-start;gap:12px;
    max-width:320px;
    transform:translateX(380px);
    transition:transform .4s cubic-bezier(.34,1.56,.64,1);
    z-index:9999;
    box-shadow:0 8px 32px rgba(0,0,0,.4);
}
#toast.show{transform:translateX(0);}
.toast-icon{
    width:36px;height:36px;flex-shrink:0;
    background:rgba(96,187,70,.2);border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:16px;color:#60bb46;
}
.toast-body{}
.toast-title{font-size:13px;font-weight:800;color:#f1f5f9;margin-bottom:3px;}
.toast-msg{font-size:12px;color:#94a3b8;line-height:1.4;}
.toast-time{font-size:11px;color:#475569;margin-top:4px;}
</style>
</head>
<body>

<!-- NOTIFICATION TOAST -->
<div id="toast">
    <div class="toast-icon"><i class="fa fa-bell"></i></div>
    <div class="toast-body">
        <div class="toast-title">Payment Successful!</div>
        <div class="toast-msg">
            Rs.<?php echo number_format($amount); ?> received for Order #<?php echo $order_id_str; ?>
        </div>
        <div class="toast-time" id="toastTime">just now</div>
    </div>
</div>

<!-- SUCCESS CARD -->
<div class="card">
    <div class="check-circle"><i class="fa fa-check"></i></div>
    <h1>Payment Successful!</h1>
    <p class="sub">Tapaiको payment safaltapurvak prapt bhayo</p>

    <!-- ORDER DETAILS -->
    <div class="detail-box">
        <div class="row">
            <span class="lbl">Order ID</span>
            <span class="val">#<?php echo $order_id_str; ?></span>
        </div>
        <div class="row">
            <span class="lbl">Amount Paid</span>
            <span class="val green">Rs. <?php echo number_format($amount); ?></span>
        </div>
        <div class="row">
            <span class="lbl">Payment Method</span>
            <span class="val">eSewa</span>
        </div>
        <div class="row">
            <span class="lbl">Status</span>
            <span class="val green"><i class="fa fa-circle" style="font-size:8px"></i> Confirmed</span>
        </div>
        <div class="row">
            <span class="lbl">Date & Time</span>
            <span class="val" id="payTime">--</span>
        </div>
    </div>

    <!-- NOTIFICATION BOX -->
    <div class="notif-box">
        <div class="notif-title"><i class="fa fa-bell"></i> Notification Pathaiyo</div>
        <div class="notif-msg" id="notifMsg">
            <?php if(isset($_SESSION['user']['id'])): ?>
                <strong>Notification save bhayo!</strong><br>
                Tapaiको notification bell icon ma dekhiney cha.
            <?php else: ?>
                Browser notification enable garnu hola confirmation ko lagi.
            <?php endif; ?>
        </div>
    </div>

    <a href="index.php" class="btn">
        <i class="fa fa-home"></i> Go to Homepage
    </a>
    <a href="notifications.php" class="btn-ghost">
        <i class="fa fa-bell"></i> View Notifications
    </a>
</div>

<script>
var orderAmount = <?php echo $amount; ?>;
var orderId     = '<?php echo $order_id_str; ?>';
var customerPhone = '<?php echo $customer_phone; ?>';

// ─── DATE TIME SHOW ───
var now = new Date();
document.getElementById('payTime').textContent =
    now.toLocaleDateString('ne-NP') + ' ' +
    now.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});

// ─── TOAST SHOW ───
setTimeout(function(){
    document.getElementById('toast').classList.add('show');
}, 600);

setTimeout(function(){
    document.getElementById('toast').classList.remove('show');
}, 5000);

// ─── BROWSER NOTIFICATION ───
var smsMessage = 
    "Namaskar! Tapaiको Order #" + orderId + 
    " ko Rs." + orderAmount.toLocaleString() + 
    " payment eSewa bata safaltapurvak prapt bhayo. Dhanyabad! - SportGhar";

function sendBrowserNotification(){
    if(!("Notification" in window)) return;

    if(Notification.permission === 'granted'){
        triggerNotification();
    } else if(Notification.permission !== 'denied'){
        Notification.requestPermission().then(function(perm){
            if(perm === 'granted') triggerNotification();
        });
    }
}

function triggerNotification(){
    var notif = new Notification('SportGhar — Payment Confirmed!', {
        body: 'Order #' + orderId + ' | Rs.' + orderAmount.toLocaleString() + ' | eSewa Payment Successful',
        icon: 'https://via.placeholder.com/64/60bb46/ffffff?text=SG',
        badge: 'https://via.placeholder.com/32/60bb46/ffffff?text=SG',
        tag: 'payment-' + orderId,
        requireInteraction: false
    });
    notif.onclick = function(){
        window.focus();
        window.location.href = 'notifications.php';
        notif.close();
    };
}

setTimeout(function(){
    sendBrowserNotification();
}, 1000);

console.log('%c📱 SMS (Demo):', 'color:#60bb46;font-weight:bold;font-size:13px');
console.log('%cTo: ' + customerPhone, 'color:#94a3b8');
console.log('%cMessage: ' + smsMessage, 'color:#f1f5f9');
</script>
</body>
</html>
<?php ob_end_flush(); ?>