<?php
require_once '../../config.php';

$user = requireAuth();

if ($user['role'] !== 'owner') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
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

$shopId = $shop['id'];

// Get stats
$stats = [];

// Pending orders
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM service_requests WHERE shop_id = ? AND status = 'pending'");
$stmt->execute([$shopId]);
$stats['pending_orders'] = $stmt->fetch()['count'];

// Total delivered
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM service_requests WHERE shop_id = ? AND status = 'completed'");
$stmt->execute([$shopId]);
$stats['total_delivered'] = $stmt->fetch()['count'];

// Total earnings
$stmt = $pdo->prepare("SELECT SUM(final_amount) as total FROM service_requests WHERE shop_id = ? AND status = 'completed'");
$stmt->execute([$shopId]);
$stats['total_earnings'] = $stmt->fetch()['total'] ?? 0;

// Total orders
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM service_requests WHERE shop_id = ?");
$stmt->execute([$shopId]);
$stats['total_orders'] = $stmt->fetch()['count'];

// Rating
$stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating FROM ratings WHERE to_user_id = ?");
$stmt->execute([$user['id']]);
$stats['rating'] = round($stmt->fetch()['avg_rating'] ?? 0, 1);

// Total liters delivered
$stmt = $pdo->prepare("SELECT SUM(liters) as total_liters FROM service_requests WHERE shop_id = ? AND status = 'completed'");
$stmt->execute([$shopId]);
$stats['total_liters_delivered'] = $stmt->fetch()['total_liters'] ?? 0;

echo json_encode(['dashboard' => $stats]);
?>
