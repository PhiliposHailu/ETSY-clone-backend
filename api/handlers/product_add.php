<?php
require_once __DIR__ . '/../../config/db.php';

require_once 'auth.php';

$upload_dir = __DIR__ . '/../../uploads//';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0775, true);
}

if (!is_writable($upload_dir)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Upload directory is not writable."]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT is_seller FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // if no user or is not a seller , deny access.
    if (!$user || !$user['is_seller']) {
        http_response_code(403); // Use 403 Forbidden for access denied
        echo json_encode(["success" => false, "message" => "Access denied. Only sellers can add products."]);
        exit();
    }

    $data = $_POST;
    $errors = [];
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $price = filter_var($data['price'] ?? '', FILTER_VALIDATE_FLOAT);
    $stock_quantity = filter_var($data['stock_quantity'] ?? '', FILTER_VALIDATE_INT);
    $category_id = filter_var($data['category_id'] ?? '', FILTER_VALIDATE_INT);

    // validation
    if (empty($title)) $errors[] = "Title is required.";
    if (strlen($title) > 255) $errors[] = "Title cannot exceed 255 characters.";

    // if price not given or if less that 0
    if ($price === false || $price < 0) $errors[] = "Valid price is required.";

    if ($stock_quantity === false || $stock_quantity < 0) $errors[] = "Valid stock quantity is required.";

    if ($category_id === false || $category_id <= 0) $errors[] = "Valid category is required.";

    // check if the given category_id exists in our table
    if ($category_id !== false && $category_id > 0) {
        $stmt_category = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt_category->execute([$category_id]);
        $category = $stmt_category->fetch(PDO::FETCH_ASSOC);
        if (!$category) {
            $errors[] = "Invalid category ID.";
        }
    }

    // Image validation and handling
    $uploaded_image_paths = [];
    if (isset($_FILES['images'])) {
        $total_files = count($_FILES['images']['name']);
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_file_size = 10 * 1024 * 1024;

        for ($i = 0; $i < $total_files; $i++) {
            $file_name = $_FILES['images']['name'][$i];
            $file_tmp_name = $_FILES['images']['tmp_name'][$i];
            $file_type = $_FILES['images']['type'][$i];
            $file_size = $_FILES['images']['size'][$i];
            $file_error = $_FILES['images']['error'][$i];

            if ($file_error !== UPLOAD_ERR_OK) {
                $errors[] = "Error uploading file '{$file_name}': " . $file_error;
                continue;
            }

            if ($file_size > $max_file_size) {
                $errors[] = "File '{$file_name}' is too large (max {$max_file_size} bytes).";
                continue;
            }

            if (!in_array($file_type, $allowed_types)) {
                $errors[] = "Invalid file type for '{$file_name}'. Only JPEG, PNG, and GIF are allowed.";
                continue;
            }

            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_file_name = uniqid('img_', true) . '.' . $file_ext;
            $destination_path = $upload_dir . $new_file_name;
            $public_path = '/uploads/' . $new_file_name;

            // Move the uploaded file to the destination directory
            if (move_uploaded_file($file_tmp_name, $destination_path)) {
                $uploaded_image_paths[] = $public_path;
            } else {
                $errors[] = "Failed to move uploaded file '{$file_name}'.";
            }
        }
    } else {
        $errors[] = "At least one image is required.";
    }

    var_dump($uploaded_image_paths);


    if (!empty($errors)) {
        foreach ($uploaded_image_paths as $path) {
            $full_path = __DIR__ . '/../../uploads' . $path;
            if (file_exists($full_path)) {
                unlink($full_path);
            }
        }
        http_response_code(400); # bad request
        echo json_encode(["success" => false, "message" => "Validation failed.", "errors" => $errors]);
        exit();
    }

    try {
        $pdo->beginTransaction(); // Start a transaction
        $stmt = $pdo->prepare("SELECT is_seller FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $seller = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$seller || !$seller['seller_id']) {
            echo json_encode(["success" => false, "message" => "Seller data not found on sellers table.", "data"]);
            exit();
        }
        $seller_id = $seller['seller_id'];

        $stmt = $pdo->prepare(query: "INSERT INTO products (seller_id, title, description, price, stock_quantity, category_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$seller_id, $title, $description, $price, $stock_quantity, $category_id]);

        $product_id = $pdo->lastInsertId();

        if (!empty($uploaded_image_paths)) {
            $stmt_images = $pdo->prepare("INSERT INTO product_images (product_id, image_url) VALUES (?, ?)");
            foreach ($uploaded_image_paths as $path) {
                $stmt_images->execute([$product_id, $path]);
            }
        }

        $pdo->commit();

        http_response_code(201);
        echo json_encode(["success" => true, "message" => "Product added successfully.", "data" => ["id" => $product_id]]);
    } catch (\PDOException $e) {
        $pdo->rollBack();
        foreach ($uploaded_image_paths as $path) {
            $full_path = __DIR__ . '/../../uploads/' . $path;
            if (file_exists($full_path)) {
                unlink($full_path);
            }
        }
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "An error occurred while adding the product and images." . $e->getMessage()]);
    }
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "An internal server error occurred during seller verification or initial processing.", "errors" => $e->getMessage()]); // Include error message for debugging
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "An unexpected error occurred.", "errors" => $e->getMessage()]);
}
