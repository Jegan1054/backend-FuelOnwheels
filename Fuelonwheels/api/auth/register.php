<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../config/db.php";

// Read JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid JSON input"]);
    exit;
}

$name       = trim($input['name'] ?? '');
$email      = trim($input['email'] ?? '');
$password   = $input['password'] ?? '';
$phone      = trim($input['phone_number'] ?? '');
$userType   = trim($input['user_type'] ?? '');

// VALIDATIONS
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

if (!preg_match('/^[0-9]{10}$/', $phone)) {
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

// CHECK EMAIL
$check = $conn->prepare("SELECT id FROM registration WHERE Email=?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["message" => "Email already exists"]);
    exit;
}

// INSERT USER
$hashed = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare(
    "INSERT INTO registration (Name, Email, Password, Phone_Number, User_Type)
     VALUES (?, ?, ?, ?, ?)"
);
$stmt->bind_param("sssss", $name, $email, $hashed, $phone, $userType);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Registration successful"
    ]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Database error"]);
}
