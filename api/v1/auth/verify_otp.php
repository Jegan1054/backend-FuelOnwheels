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
$otp = $data['otp'] ?? '';

if (!validateEmail($email) || empty($otp)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input data']);
    exit;
}

// Check OTP
$stmt = $pdo->prepare("SELECT * FROM otp_verification WHERE email = ? AND otp = ? AND expires_at > NOW()");
$stmt->execute([$email, $otp]);
$otpRecord = $stmt->fetch();

if (!$otpRecord) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or expired OTP']);
    exit;
}

// Complete registration
if (!isset($_SESSION['pending_registration'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No pending registration found']);
    exit;
}

$regData = $_SESSION['pending_registration'];
if ($regData['email'] !== $email) {
    http_response_code(400);
    echo json_encode(['error' => 'Email mismatch']);
    exit;
}

// Insert user with profile information
$stmt = $pdo->prepare("INSERT INTO users (email, password, role, verified, first_name, last_name, phone) VALUES (?, ?, ?, 1, ?, ?, ?)");
if (!$stmt->execute([$regData['email'], $regData['password'], $regData['role'], $regData['first_name'], $regData['last_name'], $regData['phone']])) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create user']);
    exit;
}

$userId = $pdo->lastInsertId();

// Delete OTP
$pdo->prepare("DELETE FROM otp_verification WHERE email = ?")->execute([$email]);

// Clear session
unset($_SESSION['pending_registration']);

echo json_encode([
    'message' => 'Registration completed successfully',
    'user_id' => $userId,
    'user_role' => $regData['role'],
    'token' => $userId // Placeholder token
]);
?>
