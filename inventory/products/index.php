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

// Include required files
require_once __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../controller/product/ProductController.php';

// Initialize ProductController
$productController = new ProductController();

// Handle search
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get products based on search
if (!empty($searchTerm)) {
    $products = $productController->search($searchTerm);
} else {
    $products = $productController->getAll();
}

// Get products as array
$productsArray = [];
if (is_object($products) && method_exists($products, 'fetch_assoc')) {
    while ($row = $products->fetch_assoc()) {
        $productsArray[] = $row;
    }
}

// Check for success/error messages
$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-up">
        <div class="row">
            <h1 class="h3 mb-0 mt-2">Products</h1>
        </div>
        <div class="d-flex align-items-center">
            <form action="" method="get" class="me-3 min-width-300px">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" 
                           class="form-control form-control-sm border-start-0 ps-0" 
                           name="search" 
                           placeholder="Search products..." 
                           value="<?php echo htmlspecialchars($searchTerm); ?>"
                           aria-label="Search products">
                    <?php if (!empty($searchTerm)): ?>
                        <a href="index.php" class="btn btn-sm btn-outline-danger border-start-0" title="Clear search">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
            <a href="add.php" class="btn btn-primary btn-sm d-flex align-items-center">
                <i class="fas fa-plus me-1"></i> Add New
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

    <div class="card border-0 shadow-sm" data-aos="fade-up" data-aos-delay="100">
        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Product List</h6>
                <div class="text-muted small">
                    <?php echo count($productsArray); ?> of <?php echo count($productsArray); ?> total
                </div>
            </div>
        </div>
        
        <div class="card-body p-0">
            <?php if (empty($productsArray)): ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-box-open fa-3x text-muted"></i>
                    </div>
                    <h5 class="text-muted">No products found</h5>
                    <p class="text-muted mb-4">
                        Get started by adding a new product
                    </p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Add New Product
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-uppercase text-muted small fw-bold text-center width-1p">#</th>
                                <th class="text-uppercase text-muted small fw-bold">Name</th>
                                <th class="text-uppercase text-muted small fw-bold">Description</th>
                                <th class="text-uppercase text-muted small fw-bold">Category</th>
                                <th class="text-uppercase text-muted small fw-bold">Unit</th>
                                <th class="text-uppercase text-muted small fw-bold text-end">Stock</th>
                                <th class="text-uppercase text-muted small fw-bold text-end">Price</th>
                                <th class="text-uppercase text-muted small fw-bold text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rowNumber = 1; ?>
                            <?php foreach ($productsArray as $product): 
                                // Determine stock status
                                $stock = $product['current_stock'];
                                $reorderLevel = $product['reorder_level'] ?? 0;
                                $stockClass = 'text-success';
                                
                                if ($stock <= 0) {
                                    $stockClass = 'text-danger';
                                } elseif ($stock <= $reorderLevel) {
                                    $stockClass = 'text-warning';
                                }
                            ?>
                                <tr>
                                    <td class="text-center text-muted"><?php echo $rowNumber++; ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <div class="small text-muted">SKU: <?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td class="text-muted">
                                        <?php 
                                        $description = $product['description'];
                                        echo strlen($description) > 50 ? 
                                            htmlspecialchars(substr($description, 0, 50)) . '...' : 
                                            htmlspecialchars($description);
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php echo htmlspecialchars($product['category']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center text-muted"><?php echo htmlspecialchars($product['unit']); ?></td>
                                    <td class="text-end">
                                        <span class="fw-bold <?php echo $stockClass; ?>">
                                            <?php echo number_format($stock, 0); ?>
                                        </span>
                                        <?php if ($stock <= $reorderLevel && $reorderLevel > 0): ?>
                                            <div class="small text-muted">
                                                Reorder at: <?php echo $reorderLevel; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold">
                                        ₱<?php echo number_format($product['selling_price'], 2); ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-flex gap-1 justify-content-end">
                                            <a href="view.php?id=<?php echo $product['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary d-flex align-items-center justify-content-center gap-1" 
                                               title="View Details" data-bs-toggle="tooltip" data-bs-placement="top">
                                                <i class="fas fa-eye fa-xs"></i><span>View</span>
                                            </a>
                                            <a href="edit.php?id=<?php echo $product['id']; ?>" 
                                               class="btn btn-sm btn-outline-success d-flex align-items-center justify-content-center gap-1" 
                                               title="Edit Product" data-bs-toggle="tooltip" data-bs-placement="top">
                                                <i class="fas fa-edit fa-xs"></i><span>Edit</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../templates/footer.php';
?>