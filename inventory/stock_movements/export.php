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

// Read filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$dateFrom = isset($_GET['from']) ? trim($_GET['from']) : '';
$dateTo = isset($_GET['to']) ? trim($_GET['to']) : '';

// Build query (mirror index.php, but without LIMIT)
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
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
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

// Prepare CSV output
$filename = 'stock_movements_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

// Output BOM for Excel UTF-8 compatibility
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Header row
$headers = [
    'Date',
    'Time',
    'Item',
    'Unit',
    'Type',
    'Quantity',
    'Reference Type',
    'Reference #',
    'Notes',
];
fputcsv($out, $headers);

// Data rows
foreach ($rows as $mv) {
    $date = '';
    $time = '';
    if (!empty($mv['created_at'])) {
        $ts = strtotime($mv['created_at']);
        $date = date('Y-m-d', $ts);
        $time = date('H:i:s', $ts);
    }
    $item = $mv['item_name'] ?? ('#' . ($mv['item_id'] ?? ''));
    $unit = $mv['item_unit'] ?? '';
    $typeTxt = $mv['movement_type'] ?? '';
    $qty = isset($mv['quantity']) ? (float)$mv['quantity'] : 0;
    $refType = $mv['reference_type'] ?? '';
    $refId = $mv['reference_id'] ?? '';
    $notes = $mv['notes'] ?? '';

    fputcsv($out, [
        $date,
        $time,
        $item,
        $unit,
        strtoupper($typeTxt),
        $qty,
        $refType,
        $refId,
        $notes,
    ]);
}

fclose($out);
$db->close();
exit;
