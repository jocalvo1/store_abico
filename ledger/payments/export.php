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

// Filters (same as index.php)
$search   = isset($_GET['search']) ? trim($_GET['search']) : '';
$dateFrom = isset($_GET['from']) ? trim($_GET['from']) : '';
$dateTo   = isset($_GET['to']) ? trim($_GET['to']) : '';
$status   = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'all';
if (!in_array($status, ['all','outstanding','paid'], true)) { $status = 'all'; }

$where = [];
$params = [];
$types = '';

// Filter by outstanding, paid (was-debt), or all
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
            MIN(CASE WHEN sd.total_amount > sd.amount_paid AND sd.due_date IS NOT NULL THEN sd.due_date END) AS next_due_date,
            MAX((SELECT MAX(pr.payment_date) 
                 FROM payment_records pr 
                 WHERE pr.sales_debt_id = sd.id OR pr.sales_transaction_id = st.id)) AS last_payment
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

// Prepare CSV headers
$filename = 'customer_debts_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

// UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');

// Header row reflecting table columns
$colOpenPaid = ($status === 'paid') ? 'Paid Debts' : 'Open Debts';
$colRemaining = ($status === 'paid') ? 'Remaining' : 'Total Remaining';
$headers = [
    'Customer',
    $colOpenPaid,
    'Total Amount',
    'Total Paid',
    $colRemaining,
    'Due Date',
    'Last Payment Date',
    'Last Payment Time'
];
fputcsv($out, $headers);

// Data rows
foreach ($rows as $r) {
    $customer = $r['customer_name'] ?? '';
    $openInvoices = (int)($r['open_invoices'] ?? 0);
    $totalDue = number_format((float)($r['total_due'] ?? 0), 2, '.', '');
    $totalPaid = number_format((float)($r['total_paid'] ?? 0), 2, '.', '');
    $totalRemaining = number_format((float)($r['total_remaining'] ?? 0), 2, '.', '');

    // Dates: prefer ISO for CSV
    $dueDate = '';
    if (!empty($r['next_due_date']) && ($status === 'outstanding' || $status === 'all')) {
        $dueDate = date('Y-m-d', strtotime($r['next_due_date']));
    }
    $lastPaymentDate = '';
    $lastPaymentTime = '';
    if (!empty($r['last_payment'])) {
        $ts = strtotime($r['last_payment']);
        $lastPaymentDate = date('Y-m-d', $ts);
        $lastPaymentTime = date('H:i:s', $ts);
    }

    fputcsv($out, [
        $customer,
        $openInvoices,
        $totalDue,
        $totalPaid,
        $totalRemaining,
        $dueDate ?: '-',
        $lastPaymentDate ?: '-',
        $lastPaymentTime ?: '-',
    ]);
}

fclose($out);
exit;
