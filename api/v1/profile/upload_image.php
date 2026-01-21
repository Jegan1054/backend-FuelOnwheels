<?php
require_once '../../config.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if image_type is provided
$imageType = $_GET['image_type'] ?? null;
if (!in_array($imageType, ['profile', 'shop'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid image type. Use "profile" or "shop"']);
    exit;
}

// Check if file is uploaded
if (!isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No image file provided']);
    exit;
}

$file = $_FILES['image'];

// Validate file
$allowedTypes = [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'image/*'
];

$maxSize = 5 * 1024 * 1024; // 5MB

if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed']);
    exit;
}

if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'File size too large. Maximum 5MB allowed']);
    exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'File upload error']);
    exit;
}

// Create upload directory if it doesn't exist
$uploadDir = '../../uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '_' . time() . '.' . $extension;
$filepath = $uploadDir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save image']);
    exit;
}

// Store relative path in database
$relativePath = 'uploads/' . $filename;

if ($imageType === 'profile') {
    // Update user profile image
    $stmt = $pdo->prepare("UPDATE users SET profile_image = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$relativePath, $user['id']]);
} else {
    // Update shop image (only for mechanics and owners)
    if ($user['role'] !== 'mechanic' && $user['role'] !== 'owner') {
        http_response_code(403);
        echo json_encode(['error' => 'Only mechanics and owners can upload shop images']);
        exit;
    }

    $shopType = $user['role'] === 'mechanic' ? 'mechanic' : 'fuel';
    $stmt = $pdo->prepare("UPDATE shops SET shop_image = ?, updated_at = NOW() WHERE user_id = ? AND type = ?");
    $stmt->execute([$relativePath, $user['id'], $shopType]);
}

echo json_encode([
    'message' => ucfirst($imageType) . ' image uploaded successfully',
    'image_url' => $relativePath
]);
?>
