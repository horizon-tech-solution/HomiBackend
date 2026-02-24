<?php
require_once __DIR__ . '../../models/Settings.php';
require_once __DIR__ . '../../models/ActivityLog.php';

class SettingsController {
    private $db;
    private $admin;
    private $settingsModel;
    private $logModel;

    public function __construct($db) {
        $this->db = $db;
        $this->settingsModel = new Settings($db);
        $this->logModel = new ActivityLog($db);
    }

    public function setAdmin($admin) {
        $this->admin = $admin;
    }

    public function index() {
        $settings = $this->settingsModel->getAll();
        jsonResponse($settings);
    }

    public function update() {
        $input = getJsonInput();
        // input should be an object with key-value pairs
        if ($this->settingsModel->updateMany($input)) {
            $this->logModel->create('settings_updated', $this->admin['name'], 'Platform settings', json_encode($input), 'settings');
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Update failed'], 500);
        }
    }
}