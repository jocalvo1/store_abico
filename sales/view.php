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

// Check if sale ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$saleId = (int)$_GET['id'];

// Include required files
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../controller/sale/SalesController.php';

// Initialize SalesController
$salesController = new SalesController();

// Get sale details
$sale = $salesController->getById($saleId);

// Check if sale exists
if (!$sale) {
    $_SESSION['error'] = 'Sale not found';
    header('Location: index.php');
    exit();
}

// Get sale items
$saleItems = $salesController->getSaleItems($saleId);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Sales</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Sale #<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">Sale Details</h1>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-1"></i> Back to Sales
            </a>
            <a href="#" class="btn btn-primary print-receipt" data-id="<?php echo $sale['id']; ?>">
                <i class="fas fa-print me-1"></i> Print Receipt
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold">Items</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">#</th>
                                    <th>Item</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end pe-3">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saleItems as $index => $item): ?>
                                <tr>
                                    <td class="ps-3"><?php echo $index + 1; ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($item['sku']); ?></small>
                                    </td>
                                    <td class="text-end">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td class="text-center"><?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                                    <td class="text-end pe-3">₱<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($sale['notes']): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold">Notes</h6>
                </div>
                <div class="card-body">
                    <?php echo nl2br(htmlspecialchars($sale['notes'])); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold">Sale Summary</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="small text-muted mb-1">Invoice #</div>
                        <div>#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="small text-muted mb-1">Date</div>
                        <div><?php echo date('M d, Y h:i A', strtotime($sale['transaction_date'])); ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="small text-muted mb-1">Customer</div>
                        <div class="fw-bold"><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?></div>
                        <?php if (!empty($sale['contact'])): ?>
                        <div class="text-muted small"><?php echo htmlspecialchars($sale['contact']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <div class="small text-muted mb-1">Payment Method</div>
                        <div><?php echo htmlspecialchars($sale['payment_method_name']); ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="small text-muted mb-1">Status</div>
                        <?php
                        $statusClass = '';
                        switch ($sale['status']) {
                            case 'paid':
                                $statusClass = 'success';
                                break;
                            case 'partial':
                                $statusClass = 'warning';
                                break;
                            case 'debt':
                                $statusClass = 'danger';
                                break;
                            default:
                                $statusClass = 'secondary';
                        }
                        ?>
                        <span class="badge bg-<?php echo $statusClass; ?> text-uppercase">
                            <?php echo ucfirst($sale['status']); ?>
                        </span>
                    </div>
                    <div class="border-top pt-3 mt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <div>Subtotal</div>
                            <div>₱<?php echo number_format($sale['total_amount'], 2); ?></div>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <div>Tax (0%)</div>
                            <div>₱0.00</div>
                        </div>
                        <div class="d-flex justify-content-between fw-bold fs-5 border-top pt-2 mt-2">
                            <div>Total</div>
                            <div>₱<?php echo number_format($sale['total_amount'], 2); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <a href="#" class="btn btn-outline-primary print-receipt" data-id="<?php echo $sale['id']; ?>">
                    <i class="fas fa-print me-1"></i> Print Receipt
                </a>
                <?php if ($sale['status'] === 'debt' || $sale['status'] === 'partial'): ?>
                <a href="payment.php?sale_id=<?php echo $sale['id']; ?>" class="btn btn-success">
                    <i class="fas fa-money-bill-wave me-1"></i> Record Payment
                </a>
                <?php endif; ?>
                <a href="edit.php?id=<?php echo $sale['id']; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-edit me-1"></i> Edit Sale
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Print Receipt Modal -->
<div class="modal fade" id="printReceiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Print Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="receiptContent">
                <!-- Receipt content will be loaded here via AJAX -->
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading receipt...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize print receipt functionality
    document.querySelectorAll('.print-receipt').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const saleId = this.getAttribute('data-id');
            const modal = new bootstrap.Modal(document.getElementById('printReceiptModal'));
            
            // Load receipt content via AJAX
            fetch(`receipt.php?id=${saleId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('receiptContent').innerHTML = html;
                    modal.show();
                })
                .catch(error => {
                    console.error('Error loading receipt:', error);
                    document.getElementById('receiptContent').innerHTML = 
                        '<div class="alert alert-danger">Error loading receipt. Please try again.</div>';
                });
        });
    });
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
