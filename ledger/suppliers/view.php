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

// Check if ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No supplier ID provided.";
    header("Location: index.php");
    exit();
}

$supplier_id = intval($_GET['id']);
$conn = getDBConnection();

// Fetch supplier details
$supplier = null;
$stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Supplier not found.";
    header("Location: index.php");
    exit();
}

$supplier = $result->fetch_assoc();
$stmt->close();

// Fetch purchase statistics for this supplier
$statsStmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status = 'pending' OR status = 'approved' THEN 1 ELSE 0 END) as active_orders,
        SUM(total_amount) as total_spent,
        MAX(order_date) as last_order_date
    FROM purchase_orders 
    WHERE supplier_id = ?
");
$statsStmt->bind_param("i", $supplier_id);
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
$stats = $statsResult->fetch_assoc();
$statsStmt->close();

// Fetch purchase orders for this supplier with item names
$purchases = [];
$purchaseStmt = $conn->prepare("
    SELECT 
        po.id, 
        po.po_number, 
        po.order_date, 
        po.status, 
        po.total_amount,
        GROUP_CONCAT(p.name SEPARATOR ', ') as items
    FROM purchase_orders po
    LEFT JOIN purchase_order_items poi ON po.id = poi.purchase_order_id
    LEFT JOIN items p ON poi.item_id = p.id
    WHERE po.supplier_id = ?
    GROUP BY po.id, po.po_number, po.order_date, po.status, po.total_amount
    ORDER BY po.order_date DESC
");
$purchaseStmt->bind_param("i", $supplier_id);
$purchaseStmt->execute();
$purchaseResult = $purchaseStmt->get_result();

if ($purchaseResult) {
    while ($row = $purchaseResult->fetch_assoc()) {
        $purchases[] = $row;
    }
}
$purchaseStmt->close();
$conn->close();

// Include header
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <?php echo htmlspecialchars($supplier['name']); ?>
            <small class="text-muted">Supplier Details</small>
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="edit.php?id=<?php echo $supplier_id; ?>" class="btn btn-sm btn-outline-secondary me-2">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Suppliers
            </a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Supplier Information</h6>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <!-- Contact Information Column -->
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <h5 class="mb-3 border-bottom pb-2"><i class="fas fa-address-card me-2"></i>Contact Information</h5>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0 me-3 text-primary">
                                                <i class="fas fa-user-tie fa-lg"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 text-muted small">Contact Person</h6>
                                                <p class="mb-0"><?php echo $supplier['contact_person'] ? htmlspecialchars($supplier['contact_person']) : '<span class="text-muted">Not specified</span>'; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0 me-3 text-primary">
                                                <i class="fas fa-phone fa-lg"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 text-muted small">Phone</h6>
                                                <p class="mb-0"><?php echo $supplier['contact_number'] ? '<a href="tel:' . htmlspecialchars($supplier['contact_number']) . '" class="text-decoration-none">' . htmlspecialchars($supplier['contact_number']) . '</a>' : '<span class="text-muted">Not provided</span>'; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0 me-3 text-primary">
                                                <i class="fas fa-envelope fa-lg"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 text-muted small">Email</h6>
                                                <p class="mb-0 text-truncate"><?php echo $supplier['email'] ? '<a href="mailto:' . htmlspecialchars($supplier['email']) . '" class="text-decoration-none">' . htmlspecialchars($supplier['email']) . '</a>' : '<span class="text-muted">Not provided</span>'; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Address & Details Column -->
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <h5 class="mb-3 border-bottom pb-2"><i class="fas fa-info-circle me-2"></i>Additional Details</h5>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0 me-3 text-primary">
                                                <i class="fas fa-map-marker-alt fa-lg"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 text-muted small">Address</h6>
                                                <p class="mb-0"><?php echo $supplier['address'] ? nl2br(htmlspecialchars($supplier['address'])) : '<span class="text-muted">No address provided</span>'; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0 me-3 text-primary">
                                                <i class="fas fa-calendar-alt fa-lg"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 text-muted small">Member Since</h6>
                                                <p class="mb-0"><?php echo date('F j, Y', strtotime($supplier['created_at'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Purchase Statistics</h6>
                    </div>
                    <div class="row g-3">
                        <div class="col-6 col-md-6">
                            <div class="p-3 border border-primary rounded text-center">
                                <h4 class="mb-0"><?php echo number_format($stats['total_orders'] ?? 0); ?></h4>
                                <small class="text-muted">Total Orders</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-6">
                            <div class="p-3 border border-info rounded text-center">
                                <h4 class="mb-0">₱<?php echo number_format($stats['total_spent'] ?? 0, 2); ?></h4>
                                <small class="text-muted">Total Spent</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-6">
                            <div class="p-3 border border-warning rounded text-center">
                                <h4 class="mb-0"><?php echo number_format($stats['active_orders'] ?? 0); ?></h4>
                                <small class="text-muted">Active Orders</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-6">
                            <div class="p-3 border border-success rounded text-center">
                                <h4 class="mb-0"><?php echo number_format($stats['completed_orders'] ?? 0); ?></h4>
                                <small class="text-muted">Completed Orders</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-6">
                            <div class="p-3 border border-secondary rounded text-center">
                                <h4 class="mb-0">
                                    <?php 
                                    if (!empty($stats['last_order_date'])) {
                                        echo date('M d, Y', strtotime($stats['last_order_date']));
                                    } else {
                                        echo 'No orders';
                                    }
                                    ?>
                                </h4>
                                <small class="text-muted">Last Order</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Purchase Orders</h6>
                <a href="<?php echo dirname(dirname($_SERVER['PHP_SELF'])) . '/../purchase_orders/add.php?supplier_id=' . $supplier_id; ?>" class="btn btn-sm btn-primary text-nowrap" aria-label="New Purchase Order">
                    <i class="fas fa-plus me-1"></i><span class="d-inline d-sm-none">New</span><span class="d-none d-sm-inline">New Purchase Order</span>
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($purchases)): ?>
                    <div class="text-center py-4">
                        <div class="mb-3">
                            <i class="fas fa-file-invoice fa-3x text-muted"></i>
                        </div>
                        <h5 class="text-muted">No purchase orders found</h5>
                        <p class="text-muted">Create a new purchase order to get started</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>PO #</th>
                                    <th class="d-none d-sm-table-cell">Date</th>
                                    <th class="text-end">Amount</th>
                                    <th class="d-none d-md-table-cell">Status</th>
                                    <th class="d-none d-lg-table-cell">Items</th>
                                    <th class="text-end d-none d-sm-table-cell">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($purchases as $po): ?>
                                    <tr>
                                        <td class="py-3">
                                            <a href="<?php echo dirname(dirname($_SERVER['PHP_SELF'])) . '/../purchase_orders/view.php?id=' . $po['id']; ?>" class="text-decoration-none fw-medium">
                                                <?php echo htmlspecialchars($po['po_number']); ?>
                                            </a>
                                            <!-- Mobile-only stacked details -->
                                            <div class="d-sm-none small text-muted mt-1">
                                                <div class="d-flex flex-wrap gap-2">
                                                    <span class="badge bg-light text-dark"><i class="fas fa-calendar-alt me-1"></i><?php echo date('M d, Y', strtotime($po['order_date'])); ?></span>
                                                    <?php
                                                    $rawStatus = isset($po['status']) ? strtolower(trim($po['status'])) : '';
                                                    $normalizedStatus = in_array($rawStatus, ['pending','completed'], true) ? $rawStatus : 'pending';
                                                    $statusClass = [
                                                        'pending' => 'bg-warning',
                                                        'completed' => 'bg-success'
                                                    ][$normalizedStatus];
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst(htmlspecialchars($normalizedStatus)); ?></span>
                                                </div>
                                                <?php if (!empty($po['items'])): ?>
                                                <div class="text-truncate mt-1" style="max-width: 240px;" title="<?php echo htmlspecialchars($po['items']); ?>">
                                                    <i class="fas fa-box-open me-1"></i>
                                                    <?php 
                                                    $items = explode(', ', $po['items']);
                                                    if (count($items) > 2) {
                                                        echo htmlspecialchars($items[0] . ', ' . $items[1] . ' +' . (count($items) - 2) . ' more');
                                                    } else {
                                                        echo htmlspecialchars($po['items']);
                                                    }
                                                    ?>
                                                </div>
                                                <?php endif; ?>
                                                <div class="mt-2">
                                                    <a href="<?php echo dirname(dirname($_SERVER['PHP_SELF'])) . '/../purchase_orders/view.php?id=' . $po['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="d-none d-sm-table-cell"><?php echo date('M d, Y', strtotime($po['order_date'])); ?></td>
                                        <td class="text-end fw-medium">₱<?php echo number_format($po['total_amount'], 2); ?></td>
                                        <td class="d-none d-md-table-cell">
                                            <?php
                                            // Normalize status to match global mapping (pending/completed)
                                            $rawStatus = isset($po['status']) ? strtolower(trim($po['status'])) : '';
                                            $normalizedStatus = in_array($rawStatus, ['pending','completed'], true) ? $rawStatus : 'pending';
                                            $statusClass = [
                                                'pending' => 'bg-warning',
                                                'completed' => 'bg-success'
                                            ][$normalizedStatus];
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <?php echo ucfirst(htmlspecialchars($normalizedStatus)); ?>
                                            </span>
                                        </td>
                                        <td class="d-none d-lg-table-cell" title="<?php echo htmlspecialchars($po['items']); ?>">
                                            <?php 
                                            $items = explode(', ', $po['items']);
                                            if (count($items) > 2) {
                                                echo htmlspecialchars($items[0] . ', ' . $items[1] . ' +' . (count($items) - 2) . ' more');
                                            } else {
                                                echo htmlspecialchars($po['items']);
                                            }
                                            ?>
                                        </td>
                                        <td class="text-end d-none d-sm-table-cell">
                                            <a href="<?php echo dirname(dirname($_SERVER['PHP_SELF'])) . '/../purchase_orders/view.php?id=' . $po['id']; ?>" 
                                            class="btn btn-sm btn-outline-primary"
                                            title="View Details"
                                            data-bs-toggle="tooltip">
                                                <i class="fas fa-eye"></i> View
                                            </a>
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