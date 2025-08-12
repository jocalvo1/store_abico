<?php
require_once __DIR__ . '/../../includes/database.php';

class ProductController {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    public function getAll() {
        $sql = "SELECT id, name, description, category, unit, current_stock, reorder_level, selling_price 
                FROM items 
                ORDER BY name ASC";
        return $this->conn->query($sql);
    }

    public function search($searchTerm) {
        $search = "%$searchTerm%";
        $sql = "SELECT id, name, description, category, unit, current_stock, reorder_level, selling_price 
                FROM items 
                WHERE name LIKE ? OR description LIKE ? OR category LIKE ?
                ORDER BY name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sss", $search, $search, $search);
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
        // Explicitly set SKU to NULL to be compatible with schemas where SKU might be NOT NULL without a default
        $sql = "INSERT INTO items (name, sku, description, category, unit, current_stock, reorder_level, selling_price) 
                VALUES (?, NULL, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssssddd", 
            $data['name'], 
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
        // Explicitly set SKU to NULL to reflect temporary removal
        $sql = "UPDATE items 
                SET name = ?, sku = NULL, description = ?, category = ?, 
                    unit = ?, current_stock = ?, reorder_level = ?, 
                    selling_price = ?, updated_at = NOW() 
                WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssssdddi", 
            $data['name'], 
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

    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>
