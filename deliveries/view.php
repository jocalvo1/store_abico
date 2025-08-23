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

<style>
@media (max-width: 576px) {
  /* Stackable tables for mobile */
  .stack-table thead, .stack-table tfoot { display: none; }
  .stack-table tbody tr { display: block; border-top: 1px solid #e9ecef; }
  .stack-table tbody tr + tr { margin-top: .5rem; }
  .stack-table tbody td { display: grid; grid-template-columns: 1fr 2fr; gap: .5rem; padding: .5rem .75rem; }
  .stack-table tbody td::before { content: attr(data-label); font-weight: 600; color: #6c757d; }
  .stack-table tbody td.text-end { text-align: left !important; }
  /* Header actions centered on mobile */
  .header-actions { gap: .5rem; }
  /* Key-value rows for info cards */
  .kv-row { display: grid; grid-template-columns: 1fr 2fr; gap: .5rem; padding: .375rem 0; border-top: 1px dashed #e9ecef; }
  .kv-row:first-of-type { border-top: 0; }
  .kv-row::before { content: attr(data-label); font-weight: 600; color: #6c757d; }
}
/* Desktop/tablet slight spacing for kv rows */
@media (min-width: 577px) {
  .kv-row { display: flex; align-items: center; gap: .5rem; padding: .125rem 0; }
  .kv-row::before { content: attr(data-label) ':'; font-weight: 600; color: #495057; margin-right: .25rem; }
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row align-items-center justify-content-center justify-content-md-between mb-4">
        <div>
            <h1 class="h3 mb-3">Delivery #<?php echo $delivery_id; ?></h1>
        </div>
        <div class="header-actions d-flex flex-wrap justify-content-center justify-content-md-end">
            <a href="index.php" class="btn btn-sm btn-outline-secondary me-md-2">
                <i class="fas fa-arrow-left"></i> Back to Deliveries
            </a>
            <a href="print.php?id=<?php echo $delivery_id; ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-print"></i> Print
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
                    <div class="col-md-4 mb-3 mb-md-0">
                        <h6>Purchase Order</h6>
                        <div class="kv-row" data-label="PO Number"><?php echo htmlspecialchars($purchase_order['po_number']); ?></div>
                        <div class="kv-row" data-label="Supplier"><?php echo htmlspecialchars($purchase_order['supplier_name']); ?></div>
                        <div class="kv-row" data-label="Order Date"><?php echo date('M d, Y', strtotime($purchase_order['order_date'])); ?></div>
                    </div>
                    <div class="col-md-4 mb-3 mb-md-0">
                        <h6>Delivery Details</h6>
                        <div class="kv-row" data-label="Delivery Date"><?php echo date('M d, Y', strtotime($delivery['delivery_date'])); ?></div>
                        <div class="kv-row" data-label="Status">
                            <span class="badge bg-<?php 
                                echo $delivery['status'] === 'delivered' ? 'success' : 
                                    ($delivery['status'] === 'pending' ? 'warning' : 'secondary'); 
                            ?>">
                                <?php echo ucfirst($delivery['status']); ?>
                            </span>
                        </div>
                        <div class="kv-row" data-label="Delivered By"><?php echo htmlspecialchars($delivery['delivered_by'] ?? '—'); ?></div>
                        <div class="kv-row" data-label="Received By"><?php echo htmlspecialchars($delivery['received_by'] ?? '—'); ?></div>
                    </div>
                    <div class="col-md-4">
                        <h6>Totals</h6>
                        <div class="kv-row" data-label="Items"><?php echo count($delivery_items); ?></div>
                        <div class="kv-row" data-label="Total Amount">₱<?php echo number_format($total, 2); ?></div>
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
                                <table class="table table-hover mb-0 stack-table">
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
                                                <td data-label="Item">
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                                </td>
                                                <td class="align-middle" data-label="Quantity">
                                                    <?php echo number_format($item['quantity']); ?>
                                                </td>
                                                <td class="align-middle" data-label="Unit">
                                                    <?php echo htmlspecialchars($item['unit']); ?>
                                                </td>
                                                <td class="align-middle" data-label="Unit Price">
                                                    ₱<?php echo number_format($item['unit_price'], 2); ?>
                                                </td>
                                                <td class="align-middle text-end" data-label="Total">
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

                <div class="card mb-4">
                    <div class="card-header d-flex align-items-center gap-2">
                        <h6 class="mb-0">Notes</h6>
                        <?php if (!empty($delivery['cancel_reason'])): ?>
                            <span class="badge bg-danger">Cancelled</span>
                        <?php elseif (!empty($delivery['confirm_notes'])): ?>
                            <span class="badge bg-success">Confirmed</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($delivery['cancel_reason'])): ?>
                            <div class="p-3 border rounded">
                                <div style="white-space: pre-wrap; word-break: break-word;">
                                    <?php echo nl2br(htmlspecialchars($delivery['cancel_reason'])); ?>
                                </div>
                            </div>
                        <?php elseif (!empty($delivery['confirm_notes'])): ?>
                            <div class="p-3 bg-light border rounded">
                                <div style="white-space: pre-wrap; word-break: break-word;">
                                    <?php echo nl2br(htmlspecialchars($delivery['confirm_notes'])); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">No notes provided.</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex flex-column flex-md-row align-items-center justify-content-center justify-content-md-between">
                    <div>
                        <p class="text-muted small mb-0">
                            Created on <?php echo date('M d, Y \a\t h:i A', strtotime($delivery['created_at'])); ?>
                            <?php if (!empty($delivery['updated_at']) && $delivery['updated_at'] !== $delivery['created_at']): ?>
                                <br>Last updated on <?php echo date('M d, Y \a\t h:i A', strtotime($delivery['updated_at'])); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="header-actions d-flex flex-wrap justify-content-center justify-content-md-end mt-3 mt-md-0">
                        <?php if ($delivery['status'] === 'pending'): ?>
                            <button type="button" class="btn btn-outline-success me-md-2" data-bs-toggle="modal" data-bs-target="#confirmDeliveryModal">
                                <i class="fas fa-check"></i> Confirm Delivery
                            </button>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelDeliveryModal">
                                <i class="fas fa-times"></i> Cancel Delivery
                            </button>
                        <?php endif; ?>
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
                            <div class="alert alert-success d-flex align-items-start py-2">
                                <i class="fas fa-truck me-2 mt-1"></i>
                                <div>
                                    Please confirm received quantities per item. You can quickly mark all items as fully received.
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mb-2">
                                <button type="button" class="btn btn-sm btn-outline-success js-receive-all"><i class="fas fa-check-double me-1"></i> Mark all as received</button>
                            </div>
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
                                                           class="form-control form-control-sm js-received" 
                                                           value="<?php echo $item['quantity']; ?>" 
                                                           min="0" 
                                                           max="<?php echo $item['quantity']; ?>"
                                                           step="1" 
                                                           inputmode="numeric"
                                                           aria-label="Received quantity for <?php echo htmlspecialchars($item['item_name']); ?>">
                                                     <div class="form-text small text-muted">Max: <?php echo (int)$item['quantity']; ?></div>
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
                                    <div class="invalid-feedback">Please enter who delivered the items.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="receivedBy" class="form-label">Received By</label>
                                    <input type="text" class="form-control" id="receivedBy" name="received_by" placeholder="Name of the person who received the items" required>
                                    <div class="invalid-feedback">Please enter who received the items.</div>
                                </div>
                                <div class="col-12">
                                    <label for="deliveryNotes" class="form-label">Notes (Optional)</label>
                                    <textarea class="form-control" id="deliveryNotes" name="notes" rows="2" placeholder="Any notes or comments about the delivery"></textarea>
                                    <div class="form-text">You can add remarks about discrepancies if any.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i> Close
                            </button>
                            <button type="submit" class="btn btn-success" id="submitConfirmBtn" disabled>
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
                    <form id="cancelDeliveryForm" action="cancel_delivery.php" method="post" novalidate>
                        <div class="modal-body">
                            <div class="alert alert-danger d-flex align-items-start py-2">
                                <i class="fas fa-exclamation-triangle me-2 mt-1"></i>
                                <div>
                                    Cancelling this delivery cannot be undone. Please provide a reason for audit logs.
                                </div>
                            </div>
                            <input type="hidden" name="delivery_id" value="<?php echo $delivery_id; ?>">
                            <div class="mb-2">
                                <label class="form-label">Quick reasons</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary js-reason" data-reason="Supplier delay">Supplier delay</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary js-reason" data-reason="Incorrect items listed">Incorrect items listed</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary js-reason" data-reason="Order duplicated">Order duplicated</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary js-reason" data-reason="Customer request">Customer request</button>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label for="cancellation_reason" class="form-label">Reason for Cancellation</label>
                                <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="3" maxlength="250" placeholder="Describe why this delivery is being cancelled..."></textarea>
                                <div class="d-flex justify-content-between small text-muted mt-1">
                                    <span>Min 5 characters</span>
                                    <span><span id="cancelReasonCount">0</span>/250</span>
                                </div>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="confirmCancelAck">
                                <label class="form-check-label" for="confirmCancelAck">
                                    I understand this action is permanent.
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-danger" id="submitCancelBtn" disabled>
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
<!-- Notes shown inline; modal removed per UX preference -->
</div>
<!-- Back to Top Button -->
<button type="button" id="backToTop" class="btn btn-primary rounded-circle back-to-top" aria-label="Back to top" title="Back to top">
    <i class="fas fa-arrow-up"></i>
    <span class="visually-hidden">Back to top</span>
</button>
<?php 
// Include footer
require_once __DIR__ . '/../templates/footer.php'; 
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Notes are fully visible; no modal logic needed
  // Toast utility
  function ensureToastContainer() {
    let c = document.getElementById('toastContainer');
    if (!c) {
      c = document.createElement('div');
      c.id = 'toastContainer';
      c.style.position = 'fixed';
      c.style.top = '20px';
      c.style.right = '20px';
      c.style.zIndex = '1080';
      document.body.appendChild(c);
    }
    return c;
  }
  function showToast(message, type = 'success') {
    const container = ensureToastContainer();
    const el = document.createElement('div');
    el.className = `toast align-items-center text-white bg-${type} border-0`;
    el.setAttribute('role', 'alert');
    el.setAttribute('aria-live', 'assertive');
    el.setAttribute('aria-atomic', 'true');
    el.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>`;
    container.appendChild(el);
    try {
      const t = new bootstrap.Toast(el, { delay: 3000 });
      t.show();
      el.addEventListener('hidden.bs.toast', () => el.remove());
    } catch (_) {
      // Fallback if Bootstrap JS missing
      setTimeout(() => el.remove(), 3000);
    }
  }

  // Helpers
  function setLoading(btn, loading) {
    if (!btn) return;
    if (loading) {
      btn.dataset.orig = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
    } else {
      if (btn.dataset.orig) btn.innerHTML = btn.dataset.orig;
      btn.disabled = false;
    }
  }

  // Confirm Delivery (AJAX with FormData; fallback to normal submit if non-JSON)
  const confirmForm = document.getElementById('confirmDeliveryForm');
  if (confirmForm) {
    const receivedInputs = Array.from(confirmForm.querySelectorAll('.js-received'));
    const deliveredByEl = document.getElementById('deliveredBy');
    const receivedByEl = document.getElementById('receivedBy');
    const submitConfirmBtn = document.getElementById('submitConfirmBtn');
    const receiveAllBtn = document.querySelector('.js-receive-all');

    function clampInputs() {
      receivedInputs.forEach(inp => {
        const min = inp.hasAttribute('min') ? parseInt(inp.getAttribute('min'), 10) : 0;
        const max = inp.hasAttribute('max') ? parseInt(inp.getAttribute('max'), 10) : Number.MAX_SAFE_INTEGER;
        let val = parseInt(inp.value || '0', 10);
        if (Number.isNaN(val)) val = min;
        if (val < min) val = min;
        if (val > max) val = max;
        if (String(val) !== inp.value) inp.value = String(val);
      });
    }

    function updateConfirmUI() {
      clampInputs();
      const namesOk = !!(deliveredByEl && deliveredByEl.value.trim().length) && !!(receivedByEl && receivedByEl.value.trim().length);
      const qtyOk = receivedInputs.every(inp => {
        const v = parseInt(inp.value || '0', 10);
        const min = inp.hasAttribute('min') ? parseInt(inp.getAttribute('min'), 10) : 0;
        const max = inp.hasAttribute('max') ? parseInt(inp.getAttribute('max'), 10) : Number.MAX_SAFE_INTEGER;
        return !Number.isNaN(v) && v >= min && v <= max;
      });
      if (submitConfirmBtn) submitConfirmBtn.disabled = !(namesOk && qtyOk);
      // invalid feedback toggles
      if (deliveredByEl) deliveredByEl.classList.toggle('is-invalid', !deliveredByEl.value.trim().length);
      if (receivedByEl) receivedByEl.classList.toggle('is-invalid', !receivedByEl.value.trim().length);
    }

    // Receive all button
    if (receiveAllBtn) {
      receiveAllBtn.addEventListener('click', () => {
        receivedInputs.forEach(inp => { if (inp.hasAttribute('max')) inp.value = inp.getAttribute('max'); });
        updateConfirmUI();
      });
    }

    // Live listeners
    receivedInputs.forEach(inp => inp.addEventListener('input', updateConfirmUI));
    if (deliveredByEl) deliveredByEl.addEventListener('input', updateConfirmUI);
    if (receivedByEl) receivedByEl.addEventListener('input', updateConfirmUI);
    // Re-evaluate on modal show
    const confirmModal = document.getElementById('confirmDeliveryModal');
    if (confirmModal) confirmModal.addEventListener('shown.bs.modal', updateConfirmUI);

    confirmForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      const submitBtn = this.querySelector('button[type="submit"]');
      setLoading(submitBtn, true);
      try {
        const fd = new FormData(this);
        const res = await fetch(this.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
          // Fallback to normal submission if server didn't return JSON
          setLoading(submitBtn, false);
          this.submit();
          return;
        }
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.message || 'Failed to confirm delivery');
        showToast(data.message || 'Delivery confirmed', 'success');
        try { bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmDeliveryModal')).hide(); } catch(_){ }
        setTimeout(() => {
          if (data.redirect) window.location.href = data.redirect; else window.location.reload();
        }, 800);
      } catch (err) {
        console.error(err);
        showToast(err.message || 'An error occurred', 'danger');
        setLoading(submitBtn, false);
      }
    });
  }

  // Cancel Delivery (validation + AJAX with FormData; fallback)
  const cancelForm = document.getElementById('cancelDeliveryForm');
  if (cancelForm) {
    const reasonEl = document.getElementById('cancellation_reason');
    const reasonCountEl = document.getElementById('cancelReasonCount');
    const ackEl = document.getElementById('confirmCancelAck');
    const modalEl = document.getElementById('cancelDeliveryModal');
    const submitBtnInit = cancelForm.querySelector('#submitCancelBtn') || cancelForm.querySelector('button[type="submit"]');

    function updateCancelUI() {
      const len = (reasonEl?.value || '').trim().length;
      if (reasonCountEl) reasonCountEl.textContent = String(len);
      const ok = len >= 5 && (!!ackEl && ackEl.checked);
      if (submitBtnInit) submitBtnInit.disabled = !ok;
    }

    // Quick reason buttons
    document.querySelectorAll('.js-reason').forEach(btn => {
      btn.addEventListener('click', () => {
        if (!reasonEl) return;
        const toAdd = btn.getAttribute('data-reason') || '';
        const cur = (reasonEl.value || '').trim();
        reasonEl.value = cur ? (cur + (cur.endsWith('.') ? ' ' : ' ') + toAdd) : toAdd;
        reasonEl.dispatchEvent(new Event('input'));
      });
    });

    // Live validation
    reasonEl && reasonEl.addEventListener('input', updateCancelUI);
    ackEl && ackEl.addEventListener('change', updateCancelUI);
    // When modal opens, recalc
    if (modalEl) {
      modalEl.addEventListener('shown.bs.modal', updateCancelUI);
    }

    cancelForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      const submitBtn = this.querySelector('#submitCancelBtn') || this.querySelector('button[type="submit"]');
      setLoading(submitBtn, true);
      try {
        const fd = new FormData(this);
        const res = await fetch(this.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
          setLoading(submitBtn, false);
          this.submit();
          return;
        }
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.message || 'Failed to cancel delivery');
        showToast(data.message || 'Delivery cancelled', 'success');
        try { bootstrap.Modal.getOrCreateInstance(document.getElementById('cancelDeliveryModal')).hide(); } catch(_){ }
        setTimeout(() => { if (data.redirect) window.location.href = data.redirect; else window.location.reload(); }, 800);
      } catch (err) {
        console.error(err);
        showToast(err.message || 'An error occurred', 'danger');
        setLoading(submitBtn, false);
      }
    });
  }
});
</script>