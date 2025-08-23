<?php
// ledger/payments/export.php - Export customer debts summary as CSV
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../includes/database.php';

$db = getDBConnection();
$db->set_charset('utf8mb4');

// Filters (mirror index.php)
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$dateFrom = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$dateTo = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
$status = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : 'all';
if (!in_array($status, ['all','outstanding','paid'], true)) { $status = 'all'; }

$where = [];
$params = [];
$types = '';

if ($status === 'outstanding') {
    $where[] = 'sd.total_amount > sd.amount_paid';
} elseif ($status === 'paid') {
    $where[] = "sd.status = 'paid'";
}
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
            MAX(st.transaction_date) AS last_transaction
        FROM sales_debts sd
        JOIN sales_transactions st ON st.id = sd.sales_transaction_id
        LEFT JOIN customers c ON c.id = st.customer_id";
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= " GROUP BY COALESCE(c.id,0), COALESCE(c.name,'Walk-in Customer')";
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

$filename = 'customer_debts_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
$out = fopen('php://output', 'w');
// UTF-8 BOM for Excel
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Customer',
    'Open Invoices',
    'Total Amount',
    'Total Paid',
    'Total Remaining',
    'Last Transaction',
]);

foreach ($rows as $r) {
    $last = $r['last_transaction'] ? date('Y-m-d H:i:s', strtotime($r['last_transaction'])) : '';
    fputcsv($out, [
        $r['customer_name'] ?? '',
        isset($r['open_invoices']) ? (int)$r['open_invoices'] : 0,
        isset($r['total_due']) && is_numeric($r['total_due']) ? (float)$r['total_due'] : ($r['total_due'] ?? ''),
        isset($r['total_paid']) && is_numeric($r['total_paid']) ? (float)$r['total_paid'] : ($r['total_paid'] ?? ''),
        isset($r['total_remaining']) && is_numeric($r['total_remaining']) ? (float)$r['total_remaining'] : ($r['total_remaining'] ?? ''),
        $last,
    ]);
}

fclose($out);
exit;
