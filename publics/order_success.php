<?php
// ==================== TOP - NO OUTPUT BEFORE THIS ====================
ob_start();
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

include "../databases/db.php";
include "../includes/header.php";

// Redirect if no order
if (empty($_SESSION['last_order_id'])) {
    header("Location: ../publics/index.php");
    exit;
}

$order_id = $_SESSION['last_order_id'];

// ====================== CREATE NOTIFICATION ======================
// After successful order, add notification
if (isset($_SESSION['user']['id'])) {
    $user_id = (int)$_SESSION['user']['id'];
    $message = "Your order #$order_id has been placed successfully! We will contact you soon for delivery.";
    
    $sql = "INSERT INTO notifications (user_id, type, message, related_id, created_at) 
            VALUES ($user_id, 'order', '$message', $order_id, NOW())";
    
    $conn->query($sql);
}

// Clear last order id
unset($_SESSION['last_order_id']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Confirmed — JerseyGhar</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:opsz,wght@9..40,400;9..40,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
  --ink:#12111a; --ink-2:#44425a; --ink-3:#8e8ba8;
  --bg:#f4f3fb; --surface:#fff; --surface-2:#f9f8fe;
  --border:rgba(100,90,200,0.13);
  --accent:#4f3de8; --accent-pale:#eeebff;
  --green:#0aaa6e; --green-pale:#d3f5ea;
  --shadow-md:0 6px 28px rgba(79,61,232,0.12);
  --r-lg:20px; --r-xl:28px;
  --font-h:'Syne',sans-serif; --font-b:'DM Sans',sans-serif;
  --ease:cubic-bezier(0.34,1.56,0.64,1);
}
body{font-family:var(--font-b);background:var(--bg);color:var(--ink);min-height:100vh;}

/* Progress */
.progress-bar{background:var(--ink);padding:14px 32px;display:flex;align-items:center;justify-content:center;}
.pstep{display:flex;flex-direction:column;align-items:center;gap:5px;position:relative;flex:1;max-width:130px;}
.pstep:not(:last-child)::after{content:'';position:absolute;top:15px;left:calc(50% + 18px);width:calc(100% - 36px);height:1.5px;background:var(--green);z-index:0;}
.pstep-dot{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;font-family:var(--font-h);z-index:1;background:var(--green);color:#fff;box-shadow:0 0 0 5px rgba(10,170,110,0.18);}
.pstep-label{font-size:10.5px;font-weight:500;font-family:var(--font-h);color:var(--green);letter-spacing:0.06em;text-transform:uppercase;}

/* Main */
.success-wrap{max-width:600px;margin:48px auto 80px;padding:0 20px;text-align:center;}

/* Confetti ring */
.success-ring{width:110px;height:110px;border-radius:50%;background:var(--green);color:#fff;font-size:48px;display:flex;align-items:center;justify-content:center;margin:0 auto 28px;box-shadow:0 0 0 14px var(--green-pale),0 14px 40px rgba(10,170,110,0.3);animation:popRing 0.6s var(--ease) forwards;}
@keyframes popRing{from{transform:scale(0) rotate(-30deg);opacity:0;}to{transform:scale(1) rotate(0);opacity:1;}}

.success-card{background:var(--surface);border-radius:var(--r-xl);border:1.5px solid var(--border);padding:40px 36px;box-shadow:var(--shadow-md);}
@media(max-width:500px){.success-card{padding:28px 20px;}}

.success-card h2{font-family:var(--font-h);font-size:30px;font-weight:800;color:var(--ink);margin-bottom:10px;}
.success-card p{font-size:14.5px;color:var(--ink-2);line-height:1.75;margin-bottom:28px;}

.order-num{background:var(--accent-pale);border:1.5px solid rgba(79,61,232,0.2);border-radius:var(--r-lg);padding:18px 24px;margin-bottom:30px;}
.order-num small{font-size:11px;font-weight:700;font-family:var(--font-h);color:var(--accent);letter-spacing:0.1em;text-transform:uppercase;display:block;margin-bottom:5px;}
.order-num strong{font-family:var(--font-h);font-size:30px;font-weight:800;color:var(--accent);}

/* Delivery tracker */
.track{display:flex;justify-content:space-between;position:relative;margin-bottom:32px;}
.track-line{position:absolute;top:16px;left:8%;right:8%;height:2px;background:var(--border);}
.track-line-fill{height:100%;width:0%;background:var(--green);border-radius:2px;transition:width 1.2s ease 0.5s;}
.track-step{display:flex;flex-direction:column;align-items:center;gap:7px;flex:1;z-index:1;}
.track-dot{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;background:var(--surface-2);border:2px solid var(--border);color:var(--ink-3);transition:all 0.4s;}
.track-step.done .track-dot{background:var(--green);color:#fff;border-color:var(--green);box-shadow:0 0 0 5px var(--green-pale);}
.track-step span{font-size:11px;font-weight:600;font-family:var(--font-h);color:var(--ink-3);}
.track-step.done span{color:var(--green);}

/* Info cards */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:28px;}
@media(max-width:440px){.info-grid{grid-template-columns:1fr;}}
.info-card{background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--r-lg);padding:14px 16px;text-align:left;}
.info-card i{font-size:18px;color:var(--accent);margin-bottom:8px;display:block;}
.info-card .ic-title{font-size:12px;font-weight:700;font-family:var(--font-h);color:var(--ink-3);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:4px;}
.info-card .ic-val{font-size:14px;font-weight:500;color:var(--ink);}

/* Buttons */
.btn-row{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
.continue-btn{display:inline-flex;align-items:center;gap:9px;padding:14px 32px;background:var(--accent);color:#fff;text-decoration:none;border-radius:var(--r-lg);font-family:var(--font-h);font-size:14px;font-weight:700;transition:all 0.3s var(--ease);box-shadow:0 6px 22px rgba(79,61,232,0.28);}
.continue-btn:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(79,61,232,0.36);}
.whatsapp-btn{display:inline-flex;align-items:center;gap:9px;padding:14px 28px;background:#25d366;color:#fff;text-decoration:none;border-radius:var(--r-lg);font-family:var(--font-h);font-size:14px;font-weight:700;transition:all 0.3s var(--ease);box-shadow:0 6px 22px rgba(37,211,102,0.28);}
.whatsapp-btn:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(37,211,102,0.36);}
</style>
</head>
<body>

<!-- PROGRESS (all done) -->
<div class="progress-bar">
  <div class="pstep">
    <div class="pstep-dot"><i class="fa fa-check" style="font-size:10px;"></i></div>
    <div class="pstep-label">Cart</div>
  </div>
  <div class="pstep">
    <div class="pstep-dot"><i class="fa fa-check" style="font-size:10px;"></i></div>
    <div class="pstep-label">Checkout</div>
  </div>
  <div class="pstep">
    <div class="pstep-dot"><i class="fa fa-check" style="font-size:10px;"></i></div>
    <div class="pstep-label">Confirm</div>
  </div>
</div>

<div class="success-wrap">
  <div class="success-ring"><i class="fa fa-check"></i></div>
  
  <div class="success-card">
    <h2>Order Confirmed! 🎉</h2>
    <p>
      Tapaiको Jersey order successfully place bhayo!<br>
      Hami chhai delivery ko lagi contact garnchhau.<br>
      <strong style="color:var(--green);">Dhanyabad for shopping with JerseyGhar!</strong>
    </p>

    <div class="order-num">
      <small>Your Order Number</small>
      <strong>#<?php echo str_pad($order_id, 5, '0', STR_PAD_LEFT); ?></strong>
    </div>

    <!-- Delivery tracker -->
    <div class="track">
      <div class="track-line"><div class="track-line-fill" id="trackFill"></div></div>
      <div class="track-step done">
        <div class="track-dot"><i class="fa fa-check"></i></div>
        <span>Ordered</span>
      </div>
      <div class="track-step" id="ts2">
        <div class="track-dot"><i class="fa fa-box"></i></div>
        <span>Packing</span>
      </div>
      <div class="track-step" id="ts3">
        <div class="track-dot"><i class="fa fa-truck"></i></div>
        <span>Shipped</span>
      </div>
      <div class="track-step" id="ts4">
        <div class="track-dot"><i class="fa fa-house"></i></div>
        <span>Delivered</span>
      </div>
    </div>

    <!-- Info cards -->
    <div class="info-grid">
      <div class="info-card">
        <i class="fa fa-clock"></i>
        <div class="ic-title">Estimated Delivery</div>
        <div class="ic-val">3–5 Business Days</div>
      </div>
      <div class="info-card">
        <i class="fa fa-phone"></i>
        <div class="ic-title">Support</div>
        <div class="ic-val">+977-98XXXXXXXX</div>
      </div>
      <div class="info-card">
        <i class="fa fa-tag"></i>
        <div class="ic-title">Order ID</div>
        <div class="ic-val">#<?php echo str_pad($order_id, 5, '0', STR_PAD_LEFT); ?></div>
      </div>
      <div class="info-card">
        <i class="fa fa-rotate-left"></i>
        <div class="ic-title">Returns</div>
        <div class="ic-val">7-day easy returns</div>
      </div>
    </div>

    <div class="btn-row">
      <a href="../publics/index.php" class="continue-btn">
        <i class="fa fa-bag-shopping"></i> Continue Shopping
      </a>
      <a href="https://wa.me/97798XXXXXXXX?text=Hello!+My+order+%23<?php echo str_pad($order_id,5,'0',STR_PAD_LEFT); ?>+regarding+query." 
         target="_blank" class="whatsapp-btn">
        <i class="fa-brands fa-whatsapp"></i> WhatsApp Us
      </a>
    </div>
  </div>
</div>

<script>
// Animate progress fill
setTimeout(()=>{
  document.getElementById('trackFill').style.width = '12%';
}, 800);
</script>

</body>
</html>
<?php include "../includes/footer.php"; ?>

<?php 
include "../includes/footer.php"; 
ob_end_flush(); 
?>