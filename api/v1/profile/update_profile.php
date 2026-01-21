<?php
require_once '../../config.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Update user profile
$userUpdates = [];
$userParams = [];

if (isset($data['first_name'])) {
    $userUpdates[] = 'first_name = ?';
    $userParams[] = trim($data['first_name']);
}

if (isset($data['last_name'])) {
    $userUpdates[] = 'last_name = ?';
    $userParams[] = trim($data['last_name']);
}

if (isset($data['phone'])) {
    $userUpdates[] = 'phone = ?';
    $userParams[] = trim($data['phone']);
}

if (!empty($userUpdates)) {
    $userParams[] = $user['id'];
    $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $userUpdates) . ", updated_at = NOW() WHERE id = ?");
    if (!$stmt->execute($userParams)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update user profile']);
        exit;
    }
}

// Update shop profile if user is mechanic or owner
if (($user['role'] === 'mechanic' || $user['role'] === 'owner') && isset($data['shop'])) {
    $shopType = $user['role'] === 'mechanic' ? 'mechanic' : 'fuel';
    $shopData = $data['shop'];

    // Check if shop exists
    $stmt = $pdo->prepare("SELECT id FROM shops WHERE user_id = ? AND type = ?");
    $stmt->execute([$user['id'], $shopType]);
    $shop = $stmt->fetch();

    if ($shop) {
        $shopUpdates = [];
        $shopParams = [];

        if (isset($shopData['description'])) {
            $shopUpdates[] = 'description = ?';
            $shopParams[] = trim($shopData['description']);
        }

        if (isset($shopData['address'])) {
            $shopUpdates[] = 'address = ?';
            $shopParams[] = trim($shopData['address']);
        }

        if (isset($shopData['phone'])) {
            $shopUpdates[] = 'phone = ?';
            $shopParams[] = trim($shopData['phone']);
        }

        if (isset($shopData['latitude']) && isset($shopData['longitude'])) {
            $shopUpdates[] = 'latitude = ?, longitude = ?';
            $shopParams[] = $shopData['latitude'];
            $shopParams[] = $shopData['longitude'];
        }

        if (isset($shopData['radius'])) {
            $shopUpdates[] = 'radius = ?';
            $shopParams[] = $shopData['radius'];
        }

        if (!empty($shopUpdates)) {
            $shopParams[] = $shop['id'];
            $stmt = $pdo->prepare("UPDATE shops SET " . implode(', ', $shopUpdates) . ", updated_at = NOW() WHERE id = ?");
            if (!$stmt->execute($shopParams)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update shop profile']);
                exit;
            }
        }
    }
}

echo json_encode(['message' => 'Profile updated successfully']);
?>
