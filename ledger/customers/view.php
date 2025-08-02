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
    $_SESSION['error'] = "No customer ID provided.";
    header("Location: index.php");
    exit();
}

$customer_id = intval($_GET['id']);
$conn = getDBConnection();

// Fetch customer details
$customer = null;
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Customer not found.";
    header("Location: index.php");
    exit();
}

$customer = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Include header
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <?php echo htmlspecialchars($customer['name']); ?>
            <small class="text-muted">Customer Details</small>
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="edit.php?id=<?php echo $customer_id; ?>" class="btn btn-sm btn-outline-success me-2">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Customers
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Customer Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Contact Details</h6>
                            <dl class="mb-0">
                                <dt>Contact</dt>
                                <dd><?php echo !empty($customer['contact']) ? htmlspecialchars($customer['contact']) : '<span class="text-muted">N/A</span>'; ?></dd>
                                
                                <dt class="mt-3">Address</dt>
                                <dd><?php echo !empty($customer['address']) ? nl2br(htmlspecialchars($customer['address'])) : '<span class="text-muted">N/A</span>'; ?></dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <h6>Additional Information</h6>
                            <dl class="mb-0">
                                <dt>Customer Since</dt>
                                <dd><?php echo date('F j, Y', strtotime($customer['created_at'])); ?></dd>
                                
                                <dt class="mt-3">Last Updated</dt>
                                <dd><?php echo date('F j, Y h:i A', strtotime($customer['updated_at'])); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Recent Orders</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted">No orders have been placed.</p>
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
                <p>Are you sure you want to delete this customer? This action cannot be undone.</p>
                <p class="mb-0"><strong>Customer:</strong> <?php echo htmlspecialchars($customer['name']); ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="delete.php?id=<?php echo $customer_id; ?>" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../templates/footer.php';
?>
