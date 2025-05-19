<?php 

require_once __DIR__ . "/../../config/db.php";
require_once "auth.php";

header("Content-Type: application/json");

$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

$paymentMethod = trim(htmlspecialchars($data["paymentMethod"]));
$shippingAddress = htmlspecialchars($data["shippingAddress"]);
$contactPhone = htmlspecialchars($data["contactPhone"]);
$notes = htmlspecialchars($data["notes"]);

// validate user inputs 
$errors = [];

if (empty($paymentMethod)) $errors[] = "Payment required.";
if (empty($shippingAddress)) $errors[] = "Shipping Adress is required.";
if (empty($contactPhone)) $errors[] = "Contact Phone number is required";

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(["success" => false, "errors" => $errors]);
    exit();
}

// first get chart items from the user 
// assumming the front end will send me the user id ???????????????????? micky check me is this correct 
try {
    $pdo->beginTransaction();

    // get chart items 
    $stmt = $pdo->prepare("SELECT ci.*, p.title, p.price, p.stock_quantity FROM cart_items ci JOIN products p ON ci.product_id = p.id WHERE ci.user_id = ?");
    $stmt->execute([$user_id]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cartItems)) {
        http_response_code(400);
        echo json_encode(["sucess" => false, "message" => "Cart items is empty"]);
        exit();
    }

    // validate stock quatities and caluculate total
    $totalPrice = 0;

    // checking if each item we ordered is available in the quantity we specified
    foreach ($cartItems as $item) {
        if ($item['quantity'] > $item['stock_quantity']) {
            $errors[] = "Only {$item['stock_quantity']} are available for {$item['title']}";
        }
        $totalPrice += $item['price'] * $item['quantity'];
    }

    if (!empty($errors)) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(["sucess" => false, "errors" => $errors]);
        exit();
    }

    // Create order record 
    $stmt = $pdo->prepare("
    INSERT INTO orders (buyer_id, total_price, status)
    VALUES (?, ?, 'Processing')
    ");
    $stmt->execute([$user_id, $totalPrice]);
    $orderId = $pdo->lastInsertId(); // need it to add the ordered items to ordered table and associate the ordered_item and orderId

    // Create order items and update product quantities 
    foreach ($cartItems as $item) {
        // add order one at a time 
        $stmt = $pdo->prepare("
        INSERT INTO  order_items (order_id, product_id, quantity, price)
        VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
        
        // update the product stock(basically reduce by the number of quantities ordered)
        $stmt = $pdo->prepare("
        UPDATE products
        SET stock_quantity = stock_quantity - ? WHERE id = ?
        ");
        $stmt->execute([$item['quantity'], $item['product_id']]);
    }


    // Clear the cart 
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Transaction Completed 
    $pdo->commit();

    http_response_code(200);
    echo json_encode(["sucess" => true, "message" => "Sucessfully checked out"]);
    exit();


} catch(\PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["sucess" => false, "message" => "Internal Error"]);

}