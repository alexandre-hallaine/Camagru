<?php
header("Content-Type: application/json");

$dsn = "mysql:host=db;dbname=camagru;charset=utf8mb4";
$user = "root";
$pass = "root";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

$path = trim(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH), "/");
$method = $_SERVER["REQUEST_METHOD"];

if ($path === "api/signup" && $method === "POST") {
    require __DIR__ . "/signup.php";
    exit;
}

http_response_code(404);
echo json_encode(["error" => "Not found"]);
