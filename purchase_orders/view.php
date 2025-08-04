<?php
// Start output buffering to prevent headers already sent error
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    ob_end_flush();
    exit();
}

// Include database connection
require_once __DIR__ . '/../includes/database.php';

// Check if ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No purchase order ID provided.";
    header("Location: index.php");
    ob_end_flush();
    exit();
}

$purchase_id = intval($_GET['id']);
$conn = getDBConnection();

// Fetch purchase order details
$purchase = null;
$stmt = $conn->prepare("
    SELECT p.*, s.name as supplier_name, s.contact_person, s.contact_number, s.email
    FROM purchase_orders p
    JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $purchase_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Purchase order not found.";
    header("Location: index.php");
    ob_end_flush();
    exit();
}

$purchase = $result->fetch_assoc();
$stmt->close();

// Fetch purchase items with received quantities
$items = [];
$stmt = $conn->prepare("
    SELECT 
        pi.*, 
        i.name as item_name, 
        i.unit,
        COALESCE(SUM(di.received_quantity), 0) as total_received
    FROM purchase_order_items pi
    JOIN items i ON pi.item_id = i.id
    LEFT JOIN delivery_items di ON pi.id = di.purchase_order_item_id
    WHERE pi.purchase_order_id = ?
    GROUP BY pi.id
");
$stmt->bind_param("i", $purchase_id);
$stmt->execute();
$items_result = $stmt->get_result();

if ($items_result) {
    $items = $items_result->fetch_all(MYSQLI_ASSOC);
    
    // Check if all items are fully received (received quantity exactly equals ordered quantity)
    $all_items_received = true;
    $any_items_received = false;
    
    foreach ($items as $item) {
        $received = (int)$item['total_received'];
        $ordered = (int)$item['quantity'];
        
        if ($received > 0) {
            $any_items_received = true;
        }
        
        if ($received !== $ordered) {
            $all_items_received = false;
        }
    }
    
    // Update purchase status if needed
    if ($all_items_received && $any_items_received) {
        if ($purchase['status'] !== 'completed') {
            $updateStmt = $conn->prepare("UPDATE purchase_orders SET status = 'completed' WHERE id = ?");
            $updateStmt->bind_param("i", $purchase_id);
            $updateStmt->execute();
            $updateStmt->close();
            $purchase['status'] = 'completed';
        }
    } elseif ($any_items_received) {
        if ($purchase['status'] !== 'partial') {
            $updateStmt = $conn->prepare("UPDATE purchase_orders SET status = 'partial' WHERE id = ?");
            $updateStmt->bind_param("i", $purchase_id);
            $updateStmt->execute();
            $updateStmt->close();
            $purchase['status'] = 'partial';
        }
    } else {
        if ($purchase['status'] !== 'pending') {
            $updateStmt = $conn->prepare("UPDATE purchase_orders SET status = 'pending' WHERE id = ?");
            $updateStmt->bind_param("i", $purchase_id);
            $updateStmt->execute();
            $updateStmt->close();
            $purchase['status'] = 'pending';
        }
    }
}
$stmt->close();

// Fetch deliveries for this purchase
$deliveries = [];
$stmt = $conn->prepare("
    SELECT d.*, 
           u.name as received_by_name,
           (SELECT GROUP_CONCAT(
                CONCAT(
                    '<div class=\'d-flex justify-content-between\'>',
                    '<span class=\'text-nowrap\'>', i.name, '</span>',
                    '<span class=\'ms-2 text-muted\'>', 
                        CAST(di.received_quantity AS UNSIGNED), ' <small>', i.unit, '</small>',
                    '</span>',
                    '</div>'
                )
                ORDER BY i.name SEPARATOR ''
            )
            FROM delivery_items di
            JOIN items i ON di.item_id = i.id
            WHERE di.delivery_id = d.id) as items_list,
           (SELECT COUNT(*) FROM delivery_items WHERE delivery_id = d.id) as item_count
    FROM deliveries d
    LEFT JOIN users u ON d.received_by_user_id = u.id
    WHERE d.purchase_order_id = ?
    ORDER BY d.delivery_date DESC, d.created_at DESC
");
$stmt->bind_param("i", $purchase_id);
$stmt->execute();
$deliveries_result = $stmt->get_result();

if ($deliveries_result) {
    $deliveries = $deliveries_result->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();

// Calculate received quantities for each item
$received_quantities = [];
foreach ($items as $item) {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(di.received_quantity), 0) as total_received
        FROM delivery_items di
        JOIN deliveries d ON di.delivery_id = d.id
        WHERE d.purchase_order_id = ? AND di.purchase_order_item_id = ?
        GROUP BY di.purchase_order_item_id
    ");
    $stmt->bind_param("ii", $purchase_id, $item['id']);
    $stmt->execute();
    $received_result = $stmt->get_result();
    $received_row = $received_result->fetch_assoc();
    $received_quantities[$item['id']] = $received_row ? $received_row['total_received'] : 0;
    $stmt->close();
}

$conn->close();

// Include header after all processing is done
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
        <h1 class="h3">
            Purchase Order #<?php echo htmlspecialchars($purchase['po_number']); ?>
            <?php 
            $status_class = [
                'pending' => 'warning',
                'partial' => 'info',
                'completed' => 'success'
            ][$purchase['status']] ?? 'secondary';
            ?>
            <span class="badge bg-<?php echo $status_class; ?>">
                <?php echo ucfirst($purchase['status']); ?>
            </span>
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="edit.php?id=<?php echo $purchase_id; ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-edit"></i> Edit
                </a>
            </div>
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0">Purchase Order Details</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Supplier</dt>
                        <dd class="col-sm-8">
                            <a href="../ledger/suppliers/view.php?id=<?php echo $purchase['supplier_id']; ?>" 
                               class="text-decoration-none fw-medium">
                                <?php echo htmlspecialchars($purchase['supplier_name']); ?>
                            </a>
                        </dd>
                        
                        <dt class="col-sm-4">Contact</dt>
                        <dd class="col-sm-8">
                            <?php if (!empty($purchase['contact_person'])): ?>
                                <?php echo htmlspecialchars($purchase['contact_person']); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($purchase['email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($purchase['email']); ?>">
                                    <?php echo htmlspecialchars($purchase['email']); ?>
                                </a><br>
                            <?php endif; ?>
                            <?php if (!empty($purchase['contact_number'])): ?>
                                <a href="tel:<?php echo htmlspecialchars($purchase['contact_number']); ?>">
                                    <?php echo htmlspecialchars($purchase['contact_number']); ?>
                                </a>
                            <?php endif; ?>
                        </dd>
                        
                        <dt class="col-sm-4">Order Date</dt>
                        <dd class="col-sm-8"><?php echo date('M d, Y', strtotime($purchase['created_at'])); ?></dd>
                        
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-<?php echo $status_class; ?>">
                                <?php echo ucfirst($purchase['status']); ?>
                            </span>
                        </dd>
                        
                        <?php if (!empty($purchase['notes'])): ?>
                            <dt class="col-sm-4">Notes</dt>
                            <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($purchase['notes'])); ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Order Summary</h6>
                    <span class="badge bg-secondary">
                        <?php echo count($items); ?> <?php echo count($items) === 1 ? 'Item' : 'Items'; ?>
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">Quantity</th>
                                    <th class="text-end">Delivered</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            No items found for this purchase order.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($items as $item): 
                                        $received = $received_quantities[$item['id']] ?? 0;
                                        $remaining = $item['quantity'] - $received;
                                        $completion = $item['quantity'] > 0 
                                            ? round(($received / $item['quantity']) * 100) 
                                            : 0;
                                        $status_class = $received == 0 ? 'warning' : 
                                                     ($remaining == 0 ? 'success' : 'info');
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="fw-medium"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                                <?php if ($completion > 0): ?>
                                                    <div class="progress mt-1" style="height: 5px;">
                                                        <div class="progress-bar bg-<?php echo $status_class; ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo $completion; ?>%" 
                                                             aria-valuenow="<?php echo $completion; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo $received; ?> of <?php echo $item['quantity']; ?> <?php echo $item['unit']; ?> received
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php echo number_format($item['quantity'], 0); ?>
                                                <span class="text-muted"><?php echo $item['unit']; ?></span>
                                            </td>
                                            <td class="text-end">
                                                <?php 
                                                    $received = (int)$item['total_received'];
                                                    $total = (int)$item['quantity'];
                                                    $is_fully_received = ($received >= $total);
                                                ?>
                                                <?php if ($is_fully_received): ?>
                                                    <span class="text-success">
                                                        <i class="fas fa-check-circle"></i>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="<?php echo $received > 0 ? 'text-primary' : 'text-muted'; ?>">
                                                    <?php echo number_format($received, 0); ?>
                                                </span>
                                                <span class="text-muted">/ <?php echo number_format($total, 0); ?></span>
                                            </td>
                                            <td class="text-end">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                            <td class="text-end fw-bold">
                                                ₱<?php 
                                                    $item_total = $item['quantity'] * $item['unit_price'];
                                                    echo number_format($item_total, 2); 
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="4" class="text-end">Total Amount:</th>
                                    <th class="text-end">
                                        ₱<?php echo number_format($purchase['total_amount'], 2); ?>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Delivery History</h6>
            <div>
                <span class="badge bg-secondary me-2">
                    <?php echo count($deliveries); ?> <?php echo count($deliveries) === 1 ? 'Delivery' : 'Deliveries'; ?>
                </span>
                <a href="create_delivery.php?purchase_id=<?php echo $purchase_id; ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus me-1"></i> New Delivery
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($deliveries)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-truck fa-2x mb-3 text-muted"></i>
                    <p class="mb-3">No deliveries recorded for this purchase order.</p>
                    <a href="create_delivery.php?purchase_id=<?php echo $purchase_id; ?>" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Create First Delivery
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Delivery #</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Delivered By</th>
                                <th>Received By</th>
                                <th>Items</th>
                                <th>Notes</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($deliveries as $delivery): 
                                $status_class = [
                                    'pending' => 'warning',
                                    'delivered' => 'success',
                                    'cancelled' => 'danger'
                                ][$delivery['status']] ?? 'secondary';
                            ?>
                                <tr>
                                    <td><?php echo $delivery['id']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($delivery['delivery_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo ucfirst($delivery['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($delivery['delivered_by'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($delivery['received_by_name'] ?? '—'); ?></td>
                                    <td class="small">
                                        <?php if (!empty($delivery['items_list'])): ?>
                                            <div class="vstack gap-1">
                                                <?php echo $delivery['items_list']; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">No items</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($delivery['confirm_notes'])): ?>
                                            <span class="d-inline-block text-truncate" style="max-width: 200px;" 
                                                  title="<?php echo htmlspecialchars($delivery['confirm_notes']); ?>">
                                                <?php echo htmlspecialchars($delivery['confirm_notes']); ?>
                                            </span>
                                        <?php elseif (!empty($delivery['cancel_reason'])): ?>
                                            <span class="d-inline-block text-truncate text-danger" style="max-width: 200px;" 
                                                  title="Cancellation: <?php echo htmlspecialchars($delivery['cancel_reason']); ?>">
                                                <i class="fas fa-times-circle me-1"></i>
                                                <?php echo htmlspecialchars($delivery['cancel_reason']); ?>
                                            </span>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="../deliveries/view.php?id=<?php echo $delivery['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary"
                                           title="View Delivery">
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

<?php 
// Include footer
require_once __DIR__ . '/../templates/footer.php'; 
?>