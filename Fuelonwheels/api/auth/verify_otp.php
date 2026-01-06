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

$email = trim($data['email'] ?? '');
$otp   = trim($data['otp'] ?? '');

// --------------------
// Validation
// --------------------
if ($email === '' || $otp === '') {
    http_response_code(400);
    echo json_encode(["message" => "Email and OTP are required"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(["message" => "Invalid email format"]);
    exit;
}

if (!preg_match('/^[0-9]{6}$/', $otp)) {
    http_response_code(422);
    echo json_encode(["message" => "OTP must be 6 digits"]);
    exit;
}

// --------------------
// Fetch OTP from DB
// --------------------
$stmt = $conn->prepare("
    SELECT otp, otp_expiry 
    FROM registration 
    WHERE Email = ?
");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["message" => "Email not registered"]);
    exit;
}

$user = $result->fetch_assoc();

// --------------------
// OTP Validation
// --------------------
if ($user['otp'] === NULL) {
    http_response_code(401);
    echo json_encode(["message" => "OTP not requested"]);
    exit;
}

if ($user['otp'] !== $otp) {
    http_response_code(401);
    echo json_encode(["message" => "Invalid OTP"]);
    exit;
}

if (strtotime($user['otp_expiry']) < time()) {
    http_response_code(401);
    echo json_encode(["message" => "OTP expired"]);
    exit;
}

// --------------------
// OTP VERIFIED
// --------------------
http_response_code(200);
echo json_encode(["message" => "OTP verified successfully"]);
