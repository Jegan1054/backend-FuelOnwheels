<?php
require_once '../../config.php';

$user = requireAuth();

if ($user['role'] !== 'mechanic') {
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
$action = $data['action'] ?? null; // 'accept' or 'reject'

if (!$requestId || !in_array($action, ['accept', 'reject'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input data']);
    exit;
}

// Get shop ID for this mechanic
$stmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? AND type = 'mechanic'");
$stmt->execute([$user['id']]);
$shop = $stmt->fetch();

if (!$shop) {
    http_response_code(404);
    echo json_encode(['error' => 'Shop not found']);
    exit;
}

// Check if request belongs to this shop and is pending
$stmt = $pdo->prepare("SELECT * FROM service_requests WHERE id = ? AND shop_id = ? AND status = 'pending'");
$stmt->execute([$requestId, $shop['id']]);
$request = $stmt->fetch();

if (!$request) {
    http_response_code(404);
    echo json_encode(['error' => 'Request not found or not pending']);
    exit;
}

$newStatus = $action === 'accept' ? 'accepted' : 'rejected';

$stmt = $pdo->prepare("UPDATE service_requests SET status = ? WHERE id = ?");
if (!$stmt->execute([$newStatus, $requestId])) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update request status']);
    exit;
}

echo json_encode(['message' => "Request {$action}ed successfully"]);
?>
