<?php

$input = json_decode(file_get_contents("php://input"), true);
if (!$input || !isset($input["username"], $input["email"], $input["password"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing fields"]);
    exit;
}

$username = trim($input["username"]);
$email = trim($input["email"]);
$password = $input["password"];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid email"]);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(["error" => "Password too short"]);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->execute([$username, $email, $hash]);

    http_response_code(201);
    echo json_encode(["message" => "User created"]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // duplicate key
        http_response_code(409);
        echo json_encode(["error" => "User already exists"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Server error"]);
    }
}
