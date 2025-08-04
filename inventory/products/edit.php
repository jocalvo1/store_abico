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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $product['name'] = trim($_POST['name'] ?? '');
    $product['sku'] = trim($_POST['sku'] ?? '');
    $product['description'] = trim($_POST['description'] ?? '');
    $product['category'] = trim($_POST['category'] ?? '');
    $product['unit'] = trim($_POST['unit'] ?? '');
    $product['current_stock'] = floatval($_POST['current_stock'] ?? 0);
    $product['reorder_level'] = floatval($_POST['reorder_level'] ?? 0);
    $product['selling_price'] = floatval($_POST['selling_price'] ?? 0);
    
    // Validate
    if (empty($product['name'])) {
        $errors[] = "Product name is required.";
    }
    
    if (empty($product['category'])) {
        $errors[] = "Category is required.";
    }
    
    if (empty($product['unit'])) {
        $errors[] = "Unit is required.";
    }
    
    if ($product['selling_price'] < 0) {
        $errors[] = "Selling price cannot be negative.";
    }
    
    // If no errors, update the product
    if (empty($errors)) {
        if ($productController->update($product_id, $product)) {
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
            <a href="view.php?id=<?php echo $product_id; ?>" class="btn btn-sm btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-1"></i> Back to Product
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
                            <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($product['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" 
                                     rows="3"><?php echo htmlspecialchars($product['description']); ?></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                <?php
                                // Check if the current category is a custom one (not in the standard list)
                                $standardCategories = ['Electronics', 'Clothing', 'Food', 'Beverages'];
                                $isCustomCategory = !in_array($product['category'], $standardCategories) && !empty($product['category']);
                                ?>
                                <select class="form-select" id="category" name="category" required onchange="toggleOtherInput('category')">
                                    <option value="" disabled>Select a category</option>
                                    <option value="Electronics" <?php echo $product['category'] === 'Electronics' ? 'selected' : ''; ?>>Electronics</option>
                                    <option value="Clothing" <?php echo $product['category'] === 'Clothing' ? 'selected' : ''; ?>>Clothing</option>
                                    <option value="Food" <?php echo $product['category'] === 'Food' ? 'selected' : ''; ?>>Food</option>
                                    <option value="Beverages" <?php echo $product['category'] === 'Beverages' ? 'selected' : ''; ?>>Beverages</option>
                                    <option value="Other" <?php echo $isCustomCategory ? 'selected' : ''; ?>>Other (please specify)</option>
                                    <?php if ($isCustomCategory): ?>
                                        <option value="<?php echo htmlspecialchars($product['category']); ?>" selected><?php echo htmlspecialchars($product['category']); ?></option>
                                    <?php endif; ?>
                                </select>
                                <div id="category_other_container" class="mt-2" style="display: <?php echo $isCustomCategory ? 'block' : 'none'; ?>">
                                    <label for="category_other" class="form-label">Specify Category</label>
                                    <input type="text" class="form-control" id="category_other" name="category_other" 
                                           value="<?php echo $isCustomCategory ? htmlspecialchars($product['category']) : ''; ?>"
                                           placeholder="Enter category name"
                                           <?php echo $isCustomCategory ? 'required' : ''; ?>>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label d-block mb-2">Unit <span class="text-danger">*</span></label>
                                
                                <!-- Unit Value Input -->
                                <div class="mb-2">
                                    <label for="unit_value" class="form-label small text-muted mb-1">Quantity</label>
                                    <?php 
                                        // Parse the unit value and type from the stored unit
                                        $unitValue = 1;
                                        $unitType = 'pcs';
                                        if (!empty($product['unit'])) {
                                            $unitParts = explode(' ', $product['unit'], 2);
                                            if (count($unitParts) === 2) {
                                                $unitValue = is_numeric($unitParts[0]) ? $unitParts[0] : 1;
                                                $unitType = $unitParts[1];
                                            } else {
                                                $unitType = $product['unit'];
                                            }
                                        }
                                    ?>
                                    <input type="number" 
                                           step="0.01" 
                                           class="form-control" 
                                           id="unit_value" 
                                           name="unit_value" 
                                           value="<?php echo $unitValue; ?>" 
                                           min="0.01" 
                                           required
                                           oninput="updateUnitValue()">
                                </div>

                                <!-- Unit Type Selection -->
                                <div class="mb-2">
                                    <label for="unit_type" class="form-label small text-muted mb-1">Unit Type</label>
                                    <select class="form-select" 
                                            id="unit_type" 
                                            name="unit_type" 
                                            required 
                                            onchange="toggleOtherInput('unit_type')">
                                        <optgroup label="Common Units">
                                            <option value="pcs" <?php echo $unitType === 'pcs' ? 'selected' : ''; ?>>Pieces (pcs)</option>
                                            <option value="box" <?php echo $unitType === 'box' ? 'selected' : ''; ?>>Boxes (box)</option>
                                            <option value="pack" <?php echo $unitType === 'pack' ? 'selected' : ''; ?>>Packs (pack)</option>
                                        </optgroup>
                                        <optgroup label="Weight">
                                            <option value="kg" <?php echo $unitType === 'kg' ? 'selected' : ''; ?>>Kilograms (kg)</option>
                                            <option value="g" <?php echo $unitType === 'g' ? 'selected' : ''; ?>>Grams (g)</option>
                                        </optgroup>
                                        <optgroup label="Volume">
                                            <option value="L" <?php echo $unitType === 'L' ? 'selected' : ''; ?>>Liters (L)</option>
                                            <option value="ml" <?php echo $unitType === 'ml' ? 'selected' : ''; ?>>Milliliters (ml)</option>
                                        </optgroup>
                                        <option value="Other" <?php echo !in_array($unitType, ['pcs', 'box', 'pack', 'kg', 'g', 'L', 'ml']) ? 'selected' : ''; ?>>Other (specify)</option>
                                    </select>
                                </div>

                                <!-- Custom Unit Input (shown when 'Other' is selected) -->
                                <div id="unit_type_other_container" class="mb-2" style="display: <?php echo !in_array($unitType, ['pcs', 'box', 'pack', 'kg', 'g', 'L', 'ml', '']) ? 'block' : 'none'; ?>">
                                    <label for="unit_type_other" class="form-label small text-muted mb-1">Custom Unit</label>
                                    <div class="input-group">
                                        <input type="text" 
                                               class="form-control" 
                                               id="unit_type_other" 
                                               name="unit_type_other" 
                                               value="<?php echo !in_array($unitType, ['pcs', 'box', 'pack', 'kg', 'g', 'L', 'ml', '']) ? $unitType : ''; ?>" 
                                               placeholder="e.g., carton, bundle"
                                               oninput="updateUnitValue()">
                                    </div>
                                </div>

                                <!-- Preview of the final unit -->
                                <div class="mt-2">
                                    <small class="text-muted d-block mb-1">Preview:</small>
                                    <div class="p-2 bg-light rounded">
                                        <span id="unit_preview"><?php echo htmlspecialchars($product['unit']); ?></span>
                                    </div>
                                </div>

                                <input type="hidden" id="unit" name="unit" value="<?php echo htmlspecialchars($product['unit']); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="current_stock" class="form-label">Current Stock</label>
                                <input type="number" step="0.01" class="form-control" id="current_stock" 
                                       name="current_stock" value="<?php echo $product['current_stock']; ?>">
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this product?</p>
                <p class="mb-0"><strong>Product:</strong> <?php echo htmlspecialchars($product['name']); ?></p>
                <p class="text-danger mt-2"><i class="fas fa-exclamation-triangle me-2"></i>This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="delete.php?id=<?php echo $product_id; ?>" class="btn btn-danger">
                    <i class="fas fa-trash me-1"></i> Delete
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Function to update the hidden unit field and preview
        function updateUnitValue() {
            const unitValue = document.getElementById('unit_value').value;
            let unitType = document.getElementById('unit_type').value;
            let displayUnit = unitType;
            
            // If 'Other' is selected, use the custom input value
            if (unitType === 'Other') {
                const otherInput = document.getElementById('unit_type_other');
                if (otherInput) {
                    unitType = otherInput.value.trim() || 'unit';
                    displayUnit = unitType;
                }
            }
            
            // Update the hidden field for form submission
            const formattedValue = unitValue ? `${unitValue} ${unitType}` : '';
            document.getElementById('unit').value = formattedValue;
            
            // Update the preview
            const preview = document.getElementById('unit_preview');
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
            
            if (selectElement.value === 'Other') {
                otherContainer.style.display = 'block';
                if (otherInput) {
                    otherInput.required = true;
                    otherInput.focus();
                }
            } else {
                otherContainer.style.display = 'none';
                if (otherInput) {
                    otherInput.required = false;
                    otherInput.value = '';
                }
            }
            updateUnitValue();
        }

        // Check if we need to show 'Other' fields on page load
        window.addEventListener('load', function() {
            // Check category
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

            // Update unit preview on page load
            updateUnitValue();
        });

        // Update form submission to handle other fields
        document.getElementById('productForm').addEventListener('submit', function(e) {
            // Handle custom category first
            const categorySelect = document.getElementById('category');
            const categoryOtherInput = document.getElementById('category_other');
            
            // If 'Other' is selected or the current value is a custom category
            if (categorySelect.value === 'Other' || 
                (categorySelect.options[categorySelect.selectedIndex] && 
                 categorySelect.options[categorySelect.selectedIndex].value === 'Other')) {
                
                if (categoryOtherInput && categoryOtherInput.value.trim()) {
                    const customCategory = categoryOtherInput.value.trim();
                    
                    // Check if we already have this option to avoid duplicates
                    let optionExists = false;
                    for (let i = 0; i < categorySelect.options.length; i++) {
                        if (categorySelect.options[i].value === customCategory) {
                            optionExists = true;
                            break;
                        }
                    }
                    
                    // Add the custom category as a new option if it doesn't exist
                    if (!optionExists) {
                        const option = new Option(customCategory, customCategory);
                        categorySelect.add(option);
                    }
                    
                    // Set the value to the custom category
                    categorySelect.value = customCategory;
                } else {
                    // If 'Other' is selected but no value provided, prevent form submission
                    e.preventDefault();
                    alert('Please specify a category');
                    return false;
                }
            }
            
            // Handle custom unit type
            const unitTypeSelect = document.getElementById('unit_type');
            const unitTypeOtherInput = document.getElementById('unit_type_other');
            
            if (unitTypeSelect.value === 'Other') {
                if (unitTypeOtherInput && unitTypeOtherInput.value.trim()) {
                    // Update the hidden unit field with the custom unit
                    const unitValue = document.getElementById('unit_value').value;
                    const customUnit = unitTypeOtherInput.value.trim();
                    document.getElementById('unit').value = `${unitValue} ${customUnit}`;
                } else {
                    // If 'Other' is selected but no unit provided, prevent form submission
                    e.preventDefault();
                    alert('Please specify a unit type');
                    return false;
                }
            } else {
                // For standard units, ensure the unit is updated
                updateUnitValue();
            }
            
            // If we got here, all validations passed and the form will submit
        });
    });
</script>

<?php 
// Include footer
require_once __DIR__ . '/../../templates/footer.php';
?>
