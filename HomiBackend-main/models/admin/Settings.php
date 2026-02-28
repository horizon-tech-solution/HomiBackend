<?php
class Settings {
    private $conn;
    private $table = 'settings';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $stmt = $this->conn->query("SELECT setting_key, setting_value, type FROM {$this->table}");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach ($rows as $row) {
            $value = $row['setting_value'];
            if ($row['type'] === 'boolean') {
                $value = (bool) $value;
            } elseif ($row['type'] === 'number') {
                $value = (int) $value;
            } elseif ($row['type'] === 'json') {
                $value = json_decode($value, true);
            }
            $settings[$row['setting_key']] = $value;
        }
        return $settings;
    }

    public function update($key, $value) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET setting_value = :value WHERE setting_key = :key");
        return $stmt->execute([':key' => $key, ':value' => $value]);
    }

    public function updateMany($settings) {
        $success = true;
        foreach ($settings as $key => $value) {
            if (!$this->update($key, $value)) {
                $success = false;
            }
        }
        return $success;
    }
}