<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . "/../../config/db.php";

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid JSON"]);
    exit;
}

$user_id = $data['user_id'] ?? null;
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;

if (!$user_id || !$latitude || !$longitude) {
    http_response_code(400);
    echo json_encode(["message" => "Missing required fields"]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE registration 
    SET latitude = ?, longitude = ?, updated_at = NOW()
    WHERE id = ?
");

$stmt->bind_param("ddi", $latitude, $longitude, $user_id);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Location updated successfully"
    ]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Database update failed"]);
}
