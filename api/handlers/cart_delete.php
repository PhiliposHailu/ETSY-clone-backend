<?php
require_once '../config/db.php';
require_once 'auth.php';
header('Content-Type: application/json');

$cookie_name = "cart";

$value = $product_id ?? null;
$product_id = filter_var($value, FILTER_VALIDATE_INT);

if (!$product_id || $product_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing product ID.']);
    exit;
}

if (!isset($user_id)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);

    $db_deleted = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    error_log("DB Error deleting cart item: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete item from database cart.']);
    exit;
}

if (isset($_COOKIE[$cookie_name])) {
    $cart = json_decode($_COOKIE[$cookie_name], true);
    if (is_array($cart)) {
        $updated_cart = array_filter($cart, function ($item) use ($product_id) {
            return !isset($item['product_id']) || (int) $item['product_id'] !== $product_id;
        });

        $updated_cart = array_values($updated_cart);

        setcookie($cookie_name, json_encode($updated_cart), time() + (7 * 24 * 60 * 60), "/");
        $cookie_updated = true;
    } else {
        setcookie($cookie_name, "", time() - 3600, "/");
        $cookie_updated = false;
    }
} else {
    $cookie_updated = true;
}

if ($db_deleted || $cookie_updated) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Item removed from cart.']);
} else {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Item is not in the cart or was already removed.']);
}
