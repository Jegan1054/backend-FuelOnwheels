<?php
session_start();
header("Content-Type: application/json");

require_once "../../config/db.php";

// --------------------
// Allow only GET method
// --------------------
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["message" => "Only GET method is allowed"]);
    exit;
}

// --------------------
// Check Login (Session)
// --------------------
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized access"]);
    exit;
}

// --------------------
// Get Logged-in User ID
// --------------------
$userId = $_SESSION['user_id'];

// --------------------
// Fetch User Profile
// --------------------
$stmt = $conn->prepare("
    SELECT Id, Name, Email, Phone_Number, User_Type
    FROM registration
    WHERE Id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["message" => "User not found"]);
    exit;
}

$user = $result->fetch_assoc();

// --------------------
// Success Response
// --------------------
http_response_code(200);
echo json_encode([
    "message" => "Profile fetched successfully",
    "profile" => [
        "id"        => $user['Id'],
        "name"      => $user['Name'],
        "email"     => $user['Email'],
        "phone"     => $user['Phone_Number'],
        "user_type" => $user['User_Type']
    ]
]);
