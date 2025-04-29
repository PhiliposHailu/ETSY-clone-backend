<?php
require_once '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"];
    $email = $_POST["email"];
    $password = $_POST["password"];
    $is_seller = isset($_POST["is_seller"]) ? 1 : 0;

    if (empty($username) || empty($email) || empty($password)) {
        die("All fields are required.");
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, is_seller) VALUES (?, ?, ?, ?)");

    try {
        $stmt->execute([$username, $email, $passwordHash, $is_seller]);
        echo "Signup successful!";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo "Email or username already exists.";
        } else {
            echo "Error: " . $e->getMessage();
        }
    }
}
