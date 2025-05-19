<?php
// connect to our database 
require_once __DIR__ . '/../../config/db.php';

// tells the client the type of data we are sending it (json)
header("Content-Type: application/json");

// get the token 
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

$token = str_replace("Bearer ", "", $token);

// check if the token is found 
if (!$token) {
    http_response_code(401); // Unautorized
    echo json_encode(["message" => "Access denied. No token provided."]);
    exit();
}

// look up the token in the data base and check its expiration data and time 
try {

    $stmt = $pdo->prepare("SELECT user_id FROM user_tokens WHERE token = ? AND  expires_at > NOW()");
    $stmt->execute([$token]);
    $valid_token = $stmt->fetch(); # so this will return if a valid token exists

    // check if a valid token exists 
    if (!$valid_token) {
        http_response_code(401);
        echo json_encode(["message" => "Access denied. Invalid or expired token."]);
        exit();
    }

    $user_id = $valid_token['user_id'];
} catch (\PDOException $e) {
    http_response_code(500);
    // error log for debugging purposes
    // error_log("Database error during token validation: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "An internal error occurred during authentication validation."]);
}
