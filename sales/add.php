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
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../includes/database.php';

// Initialize database connection
$db = getDBConnection();

// Initialize variables
$errors = [];
$success = '';
$customerId = '';
$customers = [];
$items = [];

// Fetch customers
$result = $db->query("SELECT id, name, contact FROM customers ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    $result->free();
}

// Fetch items with stock > 0
$result = $db->query("SELECT id, name, unit, selling_price, current_stock FROM items WHERE current_stock > 0 ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $result->free();
}

// Define payment methods (could be fetched from DB later)
$paymentMethods = [
    ['id' => 1, 'name' => 'Cash', 'description' => 'Pay with cash upon checkout.'],
    ['id' => 2, 'name' => 'Online Payment', 'description' => 'GCash/Bank transfer.'],
    ['id' => 3, 'name' => 'Check', 'description' => 'Company or personal check.'],
];

$db->close();
?>


<div class="container-fluid py-4" id="saleSection">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
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

    <form method="post" id="saleForm" action="../controller/sale/process.php">
        <input type="hidden" name="cart_json" id="cart_json" value="">
        <div class="row g-4">
            <!-- LEFT COLUMN -->
            <div class="col-lg-8">
                <!-- Customer Info -->
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
                                    <option 
                                        value="<?php echo $customer['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($customer['name']); ?>"
                                        data-contact="<?php echo htmlspecialchars($customer['contact'] ?? ''); ?>"
                                        <?php echo ($customerId == $customer['id']) ? 'selected' : ''; ?>>
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
                        <div class="d-flex align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold">Products</h6>
                            <div class="d-flex gap-2">
                                <input type="text" id="productSearchInput" class="form-control form-control-sm" placeholder="Search products..." style="width: 220px;">
                                <button type="button" id="productSearchBtn" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <style>
                              .products-table thead th { white-space: nowrap; }
                              .products-table col.col-num { width: 56px; }
                              .products-table col.col-product { width: auto; }
                              .products-table col.col-unit { width: 100px; }
                              .products-table col.col-price { width: 120px; }
                              .products-table col.col-qty { width: 220px; }
                              .products-table col.col-in-cart { width: 100px; }
                              .products-table td.col-price, .products-table th.col-price { padding-right: 1rem; }
                              .products-table td.col-qty, .products-table th.col-qty { padding-left: 1rem; padding-right: .5rem; }
                              .products-table td.col-in-cart, .products-table th.col-in-cart { padding-left: .25rem; }
                              .products-table td.col-price { white-space: nowrap; }
                              .products-table td.col-qty .qty-wrap { gap: .5rem; }
                              .products-table td.col-qty input.item-quantity { width: 72px; }
                              /* Ensure input and button are same height */
                              .products-table td.col-qty .item-quantity,
                              .products-table td.col-qty .add-to-cart {
                                height: calc(1.5em + .5rem + 2px); /* matches .btn-sm and .form-control-sm */
                                padding-top: .25rem;
                                padding-bottom: .25rem;
                              }
                              .products-table td.col-qty .add-to-cart i { margin-right: .25rem; }
                              /* Mobile enhancements */
                              @media (max-width: 767.98px) {
                                /* Remove extra reserved widths for hidden columns */
                                .products-table { table-layout: fixed; }
                                .products-table col.col-num,
                                .products-table col.col-unit,
                                .products-table col.col-price,
                                .products-table col.col-in-cart { display: none; }
                                .products-table col.col-product { width: auto; }
                                .products-table col.col-qty { width: 180px; }
                                /* Make qty input and button equal width side-by-side */
                                .products-table td.col-qty .qty-wrap { flex-wrap: nowrap; }
                                .products-table td.col-qty .item-quantity,
                                .products-table td.col-qty .add-to-cart { width: auto; flex: 1 1 0; min-width: 0; }
                                .products-table .product-name { max-width: 60vw; }
                              }
                            </style>
                            <table class="table table-hover mb-0 align-middle products-table">
                                <colgroup>
                                    <col class="col-num">
                                    <col class="col-product">
                                    <col class="col-unit">
                                    <col class="col-price">
                                    <col class="col-qty">
                                    <col class="col-in-cart">
                                </colgroup>
                                <thead class="table-light">
                                    <tr>
                                        <th class="d-none d-md-table-cell">#</th>
                                        <th>Product</th>
                                        <th class="text-center d-none d-md-table-cell">Unit</th>
                                        <th class="text-end col-price d-none d-md-table-cell">Price</th>
                                        <th class="col-qty">Qty</th>
                                        <th class="text-center col-in-cart d-none d-md-table-cell">In Cart</th>
                                </thead>
                                <tbody id="productsTbody"></tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center p-3 border-top">
                            <small class="text-muted" id="productPageInfo">Showing 0–0</small>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="productPrevBtn"><i class="fas fa-chevron-left"></i></button>
                                <div id="productPageNumbers" class="btn-group"></div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="productNextBtn"><i class="fas fa-chevron-right"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div> <!-- /.col-lg-8 -->

            <!-- RIGHT COLUMN -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 font-weight-bold"><i class="fas fa-shopping-cart me-2"></i> Cart</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="text-center text-muted py-5" id="emptyCartMsg">
                            <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                            <p>Your cart is empty</p>
                        </div>
                        <div class="table-responsive" id="cartTableWrapper" style="display: none;">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="cartItems"></tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="4" class="text-end">
                                            Total <span class="badge bg-secondary ms-2" id="cartItemQty">0</span>
                                        </th>
                                        <th class="text-end" id="cartTotal">₱0.00</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="p-3">
                            <div class="text-muted small mb-2" id="cartTotalItemsText">Total items: 0</div>
                            <div class="d-grid gap-2">
                                <button type="button" id="proceedToPayment" class="btn btn-primary"><i class="fas fa-credit-card me-1"></i> Proceed to Payment</button>
                                <button type="button" id="clearCart" class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Remove all items from cart"><i class="fas fa-trash me-1"></i> Clear Cart</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="orderSummarySection" style="display: none;">
            <style>
                .payment-method-card { border: 1px solid #e5e7eb; border-radius: .5rem; padding: .75rem; cursor: pointer; }
                .payment-method-card.active, .payment-method-card:hover { border-color: #0d6efd; background-color: #f8f9ff; }
            </style>
            <div class="row g-4">
                <!-- Left: Summary of Items -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h6 class="m-0 font-weight-bold">Customer Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <div class="mb-1 text-muted small">Name</div>
                                    <div id="summaryCustomerName" class="fw-medium">Walk-in Customer</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-1 text-muted small">Contact</div>
                                    <div id="summaryCustomerContact" class="fw-medium">—</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h6 class="m-0 font-weight-bold">Order Summary</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Item</th>
                                            <th class="text-end">Qty</th>
                                            <th class="text-end">Price</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="summaryItems"></tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th colspan="3" class="text-end">Total</th>
                                            <th class="text-end" id="summaryTotal">₱0.00</th>
                                        </tr>
                                        <tr>
                                            <th colspan="3" class="text-end">Total items</th>
                                            <th class="text-end"><span id="summaryTotalItems">0</span></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Payment -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-3">
                            <h6 class="m-0 font-weight-bold">Payment Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="card border-0 shadow-sm mb-2">
                                    <div class="card-header bg-white py-2">
                                        <h6 class="m-0">Payment Method</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <?php foreach ($paymentMethods as $index => $method): ?>
                                                <div class="col-md-6">
                                                    <div class="payment-method-card <?php echo $index === 0 ? 'active' : ''; ?>" onclick="selectPaymentMethod(<?php echo (int)$method['id']; ?>, this)">
                                                        <input type="radio" class="form-check-input" name="payment_method_id" 
                                                            id="method<?php echo (int)$method['id']; ?>" 
                                                            value="<?php echo (int)$method['id']; ?>" data-name="<?php echo htmlspecialchars($method['name']); ?>"
                                                            <?php echo $index === 0 ? 'checked' : ''; ?>>
                                                        <label class="form-check-label ms-2 fw-medium" for="method<?php echo (int)$method['id']; ?>">
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
                            </div>
                            <div class="mb-3">
                                <label for="amount_paid" class="form-label">Amount Received <span class="text-danger">*</span></label>
                                <div class="input-group input-group-lg mb-2">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" class="form-control" id="amount_paid" name="amount_paid" step="0.01" min="0" value="0.00" oninput="updatePaymentSummary()" required>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="pay_later" name="pay_later" value="1">
                                    <label class="form-check-label text-danger fw-bold" for="pay_later">Pay Later (Record as Debt)</label>
                                </div>
                            </div>

                            <div class="bg-light p-3 rounded mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total Amount:</span>
                                    <strong>₱<span id="displayTotal">0.00</span></strong>
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

                            <div class="mb-3">
                                <label for="notes_input" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes_input" name="notes" rows="2" placeholder="Optional notes (e.g., reference numbers, remarks)"></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="process_payment" class="btn btn-primary btn-lg" id="processPaymentBtn">
                                    <i class="fas fa-credit-card me-2"></i> Process Payment
                                </button>
                                <button type="button" id="backToCart" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Back to Cart
                                </button>
                            </div>
                        </div> <!-- /.card-body -->
                    </div> <!-- /.card -->
                </div> <!-- /.col-lg-4 -->
            </div> <!-- /.row -->
        </div> <!-- /#orderSummarySection -->
    </form>
</div>

<button type="button" id="backToTop" class="btn btn-primary rounded-circle back-to-top" aria-label="Back to top" title="Back to top">
    <i class="fas fa-arrow-up"></i>
    <span class="visually-hidden">Back to top</span>
</button>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Products dataset from server
    const ALL_PRODUCTS = <?php echo json_encode(array_map(function($it){
        return [
            'id' => (int)$it['id'],
            'name' => $it['name'],
            'unit' => $it['unit'],
            'price' => (float)$it['selling_price'],
            'stock' => (int)$it['current_stock']
        ];
    }, $items), JSON_UNESCAPED_UNICODE); ?>;

    const CART_KEY = 'cart';

    function loadCart() {
        try { return JSON.parse(localStorage.getItem(CART_KEY)) || []; } catch { return []; }
    }
    function saveCart(cart) {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
    }

    function removeFromCart(id) {
        const cart = loadCart().filter(i => i.id !== id);
        saveCart(cart);
        renderCart();
    }

    function addToCart(item) {
        const cart = loadCart();
        const existing = cart.find(i => i.id === item.id);
        const maxStock = item.stock;

        const currentQty = existing ? existing.quantity : 0;
        if (currentQty + item.quantity > maxStock) {
            Swal.fire('Stock limit reached', `Only ${maxStock - currentQty} ${item.unit} left to add.`, 'warning');
            return;
        }

        if (existing) {
            existing.quantity += item.quantity;
        } else {
            cart.push(item);
        }
        saveCart(cart);
        renderCart();
    }

    function renderCart() {
        const cart = loadCart();
        const tbody = document.getElementById('cartItems');
        const totalEl = document.getElementById('cartTotal');
        const emptyMsg = document.getElementById('emptyCartMsg');
        const tableWrap = document.getElementById('cartTableWrapper');
        const itemQtyBadge = document.getElementById('cartItemQty');

        if (!cart.length) {
            emptyMsg.style.display = 'block';
            tableWrap.style.display = 'none';
            tbody.innerHTML = '';
            totalEl.textContent = '₱0.00';
            itemQtyBadge.textContent = '0';
            return;
        }

        emptyMsg.style.display = 'none';
        tableWrap.style.display = 'block';
        tbody.innerHTML = '';
        let total = 0, totalItems = 0;

        cart.forEach(item => {
            const lineTotal = item.price * item.quantity;
            total += lineTotal;
            totalItems += item.quantity;
            tbody.innerHTML += `
                <tr>
                    <td>${escapeHtml(item.name)}</td>
                    <td class="text-end">${item.quantity}</td>
                    <td class="text-end">₱${item.price.toFixed(2)}</td>
                    <td class="text-end">₱${lineTotal.toFixed(2)}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-from-cart" data-id="${item.id}" data-bs-toggle="tooltip" title="Remove">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        totalEl.textContent = `₱${total.toFixed(2)}`;

        // Update total items (sum of quantities)
        const totalItemsEl = document.getElementById('cartTotalItemsText');
        if (totalItemsEl) totalItemsEl.textContent = `Total items: ${totalItems}`;
        itemQtyBadge.textContent = totalItems;

        // Update per-item badges
        document.querySelectorAll('.in-cart-count').forEach(badge => {
            const id = parseInt(badge.dataset.id);
            const found = cart.find(i => i.id === id);
            badge.textContent = found ? found.quantity : '0';
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.innerText = str;
        return div.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Bootstrap tooltip initializer
        function initTooltips(root = document) {
            if (window.bootstrap && typeof bootstrap.Tooltip === 'function') {
                const triggers = root.querySelectorAll('[data-bs-toggle="tooltip"]');
                triggers.forEach(el => {
                    try { new bootstrap.Tooltip(el); } catch {}
                });
            }
        }
        // Product list state
        const pageSize = 10;
        let currentPage = 1;
        let filteredProducts = [...ALL_PRODUCTS];

        function renderProductRows(items) {
            const tbody = document.getElementById('productsTbody');
            tbody.innerHTML = '';
            const startIndex = (currentPage - 1) * pageSize;
            const pageItems = items.slice(startIndex, startIndex + pageSize);
            pageItems.forEach((prod, idx) => {
                const safeName = escapeHtml(prod.name);
                const safeUnit = escapeHtml(prod.unit || '');
                tbody.innerHTML += `
                    <tr>
                        <td class="align-middle text-muted row-index d-none d-md-table-cell">${startIndex + idx + 1}</td>
                        <td class="align-middle">
                            <span class="d-inline-block text-truncate product-name" title="${safeName}" data-bs-toggle="tooltip" data-bs-placement="top">${safeName}</span>
                            <div class="d-md-none small mt-1">
                                <div><i class="fas fa-tag me-1"></i>Unit: <span class="fw-medium">${safeUnit}</span></div>
                                <div><i class="fas fa-money-bill-wave me-1"></i>Price: <span class="fw-medium">₱${prod.price.toFixed(2)}</span></div>
                                <div class="d-flex align-items-center gap-2">
                                    <span><i class="fas fa-boxes me-1"></i>Stock: <span class="fw-medium">${prod.stock} ${safeUnit}</span></span>
                                    <span class="badge bg-secondary in-cart-count" data-id="${prod.id}">0</span>
                                </div>
                            </div>
                        </td>
                        <td class="text-center align-middle d-none d-md-table-cell">${safeUnit}</td>
                        <td class="text-end align-middle col-price d-none d-md-table-cell">₱${prod.price.toFixed(2)}</td>
                        <td class="align-middle col-qty">
                            <div class="d-flex align-items-center qty-wrap">
                                <input type="number" class="form-control form-control-sm item-quantity" value="1" min="1" max="${prod.stock}">
                                <button type="button" class="btn btn-sm btn-primary add-to-cart"
                                    data-id="${prod.id}" data-name="${safeName}" data-price="${prod.price}"
                                    data-unit="${safeUnit}" data-stock="${prod.stock}">
                                    <i class="fas fa-plus"></i> Add
                                </button>
                            </div>
                            <small class="text-muted d-none d-md-block mt-1">Stock: ${prod.stock} ${safeUnit}</small>
                        </td>
                        <td class="text-center align-middle col-in-cart d-none d-md-table-cell">
                            <span class="badge bg-secondary in-cart-count" data-id="${prod.id}">0</span>
                        </td>
                    </tr>`;
            });

            // Update per-item badges after rendering
            renderCart();
            // Initialize tooltips on newly added elements
            initTooltips(tbody);
        }

        function updatePaginationUI() {
            const total = filteredProducts.length;
            const totalPages = Math.max(1, Math.ceil(total / pageSize));
            if (currentPage > totalPages) currentPage = totalPages;
            const start = total ? (currentPage - 1) * pageSize + 1 : 0;
            const end = Math.min(total, currentPage * pageSize);
            const info = document.getElementById('productPageInfo');
            if (info) info.textContent = `Showing ${start}–${end} of ${total}`;

            const numbersWrap = document.getElementById('productPageNumbers');
            if (numbersWrap) {
                numbersWrap.innerHTML = '';
                const maxButtons = 5;
                let startBtn = Math.max(1, currentPage - Math.floor(maxButtons/2));
                let endBtn = Math.min(totalPages, startBtn + maxButtons - 1);
                if (endBtn - startBtn + 1 < maxButtons) startBtn = Math.max(1, endBtn - maxButtons + 1);
                for (let p = startBtn; p <= endBtn; p++) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = `btn btn-sm ${p === currentPage ? 'btn-primary' : 'btn-outline-secondary'}`;
                    btn.textContent = p;
                    btn.addEventListener('click', () => { currentPage = p; renderProductRows(filteredProducts); updatePaginationUI(); });
                    numbersWrap.appendChild(btn);
                }
            }

            const prev = document.getElementById('productPrevBtn');
            const next = document.getElementById('productNextBtn');
            if (prev) {
                prev.disabled = currentPage <= 1;
                prev.onclick = () => { if (currentPage > 1) { currentPage--; renderProductRows(filteredProducts); updatePaginationUI(); } };
            }
            if (next) {
                next.disabled = currentPage >= totalPages;
                next.onclick = () => { if (currentPage < totalPages) { currentPage++; renderProductRows(filteredProducts); updatePaginationUI(); } };
            }
        }

        function applySearch() {
            const q = document.getElementById('productSearchInput').value.trim().toLowerCase();
            if (!q) {
                filteredProducts = [...ALL_PRODUCTS];
            } else {
                filteredProducts = ALL_PRODUCTS.filter(p =>
                    p.name.toLowerCase().includes(q) ||
                    (p.unit && p.unit.toLowerCase().includes(q))
                );
            }
            currentPage = 1;
            renderProductRows(filteredProducts);
            updatePaginationUI();
        }

        // Initial render
        filteredProducts = [...ALL_PRODUCTS];
        renderProductRows(filteredProducts);
        updatePaginationUI();
        initTooltips(document);

        // Search events (live, debounced)
        function debounce(fn, wait = 200) {
            let t;
            return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
        }
        const searchBtn = document.getElementById('productSearchBtn');
        if (searchBtn) searchBtn.addEventListener('click', applySearch);
        const searchInput = document.getElementById('productSearchInput');
        if (searchInput) {
            const run = debounce(applySearch, 200);
            searchInput.addEventListener('input', run);
            searchInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') { e.preventDefault(); applySearch(); } });
        }

        // Event delegation for Add to Cart
        const productsTbody = document.getElementById('productsTbody');
        if (productsTbody) {
            productsTbody.addEventListener('click', (e) => {
                const btn = e.target.closest('.add-to-cart');
                if (!btn) return;
                const tr = btn.closest('tr');
                const qtyInput = tr.querySelector('.item-quantity');
                const qty = Math.max(1, parseInt(qtyInput.value || '1', 10));
                const item = {
                    id: parseInt(btn.dataset.id, 10),
                    name: btn.dataset.name,
                    price: parseFloat(btn.dataset.price),
                    unit: btn.dataset.unit,
                    quantity: qty,
                    stock: parseInt(btn.dataset.stock, 10)
                };
                addToCart(item);
                qtyInput.value = 1;
            });
        }

        // Clear cart
        document.getElementById('clearCart').addEventListener('click', () => {
            Swal.fire({
                title: 'Clear cart?',
                icon: 'warning',
                showCancelButton: true
            }).then(res => {
                if (res.isConfirmed) {
                    saveCart([]);
                    renderCart();
                }
            });
        });

        // Remove single item from cart (event delegation)
        const cartTbody = document.getElementById('cartItems');
        if (cartTbody) {
            cartTbody.addEventListener('click', (e) => {
                const btn = e.target.closest('.remove-from-cart');
                if (!btn) return;
                const id = parseInt(btn.dataset.id, 10);
                removeFromCart(id);
            });
        }

        // Global helper to select payment method card
        window.selectPaymentMethod = function(id, el) {
            document.querySelectorAll('.payment-method-card').forEach(c => c.classList.remove('active'));
            if (el) el.classList.add('active');
            const radio = document.getElementById(`method${id}`);
            if (radio) radio.checked = true;
        }

        // Helpers for Order Summary
        function renderSummary() {
            const cart = loadCart();
            // Customer details
            const customerSelect = document.getElementById('customer_id');
            if (customerSelect && customerSelect.selectedIndex >= 0) {
                const opt = customerSelect.options[customerSelect.selectedIndex];
                const name = opt.value ? (opt.dataset.name || opt.textContent.trim()) : 'Walk-in Customer';
                const contact = opt.dataset && opt.dataset.contact ? opt.dataset.contact : '';
                const nameEl = document.getElementById('summaryCustomerName');
                const contactEl = document.getElementById('summaryCustomerContact');
                if (nameEl) nameEl.textContent = name;
                if (contactEl) contactEl.textContent = contact || '—';
            }
            const tbody = document.getElementById('summaryItems');
            const totalEl = document.getElementById('summaryTotal');

            tbody.innerHTML = '';
            let total = 0, totalItems = 0;
            cart.forEach(item => {
                const lineTotal = item.price * item.quantity;
                total += lineTotal;
                totalItems += item.quantity;
                tbody.innerHTML += `
                    <tr>
                        <td>${escapeHtml(item.name)}</td>
                        <td class="text-end">${item.quantity}</td>
                        <td class="text-end">₱${item.price.toFixed(2)}</td>
                        <td class="text-end">₱${lineTotal.toFixed(2)}</td>
                    </tr>
                `;
            });
            totalEl.textContent = `₱${total.toFixed(2)}`;
            const summaryItemsEl = document.getElementById('summaryTotalItems');
            if (summaryItemsEl) summaryItemsEl.textContent = totalItems;
            // Update payment summary panel
            updatePaymentSummary();
        }

        function showOrderSummary() {
            const cart = loadCart();
            if (!cart.length) {
                Swal.fire('Cart is empty', '', 'info');
                return;
            }
            // Fill hidden input for potential submit
            document.getElementById('cart_json').value = JSON.stringify(cart);
            // Toggle views
            document.getElementById('saleSection').style.display = 'none';
            document.getElementById('orderSummarySection').style.display = 'block';
            renderSummary();
        }

        // Proceed to payment toggles to Order Summary
        document.getElementById('proceedToPayment').addEventListener('click', showOrderSummary);

        // Back to cart handler
        document.getElementById('backToCart').addEventListener('click', () => {
            document.getElementById('orderSummarySection').style.display = 'none';
            document.getElementById('saleSection').style.display = 'block';
        });

        // Payment summary calculators
        window.updatePaymentSummary = function() {
            const cart = loadCart();
            const total = cart.reduce((sum, i) => sum + i.price * i.quantity, 0);
            const amountPaidInput = document.getElementById('amount_paid');
            const payLaterChk = document.getElementById('pay_later');
            const tendered = amountPaidInput ? parseFloat(amountPaidInput.value || '0') : 0;
            const payLater = payLaterChk && payLaterChk.checked;

            const displayTotalEl = document.getElementById('displayTotal');
            const displayTenderedEl = document.getElementById('displayTendered');
            const remainingRow = document.getElementById('remainingBalanceRow');
            const remainingEl = document.getElementById('remainingBalance');
            const changeEl = document.getElementById('displayChange');

            if (displayTotalEl) displayTotalEl.textContent = total.toFixed(2);
            if (displayTenderedEl) displayTenderedEl.textContent = payLater ? '0.00' : tendered.toFixed(2);

            let remaining = 0, change = 0;
            if (payLater) {
                remaining = total;
                change = 0;
            } else {
                if (tendered < total) {
                    remaining = total - tendered;
                    change = 0;
                } else {
                    remaining = 0;
                    change = tendered - total;
                }
            }
            if (remainingRow) remainingRow.style.display = (payLater || remaining > 0) ? 'flex' : 'none';
            if (remainingEl) remainingEl.textContent = remaining.toFixed(2);
            if (changeEl) changeEl.textContent = change.toFixed(2);
        }

        const amountPaidInputEl = document.getElementById('amount_paid');
        if (amountPaidInputEl) amountPaidInputEl.addEventListener('input', updatePaymentSummary);
        const payLaterEl = document.getElementById('pay_later');
        if (payLaterEl) payLaterEl.addEventListener('change', updatePaymentSummary);
        const customerSelectEl = document.getElementById('customer_id');
        if (customerSelectEl) {
            customerSelectEl.addEventListener('change', renderSummary);
        }

        // On form submit, validate and finalize values
        const saleForm = document.getElementById('saleForm');
        if (saleForm) {
            saleForm.addEventListener('submit', (e) => {
                const cart = loadCart();
                if (!cart.length) {
                    e.preventDefault();
                    Swal.fire('Cart is empty', '', 'info');
                    return;
                }
                const selectedMethod = document.querySelector('input[name="payment_method_id"]:checked');
                const paymentMethodId = selectedMethod ? selectedMethod.value : '';
                if (!paymentMethodId) {
                    e.preventDefault();
                    Swal.fire('Select payment method', '', 'warning');
                    return;
                }
                const amountPaidInput = document.getElementById('amount_paid');
                let amountPaidVal = amountPaidInput?.value || '0';
                const payLaterChecked = document.getElementById('pay_later')?.checked || false;

                // Compute total to validate partial payments
                const total = cart.reduce((sum, i) => sum + i.price * i.quantity, 0);
                const tenderedNum = parseFloat(amountPaidVal || '0');

                // If pay later, force amount paid to 0
                if (payLaterChecked) {
                    amountPaidVal = '0';
                    if (amountPaidInput) amountPaidInput.value = '0';
                }

                // Enforce customer selection for pay-later or partial payment
                const customerSel = document.getElementById('customer_id');
                const customerVal = customerSel ? customerSel.value : '';
                const isPartial = !payLaterChecked && tenderedNum > 0 && tenderedNum < total;
                const isDebt = payLaterChecked || (!payLaterChecked && tenderedNum === 0 && total > 0);
                if ((isPartial || isDebt) && !customerVal) {
                    e.preventDefault();
                    Swal.fire('Select a customer', 'A customer is required for pay-later or outstanding balance.', 'warning');
                    return;
                }

                document.getElementById('cart_json').value = JSON.stringify(cart);
                // No need to mirror other fields; they submit directly via names
            });
        }

        renderCart();
    });
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
