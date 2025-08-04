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

// Include required files
require_once __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/../../controller/customer/customerController.php';

// Initialize CustomerController
$customerController = new customerController();

// Handle search
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get customers based on search
if (!empty($searchTerm)) {
    $customers = $customerController->search($searchTerm);
} else {
    $customers = $customerController->getAll();
}

// Get customers as array
$customersArray = [];
if (is_object($customers) && method_exists($customers, 'fetch_all')) {
    $customersArray = $customers->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-up">
        <div class="row">
            <h1 class="h3 mb-0 mt-2">Customers</h1>
        </div>
        <div class="d-flex align-items-center">
            <form action="" method="get" class="me-3 min-width-300px">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" 
                           class="form-control form-control-sm border-start-0 ps-0" 
                           name="search" 
                           placeholder="Search customers..." 
                           value="<?php echo htmlspecialchars($searchTerm); ?>"
                           aria-label="Search customers">
                    <?php if (!empty($searchTerm)): ?>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-sm btn-outline-danger border-start-0" title="Clear search">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
            <a href="<?php echo dirname($_SERVER['PHP_SELF']) . '/add.php'; ?>" class="btn btn-primary btn-sm d-flex align-items-center">
                <i class="fas fa-plus me-1"></i> Add New
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm" data-aos="fade-up" data-aos-delay="100">
        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Customer List</h6>
                <div class="text-muted small">
                    <?php echo count($customersArray); ?> of <?php echo count($customersArray); ?> total
                </div>
            </div>
        </div>
        
        <div class="card-body p-0">
            <?php if (empty($customersArray)): ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-users fa-3x text-muted"></i>
                    </div>
                    <h5 class="text-muted">No customers found</h5>
                    <p class="text-muted mb-4">
                        Get started by adding a new customer
                    </p>
                    <a href="<?php echo dirname($_SERVER['PHP_SELF']) . '/add.php'; ?>" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Add New Customer
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-uppercase text-muted small fw-bold text-center width-1p">#</th>
                                <th class="text-uppercase text-muted small fw-bold">Customer Name</th>
                                <th class="text-uppercase text-muted small fw-bold">Contact</th>
                                <th class="text-uppercase text-muted small fw-bold">Address</th>
                                <th class="text-uppercase text-muted small fw-bold text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customersArray as $index => $customer): ?>
                                <tr class="border-top" data-aos="fade-up" data-aos-delay="<?php echo ($index % 10) * 50; ?>">
                                    <td class="text-center text-muted"><?php echo $index + 1; ?></td>
                                    <td class="py-3">
                                        <a href="<?php echo dirname($_SERVER['PHP_SELF']) . '/view.php?id=' . $customer['id']; ?>" class="text-decoration-none fw-medium">
                                            <?php echo htmlspecialchars($customer['name']); ?>
                                        </a>
                                    </td>
                                    <td class="text-muted">
                                        <?php echo !empty($customer['contact']) ? htmlspecialchars($customer['contact']) : '<span class="text-muted">N/A</span>'; ?>
                                    </td>
                                    <td class="text-muted">
                                        <?php echo !empty($customer['address']) ? htmlspecialchars($customer['address']) : '<span class="text-muted">N/A</span>'; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-flex gap-1 justify-content-end">
                                            <a href="<?php echo dirname($_SERVER['PHP_SELF']) . '/view.php?id=' . $customer['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary d-flex align-items-center justify-content-center gap-1" 
                                               title="View Details" data-bs-toggle="tooltip" data-bs-placement="top">
                                                <i class="fas fa-eye fa-xs"></i><span>View</span>
                                            </a>
                                            <a href="<?php echo dirname($_SERVER['PHP_SELF']) . '/edit.php?id=' . $customer['id']; ?>" 
                                               class="btn btn-sm btn-outline-secondary d-flex align-items-center justify-content-center gap-1" 
                                               title="Edit Customer" data-bs-toggle="tooltip" data-bs-placement="top">
                                                <i class="fas fa-edit fa-xs"></i><span>Edit</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchForm = document.createElement('form');
    searchForm.method = 'get';
    searchForm.style.display = 'none';
    searchForm.innerHTML = '<input type="hidden" name="search" id="searchValue">';
    document.body.appendChild(searchForm);

    const searchInput = document.querySelector('input[name="search"]');
    
    function performSearch() {
        document.getElementById('searchValue').value = searchInput.value.trim();
        searchForm.submit();
    }

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Function to show alert messages
    function showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        const container = document.querySelector('.container-fluid');
        container.insertBefore(alertDiv, container.firstChild);
        
        // Auto-remove alert after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
});
</script>

<?php 
// Include footer
require_once __DIR__ . '/../../templates/footer.php'; 
?>