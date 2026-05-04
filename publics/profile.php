<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../databases/db.php";
$conn = getDB();



if (!isset($_SESSION['user']['id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user']['id'];
$user    = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$msg     = '';
$msg_type = '';

// ── HANDLE PROFILE UPDATE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name    = trim($conn->real_escape_string($_POST['name'] ?? ''));
    $phone   = trim($conn->real_escape_string($_POST['phone'] ?? ''));
    $address = trim($conn->real_escape_string($_POST['address'] ?? ''));

    if ($name && $phone) {
        $conn->query("UPDATE users SET name='$name', phone='$phone', address='$address' WHERE id=$user_id");
        $_SESSION['user']['name'] = $name;
        $user['name']    = $name;
        $user['phone']   = $phone;
        $user['address'] = $address;
        $msg      = "Profile successfully update bhayo!";
        $msg_type = "success";
    } else {
        $msg      = "Name ra Phone fill garnu parcha.";
        $msg_type = "error";
    }
}

// ── HANDLE PASSWORD CHANGE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old_pass  = $_POST['old_password'] ?? '';
    $new_pass  = $_POST['new_password'] ?? '';
    $conf_pass = $_POST['confirm_password'] ?? '';

    if (password_verify($old_pass, $user['password'])) {
        if ($new_pass === $conf_pass && strlen($new_pass) >= 6) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password='$hashed' WHERE id=$user_id");
            $msg      = "Password change bhayo! Aagadi login garda naya password use garnus.";
            $msg_type = "success";
        } else {
            $msg      = "Naya password mismatch chha ya 6 characters bhanda sano chha.";
            $msg_type = "error";
        }
    } else {
        $msg      = "Puraano password galat chha.";
        $msg_type = "error";
    }
}

// ── ORDER STATS ──
$total_orders    = (int)$conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id=$user_id")->fetch_assoc()['c'];
$delivered_count = (int)$conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id=$user_id AND status='delivered'")->fetch_assoc()['c'];
$pending_count   = (int)$conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id=$user_id AND status='pending'")->fetch_assoc()['c'];
$total_spend_r   = $conn->query("SELECT SUM(total) as s FROM orders WHERE user_id=$user_id AND status!='cancelled'");
$total_spend     = (float)$total_spend_r->fetch_assoc()['s'];

// Recent 3 orders
$recent_r = $conn->query("SELECT * FROM orders WHERE user_id=$user_id ORDER BY created_at DESC LIMIT 3");
$recents  = [];
while ($row = $recent_r->fetch_assoc()) $recents[] = $row;

$avatar_letter = strtoupper(substr($user['name'] ?? 'U', 0, 1));
$member_since  = date("M Y", strtotime($user['created_at'] ?? 'now'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — JerseyGhar</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;500;600;700&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --bg: #f7f5f2;
    --surface: #ffffff;
    --ink: #111017;
    --ink-2: #4a4760;
    --ink-3: #9b99b4;
    --accent: #5b3de8;
    --accent-pale: #ede9ff;
    --accent-mid: #7b5df0;
    --red: #e83d3d;
    --red-pale: #ffeaea;
    --green: #0ba360;
    --green-pale: #dcf5ec;
    --amber: #d97706;
    --amber-pale: #fef3c7;
    --r: 16px;
    --shadow: 0 4px 24px rgba(17,16,23,0.07);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Instrument Sans', sans-serif;
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
}
.page {
    max-width: 900px;
    margin: 0 auto;
    padding: 48px 20px 80px;
}

/* BREADCRUMB */
.breadcrumb {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; color: var(--ink-3); margin-bottom: 32px;
}
.breadcrumb a { color: var(--ink-3); text-decoration: none; }
.breadcrumb a:hover { color: var(--accent); }
.breadcrumb i { font-size: 10px; }

/* PROFILE HERO */
.profile-hero {
    background: linear-gradient(135deg, #5b3de8 0%, #8b6cf7 60%, #a78bfa 100%);
    border-radius: 24px;
    padding: 40px 36px;
    display: flex;
    align-items: center;
    gap: 28px;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    flex-wrap: wrap;
}
.profile-hero::before {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 200px; height: 200px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
}
.profile-hero::after {
    content: '';
    position: absolute;
    bottom: -60px; right: 80px;
    width: 280px; height: 280px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
}
.avatar {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    border: 3px solid rgba(255,255,255,0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Clash Display', sans-serif;
    font-size: 38px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}
.hero-info { position: relative; z-index: 1; flex: 1; }
.hero-name {
    font-family: 'Clash Display', sans-serif;
    font-size: 30px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 6px;
}
.hero-email {
    color: rgba(255,255,255,0.75);
    font-size: 14px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.18);
    border: 1px solid rgba(255,255,255,0.25);
    border-radius: 100px;
    padding: 5px 14px;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
}
.hero-orders-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    color: var(--accent);
    text-decoration: none;
    padding: 11px 24px;
    border-radius: 100px;
    font-weight: 700;
    font-size: 14px;
    transition: all 0.2s;
    position: relative;
    z-index: 1;
}
.hero-orders-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }

/* STATS ROW */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}
.stat-card {
    background: var(--surface);
    border-radius: 18px;
    border: 1.5px solid rgba(17,16,23,0.07);
    padding: 22px 20px;
    text-align: center;
    box-shadow: var(--shadow);
    transition: transform 0.2s;
}
.stat-card:hover { transform: translateY(-2px); }
.stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    margin: 0 auto 12px;
}
.si-accent { background: var(--accent-pale); color: var(--accent); }
.si-green  { background: var(--green-pale);  color: var(--green);  }
.si-amber  { background: var(--amber-pale);  color: var(--amber);  }
.si-red    { background: var(--red-pale);    color: var(--red);    }
.stat-num {
    font-family: 'Clash Display', sans-serif;
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 4px;
}
.stat-label { font-size: 12px; color: var(--ink-3); font-weight: 600; }

/* GRID */
.content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
@media(max-width: 700px) { .content-grid { grid-template-columns: 1fr; } }

/* CARD */
.card {
    background: var(--surface);
    border-radius: 20px;
    border: 1.5px solid rgba(17,16,23,0.07);
    box-shadow: var(--shadow);
    overflow: hidden;
}
.card-header {
    padding: 20px 24px 0;
    margin-bottom: 20px;
}
.card-title {
    font-family: 'Clash Display', sans-serif;
    font-size: 20px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}
.card-title i { color: var(--accent); font-size: 16px; }
.card-body { padding: 0 24px 24px; }

/* FORM */
.form-group { margin-bottom: 16px; }
.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    color: var(--ink-3);
    margin-bottom: 7px;
}
.form-group input, .form-group textarea {
    width: 100%;
    border: 1.5px solid rgba(17,16,23,0.1);
    border-radius: 12px;
    padding: 11px 14px;
    font-size: 14px;
    font-family: 'Instrument Sans', sans-serif;
    color: var(--ink);
    background: var(--bg);
    outline: none;
    transition: border-color 0.2s;
    resize: vertical;
}
.form-group input:focus, .form-group textarea:focus {
    border-color: var(--accent);
    background: #fff;
}
.form-group input[readonly] {
    color: var(--ink-3);
    cursor: not-allowed;
    background: #f0f0f8;
}
.btn-submit {
    width: 100%;
    padding: 13px;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    font-family: 'Instrument Sans', sans-serif;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-submit:hover { background: #4530c4; transform: translateY(-1px); }

/* RECENT ORDERS */
.recent-order {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 0;
    border-bottom: 1px solid rgba(17,16,23,0.06);
}
.recent-order:last-child { border-bottom: none; }
.ro-id {
    font-family: 'Clash Display', sans-serif;
    font-weight: 700;
    font-size: 15px;
    color: var(--accent);
    white-space: nowrap;
}
.ro-info { flex: 1; }
.ro-date { font-size: 12px; color: var(--ink-3); }
.ro-amount { font-size: 14px; font-weight: 700; color: var(--green); white-space: nowrap; }

/* STATUS PILLS (mini) */
.spill {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 100px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.sp-pending    { background: var(--amber-pale); color: var(--amber); }
.sp-confirmed  { background: var(--green-pale); color: var(--green); }
.sp-processing { background: #dbeafe; color: #2563eb; }
.sp-delivered  { background: #e8f5e9; color: #2e7d32; }
.sp-cancelled  { background: var(--red-pale);   color: var(--red);   }

.view-all-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 16px;
    padding: 10px;
    background: var(--accent-pale);
    color: var(--accent);
    text-decoration: none;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 700;
    transition: all 0.2s;
}
.view-all-btn:hover { background: var(--accent); color: #fff; }

/* TOAST */
.toast {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 20px; border-radius: var(--r);
    margin-bottom: 24px; font-size: 14px; font-weight: 600;
    animation: slideDown 0.3s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.toast.success { background: var(--green-pale); color: var(--green); }
.toast.error   { background: var(--red-pale);   color: var(--red);   }

/* DANGER ZONE */
.danger-section {
    margin-top: 20px;
    border: 1.5px solid rgba(232,61,61,0.15);
    border-radius: 16px;
    padding: 20px 24px;
    background: #fff9f9;
}
.danger-title {
    font-family: 'Clash Display', sans-serif;
    font-size: 16px;
    font-weight: 700;
    color: var(--red);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.danger-desc { font-size: 13px; color: var(--ink-3); margin-bottom: 14px; }
.btn-logout {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    background: var(--red-pale);
    color: var(--red);
    border: 1.5px solid rgba(232,61,61,0.2);
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-logout:hover { background: var(--red); color: #fff; }
</style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="page">

    <!-- BREADCRUMB -->
    <div class="breadcrumb">
        <a href="../publics/index.php">Home</a>
        <i class="fa fa-chevron-right"></i>
        <span>My Profile</span>
    </div>

    <!-- TOAST -->
    <?php if ($msg): ?>
    <div class="toast <?php echo $msg_type; ?>">
        <i class="fa <?php echo $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <!-- HERO -->
    <div class="profile-hero">
        <div class="avatar"><?php echo $avatar_letter; ?></div>
        <div class="hero-info">
            <div class="hero-name"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></div>
            <div class="hero-email">
                <i class="fa fa-envelope"></i>
                <?php echo htmlspecialchars($user['email'] ?? ''); ?>
            </div>
            <div class="hero-badge">
                <i class="fa fa-calendar-alt"></i>
                Member since <?php echo $member_since; ?>
            </div>
        </div>
        <a href="my_orders.php" class="hero-orders-btn">
            <i class="fa fa-box"></i> My Orders
        </a>
    </div>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon si-accent"><i class="fa fa-shopping-bag"></i></div>
            <div class="stat-num"><?php echo $total_orders; ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-green"><i class="fa fa-truck"></i></div>
            <div class="stat-num"><?php echo $delivered_count; ?></div>
            <div class="stat-label">Delivered</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-amber"><i class="fa fa-clock"></i></div>
            <div class="stat-num"><?php echo $pending_count; ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-green"><i class="fa fa-wallet"></i></div>
            <div class="stat-num">Rs.<?php echo number_format($total_spend/1000, 1); ?>k</div>
            <div class="stat-label">Total Spent</div>
        </div>
    </div>

    <!-- CONTENT GRID -->
    <div class="content-grid">

        <!-- EDIT PROFILE -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa fa-user-edit"></i> Edit Profile</div>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="98XXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label>Delivery Address</label>
                        <textarea name="address" rows="2" placeholder="Thamel, Kathmandu..."><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn-submit">
                        <i class="fa fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div>
            <!-- RECENT ORDERS -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <div class="card-title"><i class="fa fa-receipt"></i> Recent Orders</div>
                </div>
                <div class="card-body">
                    <?php if (empty($recents)): ?>
                    <div style="text-align:center;padding:24px;color:var(--ink-3);">
                        <i class="fa fa-box" style="font-size:28px;opacity:0.3;display:block;margin-bottom:10px;"></i>
                        Kुनai order chaina
                    </div>
                    <?php else: ?>
                    <?php foreach ($recents as $r):
                        $sp = 'sp-' . $r['status'];
                    ?>
                    <div class="recent-order">
                        <div class="ro-id">#<?php echo str_pad($r['id'],5,'0',STR_PAD_LEFT); ?></div>
                        <div class="ro-info">
                            <div style="font-size:13px;font-weight:600;margin-bottom:2px;">
                                <?php echo date("d M Y", strtotime($r['created_at'])); ?>
                            </div>
                            <span class="spill <?php echo $sp; ?>"><?php echo ucfirst($r['status']); ?></span>
                        </div>
                        <div class="ro-amount">Rs. <?php echo number_format($r['total']); ?></div>
                    </div>
                    <?php endforeach; ?>
                    <a href="my_orders.php" class="view-all-btn">
                        <i class="fa fa-arrow-right"></i> Sabai Orders Hernus
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CHANGE PASSWORD -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa fa-lock"></i> Change Password</div>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="change_password" value="1">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="old_password" required placeholder="••••••••">
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" required placeholder="Min 6 characters">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required placeholder="Repeat new password">
                        </div>
                        <button type="submit" class="btn-submit" style="background:#111017;">
                            <i class="fa fa-key"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- DANGER ZONE -->
    <div class="danger-section">
        <div class="danger-title"><i class="fa fa-exclamation-triangle"></i> Account</div>
        <div class="danger-desc">Account bata bahar nikalna yo button click garnus.</div>
        <a href="../logout.php" class="btn-logout">
            <i class="fa fa-sign-out-alt"></i> Logout
        </a>
    </div>

</div>

</body>
</html>
<?php ob_end_flush(); ?>