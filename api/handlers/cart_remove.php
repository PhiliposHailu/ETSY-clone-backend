<?php
require_once '../config/db.php';
require_once 'auth.php';
header('Content-Type: application/json');

$cookie_name = "cart";

// Get product_id from GET or POST
// $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if (!$product_id || $product_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product ID'. $product_id]);
    exit;
}

// ------------------- COOKIE CART -------------------

if (isset($_COOKIE[$cookie_name])) {
    $cart = json_decode($_COOKIE[$cookie_name], true);
    if (!is_array($cart)) {
        $cart = [];
    }

    // Filter out the item to remove
    $cart = array_filter($cart, function ($item) use ($product_id) {
        return $item['product_id'] != $product_id;
    });

    // Reindex array
    $cart = array_values($cart);

    // Update cookie
    setcookie($cookie_name, json_encode($cart), time() + (7 * 24 * 60 * 60), "/");
}

// ------------------- DATABASE CART -------------------

try {
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);

    echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to remove item from cart']);
}
