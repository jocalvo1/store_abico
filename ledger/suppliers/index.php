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
require_once __DIR__ . '/../../controller/supplier/SupplierController.php';

// Initialize SupplierController
$supplierController = new SupplierController();

// Handle search
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get suppliers based on search
if (!empty($searchTerm)) {
    $suppliers = $supplierController->search($searchTerm);
} else {
    $suppliers = $supplierController->getAll();
}

// Get suppliers as array
$suppliersArray = [];
if (is_object($suppliers) && method_exists($suppliers, 'fetch')) {
    while ($supplier = $suppliers->fetch()) {
        $suppliersArray[] = $supplier;
    }
}

// Get row count for numbering
$rowNumber = 1;
?>

<div class="container-fluid px-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0 text-gray-800">Suppliers</h1>
        <div class="d-flex gap-2">
            <div class="input-group input-group-sm" style="width: 200px;">
                <input type="text" class="form-control form-control-sm" placeholder="Search..." id="searchInput">
                <button class="btn btn-outline-secondary btn-sm" type="button" id="searchButton">
                    <i class="fas fa-search"></i>
                </button>
            </div>
            <a href="add.php" class="btn btn-primary btn-sm d-flex align-items-center">
                <i class="fas fa-plus me-1"></i>Add New
            </a>
        </div>
    </div>

    <!-- Search and Add Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Suppliers List</h6>
        </div>
        
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="suppliersTable">
                    <colgroup>
                        <col style="width: 5%;">
                        <col style="width: 20%;">
                        <col style="width: 15%;">
                        <col style="width: 15%;">
                        <col style="width: 25%;">
                        <col style="width: 10%;">
                        <col style="width: 10%;">
                    </colgroup>
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Supplier</th>
                            <th>Contact Person</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Total Purchases</th>
                            <th class="text-nowrap text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($suppliersArray)): ?>
                            <?php foreach ($suppliersArray as $supplier): ?>
                                <tr>
                                    <td><?php echo $rowNumber++; ?></td>
                                    <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                                    <td><?php echo !empty($supplier['contact_person']) ? htmlspecialchars($supplier['contact_person']) : 'N/A'; ?></td>
                                    <td><?php echo !empty($supplier['contact_number']) ? htmlspecialchars($supplier['contact_number']) : 'N/A'; ?></td>
                                    <td><?php echo !empty($supplier['email']) ? htmlspecialchars($supplier['email']) : 'N/A'; ?></td>
                                    <td>₱0.00</td> <!-- Placeholder for total purchases -->
                                    <td class="text-nowrap">
                                        <div class="d-flex gap-1">
                                            <a href="view.php?id=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" title="View">
                                                <i class="fas fa-eye"></i> <span class="d-none d-sm-inline">View</span>
                                            </a>
                                            <a href="edit.php?id=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1" title="Edit">
                                                <i class="fas fa-edit"></i> <span class="d-none d-sm-inline">Edit</span>
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 delete-supplier" data-id="<?php echo $supplier['id']; ?>" title="Delete">
                                                <i class="fas fa-trash"></i> <span class="d-none d-sm-inline">Delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">No suppliers found. <a href="add.php">Add a new supplier</a> to get started.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this supplier? This action cannot be undone.</p>
                <p class="mb-0"><strong>Supplier:</strong> <?php echo htmlspecialchars($supplier['name']); ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="index.php?delete=<?php echo $supplier_id; ?>" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete
                </a>
            </div>
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

    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    
    // Set search term if it exists in URL
    const urlParams = new URLSearchParams(window.location.search);
    const searchTerm = urlParams.get('search');
    if (searchTerm) {
        searchInput.value = searchTerm;
    }

    function performSearch() {
        document.getElementById('searchValue').value = searchInput.value.trim();
        searchForm.submit();
    }

    searchButton.addEventListener('click', performSearch);
    searchInput.addEventListener('keyup', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            performSearch();
        }
    });

    // Delete confirmation
    const deleteButtons = document.querySelectorAll('.delete-supplier');
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    let supplierToDelete = null;

    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            supplierToDelete = this.getAttribute('data-id');
            deleteModal.show();
        });
    });

    document.getElementById('confirmDelete').addEventListener('click', function() {
        if (supplierToDelete) {
            // Make an AJAX call to delete the supplier
            fetch(`delete.php?id=${supplierToDelete}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the row from the table
                    const row = document.querySelector(`.delete-supplier[data-id="${supplierToDelete}"]`).closest('tr');
                    row.remove();
                    // Show success message
                    showAlert('Supplier deleted successfully', 'success');
                } else {
                    showAlert(data.message || 'Error deleting supplier', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while deleting the supplier', 'danger');
            })
            .finally(() => {
                deleteModal.hide();
            });
        }
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