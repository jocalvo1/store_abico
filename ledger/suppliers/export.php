<?php
// ledger/suppliers/export.php - Export suppliers as CSV
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../controller/supplier/supplierController.php';

$supplierController = new SupplierController();
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

if ($search !== '') {
    $res = $supplierController->search($search);
} else {
    $res = $supplierController->getAll();
}

$rows = [];
if (is_object($res) && method_exists($res, 'fetch')) {
    while ($r = $res->fetch()) { $rows[] = $r; }
}

$filename = 'suppliers_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Supplier',
    'Contact Person',
    'Phone',
    'Email',
    'Purchase Orders',
    'Total Purchases',
]);

foreach ($rows as $r) {
    $total = isset($r['total_purchases']) && is_numeric($r['total_purchases']) ? (float)$r['total_purchases'] : $r['total_purchases'];
    fputcsv($out, [
        $r['name'] ?? '',
        $r['contact_person'] ?? '',
        $r['contact_number'] ?? '',
        $r['email'] ?? '',
        isset($r['total_purchase_orders']) ? (int)$r['total_purchase_orders'] : 0,
        $total,
    ]);
}

fclose($out);
exit;
