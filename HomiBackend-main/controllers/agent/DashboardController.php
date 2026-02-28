<?php
require_once __DIR__ . '/../../models/admin/Listing.php';
require_once __DIR__ . '/../../models/admin/User.php';
require_once __DIR__ . '/../../models/agent/Notification.php';

class DashboardController {
    private $db;
    private $user;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setUser($user) {
        $this->user = $user;
    }

    public function index() {
        $listingModel = new Listing($this->db);
        $listings = $listingModel->getByUserId($this->user['id']);

        $totalListings = count($listings);
        $activeListings = count(array_filter($listings, fn($l) => $l['status'] === 'approved'));
        $pendingListings = count(array_filter($listings, fn($l) => $l['status'] === 'pending'));

        // Get total views (sum from listing_views table if exists, otherwise placeholder)
        $totalViews = array_sum(array_column($listings, 'views')) ?? 0;

        // Get leads (inquiries) for agent's listings
        $inquiryStmt = $this->db->prepare("SELECT COUNT(*) FROM inquiries i JOIN listings l ON i.listing_id = l.id WHERE l.user_id = ?");
        $inquiryStmt->execute([$this->user['id']]);
        $totalLeads = $inquiryStmt->fetchColumn();

        $notificationModel = new Notification($this->db);
        $unreadNotifications = $notificationModel->getUnreadCount($this->user['id']);

        jsonResponse([
            'totalListings' => $totalListings,
            'activeListings' => $activeListings,
            'pendingListings' => $pendingListings,
            'totalViews' => $totalViews,
            'totalLeads' => $totalLeads,
            'unreadNotifications' => $unreadNotifications,
            'recentListings' => array_slice($listings, 0, 5)
        ]);
    }
}