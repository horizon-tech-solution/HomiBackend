<?php
// models/agent/Lead.php

class Lead {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // All inquiries on this agent's listings
    public function getByAgent(int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT
                i.id,
                i.message                AS initial_message,
                i.created_at,
                u.id                     AS user_id,
                u.name                   AS user_name,
                u.email                  AS user_email,
                u.phone                  AS user_phone,
                u.avatar_url             AS user_avatar,
                l.id                     AS listing_id,
                l.title                  AS listing_title,
                l.city                   AS listing_city,
                l.transaction_type       AS listing_type,
                (SELECT lp.photo_url FROM listing_photos lp
                 WHERE lp.listing_id = l.id AND lp.is_cover = 1
                 LIMIT 1)                AS listing_photo,
                (SELECT COUNT(*) FROM messages m
                 WHERE m.inquiry_id = i.id)
                                         AS message_count,
                (SELECT m2.created_at FROM messages m2
                 WHERE m2.inquiry_id = i.id
                 ORDER BY m2.created_at DESC LIMIT 1)
                                         AS last_message_at,
                (SELECT m3.message FROM messages m3
                 WHERE m3.inquiry_id = i.id
                 ORDER BY m3.created_at DESC LIMIT 1)
                                         AS last_message_preview
             FROM inquiries i
             JOIN listings l ON i.listing_id = l.id
             JOIN users    u ON i.from_user_id = u.id
             WHERE l.user_id = ?
             ORDER BY COALESCE(
                 (SELECT m4.created_at FROM messages m4
                  WHERE m4.inquiry_id = i.id
                  ORDER BY m4.created_at DESC LIMIT 1),
                 i.created_at
             ) DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Single inquiry with full message thread
    public function getWithMessages($inquiryId, int $userId) {
        $stmt = $this->db->prepare(
            "SELECT
                i.id,
                i.message          AS initial_message,
                i.created_at,
                u.id               AS user_id,
                u.name             AS user_name,
                u.email            AS user_email,
                u.phone            AS user_phone,
                u.avatar_url       AS user_avatar,
                l.id               AS listing_id,
                l.title            AS listing_title,
                l.city             AS listing_city,
                l.price            AS listing_price,
                l.transaction_type AS listing_type,
                l.bedrooms,
                l.bathrooms,
                (SELECT lp.photo_url FROM listing_photos lp
                 WHERE lp.listing_id = l.id AND lp.is_cover = 1
                 LIMIT 1)          AS listing_photo
             FROM inquiries i
             JOIN listings l ON i.listing_id = l.id
             JOIN users    u ON i.from_user_id = u.id
             WHERE i.id = ? AND l.user_id = ?"
        );
        $stmt->execute([$inquiryId, $userId]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lead) return null;

        $msgStmt = $this->db->prepare(
            "SELECT
                m.id,
                m.message    AS text,
                m.sender_id,
                m.sender_type,
                m.created_at,
                u.name       AS sender_name,
                u.avatar_url AS sender_avatar
             FROM messages m
             JOIN users u ON m.sender_id = u.id
             WHERE m.inquiry_id = ?
             ORDER BY m.created_at ASC"
        );
        $msgStmt->execute([$inquiryId]);
        $lead['messages'] = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

        return $lead;
    }

    // Post a reply message
    public function reply($inquiryId, int $agentId, string $body): int {
        $stmt = $this->db->prepare(
            "INSERT INTO messages (inquiry_id, sender_id, sender_type, message, created_at)
             VALUES (?, ?, 'agent', ?, NOW())"
        );
        $stmt->execute([$inquiryId, $agentId, $body]);
        return (int) $this->db->lastInsertId();
    }

    // Inquiries on agent's listings with no agent reply yet
    public function getUnreadCount(int $userId): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT i.id)
             FROM inquiries i
             JOIN listings l ON i.listing_id = l.id
             WHERE l.user_id = ?
               AND i.id NOT IN (
                   SELECT DISTINCT m.inquiry_id
                   FROM messages m
                   WHERE m.sender_id = ? AND m.sender_type = 'agent'
               )"
        );
        $stmt->execute([$userId, $userId]);
        return (int) $stmt->fetchColumn();
    }
}