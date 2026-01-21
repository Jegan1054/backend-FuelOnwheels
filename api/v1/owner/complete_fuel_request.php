<?php
require_once '../../config.php';

$user = requireAuth();

if ($user['role'] !== 'owner') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$requestId = $data['request_id'] ?? null;
$finalAmount = $data['final_amount'] ?? null;
$liters = $data['liters'] ?? null;

if (!$requestId || !is_numeric($finalAmount) || $finalAmount < 0 || !is_numeric($liters) || $liters <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input data']);
    exit;
}

// Get bunk ID for this owner
$stmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? AND type = 'fuel'");
$stmt->execute([$user['id']]);
$shop = $stmt->fetch();

if (!$shop) {
    http_response_code(404);
    echo json_encode(['error' => 'Bunk not found']);
    exit;
}

// Check if request belongs to this bunk and is accepted
$stmt = $pdo->prepare("SELECT * FROM service_requests WHERE id = ? AND shop_id = ? AND status = 'accepted'");
$stmt->execute([$requestId, $shop['id']]);
$request = $stmt->fetch();

if (!$request) {
    http_response_code(404);
    echo json_encode(['error' => 'Request not found or not accepted']);
    exit;
}

$stmt = $pdo->prepare("UPDATE service_requests SET status = 'completed', final_amount = ?, liters = ?, completed_at = NOW() WHERE id = ?");
if (!$stmt->execute([$finalAmount, $liters, $requestId])) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to complete request']);
    exit;
}

echo json_encode(['message' => 'Fuel request completed successfully']);
?>
