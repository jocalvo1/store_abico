<?php
require_once __DIR__ . '/../../includes/database.php';

class ProductController {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    public function getAll() {
        $sql = "SELECT id, name, description, category, unit, current_stock, selling_price 
                FROM items 
                ORDER BY name ASC";
        return $this->conn->query($sql);
    }

    public function search($searchTerm) {
        $search = "%$searchTerm%";
        $sql = "SELECT id, name, description, category, unit, current_stock, selling_price 
                FROM items 
                WHERE name LIKE ? OR description LIKE ? OR category LIKE ? OR sku = ?
                ORDER BY name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssss", $search, $search, $search, $searchTerm);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getById($id) {
        $sql = "SELECT * FROM items WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function create($data) {
        $sql = "INSERT INTO items (name, sku, description, category, unit, current_stock, reorder_level, selling_price) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sssssiid", 
            $data['name'], 
            $data['sku'], 
            $data['description'], 
            $data['category'], 
            $data['unit'], 
            $data['current_stock'], 
            $data['reorder_level'], 
            $data['selling_price']
        );
        return $stmt->execute();
    }

    public function update($id, $data) {
        $sql = "UPDATE items 
                SET name = ?, sku = ?, description = ?, category = ?, 
                    unit = ?, current_stock = ?, reorder_level = ?, 
                    selling_price = ?, updated_at = NOW() 
                WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sssssiidi", 
            $data['name'], 
            $data['sku'], 
            $data['description'], 
            $data['category'], 
            $data['unit'], 
            $data['current_stock'], 
            $data['reorder_level'], 
            $data['selling_price'],
            $id
        );
        return $stmt->execute();
    }

    public function delete($id) {
        $sql = "DELETE FROM items WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>
