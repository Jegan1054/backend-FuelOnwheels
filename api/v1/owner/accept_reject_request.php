<?php
/**
 * FuelSand Wheels
 * Fuel Bunk Owner - Accept / Reject Fuel Request
 * POST /api/v1/owner/accept-reject-request
 */

require_once '../../config.php';

/**
 * ----------------------------------------------------
 * AUTHENTICATION
 * ----------------------------------------------------
 */
$user = requireAuth();

if (!$user || $user['role'] !== 'owner') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied'
    ]);
    exit;
}

/**
 * ----------------------------------------------------
 * METHOD CHECK
 * ----------------------------------------------------
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

/**
 * ----------------------------------------------------
 * INPUT VALIDATION
 * ----------------------------------------------------
 */
$input = json_decode(file_get_contents('php://input'), true);

$requestId = $input['request_id'] ?? null;
$action    = $input['action'] ?? null; // accept | reject

if (!$requestId || !in_array($action, ['accept', 'reject'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request data'
    ]);
    exit;
}

try {

    /**
     * ----------------------------------------------------
     * FETCH FUEL SHOP FOR OWNER
     * ----------------------------------------------------
     */
    $stmt = $pdo->prepare("
        SELECT id 
        FROM shops 
        WHERE user_id = ? AND type = 'fuel'
        LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $shop = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shop) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Fuel bunk not found'
        ]);
        exit;
    }

    /**
     * ----------------------------------------------------
     * VALIDATE SERVICE REQUEST
     * ----------------------------------------------------
     */
    $stmt = $pdo->prepare("
        SELECT id, status 
        FROM service_requests 
        WHERE id = ? 
          AND shop_id = ? 
          AND status = 'pending'
        LIMIT 1
    ");
    $stmt->execute([$requestId, $shop['id']]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Request not found or already processed'
        ]);
        exit;
    }

    /**
     * ----------------------------------------------------
     * UPDATE REQUEST STATUS
     * ----------------------------------------------------
     */
    $newStatus = ($action === 'accept') ? 'accepted' : 'rejected';

    $stmt = $pdo->prepare("
        UPDATE service_requests 
        SET status = ? 
        WHERE id = ?
    ");
    $stmt->execute([$newStatus, $requestId]);

    /**
     * ----------------------------------------------------
     * SUCCESS RESPONSE
     * ----------------------------------------------------
     */
    echo json_encode([
        'success'    => true,
        'message'    => "Fuel request {$action}ed successfully",
        'request_id' => $requestId,
        'status'     => $newStatus
    ]);

} catch (PDOException $e) {

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'error'   => $e->getMessage()
    ]);
}
