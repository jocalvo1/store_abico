<?php

require_once __DIR__ . '/../../includes/database.php';

class SalesController {
    private $conn;
    private $table = 'sales_transactions';

    public function __construct() {
        $this->conn = getDBConnection();
        
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }
    
    /**
     * Process a new sale transaction
     * @param array $saleData Array containing sale information
     * @return array Result with success status and message/sale_id
     */
    public function processSale($saleData) {
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            // Calculate total amount
            $totalAmount = 0;
            foreach ($saleData['items'] as $item) {
                $totalAmount += $item['total_price'];
            }
            
            // 1. Create sales transaction
            $transactionData = [
                'customer_id' => $saleData['customer_id'],
                'payment_method_id' => $saleData['payment_method_id'],
                'total_amount' => $totalAmount,
                'status' => $saleData['status'],
                'created_by_user_id' => $saleData['created_by_user_id'],
                'notes' => $saleData['notes'] ?? '',
                'transaction_date' => date('Y-m-d H:i:s')
            ];
            
            // Insert sales transaction
            $stmt = $this->conn->prepare("INSERT INTO sales_transactions 
                (customer_id, payment_method_id, total_amount, status, created_by_user_id, notes, transaction_date)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
                
            $stmt->bind_param("iidsiss", 
                $transactionData['customer_id'],
                $transactionData['payment_method_id'],
                $transactionData['total_amount'],
                $transactionData['status'],
                $transactionData['created_by_user_id'],
                $transactionData['notes'],
                $transactionData['transaction_date']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create sales transaction: " . $stmt->error);
            }
            
            $saleId = $this->conn->insert_id;
            
            // 2. Add sale items and update inventory
            $itemStmt = $this->conn->prepare("INSERT INTO sales_transaction_items 
                (sales_transaction_id, item_id, quantity, unit_price, notes)
                VALUES (?, ?, ?, ?, ?)");
            
            // Update inventory and record stock movements
            $stockStmt = $this->conn->prepare("UPDATE items SET current_stock = current_stock - ? WHERE id = ?");
            $movementStmt = $this->conn->prepare("INSERT INTO stock_movements 
                (item_id, movement_type, quantity, reference_type, reference_id, notes)
                VALUES (?, 'out', ?, 'sales', ?, ?)");
            
            foreach ($saleData['items'] as $item) {
                // Add sale item
                $itemStmt->bind_param("iiids", 
                    $saleId,
                    $item['item_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    '' // notes
                );
                
                if (!$itemStmt->execute()) {
                    throw new Exception("Failed to add sale item: " . $itemStmt->error);
                }
                
                // Update inventory
                $stockStmt->bind_param("di", $item['quantity'], $item['item_id']);
                if (!$stockStmt->execute()) {
                    throw new Exception("Failed to update inventory: " . $stockStmt->error);
                }
                
                // Record stock movement
                $movementStmt->bind_param("idis", 
                    $item['item_id'],
                    $item['quantity'],
                    $saleId,
                    'Sale #' . $saleId
                );
                
                if (!$movementStmt->execute()) {
                    throw new Exception("Failed to record stock movement: " . $movementStmt->error);
                }
            }
            
            // 3. If this is a credit sale, create a debt record
            if (in_array($saleData['status'], ['debt', 'partial'])) {
                $debtData = [
                    'sales_transaction_id' => $saleId,
                    'customer_id' => $saleData['customer_id'],
                    'total_amount' => $totalAmount,
                    'amount_paid' => $saleData['amount_paid'] ?? 0,
                    'due_date' => $saleData['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
                    'status' => $saleData['status'] === 'partial' ? 'partial' : 'unpaid',
                    'notes' => $saleData['notes'] ?? ''
                ];
                
                $debtStmt = $this->conn->prepare("INSERT INTO sales_debts 
                    (sales_transaction_id, customer_id, total_amount, amount_paid, due_date, status, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                    
                $debtStmt->bind_param("iiddsss",
                    $debtData['sales_transaction_id'],
                    $debtData['customer_id'],
                    $debtData['total_amount'],
                    $debtData['amount_paid'],
                    $debtData['due_date'],
                    $debtData['status'],
                    $debtData['notes']
                );
                
                if (!$debtStmt->execute()) {
                    throw new Exception("Failed to create debt record: " . $debtStmt->error);
                }
                
                // If this was a partial payment, record the payment
                if ($saleData['status'] === 'partial' && $debtData['amount_paid'] > 0) {
                    $paymentData = [
                        'sales_debt_id' => $this->conn->insert_id,
                        'payment_date' => date('Y-m-d H:i:s'),
                        'payment_method_id' => $saleData['payment_method_id'],
                        'amount' => $debtData['amount_paid'],
                        'received_by_user_id' => $saleData['created_by_user_id'],
                        'notes' => 'Initial partial payment',
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $paymentStmt = $this->conn->prepare("INSERT INTO payment_records 
                        (sales_debt_id, payment_date, payment_method_id, amount, received_by_user_id, notes, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                        
                    $paymentStmt->bind_param("isidiis",
                        $paymentData['sales_debt_id'],
                        $paymentData['payment_date'],
                        $paymentData['payment_method_id'],
                        $paymentData['amount'],
                        $paymentData['received_by_user_id'],
                        $paymentData['notes'],
                        $paymentData['created_at']
                    );
                    
                    if (!$paymentStmt->execute()) {
                        throw new Exception("Failed to record payment: " . $paymentStmt->error);
                    }
                }
            }
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'success' => true,
                'sale_id' => $saleId,
                'message' => 'Sale processed successfully.'
            ];
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->conn->rollback();
            error_log("Sale processing error: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    // Get all sales transactions
    public function getAll() {
        $query = 'SELECT st.*, c.name as customer_name, pm.name as payment_method_name, u.name as created_by_name 
                 FROM ' . $this->table . ' st 
                 LEFT JOIN customers c ON st.customer_id = c.id 
                 LEFT JOIN payment_methods pm ON st.payment_method_id = pm.id 
                 LEFT JOIN users u ON st.created_by_user_id = u.id 
                 ORDER BY st.transaction_date DESC';
        
        $result = $this->conn->query($query);
        
        if ($result === false) {
            error_log("Error in getAll(): " . $this->conn->error);
            return $this->createEmptyResult();
        }
        
        return $this->createResultObject($result);
    }

    // Search sales transactions
    public function search($searchTerm) {
        $searchTerm = $this->conn->real_escape_string($searchTerm);
        
        $query = "SELECT st.*, c.name as customer_name, pm.name as payment_method_name, u.name as created_by_name 
                 FROM " . $this->table . " st 
                 LEFT JOIN customers c ON st.customer_id = c.id 
                 LEFT JOIN payment_methods pm ON st.payment_method_id = pm.id 
                 LEFT JOIN users u ON st.created_by_user_id = u.id 
                 WHERE st.id LIKE '%$searchTerm%' 
                 OR c.name LIKE '%$searchTerm%' 
                 OR pm.name LIKE '%$searchTerm%' 
                 OR st.notes LIKE '%$searchTerm%' 
                 ORDER BY st.transaction_date DESC";
        
        $result = $this->conn->query($query);
        
        if ($result === false) {
            error_log("Error in search(): " . $this->conn->error);
            return $this->createEmptyResult();
        }
        
        return $this->createResultObject($result);
    }

    // Get a single sale by ID
    public function getById($id) {
        $id = (int)$this->conn->real_escape_string($id);
        
        $query = 'SELECT st.*, c.name as customer_name, c.contact, c.address, 
                 pm.name as payment_method_name, u.name as created_by_name 
                 FROM ' . $this->table . ' st 
                 LEFT JOIN customers c ON st.customer_id = c.id 
                 LEFT JOIN payment_methods pm ON st.payment_method_id = pm.id 
                 LEFT JOIN users u ON st.created_by_user_id = u.id 
                 WHERE st.id = ' . $id;
        
        $result = $this->conn->query($query);
        
        if ($result === false || $result->num_rows === 0) {
            return null;
        }
        
        return $result->fetch_assoc();
    }

    // Get sale items
    public function getSaleItems($saleId) {
        $saleId = (int)$this->conn->real_escape_string($saleId);
        
        $query = 'SELECT sti.*, i.name as item_name, i.sku, i.unit 
                 FROM sales_transaction_items sti 
                 JOIN items i ON sti.item_id = i.id 
                 WHERE sti.sales_transaction_id = ' . $saleId;
        
        $result = $this->conn->query($query);
        
        if ($result === false) {
            return [];
        }
        
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        
        return $items;
    }

    // Create a new sale
    public function create($data) {
        $this->conn->begin_transaction();
        
        try {
            // Insert sale transaction
            $query = 'INSERT INTO ' . $this->table . ' 
                     (customer_id, payment_method_id, total_amount, status, created_by_user_id, notes) 
                     VALUES (?, ?, ?, ?, ?, ?)';
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param(
                'iidsis', 
                $data['customer_id'],
                $data['payment_method_id'],
                $data['total_amount'],
                $data['status'],
                $data['created_by_user_id'],
                $data['notes']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error creating sale: " . $stmt->error);
            }
            
            $saleId = $this->conn->insert_id;
            
            // Insert sale items
            foreach ($data['items'] as $item) {
                $this->addSaleItem($saleId, $item);
                
                // Update inventory
                $this->updateInventory($item['item_id'], -$item['quantity']);
            }
            
            // If it's a credit sale, create a debt record
            if ($data['status'] === 'debt') {
                $this->createDebt($saleId, $data);
            }
            
            $this->conn->commit();
            return $saleId;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log($e->getMessage());
            return false;
        }
    }

    // Add sale item
    private function addSaleItem($saleId, $item) {
        $query = 'INSERT INTO sales_transaction_items 
                 (sales_transaction_id, item_id, quantity, unit_price, notes) 
                 VALUES (?, ?, ?, ?, ?)';
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            'iiids', 
            $saleId,
            $item['item_id'],
            $item['quantity'],
            $item['unit_price'],
            $item['notes'] ?? ''
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error adding sale item: " . $stmt->error);
        }
        
        return $this->conn->insert_id;
    }

    // Update inventory
    private function updateInventory($itemId, $quantity) {
        $query = 'UPDATE items SET current_stock = current_stock + ? WHERE id = ?';
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $quantity, $itemId);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating inventory: " . $stmt->error);
        }
    }

    // Create debt record
    private function createDebt($saleId, $data) {
        $query = 'INSERT INTO sales_debts 
                 (sales_transaction_id, customer_id, total_amount, due_date, status) 
                 VALUES (?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?)';
        
        $status = 'unpaid';
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            'iids', 
            $saleId,
            $data['customer_id'],
            $data['total_amount'],
            $status
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating debt record: " . $stmt->error);
        }
    }

    // Helper method to create empty result object
    private function createEmptyResult() {
        return new class {
            public function fetch() { return false; }
            public function rowCount() { return 0; }
        };
    }

    // Helper method to create result object from query
    private function createResultObject($result) {
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        
        return new class($rows) {
            private $rows;
            private $position = 0;
            
            public function __construct($rows) {
                $this->rows = $rows;
            }
            
            public function fetch() {
                return $this->rows[$this->position++] ?? false;
            }
            
            public function rowCount() {
                return count($this->rows);
            }
        };
    }

    // Get payment methods
    public function getPaymentMethods() {
        $query = 'SELECT * FROM payment_methods ORDER BY name';
        $result = $this->conn->query($query);
        
        if ($result === false) {
            error_log("Error in getPaymentMethods(): " . $this->conn->error);
            return $this->createEmptyResult();
        }
        
        return $this->createResultObject($result);
    }
    
    // Get all customers
    public function getAllCustomers() {
        $query = 'SELECT * FROM customers ORDER BY name';
        $result = $this->conn->query($query);
        
        if ($result === false) {
            error_log("Error in getAllCustomers(): " . $this->conn->error);
            return $this->createEmptyResult();
        }
        
        return $this->createResultObject($result);
    }
    
    // Get all items
    public function getAllItems() {
        $query = 'SELECT * FROM items WHERE current_stock > 0 ORDER BY name';
        $result = $this->conn->query($query);
        
        if ($result === false) {
            error_log("Error in getAllItems(): " . $this->conn->error);
            return $this->createEmptyResult();
        }
        
        return $this->createResultObject($result);
    }
    
    // Get debt by sale ID
    public function getDebtBySaleId($saleId) {
        $saleId = (int)$this->conn->real_escape_string($saleId);
        
        $query = 'SELECT sd.*, c.name as customer_name, c.contact, c.address, 
                 st.total_amount as sale_total, st.payment_method_id, st.status as sale_status,
                 (SELECT COALESCE(SUM(amount), 0) FROM payment_records WHERE sales_debt_id = sd.id) as amount_paid
                 FROM sales_debts sd
                 JOIN sales_transactions st ON sd.sales_transaction_id = st.id
                 LEFT JOIN customers c ON sd.customer_id = c.id
                 WHERE sd.sales_transaction_id = ' . $saleId;
        
        $result = $this->conn->query($query);
        
        if ($result === false || $result->num_rows === 0) {
            return null;
        }
        
        $debt = $result->fetch_assoc();
        $debt['balance_due'] = $debt['total_amount'] - $debt['amount_paid'];
        
        return $debt;
    }
    
    // Get payment history for a debt
    public function getPaymentHistory($debtId) {
        $debtId = (int)$this->conn->real_escape_string($debtId);
        
        $query = 'SELECT pr.*, pm.name as payment_method_name, u.name as received_by_name
                 FROM payment_records pr
                 JOIN payment_methods pm ON pr.payment_method_id = pm.id
                 JOIN users u ON pr.received_by_user_id = u.id
                 WHERE pr.sales_debt_id = ' . $debtId . '
                 ORDER BY pr.payment_date DESC, pr.created_at DESC';
        
        $result = $this->conn->query($query);
        
        if ($result === false) {
            return [];
        }
        
        $payments = [];
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        
        return $payments;
    }
    
    // Record a payment
    public function recordPayment($data) {
        $this->conn->begin_transaction();
        
        try {
            // Insert payment record
            $query = 'INSERT INTO payment_records 
                     (sales_debt_id, payment_date, payment_method_id, amount, received_by_user_id, notes) 
                     VALUES (?, ?, ?, ?, ?, ?)';
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param(
                'isidis', 
                $data['sales_debt_id'],
                $data['payment_date'],
                $data['payment_method_id'],
                $data['amount'],
                $data['received_by_user_id'],
                $data['notes']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error recording payment: " . $stmt->error);
            }
            
            // Get the debt details
            $debtQuery = 'SELECT sd.*, st.status as sale_status,
                         (SELECT COALESCE(SUM(amount), 0) FROM payment_records WHERE sales_debt_id = sd.id) as total_paid
                         FROM sales_debts sd
                         JOIN sales_transactions st ON sd.sales_transaction_id = st.id
                         WHERE sd.id = ' . $data['sales_debt_id'];
            
            $debtResult = $this->conn->query($debtQuery);
            if ($debtResult === false || $debtResult->num_rows === 0) {
                throw new Exception("Debt record not found");
            }
            
            $debt = $debtResult->fetch_assoc();
            $newTotalPaid = $debt['total_paid'] + $data['amount'];
            $balance = $debt['total_amount'] - $newTotalPaid;
            
            // Update debt status
            $newStatus = ($balance <= 0) ? 'paid' : 'partial';
            
            $updateDebtQuery = 'UPDATE sales_debts SET status = ? WHERE id = ?';
            $stmt = $this->conn->prepare($updateDebtQuery);
            $stmt->bind_param('si', $newStatus, $data['sales_debt_id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Error updating debt status: " . $stmt->error);
            }
            
            // Update sale status
            $updateSaleQuery = 'UPDATE sales_transactions SET status = ? WHERE id = ?';
            $stmt = $this->conn->prepare($updateSaleQuery);
            $stmt->bind_param('si', $newStatus, $debt['sales_transaction_id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Error updating sale status: " . $stmt->error);
            }
            
            // If this was a check payment, record it in check_exchanges
            if ($data['payment_method_id'] == 3) { // Assuming 3 is the ID for check payments
                $this->recordCheckPayment($data, $debt);
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log($e->getMessage());
            return false;
        }
    }
    
    // Record a check payment
    private function recordCheckPayment($paymentData, $debt) {
        // This is a simplified version - you might need to collect more check details in your form
        $query = 'INSERT INTO check_exchanges 
                 (customer_id, check_number, bank_name, check_date, amount, status, received_by_user_id, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        
        $stmt = $this->conn->prepare($query);
        
        // Default values - you should collect these from a form in a real application
        $checkNumber = 'CHK' . time();
        $bankName = 'Unknown Bank';
        $checkDate = date('Y-m-d');
        $status = 'pending';
        $notes = 'Payment for Invoice #' . str_pad($debt['sales_transaction_id'], 6, '0', STR_PAD_LEFT);
        
        $stmt->bind_param(
            'isssdsis',
            $debt['customer_id'],
            $checkNumber,
            $bankName,
            $checkDate,
            $paymentData['amount'],
            $status,
            $paymentData['received_by_user_id'],
            $notes
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error recording check payment: " . $stmt->error);
        }
        
        return $this->conn->insert_id;
    }
    
    // Close connection
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
