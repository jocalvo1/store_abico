<?php
// inventory/products/export.php - Export products as CSV

// Start session and restrict to authenticated users
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../controller/product/ProductController.php';

// Initialize controller (ProductController in this codebase does not require explicit DB arg)
$productController = new ProductController();

// Optional search filter
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

// Get data (may return mysqli_result or array depending on controller implementation)
$rows = [];
if ($search !== '') {
    $res = $productController->search($search);
} else {
    // Prefer a large limit if supported; fall back to default
    if (method_exists($productController, 'getAll')) {
        // Some controllers accept a limit param; ignore if not
        try {
            $res = $productController->getAll(1000000);
        } catch (Throwable $e) {
            $res = $productController->getAll();
        }
    } else {
        $res = [];
    }
}

// Normalize to array of associative rows
if (is_array($res)) {
    $rows = $res;
} elseif (is_object($res) && method_exists($res, 'fetch_assoc')) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
}

// Output headers for CSV download
$filename = 'products_export_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Add UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Column headers
fputcsv($out, [
    'ID',
    'Name',
    'Category',
    'Unit',
    'Current Stock',
    'Reorder Level',
    'Selling Price',
    'Description',
]);

// Rows
foreach ($rows as $r) {
    $id = $r['id'] ?? '';
    $name = $r['name'] ?? '';
    $category = $r['category'] ?? '';
    $unit = $r['unit'] ?? '';
    $stock = isset($r['current_stock']) ? (float)$r['current_stock'] : '';
    $reorder = isset($r['reorder_level']) ? (float)$r['reorder_level'] : '';
    $price = isset($r['selling_price']) ? (float)$r['selling_price'] : '';
    $description = $r['description'] ?? '';

    fputcsv($out, [
        $id,
        $name,
        $category,
        $unit,
        $stock,
        $reorder,
        $price,
        $description,
    ]);
}

fclose($out);
exit;
