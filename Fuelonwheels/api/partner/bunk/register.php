<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$database = "fuelonwheels";

$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed"]);
    exit;
}

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid JSON data"]);
    exit;
}

// Fetch values
$name       = trim($data['name'] ?? '');
$email      = trim($data['email'] ?? '');
$password   = $data['password'] ?? '';
$phone      = trim($data['phone_number'] ?? '');
$userType   = trim($data['user_type'] ?? '');

// ---------------- VALIDATION ----------------
if ($name == "" || $email == "" || $password == "" || $phone == "" || $userType == "") {
    http_response_code(400);
    echo json_encode(["message" => "All fields are required"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid email"]);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(["message" => "Password must be at least 6 characters"]);
    exit;
}

if (!preg_match("/^[0-9]{10}$/", $phone)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid phone number"]);
    exit;
}

$allowed = ["Bunk", "Mechanic", "User"];
if (!in_array($userType, $allowed)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid user type"]);
    exit;
}

// Check existing user
$check = $conn->prepare("SELECT id FROM registration WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["message" => "Email already exists"]);
    exit;
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// Insert user
$stmt = $conn->prepare("
    INSERT INTO registration (name, email, password, phone_number, user_type)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "sssss",
    $name,
    $email,
    $hashedPassword,
    $phone,
    $userType
);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Registration successful"
    ]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Registration failed"]);
}
















