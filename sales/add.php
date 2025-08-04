<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Initialize cart in session if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Include required files
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../includes/database.php';

// Initialize database connection
$db = getDBConnection();

// Include and initialize SalesController
require_once __DIR__ . '/../controller/sale/SalesController.php';
$salesController = new SalesController();

// Get all products
$products = [];
$result = $db->query("SELECT id, name, unit, selling_price as price FROM items ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $result->free();
}

// Get all customers
$customers = [];
$result = $db->query("SELECT id, name, contact FROM customers ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    $result->free();
}

// Initialize variables
$errors = [];
$success = '';
$cart = [];
$subtotal = 0;
$total = 0;
$customerId = '';
$customerName = '';
$paymentMethodId = '';
$notes = '';
$status = 'paid';

// Get all items
$items = [];
$result = $db->query("SELECT * FROM items ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $result->free();
}

// Initialize cart from session
$cart = $_SESSION['cart'] ?? [];

// Handle add to cart action
if (isset($_POST['add_to_cart'])) {
    $itemId = (int)$_POST['item_id'];
    $quantity = (int)$_POST['quantity'];
    
    // Find the product in the products array
    $product = null;
    foreach ($items as $p) {
        if ($p['id'] == $itemId) {
            $product = $p;
            break;
        }
    }
    
    if ($product && $quantity > 0) {
        // Add to cart or update quantity if already exists
        if (isset($_SESSION['cart'][$itemId])) {
            $_SESSION['cart'][$itemId]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$itemId] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'unit' => $product['unit'],
                'price' => $product['selling_price'],
                'quantity' => $quantity
            ];
        }
        
        $success = 'Item added to cart successfully!';
    } else {
        $errors[] = 'Invalid product or quantity';
    }
}

// Handle remove from cart action
if (isset($_GET['remove_from_cart'])) {
    $itemId = (int)$_GET['remove_from_cart'];
    if (isset($_SESSION['cart'][$itemId])) {
        unset($_SESSION['cart'][$itemId]);
        $success = 'Item removed from cart successfully!';
    }
    // Redirect to remove query parameter from URL
    header('Location: add.php');
    exit();
}

// Calculate cart total
$cart = $_SESSION['cart'];
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$total = $subtotal; // For now, no tax or discount

// Handle reset cart
if (isset($_POST['reset_cart'])) {
    $_SESSION['cart'] = [];
    $cart = [];
    $subtotal = 0;
    $total = 0;
    $success = 'Cart has been reset';
}

// Process form submission for adding items to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    // This is handled by the add to cart logic above
    // No need for additional processing here as it's already done
}

// Get customers for dropdown
$customers = [];
$customersResult = $salesController->getAllCustomers();
if ($customersResult && method_exists($customersResult, 'fetch')) {
    while ($customer = $customersResult->fetch()) {
        $customers[] = $customer;
    }
}

// Get payment methods
$paymentMethods = [];
$paymentMethodsResult = $salesController->getPaymentMethods();
if ($paymentMethodsResult && method_exists($paymentMethodsResult, 'fetch')) {
    while ($method = $paymentMethodsResult->fetch()) {
        $paymentMethods[] = $method;
    }
}

// Get items for product lookup
$items = [];
$itemsResult = $salesController->getAllItems();
if ($itemsResult && method_exists($itemsResult, 'fetch')) {
    while ($item = $itemsResult->fetch()) {
        $items[] = $item;
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Sales</a></li>
                    <li class="breadcrumb-item active" aria-current="page">New Sale</li>
                </ol>
            </nav>
            <h4 class="mb-0">New Sale</h4>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form method="post" id="saleForm" action="process_payment.php">
        <div class="row g-4">
            <!-- Left Column: Customer and Products -->
            <div class="col-lg-8">
                <!-- Customer Selection -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 font-weight-bold">Customer Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="customer_id" class="form-label mb-0">Select Customer</label>
                                <a href="../ledger/customers/add.php?return_to=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                                   class="btn btn-sm btn-outline-primary d-flex align-items-center">
                                    <i class="fas fa-plus me-1"></i> Add Customer
                                </a>
                            </div>
                            <select class="form-select" id="customer_id" name="customer_id">
                                <option value="">Walk-in Customer</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" <?php echo ($customerId == $customer['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['name']); ?>
                                        <?php if (!empty($customer['contact'])): ?>
                                            (<?php echo htmlspecialchars($customer['contact']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Products List -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 font-weight-bold">Products</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40%; min-width: 200px;">Product</th>
                                        <th class="text-center" style="width: 10%; min-width: 80px;">Unit</th>
                                        <th class="text-end" style="width: 15%; min-width: 100px;">Price</th>
                                        <th style="width: 25%; min-width: 180px;">Qty</th>
                                        <th class="text-center" style="width: 10%; min-width: 80px;">In Cart</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td class="align-middle">
                                                <div class="d-flex align-items-center">
                                                    <div class="ms-2">
                                                        <div class="fw-medium"><?php echo htmlspecialchars($item['name']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center align-middle">
                                                <span class="badge bg-light text-dark"><?php echo htmlspecialchars($item['unit']); ?></span>
                                            </td>
                                            <td class="text-end align-middle">
                                                <span class="fw-medium">₱<?php echo number_format($item['selling_price'], 2); ?></span>
                                            </td>
                                            <td class="align-middle">
                                                <div class="d-flex align-items-center">
                                                    <form method="post" class="d-flex w-100">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                        <div class="input-group input-group-sm" style="max-width: 140px;">
                                                            <input type="number" 
                                                                   name="quantity" 
                                                                   class="form-control form-control-sm text-center" 
                                                                   value="1" 
                                                                   min="1" 
                                                                   style="width: 60px;">
                                                            <button type="submit" 
                                                                    name="add_to_cart" 
                                                                    class="btn btn-primary btn-sm d-flex align-items-center"
                                                                    formaction="add.php">
                                                                <i class="fas fa-plus me-1"></i> Add
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </td>
                                            <td class="text-center align-middle">
                                                <?php if (isset($cart[$item['id']])): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success">
                                                        <?php echo $cart[$item['id']]['quantity']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted">0</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Order Summary -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm sticky-top" style="top: 100px;">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 font-weight-bold">Order Summary</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cart)): ?>
                            <div class="text-center text-muted py-4" id="emptyCartMsg">
                                <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                                <p class="mb-0">Your cart is empty</p>
                                <small>Add items to get started</small>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th style="width: 45%;">Item</th>
                                            <th style="width: 20%;" class="text-end">Qty</th>
                                            <th style="width: 20%;" class="text-end">Price</th>
                                            <th style="width: 25%;" class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cartItems">
                                        <?php foreach ($cart as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                <td class="text-end"><?php echo $item['quantity']; ?></td>
                                                <td class="text-end">₱<?php echo number_format($item['price'], 2); ?></td>
                                                <td class="text-end">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th colspan="3" class="text-end">Subtotal:</th>
                                            <th class="text-end">₱<?php echo number_format($subtotal, 2); ?></th>
                                        </tr>
                                        <tr>
                                            <th colspan="3" class="text-end">Total:</th>
                                            <th class="text-end">₱<?php echo number_format($total, 2); ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"><?php echo htmlspecialchars($notes); ?></textarea>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <div class="d-flex gap-2">
                                    <button type="submit" name="proceed_to_payment" value="1" class="btn btn-primary flex-grow-1">
                                        <i class="fas fa-credit-card me-1"></i> Proceed to Payment
                                    </button>
                                    <button type="submit" name="reset_cart" value="1" formaction="add.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-trash me-1"></i> Reset Cart
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
    body {
        background-color: #f8f9fa;
    }
    .card {
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
    }
    .card-header {
        border-bottom: 1px solid rgba(0,0,0,.125);
        background-color: #fff;
    }
    .table {
        margin-bottom: 0;
    }
    .table th {
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
        border-top: none;
    }
    .table td {
        vertical-align: middle;
    }
    .sticky-top {
        position: sticky;
        top: 20px;
    }
    .table th {
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .table td {
        vertical-align: middle;
    }
    .form-control-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
</style>

<script>
// Auto-close alerts after 3 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            setTimeout(() => bsAlert.close(), 3000);
                }
            }, 50);
        });
    }, 3000);
    
    // Confirm before resetting cart
    const resetBtn = document.querySelector('button[name="reset_cart"]');
    if (resetBtn) {
        resetBtn.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to reset the cart? This cannot be undone.')) {
                e.preventDefault();
    
    // Update remove buttons state
    function updateRemoveButtons() {
        const rows = document.querySelectorAll('.item-row');
        const removeButtons = document.querySelectorAll('.remove-item');
        
        removeButtons.forEach((btn, index) => {
            btn.disabled = rows.length <= 1;
        });
    }
    
    // Initialize existing rows
    document.querySelectorAll('.item-row').forEach(row => {
        initializeItemRow(row);
    });
    
    // Add new row when clicking the button
    addItemBtn.addEventListener('click', addNewItemRow);
    
    // Add initial row if none exists
    if (document.querySelectorAll('.item-row').length === 0) {
        addNewItemRow();
    }
    
    // Form validation
    document.getElementById('saleForm').addEventListener('submit', function(e) {
        const itemRows = document.querySelectorAll('.item-row');
        let isValid = true;
        
        // Check if at least one item is added
        if (itemRows.length === 0) {
            alert('Please add at least one item to the sale');
            isValid = false;
        }
        
        // Check each item row
        itemRows.forEach(row => {
            const itemSelect = row.querySelector('.item-select');
            const quantityInput = row.querySelector('.quantity');
            const priceInput = row.querySelector('.price');
            
            if (!itemSelect.value) {
                alert('Please select a product for all items');
                isValid = false;
                return;
            }
            
            if (!quantityInput.value || parseFloat(quantityInput.value) <= 0) {
                alert('Please enter a valid quantity for all items');
                isValid = false;
                return;
            }
            
            if (!priceInput.value || parseFloat(priceInput.value) < 0) {
                alert('Please enter a valid price for all items');
                isValid = false;
                return;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
