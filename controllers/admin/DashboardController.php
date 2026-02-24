<?php
require_once __DIR__ . '../../models/Listing.php';
require_once __DIR__ . '../../models/User.php';
require_once __DIR__ . '../../models/ActivityLog.php';

class DashboardController {
    private $db;
    private $admin;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setAdmin($admin) {
        $this->admin = $admin;
    }

    public function stats() {
        // Total listings
        $listingModel = new Listing($this->db);
        $listings = $listingModel->getAll();
        $totalListings = count($listings);
        $pendingListings = count(array_filter($listings, fn($l) => $l['status'] === 'pending'));

        // Users
        $userModel = new User($this->db);
        $users = $userModel->getAll();
        $totalUsers = count($users);
        $verifiedAgents = count(array_filter($users, fn($u) => $u['role'] === 'agent' && $u['verification_status'] === 'verified'));
        $blockedUsers = count(array_filter($users, fn($u) => $u['status'] === 'blocked'));

        // Reports
        $reportModel = new Report($this->db);
        $reports = $reportModel->getAll();
        $openReports = count(array_filter($reports, fn($r) => $r['status'] === 'open'));

        // Activity for recent
        $activityModel = new ActivityLog($this->db);
        $recentActivity = $activityModel->getAll(null, null, 8); // limit 8

        // Platform health (dummy for now)
        $health = [
            ['label' => 'Listings approved within 24h', 'pct' => 82],
            ['label' => 'Agent verifications complete', 'pct' => 91],
            ['label' => 'Reports resolved < 48h', 'pct' => 74],
            ['label' => 'Active listings vs total', 'pct' => 88],
        ];

        jsonResponse([
            'stats' => [
                ['label' => 'Total Listings', 'value' => $totalListings, 'sub' => "$pendingListings pending approval", 'trend' => '+12%', 'trendUp' => true, 'icon' => 'Building2', 'path' => '/admin/listings'],
                ['label' => 'Registered Users', 'value' => $totalUsers, 'sub' => '48 joined this week', 'trend' => '+8%', 'trendUp' => true, 'icon' => 'Users', 'path' => '/admin/users'],
                ['label' => 'Verified Agents', 'value' => $verifiedAgents, 'sub' => '6 pending review', 'trend' => '+3%', 'trendUp' => true, 'icon' => 'UserCheck', 'path' => '/admin/agents'],
                ['label' => 'Open Reports', 'value' => $openReports, 'sub' => '1 high priority', 'trend' => '-2', 'trendUp' => true, 'icon' => 'Flag', 'path' => '/admin/reports'],
                // ... more stats
            ],
            'pending' => $this->getPendingItems(),
            'activity' => $recentActivity,
            'health' => $health
        ]);
    }

    private function getPendingItems() {
        // Pending listings
        $listingStmt = $this->db->prepare("SELECT l.*, u.name as submitter_name FROM listings l JOIN users u ON l.user_id = u.id WHERE l.status = 'pending' ORDER BY l.submitted_at DESC LIMIT 5");
        $listingStmt->execute();
        $pendingListings = $listingStmt->fetchAll(PDO::FETCH_ASSOC);

        // Pending agents
        $agentStmt = $this->db->prepare("SELECT * FROM users WHERE role = 'agent' AND verification_status = 'pending' ORDER BY created_at DESC LIMIT 5");
        $agentStmt->execute();
        $pendingAgents = $agentStmt->fetchAll(PDO::FETCH_ASSOC);

        // Combine and format
        $pending = [];
        foreach ($pendingListings as $l) {
            $pending[] = [
                'id' => $l['id'],
                'title' => $l['title'],
                'meta' => "by @{$l['submitter_name']} · {$l['area']} m² · " . number_format($l['price']) . ' XAF',
                'type' => 'listing',
                'age' => $this->timeAgo($l['submitted_at'])
            ];
        }
        foreach ($pendingAgents as $a) {
            $pending[] = [
                'id' => $a['id'],
                'title' => $a['name'] . ' — Agent Application',
                'meta' => $a['agency_name'] . ' · ' . ($a['city'] ?? '') . ' · License submitted',
                'type' => 'agent',
                'age' => $this->timeAgo($a['created_at'])
            ];
        }
        usort($pending, fn($a, $b) => strtotime($b['submitted_at'] ?? $b['created_at']) - strtotime($a['submitted_at'] ?? $a['created_at']));
        return array_slice($pending, 0, 5);
    }

    public function status() {
    jsonResponse([
        'status' => 'ok',
        'message' => 'Propty API is running',
        'timestamp' => time()
    ]);
}

    private function timeAgo($timestamp) {
        $time = strtotime($timestamp);
        $diff = time() - $time;
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff/60) . 'm ago';
        if ($diff < 86400) return floor($diff/3600) . 'h ago';
        return floor($diff/86400) . 'd ago';
    }
}