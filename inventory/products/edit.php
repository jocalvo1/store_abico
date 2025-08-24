<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../controller/product/ProductController.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid product ID.';
    header('Location: index.php');
    exit();
}

$product_id = (int)$_GET['id'];
$productController = new ProductController();
$product = $productController->getById($product_id);

if (!$product) {
    $_SESSION['error'] = 'Product not found or you do not have permission to edit it.';
    header('Location: index.php');
    exit();
}

$originalProduct = $product; // Keep original values for stock movement comparison

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read-only product fields: always keep original values
    $product['name'] = $originalProduct['name'];
    $product['description'] = $originalProduct['description'];
    $product['category'] = $originalProduct['category'];
    $product['unit'] = $originalProduct['unit'];

    // Inventory/pricing fields
    $product['reorder_level'] = floatval($_POST['reorder_level'] ?? 0);
    $product['selling_price'] = floatval($_POST['selling_price'] ?? 0);

    // Stock adjustment inputs
    $originalStock = (float)($originalProduct['current_stock'] ?? 0);
    $adjustType = $_POST['adjust_type'] ?? '';
    $adjustQty = isset($_POST['adjust_qty']) ? floatval($_POST['adjust_qty']) : 0;
    $adjustNotes = trim($_POST['adjust_notes'] ?? '');

    // Default to original stock; apply adjustment if any
    $product['current_stock'] = $originalStock;
    if ($adjustQty > 0) {
        if ($adjustType === 'increase') {
            $product['current_stock'] = $originalStock + $adjustQty;
        } elseif ($adjustType === 'decrease') {
            $product['current_stock'] = $originalStock - $adjustQty;
        }
    }
    
    // Validate
    if (empty($product['name'])) {
        $errors[] = "Product name is required.";
    }
    
    // Validate stock adjustment if provided
    if ($adjustQty > 0) {
        if (!in_array($adjustType, ['increase','decrease'], true)) {
            $errors[] = 'Select a valid adjustment type.';
        }
        if ($adjustType === 'decrease' && $adjustQty > $originalStock) {
            $errors[] = 'Cannot decrease more than current stock.';
        }
        if ($adjustNotes === '') {
            $errors[] = 'Please provide adjustment notes.';
        }
    }
    
    if ($product['selling_price'] < 0) {
        $errors[] = "Selling price cannot be negative.";
    }
    
    // If no errors, update the product
    if (empty($errors)) {
        if ($productController->update($product_id, $product)) {
            // Log stock movement if stock changed
            $oldStock = (float)($originalProduct['current_stock'] ?? 0);
            $newStock = (float)($product['current_stock'] ?? 0);
            $diff = $newStock - $oldStock;
            if (abs($diff) > 0) {
                $db = getDBConnection();
                $movementType = $diff > 0 ? 'in' : 'out';
                $quantity = abs($diff);
                $referenceType = 'adjustment';
                $referenceId = $product_id;
                // Build detailed notes (reason removed, keep notes only)
                $noteSuffix = 'from ' . $oldStock . ' to ' . $newStock;
                $baseNote = $adjustNotes !== '' ? $adjustNotes : 'Stock adjusted';
                $notes = trim($baseNote . ' (' . $noteSuffix . ')');
                if ($stmtMv = $db->prepare('INSERT INTO stock_movements (item_id, movement_type, quantity, reference_type, reference_id, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')) {
                    // Bind: i (item_id), s (movement_type), d (quantity), s (reference_type), i (reference_id), s (notes)
                    $stmtMv->bind_param('isdsis', $product_id, $movementType, $quantity, $referenceType, $referenceId, $notes);
                    $stmtMv->execute();
                    $stmtMv->close();
                }
                $db->close();
            }

            $_SESSION['success'] = "Product updated successfully!";
            header("Location: view.php?id=" . $product_id);
            exit();
        } else {
            $errors[] = "Error updating product. Please try again.";
        }
    }
}

// Include header
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            Edit Product
            <small class="text-muted"><?php echo htmlspecialchars($product['name']); ?></small>
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="index.php" class="btn btn-sm btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-1"></i> Back to Products
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Edit Product Information</h6>
                </div>
                <div class="card-body">
                    <form method="post" id="productForm">
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" rows="2" readonly><?php echo htmlspecialchars($product['description']); ?></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="form-label">Category</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($product['category']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Unit</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($product['unit']); ?>" readonly>
                            </div>
                        </div>
                        
                        <?php 
                            // Compute initial inventory status for badge
                            $initStock = (float)($product['current_stock'] ?? 0);
                            $initReorder = (float)($product['reorder_level'] ?? 0);
                            $initStatusClass = 'success';
                            $initStatusLabel = 'In Stock';
                            if ($initStock <= 0) { $initStatusClass = 'danger'; $initStatusLabel = 'Out of Stock'; }
                            elseif ($initReorder > 0 && $initStock <= $initReorder) { $initStatusClass = 'warning'; $initStatusLabel = 'Low Stock'; }
                        ?>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="current_stock" class="form-label d-flex align-items-center justify-content-between">
                                    <span>Current Stock</span>
                                    <span id="stock_status_badge" class="badge text-bg-<?php echo $initStatusClass; ?> ms-2">
                                        <i class="fas fa-circle me-1"></i><span id="stock_status_text"><?php echo $initStatusLabel; ?></span>
                                    </span>
                                </label>
                                <input type="number" step="0.01" class="form-control" id="current_stock" 
                                       name="current_stock" value="<?php echo $product['current_stock']; ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="reorder_level" class="form-label">Reorder Level</label>
                                <input type="number" step="0.01" class="form-control" id="reorder_level" 
                                       name="reorder_level" value="<?php echo $product['reorder_level']; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="selling_price" class="form-label">Selling Price <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" step="0.01" class="form-control" id="selling_price" 
                                           name="selling_price" value="<?php echo $product['selling_price']; ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-light">
                                <strong>Adjust Stock</strong>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Adjustment Type</label>
                                        <select name="adjust_type" id="adjust_type" class="form-select">
                                            <option value="">Select</option>
                                            <option value="increase">Increase</option>
                                            <option value="decrease">Decrease</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Quantity</label>
                                        <input type="number" step="0.01" min="0.01" class="form-control" name="adjust_qty" id="adjust_qty" placeholder="0.00">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Notes</label>
                                        <textarea class="form-control" name="adjust_notes" id="adjust_notes" rows="2" placeholder="Describe what happened (required if adjusting)"></textarea>
                                        <div class="form-text">If adjusting, provide details. Example: "Expired items removed".</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="view.php?id=<?php echo $product_id; ?>" class="btn btn-secondary me-md-2">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Timestamps</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled small">
                        <li class="mb-2">
                            <i class="far fa-calendar-plus text-muted me-2"></i>
                            <strong>Created:</strong> 
                            <span class="text-muted">
                                <?php echo (new DateTime($product['created_at']))->format('M j, Y \a\t g:i A'); ?>
                            </span>
                        </li>
                        <li>
                            <i class="far fa-calendar-check text-muted me-2"></i>
                            <strong>Last Updated:</strong> 
                            <span class="text-muted">
                                <?php echo (new DateTime($product['updated_at']))->format('M j, Y \a\t g:i A'); ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
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
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Dynamic inventory status badge updater
        function updateStockStatus() {
            const stockInput = document.getElementById('current_stock');
            const reorderInput = document.getElementById('reorder_level');
            const badge = document.getElementById('stock_status_badge');
            const labelEl = document.getElementById('stock_status_text');
            if (!stockInput || !reorderInput || !badge || !labelEl) return;

            const stock = parseFloat(stockInput.value || '0');
            const reorder = parseFloat(reorderInput.value || '0');

            let state = 'success';
            let label = 'In Stock';
            if (isNaN(stock) || stock <= 0) { state = 'danger'; label = 'Out of Stock'; }
            else if (!isNaN(reorder) && reorder > 0 && stock <= reorder) { state = 'warning'; label = 'Low Stock'; }

            // Reset class to reflect new state
            badge.classList.remove('text-bg-success','text-bg-warning','text-bg-danger');
            badge.classList.add('text-bg-' + state);
            labelEl.textContent = label;
        }

        // Function retained for compatibility, guarded if unit fields are absent
        function updateUnitValue() {
            const unitValueEl = document.getElementById('unit_value');
            const unitTypeEl = document.getElementById('unit_type');
            const hiddenUnit = document.getElementById('unit');
            const preview = document.getElementById('unit_preview');
            if (!unitValueEl || !unitTypeEl || !hiddenUnit) return '';

            const unitValue = unitValueEl.value;
            let unitType = unitTypeEl.value;
            let displayUnit = unitType;

            if (unitType === 'Other') {
                const otherInput = document.getElementById('unit_type_other');
                if (otherInput) {
                    unitType = otherInput.value.trim() || 'unit';
                    displayUnit = unitType;
                }
            }

            const formattedValue = unitValue ? `${unitValue} ${unitType}` : '';
            hiddenUnit.value = formattedValue;
            if (preview) {
                preview.textContent = unitValue ? `${unitValue} ${displayUnit}` : 'No unit selected';
            }
            return formattedValue;
        }

        // Toggle 'Other' input fields
        function toggleOtherInput(fieldType) {
            const selectElement = document.getElementById(fieldType);
            const otherContainer = document.getElementById(`${fieldType}_other_container`);
            const otherInput = document.getElementById(`${fieldType}_other`);
            if (!selectElement || !otherContainer) return;
            if (selectElement.value === 'Other') {
                otherContainer.style.display = 'block';
                if (otherInput) { otherInput.required = true; otherInput.focus(); }
            } else {
                otherContainer.style.display = 'none';
                if (otherInput) { otherInput.required = false; otherInput.value = ''; }
            }
            updateUnitValue();
        }

        // Check if we need to show 'Other' fields on page load
        window.addEventListener('load', function() {
            // Update inventory status on load
            updateStockStatus();
            // If category/unit fields are present (older layout), keep behavior; otherwise skip.
            const categorySelect = document.getElementById('category');
            if (categorySelect) {
                const isCustomCategory = !['Electronics', 'Clothing', 'Food', 'Beverages', 'Other', ''].includes(categorySelect.value);
                if (isCustomCategory) {
                    const categoryOther = document.getElementById('category_other');
                    if (categoryOther) {
                        categoryOther.value = categorySelect.value;
                        const otherOption = Array.from(categorySelect.options).find(opt => opt.value === 'Other');
                        if (otherOption) otherOption.selected = true;
                        toggleOtherInput('category');
                    }
                }
            }
            updateUnitValue();
        });

        // Attach listeners for live inventory status updates
        const stockEl = document.getElementById('current_stock');
        const reorderEl = document.getElementById('reorder_level');
        if (stockEl) stockEl.addEventListener('input', updateStockStatus);
        if (reorderEl) reorderEl.addEventListener('input', updateStockStatus);

        // Grey out read-only/disabled fields for clarity
        const roSelector = '#productForm input[readonly], #productForm textarea[readonly], #productForm select[disabled], #productForm input[disabled], #productForm textarea[disabled]';
        const roEls = document.querySelectorAll(roSelector);
        roEls.forEach(el => {
            el.classList.add('bg-secondary-subtle', 'text-body');
            el.style.cursor = 'not-allowed';
            const ig = el.closest('.input-group');
            if (ig) {
                ig.querySelectorAll('.input-group-text').forEach(span => span.classList.add('bg-secondary-subtle', 'text-body'));
            }
        });

        // Update form submission: nothing needed for read-only meta fields
        const form = document.getElementById('productForm');
        if (form) {
            form.addEventListener('submit', function() {
                // Keep status fresh on submit
                updateStockStatus();
            });
        }
    });
</script>

<?php 
// Include footer
require_once __DIR__ . '/../../templates/footer.php';
?>
