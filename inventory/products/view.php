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

// Flash messages
$successMessage = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$errorMessage = isset($_SESSION['error']) ? $_SESSION['error'] : null;
// Clear after reading
unset($_SESSION['success'], $_SESSION['error']);

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

    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($successMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($errorMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Product Information</h6>
                </div>
                <div class="card-body">
                    <?php 
                        $stock = $product['current_stock'];
                        $reorderLevel = $product['reorder_level'] ?? 0;
                        $stockStateClass = 'success';
                        $statusLabel = 'In Stock';
                        if ($stock <= 0) { $stockStateClass = 'danger'; $statusLabel = 'Out of Stock'; }
                        elseif ($reorderLevel > 0 && $stock <= $reorderLevel) { $stockStateClass = 'warning'; $statusLabel = 'Low Stock'; }
                        $progressPct = $reorderLevel > 0 ? min(100, max(0, ($stock / $reorderLevel) * 100)) : null;
                    ?>

                    <div class="row g-3 align-items-stretch">
                        <div class="col-lg-7">
                            <div class="mb-3">
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge rounded-pill bg-light text-dark">
                                        <i class="fas fa-tag me-1"></i>
                                        <?php echo !empty($product['category']) ? htmlspecialchars($product['category']) : 'Uncategorized'; ?>
                                    </span>
                                    <span class="badge rounded-pill bg-secondary-subtle text-dark">
                                        <i class="fas fa-box me-1"></i>
                                        <?php echo !empty($product['unit']) ? htmlspecialchars($product['unit']) : 'No unit'; ?>
                                    </span>
                                </div>
                            </div>

                            <div>
                                <h6 class="text-muted mb-2">Description</h6>
                                <div class="p-3 rounded border bg-light-subtle">
                                    <p class="mb-0">
                                        <?php echo !empty($product['description']) ? nl2br(htmlspecialchars($product['description'])) : '<span class="text-muted">No description provided.</span>'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="border rounded p-3 h-100 d-flex flex-column justify-content-between">
                                <div class="mb-3">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <h6 class="mb-1 text-muted">Inventory Status</h6>
                                        <span class="badge text-bg-<?php echo $stockStateClass; ?>">
                                            <i class="fas fa-circle me-1"></i><?php echo $statusLabel; ?>
                                        </span>
                                    </div>
                                    <div class="mt-2">
                                        <div class="d-flex justify-content-between small">
                                            <span>Stock</span>
                                            <span class="fw-semibold text-<?php echo $stockStateClass; ?>"><?php echo number_format($stock, 2); ?></span>
                                        </div>
                                        <?php if ($reorderLevel > 0): ?>
                                            <div class="d-flex justify-content-between small text-muted">
                                                <span>Reorder Level</span>
                                                <span><?php echo number_format($reorderLevel, 2); ?></span>
                                            </div>
                                            <div class="progress mt-2" style="height: 6px;">
                                                <div class="progress-bar bg-<?php echo $stockStateClass; ?>" role="progressbar" style="width: <?php echo number_format($progressPct, 0); ?>%" aria-valuenow="<?php echo (int)$progressPct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-muted small">Selling Price</div>
                                    <div class="display-6 fw-semibold">₱<?php echo number_format($product['selling_price'], 2); ?></div>
                                </div>
                            </div>
                        </div>
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
<!-- Back to Top Button -->
<button type="button" id="backToTop" class="btn btn-primary rounded-circle back-to-top" aria-label="Back to top" title="Back to top">
    <i class="fas fa-arrow-up"></i>
    <span class="visually-hidden">Back to top</span>
</button>

<?php 
// Include footer
require_once __DIR__ . '/../../templates/footer.php';
?>
