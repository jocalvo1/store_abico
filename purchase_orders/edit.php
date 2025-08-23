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

require_once __DIR__ . '/../includes/database.php';

// Check if ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No purchase order ID provided.";
    header("Location: index.php");
    exit();
}

$purchase_id = intval($_GET['id']);
$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch purchase details
$purchase = null;
$stmt = $conn->prepare("
    SELECT p.*, s.name as supplier_name 
    FROM purchase_orders p
    JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $purchase_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Purchase order not found.";
    header("Location: index.php");
    exit();
}

$purchase = $result->fetch_assoc();
$stmt->close();

// Fetch purchase items
$stmt = $conn->prepare("
    SELECT pi.id, pi.item_id, pi.quantity, pi.unit_price, pi.total_price, 
           i.name as item_name, i.unit
    FROM purchase_order_items pi
    JOIN items i ON pi.item_id = i.id
    WHERE pi.purchase_order_id = ?
");
$stmt->bind_param("i", $purchase_id);
$stmt->execute();
$items_result = $stmt->get_result();

if ($items_result) {
    $purchase['items'] = $items_result->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();

// Get all suppliers for the dropdown
$suppliers = [];
$supplier_result = $conn->query("SELECT id, name FROM suppliers ORDER BY name");
if ($supplier_result) {
    $suppliers = $supplier_result->fetch_all(MYSQLI_ASSOC);
}

// Get all items for the items dropdown
$all_items = [];
$item_result = $conn->query("SELECT id, name, unit FROM items ORDER BY name");
if ($item_result) {
    $all_items = $item_result->fetch_all(MYSQLI_ASSOC);
}

// Build a quick lookup map for item names
$item_name_map = [];
foreach ($all_items as $it) {
    $item_name_map[(int)$it['id']] = $it['name'];
}

$errors = [];

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
        $errors[] = "Reference number is required.";
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
        $item_row_id = intval($_POST['item_row_id'][$index] ?? 0);
        
        if ($item_id > 0 && $quantity > 0) {
            $purchase_items[] = [
                'id' => $item_row_id,
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
    
    // Prevent duplicate items in the same purchase order
    if (empty($errors)) {
        $seen = [];
        foreach ($purchase_items as $pi) {
            $iid = (int)$pi['item_id'];
            if (isset($seen[$iid])) {
                $name = isset($item_name_map[$iid]) ? $item_name_map[$iid] : ('Item #' . $iid);
                $errors[] = "Duplicate item selected: " . htmlspecialchars($name);
                break;
            }
            $seen[$iid] = true;
        }
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update purchase
            $stmt = $conn->prepare("
                UPDATE purchase_orders 
                SET supplier_id = ?, po_number = ?, total_amount = ?, notes = ?
                WHERE id = ?
            ") or die($conn->error);
            $stmt->bind_param(
                "isdsi",
                $purchase['supplier_id'],
                $purchase['po_number'],
                $total_amount,
                $purchase['notes'],
                $purchase_id
            ) or die($stmt->error);
            $stmt->execute() or die($stmt->error);
            
            // Get existing item IDs to track which ones to delete
            $existing_item_ids = [];
            $stmt = $conn->prepare("SELECT id FROM purchase_order_items WHERE purchase_order_id = ?");
            $stmt->bind_param("i", $purchase_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $existing_item_ids[] = $row['id'];
            }
            $stmt->close();
            
            // Update or insert items
            $updated_item_ids = [];
            foreach ($purchase_items as $item) {
                if ($item['id'] > 0) {
                    // Update existing item
                    $stmt = $conn->prepare("
                        UPDATE purchase_order_items 
                        SET item_id = ?, quantity = ?, unit_price = ?, total_price = ?
                        WHERE id = ? AND purchase_order_id = ?
                    ") or die($conn->error);
                    $total_price = $item['quantity'] * $item['unit_price'];
                    $stmt->bind_param(
                        "iiddii",
                        $item['item_id'],
                        $item['quantity'],
                        $item['unit_price'],
                        $total_price,
                        $item['id'],
                        $purchase_id
                    ) or die($stmt->error);
                    $stmt->execute() or die($stmt->error);
                    $updated_item_ids[] = $item['id'];
                } else {
                    // Insert new item
                    $stmt = $conn->prepare("
                        INSERT INTO purchase_order_items (purchase_order_id, item_id, quantity, unit_price, total_price)
                        VALUES (?, ?, ?, ?, ?)
                    ") or die($conn->error);
                    $total_price = $item['quantity'] * $item['unit_price'];
                    $stmt->bind_param(
                        "iiddd",
                        $purchase_id,
                        $item['item_id'],
                        $item['quantity'],
                        $item['unit_price'],
                        $total_price
                    ) or die($stmt->error);
                    $stmt->execute() or die($stmt->error);
                    $updated_item_ids[] = $stmt->insert_id;
                }
                $stmt->close();
            }
            
            // Delete items that were removed
            $items_to_delete = array_diff($existing_item_ids, $updated_item_ids);
            if (!empty($items_to_delete)) {
                $placeholders = implode(',', array_fill(0, count($items_to_delete), '?'));
                $types = str_repeat('i', count($items_to_delete));
                $stmt = $conn->prepare("DELETE FROM purchase_order_items WHERE id IN ($placeholders) AND purchase_order_id = ?") or die($conn->error);
                $params = array_merge($items_to_delete, [$purchase_id]);
                $stmt->bind_param($types . 'i', ...$params) or die($stmt->error);
                $stmt->execute() or die($stmt->error);
                $stmt->close();
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = "Purchase order updated successfully!";
            header("Location: view.php?id=" . $purchase_id);
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = "An error occurred while saving the purchase order: " . $e->getMessage();
        }
    }
}

$conn->close();

// Include header after all processing is done but before any HTML output
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Edit Purchase Order #<?php echo htmlspecialchars($purchase['po_number']); ?></h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="view.php?id=<?php echo $purchase_id; ?>" class="btn btn-sm btn-outline-secondary me-2 d-inline-flex align-items-center gap-1 text-nowrap">
                <i class="fas fa-arrow-left"></i>
                <span>Back to View</span>
            </a>
            <a href="index.php" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 text-nowrap">
                <i class="fas fa-list"></i>
                <span>All Purchases</span>
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
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
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Purchase Order Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="po_number" class="form-label">Reference Number</label>
                                <input type="text" class="form-control" id="po_number" name="po_number" 
                                       value="<?php echo htmlspecialchars($purchase['po_number']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="supplier_id" class="form-label">Supplier <span class="text-danger">*</span></label>
                                <select class="form-select" id="supplier_id" name="supplier_id" required>
                                    <option value="">Select Supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?php echo $supplier['id']; ?>" 
                                            <?php echo $supplier['id'] == $purchase['supplier_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($supplier['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"><?php echo htmlspecialchars($purchase['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Items</h6>
                        <button type="button" class="btn btn-sm btn-primary" id="addItemBtn">
                            <i class="fas fa-plus"></i> Add Item
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="itemsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th class="width-40p">Item</th>
                                        <th class="width-20p">Quantity</th>
                                        <th class="width-20p">Unit Price</th>
                                        <th class="width-15p">Total</th>
                                        <th class="width-5p"></th>
                                    </tr>
                                </thead>
                                <tbody id="itemsTbody">
                                    <?php foreach ($purchase['items'] as $index => $item): ?>
                                        <tr class="item-row">
                                            <td data-label="Item">
                                                <input type="hidden" name="item_row_id[]" value="<?php echo $item['id']; ?>">
                                                <select class="form-select item-select" name="item_id[]" required>
                                                    <option value="">Select Item</option>
                                                    <?php foreach ($all_items as $i): ?>
                                                        <option value="<?php echo $i['id']; ?>" 
                                                            data-unit="<?php echo htmlspecialchars($i['unit']); ?>"
                                                            <?php echo $i['id'] == $item['item_id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($i['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td data-label="Quantity">
                                                <div class="input-group">
                                                    <input type="number" class="form-control quantity" 
                                                           name="quantity[]" min="0.01" step="0.01" 
                                                           value="<?php echo htmlspecialchars($item['quantity']); ?>" required>
                                                    <span class="input-group-text unit"><?php echo htmlspecialchars($item['unit']); ?></span>
                                                </div>
                                            </td>
                                            <td data-label="Unit Price">
                                                <div class="input-group">
                                                    <span class="input-group-text">₱</span>
                                                    <input type="number" class="form-control price" 
                                                           name="price[]" min="0" step="0.01" 
                                                           value="<?php echo htmlspecialchars($item['unit_price']); ?>" required>
                                                </div>
                                            </td>
                                            <td data-label="Total">
                                                <div class="input-group">
                                                    <span class="input-group-text">₱</span>
                                                    <input type="text" class="form-control total" 
                                                           value="<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?>" readonly>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-danger remove-item" title="Remove">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">Total:</td>
                                        <td>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="text" class="form-control fw-bold" id="grandTotal" 
                                                       value="<?php echo number_format($purchase['total_amount'], 2); ?>" readonly>
                                            </div>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="view.php?id=<?php echo $purchase_id; ?>" class="btn btn-outline-secondary me-md-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Purchase Order
                    </button>
                </div>
            </form>

            <!-- Template for new item row -->
            <template id="itemRowTemplate">
                <tr class="item-row">
                    <td data-label="Item">
                        <input type="hidden" name="item_row_id[]" value="0">
                        <select class="form-select item-select" name="item_id[]" required>
                            <option value="">Select Item</option>
                            <?php foreach ($all_items as $item): ?>
                                <option value="<?php echo $item['id']; ?>" 
                                        data-unit="<?php echo htmlspecialchars($item['unit']); ?>">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td data-label="Quantity">
                        <div class="input-group">
                            <input type="number" class="form-control quantity" name="quantity[]" min="0.01" step="0.01" required>
                            <span class="input-group-text unit">unit</span>
                        </div>
                    </td>
                    <td data-label="Unit Price">
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" class="form-control price" name="price[]" min="0" step="0.01" required>
                        </div>
                    </td>
                    <td data-label="Total">
                        <div class="input-group">
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
            <style>
            .item-row td {
                vertical-align: middle;
            }

            .item-row .form-control.is-invalid {
                border-color: #dc3545;
                padding-right: calc(1.5em + 0.75rem);
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
                background-repeat: no-repeat;
                background-position: right calc(0.375em + 0.1875rem) center;
                background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
            }
            /* Mobile table to stacked cards */
            @media (max-width: 576px) {
                #itemsTable thead,
                #itemsTable tfoot {
                    display: none;
                }
                #itemsTable,
                #itemsTable tbody,
                #itemsTable tr,
                #itemsTable td {
                    display: block;
                    width: 100%;
                }
                #itemsTable tr {
                    border-top: 1px solid #eee;
                    padding: .5rem 0;
                    margin: 0;
                }
                #itemsTable td {
                    padding: .5rem .75rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: .75rem;
                }
                #itemsTable td::before {
                    content: attr(data-label);
                    font-weight: 600;
                    color: #6c757d;
                    flex: 0 0 110px; /* reserve space for label to reduce squeezing */
                    max-width: 45%;
                    white-space: nowrap;
                }
                /* Make inputs fill available width inside stacked rows */
                .item-row .input-group {
                    flex: 1 1 auto;
                    min-width: 0;
                    flex-wrap: nowrap; /* keep input and addon on one line */
                }
                .item-row .input-group .form-control {
                    width: 1%;
                    flex: 1 1 auto;
                    min-width: 0;
                }
                .item-row .input-group .input-group-text {
                    white-space: nowrap;
                }
                /* Make Add Item button full-width at top */
                #addItemBtn {
                    width: 100% !important;
                }
            }
            </style>
        </div>
    </div>
</div>
<button type="button" id="backToTop" class="btn btn-primary rounded-circle back-to-top" aria-label="Back to top" title="Back to top">
    <i class="fas fa-arrow-up"></i>
    <span class="visually-hidden">Back to top</span>
</button>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const itemsTbody = document.getElementById('itemsTbody');
        const addItemBtn = document.getElementById('addItemBtn');
        const itemRowTemplate = document.getElementById('itemRowTemplate');
        const grandTotalInput = document.getElementById('grandTotal');
        
        function getSelectedItemIds() {
            const ids = [];
            document.querySelectorAll('.item-row .item-select').forEach(sel => {
                if (sel.value) ids.push(sel.value);
            });
            return ids;
        }
        
        function updateSelectOptions() {
            const selected = getSelectedItemIds();
            document.querySelectorAll('.item-row .item-select').forEach(sel => {
                const current = sel.value;
                Array.from(sel.options).forEach(opt => {
                    if (!opt.value) return; // skip placeholder
                    // disable if selected elsewhere, but keep enabled for the row that currently uses it
                    opt.disabled = selected.includes(opt.value) && opt.value !== current;
                });
            });
        }
        
        // Add new item row
        addItemBtn.addEventListener('click', function() {
            const newRow = itemRowTemplate.content.cloneNode(true);
            itemsTbody.appendChild(newRow);
            
            // Initialize the new row
            const newRowElement = itemsTbody.lastElementChild;
            initializeRow(newRowElement);
            
            // Focus on the item select
            newRowElement.querySelector('.item-select').focus();
            updateSelectOptions();
        });
        
        // Initialize existing rows
        document.querySelectorAll('.item-row').forEach(row => {
            initializeRow(row);
        });
        updateSelectOptions();
        
        // Initialize a row with event listeners
        function initializeRow(row) {
            const itemSelect = row.querySelector('.item-select');
            const unitSpan = row.querySelector('.unit');
            const quantityInput = row.querySelector('.quantity');
            const priceInput = row.querySelector('.price');
            const totalInput = row.querySelector('.total');
            const removeBtn = row.querySelector('.remove-item');
            
            // Update unit when item changes
            itemSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const unit = selectedOption.dataset.unit || 'unit';
                unitSpan.textContent = unit;
                calculateRowTotal(row);
                calculateGrandTotal();
                updateSelectOptions();
                // Simple duplicate warning
                const currentVal = this.value;
                if (currentVal) {
                    let count = 0;
                    document.querySelectorAll('.item-row .item-select').forEach(s => {
                        if (s.value === currentVal) count++;
                    });
                    if (count > 1) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                } else {
                    this.classList.remove('is-invalid');
                }
            });
            
            // Calculate total when quantity or price changes
            quantityInput.addEventListener('input', () => calculateRowTotal(row));
            priceInput.addEventListener('input', () => calculateRowTotal(row));
            
            // Remove row
            removeBtn.addEventListener('click', function() {
                row.remove();
                calculateGrandTotal();
                updateSelectOptions();
            });
        }
        
        // Calculate total for a single row
        function calculateRowTotal(row) {
            const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
            const price = parseFloat(row.querySelector('.price').value) || 0;
            const total = (quantity * price).toFixed(2);
            row.querySelector('.total').value = total;
            return parseFloat(total);
        }
        
        // Calculate grand total
        function calculateGrandTotal() {
            let grandTotal = 0;
            
            document.querySelectorAll('.item-row').forEach(row => {
                grandTotal += calculateRowTotal(row);
            });
            
            grandTotalInput.value = grandTotal.toFixed(2);
        }
        
        // Handle form submission
        document.getElementById('purchaseForm').addEventListener('submit', function(e) {
            // Validate at least one item exists
            if (document.querySelectorAll('.item-row').length === 0) {
                e.preventDefault();
                alert('Please add at least one item to the purchase order.');
                return false;
            }
            
            // Validate all items have a selected item
            let isValid = true;
            document.querySelectorAll('.item-select').forEach(select => {
                if (!select.value) {
                    isValid = false;
                    select.classList.add('is-invalid');
                } else {
                    select.classList.remove('is-invalid');
                }
            });
            
            // Prevent duplicate items
            if (isValid) {
                const seen = new Set();
                let duplicateFound = false;
                document.querySelectorAll('.item-select').forEach(select => {
                    const v = select.value;
                    if (!v) return;
                    if (seen.has(v)) {
                        duplicateFound = true;
                        select.classList.add('is-invalid');
                    } else {
                        seen.add(v);
                    }
                });
                if (duplicateFound) {
                    e.preventDefault();
                    alert('Each item can only be added once. Please remove duplicates.');
                    return false;
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please select an item for all rows.');
                return false;
            }
            
            return true;
        });
    });
</script>
<?php 
// Include footer
require_once __DIR__ . '/../templates/footer.php'; 
?>