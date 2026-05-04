<?php
session_start();
require_once __DIR__ . '/../databases/db.php';

if (!isset($_SESSION['seller_id'])) {
    header('Location: register.php');
    exit;
}

$seller_id = (int)$_SESSION['seller_id'];
$db = getDB();

// Updated columns: bank_name, bank_account_number instead of bank_account_details
$stmt = $db->prepare("SELECT citizenship_number, bank_holder_name, bank_name, bank_branch, bank_account_number, driving_license, nagarikta_front, nagarikta_back, passport_photo, bank_cheque_image FROM sellers WHERE id = ?");
$stmt->bind_param('i', $seller_id);
$stmt->execute();
$seller = $stmt->get_result()->fetch_assoc();
$stmt->close();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $citizenship       = trim($_POST['citizenship_number'] ?? '');
    $bank_holder       = trim($_POST['bank_holder_name'] ?? '');
    $bank_name         = trim($_POST['bank_name'] ?? '');
    $bank_branch       = trim($_POST['bank_branch'] ?? '');
    $bank_account_num  = trim($_POST['bank_account_number'] ?? '');
    $driving_license   = trim($_POST['driving_license'] ?? '');

    if (strlen($citizenship) < 3) {
        $error = 'Citizenship / NID number is required.';
    } elseif (strlen($bank_holder) < 3) {
        $error = 'Bank account holder name is required.';
    } elseif (empty($bank_name)) {
        $error = 'Please select a bank name.';
    } elseif (strlen($bank_branch) < 2) {
        $error = 'Bank branch name is required.';
    } elseif (strlen($bank_account_num) < 4) {
        $error = 'Bank account number is required.';
    } else {
        // Check if citizenship number is already used by another seller
        $check_stmt = $db->prepare("SELECT id FROM sellers WHERE citizenship_number = ? AND id != ?");
        $check_stmt->bind_param('si', $citizenship, $seller_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = 'This Citizenship / NID number is already registered by another seller.';
        } else {
            $check_stmt->close();

            // --- Updated: Use chat_uploads inside sellers folder ---
            $upload_nag    = __DIR__ . '/chat_uploads/nagarikta/';
            $upload_pass   = __DIR__ . '/chat_uploads/passport/';
            $upload_cheque = __DIR__ . '/chat_uploads/cheque/';
            foreach ([$upload_nag, $upload_pass, $upload_cheque] as $dir) {
                if (!is_dir($dir)) mkdir($dir, 0755, true);
            }

            $updates = [];
            $params  = [];
            $types   = '';
            $upload_error = false;

            // File uploads handling
            $file_fields = ['nagarikta_front', 'nagarikta_back', 'passport_photo', 'bank_cheque_image'];
            foreach ($file_fields as $field) {
                if (!empty($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                    $allowed = ($field === 'passport_photo') ? ['jpg','jpeg','png','webp'] : ['jpg','jpeg','png','gif','pdf'];
                    if (!in_array($ext, $allowed)) {
                        $error = ucfirst(str_replace('_', ' ', $field)) . ' must be ' . implode(', ', $allowed);
                        $upload_error = true;
                        break;
                    }
                    $filename = time() . '_' . $field . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $target_dir = ($field === 'nagarikta_front' || $field === 'nagarikta_back') ? $upload_nag : (($field === 'passport_photo') ? $upload_pass : $upload_cheque);
                    if (move_uploaded_file($_FILES[$field]['tmp_name'], $target_dir . $filename)) {
                        $updates[] = "$field = ?";
                        $params[] = $filename;
                        $types .= 's';
                    } else {
                        $error = "Failed to upload $field – check folder permissions.";
                        $upload_error = true;
                        break;
                    }
                }
            }

            if (!$upload_error) {
                // Add text fields
                $updates[] = "citizenship_number = ?";
                $params[] = $citizenship;
                $types .= 's';

                $updates[] = "bank_holder_name = ?";
                $params[] = $bank_holder;
                $types .= 's';

                $updates[] = "bank_name = ?";
                $params[] = $bank_name;
                $types .= 's';

                $updates[] = "bank_branch = ?";
                $params[] = $bank_branch;
                $types .= 's';

                $updates[] = "bank_account_number = ?";
                $params[] = $bank_account_num;
                $types .= 's';

                $updates[] = "driving_license = ?";
                $params[] = $driving_license ?: null;
                $types .= 's';

                $params[] = $seller_id;
                $types .= 'i';

                $sql = "UPDATE sellers SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param($types, ...$params);
                    if ($stmt->execute()) {
                        header('Location: business.php');
                        exit;
                    } else {
                        $error = 'Database error: ' . $db->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'Database prepare error: ' . $db->error;
                }
            }
        }
        if (isset($check_stmt) && $check_stmt) $check_stmt->close();
    }
}

require_once __DIR__ . '/sidenav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 3: KYC & Banking | SportGhar Seller</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            height: 100%;
            font-family: 'Plus Jakarta Sans', sans-serif;
            overflow: hidden;
        }

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
            width: 560px;
            flex-shrink: 0;
            background: #fff;
            height: 100%;
            padding: 32px 28px;
            display: flex;
            flex-direction: column;
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

        .file-zone {
            border: 2px dashed #E0DBD4;
            border-radius: 14px;
            padding: 12px;
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

        .row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .button-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 24px;
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
            .panel-right { width: 100%; height: auto; flex: 1; }
            .caption { font-size: 1.2rem; padding: 6px 18px; }
            .row-2 { grid-template-columns: 1fr; gap: 12px; }
        }
    </style>
</head>
<body>

<div class="page-wrapper">

    <!-- LEFT PANEL -->
    <div class="panel-left">
        <img class="full-image" 
             src="https://images.unsplash.com/photo-1589487391730-58f20eb2c308?w=1200&h=800&fit=crop" 
             alt="Sports background - KYC verification"
             loading="eager">
        <div class="image-overlay">
            <div class="caption">🔐 Secure & Verified</div>
            <div class="sub-caption">Fast payouts • Trusted identity • Safe onboarding</div>
        </div>
    </div>

    <!-- RIGHT PANEL: KYC & Banking -->
    <div class="panel-right">
        <div class="step-badge">● STEP 3 OF 6</div>
        <div class="step-dots">
            <span class="on"></span>
            <span class="on"></span>
            <span class="on"></span>
            <span></span><span></span><span></span>
        </div>

        <h1>KYC & Banking Details</h1>
        <p class="subtitle">Verify your identity and provide bank information for payouts.</p>

        <?php if ($error): ?>
            <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="kycForm">
            <!-- Row 1: Bank Holder Name + Bank Name (Dropdown) -->
            <div class="row-2">
                <div class="form-group">
                    <label>Bank Account Holder Name *</label>
                    <input type="text" name="bank_holder_name" value="<?= htmlspecialchars($seller['bank_holder_name'] ?? '') ?>" required placeholder="e.g., Gobind Adhikari">
                </div>
                <div class="form-group">
                    <label>Bank Name *</label>
                    <select name="bank_name" id="bank_name" required>
                        <option value="">-- Select Bank --</option>
                        <optgroup label="🇳🇵 Nepal Banks">
                            <option value="Nepal Bank Limited" <?= ($seller['bank_name'] ?? '') == 'Nepal Bank Limited' ? 'selected' : '' ?>>Nepal Bank Limited</option>
                            <option value="Rastriya Banijya Bank" <?= ($seller['bank_name'] ?? '') == 'Rastriya Banijya Bank' ? 'selected' : '' ?>>Rastriya Banijya Bank</option>
                            <option value="Agriculture Development Bank" <?= ($seller['bank_name'] ?? '') == 'Agriculture Development Bank' ? 'selected' : '' ?>>Agriculture Development Bank</option>
                            <option value="Nabil Bank" <?= ($seller['bank_name'] ?? '') == 'Nabil Bank' ? 'selected' : '' ?>>Nabil Bank</option>
                            <option value="Himalayan Bank" <?= ($seller['bank_name'] ?? '') == 'Himalayan Bank' ? 'selected' : '' ?>>Himalayan Bank</option>
                            <option value="Siddhartha Bank" <?= ($seller['bank_name'] ?? '') == 'Siddhartha Bank' ? 'selected' : '' ?>>Siddhartha Bank</option>
                            <option value="Global IME Bank" <?= ($seller['bank_name'] ?? '') == 'Global IME Bank' ? 'selected' : '' ?>>Global IME Bank</option>
                            <option value="Prabhu Bank" <?= ($seller['bank_name'] ?? '') == 'Prabhu Bank' ? 'selected' : '' ?>>Prabhu Bank</option>
                            <option value="Kumari Bank" <?= ($seller['bank_name'] ?? '') == 'Kumari Bank' ? 'selected' : '' ?>>Kumari Bank</option>
                            <option value="Laxmi Bank" <?= ($seller['bank_name'] ?? '') == 'Laxmi Bank' ? 'selected' : '' ?>>Laxmi Bank</option>
                        </optgroup>
                        <optgroup label="🌍 International Banks">
                            <option value="Citibank" <?= ($seller['bank_name'] ?? '') == 'Citibank' ? 'selected' : '' ?>>Citibank</option>
                            <option value="HSBC" <?= ($seller['bank_name'] ?? '') == 'HSBC' ? 'selected' : '' ?>>HSBC</option>
                            <option value="Standard Chartered" <?= ($seller['bank_name'] ?? '') == 'Standard Chartered' ? 'selected' : '' ?>>Standard Chartered</option>
                            <option value="Wells Fargo" <?= ($seller['bank_name'] ?? '') == 'Wells Fargo' ? 'selected' : '' ?>>Wells Fargo</option>
                            <option value="Bank of America" <?= ($seller['bank_name'] ?? '') == 'Bank of America' ? 'selected' : '' ?>>Bank of America</option>
                            <option value="ICICI Bank" <?= ($seller['bank_name'] ?? '') == 'ICICI Bank' ? 'selected' : '' ?>>ICICI Bank</option>
                            <option value="SBI" <?= ($seller['bank_name'] ?? '') == 'SBI' ? 'selected' : '' ?>>State Bank of India</option>
                            <option value="Other (Please specify)" <?= ($seller['bank_name'] ?? '') == 'Other' ? 'selected' : '' ?>>Other (Please specify)</option>
                        </optgroup>
                    </select>
                    <input type="text" id="other_bank_name" name="other_bank_name" style="display:none; margin-top:8px;" placeholder="Enter bank name" value="">
                </div>
            </div>

            <!-- Row 2: Bank Branch + Bank Account Number -->
            <div class="row-2">
                <div class="form-group">
                    <label>Bank Branch *</label>
                    <input type="text" name="bank_branch" value="<?= htmlspecialchars($seller['bank_branch'] ?? '') ?>" required placeholder="e.g., New Road Branch">
                </div>
                <div class="form-group">
                    <label>Bank Account Number *</label>
                    <input type="text" name="bank_account_number" value="<?= htmlspecialchars($seller['bank_account_number'] ?? '') ?>" required placeholder="Your account number">
                </div>
            </div>

            <!-- Row 3: Citizenship / NID Number + Driving License (Optional) -->
            <div class="row-2">
                <div class="form-group">
                    <label>Citizenship / NID Number *</label>
                    <input type="text" name="citizenship_number" value="<?= htmlspecialchars($seller['citizenship_number'] ?? '') ?>" required placeholder="e.g., 12-01-1234567">
                </div>
                <div class="form-group">
                    <label>Driving License (Optional)</label>
                    <input type="text" name="driving_license" value="<?= htmlspecialchars($seller['driving_license'] ?? '') ?>" placeholder="If available">
                </div>
            </div>

            <!-- File uploads: Bank Cheque + Passport Photo (first row) -->
            <div class="row-2">
                <div class="form-group">
                    <label>📸 Bank Cheque / Passbook Copy *</label>
                    <div class="file-zone" onclick="document.getElementById('bank_cheque_image').click()">💰 Click to upload (JPG, PNG, PDF)</div>
                    <input type="file" id="bank_cheque_image" name="bank_cheque_image" accept="image/*,application/pdf" style="display:none" onchange="previewFile(this, 'chequePreview')">
                    <div class="preview-row" id="chequePreviewRow">
                        <?php if (!empty($seller['bank_cheque_image'])): ?>
                            <div class="current-file">✓ Already uploaded: <?= htmlspecialchars($seller['bank_cheque_image']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="file-hint">JPG, PNG, GIF, PDF (max 5MB)</div>
                </div>

                <div class="form-group">
                    <label>📸 Passport‑Size Photo *</label>
                    <div class="file-zone" onclick="document.getElementById('passport_photo').click()">🤳 Upload clear face photo</div>
                    <input type="file" id="passport_photo" name="passport_photo" accept="image/*" style="display:none" onchange="previewFile(this, 'passportPreview')">
                    <div class="preview-row" id="passportPreviewRow">
                        <?php if (!empty($seller['passport_photo'])): ?>
                            <div class="current-file">✓ Already uploaded: <?= htmlspecialchars($seller['passport_photo']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="file-hint">JPG, PNG, WEBP, max 2MB</div>
                </div>
            </div>

            <!-- File uploads: ID Card Front & Back (second row) -->
            <div class="row-2">
                <div class="form-group">
                    <label>🪪 ID Card — Front *</label>
                    <div class="file-zone" onclick="document.getElementById('nagarikta_front').click()">📷 Upload front side</div>
                    <input type="file" id="nagarikta_front" name="nagarikta_front" accept="image/*,application/pdf" style="display:none" onchange="previewFile(this, 'frontPreview')">
                    <div class="preview-row" id="frontPreviewRow">
                        <?php if (!empty($seller['nagarikta_front'])): ?>
                            <div class="current-file">✓ Already uploaded: <?= htmlspecialchars($seller['nagarikta_front']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>🪪 ID Card — Back *</label>
                    <div class="file-zone" onclick="document.getElementById('nagarikta_back').click()">📷 Upload back side</div>
                    <input type="file" id="nagarikta_back" name="nagarikta_back" accept="image/*,application/pdf" style="display:none" onchange="previewFile(this, 'backPreview')">
                    <div class="preview-row" id="backPreviewRow">
                        <?php if (!empty($seller['nagarikta_back'])): ?>
                            <div class="current-file">✓ Already uploaded: <?= htmlspecialchars($seller['nagarikta_back']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="button-group">
                <a href="shop_info.php" class="btn btn-back">← Back</a>
                <button type="submit" class="btn">Continue →</button>
            </div>
        </form>
    </div>
</div>

<script>
    function previewFile(input, previewId) {
        const container = document.getElementById(previewId + 'Row');
        if (!container) return;
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            reader.onload = function(e) {
                while (container.firstChild) {
                    container.removeChild(container.firstChild);
                }
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'preview-thumb';
                    img.alt = 'Preview';
                    container.appendChild(img);
                } else {
                    const span = document.createElement('span');
                    span.textContent = '📄 ' + file.name;
                    span.style.fontSize = '12px';
                    span.style.color = '#2A7A4B';
                    span.style.background = '#EBF9F0';
                    span.style.padding = '4px 10px';
                    span.style.borderRadius = '20px';
                    container.appendChild(span);
                }
            };
            reader.readAsDataURL(file);
        }
    }

    // Show/hide "Other bank" text field
    const bankSelect = document.getElementById('bank_name');
    const otherBankInput = document.getElementById('other_bank_name');
    const originalBankValue = "<?= htmlspecialchars($seller['bank_name'] ?? '') ?>";

    function toggleOtherBank() {
        if (bankSelect.value === 'Other (Please specify)') {
            otherBankInput.style.display = 'block';
            otherBankInput.setAttribute('name', 'bank_name');
            otherBankInput.required = true;
            bankSelect.removeAttribute('name');
            if (originalBankValue && originalBankValue !== 'Other (Please specify)' && originalBankValue !== 'Other') {
                otherBankInput.value = originalBankValue;
            }
        } else {
            otherBankInput.style.display = 'none';
            otherBankInput.removeAttribute('name');
            bankSelect.setAttribute('name', 'bank_name');
            otherBankInput.required = false;
        }
    }

    bankSelect.addEventListener('change', toggleOtherBank);
    toggleOtherBank();

    // Ensure form submits the correct bank name
    document.getElementById('kycForm').addEventListener('submit', function(e) {
        if (bankSelect.value === 'Other (Please specify)') {
            if (!otherBankInput.value.trim()) {
                e.preventDefault();
                alert('Please enter the bank name.');
            }
        }
    });
</script>
</body>
</html>