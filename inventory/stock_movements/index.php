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

// Include header
require_once __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/../../includes/database.php';

// DB
$db = getDBConnection();

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$dateFrom = isset($_GET['from']) ? trim($_GET['from']) : '';
$dateTo = isset($_GET['to']) ? trim($_GET['to']) : '';

// Build query
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(i.name LIKE ? OR i.unit LIKE ? OR sm.notes LIKE ? OR sm.reference_type LIKE ? OR CAST(sm.reference_id AS CHAR) LIKE ?)';
    $s = '%' . $search . '%';
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $types .= 'sssss';
}
if ($type === 'in' || $type === 'out') {
    $where[] = 'sm.movement_type = ?';
    $params[] = $type; $types .= 's';
}
// Reference-specific filter removed; covered by combined text search
if ($dateFrom !== '') {
    $where[] = 'DATE(sm.created_at) >= ?';
    $params[] = $dateFrom; $types .= 's';
}
if ($dateTo !== '') {
    $where[] = 'DATE(sm.created_at) <= ?';
    $params[] = $dateTo; $types .= 's';
}

$sql = "SELECT sm.id, sm.item_id, sm.movement_type, sm.quantity, sm.reference_type, sm.reference_id, sm.notes, sm.created_at,
               i.name AS item_name, i.unit AS item_unit
        FROM stock_movements sm
        LEFT JOIN items i ON i.id = sm.item_id";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY sm.created_at DESC LIMIT 200';

$stmt = $db->prepare($sql);
if ($stmt && $types !== '') {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $res->free();
    $stmt->close();
} else {
    $rows = [];
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 mb-0">Stock Movements</h1>
        <div class="text-muted small"><?php echo count($rows); ?> records</div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Item, unit, notes, reference type or #">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All</option>
                        <option value="in" <?php echo $type==='in'?'selected':''; ?>>In</option>
                        <option value="out" <?php echo $type==='out'?'selected':''; ?>>Out</option>
                    </select>
                </div>
                <!-- Reference filter removed; use the main Search field instead -->
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

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3">Date</th>
                        <th>Item</th>
                        <th class="text-center">Type</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-center">Reference Type</th>
                        <th class="text-center">Reference #</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No stock movements found</td></tr>
                    <?php else: foreach ($rows as $mv): ?>
                    <tr>
                        <td class="ps-3"><?php echo date('M d, Y h:i A', strtotime($mv['created_at'])); ?></td>
                        <td>
                            <div class="fw-medium"><?php echo htmlspecialchars($mv['item_name'] ?? ('#'.$mv['item_id'])); ?></div>
                            <div class="small text-muted">Unit: <?php echo htmlspecialchars($mv['item_unit'] ?? ''); ?></div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $mv['movement_type']==='in'?'success':'danger'; ?> text-uppercase">
                                <?php echo htmlspecialchars($mv['movement_type']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <?php $sign = ($mv['movement_type']==='in') ? '+' : '-'; ?>
                            <span class="font-monospace">
                                <?php echo $sign . number_format((float)$mv['quantity']); ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php $rt = $mv['reference_type'] ?? ''; $rtClass = ($rt==='sales') ? 'primary' : (($rt==='adjustment') ? 'warning' : (($rt==='delivery') ? 'info' : (($rt==='return') ? 'secondary' : 'secondary'))); ?>
                            <span class="badge bg-<?php echo $rtClass; ?> text-uppercase"><?php echo htmlspecialchars($rt ?: '-'); ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ($mv['reference_type'] === 'sales' && $mv['reference_id']): ?>
                                <a href="../../sales/view.php?id=<?php echo (int)$mv['reference_id']; ?>" class="text-decoration-none">
                                    #<?php echo str_pad((int)$mv['reference_id'], 6, '0', STR_PAD_LEFT); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted small font-monospace"><?php echo $mv['reference_id'] ? '#'.(int)$mv['reference_id'] : '-'; ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?php $fullNotes = $mv['notes'] ?? ''; ?>
                            <span class="d-inline-block text-truncate" style="max-width: 420px;" title="<?php echo htmlspecialchars($fullNotes); ?>">
                                <?php echo htmlspecialchars($fullNotes); ?>
                            </span>
                        </td>
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