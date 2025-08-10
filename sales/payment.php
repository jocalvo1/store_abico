<?php
// Must be the very first thing in the file
if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Record a payment in the payment_records table
 */
function recordPayment($db, $debtId, $paymentMethodId, $amount, $userId, $notes = '', $transactionId = null) {
    // For full payments, we need to create a debt record first
    if ($debtId === null && $transactionId !== null) {
        // Create a debt record for the full payment
        $debtStmt = $db->prepare("INSERT INTO sales_debts 
            (sales_transaction_id, customer_id, total_amount, amount_paid, status, due_date, notes) 
            VALUES (?, (SELECT customer_id FROM sales_transactions WHERE id = ?), 
                   ?, ?, 'paid', DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?)");
        
        $debtStmt->bind_param("iidds", 
            $transactionId,
            $transactionId,
            $amount,
            $amount,  // Full amount paid
            $notes
        );
        
        if (!$debtStmt->execute()) {
            throw new Exception('Failed to create payment record: ' . $db->error);
        }
        
        $debtId = $debtStmt->insert_id;
    }
    
    // Create the payment record
    $stmt = $db->prepare("INSERT INTO payment_records 
        (sales_debt_id, payment_date, payment_method_id, amount, received_by_user_id, notes) 
        VALUES (?, NOW(), ?, ?, ?, ?)");
    
    $stmt->bind_param("iidis", 
        $debtId,
        $paymentMethodId,
        $amount,
        $userId,
        $notes
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to record payment: ' . $db->error);
    }
    
    // Update the amount_paid in sales_debts if this is a debt payment
    if ($debtId !== null) {
        $updateStmt = $db->prepare("UPDATE sales_debts SET 
            amount_paid = amount_paid + ?, 
            status = IF(amount_paid + ? >= total_amount, 'paid', 'partial'),
            updated_at = NOW()
            WHERE id = ?");
        
        $updateStmt->bind_param("ddi", $amount, $amount, $debtId);
        if (!$updateStmt->execute()) {
            throw new Exception('Failed to update debt record: ' . $db->error);
        }
    }
    
    return $db->insert_id;
}

// Function to safely redirect
function safe_redirect($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        echo '<script>window.location.href="' . $url . '";</script>';
    }
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Include required files
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../includes/database.php';

// Check if there's sale data in session
if (!isset($_SESSION['sale_data']) || empty($_SESSION['sale_data']['items'])) {
    $_SESSION['error'] = 'No items in cart. Please add items first.';
    header('Location: add.php');
    exit();
}

// Initialize database connection
$db = getDBConnection();

// Include and initialize SalesController
require_once __DIR__ . '/../controller/sale/SalesController.php';
$salesController = new SalesController();

// Get sale data from session
$saleData = $_SESSION['sale_data'];
$cartItems = $saleData['items'];
$subtotal = $saleData['subtotal'];
$total = $saleData['total'];
$customerId = $saleData['customer_id'];

// Get customer details
$customer = [];
if ($customerId) {
    $result = $db->query("SELECT * FROM customers WHERE id = $customerId");
    if ($result && $result->num_rows > 0) {
        $customer = $result->fetch_assoc();
    }
}

// Get payment methods from database
$paymentMethods = [];
$result = $db->query("SELECT * FROM payment_methods ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $paymentMethods[] = $row;
    }
    $result->free();
}

// If no payment methods found, use cash as default
if (empty($paymentMethods)) {
    $paymentMethods = [
        ['id' => 1, 'name' => 'Cash', 'description' => 'Pay with cash']
    ];
}

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    ob_start(); // Start output buffering to prevent header errors
    $db = getDBConnection();
    $db->begin_transaction();
    
    try {
        $paymentMethodId = (int)$_POST['payment_method'];
        $amountPaid = isset($_POST['pay_later']) ? 0 : (float)$_POST['amount_paid'];
        $isDebt = isset($_POST['pay_later']) ? 1 : 0;
        $status = 'paid';
        $change = $amountPaid - $total;
        // Get and validate user ID
        $userId = null;
        if (!empty($_SESSION['user_id'])) {
            // Verify user exists in database
            $userCheck = $db->prepare("SELECT id FROM users WHERE id = ?");
            $userCheck->bind_param("i", $_SESSION['user_id']);
            $userCheck->execute();
            $userResult = $userCheck->get_result();
            
            if ($userResult->num_rows > 0) {
                $userId = $_SESSION['user_id'];
            }
            $userCheck->close();
        }
        
        // If no valid user ID found, get the first available user
        if (!$userId) {
            $userQuery = $db->query("SELECT id FROM users ORDER BY id LIMIT 1");
            
            if ($userQuery && $userQuery->num_rows > 0) {
                $user = $userQuery->fetch_assoc();
                $userId = $user['id'];
            } else {
                // If no users exist, create a default admin user
                $db->query("INSERT INTO users (username, password, role) VALUES ('admin', 'admin123', 'admin')");
                $userId = $db->insert_id ?: 1;
            }
        }
        $notes = !empty($saleData['notes']) ? $saleData['notes'] : null;
        
        // For non-debt transactions, validate payment amount
        if (!$isDebt && $amountPaid < $total) {
            $_SESSION['error'] = 'Insufficient payment amount.';
            safe_redirect('payment.php');
        }
        
        // 1. Validate customer ID if provided
        $customerId = null;
        if (!empty($saleData['customer_id'])) {
            // Verify customer exists
            $checkStmt = $db->prepare("SELECT id FROM customers WHERE id = ?");
            $checkStmt->bind_param("i", $saleData['customer_id']);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                $customerId = $saleData['customer_id'];
            } else if ($isDebt) {
                // If it's a debt transaction, we need a valid customer
                throw new Exception('Invalid customer selected for debt transaction.');
            }
            $checkStmt->close();
        }

        // 2. Insert into sales_transactions - handle NULL customer_id for walk-ins
        $stmt = $db->prepare("INSERT INTO sales_transactions 
            (customer_id, payment_method_id, total_amount, status, created_by_user_id, notes) 
            VALUES (?, ?, ?, ?, ?, ?)");
            
        // Bind parameters with proper NULL handling
        $stmt->bind_param("iidsis", 
            $customerId, 
            $paymentMethodId, 
            $total, 
            $status, 
            $userId,
            $notes
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create sales transaction: ' . $db->error);
        }
        
        $transactionId = $db->insert_id;
        
        // 2. Insert sales_transaction_items
        $stmt = $db->prepare("INSERT INTO sales_transaction_items 
            (sales_transaction_id, item_id, quantity, unit_price, total_price, notes) 
            VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($saleData['items'] as $item) {
            $itemId = $item['id'];
            $quantity = $item['quantity'];
            $unitPrice = $item['price'];
            $totalPrice = $quantity * $unitPrice;
            $itemNotes = null;
            
            $stmt->bind_param("iiidss", 
                $transactionId, 
                $itemId, 
                $quantity, 
                $unitPrice,
                $totalPrice,
                $itemNotes
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to add items to transaction: ' . $db->error);
            }
            
            // Update inventory - using current_stock instead of quantity
            $updateStmt = $db->prepare("UPDATE items SET current_stock = current_stock - ? WHERE id = ?");
            $updateStmt->bind_param("ii", $quantity, $itemId);
            if (!$updateStmt->execute()) {
                throw new Exception('Failed to update inventory: ' . $db->error);
            }
            $updateStmt->close();
        }
        
        // 3. Determine transaction status
        if ($isDebt) {
            $status = 'debt';
        } elseif ($amountPaid < $total) {
            $status = 'partial';
        } else {
            $status = 'paid';
        }
        
        // Update transaction status
        $updateStatus = $db->prepare("UPDATE sales_transactions SET status = ? WHERE id = ?");
        $updateStatus->bind_param("si", $status, $transactionId);
        if (!$updateStatus->execute()) {
            throw new Exception('Failed to update transaction status: ' . $db->error);
        }
        
        // 4. Handle payment records for all scenarios
        if ($isDebt || $amountPaid < $total) {
            // This is a debt or partial payment
            $debtStatus = $isDebt ? 'unpaid' : 'partial';
            
            // Insert into sales_debts
            $stmt = $db->prepare("INSERT INTO sales_debts 
                (sales_transaction_id, customer_id, total_amount, amount_paid, status, due_date, notes) 
                VALUES (?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?)");
            
            $stmt->bind_param("iiddss", 
                $transactionId, 
                $customerId,
                $total,
                $amountPaid,
                $debtStatus,
                $notes
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to record debt: ' . $db->error);
            }
            
            $debtId = $db->insert_id;
            
            // Record payment if any amount was paid
            if ($amountPaid > 0) {
                recordPayment($db, $debtId, $paymentMethodId, $amountPaid, $userId, 'Initial payment');
            }
        } else {
            // This is a full payment - record it directly
            recordPayment($db, null, $paymentMethodId, $total, $userId, 'Full payment', $transactionId);
        }
        
        $db->commit();
        
        // Clear cart and prepare success message
        unset($_SESSION['sale_data']);
        $successMessage = $isDebt ? 'Sale recorded as debt successfully!' : 
                         ($status === 'partial' ? 'Partial payment processed successfully!' : 'Payment processed successfully!');
        
        // Redirect with success message
        $_SESSION['success'] = $successMessage;
        safe_redirect('index.php');
        
    } catch (Exception $e) {
        if (isset($db)) {
            $db->rollback();
        }
        $_SESSION['error'] = 'Payment processing failed: ' . $e->getMessage();
        safe_redirect('payment.php');
    }
}
?>

<div class="container-fluid py-4">
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="post" id="paymentForm">
        <div class="row g-4">
            <!-- Left Column: Receipt -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">Order Receipt</h6>
                        <span class="text-muted">#<?php echo strtoupper(uniqid('INV-')); ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <?php if (!empty($customer)): ?>
                                        <h6 class="mb-1">Customer: <?php echo htmlspecialchars($customer['name']); ?></h6>
                                        <?php if (!empty($customer['contact'])): ?>
                                            <small class="text-muted">Contact: <?php echo htmlspecialchars($customer['contact']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <h6 class="mb-1">Walk-in Customer</h6>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($saleData['notes'])): ?>
                                    <div class="text-muted ms-3" style="max-width: 50%;">
                                        <small><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($saleData['notes'])); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cartItems as $item): ?>
                                        <tr class="receipt-item">
                                            <td>
                                                <div class="fw-medium"><?php echo htmlspecialchars($item['name']); ?></div>
                                                <small class="text-muted">₱<?php echo number_format($item['price'], 2); ?> per <?php echo htmlspecialchars($item['unit']); ?></small>
                                            </td>
                                            <td class="text-end align-middle"><?php echo $item['quantity']; ?></td>
                                            <td class="text-end align-middle">₱<?php echo number_format($item['price'], 2); ?></td>
                                            <td class="text-end align-middle fw-medium">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="3" class="text-end">Subtotal:</th>
                                        <th class="text-end">₱<span id="subtotalAmount"><?php echo number_format($subtotal, 2); ?></span></th>
                                    </tr>
                                    <tr class="fw-bold">
                                        <th colspan="3" class="text-end">Total Amount:</th>
                                        <th class="text-end">₱<span id="totalAmount"><?php echo number_format($total, 2); ?></span></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Payment -->
            <div class="col-lg-5">
                <!-- Payment Method -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 font-weight-bold">Payment Method</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($paymentMethods as $index => $method): ?>
                                <div class="col-md-6">
                                    <div class="payment-method-card <?php echo $index === 0 ? 'active' : ''; ?>" 
                                         onclick="selectPaymentMethod(<?php echo $method['id']; ?>, this)">
                                        <input type="radio" class="form-check-input" name="payment_method" 
                                               id="method<?php echo $method['id']; ?>" 
                                               value="<?php echo $method['id']; ?>" 
                                               <?php echo $index === 0 ? 'checked' : ''; ?>>
                                        <label class="form-check-label ms-2 fw-medium" for="method<?php echo $method['id']; ?>">
                                            <?php echo htmlspecialchars($method['name']); ?>
                                        </label>
                                        <?php if (!empty($method['description'])): ?>
                                            <div class="small text-muted mt-1"><?php echo htmlspecialchars($method['description']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Details -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 font-weight-bold">Payment Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="amount_paid" class="form-label">Amount Received <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg mb-2">
                                <span class="input-group-text">₱</span>
                                <input type="number" class="form-control" id="amount_paid" name="amount_paid" 
                                       step="0.01" min="0" value="0.00" 
                                       oninput="updatePaymentSummary()" required>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="pay_later" name="pay_later" value="1">
                                <label class="form-check-label text-danger fw-bold" for="pay_later">
                                    Pay Later (Record as Debt)
                                </label>
                            </div>
                        </div>
                        
                        <div class="bg-light p-3 rounded mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Amount:</span>
                                <strong>₱<span id="displayTotal"><?php echo number_format($total, 2); ?></span></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Amount Tendered:</span>
                                <strong>₱<span id="displayTendered">0.00</span></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2" id="remainingBalanceRow" style="display: none;">
                                <span>Remaining Balance:</span>
                                <strong class="text-danger">₱<span id="remainingBalance">0.00</span></strong>
                            </div>
                            <div class="d-flex justify-content-between fw-bold">
                                <span>Change:</span>
                                <span class="text-success">₱<span id="displayChange">0.00</span></span>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="process_payment" class="btn btn-primary btn-lg" id="processPaymentBtn">
                                <i class="fas fa-credit-card me-2"></i> Process Payment
                            </button>
                            <a href="add.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back to Cart
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
.payment-method-card {
    cursor: pointer;
    transition: all 0.3s;
    border: 2px solid #dee2e6;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
}
.payment-method-card:hover {
    border-color: #0d6efd;
    background-color: #f8f9fa;
}
.payment-method-card.active {
    border-color: #0d6efd;
    background-color: #e7f1ff;
}
.receipt-item {
    border-bottom: 1px solid #eee;
    padding: 0.75rem 0;
}
.receipt-item:last-child {
    border-bottom: none;
}
</style>

<script>
function selectPaymentMethod(methodId, element) {
    // Remove active class from all payment method cards
    document.querySelectorAll('.payment-method-card').forEach(card => {
        card.classList.remove('active');
    });
    
    // Add active class to clicked card
    element.classList.add('active');
    
    // Update the radio button
    document.querySelector(`#method${methodId}`).checked = true;
    updatePaymentSummary();
}

function updatePaymentSummary() {
    const amountInput = document.getElementById('amount_paid');
    // Convert to number and handle decimal places
    const amountPaid = parseFloat(amountInput.value) || 0;
    const total = parseFloat(<?php echo $total; ?>);
    
    // Use toFixed to handle floating point precision issues
    const remaining = Math.max(0, (total - amountPaid).toFixed(2));
    const change = Math.max(0, (amountPaid - total).toFixed(2));
    
    const payLaterCheckbox = document.getElementById('pay_later');
    const payLater = payLaterCheckbox.checked;
    const processBtn = document.getElementById('processPaymentBtn');
    const remainingBalanceRow = document.getElementById('remainingBalanceRow');
    
    // Always show 2 decimal places for currency
    document.getElementById('displayTendered').textContent = amountPaid.toFixed(2);
    
    if (payLater) {
        // When Pay Later is checked
        processBtn.innerHTML = '<i class="fas fa-file-invoice-dollar me-2"></i> Record as Debt';
        processBtn.classList.remove('btn-primary', 'btn-warning');
        processBtn.classList.add('btn-danger');
        processBtn.disabled = false;
        
        // Disable and clear amount field
        amountInput.disabled = true;
        amountInput.value = '0.00';
        
        // Update display
        document.getElementById('displayTendered').textContent = '0.00';
        document.getElementById('remainingBalance').textContent = total.toFixed(2);
        document.getElementById('displayChange').textContent = '0.00';
        remainingBalanceRow.style.display = 'flex';
    } else {
        // When Pay Later is unchecked
        const isFullPayment = amountPaid >= total;
        const isPartialPayment = amountPaid > 0 && amountPaid < total;
        
        processBtn.innerHTML = `<i class="fas fa-credit-card me-2"></i>${
            isFullPayment ? 'Process Payment' : 'Record Partial Payment'
        }`;
        
        // Update button styling
        processBtn.classList.remove('btn-danger', 'btn-primary', 'btn-warning');
        processBtn.classList.add(isFullPayment ? 'btn-primary' : 'btn-warning');
        
        amountInput.disabled = false;
        
        // Handle remaining balance display
        if (isPartialPayment) {
            remainingBalanceRow.style.display = 'flex';
            document.getElementById('remainingBalance').textContent = remaining;
        } else {
            remainingBalanceRow.style.display = isFullPayment ? 'none' : 'flex';
        }
        
        // Update change/remaining
        if (isFullPayment) {
            document.getElementById('displayChange').textContent = change;
        } else {
            document.getElementById('displayChange').textContent = '0.00';
            if (amountPaid > 0) {
                document.getElementById('remainingBalance').textContent = remaining;
            }
        }
        
        // Enable button if any amount is entered
        processBtn.disabled = amountPaid <= 0;
    }
}

// Add event listener for the Pay Later checkbox
document.getElementById('pay_later').addEventListener('change', function() {
    // Update the payment summary immediately when checkbox state changes
    updatePaymentSummary();
    
    // If unchecking, focus the amount field for better UX
    if (!this.checked) {
        document.getElementById('amount_paid').focus();
    }
});

// Update button state when amount changes
document.getElementById('amount_paid').addEventListener('input', function() {
    // Only update if Pay Later is not checked
    if (!document.getElementById('pay_later').checked) {
        updatePaymentSummary();
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize amount paid field with total
    const amountPaid = document.getElementById('amount_paid');
    amountPaid.value = parseFloat(amount_paid.value).toFixed(2);
    
    // Add event listener for amount paid input
    amountPaid.addEventListener('input', updatePaymentSummary);
    
    // Initial update
    updatePaymentSummary();
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
