<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . "/../../config/db.php";

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

$partner_id = $data['partner_id'] ?? null;

if (!$partner_id) {
    http_response_code(400);
    echo json_encode(["message" => "Partner ID is required"]);
    exit;
}

// Fetch fuel prices
$stmt = $conn->prepare("
    SELECT fuel_type, price 
    FROM fuel_prices 
    WHERE partner_id = ?
");
$stmt->bind_param("i", $partner_id);
$stmt->execute();
$result = $stmt->get_result();

$fuelPrices = [];

while ($row = $result->fetch_assoc()) {
    $fuelPrices[] = $row;
}

if (count($fuelPrices) > 0) {
    echo json_encode([
        "success" => true,
        "fuel_prices" => $fuelPrices
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "No fuel prices found"
    ]);
}
