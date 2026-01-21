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
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;
$radius = $data['radius'] ?? null;

if (empty($name) || !is_numeric($latitude) || !is_numeric($longitude) || !is_numeric($radius)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input data']);
    exit;
}

// Check if user already has a bunk
$stmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ?");
$stmt->execute([$user['id']]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Bunk already registered']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO shops (user_id, name, type, latitude, longitude, radius) VALUES (?, ?, 'fuel', ?, ?, ?)");
if (!$stmt->execute([$user['id'], $name, $latitude, $longitude, $radius])) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to register bunk']);
    exit;
}

$bunkId = $pdo->lastInsertId();

echo json_encode([
    'message' => 'Fuel bunk registered successfully',
    'bunk_id' => $bunkId
]);
?>
