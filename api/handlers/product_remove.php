<?php 
require_once '././config/db.php';
require_once 'auth.php';

header("Content-Type: application/json");

// check if user is seller or not 
try {
    $stmt = $pdo->prepare("SELECT is_seller FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || $user['is_seller'] == false) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Acess denied, Only sellers can delete products."]);
        exit();
    }

    // get user data 
    $json_data = file_get_contents("php://input");
    $data = json_decode($json_data, true);

    $product_id = null;

    // check if the product id exists in the JSON data from the request body 
    if (isset($data['product_id'])) {
        $product_id = $data["product_id"];
    }
    // check if it exists in our URL query parameters
    elseif (isset($_GET['product_id'])) {
        $product_id = $_GET['product_id'];
    }
    // if not found in either place 
    else {
        $product_id = '';
    }
    // vavlidat product id 
    $product_id = filter_var($product_id, FILTER_VALIDATE_INT);
    if ($product_id == false || $product_id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Valid product ID is required for deletion."]);
        exit(); # Stop our script executoin 
    }

    // Delete the Product 

    try {                       
                                // the product to delete and the matching owner of the product who posted it 
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
        $stmt->execute([$product_id, $user_id]);

        // Check if the Product was Successfuly Deleted 
        if ($stmt->rowCount() > 0) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Product deleted successfully."]);
        }else {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Product not found or you do not have permission to delete it."]);
        }


    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "An error occurred while deleting the product."]);
    }

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "An internal server error occurred during seller verification."]);
}