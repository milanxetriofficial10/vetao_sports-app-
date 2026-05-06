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
    $name        = trim($conn->real_escape_string($_POST['name'] ?? ''));
    $email       = trim($conn->real_escape_string($_POST['email'] ?? ''));
    $country_code = trim($conn->real_escape_string($_POST['country_code'] ?? '+977'));
    $phone_local = trim($conn->real_escape_string($_POST['phone'] ?? ''));
    $phone       = $country_code . $phone_local;
    $pass        = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';

    if(!$name || !$email || !$pass || !$confirm){
        $error = "All required fields must be filled!";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $error = "Please enter a valid email address.";
    } elseif(strlen($pass) < 6){
        $error = "Password must be at least 6 characters.";
    } elseif($pass !== $confirm){
        $error = "Password and Confirm Password do not match!";
    } elseif(!empty($phone_local) && !preg_match('/^[0-9]{7,15}$/', $phone_local)){
        $error = "Phone number must contain 7 to 15 digits (only numbers).";
    } else {
        // Check if name already exists
        $nameCheck = $conn->query("SELECT id FROM users WHERE name='$name' LIMIT 1");
        if($nameCheck && $nameCheck->num_rows > 0){
            $error = "This name is already taken. Please choose another name.";
        }
        // Check if email already exists
        elseif($chk = $conn->query("SELECT id FROM users WHERE email='$email' LIMIT 1") and $chk->num_rows > 0){
            $error = "This email is already registered. Please login!";
        }
        // Check if phone already exists (if phone is provided)
        elseif(!empty($phone) && ($phoneCheck = $conn->query("SELECT id FROM users WHERE phone='$phone' LIMIT 1")) && $phoneCheck->num_rows > 0){
            $error = "This phone number is already associated with an account.";
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
            header("Location: index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Register — PlayZo</title>
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
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --orange: #c94b01;
    --orange-dark: #a33b00;
    --orange-light: #f97316;
    --cream: #fff8f2;
    --ink: #1a0a00;
    --ink-2: #5c3017;
    --muted: #a87050;
}

body {
    font-family: 'Barlow', sans-serif;
    background: var(--orange);
    min-height: 100vh;
    display: flex;
    align-items: stretch;
    overflow: hidden;
}

/* LEFT FORM PANEL */
.left-panel {
    width: 43%;
    flex-shrink: 0;
    background: var(--cream);
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 40px 44px;
    position: relative;
    overflow-y: auto;
    animation: slideRight 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes slideRight {
    from {
        opacity: 0;
        transform: translateX(-40px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.left-panel::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, var(--orange-light), var(--orange), var(--orange-dark));
}

/* RIGHT PANEL — FULL BACKGROUND IMAGE */
.right-panel {
    flex: 1;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 60px 48px;
    overflow: hidden;
    background: url('../img_logo/singuplogo.jpg') no-repeat center center;
    background-size: cover;
}

/* Dark overlay for readability */
.right-panel::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.55);
    backdrop-filter: blur(2px);
    z-index: 1;
}

.right-panel .brand-content {
    position: relative;
    z-index: 2;
    text-align: center;
    max-width: 400px;
    animation: fadeUp 0.7s ease both;
}

/* Logo image styling */
.logo-img {
    display: inline-block;
    max-width: 152px;
    height: auto;
    filter: drop-shadow(0 8px 20px rgba(90, 240, 3, 0.99));
    transition: transform 0.25s ease;
}

.logo-img:hover {
    transform: scale(1.02);
}

.right-logo {
    margin-bottom: 30px;
}

.benefits-tagline {
    font-size: 18px;
    width: 100%;
    font-weight: 600;
    color: #fff;
    background: rgba(0, 0, 0, 0.33);
    backdrop-filter: blur(8px);
    padding: 16px 28px;
    border-radius: 0px;
    line-height: 1.4;
    letter-spacing: -0.2px;
    border: 1px solid rgba(87, 235, 42, 0.95);
}

.benefits-tagline i {
    margin-right: 8px;
    color: #fde68a;
}

@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(28px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* FORM STYLES */
.form-top {
    margin-bottom: 22px;
}

.form-eyebrow {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    color: var(--orange);
    margin-bottom: 6px;
}

.form-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 36px;
    font-weight: 900;
    color: var(--ink);
    line-height: 1;
    text-transform: uppercase;
    letter-spacing: -0.5px;
}

.form-title span {
    color: var(--orange);
}

.form-sub {
    font-size: 13px;
    color: var(--muted);
    margin-top: 6px;
    font-weight: 500;
}

.toast {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 16px;
    animation: fadeUp .3s ease;
}

.toast.error {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.toast i {
    margin-top: 1px;
    flex-shrink: 0;
}

.form-row-2cols {
    display: flex;
    gap: 12px;
    margin-bottom: 14px;
}
.form-row-2cols .form-group {
    flex: 1;
    margin-bottom: 0;
}

.form-group {
    margin-bottom: 14px;
}
.form-group label {
    display: block;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--ink-2);
    margin-bottom: 5px;
}
.input-wrap {
    position: relative;
}
.input-wrap i.ico {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--muted);
    font-size: 12px;
    pointer-events: none;
}
.input-wrap input, .input-wrap select {
    width: 100%;
    padding: 11px 14px 11px 38px;
    border: 2px solid #e8d5c4;
    border-radius: 11px;
    font-size: 13.5px;
    font-weight: 500;
    font-family: 'Barlow', sans-serif;
    color: var(--ink);
    background: #fff;
    outline: none;
    transition: all 0.25s;
}
.input-wrap select {
    padding: 11px 14px 11px 38px;
    appearance: none;
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="%23a87050" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>');
    background-repeat: no-repeat;
    background-position: right 14px center;
}
.input-wrap input:focus, .input-wrap select:focus {
    border-color: var(--orange);
    box-shadow: 0 0 0 3px rgba(201, 75, 1, 0.1);
}
.phone-group {
    display: flex;
    gap: 10px;
}
.phone-group .country-code {
    width: 120px;
    flex-shrink: 0;
}
.phone-group .phone-number {
    flex: 1;
}
.input-wrap input::placeholder {
    color: #c4a892;
    font-weight: 400;
}
.eye-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: var(--muted);
    font-size: 12px;
    padding: 4px;
    transition: color 0.2s;
}
.eye-btn:hover {
    color: var(--orange);
}
.strength-wrap {
    margin-top: 6px;
}
.strength-bar {
    height: 3px;
    border-radius: 4px;
    background: #e8d5c4;
    overflow: hidden;
    position: relative;
}
.strength-fill {
    height: 100%;
    width: 0;
    border-radius: 4px;
    transition: width .3s, background .3s;
}
.strength-label {
    font-size: 10.5px;
    font-weight: 600;
    color: var(--muted);
    margin-top: 3px;
}
.terms {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    font-size: 12px;
    color: var(--muted);
    margin-bottom: 16px;
    line-height: 1.5;
}
.terms input[type=checkbox] {
    width: 15px;
    height: 15px;
    accent-color: var(--orange);
    margin-top: 1px;
    cursor: pointer;
    flex-shrink: 0;
}
.terms a {
    color: var(--orange);
    font-weight: 700;
    text-decoration: none;
}
.terms a:hover {
    text-decoration: underline;
}
.btn-submit {
    width: 100%;
    padding: 13px;
    background: linear-gradient(135deg, var(--orange-light), var(--orange));
    color: #fff;
    border: none;
    border-radius: 13px;
    font-size: 15px;
    font-weight: 800;
    font-family: 'Barlow Condensed', sans-serif;
    letter-spacing: 1px;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.25s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 6px 20px rgba(201, 75, 1, 0.3);
}
.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 28px rgba(201, 75, 1, 0.4);
}
.btn-submit:active {
    transform: translateY(0);
}
.divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 16px 0;
    font-size: 12px;
    color: var(--muted);
    font-weight: 600;
}
.divider::before, .divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #e8d5c4;
}
.login-link {
    text-align: center;
    font-size: 13.5px;
    color: var(--muted);
    font-weight: 500;
}
.login-link a {
    color: var(--orange);
    font-weight: 800;
    text-decoration: none;
}
.login-link a:hover {
    text-decoration: underline;
}
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--muted);
    font-weight: 600;
    text-decoration: none;
    margin-bottom: 22px;
    transition: color 0.2s;
}
.back-link:hover {
    color: var(--orange);
}
.back-link i {
    font-size: 10px;
}
/* Responsive */
@media (max-width: 860px) {
    body {
        flex-direction: column-reverse;
        overflow-y: auto;
    }
    .left-panel {
        width: 100%;
        padding: 36px 24px 52px;
    }
    .right-panel {
        padding: 40px 24px;
        min-height: 320px;
    }
    .form-row-2cols {
        flex-direction: column;
        gap: 0;
    }
    .form-row-2cols .form-group {
        margin-bottom: 14px;
    }
    .benefits-tagline {
        font-size: 15px;
        padding: 12px 20px;
    }
    .phone-group {
        flex-direction: column;
        gap: 8px;
    }
    .phone-group .country-code {
        width: 100%;
    }
}
</style>
</head>
<body>

<!-- LEFT PANEL: REGISTRATION FORM -->
<div class="left-panel">
    <a href="../publics/index.php" class="back-link">
        <i class="fa fa-arrow-left"></i> Back to Store
    </a>

    <div class="form-top">
        <div class="form-eyebrow">Join PlayZo Nepal</div>
        <div class="form-title">Create <span>Account</span></div>
        <div class="form-sub">Register and become a member of Nepal's best jersey store!</div>
    </div>

    <?php if($error): ?>
    <div class="toast error">
        <i class="fa fa-circle-exclamation"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

        <!-- Name and Email in a row -->
        <div class="form-row-2cols">
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
                <label>Email Address *</label>
                <div class="input-wrap">
                    <i class="fa fa-envelope ico"></i>
                    <input type="email" name="email"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="you@example.com" required>
                </div>
            </div>
        </div>

        <!-- Phone with country code -->
        <div class="form-group">
            <label>Phone Number</label>
            <div class="phone-group">
                <div class="country-code">
                    <div class="input-wrap">
                        <i class="fa fa-globe ico"></i>
                        <select name="country_code">
                            <option value="+977" <?php echo (($_POST['country_code'] ?? '') == '+977') ? 'selected' : ''; ?>>🇳🇵 +977 (Nepal)</option>
                            <option value="+91" <?php echo (($_POST['country_code'] ?? '') == '+91') ? 'selected' : ''; ?>>🇮🇳 +91 (India)</option>
                            <option value="+1" <?php echo (($_POST['country_code'] ?? '') == '+1') ? 'selected' : ''; ?>>🇺🇸 +1 (USA)</option>
                            <option value="+44" <?php echo (($_POST['country_code'] ?? '') == '+44') ? 'selected' : ''; ?>>🇬🇧 +44 (UK)</option>
                            <option value="+61" <?php echo (($_POST['country_code'] ?? '') == '+61') ? 'selected' : ''; ?>>🇦🇺 +61 (Australia)</option>
                            <option value="+86" <?php echo (($_POST['country_code'] ?? '') == '+86') ? 'selected' : ''; ?>>🇨🇳 +86 (China)</option>
                            <option value="+81" <?php echo (($_POST['country_code'] ?? '') == '+81') ? 'selected' : ''; ?>>🇯🇵 +81 (Japan)</option>
                            <option value="+82" <?php echo (($_POST['country_code'] ?? '') == '+82') ? 'selected' : ''; ?>>🇰🇷 +82 (Korea)</option>
                            <option value="+971" <?php echo (($_POST['country_code'] ?? '') == '+971') ? 'selected' : ''; ?>>🇦🇪 +971 (UAE)</option>
                            <option value="+966" <?php echo (($_POST['country_code'] ?? '') == '+966') ? 'selected' : ''; ?>>🇸🇦 +966 (Saudi)</option>
                        </select>
                    </div>
                </div>
                <div class="phone-number">
                    <div class="input-wrap">
                        <i class="fa fa-phone ico"></i>
                        <input type="text" name="phone"
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                               placeholder="98XXXXXXXX">
                    </div>
                </div>
            </div>
        </div>

        <!-- Password fields -->
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
                <div class="strength-label" id="strengthLabel">Enter your password</div>
            </div>
        </div>

        <div class="form-group">
            <label>Confirm Password *</label>
            <div class="input-wrap">
                <i class="fa fa-lock ico"></i>
                <input type="password" name="confirm_password" id="confPass"
                       placeholder="Repeat password" required>
                <button type="button" class="eye-btn" onclick="togglePass('confPass',this)">
                    <i class="fa fa-eye"></i>
                </button>
            </div>
        </div>

        <label class="terms">
            <input type="checkbox" name="terms" required>
            <span>
                I agree to the <a href="#">Terms &amp; Conditions</a> and
                <a href="#">Privacy Policy</a>.
            </span>
        </label>

        <button type="submit" class="btn-submit">
            <i class="fa fa-user-plus"></i> Create Account
        </button>
    </form>

    <div class="divider">or</div>

    <div class="login-link">
        Already have an account? &nbsp;
        <a href="login.php<?php echo $redirect ? '?redirect='.urlencode($redirect) : ''; ?>">
            Login →
        </a>
    </div>
</div>

<!-- RIGHT PANEL: FULL BACKGROUND IMAGE + LOGO + TAGLINE -->
<div class="right-panel">
    <div class="brand-content">
        <div class="right-logo">
            <img class="logo-img" src="../img_logo/playzo.w.png"
                 alt="PlayZo Nepal Logo" width="130" height="130">
        </div>
        <div class="benefits-tagline">
            <i class="fa-regular fa-circle-check"></i> Account creation is free! Order tracking, exclusive deals, and many benefits!
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
    if(!val){ fill.style.width='0'; label.textContent='Enter your password'; fill.style.background=''; return; }
    let score = 0;
    if(val.length >= 6)  score++;
    if(val.length >= 10) score++;
    if(/[A-Z]/.test(val)) score++;
    if(/[0-9]/.test(val)) score++;
    if(/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        {w:'20%',bg:'#ef4444',txt:'Very weak'},
        {w:'40%',bg:'#f97316',txt:'Weak'},
        {w:'60%',bg:'#eab308',txt:'Okay'},
        {w:'80%',bg:'#22c55e',txt:'Good'},
        {w:'100%',bg:'#15803d',txt:'Strong'},
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