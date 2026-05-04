<?php
session_start();
require_once '../databases/db.php';

$page  = $_GET['page'] ?? 'login';
$error = '';

// ── Admin Login ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    $q = $conn->prepare("SELECT * FROM admins WHERE username=?");
    $q->bind_param('s', $u);
    $q->execute();
    $admin = $q->get_result()->fetch_assoc();
    if ($admin && password_verify($p, $admin['password'])) {
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_user'] = $admin['username'];
        header('Location: admin.php?page=dashboard');
        exit;
    }
    $error = 'Invalid username or password.';
}

// ── Admin Logout ──
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_id'], $_SESSION['admin_user']);
    header('Location: admin.php');
    exit;
}

// ── Status Update ──
if (isset($_POST['update_status']) && !empty($_SESSION['admin_id'])) {
    $sid    = (int)$_POST['seller_id'];
    $status = in_array($_POST['status'], ['pending','approved','rejected']) ? $_POST['status'] : 'pending';
    $conn->prepare("UPDATE sellers SET status=? WHERE id=?")->execute() || true;
    $upd = $conn->prepare("UPDATE sellers SET status=? WHERE id=?");
    $upd->bind_param('si', $status, $sid);
    $upd->execute();
    header('Location: admin.php?page=dashboard&updated=1');
    exit;
}

// ── Delete Seller ──
if (isset($_POST['delete_seller']) && !empty($_SESSION['admin_id'])) {
    $sid = (int)$_POST['seller_id'];
    // Get files first
    $r = $conn->prepare("SELECT cit_front, cit_back FROM sellers WHERE id=?");
    $r->bind_param('i', $sid);
    $r->execute();
    $row = $r->get_result()->fetch_assoc();
    if ($row) {
        @unlink(UPLOAD_DIR . $row['cit_front']);
        @unlink(UPLOAD_DIR . $row['cit_back']);
    }
    $conn->prepare("DELETE FROM sellers WHERE id=?")->execute() || true;
    $del = $conn->prepare("DELETE FROM sellers WHERE id=?");
    $del->bind_param('i', $sid);
    $del->execute();
    header('Location: admin.php?page=dashboard&deleted=1');
    exit;
}

// Protect dashboard
if (!empty($_SESSION['admin_id'])) $page = $_GET['page'] ?? 'dashboard';
elseif ($page !== 'login') { header('Location: admin.php'); exit; }

// ── Fetch Sellers ──
$filter  = $_GET['filter'] ?? 'all';
$search  = trim($_GET['q'] ?? '');
$sellers = [];
if (!empty($_SESSION['admin_id'])) {
    $where = "WHERE 1=1";
    $params = [];
    $types  = '';
    if ($filter !== 'all') { $where .= " AND status=?"; $types .= 's'; $params[] = $filter; }
    if ($search) { $where .= " AND (full_name LIKE ? OR email LIKE ? OR shop_name LIKE ?)"; $types .= 'sss'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $q = $conn->prepare("SELECT * FROM sellers $where ORDER BY created_at DESC");
    if ($params) { $q->bind_param($types, ...$params); }
    $q->execute();
    $sellers = $q->get_result()->fetch_all(MYSQLI_ASSOC);

    $counts = ['all'=>0,'pending'=>0,'approved'=>0,'rejected'=>0];
    $cq = $conn->query("SELECT status, COUNT(*) as cnt FROM sellers GROUP BY status");
    while ($r = $cq->fetch_assoc()) { $counts[$r['status']] = $r['cnt']; $counts['all'] += $r['cnt']; }
}

$sc = ['pending'=>'#f59e0b','approved'=>'#22c55e','rejected'=>'#ef4444'];
$sb = ['pending'=>'#fffbeb','approved'=>'#f0fdf4','rejected'=>'#fff1f2'];
$si = ['pending'=>'⏳','approved'=>'✅','rejected'=>'❌'];

// View single seller
$view_seller = null;
if (isset($_GET['view']) && !empty($_SESSION['admin_id'])) {
    $vs = $conn->prepare("SELECT * FROM sellers WHERE id=?");
    $vs->bind_param('i', (int)$_GET['view']);
    $vs->execute();
    $view_seller = $vs->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $page==='login' ? 'Admin Login' : 'Admin Panel' ?> — ShopNepal</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--or:#f57224;--dark:#1a1a2e;--mid:#16213e;--bg:#f1f5f9;--card:#fff;--border:#e2e8f0;--muted:#94a3b8;--text:#1e293b;--r:13px}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

/* ── LOGIN ── */
.login-wrap{min-height:100vh;background:linear-gradient(135deg,#1a1a2e 0%,#f57224 100%);display:flex;align-items:center;justify-content:center;padding:20px}
.login-box{background:#fff;border-radius:18px;box-shadow:0 24px 64px rgba(0,0,0,.25);padding:40px 36px;width:100%;max-width:400px}
.login-box .logo{display:flex;align-items:center;gap:10px;margin-bottom:28px;justify-content:center}
.login-box .logo .ico{width:42px;height:42px;background:var(--or);border-radius:10px;display:grid;place-items:center;font-size:20px}
.login-box .logo .nm{font-family:'Sora',sans-serif;font-weight:700;font-size:20px;color:var(--dark)}
.login-box h2{font-family:'Sora',sans-serif;font-size:22px;font-weight:700;margin-bottom:4px;text-align:center}
.login-box .sub{font-size:13px;color:var(--muted);text-align:center;margin-bottom:26px}
.lfield{display:flex;flex-direction:column;gap:5px;margin-bottom:16px}
.lfield label{font-size:12.5px;font-weight:500}
.lfield input{border:1.5px solid var(--border);border-radius:9px;padding:11px 14px;font-family:inherit;font-size:14px;outline:none;transition:border .2s}
.lfield input:focus{border-color:var(--or);box-shadow:0 0 0 3px rgba(245,114,36,.1)}
.lerr{background:#fff1f2;border:1.5px solid #fca5a5;border-radius:8px;padding:10px 14px;color:#b91c1c;font-size:13px;margin-bottom:14px}
.lbtn{width:100%;background:var(--or);color:#fff;border:none;border-radius:10px;padding:13px;font-family:'Sora',sans-serif;font-size:15px;font-weight:600;cursor:pointer;transition:background .2s,transform .15s}
.lbtn:hover{background:#e8651a;transform:translateY(-1px)}

/* ── LAYOUT ── */
.sidebar{position:fixed;top:0;left:0;width:235px;height:100vh;background:linear-gradient(180deg,var(--dark),var(--mid));display:flex;flex-direction:column;z-index:100;box-shadow:4px 0 20px rgba(0,0,0,.15)}
.sb-brand{padding:22px 18px 18px;border-bottom:1px solid rgba(255,255,255,.08)}
.sb-brand .logo{display:flex;align-items:center;gap:10px}
.sb-brand .ico{width:34px;height:34px;background:var(--or);border-radius:8px;display:grid;place-items:center;font-size:16px}
.sb-brand .nm{font-family:'Sora',sans-serif;font-weight:700;font-size:16px;color:#fff}
.sb-brand .tg{font-size:9px;color:rgba(255,255,255,.45);margin-top:2px}
.sb-menu{flex:1;padding:14px 10px}
.sb-menu a{display:flex;align-items:center;gap:10px;padding:10px 13px;border-radius:9px;color:rgba(255,255,255,.6);font-size:13px;text-decoration:none;transition:all .2s;margin-bottom:3px}
.sb-menu a:hover,.sb-menu a.act{background:rgba(245,114,36,.18);color:#fff}
.sb-menu .sl{font-size:9.5px;font-weight:600;color:rgba(255,255,255,.28);letter-spacing:1px;padding:12px 13px 5px;text-transform:uppercase}
.sb-foot{padding:14px 10px;border-top:1px solid rgba(255,255,255,.07)}
.sb-foot a{display:flex;align-items:center;gap:9px;padding:9px 13px;border-radius:9px;color:rgba(255,255,255,.5);font-size:12.5px;text-decoration:none;transition:all .2s}
.sb-foot a:hover{background:rgba(239,68,68,.15);color:#ef4444}
.main{margin-left:235px;min-height:100vh}
.topbar{background:#fff;border-bottom:1px solid var(--border);padding:14px 26px;display:flex;align-items:center;justify-content:space-between;sticky;top:0;box-shadow:0 1px 6px rgba(0,0,0,.05)}
.topbar h1{font-family:'Sora',sans-serif;font-size:17px;font-weight:700;color:var(--dark)}
.topbar .achip{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted)}
.topbar .achip .av{width:32px;height:32px;background:var(--or);border-radius:50%;display:grid;place-items:center;font-size:13px;font-weight:700;color:#fff;font-family:'Sora',sans-serif}
.content{padding:24px}

/* ── STATS ── */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:22px}
.stat{background:var(--card);border-radius:var(--r);padding:18px 20px;box-shadow:0 2px 10px rgba(0,0,0,.05);display:flex;align-items:center;gap:14px}
.stat .sico{width:44px;height:44px;border-radius:11px;display:grid;place-items:center;font-size:20px;flex-shrink:0}
.stat .sv{font-family:'Sora',sans-serif;font-size:22px;font-weight:700;color:var(--dark)}
.stat .sl{font-size:11.5px;color:var(--muted);margin-top:1px}

/* ── TABLE ── */
.table-card{background:var(--card);border-radius:var(--r);box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden}
.table-head{padding:18px 22px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);gap:14px;flex-wrap:wrap}
.table-head h3{font-family:'Sora',sans-serif;font-size:14.5px;font-weight:600}
.filters{display:flex;gap:7px;flex-wrap:wrap}
.fbtn{padding:5px 13px;border-radius:20px;font-size:12px;border:1.5px solid var(--border);background:#fff;cursor:pointer;transition:all .2s;color:var(--text)}
.fbtn.act{background:var(--or);border-color:var(--or);color:#fff}
.search-box input{border:1.5px solid var(--border);border-radius:8px;padding:7px 12px;font-family:inherit;font-size:13px;outline:none;width:200px;transition:border .2s}
.search-box input:focus{border-color:var(--or)}
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th{padding:11px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border);white-space:nowrap;background:#f8fafc}
td{padding:12px 16px;border-bottom:1px solid var(--border);font-size:13.5px;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#fafafa}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:600}
.seller-name{font-weight:600;color:var(--dark)}
.seller-shop{font-size:11.5px;color:var(--muted);margin-top:1px}
/* action btns */
.acts{display:flex;gap:6px;flex-wrap:wrap}
.abtn{padding:5px 11px;border-radius:7px;font-size:11.5px;font-weight:500;border:none;cursor:pointer;transition:all .15s;font-family:inherit}
.abtn-view{background:#eff6ff;color:#1d4ed8}.abtn-view:hover{background:#dbeafe}
.abtn-ok{background:#f0fdf4;color:#15803d}.abtn-ok:hover{background:#dcfce7}
.abtn-rej{background:#fff1f2;color:#b91c1c}.abtn-rej:hover{background:#fee2e2}
.abtn-del{background:#fff7ed;color:#c2410c}.abtn-del:hover{background:#ffedd5}
/* empty */
.empty{text-align:center;padding:50px;color:var(--muted)}
.empty .eico{font-size:40px;margin-bottom:12px}
/* notify */
.notify{padding:11px 18px;border-radius:9px;margin-bottom:16px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px}
.notify.ok{background:#f0fdf4;border:1.5px solid #86efac;color:#15803d}
.notify.err{background:#fff1f2;border:1.5px solid #fca5a5;color:#b91c1c}

/* ── MODAL ── */
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px)}
.modal{background:#fff;border-radius:18px;width:100%;max-width:700px;max-height:90vh;overflow-y:auto;box-shadow:0 30px 80px rgba(0,0,0,.3)}
.modal-h{padding:20px 26px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1}
.modal-h h3{font-family:'Sora',sans-serif;font-size:16px;font-weight:700}
.modal-close{background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted);padding:4px;border-radius:6px;transition:color .2s}
.modal-close:hover{color:var(--text)}
.modal-b{padding:24px}
.mi-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px}
.mi-row{display:flex;flex-direction:column;gap:3px}
.mi-row .ml{font-size:10.5px;color:var(--muted);font-weight:500;text-transform:uppercase;letter-spacing:.5px}
.mi-row .mv{font-size:13.5px;font-weight:500;color:var(--text)}
.doc-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.doc-item{border-radius:9px;overflow:hidden;border:1.5px solid var(--border)}
.doc-lbl{padding:7px 11px;background:#f8fafc;font-size:10.5px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)}
.doc-item img{width:100%;height:160px;object-fit:cover;display:block}
.modal-actions{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}
.mac-btn{flex:1;padding:11px;border:none;border-radius:9px;font-family:'Sora',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;min-width:120px}

@media(max-width:900px){.stats-row{grid-template-columns:1fr 1fr}.main{margin-left:0}.sidebar{display:none}}
</style>
</head>
<body>

<?php if ($page === 'login' || empty($_SESSION['admin_id'])): ?>
<!-- ── LOGIN PAGE ── -->
<div class="login-wrap">
  <div class="login-box">
    <div class="logo">
      <span class="ico">🛡️</span>
      <div><div class="nm">ShopNepal</div><div style="font-size:11px;color:var(--muted);text-align:center">Admin Panel</div></div>
    </div>
    <h2>Admin Login</h2>
    <p class="sub">Sign in to manage sellers</p>
    <?php if ($error): ?><div class="lerr">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="lfield">
        <label>Username</label>
        <input type="text" name="username" placeholder="admin" required autocomplete="username">
      </div>
      <div class="lfield">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
      </div>
      <button type="submit" name="admin_login" class="lbtn">🔐 Sign In</button>
    </form>
    <p style="text-align:center;font-size:12px;color:var(--muted);margin-top:18px">Default: admin / admin123</p>
  </div>
</div>

<?php else: ?>
<!-- ── DASHBOARD ── -->
<div class="sidebar">
  <div class="sb-brand">
    <div class="logo">
      <span class="ico">🛡️</span>
      <div><div class="nm">ShopNepal</div><div class="tg">Admin Panel</div></div>
    </div>
  </div>
  <nav class="sb-menu">
    <span class="sl">Main</span>
    <a href="admin.php?page=dashboard" class="act">📊 Dashboard</a>
    <a href="admin.php?page=dashboard&filter=pending">⏳ Pending</a>
    <a href="admin.php?page=dashboard&filter=approved">✅ Approved</a>
    <a href="admin.php?page=dashboard&filter=rejected">❌ Rejected</a>
    <span class="sl">System</span>
    <a href="register.php">🛍️ Seller Register</a>
  </nav>
  <div class="sb-foot">
    <a href="admin.php?logout=1">🚪 Logout</a>
  </div>
</div>

<div class="main">
  <div class="topbar">
    <h1>Seller Management</h1>
    <div class="achip">
      <div class="av"><?= strtoupper(substr($_SESSION['admin_user'],0,1)) ?></div>
      <span><?= htmlspecialchars($_SESSION['admin_user']) ?></span>
    </div>
  </div>

  <div class="content">

    <?php if (isset($_GET['updated'])): ?><div class="notify ok">✅ Seller status updated successfully.</div><?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?><div class="notify ok">🗑️ Seller deleted successfully.</div><?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat"><div class="sico" style="background:#fff7ed">👥</div><div><div class="sv"><?= $counts['all'] ?></div><div class="sl">Total Sellers</div></div></div>
      <div class="stat"><div class="sico" style="background:#fffbeb">⏳</div><div><div class="sv"><?= $counts['pending'] ?></div><div class="sl">Pending</div></div></div>
      <div class="stat"><div class="sico" style="background:#f0fdf4">✅</div><div><div class="sv"><?= $counts['approved'] ?></div><div class="sl">Approved</div></div></div>
      <div class="stat"><div class="sico" style="background:#fff1f2">❌</div><div><div class="sv"><?= $counts['rejected'] ?></div><div class="sl">Rejected</div></div></div>
    </div>

    <!-- Table -->
    <div class="table-card">
      <div class="table-head">
        <h3>All Sellers</h3>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <div class="filters">
            <?php foreach(['all','pending','approved','rejected'] as $f): ?>
            <a href="admin.php?page=dashboard&filter=<?= $f ?><?= $search?"&q=".urlencode($search):'' ?>" class="fbtn <?= $filter===$f?'act':'' ?>"><?= ucfirst($f) ?> (<?= $counts[$f] ?>)</a>
            <?php endforeach; ?>
          </div>
          <form method="GET" class="search-box">
            <input type="hidden" name="page" value="dashboard">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <input type="text" name="q" placeholder="🔍 Search seller..." value="<?= htmlspecialchars($search) ?>">
          </form>
        </div>
      </div>

      <div class="tbl-wrap">
        <?php if (empty($sellers)): ?>
        <div class="empty"><div class="eico">🔍</div><p>No sellers found.</p></div>
        <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Seller</th>
              <th>Email</th>
              <th>Shop</th>
              <th>PAN</th>
              <th>Status</th>
              <th>Registered</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sellers as $i => $s): ?>
            <tr>
              <td style="color:var(--muted);font-size:12px">#SN<?= str_pad($s['id'],5,'0',STR_PAD_LEFT) ?></td>
              <td><div class="seller-name"><?= htmlspecialchars($s['full_name']) ?></div></td>
              <td style="font-size:12.5px"><?= htmlspecialchars($s['email']) ?></td>
              <td><div class="seller-shop"><?= htmlspecialchars($s['shop_name']) ?></div></td>
              <td style="font-family:monospace;font-size:13px"><?= htmlspecialchars($s['pan_number']) ?></td>
              <td><span class="badge" style="background:<?= $sb[$s['status']] ?>;color:<?= $sc[$s['status']] ?>"><?= $si[$s['status']] ?> <?= ucfirst($s['status']) ?></span></td>
              <td style="font-size:12px;color:var(--muted)"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
              <td>
                <div class="acts">
                  <a href="admin.php?page=dashboard&filter=<?= $filter ?>&view=<?= $s['id'] ?>#modal" class="abtn abtn-view">👁 View</a>
                  <?php if($s['status']!=='approved'): ?>
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="seller_id" value="<?= $s['id'] ?>">
                    <input type="hidden" name="status" value="approved">
                    <button type="submit" name="update_status" class="abtn abtn-ok" onclick="return confirm('Approve this seller?')">✅ Approve</button>
                  </form>
                  <?php endif; ?>
                  <?php if($s['status']!=='rejected'): ?>
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="seller_id" value="<?= $s['id'] ?>">
                    <input type="hidden" name="status" value="rejected">
                    <button type="submit" name="update_status" class="abtn abtn-rej" onclick="return confirm('Reject this seller?')">❌ Reject</button>
                  </form>
                  <?php endif; ?>
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="seller_id" value="<?= $s['id'] ?>">
                    <button type="submit" name="delete_seller" class="abtn abtn-del" onclick="return confirm('DELETE this seller permanently?')">🗑️</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php if ($view_seller): ?>
<!-- ── MODAL: Seller Detail ── -->
<div class="modal-bg" id="modal">
  <div class="modal">
    <div class="modal-h">
      <h3>Seller Detail — #SN<?= str_pad($view_seller['id'],5,'0',STR_PAD_LEFT) ?></h3>
      <a href="admin.php?page=dashboard&filter=<?= $filter ?><?= $search?"&q=".urlencode($search):'' ?>"><button class="modal-close">✕</button></a>
    </div>
    <div class="modal-b">
      <div class="mi-grid">
        <div class="mi-row"><span class="ml">Full Name</span><span class="mv"><?= htmlspecialchars($view_seller['full_name']) ?></span></div>
        <div class="mi-row"><span class="ml">Email</span><span class="mv"><?= htmlspecialchars($view_seller['email']) ?></span></div>
        <div class="mi-row"><span class="ml">Address</span><span class="mv"><?= htmlspecialchars($view_seller['address']) ?></span></div>
        <div class="mi-row"><span class="ml">Shop Name</span><span class="mv"><?= htmlspecialchars($view_seller['shop_name']) ?></span></div>
        <div class="mi-row"><span class="ml">PAN Number</span><span class="mv"><?= htmlspecialchars($view_seller['pan_number']) ?></span></div>
        <div class="mi-row"><span class="ml">Status</span><span class="mv"><span class="badge" style="background:<?= $sb[$view_seller['status']] ?>;color:<?= $sc[$view_seller['status']] ?>"><?= $si[$view_seller['status']] ?> <?= ucfirst($view_seller['status']) ?></span></span></div>
        <div class="mi-row"><span class="ml">Registered</span><span class="mv"><?= date('d M Y, h:i A', strtotime($view_seller['created_at'])) ?></span></div>
      </div>

      <h4 style="font-family:'Sora',sans-serif;font-size:13px;font-weight:600;margin-bottom:12px;color:var(--dark)">🪪 Citizenship Documents</h4>
      <div class="doc-grid">
        <div class="doc-item">
          <div class="doc-lbl">📄 Front Side</div>
          <img src="uploads/<?= htmlspecialchars($view_seller['cit_front']) ?>" alt="Citizenship Front" onclick="window.open(this.src)" style="cursor:zoom-in">
        </div>
        <div class="doc-item">
          <div class="doc-lbl">📄 Back Side</div>
          <img src="uploads/<?= htmlspecialchars($view_seller['cit_back']) ?>" alt="Citizenship Back" onclick="window.open(this.src)" style="cursor:zoom-in">
        </div>
      </div>

      <?php if($view_seller['status']==='pending'): ?>
      <div class="modal-actions">
        <form method="POST">
          <input type="hidden" name="seller_id" value="<?= $view_seller['id'] ?>">
          <input type="hidden" name="status" value="approved">
          <button type="submit" name="update_status" class="mac-btn" style="background:#22c55e;color:#fff">✅ Approve Seller</button>
        </form>
        <form method="POST">
          <input type="hidden" name="seller_id" value="<?= $view_seller['id'] ?>">
          <input type="hidden" name="status" value="rejected">
          <button type="submit" name="update_status" class="mac-btn" style="background:#ef4444;color:#fff">❌ Reject Seller</button>
        </form>
      </div>
      <?php elseif($view_seller['status']==='approved'): ?>
      <div class="modal-actions">
        <form method="POST">
          <input type="hidden" name="seller_id" value="<?= $view_seller['id'] ?>">
          <input type="hidden" name="status" value="rejected">
          <button type="submit" name="update_status" class="mac-btn" style="background:#ef4444;color:#fff">❌ Reject Seller</button>
        </form>
      </div>
      <?php else: ?>
      <div class="modal-actions">
        <form method="POST">
          <input type="hidden" name="seller_id" value="<?= $view_seller['id'] ?>">
          <input type="hidden" name="status" value="approved">
          <button type="submit" name="update_status" class="mac-btn" style="background:#22c55e;color:#fff">✅ Approve Seller</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>
</body>
</html>