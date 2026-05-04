<?php
// ========== AJAX HANDLERS (must run before any output) ==========
if (isset($_GET['ajax'])) {
    require_once __DIR__ . '/../databases/db.php';
    session_start();
    
    header('Content-Type: application/json');
    $db = getDB();
    $admin_id = $_SESSION['admin_id'] ?? 1;
    
    // Helper to create tables and add missing columns
    function ensureTables($db) {
        // Messages table (includes is_seen, is_read and admin_read)
        $db->query("CREATE TABLE IF NOT EXISTS admin_seller_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            seller_id INT NOT NULL,
            admin_id INT DEFAULT NULL,
            message TEXT NOT NULL,
            type ENUM('message','sms') DEFAULT 'message',
            is_read TINYINT(1) DEFAULT 0,
            is_seen TINYINT(1) DEFAULT 0,
            admin_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_seller_id (seller_id),
            INDEX idx_created_at (created_at)
        )");
        
        // Add admin_read column if missing (for older installations)
        $db->query("ALTER TABLE admin_seller_messages ADD COLUMN IF NOT EXISTS admin_read TINYINT(1) DEFAULT 0");
        
        // Typing status table
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
    
    // Get seller_id from GET or POST
    $seller_id = isset($_GET['seller_id']) ? (int)$_GET['seller_id'] : (isset($_POST['seller_id']) ? (int)$_POST['seller_id'] : 0);
    if (!$seller_id) {
        echo json_encode(['error' => 'Missing seller_id']);
        exit;
    }
    
    // 1. Get messages (polling)
    if ($_GET['ajax'] === 'get_messages') {
        $last_id = (int)($_GET['last_id'] ?? 0);
        $sql = "SELECT * FROM admin_seller_messages WHERE seller_id = ? AND id > ? ORDER BY created_at ASC";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            echo json_encode(['error' => $db->error]);
            exit;
        }
        $stmt->bind_param("ii", $seller_id, $last_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $is_admin_msg = !is_null($row['admin_id']);
            $is_admin_sms = ($is_admin_msg && $row['type'] === 'sms');
            // 3 minutes = 180 seconds for edit/delete
            $can_edit = $is_admin_sms && (time() - strtotime($row['created_at']) <= 180);
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
        // Mark seller messages as seen (admin has seen them)
        $db->query("UPDATE admin_seller_messages SET is_seen = 1 WHERE seller_id = $seller_id AND admin_id IS NULL AND is_seen = 0");
        echo json_encode(['messages' => $messages]);
        exit;
    }
    
    // 2. Send message
    if ($_GET['ajax'] === 'send_message') {
        $message = trim($_POST['message'] ?? '');
        $send_sms = isset($_POST['send_sms']);
        if (empty($message)) {
            echo json_encode(['error' => 'Message cannot be empty']);
            exit;
        }
        $type = $send_sms ? 'sms' : 'message';
        
        // For SMS, set admin_read = 0 (unread for admin notification)
        $admin_read = ($type === 'sms') ? 0 : 1;  // messages are "read" by admin automatically unless SMS
        
        $sql = "INSERT INTO admin_seller_messages (seller_id, admin_id, message, type, is_read, is_seen, admin_read, created_at) 
                VALUES (?, ?, ?, ?, 0, 0, ?, NOW())";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            echo json_encode(['error' => 'DB prepare: ' . $db->error]);
            exit;
        }
        $stmt->bind_param("iissi", $seller_id, $admin_id, $message, $type, $admin_read);
        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            $stmt->close();
            // Return message data for optimistic UI update
            echo json_encode([
                'success' => true,
                'message_id' => $new_id,
                'message_data' => [
                    'id'         => $new_id,
                    'message'    => $message,
                    'is_admin'   => true,
                    'type'       => $type,
                    'created_at' => date('d M, g:i A'),
                    'is_seen'    => false,
                    'can_edit_delete' => true
                ]
            ]);
        } else {
            $err = $stmt->error;
            $stmt->close();
            echo json_encode(['error' => 'Execute failed: ' . $err]);
        }
        exit;
    }
    
    // 3. Edit message (admin SMS within 3 minutes)
    if ($_GET['ajax'] === 'edit_message') {
        $msg_id  = (int)($_POST['message_id'] ?? 0);
        $new_msg = trim($_POST['message'] ?? '');
        $check = $db->prepare("SELECT created_at FROM admin_seller_messages WHERE id = ? AND admin_id = ? AND type = 'sms'");
        $check->bind_param("ii", $msg_id, $admin_id);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        $check->close();
        if (!$row || (time() - strtotime($row['created_at']) > 180)) {
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
    
    // 4. Delete message (admin SMS within 3 minutes)
    if ($_GET['ajax'] === 'delete_message') {
        $msg_id = (int)($_POST['message_id'] ?? 0);
        $check = $db->prepare("SELECT created_at FROM admin_seller_messages WHERE id = ? AND admin_id = ? AND type = 'sms'");
        $check->bind_param("ii", $msg_id, $admin_id);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        $check->close();
        if (!$row || (time() - strtotime($row['created_at']) > 180)) {
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
    
    // 5. Get typing status
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
    
    // 6. Update typing status
    if ($_GET['ajax'] === 'update_typing') {
        $is_typing = isset($_POST['typing']) ? (int)$_POST['typing'] : 0;
        $sql = "INSERT INTO typing_status (seller_id, admin_id, is_typing, updated_at) 
                VALUES (?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE is_typing = ?, updated_at = NOW()";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("iiii", $seller_id, $admin_id, $is_typing, $is_typing);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $success]);
        exit;
    }
    
    echo json_encode(['error' => 'Unknown ajax action']);
    exit;
}

// ========== NORMAL PAGE LOAD ==========
ob_start();
require_once __DIR__ . '/../databases/db.php';
session_start();

$admin_id  = $_SESSION['admin_id'] ?? 1;
$seller_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = getDB();

// Ensure tables exist and column added
$db->query("CREATE TABLE IF NOT EXISTS admin_seller_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    admin_id INT DEFAULT NULL,
    message TEXT NOT NULL,
    type ENUM('message','sms') DEFAULT 'message',
    is_read TINYINT(1) DEFAULT 0,
    is_seen TINYINT(1) DEFAULT 0,
    admin_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_seller_id (seller_id),
    INDEX idx_created_at (created_at)
)");
$db->query("ALTER TABLE admin_seller_messages ADD COLUMN IF NOT EXISTS admin_read TINYINT(1) DEFAULT 0");
$db->query("CREATE TABLE IF NOT EXISTS typing_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    admin_id INT DEFAULT 1,
    is_typing TINYINT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY seller_admin (seller_id, admin_id)
)");

include "sidenav.php";

$stmt = $db->prepare("SELECT * FROM sellers WHERE id = ?");
$stmt->bind_param('i', $seller_id);
$stmt->execute();
$seller = $stmt->get_result()->fetch_assoc();
if (!$seller) die("Seller not found.");

$message_sent = false;
$sms_sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message_text = trim($_POST['message'] ?? '');
    $send_sms = isset($_POST['send_sms']);
    if (!empty($message_text)) {
        $type = $send_sms ? 'sms' : 'message';
        $admin_read = ($type === 'sms') ? 0 : 1;
        $sql = "INSERT INTO admin_seller_messages (seller_id, admin_id, message, type, is_read, is_seen, admin_read, created_at) 
                VALUES (?, ?, ?, ?, 0, 0, ?, NOW())";
        $stmt2 = $db->prepare($sql);
        $stmt2->bind_param('iissi', $seller_id, $admin_id, $message_text, $type, $admin_read);
        if ($stmt2->execute()) {
            $message_sent = true;
            if ($send_sms) $sms_sent = true;
        }
        $stmt2->close();
    }
}

$messages = [];
$msg_query = "SELECT * FROM admin_seller_messages WHERE seller_id = ? ORDER BY created_at ASC";
$stmt3 = $db->prepare($msg_query);
$stmt3->bind_param('i', $seller_id);
$stmt3->execute();
$msg_result = $stmt3->get_result();
while ($row = $msg_result->fetch_assoc()) $messages[] = $row;
$stmt3->close();

$db->query("UPDATE admin_seller_messages SET is_read = 1 WHERE seller_id = $seller_id AND is_read = 0 AND admin_id IS NULL");

function getStatusBadge($status) {
    switch($status) {
        case 'approved': return '<span class="badge approved">✓ Approved</span>';
        case 'rejected': return '<span class="badge rejected">✗ Rejected</span>';
        default:         return '<span class="badge pending">⏳ Pending</span>';
    }
}
$shop_initial = strtoupper(substr($seller['shop_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Details - <?= htmlspecialchars($seller['shop_name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #F8F5F0;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, sans-serif;
            line-height: 1.5;
        }
        .main-content { margin-left: 280px; padding: 30px 35px; transition: all 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 20px; } }
        .card {
            background: white;
            border-radius: 32px;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 32px;
        }
        .card-header {
            padding: 20px 28px;
            border-bottom: 1px solid #F0EAE2;
            background: #FFFBF7;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .shop-logo {
            width: 70px; height: 70px;
            background: linear-gradient(135deg, #C0392B, #E67E22);
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            font-size: 32px; font-weight: 700; color: white;
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }
        .shop-info h2 {
            font-size: 1.6rem; font-weight: 700; color: #2D2A26; margin: 0;
            display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        }
        .shop-info p { margin: 5px 0 0; color: #7A6558; font-size: 0.85rem; }
        .card-body { padding: 24px 28px; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px; margin-bottom: 28px;
        }
        .info-item { background: #FEFAF5; border-radius: 18px; padding: 12px 16px; border: 1px solid #F0E4D8; }
        .info-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; color: #B26B3D; margin-bottom: 4px; }
        .info-value { font-size: 0.95rem; font-weight: 500; color: #2C2A28; word-break: break-word; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 40px; font-size: 0.7rem; font-weight: 600; }
        .badge.approved { background: #E3F7EC; color: #1E7B48; }
        .badge.rejected { background: #FEF2F0; color: #C73E2D; }
        .badge.pending  { background: #FFF0E0; color: #E67E22; }
        .documents-section { margin: 24px 0 20px; }
        .documents-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 14px; color: #3E3A35; }
        .doc-grid { display: flex; flex-wrap: wrap; gap: 20px; }
        .doc-card {
            background: #FEFAF5; border-radius: 18px; padding: 12px;
            text-align: center; width: 150px; border: 1px solid #EFE3D9;
            cursor: pointer; transition: transform 0.2s;
        }
        .doc-card:hover { transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0,0,0,0.05); }
        .doc-card strong { font-size: 0.75rem; margin-bottom: 8px; display: block; color: #7F5E3A; }
        .doc-card img { max-width: 100%; height: auto; border-radius: 12px; max-height: 100px; object-fit: cover; }
        .missing-doc { background: #FFF1F0; border-color: #F5C6B8; color: #BC5A3C; padding: 15px 8px; }
        .missing-doc .icon { font-size: 1.8rem; display: block; margin-bottom: 5px; }
        .modal {
            display: none; position: fixed; z-index: 2000;
            left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.9);
            align-items: center; justify-content: center;
        }
        .modal-content { max-width: 90%; max-height: 90%; margin: auto; display: block; box-shadow: 0 8px 20px rgba(0,0,0,0.3); border-radius: 12px; }
        .close-modal { position: absolute; top: 20px; right: 35px; color: white; font-size: 40px; font-weight: bold; cursor: pointer; }
        .action-buttons { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 24px; }
        .btn { padding: 8px 18px; border-radius: 40px; text-decoration: none; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 6px; }
        .btn-approve { background: #2C7A47; color: white; }
        .btn-reject  { background: #B3432E; color: white; }
        .btn-back    { background: #8E7A66; color: white; }
        .btn:hover { opacity: 0.85; transform: translateY(-1px); }
        .alert { padding: 10px 16px; border-radius: 40px; margin-bottom: 20px; font-size: 0.8rem; }
        .alert-success { background: #E2F3E6; color: #1E6F3F; border-left: 4px solid #2C7A47; }
        .missing-doc-warning { background: #FFF3EB; border-left: 4px solid #E67E22; padding: 10px 16px; border-radius: 18px; margin-bottom: 18px; font-size: 0.8rem; }
        /* Floating chat & drawer */
        .floating-chat-btn {
            position: fixed; bottom: 24px; right: 24px;
            width: 56px; height: 56px;
            background: linear-gradient(135deg, #C0392B, #E67E22);
            border-radius: 28px; display: flex; align-items: center; justify-content: center;
            cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 1000; transition: transform 0.2s;
        }
        .floating-chat-btn:hover { transform: scale(1.05); }
        .floating-chat-btn svg { width: 28px; height: 28px; fill: white; }
        .floating-chat-btn .tooltip {
            position: absolute; right: 70px; background: #2D2A26; color: white;
            padding: 6px 12px; border-radius: 30px; font-size: 0.75rem;
            white-space: nowrap; opacity: 0; pointer-events: none; transition: opacity 0.2s;
        }
        .floating-chat-btn:hover .tooltip { opacity: 1; }
        .chat-drawer {
            position: fixed; top: 0; right: -420px;
            width: 400px; height: 100vh;
            background: white; box-shadow: -4px 0 20px rgba(0,0,0,0.1);
            z-index: 1100; transition: right 0.3s ease;
            display: flex; flex-direction: column;
        }
        .chat-drawer.open { right: 0; }
        .drawer-header {
            padding: 16px 20px; background: #FCF8F3;
            border-bottom: 1px solid #E8DDD3;
            display: flex; justify-content: space-between; align-items: center;
            font-weight: 700;
        }
        .drawer-header h3 { font-size: 1.1rem; margin: 0; }
        .close-drawer { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #7A6558; }
        .drawer-messages {
            flex: 1; overflow-y: auto; padding: 16px;
            display: flex; flex-direction: column; gap: 12px; background: #FEFCF9;
        }
        .drawer-message-item { display: flex; flex-direction: column; align-items: flex-start; }
        .drawer-message-item.seller { align-items: flex-start; }
        .drawer-message-item.admin  { align-items: flex-end; }
        .drawer-bubble { max-width: 85%; padding: 8px 14px; border-radius: 18px; font-size: 0.85rem; line-height: 1.4; }
        .drawer-message-item.seller .drawer-bubble { background: #F0E9E2; color: #2D241C; border-bottom-left-radius: 4px; }
        .drawer-message-item.admin  .drawer-bubble { background: #C0392B; color: white; border-bottom-right-radius: 4px; }
        .drawer-meta { font-size: 0.6rem; margin-top: 4px; color: #A28D76; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .menu-btn {
            background: none;
            border: none;
            font-size: 0.7rem;
            line-height: 1;
            padding: 2px 4px;
            cursor: pointer;
            color: inherit;
            opacity: 0.8;
        }
        .drawer-message-item.admin .menu-btn { color: white; }
        .menu-btn:hover { opacity: 1; }
        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 20px;
            background: white;
            border: 1px solid #E8DDD3;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 10;
            min-width: 90px;
        }
        .dropdown-menu a {
            display: block;
            padding: 6px 12px;
            text-decoration: none;
            color: #2D2A26;
            font-size: 0.7rem;
        }
        .dropdown-menu a:hover { background: #F0E9E2; }
        .dropdown-menu.show { display: block; }
        .empty-chat { text-align: center; color: #A28D76; font-size: 0.8rem; margin: auto; }
        .drawer-typing { padding: 8px 16px; font-size: 0.7rem; color: #A28D76; font-style: italic; border-top: 1px solid #EEE5DC; background: #FEFCF9; }
        .drawer-input { padding: 12px 16px; border-top: 1px solid #E8DDD3; background: white; }
        .drawer-input-row { display: flex; gap: 8px; align-items: flex-end; }
        .drawer-input textarea { flex: 1; border: 1px solid #E4D6CA; border-radius: 20px; padding: 8px 12px; font-size: 0.8rem; resize: none; font-family: inherit; }
        .btn-send-small { background: #C0392B; border: none; color: white; padding: 8px 16px; border-radius: 30px; font-weight: 600; font-size: 0.75rem; cursor: pointer; white-space: nowrap; }
        .btn-send-small:disabled { opacity: 0.5; cursor: not-allowed; }
        .drawer-sms-check { margin-top: 8px; display: flex; align-items: center; gap: 6px; font-size: 0.7rem; color: #7A6558; }
        @media (max-width: 480px) { .chat-drawer { width: 100%; right: -100%; } }
    </style>
</head>
<body>
<div class="main-content">
    <?php if ($message_sent): ?>
        <div class="alert alert-success">✅ Message sent! <?= $sms_sent ? 'SMS also triggered.' : '' ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="shop-logo"><?= $shop_initial ?></div>
            <div class="shop-info">
                <h2><?= htmlspecialchars($seller['shop_name']) ?> <?= getStatusBadge($seller['status']) ?></h2>
                <p>Seller since <?= date('M Y', strtotime($seller['created_at'] ?? 'now')) ?></p>
            </div>
        </div>
        <div class="card-body">
            <?php
            $missingDocs = [];
            if (empty($seller['nagarikta_front'])) $missingDocs[] = "Nagarikta Front";
            if (empty($seller['nagarikta_back']))  $missingDocs[] = "Nagarikta Back";
            if (empty($seller['passport_photo']))  $missingDocs[] = "Passport Photo";
            if (!empty($missingDocs)):
            ?>
                <div class="missing-doc-warning">📄 <strong>Missing Documents:</strong> <?= implode(', ', $missingDocs) ?> — Request via message below.</div>
            <?php endif; ?>

            <div class="info-grid">
                <div class="info-item"><div class="info-label">Full Name</div><div class="info-value"><?= htmlspecialchars($seller['full_name']) ?></div></div>
                <div class="info-item"><div class="info-label">Email</div><div class="info-value"><?= htmlspecialchars($seller['email']) ?></div></div>
                <div class="info-item"><div class="info-label">Phone</div><div class="info-value"><?= htmlspecialchars($seller['phone']) ?></div></div>
                <div class="info-item"><div class="info-label">Shop Category</div><div class="info-value"><?= htmlspecialchars($seller['shop_category']) ?></div></div>
                <div class="info-item"><div class="info-label">PAN Number</div><div class="info-value"><?= htmlspecialchars($seller['pan_number']) ?></div></div>
                <div class="info-item"><div class="info-label">Address</div><div class="info-value"><?= nl2br(htmlspecialchars($seller['shop_address'])) ?></div></div>
                <div class="info-item"><div class="info-label">Description</div><div class="info-value"><?= nl2br(htmlspecialchars($seller['shop_description'])) ?></div></div>
            </div>

            <!-- OFFICIAL DOCUMENTS SECTION FIX -->
            <div class="documents-section">
                <div class="documents-title">📎 Official Documents</div>
                <div class="doc-grid">
                    <!-- Nagarikta Front -->
                    <div class="doc-card <?= empty($seller['nagarikta_front']) ? 'missing-doc' : '' ?>">
                        <strong>Nagarikta Front</strong>
                        <?php if (!empty($seller['nagarikta_front'])): ?>
                            <img src="../publics/uploads/nagarikta/<?= htmlspecialchars($seller['nagarikta_front']) ?>" 
                                 alt="Nagarikta Front">
                        <?php else: ?>
                            <div class="icon">⚠️</div> Not uploaded
                        <?php endif; ?>
                    </div>

                    <!-- Nagarikta Back -->
                    <div class="doc-card <?= empty($seller['nagarikta_back']) ? 'missing-doc' : '' ?>">
                        <strong>Nagarikta Back</strong>
                        <?php if (!empty($seller['nagarikta_back'])): ?>
                            <img src="../publics/uploads/nagarikta/<?= htmlspecialchars($seller['nagarikta_back']) ?>" 
                                 alt="Nagarikta Back">
                        <?php else: ?>
                            <div class="icon">⚠️</div> Not uploaded
                        <?php endif; ?>
                    </div>

                    <!-- Passport Photo -->
                    <div class="doc-card <?= empty($seller['passport_photo']) ? 'missing-doc' : '' ?>">
                        <strong>Passport Photo</strong>
                        <?php if (!empty($seller['passport_photo'])): ?>
                            <img src="../publics/uploads/passport/<?= htmlspecialchars($seller['passport_photo']) ?>" 
                                 alt="Passport Photo">
                        <?php else: ?>
                            <div class="icon">⚠️</div> Not uploaded
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <?php if ($seller['status'] == 'pending'): ?>
                    <a href="approve_seller.php?id=<?= $seller['id'] ?>" class="btn btn-approve" onclick="return confirm('Approve this seller?')">✔ Approve Application</a>
                    <a href="reject_seller.php?id=<?= $seller['id'] ?>"  class="btn btn-reject"  onclick="return confirm('Reject this seller?')">✘ Reject Application</a>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-back">← Back to Dashboard</a>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="modal">
    <span class="close-modal">&times;</span>
    <img class="modal-content" id="modalImg">
</div>

<!-- Floating Chat Button -->
<div class="floating-chat-btn" id="floatingChatBtn">
    <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4V4c0-1.1-.9-2-2-2z"/></svg>
    <div class="tooltip">Message Seller</div>
</div>

<!-- Chat Drawer -->
<div class="chat-drawer" id="chatDrawer">
    <div class="drawer-header">
        <h3>💬 Chat with Seller</h3>
        <button class="close-drawer" id="closeDrawerBtn">✕</button>
    </div>
    <div class="drawer-messages" id="drawerMessages">
        <div class="empty-chat">Loading messages...</div>
    </div>
    <div class="drawer-typing" id="drawerTyping" style="display:none;">Seller is typing...</div>
    <div class="drawer-input">
        <div class="drawer-input-row">
            <textarea id="drawerMessageText" rows="1" placeholder="Write a message... (Enter to send, Shift+Enter for new line)"></textarea>
            <button class="btn-send-small" id="sendBtn">Send →</button>
        </div>
        <div class="drawer-sms-check">
            <input type="checkbox" id="drawerSmsCheck">
            <label for="drawerSmsCheck">📲 Also send as SMS</label>
        </div>
    </div>
</div>

<script>
    // ---- Image Modal ----
    document.querySelectorAll('.doc-card img').forEach(img => {
        img.addEventListener('click', e => {
            e.stopPropagation();
            document.getElementById('imageModal').style.display = 'flex';
            document.getElementById('modalImg').src = img.src;
        });
    });
    document.querySelector('.close-modal').onclick = () => document.getElementById('imageModal').style.display = 'none';
    window.addEventListener('click', e => { if (e.target === document.getElementById('imageModal')) document.getElementById('imageModal').style.display = 'none'; });

    // ---- Chat ----
    const SELLER_ID = <?= (int)$seller_id ?>;
    const BASE_URL = location.pathname;

    let lastMessageId = 0;
    let pollingInterval = null;
    let typingPollInterval = null;
    let typingTimeout = null;
    let isDrawerOpen = false;

    const drawer         = document.getElementById('chatDrawer');
    const openBtn        = document.getElementById('floatingChatBtn');
    const closeBtn       = document.getElementById('closeDrawerBtn');
    const msgContainer   = document.getElementById('drawerMessages');
    const typingDiv      = document.getElementById('drawerTyping');
    const msgInput       = document.getElementById('drawerMessageText');
    const smsCheck       = document.getElementById('drawerSmsCheck');
    const sendBtn        = document.getElementById('sendBtn');

    function scrollBottom() { msgContainer.scrollTop = msgContainer.scrollHeight; }
    function esc(str) { return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    function appendMessage(msg) {
        const old = msgContainer.querySelector('.empty-chat');
        if (old) old.remove();

        const wrap = document.createElement('div');
        wrap.className = 'drawer-message-item ' + (msg.is_admin ? 'admin' : 'seller');
        wrap.dataset.msgId = msg.id;

        const bubble = document.createElement('div');
        bubble.className = 'drawer-bubble';
        bubble.innerHTML = msg.message.replace(/\n/g, '<br>');

        const meta = document.createElement('div');
        meta.className = 'drawer-meta';
        let m = `<span>${msg.is_admin ? '🛡️ Admin' : '👤 Seller'}</span><span>${msg.created_at}</span>`;
        if (msg.type === 'sms')                      m += `<span>📱 SMS</span>`;
        if (msg.is_admin && msg.is_seen)             m += `<span>✓ Seen</span>`;
        if (msg.is_admin && msg.can_edit_delete) {
            m += `<div style="position:relative;display:inline-block;margin-left:8px;">
                    <button class="menu-btn" onclick="toggleMenu(event,this)">⋮</button>
                    <div class="dropdown-menu">
                      <a href="#" class="edit-msg"   data-id="${msg.id}" data-text="${esc(msg.message)}">✏️ Edit</a>
                      <a href="#" class="delete-msg" data-id="${msg.id}">🗑 Delete</a>
                    </div>
                  </div>`;
        }
        meta.innerHTML = m;

        wrap.appendChild(bubble);
        wrap.appendChild(meta);
        msgContainer.appendChild(wrap);
        scrollBottom();
        bindEditDelete();
    }

    async function fetchMessages() {
        if (!isDrawerOpen) return;
        try {
            const r = await fetch(`${BASE_URL}?ajax=get_messages&seller_id=${SELLER_ID}&last_id=${lastMessageId}`);
            if (!r.ok) return;
            const d = await r.json();
            if (d.messages && d.messages.length) {
                d.messages.forEach(msg => {
                    if (!msgContainer.querySelector(`[data-msg-id="${msg.id}"]`)) {
                        appendMessage(msg);
                        if (msg.id > lastMessageId) lastMessageId = msg.id;
                    }
                });
            }
        } catch(e) { console.warn("Fetch error:", e); }
    }

    sendBtn.addEventListener('click', sendMessage);
    msgInput.addEventListener('keydown', e => { 
        if (e.key === 'Enter' && !e.shiftKey) { 
            e.preventDefault(); 
            sendMessage(); 
        } 
    });

    async function sendMessage() {
        const message = msgInput.value.trim();
        if (!message) return;

        sendBtn.disabled = true;
        sendBtn.textContent = '...';

        const fd = new FormData();
        fd.append('seller_id', SELLER_ID);
        fd.append('message', message);
        const isSms = smsCheck.checked;
        if (isSms) fd.append('send_sms', '1');

        try {
            const r = await fetch(`${BASE_URL}?ajax=send_message`, { method: 'POST', body: fd });
            const text = await r.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch(e) {
                console.error('Non-JSON response:', text);
                alert('Server error. Response is not JSON. Check console for details.');
                return;
            }
            if (data.success) {
                const newMsg = {
                    id: data.message_id,
                    message: message,
                    is_admin: true,
                    type: isSms ? 'sms' : 'message',
                    created_at: new Date().toLocaleString('en-US', { day: 'numeric', month: 'short', hour: 'numeric', minute: 'numeric', hour12: true }),
                    is_seen: false,
                    can_edit_delete: true
                };
                appendMessage(newMsg);
                if (data.message_id > lastMessageId) lastMessageId = data.message_id;
                
                msgInput.value = '';
                smsCheck.checked = false;
                await fetchMessages();
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        } catch(e) {
            console.error('Network error:', e);
            alert('Network error. Please try again.');
        } finally {
            sendBtn.disabled = false;
            sendBtn.textContent = 'Send →';
        }
    }

    async function checkTyping() {
        if (!isDrawerOpen) return;
        try {
            const r = await fetch(`${BASE_URL}?ajax=get_typing&seller_id=${SELLER_ID}`);
            const d = await r.json();
            typingDiv.style.display = d.typing ? 'block' : 'none';
        } catch(e) { /* silent */ }
    }

    async function updateTyping(isTyping) {
        try {
            const fd = new FormData();
            fd.append('seller_id', SELLER_ID);
            fd.append('typing', isTyping ? 1 : 0);
            await fetch(`${BASE_URL}?ajax=update_typing`, { method: 'POST', body: fd });
        } catch(e) { /* silent */ }
    }

    msgInput.addEventListener('input', () => {
        updateTyping(true);
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => updateTyping(false), 2000);
    });

    function startPolling() {
        stopPolling();
        pollingInterval    = setInterval(fetchMessages,  3000);
        typingPollInterval = setInterval(checkTyping,    2000);
    }
    function stopPolling() {
        clearInterval(pollingInterval);
        clearInterval(typingPollInterval);
    }

    async function openDrawer() {
        isDrawerOpen = true;
        drawer.classList.add('open');
        lastMessageId = 0;
        msgContainer.innerHTML = '<div class="empty-chat">Loading...</div>';
        await fetchMessages();
        if (!msgContainer.children.length || msgContainer.querySelector('.empty-chat')) {
            msgContainer.innerHTML = '<div class="empty-chat">No messages yet. Start the conversation.</div>';
        }
        startPolling();
        msgInput.focus();
    }
    function closeDrawer() {
        isDrawerOpen = false;
        drawer.classList.remove('open');
        stopPolling();
        updateTyping(false);
    }

    openBtn.addEventListener('click', openDrawer);
    closeBtn.addEventListener('click', closeDrawer);
    document.addEventListener('click', e => {
        if (isDrawerOpen && !drawer.contains(e.target) && !openBtn.contains(e.target)) closeDrawer();
    });

    async function editMessage(msgId, newText) {
        const fd = new FormData();
        fd.append('message_id', msgId);
        fd.append('message', newText);
        const r = await fetch(`${BASE_URL}?ajax=edit_message`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            const el = msgContainer.querySelector(`[data-msg-id="${msgId}"] .drawer-bubble`);
            if (el) el.innerHTML = newText.replace(/\n/g,'<br>');
        } else alert(d.error || 'Edit failed');
    }

    async function deleteMessage(msgId) {
        const fd = new FormData();
        fd.append('message_id', msgId);
        const r = await fetch(`${BASE_URL}?ajax=delete_message`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            const el = msgContainer.querySelector(`[data-msg-id="${msgId}"]`);
            if (el) el.remove();
            if (!msgContainer.querySelector('.drawer-message-item')) {
                msgContainer.innerHTML = '<div class="empty-chat">No messages yet.</div>';
            }
        } else alert(d.error || 'Delete failed');
    }

    function bindEditDelete() {
        document.querySelectorAll('.edit-msg').forEach(a => {
            a.onclick = function(e) {
                e.preventDefault();
                const t = prompt('Edit message:', this.dataset.text);
                if (t !== null && t !== this.dataset.text) editMessage(this.dataset.id, t);
            };
        });
        document.querySelectorAll('.delete-msg').forEach(a => {
            a.onclick = function(e) {
                e.preventDefault();
                if (confirm('Delete permanently?')) deleteMessage(this.dataset.id);
            };
        });
    }

    window.toggleMenu = function(e, btn) {
        e.stopPropagation();
        document.querySelectorAll('.dropdown-menu.show').forEach(m => { if (m !== btn.nextElementSibling) m.classList.remove('show'); });
        btn.nextElementSibling.classList.toggle('show');
    };
    document.addEventListener('click', () => document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show')));
</script>
</body>
</html>