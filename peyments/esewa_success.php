<?php
/*
 ╔══════════════════════════════════════════════════════════╗
 ║  esewa_success.php  —  eSewa Payment Success (DEMO)      ║
 ║                                                          ║
 ║  Real eSewa integration ma:                              ║
 ║  - Verify payment via eSewa's verification API           ║
 ║  - Update order status in DB to 'Paid'                   ║
 ║  - Send confirmation email/SMS                           ║
 ╚══════════════════════════════════════════════════════════╝
*/
if(session_status() === PHP_SESSION_NONE) session_start();
include "../databases/db.php";

$status   = $_GET['status'] ?? 'success';
$order_id = (int)($_GET['order_id'] ?? $_SESSION['esewa_order_id'] ?? 0);
$amount   = (float)($_GET['amount'] ?? $_SESSION['esewa_amount'] ?? 0);
$order_id_str = str_pad($order_id, 5, '0', STR_PAD_LEFT);

// ── REAL INTEGRATION POINT ──
// In production, verify with eSewa API before updating DB:
// POST https://rc-epay.esewa.com.np/api/epay/transaction/status/
// Then: $conn->query("UPDATE orders SET status='Paid', payment_method='eSewa' WHERE id=$order_id");

// Demo: just mark as paid
if($order_id > 0 && $status === 'success'){
    $conn->query("UPDATE orders SET status='Paid', payment_method='eSewa' WHERE id=$order_id");
}

// Clear cart & esewa session data
$_SESSION['cart'] = [];
$_SESSION['last_order_id']  = $order_id;
unset($_SESSION['esewa_order_id'], $_SESSION['esewa_amount']);

// Generate a fake txn ref for demo
$txn_ref = 'ES' . strtoupper(bin2hex(random_bytes(4)));
$txn_time = date('Y-m-d H:i:s');
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
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    padding:24px 16px;
    overflow:hidden;
    position:relative;
}

/* CONFETTI CANVAS */
#confettiCanvas{
    position:fixed;inset:0;
    pointer-events:none;z-index:0;
}

/* GLOW BG */
body::before{
    content:'';
    position:fixed;inset:0;
    background:
        radial-gradient(ellipse 700px 500px at 50% 40%, rgba(96,187,70,.07) 0%, transparent 70%),
        radial-gradient(ellipse 500px 300px at 20% 80%, rgba(249,115,22,.04) 0%, transparent 70%);
    pointer-events:none;z-index:0;
}

.page-wrap{
    position:relative;z-index:10;
    width:100%;max-width:480px;
    display:flex;flex-direction:column;
    align-items:center;gap:0;
}

/* SUCCESS RING */
.suc-ring-outer{
    margin-bottom:24px;
    position:relative;
}
.suc-ring-pulse{
    position:absolute;inset:-12px;
    border-radius:50%;
    border:2px solid rgba(96,187,70,.3);
    animation:pulse-ring 1.8s ease-out infinite;
}
.suc-ring-pulse2{
    position:absolute;inset:-24px;
    border-radius:50%;
    border:1px solid rgba(96,187,70,.15);
    animation:pulse-ring 1.8s ease-out .4s infinite;
}
@keyframes pulse-ring{
    0%{opacity:1;transform:scale(1);}
    100%{opacity:0;transform:scale(1.35);}
}
.suc-ring{
    width:100px;height:100px;
    background:linear-gradient(135deg,#3d8b2a,#60bb46);
    border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:42px;color:#fff;
    box-shadow:0 0 0 10px rgba(96,187,70,.12), 0 20px 50px rgba(96,187,70,.3);
    animation:popIn .65s cubic-bezier(.2,.9,.4,1.4) both;
}
@keyframes popIn{
    from{transform:scale(0) rotate(-15deg);opacity:0;}
    to{transform:scale(1) rotate(0);opacity:1;}
}

/* MAIN CARD */
.suc-card{
    width:100%;
    background:linear-gradient(160deg,#111827,#0d1520);
    border:1px solid rgba(96,187,70,.15);
    border-radius:24px;
    overflow:hidden;
    box-shadow:0 28px 70px rgba(0,0,0,.55);
    animation:slideUp .5s cubic-bezier(.2,.9,.4,1) .2s both;
}
@keyframes slideUp{
    from{opacity:0;transform:translateY(30px);}
    to{opacity:1;transform:translateY(0);}
}

/* CARD TOP GREEN STRIP */
.card-top-strip{
    background:linear-gradient(135deg,#2d6e1a,#3d8b2a,#60bb46);
    padding:18px 28px;
    text-align:center;
    position:relative;
    overflow:hidden;
}
.card-top-strip::before{
    content:'';position:absolute;inset:0;
    background:repeating-linear-gradient(45deg,transparent,transparent 20px,rgba(255,255,255,.03) 20px,rgba(255,255,255,.03) 40px);
}
.card-top-strip h2{
    font-size:22px;font-weight:900;color:#fff;
    position:relative;z-index:1;
    display:flex;align-items:center;justify-content:center;gap:9px;
}
.card-top-strip p{
    font-size:13px;color:rgba(255,255,255,.75);
    margin-top:5px;position:relative;z-index:1;
}

/* ESEWA BRAND BAR */
.esewa-bar{
    display:flex;align-items:center;justify-content:space-between;
    padding:12px 24px;
    background:rgba(96,187,70,.06);
    border-bottom:1px solid rgba(96,187,70,.1);
}
.esewa-bar-logo{
    display:flex;align-items:center;gap:8px;
    font-size:16px;font-weight:900;color:#60bb46;
}
.esewa-bar-logo .mini-icon{
    width:30px;height:30px;
    background:#60bb46;border-radius:8px;
    display:flex;align-items:center;justify-content:center;
    font-size:9px;font-weight:900;color:#fff;
}
.esewa-verified{
    display:flex;align-items:center;gap:5px;
    font-size:11px;font-weight:700;color:#60bb46;
    background:rgba(96,187,70,.1);
    border:1px solid rgba(96,187,70,.2);
    padding:5px 11px;border-radius:20px;
}

/* CARD BODY */
.card-body{padding:24px 28px;}

/* AMOUNT BIG */
.paid-amount{
    text-align:center;
    padding:20px;
    background:rgba(96,187,70,.06);
    border:1px solid rgba(96,187,70,.12);
    border-radius:16px;
    margin-bottom:20px;
}
.paid-amount .label{
    font-size:11px;font-weight:700;color:#475569;
    text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;
}
.paid-amount .value{
    font-size:46px;font-weight:900;color:#60bb46;
    letter-spacing:-1.5px;line-height:1;
}
.paid-amount .value small{font-size:18px;color:#94a3b8;margin-right:3px;vertical-align:middle;}

/* TXN DETAILS */
.txn-grid{
    display:flex;flex-direction:column;gap:10px;
    margin-bottom:20px;
}
.txn-row{
    display:flex;align-items:center;justify-content:space-between;
    padding:10px 14px;
    background:rgba(255,255,255,.02);
    border:1px solid rgba(255,255,255,.05);
    border-radius:12px;
    transition:.2s;
}
.txn-row:hover{background:rgba(96,187,70,.05);border-color:rgba(96,187,70,.12);}
.txn-key{
    font-size:12px;font-weight:600;color:#64748b;
    display:flex;align-items:center;gap:7px;
}
.txn-key i{font-size:11px;color:#475569;}
.txn-val{
    font-size:13px;font-weight:700;color:#f1f5f9;
    text-align:right;
}
.txn-val.green{color:#60bb46;}
.txn-val.orange{color:#f97316;}

/* ORDER TRACKING */
.track-wrap{
    margin:20px 0;
    padding:18px;
    background:rgba(255,255,255,.02);
    border:1px solid rgba(255,255,255,.05);
    border-radius:16px;
}
.track-label{
    font-size:11px;font-weight:700;color:#475569;
    text-transform:uppercase;letter-spacing:.8px;
    margin-bottom:16px;
    display:flex;align-items:center;gap:7px;
}
.track-steps{
    display:flex;align-items:center;
    position:relative;
}
.track-steps::before{
    content:'';position:absolute;
    top:16px;left:16px;right:16px;
    height:2px;
    background:rgba(255,255,255,.06);
    z-index:0;
}
.t-step{
    flex:1;display:flex;flex-direction:column;align-items:center;
    gap:7px;z-index:1;
    font-size:10.5px;font-weight:700;color:#475569;
    text-align:center;
}
.t-dot{
    width:34px;height:34px;
    border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:13px;
    background:rgba(255,255,255,.04);
    border:1.5px solid rgba(255,255,255,.07);
    color:#475569;
    transition:.4s;
}
.t-step.done .t-dot{
    background:#60bb46;border-color:#60bb46;color:#fff;
    box-shadow:0 0 0 5px rgba(96,187,70,.15);
}
.t-step.done{color:#60bb46;}
.t-step.active .t-dot{
    background:rgba(249,115,22,.15);border-color:#f97316;color:#f97316;
    animation:activePulse 1.5s ease-in-out infinite;
}
.t-step.active{color:#f97316;}
@keyframes activePulse{
    0%,100%{box-shadow:0 0 0 5px rgba(249,115,22,.1);}
    50%{box-shadow:0 0 0 9px rgba(249,115,22,.05);}
}

/* DEMO NOTE */
.demo-note{
    display:flex;align-items:flex-start;gap:9px;
    background:rgba(251,191,36,.06);
    border:1px solid rgba(251,191,36,.15);
    border-radius:12px;
    padding:12px 14px;
    margin-bottom:18px;
    font-size:12px;color:#fbbf24;line-height:1.55;font-weight:500;
}
.demo-note i{font-size:14px;margin-top:1px;flex-shrink:0;}

/* BUTTONS */
.btn-row{display:flex;gap:10px;flex-wrap:wrap;}
.btn-primary{
    flex:1;min-width:140px;
    padding:14px;
    background:linear-gradient(135deg,#f97316,#ea580c);
    color:#fff;border:none;border-radius:14px;
    font-size:14px;font-weight:800;
    font-family:'Outfit',sans-serif;
    cursor:pointer;transition:.3s;
    display:flex;align-items:center;justify-content:center;gap:8px;
    text-decoration:none;
    box-shadow:0 8px 24px rgba(234,88,12,.3);
}
.btn-primary:hover{transform:translateY(-3px);box-shadow:0 14px 32px rgba(234,88,12,.45);}
.btn-secondary{
    flex:1;min-width:120px;
    padding:14px;
    background:rgba(255,255,255,.05);
    color:#94a3b8;
    border:1.5px solid rgba(255,255,255,.08);
    border-radius:14px;
    font-size:14px;font-weight:700;
    font-family:'Outfit',sans-serif;
    cursor:pointer;transition:.3s;
    display:flex;align-items:center;justify-content:center;gap:8px;
    text-decoration:none;
}
.btn-secondary:hover{background:rgba(255,255,255,.1);color:#f1f5f9;border-color:rgba(255,255,255,.18);}

/* PRINT RECEIPT */
.print-link{
    display:flex;align-items:center;justify-content:center;gap:7px;
    margin-top:14px;
    font-size:12.5px;font-weight:700;color:#475569;
    cursor:pointer;transition:.2s;text-decoration:none;
}
.print-link:hover{color:#60bb46;}

@media(max-width:480px){
    .card-body{padding:18px 16px;}
    .paid-amount .value{font-size:36px;}
    .card-top-strip{padding:16px 18px;}
}
@media print{
    body{background:#fff;}
    #confettiCanvas,.btn-row,.print-link,.demo-note{display:none!important;}
    .suc-card{box-shadow:none;border:1px solid #e2e8f0;}
}
</style>
</head>
<body>

<canvas id="confettiCanvas"></canvas>

<div class="page-wrap">

    <!-- SUCCESS RING -->
    <div class="suc-ring-outer">
        <div class="suc-ring-pulse"></div>
        <div class="suc-ring-pulse2"></div>
        <div class="suc-ring"><i class="fa fa-check"></i></div>
    </div>

    <!-- MAIN CARD -->
    <div class="suc-card">

        <!-- TOP STRIP -->
        <div class="card-top-strip">
            <h2><i class="fa fa-circle-check"></i> Payment Successful!</h2>
            <p>Tapai ko payment successfully process bhayo 🎉</p>
        </div>

        <!-- ESEWA BAR -->
        <div class="esewa-bar">
            <div class="esewa-bar-logo">
                <div class="mini-icon">eS</div>
                eSewa
            </div>
            <div class="esewa-verified">
                <i class="fa fa-shield-check"></i> Verified Payment
            </div>
        </div>

        <!-- BODY -->
        <div class="card-body">

            <!-- AMOUNT -->
            <div class="paid-amount">
                <div class="label">Amount Paid</div>
                <div class="value"><small>Rs.</small><?php echo number_format($amount); ?></div>
            </div>

            <!-- TXN DETAILS -->
            <div class="txn-grid">
                <div class="txn-row">
                    <div class="txn-key"><i class="fa fa-receipt"></i> Order ID</div>
                    <div class="txn-val orange">#<?php echo $order_id_str; ?></div>
                </div>
                <div class="txn-row">
                    <div class="txn-key"><i class="fa fa-id-card"></i> Txn Reference</div>
                    <div class="txn-val green"><?php echo $txn_ref; ?></div>
                </div>
                <div class="txn-row">
                    <div class="txn-key"><i class="fa fa-wallet"></i> Payment Via</div>
                    <div class="txn-val">eSewa Wallet</div>
                </div>
                <div class="txn-row">
                    <div class="txn-key"><i class="fa fa-calendar"></i> Date & Time</div>
                    <div class="txn-val"><?php echo date('d M Y, h:i A'); ?></div>
                </div>
                <div class="txn-row">
                    <div class="txn-key"><i class="fa fa-circle-check"></i> Status</div>
                    <div class="txn-val green"><i class="fa fa-check-circle"></i> PAID</div>
                </div>
            </div>

            <!-- ORDER TRACKING -->
            <div class="track-wrap">
                <div class="track-label"><i class="fa fa-truck"></i> Order Status</div>
                <div class="track-steps">
                    <div class="t-step done">
                        <div class="t-dot"><i class="fa fa-check" style="font-size:13px;"></i></div>
                        <span>Ordered</span>
                    </div>
                    <div class="t-step done">
                        <div class="t-dot"><i class="fa fa-money-bill-wave" style="font-size:11px;"></i></div>
                        <span>Paid</span>
                    </div>
                    <div class="t-step active">
                        <div class="t-dot"><i class="fa fa-box" style="font-size:12px;"></i></div>
                        <span>Packing</span>
                    </div>
                    <div class="t-step">
                        <div class="t-dot"><i class="fa fa-truck" style="font-size:11px;"></i></div>
                        <span>Shipped</span>
                    </div>
                    <div class="t-step">
                        <div class="t-dot"><i class="fa fa-house" style="font-size:12px;"></i></div>
                        <span>Delivered</span>
                    </div>
                </div>
            </div>

            <!-- DEMO NOTE -->
            <div class="demo-note">
                <i class="fa fa-flask"></i>
                <div>
                    <strong>DEMO MODE:</strong> Yo real eSewa transaction hoina. Production ma,
                    eSewa ko verification API call garnu parxa ra DB ma <code style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:4px;font-size:11px;">status='Paid'</code> update hunxa.
                </div>
            </div>

            <!-- BUTTONS -->
            <div class="btn-row">
                <a href="../publics/index.php" class="btn-primary">
                    <i class="fa fa-store"></i> Continue Shopping
                </a>
                <a href="orders.php" class="btn-secondary">
                    <i class="fa fa-box"></i> My Orders
                </a>
            </div>

            <!-- PRINT -->
            <a class="print-link" onclick="window.print()">
                <i class="fa fa-print"></i> Print Receipt
            </a>

        </div>
    </div>
</div>

<script>
/* ── CONFETTI ── */
(function(){
    var canvas = document.getElementById('confettiCanvas');
    var ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    var colors = ['#60bb46','#3d8b2a','#f97316','#fbbf24','#34d399','#fff'];
    var particles = [];

    for(var i = 0; i < 120; i++){
        particles.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height - canvas.height,
            w: Math.random() * 10 + 4,
            h: Math.random() * 6 + 2,
            color: colors[Math.floor(Math.random() * colors.length)],
            rot: Math.random() * 360,
            rotSpeed: (Math.random() - 0.5) * 6,
            vx: (Math.random() - 0.5) * 3,
            vy: Math.random() * 3 + 1.5,
            opacity: 1
        });
    }

    var done = false;
    function draw(){
        if(done) return;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        var allBelow = true;
        particles.forEach(function(p){
            if(p.y < canvas.height + 20) allBelow = false;
            p.x += p.vx;
            p.y += p.vy;
            p.rot += p.rotSpeed;
            p.vy += 0.03;
            if(p.y > canvas.height * 0.7) p.opacity = Math.max(0, p.opacity - 0.01);
            ctx.save();
            ctx.globalAlpha = p.opacity;
            ctx.translate(p.x, p.y);
            ctx.rotate(p.rot * Math.PI / 180);
            ctx.fillStyle = p.color;
            ctx.fillRect(-p.w/2, -p.h/2, p.w, p.h);
            ctx.restore();
        });
        if(allBelow){ done = true; ctx.clearRect(0,0,canvas.width,canvas.height); return; }
        requestAnimationFrame(draw);
    }
    setTimeout(draw, 300);

    window.addEventListener('resize', function(){
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    });
})();
</script>

</body>
</html>