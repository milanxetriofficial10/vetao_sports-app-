<?php
ob_start();
session_start();
require_once "../databases/db.php";

$conn = getDB();
if (!$conn) die("Database connection failed.");

/* ── SELLER AUTHENTICATION ── */
if (!isset($_SESSION['seller_id'])) {
    header("Location: login.php");
    exit;
}
$seller_id = (int)$_SESSION['seller_id'];

/* ── CREATE cricket_bats TABLE IF NOT EXISTS (with shop_id) ── */
$conn->query("CREATE TABLE IF NOT EXISTS cricket_bats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    shop_id INT NULL,
    bat_name VARCHAR(200) NOT NULL,
    brand VARCHAR(100) NOT NULL,
    description TEXT,
    weight VARCHAR(20) DEFAULT '2.8 lb',
    original_price DECIMAL(10,2) NOT NULL,
    discount_price DECIMAL(10,2) DEFAULT NULL,
    stock_qty INT NOT NULL DEFAULT 0,
    main_image VARCHAR(255) DEFAULT NULL,
    additional_images TEXT DEFAULT NULL,
    visible TINYINT(1) DEFAULT 1,
    featured TINYINT(1) DEFAULT 0,
    best_seller TINYINT(1) DEFAULT 0,
    is_top TINYINT(1) DEFAULT 0,
    is_new TINYINT(1) DEFAULT 0,
    rating FLOAT DEFAULT 0,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(seller_id),
    FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE CASCADE,
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE SET NULL
)");

/* ── SELLER INFO & SHOPS ── */
$stmt = $conn->prepare("SELECT full_name, shop_name FROM sellers WHERE id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$seller = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$seller) { session_destroy(); header("Location: register.php"); exit; }
$seller_name = $seller['full_name'];
$shop_name   = $seller['shop_name'];

/* ── CREATE NEW SHOP (AJAX/POST) ── */
if (isset($_POST['create_shop']) && !empty(trim($_POST['new_shop_name']))) {
    $new_shop = trim($_POST['new_shop_name']);
    $stmt = $conn->prepare("INSERT INTO shops (seller_id, shop_name) VALUES (?, ?)");
    $stmt->bind_param("is", $seller_id, $new_shop);
    $stmt->execute();
    $stmt->close();
    header("Location: cricket_bat.php?shop_added=1");
    exit;
}

/* ── FETCH SHOPS FOR DROPDOWN ── */
$shops = [];
$stmt = $conn->prepare("SELECT id, shop_name FROM shops WHERE seller_id = ? ORDER BY shop_name");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$shops_result = $stmt->get_result();
while ($row = $shops_result->fetch_assoc()) {
    $shops[] = $row;
}
$stmt->close();

/* ── HELPER: UPLOAD IMAGE ── */
function uploadBatImage($file, $prefix) {
    if (empty($file['name'])) return '';
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed)) return '';
    
    $uploadDir = "../uploads/bats/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $name = $prefix . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
    $dest = $uploadDir . $name;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return 'uploads/bats/' . $name;
    }
    return '';
}

/* ── DELETE BAT (with image cleanup) ── */
if (isset($_GET['delete'])) {
    $did = intval($_GET['delete']);
    $res = $conn->query("SELECT main_image, additional_images FROM cricket_bats WHERE id=$did AND seller_id=$seller_id");
    if ($row = $res->fetch_assoc()) {
        if (!empty($row['main_image']) && file_exists("../" . $row['main_image'])) unlink("../" . $row['main_image']);
        $extra = json_decode($row['additional_images'], true);
        if (is_array($extra)) {
            foreach ($extra as $img) {
                if (file_exists("../" . $img)) unlink("../" . $img);
            }
        }
    }
    $conn->query("DELETE FROM cricket_bats WHERE id=$did AND seller_id=$seller_id");
    header("Location: cricket_bat.php?msg=deleted");
    exit;
}

/* ── TOGGLE VISIBILITY, FEATURED, BEST SELLER ── */
if (isset($_GET['toggle_visible'])) {
    $tid = intval($_GET['toggle_visible']);
    $conn->query("UPDATE cricket_bats SET visible = NOT visible WHERE id=$tid AND seller_id=$seller_id");
    header("Location: cricket_bat.php?msg=toggled");
    exit;
}
if (isset($_GET['toggle_featured'])) {
    $fid = intval($_GET['toggle_featured']);
    $conn->query("UPDATE cricket_bats SET featured = NOT featured WHERE id=$fid AND seller_id=$seller_id");
    header("Location: cricket_bat.php?msg=featured");
    exit;
}
if (isset($_GET['toggle_bestseller'])) {
    $bid = intval($_GET['toggle_bestseller']);
    $conn->query("UPDATE cricket_bats SET best_seller = NOT best_seller WHERE id=$bid AND seller_id=$seller_id");
    header("Location: cricket_bat.php?msg=bestseller");
    exit;
}

/* ── FETCH EDIT DATA (with ownership) ── */
$edit = null;
$edit_extra = [];
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM cricket_bats WHERE id=$eid AND seller_id=$seller_id");
    $edit = $res->fetch_assoc();
    if ($edit) {
        $edit_extra = json_decode($edit['additional_images'], true) ?: [];
    }
}

/* ── HANDLE ADD / UPDATE ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $bat_name       = $conn->real_escape_string(trim($_POST['bat_name']));
    $brand          = $conn->real_escape_string(trim($_POST['brand']));
    $description    = $conn->real_escape_string(trim($_POST['description']));
    $weight         = $conn->real_escape_string($_POST['weight']);
    $original_price = floatval($_POST['original_price']);
    $discount_percent = floatval($_POST['discount_percent']);
    $discount_price = ($discount_percent > 0) ? round($original_price * (1 - $discount_percent/100), 2) : NULL;
    $stock_qty      = intval($_POST['stock_qty']);
    $visible        = isset($_POST['visible']) ? 1 : 0;
    $shop_id        = !empty($_POST['shop_id']) ? (int)$_POST['shop_id'] : NULL;

    // Main image upload
    $main_image = '';
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === 0) {
        $main_image = uploadBatImage($_FILES['main_image'], 'bat');
    }

    // Additional images upload
    $additional = [];
    if (isset($_FILES['additional_images'])) {
        $files = $_FILES['additional_images'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === 0) {
                $fake = [
                    'name' => $files['name'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i]
                ];
                $up = uploadBatImage($fake, 'bat_extra');
                if ($up) $additional[] = $up;
            }
        }
    }

    if (isset($_POST['item_id']) && $_POST['item_id'] != '') { // UPDATE
        $pid = intval($_POST['item_id']);
        $cur = $conn->query("SELECT main_image, additional_images FROM cricket_bats WHERE id=$pid AND seller_id=$seller_id")->fetch_assoc();
        if (!$main_image) $main_image = $cur['main_image'];
        
        $existing_extra = json_decode($cur['additional_images'], true) ?: [];
        if (isset($_POST['delete_extra']) && is_array($_POST['delete_extra'])) {
            $keep = array_diff($existing_extra, $_POST['delete_extra']);
            foreach ($_POST['delete_extra'] as $del) {
                if (file_exists("../" . $del)) unlink("../" . $del);
            }
            $additional = array_merge($keep, $additional);
        } else {
            $additional = array_merge($existing_extra, $additional);
        }
        $json_extra = json_encode(array_values($additional));
        
        $sql = "UPDATE cricket_bats SET
                bat_name='$bat_name', brand='$brand', description='$description',
                weight='$weight', original_price=$original_price, discount_price=" . ($discount_price ? $discount_price : "NULL") . ",
                stock_qty=$stock_qty, main_image='$main_image', additional_images='$json_extra',
                visible=$visible, shop_id=" . ($shop_id ? $shop_id : "NULL") . "
                WHERE id=$pid AND seller_id=$seller_id";
        $conn->query($sql);
        header("Location: cricket_bat.php?msg=updated");
        exit;
    } else { // INSERT
        if (!$main_image) {
            header("Location: cricket_bat.php?msg=noimg");
            exit;
        }
        $json_extra = json_encode($additional);
        $sql = "INSERT INTO cricket_bats (
            seller_id, shop_id, bat_name, brand, description, weight,
            original_price, discount_price, stock_qty, main_image, additional_images, visible
        ) VALUES (
            $seller_id, " . ($shop_id ? $shop_id : "NULL") . ", '$bat_name', '$brand', '$description', '$weight',
            $original_price, " . ($discount_price ? $discount_price : "NULL") . ", $stock_qty, '$main_image',
            '$json_extra', $visible
        )";
        $conn->query($sql);
        header("Location: cricket_bat.php?msg=added");
        exit;
    }
}

/* ── FETCH ALL BATS FOR THIS SELLER (JOIN shops) ── */
$items = $conn->query("
    SELECT b.*, s.shop_name 
    FROM cricket_bats b
    LEFT JOIN shops s ON b.shop_id = s.id
    WHERE b.seller_id = $seller_id
    ORDER BY b.created_at DESC
");

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $edit ? 'Edit Cricket Bat' : 'Add Cricket Bat' ?> | SportsBazaar</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Outfit',sans-serif;background:#f8fafc;color:#1e2937;}
.main-content{margin-left:260px;padding:20px;}
.container{max-width:1300px;margin:25px auto;background:#fff;border-radius:20px;box-shadow:0 4px 20px rgba(0,0,0,0.08);padding:35px;}
h2{font-size:24px;font-weight:700;color:#0f172a;margin-bottom:30px;text-align:center;}
.toast{padding:14px 24px;border-radius:10px;font-weight:600;margin-bottom:25px;display:flex;align-items:center;gap:10px;}
.toast.ok{background:#dcfce7;color:#166534;}
.toast.del{background:#fee2e2;color:#b91c1c;}
.toast.warn{background:#fef3c7;color:#92400e;}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;}
.full-width{grid-column:1/-1;}
.form-group label{font-size:13px;font-weight:600;color:#475569;margin-bottom:7px;display:block;}
.form-group input,.form-group select,.form-group textarea{width:100%;background:#f8fafc;border:1.5px solid #cbd5e1;border-radius:10px;padding:12px 15px;font-size:15px;}
.form-group input:focus,.form-group select:focus{border-color:#f97316;background:#fff;}
.shop-group{display:flex;gap:8px;align-items:center;}
.shop-group select{flex:1;}
.shop-group button{background:#f1f5f9;border:1px solid #cbd5e1;border-radius:10px;width:40px;height:40px;font-size:20px;font-weight:bold;cursor:pointer;color:#f97316;}
.shop-group button:hover{background:#fff2ec;border-color:#f97316;}
.img-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;}
.img-box{border:2px dashed #cbd5e1;border-radius:14px;padding:15px;text-align:center;cursor:pointer;min-height:130px;background:#f8fafc;position:relative;}
.img-box:hover{border-color:#f97316;}
.img-box img{max-height:80px;border-radius:8px;margin-top:8px;margin-left:auto;margin-right:auto;display:block;}
.img-box .lbl{position:absolute;top:10px;left:10px;font-size:10px;font-weight:700;color:#f97316;background:#fff7ed;padding:2px 8px;border-radius:6px;}
.img-box .rm{position:absolute;top:10px;right:10px;width:24px;height:24px;background:#ef4444;color:#fff;border:none;border-radius:50%;font-size:12px;cursor:pointer;display:none;align-items:center;justify-content:center;}
.img-box.has-img .rm{display:flex;}
.btn-submit{padding:13px 40px;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;border:none;border-radius:10px;font-size:15.5px;font-weight:700;cursor:pointer;}
.btn-cancel{margin-left:12px;padding:13px 30px;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;border-radius:10px;text-decoration:none;font-weight:600;}
table{width:100%;border-collapse:collapse;margin-top:15px;}
th,td{padding:12px 15px;border-bottom:1px solid #e2e8f0;}
th{background:#f8fafc;font-weight:600;color:#475569;text-transform:uppercase;font-size:12px;}
tr:hover{background:#f8fafc;}
.td-imgs img{width:45px;height:45px;object-fit:cover;border-radius:6px;margin-right:5px;}
.btn-ed,.btn-dl{padding:5px 12px;border-radius:30px;font-size:12px;font-weight:600;text-decoration:none;display:inline-block;margin:2px;}
.btn-ed{background:#e0f2fe;color:#0369a1;}
.btn-dl{background:#fee2e2;color:#b91c1c;}
.badge{display:inline-block;padding:3px 8px;border-radius:20px;font-size:10px;font-weight:700;}
.badge-green{background:#dcfce7;color:#166534;}
.badge-red{background:#fee2e2;color:#b91c1c;}
.badge-blue{background:#dbeafe;color:#1e40af;}
.original-price{text-decoration:line-through;color:#9ca3af;font-size:12px;margin-right:6px;}
.discounted-price{color:#ef4444;font-weight:800;}
.shop-badge-sm{background:#f0f0fc;color:#4c51bf;padding:2px 8px;border-radius:12px;font-size:11px;white-space:nowrap;}
@media(max-width:992px){.main-content{margin-left:0;}}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:300;}
.modal.show{display:flex;}
.modal-content{background:#fff;border-radius:24px;width:90%;max-width:380px;padding:24px;}
.modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:16px;}
</style>
</head>
<body>
<?php include "sidenav.php"; ?>

<!-- Modal for creating new shop -->
<div id="shopModal" class="modal">
    <div class="modal-content">
        <h4>➕ Create New Shop</h4>
        <form method="post">
            <input type="text" name="new_shop_name" placeholder="e.g., Downtown Store, Online Outlet" required>
            <div class="modal-actions">
                <button type="button" onclick="closeShopModal()">Cancel</button>
                <button type="submit" name="create_shop">Create Shop</button>
            </div>
        </form>
    </div>
</div>

<div class="main-content">
<div class="container">
    <h2><i class="fa <?php echo $edit ? 'fa-pen' : 'fa-plus-circle'; ?>"></i> <?php echo $edit ? 'Edit Cricket Bat' : 'Add New Cricket Bat'; ?></h2>

    <?php
    if($msg === 'added') echo '<div class="toast ok"><i class="fa fa-check-circle"></i> Bat added successfully!</div>';
    if($msg === 'updated') echo '<div class="toast ok"><i class="fa fa-check-circle"></i> Bat updated successfully!</div>';
    if($msg === 'deleted') echo '<div class="toast del"><i class="fa fa-trash"></i> Bat deleted.</div>';
    if($msg === 'noimg') echo '<div class="toast warn"><i class="fa fa-image"></i> Main image is required!</div>';
    if($msg === 'toggled') echo '<div class="toast ok"><i class="fa fa-eye-slash"></i> Visibility toggled.</div>';
    if($msg === 'featured') echo '<div class="toast ok"><i class="fa fa-star"></i> Featured status toggled.</div>';
    if($msg === 'bestseller') echo '<div class="toast ok"><i class="fa fa-trophy"></i> Best Seller status toggled.</div>';
    if(isset($_GET['shop_added'])) echo '<div class="toast ok"><i class="fa fa-store"></i> New shop created! You can now assign bats to it.</div>';
    ?>

    <form method="POST" enctype="multipart/form-data">
        <?php if($edit): ?>
            <input type="hidden" name="item_id" value="<?php echo $edit['id']; ?>">
        <?php endif; ?>
        <div class="form-grid">
            <div class="form-group"><label>Bat Name *</label><input type="text" name="bat_name" required value="<?php echo htmlspecialchars($edit['bat_name']??''); ?>" placeholder="e.g. SS Master 5000"></div>
            <div class="form-group"><label>Brand *</label><input type="text" name="brand" required value="<?php echo htmlspecialchars($edit['brand']??''); ?>" placeholder="SS, SG, MRF, Kookaburra"></div>
            <div class="form-group full-width"><label>Description</label><textarea name="description" rows="3" placeholder="Bat features, material, etc."><?php echo htmlspecialchars($edit['description']??''); ?></textarea></div>
            <div class="form-group"><label>Weight (e.g. 2.8 lb)</label><input type="text" name="weight" value="<?php echo htmlspecialchars($edit['weight']??'2.8 lb'); ?>"></div>
            <div class="form-group"><label>Original Price (Rs.) *</label><input type="number" step="0.01" name="original_price" required value="<?php echo $edit['original_price']??''; ?>"></div>
            <div class="form-group"><label>Discount (%)</label><input type="number" step="0.01" name="discount_percent" value="<?php echo $edit && $edit['discount_price'] ? round((1 - $edit['discount_price']/$edit['original_price'])*100, 2) : '0'; ?>" min="0" max="100"></div>
            <div class="form-group"><label>Stock Quantity</label><input type="number" name="stock_qty" required value="<?php echo $edit['stock_qty']??0; ?>"></div>
            
            <!-- Shop selection -->
            <div class="form-group">
                <label>Shop (Store)</label>
                <div class="shop-group">
                    <select name="shop_id">
                        <option value="">-- No Shop / General --</option>
                        <?php foreach ($shops as $shop):
                            $selected = ($edit && $edit['shop_id'] == $shop['id']) ? 'selected' : '';
                        ?>
                            <option value="<?= $shop['id'] ?>" <?= $selected ?>><?= htmlspecialchars($shop['shop_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="openShopModal()">+</button>
                </div>
            </div>

            <div class="form-group"><label><input type="checkbox" name="visible" value="1" <?php if(empty($edit) || $edit['visible']) echo 'checked'; ?>> Visible on website</label></div>
        </div>

        <!-- Main Image -->
        <div class="form-group full-width"><label>Main Image *</label>
            <div class="img-grid"><div class="img-box <?php echo (!empty($edit['main_image']))?'has-img':''; ?>" id="mainBox" onclick="document.getElementById('mainImgInput').click()">
                <span class="lbl">MAIN</span>
                <img id="mainPreview" src="<?php echo !empty($edit['main_image']) ? '../' . htmlspecialchars($edit['main_image']) : ''; ?>" style="<?php echo !empty($edit['main_image'])?'display:block':'display:none'; ?>">
                <div class="ph" id="mainPh" style="<?php echo !empty($edit['main_image'])?'display:none':'display:block'; ?>"><i class="fa fa-image"></i><br>Upload</div>
                <button type="button" class="rm" onclick="clearMainImage(event)">✕</button>
            </div><input type="file" id="mainImgInput" name="main_image" accept="image/*" style="display:none" onchange="previewMainImage(this)"></div>
        </div>

        <!-- Additional Images (up to 4) -->
        <div class="form-group full-width"><label>Additional Images (optional, max 4)</label>
            <div class="img-grid" id="extraGrid">
                <?php 
                $extra_imgs = [];
                if($edit && !empty($edit['additional_images'])) $extra_imgs = json_decode($edit['additional_images'], true);
                for($i=0;$i<4;$i++):
                    $has = isset($extra_imgs[$i]);
                    $path = $has ? '../' . htmlspecialchars($extra_imgs[$i]) : '';
                ?>
                <div class="img-box <?php echo $has?'has-img':''; ?>" id="exBox<?php echo $i; ?>" onclick="document.getElementById('exInput<?php echo $i; ?>').click()">
                    <span class="lbl">EXTRA <?php echo $i+1; ?></span>
                    <img id="exPrev<?php echo $i; ?>" src="<?php echo $path; ?>" style="<?php echo $has?'display:block':'display:none'; ?>">
                    <div class="ph" style="<?php echo $has?'display:none':'display:block'; ?>"><i class="fa fa-image"></i><br>Extra</div>
                    <button type="button" class="rm" onclick="clearExtraImage(event,<?php echo $i; ?>)">✕</button>
                    <?php if($has && $edit): ?><div><label><input type="checkbox" name="delete_extra[]" value="<?php echo htmlspecialchars($extra_imgs[$i]); ?>"> Delete this</label></div><?php endif; ?>
                </div>
                <input type="file" id="exInput<?php echo $i; ?>" name="additional_images[]" accept="image/*" style="display:none" onchange="previewExtraImage(this,<?php echo $i; ?>)">
                <?php endfor; ?>
            </div>
        </div>

        <div style="margin-top:40px;text-align:center;">
            <button type="submit" name="submit" class="btn-submit"><i class="fa fa-<?php echo $edit?'save':'plus'; ?>"></i> <?php echo $edit?'Update Bat':'Add Bat'; ?></button>
            <?php if($edit): ?><a href="cricket_bat.php" class="btn-cancel">Cancel</a><?php endif; ?>
        </div>
    </form>

    <!-- Existing Bats List -->
    <div style="margin-top:70px;"><h2 style="text-align:left;"><i class="fa fa-list"></i> Your Cricket Bats (<?php echo $items->num_rows; ?>)</h2>
    <div style="overflow-x:auto;">
    <table>
        <thead>
            <tr><th>ID</th><th>Image</th><th>Name / Brand</th><th>Shop</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if($items->num_rows > 0): ?>
            <?php while($row = $items->fetch_assoc()): 
                $has_discount = !empty($row['discount_price']) && $row['discount_price'] > 0 && $row['discount_price'] < $row['original_price'];
                $final_price = $has_discount ? $row['discount_price'] : $row['original_price'];
                $discount_percent = $has_discount ? round((1 - $row['discount_price']/$row['original_price'])*100, 2) : 0;
                $img_src = !empty($row['main_image']) ? '../' . htmlspecialchars($row['main_image']) : 'https://placehold.co/60x60?text=No+Image';
            ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td class="td-imgs">
                    <img src="<?php echo $img_src; ?>" onerror="this.src='https://placehold.co/60x60?text=No+Image'">
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($row['bat_name']); ?></strong><br>
                    <small><?php echo htmlspecialchars($row['brand']); ?></small>
                </td>
                <td>
                    <?php if(!empty($row['shop_name'])): ?>
                        <span class="shop-badge-sm">🏪 <?php echo htmlspecialchars($row['shop_name']); ?></span>
                    <?php else: ?>
                        <span style="color:var(--muted);">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if($has_discount): ?>
                        <span class="original-price">Rs. <?php echo number_format($row['original_price'],2); ?></span>
                        <span class="discounted-price">Rs. <?php echo number_format($final_price,2); ?></span>
                        <small>(<?php echo $discount_percent; ?>% off)</small>
                    <?php else: ?>
                        Rs. <?php echo number_format($final_price,2); ?>
                    <?php endif; ?>
                </td>
                <td><?php echo $row['stock_qty']; ?></td>
                <td>
                    <span class="badge <?php echo $row['visible']?'badge-green':'badge-red'; ?>">
                        <?php echo $row['visible']?'Visible':'Hidden'; ?>
                    </span>
                    <?php if(!empty($row['featured'])): ?><br><span class="badge badge-blue">Featured</span><?php endif; ?>
                    <?php if(!empty($row['best_seller'])): ?><br><span class="badge" style="background:#fef3c7;color:#b45309;">Best Seller</span><?php endif; ?>
                </td>
                <td>
                    <a href="?edit=<?php echo $row['id']; ?>" class="btn-ed">Edit</a>
                    <a href="?delete=<?php echo $row['id']; ?>" class="btn-dl" onclick="return confirm('Delete this bat permanently?')">Delete</a>
                    <a href="?toggle_visible=<?php echo $row['id']; ?>" class="btn-ed"><?php echo $row['visible']?'Hide':'Show'; ?></a>
                    <?php if(empty($row['featured'])): ?>
                        <a href="?toggle_featured=<?php echo $row['id']; ?>" class="btn-ed">Feature</a>
                    <?php else: ?>
                        <a href="?toggle_featured=<?php echo $row['id']; ?>" class="btn-ed">Unfeature</a>
                    <?php endif; ?>
                    <?php if(empty($row['best_seller'])): ?>
                        <a href="?toggle_bestseller=<?php echo $row['id']; ?>" class="btn-ed">Mark Best</a>
                    <?php else: ?>
                        <a href="?toggle_bestseller=<?php echo $row['id']; ?>" class="btn-ed">Remove Best</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="8" style="text-align:center;padding:40px;">No cricket bats added yet. Add your first bat above.</tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    </div>
</div>
</div>

<script>
function previewMainImage(input){ if(!input.files[0]) return; var reader=new FileReader(); reader.onload=function(e){ document.getElementById('mainPreview').src=e.target.result; document.getElementById('mainPreview').style.display='block'; document.getElementById('mainPh').style.display='none'; document.getElementById('mainBox').classList.add('has-img'); }; reader.readAsDataURL(input.files[0]); }
function clearMainImage(e){ e.stopPropagation(); document.getElementById('mainPreview').style.display='none'; document.getElementById('mainPh').style.display='block'; document.getElementById('mainBox').classList.remove('has-img'); document.getElementById('mainImgInput').value=''; }
function previewExtraImage(input,idx){ if(!input.files[0]) return; var reader=new FileReader(); reader.onload=function(e){ document.getElementById('exPrev'+idx).src=e.target.result; document.getElementById('exPrev'+idx).style.display='block'; document.getElementById('exBox'+idx).classList.add('has-img'); var ph=document.querySelector('#exBox'+idx+' .ph'); if(ph) ph.style.display='none'; }; reader.readAsDataURL(input.files[0]); }
function clearExtraImage(e,idx){ e.stopPropagation(); document.getElementById('exPrev'+idx).style.display='none'; document.getElementById('exBox'+idx).classList.remove('has-img'); var ph=document.querySelector('#exBox'+idx+' .ph'); if(ph) ph.style.display='block'; document.getElementById('exInput'+idx).value=''; }

// Shop modal
let modal = document.getElementById('shopModal');
function openShopModal() { modal.classList.add('show'); }
function closeShopModal() { modal.classList.remove('show'); }
modal.addEventListener('click', function(e) { if(e.target === modal) closeShopModal(); });
</script>

</body>
</html>