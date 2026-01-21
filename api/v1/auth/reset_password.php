<?php
session_start();
require_once '../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$otp = $data['otp'] ?? '';
$newPassword = $data['new_password'] ?? '';

if (empty($otp) || empty($newPassword)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input data']);
    exit;
}

if (!isset($_SESSION['reset_email'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No reset request found']);
    exit;
}

$email = $_SESSION['reset_email'];

// Check OTP
$stmt = $pdo->prepare("SELECT * FROM otp_verification WHERE email = ? AND otp = ? AND expires_at > NOW()");
$stmt->execute([$email, $otp]);
$otpRecord = $stmt->fetch();

if (!$otpRecord) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or expired OTP']);
    exit;
}

// Update password
$hashedPassword = hashPassword($newPassword);
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
if (!$stmt->execute([$hashedPassword, $email])) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update password']);
    exit;
}

// Delete OTP
$pdo->prepare("DELETE FROM otp_verification WHERE email = ?")->execute([$email]);

// Clear session
unset($_SESSION['reset_email']);

echo json_encode(['message' => 'Password reset successfully']);
?>
