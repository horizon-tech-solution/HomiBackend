<?php
require_once __DIR__ . '/../../models/admin/ActivityLog.php';

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
    $totalListings   = (int) $this->db->query("SELECT COUNT(*) FROM listings")->fetchColumn();
    $pendingListings = (int) $this->db->query("SELECT COUNT(*) FROM listings WHERE status = 'pending'")->fetchColumn();
    $approvedToday   = (int) $this->db->query("SELECT COUNT(*) FROM listings WHERE status = 'approved' AND DATE(approved_at) = CURDATE()")->fetchColumn();

    $totalUsers      = (int) $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $blockedUsers    = (int) $this->db->query("SELECT COUNT(*) FROM users WHERE status = 'blocked'")->fetchColumn();
    $verifiedAgents  = (int) $this->db->query("SELECT COUNT(*) FROM users WHERE role = 'agent' AND verification_status = 'verified'")->fetchColumn();
    $pendingAgents   = (int) $this->db->query("SELECT COUNT(*) FROM users WHERE role = 'agent' AND verification_status = 'pending'")->fetchColumn();
    $usersThisWeek   = (int) $this->db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

    $openReports     = (int) $this->db->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")->fetchColumn();
    $highPriority    = (int) $this->db->query("SELECT COUNT(*) FROM reports WHERE status = 'open' AND priority = 'high'")->fetchColumn();

    // inquiries table might be empty — use a safe fallback
    try {
        $monthlyInquiries = (int) $this->db->query("SELECT COUNT(*) FROM inquiries WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();
    } catch (Exception $e) {
        $monthlyInquiries = 0;
    }

    jsonResponse([
        'totalListings'        => $totalListings,
        'pendingListings'      => $pendingListings,
        'registeredUsers'      => $totalUsers,
        'usersThisWeek'        => $usersThisWeek,
        'verifiedAgents'       => $verifiedAgents,
        'pendingAgents'        => $pendingAgents,
        'openReports'          => $openReports,
        'highPriorityReports'  => $highPriority,
        'approvedToday'        => $approvedToday,
        'blockedAccounts'      => $blockedUsers,
        'monthlyInquiries'     => $monthlyInquiries,
        'avgResponseTimeHours' => 0,
        'listingsTrend'        => '+0%',
        'usersTrend'           => '+0%',
        'agentsTrend'          => '+0%',
        'reportsTrend'         => '+0%',
        'inquiriesTrend'       => '+0%',
    ]);
}

    public function pending($params = []) {
        $listingStmt = $this->db->prepare(
            "SELECT l.id, l.title, l.area, l.price, l.submitted_at, u.name AS submitter_name
             FROM listings l
             JOIN users u ON l.user_id = u.id
             WHERE l.status = 'pending'
             ORDER BY l.submitted_at DESC LIMIT 5"
        );
        $listingStmt->execute();

        $agentStmt = $this->db->prepare(
            "SELECT id, name, agency_name, created_at
             FROM users
             WHERE role = 'agent' AND verification_status = 'pending'
             ORDER BY created_at DESC LIMIT 5"
        );
        $agentStmt->execute();

        $pending = [];
        foreach ($listingStmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $pending[] = [
                'id'    => $l['id'],
                'title' => $l['title'],
                'meta'  => "by {$l['submitter_name']} · {$l['area']} m² · " . number_format($l['price']) . ' XAF',
                'type'  => 'listing',
                'age'   => $this->timeAgo($l['submitted_at']),
            ];
        }
        foreach ($agentStmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $pending[] = [
                'id'    => $a['id'],
                'title' => $a['name'] . ' — Agent Application',
                'meta'  => ($a['agency_name'] ?? '') . ' · License submitted',
                'type'  => 'agent',
                'age'   => $this->timeAgo($a['created_at']),
            ];
        }

        jsonResponse($pending);
    }

    public function activity($params = []) {
        $stmt = $this->db->prepare(
            "SELECT id, action, actor, target, detail, category, created_at
             FROM activity_logs
             ORDER BY created_at DESC LIMIT 10"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $activity = array_map(fn($r) => [
            'id'     => $r['id'],
            'action' => $r['action'],
            'actor'  => $r['actor'],
            'target' => $r['target'],
            'detail' => $r['detail'] ?? '',
            'text'   => $r['target'] . ' — ' . $r['detail'],
            'time'   => $r['created_at'],
            'status' => 'approved',
        ], $rows);

        jsonResponse($activity);
    }

    public function health($params = []) {
        jsonResponse([
            ['label' => 'Listings approved within 24h', 'pct' => 82],
            ['label' => 'Agent verifications complete',  'pct' => 91],
            ['label' => 'Reports resolved < 48h',        'pct' => 74],
            ['label' => 'Active listings vs total',      'pct' => 88],
        ]);
    }

    private function timeAgo($timestamp) {
        $diff = time() - strtotime($timestamp);
        if ($diff < 60)    return 'just now';
        if ($diff < 3600)  return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        return floor($diff / 86400) . 'd ago';
    }
}