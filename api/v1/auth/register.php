<?php
session_start();
require_once '../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$role = $data['role'] ?? '';
$firstName = trim($data['first_name'] ?? '');
$lastName = trim($data['last_name'] ?? '');
$phone = trim($data['phone'] ?? '');

if (!validateEmail($email) || empty($password) || !in_array($role, ['user', 'mechanic', 'owner'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input data']);
    exit;
}

// Check if email already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Email already registered']);
    exit;
}

// Generate OTP
$otp = generateOTP();
$expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Delete any existing OTP for this email
$pdo->prepare("DELETE FROM otp_verification WHERE email = ?")->execute([$email]);

// Insert OTP
$stmt = $pdo->prepare("INSERT INTO otp_verification (email, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
if (!$stmt->execute([$email, $otp])) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate OTP']);
    exit;
}

// Send OTP via email
if (!sendOTP($email, $otp)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send OTP email']);
    exit;
}

// Store registration data temporarily (in production, use session or cache)
$_SESSION['pending_registration'] = [
    'email' => $email,
    'password' => hashPassword($password),
    'role' => $role,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'phone' => $phone
];

echo json_encode(['message' => 'OTP sent to your email for verification']);
?>
