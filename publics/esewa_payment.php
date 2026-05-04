<?php
/*
 ╔══════════════════════════════════════════════════════╗
 ║  esewa_payment.php  —  SportGhar eSewa Demo Gateway  ║
 ║  This simulates the eSewa payment page (DEMO only)   ║
 ╚══════════════════════════════════════════════════════╝

 HOW TO WIRE INTO checkout.php:
 ─────────────────────────────────────────────────────────
 When user selects eSewa & clicks "Place Order":
   1. Save order to DB with status = 'Pending_Payment'
   2. Redirect to: esewa_payment.php?order_id=ORDER_ID&amount=TOTAL
 ─────────────────────────────────────────────────────────
*/
if(session_status() === PHP_SESSION_NONE) session_start();

$order_id = (int)($_GET['order_id'] ?? $_SESSION['last_order_id'] ?? 1001);
$amount   = (float)($_GET['amount'] ?? $_SESSION['esewa_amount'] ?? 1500);
$order_id_str = str_pad($order_id, 5, '0', STR_PAD_LEFT);

// Store in session for success page
$_SESSION['esewa_order_id'] = $order_id;
$_SESSION['esewa_amount']   = $amount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>eSewa Payment — SportGhar</title>
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
    padding:20px;
    position:relative;
    overflow:hidden;
}

/* ANIMATED BG */
body::before{
    content:'';
    position:fixed;inset:0;
    background:
        radial-gradient(ellipse 800px 500px at 20% 30%, rgba(96,187,70,.06) 0%, transparent 70%),
        radial-gradient(ellipse 600px 400px at 80% 70%, rgba(96,187,70,.04) 0%, transparent 70%),
        radial-gradient(ellipse 400px 300px at 50% 50%, rgba(10,15,26,.8) 0%, transparent 100%);
    pointer-events:none;
    z-index:0;
}

/* FLOATING PARTICLES */
.particle{
    position:fixed;
    width:4px;height:4px;
    border-radius:50%;
    background:rgba(96,187,70,.3);
    animation:float linear infinite;
    pointer-events:none;
    z-index:0;
}
@keyframes float{
    0%{transform:translateY(100vh) scale(0);opacity:0;}
    10%{opacity:1;}
    90%{opacity:.5;}
    100%{transform:translateY(-10vh) scale(1.5);opacity:0;}
}

/* CARD */
.gateway-wrap{
    position:relative;
    z-index:10;
    width:100%;
    max-width:440px;
    display:flex;
    flex-direction:column;
    gap:0;
}

/* HEADER BAR */
.gw-header{
    background:linear-gradient(135deg,#3d8b2a,#60bb46);
    border-radius:22px 22px 0 0;
    padding:22px 28px 18px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    box-shadow:0 -4px 30px rgba(96,187,70,.2);
}
.esewa-logo{
    display:flex;
    align-items:center;
    gap:10px;
}
.esewa-logo-icon{
    width:46px;height:46px;
    background:#fff;
    border-radius:14px;
    display:flex;align-items:center;justify-content:center;
    font-size:11px;font-weight:900;color:#3d8b2a;
    letter-spacing:-.3px;
    box-shadow:0 4px 16px rgba(0,0,0,.2);
}
.esewa-logo-text{
    font-size:22px;font-weight:900;color:#fff;
    letter-spacing:-.5px;
}
.gw-secure{
    display:flex;align-items:center;gap:5px;
    font-size:11px;font-weight:700;color:rgba(255,255,255,.75);
    background:rgba(255,255,255,.15);
    padding:6px 12px;border-radius:20px;
}

/* MERCHANT ROW */
.merchant-row{
    background:rgba(96,187,70,.06);
    border-left:4px solid #60bb46;
    border-right:4px solid #60bb46;
    padding:14px 28px;
    display:flex;
    align-items:center;
    justify-content:space-between;
}
.merchant-name{
    font-size:13px;font-weight:700;color:#60bb46;
    display:flex;align-items:center;gap:7px;
}
.merchant-name i{font-size:11px;}
.merchant-order{
    font-size:12px;color:#64748b;font-weight:600;
}

/* AMOUNT BOX */
.amount-box{
    background:rgba(255,255,255,.02);
    border-left:4px solid #60bb46;
    border-right:4px solid #60bb46;
    padding:16px 28px 20px;
    text-align:center;
    border-bottom:1px solid rgba(255,255,255,.05);
}
.amount-label{font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;}
.amount-val{
    font-size:42px;font-weight:900;color:#60bb46;
    letter-spacing:-1px;
    line-height:1;
}
.amount-val small{font-size:16px;font-weight:700;color:#94a3b8;margin-right:4px;vertical-align:middle;}

/* MAIN BODY */
.gw-body{
    background:linear-gradient(160deg,#111827,#0d1520);
    border-radius:0 0 22px 22px;
    border:1px solid rgba(255,255,255,.06);
    border-top:none;
    padding:28px;
    box-shadow:0 24px 60px rgba(0,0,0,.5);
}

/* DEMO BADGE */
.demo-badge{
    display:flex;align-items:center;gap:8px;
    background:rgba(251,191,36,.08);
    border:1px solid rgba(251,191,36,.2);
    border-radius:12px;
    padding:10px 14px;
    margin-bottom:22px;
    font-size:12px;color:#fbbf24;font-weight:600;
}
.demo-badge i{font-size:14px;}

/* TABS */
.pay-tabs{
    display:flex;
    background:rgba(255,255,255,.04);
    border-radius:12px;
    padding:4px;
    margin-bottom:20px;
    gap:4px;
}
.pay-tab{
    flex:1;padding:9px;
    border-radius:9px;
    font-size:12.5px;font-weight:700;
    text-align:center;cursor:pointer;
    color:#64748b;
    transition:.25s;
    display:flex;align-items:center;justify-content:center;gap:6px;
}
.pay-tab.active{
    background:#60bb46;color:#fff;
    box-shadow:0 4px 12px rgba(96,187,70,.3);
}

/* FORM */
.fld{display:flex;flex-direction:column;gap:7px;margin-bottom:14px;}
.fld label{
    font-size:11px;font-weight:700;color:#64748b;
    text-transform:uppercase;letter-spacing:.7px;
    display:flex;align-items:center;gap:6px;
}
.inp-wrap{position:relative;}
.inp-wrap .ico{
    position:absolute;left:13px;top:50%;
    transform:translateY(-50%);
    color:#475569;font-size:13px;
    pointer-events:none;transition:.2s;
}
.inp-wrap:focus-within .ico{color:#60bb46;}
.fld input{
    width:100%;
    padding:13px 13px 13px 40px;
    background:rgba(255,255,255,.04);
    border:1.5px solid rgba(255,255,255,.08);
    border-radius:11px;
    color:#f1f5f9;
    font-size:15px;font-weight:600;
    font-family:'Outfit',sans-serif;
    outline:none;transition:.25s;
    letter-spacing:.5px;
}
.fld input::placeholder{color:#374151;font-weight:500;letter-spacing:0;}
.fld input:focus{
    border-color:#60bb46;
    background:rgba(96,187,70,.05);
    box-shadow:0 0 0 4px rgba(96,187,70,.1);
}
.fld input.filled{border-color:rgba(96,187,70,.4);}

/* PIN DOTS */
.pin-row{
    display:flex;gap:10px;justify-content:center;
    margin:4px 0 8px;
}
.pin-dot{
    width:14px;height:14px;
    border-radius:50%;
    border:2px solid rgba(255,255,255,.15);
    transition:.2s;
}
.pin-dot.filled{
    background:#60bb46;
    border-color:#60bb46;
    box-shadow:0 0 8px rgba(96,187,70,.5);
}

/* MPIN INPUT */
.mpin-input{
    width:100%;
    padding:13px;
    background:rgba(255,255,255,.04);
    border:1.5px solid rgba(255,255,255,.08);
    border-radius:11px;
    color:#f1f5f9;
    font-size:22px;font-weight:800;
    font-family:'Outfit',sans-serif;
    outline:none;transition:.25s;
    text-align:center;
    letter-spacing:8px;
}
.mpin-input:focus{
    border-color:#60bb46;
    background:rgba(96,187,70,.05);
    box-shadow:0 0 0 4px rgba(96,187,70,.1);
}

/* PAY BUTTON */
.pay-btn{
    width:100%;
    padding:16px;
    background:linear-gradient(135deg,#3d8b2a,#60bb46);
    color:#fff;border:none;border-radius:14px;
    font-size:16px;font-weight:800;
    font-family:'Outfit',sans-serif;
    cursor:pointer;transition:.3s;
    box-shadow:0 10px 28px rgba(96,187,70,.3);
    display:flex;align-items:center;justify-content:center;gap:10px;
    margin-top:6px;
    letter-spacing:.2px;
    position:relative;overflow:hidden;
}
.pay-btn::before{
    content:'';
    position:absolute;inset:0;
    background:linear-gradient(90deg,transparent,rgba(255,255,255,.12),transparent);
    transform:translateX(-100%);
    transition:.6s;
}
.pay-btn:hover::before{transform:translateX(100%);}
.pay-btn:hover{transform:translateY(-3px);box-shadow:0 16px 36px rgba(96,187,70,.45);}
.pay-btn:active{transform:translateY(1px);}
.pay-btn:disabled{opacity:.5;cursor:not-allowed;transform:none;}

/* CANCEL */
.cancel-link{
    display:block;text-align:center;
    margin-top:14px;
    font-size:13px;font-weight:600;color:#475569;
    text-decoration:none;transition:.2s;
    cursor:pointer;
}
.cancel-link:hover{color:#ef4444;}

/* SECURITY ROW */
.sec-row{
    display:flex;align-items:center;justify-content:center;gap:14px;
    margin-top:18px;padding-top:14px;
    border-top:1px solid rgba(255,255,255,.05);
    font-size:11px;color:#374151;font-weight:600;
}
.sec-row i{color:#60bb46;font-size:12px;}

/* LOADING OVERLAY */
.loading-overlay{
    position:fixed;inset:0;
    background:rgba(10,15,26,.92);
    display:none;
    align-items:center;justify-content:center;
    flex-direction:column;
    gap:16px;
    z-index:999;
}
.loading-overlay.show{display:flex;}
.spinner{
    width:56px;height:56px;
    border:4px solid rgba(96,187,70,.15);
    border-top:4px solid #60bb46;
    border-radius:50%;
    animation:spin .7s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg);}}
.loading-text{font-size:15px;font-weight:700;color:#f1f5f9;}
.loading-sub{font-size:12px;color:#64748b;}

/* PROGRESS BAR */
.prog-bar-wrap{
    width:260px;height:4px;
    background:rgba(255,255,255,.06);
    border-radius:4px;overflow:hidden;
    margin-top:4px;
}
.prog-bar{
    height:100%;
    background:linear-gradient(90deg,#3d8b2a,#60bb46);
    border-radius:4px;
    width:0%;
    transition:width .1s linear;
}

/* DEMO HINT — prefill button */
.demo-fill-btn{
    display:flex;align-items:center;gap:7px;
    background:rgba(96,187,70,.08);
    border:1px dashed rgba(96,187,70,.3);
    border-radius:10px;
    padding:9px 14px;
    font-size:12px;font-weight:700;color:#60bb46;
    cursor:pointer;transition:.2s;
    margin-bottom:16px;
    width:100%;justify-content:center;
    font-family:'Outfit',sans-serif;
}
.demo-fill-btn:hover{background:rgba(96,187,70,.15);}

@media(max-width:480px){
    .gw-header{padding:18px 20px 14px;}
    .gw-body{padding:20px;}
    .amount-val{font-size:34px;}
}
</style>
</head>
<body>

<!-- PARTICLES -->
<?php for($i=0;$i<12;$i++): 
    $left = rand(0,100);
    $dur  = rand(8,18);
    $del  = rand(0,10);
    $size = rand(3,6);
?>
<div class="particle" style="left:<?php echo $left; ?>%;width:<?php echo $size; ?>px;height:<?php echo $size; ?>px;animation-duration:<?php echo $dur; ?>s;animation-delay:-<?php echo $del; ?>s;"></div>
<?php endfor; ?>

<!-- LOADING OVERLAY -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
    <div class="loading-text">Processing Payment…</div>
    <div class="loading-sub">Please wait, do not close this page</div>
    <div class="prog-bar-wrap"><div class="prog-bar" id="progBar"></div></div>
</div>

<!-- GATEWAY CARD -->
<div class="gateway-wrap">

    <!-- HEADER -->
    <div class="gw-header">
        <div class="esewa-logo">
            <div class="esewa-logo-icon">eS</div>
            <div class="esewa-logo-text">eSewa</div>
        </div>
        <div class="gw-secure"><i class="fa fa-lock"></i> Secured</div>
    </div>

    <!-- MERCHANT ROW -->
    <div class="merchant-row">
        <div class="merchant-name"><i class="fa fa-store"></i> SportGhar Nepal</div>
        <div class="merchant-order">Order #<?php echo $order_id_str; ?></div>
    </div>

    <!-- AMOUNT -->
    <div class="amount-box">
        <div class="amount-label">Amount to Pay</div>
        <div class="amount-val"><small>Rs.</small><?php echo number_format($amount); ?></div>
    </div>

    <!-- BODY -->
    <div class="gw-body">

        <!-- DEMO NOTICE -->
        <div class="demo-badge">
            <i class="fa fa-flask"></i>
            DEMO MODE — No real money will be charged
        </div>

        <!-- TABS -->
        <div class="pay-tabs">
            <div class="pay-tab active" onclick="switchTab(this,'mobile')">
                <i class="fa fa-mobile-alt"></i> Mobile No.
            </div>
            <div class="pay-tab" onclick="switchTab(this,'id')">
                <i class="fa fa-user"></i> eSewa ID
            </div>
        </div>

        <!-- AUTO-FILL DEMO -->
        <button class="demo-fill-btn" type="button" onclick="demoFill()">
            <i class="fa fa-wand-magic-sparkles"></i> Auto-fill Demo Credentials
        </button>

        <!-- MOBILE TAB CONTENT -->
        <div id="tab-mobile">
            <div class="fld">
                <label><i class="fa fa-mobile-alt" style="font-size:10px;"></i> eSewa Mobile Number</label>
                <div class="inp-wrap">
                    <i class="ico fa fa-phone"></i>
                    <input type="tel" id="mobileNo" placeholder="98XXXXXXXX" maxlength="10"
                           oninput="onMobileInput(this)">
                </div>
            </div>
        </div>

        <!-- ID TAB CONTENT -->
        <div id="tab-id" style="display:none;">
            <div class="fld">
                <label><i class="fa fa-user" style="font-size:10px;"></i> eSewa ID / Email</label>
                <div class="inp-wrap">
                    <i class="ico fa fa-user"></i>
                    <input type="text" id="esewaId" placeholder="9800000000 or user@esewa.com.np"
                           oninput="this.classList.toggle('filled',this.value.trim()!=='')">
                </div>
            </div>
        </div>

        <!-- MPIN -->
        <div class="fld" style="margin-top:4px;">
            <label><i class="fa fa-lock" style="font-size:10px;"></i> MPIN</label>
            <div class="pin-row" id="pinDots">
                <div class="pin-dot" id="d1"></div>
                <div class="pin-dot" id="d2"></div>
                <div class="pin-dot" id="d3"></div>
                <div class="pin-dot" id="d4"></div>
            </div>
            <input class="mpin-input" type="password" id="mpinInput"
                   placeholder="• • • •" maxlength="4"
                   oninput="onMpinInput(this)">
        </div>

        <!-- PAY BUTTON -->
        <button class="pay-btn" id="payBtn" onclick="processPayment()" disabled>
            <i class="fa fa-lock"></i>
            Pay Rs. <?php echo number_format($amount); ?>
        </button>

        <!-- CANCEL -->
        <a class="cancel-link" href="checkout.php">
            <i class="fa fa-arrow-left" style="font-size:11px;"></i> Cancel & Go Back
        </a>

        <!-- SECURITY -->
        <div class="sec-row">
            <span><i class="fa fa-shield-halved"></i> SSL Secured</span>
            <span><i class="fa fa-check-circle"></i> PCI Compliant</span>
            <span><i class="fa fa-eye-slash"></i> Encrypted</span>
        </div>

    </div>
</div>

<script>
var activeTab = 'mobile';
var mobile = '', mpin = '';
var orderAmount = <?php echo $amount; ?>;
var orderId     = <?php echo $order_id; ?>;

function switchTab(el, tab){
    document.querySelectorAll('.pay-tab').forEach(function(t){t.classList.remove('active');});
    el.classList.add('active');
    document.getElementById('tab-mobile').style.display = tab==='mobile'?'block':'none';
    document.getElementById('tab-id').style.display     = tab==='id'?'block':'none';
    activeTab = tab;
    checkReady();
}

function onMobileInput(inp){
    mobile = inp.value.trim();
    inp.classList.toggle('filled', mobile.length >= 7);
    checkReady();
}

function onMpinInput(inp){
    mpin = inp.value;
    // Update dots
    for(var i=1;i<=4;i++){
        document.getElementById('d'+i).classList.toggle('filled', i <= mpin.length);
    }
    checkReady();
}

function checkReady(){
    var idOk = (activeTab==='mobile')
        ? document.getElementById('mobileNo').value.trim().length >= 7
        : document.getElementById('esewaId').value.trim().length >= 5;
    var mpinOk = document.getElementById('mpinInput').value.length === 4;
    document.getElementById('payBtn').disabled = !(idOk && mpinOk);
}

function demoFill(){
    // Fill mobile
    document.getElementById('mobileNo').value = '9841234567';
    document.getElementById('mobileNo').classList.add('filled');
    // Fill MPIN
    document.getElementById('mpinInput').value = '1234';
    for(var i=1;i<=4;i++) document.getElementById('d'+i).classList.add('filled');
    checkReady();

    // Flash effect
    var btn = document.querySelector('.demo-fill-btn');
    btn.innerHTML = '<i class="fa fa-check"></i> Credentials filled!';
    btn.style.background = 'rgba(96,187,70,.2)';
    setTimeout(function(){
        btn.innerHTML = '<i class="fa fa-wand-magic-sparkles"></i> Auto-fill Demo Credentials';
        btn.style.background = '';
    }, 1800);
}

function processPayment(){
    // Validate
    var idOk = (activeTab==='mobile')
        ? document.getElementById('mobileNo').value.trim().length >= 7
        : document.getElementById('esewaId').value.trim().length >= 5;
    var mpinOk = document.getElementById('mpinInput').value.length === 4;
    if(!idOk || !mpinOk) return;

    // Show loading
    var overlay = document.getElementById('loadingOverlay');
    overlay.classList.add('show');

    // Progress bar animation
    var prog = 0;
    var progBar = document.getElementById('progBar');
    var interval = setInterval(function(){
        prog += (prog < 70) ? Math.random()*8 : Math.random()*2;
        if(prog > 92) prog = 92;
        progBar.style.width = prog + '%';
    }, 150);

    // After 2.8s simulate success → redirect to success page
    setTimeout(function(){
        clearInterval(interval);
        progBar.style.width = '100%';
        setTimeout(function(){
            window.location.href = 'esewa_success.php?order_id=<?php echo $order_id; ?>&amount=<?php echo $amount; ?>&status=success';
        }, 400);
    }, 2800);
}
</script>

</body>
</html>