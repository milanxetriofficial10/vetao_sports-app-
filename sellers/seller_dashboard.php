<?php
session_start();
require_once __DIR__ . '/../databases/db.php';

// Increase PHP limits to handle file uploads
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');
ini_set('max_execution_time', 120);
ini_set('memory_limit', '128M');

// ========== ENSURE DATABASE COLUMNS EXIST ==========
$db = getDB();

$check_file_path = $db->query("SHOW COLUMNS FROM admin_messages LIKE 'file_path'");
if (!$check_file_path || $check_file_path->num_rows === 0) {
    $db->query("ALTER TABLE admin_messages ADD COLUMN file_path VARCHAR(500) DEFAULT NULL AFTER message");
}
$check_file_type = $db->query("SHOW COLUMNS FROM admin_messages LIKE 'file_type'");
if (!$check_file_type || $check_file_type->num_rows === 0) {
    $db->query("ALTER TABLE admin_messages ADD COLUMN file_type VARCHAR(100) DEFAULT NULL AFTER file_path");
}
$check_locked = $db->query("SHOW COLUMNS FROM sellers LIKE 'locked'");
if (!$check_locked || $check_locked->num_rows === 0) {
    $db->query("ALTER TABLE sellers ADD COLUMN locked TINYINT(1) NOT NULL DEFAULT 0");
}
$check_contract_signed = $db->query("SHOW COLUMNS FROM sellers LIKE 'contract_signed'");
if (!$check_contract_signed || $check_contract_signed->num_rows === 0) {
    $db->query("ALTER TABLE sellers ADD COLUMN contract_signed TINYINT(1) NOT NULL DEFAULT 0");
}
$check_contract_signed_at = $db->query("SHOW COLUMNS FROM sellers LIKE 'contract_signed_at'");
if (!$check_contract_signed_at || $check_contract_signed_at->num_rows === 0) {
    $db->query("ALTER TABLE sellers ADD COLUMN contract_signed_at DATETIME DEFAULT NULL");
}
$db->query("CREATE TABLE IF NOT EXISTS admin_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$new_columns = [
    "profile_completed TINYINT(1) NOT NULL DEFAULT 0",
    "shop_logo VARCHAR(255) DEFAULT NULL",
    "shop_banner VARCHAR(255) DEFAULT NULL",
    "bank_account_details TEXT DEFAULT NULL",
    "business_type VARCHAR(100) DEFAULT NULL",
    "tax_info VARCHAR(255) DEFAULT NULL",
    "alt_phone VARCHAR(50) DEFAULT NULL",
    "whatsapp VARCHAR(50) DEFAULT NULL",
    "emergency_contact VARCHAR(100) DEFAULT NULL",
    "agreement_accepted TINYINT(1) NOT NULL DEFAULT 0",
    "agreement_accepted_at DATETIME DEFAULT NULL",
    "bank_holder_name VARCHAR(255) DEFAULT NULL",
    "bank_cheque_image VARCHAR(255) DEFAULT NULL"
];
foreach ($new_columns as $col_def) {
    $col_name = explode(' ', trim($col_def))[0];
    $check_col = $db->query("SHOW COLUMNS FROM sellers LIKE '$col_name'");
    if (!$check_col || $check_col->num_rows === 0) {
        $db->query("ALTER TABLE sellers ADD COLUMN $col_def");
    }
}

// ========== AJAX HANDLERS ==========
if (isset($_GET['ajax'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        echo json_encode(['error' => "PHP Error: $errstr in $errfile on line $errline"]);
        exit;
    });
    $db = getDB();
    $seller_id = (int)($_SESSION['seller_id'] ?? 0);
    if (!$seller_id) { echo json_encode(['error' => 'Not logged in']); exit; }

    // ---------- CHAT HANDLERS ----------
    if ($_GET['ajax'] === 'get_messages') {
        $last_id = (int)($_GET['last_id'] ?? 0);
        $stmt = $db->prepare("
            SELECT id, seller_id, message, file_path, file_type, is_admin, type, is_seen, created_at,
                   (TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 5 AND is_admin = 0) AS can_edit_delete
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
                $fp = $m['file_path'];
                if (substr($fp, 0, 1) !== '/') $fp = '/' . $fp;
                $m['file_url']  = $fp;
                $m['file_name'] = basename($fp);
                if (empty($m['file_type'])) {
                    $ext = strtolower(pathinfo($fp, PATHINFO_EXTENSION));
                    $imageExts = ['jpg','jpeg','png','gif','webp','bmp','svg'];
                    $m['file_type'] = in_array($ext, $imageExts) ? 'image/' . $ext : 'application/octet-stream';
                }
            } else {
                $m['file_url'] = $m['file_name'] = $m['file_type'] = null;
            }
            $msgs[] = $m;
        }
        $stmt->close();
        $db->query("UPDATE admin_messages SET is_seen=1 WHERE seller_id=$seller_id AND is_admin=1 AND is_seen=0");
        echo json_encode(['messages' => $msgs]);
        exit;
    }

    if ($_GET['ajax'] === 'send_message') {
        $message  = trim($_POST['message'] ?? '');
        $send_sms = !empty($_POST['send_sms']);
        $file_path = null;
        $file_type = null;
        $upload_dir = __DIR__ . '/chat_uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $original_name = basename($_FILES['attachment']['name']);
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','pdf','doc','docx','txt','zip','mp4','webm','webp'];
            if (!in_array($ext, $allowed)) { echo json_encode(['error' => 'File type not allowed.']); exit; }
            if ($_FILES['attachment']['size'] > 5 * 1024 * 1024) { echo json_encode(['error' => 'Max 5MB']); exit; }
            $safe_name = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $target = $upload_dir . $safe_name;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
               $file_path = dirname($_SERVER['SCRIPT_NAME']) . '/chat_uploads/' . $safe_name;
                $file_type = $_FILES['attachment']['type'];
            } else { echo json_encode(['error' => 'Upload failed']); exit; }
        } elseif (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            echo json_encode(['error' => 'Upload error: ' . $_FILES['attachment']['error']]); exit;
        }
        if (empty($message) && !$file_path) { echo json_encode(['error' => 'Empty message']); exit; }
        $type = $send_sms ? 'sms' : 'message';
        $stmt = $db->prepare("INSERT INTO admin_messages (seller_id, message, file_path, file_type, is_admin, type) VALUES (?, ?, ?, ?, 0, ?)");
        $stmt->bind_param('issss', $seller_id, $message, $file_path, $file_type, $type);
        if ($stmt->execute()) {
            $new_id = $db->insert_id;
            $stmt->close();
            echo json_encode(['success' => true, 'message_id'=> $new_id, 'file_url' => $file_path, 'file_name' => $file_path ? basename($file_path) : null, 'file_type' => $file_type]);
        } else {
            echo json_encode(['success' => false, 'error' => $db->error]);
        }
        exit;
    }

    if ($_GET['ajax'] === 'delete_message') {
        $msg_id = (int)($_POST['message_id'] ?? 0);
        if (!$msg_id) { echo json_encode(['success' => false]); exit; }
        $stmt = $db->prepare("DELETE FROM admin_messages WHERE id=? AND seller_id=? AND is_admin=0 AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 5");
        $stmt->bind_param('ii', $msg_id, $seller_id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    }

    if ($_GET['ajax'] === 'edit_message') {
        $msg_id  = (int)($_POST['message_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        if (!$msg_id || !$message) { echo json_encode(['success' => false]); exit; }
        $stmt = $db->prepare("UPDATE admin_messages SET message=? WHERE id=? AND seller_id=? AND is_admin=0 AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 5");
        $stmt->bind_param('sii', $message, $msg_id, $seller_id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    }

    if ($_GET['ajax'] === 'update_typing') {
        $is_typing = !empty($_POST['typing']) ? 1 : 0;
        $db->query("INSERT INTO admin_typing (seller_id, is_admin, typing, updated_at) VALUES ($seller_id, 0, $is_typing, NOW()) ON DUPLICATE KEY UPDATE typing=VALUES(typing), updated_at=NOW()");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_GET['ajax'] === 'get_typing') {
        $res = $db->query("SELECT typing, updated_at FROM admin_typing WHERE seller_id=$seller_id AND is_admin=1");
        $row = $res ? $res->fetch_assoc() : null;
        $typing = $row && $row['typing'] && strtotime($row['updated_at']) > time() - 5;
        echo json_encode(['typing' => $typing]);
        exit;
    }

    if ($_GET['ajax'] === 'sign_contract') {
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("UPDATE sellers SET contract_signed = 1, contract_signed_at = ? WHERE id = ? AND contract_signed = 0");
        $stmt->bind_param('si', $now, $seller_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'signed_at' => $now]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Already signed or update failed']);
        }
        $stmt->close();
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}
// ========== END AJAX ==========

if (isset($_GET['delete'])) {
    $seller_id = (int)($_SESSION['seller_id'] ?? 0);
    if ($seller_id) {
        $db = getDB();
        $lock_check = $db->prepare("SELECT locked FROM sellers WHERE id=?");
        $lock_check->bind_param('i', $seller_id);
        $lock_check->execute();
        $lock_result = $lock_check->get_result()->fetch_assoc();
        $lock_check->close();
        if (!$lock_result || $lock_result['locked'] != 1) {
            $id = (int)$_GET['delete'];
            $stmt = $db->prepare("DELETE FROM jerseys WHERE id=? AND seller_id=?");
            $stmt->bind_param('ii', $id, $seller_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    header('Location: seller_dashboard.php');
    exit;
}

require_once __DIR__ . '/../sellers/sidenav.php';

if (!isset($_SESSION['seller_id'])) {
    header('Location: register.php');
    exit;
}

$db        = getDB();
$seller_id = (int)$_SESSION['seller_id'];

$stmt = $db->prepare("SELECT 
    full_name, shop_name, status, locked, contract_signed, contract_signed_at,
    email, phone, shop_category, pan_number, shop_address, shop_description, created_at,
    nagarikta_front, nagarikta_back, passport_photo,
    admin_signature, admin_stamp, admin_remarks,
    profile_completed, shop_logo, shop_banner, bank_account_details, business_type, tax_info,
    alt_phone, whatsapp, emergency_contact, bank_holder_name, bank_cheque_image
    FROM sellers WHERE id=?");
$stmt->bind_param('i', $seller_id);
$stmt->execute();
$seller = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$seller) {
    session_destroy();
    header('Location: register.php');
    exit;
}

$is_locked         = !empty($seller['locked']) && $seller['locked'] == 1;
$seller_name       = $seller['full_name'];
$shop_name         = $seller['shop_name'];
$status            = $seller['status'] ?? 'pending';
$contract_signed   = (bool)($seller['contract_signed'] ?? 0);
$contract_signed_at = $seller['contract_signed_at'] ?? null;
$profile_completed = (bool)($seller['profile_completed'] ?? 0);

// New verification flag: fully verified = profile completed + approved + not locked
$is_verified = ($profile_completed && $status === 'approved' && !$is_locked);
$_SESSION['status'] = $status;

$passport_img  = !empty($seller['passport_photo']) ? "../publics/uploads/passport/" . htmlspecialchars($seller['passport_photo']) : "https://ui-avatars.com/api/?background=C0392B&color=fff&name=" . urlencode($seller['full_name']);
$signature_img = !empty($seller['admin_signature']) ? "../publics/uploads/admin_signatures/" . htmlspecialchars($seller['admin_signature']) : "";
$stamp_img     = !empty($seller['admin_stamp']) ? "../publics/uploads/admin_stamps/" . htmlspecialchars($seller['admin_stamp']) : "";
$admin_remarks = htmlspecialchars($seller['admin_remarks'] ?? '');
$showOfficialDocBtn = ($status === 'approved' && !$is_locked && (!empty($seller['admin_signature']) || !empty($seller['admin_stamp'])));

$global_rules_pdf = null;
$stmt = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'seller_rules_pdf'");
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if ($row && !empty($row['setting_value'])) $global_rules_pdf = $row['setting_value'];
$stmt->close();

$parts = explode(' ', trim($seller_name));
$initials = strtoupper(substr($parts[0], 0, 1));
if (count($parts) > 1) $initials .= strtoupper(substr(end($parts), 0, 1));

// For verified sellers, fetch dashboard stats
$total_jerseys = $total_sell = $total_top = 0;
$chartLabels = []; $chartData = []; $recentJerseys = []; $hasCreatedAt = false;
if ($is_verified) {
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM jerseys WHERE seller_id=?");
    $stmt->bind_param('i', $seller_id); $stmt->execute();
    $total_jerseys = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0; $stmt->close();

    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM jerseys WHERE seller_id=? AND sell='Yes'");
    $stmt->bind_param('i', $seller_id); $stmt->execute();
    $total_sell = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0; $stmt->close();

    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM jerseys WHERE seller_id=? AND is_top=1");
    $stmt->bind_param('i', $seller_id); $stmt->execute();
    $total_top = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0; $stmt->close();

    $hasCreatedAt = $db->query("SHOW COLUMNS FROM jerseys LIKE 'created_at'")->num_rows > 0;
    if ($hasCreatedAt) {
        $monthlyQuery = $db->prepare("SELECT DATE_FORMAT(created_at, '%b %Y') as month_label, COUNT(*) as jersey_count FROM jerseys WHERE seller_id = ? AND created_at IS NOT NULL GROUP BY YEAR(created_at), MONTH(created_at), DATE_FORMAT(created_at, '%b %Y') ORDER BY MIN(created_at) DESC LIMIT 6");
        $monthlyQuery->bind_param('i', $seller_id); $monthlyQuery->execute();
        $result = $monthlyQuery->get_result();
        if ($result && $result->num_rows > 0) {
            $rows = array_reverse($result->fetch_all(MYSQLI_ASSOC));
            foreach ($rows as $row) { $chartLabels[] = $row['month_label']; $chartData[] = (int)$row['jersey_count']; }
        } else { $chartLabels = ['No Data']; $chartData = [0]; }
        $monthlyQuery->close();
    } else {
        $sportQuery = $db->prepare("SELECT sport_type, COUNT(*) as count FROM jerseys WHERE seller_id = ? AND sport_type IS NOT NULL AND sport_type != '' GROUP BY sport_type ORDER BY count DESC LIMIT 5");
        $sportQuery->bind_param('i', $seller_id); $sportQuery->execute();
        $result = $sportQuery->get_result();
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) { $chartLabels[] = $row['sport_type']; $chartData[] = (int)$row['count']; }
        } else { $chartLabels = ['Jerseys']; $chartData = [$total_jerseys]; }
        $sportQuery->close();
    }

    $recentQuery = $db->prepare("SELECT id, title, sport_type, price, sell, is_top, image FROM jerseys WHERE seller_id = ? ORDER BY id DESC LIMIT 5");
    $recentQuery->bind_param('i', $seller_id); $recentQuery->execute();
    $recentJerseys = $recentQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    $recentQuery->close();
}

$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM admin_messages WHERE seller_id=? AND is_admin=1 AND is_seen=0");
$stmt->bind_param('i', $seller_id); $stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();

// Lock sidebar for unverified sellers as well (admin lock OR not verified)
$body_class = ($is_locked || !$is_verified) ? 'locked' : '';

// Dummy location data for globe (simulated sales by country)
$location_sales = [
    ['lat' => 40.7128, 'lng' => -74.0060, 'city' => 'New York', 'sales' => rand(12, 45), 'jersey' => 'Football', 'country' => 'USA', 'fact' => 'Home of the Yankees ⚾'],
    ['lat' => 34.0522, 'lng' => -118.2437, 'city' => 'Los Angeles', 'sales' => rand(8, 32), 'jersey' => 'Basketball', 'country' => 'USA', 'fact' => 'Hollywood and Lakers 🎬'],
    ['lat' => 51.5074, 'lng' => -0.1278, 'city' => 'London', 'sales' => rand(15, 58), 'jersey' => 'Football', 'country' => 'UK', 'fact' => 'Premier League capital ⚽'],
    ['lat' => 48.8566, 'lng' => 2.3522, 'city' => 'Paris', 'sales' => rand(10, 38), 'jersey' => 'Rugby', 'country' => 'France', 'fact' => 'City of love & sports ❤️'],
    ['lat' => 35.6895, 'lng' => 139.6917, 'city' => 'Tokyo', 'sales' => rand(20, 62), 'jersey' => 'Baseball', 'country' => 'Japan', 'fact' => '2020 Olympics host 🏅'],
    ['lat' => 28.6139, 'lng' => 77.2090, 'city' => 'New Delhi', 'sales' => rand(25, 70), 'jersey' => 'Cricket', 'country' => 'India', 'fact' => 'Cricket mania 🇮🇳'],
    ['lat' => -33.8688, 'lng' => 151.2093, 'city' => 'Sydney', 'sales' => rand(14, 44), 'jersey' => 'Cricket', 'country' => 'Australia', 'fact' => 'Opera House & beaches 🏄'],
    ['lat' => 55.7558, 'lng' => 37.6173, 'city' => 'Moscow', 'sales' => rand(9, 28), 'jersey' => 'Hockey', 'country' => 'Russia', 'fact' => 'Red Square hockey ❄️'],
    ['lat' => -23.5505, 'lng' => -46.6333, 'city' => 'São Paulo', 'sales' => rand(18, 55), 'jersey' => 'Football', 'country' => 'Brazil', 'fact' => 'Samba football 🇧🇷'],
    ['lat' => 19.0760, 'lng' => 72.8777, 'city' => 'Mumbai', 'sales' => rand(22, 68), 'jersey' => 'Cricket', 'country' => 'India', 'fact' => 'Bollywood & cricket 🎥'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
<title>Seller Dashboard | SportGhar</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- Three.js and add-ons for 3D Globe -->
<script type="importmap">
    {
        "imports": {
            "three": "https://unpkg.com/three@0.128.0/build/three.module.js",
            "three/addons/": "https://unpkg.com/three@0.128.0/examples/jsm/"
        }
    }
</script>
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
:root {
    --primary: #C0392B;
    --primary-dk: #962d22;
    --primary-lt: #fdf1ef;
    --gold: #C9922A;
    --gold-lt: #fef8ec;
    --bg: #F5F4F0;
    --surface: #FFFFFF;
    --border: #E8E4DC;
    --text: #1C1612;
    --text-muted: #8A7D72;
    --green: #2A7A4B;
    --sidebar-w: 240px;
    --topbar-h: 0px;
    --radius: 20px;
    --shadow: 0 2px 12px rgba(0,0,0,0.06);
    --shadow-lg: 0 8px 32px rgba(0,0,0,0.1);
}
body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
.main { margin-left: var(--sidebar-w); padding: 36px 32px; min-height: 100vh; transition: margin-left 0.3s; }

/* ─── DASHBOARD HEADER ─── */
.dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
.dashboard-header h1 { font-family: 'Fraunces', serif; font-size: 2.2rem; font-weight: 700; color: var(--text); }
.header-buttons { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.btn-header { background: white; border: 1.5px solid var(--primary); color: var(--primary); padding: 9px 22px; border-radius: 100px; font-weight: 700; font-size: 0.8rem; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; letter-spacing: 0.2px; }
.btn-header:hover { background: var(--primary); color: white; box-shadow: 0 6px 16px rgba(192,57,43,0.25); }

/* ─── UNVERIFIED SPLIT LAYOUT (SKY BACKGROUND + VERIFICATION CTA) ─── */
.unverified-split {
    display: flex;
    flex-wrap: wrap;
    min-height: calc(100vh - 80px);
    background: var(--bg);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}
.unverified-left {
    flex: 1.2;
    background: linear-gradient(145deg, #1c3e6e 0%, #2b5a8c 40%, #68b0e0 100%);
    position: relative;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding: 40px;
    min-height: 400px;
    overflow: hidden;
}
.unverified-left::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" opacity="0.2"><path fill="white" d="M0,200 C150,150 300,280 450,250 C600,220 750,120 900,150 C1050,180 1150,280 1200,250 L1200,800 L0,800 Z"/><path fill="white" d="M0,400 C200,350 350,480 550,450 C750,420 900,320 1100,380 L1200,380 L1200,800 L0,800 Z" opacity="0.15"/></svg>') no-repeat bottom;
    background-size: cover;
    pointer-events: none;
}
.sky-clouds {
    position: absolute;
    width: 100%;
    height: 100%;
    background: radial-gradient(ellipse at 30% 50%, rgba(255,255,245,0.3) 0%, transparent 70%);
}
.unverified-left .illustration {
    position: relative;
    z-index: 2;
    text-align: center;
    color: white;
    max-width: 360px;
    backdrop-filter: blur(3px);
}
.unverified-left .illustration h3 {
    font-family: 'Fraunces', serif;
    font-size: 2rem;
    margin-bottom: 12px;
    text-shadow: 0 4px 14px rgba(0,0,0,0.2);
}
.unverified-left .illustration p {
    font-size: 0.9rem;
    opacity: 0.9;
}
.unverified-right {
    flex: 1;
    background: white;
    padding: 56px 48px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    box-shadow: -8px 0 32px rgba(0,0,0,0.05);
}
.unverified-right h2 {
    font-family: 'Fraunces', serif;
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 16px;
}
.unverified-right h2 span {
    color: var(--primary);
}
.unverified-right > p {
    color: var(--text-muted);
    font-size: 1rem;
    line-height: 1.5;
    margin-bottom: 28px;
}
.status-badge {
    display: inline-block;
    padding: 8px 18px;
    border-radius: 60px;
    font-weight: 800;
    font-size: 0.8rem;
    margin-bottom: 20px;
}
.status-badge.pending { background: #FFF5E0; color: #C97E00; border-left: 3px solid #F0A500; }
.status-badge.rejected { background: #FFF1F0; color: #C0392B; border-left: 3px solid #C0392B; }
.status-badge.not-started { background: #EFF6FF; color: #1E4A76; border-left: 3px solid #1E4A76; }
.info-text {
    font-size: 0.9rem;
    margin: 8px 0 20px;
    color: #5F6C7A;
}
.verify-button {
    background: var(--primary);
    color: white;
    padding: 14px 28px;
    border: none;
    border-radius: 100px;
    font-weight: 800;
    font-size: 1rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    transition: all 0.2s;
    width: fit-content;
    box-shadow: 0 6px 14px rgba(192,57,43,0.3);
    margin: 12px 0 20px;
}
.verify-button:hover { background: var(--primary-dk); transform: translateY(-3px); box-shadow: 0 12px 20px rgba(192,57,43,0.3); }
.support-note {
    font-size: 0.8rem;
    color: var(--text-muted);
    border-top: 1px solid var(--border);
    padding-top: 20px;
    margin-top: 12px;
}
.support-note span {
    color: var(--primary);
    font-weight: 700;
    cursor: pointer;
    text-decoration: underline;
}
@media (max-width: 860px) {
    .unverified-left { flex: none; width: 100%; min-height: 260px; padding: 24px; align-items: center; text-align: center; }
    .unverified-right { padding: 36px 24px; }
    .main { margin-left: 0; padding: 20px 16px; }
}
/* ─── STATUS / VERIFICATION CARDS (legacy remain but hidden in new layout) ─── */
.verify-card { max-width: 650px; margin: 60px auto; background: white; border-radius: 36px; padding: 52px 40px; text-align: center; box-shadow: var(--shadow-lg); border: 1.5px solid var(--border); }
.verify-icon { font-size: 72px; margin-bottom: 24px; }
.verify-card h2 { font-family: 'Fraunces', serif; font-size: 2rem; font-weight: 700; color: var(--text); margin-bottom: 16px; }
.verify-card p { color: var(--text-muted); font-size: 1rem; line-height: 1.6; margin-bottom: 32px; }
.status-banner { border-radius: var(--radius); padding: 18px 24px; margin-bottom: 28px; display: flex; align-items: center; gap: 18px; border: 1.5px solid; }
.status-banner.pending { background: #FFFBF0; border-color: #F0D48A; }
.status-banner.rejected { background: #FFF5F5; border-color: #FEB8B8; }
.banner-icon { width: 48px; height: 48px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 26px; flex-shrink: 0; background: rgba(255,255,255,0.7); }
.banner-text strong { display: block; font-size: 16px; font-weight: 800; margin-bottom: 4px; }
.banner-text p { font-size: 13px; color: var(--text-muted); line-height: 1.5; }
.lock-message { background: #fff5f5; border: 1.5px solid #fecdd3; border-radius: var(--radius); padding: 32px; text-align: center; }
.lock-message h2 { font-family: 'Fraunces', serif; font-size: 1.8rem; margin-bottom: 10px; color: #C0392B; }

/* ─── STAT CARDS ─── */
.mini-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 18px; margin-bottom: 28px; }
.stat-card { background: white; border-radius: var(--radius); padding: 24px 20px; box-shadow: var(--shadow); border: 1.5px solid var(--border); cursor: pointer; transition: all 0.3s; }
.stat-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: var(--primary); }
.stat-icon { font-size: 28px; margin-bottom: 12px; }
.stat-value { font-family: 'Fraunces', serif; font-size: 40px; font-weight: 700; color: var(--primary); line-height: 1; }
.stat-label { font-size: 13px; color: var(--text-muted); margin-top: 6px; font-weight: 600; }

/* ─── CHART ─── */
.chart-section { background: white; border-radius: var(--radius); padding: 28px; margin-bottom: 32px; box-shadow: var(--shadow); border: 1.5px solid var(--border); }
.chart-section h3 { font-family: 'Fraunces', serif; font-size: 1.3rem; margin-bottom: 20px; color: var(--text); display: flex; align-items: center; gap: 10px; }
.chart-container canvas { max-height: 260px; width: 100%; }

/* === GLOBE SECTION (3D Earth) === */
.globe-section {
    background: white;
    border-radius: var(--radius);
    padding: 24px 28px;
    margin: 32px 0;
    box-shadow: var(--shadow);
    border: 1.5px solid var(--border);
    position: relative;
}
.globe-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.globe-header h3 {
    font-family: 'Fraunces', serif;
    font-size: 1.4rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
}
.globe-controls {
    display: flex;
    gap: 12px;
    background: var(--bg);
    padding: 6px 14px;
    border-radius: 60px;
}
.globe-controls button {
    background: white;
    border: 1px solid var(--border);
    width: 38px;
    height: 38px;
    border-radius: 50%;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.2s;
    color: var(--primary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.globe-controls button:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.globe-wrapper {
    position: relative;
    width: 100%;
    height: 0;
    padding-bottom: 60%;
    background: radial-gradient(circle at center, #0b1120 0%, #03060c 100%);
    border-radius: 20px;
    overflow: hidden;
    margin-top: 10px;
}
#globeCanvas {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: block;
}
.globe-info-panel {
    margin-top: 16px;
    background: #fef8f5;
    border-radius: 18px;
    padding: 12px 20px;
    border-left: 5px solid var(--primary);
    font-size: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
.globe-info-text {
    color: #2c3e2f;
}
.globe-info-text strong {
    color: var(--primary);
}
.globe-note {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12px;
    color: var(--text-muted);
    font-size: 12px;
    flex-wrap: wrap;
}
.globe-note span i { font-style: normal; margin-right: 8px; }
@media (max-width: 720px) {
    .globe-wrapper { padding-bottom: 70%; }
    .globe-controls button { width: 32px; height: 32px; font-size: 14px; }
}

/* ─── GUIDELINES ─── */
.guidelines-card { background: white; border-radius: var(--radius); padding: 28px; margin: 32px 0 20px; box-shadow: var(--shadow); border: 1.5px solid var(--border); position: relative; overflow: hidden; }
.guidelines-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary), var(--gold)); }
.guidelines-card h4 { font-family: 'Fraunces', serif; font-size: 1.3rem; font-weight: 700; margin-bottom: 22px; display: flex; align-items: center; gap: 12px; }
.rules-container { display: flex; flex-wrap: wrap; gap: 10px 40px; }
.rule-col { flex: 1; min-width: 200px; }
.rule-item { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 18px; }
.rule-number { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: var(--primary); color: white; border-radius: 10px; font-size: 12px; font-weight: 800; flex-shrink: 0; margin-top: 2px; }
.rule-text { font-size: 14px; line-height: 1.5; font-weight: 500; }
.rule-text small { display: block; font-size: 12px; color: var(--text-muted); margin-top: 3px; }
.guidelines-footer { margin-top: 18px; padding-top: 16px; border-top: 1.5px dashed var(--border); font-size: 12px; color: var(--text-muted); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.pdf-link { display: inline-flex; align-items: center; gap: 6px; background: var(--primary-lt); padding: 7px 16px; border-radius: 100px; text-decoration: none; color: var(--primary); font-weight: 700; transition: all 0.2s; font-size: 12px; }
.pdf-link:hover { background: var(--primary); color: white; }

/* ─── PDF MODAL ─── */
.pdf-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.88); z-index: 10000; align-items: center; justify-content: center; }
.pdf-modal.show { display: flex; }
.pdf-modal-content { background: white; width: 90vw; height: 90vh; border-radius: 24px; overflow: hidden; position: relative; }
.pdf-modal-close { position: absolute; top: 14px; right: 18px; background: rgba(0,0,0,0.55); color: white; border: none; font-size: 24px; cursor: pointer; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 10; }
.pdf-modal-close:hover { background: rgba(0,0,0,0.8); }
.pdf-modal iframe { width: 100%; height: 100%; border: none; }

/* ─── OFFICIAL DOC MODAL ─── */
.doc-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 21000; align-items: center; justify-content: center; overflow-y: auto; padding: 24px; }
.doc-modal.show { display: flex; }
.doc-modal-content { background: #FFFDF9; border-radius: 6px; box-shadow: 0 32px 64px rgba(0,0,0,0.3); max-width: 1100px; width: 100%; margin: auto; padding: 44px 40px; border: 1px solid #DDD2C4; font-family: 'Plus Jakarta Sans', serif; position: relative; }
.doc-modal-header { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 2px solid #E7DED3; padding-bottom: 16px; margin-bottom: 24px; flex-wrap: wrap; }
.doc-modal-header h2 { font-family: 'Fraunces', serif; font-size: 1.9rem; font-weight: 700; color: #3E2A1F; }
.doc-close, .contract-close { background: none; border: none; font-size: 28px; cursor: pointer; color: #A28D76; transition: color 0.2s; }
.doc-close:hover, .contract-close:hover { color: #C0392B; }
.doc-print-btn { background: #8B5A2B; border: none; padding: 9px 22px; border-radius: 100px; color: white; font-weight: 700; cursor: pointer; font-size: 0.8rem; margin-bottom: 22px; display: inline-flex; align-items: center; gap: 8px; }
.doc-print-btn:hover { background: #5C3F28; }
.paper-header-doc { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 24px; margin-bottom: 28px; padding-bottom: 20px; border-bottom: 2px solid #E7DED3; }
.passport-area-doc { display: flex; flex-direction: column; align-items: center; gap: 8px; }
.passport-img-doc { width: 110px; height: 130px; object-fit: cover; border: 2px solid #C0A080; border-radius: 6px; background: #F9F3EA; }
.shop-title-section-doc { text-align: right; flex: 1; }
.shop-title-section-doc h1 { font-family: 'Fraunces', serif; font-size: 2rem; font-weight: 700; color: #3E2A1F; }
.info-grid-doc { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px 28px; margin-bottom: 32px; }
.info-paper-item { border-bottom: 1px dashed #E2D4C6; padding-bottom: 8px; }
.info-paper-label { font-size: 0.68rem; text-transform: uppercase; font-weight: 700; color: #AA7A50; letter-spacing: 0.5px; }
.info-paper-value { font-size: 0.95rem; font-weight: 600; color: #2E241E; margin-top: 3px; }
.doc-paper-grid { display: flex; flex-wrap: wrap; gap: 24px; margin-bottom: 28px; }
.a4-doc-card { background: #FEFAF2; border: 1px solid #DDCFBF; border-radius: 14px; width: 220px; padding: 14px 12px; text-align: center; }
.doc-label { font-size: 0.68rem; font-weight: 800; background: #E9DCCE; display: inline-block; padding: 4px 14px; border-radius: 100px; margin-bottom: 12px; color: #5C3F28; letter-spacing: 0.5px; }
.a4-doc-card img { max-width: 100%; max-height: 140px; border-radius: 8px; border: 1px solid #DBCBB9; object-fit: contain; background: #fffaf2; }
.stamp-overlay-doc { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-8deg); max-width: 180px; opacity: 0.85; z-index: 15; pointer-events: none; mix-blend-mode: multiply; }
.signature-wrapper-doc { position: absolute; bottom: 30px; right: 40px; text-align: center; z-index: 15; pointer-events: none; display: flex; flex-direction: column; align-items: center; gap: 6px; }
.signature-overlay-doc { max-width: 160px; max-height: 80px; object-fit: contain; }
.signature-caption-doc { font-size: 0.68rem; color: #5C3F28; font-weight: 700; background: rgba(255,253,249,0.9); padding: 4px 12px; border-radius: 100px; letter-spacing: 0.5px; }
.seller-description-box-doc { background: #FEF7EF; border-left: 4px solid #C86F2C; padding: 16px 20px; margin: 20px 0 24px; border-radius: 14px; font-size: 14px; line-height: 1.6; }
.admin-remark-display-doc { background: #F0FBF5; padding: 14px 20px; border-radius: 16px; margin-top: 20px; border-left: 4px solid #2C7A47; font-size: 14px; }

/* ─── FOOTER ─── */
.dashboard-footer { margin-top: 60px; padding: 28px 20px 20px; border-top: 1.5px solid var(--border); text-align: center; color: var(--text-muted); font-size: 13px; }
.dashboard-footer .footer-links { display: flex; justify-content: center; gap: 28px; margin-bottom: 10px; flex-wrap: wrap; }
.dashboard-footer a { color: var(--primary); text-decoration: none; font-weight: 600; }

/* ─── CHAT ─── */
.floating-chat-btn { position: fixed; bottom: 28px; right: 28px; width: 58px; height: 58px; background: linear-gradient(135deg, #C0392B, #E67E22); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 6px 20px rgba(192,57,43,0.45); z-index: 1000; transition: transform .2s; }
.floating-chat-btn:hover { transform: scale(1.08); }
.fcb-badge { position: absolute; top: -4px; right: -4px; background: #1C1612; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; font-weight: 800; display: flex; align-items: center; justify-content: center; border: 2px solid white; }
.chat-drawer { position: fixed; top: 0; right: -100%; width: 100%; max-width: 420px; height: 100vh; background: white; box-shadow: -4px 0 24px rgba(0,0,0,.12); z-index: 1100; transition: right .3s ease; display: flex; flex-direction: column; }
.chat-drawer.open { right: 0; }
@media (min-width: 768px) { .chat-drawer { width: 400px; right: -400px; } .chat-drawer.open { right: 0; } }
.drawer-header { padding: 18px 22px; background: #FCF8F3; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
.drawer-header h3 { font-size: 1rem; margin: 0; font-weight: 800; }
.close-drawer { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
.drawer-messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; background: #FEFCF9; }
.drawer-message-item { display: flex; flex-direction: column; }
.drawer-message-item.admin { align-items: flex-start; }
.drawer-message-item.seller { align-items: flex-end; }
.drawer-bubble { max-width: 85%; padding: 9px 15px; border-radius: 18px; font-size: .84rem; line-height: 1.5; word-break: break-word; }
.drawer-message-item.admin .drawer-bubble { background: #F0E9E2; color: #2D241C; }
.drawer-message-item.seller .drawer-bubble { background: #C0392B; color: white; }
.chat-img { max-width: 200px; max-height: 160px; border-radius: 10px; margin-top: 6px; display: block; cursor: pointer; border: 2px solid rgba(0,0,0,0.08); }
.file-attachment { display: inline-flex; align-items: center; gap: 8px; background: rgba(0,0,0,0.07); padding: 7px 12px; border-radius: 12px; margin-top: 6px; font-size: 0.75rem; text-decoration: none; color: inherit; }
.drawer-meta { font-size: .6rem; margin-top: 4px; color: #A28D76; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.menu-btn { background: none; border: none; font-size: .75rem; padding: 2px 4px; cursor: pointer; color: var(--text-muted); }
.dropdown-menu { display: none; position: absolute; right: 0; top: 20px; background: white; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 4px 14px rgba(0,0,0,.1); z-index: 10; min-width: 90px; overflow: hidden; }
.dropdown-menu a { display: block; padding: 8px 14px; text-decoration: none; color: var(--text); font-size: .72rem; }
.dropdown-menu a:hover { background: #F0E9E2; }
.dropdown-menu.show { display: block; }
.empty-chat { text-align: center; color: #A28D76; font-size: .8rem; margin: auto; padding: 20px; }
.drawer-typing { padding: 8px 16px; font-size: .7rem; color: #A28D76; font-style: italic; border-top: 1px solid #EEE5DC; background: #FEFCF9; flex-shrink: 0; }
.drawer-input { padding: 12px 16px; border-top: 1px solid var(--border); background: white; flex-shrink: 0; }
.drawer-input-row { display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap; }
.drawer-input-row .input-group { flex: 1; display: flex; gap: 6px; align-items: flex-end; }
.drawer-input textarea { flex: 1; border: 1.5px solid #E4D6CA; border-radius: 16px; padding: 9px 14px; font-size: .82rem; resize: none; font-family: inherit; outline: none; transition: border-color .15s; max-height: 100px; }
.drawer-input textarea:focus { border-color: var(--primary); }
.attachments-preview { margin-top: 8px; font-size: 0.7rem; background: #F0F0F0; padding: 4px 8px; border-radius: 12px; display: inline-flex; align-items: center; gap: 6px; }
.btn-send-small { background: var(--primary); border: none; color: white; padding: 9px 18px; border-radius: 100px; font-weight: 700; font-size: .75rem; cursor: pointer; white-space: nowrap; flex-shrink: 0; }
.drawer-sms-check { margin-top: 8px; display: flex; align-items: center; gap: 6px; font-size: .7rem; color: var(--text-muted); cursor: pointer; }
.chat-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.3); z-index: 1050; }
.chat-overlay.show { display: block; }
.img-lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.88); z-index: 9999; align-items: center; justify-content: center; cursor: zoom-out; }
.img-lightbox.show { display: flex; }
.img-lightbox img { max-width: 90vw; max-height: 90vh; border-radius: 14px; }

@media (max-width: 720px) {
    .main { margin-left: 0; padding: 20px 16px; }
    .info-grid-doc { grid-template-columns: 1fr; }
}
body.locked .sidebar-nav a:not(.profile-link) { display: none !important; }
</style>
</head>
<body class="<?= $body_class ?>">
<main class="main">

<?php if ($is_locked): ?>
    <div class="lock-message">
        <h2>🔒 Dashboard Locked</h2>
        <p style="margin-top:10px;color:var(--text-muted);">Your account has been temporarily restricted by admin.<br>Use the chat button below to contact support.</p>
    </div>

<?php elseif (!$is_verified): ?>
    <!-- NEW SPLIT VERIFICATION LAYOUT: SKY BACKGROUND + CALL TO ACTION -->
    <div class="unverified-split">
        <div class="unverified-left">
            <div class="sky-clouds"></div>
            <div class="illustration">
                <h3>🌤️ Start Your Journey</h3>
                <p>Join hundreds of verified sellers earning daily on SportGhar</p>
                <div style="margin-top: 24px;">🏆⚽🏀🏈</div>
            </div>
        </div>
        <div class="unverified-right">
            <h2>✨ Start Selling on <span>SportGhar</span></h2>
            <p>Complete your seller verification to unlock full dashboard – add jerseys, track sales, and grow your business.</p>
            <?php if ($status === 'pending'): ?>
                <div class="status-badge pending">⏳ Verification Under Review</div>
                <p class="info-text">Your application is being processed by our team. You’ll receive an email once approved (usually within 2–3 days).</p>
            <?php elseif ($status === 'rejected'): ?>
                <div class="status-badge rejected">❌ Verification Rejected</div>
                <p class="info-text">Your submitted documents did not meet requirements. Please contact support to resolve this.</p>
            <?php else: ?>
                <div class="status-badge not-started">🔑 Verification Required</div>
                <p class="info-text">Submit your documents now and become a verified seller in minutes.</p>
            <?php endif; ?>
            <a href="personal.php" class="verify-button">Verify Your Account →</a>
            <p class="support-note">📞 Need help? <span onclick="openDrawer()">Chat with support</span></p>
        </div>
    </div>

<?php else: ?>
    <!-- Fully verified seller: full dashboard with stats, jerseys, charts, guidelines AND 3D GLOBE -->
    <div class="dashboard-header">
        <h1>Welcome back, <?= htmlspecialchars($seller_name) ?> 👋</h1>
        <div class="header-buttons">
            <?php if ($showOfficialDocBtn): ?>
                <button class="btn-header" id="openOfficialDocBtn">📜 Official Document</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="mini-stats">
        <div class="stat-card"><div class="stat-icon">👕</div><div class="stat-value"><span class="counter" data-target="<?= $total_jerseys ?>">0</span></div><div class="stat-label">Total Jerseys</div></div>
        <div class="stat-card"><div class="stat-icon">🛍️</div><div class="stat-value"><span class="counter" data-target="<?= $total_sell ?>">0</span></div><div class="stat-label">For Sale</div></div>
        <div class="stat-card"><div class="stat-icon">⭐</div><div class="stat-value"><span class="counter" data-target="<?= $total_top ?>">0</span></div><div class="stat-label">Featured</div></div>
    </div>

    <?php if (!empty($chartLabels)): ?>
    <div class="chart-section">
        <h3>📊 Jersey Analytics</h3>
        <div class="chart-container"><canvas id="jerseyChart"></canvas></div>
        <p style="text-align:center;font-size:12px;color:var(--text-muted);margin-top:10px;"><?= $hasCreatedAt ? 'Monthly additions (last 6 months)' : 'Distribution by sport type' ?></p>
    </div>
    <?php endif; ?>

    <!-- 3D Earth Globe Section: Interactive World Map -->
    <div class="globe-section">
        <div class="globe-header">
            <h3>🌍 Global Jersey Reach <span style="font-size:0.85rem; background:var(--primary-lt); padding:3px 12px; border-radius:40px;">Live Location Tracking</span></h3>
            <div class="globe-controls">
                <button id="globeRotateLeft" title="Rotate Left">◀</button>
                <button id="globeRotateRight" title="Rotate Right">▶</button>
                <button id="globeRotateUp" title="Rotate Up">▲</button>
                <button id="globeRotateDown" title="Rotate Down">▼</button>
                <button id="globeZoomIn" title="Zoom In">+</button>
                <button id="globeZoomOut" title="Zoom Out">−</button>
                <button id="globeReset" title="Reset View">⟳</button>
            </div>
        </div>
        <div class="globe-wrapper">
            <canvas id="globeCanvas"></canvas>
        </div>
        <div id="globeInfoPanel" class="globe-info-panel">
            <div class="globe-info-text">✨ <strong>Click on any glowing marker</strong> to see detailed sales info and zoom in.</div>
            <div class="globe-note" style="margin-top:0;">
                <span><i>📍</i> 10+ active hotspots</span>
                <span><i>🔄</i> Auto‑rotating Earth</span>
            </div>
        </div>
        <div class="globe-note" style="margin-top: 8px;">
            <span><i>🖱️</i> Drag to rotate | Scroll to zoom | Click marker → details + zoom</span>
            <span><i>📊</i> Data based on recent orders & location tracking</span>
        </div>
    </div>

    <div class="guidelines-card">
        <h4>📋 Seller Guidelines & Code of Conduct</h4>
        <div class="rules-container">
            <div class="rule-col">
                <div class="rule-item"><span class="rule-number">1</span><div class="rule-text">Clearly disclose authentic vs. replica.<small>Transparency builds buyer trust</small></div></div>
                <div class="rule-item"><span class="rule-number">2</span><div class="rule-text">Upload high-resolution images (front, back, defects).<small>Better photos = more sales</small></div></div>
                <div class="rule-item"><span class="rule-number">3</span><div class="rule-text">Reply to buyer inquiries within 24 hours.<small>Faster responses = better ratings</small></div></div>
            </div>
            <div class="rule-col">
                <div class="rule-item"><span class="rule-number">4</span><div class="rule-text">Ship within 2–3 business days after order.<small>Reliability builds repeat buyers</small></div></div>
                <div class="rule-item"><span class="rule-number">5</span><div class="rule-text">Fair pricing — no misleading discount claims.<small>Honest pricing gains loyal customers</small></div></div>
                <div class="rule-item"><span class="rule-number">6</span><div class="rule-text">Policy violations may lead to suspension.<small>Follow rules to grow your shop</small></div></div>
            </div>
        </div>
        <div class="guidelines-footer">
            <span>🛡️ SportGhar Seller Protection Program</span>
            <?php if (!empty($global_rules_pdf) && file_exists($_SERVER['DOCUMENT_ROOT'] . $global_rules_pdf)): ?>
                <a href="javascript:void(0)" class="pdf-link" onclick="openPdfModal('<?= htmlspecialchars($global_rules_pdf . '#toolbar=0&navpanes=0') ?>')">📄 Official Rules PDF</a>
            <?php endif; ?>
            <span>⚡ Updated: <?= date('M Y') ?></span>
        </div>
    </div>

    <div class="dashboard-footer">
        <div class="footer-links">
            <a href="#">About Us</a>
            <a href="#">Seller Policies</a>
            <a href="#">Support Center</a>
            <a href="#">Terms of Service</a>
        </div>
        <p>&copy; <?= date('Y') ?> SportGhar Nepal. All rights reserved.</p>
    </div>
<?php endif; ?>

</main>

<!-- PDF Modal -->
<div id="pdfModal" class="pdf-modal" onclick="closePdfModal()">
    <div class="pdf-modal-content" onclick="event.stopPropagation()">
        <button class="pdf-modal-close" onclick="closePdfModal()">&times;</button>
        <iframe id="pdfIframe" src="" oncontextmenu="return false"></iframe>
    </div>
</div>

<!-- Official Document Modal -->
<div id="officialDocModal" class="doc-modal">
    <div class="doc-modal-content">
        <div class="doc-modal-header">
            <h2>📜 Official Verification Document</h2>
            <button class="doc-close" id="closeOfficialDocModal">&times;</button>
        </div>
        <div class="doc-body">
            <button class="doc-print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
            <div class="paper-header-doc">
                <div class="passport-area-doc">
                    <img src="<?= $passport_img ?>" class="passport-img-doc" alt="Passport Photo">
                    <small style="font-size:0.68rem;color:#AA7A50;font-weight:700;text-transform:uppercase;">Official Passport Photo</small>
                </div>
                <div class="shop-title-section-doc">
                    <h1><?= htmlspecialchars($shop_name) ?></h1>
                    <span style="font-size:13px;color:#2C7A47;font-weight:700;">✓ Approved & Verified</span><br>
                    <span style="font-size:12px;color:#AA7A50;">📅 Registered: <?= date('d M, Y', strtotime($seller['created_at'] ?? 'now')) ?></span>
                </div>
            </div>
            <div class="info-grid-doc">
                <div class="info-paper-item"><div class="info-paper-label">Full Legal Name</div><div class="info-paper-value"><?= htmlspecialchars($seller['full_name']) ?></div></div>
                <div class="info-paper-item"><div class="info-paper-label">Email Address</div><div class="info-paper-value"><?= htmlspecialchars($seller['email']) ?></div></div>
                <div class="info-paper-item"><div class="info-paper-label">Phone Number</div><div class="info-paper-value"><?= htmlspecialchars($seller['phone']) ?></div></div>
                <div class="info-paper-item"><div class="info-paper-label">Shop Category</div><div class="info-paper-value"><?= htmlspecialchars($seller['shop_category']) ?></div></div>
                <div class="info-paper-item"><div class="info-paper-label">PAN / Tax Number</div><div class="info-paper-value"><?= htmlspecialchars($seller['pan_number']) ?></div></div>
                <div class="info-paper-item"><div class="info-paper-label">Business Address</div><div class="info-paper-value"><?= nl2br(htmlspecialchars($seller['shop_address'])) ?></div></div>
            </div>
            <div style="font-family:'Fraunces',serif;font-size:1.1rem;font-weight:700;margin-bottom:14px;color:#3E2A1F;">📄 Attached Legal Documents</div>
            <div class="doc-paper-grid">
                <div class="a4-doc-card">
                    <div class="doc-label">🇳🇵 Nagarikta — Front</div>
                    <?php if (!empty($seller['nagarikta_front'])): ?>
                        <img src="../publics/uploads/nagarikta/<?= htmlspecialchars($seller['nagarikta_front']) ?>" alt="Nagarikta Front">
                    <?php else: ?><div style="padding:18px;color:#b4875f;font-size:13px;">⚠ Not Uploaded</div><?php endif; ?>
                </div>
                <div class="a4-doc-card">
                    <div class="doc-label">🇳🇵 Nagarikta — Back</div>
                    <?php if (!empty($seller['nagarikta_back'])): ?>
                        <img src="../publics/uploads/nagarikta/<?= htmlspecialchars($seller['nagarikta_back']) ?>" alt="Nagarikta Back">
                    <?php else: ?><div style="padding:18px;color:#b4875f;font-size:13px;">⚠ Not Uploaded</div><?php endif; ?>
                </div>
                <div class="a4-doc-card">
                    <div class="doc-label">📸 Passport Photo</div>
                    <?php if (!empty($seller['passport_photo'])): ?>
                        <img src="../publics/uploads/passport/<?= htmlspecialchars($seller['passport_photo']) ?>" alt="Passport">
                    <?php else: ?><div style="padding:18px;color:#b4875f;font-size:13px;">⚠ Missing</div><?php endif; ?>
                </div>
                <div class="a4-doc-card">
                    <div class="doc-label">🏦 Bank Cheque / Passbook</div>
                    <?php if (!empty($seller['bank_cheque_image'])): ?>
                        <img src="../publics/uploads/cheque/<?= htmlspecialchars($seller['bank_cheque_image']) ?>" alt="Cheque Image" style="max-width:100%;">
                    <?php else: ?><div style="padding:18px;color:#b4875f;font-size:13px;">⚠ Not Uploaded</div><?php endif; ?>
                </div>
            </div>
            <?php if (!empty($seller['shop_description'])): ?>
            <div class="seller-description-box-doc">
                <strong>📝 Shop Description:</strong><br><?= nl2br(htmlspecialchars($seller['shop_description'])) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($stamp_img)): ?><img src="<?= $stamp_img ?>" class="stamp-overlay-doc" alt="Official Stamp"><?php endif; ?>
            <?php if (!empty($signature_img)): ?>
            <div class="signature-wrapper-doc">
                <img src="<?= $signature_img ?>" class="signature-overlay-doc" alt="Admin Signature">
                <div class="signature-caption-doc">Admin Manager Signature</div>
            </div>
            <?php endif; ?>
            <?php if (!empty($admin_remarks)): ?>
            <div class="admin-remark-display-doc"><strong>📌 Admin Note:</strong><br><?= nl2br($admin_remarks) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Lightbox & Chat -->
<div class="img-lightbox" id="imgLightbox" onclick="closeLightbox()"><img id="lightboxImg" src=""></div>
<div class="chat-overlay" id="chatOverlay" onclick="closeDrawer()"></div>

<div class="floating-chat-btn" id="floatingChatBtn" onclick="openDrawer()">
    <svg width="26" height="26" viewBox="0 0 24 24" fill="white"><path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4V4c0-1.1-.9-2-2-2z"/></svg>
    <div class="fcb-badge" id="floatBadge" style="<?= $unread_count > 0 ? '' : 'display:none' ?>"><?= $unread_count ?></div>
</div>

<div class="chat-drawer" id="chatDrawer">
    <div class="drawer-header"><div><h3>💬 Support Chat</h3><small style="color:var(--text-muted);font-size:12px;">SportGhar Admin Team</small></div><button class="close-drawer" onclick="closeDrawer()">✕</button></div>
    <div class="drawer-messages" id="drawerMessages"><div class="empty-chat">Loading messages...</div></div>
    <div class="drawer-typing" id="drawerTyping" style="display:none;">Admin is typing...</div>
    <div class="drawer-input">
        <div class="drawer-input-row">
            <div class="input-group">
                <textarea id="drawerMessageText" rows="1" placeholder="Write a message..."></textarea>
                <label for="fileInput" style="background:#f0e4d8;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;">📎</label>
                <input type="file" id="fileInput" accept="image/*,application/pdf,.doc,.docx,.txt,.zip" style="display:none">
            </div>
            <button class="btn-send-small" id="sendBtn" onclick="sendMessage()">Send</button>
        </div>
        <div id="filePreview" class="attachments-preview" style="display:none;"><span id="fileName"></span><button onclick="clearAttachment()" style="background:none;border:none;cursor:pointer;">✖</button></div>
        <label class="drawer-sms-check"><input type="checkbox" id="drawerSmsCheck"> 📲 Also send as SMS</label>
    </div>
</div>

<script>
// ─── PDF MODAL ───
function openPdfModal(url) {
    document.getElementById('pdfIframe').src = url;
    document.getElementById('pdfModal').classList.add('show');
}
function closePdfModal() {
    document.getElementById('pdfIframe').src = '';
    document.getElementById('pdfModal').classList.remove('show');
}

// ─── OFFICIAL DOC MODAL ───
const docModal = document.getElementById('officialDocModal');
document.getElementById('openOfficialDocBtn')?.addEventListener('click', () => { docModal.classList.add('show'); document.body.style.overflow='hidden'; });
document.getElementById('closeOfficialDocModal')?.addEventListener('click', () => { docModal.classList.remove('show'); document.body.style.overflow=''; });
window.addEventListener('click', e => { if (e.target === docModal) { docModal.classList.remove('show'); document.body.style.overflow=''; } });

// ─── COUNTER ANIMATION ───
document.querySelectorAll('.counter').forEach(counter => {
    const target = +counter.dataset.target;
    let current = 0;
    const step = () => {
        current = Math.min(current + Math.ceil(target / 35), target);
        counter.textContent = current;
        if (current < target) setTimeout(step, 25);
    };
    step();
});

// ─── CHART ───
const ctx = document.getElementById('jerseyChart')?.getContext('2d');
if (ctx) {
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{ label: 'Jerseys', data: <?= json_encode($chartData) ?>, backgroundColor: 'rgba(192,57,43,0.75)', borderRadius: 10, borderColor: '#C0392B', borderWidth: 1.5 }]
        },
        options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
}

// ======================= 3D GLOBE (EARTH) WITH CLICKABLE MARKERS & AUTO-ROTATION =======================
if (document.getElementById('globeCanvas')) {
    (async function() {
        const THREE = await import('three');
        const { OrbitControls } = await import('three/addons/controls/OrbitControls.js');
        const { CSS2DRenderer, CSS2DObject } = await import('three/addons/renderers/CSS2DRenderer.js');
        const { Raycaster } = THREE;

        const canvas = document.getElementById('globeCanvas');
        const width = canvas.parentElement.clientWidth;
        const height = canvas.parentElement.clientHeight;
        
        // Scene, Camera, Renderers
        const scene = new THREE.Scene();
        scene.background = new THREE.Color(0x03060c);
        
        const camera = new THREE.PerspectiveCamera(45, width/height, 0.1, 1000);
        camera.position.set(0, 0, 2.8);
        
        const renderer = new THREE.WebGLRenderer({ canvas, alpha: false });
        renderer.setSize(width, height);
        renderer.setPixelRatio(window.devicePixelRatio);
        
        const labelRenderer = new CSS2DRenderer();
        labelRenderer.setSize(width, height);
        labelRenderer.domElement.style.position = 'absolute';
        labelRenderer.domElement.style.top = '0px';
        labelRenderer.domElement.style.left = '0px';
        labelRenderer.domElement.style.pointerEvents = 'none';
        canvas.parentElement.appendChild(labelRenderer.domElement);
        
        // Controls with auto-rotate
        const controls = new OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.05;
        controls.autoRotate = true;
        controls.autoRotateSpeed = 1.0;
        controls.enableZoom = true;
        controls.zoomSpeed = 1.2;
        controls.rotateSpeed = 1.0;
        controls.enablePan = false;
        controls.maxDistance = 4.2;
        controls.minDistance = 1.3;
        
        // Earth sphere
        const geometry = new THREE.SphereGeometry(1, 128, 128);
        const textureLoader = new THREE.TextureLoader();
        const earthTexture = textureLoader.load('https://threejs.org/examples/textures/planets/earth_atmos_2048.jpg');
        const material = new THREE.MeshStandardMaterial({ map: earthTexture, roughness: 0.5, metalness: 0.1 });
        const earth = new THREE.Mesh(geometry, material);
        scene.add(earth);
        
        // Starfield
        const starGeometry = new THREE.BufferGeometry();
        const starCount = 3000;
        const starPositions = new Float32Array(starCount * 3);
        for (let i = 0; i < starCount; i++) {
            starPositions[i*3] = (Math.random() - 0.5) * 1000;
            starPositions[i*3+1] = (Math.random() - 0.5) * 1000;
            starPositions[i*3+2] = (Math.random() - 0.5) * 200 - 50;
        }
        starGeometry.setAttribute('position', new THREE.BufferAttribute(starPositions, 3));
        const starMaterial = new THREE.PointsMaterial({ color: 0xffffff, size: 0.28, transparent: true, opacity: 0.8 });
        const stars = new THREE.Points(starGeometry, starMaterial);
        scene.add(stars);
        
        const starGlowGeo = new THREE.BufferGeometry();
        const glowCount = 1200;
        const glowPositions = new Float32Array(glowCount * 3);
        for (let i = 0; i < glowCount; i++) {
            glowPositions[i*3] = (Math.random() - 0.5) * 900;
            glowPositions[i*3+1] = (Math.random() - 0.5) * 900;
            glowPositions[i*3+2] = (Math.random() - 0.5) * 180 - 40;
        }
        starGlowGeo.setAttribute('position', new THREE.BufferAttribute(glowPositions, 3));
        const glowMaterial = new THREE.PointsMaterial({ color: 0xffdd99, size: 0.18, transparent: true, opacity: 0.6 });
        const starGlow = new THREE.Points(starGlowGeo, glowMaterial);
        scene.add(starGlow);
        
        // Lighting
        const ambientLight = new THREE.AmbientLight(0x404060);
        scene.add(ambientLight);
        const dirLight = new THREE.DirectionalLight(0xffffff, 1.2);
        dirLight.position.set(5, 3, 5);
        scene.add(dirLight);
        const backLight = new THREE.PointLight(0x4466cc, 0.4);
        backLight.position.set(-2, -1, -3);
        scene.add(backLight);
        
        function latLngToVector3(lat, lng, radius = 1.02) {
            const phi = (90 - lat) * Math.PI / 180;
            const theta = lng * Math.PI / 180;
            const x = radius * Math.sin(phi) * Math.cos(theta);
            const y = radius * Math.cos(phi);
            const z = radius * Math.sin(phi) * Math.sin(theta);
            return new THREE.Vector3(x, y, z);
        }
        
        const locations = <?= json_encode($location_sales) ?>;
        const markers = [];
        
        locations.forEach(loc => {
            const pos = latLngToVector3(loc.lat, loc.lng, 1.018);
            const markerGeo = new THREE.SphereGeometry(0.024, 24, 24);
            const markerMat = new THREE.MeshStandardMaterial({ color: 0xff4d4d, emissive: 0x992222, emissiveIntensity: 0.8 });
            const marker = new THREE.Mesh(markerGeo, markerMat);
            marker.userData = { location: loc };
            marker.position.copy(pos);
            scene.add(marker);
            markers.push(marker);
            
            const haloGeo = new THREE.SphereGeometry(0.042, 16, 16);
            const haloMat = new THREE.MeshBasicMaterial({ color: 0xff6666, transparent: true, opacity: 0.5 });
            const halo = new THREE.Mesh(haloGeo, haloMat);
            halo.position.copy(pos);
            scene.add(halo);
            markers.push(halo);
            
            const div = document.createElement('div');
            div.textContent = `${loc.city} 🏆`;
            div.style.color = '#fff';
            div.style.fontSize = '10px';
            div.style.fontWeight = 'bold';
            div.style.backgroundColor = 'rgba(192,57,43,0.8)';
            div.style.padding = '2px 8px';
            div.style.borderRadius = '20px';
            div.style.border = '1px solid rgba(255,215,0,0.7)';
            div.style.whiteSpace = 'nowrap';
            div.style.fontFamily = 'Plus Jakarta Sans, sans-serif';
            div.style.backdropFilter = 'blur(4px)';
            div.style.pointerEvents = 'none';
            const labelObj = new CSS2DObject(div);
            labelObj.position.copy(latLngToVector3(loc.lat, loc.lng, 1.10));
            scene.add(labelObj);
        });
        
        const cloudGeometry = new THREE.SphereGeometry(1.008, 128, 128);
        const cloudTexture = textureLoader.load('https://threejs.org/examples/textures/planets/earth_clouds_1024.png');
        const cloudMaterial = new THREE.MeshPhongMaterial({ map: cloudTexture, transparent: true, opacity: 0.12 });
        const clouds = new THREE.Mesh(cloudGeometry, cloudMaterial);
        scene.add(clouds);
        
        const raycaster = new THREE.Raycaster();
        const mouse = new THREE.Vector2();
        
        function onCanvasClick(event) {
            const rect = canvas.getBoundingClientRect();
            mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
            mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
            raycaster.setFromCamera(mouse, camera);
            const intersects = raycaster.intersectObjects(markers);
            if (intersects.length > 0) {
                const hit = intersects[0].object;
                const locData = hit.userData.location;
                if (locData) {
                    controls.autoRotate = false;
                    const targetPos = hit.position.clone().normalize().multiplyScalar(1.2);
                    const startPos = camera.position.clone();
                    const startTarget = controls.target.clone();
                    const endTarget = hit.position.clone().normalize().multiplyScalar(0.2);
                    const duration = 800;
                    const startTime = performance.now();
                    function animateZoom(now) {
                        let elapsed = now - startTime;
                        let t = Math.min(1, elapsed / duration);
                        t = 1 - Math.pow(1 - t, 3);
                        camera.position.lerpVectors(startPos, targetPos, t);
                        controls.target.lerpVectors(startTarget, endTarget, t);
                        controls.update();
                        if (t < 1) requestAnimationFrame(animateZoom);
                        else {
                            const panel = document.getElementById('globeInfoPanel');
                            panel.innerHTML = `<div class="globe-info-text">📍 <strong>${locData.city}, ${locData.country}</strong><br>
                                              🧥 Jersey: ${locData.jersey} | 🛒 Sales: ${locData.sales}<br>
                                              ✨ ${locData.fact}</div>
                                              <button id="resetGlobeViewBtn" style="background:var(--primary);color:white;border:none;padding:5px 12px;border-radius:40px;cursor:pointer;">Reset View</button>`;
                            const resetBtn = document.getElementById('resetGlobeViewBtn');
                            if (resetBtn) resetBtn.onclick = () => {
                                camera.position.set(0, 0, 2.8);
                                controls.target.set(0, 0, 0);
                                controls.autoRotate = true;
                                controls.update();
                                document.getElementById('globeInfoPanel').innerHTML = `<div class="globe-info-text">✨ <strong>Click on any glowing marker</strong> to see detailed sales info and zoom in.</div>
                                                                                    <div class="globe-note" style="margin-top:0;">
                                                                                        <span><i>📍</i> 10+ active hotspots</span>
                                                                                        <span><i>🔄</i> Auto‑rotating Earth</span>
                                                                                    </div>`;
                            };
                            setTimeout(() => { if (controls.autoRotate === false) controls.autoRotate = true; }, 5000);
                        }
                    }
                    requestAnimationFrame(animateZoom);
                }
            }
        }
        canvas.addEventListener('click', onCanvasClick);
        
        const rotateLeftBtn = document.getElementById('globeRotateLeft');
        const rotateRightBtn = document.getElementById('globeRotateRight');
        const rotateUpBtn = document.getElementById('globeRotateUp');
        const rotateDownBtn = document.getElementById('globeRotateDown');
        const zoomInBtn = document.getElementById('globeZoomIn');
        const zoomOutBtn = document.getElementById('globeZoomOut');
        const resetBtn = document.getElementById('globeReset');
        
        function tempDisableAutoRotate() {
            controls.autoRotate = false;
            setTimeout(() => { controls.autoRotate = true; }, 2000);
        }
        if (rotateLeftBtn) rotateLeftBtn.addEventListener('click', () => { tempDisableAutoRotate(); controls.object.rotation.y -= 0.12; controls.update(); });
        if (rotateRightBtn) rotateRightBtn.addEventListener('click', () => { tempDisableAutoRotate(); controls.object.rotation.y += 0.12; controls.update(); });
        if (rotateUpBtn) rotateUpBtn.addEventListener('click', () => { tempDisableAutoRotate(); controls.object.rotation.x -= 0.08; controls.update(); });
        if (rotateDownBtn) rotateDownBtn.addEventListener('click', () => { tempDisableAutoRotate(); controls.object.rotation.x += 0.08; controls.update(); });
        if (zoomInBtn) zoomInBtn.addEventListener('click', () => { if (camera.zoom < 2) camera.zoom += 0.2; camera.updateProjectionMatrix(); });
        if (zoomOutBtn) zoomOutBtn.addEventListener('click', () => { if (camera.zoom > 0.6) camera.zoom -= 0.2; camera.updateProjectionMatrix(); });
        if (resetBtn) resetBtn.addEventListener('click', () => {
            controls.autoRotate = false;
            camera.position.set(0, 0, 2.8);
            camera.zoom = 1;
            camera.updateProjectionMatrix();
            controls.target.set(0, 0, 0);
            controls.object.rotation.set(0, 0, 0);
            controls.update();
            setTimeout(() => { controls.autoRotate = true; }, 500);
            document.getElementById('globeInfoPanel').innerHTML = `<div class="globe-info-text">✨ <strong>Click on any glowing marker</strong> to see detailed sales info and zoom in.</div>
                                                                  <div class="globe-note" style="margin-top:0;">
                                                                      <span><i>📍</i> 10+ active hotspots</span>
                                                                      <span><i>🔄</i> Auto‑rotating Earth</span>
                                                                  </div>`;
        });
        
        function animate() {
            requestAnimationFrame(animate);
            controls.update();
            stars.rotation.y += 0.0003;
            starGlow.rotation.x += 0.0002;
            clouds.rotation.y += 0.0008;
            renderer.render(scene, camera);
            labelRenderer.render(scene, camera);
        }
        animate();
        
        window.addEventListener('resize', () => {
            const w = canvas.parentElement.clientWidth;
            const h = canvas.parentElement.clientHeight;
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            renderer.setSize(w, h);
            labelRenderer.setSize(w, h);
        });
    })();
}

// ───────────── CHAT LOGIC (fully functional) ─────────────
const BASE_URL = window.location.pathname.split('?')[0];
let lastMessageId = 0, pollingInterval = null, typingPollInt = null, typingTimeout = null, selectedFile = null;
const drawer = document.getElementById('chatDrawer'), overlay = document.getElementById('chatOverlay'), msgContainer = document.getElementById('drawerMessages');
const typingDiv = document.getElementById('drawerTyping'), msgInput = document.getElementById('drawerMessageText'), smsCheck = document.getElementById('drawerSmsCheck'), sendBtn = document.getElementById('sendBtn');
const floatBadge = document.getElementById('floatBadge'), fileInput = document.getElementById('fileInput'), filePreview = document.getElementById('filePreview'), fileNameSpan = document.getElementById('fileName');

function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function scrollBottom() { msgContainer.scrollTop = msgContainer.scrollHeight; }
function openLightbox(src) { document.getElementById('lightboxImg').src=src; document.getElementById('imgLightbox').classList.add('show'); }
function closeLightbox() { document.getElementById('imgLightbox').classList.remove('show'); }
function clearAttachment() { selectedFile=null; fileInput.value=''; filePreview.style.display='none'; }
fileInput.addEventListener('change', function(e) { if (e.target.files.length) { const f=e.target.files[0]; if (f.size>5*1024*1024){alert('Max 5MB');fileInput.value='';return;} selectedFile=f; fileNameSpan.textContent=f.name; filePreview.style.display='flex'; } else clearAttachment(); });
async function openDrawer() { drawer.classList.add('open'); overlay.classList.add('show'); floatBadge.style.display='none'; lastMessageId=0; msgContainer.innerHTML='<div class="empty-chat">Loading…</div>'; await fetchMessages(true); startPolling(); msgInput.focus(); }
function closeDrawer() { drawer.classList.remove('open'); overlay.classList.remove('show'); stopPolling(); updateTyping(false); clearAttachment(); }
function startPolling() { stopPolling(); pollingInterval=setInterval(()=>fetchMessages(false),3000); typingPollInt=setInterval(checkTyping,2500); }
function stopPolling() { clearInterval(pollingInterval); clearInterval(typingPollInt); }
async function fetchMessages(isInitial) { try { const res=await fetch(`${BASE_URL}?ajax=get_messages&last_id=${lastMessageId}`); const data=await res.json(); if (data.error) throw new Error(data.error); if (!data.messages?.length){if(isInitial)msgContainer.innerHTML='<div class="empty-chat">No messages yet. Say hello! 👋</div>';return;} if(isInitial)msgContainer.innerHTML=''; for(let msg of data.messages){if(!msgContainer.querySelector(`[data-msg-id="${msg.id}"]`)){appendMessage(msg);if(msg.id>lastMessageId)lastMessageId=msg.id;}} scrollBottom(); } catch(e){console.error(e);if(isInitial)msgContainer.innerHTML='<div class="empty-chat">❌ Connection error.</div>';} }
function isImageUrl(url,ft){if(!url)return false;if(ft?.startsWith('image/'))return true;const ext=url.split('.').pop().split('?')[0].toLowerCase();return['jpg','jpeg','png','gif','webp','bmp','svg'].includes(ext);}
function buildFileHTML(fu,fn,ft){if(!fu)return'';const name=fn||fu.split('/').pop();if(isImageUrl(fu,ft))return`<div><img src="${esc(fu)}" class="chat-img" onclick="openLightbox('${esc(fu)}')" onerror="this.parentElement.innerHTML='<a href=\\'${esc(fu)}\\' target=\\'_blank\\' class=\\'file-attachment\\'>📎 ${esc(name)}</a>'"></div>`;else return`<div><a href="${esc(fu)}" target="_blank" class="file-attachment">📎 ${esc(name)}</a></div>`;}
function appendMessage(msg){const old=msgContainer.querySelector('.empty-chat');if(old)old.remove();const wrap=document.createElement('div');wrap.className=`drawer-message-item ${msg.is_admin?'admin':'seller'}`;wrap.dataset.msgId=msg.id;const bubble=document.createElement('div');bubble.className='drawer-bubble';let content='';if(msg.message)content+=`<div>${esc(msg.message).replace(/\n/g,'<br>')}</div>`;content+=buildFileHTML(msg.file_url,msg.file_name,msg.file_type);bubble.innerHTML=content;const meta=document.createElement('div');meta.className='drawer-meta';let mh=`<span>${msg.is_admin?'🛡️ Admin':'👤 You'}</span><span>${esc(msg.created_at)}</span>`;if(msg.type==='sms')mh+=`<span>📱 SMS</span>`;if(!msg.is_admin&&msg.is_seen)mh+=`<span style="color:#5aad7a;">✓✓</span>`;if(!msg.is_admin&&msg.can_edit_delete)mh+=`<div style="position:relative;display:inline-block;"><button class="menu-btn" onclick="toggleMenu(event,this)">⋯</button><div class="dropdown-menu"><a href="#" class="edit-msg" data-id="${msg.id}" data-text="${esc(msg.message)}">✏️ Edit</a><a href="#" class="delete-msg" data-id="${msg.id}">🗑 Delete</a></div></div>`;meta.innerHTML=mh;wrap.appendChild(bubble);wrap.appendChild(meta);msgContainer.appendChild(wrap);bindEditDelete();}
async function sendMessage(){const message=msgInput.value.trim();if(!message&&!selectedFile)return;sendBtn.disabled=true;sendBtn.textContent='…';const fd=new FormData();fd.append('message',message);if(smsCheck.checked)fd.append('send_sms','1');if(selectedFile)fd.append('attachment',selectedFile);try{const res=await fetch(`${BASE_URL}?ajax=send_message`,{method:'POST',body:fd});const data=await res.json();if(data.success){const nm={id:data.message_id,message,file_url:data.file_url,file_name:data.file_name,file_type:data.file_type,is_admin:false,type:smsCheck.checked?'sms':'message',created_at:new Date().toLocaleString('en-US',{day:'numeric',month:'short',hour:'numeric',minute:'2-digit',hour12:true}),is_seen:false,can_edit_delete:true};appendMessage(nm);scrollBottom();lastMessageId=data.message_id;msgInput.value='';smsCheck.checked=false;clearAttachment();}else alert('Error: '+(data.error||'Unknown'));}catch(e){alert('Network error');}finally{sendBtn.disabled=false;sendBtn.textContent='Send';}}
msgInput.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendMessage();}});
msgInput.addEventListener('input',function(){this.style.height='auto';this.style.height=Math.min(this.scrollHeight,100)+'px';updateTyping(true);clearTimeout(typingTimeout);typingTimeout=setTimeout(()=>updateTyping(false),2000);});
async function checkTyping(){try{const res=await fetch(`${BASE_URL}?ajax=get_typing`);const data=await res.json();typingDiv.style.display=data.typing?'block':'none';}catch(e){}}
async function updateTyping(t){try{const fd=new FormData();fd.append('typing',t?1:0);await fetch(`${BASE_URL}?ajax=update_typing`,{method:'POST',body:fd});}catch(e){}}
async function editMessage(id,nt){const fd=new FormData();fd.append('message_id',id);fd.append('message',nt);try{const res=await fetch(`${BASE_URL}?ajax=edit_message`,{method:'POST',body:fd});const data=await res.json();if(data.success){const bubble=msgContainer.querySelector(`[data-msg-id="${id}"] .drawer-bubble`);if(bubble){const fd2=bubble.querySelector('.file-attachment,.chat-img')?.parentElement;bubble.innerHTML=esc(nt).replace(/\n/g,'<br>');if(fd2)bubble.appendChild(fd2);}}else alert('Cannot edit');}catch(e){alert('Network error');}}
async function deleteMessage(id){if(!confirm('Delete permanently?'))return;const fd=new FormData();fd.append('message_id',id);try{const res=await fetch(`${BASE_URL}?ajax=delete_message`,{method:'POST',body:fd});const data=await res.json();if(data.success){const el=msgContainer.querySelector(`[data-msg-id="${id}"]`);if(el)el.remove();if(!msgContainer.querySelector('.drawer-message-item'))msgContainer.innerHTML='<div class="empty-chat">No messages yet. Say hello! 👋</div>';}else alert('Cannot delete');}catch(e){alert('Network error');}}
function bindEditDelete(){document.querySelectorAll('.edit-msg').forEach(a=>{a.onclick=function(e){e.preventDefault();const nt=prompt('Edit message:',this.dataset.text);if(nt&&nt.trim()&&nt!==this.dataset.text)editMessage(this.dataset.id,nt.trim());};});document.querySelectorAll('.delete-msg').forEach(a=>{a.onclick=function(e){e.preventDefault();deleteMessage(this.dataset.id);};});}
window.toggleMenu=function(e,btn){e.stopPropagation();document.querySelectorAll('.dropdown-menu.show').forEach(m=>{if(m!==btn.nextElementSibling)m.classList.remove('show');});btn.nextElementSibling.classList.toggle('show');};
document.addEventListener('click',()=>document.querySelectorAll('.dropdown-menu.show').forEach(m=>m.classList.remove('show')));
</script>
</body>
</html>