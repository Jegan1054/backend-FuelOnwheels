<?php
require_once '../../config.php';

$user = requireAuth();

if ($user['role'] !== 'user') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$orderId = $data['order_id'] ?? null;
$rating = $data['rating'] ?? null;
$review = trim($data['review'] ?? '');

if (!$orderId || !is_numeric($rating) || $rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input data']);
    exit;
}

// Check if order exists, belongs to user, and is completed with payment
$stmt = $pdo->prepare("
    SELECT sr.*, s.user_id as shop_owner_id, p.status as payment_status
    FROM service_requests sr
    LEFT JOIN shops s ON sr.shop_id = s.id
    LEFT JOIN payments p ON sr.id = p.request_id
    WHERE sr.id = ? AND sr.user_id = ? AND sr.status = 'completed'
");
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found or not completed']);
    exit;
}

if ($order['payment_status'] !== 'paid') {
    http_response_code(400);
    echo json_encode(['error' => 'Payment must be completed before rating']);
    exit;
}

// Check if rating already exists
$stmt = $pdo->prepare("SELECT id FROM ratings WHERE from_user_id = ? AND to_user_id = ?");
$stmt->execute([$user['id'], $order['shop_owner_id']]);
$existingRating = $stmt->fetch();

if ($existingRating) {
    // Update existing rating
    $stmt = $pdo->prepare("UPDATE ratings SET rating = ?, review = ? WHERE id = ?");
    $success = $stmt->execute([$rating, $review ?: null, $existingRating['id']]);
    $message = 'Rating updated successfully';
} else {
    // Insert new rating
    $stmt = $pdo->prepare("INSERT INTO ratings (from_user_id, to_user_id, rating, review) VALUES (?, ?, ?, ?)");
    $success = $stmt->execute([$user['id'], $order['shop_owner_id'], $rating, $review ?: null]);
    $message = 'Rating submitted successfully';
}

if (!$success) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save rating']);
    exit;
}

echo json_encode([
    'message' => $message,
    'rating' => $rating,
    'review' => $review
]);
?>
