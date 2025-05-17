<?php 
require_once __DIR__ . '/../../config/db.php';
require_once 'auth.php';

header("Content-Type: application/json");

// check if the user is a seller or not 
try {
    $stmt = $pdo->prepare("SELECT is_seller FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // if no user or user not a seller 
    if (!$user || $user['is_seller'] == false) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Acess denied. Only sellere can update products."]);
        exit();
    }

    // get the input data 
    $json_data = file_get_contents("php://input");
    $data = json_decode($json_data, true);

    // validate input 
    if ($product_id == false || $product_id <= 0) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Valid product ID is required for update."]);
        exit();
    }

    // since the user might only want to change some fields i will need to Prepare update fields and values based on the provided data. 
    $update_fields = [];
    $update_values = [];
    $errors = [];

    // Validate each potential field for update. 
    if (isset($data['title'])) {
        $title = trim($data['title']);
        if (empty($title)) $errors[] = "Title can not be empty.";
        else {
            $update_fields[] = "title = ?";
            $update_values[] = $title;
        }
    }

    if (isset($data['description'])) {
        $description = trim($data['description']);
        // description can be empty 
        $update_fields[] = "description = ?";
        $update_values[] = $description;
    }

    if (isset($data['price'])) {
        $price =  filter_var( $data['price'], FILTER_VALIDATE_FLOAT);
        // if not valid input 
        if ($price == false || $price < 0) $errors[] = "Valid price is required.";
        else{
            $update_fields[] = "price = ?";
            $update_values[] = $price;
        }
    }

    if (isset($data['stock_quantity'])) {
        $stock_quantity = filter_var($data['stock_quantity'], FILTER_VALIDATE_INT);
        if ($stock_quantity == false || $stock_quantity < 0) $errors[] = "Valid stock quantity is required.";
        else {
            $update_fields[] = "stock_quantity = ?";
            $update_values[] = $stock_quantity;
        }
    }

    if (isset($data['category_id'])) {
        $category_id = $data['category_id'];
        if ($category_id == false || $category_id <= 0) $errors[] = "Valid category ID is required.";
        else {
            // check if the category id is actually found in our database
            try {
                $stmt_category = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
                $stmt_category->execute([$category_id]);
                $category = $stmt_category->fetch();
                // if no such category exists 
                if (!$category) $errors[] = "Invalid category ID.";
                else {
                    $update_fields[] = "category_id = ?";
                    $update_values[] = $category_id;
                }

            } catch (\PDOException $e) {
                $errors[] = "An error occurred while validating the category.";
            }
            
        }
    }

    // if not fields were given to be updated 
    if (empty($update_fields)) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "No fields provided for update."]);
        exit();
    }

    // if any validation errors occured 
    if (!empty($errors)) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Validation failed.", "errors" => $errors]);
        exit();
    }

    // check if the product we want to update exists 
    try {
        $sql = "UPDATE products SET " . implode(", ", $update_fields) . " WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);

        // add the product id and user_id at the end of our values array for the WHERE clause
        $update_values[] = $product_id;
        $update_values[] = $user_id;

        $stmt->execute($update_values);

        // Check if the product was Acutally Updated 
        if ($stmt->rowCount() > 0) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Product updated successfully."]);
        } else {
            // if rowCount == 0 can be because of
                // -> no change in our current data 
                // -> product with that id doesn't exist 
                // lets identify wich one it is 

            // first check if the product exists and belongs to the user
            $stmt_owner_checker = $pdo->prepare("SELECT id FROM products WHERE id = ? AND user_id = ?");
            $stmt_owner_checker->execute([$product_id, $user_id]);

            // if product exists and blongs to the same user 
            if ($stmt_owner_checker->fetch()) {
                http_response_code(200);
                echo json_encode(["success" => true, "message" => "Product found, but no changes were made as the data was identical."]);
            } else {
                http_response_code(403);
                echo json_encode(["success" => false, "message" => "Product not found or you do not have permission to update it."]);
            }
        }

    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "An error occurred while updating the product."]);
    }


} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "An internal server error occurred during seller verification."]);
}




