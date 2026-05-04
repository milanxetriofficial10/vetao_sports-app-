<?php
session_start();
require_once __DIR__ . '/../databases/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$admin_id = (int)$_SESSION['admin_id'];
$seller_id = isset($_POST['seller_id']) ? (int)$_POST['seller_id'] : 0;
$is_typing = isset($_POST['typing']) ? (int)$_POST['typing'] : 0;

if (!$seller_id) {
    echo json_encode(['error' => 'Missing seller_id']);
    exit;
}

$db = getDB();
$sql = "INSERT INTO typing_status (seller_id, admin_id, is_typing, updated_at) 
        VALUES (?, ?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE is_typing = ?, updated_at = NOW()";
$stmt = $db->prepare($sql);
$stmt->bind_param("iiii", $seller_id, $admin_id, $is_typing, $is_typing);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
?>