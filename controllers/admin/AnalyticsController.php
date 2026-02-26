<?php
class AnalyticsController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setAdmin($admin) {}

    public function growth($params = []) {
        $range = $_GET['range'] ?? 'last_6_months';
        $months = match($range) {
            'last_30_days'  => 1,
            'last_3_months' => 3,
            'all_time'      => 24,
            default         => 6,
        };

        $stmt = $this->db->prepare("
            SELECT DATE_FORMAT(m.month, '%b %Y') AS month,
                (SELECT COUNT(*) FROM users     WHERE created_at <= LAST_DAY(m.month)) AS users,
                (SELECT COUNT(*) FROM users     WHERE role = 'agent' AND verification_status = 'verified' AND created_at <= LAST_DAY(m.month)) AS agents,
                (SELECT COUNT(*) FROM listings  WHERE status = 'approved' AND approved_at <= LAST_DAY(m.month)) AS listings,
                (SELECT COUNT(*) FROM inquiries WHERE created_at BETWEEN DATE_FORMAT(m.month, '%Y-%m-01') AND LAST_DAY(m.month)) AS inquiries
            FROM (
                SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL n MONTH), '%Y-%m-01') AS month
                FROM (
                    SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3
                    UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7
                    UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11
                    UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15
                    UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19
                    UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23
                ) nums
                WHERE n < :months
            ) m
            ORDER BY m.month ASC
        ");
        $stmt->execute([':months' => $months]);
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function cities($params = []) {
        $stmt = $this->db->query("
            SELECT l.city,
                COUNT(*) AS listings,
                COUNT(DISTINCT i.id) AS inquiries
            FROM listings l
            LEFT JOIN inquiries i ON i.listing_id = l.id
            WHERE l.status = 'approved'
            GROUP BY l.city
            ORDER BY listings DESC
            LIMIT 8
        ");
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function propertyTypes($params = []) {
        $colors = [
            'apartment'  => '#18181b',
            'house'      => '#3f3f46',
            'villa'      => '#71717a',
            'commercial' => '#a1a1aa',
            'land'       => '#d4d4d8',
            'duplex'     => '#e4e4e7',
            'other'      => '#f4f4f5',
        ];
        $stmt = $this->db->query("
            SELECT property_type AS name, COUNT(*) AS value
            FROM listings
            GROUP BY property_type
            ORDER BY value DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rows = array_map(fn($r) => [
            'name'  => ucfirst($r['name']),
            'value' => (int) $r['value'],
            'color' => $colors[$r['name']] ?? '#e4e4e7',
        ], $rows);
        jsonResponse($rows);
    }

    public function priceDistribution($params = []) {
        $stmt = $this->db->query("
            SELECT
                SUM(price < 50000)                          AS `< 50k`,
                SUM(price BETWEEN 50000  AND 100000)        AS `50–100k`,
                SUM(price BETWEEN 100001 AND 200000)        AS `100–200k`,
                SUM(price BETWEEN 200001 AND 500000)        AS `200–500k`,
                SUM(price BETWEEN 500001 AND 1000000)       AS `500k–1M`,
                SUM(price > 1000000)                        AS `> 1M`
            FROM listings
            WHERE transaction_type = 'rent' AND status = 'approved'
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($row as $range => $count) {
            $result[] = ['range' => $range, 'count' => (int) $count];
        }
        jsonResponse($result);
    }

    public function moderation($params = []) {
        $stmt = $this->db->query("
            SELECT DATE_FORMAT(submitted_at, '%b') AS month,
                SUM(status = 'approved')                    AS approved,
                SUM(status = 'rejected')                    AS rejected,
                SUM(fraud_signals IS NOT NULL
                    AND JSON_LENGTH(fraud_signals) > 0)     AS flagged
            FROM listings
            WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY YEAR(submitted_at), MONTH(submitted_at)
            ORDER BY submitted_at ASC
        ");
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function funnel($params = []) {
        $submitted = (int) $this->db->query("SELECT COUNT(*) FROM listings")->fetchColumn();
        $approved  = (int) $this->db->query("SELECT COUNT(*) FROM listings WHERE status = 'approved'")->fetchColumn();
        $inquired  = (int) $this->db->query("SELECT COUNT(DISTINCT listing_id) FROM inquiries")->fetchColumn();
        $favorited = (int) $this->db->query("SELECT COUNT(DISTINCT listing_id) FROM favorites")->fetchColumn();

        $pct = fn($n) => $submitted > 0 ? round($n / $submitted * 100) : 0;

        jsonResponse([
            ['stage' => 'Submitted', 'value' => $submitted, 'pct' => 100],
            ['stage' => 'Approved',  'value' => $approved,  'pct' => $pct($approved)],
            ['stage' => 'Favorited', 'value' => $favorited, 'pct' => $pct($favorited)],
            ['stage' => 'Inquired',  'value' => $inquired,  'pct' => $pct($inquired)],
        ]);
    }

    public function heatmap($params = []) {
        // Returns 4 weeks x 7 days of activity counts
        $result = [];
        for ($week = 3; $week >= 0; $week--) {
            $days = [];
            for ($day = 0; $day < 7; $day++) {
                $offset = $week * 7 + (6 - $day);
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM activity_logs
                    WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL :offset DAY)
                ");
                $stmt->execute([':offset' => $offset]);
                $days[] = (int) $stmt->fetchColumn();
            }
            $result[] = $days;
        }
        jsonResponse($result);
    }

    public function topAgents($params = []) {
        $stmt = $this->db->query("
            SELECT u.name,
                COALESCE(u.agency_name, 'Independent') AS agency,
                u.listings_count AS listings,
                COUNT(DISTINCT i.id) AS inquiries,
                ROUND(COALESCE(AVG(r.rating), 0), 1) AS rating
            FROM users u
            LEFT JOIN listings  l ON l.user_id = u.id AND l.status = 'approved'
            LEFT JOIN inquiries i ON i.listing_id = l.id
            LEFT JOIN reviews   r ON r.agent_id = u.id
            WHERE u.role = 'agent' AND u.verification_status = 'verified'
            GROUP BY u.id
            ORDER BY u.listings_count DESC
            LIMIT 8
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rows = array_map(fn($r) => [
            'name'      => $r['name'],
            'agency'    => $r['agency'],
            'listings'  => (int)   $r['listings'],
            'inquiries' => (int)   $r['inquiries'],
            'rating'    => $r['rating'] > 0 ? (float) $r['rating'] : '—',
        ], $rows);
        jsonResponse($rows);
    }

    public function export($params = []) {
        $range = $_GET['range'] ?? 'last_6_months';
        // Reuse growth data for export
        ob_start();
        $this->growth();
        $data = json_decode(ob_get_clean(), true);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="analytics-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Month', 'Users', 'Agents', 'Listings', 'Inquiries']);
        foreach ($data as $row) {
            fputcsv($out, [$row['month'], $row['users'], $row['agents'], $row['listings'], $row['inquiries']]);
        }
        fclose($out);
        exit;
    }
}