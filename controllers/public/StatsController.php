<?php
// controllers/public/StatsController.php

require_once __DIR__ . '/../../models/public/HomeModel.php';

class StatsController {
    private HomeModel $model;

    public function __construct(PDO $db) {
        $this->model = new HomeModel($db);
    }

    // GET /public/stats  — index() matches route convention
    public function index(): void {
        $this->json($this->model->getPlatformStats());
    }

    // GET /public/agents  (top verified agents for homepage)
    public function topAgents(): void {
        $limit  = min((int)($_GET['limit'] ?? 3), 20);
        $agents = $this->model->getTopAgents($limit);
        $this->json(['data' => $agents]);
    }

    // GET /public/featured  (featured approved listings for homepage)
    public function featured(): void {
        $limit   = min((int)($_GET['limit'] ?? 6), 12);
        $listings = $this->model->getFeaturedListings($limit);
        $this->json(['data' => $listings]);
    }

    private function json(mixed $data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}