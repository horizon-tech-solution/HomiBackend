<?php
class StatsController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * GET /public/stats – homepage statistics
     */
    public function index() {
        $stats = [];

        // Total approved listings
        $stmt = $this->db->query("SELECT COUNT(*) FROM listings WHERE status = 'approved'");
        $stats['total_listings'] = (int)$stmt->fetchColumn();

        // New listings today
        $stmt = $this->db->query("SELECT COUNT(*) FROM listings WHERE status = 'approved' AND DATE(submitted_at) = CURDATE()");
        $stats['new_listings_today'] = (int)$stmt->fetchColumn();

        // Total verified agents
        $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE role = 'agent' AND verification_status = 'verified'");
        $stats['total_agents'] = (int)$stmt->fetchColumn();

        // Total properties sold (if you have a sales table, otherwise placeholder)
        $stats['properties_sold'] = 3500; // Placeholder

        jsonResponse($stats);
    }
}