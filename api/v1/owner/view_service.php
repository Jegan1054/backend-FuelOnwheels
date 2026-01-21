<?php
require_once '../../config.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}


$stmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? ");
$stmt->execute([$user['id']]);
$shop = $stmt->fetch();

if (!$shop) {
    http_response_code(404);
    echo json_encode(['error' => 'Bunk not found']);
    exit;
}

$stmt = $pdo->prepare("SELECT id,name ,price FROM services WHERE shop_id = ?");
$stmt->execute([$shop['id']]);

$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'message' => 'Services retrieved successfully',
    'services' => $services
]);
?>
