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

// Include required files
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../controller/sale/SalesController.php';

// Initialize SalesController
$salesController = new SalesController();

// Handle search
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get sales based on search
if (!empty($searchTerm)) {
    $sales = $salesController->search($searchTerm);
} else {
    $sales = $salesController->getAll();
}

// Get sales as array
$salesArray = [];
if (is_object($sales) && method_exists($sales, 'fetch')) {
    while ($sale = $sales->fetch()) {
        $salesArray[] = $sale;
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-up">
        <div class="row">
            <h1 class="h3 mb-0 mt-2">Sales Transactions</h1>
        </div>
        <div class="d-flex align-items-center">
            <form action="" method="get" class="me-3 min-width-300px">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0 ps-0" name="search" 
                           placeholder="Search sales..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
            </form>
            <a href="add.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> New Sale
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm" data-aos="fade-up" data-aos-delay="100">
        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Recent Sales</h6>
                <div class="text-muted small">
                    <?php echo count($salesArray); ?> sales found
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($salesArray)): ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-cash-register fa-3x text-muted"></i>
                    </div>
                    <h5 class="text-muted">No sales transactions found</h5>
                    <p class="text-muted">Get started by creating a new sale</p>
                    <a href="add.php" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-1"></i> Create Sale
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">#</th>
                                <th>Date</th>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th class="text-end">Amount</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($salesArray as $index => $sale): 
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
                            <tr>
                                <td class="ps-3"><?php echo $index + 1; ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($sale['transaction_date'])); ?></td>
                                <td>#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?></td>
                                <td class="text-end">₱<?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($sale['payment_method_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $statusClass; ?> text-uppercase">
                                        <?php echo ucfirst($sale['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group">
                                        <a href="view.php?id=<?php echo $sale['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="#" 
                                           class="btn btn-sm btn-outline-secondary print-receipt" 
                                           data-id="<?php echo $sale['id']; ?>"
                                           title="Print Receipt">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <?php if ($sale['status'] === 'debt' || $sale['status'] === 'partial'): ?>
                                        <a href="payment.php?sale_id=<?php echo $sale['id']; ?>" 
                                           class="btn btn-sm btn-outline-success" 
                                           title="Record Payment">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </a>
                                        <?php endif; ?>
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
