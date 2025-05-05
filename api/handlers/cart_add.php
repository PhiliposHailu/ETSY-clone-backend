<?php
require_once '../../config/db.php';
header('Content-Type: application/json');

$cookie_name = "cart";
$cookie_expire = time() + (7 * 24 * 60 * 60); 

if (!isset($product_id) || $product_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['quantity']) || (int)$input['quantity'] <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Quantity must be a positive integer']);
    exit;
}

$quantity = (int)$input['quantity'];

if (isset($_COOKIE[$cookie_name])) {
    $cart = json_decode($_COOKIE[$cookie_name], true);
    if (!is_array($cart)) {
        $cart = [];
    }
} else {
    $cart = [];
}

$found = false;
foreach ($cart as $item) {
    if ($item['product_id'] == $product_id) {
        $item['quantity'] += $quantity;
        $found = true;
        break;
    }
}
unset($item); 

if (!$found) {
    $cart[] = [
        'product_id' => $product_id,
        'quantity' => $quantity
    ];
}

setcookie($cookie_name, json_encode($cart), $cookie_expire, "/");

echo json_encode($cart);
