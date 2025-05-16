<?php
require_once '../config/db.php';
require_once 'auth.php';
header('Content-Type: application/json');

$cookie_name = "cart";
$cookie_expire = time() + (7 * 24 * 60 * 60); 

// Assume product_id comes from GET or POST
// $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if (!$product_id || $product_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product ID wwew']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['quantity']) || (int)$input['quantity'] <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Quantity must be a positive integer']);
    exit;
}

$quantity = (int)$input['quantity'];

// Handle cookie cart
if (isset($_COOKIE[$cookie_name])) {
    $cart = json_decode($_COOKIE[$cookie_name], true);
    if (!is_array($cart)) {
        $cart = [];
    }
} else {
    $cart = [];
}

$found = false;
foreach ($cart as &$item) {
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

// Update cookie
setcookie($cookie_name, json_encode($cart), $cookie_expire, "/");

// Add to DB
try {
    // Check if item already exists in cart
    $stmt = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update quantity
        $newQuantity = $existing['quantity'] + $quantity;
        $updateStmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
        $updateStmt->execute([$newQuantity, $existing['id']]);
    } else {
        // Insert new row
        $insertStmt = $pdo->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $insertStmt->execute([$user_id, $product_id, $quantity]);
    }

    echo json_encode(['success' => true, 'message' => 'Item added to cart']);
} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update cart in database' . $e->getMessage()]);
}
