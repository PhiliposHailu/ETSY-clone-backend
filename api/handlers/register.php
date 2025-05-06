<?php
require_once '../../config/db.php';
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

$email = trim(filter_var($data["email"] ?? "", FILTER_SANITIZE_EMAIL));
$firstName = trim(htmlspecialchars($data["name"] ?? ""));
$password = trim($data["password"] ?? "");

$errors = [];

if (empty($email)) $errors[] = "Email is required";
if (empty($firstName)) $errors[] = "Name is required";
if (empty($password)) $errors[] = "Password is required";

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
if (strlen($password) < 7) $errors[] = "Password must be at least 7 characters";
if (!preg_match("/[A-Z]/", $password)) $errors[] = "Password must contain an uppercase letter";
if (!preg_match("/[0-9]/", $password)) $errors[] = "Password must contain a number";

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(["errors" => $errors]);
    exit;
}

// Check if email exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    http_response_code(400);
    echo json_encode(["error" => "Email already registered"]);
    exit;
}

// Register user
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (email, name, password) VALUES (?, ?, ?)");
$stmt->execute([$email, $firstName, $hashedPassword]);

http_response_code(201);
echo json_encode(["message" => "Registration successful"]);
