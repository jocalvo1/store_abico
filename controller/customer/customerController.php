<?php
require_once __DIR__ . '/../../includes/database.php';

class customerController {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    public function getAll() {
        $query = "SELECT 
                    c.*, 
                    COALESCE(SUM(CASE WHEN sd.total_amount > sd.amount_paid THEN 1 ELSE 0 END), 0) AS open_invoices,
                    COALESCE(SUM(GREATEST(sd.total_amount - sd.amount_paid, 0)), 0) AS total_remaining,
                    MIN(CASE WHEN sd.total_amount > sd.amount_paid AND sd.due_date IS NOT NULL THEN sd.due_date END) AS next_due_date
                  FROM customers c
                  LEFT JOIN sales_debts sd 
                    ON sd.customer_id = c.id 
                   AND sd.status IN ('unpaid','partial')
                  GROUP BY c.id
                  ORDER BY c.name ASC";
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

    public function search($term) {
        $search = "%$term%";
        $sql = "SELECT 
                    c.*, 
                    COALESCE(SUM(CASE WHEN sd.total_amount > sd.amount_paid THEN 1 ELSE 0 END), 0) AS open_invoices,
                    COALESCE(SUM(GREATEST(sd.total_amount - sd.amount_paid, 0)), 0) AS total_remaining,
                    MIN(CASE WHEN sd.total_amount > sd.amount_paid AND sd.due_date IS NOT NULL THEN sd.due_date END) AS next_due_date
                FROM customers c
                LEFT JOIN sales_debts sd 
                  ON sd.customer_id = c.id 
                 AND sd.status IN ('unpaid','partial')
                WHERE c.name LIKE ? OR c.contact LIKE ? OR c.address LIKE ?
                GROUP BY c.id
                ORDER BY c.name ASC";
        $stmt = $this->conn->prepare($sql);
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
