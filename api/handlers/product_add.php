<?php
// connect to the database
require_once __DIR__ . '/../../config/db.php';

// authenticate
require_once 'auth.php'; // Assumes auth.php sets $user_id

// tell the client that we are sending it json response
// header('Content-Type: application/json');

// Define the directory where images will be stored
$upload_dir = __DIR__ . '/../../uploads//'; // Adjust path as needed
// Ensure the upload directory exists and is writable
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0775, true); // Create directory recursively
}

if (!is_writable($upload_dir)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Upload directory is not writable."]);
    exit();
}

// check if the Authenticated user is a seller or not
try {
    // check if user is registered as a seller or not
    $stmt = $pdo->prepare("SELECT is_seller FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC); // Fetch as associative array

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

            // Generate a unique filename to prevent overwriting
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_file_name = uniqid('img_', true) . '.' . $file_ext;
            $destination_path = $upload_dir . $new_file_name;
            $public_path = '/uploads/' . $new_file_name; // Path accessible from the web

            // Move the uploaded file to the destination directory
            if (move_uploaded_file($file_tmp_name, $destination_path)) {
                $uploaded_image_paths[] = $public_path;
            } else {
                $errors[] = "Failed to move uploaded file '{$file_name}'.";
            }
        }
    } else {
        // Optionally require at least one image
        // $errors[] = "At least one image is required.";
    }

    var_dump($uploaded_image_paths);


    // if any validation errors exit and return bad request response.
    if (!empty($errors)) {
        // Clean up uploaded files if there were other errors
        foreach ($uploaded_image_paths as $path) {
            $full_path = __DIR__ . '/../../uploads/' . $path; // Construct full path
            if (file_exists($full_path)) {
                unlink($full_path); // Delete the file
            }
        }
        http_response_code(400); # bad request
        echo json_encode(["success" => false, "message" => "Validation failed.", "errors" => $errors]);
        exit();
    }

    // after confirming the user is a seller and validating his inputs we insert Product to our data base
    try {
        $pdo->beginTransaction(); // Start a transaction

        $stmt = $pdo->prepare(query: "INSERT INTO products (user_id, title, description, price, stock_quantity, category_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $description, $price, $stock_quantity, $category_id]);

        // Get the ID of the newly inserted product
        $product_id = $pdo->lastInsertId();

        // Insert image paths into a separate product_images table
        if (!empty($uploaded_image_paths)) {
            $stmt_images = $pdo->prepare("INSERT INTO product_images (product_id, image_url) VALUES (?, ?)");
            foreach ($uploaded_image_paths as $path) {
                $stmt_images->execute([$product_id, $path]);
            }
        }

        $pdo->commit(); // Commit the transaction

        // Success Response
        http_response_code(201); # successful product post creation
        echo json_encode(["success" => true, "message" => "Product added successfully.", "data" => ["id" => $product_id]]);
    } catch (\PDOException $e) {
        $pdo->rollBack(); // Roll back the transaction on error
        // Clean up uploaded files if database insertion failed
        foreach ($uploaded_image_paths as $path) {
            $full_path = __DIR__ . '/../../public' . $path; // Construct full path
            if (file_exists($full_path)) {
                unlink($full_path); // Delete the file
            }
        }
        http_response_code(500); # server side problem
        echo json_encode(["success" => false, "message" => "An error occurred while adding the product and images.". $e->getMessage()]);
    }
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "An internal server error occurred during seller verification or initial processing.", "errors" => $e->getMessage()]); // Include error message for debugging
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "An unexpected error occurred.", "errors" => $e->getMessage()]);
}
