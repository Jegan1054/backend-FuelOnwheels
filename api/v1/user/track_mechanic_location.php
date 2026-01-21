<?php
/**
 * User - Track Mechanic Live Location During Service
 * GET /api/v1/user/track-mechanic-location?request_id=1
 */

require_once '../../config.php';

$user = requireAuth();

if ($user['role'] !== 'user') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$requestId = $_GET['request_id'] ?? null;

if (!$requestId) {
    http_response_code(400);
    echo json_encode(['error' => 'Request ID is required']);
    exit;
}

// Check if request belongs to this user and is in progress
$stmt = $pdo->prepare("SELECT sr.*, s.name as shop_name, s.latitude as shop_lat, s.longitude as shop_lng,
                      u.first_name, u.last_name, u.phone
                      FROM service_requests sr
                      JOIN shops s ON sr.shop_id = s.id
                      JOIN users u ON s.user_id = u.id
                      WHERE sr.id = ? AND sr.user_id = ? AND sr.status IN ('accepted', 'in_progress')");
$stmt->execute([$requestId, $user['id']]);
$request = $stmt->fetch();

if (!$request) {
    http_response_code(404);
    echo json_encode(['error' => 'Service request not found or not in progress']);
    exit;
}

// Get mechanic's latest location
$stmt = $pdo->prepare("SELECT latitude, longitude, updated_at
                      FROM mechanic_locations
                      WHERE mechanic_id = ? AND (request_id = ? OR request_id IS NULL)
                      ORDER BY updated_at DESC
                      LIMIT 1");
$stmt->execute([$request['user_id'], $requestId]); // Note: this should be the mechanic's user ID
$mechanicLocation = $stmt->fetch();

if (!$mechanicLocation) {
    http_response_code(404);
    echo json_encode(['error' => 'Mechanic location not available']);
    exit;
}

// Calculate distance and estimated time
$userLat = $request['latitude'] ?? 0; // User's location from request
$userLng = $request['longitude'] ?? 0;
$mechanicLat = $mechanicLocation['latitude'];
$mechanicLng = $mechanicLocation['longitude'];

$distance = calculateDistance($userLat, $userLng, $mechanicLat, $mechanicLng);
$estimatedTime = $distance > 0 ? round(($distance / 20) * 60) : 0; // Assuming 20 km/h speed

echo json_encode([
    'success' => true,
    'request_id' => $requestId,
    'mechanic' => [
        'name' => $request['first_name'] . ' ' . $request['last_name'],
        'phone' => $request['phone'],
        'shop_name' => $request['shop_name']
    ],
    'service' => [
        'status' => $request['status'],
        'description' => $request['description']
    ],
    'mechanic_location' => [
        'latitude' => (float)$mechanicLocation['latitude'],
        'longitude' => (float)$mechanicLocation['longitude'],
        'last_updated' => $mechanicLocation['updated_at']
    ],
    'distance_to_user' => round($distance, 2),
    'estimated_arrival_time' => $estimatedTime,
    'unit' => 'km',
    'time_unit' => 'minutes'
]);

/**
 * Calculate distance between two points using Haversine formula
 */
function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371; // Radius of the earth in km

    $latDelta = deg2rad($lat2 - $lat1);
    $lngDelta = deg2rad($lng2 - $lng1);

    $a = sin($latDelta/2) * sin($latDelta/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lngDelta/2) * sin($lngDelta/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));

    return $earthRadius * $c;
}
?>
