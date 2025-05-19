<?php
require_once '../config/db.php';

if (!$product_id) {
    http_response_code(400);
    echo json_encode(["error" => "Missing product_id."]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT r.product_id, r.rating, r.comment, r.created_at, u.username
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.product_id = ?
        ORDER BY r.created_at DESC"
    );
    $stmt->execute([$product_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "Procuct ID: " => $product_id,
        "message" => "User data fetched successfully.",
        "data" => $reviews
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
