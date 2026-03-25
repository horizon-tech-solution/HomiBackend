<?php
namespace App\Controllers\Public;

class HealthController {
    public function index() {
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode([
            'status' => 'ok',
            'timestamp' => time(),
            'service' => 'HOMi '
        ]);
        exit;
    }
}