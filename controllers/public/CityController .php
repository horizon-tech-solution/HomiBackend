<?php
// controllers/public/CityController.php
// GET /public/cities
// Returns distinct cities + regions from approved listings,
// plus their approximate centre coordinates.

class CityController {

    // Known coordinates for Cameroonian cities.
    // Grows automatically — any city not listed here gets null coords
    // and the frontend falls back to Nominatim.
    private array $coords = [
        'douala'        => ['lat' =>  4.0511, 'lng' =>  9.7679],
        'yaoundé'       => ['lat' =>  3.8480, 'lng' => 11.5021],
        'yaounde'       => ['lat' =>  3.8480, 'lng' => 11.5021],
        'bafoussam'     => ['lat' =>  5.4737, 'lng' => 10.4175],
        'garoua'        => ['lat' =>  9.3017, 'lng' => 13.3970],
        'bamenda'       => ['lat' =>  5.9597, 'lng' => 10.1460],
        'maroua'        => ['lat' => 10.5910, 'lng' => 14.3159],
        'ngaoundéré'    => ['lat' =>  7.3236, 'lng' => 13.5840],
        'ngaoundere'    => ['lat' =>  7.3236, 'lng' => 13.5840],
        'bertoua'       => ['lat' =>  4.5769, 'lng' => 13.6864],
        'ebolowa'       => ['lat' =>  2.9000, 'lng' => 11.1500],
        'kribi'         => ['lat' =>  2.9395, 'lng' =>  9.9086],
        'limbe'         => ['lat' =>  4.0167, 'lng' =>  9.2000],
        'buea'          => ['lat' =>  4.1527, 'lng' =>  9.2408],
        'kumba'         => ['lat' =>  4.6363, 'lng' =>  9.4469],
        'edéa'          => ['lat' =>  3.8003, 'lng' => 10.1276],
        'edea'          => ['lat' =>  3.8003, 'lng' => 10.1276],
        'nkongsamba'    => ['lat' =>  4.9528, 'lng' =>  9.9342],
        'loum'          => ['lat' =>  4.7167, 'lng' =>  9.7333],
        'dschang'       => ['lat' =>  5.4430, 'lng' => 10.0490],
        'foumban'       => ['lat' =>  5.7267, 'lng' => 10.9072],
        'sangmélima'    => ['lat' =>  2.9372, 'lng' => 11.9742],
        'sangmelima'    => ['lat' =>  2.9372, 'lng' => 11.9742],
        'tibati'        => ['lat' =>  6.4680, 'lng' => 12.6292],
        'meiganga'      => ['lat' =>  6.5197, 'lng' => 14.2942],
        'kousséri'      => ['lat' => 12.0762, 'lng' => 15.0306],
        'kousseri'      => ['lat' => 12.0762, 'lng' => 15.0306],
    ];

    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    // GET /public/cities
    public function index(): void {
        // Cities that actually have approved listings
        $stmt = $this->db->query("
            SELECT
                city,
                region,
                COUNT(*) AS listing_count
            FROM listings
            WHERE status = 'approved'
            GROUP BY city, region
            ORDER BY listing_count DESC, city ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $key    = strtolower(trim($row['city']));
            $coords = $this->coords[$key] ?? null;
            $result[] = [
                'city'          => $row['city'],
                'region'        => $row['region'],
                'listing_count' => (int)$row['listing_count'],
                'lat'           => $coords ? $coords['lat'] : null,
                'lng'           => $coords ? $coords['lng'] : null,
            ];
        }

        header('Content-Type: application/json');
        echo json_encode(['data' => $result]);
    }
}