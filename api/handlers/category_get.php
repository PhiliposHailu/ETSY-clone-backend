<?php
    require_once '../config/db.php';

    $stmt = $pdo->prepare("SELECT id, name, image, subtitle FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);

    $category = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');

    if ($category){
        echo json_encode(["success" => true, "message" => "Categories fetched successfully.", "data" => $category]);
    }else{
        http_response_code(404);
        echo json_encode(['error' => 'No products found in this category']);
    }

?>