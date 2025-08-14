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

require_once __DIR__ . '/../includes/database.php';

$db = getDBConnection();
$db->set_charset('utf8mb4');

$errors = [];
$success = '';

// Handle POST: apply payment to a sale's debt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $saleId = isset($_POST['sale_id']) ? (int)$_POST['sale_id'] : 0;
    $paymentMethodId = isset($_POST['payment_method_id']) ? (int)$_POST['payment_method_id'] : 0;
    $amountTendered = isset($_POST['amount_tendered']) ? (float)$_POST['amount_tendered'] : 0.0;
    $notes = trim($_POST['notes'] ?? '');

    if ($saleId <= 0) $errors[] = 'Invalid sale ID.';
    if ($paymentMethodId <= 0) $errors[] = 'Please select a payment method.';
    if ($amountTendered <= 0) $errors[] = 'Amount tendered must be greater than zero.';

    if (empty($errors)) {
        try {
            $db->begin_transaction();

            // Lock debt row for update
            $stmtDebt = $db->prepare('SELECT sd.id, sd.customer_id, sd.total_amount, sd.amount_paid, sd.status, st.status AS tx_status FROM sales_debts sd JOIN sales_transactions st ON st.id = sd.sales_transaction_id WHERE sd.sales_transaction_id = ? FOR UPDATE');
            if (!$stmtDebt) throw new Exception('Failed to prepare debt query.');
            $stmtDebt->bind_param('i', $saleId);
            $stmtDebt->execute();
            $resDebt = $stmtDebt->get_result();
            $debt = $resDebt->fetch_assoc();
            $resDebt->free();
            $stmtDebt->close();

            if (!$debt) throw new Exception('No debt found for this sale or already settled.');

            $total = (float)$debt['total_amount'];
            $paid = (float)$debt['amount_paid'];
            $remaining = max(0.0, $total - $paid);
            if ($remaining <= 0.0) throw new Exception('This debt is already fully paid.');

            // Compute amounts
            $applied = min($amountTendered, $remaining);
            $change = max(0.0, $amountTendered - $remaining);

            // Insert payment record (store both sales_transaction_id and sales_debt_id per schema)
            $stmtPay = $db->prepare('INSERT INTO payment_records (sales_transaction_id, sales_debt_id, payment_date, payment_method_id, amount, amount_tendered, total_due, change_amount, received_by_user_id, notes, created_at) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, NOW())');
            if (!$stmtPay) throw new Exception('Failed to prepare payment record insert.');
            $stmtPay->bind_param('iiiddddis', $saleId, $debt['id'], $paymentMethodId, $applied, $amountTendered, $remaining, $change, $userId, $notes);
            if (!$stmtPay->execute()) throw new Exception('Failed to insert payment record.');
            $stmtPay->close();

            // Update sales_debts
            $newPaid = $paid + $applied;
            $newStatus = ($newPaid >= $total - 1e-6) ? 'paid' : 'partial';
            $stmtUpdDebt = $db->prepare('UPDATE sales_debts SET amount_paid = ?, status = ?, updated_at = NOW() WHERE id = ?');
            if (!$stmtUpdDebt) throw new Exception('Failed to prepare debt update.');
            $stmtUpdDebt->bind_param('dsi', $newPaid, $newStatus, $debt['id']);
            if (!$stmtUpdDebt->execute()) throw new Exception('Failed to update debt.');
            $stmtUpdDebt->close();

            // Update sales_transactions status to match
            $txStatus = ($newStatus === 'paid') ? 'paid' : 'partial';
            $stmtUpdTx = $db->prepare('UPDATE sales_transactions SET status = ? WHERE id = ?');
            if (!$stmtUpdTx) throw new Exception('Failed to prepare transaction update.');
            $stmtUpdTx->bind_param('si', $txStatus, $saleId);
            if (!$stmtUpdTx->execute()) throw new Exception('Failed to update transaction.');
            $stmtUpdTx->close();

            $db->commit();

            header('Location: view.php?id=' . $saleId . '&paid=1');
            exit;
        } catch (Exception $ex) {
            $db->rollback();
            $errors[] = $ex->getMessage();
        }
    }
}

// GET: load sale and debt details
$saleId = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : (isset($_POST['sale_id']) ? (int)$_POST['sale_id'] : 0);
if ($saleId <= 0) {
    require_once __DIR__ . '/../templates/header.php';
    echo '<div class="container-fluid py-4"><div class="alert alert-danger">Invalid sale ID.</div></div>';
    require_once __DIR__ . '/../templates/footer.php';
    exit;
}

// Fetch sale summary
$stmtSale = $db->prepare('SELECT st.id, st.transaction_date, st.total_amount, st.status, COALESCE(c.name, "Walk-in Customer") AS customer_name, c.contact AS customer_contact FROM sales_transactions st LEFT JOIN customers c ON c.id = st.customer_id WHERE st.id = ?');
$sale = null;
if ($stmtSale) {
    $stmtSale->bind_param('i', $saleId);
    $stmtSale->execute();
    $resSale = $stmtSale->get_result();
    $sale = $resSale->fetch_assoc();
    $resSale->free();
    $stmtSale->close();
}

// Fetch debt
$stmtDebt2 = $db->prepare('SELECT id, total_amount, amount_paid, status FROM sales_debts WHERE sales_transaction_id = ? LIMIT 1');
$debt = null;
if ($stmtDebt2) {
    $stmtDebt2->bind_param('i', $saleId);
    $stmtDebt2->execute();
    $resDebt2 = $stmtDebt2->get_result();
    $debt = $resDebt2->fetch_assoc();
    $resDebt2->free();
    $stmtDebt2->close();
}

// Fetch payment methods
$paymentMethods = [];
$resPm = $db->query('SELECT id, name FROM payment_methods ORDER BY name');
if ($resPm) {
    while ($row = $resPm->fetch_assoc()) $paymentMethods[] = $row;
    $resPm->free();
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Settle Payment</h1>
    <a href="view.php?id=<?php echo (int)$saleId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?></ul></div>
  <?php endif; ?>

  <?php if (!$sale || !$debt): ?>
    <div class="alert alert-warning">No outstanding debt found for this sale.</div>
  <?php else: ?>
    <?php 
      $remaining = max(0.0, (float)$debt['total_amount'] - (float)$debt['amount_paid']);
    ?>
    <div class="row g-4">
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white py-3"><h6 class="m-0">Sale Summary</h6></div>
          <div class="card-body">
            <div class="mb-2 d-flex justify-content-between"><span class="text-muted">Sale #</span><span class="fw-medium">#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></span></div>
            <div class="mb-2 d-flex justify-content-between"><span class="text-muted">Date</span><span><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($sale['transaction_date']))); ?></span></div>
            <div class="mb-2 d-flex justify-content-between"><span class="text-muted">Customer</span><span><?php echo htmlspecialchars($sale['customer_name']); ?></span></div>
            <div class="mb-2 d-flex justify-content-between"><span class="text-muted">Total Amount</span><span class="fw-bold">₱<?php echo number_format((float)$sale['total_amount'], 2); ?></span></div>
            <hr>
            <div class="mb-2 d-flex justify-content-between"><span class="text-muted">Amount Paid</span><span>₱<?php echo number_format((float)$debt['amount_paid'], 2); ?></span></div>
            <div class="mb-2 d-flex justify-content-between"><span class="text-muted">Remaining</span><span class="fw-bold text-danger">₱<?php echo number_format($remaining, 2); ?></span></div>
            <div class="mb-2 d-flex justify-content-between"><span class="text-muted">Debt Status</span><span><span class="badge bg-<?php echo ($debt['status']==='paid'?'success':($debt['status']==='partial'?'warning':'danger')); ?>"><?php echo ucfirst($debt['status']); ?></span></span></div>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white py-3"><h6 class="m-0">Record Payment</h6></div>
          <div class="card-body">
            <form method="post">
              <input type="hidden" name="sale_id" value="<?php echo (int)$saleId; ?>">
              <div class="mb-3">
                <label for="payment_method_id" class="form-label">Payment Method <span class="text-danger">*</span></label>
                <select name="payment_method_id" id="payment_method_id" class="form-select" required>
                  <option value="">Select a method</option>
                  <?php foreach ($paymentMethods as $pm): ?>
                    <option value="<?php echo (int)$pm['id']; ?>"><?php echo htmlspecialchars($pm['name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label for="amount_tendered" class="form-label">Amount Tendered <span class="text-danger">*</span></label>
                <div class="input-group input-group-lg">
                  <span class="input-group-text">₱</span>
                  <input type="number" step="0.01" min="0.01" class="form-control" id="amount_tendered" name="amount_tendered" value="<?php echo htmlspecialchars(number_format($remaining, 2, '.', '')); ?>" required>
                </div>
                <div class="form-text">You can enter the exact remaining or a higher amount; any excess will be recorded as change.</div>
              </div>
              <div class="mb-3">
                <label for="notes" class="form-label">Notes</label>
                <textarea name="notes" id="notes" rows="2" class="form-control" placeholder="Optional remarks"></textarea>
              </div>
              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-check me-1"></i> Apply Payment</button>
                <a href="view.php?id=<?php echo (int)$saleId; ?>" class="btn btn-outline-secondary"><i class="fas fa-times me-1"></i> Cancel</a>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php 
// Include footer
require_once __DIR__ . '/../templates/footer.php';