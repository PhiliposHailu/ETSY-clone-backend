<?php
require_once __DIR__ . '/../../config/db.php';
require_once 'auth.php';
header("Content-Type: application/json");

$authenticated_user_id = $user_id;

try {

    $stmtSellerCheck = $pdo->prepare("SELECT seller_id FROM sellers WHERE user_id = ?");
    $stmtSellerCheck->execute([$authenticated_user_id]);
    $seller = $stmtSellerCheck->fetch(PDO::FETCH_ASSOC);


    if (!$seller) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "User is not registered as a seller."]);
        exit;
    }

    $sql =
        "SELECT
        o.id AS order_id,
        o.total_price AS order_total,
        o.status AS order_status,
        o.created_at AS order_date,
        o.notes,
        u_buyer.username AS buyer_username,
        u_buyer.email AS buyer_email,
        oi.quantity AS product_quantity,
        oi.price AS product_price_at_order,
        p.id AS product_id,
        p.title AS product_title,
        p.description AS product_description,
        p.price AS current_product_price
    FROM
        orders o
    JOIN
        order_items oi ON o.id = oi.order_id
    JOIN
        products p ON oi.product_id = p.id
    JOIN
        users u_buyer ON o.buyer_id = u_buyer.id
    JOIN
        users u_seller ON p.seller_id = u_seller.id
    WHERE
        u_seller.id = ?
    ORDER BY
        o.created_at DESC;
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([$authenticated_user_id]);

    $raw_orders_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $orders = [];
    foreach ($raw_orders_data as $row) {
        $order_id = $row['order_id'];

        if (!isset($orders[$order_id])) {
            $orders[$order_id] = [
                'id' => $row['order_id'],
                'customer' => $row['buyer_username'],
                'date' => $row['order_date'],
                'total' => (float) $row['order_total'],
                'status' => $row['order_status'],
                'notes' => $row['notes'],
                'buyer_email' => $row['buyer_email'],
                'items' => []
            ];
        }

        $orders[$order_id]['items'][] = [
            'product_id' => $row['product_id'] ?? null,
            'title' => $row['product_title'] ?? 'Unknown Product',
            'quantity' => (int) ($row['product_quantity'] ?? 0),
            'price_at_order' => (float) ($row['product_price_at_order'] ?? 0.0),
            'current_price' => (float) ($row['current_product_price'] ?? 0.0),
            'description' => $row['product_description'] ?? ''
        ];
    }

    $orders = array_values($orders);

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "authenticated_user_id" => $authenticated_user_id,
        "message" => "Seller orders fetched successfully.",
        "data" => $orders
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unexpected Internal server problem."]);
}
