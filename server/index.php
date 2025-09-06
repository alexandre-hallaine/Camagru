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
    $stmt = $pdo->prepare("SELECT id, email, confirmed FROM users WHERE id = ?");
    $stmt->execute([$userid]);
    $user = $stmt->fetch();

    if ($user['confirmed']) {
        $_SESSION["id"] = $user["id"];
        sendResponse(200, []);
    }

    try {
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("INSERT INTO actions (user_id, action, token) VALUES (?, 'VERIFY_ACCOUNT', ?) ON DUPLICATE KEY UPDATE token = VALUES(token)");
        $stmt->execute([$user["id"], $token]);
    } catch (PDOException $e) {
        sendResponse(500, ["error" => $e->getMessage()]);
    }

    try {
        $verifyUrl = "http://" . $_SERVER['HTTP_HOST'] . '/auth/?token=' . urlencode($token);
    } catch (\Random\RandomException $e) {
        sendResponse(500, ["error" => $e->getMessage()]);
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
    $mail->addAddress($user["email"]);
        
    $mail->Subject = 'Verify your email';
    $mail->Body =
        "Hello,\n\n".
        "Please confirm your email by opening the link below:\n".
        "$verifyUrl\n\n".
        "If you didn't create an account, you can safely ignore this email.";

    try {
        $mail->send();
    } catch (Throwable $e) {
        sendResponse(500, ["error" => $e->getMessage()]);
    }

    sendResponse(401, ["error" => "Check your email inbox to verify your account."]);
}

$router = new \Bramus\Router\Router();
$router->setBasePath('/api');

$router->post('/auth', function() use ($pdo) {
    $input = validateInput(["email", "password"]);

    if (!filter_var($input["email"], FILTER_VALIDATE_EMAIL))
        sendResponse(400, ["error" => "Invalid email"]);
    if (strlen($input["password"]) < 6)
        sendResponse(400, ["error" => "Password too short"]);

    try {
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ?");
        $stmt->execute([$input["email"]]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["error" => $e->getMessage()]);
    }

    if (!$user) {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
            $stmt->execute([$input["email"], password_hash($input["password"], PASSWORD_DEFAULT)]);

            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$pdo->lastInsertId()]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            sendResponse(500, ["error" => $e->getMessage()]);
        }
    } else if (!password_verify($input["password"], $user["password_hash"]))
        sendResponse(401, ["error" => "Invalid credentials"]);

    auth($user["id"]);
});

$router->post('/auth/verify', function() use ($pdo) {
    $input = validateInput(["token"]);

    try {
        $stmt = $pdo->prepare("SELECT user_id FROM actions WHERE action = 'VERIFY_ACCOUNT' AND token = ?");
        $stmt->execute([$input["token"]]);
        $action = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["error" => $e->getMessage()]);
    }

    if (!$action)
        sendResponse(401, ["error" => "Invalid token"]);

    try {
        $stmt = $pdo->prepare("UPDATE users SET confirmed = 1 WHERE id = ?");
        $stmt->execute([$action["user_id"]]);
    } catch (PDOException $e) {
        sendResponse(500, ["error" => $e->getMessage()]);
    }

    auth($action["user_id"]);
});

$router->post('/auth/logout', function() {
    session_destroy();
    sendResponse(200, []);
});

$router->get('/auth/check', function() use ($pdo) {
    if (!isset($_SESSION['id']))
        sendResponse(401, []);

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['id']]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["error" => $e->getMessage()]);
    }

    if (!$user)
        sendResponse(401, []);

    sendResponse(200, []);
});

$router->run();
