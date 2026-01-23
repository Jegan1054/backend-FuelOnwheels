<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
require __DIR__ . '/../vendor/autoload.php';  // <-- include Composer packages

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database configuration
$host = 'localhost';
$dbname = 'fuelsonwheels';
$username = 'root'; // Default XAMPP MySQL user
$password = ''; // Default XAMPP password
$port=3307;

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// JWT Secret Key (change this in production)
define('JWT_SECRET', 'your_secret_key_here');

// Helper functions
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateOTP() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function sendOTP($email, $otp) {
    $mail = new PHPMailer(true);

    try {
        if (DEVELOPMENT_MODE) {
            error_log("OTP for $email: $otp");
            return true;
        }

        // SMTP configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Replace with your SMTP host
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jeganlogu2005@gmail.com'; // SMTP username
        $mail->Password   = 'rujkjjrfdokcjtaj';    // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // or PHPMailer::ENCRYPTION_SMTPS
        $mail->Port       = 587; // 465 for SSL

        // Email details
        $mail->setFrom('jeganlogu2005@gmail.com', 'FuelSand Wheels');
        $mail->addAddress($email);
        $mail->Subject = 'Your OTP for FuelSand Wheels';
        $mail->Body    = "Your OTP is: $otp. It expires in 10 minutes.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}


// Development mode flag
define('DEVELOPMENT_MODE', false);

function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // km

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}

function requireAuth() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Authorization header missing']);
        exit;
    }

    $token = str_replace('Bearer ', '', $headers['Authorization']);
    // Simple token validation - in production, use proper JWT
    $user = validateToken($token);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    return $user;
}

function validateToken($token) {
    // Placeholder - implement JWT validation
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$token]); // Assuming token is user ID for simplicity
    return $stmt->fetch();
}
?>
