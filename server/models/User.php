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
        $stmt = $this->pdo->prepare("SELECT id, password_hash, is_confirmed FROM users WHERE username = ?");
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
        $stmt = $this->pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$username, $passwordHash]);
        return (int) $this->pdo->lastInsertId();
    }

    public function confirmAccount(int $userId): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET is_confirmed = 1 WHERE id = ?");
        $stmt->execute([$userId]);
    }

    public function update(int $userId, string $username, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET username = ?, password_hash = ?, is_confirmed = 1 WHERE id = ?");
        $stmt->execute([$username, $passwordHash, $userId]);
    }

    private function getAll(): array
    {
        $stmt = $this->pdo->prepare("SELECT id, username FROM users");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
