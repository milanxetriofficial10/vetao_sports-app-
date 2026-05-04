<?php
session_start();
require_once __DIR__ . '/../databases/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['seller_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$seller_id = (int)$_SESSION['seller_id'];
$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$new_message = trim($_POST['message'] ?? '');

if (!$message_id || empty($new_message)) {
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

$db = getDB();

// Verify message belongs to this seller, is an SMS type, and within 5 minutes
$check = $db->prepare("SELECT id, created_at FROM admin_seller_messages 
                       WHERE id = ? AND seller_id = ? AND admin_id IS NULL AND type = 'sms'");
$check->bind_param("ii", $message_id, $seller_id);
$check->execute();
$result = $check->get_result();
$msg = $result->fetch_assoc();
$check->close();

if (!$msg) {
    echo json_encode(['error' => 'Message not found or not editable']);
    exit;
}

// Check 5 minute limit
$created = strtotime($msg['created_at']);
if (time() - $created > 300) { // 300 seconds = 5 minutes
    echo json_encode(['error' => 'Cannot edit message older than 5 minutes']);
    exit;
}

// Update the message
$update = $db->prepare("UPDATE admin_seller_messages SET message = ? WHERE id = ?");
$update->bind_param("si", $new_message, $message_id);
$success = $update->execute();
$update->close();

echo json_encode(['success' => $success]);
?>