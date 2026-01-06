<?php
header("Content-Type: application/json");
require_once "../config/db.php";

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Only POST method allowed"]);
    exit;
}

// Get input
$data = json_decode(file_get_contents("php://input"), true);
$user_id = $data['user_id'] ?? null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(["message" => "User ID required"]);
    exit;
}

// Get user info
$userQuery = $conn->prepare("
    SELECT Id, Name, User_Type 
    FROM registration 
    WHERE Id = ?
");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$user = $userQuery->get_result()->fetch_assoc();

if (!$user) {
    http_response_code(404);
    echo json_encode(["message" => "User not found"]);
    exit;
}

$userType = $user['User_Type'];

// -------------------
// DASHBOARD LOGIC
// -------------------

$response = [
    "user" => [
        "id" => $user['Id'],
        "name" => $user['Name'],
        "type" => $userType
    ]
];

// ---------- USER DASHBOARD ----------
if ($userType === "User") {

    // Total Orders
    $q1 = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE User_Id = ?");
    $q1->bind_param("i", $user_id);
    $q1->execute();
    $totalOrders = $q1->get_result()->fetch_assoc()['total'];

    // Recent Orders
    $q2 = $conn->prepare("
        SELECT id, order_type, status, created_at 
        FROM orders 
        WHERE User_Id = ? 
        ORDER BY id DESC 
        LIMIT 5
    ");
    $q2->bind_param("i", $user_id);
    $q2->execute();
    $orders = $q2->get_result()->fetch_all(MYSQLI_ASSOC);

    $response["dashboard"] = [
        "total_orders" => $totalOrders,
        "recent_orders" => $orders
    ];
}

// ---------- PARTNER (BUNK / MECHANIC) ----------
if ($userType === "Bunk" || $userType === "Mechanic") {

    // Total Orders
    $q1 = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM orders 
        WHERE partner_id = ?
    ");
    $q1->bind_param("i", $user_id);
    $q1->execute();
    $totalOrders = $q1->get_result()->fetch_assoc()['total'];

    // Pending Orders
    $q2 = $conn->prepare("
        SELECT COUNT(*) as pending 
        FROM orders 
        WHERE partner_id = ? AND status = 'Pending'
    ");
    $q2->bind_param("i", $user_id);
    $q2->execute();
    $pending = $q2->get_result()->fetch_assoc()['pending'];

    // Recent Orders
    $q3 = $conn->prepare("
        SELECT id, order_type, status, created_at 
        FROM orders 
        WHERE partner_id = ? 
        ORDER BY id DESC 
        LIMIT 5
    ");
    $q3->bind_param("i", $user_id);
    $q3->execute();
    $recentOrders = $q3->get_result()->fetch_all(MYSQLI_ASSOC);

    $response["dashboard"] = [
        "total_orders" => $totalOrders,
        "pending_orders" => $pending,
        "recent_orders" => $recentOrders
    ];
}

echo json_encode([
    "status" => "success",
    "data" => $response
]);
