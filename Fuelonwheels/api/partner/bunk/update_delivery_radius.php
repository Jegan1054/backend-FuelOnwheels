<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . "/../../config/db.php";

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid JSON input"]);
    exit;
}

// Required fields
$user_id        = $data['user_id'] ?? null;
$latitude       = $data['latitude'] ?? null;
$longitude      = $data['longitude'] ?? null;
$service_radius = $data['service_radius'] ?? null;

// Validation
if (!$user_id || !$latitude || !$longitude || !$service_radius) {
    http_response_code(400);
    echo json_encode(["message" => "Missing required fields"]);
    exit;
}

// Update partner location
$stmt = $conn->prepare("
    UPDATE partners 
    SET 
        Latitude = ?, 
        Longitude = ?, 
        Service_Radius = ?, 
        updated_at = NOW()
    WHERE User_Id = ?
");

$stmt->bind_param("dddi", $latitude, $longitude, $service_radius, $user_id);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Location & service radius updated successfully"
    ]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Database update failed"]);
}
