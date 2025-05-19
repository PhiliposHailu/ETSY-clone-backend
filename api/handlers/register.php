<?php

require_once '../config/db.php';
header("Content-Type: application/json");

// reads and decode the json data
$json_data = file_get_contents("php://input");
// json_decode() changes the json data recived from the frontend to a php format 
$data = json_decode($json_data, true);

$email = trim(filter_var($data["email"] ?? "", FILTER_SANITIZE_EMAIL));
$username = trim($data["username"] ?? "");
$password = trim($data["password"] ?? "");
$is_seller = isset($data["is_seller"]) ? (bool) $data["is_seller"] : false;

$errors = [];

if (empty($email)) $errors[] = "Email is required";
if (empty($username)) $errors[] = "Username is required";
if (empty($password)) $errors[] = "Password is required";

// Validate  the email formatting 
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";

// validate username 
if (!empty($username)) {
    if (strlen($username) < 3 || strlen($username) > 20) {
        $errors[] = "Username must be between 3 and 20 characters.";
    }
    if (!preg_match("/^[a-zA-Z0-9_]+$/", $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores.";
    }
}

// Validate password strength 
if (!empty($password)) {
    if (strlen($password) < 5) $errors[] = "Password must be at least 5 characters";
    if (!preg_match("/[A-Z]/", $password)) $errors[] = "Password must contain an uppercase letter";
    if (!preg_match("/[0-9]/", $password)) $errors[] = "Password must contain a number";
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Validation failed.", "errors" => $errors]);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, email, username FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $username]);
    $found = $stmt->fetch();

    if ($found) {
        http_response_code(409);
        $error_message = "Email or username already registered.";

        if ($found['email'] == $email && $found['username'] == $username)
            $error_message = "Email and username are already registered.";
        elseif ($found['email'] == $email) {
            $error_message = "Email address already registered.";
        } elseif ($found['username'] == $username) {
            $error_message = "Username already taken. Please choose another one.";
        }

        $pdo->rollBack();
        echo json_encode(["success" => false, "message" => $error_message]);
        exit;
    }

    // Register user in the users table
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (email, username, password_hash, is_seller) VALUES (?, ?, ?, ?)");
    $user_registered = $stmt->execute([$email, $username, $hashedPassword, $is_seller]);

    if (!$user_registered) {
        $pdo->rollBack();
        http_response_code(500); // Server Side error
        echo json_encode(["success" => false, "message" => "Failed to register user."]);
        exit;
    }

    $new_user_id = $pdo->lastInsertId();

    if ($is_seller) {
        $stmtSeller = $pdo->prepare("INSERT INTO sellers (user_id) VALUES (?)");
        $seller_registered = $stmtSeller->execute([$new_user_id]);

        if (!$seller_registered) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Failed to register seller profile."]);
            exit;
        }
    }

    $pdo->commit();

    $success_message = "Registration successful.";
    if ($is_seller) {
        $success_message .= " You have also been registered as a seller.";
    }

    http_response_code(201); // Created
    echo json_encode(["success" => true, "message" => $success_message]);
} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "A database error occurred during registration."]);
} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    // error_log("Unexpected error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "An unexpected error occurred during registration."]);
}
