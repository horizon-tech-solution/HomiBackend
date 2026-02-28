<?php
class Inquiry {
    private $conn;
    private $table = 'inquiries';

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create an inquiry about a specific listing
     */
    public function create($listingId, $fromUserId, $toUserId, $message) {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (listing_id, from_user_id, to_user_id, message) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$listingId, $fromUserId, $toUserId, $message]);
    }

    /**
     * Create a general inquiry to an agent (no specific listing)
     */
    public function createAgentContact($agentId, $fromUserId, $message) {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (from_user_id, to_user_id, message) VALUES (?, ?, ?)");
        return $stmt->execute([$fromUserId, $agentId, $message]);
    }
}