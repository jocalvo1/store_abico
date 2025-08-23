<?php
// ledger/checks/export.php - Export check exchanges as CSV
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

$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$dateFrom = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$dateTo = isset($_GET['to']) ? trim((string)$_GET['to']) : '';

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
$sql .= ' ORDER BY ce.check_date DESC, ce.created_at DESC';

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

$filename = 'checks_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
$out = fopen('php://output', 'w');
// UTF-8 BOM for Excel
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Check Date',
    'Customer',
    'Check #',
    'Bank',
    'Amount',
    'Status',
    'Notes',
    'Received By',
]);

foreach ($rows as $r) {
    $date = $r['check_date'] ? date('Y-m-d', strtotime($r['check_date'])) : '';
    $customer = $r['customer_name'] ?? (isset($r['customer_id']) ? ('#' . (string)$r['customer_id']) : '');
    $amount = isset($r['amount']) && is_numeric($r['amount']) ? (float)$r['amount'] : $r['amount'];
    $statusV = isset($r['status']) ? ucfirst((string)$r['status']) : '';
    fputcsv($out, [
        $date,
        $customer,
        $r['check_number'] ?? '',
        $r['bank_name'] ?? '',
        $amount,
        $statusV,
        $r['notes'] ?? '',
        $r['received_by'] ?? '',
    ]);
}

fclose($out);
exit;
