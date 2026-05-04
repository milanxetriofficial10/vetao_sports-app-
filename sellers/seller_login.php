<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../databases/db.php';

// login handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $full_name  = trim($_POST['full_name'] ?? '');
    $pan_number = trim($_POST['pan_number'] ?? '');

    $errors = [];

    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }

    if (empty($pan_number) || !preg_match('/^[0-9]{9}$/', $pan_number)) {
        $errors[] = "Valid 9-digit PAN number is required";
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM sellers WHERE full_name = ? AND pan_number = ?");
        $stmt->bind_param("ss", $full_name, $pan_number);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $seller = $result->fetch_assoc();

            $_SESSION['seller_id']    = $seller['id'];
            $_SESSION['seller_name']  = $seller['full_name'];
            $_SESSION['seller_email'] = $seller['email'];
            $_SESSION['shop_name']    = $seller['shop_name'];
            $_SESSION['status']       = $seller['status'];

            echo json_encode([
                'success'  => true,
                'message'  => 'Login successful!',
                'redirect' => '../sellers/seller_dashboard.php'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid name or PAN number'
            ]);
        }

        $stmt->close();
        $db->close();

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seller Login | SportsBazaar</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
  html,body{height:100%;overflow:hidden}
  body{font-family:'DM Sans',sans-serif;background:#0F172A;display:flex;flex-direction:column;height:100vh}

  /* TOPBAR */
  .topbar{height:54px;flex-shrink:0;background: #b94502 ;display:flex;align-items:center;justify-content:space-between;padding:0 1.8rem;border-bottom:1px solid #E2E8F0;z-index:100}
  .brand{display:flex;align-items:center;gap:.55rem;text-decoration:none}
  .brand-icon{width:32px;height:32px;background: #C72A1F;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.85rem;flex-shrink:0}
  .brand-name{font-family:'Sora',sans-serif;font-weight:800;font-size:1.15rem;color:#0F172A;letter-spacing:-.3px}
  .brand-name em{color:#C72A1F;font-style:normal}
  .tb-meta{display:flex;gap:1rem;font-size:.72rem;color: rgb(248, 248, 248);padding:.32rem .9rem;}
  .tb-meta span{display:flex;align-items:center;gap:.28rem}
  .tb-meta i{color: #5cf210;font-size:.65rem}
  .btn-reg{padding:.4rem 1rem;background: #f9f5f5;border:1.5px solid #C72A1F;border-radius:2rem;color: #f50202;font-weight:600;font-size:.75rem;cursor:pointer;display:flex;align-items:center;gap:.35rem;text-decoration:none;transition:.2s}
  .btn-reg:hover{background:#9e1f15;border-color:#9e1f15}

  /* MAIN LAYOUT */
  .main{flex:1;display:flex;overflow:hidden;min-height:0}

  /* FORM COLUMN */
  .form-col{width:50%;flex-shrink:0;background: #fff;display:flex;flex-direction:column;overflow:hidden}
  .form-hdr{background: #b94502 ;;padding:1rem 1.6rem;display:flex;align-items:center;gap:.8rem;flex-shrink:0}
  .fh-icon{width:40px;height:40px;background:#C72A1F;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;flex-shrink:0}
  .fh-text h2{font-family:'Sora',sans-serif;font-weight:800;font-size:.95rem;color:#fff;letter-spacing:-.2px}
  .fh-text p{font-size:.68rem;color:rgba(255,255,255,.5);margin-top:1px}

  .form-body{flex:1;display:flex;align-items:center;justify-content:center;padding:2rem 2.2rem}
  .form-inner{width:100%;max-width:380px}

  .form-welcome{margin-bottom:1.8rem}
  .form-welcome .step-tag{display:inline-flex;align-items:center;gap:.4rem;background:#fff0ef;color:#C72A1F;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:.28rem .8rem;border-radius:2rem;margin-bottom:.7rem}
  .form-welcome h1{font-family:'Sora',sans-serif;font-size:1.55rem;font-weight:800;color:#0F172A;letter-spacing:-.4px;line-height:1.2}
  .form-welcome p{font-size:.8rem;color:#64748b;margin-top:.4rem}

  .ig{display:flex;flex-direction:column;gap:.3rem;margin-bottom:.9rem}
  .ig label{font-size:.65rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px}
  .req{color:#C72A1F}
  .iw{position:relative;display:flex;align-items:center}
  .iw .fi{position:absolute;left:.8rem;color:#94a3b8;font-size:.75rem;pointer-events:none}
  .iw input{width:100%;padding:.68rem .8rem .68rem 2.1rem;border:1.5px solid #E2E8F0;border-radius:9px;background:#F8FAFC;font-size:.84rem;color:#0F172A;font-family:'DM Sans',sans-serif;transition:.18s}
  .iw input:focus{outline:none;border-color:#C72A1F;background:#fff;box-shadow:0 0 0 3px rgba(199,42,31,.08)}
  .emsg{font-size:.64rem;color:#dc2626;min-height:14px;margin-top:2px}

  .btn-login{width:100%;background:linear-gradient(135deg,#C72A1F,#e84035);border:none;padding:.85rem;border-radius:2rem;color:#fff;font-family:'Sora',sans-serif;font-weight:700;font-size:.88rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.5rem;transition:.22s;margin-top:.4rem}
  .btn-login:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 10px 24px rgba(199,42,31,.3)}
  .btn-login:disabled{opacity:.65;cursor:not-allowed}

  .bottom-link{text-align:center;margin-top:1rem;font-size:.76rem;color:#64748b}
  .bottom-link a{color:#C72A1F;font-weight:600;text-decoration:none}

  .info-card{background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:.75rem 1rem;margin-top:1.2rem;display:flex;align-items:flex-start;gap:.6rem}
  .info-card i{color:#C72A1F;font-size:.8rem;margin-top:2px;flex-shrink:0}
  .info-card p{font-size:.72rem;color:#64748b;line-height:1.5}

  /* SKY PANEL — no border, no text */
  .sky-col{flex:1;position:relative;overflow:hidden;background:#030617}
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

  @media(max-width:820px){
    html,body{overflow:auto;height:auto}
    .main{flex-direction:column;overflow:visible}
    .form-col{width:100%}
    .sky-col{min-height:280px}
    .tb-meta{display:none}
  }
  @media(max-width:540px){
    .topbar{padding:0 1rem}
    .form-body{padding:1.5rem 1.2rem}
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
    <img src="../img_logo/playzo.w.png" alt="seller logo" class="img-logo">
  </a>
  <div class="tb-meta">
    <span><i class="fas fa-map-marker-alt"></i> Kathmandu, Nepal</span>
    <span><i class="fas fa-phone-alt"></i> +977 9801234567</span>
    <span><i class="fas fa-envelope"></i> seller@sportsbazaar.com</span>
  </div>
  <a href="seller_register.php" class="btn-reg"><i class="fas fa-user-plus"></i> Register</a>
</div>

<div class="main">

  <!-- LEFT: LOGIN FORM -->
  <div class="form-col">
    <div class="form-hdr">
      <div class="fh-text">
        <h2>PlayZo<span style="color: #6eef0c"> Nepal</span> is a place where you can find all sports items in one place.</h2>
        <p>Sign in to manage your store</p>
      </div>
    </div>

    <div class="form-body">
      <div class="form-inner">
        <div class="form-welcome">
          <div class="step-tag"><i class="fas fa-store"></i> Seller Login</div>
          <h1>Welcome Back,<br>Seller!</h1>
          <p>Enter your registered name and PAN number to continue.</p>
        </div>

        <form id="loginForm" method="POST" novalidate>
          <div class="ig">
            <label>Full Name <span class="req">*</span></label>
            <div class="iw">
              <input type="text" name="full_name" id="full_name" placeholder="Enter your registered full name" autocomplete="name">
              <i class="fas fa-user fi"></i>
            </div>
            <div class="emsg" id="eFN"></div>
          </div>

          <div class="ig">
            <label>PAN Number <span class="req">*</span></label>
            <div class="iw">
              <input type="text" name="pan_number" id="pan_number" placeholder="9-digit PAN number" maxlength="9">
              <i class="fas fa-id-card fi"></i>
            </div>
            <div class="emsg" id="ePAN"></div>
          </div>

          <button type="submit" class="btn-login" id="loginBtn">
            <i class="fas fa-arrow-right-to-bracket"></i> Sign In to Dashboard
          </button>
        </form>

        <div class="bottom-link">New seller? <a href="seller_register.php">Register here</a></div>

        <div class="info-card">
          <i class="fas fa-info-circle"></i>
          <p>Use the full name and PAN number you registered with. Contact support if you have trouble signing in.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT: PURE ANIMATED NIGHT SKY — NO TEXT, NO BORDER -->
  <div class="sky-col">
    <canvas id="skyCanvas"></canvas>
    <div class="aurora"></div>
    <div class="nebula"></div>
    <div class="nebula2"></div>
    <div class="saturn"><div class="sb"></div><div class="sr"></div></div>
    <div class="moon"></div>
    <div class="shoot" style="top:12%;left:25%;animation:shoot 1.9s 1.5s infinite linear"></div>
    <div class="shoot" style="top:22%;left:58%;animation:shoot 1.6s 4.8s infinite linear"></div>
    <div class="shoot" style="top:8%;left:72%;animation:shoot 2.1s 8.2s infinite linear"></div>
    <div class="shoot" style="top:35%;left:40%;animation:shoot 1.4s 12s infinite linear"></div>
  </div>

</div>

<div class="toast-c" id="toastContainer"></div>

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

  // Input filters
  document.getElementById('pan_number').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 9);
  });

  // Toast
  function showToast(msg, type) {
    const c = document.getElementById('toastContainer'), t = document.createElement('div');
    t.className = 'toast ' + (type === 's' ? 's' : 'e');
    t.innerHTML = '<i class="fas ' + (type === 's' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i> ' + msg;
    c.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); }, 4000);
  }

  function se(id, m) { const e = document.getElementById(id); if (e) e.textContent = m; }
  function ce(id) { const e = document.getElementById(id); if (e) e.textContent = ''; }

  document.getElementById('full_name').addEventListener('input', () => ce('eFN'));
  document.getElementById('pan_number').addEventListener('input', () => ce('ePAN'));

  document.getElementById('loginForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    let ok = true;

    const fn = document.getElementById('full_name').value.trim();
    if (!fn) { se('eFN', 'Full name is required'); ok = false; } else ce('eFN');

    const pan = document.getElementById('pan_number').value.trim();
    if (!pan || !/^[0-9]{9}$/.test(pan)) { se('ePAN', 'Valid 9-digit PAN required'); ok = false; } else ce('ePAN');

    if (!ok) return;

    const btn = document.getElementById('loginBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in…';

    try {
      const fd = new FormData(this);
      const res = await fetch(window.location.href, { method: 'POST', body: fd });
      const data = await res.json();

      if (data.success) {
        showToast(data.message || 'Login successful!', 's');
        setTimeout(() => window.location.href = data.redirect, 1500);
      } else {
        const msg = data.errors ? data.errors[0] : data.message;
        showToast(msg, 'e');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-arrow-right-to-bracket"></i> Sign In to Dashboard';
      }
    } catch (err) {
      showToast('Network error', 'e');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-arrow-right-to-bracket"></i> Sign In to Dashboard';
    }
  });
</script>
</body>
</html>