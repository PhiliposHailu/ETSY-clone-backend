<?php
// publicly accessible no autentication required
require_once '../config/db.php';
// sets up the content type as json , so basically means we are declaring that we will be sending back json file back to the frontend
header('Content-Type: applicaiton/json');

try {

    $stmt = $pdo->prepare("SELECT id, title, description, price, stock_quantity, category FROM products");
    $stmt->execute();

    // fetch all the products in the database as an associative array
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    // json_encode() changes the php associative array to json format before sending it to the forntend
    echo json_encode(["success" => true, "message" => "Products fetched successfully.", "data" => $products]);
} catch (\PDOException $e) {
    http_response_code(500); //server side faliure
    // Log the error for debugging(just incase)
    // error_log("Database error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "A database error occurred while fetching products."]); // a generic message is diplayed for the user , so sensitve information is not leaked to the public(so that it doesn't get explited)
}
