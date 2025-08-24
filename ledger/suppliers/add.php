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
$conn = getDBConnection();

$errors = [];
$supplier = [
    'name' => '',
    'contact_person' => '',
    'phone' => '',
    'email' => '',
    'address' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $supplier['name'] = trim($_POST['name'] ?? '');
    $supplier['contact_person'] = trim($_POST['contact_person'] ?? '');
    $supplier['phone'] = trim($_POST['phone'] ?? '');
    $supplier['email'] = trim($_POST['email'] ?? '');
    $supplier['address'] = trim($_POST['address'] ?? '');
    
    // Validate
    if (empty($supplier['name'])) {
        $errors[] = "Supplier name is required.";
    } else {
        // Check if supplier with this name already exists
        $stmt = $conn->prepare("SELECT id FROM suppliers WHERE name = ?");
        $stmt->bind_param("s", $supplier['name']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "A supplier with this name already exists. Please choose a different name.";
        }
        $stmt->close();
    }
    
    if (!empty($supplier['email']) && !filter_var($supplier['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        require_once __DIR__ . '/../../controller/supplier/supplierController.php';
        $supplierController = new SupplierController();
        
        $data = [
            'name' => $supplier['name'],
            'contact_person' => $supplier['contact_person'],
            'contact_number' => $supplier['phone'],
            'email' => $supplier['email'],
            'address' => $supplier['address']
        ];
        
        if ($supplierController->create($data)) {
            $_SESSION['success'] = "Supplier added successfully!";
            header("Location: index.php");
            exit();
        } else {
            $errors[] = "Error adding supplier. Please try again.";
        }
    }
}

// Include header
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Add New Supplier</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Suppliers
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Supplier Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-building"></i></span>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($supplier['name']); ?>" 
                                               placeholder="Enter supplier name" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_person" class="form-label">Contact Person</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                               value="<?php echo htmlspecialchars($supplier['contact_person']); ?>"
                                               placeholder="Full name of contact person">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($supplier['phone']); ?>"
                                               placeholder="e.g., +63 912 345 6789">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($supplier['email']); ?>"
                                               placeholder="contact@example.com">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                <textarea class="form-control" id="address" name="address" rows="3"
                                    placeholder="Complete address including city and postal code"><?php 
                                    echo htmlspecialchars($supplier['address']); 
                                ?></textarea>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Supplier
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Tips</h6>
                </div>
                <div class="card-body">
                    <h6>About Suppliers</h6>
                    <p class="small">
                        Suppliers are the companies or individuals who provide you with products or materials.
                        Keeping accurate supplier information helps in managing your purchasing process.
                    </p>
                    
                    <h6>Required Information</h6>
                    <ul class="small">
                        <li><strong>Supplier Name:</strong> The legal name of the supplier's business</li>
                        <li><strong>Contact Person:</strong> The main point of contact at the supplier</li>
                        <li><strong>Contact Details:</strong> Phone and email for placing orders and inquiries</li>
                    </ul>
                    
                    <h6>Best Practices</h6>
                    <ul class="small">
                        <li>Keep contact information up to date</li>
                        <li>Note any special terms or conditions</li>
                        <li>Record lead times for reordering</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Back to Top Button -->
<button type="button" id="backToTop" class="btn btn-primary rounded-circle back-to-top" aria-label="Back to top" title="Back to top">
    <i class="fas fa-arrow-up"></i>
    <span class="visually-hidden">Back to top</span>
</button>
<?php 
// Close database connection
if (isset($conn)) {
    $conn->close();
}

// Include footer
require_once __DIR__ . '/../../templates/footer.php'; 
?>