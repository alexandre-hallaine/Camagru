<?php

require __DIR__ . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__)->load();

session_start();

try {
    $dsn = $_ENV['DB_DSN'];
    $user = $_ENV['DB_USER'];
    $pass = $_ENV['DB_PASS'];

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    sendResponse(500, ["error" => "DB connection failed"]);
}

#[NoReturn]
function sendResponse($code, $data): void
{
    header("Content-Type: application/json");
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function validateInput($required): array
{
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

function auth($userid)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, username, email, confirmed FROM users WHERE id = ?");
    $stmt->execute([$userid]);
    $user = $stmt->fetch();

    if ($user['confirmed']) {
        $_SESSION["id"] = $user["id"];
        sendResponse(200, []);
    }

    try {
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
        $stmt->execute([$token, $user["id"]]);
    } catch (PDOException $e) {
        sendResponse(500, ["error" => "Server error"]);
    }

    try {
        $verifyUrl = "http://" . $_SERVER['HTTP_HOST'] . '/auth/?token=' . urlencode($token);
    } catch (\Random\RandomException $e) {
        sendResponse(500, ["error" => "Server error"]);
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USER'];
    $mail->Password = $_ENV['SMTP_PASS'];

    $mail->Port = 465;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;

    $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
    $mail->addAddress($user["email"], $user["username"]);

    $mail->Subject = 'Verify your email - Camagru';
    $mail->Body = "Hello " . $user["username"] . ",\n\n" .
                  "Please confirm your email by clicking the link below:\n" .
                  $verifyUrl . "\n\n";

    try {
        $mail->send();
    } catch (Throwable $e) {
        sendResponse(500, ["error" => $e->getMessage()]);
    }

    sendResponse(401, ["error" => "Check your email inbox to verify your account."]);
}

$router = new \Bramus\Router\Router();
$router->setBasePath('/api');

$router->post('/auth/register', function() use ($pdo) {
    $input = validateInput(["username", "email", "password"]);

    if (!filter_var($input["email"], FILTER_VALIDATE_EMAIL))
        sendResponse(400, ["error" => "Invalid email"]);
    if (strlen($input["password"]) < 6)
        sendResponse(400, ["error" => "Password too short"]);

    $hash = password_hash($input["password"], PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        $stmt->execute([$input["username"], $input["email"], $hash]);

        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$pdo->lastInsertId()]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        if ($e->getCode() == 23000)
            sendResponse(409, ["error" => "User already exists"]);
        else
            sendResponse(500, ["error" => "Server error"]);
    }

    auth($user["id"]);
});

$router->post('/auth/login', function() use ($pdo) {
    $input = validateInput(["username", "password"]);

    try {
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        $stmt->execute([$input["username"]]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["error" => "Server error"]);
    }

    if (!$user || !password_verify($input["password"], $user["password_hash"]))
        sendResponse(401, ["error" => "Invalid credentials"]);

    auth($user["id"]);
});

$router->post('/auth/verify', function() use ($pdo) {
    $input = validateInput(["token"]);

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_token = ?");
        $stmt->execute([$input["token"]]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["error" => "Server error"]);
    }

    if (!$user)
        sendResponse(401, ["error" => "Invalid token"]);

    try {
        $stmt = $pdo->prepare("UPDATE users SET confirmed = 1 WHERE id = ?");
        $stmt->execute([$user["id"]]);
    } catch (PDOException $e) {
        sendResponse(500, ["error" => "Server error"]);
    }

    auth($user["id"]);
});

$router->post('/auth/logout', function() {
    session_destroy();
    sendResponse(200, []);
});

$router->get('/auth/check', function() use ($pdo) {
    if (!isset($_SESSION['id']))
        sendResponse(401, []);

    try {
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['id']]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["error" => "Server error"]);
    }

    if (!$user)
        sendResponse(401, []);

    sendResponse(200, ["username" => $user["username"]]);
});

$router->run();
