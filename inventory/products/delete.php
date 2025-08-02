<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'You must be logged in to perform this action.';
    header('Location: ../../login.php');
    exit();
}

// Include required files
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

// Check if product exists before attempting to delete
$product = $productController->getById($product_id);
if (!$product) {
    $_SESSION['error'] = 'Product not found or already deleted.';
    header('Location: index.php');
    exit();
}

// Check if this is a POST request (confirmation)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token if you have CSRF protection
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    //     $_SESSION['error'] = 'Invalid request.';
    //     header('Location: index.php');
    //     exit();
    // }
    
    // Attempt to delete the product
    $result = $productController->delete($product_id);
    
    if ($result) {
        $_SESSION['success'] = 'Product deleted successfully.';
    } else {
        $_SESSION['error'] = 'Failed to delete product. Please try again.';
    }
    
    // Redirect back to the products list
    header('Location: index.php');
    exit();
}

// If not a POST request, show confirmation page
$pageTitle = 'Delete Product';
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Confirm Deletion</h5>
                </div>
                <div class="card-body">
                    <p>Are you sure you want to delete the following product?</p>
                    
                    <div class="alert alert-warning">
                        <h6 class="alert-heading"><?php echo htmlspecialchars($product['name']); ?></h6>
                        <div class="small mb-2">SKU: <?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></div>
                        <div class="small">Category: <?php echo htmlspecialchars($product['category']); ?></div>
                        <div class="small">Current Stock: <?php echo number_format($product['current_stock'], 2); ?> <?php echo htmlspecialchars($product['unit']); ?></div>
                    </div>
                    
                    <p class="text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone. All data related to this product will be permanently deleted.
                    </p>
                    
                    <form method="post" class="mt-4">
                        <!-- CSRF Token for security -->
                        <!-- <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"> -->
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash me-1"></i> Confirm Delete
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../templates/footer.php';
?>
