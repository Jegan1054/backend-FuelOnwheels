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

if (!validateEmail($email)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email']);
    exit;
}

// Check if email exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND verified = 1");
$stmt->execute([$email]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Email not found']);
    exit;
}

// Generate OTP
$otp = generateOTP();
$expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Delete any existing OTP for this email
$pdo->prepare("DELETE FROM otp_verification WHERE email = ?")->execute([$email]);

// Insert OTP
$stmt = $pdo->prepare("INSERT INTO otp_verification (email, otp, expires_at) VALUES (?, ?,DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
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

$_SESSION['reset_email'] = $email;

echo json_encode(['message' => 'OTP sent to your email for password reset']);
?>
