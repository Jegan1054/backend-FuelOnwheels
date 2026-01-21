<?php
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

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

if (!$orderId) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID is required']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT sr.*, s.name as shop_name, s.type as shop_type, s.latitude, s.longitude,
           sv.name as service_name, sv.price as service_price,
           p.amount as payment_amount, p.method as payment_method, p.status as payment_status,
           r.rating, r.review
    FROM service_requests sr
    LEFT JOIN shops s ON sr.shop_id = s.id
    LEFT JOIN services sv ON sr.service_id = sv.id
    LEFT JOIN payments p ON sr.id = p.request_id
    LEFT JOIN ratings r ON r.from_user_id = sr.user_id AND r.to_user_id = s.user_id
    WHERE sr.id = ? AND sr.user_id = ?
");
$stmt->execute([$orderId, $user['id']]);

$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
}

$orderDetails = [
    'id' => $order['id'],
    'shop' => [
        'id' => $order['shop_id'],
        'name' => $order['shop_name'],
        'type' => $order['shop_type'],
        'location' => [
            'latitude' => $order['latitude'],
            'longitude' => $order['longitude']
        ]
    ],
    'service' => $order['service_name'] ? [
        'name' => $order['service_name'],
        'price' => $order['service_price']
    ] : null,
    'description' => $order['description'],
    'status' => $order['status'],
    'final_amount' => $order['final_amount'],
    'liters' => $order['liters'],
    'requested_at' => $order['requested_at'],
    'completed_at' => $order['completed_at'],
    'payment' => $order['payment_amount'] ? [
        'amount' => $order['payment_amount'],
        'method' => $order['payment_method'],
        'status' => $order['payment_status']
    ] : null,
    'rating' => $order['rating'] ? [
        'stars' => $order['rating'],
        'review' => $order['review']
    ] : null
];

echo json_encode(['order' => $orderDetails]);
?>
