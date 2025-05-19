<?php
    require_once '../config/db.php';

    $stmt = $pdo->query("SELECT id, name, image, subtitle FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');

    if ($categories){
        echo json_encode(["success" => true, "message" => "Categories fetched successfully.", "data" => $categories]);
    }else{
        http_response_code(405);
        echo json_encode(['error' => 'No Categories available.']);
    }

?>