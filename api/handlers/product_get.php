<?php 
require_once __DIR__ . '/../../config/db.php';
header("Content-Type: application/json");

// Getting products id 
// so we assume that the product ID is given from the router 
// based on the routers pattern the variable name is $product_id

// lets validate the given product_id
$value = $product_id ?? null; // do we have a product id , if yes value == product id else defaultValue(null)
$product_id = filter_var( $value, FILTER_VALIDATE_INT );

if ($product_id == null || $product_id == false) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid or missing product ID."]);
    exit();
}

try{
    $stmt = $pdo->prepare("SELECT id, user_id, title, description, price, stock_quantity, category, created_at FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Product fetched successfully.", "data" => $product]);
        // data => holds all the data about the product we retrived earlier 
    } else {
        // if we did not find any product with that id 
        http_response_code(404); // not found error'
        echo json_encode(["success" => false, "message" => "Product not found."]);
    }
} catch (\PDOException $e) {
    http_response_code(500); // server side error
    // for debugging purposes
    // error_log("Database error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "A database error occurred while fetching the product."]); // generic message
}

?>