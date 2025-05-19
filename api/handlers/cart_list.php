<?php
require_once '../config/db.php';
require_once 'auth.php';
header('Content-Type: application/json');

if (!isset($user_id)) {
     http_response_code(401); // Unauthorized
     echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
     exit;
}

try {
    $stmt = $pdo->prepare("SELECT
        ci.product_id,
        ci.quantity AS cart_quantity,
        p.title AS product_name,
        p.price,
        p.stock_quantity AS product_stock, -- Include product stock
        p.description AS product_description, -- Include product description
        c.name AS category_name,
        u.username AS seller_username, -- Get the seller's username
        (
            SELECT image_url FROM product_images
            WHERE product_id = p.id
            ORDER BY id ASC LIMIT 1
        ) AS product_image_url
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    JOIN sellers s ON p.seller_id = s.seller_id -- Link products to sellers
    JOIN users u ON s.user_id = u.id -- Get seller username from users table
    WHERE ci.user_id = ?");
    
    $stmt->execute([$user_id]);
    $dbCartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Cart items fetched successfully.',
        'data' => $dbCartItems
    ]);
} catch (PDOException $e) {
    error_log("DB Error fetching cart items: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, // Use success: false for errors
        'message' => 'Failed to fetch cart items from database.'
    ]);
}
