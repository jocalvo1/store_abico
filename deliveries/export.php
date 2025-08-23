<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../controller/delivery/DeliveryController.php';

$conn = getDBConnection();
$deliveryController = new DeliveryController($conn);

// Read filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$dateFrom = isset($_GET['from']) ? trim($_GET['from']) : '';
$dateTo = isset($_GET['to']) ? trim($_GET['to']) : '';

// Normalize dates (YYYY-MM-DD)
if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) { $dateFrom = ''; }
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) { $dateTo = ''; }

// Fetch data using same logic as index
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

// Prepare CSV output
$filename = 'deliveries_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

// Output BOM for Excel UTF-8 compatibility
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Header row
$headers = [
    'ID',
    'PO Number',
    'Supplier',
    'Delivery Date',
    'Items',
    'Total Value',
    'Status'
];
fputcsv($out, $headers);

// Data rows
foreach ($rows as $r) {
    $id = isset($r['id']) ? $r['id'] : '';
    $poNumber = isset($r['po_number']) ? $r['po_number'] : '';
    $supplier = isset($r['supplier_name']) ? $r['supplier_name'] : '';
    $date = isset($r['delivery_date']) ? date('Y-m-d', strtotime($r['delivery_date'])) : '';
    $items = isset($r['items_list']) ? $r['items_list'] : '';
    $total = isset($r['total_value']) ? number_format((float)$r['total_value'], 2, '.', '') : '0.00';
    $statusText = isset($r['status']) ? $r['status'] : '';

    fputcsv($out, [
        $id,
        $poNumber,
        $supplier,
        $date,
        $items,
        $total,
        ucfirst($statusText)
    ]);
}

fclose($out);

// Close DB
$conn->close();
exit;
