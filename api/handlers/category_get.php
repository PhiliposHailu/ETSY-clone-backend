<?php
    require_once '../config/db.php';

    $stmt = $pdo->prepare("SELECT id, name, image FROM categories WHERE name = ?");
    $stmt->execute([$category_name]);

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');

    if ($products){
        echo json_encode($products);
    }else{
        http_response_code(404);
        echo json_encode(['error' => 'No products found in this category']);
    }

?>