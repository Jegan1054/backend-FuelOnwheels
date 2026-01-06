<?php
header("Content-Type: application/json");

require_once "../config/db.php";

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Only POST method allowed"]);
    exit;
}

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Validate required fields
$user_id = $data['user_id'] ?? null;
$name    = trim($data['name'] ?? '');
$phone   = trim($data['phone'] ?? '');

if (!$user_id) {
    http_response_code(400);
    echo json_encode(["message" => "User ID is required"]);
    exit;
}

if ($name === '' || $phone === '') {
    http_response_code(400);
    echo json_encode(["message" => "Name and Phone are required"]);
    exit;
}

// Validate phone
if (!preg_match('/^[0-9]{10}$/', $phone)) {
    http_response_code(422);
    echo json_encode(["message" => "Phone number must be 10 digits"]);
    exit;
}

// Check if user exists
$check = $conn->prepare("SELECT Id FROM registration WHERE Id = ?");
$check->bind_param("i", $user_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["message" => "User not found"]);
    exit;
}

// Update profile
$update = $conn->prepare("
    UPDATE registration 
    SET Name = ?, Phone_Number = ?
    WHERE Id = ?
");
$update->bind_param("ssi", $name, $phone, $user_id);

if ($update->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Profile updated successfully"
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "message" => "Failed to update profile"
    ]);
}
