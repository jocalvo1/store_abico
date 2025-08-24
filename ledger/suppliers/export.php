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

require_once __DIR__ . '/../../controller/supplier/supplierController.php';

// Initialize controller
$supplierController = new SupplierController();

// Read filters
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch data mirroring index.php
if (!empty($searchTerm)) {
    $suppliers = $supplierController->search($searchTerm);
} else {
    $suppliers = $supplierController->getAll();
}

// Normalize to array
$rows = [];
if (is_object($suppliers) && method_exists($suppliers, 'fetch')) {
    while ($row = $suppliers->fetch()) {
        $rows[] = $row;
    }
}

// Prepare CSV headers
$filename = 'suppliers_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

// Output BOM for Excel UTF-8 compatibility
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// CSV header row (matching table columns where applicable)
$headers = [
    '#',
    'Supplier',
    'Contact Person',
    'Phone',
    'Email',
    'Purchase Orders',
    'Total Purchases (PHP)'
];
fputcsv($out, $headers);

// Data rows
$idx = 1;
foreach ($rows as $supplier) {
    $name = $supplier['name'] ?? '';
    $contactPerson = $supplier['contact_person'] ?? '';
    $phone = $supplier['contact_number'] ?? '';
    $email = $supplier['email'] ?? '';
    $poCount = isset($supplier['total_purchase_orders']) ? (int)$supplier['total_purchase_orders'] : 0;
    $totalPurchases = isset($supplier['total_purchases']) ? number_format((float)$supplier['total_purchases'], 2, '.', '') : '0.00';

    fputcsv($out, [
        $idx++,
        $name,
        $contactPerson,
        $phone,
        $email,
        $poCount,
        $totalPurchases,
    ]);
}

fclose($out);
exit;
