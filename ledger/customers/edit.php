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
require_once __DIR__ . '/../../includes/database.php';
// Initialize variables
$errors = [];
$success = '';
$customer = [];

// Check if ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No customer ID provided.";
    header("Location: index.php");
    exit();
}

$customer_id = intval($_GET['id']);
$conn = getDBConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validate inputs
    // All fields are optional now
    // If no errors, proceed with database update
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE customers SET name = ?, contact = ?, address = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $contact, $address, $customer_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Customer updated successfully!';
            header("Location: view.php?id=" . $customer_id);
            exit();
        } else {
            $errors[] = 'Error updating customer. Please try again.';
        }
        $stmt->close();
    }
}

// Fetch customer details
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

// Include header after all processing is done (no redirects pending)
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Edit Customer</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="view.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline-secondary me-2">
                <i class="fas fa-arrow-left"></i> Back to View
            </a>
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-list"></i> View All Customers
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Edit Customer Information</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="edit.php?id=<?php echo $customer['id']; ?>" id="customerForm"> 
                        <div class="mb-3">
                            <label for="name" class="form-label">Customer Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   placeholder="Enter customer name"
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($customer['name']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="contact" class="form-label">Contact Information</label>
                            <input type="text" class="form-control" id="contact" name="contact" 
                                   placeholder="e.g., phone number or email"
                                   value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : htmlspecialchars($customer['contact']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"
                                      placeholder="Enter full address"><?php 
                                echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : htmlspecialchars($customer['address']); 
                            ?></textarea>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="view.php?id=<?php echo $customer['id']; ?>" class="btn btn-secondary me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Update Customer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../templates/footer.php';
?>
