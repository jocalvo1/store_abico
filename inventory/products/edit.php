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
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-list me-1"></i> View All
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
                                    <option value="" disabled>Select a category</option>
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
                                    <option value="" disabled>Select unit</option>
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
                    <h6 class="mb-0">Product Status</h6>
                </div>
                <div class="card-body">
                    <?php
                    $stock = $product['current_stock'];
                    $reorderLevel = $product['reorder_level'] ?? 0;
                    $stockClass = 'text-success';
                    $stockStatus = 'In Stock';
                    
                    if ($stock <= 0) {
                        $stockClass = 'text-danger';
                        $stockStatus = 'Out of Stock';
                    } elseif ($stock <= $reorderLevel) {
                        $stockClass = 'text-warning';
                        $stockStatus = 'Low Stock';
                    }
                    ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-light p-3 me-3">
                                <i class="fas fa-boxes fa-2x <?php echo $stockClass; ?>"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-0 <?php echo $stockClass; ?>">
                                <?php echo $stockStatus; ?>
                            </h5>
                            <small class="text-muted">
                                <?php echo number_format($stock, 2); ?> in stock
                                <?php if ($reorderLevel > 0): ?>
                                    (Reorder at: <?php echo $reorderLevel; ?>)
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    
                    <div class="progress mb-3" style="height: 10px;">
                        <?php
                        $maxStock = max($stock, $reorderLevel * 2, 10); // Ensure we have a reasonable max
                        $stockPercent = min(100, ($stock / $maxStock) * 100);
                        $reorderPercent = ($reorderLevel / $maxStock) * 100;
                        ?>
                        <div class="progress-bar bg-<?php echo str_replace('text-', '', $stockClass); ?>" 
                             role="progressbar" 
                             style="width: <?php echo $stockPercent; ?>%" 
                             aria-valuenow="<?php echo $stock; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="<?php echo $maxStock; ?>">
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <a href="view.php?id=<?php echo $product_id; ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-eye me-1"></i> View Product
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Danger Zone</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted">
                        <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                        Deleting this product will remove it permanently. This action cannot be undone.
                    </p>
                    <button type="button" class="btn btn-outline-danger btn-sm w-100" 
                            data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="fas fa-trash me-1"></i> Delete Product
                    </button>
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

<?php 
// Include footer
require_once __DIR__ . '/../../templates/footer.php';
?>
