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

// Check if ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No supplier ID provided.";
    header("Location: index.php");
    exit();
}

$supplier_id = intval($_GET['id']);
$conn = getDBConnection();

// Fetch supplier details
$supplier = null;
$stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Supplier not found.";
    header("Location: index.php");
    exit();
}

$supplier = $result->fetch_assoc();
$stmt->close();

// Initialize purchases array
$purchases = [];
$conn->close();

// Handle delete action
if (isset($_GET['delete']) && $_GET['delete'] == $supplier_id) {
    require_once __DIR__ . '/../../controller/supplier/SupplierController.php';
    $supplierController = new SupplierController();
    
    if ($supplierController->delete($supplier_id)) {
        $_SESSION['success'] = "Supplier deleted successfully!";
        header("Location: index.php");
        exit();
    } else {
        $errors[] = "Error deleting supplier. Please try again.";
    }
}

// Include header
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <?php echo htmlspecialchars($supplier['name']); ?>
            <small class="text-muted">Supplier Details</small>
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="#" class="btn btn-sm btn-outline-danger me-2" data-bs-toggle="modal" data-bs-target="#deleteModal">
                <i class="fas fa-trash"></i> Delete
            </a>
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Suppliers
            </a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Supplier Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Contact Details</h6>
                            <dl class="mb-0">
                                <dt>Contact Person</dt>
                                <dd><?php echo !empty($supplier['contact_person']) ? htmlspecialchars($supplier['contact_person']) : '<span class="text-muted">N/A</span>'; ?></dd>
                                
                                <dt class="mt-2">Phone</dt>
                                <dd>
                                    <?php if (!empty($supplier['contact_number'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($supplier['contact_number']); ?>">
                                            <?php echo htmlspecialchars($supplier['contact_number']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </dd>
                                
                                <dt class="mt-2">Email</dt>
                                <dd>
                                    <?php if (!empty($supplier['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($supplier['email']); ?>">
                                            <?php echo htmlspecialchars($supplier['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <h6>Additional Information</h6>
                            <dl class="mb-0">
                                <dt>Supplier Since</dt>
                                <dd><?php echo date('M d, Y', strtotime($supplier['created_at'])); ?></dd>
                                
                                <dt>Total Purchases</dt>
                                <dd>0 orders</dd>
                                
                                <?php if (!empty($supplier['address'])): ?>
                                    <dt class="mt-2">Address</dt>
                                    <dd><?php echo nl2br(htmlspecialchars($supplier['address'])); ?></dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Purchasing</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Purchase history will be available when the purchases module is implemented.
                    </div>
                    <div class="text-end">
                        <a href="#" class="btn btn-primary" disabled>
                            <i class="fas fa-plus me-1"></i> New Purchase Order
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (!empty($supplier['contact_number'])): ?>
                        <a href="tel:<?php echo htmlspecialchars($supplier['contact_number']); ?>" class="list-group-item list-group-item-action">
                            <i class="fas fa-phone me-2"></i> Call Supplier
                        </a>
                    <?php endif; ?>
                    <a href="#" class="list-group-item list-group-item-action text-muted" disabled>
                        <i class="fas fa-file-invoice-dollar me-2"></i> Create Purchase Order (Coming Soon)
                    </a>
                    <a href="edit.php?id=<?php echo $supplier_id; ?>" class="list-group-item list-group-item-action text-success">
                        <i class="fas fa-edit me-2"></i> Edit Supplier
                    </a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Purchase Statistics</h6>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Total Orders</dt>
                        <dd>0</dd>
                        
                        <dt class="mt-2">Total Spent</dt>
                        <dd>₱0.00</dd>
                            
                        <dt class="mt-2">Average Order</dt>
                        <dd>₱0.00</dd>
                    </dl>
                </div>
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
                <a href="view.php?delete=<?php echo $supplier_id; ?>" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Add confirmation for delete action
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.delete-supplier');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this supplier? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
});
</script>
    
<?php 
// Include footer
require_once __DIR__ . '/../../templates/footer.php'; 
?>