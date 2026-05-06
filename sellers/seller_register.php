<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (!isset($_SESSION['email_verified']) || $_SESSION['email_verified'] !== true) {
    header('Location: email_verify.php');
    exit;
}
$verified_email = $_SESSION['verified_email'] ?? '';

require_once __DIR__ . '/../databases/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => '', 'errors' => []];
    $full_name         = trim($_POST['full_name'] ?? '');
    $father_name       = trim($_POST['father_name'] ?? '');
    $mother_name       = trim($_POST['mother_name'] ?? '');
    $permanent_address = trim($_POST['permanent_address'] ?? '');
    $current_address   = trim($_POST['current_address'] ?? '');
    $email             = trim($_POST['email'] ?? '');
    $phone             = trim($_POST['phone'] ?? '');
    $password          = $_POST['password'] ?? '';
    $confirm_pass      = $_POST['confirm_password'] ?? '';
    $pan_number        = trim($_POST['pan_number'] ?? '');
    $agree             = isset($_POST['agree_terms']);
    $errors = [];
    if (empty($full_name)) $errors[] = 'Full name is required';
    if (empty($father_name)) $errors[] = "Father's name is required";
    if (empty($mother_name)) $errors[] = "Mother's name is required";
    if (empty($permanent_address)) $errors[] = 'Permanent address is required';
    if (empty($current_address)) $errors[] = 'Current address is required';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    if (empty($phone) || !preg_match('/^[9][6-9][0-9]{8}$/', $phone)) $errors[] = 'Valid Nepal phone number required (98XXXXXXXX)';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) $errors[] = 'Password must contain uppercase, lowercase and number';
    if ($password !== $confirm_pass) $errors[] = 'Passwords do not match';
    if (empty($pan_number) || !preg_match('/^[0-9]{9}$/', $pan_number)) $errors[] = 'PAN number must be 9 digits';
    if (!$agree) $errors[] = 'You must agree to the seller terms';
    try {
        $db = getDB();
        if (!$db) throw new Exception("Database connection failed");
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM sellers WHERE email = ? OR pan_number = ?");
        $stmt->bind_param('ss', $email, $pan_number);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) $errors[] = 'Email or PAN number already registered';
        $stmt->close();
    }
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $insert = $db->prepare("INSERT INTO sellers (full_name, father_name, mother_name, permanent_address, current_address, email, phone, password, pan_number, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        if (!$insert) { echo json_encode(['success' => false, 'message' => 'Database prepare error']); $db->close(); exit; }
        $insert->bind_param('sssssssss', $full_name, $father_name, $mother_name, $permanent_address, $current_address, $email, $phone, $hashed, $pan_number);
        if ($insert->execute()) {
            $seller_id = $db->insert_id;
            $_SESSION['seller_id'] = $seller_id;
            $_SESSION['seller_name'] = $full_name;
            $_SESSION['seller_email'] = $email;
            $_SESSION['pan_number'] = $pan_number;
            $_SESSION['status'] = 'pending';
            unset($_SESSION['email_verified'], $_SESSION['verified_email']);
            $response['success'] = true;
            $response['message'] = 'Registration successful! Redirecting to dashboard.';
            $response['redirect'] = 'seller_dashboard.php';
            $insert->close(); $db->close();
            echo json_encode($response); exit;
        } else {
            $errors[] = 'Registration failed: ' . $insert->error;
            $insert->close();
        }
    }
    $db->close();
    $response['errors'] = $errors;
    $response['message'] = $errors[0] ?? 'Validation failed';
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seller Registration | SportsBazaar</title>
<link rel="shortcut icon" href="../img_logo/cropped_circle_image.png" type="image/x-icon">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
  html,body{height:100%;overflow:hidden}
  body{font-family:'DM Sans',sans-serif;background:#0F172A;display:flex;flex-direction:column;height:100vh}

  /* TOPBAR */
  .topbar{
    height:54px;flex-shrink:0;
    background: #b94502;
    display:flex;align-items:center;justify-content:space-between;
    padding:0 1.8rem;
    border-bottom:1px solid #E2E8F0;
    z-index:100;
  }
  .brand{display:flex;align-items:center;gap:.55rem;text-decoration:none}
  .brand-icon{width:32px;height:32px;background: #C72A1F;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.85rem;flex-shrink:0}
  .brand-name{font-family:'Sora',sans-serif;font-weight:800;font-size:1.15rem;color: #0F172A;letter-spacing:-.3px}
  .brand-name em{color: #C72A1F;font-style:normal}
  .tb-meta {display:flex;gap:1rem;font-size:.78rem;color: #3afe0e; padding:.32rem .9rem;}
  .tb-meta span{display:flex;align-items:center;gap:.28rem}
  .tb-meta i{color: #032cfb;font-size:.65rem}
  .btn-si{padding:.4rem 1rem;background:#fff;border:1.5px solid #C72A1F;border-radius:2rem;color:#C72A1F;font-weight:600;font-size:.75rem;cursor:pointer;display:flex;align-items:center;gap:.35rem;text-decoration:none;transition:.2s}
  .btn-si:hover{background:#C72A1F;color:#fff}

  /* MAIN LAYOUT */
  .main{flex:1;display:flex;overflow:hidden;min-height:0}

  /* FORM COLUMN */
  .form-col{
    width:50%;flex-shrink:0;
    background: #fff;
    display:flex;flex-direction:column;
    overflow:hidden;
  }
  .form-hdr{
    background:linear-gradient(135deg, #b94502);
    padding:1rem 1.6rem;
    display:flex;align-items:center;gap:.8rem;
    flex-shrink:0;
  }
  .fh-icon{width:40px;height:40px;background:#C72A1F;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;flex-shrink:0}
  .fh-text h2{font-family:'Sora',sans-serif;font-weight:800;font-size:.80rem;color:#fff;letter-spacing:-.2px}
  .fh-text p{font-size:.68rem;color:rgba(255,255,255,.55);margin-top:1px}
  .fh-badge{margin-left:auto;display:flex;align-items:center;gap:.35rem;background:rgba(26,122,94,.3);border:1px solid rgba(26,122,94,.5);padding:.25rem .65rem;border-radius:2rem;color:#4ade80;font-size:.67rem;font-weight:600;white-space:nowrap}

  .form-scroll{flex:1;overflow-y:auto;padding:1rem 1.6rem 1.6rem}
  .form-scroll::-webkit-scrollbar{width:4px}
  .form-scroll::-webkit-scrollbar-track{background:transparent}
  .form-scroll::-webkit-scrollbar-thumb{background:#E2E8F0;border-radius:4px}

  .sec-lbl{font-family:'Sora',sans-serif;font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#C72A1F;display:flex;align-items:center;gap:.5rem;margin:1.1rem 0 .7rem}
  .sec-lbl::after{content:'';flex:1;height:1px;background:#f5cbc8}
  .g2{display:grid;grid-template-columns:1fr 1fr;gap:.6rem .8rem}
  .full{grid-column:span 2}
  .ig{display:flex;flex-direction:column;gap:.25rem}
  .ig label{font-size:.62rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px}
  .req{color:#C72A1F}
  .iw{position:relative;display:flex;align-items:center}
  .iw .fi{position:absolute;left:.75rem;color:#94a3b8;font-size:.72rem;pointer-events:none}
  .iw input{width:100%;padding:.6rem .75rem .6rem 2rem;border:1.5px solid #E2E8F0;border-radius:8px;background:#F8FAFC;font-size:.82rem;color:#0F172A;font-family:'DM Sans',sans-serif;transition:.18s}
  .iw input:focus{outline:none;border-color:#C72A1F;background:#fff;box-shadow:0 0 0 3px rgba(199,42,31,.08)}
  .iw input[readonly]{background:#f1f5f9;cursor:not-allowed;color:#64748b}
  .pwt{position:absolute;right:.75rem;background:none;border:none;cursor:pointer;color:#94a3b8;font-size:.78rem;padding:0}
  .str-t{height:2px;background:#E2E8F0;border-radius:4px;margin-top:3px;overflow:hidden}
  .str-f{height:100%;width:0%;transition:width .25s;border-radius:4px}
  .emsg{font-size:.63rem;color:#dc2626;min-height:13px;margin-top:1px}
  .agree-box{display:flex;align-items:flex-start;gap:.6rem;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:.75rem .9rem;margin:.9rem 0 .8rem}
  .agree-box input{width:14px;height:14px;accent-color:#C72A1F;margin-top:2px;flex-shrink:0}
  .agree-box label{font-size:.73rem;color:#64748b;line-height:1.5}
  .agree-box label strong{color:#C72A1F;cursor:pointer}
  .btn-sub{width:100%;background:linear-gradient(135deg,#C72A1F,#e84035);border:none;padding:.82rem;border-radius:2rem;color:#fff;font-family:'Sora',sans-serif;font-weight:700;font-size:.84rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.5rem;transition:.22s}
  .btn-sub:hover{transform:translateY(-1px);box-shadow:0 10px 24px rgba(199,42,31,.32)}
  .login-hint{text-align:center;margin-top:.75rem;font-size:.74rem;color:#64748b}
  .login-hint a{color:#C72A1F;font-weight:600;text-decoration:none}

  /* SKY PANEL — no border, no text, pure animation */
  .sky-col{
    flex:1;
    position:relative;
    overflow:hidden;
    background:#030617;
  }
  #skyCanvas{position:absolute;inset:0;width:100%;height:100%}
  .aurora{position:absolute;bottom:25%;left:0;right:0;height:160px;background:linear-gradient(180deg,transparent,rgba(56,178,120,.07) 40%,rgba(100,220,180,.11) 60%,transparent);animation:aur 9s ease-in-out infinite alternate;pointer-events:none;z-index:2}
  @keyframes aur{0%{opacity:.5;transform:scaleX(1)}100%{opacity:1;transform:scaleX(1.08)}}
  .nebula{position:absolute;top:30%;left:15%;width:200px;height:130px;background:radial-gradient(ellipse,rgba(120,80,200,.1),transparent 70%);border-radius:50%;pointer-events:none;z-index:1;animation:neb 14s ease-in-out infinite alternate}
  .nebula2{position:absolute;top:55%;right:12%;width:140px;height:90px;background:radial-gradient(ellipse,rgba(60,120,220,.08),transparent 70%);border-radius:50%;pointer-events:none;z-index:1;animation:neb 10s 3s ease-in-out infinite alternate}
  @keyframes neb{0%{opacity:.3;transform:scale(1)}100%{opacity:.7;transform:scale(1.15)}}
  .saturn{position:absolute;top:8%;right:8%;z-index:3}
  .sb{width:65px;height:65px;background:radial-gradient(circle at 35% 35%,#f5d47a,#c49b2c);border-radius:50%}
  .sr{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotateX(72deg);width:110px;height:110px;border-radius:50%;border:7px solid rgba(210,175,70,.35)}
  .moon{position:absolute;top:20%;left:8%;width:44px;height:44px;background:radial-gradient(circle at 35% 30%,#e8e0c8,#b8b0a0);border-radius:50%;box-shadow:inset -5px -3px 0 rgba(0,0,0,.18);z-index:3}
  .shoot{position:absolute;width:2px;height:2px;background:#fff;border-radius:50%;opacity:0}
  @keyframes shoot{0%{opacity:1;transform:translate(0,0) scale(1.2)}100%{opacity:0;transform:translate(110px,55px) scale(0)}}

  /* TOAST */
  .toast-c{position:fixed;bottom:1.2rem;right:1.2rem;z-index:9000;display:flex;flex-direction:column;gap:.35rem}
  .toast{background:#fff;border-radius:.75rem;padding:.55rem .9rem;box-shadow:0 8px 20px rgba(0,0,0,.12);border-left:3px solid;font-size:.74rem;font-weight:500;animation:ti .22s ease;display:flex;align-items:center;gap:.45rem}
  .toast.s{border-color:#1A7A5E;color:#1A7A5E}
  .toast.e{border-color:#C72A1F;color:#b91c1c}
  @keyframes ti{from{opacity:0;transform:translateX(16px)}to{opacity:1;transform:translateX(0)}}

  /* OVERLAY */
  .overlay{position:fixed;inset:0;background:rgba(15,23,42,.8);backdrop-filter:blur(6px);z-index:8000;display:none;align-items:center;justify-content:center;flex-direction:column;gap:1.2rem}
  .spin{width:44px;height:44px;border:3px solid rgba(255,255,255,.15);border-top-color:#C72A1F;border-radius:50%;animation:sp .7s linear infinite}
  .ot{color:#fff;font-weight:500}
  @keyframes sp{to{transform:rotate(360deg)}}

  @media(max-width:820px){
    html,body{overflow:auto;height:auto}
    .main{flex-direction:column;overflow:visible}
    .form-col{width:100%}
    .sky-col{min-height:300px}
    .tb-meta{display:none}
  }
  @media(max-width:540px){
    .g2{grid-template-columns:1fr}
    .full{grid-column:span 1}
    .form-scroll{padding:.8rem 1rem 1.2rem}
    .topbar{padding:0 1rem}
  }

  .img-logo{
    width:190px;
    height:50px;
    object-fit:contain;
    padding:4px;
    border-radius:8px;

  }
</style>
</head>
<body>

<div class="topbar">
  <a href="#" class="brand">
     <img src="../img_logo/playzo.w.png" alt="playzo logo" class="img-logo">
  </a>
  <div class="tb-meta">
    <span><i class="fas fa-map-marker-alt"></i> Kathmandu, Nepal</span>
    <span><i class="fas fa-phone-alt"></i> +977 9801234567</span>
    <span><i class="fas fa-envelope"></i> seller@sportsbazaar.com</span>
  </div>
  <a href="seller_login.php" class="btn-si"><i class="fas fa-arrow-right-to-bracket"></i> Sign In</a>
</div>

<div class="main">

  <!-- LEFT: REGISTRATION FORM -->
  <div class="form-col">
    <div class="form-hdr">
     
      <div class="fh-text">
        <h2>PlayZo<span style="color: #C72A1F"></span> PlayZo is a place where you can find all sports items in one place.</h2>
        <p>Create your seller account to start selling</p>
      </div>
      <div class="fh-badge"><i class="fas fa-check-circle"></i> Email Verified</div>
    </div>

    <div class="form-scroll">
      <form id="sellerRegisterForm" method="POST" novalidate>

        <div class="sec-lbl"><i class="fas fa-user"></i> Personal Information</div>
        <div class="g2">
          <div class="ig full">
            <label>Full Name <span class="req">*</span></label>
            <div class="iw"><input type="text" name="full_name" id="full_name" placeholder="e.g. Rajesh Hamal" autocomplete="name"><i class="fas fa-user fi"></i></div>
            <div class="emsg" id="eFull"></div>
          </div>
          <div class="ig">
            <label>Father's Name <span class="req">*</span></label>
            <div class="iw"><input type="text" name="father_name" id="father_name" placeholder="Father's full name"><i class="fas fa-user-tie fi"></i></div>
            <div class="emsg" id="eFather"></div>
          </div>
          <div class="ig">
            <label>Mother's Name <span class="req">*</span></label>
            <div class="iw"><input type="text" name="mother_name" id="mother_name" placeholder="Mother's full name"><i class="fas fa-user-tie fi"></i></div>
            <div class="emsg" id="eMother"></div>
          </div>
          <div class="ig full">
            <label>Permanent Address <span class="req">*</span></label>
            <div class="iw"><input type="text" name="permanent_address" id="permanent_address" placeholder="District, Municipality, Ward"><i class="fas fa-map-marker-alt fi"></i></div>
            <div class="emsg" id="ePA"></div>
          </div>
          <div class="ig full">
            <label>Current Address <span class="req">*</span></label>
            <div class="iw"><input type="text" name="current_address" id="current_address" placeholder="Current living address"><i class="fas fa-location-dot fi"></i></div>
            <div class="emsg" id="eCA"></div>
          </div>
        </div>

        <div class="sec-lbl"><i class="fas fa-address-card"></i> Contact & Verification</div>
        <div class="g2">
          <div class="ig">
            <label>Email Address <span class="req">*</span></label>
            <div class="iw"><input type="email" name="email" id="email" value="<?php echo htmlspecialchars($verified_email); ?>" readonly><i class="fas fa-envelope fi"></i></div>
            <div class="emsg" id="eEM"></div>
          </div>
          <div class="ig">
            <label>Phone Number <span class="req">*</span></label>
            <div class="iw"><input type="tel" name="phone" id="phone" placeholder="98XXXXXXXX" maxlength="10"><i class="fas fa-phone fi"></i></div>
            <div class="emsg" id="ePH"></div>
          </div>
          <div class="ig full">
            <label>PAN Number (9 digits) <span class="req">*</span></label>
            <div class="iw"><input type="text" name="pan_number" id="pan_number" placeholder="123456789" maxlength="9"><i class="fas fa-id-card fi"></i></div>
            <div class="emsg" id="ePAN"></div>
          </div>
        </div>

        <div class="sec-lbl"><i class="fas fa-lock"></i> Set Password</div>
        <div class="g2">
          <div class="ig">
            <label>Password <span class="req">*</span></label>
            <div class="iw"><input type="password" name="password" id="password" placeholder="Create password" style="padding-right:2.2rem"><i class="fas fa-lock fi"></i><button type="button" class="pwt" onclick="togglePw('password',this)"><i class="far fa-eye-slash"></i></button></div>
            <div class="str-t"><div class="str-f" id="sf"></div></div>
            <div class="emsg" id="ePW"></div>
          </div>
          <div class="ig">
            <label>Confirm Password <span class="req">*</span></label>
            <div class="iw"><input type="password" name="confirm_password" id="confirm_password" placeholder="Repeat password" style="padding-right:2.2rem"><i class="fas fa-lock fi"></i><button type="button" class="pwt" onclick="togglePw('confirm_password',this)"><i class="far fa-eye-slash"></i></button></div>
            <div class="emsg" id="eCPW"></div>
          </div>
        </div>

        <div class="agree-box">
          <input type="checkbox" id="agreeTerms" name="agree_terms">
          <label for="agreeTerms">I confirm that all information provided is accurate and I agree to the <strong>Seller Terms & Privacy Policy</strong>.</label>
        </div>

        <button type="submit" class="btn-sub" id="submitBtn"><i class="fas fa-paper-plane"></i> Register & Become a Seller</button>
        <div class="login-hint">Already have an account? <a href="seller_login.php">Sign in here</a></div>
      </form>
    </div>
  </div>

  <!-- RIGHT: PURE ANIMATED NIGHT SKY — NO TEXT, NO BORDER -->
<div>
  <img src="../img_logo/sellerlogo.jpg" alt="seller logo" style="width:100%;height:100%;object-fit:cover; background: #00000074">
</div>
</div>

<div class="toast-c" id="toastContainer"></div>
<div class="overlay" id="overlay">
  <div class="spin"></div>
  <div class="ot">Creating your seller account…</div>
</div>

<script>
  // Star canvas
  const canvas = document.getElementById('skyCanvas');
  const ctx = canvas.getContext('2d');
  let stars = [];

  function resize() {
    canvas.width = canvas.offsetWidth || canvas.parentElement.offsetWidth || 700;
    canvas.height = canvas.offsetHeight || canvas.parentElement.offsetHeight || 900;
    initStars();
  }

  function initStars() {
    stars = [];
    const n = Math.floor((canvas.width * canvas.height) / 1800);
    for (let i = 0; i < n; i++) {
      stars.push({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        r: Math.random() * 1.9 + 0.25,
        a: Math.random(),
        da: (Math.random() * .007 + .002) * (Math.random() < .5 ? 1 : -1),
        col: Math.random() < .14 ? '#ffe8a3' : Math.random() < .1 ? '#b3d4ff' : '#ffffff'
      });
    }
  }

  function drawStars() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const bg = ctx.createRadialGradient(canvas.width * .28, canvas.height * .38, 0, canvas.width * .5, canvas.height * .5, canvas.width * .9);
    bg.addColorStop(0, '#0a0f2a'); bg.addColorStop(1, '#030617');
    ctx.fillStyle = bg; ctx.fillRect(0, 0, canvas.width, canvas.height);
    const mw = ctx.createLinearGradient(0, canvas.height * .15, canvas.width, canvas.height * .65);
    mw.addColorStop(0, 'transparent'); mw.addColorStop(.5, 'rgba(160,140,255,0.03)'); mw.addColorStop(1, 'transparent');
    ctx.fillStyle = mw; ctx.fillRect(0, 0, canvas.width, canvas.height);
    stars.forEach(s => {
      s.a += s.da; if (s.a > 1 || s.a < 0) s.da *= -1;
      ctx.save(); ctx.globalAlpha = Math.max(0, Math.min(1, s.a));
      ctx.fillStyle = s.col; ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2); ctx.fill();
      if (s.r > 1.4) { ctx.globalAlpha = s.a * .18; ctx.beginPath(); ctx.arc(s.x, s.y, s.r * 3.5, 0, Math.PI * 2); ctx.fill(); }
      ctx.restore();
    });
    requestAnimationFrame(drawStars);
  }

  window.addEventListener('resize', resize);
  setTimeout(() => { resize(); requestAnimationFrame(drawStars); }, 100);

  // Form helpers
  function togglePw(id, btn) {
    const i = document.getElementById(id), ic = btn.querySelector('i');
    if (i.type === 'password') { i.type = 'text'; ic.className = 'far fa-eye'; }
    else { i.type = 'password'; ic.className = 'far fa-eye-slash'; }
  }

  document.getElementById('password').addEventListener('input', function () {
    let s = 0;
    if (this.value.length >= 8) s++;
    if (/[A-Z]/.test(this.value)) s++;
    if (/[0-9]/.test(this.value)) s++;
    if (/[@$!%*?&#]/.test(this.value)) s++;
    const f = document.getElementById('sf');
    f.style.width = (s / 4 * 100) + '%';
    f.style.background = s <= 1 ? '#ef4444' : s <= 2 ? '#f59e0b' : '#10b981';
  });

  document.getElementById('phone').addEventListener('input', function () { this.value = this.value.replace(/\D/g, '').slice(0, 10); });
  document.getElementById('pan_number').addEventListener('input', function () { this.value = this.value.replace(/\D/g, '').slice(0, 9); });

  function showToast(msg, type = 'e') {
    const c = document.getElementById('toastContainer'), t = document.createElement('div');
    t.className = 'toast ' + (type === 's' ? 's' : 'e');
    t.innerHTML = '<i class="fas ' + (type === 's' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i> ' + msg;
    c.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); }, 4000);
  }

  function se(id, m) { const e = document.getElementById(id); if (e) e.textContent = m; }
  function ce(id) { const e = document.getElementById(id); if (e) e.textContent = ''; }

  ['full_name','father_name','mother_name','permanent_address','current_address','phone','pan_number','password','confirm_password'].forEach(f => {
    const el = document.getElementById(f);
    if (el) el.addEventListener('input', () => ce('e' + f.charAt(0).toUpperCase() + f.slice(1)));
  });

  function validate() {
    let ok = true;
    const fn = document.getElementById('full_name').value.trim();
    if (!fn) { se('eFull', 'Full name required'); ok = false; } else ce('eFull');
    const fa = document.getElementById('father_name').value.trim();
    if (!fa) { se('eFather', "Father's name required"); ok = false; } else ce('eFather');
    const mo = document.getElementById('mother_name').value.trim();
    if (!mo) { se('eMother', "Mother's name required"); ok = false; } else ce('eMother');
    const pa = document.getElementById('permanent_address').value.trim();
    if (!pa) { se('ePA', 'Permanent address required'); ok = false; } else ce('ePA');
    const ca = document.getElementById('current_address').value.trim();
    if (!ca) { se('eCA', 'Current address required'); ok = false; } else ce('eCA');
    const ph = document.getElementById('phone').value.trim();
    if (!ph || !/^[9][6-9][0-9]{8}$/.test(ph)) { se('ePH', 'Valid Nepal phone required (96–99XXXXXXXX)'); ok = false; } else ce('ePH');
    const pan = document.getElementById('pan_number').value.trim();
    if (!pan || !/^[0-9]{9}$/.test(pan)) { se('ePAN', '9-digit PAN required'); ok = false; } else ce('ePAN');
    const pw = document.getElementById('password').value;
    if (pw.length < 8) { se('ePW', 'Min 8 characters'); ok = false; }
    else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(pw)) { se('ePW', 'Uppercase, lowercase & number required'); ok = false; }
    else ce('ePW');
    const cpw = document.getElementById('confirm_password').value;
    if (cpw !== pw) { se('eCPW', 'Passwords do not match'); ok = false; } else ce('eCPW');
    if (!document.getElementById('agreeTerms').checked) { showToast('Please agree to Seller Terms', 'e'); ok = false; }
    return ok;
  }

  document.getElementById('sellerRegisterForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!validate()) return;
    const btn = document.getElementById('submitBtn'), ov = document.getElementById('overlay');
    btn.disabled = true; ov.style.display = 'flex';
    const fd = new FormData(this);
    if (!fd.has('agree_terms')) fd.append('agree_terms', 'on');
    try {
      const res = await fetch(window.location.href, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const text = await res.text();
      let data;
      try { data = JSON.parse(text); } catch (e) { showToast('Server error', 'e'); ov.style.display = 'none'; btn.disabled = false; return; }
      ov.style.display = 'none';
      if (data.success) {
        showToast(data.message || 'Registration successful! Redirecting…', 's');
        setTimeout(() => window.location.href = data.redirect || 'seller_dashboard.php', 1600);
      } else {
        btn.disabled = false;
        (data.errors || [data.message]).filter(Boolean).forEach(m => showToast(m, 'e'));
      }
    } catch (err) {
      ov.style.display = 'none'; btn.disabled = false;
      showToast('Network error', 'e');
    }
  });
</script>
</body>
</html>