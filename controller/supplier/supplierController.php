<?php

require_once __DIR__ . '/../../includes/database.php';

class SupplierController {
    private $conn;
    private $table = 'suppliers';
    /**
     * @var mysqli Database connection
     */

    public function __construct() {
        $this->conn = getDBConnection();
        
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    // Get all suppliers
    public function getAll() {
        $query = 'SELECT * FROM ' . $this->table . ' ORDER BY name ASC';
        $result = $this->conn->query($query);
        
        if ($result === false) {
            error_log("Error in getAll(): " . $this->conn->error);
            return new class {
                public function fetch() { return false; }
                public function rowCount() { return 0; }
            };
        }
        
        $suppliers = [];
        while ($row = $result->fetch_assoc()) {
            $suppliers[] = $row;
        }
        
        return new class($suppliers) {
            private $suppliers;
            private $position = 0;
            
            public function __construct($suppliers) {
                $this->suppliers = $suppliers;
            }
            
            public function fetch() {
                return $this->suppliers[$this->position++] ?? false;
            }
            
            public function rowCount() {
                return count($this->suppliers);
            }
        };
    }

    // Get single supplier by ID
    public function getById($id) {
        $query = 'SELECT * FROM ' . $this->table . ' WHERE id = ? LIMIT 1';
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // Create new supplier
    public function create($data) {
        $query = 'INSERT INTO ' . $this->table . ' 
                SET 
                    name = ?,
                    contact_person = ?,
                    contact_number = ?,
                    email = ?,
                    address = ?';

        $stmt = $this->conn->prepare($query);
        if ($stmt === false) {
            error_log("Prepare failed: " . $this->conn->error);
            return false;
        }

        // Sanitize data
        $name = htmlspecialchars(strip_tags($data['name']));
        $contact_person = htmlspecialchars(strip_tags($data['contact_person'] ?? ''));
        $contact_number = htmlspecialchars(strip_tags($data['contact_number'] ?? ''));
        $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $address = htmlspecialchars(strip_tags($data['address'] ?? ''));

        // Bind parameters
        $stmt->bind_param('sssss', 
            $name,
            $contact_person,
            $contact_number,
            $email,
            $address
        );

        if ($stmt->execute()) {
            return $stmt->insert_id;
        } else {
            error_log("Execute failed: " . $stmt->error);
            return false;
        }
    }

    // Update supplier
    public function update($id, $data) {
        $query = 'UPDATE ' . $this->table . ' 
                SET 
                    name = ?,
                    contact_person = ?,
                    contact_number = ?,
                    email = ?,
                    address = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?';

        $stmt = $this->conn->prepare($query);
        if ($stmt === false) {
            error_log("Prepare failed: " . $this->conn->error);
            return false;
        }

        // Sanitize data
        $name = htmlspecialchars(strip_tags($data['name']));
        $contact_person = htmlspecialchars(strip_tags($data['contact_person'] ?? ''));
        $contact_number = htmlspecialchars(strip_tags($data['contact_number'] ?? ''));
        $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $address = htmlspecialchars(strip_tags($data['address'] ?? ''));

        // Bind parameters
        $stmt->bind_param('sssssi', 
            $name,
            $contact_person,
            $contact_number,
            $email,
            $address,
            $id
        );

        $result = $stmt->execute();
        if (!$result) {
            error_log("Update failed: " . $stmt->error);
        }
        return $result;
    }

    // Delete supplier
    public function delete($id) {
        $query = 'DELETE FROM ' . $this->table . ' WHERE id = ?';
        $stmt = $this->conn->prepare($query);
        if ($stmt === false) {
            error_log("Prepare failed: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param('i', $id);
        $result = $stmt->execute();
        if (!$result) {
            error_log("Delete failed: " . $stmt->error);
        }
        return $result;
    }

    // Search suppliers
    public function search($searchTerm) {
        $query = 'SELECT * FROM ' . $this->table . ' 
                 WHERE name LIKE ? 
                 OR contact_person LIKE ? 
                 OR email LIKE ? 
                 OR contact_number LIKE ?
                 ORDER BY name ASC';
        
        $stmt = $this->conn->prepare($query);
        if ($stmt === false) {
            error_log("Prepare failed: " . $this->conn->error);
            return new class {
                public function fetch() { return false; }
                public function rowCount() { return 0; }
            };
        }
        
        $searchTerm = "%$searchTerm%";
        $stmt->bind_param('ssss', $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        
        if (!$stmt->execute()) {
            error_log("Search failed: " . $stmt->error);
            return new class {
                public function fetch() { return false; }
                public function rowCount() { return 0; }
            };
        }
        
        $result = $stmt->get_result();
        $suppliers = [];
        while ($row = $result->fetch_assoc()) {
            $suppliers[] = $row;
        }
        
        return new class($suppliers) {
            private $suppliers;
            private $position = 0;
            
            public function __construct($suppliers) {
                $this->suppliers = $suppliers;
            }
            
            public function fetch() {
                return $this->suppliers[$this->position++] ?? false;
            }
            
            public function rowCount() {
                return count($this->suppliers);
            }
        };
    }
}
?>
