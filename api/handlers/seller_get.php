<?php
require_once '../config/db.php';
header("Content-Type: application/json");

// get the id extracted by the Router
$value = $seller_id ?? null;
$seller_user_id = filter_var($value, FILTER_VALIDATE_INT);

try {
    // Fetch seller info by ID
    $stmt = $pdo->prepare("
        SELECT 
            u.id AS user_id,  -- Alias user ID for clarity
            u.username,
            u.is_seller,
            s.seller_id,  -- Get the seller_id from the sellers table
            s.bio,
            s.join_date,
            s.rating
        FROM users u -- Alias the users table as 'u'
        INNER JOIN sellers s ON u.id = s.user_id  -- Join users and sellers tables on user_id
        WHERE s.seller_id = ?
        ");
    $stmt->execute([$seller_user_id]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($seller) {
        echo json_encode(["success" => true, "message" => "Seller fetched successfully.", "data" => $seller]);
    } else {
        http_response_code(404); // not found
        echo json_encode(["success" => false, "message" => "Seller not found."]);
        exit();
    }
} catch (\PDOException $e) {
    http_response_code(500); //server side error
    // Log the error for debugging(just incase)
    error_log("Database error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "A database error occurred while fetching seller details."]);
    exit;
}
