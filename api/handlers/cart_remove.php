<?php
require_once '../../config/db.php';
header('Content-Type: application/json');

$cookie_name = "cart";
$cookie_expire = time() + (7 * 24 * 60 * 60); // 7 days

if (!isset($product_id) || $product_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

if (isset($_COOKIE[$cookie_name])) {
    $cart = json_decode($_COOKIE[$cookie_name], true);
    if (!is_array($cart)) {
        $cart = [];
    }
} else {
    $cart = [];
}

$updatedCart = array_filter($cart, function ($item) use ($product_id) {
    return $item['product_id'] != $product_id;
});

$updatedCart = array_values($updatedCart);

setcookie($cookie_name, json_encode($updatedCart), $cookie_expire, "/");

echo json_encode($updatedCart);