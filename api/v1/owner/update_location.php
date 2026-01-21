<?php
/**
 * Fuel Bunk Owner - Update Live Location During Fuel Delivery
 * POST /api/v1/owner/update-location
 */

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
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;
$requestId = $data['request_id'] ?? null; // Optional: link to specific fuel request

if (!is_numeric($latitude) || !is_numeric($longitude) ||
    $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid coordinates']);
    exit;
}

// Verify owner has an active fuel bunk
$stmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? AND type = 'fuel'");
$stmt->execute([$user['id']]);
$shop = $stmt->fetch();

if (!$shop) {
    http_response_code(404);
    echo json_encode(['error' => 'Fuel bunk not found']);
    exit;
}

// Update owner's location (we'll use the mechanic_locations table for simplicity)
$stmt = $pdo->prepare("INSERT INTO mechanic_locations (mechanic_id, latitude, longitude, request_id, created_at)
                      VALUES (?, ?, ?, ?, NOW())
                      ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), updated_at = NOW()");
$result = $stmt->execute([$user['id'], $latitude, $longitude, $requestId]);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update location']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Location updated successfully',
    'location' => [
        'latitude' => (float)$latitude,
        'longitude' => (float)$longitude,
        'timestamp' => date('Y-m-d H:i:s')
    ]
]);
?>
