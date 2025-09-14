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
    sendResponse(500, ["message" => "DB connection failed"]);
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
        sendResponse(400, ["message" => "Invalid JSON"]);
    }

    foreach ($required as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            sendResponse(400, ["message" => "Missing field: $field"]);
        }
    }

    return array_map('trim', $input);
}

function sendEmail($to, $subject, $body): bool
{
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USER'];
    $mail->Password = $_ENV['SMTP_PASS'];

    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->Timeout = 5;

    $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
    $mail->addAddress($to);
        
    $mail->Subject = $subject;
    $mail->Body = $body;

    try {
        return $mail->send();
    } catch (Throwable $e) {
        return false;
    }
}

function requireLogin(): void
{
    global $pdo;

    if (!isset($_SESSION['id']))
        sendResponse(401, ["message" => "Unauthorized"]);

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['id']]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    if (!$user)
        sendResponse(401, ["message" => "Unauthorized"]);
}

$router = new \Bramus\Router\Router();
$router->setBasePath('/api');

$router->post('/auth', function() use ($pdo) {
    $input = validateInput(["email", "password"]);

    if (!filter_var($input["email"], FILTER_VALIDATE_EMAIL))
        sendResponse(400, ["message" => "Invalid email"]);
    if (strlen($input["password"]) < 6)
        sendResponse(400, ["message" => "Password too short"]);

    try {
        $stmt = $pdo->prepare("SELECT id, password_hash, email, confirmed FROM users WHERE email = ?");
        $stmt->execute([$input["email"]]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    if (!$user) {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
            $stmt->execute([$input["email"], password_hash($input["password"], PASSWORD_DEFAULT)]);

            $stmt = $pdo->prepare("SELECT id, email, confirmed FROM users WHERE id = ?");
            $stmt->execute([$pdo->lastInsertId()]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            sendResponse(500, ["message" => $e->getMessage()]);
        }
    } else if (!password_verify($input["password"], $user["password_hash"]))
        sendResponse(400, ["message" => "Invalid credentials"]);

    if ($user['confirmed']) {
        $_SESSION["id"] = $user["id"];
        sendResponse(200, []);
    }

    try {
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("INSERT INTO actions (user_id, action, token) VALUES (?, 'VERIFY_ACCOUNT', ?) ON DUPLICATE KEY UPDATE token = VALUES(token)");
        $stmt->execute([$user["id"], $token]);
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    try {
        $verifyUrl = "http://" . $_SERVER['HTTP_HOST'] . '/auth/?token=' . urlencode($token);
    } catch (\Random\RandomException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    if (!sendEmail($user["email"], "Verify your email",
        "Hello,\n\n".
        "Please confirm your email by opening the link below:\n".
        "$verifyUrl\n\n".
        "If you didn't create an account, you can safely ignore this email."
    ))
        sendResponse(500, ["message" => "Failed to send verification email"]);
    sendResponse(401, ["message" => "Check your email inbox to verify your account."]);
});

$router->post('/auth/verify', function() use ($pdo) {
    $input = validateInput(["token"]);

    try {
        $stmt = $pdo->prepare("SELECT user_id FROM actions WHERE action = 'VERIFY_ACCOUNT' AND token = ?");
        $stmt->execute([$input["token"]]);
        $action = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    if (!$action)
        sendResponse(400, ["message" => "Invalid token"]);

    try {
        $stmt = $pdo->prepare("UPDATE users SET confirmed = 1 WHERE id = ?");
        $stmt->execute([$action["user_id"]]);
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    $_SESSION["id"] = $action["user_id"];
    sendResponse(200, []);
});

$router->post('/auth/logout', function() {
    session_destroy();
    sendResponse(200, []);
});

$router->get('/auth/check', function() use ($pdo) {
    requireLogin();
    sendResponse(200, []);
});

$router->post('/auth/reset/send', function() use ($pdo) {
    $input = validateInput(["email"]);

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$input["email"]]);
        $user = $stmt->fetch();

        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("INSERT INTO actions (user_id, action, token) VALUES (?, 'RESET_PASSWORD', ?) ON DUPLICATE KEY UPDATE token = VALUES(token)");
        $stmt->execute([$user["id"], $token]);
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    try {
        $verifyUrl = "http://" . $_SERVER['HTTP_HOST'] . '/auth/reset/?token=' . urlencode($token);
    } catch (\Random\RandomException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    if (!sendEmail($input["email"], "Reset your password",
        "Hello,\n\n".
        "You can reset your password by opening the link below:\n".
        "$verifyUrl\n\n".
        "If you didn't request a password reset, you can safely ignore this email."
    ))
        sendResponse(500, ["message" => "Failed to send verification email"]);
    sendResponse(200, ["message" => "Check your email inbox to reset your password."]);
});

$router->post('/auth/reset', function() use ($pdo) {
    $input = validateInput(["token", "password"]);

    if (strlen($input["password"]) < 6)
        sendResponse(400, ["message" => "Password too short"]);

    try {
        $stmt = $pdo->prepare("SELECT user_id FROM actions WHERE action = 'RESET_PASSWORD' AND token = ?");
        $stmt->execute([$input["token"]]);
        $action = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    if (!$action)
        sendResponse(400, ["message" => "Invalid token"]);

    try {
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, confirmed = 1 WHERE id = ?");
        $stmt->execute([password_hash($input["password"], PASSWORD_DEFAULT), $action["user_id"]]);
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    $_SESSION["id"] = $action["user_id"];
    sendResponse(200, []);
});

$router->post('/images/upload', function() use ($pdo) {
    requireLogin();
    $input = validateInput(["image"]);

    try {
        $stmt = $pdo->prepare("INSERT INTO images (user_id, image_data) VALUES (?, ?)");
        $stmt->execute([$_SESSION['id'], $input["image"]]);
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    sendResponse(200, []);
});

$router->get('/images', function() use ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT id, user_id, image_data, created_at FROM images");
        $stmt->execute();
        $images = $stmt->fetchAll();

        if (isset($_SESSION['id'])) {
            $stmt = $pdo->prepare("SELECT image_id FROM likes WHERE user_id = ?");
            $stmt->execute([$_SESSION['id']]);
            $liked = $stmt->fetchAll();

            $stmt = $pdo->prepare("SELECT image_id, user_id, body, created_at FROM comments");
            $stmt->execute();
            $comments = $stmt->fetchAll();

            foreach ($images as &$image) {
                $image['liked'] = in_array($image['id'], $liked);
                $image['comments'] = array_values(array_filter($comments, fn($c) => $c['image_id'] == $image['id']));
            }
        }

        sendResponse(200, ["images" => $images]);
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }
});

$router->post('/images/(\d+)/like', function($id) use ($pdo) {
    requireLogin();

    try {
        $stmt = $pdo->prepare("SELECT 1 FROM likes WHERE user_id = ? AND image_id = ?");
        $stmt->execute([$_SESSION['id'], $id]);
        $liked = (bool)$stmt->fetch();

        if ($liked) {
            $stmt = $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND image_id = ?");
            $stmt->execute([$_SESSION['id'], $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO likes (user_id, image_id) VALUES (?, ?)");
            $stmt->execute([$_SESSION['id'], $id]);
        }

        sendResponse(200, ["liked" => !$liked]);
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }
});

$router->post('/images/(\d+)/comment', function($id) use ($pdo) {
    requireLogin();
    $input = validateInput(["body"]);

    try {
        $stmt = $pdo->prepare("INSERT INTO comments (user_id, image_id, body) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['id'], $id, $input["body"]]);

        sendResponse(200, ["comment" => ["created_at" => date("Y-m-d H:i:s")]]);
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }
});

$router->run();
