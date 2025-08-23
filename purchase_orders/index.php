<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Include database connection
require_once '../includes/database.php';

// Get database connection
$conn = getDBConnection();


// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$search_params = [];

if (!empty($search)) {
    $search_condition = " WHERE p.po_number LIKE ? OR s.name LIKE ? OR s.contact_person LIKE ? OR p.status LIKE ? ";
    $search_term = "%$search%";
    $search_params = array_fill(0, 4, $search_term);
}

// Get all purchases with supplier info and item details
$purchases = [];
$query = "
    SELECT 
        p.*, 
        s.name as supplier_name,
        (
            SELECT GROUP_CONCAT(
                CONCAT(pi.quantity, 'x ', i.name) 
                ORDER BY i.name SEPARATOR '<br>'
            )
            FROM purchase_order_items pi
            JOIN items i ON pi.item_id = i.id
            WHERE pi.purchase_order_id = p.id
        ) as item_details,
        COUNT(DISTINCT pi.id) as item_count,
        p.total_amount,
        (SELECT COUNT(*) FROM deliveries d WHERE d.purchase_order_id = p.id) as delivery_count
    FROM purchase_orders p
    JOIN suppliers s ON p.supplier_id = s.id
    LEFT JOIN purchase_order_items pi ON p.id = pi.purchase_order_id
    $search_condition
    GROUP BY p.id, s.name
    ORDER BY p.created_at DESC
";

$stmt = $conn->prepare($query);
if (!empty($search_params)) {
    $stmt->bind_param(str_repeat('s', count($search_params)), ...$search_params);
}
$stmt->execute();
$result = $stmt->get_result();
$purchases = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<?php include __DIR__ . '/../templates/header.php'; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-up">
        <h1 class="h3 mb-0 mt-2">Purchase Orders</h1>
        <div class="d-flex align-items-center">
            <form action="" method="get" class="me-3 min-width-300px">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" 
                           class="form-control form-control-sm border-start-0 ps-0" 
                           name="search" 
                           placeholder="Search purchase orders..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           aria-label="Search purchase orders">
                    <?php if (!empty($search)): ?>
                        <a href="index.php" class="btn btn-sm btn-outline-danger border-start-0" title="Clear search">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
            <a href="export.php<?php echo $search !== '' ? ('?search=' . urlencode($search)) : ''; ?>" 
               class="btn btn-success btn-sm d-flex align-items-center me-2" title="Export to CSV" aria-label="Export to CSV">
                <i class="fas fa-file-excel me-1"></i>
                <span>Export CSV</span>
            </a>
            <a href="add.php" class="btn btn-primary btn-sm d-flex align-items-center">
                <i class="fas fa-plus me-1"></i> New Order
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
                <h6 class="m-0 font-weight-bold text-primary">Purchase Orders</h6>
                <div class="text-muted small">
                    <?php echo count($purchases); ?> of <?php echo count($purchases); ?> total
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (empty($purchases)): ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-file-invoice-dollar fa-3x text-muted"></i>
                    </div>
                    <h5 class="text-muted">No purchase orders found</h5>
                    <p class="text-muted mb-4">
                        Get started by creating a new purchase order
                    </p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> New Purchase Order
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-uppercase text-muted small fw-bold text-center width-1p d-none d-sm-table-cell">#</th>
                                <th class="text-uppercase text-muted small fw-bold">PO #</th>
                                <th class="text-uppercase text-muted small fw-bold">Supplier</th>
                                <th class="text-uppercase text-muted small fw-bold d-none d-md-table-cell">Date Ordered</th>
                                <th class="text-uppercase text-muted small fw-bold d-none d-lg-table-cell">Items</th>
                                <th class="text-uppercase text-muted small fw-bold text-center d-none d-md-table-cell">Deliveries</th>
                                <th class="text-uppercase text-muted small fw-bold text-end">Total</th>
                                <th class="text-uppercase text-muted small fw-bold text-center d-none d-sm-table-cell">Status</th>
                                <th class="text-uppercase text-muted small fw-bold text-end pe-3 d-none d-md-table-cell">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchases as $index => $purchase): 
                                $status_value = strtolower(trim($purchase['status'] ?? ''));
                                // Only two statuses are used: pending and completed. Default to pending styling.
                                $status_class = [
                                    'pending' => 'warning',
                                    'completed' => 'success',
                                ][$status_value] ?? 'warning';
                            ?>
                                <tr class="border-top" data-aos="fade-up" data-aos-delay="<?php echo ($index % 10) * 50; ?>">
                                    <td class="text-center text-muted d-none d-sm-table-cell"><?php echo $index + 1; ?></td>
                                    <td class="py-3">
                                        <a href="view.php?id=<?php echo $purchase['id']; ?>" class="text-decoration-none fw-medium">
                                            <?php echo htmlspecialchars($purchase['po_number']); ?>
                                        </a>
                                        <!-- Mobile-only details -->
                                        <div class="d-md-none small text-muted mt-1">
                                            <div>
                                                <i class="far fa-calendar-alt me-1"></i><?php echo date('M j, Y', strtotime($purchase['created_at'])); ?>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 mt-1">
                                                <span class="badge rounded-pill bg-<?php echo $purchase['delivery_count'] > 0 ? 'info' : 'light text-muted'; ?>">
                                                    <i class="fas fa-truck me-1"></i><?php echo $purchase['delivery_count']; ?>
                                                </span>
                                                <span class="badge bg-<?php echo $status_class; ?> bg-opacity-10 text-<?php echo $status_class; ?> border border-<?php echo $status_class; ?> border-opacity-25">
                                                    <?php echo ucfirst($status_value ?: 'pending'); ?>
                                                </span>
                                            </div>
                                            <div class="mt-2 d-flex flex-wrap gap-2">
                                                <a href="view.php?id=<?php echo $purchase['id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1">
                                                    <i class="fas fa-eye fa-xs"></i><span>View</span>
                                                </a>
                                                <a href="edit.php?id=<?php echo $purchase['id']; ?>" 
                                                   class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                                                    <i class="fas fa-edit fa-xs"></i><span>Edit</span>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="../ledger/suppliers/view.php?id=<?php echo $purchase['supplier_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($purchase['supplier_name']); ?>
                                        </a>
                                    </td>
                                    <td class="text-muted small d-none d-md-table-cell">
                                        <?php echo date('M j, Y', strtotime($purchase['created_at'])); ?>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        <?php if (!empty($purchase['item_details'])): ?>
                                            <div class="text-truncate" style="max-width: 200px;" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars(strip_tags($purchase['item_details'])); ?>">
                                                <?php echo $purchase['item_details']; ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo $purchase['item_count']; ?> item<?php echo $purchase['item_count'] != 1 ? 's' : ''; ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted small">No items</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center d-none d-md-table-cell">
                                        <span class="badge rounded-pill bg-<?php echo $purchase['delivery_count'] > 0 ? 'info' : 'light text-muted'; ?> px-3 py-1">
                                            <i class="fas fa-truck me-1"></i><?php echo $purchase['delivery_count']; ?>
                                        </span>
                                    </td>
                                    <td class="fw-medium text-end">
                                        ₱<?php echo number_format($purchase['total_amount'], 2); ?>
                                    </td>
                                    <td class="text-center d-none d-sm-table-cell">
                                        <?php
                                        $status_icon = [
                                            'pending' => 'clock',
                                            'completed' => 'check-double'
                                        ][$status_value] ?? 'clock';
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?> bg-opacity-10 text-<?php echo $status_class; ?> border border-<?php echo $status_class; ?> border-opacity-25 px-3 py-1">
                                            <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                                            <?php echo ucfirst($status_value ?: 'pending'); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-3 d-none d-md-table-cell">
                                        <div class="d-flex gap-1 justify-content-end">
                                            <a href="view.php?id=<?php echo $purchase['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary d-flex align-items-center justify-content-center gap-1" 
                                               title="View Details" data-bs-toggle="tooltip" data-bs-placement="top">
                                                <i class="fas fa-eye fa-xs"></i><span class="d-none d-lg-inline">View</span>
                                            </a>
                                            <a href="edit.php?id=<?php echo $purchase['id']; ?>" 
                                               class="btn btn-sm btn-outline-secondary d-flex align-items-center justify-content-center gap-1" 
                                               title="Edit Purchase Order" data-bs-toggle="tooltip" data-bs-placement="top">
                                                <i class="fas fa-edit fa-xs"></i><span class="d-none d-lg-inline">Edit</span>
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
<button type="button" id="backToTop" class="btn btn-primary rounded-circle back-to-top" aria-label="Back to top" title="Back to top">
    <i class="fas fa-arrow-up"></i>
    <span class="visually-hidden">Back to top</span>
</button>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>