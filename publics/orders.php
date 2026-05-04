<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
include "../databases/db.php";

if (!isset($_SESSION['user']['id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user']['id'];

// ── CANCEL ORDER (within 30 mins only) ──
if (isset($_POST['cancel_order_id'])) {
    $cancel_id = (int)$_POST['cancel_order_id'];

    $chk = $conn->query("SELECT id, status, created_at FROM orders
                          WHERE id=$cancel_id AND user_id=$user_id")->fetch_assoc();

    if ($chk && $chk['status'] === 'pending') {
        $mins_ago = (time() - strtotime($chk['created_at'])) / 60;
        if ($mins_ago <= 30) {
            $conn->query("UPDATE orders SET status='cancelled' WHERE id=$cancel_id AND user_id=$user_id");

            // Notify admin (optional) — insert into notifications for admin user_id = 1
            $conn->query("INSERT INTO notifications (user_id, message, type, related_id, is_read, created_at)
                          VALUES (1, 'User cancelled Order #" . str_pad($cancel_id,5,'0',STR_PAD_LEFT) . " within 30 mins.', 'order', $cancel_id, 0, NOW())");

            $cancel_msg = "success";
        } else {
            $cancel_msg = "expired";
        }
    } else {
        $cancel_msg = "invalid";
    }
}

// ── FETCH ORDERS ──
$result = $conn->query("SELECT * FROM orders WHERE user_id=$user_id ORDER BY created_at DESC");
$orders = [];
while ($row = $result->fetch_assoc()) $orders[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Orders — JerseyGhar</title>
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
    --red: #e83d3d;
    --red-pale: #ffeaea;
    --green: #0ba360;
    --green-pale: #dcf5ec;
    --amber: #d97706;
    --amber-pale: #fef3c7;
    --blue: #2563eb;
    --blue-pale: #dbeafe;
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
/* PAGE WRAPPER */
.page {
    max-width: 860px;
    margin: 0 auto;
    padding: 48px 20px 80px;
}
/* BREADCRUMB */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--ink-3);
    margin-bottom: 32px;
}
.breadcrumb a { color: var(--ink-3); text-decoration: none; }
.breadcrumb a:hover { color: var(--accent); }
.breadcrumb i { font-size: 10px; }

/* HEADER */
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 32px;
    flex-wrap: wrap;
    gap: 16px;
}
.page-title {
    font-family: 'Clash Display', sans-serif;
    font-size: 38px;
    font-weight: 700;
    letter-spacing: -1px;
    line-height: 1;
}
.page-title span { color: var(--accent); }
.order-count-badge {
    background: var(--accent-pale);
    color: var(--accent);
    font-size: 13px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 100px;
    margin-top: 10px;
    display: inline-block;
}
.profile-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--surface);
    color: var(--ink);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    padding: 10px 20px;
    border-radius: 100px;
    border: 1.5px solid rgba(17,16,23,0.1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    transition: all 0.2s;
}
.profile-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
    transform: translateY(-1px);
}

/* TOAST */
.toast {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    border-radius: var(--r);
    margin-bottom: 24px;
    font-size: 14px;
    font-weight: 600;
    animation: slideDown 0.3s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.toast.success { background: var(--green-pale); color: var(--green); }
.toast.error   { background: var(--red-pale);   color: var(--red);   }

/* ORDER CARD */
.order-card {
    background: var(--surface);
    border-radius: 20px;
    border: 1.5px solid rgba(17,16,23,0.07);
    margin-bottom: 20px;
    box-shadow: var(--shadow);
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.order-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 32px rgba(17,16,23,0.1);
}

/* ORDER HEADER */
.order-header {
    padding: 18px 24px;
    background: #fcfbff;
    border-bottom: 1.5px solid rgba(17,16,23,0.06);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}
.order-id-wrap { display: flex; align-items: center; gap: 12px; }
.order-id {
    font-family: 'Clash Display', sans-serif;
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.5px;
}
.order-date {
    font-size: 13px;
    color: var(--ink-3);
    margin-top: 2px;
}
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 100px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}
.s-pending    { background: var(--amber-pale); color: var(--amber); }
.s-confirmed  { background: var(--green-pale); color: var(--green); }
.s-processing { background: var(--blue-pale);  color: var(--blue);  }
.s-delivered  { background: #e8f5e9; color: #2e7d32; }
.s-cancelled  { background: var(--red-pale);   color: var(--red);   }

/* ORDER BODY */
.order-body {
    padding: 20px 24px;
}
.order-info-row {
    display: flex;
    gap: 32px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.order-info-item label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--ink-3);
    margin-bottom: 4px;
}
.order-info-item .val {
    font-size: 14px;
    font-weight: 600;
    color: var(--ink);
}

/* ORDER ITEMS */
.items-toggle {
    background: none;
    border: 1.5px solid rgba(17,16,23,0.08);
    border-radius: 10px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    color: var(--ink-2);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    margin-bottom: 16px;
    font-family: 'Instrument Sans', sans-serif;
}
.items-toggle:hover { border-color: var(--accent); color: var(--accent); }
.items-list {
    display: none;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 16px;
}
.items-list.open { display: flex; }
.item-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 14px;
    background: var(--bg);
    border-radius: 12px;
    border: 1px solid rgba(17,16,23,0.06);
}
.item-img {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    object-fit: cover;
    background: #e0dff0;
    flex-shrink: 0;
}
.item-img-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    background: var(--accent-pale);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent);
    font-size: 18px;
    flex-shrink: 0;
}
.item-name { font-size: 14px; font-weight: 600; flex: 1; }
.item-meta { font-size: 12px; color: var(--ink-3); margin-top: 2px; }
.item-price { font-size: 14px; font-weight: 700; color: var(--accent); white-space: nowrap; }

/* ORDER FOOTER */
.order-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding-top: 16px;
    border-top: 1.5px dashed rgba(17,16,23,0.08);
    margin-top: 4px;
}
.order-total {
    font-family: 'Clash Display', sans-serif;
    font-size: 22px;
    font-weight: 700;
    color: var(--green);
}
.order-total span { font-size: 13px; font-weight: 500; color: var(--ink-3); font-family: 'Instrument Sans', sans-serif; }

/* CANCEL BUTTON */
.cancel-wrap { display: flex; align-items: center; gap: 10px; }
.btn-cancel {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 20px;
    background: var(--red-pale);
    color: var(--red);
    border: 1.5px solid rgba(232,61,61,0.2);
    border-radius: 100px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    font-family: 'Instrument Sans', sans-serif;
    transition: all 0.2s;
}
.btn-cancel:hover {
    background: var(--red);
    color: #fff;
    border-color: var(--red);
}
.cancel-timer {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: var(--amber);
    font-weight: 600;
}
.cancel-timer.expired { color: var(--ink-3); }
.cancel-timer i { font-size: 11px; }
.btn-cancel:disabled,
.btn-cancel[disabled] {
    opacity: 0.4;
    cursor: not-allowed;
    background: var(--red-pale);
    color: var(--red);
    border-color: rgba(232,61,61,0.15);
}

/* EMPTY */
.empty-state {
    text-align: center;
    padding: 100px 20px;
    color: var(--ink-3);
}
.empty-icon {
    width: 90px;
    height: 90px;
    background: var(--accent-pale);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px;
    font-size: 36px;
    color: var(--accent);
}
.empty-state h3 {
    font-family: 'Clash Display', sans-serif;
    font-size: 28px;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 8px;
}
.empty-state p { font-size: 15px; margin-bottom: 24px; }
.btn-shop {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--accent);
    color: #fff;
    text-decoration: none;
    padding: 12px 28px;
    border-radius: 100px;
    font-size: 15px;
    font-weight: 700;
    transition: all 0.2s;
}
.btn-shop:hover { background: #4530c4; transform: translateY(-1px); }
</style>
</head>
<body>

<?php include "../includes/header.php"; ?>

<div class="page">

    <!-- BREADCRUMB -->
    <div class="breadcrumb">
        <a href="../publics/index.php">Home</a>
        <i class="fa fa-chevron-right"></i>
        <a href="profile.php">My Account</a>
        <i class="fa fa-chevron-right"></i>
        <span>My Orders</span>
    </div>

    <!-- HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">My <span>Orders</span></h1>
            <?php if (!empty($orders)): ?>
            <span class="order-count-badge">
                <i class="fa fa-box"></i> <?php echo count($orders); ?> order<?php echo count($orders) > 1 ? 's' : ''; ?>
            </span>
            <?php endif; ?>
        </div>
        <a href="profile.php" class="profile-btn">
            <i class="fa fa-user-circle"></i> My Profile
        </a>
    </div>

    <!-- TOAST -->
    <?php if (isset($cancel_msg)): ?>
        <?php if ($cancel_msg === 'success'): ?>
        <div class="toast success">
            <i class="fa fa-check-circle"></i>
            Order successfully cancel gariyo! Afno order list update bhayo.
        </div>
        <?php elseif ($cancel_msg === 'expired'): ?>
        <div class="toast error">
            <i class="fa fa-clock"></i>
            30 minutes bhaisikyako chha — order cancel garna milena.
        </div>
        <?php else: ?>
        <div class="toast error">
            <i class="fa fa-times-circle"></i>
            Order cancel garna sakiyena. Paila nai pending status hunu parcha.
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ORDERS -->
    <?php if (empty($orders)): ?>
    <div class="empty-state">
        <div class="empty-icon"><i class="fa fa-shopping-bag"></i></div>
        <h3>Kुनai Order Chaina</h3>
        <p>Tapaiले aile samma kुनai order garnu bhayeko chaina.</p>
        <a href="../publics/index.php" class="btn-shop">
            <i class="fa fa-store"></i> Shopping Suru Garnus
        </a>
    </div>

    <?php else: ?>
    <?php foreach ($orders as $o):
        $oid     = str_pad($o['id'], 5, '0', STR_PAD_LEFT);
        $sc      = 's-' . $o['status'];
        $created = strtotime($o['created_at']);
        $mins_since = (time() - $created) / 60;
        $can_cancel  = ($o['status'] === 'pending' && $mins_since <= 30);
        $mins_left   = max(0, 30 - (int)$mins_since);

        // Fetch order items
        $items_r = $conn->query("SELECT oi.*, p.name as pname, p.image FROM order_items oi
                                  LEFT JOIN products p ON oi.product_id = p.id
                                  WHERE oi.order_id = " . (int)$o['id']);
        $items = [];
        if ($items_r) while ($ir = $items_r->fetch_assoc()) $items[] = $ir;
    ?>
    <div class="order-card">
        <!-- HEADER -->
        <div class="order-header">
            <div>
                <div class="order-id-wrap">
                    <div class="order-id">Order #<?php echo $oid; ?></div>
                </div>
                <div class="order-date">
                    <i class="fa fa-calendar" style="font-size:11px;"></i>
                    <?php echo date("d M, Y • h:i A", $created); ?>
                </div>
            </div>
            <span class="status-pill <?php echo $sc; ?>">
                <?php
                $icons = ['pending'=>'fa-clock','confirmed'=>'fa-check','processing'=>'fa-cog',
                          'delivered'=>'fa-truck','cancelled'=>'fa-times'];
                $ico = $icons[$o['status']] ?? 'fa-circle';
                ?>
                <i class="fa <?php echo $ico; ?>"></i>
                <?php echo ucfirst($o['status']); ?>
            </span>
        </div>

        <!-- BODY -->
        <div class="order-body">
            <div class="order-info-row">
                <div class="order-info-item">
                    <label>Delivered To</label>
                    <div class="val"><?php echo htmlspecialchars($o['name']); ?></div>
                </div>
                <div class="order-info-item">
                    <label>Address</label>
                    <div class="val"><?php echo htmlspecialchars($o['address']); ?></div>
                </div>
                <div class="order-info-item">
                    <label>Payment</label>
                    <div class="val"><?php echo ucfirst(htmlspecialchars($o['payment_method'])); ?></div>
                </div>
                <?php if (!empty($o['phone'])): ?>
                <div class="order-info-item">
                    <label>Phone</label>
                    <div class="val"><?php echo htmlspecialchars($o['phone']); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ITEMS TOGGLE -->
            <?php if (!empty($items)): ?>
            <button class="items-toggle" onclick="toggleItems(this)">
                <i class="fa fa-box-open"></i>
                <?php echo count($items); ?> item<?php echo count($items) > 1 ? 's' : ''; ?> — Details dekhaunus
                <i class="fa fa-chevron-down" style="font-size:11px;"></i>
            </button>
            <div class="items-list">
                <?php foreach ($items as $item): ?>
                <div class="item-row">
                    <?php if (!empty($item['image'])): ?>
                    <img src="../uploads/<?php echo htmlspecialchars($item['image']); ?>"
                         alt="<?php echo htmlspecialchars($item['pname']); ?>" class="item-img">
                    <?php else: ?>
                    <div class="item-img-placeholder"><i class="fa fa-tshirt"></i></div>
                    <?php endif; ?>
                    <div style="flex:1;">
                        <div class="item-name"><?php echo htmlspecialchars($item['pname'] ?? 'Product'); ?></div>
                        <div class="item-meta">
                            Qty: <?php echo (int)$item['quantity']; ?>
                            <?php if (!empty($item['size'])): ?>
                             • Size: <?php echo htmlspecialchars($item['size']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="item-price">Rs. <?php echo number_format($item['price'] * $item['quantity']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- FOOTER -->
            <div class="order-footer">
                <div class="order-total">
                    Rs. <?php echo number_format($o['total']); ?>
                    <span>Total</span>
                </div>

                <!-- CANCEL SECTION -->
                <?php if ($o['status'] === 'pending'): ?>
                <div class="cancel-wrap">
                    <?php if ($can_cancel): ?>
                    <div class="cancel-timer" id="timer-<?php echo $o['id']; ?>" data-left="<?php echo $mins_left; ?>" data-created="<?php echo $o['created_at']; ?>">
                        <i class="fa fa-clock"></i>
                        Cancel window: <strong id="t-<?php echo $o['id']; ?>"></strong>
                    </div>
                    <form method="post" onsubmit="return confirm('Order #<?php echo $oid; ?> cancel garne? Yो action undo garna sakdaina!')">
                        <input type="hidden" name="cancel_order_id" value="<?php echo $o['id']; ?>">
                        <button type="submit" class="btn-cancel" id="cbtn-<?php echo $o['id']; ?>">
                            <i class="fa fa-times-circle"></i> Cancel Order
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="cancel-timer expired">
                        <i class="fa fa-lock"></i> Cancel window expired (30 min bhaisikyako chha)
                    </div>
                    <?php endif; ?>
                </div>
                <?php elseif ($o['status'] === 'cancelled'): ?>
                <div class="cancel-timer expired">
                    <i class="fa fa-ban"></i> Yō order cancel bhaisakyo
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

</div>

<script>
// Toggle items list
function toggleItems(btn) {
    const list = btn.nextElementSibling;
    list.classList.toggle('open');
    const chevron = btn.querySelector('.fa-chevron-down, .fa-chevron-up');
    if (list.classList.contains('open')) {
        chevron.className = 'fa fa-chevron-up';
        btn.innerHTML = btn.innerHTML.replace('dekhaunus', 'lukaunus');
    } else {
        chevron.className = 'fa fa-chevron-down';
        btn.innerHTML = btn.innerHTML.replace('lukaunus', 'dekhaunus');
    }
}

// Live countdown timers
document.querySelectorAll('.cancel-timer[data-created]').forEach(function(el) {
    const id = el.id.replace('timer-', '');
    const created = new Date(el.dataset.created.replace(' ', 'T') + 'Z');
    const timerEl = document.getElementById('t-' + id);
    const btnEl   = document.getElementById('cbtn-' + id);

    function update() {
        const now    = new Date();
        const diff   = 30 * 60 * 1000 - (now - created); // ms remaining
        if (diff <= 0) {
            el.innerHTML = '<i class="fa fa-lock"></i> Cancel window expired';
            el.classList.add('expired');
            if (btnEl) { btnEl.disabled = true; }
            return;
        }
        const m = Math.floor(diff / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        if (timerEl) timerEl.textContent = m + 'm ' + s + 's';
        setTimeout(update, 1000);
    }
    update();
});
</script>

</body>
</html>
<?php ob_end_flush(); ?>