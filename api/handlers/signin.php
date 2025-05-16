<?php
// require_once __DIR__ . '/../../config/db.php';
require_once '../config/db.php';
// session_start();
header("Content-Type: application/json");

$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

$email = trim(filter_var($data["email"] ?? "", FILTER_SANITIZE_EMAIL));
$password = trim($data["password"] ?? "");

$errors = [];

if (empty($email)) $errors[] = "Email is required";
if (empty($password)) $errors[] = "Password is required";

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Validation failed.", "errors" => $errors]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $found_user = $stmt->fetch();

    if (!$found_user || !password_verify($password, $found_user["password_hash"])) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Invalid email or password."]);
        exit;
    }

    // token based authentication 
    $token = bin2hex(random_bytes(32));
    $expiry_time = date('Y-m-d H:i:s', strtotime('+7 days'));

    // delete any old tokens for this user
    $stmt_delete_old = $pdo->prepare("DELETE FROM user_tokens WHERE user_id = ?");
    $stmt_delete_old->execute([$found_user['id']]);

    // insert a new token 
    $stmt_insert_token = $pdo->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt_insert_token->execute([$found_user['id'], $token, $expiry_time]);

    http_response_code(200);
    echo json_encode( [ 
        'success' => true, 
        "message" => "Login successful.", 
        "user" => [ "id" => $found_user["id"], 
                    "username" => $found_user ["username"] 
        ],
        "token" => $token,
        "expires_at" => $expiry_time
        
    ] );

} catch (\PDOException $e) {
    http_response_code(500);
    // Log the error for debugging(just incase)
    // error_log("Database error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "A database error occurred during sign-in."]);
}
