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

require_once __DIR__ . '/../includes/database.php';

$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$autoPrint = isset($_GET['print']) && $_GET['print'] == '1';

if ($delivery_id <= 0) {
    $_SESSION['error'] = 'Invalid delivery ID.';
    header('Location: view.php');
    exit();
}

$db = getDBConnection();

// Fetch delivery base
$stmt = $db->prepare('SELECT * FROM deliveries WHERE id = ?');
$stmt->bind_param('i', $delivery_id);
$stmt->execute();
$deliveryRes = $stmt->get_result();
$delivery = $deliveryRes ? $deliveryRes->fetch_assoc() : null;
$stmt->close();

if (!$delivery) {
    $_SESSION['error'] = 'Delivery not found.';
    header('Location: view.php');
    exit();
}

// Fetch purchase order + supplier
$purchase = null;
$stmt = $db->prepare('SELECT p.*, s.name AS supplier_name, s.contact_person, s.contact_number, s.email
                      FROM purchase_orders p
                      JOIN suppliers s ON p.supplier_id = s.id
                      WHERE p.id = ?');
$stmt->bind_param('i', $delivery['purchase_order_id']);
$stmt->execute();
$res = $stmt->get_result();
$purchase = $res ? $res->fetch_assoc() : null;
$stmt->close();

// Fetch items for this delivery with unit price and totals
$items = [];
$total = 0.0;
$stmt = $db->prepare('SELECT di.*, i.name AS item_name, i.unit, poi.unit_price,
                             (di.quantity * poi.unit_price) AS line_total
                      FROM delivery_items di
                      JOIN purchase_order_items poi ON di.purchase_order_item_id = poi.id
                      JOIN items i ON poi.item_id = i.id
                      WHERE di.delivery_id = ?
                      ORDER BY i.name ASC');
$stmt->bind_param('i', $delivery_id);
$stmt->execute();
$ir = $stmt->get_result();
$items = $ir ? $ir->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

foreach ($items as $it) {
    $total += (float)$it['line_total'];
}

$db->close();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Delivery #<?php echo $delivery_id; ?> - Print</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
  @media print {
    .no-print { display: none !important; }
    body { margin: 0; }
    .sheet { box-shadow: none !important; margin: 0 !important; }
  }
  body { background: #f5f7fb; }
  .sheet {
    max-width: 210mm; /* A4 width */
    margin: 16px auto;
    background: #fff;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border-radius: 8px;
    overflow: hidden;
  }
  .sheet .sheet-body { padding: 24px; }
  .title { font-weight: 700; font-size: 20px; }
  .meta dt { color: #6c757d; width: 140px; }
  .meta dd { margin-bottom: .5rem; }
  .table th, .table td { vertical-align: middle; }
  .footer-note { color: #6c757d; font-size: 12px; }
  .hr-dashed { border-top: 1px dashed #dee2e6; opacity: 1; }
</style>
</head>
<body>
  <div class="container-fluid no-print py-3">
    <div class="d-flex justify-content-between align-items-center">
      <div class="fw-semibold">Delivery #<?php echo $delivery_id; ?></div>
      <div class="btn-group">
        <a href="view.php?id=<?php echo $delivery_id; ?>" class="btn btn-outline-secondary">Back</a>
        <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
      </div>
    </div>
  </div>

  <div class="sheet">
    <div class="sheet-body">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
          <div class="title">Delivery Note</div>
          <div class="text-muted">Delivery ID: #<?php echo $delivery_id; ?></div>
        </div>
        <div class="text-end">
          <div><strong>Date:</strong> <?php echo date('M d, Y', strtotime($delivery['delivery_date'])); ?></div>
          <div><span class="badge bg-<?php echo $delivery['status']==='delivered'?'success':($delivery['status']==='pending'?'warning':'secondary'); ?>"><?php echo ucfirst($delivery['status']); ?></span></div>
        </div>
      </div>

      <hr class="hr-dashed">

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <h6 class="mb-2">Purchase Order</h6>
          <dl class="row meta mb-0">
            <dt class="col-sm-4">PO Number</dt>
            <dd class="col-sm-8"><?php echo h($purchase['po_number'] ?? ''); ?></dd>
            <dt class="col-sm-4">Supplier</dt>
            <dd class="col-sm-8"><?php echo h($purchase['supplier_name'] ?? ''); ?></dd>
            <?php if (!empty($purchase['contact_person'])): ?>
            <dt class="col-sm-4">Contact</dt>
            <dd class="col-sm-8"><?php echo h($purchase['contact_person']); ?></dd>
            <?php endif; ?>
          </dl>
        </div>
        <div class="col-md-6">
          <h6 class="mb-2">Delivery Details</h6>
          <dl class="row meta mb-0">
            <dt class="col-sm-4">Delivered By</dt>
            <dd class="col-sm-8"><?php $dbv = trim($delivery['delivered_by'] ?? ''); echo h($dbv !== '' ? $dbv : '~~~'); ?></dd>
            <dt class="col-sm-4">Received By</dt>
            <dd class="col-sm-8"><?php $rbv = trim($delivery['received_by'] ?? ''); echo h($rbv !== '' ? $rbv : '~~~'); ?></dd>
          </dl>
        </div>
      </div>

      <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th style="width: 45%">Item</th>
              <th class="text-end" style="width: 15%">Qty</th>
              <th style="width: 15%">Unit</th>
              <th class="text-end" style="width: 12%">Unit Price</th>
              <th class="text-end" style="width: 13%">Line Total</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($items)): ?>
              <tr><td colspan="5" class="text-center text-muted">No items in this delivery.</td></tr>
            <?php else: foreach ($items as $it): ?>
              <tr>
                <td><?php echo h($it['item_name']); ?></td>
                <td class="text-end"><?php echo number_format((float)$it['quantity'], 2); ?></td>
                <td><?php echo h($it['unit']); ?></td>
                <td class="text-end">₱<?php echo number_format((float)$it['unit_price'], 2); ?></td>
                <td class="text-end">₱<?php echo number_format((float)$it['line_total'], 2); ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
          <tfoot class="table-light">
            <tr>
              <th colspan="4" class="text-end">Total</th>
              <th class="text-end">₱<?php echo number_format($total, 2); ?></th>
            </tr>
          </tfoot>
        </table>
      </div>

      <?php if (!empty($delivery['cancel_reason'])): ?>
        <div class="mb-3">
          <div class="fw-semibold text-danger mb-1">Cancellation Reason</div>
          <div class="border rounded p-2" style="white-space: pre-wrap; word-break: break-word;"><?php echo nl2br(h($delivery['cancel_reason'])); ?></div>
        </div>
      <?php elseif (!empty($delivery['confirm_notes'])): ?>
        <div class="mb-3">
          <div class="fw-semibold text-muted mb-1">Notes</div>
          <div class="border rounded p-2" style="white-space: pre-wrap; word-break: break-word;"><?php echo nl2br(h($delivery['confirm_notes'])); ?></div>
        </div>
      <?php endif; ?>


      <div class="mt-4 footer-note">
        Generated on <?php echo date('M d, Y h:i A'); ?>
      </div>
    </div>
  </div>

  <?php if ($autoPrint): ?>
  <script>window.addEventListener('load', () => setTimeout(() => window.print(), 150));</script>
  <?php endif; ?>
</body>
</html>
