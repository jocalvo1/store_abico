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

// Handle search, status and date filters (same format as stock_movements)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$dateFrom = isset($_GET['from']) ? trim($_GET['from']) : '';
$dateTo = isset($_GET['to']) ? trim($_GET['to']) : '';

// Normalize dates (YYYY-MM-DD)
if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) { $dateFrom = ''; }
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) { $dateTo = ''; }

// Get deliveries based on filters
if (!empty($search) || !empty($status) || !empty($dateFrom) || !empty($dateTo)) {
    if (method_exists($deliveryController, 'filterDeliveries')) {
        $result = $deliveryController->filterDeliveries($search, $status, $dateFrom, $dateTo);
    } else {
        // Fallback to text search if controller isn't updated
        $result = $deliveryController->search($search);
    }
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
    <div class="d-flex flex-column flex-md-row align-items-center justify-content-center justify-content-md-between mb-4" data-aos="fade-up">
        <h1 class="h3 mb-2 mb-md-0 mt-2 mt-md-0 text-center text-md-start">Deliveries</h1>
        <div class="d-flex justify-content-center justify-content-md-end gap-2">
            <?php 
                // Build query string from current filters for export link
                $qs = http_build_query([
                    'search' => $search,
                    'status' => $status,
                    'from' => $dateFrom,
                    'to' => $dateTo,
                ]);
            ?>
            <a class="btn btn-success btn-sm" href="export.php<?php echo $qs ? ('?' . $qs) : ''; ?>">
                <i class="fas fa-file-excel me-1"></i> Export
            </a>
            <a href="add.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Add New
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4" data-aos="fade-up" data-aos-delay="50">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="PO #, supplier, delivered by">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="pending" <?php echo $status==='pending'?'selected':''; ?>>Pending</option>
                        <option value="delivered" <?php echo $status==='delivered'?'selected':''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $status==='cancelled'?'selected':''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" class="form-control" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" class="form-control" name="to" value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
                <div class="col-12 mt-2 d-flex gap-2 flex-wrap">
                    <button class="btn btn-primary">Filter</button>
                    <a class="btn btn-outline-secondary" href="index.php">Reset</a>
                </div>
            </form>
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
                                <th class="text-uppercase text-muted small fw-bold text-center width-1p d-none d-sm-table-cell">#</th>
                                <th class="text-uppercase text-muted small fw-bold">PO #</th>
                                <th class="text-uppercase text-muted small fw-bold d-none d-md-table-cell">Supplier</th>
                                <th class="text-uppercase text-muted small fw-bold d-none d-md-table-cell">Date Delivered</th>
                                <th class="text-uppercase text-muted small fw-bold d-none d-lg-table-cell">Items</th>
                                <th class="text-uppercase text-muted small fw-bold text-end">Value</th>
                                <th class="text-uppercase text-muted small fw-bold d-none d-md-table-cell">Status</th>
                                <th class="text-uppercase text-muted small fw-bold text-end pe-3 d-none d-md-table-cell">Actions</th>
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
                                    <td class="text-center text-muted d-none d-sm-table-cell"><?php echo $delivery['id']; ?></td>
                                    <td>
                                        <a href="../purchase_orders/view.php?id=<?php echo $delivery['purchase_order_id']; ?>" 
                                           class="text-decoration-none fw-medium">
                                            <?php echo htmlspecialchars($delivery['po_number']); ?>
                                        </a>
                                        <!-- Mobile-only details -->
                                        <div class="d-md-none small text-muted mt-1">
                                            <div class="d-flex flex-column gap-1">
                                                <div>
                                                    <i class="fas fa-user-tag me-1"></i>
                                                    <a href="../ledger/suppliers/view.php?id=<?php echo $delivery['supplier_id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($delivery['supplier_name']); ?>
                                                    </a>
                                                </div>
                                                <div>
                                                    <i class="far fa-calendar-alt me-1"></i>
                                                    <?php echo date('M d, Y', strtotime($delivery['delivery_date'])); ?>
                                                </div>
                                                <div class="text-truncate" style="max-width: 95vw;">
                                                    <i class="fas fa-box-open me-1"></i>
                                                    <?php 
                                                    if (!empty($delivery['items_list'])) {
                                                        $items = explode(', ', $delivery['items_list']);
                                                        echo htmlspecialchars(count($items) > 2 ? (implode(', ', array_slice($items, 0, 2)) . ' +' . (count($items) - 2) . ' more') : $delivery['items_list']);
                                                    } else {
                                                        echo 'No items';
                                                    }
                                                    ?>
                                                </div>
                                                <div>
                                                    <?php
                                                    $status_icon = [
                                                        'pending' => 'clock',
                                                        'delivered' => 'check-circle',
                                                        'cancelled' => 'times-circle'
                                                    ][$delivery['status']] ?? 'question-circle';
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_class; ?> text-uppercase">
                                                        <i class="fas fa-<?php echo $status_icon; ?> me-1"></i><?php echo ucfirst($delivery['status']); ?>
                                                    </span>
                                                </div>
                                                <div class="mt-1 d-flex flex-wrap gap-2">
                                                    <a href="view.php?id=<?php echo $delivery['id']; ?>" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                                                        <i class="fas fa-eye fa-xs"></i><span>View</span>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <a href="../ledger/suppliers/view.php?id=<?php echo $delivery['supplier_id']; ?>" 
                                           class="text-decoration-none">
                                            <?php echo htmlspecialchars($delivery['supplier_name']); ?>
                                        </a>
                                    </td>
                                    <td class="text-muted d-none d-md-table-cell">
                                        <?php echo date('M d, Y', strtotime($delivery['delivery_date'])); ?>
                                    </td>
                                    <td class="text-muted d-none d-lg-table-cell" title="<?php echo htmlspecialchars($delivery['items_list'] ?? 'No items'); ?>">
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
                                    <td class="d-none d-md-table-cell" style="width: 150px;">
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
                                    <td class="text-end pe-3 d-none d-md-table-cell" style="width: 200px;">
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
<!-- Back to Top Button -->
<button type="button" id="backToTop" class="btn btn-primary rounded-circle back-to-top" aria-label="Back to top" title="Back to top">
    <i class="fas fa-arrow-up"></i>
    <span class="visually-hidden">Back to top</span>
</button>
<?php 
// Include footer
require_once __DIR__ . '/../templates/footer.php'; 
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
