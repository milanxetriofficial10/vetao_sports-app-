<?php
include "../databases/db.php";
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$page_title = $q ? "Search: " . htmlspecialchars($q) : "Search";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?> — Jersey Ghar</title>
  <?php include "../includes/header.php"; ?>
</head>
<body>

<div style="padding:40px 20px; max-width:1200px; margin:0 auto;">
  <h2>Search Results for "<?= htmlspecialchars($q) ?>"</h2>
  
  <?php
  if ($q) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE name LIKE ? OR description LIKE ?");
    $like = "%" . $q . "%";
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      echo '<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:20px; margin-top:30px;">';
      while ($product = $result->fetch_assoc()) {
        // Show your card here (same style as index.php)
        echo '<div class="card">';
        echo '<img src="../' . htmlspecialchars($product['image']) . '" style="width:100%; height:200px; object-fit:cover;">';
        echo '<h3>' . htmlspecialchars($product['name']) . '</h3>';
        echo '<p>Rs. ' . number_format($product['price']) . '</p>';
        echo '<a href="product.php?id=' . $product['id'] . '">View Details</a>';
        echo '</div>';
      }
      echo '</div>';
    } else {
      echo '<p>No products found matching your search.</p>';
    }
  }
  ?>
</div>

</body>
</html>