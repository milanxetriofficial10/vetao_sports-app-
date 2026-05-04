<?php
session_start();
require_once __DIR__ . '/../databases/db.php';

if (!isset($_SESSION['seller_id'])) {
    header('Location: register.php');
    exit;
}

$seller_id = (int)$_SESSION['seller_id'];
$db = getDB();

$stmt = $db->prepare("SELECT shop_name, shop_category, shop_address, shop_logo, shop_banner FROM sellers WHERE id = ?");
$stmt->bind_param('i', $seller_id);
$stmt->execute();
$seller = $stmt->get_result()->fetch_assoc();
$stmt->close();

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_name = trim($_POST['shop_name'] ?? '');
    $shop_category = $_POST['shop_category'] ?? '';
    $shop_address = trim($_POST['shop_address'] ?? '');

    if (strlen($shop_name) < 2) {
        $error = 'Shop name is required.';
    } elseif (!$shop_category) {
        $error = 'Please select a category.';
    } elseif (strlen($shop_address) < 5) {
        $error = 'Shop address is required.';
    } else {
        // Handle file uploads
        $logo_path = null;
        $banner_path = null;

        $upload_logo_dir = __DIR__ . '/../publics/uploads/shop_logo/';
        $upload_banner_dir = __DIR__ . '/../publics/uploads/shop_banner/';
        if (!is_dir($upload_logo_dir)) mkdir($upload_logo_dir, 0755, true);
        if (!is_dir($upload_banner_dir)) mkdir($upload_banner_dir, 0755, true);

        if (!empty($_FILES['shop_logo']) && $_FILES['shop_logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['shop_logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $filename = time() . '_logo_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($_FILES['shop_logo']['tmp_name'], $upload_logo_dir . $filename)) {
                    $logo_path = '../publics/uploads/shop_logo/' . $filename;
                } else { $error = 'Failed to upload logo.'; }
            } else { $error = 'Invalid logo file type.'; }
        }

        if (empty($error) && !empty($_FILES['shop_banner']) && $_FILES['shop_banner']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['shop_banner']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $filename = time() . '_banner_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($_FILES['shop_banner']['tmp_name'], $upload_banner_dir . $filename)) {
                    $banner_path = '../publics/uploads/shop_banner/' . $filename;
                } else { $error = 'Failed to upload banner.'; }
            } else { $error = 'Invalid banner file type.'; }
        }

        if (empty($error)) {
            $sql = "UPDATE sellers SET shop_name = ?, shop_category = ?, shop_address = ?";
            $params = [$shop_name, $shop_category, $shop_address];
            $types = "sss";
            if ($logo_path) {
                $sql .= ", shop_logo = ?";
                $params[] = $logo_path;
                $types .= "s";
            }
            if ($banner_path) {
                $sql .= ", shop_banner = ?";
                $params[] = $banner_path;
                $types .= "s";
            }
            $sql .= " WHERE id = ?";
            $params[] = $seller_id;
            $types .= "i";

            $stmt = $db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                header('Location: kyc.php');
                exit;
            } else {
                $error = 'Database error: ' . $db->error;
            }
            $stmt->close();
        }
    }
}
require_once __DIR__ . '/sidenav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 2: Shop Details | SportGhar Seller</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            height: 100%;
            font-family: 'Plus Jakarta Sans', sans-serif;
            overflow: hidden;
        }

        /* Sidenav width — adjust to match your actual sidenav */
        :root { --sidenav-width: 240px; }

        /* Wrapper that sits to the RIGHT of the sidenav */
        .page-wrapper {
            position: fixed;
            top: 40;
            left: var(--sidenav-width);
            right: 0;
            bottom: 0;
            display: flex;
        }

        /* LEFT PANEL: Full-bleed image (no border, no radius) */
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

        /* Overlay text on the image */
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

        /* RIGHT PANEL: Shop details form */
        .panel-right {
            width: 450px;  /* Slightly wider to accommodate two columns */
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

        .form-group { margin-bottom: 18px; }

        label {
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 6px;
            color: #5A4A3F;
        }

        input, textarea, select {
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

        input:focus, textarea:focus, select:focus {
            border-color: #C0392B;
            box-shadow: 0 0 0 3px rgba(192,57,43,0.08);
        }

        textarea { resize: vertical; min-height: 70px; }

        /* Two-column layout for logo + banner */
        .upload-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .upload-col {
            flex: 1;
            min-width: 180px;
        }
        .file-zone {
            border: 2px dashed #E0DBD4;
            border-radius: 14px;
            padding: 14px 12px;
            text-align: center;
            cursor: pointer;
            background: #FDFAF7;
            transition: all 0.2s;
            font-size: 12px;
            color: #8A7D72;
        }
        .file-zone:hover {
            border-color: #C0392B;
            background: #fdf1ef;
            color: #C0392B;
        }
        .preview-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 10px;
            align-items: center;
        }
        .preview-thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #E0DBD4;
            background: #FDFAF7;
        }
        .file-hint {
            font-size: 10px;
            color: #aaa;
            margin-top: 6px;
        }
        .current-file {
            font-size: 11px;
            color: #2A7A4B;
            margin-top: 6px;
            background: #EBF9F0;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
        }

        /* Button group with space-between: Back on left, Continue on right */
        .button-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 28px;
            gap: 16px;
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

        /* Responsive */
        @media (max-width: 900px) {
            .page-wrapper {
                left: 0;
                flex-direction: column;
            }
            .panel-left { min-height: 280px; position: relative; }
            .panel-right { width: 100%; height: auto; flex: 1; }
            .caption { font-size: 1.2rem; padding: 6px 18px; }
            .upload-row { flex-direction: column; gap: 12px; }
            .button-group {
                flex-direction: column-reverse;
                gap: 12px;
            }
            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="page-wrapper">

    <!-- LEFT PANEL: Full width/height image -->
    <div class="panel-left">
        <img class="full-image" 
             src="https://images.unsplash.com/photo-1589487391730-58f20eb2c308?w=1200&h=800&fit=crop" 
             alt="FC Barcelona Jersey - Blaugrana stripes full display"
             loading="eager">
        <div class="image-overlay">
            <div class="caption">🔵🔴 Gear Up Your Shop</div>
            <div class="sub-caption">Jerseys • Boots • Equipment • Fan Gear</div>
        </div>
    </div>

    <!-- RIGHT PANEL: Shop Details Form -->
    <div class="panel-right">
        <div class="step-badge">● STEP 2 OF 6</div>
        <div class="step-dots">
            <span class="on"></span>
            <span class="on"></span>
            <span></span><span></span><span></span><span></span>
        </div>

        <h1>Shop Details</h1>
        <p class="subtitle">Help buyers discover and recognize your shop.</p>

        <?php if ($error): ?>
            <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="shopForm">
            <div class="form-group">
                <label>Shop Name *</label>
                <input type="text" name="shop_name" value="<?= htmlspecialchars($seller['shop_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Shop Category *</label>
                <select name="shop_category" required>
                    <option value="">Select Category</option>
                    <option value="Jerseys" <?= ($seller['shop_category'] ?? '') == 'Jerseys' ? 'selected' : '' ?>>Jerseys</option>
                    <option value="Shoes" <?= ($seller['shop_category'] ?? '') == 'Shoes' ? 'selected' : '' ?>>Shoes</option>
                    <option value="Electronics" <?= ($seller['shop_category'] ?? '') == 'Electronics' ? 'selected' : '' ?>>Electronics</option>
                    <option value="Sports Equipment" <?= ($seller['shop_category'] ?? '') == 'Sports Equipment' ? 'selected' : '' ?>>Sports Equipment</option>
                    <option value="Other" <?= ($seller['shop_category'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label>Shop Address *</label>
                <textarea name="shop_address" rows="2" required><?= htmlspecialchars($seller['shop_address'] ?? '') ?></textarea>
            </div>

            <!-- Logo and Banner in one line (side by side) -->
            <div class="upload-row">
                <div class="upload-col">
                    <label>Shop Logo (optional)</label>
                    <div class="file-zone" onclick="document.getElementById('logo').click()">
                        📸 Click to upload logo
                    </div>
                    <input type="file" id="logo" name="shop_logo" accept="image/*" style="display:none" onchange="previewImage(this, 'logoPreview')">
                    <div class="preview-row" id="logoPreviewRow">
                        <?php if (!empty($seller['shop_logo'])): ?>
                            <img src="<?= htmlspecialchars($seller['shop_logo']) ?>" class="preview-thumb" alt="Logo preview">
                        <?php endif; ?>
                    </div>
                    <div class="file-hint">JPG, PNG, WEBP, max 2MB</div>
                </div>

                <div class="upload-col">
                    <label>Shop Banner (optional)</label>
                    <div class="file-zone" onclick="document.getElementById('banner').click()">
                        🏞️ Click to upload banner
                    </div>
                    <input type="file" id="banner" name="shop_banner" accept="image/*" style="display:none" onchange="previewImage(this, 'bannerPreview')">
                    <div class="preview-row" id="bannerPreviewRow">
                        <?php if (!empty($seller['shop_banner'])): ?>
                            <img src="<?= htmlspecialchars($seller['shop_banner']) ?>" class="preview-thumb" alt="Banner preview">
                        <?php endif; ?>
                    </div>
                    <div class="file-hint">Recommended 1200x400px</div>
                </div>
            </div>

            <div class="button-group">
                <a href="personal_info.php" class="btn btn-back">← Back</a>
                <button type="submit" class="btn">Continue →</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Image preview function
    function previewImage(input, previewContainerId) {
        const container = document.getElementById(previewContainerId + 'Row');
        if (!container) return;
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Remove all existing img elements inside this preview row
                const imgs = container.querySelectorAll('img');
                imgs.forEach(img => img.remove());
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'preview-thumb';
                img.alt = 'Preview';
                container.appendChild(img);
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
</body>
</html>