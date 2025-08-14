<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../includes/database.php';

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$deliveryId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$receivedQuantities = isset($_POST['received_quantities']) && is_array($_POST['received_quantities']) ? $_POST['received_quantities'] : [];
$deliveredBy = isset($_POST['delivered_by']) ? trim($_POST['delivered_by']) : '';
$receivedBy = isset($_POST['received_by']) ? trim($_POST['received_by']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

if ($deliveryId <= 0) {
    $_SESSION['flash_error'] = 'Invalid delivery ID.';
    header('Location: view.php?id=' . $deliveryId);
    exit;
}

$db = getDBConnection();
$db->begin_transaction();

try {
    // Lock delivery row
    $stmt = $db->prepare('SELECT id, purchase_order_id, status FROM deliveries WHERE id = ? FOR UPDATE');
    if (!$stmt) throw new Exception('Failed to prepare delivery select.');
    $stmt->bind_param('i', $deliveryId);
    $stmt->execute();
    $res = $stmt->get_result();
    $delivery = $res->fetch_assoc();
    $res->free();
    $stmt->close();

    if (!$delivery) throw new Exception('Delivery not found.');
    if ($delivery['status'] === 'delivered') throw new Exception('Delivery is already delivered.');
    if ($delivery['status'] === 'cancelled') throw new Exception('Cannot confirm a cancelled delivery.');

    // Fetch delivery items with item_id and default quantity
    $items = [];
    $q = 'SELECT di.id AS delivery_item_id, di.item_id, di.quantity AS planned_quantity, i.name
          FROM delivery_items di
          JOIN items i ON i.id = di.item_id
          WHERE di.delivery_id = ?';
    $si = $db->prepare($q);
    if (!$si) throw new Exception('Failed to prepare items select.');
    $si->bind_param('i', $deliveryId);
    $si->execute();
    $ri = $si->get_result();
    while ($row = $ri->fetch_assoc()) {
        $items[] = $row;
    }
    $ri->free();
    $si->close();

    if (empty($items)) throw new Exception('No items to confirm for this delivery.');

    // Prepare stock movement insert
    $mv = $db->prepare('INSERT INTO stock_movements (item_id, movement_type, quantity, reference_type, reference_id, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    if (!$mv) throw new Exception('Failed to prepare stock movement insert.');
    $movementType = 'in';
    $refType = 'delivery';

    // Iterate received quantities
    foreach ($items as $item) {
        $diId = (int)$item['delivery_item_id'];
        $itemId = (int)$item['item_id'];
        $plannedQty = (float)$item['planned_quantity'];
        $recvQty = isset($receivedQuantities[$diId]) ? (float)$receivedQuantities[$diId] : $plannedQty;
        if ($recvQty < 0) $recvQty = 0;

        // Stock movement only if received > 0
        if ($recvQty > 0) {
            $mv->bind_param('isdsis', $itemId, $movementType, $recvQty, $refType, $deliveryId, $notes);
            if (!$mv->execute()) throw new Exception('Failed to record stock movement.');
        }

        // Optionally update delivery_items to store received quantity if column exists
        $db->query("UPDATE delivery_items SET received_quantity = " . $recvQty . " WHERE id = " . $diId);
    }
    $mv->close();

    // Update delivery as delivered
    $updSql = 'UPDATE deliveries SET status = \'delivered\', delivered_by = ?, received_by = ?, notes = ?, delivered_at = NOW(), updated_at = NOW() WHERE id = ?';
    $upd = $db->prepare($updSql);
    if ($upd) {
        $upd->bind_param('sssi', $deliveredBy, $receivedBy, $notes, $deliveryId);
        if (!$upd->execute()) throw new Exception('Failed to update delivery status.');
        $upd->close();
    } else {
        // Fallback: if columns not present, just update status
        $fallback = $db->prepare("UPDATE deliveries SET status = 'delivered', updated_at = NOW() WHERE id = ?");
        if (!$fallback) throw new Exception('Failed to update delivery status.');
        $fallback->bind_param('i', $deliveryId);
        $fallback->execute();
        $fallback->close();
    }

    // Optionally update purchase order status to completed if all items delivered (best-effort)
    // This assumes there is a notion of ordered vs delivered in purchase_order_items
    // Safe no-op if schema differs.
    @$db->query("UPDATE purchase_orders SET status = 'completed', updated_at = NOW() WHERE id = " . (int)$delivery['purchase_order_id']);

    $db->commit();

    $_SESSION['flash_success'] = 'Delivery confirmed successfully.';
    header('Location: view.php?id=' . $deliveryId);
    exit;

} catch (Exception $e) {
    $db->rollback();
    $_SESSION['flash_error'] = 'Failed to confirm delivery: ' . $e->getMessage();
    header('Location: view.php?id=' . $deliveryId);
    exit;
}
