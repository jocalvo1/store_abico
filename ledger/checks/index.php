
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

// DB
$db = getDBConnection();

// Filters (mirror stock_movements style)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$dateFrom = isset($_GET['from']) ? trim($_GET['from']) : '';
$dateTo = isset($_GET['to']) ? trim($_GET['to']) : '';

// Build WHERE
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(c.name LIKE ? OR ce.check_number LIKE ? OR ce.bank_name LIKE ? OR ce.notes LIKE ?)';
    $s = '%' . $search . '%';
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $types .= 'ssss';
}
if (in_array($status, ['pending','cleared','bounced'], true)) {
    $where[] = 'ce.status = ?';
    $params[] = $status; $types .= 's';
}
if ($dateFrom !== '') {
    $where[] = 'DATE(ce.check_date) >= ?';
    $params[] = $dateFrom; $types .= 's';
}
if ($dateTo !== '') {
    $where[] = 'DATE(ce.check_date) <= ?';
    $params[] = $dateTo; $types .= 's';
}

$sql = "SELECT ce.id, ce.customer_id, ce.check_number, ce.bank_name, ce.check_date, ce.amount, ce.status, ce.received_by_user_id, ce.notes, ce.created_at,
               c.name AS customer_name,
               u.username AS received_by
        FROM check_exchanges ce
        LEFT JOIN customers c ON c.id = ce.customer_id
        LEFT JOIN users u ON u.id = ce.received_by_user_id";
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY ce.check_date DESC, ce.created_at DESC LIMIT 200';

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

// Include header last so title/nav render after auth
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 mt-2">Check Exchanges</h1>
            <div class="text-muted small"><?php echo count($rows); ?> records</div>
        </div>
        <div>
            <a href="add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> New Check</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Customer, check #, bank, notes">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="pending" <?php echo $status==='pending'?'selected':''; ?>>Pending</option>
                        <option value="cleared" <?php echo $status==='cleared'?'selected':''; ?>>Cleared</option>
                        <option value="bounced" <?php echo $status==='bounced'?'selected':''; ?>>Bounced</option>
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
                    <?php $qs = http_build_query(['search' => $search, 'status' => $status, 'from' => $dateFrom, 'to' => $dateTo]); ?>
                    <a class="btn btn-success me-2" href="export.php?<?php echo $qs; ?>">
                        <i class="fas fa-file-csv me-1"></i> Export CSV
                    </a>
                    <button class="btn btn-primary">Filter</button>
                    <a class="btn btn-outline-secondary" href="index.php">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3 d-none d-md-table-cell">Check Date</th>
                        <th>Customer</th>
                        <th class="d-none d-lg-table-cell">Check #</th>
                        <th class="d-none d-lg-table-cell">Bank</th>
                        <th class="text-end">Amount</th>
                        <th class="text-center d-none d-md-table-cell">Status</th>
                        <th class="d-none d-lg-table-cell">Notes</th>
                        <th class="text-center d-none d-md-table-cell">Received By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No checks found</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td class="ps-3 d-none d-md-table-cell"><?php echo $r['check_date'] ? date('M d, Y', strtotime($r['check_date'])) : '<span class="text-muted">-</span>'; ?></td>
                            <td>
                                <div class="fw-medium d-inline-block text-truncate" style="max-width: 70vw;">
                                    <?php echo htmlspecialchars($r['customer_name'] ?? ('#'.$r['customer_id'])); ?>
                                </div>
                                <!-- Mobile-only details -->
                                <div class="d-md-none small text-muted mt-1">
                                    <div><i class="far fa-calendar-alt me-1"></i><?php echo $r['check_date'] ? date('M d, Y', strtotime($r['check_date'])) : '-'; ?></div>
                                    <div class="mt-1"><i class="fas fa-hashtag me-1"></i><span class="font-monospace"><?php echo htmlspecialchars($r['check_number']); ?></span></div>
                                    <div><i class="fas fa-university me-1"></i><?php echo htmlspecialchars($r['bank_name'] ?? ''); ?></div>
                                    <div class="mt-1 d-flex align-items-center gap-2">
                                        <?php 
                                            $st = $r['status'] ?? 'pending';
                                            $cls = $st==='cleared' ? 'success' : ($st==='bounced' ? 'danger' : 'warning');
                                        ?>
                                        <span class="badge bg-<?php echo $cls; ?> text-uppercase"><?php echo htmlspecialchars($st); ?></span>
                                        <span class="badge bg-light text-dark">₱<?php echo number_format((float)$r['amount'], 2); ?></span>
                                    </div>
                                    <?php $fullNotes = $r['notes'] ?? ''; ?>
                                    <div class="mt-1">
                                        <i class="far fa-sticky-note me-1"></i>
                                        <span class="d-inline-block text-truncate" style="max-width: 70vw;" title="<?php echo htmlspecialchars($fullNotes); ?>"><?php echo htmlspecialchars($fullNotes); ?></span>
                                    </div>
                                    <div class="mt-1"><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($r['received_by'] ?? ''); ?></div>
                                </div>
                            </td>
                            <td class="font-monospace d-none d-lg-table-cell"><?php echo htmlspecialchars($r['check_number']); ?></td>
                            <td class="d-none d-lg-table-cell"><?php echo htmlspecialchars($r['bank_name'] ?? ''); ?></td>
                            <td class="text-end">₱<?php echo number_format((float)$r['amount'], 2); ?></td>
                            <td class="text-center d-none d-md-table-cell">
                                <?php 
                                    $st = $r['status'] ?? 'pending';
                                    $cls = $st==='cleared' ? 'success' : ($st==='bounced' ? 'danger' : 'warning');
                                ?>
                                <span class="badge bg-<?php echo $cls; ?> text-uppercase"><?php echo htmlspecialchars($st); ?></span>
                            </td>
                            <td class="small d-none d-lg-table-cell">
                                <?php $fullNotes = $r['notes'] ?? ''; ?>
                                <span class="d-inline-block text-truncate" style="max-width: 420px;" title="<?php echo htmlspecialchars($fullNotes); ?>">
                                    <?php echo htmlspecialchars($fullNotes); ?>
                                </span>
                            </td>
                            <td class="text-center small text-muted d-none d-md-table-cell"><?php echo htmlspecialchars($r['received_by'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
// Include footer
require_once __DIR__ . '/../../templates/footer.php'; 
?>