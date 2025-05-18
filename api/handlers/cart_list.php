<?php
require_once '../config/db.php';
header('Content-Type: application/json');

$cookie_name = "cart";
$cartItems = [];

// ------------------- COOKIE CART -------------------

if (isset($_COOKIE[$cookie_name])) {
    $cookie_cart = json_decode($_COOKIE[$cookie_name], true);
    if (is_array($cookie_cart)) {
        $cartItems = $cookie_cart;
    }
}

// ------------------- DATABASE CART -------------------

try {
    $stmt = $pdo->prepare("
        SELECT 
            ci.product_id,
            ci.quantity,
            p.title AS product_name,
            p.price,
            c.name AS category_name,
            (
                SELECT image_url FROM product_images 
                WHERE product_id = p.id 
                ORDER BY id ASC LIMIT 1
            ) AS product_image
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        JOIN categories c ON p.category_id = c.id
        WHERE ci.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $dbCart = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'source' => 'database',
        'cart' => $dbCart
    ]);
} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch cart items from database'
    ]);
}
