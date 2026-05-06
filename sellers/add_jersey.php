<?php
ob_start();
session_start();
require_once "../databases/db.php";

$conn = getDB();
if (!$conn) die("Database connection failed.");

/* ALL PHP LOGIC */

require_once "sidenav.php";

if (!$conn) die("Database connection failed.");

/* ── SESSION CHECK ── */
if (!isset($_SESSION['seller_id'])) {
    header("Location: ../sellers/login.php");
    exit;
}

$seller_id = (int)$_SESSION['seller_id'];

/* ── UPLOAD DIR ── */
$uploadDir = "../uploads/";
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

/* ── SELLER INFO ── */
$stmt = $conn->prepare("SELECT full_name, shop_name, status FROM sellers WHERE id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$seller = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$seller) { session_destroy(); header("Location: register.php"); exit; }

$seller_name = $seller['full_name'];
$shop_name   = $seller['shop_name'];
$status      = $seller['status'] ?? 'pending';
$_SESSION['status'] = $status;

$name_parts = explode(" ", trim($seller_name));
$first_name = $name_parts[0];
$initials   = strtoupper(substr($name_parts[0], 0, 1));
if (count($name_parts) > 1) $initials .= strtoupper(substr(end($name_parts), 0, 1));

/* ── CREATE NEW SHOP ── */
$shop_created = false;
if (isset($_POST['create_shop']) && !empty(trim($_POST['new_shop_name']))) {
    $new_shop = trim($_POST['new_shop_name']);
    $stmt = $conn->prepare("INSERT INTO shops (seller_id, shop_name) VALUES (?, ?)");
    $stmt->bind_param("is", $seller_id, $new_shop);
    if ($stmt->execute()) {
        $shop_created = true;
    }
    $stmt->close();
    if ($shop_created) {
        header("Location: add_jersey.php?shop_added=1");
        exit;
    }
}

/* ── FETCH SHOPS (for dropdown) ── */
$shops = [];
$stmt = $conn->prepare("SELECT id, shop_name FROM shops WHERE seller_id = ? ORDER BY shop_name");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$shops_result = $stmt->get_result();
while ($row = $shops_result->fetch_assoc()) {
    $shops[] = $row;
}
$stmt->close();

/* ── DELETE JERSEY ── */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM jerseys WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $id, $seller_id);
    $stmt->execute();
    $stmt->close();
    header("Location: add_jersey.php");
    exit;
}

/* ── FETCH EDIT DATA ── */
$editData = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM jerseys WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $id, $seller_id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* ── ADD / UPDATE JERSEY (with rating, stock, etc.) ── */
if (isset($_POST['submit'])) {
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price       = floatval($_POST['price']);
    $discount    = floatval($_POST['discount']);
    $type        = trim($_POST['jersey_type']);
    $sport_type  = trim($_POST['sport_type']);
    $sell        = trim($_POST['sell']);
    $sizes       = isset($_POST['sizes']) ? implode(",", $_POST['sizes']) : "";
    $is_top      = isset($_POST['is_top']) ? 1 : 0;
    $shop_id     = !empty($_POST['shop_id']) ? (int)$_POST['shop_id'] : null;
    $stock       = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    $rating      = isset($_POST['rating']) ? floatval($_POST['rating']) : 0;

    $imagePath = $editData['image'] ?? "";
    if (!empty($_FILES['image']['name'])) {
        $imgName = time() . "_" . basename($_FILES['image']['name']);
        $target  = $uploadDir . $imgName;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) $imagePath = $target;
    }

    $extraImages = [];
    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['name'] as $k => $name) {
            if (!empty($name)) {
                $n = time() . "_" . basename($name);
                $p = $uploadDir . $n;
                if (move_uploaded_file($_FILES['images']['tmp_name'][$k], $p)) $extraImages[] = $p;
            }
        }
    }
    $extraImagesStr = !empty($extraImages) ? implode(",", $extraImages) : ($editData['images'] ?? "");

    if (!empty($_POST['id'])) { // UPDATE
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE jerseys SET title=?, description=?, image=?, images=?, price=?, discount=?, jersey_type=?, sport_type=?, sizes=?, sell=?, is_top=?, stock=?, rating=?, shop_id=? WHERE id=? AND seller_id=?");
        $stmt->bind_param("ssssddssssiiiisi", $title,$description,$imagePath,$extraImagesStr,$price,$discount,$type,$sport_type,$sizes,$sell,$is_top,$stock,$rating,$shop_id,$id,$seller_id);
        $stmt->execute(); $stmt->close();
        header("Location: add_jersey.php?success=updated"); exit;
    } else { // INSERT
        $stmt = $conn->prepare("INSERT INTO jerseys (title, description, image, images, price, discount, jersey_type, sport_type, sizes, sell, is_top, stock, rating, seller_id, shop_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssssddssssiiisii", $title,$description,$imagePath,$extraImagesStr,$price,$discount,$type,$sport_type,$sizes,$sell,$is_top,$stock,$rating,$seller_id,$shop_id);
        $stmt->execute(); $stmt->close();
        header("Location: add_jersey.php?success=added"); exit;
    }
}

/* ── FETCH ALL JERSEYS (JOIN with shop name) ── */
$stmt = $conn->prepare("SELECT j.*, s.shop_name FROM jerseys j LEFT JOIN shops s ON j.shop_id = s.id WHERE j.seller_id = ? ORDER BY j.id DESC");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

/* ── STATS ── */
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM jerseys WHERE seller_id = ?");
$stmt->bind_param("i", $seller_id); $stmt->execute();
$total_jerseys = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0; $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM jerseys WHERE seller_id = ? AND sell='Yes'");
$stmt->bind_param("i", $seller_id); $stmt->execute();
$total_sell = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0; $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM jerseys WHERE seller_id = ? AND is_top=1");
$stmt->bind_param("i", $seller_id); $stmt->execute();
$total_top = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0; $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM jerseys WHERE seller_id = ? AND sell='Yes' AND stock <= 5 AND stock > 0");
$stmt->bind_param("i", $seller_id); $stmt->execute();
$low_stock_count = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0; $stmt->close();

$stmt = $conn->prepare("SELECT SUM(stock) as total FROM jerseys WHERE seller_id = ? AND sell='Yes'");
$stmt->bind_param("i", $seller_id); $stmt->execute();
$total_stock = $stmt->get_result()->fetch_assoc()['total'] ?? 0; $stmt->close();

// Average rating for seller (optional, not used but could be)
$stmt = $conn->prepare("SELECT AVG(rating) as avg_rating FROM jerseys WHERE seller_id = ? AND rating > 0");
$stmt->bind_param("i", $seller_id); $stmt->execute();
$avg_rating = $stmt->get_result()->fetch_assoc()['avg_rating'] ?? 0;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $editData ? 'Edit Jersey' : 'Add Jersey' ?> | SportsBazaar</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}

:root {
    --brand:      #C94B01;
    --brand-dk:   #a33a00;
    --brand-lt:   #fff2ec;
    --bg:         #f0f2f5;
    --surface:    #ffffff;
    --border:     #e2e6ea;
    --text:       #1a1a2e;
    --muted:      #6c757d;
    --green:      #1a8a4a;
    --green-bg:   #edfaf3;
    --amber:      #b86a00;
    --amber-bg:   #fff7e6;
    --red:        #dc2626;
    --red-bg:     #fef2f2;
    --star:       #fbbf24;
    --sidebar-w:  245px;
    --top-h:      64px;
}

body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

.main {
    margin-left: var(--sidebar-w);
    margin-top: var(--top-h);
    padding: 28px 26px;
    min-height: calc(100vh - var(--top-h));
}

.mini-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}
.mini-stat {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 16px 18px;
    position: relative;
    overflow: hidden;
}
.mini-stat::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    border-radius: 14px 14px 0 0;
}
.ms-brand::before { background: var(--brand); }
.ms-green::before { background: var(--green); }
.ms-amber::before { background: var(--amber); }
.ms-red::before { background: var(--red); }
.ms-icon {
    position: absolute;
    top: 12px; right: 14px;
    font-size: 18px;
    opacity: .12;
}
.ms-val {
    font-family: 'Sora', sans-serif;
    font-size: 26px;
    font-weight: 800;
    color: var(--text);
    line-height: 1;
}
.ms-lbl {
    font-size: 11.5px;
    color: var(--muted);
    font-weight: 500;
    margin-top: 3px;
}

.toast {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--green-bg);
    border: 1px solid #a7e8c5;
    border-radius: 12px;
    padding: 12px 18px;
    margin-bottom: 20px;
    font-size: 13.5px;
    color: var(--green);
    font-weight: 500;
}

.page-grid {
    display: grid;
    grid-template-columns: 390px 1fr;
    gap: 22px;
    align-items: start;
}

.form-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow: hidden;
    position: sticky;
    top: calc(var(--top-h) + 20px);
}
.form-card-head {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(135deg, var(--brand-lt), #fff);
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.form-card-head .title-row {
    display: flex;
    align-items: center;
    gap: 10px;
}
.form-card-head .shop-badge {
    background: rgba(0,0,0,0.05);
    font-size: 12px;
    font-weight: 500;
    padding: 4px 10px;
    border-radius: 30px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    width: fit-content;
    color: var(--brand);
}
.form-card-head h2 {
    font-family: 'Sora', sans-serif;
    font-size: 15px;
    font-weight: 700;
    color: var(--brand);
}
.form-body {
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.form-group label {
    font-size: 11.5px;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .04em;
}
.form-body input[type=text],
.form-body input[type=number],
.form-body textarea,
.form-body select {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid var(--border);
    border-radius: 9px;
    font-family: 'DM Sans', sans-serif;
    font-size: 13.5px;
    color: var(--text);
    background: var(--bg);
    outline: none;
    transition: border-color .15s, background .15s;
}
.form-body input:focus,
.form-body textarea:focus,
.form-body select:focus {
    border-color: var(--brand);
    background: #fff;
}
.form-body textarea { resize: vertical; min-height: 70px; }
.two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.shop-group {
    display: flex;
    gap: 6px;
    align-items: center;
}
.shop-group select {
    flex: 1;
}
.shop-group button {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 9px;
    width: 36px;
    height: 36px;
    font-size: 20px;
    font-weight: bold;
    cursor: pointer;
    color: var(--brand);
    transition: all .1s;
}
.shop-group button:hover {
    background: var(--brand-lt);
    border-color: var(--brand);
}

.size-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.size-chip input { display: none; }
.size-chip label {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 38px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    color: var(--muted);
    cursor: pointer;
    transition: all .15s;
    text-transform: uppercase;
}
.size-chip input:checked + label {
    border-color: var(--brand);
    background: var(--brand-lt);
    color: var(--brand);
}

.toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 9px;
    padding: 10px 14px;
}
.toggle-row span {
    font-size: 13px;
    font-weight: 500;
    color: var(--text);
}
.toggle-switch {
    position: relative;
    width: 40px;
    height: 22px;
}
.toggle-switch input { display: none; }
.toggle-switch .slider {
    position: absolute;
    inset: 0;
    background: #ddd;
    border-radius: 22px;
    cursor: pointer;
    transition: .2s;
}
.toggle-switch .slider::before {
    content: '';
    position: absolute;
    left: 3px;
    top: 3px;
    width: 16px;
    height: 16px;
    background: #fff;
    border-radius: 50%;
    transition: .2s;
}
.toggle-switch input:checked + .slider { background: var(--brand); }
.toggle-switch input:checked + .slider::before { transform: translateX(18px); }

.file-zone {
    border: 1.5px dashed var(--border);
    border-radius: 9px;
    padding: 14px;
    text-align: center;
    cursor: pointer;
    transition: border-color .15s, background .15s;
    position: relative;
}
.file-zone:hover {
    border-color: var(--brand);
    background: var(--brand-lt);
}
.file-zone input {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}
.file-zone p {
    font-size: 12.5px;
    color: var(--muted);
    margin-top: 4px;
}
.file-zone .fz-icon { font-size: 20px; }
.current-img {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    background: var(--bg);
    border-radius: 8px;
    border: 1px solid var(--border);
    font-size: 12px;
    color: var(--muted);
    margin-bottom: 6px;
}
.current-img img {
    width: 44px;
    height: 44px;
    object-fit: cover;
    border-radius: 6px;
}

.btn-submit {
    width: 100%;
    padding: 11px;
    background: var(--brand);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-family: 'Sora', sans-serif;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: background .15s, transform .1s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 4px;
}
.btn-submit:hover {
    background: var(--brand-dk);
    transform: translateY(-1px);
}
.btn-cancel {
    width: 100%;
    padding: 9px;
    background: var(--bg);
    color: var(--muted);
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: background .15s;
    text-decoration: none;
    text-align: center;
    display: block;
    margin-top: 6px;
}
.btn-cancel:hover {
    background: var(--border);
    color: var(--text);
}

.table-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow: hidden;
}
.table-head {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}
.table-head h3 {
    font-family: 'Sora', sans-serif;
    font-size: 15px;
    font-weight: 700;
    color: var(--text);
}
.table-head h3 small {
    font-family: 'DM Sans', sans-serif;
    font-size: 12px;
    font-weight: 400;
    color: var(--muted);
    margin-left: 6px;
}
.tbl-search {
    display: flex;
    align-items: center;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 9px;
    padding: 0 10px;
    gap: 6px;
    height: 33px;
    width: 180px;
    transition: border-color .15s, width .2s;
}
.tbl-search:focus-within {
    border-color: var(--brand);
    width: 210px;
}
.tbl-search svg { color: var(--muted); flex-shrink: 0; }
.tbl-search input {
    border: none;
    background: transparent;
    font-size: 12.5px;
    color: var(--text);
    outline: none;
    width: 100%;
    font-family: 'DM Sans', sans-serif;
}
.table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}
thead th {
    background: #f7f9fb;
    padding: 10px 14px;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--muted);
    text-align: left;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
tbody tr { border-bottom: 1px solid var(--border); transition: background .12s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #fafbfc; }
td {
    padding: 11px 14px;
    font-size: 13px;
    color: var(--text);
    vertical-align: middle;
}
.jersey-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}
.jersey-thumb {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    object-fit: cover;
    background: var(--bg);
    border: 1px solid var(--border);
    flex-shrink: 0;
}
.jname {
    font-weight: 600;
    font-size: 13px;
    line-height: 1.2;
}
.jtype {
    font-size: 11px;
    color: var(--muted);
    margin-top: 1px;
}
.rating-stars {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    color: var(--star);
    font-size: 12px;
}
.rating-stars i {
    margin-right: 1px;
}
.badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 10.5px;
    font-weight: 700;
    white-space: nowrap;
}
.badge::before {
    content: '';
    width: 5px;
    height: 5px;
    border-radius: 50%;
    flex-shrink: 0;
}
.b-sell {
    background: var(--green-bg);
    color: var(--green);
}
.b-sell::before { background: var(--green); }
.b-nosell {
    background: #f1f5f9;
    color: #64748b;
}
.b-nosell::before { background: #94a3b8; }
.b-top {
    background: var(--amber-bg);
    color: var(--amber);
}
.b-top::before { background: var(--amber); }
.b-sport {
    background: #EEF5FD;
    color: #1a6bb5;
}
.b-sport::before { background: #3b82f6; }
.stock-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}
.stock-normal {
    background: #e6f7e6;
    color: #2e7d32;
}
.stock-low {
    background: var(--red-bg);
    color: var(--red);
}
.stock-out {
    background: #f1f5f9;
    color: #64748b;
}
.shop-badge {
    background: #f0f0fc;
    color: #4c51bf;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    white-space: nowrap;
}
.price-col {
    font-weight: 700;
    color: var(--brand);
    font-family: 'Sora', sans-serif;
    font-size: 13.5px;
}
.disc-col {
    font-size: 11px;
    color: var(--muted);
    text-decoration: line-through;
}
.row-actions {
    display: flex;
    gap: 5px;
}
.btn-row {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid transparent;
    transition: opacity .15s, transform .1s;
    cursor: pointer;
    white-space: nowrap;
}
.btn-row:hover {
    opacity: .85;
    transform: translateY(-1px);
}
.btn-edit {
    background: #fff3e0;
    color: #e67e22;
    border-color: #fdd5a8;
}
.btn-delete {
    background: #fef2f2;
    color: var(--brand);
    border-color: #fecaca;
}
.empty-row td {
    text-align: center;
    padding: 40px 16px;
    color: var(--muted);
    font-size: 14px;
}

.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
    z-index: 300;
}
.modal.show {
    display: flex;
}
.modal-content {
    background: white;
    border-radius: 24px;
    width: 90%;
    max-width: 380px;
    padding: 24px;
    box-shadow: 0 20px 35px rgba(0,0,0,0.2);
}
.modal-content h4 {
    font-family: 'Sora', sans-serif;
    margin-bottom: 16px;
}
.modal-content input {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: 10px;
    margin-bottom: 16px;
}
.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
.modal-actions button {
    padding: 8px 16px;
    border-radius: 30px;
    border: none;
    cursor: pointer;
}
.modal-actions button:first-child {
    background: var(--bg);
    color: var(--text);
}
.modal-actions button:last-child {
    background: var(--brand);
    color: white;
}

@media (max-width:1050px) {
    .page-grid { grid-template-columns: 340px 1fr; }
}
@media (max-width:860px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open {
        transform: translateX(0);
        box-shadow: 6px 0 24px rgba(0,0,0,.25);
    }
    .topbar {
        left: 0;
        padding: 0 16px;
    }
    .main {
        margin-left: 0;
        padding: 18px 14px;
    }
    .hamburger { display: flex; }
    .page-grid { grid-template-columns: 1fr; }
    .form-card { position: static; }
}
@media (max-width:520px) {
    .mini-stats { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<!-- Modal for new shop -->
<div id="shopModal" class="modal">
    <div class="modal-content">
        <h4>➕ Create New Shop</h4>
        <form method="post" id="newShopForm">
            <input type="text" name="new_shop_name" placeholder="e.g., Downtown Store, Online Outlet" required>
            <div class="modal-actions">
                <button type="button" onclick="closeShopModal()">Cancel</button>
                <button type="submit" name="create_shop">Create Shop</button>
            </div>
        </form>
    </div>
</div>

<!-- MAIN -->
<main class="main">

    <?php if (isset($_GET['success'])): ?>
    <div class="toast">
        ✅ Jersey <?= $_GET['success'] === 'updated' ? 'updated' : 'added' ?> successfully!
    </div>
    <?php elseif (isset($_GET['shop_added'])): ?>
    <div class="toast">
        🏪 New shop created! You can now assign jerseys to it.
    </div>
    <?php endif; ?>

    <div class="mini-stats">
        <div class="mini-stat ms-brand">
            <div class="ms-icon">👕</div>
            <div class="ms-val"><?= $total_jerseys ?></div>
            <div class="ms-lbl">Total Jerseys</div>
        </div>
        <div class="mini-stat ms-green">
            <div class="ms-icon">🛍️</div>
            <div class="ms-val"><?= $total_sell ?></div>
            <div class="ms-lbl">For Sale</div>
        </div>
        <div class="mini-stat ms-amber">
            <div class="ms-icon">⭐</div>
            <div class="ms-val"><?= $total_top ?></div>
            <div class="ms-lbl">Featured</div>
        </div>
        <div class="mini-stat ms-red">
            <div class="ms-icon">📦</div>
            <div class="ms-val"><?= $total_stock ?></div>
            <div class="ms-lbl">Total Stock</div>
        </div>
    </div>
    <?php if ($low_stock_count > 0): ?>
    <div class="toast" style="background: var(--red-bg); border-color: #fecaca; color: var(--red);">
        ⚠️ <?= $low_stock_count ?> jersey(s) have low stock (≤5 units). Consider restocking!
    </div>
    <?php endif; ?>

    <div class="page-grid">

        <!-- FORM -->
        <div class="form-card">
            <div class="form-card-head">
                <div class="title-row">
                    <span style="font-size:20px;"><?= $editData ? '✏️' : '➕' ?></span>
                    <h2><?= $editData ? 'Edit Jersey' : 'Add New Jersey' ?></h2>
                </div>
                <div class="shop-badge">
                    🏪 Store: <?= htmlspecialchars($shop_name) ?>
                </div>
            </div>
            <div class="form-body">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= $editData['id'] ?? '' ?>">

                    <div class="form-group">
                        <label>Jersey Title *</label>
                        <input type="text" name="title" placeholder="e.g. Nepal National Football Jersey"
                               value="<?= htmlspecialchars($editData['title'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" placeholder="Jersey details, material, features..."><?= htmlspecialchars($editData['description'] ?? '') ?></textarea>
                    </div>

                    <div class="two-col">
                        <div class="form-group">
                            <label>Price (रु) *</label>
                            <input type="number" name="price" placeholder="0.00" step="0.01"
                                   value="<?= $editData['price'] ?? '' ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Discount (%)</label>
                            <input type="number" name="discount" placeholder="0" min="0" max="100"
                                   value="<?= $editData['discount'] ?? 0 ?>">
                        </div>
                    </div>

                    <div class="two-col">
                        <div class="form-group">
                            <label>Sport Type *</label>
                            <select name="sport_type" required>
                                <option value="">-- Select --</option>
                                <?php foreach (['Football','Cricket','Basketball','Volleyball','Tennis','Esports','Other'] as $sp):
                                    $sel = (isset($editData['sport_type']) && $editData['sport_type']===$sp) ? 'selected' : ''; ?>
                                <option value="<?= $sp ?>" <?= $sel ?>><?= $sp ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Jersey Type</label>
                            <select name="jersey_type">
                                <?php foreach (['Home','Away','Third','Training'] as $jt):
                                    $sel = (isset($editData['jersey_type']) && $editData['jersey_type']===$jt) ? 'selected' : ''; ?>
                                <option value="<?= $jt ?>" <?= $sel ?>><?= $jt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="two-col">
                        <div class="form-group">
                            <label>Shop (Store)</label>
                            <div class="shop-group">
                                <select name="shop_id">
                                    <option value="">-- No Shop / General --</option>
                                    <?php foreach ($shops as $shop):
                                        $selected = ($editData && $editData['shop_id'] == $shop['id']) ? 'selected' : '';
                                    ?>
                                    <option value="<?= $shop['id'] ?>" <?= $selected ?>><?= htmlspecialchars($shop['shop_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" onclick="openShopModal()">+</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Sell Status</label>
                            <select name="sell">
                                <option value="Yes" <?= (isset($editData['sell']) && $editData['sell']==='Yes') ? 'selected' : '' ?>>Yes — Active</option>
                                <option value="No"  <?= (isset($editData['sell']) && $editData['sell']==='No')  ? 'selected' : '' ?>>No — Hidden</option>
                            </select>
                        </div>
                    </div>

                    <div class="two-col">
                        <div class="form-group">
                            <label>Stock Quantity *</label>
                            <input type="number" name="stock" min="0" value="<?= $editData['stock'] ?? 0 ?>" required>
                            <small style="font-size: 10px; color: var(--muted);">Current available units</small>
                        </div>
                        <div class="form-group">
                            <label>Rating (0–5)</label>
                            <input type="number" name="rating" step="0.1" min="0" max="5" value="<?= $editData['rating'] ?? 0 ?>">
                            <small style="font-size: 10px; color: var(--muted);">Average customer rating</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Sizes (select all that apply)</label>
                        <div class="size-row">
                            <?php
                            $selSizes = isset($editData['sizes']) ? explode(",", $editData['sizes']) : [];
                            foreach (['S','M','L','XL','XXL'] as $sz):
                                $chk = in_array($sz, $selSizes) ? 'checked' : '';
                            ?>
                            <span class="size-chip">
                                <input type="checkbox" name="sizes[]" value="<?= $sz ?>" id="sz_<?= $sz ?>" <?= $chk ?>>
                                <label for="sz_<?= $sz ?>"><?= $sz ?></label>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="toggle-row">
                        <span>⭐ Feature on Front Page</span>
                        <label class="toggle-switch">
                            <input type="checkbox" name="is_top" <?= (isset($editData['is_top']) && $editData['is_top']==1) ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label>Main Image</label>
                        <?php if ($editData && !empty($editData['image'])): ?>
                        <div class="current-img">
                            <img src="<?= htmlspecialchars($editData['image']) ?>" alt="Current">
                            <span>Current — upload new to replace</span>
                        </div>
                        <?php endif; ?>
                        <div class="file-zone">
                            <input type="file" name="image" accept="image/*" onchange="showFileName(this,'fn1')">
                            <div class="fz-icon">📷</div>
                            <p id="fn1">Click or drag to upload main image</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Extra Images (up to 3)</label>
                        <div class="file-zone">
                            <input type="file" name="images[]" accept="image/*" multiple onchange="showFileName(this,'fn2')">
                            <div class="fz-icon">🖼️</div>
                            <p id="fn2">Click or drag to upload extra images</p>
                        </div>
                    </div>

                    <button type="submit" name="submit" class="btn-submit">
                        <?= $editData ? '💾 Update Jersey' : '➕ Add Jersey' ?>
                    </button>
                    <?php if ($editData): ?>
                    <a href="add_jersey.php" class="btn-cancel">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- TABLE -->
        <div class="table-card">
            <div class="table-head">
                <h3>My Jerseys <small><?= $total_jerseys ?> listings</small></h3>
                <div class="tbl-search">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="tblSearch" placeholder="Search jerseys..." oninput="filterRows()">
                </div>
            </div>
            <div class="table-wrap">
                <table id="jerseyTable">
                    <thead>
                        <tr>
                            <th>Jersey</th>
                            <th>Sport</th>
                            <th>Shop</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Top</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && $result->num_rows > 0):
                        while ($row = $result->fetch_assoc()):
                            $disc_price = $row['discount'] > 0
                                ? $row['price'] - ($row['price'] * $row['discount'] / 100)
                                : null;
                            $stockClass = 'stock-normal';
                            $stockText = $row['stock'] . ' units';
                            if ($row['stock'] <= 0) {
                                $stockClass = 'stock-out';
                                $stockText = 'Out of Stock';
                            } elseif ($row['stock'] <= 5) {
                                $stockClass = 'stock-low';
                                $stockText = $row['stock'] . ' left!';
                            }
                            $rating = floatval($row['rating'] ?? 0);
                            $fullStars = floor($rating);
                            $halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0;
                            $emptyStars = 5 - $fullStars - $halfStar;
                    ?>
                    <tr data-search="<?= strtolower(htmlspecialchars($row['title'].' '.($row['sport_type']??'').' '.($row['shop_name']??''))) ?>">
                        <td>
                            <div class="jersey-cell">
                                <?php if (!empty($row['image'])): ?>
                                <img class="jersey-thumb" src="<?= htmlspecialchars($row['image']) ?>" alt="">
                                <?php else: ?>
                                <img class="jersey-thumb" src="https://placehold.co/80x80/f0f2f5/C94B01?text=👕" alt="">
                                <?php endif; ?>
                                <div>
                                    <div class="jname"><?= htmlspecialchars($row['title']) ?></div>
                                    <div class="jtype"><?= htmlspecialchars($row['jersey_type'] ?? '') ?> • <?= htmlspecialchars($row['sizes'] ?? '—') ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($row['sport_type'])): ?>
                            <span class="badge b-sport"><?= htmlspecialchars($row['sport_type']) ?></span>
                            <?php else: ?>
                            <span style="color:var(--muted);font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($row['shop_name'])): ?>
                            <span class="shop-badge">🏪 <?= htmlspecialchars($row['shop_name']) ?></span>
                            <?php else: ?>
                            <span style="color:var(--muted);font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="price-col">रु <?= number_format($disc_price ?? $row['price'], 2) ?></div>
                            <?php if ($disc_price): ?>
                            <div class="disc-col">रु <?= number_format($row['price'], 2) ?></div>
                            <?php endif; ?>
                         </td>
                        <td>
                            <span class="stock-badge <?= $stockClass ?>">📦 <?= $stockText ?></span>
                         </td>
                        <td>
                            <?php if ($rating > 0): ?>
                            <div class="rating-stars">
                                <?php for ($i = 0; $i < $fullStars; $i++) echo '★'; ?>
                                <?php if ($halfStar) echo '½'; ?>
                                <?php for ($i = 0; $i < $emptyStars; $i++) echo '☆'; ?>
                                <span style="margin-left: 4px; color: var(--text); font-size: 11px;">(<?= number_format($rating, 1) ?>)</span>
                            </div>
                            <?php else: ?>
                            <span style="color:var(--muted); font-size:11px;">—</span>
                            <?php endif; ?>
                         </td>
                        <td>
                            <?php if ($row['sell'] === 'Yes'): ?>
                            <span class="badge b-sell">For Sale</span>
                            <?php else: ?>
                            <span class="badge b-nosell">Hidden</span>
                            <?php endif; ?>
                         </td>
                        <td>
                            <?= $row['is_top'] == 1
                                ? '<span class="badge b-top">⭐ Top</span>'
                                : '<span style="color:var(--muted);font-size:12px;">—</span>'; ?>
                         </td>
                        <td>
                            <div class="row-actions">
                                <a href="?edit=<?= $row['id'] ?>" class="btn-row btn-edit">✏️ Edit</a>
                                <a href="?delete=<?= $row['id'] ?>" class="btn-row btn-delete"
                                   onclick="return confirm('Delete this jersey?')">🗑 Del</a>
                            </div>
                         </td>
                    </tr>
                    <?php endwhile;
                    else: ?>
                    <tr class="empty-row">
                        <td colspan="9">
                            <div style="font-size:32px;margin-bottom:8px;">👕</div>
                            No jerseys yet. Use the form to add your first one!
                        </td>
                    </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('overlay').classList.remove('show');
}
function toggleDD() {
    document.getElementById('ddMenu').classList.toggle('open');
}
document.addEventListener('click', function(e) {
    var w = document.querySelector('.user-wrap');
    if (w && !w.contains(e.target)) document.getElementById('ddMenu').classList.remove('open');
});
function showFileName(input, targetId) {
    var el = document.getElementById(targetId);
    if (input.files.length > 0) {
        el.textContent = '✓ ' + Array.from(input.files).map(f => f.name).join(', ');
        el.style.color = '#1a8a4a';
    }
}
function filterRows() {
    var q = document.getElementById('tblSearch').value.toLowerCase();
    document.querySelectorAll('#jerseyTable tbody tr[data-search]').forEach(function(row) {
        row.style.display = row.dataset.search.includes(q) ? '' : 'none';
    });
}
var toast = document.querySelector('.toast');
if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);

// Shop modal functions
var modal = document.getElementById('shopModal');
function openShopModal() {
    modal.classList.add('show');
}
function closeShopModal() {
    modal.classList.remove('show');
}
modal.addEventListener('click', function(e) {
    if (e.target === modal) closeShopModal();
});
</script>
</body>
</html>