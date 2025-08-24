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

$errors = [];
$success = '';

// Load customers
$customers = [];
$resC = $db->query('SELECT id, name FROM customers ORDER BY name');
if ($resC) { while ($r = $resC->fetch_assoc()) $customers[] = $r; $resC->free(); }

// Payment method selection removed; debts will record a NULL payment_method_id.

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $customerId = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.0;
    $dueDate = trim($_POST['due_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($customerId <= 0) $errors[] = 'Please select a customer.';
    if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';
    if ($dueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) $errors[] = 'Invalid due date format.';

    if (empty($errors)) {
        try {
            $db->begin_transaction();

            // Create a minimal sales transaction representing the debt (no payment method)
            $stmtTx = $db->prepare('INSERT INTO sales_transactions (customer_id, transaction_date, payment_method_id, total_amount, status, created_by_user_id, notes, created_at) VALUES (?, NOW(), NULL, ?, ?, ?, ?, NOW())');
            if (!$stmtTx) throw new Exception('Failed to prepare transaction insert.');
            $statusTx = 'debt';
            $stmtTx->bind_param('idsis', $customerId, $amount, $statusTx, $userId, $notes);
            if (!$stmtTx->execute()) throw new Exception('Failed to insert sales transaction.');
            $saleId = (int)$stmtTx->insert_id;
            $stmtTx->close();

            // Create corresponding sales_debt (unpaid)
            $statusDebt = 'unpaid';
            $due = ($dueDate !== '') ? $dueDate : null;
            if ($due === null) {
                $stmtDebt = $db->prepare('INSERT INTO sales_debts (sales_transaction_id, customer_id, total_amount, amount_paid, due_date, status, notes, created_at, updated_at) VALUES (?, ?, ?, 0.00, NULL, ?, ?, NOW(), NOW())');
                if (!$stmtDebt) throw new Exception('Failed to prepare debt insert.');
                $stmtDebt->bind_param('iidss', $saleId, $customerId, $amount, $statusDebt, $notes);
            } else {
                $stmtDebt = $db->prepare('INSERT INTO sales_debts (sales_transaction_id, customer_id, total_amount, amount_paid, due_date, status, notes, created_at, updated_at) VALUES (?, ?, ?, 0.00, ?, ?, ?, NOW(), NOW())');
                if (!$stmtDebt) throw new Exception('Failed to prepare debt insert.');
                $stmtDebt->bind_param('iidsss', $saleId, $customerId, $amount, $due, $statusDebt, $notes);
            }
            if (!$stmtDebt->execute()) throw new Exception('Failed to insert sales debt.');
            $stmtDebt->close();

            $db->commit();

            $_SESSION['success'] = 'Debt recorded successfully.';
            header('Location: view.php?customer_id=' . $customerId);
            exit;
        } catch (Exception $ex) {
            $db->rollback();
            $errors[] = $ex->getMessage();
        }
    }
}

require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid py-4">
  <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4">
    <div class="text-center text-sm-start">
      <h1 class="h4 mb-0">New Debt</h1>
      <div class="text-muted small">Record a debt outside of Sales</div>
    </div>
    <div class="mt-1 mt-sm-0">
      <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
  <?php endif; ?>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <form method="post" class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Customer <span class="text-danger">*</span></label>
          <select name="customer_id" class="form-select" required>
            <option value="">Select customer</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Amount <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text">₱</span>
            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Due Date</label>
          <input type="date" name="due_date" class="form-control">
        </div>
        <div class="col-md-12">
          <label class="form-label">Notes</label>
          <textarea name="notes" rows="3" class="form-control" placeholder="Optional remarks"></textarea>
        </div>
        <div class="col-12 d-grid d-sm-flex gap-2">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Debt</button>
          <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
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

