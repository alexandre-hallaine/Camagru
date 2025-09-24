<?php

class Action
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function createOrUpdate(
        int $userId,
        string $kind,
        ?array $payload,
        string $token,
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO actions (user_id, kind, payload, token) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token), payload = VALUES(payload)",
        );
        $stmt->execute([
            $userId,
            $kind,
            $payload ? json_encode($payload) : null,
            $token,
        ]);
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT user_id, kind, payload FROM actions WHERE token = ?",
        );
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public function delete(string $token): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM actions WHERE token = ?");
        $stmt->execute([$token]);
    }
}
