<?php
session_start();
header('Content-Type: application/json');

// Admin authentication check (uncomment when ready)
// if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
//     echo json_encode(['error' => 'Unauthorized']);
//     exit;
// }

require_once "../databases/db.php";
$conn = getDB();

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id > 0) {
    // Fetch order details
    $order_query = "SELECT id, name, phone, email, address, payment_method, status, total, created_at FROM orders WHERE id = $order_id";
    $order_result = $conn->query($order_query);
    
    if ($order_result->num_rows === 0) {
        echo json_encode(['error' => 'Order not found']);
        exit;
    }
    
    $order = $order_result->fetch_assoc();
    
    // Fetch order items with seller name (from shops table via seller_id)
    // Assuming seller_id in order_items links to shops.id or sellers table
    $items_query = "SELECT oi.jersey_name, oi.size, oi.price, oi.qty, 
                           COALESCE(s.shop_name, 'Unknown Seller') as seller_name
                    FROM order_items oi
                    LEFT JOIN shops s ON oi.seller_id = s.id
                    WHERE oi.order_id = $order_id";
    
    $items_result = $conn->query($items_query);
    $items = [];
    while ($row = $items_result->fetch_assoc()) {
        $items[] = $row;
    }
    
    echo json_encode([
        'order_id' => $order['id'],
        'customer_name' => $order['name'],
        'phone' => $order['phone'],
        'email' => $order['email'] ?? '',
        'address' => $order['address'],
        'payment_method' => $order['payment_method'],
        'status' => $order['status'],
        'total' => $order['total'],
        'created_at' => date('M d, Y h:i A', strtotime($order['created_at'])),
        'items' => $items
    ]);
} else {
    echo json_encode(['error' => 'Invalid order ID']);
}
?>