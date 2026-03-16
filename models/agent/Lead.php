<?php
// models/agent/Lead.php

class Lead {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // All inquiries: ones received on agent's listings + ones the agent sent
    public function getByAgent(int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT
                i.id,
                i.message                AS initial_message,
                i.created_at,
                i.from_user_id,
                i.to_user_id,

                CASE WHEN l.user_id = :uid1 THEN 'received' ELSE 'sent' END AS direction,

                CASE WHEN l.user_id = :uid2
                     THEN u_from.id
                     ELSE u_to.id
                END AS user_id,

                CASE WHEN l.user_id = :uid3
                     THEN u_from.name
                     ELSE u_to.name
                END AS user_name,

                CASE WHEN l.user_id = :uid4
                     THEN u_from.email
                     ELSE u_to.email
                END AS user_email,

                CASE WHEN l.user_id = :uid5
                     THEN u_from.phone
                     ELSE u_to.phone
                END AS user_phone,

                CASE WHEN l.user_id = :uid6
                     THEN u_from.avatar_url
                     ELSE u_to.avatar_url
                END AS user_avatar,

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
             JOIN listings l   ON i.listing_id    = l.id
             JOIN users u_from ON i.from_user_id  = u_from.id
             JOIN users u_to   ON i.to_user_id    = u_to.id

             WHERE l.user_id = :uid7
                OR i.from_user_id = :uid8

             ORDER BY COALESCE(
                 (SELECT MAX(m4.created_at) FROM messages m4
                  WHERE m4.inquiry_id = i.id),
                 i.created_at
             ) DESC"
        );
        $stmt->execute([
            ':uid1' => $userId, ':uid2' => $userId, ':uid3' => $userId,
            ':uid4' => $userId, ':uid5' => $userId, ':uid6' => $userId,
            ':uid7' => $userId, ':uid8' => $userId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            if (!empty($row['user_avatar']) && str_starts_with($row['user_avatar'], '/uploads/')) {
                $row['user_avatar'] = 'https://homibackend-production.up.railway.app/' . $row['user_avatar'];
            }
            if (!empty($row['listing_photo']) && str_starts_with($row['listing_photo'], '/uploads/')) {
                $row['listing_photo'] = 'https://homibackend-production.up.railway.app/' . $row['listing_photo'];
            }
            $row['message_count'] = (int)$row['message_count'];
        }
        unset($row);

        return $rows;
    }

    // Single inquiry with full message thread
    public function getWithMessages($inquiryId, int $userId) {
        $stmt = $this->db->prepare(
            "SELECT
                i.id,
                i.message          AS initial_message,
                i.created_at,
                i.from_user_id,
                i.to_user_id,

                CASE WHEN l.user_id = :uid1 THEN 'received' ELSE 'sent' END AS direction,

                CASE WHEN l.user_id = :uid2
                     THEN u_from.id
                     ELSE u_to.id
                END AS user_id,

                CASE WHEN l.user_id = :uid3
                     THEN u_from.name
                     ELSE u_to.name
                END AS user_name,

                CASE WHEN l.user_id = :uid4
                     THEN u_from.email
                     ELSE u_to.email
                END AS user_email,

                CASE WHEN l.user_id = :uid5
                     THEN u_from.phone
                     ELSE u_to.phone
                END AS user_phone,

                CASE WHEN l.user_id = :uid6
                     THEN u_from.avatar_url
                     ELSE u_to.avatar_url
                END AS user_avatar,

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
             JOIN listings l   ON i.listing_id   = l.id
             JOIN users u_from ON i.from_user_id = u_from.id
             JOIN users u_to   ON i.to_user_id   = u_to.id

             WHERE i.id = :iid
               AND (l.user_id = :uid7 OR i.from_user_id = :uid8)"
        );
        $stmt->execute([
            ':iid'  => $inquiryId,
            ':uid1' => $userId, ':uid2' => $userId, ':uid3' => $userId,
            ':uid4' => $userId, ':uid5' => $userId, ':uid6' => $userId,
            ':uid7' => $userId, ':uid8' => $userId,
        ]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lead) return null;

        if (!empty($lead['user_avatar']) && str_starts_with($lead['user_avatar'], '/uploads/')) {
            $lead['user_avatar'] = 'https://homibackend-production.up.railway.app/' . $lead['user_avatar'];
        }
        if (!empty($lead['listing_photo']) && str_starts_with($lead['listing_photo'], '/uploads/')) {
            $lead['listing_photo'] = 'https://homibackend-production.up.railway.app/' . $lead['listing_photo'];
        }

        // ── Fetch messages — use sender_id to determine bubble side ──────────
        // We pass the current agent's userId so the frontend knows which side is "me"
        $msgStmt = $this->db->prepare(
            "SELECT
                m.id,
                m.message      AS text,
                m.sender_id,
                m.sender_type,
                m.created_at,
                u.name         AS sender_name,
                u.avatar_url   AS sender_avatar,
                u.role         AS sender_role
             FROM messages m
             JOIN users u ON m.sender_id = u.id
             WHERE m.inquiry_id = ?
             ORDER BY m.created_at ASC"
        );
        $msgStmt->execute([$inquiryId]);
        $msgs = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($msgs as &$m) {
            if (!empty($m['sender_avatar']) && str_starts_with($m['sender_avatar'], '/uploads/')) {
                $m['sender_avatar'] = 'https://homibackend-production.up.railway.app/' . $m['sender_avatar'];
            }
            // ── is_mine: true when this message was sent by the viewing agent ──
            $m['is_mine'] = ((int)$m['sender_id'] === $userId);
        }
        unset($m);

        $lead['messages']       = $msgs;
        $lead['current_user_id'] = $userId; // lets frontend double-check if needed
        return $lead;
    }

    // Post a reply — now accepts $senderType from the controller
    public function reply($inquiryId, int $senderId, string $body, string $senderType = 'agent'): int {
        $stmt = $this->db->prepare(
            "INSERT INTO messages (inquiry_id, sender_id, sender_type, message, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$inquiryId, $senderId, $senderType, $body]);
        return (int) $this->db->lastInsertId();
    }

    // Unread count
    public function getUnreadCount(int $userId): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT i.id)
             FROM inquiries i
             JOIN listings l ON i.listing_id = l.id
             WHERE l.user_id = ?
               AND i.id NOT IN (
                   SELECT DISTINCT m.inquiry_id
                   FROM messages m
                   WHERE m.sender_id = ?
               )"
        );
        $stmt->execute([$userId, $userId]);
        return (int) $stmt->fetchColumn();
    }
}