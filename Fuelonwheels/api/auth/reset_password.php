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

$email           = trim($data['email'] ?? '');
$newPassword     = $data['new_password'] ?? '';
$confirmPassword = $data['confirm_password'] ?? '';

// --------------------
// Validation
// --------------------
if ($email === '' || $newPassword === '' || $confirmPassword === '') {
    http_response_code(400);
    echo json_encode(["message" => "All fields are required"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(["message" => "Invalid email format"]);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(422);
    echo json_encode(["message" => "Password must be at least 6 characters"]);
    exit;
}

if ($newPassword !== $confirmPassword) {
    http_response_code(422);
    echo json_encode(["message" => "Passwords do not match"]);
    exit;
}

// --------------------
// Check Email + OTP Verified
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

if ($user['otp'] === NULL) {
    http_response_code(401);
    echo json_encode(["message" => "OTP not verified"]);
    exit;
}

// --------------------
// UPDATE PASSWORD
// --------------------
// ⚠️ Plain password (as per your current system)
// RECOMMENDED later: password_hash()

$update = $conn->prepare("
    UPDATE registration 
    SET Password = ?, otp = NULL, otp_expiry = NULL 
    WHERE Email = ?
");
$update->bind_param("ss", $newPassword, $email);

if ($update->execute()) {
    http_response_code(200);
    echo json_encode(["message" => "Password reset successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Password reset failed"]);
}
