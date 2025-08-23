<?php
// sales/export.php - Export sales data as CSV

// Start session and restrict to authenticated users
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../controller/sale/salesController.php';

// Init DB and controller
$db = getDBConnection();
$controller = new SalesController($db);

// Optional search filter
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

// Get data
if ($search !== '') {
    $rows = $controller->search($search);
} else {
    // Use a high limit to include most records; adjust if needed
    $rows = $controller->getAll(1000000);
}

// Output headers for CSV download
$filename = 'sales_export_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Add UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Column headers
fputcsv($out, [
    'Date',
    'Time',
    'Invoice #',
    'Customer',
    'Amount',
    'Payment Method',
    'Status',
]);

// Rows
foreach ($rows as $r) {
    $dt = strtotime($r['transaction_date']);
    $date = $dt ? date('Y-m-d', $dt) : '';
    $time = $dt ? date('H:i:s', $dt) : '';
    $invoice = '#' . str_pad((string)$r['id'], 6, '0', STR_PAD_LEFT);
    $customer = isset($r['customer_name']) && $r['customer_name'] !== '' ? $r['customer_name'] : 'Walk-in Customer';
    // Amount as plain number for Excel math
    $amount = is_numeric($r['total_amount']) ? (float)$r['total_amount'] : $r['total_amount'];
    $payment = $r['payment_method_name'] ?? '';
    $status = ucfirst((string)$r['status']);

    fputcsv($out, [
        $date,
        $time,
        $invoice,
        $customer,
        $amount,
        $payment,
        $status,
    ]);
}

fclose($out);
exit;
