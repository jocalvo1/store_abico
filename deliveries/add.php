<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Include required files
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../controller/delivery/DeliveryController.php';

// Get DB connection
$conn = getDBConnection();

// Initialize DeliveryController
$deliveryController = new DeliveryController($conn);

// Initialize variables
$errors = [];
$purchase_orders = [];
$selected_po = null;
$po_items = [];

// Get all purchase orders with undelivered items using the controller
$purchase_orders = $deliveryController->getPurchaseOrdersWithUndeliveredItems();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['select_po'])) {
        // Handle PO selection
        $po_id = intval($_POST['po_id']);
        
        // Get PO details using controller
        $selected_po = $deliveryController->getPurchaseOrderById($po_id);
        
        if ($selected_po) {
            // Get PO items with remaining quantities using controller
            $po_items = $deliveryController->getPurchaseOrderItemsWithRemaining($po_id);
            
            if (empty($po_items)) {
                $errors[] = "All items in this purchase order have already been fully delivered.";
                $selected_po = null;
            }
        } else {
            $errors[] = "Invalid purchase order selected.";
        }
    } elseif (isset($_POST['save_delivery'])) {
        // Handle delivery submission
        $po_id = intval($_POST['po_id']);
        $delivery_date = $_POST['delivery_date'];
        $item_quantities = $_POST['item_quantities'] ?? [];
        
        // Validate inputs
        if (empty($delivery_date)) {
            $errors[] = "Delivery date is required.";
        }
        
        // Get PO details
        $selected_po = $deliveryController->getPurchaseOrderById($po_id);
        if (!$selected_po) {
            $errors[] = "Invalid purchase order selected.";
        }
        
        // Get all PO items (not just remaining)
        $po_items = $deliveryController->getPurchaseOrderItems($po_id);
        if (empty($po_items)) {
            $errors[] = "No items found in this purchase order.";
        }
        
        // Process delivery items
        $delivery_items = [];
        $has_items = false;
        
        if (!empty($item_quantities)) {
            foreach ($item_quantities as $item_id => $quantity) {
                $quantity = intval($quantity);
                if ($quantity > 0) {
                    $has_items = true;
                    $item_id = intval($item_id);
                    
                    // Find the item details from the PO items
                    $item_details = null;
                    foreach ($po_items as $po_item) {
                        if ($po_item['id'] == $item_id) {
                            $item_details = $po_item;
                            break;
                        }
                    }
                    
                    if ($item_details) {
                        $delivery_items[] = [
                            'purchase_order_item_id' => $item_id,
                            'item_id' => $item_details['item_id'],
                            'quantity' => $quantity,
                            'unit_price' => $item_details['unit_price']
                        ];
                    } else {
                        $errors[] = "Could not find item details for purchase order item ID: $item_id";
                        break;
                    }
                }
            }
        }
        
        if (!$has_items) {
            $errors[] = "Please select at least one item to deliver.";
        }
        
        // If no errors, save delivery
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
                    $po_id,
                    $delivery_date,
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
                    $stmt->execute();
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
                $stmt->bind_param('i', $po_id);
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
                    $stmt->bind_param('i', $po_id);
                    $stmt->execute();
                    $stmt->close();
                }
                
                $conn->commit();
                
                // Store success message in session
                $_SESSION['success'] = "Delivery created successfully.";
                
                // Use JavaScript for redirection
                echo "<script>
                    window.location.href = 'view.php?id=$delivery_id';
                </script>";
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = "An error occurred while saving the delivery: " . $e->getMessage();
            }
        }
    }
}

// Close connection
$conn->close();
?>

<style>
/* Mobile stacked layout for delivery items table */
@media (max-width: 576px) {
  #itemsTable thead,
  #itemsTable tfoot { display: none; }

  #itemsTable,
  #itemsTable tbody,
  #itemsTable tr,
  #itemsTable td { display: block; width: 100%; }

  #itemsTable tr { border-bottom: 1px solid #e9ecef; padding: .75rem .75rem .25rem; }

  #itemsTable td { 
    border: 0 !important; 
    padding: .25rem 0 !important; 
  }

  #itemsTable td[data-label] { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    gap: .5rem; 
  }

  #itemsTable td[data-label]::before {
    content: attr(data-label);
    font-weight: 600;
    color: #6c757d;
  }

  /* Ensure the first cell (Item) shows as a block with name then details */
  #itemsTable td[data-label="Item"] { display: block; }
  #itemsTable td[data-label="Item"]::before { content: none; }

  /* Keep input group on one line if present */
  #itemsTable .input-group { flex-wrap: nowrap; }
}
</style>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Create New Delivery</h1>
                <a href="index.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 text-nowrap">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Deliveries</span>
                </a>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-triangle"></i> Please fix the following errors:</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="deliveryForm">
                <?php if (!$selected_po): ?>
                    <!-- Step 1: Select Purchase Order -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">Select Purchase Order</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="po_id" class="form-label">Purchase Order <span class="text-danger">*</span></label>
                                    <!-- Choices.js for improved select on mobile -->
                                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
                                    <style>
                                      @media (max-width: 576px) {
                                        /* Ensure select text doesn't overflow viewport */
                                        #po_id { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                                      }
                                      /* Make Choices container full width and truncate text */
                                      .choices { width: 100%; }
                                      .choices__inner { min-height: 2.4rem; }
                                      .choices__list--single .choices__item { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                                      /* Prevent dropdown from exceeding viewport on mobile */
                                      .choices__list--dropdown { max-width: 100vw; }
                                      .choices__list--dropdown .choices__item { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                                    </style>
                                    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
                                    <select class="form-select" id="po_id" name="po_id" required>
                                        <option value="">-- Select Purchase Order --</option>
                                        <?php if (!empty($purchase_orders)): ?>
                                            <?php foreach ($purchase_orders as $po): ?>
                                                <?php
                                                  $poNum = isset($po['po_number']) ? (string)$po['po_number'] : '';
                                                  $supplierName = isset($po['supplier_name']) ? (string)$po['supplier_name'] : '';
                                                  $orderDate = isset($po['order_date']) ? date('M d, Y', strtotime($po['order_date'])) : '';
                                                  $pending = isset($po['undelivered_items']) ? (int)$po['undelivered_items'] : 0;
                                                  $fullTitle = "PO#{$poNum} - {$supplierName} - {$orderDate} ({$pending} item/s pending)";
                                                ?>
                                                <option value="<?php echo $po['id']; ?>" title="<?php echo htmlspecialchars($fullTitle); ?>" <?php echo (isset($_POST['po_id']) && $_POST['po_id'] == $po['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($fullTitle); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="" disabled>No purchase orders with undelivered items found</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 text-end">
                                    <button type="submit" name="select_po" class="btn btn-primary">
                                        <i class="fas fa-arrow-right"></i> Continue
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Step 2: Enter Delivery Details -->
                    <input type="hidden" name="po_id" value="<?php echo $selected_po['id']; ?>">
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">Delivery Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <h6>Purchase Order</h6>
                                    <p class="mb-1"><strong>PO Number:</strong> <?php echo htmlspecialchars($selected_po['po_number']); ?></p>
                                    <p class="mb-1"><strong>Supplier:</strong> <?php echo htmlspecialchars($selected_po['supplier_name']); ?></p>
                                    <p class="mb-0"><strong>Order Date:</strong> <?php echo date('M d, Y', strtotime($selected_po['order_date'])); ?></p>
                                </div>
                                <div class="col-md-4">
                                    <h6>Order Status</h6>
                                    <p class="mb-1">
                                        <span class="badge bg-<?php echo $selected_po['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($selected_po['status']); ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="delivery_date" class="form-label">Scheduled Delivery Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="delivery_date" name="delivery_date" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
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
                                                        <th style="width: 120px;" class="text-end">Line Total</th>
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
                                                            <td data-label="Item">
                                                                <div class="fw-semibold"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                                                <?php if (!empty($item['item_description'])): ?>
                                                                    <div class="text-muted small"><?php echo htmlspecialchars($item['item_description']); ?></div>
                                                                <?php endif; ?>
                                                                <div class="text-muted small"><?php echo htmlspecialchars($item['unit']); ?></div>
                                                                <input type="hidden" name="item_ids[]" value="<?php echo $item['id']; ?>">
                                                            </td>
                                                            <td class="align-middle" data-label="Ordered">
                                                                <?php echo number_format($item['ordered_quantity']); ?>
                                                            </td>
                                                            <td class="align-middle" data-label="Delivered">
                                                                <?php echo number_format($item['delivered_quantity']); ?>
                                                            </td>
                                                            <td class="align-middle" data-label="Remaining">
                                                                <?php echo number_format($item['remaining_quantity']); ?>
                                                            </td>
                                                            <td class="align-middle" data-label="Quantity to Deliver">
                                                                <input type="number" 
                                                                       class="form-control form-control-sm quantity-input" 
                                                                       name="item_quantities[<?php echo $item['id']; ?>]" 
                                                                       value="<?php echo $quantity; ?>" 
                                                                       min="0" 
                                                                       max="<?php echo $item['remaining_quantity']; ?>" 
                                                                       step="0.01"
                                                                       data-unit-price="<?php echo $item['unit_price']; ?>">
                                                            </td>
                                                            <td class="align-middle" data-label="Unit Price">
                                                                ₱<?php echo number_format($item['unit_price'], 2); ?>
                                                            </td>
                                                            <td class="align-middle text-end line-total" data-label="Line Total">
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
                            
                            <div class="d-flex w-100 justify-content-center justify-content-md-end flex-wrap gap-2 mb-4">
                                <a href="add.php" class="btn btn-outline-secondary me-md-2 d-inline-flex align-items-center gap-1 text-nowrap">
                                    <i class="fas fa-arrow-left"></i>
                                    <span>Back</span>
                                </a>
                                <?php if (!empty($po_items)): ?>
                                    <button type="submit" name="save_delivery" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Delivery
                                    </button>
                                <?php endif; ?>
                            </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
<!-- Back to Top Button -->
<button type="button" id="backToTop" class="btn btn-primary rounded-circle back-to-top" aria-label="Back to top" title="Back to top">
    <i class="fas fa-arrow-up"></i>
    <span class="visually-hidden">Back to top</span>
</button>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Choices.js on PO select if available
    (function(){
        const el = document.getElementById('po_id');
        if (el && window.Choices) {
            try {
                new Choices(el, {
                    searchEnabled: true,
                    itemSelectText: '',
                    shouldSort: false,
                });
            } catch (e) { /* no-op */ }
        }
    })();
    // Dynamically truncate option labels based on available width for native selects
    function shortenPOOptions() {
        const select = document.getElementById('po_id');
        if (!select) return;
        // If Choices.js has enhanced this select, native option text is hidden; CSS already truncates
        if (select.parentElement && select.parentElement.classList.contains('choices')) return;

        // Compute available text width inside the select (subtract chevron/padding approx)
        const avail = Math.max(0, select.clientWidth - 50);
        if (avail === 0) return; // hidden or not laid out yet

        // Prepare a canvas context to measure text width with the select's font
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const style = window.getComputedStyle(select);
        ctx.font = `${style.fontStyle} ${style.fontVariant} ${style.fontWeight} ${style.fontSize} / ${style.lineHeight} ${style.fontFamily}`;

        function fits(text) { return ctx.measureText(text).width <= avail; }
        function ellipsize(text) {
            if (fits(text)) return text;
            const ell = '…';
            let start = 0, end = text.length, best = '';
            while (start <= end) {
                const mid = Math.floor((start + end) / 2);
                const candidate = text.slice(0, mid) + ell;
                if (fits(candidate)) { best = candidate; start = mid + 1; } else { end = mid - 1; }
            }
            return best || ell;
        }

        Array.from(select.options).forEach(opt => {
            if (opt.value === '') return; // skip placeholder
            // Store original full label once (prefer title if present)
            if (!opt.dataset.fullLabel) {
                opt.dataset.fullLabel = opt.title || opt.text;
            }
            const full = opt.dataset.fullLabel;
            // If it fits, show full; otherwise truncate with ellipsis
            opt.text = fits(full) ? full : ellipsize(full);
        });
    }
    shortenPOOptions();
    window.addEventListener('resize', shortenPOOptions);
    window.addEventListener('orientationchange', shortenPOOptions);
    // Function to calculate totals
    function calculateTotals() {
        let total = 0;
        
        document.querySelectorAll('.quantity-input').forEach(input => {
            const quantity = parseFloat(input.value) || 0;
            const unitPrice = parseFloat(input.dataset.unitPrice) || 0;
            const lineTotal = quantity * unitPrice;
            
            // Update line total
            const row = input.closest('tr');
            const lineTotalElement = row.querySelector('.line-total-amount');
            if (lineTotalElement) {
                lineTotalElement.textContent = lineTotal.toFixed(2);
            }
            
            // Add to total
            total += lineTotal;
        });
        
        // Update total
        const totalElement = document.getElementById('totalAmount');
        if (totalElement) {
            totalElement.textContent = '₱' + total.toFixed(2);
        }
    }
    
    // Calculate totals when quantities change
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('quantity-input')) {
            // Ensure quantity doesn't exceed remaining
            const max = parseFloat(e.target.max) || 0;
            const value = parseFloat(e.target.value) || 0;
            
            if (value > max) {
                e.target.value = max;
            } else if (value < 0) {
                e.target.value = 0;
            }
            
            calculateTotals();
        }
    });
    
    // Calculate initial totals
    calculateTotals();
});
</script>

<?php 
// Include footer
require_once __DIR__ . '/../templates/footer.php'; 
?>