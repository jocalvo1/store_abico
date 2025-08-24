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
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

$params = [];
$types  = '';
$where  = '';

if ($searchTerm !== '') {
    $where = 'WHERE c.name LIKE ? OR c.contact LIKE ? OR c.address LIKE ?';
    $s = "%$searchTerm%";
    $params = [$s, $s, $s];
    $types  = 'sss';
}

$sql = "SELECT 
            c.id,
            c.name,
            c.contact,
            c.address,
            COALESCE(SUM(CASE WHEN sd.total_amount > sd.amount_paid THEN 1 ELSE 0 END), 0) AS open_invoices,
            COALESCE(SUM(GREATEST(sd.total_amount - sd.amount_paid, 0)), 0) AS total_remaining,
            MIN(CASE WHEN sd.total_amount > sd.amount_paid AND sd.due_date IS NOT NULL THEN sd.due_date END) AS next_due_date
        FROM customers c
        LEFT JOIN sales_debts sd 
          ON sd.customer_id = c.id 
         AND sd.status IN ('unpaid','partial')
        $where
        GROUP BY c.id
        ORDER BY c.name ASC";

$stmt = $db->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo 'Failed to prepare statement.';
    exit;
}
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
$res->free();
$stmt->close();

// Prepare CSV headers
$filename = 'customers_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

// UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');

// Header row (mirrors visible table + useful finance columns)
$headers = [
    'Customer',
    'Contact',
    'Address',
    'Open Invoices',
    'Total Remaining',
    'Next Due Date'
];
fputcsv($out, $headers);

foreach ($rows as $r) {
    $name   = $r['name'] ?? '';
    $contact = $r['contact'] ?? '';
    $address = $r['address'] ?? '';
    $open   = (int)($r['open_invoices'] ?? 0);
    $remaining = number_format((float)($r['total_remaining'] ?? 0), 2, '.', '');

    $dueDate = '';
    if (!empty($r['next_due_date'])) {
        $dueDate = date('Y-m-d', strtotime($r['next_due_date']));
    }

    fputcsv($out, [
        $name,
        $contact,
        $address,
        $open,
        $remaining,
        $dueDate ?: '-'
    ]);
}

fclose($out);
exit;
