<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Check if seller is logged in
if (!isset($_SESSION['seller_id'])) {
    header('Location: seller_login.php');
    exit;
}

require_once __DIR__ . '/../databases/db.php';

// ============================================
// CONSTANTS & CONFIGURATION
// ============================================
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

define('NAGARIKTA_PATH', __DIR__ . '/uploads/nagarikta/');
define('PASSPORT_PATH',  __DIR__ . '/uploads/passport/');

if (!is_dir(NAGARIKTA_PATH)) mkdir(NAGARIKTA_PATH, 0777, true);
if (!is_dir(PASSPORT_PATH))  mkdir(PASSPORT_PATH,  0777, true);

$seller_id = $_SESSION['seller_id'];
$db = getDB();

// Fetch current seller data
$stmt = $db->prepare("SELECT * FROM sellers WHERE id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$seller = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$seller) {
    session_destroy();
    header('Location: seller_login.php');
    exit;
}

// ============================================
// HANDLE UPDATE SUBMISSION
// ============================================
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form fields
    $full_name     = trim($_POST['full_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $shop_name     = trim($_POST['shop_name'] ?? '');
    $shop_address  = trim($_POST['shop_address'] ?? '');
    $shop_category = trim($_POST['shop_category'] ?? '');
    $shop_desc     = trim($_POST['shop_description'] ?? '');
    $pan_number    = trim($_POST['pan_number'] ?? '');

    // Validations
    if (empty($full_name)) $errors[] = 'Full name is required';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    if (empty($phone) || !preg_match('/^[9][6-9][0-9]{8}$/', $phone)) $errors[] = 'Valid Nepal phone number is required (98XXXXXXXX)';
    if (empty($shop_name)) $errors[] = 'Shop name is required';
    if (empty($shop_address)) $errors[] = 'Shop address is required';
    if (empty($shop_category)) $errors[] = 'Shop category is required';
    if (empty($pan_number) || !preg_match('/^[0-9]{9}$/', $pan_number)) $errors[] = 'PAN number must be 9 digits';

    // Check email/pan uniqueness for other sellers (exclude current)
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM sellers WHERE (email = ? OR pan_number = ?) AND id != ?");
        $stmt->bind_param('ssi', $email, $pan_number, $seller_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Email or PAN number already used by another seller';
        }
        $stmt->close();
    }

    // Helper function for file upload (replacement)
    function uploadReplacementFile($file, $dest_dir, $prefix, $old_filename) {
        global $errors;
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return $old_filename;
        }
        if ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = "$prefix file size must be under 5MB";
            return $old_filename;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, ALLOWED_TYPES)) {
            $errors[] = "$prefix must be JPG, PNG or WEBP image";
            return $old_filename;
        }
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest     = $dest_dir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $errors[] = "Failed to save $prefix file";
            return $old_filename;
        }
        // Delete old file if it exists and is different
        if ($old_filename && file_exists($dest_dir . $old_filename)) {
            @unlink($dest_dir . $old_filename);
        }
        return $filename;
    }

    $new_nagarikta_front = uploadReplacementFile($_FILES['nagarikta_front'] ?? null, NAGARIKTA_PATH, 'nagarikta_front', $seller['nagarikta_front']);
    $new_nagarikta_back  = uploadReplacementFile($_FILES['nagarikta_back'] ?? null,  NAGARIKTA_PATH, 'nagarikta_back',  $seller['nagarikta_back']);
    $new_passport_photo  = uploadReplacementFile($_FILES['passport_photo'] ?? null,  PASSPORT_PATH,  'passport',        $seller['passport_photo']);

    // If no errors, update database
    if (empty($errors)) {
        $stmt = $db->prepare("
            UPDATE sellers 
            SET full_name = ?, email = ?, phone = ?, shop_name = ?, 
                shop_address = ?, shop_category = ?, shop_description = ?, 
                pan_number = ?, nagarikta_front = ?, nagarikta_back = ?, passport_photo = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
            'sssssssssssi',
            $full_name, $email, $phone, $shop_name,
            $shop_address, $shop_category, $shop_desc,
            $pan_number, $new_nagarikta_front, $new_nagarikta_back, $new_passport_photo,
            $seller_id
        );
        if ($stmt->execute()) {
            // Update session data
            $_SESSION['seller_name'] = $full_name;
            $_SESSION['shop_name']   = $shop_name;
            
            // ✅ Set flash message for dashboard
            $_SESSION['profile_update_success'] = true;
            
            // ✅ Redirect to dashboard
            header('Location: seller_dashboard.php');
            exit;
        } else {
            $errors[] = 'Database update failed: ' . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Profile | BazaarNepal Seller</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&display=swap" rel="stylesheet">
<style>
/* Same CSS as registration page – copy from previous style block or include via external file */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --crimson:    #C0392B;
  --crimson-dk: #96281B;
  --gold:       #E67E22;
  --gold-lt:    #F39C12;
  --cream:      #FDF6EC;
  --ink:        #1A0A00;
  --ink-soft:   #3D2010;
  --muted:      #8B6050;
  --border:     #DDD0C4;
  --white:      #FFFFFF;
  --success:    #27AE60;
  --error:      #C0392B;
  --shadow:     rgba(26,10,0,0.12);
}
body {
  font-family: 'DM Sans', sans-serif;
  background: var(--cream);
  color: var(--ink);
  min-height: 100vh;
}
.header {
  background: var(--ink);
  padding: 0 40px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 68px;
  position: sticky; top: 0; z-index: 100;
  box-shadow: 0 2px 20px var(--shadow);
}
.logo {
  font-family: 'Sora', sans-serif;
  font-size: 22px;
  font-weight: 800;
  color: var(--white);
  letter-spacing: -0.5px;
  display: flex; align-items: center; gap: 10px;
}
.logo-badge {
  background: var(--crimson);
  color: white;
  font-size: 10px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 100px;
}
.header-link { color: var(--muted); font-size: 14px; text-decoration: none; }
.header-link:hover { color: var(--gold-lt); }
.container { max-width: 860px; margin: 0 auto; padding: 40px 20px 80px; }
.card {
  background: white;
  border: 1px solid var(--border);
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 8px 24px var(--shadow);
}
.card-header {
  background: linear-gradient(135deg, var(--ink) 0%, var(--ink-soft) 100%);
  padding: 22px 32px;
  display: flex;
  align-items: center;
  gap: 14px;
}
.card-header .icon {
  width: 44px; height: 44px; border-radius: 12px;
  background: rgba(192,57,43,0.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 20px;
}
.card-header h2 {
  font-family: 'Sora', sans-serif;
  font-size: 18px;
  font-weight: 700;
  color: white;
}
.card-header p {
  color: rgba(255,255,255,0.5);
  font-size: 13px;
  margin-top: 2px;
}
.card-body { padding: 32px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.form-grid.full { grid-template-columns: 1fr; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field label { font-size: 13px; font-weight: 600; color: var(--ink-soft); }
.field label .req { color: var(--crimson); margin-left: 2px; }
.field input, .field select, .field textarea {
  border: 1.5px solid var(--border);
  border-radius: 10px;
  padding: 12px 16px;
  font-size: 15px;
  font-family: 'DM Sans', sans-serif;
  background: var(--cream);
  transition: all .2s;
  outline: none;
  width: 100%;
}
.field input:focus, .field select:focus, .field textarea:focus {
  border-color: var(--crimson);
  background: white;
  box-shadow: 0 0 0 3px rgba(192,57,43,0.1);
}
.field-msg { font-size: 12px; margin-top: 4px; }
.field-msg.err  { color: var(--error); }
.field-msg.ok   { color: var(--success); }
.upload-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 12px; }
.upload-zone {
  border: 2px dashed var(--border);
  border-radius: 14px;
  padding: 24px 16px;
  text-align: center;
  cursor: pointer;
  transition: all .3s;
  background: var(--cream);
  position: relative;
}
.upload-zone:hover { border-color: var(--crimson); }
.upload-zone.filled { border-style: solid; border-color: var(--success); }
.upload-zone input[type="file"] {
  position: absolute; inset: 0; opacity: 0; cursor: pointer;
}
.upload-icon { font-size: 32px; margin-bottom: 10px; display: block; }
.upload-label { font-size: 13px; font-weight: 600; display: block; }
.upload-hint { font-size: 11px; color: var(--muted); }
.upload-preview {
  width: 100%; height: 100px; object-fit: cover;
  border-radius: 8px; margin-bottom: 8px; display: none;
}
.upload-zone.filled .upload-preview { display: block; }
.upload-zone.filled .upload-icon { display: none; }
.passport-zone { width: 160px; margin: 0 auto; }
.passport-zone .upload-preview { height: 120px; object-fit: cover; }
.btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 13px 28px;
  border-radius: 10px;
  font-size: 15px; font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all .2s;
}
.btn-primary {
  background: linear-gradient(135deg, var(--crimson), var(--gold));
  color: white;
  box-shadow: 0 4px 12px rgba(192,57,43,0.35);
}
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(192,57,43,0.45); }
.btn-secondary {
  background: transparent;
  color: var(--muted);
  border: 1.5px solid var(--border);
}
.btn-secondary:hover { border-color: var(--ink); color: var(--ink); }
.toast-container {
  position: fixed; top: 90px; right: 20px; z-index: 9999;
  display: flex; flex-direction: column; gap: 10px;
  max-width: 360px;
}
.toast {
  background: white;
  border-radius: 12px;
  padding: 16px 20px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.15);
  border-left: 4px solid var(--crimson);
  animation: slideIn .35s ease both;
  font-size: 14px;
}
.toast.success { border-color: var(--success); }
.toast.error   { border-color: var(--error); }
@keyframes slideIn {
  from { opacity:0; transform: translateX(40px); }
  to   { opacity:1; transform: translateX(0); }
}
@media (max-width: 640px) {
  .form-grid { grid-template-columns: 1fr; }
  .upload-grid { grid-template-columns: 1fr; }
  .card-body { padding: 20px; }
}
</style>
</head>
<body>
<header class="header">
  <div class="logo">🛒 BazaarNepal <span class="logo-badge">Seller</span></div>
  <a href="seller_dashboard.php" class="header-link">← Dashboard</a>
</header>

<div class="container">
  <div class="card">
    <div class="card-header">
      <div class="icon">✏️</div>
      <div><h2>Edit Profile</h2><p>Update your shop and personal information</p></div>
    </div>
    <div class="card-body">
      <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $err): ?>
          <div class="toast error" style="position:relative;margin-bottom:10px;">❌ <?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" id="editForm">
        <div class="form-grid">
          <div class="field col-span"><label>Full Name <span class="req">*</span></label><input type="text" name="full_name" value="<?= htmlspecialchars($seller['full_name']) ?>" required></div>
          <div class="field"><label>Email <span class="req">*</span></label><input type="email" name="email" value="<?= htmlspecialchars($seller['email']) ?>" required></div>
          <div class="field"><label>Phone <span class="req">*</span></label><input type="tel" name="phone" value="<?= htmlspecialchars($seller['phone']) ?>" maxlength="10" required></div>
          <div class="field col-span"><label>Shop Name <span class="req">*</span></label><input type="text" name="shop_name" value="<?= htmlspecialchars($seller['shop_name']) ?>" required></div>
          <div class="field"><label>Category <span class="req">*</span></label>
            <select name="shop_category" required>
              <option value="">-- Select --</option>
              <?php
              $categories = [
                'Electronics & Mobile', 'Fashion & Clothing', 'Food & Grocery', 'Home & Kitchen',
                'Health & Beauty', 'Books & Stationery', 'Sports & Outdoor', 'Toys & Games',
                'Handicrafts & Art', 'Jewelry & Accessories', 'Agriculture & Farming', 'Automotive & Parts',
                'Repair & Maintenance', 'Food Delivery', 'Education & Tutoring', 'Beauty & Salon', 'Other Services'
              ];
              foreach ($categories as $cat) {
                $selected = ($seller['shop_category'] == $cat) ? 'selected' : '';
                echo "<option value=\"$cat\" $selected>$cat</option>";
              }
              ?>
            </select>
          </div>
          <div class="field"><label>PAN Number <span class="req">*</span></label><input type="text" name="pan_number" value="<?= htmlspecialchars($seller['pan_number']) ?>" maxlength="9" required></div>
          <div class="field col-span"><label>Shop Address <span class="req">*</span></label><input type="text" name="shop_address" value="<?= htmlspecialchars($seller['shop_address']) ?>" required></div>
          <div class="field col-span"><label>Shop Description</label><textarea name="shop_description" rows="3"><?= htmlspecialchars($seller['shop_description']) ?></textarea></div>
        </div>

        <hr style="margin: 28px 0; border-color:var(--border);">
        <h3 style="font-size: 18px; margin-bottom: 20px;">📄 Update Documents (optional)</h3>
        <p style="font-size:14px;color:var(--muted);margin-bottom:20px;">Leave file fields empty to keep current documents. Max 5MB each.</p>

        <div style="margin-bottom: 28px;">
          <div><strong>Nagarikta</strong></div>
          <div class="upload-grid">
            <div>
              <div class="upload-zone <?= $seller['nagarikta_front'] ? 'filled' : '' ?>" id="zone-nag-front">
                <input type="file" name="nagarikta_front" accept="image/*" onchange="previewFile(this,'zone-nag-front','prev-nag-front')">
                <img src="<?= $seller['nagarikta_front'] ? './uploads/nagarikta/' . htmlspecialchars($seller['nagarikta_front']) : '' ?>" class="upload-preview" id="prev-nag-front" alt="Front Preview">
                <span class="upload-icon">📸</span>
                <span class="upload-label"><?= $seller['nagarikta_front'] ? 'Change front' : 'Upload front' ?></span>
                <span class="upload-hint">Click or drag</span>
              </div>
            </div>
            <div>
              <div class="upload-zone <?= $seller['nagarikta_back'] ? 'filled' : '' ?>" id="zone-nag-back">
                <input type="file" name="nagarikta_back" accept="image/*" onchange="previewFile(this,'zone-nag-back','prev-nag-back')">
                <img src="<?= $seller['nagarikta_back'] ? './uploads/nagarikta/' . htmlspecialchars($seller['nagarikta_back']) : '' ?>" class="upload-preview" id="prev-nag-back" alt="Back Preview">
                <span class="upload-icon">📸</span>
                <span class="upload-label"><?= $seller['nagarikta_back'] ? 'Change back' : 'Upload back' ?></span>
                <span class="upload-hint">Click or drag</span>
              </div>
            </div>
          </div>
        </div>

        <div>
          <div><strong>Passport Photo</strong></div>
          <div style="width:180px;">
            <div class="upload-zone passport-zone <?= $seller['passport_photo'] ? 'filled' : '' ?>" id="zone-passport">
              <input type="file" name="passport_photo" accept="image/*" onchange="previewFile(this,'zone-passport','prev-passport')">
              <img src="<?= $seller['passport_photo'] ? './uploads/passport/' . htmlspecialchars($seller['passport_photo']) : '' ?>" class="upload-preview" id="prev-passport" alt="Passport Preview">
              <span class="upload-icon">🖼️</span>
              <span class="upload-label"><?= $seller['passport_photo'] ? 'Change photo' : 'Upload photo' ?></span>
              <span class="upload-hint">JPG/PNG/WEBP</span>
            </div>
          </div>
        </div>

        <div style="margin-top: 32px; display: flex; gap: 16px; justify-content: flex-end;">
          <a href="seller_dashboard.php" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary">💾 Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function previewFile(input, zoneId, previewId) {
  const file = input.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) {
    alert('File too large! Max 5MB.');
    input.value = '';
    return;
  }
  const reader = new FileReader();
  reader.onload = e => {
    const img = document.getElementById(previewId);
    const zone = document.getElementById(zoneId);
    img.src = e.target.result;
    zone.classList.add('filled');
    zone.querySelector('.upload-label').textContent = '✓ ' + file.name.substring(0,20);
  };
  reader.readAsDataURL(file);
}

// Phone number & PAN number formatting
document.querySelector('input[name="phone"]')?.addEventListener('input', function(e) {
  this.value = this.value.replace(/\D/g,'').slice(0,10);
});
document.querySelector('input[name="pan_number"]')?.addEventListener('input', function(e) {
  this.value = this.value.replace(/\D/g,'').slice(0,9);
});

// Auto-hide error toasts after 5 seconds
document.querySelectorAll('.toast.error').forEach(t => {
  setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 500); }, 5000);
});
</script>
</body>
</html>