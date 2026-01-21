<?php
require_once '../../config.php';

$user = requireAuth();

if ($user['role'] !== 'user') {
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
$orderId = $data['order_id'] ?? null;
$paymentMethod = $data['payment_method'] ?? null; // 'online' or 'cod'

if (!$orderId || !in_array($paymentMethod, ['online', 'cod'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input data']);
    exit;
}

// Check if order exists and belongs to user
$stmt = $pdo->prepare("SELECT * FROM service_requests WHERE id = ? AND user_id = ? AND status = 'completed'");
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found or not completed']);
    exit;
}

// Check if payment already exists
$stmt = $pdo->prepare("SELECT id FROM payments WHERE request_id = ?");
$stmt->execute([$orderId]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Payment already processed']);
    exit;
}

// For online payment, simulate gateway processing
$paymentStatus = 'paid';

$stmt = $pdo->prepare("INSERT INTO payments (request_id, amount, method, status) VALUES (?, ?, ?, ?)");
if (!$stmt->execute([$orderId, $order['final_amount'], $paymentMethod, $paymentStatus])) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to process payment']);
    exit;
}

$paymentId = $pdo->lastInsertId();

echo json_encode([
    'message' => 'Payment processed successfully',
    'payment_id' => $paymentId,
    'status' => $paymentStatus,
    'method' => $paymentMethod,
    'amount' => $order['final_amount']
]);
?>
