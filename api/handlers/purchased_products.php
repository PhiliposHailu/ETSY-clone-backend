<?php
require_once '../config/db.php';
require_once 'auth.php';
header("Content-Type: application/json");

$authenticated_user_id = $user_id;

try {
    $sql =
        "SELECT
            oi.id AS order_item_id,
            oi.quantity,
            oi.price AS purchased_price, -- Price at the time of order
            o.created_at AS purchase_date,
            o.status AS order_status,
            p.id AS product_id,
            p.title AS product_title,
            s.seller_id,
            seller_user.username AS seller_name, -- Get the seller's username
            -- Subquery to get the first image URL for the product
            (
                SELECT image_url
                FROM product_images
                WHERE product_id = p.id
                ORDER BY id ASC -- Order by image ID to get a consistent 'first' image
                LIMIT 1
            ) AS product_image
        FROM
            users buyer_user -- Start with the authenticated user (buyer)
        JOIN
            orders o ON buyer_user.id = o.buyer_id -- Join to orders placed by this user
        JOIN
            order_items oi ON o.id = oi.order_id -- Join to items within those orders
        JOIN
            products p ON oi.product_id = p.id -- Join to the product details
        JOIN
            sellers s ON p.seller_id = s.seller_id -- Join to the seller record
        JOIN
            users seller_user ON s.user_id = seller_user.id -- Join to the seller's user record to get username
        WHERE
            buyer_user.id = ? -- Filter by the authenticated user's ID
        ORDER BY
            o.created_at DESC, oi.id ASC; -- Order by order date descending, then order item ID ascending
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$authenticated_user_id]);

    $purchased_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted_items = [];
    foreach ($purchased_items as $item) {
        $formatted_items[] = [
            "id" => (int) $item['order_item_id'],
            "quantity" => (int) $item['quantity'],
            "price" => (float) $item['purchased_price'],
            "purchaseDate" => $item['purchase_date'],
            "status" => $item['order_status'],
            "product_id" => (int) $item['product_id'],
            "product_title" => $item['product_title'],
            "product_image" => $item['product_image'] ?? null,
            "seller_id" => (int) $item['seller_id'],
            "seller_name" => $item['seller_name']
        ];
    }

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Purchased products fetched successfully.",
        "data" => $formatted_items
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "A database error occurred while fetching purchased products.", "error" => $e->getMessage()]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "An unexpected error occurred.", "error" => $e->getMessage()]);
}
