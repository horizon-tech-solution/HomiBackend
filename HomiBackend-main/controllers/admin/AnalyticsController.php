<?php
class AnalyticsController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function setAdmin($admin) {
        // not needed for analytics
    }

    public function index() {
        // Fetch growth data, city data, etc. from database
        $growth = $this->getGrowthData();
        $cityData = $this->getCityData();
        $typeData = $this->getTypeData();
        $priceRent = $this->getPriceRent();
        $moderation = $this->getModerationData();
        $funnel = $this->getFunnelData();
        $heatmap = $this->getHeatmap();
        $topAgents = $this->getTopAgents();

        jsonResponse([
            'growth' => $growth,
            'city' => $cityData,
            'types' => $typeData,
            'price_rent' => $priceRent,
            'moderation' => $moderation,
            'funnel' => $funnel,
            'heatmap' => $heatmap,
            'top_agents' => $topAgents
        ]);
    }

    private function getGrowthData() {
        // Example: group by month
        $stmt = $this->db->query("
            SELECT DATE_FORMAT(created_at, '%b') as month,
                   COUNT(*) as users,
                   SUM(CASE WHEN role='agent' THEN 1 ELSE 0 END) as agents,
                   (SELECT COUNT(*) FROM listings WHERE status='approved') as listings,
                   (SELECT COUNT(*) FROM inquiries) as inquiries
            FROM users
            GROUP BY MONTH(created_at)
            ORDER BY created_at ASC
            LIMIT 8
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getCityData() {
        $stmt = $this->db->query("SELECT city, COUNT(*) as listings, (SELECT COUNT(*) FROM inquiries i JOIN listings l ON i.listing_id = l.id WHERE l.city = listings.city) as inquiries FROM listings GROUP BY city");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTypeData() {
        $stmt = $this->db->query("SELECT property_type as name, COUNT(*) as value FROM listings GROUP BY property_type");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getPriceRent() {
        // custom ranges
        return [
            ['range' => '< 50k', 'count' => 184],
            ['range' => '50–100k', 'count' => 298],
            // ...
        ];
    }

    private function getModerationData() {
        $stmt = $this->db->query("SELECT DATE_FORMAT(submitted_at, '%b') as month, COUNT(*) as total, SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved, SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected, SUM(CASE WHEN fraud_signals IS NOT NULL THEN 1 ELSE 0 END) as flagged FROM listings GROUP BY MONTH(submitted_at)");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getFunnelData() {
        // This would need real data
        return [
            ['stage' => 'Submitted', 'value' => 1390, 'pct' => 100],
            ['stage' => 'Approved', 'value' => 1284, 'pct' => 92],
            // ...
        ];
    }

    private function getHeatmap() {
        // dummy data
        return [
            [12, 38, 45, 52, 61, 28, 15],
            [18, 42, 58, 71, 68, 31, 19],
            [22, 51, 72, 84, 79, 36, 21],
            [28, 63, 89, 102, 94, 44, 27],
        ];
    }

    private function getTopAgents() {
        $stmt = $this->db->query("SELECT u.name, u.agency_name as agency, u.listings_count as listings, (SELECT COUNT(*) FROM inquiries i JOIN listings l ON i.listing_id = l.id WHERE l.user_id = u.id) as inquiries, (SELECT AVG(rating) FROM reviews WHERE agent_id = u.id) as rating FROM users u WHERE role='agent' AND verification_status='verified' ORDER BY listings_count DESC LIMIT 5");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}