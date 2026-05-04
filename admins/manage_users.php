<?php
if(session_status() === PHP_SESSION_NONE) session_start();
include "../databases/db.php";
$conn = getDB();
include "sidenav.php";   // Your existing sidebar

if(!$conn){
    die("Database connection failed");
}



$message = '';
$error   = '';

// Handle block/unblock
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && isset($_POST['action'])){
    $user_id = (int)$_POST['user_id'];
    $action  = $_POST['action'];
    
    if($user_id == $_SESSION['user']['id']){
        $error = "You cannot block/unblock your own admin account.";
    } else {
        $new_status = ($action === 'block') ? 1 : 0;
        $stmt = $conn->prepare("UPDATE users SET is_blocked = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $user_id);
        if($stmt->execute()){
            $message = $action === 'block' ? "User blocked successfully." : "User unblocked successfully.";
        } else {
            $error = "Database error: could not update user status.";
        }
        $stmt->close();
    }
    
}

if(isset($_GET['msg'])) $message = htmlspecialchars($_GET['msg']);
if(isset($_GET['err'])) $error   = htmlspecialchars($_GET['err']);

// Fetch all users
$users = [];
$result = $conn->query("SELECT id, name, email, phone, role, created_at, is_blocked FROM users ORDER BY created_at DESC");
if($result){
    while($row = $result->fetch_assoc()){
        $users[] = $row;
    }
}

// Stats
$total_users = count($users);
$blocked_count = count(array_filter($users, fn($u) => $u['is_blocked'] == 1));
$admin_count = count(array_filter($users, fn($u) => $u['role'] == 'admin'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Manage Users | JerseyGhar</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    /* RESET & MAIN LAYOUT */
    *{margin:0;padding:0;box-sizing:border-box;}
    body{
        font-family:'Barlow',sans-serif;
        background: #dadbf6;
        min-height:100vh;
    }
    /* Main content area – assumes sidebar is fixed width ~260px */
    .main-content {
        margin-left: 260px;   /* Adjust if your sidebar width is different */
        padding: 30px 35px;
        transition: all 0.3s ease;
    }
    /* Responsive sidebar handling */
    @media (max-width: 992px) {
        .main-content { margin-left: 0; padding: 20px; }
    }

    /* CARD STYLES */
    .admin-card {
        background: transparent;
        backdrop-filter: blur(2px);
        border-radius: 32px;
        box-shadow: 0 20px 40px -12px rgba(0,0,0,0.1);
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.6);
        transition: transform 0.2s ease;
    }
    .admin-header {
        background: transparent;
        padding: 20px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .admin-header h1 {
        font-family:'Barlow Condensed',sans-serif;
        font-size: 28px;
        font-weight: 800;
        color: black;
        letter-spacing: -0.3px;
        margin:0;
    }
    .admin-header h1 i { margin-right: 12px; color: #b68902; }
    .admin-badge {
        background: rgba(0,0,0,0.2);
        backdrop-filter: blur(4px);
        padding: 6px 18px;
        border-radius: 40px;
        color: white;
        font-weight: 600;
        font-size: 13px;
    }
    .back-home {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 8px 20px;
        border-radius: 40px;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        transition: 0.2s;
    }
    .back-home:hover { background: rgba(255,255,255,0.3); transform: translateY(-1px); }

    /* STATS CARDS */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        padding: 30px 30px 0 30px;
    }
    .stat-card {
        background: white;
        border-radius: 24px;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.03);
        border: 1px solid #ffe2d0;
        transition: all 0.2s;
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 24px rgba(201,75,1,0.08); }
    .stat-icon {
        width: 52px;
        height: 52px;
        background: #fff1e8;
        border-radius: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        color: #c94b01;
    }
    .stat-info h3 { font-size: 26px; font-weight: 800; color: #2c1a0c; margin-bottom: 4px; }
    .stat-info p { font-size: 13px; font-weight: 600; color: #a87050; letter-spacing: 0.3px; }

    /* MESSAGES */
    .message-toast {
        margin: 20px 30px 0 30px;
        padding: 14px 20px;
        border-radius: 20px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.3s ease;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-15px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .message-toast.success { background: #e4f5e9; color: #1e6f3f; border-left: 5px solid #2ecc71; }
    .message-toast.error   { background: #fee9e6; color: #b13b2d; border-left: 5px solid #e67e22; }

    /* SEARCH & FILTER */
    .search-bar {
        padding: 20px 30px;
    }
    .search-wrapper {
        display: flex;
        align-items: center;
        background: white;
        border-radius: 60px;
        border: 1px solid #ffe0cc;
        padding: 8px 18px;
        max-width: 380px;
    }
    .search-wrapper i { color: #c94b01; font-size: 16px; margin-right: 10px; }
    .search-wrapper input {
        border: none;
        outline: none;
        background: transparent;
        width: 100%;
        font-size: 14px;
        font-weight: 500;
        padding: 8px 0;
    }
    .search-wrapper input::placeholder { color: #c7ad98; }

    /* TABLE */
    .table-wrapper {
        overflow-x: auto;
        padding: 0 30px 30px 30px;
    }
    .user-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        background: white;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    }
    .user-table th {
        text-align: left;
        padding: 16px 16px;
        background: #fcf6f0;
        color: #5c2f14;
        font-weight: 800;
        font-size: 13px;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #fee1cf;
    }
    .user-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #f7e8df;
        vertical-align: middle;
        color: #362012;
        font-weight: 500;
    }
    .user-table tr:hover td { background-color: #fffbf7; transition: 0.1s; }

    /* BADGES */
    .badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 50px;
        font-size: 11.5px;
        font-weight: 800;
        text-align: center;
    }
    .badge.admin { background: #fff0e0; color: #b6541a; }
    .badge.user  { background: #e6f0ff; color: #1f6392; }
    .badge.blocked { background: #feebef; color: #bc2f5a; }
    .badge.active  { background: #e1f7ea; color: #1f8543; }

    /* BUTTONS */
    .btn-block, .btn-unblock {
        border: none;
        padding: 6px 16px;
        border-radius: 40px;
        font-weight: 700;
        font-size: 12px;
        cursor: pointer;
        transition: 0.2s;
        font-family:'Barlow',sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-block {
        background: #ffefef;
        color: #c0392b;
        border: 1px solid #ffcece;
    }
    .btn-block:hover { background: #ffe0e0; transform: scale(0.97); }
    .btn-unblock {
        background: #e5f6ea;
        color: #2b7a3e;
        border: 1px solid #c8f0d4;
    }
    .btn-unblock:hover { background: #d0edda; transform: scale(0.97); }
    .protected-badge {
        font-size: 11px;
        color: #b48b6e;
        font-weight: 700;
        background: #f5ede6;
        padding: 4px 12px;
        border-radius: 30px;
    }

    .empty-row td { padding: 60px; text-align: center; color: #b28160; font-weight: 500; }

    @media (max-width: 700px) {
        .stats-grid { grid-template-columns: 1fr; }
        .admin-header { flex-direction: column; align-items: start; }
        .user-table th, .user-table td { padding: 12px 8px; }
    }
</style>
</head>
<body>

<!-- SIDEBAR already loaded via include "sidenav.php" -->
<div class="main-content">
    <div class="admin-card">
        <div class="admin-header">
            <h1><i class="fa fa-users-gear"></i> User Management</h1>
            <div style="display:flex; gap:12px; align-items:center;">
                <div class="admin-badge"><i class="fa fa-shield-alt"></i> Admin: <?php echo htmlspecialchars($_SESSION['user']['name'] ?? 'Admin'); ?></div>
                <a href="../publics/index.php" class="back-home"><i class="fa fa-store"></i> Store</a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fa fa-users"></i></div><div class="stat-info"><h3><?php echo $total_users; ?></h3><p>Total Registered</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fa fa-ban"></i></div><div class="stat-info"><h3><?php echo $blocked_count; ?></h3><p>Blocked Users</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fa fa-user-shield"></i></div><div class="stat-info"><h3><?php echo $admin_count; ?></h3><p>Administrators</p></div></div>
        </div>

        <!-- Messages -->
        <?php if($message): ?>
            <div class="message-toast success"><i class="fa fa-check-circle fa-lg"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="message-toast error"><i class="fa fa-exclamation-triangle fa-lg"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Search / Filter -->
        <div class="search-bar">
            <div class="search-wrapper">
                <i class="fa fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by name or email...">
            </div>
        </div>

        <!-- Users Table -->
        <div class="table-wrapper">
            <table class="user-table" id="userTable">
                <thead>
                    <tr><th>ID</th><th>Full Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Registered</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if(empty($users)): ?>
                        <tr class="empty-row"><td colspan="8"><i class="fa fa-user-slash"></i> No users registered yet. </tr>
                    <?php else: ?>
                        <?php foreach($users as $user): ?>
                        <tr class="user-row" data-name="<?php echo strtolower(htmlspecialchars($user['name'])); ?>" data-email="<?php echo strtolower(htmlspecialchars($user['email'])); ?>">
                            <td><?php echo $user['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone'] ?: '—'); ?></td>
                            <td><span class="badge <?php echo $user['role'] === 'admin' ? 'admin' : 'user'; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                            <td><?php echo date("d M Y", strtotime($user['created_at'])); ?></td>
                            <td><?php echo $user['is_blocked'] ? '<span class="badge blocked"><i class="fa fa-ban"></i> Blocked</span>' : '<span class="badge active"><i class="fa fa-circle-check"></i> Active</span>'; ?></td>
                            <td>
                                <?php if($user['role'] !== 'admin'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to <?php echo $user['is_blocked'] ? 'unblock' : 'block'; ?> <?php echo htmlspecialchars($user['name']); ?>?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="<?php echo $user['is_blocked'] ? 'unblock' : 'block'; ?>">
                                        <?php if($user['is_blocked']): ?>
                                            <button type="submit" class="btn-unblock"><i class="fa fa-unlock-alt"></i> Unblock</button>
                                        <?php else: ?>
                                            <button type="submit" class="btn-block"><i class="fa fa-lock"></i> Block</button>
                                        <?php endif; ?>
                                    </form>
                                <?php else: ?>
                                    <span class="protected-badge"><i class="fa fa-crown"></i> Protected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Search Filter Script -->
<script>
    const searchInput = document.getElementById('searchInput');
    const rows = document.querySelectorAll('.user-row');
    searchInput.addEventListener('keyup', function() {
        const term = this.value.toLowerCase();
        rows.forEach(row => {
            const name = row.getAttribute('data-name');
            const email = row.getAttribute('data-email');
            if(name.includes(term) || email.includes(term)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
</script>
</body>
</html>