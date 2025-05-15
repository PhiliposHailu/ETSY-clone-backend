<?php
    require_once '../config/db.php';

    $stmt = $pdo->query("SELECT * FROM products");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');

    if ($products){
        echo json_encode($products);
    }else{
        http_response_code(404);
        echo json_encode(['error' => 'No products found in this category']);
    }

?>