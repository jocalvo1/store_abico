<?php
// purchase_orders/export.php - Export purchase orders as CSV

// Session and auth check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../includes/database.php';

$conn = getDBConnection();

// Optional search filter
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$search_condition = '';
$search_params = [];

if ($search !== '') {
    $search_condition = " WHERE p.po_number LIKE ? OR s.name LIKE ? OR s.contact_person LIKE ? OR p.status LIKE ? ";
    $term = "%$search%";
    $search_params = [$term, $term, $term, $term];
}

// Query similar to index.php but with CSV-friendly item details
$sql = "
    SELECT 
        p.id,
        p.po_number,
        p.supplier_id,
        p.status,
        p.total_amount,
        p.created_at,
        s.name AS supplier_name,
        (
            SELECT GROUP_CONCAT(
                CONCAT(pi.quantity, 'x ', i.name)
                ORDER BY i.name SEPARATOR ', '
            )
            FROM purchase_order_items pi
            JOIN items i ON pi.item_id = i.id
            WHERE pi.purchase_order_id = p.id
        ) AS item_details,
        COUNT(DISTINCT pi.id) AS item_count,
        (SELECT COUNT(*) FROM deliveries d WHERE d.purchase_order_id = p.id) AS delivery_count
    FROM purchase_orders p
    JOIN suppliers s ON p.supplier_id = s.id
    LEFT JOIN purchase_order_items pi ON p.id = pi.purchase_order_id
    $search_condition
    GROUP BY p.id, s.name
    ORDER BY p.created_at DESC
";

$stmt = $conn->prepare($sql);
if (!empty($search_params)) {
    $stmt->bind_param(str_repeat('s', count($search_params)), ...$search_params);
}
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// CSV headers
$filename = 'purchase_orders_export_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// UTF-8 BOM for Excel
// phpcs:ignore
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Column headers
fputcsv($out, [
    'Date',
    'Time',
    'PO #',
    'Supplier',
    'Items (Count)',
    'Item Details',
    'Deliveries',
    'Total Amount',
    'Status',
]);

foreach ($rows as $r) {
    $dt = strtotime($r['created_at'] ?? '');
    $date = $dt ? date('Y-m-d', $dt) : '';
    $time = $dt ? date('H:i:s', $dt) : '';
    $po = $r['po_number'] ?? '';
    $supplier = $r['supplier_name'] ?? '';
    $itemCount = isset($r['item_count']) ? (int)$r['item_count'] : 0;
    $details = isset($r['item_details']) ? (string)$r['item_details'] : '';
    // Make sure details are single-line for CSV
    $details = trim(preg_replace('/\s+/', ' ', $details));
    $deliveries = isset($r['delivery_count']) ? (int)$r['delivery_count'] : 0;
    $total = is_numeric($r['total_amount']) ? (float)$r['total_amount'] : $r['total_amount'];
    $status = ucfirst(strtolower(trim($r['status'] ?? '')));

    fputcsv($out, [
        $date,
        $time,
        $po,
        $supplier,
        $itemCount,
        $details,
        $deliveries,
        $total,
        $status,
    ]);
}

fclose($out);
exit;
