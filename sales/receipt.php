<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple auth guard
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../includes/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid transaction ID';
    exit;
}

$db = getDBConnection();

// Fetch sale
$sale = null;
$sqlTx = "SELECT st.id, st.transaction_date, st.total_amount, st.status, st.notes,
                 st.customer_id,
                 COALESCE(c.name, 'Walk-in Customer') AS customer_name,
                 pm.name AS payment_method_name
          FROM sales_transactions st
          LEFT JOIN customers c ON c.id = st.customer_id
          LEFT JOIN payment_methods pm ON pm.id = st.payment_method_id
          WHERE st.id = ?";
if ($stmt = $db->prepare($sqlTx)) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $sale = $res->fetch_assoc();
    $res->free();
    $stmt->close();
}
if (!$sale) {
    http_response_code(404);
    echo 'Sale not found';
    exit;
}

// Items
$items = [];
$sqlItems = "SELECT i.name, i.unit, sti.quantity, sti.unit_price,
                    (sti.quantity * sti.unit_price) AS line_total
             FROM sales_transaction_items sti
             LEFT JOIN items i ON i.id = sti.item_id
             WHERE sti.sales_transaction_id = ?";
if ($stmtIt = $db->prepare($sqlItems)) {
    $stmtIt->bind_param('i', $id);
    $stmtIt->execute();
    $resIt = $stmtIt->get_result();
    while ($row = $resIt->fetch_assoc()) $items[] = $row;
    $resIt->free();
    $stmtIt->close();
}

// Debt (for remaining/paid computation)
$debt = null;
$sqlDebt = "SELECT id, total_amount, amount_paid, status FROM sales_debts WHERE sales_transaction_id = ? LIMIT 1";
if ($stmtDebt = $db->prepare($sqlDebt)) {
    $stmtDebt->bind_param('i', $id);
    $stmtDebt->execute();
    $resDebt = $stmtDebt->get_result();
    $debt = $resDebt->fetch_assoc();
    $resDebt->free();
    $stmtDebt->close();
}
$amountPaid = $debt ? (float)$debt['amount_paid'] : (float)$sale['total_amount'];
$remaining = $debt ? max(0.0, (float)$debt['total_amount'] - (float)$debt['amount_paid']) : 0.0;

// Payments history for receipt footer
$payments = [];
if ($stmtPays = $db->prepare(
    "SELECT pr.payment_date, pr.amount, pr.amount_tendered, pr.change_amount, pm.name AS method_name
     FROM payment_records pr
     LEFT JOIN payment_methods pm ON pm.id = pr.payment_method_id
     LEFT JOIN sales_debts sd ON sd.id = pr.sales_debt_id
     WHERE pr.sales_transaction_id = ? OR sd.sales_transaction_id = ?
     ORDER BY pr.payment_date ASC, pr.id ASC"
)) {
    $stmtPays->bind_param('ii', $id, $id);
    $stmtPays->execute();
    $resPays = $stmtPays->get_result();
    while ($row = $resPays->fetch_assoc()) $payments[] = $row;
    $resPays->free();
    $stmtPays->close();
}

$storeName = 'Abico Store';
$storeAddress = '';
$storeContact = '';
$cashier = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : ('User #' . (int)$_SESSION['user_id']));
$now = date('M d, Y h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Receipt #<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></title>
  <style>
    /* 80mm thermal receipt styles */
    :root {
      --w: 80mm;
      --fs: 11px;
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body { font: normal var(--fs)/1.35 "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background: #fff; color: #000; }
    .receipt { width: var(--w); margin: 0 auto; padding: 8px 10px; }
    .center { text-align: center; }
    .right { text-align: right; }
    .muted { color: #666; }
    .title { font-weight: 700; font-size: 14px; }
    .hr { border-top: 1px dashed #444; margin: 6px 0; }
    .row { display: flex; justify-content: space-between; gap: 8px; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 4px 0; font-size: var(--fs); }
    thead th { border-bottom: 1px dashed #444; font-weight: 600; }
    tfoot td { border-top: 1px dashed #444; }
    .totals td { padding: 2px 0; }
    .small { font-size: 10px; }
    .bold { font-weight: 700; }
    @media print {
      body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .no-print { display: none !important; }
      @page { size: 80mm auto; margin: 0; }
      .receipt { padding: 6px 8px; }
    }
    .btnbar { width: var(--w); margin: 8px auto 0; display: flex; gap: 8px; }
    .btnbar button { flex: 1; padding: 8px 10px; font-size: 12px; cursor: pointer; }
  </style>
</head>
<body>
  <div class="receipt">
    <div class="center">
      <div class="title"><?php echo htmlspecialchars($storeName); ?></div>
      <?php if ($storeAddress): ?><div class="small"><?php echo nl2br(htmlspecialchars($storeAddress)); ?></div><?php endif; ?>
      <?php if ($storeContact): ?><div class="small">Contact: <?php echo htmlspecialchars($storeContact); ?></div><?php endif; ?>
    </div>
    <div class="hr"></div>

    <div class="row small">
      <div>Invoice: #<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></div>
      <div class="right">Date: <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($sale['transaction_date']))); ?></div>
    </div>
    <div class="row small">
      <div>Cashier: <?php echo htmlspecialchars($cashier); ?></div>
      <div class="right">Status: <?php echo htmlspecialchars(ucfirst($sale['status'])); ?></div>
    </div>
    <div class="small">Customer: <?php echo htmlspecialchars($sale['customer_name']); ?></div>

    <div class="hr"></div>

    <table>
      <thead>
        <tr>
          <th class="left">Item</th>
          <th class="right">Qty</th>
          <th class="right">Price</th>
          <th class="right">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($items)): ?>
        <tr><td colspan="4" class="center muted small">No items</td></tr>
        <?php else: ?>
        <?php foreach ($items as $it): ?>
        <tr>
          <td><?php echo htmlspecialchars($it['name'] ?? 'Item'); ?></td>
          <td class="right mono"><?php echo number_format((float)$it['quantity'], 0); ?><?php echo $it['unit'] ? ' ' . htmlspecialchars($it['unit']) : ''; ?></td>
          <td class="right mono">₱<?php echo number_format((float)$it['unit_price'], 2); ?></td>
          <td class="right mono">₱<?php echo number_format((float)$it['line_total'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr class="totals">
          <td colspan="3" class="right bold">Grand Total</td>
          <td class="right mono bold">₱<?php echo number_format((float)$sale['total_amount'], 2); ?></td>
        </tr>
        <tr class="totals">
          <td colspan="3" class="right">Amount Paid</td>
          <td class="right mono">₱<?php echo number_format((float)$amountPaid, 2); ?></td>
        </tr>
        <tr class="totals">
          <td colspan="3" class="right">Balance</td>
          <td class="right mono">₱<?php echo number_format((float)$remaining, 2); ?></td>
        </tr>
      </tfoot>
    </table>

    <?php if (!empty($payments)): ?>
    <div class="hr"></div>
    <div class="bold small">Payments</div>
    <table>
      <thead>
        <tr>
          <th class="left small">Date</th>
          <th class="left small">Method</th>
          <th class="right small">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td class="small"><?php echo htmlspecialchars(date('m/d H:i', strtotime($p['payment_date']))); ?></td>
          <td class="small"><?php echo htmlspecialchars($p['method_name'] ?? ''); ?></td>
          <td class="right mono small">₱<?php echo number_format((float)$p['amount'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <?php if (!empty($sale['notes'])): ?>
    <div class="hr"></div>
    <div class="small">Notes: <?php echo nl2br(htmlspecialchars($sale['notes'])); ?></div>
    <?php endif; ?>

    <div class="hr"></div>
    <div class="center small">Thank you for your purchase!</div>
    <div class="center small muted">Printed <?php echo htmlspecialchars($now); ?></div>
  </div>

  <div class="btnbar no-print">
    <button onclick="window.print()">Print</button>
    <button onclick="window.close()">Close</button>
  </div>

  <?php if (isset($_GET['print']) && $_GET['print'] === '1'): ?>
  <script>
    window.addEventListener('load', function(){
      setTimeout(function(){ window.print(); }, 150);
    });
  </script>
  <?php endif; ?>
</body>
</html>
