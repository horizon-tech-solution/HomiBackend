<?php
// models/user/Notification.php
require_once __DIR__ . '/../../config/email.php';

class Notification {
    private $conn;
    private $table = 'notifications';

    public function __construct($db) {
        $this->conn = $db;
    }

    // ── Create notification + send email instantly ────────────────────────────
    public function create(int $userId, string $type, string $title, string $message, ?array $data = null): bool {
        // 1. Save in-app notification
        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table} (user_id, type, title, message, data)
             VALUES (?, ?, ?, ?, ?)"
        );
        $ok = $stmt->execute([
            $userId,
            $type,
            $title,
            $message,
            $data ? json_encode($data) : null,
        ]);

        if (!$ok) return false;

        // 2. Send email if user has email notifications enabled for this type
        $this->maybeSendEmail($userId, $type, $title, $message, $data);

        return true;
    }

    // ── Check prefs and send email ────────────────────────────────────────────
    private function maybeSendEmail(int $userId, string $type, string $title, string $message, ?array $data): void {
        $stmt = $this->conn->prepare(
            "SELECT email, name, notification_preferences FROM users WHERE id = ? AND status = 'active'"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return;

        // Parse notification preferences
        $prefs    = json_decode($user['notification_preferences'] ?? '{}', true);
        $emailPrefs = $prefs['email'] ?? [];

        // Map notification type to pref key
        $prefKey = match($type) {
            'price_drop'  => 'price_drop',
            'new_listing' => 'new_listing',
            'message'     => 'message',
            'system'      => null, // always send system emails
            default       => null,
        };

        // Skip if user has disabled this type (system notifications always go through)
        if ($prefKey !== null && isset($emailPrefs[$prefKey]) && $emailPrefs[$prefKey] === false) {
            return;
        }

        EmailService::sendNotification(
            $user['email'],
            $user['name'],
            $title,
            $message,
            $type,
            $data
        );
    }

    // ── Read methods ──────────────────────────────────────────────────────────
    public function getByUser(int $userId, bool $unreadOnly = false, int $limit = 50): array {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ?";
        if ($unreadOnly) $sql .= " AND read_at IS NULL";
        $sql .= " ORDER BY created_at DESC LIMIT ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $userId);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnreadCount(int $userId): int {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE user_id = ? AND read_at IS NULL"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public function markAsRead(int $id, int $userId): bool {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table} SET read_at = NOW() WHERE id = ? AND user_id = ?"
        );
        return $stmt->execute([$id, $userId]);
    }

    public function markAllAsRead(int $userId): bool {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table} SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL"
        );
        return $stmt->execute([$userId]);
    }

    public function delete(int $id, int $userId): bool {
        $stmt = $this->conn->prepare(
            "DELETE FROM {$this->table} WHERE id = ? AND user_id = ?"
        );
        return $stmt->execute([$id, $userId]);
    }
}