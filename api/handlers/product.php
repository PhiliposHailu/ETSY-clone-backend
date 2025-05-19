<?php
require_once '../config/db.php';
header("Content-Type: application/json");

$product_id = filter_var($product_id, FILTER_VALIDATE_INT);

if ($product_id == null || $product_id == false) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid or missing product ID."]);
    exit();
}

try {
    // Fetch product details
    $stmt = $pdo->prepare("
        SELECT p.id, p.seller_id, p.title, p.description, p.price, p.original_price, p.stock_quantity,
               p.category_id, c.name AS category_name, p.created_at
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        // Fetch product images
        $stmt_images = $pdo->prepare("
            SELECT image_url
            FROM product_images
            WHERE product_id = ?
        ");
        $stmt_images->execute([$product_id]);
        $images = $stmt_images->fetchAll(PDO::FETCH_COLUMN); // Fetch all image_url values into an array

        // Add images array to the product data
        $product['images'] = $images;

        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Product fetched successfully.", "data" => $product]);
    } else {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Product not found."]);
    }
} catch (\PDOException $e) {
    http_response_code(500); // server side error
    // for debugging purposes
    // error_log("Database error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "A database error occurred while fetching the product." . $e->getMessage()]); // generic message
}
