<?php
require_once '../config/db.php';
require_once 'auth.php';
header('Content-Type: application/json');

$authenticated_user_id = $user_id ?? null;

if (!$authenticated_user_id) {
    http_response_code(401);
    echo json_encode(["error" => "Authentication required."]);
    exit;
}

function formatTimeAgo($timestamp)
{
    $diff = time() - strtotime($timestamp);
    if ($diff < 60) {
        $seconds = $diff;
        return $seconds . ($seconds === 1 ? " second ago" : " seconds ago");
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ($minutes === 1 ? " minute ago" : " minutes ago");
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ($hours === 1 ? " hour ago" : " hours ago");
    } elseif ($diff < 172800) {
        return "Yesterday";
    } else {
        $days = floor($diff / 86400);
        return $days . ($days === 1 ? " day ago" : " days ago");
    }
}

try {
    $stmt = $pdo->prepare("SELECT seller_id FROM sellers WHERE user_id = ?");
    $stmt->execute([$authenticated_user_id]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$seller) {
        http_response_code(403);
        echo json_encode(["error" => "User is not registered as a seller."]);
        exit;
    }

    $seller_id = $seller['seller_id'];

    $time_window = "INTERVAL 14 DAY";

    $sql = "SELECT-- Recent New Orders for this seller's products
            o.id AS activity_id,
            o.total_price,
            o.created_at AS activity_time,
            buyer_user.username AS related_username, -- The user who placed the order (buyer)
            NULL AS rating, -- Not applicable for orders
            NULL AS product_title, -- Not directly needed in the order summary text
            'new_order' AS activity_type,
            'order_with_price' AS data_type -- Type indicating price is included
        FROM orders o
        JOIN users buyer_user ON o.buyer_id = buyer_user.id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ? -- Filter by the seller's products
          AND o.created_at >= DATE_SUB(NOW(), {$time_window}) -- Filter by recent creation time
        GROUP BY o.id -- Group by order to avoid duplicate rows if an order contains multiple items from this seller

        UNION ALL

        -- Recent New Reviews for this seller's products
        SELECT
            r.id AS activity_id,
            NULL AS total_price, -- Not applicable for reviews
            r.created_at AS activity_time,
            reviewer_user.username AS related_username, -- The user who wrote the review
            r.rating,
            p.title AS product_title, -- The product title being reviewed
            'new_review' AS activity_type,
            'review_no_price' AS data_type -- Type indicating no price
        FROM reviews r
        JOIN users reviewer_user ON r.user_id = reviewer_user.id
        JOIN products p ON r.product_id = p.id
        WHERE p.seller_id = ? -- Filter by the seller's products
          AND r.created_at >= DATE_SUB(NOW(), {$time_window}) -- Filter by recent review time

        UNION ALL

        -- Recent Delivered Orders for this seller's products
        -- Assuming 'Completed' status signifies delivery and updated_at is set upon status change.
        -- Adjust status condition if your database uses a different value for 'delivered'.
        SELECT
            o.id AS activity_id,
            NULL AS total_price, -- Not applicable for delivered status update text
            o.updated_at AS activity_time, -- Use the update time for status changes
            buyer_user.username AS related_username, -- The user who placed the order (buyer)
            NULL AS rating, -- Not applicable
            NULL AS product_title, -- Not applicable
            'order_delivered' AS activity_type,
            'order_no_price' AS data_type -- Type indicating no price
        FROM orders o
        JOIN users buyer_user ON o.buyer_id = buyer_user.id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ? -- Filter by the seller's products
          AND o.status = 'Completed' -- Assuming 'Completed' status
          AND o.updated_at >= DATE_SUB(NOW(), {$time_window}) -- Filter by recent update time
        GROUP BY o.id -- Group by order to avoid duplicate rows

        ORDER BY activity_time DESC -- Order all activities by their timestamp
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([$seller_id, $seller_id, $seller_id]);

    $recent_activities_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted_activities = [];

    foreach ($recent_activities_raw as $activity) {
        $formatted_text = "";
        $type = $activity['data_type'];
        $time_ago = formatTimeAgo($activity['activity_time']);

        switch ($activity['activity_type']) {
            case 'new_order':

                $formatted_text = "New order #ORD-" . htmlspecialchars($activity['activity_id']) . " from " . htmlspecialchars($activity['related_username']);
                break;
            case 'new_review':
                $formatted_text = "New " . htmlspecialchars($activity['rating']) . "-star review on '" . htmlspecialchars($activity['product_title']) . "'";
                break;
            case 'order_delivered':
                $formatted_text = "Order #ORD-" . htmlspecialchars($activity['activity_id']) . " was delivered";
                break;
            default:
                continue 2;
        }
        $activity_data = [
            "text" => $formatted_text,
            "time_ago" => $time_ago,
            "type" => $type,
        ];

        if ($type === 'order_with_price') {
            $activity_data["price"] = number_format($activity['total_price'], 2);
        }
        $formatted_activities[] = $activity_data;
    }

    echo json_encode([
        "success" => true,
        "seller_id" => $seller_id,
        "message" => "Recent activities fetched successfully.",
        "data" => $formatted_activities
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "An unexpected error occurred: " . $e->getMessage()]);
}
