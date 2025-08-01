<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Include the dashboard header
require_once __DIR__ . '/templates/header.php';
// Include database connection
require_once __DIR__ . '/includes/database.php';
?>

<div class="row">
    <!-- Welcome Card -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'User') ?>!</h4>
                <p class="card-text">Here's what's happening with your store today.</p>
                
                <?php if (isset($debugInfo)): ?>
                <div class="alert alert-warning mt-3">
                    <h6>Debug Information:</h6>
                    <p><strong>Database:</strong> <?= $debugInfo['db_connection'] ?></p>
                    <p><strong>Tables in database:</strong></p>
                    <ul>
                        <?php foreach ($debugInfo['tables'] as $table): ?>
                            <li><?= htmlspecialchars($table) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p><strong>Session Data:</strong></p>
                    <pre><?= htmlspecialchars(print_r($debugInfo['session'], true)) ?></pre>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="col-md-4 mb-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-uppercase text-white-50 mb-1">Total Products</h6>
                        <h2 class="mb-0"><?= number_format($totalProducts ?? 0) ?></h2>
                    </div>
                    <div class="icon-circle">
                        <i class="fas fa-box fa-2x"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-primary bg-opacity-25 border-0">
                <a href="#" class="text-white text-decoration-none">View all products <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-uppercase text-white-50 mb-1">Total Sales</h6>
                        <h2 class="mb-0"><?= number_format($totalSales ?? 0) ?></h2>
                    </div>
                    <div class="icon-circle">
                        <i class="fas fa-shopping-cart fa-2x"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-success bg-opacity-25 border-0">
                <a href="#" class="text-white text-decoration-none">View all sales <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-uppercase text-white-50 mb-1">Total Customers</h6>
                        <h2 class="mb-0"><?= number_format($totalCustomers ?? 0) ?></h2>
                    </div>
                    <div class="icon-circle">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-info bg-opacity-25 border-0">
                <a href="#" class="text-white text-decoration-none">View all customers <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
    
    <!-- Recent Sales -->
    <div class="col-12 col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Sales</h5>
                <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentSales)): ?>
                                <?php foreach ($recentSales as $sale): ?>
                                <tr>
                                    <td>#<?= $sale['id'] ?></td>
                                    <td>Customer <?= $sale['customer_id'] ?? 'N/A' ?></td>
                                    <td><?= date('M d, Y', strtotime($sale['sale_date'])) ?></td>
                                    <td>$<?= number_format($sale['total_amount'] ?? 0, 2) ?></td>
                                    <td>
                                        <span class="badge bg-success">Completed</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="#" class="btn btn-sm btn-outline-primary">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="text-muted">No recent sales found.</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="col-12 col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="#" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> New Sale
                    </a>
                    <a href="#" class="btn btn-outline-primary">
                        <i class="fas fa-plus me-2"></i> Add Product
                    </a>
                    <a href="#" class="btn btn-outline-primary">
                        <i class="fas fa-user-plus me-2"></i> Add Customer
                    </a>
                    <a href="#" class="btn btn-outline-primary">
                        <i class="fas fa-file-invoice me-2"></i> Create Invoice
                    </a>
                </div>
                
                <hr>
                
                <h6 class="mb-3">Quick Stats</h6>
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                        <span>Low Stock Items</span>
                        <span class="badge bg-danger rounded-pill">3</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                        <span>Pending Orders</span>
                        <span class="badge bg-warning text-dark rounded-pill">5</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                        <span>New Customers (Today)</span>
                        <span class="badge bg-success rounded-pill">2</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include the dashboard footer
require_once __DIR__ . '/templates/footer.php';
?>