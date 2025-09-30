<?php

class Image
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $userId, string $content): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO images (user_id, content) VALUES (?, ?)");
        $stmt->execute([$userId, $content]);
    }

    public function delete(int $imageId, int $userId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM images WHERE id = ? AND user_id = ?");
        $stmt->execute([$imageId, $userId]);
    }

    public function getPaginated(int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare("SELECT id, user_id, content, created_at FROM images ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getUserIdByImageId(int $imageId): ?int
    {
        $stmt = $this->pdo->prepare("SELECT user_id FROM images WHERE id = ?");
        $stmt->execute([$imageId]);
        $result = $stmt->fetch();
        return $result ? (int) $result["user_id"] : null;
    }
}
