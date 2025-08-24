<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../controller/product/ProductController.php';

$conn = getDBConnection();
$productController = new ProductController();

// Read filters to mirror index.php behavior
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch data
if ($search !== '') {
    $result = $productController->search($search);
} else {
    $result = $productController->getAll();
}

$rows = [];
if (is_object($result) && method_exists($result, 'fetch_all')) {
    $rows = $result->fetch_all(MYSQLI_ASSOC);
}

// Prepare CSV output
$filename = 'products_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

// Output BOM for Excel UTF-8 compatibility
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// CSV header row
$headers = [
    'ID',
    'Name',
    'Description',
    'Category',
    'Unit',
    'Current Stock',
    'Selling Price (PHP)'
];
fputcsv($out, $headers);

// Data rows
foreach ($rows as $r) {
    $id = $r['id'] ?? '';
    $name = $r['name'] ?? '';
    $desc = $r['description'] ?? '';
    $category = $r['category'] ?? '';
    $unit = $r['unit'] ?? '';
    $stock = isset($r['current_stock']) ? (int)$r['current_stock'] : 0;
    $price = isset($r['selling_price']) ? number_format((float)$r['selling_price'], 2, '.', '') : '0.00';

    fputcsv($out, [
        $id,
        $name,
        $desc,
        $category,
        $unit,
        $stock,
        $price
    ]);
}

fclose($out);
$conn->close();
exit;
