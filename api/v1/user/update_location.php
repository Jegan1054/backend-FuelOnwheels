<?php
require_once '../../config.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;

if (!is_numeric($latitude) || !is_numeric($longitude) ||
    $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid coordinates']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO locations (user_id, latitude, longitude) VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), updated_at = NOW()");
if (!$stmt->execute([$user['id'], $latitude, $longitude])) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update location']);
    exit;
}

echo json_encode(['message' => 'Location updated successfully']);
?>
