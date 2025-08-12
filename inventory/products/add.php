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

$productController = new ProductController();
$errors = [];
$product = [
    'name' => '',
    'description' => '',
    'category' => '',
    'unit' => '',
    'current_stock' => 0,
    'reorder_level' => 0,
    'selling_price' => 0.00
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $product['name'] = trim($_POST['name'] ?? '');
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
    
    // If no errors, save to database
    if (empty($errors)) {
        if ($productController->create($product)) {
            $_SESSION['success'] = "Product added successfully!";
            header("Location: index.php");
            exit();
        } else {
            $errors[] = "Error adding product. Please try again.";
        }
    }
}

// Include header
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Add New Product</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Products
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
                    <h6 class="mb-0">Product Information</h6>
                </div>
                <div class="card-body">
                    <form method="post" id="productForm">
                        <div class="mb-3">
                            <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($product['name']); ?>" required>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="category" name="category" required onchange="toggleOtherInput('category')">
                                    <option value="" disabled selected>Select a category</option>
                                    <option value="Electronics" <?php echo $product['category'] === 'Electronics' ? 'selected' : ''; ?>>Electronics</option>
                                    <option value="Clothing" <?php echo $product['category'] === 'Clothing' ? 'selected' : ''; ?>>Clothing</option>
                                    <option value="Beauty" <?php echo $product['category'] === 'Beauty' ? 'selected' : ''; ?>>Beauty</option>
                                    <option value="Health" <?php echo $product['category'] === 'Health' ? 'selected' : ''; ?>>Health</option>
                                    <option value="Pharmacy" <?php echo $product['category'] === 'Pharmacy' ? 'selected' : ''; ?>>Pharmacy</option>
                                    <option value="Food" <?php echo $product['category'] === 'Food' ? 'selected' : ''; ?>>Food</option>
                                    <option value="Beverages" <?php echo $product['category'] === 'Beverages' ? 'selected' : ''; ?>>Beverages</option>
                                    <option value="Snacks" <?php echo $product['category'] === 'Snacks' ? 'selected' : ''; ?>>Snacks</option>
                                    <option value="Drinks" <?php echo $product['category'] === 'Drinks' ? 'selected' : ''; ?>>Drinks</option>
                                    <option value="Bakery" <?php echo $product['category'] === 'Bakery' ? 'selected' : ''; ?>>Bakery</option>
                                    <option value="Dairy" <?php echo $product['category'] === 'Dairy' ? 'selected' : ''; ?>>Dairy</option>
                                    <option value="Frozen" <?php echo $product['category'] === 'Frozen' ? 'selected' : ''; ?>>Frozen</option>
                                    <option value="Produce" <?php echo $product['category'] === 'Produce' ? 'selected' : ''; ?>>Produce</option>
                                    <option value="Meat" <?php echo $product['category'] === 'Meat' ? 'selected' : ''; ?>>Meat</option>
                                    <option value="Seafood" <?php echo $product['category'] === 'Seafood' ? 'selected' : ''; ?>>Seafood</option>
                                    <option value="Household" <?php echo $product['category'] === 'Household' ? 'selected' : ''; ?>>Household</option>
                                    <option value="Cleaning" <?php echo $product['category'] === 'Cleaning' ? 'selected' : ''; ?>>Cleaning</option>
                                    <option value="Hardware" <?php echo $product['category'] === 'Hardware' ? 'selected' : ''; ?>>Hardware</option>
                                    <option value="Automotive" <?php echo $product['category'] === 'Automotive' ? 'selected' : ''; ?>>Automotive</option>
                                    <option value="Office" <?php echo $product['category'] === 'Office' ? 'selected' : ''; ?>>Office</option>
                                    <option value="Other">Other (please specify)</option>
                                </select>
                                <div id="category_other_container" class="mt-2" style="display: none;">
                                    <label for="category_other" class="form-label">Specify Category</label>
                                    <input type="text" class="form-control" id="category_other" name="category_other" placeholder="Enter category name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label d-block mb-2">Unit <span class="text-danger">*</span></label>
                                
                                <!-- Unit Value Input -->
                                <div class="mb-2">
                                    <label for="unit_value" class="form-label small text-muted mb-1">Quantity</label>
                                    <input type="number" 
                                           step="0.01" 
                                           class="form-control" 
                                           id="unit_value" 
                                           name="unit_value" 
                                           value="1" 
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
                                            <option value="pcs" <?php echo $product['unit'] === 'pcs' ? 'selected' : ''; ?>>Pieces (pcs)</option>
                                            <option value="box" <?php echo $product['unit'] === 'box' ? 'selected' : ''; ?>>Boxes (box)</option>
                                            <option value="pack" <?php echo $product['unit'] === 'pack' ? 'selected' : ''; ?>>Packs (pack)</option>
                                            <option value="bottle" <?php echo $product['unit'] === 'bottle' ? 'selected' : ''; ?>>Bottle</option>
                                            <option value="can" <?php echo $product['unit'] === 'can' ? 'selected' : ''; ?>>Can</option>
                                            <option value="sachet" <?php echo $product['unit'] === 'sachet' ? 'selected' : ''; ?>>Sachet</option>
                                            <option value="tray" <?php echo $product['unit'] === 'tray' ? 'selected' : ''; ?>>Tray</option>
                                            <option value="bag" <?php echo $product['unit'] === 'bag' ? 'selected' : ''; ?>>Bag</option>
                                            <option value="roll" <?php echo $product['unit'] === 'roll' ? 'selected' : ''; ?>>Roll</option>
                                            <option value="set" <?php echo $product['unit'] === 'set' ? 'selected' : ''; ?>>Set</option>
                                            <option value="pair" <?php echo $product['unit'] === 'pair' ? 'selected' : ''; ?>>Pair</option>
                                            <option value="dozen" <?php echo $product['unit'] === 'dozen' ? 'selected' : ''; ?>>Dozen</option>
                                        </optgroup>
                                        <optgroup label="Weight">
                                            <option value="kg" <?php echo $product['unit'] === 'kg' ? 'selected' : ''; ?>>Kilograms (kg)</option>
                                            <option value="g" <?php echo $product['unit'] === 'g' ? 'selected' : ''; ?>>Grams (g)</option>
                                            <option value="lb" <?php echo $product['unit'] === 'lb' ? 'selected' : ''; ?>>Pounds (lb)</option>
                                            <option value="oz" <?php echo $product['unit'] === 'oz' ? 'selected' : ''; ?>>Ounces (oz)</option>
                                        </optgroup>
                                        <optgroup label="Volume">
                                            <option value="L" <?php echo $product['unit'] === 'L' ? 'selected' : ''; ?>>Liters (L)</option>
                                            <option value="mL" <?php echo $product['unit'] === 'mL' ? 'selected' : ''; ?>>Milliliters (mL)</option>
                                            <option value="gal" <?php echo $product['unit'] === 'gal' ? 'selected' : ''; ?>>Gallons (gal)</option>
                                        </optgroup>
                                        <optgroup label="Length/Size">
                                            <option value="m" <?php echo $product['unit'] === 'm' ? 'selected' : ''; ?>>Meters (m)</option>
                                            <option value="cm" <?php echo $product['unit'] === 'cm' ? 'selected' : ''; ?>>Centimeters (cm)</option>
                                            <option value="mm" <?php echo $product['unit'] === 'mm' ? 'selected' : ''; ?>>Millimeters (mm)</option>
                                            <option value="in" <?php echo $product['unit'] === 'in' ? 'selected' : ''; ?>>Inches (in)</option>
                                            <option value="ft" <?php echo $product['unit'] === 'ft' ? 'selected' : ''; ?>>Feet (ft)</option>
                                        </optgroup>
                                        <option value="Other">Other (specify)</option>
                                    </select>
                                </div>

                                <!-- Custom Unit Input (shown when 'Other' is selected) -->
                                <div id="unit_type_other_container" class="mb-2" style="display: none;">
                                    <label for="unit_type_other" class="form-label small text-muted mb-1">Custom Unit</label>
                                    <div class="input-group">
                                        <input type="text" 
                                               class="form-control" 
                                               id="unit_type_other" 
                                               name="unit_type_other" 
                                               placeholder="e.g., carton, bundle"
                                               oninput="updateUnitValue()">
                                    </div>
                                </div>

                                <!-- Preview of the final unit -->
                                <div class="mt-2">
                                    <small class="text-muted d-block mb-1">Preview:</small>
                                    <div class="p-2 bg-light rounded">
                                        <span id="unit_preview">1 pcs</span>
                                    </div>
                                </div>

                                <input type="hidden" id="unit" name="unit" value="">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="current_stock" class="form-label">Stock <span class="text-muted">(Optional)</span></label>
                                <input type="number" step="0.01" class="form-control" id="current_stock" 
                                       name="current_stock" value="<?php echo $product['current_stock']; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="reorder_level" class="form-label">Reorder Level <span class="text-muted">(Optional)</span></label>
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

                        <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="description" name="description" 
                                     rows="3"><?php echo htmlspecialchars($product['description']); ?></textarea>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="index.php" class="btn btn-secondary me-md-2">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Product
                            </button>
                        </div>
                    </form>
                    <script>
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

                        // Add event listeners
                        document.getElementById('unit_value').addEventListener('input', updateUnitValue);
                        document.getElementById('unit_type').addEventListener('change', updateUnitValue);
                        
                        // Add input event for other fields
                        document.getElementById('category_other')?.addEventListener('input', function() {
                            const categorySelect = document.getElementById('category');
                            if (categorySelect.value === 'Other') {
                                categorySelect.options[categorySelect.selectedIndex].text = `Other: ${this.value}`;
                            }
                        });
                        
                        document.getElementById('unit_type_other')?.addEventListener('input', updateUnitValue);
                        
                        // Initialize the form on page load
                        document.addEventListener('DOMContentLoaded', function() {
                            toggleOtherInput('category');
                            toggleOtherInput('unit_type');
                            updateUnitValue();
                            
                            // Update form submission to handle other fields
                            document.getElementById('productForm').addEventListener('submit', function(e) {
                                // Handle custom category first
                                const categorySelect = document.getElementById('category');
                                const categoryOtherInput = document.getElementById('category_other');
                                
                                if (categorySelect.value === 'Other') {
                                    if (categoryOtherInput && categoryOtherInput.value.trim()) {
                                        // Create a new option with the custom category
                                        const customCategory = categoryOtherInput.value.trim();
                                        
                                        // Check if we already have this option to avoid duplicates
                                        let optionExists = false;
                                        for (let i = 0; i < categorySelect.options.length; i++) {
                                            if (categorySelect.options[i].value === customCategory) {
                                                optionExists = true;
                                                break;
                                            }
                                        }
                                        
                                        // Add the custom category as a new option and select it
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
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Quick Tips</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled small">
                        <li class="mb-2">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            Fields marked with <span class="text-danger">*</span> are required.
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-tags text-primary me-2"></i>
                            <strong>Custom Categories:</strong> Select "Other" and type to create a new category.
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-balance-scale text-primary me-2"></i>
                            <strong>Unit Specification:</strong> Enter quantity and select unit type (e.g., 5 kg, 10 pcs).
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-ruler-combined text-primary me-2"></i>
                            <strong>Custom Units:</strong> Choose "Other" to enter a custom unit type.
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-eye text-primary me-2"></i>
                            Check the preview to see how the unit will be displayed.
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-boxes text-primary me-2"></i>
                            Set a reorder level to get alerts when stock is low.
                        </li>
                        <li>
                            <i class="fas fa-money-bill-wave text-primary me-2"></i>
                            Enter the selling price in the local currency (₱).
                        </li>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Include footer
require_once __DIR__ . '/../../templates/footer.php';
?>