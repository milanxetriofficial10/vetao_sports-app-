<?php
require_once __DIR__ . '/../databases/db.php';
$conn = getDB(); // MySQLi connection

// ------------------ Helper Functions ------------------
function uploadMultipleImagesReturnArray($files, $existingImages = []) {
    $uploadedPaths = $existingImages;
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $dir = __DIR__ . '/../uploads/boxing/';
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
                        $uploadedPaths[] = '../uploads/boxing/' . $filename;
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
        case 'Boxing Gloves':
            if (!empty($post['sizes']) && is_array($post['sizes'])) {
                $sizes = array_values(array_filter($post['sizes']));
                if (!empty($sizes)) $attrs['sizes'] = $sizes;
            }
            if (!empty($post['colors']) && is_array($post['colors'])) {
                $colors = array_values(array_filter($post['colors']));
                if (!empty($colors)) $attrs['colors'] = $colors;
            }
            if (!empty($post['weights']) && is_array($post['weights'])) {
                $weights = array_values(array_filter($post['weights']));
                if (!empty($weights)) $attrs['weights'] = $weights;
            }
            break;
            
        case 'Punching Bag':
            if (!empty($post['bag_weights']) && is_array($post['bag_weights'])) {
                $bag_weights = array_values(array_filter($post['bag_weights']));
                if (!empty($bag_weights)) $attrs['bag_weights'] = $bag_weights;
            }
            if (!empty($post['bag_types']) && is_array($post['bag_types'])) {
                $bag_types = array_values(array_filter($post['bag_types']));
                if (!empty($bag_types)) $attrs['bag_types'] = $bag_types;
            }
            if (!empty($post['materials']) && is_array($post['materials'])) {
                $materials = array_values(array_filter($post['materials']));
                if (!empty($materials)) $attrs['materials'] = $materials;
            }
            break;
            
        case 'Hand Wraps':
            if (!empty($post['lengths']) && is_array($post['lengths'])) {
                $lengths = array_values(array_filter($post['lengths']));
                if (!empty($lengths)) $attrs['lengths'] = $lengths;
            }
            if (!empty($post['wrap_colors']) && is_array($post['wrap_colors'])) {
                $wrap_colors = array_values(array_filter($post['wrap_colors']));
                if (!empty($wrap_colors)) $attrs['colors'] = $wrap_colors;
            }
            break;
            
        case 'Boxing Shoes':
            if (!empty($post['shoe_sizes']) && is_array($post['shoe_sizes'])) {
                $shoe_sizes = array_values(array_filter($post['shoe_sizes']));
                if (!empty($shoe_sizes)) $attrs['sizes'] = $shoe_sizes;
            }
            if (!empty($post['shoe_colors']) && is_array($post['shoe_colors'])) {
                $shoe_colors = array_values(array_filter($post['shoe_colors']));
                if (!empty($shoe_colors)) $attrs['colors'] = $shoe_colors;
            }
            break;
            
        case 'Mouth Guard':
        case 'Head Guard':
        case 'Focus Pads':
        case 'Boxing Shorts':
            if (!empty($post['guard_sizes']) && is_array($post['guard_sizes'])) {
                $guard_sizes = array_values(array_filter($post['guard_sizes']));
                if (!empty($guard_sizes)) $attrs['sizes'] = $guard_sizes;
            }
            if (!empty($post['guard_colors']) && is_array($post['guard_colors'])) {
                $guard_colors = array_values(array_filter($post['guard_colors']));
                if (!empty($guard_colors)) $attrs['colors'] = $guard_colors;
            }
            if (!empty($post['guard_materials']) && is_array($post['guard_materials'])) {
                $guard_materials = array_values(array_filter($post['guard_materials']));
                if (!empty($guard_materials)) $attrs['materials'] = $guard_materials;
            }
            break;
    }
    
    // CRITICAL FIX: Always return valid JSON - empty object if no attributes
    if (empty($attrs)) {
        return '{}';  // Empty JSON object, not empty string or empty array
    }
    
    $json = json_encode($attrs);
    // Validate JSON
    if ($json === false || $json === null) {
        return '{}';
    }
    return $json;
}

// Get image URL for display
function getImageUrl($path) {
    if (empty($path)) return '';
    if (preg_match('/^https?:\/\//i', $path)) return $path;
    return '../' . ltrim($path, './');
}

// ------------------ CRUD Operations ------------------
if (isset($_POST['add_product'])) {
    $category = $_POST['category'];
    $brand = trim($_POST['brand']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $discount_percent = intval($_POST['discount_percent']);
    $stock = intval($_POST['stock']);
    $store_name = trim($_POST['store_name']);
    $rating = floatval($_POST['rating']);
    $is_top = isset($_POST['is_top']) ? 1 : 0;
    $is_new = isset($_POST['is_new']) ? 1 : 0;
    $attributesJson = buildAttributesFromPost($category, $_POST);

    $allImages = uploadMultipleImagesReturnArray($_FILES, []);
    $imagesJson = json_encode($allImages);
    $main_image = !empty($allImages) ? $allImages[0] : null;

    if ($title && $price >= 0) {
        $sql = "INSERT INTO boxing_items (category, brand, title, description, regular_price, discount_percent, stock, store_name, rating, is_top, is_new, attributes, images, main_image) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        // Note: attributesJson is now guaranteed to be valid JSON
        $stmt->bind_param("ssssdiiisdiiss", $category, $brand, $title, $description, $price, $discount_percent, $stock, $store_name, $rating, $is_top, $is_new, $attributesJson, $imagesJson, $main_image);
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
    $brand = trim($_POST['brand']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $discount_percent = intval($_POST['discount_percent']);
    $stock = intval($_POST['stock']);
    $store_name = trim($_POST['store_name']);
    $rating = floatval($_POST['rating']);
    $is_top = isset($_POST['is_top']) ? 1 : 0;
    $is_new = isset($_POST['is_new']) ? 1 : 0;
    $attributesJson = buildAttributesFromPost($category, $_POST);

    $stmt = $conn->prepare("SELECT images, main_image FROM boxing_items WHERE id = ?");
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

    $sql = "UPDATE boxing_items SET category=?, brand=?, title=?, description=?, regular_price=?, discount_percent=?, stock=?, store_name=?, rating=?, is_top=?, is_new=?, attributes=?, images=?, main_image=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssdiiisdiisssi", $category, $brand, $title, $description, $price, $discount_percent, $stock, $store_name, $rating, $is_top, $is_new, $attributesJson, $finalImagesJson, $newMain, $id);
    if ($stmt->execute()) {
        $success = "Item updated successfully!";
    } else {
        $error = "Update failed: " . $stmt->error;
    }
    $stmt->close();
}

if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("SELECT images FROM boxing_items WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($imagesJson);
    $stmt->fetch();
    $stmt->close();
    if ($imagesJson) deleteImageFiles($imagesJson);
    
    $stmt = $conn->prepare("DELETE FROM boxing_items WHERE id = ?");
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
    $stmt = $conn->prepare("SELECT * FROM boxing_items WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_product = $result->fetch_assoc();
    $stmt->close();
}

$products = [];
$result = $conn->query("SELECT * FROM boxing_items ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

$categories = [
    'Boxing Gloves',
    'Punching Bag',
    'Hand Wraps',
    'Boxing Shoes',
    'Mouth Guard',
    'Head Guard',
    'Focus Pads',
    'Boxing Shorts'
];

function getDiscountedPrice($price, $percent) {
    return round($price - ($price * $percent / 100), 2);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boxing Items Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f4f7f4; font-family: 'Segoe UI', system-ui; }
        .admin-header { background: linear-gradient(135deg, #8B0000, #CC0000); color: white; padding: 1rem 0; }
        .form-section { background: white; border-radius: 28px; padding: 1.8rem; margin-bottom: 2rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
        .product-card { background: white; border-radius: 24px; padding: 1.5rem; }
        .img-preview { width: 50px; height: 50px; object-fit: cover; border-radius: 12px; margin-right: 5px; margin-bottom: 5px; }
        .img-thumb-list { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-start; }
        .stock-badge { font-size: 0.8rem; padding: 4px 10px; border-radius: 50px; }
        .stock-low { background: #fee2e2; color: #b91c1c; }
        .stock-good { background: #e0f2e0; color: #166534; }
        .rating-stars { color: #ffc107; }
        .attr-badge { background: #e9ecef; padding: 4px 8px; border-radius: 20px; font-size: 0.75rem; display: inline-block; margin: 2px; }
        .dynamic-input-group { display: flex; gap: 8px; margin-bottom: 8px; align-items: center; }
        .dynamic-input-group input, .dynamic-input-group select { flex: 1; }
    </style>
</head>
<body>
<div class="admin-header">
    <div class="container d-flex justify-content-between align-items-center">
        <div><h1 class="h3 mb-0"><i class="fas fa-fist-raised"></i> Boxing Items Manager</h1><p class="mb-0 opacity-75">Manage Gloves, Bags, Wraps, Shoes & More</p></div>
    </div>
</div>
<div class="container my-4">
    <?php if (isset($success)): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="form-section">
        <h3><i class="fas fa-<?= $edit_product ? 'pen' : 'plus' ?>-circle"></i> <?= $edit_product ? 'Edit Item' : 'Add New Item' ?></h3>
        <form method="post" enctype="multipart/form-data" class="row g-3" id="mainForm">
            <?php if ($edit_product): ?>
                <input type="hidden" name="product_id" value="<?= $edit_product['id'] ?>">
                <input type="hidden" name="update_product" value="1">
            <?php else: ?>
                <input type="hidden" name="add_product" value="1">
            <?php endif; ?>
            
            <div class="col-md-3">
                <label class="form-label">Category *</label>
                <select name="category" id="categorySelect" class="form-select" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= ($edit_product && $edit_product['category'] == $cat) ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Brand *</label>
                <input type="text" name="brand" class="form-control" value="<?= $edit_product ? htmlspecialchars($edit_product['brand']) : '' ?>" placeholder="e.g., Venum, Everlast, RDX" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Title *</label>
                <input type="text" name="title" class="form-control" value="<?= $edit_product ? htmlspecialchars($edit_product['title']) : '' ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"><?= $edit_product ? htmlspecialchars($edit_product['description']) : '' ?></textarea>
            </div>
            <div class="col-md-2">
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
            <div class="col-md-4">
                <label class="form-label">Store / Shop Name</label>
                <input type="text" name="store_name" class="form-control" value="<?= $edit_product ? htmlspecialchars($edit_product['store_name']) : '' ?>">
            </div>
            
            <div class="col-md-2">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="is_top" value="1" id="is_top" <?= ($edit_product && $edit_product['is_top']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_top">Top Pick (⭐)</label>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="is_new" value="1" id="is_new" <?= ($edit_product && $edit_product['is_new']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_new">New Arrival</label>
                </div>
            </div>

            <!-- ==================== BOXING GLOVES ==================== -->
            <div id="gloveFields" class="col-12" style="display: none;">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Sizes (Oz) - Add Multiple</label>
                        <div id="gloveSizesContainer">
                            <?php 
                            $sizes = ['8oz', '10oz', '12oz', '14oz', '16oz', '18oz'];
                            $selectedSizes = [];
                            if ($edit_product && $edit_product['attributes']) {
                                $attrs = json_decode($edit_product['attributes'], true);
                                if (isset($attrs['sizes']) && is_array($attrs['sizes'])) {
                                    $selectedSizes = $attrs['sizes'];
                                }
                            }
                            if (empty($selectedSizes)) $selectedSizes = [''];
                            foreach ($selectedSizes as $idx => $val): ?>
                                <div class="dynamic-input-group">
                                    <select name="sizes[]" class="form-select">
                                        <option value="">Select Size</option>
                                        <?php foreach ($sizes as $sz): ?>
                                            <option value="<?= $sz ?>" <?= ($val == $sz) ? 'selected' : '' ?>><?= $sz ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1 addGloveSizeBtn"><i class="fas fa-plus"></i> Add Size</button>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Colors - Add Multiple</label>
                        <div id="gloveColorsContainer">
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
                                    <input type="text" name="colors[]" class="form-control" value="<?= htmlspecialchars($col) ?>" placeholder="e.g., Red, Black">
                                    <button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1 addGloveColorBtn"><i class="fas fa-plus"></i> Add Color</button>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Weights - Add Multiple</label>
                        <div id="gloveWeightsContainer">
                            <?php 
                            $weights = ['Light (8-10oz)', 'Medium (12-14oz)', 'Heavy (16oz+)'];
                            $selectedWeights = [];
                            if ($edit_product && $edit_product['attributes']) {
                                $attrs = json_decode($edit_product['attributes'], true);
                                if (isset($attrs['weights']) && is_array($attrs['weights'])) {
                                    $selectedWeights = $attrs['weights'];
                                }
                            }
                            if (empty($selectedWeights)) $selectedWeights = [''];
                            foreach ($selectedWeights as $idx => $wt): ?>
                                <div class="dynamic-input-group">
                                    <select name="weights[]" class="form-select">
                                        <option value="">Select Weight</option>
                                        <?php foreach ($weights as $w): ?>
                                            <option value="<?= $w ?>" <?= ($wt == $w) ? 'selected' : '' ?>><?= $w ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1 addGloveWeightBtn"><i class="fas fa-plus"></i> Add Weight</button>
                    </div>
                </div>
            </div>

            <!-- ==================== PUNCHING BAG ==================== -->
            <div id="bagFields" class="col-12" style="display: none;">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Bag Weights (kg/lbs)</label>
                        <div id="bagWeightsContainer">
                            <?php 
                            $selectedBagWeights = [];
                            if ($edit_product && $edit_product['attributes']) {
                                $attrs = json_decode($edit_product['attributes'], true);
                                if (isset($attrs['bag_weights']) && is_array($attrs['bag_weights'])) {
                                    $selectedBagWeights = $attrs['bag_weights'];
                                }
                            }
                            if (empty($selectedBagWeights)) $selectedBagWeights = [''];
                            foreach ($selectedBagWeights as $idx => $bw): ?>
                                <div class="dynamic-input-group">
                                    <input type="text" name="bag_weights[]" class="form-control" value="<?= htmlspecialchars($bw) ?>" placeholder="e.g., 20kg, 40lbs">
                                    <button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1 addBagWeightBtn"><i class="fas fa-plus"></i> Add Weight</button>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Bag Types</label>
                        <div id="bagTypesContainer">
                            <?php 
                            $bagTypes = ['Heavy Bag', 'Speed Bag', 'Double-end Bag', 'Aqua Bag', 'Wall Bag'];
                            $selectedBagTypes = [];
                            if ($edit_product && $edit_product['attributes']) {
                                $attrs = json_decode($edit_product['attributes'], true);
                                if (isset($attrs['bag_types']) && is_array($attrs['bag_types'])) {
                                    $selectedBagTypes = $attrs['bag_types'];
                                }
                            }
                            if (empty($selectedBagTypes)) $selectedBagTypes = [''];
                            foreach ($selectedBagTypes as $idx => $bt): ?>
                                <div class="dynamic-input-group">
                                    <select name="bag_types[]" class="form-select">
                                        <option value="">Select Type</option>
                                        <?php foreach ($bagTypes as $type): ?>
                                            <option value="<?= $type ?>" <?= ($bt == $type) ? 'selected' : '' ?>><?= $type ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1 addBagTypeBtn"><i class="fas fa-plus"></i> Add Type</button>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Materials</label>
                        <div id="bagMaterialsContainer">
                            <?php 
                            $selectedMaterials = [];
                            if ($edit_product && $edit_product['attributes']) {
                                $attrs = json_decode($edit_product['attributes'], true);
                                if (isset($attrs['materials']) && is_array($attrs['materials'])) {
                                    $selectedMaterials = $attrs['materials'];
                                }
                            }
                            if (empty($selectedMaterials)) $selectedMaterials = [''];
                            foreach ($selectedMaterials as $idx => $mat): ?>
                                <div class="dynamic-input-group">
                                    <input type="text" name="materials[]" class="form-control" value="<?= htmlspecialchars($mat) ?>" placeholder="e.g., Leather, Synthetic">
                                    <button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1 addBagMaterialBtn"><i class="fas fa-plus"></i> Add Material</button>
                    </div>
                </div>
            </div>

            <!-- ==================== HAND WRAPS ==================== -->
            <div id="wrapFields" class="col-12" style="display: none;">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Lengths (inches)</label>
                        <div id="wrapLengthsContainer">
                            <?php 
                            $lengths = ['108"', '120"', '180"', '210"'];
                            $selectedLengths = [];
                            if ($edit_product && $edit_product['attributes']) {
                                $attrs = json_decode($edit_product['attributes'], true);
                                if (isset($attrs['lengths']) && is_array($attrs['lengths'])) {
                                    $selectedLengths = $attrs['lengths'];
                                }
                            }
                            if (empty($selectedLengths)) $selectedLengths = [''];
                            foreach ($selectedLengths as $idx => $len): ?>
                                <div class="dynamic-input-group">
                                    <select name="lengths[]" class="form-select">
                                        <option value="">Select Length</option>
                                        <?php foreach ($lengths as $l): ?>
                                            <option value="<?= $l ?>" <?= ($len == $l) ? 'selected' : '' ?>><?= $l ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1 addWrapLengthBtn"><i class="fas fa-plus"></i> Add Length</button>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Colors</label>
                        <div id="wrapColorsContainer">
                            <?php 
                            $selectedWrapColors = [];
                            if ($edit_product && $edit_product['attributes']) {
                                $attrs = json_decode($edit_product['attributes'], true);
                                if (isset($attrs['colors']) && is_array($attrs['colors'])) {
                                    $selectedWrapColors = $attrs['colors'];
                                }
                            }
                            if (empty($selectedWrapColors)) $selectedWrapColors = [''];
                            foreach ($selectedWrapColors as $idx => $col): ?>
                                <div class="dynamic-input-group">
                                    <input type="text" name="wrap_colors[]" class="form-control" value="<?= htmlspecialchars($col) ?>" placeholder="e.g., Black, White, Red">
                                    <button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1 addWrapColorBtn"><i class="fas fa-plus"></i> Add Color</button>
                    </div>
                </div>
            </div>

            <!-- ==================== BOXING SHOES ==================== -->
            <div id="shoeFields" class="col-12" style="display: none;">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Shoe Sizes (EU)</label>
                        <div id="shoeSizesContainer">
                            <?php 
                            $shoeSizes = [36,37,38,39,40,41,42,43,44,45,46,47];
                            $selectedShoeSizes = [];
                            if ($edit_product && $edit_product['attributes']) {
                                $attrs = json_decode($edit_product['attributes'], true);
                                if (isset($attrs['sizes']) && is_array($attrs['sizes'])) {
                                    $selectedShoeSizes = $attrs['sizes'];
                                }
                            }
                            if (empty($selectedShoeSizes)) $selectedShoeSizes = [''];
                            foreach ($selectedShoeSizes as $idx => $sz): ?>
                                <div class="dynamic-input-group">
                                    <select name="shoe_sizes[]" class="form-select">
                                        <option value="">Select Size</option>
                                        <?php foreach ($shoeSizes as $s): ?>
                                            <option value="<?= $s ?>" <?= ($sz == $s) ? 'selected' : '' ?>><?= $s ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1 addShoeSizeBtn"><i class="fas fa-plus"></i> Add Size</button>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Colors</label>
                        <div id="shoeColorsContainer">
                            <?php 
                            $selectedShoeColors = [];
                            if ($edit_product && $edit_product['attributes']) {
                                $attrs = json_decode($edit_product['attributes'], true);
                                if (isset($attrs['colors']) && is_array($attrs['colors'])) {
                                    $selectedShoeColors = $attrs['colors'];
                                }
                            }
                            if (empty($selectedShoeColors)) $selectedShoeColors = [''];
                            foreach ($selectedShoeColors as $idx => $col): ?>
                                <div class="dynamic-input-group">
                                    <input type="text" name="shoe_colors[]" class="form-control" value="<?= htmlspecialchars($col) ?>" placeholder="e.g., Black, Red, White">
                                    <button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1 addShoeColorBtn"><i class="fas fa-plus"></i> Add Color</button>
                    </div>
                </div>
            </div>

            <!-- ==================== GUARD / PADS / SHORTS ==================== -->
            <div id="guardFields" class="col-12" style="display: none;">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Sizes</label>
                        <div id="guardSizesContainer">
                            <?php 
                            $guardSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
                            $selectedGuardSizes = [];
                            if ($edit_product && $edit_product['attributes']) {
                                $attrs = json_decode($edit_product['attributes'], true);
                                if (isset($attrs['sizes']) && is_array($attrs['sizes'])) {
                                    $selectedGuardSizes = $attrs['sizes'];
                                }
                            }
                            if (empty($selectedGuardSizes)) $selectedGuardSizes = [''];
                            foreach ($selectedGuardSizes as $idx => $sz): ?>
                                <div class="dynamic-input-group">
                                    <select name="guard_sizes[]" class="form-select">
                                        <option value="">Select Size</option>
                                        <?php foreach ($guardSizes as $gs): ?>
                                            <option value="<?= $gs ?>" <?= ($sz == $gs) ? 'selected' : '' ?>><?= $gs ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1 addGuardSizeBtn"><i class="fas fa-plus"></i> Add Size</button>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Colors</label>
                        <div id="guardColorsContainer">
                            <?php 
                            $selectedGuardColors = [];
                            if ($edit_product && $edit_product['attributes']) {
                                $attrs = json_decode($edit_product['attributes'], true);
                                if (isset($attrs['colors']) && is_array($attrs['colors'])) {
                                    $selectedGuardColors = $attrs['colors'];
                                }
                            }
                            if (empty($selectedGuardColors)) $selectedGuardColors = [''];
                            foreach ($selectedGuardColors as $idx => $col): ?>
                                <div class="dynamic-input-group">
                                    <input type="text" name="guard_colors[]" class="form-control" value="<?= htmlspecialchars($col) ?>" placeholder="e.g., Black, Red, Blue">
                                    <button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1 addGuardColorBtn"><i class="fas fa-plus"></i> Add Color</button>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Materials</label>
                        <div id="guardMaterialsContainer">
                            <?php 
                            $selectedGuardMaterials = [];
                            if ($edit_product && $edit_product['attributes']) {
                                $attrs = json_decode($edit_product['attributes'], true);
                                if (isset($attrs['materials']) && is_array($attrs['materials'])) {
                                    $selectedGuardMaterials = $attrs['materials'];
                                }
                            }
                            if (empty($selectedGuardMaterials)) $selectedGuardMaterials = [''];
                            foreach ($selectedGuardMaterials as $idx => $mat): ?>
                                <div class="dynamic-input-group">
                                    <input type="text" name="guard_materials[]" class="form-control" value="<?= htmlspecialchars($mat) ?>" placeholder="e.g., PU Leather, EVA Foam">
                                    <button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1 addGuardMaterialBtn"><i class="fas fa-plus"></i> Add Material</button>
                    </div>
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
                                    $displayImg = getImageUrl($img);
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
                                    <label class="form-check-label" for="newMainRadio">Set the <strong>first newly uploaded image</strong> as main</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="main_image" value="none" id="noneMainRadio">
                                    <label class="form-check-label" for="noneMainRadio">No main image (auto use first)</label>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mt-2 text-muted">No existing images. Upload new ones – the first will be auto‑set as main.</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="mt-2 small text-muted">First uploaded image will be main image automatically.</div>
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
        <h3><i class="fas fa-list"></i> All Boxing Items</h3>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr><th>ID</th><th>Main Image</th><th>Title</th><th>Brand</th><th>Category</th><th>Store</th><th>Price</th><th>Discounted</th><th>Stock</th><th>Rating</th><th>Top</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): 
                        $discounted = getDiscountedPrice($p['regular_price'], $p['discount_percent']);
                        $stockClass = $p['stock'] <= 3 ? 'stock-low' : 'stock-good';
                        $images = json_decode($p['images'], true);
                        $mainImg = $p['main_image'];
                        $displayMainImg = getImageUrl($mainImg);
                        if (empty($displayMainImg) && is_array($images) && count($images) > 0) {
                            $displayMainImg = getImageUrl($images[0]);
                        }
                        $rating = floatval($p['rating']);
                        $fullStars = floor($rating);
                        $halfStar = ($rating - $fullStars) >= 0.5;
                    ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><?php if ($displayMainImg): ?><img src="<?= htmlspecialchars($displayMainImg) ?>" class="img-preview" style="width:60px; height:60px; object-fit:cover;" onerror="this.src='https://placehold.co/400x300?text=No+Image'"><?php else: ?><i class="fas fa-image fa-lg text-secondary"></i><?php endif; ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($p['title']) ?></td>
                        <td><?= htmlspecialchars($p['brand'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($p['category']) ?></td>
                        <td><?= htmlspecialchars($p['store_name'] ?: '—') ?></td>
                        <td>$<?= number_format($p['regular_price'], 2) ?></td>
                        <td><?php if($p['discount_percent'] > 0): ?><span class="badge bg-danger">-<?= $p['discount_percent'] ?>%</span><br><strong>$<?= number_format($discounted, 2) ?></strong><del class="small">$<?= number_format($p['regular_price'], 2) ?></del><?php else: ?>$<?= number_format($p['regular_price'], 2) ?><?php endif; ?></td>
                        <td><span class="stock-badge <?= $stockClass ?>"><?= $p['stock'] ?></span></td>
                        <td><?php if($rating > 0): ?><span class="rating-stars"><?php for($i=1; $i<=5; $i++): ?><?php if($i <= $fullStars): ?><i class="fas fa-star"></i><?php elseif($halfStar && $i == $fullStars+1): ?><i class="fas fa-star-half-alt"></i><?php else: ?><i class="far fa-star"></i><?php endif; ?><?php endfor; ?></span><span class="small">(<?= number_format($rating,1) ?>)</span><?php else: ?>—<?php endif; ?></td>
                        <td><?= $p['is_top'] ? '<span class="badge bg-warning">⭐</span>' : '—' ?></td>
                        <td><a href="?edit_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i> Edit</a> <a href="?delete_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete permanently?')"><i class="fas fa-trash"></i> Del</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(count($products) == 0): ?><tr><td colspan="12" class="text-center text-muted">No items yet. Add your first boxing product above.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Category toggle
    const catSelect = document.getElementById('categorySelect');
    const gloveFields = document.getElementById('gloveFields');
    const bagFields = document.getElementById('bagFields');
    const wrapFields = document.getElementById('wrapFields');
    const shoeFields = document.getElementById('shoeFields');
    const guardFields = document.getElementById('guardFields');

    function toggleAttributeFields() {
        gloveFields.style.display = 'none';
        bagFields.style.display = 'none';
        wrapFields.style.display = 'none';
        shoeFields.style.display = 'none';
        guardFields.style.display = 'none';
        const cat = catSelect.value;
        if (cat === 'Boxing Gloves') gloveFields.style.display = 'block';
        else if (cat === 'Punching Bag') bagFields.style.display = 'block';
        else if (cat === 'Hand Wraps') wrapFields.style.display = 'block';
        else if (cat === 'Boxing Shoes') shoeFields.style.display = 'block';
        else if (['Head Guard', 'Focus Pads', 'Boxing Shorts', 'Mouth Guard'].includes(cat)) guardFields.style.display = 'block';
    }
    catSelect.addEventListener('change', toggleAttributeFields);
    toggleAttributeFields();

    // Helper: Add remove button listener
    function attachRemoveListener(btn) {
        btn.addEventListener('click', function() { this.closest('.dynamic-input-group').remove(); });
    }
    document.querySelectorAll('.removeRowBtn').forEach(attachRemoveListener);

    // Boxing Gloves Add Functions
    document.querySelector('.addGloveSizeBtn')?.addEventListener('click', () => {
        const container = document.getElementById('gloveSizesContainer');
        const div = document.createElement('div'); div.className = 'dynamic-input-group';
        div.innerHTML = `<select name="sizes[]" class="form-select"><option value="">Select Size</option><?php foreach(['8oz','10oz','12oz','14oz','16oz','18oz'] as $sz): ?><option value="<?= $sz ?>"><?= $sz ?></option><?php endforeach; ?></select><button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>`;
        container.appendChild(div);
        div.querySelector('.removeRowBtn').addEventListener('click', () => div.remove());
    });
    document.querySelector('.addGloveColorBtn')?.addEventListener('click', () => {
        const container = document.getElementById('gloveColorsContainer');
        const div = document.createElement('div'); div.className = 'dynamic-input-group';
        div.innerHTML = `<input type="text" name="colors[]" class="form-control" placeholder="e.g., Red, Black"><button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>`;
        container.appendChild(div);
        div.querySelector('.removeRowBtn').addEventListener('click', () => div.remove());
    });
    document.querySelector('.addGloveWeightBtn')?.addEventListener('click', () => {
        const container = document.getElementById('gloveWeightsContainer');
        const div = document.createElement('div'); div.className = 'dynamic-input-group';
        div.innerHTML = `<select name="weights[]" class="form-select"><option value="">Select Weight</option><?php foreach(['Light (8-10oz)','Medium (12-14oz)','Heavy (16oz+)'] as $w): ?><option value="<?= $w ?>"><?= $w ?></option><?php endforeach; ?></select><button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>`;
        container.appendChild(div);
        div.querySelector('.removeRowBtn').addEventListener('click', () => div.remove());
    });

    // Punching Bag Add Functions
    document.querySelector('.addBagWeightBtn')?.addEventListener('click', () => {
        const container = document.getElementById('bagWeightsContainer');
        const div = document.createElement('div'); div.className = 'dynamic-input-group';
        div.innerHTML = `<input type="text" name="bag_weights[]" class="form-control" placeholder="e.g., 20kg"><button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>`;
        container.appendChild(div);
        div.querySelector('.removeRowBtn').addEventListener('click', () => div.remove());
    });
    document.querySelector('.addBagTypeBtn')?.addEventListener('click', () => {
        const container = document.getElementById('bagTypesContainer');
        const div = document.createElement('div'); div.className = 'dynamic-input-group';
        div.innerHTML = `<select name="bag_types[]" class="form-select"><option value="">Select Type</option><?php foreach(['Heavy Bag','Speed Bag','Double-end Bag','Aqua Bag','Wall Bag'] as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?></select><button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>`;
        container.appendChild(div);
        div.querySelector('.removeRowBtn').addEventListener('click', () => div.remove());
    });
    document.querySelector('.addBagMaterialBtn')?.addEventListener('click', () => {
        const container = document.getElementById('bagMaterialsContainer');
        const div = document.createElement('div'); div.className = 'dynamic-input-group';
        div.innerHTML = `<input type="text" name="materials[]" class="form-control" placeholder="e.g., Leather"><button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>`;
        container.appendChild(div);
        div.querySelector('.removeRowBtn').addEventListener('click', () => div.remove());
    });

    // Hand Wraps Add Functions
    document.querySelector('.addWrapLengthBtn')?.addEventListener('click', () => {
        const container = document.getElementById('wrapLengthsContainer');
        const div = document.createElement('div'); div.className = 'dynamic-input-group';
        div.innerHTML = `<select name="lengths[]" class="form-select"><option value="">Select Length</option><?php foreach(['108"','120"','180"','210"'] as $l): ?><option value="<?= $l ?>"><?= $l ?></option><?php endforeach; ?></select><button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>`;
        container.appendChild(div);
        div.querySelector('.removeRowBtn').addEventListener('click', () => div.remove());
    });
    document.querySelector('.addWrapColorBtn')?.addEventListener('click', () => {
        const container = document.getElementById('wrapColorsContainer');
        const div = document.createElement('div'); div.className = 'dynamic-input-group';
        div.innerHTML = `<input type="text" name="wrap_colors[]" class="form-control" placeholder="e.g., Black"><button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>`;
        container.appendChild(div);
        div.querySelector('.removeRowBtn').addEventListener('click', () => div.remove());
    });

    // Boxing Shoes Add Functions
    document.querySelector('.addShoeSizeBtn')?.addEventListener('click', () => {
        const container = document.getElementById('shoeSizesContainer');
        const div = document.createElement('div'); div.className = 'dynamic-input-group';
        div.innerHTML = `<select name="shoe_sizes[]" class="form-select"><option value="">Select Size</option><?php for($i=36;$i<=47;$i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select><button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>`;
        container.appendChild(div);
        div.querySelector('.removeRowBtn').addEventListener('click', () => div.remove());
    });
    document.querySelector('.addShoeColorBtn')?.addEventListener('click', () => {
        const container = document.getElementById('shoeColorsContainer');
        const div = document.createElement('div'); div.className = 'dynamic-input-group';
        div.innerHTML = `<input type="text" name="shoe_colors[]" class="form-control" placeholder="e.g., Black"><button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>`;
        container.appendChild(div);
        div.querySelector('.removeRowBtn').addEventListener('click', () => div.remove());
    });

    // Guard Add Functions
    document.querySelector('.addGuardSizeBtn')?.addEventListener('click', () => {
        const container = document.getElementById('guardSizesContainer');
        const div = document.createElement('div'); div.className = 'dynamic-input-group';
        div.innerHTML = `<select name="guard_sizes[]" class="form-select"><option value="">Select Size</option><?php foreach(['XS','S','M','L','XL','XXL'] as $sz): ?><option value="<?= $sz ?>"><?= $sz ?></option><?php endforeach; ?></select><button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>`;
        container.appendChild(div);
        div.querySelector('.removeRowBtn').addEventListener('click', () => div.remove());
    });
    document.querySelector('.addGuardColorBtn')?.addEventListener('click', () => {
        const container = document.getElementById('guardColorsContainer');
        const div = document.createElement('div'); div.className = 'dynamic-input-group';
        div.innerHTML = `<input type="text" name="guard_colors[]" class="form-control" placeholder="e.g., Black"><button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>`;
        container.appendChild(div);
        div.querySelector('.removeRowBtn').addEventListener('click', () => div.remove());
    });
    document.querySelector('.addGuardMaterialBtn')?.addEventListener('click', () => {
        const container = document.getElementById('guardMaterialsContainer');
        const div = document.createElement('div'); div.className = 'dynamic-input-group';
        div.innerHTML = `<input type="text" name="guard_materials[]" class="form-control" placeholder="e.g., PU Leather"><button type="button" class="btn btn-sm btn-danger removeRowBtn"><i class="fas fa-minus"></i></button>`;
        container.appendChild(div);
        div.querySelector('.removeRowBtn').addEventListener('click', () => div.remove());
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>