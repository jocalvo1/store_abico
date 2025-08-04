<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

// Include required files
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../controller/delivery/DeliveryController.php';

// Get DB connection
$conn = getDBConnection();
$deliveryController = new DeliveryController($conn);

// Initialize variables
$errors = [];
$purchase_id = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0;
$po_items = [];
$purchase_order = null;

// Get purchase order details
if ($purchase_id > 0) {
    $purchase_order = $deliveryController->getPurchaseOrderById($purchase_id);
    if ($purchase_order) {
        $po_items = $deliveryController->getPurchaseOrderItemsWithRemaining($purchase_id);
    } else {
        $errors[] = "Invalid purchase order selected.";
    }
} else {
    $errors[] = "No purchase order specified.";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_delivery'])) {
    $delivery_date = $_POST['delivery_date'];
    $item_quantities = $_POST['item_quantities'] ?? [];
    
    // Validate inputs
    if (empty($delivery_date)) {
        $errors[] = "Delivery date is required.";
    }
    
    // Get all PO items (not just remaining)
    $po_items = $deliveryController->getPurchaseOrderItems($purchase_id);
    if (empty($po_items)) {
        $errors[] = "No items found in this purchase order.";
    }
    
    // Process delivery items
    $delivery_items = [];
    $has_items = false;
    $valid_items = true;
    
    // First, validate all items and quantities
    if (!empty($item_quantities)) {
        foreach ($po_items as $item) {
            $item_id = $item['id'];
            $qty = isset($item_quantities[$item_id]) ? intval($item_quantities[$item_id]) : 0;
            
            // Skip items with 0 or negative quantity
            if ($qty <= 0) {
                continue;
            }
            
            $has_items = true;
            
            // Check if quantity exceeds remaining
            if ($qty > $item['remaining_quantity']) {
                $valid_items = false;
                $errors[] = "Quantity for {$item['item_name']} exceeds remaining quantity.";
                continue;
            }
            
            // Add to delivery items with all required details
            $delivery_items[] = [
                'purchase_order_item_id' => $item_id,
                'item_id' => $item['item_id'],
                'quantity' => $qty,
                'unit_price' => $item['unit_price']
            ];
        }
    }
    
    // Validate we have at least one item to deliver
    if (!$has_items) {
        $errors[] = "Please select at least one item to deliver.";
        $valid_items = false;
    }
    
    // Validate required fields
    if (empty($delivery_date)) {
        $errors[] = "Delivery date is required.";
        $valid_items = false;
    }
        
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Insert delivery record
            $query = "
                INSERT INTO deliveries (
                    purchase_order_id, 
                    delivery_date, 
                    status,
                    created_at
                ) VALUES (?, ?, 'pending', NOW())
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param(
                'is',
                $purchase_id,
                $delivery_date
            );
            
            $stmt->execute();
            $delivery_id = $conn->insert_id;
            $stmt->close();
            
            // Insert delivery items
            $query = "
                INSERT INTO delivery_items (
                    delivery_id, 
                    purchase_order_item_id,
                    item_id,
                    quantity
                ) VALUES (?, ?, ?, ?)
            ";
            
            $stmt = $conn->prepare($query);
            
            foreach ($delivery_items as $item) {
                $stmt->bind_param(
                    'iiii',
                    $delivery_id,
                    $item['purchase_order_item_id'],
                    $item['item_id'],
                    $item['quantity']
                );
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert delivery item: " . $stmt->error);
                }
            }
            
            $stmt->close();
            
            // Update PO status if all items are delivered
            $query = "
                SELECT 
                    poi.id,
                    poi.quantity as ordered_quantity,
                    COALESCE(SUM(di.quantity), 0) as delivered_quantity
                FROM purchase_order_items poi
                LEFT JOIN delivery_items di ON poi.id = di.purchase_order_item_id
                WHERE poi.purchase_order_id = ?
                GROUP BY poi.id, poi.quantity
                HAVING ordered_quantity > delivered_quantity
                LIMIT 1
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $purchase_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            
            // If no items remaining, mark PO as completed
            if ($result->num_rows === 0) {
                $query = "
                    UPDATE purchase_orders 
                    SET status = 'completed', 
                        updated_at = NOW() 
                    WHERE id = ?
                ";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param('i', $purchase_id);
                $stmt->execute();
                $stmt->close();
            }
            
            $conn->commit();
            
            // Store success message in session
            $_SESSION['success_message'] = "Delivery created successfully.";
            
            // Redirect to view the delivery
            header("Location: view.php?id=" . $purchase_id);
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "An error occurred while saving the delivery: " . $e->getMessage();
            error_log("Delivery save failed: " . $e->getMessage());
        }
    }
}


// Include header after form processing to prevent 'headers already sent' error
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Create Delivery</h1>
        <div>
            <a href="view.php?id=<?php echo $purchase_id; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Purchase Order
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($purchase_order): ?>
        <form method="post" action="">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Delivery Information</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <h6>Purchase Order</h6>
                            <p class="mb-1"><strong>PO Number:</strong> <?php echo htmlspecialchars($purchase_order['po_number']); ?></p>
                            <p class="mb-1"><strong>Supplier:</strong> <?php echo htmlspecialchars($purchase_order['supplier_name']); ?></p>
                            <p class="mb-0"><strong>Order Date:</strong> <?php echo date('M d, Y', strtotime($purchase_order['order_date'])); ?></p>
                        </div>
                        <div class="col-md-4">
                            <h6>Order Status</h6>
                            <p class="mb-1">
                                <span class="badge bg-<?php echo $purchase_order['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($purchase_order['status']); ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="delivery_date" class="form-label">Scheduled Delivery Date</label>
                                <input type="date" class="form-control" id="delivery_date" name="delivery_date" 
                                       value="<?php echo isset($_POST['delivery_date']) ? htmlspecialchars($_POST['delivery_date']) : date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Delivery Items</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($po_items)): ?>
                            <div class="alert alert-warning m-3">
                                <i class="fas fa-exclamation-triangle"></i> All items in this purchase order have been fully delivered.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="itemsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Item</th>
                                            <th style="width: 100px;">Ordered</th>
                                            <th style="width: 100px;">Delivered</th>
                                            <th style="width: 100px;">Remaining</th>
                                            <th style="width: 150px;">Quantity to Deliver</th>
                                            <th style="width: 100px;">Unit Price</th>
                                            <th style="width: 120px;" class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total = 0;
                                        foreach ($po_items as $item): 
                                            $quantity = isset($_POST['item_quantities'][$item['id']]) 
                                                ? intval($_POST['item_quantities'][$item['id']]) 
                                                : 0;
                                            $line_total = $quantity * $item['unit_price'];
                                            $total += $line_total;
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                                    <?php if (!empty($item['item_description'])): ?>
                                                        <div class="text-muted small"><?php echo htmlspecialchars($item['item_description']); ?></div>
                                                    <?php endif; ?>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($item['unit']); ?></div>
                                                    <input type="hidden" name="item_ids[]" value="<?php echo $item['id']; ?>">
                                                </td>
                                                <td class="align-middle">
                                                    <?php echo number_format($item['ordered_quantity']); ?>
                                                </td>
                                                <td class="align-middle">
                                                    <?php echo number_format($item['delivered_quantity']); ?>
                                                </td>
                                                <td class="align-middle">
                                                    <?php echo number_format($item['remaining_quantity']); ?>
                                                </td>
                                                <td class="align-middle">
                                                    <input type="number" 
                                                        class="form-control form-control-sm quantity-input" 
                                                        name="item_quantities[<?php echo $item['id']; ?>]" 
                                                        value="<?php echo $quantity; ?>" 
                                                        min="0" 
                                                        max="<?php echo $item['remaining_quantity']; ?>" 
                                                        step="0.01"
                                                        data-unit-price="<?php echo $item['unit_price']; ?>">
                                                </td>
                                                <td class="align-middle">
                                                    ₱<?php echo number_format($item['unit_price'], 2); ?>
                                                </td>
                                                <td class="align-middle text-end line-total">
                                                    ₱<span class="line-total-amount"><?php echo number_format($line_total, 2); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th colspan="6" class="text-end">Total:</th>
                                            <th class="text-end" id="totalAmount">₱<?php echo number_format($total, 2); ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                    <a href="view.php?id=<?php echo $purchase_id; ?>" class="btn btn-outline-secondary me-md-2">
                        <i class="fas fa-arrow-left"></i> Back to Purchase Order
                    </a>
                    <?php if (!empty($po_items)): ?>
                        <button type="submit" name="save_delivery" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Delivery
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
// Add JavaScript for dynamic calculations
document.addEventListener('DOMContentLoaded', function() {
    const quantityInputs = document.querySelectorAll('.quantity-input');
    
    function calculateTotals() {
        let total = 0;
        
        quantityInputs.forEach(input => {
            const row = input.closest('tr');
            const quantity = parseFloat(input.value) || 0;
            const unitPrice = parseFloat(input.dataset.unitPrice) || 0;
            const lineTotal = quantity * unitPrice;
            
            // Update line total
            const lineTotalEl = row.querySelector('.line-total-amount');
            if (lineTotalEl) {
                lineTotalEl.textContent = lineTotal.toFixed(2);
            }
            
            // Add to total
            total += lineTotal;
        });
        
        // Update total amount
        const totalAmountEl = document.getElementById('totalAmount');
        if (totalAmountEl) {
            totalAmountEl.textContent = '₱' + total.toFixed(2);
        }
    }
    
    // Add event listeners
    quantityInputs.forEach(input => {
        input.addEventListener('input', function() {
            const max = parseFloat(this.max) || 0;
            const value = parseFloat(this.value) || 0;
            
            if (value > max) {
                this.value = max;
            } else if (value < 0) {
                this.value = 0;
            }
            
            calculateTotals();
        });
    });
    
    // Initial calculation
    calculateTotals();
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../templates/footer.php';
?>