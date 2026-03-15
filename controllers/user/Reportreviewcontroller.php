<?php
// controllers/user/ReportReviewController.php
require_once __DIR__ . '/../../config/user_auth.php';

class ReportReviewController {
    private $db;
    private $user;

    public function __construct($db) { $this->db = $db; }
    public function setUser($user)   { $this->user = $user; }

    // ── POST /user/reports ────────────────────────────────────────────────────
    // Body: { subject_type, subject_id, type, description, linked_listing_id? }
    public function storeReport(): void {
        ob_start();
        try {
            $userId = (int)($this->user['id'] ?? 0);
            $body   = json_decode(file_get_contents('php://input'), true) ?? [];

            $subjectType     = trim($body['subject_type']     ?? '');
            $subjectId       = (int)($body['subject_id']      ?? 0);
            $type            = trim($body['type']             ?? '');
            $description     = trim($body['description']      ?? '');
            $linkedListingId = isset($body['linked_listing_id']) && $body['linked_listing_id']
                ? (int)$body['linked_listing_id']
                : null;

            // Validate
            if (!in_array($subjectType, ['listing', 'user'], true)) {
                ob_end_clean();
                $this->json(['error' => 'Invalid subject_type'], 400);
                return;
            }
            if ($subjectId <= 0 || !$type || !$description) {
                ob_end_clean();
                $this->json(['error' => 'subject_id, type and description are required'], 400);
                return;
            }

            // Prevent reporting yourself
            if ($subjectType === 'agent') {
                $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ? AND role = 'agent' LIMIT 1");
                $stmt->execute([$subjectId]);
                $agentRow = $stmt->fetch();
                if ($agentRow && (int)$agentRow['id'] === $userId) {
                    ob_end_clean();
                    $this->json(['error' => 'You cannot report yourself'], 400);
                    return;
                }
            }

            // Check duplicate report from same user for same subject
            $dup = $this->db->prepare(
                "SELECT id FROM reports
                 WHERE subject_type = ? AND subject_id = ? AND reported_by_user_id = ?
                 AND status NOT IN ('resolved', 'dismissed')
                 LIMIT 1"
            );
            $dup->execute([$subjectType, $subjectId, $userId]);
            if ($dup->fetch()) {
                ob_end_clean();
                $this->json(['error' => 'You have already reported this. Our team is reviewing it.'], 409);
                return;
            }

            $stmt = $this->db->prepare(
                "INSERT INTO reports
                    (type, priority, status, subject_type, subject_id,
                     reported_by_user_id, description, linked_listing_id, submitted_at)
                 VALUES (?, 'medium', 'open', ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$type, $subjectType, $subjectId, $userId, $description, $linkedListingId]);
            $reportId = (int)$this->db->lastInsertId();

            ob_end_clean();
            $this->json(['id' => $reportId, 'message' => 'Report submitted successfully'], 201);

        } catch (Throwable $e) {
            ob_end_clean();
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── POST /user/reviews ────────────────────────────────────────────────────
    // Body: { agent_id, rating, comment }
    public function storeReview(): void {
        ob_start();
        try {
            $userId = (int)($this->user['id'] ?? 0);
            $body   = json_decode(file_get_contents('php://input'), true) ?? [];

            $agentId = (int)($body['agent_id'] ?? 0);
            $rating  = (int)($body['rating']   ?? 0);
            $comment = trim($body['comment']   ?? '');

            if ($agentId <= 0 || $rating < 1 || $rating > 5 || !$comment) {
                ob_end_clean();
                $this->json(['error' => 'agent_id, rating (1-5) and comment are required'], 400);
                return;
            }

            // Can't review yourself
            if ($agentId === $userId) {
                ob_end_clean();
                $this->json(['error' => 'You cannot review yourself'], 400);
                return;
            }

            // Check agent exists
            $stmt = $this->db->prepare(
                "SELECT id FROM users WHERE id = ? AND role = 'agent' AND status = 'active' LIMIT 1"
            );
            $stmt->execute([$agentId]);
            if (!$stmt->fetch()) {
                ob_end_clean();
                $this->json(['error' => 'Agent not found'], 404);
                return;
            }

            // One review per user per agent
            $dup = $this->db->prepare(
                "SELECT id FROM reviews WHERE agent_id = ? AND reviewer_id = ? LIMIT 1"
            );
            $dup->execute([$agentId, $userId]);
            if ($dup->fetch()) {
                ob_end_clean();
                $this->json(['error' => 'You have already reviewed this agent'], 409);
                return;
            }

            $stmt = $this->db->prepare(
                "INSERT INTO reviews (agent_id, reviewer_id, rating, comment, created_at)
                 VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$agentId, $userId, $rating, $comment]);
            $reviewId = (int)$this->db->lastInsertId();

            ob_end_clean();
            $this->json(['id' => $reviewId, 'message' => 'Review submitted successfully'], 201);

        } catch (Throwable $e) {
            ob_end_clean();
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── GET /public/agents/:id/reviews ────────────────────────────────────────
    // Called from AgentDetails to show reviews list
    public function agentReviews(array $params): void {
        ob_start();
        try {
            $agentId = (int)($params['id'] ?? 0);
            if ($agentId <= 0) {
                ob_end_clean();
                $this->json(['error' => 'Invalid agent ID'], 400);
                return;
            }

            $stmt = $this->db->prepare(
                "SELECT
                    r.id, r.rating, r.comment, r.created_at,
                    u.name       AS reviewer_name,
                    u.avatar_url AS reviewer_avatar
                 FROM reviews r
                 JOIN users u ON u.id = r.reviewer_id
                 WHERE r.agent_id = ?
                 ORDER BY r.created_at DESC
                 LIMIT 50"
            );
            $stmt->execute([$agentId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$r) {
                if (!empty($r['reviewer_avatar']) && str_starts_with($r['reviewer_avatar'], '/uploads/')) {
                    $r['reviewer_avatar'] = 'http://localhost:8000' . $r['reviewer_avatar'];
                }
                $r['rating'] = (int)$r['rating'];
            }
            unset($r);

            ob_end_clean();
            $this->json(['data' => $rows, 'total' => count($rows)]);

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