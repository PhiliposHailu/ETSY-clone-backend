<?php
// connect to the database 
require_once '././config/db.php';

// authenticate 
require_once 'auth.php';

// tell the client that we are sending it json response 
header('Content-Type: application/json');

// check if the Authenticated user is a seller or not 
// try {
//     $stmt = $pdo->prepare("SELECT is_seller FROM users WHERE id = ?");
//     $stmt->execute()
// }

// check if current user is a seller 
// $stmt = $pdo->prepare("SELECT ")