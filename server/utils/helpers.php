<?php

function sendResponse($code, $data): void
{
    header("Content-Type: application/json");
    http_response_code($code);
    echo json_encode($data);
    exit();
}

function validateInput($required): array
{
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input)
        sendResponse(400, ["message" => "Invalid JSON"]);

    foreach ($required as $field)
        if (!isset($input[$field]))
            sendResponse(400, ["message" => "Missing field: $field"]);

    return array_map("trim", $input);
}

function sendEmail($to, $subject, $body): bool
{
    $headers = "From: {" . getenv("SMTP_FROM_NAME") . "} <{" . getenv("SMTP_FROM") . "}>\n";
    $headers .= "Reply-To: {" . getenv("SMTP_FROM") . "}\n";
    $headers .= "Content-type: text/html; charset=utf-8\n";
    return mail($to, $subject, $body, $headers);
}

function requireLogin(PDO $pdo): void
{
    if (!isset($_SESSION["id"]))
        sendResponse(401, ["message" => "Unauthorized"]);

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION["id"]]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    if (!$user)
        sendResponse(401, ["message" => "Unauthorized"]);

    if ($_SERVER["REQUEST_METHOD"] != "GET")
        if (!hash_equals($_SESSION["csrf_token"], $_SERVER["HTTP_X_CSRF_TOKEN"] ?? ''))
            sendResponse(403, ["message" => "Invalid CSRF token"]);
}

function action(PDO $pdo, $userId, $kind, $payload = null): void
{
    try {
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("INSERT INTO actions (user_id, kind, payload, token) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token), payload = VALUES(payload)");
        $stmt->execute([$userId, $kind, $payload ? json_encode($payload) : null, $token]);
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    } catch (\Random\RandomException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    $verifyUrl = "http://" . $_SERVER["HTTP_HOST"] . "/auth/?token=" . urlencode($token);

    try {
        $stmt = $pdo->prepare("SELECT email FROM settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $settings = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    if (!sendEmail(
            $settings["email"],
            "Action required",
            "Hello,\n\n" .
                "Please complete the action by opening the link below:\n" .
                "$verifyUrl\n\n" .
                "If you didn\'t request this, you can safely ignore this email.",
        ))
        sendResponse(500, ["message" => "Failed to send verification email"]);
    sendResponse(400, ["message" => "Check your email inbox to complete the action."]);
}
