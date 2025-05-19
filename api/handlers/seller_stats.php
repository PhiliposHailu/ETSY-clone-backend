<?php
require_once __DIR__ . '/../../config/db.php';
require_once 'auth.php';
header("Content-Type: application/json");


$authenticated_user_id = $user_id;

if (!$authenticated_user_id) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Authentication required."]);
    exit;
}

function calculatePercentageChange($current_value, $previous_value)
{
    if ($previous_value == 0) {
        if ($current_value > 0) {
            return "+" . number_format($current_value, 2) . " (New)";
        } else {
            return "0% change";
        }
    }

    $change = (($current_value - $previous_value) / $previous_value) * 100;

    if ($change > 0) {
        return "+" . number_format($change, 1) . "%";
    } elseif ($change < 0) {
        return number_format($change, 1) . "%";
    } else {
        return "0% change";
    }
}

try {

    $stmt = $pdo->prepare("SELECT seller_id FROM sellers WHERE user_id = ?");
    $stmt->execute([$authenticated_user_id]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$seller) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "User is not registered as a seller."]);
        exit;
    }

    $seller_id = $seller['seller_id'];

    $current_week_start = date('Y-m-d 00:00:00', strtotime('monday this week'));
    $current_week_end = date('Y-m-d 23:59:59', strtotime('sunday this week'));
    $previous_week_start = date('Y-m-d 00:00:00', strtotime('monday last week'));
    $previous_week_end = date('Y-m-d 23:59:59', strtotime('sunday last week'));

    $currentWeekSalesStmt = $pdo->prepare("
        SELECT SUM(o.total_price) AS total_sales
        FROM orders o
        JOIN (
            SELECT DISTINCT order_id
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE p.seller_id = ?
        ) AS seller_orders ON o.id = seller_orders.order_id
        WHERE o.created_at BETWEEN ? AND ?;
    ");
    $currentWeekSalesStmt->execute([$seller_id, $current_week_start, $current_week_end]);
    $current_week_sales_row = $currentWeekSalesStmt->fetch(PDO::FETCH_ASSOC);
    $current_week_total_sales = $current_week_sales_row['total_sales'] ?? 0;

    $currentWeekOrdersStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT o.id) AS total_orders
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ?
          AND o.created_at BETWEEN ? AND ?;
    ");
    $currentWeekOrdersStmt->execute([$seller_id, $current_week_start, $current_week_end]);
    $current_week_orders_row = $currentWeekOrdersStmt->fetch(PDO::FETCH_ASSOC);
    $current_week_total_orders = $current_week_orders_row['total_orders'] ?? 0;

    $previousWeekSalesStmt = $pdo->prepare("
        SELECT SUM(o.total_price) AS total_sales
        FROM orders o
        JOIN (
            SELECT DISTINCT order_id
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE p.seller_id = ?
        ) AS seller_orders ON o.id = seller_orders.order_id
        WHERE o.created_at BETWEEN ? AND ?;
    ");
    $previousWeekSalesStmt->execute([$seller_id, $previous_week_start, $previous_week_end]);
    $previous_week_sales_row = $previousWeekSalesStmt->fetch(PDO::FETCH_ASSOC);
    $previous_week_total_sales = $previous_week_sales_row['total_sales'] ?? 0;

    $previousWeekOrdersStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT o.id) AS total_orders
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ?
          AND o.created_at BETWEEN ? AND ?;
    ");
    $previousWeekOrdersStmt->execute([$seller_id, $previous_week_start, $previous_week_end]);
    $previous_week_orders_row = $previousWeekOrdersStmt->fetch(PDO::FETCH_ASSOC);
    $previous_week_total_orders = $previous_week_orders_row['total_orders'] ?? 0;

    $sales_change = calculatePercentageChange($current_week_total_sales, $previous_week_total_sales);
    $orders_change = calculatePercentageChange($current_week_total_orders, $previous_week_total_orders);

    $shop_stats = [
        [
            "name" => "Total Sales (This Week)",
            "value" => number_format($current_week_total_sales, 2),
            "type" => "currency",
            "change" => $sales_change
        ],
        [
            "name" => "Total Orders (This Week)",
            "value" => (int) $current_week_total_orders,
            "type" => "count",
            "change" => $orders_change
        ]
    ];

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "seller_id" => $seller_id,
        "message" => "Shop stats fetched successfully.",
        "data" => $shop_stats
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "A database error occurred while fetching shop stats."]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "An unexpected error occurred."]);
}
