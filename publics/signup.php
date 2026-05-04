<?php
if(session_status() === PHP_SESSION_NONE) session_start();
include "../databases/db.php";
$conn = getDB();

if(!$conn){
    die("Database connection failed");
}


if(isset($_SESSION['user']['id'])){
    $redirect = $_GET['redirect'] ?? '../publics/index.php';
    header("Location: " . urldecode($redirect));
    exit;
}

$error    = '';
$success  = '';
$redirect = $_GET['redirect'] ?? '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name    = trim($conn->real_escape_string($_POST['name']     ?? ''));
    $email   = trim($conn->real_escape_string($_POST['email']    ?? ''));
    $phone   = trim($conn->real_escape_string($_POST['phone']    ?? ''));
    $pass    = $_POST['password']         ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if(!$name || !$email || !$pass || !$confirm){
        $error = "Sabai required fields fill garnu parcha!";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $error = "Valid email address dinus.";
    } elseif(strlen($pass) < 6){
        $error = "Password kam se kam 6 characters ko hunu parcha.";
    } elseif($pass !== $confirm){
        $error = "Password ra Confirm Password match bhayena!";
    } else {
        $chk = $conn->query("SELECT id FROM users WHERE email='$email' LIMIT 1");
        if($chk && $chk->num_rows > 0){
            $error = "Yō email already registered chha. Login garnus!";
        } else {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $conn->query("INSERT INTO users (name, email, phone, password, role, created_at)
                          VALUES ('$name','$email','$phone','$hashed','user', NOW())");
            $new_id = $conn->insert_id;

            $_SESSION['user'] = [
                'id'    => $new_id,
                'name'  => $name,
                'email' => $email,
                'role'  => 'user',
            ];
            $go = !empty($_POST['redirect']) ? urldecode($_POST['redirect']) : '../publics/index.php';
            header("Location:  index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — JerseyGhar</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
    --orange:#c94b01;
    --orange-dark:#a33b00;
    --orange-light:#f97316;
    --cream:#fff8f2;
    --ink:#1a0a00;
    --ink-2:#5c3017;
    --muted:#a87050;
}
body{
    font-family:'Barlow',sans-serif;
    background:var(--orange);
    min-height:100vh;
    display:flex;
    align-items:stretch;
    overflow:hidden;
}

/* FORM PANEL */
.left-panel{
    width:480px;
    flex-shrink:0;
    background:var(--cream);
    display:flex;
    flex-direction:column;
    justify-content:center;
    padding:40px 44px;
    position:relative;
    overflow-y:auto;
    animation:slideRight 0.55s cubic-bezier(0.22,1,0.36,1) both;
}
@keyframes slideRight{
    from{opacity:0;transform:translateX(-40px);}
    to{opacity:1;transform:translateX(0);}
}
.left-panel::after{
    content:'';
    position:absolute;top:0;right:0;
    width:4px;height:100%;
    background:linear-gradient(180deg,var(--orange-light),var(--orange),var(--orange-dark));
}

/* BRAND PANEL */
.right-panel{
    flex:1;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    padding:60px 48px;
    position:relative;
    overflow:hidden;
}
.deco-num{
    position:absolute;
    font-family:'Barlow Condensed',sans-serif;
    font-size:clamp(200px,30vw,380px);
    font-weight:900;
    color:rgba(255,255,255,0.05);
    line-height:1;
    top:50%;left:50%;
    transform:translate(-50%,-50%);
    pointer-events:none;user-select:none;
    letter-spacing:-10px;
}
.brand{
    position:relative;z-index:2;
    text-align:center;
    animation:fadeUp 0.7s ease both;
    display:flex;
    flex-direction:column;
    align-items:center;
}
@keyframes fadeUp{
    from{opacity:0;transform:translateY(28px);}
    to{opacity:1;transform:translateY(0);}
}

/* JERSEY LOGO SVG */
.jersey-logo-wrap{
    margin-bottom:18px;
    filter:drop-shadow(0 8px 24px rgba(0,0,0,0.2));
}

.brand-name{
    font-family:'Barlow Condensed',sans-serif;
    font-size:48px;font-weight:900;
    color:#fff;letter-spacing:-1px;
    line-height:1;text-transform:uppercase;
}
.brand-name span{
    color:#fde68a;display:block;
    font-size:18px;letter-spacing:7px;font-weight:700;
    margin-top:2px;
}
.brand-tagline{
    margin-top:16px;font-size:14.5px;
    color:rgba(255,255,255,0.7);font-weight:500;
    max-width:280px;line-height:1.6;text-align:center;
}
.perks{
    margin-top:32px;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
    width:100%;
    max-width:320px;
}
.perk{
    background:rgba(255,255,255,0.1);
    border:1px solid rgba(255,255,255,0.18);
    border-radius:14px;
    padding:14px 12px;
    text-align:center;
    animation:fadeUp 0.7s ease both;
    backdrop-filter:blur(6px);
}
.perk:nth-child(1){animation-delay:.1s;}
.perk:nth-child(2){animation-delay:.2s;}
.perk:nth-child(3){animation-delay:.3s;}
.perk:nth-child(4){animation-delay:.4s;}
.perk-icon{font-size:22px;margin-bottom:7px;}
.perk-text{font-size:12px;font-weight:700;color:#fff;line-height:1.3;}
.perk-text small{display:block;color:rgba(255,255,255,0.5);font-weight:400;font-size:10.5px;margin-top:2px;}

/* FORM */
.form-top{margin-bottom:22px;}
.form-eyebrow{
    font-size:11px;font-weight:700;
    letter-spacing:2.5px;text-transform:uppercase;
    color:var(--orange);margin-bottom:6px;
}
.form-title{
    font-family:'Barlow Condensed',sans-serif;
    font-size:36px;font-weight:900;
    color:var(--ink);line-height:1;
    text-transform:uppercase;letter-spacing:-0.5px;
}
.form-title span{color:var(--orange);}
.form-sub{font-size:13px;color:var(--muted);margin-top:6px;font-weight:500;}

.toast{
    display:flex;align-items:flex-start;gap:10px;
    padding:12px 16px;border-radius:12px;
    font-size:13px;font-weight:600;margin-bottom:16px;
    animation:fadeUp .3s ease;
}
.toast.error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;}
.toast.success{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;}
.toast i{margin-top:1px;flex-shrink:0;}

.form-row{display:flex;gap:12px;}
.form-row .form-group{flex:1;}
.form-group{margin-bottom:14px;}
.form-group label{
    display:block;font-size:10.5px;font-weight:700;
    letter-spacing:1px;text-transform:uppercase;
    color:var(--ink-2);margin-bottom:5px;
}
.input-wrap{position:relative;}
.input-wrap i.ico{
    position:absolute;left:13px;top:50%;transform:translateY(-50%);
    color:var(--muted);font-size:12px;pointer-events:none;
}
.input-wrap input{
    width:100%;
    padding:11px 14px 11px 38px;
    border:2px solid #e8d5c4;
    border-radius:11px;
    font-size:13.5px;font-weight:500;
    font-family:'Barlow',sans-serif;
    color:var(--ink);background:#fff;
    outline:none;transition:all 0.25s;
}
.input-wrap input:focus{
    border-color:var(--orange);
    box-shadow:0 0 0 3px rgba(201,75,1,0.1);
}
.input-wrap input::placeholder{color:#c4a892;font-weight:400;}
.eye-btn{
    position:absolute;right:12px;top:50%;transform:translateY(-50%);
    background:none;border:none;cursor:pointer;
    color:var(--muted);font-size:12px;padding:4px;transition:color 0.2s;
}
.eye-btn:hover{color:var(--orange);}

.strength-wrap{margin-top:6px;}
.strength-bar{height:3px;border-radius:4px;background:#e8d5c4;overflow:hidden;position:relative;}
.strength-fill{height:100%;width:0;border-radius:4px;transition:width .3s,background .3s;}
.strength-label{font-size:10.5px;font-weight:600;color:var(--muted);margin-top:3px;}

.terms{
    display:flex;align-items:flex-start;gap:9px;
    font-size:12px;color:var(--muted);margin-bottom:16px;
    line-height:1.5;
}
.terms input[type=checkbox]{
    width:15px;height:15px;accent-color:var(--orange);
    margin-top:1px;cursor:pointer;flex-shrink:0;
}
.terms a{color:var(--orange);font-weight:700;text-decoration:none;}
.terms a:hover{text-decoration:underline;}

.btn-submit{
    width:100%;padding:13px;
    background:linear-gradient(135deg,var(--orange-light),var(--orange));
    color:#fff;border:none;border-radius:13px;
    font-size:15px;font-weight:800;
    font-family:'Barlow Condensed',sans-serif;
    letter-spacing:1px;text-transform:uppercase;
    cursor:pointer;transition:all 0.25s;
    display:flex;align-items:center;justify-content:center;gap:10px;
    box-shadow:0 6px 20px rgba(201,75,1,0.3);
}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(201,75,1,0.4);}
.btn-submit:active{transform:translateY(0);}

.divider{
    display:flex;align-items:center;gap:12px;
    margin:16px 0;font-size:12px;color:var(--muted);font-weight:600;
}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:#e8d5c4;}

.login-link{text-align:center;font-size:13.5px;color:var(--muted);font-weight:500;}
.login-link a{color:var(--orange);font-weight:800;text-decoration:none;}
.login-link a:hover{text-decoration:underline;}

.back-link{
    display:inline-flex;align-items:center;gap:6px;
    font-size:12px;color:var(--muted);font-weight:600;
    text-decoration:none;margin-bottom:22px;transition:color 0.2s;
}
.back-link:hover{color:var(--orange);}
.back-link i{font-size:10px;}

@media(max-width:860px){
    body{flex-direction:column-reverse;overflow-y:auto;}
    .left-panel{width:100%;padding:36px 24px 52px;}
    .right-panel{padding:36px 24px;min-height:240px;}
    .perks{grid-template-columns:1fr 1fr;}
    .form-row{flex-direction:column;gap:0;}
}
</style>
</head>
<body>

<!-- FORM PANEL -->
<div class="left-panel">
    <a href="../publics/index.php" class="back-link">
        <i class="fa fa-arrow-left"></i> Store ma farkanus
    </a>

    <div class="form-top">
        <div class="form-eyebrow">Join JerseyGhar</div>
        <div class="form-title">Naya <span>Account</span></div>
        <div class="form-sub">Register garnus ra Nepal ko best jersey store ko member banus!</div>
    </div>

    <?php if($error): ?>
    <div class="toast error">
        <i class="fa fa-circle-exclamation"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

        <div class="form-row">
            <div class="form-group">
                <label>Full Name *</label>
                <div class="input-wrap">
                    <i class="fa fa-user ico"></i>
                    <input type="text" name="name"
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                           placeholder="Aarav Sharma" required>
                </div>
            </div>
            <div class="form-group">
                <label>Phone</label>
                <div class="input-wrap">
                    <i class="fa fa-phone ico"></i>
                    <input type="text" name="phone"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                           placeholder="98XXXXXXXX">
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Email Address *</label>
            <div class="input-wrap">
                <i class="fa fa-envelope ico"></i>
                <input type="email" name="email"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       placeholder="tapai@email.com" required>
            </div>
        </div>

        <div class="form-group">
            <label>Password *</label>
            <div class="input-wrap">
                <i class="fa fa-lock ico"></i>
                <input type="password" name="password" id="regPass"
                       placeholder="Min. 6 characters" required
                       oninput="checkStrength(this.value)">
                <button type="button" class="eye-btn" onclick="togglePass('regPass',this)">
                    <i class="fa fa-eye"></i>
                </button>
            </div>
            <div class="strength-wrap">
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <div class="strength-label" id="strengthLabel">Password haln suru garnus</div>
            </div>
        </div>

        <div class="form-group">
            <label>Confirm Password *</label>
            <div class="input-wrap">
                <i class="fa fa-lock ico"></i>
                <input type="password" name="confirm_password" id="confPass"
                       placeholder="Password repeat garnus" required>
                <button type="button" class="eye-btn" onclick="togglePass('confPass',this)">
                    <i class="fa fa-eye"></i>
                </button>
            </div>
        </div>

        <label class="terms">
            <input type="checkbox" name="terms" required>
            <span>
                Ma <a href="#">Terms &amp; Conditions</a> ra
                <a href="#">Privacy Policy</a> maan garchu.
            </span>
        </label>

        <button type="submit" class="btn-submit">
            <i class="fa fa-user-plus"></i> Account Banaunus
        </button>
    </form>

    <div class="divider">ya</div>

    <div class="login-link">
        Account chha? &nbsp;
        <a href="login.php<?php echo $redirect ? '?redirect='.urlencode($redirect) : ''; ?>">
            Login Garnus →
        </a>
    </div>
</div>

<!-- BRAND PANEL -->
<div class="right-panel">
    <div class="deco-num">7</div>
    <div class="brand">
        <!-- Jersey SVG Logo -->
        <div class="jersey-logo-wrap">
            <svg width="110" height="110" viewBox="0 0 110 110" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Jersey shape -->
                <path d="M20 28 L8 50 L24 54 L24 95 L86 95 L86 54 L102 50 L90 28 L72 38 C68 22 42 22 38 38 Z" fill="rgba(255,255,255,0.15)" stroke="rgba(255,255,255,0.5)" stroke-width="2.5" stroke-linejoin="round"/>
                <!-- Collar -->
                <path d="M38 38 Q42 48 55 48 Q68 48 72 38" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="2"/>
                <!-- Chest stripe -->
                <path d="M24 62 L86 62" stroke="rgba(255,255,255,0.25)" stroke-width="8"/>
                <!-- Number on jersey -->
                <text x="55" y="84" font-family="'Barlow Condensed',sans-serif" font-size="22" font-weight="900" fill="rgba(255,255,255,0.9)" text-anchor="middle" letter-spacing="-1">JG</text>
                <!-- Shoulder detail left -->
                <path d="M24 54 L24 44" stroke="rgba(253,230,138,0.6)" stroke-width="2"/>
                <!-- Shoulder detail right -->
                <path d="M86 54 L86 44" stroke="rgba(253,230,138,0.6)" stroke-width="2"/>
            </svg>
        </div>

        <div class="brand-name">
            Jersey<span>GHAR</span>
        </div>
        <div class="brand-tagline">
            Account banaunu free chha! Order tracking, exclusive deals, ra dherai benefits paunus!
        </div>

        <div class="perks">
            <div class="perk">
                <div class="perk-icon">📦</div>
                <div class="perk-text">Order Tracking
                    <small>Real-time updates</small>
                </div>
            </div>
            <div class="perk">
                <div class="perk-icon">🎁</div>
                <div class="perk-text">Exclusive Deals
                    <small>Members only offers</small>
                </div>
            </div>
            <div class="perk">
                <div class="perk-icon">⚡</div>
                <div class="perk-text">Fast Checkout
                    <small>Saved address & info</small>
                </div>
            </div>
            <div class="perk">
                <div class="perk-icon">🏆</div>
                <div class="perk-text">Loyalty Points
                    <small>Har order ma earn garnus</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePass(id, btn){
    const inp = document.getElementById(id);
    const ico = btn.querySelector('i');
    if(inp.type === 'password'){
        inp.type = 'text';
        ico.className = 'fa fa-eye-slash';
    } else {
        inp.type = 'password';
        ico.className = 'fa fa-eye';
    }
}

function checkStrength(val){
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    if(!val){ fill.style.width='0'; label.textContent='Password haln suru garnus'; fill.style.background=''; return; }
    let score = 0;
    if(val.length >= 6)  score++;
    if(val.length >= 10) score++;
    if(/[A-Z]/.test(val)) score++;
    if(/[0-9]/.test(val)) score++;
    if(/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        {w:'20%',bg:'#ef4444',txt:'Dherai kamjor 🔴'},
        {w:'40%',bg:'#f97316',txt:'Kamjor 🟠'},
        {w:'60%',bg:'#eab308',txt:'Thikai 🟡'},
        {w:'80%',bg:'#22c55e',txt:'Ramro 🟢'},
        {w:'100%',bg:'#15803d',txt:'Dherai strong 💪'},
    ];
    const lv = levels[Math.min(score-1, 4)];
    fill.style.width      = lv.w;
    fill.style.background = lv.bg;
    label.textContent     = lv.txt;
    label.style.color     = lv.bg;
}

document.getElementById('confPass').addEventListener('input', function(){
    const pass = document.getElementById('regPass').value;
    if(!this.value){ this.style.borderColor=''; this.style.boxShadow=''; return; }
    if(pass === this.value){
        this.style.borderColor = '#22c55e';
        this.style.boxShadow   = '0 0 0 3px rgba(34,197,94,0.1)';
    } else {
        this.style.borderColor = '#ef4444';
        this.style.boxShadow   = '0 0 0 3px rgba(239,68,68,0.1)';
    }
});
</script>
</body>
</html>