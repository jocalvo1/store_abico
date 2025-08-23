<?php
// deliveries/export.php - Export deliveries as CSV

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../controller/delivery/DeliveryController.php';

$conn = getDBConnection();
$deliveryController = new DeliveryController($conn);

// Filters (same as index.php)
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$dateFrom = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$dateTo = isset($_GET['to']) ? trim((string)$_GET['to']) : '';

if (!empty($search) || !empty($status) || !empty($dateFrom) || !empty($dateTo)) {
    if (method_exists($deliveryController, 'filterDeliveries')) {
        $result = $deliveryController->filterDeliveries($search, $status, $dateFrom, $dateTo);
    } else {
        $result = $deliveryController->search($search);
    }
} else {
    $result = $deliveryController->getAll();
}

$rows = [];
if (is_object($result) && method_exists($result, 'fetch_all')) {
    $rows = $result->fetch_all(MYSQLI_ASSOC);
}

$filename = 'deliveries_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
$out = fopen('php://output', 'w');
// UTF-8 BOM for Excel
fwrite($out, "\xEF\xBB\xBF");

// Header row
fputcsv($out, [
    'PO #',
    'Supplier',
    'Delivery Date',
    'Items',
    'Value',
    'Status',
]);

foreach ($rows as $r) {
    $po = $r['po_number'] ?? '';
    $supplier = $r['supplier_name'] ?? '';
    $date = isset($r['delivery_date']) && $r['delivery_date'] ? date('Y-m-d', strtotime($r['delivery_date'])) : '';
    $items = $r['items_list'] ?? '';
    $value = isset($r['total_value']) && is_numeric($r['total_value']) ? (float)$r['total_value'] : $r['total_value'];
    $st = isset($r['status']) ? ucfirst((string)$r['status']) : '';

    fputcsv($out, [
        $po,
        $supplier,
        $date,
        $items,
        $value,
        $st,
    ]);
}

fclose($out);
$conn->close();
exit;
