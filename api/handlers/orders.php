<?php
require_once __DIR__ . "/../../config/db.php";
require_once "auth.php";

header("Content-Type: application/json");

$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

$paymentMethod = trim(htmlspecialchars($data["payment_method"] ?? ""));
$shippingAddress = htmlspecialchars($data["shipping_address"] ?? "");
$contactPhone = htmlspecialchars($data["contact_phone"] ?? "");
$billingAddress = htmlspecialchars($data["billing_address"] ?? "");
$notes = htmlspecialchars($data["notes"] ?? "");

$errors = [];

if (empty($paymentMethod)) {
    $errors[] = "Payment method is required.";
}
if (empty($shippingAddress)) {
    $errors[] = "Shipping Address is required.";
}
if (empty($contactPhone)) {
    $errors[] = "Contact Phone number is required.";
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(["success" => false, "errors" => $errors]);
    exit();
}

if (!isset($user_id)) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "User not authenticated."]);
    exit();
}

$orderId = null;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT
            ci.product_id,
            ci.quantity AS cart_quantity,
            p.title,
            p.price,
            p.stock_quantity
        FROM
            cart_items ci
        JOIN
            products p ON ci.product_id = p.id
        WHERE
            ci.user_id = ?"
    );
    $stmt->execute([$user_id]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);


    if (empty($cartItems)) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Your cart is empty."]);
        exit();
    }
    $totalPrice = 0;
    $stockErrors = [];

    foreach ($cartItems as $item) {
        if ($item['cart_quantity'] > $item['stock_quantity']) {
            $stockErrors[] = "Only {$item['stock_quantity']} are available for '{$item['title']}'. Please reduce the quantity in your cart.";
        }
        $totalPrice += $item['price'] * $item['cart_quantity'];
    }

    if (!empty($stockErrors)) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(["success" => false, "errors" => $stockErrors]);
        exit();
    }

    $stmt = $pdo->prepare("
        INSERT INTO orders (buyer_id, payment_method, shipping_address, contact_phone, billing_address, notes, total_price, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Processing')
    ");
    $orderInsertSuccess = $stmt->execute([
        $user_id,
        $paymentMethod,
        $shippingAddress,
        $contactPhone,
        $billingAddress,
        $notes,
        $totalPrice
    ]);

    if (!$orderInsertSuccess) {
        $pdo->rollBack();
        http_response_code(500);
        error_log("Order insertion failed for user_id: " . $user_id);
        echo json_encode(["success" => false, "message" => "Failed to create the main order record."]);
        exit();
    }

    $orderId = $pdo->lastInsertId();

    foreach ($cartItems as $item) {

        $stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $orderId,
            $item['product_id'],
            $item['cart_quantity'],
            $item['price']
        ]);

        $stmt = $pdo->prepare("
            UPDATE products
            SET stock_quantity = stock_quantity - ?
            WHERE id = ?
        ");
        $stmt->execute([$item['cart_quantity'], $item['product_id']]);
    }

    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $stmt->execute([$user_id]);

    $pdo->commit();

    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Successfully checked out", "order_id" => $orderId]);
} catch (\PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log("Order checkout PDOException: " . $e->getMessage() . "\nStack Trace: " . $e->getTraceAsString());
    echo json_encode(["success" => false, "message" => "An internal error occurred during checkout. Please try again.", "error_details" => $e->getMessage()]);
}
