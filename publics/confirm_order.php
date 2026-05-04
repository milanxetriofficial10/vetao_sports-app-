<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../databases/db.php";

// POST from checkout → save to session
if (isset($_POST['go_confirm'])) {
    $_SESSION['checkout_data'] = [
        'name'      => trim($_POST['name'] ?? ''),
        'phone'     => trim($_POST['phone'] ?? ''),
        'email'     => trim($_POST['email'] ?? ''),
        'address'   => trim($_POST['address'] ?? ''),
        'city'      => trim($_POST['city'] ?? ''),
        'district'  => trim($_POST['district'] ?? ''),
        'province'  => trim($_POST['province'] ?? ''),
        'payment'   => $_POST['payment'] ?? '',
    ];
}

// Redirect checks
if (empty($_SESSION['checkout_data'])) {
    header("Location: checkout.php");
    exit;
}
if (empty($_SESSION['cart'])) {
    header("Location: ../publics/index.php");
    exit;
}

$data = $_SESSION['checkout_data'];

// ====================== PLACE ORDER ======================
$error = "";

if (isset($_POST['confirm_order'])) {

    $name      = $conn->real_escape_string($data['name']);
    $phone     = $conn->real_escape_string($data['phone']);
    $email     = $conn->real_escape_string($data['email']);
    $address   = $conn->real_escape_string($data['address']);
    $city      = $conn->real_escape_string($data['city']);
    $district  = $conn->real_escape_string($data['district']);
    $province  = $conn->real_escape_string($data['province']);
    $payment   = $conn->real_escape_string($data['payment']);

    $total = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total += $item['price'] * $item['qty'];
    }

    $user_id = $_SESSION['user']['id'] ?? 0;

    $sql = "INSERT INTO orders (user_id, name, phone, email, address, city, district, province, payment_method, total, status, created_at)
            VALUES ($user_id, '$name', '$phone', '$email', '$address', '$city', '$district', '$province', '$payment', '$total', 'Pending', NOW())";

    if ($conn->query($sql)) {
        $order_id = $conn->insert_id;

        // Insert Order Items
        foreach ($_SESSION['cart'] as $item) {
            $iname  = $conn->real_escape_string($item['name']);
            $iprice = (float)$item['price'];
            $iqty   = (int)$item['qty'];
            $isize  = $conn->real_escape_string($item['size'] ?? '');

            $conn->query("INSERT INTO order_items (order_id, jersey_name, size, price, qty)
                          VALUES ($order_id, '$iname', '$isize', $iprice, $iqty)");
        }

        // ====================== 1. SAVE NOTIFICATION ======================
        $notif_msg = "Your order #$order_id has been placed successfully! We will contact you soon.";
        $conn->query("INSERT INTO notifications (user_id, type, message, related_id, created_at) 
                      VALUES ($user_id, 'order', '$notif_msg', $order_id, NOW())");

        // ====================== 2. SEND EMAIL (Gmail) ======================
        if (!empty($email)) {
            $to = $email;
            $subject = "✅ Order Confirmation - JerseyGhar #$order_id";
            $message = "
            <h2>Thank you for shopping with JerseyGhar!</h2>
            <p>Dear {$data['name']},</p>
            <p>Your order has been successfully placed.</p>
            <p><strong>Order ID:</strong> #$order_id</p>
            <p><strong>Total Amount:</strong> Rs. " . number_format($total) . "</p>
            <p>Payment Method: {$payment}</p>
            <p>We will contact you shortly for delivery confirmation.</p>
            <hr>
            <p>Best regards,<br><strong>JerseyGhar Team</strong></p>
            ";

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: no-reply@jerseyghar.com\r\n";

            mail($to, $subject, $message, $headers);
        }

        // ====================== 3. SMS (Structure - Ready for Nepal API) ======================
        if (!empty($phone)) {
            // Example for Sparrow SMS or similar API (uncomment and configure later)
            /*
            $sms_message = "Your JerseyGhar order #$order_id has been placed. Total: Rs." . number_format($total) . ". Thank you!";
            $api_url = "https://api.sparrowsms.com/v2/sms"; // or your SMS provider
            // Use file_get_contents or cURL to send SMS here
            */
        }

        // Clear session & redirect
        $_SESSION['cart'] = [];
        $_SESSION['checkout_data'] = [];
        $_SESSION['last_order_id'] = $order_id;

        header("Location: order_success.php");
        exit;
    } else {
        $error = "Failed to place order: " . $conn->error;
    }
}

// Calculate totals for display
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += $item['price'] * $item['qty'];
}
$shipping = $subtotal > 2000 ? 0 : 120;
$total    = $subtotal + $shipping;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Order — JerseyGhar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        :root{
          --ink:#12111a; --ink-2:#44425a; --ink-3:#8e8ba8;
          --bg:#f4f3fb; --surface:#ffffff; --surface-2:#f9f8fe;
          --border:rgba(100,90,200,0.13); --accent:#4f3de8;
          --green:#0aaa6e; --green-pale:#d3f5ea;
          --r:12px; --r-lg:20px; --r-xl:28px;
          --font-h:'Syne',sans-serif; --font-b:'DM Sans',sans-serif;
        }
        body{font-family:var(--font-b);background:var(--bg);color:var(--ink);min-height:100vh;}

        .progress-bar{background:var(--ink);padding:14px 32px;display:flex;align-items:center;justify-content:center;gap:0;}
        .pstep{display:flex;flex-direction:column;align-items:center;gap:5px;position:relative;flex:1;max-width:130px;}
        .pstep:not(:last-child)::after{content:'';position:absolute;top:15px;left:calc(50% + 18px);width:calc(100% - 36px);height:1.5px;background:rgba(255,255,255,0.1);z-index:0;}
        .pstep.done:not(:last-child)::after{background:var(--green);}
        .pstep-dot{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;font-family:var(--font-h);z-index:1;}
        .pstep.done .pstep-dot{background:var(--green);color:#fff;}
        .pstep.active .pstep-dot{background:var(--accent);color:#fff;}
        .pstep-label{font-size:10.5px;font-weight:500;font-family:var(--font-h);color:rgba(255,255,255,0.35);text-transform:uppercase;}
        .pstep.done .pstep-label{color:var(--green);}
        .pstep.active .pstep-label{color:#fff;}

        .page{max-width:1060px;margin:32px auto 80px;padding:0 20px;display:grid;grid-template-columns:1fr 370px;gap:24px;}
        @media(max-width:820px){.page{grid-template-columns:1fr;}}

        .card{background:var(--surface);border-radius:var(--r-xl);border:1.5px solid var(--border);box-shadow:0 6px 24px rgba(79,61,232,0.1);overflow:hidden;}
        .card-head{padding:18px 24px 16px;display:flex;align-items:center;gap:12px;border-bottom:1.5px solid var(--border);background:var(--surface-2);}
        .head-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:15px;background:var(--accent-pale);color:var(--accent);}
        .head-title{font-family:var(--font-h);font-size:15px;font-weight:700;color:var(--ink);flex:1;}
        .card-body{padding:22px 24px;}

        .detail-section{margin-bottom:20px;}
        .detail-title{font-family:var(--font-h);font-size:11px;font-weight:700;color:var(--ink-3);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;display:flex;align-items:center;gap:7px;}
        .detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
        .detail-item{background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--r);padding:10px 14px;}
        .detail-item.full{grid-column:1/-1;}
        .detail-label{font-size:10.5px;font-weight:600;font-family:var(--font-h);color:var(--ink-3);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:3px;}
        .detail-value{font-size:14px;font-weight:500;color:var(--ink);}

        .pay-badge{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:30px;font-size:13px;font-weight:700;font-family:var(--font-h);}
        .pay-badge.cod{background:#fef8e1;color:#ca8a04;}
        .pay-badge.esewa{background:var(--green-pale);color:var(--green);}
        .pay-badge.khalti{background:#f0e9ff;color:#7c3aed;}
        .pay-badge.bank{background:#dbeafe;color:#1d4ed8;}

        .order-items-list{display:flex;flex-direction:column;gap:10px;margin-bottom:18px;}
        .order-item{display:flex;align-items:center;gap:12px;padding:10px 12px;background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--r-lg);}
        .order-item img{width:54px;height:54px;object-fit:cover;border-radius:10px;border:1.5px solid var(--border);flex-shrink:0;}
        .order-item-info{flex:1;min-width:0;}
        .order-item-name{font-size:13px;font-weight:700;font-family:var(--font-h);color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .order-item-meta{font-size:11px;color:var(--ink-3);margin-top:2px;}
        .order-item-price{font-size:14px;font-weight:700;font-family:var(--font-h);color:var(--green);}

        .divider-dashed{border:none;border-top:1.5px dashed var(--border);margin:14px 0;}
        .total-row{display:flex;justify-content:space-between;align-items:center;font-size:13.5px;color:var(--ink-2);padding:4px 0;}
        .total-row.grand{font-size:20px;font-weight:800;font-family:var(--font-h);color:var(--ink);padding-top:12px;margin-top:4px;border-top:2px solid var(--border);}
        .free-tag{background:var(--green-pale);color:var(--green);font-size:11px;font-weight:700;font-family:var(--font-h);padding:3px 10px;border-radius:20px;}

        .confirm-btn{width:100%;padding:17px 20px;background:var(--green);color:#fff;border:none;border-radius:var(--r-lg);font-size:15px;font-weight:700;font-family:var(--font-h);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:all 0.3s;}
        .confirm-btn:hover{transform:translateY(-4px);}

        .warning-box{background:#fffbeb;border:1.5px solid #fcd34d;border-radius:var(--r-lg);padding:14px 16px;margin-bottom:18px;display:flex;gap:10px;align-items:flex-start;}
        .warning-box i{color:#ca8a04;}
        .warning-box p{font-size:13px;color:#92400e;line-height:1.6;}
    </style>
</head>
<body>

<!-- Progress Bar -->
<div class="progress-bar">
  <div class="pstep done"><div class="pstep-dot"><i class="fa fa-check"></i></div><div class="pstep-label">Cart</div></div>
  <div class="pstep done"><div class="pstep-dot"><i class="fa fa-check"></i></div><div class="pstep-label">Checkout</div></div>
  <div class="pstep active"><div class="pstep-dot">3</div><div class="pstep-label">Confirm</div></div>
</div>

<div class="page">

  <!-- Left Column -->
  <div class="left-col" style="display:flex;flex-direction:column;gap:20px;">
    <div class="card">
      <div class="card-head">
        <div class="head-icon"><i class="fa fa-clipboard-check"></i></div>
        <div class="head-title">Review Your Order</div>
      </div>
      <div class="card-body">

        <?php if(!empty($error)): ?>
        <div class="warning-box" style="background:#fee2e2;border-color:#ef4444;color:#991b1b;">
          <i class="fa fa-circle-exclamation"></i>
          <p><?php echo $error; ?></p>
        </div>
        <?php endif; ?>

        <div class="warning-box">
          <i class="fa fa-circle-info"></i>
          <p>Please review all details carefully before confirming.</p>
        </div>

        <!-- Contact Information -->
        <div class="detail-section">
          <div class="detail-title"><i class="fa fa-user"></i> Contact Information</div>
          <div class="detail-grid">
            <div class="detail-item"><div class="detail-label">Full Name</div><div class="detail-value"><?php echo htmlspecialchars($data['name']); ?></div></div>
            <div class="detail-item"><div class="detail-label">Phone</div><div class="detail-value"><?php echo htmlspecialchars($data['phone']); ?></div></div>
            <?php if(!empty($data['email'])): ?>
            <div class="detail-item full"><div class="detail-label">Email</div><div class="detail-value"><?php echo htmlspecialchars($data['email']); ?></div></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Delivery Address -->
        <div class="detail-section">
          <div class="detail-title"><i class="fa fa-location-dot"></i> Delivery Address</div>
          <div class="detail-grid">
            <div class="detail-item"><div class="detail-label">Province</div><div class="detail-value"><?php echo htmlspecialchars($data['province']); ?></div></div>
            <div class="detail-item"><div class="detail-label">District</div><div class="detail-value"><?php echo htmlspecialchars($data['district']); ?></div></div>
            <div class="detail-item"><div class="detail-label">City</div><div class="detail-value"><?php echo htmlspecialchars($data['city']); ?></div></div>
            <div class="detail-item">
              <div class="detail-label">Payment Method</div>
              <div class="detail-value">
                <?php
                $pm = $data['payment'];
                $cls = match($pm){ 'eSewa'=>'esewa', 'Khalti'=>'khalti', 'Bank Transfer'=>'bank', default=>'cod' };
                $icon = match($pm){ 'eSewa'=>'fa-leaf', 'Khalti'=>'fa-bolt', 'Bank Transfer'=>'fa-building-columns', default=>'fa-money-bill-wave' };
                ?>
                <span class="pay-badge <?php echo $cls; ?>"><i class="fa <?php echo $icon; ?>"></i> <?php echo htmlspecialchars($pm); ?></span>
              </div>
            </div>
            <div class="detail-item full"><div class="detail-label">Full Address</div><div class="detail-value"><?php echo htmlspecialchars($data['address']); ?></div></div>
          </div>
        </div>

        <!-- Digital Payment Buttons -->
        <?php if($data['payment'] === 'eSewa'): ?>
        <div class="digital-pay-box">
          <div class="dp-title">Pay with eSewa</div>
          <button class="dp-btn esewa-btn" onclick="initiateEsewa(<?php echo $total; ?>)">
            <i class="fa fa-leaf"></i> Pay Rs. <?php echo number_format($total); ?>
          </button>
        </div>
        <?php elseif($data['payment'] === 'Khalti'): ?>
        <div class="digital-pay-box">
          <div class="dp-title">Pay with Khalti</div>
          <button class="dp-btn khalti-btn" onclick="initiateKhalti(<?php echo $total; ?>)">
            <i class="fa fa-bolt"></i> Pay Rs. <?php echo number_format($total); ?>
          </button>
        </div>
        <?php endif; ?>

      </div>
    </div>

    <div style="text-align:center;">
      <a href="checkout.php" style="color:var(--ink-3);font-size:13px;">← Go back and edit details</a>
    </div>
  </div>

  <!-- Right Column -->
  <div class="right-col" style="display:flex;flex-direction:column;gap:16px;">
    <div class="card">
      <div class="card-head">
        <div class="head-icon"><i class="fa fa-bag-shopping"></i></div>
        <div class="head-title">Order Summary</div>
        <div style="font-size:11px;background:var(--accent-pale);color:var(--accent);padding:3px 10px;border-radius:20px;">
          <?php echo count($_SESSION['cart']); ?> item(s)
        </div>
      </div>
      <div class="card-body">
        <div class="order-items-list">
          <?php foreach($_SESSION['cart'] as $item): ?>
          <div class="order-item">
            <img src="<?php echo htmlspecialchars($item['image'] ?? 'placeholder.jpg'); ?>" alt="">
            <div class="order-item-info">
              <div class="order-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
              <div class="order-item-meta">Size: <?php echo htmlspecialchars($item['size'] ?? 'N/A'); ?> • Qty: <?php echo $item['qty']; ?></div>
            </div>
            <div class="order-item-price">Rs. <?php echo number_format($item['price'] * $item['qty']); ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <hr class="divider-dashed">

        <div class="total-row"><span>Subtotal</span><span>Rs. <?php echo number_format($subtotal); ?></span></div>
        <div class="total-row">
          <span>Shipping</span>
          <?php if($shipping == 0): ?>
            <span class="free-tag"><i class="fa fa-check"></i> FREE</span>
          <?php else: ?>
            <span>Rs. <?php echo number_format($shipping); ?></span>
          <?php endif; ?>
        </div>
        <div class="total-row grand"><span>Total</span><span>Rs. <?php echo number_format($total); ?></span></div>
      </div>
    </div>

    <?php if($data['payment'] === 'Cash on Delivery' || $data['payment'] === 'Bank Transfer'): ?>
    <form method="POST">
      <input type="hidden" name="confirm_order" value="1">
      <button type="submit" class="confirm-btn">
        <i class="fa fa-check-circle"></i> Confirm & Place Order
      </button>
    </form>
    <?php endif; ?>
  </div>
</div>

</body>
</html>

<?php 
include "../includes/footer.php"; 
ob_end_flush(); 
?>