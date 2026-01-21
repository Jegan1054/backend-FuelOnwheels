<?php
require_once '../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (!validateEmail($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input data']);
    exit;
}

// Check user
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND verified = 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !verifyPassword($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
    exit;
}

echo json_encode([
    'message' => 'Login successful',
    'user' => [
        'id' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role']
    ],
    'token' => $user['id'] // Placeholder token
]);
?>
