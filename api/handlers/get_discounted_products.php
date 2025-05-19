<?php
require_once '../config/db.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare(
        "SELECT
            p.id,
            p.seller_id,
            p.title,
            p.description,
            p.price,
            p.original_price,
            p.stock_quantity,
            p.category_id,
            c.name AS category_name,
            p.created_at,
            (
                SELECT GROUP_CONCAT(image_url ORDER BY id ASC SEPARATOR ',')
                FROM product_images
                WHERE product_id = p.id
            ) AS image_urls_string, -- Get all image URLs as a comma-separated string
            AVG(r.rating) AS average_rating -- Calculate the average rating
        FROM products p
        JOIN categories c ON p.category_id = c.id
        LEFT JOIN reviews r ON p.id = r.product_id -- LEFT JOIN to include products with no reviews
        WHERE p.original_price IS NOT NULL AND p.original_price > p.price
        GROUP BY p.id, p.seller_id, p.title, p.description, p.price, p.original_price,
                 p.stock_quantity, p.category_id, c.name, p.created_at -- Group by all non-aggregated columns
        ORDER BY p.created_at DESC -- Example ordering, adjust as needed"
    );
    $stmt->execute();
    $discounted_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($discounted_products as &$product) {
        if (!empty($product['image_urls_string'])) {
            $product['images'] = explode(',', $product['image_urls_string']);
        } else {
            $product['images'] = [];
        }
        unset($product['image_urls_string']);
        $product['average_rating'] = $product['average_rating'] !== null ? (float)$product['average_rating'] : 0.0;
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Discounted products fetched successfully.',
        'data' => $discounted_products
    ]);
} catch (PDOException $e) {

    error_log("DB Error fetching discounted products: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred while fetching discounted products.'
    ]);
}
