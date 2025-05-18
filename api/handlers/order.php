<?php 

// connect to my db 
require_once __DIR__ . '/../../config/db.php';

// says :- sending json files your way front end (just specifies the data we send will be in json format)
header("Content-Type: application/json");

// $seller_id gets sent from the front end and gets extracted throght my router and sent here

try{
    // apprently this filters out all the orders a loged in seller has recieved
    $stmt = $pdo->prepare("
        SELECT
        o.id AS order_id,
        o.total_price AS order_total,
        o.status AS order_status,
        o.created_at AS order_date,
        u_buyer.username AS buyer_username,
        u_buyer.email AS buyer_email,
        oi.quantity AS product_quantity,
        oi.price AS product_price_at_order,
        p.title AS product_title,
        p.description AS product_description,
        p.price AS current_product_price -- Note: This is the price in the products table, not the order item price
    FROM
        orders o
    JOIN
        order_items oi ON o.id = oi.order_id
    JOIN
        products p ON oi.product_id = p.id
    JOIN
        users u_buyer ON o.buyer_id = u_buyer.id
    WHERE
        p.user_id = ?");

    $stmt->execute([$seller_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    http_response_code(200);
    if ($orders) {
        echo json_encode(["success" => true, "message" => "You have recieved orders.", "data" => $orders]);

    } else {
        echo json_encode(["success" => false, "message" => "You have recieved no orders", "data" => []]);
    }

} catch (\PDOException $e) {
    http_response_code(500); // internal server Problem
    echo json_encode(["success" => false, "message" => "Unexpected Internal server problem."]);
}