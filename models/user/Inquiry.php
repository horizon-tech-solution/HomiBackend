<?php
class Inquiry {
    private $conn;
    private $table = 'inquiries';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByUser($userId, $role = 'from') {
        if ($role === 'from') {
            $stmt = $this->conn->prepare("SELECT i.*, l.title as listing_title, u.name as agent_name FROM {$this->table} i JOIN listings l ON i.listing_id = l.id JOIN users u ON l.user_id = u.id WHERE i.from_user_id = ? ORDER BY i.created_at DESC");
        } else {
            $stmt = $this->conn->prepare("SELECT i.*, l.title as listing_title, u.name as user_name FROM {$this->table} i JOIN listings l ON i.listing_id = l.id JOIN users u ON i.from_user_id = u.id WHERE l.user_id = ? ORDER BY i.created_at DESC");
        }
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMessages($inquiryId, $userId) {
        $stmt = $this->conn->prepare("SELECT m.*, u.name as sender_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.inquiry_id = ? AND (m.sender_id = ? OR m.sender_id IN (SELECT user_id FROM listings WHERE id = (SELECT listing_id FROM inquiries WHERE id = ?))) ORDER BY m.created_at ASC");
        $stmt->execute([$inquiryId, $userId, $inquiryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sendMessage($inquiryId, $senderId, $message) {
        $stmt = $this->conn->prepare("INSERT INTO messages (inquiry_id, sender_id, sender_type, message) VALUES (?, ?, 'user', ?)");
        return $stmt->execute([$inquiryId, $senderId, $message]);
    }

    public function create($listingId, $fromUserId, $message) {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (listing_id, from_user_id, to_user_id, message) SELECT ?, ?, user_id FROM listings WHERE id = ?");
        return $stmt->execute([$listingId, $fromUserId, $listingId]);
    }
}