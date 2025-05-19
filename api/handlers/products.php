<?php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

try {
    $categoryId = null;
    if (isset($_GET['category']) && !empty($_GET['category'])) {
        $categoryId = filter_var($_GET['category'], FILTER_VALIDATE_INT);

        if ($categoryId === false) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid category ID provided."]);
            exit;
        }
    }

    $sellerUserId = null;
    $sellerId = null;
    if (isset($_GET['seller']) && !empty($_GET['seller'])) {
        $sellerUserId = filter_var($_GET['seller'], FILTER_VALIDATE_INT);

        if ($sellerUserId === false) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid seller user ID provided."]);
            exit;
        }

        $stmtSeller = $pdo->prepare("SELECT seller_id FROM sellers WHERE user_id = ?");
        $stmtSeller->execute([$sellerUserId]);
        $seller = $stmtSeller->fetch(PDO::FETCH_ASSOC);

        if ($seller) {
            $sellerId = $seller['seller_id'];
        } else {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "No products found for this seller user ID.", "data" => []]);
            exit;
        }
    }

    $sql = "SELECT
            p.id,
            p.title,
            p.description,
            p.price,
            p.original_price,
            p.stock_quantity,
            p.category_id,
            pi.image_url,
            pi.id AS image_id -- Get image ID if needed for ordering or reference
        FROM
            products p
        LEFT JOIN
            product_images pi ON p.id = pi.product_id
    ";

    $whereClauses = [];
    $params = [];
    $paramTypes = [];

    if ($categoryId !== null) {
        $whereClauses[] = "p.category_id = ?";
        $params[] = $categoryId;
        $paramTypes[] = PDO::PARAM_INT;
    }

    if ($sellerId !== null) {
        $whereClauses[] = "p.seller_id = ?";
        $params[] = $sellerId;
        $paramTypes[] = PDO::PARAM_INT;
    }

    // Combine WHERE clauses if any exist
    if (count($whereClauses) > 0) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }

    $sql .= " ORDER BY p.id, pi.id";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $i => $param) {
        $stmt->bindParam($i + 1, $params[$i], $paramTypes[$i]);
    }

    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $products = [];
    foreach ($results as $row) {
        $product_id = $row['id'];

        if (!isset($products[$product_id])) {
            $products[$product_id] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'price' => (float) $row['price'],
                'original_price' => $row['original_price'] !== null ? (float) $row['original_price'] : null, // Handle potential null
                'stock_quantity' => (int) $row['stock_quantity'],
                'category_id' => (int) $row['category_id'],
                'images' => []
            ];
        }

        if ($row['image_url'] !== null) {
            $products[$product_id]['images'][] = $row['image_url'];
        }
    }

    $products = array_values($products);

    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Products fetched successfully.", "data" => $products]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "A database error occurred while fetching products.", "error" => $e->getMessage()]); // Include error message for debugging
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "An unexpected error occurred.", "error" => $e->getMessage()]);
}
