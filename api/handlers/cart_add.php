<?php
require_once '../config/db.php';
require_once 'auth.php'; // Assuming auth.php sets $user_id
header('Content-Type: application/json');

$cookie_name = "cart";
$cookie_expire = time() + (7 * 24 * 60 * 60); // 7 days

$product_id = filter_var($product_id, FILTER_VALIDATE_INT);

if (!$product_id || $product_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing product ID.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['quantity']) || (int)$input['quantity'] <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Quantity must be a positive integer.']);
    exit;
}

$quantity = (int)$input['quantity'];

// --- Stock Check ---
try {
    $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }

    $available_stock = $product['stock_quantity'];

    // Check if the requested quantity exceeds available stock
    if ($quantity > $available_stock) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Requested quantity exceeds available stock.']);
        exit;
    }
} catch (PDOException $e) {
    error_log("DB Error fetching stock: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A database error occurred while checking stock.']);
    exit;
}

// Handle cookie cart
if (isset($_COOKIE[$cookie_name])) {
    $cart = json_decode($_COOKIE[$cookie_name], true);
    if (!is_array($cart)) {
        $cart = [];
    }
} else {
    $cart = [];
}

$found_in_cookie = false;
foreach ($cart as &$item) {
    if ($item['product_id'] == $product_id) {
        if (($item['quantity'] + $quantity) > $available_stock) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Adding this quantity would exceed available stock in your cookie cart. 
            Available stock: ' . $available_stock . "cookie: " . $item['quantity']]);
            exit;
        }
        $item['quantity'] += $quantity;
        $found_in_cookie = true;
        break;
    }
}
unset($item);

if (!$found_in_cookie) {
    $cart[] = [
        'product_id' => $product_id,
        'quantity' => $quantity
    ];
}

// Update cookie
setcookie($cookie_name, json_encode($cart), $cookie_expire, "/");

if (!isset($user_id)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

try {
    // Check if item already exists in cart for this user
    $stmt = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update quantity
        $newQuantity = ($method === "POST") ? $existing['quantity'] + $quantity : $quantity;
        // $newQuantity = $existing['quantity'] + $quantity;
        if ($newQuantity > $available_stock) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Adding this quantity would exceed available stock in your database cart.']);
            exit;
        }
        $updateStmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
        $updateStmt->execute([$newQuantity, $existing['id']]);
    } else {
        // Insert new row
        $insertStmt = $pdo->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $insertStmt->execute([$user_id, $product_id, $quantity]);
    }

    echo json_encode(['success' => true, 'message' => $method === "POST" ? 'Item added to cart successfully.' : "Cart successfully Updated"]);
} catch (PDOException $e) {
    error_log("DB Error adding/updating cart item: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update cart in database.']);
}
