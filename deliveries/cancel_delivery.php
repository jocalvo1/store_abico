<?php
require_once __DIR__ . '/../includes/database.php';

header_remove('X-Powered-By');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) { http_response_code(405); header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit; }
    header('Location: index.php'); exit;
}

$deliveryId = isset($_POST['delivery_id']) ? (int)$_POST['delivery_id'] : 0;
$reason = trim($_POST['cancellation_reason'] ?? '');

if ($deliveryId <= 0 || strlen($reason) < 5) {
    $msg = 'Invalid delivery or reason.';
    if ($isAjax) { http_response_code(400); header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => $msg]); exit; }
    header('Location: view.php?id=' . $deliveryId . '&error=' . urlencode($msg)); exit;
}

$conn = getDBConnection();

try {
    $stmt = $conn->prepare("UPDATE deliveries SET status='cancelled', cancel_reason=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param('si', $reason, $deliveryId);
    if (!$stmt->execute()) throw new Exception('Failed to cancel delivery: ' . $stmt->error);
    $stmt->close();

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Delivery cancelled successfully', 'redirect' => 'view.php?id=' . $deliveryId]);
        exit;
    }
    header('Location: view.php?id=' . $deliveryId . '&success=1');
    exit;
} catch (Exception $e) {
    if ($isAjax) { http_response_code(500); header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit; }
    header('Location: view.php?id=' . $deliveryId . '&error=' . urlencode($e->getMessage()));
    exit;
}
