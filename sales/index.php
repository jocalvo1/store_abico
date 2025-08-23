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

<div class="container-fluid py-4 sales-page">
    <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-up">
        <h1 class="h3 mb-0 mt-2">Sales Transactions</h1>
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
            <a href="export.php<?php echo $searchTerm !== '' ? ('?search=' . urlencode($searchTerm)) : ''; ?>" 
               class="btn btn-success btn-sm me-2" title="Export to CSV" aria-label="Export to CSV">
                <i class="fas fa-file-excel me-1"></i>
                <span>Export CSV</span>
            </a>
            <a href="add.php" class="btn btn-primary btn-sm" title="New Sale" aria-label="New Sale">
                <i class="fas fa-plus me-1"></i>
                <span>New Sale</span>
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
                                <th class="ps-3 d-none d-sm-table-cell">#</th>
                                <th class="d-none d-md-table-cell">Date</th>
                                <th class="d-none d-md-table-cell">Invoice #</th>
                                <th>Customer</th>
                                <th class="text-end">Amount</th>
                                <th class="d-none d-md-table-cell">Payment</th>
                                <th class="text-center d-none d-md-table-cell" style="width:1%">Status</th>
                                <th class="text-end pe-3 d-none d-md-table-cell" style="width:1%; white-space:nowrap">Actions</th>
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
                                <td class="ps-3 d-none d-sm-table-cell"><?php echo $index + 1; ?></td>
                                <td class="d-none d-md-table-cell"><?php echo date('M d, Y h:i A', strtotime($sale['transaction_date'])); ?></td>
                                <td class="d-none d-md-table-cell">#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <?php $custName = htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?>
                                    <span class="d-inline-block text-truncate" style="max-width: 70vw;" title="<?php echo $custName; ?>" data-bs-toggle="tooltip" data-bs-placement="top">
                                        <?php echo $custName; ?>
                                    </span>
                                    <!-- Mobile-only details -->
                                    <div class="d-md-none small text-muted mt-1">
                                        <div class="d-flex flex-column gap-1">
                                            <div>
                                                <i class="far fa-calendar-alt me-1"></i>
                                                <span><?php echo date('M d, Y h:i A', strtotime($sale['transaction_date'])); ?></span>
                                            </div>
                                            <div>
                                                <i class="fas fa-hashtag me-1"></i>
                                                <span>#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                            </div>
                                            <div>
                                                <i class="far fa-credit-card me-1"></i>
                                                <span><?php echo htmlspecialchars($sale['payment_method_name']); ?></span>
                                            </div>
                                            <div>
                                                <span class="badge bg-<?php echo $statusClass; ?> text-uppercase">
                                                    <?php echo ucfirst($sale['status']); ?>
                                                </span>
                                            </div>
                                            <div class="mt-1 d-flex flex-wrap gap-2">
                                                <?php if ($sale['status'] === 'debt' || $sale['status'] === 'partial'): ?>
                                                <?php 
                                                  $isDebt = ($sale['status'] === 'debt');
                                                  $payLabel = $isDebt ? 'Pay' : 'Settle';
                                                  $payTitle = $isDebt ? 'Pay this unpaid transaction' : 'Settle the remaining balance';
                                                  $payIcon = $isDebt ? 'fa-credit-card' : 'fa-wallet';
                                                ?>
                                                <a href="payment.php?sale_id=<?php echo $sale['id']; ?>" 
                                                   class="btn btn-outline-success btn-sm d-flex align-items-center gap-1"
                                                   title="<?php echo $payTitle; ?>">
                                                    <i class="fas <?php echo $payIcon; ?> fa-xs"></i><span>Pay</span>
                                                </a>
                                                <?php endif; ?>
                                                <a href="view.php?id=<?php echo $sale['id']; ?>" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1">
                                                    <i class="fas fa-eye fa-xs"></i><span>View</span>
                                                </a>
                                                <a href="receipt.php?id=<?php echo $sale['id']; ?>&print=1" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                                                    <i class="fas fa-print fa-xs"></i><span>Print</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end">₱<?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($sale['payment_method_name']); ?></td>
                                <td class="text-center d-none d-md-table-cell" style="width:1%">
                                    <span class="badge bg-<?php echo $statusClass; ?> text-uppercase">
                                        <?php echo ucfirst($sale['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3 text-nowrap d-none d-md-table-cell" style="width:1%">
                                    <div class="d-flex gap-1 justify-content-end" role="group" aria-label="Actions">
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
                                            <i class="fas <?php echo $payIcon; ?>"></i>
                                            <span class="d-none d-sm-inline"><?php echo $payLabel; ?></span>
                                        </a>
                                        <?php endif; ?>
                                        <a href="view.php?id=<?php echo $sale['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary d-flex align-items-center justify-content-center gap-1"
                                           data-bs-toggle="tooltip" data-bs-placement="top" title="View">
                                            <i class="fas fa-eye"></i>
                                            <span class="d-none d-sm-inline">View</span>
                                        </a>
                                        <a href="receipt.php?id=<?php echo $sale['id']; ?>&print=1"
                                           class="btn btn-sm btn-outline-secondary d-flex align-items-center justify-content-center gap-1"
                                           data-bs-toggle="tooltip" data-bs-placement="top" title="Print Receipt">
                                            <i class="fas fa-print"></i>
                                            <span class="d-none d-sm-inline">Print</span>
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
