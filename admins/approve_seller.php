<?php
session_start();

require_once __DIR__ . '/../databases/db.php';

$id = $_GET['id'] ?? 0;
$db = getDB();
$stmt = $db->prepare("UPDATE sellers SET status = 'approved' WHERE id = ? AND status = 'pending'");
$stmt->bind_param('i', $id);
$stmt->execute();
header('Location: seller_dashboard.php');
exit;