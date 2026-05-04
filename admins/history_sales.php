<?php
session_start();

// Admin authentication check (uncomment when ready)
// if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
//     header("Location: login.php");
//     exit;
// }

require_once "../databases/db.php";
$conn = getDB();

// Get filter parameters
$period = $_GET['period'] ?? 'today';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Define date ranges based on period
$date_conditions = [
    'today' => "DATE(created_at) = CURDATE()",
    'two_days' => "created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)",
    'week' => "YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)",
    'month' => "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())",
    'year' => "YEAR(created_at) = YEAR(CURDATE())"
];

$date_condition = $date_conditions[$period] ?? $date_conditions['today'];

// Build search condition
$search_condition = "";
if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $search_condition = "AND (name LIKE '%$search_escaped%' 
                          OR email LIKE '%$search_escaped%' 
                          OR phone LIKE '%$search_escaped%' 
                          OR id = '$search_escaped')";
}

// Fetch statistics
$stats_query = "SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(total), 0) as total_revenue,
                    COALESCE(AVG(total), 0) as avg_order_value,
                    SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as delivered_count,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'Processing' THEN 1 ELSE 0 END) as processing_count,
                    SUM(CASE WHEN status = 'Shipped' THEN 1 ELSE 0 END) as shipped_count,
                    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_count
                FROM orders 
                WHERE $date_condition $search_condition";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Fetch orders with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$orders_query = "SELECT o.*, 
                    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
                FROM orders o
                WHERE $date_condition $search_condition
                ORDER BY o.created_at DESC
                LIMIT $limit OFFSET $offset";

$orders_result = $conn->query($orders_query);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM orders WHERE $date_condition $search_condition";
$count_result = $conn->query($count_query);
$total_orders = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $limit);

include "sidenav.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Order Management | SportGhar</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
       * { box-sizing: border-box; margin: 0; padding: 0; }

        
        body {
            font-family: 'Segoe UI', Roboto, Arial, sans-serif;
    background: #dadbf6;
    color: #250404;
    overflow-x: hidden;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 24px 32px;
        }
        
        /* Top Bar */
        .top-bar {
            background: #fff;
            border-radius: 20px;
            padding: 16px 24px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .page-title h1 {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }
        
        .page-title p {
            color: #64748b;
            font-size: 14px;
            margin-top: 4px;
        }
        
        .search-box {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .search-form {
            display: flex;
            align-items: center;
            background: #f1f5f9;
            border-radius: 40px;
            padding: 4px 8px 4px 16px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        
        .search-form:focus-within {
            background: #fff;
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245,158,11,0.1);
        }
        
        .search-form input {
            border: none;
            background: none;
            padding: 10px 8px 10px 0;
            font-size: 14px;
            width: 240px;
            outline: none;
        }
        
        .search-form button {
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 30px;
            transition: all 0.3s;
        }
        
        .search-form button:hover {
            color: #f59e0b;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: #fff;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .stat-header span {
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-header i {
            font-size: 28px;
            color: #cbd5e1;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
        }
        
        .stat-sub {
            font-size: 12px;
            color: #64748b;
        }
        
        /* Filter Tabs */
        .filter-tabs {
            background: #fff;
            border-radius: 16px;
            padding: 8px;
            margin-bottom: 24px;
            display: inline-flex;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .filter-tab {
            padding: 10px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            color: #64748b;
            background: transparent;
            border: none;
            cursor: pointer;
        }
        
        .filter-tab:hover {
            background: #f1f5f9;
            color: #1e293b;
        }
        
        .filter-tab.active {
            background: #f59e0b;
            color: #fff;
        }
        
        /* Orders Table */
        .orders-table-container {
            background: #fff;
            border-radius: 20px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table th,
        .orders-table td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .orders-table th {
            background: #f8fafc;
            font-weight: 600;
            font-size: 13px;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .orders-table tr:hover {
            background: #f8fafc;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }
        
        .status-processing {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .status-shipped {
            background: #e0e7ff;
            color: #4f46e5;
        }
        
        .status-delivered {
            background: #d1fae5;
            color: #059669;
        }
        
        .status-cancelled {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #fff;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: #fff;
            border-radius: 24px;
            width: 90%;
            max-width: 600px;
            padding: 28px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            font-size: 20px;
            font-weight: 700;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #94a3b8;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .detail-label {
            font-weight: 600;
            color: #475569;
        }
        
        .detail-value {
            color: #1e293b;
        }
        
        .items-list {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .item-row:last-child {
            border-bottom: none;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .item-meta {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
        }
        
        .seller-name {
            font-size: 12px;
            color: #f59e0b;
            margin-top: 2px;
        }
        
        .item-price {
            font-weight: 700;
            color: #059669;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            margin-top: 24px;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
            border: none;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 32px;
        }
        
        .pagination a {
            padding: 8px 14px;
            border-radius: 10px;
            text-decoration: none;
            color: #475569;
            background: #fff;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        
        .pagination a.active {
            background: #f59e0b;
            color: #fff;
            border-color: #f59e0b;
        }
        
        .pagination a:hover:not(.active) {
            background: #f1f5f9;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
<div class="admin-container">
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Order Management</h1>
                <p>Monitor customer orders and view seller details</p>
            </div>
            <div class="search-box">
                <form method="GET" class="search-form">
                    <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
                    <input type="text" name="search" placeholder="Search by name, email, phone, order ID..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span>Total Orders</span>
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_orders']); ?></div>
                <div class="stat-sub">in selected period</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>Revenue</span>
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="stat-value">रु <?php echo number_format($stats['total_revenue']); ?></div>
                <div class="stat-sub">total sales</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>Avg. Order Value</span>
                    <i class="fas fa-chart-simple"></i>
                </div>
                <div class="stat-value">रु <?php echo number_format($stats['avg_order_value']); ?></div>
                <div class="stat-sub">per order average</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span>Delivered</span>
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['delivered_count']; ?></div>
                <div class="stat-sub">completed orders</div>
            </div>
        </div>
        
        <!-- Period Filter Tabs -->
        <div class="filter-tabs">
            <a href="?period=today&search=<?php echo urlencode($search); ?>" 
               class="filter-tab <?php echo $period == 'today' ? 'active' : ''; ?>">Today</a>
            <a href="?period=two_days&search=<?php echo urlencode($search); ?>" 
               class="filter-tab <?php echo $period == 'two_days' ? 'active' : ''; ?>">Last 2 Days</a>
            <a href="?period=week&search=<?php echo urlencode($search); ?>" 
               class="filter-tab <?php echo $period == 'week' ? 'active' : ''; ?>">This Week</a>
            <a href="?period=month&search=<?php echo urlencode($search); ?>" 
               class="filter-tab <?php echo $period == 'month' ? 'active' : ''; ?>">This Month</a>
            <a href="?period=year&search=<?php echo urlencode($search); ?>" 
               class="filter-tab <?php echo $period == 'year' ? 'active' : ''; ?>">This Year</a>
        </div>
        
        <!-- Orders Table -->
        <div class="orders-table-container">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($orders_result && $orders_result->num_rows > 0): ?>
                        <?php while($order = $orders_result->fetch_assoc()): ?>
                            <?php
                            $status_class = '';
                            switch($order['status']) {
                                case 'Pending': $status_class = 'status-pending'; break;
                                case 'Processing': $status_class = 'status-processing'; break;
                                case 'Shipped': $status_class = 'status-shipped'; break;
                                case 'Delivered': $status_class = 'status-delivered'; break;
                                case 'Cancelled': $status_class = 'status-cancelled'; break;
                            }
                            ?>
                            <tr>
                                <td>#<?php echo str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td><strong><?php echo htmlspecialchars($order['name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($order['phone']); ?></small></td>
                                <td><strong>रु <?php echo number_format($order['total']); ?></strong></td>
                                <td><?php echo $order['item_count']; ?> items</td>
                                <td><span class="status-badge <?php echo $status_class; ?>"><i class="fas fa-circle" style="font-size: 8px;"></i> <?php echo $order['status']; ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <button class="action-btn" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 60px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #cbd5e1;"></i>
                                <p style="margin-top: 16px; color: #64748b;">No orders found for this period</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?period=<?php echo $period; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page-1; ?>">&laquo; Previous</a>
            <?php endif; ?>
            
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?period=<?php echo $period; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" 
                   class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if($page < $total_pages): ?>
                <a href="?period=<?php echo $period; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page+1; ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Order Details Modal (View only, no status update) -->
<div id="orderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Order Details</h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <div id="modalContent">
            <!-- Dynamically loaded content -->
            <div style="text-align: center; padding: 20px;">Loading...</div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal()">Close</button>
        </div>
    </div>
</div>

<script>
function viewOrderDetails(orderId) {
    // Show loading
    document.getElementById('modalContent').innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-pulse"></i> Loading order details...</div>';
    document.getElementById('orderModal').style.display = 'flex';
    
    fetch('get_order_items.php?order_id=' + orderId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('modalContent').innerHTML = '<div style="color: red; text-align: center;">' + data.error + '</div>';
                return;
            }
            
            let itemsHtml = '';
            if (data.items && data.items.length > 0) {
                data.items.forEach(item => {
                    itemsHtml += `
                        <div class="item-row">
                            <div class="item-details">
                                <div class="item-name">${escapeHtml(item.jersey_name)}</div>
                                <div class="item-meta">Size: ${item.size} | Qty: ${item.qty}</div>
                                <div class="seller-name"><i class="fas fa-store"></i> Seller: ${escapeHtml(item.seller_name)}</div>
                            </div>
                            <div class="item-price">रु ${(item.price * item.qty).toLocaleString()}</div>
                        </div>
                    `;
                });
            } else {
                itemsHtml = '<p style="color: #64748b;">No items found</p>';
            }
            
            const fullHtml = `
                <div class="detail-row">
                    <span class="detail-label">Order ID:</span>
                    <span class="detail-value">#${String(data.order_id).padStart(5, '0')}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Customer Name:</span>
                    <span class="detail-value">${escapeHtml(data.customer_name)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value">${escapeHtml(data.phone)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">${escapeHtml(data.email)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Address:</span>
                    <span class="detail-value">${escapeHtml(data.address)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Method:</span>
                    <span class="detail-value">${escapeHtml(data.payment_method)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Order Status:</span>
                    <span class="detail-value">${escapeHtml(data.status)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Order Date:</span>
                    <span class="detail-value">${data.created_at}</span>
                </div>
                <div style="margin-top: 20px;">
                    <div class="detail-label" style="margin-bottom: 10px;">Order Items (Seller wise):</div>
                    <div class="items-list">
                        ${itemsHtml}
                    </div>
                </div>
                <div class="detail-row" style="margin-top: 15px; border-top: 2px solid #e2e8f0; padding-top: 12px;">
                    <span class="detail-label">Total Amount:</span>
                    <span class="detail-value" style="font-weight: 800; color: #059669;">रु ${Number(data.total).toLocaleString()}</span>
                </div>
            `;
            document.getElementById('modalContent').innerHTML = fullHtml;
        })
        .catch(error => {
            document.getElementById('modalContent').innerHTML = '<div style="color: red; text-align: center;">Failed to load order details</div>';
            console.error(error);
        });
}

function closeModal() {
    document.getElementById('orderModal').style.display = 'none';
}

// Helper to escape HTML
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    let modal = document.getElementById('orderModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>
</body>
</html>