<?php
// models/agent/Notification.php

class Notification {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // Get all notifications for agent (newest first)
    public function getByAgent($userId, $limit = 50) {
        $stmt = $this->db->prepare(
            "SELECT id, type, title, message, data, read_at, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$userId, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON data field
        foreach ($rows as &$row) {
            $row['data'] = $row['data'] ? json_decode($row['data'], true) : [];
            $row['read'] = !is_null($row['read_at']);
        }
        return $rows;
    }

    // Count unread
    public function getUnreadCount($userId) {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    // Mark single notification as read
    public function markRead($id, $userId) {
        $stmt = $this->db->prepare(
            "UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL"
        );
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    // Mark all as read
    public function markAllRead($userId) {
        $stmt = $this->db->prepare(
            "UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL"
        );
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }

    // Delete a single notification
    public function delete($id, $userId) {
        $stmt = $this->db->prepare(
            "DELETE FROM notifications WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    // Delete all notifications for agent
    public function deleteAll($userId) {
        $stmt = $this->db->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }

    // Delete selected notifications
    public function deleteSelected($ids, $userId) {
        if (empty($ids)) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "DELETE FROM notifications WHERE id IN ($placeholders) AND user_id = ?"
        );
        $stmt->execute([...$ids, $userId]);
        return $stmt->rowCount();
    }

    // Create a notification (called internally by other controllers)
    public function create($userId, $type, $title, $message, $data = []) {
        $stmt = $this->db->prepare(
            "INSERT INTO notifications (user_id, type, title, message, data, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $userId,
            $type,
            $title,
            $message,
            !empty($data) ? json_encode($data) : null,
        ]);
        return $this->db->lastInsertId();
    }
}