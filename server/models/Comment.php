<?php

class Comment
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $userId, int $imageId, string $body): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO comments (user_id, image_id, body) VALUES (?, ?, ?)",
        );
        $stmt->execute([$userId, $imageId, $body]);
    }

    public function getByImageId(int $imageId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT user_id, body, created_at FROM comments WHERE image_id = ?",
        );
        $stmt->execute([$imageId]);
        return $stmt->fetchAll();
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT image_id, user_id, body, created_at FROM comments",
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
