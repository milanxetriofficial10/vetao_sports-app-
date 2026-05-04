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

/* ── CREATE TABLE IF NOT EXISTS (with seller_id, shop_id) ── */
$conn->query("CREATE TABLE IF NOT EXISTS sport_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    shop_id INT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    discount INT DEFAULT 0,
    sport_type VARCHAR(50) NOT NULL,
    item_type VARCHAR(50),
    rating FLOAT DEFAULT 0,
    is_top TINYINT(1) DEFAULT 0,
    is_new TINYINT(1) DEFAULT 0,
    sell ENUM('Yes','No') DEFAULT 'No',
    image VARCHAR(255),
    image2 VARCHAR(255),
    image3 VARCHAR(255),
    image4 VARCHAR(255),
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
$main_shop_name = $seller['shop_name'];

/* ── CREATE NEW SHOP (AJAX/POST) ── */
if (isset($_POST['create_shop']) && !empty(trim($_POST['new_shop_name']))) {
    $new_shop = trim($_POST['new_shop_name']);
    $stmt = $conn->prepare("INSERT INTO shops (seller_id, shop_name) VALUES (?, ?)");
    $stmt->bind_param("is", $seller_id, $new_shop);
    $stmt->execute();
    $stmt->close();
    header("Location: add_ball.php?shop_added=1");
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

/* ── IMAGE UPLOAD HELPER ── */
function uploadSportImage($file, $prefix) {
    if (empty($file['name'])) return '';
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed)) return '';
    
    $uploadDir = "../uploads/sport_items/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $name = $prefix . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
    $dest = $uploadDir . $name;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return 'uploads/sport_items/' . $name;
    }
    return '';
}

/* ── DELETE ITEM (with image cleanup & ownership) ── */
if (isset($_GET['delete'])) {
    $did = intval($_GET['delete']);
    // Fetch image paths
    $stmt = $conn->prepare("SELECT image, image2, image3, image4 FROM sport_items WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $did, $seller_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        foreach (['image','image2','image3','image4'] as $col) {
            if (!empty($row[$col]) && file_exists("../" . $row[$col])) {
                unlink("../" . $row[$col]);
            }
        }
    }
    $stmt->close();
    $stmt = $conn->prepare("DELETE FROM sport_items WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $did, $seller_id);
    $stmt->execute();
    $stmt->close();
    header("Location: add_ball.php?msg=deleted");
    exit;
}

/* ── FETCH EDIT DATA ── */
$edit = null;
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM sport_items WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $eid, $seller_id);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* ── HANDLE ADD / UPDATE ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $title       = $conn->real_escape_string(trim($_POST['title']));
    $desc        = $conn->real_escape_string(trim($_POST['description']));
    $price       = floatval($_POST['price']);
    $discount    = intval($_POST['discount']);
    $sport_type  = $conn->real_escape_string($_POST['sport_type']);
    $item_type   = $conn->real_escape_string($_POST['item_type']);
    $rating      = floatval($_POST['rating']);
    $is_top      = isset($_POST['is_top']) ? 1 : 0;
    $is_new      = isset($_POST['is_new']) ? 1 : 0;
    $sell        = $conn->real_escape_string($_POST['sell']);
    $shop_id     = !empty($_POST['shop_id']) ? (int)$_POST['shop_id'] : NULL;

    // Upload images
    $img1 = uploadSportImage($_FILES['image'], 'item1');
    $img2 = uploadSportImage($_FILES['image2'], 'item2');
    $img3 = uploadSportImage($_FILES['image3'], 'item3');
    $img4 = uploadSportImage($_FILES['image4'], 'item4');

    if (isset($_POST['item_id']) && $_POST['item_id'] != '') {
        // UPDATE mode
        $iid = intval($_POST['item_id']);
        // Keep old images if no new upload
        if (!$img1) $img1 = $conn->real_escape_string($_POST['old_img1'] ?? '');
        if (!$img2) $img2 = $conn->real_escape_string($_POST['old_img2'] ?? '');
        if (!$img3) $img3 = $conn->real_escape_string($_POST['old_img3'] ?? '');
        if (!$img4) $img4 = $conn->real_escape_string($_POST['old_img4'] ?? '');

        $stmt = $conn->prepare("UPDATE sport_items SET 
            title=?, description=?, price=?, discount=?, sport_type=?, item_type=?, 
            rating=?, is_top=?, is_new=?, sell=?, shop_id=?,
            image=?, image2=?, image3=?, image4=?
            WHERE id=? AND seller_id=?");
        $stmt->bind_param("ssdissiissssssii", 
            $title, $desc, $price, $discount, $sport_type, $item_type,
            $rating, $is_top, $is_new, $sell, $shop_id,
            $img1, $img2, $img3, $img4, $iid, $seller_id);
        $stmt->execute();
        $stmt->close();
        header("Location: add_ball.php?msg=updated");
        exit;
    } else {
        // INSERT mode
        if (!$img1) {
            header("Location: add_ball.php?msg=noimg");
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO sport_items 
            (seller_id, shop_id, title, description, price, discount, sport_type, item_type, rating, is_top, is_new, sell, image, image2, image3, image4)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissdissiissssss", 
            $seller_id, $shop_id, $title, $desc, $price, $discount, $sport_type, $item_type,
            $rating, $is_top, $is_new, $sell, $img1, $img2, $img3, $img4);
        $stmt->execute();
        $stmt->close();
        header("Location: add_ball.php?msg=added");
        exit;
    }
}

/* ── FETCH ALL ITEMS FOR THIS SELLER (JOIN shops) ── */
$items = $conn->prepare("
    SELECT i.*, s.shop_name 
    FROM sport_items i
    LEFT JOIN shops s ON i.shop_id = s.id
    WHERE i.seller_id = ?
    ORDER BY i.id DESC
");
$items->bind_param("i", $seller_id);
$items->execute();
$items = $items->get_result();

$msg = $_GET['msg'] ?? '';

$sports = ['Football','Cricket Balls','Tennis','Basketball','Volleyball','Badminton','Boxing','Cycling','Rugby','Gym','Swimming','Other'];
$types  = ['Standard','Pro','Training','Match','Size 3','Size 4','Size 5','Junior','Senior'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sport Items Manager | SportsBazaar</title>
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
        .img-box img{max-height:80px;border-radius:8px;margin-top:8px;}
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
        <h2><i class="fa fa-<?php echo $edit ? 'pen' : 'plus-circle'; ?>"></i> 
            <?php echo $edit ? 'Edit Sport Item' : 'Add New Sport Item'; ?>
        </h2>

        <?php
        if($msg==='added')   echo '<div class="toast ok"><i class="fa fa-check-circle"></i> Item added successfully!</div>';
        if($msg==='updated') echo '<div class="toast ok"><i class="fa fa-check-circle"></i> Item updated successfully!</div>';
        if($msg==='deleted') echo '<div class="toast del"><i class="fa fa-trash"></i> Item deleted.</div>';
        if($msg==='noimg')   echo '<div class="toast warn"><i class="fa fa-image"></i> Main image is required!</div>';
        if(isset($_GET['shop_added'])) echo '<div class="toast ok"><i class="fa fa-store"></i> New shop created!</div>';
        ?>

        <form method="POST" enctype="multipart/form-data">
            <?php if($edit): ?>
                <input type="hidden" name="item_id" value="<?php echo $edit['id']; ?>">
                <input type="hidden" name="old_img1" value="<?php echo htmlspecialchars($edit['image']??''); ?>">
                <input type="hidden" name="old_img2" value="<?php echo htmlspecialchars($edit['image2']??''); ?>">
                <input type="hidden" name="old_img3" value="<?php echo htmlspecialchars($edit['image3']??''); ?>">
                <input type="hidden" name="old_img4" value="<?php echo htmlspecialchars($edit['image4']??''); ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Title *</label>
                    <input type="text" name="title" required value="<?php echo htmlspecialchars($edit['title']??'');?>" placeholder="e.g. Adidas FIFA Pro Football">
                </div>

                <div class="form-group full-width">
                    <label>Description</label>
                    <textarea name="description" placeholder="Product description..."><?php echo htmlspecialchars($edit['description']??'');?></textarea>
                </div>

                <div class="form-group">
                    <label>Sport Type *</label>
                    <select name="sport_type" required>
                        <?php foreach($sports as $s): ?>
                            <option value="<?php echo $s;?>" <?php if(($edit['sport_type']??'') === $s) echo 'selected'; ?>><?php echo $s;?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Item Type</label>
                    <select name="item_type">
                        <?php foreach($types as $t): ?>
                            <option value="<?php echo $t;?>" <?php if(($edit['item_type']??'') === $t) echo 'selected'; ?>><?php echo $t;?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Price (Rs.) *</label>
                    <input type="number" name="price" required min="0" step="0.01" value="<?php echo $edit['price']??'';?>">
                </div>

                <div class="form-group">
                    <label>Discount (%)</label>
                    <input type="number" name="discount" min="0" max="100" value="<?php echo $edit['discount']??0;?>">
                </div>

                <div class="form-group">
                    <label>Rating (0–5)</label>
                    <input type="number" name="rating" min="0" max="5" step="0.1" value="<?php echo $edit['rating']??'';?>">
                </div>

                <div class="form-group">
                    <label>Trending</label>
                    <select name="sell">
                        <option value="No" <?php if(($edit['sell']??'')==='No') echo 'selected';?>>No</option>
                        <option value="Yes" <?php if(($edit['sell']??'')==='Yes') echo 'selected';?>>Yes — Trending</option>
                    </select>
                </div>

                <!-- Shop Selection -->
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

                <div class="form-group full-width">
                    <label>Flags</label>
                    <div style="display:flex; gap:25px; margin-top:8px;">
                        <label><input type="checkbox" name="is_top" <?php if(!empty($edit['is_top'])) echo 'checked';?>> Top Pick</label>
                        <label><input type="checkbox" name="is_new" <?php if(!empty($edit['is_new'])) echo 'checked';?>> New Arrival</label>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label>Images (Main image is required)</label>
                    <div class="img-grid">
                        <?php
                        $imgFields = [
                            ['name'=>'image', 'label'=>'MAIN *'],
                            ['name'=>'image2','label'=>'EXTRA 1'],
                            ['name'=>'image3','label'=>'EXTRA 2'],
                            ['name'=>'image4','label'=>'EXTRA 3']
                        ];
                        foreach($imgFields as $idx => $f):
                            $old = $edit ? ($edit[$f['name']] ?? '') : '';
                            $has = !empty($old);
                        ?>
                        <div class="img-box <?php echo $has ? 'has-img' : ''; ?>" id="box<?php echo $idx;?>" 
                             onclick="document.getElementById('fi<?php echo $idx;?>').click()">
                            <span class="lbl"><?php echo $f['label']; ?></span>
                            <img id="prev<?php echo $idx;?>" src="<?php echo $has ? htmlspecialchars($old) : ''; ?>" 
                                 style="<?php echo $has ? 'display:block' : 'display:none'; ?>">
                            <div class="ph" id="ph<?php echo $idx;?>" style="<?php echo $has ? 'display:none' : 'display:block'; ?>">
                                <i class="fa fa-image" style="font-size:28px;color:#94a3b8;"></i><br>
                                <?php echo $idx===0 ? 'Main Image' : 'Extra'; ?>
                            </div>
                            <button type="button" class="rm" onclick="clearImg(event, <?php echo $idx; ?>)">✕</button>
                        </div>
                        <input type="file" id="fi<?php echo $idx;?>" name="<?php echo $f['name'];?>" 
                               accept="image/*" style="display:none" onchange="previewImg(this, <?php echo $idx; ?>)">
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div style="margin-top:40px; text-align:center;">
                <button type="submit" name="submit" class="btn-submit">
                    <i class="fa fa-<?php echo $edit ? 'save' : 'plus'; ?>"></i> 
                    <?php echo $edit ? 'Update Item' : 'Add Item'; ?>
                </button>
                <?php if($edit): ?>
                    <a href="add_ball.php" class="btn-cancel">Cancel</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Table: Seller's Items -->
        <div style="margin-top:70px;">
            <h2 style="text-align:left;"><i class="fa fa-list"></i> Your Sport Items (<?php echo $items->num_rows; ?>)</h2>
            
            <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Images</th>
                        <th>Title</th>
                        <th>Sport</th>
                        <th>Type</th>
                        <th>Shop</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                $sn = 1;
                if($items->num_rows > 0):
                    while($row = $items->fetch_assoc()):
                        $final_price = $row['discount'] > 0 ? round($row['price'] * (1 - $row['discount']/100)) : $row['price'];
                ?>
                    <tr>
                        <td><?php echo $sn++; ?></td>
                        <td class="td-imgs">
                            <img src="<?php echo htmlspecialchars($row['image']); ?>" onerror="this.src='https://placehold.co/60x60?text=No+Image'">
                            <?php if(!empty($row['image2'])) echo '<img src="'.htmlspecialchars($row['image2']).'" alt="">'; ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['sport_type']); ?></td>
                        <td><?php echo htmlspecialchars($row['item_type']); ?></td>
                        <td>
                            <?php if(!empty($row['shop_name'])): ?>
                                <span class="shop-badge-sm">🏪 <?php echo htmlspecialchars($row['shop_name']); ?></span>
                            <?php else: ?>
                                <span style="color:var(--muted);">—</span>
                            <?php endif; ?>
                         </td>
                        <td><strong>Rs. <?php echo number_format($final_price); ?></strong></td>
                        <td>
                            <a href="?edit=<?php echo $row['id']; ?>" class="btn-ed">Edit</a>
                            <a href="?delete=<?php echo $row['id']; ?>" class="btn-dl" 
                               onclick="return confirm('Delete this item?')">Delete</a>
                         </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr class="empty-row"><td colspan="8" style="text-align:center;padding:40px;">No items yet. Add your first sport item above.</td></tr>
                <?php endif; ?>
                </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function previewImg(input, idx) {
    if (!input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('prev'+idx).src = e.target.result;
        document.getElementById('prev'+idx).style.display = 'block';
        document.getElementById('ph'+idx).style.display = 'none';
        document.getElementById('box'+idx).classList.add('has-img');
    };
    reader.readAsDataURL(input.files[0]);
}

function clearImg(e, idx) {
    e.stopPropagation();
    document.getElementById('prev'+idx).style.display = 'none';
    document.getElementById('ph'+idx).style.display = 'block';
    document.getElementById('box'+idx).classList.remove('has-img');
    document.getElementById('fi'+idx).value = '';
}

// Shop modal
let modal = document.getElementById('shopModal');
function openShopModal() { modal.classList.add('show'); }
function closeShopModal() { modal.classList.remove('show'); }
modal.addEventListener('click', function(e) { if(e.target === modal) closeShopModal(); });
</script>
</body>
</html>