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
    $_SESSION['error'] = 'Product not found or you do not have permission to view it.';
    header('Location: index.php');
    exit();
}

// Format timestamps
$created_at = new DateTime($product['created_at']);
$updated_at = new DateTime($product['updated_at']);

// Include header
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <?php echo htmlspecialchars($product['name']); ?>
            <small class="text-muted">Product Details</small>
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="index.php" class="btn btn-sm btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-1"></i> Back to Products
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Product Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Basic Information</h6>
                            <dl class="mb-0">
                                <dt>SKU</dt>
                                <dd><?php echo !empty($product['sku']) ? htmlspecialchars($product['sku']) : '<span class="text-muted">N/A</span>'; ?></dd>
                                
                                <dt class="mt-3">Category</dt>
                                <dd>
                                    <span class="badge bg-light text-dark">
                                        <?php echo !empty($product['category']) ? htmlspecialchars($product['category']) : '<span class="text-muted">N/A</span>'; ?>
                                    </span>
                                </dd>
                                
                                <dt class="mt-3">Unit</dt>
                                <dd><?php echo !empty($product['unit']) ? htmlspecialchars($product['unit']) : '<span class="text-muted">N/A</span>'; ?></dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <h6>Inventory</h6>
                            <dl class="mb-0">
                                <dt>Current Stock</dt>
                                <dd>
                                    <?php 
                                    $stock = $product['current_stock'];
                                    $reorderLevel = $product['reorder_level'] ?? 0;
                                    $stockClass = 'text-success';
                                    
                                    if ($stock <= 0) {
                                        $stockClass = 'text-danger';
                                    } elseif ($stock <= $reorderLevel) {
                                        $stockClass = 'text-warning';
                                    }
                                    ?>
                                    <span class="fw-bold <?php echo $stockClass; ?>">
                                        <?php echo number_format($stock, 2); ?>
                                    </span>
                                    <?php if ($reorderLevel > 0): ?>
                                        <small class="text-muted">
                                            (Reorder at: <?php echo $reorderLevel; ?>)
                                        </small>
                                    <?php endif; ?>
                                </dd>
                                
                                <dt class="mt-3">Selling Price</dt>
                                <dd class="fw-bold">₱<?php echo number_format($product['selling_price'], 2); ?></dd>
                            </dl>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h6>Description</h6>
                        <p class="mb-0">
                            <?php echo !empty($product['description']) ? nl2br(htmlspecialchars($product['description'])) : '<span class="text-muted">No description provided.</span>'; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Timestamps</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled small mb-4">
                        <li class="mb-2">
                            <i class="far fa-calendar-plus text-muted me-2"></i>
                            <strong>Created:</strong> 
                            <span class="text-muted">
                                <?php echo $created_at->format('M j, Y \a\t g:i A'); ?>
                            </span>
                        </li>
                        <li class="mb-3">
                            <i class="far fa-calendar-check text-muted me-2"></i>
                            <strong>Last Updated:</strong> 
                            <span class="text-muted">
                                <?php echo $updated_at->format('M j, Y \a\t g:i A'); ?>
                            </span>
                        </li>
                    </ul>
                    <div class="d-grid">
                        <a href="edit.php?id=<?php echo $product_id; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit me-1"></i> Edit Product
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<?php 
// Include footer
require_once __DIR__ . '/../../templates/footer.php';
?>
