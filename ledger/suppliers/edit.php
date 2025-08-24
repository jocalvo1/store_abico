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

$errors = [];
$supplier = null;

// Fetch the supplier
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $supplier['name'] = trim($_POST['name'] ?? '');
    $supplier['contact_person'] = trim($_POST['contact_person'] ?? '');
    $supplier['contact_number'] = trim($_POST['phone'] ?? '');
    $supplier['email'] = trim($_POST['email'] ?? '');
    $supplier['address'] = trim($_POST['address'] ?? '');
    
    // Validate
    if (empty($supplier['name'])) {
        $errors[] = "Supplier name is required.";
    }
    
    if (!empty($supplier['email']) && !filter_var($supplier['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    // If no errors, update the database using the controller
    if (empty($errors)) {
        require_once __DIR__ . '/../../controller/supplier/supplierController.php';
        $supplierController = new SupplierController();
        
        $data = [
            'name' => $supplier['name'],
            'contact_person' => $supplier['contact_person'],
            'contact_number' => $supplier['contact_number'],
            'email' => $supplier['email'],
            'address' => $supplier['address']
        ];
        
        if ($supplierController->update($supplier_id, $data)) {
            $_SESSION['success'] = "Supplier updated successfully!";
            header("Location: view.php?id=" . $supplier_id);
            exit();
        } else {
            $errors[] = "Error updating supplier. Please try again.";
        }
    }
}

$conn->close();

// Include header
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            Edit Supplier
            <small class="text-muted"><?php echo htmlspecialchars($supplier['name']); ?></small>
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="view.php?id=<?php echo $supplier_id; ?>" class="btn btn-sm btn-outline-secondary me-2">
                <i class="fas fa-arrow-left"></i> Back to Supplier
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
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($supplier['name']); ?>" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_person" class="form-label">Contact Person</label>
                                    <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                           value="<?php echo htmlspecialchars($supplier['contact_person']); ?>"
                                           placeholder="e.g., John Smith">
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
                                               value="<?php echo htmlspecialchars($supplier['contact_number']); ?>"
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
                                               placeholder="e.g., contact@example.com">
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
                            <a href="view.php?id=<?php echo $supplier_id; ?>" class="btn btn-outline-secondary me-md-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Supplier
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Supplier Information</h6>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>ID</dt>
                        <dd><?php echo htmlspecialchars($supplier['id']); ?></dd>
                        
                        <dt class="mt-2">Created</dt>
                        <dd>
                            <div class="d-flex align-items-center">
                                <i class="far fa-calendar-plus me-2 text-muted"></i>
                                <?php echo date('M d, Y h:i A', strtotime($supplier['created_at'])); ?>
                            </div>
                        </dd>
                        
                        <dt class="mt-2">Last Updated</dt>
                        <dd>
                            <div class="d-flex align-items-center">
                                <i class="far fa-clock me-2 text-muted"></i>
                                <?php 
                                if (isset($supplier['updated_at']) && $supplier['updated_at'] !== $supplier['created_at']) {
                                    echo date('M d, Y h:i A', strtotime($supplier['updated_at']));
                                } else {
                                    echo '<span class="text-muted">Not modified yet</span>';
                                }
                                ?>
                            </div>
                        </dd>
                    </dl>
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
// Include footer
require_once __DIR__ . '/../../templates/footer.php'; 
?>