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

$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validate inputs
    if (empty($name)) {
        $errors[] = 'Customer name is required';
    }
    
    // If no errors, proceed with database insertion
    if (empty($errors)) {
        $conn = getDBConnection();
        
        // Check if customer with same name already exists
        $checkStmt = $conn->prepare("SELECT id FROM customers WHERE name = ?");
        $checkStmt->bind_param("s", $name);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = 'A customer with this name already exists.';
        } else {
            // Insert new customer
            $stmt = $conn->prepare("INSERT INTO customers (name, contact, address) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $contact, $address);
            
            if ($stmt->execute()) {
                $customerId = $stmt->insert_id;
                $stmt->close();
                $conn->close();
                
                $_SESSION['success'] = 'Customer added successfully!';
                header("Location: view.php?id=" . $customerId);
                exit();
            } else {
                $errors[] = 'Error adding customer. Please try again.';
            }
        }
        
        $checkStmt->close();
        $conn->close();
    }
}
// Include header only after potential redirects are done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Add New Customer</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Customers
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Customer Information</h5>
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

                    <form method="POST" action="" id="customerForm">
                        <div class="mb-3">
                            <label for="name" class="form-label">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   placeholder="Enter customer name"
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="contact" class="form-label">Contact Information</label>
                            <input type="text" class="form-control" id="contact" name="contact" 
                                   placeholder="e.g., phone number or email"
                                   value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>">
                            <div class="form-text">Phone number or email address</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"
                                      placeholder="Enter full address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="index.php" class="btn btn-secondary me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Customer
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
