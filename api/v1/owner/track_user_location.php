<?php
/**
 * Fuel Bunk Owner - Track User Live Location for Fuel Delivery
 * GET /api/v1/owner/track-user-location.php?request_id=1
 */

require_once '../../config.php';

/* ---------------- AUTH CHECK ---------------- */
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

/* ---------------- INPUT ---------------- */
$requestId = $_GET['request_id'] ?? null;

if (empty($requestId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Request ID is required']);
    exit;
}

/* ---------------- GET OWNER FUEL BUNK ---------------- */
$stmt = $pdo->prepare("
    SELECT id, latitude, longitude
    FROM shops
    WHERE user_id = ? AND type = 'fuel'
    LIMIT 1
");
$stmt->execute([$user['id']]);
$shop = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$shop) {
    http_response_code(404);
    echo json_encode(['error' => 'Fuel bunk not found']);
    exit;
}

/* ---------------- VERIFY SERVICE REQUEST ---------------- */
$stmt = $pdo->prepare("
    SELECT
        sr.id,
        sr.user_id,
        sr.status,
        sr.liters,
        s.name AS service_name,
        u.first_name,
        u.last_name,
        u.phone
    FROM service_requests sr
    INNER JOIN users u ON sr.user_id = u.id
    LEFT JOIN services s ON sr.service_id = s.id
    WHERE sr.id = ?
      AND sr.shop_id = ?
      AND sr.status IN ('accepted', 'pending')
    LIMIT 1
");
$stmt->execute([$requestId, $shop['id']]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    http_response_code(404);
    echo json_encode(['error' => 'Fuel request not found or not active']);
    exit;
}

/* ---------------- GET USER LIVE LOCATION ---------------- */
$stmt = $pdo->prepare("
    SELECT latitude, longitude, updated_at
    FROM locations
    WHERE user_id = ?
    ORDER BY updated_at DESC
    LIMIT 1
");
$stmt->execute([$request['user_id']]);
$location = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$location) {
    http_response_code(404);
    echo json_encode(['error' => 'User location not available']);
    exit;
}

/* ---------------- DISTANCE CALCULATION ---------------- */
$shopLat = (float)$shop['latitude'];
$shopLng = (float)$shop['longitude'];
$userLat = (float)$location['latitude'];
$userLng = (float)$location['longitude'];

$distanceKm = calculateDistance($shopLat, $shopLng, $userLat, $userLng);

/* Average delivery speed: 30 km/h */
$estimatedTimeMin = $distanceKm > 0 ? round(($distanceKm / 30) * 60) : 0;

/* ---------------- RESPONSE ---------------- */
echo json_encode([
    'success' => true,
    'request_id' => (int)$requestId,

    'user' => [
        'id' => (int)$request['user_id'],
        'name' => trim($request['first_name'] . ' ' . $request['last_name']),
        'phone' => $request['phone']
    ],

    'fuel_request' => [
        'fuel_type' => $request['service_name'] ?? 'Fuel Delivery',
        'quantity' => (float)($request['liters'] ?? 0),
        'status' => $request['status']
    ],

    'current_location' => [
        'latitude' => $userLat,
        'longitude' => $userLng,
        'last_updated' => $location['updated_at']
    ],

    'distance_from_bunk' => round($distanceKm, 2),
    'estimated_delivery_time' => $estimatedTimeMin,
    'unit' => 'km',
    'time_unit' => 'minutes'
]);

exit;

/* ---------------- HELPERS ---------------- */

/**
 * Calculate distance using Haversine formula
 */
function calculateDistance($lat1, $lng1, $lat2, $lng2)
{
    $earthRadius = 6371;

    $latDelta = deg2rad($lat2 - $lat1);
    $lngDelta = deg2rad($lng2 - $lng1);

    $a = sin($latDelta / 2) ** 2 +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lngDelta / 2) ** 2;

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}
