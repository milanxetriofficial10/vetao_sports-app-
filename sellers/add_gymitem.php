<?php
session_start();
include '../databases/db.php';

$conn = getDB();

if (!$conn) {
    die("Database connection failed.");
}

// Helper functions
function isAdmin() { return isset($_SESSION['admin_id']); }
function redirect($url) { header("Location: $url"); exit; }
function sanitize($input) { global $conn; return htmlspecialchars(strip_tags(trim($input))); }
function formatPrice($price) { return '₹' . number_format($price, 2); }
function discountedPrice($price, $discount) { return $price - ($price * $discount / 100); }

$upload_dir = __DIR__ . '/uploads/product_images/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

// ---------- LOGIN CHECK ----------
if (!isAdmin() && (!isset($_POST['login_action']) || !isset($_POST['username']))) {
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Admin Login</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:linear-gradient(135deg,#667eea,#764ba2);min-height:100vh;display:flex;align-items:center}</style></head>
    <body><div class="container"><div class="row justify-content-center"><div class="col-md-4"><div class="card shadow"><div class="card-body"><h3 class="text-center">🔐 Admin Login</h3><?php if(isset($_GET['error'])) echo '<div class="alert alert-danger">Invalid credentials</div>'; ?><form method="POST"><input type="hidden" name="login_action" value="1"><input type="text" name="username" class="form-control mb-2" placeholder="Username" required><input type="password" name="password" class="form-control mb-2" placeholder="Password" required><button class="btn btn-primary w-100">Login</button></form><div class="text-center mt-3 small">Default: admin / password</div></div></div></div></div></div></body></html>
    <?php
    exit;
}
if (isset($_POST['login_action'])) {
    $user = $conn->real_escape_string($_POST['username']);
    $pass = $_POST['password'];
    $res = $conn->query("SELECT * FROM admin_users WHERE username='$user'");
    if ($res->num_rows && password_verify($pass, $res->fetch_assoc()['password_hash'])) {
        $_SESSION['admin_id'] = $user;
        redirect('admin_all.php');
    } else {
        redirect('admin_all.php?error=1');
    }
}
if (isset($_GET['logout'])) { session_destroy(); redirect('admin_all.php'); }

// ---------- HANDLE PRODUCT ADD / EDIT / DELETE ----------
$msg = '';
// Delete product
if (isset($_GET['delete_product'])) {
    $id = intval($_GET['delete_product']);
    // delete images
    $img_res = $conn->query("SELECT main_image, gallery_images FROM gym_item_products WHERE id=$id");
    if ($img = $img_res->fetch_assoc()) {
        if ($img['main_image'] && file_exists($upload_dir . $img['main_image'])) unlink($upload_dir . $img['main_image']);
        if ($img['gallery_images']) {
            foreach (explode(',', $img['gallery_images']) as $g) 
                if ($g && file_exists($upload_dir . $g)) unlink($upload_dir . $g);
        }
    }
    $conn->query("DELETE FROM gym_item_products WHERE id=$id");
    $msg = '<div class="alert alert-success">Product deleted.</div>';
}

// Add / Edit product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_product'])) {
    $id = intval($_POST['product_id']);
    $name = sanitize($_POST['name']);
    $sku = sanitize($_POST['sku']);
    $brand_id = $_POST['brand_id'] ? intval($_POST['brand_id']) : NULL;
    $cat_id = intval($_POST['category_id']);
    $sub_id = $_POST['subcategory_id'] ? intval($_POST['subcategory_id']) : NULL;
    $short_desc = sanitize($_POST['short_desc']);
    $full_desc = $_POST['full_desc'];
    $price = floatval($_POST['price']);
    $discount = floatval($_POST['discount']);
    $stock_qty = intval($_POST['stock_qty']);
    $stock_status = sanitize($_POST['stock_status']);
    $min_order = intval($_POST['min_order']);
    $warehouse = sanitize($_POST['warehouse']);
    $weight = sanitize($_POST['weight']);
    $barcode = sanitize($_POST['barcode']);
    $featured = isset($_POST['featured']) ? 1 : 0;
    $trending = isset($_POST['trending']) ? 1 : 0;
    
    // Main image
    $main_img = '';
    if ($_FILES['main_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION));
        $main_img = time() . '_main.' . $ext;
        move_uploaded_file($_FILES['main_image']['tmp_name'], $upload_dir . $main_img);
        // If editing, delete old main image
        if ($id) {
            $old = $conn->query("SELECT main_image FROM gym_item_products WHERE id=$id")->fetch_assoc();
            if ($old['main_image'] && file_exists($upload_dir . $old['main_image'])) unlink($upload_dir . $old['main_image']);
        }
    }
    
    // Gallery images (new uploads)
    $gallery = [];
    if ($id) {
        $old_g = $conn->query("SELECT gallery_images FROM gym_item_products WHERE id=$id")->fetch_assoc();
        if ($old_g['gallery_images']) $gallery = explode(',', $old_g['gallery_images']);
    }
    if (isset($_FILES['gallery']['tmp_name'])) {
        foreach ($_FILES['gallery']['tmp_name'] as $k => $tmp) {
            if ($_FILES['gallery']['error'][$k] == 0) {
                $ext = strtolower(pathinfo($_FILES['gallery']['name'][$k], PATHINFO_EXTENSION));
                $gfile = time() . "_gal$k.$ext";
                move_uploaded_file($tmp, $upload_dir . $gfile);
                $gallery[] = $gfile;
            }
        }
    }
    // Remove selected gallery images
    if (isset($_POST['remove_gallery'])) {
        foreach ($_POST['remove_gallery'] as $rimg) {
            if (file_exists($upload_dir . $rimg)) unlink($upload_dir . $rimg);
            $gallery = array_diff($gallery, [$rimg]);
        }
    }
    $gallery_str = implode(',', $gallery);
    
    if ($id == 0) { // INSERT
        $stmt = $conn->prepare("INSERT INTO gym_item_products (product_name, sku, brand_id, category_id, subcategory_id, short_description, full_description, price, discount_percent, stock_quantity, stock_status, min_order_quantity, warehouse_location, product_weight, barcode, main_image, gallery_images, featured, trending) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssiisssddiissssssii', $name, $sku, $brand_id, $cat_id, $sub_id, $short_desc, $full_desc, $price, $discount, $stock_qty, $stock_status, $min_order, $warehouse, $weight, $barcode, $main_img, $gallery_str, $featured, $trending);
        if ($stmt->execute()) $msg = '<div class="alert alert-success">Product added successfully!</div>';
        else $msg = '<div class="alert alert-danger">Error: ' . $conn->error . '</div>';
    } else { // UPDATE
        if ($main_img == '') {
            // keep existing main image
            $old_img = $conn->query("SELECT main_image FROM gym_item_products WHERE id=$id")->fetch_assoc();
            $main_img = $old_img['main_image'];
        }
        $stmt = $conn->prepare("UPDATE gym_item_products SET product_name=?, sku=?, brand_id=?, category_id=?, subcategory_id=?, short_description=?, full_description=?, price=?, discount_percent=?, stock_quantity=?, stock_status=?, min_order_quantity=?, warehouse_location=?, product_weight=?, barcode=?, main_image=?, gallery_images=?, featured=?, trending=? WHERE id=?");
        $stmt->bind_param('ssiisssddiissssssiii', $name, $sku, $brand_id, $cat_id, $sub_id, $short_desc, $full_desc, $price, $discount, $stock_qty, $stock_status, $min_order, $warehouse, $weight, $barcode, $main_img, $gallery_str, $featured, $trending, $id);
        if ($stmt->execute()) $msg = '<div class="alert alert-success">Product updated!</div>';
        else $msg = '<div class="alert alert-danger">Error: ' . $conn->error . '</div>';
    }
}

// ---------- CATEGORY / BRAND MANAGEMENT ----------
if (isset($_POST['add_category'])) {
    $name = sanitize($_POST['cat_name']);
    $slug = strtolower(preg_replace('/[^a-z0-9-]+/', '-', $name));
    $conn->query("INSERT INTO gym_categories (name, slug) VALUES ('$name','$slug')");
    $msg = '<div class="alert alert-success">Category added.</div>';
}
if (isset($_GET['del_cat'])) {
    $id = intval($_GET['del_cat']);
    $conn->query("DELETE FROM gym_categories WHERE id=$id");
    $msg = '<div class="alert alert-success">Category deleted.</div>';
}
if (isset($_POST['add_sub'])) {
    $cat = intval($_POST['cat_id']);
    $name = sanitize($_POST['sub_name']);
    $slug = strtolower(preg_replace('/[^a-z0-9-]+/', '-', $name));
    $conn->query("INSERT INTO subcategories (category_id, name, slug) VALUES ($cat, '$name','$slug')");
    $msg = '<div class="alert alert-success">Subcategory added.</div>';
}
if (isset($_GET['del_sub'])) {
    $id = intval($_GET['del_sub']);
    $conn->query("DELETE FROM subcategories WHERE id=$id");
    $msg = '<div class="alert alert-success">Subcategory deleted.</div>';
}
if (isset($_POST['add_brand'])) {
    $name = sanitize($_POST['brand_name']);
    $conn->query("INSERT INTO brands (name) VALUES ('$name')");
    $msg = '<div class="alert alert-success">Brand added.</div>';
}
if (isset($_GET['del_brand'])) {
    $id = intval($_GET['del_brand']);
    $conn->query("DELETE FROM brands WHERE id=$id");
    $msg = '<div class="alert alert-success">Brand deleted.</div>';
}

// ---------- FETCH DATA FOR UI ----------
$categories = $conn->query("SELECT * FROM gym_categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$brands = $conn->query("SELECT * FROM brands ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$subcategories = $conn->query("SELECT s.*, c.name as cat_name FROM subcategories s JOIN categories c ON s.category_id = c.id ORDER BY c.name, s.name")->fetch_all(MYSQLI_ASSOC);

// Pagination for products
$page = isset($_GET['p']) ? intval($_GET['p']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$total_res = $conn->query("SELECT COUNT(*) as c FROM gym_item_products");
$total = $total_res->fetch_assoc()['c'];
$total_pages = ceil($total / $limit);
$products = $conn->query("SELECT p.*, c.name as cat_name, b.name as brand_name FROM gym_item_products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN brands b ON p.brand_id = b.id ORDER BY p.id DESC LIMIT $limit OFFSET $offset")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Gym Products</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .product-img-thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
        .nav-tabs .nav-link { font-weight: bold; }
        .modal-lg { max-width: 900px; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <span class="navbar-brand"><i class="fas fa-dumbbell"></i> Gym Admin - One Page Panel</span>
        <div class="d-flex">
            <span class="text-white me-3">Welcome, <?= $_SESSION['admin_id'] ?></span>
            <a href="?logout=1" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>
<div class="container mt-3">
    <?= $msg ?>
    <!-- Tabs: Products | Categories | Brands -->
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab">🏋️ Products</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button" role="tab">📁 Categories & Subcategories</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="brands-tab" data-bs-toggle="tab" data-bs-target="#brands" type="button" role="tab">🏷️ Brands</button></li>
    </ul>
    <div class="tab-content mt-3">
        <!-- ========================= PRODUCTS TAB ========================= -->
        <div class="tab-pane fade show active" id="products" role="tabpanel">
            <div class="d-flex justify-content-between mb-3">
                <h3>Product List</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetProductForm()"><i class="fas fa-plus"></i> Add New Product</button>
            </div>
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr><th>ID</th><th>Image</th><th>Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Discount</th><th>Stock</th><th>Featured</th><th>Trending</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach($products as $p): $final = discountedPrice($p['price'], $p['discount_percent']); ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><img src="uploads/product_images/<?= $p['main_image'] ?: 'no-image.jpg' ?>" class="product-img-thumb" onerror="this.src='https://placehold.co/60x60'"></td>
                        <td><?= $p['product_name'] ?></td>
                        <td><?= $p['sku'] ?></td>
                        <td><?= $p['cat_name'] ?></td>
                        <td><?= formatPrice($p['price']) ?></td>
                        <td><?= $p['discount_percent'] ?>%</td>
                        <td><?= $p['stock_status'] == 'in_stock' ? '<span class="badge bg-success">In Stock ('.$p['stock_quantity'].')</span>' : ($p['stock_status']=='limited' ? '<span class="badge bg-warning">Limited</span>' : '<span class="badge bg-danger">Out</span>') ?></td>
                        <td><?= $p['featured'] ? '⭐' : '—' ?></td>
                        <td><?= $p['trending'] ? '🔥' : '—' ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?delete_product=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this product?')"><i class="fas fa-trash"></i> Del</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <!-- Pagination -->
            <nav><ul class="pagination"><?php for($i=1;$i<=$total_pages;$i++): ?><li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?p=<?= $i ?>"><?= $i ?></a></li><?php endfor; ?></ul></nav>
        </div>

        <!-- ========================= CATEGORIES & SUBCATEGORIES TAB ========================= -->
        <div class="tab-pane fade" id="categories" role="tabpanel">
            <div class="row">
                <div class="col-md-5">
                    <h4>Add Category</h4>
                    <form method="POST"><input type="text" name="cat_name" class="form-control" placeholder="Category Name" required><button type="submit" name="add_category" class="btn btn-success mt-2">Add Category</button></form>
                    <h4 class="mt-4">Existing Categories</h4>
                    <ul class="list-group"><?php foreach($categories as $c): ?><li class="list-group-item d-flex justify-content-between"><?= $c['name'] ?> <a href="?del_cat=<?= $c['id'] ?>" onclick="return confirm('Delete category? It will also delete its subcategories.')" class="btn btn-sm btn-danger">Del</a></li><?php endforeach; ?></ul>
                </div>
                <div class="col-md-7">
                    <h4>Add Subcategory</h4>
                    <form method="POST"><select name="cat_id" class="form-select"><?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= $c['name'] ?></option><?php endforeach; ?></select><input type="text" name="sub_name" class="form-control mt-2" placeholder="Subcategory Name" required><button type="submit" name="add_sub" class="btn btn-primary mt-2">Add Subcategory</button></form>
                    <h4 class="mt-4">Subcategories List</h4>
                    <table class="table table-sm"><?php foreach($subcategories as $s): ?><tr><td><?= $s['name'] ?></td><td><?= $s['cat_name'] ?></td><td><a href="?del_sub=<?= $s['id'] ?>" onclick="return confirm('Delete subcategory?')" class="btn btn-sm btn-danger">Del</a></td></tr><?php endforeach; ?></table>
                </div>
            </div>
        </div>

        <!-- ========================= BRANDS TAB ========================= -->
        <div class="tab-pane fade" id="brands" role="tabpanel">
            <div class="row"><div class="col-md-6"><h4>Add Brand</h4><form method="POST"><input type="text" name="brand_name" class="form-control" placeholder="Brand Name" required><button type="submit" name="add_brand" class="btn btn-success mt-2">Add Brand</button></form></div>
            <div class="col-md-6"><h4>Existing Brands</h4><ul class="list-group"><?php foreach($brands as $b): ?><li class="list-group-item d-flex justify-content-between"><?= $b['name'] ?> <a href="?del_brand=<?= $b['id'] ?>" onclick="return confirm('Delete brand?')" class="btn btn-sm btn-danger">Del</a></li><?php endforeach; ?></ul></div></div>
        </div>
    </div>
</div>

<!-- ======================== PRODUCT MODAL (Add/Edit) ======================== -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="modalTitle">Add / Edit Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" enctype="multipart/form-data" id="productForm">
                <div class="modal-body">
                    <input type="hidden" name="product_id" id="product_id" value="0">
                    <div class="row g-2">
                        <div class="col-md-6"><label>Product Name *</label><input type="text" name="name" id="p_name" class="form-control" required></div>
                        <div class="col-md-3"><label>SKU *</label><input type="text" name="sku" id="p_sku" class="form-control" required></div>
                        <div class="col-md-3"><label>Brand</label><select name="brand_id" id="p_brand" class="form-select"><option value="">Select Brand</option><?php foreach($brands as $b): ?><option value="<?= $b['id'] ?>"><?= $b['name'] ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label>Category *</label><select name="category_id" id="p_cat" class="form-select" required><?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= $c['name'] ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label>Subcategory</label><select name="subcategory_id" id="p_sub" class="form-select"><option value="">Select</option></select></div>
                        <div class="col-md-4"><label>Price (₹)</label><input type="number" step="0.01" name="price" id="p_price" class="form-control"></div>
                        <div class="col-md-4"><label>Discount %</label><input type="number" step="0.01" name="discount" id="p_discount" class="form-control" value="0"></div>
                        <div class="col-md-4"><label>Stock Qty</label><input type="number" name="stock_qty" id="p_stock_qty" class="form-control"></div>
                        <div class="col-md-4"><label>Stock Status</label><select name="stock_status" id="p_stock_status" class="form-select"><option value="in_stock">In Stock</option><option value="limited">Limited Stock</option><option value="out_of_stock">Out of Stock</option></select></div>
                        <div class="col-md-3"><label>Min Order Qty</label><input type="number" name="min_order" id="p_min_order" class="form-control" value="1"></div>
                        <div class="col-md-3"><label>Warehouse</label><input type="text" name="warehouse" id="p_warehouse" class="form-control"></div>
                        <div class="col-md-3"><label>Weight</label><input type="text" name="weight" id="p_weight" class="form-control" placeholder="e.g., 2.5kg"></div>
                        <div class="col-md-3"><label>Barcode</label><input type="text" name="barcode" id="p_barcode" class="form-control"></div>
                        <div class="col-md-12"><label>Short Description</label><textarea name="short_desc" id="p_short_desc" rows="2" class="form-control"></textarea></div>
                        <div class="col-md-12"><label>Full Description</label><textarea name="full_desc" id="p_full_desc" rows="3" class="form-control"></textarea></div>
                        <div class="col-md-6"><label>Main Image</label><input type="file" name="main_image" class="form-control" accept="image/*"><div id="current_main_img" class="mt-1"></div></div>
                        <div class="col-md-6"><label>Gallery Images (add multiple)</label><input type="file" name="gallery[]" multiple class="form-control"><div id="current_gallery" class="mt-1"></div></div>
                        <div class="col-md-2"><div class="form-check"><input type="checkbox" name="featured" id="p_featured" class="form-check-input"> <label>Featured</label></div></div>
                        <div class="col-md-2"><div class="form-check"><input type="checkbox" name="trending" id="p_trending" class="form-check-input"> <label>Trending</label></div></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" name="save_product" class="btn btn-primary">Save Product</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button></div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function resetProductForm() {
    document.getElementById('productForm').reset();
    document.getElementById('product_id').value = 0;
    document.getElementById('modalTitle').innerText = 'Add New Product';
    document.getElementById('current_main_img').innerHTML = '';
    document.getElementById('current_gallery').innerHTML = '';
    $('#p_sub').html('<option value="">Select Subcategory</option>');
}
function editProduct(p) {
    document.getElementById('product_id').value = p.id;
    document.getElementById('p_name').value = p.product_name;
    document.getElementById('p_sku').value = p.sku;
    document.getElementById('p_brand').value = p.brand_id || '';
    document.getElementById('p_cat').value = p.category_id;
    // load subcategories dynamically
    loadSubcats(p.category_id, p.subcategory_id);
    document.getElementById('p_price').value = p.price;
    document.getElementById('p_discount').value = p.discount_percent;
    document.getElementById('p_stock_qty').value = p.stock_quantity;
    document.getElementById('p_stock_status').value = p.stock_status;
    document.getElementById('p_min_order').value = p.min_order_quantity;
    document.getElementById('p_warehouse').value = p.warehouse_location || '';
    document.getElementById('p_weight').value = p.product_weight || '';
    document.getElementById('p_barcode').value = p.barcode || '';
    document.getElementById('p_short_desc').value = p.short_description;
    document.getElementById('p_full_desc').value = p.full_description;
    document.getElementById('p_featured').checked = p.featured == 1;
    document.getElementById('p_trending').checked = p.trending == 1;
    document.getElementById('modalTitle').innerText = 'Edit Product - ' + p.product_name;
    // show existing images
    let mainHtml = '';
    if (p.main_image) mainHtml = `<img src="uploads/product_images/${p.main_image}" width="80" class="img-thumbnail"><br><small>Current main image</small>`;
    document.getElementById('current_main_img').innerHTML = mainHtml;
    let galleryHtml = '';
    if (p.gallery_images && p.gallery_images != '') {
        let imgs = p.gallery_images.split(',');
        imgs.forEach(img => {
            galleryHtml += `<div class="d-inline-block m-1"><img src="uploads/product_images/${img}" width="60" class="img-thumbnail"><br><label><input type="checkbox" name="remove_gallery[]" value="${img}"> Remove</label></div>`;
        });
    }
    document.getElementById('current_gallery').innerHTML = galleryHtml;
    $('#productModal').modal('show');
}
function loadSubcats(catId, selectedId = null) {
    $.get('?ajax=1&cat='+catId, function(data) {
        $('#p_sub').html(data);
        if (selectedId) $('#p_sub').val(selectedId);
    });
}
$('#p_cat').change(function() { loadSubcats($(this).val()); });
// AJAX handler for subcategories
<?php if(isset($_GET['ajax']) && isset($_GET['cat'])): ?>
<?php
    $cat_id = intval($_GET['cat']);
    $subs = $conn->query("SELECT id, name FROM subcategories WHERE category_id=$cat_id ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    echo '<option value="">Select Subcategory</option>';
    foreach($subs as $s) echo "<option value='{$s['id']}'>{$s['name']}</option>";
    exit;
?>
<?php endif; ?>
</script>
</body>
</html>