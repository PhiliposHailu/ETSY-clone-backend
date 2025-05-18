<?php
require_once __DIR__ . '/../../config/db.php';
header("Content-Type: application/json");

require_once 'auth.php';

try {
    $stmt = $pdo->prepare("SELECT id, username, email, is_seller, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Authenticated user data fetched successfully.",
            "data" => $user
        ]);
    } else {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Authenticated user not found."]);
    }
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "An internal server error occurred while fetching user details."]);
}
