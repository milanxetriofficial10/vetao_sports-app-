<?php
include "../databases/db.php";

$id = (int)$_GET['id'];
$rate = (int)$_GET['rate'];

$res = $conn->query("SELECT rating, total_ratings FROM jerseys WHERE id=$id");
$data = $res->fetch_assoc();

$total = $data['total_ratings'] + 1;
$newRating = (($data['rating'] * $data['total_ratings']) + $rate) / $total;

$conn->query("UPDATE jerseys SET rating=$newRating, total_ratings=$total WHERE id=$id");

header("Location: index.php");
?>