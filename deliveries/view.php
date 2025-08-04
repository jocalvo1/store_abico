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

// Include required files
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../controller/delivery/DeliveryController.php';

// Get DB connection
$conn = getDBConnection();
$deliveryController = new DeliveryController($conn);

// Initialize variables
$errors = [];
$delivery = null;
$delivery_items = [];
$purchase_order = null;

// Get delivery ID from URL
$delivery_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($delivery_id <= 0) {
    $errors[] = "Invalid delivery ID.";
} else {
    // Get delivery details
    $delivery = $deliveryController->getById($delivery_id);
    
    if (!$delivery) {
        $errors[] = "Delivery not found.";
    } else {
        // Get purchase order details
        $purchase_order = $deliveryController->getPurchaseOrderById($delivery['purchase_order_id']);
        
        // Get delivery items
        $query = "SELECT di.*, i.name as item_name, i.unit, poi.unit_price, 
                         (di.quantity * poi.unit_price) as line_total
                  FROM delivery_items di
                  JOIN purchase_order_items poi ON di.purchase_order_item_id = poi.id
                  JOIN items i ON poi.item_id = i.id
                  WHERE di.delivery_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $delivery_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $delivery_items = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        
        // Calculate total
        $total = array_sum(array_column($delivery_items, 'line_total'));
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Delivery #<?php echo $delivery_id; ?></h1>
            <div class="d-flex align-items-center mt-2">
                <?php 
                    $status_badge = $delivery['status'] === 'delivered' ? 'success' : 
                        ($delivery['status'] === 'pending' ? 'warning' : 'secondary'); 
                ?>
                <span class="badge bg-<?php echo $status_badge; ?> me-2"><?php echo ucfirst($delivery['status']); ?></span>
                <?php if ($delivery['status'] === 'cancelled' && !empty($delivery['cancelled_at'])): ?>
                    <span class="text-muted small">
                        Cancelled on <?php echo date('M d, Y \a\t h:i A', strtotime($delivery['cancelled_at'])); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <a href="edit.php?id=<?php echo $delivery_id; ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Deliveries
            </a>
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
    <?php elseif ($delivery && $purchase_order): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Delivery Information</h6>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <h6>Purchase Order</h6>
                        <p class="mb-1"><strong>PO Number:</strong> <?php echo htmlspecialchars($purchase_order['po_number']); ?></p>
                        <p class="mb-1"><strong>Supplier:</strong> <?php echo htmlspecialchars($purchase_order['supplier_name']); ?></p>
                        <p class="mb-0"><strong>Order Date:</strong> <?php echo date('M d, Y', strtotime($purchase_order['order_date'])); ?></p>
                    </div>
                    <div class="col-md-4">
                        <h6>Delivery Details</h6>
                        <p class="mb-1"><strong>Delivery Date:</strong> <?php echo date('M d, Y', strtotime($delivery['delivery_date'])); ?></p>
                        <p class="mb-1"><strong>Status:</strong> 
                            <span class="badge bg-<?php 
                                echo $delivery['status'] === 'delivered' ? 'success' : 
                                    ($delivery['status'] === 'pending' ? 'warning' : 'secondary'); 
                            ?>">
                                <?php echo ucfirst($delivery['status']); ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-4">
                        <h6>Totals</h6>
                        <p class="mb-1"><strong>Items:</strong> <?php echo count($delivery_items); ?></p>
                        <p class="mb-0"><strong>Total Amount:</strong> ₱<?php echo number_format($total, 2); ?></p>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Delivery Items</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($delivery_items)): ?>
                            <div class="alert alert-warning m-3">
                                <i class="fas fa-exclamation-triangle"></i> No items found in this delivery.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Item</th>
                                            <th style="width: 100px;">Quantity</th>
                                            <th style="width: 100px;">Unit</th>
                                            <th style="width: 100px;">Unit Price</th>
                                            <th style="width: 120px;" class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($delivery_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                                </td>
                                                <td class="align-middle">
                                                    <?php echo number_format($item['quantity']); ?>
                                                </td>
                                                <td class="align-middle">
                                                    <?php echo htmlspecialchars($item['unit']); ?>
                                                </td>
                                                <td class="align-middle">
                                                    ₱<?php echo number_format($item['unit_price'], 2); ?>
                                                </td>
                                                <td class="align-middle text-end">
                                                    ₱<?php echo number_format($item['line_total'], 2); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th colspan="4" class="text-end">Total:</th>
                                            <th class="text-end">₱<?php echo number_format($total, 2); ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-0">
                            Created on <?php echo date('M d, Y \a\t h:i A', strtotime($delivery['created_at'])); ?>
                            <?php if (!empty($delivery['updated_at']) && $delivery['updated_at'] !== $delivery['created_at']): ?>
                                <br>Last updated on <?php echo date('M d, Y \a\t h:i A', strtotime($delivery['updated_at'])); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="btn-group">
                        <?php if ($delivery['status'] === 'pending'): ?>
                            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#confirmDeliveryModal">
                                <i class="fas fa-check"></i> Confirm Delivery
                            </button>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelDeliveryModal">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        <?php endif; ?>
                        <a href="print.php?id=<?php echo $delivery_id; ?>" target="_blank" class="btn btn-outline-secondary">
                            <i class="fas fa-print"></i> Print
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirm Delivery Modal -->
        <div class="modal fade" id="confirmDeliveryModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Confirm Delivery</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="confirmDeliveryForm" method="POST" action="../controller/delivery/DeliveryController.php">
                        <input type="hidden" name="id" value="<?php echo $delivery_id; ?>">
                        <input type="hidden" name="status" value="delivered">
                        
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Item</th>
                                            <th>Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Received</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($delivery_items as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                <td><?php echo $item['quantity'] . ' ' . htmlspecialchars($item['unit']); ?></td>
                                                <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                                <td>
                                                    <input type="number" 
                                                           name="received_quantities[<?php echo $item['id']; ?>]" 
                                                           class="form-control form-control-sm" 
                                                           value="<?php echo $item['quantity']; ?>" 
                                                           min="0" 
                                                           max="<?php echo $item['quantity']; ?>">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="row g-3 mt-2">
                                <div class="col-md-6">
                                    <label for="deliveredBy" class="form-label">Delivered By</label>
                                    <input type="text" class="form-control" id="deliveredBy" name="delivered_by" placeholder="Name of the person who delivered the items" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="receivedBy" class="form-label">Received By</label>
                                    <input type="text" class="form-control" id="receivedBy" name="received_by" placeholder="Name of the person who received the items" required>
                                </div>
                                <div class="col-12">
                                    <label for="deliveryNotes" class="form-label">Notes (Optional)</label>
                                    <textarea class="form-control" id="deliveryNotes" name="notes" rows="2" placeholder="Any notes or comments about the delivery"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i> Close
                            </button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check me-1"></i> Confirm Delivery
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Cancel Delivery Modal -->
        <div class="modal fade" id="cancelDeliveryModal" tabindex="-1" aria-labelledby="cancelDeliveryModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="cancelDeliveryModalLabel">Cancel Delivery</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="cancelDeliveryForm" action="cancel_delivery.php" method="post" onsubmit="document.getElementById('submitCancelBtn').disabled = true; document.getElementById('submitCancelBtn').innerHTML = '<span class=\'spinner-border spinner-border-sm\' role=\'status\' aria-hidden=\'true\'></span> Processing...';">
                        <div class="modal-body">
                            <input type="hidden" name="delivery_id" value="<?php echo $delivery_id; ?>">
                            <div class="mb-3">
                                <label for="cancellation_reason" class="form-label">Reason for Cancellation</label>
                                <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="3" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-danger" id="submitCancelBtn">
                                <span class="btn-text">Confirm Cancellation</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <?php if ($delivery['status'] === 'cancelled' && !empty($delivery['cancellation_reason'])): ?>
        <div class="alert alert-danger">
            <h6><i class="fas fa-ban me-2"></i>Delivery Cancelled</h6>
            <p class="mb-0"><strong>Reason:</strong> <?php echo htmlspecialchars($delivery['cancellation_reason']); ?></p>
            <?php if (!empty($delivery['cancelled_by'])): 
                // Get cancelled by user's name (you might need to fetch this from users table)
                $cancelled_by_user = 'Admin'; // Default or fetch from DB
            ?>
                <p class="mb-0 mt-2"><strong>Cancelled by:</strong> <?php echo htmlspecialchars($cancelled_by_user); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
</div>

<?php 
// Include footer
require_once __DIR__ . '/../templates/footer.php'; 
?>

<script>
// Helper function to show toast messages
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer') || (() => {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.style.position = 'fixed';
        container.style.top = '20px';
        container.style.right = '20px';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        return container;
    })();

    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0 show`;
    toast.role = 'alert';
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
document.addEventListener('DOMContentLoaded', function() {
    // Function to show toast message
    function showToast(type, message) {
        const toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // Remove toast after it's hidden
        toast.addEventListener('hidden.bs.toast', function() {
            toast.remove();
        });
    }

    // Handle cancel delivery form submission
    const cancelForm = document.getElementById('cancelDeliveryForm');
    const cancelModal = cancelForm ? bootstrap.Modal.getInstance(cancelForm.closest('.modal')) : null;
    
    if (cancelForm) {
        cancelForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            if (!submitBtn) return;
            
            // Show loading state
            const spinner = submitBtn.querySelector('.spinner-border');
            const btnText = submitBtn.querySelector('.btn-text');
            
            submitBtn.disabled = true;
            spinner.classList.remove('d-none');
            btnText.textContent = 'Processing...';
            
            try {
                const formData = new FormData(this);
                const response = await fetch('cancel_delivery.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('success', result.message || 'Delivery cancelled successfully');
                    if (cancelModal) {
                        cancelModal.hide();
                    }
                    // Reload the page after a short delay
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast('danger', result.message || 'Failed to cancel delivery');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('danger', 'An error occurred while processing your request');
            } finally {
                // Reset button state
                submitBtn.disabled = false;
                spinner.classList.add('d-none');
                btnText.textContent = 'Confirm Cancellation';
            }
            
            // Show loading state
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            
            try {
                // Get form data
                const formData = new FormData(this);
                const formObject = {
                    id: formData.get('id'),
                    cancellation_reason: formData.get('cancellation_reason')
                };
                
                // Show loading state
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                
                try {
                    // Send request
                    const response = await fetch(this.action, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(formObject)
                    });
                    
                    // Clone the response to read it multiple times if needed
                    const responseClone = response.clone();
                    let result;
                    
                    try {
                        // First try to parse as JSON
                        result = await response.json();
                    } catch (jsonError) {
                        console.error('JSON Parse Error:', jsonError);
                        // If JSON parse fails, try to get the response as text
                        try {
                            const text = await responseClone.text();
                            console.error('Response text:', text);
                            // If the response is HTML, it's likely an error page
                            if (text.trim().startsWith('<!DOCTYPE') || text.includes('<html>')) {
                                throw new Error('Server returned an error page. Please check the server logs.');
                            }
                            throw new Error(text || 'Invalid response from server');
                        } catch (textError) {
                            console.error('Error reading response as text:', textError);
                            throw new Error('Failed to process server response');
                        }
                    }
                    
                    // Handle non-OK responses
                    if (!response.ok) {
                        const errorMsg = result?.message || `Server error: ${response.status} ${response.statusText}`;
                        throw new Error(errorMsg);
                    }
                    
                    // Handle successful response
                    if (result.success) {
                        showToast(result.message || 'Delivery has been cancelled successfully', 'success');
                        
                        // Close the modal
                        if (cancelModal) {
                            cancelModal.hide();
                        }
                        
                        // Redirect or reload
                        if (result.data?.redirect) {
                            window.location.href = result.data.redirect;
                        } else if (result.redirect) {
                            window.location.href = result.redirect;
                        } else {
                            window.location.reload();
                        }
                    } else {
                        throw new Error(result.message || 'Failed to cancel delivery');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast(error.message || 'An error occurred while cancelling the delivery', 'danger');
                    
                    // Reset button state
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            } catch (error) {
                console.error('Error:', error);
                showToast(error.message || 'An error occurred while cancelling the delivery', 'danger');
                
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        });
    }
    
    // Handle confirm delivery form submission
    const confirmForm = document.getElementById('confirmDeliveryForm');
    const confirmModal = confirmForm ? bootstrap.Modal.getInstance(confirmForm.closest('.modal')) : null;
    
    if (confirmForm) {
        confirmForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            if (!submitBtn) return;
            
            // Show loading state
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            
            try {
                // Get form data
                const formData = new FormData(this);
                const formObject = {
                    id: formData.get('id'),
                    status: 'delivered',
                    delivered_by: formData.get('delivered_by'),
                    received_by: formData.get('received_by'),
                    notes: formData.get('notes'),
                    received_quantities: {}
                };
                
                // Add received quantities
                formData.forEach((value, key) => {
                    if (key.startsWith('received_quantities[')) {
                        const itemId = key.match(/\[(\d+)\]/)[1];
                        formObject.received_quantities[itemId] = value;
                    }
                });
                
                // Send request
                const response = await fetch(this.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(formObject)
                });
                
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error('Invalid response from server');
                }
                
                const result = await response.json();
                
                if (!response.ok) {
                    throw new Error(result.message || `HTTP error! status: ${response.status}`);
                }
                
                if (result.success) {
                    // Show success message
                    showToast('Delivery has been confirmed successfully', 'success');
                    
                    // Close the modal
                    if (confirmModal) {
                        confirmModal.hide();
                    }
                    
                    // Redirect to the same page to show updated status
                    if (result.redirect) {
                        window.location.href = result.redirect;
                    } else {
                        window.location.reload();
                    }
                } else {
                    throw new Error(result.message || 'Failed to confirm delivery');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast(error.message || 'An error occurred while confirming the delivery', 'danger');
                
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        });
    }
});
</script>