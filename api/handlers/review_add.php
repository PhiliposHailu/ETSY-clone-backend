<?php
    require_once '../config/db.php';
    require_once 'auth.php';
    $data = json_decode(file_get_contents("php://input"), true);
    $_POST = $data ?? [];
    $rating = $_POST['rating'] ?? null;
    $comment = $_POST['comment'] ?? '';

    echo "product:{$product_id}, user:{$user_id}, rating{$rating}";

    // Basic validation
    if (!$product_id || !$user_id || !$rating || $rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid input. Ensure product_id, user_id, and rating (1-5) are provided."]);
        exit;
    }

    try {
        // Check if the user already reviewed the product (optional)
        $checkStmt = $pdo->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
        $checkStmt->execute([$product_id, $user_id]);

        if ($checkStmt->fetch()) {
            http_response_code(409);
            echo json_encode(["error" => "You have already reviewed this product."]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$product_id, $user_id, $rating, $comment]);

        echo json_encode(["message" => "Review added successfully."]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
?>
