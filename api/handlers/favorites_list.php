<?php
require_once 'auth.php';

$stmt = $pdo->prepare("SELECT 
                p.id,
                p.title,
                p.seller_id,
                p.original_price,
                p.stock_quantity,
                p.category_id,
                pi.image_url,
                p.price,
                p.description
            FROM favorites f
            JOIN products p ON f.product_id = p.id
            LEFT JOIN product_images pi ON pi.product_id = p.id
            WHERE f.user_id = :user_id
            GROUP BY p.id
        ");
$stmt->execute(['user_id' => $user_id]);
$favorites = $stmt->fetchAll();

echo json_encode($favorites);
