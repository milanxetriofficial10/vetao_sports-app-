<?php
// search.php - Full Search Results Page
include "../databases/db.php";

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($q)) {
    header("Location: index.php");
    exit;
}

$page_title = "Search: " . htmlspecialchars($q);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> — Jersey Ghar</title>
    <?php include "../includes/header.php"; ?>   <!-- or your header path -->
    
    <style>
        .search-results {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .result-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .product-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        .product-card:hover {
            transform: translateY(-8px);
        }
        .product-card img {
            width: 100%;
            height: 220px;
            object-fit: cover;
        }
        .product-info {
            padding: 14px;
        }
        .product-info h3 {
            font-size: 16px;
            margin: 0 0 8px 0;
        }
        .product-info .price {
            color: #ff6b2b;
            font-weight: 700;
            font-size: 15px;
        }
    </style>
</head>
<body>

<div class="search-results">
    <h2>Search Results for "<?= htmlspecialchars($q) ?>"</h2>
    
    <?php
    // Safe query - only search in 'name' column (since description doesn't exist)
    $stmt = $conn->prepare("SELECT id, name, image, price FROM products 
                           WHERE name LIKE ? 
                           ORDER BY name LIMIT 50");
    
    $like = "%" . $q . "%";
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo '<div class="result-grid">';
        
        while ($product = $result->fetch_assoc()) {
            $img = !empty($product['image']) ? '../' . htmlspecialchars($product['image']) : '../uploads/placeholder.jpg';
            ?>
            <div class="product-card">
                <img src="<?= $img ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                <div class="product-info">
                    <h3><?= htmlspecialchars($product['name']) ?></h3>
                    <p class="price">Rs. <?= number_format($product['price'] ?? 0) ?></p>
                    <a href="product.php?id=<?= $product['id'] ?>" style="color:#ff6b2b; text-decoration:none;">View Details →</a>
                </div>
            </div>
            <?php
        }
        echo '</div>';
    } else {
        echo '<p style="margin-top:30px; font-size:18px;">Sorry, no jerseys found matching "<strong>' . htmlspecialchars($q) . '</strong>".</p>';
        echo '<p>Try searching with different keywords like "ronaldo", "messi", "nepal", etc.</p>';
    }
    
    $stmt->close();
    ?>
</div>

</body>
</html>