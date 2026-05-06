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
    $email = trim($conn->real_escape_string($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    if(!$email || !$pass){
        $error = "Email ra password fill garnu parcha.";
    } else {
        $res = $conn->query("SELECT id, name, email, password, role, is_blocked FROM users WHERE email='$email' LIMIT 1");
        if($res && $res->num_rows > 0){
            $user = $res->fetch_assoc();
            
            if($user['is_blocked'] == 1){
                $error = "⚠️ Your account has been blocked by admin. Please contact support for more information.";
            } 
            elseif(password_verify($pass, $user['password'])){
                $_SESSION['user'] = [
                    'id'         => $user['id'],
                    'name'       => $user['name'],
                    'email'      => $user['email'],
                    'role'       => $user['role'] ?? 'user',
                    'is_blocked' => $user['is_blocked']
                ];
                $go = !empty($_POST['redirect']) ? urldecode($_POST['redirect']) : '../publics/index.php';
                header("Location: " . $go);
                exit;
            } else {
                $error = "Password galat chha. Pheri try garnus!";
            }
        } else {
            $error = "Yō email registered chaina. Pehile register garnus!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Login — PlayZo</title>
<link rel="shortcut icon" href="../img_logo/cropped_circle_image.png" type="image/x-icon">
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">


<!-- Seo here -->
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

/* LEFT PANEL — FULL BACKGROUND IMAGE + LOGO */
.left-panel{
    flex:1;
    position:relative;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    padding:10px 48px;
    overflow:hidden;
    background: url('../img_logo/logo6.jpg') no-repeat center center;
    background-size: cover;
}
/* Dark overlay for readability */
.left-panel::before{
    content:'';
    position:absolute;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(27, 25, 25, 0.36);
    backdrop-filter:blur(2px);
    z-index:1;
}
.brand{
    position:relative;
    z-index:2;
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

/* Logo image */
.login-logo-img{
    display:inline-block;
    max-width:140px;
    margin-bottom:20px;
    filter:drop-shadow(0 8px 24px rgba(0,0,0,0.3));
    transition:transform 0.25s ease;
}
.login-logo-img:hover{
    transform:scale(1.02);
}

.brand-name{
    font-family:'Barlow Condensed',sans-serif;
    font-size:52px;font-weight:900;
    color:#fff;letter-spacing:-1px;
    line-height:1;text-transform:uppercase;
    text-shadow:0 2px 12px rgba(0,0,0,0.3);
}
.brand-name span{
    color:#fde68a;display:block;
    font-size:18px;letter-spacing:7px;font-weight:700;
    margin-top:2px;
}
.brand-tagline{
    margin-top:18px;font-size:14.5px;
    color:rgba(255,255,255,0.85);font-weight:500;
    max-width:280px;line-height:1.6;text-align:center;
    background:rgba(0,0,0,0.3);
    backdrop-filter:blur(4px);
    padding:8px 16px;
    border-radius:40px;
}
.feat-list{
    margin-top:34px;
    display:flex;
    flex-direction:column;
    gap:12px;
    width:100%;
    max-width:300px;
}
.feat-item{
    display:flex;align-items:center;gap:12px;
    background:rgba(0,0,0,0.4);
    backdrop-filter:blur(8px);
    border:1px solid rgba(255,255,255,0.2);
    border-radius:14px;
    padding:12px 16px;
    animation:fadeUp 0.7s ease both;
}
.feat-item:nth-child(1){animation-delay:.1s;}
.feat-item:nth-child(2){animation-delay:.2s;}
.feat-item:nth-child(3){animation-delay:.3s;}
.feat-icon{
    width:36px;height:36px;border-radius:10px;
    background:rgba(253,230,138,0.2);
    border:1px solid rgba(253,230,138,0.4);
    display:flex;align-items:center;justify-content:center;
    font-size:15px;color:#fde68a;flex-shrink:0;
}
.feat-text{font-size:13px;color:#fff;font-weight:600;line-height:1.35;}
.feat-text small{display:block;color:rgba(255,255,255,0.7);font-weight:400;font-size:11.5px;}

/* RIGHT PANEL — FORM */
.right-panel{
    width:560px;
    flex-shrink:0;
    background:var(--cream);
    display:flex;
    flex-direction:column;
    justify-content:center;
    padding:60px 44px;
    position:relative;
    overflow-y:auto;
    animation:slideLeft 0.55s cubic-bezier(0.22,1,0.36,1) both;
}
@keyframes slideLeft{
    from{opacity:0;transform:translateX(40px);}
    to{opacity:1;transform:translateX(0);}
}
.right-panel::before{
    content:'';
    position:absolute;top:0;left:0;
    width:4px;height:100%;
    background:linear-gradient(180deg,var(--orange-light),var(--orange),var(--orange-dark));
}

.form-top{margin-bottom:34px;}
.form-eyebrow{
    font-size:11px;font-weight:700;
    letter-spacing:2.5px;text-transform:uppercase;
    color:var(--orange);margin-bottom:8px;
}
.form-title{
    font-family:'Barlow Condensed',sans-serif;
    font-size:40px;font-weight:900;
    color:var(--ink);line-height:1;
    text-transform:uppercase;letter-spacing:-0.5px;
}
.form-title span{color:var(--orange);}
.form-sub{font-size:13.5px;color:var(--muted);margin-top:8px;font-weight:500;}

.toast{
    display:flex;align-items:flex-start;gap:10px;
    padding:13px 16px;border-radius:12px;
    font-size:13.5px;font-weight:600;margin-bottom:20px;
    animation:fadeUp .3s ease;
}
.toast.error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;}
.toast.success{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;}
.toast i{margin-top:1px;flex-shrink:0;}

.form-group{margin-bottom:18px;}
.form-group label{
    display:block;font-size:11px;font-weight:700;
    letter-spacing:1px;text-transform:uppercase;
    color:var(--ink-2);margin-bottom:7px;
}
.input-wrap{position:relative;}
.input-wrap i.ico{
    position:absolute;left:14px;top:50%;transform:translateY(-50%);
    color:var(--muted);font-size:13px;pointer-events:none;
}
.input-wrap input{
    width:100%;
    padding:13px 16px 13px 42px;
    border:2px solid #e8d5c4;
    border-radius:12px;
    font-size:14px;font-weight:500;
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
    position:absolute;right:14px;top:50%;transform:translateY(-50%);
    background:none;border:none;cursor:pointer;
    color:var(--muted);font-size:13px;padding:4px;transition:color 0.2s;
}
.eye-btn:hover{color:var(--orange);}

.form-extras{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:24px;flex-wrap:wrap;gap:8px;
}
.remember{
    display:flex;align-items:center;gap:8px;
    font-size:13px;color:var(--ink-2);font-weight:500;cursor:pointer;
}
.remember input[type=checkbox]{width:16px;height:16px;accent-color:var(--orange);cursor:pointer;}
.forgot{
    font-size:13px;font-weight:700;color:var(--orange);
    text-decoration:none;transition:opacity 0.2s;
}
.forgot:hover{opacity:0.75;text-decoration:underline;}

.btn-submit{
    width:100%;padding:15px;
    background:linear-gradient(135deg,var(--orange-light),var(--orange));
    color:#fff;border:none;border-radius:14px;
    font-size:16px;font-weight:800;
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
    margin:22px 0;font-size:12px;color:var(--muted);font-weight:600;
}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:#e8d5c4;}

.reg-link{text-align:center;font-size:14px;color:var(--muted);font-weight:500;}
.reg-link a{color:var(--orange);font-weight:800;text-decoration:none;}
.reg-link a:hover{text-decoration:underline;}

.back-link{
    display:inline-flex;align-items:center;gap:6px;
    font-size:12.5px;color:var(--muted);font-weight:600;
    text-decoration:none;margin-bottom:32px;transition:color 0.2s;
}
.back-link:hover{color:var(--orange);}
.back-link i{font-size:10px;}

.trust-badges{
    display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;
}
.badge{
    display:inline-flex;align-items:center;gap:5px;
    font-size:11px;font-weight:700;
    color:var(--orange-dark);
    background:#ffe8d4;
    border:1px solid #ffd0b0;
    border-radius:20px;
    padding:4px 10px;
}
.badge i{font-size:9px;}

@media(max-width:860px){
    body{flex-direction:column;overflow-y:auto;}
    .left-panel{padding:36px 24px;min-height:260px;}
    .right-panel{width:100%;padding:36px 24px 52px;}
    .feat-list{flex-direction:row;flex-wrap:wrap;max-width:100%;}
    .feat-item{flex:1;min-width:180px;}
    .login-logo-img{max-width:100px;}
    .brand-name{font-size:40px;}
}
</style>
</head>
<body>

<!-- LEFT PANEL: BACKGROUND IMAGE + LOGO + BRANDING -->
<div class="left-panel">
    <div class="brand">
        <!-- Logo image added -->
        <img class="login-logo-img" src="../img_logo/playzo.w.png" alt="PlayZo Nepal Logo">
        <div class="feat-list">
            <div class="feat-item"><div class="feat-icon"><i class="fa fa-shield-alt"></i></div><div class="feat-text">100% Authentic<small>Original quality guaranteed</small></div></div>
            <div class="feat-item"><div class="feat-icon"><i class="fa fa-truck"></i></div><div class="feat-text">Fast Delivery<small>Kathmandu Valley same day</small></div></div>
            <div class="feat-item"><div class="feat-icon"><i class="fa fa-undo"></i></div><div class="feat-text">Easy Returns<small>7 days return policy</small></div></div>
        </div>
    </div>
</div>

<!-- RIGHT PANEL: LOGIN FORM -->
<div class="right-panel">
    <a href="../publics/index.php" class="back-link"><i class="fa fa-arrow-left"></i> Back to Store</a>

    <div class="form-top">
        <div class="form-eyebrow">Welcome Back</div>
        <div class="form-title">Account <span>Login</span></div>
        <div class="form-sub">Login to your account to view orders and shop faster!</div>
        <div class="trust-badges">
            <span class="badge"><i class="fa fa-lock"></i> Secure Login</span>
            <span class="badge"><i class="fa fa-star"></i> Nepal #1 Store</span>
        </div>
    </div>

    <?php if($error): ?>
    <div class="toast error">
        <i class="fa fa-circle-exclamation"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <?php if($success): ?>
    <div class="toast success">
        <i class="fa fa-check-circle"></i>
        <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

        <div class="form-group">
            <label>Email Address</label>
            <div class="input-wrap">
                <i class="fa fa-envelope ico"></i>
                <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="you@example.com" required>
            </div>
        </div>

        <div class="form-group">
            <label>Password</label>
            <div class="input-wrap">
                <i class="fa fa-lock ico"></i>
                <input type="password" name="password" id="loginPass" placeholder="••••••••" required>
                <button type="button" class="eye-btn" onclick="togglePass('loginPass', this)"><i class="fa fa-eye"></i></button>
            </div>
        </div>

        <div class="form-extras">
            <label class="remember"><input type="checkbox" name="remember"> Remember Me</label>
            <a href="forgot_password.php" class="forgot">Forgot Password?</a>
        </div>

        <button type="submit" class="btn-submit"><i class="fa fa-sign-in-alt"></i> Login</button>
    </form>

    <div class="divider">or</div>
    <div class="reg-link">Don't have an account? &nbsp;<a href="singup.php<?php echo $redirect ? '?redirect='.urlencode($redirect) : ''; ?>">Create Account →</a></div>
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
</script>
</body>
</html>