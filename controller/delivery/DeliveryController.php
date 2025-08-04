<?php
class DeliveryController {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all deliveries
    public function getAll() {
        $query = "SELECT 
                    d.*, 
                    po.id as po_id,
                    po.po_number, 
                    po.supplier_id,
                    s.name as supplier_name,
                    (SELECT GROUP_CONCAT(
                        CONCAT(
                            i.name, 
                            ' (', 
                            di.quantity, 
                            ')'
                        ) 
                        SEPARATOR ', ' 
                    ) 
                    FROM delivery_items di
                    JOIN purchase_order_items poi ON di.purchase_order_item_id = poi.id
                    JOIN items i ON poi.item_id = i.id
                    WHERE di.delivery_id = d.id) as items_list,
                    (SELECT SUM(di.quantity * poi.unit_price) 
                     FROM delivery_items di
                     JOIN purchase_order_items poi ON di.purchase_order_item_id = poi.id
                     WHERE di.delivery_id = d.id) as total_value
                  FROM deliveries d
                  JOIN purchase_orders po ON d.purchase_order_id = po.id
                  JOIN suppliers s ON po.supplier_id = s.id
                  ORDER BY d.delivery_date DESC, d.id DESC";
        
        return $this->conn->query($query);
    }

    // Get purchase order items with remaining quantities
    public function getPurchaseOrderItemsWithRemaining($purchase_order_id) {
        $query = "
            SELECT 
                poi.id,
                poi.item_id,
                i.name as item_name,
                i.description as item_description,
                i.unit,
                poi.quantity as ordered_quantity,
                poi.unit_price,
                COALESCE(SUM(di.quantity), 0) as delivered_quantity,
                (poi.quantity - COALESCE(SUM(di.quantity), 0)) as remaining_quantity
            FROM purchase_order_items poi
            JOIN items i ON poi.item_id = i.id
            LEFT JOIN delivery_items di ON poi.id = di.purchase_order_item_id
            WHERE poi.purchase_order_id = ?
            GROUP BY poi.id
            HAVING remaining_quantity > 0
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $purchase_order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    
    // Get all items from a purchase order
    public function getPurchaseOrderItems($purchase_order_id) {
        $query = "
            SELECT 
                poi.id,
                poi.item_id,
                i.name as item_name,
                i.description as item_description,
                i.unit,
                poi.quantity as ordered_quantity,
                poi.unit_price,
                COALESCE(SUM(di.quantity), 0) as delivered_quantity,
                (poi.quantity - COALESCE(SUM(di.quantity), 0)) as remaining_quantity
            FROM purchase_order_items poi
            JOIN items i ON poi.item_id = i.id
            LEFT JOIN delivery_items di ON poi.id = di.purchase_order_item_id
            WHERE poi.purchase_order_id = ?
            GROUP BY poi.id
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $purchase_order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    
    // Get purchase order details by ID
    public function getPurchaseOrderById($id) {
        $query = "
            SELECT po.*, s.name as supplier_name, s.contact_person, s.contact_number, s.email
            FROM purchase_orders po
            JOIN suppliers s ON po.supplier_id = s.id
            WHERE po.id = ?
            LIMIT 1
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc() : null;
    }
    
    // Get purchase orders with undelivered items
    public function getPurchaseOrdersWithUndeliveredItems() {
        $query = "SELECT po.id, po.po_number, s.name as supplier_name, 
                 COUNT(DISTINCT poi.id) as undelivered_items,
                 po.order_date
                 FROM purchase_orders po
                 JOIN suppliers s ON po.supplier_id = s.id
                 JOIN purchase_order_items poi ON po.id = poi.purchase_order_id
                 LEFT JOIN (
                     SELECT di.purchase_order_item_id, SUM(di.quantity) as delivered_quantity
                     FROM delivery_items di
                     GROUP BY di.purchase_order_item_id
                 ) di ON poi.id = di.purchase_order_item_id
                 WHERE po.status != 'completed'
                 AND (di.delivered_quantity IS NULL OR poi.quantity > COALESCE(di.delivered_quantity, 0))
                 GROUP BY po.id
                 HAVING undelivered_items > 0
                 ORDER BY po.order_date DESC";
        
        $result = $this->conn->query($query);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    // Search deliveries
    public function search($searchTerm) {
        $searchTerm = "%$searchTerm%";
        $query = "SELECT d.*, po.po_number, s.name as supplier_name,
                 (SELECT GROUP_CONCAT(CONCAT(i.name, ' (', poi.quantity, ')') SEPARATOR ', ') 
                  FROM purchase_order_items poi 
                  JOIN items i ON poi.item_id = i.id 
                  WHERE poi.purchase_order_id = po.id) as items_list,
                 (SELECT SUM(poi.quantity * poi.unit_price) 
                  FROM purchase_order_items poi 
                  WHERE poi.purchase_order_id = po.id) as total_value
                 FROM deliveries d
                 JOIN purchase_orders po ON d.purchase_order_id = po.id
                 JOIN suppliers s ON po.supplier_id = s.id
                 WHERE po.po_number LIKE ? OR s.name LIKE ? OR d.delivered_by LIKE ?
                 ORDER BY d.delivery_date DESC, d.id DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        return $stmt->get_result();
    }

    // Get delivery by ID
    public function getById($id) {
        $query = "SELECT d.*, po.po_number, s.name as supplier_name, s.id as supplier_id
                 FROM deliveries d
                 JOIN purchase_orders po ON d.purchase_order_id = po.id
                 JOIN suppliers s ON po.supplier_id = s.id
                 WHERE d.id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Create new delivery with items
    public function createDelivery($data) {
        // Start transaction
        $this->conn->begin_transaction();
        
        try {
            // Insert delivery with only essential fields
            $query = "INSERT INTO deliveries (purchase_order_id, delivery_date, status) 
                     VALUES (?, ?, ?)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('iss', 
                $data['po_id'],
                $data['delivery_date'],
                $data['status']
            );
            
            if (!$stmt->execute()) {
                error_log("SQL Error: " . $stmt->error);
                error_log("Query: " . $query);
                error_log("Data: " . print_r($data, true));
                throw new Exception("Failed to create delivery: " . $stmt->error);
            }
            
            $delivery_id = $this->conn->insert_id;
            
            // Get purchase order items to get item_id for each purchase_order_item_id
            $poItemsQuery = "SELECT poi.id, poi.item_id 
                           FROM purchase_order_items poi 
                           WHERE poi.purchase_order_id = ?";
            $poItemsStmt = $this->conn->prepare($poItemsQuery);
            $poItemsStmt->bind_param('i', $data['po_id']);
            $poItemsStmt->execute();
            $poItemsResult = $poItemsStmt->get_result();
            $poItems = [];
            while ($row = $poItemsResult->fetch_assoc()) {
                $poItems[$row['id']] = $row;
            }
            $poItemsStmt->close();
            
            // Insert delivery items
            $itemQuery = "INSERT INTO delivery_items 
                         (delivery_id, purchase_order_item_id, item_id, quantity) 
                         VALUES (?, ?, ?, ?)";
            $itemStmt = $this->conn->prepare($itemQuery);
            
            foreach ($data['items'] as $item) {
                if (!isset($poItems[$item['purchase_order_item_id']])) {
                    throw new Exception("Invalid purchase order item ID: " . $item['purchase_order_item_id']);
                }
                
                $poItem = $poItems[$item['purchase_order_item_id']];
                
                $itemStmt->bind_param('iiid', 
                    $delivery_id,
                    $item['purchase_order_item_id'],
                    $poItem['item_id'],
                    $item['quantity']
                );
                
                if (!$itemStmt->execute()) {
                    throw new Exception("Failed to add delivery items: " . $itemStmt->error);
                }
            }
            
            // Check if all items are delivered
            $checkQuery = "SELECT COUNT(*) as undelivered 
                          FROM purchase_order_items 
                          WHERE purchase_order_id = ? 
                          AND (delivered_quantity IS NULL OR quantity > delivered_quantity)";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bind_param('i', $data['po_id']);
            $checkStmt->execute();
            $result = $checkStmt->get_result()->fetch_assoc();
            
            // Update purchase order status if all items are delivered
            if ($result['undelivered'] == 0) {
                $statusQuery = "UPDATE purchase_orders SET status = 'completed' WHERE id = ?";
                $statusStmt = $this->conn->prepare($statusQuery);
                $statusStmt->bind_param('i', $data['po_id']);
                
                if (!$statusStmt->execute()) {
                    throw new Exception("Failed to update purchase order status: " . $statusStmt->error);
                }
                
                $statusStmt->close();
            }
            
            // Commit transaction
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->conn->rollback();
            $errorMsg = "Delivery creation failed: " . $e->getMessage();
            error_log($errorMsg);
            return $errorMsg;
        }
    }

    // Update delivery
    public function update($id, $data) {
        $query = "UPDATE deliveries 
                 SET po_id = ?, delivery_date = ?, delivered_by = ?, 
                     status = ?, updated_at = NOW()
                 WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('isssi',
            $data['po_id'],
            $data['delivery_date'],
            $data['delivered_by'],
            $data['status'],
            $id
        );
        
        return $stmt->execute();
    }
    // Update delivery status
    public function updateStatus($id, $status, $cancellationReason = null) {
        $query = "UPDATE deliveries 
                 SET status = ?, 
                     cancellation_reason = ?,
                     cancelled_by = ?,
                     cancelled_at = IF(? = 'cancelled', NOW(), cancelled_at),
                     updated_at = NOW()
                 WHERE id = ?";
        
        $cancelledBy = ($status === 'cancelled') ? $_SESSION['user_id'] : null;
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('sssii', 
            $status, 
            $cancellationReason, 
            $cancelledBy,
            $status,
            $id
        );
        
        return $stmt->execute();
    }
    
    // Get delivery items for a specific delivery
    public function getDeliveryItems($deliveryId) {
        $query = "SELECT 
                    di.id,
                    i.id as item_id,
                    i.name as item_name,
                    i.description as item_description,
                    i.unit,
                    di.quantity,
                    poi.unit_price,
                    poi.quantity as ordered_quantity
                  FROM delivery_items di
                  JOIN purchase_order_items poi ON di.purchase_order_item_id = poi.id
                  JOIN items i ON poi.item_id = i.id
                  WHERE di.delivery_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $deliveryId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        
        return $items;
    }
    
    // Handle AJAX request for delivery items
    public function ajaxGetDeliveryItems() {
        // Check if it's an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            // Check if user is logged in
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                exit();
            }
            
            // Get delivery ID from query string
            $deliveryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            if ($deliveryId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid delivery ID']);
                exit();
            }
            
            // Get delivery items
            $items = $this->getDeliveryItems($deliveryId);
            
            if ($items === false) {
                http_response_code(404);
                echo json_encode(['error' => 'Delivery not found']);
                exit();
            }
            
            // Return items as JSON
            header('Content-Type: application/json');
            echo json_encode($items);
            exit();
        }
    }
}
?>
