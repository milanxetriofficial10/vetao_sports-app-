<?php
session_start();
require_once "../databases/db.php";
$conn = getDB();

// ============================================================
// SMS FUNCTION (REPLACE WITH YOUR ACTUAL SMS API)
// ============================================================
function sendSms($phone, $message) {
    // Same as before – logs to sms_log.txt for testing
    $log = date('Y-m-d H:i:s') . " | TO: $phone | MSG: $message\n";
    file_put_contents("sms_log.txt", $log, FILE_APPEND);
    return true;
}

// Check seller login
if (!isset($_SESSION['seller_id'])) {
    header("Location: ../sellers/login.php");
    exit;
}
$seller_id = (int)$_SESSION['seller_id'];

// ============================================================
// HANDLE CUSTOM SMS SEND
// ============================================================
if (isset($_POST['send_custom_sms']) && isset($_POST['item_id']) && isset($_POST['custom_message'])) {
    $item_id = (int)$_POST['item_id'];
    $custom_message = trim($_POST['custom_message']);
    
    if (!empty($custom_message)) {
        // Get customer phone and order details
        $info = $conn->query("
            SELECT oi.order_id, o.phone
            FROM order_items oi
            LEFT JOIN orders o ON oi.order_id = o.id
            WHERE oi.id = $item_id AND oi.seller_id = $seller_id
        ");
        if ($info && $info->num_rows > 0) {
            $row = $info->fetch_assoc();
            $order_id = $row['order_id'];
            $customer_phone = $row['phone'];
            
            if (!empty($customer_phone)) {
                sendSms($customer_phone, $custom_message);
                
                // Log to database
                $msg_esc = $conn->real_escape_string($custom_message);
                $conn->query("
                    INSERT INTO seller_sms_log (seller_id, order_id, item_id, customer_phone, message, sent_at)
                    VALUES ($seller_id, $order_id, $item_id, '$customer_phone', '$msg_esc', NOW())
                ");
                
                // Optional: show success message via session
                $_SESSION['sms_success'] = "SMS sent successfully to $customer_phone";
            } else {
                $_SESSION['sms_error'] = "No phone number found for this order.";
            }
        }
    } else {
        $_SESSION['sms_error'] = "Message cannot be empty.";
    }
    header("Location: seller_order.php");
    exit;
}

// ============================================================
// UPDATE ORDER STATUS & SEND SMS + NOTIFICATION (ORIGINAL)
// ============================================================
if (isset($_POST['update_status']) && isset($_POST['item_id']) && isset($_POST['new_status'])) {
    $item_id = (int)$_POST['item_id'];
    $new_status = $conn->real_escape_string($_POST['new_status']);
    $allowed = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
    if (in_array($new_status, $allowed)) {
        // Update order_items status
        $conn->query("UPDATE order_items SET seller_status = '$new_status' WHERE id = $item_id AND seller_id = $seller_id");

        // Get order details, customer info, product name, shop name
        $info = $conn->query("
            SELECT 
                oi.order_id, 
                o.user_id, 
                o.phone,
                oi.jersey_name,
                s.shop_name,
                o.total
            FROM order_items oi
            LEFT JOIN orders o ON oi.order_id = o.id
            LEFT JOIN jerseys j ON oi.product_id = j.id
            LEFT JOIN shops s ON j.shop_id = s.id
            WHERE oi.id = $item_id AND oi.seller_id = $seller_id
        ");
        if ($info && $info->num_rows > 0) {
            $row = $info->fetch_assoc();
            $order_id = $row['order_id'];
            $customer_id = $row['user_id'];
            $customer_phone = $row['phone'];
            $product_name = $row['jersey_name'] ?? 'तपाईंको उत्पादन';
            $shop_name = $row['shop_name'] ?? 'हाम्रो पसल';

            // Notification message for database
            $notification_msg = "Your order #" . str_pad($order_id, 5, '0', STR_PAD_LEFT) .
                               " – product '{$product_name}' (" . htmlspecialchars($shop_name) . ") status changed to: {$new_status}.";
            $msg_esc = $conn->real_escape_string($notification_msg);
            if ($customer_id) {
                $conn->query("
                    INSERT INTO notifications (user_id, type, message, related_id, is_read, created_at)
                    VALUES ($customer_id, 'order_status', '$msg_esc', $order_id, 0, NOW())
                ");
            }

            // Automatic SMS based on status
            $sms_msg = "";
            switch ($new_status) {
                case 'Pending':
                    $sms_msg = "SportGhar: तपाईंको '{$product_name}' अर्डर अहिले pending छ। चाँडै प्रशोधन गरिनेछ। धन्यवाद! - {$shop_name}";
                    break;
                case 'Processing':
                    $sms_msg = "SportGhar: तपाईंको '{$product_name}' अर्डर प्रशोधन भइरहेको छ। चाँडै पठाइनेछ। धन्यवाद! - {$shop_name}";
                    break;
                case 'Shipped':
                    $sms_msg = "SportGhar: तपाईंको '{$product_name}' अर्डर पठाइसकियो! ट्र्याकिङ लिङ्क पछि प्राप्त हुनेछ। - {$shop_name}";
                    break;
                case 'Delivered':
                    $sms_msg = "SportGhar: तपाईंको '{$product_name}' अर्डर डेलिभर भयो। कृपया मन पराउनुभयो भने प्रतिक्रिया दिनुहोला! - {$shop_name}";
                    break;
                case 'Cancelled':
                    $sms_msg = "SportGhar: तपाईंको '{$product_name}' अर्डर रद्द गरियो। कुनै समस्या भए हामीलाई सम्पर्क गर्नुहोस्। - {$shop_name}";
                    break;
                default:
                    $sms_msg = "SportGhar: तपाईंको अर्डरको स्थिति {$new_status} भएको छ। कृपया हाम्रो एप हेर्नुहोस्। - {$shop_name}";
            }

            if (!empty($customer_phone) && !empty($sms_msg)) {
                sendSms($customer_phone, $sms_msg);
                // Also log automatic SMS to custom log table (optional)
                $conn->query("
                    INSERT INTO seller_sms_log (seller_id, order_id, item_id, customer_phone, message, sent_at)
                    VALUES ($seller_id, $order_id, $item_id, '$customer_phone', '".$conn->real_escape_string($sms_msg)."', NOW())
                ");
            }
        }
    }
    header("Location: seller_order.php");
    exit;
}

// ============================================================
// FETCH ALL ORDERS FOR THIS SELLER (WITH SEARCH)
// ============================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$orders_data = [];
$order_ids = [];

// First get distinct order_ids for this seller
$res = $conn->query("
    SELECT DISTINCT oi.order_id 
    FROM order_items oi
    WHERE oi.seller_id = $seller_id
    ORDER BY oi.order_id DESC
");
while ($row = $res->fetch_assoc()) {
    $order_ids[] = $row['order_id'];
}

if (!empty($order_ids)) {
    $ids_string = implode(',', $order_ids);
    $orders_query = "
        SELECT * FROM orders 
        WHERE id IN ($ids_string)
    ";
    if (!empty($search)) {
        // Search by order id, name, phone, or email
        $search_esc = $conn->real_escape_string($search);
        $orders_query .= " AND (
            id LIKE '%$search_esc%'
            OR name LIKE '%$search_esc%'
            OR phone LIKE '%$search_esc%'
            OR email LIKE '%$search_esc%'
        )";
    }
    $orders_query .= " ORDER BY created_at DESC";
    $orders_res = $conn->query($orders_query);
    
    while ($order = $orders_res->fetch_assoc()) {
        // Also filter items by product name if search term exists
        $items_res = $conn->query("
            SELECT oi.*, j.title, j.image 
            FROM order_items oi
            LEFT JOIN jerseys j ON oi.product_id = j.id
            WHERE oi.order_id = {$order['id']} AND oi.seller_id = $seller_id
        ");
        $items = [];
        while ($item = $items_res->fetch_assoc()) {
            // Apply search filter on product name if needed
            if (!empty($search)) {
                if (stripos($item['jersey_name'] ?? $item['title'], $search) !== false) {
                    $items[] = $item;
                }
            } else {
                $items[] = $item;
            }
        }
        if (!empty($items)) {
            $order['items'] = $items;
            $orders_data[] = $order;
        } elseif (empty($search)) {
            // no search and no items? shouldn't happen, but just in case
            $order['items'] = [];
            $orders_data[] = $order;
        }
    }
}

// Display session messages
if (isset($_SESSION['sms_success'])) {
    echo '<script>alert("'.$_SESSION['sms_success'].'");</script>';
    unset($_SESSION['sms_success']);
}
if (isset($_SESSION['sms_error'])) {
    echo '<script>alert("Error: '.$_SESSION['sms_error'].'");</script>';
    unset($_SESSION['sms_error']);
}

require_once "sidenav.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | Seller Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --brand: #C94B01;
            --brand-dk: #a33a00;
            --brand-lt: #fff2ec;
            --bg: #f0f2f5;
            --surface: #ffffff;
            --border: #e2e6ea;
            --text: #1a1a2e;
            --muted: #6c757d;
            --green: #1a8a4a;
            --green-bg: #edfaf3;
            --amber: #b86a00;
            --amber-bg: #fff7e6;
            --sidebar-w: 245px;
            --top-h: 64px;
        }
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        .main {
            margin-left: var(--sidebar-w);
            margin-top: var(--top-h);
            padding: 28px 26px;
            min-height: calc(100vh - var(--top-h));
        }
        .search-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 60px;
            padding: 8px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
            max-width: 500px;
        }
        .search-bar i {
            color: var(--muted);
        }
        .search-bar input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 14px;
            background: transparent;
        }
        .search-bar button {
            background: var(--brand);
            border: none;
            color: white;
            padding: 6px 16px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
        }
        .stats-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 18px 22px;
            flex: 1;
            min-width: 160px;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--brand);
        }
        .stat-card .stat-icon {
            font-size: 28px;
            color: var(--brand);
            opacity: 0.3;
            position: absolute;
            bottom: 12px;
            right: 16px;
        }
        .stat-card .stat-number {
            font-family: 'Sora', sans-serif;
            font-size: 32px;
            font-weight: 800;
            color: var(--text);
        }
        .stat-card .stat-label {
            font-size: 13px;
            color: var(--muted);
            margin-top: 4px;
        }
        .order-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            margin-bottom: 24px;
            overflow: hidden;
            transition: box-shadow 0.2s;
        }
        .order-card:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }
        .order-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            background: #fafcfd;
            border-bottom: 1px solid var(--border);
        }
        .order-id {
            font-family: 'Sora', sans-serif;
            font-weight: 800;
            font-size: 16px;
            color: var(--brand);
        }
        .order-date {
            font-size: 12px;
            color: var(--muted);
        }
        .customer-info {
            padding: 16px 24px;
            background: #fff;
            border-bottom: 1px solid var(--border);
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            align-items: center;
        }
        .customer-detail {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }
        .customer-detail i {
            width: 20px;
            color: var(--brand);
        }
        .map-link {
            background: #f0f2f5;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 12px;
            text-decoration: none;
            color: var(--brand);
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .map-link:hover {
            background: var(--brand-lt);
            color: var(--brand-dk);
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table th {
            text-align: left;
            padding: 14px 16px;
            background: #f7f9fc;
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }
        .items-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .product-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .product-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid var(--border);
            cursor: pointer;
            transition: 0.2s;
        }
        .product-img:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .product-name {
            font-weight: 600;
            font-size: 14px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
        }
        .status-Pending { background: #fff3e0; color: #e67e22; }
        .status-Processing { background: #e3f2fd; color: #1e88e5; }
        .status-Shipped { background: #e8f5e9; color: #2e7d32; }
        .status-Delivered { background: #e0f2f1; color: #00695c; }
        .status-Cancelled { background: #ffebee; color: #c62828; }
        .status-update-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .status-select {
            padding: 6px 10px;
            border-radius: 20px;
            border: 1px solid var(--border);
            font-size: 12px;
            background: white;
            cursor: pointer;
        }
        .update-btn {
            background: var(--brand);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .update-btn:hover {
            background: var(--brand-dk);
        }
        .custom-sms-form {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 200px;
        }
        .custom-sms-input {
            padding: 6px 10px;
            border-radius: 20px;
            border: 1px solid var(--border);
            font-size: 12px;
            width: 100%;
            background: white;
        }
        .send-sms-btn {
            background: var(--green);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .send-sms-btn:hover {
            background: #0f6a3a;
        }
        .empty-orders {
            text-align: center;
            padding: 60px 20px;
            background: var(--surface);
            border-radius: 24px;
            border: 1px solid var(--border);
        }
        .empty-orders i {
            font-size: 56px;
            color: var(--muted);
            opacity: 0.5;
            margin-bottom: 16px;
        }
        .modal-img {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .modal-img.show { display: flex; }
        .modal-img-content {
            max-width: 90%;
            max-height: 90%;
            border-radius: 12px;
        }
        .close-modal {
            position: absolute;
            top: 20px;
            right: 40px;
            color: #fff;
            font-size: 40px;
            cursor: pointer;
        }
        @media (max-width: 860px) {
            .main { margin-left: 0; padding: 18px 14px; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .order-header { flex-direction: column; align-items: flex-start; gap: 8px; }
            .customer-info { flex-direction: column; gap: 10px; }
            .items-table, .items-table tbody, .items-table tr, .items-table td { display: block; width: 100%; }
            .items-table thead { display: none; }
            .items-table tr {
                margin-bottom: 16px;
                border: 1px solid var(--border);
                border-radius: 12px;
                padding: 12px;
            }
            .items-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 0;
                border-bottom: none;
                gap: 10px;
            }
            .items-table td:before {
                content: attr(data-label);
                font-weight: 700;
                font-size: 12px;
                color: var(--muted);
                width: 40%;
            }
            .status-update-form, .custom-sms-form { justify-content: flex-end; }
        }
    </style>
</head>
<body>

<main class="main">
    <!-- Search Bar -->
    <form method="GET" class="search-bar">
        <i class="fa fa-search"></i>
        <input type="text" name="search" placeholder="Search by Order ID, Customer Name, Phone, or Product..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Search</button>
        <?php if ($search): ?>
            <a href="seller_order.php" style="color: var(--muted); text-decoration: none;"><i class="fa fa-times"></i> Clear</a>
        <?php endif; ?>
    </form>

    <div class="stats-row">
        <?php
        $total_orders = count($orders_data);
        $total_items = 0;
        $pending = 0;
        foreach ($orders_data as $ord) {
            foreach ($ord['items'] as $it) {
                $total_items++;
                if ($it['seller_status'] == 'Pending') $pending++;
            }
        }
        ?>
        <div class="stat-card">
            <div class="stat-number"><?= $total_orders ?></div>
            <div class="stat-label">Total Orders</div>
            <div class="stat-icon"><i class="fa fa-truck"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $total_items ?></div>
            <div class="stat-label">Items Sold</div>
            <div class="stat-icon"><i class="fa fa-shirt"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $pending ?></div>
            <div class="stat-label">Pending Items</div>
            <div class="stat-icon"><i class="fa fa-clock"></i></div>
        </div>
    </div>

    <?php if (empty($orders_data)): ?>
        <div class="empty-orders">
            <i class="fa fa-box-open"></i>
            <h3>No orders found</h3>
            <p><?= $search ? 'Try a different search term.' : 'When customers buy your jerseys, they will appear here.' ?></p>
        </div>
    <?php else: ?>
        <?php foreach ($orders_data as $order): ?>
            <div class="order-card">
                <div class="order-header">
                    <div>
                        <span class="order-id">#<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></span>
                        <span class="order-date"><i class="fa fa-calendar-alt"></i> <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></span>
                    </div>
                    <div class="payment-method">
                        <span class="status-badge" style="background:#e9ecef;color:#495057;"><?= htmlspecialchars($order['payment_method']) ?></span>
                    </div>
                </div>
                <div class="customer-info">
                    <div class="customer-detail"><i class="fa fa-user"></i> <?= htmlspecialchars($order['name']) ?></div>
                    <div class="customer-detail"><i class="fa fa-phone"></i> <?= htmlspecialchars($order['phone']) ?></div>
                    <div class="customer-detail"><i class="fa fa-envelope"></i> <?= htmlspecialchars($order['email'] ?: '—') ?></div>
                    <div class="customer-detail">
                        <i class="fa fa-location-dot"></i> 
                        <?= htmlspecialchars($order['address']) ?>, <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['district']) ?>, <?= htmlspecialchars($order['province']) ?>
                        <?php $full_address = urlencode($order['address'] . ', ' . $order['city'] . ', ' . $order['district'] . ', ' . $order['province'] . ', Nepal'); ?>
                        <a href="https://www.google.com/maps/search/?api=1&query=<?= $full_address ?>" target="_blank" class="map-link"><i class="fa fa-map-marker-alt"></i> Map</a>
                    </div>
                </div>

                <table class="items-table">
                    <thead>
                        <tr><th>Product</th><th>Custom SMS</th><th>Size</th><th>Qty</th><th>Price</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order['items'] as $item): ?>
                            <tr>
                                <td data-label="Product">
                                    <div class="product-cell">
                                        <?php if (!empty($item['image'])): ?>
                                            <img class="product-img" src="<?= htmlspecialchars($item['image']) ?>" alt="Product" onclick="openImageModal(this.src)">
                                        <?php else: ?>
                                            <div class="product-img" style="background:#f0f2f5; display:flex;align-items:center;justify-content:center;"><i class="fa fa-shirt"></i></div>
                                        <?php endif; ?>
                                        <div class="product-name"><?= htmlspecialchars($item['jersey_name'] ?: $item['title']) ?></div>
                                    </div>
                                  </td>
                                <td data-label="Custom SMS">
                                    <form method="post" class="custom-sms-form">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <input type="text" name="custom_message" class="custom-sms-input" placeholder="Type SMS..." required>
                                        <button type="submit" name="send_custom_sms" class="send-sms-btn"><i class="fa fa-paper-plane"></i> Send SMS</button>
                                    </form>
                                </td>
                                <td data-label="Size"><?= htmlspecialchars($item['size'] ?: '—') ?></td>
                                <td data-label="Qty"><?= $item['qty'] ?></td>
                                <td data-label="Price">Rs. <?= number_format($item['price'], 2) ?></td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?= htmlspecialchars($item['seller_status']) ?>">
                                        <?= htmlspecialchars($item['seller_status']) ?>
                                    </span>
                                </td>
                                <td data-label="Action">
                                    <form method="post" class="status-update-form">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <select name="new_status" class="status-select">
                                            <option value="Pending" <?= $item['seller_status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="Processing" <?= $item['seller_status'] == 'Processing' ? 'selected' : '' ?>>Processing</option>
                                            <option value="Shipped" <?= $item['seller_status'] == 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                            <option value="Delivered" <?= $item['seller_status'] == 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                            <option value="Cancelled" <?= $item['seller_status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" name="update_status" class="update-btn">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<div id="imageModal" class="modal-img" onclick="closeModal()">
    <span class="close-modal">&times;</span>
    <img class="modal-img-content" id="modalImage">
</div>

<script>
    function openImageModal(src) {
        document.getElementById('imageModal').classList.add('show');
        document.getElementById('modalImage').src = src;
    }
    function closeModal() {
        document.getElementById('imageModal').classList.remove('show');
    }
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeModal(); });
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        if (sidebar) sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('show');
    }
    function closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('show');
    }
</script>

</body>
</html>