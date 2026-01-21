<?php
require_once '../../config.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get user profile
$stmt = $pdo->prepare("SELECT id, email, role, first_name, last_name, phone, profile_image, created_at FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$userProfile = $stmt->fetch();

if (!$userProfile) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$profile = [
    'user' => [
        'id' => $userProfile['id'],
        'email' => $userProfile['email'],
        'role' => $userProfile['role'],
        'first_name' => $userProfile['first_name'],
        'last_name' => $userProfile['last_name'],
        'phone' => $userProfile['phone'],
        'profile_image' => $userProfile['profile_image'],
        'created_at' => $userProfile['created_at']
    ]
];

// Add shop information if user is mechanic or owner
if ($user['role'] === 'mechanic' || $user['role'] === 'owner') {
    $shopType = $user['role'] === 'mechanic' ? 'mechanic' : 'fuel';
    $stmt = $pdo->prepare("
        SELECT s.*, COUNT(sr.id) as total_orders,
               AVG(r.rating) as avg_rating
        FROM shops s
        LEFT JOIN service_requests sr ON s.id = sr.shop_id
        LEFT JOIN ratings r ON r.to_user_id = s.user_id
        WHERE s.user_id = ? AND s.type = ?
        GROUP BY s.id
    ");
    $stmt->execute([$user['id'], $shopType]);
    $shop = $stmt->fetch();

    if ($shop) {
        $profile['shop'] = [
            'id' => $shop['id'],
            'name' => $shop['name'],
            'type' => $shop['type'],
            'description' => $shop['description'],
            'address' => $shop['address'],
            'phone' => $shop['phone'],
            'latitude' => $shop['latitude'],
            'longitude' => $shop['longitude'],
            'radius' => $shop['radius'],
            'shop_image' => $shop['shop_image'],
            'total_orders' => $shop['total_orders'] ?: 0,
            'avg_rating' => round($shop['avg_rating'] ?: 0, 1),
            'created_at' => $shop['created_at']
        ];
    }
}

echo json_encode($profile);
?>
