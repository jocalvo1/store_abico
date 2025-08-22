<?php
// Get the current protocol (http or https)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';

// Get the server name (localhost or IP)
$host = $_SERVER['HTTP_HOST'];

// Get the current script's directory
$current_path = dirname($_SERVER['SCRIPT_NAME']);

// Calculate the base path (project root)
$base_dir = dirname(__DIR__); // Goes up from templates directory
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$base_path = str_replace('\\', '/', $base_dir);
$base_relative = str_replace($doc_root, '', $base_path);

// Build the base URL
$base_url = rtrim($protocol . $host . $base_relative, '/') . '/';

// Function to create clean URLs
function url($path = '') {
    global $base_url;
    $path = ltrim($path, '/');
    
    // If the path already starts with the base URL, return as is
    if (strpos($path, $base_url) === 0) {
        return $path;
    }
    
    // If the path is empty, just return the base URL
    if (empty($path)) {
        return rtrim($base_url, '/') . '/';
    }
    
    // Return the clean URL
    return rtrim($base_url, '/') . '/' . ltrim($path, '/');
}

// Function to check if a menu item should be active
function isActive($paths, $class = 'active') {
    // Get the current request URI and remove the base path
    $base_path = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
    $current_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $current_path = ltrim(str_replace($base_path, '', $current_uri), '/');
    
    if (!is_array($paths)) {
        $paths = [$paths];
    }
    
    // Special case for dashboard (root path)
    if (in_array('', $paths, true)) {
        return $current_path === '' ? $class : '';
    }
    
    foreach ($paths as $path) {
        $path = ltrim($path, '/');
        if ($path === '') continue; // Skip empty paths (handled above)
        
        // Check if the current path starts with the given path
        if (strpos($current_path, $path) === 0) {
            // For exact matches or when the next character is a slash
            if (strlen($current_path) === strlen($path) || 
                $current_path[strlen($path)] === '/') {
                return $class;
            }
        }
    }
    
    return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abico Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/custom.css'); ?>">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="<?= url(); ?>">
            <i class="fas fa-store me-2"></i>
            <span>Abico Store</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a class="nav-link <?= isActive('') ?>" href="<?= url(); ?>">
                        <i class="fas fa-tachometer-alt"></i> DASHBOARD
                    </a>
                </li>

                <!-- Inventory Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= isActive(['inventory', 'products', 'categories', 'stock_movements']) ?>" href="#" id="inventoryDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-boxes me-1"></i> INVENTORY
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?= isActive('inventory/products') ?>" href="<?= url('inventory/products/'); ?>"><i class="fas fa-box me-2"></i>PRODUCTS</a></li>
                        <li><a class="dropdown-item <?= isActive('inventory/stock_movements') ?>" href="<?= url('inventory/stock_movements/'); ?>"><i class="fas fa-exchange-alt me-2"></i>STOCK MOVEMENTS</a></li>
                    </ul>
                </li>

                <!-- Purchase Orders Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= isActive(['purchase_orders', 'purchases', 'deliveries']) ?>" href="#" id="purchaseDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-shopping-cart me-1"></i> PURCHASE ORDERS
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?= isActive('purchase_orders') ?>" href="<?= url('purchase_orders/'); ?>"><i class="fas fa-list me-2"></i>PURCHASE ORDER LIST</a></li>
                        <li><a class="dropdown-item <?= isActive('deliveries') ?>" href="<?= url('deliveries/'); ?>"><i class="fas fa-truck me-2"></i>DELIVERIES</a></li>
                    </ul>
                </li>

                <!-- Sales Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= isActive(['sales', 'transactions']) ?>" href="#" id="salesDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cash-register me-1"></i> SALES
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= url('sales/'); ?>"><i class="fas fa-receipt me-2"></i>SALES TRANSACTION</a></li>
                        <li><a class="dropdown-item" href="<?= url('sales/add.php'); ?>"><i class="fas fa-plus-circle me-2"></i>CREATE NEW SALE</a></li>
                    </ul>
                </li>

                <!-- ledger Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= isActive(['ledger', 'customers', 'suppliers', 'payments', 'checks']) ?>" href="#" id="ledgerDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-book me-1"></i> LEDGER
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= url('ledger/customers'); ?>"><i class="fas fa-users me-2"></i>CUSTOMERS</a></li>
                        <li><a class="dropdown-item" href="<?= url('ledger/suppliers/index.php'); ?>"><i class="fas fa-truck-loading me-2"></i>SUPPLIERS</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= url('ledger/checks'); ?>"><i class="fas fa-exchange-alt me-2"></i>CHECK EXCHANGES</a></li>
                        <li><a class="dropdown-item" href="<?= url('ledger/payments'); ?>"><i class="fas fa-money-bill-wave me-2"></i>PAYMENTS</a></li>
                    </ul>
                </li>
            </ul>

            <!-- Right-aligned items -->
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown user-dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-cog"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu" aria-labelledby="userDropdown">
                        <li>
                            <a class="dropdown-item text-danger logout-item" href="<?= url('includes/logout.php'); ?>">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>

        </div>
    </div>
</nav>
<div class="container-fluid mt-4">
