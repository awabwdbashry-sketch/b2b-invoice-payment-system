<?php
declare(strict_types=1);

final class Notifier
{
    /** Notify one specific user. */
    public static function notifyUser(int $userId, string $title, string $message, string $type, ?string $entityType = null, ?int $entityId = null): void
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (user_id, title, message, type, related_entity_type, related_entity_id, is_read, created_at)
             VALUES (:uid, :title, :msg, :type, :etype, :eid, 0, :now)'
        );
        $stmt->execute([
            'uid' => $userId, 'title' => $title, 'msg' => $message, 'type' => $type,
            'etype' => $entityType, 'eid' => $entityId, 'now' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Notify every user holding one of the given role names (e.g. Admin, Finance). */
    public static function notifyRoles(array $roleNames, string $title, string $message, string $type, ?string $entityType = null, ?int $entityId = null): void
    {
        $pdo = Database::get();
        $placeholders = implode(',', array_fill(0, count($roleNames), '?'));
        $stmt = $pdo->prepare(
            "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.name IN ($placeholders) AND u.status = 'active'"
        );
        $stmt->execute($roleNames);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            self::notifyUser((int)$uid, $title, $message, $type, $entityType, $entityId);
        }
    }

    public static function unreadCount(int $userId): int
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
        $stmt->execute(['uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function recent(int $userId, int $limit = 8): array
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT :lim');
        $stmt->bindValue('uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function markRead(int $userId, int $notificationId): void
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $notificationId, 'uid' => $userId]);
    }

    public static function markAllRead(int $userId): void
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0');
        $stmt->execute(['uid' => $userId]);
    }
}
