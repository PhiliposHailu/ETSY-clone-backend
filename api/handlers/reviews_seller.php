<?php
require_once '../config/db.php';
require_once 'auth.php';

$user_id ??= null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(["error" => "Authentication required."]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT
            r.id,
            r.rating,
            r.comment,
            r.created_at,
            reviewer_user.username AS reviewer_username, -- Alias for the username of the person who wrote the review
            p.id AS product_id -- Include product_id for context
        FROM users user -- Start with the authenticated user
        JOIN sellers s ON user.id = s.user_id -- Find the seller record for this user
        JOIN products p ON s.seller_id = p.seller_id -- Find products belonging to this seller
        JOIN reviews r ON p.id = r.product_id -- Find reviews for these products
        JOIN users reviewer_user ON r.user_id = reviewer_user.id -- Join the user who wrote the review
        WHERE user.id = ? -- Filter by the authenticated user's ID
        ORDER BY r.created_at DESC"
    );

    $stmt->execute([$user_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($reviews) {
        echo json_encode([
            "success" => true,
            "user_id" => $user_id,
            "message" => "Reviews fetched successfully for seller.",
            "data" => $reviews
        ]);
    } else {
        echo json_encode([
            "success" => true,
            "user_id" => $user_id,
            "message" => "No reviews found for this seller.",
            "data" => []
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "An unexpected error occurred: " . $e->getMessage()]);
}
