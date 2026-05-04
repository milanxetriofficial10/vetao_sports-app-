<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../databases/db.php';

$db = getDB();

// ===== COLUMN SAFETY CHECK =====
function columnExists($db, $table, $column) {
    $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

if (!columnExists($db, 'admin_messages', 'file_path')) {
    $db->query("ALTER TABLE admin_messages ADD file_path VARCHAR(500) DEFAULT NULL");
}
if (!columnExists($db, 'admin_messages', 'file_type')) {
    $db->query("ALTER TABLE admin_messages ADD file_type VARCHAR(100) DEFAULT NULL");
}
if (!columnExists($db, 'sellers', 'locked')) {
    $db->query("ALTER TABLE sellers ADD locked TINYINT(1) DEFAULT 0");
}
if (!columnExists($db, 'sellers', 'profile_image')) {
    $db->query("ALTER TABLE sellers ADD profile_image VARCHAR(255) DEFAULT NULL");
}
$db->query("CREATE TABLE IF NOT EXISTS admin_typing (
    seller_id INT NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    typing TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (seller_id, is_admin)
)");

// ─── Global settings table for seller rules PDF ───────────────────────────────
$db->query("CREATE TABLE IF NOT EXISTS admin_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// ─── Upload directories ──────────────────────────────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/chat_uploads/');
define('RULES_UPLOAD_DIR', __DIR__ . '/uploads/rules/');
define('PROFILE_UPLOAD_DIR', __DIR__ . '/../uploads/profile/');

function filePathToUrl(string $absPath): string {
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
    $abs     = str_replace('\\', '/', $absPath);
    $root    = str_replace('\\', '/', $docRoot);
    if (strpos($abs, $root) === 0) {
        return str_replace('//', '/', '/' . ltrim(substr($abs, strlen($root)), '/'));
    }
    return $absPath;
}

// ========== AJAX HANDLERS ==========
if (!empty($_GET['ajax'])) {

    function sendJSON($data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    while (ob_get_level()) ob_end_clean();

    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        sendJSON(['error' => "PHP Error: $errstr in $errfile on line $errline"]);
    });

    $db        = getDB();
    $ajax      = $_GET['ajax'];
    $seller_id = isset($_GET['seller_id'])  ? (int)$_GET['seller_id']
               : (isset($_POST['seller_id']) ? (int)$_POST['seller_id'] : 0);

    // ---------- DELETE SELLER (with all related data) ----------
    if ($ajax === 'delete_seller' && $seller_id) {
        // Get seller profile image path
        $stmt = $db->prepare("SELECT profile_image FROM sellers WHERE id = ?");
        $stmt->bind_param('i', $seller_id);
        $stmt->execute();
        $seller = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Delete all messages and their attached files
        $stmt = $db->prepare("SELECT file_path FROM admin_messages WHERE seller_id = ?");
        $stmt->bind_param('i', $seller_id);
        $stmt->execute();
        $files = $stmt->get_result();
        while ($row = $files->fetch_assoc()) {
            if (!empty($row['file_path'])) {
                $abs = $row['file_path'];
                if (!file_exists($abs)) {
                    $abs = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($row['file_path'], '/');
                }
                if (file_exists($abs)) @unlink($abs);
            }
        }
        $stmt->close();
        $db->query("DELETE FROM admin_messages WHERE seller_id = $seller_id");

        // Delete typing records
        $db->query("DELETE FROM admin_typing WHERE seller_id = $seller_id");

        // Delete profile image if exists
        if ($seller && !empty($seller['profile_image'])) {
            $imgAbs = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($seller['profile_image'], '/');
            if (file_exists($imgAbs)) @unlink($imgAbs);
        }

        // Finally delete seller
        $stmt = $db->prepare("DELETE FROM sellers WHERE id = ?");
        $stmt->bind_param('i', $seller_id);
        $stmt->execute();
        $deleted = $stmt->affected_rows > 0;
        $stmt->close();

        sendJSON(['success' => $deleted]);
    }

    // ---------- TOGGLE LOCK ----------
    if ($ajax === 'toggle_lock' && $seller_id) {
        $stmt = $db->prepare("SELECT locked FROM sellers WHERE id = ?");
        $stmt->bind_param('i', $seller_id);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($current) {
            $newLock = $current['locked'] ? 0 : 1;
            $upd = $db->prepare("UPDATE sellers SET locked = ? WHERE id = ?");
            $upd->bind_param('ii', $newLock, $seller_id);
            if ($upd->execute()) {
                sendJSON(['success' => true, 'new_status' => $newLock,
                          'message' => $newLock ? 'Seller locked' : 'Seller unlocked']);
            } else {
                sendJSON(['success' => false, 'error' => 'Database update failed']);
            }
        } else {
            sendJSON(['success' => false, 'error' => 'Seller not found']);
        }
    }

    // ---------- GET MESSAGES ----------
    if ($ajax === 'get_messages' && $seller_id) {
        $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
        $stmt = $db->prepare("
            SELECT id, seller_id, message, file_path, file_type, is_admin, type, is_seen, created_at,
                   (TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 5 AND is_admin = 1) AS can_edit_delete
            FROM admin_messages
            WHERE seller_id = ? AND id > ?
            ORDER BY id ASC
        ");
        $stmt->bind_param('ii', $seller_id, $last_id);
        $stmt->execute();
        $res  = $stmt->get_result();
        $msgs = [];
        while ($m = $res->fetch_assoc()) {
            $m['created_at']      = date('d M, g:i A', strtotime($m['created_at']));
            $m['can_edit_delete'] = (bool)$m['can_edit_delete'];
            $m['is_admin']        = (bool)$m['is_admin'];
            $m['is_seen']         = (bool)$m['is_seen'];
            if (!empty($m['file_path'])) {
                $m['file_url']  = filePathToUrl($m['file_path']);
                $m['file_name'] = basename($m['file_path']);
            } else {
                $m['file_url']  = null;
                $m['file_name'] = null;
            }
            $msgs[] = $m;
        }
        $stmt->close();
        $db->query("UPDATE admin_messages SET is_seen=1
                    WHERE seller_id=$seller_id AND is_admin=0 AND is_seen=0");
        sendJSON(['messages' => $msgs]);
    }

    // ---------- GET SEEN UPDATES ----------
    if ($ajax === 'get_seen_updates' && $seller_id) {
        $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
        $stmt = $db->prepare("SELECT id FROM admin_messages
                              WHERE seller_id=? AND is_admin=1 AND id<=? AND is_seen=1");
        $stmt->bind_param('ii', $seller_id, $last_id);
        $stmt->execute();
        $res  = $stmt->get_result();
        $seen = [];
        while ($r = $res->fetch_assoc()) $seen[] = (int)$r['id'];
        $stmt->close();
        sendJSON(['seen_ids' => $seen]);
    }

    // ---------- GET SELLER LIST DATA ----------
    if ($ajax === 'get_seller_list_data') {
        $res    = $db->query("SELECT seller_id, COUNT(*) AS cnt
                              FROM admin_messages WHERE is_admin=0 AND is_seen=0
                              GROUP BY seller_id");
        $counts = [];
        while ($r = $res->fetch_assoc()) $counts[(int)$r['seller_id']] = (int)$r['cnt'];

        $lastMsgs = [];
        $msgRes   = $db->query("
            SELECT m1.seller_id, m1.message, m1.created_at, m1.is_admin, m1.type,
                   s.shop_name, s.full_name, s.locked, s.profile_image
            FROM admin_messages m1
            INNER JOIN (SELECT seller_id, MAX(id) AS max_id FROM admin_messages GROUP BY seller_id) m2
                    ON m1.id = m2.max_id
            LEFT JOIN sellers s ON m1.seller_id = s.id
            ORDER BY m1.created_at DESC
        ");
        while ($lm = $msgRes->fetch_assoc()) {
            $preview = $lm['message'];
            if (mb_strlen($preview) > 50) $preview = mb_substr($preview, 0, 47) . '...';
            if ($lm['type'] === 'sms') $preview = '📱 ' . $preview;
            $lastMsgs[(int)$lm['seller_id']] = [
                'message'  => $preview,
                'time'     => date('d M, g:i A', strtotime($lm['created_at'])),
                'sender'   => $lm['is_admin'] ? 'Admin' : ($lm['shop_name'] ?? $lm['full_name']),
                'is_admin' => (bool)$lm['is_admin'],
                'type'     => $lm['type'],
                'locked'   => (bool)($lm['locked'] ?? false),
            ];
        }

        $sellersData = [];
        $sellerRes   = $db->query("SELECT id, full_name, shop_name, locked, profile_image FROM sellers ORDER BY id DESC");
        while ($s = $sellerRes->fetch_assoc()) {
            $parts    = explode(' ', trim($s['full_name']));
            $initials = strtoupper(substr($parts[0], 0, 1));
            if (count($parts) > 1) $initials .= strtoupper(substr(end($parts), 0, 1));
            $sellersData[] = [
                'id'            => (int)$s['id'],
                'full_name'     => $s['full_name'],
                'shop_name'     => $s['shop_name'],
                'initials'      => $initials,
                'locked'        => (bool)$s['locked'],
                'profile_image' => $s['profile_image'] ? filePathToUrl($s['profile_image']) : null,
            ];
        }
        sendJSON(['counts' => $counts, 'last_msgs' => $lastMsgs, 'sellers' => $sellersData]);
    }

    // ---------- SEND MESSAGE (with file upload) - FIXED: no chmod() ----------
    if ($ajax === 'send_message' && $seller_id) {
        $message  = trim($_POST['message'] ?? '');
        $send_sms = !empty($_POST['send_sms']);
        $file_abs_path = null;
        $file_url      = null;
        $file_type     = null;

        // Ensure upload directory exists
        if (!is_dir(UPLOAD_DIR)) {
            if (!mkdir(UPLOAD_DIR, 0755, true)) {
                sendJSON(['error' => 'Failed to create upload directory: ' . UPLOAD_DIR]);
            }
        }
        // Check if writable (do NOT attempt chmod – may be forbidden on shared hosting)
        if (!is_writable(UPLOAD_DIR)) {
            sendJSON(['error' => 'Upload folder is not writable. Please manually set permissions to 0755 for: ' . UPLOAD_DIR]);
        }

        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $original_name = basename($_FILES['attachment']['name']);
            $ext           = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed       = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','txt','zip','mp4','webm'];
            if (!in_array($ext, $allowed)) {
                sendJSON(['error' => 'File type not allowed.']);
            }
            if ($_FILES['attachment']['size'] > 5 * 1024 * 1024) {
                sendJSON(['error' => 'File too large. Max 5 MB.']);
            }
            $safe_name = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $target    = UPLOAD_DIR . $safe_name;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
                $file_abs_path = $target;
                $file_url      = filePathToUrl($target);
                $file_type     = $_FILES['attachment']['type'];
            } else {
                sendJSON(['error' => 'Failed to move uploaded file. Check disk space or permissions.']);
            }
        } elseif (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension',
            ];
            sendJSON(['error' => $upload_errors[$_FILES['attachment']['error']] ?? 'Unknown upload error']);
        }

        if (empty($message) && !$file_abs_path) {
            sendJSON(['error' => 'Empty message and no file']);
        }

        $type = $send_sms ? 'sms' : 'message';
        $stmt = $db->prepare("INSERT INTO admin_messages
                              (seller_id, message, file_path, file_type, is_admin, type, is_seen)
                              VALUES (?, ?, ?, ?, 1, ?, 0)");
        $stmt->bind_param('issss', $seller_id, $message, $file_abs_path, $file_type, $type);
        if ($stmt->execute()) {
            $new_id = $db->insert_id;
            $stmt->close();
            sendJSON([
                'success'    => true,
                'message_id' => $new_id,
                'file_url'   => $file_url,
                'file_name'  => $file_abs_path ? basename($file_abs_path) : null,
            ]);
        } else {
            $err = $db->error;
            $stmt->close();
            sendJSON(['success' => false, 'error' => 'Database error: ' . $err]);
        }
    }

    // ---------- EDIT MESSAGE ----------
    if ($ajax === 'edit_message') {
        $msg_id  = (int)($_POST['message_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        if (!$msg_id || !$message) sendJSON(['success' => false, 'error' => 'Invalid data']);
        $stmt = $db->prepare("UPDATE admin_messages SET message=?
                              WHERE id=? AND is_admin=1
                              AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 5");
        $stmt->bind_param('si', $message, $msg_id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        sendJSON(['success' => $ok, 'error' => $ok ? null : 'Edit time limit (5 minutes) exceeded']);
    }

    // ---------- DELETE MESSAGE ----------
    if ($ajax === 'delete_message') {
        $msg_id = (int)($_POST['message_id'] ?? 0);
        if (!$msg_id) sendJSON(['success' => false, 'error' => 'Invalid message ID']);

        $stmt = $db->prepare("SELECT file_path FROM admin_messages WHERE id=? AND is_admin=1");
        $stmt->bind_param('i', $msg_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $db->prepare("DELETE FROM admin_messages WHERE id=? AND is_admin=1");
        $stmt->bind_param('i', $msg_id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();

        if ($ok && $row && !empty($row['file_path'])) {
            $abs = $row['file_path'];
            if (!file_exists($abs)) {
                $abs = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($row['file_path'], '/');
            }
            if (file_exists($abs)) @unlink($abs);
        }
        sendJSON(['success' => $ok]);
    }

    // ---------- TYPING INDICATORS ----------
    if ($ajax === 'update_typing') {
        $is_typing = !empty($_POST['typing']) ? 1 : 0;
        $db->query("INSERT INTO admin_typing (seller_id, is_admin, typing, updated_at)
                    VALUES ($seller_id, 1, $is_typing, NOW())
                    ON DUPLICATE KEY UPDATE typing=VALUES(typing), updated_at=NOW()");
        sendJSON(['success' => true]);
    }
    if ($ajax === 'get_typing' && $seller_id) {
        $res    = $db->query("SELECT typing, updated_at FROM admin_typing
                              WHERE seller_id=$seller_id AND is_admin=0");
        $row    = $res ? $res->fetch_assoc() : null;
        $typing = $row && $row['typing'] && strtotime($row['updated_at']) > time() - 5;
        sendJSON(['typing' => $typing]);
    }

    sendJSON(['error' => 'Invalid AJAX action']);
    exit;
}
// ========== END AJAX HANDLERS ==========

// ========== HANDLE PDF UPLOAD / DELETE (Global Seller Rules) ==========
$rules_pdf_url   = null;
$rules_pdf_error = null;

if (!is_dir(RULES_UPLOAD_DIR)) {
    mkdir(RULES_UPLOAD_DIR, 0755, true);
}

if (isset($_POST['delete_rules_pdf'])) {
    $stmt = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'seller_rules_pdf'");
    $stmt->execute();
    $oldPath = $stmt->get_result()->fetch_assoc();
    if ($oldPath && !empty($oldPath['setting_value'])) {
        $absOld = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($oldPath['setting_value'], '/');
        if (file_exists($absOld)) @unlink($absOld);
    }
    $db->query("DELETE FROM admin_settings WHERE setting_key = 'seller_rules_pdf'");
    $rules_pdf_error = "PDF removed successfully.";
}

if (isset($_FILES['rules_pdf']) && $_FILES['rules_pdf']['error'] === UPLOAD_ERR_OK) {
    $original = $_FILES['rules_pdf']['name'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        $rules_pdf_error = "Only PDF files are allowed.";
    } elseif ($_FILES['rules_pdf']['size'] > 5 * 1024 * 1024) {
        $rules_pdf_error = "File too large. Max 5 MB.";
    } else {
        $stmt = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'seller_rules_pdf'");
        $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc();
        if ($old && !empty($old['setting_value'])) {
            $absOld = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($old['setting_value'], '/');
            if (file_exists($absOld)) @unlink($absOld);
        }

        $safe_name = 'seller_rules_' . time() . '.pdf';
        $target_abs = RULES_UPLOAD_DIR . $safe_name;
        if (move_uploaded_file($_FILES['rules_pdf']['tmp_name'], $target_abs)) {
            $public_url = filePathToUrl($target_abs);
            $stmt = $db->prepare("INSERT INTO admin_settings (setting_key, setting_value)
                                  VALUES ('seller_rules_pdf', ?)
                                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->bind_param('s', $public_url);
            $stmt->execute();
            $rules_pdf_error = "PDF uploaded successfully.";
        } else {
            $rules_pdf_error = "Failed to save PDF file. Check folder permissions.";
        }
    }
}

$stmt = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'seller_rules_pdf'");
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$currentRulesPdfUrl = $row ? $row['setting_value'] : null;
$stmt->close();

// ========== NORMAL PAGE LOAD (HTML) ==========
include 'sidenav.php';
$db = getDB();

$sellers  = $db->query("SELECT id, full_name, email, shop_name, status, locked, created_at, profile_image
                        FROM sellers ORDER BY id DESC");
$all_rows = [];
while ($r = $sellers->fetch_assoc()) $all_rows[] = $r;

$total    = count($all_rows);
$approved = count(array_filter($all_rows, fn($r) => $r['status'] === 'approved'));
$pending  = count(array_filter($all_rows, fn($r) => $r['status'] === 'pending'));
$rejected = count(array_filter($all_rows, fn($r) => $r['status'] === 'rejected'));

function getInitials($name) {
    $parts = explode(' ', trim($name));
    $i = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) $i .= strtoupper(substr(end($parts), 0, 1));
    return $i;
}
function getAvatarHtml($row) {
    if (!empty($row['profile_image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($row['profile_image'], '/'))) {
        $imgUrl = filePathToUrl($row['profile_image']);
        return "<img src='".htmlspecialchars($imgUrl)."' class='seller-av-img' alt='avatar' onerror=\"this.onerror=null; this.parentElement.innerHTML='<div class=\"seller-av\">".getInitials($row['full_name'])."</div>'\">";
    } else {
        return "<div class='seller-av'>".getInitials($row['full_name'])."</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<title>Admin Panel | BazaarNepal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
/* (keep all CSS exactly as before – unchanged) */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --primary: #C0392B;
    --primary-darker: #962d22;
    --primary-light: #fdece9;
    --primary-glow: rgba(192,57,43,0.2);
    --ink: #1e1a17;
    --ink-soft: #6b4e3e;
    --ink-lighter: #b8a18e;
    --bg: #fef7f2;
    --surface: #ffffff;
    --border: #f0e2d6;
    --border-light: #f9f0e8;
    --green: #1f8a4c;
    --green-bg: #ebf9f0;
    --amber: #c97d0e;
    --amber-bg: #fff3e6;
    --red-bg: #fef0ed;
    --shadow-sm: 0 8px 20px rgba(0,0,0,0.02), 0 2px 6px rgba(0,0,0,0.03);
    --shadow-md: 0 12px 28px rgba(0,0,0,0.05), 0 0 0 1px rgba(0,0,0,0.01);
    --shadow-lg: 0 25px 40px -12px rgba(0,0,0,0.15);
    --sidebar-w: 260px;
    --top-h: 64px;
    --radius-sm: 10px;
    --radius-md: 16px;
    --radius-lg: 24px;
    --transition: all 0.25s cubic-bezier(0.2, 0, 0, 1);
}

body {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
    line-height: 1.4;
}

.main {
    margin-left: var(--sidebar-w);
    margin-top: var(--top-h);
    padding: 32px 32px 48px;
    min-height: calc(100vh - var(--top-h));
    transition: margin-left 0.3s ease;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.stat {
    background: var(--surface);
    border-radius: var(--radius-lg);
    padding: 22px 20px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
    transition: var(--transition);
    backdrop-filter: blur(2px);
    cursor: default;
}
.stat:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
    border-color: #ffe4d6;
}
.stat::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    transition: height 0.2s ease;
}
.stat:hover::before {
    height: 5px;
}
.stat.s-total::before    { background: linear-gradient(90deg, #c11313, #e34d4d); }
.stat.s-approved::before { background: linear-gradient(90deg, var(--green), #3cad6e); }
.stat.s-pending::before  { background: linear-gradient(90deg, var(--amber), #f3a45b); }
.stat.s-rejected::before { background: linear-gradient(90deg, var(--primary), #e27363); }
.stat-num {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 34px;
    font-weight: 800;
    line-height: 1.1;
    color: var(--ink);
    letter-spacing: -0.02em;
}
.stat-lbl {
    font-size: 13px;
    font-weight: 500;
    color: var(--ink-soft);
    margin-top: 8px;
    letter-spacing: 0.01em;
}
.stat-icon {
    position: absolute;
    top: 20px;
    right: 20px;
    font-size: 32px;
    opacity: 0.12;
    transition: opacity 0.2s, transform 0.2s;
}
.stat:hover .stat-icon {
    opacity: 0.2;
    transform: scale(1.03);
}

.rules-card {
    background: var(--surface);
    backdrop-filter: blur(4px);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 16px 24px;
    margin-bottom: 32px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    transition: var(--transition);
    box-shadow: var(--shadow-sm);
}
.rules-card:hover {
    box-shadow: var(--shadow-md);
    border-color: #ffe0d2;
}
.rules-info {
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
}
.rules-label {
    font-weight: 700;
    font-size: 14px;
    background: var(--primary-light);
    padding: 6px 16px;
    border-radius: 60px;
    color: var(--primary);
    backdrop-filter: blur(2px);
    letter-spacing: -0.2px;
}
.rules-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #f2f4f9;
    padding: 6px 16px;
    border-radius: 40px;
    text-decoration: none;
    color: #1f6392;
    font-size: 13px;
    font-weight: 500;
    transition: 0.2s;
}
.rules-link:hover {
    background: #e3e8f1;
    transform: scale(0.98);
}
.rules-actions {
    display: flex;
    gap: 12px;
}
.rules-upload-form {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.custom-file-upload {
    background: var(--bg);
    border: 1px solid var(--border);
    padding: 8px 18px;
    border-radius: 40px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: 0.2s;
}
.custom-file-upload:hover {
    background: #f0e3da;
    border-color: #dacbbc;
}
.btn-small {
    background: var(--primary);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 40px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.btn-small:hover {
    background: var(--primary-darker);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(192,57,43,0.2);
}
.btn-outline-small {
    background: transparent;
    border: 1px solid var(--primary);
    color: var(--primary);
    box-shadow: none;
}
.btn-outline-small:hover {
    background: var(--primary-light);
    transform: translateY(-1px);
}
.rules-message {
    font-size: 12px;
    padding: 5px 14px;
    border-radius: 60px;
    background: var(--green-bg);
    color: var(--green);
    font-weight: 500;
}
.rules-error {
    background: var(--red-bg);
    color: var(--primary);
}

.table-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
    transition: box-shadow 0.2s;
}
.table-card:hover {
    box-shadow: var(--shadow-md);
}
.table-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    background: rgba(255,255,255,0.5);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.table-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 18px;
    font-weight: 700;
    letter-spacing: -0.3px;
    color: var(--ink);
}
.table-title small {
    font-weight: 400;
    font-size: 12px;
    background: var(--bg);
    padding: 2px 10px;
    border-radius: 50px;
    margin-left: 8px;
    color: var(--ink-soft);
}
.filter-tabs {
    display: flex;
    gap: 6px;
    background: var(--bg);
    border: 1px solid var(--border-light);
    border-radius: 48px;
    padding: 4px;
}
.filter-tab {
    padding: 6px 18px;
    border-radius: 40px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    background: transparent;
    color: var(--ink-soft);
    transition: 0.2s;
}
.filter-tab.active {
    background: white;
    color: var(--primary);
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid var(--border);
}
.table-wrap {
    overflow-x: auto;
    width: 100%;
    scroll-behavior: smooth;
}
table {
      width: 1500px;
    border-collapse: collapse;
    min-width: 780px;
}
thead th {
    background: #fcf8f4;
    padding: 16px 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--ink-soft);
    border-bottom: 1px solid var(--border);
}
tbody tr {
    border-bottom: 1px solid var(--border-light);
    transition: 0.15s ease;
    cursor: pointer;
}
tbody tr:last-child {
    border-bottom: none;
}
tbody tr:hover {
    background: rgba(253, 236, 233, 0.4);
    transform: scale(1.002);
}
td {
    padding: 16px 20px;
    font-size: 14px;
    vertical-align: middle;
}
.seller-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
.seller-av, .seller-av-img {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    background: linear-gradient(145deg, #fff0ea, #ffe2d8);
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 14px;
    color: var(--primary);
    transition: 0.2s;
    flex-shrink: 0;
}
.seller-av-img {
    object-fit: cover;
}
tbody tr:hover .seller-av,
tbody tr:hover .seller-av-img {
    transform: scale(1.02);
    border-color: var(--primary-light);
}
.seller-name {
    font-weight: 700;
    font-size: 14px;
}
.seller-email {
    font-size: 12px;
    color: var(--ink-soft);
}
.badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 40px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
    width: fit-content;
    white-space: nowrap;
}
.badge.approved { background: var(--green-bg); color: var(--green); }
.badge.pending  { background: var(--amber-bg); color: var(--amber); }
.badge.rejected { background: var(--red-bg); color: var(--primary); }
.badge.locked   { background: #efe4dc; color: #8b5a2e; }
.badge.unlocked { background: #e2f3e9; color: #1e6b3b; }
.actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 40px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid transparent;
}
.btn:hover {
    transform: translateY(-2px);
    filter: brightness(0.96);
}
.btn-view { background: #ecf3fa; color: #1f6392; border-color: #d3e2f2; }
.btn-approve { background: var(--green-bg); color: var(--green); border-color: #c0e5cf; }
.btn-reject { background: var(--red-bg); color: var(--primary); border-color: #f2cdc4; }
.btn-chat { background: var(--primary-light); color: var(--primary); border-color: #fadbd4; }
.btn-lock { background: #efe4dc; color: #7b4a2b; border-color: #ddcdbe; }
.btn-lock.locked { background: #29211c; color: #f7d44a; border-color: #6a4c36; }
.btn-delete { background: #fee7e7; color: #bc3b2c; border-color: #f7cfc9; }
.btn-delete:hover { background: #fbd6d6; color: #a13022; }
.date-cell {
    font-size: 12px;
    color: var(--ink-soft);
    white-space: nowrap;
}

.floating-chat-btn {
    position: fixed;
    bottom: 26px;
    right: 28px;
    width: 58px;
    height: 58px;
    background: linear-gradient(145deg, #C0392B, #D64A2F);
    border-radius: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(192,57,43,0.4);
    z-index: 1000;
    transition: all 0.25s cubic-bezier(0.2,0.9,0.4,1.1);
}
.floating-chat-btn:hover {
    transform: scale(1.08);
    box-shadow: 0 8px 28px rgba(192,57,43,0.5);
}
.floating-chat-btn svg {
    width: 28px;
    height: 28px;
    fill: white;
}
.floating-chat-btn .fcb-badge {
    position: absolute;
    top: -6px;
    right: -4px;
    background: #2c2a27;
    color: white;
    border-radius: 30px;
    min-width: 22px;
    height: 22px;
    font-size: 11px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
    padding: 0 5px;
}
.floating-chat-btn .tooltip {
    position: absolute;
    right: 70px;
    background: #1e1a17;
    color: white;
    padding: 6px 14px;
    border-radius: 50px;
    font-size: 12px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s;
    font-weight: 500;
}
.floating-chat-btn:hover .tooltip {
    opacity: 0.9;
}

.chat-drawer {
    position: fixed;
    top: 0;
    right: -440px;
    width: 440px;
    max-width: 92vw;
    height: 100vh;
    background: white;
    box-shadow: -8px 0 32px rgba(0,0,0,0.08);
    z-index: 1100;
    transition: right 0.3s cubic-bezier(0.2,0.9,0.4,1.1);
    display: flex;
    flex-direction: column;
    border-radius: 24px 0 0 24px;
    overflow: hidden;
}
.chat-drawer.open {
    right: 0;
}
.drawer-header {
    padding: 18px 22px;
    background: #fffaf5;
    border-bottom: 1px solid #f0e4da;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.drawer-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.back-btn {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: var(--ink-soft);
    padding: 4px 10px;
    border-radius: 32px;
    transition: 0.2s;
}
.back-btn:hover {
    background: #efe2d8;
}
.drawer-header h3 { font-size: 1.1rem; font-weight: 700; }
.drawer-header small { font-size: 0.7rem; opacity: 0.7; }
.close-drawer {
    background: none;
    border: none;
    font-size: 1.6rem;
    cursor: pointer;
    color: #9c7c68;
    transition: 0.1s;
    line-height: 1;
}
.panel {
    display: none;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
}
.panel.active { display: flex; }
.drawer-search {
    padding: 12px 18px;
    border-bottom: 1px solid #f0e4da;
}
.drawer-search input {
    width: 100%;
    padding: 10px 16px;
    border: 1px solid #e9dad0;
    border-radius: 60px;
    font-size: 0.85rem;
    background: white;
    transition: 0.2s;
}
.drawer-search input:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px var(--primary-glow);
}
.seller-list {
    flex: 1;
    overflow-y: auto;
}
.seller-list-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    border-bottom: 1px solid #f5ece4;
    cursor: pointer;
    transition: 0.15s;
}
.seller-list-item:hover {
    background: #fef6f0;
}
.sli-av {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: linear-gradient(145deg, #ffe6df, #ffdad0);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 14px;
    color: var(--primary);
}
.sli-info {
    flex: 1;
    min-width: 0;
}
.sli-shop {
    font-weight: 800;
    font-size: 0.9rem;
}
.sli-name {
    font-size: 0.7rem;
    color: var(--ink-soft);
}
.sli-preview {
    font-size: 0.7rem;
    color: #8f7664;
    margin-top: 4px;
    display: flex;
    justify-content: space-between;
    gap: 6px;
}
.sli-preview-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.sli-unread {
    background: var(--primary);
    color: white;
    border-radius: 30px;
    min-width: 22px;
    height: 22px;
    font-size: 10px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
}
.lock-icon-small {
    font-size: 0.65rem;
    margin-left: 6px;
    background: #efe1d6;
    padding: 2px 6px;
    border-radius: 20px;
}
.drawer-messages {
    flex: 1;
    overflow-y: auto;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    background: #fffbf8;
}
.drawer-message-item {
    display: flex;
    flex-direction: column;
    animation: fadeSlideUp 0.2s ease;
}
@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.drawer-message-item.seller { align-items: flex-start; }
.drawer-message-item.admin  { align-items: flex-end; }
.drawer-bubble {
    max-width: 80%;
    padding: 10px 16px;
    border-radius: 24px;
    font-size: 0.85rem;
    line-height: 1.45;
    word-break: break-word;
    transition: 0.1s;
}
.drawer-message-item.seller .drawer-bubble {
    background: #F0E8E0;
    color: #271f19;
    border-bottom-left-radius: 6px;
}
.drawer-message-item.admin .drawer-bubble {
    background: var(--primary);
    color: white;
    border-bottom-right-radius: 6px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.drawer-message-item.seller .drawer-bubble.sms-bubble {
    background: #eadff5;
    color: #3a2a48;
}
.drawer-message-item.admin .drawer-bubble.sms-bubble {
    background: #874e9e;
}
.file-attachment {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(0,0,0,0.08);
    padding: 6px 12px;
    border-radius: 40px;
    margin-top: 6px;
    font-size: 0.7rem;
    text-decoration: none;
    transition: 0.1s;
}
.file-attachment:hover { background: rgba(0,0,0,0.12); }
.attach-img {
    max-width: 160px;
    max-height: 140px;
    border-radius: 12px;
    margin-top: 6px;
    cursor: pointer;
    border: 1px solid rgba(0,0,0,0.1);
}
.drawer-meta {
    font-size: 0.6rem;
    margin-top: 5px;
    color: #b49782;
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.sms-tag {
    background: #e8e2f0;
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 0.6rem;
    font-weight: 600;
}
.seen-tag {
    color: #2c9f5e;
    font-weight: 500;
}
.drawer-typing {
    padding: 8px 18px;
    font-size: 0.7rem;
    font-style: italic;
    color: #c2a690;
    border-top: 1px solid #f2e4da;
    background: white;
    animation: pulse 1.2s infinite;
}
@keyframes pulse {
    0% { opacity: 0.6; }
    50% { opacity: 1; }
    100% { opacity: 0.6; }
}
.drawer-input {
    padding: 14px 18px;
    border-top: 1px solid #f0e2d6;
    background: white;
}
.drawer-input-row {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}
.input-group {
    flex: 1;
    display: flex;
    gap: 8px;
    align-items: flex-end;
}
.drawer-input textarea {
    flex: 1;
    border: 1px solid #e2d3c8;
    border-radius: 28px;
    padding: 10px 16px;
    font-size: 0.85rem;
    resize: none;
    font-family: inherit;
    transition: 0.2s;
    background: white;
}
.drawer-input textarea:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 2px var(--primary-glow);
}
.attach-label {
    background: #f0d7cb;
    width: 38px;
    height: 38px;
    border-radius: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 18px;
    transition: 0.2s;
}
.attach-label:hover {
    background: #e1cbbc;
    transform: scale(0.96);
}
.btn-send-small {
    background: var(--primary);
    border: none;
    color: white;
    padding: 9px 20px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.8rem;
    cursor: pointer;
    transition: 0.15s;
}
.btn-send-small:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.drawer-sms-check {
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.7rem;
    color: #8a6e5c;
}
.sms-mode-bar {
    display: none;
    margin-top: 8px;
    font-size: 0.7rem;
    background: #f0e9e4;
    padding: 6px 12px;
    border-radius: 28px;
}
.sms-mode-bar.show { display: block; }
.chat-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.25);
    backdrop-filter: blur(2px);
    z-index: 1050;
    transition: 0.2s;
}
.chat-overlay.show { display: block; }
#lightbox {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.85);
    z-index: 2000;
    align-items: center;
    justify-content: center;
}
#lightbox.show { display: flex; }
#lightbox img {
    max-width: 85vw;
    max-height: 85vh;
    border-radius: 24px;
    box-shadow: 0 18px 40px rgba(0,0,0,0.4);
}
#lightbox-close {
    position: fixed;
    top: 24px;
    right: 28px;
    font-size: 2.2rem;
    color: white;
    cursor: pointer;
}

@media (max-width: 800px) {
    .main { margin-left: 0; padding: 20px; }
    .stats-row { gap: 12px; }
    .table-header { flex-direction: column; align-items: stretch; }
    .filter-tabs { justify-content: center; }
}
@media (max-width: 540px) {
    .stat-num { font-size: 28px; }
    .btn { padding: 4px 10px; font-size: 10px; }
}
</style>
</head>
<body>

<main class="main">
    <div class="stats-row">
        <div class="stat s-total">   <div class="stat-icon"><i class="fas fa-store"></i></div><div class="stat-num"><?= $total ?></div><div class="stat-lbl">Total Sellers</div></div>
        <div class="stat s-approved"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-num"><?= $approved ?></div><div class="stat-lbl">Approved</div></div>
        <div class="stat s-pending"> <div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-num"><?= $pending ?></div><div class="stat-lbl">Pending Review</div></div>
        <div class="stat s-rejected"><div class="stat-icon"><i class="fas fa-times-circle"></i></div><div class="stat-num"><?= $rejected ?></div><div class="stat-lbl">Rejected</div></div>
    </div>

    <div class="rules-card">
        <div class="rules-info">
            <span class="rules-label"><i class="fas fa-book-open"></i> Seller Guidelines PDF</span>
            <?php if ($currentRulesPdfUrl): ?>
                <a href="<?= htmlspecialchars($currentRulesPdfUrl) ?>" target="_blank" class="rules-link">
                    <i class="fas fa-file-pdf"></i> View current PDF
                </a>
            <?php else: ?>
                <span class="rules-link" style="background:#f5ede6;"><i class="fas fa-exclamation-triangle"></i> No PDF uploaded</span>
            <?php endif; ?>
        </div>
        <div class="rules-actions">
            <form method="POST" enctype="multipart/form-data" class="rules-upload-form" id="rulesUploadForm">
                <label class="custom-file-upload">
                    <i class="fas fa-upload"></i> <input type="file" name="rules_pdf" accept="application/pdf" style="display:none;" onchange="this.form.submit()"> Choose PDF
                </label>
                <button type="submit" name="upload_rules_pdf" class="btn-small"><i class="fas fa-cloud-upload-alt"></i> Upload</button>
                <?php if ($currentRulesPdfUrl): ?>
                    <button type="submit" name="delete_rules_pdf" class="btn-small btn-outline-small" onclick="return confirm('Remove current PDF?');"><i class="fas fa-trash-alt"></i> Remove</button>
                <?php endif; ?>
            </form>
            <?php if ($rules_pdf_error): ?>
                <div class="rules-message <?= strpos($rules_pdf_error, 'success') !== false ? '' : 'rules-error' ?>">
                    <?= htmlspecialchars($rules_pdf_error) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-card">
        <div class="table-header">
            <div class="table-title"><i class="fas fa-users"></i> Seller Applications <small><?= $total ?> total</small></div>
            <div class="filter-tabs">
                <button class="filter-tab active" onclick="filterStatus('all',this)">All</button>
                <button class="filter-tab" onclick="filterStatus('pending',this)">Pending</button>
                <button class="filter-tab" onclick="filterStatus('approved',this)">Approved</button>
                <button class="filter-tab" onclick="filterStatus('rejected',this)">Rejected</button>
            </div>
        </div>
        <div class="table-wrap">
            <table id="sellersTable">
                <thead><tr><th>ID</th><th>Seller</th><th>Shop</th><th>Status</th><th>Lock</th><th>Actions</th><th>Registered</th></tr></thead>
                <tbody>
                <?php if (empty($all_rows)): ?>
                    <tr class="empty-row"><td colspan="7">No sellers found.</td></tr>
                <?php else: foreach ($all_rows as $row):
                    $locked   = (bool)$row['locked'];
                    $date     = date('d M Y', strtotime($row['created_at']));
                    $avatarHtml = getAvatarHtml($row);
                ?>
                <tr data-status="<?= htmlspecialchars($row['status']) ?>" data-seller-id="<?= $row['id'] ?>">
                    <td style="color:var(--ink-soft);font-weight:500;"><?= $row['id'] ?></td>
                    <td><div class="seller-cell"><?= $avatarHtml ?><div><div class="seller-name"><?= htmlspecialchars($row['full_name']) ?></div><div class="seller-email"><?= htmlspecialchars($row['email']) ?></div></div></div></td>
                    <td><strong><?= htmlspecialchars($row['shop_name']) ?></strong></td>
                    <td><span class="badge <?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                    <td><span class="badge <?= $locked ? 'locked' : 'unlocked' ?>" id="lockBadge_<?= $row['id'] ?>"><?= $locked ? '🔒 Locked' : '🔓 Unlocked' ?></span></td>
                    <td><div class="actions"><a href="view_seller.php?id=<?= $row['id'] ?>" class="btn btn-view"><i class="fas fa-eye"></i> View</a><?php if ($row['status'] === 'pending'): ?><a href="approve_seller.php?id=<?= $row['id'] ?>" class="btn btn-approve" onclick="return confirm('Approve this seller?')"><i class="fas fa-check"></i> Approve</a><a href="reject_seller.php?id=<?= $row['id'] ?>" class="btn btn-reject"  onclick="return confirm('Reject this seller?')"><i class="fas fa-times"></i> Reject</a><?php endif; ?><button class="btn btn-chat" onclick="event.stopPropagation(); openChatForSeller(<?= $row['id'] ?>)"><i class="fas fa-comment-dots"></i> Chat</button><button class="btn btn-lock <?= $locked ? 'locked' : '' ?>" onclick="event.stopPropagation(); toggleLock(<?= $row['id'] ?>, this)"><?= $locked ? '<i class="fas fa-unlock-alt"></i> Unlock' : '<i class="fas fa-lock"></i> Lock' ?></button><button class="btn btn-delete" onclick="event.stopPropagation(); deleteSeller(<?= $row['id'] ?>, this)"><i class="fas fa-trash-alt"></i> Delete</button></div></td>
                    <td class="date-cell"><i class="far fa-calendar-alt"></i> <?= $date ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Lightbox -->
<div id="lightbox" onclick="closeLightbox()">
    <span id="lightbox-close" onclick="closeLightbox()">✕</span>
    <img id="lightbox-img" src="" alt="attachment">
</div>

<div class="floating-chat-btn" id="floatingChatBtn" onclick="openDrawerList()">
    <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4V4c0-1.1-.9-2-2-2z"/></svg>
    <div class="tooltip">Messages</div>
</div>
<div class="chat-overlay" id="chatOverlay" onclick="closeDrawer()"></div>

<div class="chat-drawer" id="chatDrawer">
    <div class="panel active" id="panelList">
        <div class="drawer-header"><div class="drawer-header-left"><h3><i class="far fa-comments"></i> Messages</h3></div><button class="close-drawer" onclick="closeDrawer()">✕</button></div>
        <div class="drawer-search"><input type="text" id="sellerSearchInput" placeholder="🔍 Search sellers..." oninput="filterSellerList(this.value)"></div>
        <div class="seller-list" id="sellerListContainer"><div class="seller-list-empty"><i class="fas fa-spinner fa-pulse"></i> Loading...</div></div>
    </div>
    <div class="panel" id="panelChat">
        <div class="drawer-header"><div class="drawer-header-left"><button class="back-btn" onclick="goBackToList()"><i class="fas fa-arrow-left"></i> Back</button><div><h3 id="chatHeaderName">Shop Name</h3><small id="chatHeaderSub">Seller name</small></div></div><button class="close-drawer" onclick="closeDrawer()">✕</button></div>
        <div class="drawer-messages" id="drawerMessages"><div class="empty-chat"><i class="far fa-comment-dots"></i> Select a seller to start chatting</div></div>
        <div class="drawer-typing" id="drawerTyping" style="display:none;"><i class="fas fa-ellipsis-h"></i> Seller is typing...</div>
        <div class="drawer-input">
            <div class="drawer-input-row"><div class="input-group"><textarea id="drawerMessageText" rows="1" placeholder="Write a message… (Enter to send)"></textarea><label for="fileInput" class="attach-label"><i class="fas fa-paperclip"></i></label><input type="file" id="fileInput" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,application/zip,video/mp4,video/webm" style="display:none;"></div><button class="btn-send-small" id="sendBtn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i> Send</button></div>
            <div id="filePreview" class="attachments-preview" style="display:none;"><span id="fileName"></span><button onclick="clearAttachment()" title="Remove file"><i class="fas fa-times-circle"></i></button></div>
            <label class="drawer-sms-check"><input type="checkbox" id="drawerSmsCheck"> <i class="fas fa-sms"></i> Also send as SMS</label>
            <div class="sms-mode-bar" id="smsModeBar"><i class="fas fa-mobile-alt"></i> This message will also be sent as SMS</div>
        </div>
    </div>
</div>

<script>
let ALL_SELLERS = <?php
    $js = [];
    foreach ($all_rows as $r) {
        $js[] = [
            'id'            => (int)$r['id'],
            'full_name'     => $r['full_name'],
            'shop_name'     => $r['shop_name'],
            'initials'      => getInitials($r['full_name']),
            'locked'        => (bool)$r['locked'],
            'profile_image' => !empty($r['profile_image']) ? filePathToUrl($r['profile_image']) : null,
        ];
    }
    echo json_encode($js);
?>;

const BASE_URL = location.pathname;

let currentSellerId = null;
let lastMessageId   = 0;
let pollInterval    = null;
let typingPollInt   = null;
let seenPollInt     = null;
let listPollInt     = null;
let typingTimeout   = null;
let unreadCounts    = {};
let lastMessages    = {};
let selectedFile    = null;

const drawer         = document.getElementById('chatDrawer');
const overlay        = document.getElementById('chatOverlay');
const panelList      = document.getElementById('panelList');
const panelChat      = document.getElementById('panelChat');
const sellerListEl   = document.getElementById('sellerListContainer');
const msgContainer   = document.getElementById('drawerMessages');
const typingDiv      = document.getElementById('drawerTyping');
const msgInput       = document.getElementById('drawerMessageText');
const smsCheck       = document.getElementById('drawerSmsCheck');
const sendBtn        = document.getElementById('sendBtn');
const chatHeaderName = document.getElementById('chatHeaderName');
const chatHeaderSub  = document.getElementById('chatHeaderSub');
const searchInput    = document.getElementById('sellerSearchInput');
const smsModeBar     = document.getElementById('smsModeBar');
const fileInput      = document.getElementById('fileInput');
const filePreview    = document.getElementById('filePreview');
const fileNameSpan   = document.getElementById('fileName');

function esc(s) { return (s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function scrollBottom() { msgContainer.scrollTop = msgContainer.scrollHeight; }
function isImage(name) { return /\.(jpe?g|png|gif|webp)$/i.test(name||''); }

window.openLightbox = function(src) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox').classList.add('show');
};
window.closeLightbox = function() { document.getElementById('lightbox').classList.remove('show'); };

function toggleSmsBar(on) { smsModeBar.classList.toggle('show', on); }
smsCheck.addEventListener('change', e => toggleSmsBar(e.target.checked));

function clearAttachment() { selectedFile = null; fileInput.value = ''; filePreview.style.display = 'none'; }
fileInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) { clearAttachment(); return; }
    if (file.size > 5*1024*1024) { alert('File too large. Max 5 MB.'); fileInput.value = ''; return; }
    selectedFile = file;
    fileNameSpan.textContent = file.name;
    filePreview.style.display = 'flex';
});

function showPanel(name) { panelList.classList.toggle('active', name==='list'); panelChat.classList.toggle('active', name==='chat'); }
function openDrawerList() { drawer.classList.add('open'); overlay.classList.add('show'); showPanel('list'); fetchSellerListData(); startListPolling(); }
function closeDrawer() { drawer.classList.remove('open'); overlay.classList.remove('show'); stopChatPolling(); stopListPolling(); if(currentSellerId) updateTyping(false); clearAttachment(); }
function goBackToList() { stopChatPolling(); if(currentSellerId) updateTyping(false); currentSellerId=null; lastMessageId=0; showPanel('list'); fetchSellerListData(); startListPolling(); }

function startListPolling() { stopListPolling(); listPollInt = setInterval(fetchSellerListData, 6000); }
function stopListPolling()  { clearInterval(listPollInt); }
function startChatPolling() { stopChatPolling(); pollInterval=setInterval(fetchMessages,3000); typingPollInt=setInterval(checkTyping,2500); seenPollInt=setInterval(fetchSeenUpdates,4000); }
function stopChatPolling() { clearInterval(pollInterval); clearInterval(typingPollInt); clearInterval(seenPollInt); }

async function fetchSellerListData() {
    try {
        const res=await fetch(`${BASE_URL}?ajax=get_seller_list_data`); const data=await res.json();
        unreadCounts=data.counts||{}; lastMessages=data.last_msgs||{};
        if(data.sellers&&data.sellers.length) ALL_SELLERS=data.sellers;
        renderSellerList(searchInput.value);
        updateTableBadgesAndLocks();
        updateFloatingBadge();
    } catch(e) { console.warn(e); }
}
function updateFloatingBadge() {
    const total=Object.values(unreadCounts).reduce((a,b)=>a+b,0); const floatBtn=document.getElementById('floatingChatBtn');
    let badge=floatBtn.querySelector('.fcb-badge');
    if(total>0){ if(!badge){ badge=document.createElement('div'); badge.className='fcb-badge'; floatBtn.appendChild(badge); } badge.textContent=total>99?'99+':total; }
    else if(badge) badge.remove();
}
function updateTableBadgesAndLocks() {
    for(const s of ALL_SELLERS){
        const lb=document.getElementById('lockBadge_'+s.id); if(lb){ lb.className=`badge ${s.locked?'locked':'unlocked'}`; lb.innerHTML=s.locked?'🔒 Locked':'🔓 Unlocked'; }
        const lockBtn=document.querySelector(`#sellersTable tr[data-seller-id="${s.id}"] .btn-lock`);
        if(lockBtn){ lockBtn.innerHTML=s.locked?'<i class="fas fa-unlock-alt"></i> Unlock':'<i class="fas fa-lock"></i> Lock'; lockBtn.classList.toggle('locked',s.locked); }
    }
}
function renderSellerList(query){
    const q=(query||'').toLowerCase().trim(); let filtered=ALL_SELLERS.filter(s=>!q||(s.shop_name+' '+s.full_name).toLowerCase().includes(q));
    filtered.sort((a,b)=>{ let ua=unreadCounts[a.id]||0,ub=unreadCounts[b.id]||0; if(ub!==ua)return ub-ua; const la=lastMessages[a.id],lb=lastMessages[b.id]; if(la&&!lb)return -1; if(!la&&lb)return 1; return a.shop_name.localeCompare(b.shop_name); });
    if(!filtered.length){ sellerListEl.innerHTML='<div class="seller-list-empty"><i class="far fa-frown"></i> No sellers found.</div>'; return; }
    let html='';
    for(const s of filtered){
        const cnt=unreadCounts[s.id]||0; const last=lastMessages[s.id];
        const preview=last?`<div class="sli-preview"><span class="sli-preview-text">${last.is_admin?'🛡️ You:':'🏪 '+esc(last.sender)+':'} ${esc(last.message)}</span><span class="sli-preview-time">${esc(last.time)}</span></div>`:'<div class="sli-preview"><span class="sli-preview-text" style="opacity:.6;">✨ No messages yet</span></div>';
        const lockIcon=s.locked?'<span class="lock-icon-small"><i class="fas fa-lock"></i></span>':'';
        const avatarImg = s.profile_image ? `<img src="${esc(s.profile_image)}" class="sli-av" style="object-fit:cover;" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\"sli-av\">${esc(s.initials)}</div>'">` : `<div class="sli-av">${esc(s.initials)}</div>`;
        html+=`<div class="seller-list-item" onclick="openChatForSeller(${s.id})">${avatarImg}<div class="sli-info"><div class="sli-shop">${esc(s.shop_name)}${lockIcon}</div><div class="sli-name">${esc(s.full_name)}</div>${preview}</div>${cnt?`<div class="sli-unread">${cnt>99?'99+':cnt}</div>`:''}</div>`;
    }
    sellerListEl.innerHTML=html;
}
function filterSellerList(q){ renderSellerList(q); }

window.openChatForSeller=async function(sellerId){
    const s=ALL_SELLERS.find(x=>x.id===sellerId); if(!s)return;
    currentSellerId=sellerId; lastMessageId=0;
    chatHeaderName.textContent=s.shop_name; chatHeaderSub.textContent=s.full_name;
    msgContainer.innerHTML='<div class="empty-chat"><i class="fas fa-spinner fa-pulse"></i> Loading messages…</div>';
    drawer.classList.add('open'); overlay.classList.add('show'); showPanel('chat');
    stopListPolling(); stopChatPolling();
    await fetchMessages();
    if(!msgContainer.querySelector('.drawer-message-item')) msgContainer.innerHTML='<div class="empty-chat"><i class="far fa-comment"></i> No messages yet. Start the conversation!</div>';
    startChatPolling(); msgInput.focus();
};

async function fetchMessages(){
    if(!currentSellerId) return;
    try{
        const res=await fetch(`${BASE_URL}?ajax=get_messages&seller_id=${currentSellerId}&last_id=${lastMessageId}`);
        const data=await res.json();
        if(!data.messages || !data.messages.length) return;
        if(lastMessageId===0){ const old=msgContainer.querySelector('.empty-chat'); if(old)old.remove(); }
        let appended=false;
        for(const msg of data.messages){
            if(!msgContainer.querySelector(`[data-msg-id="${msg.id}"]`)){
                appendMessage(msg);
                if(msg.id>lastMessageId) lastMessageId=msg.id;
                appended=true;
            }
        }
        if(appended) scrollBottom();
    } catch(e){ console.warn(e); }
}
async function fetchSeenUpdates(){
    if(!currentSellerId||!lastMessageId) return;
    try{ const res=await fetch(`${BASE_URL}?ajax=get_seen_updates&seller_id=${currentSellerId}&last_id=${lastMessageId}`); const data=await res.json();
        if(data.seen_ids) for(const id of data.seen_ids){ const el=msgContainer.querySelector(`[data-msg-id="${id}"]`); if(el&&!el.querySelector('.seen-tag')){ const meta=el.querySelector('.drawer-meta'); if(meta){ const sp=document.createElement('span'); sp.className='seen-tag'; sp.innerHTML='<i class="fas fa-check-double"></i> Seen'; meta.appendChild(sp); } } }
    } catch(e){}
}
function appendMessage(msg){
    const old=msgContainer.querySelector('.empty-chat'); if(old)old.remove();
    const isSms=msg.type==='sms'; const isAdmin=msg.is_admin; const s=ALL_SELLERS.find(x=>x.id===currentSellerId);
    const wrap=document.createElement('div'); wrap.className=`drawer-message-item ${isAdmin?'admin':'seller'}`; wrap.dataset.msgId=msg.id;
    const bubble=document.createElement('div'); bubble.className='drawer-bubble'+(isSms?' sms-bubble':'');
    let innerHtml='';
    if(msg.message) innerHtml+=`<div>${esc(msg.message).replace(/\n/g,'<br>')}</div>`;
    if(msg.file_url&&msg.file_name){
        if(isImage(msg.file_name)) innerHtml+=`<div><img class="attach-img" src="${esc(msg.file_url)}" alt="${esc(msg.file_name)}" onclick="openLightbox('${esc(msg.file_url)}')" onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';"><a href="${esc(msg.file_url)}" target="_blank" class="file-attachment" style="display:none;"><i class="fas fa-paperclip"></i> ${esc(msg.file_name)}</a></div>`;
        else innerHtml+=`<div><a href="${esc(msg.file_url)}" target="_blank" download class="file-attachment"><i class="fas fa-download"></i> ${esc(msg.file_name)}</a></div>`;
    }
    bubble.innerHTML=innerHtml;
    const meta=document.createElement('div'); meta.className='drawer-meta';
    let metaHtml=`<span>${isAdmin?'🛡️ Admin':'🏪 '+esc(s?s.shop_name:'Seller')}</span><span><i class="far fa-clock"></i> ${msg.created_at}</span>`;
    if(isSms) metaHtml+=`<span class="sms-tag"><i class="fas fa-sms"></i> SMS</span>`;
    if(isAdmin&&msg.is_seen) metaHtml+=`<span class="seen-tag"><i class="fas fa-check-double"></i> Seen</span>`;
    if(isAdmin&&msg.can_edit_delete) metaHtml+=`<div style="position:relative;display:inline-block;margin-left:8px;"><button class="menu-btn" onclick="toggleMenu(event,this)"><i class="fas fa-ellipsis-v"></i></button><div class="dropdown-menu"><a href="#" class="edit-msg" data-id="${msg.id}" data-text="${esc(msg.message||'')}"><i class="fas fa-edit"></i> Edit</a><a href="#" class="delete-msg" data-id="${msg.id}"><i class="fas fa-trash"></i> Delete</a></div></div>`;
    meta.innerHTML=metaHtml;
    wrap.appendChild(bubble); wrap.appendChild(meta); msgContainer.appendChild(wrap);
    bindEditDelete();
}
async function sendMessage(){
    const message=msgInput.value.trim(); if(!message&&!selectedFile){ alert('Please enter a message or select a file.'); return; } if(!currentSellerId) return;
    sendBtn.disabled=true; sendBtn.innerHTML='<i class="fas fa-spinner fa-pulse"></i> Sending';
    const fd=new FormData(); fd.append('seller_id',currentSellerId); fd.append('message',message); if(smsCheck.checked) fd.append('send_sms','1'); if(selectedFile) fd.append('attachment',selectedFile);
    try{
        const res=await fetch(`${BASE_URL}?ajax=send_message`,{method:'POST',body:fd}); const data=await res.json();
        if(data.success){
            const newMsg={id:data.message_id,message:message,file_url:data.file_url||null,file_name:data.file_name||null,is_admin:true,type:smsCheck.checked?'sms':'message',created_at:new Date().toLocaleString('en-US',{day:'numeric',month:'short',hour:'numeric',minute:'2-digit',hour12:true}),is_seen:false,can_edit_delete:true};
            appendMessage(newMsg); scrollBottom(); if(data.message_id>lastMessageId) lastMessageId=data.message_id;
            msgInput.value=''; msgInput.style.height=''; smsCheck.checked=false; toggleSmsBar(false); clearAttachment(); fetchSellerListData();
        } else alert('Error: '+(data.error||'Unknown error'));
    } catch(e){ alert('Network error'); } finally{ sendBtn.disabled=false; sendBtn.innerHTML='<i class="fas fa-paper-plane"></i> Send'; }
}
msgInput.addEventListener('keydown',e=>{ if(e.key==='Enter'&&!e.shiftKey){ e.preventDefault(); sendMessage(); } });
msgInput.addEventListener('input',function(){ this.style.height='auto'; this.style.height=Math.min(this.scrollHeight,100)+'px'; updateTyping(true); clearTimeout(typingTimeout); typingTimeout=setTimeout(()=>updateTyping(false),2000); });
async function checkTyping(){ if(!currentSellerId) return; try{ const res=await fetch(`${BASE_URL}?ajax=get_typing&seller_id=${currentSellerId}`); const data=await res.json(); typingDiv.style.display=data.typing?'block':'none'; } catch(e){} }
async function updateTyping(isTyping){ if(!currentSellerId) return; const fd=new FormData(); fd.append('seller_id',currentSellerId); fd.append('typing',isTyping?1:0); await fetch(`${BASE_URL}?ajax=update_typing`,{method:'POST',body:fd}); }
async function editMessage(msgId,newText){ const fd=new FormData(); fd.append('message_id',msgId); fd.append('message',newText); try{ const res=await fetch(`${BASE_URL}?ajax=edit_message`,{method:'POST',body:fd}); const data=await res.json(); if(data.success){ const bubble=msgContainer.querySelector(`[data-msg-id="${msgId}"] .drawer-bubble`); if(bubble){ const textDiv=bubble.querySelector('div:first-child'); if(textDiv) textDiv.innerHTML=esc(newText).replace(/\n/g,'<br>'); } const editLink=msgContainer.querySelector(`[data-msg-id="${msgId}"] .edit-msg`); if(editLink) editLink.dataset.text=newText; fetchSellerListData(); } else alert(data.error||'Cannot edit – 5 minute limit'); } catch(e){ alert('Network error'); } }
async function deleteMessage(msgId){ if(!confirm('Delete this message permanently?')) return; const fd=new FormData(); fd.append('message_id',msgId); try{ const res=await fetch(`${BASE_URL}?ajax=delete_message`,{method:'POST',body:fd}); const data=await res.json(); if(data.success){ msgContainer.querySelector(`[data-msg-id="${msgId}"]`)?.remove(); if(!msgContainer.querySelector('.drawer-message-item')) msgContainer.innerHTML='<div class="empty-chat"><i class="far fa-comment"></i> No messages yet.</div>'; fetchSellerListData(); } else alert('Could not delete.'); } catch(e){ alert('Network error'); } }
function bindEditDelete(){ document.querySelectorAll('.edit-msg').forEach(a=>{ a.onclick=function(e){ e.preventDefault(); const newText=prompt('Edit message:',this.dataset.text); if(newText!==null&&newText.trim()&&newText!==this.dataset.text) editMessage(this.dataset.id,newText.trim()); }; }); document.querySelectorAll('.delete-msg').forEach(a=>{ a.onclick=function(e){ e.preventDefault(); deleteMessage(this.dataset.id); }; }); }
window.toggleMenu=function(e,btn){ e.stopPropagation(); document.querySelectorAll('.dropdown-menu.show').forEach(m=>{ if(m!==btn.nextElementSibling) m.classList.remove('show'); }); btn.nextElementSibling.classList.toggle('show'); };
document.addEventListener('click',()=>{ document.querySelectorAll('.dropdown-menu.show').forEach(m=>m.classList.remove('show')); });
window.toggleLock=async function(sellerId,buttonEl){ const originalText=buttonEl.innerHTML; buttonEl.disabled=true; buttonEl.innerHTML='<i class="fas fa-spinner fa-pulse"></i>'; try{ const fd=new FormData(); fd.append('seller_id',sellerId); const res=await fetch(`${BASE_URL}?ajax=toggle_lock`,{method:'POST',body:fd}); const data=await res.json(); if(data.success){ const idx=ALL_SELLERS.findIndex(s=>s.id===sellerId); if(idx!==-1) ALL_SELLERS[idx].locked=data.new_status===1; updateTableBadgesAndLocks(); if(drawer.classList.contains('open')) renderSellerList(searchInput.value); alert(data.message); } else alert('Failed: '+(data.error||'Unknown error')); } catch(e){ alert('Network error'); } finally{ buttonEl.disabled=false; buttonEl.innerHTML=originalText; } };
window.deleteSeller=async function(sellerId, buttonEl){
    if(!confirm('⚠️ Delete seller permanently? All chats, files and data will be removed.')) return;
    const originalText=buttonEl.innerHTML;
    buttonEl.disabled=true; buttonEl.innerHTML='<i class="fas fa-spinner fa-pulse"></i>';
    try{
        const fd=new FormData(); fd.append('seller_id',sellerId);
        const res=await fetch(`${BASE_URL}?ajax=delete_seller`,{method:'POST',body:fd});
        const data=await res.json();
        if(data.success){
            // Remove from ALL_SELLERS and refresh table
            const idx=ALL_SELLERS.findIndex(s=>s.id===sellerId);
            if(idx!==-1) ALL_SELLERS.splice(idx,1);
            fetchSellerListData();
            // Remove row from table
            const row=document.querySelector(`#sellersTable tr[data-seller-id="${sellerId}"]`);
            if(row) row.remove();
            // Update stats
            const totalCell=document.querySelector('.stat.s-total .stat-num');
            if(totalCell) totalCell.textContent=ALL_SELLERS.length;
            const approved=ALL_SELLERS.filter(s=>document.querySelector(`#sellersTable tr[data-seller-id="${s.id}"] .badge.approved`)).length;
            const pending=ALL_SELLERS.filter(s=>document.querySelector(`#sellersTable tr[data-seller-id="${s.id}"] .badge.pending`)).length;
            const rejected=ALL_SELLERS.filter(s=>document.querySelector(`#sellersTable tr[data-seller-id="${s.id}"] .badge.rejected`)).length;
            document.querySelector('.stat.s-approved .stat-num').textContent=approved;
            document.querySelector('.stat.s-pending .stat-num').textContent=pending;
            document.querySelector('.stat.s-rejected .stat-num').textContent=rejected;
            alert('Seller deleted successfully.');
        } else alert('Failed to delete seller.');
    } catch(e){ alert('Network error'); }
    finally{ buttonEl.disabled=false; buttonEl.innerHTML=originalText; }
};
document.querySelectorAll('#sellersTable tbody tr').forEach(row=>{ row.addEventListener('click',function(e){ if(e.target.closest('.actions')) return; const id=this.dataset.sellerId; if(id) openChatForSeller(parseInt(id)); }); });
function filterStatus(status,btn){ document.querySelectorAll('.filter-tab').forEach(t=>t.classList.remove('active')); btn.classList.add('active'); document.querySelectorAll('#sellersTable tbody tr[data-status]').forEach(row=>{ row.style.display=(status==='all'||row.dataset.status===status)?'':'none'; }); }
fetchSellerListData(); setInterval(fetchSellerListData,12000);
</script>
</body>
</html>