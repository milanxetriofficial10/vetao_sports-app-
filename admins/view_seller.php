<?php
// ========== AJAX HANDLERS (must run before any output) ==========
if (isset($_GET['ajax'])) {
    require_once __DIR__ . '/../databases/db.php';
    session_start();
    
    header('Content-Type: application/json');
    $db = getDB();
    $admin_id = $_SESSION['admin_id'] ?? 1;
    
    // Helper to create tables if missing
    function ensureTables($db) {
        $db->query("CREATE TABLE IF NOT EXISTS admin_seller_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            seller_id INT NOT NULL,
            admin_id INT DEFAULT NULL,
            message TEXT NOT NULL,
            type ENUM('message','sms') DEFAULT 'message',
            is_read TINYINT(1) DEFAULT 0,
            is_seen TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_seller_id (seller_id),
            INDEX idx_created_at (created_at)
        )");
        $db->query("CREATE TABLE IF NOT EXISTS typing_status (
            id INT AUTO_INCREMENT PRIMARY KEY,
            seller_id INT NOT NULL,
            admin_id INT DEFAULT 1,
            is_typing TINYINT DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY seller_admin (seller_id, admin_id)
        )");
    }
    ensureTables($db);
    
    $seller_id = isset($_GET['seller_id']) ? (int)$_GET['seller_id'] : (isset($_POST['seller_id']) ? (int)$_POST['seller_id'] : 0);
    if (!$seller_id) { echo json_encode(['error' => 'Missing seller_id']); exit; }
    
    // Get messages
    if ($_GET['ajax'] === 'get_messages') {
        $last_id = (int)($_GET['last_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM admin_seller_messages WHERE seller_id = ? AND id > ? ORDER BY created_at ASC");
        $stmt->bind_param("ii", $seller_id, $last_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $is_admin_msg = !is_null($row['admin_id']);
            $can_edit = $is_admin_msg && $row['type'] === 'sms' && (time() - strtotime($row['created_at']) <= 300);
            $messages[] = [
                'id'             => $row['id'],
                'message'        => $row['message'],
                'is_admin'       => $is_admin_msg,
                'type'           => $row['type'],
                'created_at'     => date('d M, g:i A', strtotime($row['created_at'])),
                'is_seen'        => (bool)$row['is_seen'],
                'can_edit_delete'=> $can_edit
            ];
        }
        $stmt->close();
        $db->query("UPDATE admin_seller_messages SET is_seen = 1 WHERE seller_id = $seller_id AND admin_id IS NULL AND is_seen = 0");
        echo json_encode(['messages' => $messages]);
        exit;
    }
    
    // Send message
    if ($_GET['ajax'] === 'send_message') {
        $message = trim($_POST['message'] ?? '');
        $send_sms = isset($_POST['send_sms']);
        if (empty($message)) { echo json_encode(['error' => 'Message cannot be empty']); exit; }
        $type = $send_sms ? 'sms' : 'message';
        $stmt = $db->prepare("INSERT INTO admin_seller_messages (seller_id, admin_id, message, type, is_read, is_seen, created_at) VALUES (?, ?, ?, ?, 0, 0, NOW())");
        $stmt->bind_param("iiss", $seller_id, $admin_id, $message, $type);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message_id' => $stmt->insert_id]);
        } else {
            echo json_encode(['error' => $db->error]);
        }
        $stmt->close();
        exit;
    }
    
    // Edit message
    if ($_GET['ajax'] === 'edit_message') {
        $msg_id  = (int)($_POST['message_id'] ?? 0);
        $new_msg = trim($_POST['message'] ?? '');
        $check = $db->prepare("SELECT created_at FROM admin_seller_messages WHERE id = ? AND admin_id = ? AND type = 'sms'");
        $check->bind_param("ii", $msg_id, $admin_id);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        $check->close();
        if (!$row || (time() - strtotime($row['created_at']) > 300)) {
            echo json_encode(['error' => 'Not editable or expired']);
            exit;
        }
        $update = $db->prepare("UPDATE admin_seller_messages SET message = ? WHERE id = ?");
        $update->bind_param("si", $new_msg, $msg_id);
        $success = $update->execute();
        $update->close();
        echo json_encode(['success' => $success]);
        exit;
    }
    
    // Delete message
    if ($_GET['ajax'] === 'delete_message') {
        $msg_id = (int)($_POST['message_id'] ?? 0);
        $check = $db->prepare("SELECT created_at FROM admin_seller_messages WHERE id = ? AND admin_id = ? AND type = 'sms'");
        $check->bind_param("ii", $msg_id, $admin_id);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        $check->close();
        if (!$row || (time() - strtotime($row['created_at']) > 300)) {
            echo json_encode(['error' => 'Not deletable or expired']);
            exit;
        }
        $del = $db->prepare("DELETE FROM admin_seller_messages WHERE id = ?");
        $del->bind_param("i", $msg_id);
        $success = $del->execute();
        $del->close();
        echo json_encode(['success' => $success]);
        exit;
    }
    
    // Get typing status
    if ($_GET['ajax'] === 'get_typing') {
        $stmt = $db->prepare("SELECT is_typing, updated_at FROM typing_status WHERE seller_id = ?");
        $stmt->bind_param("i", $seller_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $is_typing = ($row && $row['is_typing'] && (time() - strtotime($row['updated_at']) <= 5));
        echo json_encode(['typing' => $is_typing]);
        exit;
    }
    
    // Update typing status
    if ($_GET['ajax'] === 'update_typing') {
        $is_typing = isset($_POST['typing']) ? (int)$_POST['typing'] : 0;
        $sql = "INSERT INTO typing_status (seller_id, admin_id, is_typing, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE is_typing = ?, updated_at = NOW()";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("iiii", $seller_id, $admin_id, $is_typing, $is_typing);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $success]);
        exit;
    }
    
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// ========== NORMAL PAGE LOAD ==========
ob_start();
require_once __DIR__ . '/../databases/db.php';
session_start();

$admin_id  = $_SESSION['admin_id'] ?? 1;
$seller_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = getDB();

// Ensure tables exist
$db->query("CREATE TABLE IF NOT EXISTS admin_seller_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    admin_id INT DEFAULT NULL,
    message TEXT NOT NULL,
    type ENUM('message','sms') DEFAULT 'message',
    is_read TINYINT(1) DEFAULT 0,
    is_seen TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_seller_id (seller_id),
    INDEX idx_created_at (created_at)
)");
$db->query("CREATE TABLE IF NOT EXISTS typing_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    admin_id INT DEFAULT 1,
    is_typing TINYINT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY seller_admin (seller_id, admin_id)
)");

// Add missing columns safely
function addColumnIfMissing($db, $table, $column, $definition) {
    $check = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($check && $check->num_rows === 0) {
        $db->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}
addColumnIfMissing($db, 'sellers', 'admin_signature', 'VARCHAR(255) DEFAULT NULL');
addColumnIfMissing($db, 'sellers', 'admin_stamp', 'VARCHAR(255) DEFAULT NULL');
addColumnIfMissing($db, 'sellers', 'admin_remarks', 'TEXT DEFAULT NULL');
addColumnIfMissing($db, 'sellers', 'shop_description', 'TEXT DEFAULT NULL');
addColumnIfMissing($db, 'sellers', 'driving_license', 'VARCHAR(255) DEFAULT NULL');
addColumnIfMissing($db, 'sellers', 'bank_name', 'VARCHAR(255) DEFAULT NULL');
addColumnIfMissing($db, 'sellers', 'bank_branch', 'VARCHAR(255) DEFAULT NULL');
addColumnIfMissing($db, 'sellers', 'bank_account_number', 'VARCHAR(255) DEFAULT NULL');

include "sidenav.php";

// Fetch seller data (all columns)
$stmt = $db->prepare("SELECT * FROM sellers WHERE id = ?");
$stmt->bind_param('i', $seller_id);
$stmt->execute();
$seller = $stmt->get_result()->fetch_assoc();
if (!$seller) die("Seller not found.");

// Handle signature & stamp upload + admin remarks + send document link
$sig_stamp_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save signature/stamp
    if (isset($_POST['save_sig_stamp'])) {
        $target_dir_sig = __DIR__ . '/../uploads/admin_signatures/';
        $target_dir_stamp = __DIR__ . '/../uploads/admin_stamps/';
        if (!is_dir($target_dir_sig)) mkdir($target_dir_sig, 0777, true);
        if (!is_dir($target_dir_stamp)) mkdir($target_dir_stamp, 0777, true);
        
        $update_fields = [];
        $update_values = [];
        $types = "";
        
        if (isset($_FILES['admin_signature']) && $_FILES['admin_signature']['error'] == 0) {
            $ext = pathinfo($_FILES['admin_signature']['name'], PATHINFO_EXTENSION);
            $sig_filename = "sig_seller_{$seller_id}_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['admin_signature']['tmp_name'], $target_dir_sig . $sig_filename)) {
                $update_fields[] = "admin_signature = ?";
                $update_values[] = $sig_filename;
                $types .= "s";
            } else {
                $sig_stamp_msg = "❌ Failed to upload signature.";
            }
        }
        if (isset($_FILES['admin_stamp']) && $_FILES['admin_stamp']['error'] == 0) {
            $ext = pathinfo($_FILES['admin_stamp']['name'], PATHINFO_EXTENSION);
            $stamp_filename = "stamp_seller_{$seller_id}_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['admin_stamp']['tmp_name'], $target_dir_stamp . $stamp_filename)) {
                $update_fields[] = "admin_stamp = ?";
                $update_values[] = $stamp_filename;
                $types .= "s";
            } else {
                $sig_stamp_msg = "❌ Failed to upload stamp.";
            }
        }
        
        if (!empty($update_fields)) {
            $sql = "UPDATE sellers SET " . implode(", ", $update_fields) . " WHERE id = ?";
            $update_values[] = $seller_id;
            $types .= "i";
            $update_stmt = $db->prepare($sql);
            if ($update_stmt) {
                $update_stmt->bind_param($types, ...$update_values);
                if ($update_stmt->execute()) {
                    $sig_stamp_msg = "✅ Signature & Stamp updated successfully!";
                } else {
                    $sig_stamp_msg = "❌ Database update failed.";
                }
                $update_stmt->close();
            } else {
                $sig_stamp_msg = "❌ Database error.";
            }
        } else {
            $sig_stamp_msg = "⚠️ No files selected.";
        }
    }
    
    // Save admin remarks
    if (isset($_POST['save_admin_remarks'])) {
        $remarks = trim($_POST['admin_remarks'] ?? '');
        $update_remarks = $db->prepare("UPDATE sellers SET admin_remarks = ? WHERE id = ?");
        $update_remarks->bind_param("si", $remarks, $seller_id);
        if ($update_remarks->execute()) {
            $sig_stamp_msg = "✅ Admin remarks saved!";
        } else {
            $sig_stamp_msg = "❌ Failed to save remarks.";
        }
        $update_remarks->close();
    }
    
    // Send signed notification
    if (isset($_POST['send_signed_notify'])) {
        $notify_msg = "📄 **Official Document Signed & Stamped by Admin**\n\nYour documents have been reviewed and digitally signed/stamped by the administration. This serves as official approval. Thank you for your cooperation.";
        $stmt2 = $db->prepare("INSERT INTO admin_seller_messages (seller_id, admin_id, message, type, is_read, is_seen, created_at) VALUES (?, ?, ?, 'message', 0, 0, NOW())");
        $stmt2->bind_param('iis', $seller_id, $admin_id, $notify_msg);
        $stmt2->execute();
        $stmt2->close();
        $sig_stamp_msg = "📨 Notification sent to seller via chat.";
    }
    
    // Send document link
    if (isset($_POST['send_document_link'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $base_url = $protocol . "://" . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
        $doc_url = $base_url . "seller_document.php?id=" . $seller_id;
        $link_message = "🎉 **Your Official Verified Document is ready** 🎉\n\n"
                      . "The administration has finalized your application with digital signature and official stamp.\n\n"
                      . "👉 **View / Download your document:** $doc_url\n\n"
                      . "You can also print this document for your records. Thank you!";
        $stmt2 = $db->prepare("INSERT INTO admin_seller_messages (seller_id, admin_id, message, type, is_read, is_seen, created_at) VALUES (?, ?, ?, 'message', 0, 0, NOW())");
        $stmt2->bind_param('iis', $seller_id, $admin_id, $link_message);
        if ($stmt2->execute()) {
            $sig_stamp_msg = "🔗 Document link sent to seller via chat.";
        } else {
            $sig_stamp_msg = "❌ Failed to send document link.";
        }
        $stmt2->close();
    }
    
    // Refresh seller data after POST
    $stmt = $db->prepare("SELECT * FROM sellers WHERE id = ?");
    $stmt->bind_param('i', $seller_id);
    $stmt->execute();
    $seller = $stmt->get_result()->fetch_assoc();
}

// Handle chat message sending from POST
$message_sent = false;
$sms_sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message_text = trim($_POST['message'] ?? '');
    $send_sms = isset($_POST['send_sms']);
    if (!empty($message_text)) {
        $type = $send_sms ? 'sms' : 'message';
        $stmt2 = $db->prepare("INSERT INTO admin_seller_messages (seller_id, admin_id, message, type, is_read, is_seen, created_at) VALUES (?, ?, ?, ?, 0, 0, NOW())");
        $stmt2->bind_param('iiss', $seller_id, $admin_id, $message_text, $type);
        if ($stmt2->execute()) {
            $message_sent = true;
            if ($send_sms) $sms_sent = true;
        }
        $stmt2->close();
    }
}

// Fetch all chat messages for this seller
$messages = [];
$msg_query = "SELECT * FROM admin_seller_messages WHERE seller_id = ? ORDER BY created_at ASC";
$stmt3 = $db->prepare($msg_query);
$stmt3->bind_param('i', $seller_id);
$stmt3->execute();
$msg_result = $stmt3->get_result();
while ($row = $msg_result->fetch_assoc()) $messages[] = $row;
$stmt3->close();

// Mark admin messages as read
$db->query("UPDATE admin_seller_messages SET is_read = 1 WHERE seller_id = $seller_id AND is_read = 0 AND admin_id IS NULL");

// Helper functions
function getStatusBadge($status) {
    switch($status) {
        case 'approved': return '<span class="badge approved">✓ Approved & Signed</span>';
        case 'rejected': return '<span class="badge rejected">✗ Rejected</span>';
        default:         return '<span class="badge pending">⏳ Pending Review</span>';
    }
}

function showDoc($value, $folder, $type = 'image') {
    if (empty($value)) return '<span style="color:#b4875f;">⚠️ Not uploaded</span>';
    $path = "../publics/uploads/{$folder}/" . htmlspecialchars($value);
    return '<img src="' . $path . '" class="doc-img" style="max-width:100%; max-height:140px; border-radius:8px; border:1px solid #DBCBB9; cursor:pointer;" onclick="openModal(this.src)">';
}

// Prepare image paths
$passport_img = !empty($seller['passport_photo']) ? "../publics/uploads/passport/" . htmlspecialchars($seller['passport_photo']) : "https://ui-avatars.com/api/?background=C0392B&color=fff&name=" . urlencode($seller['full_name']);
$signature_img = !empty($seller['admin_signature']) ? "../uploads/admin_signatures/" . htmlspecialchars($seller['admin_signature']) : "";
$stamp_img = !empty($seller['admin_stamp']) ? "../uploads/admin_stamps/" . htmlspecialchars($seller['admin_stamp']) : "";
$admin_remarks = htmlspecialchars($seller['admin_remarks'] ?? '');

// Determine registration date from created_at or fallback
$reg_date = isset($seller['created_at']) ? date('d M, Y', strtotime($seller['created_at'])) : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Official Document | Seller Verification - <?= htmlspecialchars($seller['shop_name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #E9E4DB;
            font-family: 'Inter', 'Segoe UI', 'Roboto', system-ui, serif;
            line-height: 1.5;
        }
        .main-content { margin-left: 280px; padding: 35px 40px; transition: all 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 20px; } }
        
        /* PAPER STYLE DOCUMENT CARD */
        .document-paper {
            background: #FFFDF9;
            border-radius: 4px;
            box-shadow: 0 40px 35px -12px rgba(0,0,0,0.2), 0 1px 3px rgba(0,0,0,0.05);
            position: relative;
            padding: 40px 35px;
            margin-bottom: 32px;
            border: 1px solid #DDD2C4;
        }
        .document-paper::before {
            content: "";
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            bottom: 20px;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" opacity="0.03"><path fill="%238B5A2B" d="M20,20 L80,20 L80,80 L20,80 Z M30,30 L70,30 L70,70 L30,70 Z"/><circle cx="50" cy="50" r="15"/></svg>') repeat;
            pointer-events: none;
            opacity: 0.4;
        }
        .stamp-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-8deg);
            max-width: 180px;
            max-height: 180px;
            opacity: 0.85;
            z-index: 10;
            pointer-events: none;
            mix-blend-mode: multiply;
        }
        .signature-wrapper {
            position: absolute;
            bottom: 30px;
            right: 40px;
            text-align: center;
            z-index: 10;
            pointer-events: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }
        .signature-overlay {
            max-width: 160px;
            max-height: 80px;
            object-fit: contain;
        }
        .signature-caption {
            font-size: 0.7rem;
            color: #5C3F28;
            font-weight: 600;
            background: rgba(255,253,249,0.85);
            padding: 4px 10px;
            border-radius: 24px;
        }
        @media (max-width: 640px) {
            .stamp-overlay { max-width: 120px; max-height: 120px; }
            .signature-overlay { max-width: 110px; }
            .signature-wrapper { bottom: 15px; right: 20px; }
        }
        .paper-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 25px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #E7DED3;
            position: relative;
            z-index: 2;
        }
        .passport-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        .passport-img {
            width: 110px;
            height: 130px;
            object-fit: cover;
            border: 2px solid #C0A080;
            border-radius: 6px;
            background: #F9F3EA;
            cursor: pointer;
        }
        .passport-label {
            font-size: 0.7rem;
            background: #F2E5D8;
            padding: 2px 10px;
            border-radius: 20px;
            color: #7A4C2C;
            font-weight: 500;
        }
        .shop-title-section {
            text-align: right;
            flex: 1;
        }
        .shop-title-section h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #3E2A1F;
        }
        .meta-badge {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 8px;
            flex-wrap: wrap;
        }
        .seller-since {
            font-size: 0.8rem;
            color: #7F674F;
            background: #F4EEE7;
            padding: 4px 12px;
            border-radius: 40px;
        }
        .divider-line {
            height: 2px;
            background: linear-gradient(90deg, #DCC9B4, #B28B65, #DCC9B4);
            margin: 20px 0 25px;
        }
        .info-grid-paper {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px 30px;
            margin-bottom: 35px;
            position: relative;
            z-index: 2;
        }
        .info-paper-item {
            border-bottom: 1px dashed #E2D4C6;
            padding-bottom: 8px;
        }
        .info-paper-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: 600;
            color: #AA7A50;
            letter-spacing: 0.5px;
        }
        .info-paper-value {
            font-size: 0.95rem;
            font-weight: 500;
            color: #2E241E;
            margin-top: 3px;
            word-break: break-word;
        }
        .documents-paper-section {
            margin: 25px 0 30px;
            position: relative;
            z-index: 2;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #5C3F28;
            border-left: 5px solid #C86F2C;
            padding-left: 15px;
            margin-bottom: 20px;
        }
        .doc-paper-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 28px;
            justify-content: flex-start;
            margin-bottom: 30px;
        }
        .a4-doc-card {
            background: #FEFAF2;
            border: 1px solid #DDCFBF;
            border-radius: 12px;
            width: 220px;
            padding: 14px 12px;
            text-align: center;
            box-shadow: 0 5px 12px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .a4-doc-card:hover { transform: translateY(-4px); }
        .doc-label {
            font-size: 0.7rem;
            font-weight: 700;
            background: #E9DCCE;
            display: inline-block;
            padding: 4px 14px;
            border-radius: 40px;
            margin-bottom: 12px;
        }
        .a4-doc-card img {
            max-width: 100%;
            max-height: 140px;
            border-radius: 8px;
            border: 1px solid #DBCBB9;
            cursor: pointer;
            object-fit: contain;
            background: #fffaf2;
        }
        .seller-description-box {
            background: #FEF7EF;
            border-left: 4px solid #C86F2C;
            padding: 16px 20px;
            margin: 20px 0 25px;
            border-radius: 16px;
        }
        .admin-remark-display {
            background: #F8F2EA;
            padding: 14px 20px;
            border-radius: 20px;
            margin-top: 20px;
            border-left: 4px solid #2C7A47;
        }
        .admin-upload-card {
            background: #FEFAF5;
            border-radius: 28px;
            padding: 28px 32px;
            margin: 20px 0 30px;
            border: 1px solid #e9dfd3;
        }
        .upload-form {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            align-items: flex-end;
        }
        .upload-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .upload-group label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #9B6E48;
        }
        .remarks-group {
            margin-top: 20px;
            width: 100%;
        }
        .remarks-group label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #8B5A2B;
        }
        .remarks-group textarea {
            width: 100%;
            padding: 12px 16px;
            border-radius: 24px;
            border: 1px solid #DDCFBF;
            background: #FFFDF9;
            font-family: inherit;
            font-size: 0.85rem;
            resize: vertical;
        }
        .btn-paper {
            background: #8B5A2B;
            border: none;
            padding: 8px 20px;
            border-radius: 40px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-paper:hover { background: #5C3F28; transform: translateY(-1px); }
        .btn-outline-paper {
            background: transparent;
            border: 1px solid #C59B6D;
            color: #8B5A2B;
        }
        .btn-outline-paper:hover { background: #F6EFE6; }
        .alert-paper {
            background: #EAE2D7;
            border-radius: 60px;
            padding: 10px 22px;
            font-size: 0.8rem;
            margin-bottom: 18px;
            color: #3E2A1F;
        }
        .badge {
            display: inline-block;
            padding: 5px 16px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .badge.approved { background: #D9E6D2; color: #2D6A4F; }
        .badge.rejected { background: #FCE4E0; color: #B4432E; }
        .badge.pending  { background: #FEF1DF; color: #D48C3C; }
        .action-buttons { margin-top: 20px; display: flex; gap: 15px; flex-wrap: wrap; }
        .btn-back { background: #A0866E; }
        .modal { display: none; position: fixed; z-index: 2000; left:0; top:0; width:100%; height:100%; background: rgba(0,0,0,0.9); align-items:center; justify-content:center; }
        .modal-content { max-width: 90%; max-height: 90%; border-radius: 12px; }
        .close-modal { position: absolute; top: 20px; right: 35px; color: white; font-size: 40px; cursor: pointer; }
        
        /* Floating Chat */
        .floating-chat-btn {
            position: fixed; bottom: 24px; right: 24px; width: 56px; height: 56px;
            background: linear-gradient(135deg, #C0392B, #E67E22); border-radius: 28px;
            display: flex; align-items: center; justify-content: center; cursor: pointer;
            box-shadow: 0 6px 14px rgba(0,0,0,0.25); z-index: 1000;
        }
        .floating-chat-btn:hover { transform: scale(1.05); }
        .chat-drawer {
            position: fixed; top: 0; right: -440px; width: 400px; height: 100vh;
            background: white; box-shadow: -6px 0 25px rgba(0,0,0,0.15);
            z-index: 1100; transition: right 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            display: flex; flex-direction: column;
        }
        .chat-drawer.open { right: 0; }
        @media (max-width: 480px) { .chat-drawer { width: 100%; right: -100%; } .info-grid-paper { grid-template-columns: 1fr; } .doc-paper-grid { justify-content: center; } }
        .drawer-header { padding: 18px 20px; background: #FCF8F3; border-bottom: 2px solid #E8DDD3; font-weight: 700; display: flex; justify-content: space-between; align-items: center; }
        .drawer-messages { flex:1; overflow-y: auto; padding: 18px; background: #FEFCF9; display: flex; flex-direction: column; gap: 14px; }
        .drawer-message-item { display: flex; flex-direction: column; max-width: 100%; }
        .drawer-message-item.seller { align-items: flex-start; }
        .drawer-message-item.admin { align-items: flex-end; }
        .drawer-bubble { max-width: 85%; padding: 10px 16px; border-radius: 20px; font-size: 0.85rem; word-wrap: break-word; }
        .drawer-message-item.seller .drawer-bubble { background: #F0E9E2; color: #2D241C; border-bottom-left-radius: 4px; }
        .drawer-message-item.admin .drawer-bubble { background: #C0392B; color: white; border-bottom-right-radius: 4px; }
        .message-time { font-size: 0.65rem; margin-top: 4px; opacity: 0.7; margin-left: 8px; margin-right: 8px; }
        .drawer-input { padding: 14px 18px; border-top: 1px solid #E8DDD3; background: #fffdf9; }
        .drawer-input-row { display: flex; gap: 10px; align-items: center; }
        .drawer-input-row input { flex: 1; padding: 10px 14px; border-radius: 40px; border: 1px solid #DDCFBF; font-family: inherit; }
        .btn-send-small { background: #C0392B; border: none; color: white; padding: 8px 20px; border-radius: 40px; cursor: pointer; font-weight: 600; }
        .typing-indicator { font-size: 0.7rem; color: #A77B51; padding: 4px 12px; font-style: italic; }
        .drawer-footer-options { display: flex; align-items: center; gap: 12px; margin-top: 8px; }
        .sms-check { font-size: 0.7rem; display: flex; align-items: center; gap: 6px; }
    </style>
</head>
<body>
<div class="main-content">
    <?php if ($message_sent): ?>
        <div class="alert-paper">✅ Message sent! <?= $sms_sent ? 'SMS also triggered.' : '' ?></div>
    <?php endif; ?>
    <?php if ($sig_stamp_msg): ?>
        <div class="alert-paper"><?= $sig_stamp_msg ?></div>
    <?php endif; ?>

    <!-- MAIN PAPER DOCUMENT -->
    <div class="document-paper">
        <div class="paper-header">
            <div class="passport-area">
                <img src="<?= $passport_img ?>" class="passport-img" alt="Passport Photo" onclick="openModal(this.src)">
                <span class="passport-label">OFFICIAL PASSPORT SIZE PHOTO</span>
            </div>
            <div class="shop-title-section">
                <h1><?= htmlspecialchars($seller['shop_name']) ?></h1>
                <div class="meta-badge">
                    <?= getStatusBadge($seller['status']) ?>
                    <span class="seller-since">📅 Registered: <?= $reg_date ?></span>
                </div>
            </div>
        </div>
        <div class="divider-line"></div>

        <!-- SELLER DETAILS (all fields) -->
        <div class="info-grid-paper">
            <!-- Personal -->
            <div class="info-paper-item"><div class="info-paper-label">Full Legal Name</div><div class="info-paper-value"><?= htmlspecialchars($seller['full_name']) ?></div></div>
            <div class="info-paper-item"><div class="info-paper-label">Email Address</div><div class="info-paper-value"><?= htmlspecialchars($seller['email']) ?></div></div>
            <div class="info-paper-item"><div class="info-paper-label">Phone Number</div><div class="info-paper-value"><?= htmlspecialchars($seller['phone']) ?></div></div>
            <div class="info-paper-item"><div class="info-paper-label">Alternative Phone</div><div class="info-paper-value"><?= htmlspecialchars($seller['alt_phone'] ?? '—') ?></div></div>
            <div class="info-paper-item"><div class="info-paper-label">WhatsApp Number</div><div class="info-paper-value"><?= htmlspecialchars($seller['whatsapp'] ?? '—') ?></div></div>
            <div class="info-paper-item"><div class="info-paper-label">Emergency Contact</div><div class="info-paper-value"><?= htmlspecialchars($seller['emergency_contact'] ?? '—') ?></div></div>
            
            <!-- Shop & Business -->
            <div class="info-paper-item"><div class="info-paper-label">Shop Category</div><div class="info-paper-value"><?= htmlspecialchars($seller['shop_category']) ?></div></div>
            <div class="info-paper-item"><div class="info-paper-label">Shop Address</div><div class="info-paper-value"><?= nl2br(htmlspecialchars($seller['shop_address'])) ?></div></div>
            <div class="info-paper-item"><div class="info-paper-label">Business Type</div><div class="info-paper-value"><?= htmlspecialchars($seller['business_type'] ?? '—') ?></div></div>
            <div class="info-paper-item"><div class="info-paper-label">PAN Number</div><div class="info-paper-value"><?= htmlspecialchars($seller['pan_number'] ?? '—') ?></div></div>
            <div class="info-paper-item"><div class="info-paper-label">VAT / Tax Info</div><div class="info-paper-value"><?= htmlspecialchars($seller['tax_info'] ?? '—') ?></div></div>
            
            <!-- KYC & Citizenship -->
            <div class="info-paper-item"><div class="info-paper-label">Citizenship Number</div><div class="info-paper-value"><?= htmlspecialchars($seller['citizenship_number'] ?? '—') ?></div></div>
            <div class="info-paper-item"><div class="info-paper-label">Driving License</div><div class="info-paper-value"><?= htmlspecialchars($seller['driving_license'] ?? '—') ?></div></div>
            
            <!-- Banking (detailed) -->
            <div class="info-paper-item"><div class="info-paper-label">Bank Account Holder</div><div class="info-paper-value"><?= htmlspecialchars($seller['bank_holder_name'] ?? '—') ?></div></div>
            <div class="info-paper-item"><div class="info-paper-label">Bank Name</div><div class="info-paper-value"><?= htmlspecialchars($seller['bank_name'] ?? '—') ?></div></div>
            <div class="info-paper-item"><div class="info-paper-label">Bank Branch</div><div class="info-paper-value"><?= htmlspecialchars($seller['bank_branch'] ?? '—') ?></div></div>
            <div class="info-paper-item"><div class="info-paper-label">Account Number</div><div class="info-paper-value"><?= htmlspecialchars($seller['bank_account_number'] ?? '—') ?></div></div>
            <?php if (!empty($seller['bank_account_details'])): ?>
            <div class="info-paper-item"><div class="info-paper-label">Additional Bank Info</div><div class="info-paper-value"><?= nl2br(htmlspecialchars($seller['bank_account_details'])) ?></div></div>
            <?php endif; ?>
        </div>

        <!-- ATTACHED LEGAL DOCUMENTS -->
        <div class="documents-paper-section">
            <div class="section-title">📄 Attached Legal Documents</div>
            <div class="doc-paper-grid">
                <div class="a4-doc-card"><div class="doc-label">🇳🇵 Citizenship (Front)</div><?= !empty($seller['nagarikta_front']) ? showDoc($seller['nagarikta_front'], 'nagarikta') : '<span class="not-uploaded">⚠️ Not uploaded</span>' ?></div>
                <div class="a4-doc-card"><div class="doc-label">🇳🇵 Citizenship (Back)</div><?= !empty($seller['nagarikta_back']) ? showDoc($seller['nagarikta_back'], 'nagarikta') : '<span class="not-uploaded">⚠️ Not uploaded</span>' ?></div>
                <div class="a4-doc-card"><div class="doc-label">📸 Passport Photo (Official)</div><?= !empty($seller['passport_photo']) ? showDoc($seller['passport_photo'], 'passport') : '<span class="not-uploaded">⚠️ Not uploaded</span>' ?></div>
                <div class="a4-doc-card"><div class="doc-label">🏦 Bank Cheque / Passbook</div><?= !empty($seller['bank_cheque_image']) ? showDoc($seller['bank_cheque_image'], 'cheque') : '<span class="not-uploaded">⚠️ Not uploaded</span>' ?></div>
            </div>
            
            <div class="section-title" style="margin-top: 20px;">🏪 Shop Branding</div>
            <div class="doc-paper-grid">
                <div class="a4-doc-card"><div class="doc-label">Shop Logo</div><?= !empty($seller['shop_logo']) ? showDoc(basename($seller['shop_logo']), 'shop_logo') : '<span class="not-uploaded">⚠️ Not uploaded</span>' ?></div>
                <div class="a4-doc-card"><div class="doc-label">Shop Banner</div><?= !empty($seller['shop_banner']) ? showDoc(basename($seller['shop_banner']), 'shop_banner') : '<span class="not-uploaded">⚠️ Not uploaded</span>' ?></div>
            </div>

            <!-- Shop Description -->
            <div class="seller-description-box">
                <strong>📝 Seller's Shop Description:</strong><br>
                <?= nl2br(htmlspecialchars($seller['shop_description'] ?? 'No description provided.')) ?>
            </div>
        </div>

        <!-- STAMP & SIGNATURE OVERLAYS -->
        <?php if (!empty($stamp_img)): ?>
            <img src="<?= $stamp_img ?>" class="stamp-overlay" alt="Official Stamp">
        <?php endif; ?>
        <?php if (!empty($signature_img)): ?>
            <div class="signature-wrapper">
                <img src="<?= $signature_img ?>" class="signature-overlay" alt="Admin Signature">
                <div class="signature-caption">Signature of Admin Manager</div>
            </div>
        <?php endif; ?>
        
        <!-- Admin Remarks -->
        <?php if (!empty($admin_remarks)): ?>
            <div class="admin-remark-display">
                <strong>📌 Admin's Final Note:</strong><br>
                <?= nl2br($admin_remarks) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ADMIN UPLOAD SECTION FOR SIGNATURE, STAMP & REMARKS -->
    <div class="admin-upload-card">
        <form method="POST" enctype="multipart/form-data" class="upload-form">
            <div class="upload-group">
                <label>📎 Upload Official Signature (PNG/JPG)</label>
                <input type="file" name="admin_signature" accept="image/*">
                <?php if (!empty($signature_img)): ?>
                    <div style="font-size:0.7rem;">✅ Current signature present</div>
                <?php endif; ?>
            </div>
            <div class="upload-group">
                <label>🖨️ Upload Official Stamp/Seal (PNG/JPG)</label>
                <input type="file" name="admin_stamp" accept="image/*">
                <?php if (!empty($stamp_img)): ?>
                    <div style="font-size:0.7rem;">✅ Current stamp present</div>
                <?php endif; ?>
            </div>
            <button type="submit" name="save_sig_stamp" class="btn-paper">💾 Save Signature & Stamp</button>
            <button type="submit" name="send_signed_notify" class="btn-paper btn-outline-paper">📨 Send Notification to Seller</button>
            <button type="submit" name="send_document_link" class="btn-paper" style="background:#2C7A47;">🔗 Send Document Link to Seller</button>
        </form>
        
        <!-- Admin Remarks Form -->
        <form method="POST" style="margin-top: 28px;">
            <div class="remarks-group">
                <label>✍️ Write Final Remarks (will appear on the document)</label>
                <textarea name="admin_remarks" rows="3" placeholder="Add any official note, approval message, or conditions..."><?= $admin_remarks ?></textarea>
                <button type="submit" name="save_admin_remarks" class="btn-paper" style="margin-top: 12px;">💬 Save Remarks</button>
            </div>
        </form>
    </div>

    <!-- ACTION BUTTONS -->
    <div class="action-buttons">
        <?php if ($seller['status'] == 'pending'): ?>
            <a href="approve_seller.php?id=<?= $seller['id'] ?>" class="btn-paper" style="background:#2C7A47;" onclick="return confirm('Approve this seller officially?')">✔ Approve & Finalize</a>
            <a href="reject_seller.php?id=<?= $seller['id'] ?>" class="btn-paper" style="background:#B24A34;" onclick="return confirm('Reject application?')">✘ Reject Application</a>
        <?php endif; ?>
        <a href="dashboard.php" class="btn-paper btn-back">← Back to Dashboard</a>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="modal">
    <span class="close-modal">&times;</span>
    <img class="modal-content" id="modalImg">
</div>

<!-- Floating Chat Button & Drawer -->
<div class="floating-chat-btn" onclick="toggleChat()">💬</div>
<div class="chat-drawer" id="chatDrawer">
    <div class="drawer-header">
        <span>Chat with Seller</span>
        <span style="cursor:pointer;" onclick="toggleChat()">✕</span>
    </div>
    <div class="drawer-messages" id="chatMessages"></div>
    <div class="drawer-input">
        <div class="drawer-input-row">
            <input type="text" id="chatInput" placeholder="Type a message...">
            <button class="btn-send-small" onclick="sendMessage()">Send</button>
        </div>
        <div class="drawer-footer-options">
            <label class="sms-check"><input type="checkbox" id="sendSmsCheckbox"> Also send as SMS</label>
            <div id="typingStatus" class="typing-indicator"></div>
        </div>
    </div>
</div>

<script>
    // Chat variables (unchanged)
    let lastMessageId = 0;
    let pollingInterval = null;
    let pollTypingInterval = null;
    let currentSellerId = <?= $seller_id ?>;
    let isTyping = false;
    let typingTimeout = null;
    const chatDrawer = document.getElementById('chatDrawer');
    const chatMessagesDiv = document.getElementById('chatMessages');
    const chatInput = document.getElementById('chatInput');
    const typingStatusDiv = document.getElementById('typingStatus');
    
    function toggleChat() {
        chatDrawer.classList.toggle('open');
        if (chatDrawer.classList.contains('open')) {
            startPolling();
        } else {
            stopPolling();
        }
    }
    
    function startPolling() {
        if(pollingInterval) clearInterval(pollingInterval);
        if(pollTypingInterval) clearInterval(pollTypingInterval);
        pollingInterval = setInterval(fetchMessages, 3000);
        pollTypingInterval = setInterval(fetchTypingStatus, 4000);
        fetchMessages();
    }
    
    function stopPolling() {
        if(pollingInterval) clearInterval(pollingInterval);
        if(pollTypingInterval) clearInterval(pollTypingInterval);
        pollingInterval = null; pollTypingInterval = null;
    }
    
    function fetchMessages() {
        fetch(window.location.href + '?ajax=get_messages&seller_id=' + currentSellerId + '&last_id=' + lastMessageId)
        .then(res => res.json())
        .then(data => {
            if(data.messages && data.messages.length) {
                data.messages.forEach(msg => {
                    appendMessage(msg);
                    if(msg.id > lastMessageId) lastMessageId = msg.id;
                });
                scrollToBottom();
            }
        });
    }
    
    function appendMessage(msg) {
        const div = document.createElement('div');
        div.className = 'drawer-message-item ' + (msg.is_admin ? 'admin' : 'seller');
        div.innerHTML = `<div class="drawer-bubble">${escapeHtml(msg.message)}</div>
                         <div class="message-time">${msg.created_at}</div>`;
        chatMessagesDiv.appendChild(div);
    }
    
    function scrollToBottom() {
        chatMessagesDiv.scrollTop = chatMessagesDiv.scrollHeight;
    }
    
    function sendMessage() {
        let message = chatInput.value.trim();
        if(!message) return;
        const sendSms = document.getElementById('sendSmsCheckbox').checked ? 1 : 0;
        const formData = new FormData();
        formData.append('message', message);
        formData.append('send_sms', sendSms);
        fetch(window.location.href + '?ajax=send_message&seller_id=' + currentSellerId, {
            method: 'POST',
            body: formData
        }).then(res => res.json()).then(data => {
            if(data.success) {
                chatInput.value = '';
                fetchMessages();
            }
        });
        updateTypingStatus(false);
    }
    
    function fetchTypingStatus() {
        fetch(window.location.href + '?ajax=get_typing&seller_id=' + currentSellerId)
        .then(res => res.json())
        .then(data => {
            if(data.typing) typingStatusDiv.innerText = 'Seller is typing...';
            else typingStatusDiv.innerText = '';
        });
    }
    
    function updateTypingStatus(typing) {
        const formData = new FormData();
        formData.append('typing', typing ? 1 : 0);
        fetch(window.location.href + '?ajax=update_typing&seller_id=' + currentSellerId, { method: 'POST', body: formData });
    }
    
    chatInput.addEventListener('input', function() {
        if(!isTyping) {
            isTyping = true;
            updateTypingStatus(true);
        }
        if(typingTimeout) clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
            isTyping = false;
            updateTypingStatus(false);
        }, 1500);
    });
    
    function escapeHtml(str) {
        return str.replace(/[&<>]/g, function(m) {
            if(m === '&') return '&amp;';
            if(m === '<') return '&lt;';
            if(m === '>') return '&gt;';
            return m;
        });
    }
    
    // Image modal
    function openModal(src) {
        document.getElementById('imageModal').style.display = 'flex';
        document.getElementById('modalImg').src = src;
    }
    document.querySelector('.close-modal').onclick = () => document.getElementById('imageModal').style.display = 'none';
    window.onclick = e => { if (e.target === document.getElementById('imageModal')) document.getElementById('imageModal').style.display = 'none'; };
</script>
</body>
</html>
<?php ob_end_flush(); ?>