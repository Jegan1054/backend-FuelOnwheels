<?php
header("Content-Type: application/json");
require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

if ($email === '' || $password === '') {
    echo json_encode(["status" => false, "message" => "Email or password missing"]);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM registration WHERE Email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => false, "message" => "Invalid credentials"]);
    exit;
}

$user = $result->fetch_assoc();

// IMPORTANT FIX
$inputPassword = trim($password);
$dbPassword    = trim($user['Password']);

if ($inputPassword !== $dbPassword) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid credentials"
    ]);
    exit;
}

echo json_encode([
    "status" => true,
    "message" => "Login successful",
    "user" => [
        "id" => $user['Id'],
        "name" => $user['Name'],
        "email" => $user['Email'],
        "type" => $user['User_Type']
    ]
]);
