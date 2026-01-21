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

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;
$stmt = $pdo->prepare("
    SELECT sr.*, s.name as shop_name, s.type as shop_type, sv.name as service_name, sv.price as service_price,
           p.amount as payment_amount, p.method as payment_method, p.status as payment_status,
           r.rating, r.review
    FROM service_requests sr
    LEFT JOIN shops s ON sr.shop_id = s.id
    LEFT JOIN services sv ON sr.service_id = sv.id
    LEFT JOIN payments p ON sr.id = p.request_id
    LEFT JOIN ratings r ON r.from_user_id = sr.user_id AND r.to_user_id = s.user_id
    WHERE sr.user_id = :user_id
    ORDER BY sr.requested_at DESC
    LIMIT :limit OFFSET :offset
");

$stmt->bindValue(':user_id', $user['id'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();


$orders = $stmt->fetchAll();
$formattedOrders = [];

foreach ($orders as $order) {
    $formattedOrders[] = [
        'id' => $order['id'],
        'shop_name' => $order['shop_name'],
        'shop_type' => $order['shop_type'],
        'service_name' => $order['service_name'],
        'service_price' => $order['service_price'],
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
}

// Get total count for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM service_requests WHERE user_id = ?");
$stmt->execute([$user['id']]);
$total = $stmt->fetch()['total'];

echo json_encode([
    'orders' => $formattedOrders,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => ceil($total / $limit)
    ]
]);
?>
