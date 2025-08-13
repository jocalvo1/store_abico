
<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../includes/database.php';

// Validate ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo '<div class="container-fluid py-4"><div class="alert alert-danger">Invalid transaction ID.</div></div>';
    require_once __DIR__ . '/../templates/footer.php';
    exit;
}

$db = getDBConnection();

// Fetch transaction
$sqlTx = "SELECT st.id, st.transaction_date, st.total_amount, st.status, st.notes,
                 COALESCE(c.name, 'Walk-in Customer') AS customer_name,
                 c.contact AS customer_contact,
                 pm.name AS payment_method_name
          FROM sales_transactions st
          LEFT JOIN customers c ON c.id = st.customer_id
          LEFT JOIN payment_methods pm ON pm.id = st.payment_method_id
          WHERE st.id = ?";
$stmt = $db->prepare($sqlTx);
$sale = null;
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $sale = $res->fetch_assoc();
    $res->free();
    $stmt->close();
}

if (!$sale) {
    echo '<div class="container-fluid py-4"><div class="alert alert-warning">Transaction not found.</div></div>';
    require_once __DIR__ . '/../templates/footer.php';
    exit;
}

// Fetch items
$items = [];
$sqlItems = "SELECT sti.item_id, i.name, i.unit, sti.quantity, sti.unit_price,
                    (sti.quantity * sti.unit_price) AS line_total
             FROM sales_transaction_items sti
             LEFT JOIN items i ON i.id = sti.item_id
             WHERE sti.sales_transaction_id = ?";
$stmtIt = $db->prepare($sqlItems);
if ($stmtIt) {
    $stmtIt->bind_param('i', $id);
    $stmtIt->execute();
    $resIt = $stmtIt->get_result();
    while ($row = $resIt->fetch_assoc()) $items[] = $row;
    $resIt->free();
    $stmtIt->close();
}

// Fetch debt record if any
$debt = null;
$sqlDebt = "SELECT id, total_amount, amount_paid, due_date, status, notes
            FROM sales_debts WHERE sales_transaction_id = ? LIMIT 1";
$stmtDebt = $db->prepare($sqlDebt);
if ($stmtDebt) {
    $stmtDebt->bind_param('i', $id);
    $stmtDebt->execute();
    $resDebt = $stmtDebt->get_result();
    $debt = $resDebt->fetch_assoc();
    $resDebt->free();
    $stmtDebt->close();
}

// Compute remaining for quick UI decisions
$remainingBalance = 0.0;
if ($debt) {
    $remainingBalance = max(0.0, (float)$debt['total_amount'] - (float)$debt['amount_paid']);
}

// Fetch payment history (includes payments recorded on the sale or via its debt)
$payments = [];
$stmtPays = $db->prepare(
    "SELECT pr.payment_date, pr.amount, pr.amount_tendered, pr.change_amount, pr.notes,
            pm.name AS method_name
     FROM payment_records pr
     LEFT JOIN payment_methods pm ON pm.id = pr.payment_method_id
     LEFT JOIN sales_debts sd ON sd.id = pr.sales_debt_id
     WHERE pr.sales_transaction_id = ? OR sd.sales_transaction_id = ?
     ORDER BY pr.payment_date ASC, pr.id ASC"
);
if ($stmtPays) {
    $stmtPays->bind_param('ii', $id, $id);
    $stmtPays->execute();
    $resPays = $stmtPays->get_result();
    while ($row = $resPays->fetch_assoc()) $payments[] = $row;
    $resPays->free();
    $stmtPays->close();
}
?>

<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h4 mb-0">Sale #<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></h1>
      <div class="text-muted small">Date: <?php echo date('M d, Y h:i A', strtotime($sale['transaction_date'])); ?></div>
    </div>
    <div class="d-flex gap-2">
      <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
      <a href="receipt.php?id=<?php echo (int)$sale['id']; ?>&print=1" target="_blank" class="btn btn-primary btn-sm"><i class="fas fa-receipt me-1"></i> Print Receipt</a>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
          <h6 class="m-0">Items</h6>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
              <thead class="bg-light">
                <tr>
                  <th class="ps-3">Item</th>
                  <th class="text-center">Unit</th>
                  <th class="text-end">Qty</th>
                  <th class="text-end">Unit Price</th>
                  <th class="text-end pe-3">Line Total</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No items</td></tr>
                <?php else: ?>
                <?php foreach ($items as $row): ?>
                <tr>
                  <td class="ps-3"><?php echo htmlspecialchars($row['name'] ?? ('#' . (int)$row['item_id'])); ?></td>
                  <td class="text-center"><?php echo htmlspecialchars($row['unit'] ?? ''); ?></td>
                  <td class="text-end"><?php echo number_format((float)$row['quantity'], 0); ?></td>
                  <td class="text-end">₱<?php echo number_format((float)$row['unit_price'], 2); ?></td>
                  <td class="text-end pe-3">₱<?php echo number_format((float)$row['line_total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
              <tfoot>
                <tr>
                  <th colspan="4" class="text-end">Total</th>
                  <th class="text-end pe-3">₱<?php echo number_format((float)$sale['total_amount'], 2); ?></th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
          <h6 class="m-0">Summary</h6>
        </div>
        <div class="card-body">
          <div class="mb-2 d-flex justify-content-between">
            <span class="text-muted">Customer</span>
            <span class="fw-medium"><?php echo htmlspecialchars($sale['customer_name']); ?></span>
          </div>
          <?php if (!empty($sale['customer_contact'])): ?>
          <div class="mb-2 d-flex justify-content-between">
            <span class="text-muted">Contact</span>
            <span><?php echo htmlspecialchars($sale['customer_contact']); ?></span>
          </div>
          <?php endif; ?>
          <div class="mb-2 d-flex justify-content-between">
            <span class="text-muted">Payment Method</span>
            <span><?php echo htmlspecialchars($sale['payment_method_name'] ?? ''); ?></span>
          </div>
          <div class="mb-2 d-flex justify-content-between">
            <span class="text-muted">Status</span>
            <span>
              <span class="badge bg-<?php echo $sale['status']==='paid'?'success':($sale['status']==='partial'?'warning':'danger'); ?>">
                <?php echo ucfirst($sale['status']); ?>
              </span>
            </span>
          </div>
          <div class="mb-2 d-flex justify-content-between">
            <span class="text-muted">Total Amount</span>
            <span class="fw-bold">₱<?php echo number_format((float)$sale['total_amount'], 2); ?></span>
          </div>
          <?php if ($debt): ?>
          <hr>
          <div class="mb-2 d-flex justify-content-between">
            <span class="text-muted">Amount Paid</span>
            <span>₱<?php echo number_format((float)$debt['amount_paid'], 2); ?></span>
          </div>
          <div class="mb-2 d-flex justify-content-between">
            <span class="text-muted">Remaining</span>
            <span class="fw-bold <?php echo $remainingBalance>0 ? 'text-danger' : ''; ?>">₱<?php echo number_format($remainingBalance, 2); ?></span>
          </div>
          <div class="mb-2 d-flex justify-content-between">
            <span class="text-muted">Debt Status</span>
            <span><span class="badge bg-<?php echo ($debt['status']==='paid'?'success':($debt['status']==='partial'?'warning':'danger')); ?>"><?php echo ucfirst($debt['status']); ?></span></span>
          </div>
          <?php if ($remainingBalance > 0): ?>
          <div class="d-grid mt-3">
            <a href="payment.php?sale_id=<?php echo (int)$sale['id']; ?>" class="btn btn-success btn-sm">
              <i class="fas fa-credit-card me-1"></i> Pay Now
            </a>
          </div>
          <?php endif; ?>
          <?php if (!empty($debt['due_date'])): ?>
          <div class="mb-2 d-flex justify-content-between">
            <span class="text-muted">Due Date</span>
            <span><?php echo htmlspecialchars(date('M d, Y', strtotime($debt['due_date']))); ?></span>
          </div>
          <?php endif; ?>
          <?php endif; ?>
          <?php if (!empty($sale['notes'])): ?>
          <hr>
          <div>
            <div class="text-muted mb-1">Notes</div>
            <div><?php echo nl2br(htmlspecialchars($sale['notes'])); ?></div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
          <div class="d-flex justify-content-between align-items-center">
            <h6 class="m-0">Payment History</h6>
            <?php if ($remainingBalance > 0): ?>
            <a href="payment.php?sale_id=<?php echo (int)$sale['id']; ?>" class="btn btn-outline-success btn-sm"><i class="fas fa-wallet me-1"></i> Settle Balance</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <style>
              .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
              .truncate { max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            </style>
            <table class="table table-hover align-middle mb-0">
              <thead class="bg-light">
                <tr>
                  <th class="ps-3">Date</th>
                  <th>Method</th>
                  <th class="text-end mono">Amount</th>
                  <th class="text-end mono">Tendered</th>
                  <th class="text-end mono pe-3">Change</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($payments)): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No payments recorded</td></tr>
                <?php else: ?>
                <?php foreach ($payments as $p): ?>
                <tr>
                  <td class="ps-3"><span class="text-muted small"><i class="far fa-clock me-1"></i><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($p['payment_date']))); ?></span></td>
                  <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?php echo htmlspecialchars($p['method_name'] ?? ''); ?></span></td>
                  <td class="text-end mono">₱<?php echo number_format((float)$p['amount'], 2); ?></td>
                  <td class="text-end mono">₱<?php echo number_format((float)$p['amount_tendered'], 2); ?></td>
                  <td class="text-end mono pe-3">₱<?php echo number_format((float)$p['change_amount'], 2); ?></td>
                </tr>
                <?php if (!empty($p['notes'])): ?>
                <tr class="bg-light-subtle">
                  <td colspan="5" class="px-3 pb-3 text-muted small">
                    <i class="fas fa-sticky-note me-1"></i><span class="truncate" title="<?php echo htmlspecialchars($p['notes']); ?>"><?php echo nl2br(htmlspecialchars($p['notes'])); ?></span>
                  </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php';

// Auto-print when requested
if (isset($_GET['print']) && $_GET['print'] === '1') {
    echo '<script>document.addEventListener("DOMContentLoaded",function(){ setTimeout(function(){ window.print(); }, 150); });</script>';
}

// Lightweight print styles to hide navigation and buttons
echo '<style>@media print { nav.navbar, .sidebar, .btn, .input-group, .d-flex.gap-2, .card-header .btn { display:none !important; } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } .container-fluid { padding: 0 !important; } .card { box-shadow: none !important; } .card-header { border-bottom: 1px solid #ddd; } .table th, .table td { border-color: #bbb !important; } }</style>';