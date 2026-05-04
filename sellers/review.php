<?php
ob_start();
session_start();
require_once __DIR__ . '/../databases/db.php';

if (!isset($_SESSION['seller_id'])) {
    header('Location: register.php');
    exit;
}

$seller_id = (int)$_SESSION['seller_id'];
$db = getDB();
$error = '';

/* ── FETCH ALL SELLER DATA ─────────────────────────────── */
$stmt = $db->prepare("SELECT * FROM sellers WHERE id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$seller = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$seller) die("Seller not found. Session seller_id=" . $seller_id);

/* ── FINAL SUBMIT ──────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    if (!isset($_POST['agree'])) {
        $error = "You must agree to the Terms & Conditions and Privacy Policy.";
    } else {
        $now = date("Y-m-d H:i:s");
        $stmt = $db->prepare("UPDATE sellers SET profile_completed=1, status='pending', agreement_accepted=1, agreement_accepted_at=? WHERE id=?");
        $stmt->bind_param("si", $now, $seller_id);
        if ($stmt->execute()) {
            header("Location: seller_dashboard.php");
            exit;
        } else {
            $error = "Unable to complete submission. Please contact support.";
        }
        $stmt->close();
    }
}

/* ── HELPER FUNCTIONS (with better debugging) ───────────── */
function v($seller, $key) {
    $val = $seller[$key] ?? null;
    if ($val === null || trim((string)$val) === '') return null;
    return trim((string)$val);
}

function d($seller, $key) {
    $val = v($seller, $key);
    if ($val === null) return '<span class="val-empty">Not provided</span>';
    return '<span>' . nl2br(htmlspecialchars($val)) . '</span>';
}

// Resolve web path for images – works with your folder structure
function resolveUploadPath($value, $type = '') {
    $value = trim((string)$value);
    if ($value === '') return null;

    $subdirs = [
        'nagarikta' => 'nagarikta',
        'passport'  => 'passport',
        'cheque'    => 'cheque',
        'logo'      => 'shop_logo',
        'banner'    => 'shop_banner',
    ];

    // Try to extract filename
    if (preg_match('#publics/uploads/([^/]+)/(.+)$#', $value, $m)) {
        $subdir = $m[1];
        $filename = $m[2];
    } elseif (preg_match('#uploads/([^/]+)/(.+)$#', $value, $m)) {
        $subdir = $m[1];
        $filename = $m[2];
    } elseif (strpos($value, '/') !== false) {
        $parts = explode('/', $value, 2);
        $subdir = $parts[0];
        $filename = $parts[1];
    } else {
        $filename = $value;
        $subdir = $subdirs[$type] ?? null;
    }
    if (!$filename || !$subdir) return null;

    return '../publics/uploads/' . $subdir . '/' . $filename;
}

function showDoc($value, $type = '') {
    $value = trim((string)$value);
    if ($value === '') {
        return '<span class="badge badge-missing">📄 Not uploaded</span>';
    }
    $webPath = resolveUploadPath($value, $type);
    if (!$webPath) {
        return '<span class="badge badge-missing">⚠️ Invalid path</span>';
    }
    $ext = strtolower(pathinfo($webPath, PATHINFO_EXTENSION));
    $safe = htmlspecialchars($webPath);
    if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
        return '<img src="' . $safe . '" class="doc-img" onclick="openLightbox(this.src)" alt="Document"
                     onerror="this.outerHTML=\'<span class=\\\'badge badge-missing\\\'>⚠️ File missing</span>\'">';
    }
    if ($ext === 'pdf') {
        return '<a href="' . $safe . '" target="_blank" class="badge badge-file">📎 View PDF</a>';
    }
    return '<span class="badge badge-missing">Unknown file type</span>';
}

require_once __DIR__ . '/sidenav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 6: Review & Submit | SportGhar Seller</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --sidenav-width: 240px;
            --red: #C0392B;
            --red-dark: #a93226;
            --red-soft: rgba(192,57,43,0.08);
            --border: #E8E2DC;
            --text: #1C1612;
            --muted: #8A7D72;
        }
        html, body { height: 100%; font-family: 'Plus Jakarta Sans', sans-serif; background: #F7F3EE; overflow: hidden; }
        .page-wrapper { position: fixed; top: 0; left: var(--sidenav-width); right: 0; bottom: 0; display: flex; }
        /* left panel styles - kept as original */
        .panel-left { width: 260px; flex-shrink: 0; position: relative; overflow: hidden; }
        .panel-left img.bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .panel-left .overlay { position: absolute; inset: 0; background: linear-gradient(180deg,rgba(10,25,22,.5) 0%,rgba(10,25,22,.93) 100%); }
        .left-content { position: relative; z-index: 2; height: 100%; display: flex; flex-direction: column; justify-content: space-between; padding: 32px 24px; }
        .brand { display: flex; align-items: center; gap: 9px; }
        .brand-dot { width: 9px; height: 9px; background: #FF6B4A; border-radius: 50%; }
        .brand-name { font-family: 'Fraunces',serif; font-size: 1rem; font-weight: 700; color: #FFEAC5; }
        .left-body h2 { font-family: 'Fraunces',serif; font-size: 1.75rem; font-weight: 700; color: #fff; line-height: 1.2; margin-bottom: 12px; }
        .left-body p { font-size: 12px; color: #C0B8A8; line-height: 1.7; margin-bottom: 24px; }
        .checklist { list-style: none; display: flex; flex-direction: column; gap: 9px; }
        .checklist li { display: flex; align-items: center; gap: 9px; font-size: 11.5px; color: #C8C0B0; }
        .ck { width: 19px; height: 19px; border-radius: 50%; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.15); display: flex; align-items: center; justify-content: center; font-size: 9px; flex-shrink: 0; }
        .checklist li.done .ck { background: #27AE60; border-color: #27AE60; color: #fff; }
        .checklist li.current .ck { background: var(--red); border-color: var(--red); color: #fff; }
        .step-indicator { font-size: 10px; color: rgba(255,255,255,.35); letter-spacing: 1.2px; text-transform: uppercase; }

        /* Right panel */
        .panel-right { flex: 1; height: 100%; overflow-y: auto; padding: 32px 36px 40px; }
        .panel-right::-webkit-scrollbar { width: 5px; }
        .panel-right::-webkit-scrollbar-thumb { background: #D8D0C8; border-radius: 10px; }
        .rh { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 6px; }
        .rh h1 { font-family: 'Fraunces',serif; font-size: 1.5rem; color: var(--text); line-height: 1.2; }
        .rh .sub { font-size: 12px; color: var(--muted); margin-top: 4px; }
        .step-pill { background: var(--red-soft); color: var(--red); font-size: 10px; font-weight: 700; padding: 5px 13px; border-radius: 100px; white-space: nowrap; }
        .step-dots { display: flex; gap: 4px; margin: 10px 0 20px; }
        .step-dots span { display: block; width: 26px; height: 3px; border-radius: 2px; background: #E0D9D2; }
        .step-dots span.on { background: var(--red); }
        .status-banner { display: flex; align-items: center; gap: 14px; background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; }
        .status-banner .icon { font-size: 22px; }
        .status-banner strong { display: block; font-size: 12.5px; color: #92400E; margin-bottom: 1px; }
        .status-banner span { font-size: 11px; color: #B45309; }
        .error-box { background: #FFF5F5; border-left: 3px solid var(--red); color: var(--red); padding: 10px 14px; border-radius: 10px; font-size: 12px; margin-bottom: 16px; }

        /* Review grid */
        .review-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; margin-bottom: 20px; }
        .card { background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 16px 18px; }
        .card.span2 { grid-column: span 2; }
        .card.span3 { grid-column: span 3; }
        .card-head { display: flex; align-items: center; gap: 8px; margin-bottom: 13px; padding-bottom: 10px; border-bottom: 1.5px solid #F2EDE8; }
        .card-icon { width: 28px; height: 28px; border-radius: 7px; background: var(--red-soft); display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; }
        .card-title { font-size: 11px; font-weight: 700; color: var(--text); text-transform: uppercase; letter-spacing: .6px; }
        .edit-link { margin-left: auto; font-size: 10.5px; color: var(--red); text-decoration: none; font-weight: 600; opacity: .65; transition: opacity .15s; }
        .edit-link:hover { opacity: 1; }
        .ir { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 9px; }
        .il { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); width: 95px; flex-shrink: 0; padding-top: 1px; line-height: 1.4; }
        .iv { font-size: 12.5px; font-weight: 500; color: var(--text); flex: 1; line-height: 1.5; word-break: break-word; }
        .val-empty { color: #C4B9B2; font-style: italic; font-size: 11.5px; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; }
        .doc-img { width: 64px; height: 54px; object-fit: cover; border-radius: 8px; border: 1.5px solid var(--border); cursor: pointer; transition: transform .15s,box-shadow .15s; }
        .doc-img:hover { transform: scale(1.07); box-shadow: 0 5px 16px rgba(0,0,0,.18); }
        .docs-row { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 12px; }
        .doc-item { display: flex; flex-direction: column; align-items: center; gap: 5px; }
        .doc-item-label { font-size: 9px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .3px; text-align: center; }
        .shop-banner-img { width: 100%; max-height: 72px; object-fit: cover; border-radius: 9px; border: 1.5px solid var(--border); cursor: pointer; margin-top: 4px; }
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 10.5px; font-weight: 600; }
        .badge-missing { background: #FFF0ED; color: #C0392B; border: 1px solid #F5C6C0; }
        .badge-file { background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; text-decoration: none; }

        .terms-box { background: #fff; border: 1px solid var(--border); border-radius: 13px; padding: 15px 18px; display: flex; gap: 13px; align-items: flex-start; margin-bottom: 18px; }
        .terms-box input { width: 17px; height: 17px; accent-color: var(--red); margin-top: 1px; flex-shrink: 0; cursor: pointer; }
        .terms-box label { font-size: 11.5px; line-height: 1.6; color: #5A4A3F; cursor: pointer; }
        .terms-box a { color: var(--red); font-weight: 600; text-decoration: none; }
        .button-group { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 24px; }
        .btn { padding: 10px 24px; border: none; border-radius: 100px; font-family: 'Plus Jakarta Sans',sans-serif; font-weight: 700; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all .15s; }
        .btn-primary { background: var(--red); color: #fff; }
        .btn-primary:hover { background: var(--red-dark); transform: translateY(-1px); }
        .btn-back { background: #EDE8E2; color: #6B5C52; }
        .btn-back:hover { background: #E2DAD2; }
        .footer-note { text-align: center; font-size: 10.5px; color: #B0A398; padding-top: 16px; border-top: 1px solid var(--border); }

        .lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.93); z-index: 9999; align-items: center; justify-content: center; cursor: zoom-out; }
        .lightbox.open { display: flex; }
        .lightbox img { max-width: 90vw; max-height: 88vh; border-radius: 12px; }
        .lb-close { position: absolute; top: 22px; right: 30px; color: #fff; font-size: 36px; cursor: pointer; }

        /* Debug table (only visible when ?debug=1) */
        .debug-table { display: none; margin-top: 30px; background: #1E1E2F; color: #f8f8f2; padding: 15px; border-radius: 12px; font-family: monospace; font-size: 11px; overflow-x: auto; }
        body.debug .debug-table { display: block; }
        @media (max-width: 1200px) { .review-grid { grid-template-columns: repeat(2,1fr); } .card.span3 { grid-column: span 2; } }
        @media (max-width: 860px) { .page-wrapper { left: 0; } .panel-left { display: none; } .panel-right { padding: 20px; } }
        @media (max-width: 640px) { .review-grid { grid-template-columns: 1fr; } .card.span2,.card.span3 { grid-column: span 1; } .two-col { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="<?= isset($_GET['debug']) ? 'debug' : '' ?>">
<div class="page-wrapper">
    <!-- LEFT PANEL (unchanged) -->
    <div class="panel-left">
        <img class="bg" src="https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=600&h=900&fit=crop" alt="">
        <div class="overlay"></div>
        <div class="left-content">
            <div class="brand"><div class="brand-dot"></div><div class="brand-name">SportGhar</div></div>
            <div class="left-body">
                <h2>Almost There!</h2>
                <p>Review your details carefully. Our team verifies applications within 24–48 hours.</p>
                <ul class="checklist">
                    <li class="done"><div class="ck">✓</div> Personal Info</li>
                    <li class="done"><div class="ck">✓</div> Shop Details</li>
                    <li class="done"><div class="ck">✓</div> KYC &amp; Banking</li>
                    <li class="done"><div class="ck">✓</div> Business Info</li>
                    <li class="done"><div class="ck">✓</div> Contact &amp; Support</li>
                    <li class="current"><div class="ck">→</div> Review &amp; Submit</li>
                </ul>
            </div>
            <div class="step-indicator">Step 6 of 6</div>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="panel-right">
        <div class="rh">
            <div><h1>Review Your Application</h1><p class="sub">Verify all details before submitting for approval.</p></div>
            <div class="step-pill">● STEP 6 OF 6</div>
        </div>
        <div class="step-dots"><span class="on"></span><span class="on"></span><span class="on"></span><span class="on"></span><span class="on"></span><span class="on"></span></div>
        <?php if ($error): ?><div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
        <div class="status-banner"><div class="icon">⏳</div><div><strong>Pending Submission</strong><span>After submitting, our team will review within 24–48 hours and notify you by email.</span></div></div>

        <!-- REVIEW CARDS -->
        <div class="review-grid">
            <!-- Personal -->
            <div class="card">
                <div class="card-head"><div class="card-icon">👤</div><div class="card-title">Personal Info</div><a href="personal_info.php" class="edit-link">✏️ Edit</a></div>
                <div class="ir"><div class="il">Full Name</div><div class="iv"><?= d($seller,'full_name') ?></div></div>
                <div class="ir"><div class="il">Email</div><div class="iv"><?= d($seller,'email') ?></div></div>
                <div class="ir"><div class="il">Phone</div><div class="iv"><?= d($seller,'phone') ?></div></div>
            </div>

            <!-- Shop Details (span2) -->
            <div class="card span2">
                <div class="card-head"><div class="card-icon">🏪</div><div class="card-title">Shop Details</div><a href="shop_info.php" class="edit-link">✏️ Edit</a></div>
                <div class="two-col">
                    <div>
                        <div class="ir"><div class="il">Shop Name</div><div class="iv"><?= d($seller,'shop_name') ?></div></div>
                        <div class="ir"><div class="il">Category</div><div class="iv"><?= d($seller,'shop_category') ?></div></div>
                        <div class="ir"><div class="il">Address</div><div class="iv"><?= d($seller,'shop_address') ?></div></div>
                    </div>
                    <div>
                        <?php if ($logoVal = v($seller,'shop_logo')): ?>
                            <div class="ir"><div class="il">Logo</div><div class="iv"><?= showDoc($logoVal,'logo') ?></div></div>
                        <?php endif; ?>
                        <?php if ($bannerVal = v($seller,'shop_banner')): ?>
                            <div class="ir"><div class="il">Banner</div><div class="iv"><img src="<?= htmlspecialchars(resolveUploadPath($bannerVal,'banner') ?? '') ?>" class="shop-banner-img" onclick="openLightbox(this.src)" onerror="this.style.display='none'" alt="Banner"></div></div>
                        <?php endif; ?>
                        <?php if (!v($seller,'shop_logo') && !v($seller,'shop_banner')): ?>
                            <div class="ir"><div class="il">Images</div><div class="iv"><span class="val-empty">None uploaded</span></div></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- KYC & Identity (span2) -->
            <div class="card span2">
                <div class="card-head"><div class="card-icon">🪪</div><div class="card-title">KYC &amp; Identity</div><a href="kyc.php" class="edit-link">✏️ Edit</a></div>
                <div class="ir"><div class="il">Citizenship No.</div><div class="iv"><?= d($seller,'citizenship_number') ?></div></div>
                <div class="ir"><div class="il">Driving License</div><div class="iv"><?= d($seller,'driving_license') ?></div></div>
                <div class="docs-row">
                    <div class="doc-item"><div class="doc-item-label">ID Front</div><?= showDoc($seller['nagarikta_front'] ?? '', 'nagarikta') ?></div>
                    <div class="doc-item"><div class="doc-item-label">ID Back</div><?= showDoc($seller['nagarikta_back'] ?? '', 'nagarikta') ?></div>
                    <div class="doc-item"><div class="doc-item-label">Passport Photo</div><?= showDoc($seller['passport_photo'] ?? '', 'passport') ?></div>
                </div>
            </div>

            <!-- Banking -->
            <div class="card">
                <div class="card-head"><div class="card-icon">🏦</div><div class="card-title">Banking</div><a href="kyc.php" class="edit-link">✏️ Edit</a></div>
                <div class="ir"><div class="il">Account Holder</div><div class="iv"><?= d($seller,'bank_holder_name') ?></div></div>
                <div class="ir"><div class="il">Bank Name</div><div class="iv"><?= d($seller,'bank_name') ?></div></div>
                <div class="ir"><div class="il">Branch</div><div class="iv"><?= d($seller,'bank_branch') ?></div></div>
                <div class="ir"><div class="il">Account No.</div><div class="iv"><?= d($seller,'bank_account_number') ?></div></div>
                <div class="docs-row"><div class="doc-item"><div class="doc-item-label">Cheque Copy</div><?= showDoc($seller['bank_cheque_image'] ?? '', 'cheque') ?></div></div>
            </div>

            <!-- Business & Tax -->
            <div class="card">
                <div class="card-head"><div class="card-icon">📊</div><div class="card-title">Business &amp; Tax</div><a href="business.php" class="edit-link">✏️ Edit</a></div>
                <div class="ir"><div class="il">Business Type</div><div class="iv"><?= d($seller,'business_type') ?></div></div>
                <div class="ir"><div class="il">PAN Number</div><div class="iv"><?= d($seller,'pan_number') ?></div></div>
                <div class="ir"><div class="il">Tax Info</div><div class="iv"><?= d($seller,'tax_info') ?></div></div>
            </div>

            <!-- Contact & Support -->
            <div class="card">
                <div class="card-head"><div class="card-icon">📞</div><div class="card-title">Contact &amp; Support</div><a href="contact.php" class="edit-link">✏️ Edit</a></div>
                <div class="ir"><div class="il">Alt Phone</div><div class="iv"><?= d($seller,'alt_phone') ?></div></div>
                <div class="ir"><div class="il">WhatsApp</div><div class="iv"><?= d($seller,'whatsapp') ?></div></div>
                <div class="ir"><div class="il">Emergency</div><div class="iv"><?= d($seller,'emergency_contact') ?></div></div>
            </div>
        </div>

        <!-- Terms & Submit -->
        <form method="POST">
            <div class="terms-box">
                <input type="checkbox" name="agree" id="agree" value="1" required>
                <label for="agree">I confirm that all information provided above is accurate, complete, and authentic. I agree to the <a href="#">Seller Terms & Conditions</a> and <a href="#">Privacy Policy</a>. False information may result in rejection or account suspension.</label>
            </div>
            <div class="button-group">
                <a href="contact.php" class="btn btn-back">← Previous Step</a>
                <button type="submit" name="submit" class="btn btn-primary">📤 Submit for Review</button>
            </div>
        </form>
        <div class="footer-note">🔒 Your data is encrypted & securely stored  ·  seller@sportghar.com</div>

        <!-- DEBUG TABLE (visible only when ?debug=1 in URL) -->
        <?php if (isset($_GET['debug'])): ?>
        <div class="debug-table">
            <strong style="display:block; margin-bottom:10px;">🔍 Database Debug – seller_id = <?= $seller_id ?></strong>
            <table style="width:100%; border-collapse:collapse; font-size:10px;">
                <tr><th style="text-align:left; padding:2px;">Field</th><th style="text-align:left; padding:2px;">Value</th></tr>
                <?php foreach ($seller as $k => $v): ?>
                <tr><td style="padding:2px; border-top:1px solid #444;"><?= htmlspecialchars($k) ?></td><td style="padding:2px; border-top:1px solid #444;"><?= htmlspecialchars(substr((string)$v,0,150)) ?></td></tr>
                <?php endforeach; ?>
            </table>
            <p style="margin-top:10px;">💡 If many fields are empty, please go back and re‑save the data in the corresponding steps.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="lightbox" class="lightbox" onclick="closeLightbox()">
    <div class="lb-close" onclick="event.stopPropagation();closeLightbox()">×</div>
    <img id="lb-img" src="" alt="Preview">
</div>
<script>
function openLightbox(src) { document.getElementById('lb-img').src = src; document.getElementById('lightbox').classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeLightbox() { document.getElementById('lightbox').classList.remove('open'); document.body.style.overflow = ''; }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
</script>
</body>
</html>
<?php ob_end_flush(); ?>