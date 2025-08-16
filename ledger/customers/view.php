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

// Check if ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No customer ID provided.";
    header("Location: index.php");
    exit();
}

$customer_id = intval($_GET['id']);
$conn = getDBConnection();

// Fetch customer details
$customer = null;
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Customer not found.";
    header("Location: index.php");
    exit();
}

$customer = $result->fetch_assoc();
$stmt->close();

// Fetch debt summary
$debtSummary = [
    'total_balance' => 0,
    'total_debt_amount' => 0,
    'total_paid' => 0,
    'next_due' => null,
    'open_debts' => 0,
];
$stmt = $conn->prepare("SELECT 
        COALESCE(SUM(balance_due),0) AS total_balance,
        COALESCE(SUM(total_amount),0) AS total_debt_amount,
        COALESCE(SUM(amount_paid),0) AS total_paid,
        MIN(due_date) AS next_due,
        COUNT(*) AS open_debts
    FROM sales_debts 
    WHERE customer_id = ? AND status IN ('unpaid','partial')");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $row = $res->fetch_assoc()) { $debtSummary = $row; }
$stmt->close();

// Fetch open debts list (limited)
$debts = [];
$stmt = $conn->prepare("SELECT id, sales_transaction_id, total_amount, amount_paid, balance_due, due_date, status
    FROM sales_debts
    WHERE customer_id = ? AND balance_due > 0 AND status IN ('unpaid','partial')
    ORDER BY (due_date IS NULL), due_date ASC, balance_due DESC
    LIMIT 100");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res) { while ($row = $res->fetch_assoc()) { $debts[] = $row; } }
$stmt->close();

// Fetch recent transactions
$transactions = [];
$stmt = $conn->prepare("SELECT st.id, st.transaction_date, st.total_amount, st.status, pm.name AS payment_method
    FROM sales_transactions st
    LEFT JOIN payment_methods pm ON pm.id = st.payment_method_id
    WHERE st.customer_id = ?
    ORDER BY st.transaction_date DESC
    LIMIT 50");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res) { while ($row = $res->fetch_assoc()) { $transactions[] = $row; } }
$stmt->close();

// Include header
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <?php echo htmlspecialchars($customer['name']); ?>
            <small class="text-muted">Customer Details</small>
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="edit.php?id=<?php echo $customer_id; ?>" class="btn btn-sm btn-outline-secondary me-2">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Customers
            </a>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Outstanding</div>
                        <div class="h4 mb-0">₱<?php echo number_format((float)($debtSummary['total_balance'] ?? 0), 2); ?></div>
                    </div>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">DEBT</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Open Debts</div>
                        <div class="h4 mb-0"><?php echo number_format((int)($debtSummary['open_debts'] ?? 0)); ?></div>
                    </div>
                    <span class="badge bg-warning text-dark">Open</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Last Transaction</div>
                        <div class="h6 mb-0">
                            <?php echo !empty($transactions) ? htmlspecialchars(date('Y-m-d H:i', strtotime($transactions[0]['transaction_date']))) : '—'; ?>
                        </div>
                    </div>
                    <i class="fas fa-receipt text-muted"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Customer Information</h6>
                </div>
                <div class="card-body p-0">
                    <div class="row g-4 p-3">
                        <!-- Contact & Address Column -->
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <h5 class="mb-3 border-bottom pb-2"><i class="fas fa-address-card me-2"></i>Contact Information</h5>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0 me-3 text-primary">
                                                <i class="fas fa-phone fa-lg"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 text-muted small">Phone</h6>
                                                <p class="mb-0">
                                                    <?php if (!empty($customer['contact'])): ?>
                                                        <a href="tel:<?php echo htmlspecialchars($customer['contact']); ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($customer['contact']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">No contact information added</span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0 me-3 text-primary">
                                                <i class="fas fa-map-marker-alt fa-lg"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 text-muted small">Address</h6>
                                                <p class="mb-0">
                                                    <?php echo !empty($customer['address']) ? nl2br(htmlspecialchars($customer['address'])) : '<span class="text-muted">No address provided</span>'; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Additional Details Column -->
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <h5 class="mb-3 border-bottom pb-2"><i class="fas fa-info-circle me-2"></i>Additional Details</h5>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0 me-3 text-primary">
                                                <i class="fas fa-calendar-day fa-lg"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 text-muted small">Customer Since</h6>
                                                <p class="mb-0"><?php echo date('F j, Y', strtotime($customer['created_at'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0 me-3 text-primary">
                                                <i class="fas fa-sync-alt fa-lg"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 text-muted small">Last Updated</h6>
                                                <p class="mb-0"><?php echo date('F j, Y h:i A', strtotime($customer['updated_at'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-12">
            <div class="card mb-4 h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Outstanding Debts</h6>
                    <span class="badge bg-secondary">Open: <?php echo number_format((int)($debtSummary['open_debts'] ?? 0)); ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="row g-0 p-3">
                        <div class="col-6">
                            <div class="small text-muted">Total Outstanding</div>
                            <div class="h5 mb-0">₱<?php echo number_format((float)($debtSummary['total_balance'] ?? 0), 2); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Next Due</div>
                            <div class="fw-semibold"><?php echo !empty($debtSummary['next_due']) ? htmlspecialchars($debtSummary['next_due']) : '—'; ?></div>
                        </div>
                    </div>
                    <?php if (empty($debts)): ?>
                        <div class="p-3 text-muted">No open debts.</div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 400px; overflow:auto;">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th>Debt #</th>
                                        <th class="text-end">Paid</th>
                                        <th class="text-end">Balance</th>
                                        <th class="text-nowrap">Due</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $today = new DateTime('now');
                                    foreach ($debts as $d):
                                        $due = !empty($d['due_date']) ? new DateTime($d['due_date']) : null;
                                        $isOverdue = $due && $due < $today;
                                        $badge = $isOverdue ? 'danger' : ($d['status']==='partial' ? 'warning text-dark' : 'secondary');
                                    ?>
                                        <tr>
                                            <td>#<?php echo (int)$d['id']; ?></td>
                                            <td class="text-end" style="min-width:160px;">
                                                <?php 
                                                    $total = max(0.01, (float)($d['total_amount'] ?? 0));
                                                    $paid = min($total, (float)($d['amount_paid'] ?? 0));
                                                    $pct = (int)round(($paid / $total) * 100);
                                                ?>
                                                <div class="small text-muted">₱<?php echo number_format($paid,2); ?> / ₱<?php echo number_format($total,2); ?> (<?php echo $pct; ?>%)</div>
                                                <div class="progress" style="height:6px;">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $pct; ?>%" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </td>
                                            <td class="text-end">₱<?php echo number_format((float)$d['balance_due'], 2); ?></td>
                                            <td class="text-nowrap">
                                                <?php 
                                                    if ($due) {
                                                        $diffDays = (int)$today->diff($due)->format('%r%a');
                                                        echo $due->format('Y-m-d');
                                                        if ($diffDays < 0) { echo ' <span class="text-danger small">('.abs($diffDays).'d overdue)</span>'; }
                                                        elseif ($diffDays > 0) { echo ' <span class="text-muted small">(in '.$diffDays.'d)</span>'; }
                                                    } else { echo '—'; }
                                                ?>
                                                <?php if ($isOverdue): ?><span class="badge bg-danger ms-1">Overdue</span><?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-<?php echo $badge; ?> text-uppercase"><?php echo htmlspecialchars($d['status']); ?></span></td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-outline-primary" href="../../sales/view.php?id=<?php echo (int)$d['sales_transaction_id']; ?>">View Sale</a>
                                                <a class="btn btn-sm btn-success ms-1" href="../../sales/payment.php?sale_id=<?php echo (int)$d['sales_transaction_id']; ?>">Settle</a>
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
        <div class="col-md-12">
            <div class="card mb-4 h-100">
                <div class="card-header">
                    <h6 class="mb-0">Recent Transactions</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($transactions)): ?>
                        <div class="p-3 text-muted">No transactions found.</div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 400px; overflow:auto;">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Payment</th>
                                        <th class="text-end">Total</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $t): ?>
                                        <tr>
                                            <td>#<?php echo (int)$t['id']; ?></td>
                                            <td class="text-nowrap"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($t['transaction_date']))); ?></td>
                                            <td><?php echo htmlspecialchars($t['payment_method'] ?? ''); ?></td>
                                            <td class="text-end">₱<?php echo number_format((float)$t['total_amount'], 2); ?></td>
                                            <td>
                                                <?php 
                                                    $statusBadge = ['paid'=>'success','debt'=>'danger','partial'=>'warning text-dark'][$t['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $statusBadge; ?> text-uppercase"><?php echo htmlspecialchars($t['status']); ?></span>
                                            </td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-outline-primary" href="../../sales/view.php?id=<?php echo (int)$t['id']; ?>">View</a>
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
    </div>
</div>
<?php
// Close DB connection
if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
// Include footer
require_once __DIR__ . '/../../templates/footer.php';
?>
