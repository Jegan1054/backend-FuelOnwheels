<?php
header("Content-Type: application/json");

require_once "../config/db.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . "/../PHPMailer/src/PHPMailer.php";
require __DIR__ . "/../PHPMailer/src/SMTP.php";
require __DIR__ . "/../PHPMailer/src/Exception.php";



// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Only POST method allowed"]);
    exit;
}

// Read input
$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');

// Validate email
if ($email === '') {
    http_response_code(400);
    echo json_encode(["message" => "Email is required"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(["message" => "Invalid email format"]);
    exit;
}

// Check email exists
$stmt = $conn->prepare("SELECT Id FROM registration WHERE Email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["message" => "Email not registered"]);
    exit;
}

// Generate OTP
$otp = rand(100000, 999999);
$expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));

// Save OTP in DB
$update = $conn->prepare(
    "UPDATE registration SET otp=?, otp_expiry=? WHERE Email=?"
);
$update->bind_param("sss", $otp, $expiry, $email);
$update->execute();

// Send OTP Email
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = "smtp.gmail.com";
    $mail->SMTPAuth   = true;
    $mail->Username   = "jeganlogu2005@gmail.com";   // ✅ CORRECT
    $mail->Password   = "rujkjjrfdokcjtaj";           // ✅ APP PASSWORD
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom("jeganlogu2005@gmail.com", "FuelOnWheels");
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "Your OTP for Password Reset";
    $mail->Body = "
        <h2>Your OTP</h2>
        <h1>$otp</h1>
        <p>This OTP is valid for 5 minutes.</p>
    ";

    $mail->send();

    http_response_code(200);
    echo json_encode(["message" => "OTP sent successfully"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "message" => "Failed to send OTP",
        "error" => $mail->ErrorInfo
    ]);
}
