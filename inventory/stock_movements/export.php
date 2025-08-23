<?php
// inventory/stock_movements/export.php - Export stock movements as CSV

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

// Filters (same as index.php)
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$type = isset($_GET['type']) ? trim((string)$_GET['type']) : '';
$dateFrom = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$dateTo = isset($_GET['to']) ? trim((string)$_GET['to']) : '';

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(i.name LIKE ? OR i.unit LIKE ? OR sm.notes LIKE ? OR sm.reference_type LIKE ? OR CAST(sm.reference_id AS CHAR) LIKE ?)';
    $s = '%' . $search . '%';
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $types .= 'sssss';
}
if ($type === 'in' || $type === 'out') {
    $where[] = 'sm.movement_type = ?';
    $params[] = $type; $types .= 's';
}
if ($dateFrom !== '') {
    $where[] = 'DATE(sm.created_at) >= ?';
    $params[] = $dateFrom; $types .= 's';
}
if ($dateTo !== '') {
    $where[] = 'DATE(sm.created_at) <= ?';
    $params[] = $dateTo; $types .= 's';
}

$sql = "SELECT sm.id, sm.item_id, sm.movement_type, sm.quantity, sm.reference_type, sm.reference_id, sm.notes, sm.created_at,
               i.name AS item_name, i.unit AS item_unit
        FROM stock_movements sm
        LEFT JOIN items i ON i.id = sm.item_id";
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY sm.created_at DESC';

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

$filename = 'stock_movements_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
// UTF-8 BOM for Excel
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

// Headers
fputcsv($out, [
    'Date/Time',
    'Item',
    'Unit',
    'Type',
    'Quantity',
    'Reference Type',
    'Reference #',
    'Notes',
]);

foreach ($rows as $mv) {
    $dt = $mv['created_at'] ? date('Y-m-d H:i:s', strtotime($mv['created_at'])) : '';
    $item = $mv['item_name'] ?? ('#' . (string)$mv['item_id']);
    $unit = $mv['item_unit'] ?? '';
    $typeV = $mv['movement_type'] ?? '';
    $qty = is_numeric($mv['quantity']) ? (float)$mv['quantity'] : $mv['quantity'];
    $refType = $mv['reference_type'] ?? '';
    $refId = $mv['reference_id'] ?? '';
    $notes = $mv['notes'] ?? '';

    fputcsv($out, [
        $dt,
        $item,
        $unit,
        $typeV,
        $qty,
        $refType,
        $refId,
        $notes,
    ]);
}

fclose($out);
exit;
