<?php
    require_once '../config/db.php';

    $stmt = $pdo->query("SELECT id, name, image FROM categories ORDER BY name");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');

    if ($products){
        echo json_encode(["success" => true, "message" => "Products fetched successfully.", "data" => $products]);
    }else{
        http_response_code(405);
        echo json_encode(['error' => 'No products found in this category']);
    }

?>