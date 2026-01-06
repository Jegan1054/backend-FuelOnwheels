<?php
header("Content-Type: application/json");
require_once "../config/db.php";

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Only POST method allowed"]);
    exit;
}

// Get input JSON
$data = json_decode(file_get_contents("php://input"), true);

// Validate input
if (!isset($data['email'])) {
    http_response_code(400);
    echo json_encode(["message" => "Email is required"]);
    exit;
}

$email = trim($data['email']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid email format"]);
    exit;
}

// Fetch user from database
$stmt = $conn->prepare("
    SELECT Id, Name, Email, Phone_Number, User_Type 
    FROM registration 
    WHERE Email = ?
");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["message" => "User not found"]);
    exit;
}

$user = $result->fetch_assoc();

// Success response
echo json_encode([
    "status" => "success",
    "profile" => [
        "id" => $user['Id'],
        "name" => $user['Name'],
        "email" => $user['Email'],
        "phone" => $user['Phone_Number'],
        "user_type" => $user['User_Type']
    ]
]);
