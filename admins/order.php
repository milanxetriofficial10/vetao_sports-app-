<?php
// ============================================================
// admin/orders.php — SportGhar Admin Panel
// ============================================================
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
include "../databases/db.php";

$admin_name = $_SESSION['user']['name'] ?? 'Admin';

// ── ADMIN LOGIN LOCATION (IP-based) ──
function getAdminLocation() {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = trim(explode(',', $ip)[0]);
    if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1') {
        return ['ip' => 'localhost', 'location' => 'Local Network'];
    }
    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $raw = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,city,regionName,country", false, $ctx);
    if ($raw) {
        $d = json_decode($raw, true);
        if (($d['status'] ?? '') === 'success') {
            $parts = array_filter([$d['city'] ?? '', $d['regionName'] ?? '', $d['country'] ?? '']);
            return ['ip' => $ip, 'location' => implode(', ', $parts)];
        }
    }
    return ['ip' => $ip, 'location' => 'Unknown'];
}
$admin_loc = getAdminLocation();

// ── NOTIFICATION HELPER ──
function sendNotification($conn, $user_id, $message, $order_id, $type = 'order') {
    $user_id  = (int)$user_id;
    $order_id = (int)$order_id;
    $type     = $conn->real_escape_string($type);
    $message  = $conn->real_escape_string($message);
    $conn->query("INSERT INTO notifications (user_id, message, type, related_id, is_read, created_at)
                  VALUES ($user_id, '$message', '$type', $order_id, 0, NOW())");
}

// SMS SIMULATION — log to file (replace with real SMS API like Sparrow/Aakash)
function sendSMS($phone, $message, $order_id) {
    $log = date('Y-m-d H:i:s') . " | Order #$order_id | To: $phone | Msg: $message\n";
    @file_put_contents(__DIR__ . '/sms_log.txt', $log, FILE_APPEND);
    // TODO: Replace with real SMS API call, e.g. Sparrow SMS Nepal:
    // $url = "https://api.sparrowsms.com/v2/sms/";
    // $data = ['token'=>'YOUR_TOKEN','from'=>'SportGhar','to'=>$phone,'text'=>$message];
    // file_get_contents($url . '?' . http_build_query($data));
}

// ── HANDLE STATUS UPDATES ──
$action_msg      = '';
$action_type     = '';
$sms_preview_msg = '';

if (isset($_POST['action'], $_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];
    $action   = $_POST['action'];

    $ord = $conn->query("SELECT o.*, u.name as uname, u.phone FROM orders o
                         LEFT JOIN users u ON o.user_id = u.id
                         WHERE o.id = $order_id")->fetch_assoc();

    if ($ord && in_array($action, ['confirmed','cancelled','delivered','processing'])) {
        $new_status = $conn->real_escape_string($action);
        $conn->query("UPDATE orders SET status = '$new_status' WHERE id = $order_id");

        $order_str  = str_pad($order_id, 5, '0', STR_PAD_LEFT);
        $amount     = number_format($ord['total']);
        $phone      = $ord['phone'] ?? '';
        $notif_type = 'order';

        // Per-status notification + SMS message
        switch ($action) {
            case 'confirmed':
                $notif_msg = "🎉 Tapaiको Order #$order_str confirm bhayo! Rs. $amount — Chado nai dispatch hunecha.";
                $sms_msg   = "SportGhar: Tapaiको Order #$order_str confirmed! Rs.$amount — Chado dispatch garinchhau. Dhanyabad!";
                $notif_type = 'order';
                break;

            case 'processing':
                $notif_msg = "⚙️ Tapaiको Order #$order_str pack garīdai chau! Rs. $amount — Chado dispatch hunecha, thikai rakhnos.";
                $sms_msg   = "SportGhar: Tapaiको Order #$order_str pack garīdai chau. Chado dispatch hunecha! Track garna app kholnus.";
                $notif_type = 'order';
                break;

            case 'delivered':
                $notif_msg = "✅ Tapaiको Order #$order_str deliver bhayo! Rs. $amount — SportGhar ma kina garnu bhayeko dhanyabad. Enjoy garnus!";
                $sms_msg   = "SportGhar: Tapaiको Order #$order_str deliver bhayo! Rs.$amount — Enjoy garnus. Feedback dinu hola!";
                $notif_type = 'delivery';
                break;

            case 'cancelled':
                $notif_msg = "❌ Tapaiको Order #$order_str cancel bhayo. Rs. $amount — Samasya bhaye hamīlāī samparka garnus. Ma\'af garnus!";
                $sms_msg   = "SportGhar: Tapaiको Order #$order_str cancel bhayo. Samasya bhaye hamīlāī call garnus. Dhanyabad!";
                $notif_type = 'cancelled';
                break;

            default:
                $notif_msg = "Tapaiको Order #$order_str ko status update bhayo: " . ucfirst($action);
                $sms_msg   = "SportGhar: Order #$order_str status: " . ucfirst($action) . ". Dhanyabad!";
        }

        // Save DB notification
        sendNotification($conn, $ord['user_id'], $notif_msg, $order_id, $notif_type);

        // Send SMS (simulated / log)
        if (!empty($phone)) {
            sendSMS($phone, $sms_msg, $order_id);
        }

        $action_msg      = "Order #$order_str → <strong>" . ucfirst($action) . "</strong> &amp; notification + SMS pathaiyo!";
        $action_type     = ($action === 'cancelled') ? 'error' : 'success';
        $sms_preview_msg = $sms_msg;
    }
}

// ── FILTERS ──
$filter_status = $_GET['status'] ?? 'all';
$search        = trim($_GET['search'] ?? '');

$where = "WHERE 1=1";
if ($filter_status !== 'all') {
    $fs    = $conn->real_escape_string($filter_status);
    $where .= " AND o.status = '$fs'";
}
if ($search !== '') {
    $s     = $conn->real_escape_string($search);
    $where .= " AND (u.name LIKE '%$s%' OR u.email LIKE '%$s%' OR o.id = " . (int)$search . ")";
}

// ── FETCH ORDERS ──
$orders_result = $conn->query("
    SELECT o.*, u.name as uname, u.email, u.phone
    FROM orders o LEFT JOIN users u ON o.user_id = u.id
    $where ORDER BY o.created_at DESC LIMIT 100
");
$orders = [];
while ($row = $orders_result->fetch_assoc()) $orders[] = $row;

// ── STATS ──
$stats = [];
foreach (['pending','confirmed','processing','delivered','cancelled'] as $s) {
    $r = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='$s'");
    $stats[$s] = (int)$r->fetch_assoc()['c'];
}
$rev_r         = $conn->query("SELECT SUM(total) as rev FROM orders WHERE status != 'cancelled'");
$total_revenue = (float)$rev_r->fetch_assoc()['rev'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orders — SportGhar Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
    --bg:#080c14; --panel:#0f1520; --card:#131a27;
    --border:rgba(255,255,255,0.07);
    --accent:#f97316; --blue:#3b82f6; --green:#22c55e;
    --red:#ef4444; --yellow:#f59e0b; --purple:#a855f7;
    --text:#f1f5f9; --muted:#64748b; --r:12px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Barlow',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;}

/* SIDEBAR */
.sidebar{
    width:240px;flex-shrink:0;background:var(--panel);
    border-right:1px solid var(--border);
    display:flex;flex-direction:column;padding:28px 0;
    position:sticky;top:0;height:100vh;overflow-y:auto;
}
.sidebar-logo{
    font-family:'Barlow Condensed',sans-serif;font-size:26px;
    font-weight:900;text-transform:uppercase;letter-spacing:1px;
    padding:0 24px 28px;border-bottom:1px solid var(--border);color:var(--text);
}
.sidebar-logo span{color:var(--accent);}
.sidebar-section{
    padding:20px 16px 8px;font-size:11px;font-weight:700;
    letter-spacing:1.5px;color:var(--muted);text-transform:uppercase;
}
.sidebar-link{
    display:flex;align-items:center;gap:12px;
    padding:11px 24px;color:var(--muted);text-decoration:none;
    font-size:14px;font-weight:600;border-left:3px solid transparent;transition:0.2s;
}
.sidebar-link:hover,.sidebar-link.active{
    color:var(--text);background:rgba(249,115,22,0.08);border-left-color:var(--accent);
}
.sidebar-link i{width:18px;text-align:center;}
.sidebar-bottom{margin-top:auto;padding:20px 24px 0;border-top:1px solid var(--border);}
.admin-pill{display:flex;align-items:center;gap:10px;}
.admin-avatar{
    width:34px;height:34px;border-radius:50%;
    background:linear-gradient(135deg,var(--accent),#fb923c);
    display:flex;align-items:center;justify-content:center;
    font-size:14px;font-weight:800;color:#fff;flex-shrink:0;
}
.location-bar{
    display:flex;align-items:flex-start;gap:8px;
    background:rgba(59,130,246,0.08);
    border:1px solid rgba(59,130,246,0.18);
    border-radius:10px;padding:10px 13px;margin-top:14px;
    font-size:12px;color:var(--blue);
}
.location-bar i{flex-shrink:0;margin-top:2px;}
.location-bar .loc-text{line-height:1.45;}
.location-bar .loc-name{font-weight:700;}
.location-bar .loc-ip{font-size:10px;opacity:.6;margin-top:1px;}

/* MAIN */
.main{flex:1;padding:32px;overflow-y:auto;min-width:0;}

/* TOAST */
.toast{
    display:flex;align-items:flex-start;gap:14px;
    padding:16px 20px;border-radius:var(--r);
    margin-bottom:24px;font-size:14px;font-weight:600;
    animation:fadeDown 0.3s ease;
}
@keyframes fadeDown{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}
.toast.success{background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.25);color:var(--green);}
.toast.error  {background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.25); color:var(--red);}
.toast-main{flex:1;}
.toast-title{margin-bottom:4px;}
.toast-sms{
    font-size:12px;font-weight:500;
    color:var(--muted);margin-top:6px;
    display:flex;align-items:flex-start;gap:6px;
}
.toast-sms i{flex-shrink:0;color:var(--green);margin-top:1px;}
.notif-sent-badge{
    display:inline-flex;align-items:center;gap:5px;
    font-size:11px;font-weight:800;padding:3px 10px;
    border-radius:100px;background:rgba(34,197,94,0.12);
    border:1px solid rgba(34,197,94,0.25);color:var(--green);
    white-space:nowrap;
}

/* PAGE TITLE */
.page-title{
    font-family:'Barlow Condensed',sans-serif;font-size:36px;
    font-weight:900;text-transform:uppercase;letter-spacing:-0.5px;margin-bottom:28px;
}
.page-title span{color:var(--accent);}

/* STATS */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:28px;}
.stat-card{
    background:var(--card);border:1px solid var(--border);
    border-radius:var(--r);padding:18px 20px;
    display:flex;flex-direction:column;gap:4px;transition:border-color 0.2s;
}
.stat-card:hover{border-color:rgba(249,115,22,0.3);}
.stat-label{font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--muted);}
.stat-value{font-family:'Barlow Condensed',sans-serif;font-size:32px;font-weight:900;line-height:1;}
.stat-value.orange{color:var(--accent);}
.stat-value.green {color:var(--green);}
.stat-value.blue  {color:var(--blue);}
.stat-value.yellow{color:var(--yellow);}
.stat-value.red   {color:var(--red);}
.stat-value.purple{color:var(--purple);}

/* TOOLBAR */
.toolbar{display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;}
.filter-tabs{display:flex;gap:6px;flex-wrap:wrap;}
.filter-tab{
    padding:7px 16px;border-radius:100px;font-size:12px;font-weight:700;
    text-transform:uppercase;letter-spacing:0.5px;border:1px solid var(--border);
    background:transparent;color:var(--muted);cursor:pointer;text-decoration:none;transition:0.2s;
}
.filter-tab:hover{color:var(--text);border-color:rgba(255,255,255,0.15);}
.filter-tab.active{background:var(--accent);color:#fff;border-color:var(--accent);}
.search-wrap{
    margin-left:auto;display:flex;align-items:center;gap:8px;
    background:var(--card);border:1px solid var(--border);
    border-radius:var(--r);padding:0 14px;
}
.search-wrap i{color:var(--muted);font-size:13px;}
.search-wrap input{
    background:transparent;border:none;outline:none;
    color:var(--text);font-family:'Barlow',sans-serif;
    font-size:14px;padding:10px 0;width:200px;
}
.search-wrap input::placeholder{color:var(--muted);}

/* TABLE */
.orders-panel{background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;}
.orders-table{width:100%;border-collapse:collapse;}
.orders-table th{
    padding:13px 18px;background:rgba(255,255,255,0.03);
    border-bottom:1px solid var(--border);
    font-size:11px;font-weight:700;letter-spacing:1px;
    text-transform:uppercase;color:var(--muted);text-align:left;white-space:nowrap;
}
.orders-table td{padding:16px 18px;border-bottom:1px solid var(--border);font-size:14px;vertical-align:middle;}
.orders-table tr:last-child td{border-bottom:none;}
.orders-table tbody tr{transition:background 0.2s;}
.orders-table tbody tr:hover td{background:rgba(249,115,22,0.028);}

.order-id{font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:16px;color:var(--accent);}
.customer-name{font-weight:600;margin-bottom:2px;}
.customer-meta{font-size:12px;color:var(--muted);}
.order-amount{font-family:'Barlow Condensed',sans-serif;font-size:18px;font-weight:800;}

/* STATUS PILLS */
.status-pill{
    display:inline-flex;align-items:center;gap:5px;
    padding:4px 12px;border-radius:100px;
    font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;white-space:nowrap;
}
.s-pending   {background:rgba(245,158,11,0.12);color:var(--yellow);border:1px solid rgba(245,158,11,0.25);}
.s-confirmed {background:rgba(34,197,94,0.12); color:var(--green); border:1px solid rgba(34,197,94,0.25);}
.s-processing{background:rgba(59,130,246,0.12);color:var(--blue);  border:1px solid rgba(59,130,246,0.25);}
.s-delivered {background:rgba(168,85,247,0.12);color:var(--purple);border:1px solid rgba(168,85,247,0.25);}
.s-cancelled {background:rgba(239,68,68,0.12); color:var(--red);   border:1px solid rgba(239,68,68,0.25);}

/* ACTION FORMS */
.action-form{display:flex;align-items:center;gap:8px;}
.status-select{
    background:var(--panel);border:1px solid var(--border);
    border-radius:8px;color:var(--text);font-family:'Barlow',sans-serif;
    font-size:13px;padding:7px 10px;cursor:pointer;outline:none;transition:0.2s;
}
.status-select:focus{border-color:var(--accent);}
.btn-act{
    padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;
    font-family:'Barlow',sans-serif;cursor:pointer;border:none;
    display:flex;align-items:center;gap:5px;transition:0.2s;
    text-transform:uppercase;letter-spacing:0.5px;
}
.btn-act.confirm{background:rgba(34,197,94,0.15);color:var(--green);border:1px solid rgba(34,197,94,0.3);}
.btn-act.confirm:hover{background:rgba(34,197,94,0.28);}
.btn-act.reject {background:rgba(239,68,68,0.15);color:var(--red);  border:1px solid rgba(239,68,68,0.3);}
.btn-act.reject:hover{background:rgba(239,68,68,0.28);}

.date-cell{font-size:12px;color:var(--muted);}
.items-count{
    display:inline-flex;align-items:center;gap:5px;
    background:rgba(249,115,22,0.1);border:1px solid rgba(249,115,22,0.2);
    color:var(--accent);font-size:11px;font-weight:700;padding:3px 10px;border-radius:100px;
}

/* EMPTY */
.empty-orders{text-align:center;padding:70px 20px;color:var(--muted);}
.empty-orders i{font-size:52px;opacity:0.2;display:block;margin-bottom:14px;}

@media(max-width:1100px){.sidebar{display:none;}.main{padding:20px;}}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-logo">Sport<span>Ghar</span></div>
    <div class="sidebar-section">Main</div>
    <a href="dashboard.php" class="sidebar-link"><i class="fa fa-chart-pie"></i> Dashboard</a>
    <a href="orders.php"    class="sidebar-link active"><i class="fa fa-box"></i> Orders</a>
    <a href="products.php"  class="sidebar-link"><i class="fa fa-tag"></i> Products</a>
    <a href="users.php"     class="sidebar-link"><i class="fa fa-users"></i> Users</a>
    <div class="sidebar-section">System</div>
    <a href="../index.php"  class="sidebar-link"><i class="fa fa-globe"></i> View Site</a>
    <a href="../logout.php" class="sidebar-link"><i class="fa fa-sign-out-alt"></i> Logout</a>

    <div class="sidebar-bottom">
        <div class="admin-pill">
            <div class="admin-avatar"><?php echo strtoupper(substr($admin_name,0,1)); ?></div>
            <div>
                <div style="font-size:13px;font-weight:700;"><?php echo htmlspecialchars($admin_name); ?></div>
                <div style="font-size:11px;color:var(--muted);">Administrator</div>
            </div>
        </div>
        <!-- ADMIN LOCATION -->
        <div class="location-bar">
            <i class="fa fa-map-marker-alt"></i>
            <div class="loc-text">
                <div class="loc-name"><?php echo htmlspecialchars($admin_loc['location']); ?></div>
                <div class="loc-ip"><?php echo htmlspecialchars($admin_loc['ip']); ?></div>
            </div>
        </div>
    </div>
</aside>

<!-- MAIN -->
<main class="main">

    <!-- TOAST -->
    <?php if ($action_msg): ?>
    <div class="toast <?php echo $action_type; ?>">
        <i class="fa <?php echo $action_type === 'success' ? 'fa-check-circle' : 'fa-times-circle'; ?>" style="margin-top:2px;"></i>
        <div class="toast-main">
            <div class="toast-title">
                <?php echo $action_msg; ?>
                <span class="notif-sent-badge" style="margin-left:8px;">
                    <i class="fa fa-bell"></i> Notif sent
                </span>
                <span class="notif-sent-badge" style="margin-left:4px;">
                    <i class="fa fa-comment-sms"></i> SMS sent
                </span>
            </div>
            <?php if ($sms_preview_msg): ?>
            <div class="toast-sms">
                <i class="fa fa-comment-sms"></i>
                <span><?php echo htmlspecialchars($sms_preview_msg); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="page-title">Orders <span>Panel</span></div>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value orange">Rs.<?php echo number_format($total_revenue/1000,1); ?>k</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pending</div>
            <div class="stat-value yellow"><?php echo $stats['pending']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Confirmed</div>
            <div class="stat-value green"><?php echo $stats['confirmed']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Processing</div>
            <div class="stat-value blue"><?php echo $stats['processing']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Delivered</div>
            <div class="stat-value purple"><?php echo $stats['delivered']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Cancelled</div>
            <div class="stat-value red"><?php echo $stats['cancelled']; ?></div>
        </div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar">
        <div class="filter-tabs">
            <?php
            $tabs = ['all'=>'All','pending'=>'Pending','confirmed'=>'Confirmed',
                     'processing'=>'Processing','delivered'=>'Delivered','cancelled'=>'Cancelled'];
            foreach ($tabs as $val => $label):
                $active = ($filter_status === $val) ? 'active' : '';
            ?>
            <a href="?status=<?php echo $val; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>"
               class="filter-tab <?php echo $active; ?>">
                <?php echo $label; ?>
                <?php if ($val !== 'all' && !empty($stats[$val])): ?>
                    (<?php echo $stats[$val]; ?>)
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <form method="get" class="search-wrap">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
            <i class="fa fa-search"></i>
            <input type="text" name="search" placeholder="Search name, email, order ID..."
                   value="<?php echo htmlspecialchars($search); ?>">
        </form>
    </div>

    <!-- TABLE -->
    <div class="orders-panel">
    <?php if (empty($orders)): ?>
        <div class="empty-orders">
            <i class="fa fa-inbox"></i>
            <p>No orders found.</p>
        </div>
    <?php else: ?>
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Items</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Update Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o):
                $oid_str    = str_pad($o['id'], 5, '0', STR_PAD_LEFT);
                $sc         = 's-' . $o['status'];
                $ic_r       = $conn->query("SELECT COUNT(*) as c FROM order_items WHERE order_id=".(int)$o['id']);
                $item_count = $ic_r ? (int)$ic_r->fetch_assoc()['c'] : 0;
            ?>
            <tr>
                <td><span class="order-id">#<?php echo $oid_str; ?></span></td>
                <td>
                    <div class="customer-name"><?php echo htmlspecialchars($o['uname'] ?? '—'); ?></div>
                    <div class="customer-meta"><?php echo htmlspecialchars($o['email'] ?? ''); ?></div>
                    <?php if (!empty($o['phone'])): ?>
                    <div class="customer-meta">
                        <i class="fa fa-phone" style="font-size:10px;"></i>
                        <?php echo htmlspecialchars($o['phone']); ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td><span class="order-amount">Rs. <?php echo number_format($o['total']); ?></span></td>
                <td>
                    <?php if ($item_count > 0): ?>
                    <span class="items-count"><i class="fa fa-box"></i> <?php echo $item_count; ?></span>
                    <?php else: ?>
                    <span style="color:var(--muted);font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px;color:var(--muted);">
                    <?php echo ucfirst($o['payment_method'] ?? 'esewa'); ?>
                </td>
                <td>
                    <span class="status-pill <?php echo $sc; ?>">
                        <i class="fa fa-circle" style="font-size:7px;"></i>
                        <?php echo ucfirst($o['status']); ?>
                    </span>
                </td>
                <td class="date-cell">
                    <?php echo date("d M Y", strtotime($o['created_at'])); ?><br>
                    <span style="opacity:.6;"><?php echo date("h:i A", strtotime($o['created_at'])); ?></span>
                </td>
                <td>
                    <?php if ($o['status'] === 'pending'): ?>
                    <!-- PENDING: Accept / Reject -->
                    <div style="display:flex;gap:8px;">
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                            <input type="hidden" name="action" value="confirmed">
                            <button class="btn-act confirm" type="submit"
                                    onclick="return confirm('Order #<?php echo $oid_str; ?> confirm garne? User lai notification + SMS pathaucha.')">
                                <i class="fa fa-check"></i> Accept
                            </button>
                        </form>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                            <input type="hidden" name="action" value="cancelled">
                            <button class="btn-act reject" type="submit"
                                    onclick="return confirm('Order #<?php echo $oid_str; ?> cancel garne? User lai notification + SMS pathaucha.')">
                                <i class="fa fa-times"></i> Reject
                            </button>
                        </form>
                    </div>

                    <?php elseif ($o['status'] !== 'cancelled' && $o['status'] !== 'delivered'): ?>
                    <!-- CONFIRMED / PROCESSING: Dropdown -->
                    <form method="post" class="action-form" onsubmit="return confirmMove(this, '<?php echo $oid_str; ?>')">
                        <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                        <select name="action" class="status-select" required>
                            <option value="">— Move to —</option>
                            <option value="processing">⚙️ Processing</option>
                            <option value="delivered">🚚 Delivered</option>
                            <option value="cancelled">❌ Cancelled</option>
                        </select>
                        <button class="btn-act confirm" type="submit">
                            <i class="fa fa-arrow-right"></i> Go
                        </button>
                    </form>

                    <?php else: ?>
                    <span style="font-size:12px;color:var(--muted);">
                        <?php echo $o['status'] === 'delivered' ? '✅ Done' : '🚫 Cancelled'; ?>
                    </span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>

</main>

<script>
function confirmMove(form, orderId) {
    var sel   = form.querySelector('select[name="action"]');
    var val   = sel.value;
    if (!val) { alert('Pehile status select garnus!'); return false; }

    var labels = {
        processing: '⚙️ Processing',
        delivered:  '🚚 Delivered',
        cancelled:  '❌ Cancelled'
    };
    var msgs = {
        processing: 'Order pack garna suru garinchhau — user lai "Processing" notification + SMS pathaucha.',
        delivered:  'Order deliver mark garinchhau — user lai "Delivered" notification + SMS pathaucha.',
        cancelled:  'Order cancel garinchhau — user lai "Cancelled" notification + SMS pathaucha.'
    };

    var label = labels[val] || val;
    var extra = msgs[val] || '';
    return confirm('Order #' + orderId + ' → ' + label + ' move garne?\n\n' + extra);
}
</script>

</body>
</html>
<?php ob_end_flush(); ?>