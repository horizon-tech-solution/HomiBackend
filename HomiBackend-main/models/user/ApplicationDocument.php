<?php
class ApplicationDocument {
    private $conn;
    private $table = 'application_documents';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($applicationId, $documentType, $filePath) {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (application_id, document_type, file_path) VALUES (?, ?, ?)");
        return $stmt->execute([$applicationId, $documentType, $filePath]);
    }

    public function getByApplication($applicationId) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE application_id = ?");
        $stmt->execute([$applicationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}