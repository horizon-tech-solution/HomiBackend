<?php
class Message {
    private $conn;
    private $table = 'messages';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByInquiry($inquiryId) {
        $stmt = $this->conn->prepare("SELECT m.*, u.name as sender_name FROM {$this->table} m JOIN users u ON m.sender_id = u.id WHERE m.inquiry_id = ? ORDER BY m.created_at ASC");
        $stmt->execute([$inquiryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($inquiryId, $senderId, $senderType, $message) {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (inquiry_id, sender_id, sender_type, message) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$inquiryId, $senderId, $senderType, $message]);
    }
}