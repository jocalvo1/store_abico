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
    'sku' => '',
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
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($product['name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="sku" class="form-label">SKU</label>
                                <input type="text" class="form-control" id="sku" name="sku" 
                                       value="<?php echo htmlspecialchars($product['sku']); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" 
                                     rows="3"><?php echo htmlspecialchars($product['description']); ?></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="" disabled selected>Select a category</option>
                                    <option value="Electronics" <?php echo $product['category'] === 'Electronics' ? 'selected' : ''; ?>>Electronics</option>
                                    <option value="Clothing" <?php echo $product['category'] === 'Clothing' ? 'selected' : ''; ?>>Clothing</option>
                                    <option value="Food" <?php echo $product['category'] === 'Food' ? 'selected' : ''; ?>>Food</option>
                                    <option value="Beverages" <?php echo $product['category'] === 'Beverages' ? 'selected' : ''; ?>>Beverages</option>
                                    <option value="Other" <?php echo $product['category'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="unit" class="form-label">Unit <span class="text-danger">*</span></label>
                                <select class="form-select" id="unit" name="unit" required>
                                    <option value="" disabled selected>Select unit</option>
                                    <option value="pcs" <?php echo $product['unit'] === 'pcs' ? 'selected' : ''; ?>>Pieces</option>
                                    <option value="kg" <?php echo $product['unit'] === 'kg' ? 'selected' : ''; ?>>Kilograms</option>
                                    <option value="g" <?php echo $product['unit'] === 'g' ? 'selected' : ''; ?>>Grams</option>
                                    <option value="L" <?php echo $product['unit'] === 'L' ? 'selected' : ''; ?>>Liters</option>
                                    <option value="ml" <?php echo $product['unit'] === 'ml' ? 'selected' : ''; ?>>Milliliters</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="current_stock" class="form-label">Initial Stock</label>
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
                            <a href="index.php" class="btn btn-secondary me-md-2">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Product
                            </button>
                        </div>
                    </form>
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
                            <i class="fas fa-tag text-primary me-2"></i>
                            SKU is optional but recommended for inventory tracking.
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-boxes text-primary me-2"></i>
                            Set a reorder level to get alerts when stock is low.
                        </li>
                        <li>
                            <i class="fas fa-money-bill-wave text-primary me-2"></i>
                            Enter the selling price in the local currency.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Include footer
require_once __DIR__ . '/../../templates/footer.php';
?>
