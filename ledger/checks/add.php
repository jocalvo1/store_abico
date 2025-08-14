
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

// Handle POST (create)
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $check_number = trim($_POST['check_number'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $check_date = trim($_POST['check_date'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $status = trim($_POST['status'] ?? 'pending');
    $notes = trim($_POST['notes'] ?? '');
    $received_by_user_id = (int)($_SESSION['user_id'] ?? 0);

    if ($customer_id <= 0) $errors['customer_id'] = 'Customer is required';
    if ($check_number === '') $errors['check_number'] = 'Check number is required';
    if ($amount === '' || !is_numeric($amount) || (float)$amount <= 0) $errors['amount'] = 'Valid amount is required';
    if ($check_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_date)) $errors['check_date'] = 'Invalid date';
    if (!in_array($status, ['pending','cleared','bounced'], true)) $status = 'pending';

    if (empty($errors)) {
        $stmt = $db->prepare("INSERT INTO check_exchanges (customer_id, check_number, bank_name, check_date, amount, status, received_by_user_id, notes) VALUES (?,?,?,?,?,?,?,?)");
        $amt = (float)$amount;
        $stmt->bind_param('isssdiss', $customer_id, $check_number, $bank_name, $check_date, $amt, $status, $received_by_user_id, $notes);
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Check exchange created successfully';
            header('Location: index.php');
            exit();
        } else {
            $errors['form'] = 'Failed to save. Please try again.';
        }
        $stmt->close();
    }
}

// Load customers for select
$customers = [];
$res = $db->query("SELECT id, name FROM customers ORDER BY name ASC");
if ($res) { while ($row = $res->fetch_assoc()) $customers[] = $row; $res->free(); }

// Include header
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 mb-0">New Check Exchange</h1>
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>

    <?php if (!empty($errors['form'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['form']); ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Customer<span class="text-danger">*</span></label>
                    <select name="customer_id" class="form-select <?php echo isset($errors['customer_id'])?'is-invalid':''; ?>" required>
                        <option value="">Select customer</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo ((int)($_POST['customer_id']??0)===(int)$c['id'])?'selected':''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['customer_id'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['customer_id']); ?></div><?php endif; ?>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Check Number<span class="text-danger">*</span></label>
                    <input type="text" name="check_number" class="form-control <?php echo isset($errors['check_number'])?'is-invalid':''; ?>" value="<?php echo htmlspecialchars($_POST['check_number'] ?? ''); ?>" required>
                    <?php if (isset($errors['check_number'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['check_number']); ?></div><?php endif; ?>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Bank Name</label>
                    <input type="text" name="bank_name" class="form-control" value="<?php echo htmlspecialchars($_POST['bank_name'] ?? ''); ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Check Date</label>
                    <input type="date" name="check_date" class="form-control <?php echo isset($errors['check_date'])?'is-invalid':''; ?>" value="<?php echo htmlspecialchars($_POST['check_date'] ?? ''); ?>">
                    <?php if (isset($errors['check_date'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['check_date']); ?></div><?php endif; ?>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Amount<span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" step="0.01" min="0" name="amount" class="form-control <?php echo isset($errors['amount'])?'is-invalid':''; ?>" value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" required>
                    </div>
                    <?php if (isset($errors['amount'])): ?><div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['amount']); ?></div><?php endif; ?>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="pending" <?php echo (($_POST['status'] ?? '')==='pending')?'selected':''; ?>>Pending</option>
                        <option value="cleared" <?php echo (($_POST['status'] ?? '')==='cleared')?'selected':''; ?>>Cleared</option>
                        <option value="bounced" <?php echo (($_POST['status'] ?? '')==='bounced')?'selected':''; ?>>Bounced</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" rows="3" class="form-control" placeholder="Optional notes..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>

                <div class="col-12 mt-2">
                    <button class="btn btn-primary"><i class="fas fa-save me-1"></i> Save</button>
                    <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
// Include footer
require_once __DIR__ . '/../../templates/footer.php'; 
?>