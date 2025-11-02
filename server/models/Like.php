<?php

class Like
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function addLike(int $userId, int $imageId): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO likes (user_id, image_id) VALUES (?, ?)");
        $stmt->execute([$userId, $imageId]);
    }

    public function removeLike(int $userId, int $imageId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM likes WHERE user_id = ? AND image_id = ?");
        $stmt->execute([$userId, $imageId]);
    }

    public function isLikedByUser(int $userId, int $imageId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM likes WHERE user_id = ? AND image_id = ?");
        $stmt->execute([$userId, $imageId]);
        return (bool) $stmt->fetch();
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT image_id, user_id FROM likes");
        return $stmt->fetchAll();
    }
}
