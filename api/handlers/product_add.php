<?php
// connect to the database 
require_once '././config/db.php';

// authenticate 
require_once 'auth.php';

// tell the client that we are sending it json response 
header('Content-Type: application/json');

// check if the Authenticated user is a seller or not 
try {
    // check if user is registered as a seller or not 
    $stmt = $pdo->prepare("SELECT is_seller FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(); # fetch the column is_seller value (0 or 1) if 1 true else False

    // if no user or is not a seller , deny access.
    if (!$user or !$user['is_seller']) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Access denied. Only sellers can add products."]);
        exit();
    }

    // get and validate input 
    $json_data = file_get_contents("php://input");
    $data = json_decode($json_data, true);

    $errors = [];
    $title = trim($data['title'] ?? '');
    $description = trim($data['description' ?? '']);
    $price = filter_var($data['price'] ?? '', FILTER_VALIDATE_FLOAT);
    $stock_quantity = filter_var($data['stock_quantity'] ?? '', FILTER_VALIDATE_INT);
    $category_id = filter_var($data['category_id'] ?? '', FILTER_VALIDATE_INT);

    // validation 
    if (empty($title)) $errors[] = "Title is required.";

    // if price not given or if less thatn 0
    if ($price == false || $price < 0) $errors[] = "Valid price is required.";

    if ($stock_quantity == false || $stock_quantity < 0) $errors[] = "Valid stock quantity is required.";

    if ($category_id == false || $category_id <= 0) $errors[] = "valid cagegory is required.";

    // check if the given category_id exists in our table 
    if ($category_id != false and $category_id > 0) {
        $stmt_category = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt_category->execute([$category_id]);
        $category = $stmt_category->fetch();
        if (!$category) {
            $errors[] = "Invalid category ID.";
        }
    }

    // if any validaion errorss exit and return bad request response.
    if (!empty($errors)) {
        http_response_code(400); # bad request
        echo json_encode(["success" => false, "message" => "Valdiation falied.", "errors" => $errors]);
        exit();
    }

    // after confirming the user is a seller and validation his inputs we insert Product to our data base 
    try {
        $stmt = $pdo->prepare("INSERT INTO products (user_id, title, description, price, stock_quantity, category_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $description, $price, $stock_quantity, $category_id]);

        // Get the ID of the newly inserted product 
        $product_id = $pdo->lastInsertId();

        // Success Response
        http_response_code(201); # successful product post creation
        echo json_encode(["success" => true, "message" => "Product added successfully.", "product_id" => $product_id]);

    } catch (\PDOException $e) {
        http_response_code(500); # server side problem
        echo json_encode(["success" => false, "message" => "An error occurred while adding the product."]);
    }


} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "messgae" => "An internal server error occurred during seller verification."]);
}