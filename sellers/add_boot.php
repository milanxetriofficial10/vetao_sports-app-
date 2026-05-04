<?php
ob_start();
session_start();
require_once "../databases/db.php";

$conn = getDB();
if (!$conn) die("Database connection failed.");

/* ── SESSION CHECK ── */
if (!isset($_SESSION['seller_id'])) {
    header("Location: ../sellers/login.php");
    exit;
}

$seller_id = (int)$_SESSION['seller_id'];

/* ── UPLOAD DIR ── */
$uploadDir = "../uploads/boots/";
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

/* ── CREATE NEW SHOP (AJAX / POST) ── */
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
        header("Location: add_boot.php?shop_added=1");
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

/* ── HELPER: UPLOAD SINGLE IMAGE ── */
function uploadBootImage($file, $prefix) {
    if (empty($file['name'])) return '';
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed)) return '';
    
    $uploadDir = "../uploads/boots/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $name = $prefix . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
    $dest = $uploadDir . $name;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return 'uploads/boots/' . $name;
    }
    return '';
}

/* ── DELETE BOOT (with image cleanup & ownership check) ── */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Fetch images first to delete files
    $stmt = $conn->prepare("SELECT main_image, additional_images FROM boot WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $id, $seller_id);
    $stmt->execute();
    $boot = $stmt->get_result()->fetch_assoc();
    if ($boot) {
        if (!empty($boot['main_image']) && file_exists("../" . $boot['main_image'])) {
            unlink("../" . $boot['main_image']);
        }
        $extra = json_decode($boot['additional_images'], true);
        if (is_array($extra)) {
            foreach ($extra as $img) {
                if (file_exists("../" . $img)) unlink("../" . $img);
            }
        }
    }
    $stmt->close();
    // Delete variants and boot
    $conn->query("DELETE FROM boot_variants WHERE boot_id = $id");
    $stmt = $conn->prepare("DELETE FROM boot WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $id, $seller_id);
    $stmt->execute();
    $stmt->close();
    header("Location: add_boot.php?msg=deleted");
    exit;
}

/* ── FETCH EDIT DATA (with ownership check) ── */
$editBoot = null;
$editVariants = [];
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM boot WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $id, $seller_id);
    $stmt->execute();
    $editBoot = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($editBoot) {
        $var_res = $conn->query("SELECT size, stock FROM boot_variants WHERE boot_id = $id");
        while ($v = $var_res->fetch_assoc()) {
            $editVariants[] = $v;
        }
    }
}

/* ── ADD / UPDATE BOOT (with seller_id & shop_id) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $name           = $conn->real_escape_string(trim($_POST['name']));
    $brand          = $conn->real_escape_string(trim($_POST['brand']));
    $price          = floatval($_POST['price']);
    $category       = $conn->real_escape_string($_POST['category']);
    $discount_percent = intval($_POST['discount_percent']);
    $sold_out       = isset($_POST['sold_out']) ? 1 : 0;
    $sizes          = $_POST['size'] ?? [];
    $stocks         = $_POST['stock'] ?? [];
    $shop_id        = !empty($_POST['shop_id']) ? (int)$_POST['shop_id'] : 0; // 0 will be converted to NULL

    $main_image = '';
    $additional_images = [];

    if (isset($_POST['boot_id']) && !empty($_POST['boot_id'])) {
        // UPDATE MODE
        $boot_id = intval($_POST['boot_id']);
        // Get current images
        $cur = $conn->query("SELECT main_image, additional_images FROM boot WHERE id=$boot_id AND seller_id=$seller_id")->fetch_assoc();
        if ($cur) {
            $main_image = $cur['main_image'];
            $additional_images = json_decode($cur['additional_images'], true) ?: [];
        }
        
        // Upload new main image if provided
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === 0) {
            $new_main = uploadBootImage($_FILES['main_image'], 'main');
            if ($new_main) {
                if (!empty($main_image) && file_exists("../" . $main_image)) unlink("../" . $main_image);
                $main_image = $new_main;
            }
        }
        
        // Delete checked additional images
        if (isset($_POST['delete_extra'])) {
            $to_delete = $_POST['delete_extra'];
            foreach ($to_delete as $del_img) {
                $del_img = $conn->real_escape_string($del_img);
                if (($key = array_search($del_img, $additional_images)) !== false) {
                    unset($additional_images[$key]);
                    if (file_exists("../" . $del_img)) unlink("../" . $del_img);
                }
            }
            $additional_images = array_values($additional_images);
        }
        
        // Upload new additional images
        if (isset($_FILES['additional_images'])) {
            $files = $_FILES['additional_images'];
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === 0) {
                    $fake_file = [
                        'name' => $files['name'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i]
                    ];
                    $up = uploadBootImage($fake_file, 'extra');
                    if ($up) $additional_images[] = $up;
                }
            }
        }
        
        $json_extra = json_encode($additional_images);
        // Use NULLIF to convert 0 to NULL for shop_id
        $stmt = $conn->prepare("UPDATE boot SET name=?, brand=?, price=?, category=?, main_image=?, additional_images=?, discount_percent=?, sold_out=?, shop_id=NULLIF(?, 0) WHERE id=? AND seller_id=?");
        $stmt->bind_param("ssdsssiisii", $name, $brand, $price, $category, $main_image, $json_extra, $discount_percent, $sold_out, $shop_id, $boot_id, $seller_id);
        $stmt->execute();
        $stmt->close();
        
        // Update variants: delete old and insert new
        $conn->query("DELETE FROM boot_variants WHERE boot_id=$boot_id");
        if (!empty($sizes)) {
            $vstmt = $conn->prepare("INSERT INTO boot_variants (boot_id, size, stock) VALUES (?, ?, ?)");
            foreach ($sizes as $idx => $size) {
                if (!empty($size) && isset($stocks[$idx]) && is_numeric($stocks[$idx])) {
                    $vstmt->bind_param("isi", $boot_id, $size, $stocks[$idx]);
                    $vstmt->execute();
                }
            }
            $vstmt->close();
        }
        header("Location: add_boot.php?success=updated");
        exit;
        
    } else {
        // ADD MODE
        // Main image required
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === 0) {
            $main_image = uploadBootImage($_FILES['main_image'], 'main');
        }
        if (!$main_image) {
            header("Location: add_boot.php?error=noimg");
            exit;
        }
        
        // Additional images
        if (isset($_FILES['additional_images'])) {
            $files = $_FILES['additional_images'];
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === 0) {
                    $fake_file = [
                        'name' => $files['name'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i]
                    ];
                    $up = uploadBootImage($fake_file, 'extra');
                    if ($up) $additional_images[] = $up;
                }
            }
        }
        $json_extra = json_encode($additional_images);
        
        // Use NULLIF to convert 0 to NULL for shop_id
        $stmt = $conn->prepare("INSERT INTO boot (name, brand, price, category, main_image, additional_images, discount_percent, sold_out, seller_id, shop_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0))");
        $stmt->bind_param("ssdsssiiii", $name, $brand, $price, $category, $main_image, $json_extra, $discount_percent, $sold_out, $seller_id, $shop_id);
        $stmt->execute();
        $boot_id = $conn->insert_id;
        $stmt->close();
        
        // Insert variants
        if (!empty($sizes)) {
            $vstmt = $conn->prepare("INSERT INTO boot_variants (boot_id, size, stock) VALUES (?, ?, ?)");
            foreach ($sizes as $idx => $size) {
                if (!empty($size) && isset($stocks[$idx]) && is_numeric($stocks[$idx])) {
                    $vstmt->bind_param("isi", $boot_id, $size, $stocks[$idx]);
                    $vstmt->execute();
                }
            }
            $vstmt->close();
        }
        header("Location: add_boot.php?success=added");
        exit;
    }
}

/* ── FETCH ALL BOOTS FOR THIS SELLER (JOIN with shop name) ── */
$stmt = $conn->prepare("
    SELECT b.*, s.shop_name 
    FROM boot b 
    LEFT JOIN shops s ON b.shop_id = s.id 
    WHERE b.seller_id = ? 
    ORDER BY b.created_at DESC
");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$boots_result = $stmt->get_result();
$stmt->close();

// Build variants map
$variants_map = [];
if ($boots_result->num_rows > 0) {
    $boot_ids = [];
    $boots_result->data_seek(0);
    while ($b = $boots_result->fetch_assoc()) {
        $boot_ids[] = $b['id'];
    }
    if (!empty($boot_ids)) {
        $ids_str = implode(',', $boot_ids);
        $var_q = $conn->query("SELECT boot_id, size, stock FROM boot_variants WHERE boot_id IN ($ids_str)");
        while ($v = $var_q->fetch_assoc()) {
            $variants_map[$v['boot_id']][] = ['size' => $v['size'], 'stock' => $v['stock']];
        }
    }
    $boots_result->data_seek(0);
}

/* ── STATS ── */
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM boot WHERE seller_id = ?");
$stmt->bind_param("i", $seller_id); $stmt->execute();
$total_boots = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0; $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM boot WHERE seller_id = ? AND sold_out = 0");
$stmt->bind_param("i", $seller_id); $stmt->execute();
$total_active = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0; $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM boot WHERE seller_id = ? AND discount_percent > 0");
$stmt->bind_param("i", $seller_id); $stmt->execute();
$total_discounted = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0; $stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $editBoot ? 'Edit Boot' : 'Add Boot' ?> | SportsBazaar</title>
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
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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
.toast.error {
    background: #fee2e2;
    border-color: #fecaca;
    color: #b91c1c;
}

.page-grid {
    display: grid;
    grid-template-columns: 420px 1fr;
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
.two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

/* Shop group with inline add button */
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

/* Size & stock rows */
.size-stock-row {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
    align-items: center;
}
.size-stock-row input {
    flex: 1;
}
.btn-remove-size {
    background: #fee2e2;
    border: none;
    color: #b91c1c;
    padding: 6px 12px;
    border-radius: 30px;
    cursor: pointer;
    font-weight: 600;
    font-size: 12px;
}
.small-btn {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 30px;
    padding: 6px 14px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
}
.small-btn:hover {
    background: var(--brand-lt);
    border-color: var(--brand);
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

/* Image upload zones */
.img-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 12px;
}
.img-box {
    border: 1.5px dashed var(--border);
    border-radius: 12px;
    padding: 12px 8px;
    text-align: center;
    cursor: pointer;
    background: var(--bg);
    position: relative;
    min-height: 110px;
}
.img-box:hover {
    border-color: var(--brand);
    background: var(--brand-lt);
}
.img-box img {
    max-height: 70px;
    border-radius: 8px;
    margin-top: 6px;
    margin-left: auto;
    margin-right: auto;
    display: block;
}
.img-box .lbl {
    position: absolute;
    top: 6px;
    left: 8px;
    font-size: 9px;
    font-weight: 700;
    background: rgba(0,0,0,0.6);
    color: white;
    padding: 2px 6px;
    border-radius: 20px;
}
.img-box .rm {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 22px;
    height: 22px;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 50%;
    font-size: 12px;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
}
.img-box.has-img .rm { display: flex; }
.delete-extra-label {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #fff0f0;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 10px;
    margin-top: 6px;
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
    outline: none;
    width: 100%;
}
.table-wrap {
    overflow-x: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
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
}
tbody tr { border-bottom: 1px solid var(--border); transition: background .12s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #fafbfc; }
td {
    padding: 11px 14px;
    font-size: 13px;
    vertical-align: middle;
}
.boot-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}
.boot-thumb {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    object-fit: cover;
    background: var(--bg);
    border: 1px solid var(--border);
}
.boot-name {
    font-weight: 600;
    font-size: 13px;
}
.boot-brand {
    font-size: 11px;
    color: var(--muted);
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
}
.b-soldout {
    background: #fee2e2;
    color: #b91c1c;
}
.b-soldout::before { background: #b91c1c; }
.b-active {
    background: var(--green-bg);
    color: var(--green);
}
.b-active::before { background: var(--green); }
.b-discount {
    background: var(--amber-bg);
    color: var(--amber);
}
.b-discount::before { background: var(--amber); }
.shop-badge-sm {
    background: #f0f0fc;
    color: #4c51bf;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    white-space: nowrap;
    display: inline-block;
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
}

/* Modal */
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
    .page-grid { grid-template-columns: 360px 1fr; }
}
@media (max-width:860px) {
    .sidebar { transform: translateX(-100%); }
    .main {
        margin-left: 0;
        padding: 18px 14px;
    }
    .page-grid { grid-template-columns: 1fr; }
    .form-card { position: static; }
}
</style>
</head>
<body>

<?php include "sidenav.php"; ?>

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

<main class="main">

    <?php if (isset($_GET['success'])): ?>
        <div class="toast">
            ✅ Boot <?= $_GET['success'] === 'updated' ? 'updated' : 'added' ?> successfully!
        </div>
    <?php elseif (isset($_GET['shop_added'])): ?>
        <div class="toast">
            🏪 New shop created! You can now assign boots to it.
        </div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'noimg'): ?>
        <div class="toast error">
            ❌ Main image is required!
        </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="toast error">
            🗑 Boot deleted successfully.
        </div>
    <?php endif; ?>

    <div class="mini-stats">
        <div class="mini-stat ms-brand">
            <div class="ms-icon">👟</div>
            <div class="ms-val"><?= $total_boots ?></div>
            <div class="ms-lbl">Total Boots</div>
        </div>
        <div class="mini-stat ms-green">
            <div class="ms-icon">🟢</div>
            <div class="ms-val"><?= $total_active ?></div>
            <div class="ms-lbl">In Stock</div>
        </div>
        <div class="mini-stat ms-amber">
            <div class="ms-icon">🏷️</div>
            <div class="ms-val"><?= $total_discounted ?></div>
            <div class="ms-lbl">On Discount</div>
        </div>
    </div>

    <div class="page-grid">

        <!-- FORM CARD -->
        <div class="form-card">
            <div class="form-card-head">
                <div class="title-row">
                    <span style="font-size:20px;"><?= $editBoot ? '✏️' : '➕' ?></span>
                    <h2><?= $editBoot ? 'Edit Football Boot' : 'Add New Football Boot' ?></h2>
                </div>
                <div class="shop-badge">
                    🏪 Store: <?= htmlspecialchars($shop_name) ?>
                </div>
            </div>
            <div class="form-body">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="boot_id" value="<?= $editBoot['id'] ?? '' ?>">

                    <div class="form-group">
                        <label>Boot Name *</label>
                        <input type="text" name="name" placeholder="e.g. Mercurial Superfly" value="<?= htmlspecialchars($editBoot['name'] ?? '') ?>" required>
                    </div>

                    <div class="two-col">
                        <div class="form-group">
                            <label>Brand *</label>
                            <input type="text" name="brand" placeholder="Nike, Adidas..." value="<?= htmlspecialchars($editBoot['brand'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Price (€) *</label>
                            <input type="number" step="0.01" name="price" placeholder="0.00" value="<?= $editBoot['price'] ?? '' ?>" required>
                        </div>
                    </div>

                    <div class="two-col">
                        <div class="form-group">
                            <label>Discount (%)</label>
                            <input type="number" name="discount_percent" min="0" max="100" value="<?= $editBoot['discount_percent'] ?? 0 ?>">
                        </div>
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category" required>
                                <option value="men" <?= (isset($editBoot['category']) && $editBoot['category'] === 'men') ? 'selected' : '' ?>>Men</option>
                                <option value="women" <?= (isset($editBoot['category']) && $editBoot['category'] === 'women') ? 'selected' : '' ?>>Women</option>
                                <option value="kids" <?= (isset($editBoot['category']) && $editBoot['category'] === 'kids') ? 'selected' : '' ?>>Kids</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Shop (Store)</label>
                        <div class="shop-group">
                            <select name="shop_id">
                                <option value="">-- No Shop / General --</option>
                                <?php foreach ($shops as $shop):
                                    $selected = ($editBoot && $editBoot['shop_id'] == $shop['id']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $shop['id'] ?>" <?= $selected ?>><?= htmlspecialchars($shop['shop_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" onclick="openShopModal()">+</button>
                        </div>
                    </div>

                    <!-- Main Image -->
                    <div class="form-group">
                        <label>Main Image *</label>
                        <div class="img-grid">
                            <div class="img-box <?= (!empty($editBoot['main_image'])) ? 'has-img' : '' ?>" id="mainBox" onclick="document.getElementById('mainImgInput').click()">
                                <span class="lbl">MAIN</span>
                                <img id="mainPreview" src="<?= (!empty($editBoot['main_image'])) ? '../' . htmlspecialchars($editBoot['main_image']) : '' ?>" style="<?= !empty($editBoot['main_image']) ? 'display:block' : 'display:none'; ?>">
                                <div class="ph" id="mainPh" style="<?= !empty($editBoot['main_image']) ? 'display:none' : 'display:block'; ?>">
                                    <i class="fa fa-image" style="font-size:24px;color:#94a3b8;"></i><br>Click to upload
                                </div>
                                <button type="button" class="rm" onclick="clearMainImage(event)">✕</button>
                            </div>
                            <input type="file" id="mainImgInput" name="main_image" accept="image/*" style="display:none" onchange="previewMainImage(this)">
                        </div>
                    </div>

                    <!-- Additional Images -->
                    <div class="form-group">
                        <label>Additional Images (max 4)</label>
                        <div class="img-grid" id="extraImagesGrid">
                            <?php
                            $extra_images = [];
                            if ($editBoot && !empty($editBoot['additional_images'])) {
                                $extra_images = json_decode($editBoot['additional_images'], true);
                            }
                            for ($i = 0; $i < 4; $i++):
                                $has = isset($extra_images[$i]);
                                $path = $has ? htmlspecialchars($extra_images[$i]) : '';
                            ?>
                                <div class="img-box <?= $has ? 'has-img' : '' ?>" id="extraBox<?= $i ?>" onclick="document.getElementById('extraInput<?= $i ?>').click()">
                                    <span class="lbl">EXTRA <?= $i+1 ?></span>
                                    <img id="extraPrev<?= $i ?>" src="<?= $has ? '../' . $path : '' ?>" style="<?= $has ? 'display:block' : 'display:none'; ?>">
                                    <div class="ph" id="extraPh<?= $i ?>" style="<?= $has ? 'display:none' : 'display:block'; ?>">
                                        <i class="fa fa-image" style="font-size:24px;color:#94a3b8;"></i><br>Upload
                                    </div>
                                    <button type="button" class="rm" onclick="clearExtraImage(event, <?= $i ?>)">✕</button>
                                    <?php if ($has && $editBoot): ?>
                                        <div style="margin-top: 6px;">
                                            <label class="delete-extra-label">
                                                <input type="checkbox" name="delete_extra[]" value="<?= $path ?>"> Delete this
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" id="extraInput<?= $i ?>" name="additional_images[]" accept="image/*" style="display:none" onchange="previewExtraImage(this, <?= $i ?>)">
                            <?php endfor; ?>
                        </div>
                        <small style="color:var(--muted); font-size:10px;">For edit: check "Delete this" to remove existing image. New uploads will be added.</small>
                    </div>

                    <!-- Sizes & Stock -->
                    <div class="form-group">
                        <label>Sizes & Stock</label>
                        <div id="sizeStockContainer">
                            <?php
                            $variants = [];
                            if ($editBoot && !empty($editVariants)) $variants = $editVariants;
                            if (empty($variants)) $variants = [['size' => '', 'stock' => '']];
                            foreach ($variants as $idx => $var):
                            ?>
                                <div class="size-stock-row">
                                    <input type="text" name="size[]" placeholder="Size (e.g. UK 6, EU 40)" value="<?= htmlspecialchars($var['size']) ?>" required>
                                    <input type="number" name="stock[]" placeholder="Stock quantity" value="<?= $var['stock'] ?>" required>
                                    <button type="button" class="btn-remove-size" onclick="this.parentElement.remove()"><i class="fa fa-trash"></i> Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="small-btn" id="addSizeBtn"><i class="fa fa-plus"></i> Add Size</button>
                    </div>

                    <div class="toggle-row">
                        <span>❌ Sold Out?</span>
                        <label class="toggle-switch">
                            <input type="checkbox" name="sold_out" value="1" <?= (!empty($editBoot['sold_out'])) ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <button type="submit" name="submit" class="btn-submit">
                        <?= $editBoot ? '💾 Update Boot' : '➕ Add Boot' ?>
                    </button>
                    <?php if ($editBoot): ?>
                        <a href="add_boot.php" class="btn-cancel">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- TABLE -->
        <div class="table-card">
            <div class="table-head">
                <h3>My Boots <small><?= $total_boots ?> listings</small></h3>
                <div class="tbl-search">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="tblSearch" placeholder="Search boots..." oninput="filterRows()">
                </div>
            </div>
            <div class="table-wrap">
                <table id="bootTable">
                    <thead>
                        <tr>
                            <th>Boot</th>
                            <th>Category</th>
                            <th>Shop</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Sizes (Stock)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($boots_result && $boots_result->num_rows > 0):
                        while ($row = $boots_result->fetch_assoc()):
                            $final_price = $row['price'] * (1 - $row['discount_percent'] / 100);
                            $var_list = $variants_map[$row['id']] ?? [];
                            $sizes_str = [];
                            foreach ($var_list as $v) $sizes_str[] = $v['size'] . "(" . $v['stock'] . ")";
                            $search_str = strtolower($row['name'] . ' ' . $row['brand'] . ' ' . ($row['shop_name'] ?? ''));
                            $img_src = !empty($row['main_image']) ? '../' . htmlspecialchars($row['main_image']) : 'https://placehold.co/60x60/f0f2f5/C94B01?text=👟';
                    ?>
                        <tr data-search="<?= $search_str ?>">
                            <td>
                                <div class="boot-cell">
                                    <img class="boot-thumb" src="<?= $img_src ?>" alt="">
                                    <div>
                                        <div class="boot-name"><?= htmlspecialchars($row['name']) ?></div>
                                        <div class="boot-brand"><?= htmlspecialchars($row['brand']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge b-sport"><?= ucfirst($row['category']) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($row['shop_name'])): ?>
                                    <span class="shop-badge-sm">🏪 <?= htmlspecialchars($row['shop_name']) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="price-col">€<?= number_format($final_price, 2) ?></div>
                                <?php if ($row['discount_percent'] > 0): ?>
                                    <div class="disc-col">€<?= number_format($row['price'], 2) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['sold_out'] == 1): ?>
                                    <span class="badge b-soldout">Sold Out</span>
                                <?php else: ?>
                                    <span class="badge b-active">In Stock</span>
                                <?php endif; ?>
                                <?php if ($row['discount_percent'] > 0): ?>
                                    <span class="badge b-discount" style="margin-left:4px;">-<?= $row['discount_percent'] ?>%</span>
                                <?php endif; ?>
                            </td>
                            <td><?= implode(', ', $sizes_str) ?: '—' ?></td>
                            <td>
                                <div class="row-actions">
                                    <a href="?edit=<?= $row['id'] ?>" class="btn-row btn-edit">✏️ Edit</a>
                                    <a href="?delete=<?= $row['id'] ?>" class="btn-row btn-delete" onclick="return confirm('Delete this boot permanently?')">🗑 Del</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile;
                    else: ?>
                        <tr class="empty-row">
                            <td colspan="7">
                                <div style="font-size:32px;margin-bottom:8px;">👟</div>
                                No boots added yet. Use the form to add your first boot!
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
// Main image preview
function previewMainImage(input) {
    if (!input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('mainPreview').src = e.target.result;
        document.getElementById('mainPreview').style.display = 'block';
        document.getElementById('mainPh').style.display = 'none';
        document.getElementById('mainBox').classList.add('has-img');
    };
    reader.readAsDataURL(input.files[0]);
}
function clearMainImage(e) {
    e.stopPropagation();
    document.getElementById('mainPreview').style.display = 'none';
    document.getElementById('mainPh').style.display = 'block';
    document.getElementById('mainBox').classList.remove('has-img');
    document.getElementById('mainImgInput').value = '';
}

// Extra images preview
function previewExtraImage(input, idx) {
    if (!input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('extraPrev' + idx).src = e.target.result;
        document.getElementById('extraPrev' + idx).style.display = 'block';
        document.getElementById('extraPh' + idx).style.display = 'none';
        document.getElementById('extraBox' + idx).classList.add('has-img');
        let delCheck = document.querySelector(`#extraBox${idx} input[type="checkbox"]`);
        if(delCheck) delCheck.checked = false;
    };
    reader.readAsDataURL(input.files[0]);
}
function clearExtraImage(e, idx) {
    e.stopPropagation();
    document.getElementById('extraPrev' + idx).style.display = 'none';
    document.getElementById('extraPh' + idx).style.display = 'block';
    document.getElementById('extraBox' + idx).classList.remove('has-img');
    document.getElementById('extraInput' + idx).value = '';
}

// Dynamic size rows
document.getElementById('addSizeBtn').addEventListener('click', function() {
    const container = document.getElementById('sizeStockContainer');
    const newRow = document.createElement('div');
    newRow.className = 'size-stock-row';
    newRow.innerHTML = `
        <input type="text" name="size[]" placeholder="Size (e.g. UK 6)" required>
        <input type="number" name="stock[]" placeholder="Stock quantity" required>
        <button type="button" class="btn-remove-size" onclick="this.parentElement.remove()"><i class="fa fa-trash"></i> Remove</button>
    `;
    container.appendChild(newRow);
});

// Shop modal
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

// Table search
function filterRows() {
    var q = document.getElementById('tblSearch').value.toLowerCase();
    document.querySelectorAll('#bootTable tbody tr[data-search]').forEach(function(row) {
        row.style.display = row.dataset.search.includes(q) ? '' : 'none';
    });
}

// Auto-hide toast
var toast = document.querySelector('.toast');
if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);
</script>
</body>
</html>