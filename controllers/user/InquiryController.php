<?php
// controllers/user/InquiryController.php

class InquiryController {
    private PDO $db;
    private array $user = [];

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function setUser(array $user): void {
        $this->user = $user;
    }

    
    // Returns ALL threads where user is sender OR recipient
    public function index(array $params = []): void {
        ob_start();
        try {
            $userId = (int)($this->user['id'] ?? 0);

            $stmt = $this->db->prepare("
                SELECT
                    i.id,
                    i.listing_id,
                    i.message           AS opening_message,
                    i.created_at,
                    i.from_user_id,
                    i.to_user_id,

                    l.title             AS listing_title,
                    l.city              AS listing_city,
                    l.price             AS listing_price,
                    l.transaction_type,
                    l.property_type,

                    -- The 'other' person in the thread (not the current user)
                    CASE WHEN i.from_user_id = :uid1
                         THEN u_to.id
                         ELSE u_from.id
                    END AS agent_id,

                    CASE WHEN i.from_user_id = :uid2
                         THEN u_to.name
                         ELSE u_from.name
                    END AS agent_name,

                    CASE WHEN i.from_user_id = :uid3
                         THEN u_to.avatar_url
                         ELSE u_from.avatar_url
                    END AS agent_avatar,

                    CASE WHEN i.from_user_id = :uid4
                         THEN u_to.role
                         ELSE u_from.role
                    END AS agent_role,

                    CASE WHEN i.from_user_id = :uid5
                         THEN u_to.agency_name
                         ELSE u_from.agency_name
                    END AS agency_name,

                    CASE WHEN i.from_user_id = :uid6
                         THEN u_to.phone
                         ELSE u_from.phone
                    END AS agent_phone,

                    -- Direction flag so frontend knows if user is owner
                    (i.to_user_id = :uid7) AS is_received,

                    (
                        SELECT COUNT(*) FROM messages m
                        WHERE m.inquiry_id = i.id
                    ) AS message_count,

                    (
                        SELECT m2.message FROM messages m2
                        WHERE m2.inquiry_id = i.id
                        ORDER BY m2.created_at DESC LIMIT 1
                    ) AS last_message,

                    (
                        SELECT m3.created_at FROM messages m3
                        WHERE m3.inquiry_id = i.id
                        ORDER BY m3.created_at DESC LIMIT 1
                    ) AS last_message_at

                FROM inquiries i
                JOIN listings l   ON l.id      = i.listing_id
                JOIN users u_from ON u_from.id = i.from_user_id
                JOIN users u_to   ON u_to.id   = i.to_user_id
                WHERE i.from_user_id = :uid8
                   OR i.to_user_id   = :uid9
                ORDER BY COALESCE(
                    (SELECT MAX(m.created_at) FROM messages m WHERE m.inquiry_id = i.id),
                    i.created_at
                ) DESC
            ");
            $stmt->execute([
                ':uid1' => $userId, ':uid2' => $userId, ':uid3' => $userId,
                ':uid4' => $userId, ':uid5' => $userId, ':uid6' => $userId,
                ':uid7' => $userId, ':uid8' => $userId, ':uid9' => $userId,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Batch fetch cover photos
            if (!empty($rows)) {
                $listingIds = array_unique(array_column($rows, 'listing_id'));
                $ph = implode(',', array_fill(0, count($listingIds), '?'));
                $pStmt = $this->db->prepare(
                    "SELECT listing_id, photo_url FROM listing_photos
                     WHERE listing_id IN ({$ph})
                     ORDER BY is_cover DESC, sort_order ASC"
                );
                $pStmt->execute(array_values($listingIds));
                $photoMap = [];
                foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
                    $lid = (int)$p['listing_id'];
                    if (!isset($photoMap[$lid])) $photoMap[$lid] = $p['photo_url'];
                }

                foreach ($rows as &$row) {
                    $cover = $photoMap[(int)$row['listing_id']] ?? null;
                    $row['listing_cover'] = $cover
                        ? (str_starts_with($cover, '/uploads/')
                            ? 'https://homibackend-production.up.railway.app/' . $cover
                            : $cover)
                        : null;

                    if (!empty($row['agent_avatar']) && str_starts_with($row['agent_avatar'], '/uploads/')) {
                        $row['agent_avatar'] = 'https://homibackend-production.up.railway.app/' . $row['agent_avatar'];
                    }

                    $row['listing_price']  = (int)$row['listing_price'];
                    $row['message_count']  = (int)$row['message_count'];
                    $row['is_received']    = (bool)$row['is_received'];
                }
                unset($row);
            }

            ob_end_clean();
            $this->json(['data' => $rows, 'total' => count($rows)]);

        } catch (Throwable $e) {
            ob_end_clean();
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // GET /user/inquiries/{id}/messages
    // Returns all messages — user must be sender OR recipient
    public function messages(array $params = []): void {
        ob_start();
        try {
            $userId    = (int)($this->user['id'] ?? 0);
            $inquiryId = (int)($params['id'] ?? 0);

            // Allow access if user is sender OR recipient
            $chk = $this->db->prepare(
                "SELECT id, listing_id, from_user_id, to_user_id
                 FROM inquiries
                 WHERE id = :id
                   AND (from_user_id = :uid1 OR to_user_id = :uid2)"
            );
            $chk->execute([':id' => $inquiryId, ':uid1' => $userId, ':uid2' => $userId]);
            $inquiry = $chk->fetch(PDO::FETCH_ASSOC);

            if (!$inquiry) {
                ob_end_clean();
                $this->json(['error' => 'Thread not found'], 404);
                return;
            }

            $stmt = $this->db->prepare("
                SELECT
                    m.id,
                    m.inquiry_id,
                    m.sender_id,
                    m.sender_type,
                    m.message,
                    m.created_at,
                    u.name       AS sender_name,
                    u.avatar_url AS sender_avatar
                FROM messages m
                JOIN users u ON u.id = m.sender_id
                WHERE m.inquiry_id = :iid
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([':iid' => $inquiryId]);
            $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($msgs as &$m) {
                if (!empty($m['sender_avatar']) && str_starts_with($m['sender_avatar'], '/uploads/')) {
                    $m['sender_avatar'] = 'https://homibackend-production.up.railway.app/' . $m['sender_avatar'];
                }
                $m['is_mine'] = (int)$m['sender_id'] === $userId;
            }
            unset($m);

            ob_end_clean();
            $this->json([
                'inquiry_id'   => $inquiryId,
                'listing_id'   => (int)$inquiry['listing_id'],
                'from_user_id' => (int)$inquiry['from_user_id'],
                'to_user_id'   => (int)$inquiry['to_user_id'],
                'messages'     => $msgs,
            ]);

        } catch (Throwable $e) {
            ob_end_clean();
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // POST /user/inquiries
    // Start a new inquiry thread (or reuse existing one for same listing)
    // Body: { listing_id, message }
    public function store(array $params = []): void {
        ob_start();
        try {
            $userId = (int)($this->user['id'] ?? 0);
            $body   = json_decode(file_get_contents('php://input'), true) ?? [];

            $listingId = (int)($body['listing_id'] ?? 0);
            $message   = trim($body['message'] ?? '');

            if ($listingId <= 0 || $message === '') {
                ob_end_clean();
                $this->json(['error' => 'listing_id and message are required'], 400);
                return;
            }

            // Get listing owner — approved OR pending (user's own listing may be pending)
            $lstmt = $this->db->prepare(
                "SELECT id, user_id FROM listings WHERE id = :id AND status IN ('approved', 'pending')"
            );
            $lstmt->execute([':id' => $listingId]);
            $listing = $lstmt->fetch(PDO::FETCH_ASSOC);

            if (!$listing) {
                ob_end_clean();
                $this->json(['error' => 'Listing not found'], 404);
                return;
            }

            $toUserId = (int)$listing['user_id'];

            if ($toUserId === $userId) {
                ob_end_clean();
                $this->json(['error' => 'You cannot message yourself'], 400);
                return;
            }

            // Reuse existing thread if one exists
            $existing = $this->db->prepare(
                "SELECT id FROM inquiries
                 WHERE listing_id = :lid AND from_user_id = :uid LIMIT 1"
            );
            $existing->execute([':lid' => $listingId, ':uid' => $userId]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $inquiryId = (int)$row['id'];
            } else {
                $ins = $this->db->prepare(
                    "INSERT INTO inquiries (listing_id, from_user_id, to_user_id, message)
                     VALUES (:lid, :from, :to, :msg)"
                );
                $ins->execute([
                    ':lid'  => $listingId,
                    ':from' => $userId,
                    ':to'   => $toUserId,
                    ':msg'  => $message,
                ]);
                $inquiryId = (int)$this->db->lastInsertId();
            }

            // Insert message
            $mstmt = $this->db->prepare(
                "INSERT INTO messages (inquiry_id, sender_id, sender_type, message)
                 VALUES (:iid, :sid, 'user', :msg)"
            );
            $mstmt->execute([
                ':iid' => $inquiryId,
                ':sid' => $userId,
                ':msg' => $message,
            ]);

            ob_end_clean();
            $this->json(['inquiry_id' => $inquiryId, 'message' => 'Message sent'], 201);

        } catch (Throwable $e) {
            ob_end_clean();
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // POST /user/inquiries/{id}/reply
    // Send a follow-up — user must be sender OR recipient
    public function reply(array $params = []): void {
        ob_start();
        try {
            $userId    = (int)($this->user['id'] ?? 0);
            $inquiryId = (int)($params['id'] ?? 0);
            $body      = json_decode(file_get_contents('php://input'), true) ?? [];
            $message   = trim($body['message'] ?? '');

            if ($message === '') {
                ob_end_clean();
                $this->json(['error' => 'message is required'], 400);
                return;
            }

            // Allow reply if sender OR recipient
            $chk = $this->db->prepare(
                "SELECT id FROM inquiries
                 WHERE id = :id
                   AND (from_user_id = :uid1 OR to_user_id = :uid2)"
            );
            $chk->execute([':id' => $inquiryId, ':uid1' => $userId, ':uid2' => $userId]);
            if (!$chk->fetch()) {
                ob_end_clean();
                $this->json(['error' => 'Thread not found'], 404);
                return;
            }

            $mstmt = $this->db->prepare(
                "INSERT INTO messages (inquiry_id, sender_id, sender_type, message)
                 VALUES (:iid, :sid, 'user', :msg)"
            );
            $mstmt->execute([
                ':iid' => $inquiryId,
                ':sid' => $userId,
                ':msg' => $message,
            ]);

            ob_end_clean();
            $this->json(['message' => 'Reply sent'], 201);

        } catch (Throwable $e) {
            ob_end_clean();
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function json(mixed $data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
