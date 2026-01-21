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
$name = trim($data['name'] ?? '');
$price = $data['price'] ?? null;

if (empty($name) || !is_numeric($price) || $price < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input data']);
    exit;
}

// Get bunk ID
$stmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? AND type = 'fuel'");
$stmt->execute([$user['id']]);
$shop = $stmt->fetch();

if (!$shop) {
    http_response_code(404);
    echo json_encode(['error' => 'Bunk not found']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO services (shop_id, name, price, type) VALUES (?, ?, ?, 'fuel')");
if (!$stmt->execute([$shop['id'], $name, $price])) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to add fuel price']);
    exit;
}

$serviceId = $pdo->lastInsertId();

echo json_encode([
    'message' => 'Fuel price added successfully',
    'service_id' => $serviceId
]);
?>
