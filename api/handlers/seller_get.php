<?php
require_once '../../config/db.php';
header("Content-Type: application/json");

// Fetch seller info by ID
$stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
$stmt->execute([$seller_id]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

if ($seller) {
    echo json_encode($seller);
} else {
    http_response_code(404);
    echo json_encode(["error" => "Seller not found"]);
}