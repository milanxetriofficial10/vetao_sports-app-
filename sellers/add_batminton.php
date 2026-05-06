<?php
require_once __DIR__ . '/../databases/db.php';
$conn = getDB(); // MySQLi connection

// ------------------ Helper Functions ------------------
function uploadMultipleImagesReturnArray($files, $existingImages = []) {
    $uploadedPaths = $existingImages;
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $dir = __DIR__ . '/../uploads/badminton/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    if (isset($files['product_images']) && is_array($files['product_images']['name'])) {
        $count = count($files['product_images']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($files['product_images']['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($files['product_images']['name'][$i], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $path = $dir . $filename;
                    if (move_uploaded_file($files['product_images']['tmp_name'][$i], $path)) {
                        // Store relative path from project root (without __DIR__)
                        $uploadedPaths[] = '../uploads/badminton/' . $filename;
                    }
                }
            }
        }
    }
    return $uploadedPaths;
}

function deleteImageFiles($imagesJson) {
    $paths = json_decode($imagesJson, true);
    if (is_array($paths)) {
        foreach ($paths as $path) {
            $fullPath = __DIR__ . '/../' . $path;
            if (file_exists($fullPath)) unlink($fullPath);
        }
    }
}

function buildAttributesFromPost($category, $post) {
    $attrs = [];
    switch ($category) {
        case 'Badminton Jerseys':
            if (!empty($post['jersey_sizes']) && is_array($post['jersey_sizes'])) {
                $attrs['sizes'] = array_values($post['jersey_sizes']);
            }
            if (!empty($post['colors']) && is_array($post['colors'])) {
                $colors = array_filter($post['colors'], function($c) { return trim($c) !== ''; });
                if (!empty($colors)) {
                    $attrs['colors'] = array_values($colors);
                }
            }
            break;
        case 'Badminton Shoes':
            if (!empty($post['shoe_size'])) $attrs['shoe_size'] = $post['shoe_size'];
            break;
        case 'Nets':
            if (!empty($post['net_type'])) $attrs['net_type'] = $post['net_type'];
            if (!empty($post['net_height'])) $attrs['net_height'] = $post['net_height'];
            if (!empty($post['net_width'])) $attrs['net_width'] = $post['net_width'];
            break;
    }
    return json_encode($attrs);
}

// Fix image path for display
function getImageUrl($path) {
    if (empty($path)) return '';
    // If it's already a full URL
    if (preg_match('/^https?:\/\//i', $path)) return $path;
    // Remove any leading slashes or '../' inconsistencies
    $path = ltrim($path, '/');
    // Return as is - will be used with base URL
    return $path;
}

// ------------------ CRUD Operations (MySQLi) ------------------
if (isset($_POST['add_product'])) {
    $category = $_POST['category'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $discount_percent = intval($_POST['discount_percent']);
    $stock = intval($_POST['stock']);
    $store_name = trim($_POST['store_name']);
    $rating = floatval($_POST['rating']);
    $attributesJson = buildAttributesFromPost($category, $_POST);

    $allImages = uploadMultipleImagesReturnArray($_FILES, []);
    $imagesJson = json_encode($allImages);
    $main_image = !empty($allImages) ? $allImages[0] : null;

    if ($title && $price >= 0) {
        $sql = "INSERT INTO badminton_items (category, title, description, regular_price, discount_percent, stock, store_name, rating, attributes, images, main_image) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssdiiissss", $category, $title, $description, $price, $discount_percent, $stock, $store_name, $rating, $attributesJson, $imagesJson, $main_image);
        if ($stmt->execute()) {
            $success = "Item added successfully!";
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Title and valid price are required.";
    }
}

if (isset($_POST['update_product'])) {
    $id = intval($_POST['product_id']);
    $category = $_POST['category'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $discount_percent = intval($_POST['discount_percent']);
    $stock = intval($_POST['stock']);
    $store_name = trim($_POST['store_name']);
    $rating = floatval($_POST['rating']);
    $attributesJson = buildAttributesFromPost($category, $_POST);

    $stmt = $conn->prepare("SELECT images, main_image FROM badminton_items WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($currentImagesJson, $currentMain);
    $stmt->fetch();
    $stmt->close();

    $currentImages = json_decode($currentImagesJson, true);
    if (!is_array($currentImages)) $currentImages = [];

    $imagesToKeep = [];
    if (isset($_POST['delete_images'])) {
        $deletedPaths = $_POST['delete_images'];
        foreach ($currentImages as $img) {
            if (!in_array($img, $deletedPaths)) {
                $imagesToKeep[] = $img;
            } else {
                $fullPath = __DIR__ . '/../' . $img;
                if (file_exists($fullPath)) unlink($fullPath);
            }
        }
    } else {
        $imagesToKeep = $currentImages;
    }

    $allImages = uploadMultipleImagesReturnArray($_FILES, $imagesToKeep);
    $finalImagesJson = json_encode($allImages);

    $newMain = null;
    if (isset($_POST['main_image'])) {
        $selectedMain = $_POST['main_image'];
        if ($selectedMain === 'new_first' && !empty($_FILES['product_images']['name'][0])) {
            $newUploads = array_diff($allImages, $imagesToKeep);
            if (!empty($newUploads)) {
                $newMain = reset($newUploads);
            } else {
                $newMain = !empty($allImages) ? $allImages[0] : null;
            }
        } elseif ($selectedMain === 'none') {
            $newMain = null;
        } elseif (!empty($selectedMain) && file_exists(__DIR__ . '/../' . $selectedMain)) {
            $newMain = $selectedMain;
        }
    }
    if (!$newMain && !empty($allImages)) {
        $newMain = $allImages[0];
    }

    $sql = "UPDATE badminton_items SET category=?, title=?, description=?, regular_price=?, discount_percent=?, stock=?, store_name=?, rating=?, attributes=?, images=?, main_image=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssdiiissssi", $category, $title, $description, $price, $discount_percent, $stock, $store_name, $rating, $attributesJson, $finalImagesJson, $newMain, $id);
    if ($stmt->execute()) {
        $success = "Item updated successfully!";
    } else {
        $error = "Update failed: " . $stmt->error;
    }
    $stmt->close();
}

if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("SELECT images FROM badminton_items WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($imagesJson);
    $stmt->fetch();
    $stmt->close();
    if ($imagesJson) deleteImageFiles($imagesJson);
    
    $stmt = $conn->prepare("DELETE FROM badminton_items WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $success = "Item deleted.";
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

$edit_product = null;
if (isset($_GET['edit_id'])) {
    $id = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT * FROM badminton_items WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_product = $result->fetch_assoc();
    $stmt->close();
}

$products = [];
$result = $conn->query("SELECT * FROM badminton_items ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

$categories = [
    'Badminton Rackets', 'Shuttlecocks', 'Badminton Shoes', 'Badminton Jerseys',
    'Shorts / Track Pants', 'Grip Tape / Overgrips', 'Racket Bags', 'Nets',
    'Badminton Socks', 'Wristbands / Headbands', 'String / Racket Accessories', 'Training Equipment'
];

function getDiscountedPrice($price, $percent) {
    return round($price - ($price * $percent / 100), 2);
}


include __DIR__ . '/sidenav.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Badminton Admin - Multi Image, Rating, Main Image</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f4f7f4; font-family: 'Segoe UI', system-ui; }
        .form-section { background: white; border-radius: 28px; padding: 1.8rem; margin-bottom: 2rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
        width: 100%; max-width: 700px;
        margin-left: auto; margin-right: auto;
        margin-top: 60px;
     }
        .product-card { background: white; border-radius: 24px; padding: 1.5rem;
            box-shadow: 0 8px 20px -5px rgba(0,0,0,0.05); margin-bottom: 2rem;
            width: 100%; max-width: 700px; margin-left: auto; margin-right: auto;
     }
        .img-preview { width: 50px; height: 50px; object-fit: cover; border-radius: 12px; margin-right: 5px; margin-bottom: 5px; }
        .img-thumb-list { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-start; }
        .stock-badge { font-size: 0.8rem; padding: 4px 10px; border-radius: 50px; }
        .stock-low { background: #fee2e2; color: #b91c1c; }
        .stock-good { background: #e0f2e0; color: #166534; }
        .rating-stars { color: #ffc107; }
        .attr-badge { background: #e9ecef; padding: 4px 8px; border-radius: 20px; font-size: 0.75rem; display: inline-block; margin: 2px; }
        .color-item { background: #ffc107; color: #000; }
        .dynamic-input-group { display: flex; gap: 8px; margin-bottom: 8px; align-items: center; }
        .dynamic-input-group input { flex: 1; }
        .current-img-wrapper { position: relative; display: inline-block; margin: 5px; }
        .img-delete-check { position: absolute; bottom: 5px; left: 5px; background: rgba(0,0,0,0.6); padding: 2px 5px; border-radius: 4px; }
        .img-delete-check input { margin: 0; transform: scale(1.2); }
    </style>
</head>
<body>


    <div class="form-section">
        <h3><i class="fas fa-<?= $edit_product ? 'pen' : 'plus' ?>-circle"></i> <?= $edit_product ? 'Edit Item' : 'Add New Item' ?></h3>
        <form method="post" enctype="multipart/form-data" class="row g-3" id="mainForm">
            <?php if ($edit_product): ?>
                <input type="hidden" name="product_id" value="<?= $edit_product['id'] ?>">
                <input type="hidden" name="update_product" value="1">
            <?php else: ?>
                <input type="hidden" name="add_product" value="1">
            <?php endif; ?>
            
            <div class="col-md-4">
                <label class="form-label">Category *</label>
                <select name="category" id="categorySelect" class="form-select" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= ($edit_product && $edit_product['category'] == $cat) ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label">Title *</label>
                <input type="text" name="title" class="form-control" value="<?= $edit_product ? htmlspecialchars($edit_product['title']) : '' ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"><?= $edit_product ? htmlspecialchars($edit_product['description']) : '' ?></textarea>
            </div>
            <div class="col-md-3">
                <label class="form-label">Price ($)</label>
                <input type="number" step="0.01" name="price" class="form-control" value="<?= $edit_product ? $edit_product['regular_price'] : '' ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Discount %</label>
                <input type="number" step="1" min="0" max="100" name="discount_percent" class="form-control" value="<?= $edit_product ? $edit_product['discount_percent'] : '0' ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Stock</label>
                <input type="number" name="stock" class="form-control" value="<?= $edit_product ? $edit_product['stock'] : '0' ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Rating (0-5)</label>
                <input type="number" step="0.1" min="0" max="5" name="rating" class="form-control" value="<?= $edit_product ? $edit_product['rating'] : '0' ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Store / Shop Name</label>
                <input type="text" name="store_name" class="form-control" value="<?= $edit_product ? htmlspecialchars($edit_product['store_name']) : '' ?>">
            </div>

            <!-- JERSEY FIELDS (multi size + multi color) -->
            <div id="jerseyFields" class="col-12" style="display: none;">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Available Sizes (select multiple)</label><br>
                        <?php 
                        $selectedSizes = [];
                        if ($edit_product && $edit_product['attributes']) {
                            $attrs = json_decode($edit_product['attributes'], true);
                            if (isset($attrs['sizes']) && is_array($attrs['sizes'])) {
                                $selectedSizes = $attrs['sizes'];
                            }
                        }
                        $allSizes = ['S','M','L','XL','XXL'];
                        foreach ($allSizes as $sz): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="jersey_sizes[]" value="<?= $sz ?>" 
                                    <?= in_array($sz, $selectedSizes) ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= $sz ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Colors (add multiple)</label>
                        <div id="colorContainer">
                            <?php 
                            $selectedColors = [];
                            if ($edit_product && $edit_product['attributes']) {
                                $attrs = json_decode($edit_product['attributes'], true);
                                if (isset($attrs['colors']) && is_array($attrs['colors'])) {
                                    $selectedColors = $attrs['colors'];
                                }
                            }
                            if (empty($selectedColors)) $selectedColors = [''];
                            foreach ($selectedColors as $idx => $col): ?>
                                <div class="dynamic-input-group">
                                    <input type="text" name="colors[]" class="form-control" value="<?= htmlspecialchars($col) ?>" placeholder="e.g., Red">
                                    <button type="button" class="btn btn-sm btn-danger removeColorBtn"><i class="fas fa-minus"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="addColorBtn" class="btn btn-sm btn-secondary mt-1"><i class="fas fa-plus"></i> Add Color</button>
                    </div>
                </div>
            </div>

            <!-- SHOE FIELDS -->
            <div id="shoeFields" class="row g-3" style="display: none;">
                <div class="col-md-6">
                    <label class="form-label">Shoe Size (EU)</label>
                    <select name="shoe_size" class="form-select">
                        <option value="">Select</option>
                        <?php
                        $shoeSizes = [36,37,38,39,40,41,42,43,44,45,46];
                        $currentShoe = ($edit_product && $edit_product['attributes']) ? json_decode($edit_product['attributes'], true)['shoe_size'] ?? '' : '';
                        foreach ($shoeSizes as $sz): ?>
                            <option value="<?= $sz ?>" <?= ($currentShoe == $sz) ? 'selected' : '' ?>><?= $sz ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- NET FIELDS -->
            <div id="netFields" class="row g-3" style="display: none;">
                <div class="col-md-4">
                    <label class="form-label">Net Type</label>
                    <select name="net_type" class="form-select">
                        <option value="">Select</option>
                        <option value="Standard" <?= ($edit_product && json_decode($edit_product['attributes'], true)['net_type'] ?? '') == 'Standard' ? 'selected' : '' ?>>Standard</option>
                        <option value="Professional" <?= ($edit_product && json_decode($edit_product['attributes'], true)['net_type'] ?? '') == 'Professional' ? 'selected' : '' ?>>Professional</option>
                        <option value="Practice" <?= ($edit_product && json_decode($edit_product['attributes'], true)['net_type'] ?? '') == 'Practice' ? 'selected' : '' ?>>Practice</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Height (m)</label>
                    <input type="text" name="net_height" class="form-control" placeholder="e.g., 1.55" value="<?= ($edit_product && json_decode($edit_product['attributes'], true)['net_height'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Width (m)</label>
                    <input type="text" name="net_width" class="form-control" placeholder="e.g., 6.1" value="<?= ($edit_product && json_decode($edit_product['attributes'], true)['net_width'] ?? '') ?>">
                </div>
            </div>

            <!-- Multiple Images + Main Image Selection -->
            <div class="col-12">
                <label class="form-label">Product Images (multiple)</label>
                <input type="file" name="product_images[]" class="form-control" accept="image/*" multiple>
                <?php if ($edit_product && $edit_product['images']): 
                    $existingImages = json_decode($edit_product['images'], true);
                    $currentMain = $edit_product['main_image'];
                    if (is_array($existingImages) && count($existingImages) > 0): ?>
                        <div class="mt-3">
                            <label class="fw-bold">Current images – select which one is MAIN:</label>
                            <div class="img-thumb-list">
                                <?php foreach ($existingImages as $img): 
                                    // Fix image path for display
                                    $displayImg = $img;
                                    if (!empty($displayImg) && !preg_match('/^https?:\/\//i', $displayImg)) {
                                        $displayImg = '../' . ltrim($displayImg, './');
                                    }
                                ?>
                                    <div class="border rounded p-2 text-center" style="width: 100px;">
                                        <img src="<?= htmlspecialchars($displayImg) ?>" class="img-preview" style="width:80px; height:80px; object-fit:cover;" onerror="this.src='https://placehold.co/400x300?text=No+Image'">
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="radio" name="main_image" value="<?= htmlspecialchars($img) ?>" 
                                                <?= ($currentMain == $img) ? 'checked' : '' ?>>
                                            <label class="form-check-label small">Main</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="delete_images[]" value="<?= htmlspecialchars($img) ?>">
                                            <label class="form-check-label small">Delete</label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="main_image" value="new_first" id="newMainRadio">
                                    <label class="form-check-label" for="newMainRadio">
                                        Set the <strong>first newly uploaded image</strong> as main image
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="main_image" value="none" id="noneMainRadio">
                                    <label class="form-check-label" for="noneMainRadio">
                                        No main image (will use first image automatically)
                                    </label>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mt-2 text-muted">No existing images. Upload new ones – the first will be auto‑set as main.</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="mt-2 small text-muted">For new product, first uploaded image will be main image automatically.</div>
                <?php endif; ?>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> <?= $edit_product ? 'Update' : 'Add' ?></button>
                <?php if ($edit_product): ?>
                    <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Items List -->
    <div class="product-card">
        <h3><i class="fas fa-list"></i> All Badminton Items</h3>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr><th>ID</th><th>Main Image</th><th>Title</th><th>Category</th><th>Store</th><th>Price</th><th>Discounted</th><th>Stock</th><th>Attributes</th><th>Rating</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): 
                        $discounted = getDiscountedPrice($p['regular_price'], $p['discount_percent']);
                        $stockClass = $p['stock'] <= 3 ? 'stock-low' : 'stock-good';
                        $images = json_decode($p['images'], true);
                        $mainImg = $p['main_image'];
                        
                        // Fix main image path for display
                        $displayMainImg = '';
                        if (!empty($mainImg)) {
                            if (preg_match('/^https?:\/\//i', $mainImg)) {
                                $displayMainImg = $mainImg;
                            } else {
                                $displayMainImg = '../' . ltrim($mainImg, './');
                            }
                        } elseif (is_array($images) && count($images) > 0) {
                            $firstImg = $images[0];
                            if (preg_match('/^https?:\/\//i', $firstImg)) {
                                $displayMainImg = $firstImg;
                            } else {
                                $displayMainImg = '../' . ltrim($firstImg, './');
                            }
                        }
                        
                        $rating = floatval($p['rating']);
                        $fullStars = floor($rating);
                        $halfStar = ($rating - $fullStars) >= 0.5;

                        $attrs = json_decode($p['attributes'], true);
                        $attrHtml = '';
                        if (is_array($attrs)) {
                            foreach ($attrs as $key => $val) {
                                if (is_array($val)) {
                                    $display = implode(', ', $val);
                                    $attrHtml .= '<span class="attr-badge"><strong>' . ucfirst($key) . ':</strong> ' . htmlspecialchars($display) . '</span> ';
                                } else {
                                    $attrHtml .= '<span class="attr-badge"><strong>' . ucfirst(str_replace('_', ' ', $key)) . ':</strong> ' . htmlspecialchars($val) . '</span> ';
                                }
                            }
                        }
                        if (empty($attrHtml)) $attrHtml = '—';
                    ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td>
                            <?php if ($displayMainImg): ?>
                                <img src="<?= htmlspecialchars($displayMainImg) ?>" class="img-preview" style="width:60px; height:60px; object-fit:cover;" onerror="this.src='https://placehold.co/400x300?text=No+Image'">
                            <?php else: ?>
                                <i class="fas fa-image fa-lg text-secondary"></i>
                            <?php endif; ?>
                        </td>
                        <td class="fw-semibold"><?= htmlspecialchars($p['title']) ?></td>
                        <td><?= htmlspecialchars($p['category']) ?></td>
                        <td><?= htmlspecialchars($p['store_name'] ?: '—') ?></td>
                        <td>$<?= number_format($p['regular_price'], 2) ?></td>
                        <td>
                            <?php if($p['discount_percent'] > 0): ?>
                                <span class="badge bg-danger">-<?= $p['discount_percent'] ?>%</span><br>
                                <strong>$<?= number_format($discounted, 2) ?></strong>
                                <del class="small">$<?= number_format($p['regular_price'], 2) ?></del>
                            <?php else: ?>
                                $<?= number_format($p['regular_price'], 2) ?>
                            <?php endif; ?>
                        </td>
                        <td><span class="stock-badge <?= $stockClass ?>"><?= $p['stock'] ?> pcs</span></td>
                        <td style="max-width: 200px;"><?= $attrHtml ?></td>
                        <td>
                            <?php if($rating > 0): ?>
                                <span class="rating-stars">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <?php if($i <= $fullStars): ?>
                                            <i class="fas fa-star"></i>
                                        <?php elseif($halfStar && $i == $fullStars+1): ?>
                                            <i class="fas fa-star-half-alt"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </span>
                                <span class="small">(<?= number_format($rating,1) ?>)</span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?edit_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i> Edit</a>
                            <a href="?delete_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete permanently? All images will be removed.')"><i class="fas fa-trash"></i> Del</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(count($products) == 0): ?>
                        <tr><td colspan="11" class="text-center text-muted">No items yet. Add your first badminton product above.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Toggle category-specific fields
    const catSelect = document.getElementById('categorySelect');
    const jerseyDiv = document.getElementById('jerseyFields');
    const shoeDiv = document.getElementById('shoeFields');
    const netDiv = document.getElementById('netFields');

    function toggleAttributeFields() {
        const cat = catSelect.value;
        jerseyDiv.style.display = 'none';
        shoeDiv.style.display = 'none';
        netDiv.style.display = 'none';
        if (cat === 'Badminton Jerseys') jerseyDiv.style.display = 'block';
        else if (cat === 'Badminton Shoes') shoeDiv.style.display = 'flex';
        else if (cat === 'Nets') netDiv.style.display = 'flex';
    }
    catSelect.addEventListener('change', toggleAttributeFields);
    toggleAttributeFields();

    // Dynamic color inputs
    const colorContainer = document.getElementById('colorContainer');
    const addColorBtn = document.getElementById('addColorBtn');

    function addColorInput(value = '') {
        const div = document.createElement('div');
        div.className = 'dynamic-input-group';
        div.innerHTML = `
            <input type="text" name="colors[]" class="form-control" value="${escapeHtml(value)}" placeholder="e.g., Red">
            <button type="button" class="btn btn-sm btn-danger removeColorBtn"><i class="fas fa-minus"></i></button>
        `;
        colorContainer.appendChild(div);
        div.querySelector('.removeColorBtn').addEventListener('click', function() {
            div.remove();
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // Attach remove event to existing remove buttons
    document.querySelectorAll('.removeColorBtn').forEach(btn => {
        btn.addEventListener('click', function() {
            btn.closest('.dynamic-input-group').remove();
        });
    });
    addColorBtn.addEventListener('click', () => addColorInput(''));
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>