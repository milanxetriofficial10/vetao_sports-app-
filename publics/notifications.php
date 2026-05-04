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

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff/60) . ' min ago';
    if ($diff < 86400)  return floor($diff/3600) . ' hr ago';
    if ($diff < 604800) return floor($diff/86400) . ' days ago';
    return date("d M, Y", strtotime($datetime));
}

// KEY FIX: Parse status from message text first (most reliable source)
function getStatusFromMessage($msg) {
    $m = strtolower($msg);
    // Check 'processing' before 'process' to avoid partial matches
    if (strpos($m, 'processing') !== false) return 'Processing';
    if (strpos($m, 'shipped')    !== false) return 'Shipped';
    if (strpos($m, 'delivered')  !== false) return 'Delivered';
    if (strpos($m, 'cancelled')  !== false) return 'Cancelled';
    if (strpos($m, 'cancel')     !== false) return 'Cancelled';
    if (strpos($m, 'pending')    !== false) return 'Pending';
    return null;
}

function notifMeta($status) {
    switch (strtolower($status)) {
        case 'cancelled':
            return ['icon'=>'fa-times-circle','bg'=>'rgba(239,68,68,0.14)','clr'=>'#ef4444','label'=>'Cancelled','lclr'=>'#ef4444','lbg'=>'rgba(239,68,68,0.12)'];
        case 'delivered':
            return ['icon'=>'fa-box-check','bg'=>'rgba(168,85,247,0.14)','clr'=>'#a855f7','label'=>'Delivered','lclr'=>'#a855f7','lbg'=>'rgba(168,85,247,0.12)'];
        case 'shipped':
            return ['icon'=>'fa-truck-fast','bg'=>'rgba(34,197,94,0.14)','clr'=>'#22c55e','label'=>'Shipped','lclr'=>'#22c55e','lbg'=>'rgba(34,197,94,0.12)'];
        case 'processing':
            return ['icon'=>'fa-gear','bg'=>'rgba(249,115,22,0.14)','clr'=>'#f97316','label'=>'Processing','lclr'=>'#f97316','lbg'=>'rgba(249,115,22,0.12)'];
        case 'pending':
            return ['icon'=>'fa-clock','bg'=>'rgba(245,158,11,0.14)','clr'=>'#f59e0b','label'=>'Pending','lclr'=>'#f59e0b','lbg'=>'rgba(245,158,11,0.12)'];
        default:
            return ['icon'=>'fa-box','bg'=>'rgba(100,116,139,0.14)','clr'=>'#94a3b8','label'=>'Order','lclr'=>'#94a3b8','lbg'=>'rgba(100,116,139,0.12)'];
    }
}

// Reconstruct the SMS text — MUST match seller_order.php exactly
// Order matters: check longer/specific strings first to avoid partial matches
function getSmsText($msg, $product_name = '', $shop_name = '') {
    $m    = strtolower($msg);
    $prod = $product_name ? "'{$product_name}' " : "";
    $shop = $shop_name    ? " - {$shop_name}"     : "";

    // 'processing' must come before 'pending' — both contain 'p', but more importantly
    // 'cancelled' must come before 'cancel', 'delivered' before any partial
    if (strpos($m, 'processing') !== false)
        return "SportGhar: तपाईंको {$prod}अर्डर प्रशोधन भइरहेको छ। चाँडै पठाइनेछ। धन्यवाद!{$shop}";
    if (strpos($m, 'shipped') !== false)
        return "SportGhar: तपाईंको {$prod}अर्डर पठाइसकियो! ट्र्याकिङ लिङ्क पछि प्राप्त हुनेछ।{$shop}";
    if (strpos($m, 'delivered') !== false)
        return "SportGhar: तपाईंको {$prod}अर्डर डेलिभर भयो। कृपया मन पराउनुभयो भने प्रतिक्रिया दिनुहोला!{$shop}";
    if (strpos($m, 'cancelled') !== false || strpos($m, 'cancel') !== false)
        return "SportGhar: तपाईंको {$prod}अर्डर रद्द गरियो। कुनै समस्या भए हामीलाई सम्पर्क गर्नुहोस्।{$shop}";
    if (strpos($m, 'pending') !== false)
        return "SportGhar: तपाईंको {$prod}अर्डर अहिले pending छ। चाँडै प्रशोधन गरिनेछ। धन्यवाद!{$shop}";
    return '';
}

// Format datetime as "d M Y, h:i A" — used for SMS sent time display
function formatSmsTime($datetime) {
    if (!$datetime) return '';
    $ts = strtotime($datetime);
    return date('d M Y, g:i A', $ts); // e.g. "29 Apr 2026, 3:45 PM"
}

function payLabel($method) {
    $m = strtolower($method ?? '');
    if (strpos($m,'cash') !== false || strpos($m,'cod') !== false)
        return ['icon'=>'fa-money-bill-wave','color'=>'#f59e0b','text'=>'Cash on Delivery'];
    if (strpos($m,'esewa') !== false)
        return ['icon'=>'fa-wallet','color'=>'#22c55e','text'=>'eSewa'];
    if (strpos($m,'khalti') !== false)
        return ['icon'=>'fa-wallet','color'=>'#a855f7','text'=>'Khalti'];
    if (strpos($m,'mastercard') !== false || strpos($m,'card') !== false)
        return ['icon'=>'fa-credit-card','color'=>'#60a5fa','text'=>'Card'];
    if (strpos($m,'ime') !== false)
        return ['icon'=>'fa-mobile-screen','color'=>'#f87171','text'=>'IME Pay'];
    if (strpos($m,'bank') !== false)
        return ['icon'=>'fa-building-columns','color'=>'#38bdf8','text'=>'Bank Transfer'];
    return ['icon'=>'fa-circle-dot','color'=>'#94a3b8','text'=>ucfirst($method ?? '')];
}

// ============================================================
// FETCH UNREAD COUNT (before marking as read)
// ============================================================
$unread_result = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $user_id AND is_read = 0");
$unread_count  = (int)$unread_result->fetch_assoc()['cnt'];

// ============================================================
// KEY FIX: Fetch notifications with seller_status from order_items
// We use GROUP BY n.id so each notification appears once even if
// an order has multiple items — we just grab the first item's data.
// ============================================================
$query = "
    SELECT
        n.id,
        n.type,
        n.message,
        n.related_id       AS order_id,
        n.is_read,
        n.created_at,
        o.total,
        o.payment_method,
        ANY_VALUE(oi.seller_status) AS seller_status,
        ANY_VALUE(oi.jersey_name)   AS product_name,
        ANY_VALUE(s.shop_name)      AS shop_name
    FROM notifications n
    LEFT JOIN orders o
        ON n.related_id = o.id
    LEFT JOIN order_items oi
        ON oi.order_id = n.related_id
    LEFT JOIN jerseys j
        ON oi.product_id = j.id
    LEFT JOIN shops s
        ON j.shop_id = s.id
    WHERE n.user_id = $user_id
    GROUP BY
        n.id,
        n.type,
        n.message,
        n.related_id,
        n.is_read,
        n.created_at,
        o.total,
        o.payment_method
    ORDER BY n.created_at DESC
    LIMIT 80
";
$result        = $conn->query($query);
$notifications = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// Mark all as read after fetching
$conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id AND is_read = 0");

// ============================================================
// BUILD JSON FOR JAVASCRIPT
// Status priority: message text → seller_status → 'Pending'
// This is the core fix — we never use orders.status anymore.
// ============================================================
$jsonNotifs = [];
foreach ($notifications as $notif) {
    $status   = getStatusFromMessage($notif['message'])
                ?? $notif['seller_status']
                ?? 'Pending';
    $meta     = notifMeta($status);
    $sms_text = getSmsText($notif['message'], $notif['product_name'] ?? '', $notif['shop_name'] ?? '');
    $pay_info = payLabel($notif['payment_method'] ?? '');

    $jsonNotifs[] = [
        'id'             => (int)$notif['id'],
        'order_id'       => (int)$notif['order_id'],
        'shop'           => $notif['shop_name']    ?? '',
        'product'        => $notif['product_name'] ?? '',
        'message'        => $notif['message']      ?? '',
        'status'         => $status,
        'total'          => $notif['total']        ?? 0,
        'payment_method' => $notif['payment_method'] ?? '',
        'created_at'     => $notif['created_at']   ?? '',
        'is_unread'      => (int)$notif['is_read'] === 0,
        'meta'           => $meta,
        'sms_text'       => $sms_text,
        // SMS was sent at the same moment the notification was created
        'sms_sent_at'    => !empty($sms_text) ? formatSmsTime($notif['created_at']) : '',
        'pay_info'       => $pay_info,
    ];
}

include "../includes/header.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications — SportGhar</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800;900&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --bg:      #0b0e17;
    --surface: #12161f;
    --border:  rgba(255,255,255,0.07);
    --accent:  #f97316;
    --green:   #22c55e;
    --red:     #ef4444;
    --blue:    #3b82f6;
    --purple:  #a855f7;
    --text:    #f1f5f9;
    --muted:   #64748b;
}
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family: 'Barlow', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
}

/* ── Page wrapper ── */
.page-wrap {
    max-width: 780px;
    margin: 0 auto;
    padding: 40px 20px 100px;
}

/* ── Header ── */
.notif-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 28px;
    flex-wrap: wrap;
    gap: 12px;
}
.notif-header h1 {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: clamp(28px, 5vw, 44px);
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.new-badge {
    font-size: 12px;
    font-weight: 800;
    background: var(--red);
    color: #fff;
    padding: 4px 13px;
    border-radius: 100px;
    animation: pulse 2s ease infinite;
}
@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,.45); }
    50%       { box-shadow: 0 0 0 8px rgba(239,68,68,0); }
}
.total-count {
    font-size: 13px;
    color: var(--muted);
}

/* ── Filter bar ── */
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 30px;
    background: rgba(255,255,255,0.03);
    padding: 8px 12px;
    border-radius: 60px;
    border: 1px solid var(--border);
}
.filter-btn {
    background: transparent;
    border: 1px solid rgba(255,255,255,0.12);
    color: var(--muted);
    font-size: 12px;
    font-weight: 700;
    padding: 6px 16px;
    border-radius: 40px;
    cursor: pointer;
    transition: all 0.2s;
    font-family: 'Barlow Condensed', sans-serif;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.filter-btn.active {
    background: var(--accent);
    border-color: var(--accent);
    color: #fff;
}
.filter-btn:hover:not(.active) {
    border-color: var(--accent);
    color: var(--text);
}

/* ── Spinner ── */
.spinner {
    display: inline-block;
    width: 18px;
    height: 18px;
    border: 2px solid rgba(255,255,255,0.15);
    border-radius: 50%;
    border-top-color: var(--accent);
    animation: spin 0.8s linear infinite;
    vertical-align: middle;
    margin-right: 8px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Date divider ── */
.date-divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 24px 0 10px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--muted);
}
.date-divider::before,
.date-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
}

/* ── Notification list ── */
.notif-list { display: flex; flex-direction: column; gap: 12px; }

/* ── Notification card ── */
.notif-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    display: flex;
    gap: 16px;
    padding: 18px 20px;
    position: relative;
    overflow: hidden;
    transition: transform 0.18s, box-shadow 0.18s, border-color 0.18s;
    text-decoration: none;
    color: inherit;
    animation: slideUp 0.35s ease both;
    cursor: pointer;
}
.notif-card:hover {
    border-color: rgba(249,115,22,0.35);
    box-shadow: 0 8px 32px rgba(0,0,0,.45);
    transform: translateY(-2px);
}
.notif-card.unread {
    border-color: rgba(249,115,22,0.22);
    background: linear-gradient(105deg, rgba(249,115,22,0.06), var(--surface) 55%);
}
.notif-card.unread::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3px;
    background: var(--accent);
    border-radius: 3px 0 0 3px;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Unread dot */
.unread-dot {
    position: absolute;
    top: 18px; right: 18px;
    width: 8px; height: 8px;
    border-radius: 50%;
    background: var(--accent);
    box-shadow: 0 0 0 3px rgba(249,115,22,0.2);
}

/* Arrow hint on hover */
.arrow-hint {
    position: absolute;
    right: 18px; bottom: 16px;
    font-size: 11px;
    color: var(--accent);
    opacity: 0;
    transform: translateX(-5px);
    transition: opacity 0.2s, transform 0.2s;
    display: flex;
    align-items: center;
    gap: 4px;
    font-weight: 700;
    font-family: 'Barlow Condensed', sans-serif;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.notif-card:hover .arrow-hint { opacity: 1; transform: translateX(0); }

/* ── Icon circle ── */
.notif-icon {
    width: 48px; height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
    margin-top: 2px;
}

/* ── Card body ── */
.notif-body { flex: 1; min-width: 0; }

/* Shop + product badges */
.badge-row { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.shop-badge {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 30px;
    padding: 2px 10px;
    font-size: 11px; font-weight: 700;
    color: var(--muted);
}
.shop-badge i { font-size: 10px; color: var(--accent); }
.product-badge {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(59,130,246,0.12);
    border: 1px solid rgba(59,130,246,0.2);
    border-radius: 30px;
    padding: 2px 10px;
    font-size: 11px; font-weight: 700;
    color: #60a5fa;
}

/* Status type label */
.notif-type-label {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 10px; font-weight: 800; letter-spacing: 1px;
    text-transform: uppercase;
    padding: 2px 10px;
    border-radius: 100px;
    margin-bottom: 8px;
    font-family: 'Barlow Condensed', sans-serif;
}

/* Message text */
.notif-msg {
    font-size: 14px;
    line-height: 1.65;
    color: var(--text);
    margin-bottom: 12px;
}

/* SMS strip */
.sms-strip {
    display: flex; align-items: flex-start; gap: 10px;
    background: rgba(34,197,94,0.05);
    border: 1px solid rgba(34,197,94,0.15);
    border-radius: 10px;
    padding: 9px 12px;
    margin-bottom: 14px;
}
.sms-icon {
    flex-shrink: 0;
    width: 26px; height: 26px;
    border-radius: 50%;
    background: rgba(34,197,94,0.12);
    color: var(--green);
    display: flex; align-items: center; justify-content: center;
    font-size: 12px;
}
.sms-label {
    font-size: 9px; font-weight: 800; letter-spacing: 1px;
    text-transform: uppercase; color: var(--green); margin-bottom: 3px;
}
.sms-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;
    flex-wrap: wrap;
}
.sms-time {
    font-size: 10px;
    color: var(--muted);
    display: flex;
    align-items: center;
    gap: 4px;
    font-weight: 600;
    white-space: nowrap;
}
.sms-time i { font-size: 9px; }
.sms-text { font-size: 12px; color: var(--muted); line-height: 1.55; }

/* Footer row */
.notif-footer {
    display: flex; align-items: center;
    justify-content: space-between;
    flex-wrap: wrap; gap: 8px;
}
.notif-time {
    font-size: 12px; color: var(--muted);
    display: flex; align-items: center; gap: 5px;
    cursor: help;
}
.exact-time { display: none; }
.notif-time:hover .exact-time { display: inline; }
.notif-right {
    display: flex; align-items: center;
    gap: 8px; flex-wrap: wrap;
}

/* Status pill */
.sp {
    font-size: 10px; font-weight: 800;
    font-family: 'Barlow Condensed', sans-serif;
    letter-spacing: 0.5px; text-transform: uppercase;
    padding: 3px 11px; border-radius: 100px;
}
.sp-pending    { background: rgba(245,158,11,.12); color: #f59e0b; }
.sp-processing { background: rgba(249,115,22,.12); color: #f97316; }
.sp-shipped    { background: rgba(34,197,94,.12);  color: #22c55e; }
.sp-delivered  { background: rgba(168,85,247,.12); color: #a855f7; }
.sp-cancelled  { background: rgba(239,68,68,.12);  color: #ef4444; }

/* Amount */
.order-amt {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 18px; font-weight: 800; color: var(--green);
}

/* Payment pill */
.pay-pill {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 10px; font-weight: 700;
    padding: 3px 10px; border-radius: 100px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
}

/* ── Empty state ── */
.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: var(--muted);
}
.empty-icon { font-size: 60px; opacity: 0.08; display: block; margin-bottom: 20px; }
.empty-state h2 {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 28px; font-weight: 900;
    text-transform: uppercase;
    margin-bottom: 8px;
    opacity: 0.35;
}
.empty-state p { font-size: 14px; opacity: 0.5; }

/* ── Footer note ── */
.footer-note {
    text-align: center;
    margin-top: 32px;
    font-size: 13px;
    color: var(--muted);
}

/* ── Mobile ── */
@media (max-width: 540px) {
    .filter-bar { border-radius: 16px; padding: 10px; }
    .notif-card { padding: 14px 14px; gap: 12px; }
    .notif-icon { width: 40px; height: 40px; font-size: 15px; }
}
</style>
</head>
<body>

<div class="page-wrap">

    <!-- Header -->
    <div class="notif-header">
        <h1>
            <i class="fa fa-bell" style="color:var(--accent);"></i>
            Notifications
            <?php if ($unread_count > 0): ?>
                <span class="new-badge"><?= $unread_count ?> new</span>
            <?php endif; ?>
        </h1>
        <?php if (!empty($notifications)): ?>
            <span class="total-count"><?= count($notifications) ?> total</span>
        <?php endif; ?>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
        <button class="filter-btn active" data-filter="all">All</button>
        <button class="filter-btn" data-filter="Pending">Pending</button>
        <button class="filter-btn" data-filter="Processing">Processing</button>
        <button class="filter-btn" data-filter="Shipped">Shipped</button>
        <button class="filter-btn" data-filter="Delivered">Delivered</button>
        <button class="filter-btn" data-filter="Cancelled">Cancelled</button>
    </div>

    <!-- Notification container -->
    <div id="notifContainer">
        <div style="text-align:center;padding:40px;">
            <div class="spinner"></div> Loading...
        </div>
    </div>

    <p class="footer-note">
        <i class="fa fa-shield-alt"></i>
        Pending, processing, shipping, delivery — sabai updates yahaan aaucha.
    </p>
</div>

<script>
const allNotifications = <?php echo json_encode($jsonNotifs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function formatDateAgo(dateStr) {
    const date = new Date(dateStr);
    const diff  = Math.floor((Date.now() - date) / 1000);
    if (diff < 60)     return 'Just now';
    if (diff < 3600)   return Math.floor(diff / 60) + ' min ago';
    if (diff < 86400)  return Math.floor(diff / 3600) + ' hr ago';
    if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
    return date.toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' });
}

function formatExactTime(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleString('en-GB', {
        day:'2-digit', month:'short', year:'numeric',
        hour:'2-digit', minute:'2-digit'
    });
}

function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function renderNotifications(notifs) {
    const container = document.getElementById('notifContainer');

    if (!notifs.length) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fa fa-bell empty-icon"></i>
                <h2>No notifications</h2>
                <p>No updates for the selected filter.</p>
            </div>`;
        return;
    }

    // Group by date
    const groups  = {};
    const keyOrder = [];
    notifs.forEach(n => {
        const dateKey = new Date(n.created_at).toLocaleDateString('en-GB');
        if (!groups[dateKey]) { groups[dateKey] = []; keyOrder.push(dateKey); }
        groups[dateKey].push(n);
    });

    const today     = new Date().toLocaleDateString('en-GB');
    const yesterday = new Date(Date.now() - 86400000).toLocaleDateString('en-GB');

    let html = '<div class="notif-list">';

    keyOrder.forEach(dateKey => {
        let displayDate = dateKey;
        if (dateKey === today)     displayDate = 'Today';
        if (dateKey === yesterday) displayDate = 'Yesterday';
        html += `<div class="date-divider">${displayDate}</div>`;

        groups[dateKey].forEach((n, idx) => {
            const meta        = n.meta;
            const timeAgoText = formatDateAgo(n.created_at);
            const exactTime   = formatExactTime(n.created_at);
            const statusClass = (n.status || 'pending').toLowerCase();
            const orderLink   = n.order_id ? `order_details.php?order_id=${n.order_id}` : '#';
            const delay       = (idx * 0.05).toFixed(2);

            html += `
<a href="${orderLink}" class="notif-card ${n.is_unread ? 'unread' : ''}" style="animation-delay:${delay}s;">
    ${n.is_unread ? '<div class="unread-dot"></div>' : ''}
    <div class="notif-icon" style="background:${meta.bg};color:${meta.clr};">
        <i class="fa ${meta.icon}"></i>
    </div>
    <div class="notif-body">
        <div class="badge-row">
            ${n.shop    ? `<div class="shop-badge"><i class="fa fa-store"></i>${esc(n.shop)}</div>`       : ''}
            ${n.product ? `<div class="product-badge"><i class="fa fa-tag"></i>${esc(n.product)}</div>` : ''}
        </div>
        <div class="notif-type-label" style="background:${meta.lbg};color:${meta.lclr};">
            <i class="fa ${meta.icon}"></i> ${esc(meta.label)}
        </div>
        <p class="notif-msg">${esc(n.message)}</p>
        ${n.sms_text ? `
        <div class="sms-strip">
            <div class="sms-icon"><i class="fa fa-comment-sms"></i></div>
            <div style="flex:1;min-width:0;">
                <div class="sms-label-row">
                    <span class="sms-label">SMS Sent</span>
                    ${n.sms_sent_at ? `<span class="sms-time"><i class="fa fa-clock"></i> ${esc(n.sms_sent_at)}</span>` : ''}
                </div>
                <div class="sms-text">${esc(n.sms_text)}</div>
            </div>
        </div>` : ''}
        <div class="notif-footer">
            <span class="notif-time">
                <i class="fa fa-clock"></i> ${timeAgoText}
                <span class="exact-time"> · ${exactTime}</span>
            </span>
            <div class="notif-right">
                ${n.payment_method ? `
                <span class="pay-pill" style="color:${n.pay_info.color};border-color:${n.pay_info.color}33;">
                    <i class="fa ${n.pay_info.icon}"></i> ${esc(n.pay_info.text)}
                </span>` : ''}
                <span class="sp sp-${statusClass}">${esc(n.status)}</span>
                ${n.total ? `<span class="order-amt">Rs.&nbsp;${Number(n.total).toLocaleString()}</span>` : ''}
            </div>
        </div>
    </div>
    <div class="arrow-hint"><i class="fa fa-arrow-right"></i> View</div>
</a>`;
        });
    });

    html += '</div>';
    container.innerHTML = html;
}

function applyFilter(filterValue) {
    const container = document.getElementById('notifContainer');
    container.innerHTML = `<div style="text-align:center;padding:40px;"><div class="spinner"></div> Filtering...</div>`;
    setTimeout(() => {
        const filtered = filterValue === 'all'
            ? allNotifications
            : allNotifications.filter(n => n.status === filterValue);
        renderNotifications(filtered);
    }, 180);
}

// Filter buttons
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        applyFilter(this.getAttribute('data-filter'));
    });
});

// Initial render
window.addEventListener('DOMContentLoaded', () => applyFilter('all'));
</script>

</body>
</html>
<?php ob_end_flush(); ?>