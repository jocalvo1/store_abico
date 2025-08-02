<?php
require_once __DIR__ . '/../../includes/database.php';

class customerController {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    public function getAll() {
        $query = "SELECT * FROM customers ORDER BY name ASC";
        $result = $this->conn->query($query);
        return $result;
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function create($data) {
        $stmt = $this->conn->prepare("INSERT INTO customers (name, contact, address) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $data['name'], $data['contact'], $data['address']);
        return $stmt->execute();
    }

    public function update($id, $data) {
        $stmt = $this->conn->prepare("UPDATE customers SET name = ?, contact = ?, address = ? WHERE id = ?");
        $stmt->bind_param("sssi", $data['name'], $data['contact'], $data['address'], $id);
        return $stmt->execute();
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function search($term) {
        $search = "%$term%";
        $stmt = $this->conn->prepare("SELECT * FROM customers WHERE name LIKE ? OR contact LIKE ? OR address LIKE ? ORDER BY name ASC");
        $stmt->bind_param("sss", $search, $search, $search);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>
