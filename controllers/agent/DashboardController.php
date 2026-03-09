<?php
require_once __DIR__ . '/../../models/agent/Listing.php';
require_once __DIR__ . '/../../models/agent/Notification.php';
require_once __DIR__ . '/../../models/agent/Lead.php';

class DashboardController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    // GET /agent/dashboard
    public function index() {
        $userId = $this->user['id'];

        $notificationModel = new Notification($this->db);
        $leadModel         = new Lead($this->db);

        // ── listings ──────────────────────────────────────────────────────────
        $stmt = $this->db->prepare(
            "SELECT
                l.id, l.title, l.price, l.transaction_type, l.property_type,
                l.city, l.region, l.status, l.submitted_at,
                (SELECT lp.photo_url FROM listing_photos lp
                 WHERE lp.listing_id = l.id AND lp.is_cover = 1
                 LIMIT 1) AS cover_photo
             FROM listings l
             WHERE l.user_id = ?
             ORDER BY l.submitted_at DESC"
        );
        $stmt->execute([$userId]);
        $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalListings   = count($listings);
        $activeListings  = count(array_filter($listings, fn($l) => $l['status'] === 'approved'));
        $pendingListings = count(array_filter($listings, fn($l) => $l['status'] === 'pending'));
        $recentListings  = array_slice($listings, 0, 5);

        // ── total views ───────────────────────────────────────────────────────
        $stmt = $this->db->prepare(
            "SELECT COUNT(vh.id) FROM view_history vh
             JOIN listings l ON vh.listing_id = l.id
             WHERE l.user_id = ?"
        );
        $stmt->execute([$userId]);
        $totalViews = (int) $stmt->fetchColumn();

        // ── total leads (inquiries sent TO this agent) ────────────────────────
        $stmt = $this->db->prepare(
            "SELECT COUNT(i.id) FROM inquiries i
             WHERE i.to_user_id = ?"
        );
        $stmt->execute([$userId]);
        $totalLeads = (int) $stmt->fetchColumn();

        // ── unread counts ─────────────────────────────────────────────────────
        $unreadNotifications = $notificationModel->getUnreadCount($userId);
        $unreadLeads         = $leadModel->getUnreadCount($userId);

        jsonResponse([
            'totalListings'       => $totalListings,
            'activeListings'      => $activeListings,
            'pendingListings'     => $pendingListings,
            'totalViews'          => $totalViews,
            'totalLeads'          => $totalLeads,
            'unreadNotifications' => $unreadNotifications,
            'unreadLeads'         => $unreadLeads,
            'recentListings'      => $recentListings,
        ]);
    }
}