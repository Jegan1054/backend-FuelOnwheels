<?php
/**
 * User - Track Fuel Bunk (Owner) Live Location
 * GET /api/v1/user/track_owner_location.php?request_id=1
 */

require_once '../../config.php';

/* ---------------- AUTH ---------------- */
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

/* ---------------- INPUT ---------------- */
$requestId = $_GET['request_id'] ?? null;

if (!$requestId) {
    http_response_code(400);
    echo json_encode(['error' => 'Request ID is required']);
    exit;
}

/* ---------------- FETCH REQUEST + OWNER ---------------- */
$stmt = $pdo->prepare("
    SELECT 
        sr.id,
        sr.shop_id,
        sr.liters,
        sr.status,
        s.name AS service_name,
        sh.name AS shop_name,
        sh.latitude AS shop_latitude,
        sh.longitude AS shop_longitude,
        u.id AS owner_id,
        u.first_name,
        u.last_name,
        u.phone
    FROM service_requests sr
    INNER JOIN services s ON sr.service_id = s.id
    INNER JOIN shops sh ON sr.shop_id = sh.id
    INNER JOIN users u ON sh.user_id = u.id
    WHERE sr.id = ?
      AND sr.user_id = ?
      AND sr.status IN ('accepted', 'pending', 'completed')
      AND s.type = 'fuel'
    LIMIT 1
");
$stmt->execute([$requestId, $user['id']]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    http_response_code(404);
    echo json_encode(['error' => 'Fuel request not found']);
    exit;
}

/* ---------------- OWNER LOCATION ---------------- */
/* Owner live location is stored in locations table */
$stmt = $pdo->prepare("
    SELECT latitude, longitude, updated_at
    FROM locations
    WHERE user_id = ?
    ORDER BY updated_at DESC
    LIMIT 1
");
$stmt->execute([$request['owner_id']]);
$ownerLocation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ownerLocation) {
    http_response_code(404);
    echo json_encode(['error' => 'Owner location not available']);
    exit;
}

/* ---------------- DISTANCE ---------------- */
$distance = calculateDistance(
    (float)$request['shop_latitude'],
    (float)$request['shop_longitude'],
    (float)$ownerLocation['latitude'],
    (float)$ownerLocation['longitude']
);

/* ---------------- RESPONSE ---------------- */
echo json_encode([
    'success' => true,
    'request_id' => (int)$requestId,
    'fuel_request' => [
        'fuel_type' => $request['service_name'],
        'liters' => (float)$request['liters'],
        'status' => $request['status']
    ],
    'fuel_bunk' => [
        'name' => $request['shop_name'],
        'owner_name' => trim($request['first_name'] . ' ' . $request['last_name']),
        'phone' => $request['phone']
    ],
    'owner_location' => [
        'latitude' => (float)$ownerLocation['latitude'],
        'longitude' => (float)$ownerLocation['longitude'],
        'last_updated' => $ownerLocation['updated_at']
    ],
    'distance_from_bunk' => round($distance, 2),
    'unit' => 'km'
]);

exit;

/* ---------------- HELPERS ---------------- */
function calculateDistance($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371;

    $latDelta = deg2rad($lat2 - $lat1);
    $lonDelta = deg2rad($lon2 - $lon1);

    $a = sin($latDelta / 2) * sin($latDelta / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lonDelta / 2) * sin($lonDelta / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}
?>
