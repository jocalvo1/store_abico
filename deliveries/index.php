<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: /../login.php');
    exit();
}

// Include required files
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../controller/delivery/DeliveryController.php';



// Get database connection
$conn = getDBConnection();
$deliveryController = new DeliveryController($conn);

// Handle AJAX request for delivery items
if (isset($_GET['action']) && $_GET['action'] === 'get_delivery_items') {
    $deliveryController->ajaxGetDeliveryItems();
    exit();
}

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get deliveries based on search
if (!empty($search)) {
    $result = $deliveryController->search($search);
} else {
    $result = $deliveryController->getAll();
}

// Get deliveries as array
$deliveries = [];
if (is_object($result) && method_exists($result, 'fetch_all')) {
    $deliveries = $result->fetch_all(MYSQLI_ASSOC);
}

// Close the database connection
$conn->close();
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-up">
        <div class="row">
            <h1 class="h3 mb-0 mt-2">Deliveries</h1>
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
                           placeholder="Search deliveries..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           aria-label="Search deliveries">
                    <?php if (!empty($search)): ?>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-sm btn-outline-danger border-start-0" title="Clear search">
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
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <div class="card border-0 shadow-sm" data-aos="fade-up" data-aos-delay="100">
        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Delivery List</h6>
                <div class="text-muted small">
                    <?php echo count($deliveries); ?> of <?php echo count($deliveries); ?> total
                </div>
            </div>
        </div>
        
        <div class="card-body p-0">
            <?php if (empty($deliveries)): ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-truck fa-3x text-muted"></i>
                    </div>
                    <h5 class="text-muted">No deliveries found</h5>
                    <p class="text-muted mb-4">
                        Get started by adding a new delivery
                    </p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Add New Delivery
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-uppercase text-muted small fw-bold text-center width-1p">#</th>
                                <th class="text-uppercase text-muted small fw-bold">PO #</th>
                                <th class="text-uppercase text-muted small fw-bold">Supplier</th>
                                <th class="text-uppercase text-muted small fw-bold">Date Delivered</th>
                                <th class="text-uppercase text-muted small fw-bold">Items</th>
                                <th class="text-uppercase text-muted small fw-bold text-end">Value</th>
                                <th class="text-uppercase text-muted small fw-bold">Status</th>
                                <th class="text-uppercase text-muted small fw-bold text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deliveries as $index => $delivery): 
                                $status_class = [
                                    'pending' => 'warning',
                                    'delivered' => 'success',
                                    'cancelled' => 'danger'
                                ][$delivery['status']] ?? 'secondary';
                            ?>
                                <tr class="border-top" data-aos="fade-up" data-aos-delay="<?php echo ($index % 10) * 50; ?>">
                                    <td class="text-center text-muted"><?php echo $delivery['id']; ?></td>
                                    <td>
                                        <a href="../purchase_orders/view.php?id=<?php echo $delivery['purchase_order_id']; ?>" 
                                           class="text-decoration-none fw-medium">
                                            <?php echo htmlspecialchars($delivery['po_number']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="../ledger/suppliers/view.php?id=<?php echo $delivery['supplier_id']; ?>" 
                                           class="text-decoration-none">
                                            <?php echo htmlspecialchars($delivery['supplier_name']); ?>
                                        </a>
                                    </td>
                                    <td class="text-muted">
                                        <?php echo date('M d, Y', strtotime($delivery['delivery_date'])); ?>
                                    </td>
                                    <td class="text-muted" title="<?php echo htmlspecialchars($delivery['items_list'] ?? 'No items'); ?>">
                                        <?php 
                                        if (!empty($delivery['items_list'])) {
                                            $items = explode(', ', $delivery['items_list']);
                                            if (count($items) > 2) {
                                                echo htmlspecialchars(implode(', ', array_slice($items, 0, 2)) . ' +' . (count($items) - 2) . ' more');
                                            } else {
                                                echo htmlspecialchars($delivery['items_list']);
                                            }
                                        } else {
                                            echo 'No items';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-end text-muted">
                                        ₱<?php echo number_format($delivery['total_value'] ?? 0, 2); ?>
                                    </td>
                                    <td style="width: 150px;">
                                        <?php
                                        $status_icon = [
                                            'pending' => 'clock',
                                            'delivered' => 'check-circle',
                                            'cancelled' => 'times-circle'
                                        ][$delivery['status']] ?? 'question-circle';
                                        ?>
                                        <div class="d-flex justify-content-center">
                                            <span class="badge bg-<?php echo $status_class; ?> bg-opacity-10 text-<?php echo $status_class; ?> border border-<?php echo $status_class; ?> border-opacity-25 px-3 py-1">
                                                <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                                                <?php echo ucfirst($delivery['status']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-end pe-3" style="width: 200px;">
                                        <div class="d-flex gap-1 justify-content-end">
                                            <a href="view.php?id=<?php echo $delivery['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary d-flex align-items-center justify-content-center gap-1" 
                                               title="View Details" data-bs-toggle="tooltip" data-bs-placement="top">
                                                <i class="fas fa-eye fa-xs"></i><span>View</span>
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
require_once __DIR__ . '/../templates/footer.php'; 
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchForm = document.createElement('form');
    searchForm.method = 'get';
    searchForm.style.display = 'none';
    searchForm.innerHTML = '<input type="hidden" name="search" id="searchValue">';
    document.body.appendChild(searchForm);

    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });
    }
    
    function performSearch() {
        if (searchInput) {
            document.getElementById('searchValue').value = searchInput.value.trim();
            searchForm.submit();
        }
    }

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php 
// Include footer
require_once __DIR__ . '/../templates/footer.php'; 
?>