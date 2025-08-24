
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

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$dateFrom = isset($_GET['from']) ? trim($_GET['from']) : '';
$dateTo = isset($_GET['to']) ? trim($_GET['to']) : '';
$status = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'all';
if (!in_array($status, ['all','outstanding','paid'], true)) { $status = 'all'; }

$where = [];
$params = [];
$types = '';

// Filter by outstanding, paid (was-debt), or all
if ($status === 'outstanding') {
    $where[] = 'sd.total_amount > sd.amount_paid';
} elseif ($status === 'paid') {
    $where[] = "sd.status = 'paid'";
} // 'all' -> no status restriction

if ($search !== '') {
    $where[] = 'COALESCE(c.name, "Walk-in Customer") LIKE ?';
    $s = '%' . $search . '%';
    $params[] = $s; $types .= 's';
}
if ($dateFrom !== '') {
    $where[] = 'DATE(st.transaction_date) >= ?';
    $params[] = $dateFrom; $types .= 's';
}
if ($dateTo !== '') {
    $where[] = 'DATE(st.transaction_date) <= ?';
    $params[] = $dateTo; $types .= 's';
}

$sql = "SELECT 
            COALESCE(c.id, 0) AS customer_id,
            COALESCE(c.name, 'Walk-in Customer') AS customer_name,
            SUM(CASE WHEN sd.total_amount > sd.amount_paid THEN 1 ELSE 0 END) AS open_invoices,
            SUM(sd.total_amount) AS total_due,
            SUM(sd.amount_paid) AS total_paid,
            SUM(GREATEST(sd.total_amount - sd.amount_paid, 0)) AS total_remaining,
            MIN(CASE WHEN sd.total_amount > sd.amount_paid AND sd.due_date IS NOT NULL THEN sd.due_date END) AS next_due_date,
            MAX((SELECT MAX(pr.payment_date) 
                 FROM payment_records pr 
                 WHERE pr.sales_debt_id = sd.id OR pr.sales_transaction_id = st.id)) AS last_payment,
            MIN(CASE WHEN sd.total_amount > sd.amount_paid THEN st.id END) AS unpaid_sale_id
        FROM sales_debts sd
        JOIN sales_transactions st ON st.id = sd.sales_transaction_id
        LEFT JOIN customers c ON c.id = st.customer_id";
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' GROUP BY COALESCE(c.id,0), COALESCE(c.name,\'Walk-in Customer\')';
if ($status === 'outstanding' || $status === 'all') {
    $sql .= ' ORDER BY total_remaining DESC, customer_name ASC';
} else {
    $sql .= ' ORDER BY customer_name ASC';
}

$stmt = $db->prepare($sql);
if ($stmt && $types !== '') {
    $stmt->bind_param($types, ...$params);
}
$rows = [];
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $res->free();
    $stmt->close();
}

// Include header
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Customer Debts</h1>
            <div class="text-muted small"><?php echo count($rows); ?> customers</div>
        </div>
        <div class="d-flex gap-2">
            <?php 
                // Build query string for current filters
                $qs = http_build_query([
                    'search' => $search,
                    'status' => $status,
                    'from'   => $dateFrom,
                    'to'     => $dateTo,
                ]);
            ?>
            <a href="export.php<?php echo $qs ? ('?' . $qs) : ''; ?>" class="btn btn-success btn-sm d-flex align-items-center" title="Download CSV" data-bs-toggle="tooltip">
                <i class="fas fa-file-excel me-1"></i>
                <span class="d-inline d-sm-none">Export CSV</span>
                <span class="d-none d-sm-inline">Export CSV</span>
            </a>
            <a href="add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> New Debt</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Customer name">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $status==='all'?'selected':''; ?>>All</option>
                        <option value="outstanding" <?php echo $status==='outstanding'?'selected':''; ?>>Outstanding</option>
                        <option value="paid" <?php echo $status==='paid'?'selected':''; ?>>Paid (Was-debt)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" class="form-control" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" class="form-control" name="to" value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
                <div class="col-12 mt-2">
                    <button class="btn btn-primary">Filter</button>
                    <a class="btn btn-outline-secondary" href="index.php">Reset</a>
                </div>
            </form>
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

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3">Customer</th>
                        <th class="text-center d-none d-sm-table-cell"><?php echo $status==='paid'?'Paid Debts':'Open Debts'; ?></th>
                        <th class="text-end">Total Amount</th>
                        <th class="text-end d-none d-lg-table-cell">Total Paid</th>
                        <th class="text-end"><?php echo $status==='paid'?'Remaining':'Total Remaining'; ?></th>
                        <th class="text-center d-none d-md-table-cell">Due Date</th>
                        <th class="text-center d-none d-md-table-cell">Last Payment</th>
                        <th class="text-end d-none d-md-table-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No outstanding debts found</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td class="ps-3">
                                <?php if ((int)$r['customer_id'] > 0): ?>
                                    <a href="../../ledger/customers/view.php?id=<?php echo (int)$r['customer_id']; ?>" class="text-decoration-none fw-medium">
                                        <?php echo htmlspecialchars($r['customer_name']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="fw-medium"><?php echo htmlspecialchars($r['customer_name']); ?></span>
                                <?php endif; ?>
                                <!-- Mobile-only details -->
                                <div class="d-md-none small text-muted mt-1">
                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <span class="badge <?php echo $status==='paid'?'bg-success':'bg-warning text-dark'; ?>">
                                            <i class="fas fa-file-invoice me-1"></i><?php echo (int)$r['open_invoices']; ?>
                                        </span>
                                        <?php if (!empty($r['next_due_date']) && ($status === 'outstanding' || $status === 'all')): ?>
                                            <?php $overdue = (strtotime($r['next_due_date']) < strtotime(date('Y-m-d'))); ?>
                                            <span class="<?php echo $overdue ? 'text-danger' : ''; ?>">
                                                <i class="far fa-calendar-alt me-1"></i><?php echo date('M d, Y', strtotime($r['next_due_date'])); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($r['last_payment'])): ?>
                                            <span><i class="far fa-clock me-1"></i><?php echo date('M d, Y h:i A', strtotime($r['last_payment'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Mobile-only primary actions -->
                                    <div class="mt-2 d-flex flex-wrap gap-2">
                                        <a href="view.php?customer_id=<?php echo (int)$r['customer_id']; ?>" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                                            <i class="fas fa-eye fa-xs"></i><span>View</span>
                                        </a>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center d-none d-sm-table-cell">
                                <span class="badge <?php echo $status==='paid'?'bg-success':'bg-warning text-dark'; ?>"><?php echo (int)$r['open_invoices']; ?></span>
                            </td>
                            <td class="text-end">₱<?php echo number_format((float)$r['total_due'], 2); ?></td>
                            <td class="text-end text-muted d-none d-lg-table-cell">₱<?php echo number_format((float)$r['total_paid'], 2); ?></td>
                            <td class="text-end fw-bold <?php echo $status==='paid'?'text-muted':'text-danger'; ?>">₱<?php echo number_format((float)$r['total_remaining'], 2); ?></td>
                            <td class="text-center small d-none d-md-table-cell">
                                <?php if (!empty($r['next_due_date']) && ($status === 'outstanding' || $status === 'all')): ?>
                                    <?php $overdue = (strtotime($r['next_due_date']) < strtotime(date('Y-m-d'))); ?>
                                    <span class="<?php echo $overdue ? 'text-danger fw-semibold' : 'text-muted'; ?>"><?php echo date('M d, Y', strtotime($r['next_due_date'])); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center small text-muted d-none d-md-table-cell"><?php echo $r['last_payment'] ? date('M d, Y h:i A', strtotime($r['last_payment'])) : '-'; ?></td>
                            <td class="text-end d-none d-md-table-cell">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="view.php?customer_id=<?php echo (int)$r['customer_id']; ?>" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
                                        <i class="fas fa-eye fa-xs"></i><span class="d-none d-lg-inline">View</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
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