<?php
require_once '../config/db.php';
require_once 'auth.php';

$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

$product_id = filter_var($data['product_id'] ?? null, FILTER_VALIDATE_INT);

$user_id = $authenticated_user_id ?? null;

$rating = filter_var($data['rating'] ?? null, FILTER_VALIDATE_INT);
$comment = trim($data['comment'] ?? '');

$errors = [];
if (!$product_id) $errors[] = "Product ID is required and must be a valid integer.";
if (!$user_id) $errors[] = "User authentication required.";
if ($rating === null || $rating === false || $rating < 1 || $rating > 5) $errors[] = "Rating is required and must be between 1 and 5.";

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Validation failed.", "errors" => $errors]);
    exit;
}

try {

    $pdo->beginTransaction();
    $checkStmt = $pdo->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
    $checkStmt->execute([$product_id, $user_id]);

    if ($checkStmt->fetch()) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "You have already reviewed this product."]);
        exit;
    }

    $insertReviewStmt = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
    $review_inserted = $insertReviewStmt->execute([$product_id, $user_id, $rating, $comment]);

    if (!$review_inserted) {

        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to add review."]);
        exit;
    }

    $getSellerStmt = $pdo->prepare("SELECT seller_id FROM products WHERE id = ?");
    $getSellerStmt->execute([$product_id]);
    $product = $getSellerStmt->fetch(PDO::FETCH_ASSOC);

    if (!$product || !isset($product['seller_id'])) {

        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Product not found or not associated with a seller."]);
        exit;
    }

    $seller_id = $product['seller_id'];

    $calculateRatingStmt = $pdo->prepare(
        "SELECT AVG(r.rating) AS average_rating
            FROM reviews r
            JOIN products p ON r.product_id = p.id
            WHERE p.seller_id = ? "
    );
    $calculateRatingStmt->execute([$seller_id]);
    $average_rating_row = $calculateRatingStmt->fetch(PDO::FETCH_ASSOC);

    $new_average_rating = $average_rating_row['average_rating'];
    $updateSellerRatingStmt = $pdo->prepare("UPDATE sellers SET rating = ? WHERE seller_id = ?");

    $seller_rating_updated = $updateSellerRatingStmt->execute([number_format($new_average_rating, 2), $seller_id]);

    if (!$seller_rating_updated) {

        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to update seller rating."]);
        exit;
    }
    $pdo->commit();

    http_response_code(201);
    echo json_encode(["success" => true, "message" => "Review added and seller rating updated successfully."]);
} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "A database error occurred."]);
} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "An unexpected error occurred."]);
}
