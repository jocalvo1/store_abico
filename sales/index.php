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
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../controller/sale/salesController.php';

// Initialize SalesController with DB connection
$db = getDBConnection();
$salesController = new SalesController($db);

// Handle search
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get sales as array based on search
$salesArray = !empty($searchTerm)
    ? $salesController->search($searchTerm)
    : $salesController->getAll();
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
                                <th class="text-center" style="width:1%">Status</th>
                                <th class="text-end pe-3" style="width:1%; white-space:nowrap">Actions</th>
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
                                <td class="text-center" style="width:1%">
                                    <span class="badge bg-<?php echo $statusClass; ?> text-uppercase">
                                        <?php echo ucfirst($sale['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3 text-nowrap" style="width:1%">
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Actions">
                                        <?php if ($sale['status'] === 'debt' || $sale['status'] === 'partial'): ?>
                                        <?php 
                                          $isDebt = ($sale['status'] === 'debt');
                                          $payLabel = $isDebt ? 'Pay Now' : 'Settle Balance';
                                          $payTitle = $isDebt ? 'Pay this unpaid transaction' : 'Settle the remaining balance';
                                          $payIcon = $isDebt ? 'fa-credit-card' : 'fa-wallet';
                                        ?>
                                        <a href="payment.php?sale_id=<?php echo $sale['id']; ?>" 
                                           class="btn btn-sm btn-outline-success d-flex align-items-center justify-content-center gap-1"
                                           data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo $payTitle; ?>">
                                            <i class="fas <?php echo $payIcon; ?>"></i> <?php echo $payLabel; ?>
                                        </a>
                                        <?php endif; ?>
                                        <a href="view.php?id=<?php echo $sale['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary d-flex align-items-center justify-content-center gap-1"
                                           data-bs-toggle="tooltip" data-bs-placement="top" title="View">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="receipt.php?id=<?php echo $sale['id']; ?>&print=1" target="_blank"
                                           class="btn btn-sm btn-outline-secondary d-flex align-items-center justify-content-center gap-1"
                                           data-bs-toggle="tooltip" data-bs-placement="top" title="Print Receipt">
                                            <i class="fas fa-print"></i> Print
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

<?php require_once __DIR__ . '/../templates/footer.php'; ?>

<script>
(function(){
  try {
    const params = new URLSearchParams(window.location.search);
    if (params.get('success') === '1') {
      // Clear cart stored by add.php
      localStorage.removeItem('cart');
      // Clean the URL so it doesn't clear again on refresh
      const url = new URL(window.location.href);
      url.searchParams.delete('success');
      if (url.searchParams.has('tx')) url.searchParams.delete('tx');
      window.history.replaceState({}, document.title, url.pathname + (url.search ? '?' + url.searchParams.toString() : ''));
    }
    // Init Bootstrap tooltips for action buttons
    if (window.bootstrap) {
      const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
      });
    }
  } catch (e) { /* ignore */ }
})();
</script>
