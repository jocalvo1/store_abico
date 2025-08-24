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

$db = getDBConnection();
$db->set_charset('utf8mb4');

$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
if ($customerId <= 0) {
    require_once __DIR__ . '/../../templates/header.php';
    echo '<div class="container-fluid py-4"><div class="alert alert-danger">Invalid customer.</div></div>';
    require_once __DIR__ . '/../../templates/footer.php';
    exit;
}

// Load customer
$customer = null;
$stmtC = $db->prepare('SELECT id, name, contact FROM customers WHERE id = ?');
if ($stmtC) {
    $stmtC->bind_param('i', $customerId);
    $stmtC->execute();
    $res = $stmtC->get_result();
    $customer = $res->fetch_assoc();
    $res->free();
    $stmtC->close();
}
if (!$customer) {
    require_once __DIR__ . '/../../templates/header.php';
    echo '<div class="container-fluid py-4"><div class="alert alert-danger">Customer not found.</div></div>';
    require_once __DIR__ . '/../../templates/footer.php';
    exit;
}

// Outstanding debts
$outstanding = [];
$stmtO = $db->prepare('SELECT st.id AS sale_id, st.transaction_date, sd.total_amount, sd.amount_paid, (sd.total_amount - sd.amount_paid) AS remaining, sd.status
                       FROM sales_debts sd
                       JOIN sales_transactions st ON st.id = sd.sales_transaction_id
                       WHERE sd.customer_id = ? AND sd.total_amount > sd.amount_paid
                       ORDER BY st.transaction_date DESC');
if ($stmtO) {
    $stmtO->bind_param('i', $customerId);
    $stmtO->execute();
    $resO = $stmtO->get_result();
    while ($r = $resO->fetch_assoc()) $outstanding[] = $r;
    $resO->free();
    $stmtO->close();
}

// Paid (was-debt)
$paid = [];
$stmtP = $db->prepare("SELECT st.id AS sale_id, st.transaction_date, sd.total_amount, sd.amount_paid, sd.updated_at AS paid_at, sd.status
                       FROM sales_debts sd
                       JOIN sales_transactions st ON st.id = sd.sales_transaction_id
                       WHERE sd.customer_id = ? AND sd.status = 'paid'
                       ORDER BY sd.updated_at DESC");
if ($stmtP) {
    $stmtP->bind_param('i', $customerId);
    $stmtP->execute();
    $resP = $stmtP->get_result();
    while ($r = $resP->fetch_assoc()) $paid[] = $r;
    $resP->free();
    $stmtP->close();
}

// Totals
$sumDue = 0.0; $sumPaid = 0.0; $sumRem = 0.0;
foreach ($outstanding as $r) {
    $sumDue += (float)$r['total_amount'];
    $sumPaid += (float)$r['amount_paid'];
    $sumRem += max(0.0, (float)$r['remaining']);
}

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h4 mb-0">Payments: <?php echo htmlspecialchars($customer['name']); ?></h1>
      <div class="text-muted small">View outstanding and paid (was-debt)</div>
    </div>
    <div class="d-flex gap-2">
      <a href="add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> New Debt</a>
      <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
          <h6 class="m-0">Outstanding Debts</h6>
          <span class="badge bg-warning text-dark"><?php echo count($outstanding); ?></span>
        </div>
        <div class="card-body p-0">
          <div class="p-3 border-bottom small text-muted">
            Total Due: <span class="fw-bold">₱<?php echo number_format($sumDue,2); ?></span> · Paid: ₱<?php echo number_format($sumPaid,2); ?> · Remaining: <span class="fw-bold text-danger">₱<?php echo number_format($sumRem,2); ?></span>
          </div>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead class="bg-light">
                <tr>
                  <th>Sale #</th>
                  <th class="d-none d-sm-table-cell">Date</th>
                  <th class="text-end">Amount</th>
                  <th class="text-end d-none d-md-table-cell">Paid</th>
                  <th class="text-end d-none d-sm-table-cell">Remaining</th>
                  <th class="text-end d-none d-sm-table-cell">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($outstanding)): ?>
                  <tr><td colspan="6" class="text-center py-4 text-muted">No outstanding debts</td></tr>
                <?php else: foreach ($outstanding as $r): ?>
                  <tr>
                    <td class="py-3">
                      #<?php echo str_pad($r['sale_id'], 6, '0', STR_PAD_LEFT); ?>
                      <!-- Mobile-only stacked details -->
                      <div class="d-sm-none small text-muted mt-1">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                          <span><i class="far fa-calendar-alt me-1"></i><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($r['transaction_date']))); ?></span>
                          <span class="badge bg-light text-dark">Amt: ₱<?php echo number_format((float)$r['total_amount'], 2); ?></span>
                          <span class="badge bg-light text-dark">Paid: ₱<?php echo number_format((float)$r['amount_paid'], 2); ?></span>
                          <span class="badge bg-danger">Rem: ₱<?php echo number_format(max(0.0,(float)$r['remaining']), 2); ?></span>
                        </div>
                        <div class="mt-2">
                          <a href="../../sales/payment.php?sale_id=<?php echo (int)$r['sale_id']; ?>" class="btn btn-sm btn-success">Record Payment</a>
                        </div>
                      </div>
                    </td>
                    <td class="small text-muted d-none d-sm-table-cell"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($r['transaction_date']))); ?></td>
                    <td class="text-end">₱<?php echo number_format((float)$r['total_amount'], 2); ?></td>
                    <td class="text-end text-muted d-none d-md-table-cell">₱<?php echo number_format((float)$r['amount_paid'], 2); ?></td>
                    <td class="text-end fw-bold text-danger d-none d-sm-table-cell">₱<?php echo number_format(max(0.0,(float)$r['remaining']), 2); ?></td>
                    <td class="text-end d-none d-sm-table-cell">
                      <a href="../../sales/payment.php?sale_id=<?php echo (int)$r['sale_id']; ?>" class="btn btn-sm btn-success">Record Payment</a>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
          <h6 class="m-0">Paid (Was-debt)</h6>
          <span class="badge bg-success"><?php echo count($paid); ?></span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead class="bg-light">
                <tr>
                  <th>Sale #</th>
                  <th class="d-none d-sm-table-cell">Paid At</th>
                  <th class="text-end">Amount</th>
                  <th class="text-end d-none d-md-table-cell">Paid</th>
                  <th class="text-end d-none d-sm-table-cell">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($paid)): ?>
                  <tr><td colspan="5" class="text-center py-4 text-muted">No paid debts</td></tr>
                <?php else: foreach ($paid as $r): ?>
                  <tr>
                    <td class="py-3">
                      #<?php echo str_pad($r['sale_id'], 6, '0', STR_PAD_LEFT); ?>
                      <!-- Mobile-only stacked details -->
                      <div class="d-sm-none small text-muted mt-1">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                          <span><i class="far fa-calendar-check me-1"></i><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($r['paid_at']))); ?></span>
                          <span class="badge bg-light text-dark">Amt: ₱<?php echo number_format((float)$r['total_amount'], 2); ?></span>
                          <span class="badge bg-success">Paid: ₱<?php echo number_format((float)$r['amount_paid'], 2); ?></span>
                        </div>
                        <div class="mt-2">
                          <a href="../../sales/view.php?id=<?php echo (int)$r['sale_id']; ?>" class="btn btn-sm btn-outline-secondary">View Sale</a>
                        </div>
                      </div>
                    </td>
                    <td class="small text-muted d-none d-sm-table-cell"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($r['paid_at']))); ?></td>
                    <td class="text-end">₱<?php echo number_format((float)$r['total_amount'], 2); ?></td>
                    <td class="text-end text-success d-none d-md-table-cell">₱<?php echo number_format((float)$r['amount_paid'], 2); ?></td>
                    <td class="text-end d-none d-sm-table-cell">
                      <a href="../../sales/view.php?id=<?php echo (int)$r['sale_id']; ?>" class="btn btn-sm btn-outline-secondary">View Sale</a>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
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

