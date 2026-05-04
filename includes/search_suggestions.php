<?php
header('Content-Type: application/json');

include "../databases/db.php";

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT id, name FROM products WHERE name LIKE ? LIMIT 10");
$like = "%" . $q . "%";
$stmt->bind_param("s", $like);
$stmt->execute();
$result = $stmt->get_result();

$suggestions = [];
while ($row = $result->fetch_assoc()) {
    $suggestions[] = [
        'id'   => $row['id'],
        'name' => htmlspecialchars($row['name'])
    ];
}

echo json_encode($suggestions);
$stmt->close();
?>