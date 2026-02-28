<?php
require_once __DIR__ . '/../../models/admin/Listing.php';
require_once __DIR__ . '/../../models/admin/User.php';
require_once __DIR__ . '/../../models/admin/ActivityLog.php';
require_once __DIR__ . '/../../models/admin/Report.php';

class DashboardController {
    private $db;
    private $admin;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setAdmin($admin) {
        $this->admin = $admin;
    }

    public function stats($params = []) {
        $listingModel = new Listing($this->db);
        $listings = $listingModel->getAll();
        $totalListings = count($listings);
        $pendingListings = count(array_filter($listings, fn($l) => $l['status'] === 'pending'));

        $userModel = new User($this->db);
        $users = $userModel->getAll();
        $totalUsers = count($users);
        $verifiedAgents = count(array_filter($users, fn($u) => $u['role'] === 'agent' && $u['verification_status'] === 'verified'));
        $pendingAgents = count(array_filter($users, fn($u) => $u['role'] === 'agent' && $u['verification_status'] === 'pending'));
        $blockedUsers = count(array_filter($users, fn($u) => $u['status'] === 'blocked'));

        $reportModel = new Report($this->db);
        $reports = $reportModel->getAll();
        $openReports = count(array_filter($reports, fn($r) => $r['status'] === 'open'));
        $highPriority = count(array_filter($reports, fn($r) => $r['status'] === 'open' && $r['priority'] === 'high'));

        jsonResponse([
            'totalListings'        => $totalListings,
            'pendingListings'      => $pendingListings,
            'registeredUsers'      => $totalUsers,
            'usersThisWeek'        => 0,
            'verifiedAgents'       => $verifiedAgents,
            'pendingAgents'        => $pendingAgents,
            'openReports'          => $openReports,
            'highPriorityReports'  => $highPriority,
            'approvedToday'        => 0,
            'blockedAccounts'      => $blockedUsers,
            'monthlyInquiries'     => 0,
            'avgResponseTimeHours' => 0,
            'listingsTrend'        => '+0%',
            'usersTrend'           => '+0%',
            'agentsTrend'          => '+0%',
            'reportsTrend'         => '+0%',
            'inquiriesTrend'       => '+0%',
        ]);
    }

    public function pending($params = []) {
        $pending = $this->getPendingItems();
        jsonResponse($pending);
    }

    public function activity($params = []) {
        $activityModel = new ActivityLog($this->db);
        $activity = $activityModel->getAll(null, null, 10);
        jsonResponse($activity);
    }

    public function health($params = []) {
        jsonResponse([
            ['label' => 'Listings approved within 24h', 'pct' => 82],
            ['label' => 'Agent verifications complete', 'pct' => 91],
            ['label' => 'Reports resolved < 48h',       'pct' => 74],
            ['label' => 'Active listings vs total',     'pct' => 88],
        ]);
    }

    public function status($params = []) {
        jsonResponse([
            'status'    => 'ok',
            'message'   => 'Propty API is running',
            'timestamp' => time()
        ]);
    }

    private function getPendingItems() {
        $listingStmt = $this->db->prepare(
            "SELECT l.*, u.name as submitter_name 
             FROM listings l 
             JOIN users u ON l.user_id = u.id 
             WHERE l.status = 'pending' 
             ORDER BY l.submitted_at DESC LIMIT 5"
        );
        $listingStmt->execute();
        $pendingListings = $listingStmt->fetchAll(PDO::FETCH_ASSOC);

        $agentStmt = $this->db->prepare(
            "SELECT * FROM users 
             WHERE role = 'agent' AND verification_status = 'pending' 
             ORDER BY created_at DESC LIMIT 5"
        );
        $agentStmt->execute();
        $pendingAgents = $agentStmt->fetchAll(PDO::FETCH_ASSOC);

        $pending = [];
        foreach ($pendingListings as $l) {
            $pending[] = [
                'id'    => $l['id'],
                'title' => $l['title'],
                'meta'  => "by {$l['submitter_name']} · {$l['area']} m² · " . number_format($l['price']) . ' XAF',
                'type'  => 'listing',
                'age'   => $this->timeAgo($l['submitted_at']),
            ];
        }
        foreach ($pendingAgents as $a) {
            $pending[] = [
                'id'    => $a['id'],
                'title' => $a['name'] . ' — Agent Application',
                'meta'  => ($a['agency_name'] ?? '') . ' · License submitted',
                'type'  => 'agent',
                'age'   => $this->timeAgo($a['created_at']),
            ];
        }

        return $pending;
    }

    private function timeAgo($timestamp) {
        $diff = time() - strtotime($timestamp);
        if ($diff < 60)    return 'just now';
        if ($diff < 3600)  return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        return floor($diff / 86400) . 'd ago';
    }
}