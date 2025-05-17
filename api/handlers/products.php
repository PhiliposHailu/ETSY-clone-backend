<?php
require_once __DIR__ . '/../../config/db.php'; // Ensure this path is correct for your setup
header('Content-Type: application/json'); // Corrected Content-Type header

try {
    // Check if a category ID is provided in the query parameters
    $categoryId = null;
    if (isset($_GET['category']) && !empty($_GET['category'])) {
        // Sanitize the input - ensure it's an integer
        $categoryId = filter_var($_GET['category'], FILTER_VALIDATE_INT);

        // If filtering failed (not a valid integer), return an error
        if ($categoryId === false) {
            http_response_code(400); // Bad Request
            echo json_encode(["success" => false, "message" => "Invalid category ID provided."]);
            exit; // Stop execution
        }
    }

    // Base SQL query
    $sql = "
        SELECT
            p.id,
            p.title,
            p.description,
            p.price,
            p.stock_quantity,
            p.category_id,
            pi.image_url,
            pi.id AS image_id -- Get image ID if needed for ordering or reference
        FROM
            products p
        LEFT JOIN
            product_images pi ON p.id = pi.product_id
    ";

    // Add WHERE clause if category ID is provided
    if ($categoryId !== null) {
        $sql .= " WHERE p.category_id = :category_id";
    }

    // Add ORDER BY clause
    $sql .= " ORDER BY p.id, pi.id";

    $stmt = $pdo->prepare($sql);

    // Bind parameter if category ID is provided
    if ($categoryId !== null) {
        $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
    }

    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $products = [];
    foreach ($results as $row) {
        $product_id = $row['id'];

        // Initialize product entry if not already exists
        if (!isset($products[$product_id])) {
            $products[$product_id] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'price' => (float) $row['price'],
                'stock_quantity' => (int) $row['stock_quantity'],
                'category_id' => (int) $row['category_id'],
                'images' => [] // Initialize images array
            ];
        }

        // Add image URL if it exists for the current row
        if ($row['image_url'] !== null) {
            $products[$product_id]['images'][] = $row['image_url'];
        }
    }

    // Convert associative array to indexed array for the final JSON output
    $products = array_values($products);

    // Set HTTP status code and return JSON response
    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Products fetched successfully.", "data" => $products]);

} catch (\PDOException $e) {
    // Log the error for debugging purposes (optional but recommended)
    // error_log("Database Error: " . $e->getMessage());

    // Set HTTP status code and return JSON error response
    http_response_code(500); // Internal Server Error
    echo json_encode(["success" => false, "message" => "A database error occurred while fetching products.", "error" => $e->getMessage()]); // Include error message in dev/debug environments
} catch (\Exception $e) {
     // Handle other potential exceptions
    http_response_code(500); // Internal Server Error
    echo json_encode(["success" => false, "message" => "An unexpected error occurred.", "error" => $e->getMessage()]);
}

?>
