<?php
require_once '../../config.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$serviceType = $_GET['service_type'] ?? null; // 'fuel' or 'repair'
$radius = $_GET['radius'] ?? 10; // default 10km

if (!in_array($serviceType, ['fuel', 'repair'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid service type']);
    exit;
}

// Get user's location
$stmt = $pdo->prepare("SELECT latitude, longitude FROM locations WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1");
$stmt->execute([$user['id']]);
$userLocation = $stmt->fetch();

if (!$userLocation) {
    http_response_code(400);
    echo json_encode(['error' => 'User location not found']);
    exit;
}

$userLat = $userLocation['latitude'];
$userLng = $userLocation['longitude'];

if ($serviceType === 'fuel') {
    $query = "
        SELECT s.id, s.name, s.latitude, s.longitude, s.radius,
               GROUP_CONCAT(CONCAT(sv.name, ': ', sv.price) SEPARATOR '; ') as services,
               AVG(r.rating) as avg_rating
        FROM shops s
        LEFT JOIN services sv ON s.id = sv.shop_id AND sv.type = 'fuel'
        LEFT JOIN service_requests sr ON s.id = sr.shop_id
        LEFT JOIN ratings r ON r.to_user_id = s.user_id
        WHERE s.type = 'fuel'
        GROUP BY s.id
        HAVING (6371 * acos(cos(radians(?)) * cos(radians(s.latitude)) * cos(radians(s.longitude) - radians(?)) + sin(radians(?)) * sin(radians(s.latitude)))) <= ?
    ";
} else {
    $query = "
        SELECT s.id, s.name, s.latitude, s.longitude, s.radius,
               GROUP_CONCAT(CONCAT(sv.name, ': ', sv.price) SEPARATOR '; ') as services,
               AVG(r.rating) as avg_rating
        FROM shops s
        LEFT JOIN services sv ON s.id = sv.shop_id AND sv.type = 'repair'
        LEFT JOIN service_requests sr ON s.id = sr.shop_id
        LEFT JOIN ratings r ON r.to_user_id = s.user_id
        WHERE s.type = 'mechanic'
        GROUP BY s.id
        HAVING (6371 * acos(cos(radians(?)) * cos(radians(s.latitude)) * cos(radians(s.longitude) - radians(?)) + sin(radians(?)) * sin(radians(s.latitude)))) <= ?
    ";
}

$stmt = $pdo->prepare($query);
$stmt->execute([$userLat, $userLng, $userLat, $radius]);
$shops = $stmt->fetchAll();

$shopsWithDistance = [];
foreach ($shops as $shop) {
    $distance = haversineDistance($userLat, $userLng, $shop['latitude'], $shop['longitude']);
    $shop['distance'] = round($distance, 2);
    $shop['services'] = explode('; ', $shop['services']);
    $shop['avg_rating'] = round($shop['avg_rating'], 1) ?? 0;
    $shopsWithDistance[] = $shop;
}

echo json_encode(['shops' => $shopsWithDistance, 'user_id' => $user['id'], 'user_location' => $userLocation, 'service_type' => $serviceType]);
?>
