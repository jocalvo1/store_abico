<?php
// Start output buffering to prevent headers already sent error
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    ob_end_flush();
    exit();
}

// Include database connection
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../templates/header.php';

// Initialize database connection
$conn = getDBConnection();
$errors = [];
$purchase = [
    'supplier_id' => isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : '',
    'po_number' => 'PO-' . strtoupper(uniqid()),
    'notes' => '',
    'items' => []
];

// Get all active suppliers for the dropdown
$suppliers = [];
$supplier_result = $conn->query("SELECT id, name FROM suppliers ORDER BY name");
if ($supplier_result) {
    $suppliers = $supplier_result->fetch_all(MYSQLI_ASSOC);
}

// Get all active items for the items dropdown
$items = [];
$item_result = $conn->query("SELECT id, name, unit FROM items ORDER BY name");
if ($item_result) {
    $items = $item_result->fetch_all(MYSQLI_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    $purchase['supplier_id'] = intval($_POST['supplier_id'] ?? 0);
    $purchase['po_number'] = trim($_POST['po_number'] ?? '');
    $purchase['notes'] = trim($_POST['notes'] ?? '');
    
    // Validate purchase order
    if (empty($purchase['supplier_id'])) {
        $errors[] = "Please select a supplier.";
    }
    
    if (empty($purchase['po_number'])) {
        $purchase['po_number'] = 'PO-' . strtoupper(uniqid());
    }
    
    // Validate items
    $purchase_items = [];
    $item_ids = $_POST['item_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['price'] ?? [];
    
    $total_amount = 0;
    
    foreach ($item_ids as $index => $item_id) {
        $item_id = intval($item_id);
        $quantity = floatval($quantities[$index] ?? 0);
        $unit_price = floatval($prices[$index] ?? 0);
        
        if ($item_id > 0 && $quantity > 0) {
            $purchase_items[] = [
                'item_id' => $item_id,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'total_price' => $quantity * $unit_price
            ];
            
            $total_amount += $quantity * $unit_price;
        }
    }
    
    if (empty($purchase_items)) {
        $errors[] = "Please add at least one item to the purchase order.";
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert purchase order with current date
            $current_date = date('Y-m-d');
            $stmt = $conn->prepare("
                INSERT INTO purchase_orders (supplier_id, po_number, order_date, total_amount, notes, created_by_user_id)
                VALUES (?, ?, ?, ?, ?, ?)
            
            
            ");
            $stmt->bind_param(
                "issdsi",
                $purchase['supplier_id'],
                $purchase['po_number'],
                $current_date,
                $total_amount,
                $purchase['notes'],
                $_SESSION['user_id']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error creating purchase order: " . $stmt->error);
            }
            
            $purchase_id = $conn->insert_id;
            $stmt->close();
            
            // Insert purchase order items
            $stmt = $conn->prepare("
                INSERT INTO purchase_order_items (purchase_order_id, item_id, quantity, unit_price, total_price)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($purchase_items as $item) {
                $stmt->bind_param(
                    "iiddd",
                    $purchase_id,
                    $item['item_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price']
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Error adding items to purchase order: " . $stmt->error);
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = "Purchase order created successfully!";
            header("Location: view.php?id=" . $purchase_id);
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
    
    // If we got here, there were errors or we need to redisplay the form
    $purchase['items'] = $purchase_items;
}
?>

<div class="container-fluid pb-5">
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm mb-4" data-aos="fade-up">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">New Purchase Order</h5>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 text-nowrap">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back</span>
                        </a>
                    </div>
                </div>
                <div class="card-body">
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

                    <form method="POST" action="" id="purchaseForm">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="supplier_id" class="form-label">Supplier <span class="text-danger">*</span></label>
                                    <select class="form-select" id="supplier_id" name="supplier_id" required>
                                        <option value="">-- Select Supplier --</option>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?php echo $supplier['id']; ?>" 
                                                <?php echo ($purchase['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($supplier['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="po_number" class="form-label">Reference #</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="po_number" name="po_number" 
                                               value="<?php echo htmlspecialchars($purchase['po_number']); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"><?php 
                                echo htmlspecialchars($purchase['notes']); 
                            ?></textarea>
                        </div>
                        
                        <div class="card mb-4 border">
                            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Items</h6>
                                <button type="button" class="btn btn-sm btn-primary" id="addItemBtn">
                                    <i class="fas fa-plus me-1"></i>
                                    <span class="d-none d-sm-inline">Add Item</span>
                                    <span class="d-inline d-sm-none">Add</span>
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="itemsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-nowrap">Item</th>
                                                <th class="text-nowrap">Quantity</th>
                                                <th class="text-nowrap">Unit Price</th>
                                                <th class="text-nowrap">Total</th>
                                                <th width="50"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="itemsTbody">
                                            <?php if (empty($purchase['items'])): ?>
                                                <tr class="no-items">
                                                    <td colspan="5" class="text-center text-muted py-4">
                                                        <i class="fas fa-shopping-cart fa-2x mb-2 d-block"></i>
                                                        No items added yet. Click "Add Item" to get started.
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($purchase['items'] as $index => $item): ?>
                                                    <tr class="item-row">
                                                        <td data-label="Item">
                                                            <select class="form-select form-select-sm item-select" name="item_id[]" required>
                                                                <option value="">-- Select Item --</option>
                                                                <?php foreach ($items as $i): ?>
                                                                    <option value="<?php echo $i['id']; ?>" 
                                                                        data-unit="<?php echo htmlspecialchars($i['unit']); ?>"
                                                                        <?php echo ($item['item_id'] == $i['id']) ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($i['name']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                        <td data-label="Quantity">
                                                            <div class="input-group input-group-sm">
                                                                <input type="number" class="form-control quantity" name="quantity[]" 
                                                                       min="0.01" step="0.01" inputmode="decimal" value="<?php echo htmlspecialchars($item['quantity']); ?>" required>
                                                                <span class="input-group-text unit"><?php 
                                                                    $selected_item = array_filter($items, function($i) use ($item) {
                                                                        return $i['id'] == $item['item_id'];
                                                                    });
                                                                    echo !empty($selected_item) ? htmlspecialchars(reset($selected_item)['unit']) : 'unit';
                                                                ?></span>
                                                            </div>
                                                        </td>
                                                        <td data-label="Unit Price">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text">₱</span>
                                                                <input type="number" class="form-control price" name="price[]" 
                                                                       min="0" step="0.01" inputmode="decimal" value="<?php echo htmlspecialchars($item['unit_price']); ?>" required>
                                                            </div>
                                                        </td>
                                                        <td data-label="Total">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text">₱</span>
                                                                <input type="text" class="form-control total" 
                                                                       value="<?php echo number_format($item['total_price'], 2); ?>" readonly>
                                                            </div>
                                                        </td>
                                                        <td class="text-center">
                                                            <button type="button" class="btn btn-sm btn-outline-danger remove-item" title="Remove">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="3" class="text-end fw-bold">Total:</td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">₱</span>
                                                        <input type="text" class="form-control fw-bold" id="grandTotal" value="0.00" readonly>
                                                    </div>
                                                </td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Template for new item row -->
<template id="itemRowTemplate">
    <tr class="item-row">
        <td data-label="Item">
            <select class="form-select form-select-sm item-select" name="item_id[]" required>
                <option value="">-- Select Item --</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?php echo $item['id']; ?>" 
                            data-unit="<?php echo htmlspecialchars($item['unit']); ?>">
                        <?php echo htmlspecialchars($item['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td data-label="Quantity">
            <div class="input-group input-group-sm">
                <input type="number" class="form-control quantity" name="quantity[]" min="0.01" step="0.01" inputmode="decimal" value="1" required>
                <span class="input-group-text unit">unit</span>
            </div>
        </td>
        <td data-label="Unit Price">
            <div class="input-group input-group-sm">
                <span class="input-group-text">₱</span>
                <input type="number" class="form-control price" name="price[]" min="0" step="0.01" inputmode="decimal" value="0.00" required>
            </div>
        </td>
        <td data-label="Total">
            <div class="input-group input-group-sm">
                <span class="input-group-text">₱</span>
                <input type="text" class="form-control total" value="0.00" readonly>
            </div>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger remove-item" title="Remove">
                <i class="fas fa-times"></i>
            </button>
        </td>
    </tr>
</template>
<button type="button" id="backToTop" class="btn btn-primary rounded-circle back-to-top" aria-label="Back to top" title="Back to top">
    <i class="fas fa-arrow-up"></i>
    <span class="visually-hidden">Back to top</span>
</button>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const itemsTbody = document.getElementById('itemsTbody');
    const addItemBtn = document.getElementById('addItemBtn');
    const itemRowTemplate = document.getElementById('itemRowTemplate');
    const purchaseForm = document.getElementById('purchaseForm');
    
    // Add new item row
    function addItemRow() {
        // Remove the "no items" row if it exists
        const noItemsRow = itemsTbody.querySelector('.no-items');
        if (noItemsRow) {
            noItemsRow.remove();
        }
        
        // Clone the template and append to tbody
        const newRow = itemRowTemplate.content.cloneNode(true);
        itemsTbody.appendChild(newRow);
        
        // Initialize the new row
        initItemRow(itemsTbody.lastElementChild);
        
        // Update item availability in all selects
        updateItemAvailability();
        
        // Calculate totals
        calculateTotals();
    }
    
    // Update item availability in all dropdowns
    function updateItemAvailability() {
        const allSelects = document.querySelectorAll('.item-select');
        const selectedItemIds = [];
        
        // Collect all selected item IDs
        allSelects.forEach(select => {
            if (select.value) {
                selectedItemIds.push(select.value);
            }
        });
        
        // Update each select
        allSelects.forEach(select => {
            const currentValue = select.value;
            
            // Enable all options first
            Array.from(select.options).forEach(option => {
                if (option.value) { // Skip the default/empty option
                    option.disabled = selectedItemIds.includes(option.value) && option.value !== currentValue;
                }
            });
        });
    }
    
    // Initialize an item row with event listeners
    function initItemRow(row) {
        const itemSelect = row.querySelector('.item-select');
        const quantityInput = row.querySelector('.quantity');
        const priceInput = row.querySelector('.price');
        const removeBtn = row.querySelector('.remove-item');
        // helper to reflect selected option text as tooltip on the select
        function setSelectTitle(sel){
            try {
                if (!sel) return;
                const opt = sel.options[sel.selectedIndex];
                sel.title = opt && opt.text ? opt.text : '';
            } catch(e) {}
        }
        
        // Update unit when item is selected
        if (itemSelect) {
            // set initial tooltip
            setSelectTitle(itemSelect);
            itemSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const selectedItemId = this.value;
                const unit = selectedOption.dataset.unit || 'unit';
                row.querySelector('.unit').textContent = unit;
                
                // Check for duplicate items
                if (selectedItemId) {
                    const allSelects = document.querySelectorAll('.item-select');
                    let duplicateFound = false;
                    
                    allSelects.forEach(select => {
                        // Skip current select
                        if (select === this) return;
                        
                        if (select.value === selectedItemId) {
                            // Reset the current selection
                            this.selectedIndex = 0;
                            row.querySelector('.unit').textContent = 'unit';
                            alert('This item has already been added to the order.');
                            duplicateFound = true;
                        }
                    });
                    
                    if (duplicateFound) return;
                }
                
                // Update item availability in all selects
                updateItemAvailability();
                calculateTotals();
                setSelectTitle(this);
            });
        }
        
        // Calculate row total when quantity or price changes
        [quantityInput, priceInput].forEach(input => {
            if (input) {
                input.addEventListener('input', calculateTotals);
            }
        });
        
        // Remove row
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                row.remove();
                
                // Update item availability in all selects
                updateItemAvailability();
                calculateTotals();
                
                // Show "no items" message if no rows left
                if (itemsTbody.querySelectorAll('.item-row').length === 0) {
                    const noItemsRow = document.createElement('tr');
                    noItemsRow.className = 'no-items';
                    noItemsRow.innerHTML = `
                        <td colspan="5" class="text-center text-muted py-4">
                            <i class="fas fa-shopping-cart fa-2x mb-2 d-block"></i>
                            No items added yet. Click "Add Item" to get started.
                        </td>
                    `;
                    itemsTbody.appendChild(noItemsRow);
                }
            });
        }
    }
    
    // Calculate row totals and grand total
    function calculateTotals() {
        let grandTotal = 0;
        
        document.querySelectorAll('.item-row').forEach(row => {
            const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
            const price = parseFloat(row.querySelector('.price').value) || 0;
            const total = quantity * price;
            
            row.querySelector('.total').value = total.toFixed(2);
            grandTotal += total;
        });
        
        document.getElementById('grandTotal').value = grandTotal.toFixed(2);
    }
    
    // Add item button click handler
    addItemBtn.addEventListener('click', addItemRow);
    
    // Initialize existing rows
    document.querySelectorAll('.item-row').forEach(initItemRow);
    
    // Add a row if there are no items when the page loads
    if (itemsTbody.querySelectorAll('.item-row').length === 0) {
        addItemRow();
    }
    
    // Initialize item availability
    updateItemAvailability();
    
    // Calculate initial totals
    calculateTotals();
    
    // No separate mobile add button; Add Item button is full-width on mobile

    // Form validation
    purchaseForm.addEventListener('submit', function(e) {
        // Check if at least one item is added
        const itemRows = itemsTbody.querySelectorAll('.item-row');
        let hasValidItems = false;
        
        itemRows.forEach(row => {
            const itemId = row.querySelector('.item-select').value;
            const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
            
            if (itemId && quantity > 0) {
                hasValidItems = true;
            }
        });
        
        if (!hasValidItems) {
            e.preventDefault();
            alert('Please add at least one valid item to the purchase order.');
            return false;
        }
        
        return true;
    });
});
</script>

<style>
.item-row td {
    vertical-align: middle;
}
.remove-item {
    padding: 0.25rem 0.5rem;
    line-height: 1;
}
.table th {
    white-space: nowrap;
}
/* Improve readability: give item column more room and allow horizontal scroll on small screens */
#itemsTable th:nth-child(1),
#itemsTable td:nth-child(1) {
    min-width: 240px;
}
#itemsTable td:nth-child(2),
#itemsTable td:nth-child(3) {
    min-width: 130px;
}
#itemsTable td:nth-child(4) {
    min-width: 120px;
}
.item-select {
    font-size: 0.95rem;
}
/* Mobile table to stacked cards, consistent with edit.php */
@media (max-width: 576px) {
  #itemsTable thead,
  #itemsTable tfoot { display: none; }
  #itemsTable,
  #itemsTable tbody,
  #itemsTable tr,
  #itemsTable td { display: block; width: 100%; }
  #itemsTable tr { border-top: 1px solid #eee; padding: .5rem 0; margin: 0; }
  #itemsTable td { padding: .5rem .75rem; display: flex; justify-content: space-between; align-items: center; gap: .75rem; }
  #itemsTable td::before { content: attr(data-label); font-weight: 600; color: #6c757d; flex: 0 0 110px; max-width: 45%; white-space: nowrap; }
  .item-row .input-group { flex: 1 1 auto; min-width: 0; flex-wrap: nowrap; }
  .item-row .input-group .form-control { width: 1%; flex: 1 1 auto; min-width: 0; }
  .item-row .input-group .input-group-text { white-space: nowrap; }
  /* Make Add Item button full-width only on mobile */
  #addItemBtn { width: 100% !important; }
}
</style>

<!-- Removed separate mobile sticky action bar to match edit.php layout -->

<?php 
// Include footer
require_once __DIR__ . '/../templates/footer.php'; 
?>