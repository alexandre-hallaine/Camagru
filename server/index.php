<?php
session_start();
header("Content-Type: application/json");

$dsn = "mysql:host=db;dbname=camagru;charset=utf8mb4";
$user = "root";
$pass = "root";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

function sendResponse($code, $data) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function validateInput($required) {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        sendResponse(400, ["error" => "Invalid JSON"]);
    }
    
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            sendResponse(400, ["error" => "Missing field: $field"]);
        }
    }
    
    return array_map('trim', $input);
}

function validateEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(400, ["error" => "Invalid email"]);
    }
}

function validatePassword($password) {
    if (strlen($password) < 6) {
        sendResponse(400, ["error" => "Password too short"]);
    }
}

$path = trim(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH), "/");
$method = $_SERVER["REQUEST_METHOD"];

if (strpos($path, "api/") === 0) {
    $apiPath = substr($path, 4);
    
    switch ($apiPath) {
        case "signup":
            if ($method === "POST") {
                $input = validateInput(["username", "email", "password"]);
                validateEmail($input["email"]);
                validatePassword($input["password"]);
                
                $hash = password_hash($input["password"], PASSWORD_DEFAULT);
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                    $stmt->execute([$input["username"], $input["email"], $hash]);
                    sendResponse(201, ["message" => "User created"]);
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        sendResponse(409, ["error" => "User already exists"]);
                    } else {
                        sendResponse(500, ["error" => "Server error"]);
                    }
                }
            }
            break;
            
        case "signin":
            if ($method === "POST") {
                $input = validateInput(["username", "password"]);
                
                try {
                    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ? OR email = ?");
                    $stmt->execute([$input["username"], $input["username"]]);
                    $user = $stmt->fetch();
                    
                    if (!$user || !password_verify($input["password"], $user["password_hash"])) {
                        sendResponse(401, ["error" => "Invalid credentials"]);
                    }
                    
                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["username"] = $user["username"];
                    
                    sendResponse(200, ["message" => "Login successful"]);
                } catch (PDOException $e) {
                    sendResponse(500, ["error" => "Server error"]);
                }
            }
            break;
            
        case "logout":
            if ($method === "POST") {
                session_destroy();
                sendResponse(200, ["message" => "Logged out successfully"]);
            }
            break;
            
        case "check-auth":
            if ($method === "GET") {
                if (isset($_SESSION['user_id'])) {
                    $stmt = $pdo->prepare("SELECT id, username, email, confirmed, notify_on_comment, created_at FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        sendResponse(200, ["authenticated" => true, "user" => $user]);
                    } else {
                        sendResponse(200, ["authenticated" => false, "user" => null]);
                    }
                } else {
                    sendResponse(200, ["authenticated" => false, "user" => null]);
                }
            }
            break;
            
        default:
            sendResponse(404, ["error" => "API endpoint not found"]);
    }
    exit;
}

sendResponse(404, ["error" => "Not found"]);
