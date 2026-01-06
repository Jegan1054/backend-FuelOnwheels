<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$SECRET_KEY = "YOUR_SUPER_SECRET_KEY";

function generateJWT($user) {
    global $SECRET_KEY;

    $payload = [
        "iss" => "fuelonwheels",
        "iat" => time(),
        "exp" => time() + (60 * 60 * 24), // 1 day
        "user_id" => $user['Id'],
        "email" => $user['Email'],
        "role" => $user['User_Type']
    ];

    return JWT::encode($payload, $SECRET_KEY, 'HS256');
}

function verifyJWT($token) {
    global $SECRET_KEY;
    try {
        return JWT::decode($token, new Key($SECRET_KEY, 'HS256'));
    } catch (Exception $e) {
        return false;
    }
}
