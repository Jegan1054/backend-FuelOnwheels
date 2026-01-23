<?php
session_start();
require_once '../../config.php';

header('Content-Type: application/json');

// ---------------------
// Allow only POST
// ---------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ---------------------
// Read JSON
// ---------------------
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON format']);
    exit;
}

// ---------------------
// Get inputs
// ---------------------
$email      = trim($data['email'] ?? '');
$password   = $data['password'] ?? '';
$role       = $data['role'] ?? '';
$firstName  = trim($data['first_name'] ?? '');
$lastName   = trim($data['last_name'] ?? '');
$phone      = trim($data['phone'] ?? '');

// ---------------------
// Email validation
// ---------------------
if (!validateEmail($email)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email address']);
    exit;
}

// ---------------------
// Password validation
// ---------------------
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 8 characters']);
    exit;
}

// ---------------------
// Role validation
// ---------------------
if (!in_array($role, ['user', 'mechanic', 'owner'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid role']);
    exit;
}

// ---------------------
// Name validation
// ---------------------
if (empty($firstName) || empty($lastName)) {
    http_response_code(400);
    echo json_encode(['error' => 'First name and last name are required']);
    exit;
}

// ---------------------
// Phone number validation (India)
// ---------------------
if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid phone number. Enter valid 10-digit Indian mobile number'
    ]);
    exit;
}

// ---------------------
// Check if email already exists
// ---------------------
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);

if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Email already registered']);
    exit;
}

// ---------------------
// Generate OTP
// ---------------------
$otp = generateOTP();

// Delete old OTP
$pdo->prepare("DELETE FROM otp_verification WHERE email = ?")
    ->execute([$email]);

// Insert OTP
$stmt = $pdo->prepare("
    INSERT INTO otp_verification (email, otp, expires_at)
    VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
");

if (!$stmt->execute([$email, $otp])) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate OTP']);
    exit;
}

// ---------------------
// Send OTP
// ---------------------
if (!sendOTP($email, $otp)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send OTP email']);
    exit;
}

// ---------------------
// Store pending registration
// ---------------------
$_SESSION['pending_registration'] = [
    'email'      => $email,
    'password'   => hashPassword($password),
    'role'       => $role,
    'first_name' => $firstName,
    'last_name'  => $lastName,
    'phone'      => $phone
];

// ---------------------
// Success
// ---------------------
echo json_encode([
    'message' => 'OTP sent to your email for verification'
]);
