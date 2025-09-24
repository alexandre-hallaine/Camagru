<?php

class User
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, password_hash, is_confirmed FROM users WHERE username = ?",
        );
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, username FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $username, string $passwordHash): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (username, password_hash) VALUES (?, ?)",
        );
        $stmt->execute([$username, $passwordHash]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET password_hash = ?, is_confirmed = 1 WHERE id = ?",
        );
        $stmt->execute([$passwordHash, $userId]);
    }

    public function confirmAccount(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET is_confirmed = 1 WHERE id = ?",
        );
        $stmt->execute([$userId]);
    }

    public function updateUsername(int $userId, string $username): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt->execute([$username, $userId]);
    }
}
