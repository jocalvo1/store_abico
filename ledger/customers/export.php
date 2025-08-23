<?php
// ledger/customers/export.php - Export customers as CSV
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../controller/customer/customerController.php';

$customerController = new customerController();
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

if ($search !== '') {
    $res = $customerController->search($search);
} else {
    $res = $customerController->getAll();
}

$rows = [];
if (is_object($res) && method_exists($res, 'fetch_all')) {
    $rows = $res->fetch_all(MYSQLI_ASSOC);
}

$filename = 'customers_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Name',
    'Contact',
    'Address',
]);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['name'] ?? '',
        $r['contact'] ?? '',
        $r['address'] ?? '',
    ]);
}

fclose($out);
exit;
