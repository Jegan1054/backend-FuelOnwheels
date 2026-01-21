<?php
require_once '../../config.php';

/*
|--------------------------------------------------------------------------
| Authenticate user
|--------------------------------------------------------------------------
*/
$user = requireAuth();

if ($user['role'] !== 'user') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

/*
|--------------------------------------------------------------------------
| Method check
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

/*
|--------------------------------------------------------------------------
| Read JSON body
|--------------------------------------------------------------------------
*/
$data = json_decode(file_get_contents('php://input'), true);

$shopId      = $data['shop_id'] ?? null;
$serviceId   = $data['service_id'] ?? null;
$description = trim($data['description'] ?? '');

/*
|--------------------------------------------------------------------------
| Validate input
|--------------------------------------------------------------------------
*/
if (!$shopId) {
    http_response_code(400);
    echo json_encode(['error' => 'Shop ID is required']);
    exit;
}

/*
|--------------------------------------------------------------------------
| Check shop exists
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    "SELECT id, type FROM shops WHERE id = ?"
);
$stmt->execute([$shopId]);
$shop = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$shop) {
    http_response_code(404);
    echo json_encode(['error' => 'Shop not found']);
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate service (only if provided)
|--------------------------------------------------------------------------
*/
if ($serviceId !== null) {
    $stmt = $pdo->prepare(
        "SELECT id FROM services WHERE id = ? AND shop_id = ?"
    );
    $stmt->execute([$serviceId, $shopId]);

    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid service for this shop']);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Create service request
|--------------------------------------------------------------------------
| NOTE:
| service_requests table DOES NOT have `type`
| type is derived from shops/services when needed
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    "INSERT INTO service_requests
        (user_id, shop_id, service_id, description)
     VALUES (?, ?, ?, ?)"
);

$stmt->execute([
    $user['id'],
    $shopId,
    $serviceId,
    $description !== '' ? $description : null
]);

/*
|--------------------------------------------------------------------------
| Success response
|--------------------------------------------------------------------------
*/
echo json_encode([
    'message'    => 'Service request created successfully',
    'request_id' => $pdo->lastInsertId()
]);
