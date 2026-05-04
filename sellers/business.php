<?php
session_start();
require_once __DIR__ . '/../databases/db.php';

if (!isset($_SESSION['seller_id'])) {
    header('Location: register.php');
    exit;
}

$seller_id = (int)$_SESSION['seller_id'];
$db = getDB();

// Fetch existing business details including PAN (from registration)
$stmt = $db->prepare("SELECT business_type, pan_number, tax_info FROM sellers WHERE id = ?");
$stmt->bind_param('i', $seller_id);
$stmt->execute();
$seller = $stmt->get_result()->fetch_assoc();
$stmt->close();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $business_type = $_POST['business_type'] ?? '';
    $tax_info      = trim($_POST['tax_info'] ?? '');
    // PAN number is NOT taken from POST – it remains unchanged from DB

    if (empty($business_type)) {
        $error = 'Please select a business type.';
    } else {
        // Update only business_type and tax_info; pan_number stays as is
        $stmt = $db->prepare("UPDATE sellers SET business_type = ?, tax_info = ? WHERE id = ?");
        $stmt->bind_param('ssi', $business_type, $tax_info, $seller_id);
        if ($stmt->execute()) {
            header('Location: contact.php');
            exit;
        } else {
            $error = 'Database error: ' . $db->error;
        }
        $stmt->close();
    }
}

require_once __DIR__ . '/sidenav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 4: Business Information | SportGhar Seller</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; font-family: 'Plus Jakarta Sans', sans-serif; overflow: hidden; }
        :root { --sidenav-width: 240px; }
        .page-wrapper {
            position: fixed;
            top: 0;
            left: var(--sidenav-width);
            right: 0;
            bottom: 0;
            display: flex;
        }
        .panel-left {
            flex: 1;
            position: relative;
            overflow: hidden;
        }
        .full-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .image-overlay {
            position: absolute;
            bottom: 30px;
            left: 0;
            right: 0;
            text-align: center;
            z-index: 2;
            pointer-events: none;
        }
        .caption {
            font-family: 'Fraunces', serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: #FFEAC5;
            text-shadow: 0 2px 12px rgba(0,0,0,0.4);
            background: rgba(0,0,0,0.4);
            display: inline-block;
            padding: 8px 24px;
            border-radius: 60px;
            backdrop-filter: blur(6px);
        }
        .sub-caption {
            font-size: 13px;
            color: #FFEAC5;
            margin-top: 12px;
            letter-spacing: 0.5px;
            font-weight: 500;
            background: rgba(0,0,0,0.3);
            display: inline-block;
            padding: 5px 16px;
            border-radius: 40px;
            backdrop-filter: blur(3px);
        }
        .panel-right {
            width: 450px;
            flex-shrink: 0;
            background: #fff;
            height: 100%;
            padding: 32px 28px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow-y: auto;
            box-shadow: -4px 0 24px rgba(0,0,0,0.15);
        }
        .step-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(192,57,43,0.09);
            color: #C0392B;
            font-size: 10.5px;
            font-weight: 600;
            padding: 4px 11px;
            border-radius: 100px;
            margin-bottom: 10px;
            letter-spacing: .4px;
        }
        .step-dots {
            display: flex;
            gap: 4px;
            margin-bottom: 16px;
        }
        .step-dots span {
            display: block;
            width: 22px;
            height: 3px;
            border-radius: 2px;
            background: #E8E4DC;
        }
        .step-dots span.on { background: #C0392B; }
        h1 {
            font-family: 'Fraunces', serif;
            font-size: 1.3rem;
            color: #1C1612;
            margin-bottom: 6px;
            line-height: 1.2;
        }
        .subtitle {
            color: #8A7D72;
            font-size: 12px;
            margin-bottom: 24px;
            line-height: 1.65;
        }
        .error-box {
            font-size: 12px;
            color: #C0392B;
            background: #FFF5F5;
            border-left: 3px solid #E74C3C;
            padding: 9px 12px;
            border-radius: 8px;
            margin-bottom: 14px;
        }
        .form-group { margin-bottom: 20px; }
        label {
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 6px;
            color: #5A4A3F;
        }
        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #E0DBD4;
            border-radius: 10px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 13px;
            background: #FDFAF7;
            color: #1C1612;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        input:focus, select:focus {
            border-color: #C0392B;
            box-shadow: 0 0 0 3px rgba(192,57,43,0.08);
        }
        input[readonly] {
            background-color: #F0EEEA;
            color: #5A4A3F;
            cursor: not-allowed;
            border-color: #D6CFC6;
        }
        .button-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        .btn {
            background: #C0392B;
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 100px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: background .15s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-back {
            background: #F5F3EF;
            color: #8A7D72;
        }
        .btn-back:hover { background: #EBE8E2; color: #1C1612; }
        .btn:hover { background: #a93226; }
        .btn:active { transform: scale(.98); }
        @media (max-width: 900px) {
            .page-wrapper {
                left: 0;
                flex-direction: column;
            }
            .panel-left { min-height: 280px; position: relative; }
            .panel-right { width: 100%; height: auto; flex: 1; justify-content: flex-start; }
            .caption { font-size: 1.2rem; padding: 6px 18px; }
        }
    </style>
</head>
<body>

<div class="page-wrapper">
    <!-- LEFT PANEL -->
    <div class="panel-left">
        <img class="full-image" 
             src="https://images.unsplash.com/photo-1589487391730-58f20eb2c308?w=1200&h=800&fit=crop" 
             alt="Business registration background"
             loading="eager">
        <div class="image-overlay">
            <div class="caption">📊 Grow Your Sports Business</div>
            <div class="sub-caption">Register • Get verified • Start selling</div>
        </div>
    </div>

    <!-- RIGHT PANEL: Business Information Form -->
    <div class="panel-right">
        <div class="step-badge">● STEP 4 OF 6</div>
        <div class="step-dots">
            <span class="on"></span>
            <span class="on"></span>
            <span class="on"></span>
            <span class="on"></span>
            <span></span><span></span>
        </div>

        <h1>Business Information</h1>
        <p class="subtitle">Tell us about your business structure and tax registration.</p>

        <?php if ($error): ?>
            <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="businessForm">
            <div class="form-group">
                <label>Business Type *</label>
                <select name="business_type" required>
                    <option value="">Select Type</option>
                    <option value="Individual" <?= ($seller['business_type'] ?? '') == 'Individual' ? 'selected' : '' ?>>Individual / Sole Proprietor</option>
                    <option value="Partnership" <?= ($seller['business_type'] ?? '') == 'Partnership' ? 'selected' : '' ?>>Partnership</option>
                    <option value="Private Limited" <?= ($seller['business_type'] ?? '') == 'Private Limited' ? 'selected' : '' ?>>Private Limited (Pvt. Ltd.)</option>
                    <option value="Public Limited" <?= ($seller['business_type'] ?? '') == 'Public Limited' ? 'selected' : '' ?>>Public Limited</option>
                </select>
            </div>

            <div class="form-group">
                <label>PAN Number (9 digits)</label>
                <input type="text" name="pan_number" id="pan_number"
                       value="<?= htmlspecialchars($seller['pan_number'] ?? '') ?>"
                       readonly
                       placeholder="Auto-filled from registration">
                <small style="display:block; font-size:10px; color:#8A7D72; margin-top:4px;">
                    ⓘ PAN number provided during registration cannot be changed.
                </small>
            </div>

            <div class="form-group">
                <label>VAT / Tax Info (optional)</label>
                <input type="text" name="tax_info" value="<?= htmlspecialchars($seller['tax_info'] ?? '') ?>" placeholder="VAT number or exemption details">
            </div>

            <div class="button-group">
                <a href="kyc_banking.php" class="btn btn-back">← Back</a>
                <button type="submit" class="btn">Continue →</button>
            </div>
        </form>
    </div>
</div>

<script>
    // No validation needed for PAN because it's read-only.
    // Ensure the form does not try to send a changed PAN.
    document.getElementById('businessForm').addEventListener('submit', function(e) {
        // Optional: prevent any tampering (though readonly already safe)
        const panInput = document.getElementById('pan_number');
        if (panInput.hasAttribute('readonly')) {
            // If someone removed readonly via dev tools, force the original value
            panInput.value = "<?= htmlspecialchars($seller['pan_number'] ?? '') ?>";
        }
    });
</script>
</body>
</html>