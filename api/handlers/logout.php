<?php 

require_once '../../config/db.php';

header("Content-Type: application/json");

$authHeader = $_SERVER['HTTP_AUTHORIZAION'] ?? '';
$token = null;

if (!empty($authHeader) && preg_match( '/Bearer\s(\S+)/' , $authHeader, $matches)) {
    $token = $matches[1];
}

// check if the token is found 
if (!$token) {
    http_response_code(401); // token not found
    echo json_encode(["success" => false, "message" => "Logout failed. No token provided."]);
    exit();
}

// delete the access token 
try {
    // delete the access token in our table
    $stmt = $pdo->prepare('DELETE FROM user_tokens WHERE token = ?');
    $stmt->execute([$token]);

    // check if the token was really deleted
        // +> rowCount here gives us the rows affected 
    if  ($stmt->rowCount() > 0) {
        http_response_code(200); // successful logout
        echo json_encode(["success" => true, "message" => "Logout successful. Token revoked."]);
        
    } else {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Logout failed. Invalid or already revoked token"]);
    }
} catch (\PDOException) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "An internal error occurred during logout."]);
    exit();
}