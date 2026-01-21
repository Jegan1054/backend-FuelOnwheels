<?php
/**
 * Fuel Bunk Owner - View Service Requests
 * GET /api/v1/owner/view_requests.php
 */

require_once '../../config.php';

header('Content-Type: application/json');

$user = requireAuth();

/**
 * Role check
 */
if ($user['role'] !== 'owner') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

/**
 * Method check
 */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

/**
 * Get fuel shop for owner
 */
$stmt = $pdo->prepare(
    "SELECT id FROM shops WHERE user_id = ? AND type = 'fuel' LIMIT 1"
);
$stmt->execute([$user['id']]);
$shop = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$shop) {
    http_response_code(404);
    echo json_encode(['error' => 'Fuel bunk not found']);
    exit;
}

$shopId = (int)$shop['id'];

/**
 * Pagination & filters
 */
$status = $_GET['status'] ?? null;
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = max(1, (int)($_GET['limit'] ?? 20));
$offset = ($page - 1) * $limit;

/**
 * Fetch requests
 */
$sql = "
    SELECT
        sr.id,
        sr.user_id,
        sr.status,
        sr.description,
        sr.liters,
        sr.final_amount,
        sr.requested_at,
        sr.completed_at,
        CONCAT(
            COALESCE(u.first_name, ''),
            ' ',
            COALESCE(u.last_name, '')
        ) AS user_name,
        u.phone AS user_phone
    FROM service_requests sr
    INNER JOIN users u ON u.id = sr.user_id
    WHERE sr.shop_id = ?
";

$params = [$shopId];

if ($status) {
    $sql .= " AND sr.status = ?";
    $params[] = $status;
}

$sql .= "
    ORDER BY sr.requested_at DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Total count
 */
$countSql = "
    SELECT COUNT(*) AS total
    FROM service_requests
    WHERE shop_id = ?
";

$countParams = [$shopId];

if ($status) {
    $countSql .= " AND status = ?";
    $countParams[] = $status;
}

$stmt = $pdo->prepare($countSql);
$stmt->execute($countParams);
$total = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

/**
 * Response formatting
 */
$requests = [];

foreach ($rows as $r) {
    $requests[] = [
        'id' => (int)$r['id'],
        'user' => [
            'id' => (int)$r['user_id'],
            'name' => trim($r['user_name']),
            'phone' => $r['user_phone']
        ],
        'liters' => $r['liters'] !== null ? (float)$r['liters'] : null,
        'final_amount' => $r['final_amount'] !== null ? (float)$r['final_amount'] : null,
        'status' => $r['status'],
        'description' => $r['description'],
        'requested_at' => $r['requested_at'],
        'completed_at' => $r['completed_at']
    ];
}

/**
 * Output
 */
echo json_encode([
    'success' => true,
    'data' => [
        'requests' => $requests,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int)ceil($total / $limit)
        ]
    ]
]);
