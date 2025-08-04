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
            <a href="edit.php?id=<?php echo $customer_id; ?>" class="btn btn-sm btn-outline-secondary me-2">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Customers
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Customer Information</h6>
                </div>
                <div class="card-body p-0">
                    <div class="row g-4 p-3">
                        <!-- Contact & Address Column -->
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <h5 class="mb-3 border-bottom pb-2"><i class="fas fa-address-card me-2"></i>Contact Information</h5>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0 me-3 text-primary">
                                                <i class="fas fa-phone fa-lg"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 text-muted small">Phone</h6>
                                                <p class="mb-0">
                                                    <?php if (!empty($customer['contact'])): ?>
                                                        <a href="tel:<?php echo htmlspecialchars($customer['contact']); ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($customer['contact']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">No contact information added</span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0 me-3 text-primary">
                                                <i class="fas fa-map-marker-alt fa-lg"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 text-muted small">Address</h6>
                                                <p class="mb-0">
                                                    <?php echo !empty($customer['address']) ? nl2br(htmlspecialchars($customer['address'])) : '<span class="text-muted">No address provided</span>'; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Additional Details Column -->
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <h5 class="mb-3 border-bottom pb-2"><i class="fas fa-info-circle me-2"></i>Additional Details</h5>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0 me-3 text-primary">
                                                <i class="fas fa-calendar-day fa-lg"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 text-muted small">Customer Since</h6>
                                                <p class="mb-0"><?php echo date('F j, Y', strtotime($customer['created_at'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0 me-3 text-primary">
                                                <i class="fas fa-sync-alt fa-lg"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 text-muted small">Last Updated</h6>
                                                <p class="mb-0"><?php echo date('F j, Y h:i A', strtotime($customer['updated_at'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-12">
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
<?php
// Include footer
require_once __DIR__ . '/../../templates/footer.php';
?>
