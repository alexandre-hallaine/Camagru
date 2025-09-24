<?php

class Setting
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT email, notify_comments FROM settings WHERE user_id = ?",
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $userId, string $email): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO settings (user_id, email) VALUES (?, ?)",
        );
        $stmt->execute([$userId, $email]);
    }

    public function updateNotifyComments(int $userId, bool $notifyComments): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE settings SET notify_comments = ? WHERE user_id = ?",
        );
        $stmt->execute([(int) $notifyComments, $userId]);
    }

    public function updateEmail(int $userId, string $email): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE settings SET email = ? WHERE user_id = ?",
        );
        $stmt->execute([$email, $userId]);
    }
}
